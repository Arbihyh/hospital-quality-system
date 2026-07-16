<?php

namespace App\Console\Commands\ZhiBiao;

use Illuminate\Console\Command;
use App\Services\IndicatorCalcService;

class Zb_sqtlrygfcyl extends Command
{
    protected $signature = 'zb_sqtlrygfcyl {zyh?} {startTime?} {endTime?}';

    protected $description = '计算术者术前讨论参与率指标';

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

        $this->info('开始计算术者术前讨论参与率: ' . date("Y-m-d H:i:s"));
        $moduleName = env("APP_NAME", "");
        $className = "\\App\\Services\\MysqlDataSync\\{$moduleName}\\IndicatorCalcService";
        $indicatorCalcService = new $className();
        //$indicatorCalcService = new IndicatorCalcService();
        $indicatorCalcService->calculateSqtlrygfcyl($zyh, $startTime, $endTime);
        $e = time();
        $diff = $e - $s;
        $h = floor($diff / 3600);
        $m = floor(($diff % 3600) / 60);
        echo "用时{$h}小时{$m}分钟\n";
        echo $this->description . ' end:' . date('Y-m-d H:i:s') . "\n";
        return 0;
    }
}
