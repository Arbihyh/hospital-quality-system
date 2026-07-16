<?php

namespace App\Console\Commands\mysqltoes;

use App\Model\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class Pacs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'es:pacs';

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
        $setName = 'es_index_pacs';
        $updated = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = 0;
        $page = 1;
        while (true) {
            $start = ($page - 1) * 20;
            $pacs = DB::select('select * from PACS where id > "' . $updated . '"  order by id asc limit ' . $start . ', 20');
            if (empty($pacs)) {
                echo '没有数据';
                return false;
            }
            $page++;
            $pacs = json_decode(json_encode($pacs), true);

            // 病例索引
            $es_params = [];
            $index = 'pacs';
            foreach ($pacs as $item) {
                echo $item['id'].PHP_EOL;
                $lastId = $item['id'];
                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['id']]];
                $es_params['body'][] = ['doc' => [
                    "pacs_id" => $item['id'],
                    "StudyUid" => $item['StudyUid'],
                    "ZYH" => $item['ZYH'],
                    "MED_REC_ID" => $item['ZYH'],
                    "YLJGDM" => $item['YLJGDM'],
                    "JZLX" => $item['JZLX'],
                    "JZLSH" => $item['JZLSH'],
                    "MZZYBZ" => $item['MZZYBZ'],
                    "KH" => $item['KH'],
                    "KLX" => $item['KLX'],
                    "BRXM" => $item['BRXM'],
                    "BRXB" => $item['BRXB'],
                    "PatientID" => $item['PatientID'],
                    "PatientID1" => $item['PatientID1'],
                    "JCXMDM" => $item['JCXMDM'],
                    "JCXMDMYB" => $item['JCXMDMYB'],
                    "SQDH" => $item['SQDH'],
                    "KDSJ" => $item['KDSJ'],
                    "JYSJ" => $item['JYSJ'],
                    "ExamType" => $item['ExamType'],
                    "SBBM" => $item['SBBM'],
                    "YQBM" => $item['YQBM'],
                    "SQKS" => $item['SQKS'],
                    "SQKSMC" => $item['SQKSMC'],
                    "SQRGH" => $item['SQRGH'],
                    "SQRXM" => $item['SQRXM'],
                    "JCBGJGMC" => $item['JCBGJGMC'],
                    "JCKS" => $item['JCKS'],
                    "JCKSMC" => $item['JCKSMC'],
                    "JCYSGH" => $item['JCYSGH'],
                    "JCYS" => $item['JCYS'],
                    "BGSJ" => $item['BGSJ'],
                    "BGRQ" => $item['BGRQ'],
                    "BGRGH" => $item['BGRGH'],
                    "BGRXM" => $item['BGRXM'],
                    "SHRGH" => $item['SHRGH'],
                    "SHRXM" => $item['SHRXM'],
                    "JCBW" => $item['JCBW'],
                    "BWACR" => $item['BWACR'],
                    "JCMC" => $item['JCMC'],
                    "ZYJCXX1" => $item['ZYJCXX1'],
                    "ZYJCXX2" => $item['ZYJCXX2'],
                    "ZYJCXX3" => $item['ZYJCXX3'],
                    "YXBX" => $item['YXBX'],
                    "YXZD" => $item['YXZD'],
                    "BZHJY" => $item['BZHJY'],
                    "SFYYY" => $item['SFYYY'],
                    "XGBZ" => $item['XGBZ'],
                    "MJ" => $item['MJ'],
                    "YLYL1" => $item['YLYL1'],
                    "YLYL2" => $item['YLYL2'],
                ], 'doc_as_upsert' => true];
            }
            app('es')->bulk($es_params);
            Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
        }
    }

}
