<?php

namespace App\Services\MysqlDataSync\ningxia;

use App\Model\Icu;
use App\Model\Yzb;
use Carbon\Carbon;
use App\Model\PACS;
use App\Model\SSSQ;
use App\Model\Staff;
use App\Model\ZY_SS;
use App\Model\Mblb42;
use App\Model\Mblb44;
use App\Model\Mblb82;
use App\Model\Mblb304;
use App\Model\MS_BRDA;
use App\Model\OmrBlsy;
use App\Model\SM_SSAP;
use App\Model\ZY_BRRY;
use App\Model\OMR_BL01;
use App\Model\Mblb30304;
use App\Model\Department;
use App\Model\PatientAdd;
use App\Model\DataSyncLog;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLSY;
use App\Model\EMR_BL_BLXG;
use App\Model\FeeDetailed;
use App\Model\PatientInfo;
use App\Model\RuleWordMap;
use App\Model\MainDiagnosis;
use App\Model\MainOperation;
use App\Model\OtherDiagnosis;
use App\Model\PatientCostInfo;
use App\Model\PatientWorkInfo;
use App\Model\V_JMGS_YMresult;
use App\Services\RadioService;
use App\Model\DataxSyncSetting;
use App\Model\PatientOtherInfo;
use App\Services\EsSaveService;
use Illuminate\Console\Command;
use App\Model\PatientDoctorInfo;
use App\Model\QualitySendMsgLog;
use App\Model\V_JMGS_TESTRESULT;
use App\Model\PatientAddressInfo;
use App\Model\PatientMedicalInfo;
use App\Model\SecondaryOperation;
use App\Model\PatientContactsInfo;
use App\Model\PatientHospitalInfo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\BlDataFormatService;
use App\Services\IihinterfaceService;
use App\Console\Commands\ShizhongDataSync;
use App\Console\Commands\DataFormat\OMR_BL01 as OMR_BL01_Format;
use App\Model\WJZ;

class HomeData
{

    public static $con;
    private $secret_key = '5F384D4AB3CD18C984BCFB1F45D23684';

    /**
     * MBLB && BLLB 对照表
     * @var \string[][]
     */
    private $groupArray = [
        '1001M310000000001BDS' => ['MBLB' => '2000001', 'BLLB' => '2000001'],
        '1001M310000000001BJU' => ['MBLB' => '292', 'BLLB' => '292'],
        '1001M310000000001BJV' => ['MBLB' => '292', 'BLLB' => '292'],
        '1001M310000000001BL4' => ['MBLB' => '1', 'BLLB' => '1'],
        '1001M310000000001BL5' => ['MBLB' => '288', 'BLLB' => '288'],
        '1001M310000000001BJW' => ['MBLB' => '20', 'BLLB' => '18'],
        '1001M310000000001BJX' => ['MBLB' => '21', 'BLLB' => '18'],
        '1001M310000000001BK6' => ['MBLB' => '295', 'BLLB' => '294'],
        '1001M310000000001BK7' => ['MBLB' => '50', 'BLLB' => '294'],
        '1001M310000000001BKE' => ['MBLB' => '50', 'BLLB' => '294'],
        '1001M310000000001BK8' => ['MBLB' => '296', 'BLLB' => '294'],
        '1001M310000000001BKD' => ['MBLB' => '296', 'BLLB' => '294'],
        '1001M310000000001BK5' => ['MBLB' => '296', 'BLLB' => '294'],
        '1001M310000000001BKC' => ['MBLB' => '45', 'BLLB' => '294'],
        '1001M310000000001BKI' => ['MBLB' => '511', 'BLLB' => '294'],
        '1001M310000000001BKG' => ['MBLB' => '511', 'BLLB' => '294'],
        '1001M310000000001BKM' => ['MBLB' => '27', 'BLLB' => '294'],
        '1001M310000000001BKK' => ['MBLB' => '30', 'BLLB' => '294'],
        '1001M310000000001BKJ' => ['MBLB' => '30', 'BLLB' => '294'],
        '1001M310000000001BKL' => ['MBLB' => '26', 'BLLB' => '294'],
        '1001M310000000001BKU' => ['MBLB' => '515', 'BLLB' => '294'],
        '1001M310000000001BK9' => ['MBLB' => '54', 'BLLB' => '294'],
        '1001M310000000001BKO' => ['MBLB' => '54', 'BLLB' => '294'],
        '1001M310000000001BKP' => ['MBLB' => '42', 'BLLB' => '294'],
        '1001M310000000001BKV' => ['MBLB' => '82', 'BLLB' => '294'],
        '1001M310000000001BKS' => ['MBLB' => '32', 'BLLB' => '294'],
        '1001M310000000001BKR' => ['MBLB' => '32', 'BLLB' => '294'],
        '1001M310000000001BL6' => ['MBLB' => '4302', 'BLLB' => '43'],
        '1001M310000000001BKA' => ['MBLB' => '44', 'BLLB' => '43'],
        '1001M310000000001BKY' => ['MBLB' => '306', 'BLLB' => '303'],
        '1001M310000000001BL1' => ['MBLB' => '40', 'BLLB' => '303'],
        '1001M310000000001BL2' => ['MBLB' => '30303', 'BLLB' => '303'],
        '1001M310000000001BL0' => ['MBLB' => '30375', 'BLLB' => '303'],
        '1001M310000000001BKW' => ['MBLB' => '304', 'BLLB' => '294'],
        '1001M310000000001BKN' => ['MBLB' => '131', 'BLLB' => '294'],
        '1001M310000000001BL8' => ['MBLB' => '8', 'BLLB' => '329'],
        '1001M310000000001BL9' => ['MBLB' => '77', 'BLLB' => '329'],
        '1001M310000000001BLA' => ['MBLB' => '59', 'BLLB' => '329'],
        '1001M310000000001BLD' => ['MBLB' => '60', 'BLLB' => '329'],
        '1001M310000000001BKX' => ['MBLB' => '32977', 'BLLB' => '329'],
        '1001M310000000001BLB' => ['MBLB' => '200018501', 'BLLB' => '2000185'],

        //第二次新增
        '1001M310000000001BLC' => ['MBLB' => '62', 'BLLB' => '329'],
        '1001M310000000001BM7' => ['MBLB' => '7901', 'BLLB' => '79'],
        '1001M310000000006MO8' => ['MBLB' => '7904', 'BLLB' => '79'],
        '1001M310000000001BM8' => ['MBLB' => '3402', 'BLLB' => '34'],
        '1001M310000000001BM9' => ['MBLB' => '10409', 'BLLB' => '104'],
    ];

    public function getConnect()
    {
        if (self::$con) {
            return self::$con;
        }
        /* $username = env('ORACLE_USERNAME', '');
        $password = env('ORACLE_PASSWORD', '');
        $connection = env('ORACLE_HOST', '');
        $port = env('ORACLE_PORT', '');
        $tns = env('ORACLE_TNS', ''); */
        $username = "nhzk";
        $password = "nhzk";
        $connection = "10.32.92.86";
        $port = "1521";
        $tns = "IIH";
        $con = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
        self::$con = $con;
    }



    public static $mysqlcon;
    /**
     * 建立MySQL数据库连接
     * @return \PDO|null
     */
    public function getMysqlConnect()
    {
        if (self::$mysqlcon) {
            return self::$mysqlcon;
        }
        $servername = '10.32.82.93';
        $port = '9030';
        $username = 'yw_bazk';
        $password = 'Yw#bazk123';
        $dbname = 'ods';

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_EMULATE_PREPARES => true,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ];

