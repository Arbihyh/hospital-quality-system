<?php

namespace App\Console\Commands\SyncMysql;

use Illuminate\Console\Command;
use App\Services\HomeDataService;

class ISCATA extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:is_cata {startTime?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清洗patient_info表中的IS_CATA字段为已编目';

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
        $homeDataService->isCATA($startTime);
    }

}
