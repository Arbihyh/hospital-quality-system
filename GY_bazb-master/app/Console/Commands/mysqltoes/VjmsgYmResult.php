<?php

namespace App\Console\Commands\mysqltoes;

use App\Model\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class VjmsgYmResult extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'es:v_jmgs_ymresult';

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
        $setName = 'es_index_v_jmgs_ymresult_2023';
        $updated = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId =  0;
        $page = 1;
        while (true) {
            $start = ($page - 1) * 20;
            $res = DB::select("SELECT id, ZYH, TXM, NO, XM, XB, NL, CH, YBLX, YBZT, AAA28, BQ, LCZD, PYJG, XJMC, XJJL, YMMC, YMJG, YMBW, SJYS, JYY, SHY, FROM_UNIXTIME(unix_timestamp(CJSJ), '%Y-%m-%d %H:%i:%s') as CJSJ, FROM_UNIXTIME(unix_timestamp(JSSJ), '%Y-%m-%d %H:%i:%s') as JSSJ, FROM_UNIXTIME(unix_timestamp(BGSJ), '%Y-%m-%d %H:%i:%s') as BGSJ,EXAMINAIM,STAYHOSPITALMODE,AAC01 from V_JMGS_YMresult where AAC01 > '{$updated}' order by id asc limit {$start}, 20");
            if (empty($res)) {
                echo '没有数据';
                return false;
            }
            $page++;
            $res = json_decode(json_encode($res), true);

            // 病例索引
            $es_params = [];
            $index = 'v_jmgs_ymresult_2023';
            foreach ($res as $item) {
                $lastId = $item['AAC01'];
                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['id']]];
                $es_params['body'][] = ['doc' => [
                    "ZYH" => $item['ZYH'],
                    "TXM" => $item['TXM'],
                    "NO" => $item['NO'],
                    "XM" => $item['XM'],
                    "XB" => $item['XB'],
                    "NL" => $item['NL'],
                    "CH" => $item['CH'],
                    "YBLX" => $item['YBLX'],
                    "YBZT" => $item['YBZT'],
                    "AAA28" => $item['AAA28'],
                    "BQ" => $item['BQ'],
                    "LCZD" => $item['LCZD'],
                    "PYJG" => $item['PYJG'],
                    "XJMC" => $item['XJMC'],
                    "XJJL" => $item['XJJL'],
                    "YMMC" => $item['YMMC'],
                    "YMJG" => $item['YMJG'],
                    "YMBW" => $item['YMBW'],
                    "SJYS" => $item['SJYS'],
                    "JYY" => $item['JYY'],
                    "SHY" => $item['SHY'],
                    "CJSJ" => $item['CJSJ'],
                    "JSSJ" => $item['JSSJ'],
                    "BGSJ" => $item['BGSJ'],
                    "EXAMINAIM" => $item['EXAMINAIM'],
                    "STAYHOSPITALMODE" => $item['STAYHOSPITALMODE'],
                ], 'doc_as_upsert' => true];
            }
            app('es')->bulk($es_params);
            Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
        }
    }

}
