<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\Bllb303;
use App\Model\Setting;
use Illuminate\Console\Command;

class SyncBLLB303 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:bllb303';

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
        $setName = 'es_index_bllb303_2023';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $data = Bllb303::query()
            ->select(['bllb303.*','EMR_BL_BLXG.HJNR'])
            ->leftJoin('EMR_BL_BLXG','bllb303.BLBH','=','EMR_BL_BLXG.BLBH')
            ->where('bllb303.id','>',$lastId)
            ->orderBy('bllb303.id')
            ->get()->toArray();

        $index = 'bllb303_2023';
        if (!empty($data)) {
            $es_params = [];
            foreach ($data as $value) {
                $lastId = $value['id'];
                echo $lastId.PHP_EOL;

                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $value['id']]];
                $es_params['body'][] = ['doc' => [
                    "data_id" => $value['id'],
                    "ZYH" => $value['ZYH'],
                    "AAA28" => $value['AAA28'],
                    "BLBH" => $value['BLBH'],
                    "AAB01" => $value['AAB01'],
                    "AAC01" => $value['AAC01'],
                    "MBLB" => $value['MBLB'],
                    "SSRQ" => $value['SSRQ'],
                    "SSKSSJ" => $value['SSKSSJ'],
                    "SSJSSJ" => $value['SSJSSJ'],
                    "SQZD" => $value['SQZD'],
                    "SZZD_ONE" => $value['SZZD_ONE'],
                    "SZZD" => $value['SZZD'],
                    "SSMC_ONE" => $value['SSMC_ONE'],
                    "SSMC" => $value['SSMC'],
                    "SSZD" => $value['SSZD'],
                    "SSZ" => $value['SSZ'],
                    "ZS" => $value['ZS'],
                    "SSQM" => $value['SSQM'],
                    "CH" => $value['CH'],
                    "JLSJ" => $value['JLSJ'],
                    "CJSJ" => $value['CJSJ'],
                    "XGSJ" => $value['XGSJ'],
                    "WCSJ" => $value['WCSJ'],
                    "HJNR" => $value['HJNR'],
                ], 'doc_as_upsert' => true];
            }

            app('es')->bulk($es_params);
            Setting::query()->updateOrInsert(['name'=>$setName],['content' => $lastId]);
        }

        $this->info('索引：'.$index.' 数据处理完成');
    }
}
