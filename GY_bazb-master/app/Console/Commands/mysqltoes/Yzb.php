<?php

namespace App\Console\Commands\mysqltoes;

use App\Model\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class Yzb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'es:yzb';

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
        $setName = 'es_index_yzb';
        $updated = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = 0;

        $page = 1;
        while (true) {
            $start = ($page - 1) * 20;
            $yzb = DB::select("select a.id,a.ZYH,a.BRKS,a.KZKS,a.KZSJ,a.KZYS,a.TZSJ,a.XZJDGH,a.YZMC,a.YZQX,a.XZJDSJ,a.YDYZLB,b.AAA28,b.AAB01,b.AAC01,a.is_has_kjyw,a.is_has_hlyw,a.kjyw_name,a.hlyw_name,a.YCJL,a.JLDW,a.SYPC,a.is_operation,FROM_UNIXTIME(unix_timestamp(a.TZQRSJ), '%Y-%m-%d %H:%i:%s') as TZQRSJ,a.ypmc,a.AAC01 from yzb as a left join patient_info as b on a.ZYH=b.MED_REC_ID and a.AAC01 > '{$updated}' order by id asc limit {$start}, 20");
            if (empty($yzb)) {
                echo '没有数据';
                return false;
            }
            $page++;
            $yzb = json_decode(json_encode($yzb), true);

            // 病例索引
            $es_params = [];
            $index = 'yzb_2023';
            foreach ($yzb as $item) {
                echo $item['ZYH'].PHP_EOL;
                $lastId = $item['AAC01'];
                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['id']]];
                $es_params['body'][] = ['doc' => [
                    "ZYH" => $item['ZYH'],
                    "BRKS" => $item['BRKS'],
                    "KZKS" => $item['KZKS'],
                    "KZSJ" => $item['KZSJ'],
                    "KZYS" => $item['KZYS'],
                    "TZSJ" => $item['TZSJ'],
                    "XZJDGH" => $item['XZJDGH'],
                    "YZMC" => $item['YZMC'],
                    "YZQX" => $item['YZQX'],
                    "XZJDSJ" => $item['XZJDSJ'],
                    "YDYZLB" => $item['YDYZLB'],
                    "AAA28" => $item['AAA28'],
                    "AAB01" => $item['AAB01'],
                    "AAC01" => $item['AAC01'],
                    "is_has_kjyw" => $item['is_has_kjyw'],
                    "is_has_hlyw" => $item['is_has_hlyw'],
                    "kjyw_name" => $item['kjyw_name'],
                    "hlyw_name" => $item['hlyw_name'],
                    "YCJL" => $item['YCJL'],
                    "JLDW" => $item['JLDW'],
                    "SYPC" => $item['SYPC'],
                    "is_operation" => $item['is_operation'],
                    "TZQRSJ" => $item['TZQRSJ'],
                    "ypmc" => $item['ypmc'],
                ], 'doc_as_upsert' => true];
            }
            app('es')->bulk($es_params);
            Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
        }
    }

}
