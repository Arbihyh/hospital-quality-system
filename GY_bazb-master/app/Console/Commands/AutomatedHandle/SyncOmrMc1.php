<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\MS_CF01;
use App\Model\Setting;
use Illuminate\Console\Command;

class SyncOmrMc1 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:mc1';

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
        $setName = 'es_index_omr_mc1_2023';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $data = MS_CF01::query()
            ->where('id','>',$lastId)
            ->orderBy('id')
            ->get()->toArray();
        $index = 'omr_mc1_2023';
        if (!empty($data)) {
            $es_params = [];
            foreach ($data as $value) {
                $lastId = $value['id'];
                echo $lastId.PHP_EOL;

                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $value['id']]];
                $es_params['body'][] = ['doc' => [
                    "data_id" => $value['id'],
                    "JZXH" => $value['JZXH'],
                    "CFSB" => $value['CFSB']
                ], 'doc_as_upsert' => true];
            }

            app('es')->bulk($es_params);
            Setting::query()->updateOrInsert(['name'=>$setName],['content' => $lastId]);
        }

        $this->info('索引：'.$index.' 数据处理完成');
    }
}
