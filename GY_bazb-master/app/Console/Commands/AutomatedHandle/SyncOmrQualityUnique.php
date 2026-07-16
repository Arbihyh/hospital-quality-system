<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\OmrQuality;
use App\Model\Setting;
use Illuminate\Console\Command;

class SyncOmrQualityUnique extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:omrQualituUnique';

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
        $setName = 'es_index_omr_quality_unique_2023';
        $index = 'omr_quality_unique_2023';

        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;
        $page = 1;
        while (true) {
            $data = OmrQuality::query()
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->groupBy('BLBH')
                ->paginate(500,['*'],'page',$page)
                ->toArray();
            if (empty($data['data'])) {
                break;
            }
            echo $page.PHP_EOL;
            $page++;

            $es_params = [];
            foreach ($data['data'] as $value) {
                $newLastId = $value['id'];

                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $value['BLBH']]];
                $es_params['body'][] = ['doc' => [
                    "BLBH" => $value['BLBH'],
                    "JZXH" => $value['JZXH'],
                    "MED_REC_ID" => $value['JZXH'],
                    "BRID" => $value['BRID'],
                    "rule_id" => $value['rule_id'],
                    "code" => $value['code'],
                    "error_field" => $value['error_field'],
                    "basis" => $value['basis'],
                    "mzh" => $value['mzh'],
                    "xm" => $value['xm'],
                    "xb" => $value['xb'],
                    "nl" => $value['nl'],
                    "cbzd" => $value['cbzd'],
                    "BRKS" => $value['BRKS'],
                    "SFZH" => $value['SFZH'],
                    "SXYS" => $value['SXYS'],
                    "jzsj" => $value['jzsj'],
                    "bl_type" => $value['bl_type'],
                ], 'doc_as_upsert' => true];
            }

            app('es')->bulk($es_params);
            Setting::query()->updateOrInsert(['name'=>$setName],['content' => $newLastId]);
        }

        $this->info('索引：'.$index.' 数据处理完成');
    }
}
