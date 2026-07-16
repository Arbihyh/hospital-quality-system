<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\BA_BASE;
use App\Model\Setting;
use Illuminate\Console\Command;

class SyncBaBase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:baBase';

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
        $setName = 'es_index_ba_base';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $data = BA_BASE::query()
            ->where('id','>',$lastId)
            ->orderBy('id')
            ->get()->toArray();
        $index = 'ba_base';
        if (!empty($data)) {
            $es_params = [];
            foreach ($data as $value) {
                $lastId = $value['id'];
                echo $lastId.PHP_EOL;
                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $value['id']]];
                $es_params['body'][] = ['doc' => [
                    "data_id" => $value['id'],
                    "bah" => $value['bah'],
                    "zycs" => $value['zycs'],
                    "brxm" => $value['brxm'],
                    "brsfzh" => $value['brsfzh'],
                    "cysj" => $value['cysj'],
                    "cyksname" => $value['cyksname'],
                    "mr_archive_time" => $value['mr_archive_time']
                ], 'doc_as_upsert' => true];
            }

            app('es')->bulk($es_params);
            Setting::query()->updateOrInsert(['name'=>$setName],['content' => $lastId]);
        }

        $this->info('索引：'.$index.' 数据处理完成');
    }
}
