<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\Appeal;
use App\Model\CaseQuality;
use App\Model\CaseRule;
use App\Model\Department;
use App\Model\EMR_BL_BL01;
use App\Model\ErrorRule;
use App\Model\HomeQuality;
use App\Model\IndexCatalog;
use App\Model\Indicator;
use App\Model\PatientInfo;
use App\Model\QualityReportStatistics;
use App\Model\RuleSetting;
use App\Model\Staff;
use App\Services\ToolsService;
use App\Services\WordReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QualityReportController extends Controller
{
    /**
     * 统一调试日志前缀，便于医院环境按关键词检索季度链路。
     */
    private const TRACE_LOG_PREFIX = '[QualityReportTrace]';

    /**
     * 请求级统计查询缓存，避免同一批月份数据被重复扫描。
     *
     * @var array
     */
    private $statisticsQueryCache = [];

    /**
     * extra_data 解析缓存。
     *
     * @var array
     */
    private $statisticsExtraDataCache = [];

    /**
     * 报告指标列表缓存。
     *
     * @var array|null
     */
    private $reportIndicatorsCache = null;

    /**
     * 统一输出排查日志。
     *
     * @param string $stage
     * @param array $context
     * @return void
     */
    private function logTrace($stage, array $context = [])
    {
        Log::info(self::TRACE_LOG_PREFIX . ' ' . $stage, $context);
    }

    /**
     * 生成Word报告并返回下载或返回统计数据
     * @param Request $request
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     */
    public function getReportStatistics(Request $request)
    {
        $logPrefix = '[QualityReport]';
        $startTime = microtime(true);

        try {
            // 接收参数
            $time = $request->input('time', '');
            $type = $request->input('type', 'month'); // 统计类型：year=年，quarter=季，month=月（默认）
            $isExport = $request->input('is_export', 1); // 默认为1，导出Word
            $depIds = $request->input('dep_id', []); // 科室ID数组，用于筛选科室

            // 处理 dep_id 参数
            if (!empty($depIds)) {
                // 如果是字符串，转换为数组
                if (is_string($depIds)) {
                    $depIds = json_decode($depIds, true);
                    if (!is_array($depIds)) {
                        $depIds = [$depIds];
                    }
                }
                // 确保是数组
                if (!is_array($depIds)) {
                    $depIds = [];
                }
            } else {
                // 如果没有传入 dep_id，使用当前登录人的科室列表
                $token = $request->header('token');
                if (!empty($token)) {
                    // 优先尝试从 request->user() 获取
                    $userInfo = $request->user();

                    // 如果为空，尝试从 Session 获取
                    if (empty($userInfo)) {
                        $userInfo = \Illuminate\Support\Facades\Session::get($token);
                    }

                    // 如果 Session 中也没有，尝试从数据库查询
                    if (empty($userInfo)) {
                        $userInfo = \App\Model\User::findWhereToken($token);
                    }

                    if (!empty($userInfo) && is_array($userInfo)) {
                        // 获取用户的科室ID（JSON格式：["D101"]）
                        $depIdJson = $userInfo['dep_id'] ?? '';

                        if (!empty($depIdJson)) {
                            $userDepIds = json_decode($depIdJson, true);

                            if (is_array($userDepIds) && !empty($userDepIds)) {
                                // 查询管理科室配置（id=4001）
                                $adminDepts = \App\Model\RuleWordMap::getFirstById(4001);
                                $isAdminDept = false;

                                // 判断是否是管理科室
                                if (is_array($adminDepts)) {
                                    foreach ($userDepIds as $depId) {
                                        if (in_array($depId, $adminDepts)) {
                                            $isAdminDept = true;
                                            break;
                                        }
                                    }
                                } elseif (is_string($adminDepts)) {
                                    $adminDepArray = explode(',', str_replace('，', ',', $adminDepts));
                                    foreach ($userDepIds as $depId) {
                                        if (in_array($depId, $adminDepArray)) {
                                            $isAdminDept = true;
                                            break;
                                        }
                                    }
                                }

                                // 如果不是管理科室，使用用户的科室列表
                                if (!$isAdminDept) {
                                    $depIds = $userDepIds;
                                    Log::info("{$logPrefix} 使用当前登录人的科室列表", ['dep_ids' => $depIds]);
                                } else {
                                    // 管理科室不限制，返回全院数据
                                    $depIds = [];
                                    Log::info("{$logPrefix} 当前用户是管理科室，返回全院数据");
                                }
                            }
                        }
                    }
                }
            }

            Log::info("{$logPrefix} 开始处理请求", [
                'time' => $time,
                'type' => $type,
                'is_export' => $isExport,
                'dep_ids' => $depIds,
                'time_raw' => $request->input('time', ''),
                'time_length' => mb_strlen($time),
                'time_bytes' => strlen($time)
            ]);

            // 验证参数
            if (empty($time)) {
                return ToolsService::returnData(4001, [], '时间参数不能为空');
            }

            if (!in_array($type, ['year', 'quarter', 'month'])) {
                return ToolsService::returnData(4001, [], 'type参数错误，应为：year、quarter 或 month');
            }

            $stepStart = microtime(true);

            // 统一处理时间格式：将 "2026年01月" 转换为 "2026年1月"（去掉月份前导零）
            $time = preg_replace_callback('/(\d{4})年0?(\d{1,2})月/', function ($matches) {
                return $matches[1] . '年' . (int)$matches[2] . '月';
            }, $time);

            Log::info("{$logPrefix} [步骤1] 接收参数完成", [
                '耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms',
                '原始时间' => $request->input('time', ''),
                '处理后时间' => $time,
                '统计类型' => $type
            ]);

            // 解析时间参数并计算时间范围
            $stepStart = microtime(true);
            $timeRanges = $this->parseTimeRanges($time, $type);
            Log::info("{$logPrefix} [步骤2] 解析时间范围完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);
            $this->logTrace('request_context', [
                'time' => $time,
                'type' => $type,
                'is_export' => (int)$isExport,
                'dep_ids' => $depIds,
                'current_range' => $timeRanges['current'] ?? [],
                'last_range' => $timeRanges['last'] ?? [],
                'last_year_range' => $timeRanges['lastYear'] ?? [],
            ]);

            $baseData = [];
            $periodData = [];

            if ((int)$isExport !== 0) {
                // 导出流程暂时保留原有预加载，避免影响仍依赖旧实现的模板逻辑。
                $stepStart = microtime(true);
                $baseData = $this->loadBaseData();
                Log::info("{$logPrefix} [步骤3] 加载基础数据完成", [
                    '耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms',
                    '规则数' => count($baseData['caseRuleMap'] ?? []),
                    '科室数' => count($baseData['departments'] ?? []),
                    '医师数' => count($baseData['staffs'] ?? [])
                ]);

                $stepStart = microtime(true);
                $periodData = $this->loadPeriodData($timeRanges, $baseData);
                $currentPatientCount = count($periodData['current']['jzhmList'] ?? []);
                $lastPatientCount = count($periodData['last']['jzhmList'] ?? []);
                $caseQualityCount = count($periodData['caseQualityList'] ?? []);
                $homeQualityCount = count($periodData['homeQualityList'] ?? []);
                Log::info("{$logPrefix} [步骤4] 加载期间数据完成", [
                    '耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms',
                    '当前期患者数' => $currentPatientCount,
                    '上期患者数' => $lastPatientCount,
                    '病历缺陷记录数' => $caseQualityCount,
                    '首页缺陷记录数' => $homeQualityCount
                ]);
            } else {
                Log::info("{$logPrefix} [步骤3-4] 跳过基础/期间全量数据加载", [
                    '原因' => '前端统计接口当前直接读取 quality_report_statistics 汇总表'
                ]);
            }

            // 获取统计数据（使用缓存数据）
            $stepStart = microtime(true);
            $statisticsData = $this->getStatisticsData($time, $timeRanges, $baseData, $periodData, $depIds);
            Log::info("{$logPrefix} [步骤5] 计算统计数据完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

            // 如果 is_export = 0，返回前端需要的分组统计数据
            if ($isExport == 0) {
                $stepStart = microtime(true);
                $frontendData = $this->formatDataForFrontend($time, $timeRanges, $baseData, $periodData, $statisticsData, $depIds);
                Log::info("{$logPrefix} [步骤6] 格式化前端数据完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

                $totalTime = round((microtime(true) - $startTime), 2);
                Log::info("{$logPrefix} 返回统计数据完成", [
                    '总耗时' => $totalTime . '秒',
                    '时间参数' => $time
                ]);
                return ToolsService::returnData(200, $frontendData, '获取统计数据成功');
            }

            // 获取模板文件路径
            $stepStart = microtime(true);
            $basePath = base_path();
            $templatePath = $basePath . '/病历质控分析报告1.docx';

            // 如果模板文件不存在，尝试其他路径
            if (!file_exists($templatePath)) {
                $storagePath = storage_path('app/templates');
                $templatePath = $storagePath . '/病历质控分析报告1.docx';
            }

            if (!file_exists($templatePath)) {
                return ToolsService::returnData(4001, [], '找不到模板文件，请确认模板文件路径');
            }
            Log::info("{$logPrefix} [步骤6] 加载模板文件完成", [
                '耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms',
                '模板路径' => $templatePath
            ]);

            // 生成Word报告
            $stepStart = microtime(true);
            $wordReportService = new WordReportService();
            $wordReportService->loadTemplate($templatePath);
            $exportStatisticsData = $this->buildWordExportStatisticsData($statisticsData);
            $wordReportService->replaceVariables($exportStatisticsData);
            Log::info("{$logPrefix} [步骤7] 初始化Word服务并替换变量完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

            // 设置指标图表数据
            $stepStart = microtime(true);
            $this->setIndicatorCharts($wordReportService, $time, $statisticsData, $timeRanges);
            Log::info("{$logPrefix} [步骤8] 设置指标图表数据完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

            // 设置规则分布表格数据（使用缓存数据）
            $stepStart = microtime(true);
            $this->setRuleDistributionTables($wordReportService, $time, $statisticsData, $timeRanges, $baseData, $periodData, $depIds);
            Log::info("{$logPrefix} [步骤9] 设置规则分布表格完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

            // 设置科室维度统计表格数据（使用缓存数据）
            $stepStart = microtime(true);
            $this->setDepartmentStatisticsTables($wordReportService, $time, $statisticsData, $timeRanges, $baseData, $periodData, $depIds);
            Log::info("{$logPrefix} [步骤10] 设置科室维度统计表格完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

            // 设置单否项缺陷占比前五表格数据（使用缓存数据）
            $stepStart = microtime(true);
            $this->setSingleNoItemStatisticsTables($wordReportService, $time, $statisticsData, $timeRanges, $baseData, $periodData, $depIds);
            Log::info("{$logPrefix} [步骤11] 设置单否项统计表格完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

            // 设置全部单否项科室问题表格数据（使用缓存数据）
            $stepStart = microtime(true);
            $this->setAllSingleNoItemDepartmentTables($wordReportService, $time, $statisticsData, $timeRanges, $baseData, $periodData, $depIds);
            Log::info("{$logPrefix} [步骤12] 设置全部单否项科室问题表格完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

            // 设置医师申诉情况表格数据（使用缓存数据）
            $stepStart = microtime(true);
            $this->setAppealStatisticsTables($wordReportService, $time, $statisticsData, $timeRanges, $baseData, $periodData, $depIds);
            Log::info("{$logPrefix} [步骤13] 设置医师申诉情况表格完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

            // 设置指标达成率最优和最差前5名表格数据
            $stepStart = microtime(true);
            $this->setIndicatorRankingTables($wordReportService, $time, $statisticsData, $timeRanges, $depIds);
            Log::info("{$logPrefix} [步骤14] 设置指标达成率排名表格完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

            // 设置病历质量最优和最差前5名表格和图表数据
            $stepStart = microtime(true);
            $this->setCaseQualityRankingTables($wordReportService, $time, $statisticsData, $depIds);
            Log::info("{$logPrefix} [步骤15] 设置病历质量排名表格和图表完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

            // 设置病历质量所有科室排名表格数据
            $stepStart = microtime(true);
            $this->setAllCaseQualityRankingTable($wordReportService, $time, $statisticsData, $depIds);
            Log::info("{$logPrefix} [步骤16] 设置病历质量所有科室排名表格完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

            // 设置医师病历质量最优和最差前5名表格和图表数据
            $stepStart = microtime(true);
            try {
                $this->setDoctorCaseQualityRankingTables($wordReportService, $time, $statisticsData, $depIds);
                Log::info("{$logPrefix} [步骤17] 设置医师病历质量排名表格和图表完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);
            } catch (\Exception $e) {
                Log::error("{$logPrefix} [步骤17] 设置医师病历质量排名表格和图表失败", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            // 保存报告到临时文件
            $stepStart = microtime(true);
            try {
                $outputDir = storage_path('app/reports');

                // 确保目录存在且有写入权限
                if (!is_dir($outputDir)) {
                    if (!mkdir($outputDir, 0777, true)) {
                        throw new \Exception("无法创建目录: {$outputDir}");
                    }
                }

                // 检查目录是否可写
                if (!is_writable($outputDir)) {
                    // 尝试修改权限
                    @chmod($outputDir, 0777);
                    if (!is_writable($outputDir)) {
                        throw new \Exception("目录不可写: {$outputDir}，请检查权限");
                    }
                }

                $filename = '病历质控分析报告_' . date('YmdHis') . '.docx';
                $outputPath = $outputDir . '/' . $filename;

                Log::info("{$logPrefix} [步骤18] 开始保存Word报告", [
                    '输出目录' => $outputDir,
                    '目录存在' => is_dir($outputDir),
                    '目录可写' => is_writable($outputDir),
                    '文件路径' => $outputPath
                ]);

                $wordReportService->saveReport($outputPath);

                $fileSize = file_exists($outputPath) ? filesize($outputPath) : 0;
                Log::info("{$logPrefix} [步骤18] 保存Word报告完成", [
                    '耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms',
                    '文件路径' => $outputPath,
                    '文件大小' => round($fileSize / 1024 / 1024, 2) . 'MB',
                    '文件存在' => file_exists($outputPath)
                ]);
            } catch (\Exception $e) {
                Log::error("{$logPrefix} [步骤18] 保存Word报告失败", [
                    'error' => $e->getMessage(),
                    'output_dir' => $outputDir ?? '未设置',
                    'output_path' => $outputPath ?? '未设置',
                    'dir_exists' => isset($outputDir) ? is_dir($outputDir) : false,
                    'dir_writable' => isset($outputDir) ? is_writable($outputDir) : false,
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e; // 重新抛出异常，让外层catch处理
            }

            $totalTime = round((microtime(true) - $startTime), 2);
            Log::info("{$logPrefix} 生成Word报告完成", [
                '总耗时' => $totalTime . '秒',
                '时间参数' => $time
            ]);

            // 返回文件下载
            return response()->download($outputPath, $filename)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            $totalTime = round((microtime(true) - $startTime), 2);
            Log::error("{$logPrefix} 生成Word报告失败", [
                '总耗时' => $totalTime . '秒',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return ToolsService::returnData(500, [], '生成报告失败: ' . $e->getMessage());
        }
    }

    /**
     * 解析时间参数并计算时间范围
     * @param string $time 时间参数，格式：2025年12月 或 2025第一季度 或 2025年
     * @param string $type 统计类型：year=年，quarter=季，month=月
     * @return array
     */
    private function parseTimeRanges($time, $type = 'month')
    {
        // 清理输入字符串：去除首尾空格、全角空格等
        $time = trim($time);
        $time = str_replace([' ', '　', "\t", "\n", "\r"], '', $time);

        // 记录清理后的时间参数，用于调试
        Log::info('[QualityReport] parseTimeRanges 接收参数', [
            'original_time' => $time,
            'type' => $type,
            'time_length' => mb_strlen($time),
            'time_bytes' => strlen($time)
        ]);

        $year = null;
        $startMonth = null;
        $endMonth = null;
        $isQuarter = false;
        $isYear = false;

        // 根据类型解析时间
        if ($type === 'year') {
            // 年度格式：2025年
            if (preg_match('/(\d{4})年/', $time, $matches)) {
                $year = (int)$matches[1];
                $startMonth = 1;
                $endMonth = 12;
                $isYear = true;
            } else {
                throw new \Exception('年度时间格式错误，应为：YYYY年（如：2025年）');
            }
        } elseif ($type === 'quarter') {
            // 季度格式：2025年第一季度、2025年第二季度、2025年第三季度、2025年第四季度
            // 使用 u 修饰符支持 UTF-8 编码
            if (preg_match('/(\d{4})年第([一二三四])季度/u', $time, $matches)) {
                $year = (int)$matches[1];
                $quarter = $matches[2];
                $isQuarter = true;

                Log::info('[QualityReport] 季度匹配成功', [
                    'quarter' => $quarter,
                    'quarter_bytes' => bin2hex($quarter)
                ]);

                // 季度映射
                $quarterMap = [
                    '一' => ['start' => 1, 'end' => 3],
                    '二' => ['start' => 4, 'end' => 6],
                    '三' => ['start' => 7, 'end' => 9],
                    '四' => ['start' => 10, 'end' => 12],
                ];

                if (!isset($quarterMap[$quarter])) {
                    Log::error('[QualityReport] 季度映射失败', [
                        'quarter' => $quarter,
                        'quarter_hex' => bin2hex($quarter),
                        'available_keys' => array_keys($quarterMap)
                    ]);
                    throw new \Exception('季度格式错误，应为：第一季度、第二季度、第三季度或第四季度');
                }

                $startMonth = $quarterMap[$quarter]['start'];
                $endMonth = $quarterMap[$quarter]['end'];
            } else {
                // 添加详细的调试信息
                Log::error('[QualityReport] 季度正则匹配失败', [
                    'time' => $time,
                    'time_hex' => bin2hex($time),
                    'pattern' => '/(\d{4})年第([一二三四])季度/u'
                ]);
                throw new \Exception('季度时间格式错误，应为：YYYY年第X季度（如：2025年第一季度）');
            }
        } else {
            // 月份格式：2025年11月
            if (preg_match('/(\d{4})年(\d{1,2})月/', $time, $matches)) {
                $year = (int)$matches[1];
                $startMonth = (int)$matches[2];
                $endMonth = $startMonth;
            } else {
                throw new \Exception('月份时间格式错误，应为：YYYY年MM月（如：2025年11月）');
            }
        }

        // 计算当前期时间范围
        $currentStartTime = date('Y-m-d', strtotime("{$year}-{$startMonth}-01")) . ' 00:00:00';
        $currentEndTime = date('Y-m-t', strtotime("{$year}-{$endMonth}-01")) . ' 23:59:59';

        // 计算上一期时间范围
        if ($isYear) {
            // 年度：上一期是上一年
            $lastYear = $year - 1;
            $lastStartMonth = 1;
            $lastEndMonth = 12;
        } elseif ($isQuarter) {
            // 季度：上一期是上一个季度
            if ($startMonth == 1) {
                // 第一季度，上一期是上一年的第四季度
                $lastYear = $year - 1;
                $lastStartMonth = 10;
                $lastEndMonth = 12;
            } else {
                // 其他季度，上一期是上一个季度
                $lastYear = $year;
                $lastStartMonth = $startMonth - 3;
                $lastEndMonth = $endMonth - 3;
            }
        } else {
            // 月份：上一期是上个月
            $lastMonth = $startMonth - 1;
            $lastYear = $year;
            if ($lastMonth < 1) {
                $lastMonth = 12;
                $lastYear = $year - 1;
            }
            $lastStartMonth = $lastMonth;
            $lastEndMonth = $lastMonth;
        }
        $lastStartTime = date('Y-m-d', strtotime("{$lastYear}-{$lastStartMonth}-01")) . ' 00:00:00';
        $lastEndTime = date('Y-m-t', strtotime("{$lastYear}-{$lastEndMonth}-01")) . ' 23:59:59';

        // 计算去年同期时间范围（用于同比）
        // 年度统计没有同比/环比映射差异输出
        $lastYearYear = $year - 1;
        $lastYearStartTime = date('Y-m-d', strtotime("{$lastYearYear}-{$startMonth}-01")) . ' 00:00:00';
        $lastYearEndTime = date('Y-m-t', strtotime("{$lastYearYear}-{$endMonth}-01")) . ' 23:59:59';

        // 计算期数：从2025年11月算作第一期
        $baseYear = 2025;
        $baseMonth = 11;

        // 获取当前实际时间
        $currentYear = (int)date('Y');
        $currentMonth = (int)date('m');

        // 确定用于计算期数的月份
        $periodMonth = $endMonth; // 默认使用结束月份

        if ($isYear || $isQuarter) {
            // 年度或季度统计：需要判断当前时间是否在期间内
            $periodEndTimestamp = strtotime("{$year}-{$endMonth}-01");
            $currentTimestamp = strtotime("{$currentYear}-{$currentMonth}-01");

            if ($currentTimestamp >= strtotime("{$year}-{$startMonth}-01") && $currentTimestamp <= $periodEndTimestamp) {
                // 当前时间在期间内，使用当前月份
                $periodMonth = $currentMonth;
            } elseif ($periodEndTimestamp < $currentTimestamp) {
                // 期间结束月份小于当前时间，使用结束月份
                $periodMonth = $endMonth;
            } else {
                // 期间结束月份大于当前时间，使用当前月份
                $periodMonth = $currentMonth;
            }
        } else {
            // 月度统计：直接使用指定月份
            $periodMonth = $startMonth;
        }

        // 计算期数
        $qinum = ($year - $baseYear) * 12 + ($periodMonth - $baseMonth) + 1;
        $qi = $qinum;

        $result = [
            'year' => $year,
            'month' => $startMonth,        // 保留month字段用于兼容
            'startMonth' => $startMonth,   // 起始月份
            'endMonth' => $endMonth,       // 结束月份
            'isQuarter' => $isQuarter,     // 是否为季度格式
            'isYear' => $isYear,           // 是否为年度格式
            'type' => $type,               // 统计类型
            'time' => $time,               // 原始时间字符串
            'qi' => $qi,                   // 期数
            'qinum' => $qinum,             // 总期数
            'current' => ['start' => $currentStartTime, 'end' => $currentEndTime],
            'last' => ['start' => $lastStartTime, 'end' => $lastEndTime],
            'lastYear' => ['start' => $lastYearStartTime, 'end' => $lastYearEndTime],
        ];

        $this->logTrace('time_ranges', [
            'time' => $time,
            'type' => $type,
            'year' => $year,
            'start_month' => $startMonth,
            'end_month' => $endMonth,
            'is_quarter' => $isQuarter,
            'is_year' => $isYear,
            'current' => $result['current'],
            'last' => $result['last'],
            'last_year' => $result['lastYear'],
        ]);

        return $result;
    }

    /**
     * 一次性加载所有基础数据（规则、科室、单否规则、医师等）
     * @return array
     */
    private function loadBaseData()
    {
        // 批量获取病历缺陷规则分数
        $caseRuleMap = CaseRule::query()
            ->where('status', 0)
            ->get(['id', 'score', 'notice', 'type', 'one_no'])
            ->keyBy('id')
            ->toArray();

        // 批量获取自定义规则分数
        $ruleSettingMap = RuleSetting::query()
            ->where('status', 1)
            ->get(['id', 'score', 'description', 'type', 'is_not'])
            ->keyBy('id')
            ->toArray();

        // 批量获取首页缺陷规则分数
        $errorRuleMap = ErrorRule::query()
            ->where('status', 0)
            ->get(['id', 'down', 'desc'])
            ->keyBy('id')
            ->toArray();

        // 批量获取科室信息
        $departments = Department::query()
            ->pluck('dep_name', 'dep_id')
            ->toArray();

        // 批量获取医师信息
        $staffs = Staff::query()
            ->pluck('name', 'code')
            ->toArray();

        // 获取标准规则的单否标识（one_no = 1 表示单否）
        $standardSingleNoRuleIds = [];
        foreach ($caseRuleMap as $id => $rule) {
            if (isset($rule['one_no']) && $rule['one_no'] == 1) {
                $standardSingleNoRuleIds[] = $id;
            }
        }

        // 获取自定义规则的单否标识（is_not = 1 表示单否）
        $customSingleNoRuleIds = [];
        foreach ($ruleSettingMap as $id => $rule) {
            if (isset($rule['is_not']) && $rule['is_not'] == 1) {
                $customSingleNoRuleIds[] = $id + 1000000; // 转换为实际使用的ID
            }
        }

        // 合并所有单否规则ID
        $allSingleNoRuleIds = array_flip(array_merge($standardSingleNoRuleIds, $customSingleNoRuleIds));

        return [
            'caseRuleMap' => $caseRuleMap,
            'ruleSettingMap' => $ruleSettingMap,
            'errorRuleMap' => $errorRuleMap,
            'departments' => $departments,
            'staffs' => $staffs,
            'allSingleNoRuleIds' => $allSingleNoRuleIds,
        ];
    }

    /**
     * 一次性查询当前期和上期的患者数据及缺陷数据
     * @param array $timeRanges 时间范围
     * @param array $baseData 基础数据
     * @return array
     */
    private function loadPeriodData($timeRanges, $baseData)
    {
        // 查询当前期患者数据
        $currentPatients = PatientInfo::query()
            ->whereBetween('AAC01', [$timeRanges['current']['start'], $timeRanges['current']['end']])
            ->get(['MED_REC_ID', 'AAC02C', 'AEE04_CODE']);

        // 查询上期患者数据
        $lastPatients = PatientInfo::query()
            ->whereBetween('AAC01', [$timeRanges['last']['start'], $timeRanges['last']['end']])
            ->get(['MED_REC_ID', 'AAC02C', 'AEE04_CODE']);

        // 查询去年同月患者数据
        $lastYearPatients = PatientInfo::query()
            ->whereBetween('AAC01', [$timeRanges['lastYear']['start'], $timeRanges['lastYear']['end']])
            ->get(['MED_REC_ID', 'AAC02C', 'AEE04_CODE']);

        // 获取所有JZHM列表
        $currentJzhmList = $currentPatients->pluck('MED_REC_ID')->toArray();
        $lastJzhmList = $lastPatients->pluck('MED_REC_ID')->toArray();
        $lastYearJzhmList = $lastYearPatients->pluck('MED_REC_ID')->toArray();

        // 一次性查询所有缺陷数据
        $allJzhmList = array_unique(array_merge($currentJzhmList, $lastJzhmList, $lastYearJzhmList));

        // 查询病历缺陷（按JZHM分组）
        $caseQualityData = CaseQuality::query()
            ->whereIn('JZHM', $allJzhmList)
            ->get(['JZHM', 'rule_id']);

        $caseQualityList = [];
        foreach ($caseQualityData as $item) {
            $jzhm = $item->JZHM;
            if (!isset($caseQualityList[$jzhm])) {
                $caseQualityList[$jzhm] = [];
            }
            $caseQualityList[$jzhm][] = $item;
        }

        // 查询首页缺陷（按ZYH分组）
        $homeQualityData = HomeQuality::query()
            ->whereIn('ZYH', $allJzhmList)
            ->where('is_del', 0)
            ->get(['ZYH', 'error_rule']);

        $homeQualityList = [];
        foreach ($homeQualityData as $item) {
            $zyh = $item->ZYH;
            if (!isset($homeQualityList[$zyh])) {
                $homeQualityList[$zyh] = [];
            }
            $homeQualityList[$zyh][] = $item;
        }

        return [
            'current' => [
                'patients' => $currentPatients,
                'jzhmList' => $currentJzhmList,
            ],
            'last' => [
                'patients' => $lastPatients,
                'jzhmList' => $lastJzhmList,
            ],
            'lastYear' => [
                'patients' => $lastYearPatients,
                'jzhmList' => $lastYearJzhmList,
            ],
            'caseQualityList' => $caseQualityList,
            'homeQualityList' => $homeQualityList,
        ];
    }

    /**
     * 获取统计数据（使用缓存数据）
     * @param string $time 时间参数，格式：2025年12月 或 2025第一季度 或 2025年
     * @param array $timeRanges 时间范围
     * @param array $baseData 基础数据
     * @param array $periodData 期间数据
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getStatisticsData($time, $timeRanges, $baseData, $periodData, $depIds = [])
    {
        $logPrefix = '[QualityReport]';
        $year = $timeRanges['year'];
        $qi = $timeRanges['qi'];
        $qinum = $timeRanges['qinum'];
        $isYear = $timeRanges['isYear'] ?? false;

        // 封面页变量
        $result = [
            'year' => (string)$year,
            'qi' => (string)$qi,
            'time' => $time,
            'qinum' => (string)$qinum,
        ];

        // 当前期（本月）总体情况数据（使用缓存数据）
        $stepStart = microtime(true);
        $currentStats = $this->getCurrentPeriodStatistics($timeRanges['current']['start'], $timeRanges['current']['end'], $periodData, $timeRanges, $depIds);
        $result = array_merge($result, $currentStats);
        Log::info("{$logPrefix} [步骤5.1] 当前期总体情况统计完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

        // 当前期（本月）病案等级数据（使用缓存数据）
        $stepStart = microtime(true);
        $currentLevelStats = $this->getCurrentPeriodLevelStatistics($timeRanges['current']['start'], $timeRanges['current']['end'], $baseData, $periodData, $depIds);
        $result = array_merge($result, $currentLevelStats);
        Log::info("{$logPrefix} [步骤5.2] 当前期病案等级统计完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

        // 上期（上个月）数据（使用缓存数据）
        $stepStart = microtime(true);
        $lastPeriodStats = $this->getLastPeriodStatistics($timeRanges['last']['start'], $timeRanges['last']['end'], $periodData, $depIds);
        $result = array_merge($result, $lastPeriodStats);
        Log::info("{$logPrefix} [步骤5.3] 上期总体情况统计完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

        // 上期（上个月）病案等级数据（使用缓存数据）
        $stepStart = microtime(true);
        $lastPeriodLevelStats = $this->getLastPeriodLevelStatistics($timeRanges['last']['start'], $timeRanges['last']['end'], $baseData, $periodData, $depIds);
        $result = array_merge($result, $lastPeriodLevelStats);
        Log::info("{$logPrefix} [步骤5.4] 上期病案等级统计完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

        // 环比数据（与上个月/上期对比，历史字段名 tb* 沿用）
        $stepStart = microtime(true);
        $tbStats = $this->calculateYearOverYear($currentStats, $lastPeriodStats, $currentLevelStats, $lastPeriodLevelStats);
        $result = array_merge($result, $tbStats);
        Log::info("{$logPrefix} [步骤5.5] 环比数据计算完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

        // 同比数据（与去年同期对比，历史字段名 hb* 沿用）
        $stepStart = microtime(true);
        if ($isYear) {
            $hbStats = [
                'hbbfb' => '-',
                'jjhbbfb' => '-',
            ];
        } else {
            $lastYearStats = $this->getLastPeriodStatistics($timeRanges['lastYear']['start'], $timeRanges['lastYear']['end'], $periodData, $depIds);
            $lastYearLevelStats = $this->getLastPeriodLevelStatistics($timeRanges['lastYear']['start'], $timeRanges['lastYear']['end'], $baseData, $periodData, $depIds);
            $hbStats = $this->calculateMonthOverMonth($currentStats, $lastYearStats, $currentLevelStats, $lastYearLevelStats);
        }
        $result = array_merge($result, $hbStats);
        Log::info("{$logPrefix} [步骤5.6] 同比数据计算完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

        // 指标统计数据
        $stepStart = microtime(true);
        $indicatorStats = $this->getIndicatorStatistics($timeRanges['current']['start'], $timeRanges['current']['end'], $timeRanges['last']['start'], $timeRanges['last']['end'], $timeRanges['lastYear']['start'], $timeRanges['lastYear']['end'], $isYear);
        $result = array_merge($result, $indicatorStats);
        Log::info("{$logPrefix} [步骤5.7] 指标统计数据计算完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

        return $result;
    }

    /**
     * 构建 Word 导出统计数据
     * 说明：历史字段名 tb* / hb* 的实际语义与命名相反，导出时统一修正为模板期望的口径
     * @param array $statisticsData
     * @return array
     */
    private function buildWordExportStatisticsData($statisticsData)
    {
        $exportStatisticsData = $statisticsData;

        $swapFields = [
            ['tb' => 'tbbfb', 'hb' => 'hbbfb'],
            ['tb' => 'jjtbbfb', 'hb' => 'jjhbbfb'],
            ['tb' => 'ryjltb', 'hb' => 'ryjlhb'],
            ['tb' => 'ssjltb', 'hb' => 'ssjlhb'],
            ['tb' => 'cyjltb', 'hb' => 'cyjlhb'],
            ['tb' => 'basytb', 'hb' => 'basyhb'],
            ['tb' => 'ctmrtb', 'hb' => 'ctmrhb'],
            ['tb' => 'bljctb', 'hb' => 'bljchb'],
            ['tb' => 'xjpytb', 'hb' => 'xjpyhb'],
            ['tb' => 'kjywtb', 'hb' => 'kjywhb'],
            ['tb' => 'hlywtb', 'hb' => 'hlywhb'],
            ['tb' => 'fszltb', 'hb' => 'fszlhb'],
            ['tb' => 'ssxgtb', 'hb' => 'ssxghb'],
            ['tb' => 'zrwtb', 'hb' => 'zrwhb'],
            ['tb' => 'lcyxtb', 'hb' => 'lcyxhb'],
            ['tb' => 'yscftb', 'hb' => 'yscfhb'],
            ['tb' => 'qjjstb', 'hb' => 'qjjshb'],
            ['tb' => 'lrgdtb', 'hb' => 'lrgdhb'],
            ['tb' => 'drgdtb', 'hb' => 'drgdhb'],
            ['tb' => 'zdmctb', 'hb' => 'zdmchb'],
            ['tb' => 'zdbmtb', 'hb' => 'zdbmhb'],
            ['tb' => 'ssmctb', 'hb' => 'ssmchb'],
            ['tb' => 'ssbmtb', 'hb' => 'ssbmhb'],
            ['tb' => 'bhlfztb', 'hb' => 'bhlfzhb'],
            ['tb' => 'zqtystb', 'hb' => 'zqtyshb'],
            ['tb' => 'jjblltb', 'hb' => 'jjbllhb'],
        ];

        foreach ($swapFields as $field) {
            $tbKey = $field['tb'];
            $hbKey = $field['hb'];
            $tbValue = $exportStatisticsData[$tbKey] ?? null;
            $hbValue = $exportStatisticsData[$hbKey] ?? null;
            $exportStatisticsData[$tbKey] = $hbValue;
            $exportStatisticsData[$hbKey] = $tbValue;
        }

        return $exportStatisticsData;
    }

    /**
     * 格式化前端需要的数据结构（按菜单分组）
     * @param string $time 时间参数
     * @param array $timeRanges 时间范围
     * @param array $baseData 基础数据
     * @param array $periodData 期间数据
     * @param array $statisticsData 统计数据
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function formatDataForFrontend($time, $timeRanges, $baseData, $periodData, $statisticsData, $depIds = [])
    {
        // 获取当前登录用户信息
        $request = request();
        $token = $request->header('token');
        $userInfo = null;
        $currentDepartments = '';
        $departmentList = [];

        if (!empty($token)) {
            // 优先尝试从 request->user() 获取
            $userInfo = $request->user();

            // 如果为空，尝试从 Session 获取
            if (empty($userInfo)) {
                $userInfo = \Illuminate\Support\Facades\Session::get($token);
            }

            // 如果 Session 中也没有，尝试从数据库查询
            if (empty($userInfo)) {
                $userInfo = \App\Model\User::findWhereToken($token);
            }

            if (!empty($userInfo) && is_array($userInfo)) {
                // 获取用户的科室ID（JSON格式：["D101"]）
                $depIdJson = $userInfo['dep_id'] ?? '';

                if (!empty($depIdJson)) {
                    $userDepIds = json_decode($depIdJson, true);

                    if (is_array($userDepIds) && !empty($userDepIds)) {
                        // 查询管理科室配置（id=4001）
                        $adminDepts = \App\Model\RuleWordMap::getFirstById(4001);
                        $isAdminDept = false;

                        // 判断是否是管理科室
                        if (is_array($adminDepts)) {
                            // 如果返回的是数组，说明有多个管理科室
                            foreach ($userDepIds as $depId) {
                                if (in_array($depId, $adminDepts)) {
                                    $isAdminDept = true;
                                    break;
                                }
                            }
                        } elseif (is_string($adminDepts)) {
                            // 如果返回的是字符串，需要分割
                            $adminDepArray = explode(',', str_replace('，', ',', $adminDepts));
                            foreach ($userDepIds as $depId) {
                                if (in_array($depId, $adminDepArray)) {
                                    $isAdminDept = true;
                                    break;
                                }
                            }
                        }

                        // 根据是否是管理科室，返回不同的科室列表
                        if ($isAdminDept) {
                            // 管理科室：返回所有科室
                            $departments = \App\Model\Department::query()
                                ->select(['dep_id', 'dep_name'])
                                ->get()
                                ->toArray();
                        } else {
                            // 普通科室：只返回用户所属科室
                            $departments = \App\Model\Department::query()
                                ->whereIn('dep_id', $userDepIds)
                                ->select(['dep_id', 'dep_name'])
                                ->get()
                                ->toArray();
                        }

                        // 格式化科室列表
                        $departmentList = array_map(function ($dept) {
                            return [
                                'dep_id' => $dept['dep_id'],
                                'dep_name' => $dept['dep_name']
                            ];
                        }, $departments);

                        // 格式化当前所属科室名称（仅显示用户自身的科室：科室1，科室2）
                        $userOwnDepartments = \App\Model\Department::query()
                            ->whereIn('dep_id', $userDepIds)
                            ->select(['dep_id', 'dep_name'])
                            ->get()
                            ->toArray();
                        $currentDeptNames = array_column($userOwnDepartments, 'dep_name');
                        $currentDepartments = implode('，', $currentDeptNames);
                    }
                }
            }
        }

        $frontendData = [
            // 基础信息
            'basic_info' => [
                'year' => $statisticsData['year'],
                'qi' => $statisticsData['qi'],
                'time' => $statisticsData['time'],
                'qinum' => $statisticsData['qinum'],
                'current_departments' => $currentDepartments, // 当前科室（科室1，科室2）
            ],

            // 科室列表
            'department_list' => $departmentList,

            // 一、病历质控概况
            'overview' => [
                // (一) 总体情况
                'overall' => [
                    'current' => [
                        'total_cases' => (int)$statisticsData['blnum'],
                        'defect_cases' => (int)$statisticsData['qxblnum'],
                        'defect_ratio' => (float)$statisticsData['qxradio'],
                        'avg_defects' => (float)$statisticsData['qxnum'],
                    ],
                    'last_period' => [
                        'time' => $statisticsData['bftime'],
                        'total_cases' => (int)$statisticsData['bfblnum'],
                        'defect_cases' => (int)$statisticsData['bfqxblnum'],
                        'defect_ratio' => (float)$statisticsData['bfqxradio'],
                    ],
                    'comparison' => [
                        'defect_ratio_yoy' => $this->convertArrowToSign($statisticsData['hbbfb']), // 同比（与去年同期对比）
                        'defect_ratio_mom' => $this->convertArrowToSign($statisticsData['tbbfb']), // 环比（与上期对比）
                    ],
                ],

                // 质控趋势分析（12个月数据）
                'trend_analysis' => $this->getQualityTrendAnalysis($timeRanges, $depIds),

                // 病案等级统计
                'case_level' => [
                    'current' => [
                        'grade_a' => [
                            'count' => (int)$statisticsData['jjblnum'],
                            'ratio' => (float)$statisticsData['jjradio'],
                        ],
                        'grade_b' => [
                            'count' => (int)$statisticsData['yjblnum'],
                            'ratio' => (float)$statisticsData['yjradio'],
                        ],
                        'grade_c' => [
                            'count' => (int)$statisticsData['bjblnum'],
                            'ratio' => (float)$statisticsData['bjradio'],
                        ],
                    ],
                    'last_period' => [
                        'grade_a' => [
                            'count' => (int)$statisticsData['bfjjblnum'],
                            'ratio' => (float)$statisticsData['bfjjradio'],
                        ],
                        'grade_b' => [
                            'count' => (int)$statisticsData['bfyjblnum'],
                            'ratio' => (float)$statisticsData['bfyjradio'],
                        ],
                        'grade_c' => [
                            'count' => (int)$statisticsData['bfbjblnum'],
                            'ratio' => (float)$statisticsData['bfbjradio'],
                        ],
                    ],
                    'comparison' => [
                        'grade_a_yoy' => $this->convertArrowToSign($statisticsData['jjhbbfb']),
                        'grade_a_mom' => $this->convertArrowToSign($statisticsData['jjtbbfb']),
                    ],
                ],

                // (二) 病案管理质量控制指标统计
                'quality_indicators' => [
                    // 1. 病历书写时效性指标
                    'timeliness' => [
                        [
                            'category' => 40,
                            'name' => '入院记录24小时内完成率',
                            'numerator' => (int)$statisticsData['ryjlfenzi'],
                            'denominator' => (int)$statisticsData['ryjlfenmu'],
                            'ratio' => (float)$statisticsData['ryjlradio'],
                            'last_ratio' => (float)$statisticsData['ryjllastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['ryjlhb']),
                            'mom' => $this->convertArrowToSign($statisticsData['ryjltb']),
                        ],
                        [
                            'category' => 41,
                            'name' => '手术记录24小时内完成率',
                            'numerator' => (int)$statisticsData['ssjlfenzi'],
                            'denominator' => (int)$statisticsData['ssjlfenmu'],
                            'ratio' => (float)$statisticsData['ssjlradio'],
                            'last_ratio' => (float)$statisticsData['ssjllastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['ssjlhb']),
                            'mom' => $this->convertArrowToSign($statisticsData['ssjltb']),
                        ],
                        [
                            'category' => 42,
                            'name' => '出院记录24小时内完成率',
                            'numerator' => (int)$statisticsData['cyjlfenzi'],
                            'denominator' => (int)$statisticsData['cyjlfenmu'],
                            'ratio' => (float)$statisticsData['cyjlradio'],
                            'last_ratio' => (float)$statisticsData['cyjllastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['cyjlhb']),
                            'mom' => $this->convertArrowToSign($statisticsData['cyjltb']),
                        ],
                        [
                            'category' => 43,
                            'name' => '病案首页24小时内完成率',
                            'numerator' => (int)$statisticsData['basyfenzi'],
                            'denominator' => (int)$statisticsData['basyfenmu'],
                            'ratio' => (float)$statisticsData['basyradio'],
                            'last_ratio' => (float)$statisticsData['basylastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['basyhb']),
                            'mom' => $this->convertArrowToSign($statisticsData['basytb']),
                        ],
                    ],

                    // 2. 重大检查记录符合率
                    'major_examination' => [
                        [
                            'category' => 44,
                            'name' => 'CT/MRI检查记录符合率',
                            'numerator' => (int)$statisticsData['ctmrfenzi'],
                            'denominator' => (int)$statisticsData['ctmrfenmu'],
                            'ratio' => (float)$statisticsData['ctmrradio'],
                            'last_ratio' => (float)$statisticsData['ctmrlastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['ctmrhb']),
                            'mom' => $this->convertArrowToSign($statisticsData['ctmrtb']),
                        ],
                        [
                            'category' => 45,
                            'name' => '病理检查记录符合率',
                            'numerator' => (int)$statisticsData['bljcfenzi'],
                            'denominator' => (int)$statisticsData['bljcfenmu'],
                            'ratio' => (float)$statisticsData['bljcradio'],
                            'last_ratio' => (float)$statisticsData['bljclastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['bljchb']),
                            'mom' => $this->convertArrowToSign($statisticsData['bljctb']),
                        ],
                        [
                            'category' => 46,
                            'name' => '细菌培养检查记录符合率',
                            'numerator' => (int)$statisticsData['xjpyfenzi'],
                            'denominator' => (int)$statisticsData['xjpyfenmu'],
                            'ratio' => (float)$statisticsData['xjpyradio'],
                            'last_ratio' => (float)$statisticsData['xjpylastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['xjpyhb']),
                            'mom' => $this->convertArrowToSign($statisticsData['xjpytb']),
                        ],
                    ],

                    // 3. 诊疗行为记录符合率
                    'treatment_behavior' => [
                        [
                            'category' => 47,
                            'name' => '抗菌药物使用记录符合率',
                            'numerator' => (int)$statisticsData['kjywfenzi'],
                            'denominator' => (int)$statisticsData['kjywfenmu'],
                            'ratio' => (float)$statisticsData['kjywradio'],
                            'last_ratio' => (float)$statisticsData['kjywlastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['kjywhb']),
                            'mom' => $this->convertArrowToSign($statisticsData['kjywtb']),
                        ],
                        [
                            'category' => 48,
                            'name' => '恶性肿瘤化学治疗记录符合率',
                            'numerator' => (int)$statisticsData['hlywfenzi'],
                            'denominator' => (int)$statisticsData['hlywfenmu'],
                            'ratio' => (float)$statisticsData['hlywradio'],
                            'last_ratio' => (float)$statisticsData['hlywlastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['hlywhb']),
                            'mom' => $this->convertArrowToSign($statisticsData['hlywtb']),
                        ],
                        [
                            'category' => 49,
                            'name' => '恶性肿瘤放射治疗记录符合率',
                            'numerator' => (int)$statisticsData['fszlfenzi'],
                            'denominator' => (int)$statisticsData['fszlfenmu'],
                            'ratio' => (float)$statisticsData['fszlradio'],
                            'last_ratio' => (float)$statisticsData['fszllastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['fszlhb']),
                            'mom' => $this->convertArrowToSign($statisticsData['fszltb']),
                        ],
                        [
                            'category' => 50,
                            'name' => '手术相关记录完整率',
                            'numerator' => (int)$statisticsData['ssxgfenzi'],
                            'denominator' => (int)$statisticsData['ssxgfenmu'],
                            'ratio' => (float)$statisticsData['ssxgradio'],
                            'last_ratio' => (float)$statisticsData['ssxglastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['ssxghb']),
                            'mom' => $this->convertArrowToSign($statisticsData['ssxgtb']),
                        ],
                        [
                            'category' => 51,
                            'name' => '植入物相关记录符合率',
                            'numerator' => (int)$statisticsData['zrwfenzi'],
                            'denominator' => (int)$statisticsData['zrwfenmu'],
                            'ratio' => (float)$statisticsData['zrwradio'],
                            'last_ratio' => (float)$statisticsData['zrwlastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['zrwhb']),
                            'mom' => $this->convertArrowToSign($statisticsData['zrwtb']),
                        ],
                        [
                            'category' => 52,
                            'name' => '临床用血相关记录符合率',
                            'numerator' => (int)$statisticsData['lcyxfenzi'],
                            'denominator' => (int)$statisticsData['lcyxfenmu'],
                            'ratio' => (float)$statisticsData['lcyxradio'],
                            'last_ratio' => (float)$statisticsData['lcyxlastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['lcyxhb']),
                            'mom' => $this->convertArrowToSign($statisticsData['lcyxtb']),
                        ],
                        [
                            'category' => 53,
                            'name' => '医师查房记录完整率',
                            'numerator' => (int)$statisticsData['yscffenzi'],
                            'denominator' => (int)$statisticsData['yscffenmu'],
                            'ratio' => (float)$statisticsData['yscfradio'],
                            'last_ratio' => (float)$statisticsData['yscflastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['yscfhb']),
                            'mom' => $this->convertArrowToSign($statisticsData['yscftb']),
                        ],
                        [
                            'category' => 54,
                            'name' => '患者抢救记录及时完成率',
                            'numerator' => (int)$statisticsData['qjjsfenzi'],
                            'denominator' => (int)$statisticsData['qjjsfenmu'],
                            'ratio' => (float)$statisticsData['qjjsradio'],
                            'last_ratio' => (float)$statisticsData['qjjslastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['qjjshb']),
                            'mom' => $this->convertArrowToSign($statisticsData['qjjstb']),
                        ],
                    ],

                    // 4. 病历归档质量指标
                    'archiving_quality' => [
                        [
                            'category' => 55,
                            'name' => '出院患者病历2日归档率',
                            'numerator' => (int)$statisticsData['lrgdfenzi'],
                            'denominator' => (int)$statisticsData['lrgdfenmu'],
                            'ratio' => (float)$statisticsData['lrgdradio'],
                            'last_ratio' => (float)$statisticsData['lrgdlastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['lrgdhb']),
                            'mom' => $this->convertArrowToSign($statisticsData['lrgdtb']),
                        ],
                        [
                            'category' => 56,
                            'name' => '出院患者病历归档完整率',
                            'numerator' => (int)$statisticsData['drgdfenzi'],
                            'denominator' => (int)$statisticsData['drgdfenmu'],
                            'ratio' => (float)$statisticsData['drgdradio'],
                            'last_ratio' => (float)$statisticsData['drgdlastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['drgdhb']),
                            'mom' => $this->convertArrowToSign($statisticsData['drgdtb']),
                        ],
                        [
                            'category' => 57,
                            'name' => '主要诊断填写正确率',
                            'numerator' => (int)$statisticsData['zdmcfenzi'],
                            'denominator' => (int)$statisticsData['zdmcfenmu'],
                            'ratio' => (float)$statisticsData['zdmcradio'],
                            'last_ratio' => (float)$statisticsData['zdmclastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['zdmchb']),
                            'mom' => $this->convertArrowToSign($statisticsData['zdmctb']),
                        ],
                        [
                            'category' => 58,
                            'name' => '主要诊断编码正确率',
                            'numerator' => (int)$statisticsData['zdbmfenzi'],
                            'denominator' => (int)$statisticsData['zdbmfenmu'],
                            'ratio' => (float)$statisticsData['zdbmradio'],
                            'last_ratio' => (float)$statisticsData['zdbmlastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['zdbmhb']),
                            'mom' => $this->convertArrowToSign($statisticsData['zdbmtb']),
                        ],
                        [
                            'category' => 59,
                            'name' => '主要手术填写正确率',
                            'numerator' => (int)$statisticsData['ssmcfenzi'],
                            'denominator' => (int)$statisticsData['ssmcfenmu'],
                            'ratio' => (float)$statisticsData['ssmcradio'],
                            'last_ratio' => (float)$statisticsData['ssmclastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['ssmchb']),
                            'mom' => $this->convertArrowToSign($statisticsData['ssmctb']),
                        ],
                        [
                            'category' => 60,
                            'name' => '主要手术编码正确率',
                            'numerator' => (int)$statisticsData['ssbmfenzi'],
                            'denominator' => (int)$statisticsData['ssbmfenmu'],
                            'ratio' => (float)$statisticsData['ssbmradio'],
                            'last_ratio' => (float)$statisticsData['ssbmlastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['ssbmhb']),
                            'mom' => $this->convertArrowToSign($statisticsData['ssbmtb']),
                        ],
                        [
                            'category' => 61,
                            'name' => '不合理复制病历发生率',
                            'numerator' => (int)$statisticsData['bhlfzfenzi'],
                            'denominator' => (int)$statisticsData['bhlfzfenmu'],
                            'ratio' => (float)$statisticsData['bhlfzradio'],
                            'last_ratio' => (float)$statisticsData['bhlfzlastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['bhlfzhb']),
                            'mom' => $this->convertArrowToSign($statisticsData['bhlfztb']),
                        ],
                        [
                            'category' => 62,
                            'name' => '知情同意书规范签署率',
                            'numerator' => (int)$statisticsData['zqtysfenzi'],
                            'denominator' => (int)$statisticsData['zqtysfenmu'],
                            'ratio' => (float)$statisticsData['zqtysradio'],
                            'last_ratio' => (float)$statisticsData['zqtyslastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['zqtyshb']),
                            'mom' => $this->convertArrowToSign($statisticsData['zqtystb']),
                        ],
                        [
                            'category' => 63,
                            'name' => '甲级病历率',
                            'numerator' => (int)$statisticsData['jjbllfenzi'],
                            'denominator' => (int)$statisticsData['jjbllfenmu'],
                            'ratio' => (float)$statisticsData['jjbllradio'],
                            'last_ratio' => (float)$statisticsData['jjblllastradio'],
                            'yoy' => $this->convertArrowToSign($statisticsData['jjbllhb']),
                            'mom' => $this->convertArrowToSign($statisticsData['jjblltb']),
                        ],
                    ],
                ],
            ],

            // 二、缺陷问题分布
            'defect_distribution' => [
                // 缺陷类型分布（按type字段分组）
                'type_distribution' => $this->getDefectTypeDistribution($time, $depIds),

                // 缺陷问题TOP10（按缺陷数量排序）
                'top10' => $this->getDefectTop10($time, $depIds),

                // 时效性问题分布（前20名）
                'timeliness_rules' => $this->getRuleDistributionFromStats($time, '时效性', 20, $depIds),

                // 内涵质量问题分布（前20名）
                'content_rules' => $this->getRuleDistributionFromStats($time, '内涵性', 20, $depIds),

                // 病案首页问题分布（前20名）
                'homepage_rules' => $this->getHomeQualityRuleDistributionFromStats($time, 20, $depIds),
            ],

            // 三、病历质量（各科室甲乙丙级病历占比、单否项缺陷、医师申诉、指标达成情况）
            'quality_distribution' => [
                // (一) 各科室甲乙丙级病历占比
                'case_level_by_dept' => $this->getDepartmentCaseLevelStats($time, $depIds),

                // (二) 单否项缺陷情况
                'single_no_defects' => $this->getAllSingleNoDefectStats($time, $depIds),

                // (三) 医师申诉情况
                'appeal_stats' => [
                    'top5_rejected' => $this->getAppealTop5Rejected($time, $depIds),
                    'by_department' => $this->getAppealStatsByDepartment($time, $depIds),
                ],

                // (四) 病案管理质量控制指标达成情况
                'indicator_achievement' => [
                    'best5' => $this->getIndicatorAchievementBest5($time, $depIds),
                    'worst5' => $this->getIndicatorAchievementWorst5($time, $depIds),
                ],
            ],

            // 四、科室排名
            'department_ranking' => [
                'best5' => $this->getDepartmentRankingBest5($time, $depIds),
                'worst5' => $this->getDepartmentRankingWorst5($time, $depIds),
                'all' => $this->getDepartmentRankingAll($time, $depIds),
            ],

            // 五、医师排名
            'doctor_ranking' => [
                'best5' => $this->getDoctorRankingBest5($time, $depIds),
                'worst5' => $this->getDoctorRankingWorst5($time, $depIds),
                'all' => $this->getDoctorRankingAll($time, $depIds),
            ],
        ];

        $this->logTrace('frontend_summary', [
            'time' => $time,
            'type' => $timeRanges['type'] ?? '',
            'dep_ids' => $depIds,
            'overview_total_cases' => $frontendData['overview']['overall']['current']['total_cases'] ?? null,
            'department_list_count' => count($frontendData['department_list'] ?? []),
            'type_distribution_count' => count($frontendData['defect_distribution']['type_distribution'] ?? []),
            'top10_count' => count($frontendData['defect_distribution']['top10'] ?? []),
            'timeliness_rules_count' => count($frontendData['defect_distribution']['timeliness_rules'] ?? []),
            'content_rules_count' => count($frontendData['defect_distribution']['content_rules'] ?? []),
            'homepage_rules_count' => count($frontendData['defect_distribution']['homepage_rules'] ?? []),
            'case_level_by_dept_count' => count($frontendData['quality_distribution']['case_level_by_dept'] ?? []),
            'single_no_defects_count' => count($frontendData['quality_distribution']['single_no_defects'] ?? []),
            'appeal_top5_count' => count($frontendData['quality_distribution']['appeal_stats']['top5_rejected'] ?? []),
            'appeal_by_department_count' => count($frontendData['quality_distribution']['appeal_stats']['by_department'] ?? []),
            'indicator_best5_count' => count($frontendData['quality_distribution']['indicator_achievement']['best5'] ?? []),
            'indicator_worst5_count' => count($frontendData['quality_distribution']['indicator_achievement']['worst5'] ?? []),
            'department_best5_count' => count($frontendData['department_ranking']['best5'] ?? []),
            'department_worst5_count' => count($frontendData['department_ranking']['worst5'] ?? []),
            'department_all_count' => count($frontendData['department_ranking']['all'] ?? []),
            'doctor_best5_count' => count($frontendData['doctor_ranking']['best5'] ?? []),
            'doctor_worst5_count' => count($frontendData['doctor_ranking']['worst5'] ?? []),
            'doctor_all_count' => count($frontendData['doctor_ranking']['all'] ?? []),
        ]);

        return $frontendData;
    }

    /**
     * 获取当前期总体情况数据（从统计表读取，支持动态汇总和科室筛选）
     * @param string $startTime
     * @param string $endTime
     * @param array $periodData 期间数据
     * @param array $timeRanges 时间范围信息
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getCurrentPeriodStatistics($startTime, $endTime, $periodData, $timeRanges = [], $depIds = [])
    {
        // 解析时间生成期间标签
        $timestamp = strtotime($startTime);
        $year = date('Y', $timestamp);
        $startMonth = (int)date('m', $timestamp);

        $endTimestamp = strtotime($endTime);
        $endMonth = (int)date('m', $endTimestamp);

        // 判断是否需要汇总多个月份
        $isMultiMonth = ($startMonth != $endMonth) || (date('Y', $timestamp) != date('Y', $endTimestamp));

        if ($isMultiMonth) {
            // 需要汇总多个月份的数据
            return $this->aggregateMonthlyStats($startTime, $endTime, 'overall', $depIds);
        }

        // 单月数据，直接查询
        $periodLabel = $year . '年' . $startMonth . '月';

        Log::info('[QualityReport] getCurrentPeriodStatistics 查询参数', [
            'startTime' => $startTime,
            'periodLabel' => $periodLabel,
            'depIds' => $depIds
        ]);

        // 如果指定了科室，需要汇总科室数据
        if (!empty($depIds)) {
            $stats = $this->getCachedStatisticsRows([$periodLabel], QualityReportStatistics::TYPE_DEPARTMENT, [
                'dimension_ids' => $depIds,
                'columns' => ['id', 'total_cases', 'defect_cases', 'total_defects'],
            ]);

            if ($stats->isEmpty()) {
                return [
                    'blnum' => '0',
                    'qxblnum' => '0',
                    'qxradio' => '0.00',
                    'qxnum' => '0.00',
                ];
            }

            // 汇总科室数据
            $totalCases = $stats->sum('total_cases');
            $defectCases = $stats->sum('defect_cases');
            $totalDefects = $stats->sum('total_defects');

            $defectRatio = $totalCases > 0 ? ($defectCases / $totalCases) * 100 : 0;

            return [
                'blnum' => (string)$totalCases,
                'qxblnum' => (string)$defectCases,
                'qxradio' => number_format($defectRatio, 2),
                'qxnum' => (string)$totalDefects,  // 当期总缺陷数量
            ];
        }

        // 从统计表读取总体数据
        $stats = $this->getCachedStatisticsRows([$periodLabel], QualityReportStatistics::TYPE_OVERALL, [
            'columns' => ['id', 'total_cases', 'defect_cases', 'defect_ratio', 'total_defects'],
        ])->first();

        if (!$stats) {
            // 如果统计表没有数据,返回默认值
            return [
                'blnum' => '0',
                'qxblnum' => '0',
                'qxradio' => '0.00',
                'qxnum' => '0.00',
            ];
        }

        return [
            'blnum' => (string)$stats->total_cases,
            'qxblnum' => (string)$stats->defect_cases,
            'qxradio' => number_format($stats->defect_ratio, 2),
            'qxnum' => (string)$stats->total_defects,  // 当期总缺陷数量
        ];
    }

    /**
     * 解析时间参数并生成月份列表
     * @param string $time 时间参数，格式：2025年12月 或 2025年第一季度 或 2025年
     * @return array 月份列表，格式：['2025年1月', '2025年2月', ...]
     */
    private function parseTimeToMonths($time)
    {
        $originalTime = $time;

        // 与 parseTimeRanges 保持一致，先清理首尾/全角空格等字符，
        // 避免季度字符串在统计表聚合链路中匹配失败。
        $time = trim($time);
        $time = str_replace([' ', '　', "\t", "\n", "\r"], '', $time);

        $months = [];

        // 年度格式：2025年
        if (preg_match('/^(\d{4})年$/u', $time, $matches)) {
            $year = (int)$matches[1];
            for ($month = 1; $month <= 12; $month++) {
                $months[] = $year . '年' . $month . '月';
            }
            $this->logTrace('parse_time_to_months', [
                'original_time' => $originalTime,
                'normalized_time' => $time,
                'months' => $months,
            ]);
            return $months;
        }

        // 季度格式：2025年第一季度
        if (preg_match('/^(\d{4})年第([一二三四])季度$/u', $time, $matches)) {
            $year = (int)$matches[1];
            $quarter = $matches[2];

            $quarterMap = [
                '一' => [1, 2, 3],
                '二' => [4, 5, 6],
                '三' => [7, 8, 9],
                '四' => [10, 11, 12],
            ];

            if (isset($quarterMap[$quarter])) {
                foreach ($quarterMap[$quarter] as $month) {
                    $months[] = $year . '年' . $month . '月';
                }
            }
            $this->logTrace('parse_time_to_months', [
                'original_time' => $originalTime,
                'normalized_time' => $time,
                'months' => $months,
            ]);
            return $months;
        }

        // 月份格式：2025年12月（单月）
        if (preg_match('/^(\d{4})年(\d{1,2})月$/u', $time, $matches)) {
            $months = [$time];
            $this->logTrace('parse_time_to_months', [
                'original_time' => $originalTime,
                'normalized_time' => $time,
                'months' => $months,
            ]);
            return $months;
        }

        // 如果无法解析，返回原值
        $months = [$time];
        $this->logTrace('parse_time_to_months', [
            'original_time' => $originalTime,
            'normalized_time' => $time,
            'months' => $months,
            'fallback' => true,
        ]);
        return $months;
    }

    /**
     * 展开统计期间标签，兼容 2026年2月 / 2026年02月 两种月标签格式。
     *
     * 医院环境里如果统计表存在历史数据格式差异，同一月份可能有不同写法。
     * 这里统一扩展查询条件，避免季度拆月后因为标签格式不一致导致部分模块查空。
     *
     * @param array $periods
     * @return array
     */
    private function expandStatisticPeriods(array $periods)
    {
        $expandedPeriods = [];

        foreach ($periods as $period) {
            if (!is_string($period) || $period === '') {
                continue;
            }

            $normalizedPeriod = trim($period);
            $normalizedPeriod = str_replace([' ', '　', "\t", "\n", "\r"], '', $normalizedPeriod);

            if ($normalizedPeriod === '') {
                continue;
            }

            $expandedPeriods[$normalizedPeriod] = true;

            if (preg_match('/^(\d{4})年0?(\d{1,2})月$/u', $normalizedPeriod, $matches)) {
                $year = $matches[1];
                $month = (int)$matches[2];

                $expandedPeriods[$year . '年' . $month . '月'] = true;
                $expandedPeriods[sprintf('%s年%02d月', $year, $month)] = true;
            }
        }

        $expandedPeriods = array_keys($expandedPeriods);
        sort($expandedPeriods);

        return $expandedPeriods;
    }

    /**
     * 判断时间范围是否跨多个月份。
     *
     * @param string $startTime
     * @param string $endTime
     * @return bool
     */
    private function isMultiMonthRange($startTime, $endTime)
    {
        $startTimestamp = strtotime($startTime);
        $endTimestamp = strtotime($endTime);

        return date('Y-m', $startTimestamp) !== date('Y-m', $endTimestamp);
    }

    /**
     * 根据时间范围生成报表展示标签。
     *
     * @param string $startTime
     * @param string $endTime
     * @return string
     */
    private function formatPeriodRangeLabel($startTime, $endTime)
    {
        $startTimestamp = strtotime($startTime);
        $endTimestamp = strtotime($endTime);

        $startYear = (int)date('Y', $startTimestamp);
        $endYear = (int)date('Y', $endTimestamp);
        $startMonth = (int)date('n', $startTimestamp);
        $endMonth = (int)date('n', $endTimestamp);

        if ($startYear === $endYear && $startMonth === $endMonth) {
            return $startYear . '年' . $startMonth . '月';
        }

        if ($startYear === $endYear && $startMonth === 1 && $endMonth === 12) {
            return $startYear . '年';
        }

        $quarterMap = [
            '1-3' => '第一季度',
            '4-6' => '第二季度',
            '7-9' => '第三季度',
            '10-12' => '第四季度',
        ];
        $quarterKey = $startMonth . '-' . $endMonth;
        if ($startYear === $endYear && isset($quarterMap[$quarterKey])) {
            return $startYear . '年' . $quarterMap[$quarterKey];
        }

        if ($startYear === $endYear) {
            return $startYear . '年' . $startMonth . '月-' . $endMonth . '月';
        }

        return $startYear . '年' . $startMonth . '月-' . $endYear . '年' . $endMonth . '月';
    }

    /**
     * 规则分布表无数据时使用的占位行，避免模板变量残留。
     *
     * @param string $prefix
     * @return array
     */
    private function getEmptyRuleDistributionRow($prefix)
    {
        return [[
            $prefix . 'id' => '',
            $prefix . 'mc' => '暂无数据',
            $prefix . 'blnum' => '0',
            $prefix . 'qxnum' => '0',
            $prefix . 'radio' => '0.00',
        ]];
    }

    /**
     * 获取统计表查询结果并做请求级缓存。
     *
     * @param array $months
     * @param string $statType
     * @param array $options
     * @return \Illuminate\Support\Collection
     */
    private function getCachedStatisticsRows(array $months, $statType, array $options = [])
    {
        $originalMonths = $months;
        $normalizedMonths = $this->expandStatisticPeriods($months);

        $dimensionIds = $options['dimension_ids'] ?? [];
        if (!empty($dimensionIds)) {
            $dimensionIds = array_values(array_unique($dimensionIds));
            sort($dimensionIds);
        }

        $columns = $options['columns'] ?? ['*'];
        $cacheKey = md5(json_encode([
            'months' => $normalizedMonths,
            'stat_type' => $statType,
            'dimension_ids' => $dimensionIds,
            'rule_case_count_gt_zero' => !empty($options['rule_case_count_gt_zero']),
            'is_single_no' => $options['is_single_no'] ?? null,
            'dimension_type' => $options['extra_data_dimension_type'] ?? null,
            'columns' => $columns,
        ], JSON_UNESCAPED_UNICODE));

        $cacheHit = isset($this->statisticsQueryCache[$cacheKey]);

        if (!$cacheHit) {
            $query = QualityReportStatistics::query()
                ->whereIn('stat_period', $normalizedMonths)
                ->where('stat_type', $statType);

            if (!empty($dimensionIds)) {
                $query->whereIn('dimension_id', $dimensionIds);
            }

            if (!empty($options['rule_case_count_gt_zero'])) {
                $query->where('rule_case_count', '>', 0);
            }

            if (array_key_exists('is_single_no', $options)) {
                $query->where('is_single_no', (int)$options['is_single_no']);
            }

            if (!empty($options['extra_data_dimension_type'])) {
                $query->whereRaw(
                    'JSON_UNQUOTE(JSON_EXTRACT(extra_data, "$.dimension_type")) = ?',
                    [$options['extra_data_dimension_type']]
                );
            }

            $this->statisticsQueryCache[$cacheKey] = $query->get($columns);
        }

        $result = $this->statisticsQueryCache[$cacheKey];

        $this->logTrace('statistics_query', [
            'cache_hit' => $cacheHit,
            'stat_type' => $statType,
            'months_input' => $originalMonths,
            'months_expanded' => $normalizedMonths,
            'dimension_ids' => $dimensionIds,
            'rule_case_count_gt_zero' => !empty($options['rule_case_count_gt_zero']),
            'is_single_no' => $options['is_single_no'] ?? null,
            'dimension_type' => $options['extra_data_dimension_type'] ?? null,
            'columns' => $columns,
            'result_count' => $result->count(),
        ]);

        return $result;
    }

    /**
     * 解析统计记录 extra_data，并在请求内缓存结果。
     *
     * @param mixed $stat
     * @return array
     */
    private function getStatisticsExtraData($stat)
    {
        $cacheKey = $stat->id ?? spl_object_hash($stat);

        if (!array_key_exists($cacheKey, $this->statisticsExtraDataCache)) {
            $this->statisticsExtraDataCache[$cacheKey] = json_decode($stat->extra_data, true) ?? [];
        }

        return $this->statisticsExtraDataCache[$cacheKey];
    }

    /**
     * 获取期间内总病历数，复用统计表缓存。
     *
     * @param array $months
     * @param array $depIds
     * @return int
     */
    private function getCachedTotalCases(array $months, array $depIds = [])
    {
        if (!empty($depIds)) {
            return (int)$this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_DEPARTMENT, [
                'dimension_ids' => $depIds,
                'columns' => ['id', 'total_cases'],
            ])->sum('total_cases');
        }

        return (int)$this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_OVERALL, [
            'columns' => ['id', 'total_cases'],
        ])->sum('total_cases');
    }

    /**
     * 获取报告中需要的指标列表并缓存。
     *
     * @return array
     */
    private function getCachedReportIndicators()
    {
        if ($this->reportIndicatorsCache === null) {
            $this->reportIndicatorsCache = $this->getReportIndicators();
        }

        return $this->reportIndicatorsCache;
    }

    /**
     * 汇总多个月份的统计数据（支持科室筛选）
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param string $statType 统计类型
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function aggregateMonthlyStats($startTime, $endTime, $statType = 'overall', $depIds = [])
    {
        // 生成月份列表
        $months = [];
        $current = strtotime($startTime);
        $end = strtotime($endTime);

        while ($current <= $end) {
            $year = date('Y', $current);
            $month = (int)date('m', $current);
            $months[] = $year . '年' . $month . '月';
            $current = strtotime('+1 month', $current);
        }

        Log::info('[QualityReport] 汇总月份数据', [
            'months' => $months,
            'stat_type' => $statType,
            'dep_ids' => $depIds
        ]);

        // 如果指定了科室，查询科室维度数据
        if (!empty($depIds)) {
            $stats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_DEPARTMENT, [
                'dimension_ids' => $depIds,
                'columns' => ['id', 'total_cases', 'defect_cases', 'total_defects'],
            ]);
        } else {
            // 查询所有月份的数据
            $stats = $this->getCachedStatisticsRows($months, $statType, [
                'columns' => ['id', 'total_cases', 'defect_cases', 'total_defects'],
            ]);
        }

        if ($stats->isEmpty()) {
            return [
                'blnum' => '0',
                'qxblnum' => '0',
                'qxradio' => '0.00',
                'qxnum' => '0.00',
            ];
        }

        // 汇总数据
        $totalCases = $stats->sum('total_cases');
        $defectCases = $stats->sum('defect_cases');
        $totalDefects = $stats->sum('total_defects');

        $defectRatio = $totalCases > 0 ? ($defectCases / $totalCases) * 100 : 0;

        return [
            'blnum' => (string)$totalCases,
            'qxblnum' => (string)$defectCases,
            'qxradio' => number_format($defectRatio, 2),
            'qxnum' => (string)$totalDefects,  // 当期总缺陷数量
        ];
    }

    /**
     * 获取当前期病案等级数据（从统计表读取，支持动态汇总和科室筛选）
     * @param string $startTime
     * @param string $endTime
     * @param array $baseData 基础数据
     * @param array $periodData 期间数据
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getCurrentPeriodLevelStatistics($startTime, $endTime, $baseData, $periodData, $depIds = [])
    {
        // 解析时间生成期间标签
        $timestamp = strtotime($startTime);
        $year = date('Y', $timestamp);
        $startMonth = (int)date('m', $timestamp);

        $endTimestamp = strtotime($endTime);
        $endMonth = (int)date('m', $endTimestamp);

        // 判断是否需要汇总多个月份
        $isMultiMonth = ($startMonth != $endMonth) || (date('Y', $timestamp) != date('Y', $endTimestamp));

        if ($isMultiMonth) {
            // 需要汇总多个月份的数据
            return $this->aggregateMonthlyLevelStats($startTime, $endTime, $depIds);
        }

        // 单月数据，直接查询
        $periodLabel = $year . '年' . $startMonth . '月';

        // 如果指定了科室，需要汇总科室数据
        if (!empty($depIds)) {
            $stats = $this->getCachedStatisticsRows([$periodLabel], QualityReportStatistics::TYPE_DEPARTMENT, [
                'dimension_ids' => $depIds,
                'columns' => ['id', 'total_cases', 'grade_a_count', 'grade_b_count', 'grade_c_count'],
            ]);

            if ($stats->isEmpty()) {
                return [
                    'jjblnum' => '0',
                    'jjradio' => '0.00',
                    'yjblnum' => '0',
                    'yjradio' => '0.00',
                    'bjblnum' => '0',
                    'bjradio' => '0.00',
                ];
            }

            // 汇总科室数据
            $totalCases = $stats->sum('total_cases');
            $gradeACount = $stats->sum('grade_a_count');
            $gradeBCount = $stats->sum('grade_b_count');
            $gradeCCount = $stats->sum('grade_c_count');

            $gradeARatio = $totalCases > 0 ? ($gradeACount / $totalCases) * 100 : 0;
            $gradeBRatio = $totalCases > 0 ? ($gradeBCount / $totalCases) * 100 : 0;
            $gradeCRatio = $totalCases > 0 ? ($gradeCCount / $totalCases) * 100 : 0;

            return [
                'jjblnum' => (string)$gradeACount,
                'jjradio' => number_format($gradeARatio, 2),
                'yjblnum' => (string)$gradeBCount,
                'yjradio' => number_format($gradeBRatio, 2),
                'bjblnum' => (string)$gradeCCount,
                'bjradio' => number_format($gradeCRatio, 2),
            ];
        }

        // 从统计表读取总体数据
        $stats = $this->getCachedStatisticsRows([$periodLabel], QualityReportStatistics::TYPE_OVERALL, [
            'columns' => ['id', 'grade_a_count', 'grade_a_ratio', 'grade_b_count', 'grade_b_ratio', 'grade_c_count', 'grade_c_ratio'],
        ])->first();

        if (!$stats) {
            // 如果统计表没有数据,返回默认值
            return [
                'jjblnum' => '0',
                'jjradio' => '0.00',
                'yjblnum' => '0',
                'yjradio' => '0.00',
                'bjblnum' => '0',
                'bjradio' => '0.00',
            ];
        }

        return [
            'jjblnum' => (string)$stats->grade_a_count,
            'jjradio' => number_format($stats->grade_a_ratio, 2),
            'yjblnum' => (string)$stats->grade_b_count,
            'yjradio' => number_format($stats->grade_b_ratio, 2),
            'bjblnum' => (string)$stats->grade_c_count,
            'bjradio' => number_format($stats->grade_c_ratio, 2),
        ];
    }

    /**
     * 汇总多个月份的病案等级数据（支持科室筛选）
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function aggregateMonthlyLevelStats($startTime, $endTime, $depIds = [])
    {
        // 生成月份列表
        $months = [];
        $current = strtotime($startTime);
        $end = strtotime($endTime);

        while ($current <= $end) {
            $year = date('Y', $current);
            $month = (int)date('m', $current);
            $months[] = $year . '年' . $month . '月';
            $current = strtotime('+1 month', $current);
        }

        // 如果指定了科室，查询科室维度数据
        if (!empty($depIds)) {
            $stats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_DEPARTMENT, [
                'dimension_ids' => $depIds,
                'columns' => ['id', 'total_cases', 'grade_a_count', 'grade_b_count', 'grade_c_count'],
            ]);
        } else {
            // 查询所有月份的数据
            $stats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_OVERALL, [
                'columns' => ['id', 'total_cases', 'grade_a_count', 'grade_b_count', 'grade_c_count'],
            ]);
        }

        if ($stats->isEmpty()) {
            return [
                'jjblnum' => '0',
                'jjradio' => '0.00',
                'yjblnum' => '0',
                'yjradio' => '0.00',
                'bjblnum' => '0',
                'bjradio' => '0.00',
            ];
        }

        // 汇总数据
        $totalCases = $stats->sum('total_cases');
        $gradeACount = $stats->sum('grade_a_count');
        $gradeBCount = $stats->sum('grade_b_count');
        $gradeCCount = $stats->sum('grade_c_count');

        $gradeARatio = $totalCases > 0 ? ($gradeACount / $totalCases) * 100 : 0;
        $gradeBRatio = $totalCases > 0 ? ($gradeBCount / $totalCases) * 100 : 0;
        $gradeCRatio = $totalCases > 0 ? ($gradeCCount / $totalCases) * 100 : 0;

        return [
            'jjblnum' => (string)$gradeACount,
            'jjradio' => number_format($gradeARatio, 2),
            'yjblnum' => (string)$gradeBCount,
            'yjradio' => number_format($gradeBRatio, 2),
            'bjblnum' => (string)$gradeCCount,
            'bjradio' => number_format($gradeCRatio, 2),
        ];
    }

    /**
     * 获取质控趋势分析数据（12个月）
     * @param array $timeRanges 时间范围信息
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getQualityTrendAnalysis($timeRanges, $depIds = [])
    {
        $type = $timeRanges['type'] ?? 'month';
        $year = $timeRanges['year'];
        $startMonth = $timeRanges['startMonth'];
        $endMonth = $timeRanges['endMonth'];

        $months = [];

        if ($type === 'year') {
            // 年度：返回整年12个月（1月-12月）
            for ($m = 1; $m <= 12; $m++) {
                $months[] = $year . '年' . $m . '月';
            }
        } elseif ($type === 'quarter') {
            // 季度：返回当前季度和前3个季度，共12个月
            // 计算当前季度的起始月份
            $currentQuarterStartMonth = $startMonth;

            // 往前推12个月
            $endTimestamp = strtotime("{$year}-{$endMonth}-01");
            $startTimestamp = strtotime('-11 months', $endTimestamp);

            $current = $startTimestamp;
            while ($current <= $endTimestamp) {
                $y = date('Y', $current);
                $m = (int)date('m', $current);
                $months[] = $y . '年' . $m . '月';
                $current = strtotime('+1 month', $current);
            }
        } else {
            // 月份：往前推11个月，共12个月
            $currentTimestamp = strtotime("{$year}-{$startMonth}-01");
            $startTimestamp = strtotime('-11 months', $currentTimestamp);

            $current = $startTimestamp;
            while ($current <= $currentTimestamp) {
                $y = date('Y', $current);
                $m = (int)date('m', $current);
                $months[] = $y . '年' . $m . '月';
                $current = strtotime('+1 month', $current);
            }
        }

        Log::info('[质控趋势分析] 查询月份列表', [
            'type' => $type,
            'months' => $months,
            'dep_ids' => $depIds
        ]);

        // 查询统计数据
        if (!empty($depIds)) {
            // 有科室筛选：查询科室维度数据并汇总
            $stats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_DEPARTMENT, [
                'dimension_ids' => $depIds,
                'columns' => ['id', 'stat_period', 'total_cases', 'defect_cases'],
            ]);

            // 按月份分组汇总
            $monthlyData = [];
            foreach ($stats as $stat) {
                $period = $stat->stat_period;
                if (!isset($monthlyData[$period])) {
                    $monthlyData[$period] = [
                        'total_cases' => 0,
                        'defect_cases' => 0,
                    ];
                }
                $monthlyData[$period]['total_cases'] += $stat->total_cases;
                $monthlyData[$period]['defect_cases'] += $stat->defect_cases;
            }
        } else {
            // 无科室筛选：查询总体数据
            $stats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_OVERALL, [
                'columns' => ['id', 'stat_period', 'total_cases', 'defect_cases'],
            ])->keyBy('stat_period');

            $monthlyData = [];
            foreach ($stats as $period => $stat) {
                $monthlyData[$period] = [
                    'total_cases' => $stat->total_cases,
                    'defect_cases' => $stat->defect_cases,
                ];
            }
        }

        // 构建返回数据（按文档格式：对象包含months、quality_cases、defect_cases数组）
        $monthLabels = [];
        $qualityCases = [];
        $defectCases = [];

        foreach ($months as $month) {
            $data = $monthlyData[$month] ?? ['total_cases' => 0, 'defect_cases' => 0];
            $monthLabels[] = $month;
            $qualityCases[] = (int)$data['total_cases'];      // 质控病历数
            $defectCases[] = (int)$data['defect_cases'];      // 缺陷病历数
        }

        return [
            'months' => $monthLabels,
            'quality_cases' => $qualityCases,
            'defect_cases' => $defectCases,
        ];
    }

    /**
     * 获取上一期总体情况数据（从统计表读取，支持科室筛选）
     * @param string $startTime
     * @param string $endTime
     * @param array $periodData 期间数据
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getLastPeriodStatistics($startTime, $endTime, $periodData, $depIds = [])
    {
        $lastPeriodTime = $this->formatPeriodRangeLabel($startTime, $endTime);
        $isMultiMonth = $this->isMultiMonthRange($startTime, $endTime);

        if ($isMultiMonth) {
            $stats = $this->aggregateMonthlyStats($startTime, $endTime, 'overall', $depIds);

            return [
                'bftime' => $lastPeriodTime,
                'bfblnum' => $stats['blnum'],
                'bfqxblnum' => $stats['qxblnum'],
                'bfqxradio' => $stats['qxradio'],
            ];
        }

        // 解析时间生成上个月时间字符串
        $timestamp = strtotime($startTime);
        $year = date('Y', $timestamp);
        $month = (int)date('m', $timestamp); // 转为整数去掉前导零
        $lastMonthTime = $year . '年' . $month . '月';

        // 如果指定了科室，需要汇总科室数据
        if (!empty($depIds)) {
            $stats = $this->getCachedStatisticsRows([$lastMonthTime], QualityReportStatistics::TYPE_DEPARTMENT, [
                'dimension_ids' => $depIds,
                'columns' => ['id', 'total_cases', 'defect_cases'],
            ]);

            if ($stats->isEmpty()) {
                return [
                    'bftime' => $lastMonthTime,
                    'bfblnum' => '0',
                    'bfqxblnum' => '0',
                    'bfqxradio' => '0.00',
                ];
            }

            // 汇总科室数据
            $totalCases = $stats->sum('total_cases');
            $defectCases = $stats->sum('defect_cases');
            $defectRatio = $totalCases > 0 ? ($defectCases / $totalCases) * 100 : 0;

            return [
                'bftime' => $lastMonthTime,
                'bfblnum' => (string)$totalCases,
                'bfqxblnum' => (string)$defectCases,
                'bfqxradio' => number_format($defectRatio, 2),
            ];
        }

        // 从统计表读取总体数据
        $stats = $this->getCachedStatisticsRows([$lastMonthTime], QualityReportStatistics::TYPE_OVERALL, [
            'columns' => ['id', 'total_cases', 'defect_cases', 'defect_ratio'],
        ])->first();

        if (!$stats) {
            // 如果统计表没有数据,返回默认值
            return [
                'bftime' => $lastMonthTime,
                'bfblnum' => '0',
                'bfqxblnum' => '0',
                'bfqxradio' => '0.00',
            ];
        }

        return [
            'bftime' => $lastMonthTime,
            'bfblnum' => (string)$stats->total_cases,
            'bfqxblnum' => (string)$stats->defect_cases,
            'bfqxradio' => number_format($stats->defect_ratio, 2),
        ];
    }

    /**
     * 获取上一期病案等级数据（从统计表读取，支持科室筛选）
     * @param string $startTime
     * @param string $endTime
     * @param array $baseData 基础数据
     * @param array $periodData 期间数据
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getLastPeriodLevelStatistics($startTime, $endTime, $baseData, $periodData, $depIds = [])
    {
        $isMultiMonth = $this->isMultiMonthRange($startTime, $endTime);

        if ($isMultiMonth) {
            $stats = $this->aggregateMonthlyLevelStats($startTime, $endTime, $depIds);

            return [
                'bfjjblnum' => $stats['jjblnum'] ?? '0',
                'bfjjradio' => $stats['jjradio'] ?? '0.00',
                'bfyjblnum' => $stats['yjblnum'] ?? '0',
                'bfyjradio' => $stats['yjradio'] ?? '0.00',
                'bfbjblnum' => $stats['bjblnum'] ?? '0',
                'bfbjradio' => $stats['bjradio'] ?? '0.00',
            ];
        }

        // 解析时间生成期间标签
        $timestamp = strtotime($startTime);
        $year = date('Y', $timestamp);
        $month = (int)date('m', $timestamp); // 转为整数去掉前导零
        $periodLabel = $year . '年' . $month . '月';

        // 如果指定了科室，需要汇总科室数据
        if (!empty($depIds)) {
            $stats = $this->getCachedStatisticsRows([$periodLabel], QualityReportStatistics::TYPE_DEPARTMENT, [
                'dimension_ids' => $depIds,
                'columns' => ['id', 'total_cases', 'grade_a_count', 'grade_b_count', 'grade_c_count'],
            ]);

            if ($stats->isEmpty()) {
                return [
                    'bfjjblnum' => '0',
                    'bfjjradio' => '0.00',
                    'bfyjblnum' => '0',
                    'bfyjradio' => '0.00',
                    'bfbjblnum' => '0',
                    'bfbjradio' => '0.00',
                ];
            }

            // 汇总科室数据
            $totalCases = $stats->sum('total_cases');
            $gradeACount = $stats->sum('grade_a_count');
            $gradeBCount = $stats->sum('grade_b_count');
            $gradeCCount = $stats->sum('grade_c_count');

            $gradeARatio = $totalCases > 0 ? ($gradeACount / $totalCases) * 100 : 0;
            $gradeBRatio = $totalCases > 0 ? ($gradeBCount / $totalCases) * 100 : 0;
            $gradeCRatio = $totalCases > 0 ? ($gradeCCount / $totalCases) * 100 : 0;

            return [
                'bfjjblnum' => (string)$gradeACount,
                'bfjjradio' => number_format($gradeARatio, 2),
                'bfyjblnum' => (string)$gradeBCount,
                'bfyjradio' => number_format($gradeBRatio, 2),
                'bfbjblnum' => (string)$gradeCCount,
                'bfbjradio' => number_format($gradeCRatio, 2),
            ];
        }

        // 从统计表读取总体数据
        $stats = $this->getCachedStatisticsRows([$periodLabel], QualityReportStatistics::TYPE_OVERALL, [
            'columns' => ['id', 'grade_a_count', 'grade_a_ratio', 'grade_b_count', 'grade_b_ratio', 'grade_c_count', 'grade_c_ratio'],
        ])->first();

        if (!$stats) {
            // 如果统计表没有数据,返回默认值
            return [
                'bfjjblnum' => '0',
                'bfjjradio' => '0.00',
                'bfyjblnum' => '0',
                'bfyjradio' => '0.00',
                'bfbjblnum' => '0',
                'bfbjradio' => '0.00',
            ];
        }

        return [
            'bfjjblnum' => (string)$stats->grade_a_count,
            'bfjjradio' => number_format($stats->grade_a_ratio, 2),
            'bfyjblnum' => (string)$stats->grade_b_count,
            'bfyjradio' => number_format($stats->grade_b_ratio, 2),
            'bfbjblnum' => (string)$stats->grade_c_count,
            'bfbjradio' => number_format($stats->grade_c_ratio, 2),
        ];
    }

    /**
     * 计算同比数据（与上个月对比）
     * @param array $currentStats
     * @param array $lastPeriodStats
     * @param array $currentLevelStats
     * @param array $lastPeriodLevelStats
     * @return array
     */
    private function calculateYearOverYear($currentStats, $lastPeriodStats, $currentLevelStats, $lastPeriodLevelStats)
    {
        // 缺陷占比同比
        $currentQxRadio = (float)$currentStats['qxradio'];
        $lastQxRadio = (float)$lastPeriodStats['bfqxradio'];
        $tbbfb = $this->formatChange($currentQxRadio, $lastQxRadio);

        // 甲级病案占比同比
        $currentJjRadio = isset($currentLevelStats['jjradio']) ? (float)$currentLevelStats['jjradio'] : 0;
        $lastJjRadio = isset($lastPeriodLevelStats['bfjjradio']) ? (float)$lastPeriodLevelStats['bfjjradio'] : 0;
        $jjtbbfb = $this->formatChange($currentJjRadio, $lastJjRadio);

        return [
            'tbbfb' => $tbbfb,
            'jjtbbfb' => $jjtbbfb,
        ];
    }

    /**
    /**
     * 计算环比数据（与去年同月对比）
     * @param array $currentStats
     * @param array $lastYearStats
     * @param array $currentLevelStats
     * @param array $lastYearLevelStats
     * @return array
     */
    private function calculateMonthOverMonth($currentStats, $lastYearStats, $currentLevelStats, $lastYearLevelStats)
    {
        // 缺陷占比环比
        $currentQxRadio = (float)$currentStats['qxradio'];
        $lastYearQxRadio = (float)$lastYearStats['bfqxradio'];
        $hbbfb = $this->formatChange($currentQxRadio, $lastYearQxRadio);

        // 甲级病案占比环比
        $currentJjRadio = (float)$currentLevelStats['jjradio'];
        $lastYearJjRadio = (float)$lastYearLevelStats['bfjjradio'];
        $jjhbbfb = $this->formatChange($currentJjRadio, $lastYearJjRadio);

        return [
            'hbbfb' => $hbbfb,
            'jjhbbfb' => $jjhbbfb,
        ];
    }

    /**
     * 获取指标统计数据
     * @param string $currentStartTime 当前期开始时间
     * @param string $currentEndTime 当前期结束时间
     * @param string $lastStartTime 上期开始时间
     * @param string $lastEndTime 上期结束时间
     * @param string $lastYearStartTime 去年同期开始时间
     * @param string $lastYearEndTime 去年同期结束时间
     * @param bool $isYear 是否为年度统计
     * @return array
     */
    private function getIndicatorStatistics($currentStartTime, $currentEndTime, $lastStartTime, $lastEndTime, $lastYearStartTime, $lastYearEndTime, $isYear = false)
    {
        $logPrefix = '[QualityReport]';
        // 指标映射：index_name => [变量前缀, 是否越低越好（用于bhlfzbl）]
        $indicators = [
            'ryjl24' => ['ryjl', false],      // 入院记录24小时内完成率
            'ssjl24' => ['ssjl', false],      // 手术记录24小时内完成率
            'cyjl24' => ['cyjl', false],       // 出院记录24小时内完成率
            'basy24' => ['basy', false],       // 病案首页24小时内完成率
            'ctmrfhl' => ['ctmr', false],      // CT/MRI检查记录符合率
            'bljcjl' => ['bljc', false],       // 病理检查记录符合率
            'xjpyjcjl' => ['xjpy', false],     // 细菌培养检查记录符合率
            'kjywsy' => ['kjyw', false],        // 抗菌药物使用记录符合率
            'exzlhxzl' => ['hlyw', false],     // 恶性肿瘤化学治疗记录符合率
            'exzlfl' => ['fszl', false],       // 恶性肿瘤放射治疗记录符合率
            'ssxgjl' => ['ssxg', false],       // 手术相关记录完整率
            'zrw' => ['zrw', false],           // 植入物相关记录符合率
            'lcyx' => ['lcyx', false],          // 临床用血相关记录符合率
            'yscf' => ['yscf', false],         // 医师查房记录完整率
            'hzqjjsl' => ['qjjs', false],       // 会诊记录及时完成率（患者抢救记录及时完成率）
            'cdl' => ['lrgd', false],           // 出院患者病历2日归档率
            'gdwzl' => ['drgd', false],         // 出院患者病历归档完整率
            'zyzdzql' => ['zdmc', false],       // 主要诊断填写正确率
            'zyzdbmzql' => ['zdbm', false],    // 主要诊断编码正确率
            'zysszql' => ['ssmc', false],       // 主要手术填写正确率
            'zyssbmzql' => ['ssbm', false],      // 主要手术编码正确率
            'bhlfzbl' => ['bhlfz', true],        // 不合理复制病历发生率（越低越好）
            'zqtys' => ['zqtys', false],        // 知情同意书规范签署率
            'jjbll' => ['jjbll', false],        // 甲级病历率
        ];

        $result = [];
        $totalStart = microtime(true);
        $indicatorCount = count($indicators);

        // 批量查询所有指标的所有时间段数据（优化：从72次查询减少到3次查询）
        $stepStart = microtime(true);
        $allIndicatorData = $this->getBatchIndicatorData($indicators, [
            'current' => ['start' => $currentStartTime, 'end' => $currentEndTime],
            'last' => ['start' => $lastStartTime, 'end' => $lastEndTime],
            'lastYear' => ['start' => $lastYearStartTime, 'end' => $lastYearEndTime],
        ]);
        Log::info("{$logPrefix} [步骤5.7.1] 批量查询指标数据完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

        // 处理每个指标的数据
        foreach ($indicators as $indexName => $config) {
            list($prefix, $lowerIsBetter) = $config;

            // 从批量查询结果中获取数据
            $currentData = $allIndicatorData['current'][$indexName] ?? ['fenzi' => 0, 'fenmu' => 0, 'radio' => 0];
            $currentRadio = $currentData['radio'];

            $lastData = $allIndicatorData['last'][$indexName] ?? ['fenzi' => 0, 'fenmu' => 0, 'radio' => 0];
            $lastRadio = $lastData['radio'];
            $tb = $this->formatChange($currentRadio, $lastRadio, $lowerIsBetter);

            // 年度统计没有环比
            if ($isYear) {
                $hb = '-';
            } else {
                $lastYearData = $allIndicatorData['lastYear'][$indexName] ?? ['fenzi' => 0, 'fenmu' => 0, 'radio' => 0];
                $lastYearRadio = $lastYearData['radio'];
                $hb = $this->formatChange($currentRadio, $lastYearRadio, $lowerIsBetter);
            }

            // 设置变量
            $result[$prefix . 'radio'] = sprintf('%.2f', $currentRadio);
            $result[$prefix . 'fenzi'] = (int)$currentData['fenzi'];
            $result[$prefix . 'fenmu'] = (int)$currentData['fenmu'];
            $result[$prefix . 'lastradio'] = sprintf('%.2f', $lastRadio);
            $result[$prefix . 'tb'] = $tb;
            $result[$prefix . 'hb'] = $hb;
        }

        $totalTime = round((microtime(true) - $totalStart) * 1000, 2);
        Log::info("{$logPrefix} [步骤5.7] 指标统计完成", [
            '总耗时' => $totalTime . 'ms',
            '指标数量' => $indicatorCount,
            '平均耗时' => round($totalTime / $indicatorCount, 2) . 'ms/指标'
        ]);

        return $result;
    }

    /**
     * 批量查询所有指标的所有时间段数据（优化：减少查询次数，使用年份和月份字段）
     * @param array $indicators 指标映射
     * @param array $timeRanges 时间范围 ['current' => [...], 'last' => [...], 'lastYear' => [...]]
     * @return array ['current' => [indexName => data], 'last' => [...], 'lastYear' => [...]]
     */
    private function getBatchIndicatorData($indicators, $timeRanges)
    {
        $result = [
            'current' => [],
            'last' => [],
            'lastYear' => [],
        ];

        // 构建所有指标的字段列表
        $selectFields = [];
        foreach (array_keys($indicators) as $indexName) {
            $selectFields[] = "sum({$indexName}_fz) as {$indexName}_fz";
            $selectFields[] = "sum({$indexName}_fm) as {$indexName}_fm";
        }
        $selectSql = implode(', ', $selectFields);

        // 批量查询三个时间段的数据
        foreach ($timeRanges as $period => $range) {
            try {
                // 解析时间范围，提取年份和月份
                $startTime = $range['start'];
                $endTime = $range['end'];

                // 提取开始和结束的年月
                $startYear = (int)date('Y', strtotime($startTime));
                $startMonth = (int)date('m', strtotime($startTime));
                $endYear = (int)date('Y', strtotime($endTime));
                $endMonth = (int)date('m', strtotime($endTime));

                $query = Indicator::query();

                // 如果跨年，需要分别处理
                if ($startYear == $endYear) {
                    // 同一年
                    if ($startMonth == $endMonth) {
                        // 单个月份，使用精确匹配（最快）
                        $query->where('AAC01_YEAR', $startYear)
                            ->where('AAC01_MONTH', $startMonth);
                    } else {
                        // 跨月份，使用范围查询
                        $query->where('AAC01_YEAR', $startYear)
                            ->whereBetween('AAC01_MONTH', [$startMonth, $endMonth]);
                    }
                } else {
                    // 跨年，使用 whereBetween 作为后备方案
                    $query->whereBetween('AAC01', [$startTime, $endTime]);
                }

                $queryResult = $query->selectRaw($selectSql)->first();

                if ($queryResult) {
                    foreach ($indicators as $indexName => $config) {
                        $fenziField = $indexName . '_fz';
                        $fenmuField = $indexName . '_fm';

                        $fenzi = $queryResult->$fenziField ?? 0;
                        $fenmu = $queryResult->$fenmuField ?? 0;
                        $radio = $fenmu > 0 ? ($fenzi / $fenmu) * 100 : 0;

                        $result[$period][$indexName] = [
                            'fenzi' => round($fenzi, 4),
                            'fenmu' => round($fenmu, 4),
                            'radio' => round($radio, 2),
                        ];
                    }
                } else {
                    // 如果没有数据，初始化所有指标为0
                    foreach ($indicators as $indexName => $config) {
                        $result[$period][$indexName] = [
                            'fenzi' => 0,
                            'fenmu' => 0,
                            'radio' => 0,
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning("[QualityReport] 批量查询指标数据失败", [
                    'period' => $period,
                    'error' => $e->getMessage(),
                ]);
                // 如果查询失败，初始化所有指标为0
                foreach ($indicators as $indexName => $config) {
                    $result[$period][$indexName] = [
                        'fenzi' => 0,
                        'fenmu' => 0,
                        'radio' => 0,
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * 获取单个指标的数据（使用年份和月份字段优化）
     * @param string $indexName 指标名称
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @return array ['fenzi' => 分子, 'fenmu' => 分母, 'radio' => 合格率]
     */
    private function getIndicatorData($indexName, $startTime, $endTime)
    {
        $fenziField = $indexName . '_fz';
        $fenmuField = $indexName . '_fm';

        // 检查字段是否存在（如果表不存在或字段不存在，返回0）
        try {
            // 提取开始和结束的年月
            $startYear = (int)date('Y', strtotime($startTime));
            $startMonth = (int)date('m', strtotime($startTime));
            $endYear = (int)date('Y', strtotime($endTime));
            $endMonth = (int)date('m', strtotime($endTime));

            $query = Indicator::query();

            // 如果跨年，需要分别处理
            if ($startYear == $endYear) {
                // 同一年
                if ($startMonth == $endMonth) {
                    // 单个月份，使用精确匹配（最快）
                    $query->where('AAC01_YEAR', $startYear)
                        ->where('AAC01_MONTH', $startMonth);
                } else {
                    // 跨月份，使用范围查询
                    $query->where('AAC01_YEAR', $startYear)
                        ->whereBetween('AAC01_MONTH', [$startMonth, $endMonth]);
                }
            } else {
                // 跨年，使用 whereBetween 作为后备方案
                $query->whereBetween('AAC01', [$startTime, $endTime]);
            }

            $result = $query->selectRaw("sum({$fenziField}) as fenzi, sum({$fenmuField}) as fenmu")
                ->first();

            $fenzi = $result->fenzi ?? 0;
            $fenmu = $result->fenmu ?? 0;

            // 计算合格率
            $radio = $fenmu > 0 ? ($fenzi / $fenmu) * 100 : 0;

            return [
                'fenzi' => round($fenzi, 4),
                'fenmu' => round($fenmu, 4),
                'radio' => round($radio, 2),
            ];
        } catch (\Exception $e) {
            // 如果查询失败（表不存在或字段不存在），返回默认值
            Log::warning('获取指标数据失败', [
                'index_name' => $indexName,
                'error' => $e->getMessage(),
            ]);
            return [
                'fenzi' => 0,
                'fenmu' => 0,
                'radio' => 0,
            ];
        }
    }

    /**
     * 格式化变化值（支持越低越好的指标）
     * @param float $current
     * @param float $last
     * @param bool $lowerIsBetter 是否越低越好（如bhlfzbl）
     * @return string
     */
    private function formatChange($current, $last, $lowerIsBetter = false)
    {
        if ($last == 0) {
            if ($lowerIsBetter) {
                return $current < 0 ? '↓ ' . sprintf('%.2f', abs($current)) : '0.00';
            }
            return $current > 0 ? '↑ ' . sprintf('%.2f', $current) : '0.00';
        }
        $change = $current - $last;

        // 对于越低越好的指标，变化方向需要反转
        if ($lowerIsBetter) {
            $symbol = $change <= 0 ? '↓' : '↑';  // 下降是好事，上升是坏事
        } else {
            $symbol = $change >= 0 ? '↑' : '↓';  // 上升是好事，下降是坏事
        }

        return $symbol . ' ' . sprintf('%.2f', abs($change));
    }

    /**
     * 将箭头符号转换为正负符号（用于JSON返回）
     * @param string $value 带箭头的值，如 "↑ 1.23" 或 "↓ 0.45"
     * @return string 带正负号的值，如 "+1.23" 或 "-0.45"
     */
    private function convertArrowToSign($value)
    {
        // 如果是 "-" 或空值，直接返回
        if ($value === '-' || empty($value)) {
            return $value;
        }

        // 移除空格
        $value = str_replace(' ', '', $value);

        // 转换箭头为正负号
        if (strpos($value, '↑') !== false) {
            return '+' . str_replace('↑', '', $value);
        } elseif (strpos($value, '↓') !== false) {
            return '-' . str_replace('↓', '', $value);
        }

        // 如果没有箭头，直接返回
        return $value;
    }

    /**
     * 设置指标图表数据
     * @param WordReportService $wordReportService
     * @param string $time 时间参数，格式：2025年12月
     * @param array $statisticsData 统计数据
     * @return void
     */
    private function setIndicatorCharts($wordReportService, $time, $statisticsData, $timeRanges)
    {
        $logPrefix = '[QualityReport]';
        $period1Name = $this->formatPeriodRangeLabel(
            $timeRanges['last']['start'],
            $timeRanges['last']['end']
        ) . '达标率';
        $period2Name = $this->formatPeriodRangeLabel(
            $timeRanges['current']['start'],
            $timeRanges['current']['end']
        ) . '达标率';

        // 1. 时效性指标图表（sxzbtb）
        $stepStart = microtime(true);
        $sxzbtbChartData = $this->buildChartData([
            'ryjl24' => '入院记录24小时内完成率',
            'ssjl24' => '手术记录24小时内完成率',
            'cyjl24' => '出院记录24小时内完成率',
            'basy24' => '病案首页24小时内完成率',
        ], [
            'last' => $timeRanges['last'],
            'current' => $timeRanges['current'],
        ]);
        Log::info("{$logPrefix} [步骤8.1] 时效性指标图表数据构建完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

        if (!empty($sxzbtbChartData)) {
            try {
                $wordReportService->replaceChartByIndicator('sxzbtb', $sxzbtbChartData, [
                    'width' => 5256530,
                    'height' => 2988310,
                    'title' => '',
                    'overlap' => -28,
                    'gapWidth' => 246,
                    'addTrendline' => true,
                    'labelSuffixPercent' => true,
                    'showGridY' => true,
                    'period1Name' => $period1Name,
                    'period2Name' => $period2Name,
                ]);
            } catch (\Exception $e) {
                Log::warning('设置时效性指标图表失败', ['error' => $e->getMessage()]);
            }
        }

        // 2. 重大检查记录符合率图表（zdjctb）
        $stepStart = microtime(true);
        $zdjctbChartData = $this->buildChartData([
            'ctmrfhl' => 'CT/MRI检查记录符合率',
            'bljcjl' => '病理检查记录符合率',
            'xjpyjcjl' => '细菌培养检查记录符合率',
        ], [
            'last' => $timeRanges['last'],
            'current' => $timeRanges['current'],
        ]);
        Log::info("{$logPrefix} [步骤8.2] 重大检查记录符合率图表数据构建完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

        if (!empty($zdjctbChartData)) {
            try {
                $wordReportService->replaceChartByIndicator('zdjctb', $zdjctbChartData, [
                    'width' => 5256530,
                    'height' => 2988310,
                    'title' => '',
                    'overlap' => -28,
                    'gapWidth' => 246,
                    'addTrendline' => true,
                    'labelSuffixPercent' => true,
                    'showGridY' => true,
                    'period1Name' => $period1Name,
                    'period2Name' => $period2Name,
                ]);
            } catch (\Exception $e) {
                Log::warning('设置重大检查记录符合率图表失败', ['error' => $e->getMessage()]);
            }
        }

        // 3. 诊疗行为记录符合率图表（zlxwtb）
        $stepStart = microtime(true);
        $zlxwtbChartData = $this->buildChartData([
            'kjywsy' => '抗菌药物使用记录符合率',
            'exzlhxzl' => '恶性肿瘤化学治疗记录符合率',
            'exzlfl' => '恶性肿瘤放射治疗记录符合率',
            'ssxgjl' => '手术相关记录完整率',
            'zrw' => '植入物相关记录符合率',
            'lcyx' => '临床用血相关记录符合率',
            'yscf' => '医师查房记录完整率',
            'hzqjjsl' => '患者抢救记录及时完成率',
        ], [
            'last' => $timeRanges['last'],
            'current' => $timeRanges['current'],
        ]);
        Log::info("{$logPrefix} [步骤8.3] 诊疗行为记录符合率图表数据构建完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

        if (!empty($zlxwtbChartData)) {
            try {
                $wordReportService->replaceChartByIndicator('zlxwtb', $zlxwtbChartData, [
                    'width' => 5760000,  // 8个指标需要更宽
                    'height' => 2988310,
                    'title' => '',
                    'overlap' => -28,
                    'gapWidth' => 246,
                    'addTrendline' => true,
                    'labelSuffixPercent' => true,
                    'showGridY' => true,
                    'period1Name' => $period1Name,
                    'period2Name' => $period2Name,
                ]);
            } catch (\Exception $e) {
                Log::warning('设置诊疗行为记录符合率图表失败', ['error' => $e->getMessage()]);
            }
        }

        // 4. 病历归档质量指标图表（gdzltb）
        $stepStart = microtime(true);
        $gdzltbChartData = $this->buildChartData([
            'cdl' => '出院患者病历2日归档率',
            'gdwzl' => '出院患者病历归档完整率',
            'zyzdzql' => '主要诊断填写正确率',
            'zyzdbmzql' => '主要诊断编码正确率',
            'zysszql' => '主要手术填写正确率',
            'zyssbmzql' => '主要手术编码正确率',
            'bhlfzbl' => '不合理复制病历发生率',
            'zqtys' => '知情同意书规范签署率',
            'jjbll' => '甲级病历率',
        ], [
            'last' => $timeRanges['last'],
            'current' => $timeRanges['current'],
        ]);
        Log::info("{$logPrefix} [步骤8.4] 病历归档质量指标图表数据构建完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

        if (!empty($gdzltbChartData)) {
            try {
                $wordReportService->replaceChartByIndicator('gdzltb', $gdzltbChartData, [
                    'width' => 5760000,  // 9个指标需要更宽
                    'height' => 2988310,
                    'title' => '',
                    'overlap' => -28,
                    'gapWidth' => 246,
                    'addTrendline' => true,
                    'labelSuffixPercent' => true,
                    'showGridY' => true,
                    'period1Name' => $period1Name,
                    'period2Name' => $period2Name,
                ]);
            } catch (\Exception $e) {
                Log::warning('设置病历归档质量指标图表失败', ['error' => $e->getMessage()]);
            }
        }

        // 5. 环比数据图表（bftb）- 缺陷占比和甲级病案占比的环比
        $stepStart = microtime(true);
        $bftbChartData = [
            '缺陷占比环比' => [
                'period1' => isset($statisticsData['bfqxradio']) ? (float)$statisticsData['bfqxradio'] : 0,
                'period2' => isset($statisticsData['qxradio']) ? (float)$statisticsData['qxradio'] : 0,
            ],
            '甲级病案占比环比' => [
                'period1' => isset($statisticsData['bfjjradio']) ? (float)$statisticsData['bfjjradio'] : 0,
                'period2' => isset($statisticsData['jjradio']) ? (float)$statisticsData['jjradio'] : 0,
            ],
        ];
        Log::info("{$logPrefix} [步骤8.5] 环比数据图表数据构建完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms']);

        if (!empty($bftbChartData)) {
            try {
                $wordReportService->replaceChartByIndicator('bftb', $bftbChartData, [
                    'width' => 5256530,
                    'height' => 2988310,
                    'title' => '',
                    'overlap' => -28,
                    'gapWidth' => 246,
                    'addTrendline' => true,
                    'labelSuffixPercent' => true,
                    'showGridY' => true,
                    'period1Name' => $period1Name,
                    'period2Name' => $period2Name,
                ]);
            } catch (\Exception $e) {
                Log::warning('设置环比数据图表失败', ['error' => $e->getMessage()]);
            }
        }

        // 6. 甲乙丙级病案占比图表（jjtb）
        $stepStart = microtime(true);
        $jjtbChartData = [
            '甲级病案' => [
                'period1' => isset($statisticsData['bfjjradio']) ? (float)$statisticsData['bfjjradio'] : 0,
                'period2' => isset($statisticsData['jjradio']) ? (float)$statisticsData['jjradio'] : 0,
            ],
            '乙级病案' => [
                'period1' => isset($statisticsData['bfyjradio']) ? (float)$statisticsData['bfyjradio'] : 0,
                'period2' => isset($statisticsData['yjradio']) ? (float)$statisticsData['yjradio'] : 0,
            ],
            '丙级病案' => [
                'period1' => isset($statisticsData['bfbjradio']) ? (float)$statisticsData['bfbjradio'] : 0,
                'period2' => isset($statisticsData['bjradio']) ? (float)$statisticsData['bjradio'] : 0,
            ],
        ];
        Log::info("{$logPrefix} [步骤8.6] 甲乙丙级病案占比图表数据构建完成", ['耗时' => round((microtime(true) - $stepStart) * 1000, 2) . 'ms', 'data' => $jjtbChartData]);

        if (!empty($jjtbChartData)) {
            try {
                $wordReportService->replaceChartByIndicator('jjtb', $jjtbChartData, [
                    'width' => 5256530,
                    'height' => 2988310,
                    'title' => '',
                    'overlap' => -28,
                    'gapWidth' => 246,
                    'addTrendline' => true,
                    'labelSuffixPercent' => true,
                    'showGridY' => true,
                    'period1Name' => $period1Name,
                    'period2Name' => $period2Name,
                ]);
                Log::info("{$logPrefix} 成功设置甲乙丙级病案占比图表");
            } catch (\Exception $e) {
                Log::warning('设置甲乙丙级病案占比图表失败', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * 构建图表数据
     * @param array $indicators 指标映射 [index_name => 指标中文名]
     * @param array $periodRanges 时间范围
     * @return array
     */
    private function buildChartData($indicators, array $periodRanges)
    {
        $chartData = [];

        // 批量查询所有指标的数据（优化：从2N次查询减少到2次查询）
        $batchData = $this->getBatchIndicatorData($indicators, $periodRanges);

        foreach ($indicators as $indexName => $indicatorName) {
            // 从批量查询结果中获取数据
            $lastData = $batchData['last'][$indexName] ?? ['radio' => 0];
            $currentData = $batchData['current'][$indexName] ?? ['radio' => 0];

            $chartData[$indicatorName] = [
                'period1' => round($lastData['radio'], 2),
                'period2' => round($currentData['radio'], 2),
            ];
        }

        return $chartData;
    }

    /**
     * 设置规则分布表格数据
     * @param WordReportService $wordReportService
     * @param string $time 时间参数，格式：2025年12月
     * @param array $statisticsData 统计数据
     * @param array $timeRanges 时间范围
     * @param array $baseData 基础数据
     * @param array $periodData 期间数据
     * @param array $depIds 科室ID数组，用于筛选
     * @return void
     */
    private function setRuleDistributionTables($wordReportService, $time, $statisticsData, $timeRanges, $baseData, $periodData, $depIds = [])
    {
        Log::info('[规则分布] 开始设置规则分布表格', ['time' => $time, 'dep_ids' => $depIds]);

        // 获取时效性规则分布数据（从统计表，通用结构）
        $sxStats = $this->getRuleDistributionFromStats($time, '时效性', 20, $depIds);
        Log::info('[规则分布] 时效性规则数据', ['count' => count($sxStats)]);

        // 转换为 Word 模板需要的字段名
        $sxgzRows = [];
        foreach ($sxStats as $row) {
            $sxgzRows[] = [
                'sxgzid'    => $row['rank'] ?? 0,
                'sxgzmc'    => $row['rule_name'] ?? '',
                'sxgzblnum' => (string)($row['quality_cases'] ?? 0),
                'sxgzqxnum' => (string)($row['defect_cases'] ?? 0),
                'sxgzradio' => isset($row['ratio']) ? number_format((float)$row['ratio'], 2) : '0.00',
            ];
        }

        if (empty($sxgzRows)) {
            $sxgzRows = $this->getEmptyRuleDistributionRow('sxgz');
            Log::warning('[规则分布] 时效性规则数据为空，使用空行占位');
        }

        try {
            $wordReportService->replaceTableRows('sxgzid', $sxgzRows);
            Log::info('[规则分布] 成功设置时效性规则表格');
        } catch (\Exception $e) {
            Log::warning('[规则分布] 设置时效性规则分布表格失败', ['error' => $e->getMessage()]);
        }

        // 获取内涵性规则分布数据（从统计表，通用结构）
        $nhStats = $this->getRuleDistributionFromStats($time, '内涵性', 20, $depIds);
        Log::info('[规则分布] 内涵性规则数据', ['count' => count($nhStats)]);

        $nhgzRows = [];
        foreach ($nhStats as $row) {
            $nhgzRows[] = [
                'nhgzid'    => $row['rank'] ?? 0,
                'nhgzmc'    => $row['rule_name'] ?? '',
                'nhgzblnum' => (string)($row['quality_cases'] ?? 0),
                'nhgzqxnum' => (string)($row['defect_cases'] ?? 0),
                'nhgzradio' => isset($row['ratio']) ? number_format((float)$row['ratio'], 2) : '0.00',
            ];
        }

        if (empty($nhgzRows)) {
            $nhgzRows = $this->getEmptyRuleDistributionRow('nhgz');
            Log::warning('[规则分布] 内涵性规则数据为空，使用空行占位');
        }

        try {
            $wordReportService->replaceTableRows('nhgzid', $nhgzRows);
            Log::info('[规则分布] 成功设置内涵性规则表格');
        } catch (\Exception $e) {
            Log::warning('[规则分布] 设置内涵性规则分布表格失败', ['error' => $e->getMessage()]);
        }

        // 获取病案首页规则分布数据（从统计表，通用结构）
        $syStats = $this->getHomeQualityRuleDistributionFromStats($time, 20, $depIds);
        Log::info('[规则分布] 病案首页规则数据', ['count' => count($syStats)]);

        $sygzRows = [];
        foreach ($syStats as $row) {
            $sygzRows[] = [
                'sygzid'    => $row['rank'] ?? 0,
                'sygzmc'    => $row['rule_name'] ?? '',
                'sygzblnum' => (string)($row['quality_cases'] ?? 0),
                'sygzqxnum' => (string)($row['defect_cases'] ?? 0),
                'sygzradio' => isset($row['ratio']) ? number_format((float)$row['ratio'], 2) : '0.00',
            ];
        }

        if (empty($sygzRows)) {
            $sygzRows = $this->getEmptyRuleDistributionRow('sygz');
            Log::warning('[规则分布] 病案首页规则数据为空，使用空行占位');
        }

        try {
            $wordReportService->replaceTableRows('sygzid', $sygzRows);
            Log::info('[规则分布] 成功设置病案首页规则表格');
        } catch (\Exception $e) {
            Log::warning('[规则分布] 设置病案首页规则分布表格失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 从统计表获取规则分布数据（支持动态汇总和科室筛选）
     * @param string $time 时间参数，格式：2025年12月 或 2025年第一季度 或 2025年
     * @param string $ruleType 规则类型：'时效性' 或 '内涵性'
     * @param int $limit 返回条数限制，默认20
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getRuleDistributionFromStats($time, $ruleType, $limit = 20, $depIds = [])
    {
        Log::info('[规则分布] 开始查询', ['time' => $time, 'rule_type' => $ruleType, 'dep_ids' => $depIds]);

        // 解析时间，判断是否需要汇总多个月份
        $months = $this->parseTimeToMonths($time);

        Log::info('[规则分布] 解析月份', ['months' => $months]);

        // 根据是否有科室筛选，选择不同的统计类型
        if (!empty($depIds)) {
            // 有科室筛选：使用 rule_department 类型
            $allRuleStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_RULE_DEPARTMENT, [
                'rule_case_count_gt_zero' => true,
            ])->filter(function ($stat) use ($depIds) {
                $extraData = $this->getStatisticsExtraData($stat);
                $deptId = $extraData['department_id'] ?? null;
                return in_array($deptId, $depIds);
            });
        } else {
            // 无科室筛选：使用 rule 类型
            $allRuleStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_RULE, [
                'rule_case_count_gt_zero' => true,
            ]);
        }

        Log::info('[规则分布] 查询到规则总数', ['count' => $allRuleStats->count()]);

        // 需要从 CaseRule 和 RuleSetting 表获取规则类型信息
        $caseRuleTypes = CaseRule::query()
            ->pluck('type', 'id')
            ->toArray();

        $ruleSettingTypes = RuleSetting::query()
            ->pluck('type', 'id')
            ->toArray();

        // 获取总病历数（汇总所有月份）
        $totalCases = $this->getCachedTotalCases($months, $depIds);

        // 按规则ID汇总数据
        $ruleAggregated = [];
        foreach ($allRuleStats as $stat) {
            // 解析 extra_data，只要病历规则
            $extraData = $this->getStatisticsExtraData($stat);
            $ruleSource = $extraData['rule_type'] ?? $extraData['rule_source'] ?? '';

            if ($ruleSource !== 'case') {
                continue; // 跳过首页规则
            }

            // 对于 rule_department 类型，需要从 extra_data 获取 rule_id
            $ruleId = !empty($depIds) ? ($extraData['rule_id'] ?? $stat->dimension_id) : $stat->dimension_id;

            if (!isset($ruleAggregated[$ruleId])) {
                $ruleName = !empty($depIds) ? ($extraData['rule_name'] ?? $stat->dimension_name) : $stat->dimension_name;
                $ruleAggregated[$ruleId] = [
                    'dimension_id' => $ruleId,
                    'dimension_name' => $ruleName,
                    'rule_case_count' => 0,
                ];
            }

            $ruleAggregated[$ruleId]['rule_case_count'] += $stat->rule_case_count;
        }

        // 转换为表格数据格式
        $rows = [];

        foreach ($ruleAggregated as $ruleId => $stat) {
            // 获取规则类型
            $ruleTypeValue = '';
            if ($ruleId < 1000000) {
                $ruleTypeValue = $caseRuleTypes[$ruleId] ?? '';
            } else {
                $settingId = $ruleId - 1000000;
                $ruleTypeValue = $ruleSettingTypes[$settingId] ?? '';
            }

            // 判断规则类型
            $isTimeliness = ($ruleTypeValue === '时效性');

            // 根据规则类型筛选
            if ($ruleType === '时效性' && !$isTimeliness) {
                continue;
            }
            if ($ruleType === '内涵性' && $isTimeliness) {
                continue;
            }

            // 计算缺陷占比
            $defectCases = $stat['rule_case_count'];
            $defectRatio = $totalCases > 0 ? round(($defectCases / $totalCases) * 100, 2) : 0;

            $rows[] = [
                'dimension_id' => $ruleId,
                'dimension_name' => $stat['dimension_name'],
                'defect_cases' => $defectCases,
                'defect_ratio' => $defectRatio,
            ];
        }

        // 按缺陷数量降序排序
        usort($rows, function ($a, $b) {
            return $b['defect_cases'] - $a['defect_cases'];
        });

        // 限制返回条数并格式化（按文档格式）
        $rows = array_slice($rows, 0, $limit);
        $formattedRows = [];
        $index = 1;

        foreach ($rows as $row) {
            $formattedRows[] = [
                'rank' => $index,
                'rule_id' => (int)$row['dimension_id'],
                'rule_name' => $row['dimension_name'],
                'quality_cases' => (int)$totalCases,
                'defect_cases' => (int)$row['defect_cases'],
                'ratio' => (float)$row['defect_ratio'],
            ];
            $index++;
        }

        Log::info('[规则分布] 筛选后的数据', ['rule_type' => $ruleType, 'count' => count($formattedRows)]);

        return $formattedRows;
    }

    /**
     * 从统计表获取病案首页规则分布数据（支持动态汇总和科室筛选）
     * @param string $time 时间参数，格式：2025年12月 或 2025年第一季度 或 2025年
     * @param int $limit 返回条数限制，默认20
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getHomeQualityRuleDistributionFromStats($time, $limit = 20, $depIds = [])
    {
        Log::info('[规则分布] 开始查询病案首页规则', ['time' => $time, 'dep_ids' => $depIds]);

        // 解析时间，判断是否需要汇总多个月份
        $months = $this->parseTimeToMonths($time);

        // 根据是否有科室筛选，选择不同的统计类型
        if (!empty($depIds)) {
            // 有科室筛选：使用 rule_department 类型
            $allRuleStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_RULE_DEPARTMENT, [
                'rule_case_count_gt_zero' => true,
            ])->filter(function ($stat) use ($depIds) {
                $extraData = $this->getStatisticsExtraData($stat);
                $deptId = $extraData['department_id'] ?? null;
                $ruleType = $extraData['rule_type'] ?? '';
                return in_array($deptId, $depIds) && $ruleType === 'home';
            });
        } else {
            // 无科室筛选：使用 rule 类型
            $allRuleStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_RULE, [
                'rule_case_count_gt_zero' => true,
            ])->filter(function ($stat) {
                $extraData = $this->getStatisticsExtraData($stat);
                return ($extraData['rule_source'] ?? '') === 'home';
            });
        }

        Log::info('[规则分布] 查询到规则总数', ['count' => $allRuleStats->count()]);

        // 获取总病历数（汇总所有月份）
        $totalCases = $this->getCachedTotalCases($months, $depIds);

        // 按规则ID汇总数据
        $ruleAggregated = [];
        foreach ($allRuleStats as $stat) {
            // 解析 extra_data
            $extraData = $this->getStatisticsExtraData($stat);

            // 对于 rule_department 类型，需要从 extra_data 获取 rule_id
            $ruleId = !empty($depIds) ? ($extraData['rule_id'] ?? $stat->dimension_id) : $stat->dimension_id;

            if (!isset($ruleAggregated[$ruleId])) {
                $ruleName = !empty($depIds) ? ($extraData['rule_name'] ?? $stat->dimension_name) : $stat->dimension_name;
                $ruleAggregated[$ruleId] = [
                    'dimension_id' => $ruleId,
                    'dimension_name' => $ruleName,
                    'rule_case_count' => 0,
                ];
            }

            $ruleAggregated[$ruleId]['rule_case_count'] += $stat->rule_case_count;
        }

        // 转换为数组并排序
        $rows = [];
        foreach ($ruleAggregated as $stat) {
            $defectCases = $stat['rule_case_count'];
            $defectRatio = $totalCases > 0 ? round(($defectCases / $totalCases) * 100, 2) : 0;

            $rows[] = [
                'dimension_id' => $stat['dimension_id'],
                'dimension_name' => $stat['dimension_name'],
                'defect_cases' => $defectCases,
                'defect_ratio' => $defectRatio,
            ];
        }

        // 按缺陷数量降序排序
        usort($rows, function ($a, $b) {
            return $b['defect_cases'] - $a['defect_cases'];
        });

        // 限制返回条数并格式化（按文档格式）
        $rows = array_slice($rows, 0, $limit);
        $formattedRows = [];
        $index = 1;

        foreach ($rows as $row) {
            $formattedRows[] = [
                'rank' => $index,
                'rule_id' => (int)$row['dimension_id'],
                'rule_name' => $row['dimension_name'],
                'quality_cases' => (int)$totalCases,
                'defect_cases' => (int)$row['defect_cases'],
                'ratio' => (float)$row['defect_ratio'],
            ];
            $index++;
        }

        Log::info('[规则分布] 病案首页规则数据', ['count' => count($formattedRows)]);

        return $formattedRows;
    }

    /**
     * 获取规则分布数据（使用缓存数据）
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param string $ruleType 规则类型：'时效性' 或 '内涵性'
     * @param array $baseData 基础数据
     * @param array $periodData 期间数据
     * @param int $limit 返回条数限制，默认20
     * @return array
     */
    private function getRuleDistributionData($startTime, $endTime, $ruleType, $baseData, $periodData, $limit = 20)
    {
        // 从缓存数据中获取患者JZHM列表
        $jzhmList = $periodData['current']['jzhmList'];

        if (empty($jzhmList)) {
            return [];
        }

        // 从缓存数据中获取病历缺陷
        $caseQualityList = $periodData['caseQualityList'];

        // 从缓存数据中获取规则信息
        $allRules = [];
        foreach ($baseData['caseRuleMap'] as $id => $rule) {
            $allRules[$id] = [
                'name' => $rule['notice'] ?? '',
                'type' => $rule['type'] ?? '',
            ];
        }
        foreach ($baseData['ruleSettingMap'] as $id => $rule) {
            $allRules[$id + 1000000] = [
                'name' => $rule['description'] ?? '',
                'type' => $rule['type'] ?? '',
            ];
        }

        // 按规则类型筛选并统计
        $ruleStats = [];
        $totalBlNum = count($jzhmList); // 病历总数

        // 遍历所有缺陷，按规则类型筛选并统计
        foreach ($jzhmList as $jzhm) {
            if (!isset($caseQualityList[$jzhm])) {
                continue;
            }

            foreach ($caseQualityList[$jzhm] as $defect) {
                $ruleId = $defect->rule_id;

                // 获取规则信息
                if (!isset($allRules[$ruleId])) {
                    continue; // 规则不存在，跳过
                }

                $rule = $allRules[$ruleId];
                $ruleTypeValue = $rule['type'] ?? '';

                // 判断规则类型：type = '时效性' 的是时效性规则，否则都是内涵性规则
                $isTimeliness = ($ruleTypeValue === '时效性');

                // 如果当前要统计时效性规则，但该规则不是时效性，则跳过
                if ($ruleType === '时效性' && !$isTimeliness) {
                    continue;
                }

                // 如果当前要统计内涵性规则，但该规则是时效性，则跳过
                // 注意：不是时效性的都算内涵性（包括空值、其他值等）
                if ($ruleType === '内涵性' && $isTimeliness) {
                    continue;
                }

                // 初始化规则统计
                if (!isset($ruleStats[$ruleId])) {
                    $ruleStats[$ruleId] = [
                        'rule_id' => $ruleId,
                        'rule_name' => $rule['name'],
                        'jzhm_set' => [], // 使用集合来去重
                    ];
                }

                // 记录该病历有该规则缺陷
                $ruleStats[$ruleId]['jzhm_set'][$jzhm] = true;
            }
        }

        // 转换为表格数据格式
        $rows = [];
        $index = 1;
        foreach ($ruleStats as $ruleId => $stat) {
            $qxBlNum = count($stat['jzhm_set']); // 缺陷病历数
            $radio = $totalBlNum > 0 ? sprintf('%.2f', ($qxBlNum / $totalBlNum) * 100) : '0.00';

            $rows[] = [
                'sxgzid' => $index,  // 时效性和内涵性都用这个字段名，WordReportService会根据占位符自动处理
                'nhgzid' => $index,
                'sxgzmc' => $stat['rule_name'],
                'nhgzmc' => $stat['rule_name'],
                'sxgzblnum' => (string)$totalBlNum,
                'nhgzblnum' => (string)$totalBlNum,
                'sxgzqxnum' => (string)$qxBlNum,
                'nhgzqxnum' => (string)$qxBlNum,
                'sxgzradio' => $radio,
                'nhgzradio' => $radio,
            ];
            $index++;
        }

        // 按缺陷占比降序排序
        usort($rows, function ($a, $b) {
            $radioA = (float)($a['sxgzradio'] ?? $a['nhgzradio']);
            $radioB = (float)($b['sxgzradio'] ?? $b['nhgzradio']);
            return $radioB <=> $radioA; // 降序
        });

        // 限制返回条数
        $rows = array_slice($rows, 0, $limit);

        // 根据规则类型返回对应的字段
        if ($ruleType === '时效性') {
            return array_map(function ($row) {
                return [
                    'sxgzid' => $row['sxgzid'],
                    'sxgzmc' => $row['sxgzmc'],
                    'sxgzblnum' => $row['sxgzblnum'],
                    'sxgzqxnum' => $row['sxgzqxnum'],
                    'sxgzradio' => $row['sxgzradio'],
                ];
            }, $rows);
        } else {
            return array_map(function ($row) {
                return [
                    'nhgzid' => $row['nhgzid'],
                    'nhgzmc' => $row['nhgzmc'],
                    'nhgzblnum' => $row['nhgzblnum'],
                    'nhgzqxnum' => $row['nhgzqxnum'],
                    'nhgzradio' => $row['nhgzradio'],
                ];
            }, $rows);
        }
    }

    /**
     * 获取病案首页规则分布数据（使用缓存数据）
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param array $baseData 基础数据
     * @param array $periodData 期间数据
     * @param int $limit 返回条数限制，默认20
     * @return array
     */
    private function getHomeQualityRuleDistributionData($startTime, $endTime, $baseData, $periodData, $limit = 20)
    {
        // 从缓存数据中获取患者JZHM列表
        $jzhmList = $periodData['current']['jzhmList'];

        if (empty($jzhmList)) {
            return [];
        }

        // 从缓存数据中获取首页缺陷
        $homeQualityList = $periodData['homeQualityList'];

        // 从缓存数据中获取规则信息
        $allRules = [];
        foreach ($baseData['errorRuleMap'] as $id => $rule) {
            $allRules[$id] = [
                'name' => $rule['desc'] ?? '',
            ];
        }
        foreach ($baseData['ruleSettingMap'] as $id => $rule) {
            $allRules[$id + 1000000] = [
                'name' => $rule['description'] ?? '',
            ];
        }

        // 按规则统计
        $ruleStats = [];
        $totalBlNum = count($jzhmList); // 病历总数

        // 遍历所有首页缺陷，按规则统计
        foreach ($jzhmList as $zyh) {
            if (!isset($homeQualityList[$zyh])) {
                continue;
            }

            foreach ($homeQualityList[$zyh] as $item) {
                $errorRuleId = $item->error_rule;

                // 获取规则信息
                if (!isset($allRules[$errorRuleId])) {
                    continue; // 规则不存在，跳过
                }

                // 初始化规则统计
                if (!isset($ruleStats[$errorRuleId])) {
                    $ruleStats[$errorRuleId] = [
                        'rule_id' => $errorRuleId,
                        'rule_name' => $allRules[$errorRuleId]['name'],
                        'zyh_set' => [], // 使用集合来去重
                    ];
                }

                // 记录该病历有该规则缺陷
                $ruleStats[$errorRuleId]['zyh_set'][$zyh] = true;
            }
        }

        // 转换为表格数据格式
        $rows = [];
        $index = 1;
        foreach ($ruleStats as $ruleId => $stat) {
            $qxBlNum = count($stat['zyh_set']); // 缺陷病历数
            $radio = $totalBlNum > 0 ? sprintf('%.2f', ($qxBlNum / $totalBlNum) * 100) : '0.00';

            $rows[] = [
                'sygzid' => $index,
                'sygzmc' => $stat['rule_name'],
                'sygzblnum' => (string)$totalBlNum,
                'sygzqxnum' => (string)$qxBlNum,
                'sygzradio' => $radio,
            ];
            $index++;
        }

        // 按缺陷占比降序排序
        usort($rows, function ($a, $b) {
            $radioA = (float)$a['sygzradio'];
            $radioB = (float)$b['sygzradio'];
            return $radioB <=> $radioA; // 降序
        });

        // 限制返回条数
        return array_slice($rows, 0, $limit);
    }

    /**
     * 设置科室维度统计表格数据
     * @param WordReportService $wordReportService
     * @param string $time
     * @param array $statisticsData
     * @param array $timeRanges 时间范围
     * @param array $baseData 基础数据
     * @param array $periodData 期间数据
     * @param array $depIds 科室ID数组，用于筛选
     */
    private function setDepartmentStatisticsTables(WordReportService $wordReportService, $time, $statisticsData, $timeRanges, $baseData, $periodData, $depIds = [])
    {
        Log::info('[科室统计] 开始设置科室统计表格', ['time' => $time, 'dep_ids' => $depIds]);

        // 从统计表获取科室维度统计数据
        $deptStats = $this->getDepartmentStatisticsFromStats($time, $depIds);

        Log::info('[科室统计] 查询到科室数据', ['count' => count($deptStats)]);

        // 转换为 Word 模板需要的字段名
        $blzlksRows = [];
        foreach ($deptStats as $row) {
            $blzlksRows[] = [
                'blzlksid'      => $row['rank'] ?? 0,
                'blzlksmc'      => $row['dept_name'] ?? '',
                'blzlksbl'      => (string)($row['total_cases'] ?? 0),
                'blzlksjjbl'    => (string)($row['grade_a_count'] ?? 0),
                'blzlksradiojj' => isset($row['grade_a_ratio']) ? number_format((float)$row['grade_a_ratio'], 2) : '0.00',
                'blzlksyjbl'    => (string)($row['grade_b_count'] ?? 0),
                'blzlksradioyj' => isset($row['grade_b_ratio']) ? number_format((float)$row['grade_b_ratio'], 2) : '0.00',
                'blzlksbjbl'    => (string)($row['grade_c_count'] ?? 0),
                'blzlksradiobj' => isset($row['grade_c_ratio']) ? number_format((float)$row['grade_c_ratio'], 2) : '0.00',
            ];
        }

        if (!empty($blzlksRows)) {
            try {
                $wordReportService->replaceTableRows('blzlksid', $blzlksRows);
                Log::info('[科室统计] 成功设置科室统计表格');
            } catch (\Exception $e) {
                Log::warning('[科室统计] 设置科室维度统计表格失败', ['error' => $e->getMessage()]);
            }
        } else {
            Log::warning('[科室统计] 科室数据为空');
        }
    }

    /**
     * 从统计表获取科室维度统计数据（支持动态汇总和科室筛选）
     * @param string $time 时间参数，格式：2025年12月 或 2025年第一季度 或 2025年
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getDepartmentStatisticsFromStats($time, $depIds = [])
    {
        // 解析时间，判断是否需要汇总多个月份
        $months = $this->parseTimeToMonths($time);

        $deptStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_DEPARTMENT, [
            'dimension_ids' => $depIds,
            'columns' => ['id', 'dimension_id', 'dimension_name', 'total_cases', 'grade_a_count', 'grade_b_count', 'grade_c_count'],
        ]);

        Log::info('[科室统计] 查询结果', ['count' => $deptStats->count()]);

        // 按科室ID汇总数据
        $deptAggregated = [];
        foreach ($deptStats as $stat) {
            $deptId = $stat->dimension_id;

            if (!isset($deptAggregated[$deptId])) {
                $deptAggregated[$deptId] = [
                    'dimension_id' => $deptId,
                    'dimension_name' => $stat->dimension_name,
                    'total_cases' => 0,
                    'grade_a_count' => 0,
                    'grade_b_count' => 0,
                    'grade_c_count' => 0,
                ];
            }

            $deptAggregated[$deptId]['total_cases'] += $stat->total_cases;
            $deptAggregated[$deptId]['grade_a_count'] += $stat->grade_a_count;
            $deptAggregated[$deptId]['grade_b_count'] += $stat->grade_b_count;
            $deptAggregated[$deptId]['grade_c_count'] += $stat->grade_c_count;
        }

        // 重新计算比率并转换为表格数据格式
        $rows = [];
        foreach ($deptAggregated as $stat) {
            $totalCases = $stat['total_cases'];
            $gradeARatio = $totalCases > 0 ? ($stat['grade_a_count'] / $totalCases) * 100 : 0;
            $gradeBRatio = $totalCases > 0 ? ($stat['grade_b_count'] / $totalCases) * 100 : 0;
            $gradeCRatio = $totalCases > 0 ? ($stat['grade_c_count'] / $totalCases) * 100 : 0;

            $rows[] = [
                'dimension_id' => $stat['dimension_id'],
                'dimension_name' => $stat['dimension_name'],
                'total_cases' => $totalCases,
                'grade_a_count' => $stat['grade_a_count'],
                'grade_a_ratio' => $gradeARatio,
                'grade_b_count' => $stat['grade_b_count'],
                'grade_b_ratio' => $gradeBRatio,
                'grade_c_count' => $stat['grade_c_count'],
                'grade_c_ratio' => $gradeCRatio,
            ];
        }

        // 按病历总数降序排序
        usort($rows, function ($a, $b) {
            return $b['total_cases'] - $a['total_cases'];
        });

        // 格式化输出（按文档格式）
        $formattedRows = [];
        $index = 1;
        foreach ($rows as $row) {
            $formattedRows[] = [
                'rank' => $index,
                'dep_id' => $row['dimension_id'],
                'dept_name' => $row['dimension_name'],
                'total_cases' => (int)$row['total_cases'],
                'grade_a_count' => (int)$row['grade_a_count'],
                'grade_a_ratio' => (float)$row['grade_a_ratio'],
                'grade_b_count' => (int)$row['grade_b_count'],
                'grade_b_ratio' => (float)$row['grade_b_ratio'],
                'grade_c_count' => (int)$row['grade_c_count'],
                'grade_c_ratio' => (float)$row['grade_c_ratio'],
            ];
            $index++;
        }

        return $formattedRows;
    }

    /**
     * 获取科室维度统计数据
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @return array
     */
    private function getDepartmentStatisticsData($startTime, $endTime)
    {
        // 获取该时间范围内的所有患者，包含科室信息
        $patients = PatientInfo::query()
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->whereNotNull('AAC02C')
            ->where('AAC02C', '!=', '')
            ->get(['MED_REC_ID', 'AAC02C']);

        if ($patients->isEmpty()) {
            return [];
        }

        // 获取所有科室信息
        $departments = Department::query()
            ->pluck('dep_name', 'dep_id')
            ->toArray();

        // 批量获取病历缺陷规则分数
        $caseRuleMap = CaseRule::query()
            ->where('status', 0)
            ->pluck('score', 'id')
            ->toArray();

        // 批量获取自定义规则分数
        $ruleSettingMap = RuleSetting::query()
            ->where('status', 1)
            ->pluck('score', 'id')
            ->toArray();

        // 批量获取首页缺陷规则分数
        $errorRuleMap = ErrorRule::query()
            ->where('status', 0)
            ->pluck('down', 'id')
            ->toArray();

        // 获取所有病历缺陷
        $jzhmList = $patients->pluck('MED_REC_ID')->toArray();
        $caseQualityData = CaseQuality::query()
            ->whereIn('JZHM', $jzhmList)
            ->get(['JZHM', 'rule_id']);

        // 按JZHM分组
        $caseQualityList = [];
        foreach ($caseQualityData as $item) {
            $jzhm = $item->JZHM;
            if (!isset($caseQualityList[$jzhm])) {
                $caseQualityList[$jzhm] = [];
            }
            $caseQualityList[$jzhm][] = $item;
        }

        // 获取所有首页缺陷
        $homeQualityData = HomeQuality::query()
            ->whereIn('ZYH', $jzhmList)
            ->where('is_del', 0)
            ->get(['ZYH', 'error_rule']);

        // 按ZYH分组
        $homeQualityList = [];
        foreach ($homeQualityData as $item) {
            $zyh = $item->ZYH;
            if (!isset($homeQualityList[$zyh])) {
                $homeQualityList[$zyh] = [];
            }
            $homeQualityList[$zyh][] = $item;
        }

        // 按科室分组统计
        $departmentStats = [];
        foreach ($patients as $patient) {
            $depId = $patient->AAC02C;
            $jzhm = $patient->MED_REC_ID;

            // 初始化科室统计
            if (!isset($departmentStats[$depId])) {
                $departmentStats[$depId] = [
                    'dep_id' => $depId,
                    'dep_name' => $departments[$depId] ?? '未知科室',
                    'total' => 0,
                    'jia' => 0,
                    'yi' => 0,
                    'bing' => 0,
                ];
            }

            $departmentStats[$depId]['total']++;

            // 计算该患者的分数
            // 病历部分分数（占比80%）
            $caseScore = 100;
            if (isset($caseQualityList[$jzhm])) {
                foreach ($caseQualityList[$jzhm] as $defect) {
                    $ruleId = $defect->rule_id;
                    $deduction = 0;
                    if ($ruleId > 1000000) {
                        $settingId = $ruleId - 1000000;
                        $deduction = $ruleSettingMap[$settingId] ?? 0;
                    } else {
                        $deduction = $caseRuleMap[$ruleId] ?? 0;
                    }
                    $caseScore -= $deduction;
                }
            }
            $caseScore = max(0, $caseScore); // 确保不低于0
            $caseScore = $caseScore * 0.8;

            // 首页部分分数（占比20%）
            $homeScore = 100;
            if (isset($homeQualityList[$jzhm])) {
                foreach ($homeQualityList[$jzhm] as $defect) {
                    $errorRuleId = $defect->error_rule;
                    $deduction = 0;
                    if ($errorRuleId > 1000000) {
                        $settingId = $errorRuleId - 1000000;
                        $deduction = $ruleSettingMap[$settingId] ?? 0;
                    } else {
                        $deduction = $errorRuleMap[$errorRuleId] ?? 0;
                    }
                    $homeScore -= $deduction;
                }
            }
            $homeScore = max(0, $homeScore); // 确保不低于0
            $homeScore = $homeScore * 0.2;

            // 总分
            $totalScore = $caseScore + $homeScore;

            // 统计等级
            if ($totalScore >= 91) {
                $departmentStats[$depId]['jia']++;
            } elseif ($totalScore >= 75) {
                $departmentStats[$depId]['yi']++;
            } else {
                $departmentStats[$depId]['bing']++;
            }
        }

        // 转换为表格数据格式
        $rows = [];
        $index = 1;
        foreach ($departmentStats as $depId => $stat) {
            $total = $stat['total'];
            $jia = $stat['jia'];
            $yi = $stat['yi'];
            $bing = $stat['bing'];

            $jiaRadio = $total > 0 ? sprintf('%.2f', ($jia / $total) * 100) : '0.00';
            $yiRadio = $total > 0 ? sprintf('%.2f', ($yi / $total) * 100) : '0.00';
            $bingRadio = $total > 0 ? sprintf('%.2f', ($bing / $total) * 100) : '0.00';

            $rows[] = [
                'blzlksid' => $index,
                'blzlksmc' => $stat['dep_name'],
                'blzlksbl' => (string)$total,
                'blzlksjjbl' => (string)$jia,
                'blzlksradiojj' => $jiaRadio,
                'blzlksyjbl' => (string)$yi,
                'blzlksradioyj' => $yiRadio,
                'blzlksbjbl' => (string)$bing,
                'blzlksradiobj' => $bingRadio,
            ];
            $index++;
        }

        // 按病历总数降序排序
        usort($rows, function ($a, $b) {
            $totalA = (int)$a['blzlksbl'];
            $totalB = (int)$b['blzlksbl'];
            return $totalB <=> $totalA; // 降序
        });

        return $rows;
    }

    /**
     * 设置单否项缺陷占比前五表格数据
     * @param WordReportService $wordReportService
     * @param string $time
     * @param array $statisticsData
     * @param array $timeRanges 时间范围
     * @param array $baseData 基础数据
     * @param array $periodData 期间数据
     * @param array $depIds 科室ID数组，用于筛选
     */
    private function setSingleNoItemStatisticsTables(WordReportService $wordReportService, $time, $statisticsData, $timeRanges, $baseData, $periodData, $depIds = [])
    {
        Log::info('[单否项统计] 开始设置单否项统计表格', ['time' => $time, 'dep_ids' => $depIds]);

        // 从统计表获取全部单否项科室数据，然后取占比前五（科室维度）
        $allDeptStats = $this->getAllSingleNoItemDepartmentFromStats($time, $depIds);
        $topDeptStats = array_slice($allDeptStats, 0, 5);

        Log::info('[单否项统计] 科室维度单否项数据', ['total' => count($allDeptStats), 'top5' => count($topDeptStats)]);

        // 转换为 Word 模板需要的字段名（单否项缺陷占比前五）
        $dfxksRows = [];
        foreach ($topDeptStats as $row) {
            $dfxksRows[] = [
                'dfxksid'    => $row['rank'] ?? 0,
                'dfxksmc'    => $row['dept_name'] ?? '',
                'dfxksbl'    => (string)($row['total_cases'] ?? 0),
                'dfxksqx'    => (string)($row['defect_cases'] ?? 0),
                'dfxksdf'    => (string)($row['defect_count'] ?? 0),
                'dfxksradio' => isset($row['ratio']) ? number_format((float)$row['ratio'], 2) : '0.00',
            ];
        }

        if (!empty($dfxksRows)) {
            try {
                $wordReportService->replaceTableRows('dfxksid', $dfxksRows);
                Log::info('[单否项统计] 成功设置单否项表格');
            } catch (\Exception $e) {
                Log::warning('[单否项统计] 设置单否项缺陷占比前五表格失败', ['error' => $e->getMessage()]);
            }

            // 设置单否项缺陷占比图表数据（按科室统计）
            try {
                $dfxkstbChartData = $this->getSingleNoItemChartDataFromStats($time);
                if (!empty($dfxkstbChartData)) {
                    $dfxkstbChartOptions = [
                        'width' => 5256530,
                        'height' => 2988310,
                        'title' => '',
                        'overlap' => -28,
                        'gapWidth' => 246,
                        'addTrendline' => true,
                        'showGridY' => true,
                        'seriesName' => '单否项问题数量',
                    ];

                    $wordReportService->replaceChartSingleSeries('dfxkstb', $dfxkstbChartData, $dfxkstbChartOptions);
                    Log::info('[单否项统计] 成功设置单否项图表');
                }
            } catch (\Exception $e) {
                Log::warning('[单否项统计] 设置单否项缺陷占比图表失败', ['error' => $e->getMessage()]);
            }
        } else {
            Log::warning('[单否项统计] 单否项数据为空');
        }
    }

    /**
     * 从统计表获取单否项缺陷占比前五数据（支持动态汇总和科室筛选）
     * @param string $time 时间参数，格式：2025年12月 或 2025年第一季度 或 2025年
     * @param int $limit 返回条数限制，默认5
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getSingleNoItemStatisticsFromStats($time, $limit = 5, $depIds = [])
    {
        // 解析时间，判断是否需要汇总多个月份
        $months = $this->parseTimeToMonths($time);

        // 根据是否有科室筛选，选择不同的统计类型
        if (!empty($depIds)) {
            // 有科室筛选：使用 rule_department 类型
            $singleNoStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_RULE_DEPARTMENT, [
                'is_single_no' => 1,
                'rule_case_count_gt_zero' => true,
            ])->filter(function ($stat) use ($depIds) {
                $extraData = $this->getStatisticsExtraData($stat);
                $deptId = $extraData['department_id'] ?? null;
                return in_array($deptId, $depIds);
            });
        } else {
            // 无科室筛选：使用 rule 类型
            $singleNoStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_RULE, [
                'is_single_no' => 1,
                'rule_case_count_gt_zero' => true,
            ]);
        }

        Log::info('[单否项统计] 查询结果', ['count' => $singleNoStats->count(), 'dep_ids' => $depIds]);

        // 获取总病历数（汇总所有月份）
        $totalCases = $this->getCachedTotalCases($months);

        // 按规则ID汇总数据
        $ruleAggregated = [];
        foreach ($singleNoStats as $stat) {
            $ruleId = $stat->dimension_id;

            if (!isset($ruleAggregated[$ruleId])) {
                $ruleAggregated[$ruleId] = [
                    'dimension_id' => $ruleId,
                    'dimension_name' => $stat->dimension_name,
                    'rule_case_count' => 0,
                ];
            }

            $ruleAggregated[$ruleId]['rule_case_count'] += $stat->rule_case_count;
        }

        // 转换为数组并排序
        $rows = [];
        foreach ($ruleAggregated as $stat) {
            $defectCases = $stat['rule_case_count'];
            $defectRatio = $totalCases > 0 ? round(($defectCases / $totalCases) * 100, 2) : 0;

            $rows[] = [
                'dimension_id' => $stat['dimension_id'],
                'dimension_name' => $stat['dimension_name'],
                'defect_cases' => $defectCases,
                'defect_ratio' => $defectRatio,
            ];
        }

        // 按缺陷数量降序排序
        usort($rows, function ($a, $b) {
            return $b['defect_cases'] - $a['defect_cases'];
        });

        // 限制返回条数并格式化
        $rows = array_slice($rows, 0, $limit);
        $formattedRows = [];
        $index = 1;

        foreach ($rows as $row) {
            $formattedRows[] = [
                'dfxksid' => $index,
                'dfxksmc' => $row['dimension_name'],
                'dfxksbl' => (string)$totalCases,
                'dfxksqx' => (string)$row['defect_cases'],
                'dfxksdf' => (string)$row['defect_cases'], // 单否项缺陷数等于总缺陷数
                'dfxksradio' => number_format($row['defect_ratio'], 2),
            ];
            $index++;
        }

        return $formattedRows;
    }

    /**
     * 从统计表获取单否项缺陷占比图表数据（按科室统计，支持动态汇总）
     * @param string $time 时间参数，格式：2025年12月 或 2025年第一季度 或 2025年
     * @param int $limit 返回条数限制，默认5
     * @param array $depIds 科室ID数组，用于筛选科室
     * @return array 格式：['科室名称' => 单否项缺陷数量]
     */
    private function getSingleNoItemChartDataFromStats($time, $limit = 5, $depIds = [])
    {
        // 解析时间，判断是否需要汇总多个月份
        $months = $this->parseTimeToMonths($time);

        // 从统计表查询单否项按科室分布数据
        $deptStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_RULE_DEPARTMENT, [
            'is_single_no' => 1,
        ]);

        // 按科室汇总单否项缺陷数量
        $departmentData = [];
        foreach ($deptStats as $stat) {
            $extraData = $this->getStatisticsExtraData($stat);
            $deptId = $extraData['department_id'] ?? '';
            $deptName = $extraData['department_name'] ?? '';

            if (empty($deptName)) {
                continue;
            }

            // 如果指定了科室筛选，只统计指定科室
            if (!empty($depIds) && !in_array($deptId, $depIds)) {
                continue;
            }

            if (!isset($departmentData[$deptName])) {
                $departmentData[$deptName] = 0;
            }

            $departmentData[$deptName] += $stat->rule_defect_count;
        }

        // 按缺陷数量降序排序
        arsort($departmentData);

        // 限制返回条数
        return array_slice($departmentData, 0, $limit, true);
    }

    /**
     * 获取单否项缺陷占比前五数据（使用缓存数据）
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param array $baseData 基础数据
     * @param array $periodData 期间数据
     * @param int $limit 返回条数限制，默认5
     * @return array
     */
    private function getSingleNoItemStatisticsData($startTime, $endTime, $baseData, $periodData, $limit = 5)
    {
        // 从缓存数据中获取患者JZHM列表
        $jzhmList = $periodData['current']['jzhmList'];

        if (empty($jzhmList)) {
            return [];
        }

        $totalBlNum = count($jzhmList); // 病历总数

        // 从缓存数据中获取病历缺陷
        $caseQualityList = $periodData['caseQualityList'];

        // 从缓存数据中获取单否规则ID
        $allSingleNoRuleIds = $baseData['allSingleNoRuleIds'];

        // 从缓存数据中获取规则信息
        $allRules = [];
        foreach ($baseData['caseRuleMap'] as $id => $rule) {
            $allRules[$id] = [
                'name' => $rule['notice'] ?? '',
            ];
        }
        foreach ($baseData['ruleSettingMap'] as $id => $rule) {
            $allRules[$id + 1000000] = $rule['description'] ?? '';
        }

        // 按规则统计单否项缺陷
        $ruleStats = [];
        // 遍历所有缺陷，按规则类型筛选并统计
        foreach ($jzhmList as $jzhm) {
            if (!isset($caseQualityList[$jzhm])) {
                continue;
            }

            foreach ($caseQualityList[$jzhm] as $defect) {
                $ruleId = $defect->rule_id;

                // 判断是否是单否项规则
                if (!isset($allSingleNoRuleIds[$ruleId])) {
                    continue; // 不是单否项规则，跳过
                }

                // 获取规则信息
                if (!isset($allRules[$ruleId])) {
                    continue; // 规则不存在，跳过
                }

                // 初始化规则统计
                if (!isset($ruleStats[$ruleId])) {
                    $ruleStats[$ruleId] = [
                        'rule_id' => $ruleId,
                        'rule_name' => is_array($allRules[$ruleId]) ? ($allRules[$ruleId]['name'] ?? '') : $allRules[$ruleId],
                        'jzhm_set' => [], // 使用集合来去重（总缺陷病历数）
                        'single_no_jzhm_set' => [], // 单否项缺陷病历数（因为已经是单否规则，所以和总缺陷数相同）
                    ];
                }

                // 记录该病历有该规则缺陷
                $ruleStats[$ruleId]['jzhm_set'][$jzhm] = true;
                $ruleStats[$ruleId]['single_no_jzhm_set'][$jzhm] = true;
            }
        }

        // 转换为表格数据格式
        $rows = [];
        $index = 1;
        foreach ($ruleStats as $ruleId => $stat) {
            $qxBlNum = count($stat['jzhm_set']); // 总缺陷病历数
            $singleNoBlNum = count($stat['single_no_jzhm_set']); // 单否项缺陷病历数（对于单否规则，等于总缺陷数）
            $radio = $totalBlNum > 0 ? sprintf('%.2f', ($singleNoBlNum / $totalBlNum) * 100) : '0.00';

            $rows[] = [
                'dfxksid' => $index,
                'dfxksmc' => $stat['rule_name'],
                'dfxksbl' => (string)$totalBlNum,
                'dfxksqx' => (string)$qxBlNum,
                'dfxksdf' => (string)$singleNoBlNum,
                'dfxksradio' => $radio,
            ];
            $index++;
        }

        // 按单否项缺陷占比降序排序
        usort($rows, function ($a, $b) {
            $radioA = (float)$a['dfxksradio'];
            $radioB = (float)$b['dfxksradio'];
            return $radioB <=> $radioA; // 降序
        });

        // 限制返回条数
        return array_slice($rows, 0, $limit);
    }

    /**
     * 获取单否项缺陷占比图表数据（按科室统计，使用缓存数据）
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param array $baseData 基础数据
     * @param array $periodData 期间数据
     * @param int $limit 返回条数限制，默认5
     * @return array 格式：['科室名称' => 单否项缺陷数量]
     */
    private function getSingleNoItemChartDataByDepartment($startTime, $endTime, $baseData, $periodData, $limit = 5)
    {
        // 从缓存数据中获取患者JZHM列表
        $jzhmList = $periodData['current']['jzhmList'];

        if (empty($jzhmList)) {
            return [];
        }

        // 从缓存数据中获取病历缺陷
        $caseQualityList = $periodData['caseQualityList'];

        // 从缓存数据中获取单否规则ID
        $allSingleNoRuleIds = $baseData['allSingleNoRuleIds'];

        // 从缓存数据中获取科室信息
        $departments = $baseData['departments'];

        // 获取患者到科室的映射（从periodData中获取）
        $patientToDepartment = [];
        foreach ($periodData['current']['patients'] as $patient) {
            if (!empty($patient->AAC02C)) {
                $patientToDepartment[$patient->MED_REC_ID] = $patient->AAC02C;
            }
        }

        // 建立患者到科室的映射
        $patientToDepartment = [];
        foreach ($periodData['current']['patients'] as $patient) {
            if (!empty($patient->AAC02C)) {
                $patientToDepartment[$patient->MED_REC_ID] = $patient->AAC02C;
            }
        }

        // 按科室统计单否项缺陷数量
        $departmentStats = [];
        // 遍历所有缺陷，按规则类型筛选并统计
        foreach ($jzhmList as $jzhm) {
            if (!isset($caseQualityList[$jzhm])) {
                continue;
            }

            foreach ($caseQualityList[$jzhm] as $defect) {
                $ruleId = $defect->rule_id;

                // 判断是否是单否项规则
                if (!isset($allSingleNoRuleIds[$ruleId])) {
                    continue; // 不是单否项规则，跳过
                }

                // 获取患者所属科室
                if (!isset($patientToDepartment[$jzhm])) {
                    continue; // 患者没有科室信息，跳过
                }

                $depId = $patientToDepartment[$jzhm];
                $depName = $departments[$depId] ?? '未知科室';

                // 初始化科室统计
                if (!isset($departmentStats[$depId])) {
                    $departmentStats[$depId] = [
                        'dep_id' => $depId,
                        'dep_name' => $depName,
                        'count' => 0,
                    ];
                }

                // 统计单否项缺陷数量（每个缺陷记录算一次）
                $departmentStats[$depId]['count']++;
            }
        }

        // 转换为图表数据格式
        $chartData = [];
        foreach ($departmentStats as $depId => $stat) {
            $chartData[$stat['dep_name']] = $stat['count'];
        }

        // 按单否项缺陷数量降序排序
        arsort($chartData);

        // 限制返回条数
        return array_slice($chartData, 0, $limit, true); // 保留键名
    }

    /**
     * 设置全部单否项科室问题表格数据
     * @param WordReportService $wordReportService
     * @param string $time
     * @param array $statisticsData
     * @param array $timeRanges 时间范围
     * @param array $baseData 基础数据
     * @param array $periodData 期间数据
     * @param array $depIds 科室ID数组，用于筛选
     */
    private function setAllSingleNoItemDepartmentTables(WordReportService $wordReportService, $time, $statisticsData, $timeRanges, $baseData, $periodData, $depIds = [])
    {
        $logPrefix = '[QualityReport]';

        // 从统计表获取全部单否项科室问题数据
        try {
            $deptStats = $this->getAllSingleNoItemDepartmentFromStats($time, $depIds);

            // 转换为 Word 模板需要的字段名
            $sydfxksRows = [];
            foreach ($deptStats as $row) {
                $sydfxksRows[] = [
                    'sydfxksid'    => $row['rank'] ?? 0,
                    'sydfxksmc'    => $row['dept_name'] ?? '',
                    'sydfxksbl'    => (string)($row['total_cases'] ?? 0),
                    'sydfxksqx'    => (string)($row['defect_cases'] ?? 0),
                    'sydfxksdf'    => (string)($row['defect_count'] ?? 0),
                    'sydfxksradio' => isset($row['ratio']) ? number_format((float)$row['ratio'], 2) : '0.00',
                ];
            }

            if (!empty($sydfxksRows)) {
                $wordReportService->replaceTableRows('sydfxksid', $sydfxksRows);
            }
        } catch (\Exception $e) {
            Log::error("{$logPrefix} [步骤12] 设置全部单否项科室问题表格失败", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 获取全部单否项科室问题数据（使用缓存数据）
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param array $baseData 基础数据
     * @param array $periodData 期间数据
     * @return array
     */
    private function getAllSingleNoItemDepartmentData($startTime, $endTime, $baseData, $periodData)
    {
        // 从缓存数据中获取患者列表
        $patients = $periodData['current']['patients']->filter(function ($patient) {
            return !empty($patient->AAC02C);
        });

        if ($patients->isEmpty()) {
            return [];
        }

        // 从缓存数据中获取科室信息
        $departments = $baseData['departments'];

        // 从缓存数据中获取病历缺陷
        $caseQualityList = $periodData['caseQualityList'];

        // 从缓存数据中获取单否规则ID
        $allSingleNoRuleIds = $baseData['allSingleNoRuleIds'];

        // 建立患者到科室的映射
        $patientToDepartment = [];
        foreach ($patients as $patient) {
            $depId = $patient->AAC02C;
            if (!isset($patientToDepartment[$depId])) {
                $patientToDepartment[$depId] = [];
            }
            $patientToDepartment[$depId][] = $patient->MED_REC_ID;
        }

        // 按科室统计
        $departmentStats = [];
        foreach ($patientToDepartment as $depId => $jzhmList) {
            $depName = $departments[$depId] ?? '未知科室';
            $totalBlNum = count($jzhmList); // 科室病历总数

            // 统计该科室的所有缺陷和单否项缺陷
            $allDefectJzhmSet = []; // 所有缺陷病历（去重）
            $singleNoDefectJzhmSet = []; // 单否项缺陷病历（去重）

            foreach ($jzhmList as $jzhm) {
                // 从缓存数据中查找该患者的所有缺陷
                if (isset($caseQualityList[$jzhm])) {
                    $hasDefect = true;
                    $allDefectJzhmSet[$jzhm] = true;

                    // 判断是否有单否项规则缺陷
                    foreach ($caseQualityList[$jzhm] as $defect) {
                        // 判断是否是单否项规则
                        if (isset($allSingleNoRuleIds[$defect->rule_id])) {
                            $singleNoDefectJzhmSet[$jzhm] = true;
                            break; // 找到一个单否项缺陷就够了
                        }
                    }
                }
            }

            $qxBlNum = count($allDefectJzhmSet); // 缺陷病历数
            $singleNoBlNum = count($singleNoDefectJzhmSet); // 单否项缺陷病历数
            $radio = $totalBlNum > 0 ? sprintf('%.2f', ($singleNoBlNum / $totalBlNum) * 100) : '0.00';

            $departmentStats[$depId] = [
                'dep_id' => $depId,
                'dep_name' => $depName,
                'total_bl' => $totalBlNum,
                'qx_bl' => $qxBlNum,
                'single_no_bl' => $singleNoBlNum,
                'radio' => $radio,
            ];
        }

        // 转换为表格数据格式
        $rows = [];
        $index = 1;
        foreach ($departmentStats as $depId => $stat) {
            $rows[] = [
                'sydfxksid' => $index,
                'sydfxksmc' => $stat['dep_name'],
                'sydfxksbl' => (string)$stat['total_bl'],
                'sydfxksqx' => (string)$stat['qx_bl'],
                'sydfxksdf' => (string)$stat['single_no_bl'],
                'sydfxksradio' => $stat['radio'],
            ];
            $index++;
        }

        // 按单否项缺陷占比降序排序
        usort($rows, function ($a, $b) {
            $radioA = (float)$a['sydfxksradio'];
            $radioB = (float)$b['sydfxksradio'];
            return $radioB <=> $radioA; // 降序
        });

        return $rows;
    }

    /**
     * 设置医师申诉情况表格数据
     * @param WordReportService $wordReportService
     * @param string $time
     * @param array $statisticsData
     * @param array $timeRanges 时间范围
     * @param array $baseData 基础数据
     * @param array $periodData 期间数据
     * @param array $depIds 科室ID数组，用于筛选
     */
    private function setAppealStatisticsTables(WordReportService $wordReportService, $time, $statisticsData, $timeRanges, $baseData, $periodData, $depIds = [])
    {
        Log::info('[申诉表格] 开始设置申诉统计表格', ['time' => $time, 'dep_ids' => $depIds]);

        // 从统计表获取申诉前五数据（按规则统计）
        $appealTopStats = $this->getAppealTopFiveFromStats($time, 5, $depIds);
        Log::info('[申诉表格] 申诉前五数据', ['count' => count($appealTopStats), 'data' => $appealTopStats]);

        // 转换为 Word 模板需要的字段名
        $ssRows = [];
        foreach ($appealTopStats as $row) {
            $ssRows[] = [
                'ssid'    => $row['rank'] ?? 0,
                'ssmc'    => $row['issue'] ?? '',
                'sswtnum' => (string)($row['appeal_count'] ?? 0),
                'bhwtnum' => (string)($row['rejected_count'] ?? 0),
                'sscgl'   => isset($row['success_rate']) ? number_format((float)$row['success_rate'], 2) : '0.00',
            ];
        }

        if (!empty($ssRows)) {
            try {
                $wordReportService->replaceTableRows('ssid', $ssRows);
                Log::info('[申诉表格] 成功设置申诉前五表格');
            } catch (\Exception $e) {
                Log::warning('[申诉表格] 设置医师申诉情况前五表格失败', ['error' => $e->getMessage()]);
            }
        } else {
            Log::warning('[申诉表格] 申诉前五数据为空');
        }

        // 从统计表获取所有申诉情况数据（按科室统计）
        $appealDeptStats = $this->getAllAppealByDepartmentFromStats($time, $depIds);
        Log::info('[申诉表格] 所有科室申诉数据', ['count' => count($appealDeptStats), 'data' => $appealDeptStats]);

        // 转换为 Word 模板需要的字段名
        $syssRows = [];
        foreach ($appealDeptStats as $row) {
            $syssRows[] = [
                'syssid'    => $row['rank'] ?? 0,
                'syssmc'    => $row['dept_name'] ?? '',
                'syssys'    => (string)($row['appeal_doctors'] ?? 0),
                'sywtnum'   => (string)($row['appeal_issues'] ?? 0),
                'sytgwtnum' => (string)($row['passed_count'] ?? 0),
                'sybhwtnum' => (string)($row['rejected_count'] ?? 0),
                'sysscgl'   => isset($row['success_rate']) ? number_format((float)$row['success_rate'], 2) : '0.00',
            ];
        }

        if (!empty($syssRows)) {
            try {
                $wordReportService->replaceTableRows('syssid', $syssRows);
                Log::info('[申诉表格] 成功设置所有申诉情况表格');
            } catch (\Exception $e) {
                Log::warning('[申诉表格] 设置所有申诉情况表格失败', ['error' => $e->getMessage()]);
            }
        } else {
            Log::warning('[申诉表格] 所有科室申诉数据为空');
        }
    }

    /**
     * 从统计表获取医师申诉情况前五数据（按规则分组，支持动态汇总）
     * @param string $time 时间参数，格式：2025年12月 或 2025年第一季度 或 2025年
     * @param int $limit 返回条数限制，默认5
     * @param array $depIds 科室ID数组，用于筛选科室
     * @return array
     */
    private function getAppealTopFiveFromStats($time, $limit = 5, $depIds = [])
    {
        Log::info('[申诉前五] 开始查询', ['time' => $time, 'stat_type' => 'appeal_rule', 'dep_ids' => $depIds]);

        // 解析时间，判断是否需要汇总多个月份
        $months = $this->parseTimeToMonths($time);
        // 如果有科室筛选，需要从 appeal_department 关联查询
        if (!empty($depIds)) {
            // 先查询指定科室的申诉数据
            $deptAppealStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_APPEAL_DEPARTMENT, [
                'dimension_ids' => $depIds,
                'columns' => ['id', 'extra_data'],
            ]);
            // 从 extra_data 中提取规则统计（需要按规则汇总）
            $ruleAggregated = [];
            foreach ($deptAppealStats as $stat) {
                $extraData = $this->getStatisticsExtraData($stat);
                $ruleStats = $extraData['rule_stats'] ?? [];

                foreach ($ruleStats as $ruleId => $ruleStat) {
                    if (!isset($ruleAggregated[$ruleId])) {
                        $ruleAggregated[$ruleId] = [
                            'dimension_id' => $ruleId,
                            'dimension_name' => $ruleStat['rule_name'] ?? '',
                            'appeal_total' => 0,
                            'appeal_rejected' => 0,
                        ];
                    }

                    $ruleAggregated[$ruleId]['appeal_total'] += $ruleStat['appeal_total'] ?? 0;
                    $ruleAggregated[$ruleId]['appeal_rejected'] += $ruleStat['appeal_rejected'] ?? 0;
                }
            }
        } else {
            // 无科室筛选，直接查询 appeal_rule 类型
            $appealStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_APPEAL_RULE, [
                'columns' => ['id', 'dimension_id', 'dimension_name', 'appeal_total', 'appeal_rejected'],
            ]);
            Log::info('[申诉前五] 查询结果', [
                'count' => $appealStats->count(),
            ]);

            // 按规则ID汇总数据
            $ruleAggregated = [];
            foreach ($appealStats as $stat) {
                $ruleId = $stat->dimension_id;

                if (!isset($ruleAggregated[$ruleId])) {
                    $ruleAggregated[$ruleId] = [
                        'dimension_id' => $ruleId,
                        'dimension_name' => $stat->dimension_name,
                        'appeal_total' => 0,
                        'appeal_rejected' => 0,
                    ];
                }

                $ruleAggregated[$ruleId]['appeal_total'] += $stat->appeal_total;
                $ruleAggregated[$ruleId]['appeal_rejected'] += $stat->appeal_rejected;
            }
        }

        // 重新计算成功率并转换为数组
        $rows = [];
        foreach ($ruleAggregated as $stat) {
            $total = $stat['appeal_total'];
            $rejected = $stat['appeal_rejected'];
            $successRatio = $total > 0 ? (($total - $rejected) / $total) * 100 : 0;

            $rows[] = [
                'dimension_id' => $stat['dimension_id'],
                'dimension_name' => $stat['dimension_name'],
                'appeal_total' => $total,
                'appeal_rejected' => $rejected,
                'appeal_success_ratio' => $successRatio,
            ];
        }

        // 按申诉总数降序排序
        usort($rows, function ($a, $b) {
            return $b['appeal_total'] - $a['appeal_total'];
        });

        // 限制返回条数并格式化
        $rows = array_slice($rows, 0, $limit);
        $formattedRows = [];
        $index = 1;

        foreach ($rows as $row) {
            $formattedRows[] = [
                'rank' => $index,
                'issue' => $row['dimension_name'],
                'appeal_count' => (int)$row['appeal_total'],
                'rejected_count' => (int)$row['appeal_rejected'],
                'success_rate' => (float)number_format($row['appeal_success_ratio'], 2),
            ];
            $index++;
        }

        return $formattedRows;
    }

    /**
     * 从统计表获取所有申诉情况数据（按科室统计，支持动态汇总）
     * @param string $time 时间参数，格式：2025年12月 或 2025年第一季度 或 2025年
     * @param array $depIds 科室ID数组，用于筛选科室
     * @return array
     */
    private function getAllAppealByDepartmentFromStats($time, $depIds = [])
    {
        Log::info('[科室申诉] 开始查询', ['time' => $time, 'stat_type' => 'appeal_department', 'dep_ids' => $depIds]);

        // 解析时间，判断是否需要汇总多个月份
        $months = $this->parseTimeToMonths($time);

        // 从统计表查询申诉科室数据
        $appealStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_APPEAL_DEPARTMENT, [
            'dimension_ids' => $depIds,
            'columns' => ['id', 'dimension_id', 'dimension_name', 'appeal_total', 'appeal_approved', 'appeal_rejected', 'extra_data'],
        ]);

        Log::info('[科室申诉] 查询结果', [
            'count' => $appealStats->count(),
        ]);

        // 按科室ID汇总数据
        $deptAggregated = [];
        foreach ($appealStats as $stat) {
            $deptId = $stat->dimension_id;

            if (!isset($deptAggregated[$deptId])) {
                $extraData = $this->getStatisticsExtraData($stat);
                $deptAggregated[$deptId] = [
                    'dimension_id' => $deptId,
                    'dimension_name' => $stat->dimension_name,
                    'appeal_total' => 0,
                    'appeal_approved' => 0,
                    'appeal_rejected' => 0,
                    'doctor_count' => $extraData['doctor_count'] ?? 0, // 医师数量取最后一个月的
                ];
            }

            $deptAggregated[$deptId]['appeal_total'] += $stat->appeal_total;
            $deptAggregated[$deptId]['appeal_approved'] += $stat->appeal_approved;
            $deptAggregated[$deptId]['appeal_rejected'] += $stat->appeal_rejected;
        }

        // 重新计算成功率并转换为数组
        $rows = [];
        foreach ($deptAggregated as $stat) {
            $total = $stat['appeal_total'];
            $rejected = $stat['appeal_rejected'];
            $successRatio = $total > 0 ? (($total - $rejected) / $total) * 100 : 0;

            $rows[] = [
                'dimension_id' => $stat['dimension_id'],
                'dimension_name' => $stat['dimension_name'],
                'appeal_total' => $total,
                'appeal_approved' => $stat['appeal_approved'],
                'appeal_rejected' => $rejected,
                'appeal_success_ratio' => $successRatio,
                'doctor_count' => $stat['doctor_count'],
            ];
        }

        // 按申诉总数降序排序
        usort($rows, function ($a, $b) {
            return $b['appeal_total'] - $a['appeal_total'];
        });

        // 格式化输出
        $formattedRows = [];
        $index = 1;
        foreach ($rows as $row) {
            Log::info('[科室申诉] 处理数据', [
                'index' => $index,
                'dimension_name' => $row['dimension_name'],
                'appeal_total' => $row['appeal_total'],
                'appeal_approved' => $row['appeal_approved'],
                'appeal_rejected' => $row['appeal_rejected'],
                'doctor_count' => $row['doctor_count']
            ]);

            $formattedRows[] = [
                'rank' => $index,
                'dep_id' => $row['dimension_id'],
                'dept_name' => $row['dimension_name'],
                'appeal_doctors' => (int)$row['doctor_count'],
                'appeal_issues' => (int)$row['appeal_total'],
                'passed_count' => (int)$row['appeal_approved'],
                'rejected_count' => (int)$row['appeal_rejected'],
                'success_rate' => (float)number_format($row['appeal_success_ratio'], 2),
            ];
            $index++;
        }

        return $formattedRows;
    }

    /**
     * 获取医师申诉情况前五数据（按规则分组）
     * @param array $jzhmList 患者JZHM列表
     * @param int $limit 返回条数限制，默认5
     * @return array
     */
    private function getAppealTopFiveData($jzhmList, $limit = 5)
    {
        if (empty($jzhmList)) {
            Log::info('[申诉统计] jzhmList为空');
            return [];
        }

        Log::info('[申诉统计] 开始查询申诉数据', ['jzhm_count' => count($jzhmList)]);

        // 查询该时间范围内的申诉记录（quality_type=2表示病历质控）
        $appealList = Appeal::query()
            ->where('quality_type', 2)
            ->whereIn('ZYH', $jzhmList)
            ->get(['error_id', 'status']);

        Log::info('[申诉统计] 查询到申诉记录', ['count' => $appealList->count()]);

        // 获取所有规则ID
        $ruleIds = $appealList->pluck('error_id')->unique()->toArray();
        Log::info('[申诉统计] 规则ID列表', ['rule_ids' => $ruleIds]);

        // 获取标准规则信息
        $standardRuleIds = array_filter($ruleIds, function ($id) {
            return $id < 1000000;
        });
        $standardRules = [];
        if (!empty($standardRuleIds)) {
            $standardRulesData = CaseRule::query()
                ->whereIn('id', $standardRuleIds)
                ->where('status', 0)
                ->get(['id', 'notice']);

            Log::info('[申诉统计] 查询到标准规则', ['count' => $standardRulesData->count()]);

            foreach ($standardRulesData as $rule) {
                $standardRules[$rule->id] = $rule->notice ?? '';
                Log::info('[申诉统计] 标准规则', ['id' => $rule->id, 'notice' => $rule->notice]);
            }
        }

        // 获取自定义规则信息
        $customRuleIds = array_filter($ruleIds, function ($id) {
            return $id >= 1000000;
        });
        $customRuleIds = array_map(function ($id) {
            return $id - 1000000;
        }, $customRuleIds);
        $customRules = [];
        if (!empty($customRuleIds)) {
            $customRulesData = RuleSetting::query()
                ->whereIn('id', $customRuleIds)
                ->where('status', 1)
                ->get(['id', 'description']);

            Log::info('[申诉统计] 查询到自定义规则', ['count' => $customRulesData->count()]);

            foreach ($customRulesData as $rule) {
                $customRules[$rule->id + 1000000] = $rule->description ?? '';
                Log::info('[申诉统计] 自定义规则', ['id' => $rule->id + 1000000, 'description' => $rule->description]);
            }
        }

        // 合并规则信息
        $allRules = array_merge($standardRules, $customRules);
        Log::info('[申诉统计] 合并后的规则映射', ['all_rules' => $allRules]);

        // 按规则分组统计
        $ruleStats = [];
        foreach ($appealList as $appeal) {
            $errorId = $appeal->error_id;
            $status = $appeal->status;

            // 初始化规则统计
            if (!isset($ruleStats[$errorId])) {
                $ruleName = $allRules[$errorId] ?? '未知规则';

                // 调试：如果是未知规则，记录详细信息
                if ($ruleName === '未知规则') {
                    Log::warning('[申诉统计] 未找到规则名称', [
                        'error_id' => $errorId,
                        'error_id_type' => gettype($errorId),
                        'all_rules_keys' => array_keys($allRules),
                        'all_rules_keys_types' => array_map('gettype', array_keys($allRules)),
                        'exists_in_all_rules' => isset($allRules[$errorId]),
                    ]);
                }

                $ruleStats[$errorId] = [
                    'error_id' => $errorId,
                    'rule_name' => $ruleName,
                    'total' => 0,      // 申诉问题总数
                    'passed' => 0,     // 通过数（status=1）
                    'rejected' => 0,   // 驳回数（status=2）
                ];
            }

            $ruleStats[$errorId]['total']++;
            if ($status == 1) {
                $ruleStats[$errorId]['passed']++;
            } elseif ($status == 2) {
                $ruleStats[$errorId]['rejected']++;
            }
        }

        // 转换为表格数据格式
        $rows = [];
        $index = 1;
        foreach ($ruleStats as $errorId => $stat) {
            $total = $stat['total'];
            $rejected = $stat['rejected'];
            $successRate = $total > 0 ? sprintf('%.2f', (($total - $rejected) / $total) * 100) : '0.00';

            $rows[] = [
                'ssid' => $index,
                'ssmc' => $stat['rule_name'],
                'sswtnum' => (string)$total,
                'bhwtnum' => (string)$rejected,
                'sscgl' => $successRate,
            ];
            $index++;
        }

        // 按驳回数量降序排序
        usort($rows, function ($a, $b) {
            $rejectedA = (int)$a['bhwtnum'];
            $rejectedB = (int)$b['bhwtnum'];
            return $rejectedB <=> $rejectedA; // 降序
        });

        // 限制返回条数
        return array_slice($rows, 0, $limit);
    }

    /**
     * 从统计表获取全部单否项科室问题数据（支持动态汇总）
     * @param string $time 时间参数，格式：2025年12月 或 2025年第一季度 或 2025年
     * @param array $depIds 科室ID数组，用于筛选科室
     * @return array
     */
    private function getAllSingleNoItemDepartmentFromStats($time, $depIds = [])
    {
        // 解析时间，判断是否需要汇总多个月份
        $months = $this->parseTimeToMonths($time);

        // 先获取各科室的病历总数和缺陷数
        $deptTotalStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_DEPARTMENT, [
            'dimension_ids' => $depIds,
            'columns' => ['id', 'dimension_id', 'dimension_name', 'total_cases', 'defect_cases'],
        ]);

        // 按科室ID汇总总数和缺陷数
        $deptAggregated = [];
        foreach ($deptTotalStats as $stat) {
            $deptId = $stat->dimension_id;

            if (!isset($deptAggregated[$deptId])) {
                $deptAggregated[$deptId] = [
                    'dep_id' => $deptId,
                    'dep_name' => $stat->dimension_name,
                    'total_bl' => 0,
                    'qx_bl' => 0,
                ];
            }

            $deptAggregated[$deptId]['total_bl'] += $stat->total_cases;
            $deptAggregated[$deptId]['qx_bl'] += $stat->defect_cases;
        }

        // 从统计表查询单否项按科室分布数据
        $singleNoStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_RULE_DEPARTMENT, [
            'is_single_no' => 1,
        ]);

        // 按科室汇总单否项数据
        $departmentData = [];
        foreach ($singleNoStats as $stat) {
            $extraData = $this->getStatisticsExtraData($stat);
            $deptId = $extraData['department_id'] ?? '';
            $deptName = $extraData['department_name'] ?? '';

            if (empty($deptId) || empty($deptName)) {
                continue;
            }

            // 如果指定了科室筛选，只统计指定科室
            if (!empty($depIds) && !in_array($deptId, $depIds)) {
                continue;
            }

            if (!isset($departmentData[$deptId])) {
                // 从汇总的科室统计中获取病历总数和缺陷数
                $deptStat = $deptAggregated[$deptId] ?? null;
                $departmentData[$deptId] = [
                    'dep_id' => $deptId,
                    'dep_name' => $deptName,
                    'total_bl' => $deptStat ? $deptStat['total_bl'] : 0,
                    'qx_bl' => $deptStat ? $deptStat['qx_bl'] : 0,
                    'single_no_bl' => 0,
                ];
            }

            // 累加单否项缺陷病历数
            $departmentData[$deptId]['single_no_bl'] += $stat->rule_case_count;
        }

        // 转换为表格数据格式
        $rows = [];
        $index = 1;
        foreach ($departmentData as $depId => $stat) {
            $totalBl = $stat['total_bl'];
            $defectCases = $stat['qx_bl'];
            $singleNoBl = $stat['single_no_bl'];
            $radio = $totalBl > 0 ? sprintf('%.2f', ($defectCases / $totalBl) * 100) : '0.00';

            $rows[] = [
                'rank' => $index,
                'dep_id' => $depId,
                'dept_name' => $stat['dep_name'],
                'total_cases' => (int)$totalBl,
                'defect_cases' => (int)$stat['qx_bl'],
                'defect_count' => (int)$singleNoBl,
                'ratio' => (float)$radio,
            ];
            $index++;
        }

        // 按单否项缺陷占比降序排序
        usort($rows, function ($a, $b) {
            return $b['ratio'] <=> $a['ratio']; // 降序
        });

        return $rows;
    }

    /**
     * 从统计表获取病历质量最优前5名数据（支持动态汇总）
     * @param string $time 时间参数，格式：2025年12月 或 2025年第一季度 或 2025年
     * @param int $limit 返回条数限制，默认5
     * @param array $depIds 科室ID数组，用于筛选科室
     * @return array
     */
    private function getCaseQualityTopFiveFromStats($time, $limit = 5, $depIds = [])
    {
        // 解析时间，判断是否需要汇总多个月份
        $months = $this->parseTimeToMonths($time);

        // 从统计表查询科室数据
        $deptStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_DEPARTMENT, [
            'dimension_ids' => $depIds,
            'columns' => ['id', 'dimension_id', 'dimension_name', 'total_cases', 'defect_cases'],
        ]);

        // 按科室ID汇总数据
        $deptAggregated = [];
        foreach ($deptStats as $stat) {
            $deptId = $stat->dimension_id;

            if (!isset($deptAggregated[$deptId])) {
                $deptAggregated[$deptId] = [
                    'dimension_id' => $deptId,
                    'dimension_name' => $stat->dimension_name,
                    'total_cases' => 0,
                    'defect_cases' => 0,
                ];
            }

            $deptAggregated[$deptId]['total_cases'] += $stat->total_cases;
            $deptAggregated[$deptId]['defect_cases'] += $stat->defect_cases;
        }

        // 重新计算缺陷占比并转换为数组
        $rows = [];
        foreach ($deptAggregated as $stat) {
            if ($stat['total_cases'] <= 0) {
                continue;
            }

            $defectRatio = ($stat['defect_cases'] / $stat['total_cases']) * 100;

            $rows[] = [
                'dimension_id' => $stat['dimension_id'],
                'dimension_name' => $stat['dimension_name'],
                'total_cases' => $stat['total_cases'],
                'defect_cases' => $stat['defect_cases'],
                'defect_ratio' => $defectRatio,
            ];
        }

        // 按缺陷占比升序排序（占比越低越好）
        usort($rows, function ($a, $b) {
            return $a['defect_ratio'] <=> $b['defect_ratio'];
        });

        // 限制返回条数并格式化（按文档格式）
        $rows = array_slice($rows, 0, $limit);
        $formattedRows = [];
        $index = 1;

        foreach ($rows as $row) {
            $formattedRows[] = [
                'rank' => $index,
                'dep_id' => $row['dimension_id'],
                'dept_name' => $row['dimension_name'],
                'total_cases' => (int)$row['total_cases'],
                'defect_cases' => (int)$row['defect_cases'],
                'defect_ratio' => (float)$row['defect_ratio'],
            ];
            $index++;
        }

        return $formattedRows;
    }

    /**
     * 从统计表获取病历质量最差前5名数据（支持动态汇总）
     * @param string $time 时间参数，格式：2025年12月 或 2025年第一季度 或 2025年
     * @param int $limit 返回条数限制，默认5
     * @param array $depIds 科室ID数组，用于筛选科室
     * @return array
     */
    private function getCaseQualityBottomFiveFromStats($time, $limit = 5, $depIds = [])
    {
        // 解析时间，判断是否需要汇总多个月份
        $months = $this->parseTimeToMonths($time);

        // 从统计表查询科室数据
        $deptStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_DEPARTMENT, [
            'dimension_ids' => $depIds,
            'columns' => ['id', 'dimension_id', 'dimension_name', 'total_cases', 'defect_cases'],
        ]);

        // 按科室ID汇总数据
        $deptAggregated = [];
        foreach ($deptStats as $stat) {
            $deptId = $stat->dimension_id;

            if (!isset($deptAggregated[$deptId])) {
                $deptAggregated[$deptId] = [
                    'dimension_id' => $deptId,
                    'dimension_name' => $stat->dimension_name,
                    'total_cases' => 0,
                    'defect_cases' => 0,
                ];
            }

            $deptAggregated[$deptId]['total_cases'] += $stat->total_cases;
            $deptAggregated[$deptId]['defect_cases'] += $stat->defect_cases;
        }

        // 重新计算缺陷占比并转换为数组
        $rows = [];
        foreach ($deptAggregated as $stat) {
            if ($stat['total_cases'] <= 0) {
                continue;
            }

            $defectRatio = ($stat['defect_cases'] / $stat['total_cases']) * 100;

            $rows[] = [
                'dimension_id' => $stat['dimension_id'],
                'dimension_name' => $stat['dimension_name'],
                'total_cases' => $stat['total_cases'],
                'defect_cases' => $stat['defect_cases'],
                'defect_ratio' => $defectRatio,
            ];
        }

        // 按缺陷占比降序排序（占比越高越差）
        usort($rows, function ($a, $b) {
            return $b['defect_ratio'] <=> $a['defect_ratio'];
        });

        // 限制返回条数并格式化（按文档格式）
        $rows = array_slice($rows, 0, $limit);
        $formattedRows = [];
        $index = 1;

        foreach ($rows as $row) {
            $formattedRows[] = [
                'rank' => $index,
                'dept_name' => $row['dimension_name'],
                'total_cases' => (int)$row['total_cases'],
                'defect_cases' => (int)$row['defect_cases'],
                'defect_ratio' => (float)$row['defect_ratio'],
            ];
            $index++;
        }

        return $formattedRows;
    }

    /**
     * 从统计表获取所有科室病历质量排名数据（支持动态汇总）
     * @param string $time 时间参数，格式：2025年12月 或 2025年第一季度 或 2025年
     * @param array $depIds 科室ID数组，用于筛选科室
     * @return array
     */
    private function getAllCaseQualityRankingFromStats($time, $depIds = [])
    {
        // 解析时间，判断是否需要汇总多个月份
        $months = $this->parseTimeToMonths($time);

        // 从统计表查询所有科室数据
        $deptStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_DEPARTMENT, [
            'dimension_ids' => $depIds,
            'columns' => ['id', 'dimension_id', 'dimension_name', 'total_cases', 'defect_cases'],
        ]);

        // 按科室ID汇总数据
        $deptAggregated = [];
        foreach ($deptStats as $stat) {
            $deptId = $stat->dimension_id;

            if (!isset($deptAggregated[$deptId])) {
                $deptAggregated[$deptId] = [
                    'dimension_id' => $deptId,
                    'dimension_name' => $stat->dimension_name,
                    'total_cases' => 0,
                    'defect_cases' => 0,
                ];
            }

            $deptAggregated[$deptId]['total_cases'] += $stat->total_cases;
            $deptAggregated[$deptId]['defect_cases'] += $stat->defect_cases;
        }

        // 重新计算缺陷占比并转换为数组
        $rows = [];
        foreach ($deptAggregated as $stat) {
            if ($stat['total_cases'] <= 0) {
                continue;
            }

            $defectRatio = ($stat['defect_cases'] / $stat['total_cases']) * 100;

            $rows[] = [
                'dimension_id' => $stat['dimension_id'],
                'dimension_name' => $stat['dimension_name'],
                'total_cases' => $stat['total_cases'],
                'defect_cases' => $stat['defect_cases'],
                'defect_ratio' => $defectRatio,
            ];
        }

        // 按缺陷占比升序排序（占比越低越好）
        usort($rows, function ($a, $b) {
            return $a['defect_ratio'] <=> $b['defect_ratio'];
        });

        // 格式化输出（按文档格式）
        $formattedRows = [];
        $index = 1;
        foreach ($rows as $row) {
            $formattedRows[] = [
                'rank' => $index,
                'dep_id' => $row['dimension_id'],
                'dept_name' => $row['dimension_name'],
                'total_cases' => (int)$row['total_cases'],
                'defect_cases' => (int)$row['defect_cases'],
                'defect_ratio' => (float)$row['defect_ratio'],
            ];
            $index++;
        }

        return $formattedRows;
    }

    /**
     * 从统计表获取医师病历质量最优前5名数据（支持动态汇总）
     * @param string $time 时间参数，格式：2025年12月 或 2025年第一季度 或 2025年
     * @param int $limit 返回条数限制，默认5
     * @param array $depIds 科室ID数组，用于筛选科室
     * @return array
     */
    private function getDoctorCaseQualityTopFiveFromStats($time, $limit = 5, $depIds = [])
    {
        // 解析时间，判断是否需要汇总多个月份
        $months = $this->parseTimeToMonths($time);

        // 从统计表查询医师数据
        $doctorStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_DOCTOR, [
            'columns' => ['id', 'dimension_id', 'dimension_name', 'total_cases', 'defect_cases', 'avg_score', 'extra_data'],
        ]);
        // 如果指定了科室筛选，需要从 extra_data 中筛选
        if (!empty($depIds)) {
            $doctorStats = $doctorStats->filter(function ($stat) use ($depIds) {
                $extraData = $this->getStatisticsExtraData($stat);
                $deptId = $extraData['department_id'] ?? null;
                return in_array($deptId, $depIds);
            });
        }

        // 按医师ID汇总数据
        $doctorAggregated = [];
        foreach ($doctorStats as $stat) {
            $doctorId = $stat->dimension_id;

            if (!isset($doctorAggregated[$doctorId])) {
                $extraData = $this->getStatisticsExtraData($stat);
                $doctorAggregated[$doctorId] = [
                    'dimension_id' => $doctorId,
                    'dimension_name' => $stat->dimension_name,
                    'total_cases' => 0,
                    'defect_cases' => 0,
                    'total_score' => 0,
                    'total_deduction' => 0,
                    'department_id' => $extraData['department_id'] ?? null,
                    'department_name' => $extraData['department_name'] ?? '未知科室',
                ];
            }

            $extraData = $this->getStatisticsExtraData($stat);
            $doctorAggregated[$doctorId]['total_cases'] += $stat->total_cases;
            $doctorAggregated[$doctorId]['defect_cases'] += $stat->defect_cases;
            $doctorAggregated[$doctorId]['total_score'] += $stat->avg_score * $stat->total_cases; // 累加总分
            $doctorAggregated[$doctorId]['total_deduction'] += $extraData['total_deduction'] ?? 0;
        }

        // 重新计算平均分并转换为数组
        $rows = [];
        foreach ($doctorAggregated as $stat) {
            if ($stat['total_cases'] <= 0) {
                continue;
            }

            $avgScore = $stat['total_score'] / $stat['total_cases'];

            $rows[] = [
                'dimension_id' => $stat['dimension_id'],
                'dimension_name' => $stat['dimension_name'],
                'total_cases' => $stat['total_cases'],
                'defect_cases' => $stat['defect_cases'],
                'avg_score' => $avgScore,
                'total_deduction' => $stat['total_deduction'],
                'department_id' => $stat['department_id'],
                'department_name' => $stat['department_name'],
            ];
        }

        // 按平均分降序排序（平均分越高越好）
        usort($rows, function ($a, $b) {
            return $b['avg_score'] <=> $a['avg_score'];
        });

        // 限制返回条数并格式化
        $rows = array_slice($rows, 0, $limit);
        $formattedRows = [];
        $index = 1;

        foreach ($rows as $row) {
            $formattedRows[] = [
                'rank' => $index,
                'doctor_code' => $row['dimension_id'],
                'doctor_name' => $row['dimension_name'],
                'department_id' => $row['department_id'],
                'department_name' => $row['department_name'],
                'total_cases' => (int)$row['total_cases'],
                'defect_cases' => (int)$row['defect_cases'],
                'total_deduction' => (float)number_format($row['total_deduction'], 2),
                'avg_score' => (float)number_format($row['avg_score'], 2),
            ];
            $index++;
        }

        return $formattedRows;
    }

    /**
     * 从统计表获取医师病历质量最差前5名数据（支持动态汇总）
     * @param string $time 时间参数，格式：2025年12月 或 2025年第一季度 或 2025年
     * @param int $limit 返回条数限制，默认5
     * @param array $depIds 科室ID数组，用于筛选科室
     * @return array
     */
    private function getDoctorCaseQualityBottomFiveFromStats($time, $limit = 5, $depIds = [])
    {
        // 解析时间，判断是否需要汇总多个月份
        $months = $this->parseTimeToMonths($time);

        // 从统计表查询医师数据
        $doctorStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_DOCTOR, [
            'columns' => ['id', 'dimension_id', 'dimension_name', 'total_cases', 'defect_cases', 'avg_score', 'extra_data'],
        ]);
        // 如果指定了科室筛选，需要从 extra_data 中筛选
        if (!empty($depIds)) {
            $doctorStats = $doctorStats->filter(function ($stat) use ($depIds) {
                $extraData = $this->getStatisticsExtraData($stat);
                $deptId = $extraData['department_id'] ?? null;
                return in_array($deptId, $depIds);
            });
        }

        // 按医师ID汇总数据
        $doctorAggregated = [];
        foreach ($doctorStats as $stat) {
            $doctorId = $stat->dimension_id;

            if (!isset($doctorAggregated[$doctorId])) {
                $extraData = $this->getStatisticsExtraData($stat);
                $doctorAggregated[$doctorId] = [
                    'dimension_id' => $doctorId,
                    'dimension_name' => $stat->dimension_name,
                    'total_cases' => 0,
                    'defect_cases' => 0,
                    'total_score' => 0,
                    'total_deduction' => 0,
                    'department_id' => $extraData['department_id'] ?? null,
                    'department_name' => $extraData['department_name'] ?? '未知科室',
                ];
            }

            $extraData = $this->getStatisticsExtraData($stat);
            $doctorAggregated[$doctorId]['total_cases'] += $stat->total_cases;
            $doctorAggregated[$doctorId]['defect_cases'] += $stat->defect_cases;
            $doctorAggregated[$doctorId]['total_score'] += $stat->avg_score * $stat->total_cases; // 累加总分
            $doctorAggregated[$doctorId]['total_deduction'] += $extraData['total_deduction'] ?? 0;
        }

        // 重新计算平均分并转换为数组
        $rows = [];
        foreach ($doctorAggregated as $stat) {
            if ($stat['total_cases'] <= 0) {
                continue;
            }

            $avgScore = $stat['total_score'] / $stat['total_cases'];

            $rows[] = [
                'dimension_id' => $stat['dimension_id'],
                'dimension_name' => $stat['dimension_name'],
                'total_cases' => $stat['total_cases'],
                'defect_cases' => $stat['defect_cases'],
                'avg_score' => $avgScore,
                'total_deduction' => $stat['total_deduction'],
                'department_id' => $stat['department_id'],
                'department_name' => $stat['department_name'],
            ];
        }

        // 按平均分升序排序（平均分越低越差）
        usort($rows, function ($a, $b) {
            return $a['avg_score'] <=> $b['avg_score'];
        });

        // 限制返回条数并格式化
        $rows = array_slice($rows, 0, $limit);
        $formattedRows = [];
        $index = 1;

        foreach ($rows as $row) {
            $formattedRows[] = [
                'rank' => $index,
                'doctor_code' => $row['dimension_id'],
                'doctor_name' => $row['dimension_name'],
                'department_id' => $row['department_id'],
                'department_name' => $row['department_name'],
                'total_cases' => (int)$row['total_cases'],
                'defect_cases' => (int)$row['defect_cases'],
                'total_deduction' => (float)number_format($row['total_deduction'], 2),
                'avg_score' => (float)number_format($row['avg_score'], 2),
            ];
            $index++;
        }

        return $formattedRows;
    }

    /**
     * 获取所有申诉情况数据（按科室统计）
     * @param array $jzhmList 患者JZHM列表
     * @return array
     */
    private function getAllAppealByDepartmentData($jzhmList)
    {
        if (empty($jzhmList)) {
            return [];
        }

        // 查询该时间范围内的申诉记录（quality_type=2表示病历质控）
        $appealList = Appeal::query()
            ->where('quality_type', 2)
            ->whereIn('ZYH', $jzhmList)
            ->get(['ZYH', 'status', 'appeal_docter as appeal_doctor']); // 申诉医师字段

        // 获取所有科室信息
        $departments = Department::query()
            ->pluck('dep_name', 'dep_id')
            ->toArray();

        // 建立患者到科室的映射
        $patientToDepartment = PatientInfo::query()
            ->whereIn('MED_REC_ID', $jzhmList)
            ->whereNotNull('AAC02C')
            ->where('AAC02C', '!=', '')
            ->pluck('AAC02C', 'MED_REC_ID')
            ->toArray();

        // 按科室分组统计
        $departmentStats = [];
        foreach ($appealList as $appeal) {
            $zyh = $appeal->ZYH;
            $status = $appeal->status;
            $appealDoctor = $appeal->appeal_doctor ?? ''; // 申诉医师

            // 获取患者所属科室
            if (!isset($patientToDepartment[$zyh])) {
                continue; // 患者没有科室信息，跳过
            }

            $depId = $patientToDepartment[$zyh];
            $depName = $departments[$depId] ?? '未知科室';

            // 初始化科室统计
            if (!isset($departmentStats[$depId])) {
                $departmentStats[$depId] = [
                    'dep_id' => $depId,
                    'dep_name' => $depName,
                    'doctors' => [],      // 申诉医师集合（去重）
                    'total' => 0,         // 申诉问题总数
                    'passed' => 0,        // 通过数（status=1）
                    'rejected' => 0,      // 驳回数（status=2）
                ];
            }

            // 统计申诉医师（去重）
            if (!empty($appealDoctor)) {
                $departmentStats[$depId]['doctors'][$appealDoctor] = true;
            }

            $departmentStats[$depId]['total']++;
            if ($status == 1) {
                $departmentStats[$depId]['passed']++;
            } elseif ($status == 2) {
                $departmentStats[$depId]['rejected']++;
            }
        }

        // 转换为表格数据格式
        $rows = [];
        $index = 1;
        foreach ($departmentStats as $depId => $stat) {
            $doctorCount = count($stat['doctors']); // 申诉医师数
            $total = $stat['total'];
            $passed = $stat['passed'];
            $rejected = $stat['rejected'];
            $successRate = $total > 0 ? sprintf('%.2f', ($passed / $total) * 100) : '0.00';

            $rows[] = [
                'syssid' => $index,
                'syssmc' => $stat['dep_name'],
                'syssys' => (string)$doctorCount,
                'sywtnum' => (string)$total,
                'sytgwtnum' => (string)$passed,
                'sybhwtnum' => (string)$rejected,
                'sysscgl' => $successRate,
            ];
            $index++;
        }

        // 按申诉成功率降序排序
        usort($rows, function ($a, $b) {
            $rateA = (float)$a['sysscgl'];
            $rateB = (float)$b['sysscgl'];
            return $rateB <=> $rateA; // 降序
        });

        return $rows;
    }

    /**
     * 设置指标达成率最优和最差前5名表格数据
     * @param WordReportService $wordReportService
     * @param string $time
     * @param array $statisticsData
     * @param array $timeRanges 时间范围
     * @param array $depIds 科室ID数组，用于筛选
     */
    private function setIndicatorRankingTables(WordReportService $wordReportService, $time, $statisticsData, $timeRanges, $depIds = [])
    {
        Log::info('[指标排名] 开始设置指标排名表格', ['time' => $time, 'dep_ids' => $depIds]);

        try {
            // 从统计表获取指标达成率最优前5名数据（通用结构）
            $bestStats = $this->getIndicatorTopFiveFromStats($time, $depIds);
            Log::info('[指标排名] 指标最优前5数据', ['count' => count($bestStats)]);

            // 转换为 Word 模板需要的字段名
            $zbzyRows = [];
            foreach ($bestStats as $row) {
                $item = [
                    'zbzyid' => $row['rank'] ?? 0,
                    'zbzymc' => $row['indicator_name'] ?? '',
                ];
                // 前 5 名科室
                for ($i = 1; $i <= 5; $i++) {
                    $nameKey = "dept{$i}_name";
                    $rateKey = "dept{$i}";
                    $item["zbzyksmc{$i}"] = $row[$nameKey] ?? '';
                    $item["zbzyksradio{$i}"] = isset($row[$rateKey]) ? (string)$row[$rateKey] : '';
                }
                $zbzyRows[] = $item;
            }

            if (!empty($zbzyRows)) {
                $wordReportService->replaceTableRows('zbzyid', $zbzyRows);
                Log::info('[指标排名] 成功设置指标最优表格');
            }
        } catch (\Exception $e) {
            Log::error('[指标排名] 设置指标达成率最优前5名表格失败', [
                'error' => $e->getMessage()
            ]);
        }

        try {
            // 从统计表获取指标达成率最差前5名数据（通用结构）
            $worstStats = $this->getIndicatorBottomFiveFromStats($time, $depIds);
            Log::info('[指标排名] 指标最差前5数据', ['count' => count($worstStats)]);

            // 转换为 Word 模板需要的字段名
            $zbzcRows = [];
            foreach ($worstStats as $row) {
                $item = [
                    'zbzcid' => $row['rank'] ?? 0,
                    'zbzcmc' => $row['indicator_name'] ?? '',
                ];
                for ($i = 1; $i <= 5; $i++) {
                    $nameKey = "dept{$i}_name";
                    $rateKey = "dept{$i}";
                    $item["zbzcksmc{$i}"] = $row[$nameKey] ?? '';
                    $item["zbzcksradio{$i}"] = isset($row[$rateKey]) ? (string)$row[$rateKey] : '';
                }
                $zbzcRows[] = $item;
            }

            if (!empty($zbzcRows)) {
                $wordReportService->replaceTableRows('zbzcid', $zbzcRows);
                Log::info('[指标排名] 成功设置指标最差表格');
            }

            // 替换表头的名次占位符
            if (!empty($zbzcRows)) {
                $rankLabels = [];
                // 这里使用 1-5 作为名次，占位符名称沿用原模板（zyzcpm1-zyzcpm5）
                $max = min(5, count($zbzcRows));
                for ($i = 1; $i <= $max; $i++) {
                    $rankLabels["zyzcpm{$i}"] = (string)$i;
                }
                if (!empty($rankLabels)) {
                    $wordReportService->replaceVariables($rankLabels);
                    Log::info('[指标排名] 成功替换名次占位符', ['labels' => $rankLabels]);
                }
            }
        } catch (\Exception $e) {
            Log::error('[指标排名] 设置指标达成率最差前5名表格失败', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 从统计表获取指标达成率最优前5名数据（按科室分组，支持动态汇总）
     * @param string $time 时间参数，格式：2025年12月 或 2025年第一季度 或 2025年
     * @param array $depIds 科室ID数组，用于筛选科室
     * @return array
     */
    private function getIndicatorTopFiveFromStats($time, $depIds = [])
    {
        // 获取报告中需要的指标列表
        $indexCatalogs = $this->getCachedReportIndicators();
        if (empty($indexCatalogs)) {
            return [];
        }

        // 获取指标名称映射
        $indicatorNameMap = [];
        foreach ($indexCatalogs as $catalog) {
            $indicatorNameMap[$catalog['index_name']] = $catalog['name'];
        }

        // 解析时间，判断是否需要汇总多个月份
        $months = $this->parseTimeToMonths($time);

        // 从统计表查询所有指标按科室的数据
        $indicatorStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_INDICATOR, [
            'extra_data_dimension_type' => 'department',
            'columns' => ['id', 'dimension_id', 'dimension_name', 'indicator_value', 'indicator_target', 'extra_data'],
        ]);

        // 按指标和科室汇总数据
        $indicatorAggregated = [];
        foreach ($indicatorStats as $stat) {
            $extraData = $this->getStatisticsExtraData($stat);
            $indexName = $extraData['index_name'] ?? '';
            $deptId = $stat->dimension_id;
            $deptName = $extraData['department_name'] ?? $stat->dimension_name;

            if (empty($indexName)) {
                continue;
            }

            // 如果指定了科室筛选，只统计指定科室
            if (!empty($depIds) && !in_array($deptId, $depIds)) {
                continue;
            }

            $key = $indexName . '_' . $deptId;

            if (!isset($indicatorAggregated[$key])) {
                $indicatorAggregated[$key] = [
                    'index_name' => $indexName,
                    'dept_id' => $deptId,
                    'dept_name' => $deptName,
                    'numerator' => 0,
                    'denominator' => 0,
                ];
            }

            // 累加分子分母（修正：使用正确的字段名）
            $indicatorAggregated[$key]['numerator'] += $stat->indicator_value;      // 分子
            $indicatorAggregated[$key]['denominator'] += $stat->indicator_target;   // 分母
        }

        // 重新计算达成率并按指标分组
        $indicatorData = [];
        foreach ($indicatorAggregated as $item) {
            $indexName = $item['index_name'];
            $rate = $item['denominator'] > 0 ? ($item['numerator'] / $item['denominator']) * 100 : 0;

            if (!isset($indicatorData[$indexName])) {
                $indicatorData[$indexName] = [];
            }

            $indicatorData[$indexName][] = [
                'dep_id' => $item['dept_id'],
                'dep_name' => $item['dept_name'],
                'rate' => $rate,
            ];
        }

        // 构建表格数据
        $rows = [];
        $index = 1;

        foreach ($indexCatalogs as $catalog) {
            $indexName = $catalog['index_name'];

            if (!isset($indicatorData[$indexName]) || empty($indicatorData[$indexName])) {
                continue;
            }

            // 按达成率降序排序（最优）
            $departments = $indicatorData[$indexName];
            usort($departments, function ($a, $b) {
                return $b['rate'] <=> $a['rate'];
            });

            // 取前5名
            $topFive = array_slice($departments, 0, 5);

            // 构建表格行数据
            $name = $indicatorNameMap[$indexName] ?? '未知指标';
            $row = [
                'rank' => $index,
                'category' => $this->getIndicatorCategory($name),
                'indicator_name' => $name,
            ];

            // 填充前5名科室数据（包含科室名称和比率）
            for ($i = 0; $i < 5; $i++) {
                $rank = $i + 1;
                if (isset($topFive[$i])) {
                    $row["dept{$rank}_id"] = $topFive[$i]['dep_id'];
                    $row["dept{$rank}_name"] = $topFive[$i]['dep_name'];
                    $row["dept{$rank}"] = sprintf('%.2f%%', $topFive[$i]['rate']);
                } else {
                    $row["dept{$rank}_id"] = '';
                    $row["dept{$rank}_name"] = '';
                    $row["dept{$rank}"] = '';
                }
            }

            $rows[] = $row;
            $index++;
        }

        return $rows;
    }

    /**
     * 根据指标名称获取对应的 category ID
     * @param string $name 指标名称
     * @return int
     */
    private function getIndicatorCategory($name)
    {
        $map = [
            '入院记录24小时内完成率' => 40,
            '手术记录24小时内完成率' => 41,
            '出院记录24小时内完成率' => 42,
            '病案首页24小时内完成率' => 43,
            'CT/MRI检查记录符合率' => 44,
            '病理检查记录符合率' => 45,
            '细菌培养检查记录符合率' => 46,
            '抗菌药物使用记录符合率' => 47,
            '恶性肿瘤化学治疗记录符合率' => 48,
            '恶性肿瘤放射治疗记录符合率' => 49,
            '手术相关记录完整率' => 50,
            '植入物相关记录符合率' => 51,
            '临床用血相关记录符合率' => 52,
            '医师查房记录完整率' => 53,
            '患者抢救记录及时完成率' => 54,
            '出院患者病历2日归档率' => 55,
            '出院患者病历归档完整率' => 56,
            '主要诊断填写正确率' => 57,
            '主要诊断编码正确率' => 58,
            '主要手术填写正确率' => 59,
            '主要手术编码正确率' => 60,
            '不合理复制病历发生率' => 61,
            '知情同意书规范签署率' => 62,
            '甲级病历率' => 63,
        ];
        return $map[$name] ?? 0;
    }

    /**
     * 从统计表获取指标达成率最差前5名数据（按科室分组，支持动态汇总）
     * @param string $time 时间参数，格式：2025年12月 或 2025年第一季度 或 2025年
     * @param array $depIds 科室ID数组，用于筛选科室
     * @return array 返回包含rows和rank_labels的数组
     */
    private function getIndicatorBottomFiveFromStats($time, $depIds = [])
    {
        // 获取报告中需要的指标列表
        $indexCatalogs = $this->getCachedReportIndicators();
        if (empty($indexCatalogs)) {
            return ['rows' => [], 'rank_labels' => []];
        }

        // 获取指标名称映射
        $indicatorNameMap = [];
        foreach ($indexCatalogs as $catalog) {
            $indicatorNameMap[$catalog['index_name']] = $catalog['name'];
        }

        // 解析时间，判断是否需要汇总多个月份
        $months = $this->parseTimeToMonths($time);

        // 从统计表查询所有指标按科室的数据
        $indicatorStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_INDICATOR, [
            'extra_data_dimension_type' => 'department',
            'columns' => ['id', 'dimension_id', 'dimension_name', 'indicator_value', 'indicator_target', 'extra_data'],
        ]);

        // 按指标和科室汇总数据
        $indicatorAggregated = [];
        foreach ($indicatorStats as $stat) {
            $extraData = $this->getStatisticsExtraData($stat);
            $indexName = $extraData['index_name'] ?? '';
            $deptId = $stat->dimension_id;
            $deptName = $extraData['department_name'] ?? $stat->dimension_name;

            if (empty($indexName)) {
                continue;
            }

            // 如果指定了科室筛选，只统计指定科室
            if (!empty($depIds) && !in_array($deptId, $depIds)) {
                continue;
            }

            $key = $indexName . '_' . $deptId;

            if (!isset($indicatorAggregated[$key])) {
                $indicatorAggregated[$key] = [
                    'index_name' => $indexName,
                    'dept_id' => $deptId,
                    'dept_name' => $deptName,
                    'numerator' => 0,
                    'denominator' => 0,
                ];
            }

            // 累加分子分母（修正：使用正确的字段名）
            $indicatorAggregated[$key]['numerator'] += $stat->indicator_value;      // 分子
            $indicatorAggregated[$key]['denominator'] += $stat->indicator_target;   // 分母
        }

        // 重新计算达成率并按指标分组
        $indicatorData = [];
        foreach ($indicatorAggregated as $item) {
            $indexName = $item['index_name'];
            $rate = $item['denominator'] > 0 ? ($item['numerator'] / $item['denominator']) * 100 : 0;

            if (!isset($indicatorData[$indexName])) {
                $indicatorData[$indexName] = [];
            }

            $indicatorData[$indexName][] = [
                'dep_id' => $item['dept_id'],
                'dep_name' => $item['dept_name'],
                'rate' => $rate,
            ];
        }

        // 构建表格数据
        $rows = [];
        $index = 1;
        $maxTotalDepts = 0; // 记录最大的科室数量

        foreach ($indexCatalogs as $catalog) {
            $indexName = $catalog['index_name'];

            if (!isset($indicatorData[$indexName]) || empty($indicatorData[$indexName])) {
                continue;
            }

            // 按达成率升序排序（最差的在前面）
            $departments = $indicatorData[$indexName];
            usort($departments, function ($a, $b) {
                return $a['rate'] <=> $b['rate'];
            });

            // 获取科室总数
            $totalDepts = count($departments);
            $maxTotalDepts = max($maxTotalDepts, $totalDepts);

            // 取倒数5名：如果科室数>=5，取最后5个；如果<5，取全部
            $bottomCount = min(5, $totalDepts);
            $bottomFive = array_slice($departments, 0, $bottomCount);

            // 不要反转，保持最差的在第一个位置

            // 构建表格行数据
            $name = $indicatorNameMap[$indexName] ?? '未知指标';
            $row = [
                'rank' => $index,
                'category' => $this->getIndicatorCategory($name),
                'indicator_name' => $name,
            ];

            // 填充倒数5名科室数据（包含科室名称和比率）
            for ($i = 0; $i < 5; $i++) {
                $rank = $i + 1;
                if (isset($bottomFive[$i])) {
                    $row["dept{$rank}_id"] = $bottomFive[$i]['dep_id'];
                    $row["dept{$rank}_name"] = $bottomFive[$i]['dep_name'];
                    $row["dept{$rank}"] = sprintf('%.2f%%', $bottomFive[$i]['rate']);
                } else {
                    $row["dept{$rank}_id"] = '';
                    $row["dept{$rank}_name"] = '';
                    $row["dept{$rank}"] = '';
                }
            }

            $rows[] = $row;
            $index++;
        }

        return $rows;
    }

    /**
     * 获取报告中需要的指标列表
     * @return array 指标列表 [index_name => name]
     */
    private function getReportIndicators()
    {
        // 报告中需要的24个指标
        $reportIndicatorNames = [
            'ryjl24',
            'ssjl24',
            'cyjl24',
            'basy24',
            'ctmrfhl',
            'bljcjl',
            'xjpyjcjl',
            'kjywsy',
            'exzlhxzl',
            'exzlfl',
            'ssxgjl',
            'zrw',
            'lcyx',
            'yscf',
            'hzqjjsl',
            'cdl',
            'gdwzl',
            'zyzdzql',
            'zyzdbmzql',
            'zysszql',
            'zyssbmzql',
            'bhlfzbl',
            'zqtys',
            'jjbll',
        ];

        // 从数据库获取这些指标的详细信息
        $indexCatalogs = IndexCatalog::query()
            ->whereIn('index_name', $reportIndicatorNames)
            ->whereNotNull('index_name')
            ->get(['index_name', 'name'])
            ->toArray();

        return $indexCatalogs;
    }

    /**
     * 批量查询所有指标按科室分组的数据（优化：一次性查询所有指标）
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param array $indexCatalogs 指标列表
     * @return array ['index_name' => ['dep_id' => ['fenzi' => ..., 'fenmu' => ..., 'rate' => ...]]]
     */
    private function getBatchIndicatorDataByDepartment($startTime, $endTime, $indexCatalogs)
    {
        $logPrefix = '[QualityReport]';
        $result = [];

        if (empty($indexCatalogs)) {
            return $result;
        }

        // 提取开始和结束的年月
        $startYear = (int)date('Y', strtotime($startTime));
        $startMonth = (int)date('m', strtotime($startTime));
        $endYear = (int)date('Y', strtotime($endTime));
        $endMonth = (int)date('m', strtotime($endTime));

        // 构建所有指标的字段列表（一次性查询所有指标）
        $selectFields = ['AAC11N'];
        foreach ($indexCatalogs as $catalog) {
            $indexName = $catalog['index_name'];
            $selectFields[] = "sum({$indexName}_fz) as {$indexName}_fz";
            $selectFields[] = "sum({$indexName}_fm) as {$indexName}_fm";
        }
        $selectSql = implode(', ', $selectFields);

        try {
            $query = Indicator::query();

            // 如果跨年，需要分别处理
            if ($startYear == $endYear) {
                // 同一年
                if ($startMonth == $endMonth) {
                    // 单个月份，使用精确匹配（最快）
                    $query->where('AAC01_YEAR', $startYear)
                        ->where('AAC01_MONTH', $startMonth);
                } else {
                    // 跨月份，使用范围查询
                    $query->where('AAC01_YEAR', $startYear)
                        ->whereBetween('AAC01_MONTH', [$startMonth, $endMonth]);
                }
            } else {
                // 跨年，使用 whereBetween 作为后备方案
                $query->whereBetween('AAC01', [$startTime, $endTime]);
            }

            // 一次性查询所有指标按科室分组的数据（关联department表获取科室名称）
            $departmentData = $query->selectRaw($selectSql)
                ->leftJoin('department', 'indicator.AAC11N', '=', 'department.dep_id')
                ->whereNotNull('indicator.AAC11N')
                ->where('indicator.AAC11N', '!=', '')
                ->groupBy('indicator.AAC11N')
                ->get()
                ->toArray();

            // 获取所有科室信息（用于映射科室名称）
            $departments = Department::query()
                ->pluck('dep_name', 'dep_id')
                ->toArray();

            // 处理查询结果，按指标和科室组织数据
            foreach ($indexCatalogs as $catalog) {
                $indexName = $catalog['index_name'];
                $fenziField = $indexName . '_fz';
                $fenmuField = $indexName . '_fm';

                $result[$indexName] = [];

                foreach ($departmentData as $item) {
                    $depId = $item['AAC11N'];
                    $fenzi = (float)($item[$fenziField] ?? 0);
                    $fenmu = (float)($item[$fenmuField] ?? 0);

                    // 只统计分母大于0的
                    if ($fenmu > 0) {
                        $rate = ($fenzi / $fenmu) * 100;
                        $result[$indexName][$depId] = [
                            'fenzi' => $fenzi,
                            'fenmu' => $fenmu,
                            'rate' => $rate,
                            'dep_name' => $departments[$depId] ?? '未知科室', // 添加科室名称
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("{$logPrefix} 批量查询指标按科室分组数据失败", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $result;
    }

    /**
     * 获取指标达成率最优前5名数据（优化：一次性查询所有指标）
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @return array
     */
    private function getIndicatorTopFiveData($startTime, $endTime)
    {
        $logPrefix = '[QualityReport]';
        // 只获取报告中需要的指标列表（24个指标）
        $indexCatalogs = $this->getReportIndicators();

        if (empty($indexCatalogs)) {
            return [];
        }

        // 获取所有科室信息
        $departments = Department::query()
            ->pluck('dep_name', 'dep_id')
            ->toArray();

        // 获取指标名称映射
        $indicatorNameMap = [];
        foreach ($indexCatalogs as $catalog) {
            $indicatorNameMap[$catalog['index_name']] = $catalog['name'];
        }

        $totalStart = microtime(true);

        // 一次性批量查询所有指标按科室分组的数据
        $batchData = $this->getBatchIndicatorDataByDepartment($startTime, $endTime, $indexCatalogs);
        Log::info("{$logPrefix} [步骤14.1] 批量查询所有指标按科室数据完成", [
            '耗时' => round((microtime(true) - $totalStart) * 1000, 2) . 'ms'
        ]);

        $rows = [];
        $index = 1;

        // 遍历每个指标，从批量查询结果中获取数据
        foreach ($indexCatalogs as $catalog) {
            $indexName = $catalog['index_name'];

            if (!isset($batchData[$indexName]) || empty($batchData[$indexName])) {
                continue; // 该指标没有数据，跳过
            }

            // 计算每个科室的达成率
            $departmentRates = [];
            foreach ($batchData[$indexName] as $depId => $data) {
                $depName = $departments[$depId] ?? '未知科室';
                $departmentRates[] = [
                    'dep_id' => $depId,
                    'dep_name' => $depName,
                    'rate' => $data['rate'],
                ];
            }

            // 按达成率降序排序（最优）
            usort($departmentRates, function ($a, $b) {
                return $b['rate'] <=> $a['rate']; // 降序
            });

            // 取前5名
            $topFive = array_slice($departmentRates, 0, 5);

            if (empty($topFive)) {
                continue; // 没有数据，跳过
            }

            // 构建表格行数据
            $row = [
                'zbzyid' => $index,
                'zbzymc' => $indicatorNameMap[$indexName] ?? '未知指标',
            ];

            // 填充前5名科室数据
            for ($i = 0; $i < 5; $i++) {
                $rank = $i + 1;
                if (isset($topFive[$i])) {
                    $row["zbzyksmc{$rank}"] = $topFive[$i]['dep_name'];
                    $row["zbzyksradio{$rank}"] = sprintf('%.2f%%', $topFive[$i]['rate']);
                } else {
                    $row["zbzyksmc{$rank}"] = '';
                    $row["zbzyksradio{$rank}"] = '';
                }
            }

            $rows[] = $row;
            $index++;
        }

        $totalTime = round((microtime(true) - $totalStart) * 1000, 2);
        Log::info("{$logPrefix} [步骤14.1] 指标达成率最优前5名数据获取完成", [
            '总耗时' => $totalTime . 'ms',
            '指标数量' => count($indexCatalogs),
            '返回数据条数' => count($rows)
        ]);

        return $rows;
    }

    /**
     * 获取指标达成率最差前5名数据（优化：一次性查询所有指标）
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @return array
     */
    private function getIndicatorBottomFiveData($startTime, $endTime)
    {
        $logPrefix = '[QualityReport]';
        // 只获取报告中需要的指标列表（24个指标）
        $indexCatalogs = $this->getReportIndicators();

        if (empty($indexCatalogs)) {
            return [];
        }

        // 获取所有科室信息
        $departments = Department::query()
            ->pluck('dep_name', 'dep_id')
            ->toArray();

        // 获取指标名称映射
        $indicatorNameMap = [];
        foreach ($indexCatalogs as $catalog) {
            $indicatorNameMap[$catalog['index_name']] = $catalog['name'];
        }

        $totalStart = microtime(true);

        // 一次性批量查询所有指标按科室分组的数据（复用批量查询方法）
        $batchData = $this->getBatchIndicatorDataByDepartment($startTime, $endTime, $indexCatalogs);
        Log::info("{$logPrefix} [步骤14.2] 批量查询所有指标按科室数据完成", [
            '耗时' => round((microtime(true) - $totalStart) * 1000, 2) . 'ms'
        ]);

        $rows = [];
        $index = 1;

        // 遍历每个指标，从批量查询结果中获取数据
        foreach ($indexCatalogs as $catalog) {
            $indexName = $catalog['index_name'];

            if (!isset($batchData[$indexName]) || empty($batchData[$indexName])) {
                continue; // 该指标没有数据，跳过
            }

            // 计算每个科室的达成率
            $departmentRates = [];
            foreach ($batchData[$indexName] as $depId => $data) {
                $depName = $departments[$depId] ?? '未知科室';
                $departmentRates[] = [
                    'dep_id' => $depId,
                    'dep_name' => $depName,
                    'rate' => $data['rate'],
                ];
            }

            // 按达成率升序排序（最差）
            usort($departmentRates, function ($a, $b) {
                return $a['rate'] <=> $b['rate']; // 升序
            });

            // 取前5名（最差的5个）
            $bottomFive = array_slice($departmentRates, 0, 5);

            if (empty($bottomFive)) {
                continue; // 没有数据，跳过
            }

            // 构建表格行数据
            $row = [
                'zbzcid' => $index,
                'zbzcmc' => $indicatorNameMap[$indexName] ?? '未知指标',
            ];

            // 填充前5名科室数据
            for ($i = 0; $i < 5; $i++) {
                $rank = $i + 1;
                if (isset($bottomFive[$i])) {
                    $row["zbzcksmc{$rank}"] = $bottomFive[$i]['dep_name'];
                    $row["zbzcksradio{$rank}"] = sprintf('%.2f%%', $bottomFive[$i]['rate']);
                } else {
                    $row["zbzcksmc{$rank}"] = '';
                    $row["zbzcksradio{$rank}"] = '';
                }
            }

            $rows[] = $row;
            $index++;
        }

        $totalTime = round((microtime(true) - $totalStart) * 1000, 2);
        Log::info("{$logPrefix} [步骤14.2] 指标达成率最差前5名数据获取完成", [
            '总耗时' => $totalTime . 'ms',
            '指标数量' => count($indexCatalogs),
            '返回数据条数' => count($rows)
        ]);

        return $rows;
    }

    /**
     * 设置病历质量最优和最差前5名表格和图表数据
     * @param WordReportService $wordReportService
     * @param string $time
     * @param array $statisticsData
     * @param array $depIds 科室ID数组，用于筛选
     */
    private function setCaseQualityRankingTables(WordReportService $wordReportService, $time, $statisticsData, $depIds = [])
    {
        // 从统计表获取病历质量最优前5名数据（通用结构）
        $bestDeptStats = $this->getCaseQualityTopFiveFromStats($time, 5, $depIds);

        // 转换为 Word 模板需要的字段名
        $ksblzyRows = [];
        foreach ($bestDeptStats as $row) {
            $ksblzyRows[] = [
                'ksblzyid'    => $row['rank'] ?? 0,
                'ksblzymc'    => $row['dept_name'] ?? '',
                'ksblzynum'   => (string)($row['total_cases'] ?? 0),
                'ksblzyqx'    => (string)($row['defect_cases'] ?? 0),
                'ksblzyradio' => isset($row['defect_ratio']) ? number_format((float)$row['defect_ratio'], 2) : '0.00',
            ];
        }
        if (!empty($ksblzyRows)) {
            try {
                $wordReportService->replaceTableRows('ksblzyid', $ksblzyRows);
            } catch (\Exception $e) {
                Log::warning('设置病历质量最优前5名表格失败', ['error' => $e->getMessage()]);
            }
        }

        // 获取病历质量最优前5名图表数据
        $kszytbChartData = [];
        foreach ($ksblzyRows as $row) {
            $kszytbChartData[$row['ksblzymc']] = [
                'total' => (int)$row['ksblzynum'],
                'defect' => (int)$row['ksblzyqx'],
            ];
        }

        if (!empty($kszytbChartData)) {
            try {
                $kszytbChartOptions = [
                    'width' => 5256530,       // 与模板实例图表一致 (约14.6cm)
                    'height' => 2988310,      // 约8.3cm（高度保持不变）
                    'title' => '',            // 不显示标题
                    'overlap' => -28,         // 系列间距
                    'gapWidth' => 246,        // 柱状图宽度
                    'addTrendline' => false,  // 不需要趋势线
                    'showGridY' => true,      // 显示Y轴网格线（横线）
                ];
                $wordReportService->replaceChart('kszytb', $kszytbChartData, $kszytbChartOptions);
            } catch (\Exception $e) {
                Log::warning('设置病历质量最优前5名图表失败', ['error' => $e->getMessage()]);
            }
        }

        // 从统计表获取病历质量最差前5名数据
        $worstDeptStats = $this->getCaseQualityBottomFiveFromStats($time, 5, $depIds);

        // 转换为 Word 模板需要的字段名
        $ksblzcRows = [];
        foreach ($worstDeptStats as $row) {
            $ksblzcRows[] = [
                'ksblzcid'    => $row['rank'] ?? 0,
                'ksblzcmc'    => $row['dept_name'] ?? '',
                'ksblzcnum'   => (string)($row['total_cases'] ?? 0),
                'ksblzcqx'    => (string)($row['defect_cases'] ?? 0),
                'ksblzcradio' => isset($row['defect_ratio']) ? number_format((float)$row['defect_ratio'], 2) : '0.00',
            ];
        }
        if (!empty($ksblzcRows)) {
            try {
                $wordReportService->replaceTableRows('ksblzcid', $ksblzcRows);
            } catch (\Exception $e) {
                Log::warning('设置病历质量最差前5名表格失败', ['error' => $e->getMessage()]);
            }
        }

        // 获取病历质量最差前5名图表数据
        $kszctbChartData = [];
        foreach ($ksblzcRows as $row) {
            $kszctbChartData[$row['ksblzcmc']] = [
                'total' => (int)$row['ksblzcnum'],
                'defect' => (int)$row['ksblzcqx'],
            ];
        }

        if (!empty($kszctbChartData)) {
            try {
                $kszctbChartOptions = [
                    'width' => 5256530,       // 与模板实例图表一致 (约14.6cm)
                    'height' => 2988310,      // 约8.3cm（高度保持不变）
                    'title' => '',            // 不显示标题
                    'overlap' => -28,         // 系列间距
                    'gapWidth' => 246,        // 柱状图宽度
                    'addTrendline' => false,  // 不需要趋势线
                    'showGridY' => true,      // 显示Y轴网格线（横线）
                ];
                $wordReportService->replaceChart('kszctb', $kszctbChartData, $kszctbChartOptions);
            } catch (\Exception $e) {
                Log::warning('设置病历质量最差前5名图表失败', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * 获取病历质量最优前5名数据
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param int $limit 返回条数限制，默认5
     * @return array
     */
    private function getCaseQualityTopFiveData($startTime, $endTime, $limit = 5)
    {
        // 获取该时间范围内的所有患者，包含科室信息
        $patients = PatientInfo::query()
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->whereNotNull('AAC02C')
            ->where('AAC02C', '!=', '')
            ->get(['MED_REC_ID', 'AAC02C']);

        if ($patients->isEmpty()) {
            return [];
        }

        // 获取所有科室信息
        $departments = Department::query()
            ->pluck('dep_name', 'dep_id')
            ->toArray();

        // 获取所有病历缺陷
        $jzhmList = $patients->pluck('MED_REC_ID')->toArray();
        $caseQualityList = CaseQuality::query()
            ->whereIn('JZHM', $jzhmList)
            ->get(['JZHM']);

        // 按JZHM分组，统计有缺陷的病历（去重）
        $defectJzhmSet = [];
        foreach ($caseQualityList as $defect) {
            $defectJzhmSet[$defect->JZHM] = true;
        }

        // 按科室分组统计
        $departmentStats = [];
        foreach ($patients as $patient) {
            $depId = $patient->AAC02C;
            $jzhm = $patient->MED_REC_ID;

            // 初始化科室统计
            if (!isset($departmentStats[$depId])) {
                $departmentStats[$depId] = [
                    'dep_id' => $depId,
                    'dep_name' => $departments[$depId] ?? '未知科室',
                    'total' => 0,      // 质控病历总数
                    'defect' => 0,     // 缺陷病历数
                ];
            }

            $departmentStats[$depId]['total']++;
            if (isset($defectJzhmSet[$jzhm])) {
                $departmentStats[$depId]['defect']++;
            }
        }

        // 计算缺陷占比并转换为表格数据格式
        $rows = [];
        foreach ($departmentStats as $depId => $stat) {
            $total = $stat['total'];
            $defect = $stat['defect'];
            $radio = $total > 0 ? sprintf('%.2f', ($defect / $total) * 100) : '0.00';

            $rows[] = [
                'dep_id' => $depId,
                'dep_name' => $stat['dep_name'],
                'total' => $total,
                'defect' => $defect,
                'radio' => $radio,
            ];
        }

        // 按缺陷占比升序排序（占比越低越好）
        usort($rows, function ($a, $b) {
            $radioA = (float)$a['radio'];
            $radioB = (float)$b['radio'];
            return $radioA <=> $radioB; // 升序
        });

        // 取前5名
        $topFive = array_slice($rows, 0, $limit);

        // 转换为表格数据格式
        $result = [];
        $index = 1;
        foreach ($topFive as $item) {
            $result[] = [
                'ksblzyid' => $index,
                'ksblzymc' => $item['dep_name'],
                'ksblzynum' => (string)$item['total'],
                'ksblzyqx' => (string)$item['defect'],
                'ksblzyradio' => $item['radio'],
            ];
            $index++;
        }

        return $result;
    }

    /**
     * 获取病历质量最优前5名图表数据
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @return array
     */
    private function getCaseQualityTopFiveChartData($startTime, $endTime)
    {
        // 获取最优前5名数据
        $topFiveData = $this->getCaseQualityTopFiveData($startTime, $endTime, 5);

        if (empty($topFiveData)) {
            return [];
        }

        // 转换为图表数据格式
        $chartData = [];
        foreach ($topFiveData as $item) {
            $chartData[$item['ksblzymc']] = [
                'total' => (int)$item['ksblzynum'],
                'defect' => (int)$item['ksblzyqx'],
            ];
        }

        return $chartData;
    }

    /**
     * 获取病历质量最差前5名数据
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param int $limit 返回条数限制，默认5
     * @return array
     */
    private function getCaseQualityBottomFiveData($startTime, $endTime, $limit = 5)
    {
        // 获取该时间范围内的所有患者，包含科室信息
        $patients = PatientInfo::query()
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->whereNotNull('AAC02C')
            ->where('AAC02C', '!=', '')
            ->get(['MED_REC_ID', 'AAC02C']);

        if ($patients->isEmpty()) {
            return [];
        }

        // 获取所有科室信息
        $departments = Department::query()
            ->pluck('dep_name', 'dep_id')
            ->toArray();

        // 获取所有病历缺陷
        $jzhmList = $patients->pluck('MED_REC_ID')->toArray();
        $caseQualityList = CaseQuality::query()
            ->whereIn('JZHM', $jzhmList)
            ->get(['JZHM']);

        // 按JZHM分组，统计有缺陷的病历（去重）
        $defectJzhmSet = [];
        foreach ($caseQualityList as $defect) {
            $defectJzhmSet[$defect->JZHM] = true;
        }

        // 按科室分组统计
        $departmentStats = [];
        foreach ($patients as $patient) {
            $depId = $patient->AAC02C;
            $jzhm = $patient->MED_REC_ID;

            // 初始化科室统计
            if (!isset($departmentStats[$depId])) {
                $departmentStats[$depId] = [
                    'dep_id' => $depId,
                    'dep_name' => $departments[$depId] ?? '未知科室',
                    'total' => 0,      // 质控病历总数
                    'defect' => 0,     // 缺陷病历数
                ];
            }

            $departmentStats[$depId]['total']++;
            if (isset($defectJzhmSet[$jzhm])) {
                $departmentStats[$depId]['defect']++;
            }
        }

        // 计算缺陷占比并转换为表格数据格式
        $rows = [];
        foreach ($departmentStats as $depId => $stat) {
            $total = $stat['total'];
            $defect = $stat['defect'];
            $radio = $total > 0 ? sprintf('%.2f', ($defect / $total) * 100) : '0.00';

            $rows[] = [
                'dep_id' => $depId,
                'dep_name' => $stat['dep_name'],
                'total' => $total,
                'defect' => $defect,
                'radio' => $radio,
            ];
        }

        // 按缺陷占比降序排序（占比越高越差）
        usort($rows, function ($a, $b) {
            $radioA = (float)$a['radio'];
            $radioB = (float)$b['radio'];
            return $radioB <=> $radioA; // 降序
        });

        // 取前5名（最差的5个）
        $bottomFive = array_slice($rows, 0, $limit);

        // 转换为表格数据格式
        $result = [];
        $index = 1;
        foreach ($bottomFive as $item) {
            $result[] = [
                'ksblzcid' => $index,
                'ksblzcmc' => $item['dep_name'],
                'ksblzcnum' => (string)$item['total'],
                'ksblzcqx' => (string)$item['defect'],
                'ksblzcradio' => $item['radio'],
            ];
            $index++;
        }

        return $result;
    }

    /**
     * 获取病历质量最差前5名图表数据
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @return array
     */
    private function getCaseQualityBottomFiveChartData($startTime, $endTime)
    {
        // 获取最差前5名数据
        $bottomFiveData = $this->getCaseQualityBottomFiveData($startTime, $endTime, 5);

        if (empty($bottomFiveData)) {
            return [];
        }

        // 转换为图表数据格式
        $chartData = [];
        foreach ($bottomFiveData as $item) {
            $chartData[$item['ksblzcmc']] = [
                'total' => (int)$item['ksblzcnum'],
                'defect' => (int)$item['ksblzcqx'],
            ];
        }

        return $chartData;
    }

    /**
     * 设置病历质量所有科室排名表格数据
     * @param WordReportService $wordReportService
     * @param string $time
     * @param array $statisticsData
     * @param array $depIds 科室ID数组，用于筛选
     */
    private function setAllCaseQualityRankingTable(WordReportService $wordReportService, $time, $statisticsData, $depIds = [])
    {
        // 从统计表获取所有科室排名数据
        $allDeptStats = $this->getAllCaseQualityRankingFromStats($time, $depIds);

        // 转换为 Word 模板需要的字段名
        $syksblRows = [];
        foreach ($allDeptStats as $row) {
            $syksblRows[] = [
                'syksblmc'    => $row['dept_name'] ?? '',
                'syksblnum'   => (string)($row['total_cases'] ?? 0),
                'syksblqx'    => (string)($row['defect_cases'] ?? 0),
                'syksblradio' => isset($row['defect_ratio']) ? number_format((float)$row['defect_ratio'], 2) : '0.00',
            ];
        }

        if (!empty($syksblRows)) {
            try {
                $wordReportService->replaceTableRows('syksblmc', $syksblRows);
            } catch (\Exception $e) {
                Log::warning('设置病历质量所有科室排名表格失败', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * 获取病历质量所有科室排名数据
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @return array
     */
    private function getAllCaseQualityRankingData($startTime, $endTime)
    {
        // 获取该时间范围内的所有患者，包含科室信息
        $patients = PatientInfo::query()
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->whereNotNull('AAC02C')
            ->where('AAC02C', '!=', '')
            ->get(['MED_REC_ID', 'AAC02C']);

        if ($patients->isEmpty()) {
            return [];
        }

        // 获取所有科室信息
        $departments = Department::query()
            ->pluck('dep_name', 'dep_id')
            ->toArray();

        // 获取所有病历缺陷
        $jzhmList = $patients->pluck('MED_REC_ID')->toArray();
        $caseQualityList = CaseQuality::query()
            ->whereIn('JZHM', $jzhmList)
            ->get(['JZHM']);

        // 按JZHM分组，统计有缺陷的病历（去重）
        $defectJzhmSet = [];
        foreach ($caseQualityList as $defect) {
            $defectJzhmSet[$defect->JZHM] = true;
        }

        // 按科室分组统计
        $departmentStats = [];
        foreach ($patients as $patient) {
            $depId = $patient->AAC02C;
            $jzhm = $patient->MED_REC_ID;

            // 初始化科室统计
            if (!isset($departmentStats[$depId])) {
                $departmentStats[$depId] = [
                    'dep_id' => $depId,
                    'dep_name' => $departments[$depId] ?? '未知科室',
                    'total' => 0,      // 质控病历总数
                    'defect' => 0,     // 缺陷病历数
                ];
            }

            $departmentStats[$depId]['total']++;
            if (isset($defectJzhmSet[$jzhm])) {
                $departmentStats[$depId]['defect']++;
            }
        }

        // 计算缺陷占比并转换为表格数据格式
        $rows = [];
        foreach ($departmentStats as $depId => $stat) {
            $total = $stat['total'];
            $defect = $stat['defect'];
            $radio = $total > 0 ? sprintf('%.2f', ($defect / $total) * 100) : '0.00';

            $rows[] = [
                'dep_id' => $depId,
                'dep_name' => $stat['dep_name'],
                'total' => $total,
                'defect' => $defect,
                'radio' => $radio,
            ];
        }

        // 按缺陷占比升序排序（占比越低越好）
        usort($rows, function ($a, $b) {
            $radioA = (float)$a['radio'];
            $radioB = (float)$b['radio'];
            return $radioA <=> $radioB; // 升序
        });

        // 转换为表格数据格式
        $result = [];
        foreach ($rows as $item) {
            $result[] = [
                'syksblmc' => $item['dep_name'],
                'syksblnum' => (string)$item['total'],
                'syksblqx' => (string)$item['defect'],
                'syksblradio' => $item['radio'],
            ];
        }

        return $result;
    }

    /**
     * 设置医师病历质量最优和最差前5名表格和图表数据
     * @param WordReportService $wordReportService
     * @param string $time
     * @param array $statisticsData
     * @param array $depIds 科室ID数组，用于筛选
     */
    private function setDoctorCaseQualityRankingTables(WordReportService $wordReportService, $time, $statisticsData, $depIds = [])
    {
        // 从统计表获取医师病历质量最优前5名数据（通用结构）
        $bestDoctorStats = $this->getDoctorCaseQualityTopFiveFromStats($time, 5, $depIds);

        // 转换为 Word 模板需要的字段名
        $ysblzlzyRows = [];
        foreach ($bestDoctorStats as $row) {
            $ysblzlzyRows[] = [
                'ysblzlzyid' => $row['rank'] ?? 0,
                'ysblzlys'   => $row['doctor_name'] ?? $row['dimension_name'] ?? '',
                'ysblzlks'   => $row['department_name'] ?? '',
                'ysblzlnum'  => (string)($row['total_cases'] ?? 0),
                'ysblzlqx'   => (string)($row['defect_cases'] ?? 0),
                'ysblzlkf'   => isset($row['total_deduction']) ? sprintf('%.2f', (float)$row['total_deduction']) : '0.00',
                'ysblzlpjf'  => isset($row['avg_score']) ? sprintf('%.2f', (float)$row['avg_score']) : '0.00',
            ];
        }
        if (!empty($ysblzlzyRows)) {
            try {
                $wordReportService->replaceTableRows('ysblzlzyid', $ysblzlzyRows);
            } catch (\Exception $e) {
                Log::warning('设置医师病历质量最优前5名表格失败', ['error' => $e->getMessage()]);
            }
        }

        // 获取医师病历质量最优前5名图表数据
        $ysblzltbChartData = [];
        foreach ($ysblzlzyRows as $row) {
            $ysblzltbChartData[$row['ysblzlys']] = [
                'total' => (int)$row['ysblzlnum'],
                'defect' => (int)$row['ysblzlqx'],
            ];
        }

        if (!empty($ysblzltbChartData)) {
            try {
                $ysblzltbChartOptions = [
                    'width' => 5256530,       // 与模板实例图表一致 (约14.6cm)
                    'height' => 2988310,      // 约8.3cm（高度保持不变）
                    'title' => '',            // 不显示标题
                    'overlap' => -28,         // 系列间距
                    'gapWidth' => 246,        // 柱状图宽度
                    'addTrendline' => false,  // 不需要趋势线
                    'showGridY' => true,      // 显示Y轴网格线（横线）
                ];
                $wordReportService->replaceChart('ysblzltb', $ysblzltbChartData, $ysblzltbChartOptions);
            } catch (\Exception $e) {
                Log::warning('设置医师病历质量最优前5名图表失败', ['error' => $e->getMessage()]);
            }
        }

        // 从统计表获取医师病历质量最差前5名数据
        $worstDoctorStats = $this->getDoctorCaseQualityBottomFiveFromStats($time, 5, $depIds);

        // 转换为 Word 模板需要的字段名
        $zcysblzlzyRows = [];
        foreach ($worstDoctorStats as $row) {
            $zcysblzlzyRows[] = [
                'zcysblzlzyid' => $row['rank'] ?? 0,
                'zcysblzlys'   => $row['doctor_name'] ?? $row['dimension_name'] ?? '',
                'zcysblzlks'   => $row['department_name'] ?? '',
                'zcysblzlnum'  => (string)($row['total_cases'] ?? 0),
                'zcysblzlqx'   => (string)($row['defect_cases'] ?? 0),
                'zcysblzlkf'   => isset($row['total_deduction']) ? sprintf('%.2f', (float)$row['total_deduction']) : '0.00',
                'zcysblzlpjf'  => isset($row['avg_score']) ? sprintf('%.2f', (float)$row['avg_score']) : '0.00',
            ];
        }
        if (!empty($zcysblzlzyRows)) {
            try {
                $wordReportService->replaceTableRows('zcysblzlzyid', $zcysblzlzyRows);
            } catch (\Exception $e) {
                Log::warning('设置医师病历质量最差前5名表格失败', ['error' => $e->getMessage()]);
            }
        }

        // 获取医师病历质量最差前5名图表数据
        $zxysblzltbChartData = [];
        foreach ($zcysblzlzyRows as $row) {
            $zxysblzltbChartData[$row['zcysblzlys']] = [
                'total' => (int)$row['zcysblzlnum'],
                'defect' => (int)$row['zcysblzlqx'],
            ];
        }

        if (!empty($zxysblzltbChartData)) {
            try {
                $zxysblzltbChartOptions = [
                    'width' => 5256530,       // 与模板实例图表一致 (约14.6cm)
                    'height' => 2988310,      // 约8.3cm（高度保持不变）
                    'title' => '',            // 不显示标题
                    'overlap' => -28,         // 系列间距
                    'gapWidth' => 246,        // 柱状图宽度
                    'addTrendline' => false,  // 不需要趋势线
                    'showGridY' => true,      // 显示Y轴网格线（横线）
                ];
                $wordReportService->replaceChart('zxysblzltb', $zxysblzltbChartData, $zxysblzltbChartOptions);
            } catch (\Exception $e) {
                Log::warning('设置医师病历质量最差前5名图表失败', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * 获取医师病历质量最优前5名数据
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param int $limit 返回条数限制，默认5
     * @return array
     */
    private function getDoctorCaseQualityTopFiveData($startTime, $endTime, $limit = 5)
    {
        // 获取该时间范围内的所有患者，包含医师和科室信息
        $patients = PatientInfo::query()
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->whereNotNull('AEE04_CODE')
            ->where('AEE04_CODE', '!=', '')
            ->whereNotNull('AAC02C')
            ->where('AAC02C', '!=', '')
            ->get(['MED_REC_ID', 'AEE04_CODE', 'AAC02C']);

        if ($patients->isEmpty()) {
            return [];
        }

        // 获取所有医师信息
        $doctorCodes = $patients->pluck('AEE04_CODE')->unique()->toArray();
        $doctors = Staff::query()
            ->whereIn('code', $doctorCodes)
            ->pluck('name', 'code')
            ->toArray();

        // 获取所有科室信息
        $departments = Department::query()
            ->pluck('dep_name', 'dep_id')
            ->toArray();

        // 批量获取病历缺陷规则分数
        $caseRuleMap = CaseRule::query()
            ->where('status', 0)
            ->pluck('score', 'id')
            ->toArray();

        // 批量获取自定义规则分数
        $ruleSettingMap = RuleSetting::query()
            ->where('status', 1)
            ->pluck('score', 'id')
            ->toArray();

        // 批量获取首页缺陷规则分数
        $errorRuleMap = ErrorRule::query()
            ->where('status', 0)
            ->pluck('down', 'id')
            ->toArray();

        // 获取所有病历缺陷
        $jzhmList = $patients->pluck('MED_REC_ID')->toArray();
        $caseQualityData = CaseQuality::query()
            ->whereIn('JZHM', $jzhmList)
            ->get(['JZHM', 'rule_id']);

        // 按JZHM分组
        $caseQualityList = [];
        foreach ($caseQualityData as $item) {
            $jzhm = $item->JZHM;
            if (!isset($caseQualityList[$jzhm])) {
                $caseQualityList[$jzhm] = [];
            }
            $caseQualityList[$jzhm][] = $item;
        }

        // 获取所有首页缺陷
        $homeQualityData = HomeQuality::query()
            ->whereIn('ZYH', $jzhmList)
            ->where('is_del', 0)
            ->get(['ZYH', 'error_rule']);

        // 按ZYH分组
        $homeQualityList = [];
        foreach ($homeQualityData as $item) {
            $zyh = $item->ZYH;
            if (!isset($homeQualityList[$zyh])) {
                $homeQualityList[$zyh] = [];
            }
            $homeQualityList[$zyh][] = $item;
        }

        // 按医师分组统计
        $doctorStats = [];
        foreach ($patients as $patient) {
            $doctorCode = $patient->AEE04_CODE;
            $depId = $patient->AAC02C;
            $jzhm = $patient->MED_REC_ID;

            // 初始化医师统计
            if (!isset($doctorStats[$doctorCode])) {
                $doctorStats[$doctorCode] = [
                    'doctor_code' => $doctorCode,
                    'doctor_name' => $doctors[$doctorCode] ?? '未知医师',
                    'dep_id' => $depId,
                    'dep_name' => $departments[$depId] ?? '未知科室',
                    'total' => 0,      // 质控病历总数
                    'defect' => 0,     // 缺陷病历数
                    'total_deduction' => 0,  // 总扣分
                    'total_score' => 0,      // 总分数（用于计算平均分）
                ];
            }

            $doctorStats[$doctorCode]['total']++;

            // 计算该患者的分数和扣分（只统计病历缺陷的扣分）
            $caseDeduction = 0; // 病历扣分（只统计病历缺陷）

            // 病历部分分数（100分满分）
            $caseScore = 100;
            if (isset($caseQualityList[$jzhm])) {
                foreach ($caseQualityList[$jzhm] as $defect) {
                    $ruleId = $defect->rule_id;
                    $deduction = 0;
                    if ($ruleId > 1000000) {
                        $settingId = $ruleId - 1000000;
                        $deduction = $ruleSettingMap[$settingId] ?? 0;
                    } else {
                        $deduction = $caseRuleMap[$ruleId] ?? 0;
                    }
                    $caseScore -= $deduction;
                    $caseDeduction += $deduction; // 只累加病历扣分
                }
            }
            $caseScore = max(0, $caseScore); // 确保不低于0

            // 统计缺陷病历（有病历缺陷或首页缺陷都算）
            if (isset($caseQualityList[$jzhm]) || isset($homeQualityList[$jzhm])) {
                $doctorStats[$doctorCode]['defect']++;
            }

            // 累加总扣分（只统计病历扣分）和总分数（只统计病历分数）
            $doctorStats[$doctorCode]['total_deduction'] += $caseDeduction;
            $doctorStats[$doctorCode]['total_score'] += $caseScore;
        }

        // 计算平均分并转换为表格数据格式
        $rows = [];
        foreach ($doctorStats as $doctorCode => $stat) {
            $total = $stat['total'];
            $defect = $stat['defect'];
            $totalDeduction = $stat['total_deduction'];
            $avgScore = $total > 0 ? sprintf('%.2f', $stat['total_score'] / $total) : '0.00';

            $rows[] = [
                'doctor_code' => $doctorCode,
                'doctor_name' => $stat['doctor_name'],
                'dep_name' => $stat['dep_name'],
                'total' => $total,
                'defect' => $defect,
                'total_deduction' => $totalDeduction,
                'avg_score' => $avgScore,
            ];
        }

        // 按平均分降序排序（平均分越高越好）
        usort($rows, function ($a, $b) {
            $scoreA = (float)$a['avg_score'];
            $scoreB = (float)$b['avg_score'];
            return $scoreB <=> $scoreA; // 降序
        });

        // 取前5名
        $topFive = array_slice($rows, 0, $limit);

        // 转换为表格数据格式
        $result = [];
        $index = 1;
        foreach ($topFive as $item) {
            $result[] = [
                'ysblzlzyid' => $index,
                'ysblzlys' => $item['doctor_name'],
                'ysblzlks' => $item['dep_name'],
                'ysblzlnum' => (string)$item['total'],
                'ysblzlqx' => (string)$item['defect'],
                'ysblzlkf' => sprintf('%.2f', $item['total_deduction']),
                'ysblzlpjf' => $item['avg_score'],
            ];
            $index++;
        }

        return $result;
    }

    /**
     * 获取医师病历质量最优前5名图表数据
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @return array
     */
    private function getDoctorCaseQualityTopFiveChartData($startTime, $endTime)
    {
        // 获取最优前5名数据
        $topFiveData = $this->getDoctorCaseQualityTopFiveData($startTime, $endTime, 5);

        if (empty($topFiveData)) {
            return [];
        }

        // 转换为图表数据格式
        $chartData = [];
        foreach ($topFiveData as $item) {
            $chartData[$item['ysblzlys']] = [
                'total' => (int)$item['ysblzlnum'],
                'defect' => (int)$item['ysblzlqx'],
            ];
        }

        return $chartData;
    }

    /**
     * 获取医师病历质量最差前5名数据
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param int $limit 返回条数限制，默认5
     * @return array
     */
    private function getDoctorCaseQualityBottomFiveData($startTime, $endTime, $limit = 5)
    {
        // 获取该时间范围内的所有患者，包含医师和科室信息
        $patients = PatientInfo::query()
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->whereNotNull('AEE04_CODE')
            ->where('AEE04_CODE', '!=', '')
            ->whereNotNull('AAC02C')
            ->where('AAC02C', '!=', '')
            ->get(['MED_REC_ID', 'AEE04_CODE', 'AAC02C']);

        if ($patients->isEmpty()) {
            return [];
        }

        // 获取所有医师信息
        $doctorCodes = $patients->pluck('AEE04_CODE')->unique()->toArray();
        $doctors = Staff::query()
            ->whereIn('code', $doctorCodes)
            ->pluck('name', 'code')
            ->toArray();

        // 获取所有科室信息
        $departments = Department::query()
            ->pluck('dep_name', 'dep_id')
            ->toArray();

        // 批量获取病历缺陷规则分数
        $caseRuleMap = CaseRule::query()
            ->where('status', 0)
            ->pluck('score', 'id')
            ->toArray();

        // 批量获取自定义规则分数
        $ruleSettingMap = RuleSetting::query()
            ->where('status', 1)
            ->pluck('score', 'id')
            ->toArray();

        // 批量获取首页缺陷规则分数
        $errorRuleMap = ErrorRule::query()
            ->where('status', 0)
            ->pluck('down', 'id')
            ->toArray();

        // 获取所有病历缺陷
        $jzhmList = $patients->pluck('MED_REC_ID')->toArray();
        $caseQualityData = CaseQuality::query()
            ->whereIn('JZHM', $jzhmList)
            ->get(['JZHM', 'rule_id']);

        // 按JZHM分组
        $caseQualityList = [];
        foreach ($caseQualityData as $item) {
            $jzhm = $item->JZHM;
            if (!isset($caseQualityList[$jzhm])) {
                $caseQualityList[$jzhm] = [];
            }
            $caseQualityList[$jzhm][] = $item;
        }

        // 获取所有首页缺陷
        $homeQualityData = HomeQuality::query()
            ->whereIn('ZYH', $jzhmList)
            ->where('is_del', 0)
            ->get(['ZYH', 'error_rule']);

        // 按ZYH分组
        $homeQualityList = [];
        foreach ($homeQualityData as $item) {
            $zyh = $item->ZYH;
            if (!isset($homeQualityList[$zyh])) {
                $homeQualityList[$zyh] = [];
            }
            $homeQualityList[$zyh][] = $item;
        }

        // 按医师分组统计
        $doctorStats = [];
        foreach ($patients as $patient) {
            $doctorCode = $patient->AEE04_CODE;
            $depId = $patient->AAC02C;
            $jzhm = $patient->MED_REC_ID;

            // 初始化医师统计
            if (!isset($doctorStats[$doctorCode])) {
                $doctorStats[$doctorCode] = [
                    'doctor_code' => $doctorCode,
                    'doctor_name' => $doctors[$doctorCode] ?? '未知医师',
                    'dep_id' => $depId,
                    'dep_name' => $departments[$depId] ?? '未知科室',
                    'total' => 0,      // 质控病历总数
                    'defect' => 0,     // 缺陷病历数
                    'total_deduction' => 0,  // 总扣分
                    'total_score' => 0,      // 总分数（用于计算平均分）
                ];
            }

            $doctorStats[$doctorCode]['total']++;

            // 计算该患者的分数和扣分（只统计病历缺陷的扣分）
            $caseDeduction = 0; // 病历扣分（只统计病历缺陷）

            // 病历部分分数（100分满分）
            $caseScore = 100;
            if (isset($caseQualityList[$jzhm])) {
                foreach ($caseQualityList[$jzhm] as $defect) {
                    $ruleId = $defect->rule_id;
                    $deduction = 0;
                    if ($ruleId > 1000000) {
                        $settingId = $ruleId - 1000000;
                        $deduction = $ruleSettingMap[$settingId] ?? 0;
                    } else {
                        $deduction = $caseRuleMap[$ruleId] ?? 0;
                    }
                    $caseScore -= $deduction;
                    $caseDeduction += $deduction; // 只累加病历扣分
                }
            }
            $caseScore = max(0, $caseScore); // 确保不低于0

            // 统计缺陷病历（有病历缺陷或首页缺陷都算）
            if (isset($caseQualityList[$jzhm]) || isset($homeQualityList[$jzhm])) {
                $doctorStats[$doctorCode]['defect']++;
            }

            // 累加总扣分（只统计病历扣分）和总分数（只统计病历分数）
            $doctorStats[$doctorCode]['total_deduction'] += $caseDeduction;
            $doctorStats[$doctorCode]['total_score'] += $caseScore;
        }

        // 计算平均分并转换为表格数据格式
        $rows = [];
        foreach ($doctorStats as $doctorCode => $stat) {
            $total = $stat['total'];
            $defect = $stat['defect'];
            $totalDeduction = $stat['total_deduction'];
            $avgScore = $total > 0 ? sprintf('%.2f', $stat['total_score'] / $total) : '0.00';

            $rows[] = [
                'doctor_code' => $doctorCode,
                'doctor_name' => $stat['doctor_name'],
                'dep_name' => $stat['dep_name'],
                'total' => $total,
                'defect' => $defect,
                'total_deduction' => $totalDeduction,
                'avg_score' => $avgScore,
            ];
        }

        // 按平均分升序排序（平均分越低越差）
        usort($rows, function ($a, $b) {
            $scoreA = (float)$a['avg_score'];
            $scoreB = (float)$b['avg_score'];
            return $scoreA <=> $scoreB; // 升序
        });

        // 取前5名（最差的5个）
        $bottomFive = array_slice($rows, 0, $limit);

        // 转换为表格数据格式
        $result = [];
        $index = 1;
        foreach ($bottomFive as $item) {
            $result[] = [
                'zcysblzlzyid' => $index,
                'zcysblzlys' => $item['doctor_name'],
                'zcysblzlks' => $item['dep_name'],
                'zcysblzlnum' => (string)$item['total'],
                'zcysblzlqx' => (string)$item['defect'],
                'zcysblzlkf' => sprintf('%.2f', $item['total_deduction']),
                'zcysblzlpjf' => $item['avg_score'],
            ];
            $index++;
        }

        return $result;
    }

    /**
     * 获取医师病历质量最差前5名图表数据
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @return array
     */
    private function getDoctorCaseQualityBottomFiveChartData($startTime, $endTime)
    {
        // 获取最差前5名数据
        $bottomFiveData = $this->getDoctorCaseQualityBottomFiveData($startTime, $endTime, 5);

        if (empty($bottomFiveData)) {
            return [];
        }

        // 转换为图表数据格式
        $chartData = [];
        foreach ($bottomFiveData as $item) {
            $chartData[$item['zcysblzlys']] = [
                'total' => (int)$item['zcysblzlnum'],
                'defect' => (int)$item['zcysblzlqx'],
            ];
        }

        return $chartData;
    }

    /**
     * 获取科室病案等级统计数据（从统计表）
     * @param string $time 时间参数
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getDepartmentCaseLevelStats($time, $depIds = [])
    {
        return $this->getDepartmentStatisticsFromStats($time, $depIds);
    }

    /**
     * 获取单否项缺陷前5名（从统计表）
     * @param string $time 时间参数
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getSingleNoDefectTop5($time, $depIds = [])
    {
        return $this->getSingleNoItemStatisticsFromStats($time, 5, $depIds);
    }

    /**
     * 获取所有单否项缺陷统计（从统计表）
     * @param string $time 时间参数
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getAllSingleNoDefectStats($time, $depIds = [])
    {
        return $this->getAllSingleNoItemDepartmentFromStats($time, $depIds);
    }

    /**
     * 获取申诉驳回最多的前5个问题（从统计表）
     * @param string $time 时间参数
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getAppealTop5Rejected($time, $depIds = [])
    {
        return $this->getAppealTopFiveFromStats($time, 5, $depIds);
    }

    /**
     * 获取按科室统计的申诉情况（从统计表）
     * @param string $time 时间参数
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getAppealStatsByDepartment($time, $depIds = [])
    {
        return $this->getAllAppealByDepartmentFromStats($time, $depIds);
    }

    /**
     * 获取指标达成率最优前5名（从统计表）
     * @param string $time 时间参数
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getIndicatorAchievementBest5($time, $depIds = [])
    {
        return $this->getIndicatorTopFiveFromStats($time, $depIds);
    }

    /**
     * 获取指标达成率最差前5名（从统计表）
     * @param string $time 时间参数
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getIndicatorAchievementWorst5($time, $depIds = [])
    {
        return $this->getIndicatorBottomFiveFromStats($time, $depIds);
    }

    /**
     * 获取病历质量最优前5名科室（从统计表）
     * @param string $time 时间参数
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getDepartmentRankingBest5($time, $depIds = [])
    {
        return $this->getCaseQualityTopFiveFromStats($time, 5, $depIds);
    }

    /**
     * 获取病历质量最差前5名科室（从统计表）
     * @param string $time 时间参数
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getDepartmentRankingWorst5($time, $depIds = [])
    {
        return $this->getCaseQualityBottomFiveFromStats($time, 5, $depIds);
    }

    /**
     * 获取所有科室病历质量排名（从统计表）
     * @param string $time 时间参数
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getDepartmentRankingAll($time, $depIds = [])
    {
        return $this->getAllCaseQualityRankingFromStats($time, $depIds);
    }

    /**
     * 获取医师病历质量最优前5名（从统计表）
     * @param string $time 时间参数
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getDoctorRankingBest5($time, $depIds = [])
    {
        return $this->getDoctorCaseQualityTopFiveFromStats($time, 5, $depIds);
    }

    /**
     * 获取医师病历质量最差前5名（从统计表）
     * @param string $time 时间参数
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getDoctorRankingWorst5($time, $depIds = [])
    {
        return $this->getDoctorCaseQualityBottomFiveFromStats($time, 5, $depIds);
    }

    /**
     * 获取所有医师病历质量排名（从统计表）
     * @param string $time 时间参数
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getDoctorRankingAll($time, $depIds = [])
    {
        Log::info('[所有医师排名] 开始查询', ['time' => $time, 'dep_ids' => $depIds]);

        // 解析时间，判断是否需要汇总多个月份
        $months = $this->parseTimeToMonths($time);

        // 从统计表查询医师数据
        $doctorStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_DOCTOR, [
            'columns' => ['id', 'dimension_id', 'dimension_name', 'total_cases', 'defect_cases', 'avg_score', 'extra_data'],
        ]);

        // 如果指定了科室筛选，需要从 extra_data 中筛选
        if (!empty($depIds)) {
            $doctorStats = $doctorStats->filter(function ($stat) use ($depIds) {
                $extraData = $this->getStatisticsExtraData($stat);
                $deptId = $extraData['department_id'] ?? null;
                return in_array($deptId, $depIds);
            });
        }

        // 按医师ID汇总数据
        $doctorAggregated = [];
        foreach ($doctorStats as $stat) {
            $doctorId = $stat->dimension_id;

            if (!isset($doctorAggregated[$doctorId])) {
                $extraData = $this->getStatisticsExtraData($stat);
                $doctorAggregated[$doctorId] = [
                    'dimension_id' => $doctorId,
                    'dimension_name' => $stat->dimension_name,
                    'total_cases' => 0,
                    'defect_cases' => 0,
                    'total_score' => 0,
                    'total_deduction' => 0,
                    'department_id' => $extraData['department_id'] ?? null,
                    'department_name' => $extraData['department_name'] ?? '未知科室',
                ];
            }

            $extraData = $this->getStatisticsExtraData($stat);
            $doctorAggregated[$doctorId]['total_cases'] += $stat->total_cases;
            $doctorAggregated[$doctorId]['defect_cases'] += $stat->defect_cases;
            $doctorAggregated[$doctorId]['total_score'] += $stat->avg_score * $stat->total_cases; // 累加总分
            $doctorAggregated[$doctorId]['total_deduction'] += $extraData['total_deduction'] ?? 0;
        }

        // 重新计算平均分并转换为数组
        $rows = [];
        foreach ($doctorAggregated as $stat) {
            if ($stat['total_cases'] <= 0) {
                continue;
            }

            $avgScore = $stat['total_score'] / $stat['total_cases'];

            $rows[] = [
                'dimension_id' => $stat['dimension_id'],
                'dimension_name' => $stat['dimension_name'],
                'total_cases' => $stat['total_cases'],
                'defect_cases' => $stat['defect_cases'],
                'avg_score' => $avgScore,
                'total_deduction' => $stat['total_deduction'],
                'department_id' => $stat['department_id'],
                'department_name' => $stat['department_name'],
            ];
        }

        // 按平均分降序排序（平均分越高越好）
        usort($rows, function ($a, $b) {
            return $b['avg_score'] <=> $a['avg_score'];
        });

        // 格式化返回数据（返回所有医师）
        $formattedRows = [];
        $index = 1;

        foreach ($rows as $row) {
            $formattedRows[] = [
                'rank' => $index,
                'doctor_code' => $row['dimension_id'],
                'doctor_name' => $row['dimension_name'],
                'department_id' => $row['department_id'],
                'department_name' => $row['department_name'],
                'total_cases' => (int)$row['total_cases'],
                'defect_cases' => (int)$row['defect_cases'],
                'total_deduction' => number_format($row['total_deduction'], 2),
                'avg_score' => number_format($row['avg_score'], 2),
            ];
            $index++;
        }

        Log::info('[所有医师排名] 查询完成', ['count' => count($formattedRows)]);

        return $formattedRows;
    }

    /**
     * 获取缺陷类型分布（按type字段分组）
     * @param string $time 时间参数
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getDefectTypeDistribution($time, $depIds = [])
    {
        Log::info('[缺陷类型分布] 开始查询', ['time' => $time, 'dep_ids' => $depIds]);

        // 解析时间，判断是否需要汇总多个月份
        $months = $this->parseTimeToMonths($time);

        // 根据是否有科室筛选，选择不同的统计类型
        if (!empty($depIds)) {
            // 有科室筛选：使用 rule_department 类型
            $allRuleStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_RULE_DEPARTMENT, [
                'rule_case_count_gt_zero' => true,
            ])->filter(function ($stat) use ($depIds) {
                $extraData = $this->getStatisticsExtraData($stat);
                $deptId = $extraData['department_id'] ?? null;
                return in_array($deptId, $depIds);
            });
        } else {
            // 无科室筛选：使用 rule 类型
            $allRuleStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_RULE, [
                'rule_case_count_gt_zero' => true,
            ]);
        }

        // 需要从 CaseRule 和 RuleSetting 表获取规则类型信息
        $caseRuleTypes = CaseRule::query()
            ->pluck('type', 'id')
            ->toArray();

        $ruleSettingTypes = RuleSetting::query()
            ->pluck('type', 'id')
            ->toArray();

        // 按type分组统计
        $typeStats = [];

        foreach ($allRuleStats as $stat) {
            // 解析 extra_data，只要病历规则
            $extraData = $this->getStatisticsExtraData($stat);
            $ruleSource = $extraData['rule_type'] ?? $extraData['rule_source'] ?? '';

            if ($ruleSource !== 'case') {
                continue; // 跳过首页规则
            }

            // 获取规则ID
            $ruleId = !empty($depIds) ? ($extraData['rule_id'] ?? $stat->dimension_id) : $stat->dimension_id;

            // 获取规则类型
            $ruleType = '';
            if ($ruleId < 1000000) {
                $ruleType = $caseRuleTypes[$ruleId] ?? '其他问题';
            } else {
                $settingId = $ruleId - 1000000;
                $ruleType = $ruleSettingTypes[$settingId] ?? '其他问题';
            }

            // 如果type为空，归类为"其他问题"
            if (empty($ruleType)) {
                $ruleType = '其他问题';
            }

            // 累加到对应类型
            if (!isset($typeStats[$ruleType])) {
                $typeStats[$ruleType] = 0;
            }
            $typeStats[$ruleType] += $stat->rule_case_count;
        }

        // 计算总数
        $totalCount = array_sum($typeStats);

        // 转换为数组格式
        $result = [];
        foreach ($typeStats as $type => $count) {
            $ratio = $totalCount > 0 ? ($count / $totalCount) * 100 : 0;
            $result[] = [
                'type' => $type,
                'count' => (int)$count,
                'ratio' => round($ratio, 2),
            ];
        }

        // 按数量降序排序
        usort($result, function ($a, $b) {
            return $b['count'] - $a['count'];
        });

        Log::info('[缺陷类型分布] 查询完成', ['type_count' => count($result)]);

        return $result;
    }

    /**
     * 获取缺陷问题TOP10（按缺陷数量排序）
     * @param string $time 时间参数
     * @param array $depIds 科室ID数组，用于筛选
     * @return array
     */
    private function getDefectTop10($time, $depIds = [])
    {
        Log::info('[缺陷TOP10] 开始查询', ['time' => $time, 'dep_ids' => $depIds]);

        // 解析时间，判断是否需要汇总多个月份
        $months = $this->parseTimeToMonths($time);

        // 根据是否有科室筛选，选择不同的统计类型
        if (!empty($depIds)) {
            // 有科室筛选：使用 rule_department 类型
            $allRuleStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_RULE_DEPARTMENT, [
                'rule_case_count_gt_zero' => true,
            ])->filter(function ($stat) use ($depIds) {
                $extraData = $this->getStatisticsExtraData($stat);
                $deptId = $extraData['department_id'] ?? null;
                return in_array($deptId, $depIds);
            });
        } else {
            // 无科室筛选：使用 rule 类型
            $allRuleStats = $this->getCachedStatisticsRows($months, QualityReportStatistics::TYPE_RULE, [
                'rule_case_count_gt_zero' => true,
            ]);
        }

        // 按规则ID汇总数据
        $ruleAggregated = [];
        foreach ($allRuleStats as $stat) {
            // 解析 extra_data，只要病历规则
            $extraData = $this->getStatisticsExtraData($stat);
            $ruleSource = $extraData['rule_type'] ?? $extraData['rule_source'] ?? '';

            if ($ruleSource !== 'case') {
                continue; // 跳过首页规则
            }

            // 对于 rule_department 类型，需要从 extra_data 获取 rule_id
            $ruleId = !empty($depIds) ? ($extraData['rule_id'] ?? $stat->dimension_id) : $stat->dimension_id;

            if (!isset($ruleAggregated[$ruleId])) {
                $ruleName = !empty($depIds) ? ($extraData['rule_name'] ?? $stat->dimension_name) : $stat->dimension_name;
                $ruleAggregated[$ruleId] = [
                    'rule_id' => $ruleId,
                    'rule_name' => $ruleName,
                    'defect_count' => 0,
                ];
            }

            $ruleAggregated[$ruleId]['defect_count'] += $stat->rule_case_count;
        }

        // 转换为数组并按缺陷数量降序排序
        $result = array_values($ruleAggregated);
        usort($result, function ($a, $b) {
            return $b['defect_count'] - $a['defect_count'];
        });

        // 只取前10名
        $top10 = array_slice($result, 0, 10);

        // 获取总质控病历数（用于计算占比）
        $totalQualityCases = $this->getCachedTotalCases($months, $depIds);

        // 格式化返回数据
        $formattedResult = [];
        foreach ($top10 as $index => $item) {
            $ratio = $totalQualityCases > 0 ? ($item['defect_count'] / $totalQualityCases) * 100 : 0;
            $formattedResult[] = [
                'rank' => $index + 1,
                'rule_id' => (int)$item['rule_id'],
                'rule_name' => $item['rule_name'],
                'quality_cases' => (int)$totalQualityCases,
                'defect_cases' => (int)$item['defect_count'],
                'defect_count' => (int)$item['defect_count'],
                'ratio' => round($ratio, 2),
            ];
        }

        Log::info('[缺陷TOP10] 查询完成', ['count' => count($formattedResult)]);

        return $formattedResult;
    }

    private function getRuleName($ruleId)
    {
        if ($ruleId < 1000000) {
            $rule = CaseRule::find($ruleId);
            return $rule ? $rule->notice : '未知规则';
        } else {
            $settingId = $ruleId - 1000000;
            $rule = RuleSetting::find($settingId);
            return $rule ? $rule->description : '未知规则';
        }
    }
}
