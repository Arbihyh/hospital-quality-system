<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\EMR_BL_BASYSJ;
use App\Model\MainOperation;
use App\Model\Setting;
use Illuminate\Console\Command;

class SyncMainOperation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:mainOperation';

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
        $setName = 'es_index_main_operation';
        $index = 'main_operation';

        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $data = MainOperation::query()
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
                    "AAA28" => $value['AAA28'],
                    "ICD9_ID1" => $value['ICD9_ID1'],
                    "ICD9_NAME" => $value['ICD9_NAME'],
                    "OPE_DATE" => $value['OPE_DATE'],
                    "OPE_MAN_NAME" => $value['OPE_MAN_NAME'],
                    "OPE_MAN_CODE" => $value['OPE_MAN_CODE'],
                    "FRIST_ASSISTANT_CODE" => $value['FRIST_ASSISTANT_CODE'],
                    "FRIST_ASSISTANT_NAME" => $value['FRIST_ASSISTANT_NAME'],
                    "SECOND_ASSISTANT_CODE" => $value['SECOND_ASSISTANT_CODE'],
                    "SECOND_ASSISTANT_NAME" => $value['SECOND_ASSISTANT_NAME'],
                    "HOCUS_WAY_ID" => $value['HOCUS_WAY_ID'],
                    "INCISION_GRADE_ID" => $value['INCISION_GRADE_ID'],
                    "HOCUS_MAN_CODE" => $value['HOCUS_MAN_CODE'],
                    "HOCUS_MAN_NAME" => $value['HOCUS_MAN_NAME'],
                    "START_TIME" => $value['START_TIME'],
                    "END_TIME" => $value['END_TIME'],
                    "OPE_ORDER" => $value['OPE_ORDER'],
                    "OPE_LEVEL" => $value['OPE_LEVEL'],
                    "RJSS" => $value['RJSS'],
                    "AREA_ID" => $value['AREA_ID'],
                    "BATCH_ID" => $value['BATCH_ID'],
                    "created_at" => $value['created_at'],
                    "AAC01" => $value['AAC01'],
                    "OPE_TYPE" => $value['OPE_TYPE'],
                    "SSPB" => $value['SSPB'],
                    "HEAL_ID" => $value['HEAL_ID']
                ], 'doc_as_upsert' => true];
            }

            app('es')->bulk($es_params);
            Setting::query()->updateOrInsert(['name'=>$setName],['content' => $lastId]);
        }

        $this->info('索引：'.$index.' 数据处理完成');
    }
}
