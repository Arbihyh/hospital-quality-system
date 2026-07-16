<?php

namespace App\Console\Commands\mysqltoes;

use App\Model\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class FeeDetail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'es:fee_detail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command';

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
        $setName = 'es_index_fee_detail';
        echo 'start es_index_fee_detail';
        $updated = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = 0;
        $page = 1;
        while (true) {
            $start = ($page - 1) * 20;
            $res = DB::select("select id,FYDJ,FYGB,FYKS,pre_FYMC,FYMC,FYSL,FYXH,JFRQ,AAA28 MED_REC_ID,SYFYGB,ZFJE,ZJE,AAC01 from fee_detailed where AAC01 > '{$updated}' limit {$start}, 20");
            if (empty($res)) {
                echo '没有数据';
                return false;
            }
            $page++;
            $res = json_decode(json_encode($res), true);

            // 病例索引
            $es_params = [];
            $index = 'fee_detail';
            foreach ($res as $item) {
                $lastId = $item['AAC01'];
                echo $item['FYDJ'].PHP_EOL;
                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['id']]];
                $es_params['body'][] = ['doc' => [
                    "FYDJ" => $item['FYDJ'],
                    "FYGB" => $item['FYGB'],
                    "FYKS" => $item['FYKS'],
                    "pre_FYMC" => $item['pre_FYMC'],
                    "FYMC" => $item['FYMC'],
                    "FYSL" => $item['FYSL'],
                    "FYXH" => $item['FYXH'],
                    "JFRQ" => $item['JFRQ'],
                    "MED_REC_ID" => $item['MED_REC_ID'],
                    "SYFYGB" => $item['SYFYGB'],
                    "ZFJE" => $item['ZFJE'],
                    "ZJE" => $item['ZJE'],
                ], 'doc_as_upsert' => true];
            }
            app('es')->bulk($es_params);
            Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
        }
    }

}
