<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\Bllb294_45;
use App\Model\Setting;
use Illuminate\Console\Command;

class SyncBLLB294_45 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:bllb294_45';

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
        $setName = 'es_index_bllb294_45_2023';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $data = Bllb294_45::query()
            ->select(['bllb294_45.*','EMR_BL_BLXG.HJNR'])
            ->leftJoin('EMR_BL_BLXG','bllb294_45.BLBH','=','EMR_BL_BLXG.BLBH')
            ->where('bllb294_45.id','>',$lastId)
            ->orderBy('bllb294_45.id')
            ->get()->toArray();
        $index = 'bllb294_45_2023';
        if (!empty($data)) {
            $es_params = [];
            foreach ($data as $value) {
                $lastId = $value['id'];
                echo $lastId.PHP_EOL;

                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $value['id']]];
                $es_params['body'][] = ['doc' => [
                    "data_id" => $value['id'],
                    "BLBH" => $value['BLBH'],
                    "ZYH" => $value['ZYH'],
                    "TIWEN" => $value['TIWEN'],
                    "JCZB" => $value['JCZB'],
                    "JCJG" => $value['JCJG'],
                    "SZZ" => $value['SZZ'],
                    "HDZ" => $value['HDZ'],
                    "created_at" => $value['created_at'],
                    "AAC01" => $value['AAC01'],
                    "HJNR" => $value['HJNR'],
                ], 'doc_as_upsert' => true];
            }

            app('es')->bulk($es_params);
            Setting::query()->updateOrInsert(['name'=>$setName],['content' => $lastId]);
        }

        $this->info('索引：'.$index.' 数据处理完成');
    }
}
