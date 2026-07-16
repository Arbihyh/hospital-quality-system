<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\Bllb1;
use App\Model\Error;
use App\Model\Setting;
use Illuminate\Console\Command;

class SyncBLLB1 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:bllb1';

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
        $setName = 'es_index_bllb1_2023';
        $index = 'bllb1_2023';

        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $data = Bllb1::query()
            ->select(['bllb1.*','EMR_BL_BLXG.HJNR'])
            ->leftJoin('EMR_BL_BLXG','bllb1.BLBH','=','EMR_BL_BLXG.BLBH')
            ->where('bllb1.id','>',$lastId)
            ->orderBy('bllb1.id')
            ->get()->toArray();

        if (!empty($data)) {
            $es_params = [];
            foreach ($data as $value) {
                $lastId = $value['id'];
                echo $lastId.PHP_EOL;

                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $value['id']]];
                $es_params['body'][] = ['doc' => [
                    "data_id" => $value['id'],
                    "BLBH" => $value['BLBH'],
                    "AAA28" => $value['AAA28'],
                    "ZYH" => $value['ZYH'],
                    "MBLB" => $value['MBLB'],
                    "XM" => $value['XM'],
                    "RYRQ" => $value['RYRQ'],
                    "XB" => $value['XB'],
                    "CYRQ" => $value['CYRQ'],
                    "NL" => $value['NL'],
                    "ZYTS" => $value['ZYTS'],
                    "RYQK" => $value['RYQK'],
                    "CBZD" => $value['CBZD'],
                    "CBZD_FIRST" => $value['CBZD_FIRST'],
                    "ZLJG" => $value['ZLJG'],
                    "CYQK" => $value['CYQK'],
                    "CYZD" => $value['CYZD'],
                    "CYZD_FIRST" => $value['CYZD_FIRST'],
                    "CYYZ" => $value['CYYZ'],
                    "KS" => $value['KS'],
                    "CH" => $value['CH'],
                    "WB_ZYH" => $value['WB_ZYH'],
                    "AAB01" => $value['AAB01'],
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
