<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\SecondaryOperation;
use App\Model\Setting;
use App\Model\Staff;
use Illuminate\Console\Command;

class SyncSecondaryOperation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:secondaryOperation';

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
        $setName = 'es_index_secondary_operation_2023';
        $index = 'secondary_operation_2023';

        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $data = SecondaryOperation::query()
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
                    "ZYH" => $value['AAA28'],
                    "ICD9_NAME" => $value['ICD9_NAME'],
                    "ICD9_ID1" => $value['ICD9_ID1'],
                    "OPE_LEVEL" => $value['OPE_LEVEL'],
                    "RJSS" => $value['RJSS'],
                    "OPE_TYPE" => $value['OPE_TYPE'],
                    "SSPB" => $value['SSPB'],
                    "HEAL_ID" => $value['HEAL_ID'],
                    "OPE_DATE" => $value['OPE_DATE'],
                    "START_TIME" => $value['START_TIME'],
                    "END_TIME" => $value['END_TIME'],
                ], 'doc_as_upsert' => true];
            }

            app('es')->bulk($es_params);
            Setting::query()->updateOrInsert(['name'=>$setName],['content' => $lastId]);
        }

        $this->info('索引：'.$index.' 数据处理完成');
    }
}
