<?php

namespace App\Console\Commands\SyncMysql;

use App\Model\MS_BRDA;
use Illuminate\Console\Command;

class MenZhen_MS_GHMX extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'menzhen:ms_ghmx {startTime?} {endTime?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步门诊病历数据  --  门诊挂号费：MS_GHMX';

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
        echo "同步门诊挂号费：MS_GHMX--start:" . date('Y-m-d H:i:s') . "\n";
        $startTime = $this->argument('startTime');
        $endTime = $this->argument('endTime');

        $startTime = empty($startTime) ? date('Y-m-d 00:00:00', strtotime("-1 day", time())) : date('Y-m-d 00:00:00', strtotime($startTime));
        $endTime = empty($endTime) ? date('Y-m-d 23:59:59', strtotime("-1 day", time())) : date('Y-m-d 23:59:59', strtotime($endTime));
        $s = time();
        $total = 0;
        while (true) {
            $startTimeEnd = date('Y-m-d 23:59:59',strtotime($startTime));
            if($startTime > $endTime){
                break;
            }
            $data = $this->get_MS_GHMX_Data($startTime,$startTimeEnd);
            echo $startTime ." - ".count($data) ." - ".date('Y-m-d H:i:s')."\n";
            $total += count($data);
            $this->add_MS_GHMX_Data($data);
            $startTime = date('Y-m-d 00:00:00',strtotime($startTime)+86400);
        }
        $e = time();
        $timeDifference = $e - $s;
        $totalHours = floor($timeDifference / 3600); // 3600秒 = 1小时
        $totalMinutes = floor(($timeDifference % 3600) / 60); // 剩余分钟
        echo "一共同步" . $total . "条数据；用时" . $totalHours . "小时" . $totalMinutes . "分钟" . "\n";
        echo "同步门诊挂号费：MS_GHMX--end:" . date('Y-m-d H:i:s') . "\n";
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
     * 获取门诊挂号费数据
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public function get_MS_GHMX_Data($startTime,$endTime){
        $sql = "SELECT SBXH,BRID,BRXZ,GHLB,KSDM,YSDM,JZYS,JZXH,GHCS,ZHLB,JZJS,JZHM,JZZT,
                to_char(GHSJ,'yyyy-mm-dd hh24:mi:ss') as GHSJ,
                to_char(JZRQ,'yyyy-mm-dd hh24:mi:ss') AS JZRQ,
                to_char(HZRQ,'yyyy-mm-dd hh24:mi:ss') AS HZRQ
                FROM PORTAL_HIS.BTF_MS_GHMX
                WHERE GHSJ BETWEEN TO_DATE('{$startTime}','yyyy-mm-dd hh24:mi:ss') AND TO_DATE('{$endTime}','yyyy-mm-dd hh24:mi:ss')";
        $result = oci_parse(self::$hisCon, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;
    }

    /**
     * 同步门诊挂号费数据
     * @param $data
     * @return void
     */
    public function add_MS_GHMX_Data($data)
    {
        if (!empty($data)) {
            foreach ($data as $item) {
                $insertData = [
                    'SBXH' => $item['SBXH'],
                    'BRID' => $item['BRID'],
                    'BRXZ' => $item['BRXZ'],
                    'GHSJ' => $item['GHSJ'],
                    'GHLB' => $item['GHLB'],
                    'KSDM' => $item['KSDM'],
                    'YSDM' => $item['YSDM'],
                    'JZYS' => $item['JZYS'],
                    'JZXH' => $item['JZXH'],
                    'GHCS' => $item['GHCS'],
                    'ZHLB' => $item['ZHLB'],
                    'JZJS' => $item['JZJS'],
                    'JZRQ' => $item['JZRQ'],
                    'HZRQ' => $item['HZRQ'],
                    'JZHM' => $item['JZHM'],
                    'JZZT' => $item['JZZT'],
                ];
                \App\Model\MS_GHMX::query()->updateOrInsert(['SBXH' => $insertData['SBXH']], $insertData);
            }
        }
    }

}
