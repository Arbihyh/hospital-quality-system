<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\OMR_BL01;
use App\Model\Setting;
use Illuminate\Console\Command;

class SyncOmrBl01 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:omrBl01';

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
        $setName = 'es_index_omr_bl01_2023';
        $index = 'omr_bl01_2023';

        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $data = OMR_BL01::query()
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
                    'data_id' => $value['id'],
                    "BLBH" => $value['BLBH'],
                    "JZXH" => $value['JZXH'],
                    "BRID" => $value['BRID'],
                    "SFZH" => $value['SFZH'],
                    "BLLX" => $value['BLLX'],
                    "BLLB" => $value['BLLB'],
                    "BLMC" => $value['BLMC'],
                    "DLLB" => $value['DLLB'],
                    "DLJ" => $value['DLJ'],
                    "JLSJ" => $value['JLSJ'],
                    "CJSJ" => $value['CJSJ'],
                    "WCSJ" => $value['WCSJ'],
                    "SXYS" => $value['SXYS'],
                    "SXKS" => $value['SXKS'],
                    "BRKS" => $value['BRKS'],
                    "BLZT" => $value['BLZT'],
                    "BLNR_TXT" => $value['BLNR_TXT'],
                    "zs" => $value['zs'],
                    "xbs" => $value['xbs'],
                    "jws" => $value['jws'],
                    "lxbxs" => $value['lxbxs'],
                    "tgjc" => $value['tgjc'],
                    "fzjc" => $value['fzjc'],
                    "cbzd" => $value['cbzd'],
                    "zlyj" => $value['zlyj'],
                    "tx" => $value['tx'],
                    "mzh" => $value['mzh'],
                    "xm" => $value['xm'],
                    "xb" => $value['xb'],
                    "jzsj" => $value['jzsj'],
                    "ks" => $value['ks'],
                    "nl" => $value['nl'],
                    "nl1" => $value['nl1'],
                    "xy" => $value['xy'],
                    "xy_json" => $value['xy_json'],
                    "is_defect" => $value['is_defect'],
                    "bl_type" => $value['bl_type'],
                ], 'doc_as_upsert' => true];
            }

            app('es')->bulk($es_params);
            Setting::query()->updateOrInsert(['name'=>$setName],['content' => $lastId]);
        }

        $this->info('索引：'.$index.' 数据处理完成');
    }
}
