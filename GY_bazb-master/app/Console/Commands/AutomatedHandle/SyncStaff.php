<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\Setting;
use App\Model\Staff;
use Illuminate\Console\Command;

class SyncStaff extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:staff';

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
        $setName = 'es_index_staff_2023';
        $index = 'staff_2023';

        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $data = Staff::query()
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
                    "name" => $value['name'],
                    "code" => $value['code'],
                    "base_code" => $value['base_code'],
                    "sfz" => $value['sfz'],
                    "ksdm" => $value['ksdm'],
                    "ygjb" => $value['ygjb'],
                    "ygjb_text" => $value['ygjb_text'],
                    "status" => $value['status'],
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
