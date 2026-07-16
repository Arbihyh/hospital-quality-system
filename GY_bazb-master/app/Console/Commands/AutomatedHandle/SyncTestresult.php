<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\Bllb1;
use App\Model\Setting;
use App\Model\V_JMGS_TESTRESULT;
use Illuminate\Console\Command;

class SyncTestresult extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:testresult';

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
        $setName = 'es_index_v_jmgs_testresult_2023';
        $index = 'v_jmgs_testresult_2023';

        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;
dd($lastId);
        $data = V_JMGS_TESTRESULT::query()
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
                    "ZYH" => $value['ZYH'],
                    "TXM" => $value['TXM'],
                    "NO" => $value['NO'],
                    "XM" => $value['XM'],
                    "XB" => $value['XB'],
                    "NL" => $value['NL'],
                    "CH" => $value['CH'],
                    "YBLX" => $value['YBLX'],
                    "YBZT" => $value['YBZT'],
                    "AAA28" => $value['AAA28'],
                    "BQ" => $value['BQ'],
                    "LCZD" => $value['LCZD'],
                    "YW" => $value['YW'],
                    "JYXM" => $value['JYXM'],
                    "JG" => $value['JG'],
                    "TS" => $value['TS'],
                    "CKFW" => $value['CKFW'],
                    "DW" => $value['DW'],
                    "SJYS" => $value['SJYS'],
                    "JYY" => $value['JYY'],
                    "SHY" => $value['SHY'],
                    "CJSJ" => $value['CJSJ'],
                    "JSSJ" => $value['JSSJ'],
                    "BGSJ" => $value['BGSJ'],
                ], 'doc_as_upsert' => true];
            }

            app('es')->bulk($es_params);
            Setting::query()->updateOrInsert(['name'=>$setName],['content' => $lastId]);
        }

        $this->info('索引：'.$index.' 数据处理完成');
    }
}
