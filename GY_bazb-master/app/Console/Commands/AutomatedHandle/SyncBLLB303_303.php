<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\Bllb303_303;
use App\Model\Setting;
use Illuminate\Console\Command;

class SyncBLLB303_303 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:bllb303_303';

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
        $setName = 'es_index_bllb303_303_2023';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $data = Bllb303_303::query()
            ->select(['bllb303_303.*','EMR_BL_BLXG.HJNR'])
            ->leftJoin('EMR_BL_BLXG','bllb303_303.BLBH','=','EMR_BL_BLXG.BLBH')
            ->where('bllb303_303.id','>',$lastId)
            ->orderBy('bllb303_303.id')
            ->get()->toArray();
        $index = 'bllb303_303_2023';
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
                    "XM" => $value['XM'],
                    "XB" => $value['XB'],
                    "NL" => $value['NL'],
                    "BS" => $value['BS'],
                    "CH" => $value['CH'],
                    "SSRQ" => $value['SSRQ'],
                    "SSSJ" => $value['SSSJ'],
                    "SQZD" => $value['SQZD'],
                    "YC" => $value['YC'],
                    "CC" => $value['CC'],
                    "SZZD" => $value['SZZD'],
                    "DCRQ" => $value['DCRQ'],
                    "SSMC" => $value['SSMC'],
                    "SSMC_FIRST" => $value['SSMC_FIRST'],
                    "MZFF" => $value['MZFF'],
                    "SSZDZ" => $value['SSZDZ'],
                    "SSZ" => $value['SSZ'],
                    "ZS" => $value['ZS'],
                    "SSJG" => $value['SSJG'],
                    "SSZQM" => $value['SSZQM'],
                    "JLSJ" => $value['JLSJ'],
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
