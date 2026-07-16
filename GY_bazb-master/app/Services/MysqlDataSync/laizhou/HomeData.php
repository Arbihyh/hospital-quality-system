<?php

namespace App\Services\MysqlDataSync\laizhou;

use App\Model\Icu;
use App\Model\Yzb;
use Carbon\Carbon;
use App\Model\Bllb1;
use App\Model\Staff;
use App\Model\Bllb292;
use App\Model\Bllb303;
use App\Model\SM_SSAP;
use App\Model\Bllb294_45;
use App\Model\PatientAdd;
use App\Model\Bllb294_295;
use App\Model\DataSyncLog;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLSY;
use App\Model\EMR_BL_BLXG;
use App\Model\FeeDetailed;
use App\Model\PatientInfo;
use App\Model\MainDiagnosis;
use App\Model\MainOperation;
use App\Model\OtherDiagnosis;
use App\Model\PatientCostInfo;
use App\Model\V_JMGS_YMresult;
use App\Services\RadioService;
use App\Services\EsSaveService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\BlDataFormatService;
use App\Services\SqlServerProxyService;
use App\Console\Commands\ShizhongDataSync;

/**
 * 数据同步
 */
class HomeData
{

    public static $con;
    public static $sqlsrvService;
    public static $sqlsrvService92;
    public static $sqlsrvServic135;
    public static $sqlsrvYmService;

    public function getConnect()
    {
        if (self::$sqlsrvService) {
            return;
        }

        // SQLSRV_HOST=192.192.192.143
        // SQLSRV_USERNAME=cissa
        // SQLSRV_PASSWORD=lZrmyy@2020
        // SQLSRV_PORT=1433
        // SQLSRV_DATABASE=CISDB
        // 初始化SQL Server连接
        $sqlsrvConfig = [
            'host' => '192.192.192.143',
            'port' => env('SQLSRV_PORT', '1433'),
            'database' => 'CISDB',
            'username' => 'cissa',
            'password' => 'lZrmyy@2020'
        ];

        // 使用SqlServerProxyService创建连接
        $sqlsrvService = SqlServerProxyService::withConfig($sqlsrvConfig);

        // 测试SQL Server连接
        if (!$sqlsrvService->testConnection()) {
            Log::error('SQL Server数据库连接失败');
            throw new \Exception('SQL Server数据库连接失败');
        }

        // 将SQL Server服务实例保存到静态属性中，供后续使用
        self::$sqlsrvService = $sqlsrvService;
    }
    public function getConnect92()
    {
        if (self::$sqlsrvService92) {
            return;
        }

        // 初始化SQL Server连接
        $sqlsrvConfig = [
            'host' => '192.192.192.92',
            'port' => env('SQLSRV_PORT', '1433'),
            'database' => 'BAGL',
            'username' => 'lis',
            'password' => 'winning'
        ];

        // 使用SqlServerProxyService创建连接
        $sqlsrvService92 = SqlServerProxyService::withConfig($sqlsrvConfig);

        // 测试SQL Server连接
        if (!$sqlsrvService92->testConnection()) {
            Log::error('SQL Server数据库连接失败');
            throw new \Exception('SQL Server数据库连接失败');
        }

        // 将SQL Server服务实例保存到静态属性中，供后续使用
        self::$sqlsrvService92 = $sqlsrvService92;
    }
    public function getConnect135()
    {
        if (self::$sqlsrvServic135) {
            return;
        }

        // 初始化SQL Server连接
        $sqlsrvConfig = [
            'host' => '192.192.192.135',
            'port' => env('SQLSRV_PORT', '1433'),
            'database' => 'OREMR_LZSRMYY',
            'username' => 'wnzkjk',
            'password' => 'wnzkjk@20260615'
        ];

        // 使用SqlServerProxyService创建连接
        $sqlsrvServic135 = SqlServerProxyService::withConfig($sqlsrvConfig);

        // 测试SQL Server连接
        if (!$sqlsrvServic135->testConnection()) {
            Log::error('SQL Server数据库连接失败');
            throw new \Exception('SQL Server数据库连接失败');
        }

        // 将SQL Server服务实例保存到静态属性中，供后续使用
        self::$sqlsrvServic135 = $sqlsrvServic135;
    }

    public function getYmConnect()
    {
        if (self::$sqlsrvYmService) {
            return;
        }

        // 初始化莱州 LIS 药敏 SQL Server 连接
        $sqlsrvConfig = [
            'host' => '192.192.192.92',
            'port' => env('SQLSRV_PORT', '1433'),
            'database' => 'DBLIS50',
            'username' => 'lis',
            'password' => 'winning'
        ];

        $sqlsrvYmService = SqlServerProxyService::withConfig($sqlsrvConfig);

        if (!$sqlsrvYmService->testConnection()) {
            Log::error('SQL Server药敏数据库连接失败');
            throw new \Exception('SQL Server药敏数据库连接失败');
        }

        self::$sqlsrvYmService = $sqlsrvYmService;
    }

    /**
     * 获取Oracle连接
     */
    public function getOracleConnect()
    {
        if (self::$con) {
            return;
        }
        $username = 'HISUSER';
        $password = 'HISUSER';
        $connection = '172.16.0.116';
        $port = '1521';
        $tns = 'orcl';
        $con = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
        self::$con = $con;
    }

    /**
     * @param string $startTime
     * 清洗patient_info表中的是否编目字段
     */
    public function isCATA($startTime = '')
    {
        $this->getConnect92();
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
                echo $str;
                exit;
            }

