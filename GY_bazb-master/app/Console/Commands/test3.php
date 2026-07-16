<?php

namespace App\Console\Commands;

use App\Model\ZY_BRRY;
use App\Model\EMR_BL_BL01;
use App\Model\V_JMGS_YMresult;
use Illuminate\Console\Command;

class Test3 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'test3';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command test3';

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
        $zyhList = ZY_BRRY::query()->where('AAB01', '>', '2025-01-01')->get(['ZYH'])->toArray();
        foreach ($zyhList as $item) {
            $zyh = $item['ZYH'];
            echo $zyh . PHP_EOL;
            // $homeDataService->getYaoMinData($zyh);
            // $homeDataService->getJianYanData($zyh);
            // $homeDataService->pacs($zyh);
            $homeDataService->SM_SSAP($zyh);
            // $homeDataService->getFyData($zyh);
        }
    }
}
