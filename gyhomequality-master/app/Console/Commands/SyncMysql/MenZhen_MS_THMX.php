<?php

namespace App\Console\Commands\SyncMysql;

use App\Model\MS_BRDA;
use Illuminate\Console\Command;

class MenZhen_MS_THMX extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'menzhen:ms_thmx {startTime?} {endTime?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步门诊病历数据  --  门诊退号：MS_THMX';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public static $hisCon;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->getHisConnect();
        echo "同步门诊退号数据：MS_THMX--start:" . date('Y-m-d H:i:s') . "\n";
        $startTime = $this->argument('startTime');
        $endTime = $this->argument('endTime');

        $startTime = empty($startTime) ? date('Y-m-d 00:00:00', strtotime("-1 day", time())) : date('Y-m-d 00:00:00', strtotime($startTime));
        $endTime = empty($endTime) ? date('Y-m-d 23:59:59', strtotime("-1 day", time())) : date('Y-m-d 23:59:59', strtotime($endTime));
        $s = time();
        $total = 0;
        while (true) {
            $startTimeEnd = date('Y-m-d 23:59:59', strtotime($startTime));
            if ($startTime > $endTime) {
                break;
            }
            $data = $this->get_MS_THMX_Data($startTime, $startTimeEnd);
            echo $startTime . " - " . count($data) . " - " . date('Y-m-d H:i:s') . "\n";
            $total += count($data);
            $this->add_MS_THMX_Data($data);
            $startTime = date('Y-m-d 00:00:00', strtotime($startTime) + 86400);
        }
        $e = time();
        $timeDifference = $e - $s;
        $totalHours = floor($timeDifference / 3600); // 3600秒 = 1小时
        $totalMinutes = floor(($timeDifference % 3600) / 60); // 剩余分钟
        echo "一共同步" . $total . "条数据；用时" . $totalHours . "小时" . $totalMinutes . "分钟" . "\n";
        echo "同步门诊退号数据：MS_THMX--end:" . date('Y-m-d H:i:s') . "\n";
        exit();
    }

    public function getHisConnect()
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
        self::$hisCon = $con;
    }


    /**
     * 获取门诊退号数据
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public function get_MS_THMX_Data($startTime, $endTime)
    {
        $sql = "SELECT SBXH,CZGH,MZLB,JGID,
                to_char(JZRQ,'yyyy-mm-dd hh24:mi:ss') as JZRQ,
                to_char(HZRQ,'yyyy-mm-dd hh24:mi:ss') as HZRQ,
                to_char(THRQ,'yyyy-mm-dd hh24:mi:ss') as THRQ
                FROM PORTAL_HIS.BTF_MS_THMX
                WHERE THRQ BETWEEN TO_DATE('{$startTime}','yyyy-mm-dd hh24:mi:ss') AND TO_DATE('{$endTime}','yyyy-mm-dd hh24:mi:ss')";
        $result = oci_parse(self::$hisCon, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;
    }

    /**
     * 同步门诊退号数据
     * @param $data
     * @return void
     */
    public function add_MS_THMX_Data($data)
    {
        if (!empty($data)) {
            foreach ($data as $item2) {
                $insertData2 = [
                    'SBXH' => $item2['SBXH'],
                    'CZGH' => $item2['CZGH'],
                    'JZRQ' => $item2['JZRQ'],
                    'MZLB' => $item2['MZLB'],
                    'HZRQ' => $item2['HZRQ'],
                    'THRQ' => $item2['THRQ'],
                    'JGID' => $item2['JGID'],
                    //                            'TCKF' => $item2['TCKF'],
                    //                            'TBLF' => $item2['TBLF'],
                ];
                \App\Model\MS_THMX::query()->updateOrInsert(['SBXH' => $insertData2['SBXH']], $insertData2);
            }
        }
    }

}
