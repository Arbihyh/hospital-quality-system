<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\EMR_BL_BLSY;
use App\Model\Setting;
use Illuminate\Console\Command;

class SyncEmrBlBlsy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:emrBlBlsy';

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
        $setName = 'es_index_blsy_2023';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;
        $batchSize = 1000; // 每批处理1000条
        $index = 'blsy_2023';

        while (true) {
            // 分批获取数据
            $data = EMR_BL_BLSY::query()
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($batchSize)
                ->get()
                ->toArray();

            // 如果没有数据了，退出循环
            if (empty($data)) {
                break;
            }

            $es_params = [];
            foreach ($data as $value) {
                $lastId = $value['id'];

                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $value['id']]];
                $es_params['body'][] = ['doc' => [
                    "data_id" => $value['id'],
                    "BLBH" => $value['BLBH'],
                    "SYYS" => $value['SYYS'],
                    "SYSJ" => $value['SYSJ'],
                    "JLSJ" => $value['JLSJ']
                ], 'doc_as_upsert' => true];

                // 每500条记录处理一次，避免单个请求过大
                if (count($es_params['body']) >= 1000) {
                    app('es')->bulk($es_params);
                    $es_params = [];

                    // 更新最后处理的ID
                    Setting::query()->updateOrInsert(
                        ['name' => $setName],
                        ['content' => $lastId]
                    );
                }
            }

            // 处理剩余的记录
            if (!empty($es_params['body'])) {
                app('es')->bulk($es_params);
                Setting::query()->updateOrInsert(
                    ['name' => $setName],
                    ['content' => $lastId]
                );
            }

            // 释放内存
            unset($data);
            unset($es_params);
            gc_collect_cycles();
        }

        $this->info('索引：'.$index.' 数据处理完成');
    }
}
