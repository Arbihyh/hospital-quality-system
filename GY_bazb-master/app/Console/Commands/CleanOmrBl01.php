<?php

namespace App\Console\Commands;

use App\Model\OMR_BL01;
use App\Model\Setting;
use App\Services\MzBlDataFormatService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanOmrBl01 extends Command
{
/**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:clean_omr_bl01 {blbh?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 清洗omr_bl01表';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $blbh = data_get($this->arguments(),'blbh');

        // 获取最后一次质控的住院号
        $lastId = Setting::query()->where('name', '=', 'cleanomrbl01')->pluck('content')->first();
        $lastNo = $lastId ?: 0;
        if ($blbh) {
            $lastNo = 0;
        }
        $pageSize = 100;
        $bl = new MzBlDataFormatService();
        try {
            while (1) {
                $query = OMR_BL01::query();
                if ($blbh) {
                    $query = $query->where('BLBH', $blbh);
                } else {
                    $query = $query->where('id', '>', $lastNo)
                        ->orderBy("id", "asc")
                        ->LIMIT($pageSize);
                }

                $res01 = $query->get()->toArray();
                if (!$res01) {
                    break;
                }
                foreach ($res01 as $item) {
                    $lastNo = $item['id'];
                    if (empty($item['BLNR_TXT'])){
                        continue;
                    }
                    $bl->formatBlData($item["BLBH"]);
                }
                if ($blbh) {
                    break;
                }
            }

            Setting::query()->where('name', '=', 'cleanomrbl01')->update(['content' => $lastNo]);
        } catch (\Throwable $e) {
            Log::error("数据清洗", ['msg' => $e->getMessage(), 'line' => $e->getLine()]);
            echo $e->getMessage() . '，所在行：' . $e->getLine();
        }
    }
}