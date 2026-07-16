<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\BaMrClassNumber;
use App\Model\MS_GHMX;
use App\Model\Setting;
use Illuminate\Console\Command;

class SyncMsGhmx extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:msGhmx';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $setName = 'es_index_ms_ghmx_2023';
        $index = 'ms_ghmx_2023';

        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $data = MS_GHMX::query()
            ->where('id','>',$lastId)
            ->orderBy('id')
            ->get()->toArray();
        if (!empty($data)) {
            $es_params = [];
            foreach ($data as $value) {
                $lastId = $value['id'];
                echo $lastId.PHP_EOL;

                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $value['id']]];
                $es_params['body'][] = ['doc' => [
                    "data_id" => $value['id'],
                    "SBXH" => $value['SBXH'],
                    "BRID" => $value['BRID'],
                    "BRXZ" => $value['BRXZ'],
                    "GHSJ" => $value['GHSJ'],
                    "GHLB" => $value['GHLB'],
                    "JZRQ" => $value['JZRQ'],
                    "HZRQ" => $value['HZRQ'],
                    "JZZT" => $value['JZZT'],
                    "JZXH" => $value['JZXH'],
                    "KSDM" => $value['KSDM'],
                    "YSDM" => $value['YSDM'],
                ], 'doc_as_upsert' => true];
            }

            app('es')->bulk($es_params);
            Setting::query()->updateOrInsert(['name'=>$setName],['content' => $lastId]);
        }

        $this->info('索引：'.$index.' 数据处理完成');
    }
}
