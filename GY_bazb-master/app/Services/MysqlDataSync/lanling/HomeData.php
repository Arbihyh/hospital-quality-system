<?php

namespace App\Services\MysqlDataSync\lanling;

use App\Console\Commands\mysqltoes\Pacs;
use App\Console\Commands\ShizhongDataSync;
use App\Model\Bllb1;
use App\Model\Bllb292;
use App\Model\Bllb294_295;
use App\Model\Bllb294_45;
use App\Model\Bllb303;
use App\Model\DataSyncLog;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLSY;
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
use App\Model\RuleWordMap;
use App\Model\SecondaryOperation;
use App\Model\SM_SSAP;
use App\Model\SSSQ;
use App\Model\Staff;
use App\Model\Yzb;
use App\Model\ZY_BRRY;
use App\Model\ZY_SS;
use App\Services\BlDataFormatService;
use App\Services\EsSaveService;
use App\Services\LanLingIihinterfaceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class HomeData
{

    public static $con;

    public function getConnect()
    {
        if (self::$con) {
            return;
        }
        $username = env('ORACLE_USERNAME', '');
        $password = env('ORACLE_PASSWORD', '');
        $connection = env('ORACLE_HOST', '');
        $port = env('ORACLE_PORT', '');
        $tns = env('ORACLE_TNS', '');
        $con = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
        self::$con = $con;
    }

    /**
     * @param string $startTime
     * 清洗patient_info表中的是否编目字段
     */
    public function isCATA($startTime = '')
    {
        $this->getConnect();
        $timeIndex = Carbon::now()->subDays(15)->startOfDay()->timestamp;
        if (!empty($startTime)) {
            $startTime = strtotime($startTime);
        } else {
            $startTime = $timeIndex;
        }

        while (true) {
            // 如果数据同步到最新时间，则重新从本年3月1日 0点0分0秒到现在
            if ($startTime >= time()) {
                $str = date("Y-m-d H:i:s", time()) . "完成一轮数据同步\n";
                $startTime = Carbon::now()->subDays(15)->startOfDay()->timestamp;
                echo $str;
            }

            $startTimeStr = date("Ymd", $startTime);
            echo '时间：' . $startTimeStr . PHP_EOL;
            $startTime += 86400;
            $sql = "SELECT MED_REC_ID FROM PORTAL_HIS.INIT_MED_REC_MAIN WHERE AAB01 BETWEEN TO_DATE('" . $startTimeStr . "000000', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('" . $startTimeStr . "235959', 'yyyy-MM-dd HH24:mi:ss')";

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
            echo '处理数据：' . count($result) . PHP_EOL;

            $zyh = array_column($result, "MED_REC_ID");
            PatientInfo::query()->whereIn("MED_REC_ID", $zyh)->update(["IS_CATA" => 1]);
        }
    }

    /**
     * @param string $startTime
     * @param string $endTime
     * @param string $zyh
     * 同步指定时间范围内的数据
     */
    public function index($startTime = '', $endTime = '', $zyh = "")
    {
        $this->getConnect();

        $timeIndex = Carbon::now()->subDays(15)->startOfDay()->timestamp;
        if (!empty($startTime)) {
            $startTime = strtotime($startTime);
        } else {
            $startTime = $timeIndex;
        }

        while (true) {
            // 如果数据同步到最新时间，则重新从本年3月1日 0点0分0秒到现在
            if ($startTime >= time()) {
                $str = date("Y-m-d H:i:s", time()) . "完成一轮数据同步\n";
                $startTime = Carbon::now()->subDays(15)->startOfDay()->timestamp;
                echo $str;
            }
            echo date("Y-m-d H:i:s", $startTime) . PHP_EOL;
            // 获取用户信息
            $this->getData($zyh, [], "", $startTime);
            $startTime += 86400;
        }
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function getData($zyh = "", $type = [], $blbh = "", $startTime = "")
    {
        // 如果数据库链接字段为初始化则重新链接
        if (!self::$con) {
            $this->getConnect();
        }

        // 获取用户信息
        $patientData = $this->getPatientInfo($zyh, $startTime);
        if (!empty($patientData)) {
            foreach ($patientData as $patientInfo) {

                $zyh = $patientInfo['MED_REC_ID'];
                if ($startTime) {
                    echo $zyh . PHP_EOL;
                }
                // 医嘱
                if (empty($type) || in_array('yzb', $type)) {
//                    $yzbData = $this->getYzb($zyh);
//                    if (!empty($yzbData)) {
//                        $this->addYzb($yzbData);
//                    }
                }

                // 医生签名
                if (empty($type) || in_array('bl01', $type)) {
                    $this->addBLSY($zyh, $blbh);
                }

                // 会诊信息
                if (empty($type) || in_array('ys_zy_hzyj', $type)) {
//                    $this->YS_ZY_HZYJ($zyh);
                }

                // 检查
                if (empty($type) || in_array('v_jmgs_testresult', $type)) {
//                    $this->V_JMGS_TESTRESULT($zyh);
                }

                // 检验
                if (empty($type) || in_array('v_jmgs_ymresult', $type)) {
//                    $this->V_JMGS_YMresult($zyh);
                }

                // 麻醉记录
                if (empty($type) || in_array('mzjl', $type)) {
                    //            $this->mzjl($zyh);
                }

                if (empty($type) || in_array('pacs', $type)) {
//                    $this->pacs($zyh);
                }

                // 首麻
                if (empty($type) || in_array('sm', $type)) {
                    $this->SM_SSAP($zyh);
                }

                // 输血
                if (empty($type) || in_array('shuxie', $type)) {
//                    $this->BLOOD_BLZK($zyh);
                }
            }
        }

        return true;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function getBlData($zyh = "", $type = [], $blbh = "", $startTime = "")
    {
        // 如果数据库链接字段为初始化则重新链接
        if (!self::$con) {
            $this->getConnect();
        }
        if ($zyh) {
            $szds = new ShizhongDataSync();
            $szds->ZY_BRRY($zyh, 0, 0, 0);
        }

        if ($startTime) {
            echo $zyh . PHP_EOL;
        }
        // 医嘱
        if (empty($type) || in_array('yzb', $type)) {
//                    $yzbData = $this->getYzb($zyh);
//                    if (!empty($yzbData)) {
//                        $this->addYzb($yzbData);
//                    }
        }

        // 医生签名
        // 医生签名
        if (empty($type) || in_array('bl01', $type)) {
            Log::info('qualityHandleV2 addBLSY 开始'.date('Y-m-d H:i:s'), ['zyh' => $zyh]);
            $this->addBLSY($zyh, $blbh);
            Log::info('qualityHandleV2 addBLSY 结束'.date('Y-m-d H:i:s'), ['zyh' => $zyh]);
        }

        // 会诊信息
        if (empty($type) || in_array('ys_zy_hzyj', $type)) {
//                    $this->YS_ZY_HZYJ($zyh);
        }

        // 检查
        if (empty($type) || in_array('v_jmgs_testresult', $type)) {
//                    $this->V_JMGS_TESTRESULT($zyh);
        }

        // 检验
        if (empty($type) || in_array('v_jmgs_ymresult', $type)) {
//                    $this->V_JMGS_YMresult($zyh);
        }

        // 麻醉记录
        if (empty($type) || in_array('mzjl', $type)) {
            //            $this->mzjl($zyh);
        }

        if (empty($type) || in_array('pacs', $type)) {
//                    $this->pacs($zyh);
        }

        // 首麻
        if (empty($type) || in_array('sm', $type)) {
            $this->SM_SSAP($zyh);
        }

        // 输血
        if (empty($type) || in_array('shuxie', $type)) {
//                    $this->BLOOD_BLZK($zyh);
        }

        return true;
    }

    // 输血
    public function BLOOD_BLZK($zyh = "")
    {

        //使用laravel自带的sqlsrv方法连接
        $connectionOptions = [
            'Database' => env("SS_SQLSRV_DATABASE"),
            'UID' => env("SS_SQLSRV_USERNAME"),
            'PWD' => env("SS_SQLSRV_PASSWORD"),
            'LoginTimeout' => 10,
            'TrustServerCertificate' => 1,
            'Encrypt' => 1
        ];

        $connection = sqlsrv_connect(env("SS_SQLSRV_HOST"), $connectionOptions);
        if ($connection) {

            // 使用直接查询获取数据
            $sql = "SELECT * FROM BLOOD_BLZK WHERE ZYH='{$zyh}'";
            $stmt = sqlsrv_query($connection, $sql);
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                foreach ($errors as $error) {
                    return false;
                }
            } else {

                // 获取所有数据，并计算每列的最大宽度
                $rows = [];
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $formattedRow = [];
                    foreach ($row as $key => $value) {
                        if ($value instanceof \DateTime) {
                            $formattedValue = $value->format('Y-m-d H:i:s');
                        } else {
                            $formattedValue = $value === null ? 'NULL' : (string)$value;
                        }


                        $formattedRow[$key] = $formattedValue;
                    }
                    $rows[] = $formattedRow;
                }
                // 打印数据行
                foreach ($rows as $row) {
                    ZY_SS::query()->updateOrInsert(["SXXH" => $row["SXXH"]], $row);
                }
            }

            // 释放资源
            sqlsrv_free_stmt($stmt);
            sqlsrv_close($connection);
        } else {
            $errors = sqlsrv_errors();
            if (is_array($errors)) {
                foreach ($errors as $error) {
                    return false;
                }
            } else {
                return false;
            }
        }
    }

    public function SM_SSAP($zyh = "")
    {

        // 使用直接查询获取数据
        $sql = "SELECT * FROM IIH.VZK_SM_SSAP WHERE ZYH='{$zyh}'";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        SM_SSAP::query()->where("ZYH", $zyh)->delete();
        foreach ($data as $item) {
            $item["SQDH"] = $item["IDSQDH"] ?? "";
            $item["SSKS_MC"] = $item["SSKS"] ?? "";
            $item["SQKS_MC"] = $item["SQKS"] ?? "";
            $item["SSKS"] = "";
            $item["SQKS"] = "";

            SM_SSAP::addData($item);
        }
    }

    public function pacs($zyh)
    {
        $servername = "192.168.53.33:3306"; // 数据库服务器地址
        $username = "jnsyyyxt"; // 数据库用户名
        $password = "Jnsy_yyxt5"; // 数据库密码
        $dbname = "clinical"; // 数据库名
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $dsn = "mysql:host=$servername;dbname=$dbname;charset=utf8mb4";
        // 创建连接
        $conn = new \PDO($dsn, $username, $password, $options);
        $sql = "SELECT * FROM hdr_exam_report WHERE VISIT_ID = {$zyh}";
        $statement = $conn->query($sql);
        $data = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $examTypeConf = config("pacsMap.ExamType");
        if (!empty($data)) {
            DB::table("PACS")->where('ZYH', '=', $zyh)->where('ExamType', '<>', '07')->delete();
            foreach ($data as $v) {
                $examType = isset($examTypeConf[$v['EXAM_CLASS_CODE']]) ? $examTypeConf[$v['EXAM_CLASS_CODE']]['EXAM_TYPE_CODE'] : '';
                $insert = [
                    "StudyUid" => $v['OUT_PATIENT_ID'] . $v['APPLY_NO'] . $v['REPORT_NO'],                  //唯一标识，由APPLY_NO,OUT_PATIENT_ID,REPORT_NO,字段拼接而成
                    "ZYH" => $v['VISIT_ID'],                  // 住院号
                    "JZLSH" => $v['APPLY_NO'],                  //就诊流水号：用于与住院就诊记录表或门诊就诊记录表关联的外键(可选关联关系)
                    "MZZYBZ" => $v['VISIT_TYPE_NAME'],           //门诊/住院标志：1门诊，2住院，3、体检 4、绿色通道、41、门诊绿色通道、42、住院绿色通道、9其他
                    "BRXM" => $v['PERSON_NAME'],               //病人姓名
                    "BRXB" => $v['SEX_NAME'],                  //病人性别
                    "PatientID" => $v['PACS_URL'],                  //影像号：被检查的病人在医院内部的影像号码，即影像图像DICOM文件中对应Dicom中位置(0010,0020)的值
                    "JCXMDM" => $v['EXAM_ITEM_CODE'],            //检查项目代码
                    "SQDH" => $v['APPLY_NO'],                  //申请单号：该检查在HIS或RIS中的申请单编号
                    "JYSJ" => $v['EXAM_PERFORM_TIME'],         //检查时间
                    "ExamType" => $examType,                       //检查类型，编码：表明病人检查的类型。01 计算机X线断层摄影 CT，02 核磁共振成像MR，03 数字减影血管造影DSA，04 普通X光摄影X-Ray，05 特殊X光摄影X-Ray，06 超声检查US，07 病理检查Microscopy，08 內窥镜检查ES，09 核医学检查NM，10 其他检查OT，11 介入
                    "SQKS" => $v['APPLY_DEPT_CODE'],           //申请科室编码
                    "SQKSMC" => $v['APPLY_DEPT_NAME'],           //申请科室名称
                    "SQRGH" => $v['APPLY_DOCTOR_CODE'],         //申请人工号
                    "SQRXM" => $v['APPLY_DOCTOR_NAME'],         //申请人姓名
                    "JCKSMC" => $v['EXAM_ROOM'],                 //检查科室名称
                    "JCYS" => $v['PERFORM_DOCTOR'],            //检查医生姓名
                    "BGSJ" => $v['REPORT_TIME'],               //报告时间
                    "BGRQ" => $v['REPORT_TIME'],               //报告日期
                    "BGRGH" => $v['REPORT_DOCTOR_CODE'],        //报告人工号
                    "BGRXM" => $v['REPORT_DOCTOR_NAME'],        //报告人姓名
                    "SHRGH" => $v['REPORT_CONFIRMER_CODE'],     //审核人工号
                    "SHRXM" => $v['REPORT_CONFIRMER_NAME'],     //审核人姓名
                    "JCBW" => $v['EXAM_PART_NAME'],            //检查部位
                    "BWACR" => $v['EXAM_PART_CODE'],            //检查部位
                    //                    "JCMC"      => $v['EXAM_PART_NAME'],            //检查名称
                    "JCMC" => $v['EXAM_ITEM_NAME'],            //检查名称
                    "YXBX" => $v['EXAM_FEATURE'],              //影像表现或检查所见
                    "YXZD" => $v['EXAM_DIAG'],                 //检查诊断或提示
                    "SFYYY" => $v['PACS_URL'],                  //是否有影像:1：有；2：无；3：未定；
                    "XGBZ" => $v['REPORT_STATUS_NAME'],        //修改标志: 编码。0：正常、1：撤销；
                    "KDSJ" => $v['APPLY_TIME'],                //开单时间
                ];
                DB::table("PACS")->insert($insert);
            }
        }
    }

    /**
     * 获取用户主信息
     * @return array
     */
    public function getPatientInfo($ZYH = "", $startTime = "")
    {
        $sql = "SELECT MED_REC_ID FROM patient_info WHERE 1=1";
        if ($ZYH) {
            $sql .= " and MED_REC_ID='" . $ZYH . "'";
        }
        if ($startTime) {
            $sql .= " AND AAC01 > '" . date('Y-m-d H:i:s', $startTime) . "' and AAC01 <'" . date('Y-m-d H:i:s', $startTime + 86400) . "'";
        }
        $result = DB::select($sql);
        $result = json_decode(json_encode($result, 256), true);
        return $result;
    }

    public function addPatientInfo($data)
    {
        // 主信息
        $hospital_name = config('confAdmin.hospital_name');
        //身份证号信息脱敏
        $AAA07 = $data['AAA07'] ? desensitize($data['AAA07'], 0, 6, '*') : '';
        $AAA07 = $AAA07 ? desensitize($AAA07, 14, 2, '*') : '';
        $AAA07 = $AAA07 ? desensitize($AAA07, 17, 1, '*') : '';
        $patient_info = [
            'hospital_name' => $data['ZA03'] ?: $hospital_name, //机构名称
            'MED_REC_ID' => $data['MED_REC_ID'],
            'AAA28' => $data['AAA28'],
            'AAA01' => $data['AAA01'] ? desensitize($data['AAA01'], 1, 1, '*') : '',        //患者姓名
            'AAA02C' => $data['AAA02C'] ?: '',      //患者性别
            'AAA03' => $data['AAA03'] ?: '',        //出生日期
            'AAA04' => $data['AAA04'] ?: '',        //年龄
            'AAA05C' => $data['AAA05C'] ?: '',      //国籍
            'AAA40' => $data['AAA40'] ?: '',        //不足一周岁年龄
            'AAA42' => $data['AAA42'] ?: '',        //新生儿入院体重
            'AEN01' => $data['AEN01'] ?: '',        //新生儿出生体重
            'AEN01' => $data['AEN01'] ?: '',        //新生儿出生体重
            'AAA06C' => $data['AAA06C'] ?: '',      //民族代码
            'AAA07' => $AAA07,                      //身份证号
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
            'ORG_STATE' => $data['ORG_STATE'] ?: '', //质控状态
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
        $sql = "SELECT A.*,to_char(SQRQ,'yyyy-mm-dd hh24:mi:ss') as SQRQ,to_char(SSRQ,'yyyy-mm-dd hh24:mi:ss') as SSRQ FROM PORTAL_HIS.SM_SSSQ A WHERE ZYH=" . $ZYH;
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
        $sql = "SELECT A.*,to_char(JFRQ,'yyyy-mm-dd hh24:mi:ss') as JFRQ FROM PORTAL_HIS.V_JMGS_BASY_FYMX A WHERE ZYH=" . $ZYH;
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
                'AAA28' => $val['ZYH'], //zyh
                'FYXH' => $val['FYXH'] ?? '', //费用序号
                'FYMC' => $val['FYMC'] ?? '', //费用名称
                'ZFJE' => $val['ZFJE'] ?? '', //自付金额
                'JFRQ' => $val['JFRQ'] ?? '', //计费日期
                'FYSL' => $val['FYSL'] ?? '', //费用数量
                'FYDJ' => $val['FYDJ'] ?? '', //费用单价
                'ZJE' => $val['ZJE'] ?? '', //总金额
                'FYKS' => $val['FYKS'] ?? '', //费用科室
                'FYGB' => $val['FYGB'] ?? '',
                'SYFYGB' => $val['SYFYGB'] ?? '',
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
                'ICD10_ID1' => $val['ZDBM'],
                'ICD10_NAME' => $val['ZDMC'],
                'DIA_ORDER' => $val['ZDXH'],
                'LBMC' => $val['LBMC'],
                'RYQK' => $val['RYQK']
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
                'ICD9_ID1' => $val['SSCZBM'] ?? '', //手术或操作ID
                'ICD9_NAME' => $val['SSCZMC'] ?? '', //手术或操作名称
                'OPE_DATE' => $val['SSCZRQ'] ?? '', //手术或操作日期
                'OPE_ORDER' => $val['SSSX'] ?? '', //手术序号
                'OPE_LEVEL' => $val['SSJB'] ?? '', //手术级别
                'OPE_TYPE' => $val['SSLX'] ?? '', //手术类型
                'OPE_MAN_NAME' => $val['SZXM'] ?? '', //主刀医师姓名
                'OPE_MAN_CODE' => $val['SZBM'] ?? '', //主刀医师编码
                'FRIST_ASSISTANT_CODE' => $val['YZYSBM'] ?? '', //一助医师编码
                'FRIST_ASSISTANT_NAME' => $val['YZXM'] ?? '', //一助医师姓名
                'SECOND_ASSISTANT_CODE' => $val['EZYSBM'] ?? '', //二助医师编码
                'SECOND_ASSISTANT_NAME' => $val['EZXM'] ?? '', //二助医师姓名
                'INCISION_GRADE_ID' => $val['QKDJ'] ?? 100, //切口等级
                'HEAL_ID' => $val['YHDJ'] ?? 100, //愈合等级
                'HOCUS_WAY_ID' => $val['MZFS'] ?? '', //麻醉方式
                'HOCUS_MAN_CODE' => $val['MZYSBM'] ?? '', //麻醉医师编码
                'HOCUS_MAN_NAME' => $val['MZYSXM'] ?? '', //麻醉医师名称
                'START_TIME' => $val['SSKSSJ'] ?? '', //手术开始时间
                'END_TIME' => $val['SSJSSJ'] ?? '', //手术结束时间
                'RJSS' => $val['SFWRJSS'] ?? '', //是否日间手术
                'CYRQ' => $val['CYRQ'] ?? '',
                'SFZYSS' => $val['SFZYSS'] ?? '',
                'QKDJ' => $val['QKDJ'] ?? '',
                'YHDJ' => $val['YHDJ'] ?? '',
                'BAHM' => $val['BAHM'] ?? '',
                'ZYHM' => $val['ZYHM'] ?? '',
                'SSPB' => intval(array_search($val['SSPB'], $config['SSPB']) ?? 5) //手术判别
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
        $sql = "SELECT A.*,to_char(TZSJ,'yyyy-mm-dd hh24:mi:ss') as TJ,to_char(XZJDSJ,'yyyy-mm-dd hh24:mi:ss') as XJ,to_char(TZQRSJ,'yyyy-mm-dd hh24:mi:ss') as TZJ,to_char(APSJ,'yyyy-mm-dd hh24:mi:ss') as AJ,to_char(KZSJ,'yyyy-mm-dd hh24:mi:ss') as KJ FROM PORTAL_HIS.BTF_EMR_YZB A WHERE ZYH=" . $ZYH;
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
        $zyh = 0;
        foreach ($data as $val) {
            $zyh = $val['ZYH'];
            if (!empty($val['DSG_OPERATION']) && $val['DSG_OPERATION'] == 'D') {
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
                    //                    'ZXSJ' => $val['ZJ'] ?? '', //---这个注释掉 oracle没有这个字段
                    'NWARN' => $val['NWARN'] ?? '',
                    'DSG_LDR_TIME' => $val['DSG_LDR_TIME'] ?? '',
                    'DSG_OPERATION' => $val['DSG_OPERATION'] ?? '',
                ];
            }
        }
        $yzb = Yzb::query()->where('ZYH', '=', $zyh)->get()->toArray();
        $idArr = array_column($yzb, 'id');
        Yzb::query()->whereIn('id', $idArr)->delete();
        if ($idArr) {

            $params = [
                'index' => 'yzb_2023',
                'body' => [
                    'query' => [
                        'term' => [
                            'ZYH' => $zyh
                        ]
                    ]
                ]
            ];
            app('es')->deleteByQuery($params);
        }

        $oldYZBXH = array_column($yzb, 'YZBXH');
        $YZBXH = array_column($insertData, 'YZBXH');
        $deleteYZBXH = array_diff($YZBXH, $oldYZBXH);
        if ($deleteYZBXH) {
            foreach ($deleteYZBXH as $xh) {
                QualitySendMsgLog::setStatus($val['ZYH'], 109, $xh);
                QualitySendMsgLog::setStatus($val['ZYH'], 110, $xh);
            }
        }

        //Yzb::query()->where('ZYH', '=', $data[0]['ZYH'])->delete();
        if (!empty($insertData)) {
            //Yzb::query()->insert($insertData);
            $chunkList = array_chunk($insertData, 500);
            foreach ($chunkList as $value) {
                Yzb::query()->insert($value);
            }
        }
    }

    public function formatBL01BLMC306($bl01 = [])
    {
        if (empty($bl01['HJNR'])) {
            return false;
        }

        $HJNR = $bl01['HJNR'];
        $HJNR = str_replace("：", ":", $HJNR);
        preg_match("/记录日期:(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", $HJNR, $timeMatches);
        $timePrefix = !empty($timeMatches[1]) ? $timeMatches[1] : '';
        // 更新blmc
        if (!empty($timePrefix)) {
            EMR_BL_BL01::query()->where('BLBH', $bl01['BLBH'])->update(['BLMC' => $timePrefix . ' 手术记录 {' . $bl01["BLMC"] . '}', 'ZXSJ' => $timePrefix]);
        }
    }

    public function formatBL01BLMC82($bl01 = [])
    {

        if (empty($bl01['HJNR'])) {
            return false;
        }

        // 首先尝试匹配"记录时间：{YYYY-MM-DD HH:MM}"格式
        preg_match("/术前小结及术前讨论结论记录\s*\{\s*(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\s*\}/", $bl01['HJNR'], $timeMatches);
        $timePrefix = !empty($timeMatches[1]) ? $timeMatches[1] : '';

        if (!empty($timePrefix)) {
            // 如果无法提取时间，使用CJSJ
            $formattedTime = date('Y-m-d H:i:s', strtotime($bl01['CJSJ']));
            EMR_BL_BL01::query()->where('BLBH', $bl01['BLBH'])->update(['operation_time' => strtotime($formattedTime)]);
        }
    }

    /**
     * @param array $bl01
     * 格式化bl01表中的病例名称
     */
    public function formatBL01BLMC294($bl01 = [])
    {

        $HJNR = $bl01['HJNR'];
        if (empty($HJNR)) {
            return false;
        }
        preg_match("/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}) (\S+)/", $HJNR, $timeMatches);
        if (empty($timeMatches[1])) {
            return false;
        }
        // 更新blmc
        $blmc = $timeMatches[0];
        // if (!empty($timeMatches[2]) && mb_strlen($timeMatches[2]) <= 15) {
        //     $blmc .= $timeMatches[2];
        // }
        $blmc .= ' {' . $bl01["BLMC"] . '} ';
        EMR_BL_BL01::query()->where('BLBH', $bl01['BLBH'])->update(['BLMC' => $blmc, 'ZXSJ' => $timeMatches[1]]);
    }

    /**
     * @param string $zyh
     * @param string $blbh
     * @param int $isRecordLog
     * @return bool
     * 同步病程记录相关数据
     */
    public function addBLSY($zyh = "", $blbh3 = "", $isRecordLog = 1)
    {
        if ($isRecordLog == 1) {
            DataSyncLog::addData(['zyh' => $zyh, 'content' => '4、数据病程记录开始']);
        }
        $bldf = new BlDataFormatService();
        $sql = "SELECT BLBH, ZYH, BLLB, BLMC, MBLB, ZXSJ, CJSJ, WCSJ, SXYS, BRKS, CJKS, BLZT, JLXH, BLBH, XGGH, XGSJ, HJNR FROM IIH.VZK_JMGS_BASY_QBL WHERE ZYH = '{$zyh}'";
        if (!empty($blbh3)) {
            $sql .= " and BLBH='{$blbh3}'";
        }
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if ($isRecordLog == 1) {
            DataSyncLog::addData(['zyh' => $zyh, 'content' => '5、本次同步数据量', 'data_nums' => count($data)]);
        }

        if (empty($data) && !empty($zyh)) {
            if ($isRecordLog == 1) {
                DataSyncLog::addData(['zyh' => $zyh, 'content' => '6、未获取到病程记录，清空mysql中的病程记录']);
            }
            // 未同步到数据则清空bl01
            EsSaveService::deleteBl01ByZyh($zyh);
            return false;
        }

        if (empty($blbh3)) {
            $diffBLBH = array_column($data, "BLBH");
            // 检查数据表中有没有不在新同步的数据中的blbh
            $diffRes = EMR_BL_BL01::query()->where('JZHM', $zyh)->whereNotIn("BLBH", $diffBLBH)->get(["BLBH"])->toArray();
            if ($diffRes) {
                foreach ($diffRes as $blbh) {
                    if ($isRecordLog == 1) {
                        DataSyncLog::addData(['zyh' => $zyh, 'content' => '7、删除mysql中的病程记录：' . $blbh['BLBH']]);
                    }
                    EsSaveService::deleteBl01ByBLBH($blbh['BLBH']);
                }
            }
        } else {
            EsSaveService::deleteBl01ByBLBH($blbh3);
        }

        $sj = PatientInfo::query()->where('MED_REC_ID', $zyh)->first(['MED_REC_ID', 'AAB01', 'AAC01']);
        $staff = Staff::query()->get()->toArray();
        $staff = array_column($staff, "name", "code");
        foreach ($data as $item) {
            if ($isRecordLog == 1) {
                DataSyncLog::addData(['zyh' => $zyh, 'content' => '8、同步病程记录：' . $item['BLBH']]);
            }
            //将三院的BLOB类型的数据转化为字符串
            $SXYS = array_search($item["SXYS"], $staff);
            $result = [
                'JZHM' => $item['ZYH'] ?? '',
                'BLBH' => $item['BLBH'] ?? '',
                'BLLB' => $item['BLLB'] ?? '',
                'MBLB' => $item['MBLB'] ?? '',
                'BLMC' => $item['BLMC'] ?? '',
                'AAB01' => $sj->AAB01 ?? '',
                'AAC01' => $sj->AAC01 ?? '',
                'YWSJ' => $item['ZXSJ'] ?? '',
                'CJSJ' => $item['CJSJ'] ?? '',
                'WCSJ' => $item['WCSJ'] ?? '',
                'SXYS' => $SXYS ?? '',
                'BRKS' => $item['BRKS'] ?? '',
                'CJKS' => $item['CJKS'] ?? '',
                'BLZT' => $item['BLZT'] ?? '',
            ];
            $sql = "SELECT * FROM iih.vzk_mr_log WHERE BLBH = '" . $result['BLBH'] . "' and DT_OPERATE>'".$result['AAB01']."' ORDER BY DT_OPERATE DESC";
            $connectRes = oci_parse(self::$con, $sql);
            oci_execute($connectRes, OCI_DEFAULT);
            $data = [];
            while ($row = oci_fetch_assoc($connectRes)) {
                $data[] = $row;
            }
            if (!empty($data)) {
                $result['first_blsy_time'] = $data[0]["DT_OPERATE"];
            }

            $temp = [
                'JZHM' => $item['ZYH'] ?? '',
                'BLBH' => $item['BLBH'] ?? '',
                'JLXH' => $item['JLXH'] ?? '',
                'XGGH' => $item['XGGH'] ?? '',
                'XGSJ' => $item['XGSJ'] ?? '',
            ];

            //处理死亡记录
            if (strpos($result['BLMC'], "死亡记录") !== false) {
                $result['BLLB'] = 288;
                $result['MBLB'] = 288;
            }
            $bl01Data = $result;
            EMR_BL_BL01::query()->updateOrInsert(['BLBH' => $item['BLBH']], $result); //插入或更新病历数据
            EMR_BL_BLXG::query()->updateOrInsert(['BLBH' => $item['BLBH']], $temp); //插入或更新BLXG数据
            EMR_BL_BLSY::query()->insert(["SYYS" => $SXYS, "BLBH" => $item['BLBH']]);


            $Mr_str = "";
            $res = LanLingIihinterfaceService::getModel()->setUri('get_mr_details', true)->setXmlParams(['Id_mr' => $result['BLBH']])->postCurl();
            if ($res && data_get($res, 'Code') === '0' && data_get($res, 'Data.Result_flag') == 'Y') {
                $Mr_str = str_replace('[图片]', '', data_get($res, 'Data.Mr_str'));
                if (empty($Mr_str)) {
                    Log::error('病历内容Data为空::', array_merge(['Id_mr' => $result['BLBH']], compact('res')));
                } else {
                    if ($zyh == '1001Z8100000032DCDOK') {
                        var_dump($Mr_str);
                    }
                    $Mr_str = str_replace("婚姻状况：", "婚姻：", $Mr_str);
                    EMR_BL_BLXG::query()->updateOrInsert(['BLBH' => $result['BLBH']], ['HJNR' => $Mr_str]);
                    $result["HJNR"] = $Mr_str;
                    $bldf->insertData([], $result["MBLB"], $result);
                }
            }
            if (empty($Mr_str)) {
                DataSyncLog::addData(['zyh' => $zyh, 'content' => '病历HJNR内容为空' . json_encode($res, JSON_UNESCAPED_UNICODE)]);
            }
            // 格式化病例名称
            $bl01Data["HJNR"] = $Mr_str;
            if ($bl01Data["BLLB"] == 294 || $bl01Data["BLLB"] == 43) {
                $this->formatBL01BLMC294($bl01Data);
            }
            if ($bl01Data["BLLB"] == 303) {
                $this->formatBL01BLMC306($bl01Data);
            }
            if ($bl01Data["BLLB"] == 82) {
                $this->formatBL01BLMC82($bl01Data);
            }
        }
        EsSaveService::bl01($zyh);
    }

    public function getMatchResultByMblb($hjnr, $mblb)
    {
        $result = '';
        if (!empty($hjnr)) {
            $hjnr = str_replace("：", ":", $hjnr);

            switch ($mblb) {
                case 26: //阶段小结
                    $patten = "/\{(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\}(阶段小结)/u";
                    break;
                case 27: //抢救记录
                    $patten = "/\{(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\}(抢救记录)/u";
                    break;
                case 30: //转接科记录
                    $patten = "/\{(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\}(转入记录|转出记录)/u";
                    break;
                case 32: //会诊记录
                    $patten = "/\{(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\}(会诊记录)/u";
                    break;
                case 42: //术后首次病程记录
                    $patten = "/\{(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\}(术后首次病程记录)/u";
                    break;
                case 45: //输血记录
                    $patten = "/\{(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\}(输血记录)/u";
                    break;
                case 50: //查房记录
                    $patten = "/(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\s+(.*?查房记录)/u";
                    break;
                case 82: //术前小结
                    $patten = "/\{(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\}(术前小结及术前讨论结论记录)/u";
                    break;
                case 295: //首次病程记录
                    $patten = "/\{(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\}(首次病程记录)/u";
                    break;
                case 296: //日常病程记录
                    $patten = "/\{(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\}.*(病程记录|操作记录|产后记录)/u";
                    break;
                default:
                    $patten = "";
            }
            if (!empty($patten)) {
                preg_match_all($patten, $hjnr, $match);
                if (isset($match[0]) && !empty($match[0][0])) {
                    $result = str_replace(["{", "}"], "", $match[0][0]);
                } else {
                    $result = '';
                }
            }
        }
        return $result;
    }


    public function text_name($text)
    {
        $MBLB = 306;
        $bl = [
            '306' => '{手 术 记 录}',
            '30301' => '{剖 宫 产 手 术 记 录}',
            '30375' => '{手术安全核查表}',
            '75' => '{手术安全核查表}',
            '3030002' => '{手 术 记 录 附 页}',
            '3030001' => '{条形码粘贴（信息记录）单}',
        ];
        foreach ($bl as $k => $v) {
            if (strstr($text, $v)) {
                $MBLB = $k;
            }
        }
        return $MBLB;
    }


    /**
     * @param $zyh
     * 医生签名
     */
    public function addBLSY2($data)
    {
        //        $sql = "SELECT a.*,to_char(ZXSJ,'yyyy-mm-dd hh24:mi:ss') as ZJ,to_char(CJSJ,'yyyy-mm-dd hh24:mi:ss') as CJ,to_char(WCSJ,'yyyy-mm-dd hh24:mi:ss') as WJ,to_char(XGSJ,'yyyy-mm-dd hh24:mi:ss') as XJ FROM PORTAL55_EMR.V_JMGS_BASY_QBL a WHERE a.ZYH={$zyh}";
        //        $result = oci_parse(self::$con, $sql);
        //        oci_execute($result, OCI_DEFAULT);
        //        $data = [];
        //        while ($row = oci_fetch_assoc($result)) {
        //            $data[] = $row;
        //        }
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
            $str = $item['HJNR']; //$this->blobToStr($item['BLNR']);
            if (empty($str)) {
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
        $sql = "select JLXH,BLBH,SYYS,to_char(SYSJ,'yyyy-mm-dd hh24:mi:ss') as SYSJ,to_char(JLSJ,'yyyy-mm-dd hh24:mi:ss') as JLSJ from PORTAL55_EMR.EMR_BL_BLSY WHERE BLBH in ($blbhStr)";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }

        foreach ($data as $item) {
            $insertData = [
                'JLXH' => $item['JLXH'],
                'BLBH' => $item['BLBH'],
                'SYYS' => $item['SYYS'],
                'SYSJ' => $item['SYSJ'],
                'JLSJ' => $item['JLSJ'],
            ];

            \App\Model\EMR_BL_BLSY::query()->updateOrInsert(['JLXH' => $insertData['JLXH']], $insertData);
        }
    }


    /**
     * @param $zyh
     * 医生签名
     */
    public function getBLSY($zyh)
    {
        $sql = "SELECT a.*,to_char(ZXSJ,'yyyy-mm-dd hh24:mi:ss') as ZJ,to_char(CJSJ,'yyyy-mm-dd hh24:mi:ss') as CJ,to_char(WCSJ,'yyyy-mm-dd hh24:mi:ss') as WJ,to_char(XGSJ,'yyyy-mm-dd hh24:mi:ss') as XJ FROM PORTAL55_EMR.V_JMGS_BASY_QBL a WHERE a.ZYH={$zyh}";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }
        $bl = [];
        foreach ($data as $item) {
            $bl[$item['ZYH']][] = [
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
                'JLXH' => $item['JLXH'] ?? '',
                'bl_type' => 0,
                'HJNR' => $this->blobToStr($item['BLNR'])
            ];
        }

        return $bl;
    }


    public function bl01New($zyh)
    {

        $sql = "SELECT BLBH,JZHM,BLLX,BLLB,BLMC,MBLB,MBBH,to_char(CJSJ,'yyyy-mm-dd hh24:mi:ss') as CJSJ,SQDH,DLLB,BLZT,SXYS,to_char(WCSJ,'yyyy-mm-dd hh24:mi:ss') as WCSJ,BRKS,BRBH,BLZM,DLJ,to_char(ZXSJ,'yyyy-mm-dd hh24:mi:ss') as ZXSJ,CJKS,BRXM,BRZD,SSYS,SYBZ,BZMBBH,BLYM,YMJL,to_char(RYZDSJ,'yyyy-mm-dd hh24:mi:ss') as RYZDSJ,PTID,BLZSTJ,JGID,ZDMC,ZDLX,CXPX,SBBZ,WZZT FROM PORTAL55_EMR.EMR_BL_BL01 WHERE BLLB=2000001 AND JZHM='{$zyh}'";
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
            $result = [
                'BLBH' => $item['BLBH'] ?? '',
                'JZHM' => $item['JZHM'] ?? '',
                'BRBH' => $item['BRBH'] ?? '',
                'BLLX' => $item['BLLX'] ?? '',
                'BLLB' => $item['BLLB'] ?? '',
                'BLMC' => $item['BLMC'] ?? '',
                'BLZM' => $item['BLZM'] ?? '',
                'DLLB' => $item['DLLB'] ?? '',
                'DLJ' => $item['DLJ'] ?? '',
                'MBLB' => $item['MBLB'] ?? '',
                'MBBH' => $item['MBBH'] ?? '',
                'ZXSJ' => $item['ZXSJ'] ?? '',
                'CJSJ' => $item['CJSJ'] ?? '',
                'WCSJ' => $item['WCSJ'] ?? '',
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
                'WZZT' => $item['WZZT'] ?? ''
            ];
            \App\Model\EMR_BL_BL01_NEW::query()->updateOrInsert(['BLBH' => $result['BLBH']], $result);
        }
    }


    public function getBl01New($zyh)
    {

        $sql = "SELECT BLBH,JZHM,BLLX,BLLB,BLMC,MBLB,MBBH,to_char(CJSJ,'yyyy-mm-dd hh24:mi:ss') as CJSJ,SQDH,DLLB,BLZT,SXYS,to_char(WCSJ,'yyyy-mm-dd hh24:mi:ss') as WCSJ,BRKS,BRBH,BLZM,DLJ,to_char(ZXSJ,'yyyy-mm-dd hh24:mi:ss') as ZXSJ,CJKS,BRXM,BRZD,SSYS,SYBZ,BZMBBH,BLYM,YMJL,to_char(RYZDSJ,'yyyy-mm-dd hh24:mi:ss') as RYZDSJ,PTID,BLZSTJ,JGID,ZDMC,ZDLX,CXPX,SBBZ,WZZT FROM PORTAL55_EMR.EMR_BL_BL01 WHERE BLLB=2000001 AND JZHM='{$zyh}'";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }

        $re = [];
        foreach ($data as $item) {
            $re[] = [
                'BLBH' => $item['BLBH'] ?? '',
                'JZHM' => $item['JZHM'] ?? '',
                'BRBH' => $item['BRBH'] ?? '',
                'BLLX' => $item['BLLX'] ?? '',
                'BLLB' => $item['BLLB'] ?? '',
                'BLMC' => $item['BLMC'] ?? '',
                'BLZM' => $item['BLZM'] ?? '',
                'DLLB' => $item['DLLB'] ?? '',
                'DLJ' => $item['DLJ'] ?? '',
                'MBLB' => $item['MBLB'] ?? '',
                'MBBH' => $item['MBBH'] ?? '',
                'ZXSJ' => $item['ZXSJ'] ?? '',
                'CJSJ' => $item['CJSJ'] ?? '',
                'WCSJ' => $item['WCSJ'] ?? '',
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
                'WZZT' => $item['WZZT'] ?? ''
            ];
        }

        return $re;
    }

    /**
     * @param $zyh
     * 护士分床时间
     */
    public function ZY_HCMX($zyh)
    {
        $sql = "SELECT ZYH,to_char(HCRQ,'yyyy-mm-dd hh24:mi:ss') as HCRQ,to_char(ZZRQ,'yyyy-mm-dd hh24:mi:ss') as ZZRQ,HCLX,HQCH,HHCH,HQKS,HHKS,HQBQ,HHBQ,JSCS,CZGH,JGID FROM PORTAL_HIS.BTF_ZY_HCMX WHERE ZYH={$zyh}";
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
                'ZYH' => $item['ZYH'],
                'HCRQ' => $item['HCRQ'],
                'ZZRQ' => $item['ZZRQ'],
                'HCLX' => $item['HCLX'],
                'HQCH' => $item['HQCH'],
                'HHCH' => $item['HHCH'],
                'HQKS' => $item['HQKS'],
                'HHKS' => $item['HHKS'],
                'HQBQ' => $item['HQBQ'],
                'HHBQ' => $item['HHBQ'],
                'JSCS' => $item['JSCS'],
                'CZGH' => $item['CZGH'],
                'JGID' => $item['JGID']
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

        //        $sql = "SELECT SQXH, JZHM, SQKS, SQYS, to_char(SQSJ,'yyyy-mm-dd hh24:mi:ss') as SQSJ, HZMD, HZMD2, HZSJ, YQDX, JJBZ, TJBZ, TJYS, TJSJ, ZFBZ, JSBZ, JSSJ, TXRY, BQZL, HZLX, BLBH, SQZD, JGID, JSYS, JZBZ, HZLB FROM PORTAL_HIS.BTF_YS_ZY_HZSQ  WHERE JZHM={$zyh}";
        //修改后sql
        $sql = "SELECT SQXH, JZHM, SQKS, SQYS, to_char(SQSJ,'yyyy-mm-dd hh24:mi:ss') as SQSJ, HZMD, HZSJ, YQDX, JJBZ, TJBZ, TJYS, TJSJ, ZFBZ, JSBZ, JSSJ, TXRY, BQZL, HZLX, BLBH  FROM PORTAL_HIS.BTF_YS_ZY_HZSQ  WHERE JZHM={$zyh}";
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

            $str = $item['BQZL']; //blobToStr($item['BQZL']);
            $inData = [
                'SQXH' => $item['SQXH'] ?? '',
                'JZHM' => $item['JZHM'] ?? '',
                'SQKS' => $item['SQKS'] ?? '',
                'SQYS' => $item['SQYS'] ?? '',
                'SQSJ' => $item['SQSJ'] ?? '',
                'HZMD' => $item['HZMD'] ?? '',
                //                'HZMD2' => $item['HZMD2'] ?? '',//---这个字段注释掉 oracle没有这个字段
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
                //                'SQZD' => $item['SQZD'] ?? '',//---这个字段注释掉 oracle没有这个字段
                //                'JGID' => $item['JGID'] ?? '',//---这个字段注释掉 oracle没有这个字段
                //                'JSYS' => $item['JSYS'] ?? '',//---这个字段注释掉 oracle没有这个字段
                //                'JZBZ' => $item['JZBZ'] ?? '',//---这个字段注释掉 oracle没有这个字段
                //                'HZLB' => $item['HZLB'] ?? '',//---这个字段注释掉 oracle没有这个字段
            ];
            $SQXH[] = $inData['SQXH'];
            \App\Model\YS_ZY_HZSQ::query()->updateOrInsert(['SQXH' => $item['SQXH'],], $inData);
        }

        $SQXHStr = implode(',', $SQXH);
        //        $sql = "SELECT JLXH,SQXH,HZYJ,HZYJ2,KSDM,SSYS,SXYS,to_char(SXSJ,'yyyy-mm-dd hh24:mi:ss') as SXSJ,to_char(QMSJ,'yyyy-mm-dd hh24:mi:ss') as QMSJ FROM PORTAL_HIS.BTF_YS_ZY_HZYJ WHERE SQXH in ($SQXHStr)";
        //修改后sql
        $sql = "SELECT JLXH,SQXH,HZYJ,KSDM,SSYS,SXYS,to_char(SXSJ,'yyyy-mm-dd hh24:mi:ss') as SXSJ,to_char(QMSJ,'yyyy-mm-dd hh24:mi:ss') as QMSJ FROM PORTAL_HIS.BTF_YS_ZY_HZYJ WHERE SQXH in ($SQXHStr)";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        foreach ($data as $item) {
            $str = $item['HZYJ']; //blobToStr($item['HZYJ']);
            $insertData = [
                'JLXH' => $item['JLXH'] ?? '',
                'SQXH' => $item['SQXH'] ?? '',
                'HZYJ' => $str ?? '',
                //                'HZYJ2' => $item['HZYJ2'] ?? '',//---这个字段注释掉 oracle没有这个字段
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

            \App\Model\Mzjl::query()->updateOrInsert(['DCID' => $insertData['DCID']], $insertData);
        }
    }

    /**
     * @param $zyh
     * 入院途径
     */
    public function BaBrsy($zyh)
    {
        $sql = "SELECT ZYH AS AAA28,CYBQ,ZZYLJG FROM PORTAL_HIS.ba_brsy WHERE ZYH={$zyh}";
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

        $sql = "SELECT YJXH,TJHM,ZYH,ZYHM,BRXM,to_char(KDRQ,'yyyy-mm-dd hh24:mi:ss') as KDRQ,KSDM,YSDM,to_char(ZXRQ,'yyyy-mm-dd hh24:mi:ss') as ZXRQ,ZXKS,ZXPB,HJGH,BBBM,ZYSX,ZFPB,HYMX,YJPH,SQDH,BWID,JBID,DJZT,SQWH,FYBQ,SQID,YQDH,JGID,SSYS,SSYZ,SSEZ,SSSZ,JZBZ FROM PORTAL_HIS.YJ_ZY01 WHERE ZYH={$zyh}";
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
        $sql = "SELECT SBXH, YJXH, YLXH, XMLX, YJZX, YLDJ, YLSL, FYGB, ZFBL, YZXH, TPLJ, YEPB, TMDY_LQ, TMH, JGID, ZTMC, JHH, XDH, JHSJ, JFID FROM PORTAL_HIS.YJ_ZY02 WHERE YJXH in ($YJXHstr)";
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
                'SBXH' => $item['SBXH'],
                'YJXH' => $item['YJXH'],
                'YLXH' => $item['YLXH'],
                'XMLX' => $item['XMLX'],
                'YJZX' => $item['YJZX'],
                'YLDJ' => $item['YLDJ'],
                'YLSL' => $item['YLSL'],
                'FYGB' => $item['FYGB'],
                'ZFBL' => $item['ZFBL'],
                'YZXH' => $item['YZXH'],
                'TPLJ' => $item['TPLJ'],
                'YEPB' => $item['YEPB'],
                'TMDY_LQ' => $item['TMDY_LQ'],
                'TMH' => $item['TMH'],
                'JGID' => $item['JGID'],
                'ZTMC' => $item['ZTMC'],
                'JHH' => $item['JHH'],
                'XDH' => $item['XDH'],
                'JHSJ' => $item['JHSJ'],
            ];
            \App\Model\YJ_ZY02::query()->updateOrInsert(['SBXH' => $insertData['SBXH']], $insertData);
        }
    }

    public function blobToStr($blob = null)
    {
        $str = '';
        if (!is_object($blob)) {
            return $blob;
        }
        if (!empty($blob)) {
            $text = $blob->load();
            $blob->free();
            $mde = mb_detect_encoding($text, array("ASCII", 'UTF-8', "GB2312", "GBK", 'BIG5'));
            if ($mde) {
                $str = mb_convert_encoding($text, 'utf-8', $mde);
            }
        }

        return $str;
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
                'JLXH' => $item['JLXH'],
                'JZHM' => $item['JZHM'],
                'BLBH' => $item['BLBH'],
                'XMXH' => $item['XMXH'],
                'XMMC' => $item['XMMC'],
                'XMQZ' => $item['XMQZ'],
                'DYYS' => $item['DYYS'],
                'DLLJ' => $item['DLLJ'],
                'GLZD' => $item['GLZD'],
                'KSMRZ' => $item['KSMRZ'],
                'SYBTX' => $item['SYBTX'],
                'XMNM' => $item['XMNM'],
            ];

            \App\Model\EMR_BL_BASYSJ::query()->updateOrInsert(['JLXH' => $insertData['JLXH']], $insertData);
        }
    }

    public function V_JMGS_TESTRESULT($zyh)
    {
        //进行数据库连接
        $username = "BTF";
        $password = "BTF";
        $connection = "192.168.10.20";
        $port = 1521;
        $tns = "xhlis";
        $con = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }

        $sql = "SELECT REPORT_PK,PERSON_NAME,IN_PATIENT_ID,OUT_PATIENT_ID,VISIT_TYPE_CODE,SAMPLE_NO,REPORT_NO,SPECIMEN_NAME,INP_NO,
                    LAB_DIAGNOSIS_NAME,LAB_ITEM_ENAME,LAB_ITEM_NAME,LAB_YM_RESULT,RESULT_STATUS_NAME,RANGE,MIN_RESULT_UNIT,
                    SPEC_SENDER_NAME,SPEC_CONFIRMER_NAME,PERFORMED_DOCTOR_NAME,OUTP_NO,VISIT_ID,APPLY_DEPT_NAME,
                    TO_CHAR(SAMPLE_TIME,'yyyy-mm-dd hh24:mi:ss') AS SAMPLE_TIME,
                    TO_CHAR(PRINT_TIME,'yyyy-mm-dd hh24:mi:ss') AS PRINT_TIME,
                    TO_CHAR(REPORT_TIME,'yyyy-mm-dd hh24:mi:ss') AS REPORT_TIME
                    FROM DBO.HDR_LAB_REPORT_BTF
                    WHERE VISIT_ID = '" . $zyh . "'OR OUTP_NO = '" . $zyh . "'";


        $result = oci_parse($con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }

        //        \App\Model\V_JMGS_TESTRESULT::query()->where('ZYH','=',$zyh)->delete();
        foreach ($data as $item) {
            $zyh = 0;
            if ($item['VISIT_TYPE_CODE'] == '01' || $item['VISIT_TYPE_CODE'] == '03') {
                $zyh = $item['OUTP_NO'];
            } elseif ($item['VISIT_TYPE_CODE'] == '02') {
                $zyh = $item['VISIT_ID'];
            }
            $VISIT_TYPE_CODE = '';
            if (!empty($item['VISIT_TYPE_CODE'])) {
                $VISIT_TYPE_CODE = $item['VISIT_TYPE_CODE'];
            } elseif ($item['APPLY_DEPT_NAME'] == '体检中心') {
                $VISIT_TYPE_CODE = 100;
            }
            $testResultData = [
                'ZYH' => $zyh,                                    //唯一标识
                'TXM' => $item['SAMPLE_NO'] ?: '',                //样本编号、条码
                'NO' => $item['REPORT_NO'] ?: '',                //报告号
                'XM' => $item['PERSON_NAME'] ? desensitize($item['PERSON_NAME'], 1, 1, '*') : '', //姓名
                'YBLX' => $item['SPECIMEN_NAME'] ?: '',            //样本类型
                //                        'YBZT' => $item['RESULT_STATUS_NAME'] ?: '',    //样本状态
                'AAA28' => $item['INP_NO'] ?: '',                   //住院号
                'LCZD' => $item['LAB_DIAGNOSIS_NAME'] ?: '',       //临床诊断
                'YW' => $item['LAB_ITEM_ENAME'] ?: '',           //英文（检验项目）
                'JYXM' => $item['LAB_ITEM_NAME'] ?: '',            //检验项目
                'JG' => $item['LAB_YM_RESULT'] ?: '',            //结果
                'TS' => $item['RESULT_STATUS_NAME'] ?: '',       //提示
                'CKFW' => $item['RANGE'] ?: '',                    //参考范围
                'DW' => $item['MIN_RESULT_UNIT'] ?: '',          //单位
                'SJYS' => $item['SPEC_SENDER_NAME'] ?: '',         //送检医生
                'JYY' => $item['SPEC_CONFIRMER_NAME'] ?: '',      //检验员
                'SHY' => $item['PERFORMED_DOCTOR_NAME'] ?: '',    //审核员
                'CJSJ' => $item['SAMPLE_TIME'] ?: '',              //采集时间
                'JSSJ' => $item['PRINT_TIME'] ?: '',               //接收时间
                'BGSJ' => $item['REPORT_TIME'] ?: '',              //报告时间
                'REPORT_PK' => $item['REPORT_PK'],
                'VISIT_TYPE_CODE' => $VISIT_TYPE_CODE
            ];

            \App\Model\V_JMGS_TESTRESULT::query()->updateOrInsert(['REPORT_PK' => $testResultData['REPORT_PK']], $testResultData);
        }
    }

    public function V_JMGS_YMresult($zyh)
    {
        //进行数据库连接
        $username = "BTF";
        $password = "BTF";
        $connection = "192.168.10.20";
        $port = 1521;
        $tns = "xhlis";
        $con = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $sql = "SELECT OUTP_NO,VISIT_ID,VISIT_TYPE_CODE,SAMPLE_NO,PERSON_NAME,LAB_ITEM_ENAME,OUT_PATIENT_ID,
                    IN_PATIENT_ID,RESULT_STATUS_NAME,MICRO_ITEM_NAME,LAB_YM_NAME,LAB_YM_RESULT,SOURCE_PK,
                    TO_CHAR(REPORT_TIME,'yyyy-mm-dd hh24:mi:ss') AS BGSJ
                    FROM DBO.HDR_LAB_REPORT_DETAIL_MICRO
                    WHERE VISIT_ID = '" . $zyh . "'OR OUTP_NO = '" . $zyh . "'";

        $result = oci_parse($con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }
        \App\Model\V_JMGS_YMresult::query()->where('ZYH', '=', $zyh)->delete();
        $params = [
            'index' => 'v_jmgs_ymresult_2023',
            'body' => [
                'query' => [
                    'term' => [
                        'ZYH' => $zyh
                    ]
                ]
            ]
        ];
        app('es')->deleteByQuery($params);
        foreach ($data as $item) {
            $zyh = 0;
            $AAA28 = '';
            if ($item['VISIT_TYPE_CODE'] == '01') {
                $zyh = $item['OUTP_NO'];
                $AAA28 = $item['OUT_PATIENT_ID'];
            } elseif ($item['VISIT_TYPE_CODE'] == '02') {
                $zyh = $item['VISIT_ID'];
                $AAA28 = $item['IN_PATIENT_ID'];
            }
            $ymResultData = [
                'ZYH' => $zyh,                                    //唯一标识
                'TXM' => $item['SAMPLE_NO'] ?: '',                //样本编号、条码
                'NO' => $item['SAMPLE_NO'] ?: '',                //报告号
                'XM' => $item['PERSON_NAME'] ? desensitize($item['PERSON_NAME'], 1, 1, '*') : '', //姓名
                'YBLX' => $item['LAB_ITEM_ENAME'] ?: '',            //样本类型
                'AAA28' => $AAA28,                                  //住院号
                'PYJG' => $item['RESULT_STATUS_NAME'],             //细菌培养结果
                'XJMC' => $item['MICRO_ITEM_NAME'] ?: '',          //细菌名称
                'XJJL' => $item['LAB_YM_RESULT'] ?: '',            //细菌数量
                'YMMC' => $item['LAB_YM_NAME'] ?: '',              //药敏名称
                'YMJG' => $item['LAB_YM_RESULT'] ?: '',            //药敏结果
                'YMBW' => $item['LAB_ITEM_ENAME'] ?: '',           //药敏部位
                'BGSJ' => $item['BGSJ'] ?: '',                     //报告时间
                'SOURCE_PK' => $item['SOURCE_PK']
            ];

            \App\Model\V_JMGS_YMresult::query()->updateOrInsert(['SOURCE_PK' => $ymResultData['SOURCE_PK']], $ymResultData);
        }
    }


    public function BA_MR_CLASS_NUMBER($aaa28)
    {
        $con = oci_connect('zzj', 'zzj', '172.16.2.177:1433/CEMS', "UTF8");
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }

        $sql = "SELECT patient_id,visit_id,MrClass,Quantity,serial_no FROM BA_MR_CLASS_NUMBER where patient_id='{$aaa28}'";
        $result = oci_parse($con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            exit;
        }
        \App\Model\BA_MR_CLASS_NUMBER::query()->where('patient_id', '=', $aaa28)->delete();
        $insertData = [];
        foreach ($data as $item) {
            $insertData[] = [
                'patient_id' => $item['patient_id'],
                'visit_id' => $item['visit_id'],
                'MrClass' => $item['MrClass'],
                'Quantity' => $item['Quantity'],
                'serial_no' => $item['serial_no'],
            ];
        }
        \App\Model\BA_MR_CLASS_NUMBER::query()->insert($insertData);
    }
}