            $startTimeStr = date("Y-m-d", $startTime);
            echo '时间：' . $startTimeStr . PHP_EOL;
            $startTime += 86400;

            // 从SQL Server获取数据，每次100条
            $sql = "SELECT FPRN AS MED_REC_ID FROM BAGL.dbo.TPATIENTVISIT WHERE FCYDATE >= '" . $startTimeStr . " 00:00:00' and FCYDATE <'" . $startTimeStr . " 23:59:59'";
            $result = self::$sqlsrvService92->query($sql);
            $zyhChunk = array_chunk($result, 100);
            foreach ($zyhChunk as $zyh) {
                $zyh = array_column($zyh, "MED_REC_ID");
                PatientInfo::query()->whereIn("MED_REC_ID", $zyh)->update(["IS_CATA" => 1]);
            }
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
        $this->getOracleConnect();

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
        $this->getConnect92();

        // 获取用户信息
        $patientData = $this->getPatientInfo($zyh, $startTime);
        if (!empty($patientData)) {
            foreach ($patientData as $patientInfo) {
                try {

                    $zyh = $patientInfo['MED_REC_ID'];
                    if ($startTime) {
                        echo $zyh . PHP_EOL;
                    }
                    // 医生签名
                    if (empty($type) || in_array('bl01', $type)) {
                        $this->addBLSY($zyh, $blbh);
                    }
                    // 患者主信息
                    if (empty($type) || in_array('patient_info', $type)) {
                        // $this->addPatientInfo($patientInfo);
                    }

                    // 医嘱
                    if (empty($type) || in_array('yzb', $type)) {
                        // $this->addYzb($zyh);
                    }

                    // 费用
                    if (empty($type) || in_array('fy', $type)) {
                        $this->addFy($zyh);
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
                        // $this->pacs($zyh);
                    }

                    // 首麻
                    if (empty($type) || in_array('sm', $type)) {
                        $this->SM_SSAP($zyh);
                    }

                    // 输血
                    if (empty($type) || in_array('shuxie', $type)) {
                        //                    $this->BLOOD_BLZK($zyh);
                    }
                } catch (\Throwable $th) {
                    //throw $th;
                    echo $th->getMessage() . PHP_EOL;
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
        $zyh = (string)$zyh;
        // 如果数据库链接字段为初始化则重新链接
        $this->getConnect();
        $this->getConnect92();
        $this->getOracleConnect();
        if ($zyh) {
            $szds = new ShizhongDataSync();
            $szds->ZY_BRRY($zyh, 0, 0, 0);

            $this->addPatientInfo($zyh);
        }

        if ($startTime) {
            echo $zyh . PHP_EOL;
        }
        // 医嘱
        if (empty($type) || in_array('yzb', $type)) {
            $this->addYzb($zyh);
        }

        // 医生签名
        if (empty($type) || in_array('bl01', $type)) {
            $this->addBLSY($zyh, $blbh);
        }
        // 费用
        if (empty($type) || in_array('fee_detailed', $type)) {
            $this->addFy($zyh);
        }

        // 药敏
        if (empty($type) || in_array('v_jmgs_ymresult', $type) || in_array('ym', $type)) {
            $this->addYM($zyh);
        }

        $this->pacs($zyh);
        $this->SM_SSAP($zyh);

        return true;
    }

    /**
     * 更新药敏数据
     *
     * @param string $zyh
     * @return bool
     */
    public function addYM($zyh = "")
    {
        if (!$zyh) {
            return false;
        }

        $this->getYmConnect();

        $rawZyh = $zyh;
        $queryZyh = str_replace("'", "''", $zyh);
        $sql = "SELECT ZYH, TXM, [NO], YBLX, BQ, LCZD, PYJG, XJMC, XJJL, YMMC, YMJG, YMBW, SJYS, JYY, SHY, CONVERT(VARCHAR(19), CJSJ, 120) AS CJSJ, CONVERT(VARCHAR(19), JSSJ, 120) AS JSSJ, CONVERT(VARCHAR(19), BGSJ, 120) AS BGSJ, EXAMINAIM FROM dbo.V_JMGS_YMresult WHERE ZYH = '{$queryZyh}'";
        $result = self::$sqlsrvYmService->query($sql);

        if (!is_array($result)) {
            Log::warning('莱州药敏数据查询结果异常', ['zyh' => $rawZyh, 'result' => $result]);
            return false;
        }

        V_JMGS_YMresult::query()->where('ZYH', $rawZyh)->delete();
        if (empty($result)) {
            DataSyncLog::addData(['zyh' => $rawZyh, 'content' => '同步药敏数据量', 'data_nums' => 0]);
            return false;
        }

        $insertData = [];
        foreach ($result as $val) {
            $insertData[] = [
                'ZYH' => $val['ZYH'] ?? '',
                'TXM' => $val['TXM'] ?? '',
                'NO' => $val['NO'] ?? '',
                'YBLX' => $val['YBLX'] ?? '',
                'BQ' => $val['BQ'] ?? '',
                'LCZD' => $val['LCZD'] ?? '',
                'PYJG' => $val['PYJG'] ?? '',
                'XJMC' => $val['XJMC'] ?? '',
                'XJJL' => $val['XJJL'] ?? '',
                'YMMC' => $val['YMMC'] ?? '',
                'YMJG' => $val['YMJG'] ?? '',
                'YMBW' => $val['YMBW'] ?? '',
                'SJYS' => $val['SJYS'] ?? '',
                'JYY' => $val['JYY'] ?? '',
                'SHY' => $val['SHY'] ?? '',
                'CJSJ' => $val['CJSJ'] ?? '',
                'JSSJ' => $val['JSSJ'] ?? '',
                'BGSJ' => $val['BGSJ'] ?? '',
                'EXAMINAIM' => $val['EXAMINAIM'] ?? '',
            ];
        }

        foreach (array_chunk($insertData, 1000) as $chunk) {
            V_JMGS_YMresult::query()->insert($chunk);
        }

        DataSyncLog::addData(['zyh' => $rawZyh, 'content' => '同步药敏数据量', 'data_nums' => count($insertData)]);

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
        $this->getConnect135();
        // 使用直接查询获取数据
        $sql = "SELECT ZYH, ZYCS, YZDM, YZ, SZDM, SZ, SSXH, CONVERT(VARCHAR(19), SSRQ, 120) AS SSRQ, SSKS_MC, SSKS, SQDH, RZDM, RZ, MZYW, MZYS_MC, MZYS, MZXG as SMMZXG, MZMC, CONVERT(VARCHAR(19), MZKSSJ, 120) AS MZKSSJ, CONVERT(VARCHAR(19), MZJSSJ, 120) AS MZJSSJ, MZDM, CONVERT(VARCHAR(19), JSSJ, 120) AS JSSJ, ICD9_SSLB, ICD9_SSCZMC, ICD9_SSCZBM, BAH AS ZYHM FROM dbo.SM_SSAP WHERE ZYH='{$zyh}'";
        $ind = 0;
        $result = [];
        while ($ind < 3) {
            $ind++;
            try {
                $result = self::$sqlsrvServic135->query($sql);
                break;
            } catch (\Exception $e) {
                continue;
            }
        }
        SM_SSAP::query()->where("ZYH", $zyh)->delete();
        SM_SSAP::query()->insert($result);
    }

    /**
     * 解析pacs数据
     *
     * @param string $data
     * @return array
     */
    public function parsePacsData($data)
    {

        if (!is_string($data)) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // 用正则给所有类似  2026-01-27 15:29:34.063 和 2026-01-27 00:00:00.0  的时间戳加上引号
        $resContent = preg_replace('/(:\s*)(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?)/', '$1"$2"', $data);

        $jsonData = json_decode($resContent, true);
        if ($jsonData === null) {
            // 方法1: 将换行符替换为 \n 转义字符
            $resContent = preg_replace('/\r?\n/', '\\n', $resContent);
            $resContent = preg_replace('/\t/', '\\n', $resContent);

            // 方法2: 如果方法1不行，尝试更严格的修复
            if (json_decode($resContent) === null) {
                // 移除所有换行符和多余的空格
                $resContent = preg_replace('/\s+/', ' ', $resContent);
            }
            $jsonData = json_decode($resContent, true);
        }

        if ($jsonData == null) {
            $testJsonString = $resContent;
            $testArray = json_decode($testJsonString, true);
            if ($testArray === null && preg_match('/^\{[^\{]*data"\s*:\s*\[/', $testJsonString)) {
                // 解析失败，但结构像注释中的情形，可能有转义和嵌套字符串问题
                // 首先去除多余斜杠（如 \"）
                $tmp = preg_replace('/\\\\(["\\\\\/bfnrt])/', '$1', $testJsonString);
                // 再次尝试解析
                $testArray = json_decode($tmp, true);
                if ($testArray !== null) {
                    $jsonData = $testArray;
                }
            } elseif ($testArray !== null) {
                $jsonData = $testArray;
            }
        }
        return $jsonData;
    }

    public function pacs($zyh)
    {

        if (strpos($zyh, '_') !== false) {
            return false;
        }
        DataSyncLog::addData(['zyh' => $zyh, 'content' => '同步pacs数据开始']);
        $sqlsrvConfig = [
            'host' => '192.192.192.145',
            'port' => env('SQLSRV_PORT', '1433'),
            'database' => 'MIIS60pro',
            'username' => 'pacs',
            'password' => 'sql@2012'
        ];
        $sqlsrvService92 = SqlServerProxyService::withConfig($sqlsrvConfig);
        if (!$sqlsrvService92->testConnection()) {
            Log::error('SQL Server数据库连接失败');
            throw new \Exception('SQL Server数据库连接失败');
        }
        $sql = "exec rsp_nhzkjk_RisReport {$zyh}";
        $data = $sqlsrvService92->query($sql);
        $jsonData = $this->parsePacsData($data);

        if (!empty($jsonData['data'])) {
            $data = $jsonData['data'];
            DataSyncLog::addData(['zyh' => $zyh, 'content' => '同步pacs数据量', 'data_nums' => count($data)]);
            $this->savePacsData($data);
        }

        $sqlsrvConfig['database'] = 'MIIS60PRO_US';
        $sqlsrvService92 = SqlServerProxyService::withConfig($sqlsrvConfig);
        if (!$sqlsrvService92->testConnection()) {
            Log::error('SQL Server数据库连接失败');
            throw new \Exception('SQL Server数据库连接失败');
        }
        $sql = "exec rsp_nhzkjk_RisReport {$zyh}";
        $data = $sqlsrvService92->query($sql);
        $jsonData = $this->parsePacsData($data);

        if (!empty($jsonData['data'])) {
            $data = $jsonData['data'];
            DataSyncLog::addData(['zyh' => $zyh, 'content' => '同步pacs数据量', 'data_nums' => count($data)]);
            $this->savePacsData($data);
        }
    }

    function savePacsData($data)
    {
        if (empty($data)) {
            return;
        }
        foreach ($data as $v) {
            $insert = [
                "ZYH"       => $v['JZLSH'] ?? "",
                "StudyUid"  => $v['StudyUid'] ?? '',
                "JZLSH"     => $v['ZYH'] ?? '',
                "MZZYBZ"    => $v['MZZYBZ'] ?? '',
                "BRXM"      => $v['BRXM'] ?? '',
                "BRXB"      => $v['BRXB'] ?? '',
                "PatientID" => $v['PatientID'] ?? '',
                "JCXMDM"    => $v['JCXMDM'] ?? '',
                "SQDH"      => $v['SQDH'] ?? '',
                "KDSJ"      => $v['KDSJ'] ?? '',
                "JYSJ"      => $v['JYSJ'] ?? '',
                "ExamType"  => $v['ExamType'] ?? '',
                "SQKS"      => $v['SQKS'] ?? '',
                "SQKSMC"    => $v['SQKSMC'] ?? '',
                "SQRGH"     => $v['SQRGH'] ?? '',
                "SQRXM"     => $v['SQRXM'] ?? '',
                "JCKS"      => $v['JCKS'] ?? '',
                "JCKSMC"    => $v['JCKSMC'] ?? '',
                "JCYSGH"    => $v['JCYSGH'] ?? '',
                "JCYS"      => $v['JCYS'] ?? '',
                "BGSJ"      => $v['BGSJ'] ?? '',
                "BGRQ"      => $v['BGRQ'] ?? '',
                "BGRGH"     => $v['BGRGH'] ?? '',
                "BGRXM"     => $v['BGRXM'] ?? '',
                "SHRGH"     => $v['SHRGH'] ?? '',
                "SHRXM"     => $v['SHRXM'] ?? '',
                "JCBW"      => $v['JCBW'] ?? '',
                "BWACR"     => $v['BWACR'] ?? '',
                "JCMC"      => $v['JCMC'] ?? '',
                "YXBX"      => $v['YXBX'] ?? '',
                "YXZD"      => $v['YXZD'] ?? '',
                "SFYYY"     => $v['SFYYY'] ?? '',
                "XGBZ"      => $v['XGBZ'] ?? '',
            ];
            if (!empty($insert['BGSJ'])) {
                $insert['BGSJ'] = date('Y-m-d H:i:s', strtotime($insert['BGSJ']));
            }
            DB::table("PACS")->updateOrInsert(["StudyUid" => $v['StudyUid']], $insert);
        }
    }

    /**
     * 获取用户主信息
     *
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



    public function addPatientInfo($zyh = "")
    {
        // 从SQL Server获取数据，每次100条
        $sql = "SELECT FPRN AS AAA28, FPRN AS MED_REC_ID, FNAME AS AAA01, FSEXBH AS AAA02C, FORMAT(FBIRTHDAY, 'yyyy-MM-dd HH:mm:ss') AS AAA03, FCOUNTRY AS AAA05C, FRYTZ AS AAA42, FCSTZ AS AEN01, FNATIONALITYBH AS AAA06C, FIDCARD AS AAA07, FSTATUSBH AS AAA08C, FLYFSBH AS AEM01C, FORMAT(DATEADD(SECOND, DATEDIFF(SECOND, 0, TRY_CAST(FRYTIME AS TIME)), FRYDATE), 'yyyy-MM-dd HH:mm:ss') AS AAB01, FORMAT(DATEADD(SECOND, DATEDIFF(SECOND, 0, TRY_CAST(FCYTIME AS TIME)), FCYDATE), 'yyyy-MM-dd HH:mm:ss') AS AAC01, FCYDEPT AS AAC11N, FDAYS AS AAC04, FSUM1 AS ADA01, FZFJE AS ADA0101, FTIMES AS AAA29, FRYTJBH AS AAB06C, FZZDOCTBH AS AEE03_CODE, FZYDOCTBH AS AEE04_CODE, FSEX AS AAA02C_MC, CAST(STUFF(FAGE, 1, 1, '') AS INT) AS AAA04, FLYFS AS AEM01C_MC, FFBBHNEW AS AAA26C, FFBNEW AS AAA26C_MC, FCYTYKH AS AAC02C, FCYTYKH AS BQ_CODE FROM TPATIENTVISIT where FPRN = '{$zyh}'";

        $result = self::$sqlsrvService92->query($sql);

        // 如果没有数据了，退出循环
        if (empty($result)) {
            return false;
        }

        // 处理获取到的数据
        foreach ($result as $item) {
            $insertData = [
                'AAA28' => $item['AAA28'] ?? '',
                'MED_REC_ID' => $item['MED_REC_ID'] ?? '',
                'AAA01' => desensitize($item['AAA01'], 1, 1, '*') ?? '',
                'AAA02C' => $item['AAA02C'] ?? '',
                'AAA03' => $item['AAA03'] ?? '',
                'AAA05C' => $item['AAA05C'] ?? '',
                'AAA42' => $item['AAA42'] ?? '',
                'AEN01' => $item['AEN01'] ?? '',
                'AAA06C' => $item['AAA06C'] ?? '',
                'AAA07' => desensitize($item['AAA07'], 6, 8, '*') ?? '',
                'AAA08C' => $item['AAA08C'] ?? '',
                'AEM01C' => $item['AEM01C'] ?? '',
                'AAB01' => $item['AAB01'] ?? '',
                'AAC01' => $item['AAC01'] ?? '',
                'AAC11N' => $item['AAC11N'] ?? '',
                'AAC04' => $item['AAC04'] ?? '',
                'ADA01' => $item['ADA01'] ?? '',
                'ADA0101' => $item['ADA0101'] ?? '',
                'AAA29' => $item['AAA29'] ?? '',
                'AAB06C' => $item['AAB06C'] ?? '',
                'AEE03_CODE' => $item['AEE03_CODE'] ?? '',
                'AEE04_CODE' => $item['AEE04_CODE'] ?? '',
                'AAA02C_MC' => $item['AAA02C_MC'] ?? '',
                'AAA04' => $item['AAA04'] ?? '',
                'AEM01C_MC' => $item['AEM01C_MC'] ?? '',
                'AAA26C' => $item['AAA26C'] ?? '',
                'AAA26C_MC' => $item['AAA26C_MC'] ?? '',
                'AAC02C' => $item['AAC02C'] ?? '',
                'BQ_CODE' => $item['BQ_CODE'] ?? '',
                'IS_CATA' => 1
            ];
            // 使用AAA28作为唯一标识进行更新或插入
            PatientInfo::query()->updateOrInsert(
                ['MED_REC_ID' => $item['MED_REC_ID']],
                $insertData
            );
        }

        return true;
    }

    /**
     * 获取手术申请
     *
     * @param  $ZYH
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
     *
     * @param  $ZYH
     * @return array
     */
    public function getFyData($ZYH)
    {

        DataSyncLog::addData(['zyh' => $ZYH, 'content' => '同步费用数据量开始']);
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
        DataSyncLog::addData(['zyh' => $ZYH, 'content' => '同步费用明细数据量', 'data_nums' => count($result)]);
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
     *
     * @param  $ZYH
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
     *
     * @param  $ZYH
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
     *
     * @param  $ZYH
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
     *
     * @param  $ZYH
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
     *
     * @param  $ZYH
     * @return array
     */
    public function addYzb($ZYH)
    {
        DataSyncLog::addData(['zyh' => $ZYH, 'content' => '同步医嘱开始']);
        $sql = "SELECT ZYH, YZBXH || YZZT AS YZBXH, BRKS, BRBQ, YDYZLB, XMLB, KZKS, KZYS, TO_CHAR(KZSJ,'YYYY-MM-DD HH24:MI:SS') as KZSJ, YZMC, SYPC, GYTJ, JJYZ, BLYZ, TO_CHAR(TZSJ,'YYYY-MM-DD HH24:MI:SS') as TZSJ, TZYS, YZZT, ZXZT, ZTBZ, TO_CHAR(XZJDSJ,'YYYY-MM-DD HH24:MI:SS') as XZJDSJ, TZQRGH, TO_CHAR(TZQRSJ,'YYYY-MM-DD HH24:MI:SS') as TZQRSJ FROM HISUSER.V_NHZK_ZYYZ A WHERE ZYH='{$ZYH}'";
        $sql = $sql . " AND TO_DATE(KZSJ,'YYYY-MM-DD HH24:MI:SS') >= TRUNC(SYSDATE) - 3";
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            // KZSJ 和 ZTSJ 为一天的是临时医嘱，其他长期医嘱
            if (!empty($row['KZSJ']) && !empty($row['TZSJ'])) {
                $kzsj = substr($row['KZSJ'], 0, 10);
                $ztsj = substr($row['TZSJ'], 0, 10);
                if ($kzsj == $ztsj) {
                    $row['YZQX'] = 2;
                } else {
                    $row['YZQX'] = 1;
                }
            } else {
                $row['YZQX'] = 0;
            }
            $result[] = $row;
        }
        DataSyncLog::addData(['zyh' => $ZYH, 'content' => '同步医嘱数据量', 'data_nums' => count($result)]);

        Yzb::query()->where('ZYH', '=', (string)$ZYH)->delete();
        $params = [
            'index' => 'yzb_2023',
            'body' => [
                'query' => [
                    'term' => [
                        'ZYH' => $ZYH
                    ]
                ]
            ]
        ];
        app('es')->deleteByQuery($params);
        if (!empty($result)) {
            $chunkList = array_chunk($result, 100);
            foreach ($chunkList as $value) {
                Yzb::query()->insert($value);
            }
        }
        RadioService::filterField((string)$ZYH);
        return true;
    }


    public function addFy($zyh = '')
    {
        DataSyncLog::addData(['zyh' => $zyh, 'content' => '同步费用明细开始']);
        // 将sql语句改为分页获取，一次获取100条
        $sql = "SELECT ZYH as AAA28, FYXH, FYMC, JFRQ, FYSL, FYDJ, ZJE, FYKS, JLXH, YSGH, YPLX, ZXKS, YEPB, ZLXZ FROM HISUSER.V_NHZK_ZYFY WHERE ZYH='{$zyh}'";
        $sql = $sql . " AND TO_DATE(JFRQ,'YYYY-MM-DD') >= TRUNC(SYSDATE) - 3";
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        // 批量添加数据
        if (!empty($result)) {
            foreach ($result as &$item) {
                if (!empty($item['JFRQ'])) {
                    $item['JFRQ'] = date('Y-m-d', strtotime($item['JFRQ']));
                }
            }
            FeeDetailed::query()->where('AAA28', (string)$zyh)->delete();
            $chunkList = array_chunk($result, 100);
            foreach ($chunkList as $value) {
                FeeDetailed::query()->insert($value);
            }
        }
        DataSyncLog::addData(['zyh' => $zyh, 'content' => '同步费用明细结束']);
        return true;
    }


    public function formatBL01BLMC306($bl01 = [])
    {

        $HJNR = $bl01['HJNR'];
        $HJNR = str_replace("：", ":", $HJNR);
        preg_match("/记录日期:(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", $HJNR, $timeMatches);
        $timePrefix = !empty($timeMatches[1]) ? $timeMatches[1] : '';
        // 更新blmc
        if (!empty($timePrefix)) {
            EMR_BL_BL01::query()->where('BLBH', (string)$bl01['BLBH'])->update(['BLMC' => $timePrefix . ' 手术记录 {' . $bl01["BLMC"] . '}', 'ZXSJ' => $timePrefix]);
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
            EMR_BL_BL01::query()->where('BLBH', (string)$bl01['BLBH'])->update(['operation_time' => strtotime($formattedTime)]);
        }
    }

    /**
     * @param array $bl01
     * 格式化bl01表中的病例名称
     */
    public function formatBL01BLMC294($bl01 = [])
    {

        $blmc = '';
        $HJNR = trim($bl01['HJNR']);
        preg_match("/^(\d{4}-\d{2}-\d{2}\s*\d{2}:\d{2})\s*(\S+)/", $HJNR, $timeMatches);
        if (!empty($timeMatches)) {
            if (!empty($timeMatches[1])) {
                $blmc = $timeMatches[1];
            }
            // 更新blmc
            if (!empty($timeMatches[2]) && mb_strlen($timeMatches[2]) <= 15) {
                $blmc .= $timeMatches[2];
            }
            $blmc .= ' {' . $bl01["BLMC"] . '} ';
            EMR_BL_BL01::query()->where('BLBH', (string)$bl01['BLBH'])->update(['BLMC' => $blmc, 'ZXSJ' => $timeMatches[1]]);
        }
        if (empty($blmc)) {
            if (preg_match('/FC\d+\s+([^\s]+)\s+\d+/', $HJNR, $m)) {
                $blmc = $m[1];
                $blmc .= ' {' . $bl01["BLMC"] . '} ';
                EMR_BL_BL01::query()->where('BLBH', (string)$bl01['BLBH'])->update(['BLMC' => $blmc]);
            }
        }
    }


    /**
     * @param  string $zyh
     * @param  string $blbh
     * @param  int $isRecordLog
     * @return bool
     * 同步病程记录相关数据
     */
    public function addBLSY($zyh = "", $blbh3 = "", $isRecordLog = 1)
    {
        // 住院号如果不是数字则不能获取病程记录
        if (!is_numeric($zyh)) {
            return false;
        }
        if ($isRecordLog == 1) {
            DataSyncLog::addData(['zyh' => $zyh, 'content' => '4、数据病程记录开始']);
        }
        try {
            $sql = "SELECT BLBH, ZYH, BLLB, BLMC, MBLB, ZXSJ, CJSJ, WCSJ, SXYS, BRKS, CJKS, BLZT, JLXH, XGGH, XGSJ, HJNR FROM dbo.NH_V_JMGS_BASY_QBL_BC WHERE BLZT <> 9 and ZYH = '{$zyh}'";
            if (!empty($blbh3)) {
                $sql .= " and BLBH='{$blbh3}'";
            }

            $data = self::$sqlsrvService->query($sql);

            $sql = "SELECT BLBH, ZYH, BLLB, BLMC, MBLB, ZXSJ, CJSJ, WCSJ, SXYS, BRKS, CJKS, BLZT, JLXH, XGGH, XGSJ, HJNR FROM dbo.NH_V_JMGS_BASY_QBL WHERE BLZT <> 9 and ZYH = '{$zyh}'";
            if (!empty($blbh3)) {
                $sql .= " and BLBH='{$blbh3}'";
            }

            $datav2 = self::$sqlsrvService->query($sql);
            foreach ($datav2 as $item) {
                $item['BLBH'] = $item['BLBH'] . '_v2';
                $data[] = $item;
            }
        } catch (\Exception $e) {
            Log::error('同步病程记录相关数据失败：' . $e->getMessage());
            return false;
        }
        $bldf = new BlDataFormatService();

        // BLLB_BF/MBLB_BF到BLLB/MBLB的映射
        $bllbMap = [
            "EMR.11.01" => ["BLLB" => 2000001, "MBLB" => 2000001],
            "EMR.02.01" => ["BLLB" => 292, "MBLB" => 292],
            "EMR.02.04" => ["BLLB" => 292, "MBLB" => 292],
            "EMR.08.01" => ["BLLB" => 1, "MBLB" => 1],
            "EMR.08.02" => ["BLLB" => 288, "MBLB" => 288],
            "EMR.02.02" => ["BLLB" => 18, "MBLB" => 20],
            "EMR.02.03" => ["BLLB" => 18, "MBLB" => 21],
            "EMR.04.03" => ["BLLB" => 294, "MBLB" => 50],
            "EMR.04.21" => ["BLLB" => 294, "MBLB" => 50],
            "EMR.04"    => ["BLLB" => 294, "MBLB" => 50],
            "EMR.04.01" => ["BLLB" => 294, "MBLB" => 296],
            "EMR.04.02" => ["BLLB" => 294, "MBLB" => 296],
            "EMR.04.11" => ["BLLB" => 294, "MBLB" => 32],
            "EMR.04.12" => ["BLLB" => 294, "MBLB" => 296],
            "EMR.04.13" => ["BLLB" => 294, "MBLB" => 296],
            "EMR.04.17" => ["BLLB" => 294, "MBLB" => 42],
            "EMR.04.18" => ["BLLB" => 294, "MBLB" => 42],
            "EMR.04.19" => ["BLLB" => 294, "MBLB" => 45],
            "EMR.04.04" => ["BLLB" => 329, "MBLB" => 32901],
            "EMR.04.05" => ["BLLB" => 329, "MBLB" => 32901],
            // "EMR.10"    => ["BLLB" => 329, "MBLB" => 32901],
            "EMR.11"    => ["BLLB" => 329, "MBLB" => 32901],
            "EMR.04.09" => ["BLLB" => 294, "MBLB" => 27],
            "EMR.04.07" => ["BLLB" => 294, "MBLB" => 30],
            "EMR.04.06" => ["BLLB" => 294, "MBLB" => 30],
            "EMR.04.08" => ["BLLB" => 294, "MBLB" => 26],
            "EMR.04.15" => ["BLLB" => 294, "MBLB" => 82],
            "EMR.05.01" => ["BLLB" => 294, "MBLB" => 82],
            "EMR.04.16" => ["BLLB" => 294, "MBLB" => 82],
            "EMR.05.03" => ["BLLB" => 294, "MBLB" => 82],
            "EMR.06.01" => ["BLLB" => 101, "MBLB" => 101],
            "EMR.06.02" => ["BLLB" => 101, "MBLB" => 101],
            "EMR.04.20" => ["BLLB" => 43, "MBLB" => 4302],
            "EMR.09.02" => ["BLLB" => 43, "MBLB" => 4302],
            "EMR.04.14" => ["BLLB" => 43, "MBLB" => 44],
            "EMR.09.01" => ["BLLB" => 43, "MBLB" => 44],
            "EMR.09.03" => ["BLLB" => 43, "MBLB" => 44],
            "EMR.05.02" => ["BLLB" => 303, "MBLB" => 74],
            "EMR.05.05" => ["BLLB" => 303, "MBLB" => 30375],
            "EMR.05.04" => ["BLLB" => 303, "MBLB" => 76],
            "EMR.09.04" => ["BLLB" => 303, "MBLB" => 304],
            "EMR.05.06" => ["BLLB" => 303, "MBLB" => 76],
            "EMR.04.10" => ["BLLB" => 303, "MBLB" => 30304],
            "EMR.07.02" => ["BLLB" => 329, "MBLB" => 8],
            "EMR.07.08" => ["BLLB" => 329, "MBLB" => 77],
            "EMR.07.03" => ["BLLB" => 329, "MBLB" => 59],
            "EMR.07.01" => ["BLLB" => 329, "MBLB" => 32901],
            "EMR.07.11.24" => ["BLLB" => 329, "MBLB" => 32901],
            "EMR.07.11" => ["BLLB" => 329, "MBLB" => 32901],
            "EMR.07.15" => ["BLLB" => 329, "MBLB" => 32901],
            "EMR.07.05" => ["BLLB" => 329, "MBLB" => 60],
            "EMR.07.06" => ["BLLB" => 329, "MBLB" => 60],
            "EMR.07.07" => ["BLLB" => 329, "MBLB" => 60],
            "EMR.07.14" => ["BLLB" => 329, "MBLB" => 60],
            "EMR.07.09" => ["BLLB" => 329, "MBLB" => 85],
            "EMR.04.23" => ["BLLB" => 34, "MBLB" => 3401],
            "EMR.15.01"     => ["BLLB" => 303, "MBLB" => 40],
            "EMR.15.02"     => ["BLLB" => 303, "MBLB" => 30303],
            // 下面是MBLB_BF的映射
            "WM002"     => ["BLLB" => 294, "MBLB" => 295],
        ];

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
                    EsSaveService::deleteBl01ByBLBH((string)$blbh['BLBH']);
                }
            }
        } else {
            EsSaveService::deleteBl01ByBLBH((string)$blbh3);
        }

