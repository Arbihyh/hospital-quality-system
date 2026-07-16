<?php

namespace App\Console\Commands\ZhiBiao;

use Illuminate\Console\Command;
use App\Services\IndicatorCalcService;

class Zb_ssyssssjch extends Command
{
    protected $signature = 'zb_ssyssssjch {zyh?} {startTime?} {endTime?}';

    protected $description = '计算手术医师手术时间重合率指标';

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

        $this->info('开始计算手术医师手术时间重合率: ' . date("Y-m-d H:i:s"));
        $moduleName = env("APP_NAME", "");
        $className = "\\App\\Services\\MysqlDataSync\\{$moduleName}\\IndicatorCalcService";
        $indicatorCalcService = new $className();
        //$indicatorCalcService = new IndicatorCalcService();
        $indicatorCalcService->calculateSsyssssjch($zyh, $startTime, $endTime);
        $e = time();
        $diff = $e - $s;
        $h = floor($diff / 3600);
        $m = floor(($diff % 3600) / 60);
        echo "用时{$h}小时{$m}分钟\n";
        echo $this->description . ' end:' . date('Y-m-d H:i:s') . "\n";
        return 0;
    }
}
