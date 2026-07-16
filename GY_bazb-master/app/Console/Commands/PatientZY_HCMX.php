<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PatientZY_HCMX extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'patient_zy_hcmx {startTime?} {endTime?}';

    public static $con;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步患者护士分床信息';

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
        echo "同步患者护士分床信息开始：" . date('Y-m-d H:i:s') . "\n";
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
            //由于patient_info表脚本执行速度快，更新时间不会隔天
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
                    echo $v . " - " . $startTime . "共" . count($data) . "条数据" . " - " . date('Y-m-d H:i:s') . "\n";
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
        echo "同步患者护士分床信息结束：" . date('Y-m-d H:i:s') . "\n";
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
     * 获取护士分床信息
     * @param $ZYH
     * @return array
     */
    public function getData($ZYH)
    {
        if (!self::$con) {
            $this->getConnect();
        }
        $sql = "SELECT ZYH,to_char(HCRQ,'yyyy-mm-dd hh24:mi:ss') as HCRQ,to_char(ZZRQ,'yyyy-mm-dd hh24:mi:ss') as ZZRQ,
                HCLX,HQCH,HHCH,HQKS,HHKS,HQBQ,HHBQ,JSCS,CZGH,JGID
                FROM PORTAL_HIS.BTF_ZY_HCMX
                WHERE ZYH={$ZYH}";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }

        return $data;
    }

    /**
     * 同步护士分床信息
     * @param $data
     * @return void
     */
    public function addData($data)
    {
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
}
