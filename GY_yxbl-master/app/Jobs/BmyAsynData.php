<?php

namespace App\Jobs;

use App\Model\ErrorRule;
use App\Model\HomeQuality;
use App\Model\PatientInfo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BmyAsynData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        //
        $this->data = $data;
        Log::info('BmyAsynData constructor data:', $this->data);

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if(empty($this->data)){
            exit('bmy data empty');
        }

        Log::info('start bmy data zyh:'.$this->data['zyh']);

        // 质控结果处理
        HomeQuality::query()->where('ZYH', '=', $this->data['zyh'])->update(['is_del' => 1]);
        if (!empty($this->data['err_rule_data'])) {
            HomeQuality::query()->insert($this->data['err_rule_data']);
        }


        $errorRuleData = ErrorRule::query()->get()->toArray();
        $errorRuleData = array_column($errorRuleData, null, 'id');

        $homeQualityData = HomeQuality::query()->where('ZYH', '=', $this->data['zyh'])->get();
        if (!$homeQualityData->isEmpty()) {

            $es_params1 = [];
            $es_params2 = [];
            foreach ($homeQualityData->toArray() as $value) {
                if (empty($errorRuleData[$value['error_rule']])) {
                    continue;
                }
                if ($value['is_del'] == 1) {
                    $es_params1['body'][] = ['delete' => ['_index' => 'home_quality', '_id' => $value['id']]];
                } else {
                    $es_params2['body'][] = ['update' => ['_index' => 'home_quality', '_id' => $value['id']]];
                    $es_params2['body'][] = ['doc' => [
                        'data_id' => $value['id'],
                        'hospital_name' => $value['hospital_name'],
                        'AAA28' => $value['AAA28'],
                        'ZYH' => $this->data['zyh'],
                        'AAC01' => $value['AAC01'],
                        'error_rule' => $value['error_rule'],
                        'field' => $errorRuleData[$value['error_rule']]['auth'],
                        'field_name' => $errorRuleData[$value['error_rule']]['field'],
                        'desc' => $errorRuleData[$value['error_rule']]['desc'],
                        'level' => $errorRuleData[$value['error_rule']]['bmy_level'],
                        'type' => $errorRuleData[$value['error_rule']]['type'],
                        'down' => $errorRuleData[$value['error_rule']]['down'],
                        'error_type' => $errorRuleData[$value['error_rule']]['error_type'],
                        'category' => $errorRuleData[$value['error_rule']]['category'],
                        'ZKDX' => $errorRuleData[$value['error_rule']]['ZKDX'],
                        'ZKFL' => $errorRuleData[$value['error_rule']]['ZKFL'],
                        'basis' => $value['basis'],
                        'AAC11C' => $value['AAC11C'],
                        'AEE03_CODE' => $value['AEE03_CODE'],
                        'AEE04_CODE' => $value['AEE04_CODE'],
                        'AEE08_CODE' => $value['AEE08_CODE'],
                        'is_del' => $value['is_del'],
                        'ICD10_ID1' => $value['ICD10_ID1'],
                        'ICD10_NAME' => $value['ICD10_NAME'],
                        'ICD9_ID1' => $value['ICD9_ID1'],
                        'ICD9_NAME' => $value['ICD9_NAME'],
                    ], 'doc_as_upsert' => true];
                }
            }

            if ($es_params1) {
                app('es')->bulk($es_params1);
            }
            if ($es_params2) {
                app('es')->bulk($es_params2);
            }
        }


        PatientInfo::query()->updateOrInsert(['MED_REC_ID' => $this->data['zyh']], $this->data['patient_info_data']);
        Log::info('end bmy data zyh:'.$this->data['zyh']);
    }
}
