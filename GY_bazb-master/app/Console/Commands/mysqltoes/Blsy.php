<?php

namespace App\Console\Commands\mysqltoes;

use App\Model\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class Blsy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'es:blsy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command blsy_2023';

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
        $setName = 'es_index_blsy';
        $updated = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = 0;
        $page = 1;
        while (true) {
            $start = ($page - 1) * 20;
            $blsy = DB::select('select id,id as data_id,BLBH,SYYS,SYSJ,JLSJ from EMR_BL_BLSY where id > "' . $updated . '" order by id asc limit '.$start.',20');
            if (empty($blsy)) {
                echo "没有数据";
                return false;
            }
            $page++;
            $blsy = json_decode(json_encode($blsy), true);

            // 病例索引
            $es_params = [];
            $index = 'blsy_2023';
            foreach ($blsy as $item) {
                $lastId = $item['id'];
                echo $item['BLBH'].PHP_EOL;
                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['id']]];
                $es_params['body'][] = ['doc' => [
                    "data_id" => $item['data_id'],
                    "BLBH" => $item['BLBH'],
                    "SYYS" => $item['SYYS'],
                    "SYSJ" => $item['SYSJ'] ?: '1970-01-01 00:00:00',
                    "JLSJ" => $item['JLSJ'] ?: '1970-01-01 00:00:00',
                ], 'doc_as_upsert' => true];
            }
            app('es')->bulk($es_params);
            Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
        }

    }

}
