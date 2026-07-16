<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\Bllb292;
use App\Model\Setting;
use Illuminate\Console\Command;

class SyncBLLB292 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:bllb292';

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
        $setName = 'es_index_bllb292_2023';
        $index = 'bllb292_2023';

        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $data = Bllb292::query()
            ->select(['bllb292.*','EMR_BL_BLXG.HJNR'])
            ->leftJoin('EMR_BL_BLXG','bllb292.BLBH','=','EMR_BL_BLXG.BLBH')
            ->where('bllb292.id','>',$lastId)
            ->orderBy('bllb292.id')
            ->get()->toArray();

        if (!empty($data)) {
            $es_params = [];
            foreach ($data as $value) {
                $lastId = $value['id'];
                echo $lastId.PHP_EOL;

                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $value['ZYH']]];
                $es_params['body'][] = ['doc' => [
                    "data_id" => $value['id'],
                    "BLBH" => $value['BLBH'],
                    "AAA28" => $value['AAA28'],
                    "ZYH" => $value['ZYH'],
                    "AAB01" => $value['AAB01'],
                    "AAC01" => $value['AAC01'],

                    "XM" => $value['XM'],
                    "CSD" => $value['CSD'],
                    "XB" => $value['XB'],
                    "ZHY" => $value['ZHY'],
                    "NL" => $value['NL'],
                    "RYSJ" => $value['RYSJ'],
                    "MZ" => $value['MZ'],
                    "JLSJ" => $value['JLSJ'],
                    "HY" => $value['HY'],
                    "BSCSZ" => $value['BSCSZ'],
                    "ZHS" => $value['ZHS'],
                    "XBS" => $value['XBS'],
                    "JWS" => $value['JWS'],
                    "GRS" => $value['GRS'],
                    "YJJHYS" => $value['YJJHYS'],
                    "HYS" => $value['HYS'],
                    "JZS" => $value['JZS'],
                    "TGJC" => $value['TGJC'],
                    "FZJC" => $value['FZJC'],
                    "CBZD" => $value['CBZD'],
                    "YSQM" => $value['YSQM'],
                    "CHH" => $value['CHH'],
                    "ZHUANKE" => $value['ZHUANKE'],
                    "CBZB_FIRST" => $value['CBZB_FIRST'],
                    "HJNR" => $value['HJNR'],
                ], 'doc_as_upsert' => true];
            }

            app('es')->bulk($es_params);
            Setting::query()->updateOrInsert(['name'=>$setName],['content' => $lastId]);
        }

        $this->info('索引：'.$index.' 数据处理完成');
    }
}
