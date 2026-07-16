<?php

namespace App\Console\Commands\SyncMysql;

use Illuminate\Console\Command;
use App\Services\HomeDataService;

class OMRBL01 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:menzhen {startTime?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '获取门诊数据';

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

        $moduleName = env("APP_NAME", "");
        $className = "\\App\\Services\\MysqlDataSync\\{$moduleName}\\HomeData";
        $homeDataService = new $className();
        $homeDataService->OMRBL01($startTime);
    }

}
