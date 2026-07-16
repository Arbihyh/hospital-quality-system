<?php

namespace App\Console\Commands\SyncMysql;

use Illuminate\Console\Command;
class HomeData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:homeData {startTime?} {endTime?} {ZYH?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步所有数据';

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
        $endTime = $this->argument('endTime');
        $ZYH = $this->argument('ZYH');

        $moduleName = env("APP_NAME", "");
        $className = "\\App\\Services\\MysqlDataSync\\{$moduleName}\\HomeData";
        $homeDataService = new $className();
        $homeDataService->index($startTime, $endTime, $ZYH);
    }

}
