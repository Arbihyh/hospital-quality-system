<?php

namespace App\Console\Commands;

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
    protected $signature = 'command:clean_blmc {zyh?} {bllb?} {mblb?}';

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
        $bllb = data_get($this->arguments(), 'bllb');
        $mblb = data_get($this->arguments(), 'mblb');

        // 获取EMR_BL_BL01表中当前最大的ID
        $id = EMR_BL_BL01::query()->max('id') ?? 0;
        $limit = 100;
        $num = 1;
        while(true) {
            $query = EMR_BL_BL01::query()
            ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH');
            if ($zyh) {
                $query = $query->where('JZHM', $zyh);
            } 
            if ($bllb) {
                $query = $query->where('BLLB', $bllb);
            }
            if ($mblb) {
                $query = $query->where('MBLB', $mblb);
            }
            $query = $query->where("EMR_BL_BL01.id", '<', $id)->orderBy('EMR_BL_BL01.id', 'desc')->limit($limit);

            $res01 = $query->get(['EMR_BL_BLXG.HJNR', 'EMR_BL_BL01.id', 'EMR_BL_BL01.BLBH', 'EMR_BL_BL01.MBLB', 'EMR_BL_BL01.JZHM', 'EMR_BL_BL01.BLLB', 'EMR_BL_BL01.BLMC1', 'EMR_BL_BL01.BLMC', 'EMR_BL_BL01.CJSJ'])->toArray();
            echo $num . PHP_EOL;
            $num++;

            if (empty($res01)) {
                break;
            }

            $moduleName = env("APP_NAME", "");
            $className = "\\App\\Services\\MysqlDataSync\\{$moduleName}\\HomeData";
            $homeDataService = new $className();
            $id = min(array_column($res01, 'id'));
            foreach ($res01 as $item) {
                echo $item["BLBH"] . PHP_EOL;
                $homeDataService->cleanBlmc($item);
            }
        }

        
    }

}
