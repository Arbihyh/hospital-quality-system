<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;


class CleanPacsZYH extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'clean_pacs_zyh {time?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 清洗PACS的ZYH';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    function handle()
    {
        $time = $this->argument("time");
        $moduleName = env("APP_NAME", "");
        $className = "\\App\\Services\\MysqlDataSync\\{$moduleName}\\HomeData";
        $homeDataService = app()->make($className);
        if($time){
            $time = strtotime($time);
        }else{
            $time = time()-86400;
        }
        $homeDataService->cleanPacsZYH($time);
    }


}
