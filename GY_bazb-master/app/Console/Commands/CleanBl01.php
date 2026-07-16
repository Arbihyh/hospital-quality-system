<?php

namespace App\Console\Commands;

use App\Model\EMR_BL_BL01;
use App\Model\RuleWordMap;
use App\Model\Setting;
use App\Services\BlDataFormatService;
use Illuminate\Console\Command;
use App\Services\CaseService;
use Illuminate\Support\Facades\Log;

class CleanBl01 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:clean_bl01 {mblb?} {zyh?}';

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
        $mblb = data_get($this->arguments(),'mblb');
        $zyh = data_get($this->arguments(),'zyh');

        $keyword = "id";

        // 获取最后一次质控的住院号
        $lastId = Setting::query()->where('name', '=', 'platform_ruyuan_last_id')->pluck('content')->first();
        $lastNo = $lastId ?: 0;
        if ($zyh) {
            $lastNo = 0;
        }
        $pageSize = 100;
        $bl = new BlDataFormatService();
        $MBLB = [30301, 306, 45, 295, 1, 292, 3069999, 82, 288, 11, 74];
        try {
            while (1) {
                $query = EMR_BL_BL01::query();
                if ($zyh) {
                    $query = $query->where('JZHM', $zyh);
                } else {
                    $query->where($keyword, '>', $lastNo);
                }
                if ($mblb) {
                    $query = $query->where('MBLB', $mblb);
                }
                $query = $query->orderBy($keyword, "asc")->LIMIT($pageSize);

                $res01 = $query->get()->toArray();
                if (!$res01) {
                    break;
                }
                foreach ($res01 as $item) {
                    echo $item[$keyword].PHP_EOL;
                    $lastNo = $item[$keyword];
                    if (!in_array($item["MBLB"], $MBLB)){
                        continue;
                    }
                    $bl->formatBlData($item["JZHM"], $item["MBLB"]);
                }

                if (!$mblb) {
                    Setting::query()->where('name', '=', 'platform_ruyuan_last_id')->update(['content' => $lastNo]);
                }
                if ($zyh) {
                    break;
                }
            }

        } catch (\Throwable $e) {
            Log::error("数据清洗", ['msg' => $e->getMessage(), 'line' => $e->getLine()]);
            echo $e->getMessage() . '，所在行：' . $e->getLine();
        }
    }

}
