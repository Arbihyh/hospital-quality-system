<?php

namespace App\Http\Controllers\Api;

use App\Exports\DataExport;
use App\Http\Controllers\Controller;
use App\Model\CaseQuality;
use App\Model\CaseQualityDoctor;
use App\Model\Department;
use App\Model\PatientInfo;
use App\Model\Staff;
use App\Model\User;
use App\Services\CsvService;
use App\Services\ToolsService;
use App\Services\CaseQualityService;
use App\Services\UserService;
use App\Services\ElasticsearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache; // Added Cache facade
use Maatwebsite\Excel\Facades\Excel;

/**
 * 全病历质控
 *
 * Class CaseQualityController
 * @package App\Http\Controllers\Api
 * @group case-quality
 */
class CaseQualityController extends Controller
{
    public $doctor = "王华,孙雪华,韩珂,李新友,张雪峰,王志永,黄成日,徐爱国,朱永林,王军,韩文生,尹高平,李贺,姜宏杰,徐宁,曹庆华,胡雁,全仁贵,李霞,李丰玲,李明,王佳森,张淑鹏,马维昌,董鑫,杨坚,张承,张刚,刘松林,张欣欣,孙凤娇,全无瑕,缪延栋,张芳,张鑫雨,李华,张赟,李文杰,孙英红,刘越,贺鹏,李丽霞,崔守斌,贾茹,邢乃飞,李攀,张文杰,王玉红,刘华,李雷,高青,于范亭,王曦,衣高峰,吕晴晴,尹荣江,高倩,李成立,岳文静,孙政尧,孙艺英,代春华,李华龙,赵善润,孙成飞,张玉茹,李威威,孙德平,王勇1,邹阿鹏,刘晓亮,李传波,李燕,纪芳,尹雯,王寿寿,张宇,李瑞健,孙小钧,张爱花,赵威东,张永明,田爱民,连慧秀,杜晓峰,高蓉,于文洁,曲凡勇,程霄瀚,鲁科翔";

    public function dockerList(Request $request)
    {
        $doctorArray = Staff::query()->get(['code', 'name'])->toArray();
        return ToolsService::jsonSuccess($doctorArray);
    }

    /**
     * 获取文书类型列表
     * /api/case-quality/document_type_list
     * @return array
     */
    public function getDocumentTypeList()
    {
        $caseRuleDocumentTypes = DB::table('case_rule')
            ->where('status', '!=', 3)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->groupBy('category')
            ->pluck('category')
            ->toArray();

        $ruleSettingDocumentTypes = DB::table('rule_setting')
            ->where('status', '!=', 3)
            ->whereNotNull('case_type')
            ->where('rule_type', '=', '普通规则')
            ->where('case_type', '!=', '')
            ->groupBy('case_type')
            ->pluck('case_type')
            ->toArray();

        $list = array_values(array_unique(array_merge($caseRuleDocumentTypes, $ruleSettingDocumentTypes)));
        sort($list, SORT_NATURAL);

        return ToolsService::returnData(200, ['list' => $list]);
    }

    /**
     * 获取规则性质列表
     * /api/case-quality/rule_nature_list
     * @return array
     */
    public function getRuleNatureList()
    {
        $caseRuleNatures = DB::table('case_rule')
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->groupBy('type')
            ->pluck('type')
            ->toArray();

        $ruleSettingNatures = DB::table('rule_setting')
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->groupBy('type')
            ->pluck('type')
            ->toArray();

        $list = array_values(array_unique(array_merge($caseRuleNatures, $ruleSettingNatures)));
        sort($list, SORT_NATURAL);

        return ToolsService::returnData(200, ['list' => $list]);
    }

    /**
     * 获取整改状态列表
     * /api/case-quality/correction_status_list
     * @return array
     */
    public function getCorrectionStatusList()
    {
        return ToolsService::returnData(200, [
            'list' => ['已整改', '未整改'],
        ]);
    }

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

        if (is_array($queryCond->dep_ids) && empty($queryCond->dep_ids)) {
            return ToolsService::jsonSuccess(['case_total' => 0, 'defect_case_total' => 0]);
        }

        // 病例数量
        $caseTotal = CaseQualityService::getCaseTotalByEs($queryCond);

        // 缺陷病案数
        $defectCaseTotal = CaseQualityService::getDefectCaseTotal($queryCond);

        $data['case_total'] = $caseTotal ?? 0;
        $data['defect_case_total'] = $defectCaseTotal ?? 0;

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
        if (!$queryCond->dep_id) {
            return ToolsService::jsonSuccess($data);
        }
        $departmentCases = CaseQualityService::getDepartmentCases($queryCond);
        $departmentDefectCases = CaseQualityService::getDepartmentDefectCases($queryCond);

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


        if (!empty($collects)) {
            // 缺陷病案数/总病案数    数值小的在前
            $collects = collect($collects)->sortBy(function ($item, $key) {
                return $item['total_error_medical'];
            })->values()->take(10)->all();
            $collects = array_values($collects);

            $data = ['list' => $collects, 'count' => count($collects)];
        }

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
        $totalStart = microtime(true);

        $queryCond = $this->commonQueryCond($request);
        //如果start_time为空，则设置为3年前
        Log::info('bazb defectIssues - 步骤1: start_time', [$queryCond->start_time]);
        Log::info('bazb defectIssues - 步骤1: end_time', [$queryCond->end_time]);
        if (empty($queryCond->start_time)) {
            $queryCond->start_time = date('Y-01-01 00:00:00', strtotime('-3 year'));
        }
        //如果end_time为空，则设置为当前时间
        if (empty($queryCond->end_time)) {
            $queryCond->end_time = date('Y-12-31 23:59:59');
        }
        $data = ['list' => [], 'count' => 0];

        if (empty($queryCond->dep_ids)) {
            return ToolsService::jsonSuccess($data);
        }

        // ============ 步骤1：使用原生SQL强制JOIN顺序 ============
        $step1Start = microtime(true);

        $bindings = [];
        $where = [];

        // 基础条件
        $where[] = "patient_info.score < 100";
        $where[] = "case_quality.is_correction = 0";
        $where[] = "case_quality.is_appeal = 0";
        $where[] = "case_quality.is_ignore = 0";
        $where[] = "case_rule.status = 1";

        $whereCase = 'patient_info.AAB01 >= "' . $queryCond->start_time . '" AND patient_info.AAB01 <= "' . $queryCond->end_time . '"';
        $where[] = $whereCase;
        // 搜索在院状态
        if ($queryCond->status == 2) {
            $where[] = "patient_info.in_hospital = 1";
        } else if ($queryCond->status == 3) {
            $where[] = "patient_info.AAC01 > ?";
            $bindings[] = date("Y-m-d 00:00:00");
        } else if ($queryCond->status == 4) {
            $where[] = "patient_info.in_hospital = 2";
        } else {
            $where[] = "(patient_info.in_hospital = 1 OR patient_info.AAC01 > ?)";
            $bindings[] = date("Y-m-d 00:00:00");
        }

        // 搜索病案号
        if ($queryCond->AAA28) {
            $where[] = "ZY_BRRY.AAA28 = ?";
            $bindings[] = $queryCond->AAA28;
        }

        // 搜索病人科室
        if (!empty($queryCond->brks)) {
            $brks = is_array($queryCond->brks) ? $queryCond->brks : [$queryCond->brks];
            $placeholders = implode(',', array_fill(0, count($brks), '?'));
            $where[] = "ZY_BRRY.BRKS IN ($placeholders)";
            $bindings = array_merge($bindings, $brks);
        }

        // 当前账号权限范围内可以查看的信息
        if (is_array($queryCond->dep_ids) && !empty($queryCond->dep_ids)) {
            $placeholders = implode(',', array_fill(0, count($queryCond->dep_ids), '?'));
            $where[] = "ZY_BRRY.BRKS IN ($placeholders)";
            $bindings = array_merge($bindings, $queryCond->dep_ids);
        }

        // 使用 STRAIGHT_JOIN 强制从 patient_info 开始
        $sql = "
            SELECT case_quality.rule_id, COUNT(1) as total_num
            FROM patient_info
            LEFT JOIN case_quality ON case_quality.JZHM = patient_info.MED_REC_ID
                AND case_quality.is_correction = 0
                AND case_quality.is_appeal = 0
                AND case_quality.is_ignore = 0
            INNER JOIN (SELECT id,title,notice,type,category,status FROM case_rule union all select id+1000000,`object` as title,description as notice,type,case_type as category,status from rule_setting rs) as case_rule ON case_quality.rule_id=case_rule.id
            LEFT JOIN ZY_BRRY ON case_quality.JZHM = ZY_BRRY.ZYH
            WHERE " . implode(' AND ', $where) . "
            GROUP BY case_quality.rule_id
            ORDER BY total_num DESC
        ";
        Log::info('bazb defectIssues - 步骤1: 统计查询sql', [$sql]);
        $stats = DB::select($sql, $bindings);

