<?php

namespace App\Services;

use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
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
use App\Model\QualitySendMsgLog;
use App\Model\SecondaryOperation;
use App\Model\SSSQ;
use App\Model\SyncRecord;
use App\Model\Yzb;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class HomeDataService2
{

    public static $con;
    public static $conHis;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function index($startTime = '', $endTime = '', $day = 1)
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





        $username2 = 'zdyh';
        $password2 = 'zdyh';
        $connection2 = '172.16.2.1';
        $port2 = '1521';
        $tns2 = 'his';
        $con2 = oci_connect($username2, $password2, $connection2 . ':' . $port2 . '/' . $tns2, 'UTF8');
        if (!$con2) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        self::$conHis = $con2;

        $timeIndex = $day == 1 ? 1685548800 : time() - 5 * 24 * 3600;

        $ZYHList = '';
        if (!empty($startTime) && !empty($endTime)) {
            $startTime = strtotime($startTime);
            $endTime = strtotime($endTime . ' 23:59:59');
        } else {
            $startTime = $timeIndex;
            $endTime = time();
        }

        while (true) {
            // 如果数据同步到最新时间，则重新从2022年开始同步
            if ($startTime >= time()) {
                echo date("Y-m-d H:i:s", time()) . "完成一轮数据同步\n";
                $startTime = $timeIndex;
            }
            // 获取用户信息
            $patientData = $this->getPatientInfo($ZYHList, $startTime, $day);
            $startTime += 86400;
            if (!empty($patientData)) {
                $config = config('dictionaries');
                foreach ($patientData as $patientInfo) {


                    $ZYH = $patientInfo['MED_REC_ID'];

                    file_put_contents(storage_path() . '\homedata1.log', $ZYH . "\r\n", FILE_APPEND);


                    //$piInfo = PatientInfo::query()->where('MED_REC_ID', '=', $ZYH)->first();
                    //if ($piInfo) {
//                        continue;
               // }
                    echo $ZYH . ' - ' . date('Y-m-d H:i:s', time()) . PHP_EOL;


                    // 获取 手术申请 信息
                    $smsssqData = $this->getSssqData($ZYH);
                    if (!empty($smsssqData)) {
                        $this->addSmSssq($smsssqData);
                    }

                    // 获取费用信息
                    $this->getFyData($ZYH);

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
                        $this->addBuChong($ZYH, $buChongData[0]);
                    }

                    // 医嘱
                    $yzbData = $this->getYzb($ZYH);
                    if (!empty($yzbData)) {
                        $this->addYzb($yzbData);
                    }

                    // 医生签名
                    //$this->addBLSY($ZYH);

                    // 护士分床时间
                    $this->ZY_HCMX($ZYH);

                    // 会诊信息
                    $this->YS_ZY_HZYJ($ZYH);

                    // 麻醉记录
                    $this->mzjl($ZYH);

                    // 入院途径
                    $this->BaBrsy($ZYH);

                    // 医技表同步
                    $this->YJ_ZY01($ZYH);

                    // 病案首页（临床）
                    $this->EMR_BL_BASYSJ($ZYH);

                    // 检验报告单
                    $this->V_JMGS_TESTRESULT($ZYH);

                    // 检验（药敏）
                    $this->V_JMGS_YMresult($ZYH);

                }
            }
        }
    }

    /**
     * 获取用户主信息
     * @return array
     */
    public function getPatientInfo($ZYH, $startTime, $day)
    {
        $column = $day == 1 ? 'AAC01' : 'DSG_LDR_TIME';
        $startTimeStr = date("Ymd", $startTime);
        if (!empty($ZYH)) {
            $sql = "SELECT A.*,to_char(AAA03,'yyyy-mm-dd hh24:mi:ss') as AAA03,to_char(SYN_DATE,'yyyy-mm-dd hh24:mi:ss') as SYN_DATE,to_char(AAB01,'yyyy-mm-dd hh24:mi:ss') as AAB01,to_char(AAC01,'yyyy-mm-dd hh24:mi:ss') as AAC01,to_char(AED04,'yyyy-mm-dd hh24:mi:ss') as AED04,to_char(MT_JZRQ,'yyyy-mm-dd') as MT_JZRQ FROM PORTAL_HIS.INIT_MED_REC_MAIN A WHERE A.MED_REC_ID=" . $ZYH . " order by A.AAC01";
        } else {
            $sql = "SELECT A.ZA03,A.AAA28,A.AAA01,A.AAA01,A.AAA02C,A.AAA03,A.AAA04,A.AAA05C,A.AAA40,A.AAA42,A.AEN01,A.AAA06C,A.AAA07,A.AAA07,A.AAA08C,A.AEM01C,A.AAB01,A.AAC01,A.AAC11N,A.AAC04,A.ADA01,A.ADA0101,A.AAA29,A.ABG01C,A.ABG01N,A.AAB06C,A.ABC01N,A.ORG_STATE,A.AAA26C,A.ATTEND_GRP_CODE,A.ATTEND_GRP_NAME,A.AAB07C,A.AAB07N,A.AAB07,A.AAB07D,A.ABD04,A.ABD051,A.ABD052,A.ABD053,A.ABD054,A.ZB09,A.ZB08,A.ZB07,A.ZB06,A.ZB05,A.ZB04,A.ZB03,A.ZB02,A.ZB01C,A.ZA04,A.UNT_ID,A.ZA03,A.AFA01,A.AFA02,A.AFA03,A.AFA04,A.AFA05,A.AFA06,A.AFA07,A.AFA08,A.AFA09,A.AFA10,A.AFA11,A.AFA12,A.ZB10,A.ZB11,A.IS_VALID,A.SYN_DATE,A.QU_STATE,A.DATA_STATE,A.BALANCEID,A.AKC021,A.ABA01C,A.ABA01N,A.ABC03C,A.ABF01C,A.ABF01N,A.ABF04,A.ABF02C,A.ABF03C,A.ABH01C,A.ABH0201C,A.ABH0202C,A.ABH0203C,A.ABH03C,A.AEB02C,A.AEB01,A.AED01C,A.AEG01C,A.AEG02C,A.AEG04,A.AEG05,A.AEG06,A.AEG07,A.AEG08,A.AEJ01,A.AEJ02,A.AEJ03,A.AEJ04,A.AEJ05,A.AEJ06,A.AEL01,A.AEN02C,A.AEN02N,A.AEI09,A.AEI10,A.AEI08,A.AAA30,A.ABC01C,A.AAA27,A.AAC001,A.AAB01,A.AAB02C,A.AAB03,A.AAB11C,A.AAB11N,A.AAC02C,A.AAC03,A.AAC11C,A.AAD01C,A.AEM02,A.AEM03C,A.AEM04,A.AEI01C,A.AED02,A.AED03,A.AEE01,A.AEE02,A.AEE03,A.AEE11,A.AEE09,A.AEE04,A.AEE05,A.AEE07,A.AEE08,A.AEE10,A.AEE01_CODE,A.AEE02_CODE,A.AEE03_CODE,A.AEE04_CODE,A.AAA09,A.AAA10,A.AAA11,A.AAA43,A.AAA44,A.AAA45,A.AAA46,A.AAA47,A.AAA12,A.AAA13C,A.AAA33C,A.AAA14C,A.AAA15,A.AAA48,A.AAA49,A.AAA50,A.AAA16C,A.AAA36C,A.AAA51,A.AAA51,A.AAA17C,A.AAA18C,A.AAA19,A.AAA20,A.AAA20,A.AAA21C,A.AAA22,A.AAA22,A.AAA23C,A.AAA24,A.AAA25,A.AAA25,A.MED_REC_ID,to_char(A.AAA03,'yyyy-mm-dd hh24:mi:ss') as AAA03,to_char(A.SYN_DATE,'yyyy-mm-dd hh24:mi:ss') as SYN_DATE,to_char(A.AAB01,'yyyy-mm-dd hh24:mi:ss') as AAB01,to_char(A.AAC01,'yyyy-mm-dd hh24:mi:ss') as AAC01,to_char(A.AED04,'yyyy-mm-dd hh24:mi:ss') as AED04,to_char(A.MT_JZRQ,'yyyy-mm-dd') as MT_JZRQ FROM PORTAL_HIS.INIT_MED_REC_MAIN A WHERE {$column} BETWEEN TO_DATE('" . $startTimeStr . "000000', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('" . $startTimeStr . "235959', 'yyyy-MM-dd HH24:mi:ss')";
//            $sql = "SELECT A.*,to_char(AAA03,'yyyy-mm-dd hh24:mi:ss') as AAA03,to_char(SYN_DATE,'yyyy-mm-dd hh24:mi:ss') as SYN_DATE,to_char(AAB01,'yyyy-mm-dd hh24:mi:ss') as AAB01,to_char(AAC01,'yyyy-mm-dd hh24:mi:ss') as AAC01,to_char(AED04,'yyyy-mm-dd hh24:mi:ss') as AED04,to_char(MT_JZRQ,'yyyy-mm-dd') as MT_JZRQ FROM PORTAL_HIS.INIT_MED_REC_MAIN A WHERE AAC01 BETWEEN TO_DATE('".$startTimeStr."000000', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('".$startTimeStr."235959', 'yyyy-MM-dd HH24:mi:ss')"." order by A.AAC01";
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
            'AAA07' => $data['AAA07'] ? desensitize($data['AAA07'], 6, 8, '*') : '',        //身份证号
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
        PatientInfo::query()->updateOrInsert(['MED_REC_ID' => $data['MED_REC_ID']], $patient_info);

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
        PatientOtherInfo::query()->updateOrInsert(['AAA28' => $data['MED_REC_ID']], $patient_other_info);

        $patient_medical_info = [
            'ABA01C' => $data['ABA01C'] ?: '',      //门(急)诊诊断编码
            'ABA01N' => $data['ABA01N'] ?: '',      //⻔（急）诊诊断名称
            'ABC03C' => $data['ABC03C'] ?: '',      //入院病情代码
            'ABF01C' => $data['ABF01C'] ?: '',      //病理诊断编码
            'ABF01N' => $data['ABF01N'] ?: '',      //病理诊断名称
            'ABF04' => $data['ABF04'] ?: '',        //病理号
            'ABF02C' => $data['ABF02C'] ?: '',      //最高诊断依据代码ID
            'ABF03C' => $data['ABF03C'] ?: '',      //分化程度编码ID
            'ABH01C' => $data['ABH01C'] ?: '',      //肿瘤分期是否不详
            'ABH0201C' => $data['ABH0201C'] ?: '',  //肿瘤分期 TID
            'ABH0202C' => $data['ABH0202C'] ?: '',  //肿瘤分期 NID
            'ABH0203C' => $data['ABH0203C'] ?: '',  //肿瘤分期 MID
            'ABH03C' => $data['ABH03C'] ?: '',      //0～Ⅳ肿瘤分期ID
            'AEB02C' => $data['AEB02C'] ?: null,      //有无药物过敏
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
        PatientMedicalInfo::query()->updateOrInsert(['AAA28' => $data['MED_REC_ID']], $patient_medical_info);

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
            'AEM02' => $data['AEM02'] ?: '',   //医嘱转院、转社区、卫生院机编码ID
            'AEM03C' => $data['AEM03C'] ?: '',  //是否有出院31日内再住院计划
            'AEM04' => $data['AEM04'] ?: '',    //31日内再住院目的
            'AEI01C' => $data['AEI01C'] ?: '',  //是否尸检代码ID
        ];
        PatientHospitalInfo::query()->updateOrInsert(['AAA28' => $data['MED_REC_ID']], $patient_hospital_info);

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
        PatientDoctorInfo::query()->updateOrInsert(['AAA28' => $data['MED_REC_ID']], $patient_doctor_info);

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
        PatientAddressInfo::query()->updateOrInsert(['AAA28' => $data['MED_REC_ID']], $patient_address_info);

        //患者工作信息
        $patient_work_info = [
            'AAA18C' => $data['AAA18C'] ?: '',      //职业代码ID
            'AAA19' => $data['AAA19'] ?: '',        //工作单位及地址
            'AAA20' => $data['AAA20'] ? desensitize($data['AAA20'], 3, 4, '*') : '',        //工作单位电话
            'AAA21C' => $data['AAA21C'] ?: '',      //工作单位邮政编码
        ];
        PatientWorkInfo::query()->updateOrInsert(['AAA28' => $data['MED_REC_ID']], $patient_work_info);

        //患者联系人信息
        $patient_contacts_info = [
            'AAA22' => $data['AAA22'] ? desensitize($data['AAA22'], 1, 1, '*') : '',    //联系人姓名
            'AAA23C' => $data['AAA23C'] ?: '',  //联系人关系代码ID
            'AAA24' => $data['AAA24'] ?: '',    //联系人地址
            'AAA25' => $data['AAA25'] ? desensitize($data['AAA25'], 3, 4, '*') : '',    //联系人电话
        ];
        PatientContactsInfo::query()->updateOrInsert(['AAA28' => $data['MED_REC_ID']], $patient_contacts_info);
    }

    /**
     * 获取手术申请
     * @param $ZYH
     * @return array
     */
    public function getSssqData($ZYH)
    {
        // 查询 手术申请
        $sql = "SELECT A.*,to_char(SQRQ,'yyyy-mm-dd hh24:mi:ss') as SQRQ,to_char(SSRQ,'yyyy-mm-dd hh24:mi:ss') as SSRQ,to_char(DSG_LDR_TIME,'yyyy-mm-dd hh24:mi:ss') as DSG_LDR_TIME FROM PORTAL_HIS.SM_SSSQ A WHERE ZYH=" . $ZYH . "AND A.DSG_OPERATION <> 'D'";
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }

        return $result;
    }

    public function addSmSssq($data)
    {
        $insertData = [];
        foreach ($data as $val) {
            $insertData[] = [
                'SQDH' => $val['SQDH'] ?? '',
                'ZYH' => $val['ZYH'] ?? '',
                'SSKS' => $val['SSKS'] ?? '',
                'SQKS' => $val['SQKS'] ?? '',
                'SQYS' => $val['SQYS'] ?? '',
                'SQRQ' => $val['SQRQ'] ?? '',
                'SSRQ' => $val['SSRQ'] ?? '',
                'SSNM' => $val['SSNM'] ?? '',
                'SSYS' => $val['SSYS'] ?? '',
                'SSYZ' => $val['SSYZ'] ?? '',
                'SSEZ' => $val['SSEZ'] ?? '',
                'SSSZ' => $val['SSSZ'] ?? '',
                'MZDM' => $val['MZDM'] ?? '',
                'MZYS' => $val['MZYS'] ?? '',
                'TJBZ' => $val['TJBZ'] ?? '',
                'APBZ' => $val['APBZ'] ?? '',
                'ZFBZ' => $val['ZFBZ'] ?? '',
                'TXKS' => $val['TXKS'] ?? '',
                'CZGH' => $val['CZGH'] ?? '',
                'SQTL' => $val['SQTL'] ?? '',
                'SQZD' => $val['SQZD'] ?? '',
                'NSSMC' => $val['NSSMC'] ?? '',
                'FYBQ' => $val['FYBQ'] ?? '',
                'ZFGH' => $val['ZFGH'] ?? '',
                'ZLXZ' => $val['ZLXZ'] ?? '',
                'QKDJ' => $val['QKDJ'] ?? '',
                'CFSS' => $val['CFSS'] ?? '',
                'SSYQ' => $val['SSYQ'] ?? '',
                'LRBZ' => $val['LRBZ'] ?? '',
                'THYY' => $val['THYY'] ?? '',
                'ZFYY' => $val['ZFYY'] ?? '',
                'YXJS' => $val['YXJS'] ?? '',
                'NLTR' => $val['NLTR'] ?? '',
                'HBQTJB' => $val['HBQTJB'] ?? '',
                'QTTSQK' => $val['QTTSQK'] ?? '',
                'TSQKNR' => $val['TSQKNR'] ?? '',
                'SSJB' => $val['SSJB'] ?? '',
                'CRBZ' => $val['CRBZ'] ?? '',
                'CRBG' => $val['CRBG'] ?? '',
                'BXBZ' => $val['BXBZ'] ?? '',
                'BXSM' => $val['BXSM'] ?? '',
                'BZXX' => $val['BZXX'] ?? '',
                'SSTW' => $val['SSTW'] ?? '',
                'SPBZ' => $val['SPBZ'] ?? '',
                'JGID' => $val['JGID'] ?? '',
                'RJSS' => $val['RJSS'] ?? null,
                'JJBZ' => $val['JJBZ'] ?? '',
                'BRLY' => $val['BRLY'] ?? '',
                'BRID' => $val['BRID'] ?? null,
                'SSLX' => $val['SSLX'] ?? null,
                'JJYZ' => $val['JJYZ'] ?? null,
                'TZBH' => $val['TZBH'] ?? null,
                'DSG_OPERATION' => $val['DSG_OPERATION'] ?? '',
                'DSG_LDR_TIME' => $val['DSG_LDR_TIME'] ?? ''
            ];
        }
        SSSQ::query()->where('ZYH', '=', $data[0]['ZYH'])->delete();
        if (!empty($insertData)) {
            SSSQ::query()->insert($insertData);
        }

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
        //$sql = "SELECT ZYH as AAA28,FYXH,FYMC,ZFJE,to_char(JFRQ,'yyyy-mm-dd hh24:mi:ss') as JFRQ,FYSL,FYDJ,FYKS FROM PORTAL_HIS.V_ZY_FYMX WHERE ZYH=" . $ZYH;
        $sql = "SELECT A.*,to_char(JFRQ,'yyyy-mm-dd hh24:mi:ss') as JFRQ,to_char(DSG_LDR_TIME,'yyyy-mm-dd hh24:mi:ss') as DSG_LDR_TIME FROM PORTAL_HIS.V_JMGS_BASY_FYMX A WHERE ZYH=" . $ZYH . "AND A.DSG_OPERATION <> 'D'";
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }

        if (empty($result)) {
            return false;
        }
        $insertData = [];
        foreach ($result as $val) {
            $insertData[] = [
                'AAA28' => $val['ZYH'],//zyh
                'FYXH' => $val['FYXH'] ?? '',//费用序号
                'FYMC' => $val['FYMC'] ?? '',//费用名称
                'ZFJE' => $val['ZFJE'] ?? '',//自付金额
                'JFRQ' => $val['JFRQ'] ?? '',//计费日期
                'FYSL' => $val['FYSL'] ?? '',//费用数量
                'FYDJ' => $val['FYDJ'] ?? '',//费用单价
                'ZJE' => $val['ZJE'] ?? '',//总金额
                'FYKS' => $val['FYKS'] ?? '',//费用科室
                'FYGB' => $val['FYGB'] ?? '',
                'SYFYGB' => $val['SYFYGB'] ?? '',
                'DSG_LDR_TIME' => $val['DSG_LDR_TIME'] ?? '',
                'DSG_OPERATION' => $val['DSG_OPERATION'] ?? '',
                'JLXH' => $val['JLXH'] ?? ''
            ];
        }
        FeeDetailed::query()->where('AAA28', '=', $ZYH)->delete();

        if (!empty($insertData)) {
            $chunkList = array_chunk($insertData, 1000);
            foreach ($chunkList as $value) {
                FeeDetailed::query()->insert($value);
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
        $sql = "SELECT A.MED_REC_ID,A.AREA_ID,A.BATCH_ID,A.IS_MAIN_WAY,to_char(IN_TIME,'yyyy-mm-dd hh24:mi:ss') as IN_TIME,to_char(OUT_TIME,'yyyy-mm-dd hh24:mi:ss') as OUT_TIME FROM PORTAL_HIS.INIT_MED_REC_TUTORSSIP A WHERE A.MED_REC_ID=" . $ZYH;
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
                'IS_MAIN_WAY' => $val['IS_MAIN_WAY'] ?? '',
                'IN_TIME' => $val['IN_TIME'] ?? '',
                'OUT_TIME' => $val['OUT_TIME'] ?? '',
                'AREA_ID' => $val['AREA_ID'] ?? 0,
                'BATCH_ID' => $val['BATCH_ID'] ?? '',
            ];
        }

        Icu::query()->where('AAA28', '=', $data[0]['MED_REC_ID'])->delete();
        if (!empty($insertData)) {
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
        $sql = "SELECT A.*,to_char(CYRQ,'yyyy-mm-dd hh24:mi:ss') as CYRQ FROM PORTAL_HIS.V_JMGS_BASY_ZD A WHERE ZYH=" . $ZYH;
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
        $main = [];
        $diagnosis = [];
        foreach ($data as $val) {
            $addData = [
                'AAA28' => $val['ZYH'],
                'ICD10_ID1' => $val['ZDBM'] ?? '',
                'ICD10_NAME' => $val['ZDMC'] ?? '',
                'DIA_ORDER' => $val['ZDXH'] ?? '',
                'LBMC' => $val['LBMC'] ?? '',
                'RYQK' => $val['RYQK'] ?? ''
            ];

            if ($val['ZZPB'] == 1) {
                $main[] = $addData;
            } else {
                $diagnosis[] = $addData;
            }
        }

        // 主要诊断
        MainDiagnosis::query()->where('AAA28', '=', $data[0]['ZYH'])->delete();
        if (!empty($main)) {
            MainDiagnosis::query()->insert($main);
        }
        // 其他诊断
        OtherDiagnosis::query()->where('AAA28', '=', $data[0]['ZYH'])->delete();
        if (!empty($diagnosis)) {
            OtherDiagnosis::query()->insert($diagnosis);
        }

        return array_column($main, 'RYQK', 'AAA28');
    }

    /**
     * 获取手术信息
     * @param $ZYH
     * @return array
     */
    public function getOperationData($ZYH)
    {
        $sql = "SELECT A.*,to_char(CYRQ,'yyyy-mm-dd hh24:mi:ss') as CYRQ,to_char(SSCZRQ,'yyyy-mm-dd hh24:mi:ss') as SSCZRQ,to_char(SSKSSJ,'yyyy-mm-dd hh24:mi:ss') as SSKSSJ,to_char(SSJSSJ,'yyyy-mm-dd hh24:mi:ss') as SSJSSJ FROM PORTAL_HIS.V_JMGS_BASY_SS A WHERE A.ZYH=" . $ZYH;
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
        $main = [];
        $other = [];
        foreach ($data as $val) {
            $addData = [
                'AAA28' => $val['ZYH'],
                'ICD9_ID1' => $val['SSCZBM'] ?? '',//手术或操作ID
                'ICD9_NAME' => $val['SSCZMC'] ?? '',//手术或操作名称
                'OPE_DATE' => $val['SSCZRQ'] ?? '',//手术或操作日期
                'OPE_ORDER' => $val['SSSX'] ?? '',//手术序号
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
                'CYRQ' => $val['CYRQ'] ?? '',
                'SFZYSS' => $val['SFZYSS'] ?? '',
                'QKDJ' => $val['QKDJ'] ?? '',
                'YHDJ' => $val['YHDJ'] ?? '',
                'BAHM' => $val['BAHM'] ?? '',
                'ZYHM' => $val['ZYHM'] ?? '',
                'SSPB' => intval(array_search($val['SSPB'], $config['SSPB']) ?? 5)//手术判别
            ];

            if ($val['SFZYSS'] == 1) {
                $main[] = $addData;
            } else {
                $other[] = $addData;
            }
        }

        // 主要手术
        MainOperation::query()->where('AAA28', '=', $data[0]['ZYH'])->delete();
        if (!empty($main)) {
            MainOperation::query()->insert($main);
        }
        // 其他手术
        SecondaryOperation::query()->where('AAA28', '=', $data[0]['ZYH'])->delete();
        if (!empty($other)) {
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
//        $sql = "SELECT A.*,to_char(ZKRQ,'yyyy-mm-dd hh24:mi:ss') as ZKRQ1 FROM PORTAL_HIS.V_JMGS_BASY_FY A WHERE A.ZYH=".$ZYH;
        $sql = "SELECT A.YBYLFWF,A.YBZLCZF,A.HLF,A.ZHYLFWLQTFY,A.BLZDF,A.SYSZDF,A.YXXZDF,A.LCZDXMF,A.FSSZLXMF,A.LCWLZLF,A.SSZLF,A.MZF,A.SSF,A.KFF,A.ZYZLF,A.XYF,A.KJYWF,A.ZCHENGYF,A.ZCAOYF,A.XF,A.BDBLZPF,A.QDBLZPF,A.NXYZLZPF,A.XBYZLZPF,A.JCYYCXYYCLF,A.ZLYYCXYYCLF,A.SSYYCXYYCLF,A.QTF,A.TYSHXYDM,A.JKKH,A.ZJLB,A.SJHL,A.EJHL,A.YJHL,A.TJHL,A.ZRHS,A.ZRHSBM,A.ZKHS,A.ZKHSBM,A.ZHFZRYS,A.ZZYSBM,A.ZYYSBM,A.ZZZYSBM,A.KZRXM,A.ZHFZRYSXM,A.ZZYSXM,A.ZYYSXM,A.ZZYISXM,A.BMY,A.RYKB,A.BFRY,A.ZKKB,A.CYKB,A.HB,A.HCV,A.HIV,A.LCLJ,A.WCQK,A.BYQK,to_char(A.ZKRQ,'yyyy-mm-dd hh24:mi:ss') as ZKRQ1 FROM PORTAL_HIS.V_JMGS_BASY_FY A WHERE A.ZYH=" . $ZYH;
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }

        return $result;
    }

    public function addBuChong($ZYH, $data)
    {
        // 费用信息
        $feeData = [
            'ADA0101' => $data['ZFFY'] ?? 0,
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
        PatientCostInfo::query()->updateOrInsert(['AAA28' => $ZYH], $feeData);

        // 补充信息
        $addData = [
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
        PatientAdd::query()->updateOrInsert(['AAA28' => $ZYH], $addData);

        return true;
    }

    /**
     * 医嘱
     * @param $ZYH
     * @return array
     */
    public function getYzb($ZYH)
    {
        $sql = "SELECT A.*,to_char(TZSJ,'yyyy-mm-dd hh24:mi:ss') as TJ,to_char(XZJDSJ,'yyyy-mm-dd hh24:mi:ss') as XJ,to_char(TZQRSJ,'yyyy-mm-dd hh24:mi:ss') as TZJ,to_char(APSJ,'yyyy-mm-dd hh24:mi:ss') as AJ,to_char(KZSJ,'yyyy-mm-dd hh24:mi:ss') as KJ,to_char(ZXSJ,'yyyy-mm-dd hh24:mi:ss') as ZJ FROM PORTAL_HIS.EMR_YZB A WHERE ZYH=" . $ZYH;
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
            if ($val['DSG_OPERATION'] == 'D') {
                // 删除预警信息
                QualitySendMsgLog::setStatus($val['ZYH'], 109, $val['YZBXH']);
                QualitySendMsgLog::setStatus($val['ZYH'], 110, $val['YZBXH']);
            } else {
                $insertData[] = [
                    'ZYH' => $val['ZYH'],
                    'YZBXH' => $val['YZBXH'] ?? '',
                    'RID' => $val['BRID'] ?? '',
                    'YEPB' => $val['YEPB'] ?? '',
                    'BRKS' => $val['BRKS'] ?? '',
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
                    'TZQRSJ' => $val['TZJ'] ?? null,
                    'APSJ' => $val['AJ'] ?? null,
                    'YYTS' => $val['YYTS'] ?? null,
                    'YSZT' => $val['YSZT'] ?? '',
                    'SRCS' => $val['SRCS'] ?? null,
                    'SRSD' => $val['SRSD'] ?? '',
                    'ZXSD' => $val['ZXSD'] ?? '',
                    'DS' => $val['DS'] ?? null,
                    'DSDW' => $val['DSDW'] ?? '',
                    'PSBZ' => $val['PSBZ'] ?? '',
                    'PSJG' => $val['PSJG'] ?? null,
                    'ZFPB' => $val['ZFPB'] ?? '',
                    'YBLX' => $val['YBLX'] ?? '',
                    'SPBH' => $val['SPBH'] ?? null,
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
                    'NWARN' => $val['NWARN'] ?? '',
                    'DSG_LDR_TIME' => $val['DSG_LDR_TIME'] ?? '',
                    'DSG_OPERATION' => $val['DSG_OPERATION'] ?? ''
                ];
            }
        }
        $idArr = Yzb::query()->where('ZYH', '=', $data[0]['ZYH'])->pluck('id')->toArray();
        Yzb::query()->whereIn('id', $idArr)->delete();

        //Yzb::query()->where('ZYH', '=', $data[0]['ZYH'])->delete();
        if (!empty($insertData)) {
            //Yzb::query()->insert($insertData);
            $chunkList = array_chunk($insertData, 500);
            foreach ($chunkList as $value) {
                Yzb::query()->insert($value);
            }
        }
    }

    /**
     * @param $zyh
     * 医生签名
     */
    public function addBLSY($zyh)
    {
        $sql = "SELECT a.*,to_char(ZXSJ,'yyyy-mm-dd hh24:mi:ss') as ZJ,to_char(CJSJ,'yyyy-mm-dd hh24:mi:ss') as CJ,to_char(WCSJ,'yyyy-mm-dd hh24:mi:ss') as WJ,to_char(XGSJ,'yyyy-mm-dd hh24:mi:ss') as XJ FROM PORTAL55_EMR.V_JMGS_BASY_QBL a WHERE a.ZYH={$zyh} AND a.DSG_OPERATION <> 'D'";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }
        $blbh = [];
        foreach ($data as $item) {
            $result = [
                'BLBH' => $item['BLBH'] ?? '',
                'JZHM' => $item['ZYH'] ?? '',
                'BRBH' => $item['BRBH'] ?? '',
                'BLLX' => $item['BLLX'] ?? '',
                'BLLB' => $item['BLLB'] ?? '',
                'BLMC' => $item['BLMC'] ?? '',
                'BLZM' => $item['BLZM'] ?? '',
                'DLLB' => $item['DLLB'] ?? '',
                'DLJ' => $item['DLJ'] ?? '',
                'MBLB' => $item['MBLB'] ?? '',
                'MBBH' => $item['MBBH'] ?? '',
                'ZXSJ' => $item['ZJ'] ?? '',
                'CJSJ' => $item['CJ'] ?? '',
                'WCSJ' => $item['WJ'] ?? '',
                'SXYS' => $item['SXYS'] ?? '',
                'BRKS' => $item['BRKS'] ?? '',
                'CJKS' => $item['CJKS'] ?? '',
                'BLZT' => $item['BLZT'] ?? '',
                'BRXM' => $item['BRXM'] ?? '',
                'BRZD' => $item['BRZD'] ?? '',
                'SSYS' => $item['SSYS'] ?? '',
                'SYBZ' => $item['SYBZ'] ?? '',
                'BZMBBH' => $item['BZMBBH'] ?? '',
                'BLYM' => $item['BLYM'] ?? '',
                'YMJL' => $item['YMJL'] ?? '',
                'RYZDSJ' => $item['RYZDSJ'] ?? '',
                'PTID' => $item['PTID'] ?? '',
                'BLZSTJ' => $item['BLZSTJ'] ?? '',
                'JGID' => $item['JGID'] ?? '',
                'SQDH' => $item['SQDH'] ?? '',
                'ZDMC' => $item['ZDMC'] ?? '',
                'ZDLX' => $item['ZDLX'] ?? '',
                'CXPX' => $item['CXPX'] ?? '',
                'SBBZ' => $item['SBBZ'] ?? '',
                'WZZT' => $item['WZZT'] ?? '',
                'bl_type' => 0
            ];
            $str = blobToStr($item['BLNR']);
            if (empty($str)) {
                Log::info("HJNR_is_empty:BLBH=" . $item['BLBH']);
                continue;
            }

            if (
                in_array($item['MBLB'], [295, 129]) ||
                ($item['BLLB'] == 294 && (strpos($str, '病例特点') !== false || strpos($str, '鉴别诊断') !== false))
            ) {
                $result['bl_type'] = 1;
            }


            $temp = [
                'JLXH' => $item['JLXH'] ?? '',
                'BLBH' => $item['BLBH'] ?? '',
                'XGGH' => $item['XGGH'] ?? '',
                'XGSJ' => $item['XJ'] ?? '',
                'HJNR' => $str,
            ];
            EMR_BL_BL01::query()->updateOrInsert(['BLBH' => $item['BLBH']], $result);
            EMR_BL_BLXG::query()->updateOrInsert(['BLBH' => $item['BLBH']], $temp);
            $blbh[] = $item['BLBH'];
        }
        $blbhStr = implode(',', $blbh);
        $sql = "select JLXH,BLBH,SYYS,to_char(SYSJ,'yyyy-mm-dd hh24:mi:ss') as SYSJ,to_char(JLSJ,'yyyy-mm-dd hh24:mi:ss') as JLSJ from PORTAL55_EMR.EMR_BL_BLSY WHERE BLBH in ($blbhStr) AND DSG_OPERATION <> 'D'";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }

        foreach ($data as $item) {
            $insertData = [
                'JLXH' => $item['JLXH'] ?? '',
                'BLBH' => $item['BLBH'] ?? '',
                'SYYS' => $item['SYYS'] ?? '',
                'SYSJ' => $item['SYSJ'] ?? '',
                'JLSJ' => $item['JLSJ'] ?? '',
            ];

            \App\Model\EMR_BL_BLSY::query()->updateOrInsert(['JLXH' => $insertData['JLXH']], $insertData);
        }

    }

    /**
     * @param $zyh
     * 护士分床时间
     */
    public function ZY_HCMX($zyh)
    {
        $sql = "SELECT ZYH,to_char(HCRQ,'yyyy-mm-dd hh24:mi:ss') as HCRQ,to_char(ZZRQ,'yyyy-mm-dd hh24:mi:ss') as ZZRQ,HCLX,HQCH,HHCH,HQKS,HHKS,HQBQ,HHBQ,JSCS,CZGH,JGID FROM PORTAL_HIS.ZY_HCMX WHERE ZYH={$zyh} AND DSG_OPERATION <> 'D'";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }

        foreach ($data as $item) {
            $insertData = [
                'ZYH' => $item['ZYH'] ?? '',
                'HCRQ' => $item['HCRQ'] ?? '',
                'ZZRQ' => $item['ZZRQ'] ?? '',
                'HCLX' => $item['HCLX'] ?? '',
                'HQCH' => $item['HQCH'] ?? '',
                'HHCH' => $item['HHCH'] ?? '',
                'HQKS' => $item['HQKS'] ?? '',
                'HHKS' => $item['HHKS'] ?? '',
                'HQBQ' => $item['HQBQ'] ?? '',
                'HHBQ' => $item['HHBQ'] ?? '',
                'JSCS' => $item['JSCS'] ?? '',
                'CZGH' => $item['CZGH'] ?? '',
                'JGID' => $item['JGID'] ?? ''
            ];

            \App\Model\ZY_HCMX::query()->updateOrInsert(['ZYH' => $insertData['ZYH'], 'HCRQ' => $insertData['HCRQ']], $insertData);
        }

    }

    /**
     * @param $zyh
     *住院病历（会诊意见）\住院病历（会诊申请）
     */
    public function YS_ZY_HZYJ($zyh)
    {

        $sql = "SELECT SQXH, JZHM, SQKS, SQYS, to_char(SQSJ,'yyyy-mm-dd hh24:mi:ss') as SQSJ, HZMD, HZMD2, HZSJ, YQDX, JJBZ, TJBZ, TJYS, TJSJ, ZFBZ, JSBZ, JSSJ, TXRY, BQZL, HZLX, BLBH, SQZD, JGID, JSYS, JZBZ, HZLB FROM PORTAL_HIS.YS_ZY_HZSQ  WHERE JZHM={$zyh} AND DSG_OPERATION <> 'D'";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }

        $SQXH = [];
        foreach ($data as $item) {

            $str = blobToStr($item['BQZL']);
            $inData = [
                'SQXH' => $item['SQXH'] ?? '',
                'JZHM' => $item['JZHM'] ?? '',
                'SQKS' => $item['SQKS'] ?? '',
                'SQYS' => $item['SQYS'] ?? '',
                'SQSJ' => $item['SQSJ'] ?? '',
                'HZMD' => $item['HZMD'] ?? '',
                'HZMD2' => $item['HZMD2'] ?? '',
                'HZSJ' => $item['HZSJ'] ?? '',
                'YQDX' => $item['YQDX'] ?? '',
                'JJBZ' => $item['JJBZ'] ?? '',
                'TJBZ' => $item['TJBZ'] ?? '',
                'TJYS' => $item['TJYS'] ?? '',
                'TJSJ' => $item['TJSJ'] ?? '',
                'ZFBZ' => $item['ZFBZ'] ?? '',
                'JSBZ' => $item['JSBZ'] ?? '',
                'JSSJ' => $item['JSSJ'] ?? '',
                'TXRY' => $item['TXRY'] ?? '',
                'BQZL' => $str ?? '',
                'HZLX' => $item['HZLX'] ?? '',
                'BLBH' => $item['BLBH'] ?? '',
                'SQZD' => $item['SQZD'] ?? '',
                'JGID' => $item['JGID'] ?? '',
                'JSYS' => $item['JSYS'] ?? '',
                'JZBZ' => $item['JZBZ'] ?? '',
                'HZLB' => $item['HZLB'] ?? '',
            ];
            $SQXH[] = $inData['SQXH'];
            \App\Model\YS_ZY_HZSQ::query()->updateOrInsert(['SQXH' => $item['SQXH'],], $inData);
        }

        $SQXHStr = implode(',', $SQXH);
        $sql = "SELECT JLXH,SQXH,HZYJ,HZYJ2,KSDM,SSYS,SXYS,to_char(SXSJ,'yyyy-mm-dd hh24:mi:ss') as SXSJ,to_char(QMSJ,'yyyy-mm-dd hh24:mi:ss') as QMSJ FROM PORTAL_HIS.YS_ZY_HZYJ WHERE SQXH in ($SQXHStr) AND DSG_OPERATION <> 'D'";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        foreach ($data as $item) {
            $str = blobToStr($item['HZYJ']);
            $insertData = [
                'JLXH' => $item['JLXH'] ?? '',
                'SQXH' => $item['SQXH'] ?? '',
                'HZYJ' => $str ?? '',
                'HZYJ2' => $item['HZYJ2'] ?? '',
                'KSDM' => $item['KSDM'] ?? '',
                'SSYS' => $item['SSYS'] ?? '',
                'SXYS' => $item['SXYS'] ?? '',
                'SXSJ' => $item['SXSJ'] ?? '',
                'QMSJ' => $item['QMSJ'] ?? '',
            ];

            \App\Model\YS_ZY_HZYJ::query()->updateOrInsert(['JLXH' => $insertData['JLXH']], $insertData);
        }
    }

    public function mzjl($zyh)
    {

        $sql = "SELECT DCID,PATIENTID,PATIENTTYPE,VISITID,EFFECTIVEFLAG,AUTHORORGANIZATION,AUTHORORGANIZATIONNAME,IDCARD,CLINICID,HOSPIZATIONID,to_char(VISITDATETIME,'yyyy-mm-dd hh24:mi:ss') as VISITDATETIME,REQUESTNOTEID,NAME,SEX,AGE,MONTHAGE,HEIGHT,WEIGHT,ABOBLOODCODE,RHBLOODCODE,DEPTCODE,DEPTNAME,WARDAREANAME,WARDAREAROOM,SICKBEDID,to_char(REQUESTDATETIME,'yyyy-mm-dd hh24:mi:ss') as REQUESTDATETIME,OPERATIONDEPTCODE,OPERATIONCODE,PREOPERATIONNAME,OPERATIONNAME,OPTPATIENTTYPE,ISRETURNOPERATION,HAVEPREOPERATIVEDISCUSS,PREOPERATIVENOTES,PREOPERATIVEDIAGNOSECODE,PREOPERATIVEDIAGNOSENAME,POSTOPERATIVEDIAGNOSECODE,POSTOPERATIVEDIAGNOSENAME,DIAGCOINPREOPERATIVEVSPOST,OPERATIONROOMNO,OPERATIONROOMTABLENO,to_char(INOPTROOMTIME,'yyyy-mm-dd hh24:mi:ss') as INOPTROOMTIME,to_char(OUTOPTROOMTIME,'yyyy-mm-dd hh24:mi:ss') as OUTOPTROOMTIME,to_char(OPERATESTARTTIME,'yyyy-mm-dd hh24:mi:ss') as OPERATESTARTTIME,OPERATEENDTIME,OPERATOR,FIRSTASSISTANT,SECONDASSISTANT,THIRDASSISTANT,FIRSTINSTRUMENTNURSE,SECONDINSTRUMENTNURSE,THIRDINSTRUMENTNURSE,FIRSTCIRCULATINGNURSE,SECONDCIRCULATINGNURSE,THIRDCIRCULATINGNURSE,MEDICATEBEFOREANESTHESIA,ASALEVEL,ANESTHESIAWAYCODE,ANESTHESIAWAYNAME,TRACHEATUBETYPE,ANESTHESIABODYPOSITION,ANAESTHETIST,FIRSTANAESTHETISTASSI,SECONDANAESTHETISTASSI,ANESTHESIASTARTTIME,to_char(ANESTHESIAENDTIME,'yyyy-mm-dd hh24:mi:ss') as ANESTHESIAENDTIME,ANAESTHETICNAME,BREATHTYPECODE,ANESTHESIAEFFECT,ANESTHESIADESCRIPTION FROM SAMIS.V_CDR_5504 where HOSPIZATIONID='" . $zyh . "'";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }
        foreach ($data as $item) {
            $insertData = [
                'DCID' => $item['DCID'] ?? '',
                'PATIENTID' => $item['PATIENTID'] ?? '',
                'PATIENTTYPE' => $item['PATIENTTYPE'] ?? '',
                'VISITID' => $item['VISITID'] ?? '',
                'EFFECTIVEFLAG' => $item['EFFECTIVEFLAG'] ?? '',
                'AUTHORORGANIZATION' => $item['AUTHORORGANIZATION'] ?? '',
                'AUTHORORGANIZATIONNAME' => $item['AUTHORORGANIZATIONNAME'] ?? '',
                'IDCARD' => $item['IDCARD'] ?? '',
                'CLINICID' => $item['CLINICID'] ?? '',
                'HOSPIZATIONID' => $item['HOSPIZATIONID'] ?? '',
                'VISITDATETIME' => $item['VISITDATETIME'] ?? '',
                'REQUESTNOTEID' => $item['REQUESTNOTEID'] ?? '',
                'NAME' => $item['NAME'] ?? '',
                'SEX' => $item['SEX'] ?? '',
                'AGE' => $item['AGE'] ?? '',
                'MONTHAGE' => $item['MONTHAGE'] ?? '',
                'HEIGHT' => $item['HEIGHT'] ?? '',
                'WEIGHT' => $item['WEIGHT'] ?? '',
                'ABOBLOODCODE' => $item['ABOBLOODCODE'] ?? '',
                'RHBLOODCODE' => $item['RHBLOODCODE'] ?? '',
                'DEPTCODE' => $item['DEPTCODE'] ?? '',
                'DEPTNAME' => $item['DEPTNAME'] ?? '',
                'WARDAREANAME' => $item['WARDAREANAME'] ?? '',
                'WARDAREAROOM' => $item['WARDAREAROOM'] ?? '',
                'SICKBEDID' => $item['SICKBEDID'] ?? '',
                'REQUESTDATETIME' => $item['REQUESTDATETIME'] ?? '',
                'OPERATIONDEPTCODE' => $item['OPERATIONDEPTCODE'] ?? '',
                'OPERATIONCODE' => $item['OPERATIONCODE'] ?? '',
                'PREOPERATIONNAME' => $item['PREOPERATIONNAME'] ?? '',
                'OPERATIONNAME' => $item['OPERATIONNAME'] ?? '',
                'OPTPATIENTTYPE' => $item['OPTPATIENTTYPE'] ?? '',
                'ISRETURNOPERATION' => $item['ISRETURNOPERATION'] ?? '',
                'HAVEPREOPERATIVEDISCUSS' => $item['HAVEPREOPERATIVEDISCUSS'] ?? '',
                'PREOPERATIVENOTES' => $item['PREOPERATIVENOTES'] ?? '',
                'PREOPERATIVEDIAGNOSECODE' => $item['PREOPERATIVEDIAGNOSECODE'] ?? '',
                'PREOPERATIVEDIAGNOSENAME' => $item['PREOPERATIVEDIAGNOSENAME'] ?? '',
                'POSTOPERATIVEDIAGNOSECODE' => $item['POSTOPERATIVEDIAGNOSECODE'] ?? '',
                'POSTOPERATIVEDIAGNOSENAME' => $item['POSTOPERATIVEDIAGNOSENAME'] ?? '',
                'DIAGCOINPREOPERATIVEVSPOST' => $item['DIAGCOINPREOPERATIVEVSPOST'] ?? '',
                'OPERATIONROOMNO' => $item['OPERATIONROOMNO'] ?? '',
                'OPERATIONROOMTABLENO' => $item['OPERATIONROOMTABLENO'] ?? '',
                'INOPTROOMTIME' => $item['INOPTROOMTIME'] ?? '',
                'OUTOPTROOMTIME' => $item['OUTOPTROOMTIME'] ?? '',
                'OPERATESTARTTIME' => $item['OPERATESTARTTIME'] ?? '',
                'OPERATEENDTIME' => $item['OPERATEENDTIME'] ?? '',
                'OPERATOR' => $item['OPERATOR'] ?? '',
                'FIRSTASSISTANT' => $item['FIRSTASSISTANT'] ?? '',
                'SECONDASSISTANT' => $item['SECONDASSISTANT'] ?? '',
                'THIRDASSISTANT' => $item['THIRDASSISTANT'] ?? '',
                'FIRSTINSTRUMENTNURSE' => $item['FIRSTINSTRUMENTNURSE'] ?? '',
                'SECONDINSTRUMENTNURSE' => $item['SECONDINSTRUMENTNURSE'] ?? '',
                'THIRDINSTRUMENTNURSE' => $item['THIRDINSTRUMENTNURSE'] ?? '',
                'FIRSTCIRCULATINGNURSE' => $item['FIRSTCIRCULATINGNURSE'] ?? '',
                'SECONDCIRCULATINGNURSE' => $item['SECONDCIRCULATINGNURSE'] ?? '',
                'THIRDCIRCULATINGNURSE' => $item['THIRDCIRCULATINGNURSE'] ?? '',
                'MEDICATEBEFOREANESTHESIA' => $item['MEDICATEBEFOREANESTHESIA'] ?? '',
                'ASALEVEL' => $item['ASALEVEL'] ?? '',
                'ANESTHESIAWAYCODE' => $item['ANESTHESIAWAYCODE'] ?? '',
                'ANESTHESIAWAYNAME' => $item['ANESTHESIAWAYNAME'] ?? '',
                'TRACHEATUBETYPE' => $item['TRACHEATUBETYPE'] ?? '',
                'ANESTHESIABODYPOSITION' => $item['ANESTHESIABODYPOSITION'] ?? '',
                'ANAESTHETIST' => $item['ANAESTHETIST'] ?? '',
                'FIRSTANAESTHETISTASSI' => $item['FIRSTANAESTHETISTASSI'] ?? '',
                'SECONDANAESTHETISTASSI' => $item['SECONDANAESTHETISTASSI'] ?? '',
                'ANESTHESIASTARTTIME' => $item['ANESTHESIASTARTTIME'] ?? '',
                'ANESTHESIAENDTIME' => $item['ANESTHESIAENDTIME'] ?? '',
                'ANAESTHETICNAME' => $item['ANAESTHETICNAME'] ?? '',
                'BREATHTYPECODE' => $item['BREATHTYPECODE'] ?? '',
                'ANESTHESIAEFFECT' => $item['ANESTHESIAEFFECT'] ?? '',
                'ANESTHESIADESCRIPTION' => $item['ANESTHESIADESCRIPTION'] ?? '',
            ];

            \App\Model\MZJL::query()->updateOrInsert(['DCID' => $insertData['DCID']], $insertData);
        }
    }

    /**
     * @param $zyh
     * 入院途径
     */
    public function BaBrsy($zyh)
    {
        $sql = "SELECT ZYH AS AAA28,CYBQ,ZZYLJG FROM PORTAL_HIS.ba_brsy WHERE ZYH={$zyh} and DSG_OPERATION <> 'D'";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }
        foreach ($data as $item) {
            $insertData = [
                'AAA28' => $item['AAA28'] ?? '',
                'CYBQ' => $item['CYBQ'] ?? '',
                'ZZYLJG' => $item['ZZYLJG'] ?? '',
            ];
            \App\Model\BaBrsy::query()->updateOrInsert(['AAA28' => $insertData['AAA28']], $insertData);
        }
    }

    public function YJ_ZY01($zyh = null)
    {

        $sql = "SELECT YJXH,TJHM,ZYH,ZYHM,BRXM,to_char(KDRQ,'yyyy-mm-dd hh24:mi:ss') as KDRQ,KSDM,YSDM,to_char(ZXRQ,'yyyy-mm-dd hh24:mi:ss') as ZXRQ,ZXKS,ZXPB,HJGH,BBBM,ZYSX,ZFPB,HYMX,YJPH,SQDH,BWID,JBID,DJZT,SQWH,FYBQ,SQID,YQDH,JGID,SSYS,SSYZ,SSEZ,SSSZ,JZBZ FROM PORTAL_HIS.YJ_ZY01 WHERE ZYH={$zyh} and DSG_OPERATION <> 'D'";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }

        $YJXH = [];
        foreach ($data as $item) {
            $YJXH[] = $item['YJXH'];
            $insertData = [
                'YJXH' => $item['YJXH'] ?? '',
                'TJHM' => $item['TJHM'] ?? '',
                'ZYH' => $item['ZYH'] ?? '',
                'ZYHM' => $item['ZYHM'] ?? '',
                'BRXM' => desensitize($item['BRXM'], 1, 1, '*'),
                'KDRQ' => $item['KDRQ'] ?? '',
                'KSDM' => $item['KSDM'] ?? '',
                'YSDM' => $item['YSDM'] ?? '',
                'ZXRQ' => $item['ZXRQ'] ?? '',
                'ZXKS' => $item['ZXKS'] ?? '',
                'ZXPB' => $item['ZXPB'] ?? '',
                'BBBM' => $item['BBBM'] ?? '',
                'ZYSX' => $item['ZYSX'] ?? '',
                'ZFPB' => $item['ZFPB'] ?? '',
                'HYMX' => $item['HYMX'] ?? '',
                'YJPH' => $item['YJPH'] ?? '',
                'SQDH' => $item['SQDH'] ?? '',
                'BWID' => $item['BWID'] ?? '',
                'JBID' => $item['JBID'] ?? '',
                'DJZT' => $item['DJZT'] ?? '',
                'SQWH' => $item['SQWH'] ?? '',
                'FYBQ' => $item['FYBQ'] ?? '',
                'SQID' => $item['SQID'] ?? '',
                'YQDH' => $item['YQDH'] ?? '',
                'JGID' => $item['JGID'] ?? '',
                'SSYS' => $item['SSYS'] ?? '',
                'SSYZ' => $item['SSYZ'] ?? '',
                'SSEZ' => $item['SSEZ'] ?? '',
                'SSSZ' => $item['SSSZ'] ?? '',
                'JZBZ' => $item['JZBZ'] ?? '',
            ];

            \App\Model\YJ_ZY01::query()->updateOrInsert(['YJXH' => $insertData['YJXH']], $insertData);
        }

        $YJXHstr = implode(',', $YJXH);
        $sql = "SELECT SBXH, YJXH, YLXH, XMLX, YJZX, YLDJ, YLSL, FYGB, ZFBL, YZXH, TPLJ, YEPB, TMDY_LQ, TMH, JGID, ZTMC, JHH, XDH, JHSJ, JFID FROM PORTAL_HIS.YJ_ZY02 WHERE YJXH in ($YJXHstr) and DSG_OPERATION <> 'D'";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }

        foreach ($data as $item) {
            if (empty($item)) {
                continue;
            }
            $insertData = [
                'SBXH' => $item['SBXH'] ?? '',
                'YJXH' => $item['YJXH'] ?? '',
                'YLXH' => $item['YLXH'] ?? '',
                'XMLX' => $item['XMLX'] ?? '',
                'YJZX' => $item['YJZX'] ?? '',
                'YLDJ' => $item['YLDJ'] ?? '',
                'YLSL' => $item['YLSL'] ?? '',
                'FYGB' => $item['FYGB'] ?? '',
                'ZFBL' => $item['ZFBL'] ?? '',
                'YZXH' => $item['YZXH'] ?? '',
                'TPLJ' => $item['TPLJ'] ?? '',
                'YEPB' => $item['YEPB'] ?? '',
                'TMDY_LQ' => $item['TMDY_LQ'] ?? '',
                'TMH' => $item['TMH'] ?? '',
                'JGID' => $item['JGID'] ?? '',
                'ZTMC' => $item['ZTMC'] ?? '',
                'JHH' => $item['JHH'] ?? '',
                'XDH' => $item['XDH'] ?? '',
                'JHSJ' => $item['JHSJ'] ?? '',
            ];
            \App\Model\YJ_ZY02::query()->updateOrInsert(['SBXH' => $insertData['SBXH']], $insertData);
        }
    }


    public function EMR_BL_BASYSJ($zyh)
    {
        $sql = "SELECT JLXH,JZHM,BLBH,XMXH,XMMC,XMQZ,DYYS,DLLJ,GLZD,KSMRZ,SYBTX,XMNM FROM PORTAL55_EMR.EMR_BL_BASYSJ WHERE JZHM='{$zyh}'";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }
        foreach ($data as $item) {
            $insertData = [
                'JLXH' => $item['JLXH'] ?? '',
                'JZHM' => $item['JZHM'] ?? '',
                'BLBH' => $item['BLBH'] ?? '',
                'XMXH' => $item['XMXH'] ?? '',
                'XMMC' => $item['XMMC'] ?? '',
                'XMQZ' => $item['XMQZ'] ?? '',
                'DYYS' => $item['DYYS'] ?? '',
                'DLLJ' => $item['DLLJ'] ?? '',
                'GLZD' => $item['GLZD'] ?? '',
                'KSMRZ' => $item['KSMRZ'] ?? '',
                'SYBTX' => $item['SYBTX'] ?? '',
                'XMNM' => $item['XMNM'] ?? '',
            ];

            \App\Model\EMR_BL_BASYSJ::query()->updateOrInsert(['JLXH' => $insertData['JLXH']], $insertData);
        }

    }

    public function V_JMGS_TESTRESULT($zyh)
    {
        $sql = "SELECT ZYH,TXM,NO,XM,XB,NL,CH,YBLX,YBZT,AAA28,BQ,LCZD,YW,JYXM,JG,TS,CKFW,DW,SJYS,JYY,SHY,CJSJ,JSSJ,BGSJ FROM BSLIS52_Y12.V_JMGS_TESTRESULT WHERE ZYH={$zyh}";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }
        foreach ($data as $item) {
            $insertData = [
                'ZYH' => $item['ZYH'] ?? '',
                'TXM' => $item['TXM'] ?? '',
                'NO' => $item['NO'] ?? '',
                'XM' => $item['XM'] ?? '',
                'XB' => $item['XB'] ?? '',
                'NL' => $item['NL'] ?? '',
                'CH' => $item['CH'] ?? '',
                'YBLX' => $item['YBLX'] ?? '',
                'YBZT' => $item['YBZT'] ?? '',
                'AAA28' => $item['AAA28'] ?? '',
                'BQ' => $item['BQ'] ?? '',
                'LCZD' => $item['LCZD'] ?? '',
                'YW' => $item['YW'] ?? '',
                'JYXM' => $item['JYXM'] ?? '',
                'JG' => $item['JG'] ?? '',
                'TS' => $item['TS'] ?? '',
                'CKFW' => $item['CKFW'] ?? '',
                'DW' => $item['DW'] ?? '',
                'SJYS' => $item['SJYS'] ?? '',
                'JYY' => $item['JYY'] ?? '',
                'SHY' => $item['SHY'] ?? '',
                'CJSJ' => $item['CJSJ'] ?? '',
                'JSSJ' => $item['JSSJ'] ?? '',
                'BGSJ' => $item['BGSJ'] ?? '',
            ];

            \App\Model\V_JMGS_TESTRESULT::query()->updateOrInsert(['ZYH' => $insertData['ZYH']], $insertData);
        }

    }

    public function V_JMGS_YMresult($zyh)
    {
        $sql = "SELECT ZYH,TXM,NO,XM,XB,NL,CH,YBLX,YBZT,AAA28,BQ,LCZD,PYJG,XJMC,XJJL,YMMC,YMJG,YMBW,SJYS,JYY,SHY,to_char(CJSJ,'yyyy-mm-dd hh24:mi:ss') as CJSJ,to_char(JSSJ,'yyyy-mm-dd hh24:mi:ss') as JSSJ,to_char(BGSJ,'yyyy-mm-dd hh24:mi:ss') as BGSJ,EXAMINAIM,STAYHOSPITALMODE FROM BSLIS52_Y12.V_JMGS_YMresult WHERE ZYH={$zyh}";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }
        foreach ($data as $item) {
            $insertData = [
                'ZYH' => $item['ZYH'] ?? '',
                'TXM' => $item['TXM'] ?? '',
                'NO' => $item['NO'] ?? '',
                'XM' => $item['XM'] ?? '',
                'XB' => $item['XB'] ?? '',
                'NL' => $item['NL'] ?? '',
                'CH' => $item['CH'] ?? '',
                'YBLX' => $item['YBLX'] ?? '',
                'YBZT' => $item['YBZT'] ?? '',
                'AAA28' => $item['AAA28'] ?? '',
                'BQ' => $item['BQ'] ?? '',
                'LCZD' => $item['LCZD'] ?? '',
                'PYJG' => $item['PYJG'] ?? '',
                'XJMC' => $item['XJMC'] ?? '',
                'XJJL' => $item['XJJL'] ?? '',
                'YMMC' => $item['YMMC'] ?? '',
                'YMJG' => $item['YMJG'] ?? '',
                'YMBW' => $item['YMBW'] ?? '',
                'SJYS' => $item['SJYS'] ?? '',
                'JYY' => $item['JYY'] ?? '',
                'SHY' => $item['SHY'] ?? '',
                'CJSJ' => $item['CJSJ'] ?? '',
                'JSSJ' => $item['JSSJ'] ?? '',
                'BGSJ' => $item['BGSJ'] ?? '',
                'EXAMINAIM' => $item['EXAMINAIM'] ?? '',
                'STAYHOSPITALMODE' => $item['STAYHOSPITALMODE'] ?? '',
            ];

            \App\Model\V_JMGS_YMresult::query()->updateOrInsert(['ZYH' => $insertData['ZYH']], $insertData);
        }

    }
}
