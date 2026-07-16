<?php

namespace App\Console\Commands;

use App\Model\WJZ;
use App\Model\ZY_BRRY;
use Illuminate\Console\Command;

class WjzCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:wjz {zyh?} {startTime?} {endTime?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步危急值数据';

    public static $con;

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
     * Undocumented function
     *
     * @return void
     */
    public function handle()
    {

        echo $this->description . date('Y-m-d H:i:s') . "\n";
        $this->getConnect(); //建立数据库连接
        $zyh = $this->argument('zyh');
        $zyh = !empty($zyh) ? $zyh : '';
        $startTime = $this->argument('startTime');
        $startTime = !empty($startTime) ? date('Y-m-d 00:00:00', strtotime($startTime)) : date('Y-m-d 00:00:00', time() - 7 * 3600);
        $endTime = $this->argument('endTime');
        $endTime = !empty($endTime) ? date('Y-m-d 23:59:59', strtotime($endTime)) : date('Y-m-d 23:59:59', time());
        $s = time();
        $total = 0;
        while (true) {
            $startEndTime = date('Y-m-d 23:59:59', strtotime($startTime));
            if ($startTime > $endTime) {
                break;
            }
            $wjzData = $this->getWjzData($startTime, $startEndTime, $zyh);
            if (!empty($wjzData)) {
                echo $startTime . "共" . count($wjzData) . "条数据" . "\n";
                $total += count($wjzData);
                echo $startTime . ' - ' . date('Y-m-d H:i:s') . "\n";
                $this->addWjzData($wjzData, $startTime, $startEndTime, $zyh);
            }
            $startTime = date('Y-m-d 00:00:00', strtotime($startTime) + 86400);
        }


        $e = time();
        $timeDifference = $e - $s;
        $totalHours = floor($timeDifference / 3600); // 3600秒 = 1小时
        $totalMinutes = floor(($timeDifference % 3600) / 60); // 剩余分钟
        echo "一共同步" . $total . "条数据；用时" . $totalHours . "小时" . $totalMinutes . "分钟" . "\n";
        echo $this->description . date('Y-m-d H:i:s') . "\n";
        exit();
    }


    private function getConnect()
    {
        $username = 'blzk';
        $password = 'blzk';
        $connection = '192.168.10.254';
        $port = '1521';
        $tns = 'orcl';
        $con = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        self::$con = $con;
    }

    public function getWjzData($startTime, $endTime, $zyh)
    {
        if (!self::$con) {
            $this->getConnect();
        }
        $sql = "select ZYH,AAA28,AAB01,WJZLX,WJZNR,
         to_char(WJZSJ,'yyyy-mm-dd hh24:mi:ss') as WJZSJ
         from panicuser.BLZK ";
        if (!empty($zyh)) {
            $sql .= " WHERE AAA28 = '" . $zyh . "'";
        } else {
            $sql .= " WHERE WJZSJ BETWEEN TO_DATE('" . $startTime . "', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('" . $endTime . "', 'yyyy-MM-dd HH24:mi:ss')";
        }
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        return $result;
    }

    public function addWjzData($data, $startTime, $endTime, $zyh)
    {
        if (!empty($data)) {
            if (!empty($zyh)) {
                WJZ::query()->where('ZYH', '=', $zyh)->delete();
            } else {
                WJZ::query()->whereBetween('WJZSJ', [$startTime, $endTime])->delete();
            }
            foreach ($data as $item) {
                $insert = [
                    'ZYH' => $item['AAA28'] ?? '',
                    'AAA28' => $item['ZYH'] ?? '',
                    'AAB01' => $item['AAB01'] ?? '',
                    'WJZLX' => $item['WJZLX'] ?? '',
                    'WJZNR' => $item['WJZNR'] ?? '',
                    'WJZSJ' => $item['WJZSJ'] ?? '',
                ];
                WJZ::query()->insert($insert);
            }
        }
    }
}