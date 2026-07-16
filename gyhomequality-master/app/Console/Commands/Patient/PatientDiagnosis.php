<?php

namespace App\Console\Commands\Patient;

use App\Model\FeeDetailed;
use App\Model\Icu;
use App\Model\MainDiagnosis;
use App\Model\OtherDiagnosis;
use Illuminate\Console\Command;

class PatientDiagnosis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'patient_diagnosis {startTime?} {endTime?}';

    public static $con;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步患者诊断信息';

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
        echo "同步患者诊断信息开始：" . date('Y-m-d H:i:s') . "\n";
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
        echo "同步患者诊断信息结束：" . date('Y-m-d H:i:s') . "\n";
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
        $sql = "SELECT A.* FROM PORTAL_HIS.V_JMGS_BASY_ZD A WHERE ZYH=" . $ZYH;
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
        $main = [];
        $diagnosis = [];
        foreach ($data as $val) {
            $addData = [
                'AAA28' => $val['ZYH'],
                'ICD10_ID1' => $val['ZDBM'] ?? '', //诊断编码
                'ICD10_NAME' => $val['ZDMC'] ?? '', //诊断名称
                'DIA_ORDER' => $val['ZDXH'] ?? '', //诊断序号
                'LBMC' => $val['LBMC'] ?? '',//类别名称
                'RYQK' => $val['RYQK'] ?? ''//入院情况
            ];

            //诊断判断 zzpb=1主要诊断  = 0其他诊断
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
    }

}
