<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\PACS;
use App\Model\PatientInfo;
use App\Model\Setting;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncPacsZyh extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:pacsZyh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步AAB01字段';

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
        $setName = 'es_index_pacs_zyh';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $page = 1;
        while (true) {
            // 所有符合条件的病例信息
            $list = PatientInfo::query()
                ->where('id', '>', $lastId)
                ->whereNotNull('MED_REC_ID')
                ->where('AAB01', '!=', '')
                ->where('AAC01', '!=', '')
                ->orderBy('id')
                ->paginate(100,['id','AAA28', 'MED_REC_ID', 'AAB01', 'AAC01'],'page',$page)
                ->toArray();
            if (empty($list['data'])) {
                break;
            }
            $page++;

            $data = $list['data'];
            $es_params = [];
            $index = 'pacs';

            foreach ($data as $value) {
                $newLastId = $value['id'];
                echo $newLastId.PHP_EOL;

                $AAA28 = $value['AAA28'];
                $MED_REC_ID = $value['MED_REC_ID'];
                $AAB01 = $value['AAB01'];
                $AAC01 = $value['AAC01'];

                $pacsData = PACS::query()
                    ->where('JZLSH','=',$AAA28)
                    ->whereBetween('KDSJ',[$AAB01,$AAC01])
                    ->get()->toArray();

                $pacsIdList = [];
                if (!empty($pacsData)) {
                    foreach ($pacsData as $val) {
                        $pacsIdList[] = $val['id'];

                        // 要同步到Es的数据
                        $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $val['id']]];
                        $es_params['body'][] = ['doc' => [
                            "ZYH" => $MED_REC_ID
                        ], 'doc_as_upsert' => true];
                    }
                }

                if ($pacsIdList) {
                    PACS::query()->whereIn('id',$pacsIdList)->update(['ZYH'=>$MED_REC_ID]);
                }
            }

            // 同步数据到Es
            if (!empty($es_params)) {
                app('es')->bulk($es_params);
            }
        }

        if (!empty($newLastId)) {
            Setting::query()->updateOrInsert(['name'=>$setName],['content' => $newLastId]);
        }

        $this->info("清洗PACS表中ZYH字段 - 完毕");
    }
}
