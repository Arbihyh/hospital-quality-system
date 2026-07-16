<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\MainDiagnosis;
use App\Model\Setting;
use Illuminate\Console\Command;

class SyncMainDiagnosis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:mainDiagnosis';

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
        $setName = 'es_index_main_diagnosis';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $data = MainDiagnosis::query()
            ->where('id','>',$lastId)
            ->orderBy('id')
            ->get()->toArray();
        $index = 'main_diagnosis';
        if (!empty($data)) {
            $es_params = [];
            foreach ($data as $value) {
                $lastId = $value['id'];
                echo $lastId.PHP_EOL;

                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $value['id']]];
                $es_params['body'][] = ['doc' => [
                    "data_id" => $value['id'],
                    "ZYH" => $value['AAA28'],
                    "ICD10_ID1" => $value['ICD10_ID1'],
                    "ICD10_NAME" => $value['ICD10_NAME'],
                    "DIA_ORDER" => $value['DIA_ORDER'],
                    "AREA_ID" => $value['AREA_ID'],
                    "BATCH_ID" => $value['BATCH_ID'],
                    "created_at" => $value['created_at'],
                    "LBMC" => $value['LBMC'],
                    "RYQK" => $value['RYQK']
                ], 'doc_as_upsert' => true];
            }

            app('es')->bulk($es_params);
            Setting::query()->updateOrInsert(['name'=>$setName],['content' => $lastId]);
        }

        $this->info('索引：'.$index.' 数据处理完成');
    }
}
