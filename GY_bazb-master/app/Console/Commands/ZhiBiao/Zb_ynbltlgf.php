<?php

namespace App\Console\Commands\ZhiBiao;

use Illuminate\Console\Command;
use App\Services\IndicatorService;

/**
 * 疑难病历讨论规范开展率
 * @author lch 
 */
class Zb_ynbltlgf extends Command
{
    protected $signature = 'zb_ynbltlgf {zyh?} {startTime?} {endTime?}';

    protected $description = '指标：疑难病历讨论规范开展率';



    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $moduleName = env("APP_NAME", "");
        $className = "\\App\\Services\\MysqlDataSync\\{$moduleName}\\IndicatorService";
        $indicatorService = new $className();
        //$indicatorService = new IndicatorService();
        $s = time();
        echo $this->description . " start:" . date('Y-m-d H:i:s') . "\n";
        $zyh = $this->argument('zyh');
        $zyh = !empty($zyh) ? $zyh : '';
        $startTime = $this->argument('startTime');
        $startTime = !empty($startTime) ? date('Y-m-d 00:00:00', strtotime($startTime)) : date('Y-m-d 00:00:00', strtotime("-180 day", time()));
        $endTime = $this->argument('endTime');
        $endTime = !empty($endTime) ? date('Y-m-d 23:59:59', strtotime($endTime)) : date('Y-m-d 23:59:59', time());


        $data = $indicatorService->getPatientInfoData($zyh, $startTime, $endTime);
        $indicatorService->handleYnbltlgf($data);

        $e = time();
        $timeDifference = $e - $s;
        $totalHours = floor($timeDifference / 3600); // 3600秒 = 1小时
        $totalMinutes = floor(($timeDifference % 3600) / 60); // 剩余分钟
        echo "用时" . $totalHours . "小时" . $totalMinutes . "分钟" . "\n";
        echo $this->description . " end:" . date('Y-m-d H:i:s') . "\n";
        return 0;
    }
}