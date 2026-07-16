<?php

/**
 * 终末病历质控
 */

namespace App\Http\Controllers\Api\CaseHistory\Terminal;

use App\Model\CaseRule;
use App\Model\Department;
use App\Model\CaseQuality;
use App\Model\PatientInfo;
use App\Model\RuleSetting;
use App\Services\CsvService;
use Illuminate\Http\Request;
use App\Services\UserService;
use App\Services\ToolsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Controllers\Api\ApiController;


class IndexController extends ApiController
{
    public function __construct()
    {
        parent::__construct();
    }


    /**
     * 获取顶部搜索options
     * @param Request $request
     * @return array
     */
    public function getSearchOptions(Request $request)
    {
        $yqArray = Department::getDepartmentOptions();
        $ksArray = Department::getDepartmentOptions(2);
        $bqArray = Department::getDepartmentOptions(3);
        $data = [];
        $data['yqArray'] = $yqArray;
        $data['ksArray'] = $ksArray;
        $data['bqArray'] = $bqArray;
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



    //region 科室排名===
    /**
     * 科室排名列表
     * @param Request $request
     * @return void
     */
    public function getDepartmentTableList(Request $request)
    {
        $depIds = UserService::getCurrentUserDep($request);
        if (empty($depIds)) {
            return ToolsService::jsonSuccess(['count' => 0, 'data' => [], 'chartData' => []]);
        }
        //region 处理搜索条件===
        $model = PatientInfo::query()->where("in_hospital", 2)
            ->join("ZY_BRRY", 'ZY_BRRY.ZYH', '=', 'patient_info.MED_REC_ID')
            ->when(is_array($depIds), function ($query) use ($depIds) {
                return $query->whereIn('AAC02C', $depIds);
            });
        $model = $this->getTopSearch($request->post(), $model, 'patient_info');
        //排名类型
        $type_id = $request->post('rank_type', 1);
        $depTable = Department::query()->where('type_id', $type_id)->get(['id', 'dep_id'])->toArray();
        $codeArray = [];
        foreach ($depTable as $v) {
            $codeArray[] = $v['dep_id'];
        }
        $filed = "";
        switch ($type_id) {
            case 1: //院区
                $filed = 'patient_info.YQ_CODE';
                $model->whereIn($filed, $codeArray)
                    ->groupBy($filed);
                break;
            case 2: //科室
                $filed = 'ZY_BRRY.BRKS';
                $model->whereIn($filed, $codeArray)
                    ->groupBy($filed);
                break;
            case 3: //病区
                $filed = 'patient_info.BQ_CODE';
                $model->whereIn($filed, $codeArray)
                    ->groupBy($filed);
                break;
        }

        //排序
        $rank_order = $request->post('rank_order', 1);
        $order = $rank_order === 2 ? 'asc' : 'desc'; //2代表升序，1代表降序


        //分页
        $page = $request->post('page', 1);
        $pageSize = $request->post('size', 10);
        //endregion

        $count = count($model->get()->toArray());
        $pageStart = ($page - 1) * $pageSize;
        $tableList = $model
            ->select([
                'patient_info.id',
                "{$filed} as code",
                DB::raw('COUNT(patient_info.id) as bl_num'), //病例总数
                DB::raw('SUM(CASE WHEN patient_info.zm_score_lv = "甲" THEN 1 ELSE 0 END) as jia_num'), //甲级病例总数
                DB::raw('SUM(CASE WHEN patient_info.zm_score_lv = "乙" THEN 1 ELSE 0 END) as yi_num'), //乙级病例总数
                DB::raw('SUM(CASE WHEN patient_info.zm_score_lv = "丙" THEN 1 ELSE 0 END) as bing_num'), //丙级病例总数
                DB::raw('SUM(CASE WHEN patient_info.zm_score < 100 THEN 1 ELSE 0 END) as qx_num'), // 缺陷病例总数
                DB::raw('ROUND(CASE WHEN COUNT(patient_info.id) = 0 THEN 0 ELSE SUM(CASE WHEN patient_info.zm_score < 100 THEN 1 ELSE 0 END) / COUNT(patient_info.id) END,4) as qx_ratio'), // 缺陷病例比率
            ])
            ->orderBy('qx_ratio', $order)
            ->offset($pageStart)->limit($pageSize)->get()->toArray();

        //获取所有dep
        $depAllTable = Department::query()->pluck('dep_name', 'dep_id')->toArray();
        $chartData = []; //图表数据
        foreach ($tableList as $k => $v) {
            if ($rank_order == 2) {
                $v['rank'] = $count - $pageStart - $k;
            } else {
                $v['rank'] = $pageStart + $k + 1;
            }
            $v['dep_name'] = $depAllTable[$v['code']] ?? '';
            $v['qx_ratio'] = ($v['qx_ratio'] * 100) . '%';
            //处理图表数据
            $chartData['name'][$k] = $v['dep_name'];
            $chartData['bl_num'][$k] = $v['bl_num'];
            $chartData['qx_num'][$k] = $v['qx_num'];
            $tableList[$k] = $v;
        }


        //处理图表
        foreach ($chartData as $k => $v) {
            $chartData[$k] = array_reverse($v);
        }
        $data = [];
        $data['total'] = $count;
        $data['data'] = $tableList;
        $data['chartData'] = $chartData;

        return ToolsService::returnData(200, $data);
    }

    /**
     * 科室排名导出
     */
    public function getDepartmentRankExport(Request $request)
    {
        //region 处理搜索条件===
        $model = PatientInfo::query();
        $model = $this->getTopSearch($request->post(), $model);

        //排名类型
        $type_id = $request->post('rank_type', 1);
        $depTable = Department::query()->where('type_id', $type_id)->get(['id', 'dep_id'])->toArray();
        $codeArray = [];
        foreach ($depTable as $v) {
            $codeArray[] = $v['dep_id'];
        }
        $filed = "";
        $filedName = "";
        switch ($type_id) {
            case 1: //院区
                $filed = 'YQ_CODE';
                $filedName = "出院院区名称";
                $model->whereIn($filed, $codeArray)
                    ->groupBy($filed);
                break;
            case 2: //科室
                $filed = 'AAC02C';
                $filedName = "出院科室名称";
                $model->whereIn($filed, $codeArray)
                    ->groupBy($filed);
                break;
            case 3: //病区
                $filed = 'BQ_CODE';
                $filedName = "出院病区名称";
                $model->whereIn($filed, $codeArray)
                    ->groupBy($filed);
                break;
        }

        //排序
        $rank_order = $request->post('rank_order', 1);
        $order = $rank_order === 2 ? 'asc' : 'desc'; //2代表升序，1代表降序

        $tableList = $model
            ->select([
                'id',
                "{$filed}",
                DB::raw('COUNT(id) as bl_num'), //病例总数
                DB::raw('SUM(CASE WHEN patient_info.zm_score_lv = "甲" THEN 1 ELSE 0 END) as jia_num'), //甲级病例总数
                DB::raw('SUM(CASE WHEN patient_info.zm_score_lv = "乙" THEN 1 ELSE 0 END) as yi_num'), //乙级病例总数
                DB::raw('SUM(CASE WHEN patient_info.zm_score_lv = "丙" THEN 1 ELSE 0 END) as bing_num'), //丙级病例总数
                DB::raw('SUM(CASE WHEN zm_score < 100 THEN 1 ELSE 0 END) as qx_num'), // 缺陷病例总数
                DB::raw('ROUND(CASE WHEN COUNT(id) = 0 THEN 0 ELSE SUM(CASE WHEN zm_score < 100 THEN 1 ELSE 0 END) / COUNT(id) END,2) as qx_ratio'), // 缺陷病例比率
            ])
            ->orderBy('qx_ratio', $order)
            ->offset(0)->limit(10000)->get()->toArray();
        //获取所有dep
        $depAllTable = Department::query()->pluck('dep_name', 'dep_id')->toArray();
        $filedNew = ['rank', 'dep_name', 'bl_num', 'qx_num', 'qx_ratio', 'jia_num', 'yi_num', 'bing_num'];
        foreach ($tableList as $k => $v) {
            $v['rank'] = $k + 1;
            $v['dep_name'] = $depAllTable[$v[$filed]] ?? '';
            $v['qx_ratio'] = $v['qx_ratio'] * 100;
            $v['qx_ratio'] = $v['qx_ratio'] . '%';
            unset($v['id'], $v[$filed]);
            $newValue = [];
            foreach ($filedNew as $vv) {
                if (isset($v[$vv])) {
                    $newValue[$vv] = $v[$vv];
                }
            }
            $tableList[$k] = $newValue;
        }
        $title = ['排名', $filedName, '质控病例', '缺陷病例', '缺陷占比', '甲级病历数', '乙级病历数', '丙级病历数'];
        array_unshift($tableList, $title);
        $csv = new CsvService();
        $csv->filename = $csv->charset('科室排名', 'UTF-8');

        return $csv->export($tableList);
    }
    //endregion


    /**
     * 统计汇总列表
     * @param Request $request
     * @return array
     */
    public function getTotalList(Request $request)
    {
        $totalStart = microtime(true);

        $depIds = UserService::getCurrentUserDep($request);
        if (empty($depIds)) {
            return ToolsService::returnData(200, ['case_total' => 0, 'case_quality_total' => 0, 'quality_proportion' => 0, 'case_calibre' => [], 'question_total' => 0]);
        }

        // ============ 步骤1：病案统计 ============
        $step1Start = microtime(true);

        $baseQuery = PatientInfo::query()
            ->join('ZY_BRRY', 'ZY_BRRY.ZYH', '=', 'patient_info.MED_REC_ID')
            ->leftJoin('patient_info_v2', function ($join) {
                $join->on('patient_info_v2.ZYH', '=', 'patient_info.MED_REC_ID')
                    ->where('patient_info_v2.status', '=', 0);
            })
            ->when(is_array($depIds), function ($query) use ($depIds) {
                return $query->whereIn('ZY_BRRY.BRKS', $depIds);
            })
            ->whereNotNull('patient_info.MED_REC_ID')
            ->where("patient_info.in_hospital", 2);

        $baseQuery = $this->getTopSearch($request->post(), $baseQuery, 'patient_info');

        // 缺陷病例按有效缺陷记录统计，确保扣 0 分但仍有缺陷的病例也能纳入。
        $case_quality_total = (clone $baseQuery)
            ->whereExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('case_quality_zm')
                    ->whereColumn('case_quality_zm.JZHM', 'patient_info.MED_REC_ID')
                    ->where('case_quality_zm.is_correction', 0)
                    ->where(function ($appealQuery) {
                        $this->applyAppealVisibleCondition($appealQuery, 'case_quality_zm');
                    })
                    ->where('case_quality_zm.is_ignore', 0);
            })
            ->count();

