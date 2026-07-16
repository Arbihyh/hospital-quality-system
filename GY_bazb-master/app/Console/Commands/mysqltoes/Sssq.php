<?php

namespace App\Console\Commands\mysqltoes;

use App\Model\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class Sssq extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'es:sssq';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 定时生成执行相关诊断信息的统计信息';

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
        $setName = 'es_index_sssq';
        $updated = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = 0;
        $page = 1;
        while (true) {
            $start = ($page - 1) * 20;
            $sssq = DB::select('select * from SSSQ where AAC01 > "' . $updated . '" order by SQDH asc limit '.$start.', 20');
            if (empty($sssq)) {
                echo '没有数据';
                return false;
            }
            $page++;
            $sssq = json_decode(json_encode($sssq), true);

            // 病例索引
            $es_params = [];
            $index = 'sssq_2023';
            foreach ($sssq as $item) {
                echo $item['SQDH'].PHP_EOL;
                $lastId = $item['AAC01'];
                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['SQDH']]];
                $es_params['body'][] = ['doc' => [
                    "SQDH" => $item["SQDH"],
                    "JGID" => $item["JGID"],
                    "ZYH" => $item["ZYH"],
                    "SSKS" => $item["SSKS"],
                    "SQKS" => $item["SQKS"],
                    "SQYS" => $item["SQYS"],
                    "SQRQ" => $item["SQRQ"],
                    "SSRQ" => $item["SSRQ"],
                    "SSNM" => $item["SSNM"],
                    "SSYS" => $item["SSYS"],
                    "SSYZ" => $item["SSYZ"],
                    "SSRZ" => $item["SSRZ"],
                    "SSSZ" => $item["SSSZ"],
                    "SSEZ" => $item["SSEZ"],
                    "SSYQ" => $item["SSYQ"],
                    "MZDM" => $item["MZDM"],
                    "MZYS" => $item["MZYS"],
                    "TJBZ" => $item["TJBZ"],
                    "APBZ" => $item["APBZ"],
                    "ZFBZ" => $item["ZFBZ"],
                    "TXKS" => $item["TXKS"],
                    "CZGH" => $item["CZGH"],
                    "SQTL" => $item["SQTL"],
                    "SQZD" => $item["SQZD"],
                    "NSSMC" => $item["NSSMC"],
                    "FYBQ" => $item["FYBQ"],
                    "QKDJ" => $item["QKDJ"],
                    "THYY" => $item["THYY"],
                    "ZFYY" => $item["ZFYY"],
                    "ZFGH" => $item["ZFGH"],
                    "LRBZ" => $item["LRBZ"],
                    "YXJS" => $item["YXJS"],
                    "NLTR" => $item["NLTR"],
                    "HBQTJB" => $item["HBQTJB"],
                    "QTTSQK" => $item["QTTSQK"],
                    "TSQKNR" => $item["TSQKNR"],
                    "SSJB" => $item["SSJB"],
                    "CRBZ" => $item["CRBZ"],
                    "CRBG" => $item["CRBG"],
                    "BXBZ" => $item["BXBZ"],
                    "BXSM" => $item["BXSM"],
                    "BZXX" => $item["BZXX"],
                    "SSTW" => $item["SSTW"],
                    "SPBZ" => $item["SPBZ"],
                    "CFSS" => $item["CFSS"],
                    "ZLXZ" => $item["ZLXZ"],
                    "RJSS" => $item["RJSS"],
                ], 'doc_as_upsert' => true];
            }
            app('es')->bulk($es_params);
            Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
        }
    }

}
