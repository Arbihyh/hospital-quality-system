<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\FeeDetailed;
use App\Model\Icu;
use App\Model\MainDiagnosis;
use App\Model\MainOperation;
use App\Model\OtherDiagnosis;
use App\Model\PatientAdd;
use App\Model\PatientAddressInfo;
use App\Model\PatientContactsInfo;
use App\Model\PatientCostInfo;
use App\Model\PatientDoctorInfo;
use App\Model\PatientHospitalInfo;
use App\Model\PatientInfo;
use App\Model\PatientMedicalInfo;
use App\Model\PatientOtherInfo;
use App\Model\PatientWorkInfo;
use App\Model\SecondaryOperation;
use App\Model\Yzb;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class HomeData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:homeData {startTime?} {endTime?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public static $con;
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $username = env('ORACLE_USERNAME', 'zdyh');
        $password = env('ORACLE_PASSWORD', 'zdyh');
        $connection = env('ORACLE_HOST', '172.16.9.8');
        $port = env('ORACLE_PORT', '1521');
        $tns = env('ORACLE_TNS', 'his');
        $con = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        self::$con = $con;

        $ZYH = '';
        $startTime = $this->argument('startTime');
        $endTime = $this->argument('endTime');
        if (!empty($startTime) && !empty($endTime)) {
            $startTime = $startTime.' 00:00:00';
            $endTime = $endTime.' 23:59:59';
        } else {
            // 获取出院时间最大的时间
            $lastAAC01 = PatientInfo::query()->orderByDesc('AAC01')->value('AAC01');
            $startTime = date("Y-m-d H:i:s", (strtotime($lastAAC01)));

            $endTime = date("Y-m-d", time()).' 23:59:59';
        }

        // 获取用户信息
        $patientData = $this->getPatientInfo($ZYH,$startTime,$endTime);
        if (!empty($patientData)) {
            $config = config('dictionaries');
            foreach ($patientData as $patientInfo) {
                $ZYH = $patientInfo['MED_REC_ID'];

                $piInfo = PatientInfo::query()->where('MED_REC_ID','=',$ZYH)->first();
                if ($piInfo) {
                    continue;
                }

                echo $ZYH.PHP_EOL;

                // 获取费用信息
                $feeDetailedData = $this->getFyData($ZYH);
                if (!empty($feeDetailedData)) {
                    $this->addFeeDetailed($feeDetailedData);
                }

                // 获取重症监护（ICU）信息
                $IcuData = $this->getIcuInfo($ZYH);
                if (!empty($IcuData)) {
                    $this->addIcuData($IcuData);
                }

                // 获取诊断信息
                $diagnosisData = $this->getDiagnosisData($ZYH);
                $ABC03C = [];
                if (!empty($diagnosisData)) {
                    $ABC03C = $this->addDiagnosis($diagnosisData);
                }

                // 主信息
                $this->addPatientInfo($patientInfo, $ABC03C, $config);

                // 获取手术信息
                $operationData = $this->getOperationData($ZYH);
                if (!empty($operationData)) {
                    $this->addOperation($operationData, $config);
                }

                // 补充信息
                $buChongData = $this->getBuChong($ZYH);
                if (!empty($buChongData)) {
                    $this->addBuChong($buChongData[0]);
                }

                // 医嘱
//                $yzbData = $this->getYzb($ZYH);
//                if (!empty($yzbData)) {
//                    $this->addYzb($yzbData);
//                }
            }
        }
    }

    /**
     * 获取用户主信息
     * @return array
     */
    public function getPatientInfo($ZYH,$startTime,$endTime)
    {
        if (!empty($ZYH)) {
            $sql = "SELECT A.*,to_char(AAA03,'yyyy-mm-dd hh24:mi:ss') as AAA03,to_char(SYN_DATE,'yyyy-mm-dd hh24:mi:ss') as SYN_DATE,to_char(AAB01,'yyyy-mm-dd hh24:mi:ss') as AAB01,to_char(AAC01,'yyyy-mm-dd hh24:mi:ss') as AAC01,to_char(AED04,'yyyy-mm-dd hh24:mi:ss') as AED04,to_char(MT_JZRQ,'yyyy-mm-dd') as MT_JZRQ FROM PORTAL_HIS.INIT_MED_REC_MAIN A WHERE A.MED_REC_ID=".$ZYH." order by A.AAC01";
        } else {
            $sql = "SELECT A.*,to_char(AAA03,'yyyy-mm-dd hh24:mi:ss') as AAA03,to_char(SYN_DATE,'yyyy-mm-dd hh24:mi:ss') as SYN_DATE,to_char(AAB01,'yyyy-mm-dd hh24:mi:ss') as AAB01,to_char(AAC01,'yyyy-mm-dd hh24:mi:ss') as AAC01,to_char(AED04,'yyyy-mm-dd hh24:mi:ss') as AED04,to_char(MT_JZRQ,'yyyy-mm-dd') as MT_JZRQ FROM PORTAL_HIS.INIT_MED_REC_MAIN A WHERE AAC01 BETWEEN TO_DATE('".$startTime."', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('".$endTime."', 'yyyy-MM-dd HH24:mi:ss')"." order by A.AAC01";
        }

        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }

        return $result;
    }

    public function addPatientInfo($data, $ABC03C, $config)
    {
        // 主信息
        $hospital_name = config('confAdmin.hospital_name');
        $patient_info = [
            'hospital_name' => $data['ZA03'] ?: $hospital_name, //机构名称
            'AAA28' => $data['AAA28'],
            'AAA01' => $data['AAA01'] ? desensitize($data['AAA01'], 1, 1, '*') : '',        //患者姓名
            'AAA02C' => $data['AAA02C'] ?: '',      //患者性别
            'AAA03' => $data['AAA03'] ?: '',        //出生日期
            'AAA04' => $data['AAA04'] ?: '',        //年龄
            'AAA05C' => $data['AAA05C'] ?: '',      //国籍
            'AAA40' => $data['AAA40'] ?: '',        //不足一周岁年龄
            'AAA42' => $data['AAA42'] ?: '',        //新生儿入院体重
            'AEN01' => $data['AEN01'] ?: '',        //新生儿出生体重
            'AAA06C' => $data['AAA06C'] ?: '',      //民族代码
            'AAA07' => $data['AAA07'] ? desensitize($data['AAA07'], 6, 8, '*')  : '',        //身份证号
            'AAA08C' => $data['AAA08C'] ?: '',      //婚姻状况
            'AEM01C' => $data['AEM01C'] ?: '',      //离院方式代码
            'AAB01' => $data['AAB01'] ?: '',        //入院时间
            'AAC01' => $data['AAC01'] ?: '',        //出院时间
            'AAC11N' => $data['AAC11N'] ?: '',      //出院医院内部科室名称
            'AAC04' => $data['AAC04'] ?: '',        //实际住院
            'ADA01' => $data['ADA01'] ?: '',        //总费用
            'ADA0101' => $data['ADA0101'] ?: '',    //自付费用
            'AAA29' => $data['AAA29'] ?: '',        //住院次数
            'ABG01C' => $data['ABG01C'] ?: '',      //损伤和中毒外部原因编码 no
            'ABG01N' => $data['ABG01N'] ?: '',      //损伤和中毒外部原因名称 no
            'AAB06C' => $data['AAB06C'] ?: '',      //入院途径代码
            'ABC01N' => $data['ABC01N'] ?: '',      //出院主要诊断名称
            'ORG_STATE' => $data['ORG_STATE'] ?: '',//质控状态
            'AAA26C' => $data['AAA26C'] ?: '',      //医疗付费方式代码
            'ATTEND_GRP_CODE' => $data['ATTEND_GRP_CODE'] ?: '',    //主诊组编码
            'ATTEND_GRP_NAME' => $data['ATTEND_GRP_NAME'] ?: '',    //主诊组名称
        ];
        PatientInfo::query()->updateOrInsert(['MED_REC_ID'=>$data['MED_REC_ID']],$patient_info);

        // 其他信息
        $patient_other_info = [
            'AAB07C' => $data['AAB07C'] ?: '',      //入院诊断id
            'AAB07N' => $data['AAB07N'] ?: '',      //入院诊断名称
            'AAB07' => $data['AAB07'] ?: '',        //入院时情况
            'AAB07D' => $data['AAB07D'] ?: '',      //入院后确诊日期
            'ABD04' => $data['ABD04'] ?: '',        //医院感染名称
            'ABD051' => $data['ABD051'] ?: '',      //门诊与出院诊断符合情况
            'ABD052' => $data['ABD052'] ?: '',      //术前与术后诊断符合情况
            'ABD053' => $data['ABD053'] ?: '',      //临床与病理诊断符合情况
            'ABD054' => $data['ABD054'] ?: '',      //放射与病理诊断符合情况
            'ZB09' => $data['ZB09'] ?: '',          //手机
            'ZB08' => $data['ZB08'] ?: '',
            'ZB07' => $data['ZB07'] ?: '',
            'ZB06' => $data['ZB06'] ?: '',
            'ZB05' => $data['ZB05'] ?: '',
            'ZB04' => $data['ZB04'] ?: '',
            'ZB03' => $data['ZB03'] ?: '',
            'ZB02' => $data['ZB02'] ?: '',
            'ZB01C' => $data['ZB01C'] ?: '',
            'ZA04' => $data['ZA04'] ?: '',
            'MED_REC_ID' => $data['MED_REC_ID'] ?: '',  //病案⾸⻚ID
            'UNT_ID' => $data['UNT_ID'] ?: '',      //组织机构代码ID
            'ZA03' => $data['ZA03'] ?: '',          //机构名称
            'AFA01' => $data['AFA01'] ?: '',        //抢救次数
            'AFA02' => $data['AFA02'] ?: '',        //成本次数
            'AFA03' => $data['AFA03'] ?: '',
            'AFA04' => $data['AFA04'] ?: '',
            'AFA05' => $data['AFA05'] ?: '',
            'AFA06' => $data['AFA06'] ?: '',
            'AFA07' => $data['AFA07'] ?: '',
            'AFA08' => $data['AFA08'] ?: '',
            'AFA09' => $data['AFA09'] ?: '',
            'AFA10' => $data['AFA10'] ?: '',
            'AFA11' => $data['AFA11'] ?: '',
            'AFA12' => $data['AFA12'] ?: '',
            'ZB10' => $data['ZB10'] ?: '',          //填报版本
            'ZB11' => $data['ZB11'] ?: '',          //填报说明
            'IS_VALID' => $data['IS_VALID'] ?: '',  //有效标识
            'SYN_DATE' => $data['SYN_DATE'] ?: '',  //获取时间
            'QU_STATE' => $data['QU_STATE'] ?: '',  //是否采集
            'DATA_STATE' => $data['DATA_STATE'] ?: '',  //病案采集状态
            'BALANCEID' => $data['BALANCEID'] ?: '',    //病案流水号
            'AKC021' => $data['AKC021'] ?: '',      //⼈群类型
        ];
        PatientOtherInfo::query()->updateOrInsert(['AAA28'=>$data['MED_REC_ID']],$patient_other_info);

        $patient_medical_info = [
            'ABA01C' => $data['ABA01C'] ?: '',      //门(急)诊诊断编码
            'ABA01N' => $data['ABA01N'] ?: '',      //⻔（急）诊诊断名称
            'ABC03C' => empty($ABC03C[$data['MED_REC_ID']]) ? 4 : (array_search($ABC03C[$data['MED_REC_ID']],$config['RYQK']) ?: 4),    //入院病情代码
            'ABF01C' => $data['ABF01C'] ?: '',      //入院病情
            'ABF01N' => $data['ABF01N'] ?: '',      //病理诊断名称
            'ABF04' => $data['ABF04'] ?: '',        //病理号
            'ABF02C' => $data['ABF02C'] ?: '',      //最高诊断依据代码ID
            'ABF03C' => $data['ABF03C'] ?: '',      //分化程度编码ID
            'ABH01C' => $data['ABH01C'] ?: '',      //肿瘤分期是否不详
            'ABH0201C' => $data['ABH0201C'] ?: '',  //肿瘤分期 TID
            'ABH0202C' => $data['ABH0202C'] ?: '',  //肿瘤分期 NID
            'ABH0203C' => $data['ABH0203C'] ?: '',  //肿瘤分期 MID
            'ABH03C' => $data['ABH03C'] ?: '',      //0～Ⅳ肿瘤分期ID
            'AEB02C' => $data['AEB02C'] ?: '',      //有无药物过敏
            'AEB01' => $data['AEB01'] ?: '',        //过敏药物
            'AED01C' => $data['AED01C'] ?: '',      //病案质量代码ID
            'AEG01C' => $data['AEG01C'] ?: '',      //血型代码ID
            'AEG02C' => $data['AEG02C'] ?: '',      //Rh 代码ID
            'AEG04' => $data['AEG04'] ?: '',        //红细胞(单位)
            'AEG05' => $data['AEG05'] ?: '',        //血小板(袋)
            'AEG06' => $data['AEG06'] ?: '',        //血浆(ml)
            'AEG07' => $data['AEG07'] ?: '',        //全血(ml)
            'AEG08' => $data['AEG08'] ?: '',        //其它(ml)
            'AEJ01' => $data['AEJ01'] ?: '',        //颅脑损伤患者入院前昏迷时间（天）
            'AEJ02' => $data['AEJ02'] ?: '',        //颅脑损伤患者入院前昏迷时间（天）
            'AEJ03' => $data['AEJ03'] ?: '',        //颅脑损伤患者入院前昏迷时间（天）
            'AEJ04' => $data['AEJ04'] ?: '',        //颅脑损伤患者入院后昏迷时间（天）
            'AEJ05' => $data['AEJ05'] ?: '',        //颅脑损伤患者入院后昏迷时间（天）
            'AEJ06' => $data['AEJ06'] ?: '',        //颅脑损伤患者入院后昏迷时间（天）
            'AEL01' => $data['AEL01'] ?: '',        //呼吸机使用时间（天）
            'AEN02C' => $data['AEN02C'] ?: '',      //新生儿出生缺陷诊断
            'AEN02N' => $data['AEN02N'] ?: '',      //新生儿出生缺陷诊断名称
            'AEI09' => $data['AEI09'] ?: '',        //日常生活能力评定量得分
            'AEI10' => $data['AEI10'] ?: '',        //日常生活能力评定量得分
            'AEI08' => $data['AEI08'] ?: '',        //备注
        ];
        PatientMedicalInfo::query()->updateOrInsert(['AAA28'=>$data['MED_REC_ID']],$patient_medical_info);

        //患者住院信息
        $patient_hospital_info = [
            'AAA30' => $data['AAA30'] ?: '',    //住院号
            'ABC01C' => $data['ABC01C'] ?: '',  //出院时主要诊断编码
            'AAA27' => $data['AAA27'] ?: '-',   //医疗保险手册(卡)号
            'AAC001' => $data['AAC001'] ?: '',  //医保个人编号
            'AAB01' => $data['AAB01'] ?: '',    //入院时间（时）
            'AAB02C' => $data['AAB02C'] ?: '',  //入院科别代码
            'AAB03' => $data['AAB03'] ?: '',    //入院病房
            'AAB11C' => $data['AAB11C'] ?: '',  //入院医院内部科室代码ID
            'AAB11N' => $data['AAB11N'] ?: '',  //入院医院内部科室名称
            'AAC02C' => $data['AAC02C'] ?: '',  //出院科别代码ID
            'AAC03' => $data['AAC03'] ?: '',    //出院病房
            'AAC11C' => $data['AAC11C'] ?: '',  //出院医院内部科室代码ID
            'AAD01C' => $data['AAD01C'] ?: '',  //转经科别代码ID
            'AEM02' =>  $data['AEM02'] ?: '',   //医嘱转院、转社区、卫生院机编码ID
            'AEM03C' => $data['AEM03C'] ?: '',  //是否有出院31日内再住院计划
            'AEM04' => $data['AEM04'] ?: '',    //31日内再住院目的
            'AEI01C' => $data['AEI01C'] ?: '',  //是否尸检代码ID
        ];
        PatientHospitalInfo::query()->updateOrInsert(['AAA28'=>$data['MED_REC_ID']],$patient_hospital_info);

        //患者医生信息
        $patient_doctor_info = [
            'AED02' => $data['AED02'] ?: '',    //质控医师姓名
            'AED03' => $data['AED03'] ?: '',    //质控护士姓名
            'AED04' => $data['AED04'] ?: '',    //病案质量检查日期
            'AEE01' => $data['AEE01'] ?: '',    //科主任姓名
            'AEE02' => $data['AEE02'] ?: '',    //主(副主)任医师姓名
            'AEE03' => $data['AEE03'] ?: '',    //主治医师姓
            'AEE11' => $data['AEE11'] ?: '',    //主诊医师执业证书编码
            'AEE09' => $data['AEE09'] ?: '',    //主诊医师姓名
            'AEE04' => $data['AEE04'] ?: '',    //住院医师姓名
            'AEE05' => $data['AEE05'] ?: '',    //进修医师姓名
            'AEE07' => $data['AEE07'] ?: '',    //实习医师姓名
            'AEE08' => $data['AEE08'] ?: '',    //编码员姓名
            'AEE10' => $data['AEE10'] ?: '',    //责任护士姓名
            'AEE01_CODE' => $data['AEE01_CODE'] ?: '',  //科主任编码
            'AEE02_CODE' => $data['AEE02_CODE'] ?: '',  //主（副主）任医师工号
            'AEE03_CODE' => $data['AEE03_CODE'] ?: '',  //主治医师工号
            'AEE04_CODE' => $data['AEE04_CODE'] ?: '',  //住院医师工号
        ];
        PatientDoctorInfo::query()->updateOrInsert(['AAA28'=>$data['MED_REC_ID']],$patient_doctor_info);

        //患者地址相关信息
        $patient_address_info = [
            'AAA09' => $data['AAA09'] ?: '',    //出生地省
            'AAA10' => $data['AAA10'] ?: '',    //出生地市
            'AAA11' => $data['AAA11'] ?: '',    //出生地县
            'AAA43' => $data['AAA43'] ?: '',    //籍贯省
            'AAA44' => $data['AAA44'] ?: '',    //籍贯市
            'AAA45' => $data['AAA45'] ?: '',    //户籍省
            'AAA46' => $data['AAA46'] ?: '',    //户籍市
            'AAA47' => $data['AAA47'] ?: '',    //户籍县
            'AAA12' => $data['AAA12'] ?: '',    //户籍详细地址
            'AAA13C' => $data['AAA13C'] ?: '',  //户籍地址区县编码
            'AAA33C' => $data['AAA33C'] ?: '',  //户籍街道乡镇代码ID
            'AAA14C' => $data['AAA14C'] ?: '',  //户籍地址邮政编码
            'AAA15' => $data['AAA15'] ?: '',    //现住址详细地址
            'AAA48' => $data['AAA48'] ?: '',    //现住址省
            'AAA49' => $data['AAA49'] ?: '',    //现住址市
            'AAA50' => $data['AAA50'] ?: '',    //现住址县
            'AAA16C' => $data['AAA16C'] ?: '',  //现住址区县编码
            'AAA36C' => $data['AAA36C'] ?: '',  //现住址街道乡镇代码
            'AAA51' => $data['AAA51'] ? desensitize($data['AAA51'], 3, 4, '*') : '',    //现住址电话
            'AAA17C' => $data['AAA17C'] ?: '',  //现住址邮政编码
        ];
        PatientAddressInfo::query()->updateOrInsert(['AAA28'=>$data['MED_REC_ID']],$patient_address_info);

        //患者工作信息
        $patient_work_info = [
            'AAA18C' => $data['AAA18C'] ?: '',      //职业代码ID
            'AAA19' => $data['AAA19'] ?: '',        //工作单位及地址
            'AAA20' => $data['AAA20'] ? desensitize($data['AAA20'], 3, 4, '*') : '',        //工作单位电话
            'AAA21C' => $data['AAA21C'] ?: '',      //工作单位邮政编码
        ];
        PatientWorkInfo::query()->updateOrInsert(['AAA28'=>$data['MED_REC_ID']],$patient_work_info);

        //患者联系人信息
        $patient_contacts_info = [
            'AAA22' => $data['AAA22'] ? desensitize($data['AAA22'], 1, 1, '*') : '',    //联系人姓名
            'AAA23C' => $data['AAA23C'] ?: '',  //联系人关系代码ID
            'AAA24' => $data['AAA24'] ?: '',    //联系人地址
            'AAA25' => $data['AAA25'] ? desensitize($data['AAA25'], 3, 4, '*') : '',    //联系人电话
        ];
        PatientContactsInfo::query()->updateOrInsert(['AAA28'=>$data['MED_REC_ID']],$patient_contacts_info);
    }

    /**
     * 获取费用信息
     * @param $ZYH
     * @return array
     */
    public function getFyData($ZYH)
    {
        // 查询费用数据
        // ,FYGB,SYFYGB,ZJE
        $sql = "SELECT ZYH as AAA28,FYXH,FYMC,ZFJE,to_char(JFRQ,'yyyy-mm-dd hh24:mi:ss') as JFRQ,FYSL,FYDJ,FYKS FROM PORTAL_HIS.V_ZY_FYMX WHERE ZYH=".$ZYH;
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }

        return $result;
    }

    public function addFeeDetailed($data)
    {
        if (count($data) > 1000) {
            FeeDetailed::query()->where('AAA28','=',$data[0]['AAA28'])->delete();
            $index = 0;
            $insertData = [];
            foreach ($data as $val) {
                $insertData[] = [
                    'AAA28' => $val['AAA28'],//病案号
                    'FYXH' => $val['FYXH'] ?? '',//费用序号
                    'FYMC' => $val['FYMC'] ?? '',//费用名称
                    'ZFJE' => $val['ZFJE'] ?? '',//自付金额
                    'JFRQ' => $val['JFRQ'] ?? '',//计费日期
                    'FYSL' => $val['FYSL'] ?? '',//费用数量
                    'FYDJ' => $val['FYDJ'] ?? '',//费用单价
                    'ZJE' => $val['ZJE'] ?? '',//总金额
                    'FYKS' => $val['FYKS'] ?? '',//费用科室
//                'FYGB' => array_search($val['FYGB'], $config) ?? '',//费用归并
//                'SYFYGB' => array_search($val['SYFYGB'], $config) ?? '',//首页费用归并
                ];

                $index++;
                if ($index==1000) {
                    FeeDetailed::query()->insert($insertData);
                    $insertData = [];
                    $index = 0;
                }
            }

            if (!empty($insertData)) {
                FeeDetailed::query()->insert($insertData);
            }
        } else {
            $insertData = [];
            foreach ($data as $val) {
                $insertData[] = [
                    'AAA28' => $val['AAA28'],//病案号
                    'FYXH' => $val['FYXH'] ?? '',//费用序号
                    'FYMC' => $val['FYMC'] ?? '',//费用名称
                    'ZFJE' => $val['ZFJE'] ?? '',//自付金额
                    'JFRQ' => $val['JFRQ'] ?? '',//计费日期
                    'FYSL' => $val['FYSL'] ?? '',//费用数量
                    'FYDJ' => $val['FYDJ'] ?? '',//费用单价
                    'ZJE' => $val['ZJE'] ?? '',//总金额
                    'FYKS' => $val['FYKS'] ?? '',//费用科室
//                'FYGB' => array_search($val['FYGB'], $config) ?? '',//费用归并
//                'SYFYGB' => array_search($val['SYFYGB'], $config) ?? '',//首页费用归并
                ];
            }
            FeeDetailed::query()->where('AAA28','=',$data[0]['AAA28'])->delete();
            if (!empty($insertData)) {
                FeeDetailed::query()->insert($insertData);
            }
        }

    }

    /**
     * 获取重症监护（ICU）信息
     * @param $ZYH
     * @return array
     */
    public function getIcuInfo($ZYH)
    {
        $sql = "SELECT A.MED_REC_ID,A.AREA_ID,A.BATCH_ID,A.IS_MAIN_WAY,to_char(IN_TIME,'yyyy-mm-dd hh24:mi:ss') as IN_TIME,to_char(OUT_TIME,'yyyy-mm-dd hh24:mi:ss') as OUT_TIME FROM PORTAL_HIS.INIT_MED_REC_TUTORSSIP A WHERE A.MED_REC_ID=".$ZYH;
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }

        return $result;
    }

    public function addIcuData($data)
    {
        $insertData = [];
        foreach ($data as $val) {
            $insertData[] = [
                'AAA28' => $val['MED_REC_ID'],
                'IS_MAIN_WAY' => $val['IS_MAIN_WAY']??'',
                'IN_TIME' => $val['IN_TIME']??'',
                'OUT_TIME' => $val['OUT_TIME']??'',
                'AREA_ID' => $val['AREA_ID'] ?? 0,
                'BATCH_ID' => $val['BATCH_ID']??'',
            ];
        }

        Icu::query()->where('AAA28','=',$data[0]['MED_REC_ID'])->delete();
        if (!empty($insertData)){
            Icu::query()->insert($insertData);
        }
    }

    /**
     * 获取诊断信息
     * @param $ZYH
     * @return array
     */
    public function getDiagnosisData($ZYH)
    {
        $sql = "SELECT A.*,to_char(CYRQ,'yyyy-mm-dd hh24:mi:ss') as CYRQ FROM PORTAL_HIS.V_JMGS_BASY_ZD A WHERE ZYH=".$ZYH;
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }

        return $result;
    }

    public function addDiagnosis($data)
    {
        $main = []; $diagnosis = [];
        foreach ($data as $val){
            $addData = [
                'AAA28' => $val['ZYH'],
                'ICD10_ID1' => $val['ZDBM'],
                'ICD10_NAME' => $val['ZDMC'],
                'DIA_ORDER' => $val['ZDXH'],
                'LBMC' =>  $val['LBMC'],
                'RYQK' => $val['RYQK']
            ];

            if ($val['ZZPB'] == 1) {
                $main[] = $addData;
            } else {
                $diagnosis[] = $addData;
            }
        }

        // 主要诊断
        MainDiagnosis::query()->where('AAA28','=',$data[0]['ZYH'])->delete();
        if (!empty($main)){
            MainDiagnosis::query()->insert($main);
        }
        // 其他诊断
        OtherDiagnosis::query()->where('AAA28','=',$data[0]['ZYH'])->delete();
        if (!empty($diagnosis)){
            OtherDiagnosis::query()->insert($diagnosis);
        }

        return array_column($main,'RYQK','AAA28');
    }

    /**
     * 获取手术信息
     * @param $ZYH
     * @return array
     */
    public function getOperationData($ZYH)
    {
        $sql = "SELECT A.*,to_char(CYRQ,'yyyy-mm-dd hh24:mi:ss') as CYRQ,to_char(SSCZRQ,'yyyy-mm-dd hh24:mi:ss') as SSCZRQ,to_char(SSKSSJ,'yyyy-mm-dd hh24:mi:ss') as SSKSSJ,to_char(SSJSSJ,'yyyy-mm-dd hh24:mi:ss') as SSJSSJ FROM PORTAL_HIS.V_JMGS_BASY_SS A WHERE A.ZYH=".$ZYH;
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }

        return $result;
    }

    public function addOperation($data, $config)
    {
        $main = []; $other = [];
        foreach ($data as $val) {
            $addData = [
                'AAA28' => $val['ZYH'],
                'ICD9_ID1' => $val['SSCZBM'] ?? '',//手术或操作ID
                'ICD9_NAME' => $val['SSCZMC'] ?? '',//手术或操作名称
                'OPE_DATE' => $val['SSCZRQ'] ?? '',//手术或操作日期
                'OPE_ORDER' =>  $val['SSSX'] ?? '',//手术序号
                'OPE_LEVEL' => $val['SSJB'] ?? '',//手术级别
                'OPE_TYPE' => $val['SSLX'] ?? '',//手术类型
                'OPE_MAN_NAME' => $val['SZXM'] ?? '',//主刀医师姓名
                'OPE_MAN_CODE' => $val['SZBM'] ?? '',//主刀医师编码
                'FRIST_ASSISTANT_CODE' => $val['YZYSBM'] ?? '',//一助医师编码
                'FRIST_ASSISTANT_NAME' => $val['YZXM'] ?? '',//一助医师姓名
                'SECOND_ASSISTANT_CODE' => $val['EZYSBM'] ?? '',//二助医师编码
                'SECOND_ASSISTANT_NAME' => $val['EZXM'] ?? '',//二助医师姓名
                'INCISION_GRADE_ID' => $val['QKDJ'] ?? 100,//切口等级
                'HEAL_ID' => $val['YHDJ'] ?? 100,//愈合等级
                'HOCUS_WAY_ID' => $val['MZFS'] ?? '',//麻醉方式
                'HOCUS_MAN_CODE' => $val['MZYSBM'] ?? '',//麻醉医师编码
                'HOCUS_MAN_NAME' => $val['MZYSXM'] ?? '',//麻醉医师名称
                'START_TIME' => $val['SSKSSJ'] ?? '',//手术开始时间
                'END_TIME' => $val['SSJSSJ'] ?? '',//手术结束时间
                'RJSS' => $val['SFWRJSS'] ?? '',//是否日间手术
                'SSPB' => array_search($val['SSPB'],$config['SSPB']) ?? 5//手术判别
            ];

            if ($val['SFZYSS'] == 1) {
                $main[] = $addData;
            } else {
                $other[] = $addData;
            }
        }

        // 主要手术
        MainOperation::query()->where('AAA28','=',$data[0]['ZYH'])->delete();
        if (!empty($main)) {
            MainOperation::query()->insert($main);
        }
        // 其他手术
        SecondaryOperation::query()->where('AAA28','=',$data[0]['ZYH'])->delete();
        if (!empty($other)){
            SecondaryOperation::query()->insert($other);
        }
    }

    /**
     * 补充信息
     * @param $ZYH
     * @return array
     */
    public function getBuChong($ZYH)
    {
        $sql = "SELECT A.*,to_char(ZKRQ,'yyyy-mm-dd hh24:mi:ss') as ZKRQ1 FROM PORTAL_HIS.V_JMGS_BASY_FY A WHERE A.ZYH=".$ZYH;
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }

        return $result;
    }

    public function addBuChong($data)
    {
        $fee = [
            'AAA28' => $data['ZYH'],
            'ADA0101' => $info['ADA0101'] ?? 0,
            'D11' => $data['YBYLFWF'] ?? '',
            'D12' => $data['YBZLCZF'] ?? '',
            'D13' => $data['HLF'] ?? '',
            'D14' => $data['ZHYLFWLQTFY'] ?? '',
            'D15' => $data['BLZDF'] ?? '',
            'D16' => $data['SYSZDF'] ?? '',
            'D17' => $data['YXXZDF'] ?? '',
            'D18' => $data['LCZDXMF'] ?? '',
            'D19' => $data['FSSZLXMF'] ?? '',
            'D19X01' => $data['LCWLZLF'] ?? '',
            'D20' => $data['SSZLF'] ?? '',
            'D20X01' => $data['MZF'] ?? '',
            'D20X02' => $data['SSF'] ?? '',
            'D21' => $data['KFF'] ?? '',
            'D22' => $data['ZYZLF'] ?? '',
            'D23' => $data['XYF'] ?? '',
            'D23X01' => $data['KJYWF'] ?? '',
            'D24' => $data['ZCHENGYF'] ?? '',
            'D25' => $data['ZCAOYF'] ?? '',
            'D26' => $data['XF'] ?? '',
            'D27' => $data['BDBLZPF'] ?? '',
            'D28' => $data['QDBLZPF'] ?? '',
            'D29' => $data['NXYZLZPF'] ?? '',
            'D30' => $data['XBYZLZPF'] ?? '',
            'D31' => $data['JCYYCXYYCLF'] ?? '',
            'D32' => $data['ZLYYCXYYCLF'] ?? '',
            'D33' => $data['SSYYCXYYCLF'] ?? '',
            'D34' => $data['QTF'] ?? '',
        ];

        $add = [
            'AAA28' => $data['ZYH'],
            'TYSHXYDM' => $data['TYSHXYDM'] ?? '',
            'JKKH' => $data['JKKH'] ?? '',
            'SFZJLX' => $data['ZJLB'] ?? '',
            'SJHL' => $data['SJHL'] ?? '',
            'EJHL' => $data['EJHL'] ?? '',
            'YJHL' => $data['YJHL'] ?? '',
            'TJHL' => $data['TJHL'] ?? '',
            'ZRHS' => $data['ZRHS'] ?? '',
            'ZRHSBM' => $data['ZRHSBM'] ?? '',
            'ZKHS' => $data['ZKHS'] ?? '',
            'ZKHSBM' => $data['ZKHSBM'] ?? '',
            'ZKRQ' => $data['ZKRQ1'] ?? '',
            'ZHFZRYS' => $data['ZHFZRYS'] ?? '',
            'ZZYSBM' => $data['ZZYSBM'] ?? '',
            'ZYYSBM' => $data['ZYYSBM'] ?? '',
            'ZZZYSBM' => $data['ZZZYSBM'] ?? '',
            'KZRXM' => $data['KZRXM'] ?? '',
            'ZHFZRYSXM' => $data['ZHFZRYSXM'] ?? '',
            'ZZYSXM' => $data['ZZYSXM'] ?? '',
            'ZYYSXM' => $data['ZYYSXM'] ?? '',
            'ZZYISXM' => $data['ZZYISXM'] ?? '',
            'BMY' => $data['BMY'] ?? '',
            'RYKB' => $data['RYKB'] ?? '',
            'BFRY' => $data['BFRY'] ?? '',
            'ZKKB' => $data['ZKKB'] ?? '',
            'CYKB' => $data['CYKB'] ?? '',
            'HB' => $data['HB'] ?? '',
            'HCV' => $data['HCV'] ?? '',
            'HIV' => $data['HIV'] ?? '',
            'LCLJ' => $data['LCLJ'] ?? '', // 临床路径
            'WCQK' => $data['WCQK'] ?? '',
            'BYQK' => $data['BYQK'] ?? '',
        ];

        // 费用信息
        PatientCostInfo::query()->where('AAA28','=',$data['ZYH'])->delete();
        if (!empty($fee)) {
            PatientCostInfo::query()->insert($fee);
        }

        // 补充信息
        PatientAdd::query()->where('AAA28','=',$data['ZYH'])->delete();
        if (!empty($add)) {
            PatientAdd::query()->insert($add);
        }
    }

    /**
     * 医嘱
     * @param $ZYH
     * @return array
     */
    public function getYzb($ZYH)
    {
        $sql = "SELECT A.*,to_char(TZSJ,'yyyy-mm-dd hh24:mi:ss') as TJ,to_char(XZJDSJ,'yyyy-mm-dd hh24:mi:ss') as XJ,to_char(TZQRSJ,'yyyy-mm-dd hh24:mi:ss') as TZJ,to_char(APSJ,'yyyy-mm-dd hh24:mi:ss') as AJ,to_char(KZSJ,'yyyy-mm-dd hh24:mi:ss') as KJ,to_char(ZXSJ,'yyyy-mm-dd hh24:mi:ss') as ZJ FROM PORTAL_HIS.EMR_YZB A WHERE ZYH=".$ZYH;
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        return $result;
    }

    public function addYzb($data)
    {
        $insertData = [];
        foreach ($data as $val) {
            $insertData[] = [
                'ZYH' => $val['ZYH'],
                'YZBXH' => $val['YZBXH'] ?? '',
                'RID' => $val['RID'] ?? '',
                'YEPB' => $val['YEPB'] ?? '',
                'BRKS' =>  $val['BRKS'] ?? '',
                'BRBQ' => $val['BRBQ'] ?? '',
                'BRCH' => $val['BRCH'] ?? '',
                'YDYZLB' => $val['YDYZLB'] ?? '',
                'XMLB' => $val['XMLB'] ?? '',
                'XMID' => $val['XMID'] ?? '',
                'XMDJ' => $val['XMDJ'] ?? '',
                'YZZH' => $val['YZZH'] ?? '',
                'YZQX' => $val['YZQX'] ?? '',
                'YYSX' => $val['YYSX'] ?? '',
                'KZKS' => $val['KZKS'] ?? '',
                'KZYS' => $val['KZYS'] ?? '',
                'KZSJ' => $val['KJ'] ?? '',
                'YZMC' => $val['YZMC'] ?? '',
                'YPCD' => $val['YPCD'] ?? '',
                'FYSX' => $val['FYSX'] ?? '',
                'SYPC' => $val['SYPC'] ?? '',
                'GYTJ' => $val['GYTJ'] ?? '',
                'YCJL' => $val['YCJL'] ?? '',
                'JLDW' => $val['JLDW'] ?? '',
                'ZL' => $val['ZL'] ?? '',
                'ZLDW' => $val['ZLDW'] ?? '',
                'JJYZ' => $val['JJYZ'] ?? '',
                'BLYZ' => $val['BLYZ'] ?? '',
                'TZSJ' => $val['TJ'] ?? '',
                'TZYS' => $val['TZYS'] ?? '',
                'YZZT' => $val['YZZT'] ?? '',
                'ZXZT' => $val['ZXZT'] ?? '',
                'KZDY' => $val['KZDY'] ?? '',
                'ZTBZ' => $val['ZTBZ'] ?? '',
                'XZJDGH' => $val['XZJDGH'] ?? '',
                'XZJDSJ' => $val['XJ'] ?? '',
                'TZQRGH' => $val['TZQRGH'] ?? '',
                'TZQRSJ' => $val['TZJ'] ?? '',
                'APSJ' => $val['AJ'] ?? '',
                'YYTS' => $val['YYTS'] ?? '',
                'YSZT' => $val['YSZT'] ?? '',
                'SRCS' => $val['SRCS'] ?? '',
                'SRSD' => $val['SRSD'] ?? '',
                'ZXSD' => $val['ZXSD'] ?? '',
                'DS' => $val['DS'] ?? '',
                'DSDW' => $val['DSDW'] ?? '',
                'PSBZ' => $val['PSBZ'] ?? '',
                'PSJG' => $val['PSJG'] ?? '',
                'ZFPB' => $val['ZFPB'] ?? '',
                'YBLX' => $val['YBLX'] ?? '',
                'SPBH' => $val['SPBH'] ?? '',
                'CYJF' => $val['CYJF'] ?? '',
                'PLSX' => $val['PLSX'] ?? '',
                'CZBZ' => $val['CZBZ'] ?? '',
                'BZXX' => $val['BZXX'] ?? '',
                'SQDH' => $val['SQDH'] ?? '',
                'ZXKS' => $val['ZXKS'] ?? '',
                'YFGG' => $val['YFGG'] ?? '',
                'YFDW' => $val['YFDW'] ?? '',
                'YFBZ' => $val['YFBZ'] ?? '',
                'SFSJ' => $val['SFSJ'] ?? '',
                'YFYY' => $val['YFYY'] ?? '',
                'YFYYYY' => $val['YFYYYY'] ?? '',
                'QXKZ' => $val['QXKZ'] ?? '',
                'YYPS' => $val['YYPS'] ?? '',
                'FZLJ' => $val['FZLJ'] ?? '',
                'PASSINDEX' => $val['PASSINDEX'] ?? '',
                'QXMC' => $val['QXMC'] ?? '',
                'YZPLZH' => $val['YZPLZH'] ?? '',
                'LCTS' => $val['LCTS'] ?? '',
                'ZLFY' => $val['ZLFY'] ?? '',
                'YZLX' => $val['YZLX'] ?? '',
                'SSYZ' => $val['SSYZ'] ?? '',
                'CDA_PC' => $val['CDA_PC'] ?? '',
                'ZXSJ' => $val['ZJ'] ?? '',
                'NWARN' => $val['NWARN'] ?? ''
            ];
        }

        Yzb::query()->where('ZYH','=',$data[0]['ZYH'])->delete();
        if (!empty($insertData)){
            Yzb::query()->insert($insertData);
        }
    }



}