        // 一次查询获取所有统计
        $statsResult = $baseQuery->selectRaw('
            COUNT(*) as total,
            patient_info.zm_score_lv as score_lv,
            COUNT(*) as lv_count
        ')->groupBy('patient_info.zm_score_lv')->get();

        //Log::info('sql---', [$baseQuery->toSql(), $baseQuery->getBindings()]);
        $case_total = $statsResult->sum('total');
        $quality_proportion = $case_total != 0 ? bcmul(bcdiv((string)$case_quality_total, (string)$case_total, 4), 100, 2) : 0.00;

        $case_calibre = $statsResult->map(function ($item) {
            return [
                'num' => $item->lv_count,
                'score_lv' => $item->score_lv
            ];
        })->values();


        $step1End = microtime(true);
        /* Log::info('getTotalList - 步骤1', [
            'duration' => round(($step1End - $step1Start) * 1000, 2) . 'ms',
            'case_total' => $case_total
        ]); */

        // ============ 步骤2：问题总数（极简优化）============
        $step2Start = microtime(true);

        $searchConditions = $request->post();
        $bindings = [];
        $where = [];

        // 基础条件
        $where[] = "patient_info.in_hospital = 2";
        $where[] = "patient_info.zm_score IS NOT NULL";

        // 病案号
        if (!empty($searchConditions['AAA28'])) {
            $where[] = "patient_info.AAA28 = ?";
            $bindings[] = $searchConditions['AAA28'];
        }

        // 院区
        if (!empty($searchConditions['YQ_CODE'])) {
            $placeholders = implode(',', array_fill(0, count($searchConditions['YQ_CODE']), '?'));
            $where[] = "patient_info.YQ_CODE IN ($placeholders)";
            $bindings = array_merge($bindings, $searchConditions['YQ_CODE']);
        }

        // 科室（需要处理子科室）
        $step2aStart = microtime(true);
        if (!empty($searchConditions['KS_CODE'])) {
            $ksArray = Department::query()->whereIn('dep_id', $searchConditions['KS_CODE'])->get(['id', 'sub_ids'])->toArray();
            if (!empty($ksArray)) {
                $ksCodeArray = $searchConditions['KS_CODE'];
                foreach ($ksArray as $v) {
                    if (!empty($v['sub_ids'])) {
                        $ids = explode(',', $v['sub_ids']);
                        $ksCodeArray = array_merge($ksCodeArray, $ids);
                    }
                }
                $ksCodeArray = array_filter(array_unique($ksCodeArray));
                $placeholders = implode(',', array_fill(0, count($ksCodeArray), '?'));
                $where[] = "patient_info.AAC02C IN ($placeholders)";
                $bindings = array_merge($bindings, $ksCodeArray);
            }
        }
        $step2aEnd = microtime(true);
        /* Log::info('getTotalList - 步骤2a（查询科室）', [
            'duration' => round(($step2aEnd - $step2aStart) * 1000, 2) . 'ms'
        ]); */

        // 病区
        $step2bStart = microtime(true);
        if (!empty($searchConditions['BQ_CODE'])) {
            $bqArray = Department::query()->whereIn('dep_id', $searchConditions['BQ_CODE'])->get(['id', 'sub_ids'])->toArray();
            if (!empty($bqArray)) {
                $bqCodeArray = [];
                foreach ($bqArray as $v) {
                    $ids = explode(',', $v['sub_ids']);
                    $bqCodeArray = array_merge($bqCodeArray, $ids);
                }
                $bqCodeArray = array_unique($bqCodeArray);
                $placeholders = implode(',', array_fill(0, count($bqCodeArray), '?'));
                $where[] = "patient_info.BQ_CODE IN ($placeholders)";
                $bindings = array_merge($bindings, $bqCodeArray);
            }
        }
        $step2bEnd = microtime(true);
        /* Log::info('getTotalList - 步骤2b（查询病区）', [
            'duration' => round(($step2bEnd - $step2bStart) * 1000, 2) . 'ms'
        ]); */

        // 出院时间
        if (!empty($searchConditions['startTime'])) {
            $where[] = "patient_info.AAC01 >= ?";
            $bindings[] = date("Y-m-d 00:00:00", strtotime($searchConditions['startTime']));
        }
        if (!empty($searchConditions['endTime'])) {
            $where[] = "patient_info.AAC01 <= ?";
            $bindings[] = date("Y-m-d 23:59:59", strtotime($searchConditions['endTime']));
        }

        // 部门条件
        if (is_array($depIds) && !empty($depIds)) {
            $placeholders = implode(',', array_fill(0, count($depIds), '?'));
            $where[] = "ZY_BRRY.BRKS IN ($placeholders)";
            $bindings = array_merge($bindings, $depIds);
        }

        // 极简SQL - 不 JOIN 规则表，只统计 case_quality 的数量
        $caseRule = CaseRule::query()->where('status', 1)->get(['id'])->toArray();
        $ruleSetting = RuleSetting::query()->where('status', 1)->get(['id'])->map(function ($item) {
            return ['id' => $item->id + 1000000];
        })->toArray();
        $ruleArray = array_column(array_merge($caseRule, $ruleSetting), 'id');
        $step2cStart = microtime(true);
        $sql = "
            SELECT COUNT(*) as question_total
            FROM patient_info
            INNER JOIN case_quality_zm ON case_quality_zm.JZHM = patient_info.MED_REC_ID
                AND case_quality_zm.is_correction = 0
                AND case_quality_zm.is_appeal = 0
                AND case_quality_zm.is_ignore = 0
            STRAIGHT_JOIN ZY_BRRY ON ZY_BRRY.ZYH = case_quality_zm.JZHM
            WHERE case_quality_zm.rule_id in (" . implode(',', $ruleArray) . ") AND " . implode(' AND ', $where);

        //Log::info('sql------', [$sql]);

        $result = DB::selectOne($sql, $bindings);
        $question_total = $result->question_total ?? 0;

        return ToolsService::returnData(200, compact('case_total', 'case_quality_total', 'quality_proportion', 'case_calibre', 'question_total'));
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

    /**
     * 处理顶部搜索条件
     * @param array $request
     * @param Builder $model
     * @return Builder
     */
    private function getTopSearch(array $request, Builder $model, $prefix = '')
    {

        //病案号
        $AAA28 = $request['AAA28'] ?? "";
        if (!empty($AAA28)) $model->where(($prefix ? "{$prefix}." : "") . 'AAA28', $AAA28);

        //所属院区
        $YQ_CODE = $request['YQ_CODE'] ?? "";
        if (!empty($YQ_CODE)) $model->whereIn(($prefix ? "{$prefix}." : "") . 'YQ_CODE', $YQ_CODE);

        //所属科室
        $ksCode = $request['KS_CODE'] ?? "";

        if (!empty($ksCode)) {
            $ksArray = Department::query()->whereIn('dep_id', $ksCode)->get(['id', 'sub_ids'])->toArray();
            if (!empty($ksArray)) {
                $ksCodeArray = $ksCode;
                foreach ($ksArray as $v) {
                    if (!empty($v['sub_ids'])) {
                        $ids = explode(',', $v['sub_ids']);
                        $ksCodeArray = array_merge($ksCodeArray, $ids);
                    }
                }
                $ksCodeArray = array_filter(array_unique($ksCodeArray));
                $model->whereIn(($prefix ? "{$prefix}." : "") . 'AAC02C', $ksCodeArray);
            }
        }

        //所属病区
        $bqCode = $request['BQ_CODE'] ?? "";
        if (!empty($bqCode)) {
            $bqArray = Department::query()->whereIn('dep_id', $bqCode)->get(['id', 'sub_ids'])->toArray();
            if (!empty($bqArray)) {
                $bqCodeArray = [];
                foreach ($bqArray as $v) {
                    $ids = explode(',', $v['sub_ids']);
                    $bqCodeArray = array_merge($bqCodeArray, $ids);
                }
                $bqCodeArray = array_unique($bqCodeArray);
                $model->whereIn(($prefix ? "{$prefix}." : "") . 'BQ_CODE', $bqCodeArray);
            }
        }

        //出院时间
        $startTime = $request['startTime'] ? date("Y-m-d 00:00:00", strtotime($request['startTime'])) : "";
        $endTime = $request['endTime'] ? date("Y-m-d 23:59:59", strtotime($request['endTime'])) : "";
        if (!empty($startTime)) $model->where(($prefix ? "{$prefix}." : "") . 'AAC01', '>=', $startTime);
        if (!empty($endTime)) $model->where(($prefix ? "{$prefix}." : "") . 'AAC01', '<=', $endTime);

        return $model;
    }
}
