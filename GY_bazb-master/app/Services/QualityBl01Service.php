<?php

namespace App\Services;

use App\Model\Bllb1;
use App\Model\Bllb303;
use App\Model\DiseaseDiagnosisCode;
use App\Model\CaseQuality;
use App\Model\EMR_BL_BLSY;
use App\Model\PatientInfo;
use App\Model\Setting;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Model\Staff;
use Illuminate\Support\Facades\Log;
use App\Model\CaseRule;
use Illuminate\Validation\Rule;
use function GuzzleHttp\Psr7\str;

/**
 * 入院记录质控
 */
class QualityBl01Service
{
    public $ygjb = ["副主任护师", "副主任检验师", "副主任技师", "副主任医师", "主任医师", "主任护士", "主任检验师", "主治医师", "主管技师", "主管护师", "主管检验师", "主管药师", "医师", "实习医生", "技师", "护士", "护士长", "护师", "检验师", "药师"];

    public function __construct()
    {
        //所有添加的质控规则
        $caseRule = CaseRule::query()->get()->toArray();
        $this->caseRule = array_column($caseRule, null, 'id');
    }

    public static function checkCase($method = [], $item = [], $caseRule = [])
    {
        $errorNotice = [];
        try {

            foreach ($method as $ruleid => $m) {
                if (empty($caseRule[$ruleid]['status'])) {
                    continue;
                }
                $ruleRes = self::$m($item, $caseRule);
                $errorNotice = array_merge($errorNotice, $ruleRes);
            }
        } catch (\Throwable $e) {
            Log::error("病例之间处理失败", ['msg' => $e->getMessage(), 'line' => $e->getLine()]);
            echo $e->getMessage() . '，所在行：' . $e->getLine();
        }

        return $errorNotice;
    }








}

