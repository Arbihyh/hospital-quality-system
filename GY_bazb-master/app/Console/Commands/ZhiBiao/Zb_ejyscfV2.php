<?php

namespace App\Console\Commands\ZhiBiao;

use Illuminate\Console\Command;
use App\Services\IndicatorCalcService;

/**
 * 二级医师查房频次达标率 V2版本
 * @author lch 
 */
class Zb_ejyscfV2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zb_ejyscf_v2 {zyh?} {startTime?} {endTime?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '指标：二级医师查房频次达标率(新版)';

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
        ini_set('memory_limit', '4G');
        gc_enable();
        
        $s = time();
        echo $this->description . " start:" . date('Y-m-d H:i:s') . "\n";

        $zyh = $this->argument('zyh');
        $zyh = !empty($zyh) ? $zyh : '';

        $startTime = $this->argument('startTime');
        $startTime = !empty($startTime) ? 
            date('Y-m-d 00:00:00', strtotime($startTime)) : 
            date('Y-m-d 00:00:00', strtotime("-180 day", time()));

        $endTime = $this->argument('endTime');
        $endTime = !empty($endTime) ? 
            date('Y-m-d 23:59:59', strtotime($endTime)) : 
            date('Y-m-d 23:59:59', time());

        echo "处理时间范围: {$startTime} 至 {$endTime}\n";

        $moduleName = env("APP_NAME", "");
        $className = "\\App\\Services\\MysqlDataSync\\{$moduleName}\\IndicatorCalcService";
        $indicatorCalcService = new $className();
        //$indicatorCalcService = new IndicatorCalcService();
        $indicatorCalcService->calculateEjyscf($zyh, $startTime, $endTime);

        $e = time();
        $timeDifference = $e - $s;
        $totalHours = floor($timeDifference / 3600); // 3600秒 = 1小时
        $totalMinutes = floor(($timeDifference % 3600) / 60); // 剩余分钟
        echo "用时" . $totalHours . "小时" . $totalMinutes . "分钟" . "\n";
        echo $this->description . " end:" . date('Y-m-d H:i:s') . "\n";
        return 0;
    }
} 