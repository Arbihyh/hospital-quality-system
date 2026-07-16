<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\PatientInfo;
use App\Services\ToolsService;
use App\Services\DebugItemsService;
use App\Services\CaseQualityService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\CsvService;

/**
 * 全病历质控
 *
 * Class CaseQualityController
 * @package App\Http\Controllers\Api
 * @group case-quality
 */
class CaseQualityController extends Controller
{

    /**
     * analysis
     * /api/case-quality/analysis
     * 质量分析
     * @group case-quality
     * @bodyParam start_time string  开始时间  日期字符串格式
     * @bodyParam end_time string  结束时间  日期字符串格式
     *
     * @response {
     *  "code":200,
     *    "msg":"结果",
     *  "data":{
     *       "case_total":"病案数量",
     *       "defect_case_total":"缺陷病案数",
     *  },
     * "time":123787842
     * }
     */
    public function analysis(Request $request)
    {
        $queryCond = $this->commonQueryCond($request);

        // 病例数量
        $caseTotal = CaseQualityService::getCaseTotalByEs($queryCond);

        // 缺陷病案数
        $defectCaseTotal = CaseQualityService::getDefectCaseTotal($queryCond);

        $data['case_total'] = $caseTotal ?? 0;
        $data['defect_case_total'] = $defectCaseTotal ?? 0;

        DebugItemsService::getInstance()->calcDebugData($queryCond->debugis, $data);
        return ToolsService::jsonSuccess($data);
    }

    /**
     * ranking_department
     * /api/case-quality/ranking_department
     * 科室排名 前10条
     * @group case-quality
     * @bodyParam start_time string  开始时间  日期字符串格式
     * @bodyParam end_time string  结束时间  日期字符串格式
     *
     * @response {
     *  "code":200,
     *    "msg":"结果",
     *  "data": {
     *      "list":[{
     *          "name":"科室名称",
     *          "total_medical":"病案数",
     *          "total_error_medical":"缺陷病案数",
     *      }]
     *  },
     * "time":123787842
     * }
     */
    public function rankingDepartment(Request $request)
    {
        $queryCond = $this->commonQueryCond($request);

        $data = ['list' => [], 'count' => 0];
        // 总病例总数
        $caseTotal = CaseQualityService::getCaseTotalByEs($queryCond);

        $departmentCases = CaseQualityService::getDepartmentCases($queryCond);
        $departmentDefectCases = CaseQualityService::getDepartmentDefectCases($queryCond);

        DebugItemsService::getInstance()->putDebugItem('department_data', [
            '$caseTotal' => $caseTotal,
            '$departmentCases' => $departmentCases,
            '$departmentDefectCases' => $departmentDefectCases,
        ], 'data');

        $collects = [];

        foreach ($departmentCases as $case) {
            $name = $case['key'];
            $collects[$name]['name'] = $name;
            $collects[$name]['total_medical'] = $case['doc_count'];
            $collects[$name]['total_error_medical'] = 0;
        }

        $departmentDefectCases = array_column($departmentDefectCases, 'doc_count', 'key');
        foreach ($collects as $key => &$v) {
            $v['total_error_medical'] = empty($departmentDefectCases[$key]) ? 0 : $departmentDefectCases[$key];
        }

        DebugItemsService::getInstance()->putDebugItem('merge', $collects, 'data:');

        if (!empty($collects)) {
            // 缺陷病案数/总病案数    数值小的在前
            $collects = collect($collects)->sortBy(function ($item, $key) {
                return $item['total_error_medical'];
            })->values()->take(10)->all();
            $collects = array_values($collects);

            $data = ['list' => $collects, 'count' => count($collects)];
        }


        DebugItemsService::getInstance()->calcDebugData($queryCond->debugis, $data);
        return ToolsService::jsonSuccess($data);
    }

