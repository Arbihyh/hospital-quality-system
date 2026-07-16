<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;


use App\Model\BA_SYSB_ZDDZ;
use App\Model\Department;
use App\Model\IndexCatalog;
use App\Model\Indicator;
use App\Model\ManLog;
use App\Model\Staff;
use App\Model\User;
use App\Services\CsvService;
use App\Services\ElasticsearchService;
use App\Services\QualityIndexService;
use App\Services\QualityIndexRecalculationService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use App\Model\PatientInfo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

class QualityIndexController extends Controller
{
    /**
     * 获取真实客户端IP地址
     * 优先从HTTP头中获取，适用于代理/负载均衡器场景
     * @param Request $request
     * @return string
     */
    private function getRealIp(Request $request)
    {
        // 优先从 X-Forwarded-For 获取（可能包含多个IP，取第一个）
        $xForwardedFor = $request->header('X-Forwarded-For');
        if (!empty($xForwardedFor)) {
            $ips = explode(',', $xForwardedFor);
            $ip = trim($ips[0]);
            if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                if ($ip !== '127.0.0.1' && $ip !== '::1') {
                    return $ip;
                }
            }
        }

        // 从 X-Real-IP 获取
        $xRealIp = $request->header('X-Real-IP');
        if (!empty($xRealIp) && filter_var($xRealIp, FILTER_VALIDATE_IP)) {
            if ($xRealIp !== '127.0.0.1' && $xRealIp !== '::1') {
                return $xRealIp;
            }
        }

        // 尝试从 REMOTE_ADDR 获取
        $remoteAddr = $request->server('REMOTE_ADDR');
        if (!empty($remoteAddr) && filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
            if ($remoteAddr !== '127.0.0.1' && $remoteAddr !== '::1') {
                return $remoteAddr;
            }
        }

        // 最后使用 Laravel 的 getClientIp 方法
        $ip = $request->getClientIp();
        if (!empty($ip) && $ip !== '127.0.0.1' && $ip !== '::1') {
            return $ip;
        }

