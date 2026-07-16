<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Service\FrontDataService;
use App\Model\Appeal;
use App\Model\BaBrsy;
use App\Model\CaseRule;
use App\Model\Dep;
use App\Model\Department;
use App\Model\DepartmentData;
use App\Model\ErrorHomeBmy;
use App\Model\ErrorRule;
use App\Model\ErrorV2;
use App\Model\HomeQuality;
use App\Model\Icu;
use App\Model\MainDiagnosis;
use App\Model\MainOperation;
use App\Model\OtherDiagnosis;
use App\Model\PatientAdd;
use App\Model\PatientAddressInfo;
use App\Model\PatientContactsInfo;
use App\Model\PatientCostInfo;
use App\Model\PatientDoctorInfo;
use App\Model\PatientHospitalInfo;
use App\Model\PatientInfo;
use App\Model\PatientInfoCostV2;
use App\Model\PatientInfoDiagnosisV2;
use App\Model\PatientInfoFeeDetailedV2;
use App\Model\PatientInfoIcuV2;
use App\Model\PatientInfoOperationV2;
use App\Model\PatientInfoV2;
use App\Model\PatientMedicalInfo;
use App\Model\PatientOtherInfo;
use App\Model\PatientWorkInfo;
use App\Model\RuleSetting;
use App\Model\RuleSettingOther;
use App\Model\RuleWordMap;
use App\Model\SecondaryOperation;
use App\Model\Staff;
use App\Model\TableDictSY;
use App\Model\User;
use App\Model\YqDetails;
use App\Services\CsvService;
use App\Services\ElasticsearchService;
use App\Services\HomeQualityService;
use App\Services\PublicService;
use App\Services\ToolsService;
use App\Services\UserService;
use App\Services\WordReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToArray;

class HomeSzQualityController extends Controller
{

    /**
     * 获取首页质控(编码员)
     */
    public function getBmyIndexDoctorOptions(request $request)
    {
        $type_id = $request->post('sf_type', '');
        if (!is_numeric($type_id))
            ToolsService::returnData(200, []);
        $data = Staff::getDoctorStaffType($type_id)['data'];
        return ToolsService::returnData(200, $data);
    }

    /**
     * 质控列表(编码员)获取搜索options
     */
    public function bmyGetSearchOptions()
    {
        //缺陷详情options
        $qxOptions = [];
        //编码员
        $qxOptions['bmyArray'] = Staff::query()->where('status', 0)->get(['id', 'name'])->toArray();

        $yqArray = Department::getDepartmentOptions();
        $ksArray = Department::getDepartmentOptions(2);
        $bqArray = Department::getDepartmentOptions(3);
        $data = [];
        $data['yqArray'] = $yqArray;
        $data['depArray'] = $ksArray;
        $data['bqArray'] = $bqArray;
        $data['start_time'] = date("Y") . '-01-01';
        $data['end_time'] = date("Y-m-d");
        $data['qxSearchOptions'] = $qxOptions;
        return ToolsService::returnData(200, $data);
    }

