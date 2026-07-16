<?php

namespace App\Console\Commands\Lanling;

use App\Model\EMR_BL_BL01;
use App\Model\RuleWordMap;
use App\Model\Setting;
use App\Services\BlDataFormatService;
use Illuminate\Console\Command;
use App\Services\CaseService;
use Illuminate\Support\Facades\Log;

class CleanBlmc extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:lianling_clean_blmc {zyh?} {start_time?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 清洗bl01表';

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
        $zyh = data_get($this->arguments(), 'zyh');
        $startTime = data_get($this->arguments(), 'start_time');

        // 同步所有数据
        if(!$startTime){
            $startTime = date("Y-m-d 00:00:00");
        }
        $id = 0;
        $limit = 100;

        while(true) {
            $query = EMR_BL_BL01::query()
            ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH');
            if ($zyh) {
                $query = $query->where('JZHM', $zyh);
            } else {
                $query = $query->where("EMR_BL_BL01.id", '>', $id)->orderBy('id', 'asc')->limit($limit);
            }

            $res01 = $query->get(['EMR_BL_BL01.id', "EMR_BL_BL01.BLBH", "EMR_BL_BL01.BLMC", "EMR_BL_BL01.BLLB", "EMR_BL_BLXG.HJNR"])->toArray();
            
            if (empty($res01)) {
                break;
            }

            $moduleName = env("APP_NAME", "");
            $className = "\\App\\Services\\MysqlDataSync\\{$moduleName}\\HomeData";
            $homeDataService = new $className();
            $id = max(array_column($res01, 'id'));
            foreach ($res01 as $item) {
                echo $item["BLBH"] . PHP_EOL;

                $item["HJNR"] = str_replace("婚姻状况：", "婚姻：", $item["HJNR"]);
                if ($item["BLLB"] == 294 || $item["BLLB"] == 43) {
                    $homeDataService->formatBL01BLMC294($item);
                }
                if ($item["BLLB"] == 303) {
                    $homeDataService->formatBL01BLMC306($item);
                }
                if ($item["BLLB"] == 82) {
                    $homeDataService->formatBL01BLMC82($item);
                }
            }
        }

        
    }

}
