<?php

namespace App\Console\Commands\Patient;

use App\Model\SSSQ;
use Illuminate\Console\Command;

class PatientSSSQ extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'patient_sssq {startTime?} {endTime?}';

    public static $con;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步患者手术申请信息';

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
        echo "同步患者手术申请信息开始：" . date('Y-m-d H:i:s') . "\n";
        $this->getConnect(); //建立数据库连接
        $startTime = $this->argument('startTime');
        $endTime = $this->argument('endTime');
        $s = time();
        $total = 0;
        $page = 1;
        $pageSize = 1000;
        $startTime = empty($startTime) ? date('Y-m-d 00:00:00', time() - 30 * 3600) : date('Y-m-d 00:00:00', strtotime($startTime));
        $endTime = empty($endTime) ? date('Y-m-d 23:59:59', time()) : date('Y-m-d 23:59:59', strtotime($endTime));
        while (true) {
            $PatientData = \App\Model\PatientInfo::query()
                ->whereBetween('updated_at', [$startTime, $endTime])
                ->paginate($pageSize, ['MED_REC_ID'], 'page', $page)
                ->toArray();
            $PatientData = $PatientData['data'];
            if (empty($PatientData)) {
                break;
            }
            echo "page:" . $page . " - " . date('Y-m-d H:i:s') . PHP_EOL;
            $zyhArray = array_unique(array_column($PatientData, 'MED_REC_ID'));
            foreach ($zyhArray as $v) {
                $data = $this->getData($v);
                if (!empty($data)) {
                    echo $v . " - " . $startTime . "共" . count($data) . "条数据" . "\n";
                    $total += count($data);
                    $this->addData($data);
                }
            }
            $page++;
        }

        $e = time();
        $timeDifference = $e - $s;
        $totalHours = floor($timeDifference / 3600); // 3600秒 = 1小时
        $totalMinutes = floor(($timeDifference % 3600) / 60); // 剩余分钟
        echo "一共同步" . $total . "条数据；用时" . $totalHours . "小时" . $totalMinutes . "分钟" . "\n";
        echo "同步患者手术申请信息开始：" . date('Y-m-d H:i:s') . "\n";
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

    public function getData($ZYH)
    {
        // 查询 手术申请
        $sql = "SELECT A.*,to_char(SQRQ,'yyyy-mm-dd hh24:mi:ss') as SQRQ,to_char(SSRQ,'yyyy-mm-dd hh24:mi:ss') as SSRQ
                FROM PORTAL_HIS.BTF_SM_SSSQ A
                WHERE ZYH=" . $ZYH;
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }

        return $result;
    }

    public function addData($data)
    {
        foreach ($data as $val) {
            $insertData = [
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
                'JJBZ' => $val['JZBZ'] ?? '',
                'BRLY' => $val['BRLY'] ?? '',
                'BRID' => $val['BRID'] ?? null,
                'SSLX' => $val['SSLX'] ?? null,
                'JJYZ' => $val['JJYZ'] ?? null,
                'TZBH' => $val['TZBH'] ?? null,
                'DSG_OPERATION' => $val['DSG_OPERATION'] ?? '',
                'DSG_LDR_TIME' => $val['DSG_LDR_TIME'] ?? '',
                'FJHZCSS' => $val['FJHZCSS'] ?? '', //是否是非计划再次手术
            ];
            SSSQ::query()->updateOrInsert(['SQDH' => $insertData['SQDH'], 'ZYH' => $insertData['ZYH']], $insertData);
        }
    }
}
