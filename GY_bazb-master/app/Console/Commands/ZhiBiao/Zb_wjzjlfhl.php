<?php

namespace App\Console\Commands\ZhiBiao;

use App\Model\IndexCatalog;
use App\Services\IndicatorService;
use Illuminate\Console\Command;

/**
 * 危急值记录符合率
 * @author lch 
 */
class Zb_wjzjlfhl extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zb_wjzjlfhl {zyh?} {startTime?} {endTime?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '指标：危急值记录符合率';



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
        $indicatorService->handleWjzjlfhl($data);


        $e = time();
        $timeDifference = $e - $s;
        $totalHours = floor($timeDifference / 3600); // 3600秒 = 1小时
        $totalMinutes = floor(($timeDifference % 3600) / 60); // 剩余分钟
        echo "用时" . $totalHours . "小时" . $totalMinutes . "分钟" . "\n";
        echo $this->description . " end:" . date('Y-m-d H:i:s') . "\n";
        return 0;
    }
}