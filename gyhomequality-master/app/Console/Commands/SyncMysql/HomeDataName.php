<?php

namespace App\Console\Commands\SyncMysql;

use Illuminate\Console\Command;
use App\Services\HomeDataService;

class HomeDataName extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:homeDataName {startTime?} {endTime?} {ZYH?}';

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
        $ZYH = $this->argument('ZYH');

        $homeDataService = new HomeDataService();
        $homeDataService->newIndex($startTime, $endTime, 1, $ZYH);
    }

}
