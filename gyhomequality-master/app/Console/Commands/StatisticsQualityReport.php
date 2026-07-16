<?php

namespace App\Console\Commands;

use App\Model\Appeal;
use App\Model\CaseQuality;
use App\Model\CaseRule;
use App\Model\Department;
use App\Model\ErrorRule;
use App\Model\HomeQuality;
use App\Model\IndexCatalog;
use App\Model\Indicator;
use App\Model\PatientInfo;
use App\Model\QualityReportStatistics;
use App\Model\RuleSetting;
use App\Model\Staff;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class StatisticsQualityReport extends Command
{
    /**
     * 命令名称
     *
     * @var string
     */
    protected $signature = 'statistics:quality-report {--period= : 起始统计月,格式:2025年01月,从该月统计到当前月;不指定则从2025年11月起}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '统计质控报告数据,从2025年11月开始按月统计';

    /**
     * 执行命令
     *
     * @return int
     */
    public function handle()
    {
        $startTime = microtime(true);
        $this->info('开始统计质控报告数据...');

        try {
            // 获取需要统计的期间列表
            $periods = $this->getPeriods();

            $this->info("共需统计 " . count($periods) . " 个期间");

            foreach ($periods as $period) {
                $this->info("正在统计: {$period['label']}");
                $periodStart = microtime(true);

                // 统计该期间的数据
                $this->statisticsPeriod($period);

                $periodTime = round((microtime(true) - $periodStart), 2);
                $this->info("  完成,耗时: {$periodTime}秒");
            }

            $totalTime = round((microtime(true) - $startTime), 2);
            $this->info("统计完成! 总耗时: {$totalTime}秒");

            return 0;
        } catch (\Exception $e) {
            $this->error('统计失败: ' . $e->getMessage());
            Log::error('[StatisticsQualityReport] 统计失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * 获取需要统计的期间列表
     *
     * @return array
     */
    private function getPeriods()
    {
        $specifiedPeriod = $this->option('period');
        $currentDate = strtotime(date('Y-m-01'));
        $periods = [];

        if ($specifiedPeriod) {
            // 指定了期间：从该月起统计到当前月
            $parsed = $this->parsePeriod($specifiedPeriod);
            $startDate = strtotime($parsed['start']);
        } else {
            // 未指定：从2025年11月开始到当前月份
            $startDate = strtotime('2025-11-01');
        }

        while ($startDate <= $currentDate) {
            $year = date('Y', $startDate);
            $month = date('n', $startDate);
            $label = "{$year}年{$month}月";

            $periods[] = $this->parsePeriod($label);

            // 下一个月
            $startDate = strtotime('+1 month', $startDate);
        }

        return $periods;
    }

    /**
     * 解析期间字符串
     *
     * @param string $periodLabel
     * @return array
     */
    private function parsePeriod($periodLabel)
    {
        // 解析格式: 2025年11月
        preg_match('/(\d{4})年(\d{1,2})月/', $periodLabel, $matches);
        $year = $matches[1];
        $month = $matches[2];

        $startDate = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate)) . ' 23:59:59';

        return [
            'label' => $periodLabel,
            'year' => $year,
            'month' => $month,
            'start' => $startDate,
            'end' => $endDate,
        ];
    }

    /**
     * 统计某个期间的数据
     *
     * @param array $period
     */
    private function statisticsPeriod($period)
    {
        $startTime = $period['start'];
        $endTime = $period['end'];
        $periodLabel = $period['label'];

        // 先删除该期间的旧数据
        QualityReportStatistics::where('stat_period', $periodLabel)->delete();

        // 加载基础数据
        $baseData = $this->loadBaseData();

        // 加载期间数据
        $periodData = $this->loadPeriodData($startTime, $endTime, $baseData);

        // 1. 统计总体数据
        $this->statisticsOverall($periodLabel, $periodData, $baseData);

        // 2. 统计科室维度数据
        $this->statisticsDepartment($periodLabel, $periodData, $baseData);

        // 3. 统计医师维度数据
        $this->statisticsDoctor($periodLabel, $periodData, $baseData);

        // 4. 统计规则维度数据
        $this->statisticsRule($periodLabel, $periodData, $baseData);

        // 5. 统计规则按科室分布数据
        $this->statisticsRuleByDepartment($periodLabel, $periodData, $baseData);

        // 6. 统计指标维度数据
        $this->statisticsIndicator($periodLabel, $startTime, $endTime);

        // 7. 统计申诉数据
        $this->statisticsAppeal($periodLabel, $periodData, $baseData);
    }

    /**
     * 加载基础数据
     *
     * @return array
     */
    private function loadBaseData()
    {
        // 批量获取病历缺陷规则分数
        $caseRuleCollection = CaseRule::query()
            ->get(['id', 'score', 'notice', 'type', 'one_no']);
        $caseRuleMap = [];
        foreach ($caseRuleCollection as $rule) {
            $caseRuleMap[$rule->id] = [
                'score' => $rule->score,
                'notice' => $rule->notice,
                'type' => $rule->type,
                'one_no' => $rule->one_no,
            ];
        }

        // 批量获取自定义规则分数
        $ruleSettingCollection = RuleSetting::query()
            ->get(['id', 'score', 'description', 'type', 'is_not']);
        $ruleSettingMap = [];
        foreach ($ruleSettingCollection as $rule) {
            $ruleSettingMap[$rule->id] = [
                'score' => $rule->score,
                'description' => $rule->description,
                'type' => $rule->type,
                'is_not' => $rule->is_not,
            ];
        }

        // 批量获取首页缺陷规则分数
        $errorRuleCollection = ErrorRule::query()
            ->get(['id', 'down', 'desc']);
        $errorRuleMap = [];
        foreach ($errorRuleCollection as $rule) {
            $errorRuleMap[$rule->id] = [
                'down' => $rule->down,
                'desc' => $rule->desc,
            ];
        }

        // 批量获取科室信息
        $departments = Department::query()
            ->pluck('dep_name', 'dep_id')
            ->toArray();

        // 批量获取医师信息
        $staffs = Staff::query()
            ->pluck('name', 'code')
            ->toArray();

        return [
            'caseRuleMap' => $caseRuleMap,
            'errorRuleMap' => $errorRuleMap,
            'ruleSettingMap' => $ruleSettingMap,
            'departments' => $departments,
            'staffs' => $staffs,
        ];
    }

    /**
     * 加载期间数据
     *
     * @param string $startTime
     * @param string $endTime
     * @param array $baseData
     * @return array
     */
    private function loadPeriodData($startTime, $endTime, $baseData)
    {
        /**
         * 查询患者数据（与终末质控接口保持一致）
         * 
         * 关键查询条件：
         * 1. 关联 ZY_BRRY 表（用于获取入院科室）
         * 2. 添加 in_hospital = 2 条件（只统计已出院病历）
         * 3. 添加 whereNotNull('MED_REC_ID') 条件
         * 4. 关联 patient_info_v2 表
         * 
         * 这样确保病历总数与终末质控接口完全一致
         */
        $patients = PatientInfo::query()
            ->join('ZY_BRRY', 'ZY_BRRY.ZYH', '=', 'patient_info.MED_REC_ID')
            ->leftJoin('patient_info_v2', function ($join) {
                $join->on('patient_info_v2.ZYH', '=', 'patient_info.MED_REC_ID')
                    ->where('patient_info_v2.status', '=', 0);
            })
            ->whereNotNull('patient_info.MED_REC_ID')
            ->where('patient_info.in_hospital', 2)  // 必须是已出院状态
            ->whereBetween('patient_info.AAC01', [$startTime, $endTime])
            ->get([
                'patient_info.MED_REC_ID',
                'ZY_BRRY.BRKS as AAC02C',      // 保留出院科室（用于原有逻辑）
                'ZY_BRRY.BRKS as BRKS',     // 入院科室（新增）
                'patient_info_v2.AEE04_CODE',
                'patient_info.zm_score',
                'patient_info.zm_score_lv'
            ]);

        $jzhmList = $patients->pluck('MED_REC_ID')->toArray();

        Log::info('[StatisticsQualityReport] 查询患者数据完成', [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'patient_count' => count($jzhmList),
            'query_info' => '使用关联 ZY_BRRY 表的查询逻辑'
        ]);

        // 输出到控制台以便调试
        echo "  [DEBUG] 查询到 " . count($jzhmList) . " 条病历记录\n";

        // 查询病历缺陷数据
        $caseQualityList = [];
        if (!empty($jzhmList)) {
            $caseQualities = CaseQuality::query()
                ->whereIn('JZHM', $jzhmList)
                ->get(['JZHM', 'rule_id']);

            foreach ($caseQualities as $quality) {
                $caseQualityList[$quality->JZHM][] = $quality;
            }
        }

        // 查询首页缺陷数据
        $homeQualityList = [];
        if (!empty($jzhmList)) {
            $homeQualities = HomeQuality::query()
                ->whereIn('ZYH', $jzhmList)
                ->where('is_del', 0)
                ->get(['ZYH', 'error_rule']);

            foreach ($homeQualities as $quality) {
                $homeQualityList[$quality->ZYH][] = $quality;
            }
        }

        return [
            'patients' => $patients,
            'jzhmList' => $jzhmList,
            'caseQualityList' => $caseQualityList,
            'homeQualityList' => $homeQualityList,
        ];
    }

    /**
     * 统计总体数据
     *
     * @param string $periodLabel
     * @param array $periodData
     * @param array $baseData
     */
    private function statisticsOverall($periodLabel, $periodData, $baseData)
    {
        $patients = $periodData['patients'];
        $jzhmList = $periodData['jzhmList'];
        $caseQualityList = $periodData['caseQualityList'];
        $homeQualityList = $periodData['homeQualityList'];

        // 病历总数
        $totalCases = count($jzhmList);

        // 输出调试信息
        echo "  [DEBUG] 总体统计 - 病历总数: $totalCases\n";

        // 缺陷病历数
        $defectCases = 0;
        $totalDefects = 0;
        foreach ($jzhmList as $jzhm) {
            $hasDefect = false;
            $defectCount = 0;

            if (isset($caseQualityList[$jzhm])) {
                $hasDefect = true;
                $defectCount += count($caseQualityList[$jzhm]);
            }

            if (isset($homeQualityList[$jzhm])) {
                $hasDefect = true;
                $defectCount += count($homeQualityList[$jzhm]);
            }

            if ($hasDefect) {
                $defectCases++;
                $totalDefects += $defectCount;
            }
        }

        // 缺陷占比
        $defectRatio = $totalCases > 0 ? round(($defectCases / $totalCases) * 100, 2) : 0;

        // 平均缺陷数
        $avgDefects = $totalCases > 0 ? round($totalDefects / $totalCases, 2) : 0;

        // 计算病案等级
        $gradeStats = $this->calculateGradeStatistics($patients, $caseQualityList, $homeQualityList, $baseData);

        // 插入数据
        QualityReportStatistics::create([
            'stat_period' => $periodLabel,
            'stat_type' => QualityReportStatistics::TYPE_OVERALL,
            'dimension_id' => null,
            'dimension_name' => '总体统计',
            'total_cases' => $totalCases,
            'defect_cases' => $defectCases,
            'defect_ratio' => $defectRatio,
            'avg_defects' => $avgDefects,
            'total_defects' => $totalDefects,
            'grade_a_count' => $gradeStats['grade_a_count'],
            'grade_a_ratio' => $gradeStats['grade_a_ratio'],
            'grade_b_count' => $gradeStats['grade_b_count'],
            'grade_b_ratio' => $gradeStats['grade_b_ratio'],
            'grade_c_count' => $gradeStats['grade_c_count'],
            'grade_c_ratio' => $gradeStats['grade_c_ratio'],
            'avg_score' => $gradeStats['avg_score'],
        ]);
    }

    /**
     * 统计科室维度数据
     *
     * @param string $periodLabel
     * @param array $periodData
     * @param array $baseData
     */
    private function statisticsDepartment($periodLabel, $periodData, $baseData)
    {
        $patients = $periodData['patients'];
        $caseQualityList = $periodData['caseQualityList'];
        $homeQualityList = $periodData['homeQualityList'];
        $departments = $baseData['departments'];

        // 按科室分组
        $deptData = [];
        foreach ($patients as $patient) {
            $deptCode = $patient->AAC02C; // 科室代码字段
            if (empty($deptCode)) {
                continue;
            }
            if (!isset($deptData[$deptCode])) {
                $deptData[$deptCode] = [
                    'patients' => [],
                    'jzhmList' => [],
                ];
            }
            $deptData[$deptCode]['patients'][] = $patient;
            $deptData[$deptCode]['jzhmList'][] = $patient->MED_REC_ID;
        }

        // 统计每个科室
        foreach ($deptData as $deptCode => $data) {
            $jzhmList = $data['jzhmList'];
            $deptPatients = $data['patients'];

            // 病历总数
            $totalCases = count($jzhmList);

            // 缺陷病历数
            $defectCases = 0;
            $totalDefects = 0;
            foreach ($jzhmList as $jzhm) {
                $hasDefect = false;
                $defectCount = 0;

                if (isset($caseQualityList[$jzhm])) {
                    $hasDefect = true;
                    $defectCount += count($caseQualityList[$jzhm]);
                }

                if (isset($homeQualityList[$jzhm])) {
                    $hasDefect = true;
                    $defectCount += count($homeQualityList[$jzhm]);
                }

                if ($hasDefect) {
                    $defectCases++;
                    $totalDefects += $defectCount;
                }
            }

            // 缺陷占比
            $defectRatio = $totalCases > 0 ? round(($defectCases / $totalCases) * 100, 2) : 0;

            // 平均缺陷数
            $avgDefects = $totalCases > 0 ? round($totalDefects / $totalCases, 2) : 0;

            // 计算病案等级
            $gradeStats = $this->calculateGradeStatistics($deptPatients, $caseQualityList, $homeQualityList, $baseData);

            // 科室名称
            $deptName = $departments[$deptCode] ?? $deptCode;

            // 插入数据
            QualityReportStatistics::create([
                'stat_period' => $periodLabel,
                'stat_type' => QualityReportStatistics::TYPE_DEPARTMENT,
                'dimension_id' => $deptCode,
                'dimension_name' => $deptName,
                'total_cases' => $totalCases,
                'defect_cases' => $defectCases,
                'defect_ratio' => $defectRatio,
                'avg_defects' => $avgDefects,
                'total_defects' => $totalDefects,
                'grade_a_count' => $gradeStats['grade_a_count'],
                'grade_a_ratio' => $gradeStats['grade_a_ratio'],
                'grade_b_count' => $gradeStats['grade_b_count'],
                'grade_b_ratio' => $gradeStats['grade_b_ratio'],
                'grade_c_count' => $gradeStats['grade_c_count'],
                'grade_c_ratio' => $gradeStats['grade_c_ratio'],
                'avg_score' => $gradeStats['avg_score'],
            ]);
        }
    }

    /**
     * 统计医师维度数据
     *
     * @param string $periodLabel
     * @param array $periodData
     * @param array $baseData
     */
    private function statisticsDoctor($periodLabel, $periodData, $baseData)
    {
        $patients = $periodData['patients'];
        $caseQualityList = $periodData['caseQualityList'];
        $homeQualityList = $periodData['homeQualityList'];
        $staffs = $baseData['staffs'];
        $departments = $baseData['departments'];
        $caseRuleMap = $baseData['caseRuleMap'];
        $ruleSettingMap = $baseData['ruleSettingMap'];

        // 按医师分组
        $doctorData = [];
        foreach ($patients as $patient) {
            $doctorCode = $patient->AEE04_CODE;
            $depId = $patient->AAC02C;
            if (empty($doctorCode)) {
                continue;
            }

            if (!isset($doctorData[$doctorCode])) {
                $doctorData[$doctorCode] = [
                    'patients' => [],
                    'jzhmList' => [],
                    'dep_id' => $depId,
                ];
            }
            $doctorData[$doctorCode]['patients'][] = $patient;
            $doctorData[$doctorCode]['jzhmList'][] = $patient->MED_REC_ID;
        }

        // 统计每个医师
        foreach ($doctorData as $doctorCode => $data) {
            $jzhmList = $data['jzhmList'];
            $doctorPatients = $data['patients'];
            $depId = $data['dep_id'];

            // 病历总数
            $totalCases = count($jzhmList);

            // 缺陷病历数和总扣分
            $defectCases = 0;
            $totalDefects = 0;
            $totalDeduction = 0; // 总扣分（只统计病历缺陷）

            foreach ($jzhmList as $jzhm) {
                $hasDefect = false;
                $defectCount = 0;
                $caseDeduction = 0; // 该病历的扣分

                // 统计病历缺陷
                if (isset($caseQualityList[$jzhm])) {
                    $hasDefect = true;
                    $defectCount += count($caseQualityList[$jzhm]);

                    // 计算扣分
                    foreach ($caseQualityList[$jzhm] as $defect) {
                        $ruleId = $defect->rule_id;
                        $deduction = 0;
                        if ($ruleId > 1000000) {
                            $settingId = $ruleId - 1000000;
                            $deduction = $ruleSettingMap[$settingId]['score'] ?? 0;
                        } else {
                            $deduction = $caseRuleMap[$ruleId]['score'] ?? 0;
                        }
                        $caseDeduction += $deduction;
                    }
                }

                // 统计首页缺陷（不计入扣分）
                if (isset($homeQualityList[$jzhm])) {
                    $hasDefect = true;
                    $defectCount += count($homeQualityList[$jzhm]);
                }

                if ($hasDefect) {
                    $defectCases++;
                    $totalDefects += $defectCount;
                    $totalDeduction += $caseDeduction;
                }
            }

            // 缺陷占比
            $defectRatio = $totalCases > 0 ? round(($defectCases / $totalCases) * 100, 2) : 0;

            // 平均缺陷数
            $avgDefects = $totalCases > 0 ? round($totalDefects / $totalCases, 2) : 0;

            // 计算病案等级
            $gradeStats = $this->calculateGradeStatistics($doctorPatients, $caseQualityList, $homeQualityList, $baseData);

            // 医师名称和科室名称
            $doctorName = $staffs[$doctorCode] ?? $doctorCode;
            $deptName = $departments[$depId] ?? '未知科室';

            // 插入数据
            QualityReportStatistics::create([
                'stat_period' => $periodLabel,
                'stat_type' => QualityReportStatistics::TYPE_DOCTOR,
                'dimension_id' => $doctorCode,
                'dimension_name' => $doctorName,
                'total_cases' => $totalCases,
                'defect_cases' => $defectCases,
                'defect_ratio' => $defectRatio,
                'avg_defects' => $avgDefects,
                'total_defects' => $totalDefects,
                'grade_a_count' => $gradeStats['grade_a_count'],
                'grade_a_ratio' => $gradeStats['grade_a_ratio'],
                'grade_b_count' => $gradeStats['grade_b_count'],
                'grade_b_ratio' => $gradeStats['grade_b_ratio'],
                'grade_c_count' => $gradeStats['grade_c_count'],
                'grade_c_ratio' => $gradeStats['grade_c_ratio'],
                'avg_score' => $gradeStats['avg_score'],
                'extra_data' => json_encode([
                    'department_id' => $depId,
                    'department_name' => $deptName,
                    'total_deduction' => $totalDeduction,
                ]),
            ]);
        }
    }

    /**
     * 统计规则维度数据
     *
     * @param string $periodLabel
     * @param array $periodData
     * @param array $baseData
     */
    private function statisticsRule($periodLabel, $periodData, $baseData)
    {
        $caseQualityList = $periodData['caseQualityList'];
        $homeQualityList = $periodData['homeQualityList'];
        $caseRuleMap = $baseData['caseRuleMap'];
        $errorRuleMap = $baseData['errorRuleMap'];
        $ruleSettingMap = $baseData['ruleSettingMap'];

        // 统计病历缺陷规则
        $ruleStats = [];
        foreach ($caseQualityList as $jzhm => $defects) {
            foreach ($defects as $defect) {
                $ruleId = $defect->rule_id;

                if (!isset($ruleStats[$ruleId])) {
                    $ruleStats[$ruleId] = [
                        'type' => 'case',
                        'defect_count' => 0,
                        'case_set' => [],
                    ];
                }

                $ruleStats[$ruleId]['defect_count']++;
                $ruleStats[$ruleId]['case_set'][$jzhm] = true;
            }
        }

        // 统计首页缺陷规则
        foreach ($homeQualityList as $jzhm => $defects) {
            foreach ($defects as $defect) {
                $ruleId = $defect->error_rule;

                if (!isset($ruleStats[$ruleId])) {
                    $ruleStats[$ruleId] = [
                        'type' => 'home',
                        'defect_count' => 0,
                        'case_set' => [],
                    ];
                }

                $ruleStats[$ruleId]['defect_count']++;
                $ruleStats[$ruleId]['case_set'][$jzhm] = true;
            }
        }

        // 插入数据
        foreach ($ruleStats as $ruleId => $stats) {
            $ruleName = '';
            $isSingleNo = 0;

            if ($stats['type'] === 'case') {
                // 病历缺陷规则
                if ($ruleId > 1000000) {
                    // 自定义规则
                    $settingId = $ruleId - 1000000;
                    $ruleName = $ruleSettingMap[$settingId]['description'] ?? '';
                    $isSingleNo = $ruleSettingMap[$settingId]['is_not'] ?? 0;
                } else {
                    // 标准规则
                    $ruleName = $caseRuleMap[$ruleId]['notice'] ?? '';
                    $isSingleNo = $caseRuleMap[$ruleId]['one_no'] ?? 0;
                }
            } else {
                // 首页缺陷规则
                if ($ruleId > 1000000) {
                    // 自定义规则
                    $settingId = $ruleId - 1000000;
                    $ruleName = $ruleSettingMap[$settingId]['description'] ?? '';
                    $isSingleNo = $ruleSettingMap[$settingId]['is_not'] ?? 0;
                } else {
                    // 标准规则(ErrorRule没有单否项字段)
                    $ruleName = $errorRuleMap[$ruleId]['desc'] ?? '';
                    $isSingleNo = 0; // ErrorRule表没有单否项字段,默认为0
                }
            }

            QualityReportStatistics::create([
                'stat_period' => $periodLabel,
                'stat_type' => QualityReportStatistics::TYPE_RULE,
                'dimension_id' => $ruleId,
                'dimension_name' => $ruleName,
                'rule_defect_count' => $stats['defect_count'],
                'rule_case_count' => count($stats['case_set']),
                'is_single_no' => $isSingleNo,
                'extra_data' => json_encode([
                    'rule_source' => $stats['type'], // 'case' 或 'home'
                    'rule_id' => $ruleId,
                ]),
            ]);
        }
    }

    /**
     * 统计规则按科室分布数据
     *
     * @param string $periodLabel
     * @param array $periodData
     * @param array $baseData
     */
    private function statisticsRuleByDepartment($periodLabel, $periodData, $baseData)
    {
        $patients = $periodData['patients'];
        $caseQualityList = $periodData['caseQualityList'];
        $homeQualityList = $periodData['homeQualityList'];
        $caseRuleMap = $baseData['caseRuleMap'];
        $errorRuleMap = $baseData['errorRuleMap'];
        $ruleSettingMap = $baseData['ruleSettingMap'];
        $departments = $baseData['departments'];

        // 建立患者到科室的映射
        $patientToDepartment = [];
        foreach ($patients as $patient) {
            $depId = $patient->AAC02C;
            if (empty($depId)) {
                continue;
            }
            $patientToDepartment[$patient->MED_REC_ID] = $depId;
        }

        // 统计每个规则在每个科室的缺陷情况
        $ruleDepStats = []; // [ruleId][depId] = count

        // 统计病历缺陷规则
        foreach ($caseQualityList as $jzhm => $defects) {
            $depId = $patientToDepartment[$jzhm] ?? null;
            if (!$depId) {
                continue;
            }

            foreach ($defects as $defect) {
                $ruleId = $defect->rule_id;

                if (!isset($ruleDepStats[$ruleId])) {
                    $ruleDepStats[$ruleId] = [
                        'type' => 'case',
                        'departments' => []
                    ];
                }

                if (!isset($ruleDepStats[$ruleId]['departments'][$depId])) {
                    $ruleDepStats[$ruleId]['departments'][$depId] = [
                        'defect_count' => 0,
                        'case_set' => []
                    ];
                }

                $ruleDepStats[$ruleId]['departments'][$depId]['defect_count']++;
                $ruleDepStats[$ruleId]['departments'][$depId]['case_set'][$jzhm] = true;
            }
        }

        // 统计首页缺陷规则
        foreach ($homeQualityList as $jzhm => $defects) {
            $depId = $patientToDepartment[$jzhm] ?? null;
            if (!$depId) {
                continue;
            }

            foreach ($defects as $defect) {
                $ruleId = $defect->error_rule;

                if (!isset($ruleDepStats[$ruleId])) {
                    $ruleDepStats[$ruleId] = [
                        'type' => 'home',
                        'departments' => []
                    ];
                }

                if (!isset($ruleDepStats[$ruleId]['departments'][$depId])) {
                    $ruleDepStats[$ruleId]['departments'][$depId] = [
                        'defect_count' => 0,
                        'case_set' => []
                    ];
                }

                $ruleDepStats[$ruleId]['departments'][$depId]['defect_count']++;
                $ruleDepStats[$ruleId]['departments'][$depId]['case_set'][$jzhm] = true;
            }
        }

        // 插入数据
        foreach ($ruleDepStats as $ruleId => $ruleData) {
            $ruleName = '';
            $isSingleNo = 0;

            if ($ruleData['type'] === 'case') {
                if ($ruleId > 1000000) {
                    $settingId = $ruleId - 1000000;
                    $ruleName = $ruleSettingMap[$settingId]['description'] ?? '';
                    $isSingleNo = $ruleSettingMap[$settingId]['is_not'] ?? 0;
                } else {
                    $ruleName = $caseRuleMap[$ruleId]['notice'] ?? '';
                    $isSingleNo = $caseRuleMap[$ruleId]['one_no'] ?? 0;
                }
            } else {
                if ($ruleId > 1000000) {
                    $settingId = $ruleId - 1000000;
                    $ruleName = $ruleSettingMap[$settingId]['description'] ?? '';
                    $isSingleNo = $ruleSettingMap[$settingId]['is_not'] ?? 0;
                } else {
                    $ruleName = $errorRuleMap[$ruleId]['desc'] ?? '';
                    $isSingleNo = 0;
                }
            }

            // 为每个科室创建一条记录
            foreach ($ruleData['departments'] as $depId => $depData) {
                $depName = $departments[$depId] ?? $depId;

                QualityReportStatistics::create([
                    'stat_period' => $periodLabel,
                    'stat_type' => 'rule_department', // 新的统计类型
                    'dimension_id' => $ruleId . '_' . $depId,
                    'dimension_name' => $ruleName . ' - ' . $depName,
                    'rule_defect_count' => $depData['defect_count'],
                    'rule_case_count' => count($depData['case_set']),
                    'is_single_no' => $isSingleNo,
                    'extra_data' => json_encode([
                        'rule_id' => $ruleId,
                        'rule_name' => $ruleName,
                        'department_id' => $depId,
                        'department_name' => $depName,
                        'rule_type' => $ruleData['type']
                    ]),
                ]);
            }
        }
    }

    /**
     * 统计指标维度数据(参考QualityIndexController的逻辑)
     *
     * @param string $periodLabel
     * @param string $startTime
     * @param string $endTime
     */
    private function statisticsIndicator($periodLabel, $startTime, $endTime)
    {
        // 获取所有指标目录，过滤掉index_name为空的记录
        $catalogs = IndexCatalog::query()
            ->whereNotNull('index_name')
            ->where('index_name', '!=', '')
            ->get();

        foreach ($catalogs as $catalog) {
            try {
                $indexName = $catalog->index_name;

                // 跳过不查indicator表的指标
                if ($indexName === 'sjsjsssjkzl') {
                    continue;
                }

                // 跳过无效的index_name
                if (empty($indexName) || strlen($indexName) < 2) {
                    continue;
                }

                // 判断是否为特殊指标
                $isSpecialIndex = in_array($indexName, ['sjssysjssbfzfs', 'sjssysjssswlb']);

                // 1. 统计总体数据
                $overallData = $this->getIndicatorData($indexName, $isSpecialIndex, $startTime, $endTime, null, null);
                if ($overallData) {
                    QualityReportStatistics::create([
                        'stat_period' => $periodLabel,
                        'stat_type' => QualityReportStatistics::TYPE_INDICATOR,
                        'dimension_id' => $catalog->id,
                        'dimension_name' => $catalog->name,
                        'indicator_value' => $overallData['fenzi'],
                        'indicator_target' => $overallData['fenmu'],
                        'indicator_completion' => $overallData['radio'],
                        'extra_data' => json_encode([
                            'index_name' => $indexName,
                            'dimension_type' => 'overall'
                        ]),
                    ]);
                }

                // 2. 统计科室维度数据
                $departmentData = $this->getIndicatorDepartmentData($indexName, $isSpecialIndex, $startTime, $endTime);
                foreach ($departmentData as $depName => $data) {
                    QualityReportStatistics::create([
                        'stat_period' => $periodLabel,
                        'stat_type' => QualityReportStatistics::TYPE_INDICATOR,
                        'dimension_id' => $catalog->id . '_dept_' . md5($depName),
                        'dimension_name' => $catalog->name . ' - ' . $depName,
                        'indicator_value' => $data['fenzi'],
                        'indicator_target' => $data['fenmu'],
                        'indicator_completion' => $data['radio'],
                        'extra_data' => json_encode([
                            'index_name' => $indexName,
                            'dimension_type' => 'department',
                            'department_name' => $depName
                        ]),
                    ]);
                }

                // 3. 统计医师维度数据
                $doctorData = $this->getIndicatorDoctorData($indexName, $isSpecialIndex, $startTime, $endTime);
                foreach ($doctorData as $doctorName => $data) {
                    QualityReportStatistics::create([
                        'stat_period' => $periodLabel,
                        'stat_type' => QualityReportStatistics::TYPE_INDICATOR,
                        'dimension_id' => $catalog->id . '_doctor_' . md5($doctorName),
                        'dimension_name' => $catalog->name . ' - ' . $doctorName,
                        'indicator_value' => $data['fenzi'],
                        'indicator_target' => $data['fenmu'],
                        'indicator_completion' => $data['radio'],
                        'extra_data' => json_encode([
                            'index_name' => $indexName,
                            'dimension_type' => 'doctor',
                            'doctor_name' => $doctorName
                        ]),
                    ]);
                }
            } catch (\Exception $e) {
                // 记录错误但继续处理下一个指标
                $this->warn("  跳过指标 {$catalog->name} ({$catalog->index_name}): " . $e->getMessage());
                Log::warning('[StatisticsQualityReport] 指标统计失败', [
                    'period' => $periodLabel,
                    'catalog_id' => $catalog->id,
                    'catalog_name' => $catalog->name,
                    'index_name' => $catalog->index_name,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
    }

    /**
     * 获取指标数据(总体)
     *
     * @param string $indexName
     * @param bool $isSpecialIndex
     * @param string $startTime
     * @param string $endTime
     * @param string|null $AAC11N 科室
     * @param string|null $AEE03 医师
     * @return array|null
     */
    private function getIndicatorData($indexName, $isSpecialIndex, $startTime, $endTime, $AAC11N = null, $AEE03 = null)
    {
        // 构建查询
        if ($isSpecialIndex) {
            if ($indexName === 'sjssysjssbfzfs') {
                $query = Indicator::query()->selectRaw(
                    "sum(sjssbfz_fm) as sjssbfz_fm_sum, 
                    sum(sjssbfz_fz) as sjssbfz_fz_sum,
                    sum(sijssbfz_fm) as sijssbfz_fm_sum, 
                    sum(sijssbfz_fz) as sijssbfz_fz_sum"
                );
            } else {
                $query = Indicator::query()->selectRaw(
                    "sum(sjsssw_fm) as sjsssw_fm_sum, 
                    sum(sjsssw_fz) as sjsssw_fz_sum,
                    sum(sijsssw_fm) as sijsssw_fm_sum, 
                    sum(sijsssw_fz) as sijsssw_fz_sum"
                );
            }
        } else {
            $query = Indicator::query()->selectRaw(
                "sum({$indexName}_fm) as fenmu, 
                sum({$indexName}_fz) as fenzi"
            );
        }

        // 添加筛选条件
        if (!empty($AAC11N)) {
            $query->where('AAC11N', $AAC11N);
        }
        if (!empty($AEE03)) {
            $query->where('AEE03', $AEE03);
        }

        $query->whereBetween('AAC01', [$startTime, $endTime]);

        $result = $query->first();

        if (!$result) {
            return null;
        }

        // 处理结果
        if ($isSpecialIndex) {
            if ($indexName === 'sjssysjssbfzfs') {
                $fenmu = ($result->sjssbfz_fm_sum > 0) ? round($result->sjssbfz_fz_sum / $result->sjssbfz_fm_sum, 4) : 0;
                $fenzi = ($result->sijssbfz_fm_sum > 0) ? round($result->sijssbfz_fz_sum / $result->sijssbfz_fm_sum, 4) : 0;
            } else {
                $fenmu = ($result->sjsssw_fm_sum > 0) ? round($result->sjsssw_fz_sum / $result->sjsssw_fm_sum, 4) : 0;
                $fenzi = ($result->sijsssw_fm_sum > 0) ? round($result->sijsssw_fz_sum / $result->sijsssw_fm_sum, 4) : 0;
            }
        } else {
            $fenmu = round(floatval($result->fenmu), 4);
            $fenzi = round(floatval($result->fenzi), 4);
        }

        $radio = $fenmu > 0 ? round(($fenzi / $fenmu) * 100, 2) : 0;

        return [
            'fenmu' => $fenmu,
            'fenzi' => $fenzi,
            'radio' => $radio
        ];
    }

    /**
     * 获取指标科室维度数据
     *
     * @param string $indexName
     * @param bool $isSpecialIndex
     * @param string $startTime
     * @param string $endTime
     * @return array
     */
    private function getIndicatorDepartmentData($indexName, $isSpecialIndex, $startTime, $endTime)
    {
        // 构建查询
        if ($isSpecialIndex) {
            if ($indexName === 'sjssysjssbfzfs') {
                $query = Indicator::query()->selectRaw(
                    "AAC11N,
                    sum(sjssbfz_fm) as sjssbfz_fm_sum, 
                    sum(sjssbfz_fz) as sjssbfz_fz_sum,
                    sum(sijssbfz_fm) as sijssbfz_fm_sum, 
                    sum(sijssbfz_fz) as sijssbfz_fz_sum"
                );
            } else {
                $query = Indicator::query()->selectRaw(
                    "AAC11N,
                    sum(sjsssw_fm) as sjsssw_fm_sum, 
                    sum(sjsssw_fz) as sjsssw_fz_sum,
                    sum(sijsssw_fm) as sijsssw_fm_sum, 
                    sum(sijsssw_fz) as sijsssw_fz_sum"
                );
            }
        } else {
            $query = Indicator::query()->selectRaw(
                "AAC11N,
                sum({$indexName}_fm) as fenmu, 
                sum({$indexName}_fz) as fenzi"
            );
        }

        $query->whereBetween('AAC01', [$startTime, $endTime])
            ->whereNotNull('AAC11N')
            ->where('AAC11N', '!=', '')
            ->groupBy('AAC11N');

        $results = $query->get();

        // 处理结果
        $departmentData = [];
        foreach ($results as $item) {
            $depName = $item->AAC11N;

            if ($isSpecialIndex) {
                if ($indexName === 'sjssysjssbfzfs') {
                    $fenmu = ($item->sjssbfz_fm_sum > 0) ? round($item->sjssbfz_fz_sum / $item->sjssbfz_fm_sum, 4) : 0;
                    $fenzi = ($item->sijssbfz_fm_sum > 0) ? round($item->sijssbfz_fz_sum / $item->sijssbfz_fm_sum, 4) : 0;
                } else {
                    $fenmu = ($item->sjsssw_fm_sum > 0) ? round($item->sjsssw_fz_sum / $item->sjsssw_fm_sum, 4) : 0;
                    $fenzi = ($item->sijsssw_fm_sum > 0) ? round($item->sijsssw_fz_sum / $item->sijsssw_fm_sum, 4) : 0;
                }
            } else {
                $fenmu = round(floatval($item->fenmu), 4);
                $fenzi = round(floatval($item->fenzi), 4);
            }

            $radio = $fenmu > 0 ? round(($fenzi / $fenmu) * 100, 2) : 0;

            $departmentData[$depName] = [
                'fenmu' => $fenmu,
                'fenzi' => $fenzi,
                'radio' => $radio
            ];
        }

        return $departmentData;
    }

    /**
     * 获取指标医师维度数据
     *
     * @param string $indexName
     * @param bool $isSpecialIndex
     * @param string $startTime
     * @param string $endTime
     * @return array
     */
    private function getIndicatorDoctorData($indexName, $isSpecialIndex, $startTime, $endTime)
    {
        // 构建查询
        if ($isSpecialIndex) {
            if ($indexName === 'sjssysjssbfzfs') {
                $query = Indicator::query()->selectRaw(
                    "AEE03,
                    sum(sjssbfz_fm) as sjssbfz_fm_sum, 
                    sum(sjssbfz_fz) as sjssbfz_fz_sum,
                    sum(sijssbfz_fm) as sijssbfz_fm_sum, 
                    sum(sijssbfz_fz) as sijssbfz_fz_sum"
                );
            } else {
                $query = Indicator::query()->selectRaw(
                    "AEE03,
                    sum(sjsssw_fm) as sjsssw_fm_sum, 
                    sum(sjsssw_fz) as sjsssw_fz_sum,
                    sum(sijsssw_fm) as sijsssw_fm_sum, 
                    sum(sijsssw_fz) as sijsssw_fz_sum"
                );
            }
        } else {
            $query = Indicator::query()->selectRaw(
                "AEE03,
                sum({$indexName}_fm) as fenmu, 
                sum({$indexName}_fz) as fenzi"
            );
        }

        $query->whereBetween('AAC01', [$startTime, $endTime])
            ->whereNotNull('AEE03')
            ->where('AEE03', '!=', '')
            ->groupBy('AEE03');

        $results = $query->get();

        // 处理结果
        $doctorData = [];
        foreach ($results as $item) {
            $doctorName = $item->AEE03;

            if ($isSpecialIndex) {
                if ($indexName === 'sjssysjssbfzfs') {
                    $fenmu = ($item->sjssbfz_fm_sum > 0) ? round($item->sjssbfz_fz_sum / $item->sjssbfz_fm_sum, 4) : 0;
                    $fenzi = ($item->sijssbfz_fm_sum > 0) ? round($item->sijssbfz_fz_sum / $item->sijssbfz_fm_sum, 4) : 0;
                } else {
                    $fenmu = ($item->sjsssw_fm_sum > 0) ? round($item->sjsssw_fz_sum / $item->sjsssw_fm_sum, 4) : 0;
                    $fenzi = ($item->sijsssw_fm_sum > 0) ? round($item->sijsssw_fz_sum / $item->sijsssw_fm_sum, 4) : 0;
                }
            } else {
                $fenmu = round(floatval($item->fenmu), 4);
                $fenzi = round(floatval($item->fenzi), 4);
            }

            $radio = $fenmu > 0 ? round(($fenzi / $fenmu) * 100, 2) : 0;

            $doctorData[$doctorName] = [
                'fenmu' => $fenmu,
                'fenzi' => $fenzi,
                'radio' => $radio
            ];
        }

        return $doctorData;
    }

    /**
     * 统计申诉数据(参考Controller的逻辑)
     *
     * @param string $periodLabel
     * @param array $periodData
     * @param array $baseData
     */
    private function statisticsAppeal($periodLabel, $periodData, $baseData)
    {
        $jzhmList = $periodData['jzhmList'];
        $staffs = $baseData['staffs'];
        $caseRuleMap = $baseData['caseRuleMap'];
        $ruleSettingMap = $baseData['ruleSettingMap'];
        $departments = $baseData['departments'];

        $this->info("  [申诉统计] 期间: {$periodLabel}");
        $this->info("  [申诉统计] 就诊号数量: " . count($jzhmList));

        if (empty($jzhmList)) {
            $this->warn("  [申诉统计] 就诊号列表为空，跳过申诉统计");
            return;
        }

        // 查询申诉数据(quality_type=2表示病历质控)
        $appeals = Appeal::query()
            ->where('quality_type', 2)
            ->whereIn('ZYH', $jzhmList)
            ->get(['ZYH', 'error_id', 'status', 'appeal_docter']);

        $this->info("  [申诉统计] 查询到申诉记录数: " . $appeals->count());

        if ($appeals->isEmpty()) {
            $this->warn("  [申诉统计] 未查询到申诉数据");

            // 输出调试信息：查看申诉表中是否有数据
            $totalAppeals = Appeal::query()->where('quality_type', 2)->count();
            $this->info("  [申诉统计] 申诉表中quality_type=2的总记录数: {$totalAppeals}");

            // 查看申诉表中的就诊号范围
            $appealZyhSample = Appeal::query()
                ->where('quality_type', 2)
                ->limit(5)
                ->pluck('ZYH')
                ->toArray();
            $this->info("  [申诉统计] 申诉表中的就诊号示例: " . implode(', ', $appealZyhSample));

            // 查看当前期间的就诊号示例
            $jzhmSample = array_slice($jzhmList, 0, 5);
            $this->info("  [申诉统计] 当前期间就诊号示例: " . implode(', ', $jzhmSample));

            return;
        }

        // 1. 按规则分组统计(用于申诉前五问题)
        $ruleAppealStats = [];
        foreach ($appeals as $appeal) {
            $errorId = $appeal->error_id;
            $status = $appeal->status;

            if (!isset($ruleAppealStats[$errorId])) {
                // 获取规则名称
                $ruleName = '';
                if ($errorId > 1000000) {
                    $settingId = $errorId - 1000000;
                    $ruleName = $ruleSettingMap[$settingId]['description'] ?? '未知规则';

                    // 调试输出
                    if ($ruleName === '未知规则') {
                        $this->warn("  [申诉统计] 自定义规则 {$errorId} (settingId: {$settingId}) 未找到");
                        $this->info("  [申诉统计] ruleSettingMap中是否存在: " . (isset($ruleSettingMap[$settingId]) ? '是' : '否'));
                        if (isset($ruleSettingMap[$settingId])) {
                            $this->info("  [申诉统计] 规则数据: " . json_encode($ruleSettingMap[$settingId], JSON_UNESCAPED_UNICODE));
                        }
                    }
                } else {
                    $ruleName = $caseRuleMap[$errorId]['notice'] ?? '未知规则';

                    // 调试输出
                    if ($ruleName === '未知规则') {
                        $this->warn("  [申诉统计] 标准规则 {$errorId} 未找到");
                        $this->info("  [申诉统计] caseRuleMap中是否存在: " . (isset($caseRuleMap[$errorId]) ? '是' : '否'));
                        if (isset($caseRuleMap[$errorId])) {
                            $this->info("  [申诉统计] 规则数据: " . json_encode($caseRuleMap[$errorId], JSON_UNESCAPED_UNICODE));
                        }
                        // 输出caseRuleMap的前几个键
                        $sampleKeys = array_slice(array_keys($caseRuleMap), 0, 5);
                        $this->info("  [申诉统计] caseRuleMap示例键: " . implode(', ', $sampleKeys));
                    }
                }

                $ruleAppealStats[$errorId] = [
                    'rule_name' => $ruleName,
                    'total' => 0,
                    'approved' => 0,
                    'rejected' => 0,
                    'pending' => 0,
                ];
            }

            $ruleAppealStats[$errorId]['total']++;

            if ($status == 1) {
                $ruleAppealStats[$errorId]['approved']++;
            } elseif ($status == 2) {
                $ruleAppealStats[$errorId]['rejected']++;
            } else {
                $ruleAppealStats[$errorId]['pending']++;
            }
        }

        // 插入按规则统计的申诉数据
        foreach ($ruleAppealStats as $errorId => $stats) {
            $successRatio = $stats['total'] > 0 ? round(($stats['approved'] / $stats['total']) * 100, 2) : 0;

            QualityReportStatistics::create([
                'stat_period' => $periodLabel,
                'stat_type' => 'appeal_rule', // 按规则统计的申诉
                'dimension_id' => $errorId,
                'dimension_name' => $stats['rule_name'],
                'appeal_total' => $stats['total'],
                'appeal_approved' => $stats['approved'],
                'appeal_rejected' => $stats['rejected'],
                'appeal_pending' => $stats['pending'],
                'appeal_success_ratio' => $successRatio,
            ]);
        }

        // 2. 按科室分组统计(用于所有科室申诉情况)
        // 建立患者到科室的映射（使用入院科室 BRKS）
        $patientToDepartment = [];
        foreach ($periodData['patients'] as $patient) {
            // 优先使用 BRKS（入院科室），如果没有则使用 AAC02C（出院科室）
            $deptId = $patient->BRKS ?? $patient->AAC02C ?? null;
            if (!empty($deptId)) {
                $patientToDepartment[$patient->MED_REC_ID] = $deptId;
            }
        }

        $deptAppealStats = [];
        foreach ($appeals as $appeal) {
            $zyh = $appeal->ZYH;
            $status = $appeal->status;
            $appealDoctor = $appeal->appeal_docter ?? '';

            // 获取患者所属科室
            $depId = $patientToDepartment[$zyh] ?? null;
            if (!$depId) {
                continue;
            }

            if (!isset($deptAppealStats[$depId])) {
                $deptAppealStats[$depId] = [
                    'dept_name' => $departments[$depId] ?? '未知科室',
                    'doctors' => [], // 申诉医师集合(去重)
                    'total' => 0,
                    'approved' => 0,
                    'rejected' => 0,
                    'pending' => 0,
                ];
            }

            // 统计申诉医师(去重)
            if (!empty($appealDoctor)) {
                $deptAppealStats[$depId]['doctors'][$appealDoctor] = true;
            }

            $deptAppealStats[$depId]['total']++;

            if ($status == 1) {
                $deptAppealStats[$depId]['approved']++;
            } elseif ($status == 2) {
                $deptAppealStats[$depId]['rejected']++;
            } else {
                $deptAppealStats[$depId]['pending']++;
            }
        }

        // 插入按科室统计的申诉数据
        foreach ($deptAppealStats as $depId => $stats) {
            $doctorCount = count($stats['doctors']);
            $successRatio = $stats['total'] > 0 ? round(($stats['approved'] / $stats['total']) * 100, 2) : 0;

            QualityReportStatistics::create([
                'stat_period' => $periodLabel,
                'stat_type' => 'appeal_department', // 按科室统计的申诉
                'dimension_id' => $depId,
                'dimension_name' => $stats['dept_name'],
                'appeal_total' => $stats['total'],
                'appeal_approved' => $stats['approved'],
                'appeal_rejected' => $stats['rejected'],
                'appeal_pending' => $stats['pending'],
                'appeal_success_ratio' => $successRatio,
                'extra_data' => json_encode([
                    'doctor_count' => $doctorCount,
                    'department_id' => $depId
                ]),
            ]);
        }
    }

    /**
     * 计算病案等级统计
     *
     * @param array $patients
     * @param array $caseQualityList
     * @param array $homeQualityList
     * @param array $baseData
     * @return array
     */
    private function calculateGradeStatistics($patients, $caseQualityList, $homeQualityList, $baseData)
    {
        $caseRuleMap = [];
        foreach ($baseData['caseRuleMap'] as $id => $rule) {
            $caseRuleMap[$id] = $rule['score'];
        }

        $ruleSettingMap = [];
        foreach ($baseData['ruleSettingMap'] as $id => $rule) {
            $ruleSettingMap[$id] = $rule['score'];
        }

        $errorRuleMap = [];
        foreach ($baseData['errorRuleMap'] as $id => $rule) {
            $errorRuleMap[$id] = $rule['down'];
        }

        $gradeACount = 0;
        $gradeBCount = 0;
        $gradeCCount = 0;
        $totalScore = 0;

        $sampleCount = 0; // 用于调试，只输出前几个样本

        foreach ($patients as $patient) {
            $jzhm = is_object($patient) ? $patient->MED_REC_ID : $patient['MED_REC_ID'];

            // 病历部分：总分80分，根据缺陷扣分，最低0分
            $caseScore = 80;
            $caseDefectCount = 0;
            $hasSingleNo = false; // 是否有单否项错误
            if (isset($caseQualityList[$jzhm])) {
                foreach ($caseQualityList[$jzhm] as $defect) {
                    $ruleId = $defect->rule_id;
                    $deduction = 0;

                    if ($ruleId > 1000000) {
                        $settingId = $ruleId - 1000000;
                        $deduction = $ruleSettingMap[$settingId] ?? 0;
                        // 检查单否项
                        if (!$hasSingleNo && isset($baseData['ruleSettingMap'][$settingId]['is_not']) && $baseData['ruleSettingMap'][$settingId]['is_not']) {
                            $hasSingleNo = true;
                        }
                    } else {
                        $deduction = $caseRuleMap[$ruleId] ?? 0;
                        // 检查单否项
                        if (!$hasSingleNo && isset($baseData['caseRuleMap'][$ruleId]['one_no']) && $baseData['caseRuleMap'][$ruleId]['one_no']) {
                            $hasSingleNo = true;
                        }
                    }

                    $caseScore -= $deduction;
                    $caseDefectCount++;
                }
            }
            $caseScore = max(0, $caseScore);

            // 首页部分：总分20分，根据缺陷扣分，最低0分
            $homeScore = 20;
            $homeDefectCount = 0;
            if (isset($homeQualityList[$jzhm])) {
                foreach ($homeQualityList[$jzhm] as $defect) {
                    $errorRuleId = $defect->error_rule;
                    $deduction = 0;

                    if ($errorRuleId > 1000000) {
                        $settingId = $errorRuleId - 1000000;
                        $deduction = $ruleSettingMap[$settingId] ?? 0;
                        // 检查单否项(自定义首页规则)
                        if (!$hasSingleNo && isset($baseData['ruleSettingMap'][$settingId]['is_not']) && $baseData['ruleSettingMap'][$settingId]['is_not']) {
                            $hasSingleNo = true;
                        }
                    } else {
                        $deduction = $errorRuleMap[$errorRuleId] ?? 0;
                    }

                    $homeScore -= $deduction;
                    $homeDefectCount++;
                }
            }
            $homeScore = max(0, $homeScore);

            // 总分 = 病历分数 + 首页分数（满100分）
            $score = $caseScore + $homeScore;
            $totalScore += $score;

            // 调试：输出前3个样本的详细信息
            if ($sampleCount < 3 && ($caseDefectCount > 0 || $homeDefectCount > 0)) {
                $this->info("  [等级计算样本] JZHM: {$jzhm}, 病历缺陷数: {$caseDefectCount}, 首页缺陷数: {$homeDefectCount}, 病历分: {$caseScore}, 首页分: {$homeScore}, 总分: {$score}");
                $sampleCount++;
            }

            // 判断等级
            if ($score >= 91) {
                $grade = '甲';
                $gradeACount++;
            } elseif ($score >= 75) {
                $grade = '乙';
                $gradeBCount++;
            } else {
                $grade = '丙';
                $gradeCCount++;
            }

            // 将计算结果回写到 patient_info 表
            PatientInfo::where('MED_REC_ID', $jzhm)->update([
                'case_score' => $caseScore,
                'home_score' => $homeScore,
                'computed_grade' => $grade,
                'is_danfou' => $hasSingleNo ? '有' : '无',
            ]);
        }

        $total = $gradeACount + $gradeBCount + $gradeCCount;
        $gradeARatio = $total > 0 ? round(($gradeACount / $total) * 100, 2) : 0;
        $gradeBRatio = $total > 0 ? round(($gradeBCount / $total) * 100, 2) : 0;
        $gradeCRatio = $total > 0 ? round(($gradeCCount / $total) * 100, 2) : 0;
        $avgScore = $total > 0 ? round($totalScore / $total, 2) : 0;

        $this->info("  [等级统计] 总数: {$total}, 甲级: {$gradeACount} ({$gradeARatio}%), 乙级: {$gradeBCount} ({$gradeBRatio}%), 丙级: {$gradeCCount} ({$gradeCRatio}%), 平均分: {$avgScore}");

        return [
            'grade_a_count' => $gradeACount,
            'grade_a_ratio' => $gradeARatio,
            'grade_b_count' => $gradeBCount,
            'grade_b_ratio' => $gradeBRatio,
            'grade_c_count' => $gradeCCount,
            'grade_c_ratio' => $gradeCRatio,
            'avg_score' => $avgScore,
        ];
    }
}
