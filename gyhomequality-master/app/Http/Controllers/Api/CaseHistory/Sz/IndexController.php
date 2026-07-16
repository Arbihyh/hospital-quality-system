<?php

/**
 * 事中病历质控
 */

namespace App\Http\Controllers\Api\CaseHistory\Sz;

use App\Model\CaseRule;
use App\Model\Department;
use App\Model\PatientInfo;
use App\Model\RuleSetting;
use App\Model\Staff;
use App\Model\ZY_BRRY;
use App\Services\CsvService;
use App\Services\UserService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Controllers\Api\ApiController;


class IndexController extends ApiController
{
    public function __construct()
    {
        parent::__construct();
    }

    //region 科室排名===
    /**
     * 事中科室排名列表（按 rank_type 分组：院区/科室/病区）
     * 参考：Terminal\\IndexController::getDepartmentTableList
     * 差异：
     * - 评分字段：score 替代 zm_score
     * - 时间字段：使用顶部筛选的入院时间 AAB01（getTopSearch 内已处理）
     * - 不限制 in_hospital（事中可能未出院）
     * @param Request $request
     * @return array
     */
    public function getDepartmentTableList(Request $request)
    {
        $depIds = UserService::getCurrentUserDep($request);
        if (empty($depIds)) {
            return ToolsService::jsonSuccess(['count' => 0, 'data' => [], 'chartData' => []]);
        }

        //region 处理搜索条件===
        $model = PatientInfo::query()
            ->join("ZY_BRRY", 'ZY_BRRY.ZYH', '=', 'patient_info.MED_REC_ID')
            ->when(is_array($depIds), function ($query) use ($depIds) {
                return $query->whereIn('ZY_BRRY.BRKS', $depIds);
            })
            ->whereNotNull('patient_info.MED_REC_ID');

        $AAA28 = trim((string)$request->post('AAA28', ''));
        $searchConditions = $request->post();
        unset($searchConditions['AAA28']);

        // 复用事中顶部筛选（AAB01 入院时间）
        $model = $this->getTopSearch($searchConditions, $model, 'patient_info');

        if ($AAA28 !== '') {
            $aaa28DepIds = ZY_BRRY::query()
                ->where('AAA28', $AAA28)
                ->whereNotNull('BRKS')
                ->where('BRKS', '!=', '')
                ->distinct()
                ->pluck('BRKS')
                ->toArray();
            if (empty($aaa28DepIds)) {
                return ToolsService::jsonSuccess(['count' => 0, 'data' => [], 'chartData' => []]);
            }
            $model->whereIn('ZY_BRRY.BRKS', $aaa28DepIds);
        }

        // status筛选（出院状态）
        $status = $request->post('status', '');
        $today = date('Y-m-d 00:00:00');
        if ($status == 1) {
            // 在院及当天出院：AAC01为空(NULL或'') 或 AAC01>=当天0点
            $model->where(function ($query) use ($today) {
                $query->where(function ($q) {
                    $q->whereNull('patient_info.AAC01')
                        ->orWhere('patient_info.AAC01', '=', '');
                })->orWhere('patient_info.AAC01', '>=', $today);
            });
        } elseif ($status == 2) {
            // 在院：AAC01为空(NULL或'')
            $model->where(function ($query) {
                $query->whereNull('patient_info.AAC01')
                    ->orWhere('patient_info.AAC01', '=', '');
            });
        } elseif ($status == 3) {
            // 当天出院：AAC01不为空(非NULL且非'')且>=当天0点
            $model->whereNotNull('patient_info.AAC01')
                ->where('patient_info.AAC01', '!=', '')
                ->where('patient_info.AAC01', '>=', $today);
        } elseif ($status == 4) {
            // 常规出院：AAC01不为空(非NULL且非'')且<当天0点
            $model->whereNotNull('patient_info.AAC01')
                ->where('patient_info.AAC01', '!=', '')
                ->where('patient_info.AAC01', '<', $today);
        }

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
                DB::raw('SUM(CASE WHEN patient_info.score < 100 THEN 1 ELSE 0 END) as qx_num'), // 缺陷病例总数
                DB::raw('ROUND(CASE WHEN COUNT(patient_info.id) = 0 THEN 0 ELSE SUM(CASE WHEN patient_info.score < 100 THEN 1 ELSE 0 END) / COUNT(patient_info.id) END,4) as qx_ratio'), // 缺陷病例比率
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
    //endregion

    /**
     * 事中统计汇总列表
     * @param Request $request
     * @return array
     */
    public function getTotalList(Request $request)
    {
        $depIds = UserService::getCurrentUserDep($request);
        if (empty($depIds)) {
            return ToolsService::returnData(200, ['case_total' => 0, 'case_quality_total' => 0, 'quality_proportion' => 0, 'case_calibre' => [], 'question_total' => 0]);
        }

        // ============ 步骤1：病案统计（使用 score、score_lv）============
        $baseQuery = PatientInfo::query()
            ->join('ZY_BRRY', 'ZY_BRRY.ZYH', '=', 'patient_info.MED_REC_ID')
            ->leftJoin('patient_info_v2', function ($join) {
                $join->on('patient_info_v2.ZYH', '=', 'patient_info.MED_REC_ID')
                    ->where('patient_info_v2.status', '=', 0);
            })
            ->when(is_array($depIds), function ($query) use ($depIds) {
                return $query->whereIn('ZY_BRRY.BRKS', $depIds);
            })
            ->whereNotNull('patient_info.MED_REC_ID');

        $baseQuery = $this->getTopSearch($request->post(), $baseQuery, 'patient_info');

        // status筛选（出院状态）
        $status = $request->post('status', '');
        $today = date('Y-m-d 00:00:00');
        if ($status == 1) {
            $baseQuery->where(function ($query) use ($today) {
                $query->where(function ($q) {
                    $q->whereNull('patient_info.AAC01')
                        ->orWhere('patient_info.AAC01', '=', '');
                })->orWhere('patient_info.AAC01', '>=', $today);
            });
        } elseif ($status == 2) {
            $baseQuery->where(function ($query) {
                $query->whereNull('patient_info.AAC01')
                    ->orWhere('patient_info.AAC01', '=', '');
            });
        } elseif ($status == 3) {
            $baseQuery->whereNotNull('patient_info.AAC01')
                ->where('patient_info.AAC01', '!=', '')
                ->where('patient_info.AAC01', '>=', $today);
        } elseif ($status == 4) {
            $baseQuery->whereNotNull('patient_info.AAC01')
                ->where('patient_info.AAC01', '!=', '')
                ->where('patient_info.AAC01', '<=', $today);
        }

        // 缺陷病例按有效缺陷记录统计，确保扣 0 分但仍有缺陷的病例也能纳入。
        $case_quality_total = (clone $baseQuery)
            ->whereExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('case_quality')
                    ->whereColumn('case_quality.JZHM', 'patient_info.MED_REC_ID')
                    ->where('case_quality.is_correction', 0)
                    ->where(function ($appealQuery) {
                        $appealQuery->where('case_quality.is_appeal', 0)
                            ->orWhere(function ($appealedQualityQuery) {
                                $appealedQualityQuery->where('case_quality.is_appeal', 1)
                                    ->whereExists(function ($appealSubQuery) {
                                        $appealSubQuery->select(DB::raw(1))
                                            ->from('appeal')
                                            ->whereColumn('appeal.id', 'case_quality.appeal_id')
                                            ->whereIn('appeal.status', [0, 2]);
                                    });
                            });
                    })
                    ->where('case_quality.is_ignore', 0);
            })
            ->count();

