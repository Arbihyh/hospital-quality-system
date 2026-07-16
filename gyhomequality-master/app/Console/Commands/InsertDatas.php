<?php

namespace App\Console\Commands;

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
use App\Model\FeeDetailed;
use App\Model\SecondaryOperation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Pheanstalk\Pheanstalk;

class InsertDatas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:insert';

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

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        ini_set('default_socket_timeout', 0);
        $config = config('dictionaries');
        $start = '20230302';
        while (true) {
            if ($start == date('Ymd')) {
                break;
            }
            echo $start . "\n";
            $start_page = 0;
            $array = [];
            $Id = self::selectList($start);
            while (1) {
                $end_page = $start_page + 9;
                $info = self::selectInsertData($Id, $start_page, $end_page);
                if (empty($info['data'])) {
                    break;
                }
                $list1 = array_column($info['data'], 'MED_REC_ID');
                $list = array_diff($list1, $array);
                $rData = [];
                foreach ($info['data'] as $value) {
                    if (!in_array($value['MED_REC_ID'], $array)) {
                        $rData[] = $value;
                    }
                }
                $array = array_merge($array, $list);
                //in查询用的字符串
                $strList = implode(',', $list);
                //费用查询 返回查询数据
                $feeData = self::selectFeeDetail($strList);
                if (!empty($feeData)) {
                    //费用数据入库
                    self::feeDetail($feeData, $config);
                }
                //手术数据查询
                $operationData = self::selectMainOperation($strList);
                if (!empty($operationData)) {
                    //手术数据入库
                    self::mainOperation($operationData, $config);
                }
                //诊断数据查询
                $diagnosis = self::selectDiagnosis($strList);
                $ABC03C = [];
                if (!empty($diagnosis)) {
                    //诊断数据入库
                    $ABC03C = self::diagnosis($diagnosis);
                }
                //患者信息入库
                self::insertDataMain($rData, $info['inData'], $ABC03C, $config);
                //费用信息查询
                $feeInfo = self::selectFeeInfo($strList);
                if (!empty($feeInfo)) {
                    $ZF = array_column($rData, 'ADA0101', 'MED_REC_ID');
                    //费用信息入库
                    self::feeInfo($feeInfo, $ZF);
                }
                //icu信息查询
                $icu = self::selectIcuInfo($strList);
                if (!empty($icu)) {
                    //icu信息入库
                    self::icuInfo($icu);
                }
                $list = self::selectYzb($strList);
                self::Yzb($list);
                $start_page += 10;
                //加入质控队列
                self::putBeanstalkd($list);
            }
            sleep(1);
            $start = date('Ymd', strtotime($start) + 86400);
        }
    }

    public static function selectList($start)
    {
        $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS', 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $sql1 = "SELECT MED_REC_ID FROM PORTAL_HIS.INIT_MED_REC_MAIN WHERE AAC01 BETWEEN to_date(" . $start . "000000,'yyyy-MM-dd HH24:mi:ss') AND to_date(" . $start . "235959,'yyyy-MM-dd HH24:mi:ss') GROUP BY MED_REC_ID ORDER BY MED_REC_ID ASC";
        $result = oci_parse($con, $sql1);
        oci_execute($result, OCI_DEFAULT);
        $ID = [];
        while ($row = oci_fetch_assoc($result)) {
            $ID[] = $row;
        }
        return $ID;
    }

    //查询患者信息
    public static function selectInsertData($ID, $start_page, $end_page)
    {
        try {

            $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS', 'UTF8');
            if (!$con) {
                $e = oci_error();
                print_r(htmlentities($e['message'])) . "\n";
                die;
            }
            $list = [];
            for ($i = $start_page; $i <= $end_page; $i++) {
                if (!isset($ID[$i])) {
                    continue;
                }
                $list[] = $ID[$start_page];
            }
            $list = implode(',', $list);
            echo $list . "\n";
            $sql = "SELECT * FROM PORTAL_HIS.INIT_MED_REC_MAIN a WHERE AAC01 IN ($list)";
            $result = oci_parse($con, $sql);
            oci_execute($result, OCI_DEFAULT);
            $data = [];
            while ($row = oci_fetch_assoc($result)) {
                $data[] = $row;
            }
            $in_sql = "SELECT to_char(AAA03,'yyyy-mm-dd hh24:mi:ss') as AAA03,to_char(SYN_DATE,'yyyy-mm-dd hh24:mi:ss') as SYN_DATE,to_char(AAB01,'yyyy-mm-dd hh24:mi:ss') as AAB01,to_char(AAC01,'yyyy-mm-dd hh24:mi:ss') as AAC01,to_char(AAC01,'yyyy-mm-dd hh24:mi:ss') as AAC01,MED_REC_ID FROM PORTAL_HIS.INIT_MED_REC_MAIN WHERE MED_REC_ID IN (" . $list . ")";
            $inResult = oci_parse($con, $in_sql);
            oci_execute($inResult, OCI_DEFAULT);
            $inData = [];
            while ($row = oci_fetch_assoc($inResult)) {
                $inData[] = $row;
            }
            $inData = array_column($inData, null, 'MED_REC_ID');
            unset($con);
        } catch (\Throwable $e) {
            var_dump($e->getMessage());
            return ['data' => [], 'inData' => []];
        }
        return ['data' => $data, 'inData' => $inData];
    }

    //患者信息
    public static function insertDataMain($data, $inData, $ABC03C, $config)
    {
        foreach ($data as $v) {
            $patient_info['AAA28'] = $v['AAA28'];
            $patient_info['MED_REC_ID'] = $v['MED_REC_ID'];
            $patient_info['AAA01'] = $v['AAA01'] ?: '';//患者姓名
            $patient_info['AAA02C'] = $v['AAA02C'] ?: '';//患者性别
            $patient_info['AAA03'] = $inData[$v['MED_REC_ID']]['AAA03'] ?: '';//出生日期
            $patient_info['AAA04'] = $v['AAA04'] ?: '';//年龄
            $patient_info['AAA05C'] = $v['AAA05C'] ?: '';//国籍
            $patient_info['AAA40'] = $v['AAA40'] ?: '';//不足一周岁年龄
            $patient_info['AAA42'] = $v['AAA42'] ?: '';//新生儿入院体重
            $patient_info['AEN01'] = $v['AEN01'] ?: '';//新生儿出生体重
            $patient_info['AAA06C'] = $v['AAA06C'] ?: '';//民族代码
            $patient_info['AAA07'] = $v['AAA07'] ?: '';//身份证号
            $patient_info['AAA08C'] = $v['AAA08C'] ?: '';//婚姻状况
            $patient_info['AEM01C'] = $v['AEM01C'] ?: '';//离院方式代码
            $patient_info['AAC01'] = $inData[$v['MED_REC_ID']]['AAC01'] ?: '';//出院时间
            $patient_info['AAC11N'] = $v['AAC11N'] ?: '';//出院医院内部科室名称
            $patient_info['AAC04'] = $v['AAC04'] ?: '';//实际住院
            $patient_info['ADA01'] = $v['ADA01'] ?: '';//总费用
            $patient_info['ADA0101'] = $v['ADA0101'] ?: '';//自付费用
            $patient_info['AAA29'] = $v['AAA29'] ?: '';//住院次数
            $patient_info['ABG01C'] = $v['ABG01C'] ?: '';//损伤和中毒外部原因编码 no
            $patient_info['ABG01N'] = $v['ABG01N'] ?: '';//损伤和中毒外部原因名称 no
            $patient_info['AAB06C'] = $v['AAB06C'] ?: '';//入院途径代码
            $patient_info['ABC01N'] = $v['ABC01N'] ?: '';//出院主要诊断名称
            $patient_info['ORG_STATE'] = $v['ORG_STATE'] ?: '';//质控状态
            $patient_info['AAA26C'] = $v['AAA26C'] ?: '';//医疗付费方式代码
            $patient_info['ATTEND_GRP_CODE'] = $v['ATTEND_GRP_CODE'] ?: '';//主诊组编码
            $patient_info['ATTEND_GRP_NAME'] = $v['ATTEND_GRP_NAME'] ?: '';//主诊组名称


            PatientInfo::query()->insert($patient_info);

            $patient_other_info['AAA28'] = $v['MED_REC_ID'];
            $patient_other_info['AAB07C'] = $v['AAB07C'] ?: '';//入院诊断id
            $patient_other_info['AAB07N'] = $v['AAB07N'] ?: '';//入院诊断名称
            $patient_other_info['AAB07'] = $v['AAB07'] ?: '';//入院时情况
            $patient_other_info['AAB07D'] = $v['AAB07D'] ?: '';//入院后确诊日期
            $patient_other_info['ABD04'] = $v['ABD04'] ?: '';//医院感染名称
            $patient_other_info['ABD051'] = $v['ABD051'] ?: '';//门诊与出院诊断符合情况
            $patient_other_info['ABD052'] = $v['ABD052'] ?: '';//术前与术后诊断符合情况
            $patient_other_info['ABD053'] = $v['ABD053'] ?: '';//临床与病理诊断符合情况
            $patient_other_info['ABD054'] = $v['ABD054'] ?: '';//放射与病理诊断符合情况
            $patient_other_info['ZB09'] = $v['ZB09'] ?: '';//手机
            $patient_other_info['ZB08'] = $v['ZB08'] ?: '';//
            $patient_other_info['ZB07'] = $v['ZB07'] ?: '';//
            $patient_other_info['ZB06'] = $v['ZB06'] ?: '';//
            $patient_other_info['ZB05'] = $v['ZB05'] ?: '';//
            $patient_other_info['ZB04'] = $v['ZB04'] ?: '';//
            $patient_other_info['ZB03'] = $v['ZB03'] ?: '';//
            $patient_other_info['ZB02'] = $v['ZB02'] ?: '';//
            $patient_other_info['ZB01C'] = $v['ZB01C'] ?: '';//
            $patient_other_info['ZA04'] = $v['ZA04'] ?: '';//
            $patient_other_info['MED_REC_ID'] = $v['MED_REC_ID'] ?: '';//病案⾸⻚ID
            $patient_other_info['UNT_ID'] = $v['UNT_ID'] ?: '';//组织机构代码ID
            $patient_other_info['ZA03'] = $v['ZA03'] ?: '';//机构名称
            $patient_other_info['AFA01'] = $v['AFA01'] ?: '';//抢救次数
            $patient_other_info['AFA02'] = $v['AFA02'] ?: '';//成本次数
            $patient_other_info['AFA03'] = $v['AFA03'] ?: '';//
            $patient_other_info['AFA04'] = $v['AFA04'] ?: '';//
            $patient_other_info['AFA05'] = $v['AFA05'] ?: '';//
            $patient_other_info['AFA06'] = $v['AFA06'] ?: '';//
            $patient_other_info['AFA07'] = $v['AFA07'] ?: '';//
            $patient_other_info['AFA08'] = $v['AFA08'] ?: '';//
            $patient_other_info['AFA09'] = $v['AFA09'] ?: '';//
            $patient_other_info['AFA10'] = $v['AFA10'] ?: '';//
            $patient_other_info['AFA11'] = $v['AFA11'] ?: '';//
            $patient_other_info['AFA12'] = $v['AFA12'] ?: '';//
            $patient_other_info['ZB10'] = $v['ZB10'] ?: '';//填报版本
            $patient_other_info['ZB11'] = $v['ZB11'] ?: '';//填报说明
            $patient_other_info['IS_VALID'] = $v['IS_VALID'] ?: '';//有效标识
            $patient_other_info['SYN_DATE'] = $inData[$v['MED_REC_ID']]['SYN_DATE'] ?: '';//获取时间
            $patient_other_info['QU_STATE'] = $v['QU_STATE'] ?: '';//是否采集
            $patient_other_info['DATA_STATE'] = $v['DATA_STATE'] ?: '';//病案采集状态
            $patient_other_info['BALANCEID'] = $v['BALANCEID'] ?: '';//病案流水号
            $patient_other_info['AKC021'] = $v['AKC021'] ?: '';//⼈群类型

            PatientOtherInfo::query()->insert($patient_other_info);

            $patient_medical_info['AAA28'] = $v['MED_REC_ID'];
            $patient_medical_info['ABA01C'] = $v['ABA01C'] ?: '';//门(急)诊诊断编码
            $patient_medical_info['ABA01N'] = $v['ABA01N'] ?: '';//⻔（急）诊诊断名称
            $patient_medical_info['ABC03C'] = empty($ABC03C[$v['MED_REC_ID']]) ? 4 : (array_search($ABC03C[$v['MED_REC_ID']], $config['RYQK']) ?: 4);//入院病情代码
            $patient_medical_info['ABF01C'] = $v['ABF01C'] ?: '';//入院病情
            $patient_medical_info['ABF01N'] = $v['ABF01N'] ?: '';//病理诊断名称
            $patient_medical_info['ABF04'] = $v['ABF04'] ?: '';//病理号
            $patient_medical_info['ABF02C'] = $v['ABF02C'] ?: '';//最高诊断依据代码ID
            $patient_medical_info['ABF03C'] = $v['ABF03C'] ?: '';//分化程度编码ID
            $patient_medical_info['ABH01C'] = $v['ABH01C'] ?: '';//肿瘤分期是否不详
            $patient_medical_info['ABH0201C'] = $v['ABH0201C'] ?: '';//肿瘤分期 TID
            $patient_medical_info['ABH0202C'] = $v['ABH0202C'] ?: '';//肿瘤分期 NID
            $patient_medical_info['ABH0203C'] = $v['ABH0203C'] ?: '';//肿瘤分期 MID
            $patient_medical_info['ABH03C'] = $v['ABH03C'] ?: '';//0～Ⅳ肿瘤分期ID
            $patient_medical_info['AEB02C'] = $v['AEB02C'] ?: '';//有无药物过敏
            $patient_medical_info['AEB01'] = $v['AEB01'] ?: '';//过敏药物
            $patient_medical_info['AED01C'] = $v['AED01C'] ?: '';//病案质量代码ID
            $patient_medical_info['AEG01C'] = $v['AEG01C'] ?: '';//血型代码ID
            $patient_medical_info['AEG02C'] = $v['AEG02C'] ?: '';//Rh 代码ID
            $patient_medical_info['AEG04'] = $v['AEG04'] ?: '';//红细胞(单位)
            $patient_medical_info['AEG05'] = $v['AEG05'] ?: '';//血小板(袋)
            $patient_medical_info['AEG06'] = $v['AEG06'] ?: '';//血浆(ml)
            $patient_medical_info['AEG07'] = $v['AEG07'] ?: '';//全血(ml)
            $patient_medical_info['AEG08'] = $v['AEG08'] ?: '';//其它(ml)
            $patient_medical_info['AEJ01'] = $v['AEJ01'] ?: '';//颅脑损伤患者入院前昏迷时间（天）
            $patient_medical_info['AEJ02'] = $v['AEJ02'] ?: '';//颅脑损伤患者入院前昏迷时间（天）
            $patient_medical_info['AEJ03'] = $v['AEJ03'] ?: '';//颅脑损伤患者入院前昏迷时间（天）
            $patient_medical_info['AEJ04'] = $v['AEJ04'] ?: '';//颅脑损伤患者入院后昏迷时间（天）
            $patient_medical_info['AEJ05'] = $v['AEJ05'] ?: '';//颅脑损伤患者入院后昏迷时间（天）
            $patient_medical_info['AEJ06'] = $v['AEJ06'] ?: '';//颅脑损伤患者入院后昏迷时间（天）
            $patient_medical_info['AEL01'] = $v['AEL01'] ?: '';//呼吸机使用时间（天）
            $patient_medical_info['AEN02C'] = $v['AEN02C'] ?: '';//新生儿出生缺陷诊断
            $patient_medical_info['AEN02N'] = $v['AEN02N'] ?: '';//新生儿出生缺陷诊断名称
            $patient_medical_info['AEI09'] = $v['AEI09'] ?: '';//日常生活能力评定量得分
            $patient_medical_info['AEI10'] = $v['AEI10'] ?: '';//日常生活能力评定量得分
            $patient_medical_info['AEI08'] = $v['AEI08'] ?: '';//备注
            PatientMedicalInfo::query()->insert($patient_medical_info);


            //患者住院信息
            $patient_hospital_info['AAA28'] = $v['MED_REC_ID'];
            $patient_hospital_info['AAA30'] = $v['AAA30'] ?: '';//住院号
            $patient_hospital_info['ABC01C'] = $v['ABC01C'] ?: '';//出院时主要诊断编码
            $patient_hospital_info['AAA27'] = $v['AAA27'] ?: '-';//医疗保险手册(卡)号
            $patient_hospital_info['AAC001'] = $v['AAC001'] ?: '';//医保个人编号
            $patient_hospital_info['AAB01'] = $inData[$v['MED_REC_ID']]['AAB01'] ?: '';//入院时间（时）
            $patient_hospital_info['AAB02C'] = $v['AAB02C'] ?: '';//入院科别代码
            $patient_hospital_info['AAB03'] = $v['AAB03'] ?: '';//入院病房
            $patient_hospital_info['AAB11C'] = $v['AAB11C'] ?: '';//入院医院内部科室代码ID
            $patient_hospital_info['AAB11N'] = $v['AAB11N'] ?: '';//入院医院内部科室名称
            $patient_hospital_info['AAC02C'] = $v['AAC02C'] ?: '';//出院科别代码ID
            $patient_hospital_info['AAC03'] = $v['AAC03'] ?: '';//出院病房
            $patient_hospital_info['AAC11C'] = $v['AAC11C'] ?: '';//出院医院内部科室代码ID
            $patient_hospital_info['AAD01C'] = $v['AAD01C'] ?: '';//转经科别代码ID
            $patient_hospital_info['AEM02'] = $v['AEM02'] ?: '';//医嘱转院、转社区、卫生院机编码ID
            $patient_hospital_info['AEM03C'] = $v['AEM03C'] ?: '';//是否有出院31日内再住院计划
            $patient_hospital_info['AEM04'] = $v['AEM04'] ?: '';//31日内再住院目的
            $patient_hospital_info['AEI01C'] = $v['AEI01C'] ?: '';//是否尸检代码ID
            PatientHospitalInfo::query()->insert($patient_hospital_info);


            //患者医生信息
            $patient_doctor_info['AAA28'] = $v['MED_REC_ID'];
            $patient_doctor_info['AED02'] = $v['AED02'] ?: '';//质控医师姓名
            $patient_doctor_info['AED03'] = $v['AED03'] ?: '';//质控护士姓名
            $patient_doctor_info['AED04'] = $v['AED04'] ?: '';//病案质量检查日期
            $patient_doctor_info['AEE01'] = $v['AEE01'] ?: '';//科主任姓名
            $patient_doctor_info['AEE02'] = $v['AEE02'] ?: '';//主(副主)任医师姓名
            $patient_doctor_info['AEE03'] = $v['AEE03'] ?: '';//主治医师姓
            $patient_doctor_info['AEE11'] = $v['AEE11'] ?: '';//主诊医师执业证书编码
            $patient_doctor_info['AEE09'] = $v['AEE09'] ?: '';//主诊医师姓名
            $patient_doctor_info['AEE04'] = $v['AEE04'] ?: '';//住院医师姓名
            $patient_doctor_info['AEE05'] = $v['AEE05'] ?: '';//进修医师姓名
            $patient_doctor_info['AEE07'] = $v['AEE07'] ?: '';//实习医师姓名
            $patient_doctor_info['AEE08'] = $v['AEE08'] ?: '';//编码员姓名
            $patient_doctor_info['AEE10'] = $v['AEE10'] ?: '';//责任护士姓名
            $patient_doctor_info['AEE01_CODE'] = $v['AEE01_CODE'] ?: '';//科主任编码
            $patient_doctor_info['AEE02_CODE'] = $v['AEE02_CODE'] ?: '';//主（副主）任医师工号
            $patient_doctor_info['AEE03_CODE'] = $v['AEE03_CODE'] ?: '';//主治医师工号
            $patient_doctor_info['AEE04_CODE'] = $v['AEE04_CODE'] ?: '';//住院医师工号
            PatientDoctorInfo::query()->insert($patient_doctor_info);


            //患者地址相关信息
            $patient_address_info['AAA28'] = $v['MED_REC_ID'];
            $patient_address_info['AAA09'] = $v['AAA09'] ?: '';//出生地省（区、市）
            $patient_address_info['AAA10'] = $v['AAA10'] ?: '';//出生地市
            $patient_address_info['AAA11'] = $v['AAA11'] ?: '';//出生地县
            $patient_address_info['AAA43'] = $v['AAA43'] ?: '';//籍贯省（区、市
            $patient_address_info['AAA44'] = $v['AAA44'] ?: '';//籍贯市
            $patient_address_info['AAA45'] = $v['AAA45'] ?: '';//户籍省（区、市）
            $patient_address_info['AAA46'] = $v['AAA46'] ?: '';//户籍市
            $patient_address_info['AAA47'] = $v['AAA47'] ?: '';//户籍县
            $patient_address_info['AAA12'] = $v['AAA12'] ?: '';//户籍详细地址
            $patient_address_info['AAA13C'] = $v['AAA13C'] ?: '';//户籍地址区县编码
            $patient_address_info['AAA33C'] = $v['AAA33C'] ?: '';//户籍街道乡镇代码ID
            $patient_address_info['AAA14C'] = $v['AAA14C'] ?: '';//户籍地址邮政编码
            $patient_address_info['AAA15'] = $v['AAA15'] ?: '';//现住址详细地址
            $patient_address_info['AAA48'] = $v['AAA48'] ?: '';//现住址省（区、市）
            $patient_address_info['AAA49'] = $v['AAA49'] ?: '';//现住址市
            $patient_address_info['AAA50'] = $v['AAA50'] ?: '';//现住址县
            $patient_address_info['AAA16C'] = $v['AAA16C'] ?: '';//现住址区县编码
            $patient_address_info['AAA36C'] = $v['AAA36C'] ?: '';//现住址街道乡镇代码
            $patient_address_info['AAA51'] = $v['AAA51'] ?: '';//现住址电话
            $patient_address_info['AAA17C'] = $v['AAA17C'] ?: '';//现住址邮政编码
            PatientAddressInfo::query()->insert($patient_address_info);


            //患者工作信息
            $patient_work_info['AAA28'] = $v['MED_REC_ID'];
            $patient_work_info['AAA18C'] = $v['AAA18C'] ?: '';//职业代码ID
            $patient_work_info['AAA19'] = $v['AAA19'] ?: '';//工作单位及地址
            $patient_work_info['AAA20'] = $v['AAA20'] ?: '';//工作单位电话
            $patient_work_info['AAA21C'] = $v['AAA21C'] ?: '';//工作单位邮政编码
            PatientWorkInfo::query()->insert($patient_work_info);


            //患者联系人信息
            $patient_contacts_info['AAA28'] = $v['MED_REC_ID'];
            $patient_contacts_info['AAA22'] = $v['AAA22'] ?: '';//联系人姓名
            $patient_contacts_info['AAA23C'] = $v['AAA23C'] ?: '';//联系人关系代码ID
            $patient_contacts_info['AAA24'] = $v['AAA24'] ?: '';//联系人地址
            $patient_contacts_info['AAA25'] = $v['AAA25'] ?: '';//联系人电话
            PatientContactsInfo::query()->insert($patient_contacts_info);
        }
    }

    //查询费用明细
    public static function selectFeeDetail($list)
    {
        $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS', "UTF8");
        $sql = "SELECT ZYH,FYXH,FYMC,to_char(ZFJE,'fm9999990.000') as ZFJE,to_char(JFRQ,'yyyy-mm-dd hh24:mi:ss') as JFRQ,FYSL,to_char(FYDJ,'fm9999990.000') as FYDJ,to_char(ZJE,'fm9999990.000') as ZJE,FYKS,FYGB,SYFYGB FROM PORTAL_HIS.V_JMGS_BASY_FYMX WHERE ZYH IN (" . $list . ") ";
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $data = oci_parse($con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        unset($con);
        return $result;
    }

    //费用明细
    public static function feeDetail($data, $config)
    {
        $fee = [];
        foreach ($data as $item) {
            $fee[] = [
                'AAA28' => $item['ZYH'],//病案号
                'FYXH' => $item['FYXH'] ?? '',//费用序号
                'FYMC' => $item['FYMC'] ?? '',//费用名称
                'ZFJE' => $item['ZFJE'] ?? '',//自付金额
                'JFRQ' => $item['JFRQ'] ?? '',//计费日期
                'FYSL' => $item['FYSL'] ?? '',//费用数量
                'FYDJ' => $item['FYDJ'] ?? '',//费用单价
                'ZJE' => $item['ZJE'] ?? '',//总金额
                'FYKS' => $item['FYKS'] ?? '',//费用科室
                'FYGB' => array_search($item['FYGB'], $config) ?? '',//费用归并
                'SYFYGB' => array_search($item['SYFYGB'], $config) ?? '',//首页费用归并
            ];
        }
        $start = 0;
        while (1) {
            $temFee = array_slice($fee, $start, 10);
            if (empty($temFee)) {
                break;
            }
            FeeDetailed::query()->insert($temFee);
            $start += 10;
        }

    }

    //手术数据查询
    public static function selectMainOperation($list)
    {
        $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS', "UTF8");
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $sql = "SELECT ZYH,SSCZBM,to_char(SSCZRQ,'yyyy-mm-dd hh24:mi:ss') as SSCZRI,SFZYSS,SSSX,SSCZMC,SSJB,SSLX,SZXM,SZBM,YZYSBM,YZXM,EZXM,EZYSBM,QKDJ,YHDJ,MZFS,MZYSXM,MZYSBM,to_char(SSKSSJ,'yyyy-mm-dd hh24:mi:ss') as SSKSSJ,to_char(SSJSSJ,'yyyy-mm-dd hh24:mi:ss') as SSJSSJ,SFWRJSS,SSPB FROM PORTAL_HIS.V_JMGS_BASY_SS WHERE ZYH IN (" . $list . ")";
        $data = oci_parse($con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        unset($con);
        return $result;
    }

    //手术数据
    public static function mainOperation($data, $config)
    {
        foreach ($data as $item) {
            if ($item['SFZYSS'] == 1) {
                $main[] = [
                    'AAA28' => $item['ZYH'],
                    'ICD9_ID1' => $item['SSCZBM'] ?? '',//手术或操作ID
                    'ICD9_NAME' => $item['SSCZMC'] ?? '',//手术或操作名称
                    'OPE_DATE' => $item['SSCZRI'] ?? '',//手术或操作日期
                    'OPE_ORDER' => $item['SSSX'] ?? '',//手术序号
                    'OPE_LEVEL' => $item['SSJB'] ?? '',//手术级别
                    'OPE_TYPE' => $item['SSLX'] ?? '',//手术类型
                    'OPE_MAN_NAME' => $item['SZXM'] ?? '',//主刀医师姓名
                    'OPE_MAN_CODE' => $item['SZBM'] ?? '',//主刀医师编码
                    'FRIST_ASSISTANT_CODE' => $item['YZYSBM'] ?? '',//一助医师编码
                    'FRIST_ASSISTANT_NAME' => $item['YZXM'] ?? '',//一助医师姓名
                    'SECOND_ASSISTANT_CODE' => $item['EZYSBM'] ?? '',//二助医师编码
                    'SECOND_ASSISTANT_NAME' => $item['EZXM'] ?? '',//二助医师姓名
                    'INCISION_GRADE_ID' => $item['QKDJ'] ?? 100,//切口等级
                    'HEAL_ID' => $item['YHDJ'] ?? 100,//愈合等级
                    'HOCUS_WAY_ID' => $item['MZFS'] ?? '',//麻醉方式
                    'HOCUS_MAN_CODE' => $item['MZYSBM'] ?? '',//麻醉医师编码
                    'HOCUS_MAN_NAME' => $item['MZYSXM'] ?? '',//麻醉医师名称
                    'START_TIME' => $item['SSKSSJ'] ?? '',//手术开始时间
                    'END_TIME' => $item['SSJSSJ'] ?? '',//手术结束时间
                    'RJSS' => $item['SFWRJSS'] ?? '',//是否日间手术
                    'SSPB' => array_search($item['SSPB'], $config['SSPB']) ?? 5//手术判别
                ];
            } else {
                $other[] = [
                    'AAA28' => $item['ZYH'],
                    'ICD9_ID1' => $item['SSCZBM'] ?? '',//手术或操作ID
                    'ICD9_NAME' => $item['SSCZMC'] ?? '',//手术或操作名称
                    'OPE_DATE' => $item['SSCZRI'] ?? '',//手术或操作日期
                    'OPE_ORDER' => $item['SSSX'] ?? '',//手术序号
                    'OPE_LEVEL' => $item['SSJB'] ?? '',//手术级别
                    'OPE_TYPE' => $item['SSLX'] ?? '',//手术类型
                    'OPE_MAN_NAME' => $item['SZXM'] ?? '',//主刀医师姓名
                    'OPE_MAN_CODE' => $item['SZBM'] ?? '',//主刀医师编码
                    'FRIST_ASSISTANT_CODE' => $item['YZYSBM'] ?? '',//一助医师编码
                    'FRIST_ASSISTANT_NAME' => $item['YZXM'] ?? '',//一助医师姓名
                    'SECOND_ASSISTANT_CODE' => $item['EZYSBM'] ?? '',//二助医师编码
                    'SECOND_ASSISTANT_NAME' => $item['EZXM'] ?? '',//二助医师姓名
                    'INCISION_GRADE_ID' => $item['QKDJ'] ?? 100,//切口等级
                    'HEAL_ID' => $item['YHDJ'] ?? 100,//愈合等级
                    'HOCUS_WAY_ID' => $item['MZFS'] ?? '',//麻醉方式
                    'HOCUS_MAN_CODE' => $item['MZYSBM'] ?? '',//麻醉医师编码
                    'HOCUS_MAN_NAME' => $item['MZYSXM'] ?? '',//麻醉医师名称
                    'START_TIME' => $item['SSKSSJ'] ?? '',//手术开始时间
                    'END_TIME' => $item['SSJSSJ'] ?? '',//手术结束时间
                    'RJSS' => $item['SFWRJSS'] ?? '',//是否日间手术
                    'SSPB' => array_search($item['SSPB'], $config['SSPB']) ?? 5//手术判别
                ];
            }
        }
        if (!empty($main)) {
            MainOperation::query()->insert($main);
        }
        if (!empty($other)) {
            SecondaryOperation::query()->insert($other);
        }
    }

    //诊断数据查询
    public static function selectDiagnosis($list)
    {
        $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS', "UTF8");
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $sql1 = "SELECT * FROM PORTAL_HIS.V_JMGS_BASY_ZD WHERE ZYH IN (" . $list . ")";
        $data = oci_parse($con, $sql1);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        unset($con);
        return $result;
    }

    //诊断数据写入
    public static function diagnosis($data)
    {
        $main = [];
        $diagnosis = [];
        foreach ($data as $item) {
            if ($item['ZZPB'] == 1) {
                $main[] = [
                    'AAA28' => $item['ZYH'],
                    'ICD10_ID1' => $item['ZDBM'],
                    'ICD10_NAME' => $item['ZDMC'],
                    'DIA_ORDER' => $item['ZDXH'],
                    'LBMC' => $item['LBMC'],
                    'RYQK' => $item['RYQK']
                ];
            } else {
                $diagnosis[] = [
                    'AAA28' => $item['ZYH'],
                    'ICD10_ID1' => $item['ZDBM'],
                    'ICD10_NAME' => $item['ZDMC'],
                    'DIA_ORDER' => $item['ZDXH'],
                    'LBMC' => $item['LBMC'],
                    'RYQK' => $item['RYQK']
                ];
            }
        }
        if (!empty($diagnosis)) {
            OtherDiagnosis::query()->insert($diagnosis);
        }
        if (!empty($main)) {
            MainDiagnosis::query()->insert($main);
        }
        return array_column($main, 'RYQK', 'AAA28');
    }

    //费用信息
    public static function selectFeeInfo($list)
    {
        $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS', 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $sql = "SELECT A.*,to_char(ZKRQ,'yyyy-mm-dd hh24:mi:ss') as ZKRQ1 FROM PORTAL_HIS.V_JMGS_BASY_FY A WHERE ZYH IN ($list)";
        $data = oci_parse($con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        unset($con);
        return $result;
    }

    //费用信息
    public static function feeInfo($data, $ZF)
    {
        $fee = [];
        $add = [];
        foreach ($data as $item) {
            $fee[] = [
                'AAA28' => $item['ZYH'],
                'ADA0101' => $ZF[$item['ZYH']] ?? 0,
                'D11' => $item['YBYLFWF'] ?? '',
                'D12' => $item['YBZLCZF'] ?? '',
                'D13' => $item['HLF'] ?? '',
                'D14' => $item['ZHYLFWLQTFY'] ?? '',
                'D15' => $item['BLZDF'] ?? '',
                'D16' => $item['SYSZDF'] ?? '',
                'D17' => $item['YXXZDF'] ?? '',
                'D18' => $item['LCZDXMF'] ?? '',
                'D19' => $item['FSSZLXMF'] ?? '',
                'D19X01' => $item['LCWLZLF'] ?? '',
                'D20' => $item['SSZLF'] ?? '',
                'D20X01' => $item['MZF'] ?? '',
                'D20X02' => $item['SSF'] ?? '',
                'D21' => $item['KFF'] ?? '',
                'D22' => $item['ZYZLF'] ?? '',
                'D23' => $item['XYF'] ?? '',
                'D23X01' => $item['KJYWF'] ?? '',
                'D24' => $item['ZCHENGYF'] ?? '',
                'D25' => $item['ZCAOYF'] ?? '',
                'D26' => $item['XF'] ?? '',
                'D27' => $item['BDBLZPF'] ?? '',
                'D28' => $item['QDBLZPF'] ?? '',
                'D29' => $item['NXYZLZPF'] ?? '',
                'D30' => $item['XBYZLZPF'] ?? '',
                'D31' => $item['JCYYCXYYCLF'] ?? '',
                'D32' => $item['ZLYYCXYYCLF'] ?? '',
                'D33' => $item['SSYYCXYYCLF'] ?? '',
                'D34' => $item['QTF'] ?? '',
            ];
            $add[] = [
                'AAA28' => $item['ZYH'],
                'TYSHXYDM' => $item['TYSHXYDM'] ?? '',
                'JKKH' => $item['JKKH'] ?? '',
                'SFZJLX' => $item['ZJLB'] ?? '',
                'SJHL' => $item['SJHL'] ?? '',
                'EJHL' => $item['EJHL'] ?? '',
                'YJHL' => $item['YJHL'] ?? '',
                'TJHL' => $item['TJHL'] ?? '',
                'ZRHS' => $item['ZRHS'] ?? '',
                'ZRHSBM' => $item['ZRHSBM'] ?? '',
                'ZKHS' => $item['ZKHS'] ?? '',
                'ZKHSBM' => $item['ZKHSBM'] ?? '',
                'ZKRQ' => $item['ZKRQ1'] ?? '',
                'ZHFZRYS' => $item['ZHFZRYS'] ?? '',
                'ZZYSBM' => $item['ZZYSBM'] ?? '',
                'ZYYSBM' => $item['ZYYSBM'] ?? '',
                'ZZZYSBM' => $item['ZZZYSBM'] ?? '',
                'KZRXM' => $item['KZRXM'] ?? '',
                'ZHFZRYSXM' => $item['ZHFZRYSXM'] ?? '',
                'ZZYSXM' => $item['ZZYSXM'] ?? '',
                'ZYYSXM' => $item['ZYYSXM'] ?? '',
                'ZZYISXM' => $item['ZZYISXM'] ?? '',
                'BMY' => $item['BMY'] ?? '',
                'RYKB' => $item['RYKB'] ?? '',
                'BFRY' => $item['BFRY'] ?? '',
                'ZKKB' => $item['ZKKB'] ?? '',
                'CYKB' => $item['CYKB'] ?? '',
                'HB' => $item['HB'] ?? '',
                'HCV' => $item['HCV'] ?? '',
                'HIV' => $item['HIV'] ?? '',
                'LCLJ' => $item['LCLJ'] ?? '', // 临床路径
                'WCQK' => $item['WCQK'] ?? '',
                'BYQK' => $item['BYQK'] ?? '',
            ];
        }
        if (!empty($fee)) {
            PatientCostInfo::query()->insert($fee);
        }
        if (!empty($add)) {
            PatientAdd::query()->insert($add);
        }
    }

    //查询icu信息
    public static function selectIcuInfo($list)
    {
        $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS', "UTF8");
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $sql = "SELECT MED_REC_ID,AREA_ID,BATCH_ID,IS_MAIN_WAY,to_char(IN_TIME,'yyyy-mm-dd hh24:mi:ss') as IN_TIME,to_char(OUT_TIME,'yyyy-mm-dd hh24:mi:ss') as OUT_TIME FROM PORTAL_HIS.INIT_MED_REC_TUTORSSIP WHERE MED_REC_ID IN (" . $list . ")";
        $data = oci_parse($con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        unset($con);
        return $result;
    }

    //icu信息入库
    public static function icuInfo($data)
    {
        $icu = [];
        foreach ($data as $item) {
            $icu[] = [
                'AAA28' => $item['MED_REC_ID'],
                'IS_MAIN_WAY' => $item['IS_MAIN_WAY'] ?? '',
                'IN_TIME' => $item['IN_TIME'] ?? '',
                'OUT_TIME' => $item['OUT_TIME'] ?? '',
                'AREA_ID' => $item['AREA_ID'] ?? 0,
                'BATCH_ID' => $item['BATCH_ID'] ?? '',
            ];
        }
        if (!empty($icu)) {
            \App\Model\Icu::query()->insert($icu);
        }
    }

    //医嘱本数据查询
    public static function selectYzb($list)
    {
        $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS', "UTF8");
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $sql = "SELECT A.*,to_char(TZSJ,'yyyy-mm-dd hh24:mi:ss') as TJ,to_char(XZJDSJ,'yyyy-mm-dd hh24:mi:ss') as XJ,to_char(TZQRSJ,'yyyy-mm-dd hh24:mi:ss') as TZJ,to_char(APSJ,'yyyy-mm-dd hh24:mi:ss') as AJ,to_char(KZSJ,'yyyy-mm-dd hh24:mi:ss') as KJ,to_char(ZXSJ,'yyyy-mm-dd hh24:mi:ss') as ZJ FROM PORTAL_HIS.EMR_YZB A WHERE ZYH IN (" . $list . ")";
        $data = oci_parse($con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        unset($con);
        return $result;
    }

    //医嘱本数据
    public static function Yzb($data)
    {
        foreach ($data as $item) {
            $list = [
                'ZYH' => $item['ZYH'],
                'YZBXH' => $item['YZBXH'] ?? '',
                'RID' => $item['RID'] ?? '',
                'YEPB' => $item['YEPB'] ?? '',
                'BRKS' => $item['BRKS'] ?? '',
                'BRBQ' => $item['BRBQ'] ?? '',
                'BRCH' => $item['BRCH'] ?? '',
                'YDYZLB' => $item['YDYZLB'] ?? '',
                'XMLB' => $item['XMLB'] ?? '',
                'XMID' => $item['XMID'] ?? '',
                'XMDJ' => $item['XMDJ'] ?? '',
                'YZZH' => $item['YZZH'] ?? '',
                'YZQX' => $item['YZQX'] ?? '',
                'YYSX' => $item['YYSX'] ?? '',
                'KZKS' => $item['KZKS'] ?? '',
                'KZYS' => $item['KZYS'] ?? '',
                'KZSJ' => $item['KJ'] ?? '',
                'YZMC' => $item['YZMC'] ?? '',
                'YPCD' => $item['YPCD'] ?? '',
                'FYSX' => $item['FYSX'] ?? '',
                'SYPC' => $item['SYPC'] ?? '',
                'GYTJ' => $item['GYTJ'] ?? '',
                'YCJL' => $item['YCJL'] ?? '',
                'JLDW' => $item['JLDW'] ?? '',
                'ZL' => $item['ZL'] ?? '',
                'ZLDW' => $item['ZLDW'] ?? '',
                'JJYZ' => $item['JJYZ'] ?? '',
                'BLYZ' => $item['BLYZ'] ?? '',
                'TZSJ' => $item['TJ'] ?? '',
                'TZYS' => $item['TZYS'] ?? '',
                'YZZT' => $item['YZZT'] ?? '',
                'ZXZT' => $item['ZXZT'] ?? '',
                'KZDY' => $item['KZDY'] ?? '',
                'ZTBZ' => $item['ZTBZ'] ?? '',
                'XZJDGH' => $item['XZJDGH'] ?? '',
                'XZJDSJ' => $item['XJ'] ?? '',
                'TZQRGH' => $item['TZQRGH'] ?? '',
                'TZQRSJ' => $item['TZJ'] ?? '',
                'APSJ' => $item['AJ'] ?? '',
                'YYTS' => $item['YYTS'] ?? '',
                'YSZT' => $item['YSZT'] ?? '',
                'SRCS' => $item['SRCS'] ?? '',
                'SRSD' => $item['SRSD'] ?? '',
                'ZXSD' => $item['ZXSD'] ?? '',
                'DS' => $item['DS'] ?? '',
                'DSDW' => $item['DSDW'] ?? '',
                'PSBZ' => $item['PSBZ'] ?? '',
                'PSJG' => $item['PSJG'] ?? '',
                'ZFPB' => $item['ZFPB'] ?? '',
                'YBLX' => $item['YBLX'] ?? '',
                'SPBH' => $item['SPBH'] ?? '',
                'CYJF' => $item['CYJF'] ?? '',
                'PLSX' => $item['PLSX'] ?? '',
                'CZBZ' => $item['CZBZ'] ?? '',
                'BZXX' => $item['BZXX'] ?? '',
                'SQDH' => $item['SQDH'] ?? '',
                'ZXKS' => $item['ZXKS'] ?? '',
                'YFGG' => $item['YFGG'] ?? '',
                'YFDW' => $item['YFDW'] ?? '',
                'YFBZ' => $item['YFBZ'] ?? '',
                'SFSJ' => $item['SFSJ'] ?? '',
                'YFYY' => $item['YFYY'] ?? '',
                'YFYYYY' => $item['YFYYYY'] ?? '',
                'QXKZ' => $item['QXKZ'] ?? '',
                'YYPS' => $item['YYPS'] ?? '',
                'FZLJ' => $item['FZLJ'] ?? '',
                'PASSINDEX' => $item['PASSINDEX'] ?? '',
                'QXMC' => $item['QXMC'] ?? '',
                'YZPLZH' => $item['YZPLZH'] ?? '',
                'LCTS' => $item['LCTS'] ?? '',
                'ZLFY' => $item['ZLFY'] ?? '',
                'YZLX' => $item['YZLX'] ?? '',
                'SSYZ' => $item['SSYZ'] ?? '',
                'CDA_PC' => $item['CDA_PC'] ?? '',
                'ZXSJ' => $item['ZJ'] ?? '',
                'NWARN' => $item['NWARN'] ?? ''
            ];
            \App\Model\Yzb::query()->insert($list);
        }
    }

    //质控队列
    public static function putBeanstalkd($list)
    {
        $beanstalkd = Pheanstalk::create('beanstalkd')->useTube('validate');
        foreach ($list as $item) {
            $beanstalkd->put(json_encode(['AAA28' => $item]), 100, 600);
        }
    }
}