    /**
     * 统计分析
     * @param Request $request
     * @return array
     */
    public function qualityStatistics(Request $request)
    {
        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');

        $where = [];
        $where[] = ['status', '=', 0];
        if (!empty($startTime) && !empty($endTime)) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $where[] = ['AAC01', '>=', $startTime];
            $where[] = ['AAC01', '<=', $endTime];
        } elseif (!empty($startTime)) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $where[] = ['AAC01', '>=', $startTime];
        } elseif (!empty($endTime)) {
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $where[] = ['AAC01', '<=', $endTime];
        }

        // 病历总数
        $returnData['blCount'] = PatientInfoV2::query()->where($where)->count();
        // 缺陷数
        $returnData['qxCount'] = PatientInfoV2::query()->where($where)->whereBetween('score', [0, 99.9])->count();
        // 平均得分
        $sumScore = PatientInfoV2::query()->where($where)->sum('score');
        $returnData['averageScore'] = sprintf('%.2f', ($sumScore / $returnData['blCount']));

        // 优良中差数
        foreach (ErrorRule::YLZC as $key => $value) {
            $returnData[$key . '_count'] = PatientInfoV2::query()->where($where)->whereBetween('score', $value)->count();
        }
        // 优良中差占比
        $returnData['you_ratio'] = sprintf('%.2f', ($returnData['you_count'] / $returnData['blCount']) * 100);
        $returnData['liang_ratio'] = sprintf('%.2f', ($returnData['liang_count'] / $returnData['blCount']) * 100);
        $returnData['zhong_ratio'] = sprintf('%.2f', ($returnData['zhong_count'] / $returnData['blCount']) * 100);
        $returnData['cha_ratio'] = sprintf('%.2f', ($returnData['cha_count'] / $returnData['blCount']) * 100);
        //缺陷数
        //$returnData['errors'] = ErrorV2::query()->where('status',0)->count();

        // 日均
        //        $returnData['dayAvg'] = 0;
        //        if (!empty($startTime) && !empty($endTime)) {
        //            $dayCount = ceil((strtotime($endTime)-strtotime($startTime))/(3600*24));
        //            if ($returnData['blCount'] && $dayCount) {
        //                $returnData['dayAvg'] = (int)ceil($returnData['blCount']/$dayCount);
        //            }
        //        }

        // 缺陷占比
        //        $returnData['averageError'] = sprintf('%.2f', ($returnData['qxCount']/$returnData['blCount']) * 100);

        // 最低得分
        //        $returnData['minScore'] = PatientInfoV2::query()->where($where)->orderBy('score')->value('score');

        // 缺陷分类统计
        //        $ruleIdList = ErrorRule::query()->where('status','=',0)->get(['id','type'])->toArray();
        //        $jbRule = []; $zlRule = []; $fyRule = [];
        //        foreach ($ruleIdList as $value) {
        //            if ($value['type'] == 1) {
        //                $zlRule[] = $value['id'];
        //            } elseif ($value['type'] == 2) {
        //                $fyRule[] = $value['id'];
        //            } else {
        //                $fyRule[] = $value['id'];
        //            }
        //        }
        //
        //        $returnData['jbxx'] = ErrorV2::query()->where($where)->where('error_rule',$jbRule)->count();
        //        $returnData['zlxx'] = ErrorV2::query()->where($where)->where('error_rule',$zlRule)->count();
        //        $returnData['fyxx'] = ErrorV2::query()->where($where)->where('error_rule',$fyRule)->count();
        return ToolsService::returnData(200, $returnData);
    }

    /**
     * 生成病历质控分析报告（Word文档）
     * @param Request $request
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     */
    public function generateQualityReport(Request $request)
    {
        try {
            $startTime = $request->post('start_time', '');
            $endTime = $request->post('end_time', '');

            // 获取统计数据
            $statisticsData = $this->qualityStatistics($request);
            if (isset($statisticsData['data'])) {
                $statisticsData = $statisticsData['data'];
            }

            // 生成Word报告
            $wordReportService = new WordReportService();
            $outputPath = $wordReportService->generateQualityReport(
                $statisticsData,
                $startTime,
                $endTime
            );

            // 返回文件下载
            $filename = '病历质控分析报告_' . date('YmdHis') . '.docx';
            return response()->download($outputPath, $filename)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('生成病历质控分析报告失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ToolsService::returnData(500, [], '生成报告失败: ' . $e->getMessage());
        }
    }

    /**
     * 缺陷问题
     * @param Request $request
     * @return array|string|null
     */
    public function errorData(Request $request)
    {
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        $isExport = $request->post('is_export', 0);
        if ($isExport) {
            $page = 1;
            $pageSize = 1000;
        }
        // 获取当前登录用户所属科室
        $userDep = UserService::getCurrentUserDep($request);

        // 判断$userDep是否为空，如果为空，则直接返回
        if (empty($userDep)) {
            return ToolsService::returnData(200, ['list' => [], 'count' => 0]);
        }

        // 查询质控规则
        $query = ErrorRule::query()
            ->where('status', '=', 0)
            ->where('node', 'like', "%运行%");
        // 缺陷分级
        $level = $request->post("level", '');
        $levelCode = null;
        if ($level !== '' && $level !== null) {
            $levelCode = ErrorRule::RULE_LEVEL_CODE[$level];
            $query->where('level', '=', $levelCode);
        }
        // 缺陷归类
        $type = $request->post("type", '');
        $typeCode = null;
        $typeName = '';
        if ($type !== '' && $type !== null) {
            $typeCode = ErrorRule::RULE_TYPE_CODE[$type];
            $typeName = ErrorRule::RULE_TYPE[$typeCode] ?? '';
            $query->where('type', '=', $typeCode);
        }
        // 缺陷字段
        $field = $request->post("field", '');
        if ($field !== '' && $field !== null) {
            $query->where('field', 'like', "%" . $field . "%");
        }
        // 缺陷描述
        $desc = $request->post("desc", '');
        if ($desc !== '' && $desc !== null) {
            $query->where('desc', 'like', "%" . $desc . "%");
        }

        $errorRule = $query->get()->toArray();
        $errorRuleData = array_column($errorRule, null, 'id');
        $ruleIdList = array_column($errorRule, 'id');

        //自定义规则列表
        $ruleSettingQuery = RuleSetting::query()
            ->where('status', '=', 1)
            ->where('rule_type', '=', '首页规则');
        if ($levelCode !== null) {
            $ruleSettingQuery->where('error_level', '=', (string)($levelCode + 1));
        }
        if ($typeName !== '') {
            $ruleSettingQuery->whereJsonContains('department', $typeName);
        }
        if ($field !== '' && $field !== null) {
            $ruleSettingQuery->where('case_type', 'like', "%" . $field . "%");
        }
        if ($desc !== '' && $desc !== null) {
            $ruleSettingQuery->where('description', 'like', "%" . $desc . "%");
        }
        $ruleSetting = $ruleSettingQuery->get()->toArray();
        $ruleSettingData = array_column($ruleSetting, null, 'id');
        //统一+100000
        $ruleSettingData = array_map(function ($item) {
            $item['id'] = $item['id'] + 1000000;
            return $item;
        }, $ruleSettingData);
        $ruleIdList = array_merge($ruleIdList, array_column($ruleSettingData, 'id'));
        //Log::info('errorData dep_id is null ruleIdList:'.json_encode($ruleIdList));

        // 医院
        $hospital_name = $request->post('hospital_name', '');
        $hospital_name = !empty($hospital_name) ? $hospital_name : config('confAdmin.hospital_name');
        //Log::info('errorData dep_id is null hospital_name:'.$hospital_name);
        //        $hospital_name = '青州市人民医院';

        // 入院 开始时间、结束时间
        $startTime = $request->post("start_time", '');
        $endTime = $request->post("end_time", '');
        $startTime = !empty($startTime) ? date('Y-m-d', strtotime($startTime)) . ' 00:00:00' : '';
        $endTime = !empty($endTime) ? date('Y-m-d', strtotime($endTime)) . ' 23:59:59' : '';

        // 质控 开始时间、结束时间
        $zkStartTime = $request->post("zk_start_time", '');
        $zkEndTime = $request->post("zk_end_time", '');
        $zkStartTime = !empty($zkStartTime) ? date('Y-m-d', strtotime($zkStartTime)) . ' 00:00:00' : '';
        $zkEndTime = !empty($zkEndTime) ? date('Y-m-d', strtotime($zkEndTime)) . ' 23:59:59' : '';
        //Log::info('errorData dep_id is null zkStartTime:'.$zkStartTime.' zkEndTime:'.$zkEndTime);

        //Log::info('errorData dep_id is null ryStartTime:'.$ryStartTime.' ryEndTime:'.$ryEndTime);

        // 查询质控结果
        $query = ErrorV2::query()
            ->join("patient_info_v2", "patient_info_v2.ZYH", "=", "error_v2.ZYH")
            ->Join("ZY_BRRY", "ZY_BRRY.ZYH", "=", "error_v2.ZYH")
            ->whereIn('error_rule', $ruleIdList)
            ->where('error_v2.status', '=', 0);

        if (!empty($zkStartTime) && !empty($zkEndTime)) {
            $query->whereBetween('error_v2.created_at', [$zkStartTime, $zkEndTime]);
        }

        if (is_array($userDep)) {
            $query->whereIn('ZY_BRRY.BRKS', $userDep);
        }

        if ($startTime && $endTime) {
            $query->whereBetween('ZY_BRRY.AAC01', [$startTime, $endTime]);
        } elseif ($startTime) {
            $query->where('ZY_BRRY.AAC01', '>=', $startTime);
        } elseif ($endTime) {
            $query->where('ZY_BRRY.AAC01', '<=', $endTime);
        }
        //入院时间
        $ryStartTime = $request->post('ry_start_time', '');
        $ryEndTime = $request->post('ly_end_time', '');
        if (!empty($ryStartTime) && !empty($ryEndTime)) {
            $ryStartTime = date('Y-m-d', strtotime($ryStartTime));
            $ryEndTime = date('Y-m-d', strtotime($ryEndTime));
            $query->whereBetween('ZY_BRRY.AAB01', [$ryStartTime, $ryEndTime]);
            //$query->where(DB::raw('DATE(AAB01)'),'>=',$ryStartTime);
            //$query->where(DB::raw('DATE(AAC01)'),'<=',$lyEndTime);
        }
        //出院科室
        $cykb = $request->post('cykb', '');
        if ($cykb != '' && $cykb != null) {
            $depId = Department::query()->where('dep_name', '=', $cykb)->value('dep_id');
            $query->where('ZY_BRRY.BRKS', $depId);
        }

        $zyhm = $request->post('zyhm', '');
        if ($zyhm != '' && $zyhm != null) {
            $query->where('ZY_BRRY.AAA28', '=', $zyhm);
        }

        $isChuyuan = $request->post('isChuyuan', 0);
        if ($isChuyuan) {
            if ($isChuyuan == 1) {
                $query->whereNull('ZY_BRRY.AAC01');
            }

            if ($isChuyuan == 2) {
                $query->whereNotNull('ZY_BRRY.AAC01');
            }
        }

        $errorData = $query->groupBy('error_rule')->get(DB::raw("count(1) as count,error_rule"))->toArray();
        Log::info('errorData:' . $query->toSql(), $query->getBindings());
        if (empty($errorData)) {
            return ToolsService::returnData(200, ['list' => [], 'count' => 0]);
        }

        array_multisort(array_column($errorData, 'count'), SORT_DESC, $errorData);
        $dataChunk = array_chunk($errorData, $pageSize);
        $data = $dataChunk[$page - 1];
        $count = count($errorData);

        $exportData = [
            ['缺陷字段', '缺陷描述', '缺陷数量', '缺陷分级', '缺陷归类']
        ];
        $returnData = [];
        foreach ($data as $value) {

            $level = $errorRuleData[$value['error_rule']]['level'] ?? '';

            $type = $errorRuleData[$value['error_rule']]['type'] ?? '';
            $type = ErrorRule::RULE_TYPE[$type] ?? '';
            $auth = $errorRuleData[$value['error_rule']]['auth'] ?? '';
            $field = $errorRuleData[$value['error_rule']]['field'] ?? '';
            $desc = $errorRuleData[$value['error_rule']]['desc'] ?? '';
            if ($value['error_rule'] > 1000000) {
                $level = $ruleSettingData[$value['error_rule'] - 1000000]['error_level'] - 1 ?? 0;
                $field = $ruleSettingData[$value['error_rule'] - 1000000]['case_type'] ?? '';
                $errorfeild = TableDictSY::query()->where('field_name', '=', $field)->get()->ToArray();
                $auth = $errorfeild[0]['field'] ?? "";
                $desc = $ruleSettingData[$value['error_rule'] - 1000000]['description'] ?? '';
                $type = $ruleSettingData[$value['error_rule'] - 1000000]['department'] ?? '';
                //去除'["'和'"]'
                $type = str_replace('["', '', $type);
                $type = str_replace('"]', '', $type);
            }
            $level = ErrorRule::RULE_LEVEL[$level] ?? '';
            $returnData[] = [
                'error_rule' => $value['error_rule'],
                'count' => $value['count'],
                'auth' => $auth,
                'field' => $field,
                'level' => $level,
                'type' => $type,
                'desc' => $desc,
            ];

            $exportData[] = [
                'field' => $field,
                'desc' => $desc,
                'count' => $value['count'],
                'level' => $level,
                'type' => $type,
            ];
        }

        if ($isExport) {
            $csv = new CsvService();
            $csv->filename = $csv->charset('缺陷问题', 'UTF-8');

            return $csv->export($exportData, false);
        }

        return ToolsService::returnData(200, ['list' => $returnData, 'count' => $count]);
    }


    /**
     * 缺陷问题详情列表  病案首页
     * @param Request $request
     * @return array|string|null
     */
    public function errorDetailsList(Request $request)
    {
        $ruleId = $request->post('error_rule', '');
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        $hospital_name = $request->post('hospital_name', '');
        $hospital_name = !empty($hospital_name) ? $hospital_name : config('confAdmin.hospital_name');
        $isExport = $request->post('is_export', 0);
        if ($isExport) {
            $page = 1;
            $pageSize = 1000;
        }

        // 获取当前登录用户所属科室
        $depIds = UserService::getCurrentUserDep($request);

        if (empty($depIds)) {
            return ToolsService::returnData(200, ['list' => [], 'count' => 0]);
        }

        if (empty($ruleId)) {
            return ToolsService::returnData(4001, [], '参数错误');
        }

        $errorRule = ErrorRule::query()
            ->where('status', '=', 0)
            ->where('id', '=', $ruleId)
            ->first();

        if (empty($errorRule)) {
            if ($ruleId > 1000000) {
                $ruleSetting = RuleSetting::query()->where('status', '=', 1)->where('rule_type', '=', '首页规则')->where('id', '=', $ruleId - 1000000)->first();
                if (empty($ruleSetting)) {
                    return ToolsService::returnData(4001, [], '规则不存在');
                }
                $errorRule = $ruleSetting->first();
            } else {
                return ToolsService::returnData(4001, [], '规则不存在');
            }
        }

        $errorRuleInfo = $errorRule->toArray();

        // 优化：LEFT JOIN Department 表，一次性获取科室名称
        $query = ErrorV2::query()
            ->join("patient_info_v2", "patient_info_v2.ZYH", "=", "error_v2.ZYH")
            ->join("ZY_BRRY", "ZY_BRRY.ZYH", "=", "error_v2.ZYH")
            ->leftJoin("department", "department.dep_id", "=", "ZY_BRRY.BRKS")
            ->where('error_v2.status', '=', 0)
            ->where('error_rule', '=', $ruleId);

        if (is_array($depIds)) {
            $query->whereIn('ZY_BRRY.BRKS', $depIds);
        }

        // 住院号码
        $AAA28 = $request->post('AAA28', '');
        if (!empty($AAA28)) {
            $query->where('ZY_BRRY.AAA28', '=', $AAA28);
        }

        // 出院科室
        $CYKB = $request->post('AAC11N', '');
        if (!empty($CYKB)) {
            $depId = Department::query()->where('dep_name', '=', $CYKB)->value('dep_id');
            $query->where('ZY_BRRY.BRKS', $depId);
        }

        // 出院 开始时间、结束时间
        $startTime = $request->post("start_time", '');
        $endTime = $request->post("end_time", '');
        if ($startTime && $endTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $query->whereBetween('ZY_BRRY.AAC01', [$startTime, $endTime]);
        } elseif ($startTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $query->where('ZY_BRRY.AAC01', '>=', $startTime);
        } elseif ($endTime) {
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $query->where('ZY_BRRY.AAC01', '<=', $endTime);
        }

        // 质控 开始时间、结束时间
        $zkStartTime = $request->post("zk_start_time", '');
        $zkEndTime = $request->post("zk_end_time", '');
        $zkStartTime = !empty($zkStartTime) ? date('Y-m-d', strtotime($zkStartTime)) . ' 00:00:00' : '2020-01-01 00:00:00';
        $zkEndTime = !empty($zkEndTime) ? date('Y-m-d', strtotime($zkEndTime)) . ' 23:59:59' : '2099-12-31 23:59:59';
        $query->whereBetween('error_v2.created_at', [$zkStartTime, $zkEndTime]);

        //入院时间
        $ryStartTime = $request->post('ry_start_time', '');
        $ryEndTime = $request->post('ly_end_time', '');
        if (!empty($ryStartTime) && !empty($ryEndTime)) {
            $ryStartTime = date('Y-m-d', strtotime($ryStartTime));
            $ryEndTime = date('Y-m-d', strtotime($ryEndTime));
            $query->whereBetween('ZY_BRRY.AAB01', [$ryStartTime, $ryEndTime]);
        }

        // 缺陷描述
        $desc = $request->post("desc", '');
        if ($desc !== '' && $desc !== null) {
            $query->where('error_v2.desc', 'like', "%" . $desc . "%");
        }

        // 优化：只查询需要的字段，并包含 department.dep_name
        $errorData = $query->paginate(
            $pageSize,
            [
                'error_v2.*',
                'ZY_BRRY.AAA28',
                'ZY_BRRY.BRKS',
                'ZY_BRRY.ZZYSMC',
                'department.dep_name as dep_name'  // 优化：一次性获取科室名称
            ],
            'page',
            $page
        )->toArray();
        Log::info('errorData:' . $query->toSql(), $query->getBindings());

        $data = !empty($errorData['data']) ? $errorData['data'] : [];
        $count = !empty($errorData['total']) ? $errorData['total'] : 0;

        // 优化：批量查询 rectify 状态，避免 N+1 问题
        $aaa28List = array_column($data, 'AAA28');
        $rectifyMap = [];
        if (!empty($aaa28List)) {
            $rectifyList = ErrorV2::query()
                ->where('status', 0)
                ->whereIn('AAA28', $aaa28List)
                ->where('error_rule', $ruleId)
                ->pluck('AAA28')
                ->toArray();
            $rectifyMap = array_flip($rectifyList);
        }

        $exportData = [
            ['缺陷字段', '缺陷描述', '住院号码', '姓名', '出院时间', '出院科室', '编码员', '住院医师', '主要诊断名称', '主要诊断编码', '主要手术名称', '主要手术编码', '缺陷分级', '缺陷归类']
        ];

        $returnData = [];
        $type = ErrorRule::RULE_TYPE[$errorRuleInfo['type']] ?? '';
        $ruleSetting = RuleSetting::query()->where('status', '=', 1)->where('rule_type', '=', '首页规则')->get()->toArray();
        $ruleSettingData = array_column($ruleSetting, null, 'id');
        $ruleSettingData = array_map(function ($item) {
            $item['id'] = $item['id'] + 1000000;
            return $item;
        }, $ruleSettingData);
        if ($ruleId > 1000000) {
            $errorRuleInfo['level'] = $ruleSettingData[$ruleId - 1000000]['error_level'] - 1 ?? 0;
            $errorRuleInfo['type'] = $ruleSettingData[$ruleId - 1000000]['department'] ?? '';
        }
        $level = ErrorRule::RULE_LEVEL[$errorRuleInfo['level']] ?? '';


        foreach ($data as $value) {
            // 优化：直接使用 JOIN 查询得到的 dep_name，不再循环查询
            $cykb = $value['dep_name'] ?? $value['BRKS'];

            // 优化：使用批量查询的结果判断 rectify 状态
            $rectify = '';
            if ($value['status'] == 1) {
                $rectify = isset($rectifyMap[$value['AAA28']]) ? '未改' : '已改';
            }

            $auth = $errorRuleInfo['auth'] ?? '';
            $field = $errorRuleInfo['field'] ?? '';
            $desc = $errorRuleInfo['desc'] ?? '';
            $type = $errorRuleInfo['type'] ? ErrorRule::RULE_TYPE[$errorRuleInfo['type']] : '';//如果不为空，映射为0 基本信息 1诊疗信息 2费用信息
            $AAA01 = $value['AAA01'] ?? '';
            $AAC01 = $value['AAC01'] ?? '';
            $ICD10_NAME = $value['ICD10_NAME'] ?? '';
            $ICD10_ID1 = $value['ICD10_ID1'] ?? '';
            $ICD9_NAME = $value['ICD9_NAME'] ?? '';
            $ICD9_ID1 = $value['ICD9_ID1'] ?? '';

            if ($value['error_rule'] > 1000000) {
                //$level = $ruleSettingData[$value['error_rule']-1000000]['error_level'] - 1 ?? 0;
                $field = $ruleSettingData[$value['error_rule'] - 1000000]['case_type'] ?? '';
                $errorfeild = TableDictSY::query()->where('field_name', '=', $field)->get()->ToArray();
                $auth = $errorfeild[0]['field'] ?? "";
                $desc = $ruleSettingData[$value['error_rule'] - 1000000]['description'] ?? '';
                $type = $ruleSettingData[$value['error_rule'] - 1000000]['department'] ?? '';
                //去除'["'和'"]'
                $type = str_replace('["', '', $type);
                $type = str_replace('"]', '', $type);
                $patientInfo = PatientInfoV2::query()->where('patient_info_v2.ZYH', '=', $value['ZYH'])->get()->toArray();
                $AAA01 = $patientInfo[0]['AAA01'] ?? '';
                $AAC01 = $patientInfo[0]['AAC01'] ?? '';
                $mainDiagnosis = PatientInfoDiagnosisV2::query()->where('patient_info_diagnosis_v2.ZYH', '=', $value['ZYH'])->where('patient_info_diagnosis_v2.type', '=', 1)->get()->toArray();
                $mainOperation = PatientInfoOperationV2::query()->where('patient_info_operation_v2.ZYH', '=', $value['ZYH'])->where('patient_info_operation_v2.type', '=', 1)->get()->toArray();
                $ICD10_NAME = $mainDiagnosis[0]['ICD10_NAME'] ?? '';
                $ICD10_ID1 = $mainDiagnosis[0]['ICD10_ID1'] ?? '';
                $ICD9_NAME = $mainOperation[0]['ICD9_NAME'] ?? '';
                $ICD9_ID1 = $mainOperation[0]['ICD9_ID1'] ?? '';
            }
            $returnData[] = [
                'error_rule' => $value['error_rule'],
                'auth' => $auth,
                'field' => $field,
                'ZYH' => $value['ZYH'] ?? '',
                'AAA28' => $value['AAA28'] ?? '',
                'AAA01' => $AAA01,
                'AAC01' => $AAC01,
                'AAC11N' => $cykb,
                'level' => $level,
                'type' => $type,
                'desc' => $desc,
                'coder_name' => $value['coder_name'] ?? '',
                'ZYYS' => $value['ZZYSMC'] ?? '',
                'ICD10_NAME' => $ICD10_NAME,
                'ICD10_ID1' => $ICD10_ID1,
                'ICD9_NAME' => $ICD9_NAME,
                'ICD9_ID1' => $ICD9_ID1,
                'rectify' => $rectify,
            ];

            $exportData[] = [
                'field' => $field,
                'desc' => $desc,
                'AAA28' => $value['AAA28'] ?? '',
                'AAA01' => $AAA01,
                'AAC01' => $AAC01,
                'AAC11N' => $cykb,
                'coder_name' => $value['coder_name'] ?? '',
                'ZYYS' => $value['ZZYSMC'] ?? '',
                'ICD10_NAME' => $ICD10_NAME,
                'ICD10_ID1' => $ICD10_ID1,
                'ICD9_NAME' => $ICD9_NAME,
                'ICD9_ID1' => $ICD9_ID1,
                'level' => $level,
                'type' => $type,
            ];
        }

        if ($isExport) {
            $csv = new CsvService();
            $csv->filename = $csv->charset('缺陷问题', 'UTF-8');

            return $csv->export($exportData, false);
        }

        return ToolsService::returnData(200, ['list' => $returnData, 'count' => $count]);
    }


    /* public function errorDetailsList(Request $request)
    {
        $ruleId = $request->post('error_rule', '');
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        $hospital_name = $request->post('hospital_name', '');
        $hospital_name = !empty($hospital_name) ? $hospital_name : config('confAdmin.hospital_name');
        $isExport = $request->post('is_export', 0);
        if ($isExport) {
            $page = 1;
            $pageSize = 1000;
        }


        // 获取当前登录用户所属科室
        $depIds = UserService::getCurrentUserDep($request);

        if (empty($depIds)) {
            return ToolsService::returnData(200, ['list' => [], 'count' => 0]);
        }

        if (empty($ruleId)) {
            return ToolsService::returnData(4001, [], '参数错误');
        }

        $errorRule = ErrorRule::query()
            ->where('status', '=', 0)
            ->where('id', '=', $ruleId)
            ->first();

        $errorRuleInfo = $errorRule->toArray();


        $query = ErrorV2::query()
            ->join("patient_info_v2", "patient_info_v2.ZYH", "=", "error_v2.ZYH")
            ->join("ZY_BRRY", "ZY_BRRY.ZYH", "=", "error_v2.ZYH")
            ->where('error_v2.status', '=', 0)
            ->where('error_rule', '=', $ruleId);
        //->where('status', '=', 0);
        if (is_array($depIds)) {
            $query->whereIn('ZY_BRRY.BRKS', $depIds);
        }

        // 住院号码
        $AAA28 = $request->post('AAA28', '');
        if (!empty($AAA28)) {
            $query->where('ZY_BRRY.AAA28', '=', $AAA28);
        }
        // 出院科室
        $CYKB = $request->post('AAC11N', '');
        if (!empty($CYKB)) {
            $depId = Department::query()->where('dep_name', '=', $CYKB)->value('dep_id');
            $query->where('ZY_BRRY.BRKS', $depId);
        }

        // 出院 开始时间、结束时间
        $startTime = $request->post("start_time", '');
        $endTime = $request->post("end_time", '');
        if ($startTime && $endTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $query->whereBetween('ZY_BRRY.AAC01', [$startTime, $endTime]);
        } elseif ($startTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $query->where('ZY_BRRY.AAC01', '>=', $startTime);
        } elseif ($endTime) {
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $query->where('ZY_BRRY.AAC01', '<=', $endTime);
        }

        // 质控 开始时间、结束时间
        $zkStartTime = $request->post("zk_start_time", '');
        $zkEndTime = $request->post("zk_end_time", '');
        $zkStartTime = !empty($zkStartTime) ? date('Y-m-d', strtotime($zkStartTime)) . ' 00:00:00' : '2020-01-01 00:00:00';
        $zkEndTime = !empty($zkEndTime) ? date('Y-m-d', strtotime($zkEndTime)) . ' 23:59:59' : '2099-12-31 23:59:59';
        $query->whereBetween('error_v2.created_at', [$zkStartTime, $zkEndTime]);

        //入院时间
        $ryStartTime = $request->post('ry_start_time', '');
        $ryEndTime = $request->post('ly_end_time', '');
        if (!empty($ryStartTime) && !empty($ryEndTime)) {
            $ryStartTime = date('Y-m-d', strtotime($ryStartTime));
            $ryEndTime = date('Y-m-d', strtotime($ryEndTime));
            $query->whereBetween('ZY_BRRY.AAB01', [$ryStartTime, $ryEndTime]);
            //$query->where(DB::raw('DATE(AAB01)'),'>=',$ryStartTime);
            //$query->where(DB::raw('DATE(AAC01)'),'<=',$lyEndTime);
        }

        // 缺陷描述
        $desc = $request->post("desc", '');
        if ($desc !== '' && $desc !== null) {
            $query->where('error_v2.desc', 'like', "%" . $desc . "%");
        }
        $errorData = $query->paginate($pageSize, ['error_v2.*', 'ZY_BRRY.AAA28', 'ZY_BRRY.BRKS', 'ZY_BRRY.ZZYSMC'], 'page', $page)->toArray();

        //查找缺陷已改未改的状态
        foreach ($errorData['data'] as $k => $v) {
            if ($v['status'] == 1) {
                $rectifyList = ErrorV2::query()->where('status', 0)
                    ->where('AAA28', $v['AAA28'])
                    ->where('error_rule', $v['error_rule'])->first();
                if ($rectifyList) {
                    $errorData['data'][$k]['rectify'] = '未改';
                } else {
                    $errorData['data'][$k]['rectify'] = '已改';
                }
            }
        }

        $data = !empty($errorData['data']) ? $errorData['data'] : [];
        $count = !empty($errorData['total']) ? $errorData['total'] : 0;

        $exportData = [
            ['缺陷字段', '缺陷描述', '住院号码', '姓名', '出院时间', '出院科室', '编码员', '住院医师', '主要诊断名称', '主要诊断编码', '主要手术名称', '主要手术编码', '缺陷分级', '缺陷归类']
        ];
        $returnData = [];
        foreach ($data as $value) {
            $level = ErrorRule::RULE_LEVEL[$errorRuleInfo['level']] ?? '';
            $type = ErrorRule::RULE_TYPE[$errorRuleInfo['type']] ?? '';
            if (!preg_match("/^[\x7f-\xff]+$/", $value['BRKS'])) {
                $cykb = Department::query()->where('dep_id', '=', $value['BRKS'])->value('dep_name');
            } else {
                $cykb = $value['BRKS'];
            }
            $returnData[] = [
                'error_rule' => $value['error_rule'],
                'auth' => $errorRuleInfo['auth'] ?? '',
                'field' => $errorRuleInfo['field'] ?? '',
                'ZYH' => $value['ZYH'] ?? '',
                'AAA28' => $value['AAA28'] ?? '',
                'AAA01' => $value['AAA01'] ?? '',
                'AAC01' => $value['AAC01'] ?? '',
                'AAC11N' => $cykb ?? '',
                'level' => $level,
                'type' => $type,
                'desc' => $value['desc'] ?? '',
                'coder_name' => $value['coder_name'] ?? '',
                'ZYYS' => $value['ZZYSMC'] ?? '',
                'ICD10_NAME' => $value['ICD10_NAME'] ?? '',
                'ICD10_ID1' => $value['ICD10_ID1'] ?? '',
                'ICD9_NAME' => $value['ICD9_NAME'] ?? '',
                'ICD9_ID1' => $value['ICD9_ID1'] ?? '',
                'rectify' => $value['rectify'] ?? '',
            ];
            $exportData[] = [
                'field' => $errorRuleInfo['field'] ?? '',
                'desc' => $errorRuleInfo['desc'] ?? '',
                'AAA28' => $patientInfoV2->AAA28 ?? '',
                'AAA01' => $patientInfoV2->AAA01 ?? '',
                'AAC01' => $value['AAC01'] . "\t",
                'AAC11N' => $cykb ?? '',
                'coder_name' => $value['coder_name'] ?? '',
                'ZYYS' => $value['ZZYSMC'] ?? '',
                'ICD10_NAME' => $zdData->ICD10_NAME ?? '',
                'ICD10_ID1' => $zdData->ICD10_ID1 ?? '',
                'ICD9_NAME' => $ssData->ICD9_NAME ?? '',
                'ICD9_ID1' => $ssData->ICD9_ID1 ?? '',
                'level' => $level,
                'type' => $type,
            ];
        }

        if ($isExport) {
            $csv = new CsvService();
            $csv->filename = $csv->charset('缺陷问题', 'UTF-8');

            return $csv->export($exportData, false);
        }

        return ToolsService::returnData(200, ['list' => $returnData, 'count' => $count]);
    } */

    /**
     * 详情页
     * @param Request $request
     * @return array
     */
    public function blInfo(Request $request)
    {
        $ZYH = $request->post('ZYH', '');
        if (empty($ZYH)) {
            return ToolsService::returnData(4001, [], '参数错误');
        }

        $patientInfo = PatientInfoV2::query()->where('ZYH', '=', $ZYH)->first();
        if (empty($patientInfo)) {
            return ToolsService::returnData(200, [], '参数错误');
        }
        $patientInfo = $patientInfo->toArray();

        // 医疗付费方式
        $AAA26C = config('dictionaries.AAA26C');
        $patientInfo['AAA26C'] = $AAA26C[$patientInfo['AAA26C']] ?? $patientInfo['AAA26C'];
        // 性别
        $AAA02C = config('dictionaries.AAA02C');
        $patientInfo['AAA02C'] = $AAA02C[$patientInfo['AAA02C']] ?? $patientInfo['AAA02C'];
        // 国籍
        $AAA05C = config('dictionaries.AAA05C');
        $patientInfo['AAA05C'] = $AAA05C[$patientInfo['AAA05C']] ?? $patientInfo['AAA05C'];
        // 出生地
        $patientInfo['CSD'] = $patientInfo['AAA09'] . $patientInfo['AAA10'] . $patientInfo['AAA11'];
        // 籍贯
        $patientInfo['GG'] = $patientInfo['AAA43'] . $patientInfo['AAA44'] . $patientInfo['GG'];
        // 民族
        $AAA06C = config('dictionaries.AAA06C');
        $patientInfo['AAA06C'] = $AAA06C[$patientInfo['AAA06C']] ?? $patientInfo['AAA06C'];
        // 证件类别
        $patientInfo['ZJLB'] = '居民身份证';
        // 职业
        $AAA18C = config('dictionaries.AAA18C');
        $patientInfo['AAA18C'] = $AAA18C[$patientInfo['AAA18C']] ?? $patientInfo['AAA18C'];
        // 婚姻
        $AAA08C = config('dictionaries.AAA08C');
        $patientInfo['AAA08C'] = $AAA08C[$patientInfo['AAA08C']] ?? $patientInfo['AAA08C'];
        // 现住址
        $patientInfo['XZZ'] = $patientInfo['AAA48'] . $patientInfo['AAA49'] . $patientInfo['AAA50'] . $patientInfo['AAA36C'] . $patientInfo['AAA15'];
        // 户口地址
        $patientInfo['HKDZ'] = $patientInfo['AAA45'] . $patientInfo['AAA46'] . $patientInfo['AAA47'] . $patientInfo['AAA33C'] . $patientInfo['AAA12'];
        // 联系人关系
        $AAA23C = config('dictionaries.AAA23C');
        $patientInfo['AAA23C'] = $AAA23C[$patientInfo['AAA23C']] ?? $patientInfo['AAA23C'];
        // 入院途径
        $AAB06C = config('dictionaries.AAB06C');
        $patientInfo['AAB06C'] = $AAB06C[$patientInfo['AAB06C']] ?? $patientInfo['AAB06C'];
        // 有无药物过敏名称
        $AEB02C = config('dictionaries.AEB02C');
        $patientInfo['AEB02C'] = $AEB02C[$patientInfo['AEB02C']] ?? $patientInfo['AEB02C'];
        // 血型
        $AEG01C = config('dictionaries.AEG01C');
        $patientInfo['AEG01C'] = $AEG01C[$patientInfo['AEG01C']] ?? $patientInfo['AEG01C'];
        // Rh血型
        $AEG02C = config('dictionaries.AEG02C');
        if (is_numeric($patientInfo['AEG02C'])) {
            $patientInfo['AEG02C'] = $AEG02C[$patientInfo['AEG02C']] ?? $patientInfo['AEG02C'];
        } else {
            $patientInfo['AEG02C'] = $patientInfo['AEG02C'];
        }
        // 病案质量
        $AED01C = config('dictionaries.AED01C');
        $patientInfo['AED01C'] = $AED01C[$patientInfo['AED01C']] ?? $patientInfo['AED01C'];
        // 是否日间手术
        //$patientInfo['SFRJSS'] = $patientInfo['SFRJSS'] == 1 ? '是' : '否';
        if ($patientInfo['SFRJSS'] == 'N' || $patientInfo['SFRJSS'] == '2') {
            $patientInfo['SFRJSS'] = '否';
        } elseif ($patientInfo['SFRJSS'] == 'Y' || $patientInfo['SFRJSS'] == '1') {
            $patientInfo['SFRJSS'] = '是';
        }
        if (is_numeric($patientInfo['SFRJSS'])) {
            //$patientInfo['RJSS'] = $patientInfo['SFRJSS'] == 1 ? '是' : '否';
            if ($patientInfo['SFRJSS'] == '1') {
                $patientInfo['SFRJSS'] = '是';
            } elseif ($patientInfo['SFRJSS'] == '2') {
                $patientInfo['SFRJSS'] = '否';
            }
        } else {
            $patientInfo['RJSS'] = $patientInfo['SFRJSS'];
        }
        // 完成情况
        $patientInfo['WCQK'] = $patientInfo['WCQK'];
        //LCLJ
        $patientInfo['LCLJ'] = $patientInfo['SSLCLJ'];
        // 变异情况
        $patientInfo['BYQK'] = $patientInfo['BYQK'];
        $patientInfo['YJ'] = $patientInfo['ZLLB'];
        // 离院方式
        $AEM01C = config('dictionaries.AEM01C');
        $patientInfo['AEM01C'] = $AEM01C[$patientInfo['AEM01C']] ?? $patientInfo['AEM01C'];
        // 是否有出院31天内再住院计划
        $AEM03C = config('dictionaries.AEM03C');
        $patientInfo['AEM03C'] = $AEM03C[$patientInfo['AEM03C']] ?? $patientInfo['AEM03C'] ?? '';
        $patientInfo['AEM04'] = $patientInfo['AEM04'] ?? '';
        $patientInfo['ZZLB'] = $patientInfo['ZZLB'] ?? '';
        //转科科别 AAD01C
        $AAD01C = Department::query()->where('dep_id', $patientInfo['AAD01C'])->value('dep_name');
        if (!empty($AAD01C)) {
            $patientInfo['AAD01C'] = $AAD01C;
        }
        // 重症监护
        $patientInfo['icu_list'] = PatientInfoIcuV2::query()
            ->where('ZYH', '=', $ZYH)
            ->where('status', '=', 0)
            ->get(['IS_MAIN_WAY', 'IN_TIME', 'OUT_TIME'])->toArray();

        // 费用信息
        $cost = PatientInfoCostV2::query()
            ->where('ZYH', '=', $ZYH)
            ->where('status', '=', 0)
            ->first();
        $patientInfo['cost'] = !empty($cost) ? $cost->toArray() : [];

        //合并费用信息
        $patientInfo = array_merge($patientInfo, $patientInfo['cost']);

        // 诊断信息
        $patientInfo['diagnosis_list'] = PatientInfoDiagnosisV2::query()
            ->where('ZYH', '=', $ZYH)
            ->where('status', '=', 0)
            ->orderBy('type')
            ->orderBy('DIA_ORDER', 'asc')
            ->get(['ICD10_ID1', 'ICD10_NAME', 'RYBQ', 'type', 'DIA_ORDER'])
            ->toArray();
        $IN_STATUS = config('dictionaries.IN_STATUS');
        foreach ($patientInfo['diagnosis_list'] as $key => $value) {
            // 入院病情
            $patientInfo['diagnosis_list'][$key]['RYBQ'] = $IN_STATUS[$value['RYBQ']] ?? $value['RYBQ'];
            $patientInfo['diagnosis_list'][$key]['RYQK'] = $IN_STATUS[$value['RYBQ']] ?? $value['RYBQ'];
            if ($value['type'] == 1) {
                $patientInfo['diagnosis_list'][$key]['class'] = 'main';
            } else {
                $patientInfo['diagnosis_list'][$key]['class'] = 'other';
            }
        }

        // 手术信息
        $patientInfo['operation_list'] = PatientInfoOperationV2::query()
            ->where('ZYH', '=', $ZYH)
            ->where('status', '=', 0)
            ->orderBy('type')
            ->orderBy('OPE_ORDER')
            ->get(['ICD9_ID1', 'ICD9_NAME', 'OPE_DATE', 'OPE_LEVEL', 'OPE_MAN_NAME', 'FRIST_ASSISTANT_NAME', 'SECOND_ASSISTANT_NAME', 'QKDJ', 'QKYHLB', 'HOCUS_WAY_ID', 'HOCUS_MAN_NAME', 'SSCXSJ', 'type', 'SSLX', 'INCISION_GRADE_ID'])
            ->toArray();
        $OPE_LEVEL = config('dictionaries.OPE_LEVEL');
        foreach ($patientInfo['operation_list'] as $key => $value) {
            // 手术级别
            $patientInfo['operation_list'][$key]['OPE_LEVEL'] = $OPE_LEVEL[$value['OPE_LEVEL']] ?? $value['OPE_LEVEL'];
        }
        $patientInfo['operation'] = $patientInfo['operation_list'];
        $patientInfo['AAA14C'] = empty($patientInfo['AAA13C']) ? '' : $patientInfo['AAA13C'];
        $patientInfo['ZRHSBM'] = empty($patientInfo['AEE10_CODE']) ? '' : $patientInfo['AEE10_CODE'];
        //AAA42如果是0就返回''
        $patientInfo['AAA42'] = $patientInfo['AAA42'] == 0 ? '' : $patientInfo['AAA42'];
        $patientInfo['AEN01'] = $patientInfo['AAA42'] == 0 ? '' : $patientInfo['AAA42'];
        $patientInfo['AAA40'] = $patientInfo['AAA40'] == 0 ? '' : $patientInfo['AAA40'];
        //格式化AAB01和AAC01,2025-05-29 14:36:4900:00,这种取2025-05-29 14:36:49
        $patientInfo['AAB01'] = date('Y-m-d H:i:s', strtotime(substr($patientInfo['AAB01'], 0, 19)));
        if (!empty($patientInfo['AAC01'])) {
            $patientInfo['AAC01'] = date('Y-m-d H:i:s', strtotime(substr($patientInfo['AAC01'], 0, 19)));
        }

        //AAB11N映射BFRY
        $patientInfo['BFRY'] = empty($patientInfo['AAB11N']) ? '' : $patientInfo['AAB11N'];
        //AAC03=>AAC11N
        $patientInfo['AAC03'] = empty($patientInfo['AAC11N']) ? '' : $patientInfo['AAC11N'];
        //入院科别  AAB02C   出院科别 AAC02C 判断是否是数字,如果是需要取department的dep_id=AAC02C的值
        $patientInfo['AAB02C'] = empty($patientInfo['AAB02C']) ? '' : $patientInfo['AAB02C'];

        if (is_numeric($patientInfo['AAB02C'])) {
            $patientInfo['AAB02C'] = Department::query()->where('dep_id', $patientInfo['AAB02C'])->value('dep_name');
        }
        if(!empty($patientInfo['AAB11N'])){
            $patientInfo['AAB02C'] = $patientInfo['AAB11N'];    
        }
        if (is_numeric($patientInfo['AAC02C'])) {
            $patientInfo['AAC02C'] = Department::query()->where('dep_id', $patientInfo['AAC02C'])->value('dep_name');
        }
        if(!empty($patientInfo['AAC11N'])){
            $patientInfo['AAC02C'] = $patientInfo['AAC11N'];    
        }
        //ZKRQ=>AED04
        $patientInfo['ZKRQ'] = empty($patientInfo['AED04']) ? '' : $patientInfo['AED04'];

        //替换字段
        $patientInfo = $this->fieldEdit($patientInfo);
        return ToolsService::returnData(200, $patientInfo);
    }

    /**
     * 获取病案首页（事中）费用明细列表
     * @param Request $request
     * @return array
     */
    public function feeDetailedV2(Request $request)
    {
        $ZYH = $request->post('ZYH', '');
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        if (!$ZYH) {
            return ToolsService::returnData(4001, [], '参数错误');
        }

        $data = PatientInfoFeeDetailedV2::query()
            ->where('ZYH', '=', $ZYH)
            ->where('status', '=', 0)
            ->paginate($pageSize, ['FYXH', 'FYMC', 'ZFJE', 'JFRQ', 'FYSL', 'FYDJ', 'FYKS'], 'page', $page)
            ->toArray();

        $depData = Department::getDepartmentData();
        foreach ($data['data'] as &$value) {
            $value['FYKS'] = $depData[$value['FYKS']];
        }

        return ToolsService::returnData(200, ['count' => $data['total'], 'list' => $data['data']]);
    }

    /**
     * 质控结果（侧边栏）
     * @param Request $request
     * @return array
     */
    public function qualityResult(Request $request)
    {
        $ZYH = $request->post('ZYH', '');
        if (empty($ZYH)) {
            return ToolsService::returnData(4001, [], '参数错误');
        }

        // 查询质控结果
        $errorV2Data = ErrorV2::query()
            ->where('ZYH', '=', $ZYH)
            ->where('status', '=', 0)
            ->get(['error_rule', 'desc', 'basis'])->toArray();

        $returnData = ['ZYH' => $ZYH, 'score' => ['score' => 100, 'level' => 0], 'list' => []];
        $down = 0;
        if (!empty($errorV2Data)) {
            // 查询质控规则
            $errorRuleData = ErrorRule::query()
                ->where('status', '=', 0)
                ->where('node', 'like', "%运行%")
                ->get(['id', 'category', 'auth', 'field', 'down', 'level', 'desc'])->toArray();
            $errorRuleData = array_column($errorRuleData, null, 'id');
            $ruleSetting = RuleSetting::query()->where('status', '=', 1)->where('rule_type', '=', '首页规则')->get()->toArray();
            $ruleSetting = array_column($ruleSetting, null, 'id');
            foreach ($errorV2Data as &$errorInfo) {
                $ruleSettingItem = $ruleSetting[$errorInfo['error_rule'] - 1000000] ?? [];
                if (empty($errorRuleData[$errorInfo['error_rule']]) && empty($ruleSettingItem)) {
                    continue;
                }

                if ($errorInfo['error_rule'] > 1000000 && isset($ruleSettingItem)) {
                    $errorfeild = TableDictSY::query()->where('field_name', '=', $ruleSettingItem['case_type'])->get()->ToArray();
                    $object = ['A类' => 0, 'B类' => 1, 'C类' => 2, 'D类' => 3];
                    $down += $ruleSettingItem['score'] ?? 0;
                    $errorInfo['level'] = $ruleSettingItem['error_level'] - 1 ?? 0;
                    $errorInfo['error_field'] = $errorfeild[0]['field'] ?? "";
                    $errorInfo['error_name'] = $ruleSettingItem['case_type'] ?? "";
                    $errorInfo['category'] = $object[$ruleSettingItem['object']] ?? "";
                    $errorInfo['down'] = $ruleSettingItem['score'] ?? "";
                    //$errorInfo['desc'] = $ruleSettingItem['description'] ?? "";
                } else {
                    $down += $errorRuleData[$errorInfo['error_rule']]['down'];
                    $errorInfo['level'] = $errorRuleData[$errorInfo['error_rule']]['level'];
                    $errorInfo['error_field'] = $errorRuleData[$errorInfo['error_rule']]['auth'];
                    $errorInfo['error_name'] = $errorRuleData[$errorInfo['error_rule']]['field'];
                    $errorInfo['category'] = $errorRuleData[$errorInfo['error_rule']]['category'];
                    $errorInfo['down'] = $errorRuleData[$errorInfo['error_rule']]['down'];
                }

                if ($errorInfo['error_rule'] != 1458) {
                    $errorInfo['desc'] = !empty($errorRuleData[$errorInfo['error_rule']]) ? $errorRuleData[$errorInfo['error_rule']]['desc'] : $errorInfo['desc'];
                }
                if (!empty($errorInfo['basis'])) {
                    $errorInfo['basis'] = explode(',', $errorInfo['basis']);
                } else {
                    $errorInfo['basis'] = [];
                }

                $returnData['list'][] = $errorInfo;
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

        if (!empty($returnData['list'])) {
            // 按照 ABCD类 排序
            $returnData['list'] = PublicService::sortByKey($returnData['list'], 'category', 2);
        }

        return ToolsService::returnData(200, $returnData);
    }

    /**
     * 获取医院列表
     * @return array
     */
    public function getHospitalList()
    {
        $hospitalData = ErrorV2::query()
            ->where('hospital_name', '!=', '')
            ->whereNotNull('hospital_name')
            ->groupBy('hospital_name')
            ->orderBy('id')
            ->pluck('hospital_name')
            ->toArray();

        return ToolsService::returnData(200, $hospitalData);
    }

    /**
     * 获取质控结果中的出院科室
     * @return array
     */
    public function getKsList()
    {
        $data = ErrorV2::query()
            ->where('CYKB', '!=', '')
            ->where('CYKB', '!=', '-')
            ->whereNotNull('CYKB')
            ->where('status', '=', 0)
            ->groupBy('CYKB')
            ->orderBy('id')
            ->pluck('CYKB')
            ->toArray();

        //判断是否是汉字，如果不是汉字需要关联科室表查询
        foreach ($data as &$value) {
            if (!preg_match("/^[\x7f-\xff]+$/", $value)) {
                $value = Department::query()
                    ->where('dep_id', '=', $value)
                    ->value('dep_name');
            }
        }

        return ToolsService::returnData(200, $data);
    }

    /**
     * 编码员质控问题列表
     * @param Request $request
     * @return array|string|null
     */
    public function bmyErrorData(Request $request)
    {
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        $isExport = $request->post('is_export', 0);
        if ($isExport) {
            $page = 1;
            $pageSize = 1000;
        }

        // 查询质控规则
        $query = ErrorRule::query()
            ->where('status', '=', 0)
            ->where('node', 'like', "%终末%");
        // 缺陷分级
        $level = $request->post("level", '');
        if ($level !== '' && $level !== null) {
            $query->where('level', '=', $level);
        }
        // 缺陷归类
        $type = $request->post("type", '');
        if ($type !== '' && $type !== null) {
            $query->where('type', '=', $type);
        }
        // 缺陷字段
        $field = $request->post("field", '');
        if ($field !== '' && $field !== null) {
            $query->where('field', 'like', "%" . $field . "%");
        }
        // 缺陷描述
        $desc = $request->post("desc", '');
        if ($desc !== '' && $desc !== null) {
            $query->where('desc', 'like', "%" . $desc . "%");
        }

        $errorRule = $query->get()->toArray();
        $errorRuleData = array_column($errorRule, null, 'id');
        $ruleIdList = array_column($errorRule, 'id');

        // 医院
        $hospital_name = $request->post('hospital_name', '');
        $hospital_name = !empty($hospital_name) ? $hospital_name : config('confAdmin.hospital_name');

        // 开始时间、结束时间
        $startTime = $request->post("start_time", '');
        $endTime = $request->post("end_time", '');
        $startTime = !empty($startTime) ? date('Y-m-d', strtotime($startTime)) . ' 00:00:00' : '2010-01-01 00:00:00';
        $endTime = !empty($endTime) ? date('Y-m-d', strtotime($endTime)) . ' 23:59:59' : '2099-12-31 23:59:59';

        // 查询质控结果
        $errorData = ErrorHomeBmy::query()
            ->where('status', '=', 0)
            ->where('hospital_name', '=', $hospital_name)
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->whereIn('error_rule', $ruleIdList)
            ->groupBy('error_rule')
            ->orderByDesc('count')
            ->paginate($pageSize, ['error_rule', DB::raw('count(error_rule) as count')], 'page', $page)
            ->toArray();
        $data = !empty($errorData['data']) ? $errorData['data'] : [];
        $count = !empty($errorData['total']) ? $errorData['total'] : 0;

        $exportData = [
            ['缺陷字段', '缺陷描述', '缺陷数量', '缺陷分级', '缺陷归类']
        ];
        $returnData = [];
        foreach ($data as $value) {
            $level = $errorRuleData[$value['error_rule']]['level'] ?? '';
            $level = ErrorRule::RULE_LEVEL[$level] ?? '';
            $type = $errorRuleData[$value['error_rule']]['type'] ?? '';
            $type = ErrorRule::RULE_TYPE[$type] ?? '';
            $returnData[] = [
                'error_rule' => $value['error_rule'],
                'count' => $value['count'],
                'auth' => $errorRuleData[$value['error_rule']]['auth'] ?? '',
                'field' => $errorRuleData[$value['error_rule']]['field'] ?? '',
                'level' => $level,
                'type' => $type,
                'desc' => $errorRuleData[$value['error_rule']]['desc'] ?? '',
            ];

            $exportData[] = [
                'field' => $errorRuleData[$value['error_rule']]['field'] ?? '',
                'desc' => $errorRuleData[$value['error_rule']]['desc'] ?? '',
                'count' => $value['count'],
                'level' => $level,
                'type' => $type,
            ];
        }

        if ($isExport) {
            $csv = new CsvService();
            $csv->filename = $csv->charset('缺陷问题', 'UTF-8');

            return $csv->export($exportData, false);
        }

        return ToolsService::returnData(200, ['list' => $returnData, 'count' => $count]);
    }

    /**
     * 缺陷问题详情列表
     * @param Request $request
     * @return array|string|null
     */
    public function bmyErrorDetailsList(Request $request)
    {
        $ruleId = $request->post('error_rule', '');
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        $hospital_name = $request->post('hospital_name', '');
        $hospital_name = !empty($hospital_name) ? $hospital_name : config('confAdmin.hospital_name');
        $isExport = $request->post('is_export', 0);
        if ($isExport) {
            $page = 1;
            $pageSize = 100000;
        }

        if (empty($ruleId)) {
            return ToolsService::returnData(4001, [], '参数错误');
        }

        $errorRule = ErrorRule::query()
            ->where('status', '=', 0)
            ->where('id', '=', $ruleId)
            ->first();
        if (empty($errorRule)) {
            return ToolsService::returnData(4001, [], '参数错误');
        }
        $errorRuleInfo = $errorRule->toArray();

        //
        $query = ErrorHomeBmy::query()
            ->where('hospital_name', '=', $hospital_name)
            ->where('error_rule', '=', $ruleId)
            ->where('status', '=', 0);

        // 住院号码
        $AAA28 = $request->post('AAA28', '');
        if (!empty($AAA28)) {
            $query->where('AAA28', '=', $AAA28);
        }
        // 出院科室
        $CYKB = $request->post('AAC11N', '');
        if (!empty($CYKB)) {
            $query->where('CYKB', 'like', "%" . $CYKB . "%");
        }

        // 开始时间、结束时间
        $startTime = $request->post("start_time", '');
        $endTime = $request->post("end_time", '');
        if ($startTime && $endTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $query->whereBetween('AAC01', [$startTime, $endTime]);
        } elseif ($startTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $query->where('AAC01', '>=', $startTime);
        } elseif ($endTime) {
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $query->where('AAC01', '<=', $endTime);
        }

        $errorData = $query
            ->paginate($pageSize, ['ZYH', 'AAC01', 'error_rule', 'desc'], 'page', $page)
            ->toArray();

        $data = !empty($errorData['data']) ? $errorData['data'] : [];
        $count = !empty($errorData['total']) ? $errorData['total'] : 0;

        $exportData = [
            ['缺陷字段', '缺陷描述', '住院号码', '姓名', '出院时间', '出院科室', '编码员', '住院医师', '主要诊断名称', '主要诊断编码', '主要手术名称', '主要手术编码', '缺陷分级', '缺陷归类']
        ];
        $returnData = [];
        foreach ($data as $value) {
            // 获取用户信息
            //            $patientInfo = PatientInfo::query()->where('ZYH','=',$value['ZYH'])->first();
            $patientInfo = PatientInfo::query()
                ->select(['patient_info.MED_REC_ID', 'patient_info.AAA01', 'patient_info.AAC01', 'zd.ICD10_NAME', 'zd.ICD10_ID1', 'ss.ICD9_NAME', 'ss.ICD9_ID1'])
                ->leftJoin('main_diagnosis as zd', 'patient_info.MED_REC_ID', '=', 'zd.AAA28')
                ->leftJoin('main_operation as ss', 'patient_info.MED_REC_ID', '=', 'ss.AAA28')
                ->where('patient_info.MED_REC_ID', '=', $value['ZYH'])
                ->first();

            $level = ErrorRule::RULE_LEVEL[$errorRuleInfo['level']] ?? '';
            $type = ErrorRule::RULE_TYPE[$errorRuleInfo['type']] ?? '';
            $returnData[] = [
                'error_rule' => $value['error_rule'],
                'auth' => $errorRuleInfo['auth'] ?? '',
                'field' => $errorRuleInfo['field'] ?? '',
                'ZYH' => $value['ZYH'] ?? '',
                'AAA28' => $patientInfo->AAA28 ?? '',
                'AAA01' => $patientInfo->AAA01 ?? '',
                'AAC01' => $value['AAC01'] ?? '',
                'AAC11N' => $patientInfo->AAC11N ?? '',
                'level' => $level,
                'type' => $type,
                'desc' => $errorRuleInfo['desc'] ?? '',
                'coder_name' => !empty($value['coder_name']) ? $value['coder_name'] : $value['coder_id'],
                'ZYYS' => $value['ZYYS'] ?? '',
                'ICD10_NAME' => $patientInfo->ICD10_NAME ?? '',
                'ICD10_ID1' => $patientInfo->ICD10_ID1 ?? '',
                'ICD9_NAME' => $patientInfo->ICD9_NAME ?? '',
                'ICD9_ID1' => $patientInfo->ICD9_ID1 ?? '',
            ];

            $exportData[] = [
                'field' => $errorRuleInfo['field'] ?? '',
                'desc' => $errorRuleInfo['desc'] ?? '',
                'AAA28' => $patientInfo->AAA28 ?? '',
                'AAA01' => $patientInfo->AAA01 ?? '',
                'AAC01' => $value['AAC01'] ?? '',
                'AAC11N' => $patientInfo->AAC11N ?? '',
                'coder_name' => !empty($value['coder_name']) ? $value['coder_name'] : $value['coder_id'],
                'ZYYS' => $value['ZYYS'] ?? '',
                'ICD10_NAME' => $patientInfo->ICD10_NAME ?? '',
                'ICD10_ID1' => $patientInfo->ICD10_ID1 ?? '',
                'ICD9_NAME' => $patientInfo->ICD9_NAME ?? '',
                'ICD9_ID1' => $patientInfo->ICD9_ID1 ?? '',
                'level' => $level,
                'type' => $type,
            ];
        }

        if ($isExport) {
            $csv = new CsvService();
            $csv->filename = $csv->charset('缺陷问题', 'UTF-8');

            return $csv->export($exportData, false);
        }

        return ToolsService::returnData(200, ['list' => $returnData, 'count' => $count]);
    }

    /**
     * 获取编码员质控结果
     * @param Request $request
     * @return array
     */
    public function bmyQualityResult(Request $request)
    {
        $ZYH = $request->post('ZYH', '');
        if (empty($ZYH)) {
            return ToolsService::returnData(4001, [], '参数错误');
        }

        // 查询质控结果
        $errorV2Data = ErrorHomeBmy::query()
            ->where('ZYH', '=', $ZYH)
            ->where('status', '=', 0)
            ->get(['error_rule', 'desc'])->toArray();

        $returnData = ['ZYH' => $ZYH, 'score' => ['score' => 100, 'level' => 0], 'list' => []];
        $down = 0;
        if (!empty($errorV2Data)) {
            // 查询质控规则
            $errorRuleData = ErrorRule::query()
                ->where('status', '=', 0)
                ->where('node', 'like', "%终末%")
                ->get(['id', 'category', 'auth', 'field', 'down', 'level', 'desc'])->toArray();
            $errorRuleData = array_column($errorRuleData, null, 'id');

            foreach ($errorV2Data as &$errorInfo) {
                if (empty($errorRuleData[$errorInfo['error_rule']])) {
                    continue;
                }
                $down += $errorRuleData[$errorInfo['error_rule']]['down'];
                $errorInfo['level'] = $errorRuleData[$errorInfo['error_rule']]['level'];
                $errorInfo['error_field'] = $errorRuleData[$errorInfo['error_rule']]['auth'];
                $errorInfo['error_name'] = $errorRuleData[$errorInfo['error_rule']]['field'];
                $errorInfo['category'] = $errorRuleData[$errorInfo['error_rule']]['category'];
                $errorInfo['down'] = $errorRuleData[$errorInfo['error_rule']]['down'];

                if (!in_array($errorInfo['error_rule'], [1439, 1440, 1441, 1458])) {
                    $errorInfo['desc'] = !empty($errorRuleData[$errorInfo['error_rule']]) ? $errorRuleData[$errorInfo['error_rule']]['desc'] : $errorInfo['desc'];
                }

                $returnData['list'][] = $errorInfo;
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

        if (!empty($returnData['list'])) {
            // 按照 ABCD类 排序
            $returnData['list'] = PublicService::sortByKey($returnData['list'], 'category', 2);
        }

        return ToolsService::returnData(200, $returnData);
    }


    /**
     * 获取编码员首页搜索
     *
     */
    protected function getBmyIndexSearch($searchArray): array
    {
        //所属院区
        $result = [];
        $yqCode = $searchArray['YQ_CODES'] ?? "";
        if (!empty($yqCode)) {
            $result['YQ_CODE'] = $yqCode;
        }
        //所属出院科室
        $KS_IDS = $searchArray['KS_IDS'] ?? "";
        if (!empty($KS_IDS)) {
            $result['AAC02C'] = $KS_IDS;
        }
        //出院病区
        $BQ_IDS = $searchArray['BQ_IDS'] ?? "";
        if (!empty($BQ_IDS)) {
            $result['AAC11C'] = $BQ_IDS;
        }

        return $result;
    }

    /**
     * 获取病案首页质控统计信息（编码员）
     * @param Request $request
     * @return array
     */
    public function bmyQualityStatistics(Request $request)
    {
        // 构建查询条件
        $where = [];
        $zy_status = $request->post('zy_status', "");
        if (is_numeric($zy_status)) {
            $where[] = ['in_hospital', $zy_status];
        }
        $searchArray = $this->getBmyIndexSearch($request->post());
        //所属院区
        if (!empty($searchArray['YQ_CODE'])) {
            $where[] = ['YQ_CODE', $searchArray['YQ_CODE']];
        }
        //所属出院科室
        if (!empty($searchArray['AAC02C'])) {
            $where[] = ['AAC02C', $searchArray['AAC02C']];
        }
        //所属出院病房
        if (!empty($searchArray['AAC11C'])) {
            $where[] = ['AAC11C', $searchArray['AAC11C']];
        }

        //开始时间与结束时间
        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');
        //处理时间
        $date = HomeQualityService::getAAC01StartEndTime($startTime, $endTime);
        $startTime = $date['start_time'];
        $endTime = $date['end_time'];

        //病案号
        $AAA28 = $request->post('AAA28', '');
        if (!empty($AAA28)) {
            $where[] = ['AAA28', $AAA28];
        }

        $depIds = UserService::getCurrentUserDep($request);
        if (empty($depIds)) {
            ToolsService::returnData(200, []);
        }

        $bm_status = $request->post('bm_status', "");

        // 构建基础查询（复用）
        $baseQuery = $this->buildBaseQuery($where, $bm_status, $depIds, $startTime, $endTime);

        // 修改查询，确保分数不会超过100
        $statistics = $baseQuery
            ->selectRaw('
                COUNT(DISTINCT patient_info.MED_REC_ID) as bl_sum,
                SUM(LEAST(COALESCE(patient_info.home_bmy_score, 100), 100)) as sum_score,
                MIN(LEAST(COALESCE(patient_info.home_bmy_score, 100), 100)) as min_score,
                MAX(LEAST(COALESCE(patient_info.home_bmy_score, 100), 100)) as max_score,
                SUM(CASE 
                    WHEN patient_info.home_bmy_score < 100 THEN 1 
                    ELSE 0 
                END) as qx_sum,
                SUM(CASE 
                    WHEN COALESCE(patient_info.home_bmy_score, 100) >= 97 THEN 1 
                    ELSE 0 
                END) as you_sum,
                SUM(CASE 
                    WHEN patient_info.home_bmy_score >= 90 AND patient_info.home_bmy_score < 97 THEN 1 
                    ELSE 0 
                END) as liang_sum,
                SUM(CASE 
                    WHEN patient_info.home_bmy_score >= 75 AND patient_info.home_bmy_score < 90 THEN 1 
                    ELSE 0 
                END) as zhong_sum,
                SUM(CASE 
                    WHEN patient_info.home_bmy_score < 75 THEN 1 
                    ELSE 0 
                END) as cha_sum
            ')
            ->first();


        $blSum = $statistics->bl_sum ?? 0;
        $returnData = [];
        $returnData['blSum'] = $blSum; //病历数量

        //获取病历日均例数
        $dayCount = ceil((strtotime($endTime) - strtotime($startTime)) / (3600 * 24));
        $dayAvg = 0;
        if ($blSum && $dayCount) {
            $dayAvg = (int)ceil($blSum / $dayCount);
        }
        $returnData['dayAvg'] = $dayAvg;

        //获取病历缺陷总例数
        $qxSum = $statistics->qx_sum ?? 0;
        $returnData['qxSum'] = $qxSum;
        //获取病历缺陷例数占比
        $returnData['averageError'] = 0;
        if ($blSum > 0) {
            $returnData['averageError'] = sprintf('%.2f', ($qxSum / $blSum) * 100);
        }

        $sumScore = $statistics->sum_score ?? 0;
        $blSum = $statistics->bl_sum ?? 0;
        $averageScore = $blSum > 0 ? ($sumScore / $blSum) : 0;

        $returnData['averageScore'] = number_format($averageScore, 2);

        //获取病历最低得分
        $returnData['minScore'] = $statistics->min_score ?? 0;

        //获取病历优良中差病历数查询
        $arr = ErrorRule::YLZC;
        $returnData['you_sum'] = $statistics->you_sum ?? 0;
        $returnData['you_ratio'] = $blSum > 0 ? sprintf('%.2f', ($returnData['you_sum'] / $blSum) * 100) : 0;

        $returnData['liang_sum'] = $statistics->liang_sum ?? 0;
        $returnData['liang_ratio'] = $blSum > 0 ? sprintf('%.2f', ($returnData['liang_sum'] / $blSum) * 100) : 0;

        $returnData['zhong_sum'] = $statistics->zhong_sum ?? 0;
        $returnData['zhong_ratio'] = $blSum > 0 ? sprintf('%.2f', ($returnData['zhong_sum'] / $blSum) * 100) : 0;

        $returnData['cha_sum'] = $statistics->cha_sum ?? 0;
        $returnData['cha_ratio'] = $blSum > 0 ? sprintf('%.2f', ($returnData['cha_sum'] / $blSum) * 100) : 0;

        // 缺陷问题分析（0-基本信息，1-诊疗信息，2-费用信息）
        // 批量获取所有规则ID，避免循环查询
        $questionArr = ['jbxx' => 0, 'zlxx' => 1, 'fyxx' => 2];
        $ruleTypeMap = ErrorRule::query()
            ->whereIn('type', array_values($questionArr))
            ->where('status', '=', 0)
            ->get()
            ->groupBy('type')
            ->map(function ($items) {
                return $items->pluck('id')->toArray();
            })
            ->toArray();

        // 优化：使用一次查询获取所有类型的统计数据
        $ruleTypeStats = [];
        if (!empty($ruleTypeMap)) {
            $baseQualityQuery = HomeQuality::query()
                ->leftJoin('ZY_BRRY', 'ZY_BRRY.ZYH', '=', 'home_quality.ZYH')
                ->whereBetween('ZY_BRRY.AAC01', [$startTime, $endTime]);

            if (is_array($depIds)) {
                $baseQualityQuery->whereIn('ZY_BRRY.BRKS', $depIds);
            }

            // 使用聚合查询一次性获取所有类型的统计
            $qualityStats = $baseQualityQuery->select('home_quality.error_rule')
                ->get()->toArray();
            $qualityStats = array_column($qualityStats, 'error_rule');
            // 统计$qualityStats数组中所有数字出现过的次数
            $qualityStatsCounts = array_count_values($qualityStats);

            // 按类型汇总
            foreach ($questionArr as $key => $type) {
                $ruleIds = $ruleTypeMap[$type] ?? [];
                $count = 0;
                foreach ($ruleIds as $ruleId) {
                    if (isset($qualityStatsCounts[$ruleId])) {
                        $count += $qualityStatsCounts[$ruleId];
                    }
                }
                $returnData[$key] = $count;
            }
        } else {
            foreach ($questionArr as $key => $val) {
                $returnData[$key] = 0;
            }
        }

        $returnData['qtxx'] = 0;
        return ToolsService::returnData(200, $returnData);
    }

    /**
     * 构建基础查询（复用查询条件）
     * @param array $where 基础条件
     * @param string $bm_status 病案状态
     * @param array|string $depIds 科室ID
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function buildBaseQuery($where, $bm_status, $depIds, $startTime, $endTime)
    {
        $query = PatientInfo::query();

        // 应用基础条件
        foreach ($where as $w) {
            $query->where($w[0], $w[1]);
        }

        // 病案状态
        if (is_numeric($bm_status)) {
            if ($bm_status == 1) {
                $query->where('patient_info.IS_CATA', 1);
            } elseif ($bm_status == 2) {
                $query->where('patient_info.IS_CATA', 0);
            }
        }

        // 科室过滤
        if (is_array($depIds)) {
            $query->whereIn('patient_info.AAC02C', $depIds);
        }

        // 时间范围
        $query->whereBetween('patient_info.AAC01', [$startTime, $endTime]);

        return $query;
    }

    /**
     * 获取缺陷字段
     * @return array
     */
    public function errorFieldList()
    {
        $data = ErrorRule::query()
            ->where('status', '=', 0)
            ->groupBy('field')
            ->get(['field'])->toArray();
        $data = array_column($data, 'field');
        return ToolsService::returnData(200, $data);
    }

    /**
     * 缺陷问题获取（编码员）
     * @param Request $request
     * @return array|void
     */
    public function bmyQualityData(Request $request)
    {
        // ========== 1. 参数预处理 ==========
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);

        // 时间处理
        $createdAtStart = $request->post('created_at_start', '');
        $createdAtEnd = $request->post('created_at_end', '');
        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');

        $useCreatedAt = !empty($createdAtStart) && !empty($createdAtEnd);
        if ($useCreatedAt) {
            $timeField = 'home_quality.created_at';
            $startTime = date('Y-m-d', strtotime($createdAtStart)) . ' 00:00:00';
            $endTime = date('Y-m-d', strtotime($createdAtEnd)) . ' 23:59:59';
            $startTimeForAAC01 = null; // 不需要用于AAC01查询
            $endTimeForAAC01 = null;
        } else {
            $timeField = 'ZY_BRRY.AAC01';
            $date = HomeQualityService::getAAC01StartEndTime($startTime, $endTime);
            $startTime = $date['start_time'];
            $endTime = $date['end_time'];
            $startTimeForAAC01 = $startTime;
            $endTimeForAAC01 = $endTime;
        }

        // 公共状态参数（只获取一次）
        $zy_status = $request->post('zy_status', '');
        $bm_status = $request->post('bm_status', '');
        $zy_status_numeric = is_numeric($zy_status) ? (int)$zy_status : null;
        $bm_status_numeric = is_numeric($bm_status) ? (int)$bm_status : null;

        // ========== 2. 规则数据查询（优化：合并条件） ==========
        $ruleQuery = ErrorRule::query()->where('status', 0);

        $type = $request->post('type', '');
        if ($type) {
            $ruleQuery->where('type', $type);
        }

        $level = $request->post('level', '');
        if (is_numeric($level)) {
            $ruleQuery->where('level', $level);
        }

        $field = $request->post('field', '');
        if ($field) {
            $ruleQuery->whereIn('field', $field);
        }

        $desc = $request->post('desc', '');
        if ($desc) {
            $ruleQuery->where('desc', 'like', "%{$desc}%");
        }

        $ruleDataArray = $ruleQuery->orderBy('id')->get()->toArray();

        $ruleData = array_column($ruleDataArray, null, 'id');
        $ruleIdList = array_keys($ruleData);

        // rule_setting 需应用与 error_rule 相同的 desc 过滤（对应字段为 description）
        $ruleSettingQuery = RuleSetting::query();
        if ($desc) {
            $ruleSettingQuery->where('description', 'like', "%{$desc}%");
        }
        $ruleSetting = $ruleSettingQuery->get()->toArray();
        foreach ($ruleSetting as $k=>$v) {
            $ruleSetting[$k]['id'] += 1000000;
        }
        $ruleSettingV2 = array_column($ruleSetting, null, 'id');

        $ruleIdList = array_merge($ruleIdList, array_keys($ruleSettingV2));

        // error_rule 与 rule_setting 均无匹配时才返回空
        if (empty($ruleIdList)) {
            return ToolsService::returnData(200, ['data' => [], 'count' => 0]);
        }

        // 预先处理规则常量映射，避免循环中重复查找
        $ruleLevelMap = ErrorRule::RULE_LEVEL;
        $ruleTypeMap = ErrorRule::RULE_TYPE;
        $categoryMap = ErrorRule::CATEGORY;

        // ========== 3. 用户权限检查 ==========
        $depIds = UserService::getCurrentUserDep($request);
        if (empty($depIds)) {
            return ToolsService::returnData(200, ['data' => [], 'count' => 0]);
        }

        // ========== 4. 批量获取辅助数据（减少查询次数） ==========
        $yqCode = $request->post('YQ_CODES', '');
        $KS_IDS = $request->post('KS_IDS', '');
        $bmy_ids = $request->post('bmy_ids', '');

        // 批量获取部门数据
        $deptIdsToQuery = [];
        if ($yqCode) {
            $deptIdsToQuery = array_merge($deptIdsToQuery, is_array($yqCode) ? $yqCode : [$yqCode]);
        }
        if ($KS_IDS) {
            $deptIdsToQuery = array_merge($deptIdsToQuery, is_array($KS_IDS) ? $KS_IDS : [$KS_IDS]);
        }

        $deptMap = [];
        if (!empty($deptIdsToQuery)) {
            $deptResults = Department::query()
                ->whereIn('id', array_unique($deptIdsToQuery))
                ->get(['id', 'dep_id'])
                ->toArray();
            foreach ($deptResults as $dept) {
                $deptMap[$dept['id']] = $dept['dep_id'];
            }
        }

        // 获取编码员代码（优化：简化循环）
        $bmyCodes = [];
        if (!empty($bmy_ids)) {
            $bmyIdsArray = is_array($bmy_ids) ? $bmy_ids : [$bmy_ids];
            $bmyCodeResult = Staff::query()
                ->whereIn('id', $bmyIdsArray)
                ->pluck('code')
                ->filter()
                ->toArray();
            $bmyCodes = array_values($bmyCodeResult);
        }

        // ========== 5. 预先提取所有查询参数（避免在闭包中重复调用） ==========
        $BQ_IDS = $request->post('BQ_IDS', '');
        $AAA28 = $request->post('AAA28', '');
        $ICD10_ID1 = $request->post('ICD10_ID1', '');
        $ICD10_NAME = $request->post('ICD10_NAME', '');
        $ICD9_ID1 = $request->post('ICD9_ID1', '');
        $ICD9_NAME = $request->post('ICD9_NAME', '');
        $AEE04_CODE = $request->post('AEE04_CODE', '');

        // 处理院区和科室的dep_id映射
        $yqDepIds = [];
        if ($yqCode) {
            $yqIds = is_array($yqCode) ? $yqCode : [$yqCode];
            foreach ($yqIds as $id) {
                if (isset($deptMap[$id])) {
                    $yqDepIds[] = $deptMap[$id];
                }
            }
        }

        $ksDepIds = [];
        if ($KS_IDS) {
            $ksDepIds = is_array($KS_IDS) ? $KS_IDS : [$KS_IDS];
        }

        // ========== 6. 构建主查询 ==========
        $query = HomeQuality::query()
            ->leftJoin('ZY_BRRY', 'ZY_BRRY.ZYH', '=', 'home_quality.ZYH')
            ->where('home_quality.is_del', 0)
            ->whereIn('home_quality.error_rule', $ruleIdList)
            ->whereBetween($timeField, [$startTime, $endTime]);

        if (is_array($depIds)) {
            $query->whereIn('ZY_BRRY.BRKS', $depIds);
        }

        if ($zy_status_numeric !== null) {
            $query->where('home_quality.in_hospital', $zy_status_numeric);
        }

        if ($bm_status_numeric !== null) {
            $query->where('home_quality.is_CATA', $bm_status_numeric);
        }

        if (!empty($yqDepIds)) {
            $query->whereIn('home_quality.YQ_CODE', $yqDepIds);
        }

        if (!empty($ksDepIds)) {
            $query->whereIn('home_quality.AAC02C', $ksDepIds);
        }

        if ($BQ_IDS) {
            $bqIds = is_array($BQ_IDS) ? $BQ_IDS : [$BQ_IDS];
            $query->whereIn('ZY_BRRY.BRBQ', $bqIds);
        }

        if (!empty($AAA28)) {
            $query->where('ZY_BRRY.AAA28', $AAA28);
        }

        if ($ICD10_ID1) {
            $query->where('home_quality.ICD10_ID1', 'like', "%{$ICD10_ID1}%");
        }

        if ($ICD10_NAME) {
            $query->where('home_quality.ICD10_NAME', 'like', "%{$ICD10_NAME}%");
        }

        if ($ICD9_ID1) {
            $query->where('home_quality.ICD9_ID1', 'like', "%{$ICD9_ID1}%");
        }

        if ($ICD9_NAME) {
            $query->where('home_quality.ICD9_NAME', 'like', "%{$ICD9_NAME}%");
        }

        if ($AEE04_CODE) {
            $query->where('home_quality.AEE04_CODE', $AEE04_CODE);
        }

        if (!empty($bmyCodes)) {
            $query->whereIn('home_quality.AEE08_CODE', $bmyCodes);
        }

        // ========== 7. 执行分页查询（自动包含count） ==========
        $data = $query->groupBy('error_rule')
            ->select('error_rule', DB::raw('count(home_quality.id) as count'))
            ->get()->toArray();

        $totalCount = array_sum(array_column($data, 'count'));

        // 通过$data中的count字段倒序排列，并且完成翻页
        $page = max(1, (int)$request->post('page', 1));
        $limit = max(1, (int)$request->post('limit', 10));
        // 排序
        usort($data, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });
        // 分页
        $total = count($data);
        $offset = ($page - 1) * $limit;
        $pagedData = array_slice($data, $offset, $limit);

        // 计算总缺陷病历数（去重后的 ZYH 数量，且关联 patient_info 和 brry 表）
        $totalDefectCount = DB::table('home_quality')
            ->leftJoin('ZY_BRRY', 'ZY_BRRY.ZYH', '=', 'home_quality.ZYH')
            ->leftJoin('patient_info', 'patient_info.MED_REC_ID', '=', 'ZY_BRRY.ZYH')
            ->where('home_quality.is_del', 0)
            ->whereIn('home_quality.error_rule', $ruleIdList)
            ->whereBetween('patient_info.AAC01', [$startTime, $endTime])
            ->when(is_array($depIds), function ($query) use ($depIds) {
                $query->whereIn('ZY_BRRY.BRKS', $depIds);
            })
            ->when($zy_status_numeric !== null, function ($query) use ($zy_status_numeric) {
                $query->where('home_quality.in_hospital', $zy_status_numeric);
            })
            ->when($bm_status_numeric !== null, function ($query) use ($bm_status_numeric) {
                $query->where('home_quality.is_CATA', $bm_status_numeric);
            })
            ->when(!empty($yqDepIds), function ($query) use ($yqDepIds) {
                $query->whereIn('home_quality.YQ_CODE', $yqDepIds);
            })
            ->when(!empty($ksDepIds), function ($query) use ($ksDepIds) {
                $query->whereIn('home_quality.AAC02C', $ksDepIds);
            })
            ->when(!empty($BQ_IDS), function ($query) use ($BQ_IDS) {
                $bqIds = is_array($BQ_IDS) ? $BQ_IDS : [$BQ_IDS];
                $query->whereIn('ZY_BRRY.BRBQ', $bqIds);
            })
            ->when(!empty($AAA28), function ($query) use ($AAA28) {
                $query->where('ZY_BRRY.AAA28', $AAA28);
            })
            ->when(!empty($ICD10_ID1), function ($query) use ($ICD10_ID1) {
                $query->where('home_quality.ICD10_ID1', 'like', "%{$ICD10_ID1}%");
            })
            ->when(!empty($ICD10_NAME), function ($query) use ($ICD10_NAME) {
                $query->where('home_quality.ICD10_NAME', 'like', "%{$ICD10_NAME}%");
            })
            ->when(!empty($ICD9_ID1), function ($query) use ($ICD9_ID1) {
                $query->where('home_quality.ICD9_ID1', 'like', "%{$ICD9_ID1}%");
            })
            ->when(!empty($ICD9_NAME), function ($query) use ($ICD9_NAME) {
                $query->where('home_quality.ICD9_NAME', 'like', "%{$ICD9_NAME}%");
            })
            ->when(!empty($AEE04_CODE), function ($query) use ($AEE04_CODE) {
                $query->where('home_quality.AEE04_CODE', $AEE04_CODE);
            })
            ->when(!empty($bmyCodes), function ($query) use ($bmyCodes) {
                $query->whereIn('home_quality.AEE08_CODE', $bmyCodes);
            })
            ->distinct('home_quality.ZYH')
            ->count('home_quality.ZYH');

        $totalCount = $totalDefectCount;

        $list = [];
        foreach ($pagedData as $value) {
            $ruleId = $value['error_rule'];
            if (empty($ruleData[$ruleId]) && empty($ruleSettingV2[$ruleId])) {
                continue;
            }

            $rule = $ruleData[$ruleId] ?? $ruleSettingV2[$ruleId];
            $count = $value['count'];

            // 预先计算百分比
            $percentage = $totalCount > 0 ? sprintf('%.2f', $count / $totalCount * 100) : '0.00';

            $list[] = [
                'error_rule' => $ruleId,
                'count' => $count,
                'eror_zb' => $percentage,
                'category' => $categoryMap[$rule['category']??''] ?? $rule['object'],
                'down' => $rule['down'] ?? $rule['object'],
                'desc' => $rule['desc'] ?? $rule['description'],
                'level' => $ruleLevelMap[$rule['level'] ?? $rule['error_level']] ?? '',
                'type' => $ruleTypeMap[$rule['type']] ?? '',
                'field' => $rule['field'] ?? $rule['case_type'],
                'percentage' => $percentage . '%',
            ];
        }

        return ToolsService::returnData(200, ['data' => $list, 'count' => $total ?? 0]);
    }

    /**
     * 缺陷问题导出（编码员）
     * @param Request $request
     * @return array|string|null
     */
    public function bmyQualityExport(Request $request)
    {
        
        
        // ========== 1. 参数预处理 ==========
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);

        // 时间处理
        $createdAtStart = $request->post('created_at_start', '');
        $createdAtEnd = $request->post('created_at_end', '');
        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');

        $useCreatedAt = !empty($createdAtStart) && !empty($createdAtEnd);
        if ($useCreatedAt) {
            $timeField = 'home_quality.created_at';
            $startTime = date('Y-m-d', strtotime($createdAtStart)) . ' 00:00:00';
            $endTime = date('Y-m-d', strtotime($createdAtEnd)) . ' 23:59:59';
            $startTimeForAAC01 = null; // 不需要用于AAC01查询
            $endTimeForAAC01 = null;
        } else {
            $timeField = 'ZY_BRRY.AAC01';
            $date = HomeQualityService::getAAC01StartEndTime($startTime, $endTime);
            $startTime = $date['start_time'];
            $endTime = $date['end_time'];
            $startTimeForAAC01 = $startTime;
            $endTimeForAAC01 = $endTime;
        }

        // 公共状态参数（只获取一次）
        $zy_status = $request->post('zy_status', '');
        $bm_status = $request->post('bm_status', '');
        $zy_status_numeric = is_numeric($zy_status) ? (int)$zy_status : null;
        $bm_status_numeric = is_numeric($bm_status) ? (int)$bm_status : null;

        // ========== 2. 规则数据查询（优化：合并条件） ==========
        $ruleQuery = ErrorRule::query()->where('status', 0);

        $type = $request->post('type', '');
        if ($type) {
            $ruleQuery->where('type', $type);
        }

        $level = $request->post('level', '');
        if (is_numeric($level)) {
            $ruleQuery->where('level', $level);
        }

        $field = $request->post('field', '');
        if ($field) {
            $ruleQuery->whereIn('field', $field);
        }

        $desc = $request->post('desc', '');
        if ($desc) {
            $ruleQuery->where('desc', 'like', "%{$desc}%");
        }

        $ruleDataArray = $ruleQuery->orderBy('id')->get()->toArray();

        $ruleData = array_column($ruleDataArray, null, 'id');
        $ruleIdList = array_keys($ruleData);

        // rule_setting 需应用与 error_rule 相同的 desc 过滤（对应字段为 description）
        $ruleSettingQuery = RuleSetting::query();
        if ($desc) {
            $ruleSettingQuery->where('description', 'like', "%{$desc}%");
        }
        $ruleSetting = $ruleSettingQuery->get()->toArray();
        foreach ($ruleSetting as $k=>$v) {
            $ruleSetting[$k]['id'] += 1000000;
        }
        $ruleSettingV2 = array_column($ruleSetting, null, 'id');

        $ruleIdList = array_merge($ruleIdList, array_keys($ruleSettingV2));

        // error_rule 与 rule_setting 均无匹配时才返回空
        if (empty($ruleIdList)) {
            return ToolsService::returnData(200, ['data' => [], 'count' => 0]);
        }

        // 预先处理规则常量映射，避免循环中重复查找
        $ruleLevelMap = ErrorRule::RULE_LEVEL;
        $ruleTypeMap = ErrorRule::RULE_TYPE;
        $categoryMap = ErrorRule::CATEGORY;

        // ========== 3. 用户权限检查 ==========
        $depIds = UserService::getCurrentUserDep($request);
        if (empty($depIds)) {
            return ToolsService::returnData(200, ['data' => [], 'count' => 0]);
        }

        // ========== 4. 批量获取辅助数据（减少查询次数） ==========
        $yqCode = $request->post('YQ_CODES', '');
        $KS_IDS = $request->post('KS_IDS', '');
        $bmy_ids = $request->post('bmy_ids', '');

        // 批量获取部门数据
        $deptIdsToQuery = [];
        if ($yqCode) {
            $deptIdsToQuery = array_merge($deptIdsToQuery, is_array($yqCode) ? $yqCode : [$yqCode]);
        }
        if ($KS_IDS) {
            $deptIdsToQuery = array_merge($deptIdsToQuery, is_array($KS_IDS) ? $KS_IDS : [$KS_IDS]);
        }

        $deptMap = [];
        if (!empty($deptIdsToQuery)) {
            $deptResults = Department::query()
                ->whereIn('id', array_unique($deptIdsToQuery))
                ->get(['id', 'dep_id'])
                ->toArray();
            foreach ($deptResults as $dept) {
                $deptMap[$dept['id']] = $dept['dep_id'];
            }
        }

        // 获取编码员代码（优化：简化循环）
        $bmyCodes = [];
        if (!empty($bmy_ids)) {
            $bmyIdsArray = is_array($bmy_ids) ? $bmy_ids : [$bmy_ids];
            $bmyCodeResult = Staff::query()
                ->whereIn('id', $bmyIdsArray)
                ->pluck('code')
                ->filter()
                ->toArray();
            $bmyCodes = array_values($bmyCodeResult);
        }

        // ========== 5. 预先提取所有查询参数（避免在闭包中重复调用） ==========
        $BQ_IDS = $request->post('BQ_IDS', '');
        $AAA28 = $request->post('AAA28', '');
        $ICD10_ID1 = $request->post('ICD10_ID1', '');
        $ICD10_NAME = $request->post('ICD10_NAME', '');
        $ICD9_ID1 = $request->post('ICD9_ID1', '');
        $ICD9_NAME = $request->post('ICD9_NAME', '');
        $AEE04_CODE = $request->post('AEE04_CODE', '');

        // 处理院区和科室的dep_id映射
        $yqDepIds = [];
        if ($yqCode) {
            $yqIds = is_array($yqCode) ? $yqCode : [$yqCode];
            foreach ($yqIds as $id) {
                if (isset($deptMap[$id])) {
                    $yqDepIds[] = $deptMap[$id];
                }
            }
        }

        $ksDepIds = [];
        if ($KS_IDS) {
            $ksDepIds = is_array($KS_IDS) ? $KS_IDS : [$KS_IDS];
        }

        // ========== 6. 构建主查询 ==========
        $query = HomeQuality::query()
            ->leftJoin('ZY_BRRY', 'ZY_BRRY.ZYH', '=', 'home_quality.ZYH')
            ->where('home_quality.is_del', 0)
            ->whereIn('home_quality.error_rule', $ruleIdList)
            ->whereBetween($timeField, [$startTime, $endTime]);

        if (is_array($depIds)) {
            $query->whereIn('ZY_BRRY.BRKS', $depIds);
        }

        if ($zy_status_numeric !== null) {
            $query->where('home_quality.in_hospital', $zy_status_numeric);
        }

        if ($bm_status_numeric !== null) {
            $query->where('home_quality.is_CATA', $bm_status_numeric);
        }

        if (!empty($yqDepIds)) {
            $query->whereIn('home_quality.YQ_CODE', $yqDepIds);
        }

        if (!empty($ksDepIds)) {
            $query->whereIn('home_quality.AAC02C', $ksDepIds);
        }

        if ($BQ_IDS) {
            $bqIds = is_array($BQ_IDS) ? $BQ_IDS : [$BQ_IDS];
            $query->whereIn('ZY_BRRY.BRBQ', $bqIds);
        }

        if (!empty($AAA28)) {
            $query->where('ZY_BRRY.AAA28', $AAA28);
        }

        if ($ICD10_ID1) {
            $query->where('home_quality.ICD10_ID1', 'like', "%{$ICD10_ID1}%");
        }

        if ($ICD10_NAME) {
            $query->where('home_quality.ICD10_NAME', 'like', "%{$ICD10_NAME}%");
        }

        if ($ICD9_ID1) {
            $query->where('home_quality.ICD9_ID1', 'like', "%{$ICD9_ID1}%");
        }

        if ($ICD9_NAME) {
            $query->where('home_quality.ICD9_NAME', 'like', "%{$ICD9_NAME}%");
        }

        if ($AEE04_CODE) {
            $query->where('home_quality.AEE04_CODE', $AEE04_CODE);
        }

        if (!empty($bmyCodes)) {
            $query->whereIn('home_quality.AEE08_CODE', $bmyCodes);
        }

        // ========== 7. 执行分页查询（自动包含count） ==========
        $data = $query->groupBy('error_rule')
            ->select('error_rule', DB::raw('count(home_quality.id) as count'))
            ->get()->toArray();

        // 排序
        usort($data, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });
        // 分页
        $pagedData = $data;

        // 计算总缺陷病历数（去重后的 ZYH 数量，且关联 patient_info 和 brry 表）
        $totalDefectCount = DB::table('home_quality')
            ->leftJoin('ZY_BRRY', 'ZY_BRRY.ZYH', '=', 'home_quality.ZYH')
            ->leftJoin('patient_info', 'patient_info.MED_REC_ID', '=', 'ZY_BRRY.ZYH')
            ->where('home_quality.is_del', 0)
            ->whereIn('home_quality.error_rule', $ruleIdList)
            ->whereBetween('patient_info.AAC01', [$startTime, $endTime])
            ->when(is_array($depIds), function ($query) use ($depIds) {
                $query->whereIn('ZY_BRRY.BRKS', $depIds);
            })
            ->when($zy_status_numeric !== null, function ($query) use ($zy_status_numeric) {
                $query->where('home_quality.in_hospital', $zy_status_numeric);
            })
            ->when($bm_status_numeric !== null, function ($query) use ($bm_status_numeric) {
                $query->where('home_quality.is_CATA', $bm_status_numeric);
            })
            ->when(!empty($yqDepIds), function ($query) use ($yqDepIds) {
                $query->whereIn('home_quality.YQ_CODE', $yqDepIds);
            })
            ->when(!empty($ksDepIds), function ($query) use ($ksDepIds) {
                $query->whereIn('home_quality.AAC02C', $ksDepIds);
            })
            ->when(!empty($BQ_IDS), function ($query) use ($BQ_IDS) {
                $bqIds = is_array($BQ_IDS) ? $BQ_IDS : [$BQ_IDS];
                $query->whereIn('ZY_BRRY.BRBQ', $bqIds);
            })
            ->when(!empty($AAA28), function ($query) use ($AAA28) {
                $query->where('ZY_BRRY.AAA28', $AAA28);
            })
            ->when(!empty($ICD10_ID1), function ($query) use ($ICD10_ID1) {
                $query->where('home_quality.ICD10_ID1', 'like', "%{$ICD10_ID1}%");
            })
            ->when(!empty($ICD10_NAME), function ($query) use ($ICD10_NAME) {
                $query->where('home_quality.ICD10_NAME', 'like', "%{$ICD10_NAME}%");
            })
            ->when(!empty($ICD9_ID1), function ($query) use ($ICD9_ID1) {
                $query->where('home_quality.ICD9_ID1', 'like', "%{$ICD9_ID1}%");
            })
            ->when(!empty($ICD9_NAME), function ($query) use ($ICD9_NAME) {
                $query->where('home_quality.ICD9_NAME', 'like', "%{$ICD9_NAME}%");
            })
            ->when(!empty($AEE04_CODE), function ($query) use ($AEE04_CODE) {
                $query->where('home_quality.AEE04_CODE', $AEE04_CODE);
            })
            ->when(!empty($bmyCodes), function ($query) use ($bmyCodes) {
                $query->whereIn('home_quality.AEE08_CODE', $bmyCodes);
            })
            ->distinct('home_quality.ZYH')
            ->count('home_quality.ZYH');

        $totalCount = $totalDefectCount;

        $list = [];
        foreach ($pagedData as $value) {
            $ruleId = $value['error_rule'];
            if (empty($ruleData[$ruleId]) && empty($ruleSettingV2[$ruleId])) {
                continue;
            }

            $rule = $ruleData[$ruleId] ?? $ruleSettingV2[$ruleId];
            $count = $value['count'];

            // 预先计算百分比
            $percentage = $totalCount > 0 ? sprintf('%.2f', $count / $totalCount * 100) : '0.00';

            $list[] = [
                'error_rule' => $ruleId,
                'count' => $count,
                'eror_zb' => $percentage,
                'category' => $categoryMap[$rule['category']??''] ?? $rule['object'],
                'down' => $rule['down'] ?? $rule['object'],
                'desc' => $rule['desc'] ?? $rule['description'],
                'level' => $ruleLevelMap[$rule['level'] ?? $rule['error_level']] ?? '',
                'type' => $ruleTypeMap[$rule['type']] ?? '',
                'field' => $rule['field'] ?? $rule['case_type'],
                'percentage' => $percentage . '%',
            ];
        }

        $exportData = [];
        $exportData[] = ['缺陷描述', '缺陷字段', '缺陷分级', '缺陷数量', '缺陷占比'];
        foreach ($list as $value) {
            $array = [
                $value['desc'],
                $value['field'],
                $value['level'],
                $value['count'],
                $value['percentage'],
            ];
            $exportData[] = $array;
        }

        $csv = new CsvService();
        $csv->filename = $csv->charset('缺陷问题详情', 'UTF-8');

        return $csv->export($exportData, false);
    }

    /**
     * 科室优秀率（编码员）
     * @param Request $request
     * @return array
     */
    public function bmyKsYxl(Request $request)
    {
        $hospitalName = $request->post('hospital_name') ?: config('confAdmin.hospital_name');

        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');
        $date = HomeQualityService::getAAC01StartEndTime($startTime, $endTime);
        $startTime = $date['start_time'];
        $endTime = $date['end_time'];

        // 获取科室信息
        $depData = Department::getDepartmentData();

        $homeQualityService = new HomeQualityService();

        $ksData = PatientInfo::query()
            ->where('hospital_name', '=', $hospitalName)
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->where('home_bmy_score', '>', 0)
            ->groupBy('AAC11C')
            ->orderByDesc('count')
            ->orderBy('AAC11C')
            ->get(['AAC11C', DB::raw('count(id) as count')])->toArray();

        $sumCount = 0;
        $sumScore = 0;
        foreach ($ksData as &$value) {
            $value['AAC11N'] = $depData[$value['AAC11C']] ?? $value['AAC11C'];

            // 科室优秀率
            $must = [
                ['match_phrase' => ["hospital_name" => $hospitalName]],
                ['term' => ["AAC11C" => $value['AAC11C']]],
                ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]],
                ['range' => ['home_bmy_score' => ['gte' => 97, 'lte' => 100]]]
            ];
            $data = $homeQualityService->getBlData($must);
            $youCount = !empty($data[1]) ? $data[1] : 0;
            $sumCount += $youCount;
            $value['yxl_sl'] = $youCount;
            $value['yxl'] = sprintf('%.2f', ($youCount / $value['count']) * 100);

            // 科室优秀率平均得分
            $must = [
                ['match_phrase' => ["hospital_name" => $hospitalName]],
                ['term' => ["AAC11C" => $value['AAC11C']]],
                ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]],
                ['range' => ['home_bmy_score' => ['gte' => 97, 'lte' => 100]]]
            ];
            $aggs = ['sumScore' => ['sum' => ['field' => 'home_bmy_score']]];
            $data = $homeQualityService->getBlData($must, $aggs);
            $youSumScore = !empty($data[2]['sumScore']['value']) ? $data[2]['sumScore']['value'] : 0;
            $sumScore += $youSumScore;
            $value['yxl_avg_score'] = sprintf('%.2f', $youSumScore / $youCount);
        }

        $returnData = [
            'avg_score' => sprintf('%.2f', $sumScore / $sumCount),
            'data' => $ksData,
        ];

        return ToolsService::returnData(200, $returnData);
    }

    /**
     * 科室平均分（编码员）
     * @param Request $request
     * @return array
     */
    public function bmyKsPjf(Request $request)
    {
        $hospitalName = $request->post('hospital_name') ?: config('confAdmin.hospital_name');
        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');
        $date = HomeQualityService::getAAC01StartEndTime($startTime, $endTime);
        $startTime = $date['start_time'];
        $endTime = $date['end_time'];

        // 获取科室信息
        $depData = Department::getDepartmentData();

        $homeQualityService = new HomeQualityService();

        $ksData = PatientInfo::query()
            ->where('hospital_name', '=', $hospitalName)
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->where('home_bmy_score', '>', 0)
            ->groupBy('AAC11C')
            ->orderByDesc('count')
            ->orderBy('AAC11C')
            ->get(['AAC11C', DB::raw('count(id) as count')])->toArray();
        $sumCount = 0;
        $sumScore = 0;
        foreach ($ksData as &$value) {
            $value['AAC11N'] = $depData[$value['AAC11C']] ?? $value['AAC11C'];

            // 科室平均得分
            $must = [
                ['match_phrase' => ["hospital_name" => $hospitalName]],
                ['term' => ["AAC11C" => $value['AAC11C']]],
                ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]]
            ];
            $aggs = ['sumScore' => ['sum' => ['field' => 'home_bmy_score']]];
            $data = $homeQualityService->getBlData($must, $aggs);
            $sumFs = !empty($data[2]['sumScore']['value']) ? $data[2]['sumScore']['value'] : 0;

            $value['avg_score'] = sprintf('%.2f', $sumFs / $value['count']);

            $sumCount += $sumFs;
            $sumScore += $value['count'];
        }

        $returnData = [
            'avg_score' => sprintf('%.2f', $sumCount / $sumScore),
            'data' => $ksData,
        ];

        return ToolsService::returnData(200, $returnData);
    }

    /**
     * 科室质控结果（编码员）
     * @param Request $request
     * @return array
     */
    public function bmyKsQualityResult(Request $request)
    {
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        $hospitalName = $request->post('hospital_name') ?: config('confAdmin.hospital_name');
        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');
        $date = HomeQualityService::getAAC01StartEndTime($startTime, $endTime);
        $startTime = $date['start_time'];
        $endTime = $date['end_time'];

        // 获取科室信息
        $depData = Department::getDepartmentData();

        $homeQualityService = new HomeQualityService();

        $list = PatientInfo::query()
            ->where('hospital_name', '=', $hospitalName)
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->where('home_bmy_score', '>', 0)
            ->groupBy('AAC11C')
            ->orderByDesc('count')
            ->orderBy('AAC11C')
            ->paginate($pageSize, ['AAC11C', DB::raw('count(id) as count')], 'page', $page)
            ->toArray();

        $ksData = $list['data'] ?? [];
        $total = $list['total'] ?? 0;

        foreach ($ksData as &$value) {
            $value['AAC11N'] = $depData[$value['AAC11C']] ?? $value['AAC11C'];

            // 科室优秀率
            $must = [
                ['match_phrase' => ["hospital_name" => $hospitalName]],
                ['term' => ["AAC11C" => $value['AAC11C']]],
                ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]],
                ['range' => ['home_bmy_score' => ['gte' => 97, 'lte' => 100]]]
            ];
            $data = $homeQualityService->getBlData($must);
            $youCount = !empty($data[1]) ? $data[1] : 0;
            $value['yxl_sl'] = $youCount;
            $value['yxl'] = sprintf('%.2f', ($youCount / $value['count']) * 100);

            // 科室平均得分
            $must = [
                ['match_phrase' => ["hospital_name" => $hospitalName]],
                ['term' => ["AAC11C" => $value['AAC11C']]],
                ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]]
            ];
            $aggs = ['sumScore' => ['sum' => ['field' => 'home_bmy_score']]];
            $data = $homeQualityService->getBlData($must, $aggs);
            $sumFs = !empty($data[2]['sumScore']['value']) ? $data[2]['sumScore']['value'] : 0;
            $value['avg_score'] = sprintf('%.2f', $sumFs / $value['count']);

            // 科室优良中差
            $arr = ErrorRule::YLZC;
            foreach ($arr as $k => $v) {
                $must = [
                    ['match_phrase' => ["hospital_name" => $hospitalName]],
                    ['term' => ["AAC11C" => $value['AAC11C']]],
                    ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]],
                    ['range' => ['home_bmy_score' => ['gte' => $v[0], 'lte' => $v[1]]]]
                ];
                $data = $homeQualityService->getBlData($must);
                $count = !empty($data[1]) ? $data[1] : 0;
                $value[$k . '_sl'] = $count;
                $value[$k . '_zb'] = sprintf('%.2f', ($count / $value['count']) * 100);
            }

            $value['wzx_sl'] = '';
            $value['zd_sl'] = '';
            $value['ss_sl'] = '';
        }

        return ToolsService::returnData(200, ['data' => $ksData, 'count' => $total]);
    }

    /**
     * 科室质控结果导出（编码员）
     * @param Request $request
     * @return array|string|null
     */
    public function bmyKsQualityResultExport(Request $request)
    {
        $isTen = $request->post('is_ten', 0);
        $page = 1;
        $pageSize = 10000;
        if ($isTen) {
            $pageSize = 10;
        }

        $hospitalName = $request->post('hospital_name') ?: config('confAdmin.hospital_name');
        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');
        $date = HomeQualityService::getAAC01StartEndTime($startTime, $endTime);
        $startTime = $date['start_time'];
        $endTime = $date['end_time'];

        // 获取科室信息
        $depData = Department::getDepartmentData();

        $homeQualityService = new HomeQualityService();

        $list = PatientInfo::query()
            ->where('hospital_name', '=', $hospitalName)
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->where('home_bmy_score', '>', 0)
            ->groupBy('AAC11C')
            ->orderByDesc('count')
            ->orderBy('AAC11C')
            ->paginate($pageSize, ['AAC11C', DB::raw('count(id) as count')], 'page', $page)
            ->toArray();

        $exportData = [
            ['科室名称', '病案数', '优秀数', '优秀率', '平均得分', '优', '占比', '良', '占比', '中', '占比', '差', '占比', '完整性', '诊断', '手术']
        ];
        $ksData = $list['data'] ?? [];
        foreach ($ksData as $value) {
            // 科室优秀率
            $must = [
                ['match_phrase' => ["hospital_name" => $hospitalName]],
                ['term' => ["AAC11C" => $value['AAC11C']]],
                ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]],
                ['range' => ['home_bmy_score' => ['gte' => 97, 'lte' => 100]]]
            ];
            $data = $homeQualityService->getBlData($must);
            $youCount = !empty($data[1]) ? $data[1] : 0;

            // 科室平均得分
            $must = [
                ['match_phrase' => ["hospital_name" => $hospitalName]],
                ['term' => ["AAC11C" => $value['AAC11C']]],
                ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]]
            ];
            $aggs = ['sumScore' => ['sum' => ['field' => 'home_bmy_score']]];
            $data = $homeQualityService->getBlData($must, $aggs);
            $sumFs = !empty($data[2]['sumScore']['value']) ? $data[2]['sumScore']['value'] : 0;

            // 科室优良中差
            $arr = ErrorRule::YLZC;
            foreach ($arr as $k => $v) {
                $must = [
                    ['match_phrase' => ["hospital_name" => $hospitalName]],
                    ['term' => ["AAC11C" => $value['AAC11C']]],
                    ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]],
                    ['range' => ['home_bmy_score' => ['gte' => $v[0], 'lte' => $v[1]]]]
                ];
                $data = $homeQualityService->getBlData($must);
                $count = !empty($data[1]) ? $data[1] : 0;
                $value[$k . '_sl'] = $count;
                $value[$k . '_zb'] = sprintf('%.2f', ($count / $value['count']) * 100);
            }

            $exportData[] = [
                $depData[$value['AAC11C']] ?? $value['AAC11C'],
                $value['count'],
                $youCount,
                sprintf('%.2f', ($youCount / $value['count']) * 100),
                sprintf('%.2f', $sumFs / $value['count']),
                $value['you_sl'],
                $value['you_zb'],
                $value['liang_sl'],
                $value['liang_zb'],
                $value['zhong_sl'],
                $value['zhong_zb'],
                $value['cha_sl'],
                $value['cha_zb'],
                '',
                '',
                ''
            ];
        }

        $csv = new CsvService();
        $csv->filename = $csv->charset('科室质控结果', 'UTF-8');

        return $csv->export($exportData, false);
    }

    /**
     * 主治医师优秀率
     * @param Request $request
     * @return array
     */
    public function bmyZzysYxl(Request $request)
    {
        $hospitalName = $request->post('hospital_name') ?: config('confAdmin.hospital_name');
        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');
        $date = HomeQualityService::getAAC01StartEndTime($startTime, $endTime);
        $startTime = $date['start_time'];
        $endTime = $date['end_time'];

        $homeQualityService = new HomeQualityService();

        $zzysData = PatientInfo::query()
            ->where('hospital_name', '=', $hospitalName)
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->where('home_bmy_score', '>', 0)
            ->groupBy('AEE03_CODE')
            ->orderByDesc('count')
            ->orderBy('AEE03_CODE')
            ->get(['AEE03_CODE', DB::raw('count(id) as count')])->toArray();

        // 获取医师信息
        $staffData = Staff::getStaffData();

        $sumCount = 0;
        $sumScore = 0;
        foreach ($zzysData as &$value) {
            $value['name'] = $staffData[$value['AEE03_CODE']] ?? $value['AEE03_CODE'];
            // 主治医师优秀率
            $must = [
                ['match_phrase' => ["hospital_name" => $hospitalName]],
                ['term' => ["AEE03_CODE" => $value['AEE03_CODE']]],
                ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]],
                ['range' => ['home_bmy_score' => ['gte' => 97, 'lte' => 100]]]
            ];
            $data = $homeQualityService->getBlData($must);
            $youCount = !empty($data[1]) ? $data[1] : 0;
            $sumCount += $youCount;
            $value['yxl_sl'] = $youCount;
            $value['yxl'] = sprintf('%.2f', ($youCount / $value['count']) * 100);

            // 主治医师优秀率平均得分
            $must = [
                ['match_phrase' => ["hospital_name" => $hospitalName]],
                ['term' => ["AEE03_CODE" => $value['AEE03_CODE']]],
                ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]],
                ['range' => ['home_bmy_score' => ['gte' => 97, 'lte' => 100]]]
            ];
            $aggs = ['sumScore' => ['sum' => ['field' => 'home_bmy_score']]];
            $data = $homeQualityService->getBlData($must, $aggs);
            $youSumScore = !empty($data[2]['sumScore']['value']) ? $data[2]['sumScore']['value'] : 0;
            $sumScore += $youSumScore;
            $value['yxl_avg_score'] = sprintf('%.2f', $youSumScore / $youCount);
        }

        $returnData = [
            'avg_score' => sprintf('%.2f', $sumScore / $sumCount),
            'data' => $zzysData,
        ];

        return ToolsService::returnData(200, $returnData);
    }

    /**
     * 主治医师平均分（编码员）
     * @param Request $request
     * @return array
     */
    public function bmyZzysPjf(Request $request)
    {
        $hospitalName = $request->post('hospital_name') ?: config('confAdmin.hospital_name');
        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');
        $date = HomeQualityService::getAAC01StartEndTime($startTime, $endTime);
        $startTime = $date['start_time'];
        $endTime = $date['end_time'];

        // 获取医师信息
        $staffData = Staff::getStaffData();

        $homeQualityService = new HomeQualityService();

        $ksData = PatientInfo::query()
            ->where('hospital_name', '=', $hospitalName)
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->where('home_bmy_score', '>', 0)
            ->groupBy('AEE03_CODE')
            ->orderByDesc('count')
            ->orderBy('AEE03_CODE')
            ->get(['AEE03_CODE', DB::raw('count(id) as count')])->toArray();
        $sumCount = 0;
        $sumScore = 0;
        foreach ($ksData as &$value) {
            $value['name'] = $staffData[$value['AEE03_CODE']] ?? $value['AEE03_CODE'];

            // 主治医师平均得分
            $must = [
                ['match_phrase' => ["hospital_name" => $hospitalName]],
                ['term' => ["AEE03_CODE" => $value['AEE03_CODE']]],
                ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]]
            ];
            $aggs = ['sumScore' => ['sum' => ['field' => 'home_bmy_score']]];
            $data = $homeQualityService->getBlData($must, $aggs);
            $sumFs = !empty($data[2]['sumScore']['value']) ? $data[2]['sumScore']['value'] : 0;

            $value['avg_score'] = sprintf('%.2f', $sumFs / $value['count']);

            $sumCount += $sumFs;
            $sumScore += $value['count'];
        }

        $returnData = [
            'avg_score' => sprintf('%.2f', $sumCount / $sumScore),
            'data' => $ksData,
        ];

        return ToolsService::returnData(200, $returnData);
    }

    /**
     * 主治医师质控结果（编码员）
     * @param Request $request
     * @return array
     */
    public function bmyZzysQualityResult(Request $request)
    {
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        $hospitalName = $request->post('hospital_name') ?: config('confAdmin.hospital_name');
        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');
        $date = HomeQualityService::getAAC01StartEndTime($startTime, $endTime);
        $startTime = $date['start_time'];
        $endTime = $date['end_time'];

        // 获取医师信息
        $staffData = Staff::getStaffData();

        $homeQualityService = new HomeQualityService();

        $list = PatientInfo::query()
            ->where('hospital_name', '=', $hospitalName)
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->where('home_bmy_score', '>', 0)
            ->groupBy('AEE03_CODE')
            ->orderByDesc('count')
            ->orderBy('AEE03_CODE')
            ->paginate($pageSize, ['AEE03_CODE', DB::raw('count(id) as count')], 'page', $page)
            ->toArray();

        $zzysData = $list['data'] ?? [];
        $total = $list['total'] ?? 0;

        foreach ($zzysData as &$value) {
            $value['name'] = $staffData[$value['AEE03_CODE']] ?? $value['AEE03_CODE'];

            // 主治医师优秀率
            $must = [
                ['match_phrase' => ["hospital_name" => $hospitalName]],
                ['term' => ["AEE03_CODE" => $value['AEE03_CODE']]],
                ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]],
                ['range' => ['home_bmy_score' => ['gte' => 97, 'lte' => 100]]]
            ];
            $data = $homeQualityService->getBlData($must);
            $youCount = !empty($data[1]) ? $data[1] : 0;
            $value['yxl_sl'] = $youCount;
            $value['yxl'] = sprintf('%.2f', ($youCount / $value['count']) * 100);

            // 主治医师平均得分
            $must = [
                ['match_phrase' => ["hospital_name" => $hospitalName]],
                ['term' => ["AEE03_CODE" => $value['AEE03_CODE']]],
                ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]]
            ];
            $aggs = ['sumScore' => ['sum' => ['field' => 'home_bmy_score']]];
            $data = $homeQualityService->getBlData($must, $aggs);
            $sumFs = !empty($data[2]['sumScore']['value']) ? $data[2]['sumScore']['value'] : 0;
            $value['avg_score'] = sprintf('%.2f', $sumFs / $value['count']);

            // 主治医师优良中差
            $arr = ErrorRule::YLZC;
            foreach ($arr as $k => $v) {
                $must = [
                    ['term' => ["hospital_name" => $hospitalName]],
                    ['term' => ["AEE03_CODE" => $value['AEE03_CODE']]],
                    ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]],
                    ['range' => ['home_bmy_score' => ['gte' => $v[0], 'lte' => $v[1]]]]
                ];
                $data = $homeQualityService->getBlData($must);
                $count = !empty($data[1]) ? $data[1] : 0;
                $value[$k . '_sl'] = $count;
                $value[$k . '_zb'] = sprintf('%.2f', ($count / $value['count']) * 100);
            }

            $value['wzx_sl'] = '';
            $value['zd_sl'] = '';
            $value['ss_sl'] = '';
        }

        return ToolsService::returnData(200, ['data' => $zzysData, 'count' => $total]);
    }

    /**
     * 主治医师质控结果导出（编码员）
     * @param Request $request
     * @return array|string|null
     */
    public function bmyZzysQualityResultExport(Request $request)
    {
        $isTen = $request->post('is_ten', 0);
        $page = 1;
        $pageSize = 1000000;
        if ($isTen) {
            $pageSize = 10;
        }

        $hospitalName = $request->post('hospital_name') ?: config('confAdmin.hospital_name');
        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');
        $date = HomeQualityService::getAAC01StartEndTime($startTime, $endTime);
        $startTime = $date['start_time'];
        $endTime = $date['end_time'];

        // 获取医师信息
        $staffData = Staff::getStaffData();

        $homeQualityService = new HomeQualityService();

        $list = PatientInfo::query()
            ->where('hospital_name', '=', $hospitalName)
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->where('home_bmy_score', '>', 0)
            ->groupBy('AEE03_CODE')
            ->orderByDesc('count')
            ->orderBy('AEE03_CODE')
            ->paginate($pageSize, ['AEE03_CODE', DB::raw('count(id) as count')], 'page', $page)
            ->toArray();

        $zzysData = $list['data'] ?? [];

        $exportData = [
            ['主治医师', '病案数', '优秀数', '优秀率', '平均得分', '优', '占比', '良', '占比', '中', '占比', '差', '占比', '完整性', '诊断', '手术']
        ];
        foreach ($zzysData as &$value) {
            // 主治医师优秀率
            $must = [
                ['match_phrase' => ["hospital_name" => $hospitalName]],
                ['term' => ["AEE03_CODE" => $value['AEE03_CODE']]],
                ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]],
                ['range' => ['home_bmy_score' => ['gte' => 97, 'lte' => 100]]]
            ];
            $data = $homeQualityService->getBlData($must);
            $youCount = !empty($data[1]) ? $data[1] : 0;

            // 主治医师平均得分
            $must = [
                ['match_phrase' => ["hospital_name" => $hospitalName]],
                ['term' => ["AEE03_CODE" => $value['AEE03_CODE']]],
                ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]]
            ];
            $aggs = ['sumScore' => ['sum' => ['field' => 'home_bmy_score']]];
            $data = $homeQualityService->getBlData($must, $aggs);
            $sumFs = !empty($data[2]['sumScore']['value']) ? $data[2]['sumScore']['value'] : 0;

            // 主治医师优良中差
            $arr = ErrorRule::YLZC;
            foreach ($arr as $k => $v) {
                $must = [
                    ['match_phrase' => ["hospital_name" => $hospitalName]],
                    ['term' => ["AEE03_CODE" => $value['AEE03_CODE']]],
                    ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]],
                    ['range' => ['home_bmy_score' => ['gte' => $v[0], 'lte' => $v[1]]]]
                ];
                $data = $homeQualityService->getBlData($must);
                $count = !empty($data[1]) ? $data[1] : 0;
                $value[$k . '_sl'] = $count;
                $value[$k . '_zb'] = sprintf('%.2f', ($count / $value['count']) * 100);
            }

            $exportData[] = [
                $staffData[$value['AEE03_CODE']] ?? $value['AEE03_CODE'],
                $value['count'],
                $youCount,
                sprintf('%.2f', ($youCount / $value['count']) * 100),
                sprintf('%.2f', $sumFs / $value['count']),
                $value['you_sl'],
                $value['you_zb'],
                $value['liang_sl'],
                $value['liang_zb'],
                $value['zhong_sl'],
                $value['zhong_zb'],
                $value['cha_sl'],
                $value['cha_zb'],
                '',
                '',
                ''
            ];
        }

        $csv = new CsvService();
        $csv->filename = $csv->charset('主治医师质控结果', 'UTF-8');

        return $csv->export($exportData, false);
    }

    /**
     * 住院医师优秀率
     * @param Request $request
     * @return array
     */
    public function bmyZyysYxl(Request $request)
    {
        $hospitalName = $request->post('hospital_name') ?: config('confAdmin.hospital_name');
        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');
        $date = HomeQualityService::getAAC01StartEndTime($startTime, $endTime);
        $startTime = $date['start_time'];
        $endTime = $date['end_time'];

        $homeQualityService = new HomeQualityService();

        $zzysData = PatientInfo::query()
            ->where('hospital_name', '=', $hospitalName)
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->where('home_bmy_score', '>', 0)
            ->groupBy('AEE04_CODE')
            ->orderByDesc('count')
            ->orderBy('AEE04_CODE')
            ->get(['AEE04_CODE', DB::raw('count(id) as count')])->toArray();

        // 获取医师信息
        $staffData = Staff::getStaffData();

        $sumCount = 0;
        $sumScore = 0;
        foreach ($zzysData as &$value) {
            $value['name'] = $staffData[$value['AEE04_CODE']] ?? $value['AEE04_CODE'];
            // 住院医师优秀率
            $must = [
                ['match_phrase' => ["hospital_name" => $hospitalName]],
                ['term' => ["AEE04_CODE" => $value['AEE04_CODE']]],
                ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]],
                ['range' => ['home_bmy_score' => ['gte' => 97, 'lte' => 100]]]
            ];
            $data = $homeQualityService->getBlData($must);
            $youCount = !empty($data[1]) ? $data[1] : 0;
            $sumCount += $youCount;
            $value['yxl_sl'] = $youCount;
            $value['yxl'] = sprintf('%.2f', ($youCount / $value['count']) * 100);

            // 住院医师优秀率平均得分
            $must = [
                ['match_phrase' => ["hospital_name" => $hospitalName]],
                ['term' => ["AEE04_CODE" => $value['AEE04_CODE']]],
                ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]],
                ['range' => ['home_bmy_score' => ['gte' => 97, 'lte' => 100]]]
            ];
            $aggs = ['sumScore' => ['sum' => ['field' => 'home_bmy_score']]];
            $data = $homeQualityService->getBlData($must, $aggs);
            $youSumScore = !empty($data[2]['sumScore']['value']) ? $data[2]['sumScore']['value'] : 0;
            $sumScore += $youSumScore;
            $value['yxl_avg_score'] = sprintf('%.2f', $youSumScore / $youCount);
        }

        $returnData = [
            'avg_score' => sprintf('%.2f', $sumScore / $sumCount),
            'data' => $zzysData,
        ];

        return ToolsService::returnData(200, $returnData);
    }

    /**
     * 住院医师平均分（编码员）
     * @param Request $request
     * @return array
     */
    public function bmyZyysPjf(Request $request)
    {
        $hospitalName = $request->post('hospital_name') ?: config('confAdmin.hospital_name');
        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');
        $date = HomeQualityService::getAAC01StartEndTime($startTime, $endTime);
        $startTime = $date['start_time'];
        $endTime = $date['end_time'];

        // 获取医师信息
        $staffData = Staff::getStaffData();

        $homeQualityService = new HomeQualityService();

        $ksData = PatientInfo::query()
            ->where('hospital_name', '=', $hospitalName)
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->where('home_bmy_score', '>', 0)
            ->groupBy('AEE04_CODE')
            ->orderByDesc('count')
            ->orderBy('AEE04_CODE')
            ->get(['AEE04_CODE', DB::raw('count(id) as count')])->toArray();
        $sumCount = 0;
        $sumScore = 0;
        foreach ($ksData as &$value) {
            $value['name'] = $staffData[$value['AEE04_CODE']] ?? $value['AEE04_CODE'];

            // 住院医师平均得分
            $must = [
                ['match_phrase' => ["hospital_name" => $hospitalName]],
                ['term' => ["AEE04_CODE" => $value['AEE04_CODE']]],
                ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]]
            ];
            $aggs = ['sumScore' => ['sum' => ['field' => 'home_bmy_score']]];
            $data = $homeQualityService->getBlData($must, $aggs);
            $sumFs = !empty($data[2]['sumScore']['value']) ? $data[2]['sumScore']['value'] : 0;

            $value['avg_score'] = sprintf('%.2f', $sumFs / $value['count']);

            $sumCount += $sumFs;
            $sumScore += $value['count'];
        }

        $returnData = [
            'avg_score' => sprintf('%.2f', $sumCount / $sumScore),
            'data' => $ksData,
        ];

        return ToolsService::returnData(200, $returnData);
    }

    /**
     * 住院医师质控结果（编码员）
     * @param Request $request
     * @return array
     */
    public function bmyZyysQualityResult(Request $request)
    {
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        $hospitalName = $request->post('hospital_name') ?: config('confAdmin.hospital_name');
        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');
        $date = HomeQualityService::getAAC01StartEndTime($startTime, $endTime);
        $startTime = $date['start_time'];
        $endTime = $date['end_time'];

        // 获取医师信息
        $staffData = Staff::getStaffData();

        $homeQualityService = new HomeQualityService();

        $list = PatientInfo::query()
            ->where('hospital_name', '=', $hospitalName)
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->where('home_bmy_score', '>', 0)
            ->groupBy('AEE04_CODE')
            ->orderByDesc('count')
            ->orderBy('AEE04_CODE')
            ->paginate($pageSize, ['AEE04_CODE', DB::raw('count(id) as count')], 'page', $page)
            ->toArray();

        $zzysData = $list['data'] ?? [];
        $total = $list['total'] ?? 0;

        foreach ($zzysData as &$value) {
            $value['name'] = $staffData[$value['AEE04_CODE']] ?? $value['AEE04_CODE'];

            // 住院医师优秀率
            $must = [
                ['match_phrase' => ["hospital_name" => $hospitalName]],
                ['term' => ["AEE04_CODE" => $value['AEE04_CODE']]],
                ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]],
                ['range' => ['home_bmy_score' => ['gte' => 97, 'lte' => 100]]]
            ];
            $data = $homeQualityService->getBlData($must);
            $youCount = !empty($data[1]) ? $data[1] : 0;
            $value['yxl_sl'] = $youCount;
            $value['yxl'] = sprintf('%.2f', ($youCount / $value['count']) * 100);

            // 住院医师平均得分
            $must = [
                ['match_phrase' => ["hospital_name" => $hospitalName]],
                ['term' => ["AEE04_CODE" => $value['AEE04_CODE']]],
                ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]]
            ];
            $aggs = ['sumScore' => ['sum' => ['field' => 'home_bmy_score']]];
            $data = $homeQualityService->getBlData($must, $aggs);
            $sumFs = !empty($data[2]['sumScore']['value']) ? $data[2]['sumScore']['value'] : 0;
            $value['avg_score'] = sprintf('%.2f', $sumFs / $value['count']);

            // 住院医师优良中差
            $arr = ErrorRule::YLZC;
            foreach ($arr as $k => $v) {
                $must = [
                    ['match_phrase' => ["hospital_name" => $hospitalName]],
                    ['term' => ["AEE04_CODE" => $value['AEE04_CODE']]],
                    ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]],
                    ['range' => ['home_bmy_score' => ['gte' => $v[0], 'lte' => $v[1]]]]
                ];
                $data = $homeQualityService->getBlData($must);
                $count = !empty($data[1]) ? $data[1] : 0;
                $value[$k . '_sl'] = $count;
                $value[$k . '_zb'] = sprintf('%.2f', ($count / $value['count']) * 100);
            }

            $value['wzx_sl'] = '';
            $value['zd_sl'] = '';
            $value['ss_sl'] = '';
        }

        return ToolsService::returnData(200, ['data' => $zzysData, 'count' => $total]);
    }

    /**
     * 住院医师质控结果导出（编码员）
     * @param Request $request
     * @return array|string|null
     */
    public function bmyZyysQualityResultExport(Request $request)
    {
        $isTen = $request->post('is_ten', 0);
        $page = 1;
        $pageSize = 1000000;
        if ($isTen) {
            $pageSize = 10;
        }

        $hospitalName = $request->post('hospital_name') ?: config('confAdmin.hospital_name');
        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');
        $date = HomeQualityService::getAAC01StartEndTime($startTime, $endTime);
        $startTime = $date['start_time'];
        $endTime = $date['end_time'];

        // 获取医师信息
        $staffData = Staff::getStaffData();

        $homeQualityService = new HomeQualityService();

        $list = PatientInfo::query()
            ->where('hospital_name', '=', $hospitalName)
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->where('home_bmy_score', '>', 0)
            ->groupBy('AEE04_CODE')
            ->orderByDesc('count')
            ->orderBy('AEE04_CODE')
            ->paginate($pageSize, ['AEE04_CODE', DB::raw('count(id) as count')], 'page', $page)
            ->toArray();

        $zzysData = $list['data'] ?? [];

        $exportData = [
            ['住院医师', '病案数', '优秀数', '优秀率', '平均得分', '优', '占比', '良', '占比', '中', '占比', '差', '占比', '完整性', '诊断', '手术']
        ];
        foreach ($zzysData as &$value) {
            // 住院医师优秀率
            $must = [
                ['match_phrase' => ["hospital_name" => $hospitalName]],
                ['term' => ["AEE04_CODE" => $value['AEE04_CODE']]],
                ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]],
                ['range' => ['home_bmy_score' => ['gte' => 97, 'lte' => 100]]]
            ];
            $data = $homeQualityService->getBlData($must);
            $youCount = !empty($data[1]) ? $data[1] : 0;

            // 住院医师平均得分
            $must = [
                ['match_phrase' => ["hospital_name" => $hospitalName]],
                ['term' => ["AEE04_CODE" => $value['AEE04_CODE']]],
                ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]]
            ];
            $aggs = ['sumScore' => ['sum' => ['field' => 'home_bmy_score']]];
            $data = $homeQualityService->getBlData($must, $aggs);
            $sumFs = !empty($data[2]['sumScore']['value']) ? $data[2]['sumScore']['value'] : 0;

            // 住院医师优良中差
            $arr = ErrorRule::YLZC;
            foreach ($arr as $k => $v) {
                $must = [
                    ['term' => ["hospital_name" => $hospitalName]],
                    ['term' => ["AEE04_CODE" => $value['AEE04_CODE']]],
                    ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]],
                    ['range' => ['home_bmy_score' => ['gte' => $v[0], 'lte' => $v[1]]]]
                ];
                $data = $homeQualityService->getBlData($must);
                $count = !empty($data[1]) ? $data[1] : 0;
                $value[$k . '_sl'] = $count;
                $value[$k . '_zb'] = sprintf('%.2f', ($count / $value['count']) * 100);
            }

            $exportData[] = [
                $staffData[$value['AEE04_CODE']] ?? $value['AEE04_CODE'],
                $value['count'],
                $youCount,
                sprintf('%.2f', ($youCount / $value['count']) * 100),
                sprintf('%.2f', $sumFs / $value['count']),
                $value['you_sl'],
                $value['you_zb'],
                $value['liang_sl'],
                $value['liang_zb'],
                $value['zhong_sl'],
                $value['zhong_zb'],
                $value['cha_sl'],
                $value['cha_zb'],
                '',
                '',
                ''
            ];
        }

        $csv = new CsvService();
        $csv->filename = $csv->charset('住院医师质控结果', 'UTF-8');

        return $csv->export($exportData, false);
    }

    /**
     * 编码员质控结果（编码员）
     * @param Request $request
     * @return array|void
     */
    public function bmyQualityResultList(Request $request)
    {
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        $hospitalName = $request->post('hospital_name') ?: config('confAdmin.hospital_name');
        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');
        $date = HomeQualityService::getAAC01StartEndTime($startTime, $endTime);
        $startTime = $date['start_time'];
        $endTime = $date['end_time'];
        // 获取医师信息
        $staffData = Staff::getStaffData();

        $homeQualityService = new HomeQualityService();

        $list = PatientInfo::query()
            ->where('hospital_name', '=', $hospitalName)
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->where('home_bmy_score', '>', 0)
            ->groupBy('AEE08_CODE')
            ->orderByDesc('count')
            ->orderBy('AEE08_CODE')
            ->paginate($pageSize, ['AEE08_CODE', DB::raw('count(id) as count')], 'page', $page)
            ->toArray();

        $bmyData = $list['data'] ?? [];
        $total = $list['total'] ?? 0;

        $sumCount = PatientInfo::query()
            ->where('hospital_name', '=', $hospitalName)
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->where('home_bmy_score', '>', 0)
            ->where('AEE08_CODE', '!=', '')
            ->count();

        foreach ($bmyData as &$value) {
            $value['bzs_zb'] = sprintf('%.2f', ($value['count'] / $sumCount) * 100);
            $value['name'] = $staffData[$value['AEE08_CODE']] ?? $value['AEE08_CODE'];

            // 编码员缺陷病历数量
            $must = [
                ['match_phrase' => ["hospital_name" => $hospitalName]],
                ['term' => ["AEE08_CODE" => $value['AEE08_CODE']]],
                ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]],
                ['range' => ['home_bmy_score' => ['gte' => 1, 'lte' => 99]]]
            ];
            $aggs = ['sumScore' => ['sum' => ['field' => 'home_bmy_score']]];
            $data = $homeQualityService->getBlData($must, $aggs);
            $qxCount = !empty($data[1]) ? $data[1] : 0;
            $value['qx_sl'] = $qxCount;
            $value['qx_zb'] = sprintf('%.2f', ($qxCount / $value['count']) * 100);
            $value['wzx_sl'] = '';
            $value['wzx_zb'] = '';
            $value['zd_sl'] = '';
            $value['zd_zb'] = '';
            $value['ss_sl'] = '';
            $value['ss_zb'] = '';
        }

        return ToolsService::returnData(200, ['data' => $bmyData, 'count' => $total]);
    }

    /**
     * 编码员质控结果导出（编码员）
     * @param Request $request
     * @return array|string|null
     */
    public function bmyQualityResultListExport(Request $request)
    {
        $isTen = $request->post('is_ten', 0);
        $page = 1;
        $pageSize = 1000000;
        if ($isTen) {
            $pageSize = 10;
        }

        $hospitalName = $request->post('hospital_name') ?: config('confAdmin.hospital_name');
        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');
        $date = HomeQualityService::getAAC01StartEndTime($startTime, $endTime);
        $startTime = $date['start_time'];
        $endTime = $date['end_time'];

        // 获取医师信息
        $staffData = Staff::getStaffData();

        $homeQualityService = new HomeQualityService();

        $list = PatientInfo::query()
            ->where('hospital_name', '=', $hospitalName)
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->where('home_bmy_score', '>', 0)
            ->groupBy('AEE08_CODE')
            ->orderByDesc('count')
            ->orderBy('AEE08_CODE')
            ->paginate($pageSize, ['AEE08_CODE', DB::raw('count(id) as count')], 'page', $page)
            ->toArray();

        $bmyData = $list['data'] ?? [];
        $sumCount = PatientInfo::query()
            ->where('hospital_name', '=', $hospitalName)
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->where('home_bmy_score', '>', 0)
            ->where('AEE08_CODE', '!=', '')
            ->count();

        $exportData = [
            ['编码员', '病案数', '病案占比', '缺陷病案', '缺陷病案占比', '缺陷（完整性）', '占比', '缺陷（诊断）', '占比', '缺陷（手术）', '占比']
        ];
        foreach ($bmyData as $value) {
            // 编码员缺陷病历数量
            $must = [
                ['match_phrase' => ["hospital_name" => $hospitalName]],
                ['term' => ["AEE08_CODE" => $value['AEE08_CODE']]],
                ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]],
                ['range' => ['home_bmy_score' => ['gte' => 1, 'lte' => 99]]]
            ];
            $aggs = ['sumScore' => ['sum' => ['field' => 'home_bmy_score']]];
            $data = $homeQualityService->getBlData($must, $aggs);
            $qxCount = !empty($data[1]) ? $data[1] : 0;

            $exportData[] = [
                $staffData[$value['AEE08_CODE']] ?? $value['AEE08_CODE'],
                $value['count'],
                sprintf('%.2f', ($value['count'] / $sumCount) * 100),
                $qxCount,
                sprintf('%.2f', ($qxCount / $value['count']) * 100),
                '',
                '',
                '',
                '',
                '',
                ''
            ];
        }

        $csv = new CsvService();
        $csv->filename = $csv->charset('编码员质控结果', 'UTF-8');

        return $csv->export($exportData, false);
    }



    /**
     * 缺陷详情列表（编码员）
     * @param Request $request
     * @return array|string|null
     */
    public function bmyQualityList(Request $request)
    {
        $ruleId = $request->post('rule_id', '');         // 规则ID
        $AAC11C = $request->post('AAC11C', '');          // 科室代码
        $AEE03_CODE = $request->post('AEE03_CODE', '');  // 主治医师代码
        $AEE04_CODE = $request->post('AEE04_CODE', '');  // 住院医师代码
        $AEE08_CODE = $request->post('AEE08_CODE', '');  // 编码员代码
        $AAA28 = $request->post('AAA28', ''); // 住院号码
        $ICD10_ID1 = $request->post('ICD10_ID1', ''); // 主要诊断编码
        $ICD10_NAME = $request->post('ICD10_NAME', ''); // 主要诊断名称
        $ICD9_ID1 = $request->post('ICD9_ID1', ''); // 主要手术编码
        $ICD9_NAME = $request->post('ICD9_NAME', ''); // 主要手术名称
        $page = $request->post('page', 1); // 页码
        $pageSize = $request->post('page_size', 10); // 每页条数
        $isExport = $request->post('is_export', 0); // 是否导出数据 0-列表返回 1-导出
        // AAC01: ["20250101", "20250710"]
        $AAC01 = $request->post('AAC01', ''); // 出院时间
        $startTime = $AAC01[0] ? date('Y-m-d', strtotime($AAC01[0])) . ' 00:00:00' : '';
        $endTime = $AAC01[1] ? date('Y-m-d', strtotime($AAC01[1])) . ' 23:59:59' : '';

        if (!$ruleId && !$AAC11C && !$AEE03_CODE && !$AEE04_CODE && !$AEE08_CODE) {
            return ToolsService::returnData(4001, [], '参数错误');
        }

        $homeQualityService = new HomeQualityService();
        $list = $homeQualityService->getBmyQualityList($ruleId, $AAC11C, $AEE03_CODE, $AEE04_CODE, $AEE08_CODE, $AAA28, $ICD10_ID1, $ICD10_NAME, $ICD9_ID1, $ICD9_NAME, $page, $pageSize, $isExport, $startTime, $endTime);

        $depData = Department::query()->get(['dep_id', 'dep_name'])->toArray();
        $depData = array_column($depData, null, 'dep_id');

        $staffData = Staff::getStaffData();
        $resList = [];
        foreach ($list['data'] as $key => $value) {
            $value['YQ_CODE'] = $depData[$value['YQ_CODE']]['dep_name'] ?? $value['YQ_CODE'];
            $value['AAC11N'] = $depData[$value['BRKS']]['dep_name'] ?? $value['BRKS'];
            $value['AEE08_CODE'] = $staffData[$value['AEE08_CODE']] ?? $value['AEE08_CODE'];
            $value['AEE04_CODE'] = $staffData[$value['AEE04_CODE']] ?? $value['AEE04_CODE'];
            $resList[] = [
                'field_name' => $value['field'],
                'MED_REC_ID' => $value['ZYH'],
                'AAA01' => $value['BRXM'],
                'AAC01' => $value['AAC01'],
                'YQ_CODE' => $value['YQ_CODE'],
                'AAC11N' => $value['AAC11N'],
                'AAC11C' => $value['AAC11C'],
                'AEE08' => $value['AEE08_CODE'],
                'AEE04' => $value['AEE04_CODE'],
                'ICD10_NAME' => $value['ICD10_NAME'],
                'ICD10_ID1' => $value['ICD10_ID1'],
                'ICD9_NAME' => $value['ICD9_NAME'],
                'ICD9_ID1' => $value['ICD9_ID1'],
                'ZYH' => $value['ZYH'],
                'AAA28' => $value['AAA28'],
            ];
        }
        if ($isExport) {
            $csv = new CsvService();
            $csv->filename = $csv->charset('缺陷问题详情', 'UTF-8');

            $title = ['缺陷字段', '住院号码', '姓名', '出院时间', '出院病区', '出院科室', '出院病房', '编码员', '住院医师',  '主要诊断名称', '主要诊断编码', '主要手术名称', '主要手术编码'];
            foreach ($resList as $key => $value) {
                unset($resList[$key]['ZYH'], $resList[$key]['AAA28']);
            }
            array_unshift($resList, $title);

            return $csv->export($resList, false);
        }

        return ToolsService::returnData(200, ['count' => $list['total'] ?? 0, 'data' => $resList]);
    }

    /**
     * 病历数据（编码员）
     * @param Request $request
     * @return array|string|null
     */
    public function bmyQualityBlData(Request $request)
    {
        $hospitalName = $request->post('hospital_name') ?: config('confAdmin.hospital_name');
        $AAA28 = $request->post('AAA28', '');
        $AAC11C = $request->post('AAC11C', '');
        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');
        $isQx = $request->post('is_qx') ?: 0;
        $isExport = $request->post('is_export', 0);
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        if ($isExport) {
            $page = 1;
            $pageSize = 1000000;
        }

        $must = [];
        if ($isQx) {
            // 有缺陷
            $must[] = ['range' => ['home_bmy_score' => ['gte' => 0, 'lte' => 99]]];
        } else {
            // 全部
            $must[] = ['range' => ['home_bmy_score' => ['gte' => 0, 'lte' => 100]]];
        }
        if ($AAA28) {
            $must[] = ['term' => ['AAA28' => $AAA28]];
        }
        if ($AAC11C) {
            $must[] = ['term' => ['AAC11C' => $AAC11C]];
        }
        if ($startTime && $endTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $must[] = ['range' => ['AAC01' => ['gte' => $startTime, 'lte' => $endTime]]];
        } elseif ($startTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $must[] = ['range' => ['AAC01' => ['gte' => $startTime]]];
        } elseif ($endTime) {
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $must[] = ['range' => ['AAC01' => ['lte' => $endTime]]];
        }

        $field = ['AAA28', 'MED_REC_ID', 'AAA01', 'AAA02C', 'AAA04', 'AAA29', 'AAC11C', 'AAC01', 'ADA01', 'F_D', 'J', 'ICD10_NAME', 'ICD9_NAME', 'AAC04'];
        $patientInfoService = new ElasticsearchService('patient_info');
        $params = $patientInfoService->clearMust()
            ->queryByMustBatch($must)
            ->source($field)
            ->trackTotalHits()
            ->paginate($page, $pageSize)
            ->getParams();
        $result = app('es')->search($params);
        $data = $patientInfoService->getDataByEs($result);

        $returnData = [];
        if ($isExport) {
            $returnData[] = ['住院号码', '姓名', '性别', '年龄', '住院次数', '出院科室', '出院日期', '总费用', '药品费用', '材料费用', '主诊断', '主手术', '实际住院天数'];
        }

        // 科室
        $depData = Department::getDepartmentData($hospitalName);

        // 性别
        $AAA02C_ARR = config('dictionaries.AAA02C');
        foreach ($data[0] as $val) {
            $AAA02C = isset($val['AAA02C']) ? $AAA02C_ARR[$val['AAA02C']] : $val['AAA02C'];
            $AAC11C = $depData[$val['AAC11C']] ?? $val['AAC11C'];
            $arr = [
                'AAA28' => $val['AAA28'] ?? '',
                'ZYH' => $val['MED_REC_ID'] ?? '',
                'AAA01' => $val['AAA01'] ?? '',
                'AAA02C' => $AAA02C,
                'AAA04' => $val['AAA04'] ?? '',
                'AAA29' => $val['AAA29'] ?? '',
                'AAC11C' => $AAC11C,
                'AAC01' => $val['AAC01'] ?? '',
                'ADA01' => $val['ADA01'] ?? '',
                'F_D' => $val['F_D'] ?? '',
                'J' => $val['J'] ?? '',
                'ICD10_NAME' => $val['ICD10_NAME'] ?? '',
                'ICD9_NAME' => $val['ICD9_NAME'] ?? '',
                'AAC04' => $val['AAC04'] ?? ''
            ];
            if ($isExport) {
                unset($arr['MED_REC_ID']);
            }
            $returnData[] = $arr;
        }

        if ($isExport) {
            $csv = new CsvService();
            $csv->filename = $csv->charset('病案首页', 'UTF-8');
            return $csv->export($returnData, false);
        }

        return ToolsService::returnData(200, ['count' => $data[1], 'data' => $returnData]);
    }

    /**
     * 医院列表（编码员）
     * @return array
     */
    public function bmyQualityHospitalList()
    {
        $hospitalList = HomeQuality::query()->groupBy('hospital_name')->pluck('hospital_name')->toArray();
        $hospitalList = !empty($hospitalList) ? $hospitalList : config('confAdmin.hospital_name');

        return ToolsService::returnData(200, $hospitalList);
    }

    public function getAllDepartment()
    {
        $hospital_name = config('confAdmin.hospital_name');
        $data = Department::query()
            //  ->where('hospital_name', '=', $hospital_name)
            //            ->where('flag','=',1)
            ->get(['dep_id', 'dep_name'])->toArray();

        return ToolsService::returnData(200, $data);
    }

    /**
     * 质控结果获取（编码员）
     * @param Request $request
     * @return array|void
     */
    public function getBmyQualityResult(Request $request)
    {
        $ZYH = $request->post('ZYH', '');
        $source = $request->post('source', '');
        $showCorrection = $request->post('show_correction', 0) or 0; // 是否返回整改数据
        if (empty($ZYH)) {
            return ToolsService::returnData(4001, [], '参数错误');
        }

        $homeQualityList = HomeQuality::query()->where('ZYH', '=', $ZYH)->where('is_del', 0)->get()->toArray();
        Log::info('homeQualityList:' . json_encode($homeQualityList));

        $returnData = ['ZYH' => $ZYH, 'score' => ['score' => 100, 'level' => 0], 'qz' => [], 'jy' => []];
        $errorRuleTable = ErrorRule::query()->where("status", 0)->get(['id', 'auth', 'level', 'field', 'category', 'down', 'desc'])->toArray();
        $errorRuleTable = array_column($errorRuleTable, null, 'id');

        $down = 0;
        if (!empty($homeQualityList)) {
            $errorRule = array_column($homeQualityList, 'error_rule');

            $appeal = Appeal::query()
                ->where('ZYH', '=', $ZYH)
                ->where('quality_type', '=', 3)
                ->whereIn('error_id', $errorRule)
                ->get()
                ->toArray();
            $appeal = array_column($appeal, null, 'error_id');
            // 自定义规则
            $ruleSetting = RuleSetting::query()->where('status', '=', 1)->get()->toArray();
            $ruleSetting = array_column($ruleSetting, null, 'id');
            foreach ($homeQualityList as $key => $errorInfo) {
                $type = 0;
                $errorRuleRow = $errorRuleTable[$errorInfo['error_rule']] ?? [];
                //var_dump($errorInfo['error_rule'] - 1000000);
                $ruleSettingItem = $ruleSetting[$errorInfo['error_rule'] - 1000000] ?? [];
                // 隐藏已整改数据
                if ($showCorrection == 2 and $errorInfo['is_correction'] == 1) {
                    continue;
                }
                // 规则不存在则跳过
                if ((empty($errorRuleRow) || $errorInfo['is_ignore'] == 1) && empty($ruleSettingItem)) {
                    continue;
                }
                if ($source == "appeal" && empty($errorInfo['appeal_id'])) {
                    continue;
                }
                // 未整改的数据才计算分数
                if (empty($errorInfo['is_correction'])) {
                    $down += $errorRuleRow['down'] ?? 0;
                }
                $array = [
                    'id' => $errorInfo['id'],
                    'error_rule' => $errorInfo['error_rule'],
                    'rule_id' => $errorInfo['error_rule'],

                    'basis' => [],
                    'is_artificial' => $errorInfo['is_artificial'],
                    'is_correction' => $errorInfo['is_correction'],
                    'appeal_id' => $appeal[$errorInfo['error_rule']]['id'] ?? 0,
                    'type' => $appeal[$errorInfo['error_rule']]['type'] ?? 0,
                    'status' => $appeal[$errorInfo['error_rule']]['status'] ?? 0,
                    'reject_content' => $appeal[$errorInfo['error_rule']]['defect_content'] ?? "",
                    'cate' => 3,
                ];
                //var_dump($errorInfo['error_rule']);
                //var_dump($ruleSettingItem);

                if ($errorInfo['error_rule'] > 1000000 && isset($ruleSettingItem)) {
                    $object = ['A类' => 0, 'B类' => 1, 'C类' => 2, 'D类' => 3];
                    $down += $ruleSettingItem['score'] ?? 0;
                    $array['level'] = $ruleSettingItem['error_level'] - 1 ?? 0;
                    $array['error_field'] = $ruleSettingItem['case_type'] ?? "";
                    $array['field_name'] = $ruleSettingItem['case_type'] ?? "";
                    $array['category'] = $object[$ruleSettingItem['object']] ?? "";
                    $array['down'] = $ruleSettingItem['score'] ?? "";
                    $array['desc'] = $ruleSettingItem['description'] ?? "";
                } else {
                    $array['level'] = $errorRuleRow['level'];
                    $array['error_field'] = $errorRuleRow['auth'];
                    $array['field_name'] = $errorRuleRow['field'];
                    $array['category'] = $errorRuleRow['category'];
                    $array['down'] = $errorRuleRow['down'];
                    $array['desc'] = $errorRuleRow['desc'];
                }

                // 判断质控依据中是否包含desc关键字，如果包含，则只需要desc内容，如果不包含，则要完整的basis内容
                if (strpos($errorInfo['basis'], "desc")) {
                    $basis = json_decode($errorInfo['basis'], true);
                    $basis = array_column($basis, "desc");
                } else {
                    $basis = json_decode($errorInfo['basis'] ?: '[]', true);
                }
                $array["basis"] = $basis;

                if ($array["status"] == 1) {
                    continue;
                }

                $returnData['list'][] = $array;
                $errorInfo['level'] = $array['level'];
                if ($errorInfo['level'] == 1) {
                    // 建议
                    $returnData['jy'][] = $array;
                } else {
                    // 强制
                    $returnData['qz'][] = $array;
                }
                //                }

            }
        }

        $returnData['score']['score'] = 100 - $down;
        $returnData['score']['level'] = HomeQualityService::levelJs($down);

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
     * 获取病案首页详情数据
     * @param Request $request
     * @return array
     */
    public function getBlDetails(Request $request)
    {
        $ZYH = $request->post('ZYH', '');

        $AAA26C = config('dictionaries.AAA26C');
        $AAA02C = config('dictionaries.AAA02C');
        $AAA05C = config('dictionaries.AAA05C');
        $AAA06C = config('dictionaries.AAA06C');
        $AAA18C = config('dictionaries.AAA18C');
        $AAA08C = config('dictionaries.AAA08C');
        //        $AAB06C = config('dictionaries.AAB06C');
        $AAA23C = config('dictionaries.AAA23C');
        $ABF02C = config('dictionaries.ABF02C');

        $depData = Department::getDepartmentData();
        // 主信息
        $patientInfo = PatientInfo::query()->where('MED_REC_ID', '=', $ZYH)->first();

        $returnData['patient_info'] = !empty($patientInfo) ? $patientInfo->toArray() : [];
        // 医疗付费方式
        if (!empty($returnData['patient_info']['AAA26C']) && !empty($AAA26C[$returnData['patient_info']['AAA26C']])) {
            $returnData['patient_info']['AAA26C'] = $returnData['patient_info']['AAA26C'] . '.' . $AAA26C[$returnData['patient_info']['AAA26C']];
        }
        // 性别
        if (!empty($returnData['patient_info']['AAA02C']) && !empty($AAA02C[$returnData['patient_info']['AAA02C']])) {
            $returnData['patient_info']['AAA02C'] = $AAA02C[$returnData['patient_info']['AAA02C']];
        }
        // 民族
        if (!empty($returnData['patient_info']['AAA06C']) && !empty($AAA06C[$returnData['patient_info']['AAA06C']])) {
            $returnData['patient_info']['AAA06C'] = $AAA06C[$returnData['patient_info']['AAA06C']];
        }
        // 国籍
        if (!empty($returnData['patient_info']['AAA05C']) && !empty($AAA05C[$returnData['patient_info']['AAA05C']])) {
            $returnData['patient_info']['AAA05C'] = $AAA05C[$returnData['patient_info']['AAA05C']];
        }
        // 婚姻
        if (!empty($returnData['patient_info']['AAA08C']) && !empty($AAA08C[$returnData['patient_info']['AAA08C']])) {
            $returnData['patient_info']['AAA08C'] = $AAA08C[$returnData['patient_info']['AAA08C']];
        }
        // 出生日期
        if (!empty($returnData['patient_info']['AAA03'])) {
            $returnData['patient_info']['AAA03'] = substr($returnData['patient_info']['AAA03'], 0, 10);
        }
        // 天龄
        if ($returnData['patient_info']['AAA40'] == 0) {
            $returnData['patient_info']['AAA40'] = '';
        }


        // 补充信息
        $patientAdd = PatientAdd::query()->where('AAA28', '=', $ZYH)->first();
        $returnData['patient_add'] = !empty($patientAdd) ? $patientAdd->toArray() : (object)[];

        // 地址信息
        $patientAddressInfo = PatientAddressInfo::query()->where('AAA28', '=', $ZYH)->first();
        $returnData['patient_address_info'] = !empty($patientAddressInfo) ? $patientAddressInfo->toArray() : (object)[];


        // 联系人信息
        $patientContactsInfo = PatientContactsInfo::query()->where('AAA28', '=', $ZYH)->first();
        if (!empty($patientContactsInfo)) {
            $returnData['patient_contacts_info'] = $patientContactsInfo->toArray();
            //联系人关系
            $AAA23C = config('dictionaries.AAA23C');
            $returnData['patient_contacts_info']['AAA23C'] = $AAA23C[$returnData['patient_contacts_info']['AAA23C']] ?? $returnData['patient_contacts_info']['AAA23C'];
        } else {
            $returnData['patient_contacts_info'] = (object)[];
        }

        // 费用信息
        $patientCostInfo = PatientCostInfo::query()->where('AAA28', '=', $ZYH)->first();
        $returnData['patient_cost_info'] = !empty($patientCostInfo) ? $patientCostInfo->toArray() : (object)[];

        // 医生信息
        $patientDoctorInfo = PatientDoctorInfo::query()->where('AAA28', '=', $ZYH)->first();
        if (!empty($patientDoctorInfo)) {
            // 获取员工信息
            $staffData = Staff::query()->pluck('base_code', 'code')->toArray();

            $returnData['patient_doctor_info'] = $patientDoctorInfo->toArray();

            // 医师编号
            $returnData['patient_doctor_info']['AEE01_CODE'] = !empty($staffData[$returnData['patient_doctor_info']['AEE01_CODE']]) ? $staffData[$returnData['patient_doctor_info']['AEE01_CODE']] : $returnData['patient_doctor_info']['AEE01_CODE'];
            $returnData['patient_doctor_info']['AEE02_CODE'] = !empty($staffData[$returnData['patient_doctor_info']['AEE02_CODE']]) ? $staffData[$returnData['patient_doctor_info']['AEE02_CODE']] : $returnData['patient_doctor_info']['AEE02_CODE'];
            $returnData['patient_doctor_info']['AEE03_CODE'] = !empty($staffData[$returnData['patient_doctor_info']['AEE03_CODE']]) ? $staffData[$returnData['patient_doctor_info']['AEE03_CODE']] : $returnData['patient_doctor_info']['AEE03_CODE'];
            $returnData['patient_doctor_info']['AEE04_CODE'] = !empty($staffData[$returnData['patient_doctor_info']['AEE04_CODE']]) ? $staffData[$returnData['patient_doctor_info']['AEE04_CODE']] : $returnData['patient_doctor_info']['AEE04_CODE'];

            if (!empty($returnData['patient_doctor_info']['AEE10'])) {
                $returnData['patient_doctor_info']['AEE10_CODE'] = Staff::query()->where('name', '=', $returnData['patient_doctor_info']['AEE10'])->value('base_code');
            } else {
                $returnData['patient_doctor_info']['AEE10_CODE'] = '';
            }
            if (!empty($returnData['patient_doctor_info']['AEE08'])) {
                $name = Staff::query()->where('code', '=', $returnData['patient_doctor_info']['AEE08'])->value('name');
                $name = !empty($name) ? $name : $returnData['patient_doctor_info']['AEE08'];
                $returnData['patient_doctor_info']['AEE08'] = $name;
            }
            //责任护士编码
            $returnData['patient_doctor_info']['AEE10_CODE'] = $returnData['patient_doctor_info']['ZRHS_BH'] ?? "";
            //医疗组长
            $returnData['patient_doctor_info']['ZZYISXM'] = $returnData['patient_doctor_info']['YLZZ'] ?? "";
        } else {
            $returnData['patient_doctor_info'] = (object)[];
        }

        // 住院信息
        $patientHospitalInfo = PatientHospitalInfo::query()->where('AAA28', '=', $ZYH)->first();
        if (!empty($patientHospitalInfo)) {
            $returnData['patient_hospital_info'] = $patientHospitalInfo->toArray();
            // 入院科别
            if (!empty($returnData['patient_hospital_info']['AAB02C']) && !empty($depData[$returnData['patient_hospital_info']['AAB02C']])) {
                $returnData['patient_hospital_info']['AAB02C'] = $depData[$returnData['patient_hospital_info']['AAB02C']];
            }
            // 入院病房
            if (!empty($returnData['patient_hospital_info']['AAB03']) && !empty($depData[$returnData['patient_hospital_info']['AAB03']])) {
                $returnData['patient_hospital_info']['AAB03'] = $depData[$returnData['patient_hospital_info']['AAB03']];
            }
            $returnData['patient_hospital_info']['AAB11N'] = $returnData['patient_hospital_info']['AAB03'];
            // 出院科别
            if (!empty($returnData['patient_hospital_info']['AAC02C']) && !empty($depData[$returnData['patient_hospital_info']['AAC02C']])) {
                $returnData['patient_hospital_info']['AAC02C'] = $depData[$returnData['patient_hospital_info']['AAC02C']];
            }
            if (!empty($returnData['patient_hospital_info']['AAC11N'])) {
                $returnData['patient_hospital_info']['AAC02C'] = $returnData['patient_hospital_info']['AAC11N'];
            }
            // 出院病房
            if (!empty($returnData['patient_hospital_info']['AAC03'])) {
                $returnData['patient_hospital_info']['AAC11N'] = $returnData['patient_hospital_info']['AAC03'];
                $returnData['patient_info']['AAC11N'] = $returnData['patient_hospital_info']['AAC03'];
            }
            // 入院科室
            if (!empty($returnData['patient_hospital_info']['AAB11C']) && !empty($depData[$returnData['patient_hospital_info']['AAB11C']])) {
                $returnData['patient_hospital_info']['AAB11C'] = $depData[$returnData['patient_hospital_info']['AAB11C']];
            }
            $returnData['patient_hospital_info']['AAB11C'] = $returnData['patient_hospital_info']['AAC11C'];
            // 出院科室
            if (!empty($returnData['patient_hospital_info']['AAC11C']) && !empty($depData[$returnData['patient_hospital_info']['AAC11C']])) {
                $returnData['patient_hospital_info']['AAC11C'] = $depData[$returnData['patient_hospital_info']['AAC11C']];
            }
            // 转科科别
            if (!empty($returnData['patient_hospital_info']['AAD01C']) && !empty($depData[$returnData['patient_hospital_info']['AAD01C']])) {
                $returnData['patient_hospital_info']['AAD01C'] = $depData[$returnData['patient_hospital_info']['AAD01C']];
            }
            // $returnData['patient_hospital_info']['AAC02C'] = $returnData['patient_hospital_info']['AAC11C'];  // AAC11C 在莱州这样展示是错误的
        } else {
            $returnData['patient_hospital_info'] = (object)[];
        }

        // 诊断信息
        $patientMedicalInfo = PatientMedicalInfo::query()->where('AAA28', '=', $ZYH)->first();
        if (!empty($patientMedicalInfo)) {
            $returnData['patient_medical_info'] = $patientMedicalInfo->toArray();
            if (!empty($returnData['patient_medical_info']['ABF02C']) && !empty($ABF02C[$returnData['patient_medical_info']['ABF02C']])) {
                $returnData['patient_medical_info']['ABF02C'] = $ABF02C[$returnData['patient_medical_info']['ABF02C']];
            }

            // 呼吸机使用时间
            //            $feeService = new ElasticsearchService('fee_detailed');
            //            $must = [
            //                ['term' => ['MED_REC_ID' => $ZYH]],
            //                ['match_phrase' => ['FYMC' => '呼吸机辅助呼吸']]
            //            ];
            //            $params = $feeService->clearMust()
            //                ->queryByMustBatch($must)
            //                ->paginate(1,10000)
            //                ->getParams();
            //            $res = app('es')->search($params);
            //            $feeData = $feeService->getDataByEs($res);
            //            $AEL01 = 0;
            //            foreach ($feeData[0] as $value) {
            //                $AEL01 += $value['FYSL'];
            //            }
            //            if (!empty($AEL01)) {
            //                $returnData['patient_medical_info']['AEL01'] = (int)floor($AEL01/24).' 天 '.($AEL01 % 24).' 小时 0 分钟';
            //            } else {
            //                $returnData['patient_medical_info']['AEL01'] = '';
            //            }
            if (!empty($patientMedicalInfo['AEL01']) && $patientMedicalInfo['AEL01'] != 0) {
                $returnData['patient_medical_info']['AEL01'] = (int)floor($patientMedicalInfo['AEL01'] / 24) . ' 天 ' . ($patientMedicalInfo['AEL01'] % 24) . ' 小时 0 分钟';
            } else {
                $returnData['patient_medical_info']['AEL01'] = $patientMedicalInfo['AEL01'];
            }
        } else {
            $returnData['patient_medical_info'] = [];
        }

        // 其他信息
        $patientOtherInfo = PatientOtherInfo::query()->where('AAA28', '=', $ZYH)->first();
        if (!empty($patientOtherInfo)) {
            $returnData['patient_other_info'] = $patientOtherInfo->toArray();
            if (!empty($returnData['patient_other_info']['UNT_ID']) && $returnData['patient_other_info']['UNT_ID'] == '060101') {
                $returnData['patient_other_info']['UNT_ID'] = '08718416-3';
            }
        } else {
            $returnData['patient_other_info'] = (object)[];
        }

        // 工作信息
        $patientWoreInfo = PatientWorkInfo::query()->where('AAA28', '=', $ZYH)->first();
        if (!empty($patientWoreInfo)) {
            $returnData['patient_work_info'] = $patientWoreInfo->toArray();
            // 职业
            if (!empty($returnData['patient_work_info']['AAA18C']) && !empty($AAA18C[$returnData['patient_work_info']['AAA18C']])) {
                $returnData['patient_work_info']['AAA18C'] = $AAA18C[$returnData['patient_work_info']['AAA18C']];
            }
        } else {
            $returnData['patient_work_info'] = (object)[];
        }

        // 主要诊断信息
        $mainDiagnosis = MainDiagnosis::query()->where('AAA28', '=', $ZYH)->first();
        $returnData['main_diagnosis'] = !empty($mainDiagnosis) ? $mainDiagnosis->toArray() : (object)[];

        // 其他诊断
        $otherDiagnosis = OtherDiagnosis::query()->where('AAA28', '=', $ZYH)->get()->toArray();
        $returnData['other_diagnosis'] = $otherDiagnosis;

        // 主要手术信息
        $HOCUS_WAY_ID = config('dictionaries.HOCUS_WAY_ID');
        $OPE_LEVEL = config('dictionaries.OPE_LEVEL');
        $OPE_TYPE = config('dictionaries.OPE_TYPE');
        $SSPB = config('dictionaries.SSPB');
        $mainOperation = MainOperation::query()->where('AAA28', '=', $ZYH)->first();
        if (!empty($mainOperation)) {
            $returnData['main_operation'] = $mainOperation->toArray();
            $returnData['main_operation']['SECOND_ASSISTANT_CODE'] = $returnData['main_operation']['SECOND_ASSISTANT_NAME'] ?? ""; //2助
            $returnData['main_operation']['FRIST_ASSISTANT_CODE'] = $returnData['main_operation']['FRIST_ASSISTANT_NAME'] ?? ""; //1助
            $returnData['main_operation']['HOCUS_MAN_CODE'] = $returnData['main_operation']['HOCUS_MAN_NAME'] ?? ""; //麻醉医师
            $returnData['main_operation']['QKDJ'] = $returnData['main_operation']['QKDJ']
                ?? ""; //切口愈合等级

            if (!empty($returnData['main_operation']['HOCUS_WAY_ID'])) {
                $returnData['main_operation']['HOCUS_WAY_ID'] = $HOCUS_WAY_ID[$returnData['main_operation']['HOCUS_WAY_ID']] ?? $returnData['main_operation']['HOCUS_WAY_ID']; //麻醉方式
            }

            if (is_numeric($returnData['main_operation']['HOCUS_WAY_ID'])) {
                $returnData['main_operation']['HOCUS_WAY_ID'] = $returnData['main_operation']['HOCUS_WAY_MC'] ?? ""; //麻醉方式
            }

            $returnData['main_operation']['OPE_LEVEL'] = $OPE_LEVEL[$returnData['main_operation']['OPE_LEVEL']] ?? ""; //手术级别
            //Log::info('shoushujibie----',['level' => $returnData['main_operation']['OPE_LEVEL']]);
            if ($returnData['main_operation']['OPE_LEVEL'] == "") {
                //Log::info('shoushujibie2----',['level' => $returnData['main_operation']['OPE_LEVEL']]);
                //Log::info('shoushujibie3----',['level' => $OPE_LEVEL]);
                $returnData['main_operation']['OPE_LEVEL'] = $returnData['main_operation']['OPE_LEVEL_MC'] ?? ""; //手术级别
            }
            //Log::info('shoushujibie1----',['level' => $returnData['main_operation']['OPE_LEVEL']]);
            $returnData['main_operation']['OPE_TYPE'] = $returnData['main_operation']['OPE_TYPE'] ?? ""; //手术类型
            $returnData['main_operation']['SSPB'] = $returnData['main_operation']['SSPB'] ?? ""; //手术判别
            $rjss = $returnData['patient_medical_info']['SFRJSS'] ?? '';
            if ($rjss == 'N' || $rjss == '2') {
                $rjss = '否';
            } elseif ($rjss == 'Y' || $rjss == '1') {
                $rjss = '是';
            }
            $returnData['main_operation']['RJSS'] = $rjss;
        } else {
            $returnData['main_operation'] = (object)[];
        }

        // 其他手术
        $secondaryOperation = SecondaryOperation::query()->where('AAA28', '=', $ZYH)->get()->toArray();
        $returnData['secondary_operation'] = $secondaryOperation;
        //$OPE_LEVEL = config('dictionaries.OPE_LEVEL');
        foreach ($returnData['secondary_operation'] as &$value) {
            // 手术级别
            $value['SECOND_ASSISTANT_CODE'] = $value['SECOND_ASSISTANT_NAME'] ?? ""; //2助
            $value['FRIST_ASSISTANT_CODE'] = $value['FRIST_ASSISTANT_NAME'] ?? ""; //1助
            $value['HOCUS_MAN_CODE'] = $value['HOCUS_MAN_NAME'] ?? ""; //麻醉医师
            $value['QKDJ'] = $value['QKDJ'] ?? ""; //切口愈合等级
            if (!empty($value['HOCUS_WAY_ID'])) {
                $value['HOCUS_WAY_ID'] = $HOCUS_WAY_ID[$value['HOCUS_WAY_ID']] ?? $value['HOCUS_WAY_ID']; //麻醉方式
            }

            if (is_numeric($value['HOCUS_WAY_ID'])) {
                $value['HOCUS_WAY_ID'] = $value['HOCUS_WAY_MC'] ?? ""; //麻醉方式
            }
            $value['OPE_LEVEL'] = $OPE_LEVEL[$value['OPE_LEVEL']] ?? ""; //手术级别
            if ($value['OPE_LEVEL'] == "") {
                $value['OPE_LEVEL'] = $value['OPE_LEVEL_MC'] ?? ""; //手术级别
            }
            $value['OPE_TYPE'] = $value['OPE_TYPE'] ?? ""; //手术类型
            $value['SSPB'] = $value['SSPB'] ?? ""; //手术判别
        }

        // 重症监护
        $returnData['icu'] = Icu::query()->where('AAA28', '=', $ZYH)->get()->toArray();
        if (!empty($returnData['icu'])) {
            $IS_MAIN_WAY_NEW = config('dictionaries.IS_MAIN_WAY_NEW');
            foreach ($returnData['icu'] as &$value) {
                if (!empty($value['IS_MAIN_WAY']) && !empty($IS_MAIN_WAY_NEW[$value['IS_MAIN_WAY']])) {
                    $value['IS_MAIN_WAY'] = $IS_MAIN_WAY_NEW[$value['IS_MAIN_WAY']] ?? $value['IS_MAIN_WAY'];
                }

                $value['HJXS'] = '';
                if (!empty($value['IN_TIME']) && !empty($value['OUT_TIME'])) {
                    //
                }
            }
        }

        // 入院途径-机构名称
        $returnData['patient_info']['ZZYLJG'] = BaBrsy::query()->where('AAA28', '=', $ZYH)->value('ZZYLJG');
        foreach ($returnData as $k => $v) {
            if (empty($v))
                continue;
            foreach ($v as $kk => $vv) {
                $returnData[$k][$kk] = $vv ?? "";
            }
        }
        return ToolsService::returnData(200, $returnData);
    }

    /**
     * 获取费用明细
     * @param Request $request
     * @return array
     */
    public function getFeeDetailed(Request $request)
    {
        $ZYH = $request->post('ZYH', '');
        if (empty($ZYH)) {
            $ZYH = $request->post('MED_REC_ID', '');
        }
        $FYMC = $request->post('FYMC', '');
        $JFRQ_START = $request->post('JFRQ_START', '');
        $JFRQ_END = $request->post('JFRQ_END', '');
        $FYKS = $request->post('FYKS', '');
        $FYGB = $request->post('FYGB', '');
        $SYFYGB = $request->post('SYFYGB', '');
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);

        $esService = new ElasticsearchService('fee_detailed');

        $must = [
            ['term' => ['MED_REC_ID' => $ZYH]]
        ];
        //Log::info('must', $must);
        // 费用名称
        if (!empty($FYMC)) {
            $must[] = ['match_phrase' => ['FYMC' => $FYMC]];
        }
        // 缴费日期
        if ($JFRQ_START && $JFRQ_END) {
            $JFRQ_START = date('Y-m-d', strtotime($JFRQ_START)) . ' 00:00:00';
            $JFRQ_END = date('Y-m-d', strtotime($JFRQ_END)) . ' 23:59:59';
            $must[] = ['range' => ['JFRQ.keyword' => ['gte' => $JFRQ_START, 'lte' => $JFRQ_END]]];
        } elseif ($JFRQ_START && !$JFRQ_END) {
            $JFRQ_START = date('Y-m-d', strtotime($JFRQ_START)) . ' 00:00:00';
            $must[] = ['range' => ['JFRQ.keyword' => ['gte' => $JFRQ_START]]];
        } elseif (!$JFRQ_START && $JFRQ_END) {
            $JFRQ_END = date('Y-m-d', strtotime($JFRQ_END)) . ' 23:59:59';
            $must[] = ['range' => ['JFRQ.keyword' => ['lte' => $JFRQ_END]]];
        }
        // 费用科室
        if (!empty($FYKS)) {
            $must[] = ['term' => ['FYKS' => $FYKS]];
        }
        // 费用归并
        if (!empty($FYGB)) {
            $must[] = ['term' => ['FYGB' => $FYGB]];
        }
        // 首页费用归并
        if (!empty($SYFYGB)) {
            $must[] = ['term' => ['SYFYGB' => $SYFYGB]];
        }
        $params = $esService->clearMust()
            ->queryByMustBatch($must)
            ->paginate($page, $pageSize)
            ->getParams();

        $res = app('es')->search($params);
        $feeData = $esService->getDataByEs($res);
        //Log::info('mustfeeData', $feeData);

        //获取科室
        foreach ($feeData[0] as $k => $v) {
            $departmentName = Department::query()->where('dep_id', '=', $v['FYKS'])->first('dep_name');
            if (empty($departmentName)) {
                $departmentName = $departmentName['dep_name'] ?? "-";
            }
            $feeData[0][$k]['FYKS'] = $departmentName['dep_name'] ?? "-";
            $feeData[0][$k]['JFRQ'] = $v['JFRQ'] == '1970-01-01 08:00:00' ? '' : $v['JFRQ'];
        }
        return ToolsService::returnData(200, ['count' => $feeData[1], 'list' => $feeData[0]]);
    }

    /**
     * 医生排名
     * @param Request $request
     * @return array|string|null
     */
    public function doctorRanking(Request $request)
    {
        //region 医师身份处理===
        //身份类型
        $syType = $request->post('sf_type', 0);
        //所属科室
        $AAC02C = $this->getBmyIndexSearch($request->post())['AAC02C'] ?? "";
        //人员信息
        $staffcode = $request->post('staff_code', []);
        //获取医师身份信息
        $staffKeyArray = [];
        $staffQuery = Staff::query()->where('ygjb', '!=', '');
        if ($AAC02C)
            $staffQuery->where('staff.ksdm', $AAC02C);
        if ($staffcode){
            if (env('APP_NAME') == 'ningxia') {
                $staffQuery->whereIn('staff.base_code', $staffcode);
            } else {
                $staffQuery->whereIn('staff.code', $staffcode);
            }
        }
        if(env('APP_NAME') == 'ningxia'){
            $staffKeyArray = $staffQuery->pluck('staff.ksdm', 'staff.base_code')->toArray();
        } else {
            $staffKeyArray = $staffQuery->pluck('staff.ksdm', 'staff.code')->toArray();
        }

        //处理人员信息
        $staffCodeArray = array_keys($staffKeyArray); //获取所有人员的code，后续作in使用

        //region 处理排名===doctorRanking
        $depIds = UserService::getCurrentUserDep($request);
        if (empty($depIds)) {
            return ToolsService::returnData(200, ['total' => 0, 'data' => []]);
        }
        $query = PatientInfo::query()
            ->leftJoin('ZY_BRRY', 'ZY_BRRY.ZYH', '=', 'patient_info.MED_REC_ID')
            ->leftJoin('patient_doctor_info', 'patient_doctor_info.AAA28', '=', 'patient_info.MED_REC_ID');

        if (is_array($depIds)) {
            $query->whereIn('ZY_BRRY.BRKS', $depIds);
        }

        //->leftJoin('home_quality','home_quality.ZYH','=','patient_info.MED_REC_ID');
        //在院状态
        $zy_status = $request->post('zy_status', '');
        if (is_numeric($zy_status))
            $query->where('patient_info.in_hospital', $zy_status);

        //编目状态
        $bm_status = $request->post('bm_status', '');
        if (is_numeric($bm_status))
            $query->where('patient_info.is_CATA', $bm_status);


        //时间范围(起始时间)
        $startTime = $request->post('start_time', '');
        $startTime = empty($startTime) ? date('Y-01-01 00:00:00') : Carbon::createFromFormat('Ymd', $startTime)->format('Y-m-d 00:00:00'); //如果为空，则默认为当前年份的1月1日


        //时间范围(结束时间)
        $endTime = $request->post('end_time', '');
        $endTime = empty($endTime) ? date('Y-m-d 23:59:59') : Carbon::createFromFormat('Ymd', $endTime)->format('Y-m-d 23:59:59'); //如果为空，则默认为当前时间
        $query->whereBetween('patient_info.AAC01', [$startTime, $endTime]);

        //住院号
        $AAA28 = $request->post('AAA28', '');
        if (!empty($AAA28))
            $query->where('patient_info.AAA28', $AAA28);

        //身份类型
        $homeFiled = "";
        switch ($syType) {
            case 1: //科主任
                $query->whereIn('patient_doctor_info.AEE01_CODE', $staffCodeArray)
                    ->groupBy('patient_doctor_info.AEE01_CODE')
                    ->selectRaw('patient_doctor_info.AEE01 as name,patient_doctor_info.AEE01_CODE as code');
                $homeFiled = "AEE01_CODE";
                break;
            case 2: //主任（副主任）医师
                $query->whereIn('patient_doctor_info.AEE02_CODE', $staffCodeArray)
                    ->groupBy('patient_doctor_info.AEE02_CODE')
                    ->selectRaw('patient_doctor_info.AEE02 as name,patient_doctor_info.AEE02_CODE as code');
                $homeFiled = "AEE02_CODE";
                break;
            case 3: //主治医师
                $query->whereIn('patient_doctor_info.AEE03_CODE', $staffCodeArray)
                    ->groupBy('patient_doctor_info.AEE03_CODE')
                    ->selectRaw('patient_doctor_info.AEE03 as name,patient_doctor_info.AEE03_CODE as code');
                $homeFiled = "AEE03_CODE";
                break;
            case 4: //住院医师
                $query->whereIn('patient_doctor_info.AEE04_CODE', $staffCodeArray)
                    ->groupBy('patient_doctor_info.AEE04_CODE')
                    ->selectRaw('patient_doctor_info.AEE04 as name,patient_doctor_info.AEE04_CODE as code');
                // ->select(['patient_info.ZYH_ID as ZYH_ID','patient_doctor_info.AEE03 as name,patient_doctor_info.AEE03_CODE as code', DB::raw('MAX(patient_info.home_bmy_score) as home_bmy_score')]);
                $homeFiled = "AEE04_CODE";
                break;
            case 5: //编码员
                $query->whereIn('patient_doctor_info.BMY_BH', $staffCodeArray)
                    ->groupBy('patient_doctor_info.BMY_BH')
                    ->selectRaw('patient_doctor_info.BMY_BH as name,patient_doctor_info.BMY_BH as code');
                $homeFiled = "AEE08_CODE";
                break;
        };

        //获取排名数据
        $data = $query
            ->addSelect([
                'patient_info.MED_REC_ID',
                DB::raw('COUNT(DISTINCT patient_info.MED_REC_ID) as bl_sum'), //病例总数
                DB::raw('SUM(100 - patient_info.home_bmy_score) as total_deduction'), //总扣分
                DB::raw('ROUND(AVG( patient_info.home_bmy_score),2) as avg_score'), //平均分
                DB::raw('COUNT(DISTINCT CASE WHEN patient_info.home_bmy_score < 100 THEN patient_info.MED_REC_ID ELSE NULL END) as qx_total_num'), //缺陷总例数
                //DB::raw('COUNT(home_quality.id) as qx_num'),//缺陷问题总数量
            ])
            ->get()->toArray();

        $total = count($data);

        $homeQualityTotal = array_sum(array_column($data, 'qx_total_num')); //获取总数量
        $depArray = Department::query()->pluck('dep_name', 'dep_id')->toArray();

        //循环处理医师排名数据
        foreach ($data as $k => $v) {
            $ksCode = $staffKeyArray[$v['code']] ?? ""; //所属科室code
            $v['dep_name'] = $depArray[$ksCode] ?? ""; //所属科室名称
            $v['total_deduction'] = round($v['total_deduction'], 1);
            $v['qx_total_num'] = (int)$v['qx_total_num'];
            //计算缺陷占比
            $v['qx_percentage'] = ($v['qx_total_num'] > 0 && $homeQualityTotal > 0) ? round($v['qx_total_num'] / $homeQualityTotal * 100, 2) : 0;
            $data[$k] = $v;
        }
        //endregion

        //导出
        $isExport = $request->post('is_export', 0);
        if ($isExport == 1) {
            $title = ['医生姓名', '医生工号', '医生科室', '病例总数', '总扣分', '平均得分', '缺陷总数', '缺陷占比'];
            array_unshift($data, $title);
            $csv = new CsvService();
            $csv->filename = $csv->charset('医生排名', 'UTF-8');
            return $csv->export($data);
        }


        //处理排序
        $orderByName = $request->post('prop', ''); //排序字段
        if (empty($orderByName))
            $orderByName = 'avg_score'; //如果为空则默认为平均分
        $orderBySort = $request->post('order', '') == 'ascending' ? 'asc' : 'desc';
        $data = collect($data)->sortBy($orderByName, SORT_REGULAR, $orderBySort)->toArray();

        // 实现PHP分页
        $page = (int)$request->post('page', 1);
        $pageSize = (int)$request->post('page_size', 10);

        // 计算总条数
        $total = count($data);
        // 截取分页数据
        $start = ($page - 1) * $pageSize;
        $pagedData = array_slice($data, $start, $pageSize);


        //少于10条数据补全10条,之前是前端处理补全，现在改为后端处理
        if ($total < 10) {
            $num = 10 - $total;
            for ($i = 0; $i < $num; $i++) {
                $data[] = [
                    'name' => "",
                    'code' => "",
                    'dep_name' => "",
                    'total_deduction' => "",
                    'avg_score' => "",
                    'bl_sum' => "",
                    'qx_total_num' => "",
                    'qx_zb' => "",
                ];
            }
        }

        return ToolsService::returnData(200, ['total' => $total, 'data' => $pagedData]);
    }

    /**
     * 医师排名-根据医师code下钻病历明细列表
     * 前端传入时间、code、sf_type，返回该医师名下的病历列表
     * @param Request $request
     * @return array|string|null
     */
    public function doctorRankingDrillList(Request $request)
    {
        //身份类型
        $syType = $request->post('sf_type', 0);
        //医师code
        $code = $request->post('code', '');
        if (empty($code)) {
            return ToolsService::returnData(200, ['count' => 0, 'list' => []]);
        }

        //数据权限-科室
        $depIds = UserService::getCurrentUserDep($request);
        if (empty($depIds)) {
            return ToolsService::returnData(200, ['count' => 0, 'list' => []]);
        }

        $query = PatientInfo::query()
            ->leftJoin('ZY_BRRY', 'ZY_BRRY.ZYH', '=', 'patient_info.MED_REC_ID')
            ->leftJoin('patient_doctor_info', 'patient_doctor_info.AAA28', '=', 'patient_info.MED_REC_ID');

        if (is_array($depIds)) {
            $query->whereIn('ZY_BRRY.BRKS', $depIds);
        }

        //时间范围(起始时间)
        $startTime = $request->post('start_time', '');
        $startTime = empty($startTime) ? date('Y-01-01 00:00:00') : Carbon::createFromFormat('Ymd', $startTime)->format('Y-m-d 00:00:00');
        //时间范围(结束时间)
        $endTime = $request->post('end_time', '');
        $endTime = empty($endTime) ? date('Y-m-d 23:59:59') : Carbon::createFromFormat('Ymd', $endTime)->format('Y-m-d 23:59:59');
        $query->whereBetween('patient_info.AAC01', [$startTime, $endTime]);

        //根据身份类型确定过滤字段，与 doctorRanking 保持一致
        switch ($syType) {
            case 1: //科主任
                $query->where('patient_doctor_info.AEE01_CODE', $code);
                break;
            case 2: //主任（副主任）医师
                $query->where('patient_doctor_info.AEE02_CODE', $code);
                break;
            case 3: //主治医师
                $query->where('patient_doctor_info.AEE03_CODE', $code);
                break;
            case 4: //住院医师
                $query->where('patient_doctor_info.AEE04_CODE', $code);
                break;
            case 5: //编码员
                $query->where('patient_doctor_info.BMY_BH', $code);
                break;
        }

        //是否导出
        $isExport = $request->post('is_export', 0);
        if ($isExport == 1) {
            $page = 1;
            $pageSize = 100000;
        } else {
            $page = (int)$request->post('page', 1);
            $pageSize = (int)$request->post('page_size', 10);
        }

        $field = [
            'patient_info.AAA28',        //病案号
            'patient_info.AAC01',        //出院时间
            'ZY_BRRY.BRKS',              //出院科室code(与筛选保持一致)
            'patient_doctor_info.AEE01', //科主任
            'patient_doctor_info.AEE02', //主任（副主任）医师
            'patient_doctor_info.AEE03', //主治医师
            'patient_doctor_info.AEE04', //住院医师
            'patient_info.home_bmy_score', //分数
        ];

        $data = $query->orderBy('patient_info.AAC01', 'desc')
            ->paginate($pageSize, $field, 'page', $page)
            ->toArray();

        //出院科室：BRKS关联科室表取dep_name，与筛选保持一致
        $depArray = Department::query()->pluck('dep_name', 'dep_id')->toArray();

        $returnData = [];
        if (!empty($data['data'])) {
            foreach ($data['data'] as $value) {
                $score = $value['home_bmy_score'];
                $level = HomeQualityService::$bl_level[HomeQualityService::levelJs($score, 2)];
                $arr = [
                    'AAA28' => $value['AAA28'] . "\t", //病案号
                    'AAC01' => !empty($value['AAC01']) ? $value['AAC01'] . "\t" : '', //出院时间
                    'AAC11N' => $depArray[$value['BRKS']] ?? '', //出院科室
                    'AEE01' => $value['AEE01'] ?? '', //科主任
                    'AEE02' => $value['AEE02'] ?? '', //主任（副主任）医师
                    'AEE03' => $value['AEE03'] ?? '', //主治医师
                    'AEE04' => $value['AEE04'] ?? '', //住院医师
                    'home_bmy_score' => $score, //分数
                    'level' => $level, //等级
                ];
                $returnData[] = $arr;
            }
        }

        if ($isExport == 1) {
            $title = ['病案号', '出院时间', '出院科室', '科主任', '主任(副主任)医师', '主治医师', '住院医师', '分数', '等级'];
            array_unshift($returnData, $title);

            $csv = new CsvService();
            $csv->filename = $csv->charset('医师排名明细', 'UTF-8');
            return $csv->export($returnData);
        }

        return ToolsService::returnData(200, ['count' => $data['total'], 'list' => $returnData]);
    }

    /**
     * 医生排名-病历列表
     * @param Request $request
     * @return array|string|null
     */
    public function doctorRankingBlList(Request $request)
    {
        $startTime = $request->post('start_time', '');
        $endTime = $request->post('end_time', '');
        $AAC11N = $request->post('AAC11N', '');
        $level = $request->post('level', '');
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

        $piService = new ElasticsearchService('patient_info');

        $must = [];
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
        if ($AAC11N) {
            $must[] = ['match_phrase' => ['AAC11N' => $AAC11N]];
        }
        if ($level == '优') {
            $must[] = ['range' => ['home_bmy_score' => ['gte' => 97]]];
        } elseif ($level == '良') {
            $must[] = ['range' => ['home_bmy_score' => ['gte' => 90, 'lte' => 96.9]]];
        } elseif ($level == '中') {
            $must[] = ['range' => ['home_bmy_score' => ['gte' => 75, 'lte' => 89.9]]];
        } elseif ($level == '差') {
            $must[] = ['range' => ['home_bmy_score' => ['lte' => 74]]];
        } else {
            $must[] = ['range' => ['home_bmy_score' => ['gte' => 0, 'lte' => 100]]];
        }

        $should = [];
        foreach ($doctorList as $doctorName) {
            $should[] = ['match_phrase' => ['ZZYISXM' => $doctorName]];
            $should[] = ['match_phrase' => ['ZYYSXM' => $doctorName]];
            $should[] = ['match_phrase' => ['ZZYSXM' => $doctorName]];
            $should[] = ['match_phrase' => ['KZRXM' => $doctorName]];
            $should[] = ['match_phrase' => ['ZHFZRYSXM' => $doctorName]];
        }

        $params = $piService->clearMust()
            ->queryByMustBatch($must)
            ->queryByShouldBatch($should)
            ->minimumShouldMatch()
            ->trackTotalHits()
            ->orderBy($sort[0], $sort[1])
            ->paginate($page, $pageSize)
            ->getParams();
        $res = app('es')->search($params);
        $patientInfoData = $piService->getDataByEs($res);

        $returnData = [];
        if (!empty($patientInfoData[0])) {
            foreach ($patientInfoData[0] as $patientInfo) {
                $level = HomeQualityService::$bl_level[HomeQualityService::levelJs($patientInfo['home_bmy_score'], 2)];
                $arr = [
                    'AAA28' => $patientInfo['AAA28'] . "\t",
                    'ZYH' => $patientInfo['MED_REC_ID'],
                    'AAC01' => $patientInfo['AAC01'] . "\t",
                    'AAC11N' => $patientInfo['AAC11N'],
                    'KZRXM' => $patientInfo['KZRXM'],
                    'ZHFZRYSXM' => $patientInfo['ZHFZRYSXM'],
                    'ZZYSXM' => $patientInfo['ZZYSXM'],
                    'ZYYSXM' => $patientInfo['ZYYSXM'],
                    'ZZYISXM' => $patientInfo['ZZYISXM'],
                    'home_bmy_score' => $patientInfo['home_bmy_score'],
                    'level' => $level,
                ];

                if ($isExport == 1) {
                    unset($arr['ZYH']);
                }

                $returnData[] = $arr;
            }
        }

        if ($isExport == 1) {
            $title = ['住院号码', '出院时间', '出院科室', '科主任', '主任(副主任)医师', '主治医生', '住院医生', '医疗组长', '病历评分', '病历等级'];
            array_unshift($returnData, $title);

            $csv = new CsvService();
            $csv->filename = $csv->charset('医生排名', 'UTF-8');

            return $csv->export($returnData);
        }

        return ToolsService::returnData(200, ['count' => $patientInfoData[1], 'list' => $returnData]);
    }

    /**
     * 医师排名-缺陷字段
     * @param Request $request
     * @return array|string|null
     */
    public function doctorErrorRanking(Request $request)
    {
        $query = HomeQuality::query()->leftJoin('patient_doctor_info as b', 'b.AAA28', '=', 'home_quality.ZYH')
            ->leftJoin('patient_info as c', 'c.MED_REC_ID', '=', 'home_quality.ZYH');
        //时间范围
        $time = $request->post('time', []);
        if ($time[0] && $time[1]) {
            $startTime = date('Y-m-d', strtotime($time[0])) . ' 00:00:00';
            $endTime = date('Y-m-d', strtotime($time[1])) . ' 23:59:59';
            $query->whereBetween('home_quality.AAC01', [$startTime, $endTime]);
        } else {
            $startTime = date('Y-01-01') . ' 00:00:00';
            $endTime = date('Y-12-31') . ' 23:59:59';
            $query->whereBetween('home_quality.AAC01', [$startTime, $endTime]);
        }

        $doctorCodeArray = $request->post('doctor_code', []);
        if (!empty($doctorCodeArray)) {
            $sf_type = $request->post('sf_type', '');
            switch ($sf_type) {
                case 1:
                    $query->whereIn('home_quality.AEE01_CODE', $doctorCodeArray);
                    break;
                case 2:
                    $query->whereIn('home_quality.AEE02_CODE', $doctorCodeArray);
                    break;
                case 3:
                    $query->whereIn('home_quality.AEE03_CODE', $doctorCodeArray);
                    break;
                case 4:
                    $query->whereIn('home_quality.AEE04_CODE', $doctorCodeArray);
                    break;
                case 5:
                    $query->whereIn('home_quality.AEE08_CODE', $doctorCodeArray);
                    break;
            }
        };

        //所属科室
        $department = $request->get('department', '');
        if (!empty($department))
            $query->whereIn('home_quality.AAC02C', $department);

        //住院号
        $AAA28 = $request->post('AAA28', '');

        if (!empty($AAA28))
            $query->where('home_quality.AAA28', $AAA28);
        //是否导出与页吗
        $isExport = $request->get('is_export', 0);
        if ($isExport === 1) {
            $page = 1;
            $pageSize = 10000;
        } else {
            $page = $request->get('page', 1);
            $pageSize = $request->get('page_size', 10);
        }

        $data = $query->select('home_quality.AAA28', 'home_quality.AAC01', 'home_quality.ZYH', 'home_quality.error_rule', 'b.AEE01 as KZRXM', 'b.AEE02 as ZHFZRYSXM', 'b.AEE03 as ZZYSXM', 'b.AEE04 as ZYYSXM', 'b.YLZZ as ZZYISXM', 'c.home_bmy_score')->paginate($pageSize, $page)->toArray();


        $errorRuleData = ErrorRule::query()->get(['id', 'desc', 'down'])->toArray();
        $errorRuleData = array_column($errorRuleData, null, 'id');

        foreach ($data['data'] as $k => $v) {
            $errorRow = $errorRuleData[$v['error_rule']];
            $v['down'] = $errorRow['down'];
            $v['desc'] = $errorRow['desc'];
            if ($isExport === 1)
                unset($v['error_rule']); //导出时删除error_rule字段
            $data['data'][$k] = $v;
        }

        if ($isExport == 1) {
            $title = ['住院号码', '出院时间', '科主任', '主任(副主任)医师', '主治医生', '住院医生', '医疗组长', '病历评分', '缺陷描述', '扣分'];
            array_unshift($data['data'], $title);

            $csv = new CsvService();
            $csv->filename = $csv->charset('医生排名', 'UTF-8');
            return $csv->export($data['data']);
        }
        return ToolsService::returnData(200, $data);

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

    //首页质控医生站详情页字段调整
    private function fieldEdit($data)
    {
        $replaceMap = [
            /* "AAB11N" => "BFRY",
            "AAC11N" => "AAC03",
            "AAD01C" => "ZKKB",
            "AEB01" => "GMYW",
            "AED04" => "ZKRQ",
            "D11" => "YBYLFWF",
            "D12" => "YBZLCZF",
            "D13" => "HLF",
            "D14" => "ZHYLFWLQTFY",
            "D15" => "ZHYLFWLQTFY",
            "D16" => "BLZDF",
            "D17" => "SYSZDF",
            "D18" => "YXXZDF",
            "D19" => "LCZDXMF",
            "D19X01" => "LCWLZLF",
            "D20" => "SSZLF",
            "D20X01" => "MZF",
            "D20X02" => "SSF",
            "D21" => "KFF",
            "D22" => "ZYZLF",
            "D23" => "XYF",
            "D23X01" => "KJYWF",
            "D24" => "ZCHENGYF",
            "D25" => "ZCAOYF",
            "D26" => "XF",
            "D27" => "BDBLZPF",
            "D28" => "QDBLZPF",
            "D29" => "NXYZLZPF",
            "D30" => "XBYZLZPF",
            "D31" => "JCYYCXYYCLF",
            "D32" => "ZLYYCXYYCLF",
            "D33" => "SSYYCXYYCLF",
            "D34" => "QTF", */];
        if (isset($data['diagnosis_list'])) {
            $data['diagnosis'] = $data['diagnosis_list'];
            unset($data['diagnosis_list']);
        }
        // 遍历数组并替换下标
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $replaceMap)) {
                $newKey = $replaceMap[$key];
                unset($data[$key]); // 删除旧下标
                $data[$newKey] = $value; // 设置新下标和对应的值
            }
        }
        //$data['AAB02C'] = $data['RYBFMC'] ?? '';
        return $data;
    }
}
