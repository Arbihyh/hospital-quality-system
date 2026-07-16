<?php

namespace App\Console\Commands\SyncMysql;

use App\Model\ZY_BRRY;
use Illuminate\Console\Command;

class SM_SSAP extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:ssap {startTime?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '获取ssap数据';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public static $con;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $startTime = $this->argument('startTime');
        $startTime = $startTime ?: '2025-01-01 00:00:00';
        $moduleName = env("APP_NAME", "");
        $className = "\\App\\Services\\MysqlDataSync\\{$moduleName}\\HomeData";
        $homeDataService = new $className();
        $zyhList = ZY_BRRY::query()->where('AAB01', '>', $startTime)->get(['ZYH'])->toArray();
        foreach ($zyhList as $item) {
            $zyh = $item['ZYH'];
            echo $zyh . PHP_EOL;
            $homeDataService->SM_SSAP($zyh);
        }
    }

}
