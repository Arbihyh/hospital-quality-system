<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Service\ExportService;
use App\Http\Service\FrontDataService;
use App\Model\Appeal;
use App\Model\CaseRule;
use App\Model\Department;
use App\Model\DepartmentData;
use App\Model\Error;
use App\Model\ErrorData;
use App\Model\ErrorRule;
use App\Model\ErrorV2;
use App\Model\HomeQuality;
use App\Model\PatientHospitalInfo;
use App\Model\PatientInfo;
use App\Model\PatientInfoV2;
use App\Model\PatientScore;
use App\Model\RuleSetting;
use App\Model\RuleSettingOther;
use App\Model\Staff;
use App\Model\TableDictSY;
use App\Model\ZY_BRRY;
use App\Services\CsvService;
use App\Services\ElasticsearchService;
use App\Services\PublicService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HomeQualityController extends Controller
{
    /**
     * 缺陷问题列表
     * @param Request $request
     * @return array
     */
    public function errorData(Request $request)
    {
        $startTime = $request->post("start_time", '');
        $endTime = $request->post("end_time", '');
        $level = $request->post("level", '');
        $type = $request->post("type", '');
        $source = $request->post('source', 0);
        $AAC11C = $request->post("AAC11C", '');
        $field = $request->post("field", '');
        $desc = $request->post("desc", '');
        $page = $request->post("page", 1);
        $pageSize = $request->post("page_size", 10);
        $hospital_name = $request->post('hospital_name', '');
        $hospital_name = !empty($hospital_name) ? $hospital_name : config('confAdmin.hospital_name');

        $startTime = !empty($startTime) ? strtotime($startTime) : 1577808000;
        $endTime = !empty($endTime) ? strtotime($endTime) : 4102329600;

        $SYear = date('Y', $startTime);
        $SMonth = date('m', $startTime);
        $EYear = date('Y', $endTime);
        $EMonth = date('m', $endTime);
        $query = ErrorData::query()
            ->where('error_data.hospital_name', '=', $hospital_name)
            ->where('error_data.source', $source);
        // 错误等级
        if ($level !== null) {
            $level = ErrorRule::RULE_LEVEL_CODE[$level];
            $query->where('er.level', '=', $level);
        }
        // 缺陷分类
        if ($type !== null) {
            $type = ErrorRule::RULE_TYPE_CODE[$type];
            $query->where('er.type', '=', $type);
        }
        // 缺陷字段
        if ($field) {
            $query->where('er.field', 'like', "%" . $field . "%");
        }
        // 缺陷描述
        if ($desc) {
            $query->where('er.desc', 'like', "%" . $desc . "%");
        }

        if ($AAC11C !== null) {
            $error = Error::query()
                ->where('hospital_name', '=', $hospital_name)
                ->where('AAC11C', '=', $AAC11C)
                ->whereRaw("(year >= $SYear and month >= $SMonth)")
                ->whereRaw("(year <= $EYear and month <= $EMonth)")
                ->groupBy('error_rule')
                ->pluck('error_rule')->toArray();
            if (!empty($error)) {
                $query->whereIn('error_rule', $error);
            }
        }

        $field = ['error_data.error_rule', DB::raw('sum(`count`) as count'), 'field', 'er.desc', 'er.level', 'er.auth', 'er.type'];
        $data = $query
            ->leftJoin('error_rule as er', 'error_data.error_rule', '=', 'er.id')
            //            ->where('er.type','!=','')
            ->groupBy('error_data.error_rule')
            ->orderBy('count', 'desc')
            ->orderBy('error_data.error_rule')
            ->whereRaw("(error_data.year >= $SYear and error_data.month >= $SMonth)")
            ->whereRaw("(error_data.year <= $EYear and error_data.month <= $EMonth)")
            ->paginate($pageSize, $field, 'page', $page)
            ->toArray();

        $list = !empty($data['data']) ? $data['data'] : [];
        $count = !empty($data['total']) ? $data['total'] : 0;

        if (!empty($list)) {
            foreach ($list as &$value) {
                $value['level'] = ErrorRule::RULE_LEVEL[$value['level']] ?? $value['level'];
                $value['type'] = ErrorRule::RULE_TYPE[$value['type']] ?? $value['type'];
            }
        }

        return ToolsService::returnData(200, ['list' => $list, 'count' => $count]);
    }

    /**
     * 缺陷问题列表导出
     * @param Request $request
     * @return string|null
     */
    public function errorDataExport(Request $request)
    {
        $startTime = $request->post("start_time", '');
        $endTime = $request->post("end_time", '');
        $level = $request->post("level", '');
        $type = $request->post("type", '');
        $source = $request->post('source', 0);
        $AAC11C = $request->post("AAC11C", '');
        $field = $request->post("field", '');
        $desc = $request->post("desc", '');
        $hospital_name = $request->post('hospital_name', '');
        $hospital_name = !empty($hospital_name) ? $hospital_name : config('confAdmin.hospital_name');

        $startTime = !empty($startTime) ? strtotime($startTime) : 1577808000;
        $endTime = !empty($endTime) ? strtotime($endTime) : 4102329600;

        $SYear = date('Y', $startTime);
        $SMonth = date('m', $startTime);
        $EYear = date('Y', $endTime);
        $EMonth = date('m', $endTime);

        $query = ErrorData::query()
            ->where('error_data.hospital_name', '=', $hospital_name)
            ->where('error_data.source', $source);
        // 错误等级
        if ($level !== null) {
            $level = ErrorRule::RULE_LEVEL_CODE[$level];
            $query->where('er.level', '=', $level);
        }
        // 缺陷分类
        if ($type !== null) {
            $type = ErrorRule::RULE_TYPE_CODE[$type];
            $query->where('er.type', '=', $type);
        }
        // 缺陷字段
        if ($field) {
            $query->where('er.field', 'like', "%" . $field . "%");
        }
        // 缺陷描述
        if ($desc) {
            $query->where('er.desc', 'like', "%" . $desc . "%");
        }

        if ($AAC11C !== null) {
            $error = Error::query()
                ->where('hospital_name', '=', $hospital_name)
                ->where('AAC11C', '=', $AAC11C)
                ->whereRaw("(year >= $SYear and month >= $SMonth)")
                ->whereRaw("(year <= $EYear and month <= $EMonth)")
                ->groupBy('error_rule')
                ->pluck('error_rule')->toArray();
            if (!empty($error)) {
                $query->whereIn('error_rule', $error);
            }
        }

        $field = ['error_data.error_rule', DB::raw('sum(`count`) as count'), 'field', 'er.desc', 'er.level', 'er.auth', 'er.type'];
        $data = $query
            ->leftJoin('error_rule as er', 'error_data.error_rule', '=', 'er.id')
            //            ->where('er.type','!=','')
            ->groupBy('error_data.error_rule')
            ->orderBy('count', 'desc')
            ->orderBy('error_data.error_rule')
            ->whereRaw("(error_data.year >= $SYear and error_data.month >= $SMonth)")
            ->whereRaw("(error_data.year <= $EYear and error_data.month <= $EMonth)")
            ->get($field)->toArray();

        $exportData = [];
        $exportData[] = ['序号', '缺陷描述', '缺陷字段', '缺陷数量', '缺陷分级', '缺陷归类'];
        $index = 1;
        foreach ($data as $val) {
            $exportData[] = [
                $index,
                $val['desc'] ?? '',
                $val['field'] ?? '',
                $val['count'] ?? 0,
                ErrorRule::RULE_LEVEL[$val['level']] ?? $val['level'],
                ErrorRule::RULE_TYPE[$val['type']] ?? $val['type'],
            ];
            $index++;
        }

        $csv = new CsvService();
        $csv->filename = $csv->charset('缺陷问题', 'UTF-8');

        return $csv->export($exportData, false);
    }

    public function errorDetailsListQuery($request)
    {

        $errorRule = $request->post('error_rule', '');
        $AAA28 = $request->post('AAA28', '');
        $isBas = $request->post('is_bas', '');
        $type = $request->post('type', '');
        $level = $request->post("level", '');
        $AAC11C = $request->post("dep_id", '');
        $isEdit = $request->post("is_edit", '');
        $errorField = $request->post("error_field", '');
        $source = $request->post('source', 0);
        $desc = $request->post('desc', '');
        $AEE04 = $request->post('AEE04', '');
        $AEE08 = $request->post('AEE08', '');
        $ICD10_NAME = $request->post('ICD10_NAME', '');
        $ICD10_ID1 = $request->post('ICD10_ID1', '');
        $ICD9_NAME = $request->post('ICD9_NAME', '');
        $ICD9_ID1 = $request->post('ICD9_ID1', '');
        $hospital_name = $request->post('hospital_name', '');
        $hospital_name = !empty($hospital_name) ? $hospital_name : config('confAdmin.hospital_name');

        // 验证规则id是否存在
        if (empty($errorRule)) {
            //            return ToolsService::returnData(4001, '参数错误', $msg ?? '');
        }

        // 查询数据
        $query = Error::query()
            ->leftJoin("ZY_BRRY", "ZY_BRRY.ZYH", "=", "error.ZYH")
            ->where('error.hospital_name', '=', $hospital_name)
            ->where('error.source', '=', $source);

        // AAA28
        if (!empty($AAA28)) {
            $query->where('ZY_BRRY.AAA28', '=', $AAA28);
        }
        if (!empty($isBas)) {
            $query->where('error.is_bas', '=', $isBas);
        }
        // 获取指定的质控规则
        if (!empty($errorRule)) {
            $query->where('error.error_rule', '=', $errorRule);
        }
        // 是否修改
        if (!empty($isEdit)) {
            $query->where('error.is_edit', '=', $isEdit);
        }
        // 科室
        if (!empty($AAC11C)) {
            $query->where('error.AAC11C', '=', $AAC11C);
        }
        // 错误等级
        if ($level !== null) {
            $query->where('error.level', '=', $level);
        }
        // 缺陷分类
        if ($type !== null) {
            $type = ErrorRule::RULE_TYPE_CODE[$type];
            $query->where('error.type', '=', $type);
        }
        // 出院时间
        $startTime = $request->post("start_time", '');
        $endTime = $request->post("end_time", '');
        $startTime = !empty($startTime) ? date("Y-m-d H:i:s", strtotime($startTime)) : "";
        $endTime = !empty($endTime) ? date("Y-m-d H:i:s", strtotime($endTime) + 24 * 3600) : "";
        if (!empty($startTime) && !empty($endTime)) {
            $query->where('ZY_BRRY.AAC01', '>=', $startTime);
            $query->where('ZY_BRRY.AAC01', '<=', $endTime);
        }
        // 质控时间
        $zkStartTime = $request->post("zk_start_time", '');
        $zkEndTime = $request->post("zk_end_time", '');
        $zkStartTime = !empty($zkStartTime) ? date("Y-m-d H:i:s", strtotime($zkStartTime)) : "";
        $zkEndTime = !empty($zkEndTime) ? date("Y-m-d H:i:s", strtotime($zkEndTime) + 24 * 3600) : "";
        if (!empty($zkStartTime) && !empty($zkEndTime)) {
            $query->where('error.created_at', '>=', $zkStartTime);
            $query->where('error.created_at', '<=', $zkEndTime);
        }
        // 缺陷描述
        if (!empty($desc)) {
            $query->where('error.desc', 'like', "%" . $desc . "%");
        }
        // 住院医师
        if (!empty($AEE04)) {
            $query->where('error.AEE04', '=', $AEE04);
        }
        // 编码员
        if (!empty($AEE08)) {
            $query->where('error.AEE08', '=', $AEE08);
        }
        // 主要诊断名称
        if (!empty($ICD10_NAME)) {
            $query->where('error.ICD10_NAME', 'like', "%" . $ICD10_NAME . "%");
        }
        // 主要诊断编码
        if (!empty($ICD10_ID1)) {
            $query->where('error.ICD10_ID1', 'like', "%" . $ICD10_ID1 . "%");
        }
        // 主要手术名称
        if (!empty($ICD9_NAME)) {
            $query->where('error.ICD9_NAME', 'like', "%" . $ICD9_NAME . "%");
        }
        // 主要手术编码
        if (!empty($ICD9_ID1)) {
            $query->where('error.ICD9_ID1', 'like', "%" . $ICD9_ID1 . "%");
        }
        // 获取指定的质控规则
        if (!empty($errorRule)) {
            $query->where('error.error_rule', '=', $errorRule);
        }
        // 质控字段
        if (!empty($errorField)) {
            $query->where('error.error_field', '=', $errorField);
        }

        return $query;
    }

    /**
     * 缺陷问题详情列表
     * @param Request $request
     * @return array
     */
    public function errorDetailsList(Request $request)
    {
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        $sort = $request->post('sort', '');

        $query = $this->errorDetailsListQuery($request);

        // 排序
        $sortField = !empty($sort) ? $sort[0] : 'ZY_BRRY.AAC01';
        $sortType = !empty($sort) ? $sort[1] : 'desc';

        $field = ['error_name AS field', 'ZY_BRRY.AAA28', 'is_edit', 'ZY_BRRY.ZYH as MED_REC_ID', 'AAA01', 'AAC11N', 'ZY_BRRY.AAC01', 'error_field', 'error_name', 'level', 'type', 'desc', 'AAC03', 'AEE04', 'AEE08', 'ICD10_NAME', 'ICD10_ID1', 'ICD9_NAME', 'ICD9_ID1'];
        $pageStart = ($page - 1) * $pageSize;
        $list = $query
            ->orderBy($sortField, $sortType)
            ->offset($pageStart)->LIMIT($pageSize)->get()->toArray();
        $count = $query->count();

        if (!empty($list)) {
            $doctor = Staff::query()->pluck('name', 'code')->toArray();
            foreach ($list as &$value) {
                $value['AEE08_code'] = $value['AEE08'] ?? "";
                $value['AEE08'] = !empty($value['AEE08']) && !empty($doctor[$value['AEE08']]) ? $doctor[$value['AEE08']] : "";
                $value['level'] = ErrorRule::RULE_LEVEL[$value['level']] ?? $value['level'];
                $value['type'] = ErrorRule::RULE_TYPE[$value['type']] ?? $value['type'];
            }
        }

        return ToolsService::returnData(200, ['list' => $list, 'count' => $count]);
    }

    /**
     * 缺陷问题详情列表导出
     * @param Request $request
     * @return array|string|null
     */
    public function errorDetailsListExport(Request $request)
    {

        $sort = $request->post('sort', '');

        $query = $this->errorDetailsListQuery($request);

        // 排序
        $sortField = !empty($sort) ? $sort[0] : 'AAC01';
        $sortType = !empty($sort) ? $sort[1] : 'desc';
        $field = ['error_name AS field', 'AAA28', 'is_edit', 'ZYH as MED_REC_ID', 'AAA01', 'AAC11N', 'AAC01', 'error_field', 'error_name', 'level', 'type', 'desc', 'AAC03', 'AEE04', 'AEE08', 'ICD10_NAME', 'ICD10_ID1', 'ICD9_NAME', 'ICD9_ID1'];
        $data = $query
            ->orderBy($sortField, $sortType)
            ->get($field)->toArray();

        $exportData = [];
        $exportData[] = ['序号', '缺陷字段', '缺陷描述', '住院号码', '姓名', '出院时间', '出院科室', '编码员', '住院医师', '主要诊断名称', '主要诊断编码', '主要手术名称', '主要手术编码', '缺陷分级', '缺陷归类'];
        if (!empty($data)) {
            $doctor = Staff::query()->pluck('name', 'code')->toArray();
            $index = 1;
            foreach ($data as $val) {
                $AEE08 = $doctor[$val['AEE08']] ?? $val['AEE08'];
                $exportData[] = [
                    $index,
                    $val['field'],
                    $val['desc'],
                    $val['AAA28'],
                    $val['AAA01'],
                    $val['AAC01'],
                    $val['AAC11N'],
                    $AEE08,
                    $val['AEE04'],
                    $val['ICD10_NAME'],
                    $val['ICD10_ID1'],
                    $val['ICD9_NAME'],
                    $val['ICD9_ID1'],
                    ErrorRule::RULE_LEVEL[$val['level']] ?? $val['level'],
                    ErrorRule::RULE_TYPE[$val['type']] ?? $val['type'],
                ];
                $index++;
            }
        }

        $csv = new CsvService();
        $csv->filename = $csv->charset('缺陷问题详情列表导出', 'UTF-8');

        return $csv->export($exportData, false);
    }

    /**
     * 获取搜索条件
     * @return array
     */
    public function getErrorSerachWhere()
    {
        // 缺陷归类
        $returnData['type'] = ['患者基本信息', '诊疗信息', '费用信息'];

        // 缺陷等级
        $returnData['level'] = ['强制', '建议'];

        // 医师
        $doctor = Staff::query()->where('name', '!=', '')->get(['code as id', 'name'])->toArray();
        $returnData['doctor'] = $doctor;
        // 缺陷字段
        $errorField = ErrorRule::query()
            ->groupBy('auth')
            ->get(['auth', 'field'])
            ->toArray();
        $returnData['error_field'] = $errorField;

        array_unshift($returnData['error_field'], ['auth' => "", 'field' => "请选择"]);

        return ToolsService::returnData(200, $returnData);
    }

    /**
     * 病案首页质控结果
     * @param Request $request
     * @return array
     */
    public function getQualityResult(Request $request)
    {
        $ZYH = $request->post('id', '');
        $source = $request->post('source', '');
        $showCorrection = $request->post('show_correction', 0) or 0; // 是否返回整改数据

        if (empty($ZYH)) {
            return ToolsService::returnData(4001, '', '参数错误');
        }

        Log::info('getQualityResult 请求参数：' . json_encode($request->all()));

        // 质控结果
        $error = ErrorV2::query()
            ->where('error_v2.ZYH', '=', $ZYH)
            ->where('error_v2.status', '=', 0)
            ->get(["error_v2.*"])->toArray();
        $returnData = ['ZYH' => $ZYH, 'score' => ['score' => 100, 'level' => 0], 'qz' => [], 'jy' => [], 'list' => []];
        $down = 0;
        $req = 0;
        if (!empty($error)) {
            $errorRuleData = ErrorRule::query()->where('status', '=', 0)->get()->toArray();
            $errorRuleData = array_column($errorRuleData, null, 'id');
            $errorRule = array_column($error, 'error_rule');
            /**
             * 查询申诉状态
             */
            $appeal = Appeal::query()
                ->where('ZYH', '=', $ZYH)
                ->where('quality_type', '=', 1)
                ->whereIn('error_id', $errorRule)
                ->get()
                ->toArray();
            $appeal = array_column($appeal, null, 'error_id');
            $ruleSetting = RuleSetting::query()->where('status', '=', 1)->where('rule_type', '=', '首页规则')->get()->toArray();
            $ruleSetting = array_column($ruleSetting, null, 'id');
            foreach ($error as &$errorInfo) {
                // 隐藏已整改数据
                if ($showCorrection == 2 and $errorInfo['is_correction'] == 1) {
                    continue;
                }
                // 规则不存在，已忽略的数据不展示
                $ruleSettingItem = $ruleSetting[$errorInfo['error_rule'] - 1000000] ?? [];
                if ((empty($errorRuleData[$errorInfo['error_rule']]) && empty($ruleSettingItem)) || $errorInfo['is_ignore'] == 1) {
                    continue;
                }
                // 获取申诉数据，如果没有关联申诉ID则不展示
                if ($source == "appeal" && empty($errorInfo['appeal_id'])) {
                    continue;
                }
                // 未整改的数据才计算分数
                /* if (empty($errorInfo['is_correction'] == 1)) {
                    $down += $errorRuleData[$errorInfo['error_rule']]['down'];
                }
                $errorInfo['level'] = $errorRuleData[$errorInfo['error_rule']]['level'];
                $errorInfo['error_field'] = $errorRuleData[$errorInfo['error_rule']]['auth'];
                $errorInfo['error_name'] = $errorRuleData[$errorInfo['error_rule']]['field'];
                $errorInfo['category'] = $errorRuleData[$errorInfo['error_rule']]['category'];
                $errorInfo['down'] = $errorRuleData[$errorInfo['error_rule']]['down'];
                $errorInfo['is_artificial'] = $errorInfo['is_artificial'] ?? 0;
                $errorInfo['rule_id'] = $errorInfo['error_rule'];
                $errorInfo['appeal_id'] = $appeal[$errorInfo['error_rule']]['id'] ?? 0;
                $errorInfo['type'] = $appeal[$errorInfo['error_rule']]['type'] ?? 0;
                $errorInfo['status'] = $appeal[$errorInfo['error_rule']]['status'] ?? 0;
                $errorInfo['reject_content'] = $appeal[$errorInfo['error_rule']]['reject_content'] ?? 0;
                $errorInfo['cate'] = 1; */

                if ($errorInfo['error_rule'] > 1000000 && isset($ruleSettingItem)) {
                    $errorfeild = TableDictSY::query()->where('field_name', '=', $ruleSettingItem['case_type'])->get()->ToArray();
                    $object = ['A类' => 0, 'B类' => 1, 'C类' => 2, 'D类' => 3];
                    //$down += $ruleSettingItem['score'] ?? 0;
                    if (empty($errorInfo['is_correction'] == 1)) {
                        $down += $ruleSettingItem['score'] ?? 0;
                    }
                    $errorInfo['level'] = $ruleSettingItem['error_level'] - 1 ?? 0;
                    $errorInfo['error_field'] = $errorfeild[0]['field'] ?? "";
                    $errorInfo['error_name'] = $ruleSettingItem['case_type'] ?? "";
                    $errorInfo['category'] = $object[$ruleSettingItem['object']] ?? "";
                    $errorInfo['down'] = $ruleSettingItem['score'] ?? "";
                    //$errorInfo['desc'] = $ruleSettingItem['description'] ?? "";
                } else {
                    if (empty($errorInfo['is_correction'] == 1)) {
                        $down += $errorRuleData[$errorInfo['error_rule']]['down'];
                    }
                    //$down += $errorRuleData[$errorInfo['error_rule']]['down'];
                    $errorInfo['level'] = $errorRuleData[$errorInfo['error_rule']]['bmy_level'];
                    $errorInfo['error_field'] = $errorRuleData[$errorInfo['error_rule']]['auth'];
                    $errorInfo['error_name'] = $errorRuleData[$errorInfo['error_rule']]['field'];
                    $errorInfo['category'] = $errorRuleData[$errorInfo['error_rule']]['category'];
                    $errorInfo['down'] = $errorRuleData[$errorInfo['error_rule']]['down'];
                }

                $errorInfo['is_artificial'] = $errorInfo['is_artificial'] ?? 0;
                $errorInfo['rule_id'] = $errorInfo['error_rule'];
                $errorInfo['appeal_id'] = $appeal[$errorInfo['error_rule']]['id'] ?? 0;
                $errorInfo['type'] = $appeal[$errorInfo['error_rule']]['type'] ?? 0;
                $errorInfo['status'] = $appeal[$errorInfo['error_rule']]['status'] ?? 0;
                $errorInfo['reject_content'] = $appeal[$errorInfo['error_rule']]['defect_content'] ?? 0;
                $errorInfo['cate'] = 1;
                if (!empty($errorInfo['basis'])) {
                    $errorInfo['basis'] = explode(',', $errorInfo['basis']);
                } else {
                    $errorInfo['basis'] = [];
                }

                if ($errorInfo['status'] == 1) {
                    continue;
                }
                //                if ($errorInfo['error_rule'] != 1458) {
                //                    $errorInfo['desc'] = !empty($errorRuleData[$errorInfo['error_rule']]) ? $errorRuleData[$errorInfo['error_rule']]['desc'] : $errorInfo['desc'];
                //                }
                $returnData['list'][] = $errorInfo;

                if ($errorInfo['level'] === 1) {
                    // 建议
                    $returnData['jy'][] = $errorInfo;
                } else {
                    // 强制
                    $req++;
                    $returnData['qz'][] = $errorInfo;
                }
            }
        }
        $score = 100 - $down;
        if ($score >= 97) {
            $level = 0;
        } else if ($score >= 90 && $score <= 96) {
            $level = 1;
        } else if ($score >= 75 && $score <= 89) {
            $level = 2;
        } else {
            $level = 3;
        }

        $returnData['score']['score'] = $score;
        $returnData['score']['level'] = $level;
        $returnData['req'] = $req;

        if (!empty($returnData['qz'])) {
            // 按照 ABCD类 排序
            $returnData['qz'] = PublicService::sortByKey($returnData['qz'], 'category', 2);
        }
        if (!empty($returnData['jy'])) {
            // 按照 ABCD类 排序
            $returnData['jy'] = PublicService::sortByKey($returnData['jy'], 'category', 2);
        }

        return ToolsService::returnData(200, $returnData);
    }

    /**
     * 获取医院列表
     * @return array
     */
    public function getHospitalList()
    {
        $hospitalData = PatientInfo::query()
            ->where('hospital_name', '!=', '')
            ->whereNotNull('hospital_name')
            ->groupBy('hospital_name')
            ->orderBy('id')
            ->pluck('hospital_name')
            ->toArray();

        return ToolsService::returnData(200, $hospitalData);
    }

    //病案首页(医生站)主信息列表
    public function getHomeList(Request $request)
    {
        $start_time = $request->post("start_time"); //日期字符串格式
        $end_time = $request->post("end_time");
        $type = $request->post("type", 0); //
        $page = $request->post("page", 1);
        $pageSize = $request->post("page_size", 10);
        $isExport = $request->post('is_export', 0);
        if ($isExport) {
            $page = 1;
            $pageSize = 100000;
        }

        if (empty($start_time)) {
            $start_time = date('Y-01-01 00:00:00');
        } else {
            $start_time = date('Y-m-d', strtotime($start_time));
        }
        if (empty($end_time)) {
            $end_time = date('Y-12-31 00:00:00');
        } else {
            $end_time = date('Y-m-d', strtotime($end_time));
        }

        $query = PatientInfoV2::query()->select(['id', 'ZYH', 'AAA28', 'AAC01', 'AAC11N', 'AAB11N', 'AED04', 'AEE01', 'AEE02', 'AEE03', 'AEE04', 'AED02', 'AED03', 'AEE10', 'AAA01', 'AAA02C', 'AAA04', 'AAA29', 'ADA01', 'AAB07N', 'AAC04', 'AEM01C', 'AAB06C', 'score'])
            ->where('status', 1)
            ->whereBetween('AAC01', [$start_time, $end_time]);

        $department_id = $request->post("department_id");
        if (!empty($department_id)) {
            $query->where('AAB11C', $department_id);
        }
        //出院科室
        $AAC11N = $request->post("AAC11N", '');
        if (!empty($AAC11N)) {
            $query->where('AAC11N', 'like', "%$AAC11N%");
        }
        //住院号码
        $AAA28 = $request->post("AAA28", '');
        if (!empty($AAA28)) {
            $query->where('AAA28', $AAA28);
        }
        //医师姓名
        $dockter_name = $request->post('dockerter_name', '');
        if (!empty($dockter_name)) {
            $query->where('AEE01', 'like', "%" . $dockter_name . "%");
        }

        //$result = $query->select('id','ZYH','AAC01','AAC11C','AEE01','AEE02','AEE03','AEE04','AEE10','score')->toArray();
        $result = $query->orderBy('created_at', 'desc')
            ->with(['errors' => function ($query) {
                $query->where('status', 1)->select(['id', 'AAA28', 'desc', 'ICD9_NAME']);
            }])
            ->paginate($pageSize, $page)
            ->toArray();

        foreach ($result['data'] as $k => $v) {
            //病历等级计算 优良中差
            if (empty($v['score'])) {
                $result['data'][$k]['level'] = '';
            } else {
                $lev = ErrorV2::YLZC;
                foreach ($lev as $kk => $vv) {
                    if ($v['score'] >= $vv[0] && $v['score'] <= $vv[1]) {
                        $result['data'][$k]['level'] = $kk;
                    }
                }
            }
            //获取缺陷问题个数、主手术
            $result['data'][$k]['errors_num'] = count($v['errors']) ?? 0;
            $result['data'][$k]['ICD9_NAME'] = $v['errors'][0]['ICD9_NAME'] ?? '';
        }

        $data = !empty($result['data']) ? $result['data'] : [];
        $count = !empty($result['total']) ? $result['total'] : 0;

        if ($isExport) {
            $exportData = [];
            if ($type == 1) {
                $title = ['住院号码', '出院时间', '出院科室', '缺陷问题', '质控时间', '科主任', '主任(副主任)医师', '主治医师', '责任护士', '质控医生', '质控护士', '患者姓名', '性别', '年龄', '住院次数', '总费用', '主诊断', '主手术', '实际住院天数', '离院方式', '入院途径'];
                foreach ($data as $k => $v) {
                    $re = [
                        'AAA28' => $v['AAA28'] ?? '',
                        'AAC01' => $v['AAC01'] ?? '',
                        'AAB11N' => $v['AAB11N'] ?? '',
                        'errors_num' => $v['errors_num'] ?? '',
                        'AAD04' => $v['AAD04'] ?? '',
                        'AEE01' => $v['AEE01'] ?? '',
                        'AEE02' => $v['AEE02'] ?? '',
                        'AEE03' => $v['AEE03'] ?? '',
                        'AEE04' => $v['AEE04'] ?? '',
                        'AED02' => $v['AED02'] ?? '',
                        'AED03' => $v['AED03'] ?? '',
                        'AAA01' => $v['AAA01'] ?? '',
                        'AAA02C' => $v['AAA02C'] ?? '',
                        'AAA04' => $v['AAA04'] ?? '',
                        'AAA29' => $v['AAA29'] ?? '',
                        'ADA01' => $v['ADA01'] ?? '',
                        'AAB07N' => $v['AAB07N'] ?? '',
                        'ICD9_NAME' => $v['ICD9_NAME'] ?? '',
                        'AAC04' => $v['AAC04'] ?? '',
                        'AEM01C' => $v['AEM01C'] ?? '',
                        'AAB06C' => $v['AAB06C'] ?? '',
                    ];

                    $exportData[] = $re;
                }
            }
            if ($type == 2) {
                $title = ['住院号码', '出院时间', '出院科室', '科主任', '主任(副主任)医师', '主治医师', '住院医师', '病历评分', '病历等级'];
                foreach ($data as $k => $v) {
                    $re = [
                        'AAA28' => $v['AAA28'] ?? '',
                        'AAC01' => $v['AAC01'] ?? '',
                        'AAB11N' => $v['AAB11N'] ?? '',
                        'AEE01' => $v['AEE01'] ?? '',
                        'AEE02' => $v['AEE02'] ?? '',
                        'AEE03' => $v['AEE03'] ?? '',
                        'AEE04' => $v['AEE04'] ?? '',
                        'score' => $v['score'] ?? '',
                        'level' => $v['level'] ?? '',
                    ];

                    $exportData[] = $re;
                }
            }
            array_unshift($exportData, $title);
            $csv = new CsvService();
            $csv->filename = $csv->charset('缺陷问题', 'UTF-8');

            return $csv->export($exportData);
        }
        return ToolsService::returnData(200, ['count' => $count, 'list' => $data]);
    }

    //科室排名top10 + 全部科室排名
    public function department(Request $request)
    {
        $start_time = $request->post("start_time");
        $end_time = $request->post("end_time");
        $type = $request->post("type", 1);
        $isExport = $request->post("is_export", 0);
        $isPage = $request->post("isPage", 1);
        $num = 10;
        $offset = ($isPage - 1) * $num;

        ## 导出
        if ($isExport == 1) {
            $exportService = new FrontDataService();
            return $exportService->departmentExport($start_time, $end_time);
        }

        if (empty($start_time)) {
            $start_time = date('Y-01-01 00:00:00');
        } else {
            $start_time = date('Y-m-d H:i:s', strtotime($start_time));
        }
        if (empty($end_time)) {
            $end_time = date('Y-12-31 00:00:00');
        } else {
            $end_time = date('Y-m-d H:i:s', strtotime($end_time));
        }

        $hospital_name = $request->post('hospital_name', '');
        $hospital_name = !empty($hospital_name) ? $hospital_name : config('confAdmin.hospital_name');

        $model = DepartmentData::query()
            ->where('hospital_name', '=', $hospital_name)
            ->whereBetween('created_at', [$start_time, $end_time]);
        $result = $model
            ->groupBy('department_id')
            ->get([
                'department_id',
                DB::raw('sum(total_score) as total_score'),
                DB::raw('sum(total_medical) as total_medical'),
                DB::raw('sum(total_error) as total_error'),
                DB::raw('max(max_score) as max_score'),
                DB::raw('min(min_score) as min_score'),
                DB::raw('sum(total_error_medical) as total_error_medical')
            ]);
        if ($result->isEmpty()) {
            $code = 200;
            $data = ['list' => [], 'count' => 0];
            $msg = '没有数据！';
        } else {
            $result = $result->toArray();
            foreach ($result as &$item) //按department_id分组
            {
                $item['average_error'] = sprintf('%.2f', $item['total_error_medical'] / $item['total_medical']);
                $item['average_score'] = sprintf('%.2f', $item['total_score'] / $item['total_medical']);
            }
            $res = $this->sortByKey($result, 'average_error', 2, $offset, $num);
            $count = count($result);
            $names = config('dictionaries.ABAS02');
            $list = array_column($names, 'code');
            $outstanding = PatientHospitalInfo::query()
                ->whereRaw("(AAA28 in (select MED_REC_ID from patient_info where AAC01 between '$start_time' and '$end_time' and level = 0))")
                ->whereIn('AAC11C', $list)
                ->groupBy('AAC11C')
                ->get([
                    'AAC11C',
                    DB::raw('count(AAA28) as count')
                ])
                ->keyBy('AAC11C')
                ->toArray();
            foreach ($res as &$v) {
                $v['name'] = $names[$v['department_id']];
                $v['outstanding'] = isset($outstanding[$v['department_id']]) ? sprintf('%.2f', $outstanding[$names[$v['department_id']]['code']]['count'] / $v['total_medical']) : 0;
            }

            //查缺陷问题数量和已修改缺陷数量
            $departmentIds = array_column($res, 'department_id');
            //查询对应缺陷问题总数量
            $errorsNum = ErrorV2::query()->whereIn('CYKSBM', $departmentIds)->where('status', 1)->groupBy('CYKSBM')->select('CYKSBM', DB::raw('count(*) as total'))->pluck('total', 'CYKSBM')->toArray();
            //已修改数量
            $errosNum2 = ErrorV2::query()->whereIn('CYKSBM', $departmentIds)->where('status', 0)->groupBy('CYKSBM')->select('CYKSBM', DB::raw('count(*) as total'))->pluck('total', 'CYKSBM')->toArray();
            foreach ($errosNum2 as $key => $value) {
                if (isset($errorsNum[$key])) {
                    $errosNum2[$key] = $errorsNum[$key] - $value;
                }
            }
            foreach ($res as $k => $v) {
                $res[$k]['errors_num'] = $errorsNum[$v['department_id']] ?? '';
                $res[$k]['up_errors_num'] = $errosNum2[$v['department_id']] ?? '';
            }

            $code = 200;
            $data = ['list' => $res, 'count' => $count];
            $msg = '成功！';
        }
        return ToolsService::returnData($code, $data, $msg);
    }

    /**
     * 医生排名
     * @param Request $request
     * @return array|string|null
     */
    public function doctorRanking(Request $request)
    {
        $AAA28 = $request->post('AAA28', '');
        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        $isExport = $request->post('is_export', 0);
        if ($isExport == 1) {
            $page = 1;
            $pageSize = 10000;
        }

        $doctorList = config('confAdmin.doctor_list');
        $doctorList = explode(',', $doctorList);

        $piService = new ElasticsearchService('patient_info');

        $must = [];
        if (!empty($AAA28)) {
            $must[] = ['term' => ['AAA28' => $AAA28]];
        }
        $must[] = ['range' => ['home_bmy_score' => ['gte' => 0, 'lte' => 100]]];
        if ($startTime && $endTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $must[] = ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]];
        } elseif ($startTime && $endTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $must[] = ['range' => ['AAC01' => ['gte' => $startTime]]];
        } elseif ($startTime && $endTime) {
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $must[] = ['range' => ['AAC01' => ['lte' => $endTime]]];
        }

        // 员工信息
        $staff = Staff::query()->whereIn('name', $doctorList)->get()->toArray();
        foreach ($staff as $k => $s) {
            $staff[$k]['dep_name'] = '';
            if (!empty($dep[$s['ksdm']])) {
                $staff[$k]['dep_name'] = $dep[$s['ksdm']];
            }
        }
        $staff = array_column($staff, null, 'name');

        // 科室
        $depData = Department::query()->pluck('dep_name', 'dep_id')->toArray();

        $data = [];
        foreach ($doctorList as $key => $doctorName) {
            $arr = [];
            $arr['docker_name'] = $doctorName;
            $arr['code'] = $staff[$doctorName]['code'] ?? '';
            $arr['dep_name'] = '';
            $arr['dep_id'] = '';
            if (!empty($staff[$doctorName]['ksdm'])) {
                $arr['dep_name'] = $depData[$staff[$doctorName]['ksdm']] ?? $staff[$doctorName]['ksdm'];
                $arr['dep_id'] = $staff[$doctorName]['ksdm'];
            }
            $should = [
                ['match_phrase' => ['ZZYISXM' => $doctorName]],
                ['match_phrase' => ['ZYYSXM' => $doctorName]],
                ['match_phrase' => ['ZZYSXM' => $doctorName]],
                ['match_phrase' => ['KZRXM' => $doctorName]],
                ['match_phrase' => ['ZHFZRYSXM' => $doctorName]],
            ];

            $aggs = [
                'home_bmy_score' => [
                    'terms' => [
                        "field" => 'home_bmy_score',
                        "size" => 100000
                    ],
                    "aggs" => [
                        "sum_score" => [
                            "sum" => [
                                "field" => "home_bmy_score"
                            ]
                        ]
                    ]
                ]
            ];

            // 查询总数、总分
            $params = $piService->clearMust()
                ->queryByMustBatch($must)
                ->queryByShouldBatch($should)
                ->minimumShouldMatch()
                ->aggs($aggs)
                ->trackTotalHits()
                ->paginate(1, 1)
                ->getParams();
            $res = app('es')->search($params);
            $patientInfoData = $piService->getDataByEs($res);
            if (empty($patientInfoData[1])) {
                continue;
            }

            // 总数
            $arr['bl_sum'] = $patientInfoData[1];

            // 总分
            $arr['sum_score'] = $patientInfoData[1] * 100;

            // 总得分
            $arr['df_score'] = 0;
            if (!empty($patientInfoData[2])) {
                foreach ($patientInfoData[2]['home_bmy_score']['buckets'] as $value) {
                    $arr['df_score'] += $value['sum_score']['value'];
                }
            }

            // 总扣分
            $arr['kf_score'] = $arr['sum_score'] - $arr['df_score'];

            // 占比
            $arr['avg_score'] = sprintf('%.2f', ($arr['df_score'] / $arr['bl_sum']));

            // 查询缺陷总数
            $must1 = $must;
            $must1[0] = ['range' => ['home_bmy_score' => ['gte' => 0, 'lt' => 100]]];
            $params = $piService->clearMust()
                ->queryByMustBatch($must1)
                ->queryByShouldBatch($should)
                ->minimumShouldMatch()
                ->trackTotalHits()
                ->paginate(1, 1)
                ->getParams();
            $res = app('es')->search($params);
            $patientInfoData = $piService->getDataByEs($res);

            // 缺陷总数
            $arr['qx_sum'] = $patientInfoData[1];

            $data[] = $arr;
        }

        $data = PublicService::sortByKey($data, 'avg_score');

        if ($isExport == 1) {
            $exportData[] = ['排名', '医生姓名', '医生工号', '医生科室', '病历总数', '缺陷病历总数', '总扣分', '平均得分'];
            foreach ($data as $key => $value) {
                $exportData[] = [
                    'id' => ($key + 1),
                    'docker_name' => $value['docker_name'],
                    'code' => $value['code'],
                    'dep_name' => $value['dep_name'],
                    'bl_sum' => $value['bl_sum'],
                    'qx_sum' => $value['qx_sum'],
                    'kf_score' => $value['kf_score'],
                    'avg_score' => $value['avg_score'],
                ];
            }
            $csv = new CsvService();
            $csv->filename = $csv->charset('医生排名', 'UTF-8');

            return $csv->export($exportData);
        }

        $page = ($page - 1) * $pageSize;
        $returnData = [];
        foreach ($data as $key => $value) {
            if ($key >= $page && count($returnData) < $pageSize) {
                $returnData[] = $value;
            }
        }

        return ToolsService::returnData(200, ['count' => count($data), 'list' => $returnData]);
    }

    /**
     * 病案首页(医生站)医师排名-缺陷字段
     * @param Request $request
     * @return array|string|null
     */
    public function doctorErrorRanking(Request $request)
    {
        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');
        $AAC11C = $request->post('AAC11C', '');
        $doctorList = $request->post('doctor_name', '');
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        $isExport = $request->post('is_export', 0);
        if ($isExport == 1) {
            $page = 1;
            $pageSize = 100000;
        }
        $sort = $request->post('sort', '');
        if (empty($sort)) {
            $sort = ['AAC01', 'desc'];
        }

        if (empty($doctorList)) {
            $doctorList = config('confAdmin.doctor_list');
            $doctorList = explode(',', $doctorList);
        }

        $query = HomeQuality::query()
            ->leftJoin('patient_info as pi', 'home_quality.ZYH', '=', 'pi.MED_REC_ID')
            ->leftJoin('patient_add as add', 'home_quality.ZYH', '=', 'add.AAA28')
            ->where('home_quality.is_del', '=', 0)
            ->where(function ($query) use ($doctorList) {
                $query->whereIn('add.KZRXM', $doctorList)
                    ->orWhereIn('add.ZHFZRYSXM', $doctorList)
                    ->orWhereIn('add.ZZYSXM', $doctorList)
                    ->orWhereIn('add.ZYYSXM', $doctorList)
                    ->orWhereIn('add.ZZYISXM', $doctorList);
            });
        if ($startTime && $endTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $query->whereBetween('home_quality.AAC01', [$startTime, $endTime]);
        } elseif ($startTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $query->where('home_quality.AAC01', '>=', $startTime);
        } elseif ($endTime) {
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $query->where('home_quality.AAC01', '<=', $endTime);
        }
        if ($AAC11C) {
            $query->where('home_quality.AAC11C', '=', $AAC11C);
        }

        $field = [
            'home_quality.AAA28',
            'home_quality.ZYH',
            'home_quality.AAC01',
            'home_quality.error_rule',
            'add.KZRXM',
            'add.ZHFZRYSXM',
            'add.ZZYSXM',
            'add.ZYYSXM',
            'add.ZZYISXM',
            'pi.home_bmy_score'
        ];
        $data = $query->orderBy($sort[0], $sort[1])->paginate($pageSize, $field, 'page', $page)->toArray();

        $returnData = [];
        if (!empty($data['data'])) {
            $errorRuleData = ErrorRule::query()->get()->toArray();
            $errorRuleData = array_column($errorRuleData, null, 'id');
            foreach ($data['data'] as $value) {
                $arr = [
                    'AAA28' => $value['AAA28'] . "\t",
                    'ZYH' => $value['ZYH'],
                    'AAC01' => !empty($value['AAC01']) ? $value['AAC01'] . "\t" : '',
                    'KZRXM' => $value['KZRXM'] ?? '',
                    'ZHFZRYSXM' => $value['ZHFZRYSXM'] ?? '',
                    'ZZYSXM' => $value['ZZYSXM'] ?? '',
                    'ZYYSXM' => $value['ZYYSXM'] ?? '',
                    'ZZYISXM' => $value['ZZYISXM'] ?? '',
                    'home_bmy_score' => $value['home_bmy_score'] ?? '',
                    'desc' => $errorRuleData[$value['error_rule']]['desc'],
                    'down' => $errorRuleData[$value['error_rule']]['down'],
                ];

                if ($isExport == 1) {
                    unset($arr['ZYH']);
                }

                $returnData[] = $arr;
            }
        }

        if ($isExport == 1) {
            $title = ['住院号码', '出院时间', '科主任', '主任(副主任)医师', '主治医生', '住院医生', '医疗组长', '病历评分', '缺陷描述', '扣分'];
            array_unshift($returnData, $title);

            $csv = new CsvService();
            $csv->filename = $csv->charset('医生排名', 'UTF-8');

            return $csv->export($returnData);
        }

        return ToolsService::returnData(200, ['count' => $data['total'], 'list' => $returnData]);
    }
}
