<?php

namespace App\Console\Commands\Ningxia;

use App\Model\ZY_BRRY;
use App\Model\EMR_BL_BL01;
use App\Model\V_JMGS_YMresult;
use Illuminate\Console\Command;

class CleanBingli extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'ningxia:clean_bingli';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 清洗宁厦的病理数据';

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
            try {
                $homeDataService->getBingLiData($zyh);
            } catch (\Throwable $e) {
                var_dump("command laravel:CleanBingli 错误:code:" . $e->getCode() . '；line:' . $e->getLine() . '；错误信息：' . $e->getMessage());
            }
        }
    }
}