    /**
     * defect_issues
     * /api/case-quality/defect_issues
     * 缺陷问题列表
     * @group case-quality
     * @bodyParam start_time string  开始时间  日期字符串格式
     * @bodyParam end_time string  结束时间  日期字符串格式
     *
     * @response {
     *  "code":200,
     *    "msg":"结果",
     *  "data": {
     *      "list":[{
     *          "id":"序号",
     *          "desc":"缺陷描述",
     *          "field":"缺陷字段",
     *          "level":"缺陷分级",
     *          "total_num":"缺陷数量",
     *      }]
     *  },
     * "time":123787842
     * }
     */
    public function defectIssues(Request $request)
    {

        $depIds = UserService::getCurrentUserDep($request);
        if (empty($depIds)) {
            return ToolsService::jsonSuccess(['count' => 0, 'list' => []]);
        }

        $page = (int)$request->post('page', 1);
        $size = (int)$request->post('size', 10);

        $queryCond = $this->commonQueryCond($request);

        $sql = "
            SELECT case_quality_zm.rule_id, COUNT(1) as total_num
            FROM patient_info
            join case_quality_zm ON case_quality_zm.JZHM = patient_info.MED_REC_ID
                AND case_quality_zm.is_correction = 0
                AND case_quality_zm.is_appeal = 0
                AND case_quality_zm.is_ignore = 0
            join ZY_BRRY ON ZY_BRRY.ZYH = case_quality_zm.JZHM
        ";

        $bindings = [];
        $where = [];

        // patient_info 条件
        $where[] = "patient_info.in_hospital = 2";
        $where[] = "patient_info.zm_score IS NOT NULL";

        if ($queryCond->start_time && $queryCond->end_time) {
            $where[] = "patient_info.AAC01 >= ?";
            $where[] = "patient_info.AAC01 <= ?";
            $bindings[] = $queryCond->start_time . ' 00:00:00';
            $bindings[] = date("Y-m-d 23:59:59", strtotime($queryCond->end_time));
        }

        if ($queryCond->department) {
            $placeholders = implode(',', array_fill(0, count($queryCond->department), '?'));
            $where[] = "patient_info.AAC02C IN ($placeholders)";
            $bindings = array_merge($bindings, $queryCond->department);
        }

        if ($queryCond->AAA28) {
            $where[] = "patient_info.AAA28 = ?";
            $bindings[] = $queryCond->AAA28;
        }

        // ZY_BRRY 条件
        if (is_array($depIds) && !empty($depIds)) {
            $placeholders = implode(',', array_fill(0, count($depIds), '?'));
            $where[] = "ZY_BRRY.BRKS IN ($placeholders)";
            $bindings = array_merge($bindings, $depIds);
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " GROUP BY case_quality_zm.rule_id ORDER BY total_num DESC";

        $statsRaw = DB::select($sql, $bindings);
        $stats = collect($statsRaw)->keyBy('rule_id');

        if ($stats->isEmpty()) {
            return ToolsService::jsonSuccess(['count' => 0, 'list' => []]);
        }

        $ruleIds = $stats->keys()->toArray();

        // 区分普通规则和设置规则
        $normalRuleIds = array_filter($ruleIds, fn($id) => $id < 1000000);
        $settingRuleIds = array_map(
            fn($id) => $id - 1000000,
            array_filter($ruleIds, fn($id) => $id >= 1000000)
        );

        $rules = [];

        // 查询 case_rule
        if (!empty($normalRuleIds)) {
            $caseRules = DB::table('case_rule')
                ->select('id', 'category', 'notice')
                ->whereIn('id', $normalRuleIds)
                ->where('status', 1)
                ->when($queryCond->type, function ($query) use ($queryCond) {
                    return $query->where('type', $queryCond->type);
                })
                ->when($queryCond->case_title, function ($query) use ($queryCond) {
                    return $query->whereIn('category', $queryCond->case_title);
                })
                ->when($queryCond->rule_id, function ($query) use ($queryCond) {
                    return $query->whereIn('notice', $queryCond->rule_id);
                })
                ->get()->toArray();
            $rules = array_merge($rules, $caseRules);
        }

        // 查询 rule_setting
        if (!empty($settingRuleIds)) {
            $settingRules = DB::table('rule_setting')
                ->selectRaw('id+1000000 as id, case_type as category, description as notice')
                ->whereIn('id', $settingRuleIds)
                ->where('status', 1)
                ->when($queryCond->type, function ($query) use ($queryCond) {
                    return $query->where('type', $queryCond->type);
                })
                ->when($queryCond->case_title, function ($query) use ($queryCond) {
                    return $query->whereIn('case_type', $queryCond->case_title);
                })
                ->when($queryCond->rule_id, function ($query) use ($queryCond) {
                    return $query->whereIn('description', $queryCond->rule_id);
                })
                ->get()->toArray();
            $rules = array_merge($rules, $settingRules);
        }
        $rules = array_column($rules, null, 'id');

        $list = [];
        foreach ($stats as $ruleId => $stat) {
            if (isset($rules[$ruleId])) {
                $rule = $rules[$ruleId];
                $list[] = [
                    'key' => $ruleId,
                    'total_num' => (int)$stat->total_num,
                    'field' => $rule->category,
                    'desc' => $rule->notice,
                ];
            }
        }

        // 计算比例
        $scoreTotal = array_sum(array_column($list, 'total_num'));

        // 计算 proportion 的分母 - 参考 IndexController 的 case_quality_total 计算方式
        // 统计 zm_score < 100 的缺陷病案数，作为占比的分母
        $defectTotalQuery = PatientInfo::query()
            ->join('ZY_BRRY', 'ZY_BRRY.ZYH', '=', 'patient_info.MED_REC_ID')
            ->leftJoin('patient_info_v2', function ($join) {
                $join->on('patient_info_v2.ZYH', '=', 'patient_info.MED_REC_ID')
                    ->where('patient_info_v2.status', '=', 0);
            })
            ->when(is_array($depIds), function ($query) use ($depIds) {
                return $query->whereIn('ZY_BRRY.BRKS', $depIds);
            })
            ->whereNotNull('patient_info.MED_REC_ID')
            ->where("patient_info.in_hospital", 2)
            ->whereNotNull('patient_info.zm_score');

        // 应用查询条件（与上面的 SQL 查询保持一致）
        if ($queryCond->start_time && $queryCond->end_time) {
            $defectTotalQuery->where('patient_info.AAC01', '>=', $queryCond->start_time)
                ->where('patient_info.AAC01', '<=', $queryCond->end_time);
        }

        if ($queryCond->department) {
            $defectTotalQuery->whereIn('patient_info.AAC02C', $queryCond->department);
        }

        if ($queryCond->AAA28) {
            $defectTotalQuery->where('patient_info.AAA28', $queryCond->AAA28);
        }

        $defectTotal = $defectTotalQuery->count();

        foreach ($list as &$v) {
            $v["defect_total"] = $defectTotal;
            $v["proportion"] = $defectTotal > 0 ? round($v["total_num"] / $defectTotal * 100, 2) . '%' : '0%';
        }

        $isExport = $request->post("is_export");
        if ($isExport) {
            $exportData = [];
            $exportData[] = ['序号', '缺陷描述', '病历目录', '缺陷数量', '总例数'];
            foreach ($list as $idx => $item) {
                $exportData[] = [
                    $idx + 1,
                    $item['desc'],
                    $item['field'],
                    $item['total_num'],
                    $item['defect_total'],
                ];
            }
            $csv = new CsvService();
            $csv->filename = $csv->charset('缺陷病例数量列表', 'UTF-8');
            return $csv->export($exportData);
        }

        $totalCount = count($list);
        $page = max(1, $page);
        $size = max(1, $size);
        $pageStart = ($page - 1) * $size;
        $list = array_slice($list, $pageStart, $size);

        return ToolsService::jsonSuccess(['count' => $totalCount, 'list' => $list]);
    }

    public function commonQueryCond(Request $request)
    {
        $start_time = $request->post("startTime"); //日期字符串格式
        $end_time = $request->post("endTime");
        $department = $request->post("KS_CODE");
        $AAA28 = $request->post("AAA28");
        $type = $request->post("type");
        $debugis = $request->post("debugis", 0);
        if (empty($start_time)) {
            $start_time = date('Y-01-01');
        } else {
            $start_time = date('Y-m-d 00:00:00', strtotime($start_time));
        }
        if (empty($end_time)) {
            $end_time = date('Y-12-31');
        } else {
            $end_time = date('Y-m-d 23:59:59', strtotime($end_time));
        }

        $obj = new \stdClass();

        $obj->AAA28 = $AAA28;
        $obj->start_time = $start_time;
        $obj->end_time = $end_time;
        $obj->debugis = $debugis;
        $obj->department = $department;
        $obj->type = $type;
        $obj->rule_id = 0;
        $obj->case_title = [];
        $case_title = $request->post("case_title", 0);
        if ($case_title) {
            $obj->case_title = explode(',', $case_title);
        }

        $case_notice = $request->post("case_notice", 0);
        if ($case_notice) {
            $obj->rule_id = explode(',', $case_notice);
        }

        return $obj;
    }


    /**
     * 返回数组指定键值组
     */
    private function keyGetval($arr, $key)
    {
        $arrs = [];
        foreach ($arr as $v) {
            $arrs[] = $v[$key];
        }
        return $arrs;
    }
}