        $sj = PatientInfo::query()->where('MED_REC_ID', $zyh)->first(['MED_REC_ID', 'AAB01', 'AAC01']);
        $staff = Staff::query()->get()->toArray();
        $staff = array_column($staff, "name", "code");

        foreach ($data as $item) {
            if ($isRecordLog == 1) {
                DataSyncLog::addData(['zyh' => $zyh, 'content' => '8、同步病程记录：' . $item['BLBH']]);
            }

            $BLLB = $bllbMap[$item["MBLB"]]["BLLB"] ?? $bllbMap[$item["BLLB"]]["BLLB"] ?? '';
            $MBLB = $bllbMap[$item["MBLB"]]["MBLB"] ?? $bllbMap[$item["BLLB"]]["MBLB"] ?? '';

            $result = [
                'JZHM' => $item['ZYH'] ?? '',
                'BLBH' => $item['BLBH'] ?? '',
                'BLLB' => $BLLB,
                'MBLB' => $MBLB,
                'BLMC' => $item['BLMC'] ?? '',
                'AAB01' => $sj->AAB01 ?? '',
                'AAC01' => $sj->AAC01 ?? '',
                'ZXSJ' => $item['ZXSJ'] ?? '',
                'YWSJ' => $item['ZXSJ'] ?? '',
                'CJSJ' => $item['CJSJ'] ?? '',
                'WCSJ' => $item['WCSJ'] ?? '',
                'SXYS' => $item['SXYS'] ?? '',
                'BRKS' => $item['BRKS'] ?? '',
                'CJKS' => $item['CJKS'] ?? '',
                'BLZT' => $item['BLZT'] ?? '',
                'first_blsy_time' => $item['ZXSJ'] ?? '',
            ];

            $temp = [
                'BLBH' => $item['BLBH'] ?? '',
                'JLXH' => $item['JLXH'] ?? '',
                'XGGH' => $item['XGGH'] ?? '',
                'XGSJ' => $item['XGSJ'] ?? '',
                'HJNR' => $item['HJNR'] ?? '',
            ];

            //处理死亡记录
            if (strpos($result['BLMC'], "死亡记录") !== false) {
                $result['BLLB'] = 288;
                $result['MBLB'] = 288;
            }
            $bl01Data = $result;
            EMR_BL_BL01::query()->updateOrInsert(['BLBH' => (string)$item['BLBH']], $result); //插入或更新病历数据
            EMR_BL_BLXG::query()->updateOrInsert(['BLBH' => (string)$item['BLBH']], $temp); //插入或更新BLXG数据
            EMR_BL_BLSY::query()->where('BLBH', (string) $item['BLBH'])->delete();
            EMR_BL_BLSY::query()->insert(["SYYS" => $item['SXYS'] ?? '', "BLBH" => (string) $item['BLBH']]);

            $bl01Data['HJNR'] = $temp['HJNR'] ?? '';

            // 检查HJNR是否为空并记录日志
            if (empty($bl01Data['HJNR'])) {
                DataSyncLog::addData(['zyh' => $zyh, 'content' => '病历HJNR内容为空，BLBH：' . $item['BLBH']]);
            }

            if ($bl01Data["BLLB"] == 294 || $bl01Data["BLLB"] == 43) {
                $this->formatBL01BLMC294($bl01Data);
            }
            if ($bl01Data["BLLB"] == 303) {
                $this->formatBL01BLMC306($bl01Data);
            }
            if ($bl01Data["BLLB"] == 82) {
                $this->formatBL01BLMC82($bl01Data);
            }
            $bldf->insertData([], $result["MBLB"], $bl01Data);
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
                in_array($item['MBLB'], [295, 129])
                || ($item['BLLB'] == 294 && (strpos($str, '病例特点') !== false || strpos($str, '鉴别诊断') !== false))
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
            EMR_BL_BL01::query()->updateOrInsert(['BLBH' => (string)$item['BLBH']], $result);
            EMR_BL_BLXG::query()->updateOrInsert(['BLBH' => (string)$item['BLBH']], $temp);
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
     * 住院病历（会诊意见）\住院病历（会诊申请）
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

    /**
     * 移除 JSON 字符串中的控制字符
     * @param string $json
     * @return string
     */
    private function removeControlCharacters($json)
    {
        // JSON 规范只允许这些控制字符：\n (0x0A), \r (0x0D), \t (0x09)
        // 移除其他所有控制字符：\x00-\x08, \x0B-\x0C, \x0E-\x1F, \x7F

        // 使用 preg_replace_callback 来正确处理，保留允许的控制字符
        $json = preg_replace_callback(
            '/[\x00-\x1F\x7F]/',
            function ($matches) {
                $ord = ord($matches[0]);
                // 保留允许的控制字符：\t (9), \n (10), \r (13)
                if ($ord === 9 || $ord === 10 || $ord === 13) {
                    return $matches[0];
                }
                // 移除其他所有控制字符
                return '';
            },
            $json
        );

        return $json;
    }

    /**
     * 修复 JSON 字符串中常见的格式问题
     * @param string $json
     * @return string
     */
    private function fixJsonString($json)
    {
        // 移除 BOM
        $json = preg_replace('/^\xEF\xBB\xBF/', '', $json);

        // 确保编码正确
        if (!mb_check_encoding($json, 'UTF-8')) {
            $json = mb_convert_encoding($json, 'UTF-8', mb_detect_encoding($json) ?: 'UTF-8');
        }

        // 使用更安全的方法清理控制字符：逐字符处理，保护多字节字符
        $result = '';
        $len = strlen($json);
        $i = 0;

        while ($i < $len) {
            $byte = ord($json[$i]);

            if ($byte < 0x80) {
                // ASCII 字符
                // 允许的控制字符：\t (9), \n (10), \r (13)
                // 允许的可打印字符：32-126
                if (($byte >= 32 && $byte <= 126) || $byte === 9 || $byte === 10 || $byte === 13) {
                    $result .= $json[$i];
                }
                // 其他控制字符（0-8, 11-12, 14-31, 127）被移除
                $i++;
            } else {
                // 多字节字符（UTF-8），需要完整提取
                $charLen = 0;
                if (($byte & 0xE0) === 0xC0) {
                    $charLen = 2;
                } elseif (($byte & 0xF0) === 0xE0) {
                    $charLen = 3;
                } elseif (($byte & 0xF8) === 0xF0) {
                    $charLen = 4;
                }

                if ($charLen > 0 && $i + $charLen <= $len) {
                    $char = substr($json, $i, $charLen);
                    // 验证是否是有效的 UTF-8
                    if (mb_check_encoding($char, 'UTF-8')) {
                        $result .= $char;
                    }
                    $i += $charLen;
                } else {
                    // 无效字符，跳过
                    $i++;
                }
            }
        }

        return trim($result);
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