        try {
            $dsn = "mysql:host={$servername};port={$port};dbname={$dbname};charset=utf8mb4";
            $conn = new \PDO($dsn, $username, $password, $options);
            self::$mysqlcon = $conn;
        } catch (\PDOException $e) {
            Log::error('MySQL连接失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 执行查询视图的sql语句
     */
    public static function querySql($sql = '')
    {
        if (empty($sql)) {
            return [];
        }

        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;
    }

    public static function queryMysqlSql($sql = '')
    {
        $result = self::$mysqlcon->query($sql);
        $data = [];
        // 获取所有结果
        $results = $result->fetchAll();

        // 或逐行获取
        foreach ($results as $row) {
            $data[] = $row;
        }
        return $data;
    }

    /**
     * @param string $startTime
     * 清洗patient_info表中的是否编目字段
     */
    public function isCATA($startTime = '')
    {
        $this->getConnect();
        $timeIndex = Carbon::now()->subDays(365)->startOfDay()->timestamp;
        if (!empty($startTime)) {
            $startTime = strtotime($startTime);
        } else {
            $startTime = $timeIndex;
        }

        while (true) {

            $startTimeStr = date("Y-m-d", $startTime);
            echo '时间：' . $startTimeStr . PHP_EOL;
            $startTime += 86400;
            $sql = "SELECT ID_ENT AS MED_REC_ID FROM IIH.EN_ENT WHERE DT_END BETWEEN '" . $startTimeStr . " 00:00:00' AND '" . $startTimeStr . " 23:59:59'";

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
            if (empty($result)) {
                break;
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
        DataSyncLog::addData(['zyh' => $zyh, 'content' => 'getBlData开始']);
        if (!self::$con) {
            $this->getConnect();
        }
        DataSyncLog::addData(['zyh' => $zyh, 'content' => '数据库链接完成']);
        if ($zyh) {
            $szds = new ShizhongDataSync();
            $brryData = $szds->getBRRYData($zyh, 0, $startTime);

            foreach ($brryData as $item) {
                $update = [
                    'AAA28' => !empty($item['AAA28']) ? $item['AAA28'] : "",
                    'AAB01' => !empty($item['AAB01']) ? $item['AAB01'] : "",
                    'AAC01' => !empty($item['AAC01']) ? $item['AAC01'] : "",
                    'BRKS' => !empty($item['BRKS']) ? $item['BRKS'] : "",
                    'BRXM' => !empty($item['BRXM']) ? $item['BRXM'] : "",
                    'XB' => !empty($item['XB']) ? $item['XB'] : "",
                    'NL' => !empty($item['NL']) ? $item['NL'] : "",
                    'ZYCS' => !empty($item['ZYCS']) ? $item['ZYCS'] : "",
                ];
                if ($item['CH']) {
                    $update["CH"] = $item['CH'];
                }
                \App\Model\ZY_BRRY::query()->updateOrInsert(['ZYH' => $item['ZYH']], $update);
                $inhospital = 2;
                if (empty($update['AAC01']) || $update['AAC01'] == '') {
                    $inhospital = 1;
                } else {
                    $inhospital = 2;
                }
                $insertData = [
                    'MED_REC_ID' => $item['ZYH'] ?? "",
                    'AAA28' => $update['AAA28'] ?? "",
                    'AAA29' => $update['ZYCS'] ?? 0,
                    'AAB01' => $update['AAB01'] ?? "",
                    'AAC01' => $update['AAC01'] ?? "",
                    'in_hospital' => $inhospital,
                    'AAA01' => $update['BRXM'] ? desensitize($update['BRXM'], 1, 1, '*') : '',        // 患者姓名
                ];
                \App\Model\PatientInfo::query()->updateOrInsert(['MED_REC_ID' => $item['ZYH']], $insertData);
                DataSyncLog::addData(['zyh' => $zyh, 'content' => 'ZY_BRRY处理完成']);
            }
        }

        // 医嘱
        if (empty($type) || in_array('yzb', $type)) {
            Log::info('qualityHandleV2 yzb 开始' . date('Y-m-d H:i:s'), ['zyh' => $zyh]);
            $yzbData = $this->getYzb($zyh);
            if (!empty($yzbData)) {
                $this->addYzb($yzbData);
            }
            Log::info('qualityHandleV2 yzb 结束' . date('Y-m-d H:i:s'), ['zyh' => $zyh]);
        }

        // 费用
        // if (empty($type) || in_array('fee_detailed', $type)) {
        // Log::info('qualityHandleV2 fee 开始' . date('Y-m-d H:i:s'), ['zyh' => $zyh]);
        // $this->getFyData($zyh);

        // Log::info('qualityHandleV2 fee 结束' . date('Y-m-d H:i:s'), ['zyh' => $zyh]);
        // }

        // 医生签名
        if (empty($type) || in_array('bl01', $type)) {
            $this->addBLSY($zyh, $blbh);
        }
        // 首麻
        $this->SM_SSAP($zyh);
        // 危急值
        $this->wjz($zyh);

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
        $this->getMysqlConnect();

        $brry = ZY_BRRY::query()->where("ZYH", $zyh)->first();
        if (!$brry) {
            return false;
        }
        $zyhm = $brry->AAA28;

        
        $user = $this->queryMysqlSql('SELECT Id,RealName,Account FROM AIMS_V2_SysUser');
        $user = array_column($user, NULL, 'Id');

        $sql = "SELECT a.id as odsid,GROUP_CONCAT(z.ProjectName) as ZTX,b.InHospitalNo as ZYHM,b.VisitId as ZYCS,g.icdcode AS ICD9_SSCZBM,g.NAME AS ICD9_SSCZMC,a.OperationBeginTime AS SSRQ,a.OperationEndTime AS JSRQ,a.OperationDoctor as MZSZGH,c.Account as SZDM,c.RealName as SZ,a.Assistant1 as YZDM,i.RealName as YZ,a.TourNurse as XHHSGH,a.InRoomTime AS RSSJ,a.AnesthesiaBeginTime as MZKSSJ,a.AnesthesiaEndTime as MZJSSJ,a.OutRoomTime AS CSSJ,a.AnaesthesiaMethodId AS MZFSDM,d.name AS MZFS,e.VALUE,a.AnesthesiaEffect as SMMZXG,h.ScheduleNo as SQDH,h.ExecDepartmentId AS SSKS,a.Assistant2 as RZDM,j.RealName as RZ,SUBSTRING_INDEX(a.AnesthesiaDoctor,',',1) as MZYS,j1.RealName AS MZYS_MC,CASE WHEN h.OperationPriorityId = '600206680621125' THEN '日间手术' WHEN h.OperationPriorityId = '600206477733957' THEN '择期手术' WHEN h.OperationPriorityId = '600206586167365' THEN '急诊手术' ELSE '' END AS SSLX,sug.FG_NOPLAN as FJHSS FROM AIMS_V2_BizOperationRecord a LEFT JOIN AIMS_V2_BizPatients b ON b.id=a.PatientId LEFT JOIN AIMS_V2_SysUser c ON c.id=a.OperationDoctor LEFT JOIN AIMS_V2_SysAnaesthesiaMethod d on d.id=a.AnaesthesiaMethodId LEFT JOIN AIMS_V2_SysDictData e on e.id=a.AnesthesiaEffect LEFT JOIN AIMS_V2_BizOperationPreSuffix f on f.OperationRecordId=a.Id LEFT JOIN AIMS_V2_SysOperations g on g.Id=f.OperationId LEFT JOIN AIMS_V2_BizOperationApply AS h on h.id=a.OperationApplyId LEFT JOIN AIMS_V2_SysUser i on a.Assistant1=i.id LEFT JOIN AIMS_V2_SysUser j on a.Assistant2=j.id LEFT JOIN AIMS_V2_SysUser j1 on SUBSTRING_INDEX(a.AnesthesiaDoctor,',',1)=j1.id LEFT JOIN HIS_V1_CI_AP_SUG AS sug on h.ScheduleNo=sug.NO_APPLYFORM LEFT JOIN AIMS_V2_BizBloodTransfusionRegistrationRecord z ON a.id=z.OperationRecordId WHERE a.OperationState = 1 and b.InHospitalNo='{$zyhm}' GROUP BY a.id,b.InHospitalNo,b.VisitId,g.icdcode,g.NAME,a.OperationBeginTime,a.OperationEndTime,a.OperationDoctor,c.Account,c.RealName,a.Assistant1,i.RealName,a.TourNurse,a.InRoomTime,a.AnesthesiaBeginTime,a.AnesthesiaEndTime,a.OutRoomTime,a.AnaesthesiaMethodId,d.name,e.VALUE,a.AnesthesiaEffect,h.ScheduleNo,h.ExecDepartmentId,a.Assistant2,j.RealName,a.AnesthesiaDoctor,j1.RealName,h.OperationPriorityId,sug.FG_NOPLAN";
        $data = $this->queryMysqlSql($sql);
        SM_SSAP::query()->where("ZYHM", $zyhm)->delete();
        foreach ($data as $item) {
            // $item["RZDM"] = $item["RZDM"] == -1 ? "" : $item["RZDM"];
            // $item["RZ"] = !empty($user[$item["RZDM"]]) ? $user[$item["RZDM"]]['RealName'] : "";
            // $item["YZ"] = !empty($user[$item["YZDM"]]) ? $user[$item["YZDM"]]['RealName'] : "";
            // if (strpos($item["SMQXHSGH"], ",") === true) {
            //     $SMQXHSGH = explode(",", $item["SMQXHSGH"]);
            //     $item["SMQXHSGH"] = $SMQXHSGH[0];
            // }
            // if (strpos($item["MZYS"], ",") === true) {
            //     $MZYS = explode(",", $item["MZYS"]);
            //     $item["MZYS"] = $MZYS[0];
            //     $item["MZYS_MC"] = !empty($user[$item["MZYS"]]) ? $user[$item["MZYS"]]['RealName'] : "";
            // }
            SM_SSAP::query()->insert($item);
        }
        // 清理住院号
        $this->cleanSSAPZYH($zyhm);
    }

    /**
     * 危急值
     */
    public function wjz($zyh = "")
    {
        $this->getMysqlConnect();

        // 使用直接查询获取数据
        $sql = " SELECT HIS_V1_MP_CRIS_VAL.SV AS XGSJ, HIS_V1_MP_CRIS_VAL.ID_CRIS_VAL AS JLXH, HIS_V1_MP_CRIS_VAL.ID_ENT AS ZYH, HIS_V1_MP_CRIS_VAL.DT_REPORT AS WJZSJ, HIS_V1_MP_CRIS_VAL.DT_NOTIC_DOC AS WJZTSSJ, HIS_V1_MP_CRIS_VAL.CODE_ENTP AS WJZLX, CONCAT(HIS_V1_MP_CRIS_VAL_LIS.NAME_SRV, HIS_V1_MP_CRIS_VAL_LIS.VAL) AS WJZNR, HIS_V1_EN_ENT_IP.CODE_AMR_IP AS AAA28, HIS_V1_EN_ENT_IP.TIMES_IP AS ZYCS FROM HIS_V1_MP_CRIS_VAL LEFT JOIN ods.HIS_V1_MP_CRIS_VAL_LIS ON ods.HIS_V1_MP_CRIS_VAL.ID_CRIS_VAL=ods.HIS_V1_MP_CRIS_VAL_LIS.ID_CRIS_VAL LEFT JOIN ods.HIS_V1_EN_ENT_IP ON ods.HIS_V1_MP_CRIS_VAL.ID_ENT=ods.HIS_V1_EN_ENT_IP.ID_ENT where HIS_V1_MP_CRIS_VAL.ID_ENT='{$zyh}'";

        $data = $this->queryMysqlSql($sql);
        WJZ::query()->where("ZYH", $zyh)->delete();
        foreach ($data as $item) {
            WJZ::query()->Insert($item);
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
        $this->getConnect();
        DataSyncLog::addData(['zyh' => $ZYH, 'content' => '费用明细同步开始']);
        // 查询费用数据
        // ,FYGB,SYFYGB,ZJE
        //$sql = "SELECT ZYH as AAA28,FYXH,FYMC,ZFJE,to_char(JFRQ,'yyyy-mm-dd hh24:mi:ss') as JFRQ,FYSL,FYDJ,FYKS FROM PORTAL_HIS.V_ZY_FYMX WHERE ZYH=" . $ZYH;
        $sql = DataxSyncSetting::getByNameSql('fee_detailed', 0);
        $sql = $sql . " WHERE BL_CG_IP.ID_ENT= '" . $ZYH . "'";
        $sql = $sql . " AND TO_DATE(BL_CG_IP.DT_ST,'YYYY-MM-DD HH24:MI:SS') >= TRUNC(SYSDATE) - 3";
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        DataSyncLog::addData(['zyh' => $ZYH, 'content' => '本次同步费用明细数量', 'data_nums' => count($result)]);
        if (empty($result)) {
            DataSyncLog::addData(['zyh' => $ZYH, 'content' => '费用明细同步结束']);
            return false;
        }
        $insertData = [];
        foreach ($result as $val) {
            $insertData[] = [
                'AAA28' => $val['AAA28'], //zyh
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
                'TKBZ' => !empty($val['TKBZ']) ? ($val['TKBZ'] == -1 ? 1 : 0) : 0,
                'JLXH' => $val['JLXH'] ?? ''
            ];
        }
        //FeeDetailed::query()->where('AAA28', '=', $ZYH)->delete();

        if (!empty($insertData)) {
            foreach ($insertData as $item) {
                FeeDetailed::query()->updateOrInsert(['FYXH' => $item['FYXH']], $item);
            }
        }
        DataSyncLog::addData(['zyh' => $ZYH, 'content' => '费用明细同步结束']);
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
        DataSyncLog::addData(['zyh' => $ZYH, 'content' => '医嘱同步开始']);
        //$sql = "SELECT A.*,to_char(TZSJ,'yyyy-mm-dd hh24:mi:ss') as TJ,to_char(XZJDSJ,'yyyy-mm-dd hh24:mi:ss') as XJ,to_char(TZQRSJ,'yyyy-mm-dd hh24:mi:ss') as TZJ,to_char(APSJ,'yyyy-mm-dd hh24:mi:ss') as AJ,to_char(KZSJ,'yyyy-mm-dd hh24:mi:ss') as KJ FROM PORTAL_HIS.BTF_EMR_YZB A WHERE ZYH=" . $ZYH;
        $sql = DataxSyncSetting::getByNameSql('yzb', 0);
        $sql = $sql . " WHERE CI_ORDER.FG_CANC='N' AND CI_ORDER.ID_EN= '" . $ZYH . "'";
        $sql = $sql . " AND TO_DATE(CI_ORDER.DT_ENTRY,'YYYY-MM-DD HH24:MI:SS') >= TRUNC(SYSDATE) - 3";
        //Log::info('qualityHandleV2 yzb sql', ['sql' => $sql, 'zyh' => $ZYH]);
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        DataSyncLog::addData(['zyh' => $ZYH, 'content' => '医嘱同步数量：' . count($result), 'sync_content_msg' => $sql]);
        return $result;
    }

    public function addYzb($data)
    {
        DataSyncLog::addData(['zyh' => $data[0]['ZYH'], 'content' => '医嘱数据插入开始']);
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
                    'KZSJ' => $val['KZSJ'] ?? '',
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
                    'TZSJ' => $val['TZSJ'] ?? '',
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
        EsSaveService::yzb($zyh);
        DataSyncLog::addData(['zyh' => $zyh, 'content' => '医嘱数据插入结束']);
    }

    public function formatBL01BLMC306($bl01 = [])
    {

        $HJNR = $bl01['HJNR'];
        $HJNR = str_replace("：", ":", $HJNR);
        preg_match("/记录日期:(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", $HJNR, $timeMatches);
        $timePrefix = !empty($timeMatches[1]) ? $timeMatches[1] : '';
        // 更新blmc
        if (!empty($timePrefix)) {
            EMR_BL_BL01::query()->where('BLBH', $bl01['BLBH'])->update(['BLMC' => $timePrefix . ' 手术记录 {' . $bl01["BLMC"] . '}']);
        }
    }

    public function formatBL01BLMC82($bl01 = [])
    {

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

        $pregTime = '(\d{4}\/\d{2}\/\d{2}\s+\d{2}:\d{2}(?::\d{2})?|\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(?::\d{2})?|\d{4}-\d{2}-\d{2}\s+\d{2}时\d{2}分(?:\d{2}秒)?|\d{4}年\d{2}月\d{2}日\s+\d{2}时\d{2}分(?:\d{2}秒)?|\d{4}年\d{2}月\d{2}日\s+\d{2}:\d{2}|\d{4}年\d{2}月\d{2}日\s+\d{2}:\d{2}:\d{2})';

        //$HJNR = $bl01['HJNR'];
        $blmc = $bl01['BLMC1'];
        if (!empty($bl01['HJNR'])) {
            // $bl01['HJNR'] = str_replace(['年', '月', '日', '时', '分', '秒'], ['-', '-', '', ':', ':', ''], $bl01['HJNR']);
            // 从HJNR中提取时间  2024-05-14 09:00:00
            preg_match("/^$pregTime/", $bl01['HJNR'], $timeMatches);
            //增加格式匹配 '时间：2024-05-1411:33:12 姓名：性别：女
            preg_match("/^时间：$pregTime/", $bl01['HJNR'], $timeMatches2);
            //增加格式匹配 '2024-05-14 11:33'不包含秒
            // preg_match("/^$pregTime/", $bl01['HJNR'], $timeMatches3);
            //增加格式匹配 '{2024-08-01 19:20}'花括号包围的时间格式
            preg_match("/^\{$pregTime\}/", $bl01['HJNR'], $timeMatches4);
            //增加格式匹配 '记录时间：2024-07-31 10:12'
            preg_match("/^记录时间：$pregTime/", $bl01['HJNR'], $timeMatches5);
            //增加格式匹配 '讨论日期：2024-07-31 10:12'不一定在开头
            preg_match("/讨论日期：$pregTime/", $bl01['HJNR'], $timeMatches6);
            //新增匹配格式 2024.07.01 10:55:20
            // preg_match("/^$pregTime/", $bl01['HJNR'], $timeMatches7);
            //新增匹配格式 2024.07.01 10:55
            // preg_match("/^$pregTime/", $bl01['HJNR'], $timeMatches8);
            //新增匹配格式 记录日期：2024-07-31 10:12
            preg_match("/^记录日期：$pregTime/", $bl01['HJNR'], $timeMatches9);

            // 讨论时间
            preg_match("/多学科讨论时间：$pregTime/", $bl01['HJNR'], $timeMatches10);

            $timePrefix = '';
            if (!empty($timeMatches10[1])) {
                $timePrefix = $timeMatches10[1];
            } elseif (!empty($timeMatches[1])) {
                $timePrefix = $timeMatches[1];
            } else if (!empty($timeMatches2[1])) {
                $timePrefix = $timeMatches2[1];
            } else if (!empty($timeMatches3[1])) {
                $timePrefix = $timeMatches3[1];
            } else if (!empty($timeMatches4[1])) {
                $timePrefix = $timeMatches4[1];
            } else if (!empty($timeMatches5[1])) {
                $timePrefix = $timeMatches5[1];
            } else if (!empty($timeMatches6[1])) {
                $timePrefix = $timeMatches6[1];
            } else if (!empty($timeMatches9[1])) {
                $timePrefix = $timeMatches9[1];
            }

            if ($timePrefix != '') {
                // 检查BLMC中是否已包含重复的时间格式
                // 先检查是否包含完全相同的时间格式
                $pattern = "/(\d{4}-\d{2}-\d{2} \d{2}:\d{2})([ ]+)\\1/";
                if (preg_match($pattern, $blmc)) {
                    // 如果存在重复的时间格式，去除重复部分
                    $blmc = preg_replace($pattern, "$1", $blmc);
                }

                //判断blmc是否包含时间格式,如果已经包含时间格式,跟timePrefix对比,如果相同就不改变,如果不同就替换
                $newblmc = $blmc;


                //如果blmc中包含2025.01.02 08:09这种格式，先删除
                $blmc = preg_replace("/(\d{4}\.\d{2}\.\d{2} \d{2}:\d{2})/", "", $blmc);
                //如果原本的blmc中包含01.02 08:09这种格式，先删除
                $blmc = preg_replace("/(\d{2}\.\d{2} \d{2}:\d{2})/", "", $blmc);

                //如果blmc中包含2025-01-02 08:09这种格式，先删除
                $blmc = preg_replace("/(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", "", $blmc);

                //判断blmc是否包含时间格式
                preg_match("/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/", $blmc, $timeMatches1);
                //最后删除所有空格
                $blmc = preg_replace("/\s+/", "", $blmc);
                // 增加对不带秒的时间格式的检查
                if (empty($timeMatches1[1])) {
                    preg_match("/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", $blmc, $timeMatches1);
                }

                if (!empty($timeMatches1[1])) {
                    if ($timeMatches1[1] != $timePrefix) {
                        //替换blmc中的时间格式
                        $newblmc = str_replace($timeMatches1[1], $timePrefix, $blmc);
                    }
                } else {
                    $newblmc = $timePrefix . ' ' . $blmc;
                }
                $blService = new BlDataFormatService();
                $timePrefix = $blService->formatTime($timePrefix);
                EMR_BL_BL01::query()->where('BLBH', $bl01['BLBH'])->update(['BLMC' => $newblmc, 'ZXSJ' => $timePrefix, 'YWSJ' => $timePrefix]);
            } else {
                //取不到使用cjsj
                $timePrefix = date('Y-m-d H:i:s', strtotime($bl01['CJSJ']));
                $newblmc = $timePrefix . ' ' . $blmc;
                EMR_BL_BL01::query()->where('BLBH', $bl01['BLBH'])->update(['BLMC' => $newblmc, 'ZXSJ' => $timePrefix, 'YWSJ' => $timePrefix]);
            }
        } else {
            EMR_BL_BL01::query()->where('BLBH', $bl01['BLBH'])->update(['BLMC' => $blmc]);
        }
    }

    /**
     * @param string $zyh
     * @param string $blbh
     * @return bool
     * 同步病程记录相关数据
     */
    public function addBLSY($zyh = "", $blbh3 = "")
    {
        DataSyncLog::addData(['zyh' => $zyh, 'content' => '4、数据病程记录开始']);
        //echo "开始addBLSY：" . date("Y-m-d H:i:s") . PHP_EOL;
        $bldf = new BlDataFormatService();
        //$sql = "SELECT BLBH, ZYH, BLLB, BLMC, MBLB, ZXSJ, CJSJ, WCSJ, SXYS, BRKS, CJKS, BLZT, JLXH, BLBH, XGGH, XGSJ, HJNR FROM IIH.VZK_JMGS_BASY_QBL WHERE ZYH = '{$zyh}'";
        $sql = "SELECT NVL(IIH.CI_MR.ID_MR, '') AS BLBH, NVL(IIH.CI_MR.ID_PAT, '') AS BLBH3, NVL(IIH.CI_MR.ID_ENT, '') AS JZHM, NVL( EN_ENT.DT_ACPT, '' ) AS AAB01,NVL( EN_ENT.DT_END, '' ) AS AAC01, NVL(IIH.CI_MR.NAME, '') AS BLMC, NVL(IIH.CI_MR.NAME, '') AS BLMC1, NVL(IIH.CI_MR.ID_MRTP, '') AS MBLB3, NVL(IIH.CI_MR.VER_MRTPL, '') AS MBBH, NVL(IIH.CI_MR.CREATEDTIME, '') AS CJSJ, NVL(IIH.CI_MR.MODIFIEDTIME, '') AS WCSJ, NVL(IIH.CI_MR.MODIFIEDBY, '') AS SXYS, NVL(IIH.CI_MR.ID_DEP_PAT, '') AS BRKS, NVL(IIH.CI_MR.ID_SUBMIT_DEPT, '') AS CJKS, NVL(CASE FG_COMPLETE WHEN 'Y' THEN 1 WHEN 'N' THEN 2 ELSE 9 END, '') AS BLZT, NVL(IIH.CI_MR.DT_SUBMIT_FIRST, '') AS ZXSJ,NVL(IIH.EN_ENT.ID_ENT, '') AS ZYH_ID FROM IIH.EN_ENT LEFT JOIN IIH.CI_MR ON IIH.EN_ENT.ID_ENT = IIH.CI_MR.ID_ENT LEFT JOIN IIH.CI_MR_FP_PAT ON IIH.EN_ENT.ID_ENT = IIH.CI_MR_FP_PAT.ID_ENT WHERE IIH.EN_ENT.CODE_ENTP = 10  AND EN_ENT.FG_CANC = 'N' AND IIH.CI_MR.ID_MR IS NOT NULL AND IIH.CI_MR.DS = 0 AND IIH.CI_MR.ID_ENT ='{$zyh}'";
        // AND IIH.EN_ENT.ID_ENT ='{$zyh}'
        // AND CONCAT(IIH.CI_MR_FP_PAT.CODE_AMR_IP,IIH.CI_MR_FP_PAT.N_TIMES_INHOSPITAL) ='{$zyh}'
        if (!empty($blbh3)) {
            $sql .= " and IIH.CI_MR.ID_MR='{$blbh3}'";
        }
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        DataSyncLog::addData(['zyh' => $zyh, 'content' => '5、本次同步数据量', 'data_nums' => count($data)]);
        if (empty($data) && !empty($zyh)) {
            DataSyncLog::addData(['zyh' => $zyh, 'content' => '6、未获取到病程记录，清空mysql中的病程记录']);
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
                    DataSyncLog::addData(['zyh' => $zyh, 'content' => '7、删除mysql中的病程记录：' . $blbh['BLBH']]);
                    EsSaveService::deleteBl01ByBLBH($blbh['BLBH']);
                }
            }
        } else {
            EsSaveService::deleteBl01ByBLBH($blbh3);
        }

        $sj = PatientInfo::query()->where('MED_REC_ID', $zyh)->first(['MED_REC_ID', 'AAB01', 'AAC01']);
        $staff = Staff::query()->get()->toArray();
        $staff = array_column($staff, "name", "code");
        //echo "addBLSY数据总条数：" . count($data) . PHP_EOL;
        foreach ($data as $item) {
            DataSyncLog::addData(['zyh' => $zyh, 'content' => '8、同步病程记录：' . ($item['BLBH'] ?? '')]);
            //数据清洗
            if (!empty($item['MBLB3'])) {
                $update_array1 = data_get($this->groupArray, $item['MBLB3'], []);
                $item['BLLB'] = $update_array1['BLLB'] ?? "";
                $item['MBLB'] = $update_array1['MBLB'] ?? "";
                $keyword = RuleWordMap::where('id', 4038)->value('keyword');
                $keyword_array = json_decode($keyword, true);
                $stripos_key = array_keys($keyword_array);
                $update_array = [];
                foreach ($stripos_key as $item1) {
                    if (stripos($item['BLMC'], $item1) !== false) {
                        $item['MBLB'] = $item[$keyword_array[$item1]['key']] . $keyword_array[$item1]['value'];
                        if ($item['BLLB'] == 292) {
                            $item['BLLB'] = $item[$keyword_array[$item1]['key']] . $keyword_array[$item1]['value'];
                        }
                        break;
                    } elseif (stripos($item['BLMC'], "补充诊断") !== false) {
                        $item['BLLB'] = 2920002;
                        $item['MBLB'] = 2920002;
                    }
                }
                if (stripos($item['BLMC'], "报告单") !== false) {
                    $item['BLLB'] = '34';
                    $item['MBLB'] = '3402';
                }
                /*
                if(empty($update_array)) data_set($update_array,'BLLB',0);
                if(empty($update_array)) data_set($update_array,'MBLB',"");
                $item['BLLB'] = $update_array['BLLB'];
                $item['MBLB'] = $update_array['MBLB'];
                */
            }
            //将三院的BLOB类型的数据转化为字符串
            //  $SXYS = array_search($item["SXYS"], $staff);
            $SXYS = $item['SXYS'] ?? "";
            $result = [
                'JZHM' => $item['JZHM'] ?? '',
                'BLBH' => $item['BLBH'] ?? '',
                'BLLB' => $item['BLLB'] ?? '',
                'MBLB' => $item['MBLB'] ?? '',
                'BLMC' => $item['BLMC'] ?? '',
                'BLMC1' => $item['BLMC'] ?? '',
                'AAB01' => $sj->AAB01 ?? '',
                'AAC01' => $sj->AAC01 ?? '',
                'ZXSJ' => $item['ZXSJ'] ?? '',
                'CJSJ' => $item['CJSJ'] ?? '',
                'WCSJ' => $item['WCSJ'] ?? '',
                'first_blsy_time' => $item['ZXSJ'] ?? '',
                'SXYS' => $SXYS ?? '',
                'BRKS' => $item['BRKS'] ?? '',
                'CJKS' => $item['CJKS'] ?? '',
                'BLZT' => $item['BLZT'] ?? '',
            ];
            if (strpos($result['BLMC1'], "术前小结") !== false && strpos($result['BLMC1'], "讨论") === false) {
                $result['MBLB'] = 82;
            }
            if (strpos($result['BLMC1'], "术前讨论") !== false) {
                $result['MBLB'] = 304;
            }
            $temp = [
                //  'JZHM' => $item['JZHM'] ?? '',
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

            // 获取签名
            $blsysql = "SELECT NVL(IIH.CI_MR_SIGN.DS, '') AS DS,NVL(IIH.CI_MR_SIGN.ID_SIGN, '') AS QMLX,NVL(IIH.CI_MR_SIGN.NAME_PAT_SIGN, '') AS NAME_PAT_SIGN,NVL(IIH.CI_MR_SIGN.ID_MRSIGN, '') AS JLXH, NVL(IIH.CI_MR_SIGN.ID_MR, '') AS BLBH, NVL(IIH.CI_MR_SIGN.ID_EMP_SIGN, '') AS SYYS, NVL(IIH.CI_MR_SIGN.DT_SIGN, '') AS SYSJ, NVL(IIH.CI_MR_SIGN.CREATEDTIME, '') AS JLSJ, NVL(IIH.CI_MR_SIGN.MODIFIEDTIME, '') AS updated_at FROM IIH.CI_MR_SIGN WHERE CI_MR_SIGN.ID_MR = '{$item['BLBH']}' ORDER BY CI_MR_SIGN.DT_SIGN ASC"; //按照jlsj时间升序
            $blsydata = self::querySql($blsysql);
            DataSyncLog::addData(['zyh' => $zyh, 'content' => '8、病程记录' . $item['BLBH'] . '签名数据：' . json_encode(array_column($blsydata, 'SYYS'), JSON_UNESCAPED_UNICODE)]);

            /* $sql = "SELECT DISTINCT CI_MR_SIGN.DT_SIGN AS first_blsy_time FROM IIH.CI_MR_SIGN LEFT JOIN IIH.CI_MR ON IIH.CI_MR.ID_MR = IIH.CI_MR_SIGN.ID_MR LEFT JOIN IIH.BD_MR_LVL ON IIH.BD_MR_LVL.ID_MR_SIGN_TYPE = IIH.CI_MR.ID_MR_SIGNLVL AND IIH.BD_MR_LVL.ID_DEP_LVL = IIH.CI_MR.ID_DEP_PAT LEFT JOIN IIH.BD_MR_LVL_ITM_EMP ON IIH.BD_MR_LVL_ITM_EMP.ID_LVL_EMP = IIH.CI_MR_SIGN.ID_EMP_SIGN LEFT JOIN IIH.BD_MR_LVL_ITM ON IIH.BD_MR_LVL_ITM.ID_MR_LVL_ITM = IIH.BD_MR_LVL_ITM_EMP.ID_MR_LVL_ITM AND IIH.BD_MR_LVL_ITM.NAME = CASE WHEN IIH.CI_MR.SD_MR_SIGNLVL = '01' THEN '一级审签' WHEN IIH.CI_MR.SD_MR_SIGNLVL = '02' THEN '二级审签' WHEN IIH.CI_MR.SD_MR_SIGNLVL = '03' THEN '三级审签' ELSE IIH.CI_MR.SD_MR_SIGNLVL  END WHERE IIH.BD_MR_LVL_ITM.NAME IS NOT NULL AND IIH.CI_MR.ID_MR = '{$item['BLBH']}' ORDER BY IIH.CI_MR_SIGN.DT_SIGN ASC";
            $result1 = oci_parse(self::$con, $sql);
            oci_execute($result1, OCI_DEFAULT);
            $data1 = [];
            while ($row = oci_fetch_assoc($result1)) {
                $data1[] = $row;
            } */
            $lvsql = "SELECT CI_MR.SD_MR_SIGNLVL FROM IIH.CI_MR WHERE IIH.CI_MR.ID_MR = '{$item['BLBH']}'";
            $lvdata = oci_parse(self::$con, $lvsql);
            oci_execute($lvdata, OCI_DEFAULT);
            $resultlv = [];
            while ($row = oci_fetch_assoc($lvdata)) {
                $resultlv[] = $row;
            }
            $level = $resultlv[0]['SD_MR_SIGNLVL'];
            if (!empty($result)) {
                if ($level == '一级审签' || $level == '无需审签' || $level == '无审签' || $level == '01' || empty($level)) {
                    $result['first_blsy_time']  = $blsydata[0]['SYSJ'] ?? '';
                } elseif ($level == '二级审签' || $level == '02') {
                    //$result['first_blsy_time'] = $data1[0]['FIRST_BLSY_TIME'] ?? '';
                    //根据签名数据的blsydata的syys关联staff的code取ygjb_text(职称)，二级需要包含主治或主任，取最早的
                    $first_blsy_time = '';
                    foreach ($blsydata as $item) {
                        $staff = Staff::query()->where('code', $item['SYYS'])->first();
                        if ($staff) {
                            $ygjb = $staff->ygjb_text;
                            if ($ygjb == '住陪医师') {
                                continue;
                            }
                            if (strpos($ygjb, '医师') !== false || strpos($ygjb, '主治') !== false || strpos($ygjb, '主任') !== false) {
                                $first_blsy_time = $item['SYSJ'] ?? '';
                                break;
                            }
                        }
                    }
                    $result['first_blsy_time'] = $first_blsy_time;
                } elseif ($level == '三级审签' || $level == '03') {
                    //$result['first_blsy_time'] = $data1[0]['FIRST_BLSY_TIME'] ?? '';
                    //根据签名数据的blsydata的syys关联staff的code取ygjb_text(职称)，三级需要包含主任，取最早的
                    $first_blsy_time = '';
                    foreach ($blsydata as $item) {
                        $staff = Staff::query()->where('code', $item['SYYS'])->first();
                        if ($staff) {
                            $ygjb = $staff->ygjb_text;
                            if (strpos($ygjb, '主任') !== false) {
                                $first_blsy_time = $item['SYSJ'] ?? '';
                                break;
                            }
                        }
                    }
                    $result['first_blsy_time'] = $first_blsy_time;
                }
            }
            $bl01Data = $result;
            EMR_BL_BL01::query()->updateOrInsert(['BLBH' => $item['BLBH']], $result); //插入或更新病历数据
            EMR_BL_BLXG::query()->updateOrInsert(['BLBH' => $item['BLBH']], $temp); //插入或更新BLXG数据

            foreach ($blsydata as $item) {
                // DS=1 清除， 我方是0
                // DS=0 正常，我方是1
                if (intval($item['DS']) == 1) {
                    continue;
                }
                if (strpos($item['QMLX'], 'patname') !== false) {
                    $item['QMLX'] = 2;
                    $item['SYYS'] = $item['NAME_PAT_SIGN'];
                } else {
                    $item['QMLX'] = 1;
                }
                unset($item['NAME_PAT_SIGN'], $item['DS']);
                EMR_BL_BLSY::query()->updateOrInsert(['JLXH' => $item['JLXH']], $item);
            }

            $Mr_str = "";
            $res = $this->getEmrContentFromApi('http://10.32.45.111:8089/emr/download', ['emrId' => $result['BLBH']]);
            // Log::info('病历内容Data为空::'.$zyh, array_merge(['Id_mr' => $result['BLBH'],'zyh' => $zyh], compact('res')));
            if ($res && data_get($res, 'code') === '0') {
                //获取加密串
                $blContent = data_get($res, 'data.content', '');
                if ($blContent) {
                    $Mr_str = str_replace('[图片]', '', $blContent);
                    if (!empty($Mr_str)) {
                        $Mr_str = str_replace("婚姻状况：", "婚姻：", $Mr_str);
                        EMR_BL_BLXG::query()->updateOrInsert(['BLBH' => $result['BLBH']], ['HJNR' => $Mr_str]);
                        $result["HJNR"] = $Mr_str;
                        $bldf->insertData([], $result["MBLB"], $result);
                    }
                } else {
                    DataSyncLog::addData(['zyh' => $zyh, 'content' => '病历HJNR内容为空' . $result['BLBH']]);
                    Log::error('Iih病历内容Data为空::', array_merge(['Id_mr' => $result['BLBH']], compact('res')));
                }
            } else {
                DataSyncLog::addData(['zyh' => $zyh, 'content' => '病历HJNR内容查询失败' . $result['BLBH'], 'sync_content_msg' => json_encode($res, JSON_UNESCAPED_UNICODE)]);
                Log::error('Iih病历内容查询失败::', array_merge(['Id_mr' => $result['BLBH']], compact('res')));
            }
            // 格式化病例名称
            $bl01Data["HJNR"] = $Mr_str;
            $this->cleanBlmc($bl01Data);
        }
        EsSaveService::bl01($zyh);
    }

    public function cleanBlmc($item = [])
    {
        if ($item["BLLB"] == 294 || $item["BLLB"] == 43 || $item["BLLB"] == 303) {
            $this->formatBL01BLMC294($item);
        } elseif ($item["BLLB"] == 82) {
            $this->formatBL01BLMC82($item);
        }

        if ($item["MBLB"] == 30304) {
            $this->formatHjnr30304($item);
        } elseif ($item["MBLB"] == 42) {
            $this->formatHJNR42($item);
        } elseif ($item["MBLB"] == 304) {
            $this->formatHJNR304($item);
        } elseif ($item["MBLB"] == 82) {
            $this->formatHJNR82($item);
        } elseif ($item["MBLB"] == 44) {
            $this->formatHJNR44($item);
        }
    }

    /**
     * 疑难病历讨论记录格式化
     */
    public function formatHJNR44($bl01Data)
    {
        $HJNR = $bl01Data["HJNR"];
        $data = (new BlDataFormatService())->cleanDataFilter('mblb44', [], $HJNR);
        $data['BLBH'] = $bl01Data["BLBH"];
        $data['ZYH'] = $bl01Data["JZHM"];

        Mblb44::query()->updateOrInsert(['BLBH' => $data['BLBH']], $data);
    }

    /**
     * 格式化术前小结
     */
    public function formatHJNR82($bl01Data)
    {
        $HJNR = $bl01Data["HJNR"];
        $data = (new BlDataFormatService())->cleanDataFilter('mblb82', [], $HJNR);
        $data['BLBH'] = $bl01Data["BLBH"];
        $data['ZYH'] = $bl01Data["JZHM"];

        Mblb82::query()->updateOrInsert(['BLBH' => $data['BLBH']], $data);
    }

    /**
     * 格式化术前讨论
     */
    public function formatHJNR304($bl01Data)
    {
        $HJNR = $bl01Data["HJNR"];
        $data = (new BlDataFormatService())->cleanDataFilter('mblb304', [], $HJNR);
        $data['BLBH'] = $bl01Data["BLBH"];
        $data['ZYH'] = $bl01Data["JZHM"];

        Mblb304::query()->updateOrInsert(['BLBH' => $data['BLBH']], $data);
    }

    /**
     * 格式化优创操作记录
     * @param array $bl01Data
     */
    public function formatHJNR42($bl01Data)
    {
        $HJNR = $bl01Data["HJNR"];
        $data = (new BlDataFormatService())->cleanDataFilter('mblb42', [], $HJNR);

        $sssj = $data['SSSJ'];
        unset($data['SSSJ']);
        if (preg_match('/(\d{4}-\d{1,2}-\d{1,2}\d{1,2}:\d{2}:\d{2})至(\d{4}-\d{1,2}-\d{1,2}\d{1,2}:\d{2}:\d{2})/i', $sssj, $matches)) {
            $data['KSSJ'] = $matches[1];
            $data['KSSJ'] = substr($data['KSSJ'], 0, 10) . ' ' . substr($data['KSSJ'], 10);
            $data['JSSJ'] = $matches[2];
            $data['JSSJ'] = substr($data['JSSJ'], 0, 10) . ' ' . substr($data['JSSJ'], 10);
        }
        $data['BLBH'] = $bl01Data["BLBH"];
        $data['ZYH'] = $bl01Data["JZHM"];

        Mblb42::query()->updateOrInsert(['BLBH' => $data['BLBH']], $data);
    }

    /**
     * 格式化优创操作记录
     * @param array $bl01Data
     */
    public function formatHjnr30304($bl01Data)
    {
        if (strpos($bl01Data["BLMC"], '有创操作记录') === false) {
            return;
        }
        $HJNR = $bl01Data["HJNR"];
        $data = (new BlDataFormatService())->cleanDataFilter('mblb30304', [], $HJNR);
        $data['BLBH'] = $bl01Data["BLBH"];
        $data['ZYH'] = $bl01Data["JZHM"];

        Mblb30304::query()->updateOrInsert(['BLBH' => $data['BLBH']], $data);
    }

    /**
     * 发送GET请求到第三方接口，解析响应中的content字段（XML字符串）
     * @param string $url 第三方接口URL
     * @param array $params GET参数，key-value形式
     * @return array [
     *   'code' => string,
     *   'message' => string,
     *   'data' => [
     *      'emrId' => string,
     *      'emrName' => string,
     *      'content' => array|string 原content是xml字符串，这里解析为数组
     *   ]
     * ]
     */
    public function getEmrContentFromApi($url, $params = [])
    {
        // 构建完整URL参数
        $queryStr = http_build_query($params);
        $requestUrl = $url . (strpos($url, '?') === false ? '?' : '&') . $queryStr;

        // 请求
        $response = @file_get_contents($requestUrl);
        if ($response === false) {
            return [
                'code' => '-1',
                'message' => '请求失败',
                'data' => []
            ];
        }

        // 解析JSON（接口返回格式为JSON）
        $resArr = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($resArr)) {
            return [
                'code' => '-2',
                'message' => '返回数据不是有效的JSON',
                'data' => []
            ];
        }

        // 正常响应结构判断
        if (!isset($resArr['code'], $resArr['data']['content'])) {
            return [
                'code' => '-3',
                'message' => '接口响应结构异常',
                'data' => []
            ];
        }

        $contentXml = $resArr['data']['content'];

        // if (is_string($contentXml) && !empty($contentXml)) {
        //     // $contentXml 是一个xml字符串，需要获取其中 BodyText 的内容
        //     $bodyTextValue = null;
        //     try {
        //         $xmlObjTemp = simplexml_load_string($contentXml, 'SimpleXMLElement', LIBXML_NOCDATA);
        //         if ($xmlObjTemp !== false) {
        //             // 支持BodyText作为根节点或各级子节点
        //             if (isset($xmlObjTemp->BodyText)) {
        //                 $bodyTextValue = (string)$xmlObjTemp->BodyText;
        //             } else {
        //                 // 搜索所有节点中的BodyText
        //                 $bodyTextNodes = $xmlObjTemp->xpath('//BodyText');
        //                 if (!empty($bodyTextNodes)) {
        //                     // 有可能多个BodyText节点，取第一个
        //                     $bodyTextValue = (string)$bodyTextNodes[0];
        //                 }
        //             }
        //         }
        //     } catch (\Throwable $e) {
        //         // 通过正则获取xml中BodyText标签中的内容
        //         if (empty($bodyTextValue)) {


        //         }
        //     }
        // }


        $bodyTextValue = '';
        if (preg_match('/<BodyText(?:\s[^>]*)?>(.*?)<\/BodyText>/is', $contentXml, $matches)) {
            $bodyTextValue = $matches[1];
        }
        $resArr['data']['content'] = $bodyTextValue;

        return $resArr;
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

        //        V_JMGS_TESTRESULT::query()->where('ZYH','=',$zyh)->delete();
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

            V_JMGS_TESTRESULT::query()->updateOrInsert(['REPORT_PK' => $testResultData['REPORT_PK']], $testResultData);
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
        V_JMGS_YMresult::query()->where('ZYH', '=', $zyh)->delete();
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

            V_JMGS_YMresult::query()->updateOrInsert(['SOURCE_PK' => $ymResultData['SOURCE_PK']], $ymResultData);
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


    /**
     * @param string $zyh
     * @param string $blbh
     * @return bool
     * 同步病程记录相关数据
     */
    public function OMRBL01($startTime = "")
    {
        $this->getConnect();
        if (empty($startTime)) {
            $startTime = strtotime(date('Y-m-d'), time() - 86400);
        } else {
            $startTime = strtotime(date('Y-m-d', strtotime($startTime)));
        }

        $staff = Staff::query()->get()->toArray();
        $staff = array_column($staff, 'name', 'YHID');


        $sql = "SELECT IIH.BD_DEP.ID_DEP,IIH.BD_DEP.NAME FROM IIH.BD_DEP";
        $dep = self::querySql($sql);
        $dep = array_column($dep, 'NAME', 'ID_DEP');
        while (true) {
            if ($startTime >= time()) {
                break;
            }
            $startTimeStr = date('Y-m-d', $startTime);
            $endTimeStr = date('Y-m-d', $startTime + 86400);
            $sql = "SELECT NVL(IIH.CI_MR.ID_MR, '') AS BLBH, NVL(IIH.CI_MR.ID_ENT, '') AS JZHM, NVL(IIH.CI_MR.NAME, '') AS BLMC, NVL(IIH.CI_MR.CREATEDTIME, '') AS CJSJ, NVL(IIH.CI_MR.MODIFIEDTIME, '') AS WCSJ, NVL(IIH.CI_MR.MODIFIEDBY, '') AS SXYS, NVL(IIH.CI_MR.ID_DEP_PAT, '') AS BRKS, NVL(IIH.CI_MR.ID_SUBMIT_DEPT, '') AS CJKS, NVL(CASE FG_COMPLETE WHEN 'Y' THEN 1 WHEN 'N' THEN 2 ELSE 9 END, '') AS BLZT, NVL(IIH.CI_MR.DT_SUBMIT_FIRST, '') AS first_blsy_time FROM IIH.CI_MR WHERE IIH.CI_MR.CODE_ENTP = 00 AND IIH.CI_MR.DS = 0 AND IIH.CI_MR.MODIFIEDTIME >='{$startTimeStr}' AND IIH.CI_MR.MODIFIEDTIME <='{$endTimeStr}'";
            $data = self::querySql($sql);

            foreach ($data as $item) {
                if (strpos($item['BLMC'], '门诊病历') === false || (strpos($item['BLMC'], '初诊') === false && strpos($item['BLMC'], '复诊') === false && strpos($item['BLMC'], '急诊') === false)) {
                    continue;
                }


                $sql = "SELECT IIH.EN_ENT.ID_DEP_PHY,IIH.EN_ENT.ID_PAT,IIH.EN_ENT.DT_ACPT,IIH.EN_ENT.SD_SEX_PAT,IIH.EN_ENT.DT_BIRTH_PAT FROM IIH.EN_ENT WHERE IIH.EN_ENT.FG_ACPTVALID='Y' AND IIH.EN_ENT.ID_ENT = '{$item['JZHM']}'";
                $data2 = self::querySql($sql);
                if (empty($data2)) {
                    continue;
                }
                // SD_BIRTH_PAT是一个年月日，根据当前时间计算患者的年龄
                $age = '';
                if (!empty($data2) && !empty($data2[0]['DT_BIRTH_PAT'])) {
                    $birthDate = $data2[0]['DT_BIRTH_PAT'];
                    $birthTime = strtotime($birthDate);
                    if ($birthTime !== false) {
                        $age = date('Y') - date('Y', $birthTime);
                        if (date('md') < date('md', $birthTime)) {
                            $age--;
                        }
                        $age = $age < 0 ? 0 : $age;
                    }
                }

                $sql = "SELECT IIH.PI_PAT.CODE,IIH.PI_PAT.NAME,IIH.PI_PAT.ID_SEX,IIH.PI_PAT.ID_CODE FROM IIH.PI_PAT WHERE IIH.PI_PAT.ID_PAT = '{$data2[0]['ID_PAT']}'";
                $data3 = self::querySql($sql);
                if (empty($data3)) {
                    continue;
                }
                $ksdm = !empty($data2) ? $data2[0]['ID_DEP_PHY'] : '';

                $result = [
                    'JZXH' => $item['JZHM'] ?? '',
                    'BRID' => !empty($data2) ? $data2[0]['ID_PAT'] ?? '' : '',
                    'mzh' => !empty($data3) ? $data3[0]['CODE'] ?? '' : '',
                    'BLBH' => $item['BLBH'] ?? '',
                    'BLMC' => $item['BLMC'] ?? '',
                    'CJSJ' => $item['CJSJ'] ?? '',
                    'WCSJ' => $item['WCSJ'] ?? '',
                    'BRKS' => $item['BRKS'] ?? '',
                    'CJKS' => $item['CJKS'] ?? '',
                    'BLZT' => $item['BLZT'] ?? '',
                    'SXYS' => $staff[$item['SXYS']] ?? '',
                    'jzsj' => !empty($data2) ? $data2[0]['DT_ACPT'] ?? '' : '',
                    'SFZH' => !empty($data3) ? $data3[0]['ID_CODE'] ?? '' : '',
                    'xm' => !empty($data3) ? $data3[0]['NAME'] ?? '' : '',
                    'nl' => $age,
                    'nl1' => $age . '岁',
                    'xb' => !empty($data2) ? ($data2[0]['SD_SEX_PAT'] == 1 ? '男' : '女') : '',
                    'ks' => $dep[$ksdm] ?? ''
                ];


                $res = $this->getEmrContentFromApi('http://10.32.45.111:8089/emr/download', ['emrId' => $result['BLBH']]);
                // Log::info('病历内容Data为空::'.$zyh, array_merge(['Id_mr' => $result['BLBH'],'zyh' => $zyh], compact('res')));
                if ($res && data_get($res, 'code') === '0') {
                    //获取加密串
                    $blContent = data_get($res, 'data.content', '');
                    if ($blContent) {
                        $Mr_str = str_replace('[图片]', '', $blContent);
                        if (!empty($Mr_str)) {
                            $result["BLNR_TXT"] = $Mr_str;
                        } else {
                            Log::error('Iih病历内容Data为空::', array_merge(['Id_mr' => $result['BLBH']], compact('res', 'encryptStr', 'blXml', 'blJson', 'blContent')));
                        }
                    } else {
                        Log::error('Iih病历内容Data为空::', array_merge(['Id_mr' => $result['BLBH']], compact('res', 'encryptStr', 'blXml', 'blJson', 'blContent')));
                    }
                } else {
                    Log::error('Iih病历内容查询失败::', array_merge(['Id_mr' => $result['BLBH']], compact('res')));
                }


                // 获取签名
                $blsysql = "SELECT NVL(IIH.CI_MR_SIGN.ID_MRSIGN, '') AS JLXH, NVL(IIH.CI_MR_SIGN.ID_MR, '') AS BLBH, NVL(IIH.CI_MR_SIGN.ID_EMP_SIGN, '') AS SYYS, NVL(IIH.CI_MR_SIGN.DT_SIGN, '') AS SYSJ, NVL(IIH.CI_MR_SIGN.CREATEDTIME, '') AS JLSJ, NVL(IIH.CI_MR_SIGN.MODIFIEDTIME, '') AS updated_at FROM IIH.CI_MR_SIGN WHERE CI_MR_SIGN.ID_MR = '{$item['BLBH']}' ORDER BY CI_MR_SIGN.DT_SIGN ASC"; //按照jlsj时间升序
                $blsydata = self::querySql($blsysql);
                foreach ($blsydata as $item) {
                    OmrBlsy::query()->updateOrInsert(['JLXH' => $item['JLXH']], $item);
                }

                OMR_BL01::query()->updateOrInsert(['BLBH' => $item['BLBH']], $result);
                $this->cleanBl01Data($result);
            }

            $startTime += 86400;
        }
    }

    /**
     * 清洗病历数据
     * @param array $result
     * @return void
     */
    public function cleanBl01Data($result = [])
    {
        $omrBl01Format = new OMR_BL01_Format();
        $blbh = $result['BLBH'];
        $saveData = [];

        // 处理病历内容
        $BLNR_TXT = $result['BLNR_TXT'] ?? "";
        if (empty($BLNR_TXT)) {
            return [];
        }

        // 门诊病历有两套模板，如果第一个没有清洗出来住院号，则换第二种方法
        $saveData1 = $omrBl01Format->cleanDataFilter($BLNR_TXT, 1);
        if (empty($saveData1['mzh'])) {
            $saveData1 = $omrBl01Format->cleanDataFilterV2($BLNR_TXT, 1);
        }

        $saveData = array_merge($saveData, $saveData1);
        if (!empty($saveData['ks'])) {
            $saveData['ks'] = str_replace('门诊', '', $saveData['ks']);
        }

        if (!empty($saveData['jzsj'])) {
            $saveData['jzsj'] = substr($saveData['jzsj'], 0, 10) . ' ' . substr($saveData['jzsj'], 10);
        }

        // 西药处理
        if (preg_match('/&lt;西药&gt;(.*?)}/s', $BLNR_TXT, $matches)) {
            $xyContent = $matches[1];
            $saveData['xy'] = trim($xyContent);

            $xyLines = preg_split('/\s+/', trim($xyContent));
            $xyList = [];

            // 每3个元素为一组药品信息
            for ($i = 0; $i < count($xyLines); $i += 3) {
                if (isset($xyLines[$i]) && !empty(trim($xyLines[$i]))) {
                    $xyList[] = [
                        'ym' => $xyLines[$i] ?? '',
                        'yl' => $xyLines[$i + 1] ?? '',
                        'yf' => $xyLines[$i + 2] ?? '',
                        'pc' => '',
                    ];
                }
            }

            if ($xyList) {
                $saveData['xy_json'] = json_encode($xyList, JSON_UNESCAPED_UNICODE);
            }
        }

        // 初诊、复诊、急诊
        $saveData['bl_type'] = '门诊';
        if (stripos($BLNR_TXT, '门(急)诊病历') !== false) {
            $saveData['bl_type'] = '门诊';
        } elseif (stripos($BLNR_TXT, '初诊') !== false) {
            $saveData['bl_type'] = '初诊';
        } elseif (stripos($BLNR_TXT, '复诊') !== false) {
            $saveData['bl_type'] = '复诊';
        } elseif (stripos($BLNR_TXT, '急诊') !== false) {
            $saveData['bl_type'] = '急诊';
        }

        unset($saveData['xm']);
        if (empty($saveData['jzsj'])) {
            unset($saveData['jzsj']);
        }

        unset($saveData['nl']);
        unset($saveData['xb']);
        unset($saveData['ks']);
        unset($saveData['mzh']);

        if ($saveData) {
            $result = OMR_BL01::query()->where('BLBH', '=', $blbh)->update($saveData);
            if ($result) {
                EsSaveService::esSaveOmrBl01($blbh);
            }
        }
    }

    /**
     * 清洗输血记录中的住院号
     */
    public function cleanBloodBLZK()
    {
        // 获取全部的手术记录
        $page = 1;
        $pageSize = 100;
        while (true) {
            // 分批读取ZY_SS表数据
            $ssList = ZY_SS::query()
                ->orderBy('id', 'asc')
                ->offset(($page - 1) * $pageSize)
                ->limit($pageSize)
                ->get();

            if ($ssList->isEmpty()) {
                break;
            }

            foreach ($ssList as $ss) {
                // 从手术表获取pat_no和inf_starttime
                $pat_no = $ss->BAH ?? '';
                $inf_starttime = $ss->inf_starttime ?? null;
                if (!$pat_no || !$inf_starttime) {
                    continue;
                }

                // 查找ZY_BRRY符合条件的住院患者
                $brry = ZY_BRRY::query()
                    ->where('AAA28', $pat_no)
                    ->where('AAB01', '<=', $inf_starttime)
                    ->where(function ($query) use ($inf_starttime) {
                        $query->where('AAC01', '>=', $inf_starttime)->orWhereNull('AAC01')->orWhere('AAC01', '');
                    })
                    ->first();

                if ($brry && isset($brry->ZYH)) {
                    ZY_SS::query()->where('id', $ss->id)->update(['ZYH' => $brry->ZYH]);
                }
            }
            $page++;
        }
    }

    // 清洗输血记录中的住院号
    public function cleanPacsZYH($time = '')
    {
        $id = 0;
        while (true) {
            $rows = PACS::query()->where('id', '>', $id)
                ->orderBy('id', 'asc')
                ->where('JYSJ', '>=', $time)
                ->limit(100)->get(['id', 'BAH', 'JYSJ'])->toArray();
            if (empty($rows)) {
                break;
            }
            echo $id . PHP_EOL;
            foreach ($rows as $ss) {
                // 从手术表获取pat_no和inf_starttime
                $pat_no = $ss['BAH'] ?? '';
                $jysj = $ss['JYSJ'] ?? null;
                if (!$pat_no || !$jysj) {
                    continue;
                }

                // 查找ZY_BRRY符合条件的住院患者
                $brry = ZY_BRRY::query()
                    ->where('AAA28', $pat_no)
                    ->where('AAB01', '<=', $jysj)
                    ->where(function ($query) use ($jysj) {
                        $query->where('AAC01', '>=', $jysj)->orWhereNull('AAC01')->orWhere('AAC01', '');
                    })
                    ->first();

                if ($brry && isset($brry->ZYH)) {
                    PACS::query()->where('id', $ss['id'])->update(['ZYH' => $brry->ZYH]);
                }
            }
            $id = $rows[count($rows) - 1]['id'];
        }
    }

    // 清手麻中的住院号
    public function cleanSSAPZYH($zyhm = '')
    {
        $id = 0;
        while (true) {
            $rows = SM_SSAP::query()->where('id', '>', $id)
                ->when($zyhm, function ($query) use ($zyhm) {
                    $query->where('ZYHM', '=', $zyhm);
                })
                ->orderBy('id', 'asc')
                ->limit(100)->get(['id', 'ZYHM', 'SSRQ'])->toArray();
            if (empty($rows)) {
                break;
            }
            foreach ($rows as $ss) {
                // 从手术表获取pat_no和inf_starttime
                $pat_no = $ss['ZYHM'] ?? '';
                $ssrq = $ss['SSRQ'] ?? null;
                if (!$pat_no || !$ssrq) {
                    continue;
                }

                // 查找ZY_BRRY符合条件的住院患者
                $brry = ZY_BRRY::query()
                    ->where('AAA28', $pat_no)
                    ->where('AAB01', '<=', $ssrq)
                    ->where(function ($query) use ($ssrq) {
                        $query->where('AAC01', '>=', $ssrq)->orWhereNull('AAC01')->orWhere('AAC01', '');
                    })
                    ->first();

                if ($brry && isset($brry->ZYH)) {
                    SM_SSAP::query()->where('id', $ss['id'])->update(['ZYH' => $brry->ZYH]);
                }
            }
            $id = $rows[count($rows) - 1]['id'];
        }
    }

    /**
     * 获取抢救记录中的抢救时间
     * @param string $ZYH 住院号
     * @return array
     */
    public function getQjsj($ZYH = '')
    {

        $pregTime = '(\d{4}\/\d{2}\/\d{2}\s+\d{2}:\d{2}(?::\d{2})?|\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(?::\d{2})?|\d{4}-\d{2}-\d{2}\s+\d{2}时\d{2}分(?:\d{2}秒)?|\d{4}年\d{2}月\d{2}日\s+\d{2}时\d{2}分(?:\d{2}秒)?|\d{4}年\d{2}月\d{2}日\s+\d{2}:\d{2}|\d{4}年\d{2}月\d{2}日\s+\d{2}:\d{2}:\d{2})';
        $newData = EMR_BL_BL01::query()
            ->leftJoin("EMR_BL_BLXG", "EMR_BL_BL01.BLBH", "=", "EMR_BL_BLXG.BLBH")
            ->where("EMR_BL_BL01.JZHM", $ZYH)
            ->where("EMR_BL_BL01.MBLB", 27)
            ->get(['EMR_BL_BLXG.HJNR', 'EMR_BL_BL01.BLMC', 'EMR_BL_BL01.ZXSJ', 'EMR_BL_BL01.first_blsy_time'])->toArray();
        if (empty($newData)) {
            return [];
        }

        $qjjl = [];
        foreach ($newData as $item) {
            $hjnr = $item["HJNR"];
            if (empty($hjnr)) {
                continue;
            }
            $match_time_str = '';
            if ($hjnr) {
                // 匹配各种抢救结束的时间格式
                if (preg_match('/抢救结束时间：' . $pregTime . '/', $hjnr, $matches)) {
                    $match_time_str = $matches[1];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分抢救成功/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于.*?(\d{1,2})时(\d{1,2})分抢救成功/', $hjnr, $matches)) {
                    // 确保小时和分钟是两位数格式
                    $hour = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                    $minute = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                    $match_time_str = "$hour:$minute";
                    //echo $match_time_str; // 输出 22:05
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分病情/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分血压/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分测血压/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分使用/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分氧/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分仍无自主呼吸心跳/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分心电图示直线/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分临床死亡/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分宣布/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分转入/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分死亡/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分出现意识不清/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{4}-\d{2}-\d{2} (\d{2}:\d{2}))宣布/', $hjnr, $matches)) {
                    //提取后面的时间到分
                    $match_time_str = $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分抢救成功/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分抢救成功/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分心电图示无心电/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分出现意识不清/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于\((\d{1,2})\s*时\s*(\d{1,2})\s*分\)心电图示无心电/', $hjnr, $matches)) { // 修正括号匹配
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分呼吸及血压/', $hjnr, $matches)) {
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/于(\d{1,2})\s*时\s*(\d{1,2})\s*分呼吸及血压/', $hjnr, $matches)) { // 修正括号匹配
                    $match_time_str = $matches[1] . ':' . $matches[2];
                } elseif (preg_match('/抢救结束时间：\s*(\d{4}-\d{1,2}-\d{1,2}\s+(\d{1,2}:\d{1,2}))/', $hjnr, $matches)) {
                    // 新增匹配 抢救结束时间：2025-10-23 00:23 取00:23 (全角冒号)
                    $match_time_str = $matches[2];
                } elseif (preg_match('/抢救结束时间:\s*(\d{4}-\d{1,2}-\d{1,2}\s+(\d{1,2}:\d{1,2}))/', $hjnr, $matches)) {
                    // 新增匹配 抢救结束时间:2025-10-23 00:23 取00:23 (半角冒号)
                    $match_time_str = $matches[2];
                }
            }
            //preg_match($preg, $HJNR, $dateTime);
            $dateTime = $match_time_str;

            if (empty($dateTime)) {
                return [];
            }
            $item['qjsj'] = $dateTime;
            $qjjl[] = $item;
            return $qjjl;
        }
    }

    /**
     * 获取病案首页的签名
     * @param string $zyh 病案号
     * @return array
     */
    public function getBasyBlsy($zyh = "")
    {
        $staffData = staff::query()->get()->toArray();
        $staffData = array_column($staffData, 'code', 'base_code');
        $sql = "select fp.ID_ENT,fpo.ID_ZR_DOC,psn.CODE as PSN_CODE,usr.CODE as USR_CODE,fp.DT_SUBMIT_FIRST as SYSJ
        from IIH.CI_MR_FP fp
        inner join IIH.EN_ENT_IP ip on fp.ID_ENT = ip.ID_ENT
        inner join IIH.CI_MR_FP_OTHER fpo on fp.ID_MRFP = fpo.ID_MRTP and fp.ID_ENT = fpo.ID_ENT
        inner join IIH.BD_PSNDOC psn on fpo.ID_ZR_DOC = psn.ID_PSNDOC
        inner join IIH.SYS_USER usr on psn.ID_PSNDOC = usr.ID_PSN
        left join IIH.SYS_MYSCVI sm on sm.ID_USER = usr.ID_USER
        where fp.ID_ENT='{$zyh}'";

        $blsydata = self::querySql($sql);
        if (empty($blsydata)) {
            return [];
        }
        EMR_BL_BLSY::query()->where('BLBH', $zyh)->delete();
        foreach ($blsydata as $item) {
            $user = [];
            $user[] = !empty($staffData[$item['ID_ZR_DOC']]) ? $staffData[$item['ID_ZR_DOC']] : $item['ID_ZR_DOC'];
            $user[] = !empty($staffData[$item['PSN_CODE']]) ? $staffData[$item['PSN_CODE']] : $item['PSN_CODE'];
            $user[] = !empty($staffData[$item['USR_CODE']]) ? $staffData[$item['USR_CODE']] : $item['USR_CODE'];
            $user = array_filter(array_unique($user));
            $item['QMLX'] = 1;
            foreach ($user as $u) {
                EMR_BL_BLSY::query()->insert([
                    'BLBH' => $item['ID_ENT'],
                    'SYYS' => $u,
                    'SYSJ' => $item['SYSJ'],
                ]);
            }
        }
    }

    /**
     * 同步病理数据
     * @param string $zyh 病案号
     * @return array
     */
    public function getBingLiData($zyh = "")
    {

        PACS::query()->where('ZYH', $zyh)->where('ExamType', '07')->delete();
        $this->getMysqlConnect();
        $this->getConnect();
        $sql = "select ID_PAT from HIS_V1_EN_ENT where ID_ENT='{$zyh}'";

        $ent = self::queryMysqlSql($sql);
        if (empty($ent)) {
            return [];
        }
        $sql = "select ID_PAT,CODE from HIS_V1_PI_PAT where ID_PAT='{$ent[0]['ID_PAT']}'";

        $pat = self::queryMysqlSql($sql);
        if (empty($pat)) {
            return [];
        }

        $patient = self::queryMysqlSql("select PK_PATIENT from PIS_V1_BD_PATIENT where PATIENTID_HIS = '{$pat[0]['CODE']}'");
        if (empty($patient)) {
            return [];
        }
        $study = self::queryMysqlSql("select PK_STUDY from PIS_V1_STUDY where PK_PATIENT = '{$patient[0]['PK_PATIENT']}'");
        if (empty($study)) {
            return [];
        }
        foreach ($study as $item) {
            $BGSJ = '';
            $YXBX = '';
            $report = self::queryMysqlSql("select REPORTDATETIME,CONCLUSIONFORMAT from PIS_V1_REPORT where PK_STUDY = '{$item['PK_STUDY']}'");
            if ($report) {
                $BGSJ = $report[0]['REPORTDATETIME'];
                $YXBX = $report[0]['CONCLUSIONFORMAT'];
            } else {
                continue;
            }
            
            PACS::query()->insert([
                'StudyUid' => $item['PK_STUDY'],
                'SQDH' => $item['ACCESSIONNUMBER'],
                'SQKSMC' => $item['HISAPPLYDEPT_NAME'],
                'SQRXM' => $item['HISAPPLYDOCTOR'],
                'JCKS' => $item['PK_DEPT'],
                'JCKSMC' => $item['DEPT_NAME'],
                'JCYS' => $item['INSPECTIONDOCTOR_NAME'],
                'JYSJ' => $item['STUDYDATE'],
                'ZYH' => $zyh,
                'BGSJ' => $BGSJ,
                'YXBX' => $YXBX,
                'ExamType' => '07',
            ]);
        }

        // $biaoben = self::queryMysqlSql("select 07 as ExamType,BIAOBEN_MINGCHENG AS ExamType_MC,BIAOBEN_DENGJI_TIME as SYSJ from PIS_V1_PL_BIAOBEN where PK_STUDY = '{$study[0]['PK_STUDY']}'");
        // if ($biaoben) {
        //     foreach ($biaoben as $item) {
        //         PACS::query()->insert([
        //             'StudyUid' => $study[0]['PK_STUDY'],
        //             'SQDH' => $study[0]['ACCESSIONNUMBER'],
        //             'SQKSMC' => $study[0]['HISAPPLYDEPT_NAME'],
        //             'SQRXM' => $study[0]['HISAPPLYDOCTOR'],
        //             'JCKS' => $study[0]['PK_DEPT'],
        //             'JCKSMC' => $study[0]['DEPT_NAME'],
        //             'JCYS' => $study[0]['INSPECTIONDOCTOR_NAME'],
        //             'JYSJ' => $study[0]['STUDYDATE'],
        //             'ZYH' => $zyh,
        //             'BGSJ' => $BGSJ,
        //             'YXBX' => $YXBX,
        //             'ExamType' => '07',
        //             'ExamType_MC' => $item['ExamType_MC'],
        //             'JYSJ' => $item['SYSJ'],
        //         ]);
        //     }
        // }
        // $bopian = self::queryMysqlSql("select 07 as ExamType,HUIZHEN_QUCAI_BUWEI AS ExamType_MC,ZHIPIAN_TIME as SYSJ from PIS_V1_PL_BOPIAN where PK_STUDY = '{$study[0]['PK_STUDY']}'");
        // if ($bopian) {
        //     foreach ($bopian as $item) {
        //         PACS::query()->insert([
        //             'StudyUid' => $study[0]['PK_STUDY'],
        //             'SQDH' => $study[0]['ACCESSIONNUMBER'],
        //             'SQKSMC' => $study[0]['HISAPPLYDEPT_NAME'],
        //             'SQRXM' => $study[0]['HISAPPLYDOCTOR'],
        //             'JCKS' => $study[0]['PK_DEPT'],
        //             'JCKSMC' => $study[0]['DEPT_NAME'],
        //             'JCYS' => $study[0]['INSPECTIONDOCTOR_NAME'],
        //             'ZYH' => $zyh,
        //             'BGSJ' => $BGSJ,
        //             'YXBX' => $YXBX,
        //             'ExamType' => '07',
        //             'ExamType_MC' => $item['ExamType_MC'],
        //             'JYSJ' => $item['SYSJ'],
        //         ]);
        //     }
        // }
        // $lakuai = self::queryMysqlSql("select 07 as ExamType,LAKUAI_QUCAI_BUWEI AS ExamType_MC,QUCAI_TIME as SYSJ from PIS_V1_PL_LAKUAI where PK_STUDY = '{$study[0]['PK_STUDY']}'");
        // if ($lakuai) {
        //     foreach ($lakuai as $item) {
        //         PACS::query()->insert([
        //             'StudyUid' => $study[0]['PK_STUDY'],
        //             'SQDH' => $study[0]['ACCESSIONNUMBER'],
        //             'SQKSMC' => $study[0]['HISAPPLYDEPT_NAME'],
        //             'SQRXM' => $study[0]['HISAPPLYDOCTOR'],
        //             'JCKS' => $study[0]['PK_DEPT'],
        //             'JCKSMC' => $study[0]['DEPT_NAME'],
        //             'JCYS' => $study[0]['INSPECTIONDOCTOR_NAME'],
        //             'ZYH' => $zyh,
        //             'BGSJ' => $BGSJ,
        //             'YXBX' => $YXBX,
        //             'ExamType' => '07',
        //             'ExamType_MC' => $item['ExamType_MC'],
        //             'JYSJ' => $item['SYSJ'],
        //         ]);
        //     }
        // }
    }

    /**
     * 同步检验数据
     * @param string $zyh 病案号
     * @return array
     */
    public function getJianYanData($zyh = "")
    {
        echo '检验数据同步开始' . PHP_EOL;

        $this->getMysqlConnect();
        $brry = ZY_BRRY::query()->where('ZYH', $zyh)->first();
        if (empty($brry)) {
            return [];
        }
        $brry = $brry->toArray();
        $AAA28 = $brry['AAA28'];
        $ZYCS = $brry['ZYCS'];
        $sql = "SELECT LIS_V1_req_master.barcode as TXM, LIS_V1_lab_report.sampleno as NO, LIS_V1_lab_report.specimen_name as YBLX, LIS_V1_lab_report.req_deptno as KS, LIS_V1_lab_report.req_wardno as BQ, LIS_V1_lab_report.pat_diag as LCZD, LIS_V1_lab_result.rpt_itemname as JYXM, LIS_V1_lab_result.result_num as JG, LIS_V1_lab_result.result_flag as TS, LIS_V1_lab_result.result_ref as CKFW, LIS_V1_lab_result.result_unit as DW, LIS_V1_req_master.report_user as SJYS, LIS_V1_req_master.report_user as JYY, LIS_V1_req_master.report_username as JYY_MC, LIS_V1_req_master.rechk_user as SHY, LIS_V1_req_master.rechk_username as SHY_MC, LIS_V1_req_master.sampled_dt as CJSJ, LIS_V1_req_master.recieve_dt as JSSJ, LIS_V1_req_master.report_dt as BGSJ, CASE LIS_V1_lab_report.req_reason WHEN '1' THEN '门诊' WHEN '2' THEN '急诊' WHEN '3' THEN '住院' WHEN '4' THEN '体检' WHEN '0401' THEN '暂且未知' WHEN '11' THEN '暂且未知'  WHEN '0402' THEN '暂且未知' WHEN '6' THEN '暂且未知' ELSE '其他' END as MZZYBZ, CASE LIS_V1_lab_report.req_reason WHEN '1' THEN '1' WHEN '3' THEN '2' WHEN '4' THEN '3' ELSE '9' END as STAYHOSPITALMODE, CASE LIS_V1_lab_report.req_reason WHEN '1' THEN '1' WHEN '3' THEN '2' WHEN '4' THEN '3' ELSE '9' END as BGLB,LIS_V1_lab_report.pat_no as AAA28,LIS_V1_lab_report.inp_id as ZYCS from LIS_V1_req_master left join LIS_V1_lab_report on LIS_V1_req_master.report_id=LIS_V1_lab_report.reportid left join LIS_V1_lab_result on LIS_V1_lab_report.reportid=LIS_V1_lab_result.reportid left join LIS_V1_lab_resultmed on LIS_V1_lab_resultmed.reportid=LIS_V1_lab_result.reportid left join LIS_V1_lab_med on  LIS_V1_lab_resultmed.medcode=LIS_V1_lab_med.medcode where LIS_V1_lab_result.Germflag!=0 AND LIS_V1_lab_report.pat_no='{$AAA28}' and LIS_V1_lab_report.inp_id={$ZYCS}";

        $test = self::queryMysqlSql($sql);
        echo '本次同步数据量：' . count($test) . PHP_EOL;
        if (empty($test)) {
            return [];
        }
        V_JMGS_TESTRESULT::query()->where('ZYH', $zyh)->delete();
        foreach ($test as $item) {
            $item['ZYH'] = $zyh;
            V_JMGS_TESTRESULT::query()->insert($item);
        }
        echo '检验数据同步完成' . PHP_EOL;
    }

    /**
     * 同步药敏数据
     * @param string $zyh 病案号
     * @return array
     */
    public function getYaoMinData($zyh = "")
    {
        echo '药敏数据同步开始' . PHP_EOL;

        $this->getMysqlConnect();
        $brry = ZY_BRRY::query()->where('ZYH', $zyh)->first();
        if (empty($brry)) {
            return [];
        }
        $brry = $brry->toArray();
        $AAA28 = $brry['AAA28'];
        $ZYCS = $brry['ZYCS'];
        $sql = "SELECT LIS_V1_req_master.barcode as TXM, LIS_V1_lab_report.sampleno as NO, LIS_V1_lab_report.specimen_name as YBLX, LIS_V1_lab_report.req_deptno as BQ, LIS_V1_lab_report.pat_diag as LCZD, LIS_V1_lab_result.result_ref as PYJG, LIS_V1_lab_result.rpt_itemname as XJMC, LIS_V1_lab_result.result1 as XJJL, LIS_V1_lab_med.medname as YMMC, LIS_V1_lab_resultmed.rad as YMJG, LIS_V1_lab_resultmed.medresult as MGD, LIS_V1_lab_resultmed.result6 as ZD, LIS_V1_lab_report.specimen_name as YMBW, LIS_V1_lab_report.report_user as SJYS, LIS_V1_req_master.report_user as JYY, LIS_V1_req_master.rechk_user as SHY, LIS_V1_req_master.sampled_dt as CJSJ, LIS_V1_req_master.recieve_dt as JSSJ, LIS_V1_req_master.report_dt as BGSJ, LIS_V1_lab_report.req_reason as EXAMINAIM, LIS_V1_lab_report.pat_no as AAA28, LIS_V1_lab_report.inp_id as ZYCS,CASE pat_typecode WHEN '1' THEN '门诊' WHEN '2' THEN '急诊' WHEN '3' THEN '住院' WHEN '4' THEN '体检' WHEN '0401' THEN '暂且未知' WHEN '11' THEN '暂且未知' WHEN '0402' THEN '暂且未知' WHEN '6' THEN '暂且未知' ELSE '其他' END as MZZYBZ,CASE pat_typecode WHEN '1' THEN '1' WHEN '3' THEN '2' WHEN '4' THEN '3' ELSE '9' END as STAYHOSPITALMODE from LIS_V1_req_master left join LIS_V1_lab_report on LIS_V1_req_master.report_id=LIS_V1_lab_report.reportid left join LIS_V1_lab_result on LIS_V1_lab_report.reportid=LIS_V1_lab_result.reportid left join LIS_V1_lab_resultmed on LIS_V1_lab_resultmed.reportid=LIS_V1_lab_result.reportid left join LIS_V1_lab_med on LIS_V1_lab_resultmed.medcode=LIS_V1_lab_med.medcode where LIS_V1_lab_result.Germflag!=0 AND LIS_V1_lab_report.pat_no='{$AAA28}' and LIS_V1_lab_report.inp_id={$ZYCS}";

        $test = self::queryMysqlSql($sql);
        if (empty($test)) {
            return [];
        }
        echo '本次同步数据量：' . count($test) . PHP_EOL;
        V_JMGS_YMresult::query()->where('ZYH', $zyh)->delete();
        foreach ($test as $item) {
            $item['ZYH'] = $zyh;
            V_JMGS_YMresult::query()->insert($item);
        }
        EsSaveService::vjmgsymresult($zyh);
        echo '药敏数据同步完成' . PHP_EOL;
    }


    public function pacs($zyh)
    {
        echo 'PACS数据同步开始' . PHP_EOL;

        $this->getMysqlConnect();
        $this->getConnect();
        $brry = ZY_BRRY::query()->where('ZYH', $zyh)->first();
        if (empty($brry)) {
            return [];
        }
        $brry = $brry->toArray();
        $AAA28 = $brry['AAA28'];
        $sql = "select ods.PACS_V1_STUDYINFO.STUDYID AS StudyUid, ods.PACS_V1_STUDYINFO.MAINNUM AS Patientid, ods.PACS_V1_STUDYINFO.DIAGID AS SQDH, ods.PACS_V1_STUDYINFO.BESPEAKTIME AS KDSJ, ods.PACS_V1_STUDYINFO.STUDYTIME AS JYSJ, CASE ods.PACS_V1_STUDYINFO.DEVICETYPEID WHEN 3 THEN '01'WHEN 4 THEN '02'WHEN 8 THEN '03'WHEN 1 THEN '04'WHEN 9 THEN '05'WHEN 11 THEN '05'WHEN 12 THEN '05'WHEN 5 THEN '06'WHEN 15 THEN '07'WHEN 25 THEN '07'WHEN 6 THEN '08'WHEN 24 THEN '09'WHEN 23 THEN '09'WHEN 0 THEN '10'WHEN 99 THEN '10'WHEN 8 THEN '11'ELSE '10'END AS ExamType, PACS_V1_DEVICETYPEINFO.DEVICETYPENAME AS ExamType_MC, ods.PACS_V1_STUDYINFO.DEPARTMENTID AS SQKS, ods.PACS_V1_STUDYINFO.DOCTORCODE AS SQRXM, ods.PACS_V1_STUDYINFO.CHKDEPTID AS JCKS, PACS_V1_PATIENTDIAGRPTINFO.OPERATETIME AS BGSJ, ods.PACS_V1_STUDYINFO.STUDYSCRIPTION AS JCBW, concat(replace(replace(ods.PACS_V1_STUDYINFO.STUDYSCRIPTION, '[', ''), ']', ''), PACS_V1_DEVICETYPEINFO.DEVICETYPENAME) AS JCMC, PACS_V1_PATIENTDIAGRPTINFO.REPORTDESCRIBE AS YXBX, PACS_V1_PATIENTDIAGRPTINFO.REPORTDIAGNOSE AS YXZD, 0 AS XGBZ, ods.PACS_V1_PATIENTINFO.INFEEPATIENTID AS BAH FROM ods.PACS_V1_STUDYINFO LEFT JOIN ods.PACS_V1_PATIENTINFO ON ods.PACS_V1_STUDYINFO.CHECKSERIALNUM=ods.PACS_V1_PATIENTINFO.CHECKSERIALNUM LEFT JOIN ods.PACS_V1_DEVICETYPEINFO ON ods.PACS_V1_STUDYINFO.DEVICETYPEID=ods.PACS_V1_DEVICETYPEINFO.DEVICETYPEID LEFT JOIN ods.PACS_V1_PATIENTDIAGRPTINFO ON ods.PACS_V1_STUDYINFO.DIAGRPTID=ods.PACS_V1_PATIENTDIAGRPTINFO.DIAGRPTID where ods.PACS_V1_PATIENTINFO.INFEEPATIENTID='{$AAA28}'";

        $test = self::queryMysqlSql($sql);
        echo '本次同步数据量：' . count($test) . PHP_EOL;
        if (empty($test)) {
            return [];
        }
        PACS::query()->where('ZYH', $zyh)->delete();
        foreach ($test as $item) {
            $item['ZYH'] = $zyh;
            PACS::query()->insert($item);
        }
        echo 'PACS数据同步完成' . PHP_EOL;
        EsSaveService::pacs($zyh);
        $this->getBasyBlsy($zyh);
    }
}