        // 一次查询获取所有统计（使用 score、score_lv）
        $statsResult = $baseQuery->selectRaw('
            COUNT(*) as total,
            patient_info.score_lv as score_lv,
            COUNT(*) as lv_count
        ')->groupBy('patient_info.score_lv')->get();
        $case_total = $statsResult->sum('total');
        $quality_proportion = $case_total != 0 ? bcmul(bcdiv((string)$case_quality_total, (string)$case_total, 4), 100, 2) : 0.00;

        $case_calibre = $statsResult->map(function ($item) {
            return [
                'num' => $item->lv_count,
                'score_lv' => $item->score_lv
            ];
        })->values();

        // ============ 步骤2：问题总数（使用 case_quality）============
        $searchConditions = $request->post();
        $bindings = [];
        $where = [];

        // 基础条件（事中可能未出院，不限 in_hospital）
        $where[] = "patient_info.score IS NOT NULL";

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
        $ksCodeInput = $searchConditions['KS_CODE'] ?? [];
        if (is_string($ksCodeInput)) {
            $ksCodeInput = json_decode($ksCodeInput, true) ?: [];
        }
        if (!empty($ksCodeInput)) {
            $ksArray = Department::query()->whereIn('dep_id', $ksCodeInput)->get(['id', 'sub_ids'])->toArray();
            if (!empty($ksArray)) {
                $ksCodeArray = $ksCodeInput;
                foreach ($ksArray as $v) {
                    if (!empty($v['sub_ids'])) {
                        $ids = explode(',', $v['sub_ids']);
                        $ksCodeArray = array_merge($ksCodeArray, $ids);
                    }
                }
                $ksCodeArray = array_filter(array_unique($ksCodeArray));
                $placeholders = implode(',', array_fill(0, count($ksCodeArray), '?'));
                $where[] = "ZY_BRRY.BRKS IN ($placeholders)";
                $bindings = array_merge($bindings, $ksCodeArray);
            }
        }

        // 病区
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

        // 出院时间筛选（cysj_start/cysj_end不为空时，入院时间条件作废，改为出院时间筛选）
        $cysjStart = $searchConditions['cysj_start'] ?? '';
        $cysjEnd = $searchConditions['cysj_end'] ?? '';
        if (!empty($cysjStart) || !empty($cysjEnd)) {
            // 按出院时间筛选
            if (!empty($cysjStart)) {
                $where[] = "patient_info.AAC01 >= ?";
                $bindings[] = date("Y-m-d 00:00:00", strtotime($cysjStart));
            }
            if (!empty($cysjEnd)) {
                $where[] = "patient_info.AAC01 <= ?";
                $bindings[] = date("Y-m-d 23:59:59", strtotime($cysjEnd));
            }
            $where[] = "patient_info.in_hospital = 2";
        } else {
            // 入院时间（事中可能未出院）
            if (!empty($searchConditions['startTime'])) {
                $where[] = "patient_info.AAB01 >= ?";
                $bindings[] = date("Y-m-d 00:00:00", strtotime($searchConditions['startTime']));
            }
            if (!empty($searchConditions['endTime'])) {
                $where[] = "patient_info.AAB01 <= ?";
                $bindings[] = date("Y-m-d 23:59:59", strtotime($searchConditions['endTime']));
            }
        }

        // 部门条件
        if (is_array($depIds) && !empty($depIds)) {
            $placeholders = implode(',', array_fill(0, count($depIds), '?'));
            $where[] = "ZY_BRRY.BRKS IN ($placeholders)";
            $bindings = array_merge($bindings, $depIds);
        }

        // status筛选（出院状态）
        $status = $searchConditions['status'] ?? '';
        $today = date('Y-m-d 00:00:00');
        if ($status == 1) {
            $where[] = "((patient_info.AAC01 IS NULL OR patient_info.AAC01 = '') OR patient_info.AAC01 >= ?)";
            $bindings[] = $today;
        } elseif ($status == 2) {
            $where[] = "(patient_info.AAC01 IS NULL OR patient_info.AAC01 = '')";
        } elseif ($status == 3) {
            $where[] = "patient_info.AAC01 IS NOT NULL";
            $where[] = "patient_info.AAC01 != ''";
            $where[] = "patient_info.AAC01 >= ?";
            $bindings[] = $today;
        } elseif ($status == 4) {
            $where[] = "patient_info.AAC01 IS NOT NULL";
            $where[] = "patient_info.AAC01 != ''";
            $where[] = "patient_info.AAC01 < ?";
            $bindings[] = $today;
        }

        // 极简SQL - 使用 case_quality 表统计问题数量
        $caseRule = CaseRule::query()->where('status', 1)->get(['id'])->toArray();
        $ruleSetting = RuleSetting::query()->where('status', 1)->get(['id'])->map(function ($item) {
            return ['id' => $item->id + 1000000];
        })->toArray();
        $ruleArray = array_column(array_merge($caseRule, $ruleSetting), 'id');
        $sql = "
            SELECT COUNT(*) as question_total
            FROM patient_info
            INNER JOIN case_quality ON case_quality.JZHM = patient_info.MED_REC_ID
                AND case_quality.is_correction = 0
                AND case_quality.is_appeal = 0
                AND case_quality.is_ignore = 0
            STRAIGHT_JOIN ZY_BRRY ON ZY_BRRY.ZYH = case_quality.JZHM
            WHERE case_quality.rule_id in (" . implode(',', $ruleArray) . ") AND " . implode(' AND ', $where);

        $result = DB::selectOne($sql, $bindings);
        $question_total = $result->question_total ?? 0;

        return ToolsService::returnData(200, compact('case_total', 'case_quality_total', 'quality_proportion', 'case_calibre', 'question_total'));
    }

