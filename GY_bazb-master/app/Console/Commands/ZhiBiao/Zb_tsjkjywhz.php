<?php

namespace App\Console\Commands\ZhiBiao;

use App\Services\IndicatorService;
use Illuminate\Console\Command;

/**
 * 特殊使用级抗菌药物会诊率
 */
class Zb_tsjkjywhz extends Command
{
    protected $signature = 'zb_tsjkjywhz {zyh?} {startTime?} {endTime?}';

    protected $description = '指标：特殊使用级抗菌药物会诊率';

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
        $zyh = $this->argument('zyh') ?: '';
        $startTime = $this->argument('startTime');
        $startTime = !empty($startTime) ? date('Y-m-d 00:00:00', strtotime($startTime)) : date('Y-m-d 00:00:00', strtotime('-180 day'));
        $endTime = $this->argument('endTime');
        $endTime = !empty($endTime) ? date('Y-m-d 23:59:59', strtotime($endTime)) : date('Y-m-d 23:59:59');

        $data = $indicatorService->getPatientInfoData($zyh, $startTime, $endTime);
        $indicatorService->handleTsjkjywhz($data);

        $e = time();
        $diff = $e - $s;
        echo "用时" . floor($diff / 3600) . "小时" . floor(($diff % 3600) / 60) . "分钟\n";
        echo $this->description . " end:" . date('Y-m-d H:i:s') . "\n";
        return 0;
    }
}
