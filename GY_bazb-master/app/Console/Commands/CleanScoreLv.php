<?php

namespace App\Console\Commands;

use App\Model\CaseQuality;
use App\Model\ErrorV2;
use App\Model\HomeQuality;
use App\Model\Setting;
use App\Model\ZY_BRRY;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Model\PatientInfo;

class CleanScoreLv extends Command
{
    protected $signature = 'clean:score-lv {zyh?}';
    protected $description = '清洗病历及病案首页分级数据';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {

        $this->info('开始处理病历及病案首页分级数据 ' . date('Y-m-d H:i:s'));
        $query = ZY_BRRY::query()
            ->join('patient_info', 'ZY_BRRY.ZYH', '=', 'patient_info.MED_REC_ID')
            ->where('patient_info.in_hospital', 1)
            ->orWhere('patient_info.AAC01', '>', date("Y-m-d 00:00:00"));

        $patients = $query->get();

        foreach ($patients as $patient) {
            $updates = [];
            $score = CaseQuality::query()
                ->join(DB::raw('(SELECT id,score FROM case_rule where status=1 union all select id+1000000 as id,score from rule_setting where status=1) as case_rule'), 'case_quality.rule_id', '=', 'case_rule.id')
                ->where("case_quality.JZHM", $patient->ZYH)
                ->sum("case_rule.score");

            $updates["score"] = 100 - $score;

            // 处理病历分级
            if ($updates["score"] !== null) {
                if ($updates["score"] > 90) {
                    $updates['score_lv'] = '甲';
                } elseif ($updates["score"] >= 75 && $updates["score"] <= 90) {
                    $updates['score_lv'] = '乙';
                } elseif ($updates["score"] < 75) {
                    $updates['score_lv'] = '丙';
                }
            }

            $down = HomeQuality::query()->join("error_rule", 'error_rule.id', '=', 'home_quality.error_rule')
                ->where("error_rule.status", 1)
                ->where("home_quality.ZYH", $patient->ZYH)
                ->sum("error_rule.down");
            $updates['home_bmy_score'] = 100 - $down;

            // 处理病案首页编码员分级
            if ($updates['home_bmy_score'] !== null) {
                if ($updates['home_bmy_score'] >= 97) {
                    $updates['home_bmy_score_lv'] = '优';
                } elseif ($updates['home_bmy_score'] >= 90 && $updates['home_bmy_score'] < 97) {
                    // 注意：这里需要检查A类错误的逻辑，如果你有相关字段
                    $updates['home_bmy_score_lv'] = '良';
                } elseif ($updates['home_bmy_score'] >= 75 && $updates['home_bmy_score'] < 90) {
                    // 注意：这里需要检查A类错误的逻辑，如果你有相关字段
                    $updates['home_bmy_score_lv'] = '中';
                } elseif ($updates['home_bmy_score'] < 75) {
                    $updates['home_bmy_score_lv'] = '差';
                }
            }

            $down = ErrorV2::query()->join("error_rule", 'error_rule.id', '=', 'error_v2.error_rule')
                ->where("error_rule.status", 1)
                ->where("error_v2.ZYH", $patient->ZYH)
                ->sum("error_rule.down");
            $updates['home_ysz_score'] = 100 - $down;
            // 处理病案首页医生站分级
            if ($updates['home_ysz_score'] !== null) {
                if ($updates['home_ysz_score'] >= 97) {
                    $updates['home_ysz_score_lv'] = '优';
                } elseif ($updates['home_ysz_score'] >= 90 && $updates['home_ysz_score'] < 97) {
                    $updates['home_ysz_score_lv'] = '良';
                } elseif ($updates['home_ysz_score'] >= 75 && $updates['home_ysz_score'] < 90) {
                    $updates['home_ysz_score_lv'] = '中';
                } elseif ($updates['home_ysz_score'] < 75) {
                    $updates['home_ysz_score_lv'] = '差';
                }
            }

            if (!empty($updates)) {
                ZY_BRRY::query()->where('ZYH', $patient->ZYH)->update($updates);
                PatientInfo::query()
                    ->where('MED_REC_ID', $patient->ZYH)
                    ->update($updates);
            }
            $this->info("已处理ZYH: {$patient->ZYH}");
        }

        $this->info('处理完成 ' . date('Y-m-d H:i:s'));
    }
}
