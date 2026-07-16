<?php

namespace App\Console\Commands\mysqltoes;

use App\Model\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class CaseQualityV2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'es:case_quality_v2';

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
        $setName = 'es_index_case_quality_v2';
        $updated = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = 0;
        $page = 1;
        while (true) {
            $start = ($page - 1) * 20;
            $res = DB::select("SELECT a.id,BLBH,rule_id,notice,JZHM,BRBH,code,error_field,FROM_UNIXTIME(unix_timestamp(b.AAC01), '%Y-%m-%d %H:%i:%s') as AAC01,a.AAC01 from case_quality_v2 as a inner join patient_info as b on a.JZHM=b.MED_REC_ID where a.AAC01 > '{$updated}' limit {$start}, 20");
            if (empty($res)) {
                return false;
            }
            $page++;
            $res = json_decode(json_encode($res), true);

            // 病例索引
            $es_params = [];
            $index = 'case_quality_v2_2023';
            foreach ($res as $item) {
                $lastId = $item['AAC01'];
                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['id']]];
                $es_params['body'][] = ['doc' => [
                    "BLBH" => $item['BLBH'],
                    "rule_id" => $item['rule_id'],
                    "notice" => $item['notice'],
                    "JZHM" => $item['JZHM'],
                    "BRBH" => $item['BRBH'],
                    "code" => $item['code'],
                    "error_field" => $item['error_field'],
                    "AAC01" => $item['AAC01'] ?: '1970-01-01 00:00:00',
                ], 'doc_as_upsert' => true];
            }
            app('es')->bulk($es_params);
            Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
        }
    }

}