    /**
     * 事中缺陷问题列表（参考全病历质控 defectIssues）
     * 使用 score / case_quality，按入院时间 AAB01 统计
     * @param Request $request
     * @return array
     */
    public function defectIssues(Request $request)
    {
        $depIds = UserService::getCurrentUserDep($request);
        if (empty($depIds)) {
            return ToolsService::returnData(200, ['count' => 0, 'list' => []]);
        }

        $page = (int)$request->post('page', 1);
        $pageSize = (int)$request->post('size', 10);

        // 基础查询条件（与 getTotalList 步骤2 保持一致）
        $searchConditions = $request->post();
        $bindings = [];
        $where = [];

        // 基础条件：事中不限制出院，只要求有 score
        $where[] = "patient_info.score IS NOT NULL";

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
        $ksCodeInput = $searchConditions['KS_CODE'] ?? [];
        if (is_string($ksCodeInput)) {
            $ksCodeInput = json_decode($ksCodeInput, true) ?: [];
        }
        if (!empty($ksCodeInput)) {
            $ksArray = Department::query()->whereIn('dep_id', $ksCodeInput)->get(['id', 'sub_ids'])->toArray();
            if (!empty($ksArray)) {
                $ksCodeArray = $ksCodeInput;
                foreach ($ksArray as $v) {
                    if (!empty($v['sub_ids'])) {
                        $ids = explode(',', $v['sub_ids']);
                        $ksCodeArray = array_merge($ksCodeArray, $ids);
                    }
                }
                $ksCodeArray = array_filter(array_unique($ksCodeArray));
                $placeholders = implode(',', array_fill(0, count($ksCodeArray), '?'));
                $where[] = "ZY_BRRY.BRKS IN ($placeholders)";
                $bindings = array_merge($bindings, $ksCodeArray);
            }
        }

        // 病区
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

        // 出院时间筛选（cysj_start/cysj_end不为空时，入院时间条件作废，改为出院时间筛选）
        $cysjStart = $searchConditions['cysj_start'] ?? '';
        $cysjEnd = $searchConditions['cysj_end'] ?? '';
        if (!empty($cysjStart) || !empty($cysjEnd)) {
            // 按出院时间筛选
            if (!empty($cysjStart)) {
                $where[] = "patient_info.AAC01 >= ?";
                $bindings[] = date("Y-m-d 00:00:00", strtotime($cysjStart));
            }
            if (!empty($cysjEnd)) {
                $where[] = "patient_info.AAC01 <= ?";
                $bindings[] = date("Y-m-d 23:59:59", strtotime($cysjEnd));
            }
            $where[] = "patient_info.in_hospital = 2";
        } else {
            // 入院时间
            if (!empty($searchConditions['startTime'])) {
                $where[] = "patient_info.AAB01 >= ?";
                $bindings[] = date("Y-m-d 00:00:00", strtotime($searchConditions['startTime']));
            }
            if (!empty($searchConditions['endTime'])) {
                $where[] = "patient_info.AAB01 <= ?";
                $bindings[] = date("Y-m-d 23:59:59", strtotime($searchConditions['endTime']));
            }
        }

        // 部门条件（登录用户所属科室）
        if (is_array($depIds) && !empty($depIds)) {
            $placeholders = implode(',', array_fill(0, count($depIds), '?'));
            $where[] = "ZY_BRRY.BRKS IN ($placeholders)";
            $bindings = array_merge($bindings, $depIds);
        }

        // status筛选（出院状态）
        $status = $searchConditions['status'] ?? '';
        $today = date('Y-m-d 00:00:00');
        if ($status == 1) {
            $where[] = "((patient_info.AAC01 IS NULL OR patient_info.AAC01 = '') OR patient_info.AAC01 >= ?)";
            $bindings[] = $today;
        } elseif ($status == 2) {
            $where[] = "(patient_info.AAC01 IS NULL OR patient_info.AAC01 = '')";
        } elseif ($status == 3) {
            $where[] = "patient_info.AAC01 IS NOT NULL";
            $where[] = "patient_info.AAC01 != ''";
            $where[] = "patient_info.AAC01 >= ?";
            $bindings[] = $today;
        } elseif ($status == 4) {
            $where[] = "patient_info.AAC01 IS NOT NULL";
            $where[] = "patient_info.AAC01 != ''";
            $where[] = "patient_info.AAC01 < ?";
            $bindings[] = $today;
        }

        // 统计每个规则的缺陷数量（case_quality）
        $sql = "
            SELECT case_quality.rule_id, COUNT(1) as total_num
            FROM patient_info
            INNER JOIN case_quality ON case_quality.JZHM = patient_info.MED_REC_ID
                AND case_quality.is_correction = 0
                AND NOT (
                    case_quality.is_appeal = 1
                    AND EXISTS (
                        SELECT 1
                        FROM appeal
                        WHERE appeal.id = case_quality.appeal_id
                            AND appeal.status = 1
                    )
                )
                AND case_quality.is_ignore = 0
            STRAIGHT_JOIN ZY_BRRY ON ZY_BRRY.ZYH = case_quality.JZHM
        ";

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " GROUP BY case_quality.rule_id ORDER BY total_num DESC";

        $statsRaw = DB::select($sql, $bindings);
        $stats = collect($statsRaw)->keyBy('rule_id');

        if ($stats->isEmpty()) {
            return ToolsService::returnData(200, ['count' => 0, 'list' => []]);
        }

        $ruleIds = $stats->keys()->toArray();

        // 前端筛选条件（类型、病例类型、规则描述）
        $type = $searchConditions['type'] ?? null;
        $caseTitleParam = $searchConditions['case_title'] ?? '';
        $caseNoticeParam = $searchConditions['case_notice'] ?? '';
        $caseTitles = $caseTitleParam ? explode(',', $caseTitleParam) : [];
        $ruleNotices = $caseNoticeParam ? explode(',', $caseNoticeParam) : [];

        // 区分普通规则和设置规则
        $normalRuleIds = array_filter($ruleIds, function ($id) {
            return $id < 1000000;
        });
        $settingRuleIds = array_map(function ($id) {
            return $id - 1000000;
        }, array_filter($ruleIds, function ($id) {
            return $id >= 1000000;
        }));

        $rules = [];

        // 查询 case_rule
        if (!empty($normalRuleIds)) {
            $caseRules = DB::table('case_rule')
                ->select('id', 'category', 'notice')
                ->whereIn('id', $normalRuleIds)
                ->where('status', 1)
                ->when($type, function ($query) use ($type) {
                    return $query->where('type', $type);
                })
                ->when($caseTitles, function ($query) use ($caseTitles) {
                    return $query->whereIn('category', $caseTitles);
                })
                ->when($ruleNotices, function ($query) use ($ruleNotices) {
                    return $query->whereIn('notice', $ruleNotices);
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
                ->when($type, function ($query) use ($type) {
                    return $query->where('type', $type);
                })
                ->when($caseTitles, function ($query) use ($caseTitles) {
                    return $query->whereIn('case_type', $caseTitles);
                })
                ->when($ruleNotices, function ($query) use ($ruleNotices) {
                    return $query->whereIn('description', $ruleNotices);
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

        // 计算 proportion 的分母：事中按 score < 100 的缺陷病案数
        $defectTotalQuery = PatientInfo::query()
            ->join('ZY_BRRY', 'ZY_BRRY.ZYH', '=', 'patient_info.MED_REC_ID')
            ->leftJoin('patient_info_v2', function ($join) {
                $join->on('patient_info_v2.ZYH', '=', 'patient_info.MED_REC_ID')
                    ->where('patient_info_v2.status', '=', 0);
            })
            ->when(is_array($depIds), function ($query) use ($depIds) {
                return $query->whereIn('ZY_BRRY.BRKS', $depIds);
            })
            ->whereNotNull('patient_info.MED_REC_ID');

        // 复用顶部搜索条件
        $defectTotalQuery = $this->getTopSearch($searchConditions, $defectTotalQuery, 'patient_info')
            ->whereNotNull('patient_info.score');

        // status筛选（出院状态）- 与上方保持一致
        if ($status == 1) {
            $defectTotalQuery->where(function ($query) use ($today) {
                $query->where(function ($q) {
                    $q->whereNull('patient_info.AAC01')
                        ->orWhere('patient_info.AAC01', '=', '');
                })->orWhere('patient_info.AAC01', '>=', $today);
            });
        } elseif ($status == 2) {
            $defectTotalQuery->where(function ($query) {
                $query->whereNull('patient_info.AAC01')
                    ->orWhere('patient_info.AAC01', '=', '');
            });
        } elseif ($status == 3) {
            $defectTotalQuery->whereNotNull('patient_info.AAC01')
                ->where('patient_info.AAC01', '!=', '')
                ->where('patient_info.AAC01', '>=', $today);
        } elseif ($status == 4) {
            $defectTotalQuery->whereNotNull('patient_info.AAC01')
                ->where('patient_info.AAC01', '!=', '')
                ->where('patient_info.AAC01', '<', $today);
        }

        $defectTotal = $defectTotalQuery
            ->selectRaw('SUM(CASE WHEN patient_info.score < 100 THEN 1 ELSE 0 END) as defect_total')
            ->value('defect_total') ?? 0;

        foreach ($list as &$v) {
            $v['proportion'] = $defectTotal > 0 ? round($v['total_num'] / $defectTotal * 100, 2) . '%' : '0%';
        }

        $totalCount = count($list);
        $page = max(1, $page);
        $pageSize = max(1, $pageSize);
        $pageStart = ($page - 1) * $pageSize;
        $list = array_slice($list, $pageStart, $pageSize);

        return ToolsService::returnData(200, ['count' => $totalCount, 'list' => $list]);
    }

    /**
     * 事中医师排名
     * 参考 GY_bazb CaseQualityController::doctorRanking
     * 数据来源：doctor_ranking_sz_summary（按入院时间 AAB01 汇总）
     * @param Request $request
     * @return array|string|null
     */
    public function doctorRanking(Request $request)
    {
        $depIds = UserService::getCurrentUserDep($request);
        if (empty($depIds)) {
            return ToolsService::returnData(200, ['list' => [], 'count' => 0]);
        }

        $AAA28 = $request->post('AAA28', '');
        $startTime = $request->post('startTime', '');
        $endTime = $request->post('endTime', '');
        $cysjStart = $request->post('cysj_start', '');
        $cysjEnd = $request->post('cysj_end', '');

        $startDate = !empty($startTime) ? date('Y-m-d', strtotime($startTime)) : date('Y-01-01');
        $endDate = !empty($endTime) ? date('Y-m-d', strtotime($endTime)) : date('Y-m-d');

        $page = (int)$request->post('page', 1);
        $pageSize = (int)$request->post('page_size', $request->post('pageSize', 10));
        $AAA28 = trim((string)$request->post('AAA28', ''));

        $KS_CODE = $request->post('KS_CODE', []);
        if (is_string($KS_CODE)) {
            $KS_CODE = json_decode($KS_CODE, true) ?: [];
        }
        $isExport = (int)$request->post('is_export', $request->post('isExport', 0));
        $orderKey = $request->post('order_key', 'total_num') ?: 'total_num';

        // ============ 从汇总表查询（极快！）============
        $bindings = [];
        $where = [];
        $useCysj = !empty($cysjStart) || !empty($cysjEnd);

        if ($useCysj) {
            // 出院时间筛选（cysj_start/cysj_end不为空时，入院时间条件作废）
            if (!empty($cysjStart)) {
                $where[] = "patient_info.AAC01 >= ?";
                $bindings[] = date('Y-m-d 00:00:00', strtotime($cysjStart));
            }
            if (!empty($cysjEnd)) {
                $where[] = "patient_info.AAC01 <= ?";
                $bindings[] = date('Y-m-d 23:59:59', strtotime($cysjEnd));
            }
        } else {
            $where[] = "s.record_date >= ?";
            $where[] = "s.record_date <= ?";
            $bindings[] = $startDate;
            $bindings[] = $endDate;
        }
        if (!empty($AAA28)) {
            $where[] = "patient_info.AAA28 = ?";
            $bindings[] = $AAA28;
        }

        // status筛选（出院状态）
        $status = $request->post('status', '');
        $today = date('Y-m-d 00:00:00');
        $needJoin = $useCysj; // 使用出院时间筛选时需要JOIN ZY_BRRY
        if ($status == 1) {
            $where[] = "((patient_info.AAC01 IS NULL OR patient_info.AAC01 = '') OR patient_info.AAC01 >= ?)";
            $bindings[] = $today;
            $needJoin = true;
        } elseif ($status == 2) {
            $where[] = "(patient_info.AAC01 IS NULL OR patient_info.AAC01 = '')";
            $needJoin = true;
        } elseif ($status == 3) {
            $where[] = "patient_info.AAC01 IS NOT NULL";
            $where[] = "patient_info.AAC01 != ''";
            $where[] = "patient_info.AAC01 >= ?";
            $bindings[] = $today;
            $needJoin = true;
        } elseif ($status == 4) {
            $where[] = "patient_info.AAC01 IS NOT NULL";
            $where[] = "patient_info.AAC01 != ''";
            $where[] = "patient_info.AAC01 < ?";
            $bindings[] = $today;
            $needJoin = true;
        }

        if ($AAA28 !== '') {
            $patientDoctorRows = DB::table('patient_info_v2 as piv')
                ->join('patient_info as pi', 'pi.MED_REC_ID', '=', 'piv.ZYH')
                ->where('pi.AAA28', $AAA28)
                ->get(['piv.AEE01_CODE', 'piv.AEE02_CODE', 'piv.AEE03_CODE', 'piv.AEE04_CODE']);

            $doctorCodes = [];
            foreach ($patientDoctorRows as $row) {
                foreach (['AEE01_CODE', 'AEE02_CODE', 'AEE03_CODE', 'AEE04_CODE'] as $field) {
                    $code = trim((string)($row->$field ?? ''));
                    if ($code !== '') {
                        $doctorCodes[] = $code;
                    }
                }
            }

            $doctorCodes = array_values(array_unique($doctorCodes));
            if (empty($doctorCodes)) {
                return ToolsService::returnData(200, ['count' => 0, 'list' => []]);
            }

            $placeholders = implode(',', array_fill(0, count($doctorCodes), '?'));
            $where[] = "s.code IN ($placeholders)";
            $bindings = array_merge($bindings, $doctorCodes);
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $joinClause = $needJoin ? 'INNER JOIN patient_info ON patient_info.MED_REC_ID = s.zyh COLLATE utf8mb4_unicode_ci' : '';

        $sql = "
            SELECT
                s.code,
                COUNT(DISTINCT s.zyh) as total_num,
                SUM(s.score) as score
            FROM doctor_ranking_sz_summary s
            $joinClause
            $whereClause
            GROUP BY s.code
        ";

        $data = DB::select($sql, $bindings);
        if (empty($data)) {
            return ToolsService::returnData(200, ['count' => 0, 'list' => []]);
        }
        $data = json_decode(json_encode($data), true);

        // 查询员工和部门信息（用于过滤权限、展示姓名/科室）
        $codes = array_column($data, 'code');
        $staff = [];
        if (env('APP_NAME') == 'ningxia') {
            $staff = Staff::query()
                ->whereIn('base_code', $codes)
                ->get(['base_code as code', 'name', 'ksdm'])
                ->keyBy('code')
                ->toArray();
        } else {
            $staff = Staff::query()
                ->whereIn('code', $codes)
                ->get(['code', 'name', 'ksdm'])
                ->keyBy('code')
                ->toArray();
        }

        // 过滤范围：前端选择 KS_CODE，否则用当前账号权限 depIds
        $filterDepIds = [];
        if ($KS_CODE && is_array($KS_CODE) && !empty($KS_CODE)) {
            $filterDepIds = $KS_CODE;
        } else {
            $filterDepIds = is_array($depIds) ? $depIds : [];
        }

        $filterDepIds = array_values(array_filter(array_unique($filterDepIds)));

        // 过滤掉无员工信息或不在权限/筛选范围内的医生
        $filtered = [];
        $depIdsForQuery = [];
        foreach ($data as $row) {
            $code = $row['code'] ?? '';
            if (!$code || empty($staff[$code])) {
                continue;
            }
            $ksdm = $staff[$code]['ksdm'] ?? null;
            if (!empty($filterDepIds) && !in_array($ksdm, $filterDepIds)) {
                continue;
            }
            $depIdsForQuery[] = $ksdm;
            $filtered[] = $row;
        }

        if (empty($filtered)) {
            return ToolsService::returnData(200, ['count' => 0, 'list' => []]);
        }

        $depIdsForQuery = array_values(array_filter(array_unique($depIdsForQuery)));
        $depArray = [];
        if (!empty($depIdsForQuery)) {
            $depArray = Department::query()
                ->whereIn('dep_id', $depIdsForQuery)
                ->pluck('dep_name', 'dep_id')
                ->toArray();
        }

        // 处理数据
        foreach ($filtered as &$v) {
            $code = $v['code'];
            $totalNum = (int)($v['total_num'] ?? 0);
            $score = (float)($v['score'] ?? 0);

            $v['doc_count'] = $totalNum;
            $v['avg_score'] = $totalNum > 0 ? round(($totalNum * 100 - $score) / $totalNum, 2) : 0;
            $v['key'] = $staff[$code]['name'] ?? '';
            $ksdm = $staff[$code]['ksdm'] ?? 0;
            $v['dep_name'] = $depArray[$ksdm] ?? '';
        }
        unset($v);

        // 排序（默认按 total_num 降序）
        usort($filtered, function ($a, $b) use ($orderKey) {
            $av = $a[$orderKey] ?? 0;
            $bv = $b[$orderKey] ?? 0;
            if ($av == $bv) {
                return 0;
            }
            return ($bv <=> $av);
        });

        // 排名（按排序后的顺序）
        foreach ($filtered as $idx => &$v) {
            $v['rank'] = $idx + 1;
        }
        unset($v);

        $totalCount = count($filtered);

        // 导出或分页
        if ($isExport === 1) {
            $exportData = [];
            $exportData[] = ['排名', '医生姓名', '医生工号', '医生科室', '病历总数', '总扣分', '平均得分'];
            foreach ($filtered as $row) {
                $exportData[] = [
                    $row['rank'] ?? '',
                    $row['key'] ?? '',
                    $row['code'] ?? '',
                    $row['dep_name'] ?? '',
                    $row['total_num'] ?? 0,
                    $row['score'] ?? 0,
                    $row['avg_score'] ?? 0,
                ];
            }
            $csv = new CsvService();
            $csv->filename = $csv->charset('事中医生排名', 'UTF-8');
            return $csv->export($exportData);
        }

        $page = max(1, $page);
        $pageSize = max(1, $pageSize);
        $pageStart = ($page - 1) * $pageSize;
        $returnData = array_slice($filtered, $pageStart, $pageSize);

        return ToolsService::returnData(200, ['count' => $totalCount, 'list' => $returnData]);
    }

    /**
     * 处理顶部搜索条件
     * @param array $request
     * @param Builder $model
     * @param string $prefix
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
        $ksCode = $request['KS_CODE'] ?? [];
        if (is_string($ksCode)) {
            $ksCode = json_decode($ksCode, true) ?: [];
        }
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
                $model->whereIn('ZY_BRRY.BRKS', $ksCodeArray);
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

        //出院时间筛选（cysj_start/cysj_end不为空时，入院时间条件作废，改为出院时间筛选）
        $cysjStart = $request['cysj_start'] ?? '';
        $cysjEnd = $request['cysj_end'] ?? '';
        if (!empty($cysjStart) || !empty($cysjEnd)) {
            // 按出院时间筛选
            if (!empty($cysjStart)) {
                $model->where('patient_info.AAC01', '>=', date("Y-m-d 00:00:00", strtotime($cysjStart)));
            }
            if (!empty($cysjEnd)) {
                $model->where('patient_info.AAC01', '<=', date("Y-m-d 23:59:59", strtotime($cysjEnd)));
            }
            $model->where(($prefix ? "{$prefix}." : "") . 'in_hospital', 2);
        } else {
            //入院时间（事中可能未出院）
            $startTime = $request['startTime'] ? date("Y-m-d 00:00:00", strtotime($request['startTime'])) : "";
            $endTime = $request['endTime'] ? date("Y-m-d 23:59:59", strtotime($request['endTime'])) : "";
            if (!empty($startTime)) $model->where(($prefix ? "{$prefix}." : "") . 'AAB01', '>=', $startTime);
            if (!empty($endTime)) $model->where(($prefix ? "{$prefix}." : "") . 'AAB01', '<=', $endTime);
        }

        return $model;
    }
}