        $step1End = microtime(true);
        Log::info('bazb defectIssues - 步骤1: 统计查询完成', [
            'duration' => round(($step1End - $step1Start) * 1000, 2) . 'ms',
            'count' => count($stats)
        ]);

        if (empty($stats)) {
            return ToolsService::jsonSuccess($data);
        }

        // ============ 步骤2：查询规则信息 ============
        $step2Start = microtime(true);

        $ruleIds = array_column($stats, 'rule_id');

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
                ->select('id', 'title', 'notice')
                ->whereIn('id', $normalRuleIds)
                ->where('status', 1)
                ->get();

            foreach ($caseRules as $rule) {
                $rules[(int)$rule->id] = $rule; // 使用整数ID作为key
            }
        }

        // 查询 rule_setting
        if (!empty($settingRuleIds)) {
            $settingRules = DB::table('rule_setting')
                ->selectRaw('id+1000000 as id, `object` as title, description as notice')
                ->whereIn('id', $settingRuleIds)
                ->where('status', 1)
                ->get();

            foreach ($settingRules as $rule) {
                $rules[(int)$rule->id] = $rule; // 使用整数ID作为key
            }
        }

        $step2End = microtime(true);
        Log::info('bazb defectIssues - 步骤2: 规则查询完成', [
            'duration' => round(($step2End - $step2Start) * 1000, 2) . 'ms',
            'rules_count' => count($rules),
            'rules_keys_sample' => array_slice(array_keys($rules), 0, 10)
        ]);

        Log::info('bazb defectIssues - 步骤2.1: 规则查询完成', [
            'stats_count' => count($stats),
            'rules_keys' => array_keys($rules)
        ]);

        // ============ 步骤3：合并结果 ============
        $step3Start = microtime(true);

        $res = [];

        foreach ($stats as $stat) {
            $ruleId = (int)$stat->rule_id; // 确保rule_id是整数

            if (isset($rules[$ruleId])) {
                $rule = $rules[$ruleId];
                $res[] = [
                    'rule_id' => $ruleId,
                    'total_num' => (int)$stat->total_num,
                    'field' => $rule->title,
                    'desc' => $rule->notice,
                ];
            }
        }

        $step3End = microtime(true);
        Log::info('bazb defectIssues - 步骤3: 合并完成', [
            'duration' => round(($step3End - $step3Start) * 1000, 2) . 'ms',
            'res_count' => count($res),
            'missing_rules' => count($stats) - count($res)
        ]);

        // 已经按 total_num DESC 排序了，不需要再排序
        $data['list'] = $res;
        $data['count'] = count($res);

        $totalEnd = microtime(true);
        Log::info('bazb defectIssues - 总耗时', [
            'total_duration' => round(($totalEnd - $totalStart) * 1000, 2) . 'ms'
        ]);

        return ToolsService::jsonSuccess($data);
    }

    /**
     * 医生排名
     * @param Request $request
     * @return array|string|null
     */
    public function doctorRanking(Request $request)
    {

        $depIds = UserService::getCurrentUserDep($request);
        if (empty($depIds)) {
            return ToolsService::returnData(200, ['list' => [], 'count' => 0]);
        }

        $startTime = $request->post('startTime', '');
        if (empty($startTime)) {
            $startTime = $request->post('start_time', '');
        }
        $endTime = $request->post('endTime', '');
        if (empty($endTime)) {
            $endTime = $request->post('end_time', '');
        }
        if (!empty($startTime)) {
            $startTime = date('Y-m-d 00:00:00', strtotime($startTime));
        } else {
            $startTime = date('Y-01-01');
        }
        if (!empty($endTime)) {
            $endTime = date('Y-m-d 23:59:59', strtotime($endTime));
        } else {
            $endTime = date('Y-12-31');
        }

        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        $KS_CODE = $request->post('KS_CODE', []);
        $isExport = $request->post('is_export', 0);
        $AAA28 = $request->post('AAA28', 0);
        $orderKey = $request->post('order_key', 'total_num') ?: 'total_num';


        // ============ 从汇总表查询（极快！）============
        $bindings = [];
        $where = [];

        // 时间范围（使用 brry 表的 AAC01）
        $where[] = "brry.AAC01 >= ?";
        $where[] = "brry.AAC01 <= ?";
        $bindings[] = $startTime;
        $bindings[] = $endTime;

        if ($AAA28) {
            // 通过住院号过滤，使用 brry.AAA28
            $where[] = "brry.AAA28 = ?";
            $bindings[] = $AAA28;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // 直接从汇总表查询并关联 brry 表，速度极快
        $sql = "
            SELECT
                drs.code,
                COUNT(DISTINCT drs.zyh) as total_num,
                SUM(drs.score) as score
            FROM doctor_ranking_summary drs
            LEFT JOIN ZY_BRRY AS brry ON brry.ZYH = drs.zyh
            $whereClause
            GROUP BY drs.code
        ";
        $data = DB::select($sql, $bindings);

        if (empty($data)) {
            return ToolsService::returnData(200, ['count' => 0, 'list' => []]);
        }

        $data = json_decode(json_encode($data), true);
        $totalCount = count($data);

        // 查询员工和部门信息
        $codes = array_column($data, 'code');

        $staff = Staff::query()
            ->whereIn('code', $codes)
            ->get(['code', 'name', 'ksdm'])
            ->keyBy('code')
            ->toArray();

        $depIdsForQuery = array_filter(array_unique(array_column($staff, 'ksdm')));
        $depArray = [];
        if (!empty($depIdsForQuery)) {
            $depArray = Department::query()
                ->whereIn('dep_id', $depIdsForQuery)
                ->pluck('dep_name', 'dep_id')
                ->toArray();
        }

        // 处理数据

        foreach ($data as $k => &$v) {
            $v["doc_count"] = $v["total_num"];
            $v["avg_score"] = $v["total_num"] > 0
                ? round(($v["total_num"] * 100 - $v["score"]) / $v["total_num"], 2)
                : 0;
            $v["key"] = $staff[$v["code"]]["name"] ?? "";
            $v["dep_name"] = $depArray[$staff[$v["code"]]["ksdm"] ?? 0] ?? "";
            $v["rank"] = $k + 1;
            if ($KS_CODE && is_array($KS_CODE) && !empty($staff[$v["code"]]["ksdm"]) && !in_array($staff[$v["code"]]["ksdm"], $KS_CODE)) {
                unset($data[$k]);
            }
        }

        // 根据指定键对$data数组排序，假设以'total_num'降序排序为例
        usort($data, function ($a, $b) use ($orderKey) {
            return $b[$orderKey] <=> $a[$orderKey];
        });

        // 导出或分页
        if ($isExport == 1) {
            $exportData[] = ['排名', '医生姓名', '医生工号', '医生科室', '病历总数', '总扣分', '平均得分'];
            foreach ($data as $value) {
                $exportData[] = [
                    $value['rank'],
                    $value['key'],
                    $value['code'],
                    $value['dep_name'],
                    $value['total_num'],
                    $value['score'],
                    $value['avg_score'],
                ];
            }
            $csv = new CsvService();
            $csv->filename = $csv->charset('医生排名', 'UTF-8');
            return $csv->export($exportData);
        }

        $pageStart = ($page - 1) * $pageSize;
        $returnData = array_slice($data, $pageStart, $pageSize);
        return ToolsService::returnData(200, ['count' => $totalCount, 'list' => $returnData]);
    }

    /**
     * 医生排名--有任务插入，还没有改完 todo
     * @param Request $request
     * @return array|string|null
     */
    public function doctorRankingV2(Request $request)
    {
        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        $isExport = $request->post('is_export', 0);
        if ($isExport == 1) {
            $page = 1;
            $pageSize = 10000;
        }


        // 科室信息
        $dep = Department::query()->get()->toArray();
        $dep = array_column($dep, 'dep_name', 'dep_id');

        // 员工信息
        $staff = Staff::query()->whereIn('YGJB', [1, 2, 3, 6, 7])->get()->toArray();
        foreach ($staff as $k => $s) {
            $staff[$k]['dep_name'] = '';
            if (!empty($dep[$s['ksdm']])) {
                $staff[$k]['dep_name'] = $dep[$s['ksdm']];
            }
        }
        $staff = array_column($staff, null, 'code');
        if ($startTime && $endTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
        } else {
            $startTime = date('Y-01-01') . ' 00:00:00';
            $endTime = date('Y-12-31') . ' 23:59:59';
        }

        $list = PatientInfo::query()
            ->selectRaw('patient_doctor_info.AEE03_CODE as code,count(1) as doc_count,sum(if case_quality.id is null,0,1) as defect_doc_count,sum(case_rule.score) as `score`')
            ->leftJoin('case_quality', 'case_quality.JZHM', '=', 'patient_info.MED_REC_ID')
            ->leftJoin('patient_doctor_info', 'patient_doctor_info.AAA28', '=', 'patient_info.MED_REC_ID')
            ->leftJoin(DB::raw('(SELECT id,title,notice FROM case_rule union all select id+1000000,`object` as title,description as notice from rule_setting rs) as case_rule'), 'case_quality.rule_id', '=', 'case_rule.id')
            ->when($startTime && $endTime, function ($query) use ($startTime, $endTime) {
                return $query->whereBetween('patient_info.AAC01', [$startTime, $endTime]);
            })
            ->groupBy('patient_doctor_info.AEE03_CODE')->orderBy('total_num', 'desc')->get()->toArray();

        if ($list) {
            foreach ($list as &$v) {
                $v["key"] = $staff[$v["code"]]["name"] ?? "";
                $v["dep_name"] = $staff[$v["code"]]["dep_name"] ?? "";
            }
        }


        if ($isExport == 1) {
            $exportData[] = ['排名', '医生姓名', '医生工号', '医生科室', '病历总数', '缺陷病例数', '总扣分', '平均得分'];
            foreach ($list as $key => $value) {
                $exportData[] = [
                    ($key + 1),
                    $value['key'],
                    $value['code'],
                    $value['dep_name'],
                    $value['doc_count'],
                    $value['defect_doc_count'],
                    $value['score'],
                    $value['avg_score'],
                ];
            }
            $csv = new CsvService();
            $csv->filename = $csv->charset('医生排名', 'UTF-8');

            return $csv->export($exportData);
        }

        $page = ($page - 1) * $pageSize;
        $returnData = [];
        foreach ($list as $key => $value) {
            if ($key >= $page && count($returnData) < $pageSize) {
                $returnData[] = $value;
            }
        }

        return ToolsService::returnData(200, ['count' => count($list), 'list' => $returnData]);
    }

    /**
     * 病例等级
     */
    protected function getBlLevel($score)
    {
        if ($score > 90) return "优";
        if ($score > 80 && $score <= 90) return "良";
        if ($score > 70 && $score <= 80) return "中";
        if ($score < 70) return "差";
    }

    /**
     * 医生排名
     * @param Request $request
     * @return array|string|null
     */
    public function doctorRankingList(Request $request)
    {

        $query = CaseQualityDoctor::query()
            ->join('patient_info', 'case_quality_doctor.ZYH', '=', 'patient_info.MED_REC_ID')
            ->join('patient_doctor_info', 'patient_doctor_info.AAA28', '=', 'patient_info.MED_REC_ID')
            ->join('ZY_BRRY', 'ZY_BRRY.ZYH', '=', 'patient_info.MED_REC_ID')
            ->where('patient_info.in_hospital', 2);
        //where条件
        //时间范围
        $time = $request->get('time', []);
        if ($time[0] && $time[1]) {
            $startTime = date('Y-m-d', strtotime($time[0])) . ' 00:00:00';
            $endTime = date('Y-m-d', strtotime($time[1])) . ' 23:59:59';
            $query->whereBetween('patient_info.AAC01', [$startTime, $endTime]);
        } else {
            $startTime = date('Y-01-01') . ' 00:00:00';
            $endTime = date('Y-12-31') . ' 23:59:59';
            $query->whereBetween('patient_info.AAC01', [$startTime, $endTime]);
        }


        //医生code
        $doctorCodeArray = $request->post('doctor_code', []);
        if (!empty($doctorCodeArray)) {
            $query->whereIn('case_quality_doctor.code', $doctorCodeArray);
        };
        //所属科室
        $department = $request->post('department', '');
        if (!empty($department)) $query->whereIn('patient_info.AAC02C', $department);
        //病例级别
        $level = $request->post('level', '');
        if (!empty($level)) {
            switch ($level) {
                case '优':
                    $query->where('patient_info.zm_score', '>', 90);
                    break;
                case '良':
                    $query->whereBetween('patient_info.zm_score', [80, 90]);
                    break;
                case '中':
                    $query->whereBetween('patient_info.zm_score', [70, 80]);
                    break;
                case '差':
                    $query->where('patient_info.zm_score', '<', 70);
                    break;
            }
        }

        //住院号
        $AAA28 = $request->post('AAA28', '');
        if (!empty($AAA28)) $query->where('patient_info.AAA28', $AAA28);


        //是否导出与页吗
        $isExport = $request->get('is_export', 0);
        if ($isExport === 1) {
            $page = 1;
            $pageSize = 10000;
        } else {
            $page = $request->get('page', 1);
            $pageSize = $request->get('page_size', 10);
        }


        $rankingQuery = $query->select(
            'patient_info.AAC02C',
            'patient_info.MED_REC_ID as ZYH',
            'ZY_BRRY.AAA28',
            'patient_info.AAC01',
            'patient_info.AAC11N',
            'patient_doctor_info.AEE01 as KZRXM',
            'patient_doctor_info.AEE02 as ZHFZRYSXM',
            'patient_doctor_info.AEE03 as ZZYSXM',
            'patient_doctor_info.AEE04 as ZYYSXM',
            'patient_info.zm_score as score',
            'patient_info.zm_score_lv as score_lv',
            'patient_info.AAC11N'
        );


        $data = $rankingQuery->paginate($pageSize, $page)->toArray();


        //,'patient_doctor_info.YLZZ as ZZYISXM' //三院没有这个字段

        $depArray = Department::query()->get(['dep_id', 'dep_name'])->toArray();
        $depArray = array_column($depArray, 'dep_name', 'dep_id');

        $depArray = Department::query()->pluck('dep_name', 'dep_id')->toArray();
        foreach ($data['data'] as $k => $v) {
            $v['AAC11N'] = empty($v['AAC11N']) ? ($depArray[$v['AAC02C']] ?? "") : $v['AAC11N'];
            $v['level'] = $this->getBlLevel($v['score']);
            $data['data'][$k] = $v;
        }

        if ($isExport == 1) {
            $title = ['住院号码', '出院时间', '出院科室', '科主任', '主任(副主任)医师', '主治医生', '住院医生', '医疗组长', '病历评分', '病例等级'];
            array_unshift($data['data'], $title);
            $csv = new CsvService();
            $csv->filename = $csv->charset('医生排名', 'UTF-8');
            return $csv->export($data['data']);
        }
        $data['count'] = $data['total'];
        return ToolsService::returnData(200, $data);
    }

    public function medicalRecordDoctor(Request $request)
    {
        $doctor = explode(',', $this->doctor);
        $queryCond = $this->commonQueryCond($request);
        // 总病例数
        $must = [
            [
                'range' => [
                    "AAC01" => [
                        'gt' => $queryCond->start_time . ' 00:00:00',
                        'lt' => $queryCond->end_time . ' 23:59:59',
                    ]
                ]
            ]
        ];
        $fieldArr = ['KZRXM', 'ZHFZRYSXM', 'ZZYSXM', 'ZYYSXM', 'ZZYISXM'];
        $resData = [];
        foreach ($fieldArr as $v) {

            $must[1] = [
                'terms' => [
                    $v => $doctor
                ]
            ];
            $res = $this->getBucketData($must, $v);
            $resData = array_merge($resData, $res);
        }

        $newData = [];
        foreach ($resData as $item) {
            $md5Key = md5($item['key']);
            $item['score_total'] = $item['sum_score']['value'];
            if (!empty($newData[$md5Key])) {
                $newData[$md5Key]['doc_count'] += $item['doc_count'];
                $newData[$md5Key]['score_total'] += $item['score_total'];
            } else {
                $newData[$md5Key] = $item;
            }
        }

        // 缺陷病例
        $must = [
            [
                'range' => [
                    "AAC01" => [
                        'gt' => $queryCond->start_time . ' 00:00:00',
                        'lt' => $queryCond->end_time . ' 23:59:59',
                    ]
                ]
            ],
            [
                "term" => [
                    "is_defect" => 1
                ]
            ]
        ];
        $resData1 = [];
        foreach ($fieldArr as $v) {
            $must[2] = [
                'terms' => [
                    $v => $doctor
                ]
            ];
            $res = $this->getBucketData($must, $v);
            $resData1 = array_merge($resData1, $res);
        }

        $newData1 = [];
        foreach ($resData1 as $item1) {
            $md5Key = md5($item1['key']);
            if (!empty($newData1[$md5Key])) {
                $newData1[$md5Key]['doc_count'] += $item1['doc_count'];
            } else {
                $newData1[$md5Key] = $item1;
            }
        }

        // 合并数据
        foreach ($newData as $key => &$value) {
            $value['score'] = $value['doc_count'] * 100 - $value['score_total'];
            $value['avg_score'] = intval($value['score_total'] / $value['doc_count']);
            $value['defect_doc_count'] = !empty($newData1[$key]) ? $newData1[$key]['doc_count'] : 0;
        }

        array_multisort(array_column($newData, 'avg_score'), SORT_DESC, $newData);

        if ($request->post('is_export') == 1) {

            $exportData[] = ['排名', '医师姓名', '病例总数', '总扣分', '平均得分'];
            $index = 1;
            foreach ($newData as $key => $val) {
                $exportData[$key] = [$index, $val['key'], $val['doc_count'], $val['score'], $val['avg_score']];
                $index++;
            }
            ## 病案首页缺陷分析
            $csv = new CsvService();
            $csv->filename = $csv->charset('医生排名', 'UTF-8');
            return $csv->export($exportData);
        }

        $limit = $request->post('page_size', 10);
        $page = $request->post('page', 1);
        $pageData = array_chunk($newData, $limit);

        $data = ['count' => count($newData), 'list' => $pageData[$page - 1]];

        return ToolsService::jsonSuccess($data);
    }

    public function getBucketData($must = [], $field = '')
    {
        $piService = new ElasticsearchService('patient_info');
        $aggs = [
            $field => [
                'terms' => [
                    "field" => $field,
                    "size" => 2000
                ],
                "aggs" => [
                    "sum_score" => [
                        "sum" => [
                            "field" => "score"
                        ]
                    ]
                ]
            ]
        ];
        $params = $piService->clearMust()->queryByMustBatch($must)->paginate(1, 0)->aggs($aggs)->getParams();
        $mzRes = app('es')->search($params);
        $mzRes = $piService->getDataByEs($mzRes);
        if (empty($mzRes[2]) || empty($mzRes[2][$field]) || empty($mzRes[2][$field]['buckets'])) {
            return [];
        }
        return $mzRes[2][$field]['buckets'];
    }

    /**
     * 事中质控记录列表
     * /api/case-quality/shizhong_quality_records
     * @param Request $request
     * @return array
     */
    public function shizhongQualityRecords(Request $request)
    {
        $page = (int)$request->post('page', 1);
        $pageSize = (int)$request->post('page_size', 10);
        $page = $page > 0 ? $page : 1;
        $pageSize = $pageSize > 0 ? $pageSize : 10;
        $isExport = (int)$request->post('is_export', 0);

        $ruleSql = "
            SELECT
                id,
                notice AS rule_name,
                level AS rule_level,
                category AS document_type,
                type AS rule_nature
            FROM case_rule
            WHERE is_tj = 1
            UNION ALL
            SELECT
                id + 1000000 AS id,
                description AS rule_name,
                error_level AS rule_level,
                case_type AS document_type,
                type AS rule_nature
            FROM rule_setting
            WHERE is_tj = 1
        ";

        $query = DB::table('case_quality_shizhong_records as record')
            ->leftJoin('ZY_BRRY as brry', 'brry.ZYH', '=', 'record.jzhm')
            ->leftJoin('department as dep', 'dep.dep_id', '=', 'brry.BRKS')
            ->leftJoin('case_quality as uncorrected_quality', function ($join) {
                $join->on('uncorrected_quality.rule_id', '=', 'record.rule_id')
                    ->on('uncorrected_quality.JZHM', '=', 'record.jzhm')
                    ->where('uncorrected_quality.is_correction', '=', 0)
                    ->where('uncorrected_quality.is_appeal', '=', 0)
                    ->where('uncorrected_quality.is_ignore', '=', 0);
            })
            ->join(DB::raw("({$ruleSql}) as rule_info"), 'rule_info.id', '=', 'record.rule_id');

        $this->buildShizhongQualityRecordWhere($query, $request);

        $total = (clone $query)->distinct()->count('record.id');
        $rowsQuery = $query
            ->distinct()
            ->select([
                'record.id',
                'record.jzhm',
                'record.rule_id',
                'record.lock_count',
                'record.resident_doctor',
                'record.medical_record_no',
                'record.patient_name',
                'record.bed_no',
                'record.last_quality_time',
                'record.department as first_department',
                'brry.BRKS as current_department_id',
                'brry.AAB01 as admission_time',
                'brry.AAC01 as discharge_time',
                'rule_info.rule_name',
                'rule_info.rule_level',
                'rule_info.document_type',
                'rule_info.rule_nature',
                'dep.dep_name as current_department_name',
            ])
            ->selectRaw('CASE WHEN uncorrected_quality.id IS NULL THEN 0 ELSE 1 END AS has_uncorrected_quality')
            ->selectRaw('CASE WHEN rule_info.rule_level = 1 AND uncorrected_quality.id IS NOT NULL THEN 0 ELSE 1 END AS required_uncorrected_sort')
            ->selectRaw('CASE WHEN uncorrected_quality.id IS NOT NULL THEN 0 ELSE 1 END AS uncorrected_sort')
            ->selectRaw("CASE WHEN brry.AAC01 IS NULL OR brry.AAC01 = '' OR brry.AAC01 = '0000-00-00 00:00:00' THEN 0 ELSE 1 END AS discharge_sort");
        $rowsQuery = $this->applyShizhongQualityRecordOrder($rowsQuery, $request);

        $rows = $isExport === 1 ? $rowsQuery->get() : $rowsQuery->forPage($page, $pageSize)->get();
        $appealStatusMap = $this->getShizhongLatestAppealStatusMap($rows);

        $list = [];
        foreach ($rows as $row) {
            $dischargeTime = $this->formatShizhongQualityTime($row->discharge_time);
            $rowKey = $this->getShizhongQualityRecordKey($row->jzhm, $row->rule_id);
            $list[] = [
                'id' => $row->id,
                'jzhm' => $row->jzhm,
                'rule_id' => $row->rule_id,
                'rule_name' => $row->rule_name ?: '',
                'department' => $row->current_department_name ?: ($row->current_department_id ?: $row->first_department),
                'lock_count' => (int)$row->lock_count,
                'correction_status' => $this->getShizhongCorrectionStatus($row),
                'resident_doctor' => $row->resident_doctor ?: '',
                'medical_record_no' => $row->medical_record_no ?: '',
                'patient_name' => $row->patient_name ?: '',
                'bed_no' => $row->bed_no ?: '',
                'admission_time' => $this->formatShizhongQualityTime($row->admission_time),
                'discharge_time' => $dischargeTime,
                'rule_type' => ((int)$row->rule_level === 1) ? '强制' : '建议',
                'rule_nature' => $row->rule_nature ?: '',
                'document_type' => $row->document_type ?: '',
                'appeal_status' => $this->getShizhongAppealStatus($appealStatusMap[$rowKey] ?? null),
                'last_quality_time' => $this->formatShizhongQualityTime($row->last_quality_time),
                'is_discharge' => $dischargeTime === '' ? '否' : '是',
            ];
        }

        if ($isExport === 1) {
            return $this->exportShizhongQualityRecords($list);
        }

        return ToolsService::returnData(200, ['count' => $total, 'list' => $list]);
    }

    /**
     * 事中质控按规则分组统计
     * /api/case-quality/shizhong_quality_rule_statistics
     * @param Request $request
     * @return array
     */
    public function shizhongQualityRuleStatistics(Request $request)
    {
        $page = (int)$request->post('page', 1);
        $pageSize = (int)$request->post('page_size', 10);
        $page = $page > 0 ? $page : 1;
        $pageSize = $pageSize > 0 ? $pageSize : 10;
        $isExport = (int)$request->post('is_export', 0);

        $ruleSql = "
            SELECT
                id,
                notice AS rule_name,
                level AS rule_level,
                category AS document_type,
                type AS rule_nature
            FROM case_rule
            WHERE is_tj = 1
            UNION ALL
            SELECT
                id + 1000000 AS id,
                description AS rule_name,
                error_level AS rule_level,
                case_type AS document_type,
                type AS rule_nature
            FROM rule_setting
            WHERE is_tj = 1
        ";

        $query = DB::table('case_quality_shizhong_records as record')
            ->leftJoin('ZY_BRRY as brry', 'brry.ZYH', '=', 'record.jzhm')
            ->leftJoin('case_quality as uncorrected_quality', function ($join) {
                $join->on('uncorrected_quality.rule_id', '=', 'record.rule_id')
                    ->on('uncorrected_quality.JZHM', '=', 'record.jzhm')
                    ->where('uncorrected_quality.is_correction', '=', 0)
                    ->where('uncorrected_quality.is_appeal', '=', 0)
                    ->where('uncorrected_quality.is_ignore', '=', 0);
            })
            ->join(DB::raw("({$ruleSql}) as rule_info"), 'rule_info.id', '=', 'record.rule_id');

        $this->buildShizhongQualityRecordWhere($query, $request);

        $total = (clone $query)->distinct()->count('record.rule_id');
        $rowsQuery = $query
            ->select([
                'record.rule_id',
                'rule_info.rule_name',
                'rule_info.rule_level',
                'rule_info.rule_nature',
            ])
            ->selectRaw('COUNT(DISTINCT record.id) AS problem_count')
            ->selectRaw('SUM(COALESCE(record.lock_count, 0)) AS lock_count')
            ->selectRaw('COUNT(DISTINCT CASE WHEN uncorrected_quality.id IS NULL THEN record.id END) AS corrected_count')
            ->selectRaw('COUNT(DISTINCT CASE WHEN uncorrected_quality.id IS NOT NULL THEN record.id END) AS uncorrected_count')
            ->groupBy('record.rule_id', 'rule_info.rule_name', 'rule_info.rule_level', 'rule_info.rule_nature')
            ->orderBy('problem_count', 'desc')
            ->orderBy('record.rule_id', 'asc');
        $rows = $isExport === 1 ? $rowsQuery->get() : $rowsQuery->forPage($page, $pageSize)->get();

        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'rule_id' => $row->rule_id,
                'rule_name' => $row->rule_name ?: '',
                'problem_count' => (int)$row->problem_count,
                'lock_count' => (int)$row->lock_count,
                'corrected_count' => (int)$row->corrected_count,
                'uncorrected_count' => (int)$row->uncorrected_count,
                'rule_nature' => $row->rule_nature ?: '',
                'rule_type' => ((int)$row->rule_level === 1) ? '强制' : '建议',
            ];
        }

        if ($isExport === 1) {
            return $this->exportShizhongQualityRuleStatistics($list);
        }

        return ToolsService::returnData(200, ['count' => $total, 'list' => $list]);
    }

    /**
     * 事中质控按科室汇总
     * /api/case-quality/shizhong_quality_department_statistics
     * @param Request $request
     * @return array
     */
    public function shizhongQualityDepartmentStatistics(Request $request)
    {
        $page = (int)$request->post('page', 1);
        $pageSize = (int)$request->post('page_size', 10);
        $page = $page > 0 ? $page : 1;
        $pageSize = $pageSize > 0 ? $pageSize : 10;
        $isExport = (int)$request->post('is_export', 0);

        $ruleSql = "
            SELECT
                id,
                notice AS rule_name,
                level AS rule_level,
                category AS document_type,
                type AS rule_nature
            FROM case_rule
            WHERE is_tj = 1
            UNION ALL
            SELECT
                id + 1000000 AS id,
                description AS rule_name,
                error_level AS rule_level,
                case_type AS document_type,
                type AS rule_nature
            FROM rule_setting
            WHERE is_tj = 1
        ";

        $query = DB::table('case_quality_shizhong_records as record')
            ->leftJoin('ZY_BRRY as brry', 'brry.ZYH', '=', 'record.jzhm')
            ->leftJoin('department as dep', 'dep.dep_id', '=', 'brry.BRKS')
            ->join(DB::raw("({$ruleSql}) as rule_info"), 'rule_info.id', '=', 'record.rule_id');

        $this->buildShizhongQualityRecordWhere($query, $request);

        $total = (clone $query)->distinct()->count('brry.BRKS');
        $rowsQuery = $query
            ->select([
                'brry.BRKS as department_id',
                'dep.dep_name as department_name',
            ])
            ->selectRaw('SUM(COALESCE(record.lock_count, 0)) AS lock_count')
            ->groupBy('brry.BRKS', 'dep.dep_name')
            ->orderBy('lock_count', 'desc')
            ->orderBy('brry.BRKS', 'asc');
        $rows = $isExport === 1 ? $rowsQuery->get() : $rowsQuery->forPage($page, $pageSize)->get();

        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'department_id' => $row->department_id ?: '',
                'department_name' => $row->department_name ?: ($row->department_id ?: ''),
                'lock_count' => (int)$row->lock_count,
            ];
        }

        if ($isExport === 1) {
            return $this->exportShizhongQualityDepartmentStatistics($list);
        }

        return ToolsService::returnData(200, ['count' => $total, 'list' => $list]);
    }

    /**
     * 事中质控规则解锁申请
     * /api/case-quality/shizhong_quality_rule_unlock
     * @param Request $request
     * @return array
     */
    public function shizhongQualityRuleUnlock(Request $request)
    {
        $zyh = trim((string)$request->post('zyh', $request->post('ZYH', '')));
        if ($zyh === '') {
            return ToolsService::returnData(4001, [], '住院号不能为空');
        }

        $ruleId = $request->post('rule_id', '');
        if ($ruleId === '' || $ruleId === null) {
            return ToolsService::returnData(4001, [], '规则ID不能为空');
        }
        $ruleId = (int)$ruleId;

        $unlockCount = DB::table('case_quality_shizhong_unlock_records')
            ->where('zyh', '=', $zyh)
            ->where('rule_id', '=', $ruleId)
            ->count();
        if ($unlockCount >= 3) {
            return ToolsService::returnData(4001, [], '该规则解锁次数已达三次，不可解锁');
        }

        $unlockApplicant = trim((string)$request->post('unlock_applicant', $request->post('解锁申请人', '')));
        /* if ($unlockApplicant === '') {
            return ToolsService::returnData(4001, [], '解锁申请人不能为空');
        } */

        $unlockReason = trim((string)$request->post('unlock_reason', $request->post('解锁原因', '')));
        /* if ($unlockReason === '') {
            return ToolsService::returnData(4001, [], '解锁原因不能为空');
        } */

        $applyTimestamp = time();
        $applyTime = date('Y-m-d H:i:s', $applyTimestamp);
        $expireTime = date('Y-m-d H:i:s', strtotime('+1 hour', $applyTimestamp));

        $insertData = [
            'zyh' => $zyh,
            'rule_id' => $ruleId,
            'unlock_applicant' => $unlockApplicant,
            'unlock_reason' => $unlockReason,
            'apply_time' => $applyTime,
            'expire_time' => $expireTime,
        ];

        $id = DB::table('case_quality_shizhong_unlock_records')->insertGetId($insertData);
        $insertData['id'] = $id;

        return ToolsService::returnData(200, $insertData, '解锁申请提交成功');
    }

    /**
     * 事中质控规则解锁记录列表
     * /api/case-quality/shizhong_quality_unlock_records
     * @param Request $request
     * @return array
     */
    public function shizhongQualityUnlockRecords(Request $request)
    {
        $page = (int)$request->post('page', 1);
        $pageSize = (int)$request->post('page_size', 10);
        $islb = $request->post('is_lb', 0);
        $page = $page > 0 ? $page : 1;
        $pageSize = $pageSize > 0 ? $pageSize : 10;

        $ruleSql = "
            SELECT
                id,
                notice AS rule_name,
                level AS rule_level,
                category AS category,
                score AS rule_score
            FROM case_rule
            UNION ALL
            SELECT
                id + 1000000 AS id,
                description AS rule_name,
                error_level AS rule_level,
                case_type AS category,
                score AS rule_score
            FROM rule_setting
        ";

        $query = DB::table('case_quality_shizhong_unlock_records as unlock_record')
            ->leftJoin('ZY_BRRY as brry', 'brry.ZYH', '=', 'unlock_record.zyh')
            ->leftJoin('department as dep', 'dep.dep_id', '=', 'brry.BRKS')
            ->leftJoin(DB::raw("({$ruleSql}) as rule_info"), 'rule_info.id', '=', 'unlock_record.rule_id');

        $unlockStartTime = $request->post('unlock_start_time', $request->post('unlock_time_start', ''));
        if ($unlockStartTime !== '' && $unlockStartTime !== null) {
            $query->where('unlock_record.apply_time', '>=', date('Y-m-d 00:00:00', strtotime($unlockStartTime)));
        }

        if ($islb == 1) {
            $query->where('unlock_record.audit_status', '=', 0);
        }
        
        $unlockEndTime = $request->post('unlock_end_time', $request->post('unlock_time_end', ''));
        if ($unlockEndTime !== '' && $unlockEndTime !== null) {
            $query->where('unlock_record.apply_time', '<=', date('Y-m-d 23:59:59', strtotime($unlockEndTime)));
        }

        $admissionStartTime = $request->post('admission_start_time', $request->post('admission_start_date', ''));
        if ($admissionStartTime !== '' && $admissionStartTime !== null) {
            $query->where('brry.AAB01', '>=', date('Y-m-d 00:00:00', strtotime($admissionStartTime)));
        }

        $admissionEndTime = $request->post('admission_end_time', $request->post('admission_end_date', ''));
        if ($admissionEndTime !== '' && $admissionEndTime !== null) {
            $query->where('brry.AAB01', '<=', date('Y-m-d 23:59:59', strtotime($admissionEndTime)));
        }

        $dischargeStartTime = $request->post('discharge_start_time', $request->post('discharge_start_date', ''));
        if ($dischargeStartTime !== '' && $dischargeStartTime !== null) {
            $query->where('brry.AAC01', '>=', date('Y-m-d 00:00:00', strtotime($dischargeStartTime)));
        }

        $dischargeEndTime = $request->post('discharge_end_time', $request->post('discharge_end_date', ''));
        if ($dischargeEndTime !== '' && $dischargeEndTime !== null) {
            $query->where('brry.AAC01', '<=', date('Y-m-d 23:59:59', strtotime($dischargeEndTime)));
        }

        $medicalRecordNo = $request->post('AAA28', $request->post('medical_record_no', ''));
        if ($medicalRecordNo !== '' && $medicalRecordNo !== null) {
            $query->where('brry.AAA28', '=', $medicalRecordNo);
        }

        $zyh = $request->post('zyh', $request->post('ZYH', ''));
        if ($zyh !== '' && $zyh !== null) {
            $query->where('unlock_record.zyh', '=', $zyh);
        }

        $department = $request->post('discharge_department', $request->post('department', $request->post('BRKS', [])));
        if (!empty($department)) {
            $department = is_array($department) ? $department : explode(',', (string)$department);
            $department = array_map(function ($depId) {
                return trim((string)$depId);
            }, $department);
            $department = array_values(array_filter($department, function ($depId) {
                return $depId !== '' && $depId !== null;
            }));
            if (!empty($department)) {
                $query->whereIn('brry.BRKS', $department);
            }
        }

        $auditStatus = $request->post('audit_status', '');
        if ($auditStatus !== '' && $auditStatus !== null) {
            $query->where('unlock_record.audit_status', '=', (int)$auditStatus);
        }

        $isInHospital = $request->post('is_in_hospital', $request->post('in_hospital', ''));
        if ($isInHospital !== '' && $isInHospital !== null) {
            if ((string)$isInHospital === '1' || $isInHospital === '是') {
                $query->where(function ($where) {
                    $where->whereNull('brry.AAC01')
                        ->orWhere('brry.AAC01', '=', '')
                        ->orWhere('brry.AAC01', '=', '0000-00-00 00:00:00');
                });
            } else if ((string)$isInHospital === '0' || $isInHospital === '否') {
                $query->whereNotNull('brry.AAC01')
                    ->where('brry.AAC01', '!=', '')
                    ->where('brry.AAC01', '!=', '0000-00-00 00:00:00');
            }
        }

        $total = (clone $query)->count('unlock_record.id');
        // 当 zyh 与 is_lb 同时不为空时，返回全部数据，不做分页
        $disablePaging = ($zyh !== '' && $zyh !== null) && !empty($islb);
        $rows = $query
            ->select([
                'unlock_record.id',
                'unlock_record.zyh',
                'unlock_record.unlock_applicant',
                'unlock_record.unlock_reason',
                'unlock_record.apply_time',
                'unlock_record.rule_id',
                'unlock_record.audit_status',
                'unlock_record.auditor',
                'unlock_record.audit_time',
                'unlock_record.audit_reason',
                'brry.BRKS as department_id',
                'brry.AAA28 as medical_record_no',
                'dep.dep_name as department_name',
                'brry.BRXM as patient_name',
                'brry.CH as bed_no',
                'brry.AAB01 as admission_time',
                'brry.AAC01 as discharge_time',
                'rule_info.rule_name',
                'rule_info.rule_level',
                'rule_info.category',
                'rule_info.rule_score',
            ])
            ->orderBy('unlock_record.apply_time', 'desc')
            ->orderBy('unlock_record.id', 'desc')
            ->when(!$disablePaging, function ($query) use ($page, $pageSize) {
                return $query->forPage($page, $pageSize);
            })
            ->get();

        $list = [];
        foreach ($rows as $row) {
            $auditStatus = (int)$row->audit_status;
            $basis = CaseQuality::query()->where('JZHM', '=', $row->zyh)->where('rule_id', '=', $row->rule_id)->value('basis');
            $list[] = [
                'id' => $row->id,
                'rule_id' => $row->rule_id,
                'zyh' => $row->zyh ?: '',
                'basis' => $basis ?: '',
                'category' => $row->category ?: '',
                'rule_score' => $row->rule_score ?: '',
                'medical_record_no' => $row->medical_record_no ?: '',
                'department' => $row->department_name ?: ($row->department_id ?: ''),
                'patient_name' => $row->patient_name ?: '',
                'bed_no' => $row->bed_no ?: '',
                'audit_status' => $auditStatus,
                'audit_status_text' => $this->getShizhongUnlockAuditStatusText($auditStatus),
                'unlock_applicant' => $row->unlock_applicant ?: '',
                'unlock_reason' => $row->unlock_reason ?: '',
                'unlock_time' => $this->formatShizhongQualityTime($row->apply_time),
                'rule_name' => $row->rule_name ?: '',
                'auditor' => $row->auditor ?: '',
                'audit_time' => $this->formatShizhongQualityTime($row->audit_time),
                'audit_reason' => $row->audit_reason ?: '',
                'rule_type' => ((int)$row->rule_level === 1) ? '强制' : '建议',
                'admission_time' => $this->formatShizhongQualityTime($row->admission_time),
                'discharge_time' => $this->formatShizhongQualityTime($row->discharge_time),
            ];
        }

        return ToolsService::returnData(200, ['count' => $total, 'list' => $list]);
    }

    /**
     * 事中质控规则解锁审核
     * /api/case-quality/shizhong_quality_unlock_audit
     * @param Request $request
     * @return array
     */
    public function shizhongQualityUnlockAudit(Request $request)
    {
        $id = (int)$request->post('id', 0);
        if ($id <= 0) {
            return ToolsService::returnData(4001, [], '解锁记录ID不能为空');
        }

        $auditStatus = $request->post('status', $request->post('audit_status', ''));
        if (!in_array((string)$auditStatus, ['1', '2'], true)) {
            return ToolsService::returnData(4001, [], '审核状态只能为通过或驳回');
        }
        $auditStatus = (int)$auditStatus;

        $auditReason = trim((string)$request->post('reason', $request->post('audit_reason', '')));
        $auditor = trim((string)$request->post('auditor', $request->post('审核人', '')));
        if($auditor === '' || empty($auditor)){
            $auditor = $this->getCurrentUserRealName($request);
            if ($auditor === '') {
                return ToolsService::returnData(4001, [], '未获取到当前登录人');
            }
        }

        $record = DB::table('case_quality_shizhong_unlock_records')->where('id', '=', $id)->first();
        if (empty($record)) {
            return ToolsService::returnData(4001, [], '解锁记录不存在');
        }

        $auditTime = date('Y-m-d H:i:s');
        DB::table('case_quality_shizhong_unlock_records')
            ->where('id', '=', $id)
            ->update([
                'audit_status' => $auditStatus,
                'auditor' => $auditor,
                'audit_time' => $auditTime,
                'audit_reason' => $auditReason,
            ]);

        return ToolsService::returnData(200, [
            'id' => $id,
            'audit_status' => $auditStatus,
            'audit_status_text' => $this->getShizhongUnlockAuditStatusText($auditStatus),
            'auditor' => $auditor,
            'audit_time' => $auditTime,
            'audit_reason' => $auditReason,
        ], '审核成功');
    }

    /**
     * 获取解锁审核状态文本
     *
     * @param int $auditStatus
     * @return string
     */
    private function getShizhongUnlockAuditStatusText($auditStatus)
    {
        $map = [
            0 => '审核中',
            1 => '通过',
            2 => '驳回',
        ];

        return $map[(int)$auditStatus] ?? '未知';
    }

    /**
     * 获取当前登录人姓名
     *
     * @param Request $request
     * @return string
     */
    private function getCurrentUserRealName(Request $request)
    {
        $token = $request->header('token', '');
        if ($token === '') {
            return '';
        }

        $user = User::query()->where('token', '=', $token)->first();
        if (empty($user)) {
            return '';
        }

        return $user->realname ?: ($user->name ?: '');
    }

    /**
     * 导出事中质控记录列表
     *
     * @param array $list
     * @return mixed
     */
    private function exportShizhongQualityRecords(array $list)
    {
        foreach ($list as &$item) {
            if (!empty($item['medical_record_no'])) {
                // 避免 Excel 将长数字病案号展示为科学计数法。
                $item['medical_record_no'] = "\t" . (string)$item['medical_record_no'];
            }
        }
        unset($item);

        $title = [[
            'rule_name' => '规则名称',
            'department' => '所属科室',
            'lock_count' => '锁定次数',
            'correction_status' => '整改状态',
            'resident_doctor' => '住院医师',
            'medical_record_no' => '病案号',
            'patient_name' => '患者姓名',
            'bed_no' => '床号',
            'admission_time' => '入院时间',
            'discharge_time' => '出院时间',
            'rule_type' => '问题级别',
            'rule_nature' => '规则类型',
            'document_type' => '文书类型',
            'appeal_status' => '申诉状态',
            'last_quality_time' => '末次质控时间',
            'is_discharge' => '是否出院',
        ]];

        $fileName = '事中质控记录_' . date('YmdHis') . '.xlsx';

        return Excel::download(new DataExport($title, $list), $fileName);
    }

    /**
     * 导出事中质控按规则统计
     *
     * @param array $list
     * @return mixed
     */
    private function exportShizhongQualityRuleStatistics(array $list)
    {
        $title = [[
            'rule_name' => '规则名称',
            'rule_nature' => '规则类型',
            'rule_type' => '问题级别',
            'problem_count' => '问题数量',
            'lock_count' => '锁定次数',
            'corrected_count' => '已整改数',
            'uncorrected_count' => '未整改数',
        ]];

        $fileName = '事中质控按规则统计_' . date('YmdHis') . '.xlsx';

        return Excel::download(new DataExport($title, $list), $fileName);
    }

    /**
     * 导出事中质控按科室汇总
     *
     * @param array $list
     * @return mixed
     */
    private function exportShizhongQualityDepartmentStatistics(array $list)
    {
        $title = [[
            'department_name' => '科室名称',
            'lock_count' => '锁定次数',
        ]];

        $fileName = '事中质控按科室汇总_' . date('YmdHis') . '.xlsx';

        return Excel::download(new DataExport($title, $list), $fileName);
    }

    /**
     * 组装事中质控记录列表查询条件
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param Request $request
     * @return void
     */
    private function buildShizhongQualityRecordWhere($query, Request $request)
    {
        $jzhm = $request->post('jzhm', $request->post('ZYH', ''));
        if ($jzhm !== '' && $jzhm !== null) {
            $query->where('record.jzhm', '=', $jzhm);
        }

        $ruleId = $request->post('rule_id', '');
        if ($ruleId !== '' && $ruleId !== null) {
            $ruleIds = is_array($ruleId) ? $ruleId : explode(',', (string)$ruleId);
            $ruleIds = array_map(function ($id) {
                return trim((string)$id);
            }, $ruleIds);
            $ruleIds = array_values(array_filter($ruleIds, function ($id) {
                return $id !== '' && $id !== null;
            }));

            if (count($ruleIds) > 1) {
                $query->whereIn('record.rule_id', $ruleIds);
            } else if (!empty($ruleIds)) {
                $query->where('record.rule_id', '=', $ruleIds[0]);
            }
        }

        $ruleName = $request->post('rule_name', '');
        if ($ruleName !== '' && $ruleName !== null) {
            $query->where('rule_info.rule_name', 'like', '%' . $ruleName . '%');
        }

        $ruleType = $request->post('rule_type', '');
        if ($ruleType !== '' && $ruleType !== null) {
            if ((string)$ruleType === '1' || $ruleType === '强制') {
                $query->where('rule_info.rule_level', '=', 1);
            } else if ((string)$ruleType === '2' || $ruleType === '建议') {
                $query->where(function ($where) {
                    $where->where('rule_info.rule_level', '!=', 1)
                        ->orWhereNull('rule_info.rule_level');
                });
            }
        }

        $ruleNature = $request->post('rule_nature', $request->post('type', $request->post('规则性质', '')));
        if ($ruleNature !== '' && $ruleNature !== null) {
            $ruleNatures = is_array($ruleNature) ? $ruleNature : explode(',', (string)$ruleNature);
            $ruleNatures = array_map(function ($nature) {
                return trim((string)$nature);
            }, $ruleNatures);
            $ruleNatures = array_values(array_filter($ruleNatures, function ($nature) {
                return $nature !== '' && $nature !== null;
            }));

            if (count($ruleNatures) > 1) {
                $query->whereIn('rule_info.rule_nature', $ruleNatures);
            } else if (!empty($ruleNatures)) {
                $query->where('rule_info.rule_nature', '=', $ruleNatures[0]);
            }
        }

        $documentType = $request->post('document_type', '');
        if ($documentType !== '' && $documentType !== null) {
            $query->where('rule_info.document_type', '=', $documentType);
        }

        $medicalRecordNo = $request->post('AAA28', $request->post('medical_record_no', ''));
        if ($medicalRecordNo !== '' && $medicalRecordNo !== null) {
            $query->where(function ($where) use ($medicalRecordNo) {
                $where->where('brry.AAA28', '=', $medicalRecordNo)
                    ->orWhere('record.medical_record_no', '=', $medicalRecordNo);
            });
        }

        $patientName = $request->post('patient_name', '');
        if ($patientName !== '' && $patientName !== null) {
            $query->where('record.patient_name', 'like', '%' . $patientName . '%');
        }

        $residentDoctor = $request->post('resident_doctor', '');
        if ($residentDoctor !== '' && $residentDoctor !== null) {
            $query->where('record.resident_doctor', 'like', '%' . $residentDoctor . '%');
        }

        $department = $request->post('department', $request->post('BRKS', []));
        if (!empty($department)) {
            $department = is_array($department) ? $department : explode(',', (string)$department);
            $department = array_map(function ($depId) {
                return trim((string)$depId);
            }, $department);
            $department = array_values(array_filter($department, function ($depId) {
                return $depId !== '' && $depId !== null;
            }));
            if (!empty($department)) {
                $query->whereIn('brry.BRKS', $department);
            }
        }

        $admissionStartTime = $request->post('admission_start_time', '');
        if ($admissionStartTime !== '' && $admissionStartTime !== null) {
            $query->where('brry.AAB01', '>=', date('Y-m-d 00:00:00', strtotime($admissionStartTime)));
        }

        $admissionEndTime = $request->post('admission_end_time', '');
        if ($admissionEndTime !== '' && $admissionEndTime !== null) {
            $query->where('brry.AAB01', '<=', date('Y-m-d 23:59:59', strtotime($admissionEndTime)));
        }

        $dischargeStartTime = $request->post('discharge_start_time', '');
        if ($dischargeStartTime !== '' && $dischargeStartTime !== null) {
            $query->where('brry.AAC01', '>=', date('Y-m-d 00:00:00', strtotime($dischargeStartTime)));
        }

        $dischargeEndTime = $request->post('discharge_end_time', '');
        if ($dischargeEndTime !== '' && $dischargeEndTime !== null) {
            $query->where('brry.AAC01', '<=', date('Y-m-d 23:59:59', strtotime($dischargeEndTime)));
        }

        $startTime = $request->post('start_time', '');
        if ($startTime !== '' && $startTime !== null) {
            $query->where('record.last_quality_time', '>=', date('Y-m-d 00:00:00', strtotime($startTime)));
        }

        $endTime = $request->post('end_time', '');
        if ($endTime !== '' && $endTime !== null) {
            $query->where('record.last_quality_time', '<=', date('Y-m-d 23:59:59', strtotime($endTime)));
        }

        $correctionStatus = $request->post('correction_status', '');
        if ($correctionStatus !== '' && $correctionStatus !== null) {
            if ((string)$correctionStatus === '0' || $correctionStatus === '未整改') {
                $query->whereNotNull('uncorrected_quality.id');
            } else if ((string)$correctionStatus === '1' || $correctionStatus === '已整改') {
                $query->whereNull('uncorrected_quality.id');
            }
        }

        $appealStatus = $request->post('appeal_status', '');
        if ($appealStatus !== '' && $appealStatus !== null) {
            $latestAppealStatusSql = $this->getShizhongLatestAppealStatusSql();
            if ($appealStatus === '未申诉') {
                $query->whereRaw($latestAppealStatusSql . ' IS NULL');
            } else {
                $appealStatusCode = $this->normalizeShizhongAppealStatusCode($appealStatus);
                $query->whereRaw($latestAppealStatusSql . ' = ?', [$appealStatusCode]);
            }
        }

        $isDischarge = $request->post('is_discharge', '');
        if ($isDischarge !== '' && $isDischarge !== null) {
            if ((string)$isDischarge === '1' || $isDischarge === '是') {
                $query->whereNotNull('brry.AAC01')->where('brry.AAC01', '!=', '');//不为null且不为''
            } else {
                $query->where(function ($where) {
                    $where->whereNull('brry.AAC01')
                        ->orWhere('brry.AAC01', '=', '');
                });//null或''
            }
        }
    }

    /**
     * 组装事中质控记录列表排序
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param Request $request
     * @return \Illuminate\Database\Query\Builder
     */
    private function applyShizhongQualityRecordOrder($query, Request $request)
    {
        $query->orderBy('required_uncorrected_sort', 'asc')
            ->orderBy('uncorrected_sort', 'asc')
            ->orderBy('discharge_sort', 'asc');

        $orderKey = $this->getShizhongQualityOrderValue($request, ['order_key', 'sort_field', 'order_field', 'order_by', 'sort']);
        $orderDirection = $this->getShizhongQualityOrderValue($request, ['order_type', 'sort_order', 'order_direction', 'direction', 'order'], 'asc');
        $orderDirection = $this->normalizeShizhongQualityOrderDirection($orderDirection);

        $orderMap = [
            'rule_name' => ['type' => 'column', 'value' => 'rule_info.rule_name'],
            '规则名称' => ['type' => 'column', 'value' => 'rule_info.rule_name'],
            'department' => ['type' => 'raw', 'value' => "COALESCE(NULLIF(dep.dep_name, ''), NULLIF(brry.BRKS, ''), record.department)"],
            '所属科室' => ['type' => 'raw', 'value' => "COALESCE(NULLIF(dep.dep_name, ''), NULLIF(brry.BRKS, ''), record.department)"],
            'lock_count' => ['type' => 'column', 'value' => 'record.lock_count'],
            '锁定次数' => ['type' => 'column', 'value' => 'record.lock_count'],
            'admission_time' => ['type' => 'column', 'value' => 'brry.AAB01'],
            '入院时间' => ['type' => 'column', 'value' => 'brry.AAB01'],
            'discharge_time' => ['type' => 'column', 'value' => 'brry.AAC01'],
            '出院时间' => ['type' => 'column', 'value' => 'brry.AAC01'],
            'document_type' => ['type' => 'column', 'value' => 'rule_info.document_type'],
            '文书类型' => ['type' => 'column', 'value' => 'rule_info.document_type'],
            'appeal_status' => ['type' => 'raw', 'value' => $this->getShizhongAppealStatusOrderSql()],
            '申诉状态' => ['type' => 'raw', 'value' => $this->getShizhongAppealStatusOrderSql()],
        ];

        if (isset($orderMap[$orderKey])) {
            if ($orderMap[$orderKey]['type'] === 'raw') {
                $query->orderByRaw($orderMap[$orderKey]['value'] . ' ' . strtoupper($orderDirection));
            } else {
                $query->orderBy($orderMap[$orderKey]['value'], $orderDirection);
            }
        }

        return $query->orderBy('record.last_quality_time', 'desc')
            ->orderBy('record.id', 'desc');
    }

    /**
     * 获取事中质控记录排序参数
     *
     * @param Request $request
     * @param array $keys
     * @param string $default
     * @return string
     */
    private function getShizhongQualityOrderValue(Request $request, array $keys, $default = '')
    {
        foreach ($keys as $key) {
            $value = $request->post($key, null);
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $value = reset($value);
            }

            return trim((string)$value);
        }

        return $default;
    }

    /**
     * 规范化事中质控记录排序方向
     *
     * @param string $direction
     * @return string
     */
    private function normalizeShizhongQualityOrderDirection($direction)
    {
        $direction = strtolower(trim((string)$direction));
        $descValues = ['desc', 'descending', 'descend', '倒序', '降序', '-1'];

        return in_array($direction, $descValues, true) ? 'desc' : 'asc';
    }

    /**
     * 获取当前页事中质控记录的最新申诉状态。
     *
     * @param \Illuminate\Support\Collection $rows
     * @return array
     */
    private function getShizhongLatestAppealStatusMap($rows)
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $pairs = [];
        foreach ($rows as $row) {
            $key = $this->getShizhongQualityRecordKey($row->jzhm, $row->rule_id);
            $pairs[$key] = [
                'jzhm' => $row->jzhm,
                'rule_id' => (int)$row->rule_id,
            ];
        }

        $statusMap = [];
        foreach (array_chunk(array_values($pairs), 200) as $chunk) {
            $appeals = DB::table('appeal')
                ->select(['ZYH', 'error_id', 'status'])
                ->where('quality_type', '=', 2)
                ->where('type', '=', 2)
                ->where(function ($query) use ($chunk) {
                    foreach ($chunk as $pair) {
                        $query->orWhere(function ($where) use ($pair) {
                            $where->where('ZYH', '=', $pair['jzhm'])
                                ->where('error_id', '=', $pair['rule_id']);
                        });
                    }
                })
                ->orderBy('id', 'desc')
                ->get();

            foreach ($appeals as $appeal) {
                $key = $this->getShizhongQualityRecordKey($appeal->ZYH, $appeal->error_id);
                if (!array_key_exists($key, $statusMap)) {
                    $statusMap[$key] = $appeal->status;
                }
            }
        }

        return $statusMap;
    }

    /**
     * 获取事中质控记录的唯一键。
     *
     * @param mixed $jzhm
     * @param mixed $ruleId
     * @return string
     */
    private function getShizhongQualityRecordKey($jzhm, $ruleId)
    {
        return (string)$jzhm . '|' . (int)$ruleId;
    }

    /**
     * 获取最新申诉状态子查询 SQL。
     *
     * @return string
     */
    private function getShizhongLatestAppealStatusSql()
    {
        return "(
            SELECT appeal_status.status
            FROM appeal AS appeal_status
            WHERE appeal_status.quality_type = 2
                AND appeal_status.type = 2
                AND appeal_status.ZYH = record.jzhm
                AND appeal_status.error_id = record.rule_id
            ORDER BY appeal_status.id DESC
            LIMIT 1
        )";
    }

    /**
     * 获取申诉状态排序表达式。
     *
     * @return string
     */
    private function getShizhongAppealStatusOrderSql()
    {
        $latestAppealStatusSql = $this->getShizhongLatestAppealStatusSql();

        return "CASE
            WHEN {$latestAppealStatusSql} IS NULL THEN 0
            WHEN {$latestAppealStatusSql} = 0 THEN 1
            WHEN {$latestAppealStatusSql} = 1 THEN 2
            WHEN {$latestAppealStatusSql} = 2 THEN 3
            WHEN {$latestAppealStatusSql} = 3 THEN 4
            ELSE 5
        END";
    }

    /**
     * 规范化申诉状态查询值。
     *
     * @param mixed $status
     * @return int
     */
    private function normalizeShizhongAppealStatusCode($status)
    {
        if (is_numeric($status)) {
            return (int)$status;
        }

        $map = [
            '申诉中' => 0,
            '申诉通过' => 1,
            '申诉驳回' => 2,
            '已整改' => 3,
        ];

        return $map[$status] ?? -1;
    }

    /**
     * 获取整改状态
     *
     * @param object $row
     * @return string
     */
    private function getShizhongCorrectionStatus($row)
    {
        if (!empty($row->has_uncorrected_quality)) {
            return '未整改';
        }

        return '已整改';
    }

    /**
     * 获取申诉状态
     *
     * @param mixed $status
     * @return string
     */
    private function getShizhongAppealStatus($status)
    {
        if ($status === null) {
            return '未申诉';
        }

        $map = [
            0 => '申诉中',
            1 => '申诉通过',
            2 => '申诉驳回',
            3 => '已整改',
        ];

        return $map[(int)$status] ?? '未申诉';
    }

    /**
     * 格式化事中质控记录时间
     *
     * @param mixed $time
     * @return string
     */
    private function formatShizhongQualityTime($time)
    {
        if (empty($time) || $time === '0000-00-00 00:00:00') {
            return '';
        }

        return date('Y-m-d H:i:s', strtotime($time));
    }

    /**
     * @param Request $request
     * @return array
     * 病历等级接口
     */
    public function medicalRecordLevel(Request $request)
    {
        $queryCond = $this->commonQueryCond($request);
        $data = ['list' => [], 'count' => 0];

        $piService = new ElasticsearchService('patient_info');
        $must = [
            [
                'range' => [
                    "AAC01" => [
                        'gt' => $queryCond->start_time . ' 00:00:00',
                        'lt' => $queryCond->end_time . ' 23:59:59',
                    ]
                ]
            ]
        ];
        $must[1] = ['range' => ["score" => ['gte' => 90]]];
        $params = $piService->clearMust()->queryByMustBatch($must)->paginate(1, 0)->getParams();
        $mzRes = app('es')->search($params);
        $mzRes = $piService->getDataByEs($mzRes);
        $levelA = $mzRes[1] ?? 0;

        $must[1] = ['range' => ["score" => ['gte' => 80, 'lt' => 90]]];
        $params = $piService->clearMust()->queryByMustBatch($must)->paginate(1, 0)->getParams();
        $mzRes = app('es')->search($params);
        $mzRes = $piService->getDataByEs($mzRes);
        $levelB = $mzRes[1] ?? 0;

        $must[1] = ['range' => ["score" => ['lt' => 80]]];
        $params = $piService->clearMust()->queryByMustBatch($must)->paginate(1, 0)->getParams();
        $mzRes = app('es')->search($params);
        $mzRes = $piService->getDataByEs($mzRes);
        $levelC = $mzRes[1] ?? 0;

        $data['levelA'] = $levelA;
        $data['levelB'] = $levelB;
        $data['levelC'] = $levelC;

        return ToolsService::jsonSuccess($data);
    }

    public function commonQueryCond(Request $request)
    {
        $start_time = $request->post("start_time"); //日期字符串格式
        $end_time = $request->post("end_time");
        $debugis = $request->post("debugis", 0);
        $depList = $request->post("dep_id", 0);
        $status = $request->post("status", 0);
        if (!empty($start_time)) {
            $start_time = date('Y-m-d', strtotime($start_time));
        }
        if (!empty($end_time)) {
            $end_time = date('Y-m-d H:i:s', strtotime($end_time . ' 23:59:59'));
        }

        $depIds = UserService::getCurrentUserDep($request);
        $obj = new \stdClass();

        $obj->start_time = $start_time;
        $obj->end_time = $end_time;
        $obj->debugis = $debugis;
        $obj->dep_ids = $depIds;
        // 增加病人科室搜索条件
        $obj->brks = $depList;
        $obj->status = $status;
        $obj->AAA28 = $request->post("AAA28", "");
        $obj->is_defect = $request->post("is_defect", "");

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
