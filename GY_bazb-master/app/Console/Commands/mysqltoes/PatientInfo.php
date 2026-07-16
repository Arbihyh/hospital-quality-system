<?php

namespace App\Console\Commands\mysqltoes;

use App\Model\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class PatientInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'es:patient_info';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 定时生成执行相关诊断信息的统计信息';

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
     *
     */
    public function handle()
    {
        $setName = 'es_index_patient_info';
        $updated = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = 0;
        $page = 1;
        while (true) {
            $start = ($page - 1) * 20;
            $patientInfo = DB::select('select * from patient_info where AAC01 > "' . $updated . '" order by AAC01 asc limit ' . $start . ', 20');
            if (empty($patientInfo)) {
                echo '没有数据';
                return false;
            }
            $page++;
            $patientInfo = json_decode(json_encode($patientInfo), true);

            // 病例索引
            $es_params = [];
            $index = 'patient_info';
            foreach ($patientInfo as $item) {
                $lastId = $item['AAC01'];
                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['id']]];
                $es_params['body'][] = ['doc' => [
                    "data_id" => $item['id'],
                    "hospital_name" => $item['hospital_name'],
                    "AAA28" => $item['AAA28'],
                    "MED_REC_ID" => $item['MED_REC_ID'],
                    "AAA01" => $item['AAA01'],
                    "AAA02C" => $item['AAA02C'],
                    "AAA03" => $item['AAA03'],
                    "AAA04" => $item['AAA04'],
                    "AAA05C" => $item['AAA05C'],
                    "AAA40" => $item['AAA40'],
                    "AAA42" => $item['AAA42'],
                    "AEN01" => $item['AEN01'],
                    "AAA06C" => $item['AAA06C'],
                    "AAA07" => $item['AAA07'],
                    "AAA08C" => $item['AAA08C'],
                    "AEM01C" => $item['AEM01C'],
                    "AAB01" => $item['AAB01'],
                    "AAC01" => $item['AAC01'],
                    "AAC11N" => $item['AAC11N'],
                    "AAC04" => $item['AAC04'],
                    "ADA01" => $item['ADA01'],
                    "ADA0101" => $item['ADA0101'],
                    "AAA29" => $item['AAA29'],
                    "AAB06C" => $item['AAB06C'],
                    "ABC01N" => $item['ABC01N'],
                    "ICD9_NAME" => $item['ICD9_NAME'],
                    "ORG_STATE" => $item['ORG_STATE'],
                    "AAA26C" => $item['AAA26C'],
                    "ATTEND_GRP_CODE" => $item['ATTEND_GRP_CODE'],
                    "ATTEND_GRP_NAME" => $item['ATTEND_GRP_NAME'],
                    "F_D" => $item['F_D'],
                    "J" => $item['J'],
                    "coder_id" => $item['coder_id'],
                    "score" => $item['score'],
                    "is_error" => $item['is_error'],
                    "ABG01N" => $item['ABG01N'],
                    "ABG01C" => $item['ABG01C'],
                    "status" => $item['status'],
                    "created_at" => $item['created_at'],
                    "source" => $item['source'],
                    "level" => $item['level'],
                    "is_defect" => $item['is_defect'],
                    "is_defect_v2" => $item['is_defect_v2'],
                    "home_bmy_score" => $item['home_bmy_score'],
                    "AAC11C" => $item['AAC11C'],
                    "AEE03_CODE" => $item['AEE03_CODE'],
                    "AEE04_CODE" => $item['AEE04_CODE'],
                    "AEE08_CODE" => $item['AEE08_CODE'],
                    "ICD10_NAME" => $item['ICD10_NAME'],
                ], 'doc_as_upsert' => true];
            }
            app('es')->bulk($es_params);
            Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
        }
    }

}
