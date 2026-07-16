<?php

namespace App\Console\Commands\Ningxia;

use App\Model\EMR_BL_BL01;
use Illuminate\Console\Command;

class CleanSs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'ningxia:clean_ss';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 清洗宁厦的输血信息';

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
     *
     */
    public function handle()
    {
        $moduleName = env("APP_NAME", "");
        $className = "\\App\\Services\\MysqlDataSync\\{$moduleName}\\HomeData";
        $homeDataService = new $className();
        $homeDataService->cleanBloodBLZK();
    }

}
