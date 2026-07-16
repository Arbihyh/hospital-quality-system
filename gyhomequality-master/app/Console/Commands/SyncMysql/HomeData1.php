<?php

namespace App\Console\Commands\SyncMysql;

use Illuminate\Console\Command;
use App\Services\HomeDataService;

class HomeData1 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:homeData1 {startTime?} {endTime?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $this->info('脚本开始');
        $homeDataService = new HomeDataService();
        $homeDataService->index($startTime, $endTime, 2);
    }

}
