<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\Setting;
use App\Model\ZY_HCMX;
use Illuminate\Console\Command;

class SyncZyHcmx extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:zyHcmx';

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
        $setName = 'es_index_zy_hcmx';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $data = ZY_HCMX::query()
            ->where('id','>',$lastId)
            ->orderBy('id')
            ->get()->toArray();
        $index = 'zy_hcmx';
        if (!empty($data)) {
            $es_params = [];
            foreach ($data as $value) {
                $lastId = $value['id'];
                echo $lastId.PHP_EOL;

                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $value['id']]];
                $es_params['body'][] = ['doc' => [
                    "data_id" => $value['id'],
                    "ZYH" => $value['ZYH'],
                    "HCRQ" => $value['HCRQ'],
                    "ZZRQ" => $value['ZZRQ'],
                    "HCLX" => $value['HCLX'],
                    "HQCH" => $value['HQCH'],
                    "HHCH" => $value['HHCH'],
                    "HQKS" => $value['HQKS'],
                    "HHKS" => $value['HHKS'],
                    "HQBQ" => $value['HQBQ'],
                    "HHBQ" => $value['HHBQ'],
                    "JSCS" => $value['JSCS'],
                    "CZGH" => $value['CZGH'],
                    "JGID" => $value['JGID'],
                ], 'doc_as_upsert' => true];
            }

            app('es')->bulk($es_params);
            Setting::query()->updateOrInsert(['name'=>$setName],['content' => $lastId]);
        }

        $this->info('索引：'.$index.' 数据处理完成');
    }
}
