<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\PatientHospitalInfo;
use App\Model\PatientInfo;
use App\Model\Setting;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncAAB01 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:AAB01';

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
        $this->info('同步AAB01数据 - 数据开始处理');

        $setName = 'es_index_patient_info_AAB01';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        // 所有符合条件的病例信息
        $data = PatientInfo::query()
            ->where('id','>',$lastId)
            ->orderBy('id')
            ->get(['id','AAA28','MED_REC_ID','AAB01'])
            ->toArray();

        if (!empty($data)) {
            $es_params = [];
            $index = 'patient_info';

            foreach ($data as $value) {
                $lastId = $value['id'];

                if (empty($value['AAB01'])) {
                    $AAB01 = PatientHospitalInfo::query()
                        ->where('AAA28','=',$value['MED_REC_ID'])
                        ->value('AAB01');
                    if ($AAB01) {
                        // 记录入院时间
                        PatientInfo::query()->where('id','=',$value['id'])->update(['AAB01'=>$AAB01]);

                        // 要同步到Es的数据
                        $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $value['id']]];
                        $es_params['body'][] = ['doc' => [
                            "AAB01" => $value['AAB01']
                        ], 'doc_as_upsert' => true];
                    }
                }
            }

            // 同步数据到Es
            if (!empty($es_params)) {
                app('es')->bulk($es_params);
            }
            Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
        }

        $this->info('同步AAB01数据 - 数据处理完毕');
    }
}
