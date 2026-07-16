<?php

namespace App\Console\Commands\mysqltoes;

use App\Model\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class Mzjl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'es:mzjl';

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
        $setName = 'es_index_mzjl';
        $updated = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = 0;
        $page = 1;
        while (true) {
            $start = ($page - 1) * 20;
            $mzjl = DB::select('select * from mzjl where id > "' . $updated . '" order by id asc limit ' . $start . ', 20');
            if (empty($mzjl)) {
                echo '没有数据';
                return false;
            }
            $page++;
            $mzjl = json_decode(json_encode($mzjl), true);

            // 病例索引
            $es_params = [];
            $index = 'mzjl_2023';
            foreach ($mzjl as $item) {
                $lastId = $item['id'];
                echo $item['id'].PHP_EOL;
                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['id']]];
                $es_params['body'][] = ['doc' => [
                    "uniid" => $item['id'],
                    "HOSPIZATIONID" => $item['HOSPIZATIONID'],
                    "OPERATESTARTTIME" => $item['OPERATESTARTTIME'],
                    "OPERATEENDTIME" => $item['OPERATEENDTIME'],
                    "PREOPERATIONNAME" => $item['PREOPERATIONNAME'],
                    "OPERATOR" => $item['OPERATOR'],
                ], 'doc_as_upsert' => true];
            }
            app('es')->bulk($es_params);
            Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
        }
    }

}
