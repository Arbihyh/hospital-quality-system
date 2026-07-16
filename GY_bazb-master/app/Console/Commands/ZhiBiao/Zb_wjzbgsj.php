<?php

namespace App\Console\Commands\ZhiBiao;

use Illuminate\Console\Command;

/**
 * 危急值报告时间指标。
 */
class Zb_wjzbgsj extends Command
{
    /**
     * The name and signature of the console command.
     *
     * 该指标不接收住院号，只接收开始时间和结束时间。
     *
     * @var string
     */
    protected $signature = 'zb_wjzbgsj {startTime?} {endTime?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '指标：危急值报告时间';

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
        $startTimestamp = time();
        $this->info($this->description . ' start:' . date('Y-m-d H:i:s'));

        $startTimeArgument = $this->argument('startTime');
        $startTime = !empty($startTimeArgument)
            ? date('Y-m-d 00:00:00', strtotime($startTimeArgument))
            : date('Y-m-d 00:00:00', strtotime('-180 day', time()));

        $endTimeArgument = $this->argument('endTime');
        $endTime = !empty($endTimeArgument)
            ? date('Y-m-d 23:59:59', strtotime($endTimeArgument))
            : date('Y-m-d 23:59:59', time());

        $moduleName = env('APP_NAME', '');
        $className = "\\App\\Services\\MysqlDataSync\\{$moduleName}\\IndicatorCalcService";
        $indicatorCalcService = new $className();
        $indicatorCalcService->calculateWjzbgsj($startTime, $endTime);

        $endTimestamp = time();
        $timeDifference = $endTimestamp - $startTimestamp;
        $totalHours = floor($timeDifference / 3600);
        $totalMinutes = floor(($timeDifference % 3600) / 60);

        echo '用时' . $totalHours . '小时' . $totalMinutes . '分钟' . "\n";
        echo $this->description . ' end:' . date('Y-m-d H:i:s') . "\n";
        return 0;
    }
}
