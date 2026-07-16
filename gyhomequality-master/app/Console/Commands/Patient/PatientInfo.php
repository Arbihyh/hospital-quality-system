<?php

namespace App\Console\Commands\Patient;

use App\Model\PatientAddressInfo;
use App\Model\PatientContactsInfo;
use App\Model\PatientDoctorInfo;
use App\Model\PatientHospitalInfo;
use App\Model\PatientMedicalInfo;
use App\Model\PatientOtherInfo;
use App\Model\PatientWorkInfo;
use Illuminate\Console\Command;

class PatientInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'patientInfo {startTime?} {endTime?}';

    public static $con;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步患者数据
                                PatientInfo(主信息)，
                                PatientOtherInfo(其他信息)，
                                PatientMedicalInfo，
                                PatientHospitalInfo(患者住院信息)，
                                PatientDoctorInfo(患者医生信息),
                                PatientAddressInfo(患者地址相关信息),
                                PatientWorkInfo(患者工作信息),
                                PatientContactsInfo(患者联系人信息)';

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
        echo "同步患者信息开始：" . date('Y-m-d H:i:s') . "\n";
        $this->getConnect(); //建立数据库连接
        $startTime = $this->argument('startTime');
        $endTime = $this->argument('endTime');
        $s = time();

        $total = 0;

        //由于医院归档时间慢，咱们将时间按照出院时间近2个月的数据，进行同步
        $startTime = empty($startTime) ? date('Y-m-d 00:00:00', strtotime('-2 month', time())) : date('Y-m-d 00:00:00', strtotime($startTime));
        $endTime = empty($endTime) ? date('Y-m-d 23:59:59', strtotime('-1 day', time())) : date('Y-m-d 23:59:59', strtotime($endTime));
        while (true) {
            $startEndTime = date('Y-m-d 23:59:59', strtotime($startTime));
            if ($startTime > $endTime) {
                break;
            }
            $patientData = $this->getPatientInfo($startTime, $startEndTime, 1);
            if (!empty($patientData)) {
                echo $startTime . "共" . count($patientData) . "条数据" . "\n";
                $total += count($patientData);
                foreach ($patientData as $item) {
                    echo $item['MED_REC_ID'] . ' - ' . $startTime . ' - ' . date('Y-m-d H:i:s') . "\n";
                    $this->addPatientInfo($item, '', '');
                }
            }
            $startTime = date('Y-m-d 00:00:00', strtotime($startTime) + 86400);
        }


        $e = time();
        $timeDifference = $e - $s;
        $totalHours = floor($timeDifference / 3600); // 3600秒 = 1小时
        $totalMinutes = floor(($timeDifference % 3600) / 60); // 剩余分钟
        echo "一共同步" . $total . "条数据；用时" . $totalHours . "小时" . $totalMinutes . "分钟" . "\n";
        echo "同步患者信息开始结束：" . date('Y-m-d H:i:s') . "\n";
        exit();
    }

    /**
     * 建立数据库连接
     * @return void
     */
    public function getConnect()
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
    }

    /**
     * 获取患者数据
     * @param $startTime
     * @param $endTime
     * @param $day
     * @return array
     */
    public function getPatientInfo($startTime, $endTime, $day)
    {
        $column = $day == 1 ? 'AAC01' : 'DSG_LDR_TIME';
        if (!empty($ZYH)) {
            $sql = "SELECT A.*,to_char(AAA03,'yyyy-mm-dd hh24:mi:ss') as AAA03,
                    to_char(SYN_DATE,'yyyy-mm-dd hh24:mi:ss') as SYN_DATE,
                    to_char(AAB01,'yyyy-mm-dd hh24:mi:ss') as AAB01,
                    to_char(AAC01,'yyyy-mm-dd hh24:mi:ss') as AAC01,
                    to_char(AED04,'yyyy-mm-dd hh24:mi:ss') as AED04,
                    to_char(MT_JZRQ,'yyyy-mm-dd') as MT_JZRQ
                    FROM PORTAL_HIS.INIT_MED_REC_MAIN A WHERE A.MED_REC_ID=" . $ZYH . " order by A.AAC01";
        } else {
            $sql = "SELECT A.ZA03,A.AAA28,A.AAA01,A.AAA01,A.AAA02C,A.AAA03,A.AAA04,A.AAA05C,A.AAA40,A.AAA42,A.AEN01,A.AAA06C,
                    A.AAA07,A.AAA07,A.AAA08C,A.AEM01C,A.AAB01,A.AAC01,A.AAC11N,A.AAC04,A.ADA01,A.ADA0101,A.AAA29,A.ABG01C,
                    A.ABG01N,A.AAB06C,A.ABC01N,A.ORG_STATE,A.AAA26C,A.ATTEND_GRP_CODE,A.ATTEND_GRP_NAME,A.AAB07C,A.AAB07N,
                    A.AAB07,A.AAB07D,A.ABD04,A.ABD051,A.ABD052,A.ABD053,A.ABD054,A.ZB09,A.ZB08,A.ZB07,A.ZB06,A.ZB05,A.ZB04,
                    A.ZB03,A.ZB02,A.ZB01C,A.ZA04,A.UNT_ID,A.ZA03,A.AFA01,A.AFA02,A.AFA03,A.AFA04,A.AFA05,A.AFA06,A.AFA07,
                    A.AFA08,A.AFA09,A.AFA10,A.AFA11,A.AFA12,A.ZB10,A.ZB11,A.IS_VALID,A.SYN_DATE,A.QU_STATE,A.DATA_STATE,
                    A.BALANCEID,A.AKC021,A.ABA01C,A.ABA01N,A.ABC03C,A.ABF01C,A.ABF01N,A.ABF04,A.ABF02C,A.ABF03C,A.ABH01C,
                    A.ABH0201C,A.ABH0202C,A.ABH0203C,A.ABH03C,A.AEB02C,A.AEB01,A.AED01C,A.AEG01C,A.AEG02C,A.AEG04,A.AEG05,
                    A.AEG06,A.AEG07,A.AEG08,A.AEJ01,A.AEJ02,A.AEJ03,A.AEJ04,A.AEJ05,A.AEJ06,A.AEL01,A.AEN02C,A.AEN02N,
                    A.AEI09,A.AEI10,A.AEI08,A.AAA30,A.ABC01C,A.AAA27,A.AAC001,A.AAB01,A.AAB02C,A.AAB03,A.AAB11C,A.AAB11N,
                    A.AAC02C,A.AAC03,A.AAC11C,A.AAD01C,A.AEM02,A.AEM03C,A.AEM04,A.AEI01C,A.AED02,A.AED03,A.AEE01,A.AEE02,
                    A.AEE03,A.AEE11,A.AEE09,A.AEE04,A.AEE05,A.AEE07,A.AEE08,A.AEE10,A.AEE01_CODE,A.AEE02_CODE,A.AEE03_CODE,
                    A.AEE04_CODE,A.AAA09,A.AAA10,A.AAA11,A.AAA43,A.AAA44,A.AAA45,A.AAA46,A.AAA47,A.AAA12,A.AAA13C,A.AAA33C,
                    A.AAA14C,A.AAA15,A.AAA48,A.AAA49,A.AAA50,A.AAA16C,A.AAA36C,A.AAA51,A.AAA51,A.AAA17C,A.AAA18C,A.AAA19,
                    A.AAA20,A.AAA20,A.AAA21C,A.AAA22,A.AAA22,A.AAA23C,A.AAA24,A.AAA25,A.AAA25,A.MED_REC_ID,
                    to_char(A.AAA03,'yyyy-mm-dd hh24:mi:ss') as AAA03,
                    to_char(A.SYN_DATE,'yyyy-mm-dd hh24:mi:ss') as SYN_DATE,
                    to_char(A.AAB01,'yyyy-mm-dd hh24:mi:ss') as AAB01,
                    to_char(A.AAC01,'yyyy-mm-dd hh24:mi:ss') as AAC01,
                    to_char(A.AED04,'yyyy-mm-dd hh24:mi:ss') as AED04,
                    to_char(A.MT_JZRQ,'yyyy-mm-dd') as MT_JZRQ
                    FROM PORTAL_HIS.INIT_MED_REC_MAIN A
                    WHERE {$column} BETWEEN TO_DATE('" . $startTime . "', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('" . $endTime . "', 'yyyy-MM-dd HH24:mi:ss')
                    ORDER BY A.MED_REC_ID ASC";
        }

        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while (@$row = oci_fetch_assoc($data)) {
            foreach ($row as &$value) {
                $source_encoding = mb_detect_encoding($value);
                $value = iconv($source_encoding, 'UTF-8', $value);
            }
            $result[] = $row;
        }


        return $result;
    }

    /**
     * 同步更新患者数据
     * @param $data
     * @param $ABC03C
     * @param $config
     * @return void
     */
    public function addPatientInfo($data, $ABC03C, $config)
    {
        // 主信息
        $hospital_name = config('confAdmin.hospital_name');
        //身份证号信息脱敏
        $AAA07 = $data['AAA07'] ? desensitize($data['AAA07'], 0, 6, '*') : '';
        $AAA07 = $AAA07 ? desensitize($AAA07, 14, 2, '*') : '';
        $AAA07 = $AAA07 ? desensitize($AAA07, 17, 1, '*') : '';
        $patient_info = [
            'hospital_name'   => $data['ZA03'] ?: $hospital_name,   //机构名称
            'AAA28'           => $data['AAA28'],
            'AAA01'           => $data['AAA01'] ? desensitize($data['AAA01'], 1, 1, '*') : '',        //患者姓名
            'AAA02C'          => $data['AAA02C'] ?: '',             //患者性别
            'AAA03'           => $data['AAA03'] ?: '',              //出生日期
            'AAA04'           => $data['AAA04'] ?: '',              //年龄
            'AAA05C'          => $data['AAA05C'] ?: '',             //国籍
            'AAA40'           => $data['AAA40'] ?: '',              //不足一周岁年龄
            'AAA42'           => $data['AAA42'] ?: '',              //新生儿入院体重
            'AEN01'           => $data['AEN01'] ?: '',              //新生儿出生体重
            'AAA06C'          => $data['AAA06C'] ?: '',             //民族代码
            'AAA07'           => $AAA07,                            //身份证号
            'AAA08C'          => $data['AAA08C'] ?: '',             //婚姻状况
            'AEM01C'          => $data['AEM01C'] ?: '',             //离院方式代码
            'AAB01'           => $data['AAB01'] ?: '',              //入院时间
            'AAC01'           => $data['AAC01'] ?: '',              //出院时间
            'AAC11N'          => $data['AAC11N'] ?: '',             //出院医院内部科室名称
            'AAC04'           => $data['AAC04'] ?: '',              //实际住院
            'ADA01'           => $data['ADA01'] ?: '',              //总费用
            'ADA0101'         => $data['ADA0101'] ?: '',            //自付费用
            'AAA29'           => $data['AAA29'] ?: '',              //住院次数
            'ABG01C'          => $data['ABG01C'] ?: '',             //损伤和中毒外部原因编码 no
            'ABG01N'          => $data['ABG01N'] ?: '',             //损伤和中毒外部原因名称 no
            'AAB06C'          => $data['AAB06C'] ?: '',             //入院途径代码
            'ABC01N'          => $data['ABC01N'] ?: '',             //出院主要诊断名称
            'ORG_STATE'       => $data['ORG_STATE'] ?: '',          //质控状态
            'AAA26C'          => $data['AAA26C'] ?: '',             //医疗付费方式代码
            'ATTEND_GRP_CODE' => $data['ATTEND_GRP_CODE'] ?: '',    //主诊组编码
            'ATTEND_GRP_NAME' => $data['ATTEND_GRP_NAME'] ?: '',    //主诊组名称
            'IS_CATA'         => 1,                                  //是否编目
        ];
        \App\Model\PatientInfo::query()->updateOrInsert(['MED_REC_ID' => $data['MED_REC_ID']], $patient_info);

        // 其他信息
        $patient_other_info = [
            'AAB07C'     => $data['AAB07C'] ?: '',          //入院诊断id
            'AAB07N'     => $data['AAB07N'] ?: '',          //入院诊断名称
            'AAB07'      => $data['AAB07'] ?: '',           //入院时情况
            'AAB07D'     => $data['AAB07D'] ?: '',          //入院后确诊日期
            'ABD04'      => $data['ABD04'] ?: '',           //医院感染名称
            'ABD051'     => $data['ABD051'] ?: '',          //门诊与出院诊断符合情况
            'ABD052'     => $data['ABD052'] ?: '',          //术前与术后诊断符合情况
            'ABD053'     => $data['ABD053'] ?: '',          //临床与病理诊断符合情况
            'ABD054'     => $data['ABD054'] ?: '',          //放射与病理诊断符合情况
            'ZB09'       => $data['ZB09'] ?: '',            //手机
            'ZB08'       => $data['ZB08'] ?: '',
            'ZB07'       => $data['ZB07'] ?: '',
            'ZB06'       => $data['ZB06'] ?: '',
            'ZB05'       => $data['ZB05'] ?: '',
            'ZB04'       => $data['ZB04'] ?: '',
            'ZB03'       => $data['ZB03'] ?: '',
            'ZB02'       => $data['ZB02'] ?: '',
            'ZB01C'      => $data['ZB01C'] ?: '',
            'ZA04'       => $data['ZA04'] ?: '',
            'MED_REC_ID' => $data['MED_REC_ID'] ?: '',      //病案⾸⻚ID
            'UNT_ID'     => $data['UNT_ID'] ?: '',          //组织机构代码ID
            'ZA03'       => $data['ZA03'] ?: '',            //机构名称
            'AFA01'      => $data['AFA01'] ?: '',           //抢救次数
            'AFA02'      => $data['AFA02'] ?: '',           //成本次数
            'AFA03'      => $data['AFA03'] ?: '',
            'AFA04'      => $data['AFA04'] ?: '',
            'AFA05'      => $data['AFA05'] ?: '',
            'AFA06'      => $data['AFA06'] ?: '',
            'AFA07'      => $data['AFA07'] ?: '',
            'AFA08'      => $data['AFA08'] ?: '',
            'AFA09'      => $data['AFA09'] ?: '',
            'AFA10'      => $data['AFA10'] ?: '',
            'AFA11'      => $data['AFA11'] ?: '',
            'AFA12'      => $data['AFA12'] ?: '',
            'ZB10'       => $data['ZB10'] ?: '',            //填报版本
            'ZB11'       => $data['ZB11'] ?: '',            //填报说明
            'IS_VALID'   => $data['IS_VALID'] ?: '',        //有效标识
            'SYN_DATE'   => $data['SYN_DATE'] ?: '',        //获取时间
            'QU_STATE'   => $data['QU_STATE'] ?: '',        //是否采集
            'DATA_STATE' => $data['DATA_STATE'] ?: '',      //病案采集状态
            'BALANCEID'  => $data['BALANCEID'] ?: '',       //病案流水号
            'AKC021'     => $data['AKC021'] ?: '',          //⼈群类型
        ];
        PatientOtherInfo::query()->updateOrInsert(['AAA28' => $data['MED_REC_ID']], $patient_other_info);

        $patient_medical_info = [
            'ABA01C'   => $data['ABA01C'] ?: '',            //门(急)诊诊断编码
            'ABA01N'   => $data['ABA01N'] ?: '',            //⻔（急）诊诊断名称
            'ABC03C'   => $data['ABC03C'] ?: '',            //入院病情代码
            'ABF01C'   => $data['ABF01C'] ?: '',            //病理诊断编码
            'ABF01N'   => $data['ABF01N'] ?: '',            //病理诊断名称
            'ABF04'    => $data['ABF04'] ?: '',             //病理号
            'ABF02C'   => $data['ABF02C'] ?: '',            //最高诊断依据代码ID
            'ABF03C'   => $data['ABF03C'] ?: '',            //分化程度编码ID
            'ABH01C'   => $data['ABH01C'] ?: '',            //肿瘤分期是否不详
            'ABH0201C' => $data['ABH0201C'] ?: '',          //肿瘤分期 TID
            'ABH0202C' => $data['ABH0202C'] ?: '',          //肿瘤分期 NID
            'ABH0203C' => $data['ABH0203C'] ?: '',          //肿瘤分期 MID
            'ABH03C'   => $data['ABH03C'] ?: '',            //0～Ⅳ肿瘤分期ID
            'AEB02C'   => $data['AEB02C'] ?: null,          //有无药物过敏
            'AEB01'    => $data['AEB01'] ?: '',             //过敏药物
            'AED01C'   => $data['AED01C'] ?: '',            //病案质量代码ID
            'AEG01C'   => $data['AEG01C'] ?: '',            //血型代码ID
            'AEG02C'   => $data['AEG02C'] ?: '',            //Rh 代码ID
            'AEG04'    => $data['AEG04'] ?: '',             //红细胞(单位)
            'AEG05'    => $data['AEG05'] ?: '',             //血小板(袋)
            'AEG06'    => $data['AEG06'] ?: '',             //血浆(ml)
            'AEG07'    => $data['AEG07'] ?: '',             //全血(ml)
            'AEG08'    => $data['AEG08'] ?: '',             //其它(ml)
            'AEJ01'    => $data['AEJ01'] ?: '',             //颅脑损伤患者入院前昏迷时间（天）
            'AEJ02'    => $data['AEJ02'] ?: '',             //颅脑损伤患者入院前昏迷时间（天）
            'AEJ03'    => $data['AEJ03'] ?: '',             //颅脑损伤患者入院前昏迷时间（天）
            'AEJ04'    => $data['AEJ04'] ?: '',             //颅脑损伤患者入院后昏迷时间（天）
            'AEJ05'    => $data['AEJ05'] ?: '',             //颅脑损伤患者入院后昏迷时间（天）
            'AEJ06'    => $data['AEJ06'] ?: '',             //颅脑损伤患者入院后昏迷时间（天）
            'AEL01'    => $data['AEL01'] ?: '',             //呼吸机使用时间（天）
            'AEN02C'   => $data['AEN02C'] ?: '',            //新生儿出生缺陷诊断
            'AEN02N'   => $data['AEN02N'] ?: '',            //新生儿出生缺陷诊断名称
            'AEI09'    => $data['AEI09'] ?: '',             //日常生活能力评定量得分
            'AEI10'    => $data['AEI10'] ?: '',             //日常生活能力评定量得分
            'AEI08'    => $data['AEI08'] ?: '',             //备注
        ];
        PatientMedicalInfo::query()->updateOrInsert(['AAA28' => $data['MED_REC_ID']], $patient_medical_info);

        //患者住院信息
        $patient_hospital_info = [
            'AAA30'  => $data['AAA30'] ?: '',           //住院号
            'ABC01C' => $data['ABC01C'] ?: '',          //出院时主要诊断编码
            'AAA27'  => $data['AAA27'] ?: '-',          //医疗保险手册(卡)号
            'AAC001' => $data['AAC001'] ?: '',          //医保个人编号
            'AAB01'  => $data['AAB01'] ?: '',           //入院时间（时）
            'AAB02C' => $data['AAB02C'] ?: '',          //入院科别代码
            'AAB03'  => $data['AAB03'] ?: '',           //入院病房
            'AAB11C' => $data['AAB11C'] ?: '',          //入院医院内部科室代码ID
            'AAB11N' => $data['AAB11N'] ?: '',          //入院医院内部科室名称
            'AAC02C' => $data['AAC02C'] ?: '',          //出院科别代码ID
            'AAC03'  => $data['AAC03'] ?: '',           //出院病房
            'AAC11C' => $data['AAC11C'] ?: '',          //出院医院内部科室代码ID
            'AAD01C' => $data['AAD01C'] ?: '',          //转经科别代码ID
            'AEM02'  => $data['AEM02'] ?: '',           //医嘱转院、转社区、卫生院机编码ID
            'AEM03C' => $data['AEM03C'] ?: '',          //是否有出院31日内再住院计划
            'AEM04'  => $data['AEM04'] ?: '',           //31日内再住院目的
            'AEI01C' => $data['AEI01C'] ?: '',          //是否尸检代码ID
        ];
        PatientHospitalInfo::query()->updateOrInsert(['AAA28' => $data['MED_REC_ID']], $patient_hospital_info);

        //患者医生信息
        $patient_doctor_info = [
            'AED02'      => $data['AED02'] ?: '',       //质控医师姓名
            'AED03'      => $data['AED03'] ?: '',       //质控护士姓名
            'AED04'      => $data['AED04'] ?: '',       //病案质量检查日期
            'AEE01'      => $data['AEE01'] ?: '',       //科主任姓名
            'AEE02'      => $data['AEE02'] ?: '',       //主(副主)任医师姓名
            'AEE03'      => $data['AEE03'] ?: '',       //主治医师姓
            'AEE11'      => $data['AEE11'] ?: '',       //主诊医师执业证书编码
            'AEE09'      => $data['AEE09'] ?: '',       //主诊医师姓名
            'AEE04'      => $data['AEE04'] ?: '',       //住院医师姓名
            'AEE05'      => $data['AEE05'] ?: '',       //进修医师姓名
            'AEE07'      => $data['AEE07'] ?: '',       //实习医师姓名
            'AEE08'      => $data['AEE08'] ?: '',       //编码员姓名
            'AEE10'      => $data['AEE10'] ?: '',       //责任护士姓名
            'AEE01_CODE' => $data['AEE01_CODE'] ?: '',  //科主任编码
            'AEE02_CODE' => $data['AEE02_CODE'] ?: '',  //主（副主）任医师工号
            'AEE03_CODE' => $data['AEE03_CODE'] ?: '',  //主治医师工号
            'AEE04_CODE' => $data['AEE04_CODE'] ?: '',  //住院医师工号
        ];
        PatientDoctorInfo::query()->updateOrInsert(['AAA28' => $data['MED_REC_ID']], $patient_doctor_info);

        //患者地址相关信息
        $patient_address_info = [
            'AAA09'  => $data['AAA09'] ?: '',           //出生地省
            'AAA10'  => $data['AAA10'] ?: '',           //出生地市
            'AAA11'  => $data['AAA11'] ?: '',           //出生地县
            'AAA43'  => $data['AAA43'] ?: '',           //籍贯省
            'AAA44'  => $data['AAA44'] ?: '',           //籍贯市
            'AAA45'  => $data['AAA45'] ?: '',           //户籍省
            'AAA46'  => $data['AAA46'] ?: '',           //户籍市
            'AAA47'  => $data['AAA47'] ?: '',           //户籍县
            'AAA12'  => $data['AAA12'] ?: '',           //户籍详细地址
            'AAA13C' => $data['AAA13C'] ?: '',          //户籍地址区县编码
            'AAA33C' => $data['AAA33C'] ?: '',          //户籍街道乡镇代码ID
            'AAA14C' => $data['AAA14C'] ?: '',          //户籍地址邮政编码
            'AAA15'  => $data['AAA15'] ?: '',           //现住址详细地址
            'AAA48'  => $data['AAA48'] ?: '',           //现住址省
            'AAA49'  => $data['AAA49'] ?: '',           //现住址市
            'AAA50'  => $data['AAA50'] ?: '',           //现住址县
            'AAA16C' => $data['AAA16C'] ?: '',          //现住址区县编码
            'AAA36C' => $data['AAA36C'] ?: '',          //现住址街道乡镇代码
            'AAA51'  => $data['AAA51'] ? desensitize($data['AAA51'], 3, 4, '*') : '',    //现住址电话
            'AAA17C' => $data['AAA17C'] ?: '',          //现住址邮政编码
        ];
        PatientAddressInfo::query()->updateOrInsert(['AAA28' => $data['MED_REC_ID']], $patient_address_info);

        //患者工作信息
        $patient_work_info = [
            'AAA18C' => $data['AAA18C'] ?: '',          //职业代码ID
            'AAA19'  => $data['AAA19'] ?: '',           //工作单位及地址
            'AAA20'  => $data['AAA20'] ? desensitize($data['AAA20'], 3, 4, '*') : '',        //工作单位电话
            'AAA21C' => $data['AAA21C'] ?: '',          //工作单位邮政编码
        ];
        PatientWorkInfo::query()->updateOrInsert(['AAA28' => $data['MED_REC_ID']], $patient_work_info);

        //患者联系人信息
        $patient_contacts_info = [
            'AAA22'  => $data['AAA22'] ? desensitize($data['AAA22'], 1, 1, '*') : '',    //联系人姓名
            'AAA23C' => $data['AAA23C'] ?: '',      //联系人关系代码ID
            'AAA24'  => $data['AAA24'] ?: '',       //联系人地址
            'AAA25'  => $data['AAA25'] ? desensitize($data['AAA25'], 3, 4, '*') : '',    //联系人电话
        ];
        PatientContactsInfo::query()->updateOrInsert(['AAA28' => $data['MED_REC_ID']], $patient_contacts_info);
    }
}
