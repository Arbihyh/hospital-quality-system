<?php

namespace App\Console\Commands\Patient;

use App\Model\FeeDetailed;
use Illuminate\Console\Command;

class PatientFeeDetail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'patient_feeDetail {startTime?} {endTime?}';

    public static $con;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步患者费用信息';

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
        echo "同步患者费用信息信息开始：" . date('Y-m-d H:i:s') . "\n";
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
                $data = $this->getFeeDetailData($v);
                if (!empty($data)) {
                    echo $v . " - " . $startTime . "共" . count($data) . "条数据 - " . date('Y-m-d H:i:s') . "\n";
                    $total += count($data);
                    $this->addFeeDetail($data);
                }
            }
            $page++;
        }

        $e = time();
        $timeDifference = $e - $s;
        $totalHours = floor($timeDifference / 3600); // 3600秒 = 1小时
        $totalMinutes = floor(($timeDifference % 3600) / 60); // 剩余分钟
        echo "一共同步" . $total . "条数据；用时" . $totalHours . "小时" . $totalMinutes . "分钟" . "\n";
        echo "同步患者费用信息信息结束：" . date('Y-m-d H:i:s') . "\n";
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
     * 获取费用信息
     * @param $zyh
     * @return array
     */
    public function getFeeDetailData($zyh)
    {
        // 查询费用数据
        $sql = "SELECT A.*,to_char(JFRQ,'yyyy-mm-dd hh24:mi:ss') as JFRQ,to_char(DSG_LDR_TIME,'yyyy-mm-dd hh24:mi:ss') as DSG_LDR_TIME
                FROM PORTAL_HIS.V_JMGS_BASY_FYMX A WHERE A.ZYH = {$zyh}";
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * 添加费用信息
     * @param $result
     * @return void
     */
    public function addFeeDetail($result)
    {
        foreach ($result as $val) {
            $item = [
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
                'JLXH' => $val['JLXH'] ?? '',
                'YZXH' => $val['YZXH'] ?? '',//医嘱序号
            ];
            FeeDetailed::query()->updateOrInsert(['AAA28' => $item['AAA28'], 'JLXH' => $item['JLXH']], $item);
        }
    }

}