        return $remoteAddr ?: ($ip ?: '127.0.0.1');
    }

    /**
     * 记录每天第一次访问的日志
     * @param Request $request
     * @return void
     */
    private function logFirstAccessToday(Request $request)
    {
        try {
            // 获取token和用户信息
            $token = $request->header('token');
            if (empty($token)) {
                Log::info('记录运营日志: token为空');
                return;
            }

            // 优先尝试从 request->user() 获取（通过 CheckLogin 中间件设置）
            $userInfo = $request->user();

            // 如果 request->user() 为空，尝试从 Session 获取
            if (empty($userInfo)) {
                $userInfo = Session::get($token);
            }

            // 如果 Session 中也没有，尝试从数据库查询（通过 token）
            if (empty($userInfo)) {
                $userInfo = User::findWhereToken($token);
            }

            if (empty($userInfo) || !is_array($userInfo)) {
                Log::info('记录运营日志: 未找到用户信息', [
                    'token' => $token,
                    'userInfo' => $userInfo,
                    'request_user' => $request->user()
                ]);
                return;
            }

            $name = $userInfo['name'] ?? '';
            $realname = $userInfo['realname'] ?? '';
            if (empty($name)) {
                Log::info('记录运营日志: 用户名为空', ['userInfo' => $userInfo]);
                return;
            }

            // 检查今天是否已经记录过日志（使用 Session 记录）
            $today = date('Y-m-d');
            $logKey = 'getIndex_logged_' . $name . '_' . $today;

            // 如果今天已经记录过，直接返回
            if (Session::has($logKey)) {
                Log::info('记录运营日志: 今天已记录过', ['name' => $name, 'today' => $today]);
                return;
            }

            // 获取真实IP
            $loginIp = $this->getRealIp($request);

            // 记录日志
            $insertData = [
                'name' => $name,
                'realname' => $realname,
                'loginip' => $loginIp,
                'content' => '查看指标',
                'created_at' => date('Y-m-d H:i:s'),
            ];

            Log::info('记录运营日志: 准备插入', $insertData);

            // 尝试使用 Model 插入
            try {
                $result = ManLog::query()->insert($insertData);
                if ($result) {
                    Log::info('记录运营日志: 插入成功', $insertData);
                    // 标记今天已记录
                    Session::put($logKey, true);
                } else {
                    Log::error('记录运营日志: 插入失败，返回false', $insertData);
                    // 尝试使用 DB facade 作为备选方案
                    try {
                        DB::table('man_logs')->insert($insertData);
                        Log::info('记录运营日志: 使用DB facade插入成功', $insertData);
                        Session::put($logKey, true);
                    } catch (\Exception $dbException) {
                        Log::error('记录运营日志: DB facade插入也失败', [
                            'error' => $dbException->getMessage(),
                            'data' => $insertData
                        ]);
                    }
                }
            } catch (\Exception $modelException) {
                Log::error('记录运营日志: Model插入异常', [
                    'error' => $modelException->getMessage(),
                    'file' => $modelException->getFile(),
                    'line' => $modelException->getLine(),
                    'data' => $insertData
                ]);
                // 尝试使用 DB facade 作为备选方案
                try {
                    DB::table('man_logs')->insert($insertData);
                    Log::info('记录运营日志: 使用DB facade插入成功（Model失败后）', $insertData);
                    Session::put($logKey, true);
                } catch (\Exception $dbException) {
                    Log::error('记录运营日志: DB facade插入也失败', [
                        'error' => $dbException->getMessage(),
                        'data' => $insertData
                    ]);
                }
            }
        } catch (\Exception $e) {
            // 日志记录失败不影响接口功能
            Log::error('记录运营日志失败', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function getIndex(Request $request)
    {
        $year = $request->get('year'); //年

        $isExport = $request->get('is_export'); //是否导出
        $category = $request->get('category'); //类别
        $AAC11N = $request->get('AAC11N');
        $AEE03 = $request->get('AEE03');
        $cysj_start = $request->get('cysj_start');
        $cysj_end = $request->get('cysj_end');

        // 确定年份，如果没有传入则使用当前年份
        $year = $year ?: date("Y");
        $currentYear = (int) date("Y");
        $currentMonth = (int) date("m");

        // 先构建传入年份的空数据
        // 如果是当前年且当前月不到12月，只构建到当前月
        $maxMonth = 12;
        if ($year == $currentYear && $currentMonth < 12) {
            $maxMonth = $currentMonth;
        }

        $emptyData = [];
        for ($month = 1; $month <= $maxMonth; $month++) {
            $emptyData[$month] = [
                'fenzi' => 0,
                'fenmu' => 0,
                'radio' => 0,
                'month' => $month,
                'year' => $year,
                'status' => '',
                'update_time' => '--'
            ];
        }

        $indexCatalog = IndexCatalog::query()->where("url", "=", $category)->first();
        if (!$indexCatalog) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }
        $indexCatalog = is_array($indexCatalog) ? $indexCatalog : $indexCatalog->toArray();

        // 特殊处理 sjsjsssjkzl 指标
        if ($indexCatalog['index_name'] === 'sjsjsssjkzl') {
            // 处理时间范围
            if (!empty($cysj_start) && !empty($cysj_end)) {
                // 将 Ymd 格式转换为标准日期格式
                // 如果日期格式是 20250101，转换为 2025-01-01
                // 先转换为字符串，确保能正确计算长度
                $cysj_start_str = (string) $cysj_start;
                $cysj_end_str = (string) $cysj_end;

                if (strlen($cysj_start_str) == 8 && is_numeric($cysj_start_str)) {
                    $cysj_start = substr($cysj_start_str, 0, 4) . '-' . substr($cysj_start_str, 4, 2) . '-' . substr($cysj_start_str, 6, 2);
                }
                if (strlen($cysj_end_str) == 8 && is_numeric($cysj_end_str)) {
                    $cysj_end = substr($cysj_end_str, 0, 4) . '-' . substr($cysj_end_str, 4, 2) . '-' . substr($cysj_end_str, 6, 2);
                }

                $startYear = (int) date('Y', strtotime($cysj_start));
                $startMonth = (int) date('m', strtotime($cysj_start));
                $endYear = (int) date('Y', strtotime($cysj_end));
                $endMonth = (int) date('m', strtotime($cysj_end));

                // 如果查询的是当前年份，结束月份不能超过当前月
                if ($endYear == $currentYear && $endMonth > $currentMonth) {
                    $endMonth = $currentMonth;
                }

                Log::info('getIndex跨年查询参数', [
                    'cysj_start' => $cysj_start,
                    'cysj_end' => $cysj_end,
                    'startYear' => $startYear,
                    'startMonth' => $startMonth,
                    'endYear' => $endYear,
                    'endMonth' => $endMonth
                ]);
            } else {
                $startYear = $year ?: date("Y");
                $startMonth = 1;
                $endYear = $startYear;
                // 如果是当前年且当前月不到12月，只到当前月
                if ($startYear == $currentYear && $currentMonth < 12) {
                    $endMonth = $currentMonth;
                } else {
                    $endMonth = 12;
                }
            }

            // 生成所有需要查询的年月组合（支持跨年）
            $yearMonthList = [];

            // 使用更可靠的方法：从开始日期逐月递增到结束日期
            $currentDate = strtotime("$startYear-$startMonth-01");
            $endDate = strtotime("$endYear-$endMonth-01");

            while ($currentDate <= $endDate) {
                $currentYearLoop = (int) date('Y', $currentDate);
                $currentMonthLoop = (int) date('m', $currentDate);

                $yearMonthList[] = [
                    'year' => $currentYearLoop,
                    'month' => $currentMonthLoop,
                    'key' => sprintf('%04d-%02d', $currentYearLoop, $currentMonthLoop)
                ];

                // 移动到下一个月
                $currentDate = strtotime('+1 month', $currentDate);
            }

            Log::info('getIndex生成的年月列表', [
                'startYear' => $startYear,
                'startMonth' => $startMonth,
                'endYear' => $endYear,
                'endMonth' => $endMonth,
                'yearMonthList' => $yearMonthList,
                'count' => count($yearMonthList)
            ]);

            // 重新构建空数据以匹配查询的时间范围（支持跨年）
            $emptyData = [];
            foreach ($yearMonthList as $ym) {
                $key = $ym['key'];
                $emptyData[$key] = [
                    'fenzi' => 0,
                    'fenmu' => 0,
                    'radio' => 0,
                    'month' => $ym['month'],
                    'year' => $ym['year'],
                    'status' => '',
                    'update_time' => '--'
                ];
            }

            // 使用预先构建的空数据，并更新status和update_time
            $data = $emptyData;
            foreach ($data as $key => $value) {
                $data[$key]['status'] = $indexCatalog['status'] == 1 ? '质控中' : "成功";
                $data[$key]['update_time'] = $indexCatalog['quality_time'] ? date("Y-m-d", $indexCatalog['quality_time']) : '--';
            }

            // 优化：批量查询所有月份的数据，减少数据库往返次数
            // 1. 批量获取所有月份的 surgical_catalog 数据（按月份分组）
            $yearMonthPatterns = array_column($yearMonthList, 'key');

            // 优化：按月份分别查询 surgical_catalog，避免多个 LIKE 导致全表扫描
            // 虽然会增加查询次数，但每个查询可以使用更精确的条件，减少扫描范围
            $surgicalCatalogByMonth = [];
            foreach ($yearMonthPatterns as $pattern) {
                // 使用 chunk 分批处理，避免一次性加载大量数据到内存
                DB::table('surgical_catalog')
                    // ->where('CYSJ', 'like', '%' . $pattern . '%')
                    ->select('CYSJ', 'ICD9_NAME')
                    ->orderBy('id')
                    ->chunk(1000, function ($items) use (&$surgicalCatalogByMonth, $pattern) {
                        foreach ($items as $item) {
                            if (!isset($surgicalCatalogByMonth[$pattern])) {
                                $surgicalCatalogByMonth[$pattern] = [];
                            }
                            // 使用数组存储，避免重复
                            if (!isset($surgicalCatalogByMonth[$pattern][$item->ICD9_NAME])) {
                                $surgicalCatalogByMonth[$pattern][$item->ICD9_NAME] = true;
                            }
                        }
                    });
            }

            // 2. 批量获取所有月份的主手术和次手术数据（支持跨年）
            $queryStart = date('Y-m-01 00:00:00', strtotime("$startYear-$startMonth-01"));
            $queryEnd = date('Y-m-t 23:59:59', strtotime("$endYear-$endMonth-01"));

            // 构建主手术查询（一次性查询所有月份）
            // 优化：使用 chunk 分批处理，避免一次性加载大量数据
            $allMainOperations = collect();
            $mainOperationsQuery = DB::table('main_operation as mo')
                ->join('patient_info as pi', 'mo.AAA28', '=', 'pi.MED_REC_ID')
                ->whereBetween('pi.AAC01', [$queryStart, $queryEnd])
                ->whereIn('mo.OPE_LEVEL', [3, 4]);

            if (!empty($AAC11N)) {
                $mainOperationsQuery->where('pi.AAC11N', $AAC11N);
            }
            if (!empty($AEE03)) {
                $mainOperationsQuery->where('pi.AEE03', $AEE03);
            }

            // 使用 chunk 分批获取数据，减少内存占用
            $mainOperationsQuery->select('mo.ICD9_NAME', 'pi.AAC01')
                ->orderBy('pi.AAC01')
                ->chunk(5000, function ($items) use (&$allMainOperations) {
                    $allMainOperations = $allMainOperations->merge($items);
                });

            // 构建次手术查询（一次性查询所有月份）
            // 优化：使用 chunk 分批处理，避免一次性加载大量数据
            $allSecondaryOperations = collect();
            $secondaryOperationsQuery = DB::table('secondary_operation as so')
                ->join('patient_info as pi', 'so.AAA28', '=', 'pi.MED_REC_ID')
                ->whereBetween('pi.AAC01', [$queryStart, $queryEnd])
                ->whereIn('so.OPE_LEVEL', [3, 4]);

            if (!empty($AAC11N)) {
                $secondaryOperationsQuery->where('pi.AAC11N', $AAC11N);
            }
            if (!empty($AEE03)) {
                $secondaryOperationsQuery->where('pi.AEE03', $AEE03);
            }

            // 使用 chunk 分批获取数据，减少内存占用
            $secondaryOperationsQuery->select('so.ICD9_NAME', 'pi.AAC01')
                ->orderBy('pi.AAC01')
                ->chunk(5000, function ($items) use (&$allSecondaryOperations) {
                    $allSecondaryOperations = $allSecondaryOperations->merge($items);
                });

            // 3. 在内存中按月份分组并计算（支持跨年）
            // 优化：预先计算每个月的开始和结束时间戳，避免在循环中重复计算
            $monthTimeRanges = [];
            foreach ($yearMonthList as $ym) {
                $year = $ym['year'];
                $month = $ym['month'];
                $key = $ym['key'];
                $monthTimeRanges[$key] = [
                    'start' => strtotime("$year-$month-01 00:00:00"),
                    'end' => strtotime("$year-$month-" . date('t', strtotime("$year-$month-01")) . " 23:59:59"),
                    'str' => $key,
                    'year' => $year,
                    'month' => $month
                ];
            }

            foreach ($yearMonthList as $ym) {
                $key = $ym['key'];
                $monthStr = $monthTimeRanges[$key]['str'];
                $monthStartTs = $monthTimeRanges[$key]['start'];
                $monthEndTs = $monthTimeRanges[$key]['end'];
                $year = $monthTimeRanges[$key]['year'];
                $month = $monthTimeRanges[$key]['month'];

                // 计算分母：该月份的 surgical_catalog 数量
                $fenmu = isset($surgicalCatalogByMonth[$monthStr]) ? count($surgicalCatalogByMonth[$monthStr]) : 0;

                // 筛选该月份的主手术和次手术（优化：直接比较时间戳，避免重复调用 strtotime）
                $monthMainOperations = $allMainOperations->filter(function ($item) use ($monthStartTs, $monthEndTs) {
                    $aac01Ts = strtotime($item->AAC01);
                    return $aac01Ts >= $monthStartTs && $aac01Ts <= $monthEndTs;
                });

                $monthSecondaryOperations = $allSecondaryOperations->filter(function ($item) use ($monthStartTs, $monthEndTs) {
                    $aac01Ts = strtotime($item->AAC01);
                    return $aac01Ts >= $monthStartTs && $aac01Ts <= $monthEndTs;
                });

                // 合并并按照 ICD9_NAME 分组
                $allOperations = $monthMainOperations->merge($monthSecondaryOperations);
                $groupedOperations = $allOperations->groupBy('ICD9_NAME');

                // 获取该月份 surgical_catalog 中存在的 ICD9_NAME 列表
                $surgicalCatalogNames = isset($surgicalCatalogByMonth[$monthStr])
                    ? array_keys($surgicalCatalogByMonth[$monthStr])
                    : [];

                // 计算分子：在 surgical_catalog 的 ICD9_NAME 存在的算作分子
                $fenzi = 0;
                foreach ($groupedOperations as $icd9Name => $operations) {
                    if (in_array($icd9Name, $surgicalCatalogNames)) {
                        $fenzi += $operations->count();
                    }
                }

                $fenmu = round(floatval($fenmu), 4);
                $fenzi = round(floatval($fenzi), 4);

                $data[$key] = [
                    'fenzi' => $fenzi,
                    'fenmu' => $fenmu,
                    'radio' => $fenmu > 0 ? number_format(($fenzi / $fenmu) * 100, 2) : 0,
                    'month' => $month,
                    'year' => $year,
                    'status' => $indexCatalog['status'] == 1 ? '质控中' : "成功",
                    'update_time' => $indexCatalog['quality_time'] ? date("Y-m-d", $indexCatalog['quality_time']) : '--'
                ];
            }

            // 按年月顺序排序并转换为数组（保持顺序）
            $filteredData = [];
            foreach ($yearMonthList as $ym) {
                $key = $ym['key'];
                if (isset($data[$key])) {
                    $filteredData[] = $data[$key];
                } else {
                    Log::warning('getIndex数据缺失', [
                        'key' => $key,
                        'year' => $ym['year'],
                        'month' => $ym['month'],
                        'data_keys' => array_keys($data)
                    ]);
                }
            }

            Log::info('getIndex最终返回数据', [
                'filteredData_count' => count($filteredData),
                'yearMonthList_count' => count($yearMonthList),
                'data_keys' => array_keys($data),
                'filteredData' => $filteredData
            ]);

            $resData = $filteredData;



            //保留小数点后四位
            $fenzi = round(array_sum(array_column($resData, 'fenzi')), 4);
            $fenmu = round(array_sum(array_column($resData, 'fenmu')), 4);
            $r = $fenmu > 0 ? round($fenzi / $fenmu, 4) * 100 : 0;
            $r = number_format($r, 2);
            $count = [
                'fenzi' => $fenzi,
                'fenmu' => $fenmu,
                'radio' => $r,
                'month' => '',
                'year' => '平均值',
                'status' => $indexCatalog['status'] == 1 ? '质控中' : "成功",
                'update_time' => $indexCatalog['quality_time'] ? date("Y-m-d", $indexCatalog['quality_time']) : '--'
            ];
            $resData[] = $count;



            if ($isExport) {
                $exportData[] = ['时间', $this->getIndicatorName($indexCatalog['index_name']) . '分子', $this->getIndicatorName($indexCatalog['index_name']) . '分母', '指标合格率'];
                foreach ($resData as $key => $val) {
                    // 在 yearStr 前加上单引号
                    if ($val['month']) {
                        $yearStr = sprintf("\t%04d/%02d", $val['year'], $val['month']);
                    } else {
                        $yearStr = '平均值';
                    }
                    $exportData[$key + 1] = [
                        $yearStr,
                        $val["fenzi"],
                        $val["fenmu"],
                        $val["radio"] . "%",
                    ];
                }

                $csv = new CsvService();
                $csv->filename = $csv->charset($indexCatalog["name"] . '--指标', 'UTF-8');
                return $csv->export($exportData);
            }

            // 记录每天第一次访问的日志
            $this->logFirstAccessToday($request);

            return ToolsService::returnData(200, $resData, $msg ?? '');
        } else {
            // 判断是否为特殊指标
            $isSpecialIndex = in_array($indexCatalog['index_name'], ['sjssysjssbfzfs', 'sjssysjssswlb']);

            // 使用 MySQL 查询（indicator 表字段结构与原 ES 索引一致）
            $mysqlQuery = DB::table('indicator');

            // 处理时间范围（支持跨年）
            $yearMonthList = [];
            if (!empty($cysj_start) && !empty($cysj_end)) {
                // 将 Ymd 格式转换为标准日期格式
                $cysj_start_str = (string) $cysj_start;
                $cysj_end_str = (string) $cysj_end;

                if (strlen($cysj_start_str) == 8 && is_numeric($cysj_start_str)) {
                    $cysj_start = substr($cysj_start_str, 0, 4) . '-' . substr($cysj_start_str, 4, 2) . '-' . substr($cysj_start_str, 6, 2);
                }
                if (strlen($cysj_end_str) == 8 && is_numeric($cysj_end_str)) {
                    $cysj_end = substr($cysj_end_str, 0, 4) . '-' . substr($cysj_end_str, 4, 2) . '-' . substr($cysj_end_str, 6, 2);
                }

                // 将 cysj_start 转换为该月的第一天
                $startDate = date('Y-m-01 00:00:00', strtotime($cysj_start));
                // 将 cysj_end 转换为该月的最后一天
                $endDate = date('Y-m-t 23:59:59', strtotime($cysj_end));

                // MySQL 时间范围查询（按出院时间 AAC01 过滤）
                $mysqlQuery->whereBetween('AAC01', [$startDate, $endDate]);

                // 提取开始和结束的年份、月份
                $startYear = (int) date('Y', strtotime($cysj_start));
                $startMonth = (int) date('m', strtotime($cysj_start));
                $endYear = (int) date('Y', strtotime($cysj_end));
                $endMonth = (int) date('m', strtotime($cysj_end));

                // 如果查询的是当前年份，结束月份不能超过当前月
                if ($endYear == $currentYear && $endMonth > $currentMonth) {
                    $endMonth = $currentMonth;
                }

                // 生成所有需要查询的年月组合（支持跨年）
                $currentDate = strtotime("$startYear-$startMonth-01");
                $endDateTs = strtotime("$endYear-$endMonth-01");

                while ($currentDate <= $endDateTs) {
                    $currentYearLoop = (int) date('Y', $currentDate);
                    $currentMonthLoop = (int) date('m', $currentDate);

                    $yearMonthList[] = [
                        'year' => $currentYearLoop,
                        'month' => $currentMonthLoop,
                        'key' => sprintf('%04d-%02d', $currentYearLoop, $currentMonthLoop)
                    ];

                    // 移动到下一个月
                    $currentDate = strtotime('+1 month', $currentDate);
                }

                Log::info('getIndex通用指标跨年查询参数', [
                    'cysj_start' => $cysj_start,
                    'cysj_end' => $cysj_end,
                    'startYear' => $startYear,
                    'startMonth' => $startMonth,
                    'endYear' => $endYear,
                    'endMonth' => $endMonth,
                    'yearMonthList_count' => count($yearMonthList)
                ]);
            } else {
                // 如果没有时间范围，则使用全年
                $year = $year ?: date("Y");
                $mysqlQuery->where('AAC01_YEAR', (string)$year);

                // 如果是当前年且当前月不到12月，只到当前月
                $startMonth = 1;
                if ($year == $currentYear && $currentMonth < 12) {
                    $endMonth = $currentMonth;
                } else {
                    $endMonth = 12;
                }

                // 生成单年的年月列表
                for ($month = $startMonth; $month <= $endMonth; $month++) {
                    $yearMonthList[] = [
                        'year' => $year,
                        'month' => $month,
                        'key' => sprintf('%04d-%02d', $year, $month)
                    ];
                }
            }

            // 重新构建空数据以匹配查询的时间范围（支持跨年）
            $emptyData = [];
            foreach ($yearMonthList as $ym) {
                $key = $ym['key'];
                $emptyData[$key] = [
                    'fenzi' => 0,
                    'fenmu' => 0,
                    'radio' => 0,
                    'month' => $ym['month'],
                    'year' => $ym['year'],
                    'status' => '',
                    'update_time' => '--'
                ];
            }

            // 添加科室和医师筛选条件
            if (!empty($AAC11N)) {
                $mysqlQuery->where('AAC11N', $AAC11N);
            }
            if (!empty($AEE03)) {
                $mysqlQuery->where('AEE03', $AEE03);
            }

            // 构建 MySQL 分组聚合查询（支持跨年）
            // 按 AAC01_YEAR、AAC01_MONTH 分组，聚合对应指标的分子分母字段
            $mysqlQuery->select('AAC01_YEAR', 'AAC01_MONTH')
                ->groupBy('AAC01_YEAR', 'AAC01_MONTH');

            // 根据指标类型添加不同的聚合字段
            if ($isSpecialIndex) {
                if ($indexCatalog['index_name'] === 'sjssysjssbfzfs') {
                    $mysqlQuery->selectRaw('SUM(sjssbfz_fm) as sjssbfz_fm_sum')
                        ->selectRaw('SUM(sjssbfz_fz) as sjssbfz_fz_sum')
                        ->selectRaw('SUM(sijssbfz_fm) as sijssbfz_fm_sum')
                        ->selectRaw('SUM(sijssbfz_fz) as sijssbfz_fz_sum');
                } else {
                    $mysqlQuery->selectRaw('SUM(sjsssw_fm) as sjsssw_fm_sum')
                        ->selectRaw('SUM(sjsssw_fz) as sjsssw_fz_sum')
                        ->selectRaw('SUM(sijsssw_fm) as sijsssw_fm_sum')
                        ->selectRaw('SUM(sijsssw_fz) as sijsssw_fz_sum');
                }
            } else {
                // 普通指标：直接累加对应的 _fm / _fz 字段
                $fmField = $indexCatalog['index_name'] . '_fm';
                $fzField = $indexCatalog['index_name'] . '_fz';
                $mysqlQuery->selectRaw("SUM(`{$fmField}`) as fenmu")
                    ->selectRaw("SUM(`{$fzField}`) as fenzi");
            }

            // 执行 MySQL 查询
            $aggRows = $mysqlQuery->get();

            Log::info('getIndex请求参数9：--------------------------' . date("Y-m-d H:i:s"));

            // 使用预先构建的空数据，并更新status和update_time
            $data = $emptyData;
            foreach ($data as $key => $value) {
                $data[$key]['status'] = $indexCatalog['status'] == 1 ? '质控中' : "成功";
                $data[$key]['update_time'] = $indexCatalog['quality_time'] ? date("Y-m-d", $indexCatalog['quality_time']) : '--';
            }

            // 解析 MySQL 聚合结果（支持跨年）
            foreach ($aggRows as $row) {
                $bucketYear = intval($row->AAC01_YEAR);
                $month = intval($row->AAC01_MONTH);
                $key = sprintf('%04d-%02d', $bucketYear, $month);

                // 只处理在查询范围内的年月
                if (!isset($emptyData[$key])) {
                    continue;
                }

                // 根据指标类型计算分子分母
                if ($isSpecialIndex) {
                    if ($indexCatalog['index_name'] === 'sjssysjssbfzfs') {
                        $sjssbfz_fm_sum = $row->sjssbfz_fm_sum ?? 0;
                        $sjssbfz_fz_sum = $row->sjssbfz_fz_sum ?? 0;
                        $sijssbfz_fm_sum = $row->sijssbfz_fm_sum ?? 0;
                        $sijssbfz_fz_sum = $row->sijssbfz_fz_sum ?? 0;

                        // 分母 = 手术室手术时间比率
                        $fenmu = ($sjssbfz_fm_sum > 0) ? round($sjssbfz_fz_sum / $sjssbfz_fm_sum, 4) : 0;
                        // 分子 = 四级手术时间比率
                        $fenzi = ($sijssbfz_fm_sum > 0) ? round($sijssbfz_fz_sum / $sijssbfz_fm_sum, 4) : 0;
                    } else {
                        $sjsssw_fm_sum = $row->sjsssw_fm_sum ?? 0;
                        $sjsssw_fz_sum = $row->sjsssw_fz_sum ?? 0;
                        $sijsssw_fm_sum = $row->sijsssw_fm_sum ?? 0;
                        $sijsssw_fz_sum = $row->sijsssw_fz_sum ?? 0;

                        // 分母 = 手术室手术死亡率
                        $fenmu = ($sjsssw_fm_sum > 0) ? round($sjsssw_fz_sum / $sjsssw_fm_sum, 4) : 0;
                        // 分子 = 四级手术死亡率
                        $fenzi = ($sijsssw_fm_sum > 0) ? round($sijsssw_fz_sum / $sijsssw_fm_sum, 4) : 0;
                    }
                } else {
                    // 普通指标直接使用累加值
                    $fenmu = round(floatval($row->fenmu ?? 0), 4);
                    $fenzi = round(floatval($row->fenzi ?? 0), 4);
                }

                $data[$key] = [
                    'fenzi' => $fenzi,
                    'fenmu' => $fenmu,
                    'radio' => $fenmu > 0 ? number_format(($fenzi / $fenmu) * 100, 2) : 0,
                    'month' => $month,
                    'year' => $bucketYear,
                    'status' => $indexCatalog['status'] == 1 ? '质控中' : "成功",
                    'update_time' => $indexCatalog['quality_time'] ? date("Y-m-d", $indexCatalog['quality_time']) : '--'
                ];
            }

            // 按年月顺序排序并转换为数组（保持顺序，支持跨年）
            $filteredData = [];
            foreach ($yearMonthList as $ym) {
                $key = $ym['key'];
                if (isset($data[$key])) {
                    $filteredData[] = $data[$key];
                }
            }

            Log::info('getIndex通用指标最终返回数据', [
                'filteredData_count' => count($filteredData),
                'yearMonthList_count' => count($yearMonthList),
                'data_keys' => array_keys($data)
            ]);

            $resData = $filteredData;
            Log::info('getIndex请求参数10：--------------------------' . date("Y-m-d H:i:s"));

            //保留小数点后四位
            $fenzi = round(array_sum(array_column($resData, 'fenzi')), 4);
            $fenmu = round(array_sum(array_column($resData, 'fenmu')), 4);
            $r = $fenmu > 0 ? round($fenzi / $fenmu, 4) * 100 : 0;
            $r = number_format($r, 2);


            $count = [
                'fenzi' => $fenzi,
                'fenmu' => $fenmu,
                'radio' => $r,
                'month' => '',
                'year' => '平均值',
                'status' => $indexCatalog['status'] == 1 ? '质控中' : "成功",
                'update_time' => $indexCatalog['quality_time'] ? date("Y-m-d", $indexCatalog['quality_time']) : '--'
            ];
            $resData[] = $count;

            Log::info('getIndex请求参数11：--------------------------' . date("Y-m-d H:i:s"));
            if ($isExport) {
                $exportData[] = ['时间', $this->getIndicatorName($indexCatalog['index_name']) . '分子', $this->getIndicatorName($indexCatalog['index_name']) . '分母', '指标合格率'];
                foreach ($resData as $key => $val) {
                    // 在 yearStr 前加上单引号
                    if ($val['month']) {
                        $yearStr = sprintf("\t%04d/%02d", $val['year'], $val['month']);
                    } else {
                        $yearStr = '平均值';
                    }
                    $exportData[$key + 1] = [
                        $yearStr,
                        $val["fenzi"],
                        $val["fenmu"],
                        $val["radio"] . "%",
                    ];
                }

                $csv = new CsvService();
                $csv->filename = $csv->charset($indexCatalog["name"] . '--指标', 'UTF-8');
                return $csv->export($exportData);
            }

            // 记录每天第一次访问的日志
            Log::info('getIndex请求参数12：--------------------------' . date("Y-m-d H:i:s"));
            $this->logFirstAccessToday($request);
            Log::info('getIndex请求参数13：--------------------------' . date("Y-m-d H:i:s"));

            return ToolsService::returnData(200, $resData, $msg ?? '');
        }
    }

    /**
     * 指标分析接口
     * 根据传入的年月和type类型,查询不同时间范围的指标数据
     * 
     * @param Request $request
     * @return array
     */
    public function getIndexAnalysis(Request $request)
    {
        $yearMonth = $request->get('year_month'); // 年月,格式: 202507
        $type = $request->get('type'); // 类型: month, quarter, year
        $isExport = $request->get('is_export'); // 是否导出
        $category = $request->get('category'); // 类别
        $AAC11N = $request->get('AAC11N');
        $AEE03 = $request->get('AEE03');

        // 验证必要参数
        if (empty($yearMonth) || empty($type)) {
            return ToolsService::returnData(4001, [], '缺少必要参数: year_month 或 type');
        }

        // 验证年月格式
        if (strlen($yearMonth) != 6 || !is_numeric($yearMonth)) {
            return ToolsService::returnData(4001, [], 'year_month 格式错误,应为6位数字,如: 202507');
        }

        // 验证type参数
        if (!in_array($type, ['month', 'quarter', 'year'])) {
            return ToolsService::returnData(4001, [], 'type 参数错误,应为: month, quarter 或 year');
        }

        // 解析年月
        $year = (int) substr($yearMonth, 0, 4);
        $month = (int) substr($yearMonth, 4, 2);

        // 验证月份有效性
        if ($month < 1 || $month > 12) {
            return ToolsService::returnData(4001, [], '月份无效,应为 01-12');
        }

        // 获取指标配置
        $indexCatalog = IndexCatalog::query()->where("url", "=", $category)->first();
        if (!$indexCatalog) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }
        $indexCatalog = $indexCatalog->toArray();

        // 根据type计算时间范围
        $timeRanges = $this->calculateTimeRanges($year, $month, $type);

        if (empty($timeRanges)) {
            return ToolsService::returnData(4001, [], '时间范围计算失败');
        }

        // 特殊处理 sjsjsssjkzl 指标
        if ($indexCatalog['index_name'] === 'sjsjsssjkzl') {
            return $this->handleSjsjsssjkzlAnalysis($indexCatalog, $timeRanges, $type, $AAC11N, $AEE03, $isExport);
        }

        // 处理其他指标
        return $this->handleNormalIndexAnalysis($indexCatalog, $timeRanges, $type, $AAC11N, $AEE03, $isExport);
    }

    /**
     * 根据type计算时间范围
     * 
     * @param int $year
     * @param int $month
     * @param string $type
     * @return array
     */
    private function calculateTimeRanges($year, $month, $type)
    {
        $ranges = [];

        switch ($type) {
            case 'month':
                // 当前月份往前推5个月(包含当前月,共6个月)
                // 如7月,查询2-7月
                $startMonth = $month - 5;
                $startYear = $year;

                // 如果起始月份小于1,需要往前推一年
                if ($startMonth < 1) {
                    $startMonth += 12;
                    $startYear -= 1;
                }

                // 生成月份范围
                $currentYear = $startYear;
                $currentMonth = $startMonth;
                while (true) {
                    $ranges[] = [
                        'year' => $currentYear,
                        'month' => $currentMonth,
                        'label' => sprintf('%04d年%02d月', $currentYear, $currentMonth)
                    ];

                    if ($currentYear == $year && $currentMonth == $month) {
                        break;
                    }

                    $currentMonth++;
                    if ($currentMonth > 12) {
                        $currentMonth = 1;
                        $currentYear++;
                    }
                }
                break;

            case 'quarter':
                // 计算当前月份所在的季度
                $currentQuarter = ceil($month / 3);

                // 当前季度
                $currentQuarterStartMonth = ($currentQuarter - 1) * 3 + 1;
                $currentQuarterEndMonth = $currentQuarter * 3;

                $ranges[] = [
                    'year' => $year,
                    'start_month' => $currentQuarterStartMonth,
                    'end_month' => $currentQuarterEndMonth,
                    'quarter' => $currentQuarter,
                    'label' => sprintf('%04d年第%d季度', $year, $currentQuarter)
                ];

                // 上一季度
                $prevQuarter = $currentQuarter - 1;
                $prevYear = $year;

                if ($prevQuarter < 1) {
                    $prevQuarter = 4;
                    $prevYear = $year - 1;
                }

                $prevQuarterStartMonth = ($prevQuarter - 1) * 3 + 1;
                $prevQuarterEndMonth = $prevQuarter * 3;

                $ranges[] = [
                    'year' => $prevYear,
                    'start_month' => $prevQuarterStartMonth,
                    'end_month' => $prevQuarterEndMonth,
                    'quarter' => $prevQuarter,
                    'label' => sprintf('%04d年第%d季度', $prevYear, $prevQuarter)
                ];

                // 上上季度
                $prevPrevQuarter = $prevQuarter - 1;
                $prevPrevYear = $prevYear;

                if ($prevPrevQuarter < 1) {
                    $prevPrevQuarter = 4;
                    $prevPrevYear = $prevYear - 1;
                }

                $prevPrevQuarterStartMonth = ($prevPrevQuarter - 1) * 3 + 1;
                $prevPrevQuarterEndMonth = $prevPrevQuarter * 3;

                $ranges[] = [
                    'year' => $prevPrevYear,
                    'start_month' => $prevPrevQuarterStartMonth,
                    'end_month' => $prevPrevQuarterEndMonth,
                    'quarter' => $prevPrevQuarter,
                    'label' => sprintf('%04d年第%d季度', $prevPrevYear, $prevPrevQuarter)
                ];
                break;

            case 'year':
                // 当前年、上一年和上上年
                $ranges[] = [
                    'year' => $year,
                    'label' => sprintf('%04d年', $year)
                ];
                $ranges[] = [
                    'year' => $year - 1,
                    'label' => sprintf('%04d年', $year - 1)
                ];
                $ranges[] = [
                    'year' => $year - 2,
                    'label' => sprintf('%04d年', $year - 2)
                ];
                break;
        }

        return $ranges;
    }

    /**
     * 处理 sjsjsssjkzl 指标的分析
     * 
     * @param array $indexCatalog
     * @param array $timeRanges
     * @param string $type
     * @param string $AAC11N
     * @param string $AEE03
     * @param bool $isExport
     * @return array
     */
    private function handleSjsjsssjkzlAnalysis($indexCatalog, $timeRanges, $type, $AAC11N, $AEE03, $isExport)
    {
        $data = [];

        foreach ($timeRanges as $range) {
            if ($type === 'month') {
                // 按月处理
                $year = $range['year'];
                $month = $range['month'];
                $monthStr = sprintf('%04d-%02d', $year, $month);

                // 分母：查询 surgical_catalog 的 CYSJ 包含该月份
                $fenmu = DB::table('surgical_catalog')
                    ->where('CYSJ', 'like', '%' . $monthStr . '%')
                    ->count();

                // 分子：查询主手术和其他手术中关联 info 表的 AAC01 范围是当月的
                $monthStart = date('Y-m-01 00:00:00', strtotime("$year-$month-01"));
                $monthEnd = date('Y-m-t 23:59:59', strtotime("$year-$month-01"));

                // 构建主手术查询
                $mainOperationsQuery = DB::table('main_operation as mo')
                    ->join('patient_info as pi', 'mo.AAA28', '=', 'pi.MED_REC_ID')
                    ->whereBetween('pi.AAC01', [$monthStart, $monthEnd])
                    ->whereIn('mo.OPE_LEVEL', [3, 4]);

                if (!empty($AAC11N)) {
                    $mainOperationsQuery->where('pi.AAC11N', $AAC11N);
                }
                if (!empty($AEE03)) {
                    $mainOperationsQuery->where('pi.AEE03', $AEE03);
                }

                $mainOperations = $mainOperationsQuery->select('mo.ICD9_NAME')->get();

                // 构建其他手术查询
                $secondaryOperationsQuery = DB::table('secondary_operation as so')
                    ->join('patient_info as pi', 'so.AAA28', '=', 'pi.MED_REC_ID')
                    ->whereBetween('pi.AAC01', [$monthStart, $monthEnd])
                    ->whereIn('so.OPE_LEVEL', [3, 4]);

                if (!empty($AAC11N)) {
                    $secondaryOperationsQuery->where('pi.AAC11N', $AAC11N);
                }
                if (!empty($AEE03)) {
                    $secondaryOperationsQuery->where('pi.AEE03', $AEE03);
                }

                $secondaryOperations = $secondaryOperationsQuery->select('so.ICD9_NAME')->get();

                // 合并并按照 ICD9_NAME 分组
                $allOperations = $mainOperations->merge($secondaryOperations);
                $groupedOperations = $allOperations->groupBy('ICD9_NAME');

                // 获取 surgical_catalog 中存在的 ICD9_NAME 列表
                $surgicalCatalogNames = DB::table('surgical_catalog')
                    ->where('CYSJ', 'like', '%' . $monthStr . '%')
                    ->pluck('ICD9_NAME')
                    ->toArray();

                // 计算分子
                $fenzi = 0;
                foreach ($groupedOperations as $icd9Name => $operations) {
                    if (in_array($icd9Name, $surgicalCatalogNames)) {
                        $fenzi += $operations->count();
                    }
                }

                $fenmu = round(floatval($fenmu), 4);
                $fenzi = round(floatval($fenzi), 4);

                $data[] = [
                    'fenzi' => $fenzi,
                    'fenmu' => $fenmu,
                    'radio' => $fenmu > 0 ? number_format(($fenzi / $fenmu) * 100, 2) : 0,
                    'month' => $month,
                    'year' => $year,
                    'label' => $range['label'],
                    'status' => $indexCatalog['status'] == 1 ? '质控中' : "成功",
                    'update_time' => $indexCatalog['quality_time'] ? date("Y-m-d", $indexCatalog['quality_time']) : '--'
                ];
            } elseif ($type === 'quarter') {
                // 按季度处理
                $year = $range['year'];
                $startMonth = $range['start_month'];
                $endMonth = $range['end_month'];

                $fenmu = 0;
                $fenzi = 0;

                // 遍历季度内的每个月
                for ($m = $startMonth; $m <= $endMonth; $m++) {
                    $monthStr = sprintf('%04d-%02d', $year, $m);

                    // 分母
                    $monthFenmu = DB::table('surgical_catalog')
                        ->where('CYSJ', 'like', '%' . $monthStr . '%')
                        ->count();
                    $fenmu += $monthFenmu;

                    // 分子
                    $monthStart = date('Y-m-01 00:00:00', strtotime("$year-$m-01"));
                    $monthEnd = date('Y-m-t 23:59:59', strtotime("$year-$m-01"));

                    $mainOperationsQuery = DB::table('main_operation as mo')
                        ->join('patient_info as pi', 'mo.AAA28', '=', 'pi.MED_REC_ID')
                        ->whereBetween('pi.AAC01', [$monthStart, $monthEnd])
                        ->whereIn('mo.OPE_LEVEL', [3, 4]);

                    if (!empty($AAC11N)) {
                        $mainOperationsQuery->where('pi.AAC11N', $AAC11N);
                    }
                    if (!empty($AEE03)) {
                        $mainOperationsQuery->where('pi.AEE03', $AEE03);
                    }

                    $mainOperations = $mainOperationsQuery->select('mo.ICD9_NAME')->get();

                    $secondaryOperationsQuery = DB::table('secondary_operation as so')
                        ->join('patient_info as pi', 'so.AAA28', '=', 'pi.MED_REC_ID')
                        ->whereBetween('pi.AAC01', [$monthStart, $monthEnd])
                        ->whereIn('so.OPE_LEVEL', [3, 4]);

                    if (!empty($AAC11N)) {
                        $secondaryOperationsQuery->where('pi.AAC11N', $AAC11N);
                    }
                    if (!empty($AEE03)) {
                        $secondaryOperationsQuery->where('pi.AEE03', $AEE03);
                    }

                    $secondaryOperations = $secondaryOperationsQuery->select('so.ICD9_NAME')->get();

                    $allOperations = $mainOperations->merge($secondaryOperations);
                    $groupedOperations = $allOperations->groupBy('ICD9_NAME');

                    $surgicalCatalogNames = DB::table('surgical_catalog')
                        ->where('CYSJ', 'like', '%' . $monthStr . '%')
                        ->pluck('ICD9_NAME')
                        ->toArray();

                    foreach ($groupedOperations as $icd9Name => $operations) {
                        if (in_array($icd9Name, $surgicalCatalogNames)) {
                            $fenzi += $operations->count();
                        }
                    }
                }

                $fenmu = round(floatval($fenmu), 4);
                $fenzi = round(floatval($fenzi), 4);

                $data[] = [
                    'fenzi' => $fenzi,
                    'fenmu' => $fenmu,
                    'radio' => $fenmu > 0 ? number_format(($fenzi / $fenmu) * 100, 2) : 0,
                    'quarter' => $range['quarter'],
                    'year' => $year,
                    'label' => $range['label'],
                    'status' => $indexCatalog['status'] == 1 ? '质控中' : "成功",
                    'update_time' => $indexCatalog['quality_time'] ? date("Y-m-d", $indexCatalog['quality_time']) : '--'
                ];
            } elseif ($type === 'year') {
                // 按年处理
                $year = $range['year'];

                $fenmu = 0;
                $fenzi = 0;

                // 遍历全年12个月
                for ($m = 1; $m <= 12; $m++) {
                    $monthStr = sprintf('%04d-%02d', $year, $m);

                    // 分母
                    $monthFenmu = DB::table('surgical_catalog')
                        ->where('CYSJ', 'like', '%' . $monthStr . '%')
                        ->count();
                    $fenmu += $monthFenmu;

                    // 分子
                    $monthStart = date('Y-m-01 00:00:00', strtotime("$year-$m-01"));
                    $monthEnd = date('Y-m-t 23:59:59', strtotime("$year-$m-01"));

                    $mainOperationsQuery = DB::table('main_operation as mo')
                        ->join('patient_info as pi', 'mo.AAA28', '=', 'pi.MED_REC_ID')
                        ->whereBetween('pi.AAC01', [$monthStart, $monthEnd])
                        ->whereIn('mo.OPE_LEVEL', [3, 4]);

                    if (!empty($AAC11N)) {
                        $mainOperationsQuery->where('pi.AAC11N', $AAC11N);
                    }
                    if (!empty($AEE03)) {
                        $mainOperationsQuery->where('pi.AEE03', $AEE03);
                    }

                    $mainOperations = $mainOperationsQuery->select('mo.ICD9_NAME')->get();

                    $secondaryOperationsQuery = DB::table('secondary_operation as so')
                        ->join('patient_info as pi', 'so.AAA28', '=', 'pi.MED_REC_ID')
                        ->whereBetween('pi.AAC01', [$monthStart, $monthEnd])
                        ->whereIn('so.OPE_LEVEL', [3, 4]);

                    if (!empty($AAC11N)) {
                        $secondaryOperationsQuery->where('pi.AAC11N', $AAC11N);
                    }
                    if (!empty($AEE03)) {
                        $secondaryOperationsQuery->where('pi.AEE03', $AEE03);
                    }

                    $secondaryOperations = $secondaryOperationsQuery->select('so.ICD9_NAME')->get();

                    $allOperations = $mainOperations->merge($secondaryOperations);
                    $groupedOperations = $allOperations->groupBy('ICD9_NAME');

                    $surgicalCatalogNames = DB::table('surgical_catalog')
                        ->where('CYSJ', 'like', '%' . $monthStr . '%')
                        ->pluck('ICD9_NAME')
                        ->toArray();

                    foreach ($groupedOperations as $icd9Name => $operations) {
                        if (in_array($icd9Name, $surgicalCatalogNames)) {
                            $fenzi += $operations->count();
                        }
                    }
                }

                $fenmu = round(floatval($fenmu), 4);
                $fenzi = round(floatval($fenzi), 4);

                $data[] = [
                    'fenzi' => $fenzi,
                    'fenmu' => $fenmu,
                    'radio' => $fenmu > 0 ? number_format(($fenzi / $fenmu) * 100, 2) : 0,
                    'year' => $year,
                    'label' => $range['label'],
                    'status' => $indexCatalog['status'] == 1 ? '质控中' : "成功",
                    'update_time' => $indexCatalog['quality_time'] ? date("Y-m-d", $indexCatalog['quality_time']) : '--'
                ];
            }
        }

        if ($isExport) {
            $exportData[] = ['时间', $this->getIndicatorName($indexCatalog['index_name']) . '分子', $this->getIndicatorName($indexCatalog['index_name']) . '分母', '指标合格率'];
            foreach ($data as $key => $val) {
                $exportData[$key + 1] = [
                    $val['label'],
                    $val["fenzi"],
                    $val["fenmu"],
                    $val["radio"] . "%",
                ];
            }

            $csv = new CsvService();
            $csv->filename = $csv->charset($indexCatalog["name"] . '--指标分析', 'UTF-8');
            return $csv->export($exportData);
        }

        // 转换为键值对格式: 键为时间标识,值为指标率
        // 与getIndex方法保持一致，radio是百分比形式（0-100），如0.97表示0.97%
        $result = [];
        foreach ($data as $item) {
            $result[$item['label']] = floatval($item['radio']);
        }

        return ToolsService::returnData(200, $result, '成功');
    }

    /**
     * 处理普通指标的分析
     * 
     * @param array $indexCatalog
     * @param array $timeRanges
     * @param string $type
     * @param string $AAC11N
     * @param string $AEE03
     * @param bool $isExport
     * @return array
     */
    private function handleNormalIndexAnalysis($indexCatalog, $timeRanges, $type, $AAC11N, $AEE03, $isExport)
    {
        // 判断是否为特殊指标(需要特殊处理比率计算)
        $isSpecialIndex = in_array($indexCatalog['index_name'], ['sjssysjssbfzfs', 'sjssysjssswlb']);

        $data = [];

        foreach ($timeRanges as $range) {
            if ($type === 'month') {
                // 按月处理
                $year = $range['year'];
                $month = $range['month'];

                $startDate = date('Y-m-01 00:00:00', strtotime("$year-$month-01"));
                $endDate = date('Y-m-t 23:59:59', strtotime("$year-$month-01"));

                // 特殊处理 sjssysjssbfzfs 指标
                if ($indexCatalog['index_name'] === 'sjssysjssbfzfs') {
                    $query = Indicator::query()->selectRaw(
                        "AAC01_YEAR as year, 
                        AAC01_MONTH as month, 
                        CASE WHEN sum(sjssbfz_fm) > 0 THEN sum(sjssbfz_fz) / sum(sjssbfz_fm) ELSE 0 END as fenmu, 
                        CASE WHEN sum(sijssbfz_fm) > 0 THEN sum(sijssbfz_fz) / sum(sijssbfz_fm) ELSE 0 END as fenzi"
                    );
                } else if ($indexCatalog['index_name'] === 'sjssysjssswlb') {
                    $query = Indicator::query()->selectRaw(
                        "AAC01_YEAR as year, 
                        AAC01_MONTH as month, 
                        CASE WHEN sum(sjsssw_fm) > 0 THEN sum(sjsssw_fz) / sum(sjsssw_fm) ELSE 0 END as fenmu, 
                        CASE WHEN sum(sijsssw_fm) > 0 THEN sum(sijsssw_fz) / sum(sijsssw_fm) ELSE 0 END as fenzi"
                    );
                } else {
                    $query = Indicator::query()->selectRaw("AAC01_YEAR as year, AAC01_MONTH as month, sum(" . $indexCatalog['index_name'] . "_fm) as fenmu, sum(" . $indexCatalog['index_name'] . "_fz) as fenzi");
                }

                if (!empty($AAC11N)) {
                    $query->where('AAC11N', $AAC11N);
                }
                if (!empty($AEE03)) {
                    $query->where('AEE03', $AEE03);
                }

                $query->whereBetween('AAC01', [$startDate, $endDate]);
                $res = $query->groupBy("AAC01_MONTH")->first();

                $fenmu = $res ? round(floatval($res->fenmu), 4) : 0;
                $fenzi = $res ? round(floatval($res->fenzi), 4) : 0;

                $data[] = [
                    'fenzi' => $fenzi,
                    'fenmu' => $fenmu,
                    'radio' => $fenmu > 0 ? number_format(($fenzi / $fenmu) * 100, 2) : 0,
                    'month' => $month,
                    'year' => $year,
                    'label' => $range['label'],
                    'status' => $indexCatalog['status'] == 1 ? '质控中' : "成功",
                    'update_time' => $indexCatalog['quality_time'] ? date("Y-m-d", $indexCatalog['quality_time']) : '--'
                ];
            } elseif ($type === 'quarter') {
                // 按季度处理
                $year = $range['year'];
                $startMonth = $range['start_month'];
                $endMonth = $range['end_month'];

                $startDate = date('Y-m-01 00:00:00', strtotime("$year-$startMonth-01"));
                $endDate = date('Y-m-t 23:59:59', strtotime("$year-$endMonth-01"));

                // 对于特殊指标,需要先获取原始数据再计算比率
                if ($isSpecialIndex) {
                    if ($indexCatalog['index_name'] === 'sjssysjssbfzfs') {
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
                    $query = Indicator::query()->selectRaw("sum(" . $indexCatalog['index_name'] . "_fm) as fenmu, sum(" . $indexCatalog['index_name'] . "_fz) as fenzi");
                }

                if (!empty($AAC11N)) {
                    $query->where('AAC11N', $AAC11N);
                }
                if (!empty($AEE03)) {
                    $query->where('AEE03', $AEE03);
                }

                $query->whereBetween('AAC01', [$startDate, $endDate]);
                $res = $query->first();

                if ($isSpecialIndex) {
                    if ($indexCatalog['index_name'] === 'sjssysjssbfzfs') {
                        $fenmu = ($res && $res->sjssbfz_fm_sum > 0) ? round($res->sjssbfz_fz_sum / $res->sjssbfz_fm_sum, 4) : 0;
                        $fenzi = ($res && $res->sijssbfz_fm_sum > 0) ? round($res->sijssbfz_fz_sum / $res->sijssbfz_fm_sum, 4) : 0;
                    } else {
                        $fenmu = ($res && $res->sjsssw_fm_sum > 0) ? round($res->sjsssw_fz_sum / $res->sjsssw_fm_sum, 4) : 0;
                        $fenzi = ($res && $res->sijsssw_fm_sum > 0) ? round($res->sijsssw_fz_sum / $res->sijsssw_fm_sum, 4) : 0;
                    }
                } else {
                    $fenmu = $res ? round(floatval($res->fenmu), 4) : 0;
                    $fenzi = $res ? round(floatval($res->fenzi), 4) : 0;
                }

                $data[] = [
                    'fenzi' => $fenzi,
                    'fenmu' => $fenmu,
                    'radio' => $fenmu > 0 ? number_format(($fenzi / $fenmu) * 100, 2) : 0,
                    'quarter' => $range['quarter'],
                    'year' => $year,
                    'label' => $range['label'],
                    'status' => $indexCatalog['status'] == 1 ? '质控中' : "成功",
                    'update_time' => $indexCatalog['quality_time'] ? date("Y-m-d", $indexCatalog['quality_time']) : '--'
                ];
            } elseif ($type === 'year') {
                // 按年处理
                $year = $range['year'];

                $startDate = date('Y-01-01 00:00:00', strtotime("$year-01-01"));
                $endDate = date('Y-12-t 23:59:59', strtotime("$year-12-01"));

                // 对于特殊指标,需要先获取原始数据再计算比率
                if ($isSpecialIndex) {
                    if ($indexCatalog['index_name'] === 'sjssysjssbfzfs') {
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
                    $query = Indicator::query()->selectRaw("sum(" . $indexCatalog['index_name'] . "_fm) as fenmu, sum(" . $indexCatalog['index_name'] . "_fz) as fenzi");
                }

                if (!empty($AAC11N)) {
                    $query->where('AAC11N', $AAC11N);
                }
                if (!empty($AEE03)) {
                    $query->where('AEE03', $AEE03);
                }

                $query->whereBetween('AAC01', [$startDate, $endDate]);
                $res = $query->first();

                // 添加调试日志
                Log::info('getIndexAnalysis年度查询', [
                    'year' => $year,
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'index_name' => $indexCatalog['index_name'],
                    'AAC11N' => $AAC11N,
                    'AEE03' => $AEE03,
                    'result' => $res ? $res->toArray() : null
                ]);

                if ($isSpecialIndex) {
                    if ($indexCatalog['index_name'] === 'sjssysjssbfzfs') {
                        $fenmu = ($res && $res->sjssbfz_fm_sum > 0) ? round($res->sjssbfz_fz_sum / $res->sjssbfz_fm_sum, 4) : 0;
                        $fenzi = ($res && $res->sijssbfz_fm_sum > 0) ? round($res->sijssbfz_fz_sum / $res->sijssbfz_fm_sum, 4) : 0;
                    } else {
                        $fenmu = ($res && $res->sjsssw_fm_sum > 0) ? round($res->sjsssw_fz_sum / $res->sjsssw_fm_sum, 4) : 0;
                        $fenzi = ($res && $res->sijsssw_fm_sum > 0) ? round($res->sijsssw_fz_sum / $res->sijsssw_fm_sum, 4) : 0;
                    }
                } else {
                    $fenmu = $res ? round(floatval($res->fenmu), 4) : 0;
                    $fenzi = $res ? round(floatval($res->fenzi), 4) : 0;
                }

                Log::info('getIndexAnalysis年度计算结果', [
                    'year' => $year,
                    'fenmu' => $fenmu,
                    'fenzi' => $fenzi,
                    'radio_before_format' => $fenmu > 0 ? ($fenzi / $fenmu) * 100 : 0,
                    'radio_after_format' => $fenmu > 0 ? number_format(($fenzi / $fenmu) * 100, 2) : 0
                ]);

                $data[] = [
                    'fenzi' => $fenzi,
                    'fenmu' => $fenmu,
                    'radio' => $fenmu > 0 ? number_format(($fenzi / $fenmu) * 100, 2) : 0,
                    'year' => $year,
                    'label' => $range['label'],
                    'status' => $indexCatalog['status'] == 1 ? '质控中' : "成功",
                    'update_time' => $indexCatalog['quality_time'] ? date("Y-m-d", $indexCatalog['quality_time']) : '--'
                ];
            }
        }

        if ($isExport) {
            $exportData[] = ['时间', $this->getIndicatorName($indexCatalog['index_name']) . '分子', $this->getIndicatorName($indexCatalog['index_name']) . '分母', '指标合格率'];
            foreach ($data as $key => $val) {
                $exportData[$key + 1] = [
                    $val['label'],
                    $val["fenzi"],
                    $val["fenmu"],
                    $val["radio"] . "%",
                ];
            }

            $csv = new CsvService();
            $csv->filename = $csv->charset($indexCatalog["name"] . '--指标分析', 'UTF-8');
            return $csv->export($exportData);
        }

        // 转换为键值对格式: 键为时间标识,值为指标率
        // 与getIndex方法保持一致，radio是百分比形式（0-100），如0.97表示0.97%
        $result = [];
        foreach ($data as $item) {
            $result[$item['label']] = floatval($item['radio']);
        }

        return ToolsService::returnData(200, $result, '成功');
    }

    /**
     * 指标科室排名接口
     * 根据传入的年月、type类型和指标类别,查询各科室的指标率排名
     * 
     * @param Request $request
     * @return array
     */
    public function getIndexDepartmentRanking(Request $request)
    {
        $yearMonth = $request->get('year_month'); // 年月,格式: 202507
        $type = $request->get('type'); // 类型: month, quarter, year
        $category = $request->get('category'); // 类别
        $AAC11N = $request->get('AAC11N'); // 科室(可选)
        $AEE03 = $request->get('AEE03'); // 主治医师(可选)
        $isExport = $request->get('is_export'); // 是否导出(可选)


        // 验证必要参数
        if (empty($yearMonth) || empty($type) || empty($category)) {
            return ToolsService::returnData(4001, [], '缺少必要参数: year_month、type 或 category');
        }

        // 验证年月格式
        if (strlen($yearMonth) != 6 || !is_numeric($yearMonth)) {
            return ToolsService::returnData(4001, [], 'year_month 格式错误,应为6位数字,如: 202507');
        }

        // 验证type参数
        if (!in_array($type, ['month', 'quarter', 'year'])) {
            return ToolsService::returnData(4001, [], 'type 参数错误,应为: month, quarter 或 year');
        }

        // 解析年月
        $year = (int) substr($yearMonth, 0, 4);
        $month = (int) substr($yearMonth, 4, 2);

        // 验证月份有效性
        if ($month < 1 || $month > 12) {
            return ToolsService::returnData(4001, [], '月份无效,应为 01-12');
        }

        // 获取指标配置
        $indexCatalog = IndexCatalog::query()->where("url", "=", $category)->first();
        if (!$indexCatalog) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }
        $indexCatalog = $indexCatalog->toArray();

        // 不查indicator表的指标不支持科室排名查询（因为其他表不一定有科室数据）
        // sjsjsssjkzl 指标查询的是surgical_catalog、main_operation、secondary_operation等表
        if ($indexCatalog['index_name'] === 'sjsjsssjkzl') {
            return ToolsService::returnData(200, [], '该指标不支持科室排名查询');
        }

        // 计算时间范围
        $timeRanges = $this->calculateTimeRanges($year, $month, $type);

        if (empty($timeRanges)) {
            return ToolsService::returnData(4001, [], '时间范围计算失败');
        }

        // 计算当前期的数据（只查询indicator表的指标）
        $currentData = $this->getNormalIndexDepartmentData($indexCatalog, $timeRanges, $type, $AAC11N, $AEE03);

        // 计算上一期的数据用于环比
        $prevTimeRanges = $this->calculatePrevTimeRanges($year, $month, $type);
        $prevData = [];
        if (!empty($prevTimeRanges)) {
            $prevData = $this->getNormalIndexDepartmentData($indexCatalog, $prevTimeRanges, $type, $AAC11N, $AEE03);
        }

        // 获取所有科室名称对应的dep_id
        $depNames = array_keys($currentData);
        $departmentMap = [];
        if (!empty($depNames)) {
            $departments = \App\Model\Department::query()
                ->whereIn('dep_name', $depNames)
                ->get(['dep_id', 'dep_name']);
            foreach ($departments as $dept) {
                $departmentMap[$dept->dep_name] = $dept->dep_id;
            }
        }

        // 合并数据并计算环比
        $result = [];
        foreach ($currentData as $depName => $stats) {
            $fenmu = round(floatval($stats['fenmu']), 4);
            $fenzi = round(floatval($stats['fenzi']), 4);
            $radio = $fenmu > 0 ? round(($fenzi / $fenmu) * 100, 2) : 0;

            // 计算环比
            $prevRadio = 0;
            $chainRatio = 0;
            if (isset($prevData[$depName])) {
                $prevFenmu = round(floatval($prevData[$depName]['fenmu']), 4);
                $prevFenzi = round(floatval($prevData[$depName]['fenzi']), 4);
                $prevRadio = $prevFenmu > 0 ? round(($prevFenzi / $prevFenmu) * 100, 2) : 0;

                if ($prevRadio > 0) {
                    $chainRatio = round((($radio - $prevRadio) / $prevRadio) * 100, 2);
                } elseif ($radio > 0) {
                    $chainRatio = 100; // 上一期为0，当前期有值，增长100%
                }
            }

            $result[] = [
                'dep_id' => $departmentMap[$depName] ?? '',
                'dep_name' => $depName,
                'radio' => $radio,
                'fenzi' => $fenzi,
                'fenmu' => $fenmu,
                'chain_ratio' => $chainRatio
            ];
        }

        // 按指标率降序排序
        usort($result, function ($a, $b) {
            return $b['radio'] <=> $a['radio'];
        });

        // 添加排名
        foreach ($result as $index => &$item) {
            $item['rank'] = $index + 1;
        }

        if ($isExport) {
            $exportData[] = ['排名', '就诊科室', '达标率', '分子', '分母', '环比'];
            foreach ($result as $key => $val) {
                $exportData[$key + 1] = [
                    $val['rank'],
                    $val["dep_name"],
                    $val["radio"] . "%",
                    $val["fenzi"],
                    $val["fenmu"],
                    $val["chain_ratio"] . "%",
                ];
            }

            $csv = new CsvService();
            $csv->filename = $csv->charset($indexCatalog["name"] . '--指标分析', 'UTF-8');
            return $csv->export($exportData);
        }

        return ToolsService::returnData(200, $result, '成功');
    }

    /**
     * 计算上一期的时间范围
     * 
     * @param int $year
     * @param int $month
     * @param string $type
     * @return array
     */
    private function calculatePrevTimeRanges($year, $month, $type)
    {
        $ranges = [];

        switch ($type) {
            case 'month':
                // 上一个月
                $prevMonth = $month - 1;
                $prevYear = $year;
                if ($prevMonth < 1) {
                    $prevMonth = 12;
                    $prevYear -= 1;
                }
                $ranges[] = [
                    'year' => $prevYear,
                    'month' => $prevMonth,
                    'label' => sprintf('%04d年%02d月', $prevYear, $prevMonth)
                ];
                break;

            case 'quarter':
                // 上一个季度
                $currentQuarter = ceil($month / 3);
                $prevQuarter = $currentQuarter - 1;
                $prevYear = $year;

                if ($prevQuarter < 1) {
                    $prevQuarter = 4;
                    $prevYear = $year - 1;
                }

                $prevQuarterStartMonth = ($prevQuarter - 1) * 3 + 1;
                $prevQuarterEndMonth = $prevQuarter * 3;

                $ranges[] = [
                    'year' => $prevYear,
                    'start_month' => $prevQuarterStartMonth,
                    'end_month' => $prevQuarterEndMonth,
                    'quarter' => $prevQuarter,
                    'label' => sprintf('%04d年第%d季度', $prevYear, $prevQuarter)
                ];
                break;

            case 'year':
                // 上一年
                $ranges[] = [
                    'year' => $year - 1,
                    'label' => sprintf('%04d年', $year - 1)
                ];
                break;
        }

        return $ranges;
    }

    /**
     * 获取 sjsjsssjkzl 指标的科室数据
     * 
     * @param array $timeRanges
     * @param string $type
     * @param string $AEE03
     * @return array
     */
    private function getSjsjsssjkzlDepartmentData($timeRanges, $type, $AEE03)
    {
        $departmentData = [];

        foreach ($timeRanges as $range) {
            if ($type === 'month') {
                // 按月处理
                $year = $range['year'];
                $month = $range['month'];
                $monthStr = sprintf('%04d-%02d', $year, $month);

                // 查询所有科室的数据
                $surgicalCatalog = DB::table('surgical_catalog')
                    ->where('CYSJ', 'like', '%' . $monthStr . '%')
                    ->get();

                $monthStart = date('Y-m-01 00:00:00', strtotime("$year-$month-01"));
                $monthEnd = date('Y-m-t 23:59:59', strtotime("$year-$month-01"));

                // 按科室分组统计
                $departmentStats = [];
                foreach ($surgicalCatalog as $item) {
                    $depId = $item->AAC11N ?? '';
                    if (empty($depId)) {
                        continue;
                    }
                    if (!isset($departmentStats[$depId])) {
                        $departmentStats[$depId] = ['fenmu' => 0, 'fenzi' => 0];
                    }
                    $departmentStats[$depId]['fenmu']++;
                }

                // 查询主手术和其他手术
                $mainOperationsQuery = DB::table('main_operation as mo')
                    ->join('patient_info as pi', 'mo.AAA28', '=', 'pi.MED_REC_ID')
                    ->whereBetween('pi.AAC01', [$monthStart, $monthEnd])
                    ->whereIn('mo.OPE_LEVEL', [3, 4]);

                if (!empty($AEE03)) {
                    $mainOperationsQuery->where('pi.AEE03', $AEE03);
                }

                $mainOperations = $mainOperationsQuery->select('mo.ICD9_NAME', 'pi.AAC11N')->get();

                $secondaryOperationsQuery = DB::table('secondary_operation as so')
                    ->join('patient_info as pi', 'so.AAA28', '=', 'pi.MED_REC_ID')
                    ->whereBetween('pi.AAC01', [$monthStart, $monthEnd])
                    ->whereIn('so.OPE_LEVEL', [3, 4]);

                if (!empty($AEE03)) {
                    $secondaryOperationsQuery->where('pi.AEE03', $AEE03);
                }

                $secondaryOperations = $secondaryOperationsQuery->select('so.ICD9_NAME', 'pi.AAC11N')->get();

                $allOperations = $mainOperations->merge($secondaryOperations);
                $surgicalCatalogNames = $surgicalCatalog->pluck('ICD9_NAME')->toArray();

                foreach ($allOperations as $operation) {
                    $depId = $operation->AAC11N ?? '';
                    if (empty($depId)) {
                        continue;
                    }
                    if (!isset($departmentStats[$depId])) {
                        $departmentStats[$depId] = ['fenmu' => 0, 'fenzi' => 0];
                    }
                    if (in_array($operation->ICD9_NAME, $surgicalCatalogNames)) {
                        $departmentStats[$depId]['fenzi']++;
                    }
                }

                // 合并到总数据中
                foreach ($departmentStats as $depId => $stats) {
                    if (!isset($departmentData[$depId])) {
                        $departmentData[$depId] = ['fenmu' => 0, 'fenzi' => 0];
                    }
                    $departmentData[$depId]['fenmu'] += $stats['fenmu'];
                    $departmentData[$depId]['fenzi'] += $stats['fenzi'];
                }
            } elseif ($type === 'quarter') {
                // 按季度处理
                $year = $range['year'];
                $startMonth = $range['start_month'];
                $endMonth = $range['end_month'];

                for ($m = $startMonth; $m <= $endMonth; $m++) {
                    $monthStr = sprintf('%04d-%02d', $year, $m);
                    $monthStart = date('Y-m-01 00:00:00', strtotime("$year-$m-01"));
                    $monthEnd = date('Y-m-t 23:59:59', strtotime("$year-$m-01"));

                    $surgicalCatalog = DB::table('surgical_catalog')
                        ->where('CYSJ', 'like', '%' . $monthStr . '%')
                        ->get();

                    $departmentStats = [];
                    foreach ($surgicalCatalog as $item) {
                        $depId = $item->AAC11N ?? '';
                        if (empty($depId)) {
                            continue;
                        }
                        if (!isset($departmentStats[$depId])) {
                            $departmentStats[$depId] = ['fenmu' => 0, 'fenzi' => 0];
                        }
                        $departmentStats[$depId]['fenmu']++;
                    }

                    $mainOperationsQuery = DB::table('main_operation as mo')
                        ->join('patient_info as pi', 'mo.AAA28', '=', 'pi.MED_REC_ID')
                        ->whereBetween('pi.AAC01', [$monthStart, $monthEnd])
                        ->whereIn('mo.OPE_LEVEL', [3, 4]);

                    if (!empty($AEE03)) {
                        $mainOperationsQuery->where('pi.AEE03', $AEE03);
                    }

                    $mainOperations = $mainOperationsQuery->select('mo.ICD9_NAME', 'pi.AAC11N')->get();

                    $secondaryOperationsQuery = DB::table('secondary_operation as so')
                        ->join('patient_info as pi', 'so.AAA28', '=', 'pi.MED_REC_ID')
                        ->whereBetween('pi.AAC01', [$monthStart, $monthEnd])
                        ->whereIn('so.OPE_LEVEL', [3, 4]);

                    if (!empty($AEE03)) {
                        $secondaryOperationsQuery->where('pi.AEE03', $AEE03);
                    }

                    $secondaryOperations = $secondaryOperationsQuery->select('so.ICD9_NAME', 'pi.AAC11N')->get();

                    $allOperations = $mainOperations->merge($secondaryOperations);
                    $surgicalCatalogNames = $surgicalCatalog->pluck('ICD9_NAME')->toArray();

                    foreach ($allOperations as $operation) {
                        $depId = $operation->AAC11N ?? '';
                        if (empty($depId)) {
                            continue;
                        }
                        if (!isset($departmentStats[$depId])) {
                            $departmentStats[$depId] = ['fenmu' => 0, 'fenzi' => 0];
                        }
                        if (in_array($operation->ICD9_NAME, $surgicalCatalogNames)) {
                            $departmentStats[$depId]['fenzi']++;
                        }
                    }

                    foreach ($departmentStats as $depId => $stats) {
                        if (!isset($departmentData[$depId])) {
                            $departmentData[$depId] = ['fenmu' => 0, 'fenzi' => 0];
                        }
                        $departmentData[$depId]['fenmu'] += $stats['fenmu'];
                        $departmentData[$depId]['fenzi'] += $stats['fenzi'];
                    }
                }
            } elseif ($type === 'year') {
                // 按年处理
                $year = $range['year'];

                for ($m = 1; $m <= 12; $m++) {
                    $monthStr = sprintf('%04d-%02d', $year, $m);
                    $monthStart = date('Y-m-01 00:00:00', strtotime("$year-$m-01"));
                    $monthEnd = date('Y-m-t 23:59:59', strtotime("$year-$m-01"));

                    $surgicalCatalog = DB::table('surgical_catalog')
                        ->where('CYSJ', 'like', '%' . $monthStr . '%')
                        ->get();

                    $departmentStats = [];
                    foreach ($surgicalCatalog as $item) {
                        $depId = $item->AAC11N ?? '';
                        if (empty($depId)) {
                            continue;
                        }
                        if (!isset($departmentStats[$depId])) {
                            $departmentStats[$depId] = ['fenmu' => 0, 'fenzi' => 0];
                        }
                        $departmentStats[$depId]['fenmu']++;
                    }

                    $mainOperationsQuery = DB::table('main_operation as mo')
                        ->join('patient_info as pi', 'mo.AAA28', '=', 'pi.MED_REC_ID')
                        ->whereBetween('pi.AAC01', [$monthStart, $monthEnd])
                        ->whereIn('mo.OPE_LEVEL', [3, 4]);

                    if (!empty($AEE03)) {
                        $mainOperationsQuery->where('pi.AEE03', $AEE03);
                    }

                    $mainOperations = $mainOperationsQuery->select('mo.ICD9_NAME', 'pi.AAC11N')->get();

                    $secondaryOperationsQuery = DB::table('secondary_operation as so')
                        ->join('patient_info as pi', 'so.AAA28', '=', 'pi.MED_REC_ID')
                        ->whereBetween('pi.AAC01', [$monthStart, $monthEnd])
                        ->whereIn('so.OPE_LEVEL', [3, 4]);

                    if (!empty($AEE03)) {
                        $secondaryOperationsQuery->where('pi.AEE03', $AEE03);
                    }

                    $secondaryOperations = $secondaryOperationsQuery->select('so.ICD9_NAME', 'pi.AAC11N')->get();

                    $allOperations = $mainOperations->merge($secondaryOperations);
                    $surgicalCatalogNames = $surgicalCatalog->pluck('ICD9_NAME')->toArray();

                    foreach ($allOperations as $operation) {
                        $depId = $operation->AAC11N ?? '';
                        if (empty($depId)) {
                            continue;
                        }
                        if (!isset($departmentStats[$depId])) {
                            $departmentStats[$depId] = ['fenmu' => 0, 'fenzi' => 0];
                        }
                        if (in_array($operation->ICD9_NAME, $surgicalCatalogNames)) {
                            $departmentStats[$depId]['fenzi']++;
                        }
                    }

                    foreach ($departmentStats as $depId => $stats) {
                        if (!isset($departmentData[$depId])) {
                            $departmentData[$depId] = ['fenmu' => 0, 'fenzi' => 0];
                        }
                        $departmentData[$depId]['fenmu'] += $stats['fenmu'];
                        $departmentData[$depId]['fenzi'] += $stats['fenzi'];
                    }
                }
            }
        }

        return $departmentData;
    }

    /**
     * 获取普通指标的科室数据
     * 
     * @param array $indexCatalog
     * @param array $timeRanges
     * @param string $type
     * @param string $AEE03
     * @return array
     */
    private function getNormalIndexDepartmentData($indexCatalog, $timeRanges, $type, $AAC11N, $AEE03)
    {
        // 判断是否为特殊指标
        $isSpecialIndex = in_array($indexCatalog['index_name'], ['sjssysjssbfzfs', 'sjssysjssswlb']);

        $startDate = null;
        $endDate = null;

        // 计算时间范围 - 只查询当前周期
        if ($type === 'month') {
            // 月度查询：取最后一个时间范围（当前月）
            // 因为 calculateTimeRanges 返回的月度数据是从旧到新排序的
            $range = end($timeRanges);
            $year = $range['year'];
            $month = $range['month'];
            $startDate = date('Y-m-01 00:00:00', strtotime("$year-$month-01"));
            $endDate = date('Y-m-t 23:59:59', strtotime("$year-$month-01"));
        } elseif ($type === 'quarter') {
            // 季度查询：取第一个时间范围（当前季度）
            $range = $timeRanges[0];
            $year = $range['year'];
            $startMonth = $range['start_month'];
            $endMonth = $range['end_month'];

            $startDate = date('Y-m-01 00:00:00', strtotime("$year-$startMonth-01"));
            $endDate = date('Y-m-t 23:59:59', strtotime("$year-$endMonth-01"));
        } elseif ($type === 'year') {
            // 年度查询：取第一个时间范围（当前年）
            $range = $timeRanges[0];
            $year = $range['year'];

            $startDate = date('Y-01-01 00:00:00', strtotime("$year-01-01"));
            $endDate = date('Y-12-t 23:59:59', strtotime("$year-12-01"));
        }

        // 构建查询
        if ($isSpecialIndex) {
            if ($indexCatalog['index_name'] === 'sjssysjssbfzfs') {
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
                sum(" . $indexCatalog['index_name'] . "_fm) as fenmu, 
                sum(" . $indexCatalog['index_name'] . "_fz) as fenzi"
            );
        }

        // 添加筛选条件
        if (!empty($AAC11N)) {
            $query->where('AAC11N', $AAC11N);
        }
        if (!empty($AEE03)) {
            $query->where('AEE03', $AEE03);
        }

        $query->whereBetween('AAC01', [$startDate, $endDate])
            ->whereNotNull('AAC11N')
            ->where('AAC11N', '!=', '')
            ->groupBy('AAC11N');

        $res = $query->get();

        // 处理结果，返回科室数据数组
        $departmentData = [];
        foreach ($res as $item) {
            $depId = $item->AAC11N;

            if ($isSpecialIndex) {
                if ($indexCatalog['index_name'] === 'sjssysjssbfzfs') {
                    // 对于特殊指标，需要计算比率作为分子分母
                    // 分母 = 手术室手术时间比率
                    $fenmu = ($item->sjssbfz_fm_sum > 0) ? round($item->sjssbfz_fz_sum / $item->sjssbfz_fm_sum, 4) : 0;
                    // 分子 = 四级手术时间比率
                    $fenzi = ($item->sijssbfz_fm_sum > 0) ? round($item->sijssbfz_fz_sum / $item->sijssbfz_fm_sum, 4) : 0;
                    $departmentData[$depId] = [
                        'fenmu' => $fenmu,
                        'fenzi' => $fenzi
                    ];
                } else {
                    // sjssysjssswlb 指标
                    // 分母 = 手术室手术死亡率
                    $fenmu = ($item->sjsssw_fm_sum > 0) ? round($item->sjsssw_fz_sum / $item->sjsssw_fm_sum, 4) : 0;
                    // 分子 = 四级手术死亡率
                    $fenzi = ($item->sijsssw_fm_sum > 0) ? round($item->sijsssw_fz_sum / $item->sijsssw_fm_sum, 4) : 0;
                    $departmentData[$depId] = [
                        'fenmu' => $fenmu,
                        'fenzi' => $fenzi
                    ];
                }
            } else {
                $fenmu = round(floatval($item->fenmu), 4);
                $fenzi = round(floatval($item->fenzi), 4);
                $departmentData[$depId] = [
                    'fenmu' => $fenmu,
                    'fenzi' => $fenzi
                ];
            }
        }

        return $departmentData;
    }


    public function getAllIndex(Request $request)
    {
        $year = $request->get('year'); //年
        $AAC11N = $request->get('AAC11N');
        $isExport = $request->get('is_export');
        $AEE03 = $request->get('AEE03');
        $cysj_start = $request->get('cysj_start');
        $cysj_end = $request->get('cysj_end');

        // 获取所有指标目录
        $indexCatalogs = IndexCatalog::query()->whereNotNull('url')->get()->toArray();
        if (empty($indexCatalogs)) {
            return ToolsService::returnData(4001, [], '未找到指标配置');
        }

        $allData = [];
        foreach ($indexCatalogs as $indexCatalog) {
            $index_name = $indexCatalog['index_name'];

            // 构建查询
            $query = Indicator::query()->selectRaw(
                "AAC01_YEAR as year,
                AAC01_MONTH as month,
                sum({$index_name}_fm) as fenmu,
                sum({$index_name}_fz) as fenzi"
            );

            // 处理时间范围
            if (!empty($cysj_start) && !empty($cysj_end)) {
                // 将 cysj_start 转换为该月的第一天
                $startDate = date('Y-m-01 00:00:00', strtotime($cysj_start));
                // 将 cysj_end 转换为该月的最后一天
                $endDate = date('Y-m-t 23:59:59', strtotime($cysj_end));

                $query->whereBetween('AAC01', [$startDate, $endDate]);

                // 计算开始和结束月份
                $startMonth = (int) date('m', strtotime($cysj_start));
                $endMonth = (int) date('m', strtotime($cysj_end));
            } else {
                // 如果没有时间范围，则使用全年
                $year = $year ?: date("Y");
                $query->where("AAC01_YEAR", "=", $year);

                // 全年填充12个月
                $startMonth = 1;
                $endMonth = 12;
            }

            // 添加其他筛选条件
            if (!empty($AAC11N)) {
                $query->where('AAC11N', $AAC11N);
            }
            if (!empty($AEE03)) {
                $query->where('AEE03', $AEE03);
            }

            // 按月份分组获取数据
            $res = $query->groupBy("AAC01_MONTH")->get();

            // 初始化数据数组
            $data = [];
            for ($month = $startMonth; $month <= $endMonth; $month++) {
                $data[$month] = [
                    'fenzi' => 0,
                    'fenmu' => 0,
                    'radio' => 0,
                    'month' => $month,
                    'year' => $year,
                    'status' => $indexCatalog['status'] == 1 ? '质控中' : "成功",
                    'update_time' => $indexCatalog['quality_time'] ? date("Y-m-d", $indexCatalog['quality_time']) : '--'
                ];
            }

            // 用查询结果覆盖初始化的数据
            foreach ($res as $re) {
                $month = intval($re->month);
                if (isset($data[$month])) {
                    $data[$month] = [
                        'fenzi' => $re->fenzi,
                        'fenmu' => $re->fenmu,
                        'radio' => $re->fenmu ? number_format(($re->fenzi / $re->fenmu) * 100, 2) : 0,
                        'month' => $month,
                        'year' => $re->year,
                        'status' => $indexCatalog['status'] == 1 ? '质控中' : "成功",
                        'update_time' => $indexCatalog['quality_time'] ? date("Y-m-d", $indexCatalog['quality_time']) : '--'
                    ];
                }
            }

            $resData = array_values($data);

            // 计算平均值
            $fenzi = array_sum(array_column($resData, 'fenzi'));
            $fenmu = array_sum(array_column($resData, 'fenmu'));
            $r = $fenmu ? round($fenzi / $fenmu, 4) * 100 : 0;
            $r = number_format($r, 2);

            // 添加平均值记录
            $count = [
                'fenzi' => $fenzi,
                'fenmu' => $fenmu,
                'radio' => $r,
                'month' => '',
                'year' => '平均值',
                'status' => $indexCatalog['status'] == 1 ? '质控中' : "成功",
                'update_time' => $indexCatalog['quality_time'] ? date("Y-m-d", $indexCatalog['quality_time']) : '--'
            ];
            $resData[] = $count;

            // 只有当有数据时才添加到结果中
            if ($fenmu > 0) {
                $allData[$index_name] = [
                    'name' => $this->getIndicatorName($index_name),
                    'url' => $indexCatalog['url'],
                    'data' => $resData
                ];
            }
        }

        if ($isExport == '1') {
            // 构建表头
            $headers = ['时间'];
            foreach ($allData as $index_name => $indexData) {
                $indicatorName = $this->getIndicatorName($index_name);
                $headers[] = $indicatorName . '分子';
                $headers[] = $indicatorName . '分母';
                $headers[] = $indicatorName . '合格率';
            }
            $exportData = [$headers];

            // 获取所有月份的数据
            $monthData = [];
            foreach ($allData as $index_name => $indexData) {
                foreach ($indexData['data'] as $item) {
                    $timeKey = $item['month'] ? sprintf("\t%04d/%02d", $item['year'], $item['month']) : '平均值';
                    if (!isset($monthData[$timeKey])) {
                        $monthData[$timeKey] = [];
                    }
                    $monthData[$timeKey][$index_name] = [
                        'fenzi' => $item['fenzi'],
                        'fenmu' => $item['fenmu'],
                        'radio' => $item['radio']
                    ];
                }
            }

            // 填充导出数据
            foreach ($monthData as $timeKey => $indicators) {
                $row = [$timeKey];
                foreach ($allData as $index_name => $indexData) {
                    if (isset($indicators[$index_name])) {
                        $row[] = $indicators[$index_name]['fenzi'];
                        $row[] = $indicators[$index_name]['fenmu'];
                        $row[] = $indicators[$index_name]['radio'] . '%';
                    } else {
                        $row[] = 0;
                        $row[] = 0;
                        $row[] = '0%';
                    }
                }
                $exportData[] = $row;
            }

            $csv = new CsvService();
            $filename = sprintf('所有指标统计-%s', date('YmdHis'));
            $csv->filename = $csv->charset($filename, 'UTF-8');
            return $csv->export($exportData);
        }

        return ToolsService::returnData(200, $allData, '成功');
    }

    /**
     * 核心制度指标报表
     * 支持月/季度/年统计，可按科室筛选，并支持导出
     *
     * 请求示例：
     * 1. 按月查询：type=month&period=2026-01
     * 2. 按季度查询：type=quarter&period=2026年第一季度
     * 3. 按年度查询：type=year&period=2026
     * 4. 科室筛选：AAC11N=科室id
     * 5. 导出：is_export=1
     *
     * 返回字段说明：
     * - name：指标名称
     * - radio/current_radio：当前周期指标率
     * - fenzi：分子数量
     * - fenmu：分母数量
     * - prev_radio/previous_radio：上期指标率
     * - chain_ratio：环比
     * - yoy_ratio/same_ratio：同比
     *
     * 返回示例：
     * {
     *   "code": 200,
     *   "msg": "成功",
     *   "data": {
     *     "type": "month",
     *     "period": "2026年01月",
     *     "start_time": "2026-01-01 00:00:00",
     *     "end_time": "2026-01-31 23:59:59",
     *     "previous_period": "2025年12月",
     *     "same_period_last_year": "2025年01月",
     *     "department": {
     *       "id": "1001",
     *       "name": "心内科"
     *     },
     *     "list": [
     *       {
     *         "sort_index": 1,
     *         "index_name": "sjyscf",
     *         "name": "指标3：三级医师查房频次达标率",
     *         "radio": "96.20%",
     *         "radio_value": 96.2,
     *         "fenzi": 481,
     *         "fenmu": 500,
     *         "prev_radio": "95.00%",
     *         "prev_radio_value": 95,
     *         "chain_ratio": "1.26%",
     *         "chain_ratio_value": 1.26,
     *         "yoy_ratio": "2.34%",
     *         "yoy_ratio_value": 2.34
     *       }
     *     ]
     *   }
     * }
     */
    public function getCoreInstitutionReport(Request $request)
    {
        $type = strtolower(trim((string)$request->input('type', '')));
        $period = trim((string)$request->input('period', ''));
        $departmentId = trim((string)$request->input('AAC11N', ''));
        $isExport = (int)$request->input('is_export', 0);

        if (!in_array($type, ['month', 'quarter', 'year'])) {
            return ToolsService::returnData(4001, [], 'type 参数错误，应为 month、quarter 或 year');
        }

        if ($period === '') {
            return ToolsService::returnData(4001, [], 'period 参数不能为空');
        }

        $periodInfo = $this->parseCoreInstitutionReportPeriod($type, $period);
        if (empty($periodInfo)) {
            return ToolsService::returnData(4001, [], 'period 参数格式错误');
        }

        $departmentNames = [];
        $departmentInfo = [];
        $departmentInfos = [];
        if ($departmentId !== '') {
            $departmentInfos = $this->findDepartmentsByRequestValue($departmentId);
            if (empty($departmentInfos)) {
                return ToolsService::returnData(4001, [], '未找到对应科室');
            }
            $departmentInfo = count($departmentInfos) === 1 ? $departmentInfos[0] : [];
            $departmentNames = array_values(array_filter(array_map(function ($item) {
                return trim((string)($item['dep_name'] ?? ''));
            }, $departmentInfos)));
        }

        $indexCatalogs = $this->getCoreInstitutionReportCatalogs();
        if (empty($indexCatalogs)) {
            return ToolsService::returnData(4001, [], '未找到核心制度指标配置');
        }

        $list = [];
        foreach ($indexCatalogs as $index => $indexCatalog) {
            $currentStats = $this->getCoreInstitutionIndicatorStats(
                $indexCatalog,
                $periodInfo['current']['start'],
                $periodInfo['current']['end'],
                $departmentNames
            );
            $prevStats = $this->getCoreInstitutionIndicatorStats(
                $indexCatalog,
                $periodInfo['previous']['start'],
                $periodInfo['previous']['end'],
                $departmentNames
            );
            $yoyStats = $this->getCoreInstitutionIndicatorStats(
                $indexCatalog,
                $periodInfo['yoy']['start'],
                $periodInfo['yoy']['end'],
                $departmentNames
            );

            $currentRadio = $this->normalizeReportNumber($currentStats['radio'], 2);
            $prevRadio = $this->normalizeReportNumber($prevStats['radio'], 2);
            $yoyRadio = $this->normalizeReportNumber($yoyStats['radio'], 2);
            $chainRatio = $this->calculateReportChangeRate($currentRadio, $prevRadio);
            $sameRatio = $this->calculateReportChangeRate($currentRadio, $yoyRadio);

            $list[] = [
                'sort_num' => (int)($indexCatalog['sort_num'] ?? 0),
                'sort_index' => $index + 1,
                'category' => $indexCatalog['url'] ?? '',
                'index_name' => $indexCatalog['index_name'],
                'name' => $indexCatalog['name'],
                'current_radio' => $this->formatReportPercent($currentRadio),
                'current_radio_value' => $currentRadio,
                'radio' => $this->formatReportPercent($currentRadio),
                'radio_value' => $currentRadio,
                'fenzi' => $this->normalizeReportNumber($currentStats['fenzi']),
                'fenmu' => $this->normalizeReportNumber($currentStats['fenmu']),
                'previous_radio' => $this->formatReportPercent($prevRadio),
                'previous_radio_value' => $prevRadio,
                'prev_radio' => $this->formatReportPercent($prevRadio),
                'prev_radio_value' => $prevRadio,
                'chain_ratio' => $this->formatReportPercent($chainRatio),
                'chain_ratio_value' => $chainRatio,
                'yoy_ratio' => $this->formatReportPercent($sameRatio),
                'yoy_ratio_value' => $sameRatio,
                'same_ratio' => $this->formatReportPercent($sameRatio),
                'same_ratio_value' => $sameRatio,
                'current_period' => $periodInfo['current']['label'],
                'previous_period' => $periodInfo['previous']['label'],
                'same_period_last_year' => $periodInfo['yoy']['label'],
            ];
        }

        usort($list, function ($a, $b) {
            if ($a['sort_num'] === $b['sort_num']) {
                return $a['sort_index'] <=> $b['sort_index'];
            }
            return $a['sort_num'] <=> $b['sort_num'];
        });

        foreach ($list as $index => &$item) {
            $item['sort_index'] = $index + 1;
        }
        unset($item);

        if ($isExport === 1) {
            return $this->exportCoreInstitutionReport($list, $type, $periodInfo, $departmentInfo);
        }

        return ToolsService::returnData(200, [
            'type' => $type,
            'period' => $periodInfo['current']['label'],
            'start_time' => $periodInfo['current']['start'],
            'end_time' => $periodInfo['current']['end'],
            'previous_period' => $periodInfo['previous']['label'],
            'same_period_last_year' => $periodInfo['yoy']['label'],
            'department' => !empty($departmentInfo) ? [
                'id' => $departmentInfo['dep_id'] ?? $departmentId,
                'name' => $departmentInfo['dep_name'] ?? '',
            ] : null,
            'departments' => array_map(function ($item) {
                return [
                    'id' => $item['dep_id'] ?? ($item['id'] ?? ''),
                    'name' => $item['dep_name'] ?? '',
                ];
            }, $departmentInfos),
            'list' => $list,
        ], '成功');
    }

    private function getCoreInstitutionReportCatalogs()
    {
        return IndexCatalog::query()
            ->where('pid', 1)
            ->whereNotNull('index_name')
            ->where('index_name', '!=', '')
            ->where(function ($query) {
                $query->whereNull('name')->orWhere('name', 'not like', '----%');
            })
            ->orderBy('sort_num', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();
    }

    private function parseCoreInstitutionReportPeriod($type, $period)
    {
        switch ($type) {
            case 'month':
                if (!preg_match('/^(\d{4})[-\/年]?(\d{1,2})(?:月)?$/u', $period, $matches)) {
                    return [];
                }
                $year = (int)$matches[1];
                $month = (int)$matches[2];
                if ($month < 1 || $month > 12) {
                    return [];
                }

                $currentStart = date('Y-m-01 00:00:00', strtotime(sprintf('%04d-%02d-01', $year, $month)));
                $currentEnd = date('Y-m-t 23:59:59', strtotime($currentStart));

                $prevMonth = $month - 1;
                $prevYear = $year;
                if ($prevMonth < 1) {
                    $prevMonth = 12;
                    $prevYear--;
                }
                $prevStart = date('Y-m-01 00:00:00', strtotime(sprintf('%04d-%02d-01', $prevYear, $prevMonth)));
                $prevEnd = date('Y-m-t 23:59:59', strtotime($prevStart));

                $yoyStart = date('Y-m-01 00:00:00', strtotime(sprintf('%04d-%02d-01', $year - 1, $month)));
                $yoyEnd = date('Y-m-t 23:59:59', strtotime($yoyStart));

                return [
                    'current' => [
                        'start' => $currentStart,
                        'end' => $currentEnd,
                        'label' => sprintf('%04d年%02d月', $year, $month),
                    ],
                    'previous' => [
                        'start' => $prevStart,
                        'end' => $prevEnd,
                        'label' => sprintf('%04d年%02d月', $prevYear, $prevMonth),
                    ],
                    'yoy' => [
                        'start' => $yoyStart,
                        'end' => $yoyEnd,
                        'label' => sprintf('%04d年%02d月', $year - 1, $month),
                    ],
                ];

            case 'quarter':
                if (
                    !preg_match('/^(\d{4})(?:年)?第?([一二三四1234])季度$/u', $period, $matches)
                    && !preg_match('/^(\d{4})[-\/]?Q([1-4])$/i', $period, $matches)
                ) {
                    return [];
                }

                $year = (int)$matches[1];
                $quarterMap = ['一' => 1, '二' => 2, '三' => 3, '四' => 4];
                $quarterRaw = $matches[2];
                $quarter = isset($quarterMap[$quarterRaw]) ? $quarterMap[$quarterRaw] : (int)$quarterRaw;
                if ($quarter < 1 || $quarter > 4) {
                    return [];
                }

                $startMonth = ($quarter - 1) * 3 + 1;
                $endMonth = $startMonth + 2;
                $currentStart = date('Y-m-01 00:00:00', strtotime(sprintf('%04d-%02d-01', $year, $startMonth)));
                $currentEnd = date('Y-m-t 23:59:59', strtotime(sprintf('%04d-%02d-01', $year, $endMonth)));

                $prevQuarter = $quarter - 1;
                $prevYear = $year;
                if ($prevQuarter < 1) {
                    $prevQuarter = 4;
                    $prevYear--;
                }
                $prevStartMonth = ($prevQuarter - 1) * 3 + 1;
                $prevEndMonth = $prevStartMonth + 2;
                $prevStart = date('Y-m-01 00:00:00', strtotime(sprintf('%04d-%02d-01', $prevYear, $prevStartMonth)));
                $prevEnd = date('Y-m-t 23:59:59', strtotime(sprintf('%04d-%02d-01', $prevYear, $prevEndMonth)));

                $yoyStart = date('Y-m-01 00:00:00', strtotime(sprintf('%04d-%02d-01', $year - 1, $startMonth)));
                $yoyEnd = date('Y-m-t 23:59:59', strtotime(sprintf('%04d-%02d-01', $year - 1, $endMonth)));

                return [
                    'current' => [
                        'start' => $currentStart,
                        'end' => $currentEnd,
                        'label' => sprintf('%04d年第%d季度', $year, $quarter),
                    ],
                    'previous' => [
                        'start' => $prevStart,
                        'end' => $prevEnd,
                        'label' => sprintf('%04d年第%d季度', $prevYear, $prevQuarter),
                    ],
                    'yoy' => [
                        'start' => $yoyStart,
                        'end' => $yoyEnd,
                        'label' => sprintf('%04d年第%d季度', $year - 1, $quarter),
                    ],
                ];

            case 'year':
                if (!preg_match('/^(\d{4})(?:年)?$/u', $period, $matches)) {
                    return [];
                }
                $year = (int)$matches[1];

                return [
                    'current' => [
                        'start' => sprintf('%04d-01-01 00:00:00', $year),
                        'end' => sprintf('%04d-12-31 23:59:59', $year),
                        'label' => sprintf('%04d年', $year),
                    ],
                    'previous' => [
                        'start' => sprintf('%04d-01-01 00:00:00', $year - 1),
                        'end' => sprintf('%04d-12-31 23:59:59', $year - 1),
                        'label' => sprintf('%04d年', $year - 1),
                    ],
                    'yoy' => [
                        'start' => sprintf('%04d-01-01 00:00:00', $year - 1),
                        'end' => sprintf('%04d-12-31 23:59:59', $year - 1),
                        'label' => sprintf('%04d年', $year - 1),
                    ],
                ];
        }

        return [];
    }

    private function findDepartmentByRequestValue($departmentId)
    {
        if ($departmentId === '') {
            return [];
        }

        $department = Department::query()
            ->where('dep_id', $departmentId)
            ->first(['id', 'dep_id', 'dep_name']);

        if (empty($department) && ctype_digit($departmentId)) {
            $department = Department::query()
                ->where('id', (int)$departmentId)
                ->first(['id', 'dep_id', 'dep_name']);
        }

        if (empty($department)) {
            $department = Department::query()
                ->where('dep_name', $departmentId)
                ->first(['id', 'dep_id', 'dep_name']);
        }

        return empty($department) ? [] : $department->toArray();
    }

    private function findDepartmentsByRequestValue($departmentIds)
    {
        if (!is_string($departmentIds) || trim($departmentIds) === '') {
            return [];
        }

        $departmentValues = preg_split('/[,\x{FF0C}]+/u', $departmentIds);
        $departmentValues = array_values(array_unique(array_filter(array_map('trim', $departmentValues))));

        if (empty($departmentValues)) {
            return [];
        }

        $departmentInfos = [];
        foreach ($departmentValues as $departmentValue) {
            $departmentInfo = $this->findDepartmentByRequestValue($departmentValue);
            if (empty($departmentInfo)) {
                return [];
            }
            $departmentInfos[$departmentInfo['dep_id'] ?? $departmentValue] = $departmentInfo;
        }

        return array_values($departmentInfos);
    }

    private function getCoreInstitutionIndicatorStats($indexCatalog, $startDate, $endDate, $departmentNames = [])
    {
        $indexName = $indexCatalog['index_name'] ?? '';
        if ($indexName === '') {
            return ['fenzi' => 0, 'fenmu' => 0, 'radio' => 0];
        }

        if ($indexName === 'sjsjsssjkzl') {
            return $this->getSjsjsssjkzlReportStats($startDate, $endDate, $departmentNames);
        }

        $isSpecialIndex = in_array($indexName, ['sjssysjssbfzfs', 'sjssysjssswlb']);
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
                "sum({$indexName}_fm) as fenmu, sum({$indexName}_fz) as fenzi"
            );
        }

        if (!empty($departmentNames)) {
            $query->whereIn('AAC11N', $departmentNames);
        }

        $query->whereBetween('AAC01', [$startDate, $endDate]);
        $res = $query->first();

        if ($isSpecialIndex) {
            if ($indexName === 'sjssysjssbfzfs') {
                $fenmu = ($res && (float)$res->sjssbfz_fm_sum > 0) ? round($res->sjssbfz_fz_sum / $res->sjssbfz_fm_sum, 4) : 0;
                $fenzi = ($res && (float)$res->sijssbfz_fm_sum > 0) ? round($res->sijssbfz_fz_sum / $res->sijssbfz_fm_sum, 4) : 0;
            } else {
                $fenmu = ($res && (float)$res->sjsssw_fm_sum > 0) ? round($res->sjsssw_fz_sum / $res->sjsssw_fm_sum, 4) : 0;
                $fenzi = ($res && (float)$res->sijsssw_fm_sum > 0) ? round($res->sijsssw_fz_sum / $res->sijsssw_fm_sum, 4) : 0;
            }
        } else {
            $fenmu = $res ? round((float)$res->fenmu, 4) : 0;
            $fenzi = $res ? round((float)$res->fenzi, 4) : 0;
        }

        $radio = $fenmu > 0 ? round(($fenzi / $fenmu) * 100, 2) : 0;

        return [
            'fenzi' => $fenzi,
            'fenmu' => $fenmu,
            'radio' => $radio,
        ];
    }

    private function getSjsjsssjkzlReportStats($startDate, $endDate, $departmentNames = [])
    {
        $hasCatalogDate = Schema::hasColumn('surgical_catalog', 'CYSJ');
        $fenzi = 0;
        $fenmu = 0;

        if ($hasCatalogDate) {
            $cursor = strtotime(date('Y-m-01 00:00:00', strtotime($startDate)));
            $limit = strtotime(date('Y-m-01 00:00:00', strtotime($endDate)));

            while ($cursor <= $limit) {
                $monthStr = date('Y-m', $cursor);
                $monthStart = date('Y-m-01 00:00:00', $cursor);
                $monthEnd = date('Y-m-t 23:59:59', $cursor);

                $catalogQuery = DB::table('surgical_catalog');
                if (Schema::hasColumn('surgical_catalog', 'OPE_LEVEL')) {
                    $catalogQuery->whereIn('OPE_LEVEL', [3, 4]);
                }
                $catalogQuery->where('CYSJ', 'like', '%' . $monthStr . '%');

                $monthFenmu = $catalogQuery->count();
                $fenmu += $monthFenmu;

                $catalogNames = DB::table('surgical_catalog')
                    ->when(Schema::hasColumn('surgical_catalog', 'OPE_LEVEL'), function ($query) {
                        $query->whereIn('OPE_LEVEL', [3, 4]);
                    })
                    ->where('CYSJ', 'like', '%' . $monthStr . '%')
                    ->pluck('ICD9_NAME')
                    ->filter()
                    ->toArray();

                $fenzi += $this->countSjsjsssjkzlOperationsByCatalogNames(
                    $catalogNames,
                    $monthStart,
                    $monthEnd,
                    $departmentNames
                );

                $cursor = strtotime('+1 month', $cursor);
            }
        } else {
            $catalogQuery = DB::table('surgical_catalog');
            if (Schema::hasColumn('surgical_catalog', 'OPE_LEVEL')) {
                $catalogQuery->whereIn('OPE_LEVEL', [3, 4]);
            }
            $catalogNames = $catalogQuery->pluck('ICD9_NAME')->filter()->unique()->toArray();
            $fenmu = count($catalogNames);
            $fenzi = $this->countSjsjsssjkzlOperationsByCatalogNames(
                $catalogNames,
                $startDate,
                $endDate,
                $departmentNames
            );
        }

        $fenmu = round((float)$fenmu, 4);
        $fenzi = round((float)$fenzi, 4);

        return [
            'fenzi' => $fenzi,
            'fenmu' => $fenmu,
            'radio' => $fenmu > 0 ? round(($fenzi / $fenmu) * 100, 2) : 0,
        ];
    }

    private function countSjsjsssjkzlOperationsByCatalogNames($catalogNames, $startDate, $endDate, $departmentNames = [])
    {
        if (empty($catalogNames)) {
            return 0;
        }

        $mainOperations = DB::table('main_operation as mo')
            ->join('patient_info as pi', 'mo.AAA28', '=', 'pi.MED_REC_ID')
            ->whereBetween('pi.AAC01', [$startDate, $endDate])
            ->whereIn('mo.OPE_LEVEL', [3, 4])
            ->when(!empty($departmentNames), function ($query) use ($departmentNames) {
                $query->whereIn('pi.AAC11N', $departmentNames);
            })
            ->whereIn('mo.ICD9_NAME', $catalogNames)
            ->count();

        $secondaryOperations = DB::table('secondary_operation as so')
            ->join('patient_info as pi', 'so.AAA28', '=', 'pi.MED_REC_ID')
            ->whereBetween('pi.AAC01', [$startDate, $endDate])
            ->whereIn('so.OPE_LEVEL', [3, 4])
            ->when(!empty($departmentNames), function ($query) use ($departmentNames) {
                $query->whereIn('pi.AAC11N', $departmentNames);
            })
            ->whereIn('so.ICD9_NAME', $catalogNames)
            ->count();

        return $mainOperations + $secondaryOperations;
    }

    private function calculateReportChangeRate($currentValue, $baseValue)
    {
        $currentValue = (float)$currentValue;
        $baseValue = (float)$baseValue;

        if ($baseValue == 0.0) {
            return $currentValue > 0 ? 100.00 : 0.00;
        }

        return round((($currentValue - $baseValue) / $baseValue) * 100, 2);
    }

    private function formatReportPercent($value)
    {
        return number_format((float)$value, 2) . '%';
    }

    private function normalizeReportNumber($value, $precision = 4)
    {
        $value = round((float)$value, $precision);
        if (abs($value - round($value)) < 0.0001) {
            return (int)round($value);
        }
        return $value;
    }

    private function exportCoreInstitutionReport($list, $type, $periodInfo, $departmentInfo = [])
    {
        $exportData = [];
        $exportData[] = ['序号', '指标名称', '指标率', '分子', '分母', '上期指标率', '环比', '同比'];

        foreach ($list as $item) {
            $exportData[] = [
                $item['sort_index'],
                $item['name'],
                $item['radio'],
                $item['fenzi'],
                $item['fenmu'],
                $item['prev_radio'],
                $item['chain_ratio'],
                $item['same_ratio'],
            ];
        }

        $filename = '核心制度指标报表';
        if (!empty($departmentInfo['dep_name'])) {
            $filename .= '-' . $departmentInfo['dep_name'];
        }
        $filename .= '-' . $periodInfo['current']['label'] . '-' . $type;

        $csv = new CsvService();
        $csv->filename = $csv->charset($filename, 'UTF-8');
        return $csv->export($exportData);
    }

    /**
     * 核心制度指标达成率最优前五科室
     */
    public function getCoreInstitutionTopDepartments(Request $request)
    {
        return $this->getCoreInstitutionDepartmentRankReport($request, 'desc');
    }

    /**
     * 核心制度指标达成率最差后五科室
     */
    public function getCoreInstitutionBottomDepartments(Request $request)
    {
        return $this->getCoreInstitutionDepartmentRankReport($request, 'asc');
    }

    private function getCoreInstitutionDepartmentRankReport(Request $request, $direction = 'desc')
    {
        $type = strtolower(trim((string)$request->input('type', '')));
        $period = trim((string)$request->input('period', ''));
        $isExport = (int)$request->input('is_export', 0);

        if (!in_array($type, ['month', 'quarter', 'year'])) {
            return ToolsService::returnData(4001, [], 'type 参数错误，应为 month、quarter 或 year');
        }

        if ($period === '') {
            return ToolsService::returnData(4001, [], 'period 参数不能为空');
        }

        $periodInfo = $this->parseCoreInstitutionReportPeriod($type, $period);
        if (empty($periodInfo)) {
            return ToolsService::returnData(4001, [], 'period 参数格式错误');
        }

        $indexCatalogs = $this->getCoreInstitutionReportCatalogs();
        if (empty($indexCatalogs)) {
            return ToolsService::returnData(4001, [], '未找到核心制度指标配置');
        }

        $list = [];
        foreach ($indexCatalogs as $index => $indexCatalog) {
            $departmentStats = $this->getCoreInstitutionIndicatorDepartmentStats(
                $indexCatalog,
                $periodInfo['current']['start'],
                $periodInfo['current']['end']
            );

            $rankings = [];
            foreach ($departmentStats as $departmentName => $stats) {
                $fenmu = $this->normalizeReportNumber($stats['fenmu']);
                $fenzi = $this->normalizeReportNumber($stats['fenzi']);
                $radioValue = $this->normalizeReportNumber($stats['radio'], 2);

                $rankings[] = [
                    'dep_name' => $departmentName,
                    'radio' => $this->formatReportPercent($radioValue),
                    'radio_value' => $radioValue,
                    'fenzi' => $fenzi,
                    'fenmu' => $fenmu,
                ];
            }

            usort($rankings, function ($a, $b) use ($direction) {
                if ($a['radio_value'] == $b['radio_value']) {
                    return strcmp($a['dep_name'], $b['dep_name']);
                }
                if ($direction === 'asc') {
                    return $a['radio_value'] <=> $b['radio_value'];
                }
                return $b['radio_value'] <=> $a['radio_value'];
            });

            $rankings = array_slice($rankings, 0, 5);
            $list[] = $this->buildCoreInstitutionDepartmentRankRow(
                $indexCatalog,
                $index + 1,
                $rankings,
                $direction
            );
        }

        usort($list, function ($a, $b) {
            if ($a['sort_num'] === $b['sort_num']) {
                return $a['sort_index'] <=> $b['sort_index'];
            }
            return $a['sort_num'] <=> $b['sort_num'];
        });

        foreach ($list as $index => &$item) {
            $item['sort_index'] = $index + 1;
        }
        unset($item);

        if ($isExport === 1) {
            return $this->exportCoreInstitutionDepartmentRankReport($list, $type, $periodInfo, $direction);
        }

        return ToolsService::returnData(200, [
            'type' => $type,
            'period' => $periodInfo['current']['label'],
            'start_time' => $periodInfo['current']['start'],
            'end_time' => $periodInfo['current']['end'],
            'direction' => $direction === 'asc' ? 'bottom' : 'top',
            'title' => $direction === 'asc' ? '指标达成率最差前5名' : '指标达成率最优前5名',
            'list' => $list,
        ], '成功');
    }

    private function getCoreInstitutionIndicatorDepartmentStats($indexCatalog, $startDate, $endDate)
    {
        $indexName = $indexCatalog['index_name'] ?? '';
        if ($indexName === '') {
            return [];
        }

        if ($indexName === 'sjsjsssjkzl') {
            return $this->getSjsjsssjkzlDepartmentStats($startDate, $endDate);
        }

        $isSpecialIndex = in_array($indexName, ['sjssysjssbfzfs', 'sjssysjssswlb']);
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
                "AAC11N, sum({$indexName}_fm) as fenmu, sum({$indexName}_fz) as fenzi"
            );
        }

        $rows = $query->whereBetween('AAC01', [$startDate, $endDate])
            ->whereNotNull('AAC11N')
            ->where('AAC11N', '!=', '')
            ->groupBy('AAC11N')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $departmentName = trim((string)$row->AAC11N);
            if ($departmentName === '') {
                continue;
            }

            if ($isSpecialIndex) {
                if ($indexName === 'sjssysjssbfzfs') {
                    $fenmu = ((float)$row->sjssbfz_fm_sum > 0) ? round($row->sjssbfz_fz_sum / $row->sjssbfz_fm_sum, 4) : 0;
                    $fenzi = ((float)$row->sijssbfz_fm_sum > 0) ? round($row->sijssbfz_fz_sum / $row->sijssbfz_fm_sum, 4) : 0;
                } else {
                    $fenmu = ((float)$row->sjsssw_fm_sum > 0) ? round($row->sjsssw_fz_sum / $row->sjsssw_fm_sum, 4) : 0;
                    $fenzi = ((float)$row->sijsssw_fm_sum > 0) ? round($row->sijsssw_fz_sum / $row->sijsssw_fm_sum, 4) : 0;
                }
            } else {
                $fenmu = round((float)$row->fenmu, 4);
                $fenzi = round((float)$row->fenzi, 4);
            }

            $result[$departmentName] = [
                'fenzi' => $fenzi,
                'fenmu' => $fenmu,
                'radio' => $fenmu > 0 ? round(($fenzi / $fenmu) * 100, 2) : 0,
            ];
        }

        return $result;
    }

    private function getSjsjsssjkzlDepartmentStats($startDate, $endDate)
    {
        $departmentStats = [];
        $hasCatalogDate = Schema::hasColumn('surgical_catalog', 'CYSJ');
        $hasCatalogDepartment = Schema::hasColumn('surgical_catalog', 'AAC11N');

        if ($hasCatalogDate && $hasCatalogDepartment) {
            $cursor = strtotime(date('Y-m-01 00:00:00', strtotime($startDate)));
            $limit = strtotime(date('Y-m-01 00:00:00', strtotime($endDate)));

            while ($cursor <= $limit) {
                $monthStr = date('Y-m', $cursor);
                $monthStart = date('Y-m-01 00:00:00', $cursor);
                $monthEnd = date('Y-m-t 23:59:59', $cursor);

                $catalogRows = DB::table('surgical_catalog')
                    ->where('CYSJ', 'like', '%' . $monthStr . '%')
                    ->when(Schema::hasColumn('surgical_catalog', 'OPE_LEVEL'), function ($query) {
                        $query->whereIn('OPE_LEVEL', [3, 4]);
                    })
                    ->get(['AAC11N', 'ICD9_NAME']);

                $monthCatalogNames = $catalogRows->pluck('ICD9_NAME')->filter()->toArray();

                foreach ($catalogRows as $catalogRow) {
                    $departmentName = trim((string)($catalogRow->AAC11N ?? ''));
                    if ($departmentName === '') {
                        continue;
                    }
                    if (!isset($departmentStats[$departmentName])) {
                        $departmentStats[$departmentName] = ['fenzi' => 0, 'fenmu' => 0, 'radio' => 0];
                    }
                    $departmentStats[$departmentName]['fenmu']++;
                }

                $this->mergeSjsjsssjkzlOperationStatsByDepartment(
                    $departmentStats,
                    $monthCatalogNames,
                    $monthStart,
                    $monthEnd
                );

                $cursor = strtotime('+1 month', $cursor);
            }
        } else {
            $catalogQuery = DB::table('surgical_catalog');
            if (Schema::hasColumn('surgical_catalog', 'OPE_LEVEL')) {
                $catalogQuery->whereIn('OPE_LEVEL', [3, 4]);
            }
            $catalogNames = $catalogQuery->pluck('ICD9_NAME')->filter()->unique()->toArray();
            $this->buildSjsjsssjkzlFallbackDepartmentStats(
                $departmentStats,
                $catalogNames,
                $startDate,
                $endDate
            );
        }

        foreach ($departmentStats as $departmentName => &$stats) {
            $stats['fenzi'] = round((float)$stats['fenzi'], 4);
            $stats['fenmu'] = round((float)$stats['fenmu'], 4);
            $stats['radio'] = $stats['fenmu'] > 0 ? round(($stats['fenzi'] / $stats['fenmu']) * 100, 2) : 0;
        }
        unset($stats);

        return $departmentStats;
    }

    private function mergeSjsjsssjkzlOperationStatsByDepartment(&$departmentStats, $catalogNames, $startDate, $endDate)
    {
        if (empty($catalogNames)) {
            return;
        }

        $mainOperations = DB::table('main_operation as mo')
            ->join('patient_info as pi', 'mo.AAA28', '=', 'pi.MED_REC_ID')
            ->whereBetween('pi.AAC01', [$startDate, $endDate])
            ->whereIn('mo.OPE_LEVEL', [3, 4])
            ->whereIn('mo.ICD9_NAME', $catalogNames)
            ->select('pi.AAC11N as dep_name')
            ->get();

        $secondaryOperations = DB::table('secondary_operation as so')
            ->join('patient_info as pi', 'so.AAA28', '=', 'pi.MED_REC_ID')
            ->whereBetween('pi.AAC01', [$startDate, $endDate])
            ->whereIn('so.OPE_LEVEL', [3, 4])
            ->whereIn('so.ICD9_NAME', $catalogNames)
            ->select('pi.AAC11N as dep_name')
            ->get();

        foreach ($mainOperations->merge($secondaryOperations) as $operation) {
            $departmentName = trim((string)($operation->dep_name ?? ''));
            if ($departmentName === '') {
                continue;
            }
            if (!isset($departmentStats[$departmentName])) {
                $departmentStats[$departmentName] = ['fenzi' => 0, 'fenmu' => 0, 'radio' => 0];
            }
            $departmentStats[$departmentName]['fenzi']++;
        }
    }

    private function buildSjsjsssjkzlFallbackDepartmentStats(&$departmentStats, $catalogNames, $startDate, $endDate)
    {
        $mainQuery = DB::table('main_operation as mo')
            ->join('patient_info as pi', 'mo.AAA28', '=', 'pi.MED_REC_ID')
            ->whereBetween('pi.AAC01', [$startDate, $endDate])
            ->whereIn('mo.OPE_LEVEL', [3, 4]);

        $secondaryQuery = DB::table('secondary_operation as so')
            ->join('patient_info as pi', 'so.AAA28', '=', 'pi.MED_REC_ID')
            ->whereBetween('pi.AAC01', [$startDate, $endDate])
            ->whereIn('so.OPE_LEVEL', [3, 4]);

        $mainRows = $mainQuery->select('pi.AAC11N as dep_name', 'mo.ICD9_NAME')->get();
        $secondaryRows = $secondaryQuery->select('pi.AAC11N as dep_name', 'so.ICD9_NAME')->get();

        foreach ($mainRows->merge($secondaryRows) as $row) {
            $departmentName = trim((string)($row->dep_name ?? ''));
            if ($departmentName === '') {
                continue;
            }
            if (!isset($departmentStats[$departmentName])) {
                $departmentStats[$departmentName] = ['fenzi' => 0, 'fenmu' => 0, 'radio' => 0];
            }
            $departmentStats[$departmentName]['fenmu']++;
            if (!empty($catalogNames) && in_array($row->ICD9_NAME, $catalogNames)) {
                $departmentStats[$departmentName]['fenzi']++;
            }
        }
    }

    private function buildCoreInstitutionDepartmentRankRow($indexCatalog, $sortIndex, $rankings, $direction)
    {
        $labels = $direction === 'asc'
            ? ['第一名（末位）', '第二名（末位）', '第三名（末位）', '第四名（末位）', '第五名（末位）']
            : ['第一名', '第二名', '第三名', '第四名', '第五名'];

        $keys = ['first', 'second', 'third', 'fourth', 'fifth'];
        $row = [
            'sort_num' => (int)($indexCatalog['sort_num'] ?? 0),
            'sort_index' => $sortIndex,
            'index_name' => $indexCatalog['index_name'],
            'name' => $indexCatalog['name'],
            'direction' => $direction === 'asc' ? 'bottom' : 'top',
            'rankings' => [],
        ];

        foreach ($keys as $index => $key) {
            $ranking = $rankings[$index] ?? null;
            $display = $ranking ? ($ranking['dep_name'] . ' ' . $ranking['radio']) : '';
            $row[$key] = $display;
            $row[$key . '_label'] = $labels[$index];
            $row[$key . '_data'] = $ranking ?: null;
            if ($ranking) {
                $row['rankings'][] = [
                    'rank' => $index + 1,
                    'label' => $labels[$index],
                    'dep_name' => $ranking['dep_name'],
                    'radio' => $ranking['radio'],
                    'radio_value' => $ranking['radio_value'],
                    'fenzi' => $ranking['fenzi'],
                    'fenmu' => $ranking['fenmu'],
                ];
            }
        }

        return $row;
    }

    private function exportCoreInstitutionDepartmentRankReport($list, $type, $periodInfo, $direction)
    {
        $headers = $direction === 'asc'
            ? ['序号', '指标名称', '第一名（末位）', '第二名（末位）', '第三名（末位）', '第四名（末位）', '第五名（末位）']
            : ['序号', '指标名称', '第一名', '第二名', '第三名', '第四名', '第五名'];

        $exportData = [$headers];
        foreach ($list as $item) {
            $exportData[] = [
                $item['sort_index'],
                $item['name'],
                $item['first'],
                $item['second'],
                $item['third'],
                $item['fourth'],
                $item['fifth'],
            ];
        }

        $filename = $direction === 'asc' ? '核心制度指标后五科室' : '核心制度指标前五科室';
        $filename .= '-' . $periodInfo['current']['label'] . '-' . $type;

        $csv = new CsvService();
        $csv->filename = $csv->charset($filename, 'UTF-8');
        return $csv->export($exportData);
    }


    /**
     * 重新计算指标。
     *
     * @param Request $request
     * @param QualityIndexRecalculationService $recalculationService
     * @return array
     */
    public function reCalculateIndex(
        Request $request,
        QualityIndexRecalculationService $recalculationService
    ) {
        $categoryValue = $request->post('category');
        $category = is_scalar($categoryValue) ? trim((string) $categoryValue) : '';
        if ($category === '') {
            return ToolsService::returnData(4001, [], '缺少类别参数');
        }

        $inpatientNumberValue = $request->post('zyh');
        $inpatientNumber = is_scalar($inpatientNumberValue)
            ? trim((string) $inpatientNumberValue)
            : null;
        if ($inpatientNumber === '') {
            $inpatientNumber = null;
        }

        $indexName = null;
        $startTime = null;
        $endTime = null;

        try {
            $dateRange = $this->normalizeRecalculationDateRange(
                $request->post('cysj_start'),
                $request->post('cysj_end')
            );
        } catch (\InvalidArgumentException $exception) {
            return ToolsService::returnData(4001, [], $exception->getMessage());
        }

        $startTime = $dateRange['start_time'];
        $endTime = $dateRange['end_time'];

        try {
            $indexCatalog = IndexCatalog::where('url', $category)->first();
            if (!$indexCatalog) {
                return ToolsService::returnData(4001, [], '未找到指标配置');
            }

            $indexName = trim((string) $indexCatalog->index_name);
            $recalculationResult = $recalculationService->recalculate(
                $indexName,
                $inpatientNumber,
                $startTime,
                $endTime
            );

            if (empty($recalculationResult['supported'])) {
                return ToolsService::returnData(4001, [], '暂不支持该指标的重新计算');
            }

            Log::info('质量指标重新计算完成', [
                'index_name' => $indexName,
                'category' => $category,
                'zyh' => $inpatientNumber,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'handler' => $recalculationResult['handler'] ?? null,
                'command' => $recalculationResult['command'] ?? null,
            ]);

            return ToolsService::returnData(200, [], $indexCatalog->name . '重新计算完成');
        } catch (\Throwable $throwable) {
            Log::error('质量指标重新计算失败', [
                'message' => $throwable->getMessage(),
                'index_name' => $indexName,
                'category' => $category,
                'zyh' => $inpatientNumber,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ]);

            return ToolsService::returnData(5000, [], '指标计算失败，请稍后重试');
        }
    }

    /**
     * 规范化指标重新计算的日期范围。
     *
     * @param mixed $startDate 开始日期，为空时默认当前年份第一天
     * @param mixed $endDate 结束日期，为空时默认当天
     * @return array 包含 start_time 和 end_time 的日期范围
     * @throws \InvalidArgumentException 日期无效或开始日期晚于结束日期时抛出
     */
    private function normalizeRecalculationDateRange($startDate, $endDate): array
    {
        $normalizedStartDate = is_scalar($startDate) ? trim((string) $startDate) : '';
        $normalizedEndDate = is_scalar($endDate) ? trim((string) $endDate) : '';

        if ($normalizedStartDate === '') {
            $startTime = date('Y-01-01 00:00:00');
        } else {
            $startTimestamp = strtotime($normalizedStartDate);
            if ($startTimestamp === false) {
                throw new \InvalidArgumentException('开始日期格式无效');
            }

            $startTime = date('Y-m-d 00:00:00', $startTimestamp);
        }

        if ($normalizedEndDate === '') {
            $endTime = date('Y-m-d 23:59:59');
        } else {
            $endTimestamp = strtotime($normalizedEndDate);
            if ($endTimestamp === false) {
                throw new \InvalidArgumentException('结束日期格式无效');
            }

            $endTime = date('Y-m-d 23:59:59', $endTimestamp);
        }

        if (strtotime($startTime) > strtotime($endTime)) {
            throw new \InvalidArgumentException('开始日期不能晚于结束日期');
        }

        return [
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];
    }

    /**
     * 指标列表分子，分母下钻列表接口
     */
    public function getDetailIndex(Request $request)
    {
        $isExport = $request->post('is_export');
        $category = $request->post('category');
        $AAC11N = $request->post('AAC11N');
        $AEE03 = $request->post('AEE03');
        $status = $request->post('status');
        $cysj_start = $request->post('cysj_start');
        $cysj_end = $request->post('cysj_end');
        $zyh = $request->post('zyh');
        $page = $request->post('page', 1);
        $pageSize = $request->post('pagesize', 100);

        $indexCatalog = IndexCatalog::query()->where("url", "=", $category)->first();
        if (!$indexCatalog) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }

        $index_name = $indexCatalog['index_name'];
        $fenzi = $index_name . "_fz";
        $fenmu = $index_name . "_fm";
        $error = $index_name . "_error";

        // 使用 MySQL 查询（indicator 表 join patient_info 补充住院号码 AAA28、患者姓名 AAA01）
        // 构建基础查询条件闭包，供计数、分页、导出复用
        $buildQuery = function () use ($index_name, $fenzi, $fenmu, $error, $AAC11N, $AEE03, $zyh, $status, $cysj_start, $cysj_end) {
            $query = DB::table('indicator')
                ->leftJoin('patient_info', 'indicator.zyh', '=', 'patient_info.MED_REC_ID');

            if (!empty($AAC11N)) {
                $query->where('indicator.AAC11N', $AAC11N);
            }
            if (!empty($AEE03)) {
                $query->where('indicator.AEE03', $AEE03);
            }
            if (!empty($zyh)) {
                // ES 中按 AAA28（住院号码）过滤，对应 patient_info.AAA28
                $query->where('patient_info.AAA28', $zyh);
            }

            // 确保 error 字段不为 null 且分母大于 0
            $query->whereNotNull('indicator.' . $error);
            $query->where('indicator.' . $fenmu, '>', 0);

            // 根据 status 构建查询条件
            $statusStr = (string) $status;
            if ($statusStr !== '') {
                if ($index_name != 'sjsjsssjkzl') {
                    switch ($statusStr) {
                        case '1': // 正确：分子 == 分母
                            $query->whereColumn('indicator.' . $fenzi, '=', 'indicator.' . $fenmu);
                            break;
                        case '3': // 错误：分子 == 0
                            $query->where('indicator.' . $fenzi, '=', 0);
                            break;
                        case '2': // 警告：0 < 分子 < 分母
                            $query->where('indicator.' . $fenzi, '>', 0);
                            $query->whereColumn('indicator.' . $fenzi, '<', 'indicator.' . $fenmu);
                            break;
                    }
                }
            }

            if (!empty($cysj_start) && !empty($cysj_end)) {
                $query->whereBetween('indicator.AAC01', [
                    date('Y-m-d 00:00:00', strtotime($cysj_start)),
                    date('Y-m-d 23:59:59', strtotime($cysj_end))
                ]);
            }

            return $query;
        };

        // sjsjsssjkzl 指标在无 status 时直接返回空
        if ((string) $status === '' && $index_name == 'sjsjsssjkzl') {
            return ToolsService::returnData(200, [], '');
        }

        // 统一查询字段（对应原 ES _source）
        $selectFields = [
            'indicator.' . $fenzi . ' as fz_val',
            'indicator.' . $fenmu . ' as fm_val',
            'indicator.' . $error . ' as error_val',
            'patient_info.AAA28 as AAA28',
            'indicator.zyh as zyh',
            'indicator.AAC11N as AAC11N',
            'indicator.AEE03 as AEE03',
            'indicator.AAC01 as AAC01',
            'patient_info.AAA01 as AAA01',
        ];

        // 导出时获取全部数据
        if ($isExport == '1') {
            $allData = $buildQuery()
                ->select($selectFields)
                ->orderBy('indicator.AAC01', 'desc')
                ->get()
                ->map(function ($row) {
                    return (array) $row;
                })
                ->toArray();

            // 格式化导出数据
            $tableHeader = ['住院号码', '住院号', '患者姓名', '出院时间', '出院科室', '主治医师', '指标状态', '指标名称', '指标详情'];
            // jhzjsdwl（急会诊及时到位率）、pthzjswcl（普通会诊及时完成率）额外导出会诊科室、会诊医师两列
            if ($index_name === 'jhzjsdwl' || $index_name === 'pthzjswcl') {
                $tableHeader = ['住院号码', '住院号', '患者姓名', '出院时间', '出院科室', '主治医师', '会诊科室', '会诊医师', '指标状态', '指标名称', '指标详情'];
            }
            $exportData[] = $tableHeader;
            foreach ($allData as $source) {
                $detailContentStr = json_decode($source['error_val'], true);

                // jhzjsdwl（急会诊及时到位率）、pthzjswcl（普通会诊及时完成率）：
                // error 为多组会诊数据，导出时按会诊组拆分为多行，
                // 每行仅含一组会诊，指标状态与指标详情按该组单独计算，其余列保持一致
                if (($index_name === 'jhzjsdwl' || $index_name === 'pthzjswcl') && is_array($detailContentStr) && !empty($detailContentStr)) {
                    foreach ($detailContentStr as $item) {
                        // 单组状态：该组 status==1 记为正确（准时到位），否则错误
                        $groupStatus = (isset($item['status']) && $item['status'] == 1) ? '正确' : '错误';

                        // 从该组 content 中提取「会诊科室」「会诊医师」，用于单独成列
                        $hzks = '';
                        $hzys = '';
                        $detailContent = '"';
                        $detailContent .= $groupStatus . PHP_EOL;
                        if (!empty($item['content'])) {
                            foreach ($item['content'] as $v2) {
                                $text = $v2['content'];
                                // 会诊科室【xxx】
                                if ($hzks === '' && mb_strpos($text, '会诊科室') === 0) {
                                    if (preg_match('/【(.*?)】/u', $text, $m)) {
                                        $hzks = $m[1];
                                    }
                                }
                                // 会诊医师【xxx（code）】
                                if ($hzys === '' && mb_strpos($text, '会诊医师') === 0) {
                                    if (preg_match('/【(.*?)】/u', $text, $m)) {
                                        $hzys = $m[1];
                                    }
                                }
                                $detailContent .= '{' . ($v2['status'] == 1 ? '正确' : '错误') . '}' . $text . PHP_EOL;
                            }
                        }
                        $detailContent .= '"';

                        $exportData[] = [
                            $source['AAA28'],
                            $source['zyh'],
                            $source['AAA01'],
                            $source['AAC01'],
                            $source['AAC11N'],
                            $source['AEE03'],
                            $hzks,
                            $hzys,
                            $groupStatus,
                            $indexCatalog["name"],
                            $detailContent,
                        ];
                    }
                    continue;
                }

                $status1 = $this->checkStatus($source['fz_val'], $source['fm_val']);
                $detailContent = '"';

                if (!empty($detailContentStr)) {
                    foreach ($detailContentStr as $item) {
                        if ($item['status'] == '1') {
                            $detailContent .= '正确' . PHP_EOL;
                        } else {
                            $detailContent .= '错误' . PHP_EOL;
                        }
                        foreach ($item['content'] as $v2) {
                            $detailContent .= '{' . ($v2['status'] == 1 ? '正确' : '错误') . '}' . $v2['content'] . PHP_EOL;
                        }
                    }
                }
                $detailContent .= '"';
                $exportData[] = [
                    $source['AAA28'],
                    $source['zyh'],
                    $source['AAA01'],
                    $source['AAC01'],
                    $source['AAC11N'],
                    $source['AEE03'],
                    $status1,
                    $indexCatalog["name"],
                    $detailContent,
                ];
            }

            $csv = new CsvService();
            $csv->filename = $csv->charset($indexCatalog["name"] . '--指标详情', 'UTF-8');
            return $csv->export($exportData);
        }

        // 非导出模式：MySQL 分页查询
        // 计算总行数
        $total = $buildQuery()->count();

        // 获取当前页数据（按出院时间倒序，id 升序保证稳定排序）
        $data = $buildQuery()
            ->select($selectFields)
            ->orderBy('indicator.AAC01', 'desc')
            ->orderBy('indicator.id', 'asc')
            ->forPage($page, $pageSize)
            ->get()
            ->map(function ($row) {
                return (array) $row;
            })
            ->toArray();

        $formattedItems = [];
        foreach ($data as $source) {
            $status1 = $this->checkStatus($source['fz_val'], $source['fm_val']);

            $formattedItems[] = [
                'zyh' => $source['zyh'],
                'name' => $this->getIndicatorName($index_name),
                'AAA28' => $source['AAA28'] ?? '',
                'AAA01' => $source['AAA01'] ?? '',
                'AAC01' => $source['AAC01'] ?? '',
                'AAC11N' => $source['AAC11N'] ?? '',
                'AEE03' => $source['AEE03'] ?? '',
                'status' => $status1,
                'fenzi' => $source['fz_val'],
                'fenmu' => $source['fm_val'],
                'error' => json_decode($source['error_val'], true),
                'update_time' => $indexCatalog['quality_time'] ? date("Y-m-d H:i:s", $indexCatalog['quality_time']) : '--',
            ];
        }

        return ToolsService::returnData(200, ['data' => $formattedItems, 'total' => $total], '成功');
    }

    //检查指标状态
    public function checkStatus($fenzi, $fenmu)
    {
        if ($fenzi == $fenmu) {
            return '正确';
        } elseif ($fenzi == 0 && $fenzi < $fenmu) {
            return '错误';
        } elseif ($fenzi > 0 && $fenzi < $fenmu) {
            return '警告';
        }
        return '未知';
    }

    /**
     * @param Request $request
     * @return array
     * 质量控制指标
     */
    public function getList(Request $request)
    {
        $year = $request->get('year'); //年
        $type = $request->get('type'); //0月 1天
        $isExport = $request->get('is_export'); //是否导出
        $category = $request->get('category'); //类别
        $month = $request->get('month');

        $zhuangtai = $request->get('zhuangtai');
        $chuyuankesi = $request->get('chuyuankesi');
        $zyh = $request->get('zyh');
        $perPage = $request->get('perpage'); //分页
        $chuyuanshijianOrder = $request->get('chuyuanshijian_order'); //分页

        $where = [];
        if ($year) {
            $where[] = ['year', '=', $year];
        }

        if ($month) {
            $where[] = ['month', '=', $month];
        }

        if ($category) {
            $where[] = ['category', '=', $category];
        }

        if ($zyh) {
            $where[] = ['zhuyuanhao', '=', $zyh];
        }

        if ($zhuangtai) {
            $where[] = ['zhuangtai', '=', $zhuangtai];
        }

        if ($zhuangtai === "0") {
            $where[] = ['zhuangtai', '=', 0];
        }

        if ($chuyuankesi) {
            $where[] = ['chuyuankesi', '=', $chuyuankesi];
        }


        $cap = new QualityIndexService();

        $list = $cap->getList($where, $type, $isExport, $perPage, $chuyuanshijianOrder, $year);


        if ($isExport == 1) {
            if ($category == 1) {
                $category = '低危CAP患者住院比例';
            } elseif ($category == 2) {
                $category = '住院成人社区获得肺炎（CAP）患者进行CAP严重程度评估的比例';
            } elseif ($category == 3) {
                $category = '初产妇剖宫产率';
            } elseif ($category == 4) {
                $category = '日常生活活动能力(ADL)改善率(REH-ADL-01)';
            } elseif ($category == 5) {
                $category = '脊髓损伤患者ADL改善率(REH-ADL-02)';
            } elseif ($category == 6) {
                $category = '脑卒中患者 ADL 改善率(REH-ADL-03)';
            } elseif ($category == 7) {
                $category = '脑卒中患者运动功能评定率(REH-EVA-01)';
            } elseif ($category == 8) {
                $category = '脑卒中患者吞咽功能评定率(REH-EVA-03)';
            } elseif ($category == 9) {
                $category = '脊髓损伤患者神经功能评定率(REH-EVA-04)';
            } elseif ($category == 10) {
                $category = '住院成人社区获得性肺炎（CAP）患者进行CAP严重程度评估的比例';
            }

            if ($type == 1) {


                $cata = IndexCatalog::query()->whereNotNull('url')->get()->toArray();
                $cataArr = [];
                foreach ($cata as $cataItem) {
                    $cataArr[$cataItem['url']] = $cataItem;
                }


                $exportData[] = ['时间', '指标率', '分子名', '分子', '分母名', '分母', '来源'];

                foreach ($list as $key => $val) {
                    if (intval($val['month']) > 0) {
                        $yearStr = $val['year'] . '-' . $val['month'];
                    } else {
                        $yearStr = $val['year'];
                    }


                    $exportData[$key + 1] = [" " . $yearStr, $val['radio'] . '%', isset($cataArr[$val['category']]['fenzi_name']) ? $cataArr[$val['category']]['fenzi_name'] : '', $val['fenzi'], isset($cataArr[$val['category']]['fenmu_name']) ? $cataArr[$val['category']]['fenmu_name'] : '', $val['fenmu'], '系统'];
                }

                $csv = new CsvService();
                $csv->filename = $csv->charset($category . '--指标', 'UTF-8');
                return $csv->export($exportData);
            } else {
                $exportData[] = ['序号', '住院号码', '出院时间', '患者姓名', '出院科室', '状态', '计算详情', '来源'];

                $num = 0;
                foreach ($list as $key => $val) {
                    $exportData[$key + 1] = [++$num, "\t{$val['zhuyuanhao']}", $val['chuyuanshijian'], $val['xingming'], $val['chuyuankesi'], $val['zhuangtai'] ? '正确' : '错误', $val['jisuanxiangqing'], '系统'];
                }

                $csv = new CsvService();
                $csv->filename = $csv->charset($category . '--指标详情', 'UTF-8');
                return $csv->export($exportData);
            }
        }


        return ToolsService::returnData(200, $list, $msg ?? '');
    }

    public function getStaff(Request $request)
    {
        $ygjb = $request->get('ygjb');
        $ygjb = $ygjb ?: '主治医师';

        $list = Staff::query()->where("ygjb_text", '=', $ygjb)->get()->toArray();

        return ToolsService::returnData(200, $list, $msg ?? '');
    }

    public function getKesi()
    {
        $data = [];

        $list = BA_SYSB_ZDDZ::query()->get();
        foreach ($list as $item) {
            if (in_array($item['HISZ'], [2, 3, 222, 3056, 224])) {
                continue;
            }
            if (empty($item['WSTZ'])) {
                continue;
            }

            $data[] = $item;
        }

        return ToolsService::returnData(200, $data, $msg ?? '');
    }

    public function getInfo(Request $request)
    {
        $zhuyuanhao = $request->post('zhuyuanhao');
        $category = $request->post('category');

        if (empty($zhuyuanhao) || empty($category)) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }

        $ser = new QualityIndexService();
        $data = $ser->getInfo($zhuyuanhao, $category);


        return ToolsService::returnData(200, $data, $msg ?? '');
    }

    /**
     * 根据index_name获取指标名称
     *
     * @param string $indexName
     * @return string
     */
    private function getIndicatorName($indexName)
    {
        /* $indicators = [
            "sjyscf" => "三级医师查房频次达标率",
            "ejyscf" => "二级医师查房频次达标率",
            "szsqcf" => "术者术前查房完成率",
            "szshcf24" => "术者术后24小时查房完成率",
            "sstysqs" => "手术同意书签署完成率",
            "sstysqssj" => "手术同意书签署时间规范率",
            "sstysqssx" => "手术同意书签署序规范率",
            "zlfajcqyssh" => "诊疗方案决策权医师审核率",
            "jhzjsdwl" => "急会诊及时到位率",
            "pthzjswcl" => "普通会诊及时完成率",
            "zbqjzlcz" => "值班期间诊疗处置记录完成率",
            "sqfjhzssbltl" => "非计划再次手术完成（术前）疑难病历讨论率",
            "shfjhzssbltl" => "非计划再次手术完成（术后）疑难病历讨论率",
            "shshbfzbltl" => "手后出现严重脏器功能损害的并发症的患者完成疑难病历讨论率",
            "ynbltlgf" => "疑难病历讨论规范开展率",
            "qjjljsjl" => "抢救记录及时记录率",
            "qjjlsh" => "抢救记录审核率",
            "sqtlwcl" => "术前讨论完成率",
            "sqtlrygfcyl" => "术前讨论人员规范参与率",
            "sjsssqdxktl" => "四级手术术前多学科讨论率",
            "ssyzgfkjl" => "手术医嘱规范开具率",
            "swtljswcl" => "死亡讨论及时完成率",
            "kzrzcswtll" => "科主任主持死亡讨论率",
            "szfhsqmlyzl" => "术者符合授权目录一致率",
            "xjshxxmssr" => "临床新技术和新项目（手术）实施人符合率",
            "wjzjlfhl" => "危急值记录符合率",
            "ryjl24wcl" => "入院记录24小时完成率",
            "ryjlxswcl24" => "入院记录24小时完成率",
            "scbcjlwc8" => "首次病程记录8小时内完成率",
            "ssjlwc24" => "手术记录24小时完成率",
            "shscbcjkwc" => "术后首次病程即刻完成率",
            "rjssblsqtl" => "日间手术病历术前讨论及时完成率",
            "rjssblsqpg" => "日间手术病历术前评估及时完成率",
            "rjssblrcyjl24" => "日手术病历24小时入出院记录的术前部分及时完成率",
            "kjywcfqlshg" => "抗菌药物处方权落实合格率",
            "tsjkjywhz" => "特殊级抗菌药物会诊率",
            "cyqxdkjywy" => "超越权限的抗菌药物药物处方时限合格率",
            "kjywsyjl" => "抗菌药物使用记录符合率",
            "lcyxqpgjl" => "临床用血前评估记录率",
            "lcyxhpg" => "临床用血后评估记录率",
            "ryjl24" => "入院记录24小时内完成率",
            "hzqjjsl" => "会诊记录及时完成率",
            "hzqjcgl" => "会诊记录完成率",
            "bhlfzbl" => "病历首页完成率",
            "cyjl24" => "出院记录24小时内完成率",
            "yscf" => "上级医师查房完成率",
            "lcyx" => "临床用血完成率",
            "basy24" => "病案首页24小时内完成率",
            "ssjl24" => "手术记录24小时内完成率",
            "ctmrfhl" => "CT/MRI检查记录符合率",
            "bljcjl" => "病理检查记录符合率",
            "exzlhxzl" => "恶性肿瘤化疗药物使用符合率",
            "kjywsy" => "抗菌药物使用符合率",
            "ssxgjl" => "手术相关完整率",
            "sjssysjssswlb"=> "四级手术与三级手术患者死亡率比",
            "sjsjsssjkzl"=> "三、四级手术实际开展率",
            "mzysmzsjch"=> "麻醉医师手术时间重合率",
            // "sjssysjssbfzfs"=> "四级手术与三级手术并发症发生率比",
            "sjsjsssjkzlfj"=> "附加指标：50.1.三、四级手术实际开展率",
            "hzry48xsnzk" => "患者入院48小时内转科率",
            "hzry8xsncf" => "患者入院8小时内查房率",
            "zrw" => "植入物相关记录符合率",
            "ejhlsjhlcy" => "二级护理/三级护理出院率",
        ]; */

        //更改成查询index_catalog表的index_name=>name
        $indexCatalog = IndexCatalog::query()->where('pid', '!=', 0)->get()->toArray();
        $indicators = [];
        foreach ($indexCatalog as $item) {
            $indicators[$item['index_name']] = $item['name'];
        }

        return $indicators[$indexName] ?? '未知指标';
    }
}
