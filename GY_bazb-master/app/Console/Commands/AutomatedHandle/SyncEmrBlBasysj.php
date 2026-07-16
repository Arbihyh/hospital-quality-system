<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\EMR_BL_BASYSJ;
use App\Model\Setting;
use Illuminate\Console\Command;

class SyncEmrBlBasysj extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:emrBlBasysj';

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
        $setName = 'es_index_emr_bl_basysj';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $data = EMR_BL_BASYSJ::query()
            ->where('id','>',$lastId)
            ->orderBy('id')
            ->get()->toArray();
        $index = 'emr_bl_basysj';
        if (!empty($data)) {
            $es_params = [];
            foreach ($data as $value) {
                $lastId = $value['id'];
                echo $lastId.PHP_EOL;

                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $value['id']]];
                $es_params['body'][] = ['doc' => [
                    "data_id" => $value['id'],
                    "JLXH" => $value['JLXH'],
                    "JZHM" => $value['JZHM'],
                    "BLBH" => $value['BLBH'],
                    "XMXH" => $value['XMXH'],
                    "XMMC" => $value['XMMC'],
                    "XMQZ" => $value['XMQZ'],
                    "DYYS" => $value['DYYS'],
                    "DLLJ" => $value['DLLJ'],
                    "GLZD" => $value['GLZD'],
                    "KSMRZ" => $value['KSMRZ'],
                    "SYBTX" => $value['SYBTX'],
                    "XMNM" => $value['XMNM']
                ], 'doc_as_upsert' => true];
            }

            app('es')->bulk($es_params);
            Setting::query()->updateOrInsert(['name'=>$setName],['content' => $lastId]);
        }

        $this->info('索引：'.$index.' 数据处理完成');
    }
}
