<?php

namespace App\Console\Commands\ZhiBiao;

use Illuminate\Console\Command;
use App\Services\IndicatorCalcService;
use Illuminate\Support\Facades\Log;

/**
 * 手术患者特级护理/一级护理出院率
 */
class Zb_sshztjhlyjhlcyl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zb_sshztjhlyjhlcyl {zyh?} {startTime?} {endTime?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '计算手术患者特级护理/一级护理出院率指标';

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
        $zyh = $this->argument('zyh');
        $s = time();
        $startTime = $this->argument('startTime');
        $startTime = !empty($startTime) ? date('Y-m-d 00:00:00', strtotime($startTime)) : date('Y-m-d 00:00:00', strtotime("-180 day", time()));
        $endTime = $this->argument('endTime');
        $endTime = !empty($endTime) ? date('Y-m-d 23:59:59', strtotime($endTime)) : date('Y-m-d 23:59:59', time());

        $this->info('开始计算手术患者特级护理/一级护理出院率: ' . date("Y-m-d H:i:s"));
        $moduleName = env("APP_NAME", "");
        $className = "\\App\\Services\\MysqlDataSync\\{$moduleName}\\IndicatorCalcService";
        $indicatorCalcService = new $className();
        //$indicatorCalcService = new IndicatorCalcService();
        $indicatorCalcService->calculateSshztjhlyjhlcyl($zyh, $startTime, $endTime);
        $e = time();
        $timeDifference = $e - $s;
        $totalHours = floor($timeDifference / 3600); // 3600秒 = 1小时
        $totalMinutes = floor(($timeDifference % 3600) / 60); // 剩余分钟
        echo "用时" . $totalHours . "小时" . $totalMinutes . "分钟" . "\n";
        echo $this->description . " end:" . date('Y-m-d H:i:s') . "\n";
        return 0;
    }
}
