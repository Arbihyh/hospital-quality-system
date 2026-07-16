<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exports\DataExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\ToolsService;
use Maatwebsite\Excel\Facades\Excel;

class QualityReportDrillController extends Controller
{
    /**
     * 日期格式化：兼容 20260101 / 2026-01-01 等格式
     * @param string|null $date 原始日期
     * @param string $suffix 时间后缀：'start' 补 00:00:00，'end' 补 23:59:59
     * @return string|null
     */
    private function formatDate($date, $suffix = 'start')
    {
        if (empty($date)) {
            return null;
        }
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return null;
        }
        $dateOnly = date('Y-m-d', $timestamp);
        return $suffix === 'end' ? $dateOnly . ' 23:59:59' : $dateOnly . ' 00:00:00';
    }
    /**
     * 质控病历数下钻接口（全部病历）
     */
    public function totalCasesDrillDown(Request $request)
    {
        return $this->drillDown($request);
    }

    /**
     * 缺陷病历数下钻接口（仅有缺陷的病历）
     */
    public function defectCasesDrillDown(Request $request)
    {
        return $this->drillDown($request, true);
    }

    /**
     * 公共下钻查询逻辑
     * @param Request $request
     * @param bool $onlyDefect 是否只查有缺陷的病历
     */
    private function drillDown(Request $request, $onlyDefect = false)
    {
        // 基础过滤参数
        $dischargeStart = $this->formatDate($request->input('discharge_date_start'), 'start');
        $dischargeEnd   = $this->formatDate($request->input('discharge_date_end'), 'end');
        $dischargeDepts = $request->input('discharge_departments', []); // 多选
        $caseNumber     = $request->input('case_number');
        $admissionDepts = $request->input('admission_departments', []); // 多选
        $admissionStart = $this->formatDate($request->input('admission_date_start'), 'start');
        $admissionEnd   = $this->formatDate($request->input('admission_date_end'), 'end');
        $campusList     = $request->input('campus', []); // 多选
        $recordLevels   = $request->input('record_levels', []); // 甲、乙、丙
        $patientStatus  = $request->input('patient_status'); // 在院、出院、空、全部
        $catalogStatus  = $request->input('catalog_status'); // 已编目、未编目、空、全部
        $isDanfou       = $request->input('is_danfou'); // 有、无、全部
        $residentDoctor = $request->input('resident_doctor', []); // 住院医师
        $page = max(1, (int)$request->input('page', 1));
        $limit = max(1, (int)$request->input('limit', 20));
        $isExport = (int)$request->input('is_export', 0);

        $ruleId = $request->input('rule_id');
        $ruleType = $request->input('rule_type'); // 1=病历, 2=首页，未传时规则名称默认按病历取
        $ruleIds = [];
        if (!empty($ruleId)) {
            $ruleIds = is_array($ruleId) ? $ruleId : explode(',', $ruleId);
            $ruleIds = array_filter(array_map('trim', $ruleIds));
        }

        // 基础患者信息查询
        $query = DB::table('patient_info as pi')
            ->join('ZY_BRRY as br', 'pi.MED_REC_ID', '=', 'br.ZYH')
            ->join('patient_info_v2 as pi2', 'pi.MED_REC_ID', '=', 'pi2.ZYH')
            ->leftJoin('patient_hospital_info as phi', 'pi.MED_REC_ID', '=', 'phi.AAA28')
            ->leftJoin('main_diagnosis as md', 'pi.MED_REC_ID', '=', 'md.AAA28')
            ->leftJoin('main_operation as mo', 'pi.MED_REC_ID', '=', 'mo.AAA28')
            ->leftJoin('patient_doctor_info as pdi', 'pi.MED_REC_ID', '=', 'pdi.AAA28');

        // 缺陷病历：只查在 case_quality 或 home_quality 中有记录的
        if ($onlyDefect) {
            $query->where(function ($q) use ($ruleIds, $ruleType) {
                $allowCase = empty($ruleType) || $ruleType == 1;
                $allowHome = empty($ruleType) || $ruleType == 2;

                if (!$allowCase && !$allowHome) {
                    $q->whereRaw('1 = 0');
                    return;
                }

                if ($allowCase) {
                    $q->whereExists(function ($sub) use ($ruleIds) {
                        $sub->select(DB::raw(1))
                            ->from('case_quality')
                            ->whereColumn('case_quality.JZHM', 'pi.MED_REC_ID');
                        if (!empty($ruleIds)) {
                            $sub->whereIn('case_quality.rule_id', $ruleIds);
                        }
                    });
                }

                if ($allowHome) {
                    $method = $allowCase ? 'orWhereExists' : 'whereExists';
                    $q->$method(function ($sub) use ($ruleIds) {
                        $sub->select(DB::raw(1))
                            ->from('home_quality')
                            ->whereColumn('home_quality.ZYH', 'pi.MED_REC_ID')
                            ->where('home_quality.is_del', 0);
                        if (!empty($ruleIds)) {
                            $sub->whereIn('home_quality.error_rule', $ruleIds);
                        }
                    });
                }
            });
        }

        // 出院日期过滤
        if ($dischargeStart && $dischargeEnd) {
            $query->whereBetween('pi.AAC01', [$dischargeStart, $dischargeEnd]);
        } elseif ($dischargeStart) {
            $query->where('pi.AAC01', '>=', $dischargeStart);
        } elseif ($dischargeEnd) {
            $query->where('pi.AAC01', '<=', $dischargeEnd);
        }

        // 出院科室（多选）
        if (!empty($dischargeDepts)) {
            $query->whereIn('pi2.AAC02C', $dischargeDepts);
        }

        // 病案号（精确）
        if ($caseNumber) {
            $query->where('br.AAA28', $caseNumber);
        }

        // 入院科室（多选）
        if (!empty($admissionDepts)) {
            $query->whereIn('pi2.AAB02C', $admissionDepts);
        }

        // 入院日期过滤
        if ($admissionStart && $admissionEnd) {
            $query->whereBetween('pi.AAB01', [$admissionStart, $admissionEnd]);
        } elseif ($admissionStart) {
            $query->where('pi.AAB01', '>=', $admissionStart);
        } elseif ($admissionEnd) {
            $query->where('pi.AAB01', '<=', $admissionEnd);
        }

        // 院区（多选）
        if (!empty($campusList)) {
            $query->whereIn('pi.YQ_CODE', $campusList);
        }

        // 病历等级（甲、乙、丙）
        if (!empty($recordLevels)) {
            $query->whereIn('pi.computed_grade', $recordLevels);
        }

        // 患者状态
        if ($patientStatus && $patientStatus !== '全部') {
            $statusMap = ['在院' => 1, '出院' => 2];
            $query->where('pi.in_hospital', $statusMap[$patientStatus] ?? null);
        }

        // 编码状态
        if ($catalogStatus && $catalogStatus !== '全部') {
            $catalogMap = ['已编目' => 1, '未编目' => 0];
            $query->where('pi.IS_CATA', $catalogMap[$catalogStatus] ?? null);
        }

        // 是否有单否问题
        if ($isDanfou && $isDanfou !== '全部') {
            $query->where('pi.is_danfou', $isDanfou);
        }

        // 住院医师筛选（多选）
        if (!empty($residentDoctor)) {
            $query->whereIn('pi.AEE04_CODE', is_array($residentDoctor) ? $residentDoctor : [$residentDoctor]);
        }

        // 分页查询，导出时取当前筛选条件下的全部数据
        $total = $query->count();
        $recordQuery = $query->orderBy('pi.AAC01', 'desc')
            ->select(
                'br.BRXM as name',
                DB::raw('IFNULL(pi.AAA04, pi.AAA40) as age'),
                'br.AAA28 as case_number',
                'pi.MED_REC_ID as patient_id',
                'pi.computed_grade as record_grade',
                'pi.case_score as case_score',
                'pi.home_score as home_score',
                DB::raw('IFNULL(pi.case_score, 0) + IFNULL(pi.home_score, 0) as record_grade_score'),
                'pi.AAB01 as admission_date',
                'pi.AAC01 as discharge_date',
                'pi2.AAB02C as admission_department',
                'phi.AAB03 as admission_ward',
                'pi.YQ_CODE as campus',
                'pi2.AAC02C as discharge_department',
                'phi.AAC03 as discharge_ward',
                'pi.AAC04 as hospital_days',
                'pi.in_hospital as patient_status_raw',
                'pi.IS_CATA as catalog_status_raw',
                'md.RYQK as admission_status',
                DB::raw('LEFT(md.ICD10_ID1, 3) as diagnosis_code'),
                'md.ICD10_NAME as diagnosis_name',
                'md.CYQK as discharge_status',
                'pdi.AEE01 as chief_physician',
                'pdi.AEE02 as deputy_chief_physician',
                'pdi.AEE03 as attending_physician',
                'pdi.AEE04 as resident_physician',
                'pdi.AEE08 as coder',
                'mo.ICD9_ID1 as operation_code',
                'mo.ICD9_NAME as operation_name',
                'mo.OPE_DATE as operation_date',
                'mo.OPE_MAN_NAME as surgeon',
                'mo.FRIST_ASSISTANT_NAME as first_assistant',
                'mo.SECOND_ASSISTANT_NAME as second_assistant',
                'mo.HOCUS_WAY_MC as anesthesia_method',
                'mo.YHDJ as wound_healing_grade',
                'pi.is_danfou as is_danfou'
            );

        if (!$isExport) {
            $recordQuery->offset(($page - 1) * $limit)->limit($limit);
        }

        $records = $recordQuery->get();

        // 结果后处理：映射状态文字
        $ruleName = $this->getRuleNameByIds($ruleIds, $ruleType);
        $list = [];
        foreach ($records as $item) {
            $statusTextMap = [1 => '在院', 2 => '出院'];
            $patientStatusText = $statusTextMap[$item->patient_status_raw] ?? '空';

            $catalogTextMap = [1 => '已编目', 0 => '未编目'];
            $catalogStatusText = $catalogTextMap[$item->catalog_status_raw] ?? '空';
            // 根据 admission_department 和 discharge_department 关联科室表获取 dep_name（科室名称）
            $admissionDepName = '';
            $dischargeDepName = '';
            if (!empty($item->admission_department)) {
                $adDept = DB::table('department')
                    ->where('dep_id', $item->admission_department)
                    ->value('dep_name');
                if ($adDept) {
                    $admissionDepName = $adDept;
                }
            }
            if (!empty($item->discharge_department)) {
                $disDept = DB::table('department')
                    ->where('dep_id', $item->discharge_department)
                    ->value('dep_name');
                if ($disDept) {
                    $dischargeDepName = $disDept;
                }
            }

            $list[] = [
                'name' => $item->name, //姓名
                'age' => $item->age, //年龄
                'case_number' => $item->case_number, //病案号
                'zyh' => $item->patient_id, //住院号
                'rule_name' => $ruleName, //规则名称
                'record_grade' => $item->record_grade, //病历等级
                'record_grade_score' => $item->record_grade_score, //病历等级得分
                'case_score' => $item->case_score, //病历得分
                'home_score' => $item->home_score, //首页得分
                'admission_date' => $item->admission_date, //入院日期
                'admission_department' => $admissionDepName, //入院科室
                //'admission_ward' => $item->admission_ward, //入院病房
                'discharge_date' => $item->discharge_date, //出院日期
                'discharge_department' => $dischargeDepName, //出院科室
                //'discharge_ward' => $item->discharge_ward, //出院病房
                'hospital_days' => $item->hospital_days, //住院天数
                'admission_status' => $item->admission_status, //入院状态
                'diagnosis_code' => $item->diagnosis_code, //诊断代码
                'diagnosis_name' => $item->diagnosis_name, //诊断名称
                'discharge_status' => $item->discharge_status, //出院状态
                'chief_physician' => $item->chief_physician, //主诊医生
                'deputy_chief_physician' => $item->deputy_chief_physician, //副主诊医生
                'attending_physician' => $item->attending_physician, //主治医生
                'resident_physician' => $item->resident_physician, //住院医生
                'coder' => $item->coder, //编码医生
                'operation_code' => $item->operation_code, //手术代码
                'operation_name' => $item->operation_name, //手术名称
                'operation_date' => $item->operation_date, //手术日期
                'surgeon' => $item->surgeon, //主刀医生
                'first_assistant' => $item->first_assistant, //第一助手
                'second_assistant' => $item->second_assistant, //第二助手
                'anesthesia_method' => $item->anesthesia_method, //麻醉方式
                'wound_healing_grade' => $item->wound_healing_grade, //伤口愈合等级
                'is_danfou' => $item->is_danfou, //单复
                'campus' => $item->campus, //院区
                'patient_status' => $patientStatusText, //患者状态
                'catalog_status' => $catalogStatusText, //编目状态
            ];
        }

        if ($isExport) {
            return $this->exportDrillDownExcel($list, $onlyDefect ? '缺陷病历数下钻' : '质控病历数下钻');
        }

        $data = [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'list' => $list,
        ];

        return ToolsService::returnData(200, $data, '获取成功');
    }

    /**
     * 根据规则类型获取规则名称。
     *
     * @param array $ruleIds 规则ID列表
     * @param mixed $ruleType 规则类型：1=病历，2=首页
     * @return string
     */
    private function getRuleNameByIds($ruleIds, $ruleType)
    {
        if (empty($ruleIds)) {
            return '';
        }

        $ruleType = empty($ruleType) ? 1 : (int)$ruleType;
        $normalRuleIds = [];
        $ruleSettingIds = [];

        foreach ($ruleIds as $ruleId) {
            $ruleId = (int)$ruleId;
            if ($ruleId <= 0) {
                continue;
            }

            if ($ruleId > 1000000) {
                $ruleSettingIds[] = $ruleId - 1000000;
            } else {
                $normalRuleIds[] = $ruleId;
            }
        }

        $ruleMap = [];
        if (!empty($normalRuleIds)) {
            if ($ruleType === 2) {
                $ruleMap = DB::table('error_rule')
                    ->whereIn('id', $normalRuleIds)
                    ->pluck('desc', 'id')
                    ->toArray();
            } else {
                $ruleMap = DB::table('case_rule')
                    ->whereIn('id', $normalRuleIds)
                    ->pluck('notice', 'id')
                    ->toArray();
            }
        }

        $ruleSettingMap = [];
        if (!empty($ruleSettingIds)) {
            $ruleSettingMap = DB::table('rule_setting')
                ->whereIn('id', $ruleSettingIds)
                ->pluck('description', 'id')
                ->toArray();
        }

        $ruleNames = [];
        foreach ($ruleIds as $ruleId) {
            $ruleId = (int)$ruleId;
            if ($ruleId <= 0) {
                continue;
            }

            if ($ruleId > 1000000) {
                $ruleName = $ruleSettingMap[$ruleId - 1000000] ?? '';
            } else {
                $ruleName = $ruleMap[$ruleId] ?? '';
            }

            if ($ruleName !== '') {
                $ruleNames[] = $ruleName;
            }
        }

        return implode('，', array_values(array_unique($ruleNames)));
    }

    /**
     * 导出病历下钻 Excel。
     *
     * @param array $list 导出数据
     * @param string $filePrefix 文件名前缀
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    private function exportDrillDownExcel($list, $filePrefix)
    {
        $title = [[
            'name' => '姓名',
            'age' => '年龄',
            'case_number' => '病案号',
            'zyh' => '住院号',
            'rule_name' => '规则名称',
            'record_grade' => '病历等级',
            'record_grade_score' => '病历等级得分',
            'case_score' => '病历得分',
            'home_score' => '首页得分',
            'admission_date' => '入院日期',
            'admission_department' => '入院科室',
            'discharge_date' => '出院日期',
            'discharge_department' => '出院科室',
            'hospital_days' => '住院天数',
            'admission_status' => '入院状态',
            'diagnosis_code' => '诊断代码',
            'diagnosis_name' => '诊断名称',
            'discharge_status' => '出院状态',
            'chief_physician' => '主诊医生',
            'deputy_chief_physician' => '副主诊医生',
            'attending_physician' => '主治医生',
            'resident_physician' => '住院医生',
            'coder' => '编码医生',
            'operation_code' => '手术代码',
            'operation_name' => '手术名称',
            'operation_date' => '手术日期',
            'surgeon' => '主刀医生',
            'first_assistant' => '第一助手',
            'second_assistant' => '第二助手',
            'anesthesia_method' => '麻醉方式',
            'wound_healing_grade' => '伤口愈合等级',
            'is_danfou' => '单否',
            'campus' => '院区',
            'patient_status' => '患者状态',
            'catalog_status' => '编目状态',
        ]];

        $fileName = $filePrefix . '_' . date('YmdHis') . '.xlsx';
        return Excel::download(new DataExport($title, $list), $fileName);
    }

    /**
     * 导出缺陷数量下钻 Excel。
     *
     * @param array $list 导出数据
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    private function exportDefectCountDrillDownExcel($list)
    {
        $title = [[
            'bl_type' => '质控类型',
            'notice' => '缺陷描述',
            'name' => '姓名',
            'age' => '年龄',
            'case_number' => '病案号',
            'zyh' => '住院号',
            'record_grade' => '病历等级',
            'record_grade_score' => '病历等级得分',
            'case_score' => '病历得分',
            'home_score' => '首页得分',
            'admission_date' => '入院日期',
            'admission_department' => '入院科室',
            'discharge_date' => '出院日期',
            'discharge_department' => '出院科室',
            'hospital_days' => '住院天数',
            'admission_status' => '入院状态',
            'diagnosis_code' => '诊断代码',
            'diagnosis_name' => '诊断名称',
            'discharge_status' => '出院状态',
            'chief_physician' => '主诊医生',
            'deputy_chief_physician' => '副主诊医生',
            'attending_physician' => '主治医生',
            'resident_physician' => '住院医生',
            'coder' => '编码医生',
            'operation_code' => '手术代码',
            'operation_name' => '手术名称',
            'operation_date' => '手术日期',
            'surgeon' => '主刀医生',
            'first_assistant' => '第一助手',
            'second_assistant' => '第二助手',
            'anesthesia_method' => '麻醉方式',
            'wound_healing_grade' => '伤口愈合等级',
            'is_danfou' => '单否',
            'campus' => '院区',
            'patient_status' => '患者状态',
            'catalog_status' => '编目状态',
        ]];

        $fileName = '缺陷数量下钻_' . date('YmdHis') . '.xlsx';
        return Excel::download(new DataExport($title, $list), $fileName);
    }

    /**
     * 缺陷数量下钻接口（以缺陷为主表，一个患者可能有多条缺陷记录）
     * 额外返回 bl_type（首页/病历）和 notice（缺陷描述）
     */
    public function defectCountDrillDown(Request $request)
    {
        // 基础过滤参数
        $dischargeStart = $this->formatDate($request->input('discharge_date_start'), 'start');
        $dischargeEnd   = $this->formatDate($request->input('discharge_date_end'), 'end');
        $dischargeDepts = $request->input('discharge_departments', []);
        $caseNumber     = $request->input('case_number');
        $admissionDepts = $request->input('admission_departments', []);
        $admissionStart = $this->formatDate($request->input('admission_date_start'), 'start');
        $admissionEnd   = $this->formatDate($request->input('admission_date_end'), 'end');
        $campusList     = $request->input('campus', []);
        $recordLevels   = $request->input('record_levels', []);
        $patientStatus  = $request->input('patient_status');
        $catalogStatus  = $request->input('catalog_status');
        $isDanfou       = $request->input('is_danfou');
        $residentDoctor = $request->input('resident_doctor', []);
        $page = max(1, (int)$request->input('page', 1));
        $limit = max(1, (int)$request->input('limit', 20));
        $isExport = (int)$request->input('is_export', 0);

        $ruleId = $request->input('rule_id');
        $ruleType = $request->input('rule_type'); // 1=病历, 2=首页

        $ruleIds = [];
        if (!empty($ruleId)) {
            $ruleIds = is_array($ruleId) ? $ruleId : explode(',', $ruleId);
            $ruleIds = array_filter(array_map('trim', $ruleIds));
        }

        $allowCase = empty($ruleType) || $ruleType == 1;
        $allowHome = empty($ruleType) || $ruleType == 2;

        // 构建缺陷联合子查询：case_quality(病历) UNION ALL home_quality(首页)
        $caseDefectQuery = DB::table('case_quality')
            ->select(
                'JZHM as MED_REC_ID',
                'rule_id',
                DB::raw("'病历' as bl_type")
            );
        if (!$allowCase) {
            $caseDefectQuery->whereRaw('1 = 0');
        } elseif (!empty($ruleIds)) {
            $caseDefectQuery->whereIn('rule_id', $ruleIds);
        }

        $homeDefectQuery = DB::table('home_quality')
            ->where('is_del', 0)
            ->select(
                'ZYH as MED_REC_ID',
                'error_rule as rule_id',
                DB::raw("'首页' as bl_type")
            );
        if (!$allowHome) {
            $homeDefectQuery->whereRaw('1 = 0');
        } elseif (!empty($ruleIds)) {
            $homeDefectQuery->whereIn('error_rule', $ruleIds);
        }

        // 以缺陷联合表为主表，关联患者信息
        $query = DB::table(DB::raw("({$caseDefectQuery->toSql()} UNION ALL {$homeDefectQuery->toSql()}) as defects"))
            ->mergeBindings($caseDefectQuery)
            ->mergeBindings($homeDefectQuery)
            ->join('patient_info as pi', 'defects.MED_REC_ID', '=', 'pi.MED_REC_ID')
            ->join('patient_info_v2 as pi2', 'defects.MED_REC_ID', '=', 'pi2.ZYH')
            ->join('ZY_BRRY as br', 'pi.MED_REC_ID', '=', 'br.ZYH')
            ->leftJoin('patient_hospital_info as phi', 'pi.MED_REC_ID', '=', 'phi.AAA28')
            ->leftJoin('main_diagnosis as md', 'pi.MED_REC_ID', '=', 'md.AAA28')
            ->leftJoin('main_operation as mo', 'pi.MED_REC_ID', '=', 'mo.AAA28')
            ->leftJoin('patient_doctor_info as pdi', 'pi.MED_REC_ID', '=', 'pdi.AAA28');

        // 出院日期
        if ($dischargeStart && $dischargeEnd) {
            $query->whereBetween('pi.AAC01', [$dischargeStart, $dischargeEnd]);
        } elseif ($dischargeStart) {
            $query->where('pi.AAC01', '>=', $dischargeStart);
        } elseif ($dischargeEnd) {
            $query->where('pi.AAC01', '<=', $dischargeEnd);
        }

        // 出院科室
        if (!empty($dischargeDepts)) {
            $query->whereIn('pi2.AAC02C', $dischargeDepts);
        }

        // 病案号
        if ($caseNumber) {
            $query->where('br.AAA28', $caseNumber);
        }

        // 入院科室
        if (!empty($admissionDepts)) {
            $query->whereIn('pi2.AAB02C', $admissionDepts);
        }

        // 入院日期
        if ($admissionStart && $admissionEnd) {
            $query->whereBetween('pi.AAB01', [$admissionStart, $admissionEnd]);
        } elseif ($admissionStart) {
            $query->where('pi.AAB01', '>=', $admissionStart);
        } elseif ($admissionEnd) {
            $query->where('pi.AAB01', '<=', $admissionEnd);
        }

        // 院区
        if (!empty($campusList)) {
            $query->whereIn('pi.YQ_CODE', $campusList);
        }

        // 病历等级
        if (!empty($recordLevels)) {
            $query->whereIn('pi.computed_grade', $recordLevels);
        }

        // 患者状态
        if ($patientStatus && $patientStatus !== '全部') {
            $statusMap = ['在院' => 1, '出院' => 2];
            $query->where('pi.in_hospital', $statusMap[$patientStatus] ?? null);
        }

        // 编码状态
        if ($catalogStatus && $catalogStatus !== '全部') {
            $catalogMap = ['已编目' => 1, '未编目' => 0];
            $query->where('pi.IS_CATA', $catalogMap[$catalogStatus] ?? null);
        }

        // 是否有单否问题
        if ($isDanfou && $isDanfou !== '全部') {
            $query->where('pi.is_danfou', $isDanfou);
        }

        // 住院医师筛选（多选）
        if (!empty($residentDoctor)) {
            $query->whereIn('pi.AEE04_CODE', is_array($residentDoctor) ? $residentDoctor : [$residentDoctor]);
        }

        // 分页查询，导出时取当前筛选条件下的全部数据
        $total = $query->count();
        $recordQuery = $query->orderBy('pi.AAC01', 'desc')
            ->select(
                'defects.rule_id',
                'defects.bl_type',
                'br.BRXM as name',
                DB::raw('IFNULL(pi.AAA04, pi.AAA40) as age'),
                'br.AAA28 as case_number',
                'pi.MED_REC_ID as patient_id',
                'pi.computed_grade as record_grade',
                'pi.case_score as case_score',
                'pi.home_score as home_score',
                DB::raw('IFNULL(pi.case_score, 0) + IFNULL(pi.home_score, 0) as record_grade_score'),
                'pi.AAB01 as admission_date',
                'pi.AAC01 as discharge_date',
                'pi2.AAB02C as admission_department',
                'phi.AAB03 as admission_ward',
                'pi.YQ_CODE as campus',
                'pi2.AAC02C as discharge_department',
                'phi.AAC03 as discharge_ward',
                'pi.AAC04 as hospital_days',
                'pi.in_hospital as patient_status_raw',
                'pi.IS_CATA as catalog_status_raw',
                'md.RYQK as admission_status',
                DB::raw('LEFT(md.ICD10_ID1, 3) as diagnosis_code'),
                'md.ICD10_NAME as diagnosis_name',
                'md.CYQK as discharge_status',
                'pdi.AEE01 as chief_physician',
                'pdi.AEE02 as deputy_chief_physician',
                'pdi.AEE03 as attending_physician',
                'pdi.AEE04 as resident_physician',
                'pdi.AEE08 as coder',
                'mo.ICD9_ID1 as operation_code',
                'mo.ICD9_NAME as operation_name',
                'mo.OPE_DATE as operation_date',
                'mo.OPE_MAN_NAME as surgeon',
                'mo.FRIST_ASSISTANT_NAME as first_assistant',
                'mo.SECOND_ASSISTANT_NAME as second_assistant',
                'mo.HOCUS_WAY_MC as anesthesia_method',
                'mo.YHDJ as wound_healing_grade',
                'pi.is_danfou as is_danfou'
            );

        if (!$isExport) {
            $recordQuery->offset(($page - 1) * $limit)->limit($limit);
        }

        $records = $recordQuery->get();

        // 构建规则名称映射（用于获取缺陷描述 notice）
        $caseRuleMap = DB::table('case_rule')->pluck('notice', 'id')->toArray();
        $errorRuleMap = DB::table('error_rule')->pluck('desc', 'id')->toArray();
        $ruleSettingMap = DB::table('rule_setting')->pluck('description', 'id')->toArray();

        // 结果后处理
        $list = [];
        foreach ($records as $item) {
            $statusTextMap = [1 => '在院', 2 => '出院'];
            $patientStatusText = $statusTextMap[$item->patient_status_raw] ?? '空';

            $catalogTextMap = [1 => '已编目', 0 => '未编目'];
            $catalogStatusText = $catalogTextMap[$item->catalog_status_raw] ?? '空';

            // 根据 rule_id 和 bl_type 获取缺陷描述
            $ruleId = $item->rule_id;
            if ($ruleId > 1000000) {
                // 自定义规则
                $notice = $ruleSettingMap[$ruleId - 1000000] ?? '';
            } elseif ($item->bl_type === '病历') {
                $notice = $caseRuleMap[$ruleId] ?? '';
            } else {
                $notice = $errorRuleMap[$ruleId] ?? '';
            }
            // 根据 admission_department 和 discharge_department 关联科室表获取 dep_name（科室名称）
            $admissionDepName = '';
            $dischargeDepName = '';

            if (!empty($item->admission_department)) {
                $adDept = DB::table('department')
                    ->where('dep_id', $item->admission_department)
                    ->value('dep_name');
                if ($adDept) {
                    $admissionDepName = $adDept;
                }
            }
            if (!empty($item->discharge_department)) {
                $disDept = DB::table('department')
                    ->where('dep_id', $item->discharge_department)
                    ->value('dep_name');
                if ($disDept) {
                    $dischargeDepName = $disDept;
                }
            }
    
            $list[] = [
                'bl_type' => $item->bl_type,
                'notice' => $notice,
                'name' => $item->name,
                'age' => $item->age,
                'case_number' => $item->case_number,
                'record_grade' => $item->record_grade,
                'record_grade_score' => $item->record_grade_score,
                'case_score' => $item->case_score,
                'home_score' => $item->home_score,
                'admission_date' => $item->admission_date,
                'admission_department' => $admissionDepName,
                //'admission_ward' => $item->admission_ward,
                'discharge_date' => $item->discharge_date,
                'discharge_department' => $dischargeDepName,
                //'discharge_ward' => $item->discharge_ward,
                'hospital_days' => $item->hospital_days,
                'admission_status' => $item->admission_status,
                'diagnosis_code' => $item->diagnosis_code,
                'diagnosis_name' => $item->diagnosis_name,
                'discharge_status' => $item->discharge_status,
                'chief_physician' => $item->chief_physician,
                'deputy_chief_physician' => $item->deputy_chief_physician,
                'attending_physician' => $item->attending_physician,
                'resident_physician' => $item->resident_physician,
                'coder' => $item->coder,
                'operation_code' => $item->operation_code,
                'operation_name' => $item->operation_name,
                'operation_date' => $item->operation_date,
                'surgeon' => $item->surgeon,
                'first_assistant' => $item->first_assistant,
                'second_assistant' => $item->second_assistant,
                'anesthesia_method' => $item->anesthesia_method,
                'wound_healing_grade' => $item->wound_healing_grade,
                'is_danfou' => $item->is_danfou,
                'campus' => $item->campus,
                'patient_status' => $patientStatusText,
                'catalog_status' => $catalogStatusText,
                'zyh' => $item->patient_id,
            ];
        }

        if ($isExport) {
            return $this->exportDefectCountDrillDownExcel($list);
        }

        $data = [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'list' => $list,
        ];

        return ToolsService::returnData(200, $data, '获取成功');
    }

    /**
     * 为支持多选与模糊匹配提供辅助查询
     */
    private function applyFuzzyMatch($query, $column, $input)
    {
        if (empty($input)) return;
        $values = is_array($input) ? $input : explode(',', $input);
        $values = array_filter(array_map('trim', $values));
        if (!empty($values)) {
            $query->where(function ($q) use ($column, $values) {
                foreach ($values as $val) {
                    $q->orWhere($column, 'like', '%' . $val . '%');
                }
            });
        }
    }

    /**
     * 医师申诉情况下钻接口
     */
    public function appealCasesDrillDown(Request $request)
    {
        // 基础过滤参数
        $dischargeStart = $this->formatDate($request->input('discharge_date_start'), 'start');
        $dischargeEnd   = $this->formatDate($request->input('discharge_date_end'), 'end');
        $dischargeDepts = $request->input('discharge_departments', []);
        $caseNumber     = $request->input('case_number');
        $admissionDepts = $request->input('admission_departments', []);
        $admissionStart = $this->formatDate($request->input('admission_date_start'), 'start');
        $admissionEnd   = $this->formatDate($request->input('admission_date_end'), 'end');
        $campusList     = $request->input('campus', []);
        $recordLevels   = $request->input('record_levels', []);
        $patientStatus  = $request->input('patient_status');
        $catalogStatus  = $request->input('catalog_status');
        $residentDoctor = $request->input('resident_doctor', []); // 住院医师
        $page = max(1, (int)$request->input('page', 1));
        $limit = max(1, (int)$request->input('limit', 20));

        // 新增过滤参数
        $appealStatus = $request->input('appeal_status'); // 审核状态
        $qualityType = $request->input('quality_type'); // 质控类型
        $appealDoctor = $request->input('appeal_doctor'); // 申诉医师
        $defectContent = $request->input('defect_content'); // 申诉问题
        $appealTimeStart = $this->formatDate($request->input('appeal_time_start'), 'start');
        $appealTimeEnd = $this->formatDate($request->input('appeal_time_end'), 'end');
        $caseDoctor = $request->input('case_doctor'); // 质控审核医师
        $examineTimeStart = $this->formatDate($request->input('examine_time_start'), 'start');
        $examineTimeEnd = $this->formatDate($request->input('examine_time_end'), 'end');
        $rejectContent = $request->input('reject_content'); // 通过/驳回原因

        // 构建申诉为主表的查询
        $query = DB::table('appeal')
            ->where('appeal.type', 2) // 类型固定为2（申诉）
            ->join('patient_info as pi', 'appeal.ZYH', '=', 'pi.MED_REC_ID')
            ->join('ZY_BRRY as br', 'pi.MED_REC_ID', '=', 'br.ZYH')
            ->leftJoin('patient_hospital_info as phi', 'pi.MED_REC_ID', '=', 'phi.AAA28')
            ->leftJoin('main_diagnosis as md', 'pi.MED_REC_ID', '=', 'md.AAA28')
            ->leftJoin('main_operation as mo', 'pi.MED_REC_ID', '=', 'mo.AAA28')
            ->leftJoin('patient_doctor_info as pdi', 'pi.MED_REC_ID', '=', 'pdi.AAA28');

        // ==== 基础过滤 ====
        if ($dischargeStart && $dischargeEnd) {
            $query->whereBetween('pi.AAC01', [$dischargeStart, $dischargeEnd]);
        } elseif ($dischargeStart) {
            $query->where('pi.AAC01', '>=', $dischargeStart);
        } elseif ($dischargeEnd) {
            $query->where('pi.AAC01', '<=', $dischargeEnd);
        }
        if (!empty($dischargeDepts)) $query->whereIn('pi.AAC02C', $dischargeDepts);
        if ($caseNumber) $query->where('br.AAA28', $caseNumber);
        if (!empty($admissionDepts)) $query->whereIn('pi.AAB11C', $admissionDepts);
        if ($admissionStart && $admissionEnd) {
            $query->whereBetween('pi.AAB01', [$admissionStart, $admissionEnd]);
        } elseif ($admissionStart) {
            $query->where('pi.AAB01', '>=', $admissionStart);
        } elseif ($admissionEnd) {
            $query->where('pi.AAB01', '<=', $admissionEnd);
        }
        if (!empty($campusList)) $query->whereIn('pi.YQ_CODE', $campusList);
        if (!empty($recordLevels)) $query->whereIn('pi.computed_grade', $recordLevels);
        if ($patientStatus && $patientStatus !== '全部') {
            $statusMap = ['在院' => 1, '出院' => 2];
            $query->where('pi.in_hospital', $statusMap[$patientStatus] ?? null);
        }
        if ($catalogStatus && $catalogStatus !== '全部') {
            $catalogMap = ['已编目' => 1, '未编目' => 0];
            $query->where('pi.IS_CATA', $catalogMap[$catalogStatus] ?? null);
        }

        // 住院医师筛选（多选）
        if (!empty($residentDoctor)) {
            $query->whereIn('pi.AEE04_CODE', is_array($residentDoctor) ? $residentDoctor : [$residentDoctor]);
        }

        // ==== 新增过滤 ====
        if (!empty($appealStatus)) {
            $statuses = is_array($appealStatus) ? $appealStatus : explode(',', $appealStatus);
            $mappedStatuses = [];
            foreach ($statuses as $s) {
                $s = trim($s);
                if (is_numeric($s)) {
                    $mappedStatuses[] = (int)$s;
                } else {
                    $sm = ['待审核' => 0, '已通过' => 1, '未通过' => 2, '已整改' => 3];
                    if (isset($sm[$s])) $mappedStatuses[] = $sm[$s];
                }
            }
            if (!empty($mappedStatuses)) $query->whereIn('appeal.status', $mappedStatuses);
        }

        if (!empty($qualityType)) {
            $qTypes = is_array($qualityType) ? $qualityType : explode(',', $qualityType);
            $mappedQTypes = [];
            foreach ($qTypes as $qt) {
                $qt = trim($qt);
                if (is_numeric($qt)) {
                    $mappedQTypes[] = (int)$qt;
                } else {
                    if (strpos($qt, '运行首页') !== false) $mappedQTypes[] = 1;
                    if (strpos($qt, '运行病历') !== false) $mappedQTypes[] = 2;
                    if (strpos($qt, '编码首页') !== false) $mappedQTypes[] = 3;
                }
            }
            if (!empty($mappedQTypes)) $query->whereIn('appeal.quality_type', $mappedQTypes);
        }

        $this->applyFuzzyMatch($query, 'appeal.appeal_docter', $appealDoctor);
        $this->applyFuzzyMatch($query, 'appeal.defect_content', $defectContent);
        $this->applyFuzzyMatch($query, 'appeal.case_docter', $caseDoctor);

        if (!empty($rejectContent)) {
            $query->where('appeal.reject_content', 'like', '%' . $rejectContent . '%');
        }

        if ($appealTimeStart) $query->where('appeal.appeal_time', '>=', strtotime($appealTimeStart));
        if ($appealTimeEnd) $query->where('appeal.appeal_time', '<=', strtotime($appealTimeEnd));
        if ($examineTimeStart) $query->where('appeal.examine_time', '>=', strtotime($examineTimeStart));
        if ($examineTimeEnd) $query->where('appeal.examine_time', '<=', strtotime($examineTimeEnd));

        // 出院时间最晚的在前+未审核置顶
        $total = $query->count();
        $records = $query->offset(($page - 1) * $limit)
            ->limit($limit)
            ->orderByRaw('CASE WHEN appeal.status = 0 THEN 0 ELSE 1 END ASC')
            ->orderBy('pi.AAC01', 'desc')
            ->select(
                'appeal.status as appeal_status',
                'appeal.quality_type',
                'appeal.appeal_docter',
                'appeal.defect_content',
                'appeal.appeal_time',
                'appeal.case_docter',
                'appeal.examine_time',
                'appeal.reject_content',
                'br.BRXM as name',
                DB::raw('IFNULL(pi.AAA04, pi.AAA40) as age'),
                'br.AAA28 as case_number',
                'pi.MED_REC_ID as patient_id',
                'pi.computed_grade as record_grade',
                'pi.case_score as case_score',
                'pi.home_score as home_score',
                DB::raw('IFNULL(pi.case_score, 0) + IFNULL(pi.home_score, 0) as record_grade_score'),
                'pi.AAB01 as admission_date',
                'pi.AAC01 as discharge_date',
                'pi2.AAB02C as admission_department',
                'phi.AAB03 as admission_ward',
                'pi.YQ_CODE as campus',
                'pi2.AAC02C as discharge_department',
                'phi.AAC03 as discharge_ward',
                'pi.AAC04 as hospital_days',
                'pi.in_hospital as patient_status_raw',
                'pi.IS_CATA as catalog_status_raw',
                'md.RYQK as admission_status',
                DB::raw('LEFT(md.ICD10_ID1, 3) as diagnosis_code'),
                'md.ICD10_NAME as diagnosis_name',
                'md.CYQK as discharge_status',
                'pdi.AEE01 as chief_physician',
                'pdi.AEE02 as deputy_chief_physician',
                'pdi.AEE03 as attending_physician',
                'pdi.AEE04 as resident_physician',
                'pdi.AEE08 as coder',
                'mo.ICD9_ID1 as operation_code',
                'mo.ICD9_NAME as operation_name',
                'mo.OPE_DATE as operation_date',
                'mo.OPE_MAN_NAME as surgeon',
                'mo.FRIST_ASSISTANT_NAME as first_assistant',
                'mo.SECOND_ASSISTANT_NAME as second_assistant',
                'mo.HOCUS_WAY_MC as anesthesia_method',
                'mo.YHDJ as wound_healing_grade',
                'pi.is_danfou as is_danfou'
            )
            ->get();

        $staffData = DB::table('staff')->pluck('name', 'base_code')->toArray();

        // 结果后处理
        $list = [];
        foreach ($records as $item) {
            $statusTextMap = [1 => '在院', 2 => '出院'];
            $patientStatusText = $statusTextMap[$item->patient_status_raw] ?? '空';

            $catalogTextMap = [1 => '已编目', 0 => '未编目'];
            $catalogStatusText = $catalogTextMap[$item->catalog_status_raw] ?? '空';

            // 审核状态
            $appealStatusMap = [0 => '待审核', 1 => '已通过', 2 => '未通过', 3 => '已整改'];
            $statusName = $appealStatusMap[$item->appeal_status] ?? '未知';

            // 质控类型
            $qTypeMap = [1 => '运行首页', 2 => '运行病历', 3 => '编码首页'];
            $qtName = $qTypeMap[$item->quality_type] ?? '未知';

            // 质控审核医师展示格式化
            $caseDoctorLabel = $item->case_docter;
            if (!empty($caseDoctorLabel)) {
                if (preg_match('/^\s*(\d+)\s+(.+)\s*$/u', $caseDoctorLabel, $matches)) {
                    $caseDoctorLabel = $matches[2] . '（' . $matches[1] . '）';
                } else {
                    $code = array_search($caseDoctorLabel, $staffData, true);
                    if ($code !== false) {
                        $caseDoctorLabel = $caseDoctorLabel . '（' . $code . '）';
                    }
                }
            }

            // 根据 admission_department 和 discharge_department 关联科室表获取 dep_name（科室名称）
            $admissionDepName = '';
            $dischargeDepName = '';
            if (!empty($item->admission_department)) {
                $adDept = DB::table('department')
                    ->where('dep_id', $item->admission_department)
                    ->value('dep_name');
            }
            if (!empty($item->discharge_department)) {
                $disDept = DB::table('department')
                    ->where('dep_id', $item->discharge_department)
                    ->value('dep_name');
                if ($disDept) {
                    $dischargeDepName = $disDept;
                }
            }
            $list[] = [
                'appeal_status_name' => $statusName,
                'quality_type_name' => $qtName,
                'appeal_docter' => $item->appeal_docter,
                'defect_content' => $item->defect_content,
                'appeal_time' => $item->appeal_time ? date('Y-m-d H:i:s', $item->appeal_time) : '',
                'case_docter' => $caseDoctorLabel,
                'examine_time' => $item->examine_time ? date('Y-m-d H:i:s', $item->examine_time) : '',
                'reject_content' => $item->reject_content,

                // 原有基础字段
                'name' => $item->name,
                'age' => $item->age,
                'case_number' => $item->case_number,
                'record_grade' => $item->record_grade,
                'record_grade_score' => $item->record_grade_score,
                'case_score' => $item->case_score,
                'home_score' => $item->home_score,
                'admission_date' => $item->admission_date,
                'admission_department' => $admissionDepName,
                //'admission_ward' => $item->admission_ward,
                'discharge_date' => $item->discharge_date,
                'discharge_department' => $dischargeDepName,
                //'discharge_ward' => $item->discharge_ward,
                'hospital_days' => $item->hospital_days,
                'admission_status' => $item->admission_status,
                'diagnosis_code' => $item->diagnosis_code,
                'diagnosis_name' => $item->diagnosis_name,
                'discharge_status' => $item->discharge_status,
                'chief_physician' => $item->chief_physician,
                'deputy_chief_physician' => $item->deputy_chief_physician,
                'attending_physician' => $item->attending_physician,
                'resident_physician' => $item->resident_physician,
                'coder' => $item->coder,
                'operation_code' => $item->operation_code,
                'operation_name' => $item->operation_name,
                'operation_date' => $item->operation_date,
                'surgeon' => $item->surgeon,
                'first_assistant' => $item->first_assistant,
                'second_assistant' => $item->second_assistant,
                'anesthesia_method' => $item->anesthesia_method,
                'wound_healing_grade' => $item->wound_healing_grade,
                'is_danfou' => $item->is_danfou,
                'campus' => $item->campus,
                'patient_status' => $patientStatusText,
                'catalog_status' => $catalogStatusText,
            ];
        }

        $data = [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'list' => $list,
        ];

        return ToolsService::returnData(200, $data, '获取成功');
    }
}
