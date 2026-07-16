<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\OmrBlsy;
use App\Model\Setting;
use Illuminate\Console\Command;

class SyncOmrBlsy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:omrBlsy';

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
        $setName = 'es_index_omr_blsy_2023';
        $index = 'omr_blsy_2023';

        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $data = OmrBlsy::query()
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
                    "JLXH" => $value['JLXH'],
                    "BLBH" => $value['BLBH'],
                    "SYYS" => $value['SYYS'],
                    "SYSJ" => $value['SYSJ'],
                    "JLSJ" => $value['JLSJ'],
                    "QMLSH" => $value['QMLSH'],
                    "DLLJ" => $value['DLLJ'],
                    "QMYS" => $value['QMYS'],
                ], 'doc_as_upsert' => true];
            }

            app('es')->bulk($es_params);
            Setting::query()->updateOrInsert(['name'=>$setName],['content' => $lastId]);
        }

        $this->info('索引：'.$index.' 数据处理完成');
    }
}
