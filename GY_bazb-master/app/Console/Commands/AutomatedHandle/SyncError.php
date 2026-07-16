<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\Error;
use App\Model\Setting;
use Illuminate\Console\Command;

class SyncError extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:error';

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
        $setName = 'es_index_error';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $data = Error::query()
            ->where('id','>',$lastId)
            ->orderBy('id')
            ->get()->toArray();
        $index = 'error';
        if (!empty($data)) {
            $es_params = [];
            foreach ($data as $value) {
                $lastId = $value['id'];
                echo $lastId.PHP_EOL;

                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $value['id']]];
                $es_params['body'][] = ['doc' => [
                    "data_id" => $value['id'],
                    "year" => $value['year'],
                    "month" => $value['month'],
                    "ZYH" => $value['ZYH'],
                    "desc" => $value['desc'],
                    "error_field" => $value['error_field'],
                    "error_name" => $value['error_name'],
                    "level" => $value['level'],
                    "type" => $value['type'],
                    "error_type" => $value['error_type'],
                    "error_rule" => $value['error_rule'],
                    "down" => $value['down'],
                    "coder_id" => $value['coder_id'],
                    "AAC11C" => $value['AAC11C'],
                    "status" => $value['status'],
                    "source" => $value['source'],
                    "category" => $value['category'],
                    "created_at" => $value['created_at'],
                    "AAC01" => $value['AAC01'],
                ], 'doc_as_upsert' => true];
            }

            app('es')->bulk($es_params);
            Setting::query()->updateOrInsert(['name'=>$setName],['content' => $lastId]);
        }

        $this->info('索引：'.$index.' 数据处理完成');
    }
}
