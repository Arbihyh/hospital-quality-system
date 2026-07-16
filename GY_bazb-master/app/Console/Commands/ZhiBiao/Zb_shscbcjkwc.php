<?php

namespace App\Console\Commands\ZhiBiao;

use Illuminate\Console\Command;
use App\Services\IndicatorCalcService;
use App\Services\IndicatorService;
use Illuminate\Support\Facades\Log;

class Zb_shscbcjkwc extends Command
{
    protected $signature = 'zb_shscbcjkwc {zyh?} {startTime?} {endTime?}';

    protected $description = '计算手术后首次病程记录即刻完成率指标';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $zyh = $this->argument('zyh');
        $s = time();
        $startTime = $this->argument('startTime');
        $startTime = !empty($startTime) ? date('Y-m-d 00:00:00', strtotime($startTime)) : date('Y-m-d 00:00:00', strtotime("-180 day", time()));
        $endTime = $this->argument('endTime');
        $endTime = !empty($endTime) ? date('Y-m-d 23:59:59', strtotime($endTime)) : date('Y-m-d 23:59:59', time());

        $this->info('开始计算手术后首次病程记录即刻完成率: ' . date("Y-m-d H:i:s"));
        $moduleName = env("APP_NAME", "");
        $className = "\\App\\Services\\MysqlDataSync\\{$moduleName}\\IndicatorService";
        $indicatorService = new $className();
        //$indicatorService = new IndicatorService();
        $data = $indicatorService->getPatientInfoData($zyh, $startTime, $endTime);
        $indicatorService->handleShscbcjkwc($data);
        $e = time();
        $timeDifference = $e - $s;
        $totalHours = floor($timeDifference / 3600); // 3600秒 = 1小时
        $totalMinutes = floor(($timeDifference % 3600) / 60); // 剩余分钟
        echo "用时" . $totalHours . "小时" . $totalMinutes . "分钟" . "\n";
        echo $this->description . " end:" . date('Y-m-d H:i:s') . "\n";
        return 0;
    }
}