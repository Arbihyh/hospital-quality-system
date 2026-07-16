<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\EMR_BL_BL01_NEW;
use App\Model\Setting;
use Illuminate\Console\Command;

class SyncEmrBlBl01New extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:emrBlBl01New';

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
        $setName = 'es_index_emr_bl_bl01_new';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $data = EMR_BL_BL01_NEW::query()
            ->where('id','>',$lastId)
            ->orderBy('id')
            ->get()->toArray();

        $index = 'emr_bl_bl01_new';
        if (!empty($data)) {
            $es_params = [];
            foreach ($data as $value) {
                $lastId = $value['id'];
                echo $lastId.PHP_EOL;

                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $value['id']]];
                $es_params['body'][] = ['doc' => [
                    "data_id" => $value['id'],
                    "BLBH" => $value['BLBH'],
                    "JZHM" => $value['JZHM'],
                    "BLLX" => $value['BLLX'],
                    "BLLB" => $value['BLLB'],
                    "BLMC" => $value['BLMC'],
                    "MBLB" => $value['MBLB'],
                    "MBBH" => $value['MBBH'],
                    "CJSJ" => $value['CJSJ'],
                    "SQDH" => $value['SQDH'],
                    "DLLB" => $value['DLLB'],
                    "BLZT" => $value['BLZT'],
                    "SXYS" => $value['SXYS'],
                    "WCSJ" => $value['WCSJ'],
                    "BRKS" => $value['BRKS'],
                ], 'doc_as_upsert' => true];
            }

            app('es')->bulk($es_params);
            Setting::query()->updateOrInsert(['name'=>$setName],['content' => $lastId]);
        }

        $this->info('索引：'.$index.' 数据处理完成');
    }
}
