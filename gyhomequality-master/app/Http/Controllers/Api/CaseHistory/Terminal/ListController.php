<?php

/**
 * 下砖列表页面
 */

namespace App\Http\Controllers\Api\CaseHistory\Terminal;

use App\Model\PatientInfoDiagnosisV2;
use App\Model\PatientInfoOperationV2;
use App\Model\ZY_BLMB;
use App\Model\CaseRule;
use App\Model\Department;
use App\Model\CaseQuality;
use App\Model\PatientInfo;
use App\Model\RuleSetting;
use App\Model\MainDiagnosis;
use App\Model\MainOperation;
use App\Model\PatientInfoV2;
use Illuminate\Http\Request;
use App\Services\UserService;
use App\Services\ToolsService;
use App\Services\CsvService;
use App\Model\PatientDoctorInfo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\ApiController;
use App\Model\CaseQualityZm;

class ListController extends ApiController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取病例列表搜索条件
     */
    public function getBlSearchOptions()
    {
        $yqArray = Department::getDepartmentOptions();
        $ksArray = Department::getDepartmentOptions(2);
        $bqArray = Department::getDepartmentOptions(3);
        $bazlArray = PatientInfo::getBazlArray(); //
        $lyTypeArray = PatientInfo::getLyTypeArray();
        $data = [];
        $data['yqArray'] = $yqArray;
        $data['ksArray'] = $ksArray;
        $data['bqArray'] = $bqArray;
        $data['bazlArray'] = $bazlArray;
        $data['lyTypeArray'] = $lyTypeArray;
        return ToolsService::returnData(200, $data);
    }

    /**
     * 获取问题病例列表搜索options
     */
    public function getQxBlSearchOptions(Request $request)
    {
        $isTj = (int)$request->input('is_tj', 0);
        $tjWhere = $isTj === 1 ? ' and is_tj=1' : '';
        $yqArray = Department::getDepartmentOptions(); //院区options
        $ksArray = Department::getDepartmentOptions(2); //科室options
        $bqArray = Department::getDepartmentOptions(3); //病区options
        $bazlArray = PatientInfo::getBazlArray(); //病案质量options
        $lyTypeArray = PatientInfo::getLyTypeArray(); //离院方式options
        //        $wtArray = DB::select('SELECT id,notice,title,category FROM case_rule where status=1');
        $wtArray = DB::select('SELECT id,notice,title,category,is_ai as is_artificial,`type` FROM case_rule where status=1 and is_ai In (1,2,3)' . $tjWhere . ' union all select id+1000000,description as notice,`object` as title,case_type as category,1 as is_artificial,`type` from rule_setting  where status=1 and rule_setting.rule_type = "普通规则"' . $tjWhere);
        $ruleTypeArray = CaseQuality::ruleTypeArray(); //规则类型
        $data = [];
        $data['yqArray'] = $yqArray;
        $data['ksArray'] = $ksArray;
        $data['bqArray'] = $bqArray;
        $data['bazlArray'] = $bazlArray;
        $data['lyTypeArray'] = $lyTypeArray;
        $data['wtArray'] = array_column($wtArray, 'notice', 'id');
        $data['wtTitleArray'] = [];
        foreach ($wtArray as $k => $item) {
            if ($item->is_artificial == 2) {
                unset($wtArray[$k]);
                continue;
            }
            if (array_key_exists($item->category, $data['wtTitleArray'])) {
                $data['wtTitleArray'][$item->category]['type'][] = $item->type;
            } else {
                $data['wtTitleArray'][$item->category] = [
                    "id" => $item->id,
                    "title" => $item->category,
                    "type" => [$item->type],
                ];
            }
            $data['wtTitleArray'][$item->category]['type'] = array_unique($data['wtTitleArray'][$item->category]['type']);
        }
        $data['wtTitleArray'] = array_values($data['wtTitleArray']);

        $data['wtDataArray'] = array_column($wtArray, null, 'id');
        $data['ruleTypeArray'] = $ruleTypeArray;
        $data['fjhsseArray'] = ["是", "否"];
        $data['ssapeArray'] = ["是", "否"];
        $blmb = ZY_BLMB::query()->get()->toArray();

        $result = [];
        foreach ($blmb as $item) {
            if (!isset($result[$item["ID_MEDI"]])) {
                $result[$item["ID_MEDI"]] = [
                    "key" => $item["ID_MEDI"],
                    "value" => $item["NA_MEDI"],
                    "child" => []
                ];
            }
            $key = array_keys($result[$item["ID_MEDI"]]["child"]);
            if (!in_array($item["ID_MECA"], $key)) {
                $result[$item["ID_MEDI"]]["child"][$item["ID_MECA"]] = [
                    "key" => $item["ID_MECA"],
                    "value" => $item["MECA_NA"],
                    "child" => []
                ];
            }

            $result[$item["ID_MEDI"]]["child"][$item["ID_MECA"]]["child"][$item["ID_TEP"]] = [
                "key" => $item["ID_TEP"],
                "value" => $item["TEP_NAME"]
            ];
        }
        $data['BLMB'] = $result;
        return ToolsService::returnData(200, $data);
    }

    /**
     * 获取科室options
     * @param Request $request
     * @return array
     */
    public function getKsOptions(Request $request)
    {
        $YQ_CODE = $request->post("YQ_CODE", '');
        if (empty($YQ_CODE)) {
            $ksArray = Department::getDepartmentOptions(2);
            $bqArray = Department::getDepartmentOptions(3);
        } else {
            $yqTable = Department::query()->whereIn('dep_id', $YQ_CODE)->get(['id', 'sub_ids'])->toArray();
            $ksCodeArray = [];
            foreach ($yqTable as $v) {
                $v['sub_ids'] = explode(',', $v['sub_ids']);
                $ksCodeArray = array_merge($ksCodeArray, $v['sub_ids']);
            }
            $ksCodeArray = array_unique($ksCodeArray);
            $ksArray = Department::getDepartmentOptions(2, 1, $ksCodeArray);
            $bqArray = Department::getDepartmentOptions(3, 1, $ksCodeArray);
        }
        $data = [];
        $data['ksArray'] = $ksArray;
        $data['bqArray'] = $bqArray;
        return ToolsService::returnData(200, $data);
    }

    /**
     * 获取病区options
     * @param Request $request
     * @return array
     */
    public function getBqOptions(Request $request)
    {
        $KS_CODE = $request->post("KS_CODE", '');
        if (empty($KS_CODE)) {
            $bqArray = Department::getDepartmentOptions(3);
        } else {
            $KS_CODE = is_array($KS_CODE) ? $KS_CODE : [$KS_CODE];
            $yqTable = Department::query()->whereIn('dep_id', $KS_CODE)->get(['id', 'sub_ids'])->toArray();
            $bqCodeArray = [];
            foreach ($yqTable as $v) {
                $v['sub_ids'] = explode(',', $v['sub_ids']);
                $bqCodeArray = array_merge($bqCodeArray, $v['sub_ids']);
            }
            $bqCodeArray = array_unique($bqCodeArray);
            $bqArray = Department::getDepartmentOptions(3, 1, $bqCodeArray);
        }
        $data = [];
        $data['bqArray'] = $bqArray;
        return ToolsService::returnData(200, $data);
    }

    /**
     * 病历数量列表（优化版 - 避免数据重复）
     * @param Request $request
     * @return array
     *
     *
     * -- patient_info 表索引
     *ALTER TABLE patient_info ADD INDEX idx_med_rec_in_hospital (MED_REC_ID, in_hospital);
     *ALTER TABLE patient_info ADD INDEX idx_aac01 (AAC01);  -- 出院时间
     *ALTER TABLE patient_info ADD INDEX idx_score (score);
     *ALTER TABLE patient_info ADD INDEX idx_yq_code (YQ_CODE);
     *ALTER TABLE patient_info ADD INDEX idx_bq_code (BQ_CODE);
     *ALTER TABLE patient_info ADD INDEX idx_aaa28 (AAA28);

     *-- ZY_BRRY 表索引
     *ALTER TABLE ZY_BRRY ADD INDEX idx_zyh_brks (ZYH, BRKS);

     *-- patient_info_v2 表索引
     *ALTER TABLE patient_info_v2 ADD INDEX idx_zyh_status (ZYH, status);

     *-- 其他关联表索引
     *ALTER TABLE patient_doctor_info ADD INDEX idx_aaa28 (AAA28);
     *ALTER TABLE main_diagnosis ADD INDEX idx_aaa28 (AAA28);
     *ALTER TABLE main_operation ADD INDEX idx_aaa28 (AAA28);
     *ALTER TABLE patient_info_diagnosis_v2 ADD INDEX idx_zyh (ZYH);
     *ALTER TABLE patient_info_operation_v2 ADD INDEX idx_zyh (ZYH);
     */
    public function blNumberTableList(Request $request)
    {
        $depIds = UserService::getCurrentUserDep($request);
        if (empty($depIds)) {
            return ToolsService::jsonSuccess(['count' => 0, 'data' => []]);
        }
        $status = $request->post('status', '');
        $useSzMode = !empty($status);
        $scoreField = $useSzMode ? 'patient_info.score' : 'patient_info.zm_score';
        $scoreLevelField = $useSzMode ? 'patient_info.score_lv' : 'patient_info.zm_score_lv';

        //处理where条件 - 主查询保持简洁
        $query = PatientInfo::query()
            ->join('ZY_BRRY', 'ZY_BRRY.ZYH', '=', 'patient_info.MED_REC_ID')
            ->leftJoin('patient_info_v2', function ($join) {
                $join->on('patient_info_v2.ZYH', '=', 'patient_info.MED_REC_ID')
                    ->where('patient_info_v2.status', '=', 0);
            })
            ->when(is_array($depIds), function ($query) use ($depIds) {
                return $query->whereIn('ZY_BRRY.BRKS', $depIds);
            })
            ->whereNotNull('patient_info.MED_REC_ID');

        if (empty($status)) {
            $query->where("patient_info.in_hospital", 2);
        }

        //缺陷数量
        $is_qx = $request->post('is_qx', 0);
        if ($is_qx == 1) {
            if ($useSzMode) {
                $query->whereExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('case_quality')
                        ->whereColumn('case_quality.JZHM', 'patient_info.MED_REC_ID')
                        ->where('case_quality.is_correction', 0)
                        ->where(function ($appealQuery) {
                            $this->applyAppealVisibleCondition($appealQuery, 'case_quality');
                        })
                        ->where('case_quality.is_ignore', 0);
                });
            } else {
                $query->whereExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('case_quality_zm')
                        ->whereColumn('case_quality_zm.JZHM', 'patient_info.MED_REC_ID')
                        ->where('case_quality_zm.is_correction', 0)
                        ->where(function ($appealQuery) {
                            $this->applyAppealVisibleCondition($appealQuery, 'case_quality_zm');
                        })
                        ->where('case_quality_zm.is_ignore', 0);
                });
            }
        }

        //住院天数(起始天数)
        $startDay = $request->post('endDay1');
        if (is_numeric($startDay)) $query->where('patient_info.AAC04', '>=', $startDay);

        //住院天数(结束天数)
        $endDay = $request->post('endDay2');
        if (is_numeric($endDay)) $query->where('patient_info.AAC04', '<=', $endDay);

        //病案质量
        $lb_level = $request->post('lb_level');
        $score_lv = config("dictionaries.AED01C");
        if (!empty($lb_level)) $query->where($scoreLevelField, $score_lv[$lb_level]);

        //住院号
        $AAA28 = $request->post('AAA28');
        if (!empty($AAA28)) $query->where('patient_info.AAA28', $AAA28);

        //院区
        $YQ_CODE = $request->post('YQ_CODE');
        if (!empty($YQ_CODE)) {
            $YQ_CODE = explode(",", $YQ_CODE);
            $YQ_CODE = array_filter($YQ_CODE);
            if ($YQ_CODE) {
                $query->whereIn('patient_info.YQ_CODE', $YQ_CODE);
            }
        }

        //科室
        $KS_CODE = $request->post("KS_CODE");
        if (!empty($KS_CODE)) {
            $KS_CODE = explode(",", $KS_CODE);
            $KS_CODE = array_filter($KS_CODE);
            if ($KS_CODE) {
                $query->whereIn("ZY_BRRY.BRKS", $KS_CODE);
            }
        }

        //病区
        $BQ_CODE = $request->post('BQ_CODE');
        if (!empty($BQ_CODE)) {
            $BQ_CODE = explode(",", $BQ_CODE);
            $BQ_CODE = array_filter($BQ_CODE);
            if ($BQ_CODE) {
                $query->whereIn("patient_info.BQ_CODE", $BQ_CODE);
            }
        }

        // 时间筛选：status 不为空时，start/end 对应入院时间，cysj_start/cysj_end 对应出院时间
        $start_time = $request->post('start_time');
        $start_time = $start_time ? date("Y-m-d 00:00:00", strtotime($start_time)) : "";
        if (!empty($start_time)) {
            $query->where('patient_info.' . (!empty($status) ? 'AAB01' : 'AAC01'), '>=', $start_time);
        }

        // 结束时间筛选
        $end_time = $request->post('end_time');
        $end_time = $end_time ? date("Y-m-d 23:59:59", strtotime($end_time)) : "";
        if (!empty($end_time)) {
            $query->where('patient_info.' . (!empty($status) ? 'AAB01' : 'AAC01'), '<=', $end_time);
        }

        if (!empty($status)) {
            $cysjStart = $request->post('cysj_start', '');
            $cysjStart = $cysjStart ? date("Y-m-d 00:00:00", strtotime($cysjStart)) : "";
            if (!empty($cysjStart)) {
                $query->where('patient_info.AAC01', '>=', $cysjStart);
            }

            $cysjEnd = $request->post('cysj_end', '');
            $cysjEnd = $cysjEnd ? date("Y-m-d 23:59:59", strtotime($cysjEnd)) : "";
            if (!empty($cysjEnd)) {
                $query->where('patient_info.AAC01', '<=', $cysjEnd);
            }

            // status筛选（出院状态）
            $today = date('Y-m-d 00:00:00');
            if ($status == 1) {
                // 在院及当天出院：AAC01为空(NULL或'') 或 AAC01>=当天0点
                $query->where(function ($query) use ($today) {
                    $query->where(function ($q) {
                        $q->whereNull('patient_info.AAC01')
                            ->orWhere('patient_info.AAC01', '=', '');
                    })->orWhere('patient_info.AAC01', '>=', $today);
                });
            } elseif ($status == 2) {
                // 在院：AAC01为空(NULL或'')
                $query->where(function ($query) {
                    $query->whereNull('patient_info.AAC01')
                        ->orWhere('patient_info.AAC01', '=', '');
                });
            } elseif ($status == 3) {
                // 当天出院：AAC01不为空(非NULL且非'')且>=当天0点
                $query->whereNotNull('patient_info.AAC01')
                    ->where('patient_info.AAC01', '!=', '')
                    ->where('patient_info.AAC01', '>=', $today);
            } elseif ($status == 4) {
                // 常规出院：AAC01不为空(非NULL且非'')且<当天0点
                $query->whereNotNull('patient_info.AAC01')
                    ->where('patient_info.AAC01', '!=', '')
                    ->where('patient_info.AAC01', '<', $today);
            }
        }

        //离院方式 - 优化为 COALESCE 避免 orWhere
        $AEM01C = $request->post('AEM01C');
        if ($AEM01C) {
            $query->whereRaw('(COALESCE(patient_info_v2.AEM01C, patient_info.AEM01C) = ?)', [$AEM01C]);
        }

        //问题描述 - 规则ID
        $ruleId = $request->post('rule_id');
        if (!empty($ruleId)) $query->where('case_quality.rule_id', $ruleId);

        //规则类型
        $rule_type = $request->post('rule_type');
        if ($rule_type) {
            $type_ids = [];
            $result = CaseRule::query()->where('type', $rule_type)->get(['id'])->toArray();
            if ($result) {
                $type_ids = array_column($result, 'id');
            }

            $ruleSetting = RuleSetting::query()->where('type', $rule_type)->get(['id'])->toArray();
            if ($ruleSetting) {
                $ruleSetting = array_column($ruleSetting, 'id');
                $ruleSetting = array_map(function ($v) {
                    return $v + 1000000;
                }, $ruleSetting);
                $type_ids = array_merge($type_ids, $ruleSetting);
            }

            $query->whereIn('case_quality.rule_id', $type_ids);
        }

        //分页
        $page = $request->post('page', 1);
        $pageSize = $request->post('limit', 10);
        $is_export = $request->post('is_export', '');

        //获取数据
        if (empty($is_export)) {
            $table = $query->orderBy("patient_info.AAC01", "desc")->paginate($pageSize, [
                'patient_info.AAB01',
                'patient_info.MED_REC_ID',
                'patient_info.id',
                'patient_info.AAC02C',
                'patient_info.AAA28',
                'patient_info.AAC01',
                'patient_info.AAA01',
                DB::raw($scoreField . ' as zm_score'),
                'patient_info.AAC04',
                'patient_info_v2.AEM01C as AEM01C_v2',
                'patient_info.AEM01C',
                'ZY_BRRY.BRKS',
                'ZY_BRRY.ZYCS',
                'ZY_BRRY.ZZYSMC',
                'ZY_BRRY.ZZYSDM'
            ], 'page', $page)->toArray();
        } else {
            $table['data'] = $query->orderBy("patient_info.AAC01", "desc")->get([
                'patient_info.AAB01',
                'patient_info.MED_REC_ID',
                'patient_info.id',
                'patient_info.AAC02C',
                'patient_info.AAA28',
                'patient_info.AAC01',
                'patient_info.AAA01',
                DB::raw($scoreField . ' as zm_score'),
                'patient_info.AAC04',
                'patient_info_v2.AEM01C as AEM01C_v2',
                'patient_info.AEM01C',
                'ZY_BRRY.BRKS',
                'ZY_BRRY.ZYCS',
                'ZY_BRRY.ZZYSMC',
                'ZY_BRRY.ZZYSDM'
            ])->toArray();
            $table['total'] = count($table['data']);
        }
        // 获取科室和字典数据
        $depArray = Department::query()->pluck('dep_name', 'dep_id')->toArray();
        $AEM01C_dict = config('dictionaries.AEM01C');

        $ZYH = array_column($table['data'], "MED_REC_ID");

        // 获取所有 ZZYSDM 并查询 staff 表
        $zzysdmList = array_unique(array_filter(array_column($table['data'], 'ZZYSDM')));
        $staffData = [];
        if (!empty($zzysdmList)) {
            $staffData = \App\Model\Staff::query()
                ->whereIn('code', $zzysdmList)
                ->pluck('YGBH', 'code')
                ->toArray();
        }

        // 优化：使用 DB::raw 和 GROUP BY 只取每个患者的第一条记录，避免重复
        // 方案1：分别查询并只取第一条
        $ptv2 = PatientInfoV2::query()
            ->whereIn("ZYH", $ZYH)
            ->where("status", 0)
            ->select("AAC11N", "AAC04", "ZYH")
            ->get()
            ->keyBy("ZYH")
            ->toArray();

        $patientDoctorInfo = PatientDoctorInfo::query()
            ->whereIn("AAA28", $ZYH)
            ->select("AAA28", "AEE03", "AEE03_CODE")
            ->get()
            ->keyBy("AAA28")
            ->toArray();

        // 使用 groupBy 只取第一条主诊断/手术
        $mainDiagnosisRaw = MainDiagnosis::query()
            ->whereIn("AAA28", $ZYH)
            ->orderBy('id', 'asc')
            ->get(["AAA28", "ICD10_NAME"])
            ->toArray();
        $mainDiagnosis = [];
        foreach ($mainDiagnosisRaw as $item) {
            if (!isset($mainDiagnosis[$item['AAA28']])) {
                $mainDiagnosis[$item['AAA28']] = $item['ICD10_NAME'];
            }
        }

        $mainOperationRaw = MainOperation::query()
            ->whereIn("AAA28", $ZYH)
            ->orderBy('id', 'asc')
            ->get(["AAA28", "ICD9_NAME"])
            ->toArray();
        $mainOperation = [];
        foreach ($mainOperationRaw as $item) {
            if (!isset($mainOperation[$item['AAA28']])) {
                $mainOperation[$item['AAA28']] = $item['ICD9_NAME'];
            }
        }

        $patientInfoDiagnosisV2Raw = PatientInfoDiagnosisV2::query()
            ->whereIn("ZYH", $ZYH)
            ->orderBy('id', 'asc')
            ->get(["ZYH", "ICD10_NAME"])
            ->toArray();
        $patientInfoDiagnosisV2 = [];
        foreach ($patientInfoDiagnosisV2Raw as $item) {
            if (!isset($patientInfoDiagnosisV2[$item['ZYH']])) {
                $patientInfoDiagnosisV2[$item['ZYH']] = $item['ICD10_NAME'];
            }
        }

        $patientInfoOperationV2Raw = PatientInfoOperationV2::query()
            ->whereIn("ZYH", $ZYH)
            ->orderBy('id', 'asc')
            ->get(["ZYH", "ICD9_NAME"])
            ->toArray();
        $patientInfoOperationV2 = [];
        foreach ($patientInfoOperationV2Raw as $item) {
            if (!isset($patientInfoOperationV2[$item['ZYH']])) {
                $patientInfoOperationV2[$item['ZYH']] = $item['ICD9_NAME'];
            }
        }

        foreach ($table['data'] as $k => $v) {
            $v['ICD10_NAME'] = $mainDiagnosis[$v['MED_REC_ID']] ?? "";
            if (empty($v['ICD10_NAME'])) {
                $v['ICD10_NAME'] = $patientInfoDiagnosisV2[$v['MED_REC_ID']] ?? "";
            }
            $v['ICD9_NAME'] = $mainOperation[$v['MED_REC_ID']] ?? "";
            if (empty($v['ICD9_NAME'])) {
                $v['ICD9_NAME'] = $patientInfoOperationV2[$v['MED_REC_ID']] ?? "";
            }
            $v['AEE03'] = $patientDoctorInfo[$v['MED_REC_ID']]['AEE03'] ?? "";
            $v['AEE03_CODE'] = $patientDoctorInfo[$v['MED_REC_ID']]['AEE03_CODE'] ?? "";
            $v["AAC04"] = $ptv2[$v["MED_REC_ID"]]["AAC04"] ?? $v["AAC04"];
            $v['AAC11N'] = $depArray[$v['BRKS']] ?? ($ptv2[$v["MED_REC_ID"]]["AAC11N"] ?? "");
            $v['index'] = $k + 1;
            $v['AEM01C_MC'] = ($v['AEM01C'] && isset($AEM01C_dict[$v['AEM01C']]))
                ? $AEM01C_dict[$v['AEM01C']]
                : (($v['AEM01C_v2'] && isset($AEM01C_dict[$v['AEM01C_v2']])) ? $AEM01C_dict[$v['AEM01C_v2']] : "");
            $v['score_lv'] = PatientInfo::getBlZl($v['zm_score']);
            $ygbh = !empty($v['ZZYSDM']) && isset($staffData[$v['ZZYSDM']]) ? $staffData[$v['ZZYSDM']] : $v['ZZYSDM'];
            $v['AEE03'] = "{$v['ZZYSMC']}/{$ygbh}";

            $table['data'][$k] = $v;
        }

        // 导出CSV功能
        if ($is_export == 1) {
            $exportData = [];
            $exportData[] = [
                '序号',
                '病案质量',
                '病案号',
                '患者姓名',
                '出院时间',
                '出院科室',
                '住院天数',
                '主治医师',
                '离院方式',
                '入院时间',
                '主要诊断名称',
                '主要手术名称'
            ];
            foreach ($table['data'] as $k => $v) {
                $score_lv = PatientInfo::getBlZl($v['zm_score'], 1);
                $exportData[] = [
                    $k + 1,
                    ($v['zm_score'] ?? '') . '/' . ($score_lv ?? ''),
                    $v["AAA28"] . "\t",
                    $v["AAA01"] ?? '',
                    $v["AAC01"] ?? '',
                    $v['AAC11N'] ?? '',
                    $v["AAC04"] ?? '',
                    $v['AEE03'] ?? '',
                    $v['AEM01C_MC'] ?? '',
                    $v["AAB01"] ?? '',
                    $v['ICD10_NAME'] ?? '',
                    $v['ICD9_NAME'] ?? '',
                ];
            }
            $csv = new CsvService();
            $csv->filename = $csv->charset('病历数量列表', 'UTF-8');
            return $csv->export($exportData);
        }

        $data = [];
        $data['count'] = $table['total'];
        $data['data'] = $table['data'];
        return ToolsService::returnData(200, $data);
    }

    /**
     * 获取缺陷病例数量列表
     * @param Request $request
     * @return array
     */
    public function qxBlNumberTableList(Request $request)
    {
        $depIds = UserService::getCurrentUserDep($request);
        if (empty($depIds)) {
            return ToolsService::jsonSuccess(['count' => 0, 'list' => []]);
        }
        $status = $request->post('status', '');
        $useSzMode = !empty($status);
        $qualityTable = $useSzMode ? 'case_quality' : 'case_quality_zm';
        $scoreField = $useSzMode ? 'patient_info.score' : 'patient_info.zm_score';
        $scoreLevelField = $useSzMode ? 'patient_info.score_lv' : 'patient_info.zm_score_lv';

        //处理where条件
        $query = ($useSzMode ? CaseQuality::query() : CaseQualityZm::query())
            ->join('patient_info', 'patient_info.MED_REC_ID', '=', $qualityTable . '.JZHM')
            ->join('ZY_BRRY', 'ZY_BRRY.ZYH', '=', $qualityTable . '.JZHM')
            ->join(DB::raw('(SELECT id,title,notice,status FROM case_rule union all select id+1000000,`object` as title,description as notice,status from rule_setting rs) as case_rule'), $qualityTable . '.rule_id', '=', 'case_rule.id')
            ->when(is_array($depIds), function ($query) use ($depIds) {
                return $query->whereIn('ZY_BRRY.BRKS', $depIds);
            })
            ->whereNotNull($scoreField)
            ->where("case_rule.status", 1)
            ->where($qualityTable . ".is_correction", 0)
            ->where(function ($appealQuery) use ($qualityTable) {
                $this->applyAppealVisibleCondition($appealQuery, $qualityTable);
            })
            ->where($qualityTable . ".is_ignore", 0);

        if (empty($status)) {
            $query->where("patient_info.in_hospital", 2);
        }


        //住院天数(起始天数)
        $startDay = $request->post('endDay1');
        if (is_numeric($startDay)) {
            $query->where('patient_info.AAC04', '>=', $startDay);
        }
        //住院天数(结束天数)
        $endDay = $request->post('endDay2');
        if (is_numeric($endDay)) $query->where('patient_info.AAC04', '<=', $endDay);

        //病案质量
        $lb_level = $request->post('lb_level');
        $score_lv = config("dictionaries.AED01C");
        if (!empty($lb_level)) $query->where($scoreLevelField, $score_lv[$lb_level]);

        //住院号
        $AAA28 = $request->post('AAA28');
        if (!empty($AAA28)) $query->where('patient_info.AAA28', $AAA28);

        //院区
        $YQ_CODE = $request->post('YQ_CODE');
        if (!empty($YQ_CODE)) {
            $YQ_CODE = explode(",", $YQ_CODE);
            $YQ_CODE = array_filter($YQ_CODE);
            if ($YQ_CODE) {
                $query->whereIn('patient_info.YQ_CODE', $YQ_CODE);
            }
        }
        //科室
        $KS_CODE = $request->post("KS_CODE");
        if (!empty($KS_CODE)) {
            $KS_CODE = explode(",", $KS_CODE);
            $KS_CODE = array_filter($KS_CODE);
            if ($KS_CODE) {
                $query->whereIn("ZY_BRRY.BRKS", $KS_CODE);
            }
        }
        //病区
        $BQ_CODE = $request->post('BQ_CODE');
        if (!empty($BQ_CODE)) {
            $BQ_CODE = explode(",", $BQ_CODE);
            $BQ_CODE = array_filter($BQ_CODE);
            if ($BQ_CODE) {
                $query->whereIn("patient_info.BQ_CODE", $BQ_CODE);
            }
        }

        // 时间筛选：status 不为空时，start/end 对应入院时间，cysj_start/cysj_end 对应出院时间
        $start_time = $request->post('start_time');
        if (!empty($start_time)) {
            $start_time = $start_time ? date("Y-m-d 00:00:00", strtotime($start_time)) : "";
            $query->where('patient_info.' . (!empty($status) ? 'AAB01' : 'AAC01'), '>=', $start_time);
        }

        // 结束时间筛选
        $end_time = $request->post('end_time');
        if (!empty($end_time)) {
            $end_time = $end_time ? date("Y-m-d 23:59:59", strtotime($end_time)) : "";
            $query->where('patient_info.' . (!empty($status) ? 'AAB01' : 'AAC01'), '<=', $end_time);
        }

        if (!empty($status)) {
            $cysjStart = $request->post('cysj_start', '');
            $cysjStart = $cysjStart ? date("Y-m-d 00:00:00", strtotime($cysjStart)) : "";
            if (!empty($cysjStart)) {
                $query->where('patient_info.AAC01', '>=', $cysjStart);
            }

            $cysjEnd = $request->post('cysj_end', '');
            $cysjEnd = $cysjEnd ? date("Y-m-d 23:59:59", strtotime($cysjEnd)) : "";
            if (!empty($cysjEnd)) {
                $query->where('patient_info.AAC01', '<=', $cysjEnd);
            }

            // status筛选（出院状态）
            $today = date('Y-m-d 00:00:00');
            if ($status == 1) {
                $query->where(function ($query) use ($today) {
                    $query->where(function ($q) {
                        $q->whereNull('patient_info.AAC01')
                            ->orWhere('patient_info.AAC01', '=', '');
                    })->orWhere('patient_info.AAC01', '>=', $today);
                });
            } elseif ($status == 2) {
                $query->where(function ($query) {
                    $query->whereNull('patient_info.AAC01')
                        ->orWhere('patient_info.AAC01', '=', '');
                });
            } elseif ($status == 3) {
                $query->whereNotNull('patient_info.AAC01')
                    ->where('patient_info.AAC01', '!=', '')
                    ->where('patient_info.AAC01', '>=', $today);
            } elseif ($status == 4) {
                $query->whereNotNull('patient_info.AAC01')
                    ->where('patient_info.AAC01', '!=', '')
                    ->where('patient_info.AAC01', '<', $today);
            }
        }

        //离院方式
        $AEM01C = $request->post('AEM01C');
        $query->when($AEM01C, function ($query) use ($AEM01C) {
            $query->where('patient_info.AEM01C', $AEM01C);
        });

        //问题描述
        // 规则ID
        $ruleId = $request->post('rule_id');
        if (!empty($ruleId)) $query->where($qualityTable . '.rule_id', $ruleId);

        //规则类型
        $rule_type = $request->post('rule_type');
        if ($rule_type) {
            $type_ids = [];
            $result = CaseRule::query()->where('type', $rule_type)->get(['id'])->toArray();
            if ($result) {
                $type_ids = array_column($result, 'id');
            }

            $ruleSetting = RuleSetting::query()->where('type', $rule_type)->get(['id'])->toArray();
            if ($ruleSetting) {
                $ruleSetting = array_column($ruleSetting, 'id');
                $ruleSetting = array_map(function ($v) {
                    return $v + 1000000;
                }, $ruleSetting);
                $type_ids = array_merge($type_ids, $ruleSetting);
            }

            $query->whereIn($qualityTable . '.rule_id', $type_ids);
        }

        //分页
        $page = $request->post('page', 1);
        $pageSize = $request->post('limit', 10);
        $is_export = $request->post('is_export', '');

        //获取数据
        //AAC04 '住院天数',
        //AAB01 '入院时间',
        //AEM01C '离院方式',
        //ICD9_NAME '主要手术名称',
        //ICD10_NAME '主要诊断名称',
        $orderKey = $request->post('order_key', "");
        $orderKey = $orderKey ?: "AAB01";
        $orderValue = $request->post('order_value', "");
        $orderValue = $orderValue ?: "desc";

        if (empty($is_export)) {
            $table = $query->orderBy("patient_info." . $orderKey, $orderValue)->paginate($pageSize, ['patient_info.AAB01', 'patient_info.MED_REC_ID', 'patient_info.AAA01', DB::raw($qualityTable . '.JZHM as JZHM'), 'patient_info.AAA28', 'patient_info.AAC01', 'patient_info.AAC02C', 'patient_info.AAC04', 'patient_info.AEM01C', 'case_rule.notice', 'case_rule.id as rule_id', 'ZY_BRRY.BRKS', 'patient_info.AEM01C', 'ZY_BRRY.ZYCS', 'ZY_BRRY.ZZYSMC', 'ZY_BRRY.ZZYSDM'], 'page', $page)->toArray();
        } else {
            $table['data'] = $query->orderBy("patient_info." . $orderKey, $orderValue)->get(['patient_info.AAB01', 'patient_info.MED_REC_ID', 'patient_info.AAA01', DB::raw($qualityTable . '.JZHM as JZHM'), 'patient_info.AAA28', 'patient_info.AAC01', 'patient_info.AAC02C', 'patient_info.AAC04', 'patient_info.AEM01C', 'case_rule.notice', 'case_rule.id as rule_id', 'ZY_BRRY.BRKS', 'patient_info.AEM01C', 'ZY_BRRY.ZYCS', 'ZY_BRRY.ZZYSMC', 'ZY_BRRY.ZZYSDM'])->toArray();
            $table['total'] = count($table['data']);
        }

        // 获取查询日志
        $AEM01C = config('dictionaries.AEM01C');
        $depArray = Department::query()->pluck('dep_name', 'dep_id')->toArray();

        $ZYH = array_column($table['data'], "MED_REC_ID");
        
        // 获取所有 ZZYSDM 并查询 staff 表
        $zzysdmList = array_unique(array_filter(array_column($table['data'], 'ZZYSDM')));
        $staffData = [];
        if (!empty($zzysdmList)) {
            $staffData = \App\Model\Staff::query()
                ->whereIn('code', $zzysdmList)
                ->pluck('YGBH', 'code')
                ->toArray();
        }
        
        $ptv2 = PatientInfoV2::query()->whereIn("ZYH", $ZYH)->where("status", 0)->get(["AAC11N", "AAC04", "ZYH"])->toArray();
        $ptv2 = array_column($ptv2, NULL, "ZYH");

        $patientDoctorInfo = PatientDoctorInfo::query()->whereIn("AAA28", $ZYH)->get(["AAA28", "AEE03", "AEE03_CODE"])->toArray();
        $patientDoctorInfo = array_column($patientDoctorInfo, NULL, "AAA28");

        $mainDiagnosis = MainDiagnosis::query()->whereIn("AAA28", $ZYH)->pluck("ICD10_NAME", "AAA28");
        $mainOperation = MainOperation::query()->whereIn("AAA28", $ZYH)->pluck("ICD9_NAME", 'AAA28');
        $patientInfoDiagnosisV2 = PatientInfoDiagnosisV2::query()->whereIn("ZYH", $ZYH)->pluck("ICD10_NAME", "ZYH");
        $patientInfoOperationV2 = PatientInfoOperationV2::query()->whereIn("ZYH", $ZYH)->pluck("ICD9_NAME", 'ZYH');

        foreach ($table['data'] as $k => $v) {
            $v['ICD10_NAME'] = $mainDiagnosis[$v['MED_REC_ID']] ?? "";
            if (empty($v['ICD10_NAME'])) {
                $v['ICD10_NAME'] = $patientInfoDiagnosisV2[$v['MED_REC_ID']] ?? "";
            }
            $v['ICD9_NAME'] = $mainOperation[$v['MED_REC_ID']] ?? "";
            if (empty($v['ICD9_NAME'])) {
                $v['ICD9_NAME'] = $patientInfoOperationV2[$v['MED_REC_ID']] ?? "";
            }

            $depName = !empty($v['AAC02C']) ? $depArray[$v['AAC02C']] ?? "" : '';
            if (empty($depName)) {
                $depName = !empty($v['BRKS']) ? $depArray[$v['BRKS']] ?? '' : '';
            }
            $v['AEE03'] = $patientDoctorInfo[$v['MED_REC_ID']]['AEE03'] ?? "";
            $v['AEE03_CODE'] = $patientDoctorInfo[$v['MED_REC_ID']]['AEE03_CODE'] ?? "";
            $v['index'] = $k + 1; //序号
            $v["AAC04"] = $v["AAC04"] ?: $ptv2[$v["MED_REC_ID"]]["AAC04"] ?? "";
            $v['AAC11N'] = $depName ?? ""; //出院科室
            $v['AEM01C_MC'] = $v['AEM01C'] && !empty($AEM01C[$v['AEM01C']]) ? $AEM01C[$v['AEM01C']] : $v['AEM01C'];
            $ygbh = !empty($v['ZZYSDM']) && isset($staffData[$v['ZZYSDM']]) ? $staffData[$v['ZZYSDM']] : $v['ZZYSDM'];
            $v['AEE03'] = "{$v['ZZYSMC']}/{$ygbh}";
            $table['data'][$k] = $v;
        }

        // 导出CSV功能
        if ($is_export == 1) {
            $exportData = [];
            $exportData[] = [
                '序号',
                '问题描述',
                '病案号',
                '患者姓名',
                '出院时间',
                '出院科室',
                '住院天数',
                '主治医师',
                '离院方式',
                '入院时间',
                '主要诊断名称',
                '主要手术名称'
            ];
            foreach ($table['data'] as $k => $v) {
                $exportData[] = [
                    $k + 1,
                    $v['notice'] ?? '',
                    $v["AAA28"] . "\t",
                    $v["AAA01"] ?? '',
                    $v["AAC01"] ?? '',
                    $v['AAC11N'] ?? '',
                    $v["AAC04"] ?? '',
                    $v['AEE03'] ?? '',
                    $v['AEM01C_MC'] ?? '',
                    $v["AAB01"] ?? '',
                    $v['ICD10_NAME'] ?? '',
                    $v['ICD9_NAME'] ?? '',
                ];
            }
            $csv = new CsvService();
            $csv->filename = $csv->charset('缺陷病例数量列表', 'UTF-8');
            return $csv->export($exportData);
        }

        $data = [];
        $data['count'] = $table['total'];
        $data['data'] = $table['data'];
        return ToolsService::returnData(200, $data);
    }

    /**
     * 展示未申诉的问题，或仍需纳入统计的申诉问题。
     */
    private function applyAppealVisibleCondition($query, $qualityTable)
    {
        $query->where($qualityTable . '.is_appeal', 0)
            ->orWhere(function ($appealQuery) use ($qualityTable) {
                $appealQuery->where($qualityTable . '.is_appeal', 1)
                    ->whereExists(function ($subQuery) use ($qualityTable) {
                        $subQuery->select(DB::raw(1))
                            ->from('appeal')
                            ->whereColumn('appeal.id', $qualityTable . '.appeal_id')
                            ->whereIn('appeal.status', [0, 2]);
                    });
            });
    }
}
