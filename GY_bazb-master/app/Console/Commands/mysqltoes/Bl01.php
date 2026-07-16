<?php

namespace App\Console\Commands\mysqltoes;

use App\Model\EMR_BL_BL01;
use App\Model\Setting;
use App\Services\ElasticsearchService;
use App\Services\OperationService;
use Illuminate\Console\Command;
use App\Services\BcService;
use Illuminate\Support\Facades\DB;


class Bl01 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'es:bl01';

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
        $setName = 'es_index_bl01';
        $updated = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = 0;

        $page = 1;
        while (true) {
            $start = ($page - 1) * 20;
            $bl01 = DB::select('SELECT a.BLBH as id,a.BLBH,a.MBLB,CJKS,if(p.AAB01 != "", p.AAB01, "1970-01-01 00:00:00") AS AAB01,if(p.AAC01 != ",", p.AAC01, "1970-01-01 00:00:00") AS AAC01,BRKS,JZHM,SXYS,BRBH,p.is_defect,BLLB,BLMC,b.HJNR,if(CJSJ != "", CJSJ, "1970-01-01 00:00:00") AS CJSJ,if(ZXSJ !="", ZXSJ, "1970-01-01 00:00:00") AS ZXSJ,a.operation_time,a.operation_handler,a.operation_handler_code,a.operation_end_time,p.AAC11N,a.AAC01 from EMR_BL_BL01 as a inner join EMR_BL_BLXG as b on a.BLBH=b.BLBH left join patient_info as p on a.JZHM=p.MED_REC_ID where a.BLZT<>9 AND a.AAC01 > "'.$updated.'" order by BLBH asc limit '.$start.',20');
            if(empty($bl01)){
                echo "没有数据";
                return false;
            }
            $page++;
            $bl01 = json_decode(json_encode($bl01), true);

            // 病例索引
            $es_params = [];
            $index = 'bl01_202303';
            foreach ($bl01 as $item) {
                $lastId = $item['AAC01'];
                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['id']]];
                $es_params['body'][] = ['doc' => [
                    "BLBH" => $item['BLBH'],
                    "MBLB" => $item['MBLB'],
                    "CJKS" => $item['CJKS'],
                    "AAB01" => $item['AAB01'],
                    "AAC01" => $item['AAC01'],
                    "BRKS" => $item['BRKS'],
                    "JZHM" => $item['JZHM'],
                    "SXYS" => $item['SXYS'],
                    "BRBH" => $item['BRBH'],
                    "is_defect" => $item['is_defect'],
                    "BLLB" => $item['BLLB'],
                    "BLMC" => $item['BLMC'],
                    "HJNR" => $item['HJNR'],
                    "CJSJ" => $item['CJSJ'],
                    "ZXSJ" => $item['ZXSJ'],
                    "operation_time" => $item['operation_time'],
                    "operation_handler" => $item['operation_handler'],
                    "operation_handler_code" => $item['operation_handler_code'],
                    "operation_end_time" => $item['operation_end_time'],
                    "AAC11N" => $item['AAC11N'],
                ], 'doc_as_upsert' => true];
            }
            app('es')->bulk($es_params);
            Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
        }


    }

}
