<?php

namespace App\Http\Controllers\Api;

use App\Model\Staff;
use App\Model\MS_BRDA;
use App\Model\YS_MZ_JZLS;
use App\Model\OmrRule;
use App\Model\OMR_BL01;
use App\Model\OMR_BLSY;
use App\Model\Department;
use App\Model\OmrDepartment;
use App\Services\CsvService;
use App\Services\UserService;
use Illuminate\Http\Request;
use App\Services\ToolsService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Model\Omr_Quality;
use App\Services\ElasticsearchService;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ExportData;
use App\Exports\DefectIssuesAllExport;
use App\Model\MZFK;
use App\Model\RuleSetting;
use App\Model\RuleWordMap;

class OmrBl01Controller extends Controller
{
    /**
     * 格式化时间字符串
     * 支持格式：20260101、2026-01-01、2026/01/01
     * @param string $time
     * @return string 返回 Y-m-d 格式
     */
    private function formatTimeString($time)
    {
        if (empty($time)) {
            return '';
        }

        // 如果是 8 位数字格式（如 20260101），转换为 2026-01-01
        if (preg_match('/^\d{8}$/', $time)) {
            return substr($time, 0, 4) . '-' . substr($time, 4, 2) . '-' . substr($time, 6, 2);
        }

        // 其他格式直接返回（会被 strtotime 处理）
        return $time;
    }

    private function formatCsvTextValue($value)
    {
        if ($value === null || $value === '') {
            return '';
        }

        return "\t" . (string)$value;
    }

    private function buildDoctorDepartmentNameMap(array $data)
    {
        $doctorCodes = array_filter(array_unique(array_column($data, 'SXYS')));
        if (empty($doctorCodes)) {
            return [];
        }

        $staffList = Staff::query()
            ->whereIn('code', array_values($doctorCodes))
            ->select(['code', 'ksdm'])
            ->get()
            ->toArray();

        $departmentIds = array_filter(array_unique(array_column($staffList, 'ksdm')));
        if (empty($departmentIds)) {
            return [];
        }

        $departmentMap = Department::query()
            ->whereIn('dep_id', array_values($departmentIds))
            ->pluck('dep_name', 'dep_id')
            ->toArray();

        $doctorDepartmentMap = [];
        foreach ($staffList as $staff) {
            $doctorCode = $staff['code'] ?? '';
            $departmentId = $staff['ksdm'] ?? '';
            if ($doctorCode === '') {
                continue;
            }

            $doctorDepartmentMap[$doctorCode] = $departmentMap[$departmentId] ?? '';
        }

        return $doctorDepartmentMap;
    }

    /**
     * 门诊病历搜索
     * @param Request $request
     * @return array
     */
    public function getOmrBl01List(Request $request)
    {
        $startTime = $request->post('start_time', '');   // 开始时间
        $endTime = $request->post('end_time', '');       // 结束时间
        $startNl = $request->post('start_nl', '');   // 开始岁
        $endNl = $request->post('end_nl', '');       // 结束岁
        $field = $request->post('field', []);       // 搜索字段
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        $isTm = $request->post('is_tm', 0);
        $pageStart = ($page - 1) * $pageSize;
        if ($pageStart >= 10000) {
            if ($pageStart >= 10000) {
                $page = floor(10000 / $pageSize);
            }
            $page = ($page - 1);
        }

        //$userDepId = UserService::getCurrentUserDep($request);
        /* if (empty($userDepId)) {
            return ToolsService::jsonSuccess(['count' => 0, 'list' => []]);
        } */

        /* $userCode = $request->post('code', '');
        if ($userCode) {
            $userDepId = Staff::query()->where('YGBH', '=', $userCode)->value('ksdm');
            if (empty($userDepId)) {
                $userDepId = Staff::query()->where('code', '=', $userCode)->value('ksdm');
                $userDepId = [$userDepId];
            }
        } */

        // 全文搜索关键字
        $must = [];

        /* if ($userDepId && is_array($userDepId)) {
            $depList = Department::query()->whereIn('dep_id', $userDepId)->pluck('dep_name')->toArray();
            if (!empty($depList)) {
                $must[] = ['terms' => ['ks.keyword' => $depList]];
            }
        } */

        // 年龄匹配
        if ($startNl && $endNl) {
            $must[] = ['range' => ['nl' => ['gte' => $startNl, 'lte' => $endNl]]];
        } elseif ($startNl) {
            $must[] = ['range' => ['nl' => ['gte' => $startNl]]];
        } elseif ($endNl) {
            $must[] = ['range' => ['nl' => ['lte' => $endNl]]];
        }

        // 就诊时间匹配
        if ($startTime && $endTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $must[] = ['range' => ['jzsj' => ['gte' => $startTime, 'lte' => $endTime]]];
        } elseif ($startTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $must[] = ['range' => ['jzsj' => ['gte' => $startTime]]];
        } elseif ($endTime) {
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $must[] = ['range' => ['jzsj' => ['lte' => $endTime]]];
        }

        $notMust = [];
        $should = [];
        $BLNR_TXT_ARR = [];

        // 处理字段筛选
        if ($field) {
            foreach ($field as $value) {
                if ($value['key'] == 'BLNR_TXT') {
                    $BLNR_TXT_ARR[] = $value['value'];
                }

                if (!empty($value['value'])) {
                    // 处理特殊字段映射
                    $fieldKey = $value['key'];
                    if ($fieldKey == 'ks') {
                        $fieldKey = 'ks.keyword';
                        $value['value'] = OmrDepartment::query()->where('dep_id', $value['value'])->value('dep_name');
                    }

                    // 根据字段类型选择合适的查询方式
                    $queryType = $this->getQueryTypeForField($fieldKey);

                    // 根据选择类型确定查询条件
                    if (isset($value['select_type']) && $value['select_type'] === 1) {
                        // OR 条件
                        $should[] = [$queryType => [$fieldKey => $value['value']]];
                    } else if (isset($value['select_type']) && $value['select_type'] == 2) {
                        // NOT 条件
                        $notMust[] = [$queryType => [$fieldKey => $value['value']]];
                    } else {
                        // AND 条件
                        $must[] = [$queryType => [$fieldKey => $value['value']]];
                    }
                }
            }
        }

        $omrBl01Service = new ElasticsearchService('omr_bl01_2023');
        if ($should) {
            $params = $omrBl01Service->clearMust()
                ->queryByMustBatch($must)
                ->queryByMustNot($notMust)
                ->queryByShouldBatch($should)
                ->minimumShouldMatch(1)
                ->highlight()
                ->paginate($page, $pageSize)
                ->trackTotalHits()
                ->getParams();
        } else {
            $params = $omrBl01Service->clearMust()
                ->queryByMustBatch($must)
                ->queryByMustNot($notMust)
                ->highlight()
                ->paginate($page, $pageSize)
                ->trackTotalHits()
                ->getParams();
        }
        $params['body']['sort'] = [['jzsj' => 'desc']];

        // 查询
        $restful = app('es')->search($params);
        $omrBl01List = $omrBl01Service->getDataByEs($restful);

        // 解析详情数据
        $omrBl01Ddetail = $omrBl01Service->getDataByEsToArray($restful);

        // 总条数
        $total = !empty($omrBl01List[1]) ? $omrBl01List[1] : 0;
        // 总页数
        $totalPage = 0;
        if ($total) {
            $totalPage = (int)ceil($total / $pageSize);
        }

        // 列表数据处理
        $list = [];
        if (!empty($omrBl01List[0])) {
            foreach ($omrBl01List[0] as $key => $value) {
                //$isTm = $userDepId == 'admin' ? 0 : 1;
                $isTm = 0;
                // 身份证号脱敏
                //$SFZH = $isTm ? desensitize($value['SFZH'], 6, 8) : $value['SFZH'];
                $SFZH = $value['SFZH'];

                $msBrdaData = MS_BRDA::query()->where('BRID', '=', $value['BRID'])->first();
                if (empty($value['mzh']) && !empty($msBrdaData)) {
                    $value['mzh'] = $msBrdaData->MZHM;
                }
                if (empty($value['xm']) && !empty($msBrdaData)) {
                    $value['xm'] = $msBrdaData->BRXM;
                }
                if (empty($value['xb']) && !empty($msBrdaData)) {
                    $xbArr = [1 => '男', 2 => '女'];
                    $value['xb'] = $xbArr[$msBrdaData->BRXB] ?? '';
                }

                //$value['xm'] = $isTm ? desensitize($value['xm'], 1, 1) : $value['xm'];
                $value['xm'] = $value['xm'];
                $list[] = [
                    'mzh' => $value['mzh'],
                    'xm' => $value['xm'],
                    'nl' => str_replace('-', '', $value['nl1']),
                    'xb' => $value['xb'],
                    'ks' => $value['ks'],
                    // 'ks' => $depData[$value['BRKS']] ?? $value['BRKS'],
                    'CJSJ' => strpos($value['jzsj'], '1970-01-01') !== false ? '' : $value['jzsj'],
                    'SFZH' => $SFZH,
                    'BLBH' => $value['BLBH'],
                    'BRID' => $value['BRID'],
                    'cbzd' => $value['cbzd'] ?? '',
                    'zs' => $value['zs'] ?? '',
                    'bl_type' => $value['bl_type'] ?? ''
                ];

                // 姓名脱敏
                $omrBl01Ddetail[0][$key]['xm'] = $value['xm'];
                $omrBl01Ddetail[0][$key]['SFZH'] = $SFZH;
            }
        }

        // 关键字高亮处理
        if (!empty($BLNR_TXT_ARR) && !empty($omrBl01Ddetail[0])) {
            foreach ($omrBl01Ddetail[0] as $key => $omrBl01Info) {
                foreach ($omrBl01Info as $k => $val) {
                    $newValue = $val;
                    foreach ($BLNR_TXT_ARR as $keyword) {
                        $newValue = str_replace($keyword, "<font color='red'>" . $keyword . "</font>", $newValue);
                    }
                    $omrBl01Ddetail[0][$key][$k] = $newValue;
                }
            }
        }

        // 返回数据
        $returnData = [
            'total_page' => $totalPage,
            'total' => $total,
            'list' => $list,
            'detail' => $omrBl01Ddetail[0] ?? []
        ];

        return ToolsService::returnData(200, $returnData, $msg ?? '');
    }

    /**
     * 根据字段类型获取合适的查询方式
     * @param string $fieldKey
     * @return string
     */
    private function getQueryTypeForField($fieldKey)
    {
        // 根据字段类型选择合适的查询方式
        $keywordFields = ['SFZH', 'mzh', 'xb', 'bl_type', 'SXYS', 'BLZT', 'DLJ', 'DLLB', 'tx', 'nl1', 'ks'];
        $integerFields = ['BLBH', 'BLLB', 'BLLX', 'BRID', 'BRKS', 'id', 'is_defect', 'JZXH', 'nl', 'SXKS'];
        $dateFields = ['CJSJ', 'JLSJ', 'WCSJ', 'jzsj'];

        if (in_array($fieldKey, $keywordFields)) {
            return 'term';
        } elseif (in_array($fieldKey, $integerFields)) {
            return 'term';
        } elseif (in_array($fieldKey, $dateFields)) {
            return 'range';
        } else {
            // 默认使用全文搜索
            return 'match_phrase';
        }
    }

    /**
     * 门诊病历质控 - 质量分析
     * @param Request $request
     * @return array
     */
    public function analysis(Request $request)
    {
        // 从 params 对象中获取参数
        $params = $request->post('params', []);
        $startTime = $params['start_time'] ?? '';   // 开始时间
        $endTime = $params['end_time'] ?? '';       // 结束时间
        $depId = $params['dep_id'] ?? '';           // 科室ID
        $doctorId = $params['doctor_id'] ?? '';     // 医师ID

        // 格式化时间（支持 20260101 格式）
        $startTime = $this->formatTimeString($startTime);
        $endTime = $this->formatTimeString($endTime);

        $must = [['term' => ['BLZT' => 1]]];
        if (!$startTime) {
            $startTime = date('Y-m-01');
        }
        $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
        $must[] = ['range' => ['JLSJ' => ['gte' => $startTime]]];
        if (!$endTime) {
            $endTime = date('Y-m-d');
        }
        $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
        $must[] = ['range' => ['JLSJ' => ['lte' => $endTime]]];

        // 科室过滤
        if ($depId) {
            $must[] = ['term' => ['BRKS' => $depId]];
        }

        // 医师过滤
        if ($doctorId) {
            $must[] = ['term' => ['SXYS' => $doctorId]];
        }

        // 查询所有门诊病历（门诊病历数量）
        $omrBl01Service = new ElasticsearchService('omr_bl01_2023');
        $params = $omrBl01Service->clearMust()
            ->queryByMustBatch($must)
            ->trackTotalHits()
            ->getParams();
        $restful = app('es')->search($params);
        $data = $omrBl01Service->getDataByEs($restful);
        $returnData['omr_total'] = !empty($data[1]) ? $data[1] : 0;


        // 就诊人次（应有病历数量）
        $jzlsQuery = YS_MZ_JZLS::query();
        if (!empty($startTime)) {
            $jzlsQuery->where('KSSJ', '>=', $startTime);
        }
        if (!empty($endTime)) {
            $jzlsQuery->where('KSSJ', '<=', $endTime);
        }
        // 科室过滤
        if ($depId) {
            $jzlsQuery->where('KSDM', $depId);
        }
        // 医师过滤
        if ($doctorId) {
            $jzlsQuery->where('YSDM', $doctorId);
        }
        $jzlsData = $jzlsQuery->count();

        $returnData['should_be_total'] = $jzlsData;

        // 查询有缺陷门诊病历数量
        $must[] = ['term' => ['is_defect' => 1]];
        $params = $omrBl01Service->clearMust()
            ->queryByMustBatch($must)
            ->trackTotalHits()
            ->getParams();
        $restful = app('es')->search($params);
        $data = $omrBl01Service->getDataByEs($restful);
        $returnData['omr_defect_total'] = !empty($data[1]) ? $data[1] : 0;

        // 查询缺陷问题数量
        $query = Omr_Quality::query()
            ->leftJoin('OMR_BL01', 'omr_quality.BLBH', '=', 'OMR_BL01.BLBH')
            ->where('OMR_BL01.BLZT', 1);

        if ($startTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $query->where('OMR_BL01.JLSJ', '>=', $startTime);
        }
        if ($endTime) {
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $query->where('OMR_BL01.JLSJ', '<=', $endTime);
        }
        // 科室过滤
        if ($depId) {
            $query->where('OMR_BL01.BRKS', $depId);
        }
        // 医师过滤
        if ($doctorId) {
            $query->where('OMR_BL01.SXYS', $doctorId);
        }
        $returnData['omr_defect_issue_total'] = $query->count();

        // 查询时间范围内得分等级分布（查询所有病历，不只是有缺陷的）
        // 移除 is_defect 条件
        $mustForGrade = $must;
        array_pop($mustForGrade); // 移除最后添加的 is_defect 条件

        $params = $omrBl01Service->clearMust()
            ->queryByMustBatch($mustForGrade)
            ->aggs(['score_lv' => ['terms' => ['field' => 'score_lv.keyword', 'size' => 1000]]])
            ->paginate(1, 0)
            ->getParams();
        $restful = app('es')->search($params);
        $data = $omrBl01Service->getDataByEs($restful);

        // 根据score_lv进行汇总统计（当期：甲/乙/丙）
        $scoreLvBuckets = !empty($data[2]['score_lv']['buckets']) ? $data[2]['score_lv']['buckets'] : [];
        $curGradeACount = 0;
        $curGradeBCount = 0;
        $curGradeCCount = 0;
        foreach ($scoreLvBuckets as $bucket) {
            $lvKey = $bucket['key'] ?? '';
            $lvKey = is_string($lvKey) ? $lvKey : '';
            // 兼容 key 可能为：甲、/甲、<span>xx/甲</span> 等情况
            $lvKey = strip_tags($lvKey);
            $lvKey = trim($lvKey);
            if (strpos($lvKey, '/') !== false) {
                $lvKey = substr($lvKey, strrpos($lvKey, '/') + 1);
            }
            $lvKey = trim($lvKey);
            $count = intval($bucket['doc_count'] ?? 0);
            if ($lvKey === '甲') {
                $curGradeACount += $count;
            } elseif ($lvKey === '乙') {
                $curGradeBCount += $count;
            } elseif ($lvKey === '丙') {
                $curGradeCCount += $count;
            }
        }
        $returnData['cur_grade_a_count'] = $curGradeACount;
        $returnData['cur_grade_b_count'] = $curGradeBCount;
        $returnData['cur_grade_c_count'] = $curGradeCCount;
        // 查询上个周期的得分等级分布（上期：甲/乙/丙 + 上期病历总数）
        if ($startTime && $endTime) {
            // 本周期天数
            $startTimestamp = strtotime($startTime);
            $endTimestamp = strtotime($endTime);
            $days = ceil(($endTimestamp - $startTimestamp) / (60 * 60 * 24)) + 1;

            // 上个周期的起止时间
            $prevEndTimestamp = $startTimestamp - 1;
            $prevStartTimestamp = $startTimestamp - $days * 24 * 60 * 60;
            $prevStartTime = date('Y-m-d 00:00:00', $prevStartTimestamp);
            $prevEndTime = date('Y-m-d 23:59:59', $prevEndTimestamp);

            $prevMust = [['term' => ['BLZT' => 1]]];
            if ($startTime) {
                $prevMust[] = ['range' => ['JLSJ' => ['gte' => $prevStartTime]]];
            }
            if ($endTime) {
                $prevMust[] = ['range' => ['JLSJ' => ['lte' => $prevEndTime]]];
            }
            // 科室过滤
            if ($depId) {
                $prevMust[] = ['term' => ['BRKS' => $depId]];
            }
            // 医师过滤
            if ($doctorId) {
                $prevMust[] = ['term' => ['SXYS' => $doctorId]];
            }
            // 替换时间区间
            // 移除和时间相关的条件（如果有），然后加上新时间要求
            // 由于上面must只管 is_defect=1，按业务本段原先后面用 where() 判时间，所以此处在es参数加上时间
            $prevParams = $omrBl01Service->clearMust()
                ->queryByMustBatch($prevMust)
                ->aggs(['score_lv' => ['terms' => ['field' => 'score_lv.keyword', 'size' => 1000]]])
                ->paginate(1, 0)
                ->getParams();
            $prevRestful = app('es')->search($prevParams);
            $prevData = $omrBl01Service->getDataByEs($prevRestful);

            // 汇总统计
            $prevScoreLvBuckets = !empty($prevData[2]['score_lv']['buckets']) ? $prevData[2]['score_lv']['buckets'] : [];
            $prevGradeACount = 0;
            $prevGradeBCount = 0;
            $prevGradeCCount = 0;
            foreach ($prevScoreLvBuckets as $bucket) {
                $lvKey = $bucket['key'] ?? '';
                $lvKey = is_string($lvKey) ? $lvKey : '';
                $lvKey = strip_tags($lvKey);
                $lvKey = trim($lvKey);
                if (strpos($lvKey, '/') !== false) {
                    $lvKey = substr($lvKey, strrpos($lvKey, '/') + 1);
                }
                $lvKey = trim($lvKey);
                $count = intval($bucket['doc_count'] ?? 0);
                if ($lvKey === '甲') {
                    $prevGradeACount += $count;
                } elseif ($lvKey === '乙') {
                    $prevGradeBCount += $count;
                } elseif ($lvKey === '丙') {
                    $prevGradeCCount += $count;
                }
            }
            $returnData['prev_grade_a_count'] = $prevGradeACount;
            $returnData['prev_grade_b_count'] = $prevGradeBCount;
            $returnData['prev_grade_c_count'] = $prevGradeCCount;
            $returnData['prev_total_count'] = intval($prevData[1] ?? 0);
        } else {
            $returnData['prev_grade_a_count'] = 0;
            $returnData['prev_grade_b_count'] = 0;
            $returnData['prev_grade_c_count'] = 0;
            $returnData['prev_total_count'] = 0;
        }


        return ToolsService::returnData(200, $returnData, '');
    }

    /**
     * 质控问题的月趋势图
     * @param Request $request
     * @return array
     */
    public function qualityMonthTrend(Request $request)
    {
        // 从 params 对象中获取参数
        $params = $request->post('params', []);
        $startTime = $params['start_time'] ?? '';   // 开始时间
        $endTime = $params['end_time'] ?? '';       // 结束时间
        $depId = $params['dep_id'] ?? '';           // 科室ID
        $doctorId = $params['doctor_id'] ?? '';     // 医师ID

        // 格式化时间（支持 20260101 格式）
        $startTime = $this->formatTimeString($startTime);
        $endTime = $this->formatTimeString($endTime);

        // 获取前端传递的结束时间, 默认为本月
        $endTime = date('Y-m');
        // 获取近6个月的开始时间
        $endDate = date('Y-m-01', strtotime($endTime . '-01'));
        $startDate = date('Y-m-01', strtotime('-5 months', strtotime($endDate)));
        $endDateFull = date('Y-m-t 23:59:59', strtotime($endDate));

        // 查询近6个月的各月的缺陷数量（即Omr_Quality有效记录数量，BLZT=1）
        $query = Omr_Quality::query()
            ->leftJoin('OMR_BL01', 'omr_quality.BLBH', '=', 'OMR_BL01.BLBH')
            ->where('OMR_BL01.BLZT', 1)
            ->whereBetween('OMR_BL01.JLSJ', [$startDate, $endDateFull]);

        // 科室过滤
        if ($depId) {
            $query->where('OMR_BL01.BRKS', $depId);
        }

        // 医师过滤
        if ($doctorId) {
            $query->where('OMR_BL01.SXYS', $doctorId);
        }

        $items = $query->selectRaw("DATE_FORMAT(OMR_BL01.JLSJ, '%Y-%m') as month, COUNT(*) as defect_total")
            ->groupBy('month')
            ->get()
            ->pluck('defect_total', 'month')
            ->toArray();

        // 生成近6个月的月份列表
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $months[] = date('Y-m', strtotime("-{$i} months", strtotime($endDate)));
        }

        $trendData = [];
        foreach ($months as $month) {
            $trendData[] = [
                'month' => $month,
                'defect_total' => isset($items[$month]) ? intval($items[$month]) : 0
            ];
        }

        return ToolsService::returnData(200, $trendData, '');
    }

    /**
     * 缺陷问题趋势图
     * @param Request $request
     * @return array
     */
    public function defectIssuesTrend(Request $request)
    {
        // 从 params 对象中获取参数
        $params = $request->post('params', []);
        $startTime = $params['start_time'] ?? '';   // 开始时间
        $endTime = $params['end_time'] ?? '';       // 结束时间
        $depId = $params['dep_id'] ?? '';           // 科室ID
        $doctorId = $params['doctor_id'] ?? '';     // 医师ID
        $isTop10 = (int)($params['is_top10'] ?? 0);   // 是否只返回Top10（1=是）
        $page = (int)($params['page'] ?? 1);          // 页码

        // is_export 支持从 params 中获取，也支持从外层直接获取
        $isExport = (int)($params['is_export'] ?? $request->post('is_export', 0)); // 是否导出（1=是）

        // 格式化时间（支持 20260101 格式）
        $startTime = $this->formatTimeString($startTime);
        $endTime = $this->formatTimeString($endTime);
        $pageSize = (int)$request->post('page_size', 10); // 每页数量

        // ===== 1. 计算总缺陷病例数量（完全参考 analysis 中的缺陷病例统计逻辑）=====
        $omrBl01Service = new ElasticsearchService('omr_bl01_2023');
        $defectMust = [['term' => ['BLZT' => 1]]];

        // 这里为了与 analysis 完全对齐，start / end 为空时也走默认时间（当月 / 当天）
        $defectStart = $startTime;
        $defectEnd = $endTime;
        if (!$defectStart) {
            $defectStart = date('Y-m-01');
        }
        $defectStart = date('Y-m-d', strtotime($defectStart)) . ' 00:00:00';
        $defectMust[] = ['range' => ['JLSJ' => ['gte' => $defectStart]]];

        if (!$defectEnd) {
            $defectEnd = date('Y-m-d');
        }
        $defectEnd = date('Y-m-d', strtotime($defectEnd)) . ' 23:59:59';
        $defectMust[] = ['range' => ['JLSJ' => ['lte' => $defectEnd]]];

        // 科室过滤
        if ($depId) {
            $defectMust[] = ['term' => ['BRKS' => $depId]];
        }

        // 医师过滤
        if ($doctorId) {
            $defectMust[] = ['term' => ['SXYS' => $doctorId]];
        }

        // 只算有缺陷的病历
        $defectMust[] = ['term' => ['is_defect' => 1]];

        $paramsTotalDefect = $omrBl01Service->clearMust()
            ->queryByMustBatch($defectMust)
            ->trackTotalHits()
            ->getParams();
        $restfulTotalDefect = app('es')->search($paramsTotalDefect);
        $totalDefectData = $omrBl01Service->getDataByEs($restfulTotalDefect);
        // 总缺陷病例数（严格等于 analysis 中 $returnData['omr_defect_total'] 的含义）
        $totalDefectCases = !empty($totalDefectData[1]) ? (int)$totalDefectData[1] : 0;

        $omrQualityService = new ElasticsearchService('omr_quality_2023');
        $must = [];

        // 时间过滤：与 defectIssues 一致，支持只传 start 或只传 end
        if (!$startTime) {
            $startTime = date('Y-m-01');
        }
        if (!$endTime) {
            $endTime = date('Y-m-d');
        }
        $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
        $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';

        // 使用数据库查询（因为 omr_quality_2023 索引中没有 BRKS 和 SXYS 字段）
        $query = Omr_Quality::query()
            ->leftJoin('OMR_BL01', 'omr_quality.BLBH', '=', 'OMR_BL01.BLBH')
            ->where('OMR_BL01.BLZT', 1)
            ->whereBetween('OMR_BL01.JLSJ', [$startTime, $endTime]);

        // 科室过滤
        if ($depId) {
            $query->where('OMR_BL01.BRKS', $depId);
        }

        // 医师过滤
        if ($doctorId) {
            $query->where('OMR_BL01.SXYS', $doctorId);
        }

        // 按 rule_id 分组统计
        $ruleStats = $query->select('omr_quality.rule_id', DB::raw('COUNT(*) as count'))
            ->groupBy('omr_quality.rule_id')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();

        // 规则信息映射（参考 defectIssues 的返回字段）
        $ruleMap = OmrRule::query()->get(['id', 'title', 'notice'])->keyBy('id')->toArray();
        //自定义规则
        $customRules = RuleSetting::query()->where('rule_type', '=', '门诊规则')->get(['id', 'object', 'description'])->keyBy('id')->toArray();

        $returnData = [];
        foreach ($ruleStats as $stat) {
            $ruleId = (int)($stat['rule_id'] ?? 0);
            $ruleTotal = (int)($stat['count'] ?? 0);
            $field = '';
            $desc = '';
            if ($ruleId < 1000000) {
                $field = $ruleMap[$ruleId]['title'] ?? '';
                $desc = $ruleMap[$ruleId]['notice'] ?? '';
            } else {
                $coustomruleid = $ruleId - 1000000;
                $object = $customRules[$coustomruleid]['object'] ?? '';
                //如果包含逗号
                if (strpos($object, ',') !== false) {
                    $objects = explode(',', $object);
                    $object = $objects[1];
                }
                $field = $object;
                $desc = $customRules[$coustomruleid]['description'] ?? '';
            }
            // 缺陷占比 = 当前规则缺陷问题数量 / 总缺陷病例数 * 100
            // 为了稳定保留两位小数（含尾随 0），返回字符串，如 "12.30"
            $defectRate = 0;
            if ($totalDefectCases > 0) {
                $defectRate = $ruleTotal / $totalDefectCases * 100;
            }
            $defectRate = number_format((float)$defectRate, 2, '.', '');

            $returnData[] = [
                'rule_id' => $ruleId,
                'field' => $field,
                'desc' => $desc,
                'total_num' => $ruleTotal,
                'defect_rate' => $defectRate,
            ];
        }

        // is_top10=1 时只返回前10条数据（在分页前限制，与 defectIssues 保持一致）
        if ($isTop10 === 1) {
            $returnData = array_slice($returnData, 0, 10);
        }

        // 如果是导出，直接返回导出文件
        if ($isExport === 1) {
            return $this->exportDefectIssuesTrend($returnData);
        }

        // 对规则列表结果进行分页
        $page = max($page, 1);
        $pageSize = max($pageSize, 1);
        $offset = ($page - 1) * $pageSize;
        $pagedList = array_slice($returnData, $offset, $pageSize);

        return ToolsService::returnData(200, [
            'list' => $pagedList,
            'count' => count($returnData),
            'page' => $page,
            'page_size' => $pageSize,
        ], '');
    }

    /**
     * 导出缺陷问题趋势数据
     * @param array $data
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    private function exportDefectIssuesTrend($data)
    {
        $title = ['缺陷类目', '缺陷描述', '缺陷数量', '缺陷占比'];
        $exportData = [];

        foreach ($data as $item) {
            $exportData[] = [
                $item['field'],
                $item['desc'],
                $item['total_num'],
                $item['defect_rate'] . '%',
            ];
        }

        $fileName = '缺陷问题_' . date('YmdHis') . '.xlsx';
        return Excel::download(new ExportData($title, $exportData), $fileName);
    }

    /**
     * 导出科室排名数据
     * @param array $data
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    private function exportRankingDepartment($data)
    {
        $title = ['科室名称', '病历总数', '缺陷病历数', '扣分', '问题数量', '甲级数量', '甲级占比', '乙级数量', '乙级占比', '丙级数量', '丙级占比'];
        $exportData = [];

        foreach ($data as $item) {
            $exportData[] = [
                $item['name'],
                $item['total_medical'],
                $item['total_error_medical'],
                $item['deduct_score'],
                $item['issue_count'],
                $item['grade_a_count'],
                $item['grade_a_rate'] . '%',
                $item['grade_b_count'],
                $item['grade_b_rate'] . '%',
                $item['grade_c_count'],
                $item['grade_c_rate'] . '%',
            ];
        }

        $fileName = '科室排名_' . date('YmdHis') . '.xlsx';
        return Excel::download(new ExportData($title, $exportData), $fileName);
    }

    /**
     * 导出医师排名数据
     * @param array $data
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    private function exportRankingDoctor($data)
    {
        $title = ['医师姓名', '科室名称', '病历总数', '缺陷病历数', '扣分', '问题数量', '甲级数量', '甲级占比', '乙级数量', '乙级占比', '丙级数量', '丙级占比'];
        $exportData = [];

        foreach ($data as $item) {
            $exportData[] = [
                $item['doctor_name'],
                $item['dep_name'],
                $item['total_medical'],
                $item['total_error_medical'],
                $item['deduct_score'],
                $item['issue_count'],
                $item['grade_a_count'],
                $item['grade_a_rate'] . '%',
                $item['grade_b_count'],
                $item['grade_b_rate'] . '%',
                $item['grade_c_count'],
                $item['grade_c_rate'] . '%',
            ];
        }

        $fileName = '医师排名_' . date('YmdHis') . '.xlsx';
        return Excel::download(new ExportData($title, $exportData), $fileName);
    }
    /**
     * 门诊病历质控 - 科室排名
     * @param Request $request
     * @return array
     */
    public function rankingDepartment(Request $request)
    {
        // 从 params 对象中获取参数
        $params = $request->post('params', []);
        $startTime = $params['start_time'] ?? '';   // 开始时间
        $endTime = $params['end_time'] ?? '';       // 结束时间
        $depId = $params['dep_id'] ?? '';           // 科室ID
        $doctorId = $params['doctor_id'] ?? '';     // 医师ID
        $isTop10 = (int)($params['is_top10'] ?? 0);   // 是否只返回Top10（1=是）
        $page = (int)($params['page'] ?? 1);          // 页码
        $pageSize = (int)($params['page_size'] ?? 10); // 每页数量
        $order = $params['order'] ?? 'desc';        // 排序方式：desc=倒序，asc=正序

        // is_export 支持从 params 中获取，也支持从外层直接获取
        $isExport = (int)($params['is_export'] ?? $request->post('is_export', 0)); // 是否导出（1=是）

        // 格式化时间（支持 20260101 格式）
        $startTime = $this->formatTimeString($startTime);
        $endTime = $this->formatTimeString($endTime);

        // 获取科室信息
        $department = OmrDepartment::getOmrDepartmentData();

        $omrBl01Service = new ElasticsearchService('omr_bl01_2023');
        $must = [['term' => ['BLZT' => 1]]];

        // 时间处理：开始时间不传默认当月1号，结束时间不传默认当天
        if (!$startTime) {
            $startTime = date('Y-m-01');
        }
        if (!$endTime) {
            $endTime = date('Y-m-d');
        }

        $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
        $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
        $must[] = ['range' => ["JLSJ" => ['gte' => $startTime, 'lte' => $endTime]]];

        // 科室过滤
        if ($depId) {
            $must[] = ['term' => ['BRKS' => $depId]];
        }

        // 医师过滤
        if ($doctorId) {
            $must[] = ['term' => ['SXYS' => $doctorId]];
        }

        $topSize = ($isTop10 === 1) ? 10 : 500;

        // 确定排序方向
        $esOrder = ($order === 'asc') ? 'asc' : 'desc';

        // 按科室聚合
        $aggs = [
            'BRKS' => [
                'terms' => [
                    "field" => 'BRKS',
                    "size" => $topSize,
                    "order" => ['_count' => $esOrder],
                ],
            ],
        ];

        // 查询科室下所有病历
        $params = $omrBl01Service->clearMust()
            ->queryByMustBatch($must)
            ->aggs($aggs)
            ->paginate(1, 0)
            ->getParams();
        $restful = app('es')->search($params);
        $dataAll = $omrBl01Service->getDataByEs($restful);
        $dataAll = !empty($dataAll[2]['BRKS']['buckets']) ? $dataAll[2]['BRKS']['buckets'] : [];
        $dataAllMap = [];
        foreach ($dataAll as $item) {
            $dataAllMap[$item['key']] = [
                'count' => $item['doc_count'] ?? 0,
            ];
        }

        // 查询科室下有缺陷的病历
        $must[] = ['term' => ['is_defect' => 1]];
        $params = $omrBl01Service->clearMust()
            ->queryByMustBatch($must)
            ->aggs($aggs)
            ->paginate(1, 0)
            ->getParams();
        $restful = app('es')->search($params);
        $dataQx = $omrBl01Service->getDataByEs($restful);
        $dataQx = !empty($dataQx[2]['BRKS']['buckets']) ? $dataQx[2]['BRKS']['buckets'] : [];
        $dataQxMap = array_column($dataQx, 'doc_count', 'key');

        // 查询甲乙丙等级分布（按科室聚合，再按等级聚合）
        $aggsGrade = [
            'BRKS' => [
                'terms' => [
                    "field" => 'BRKS',
                    "size" => $topSize,
                ],
                'aggs' => [
                    'score_lv' => [
                        'terms' => [
                            'field' => 'score_lv.keyword',
                            'size' => 100
                        ]
                    ]
                ]
            ],
        ];

        // 移除 is_defect 条件，查询所有病历的等级分布
        array_pop($must);
        $params = $omrBl01Service->clearMust()
            ->queryByMustBatch($must)
            ->aggs($aggsGrade)
            ->paginate(1, 0)
            ->getParams();
        $restful = app('es')->search($params);
        $dataGrade = $omrBl01Service->getDataByEs($restful);
        $dataGrade = !empty($dataGrade[2]['BRKS']['buckets']) ? $dataGrade[2]['BRKS']['buckets'] : [];

        // 处理等级数据
        $gradeMap = [];
        foreach ($dataGrade as $depItem) {
            $depKey = $depItem['key'];
            $gradeMap[$depKey] = [
                'grade_a' => 0,
                'grade_b' => 0,
                'grade_c' => 0,
            ];

            $scoreLvBuckets = $depItem['score_lv']['buckets'] ?? [];
            foreach ($scoreLvBuckets as $bucket) {
                $lvKey = $bucket['key'] ?? '';
                $lvKey = is_string($lvKey) ? $lvKey : '';
                $lvKey = strip_tags($lvKey);
                $lvKey = trim($lvKey);
                if (strpos($lvKey, '/') !== false) {
                    $lvKey = substr($lvKey, strrpos($lvKey, '/') + 1);
                }
                $lvKey = trim($lvKey);
                $count = intval($bucket['doc_count'] ?? 0);

                if ($lvKey === '甲') {
                    $gradeMap[$depKey]['grade_a'] += $count;
                } elseif ($lvKey === '乙') {
                    $gradeMap[$depKey]['grade_b'] += $count;
                } elseif ($lvKey === '丙') {
                    $gradeMap[$depKey]['grade_c'] += $count;
                }
            }
        }

        // 查询问题数量和扣分（优化：分步查询，减少关联）
        // 第一步：查询问题数量和规则ID分组
        $issueQuery = Omr_Quality::query()
            ->leftJoin('OMR_BL01', 'omr_quality.BLBH', '=', 'OMR_BL01.BLBH')
            ->where('OMR_BL01.BLZT', 1);

        if ($startTime) {
            $issueQuery->whereBetween('OMR_BL01.JLSJ', [$startTime, $endTime]);
        }
        if ($depId) {
            $issueQuery->where('OMR_BL01.BRKS', $depId);
        }
        if ($doctorId) {
            $issueQuery->where('OMR_BL01.SXYS', $doctorId);
        }

        // 按科室和规则分组统计
        $issueRawData = $issueQuery->selectRaw('OMR_BL01.BRKS, omr_quality.rule_id, COUNT(*) as count')
            ->groupBy('OMR_BL01.BRKS', 'omr_quality.rule_id')
            ->get();

        // 第二步：批量获取规则分数（一次查询）
        $ruleIds = $issueRawData->pluck('rule_id')->unique()->filter();
        $ruleScores = [];
        if ($ruleIds->isNotEmpty()) {
            $ruleScores = OmrRule::whereIn('id', $ruleIds)->pluck('score', 'id')->toArray();
        }

        // 第三步：在应用层计算每个科室的问题数量和扣分
        $issueData = [];
        foreach ($issueRawData as $item) {
            $brks = $item->BRKS;
            $ruleId = $item->rule_id;
            $count = $item->count;
            $score = $ruleScores[$ruleId] ?? 0;

            if (!isset($issueData[$brks])) {
                $issueData[$brks] = [
                    'issue_count' => 0,
                    'total_deduct_score' => 0
                ];
            }

            $issueData[$brks]['issue_count'] += $count;
            $issueData[$brks]['total_deduct_score'] += ($score * $count);
        }

        // 组装数据
        $depData = [];
        foreach ($dataAllMap as $key => $value) {
            $totalMedical = $value['count'];
            $gradeA = $gradeMap[$key]['grade_a'] ?? 0;
            $gradeB = $gradeMap[$key]['grade_b'] ?? 0;
            $gradeC = $gradeMap[$key]['grade_c'] ?? 0;

            // 计算占比
            $gradeARate = $totalMedical > 0 ? number_format($gradeA / $totalMedical * 100, 2, '.', '') : '0.00';
            $gradeBRate = $totalMedical > 0 ? number_format($gradeB / $totalMedical * 100, 2, '.', '') : '0.00';
            $gradeCRate = $totalMedical > 0 ? number_format($gradeC / $totalMedical * 100, 2, '.', '') : '0.00';

            // 获取问题数量和扣分
            $issueCount = isset($issueData[$key]) ? (int)$issueData[$key]['issue_count'] : 0;
            $deductScore = isset($issueData[$key]) ? (float)$issueData[$key]['total_deduct_score'] : 0;

            $depData[] = [
                'name' => $department[$key] ?? $key,
                'dep_id' => $key,
                'total_medical' => $totalMedical,
                'total_error_medical' => $dataQxMap[$key] ?? 0,
                'deduct_score' => number_format($deductScore, 2, '.', ''),
                'issue_count' => $issueCount,
                'grade_a_count' => $gradeA,
                'grade_a_rate' => $gradeARate,
                'grade_b_count' => $gradeB,
                'grade_b_rate' => $gradeBRate,
                'grade_c_count' => $gradeC,
                'grade_c_rate' => $gradeCRate,
            ];
        }

        // 按病历数量排序（已在 ES 查询时排序，这里保持顺序）
        // 注意：由于后续还需要合并等级数据，所以这里不再重新排序
        // $depData 已经按照 ES 返回的顺序（即 total_medical 的排序）

        // 总数
        $totalCount = count($depData);

        // is_top10=1 时只返回前10
        if ($isTop10 === 1) {
            $depData = array_slice($depData, 0, 10);
        }

        // 如果是导出，直接返回导出文件
        if ($isExport === 1) {
            return $this->exportRankingDepartment($depData);
        }

        // 分页处理
        $page = max($page, 1);
        $pageSize = max($pageSize, 1);
        $offset = ($page - 1) * $pageSize;
        $returnData = array_slice($depData, $offset, $pageSize);

        return ToolsService::returnData(200, [
            'list' => $returnData,
            'count' => $totalCount,
            'page' => $page,
            'page_size' => $pageSize,
        ], '');
    }

    /**
     * 门诊病历质控 - 医师排名
     * @param Request $request
     * @return array
     */
    public function rankingDoctor(Request $request)
    {
        // 从 params 对象中获取参数
        $params = $request->post('params', []);
        $startTime = $params['start_time'] ?? '';   // 开始时间
        $endTime = $params['end_time'] ?? '';       // 结束时间
        $depId = $params['dep_id'] ?? '';           // 科室ID
        $doctorId = $params['doctor_id'] ?? '';     // 医师ID
        $isTop10 = (int)($params['is_top10'] ?? 0);   // 是否只返回Top10（1=是）
        $page = (int)($params['page'] ?? 1);          // 页码
        $pageSize = (int)($params['page_size'] ?? 10); // 每页数量
        $order = $params['order'] ?? 'desc';        // 排序方式：desc=倒序，asc=正序

        // is_export 支持从 params 中获取，也支持从外层直接获取
        $isExport = (int)($params['is_export'] ?? $request->post('is_export', 0)); // 是否导出（1=是）

        // 格式化时间（支持 20260101 格式）
        $startTime = $this->formatTimeString($startTime);
        $endTime = $this->formatTimeString($endTime);

        $omrBl01Service = new ElasticsearchService('omr_bl01_2023');
        $must = [['term' => ['BLZT' => 1]]];

        // 时间处理：开始时间不传默认当月1号，结束时间不传默认当天
        if (!$startTime) {
            $startTime = date('Y-m-01');
        }
        if (!$endTime) {
            $endTime = date('Y-m-d');
        }

        $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
        $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
        $must[] = ['range' => ["JLSJ" => ['gte' => $startTime, 'lte' => $endTime]]];

        // 科室过滤
        if ($depId) {
            $must[] = ['term' => ['BRKS' => $depId]];
        }

        // 医师过滤
        if ($doctorId) {
            $must[] = ['term' => ['SXYS' => $doctorId]];
        }

        $topSize = ($isTop10 === 1) ? 10 : 500;

        // 按医师聚合
        // 医师过滤
        if ($doctorId) {
            $must[] = ['term' => ['SXYS' => $doctorId]];
        }

        $topSize = ($isTop10 === 1) ? 10 : 500;

        // 确定排序方向
        $esOrder = ($order === 'asc') ? 'asc' : 'desc';

        // 按医师聚合
        $aggs = [
            'SXYS' => [
                'terms' => [
                    "field" => 'SXYS',
                    "size" => $topSize,
                    "order" => ['_count' => $esOrder],
                ],
            ],
        ];

        // 查询医师下所有病历
        $params = $omrBl01Service->clearMust()
            ->queryByMustBatch($must)
            ->aggs($aggs)
            ->paginate(1, 0)
            ->getParams();
        $restful = app('es')->search($params);
        $dataAll = $omrBl01Service->getDataByEs($restful);
        $dataAll = !empty($dataAll[2]['SXYS']['buckets']) ? $dataAll[2]['SXYS']['buckets'] : [];
        $dataAllMap = [];
        foreach ($dataAll as $item) {
            $dataAllMap[$item['key']] = [
                'count' => $item['doc_count'] ?? 0,
            ];
        }

        // 查询医师下有缺陷的病历
        $must[] = ['term' => ['is_defect' => 1]];
        $params = $omrBl01Service->clearMust()
            ->queryByMustBatch($must)
            ->aggs($aggs)
            ->paginate(1, 0)
            ->getParams();
        $restful = app('es')->search($params);
        $dataQx = $omrBl01Service->getDataByEs($restful);
        $dataQx = !empty($dataQx[2]['SXYS']['buckets']) ? $dataQx[2]['SXYS']['buckets'] : [];
        $dataQxMap = array_column($dataQx, 'doc_count', 'key');

        // 查询甲乙丙等级分布（按医师聚合，再按等级聚合）
        $aggsGrade = [
            'SXYS' => [
                'terms' => [
                    "field" => 'SXYS',
                    "size" => $topSize,
                ],
                'aggs' => [
                    'score_lv' => [
                        'terms' => [
                            'field' => 'score_lv.keyword',
                            'size' => 100
                        ]
                    ]
                ]
            ],
        ];

        // 移除 is_defect 条件，查询所有病历的等级分布
        array_pop($must);
        $params = $omrBl01Service->clearMust()
            ->queryByMustBatch($must)
            ->aggs($aggsGrade)
            ->paginate(1, 0)
            ->getParams();
        $restful = app('es')->search($params);
        $dataGrade = $omrBl01Service->getDataByEs($restful);
        $dataGrade = !empty($dataGrade[2]['SXYS']['buckets']) ? $dataGrade[2]['SXYS']['buckets'] : [];

        // 处理等级数据
        $gradeMap = [];
        foreach ($dataGrade as $doctorItem) {
            $doctorKey = $doctorItem['key'];
            $gradeMap[$doctorKey] = [
                'grade_a' => 0,
                'grade_b' => 0,
                'grade_c' => 0,
            ];

            $scoreLvBuckets = $doctorItem['score_lv']['buckets'] ?? [];
            foreach ($scoreLvBuckets as $bucket) {
                $lvKey = $bucket['key'] ?? '';
                $lvKey = is_string($lvKey) ? $lvKey : '';
                $lvKey = strip_tags($lvKey);
                $lvKey = trim($lvKey);
                if (strpos($lvKey, '/') !== false) {
                    $lvKey = substr($lvKey, strrpos($lvKey, '/') + 1);
                }
                $lvKey = trim($lvKey);
                $count = intval($bucket['doc_count'] ?? 0);

                if ($lvKey === '甲') {
                    $gradeMap[$doctorKey]['grade_a'] += $count;
                } elseif ($lvKey === '乙') {
                    $gradeMap[$doctorKey]['grade_b'] += $count;
                } elseif ($lvKey === '丙') {
                    $gradeMap[$doctorKey]['grade_c'] += $count;
                }
            }
        }

        // 查询问题数量和扣分（优化：分步查询，减少关联）
        // 第一步：查询问题数量和规则ID分组
        $issueQuery = Omr_Quality::query()
            ->leftJoin('OMR_BL01', 'omr_quality.BLBH', '=', 'OMR_BL01.BLBH')
            ->where('OMR_BL01.BLZT', 1);

        if ($startTime) {
            $issueQuery->whereBetween('OMR_BL01.JLSJ', [$startTime, $endTime]);
        }
        if ($depId) {
            $issueQuery->where('OMR_BL01.BRKS', $depId);
        }
        if ($doctorId) {
            $issueQuery->where('OMR_BL01.SXYS', $doctorId);
        }

        // 按医师和规则分组统计
        $issueRawData = $issueQuery->selectRaw('OMR_BL01.SXYS, omr_quality.rule_id, COUNT(*) as count')
            ->groupBy('OMR_BL01.SXYS', 'omr_quality.rule_id')
            ->get();

        // 第二步：批量获取规则分数（一次查询）
        $ruleIds = $issueRawData->pluck('rule_id')->unique()->filter();
        $ruleScores = [];
        if ($ruleIds->isNotEmpty()) {
            $ruleScores = OmrRule::whereIn('id', $ruleIds)->pluck('score', 'id')->toArray();
        }

        // 第三步：在应用层计算每个医师的问题数量和扣分
        $issueData = [];
        foreach ($issueRawData as $item) {
            $sxys = $item->SXYS;
            $ruleId = $item->rule_id;
            $count = $item->count;
            $score = $ruleScores[$ruleId] ?? 0;

            if (!isset($issueData[$sxys])) {
                $issueData[$sxys] = [
                    'issue_count' => 0,
                    'total_deduct_score' => 0
                ];
            }

            $issueData[$sxys]['issue_count'] += $count;
            $issueData[$sxys]['total_deduct_score'] += ($score * $count);
        }

        // 获取医师信息
        $doctorCodes = array_keys($dataAllMap);
        $staffMap = Staff::query()
            ->whereIn('code', $doctorCodes)
            ->get(['code', 'name', 'ksdm'])
            ->keyBy('code')
            ->toArray();

        // 获取科室信息
        $department = Department::getDepartmentData();

        // 组装数据
        $doctorData = [];
        foreach ($dataAllMap as $key => $value) {
            $totalMedical = $value['count'];
            $gradeA = $gradeMap[$key]['grade_a'] ?? 0;
            $gradeB = $gradeMap[$key]['grade_b'] ?? 0;
            $gradeC = $gradeMap[$key]['grade_c'] ?? 0;

            // 计算占比
            $gradeARate = $totalMedical > 0 ? number_format($gradeA / $totalMedical * 100, 2, '.', '') : '0.00';
            $gradeBRate = $totalMedical > 0 ? number_format($gradeB / $totalMedical * 100, 2, '.', '') : '0.00';
            $gradeCRate = $totalMedical > 0 ? number_format($gradeC / $totalMedical * 100, 2, '.', '') : '0.00';

            // 获取问题数量和扣分
            $issueCount = isset($issueData[$key]) ? (int)$issueData[$key]['issue_count'] : 0;
            $deductScore = isset($issueData[$key]) ? (float)$issueData[$key]['total_deduct_score'] : 0;

            // 获取医师姓名和科室
            $doctorName = isset($staffMap[$key]) ? $staffMap[$key]['name'] : $key;
            $depId = isset($staffMap[$key]) ? $staffMap[$key]['ksdm'] : '';
            $depName = $depId ? ($department[$depId] ?? $depId) : '';

            $doctorData[] = [
                'doctor_code' => $key,
                'doctor_name' => $doctorName,
                'dep_id' => $depId,
                'dep_name' => $depName,
                'total_medical' => $totalMedical,
                'total_error_medical' => $dataQxMap[$key] ?? 0,
                'deduct_score' => number_format($deductScore, 2, '.', ''),
                'issue_count' => $issueCount,
                'grade_a_count' => $gradeA,
                'grade_a_rate' => $gradeARate,
                'grade_b_count' => $gradeB,
                'grade_b_rate' => $gradeBRate,
                'grade_c_count' => $gradeC,
                'grade_c_rate' => $gradeCRate,
            ];
        }

        // 按病历数量排序（已在 ES 查询时排序，这里保持顺序）
        // 注意：由于后续还需要合并等级数据，所以这里不再重新排序
        // $doctorData 已经按照 ES 返回的顺序（即 total_medical 的排序）

        // 总数
        $totalCount = count($doctorData);

        // is_top10=1 时只返回前10
        if ($isTop10 === 1) {
            $doctorData = array_slice($doctorData, 0, 10);
        }

        // 如果是导出，直接返回导出文件
        if ($isExport === 1) {
            return $this->exportRankingDoctor($doctorData);
        }

        // 分页处理
        $page = max($page, 1);
        $pageSize = max($pageSize, 1);
        $offset = ($page - 1) * $pageSize;
        $returnData = array_slice($doctorData, $offset, $pageSize);

        return ToolsService::returnData(200, [
            'list' => $returnData,
            'count' => $totalCount,
            'page' => $page,
            'page_size' => $pageSize,
        ], '');
    }


    /**
     * 所有缺陷问题列表（不区分规则，按就诊时间倒序）
     * @param Request $request
     * @return array
     */
    public function defectIssuesAll(Request $request)
    {
        $startTime = $request->post('start_time', '');   // 开始时间
        $endTime = $request->post('end_time', '');       // 结束时间
        $depId = $request->post('dep_id', '');           // 科室ID
        $doctorId = $request->post('doctor_id', '');     // 医师ID
        $reviewStatus = $request->post('review_status', ''); // 审核状态：默认全部，支持 已审核/未审核/1/0
        $page = (int)$request->post('page', 1);
        $pageSize = (int)$request->post('page_size', 10);
        $isExport = (int)$request->post('is_export', 0); // 是否导出

        // 使用数据库查询，因为需要关联 OMR_BL01 表来过滤科室和医师
        $query = Omr_Quality::query()
            ->leftJoin('OMR_BL01', 'omr_quality.BLBH', '=', 'OMR_BL01.BLBH')
            ->where('OMR_BL01.BLZT', 1);

        // 时间范围：按就诊时间 JLSJ 过滤，默认本月1号至今天
        if ($startTime && $endTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $query->whereBetween('OMR_BL01.JLSJ', [$startTime, $endTime]);
        } elseif ($startTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $query->where('OMR_BL01.JLSJ', '>=', $startTime);
        } elseif ($endTime) {
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $query->where('OMR_BL01.JLSJ', '<=', $endTime);
        } else {
            // 默认本月1号 00:00:00 到当天 23:59:59
            $now = date('Y-m-d');
            $startTime = date('Y-m-01') . ' 00:00:00';
            $endTime = $now . ' 23:59:59';
            $query->whereBetween('OMR_BL01.JLSJ', [$startTime, $endTime]);
        }

        // 科室过滤
        if ($depId) {
            $query->where('OMR_BL01.BRKS', $depId);
        }


        // 审核状态过滤：默认全部；已审核/1 走 exists，未审核/0 走 not exists
        if ($reviewStatus !== '' && $reviewStatus !== null) {
            if (in_array($reviewStatus, ['已审核', '1', 1], true)) {
                $query->whereExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('MZFK')
                        ->whereColumn('MZFK.blbh', 'omr_quality.BLBH')
                        ->whereColumn('MZFK.rule_id', 'omr_quality.rule_id');
                });
            } elseif (in_array($reviewStatus, ['未审核', '0', 0], true)) {
                $query->whereNotExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('MZFK')
                        ->whereColumn('MZFK.blbh', 'omr_quality.BLBH')
                        ->whereColumn('MZFK.rule_id', 'omr_quality.rule_id');
                });
            }
        }

        // 医师过滤
        if ($doctorId) {
            $query->where('OMR_BL01.SXYS', $doctorId);
        }

        // 获取总数
        $count = $query->count();

        // 导出时使用流式导出，避免一次性加载所有数据到内存
        if ($isExport == 1) {
            // 根据实际情况限制最大导出数量，避免极端情况下仍然内存溢出
            if ($count > 200000) {
                return ToolsService::returnData(400, [], '导出数据量过大，请缩小时间范围或优化筛选条件后再尝试导出');
            }

            return $this->exportDefectIssuesAll(clone $query);
        }

        // 分页查询，按就诊时间倒序
        $rawList = $query->select('omr_quality.*', 'OMR_BL01.BRKS', 'OMR_BL01.SXYS', 'OMR_BL01.JLSJ')
            ->orderBy('OMR_BL01.JLSJ', 'desc')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get()
            ->toArray();

        $list = [];
        if (!empty($rawList)) {
            // 当前页反馈信息映射：BLBH_ruleId => [fsr, fssj]
            $feedbackMap = [];
            $feedbackConditions = [];
            foreach ($rawList as $item) {
                if (!empty($item['BLBH']) && isset($item['rule_id']) && $item['rule_id'] !== '') {
                    $feedbackConditions[] = [
                        'blbh' => $item['BLBH'],
                        'rule_id' => $item['rule_id'],
                    ];
                }
            }

            if (!empty($feedbackConditions)) {
                $feedbackList = MZFK::query()
                    ->select(['blbh', 'rule_id', 'fsr', 'fssj'])
                    ->where(function ($query) use ($feedbackConditions) {
                        foreach ($feedbackConditions as $condition) {
                            $query->orWhere(function ($subQuery) use ($condition) {
                                $subQuery->where('blbh', $condition['blbh'])
                                    ->where('rule_id', $condition['rule_id']);
                            });
                        }
                    })
                    ->orderBy('fssj', 'desc')
                    ->get()
                    ->toArray();

                foreach ($feedbackList as $feedback) {
                    $feedbackKey = ($feedback['blbh'] ?? '') . '_' . ($feedback['rule_id'] ?? '');
                    if (!isset($feedbackMap[$feedbackKey])) {
                        $feedbackMap[$feedbackKey] = $feedback;
                    }
                }
            }

            // 规则说明映射：id => notice
            $ruleInfo = OmrRule::query()->pluck('notice', 'id')->toArray();
            // 自定义规则
            $customRules = RuleSetting::query()->where('rule_type', '=', '门诊规则')->get(['id', 'object', 'description'])->keyBy('id')->toArray();
            // 获取科室
            $department = Department::getDepartmentData();

            foreach ($rawList as $item) {
                $feedbackKey = ($item['BLBH'] ?? '') . '_' . ($item['rule_id'] ?? '');
                $feedback = $feedbackMap[$feedbackKey] ?? null;

                // 审核信息
                $item['review_doctor'] = $feedback['fsr'] ?? '';
                $item['review_time'] = $feedback['fssj'] ?? '';
                $item['review_status'] = $feedback ? '已审核' : '未审核';

                // 身份证号脱敏
                if (!empty($item['SFZH'])) {
                    //$item['SFZH'] = desensitize($item['SFZH'], 6, 8);
                    $item['SFZH'] = $item['SFZH'];
                }

                // 病人科室名称
                if (!empty($item['ks'])) {
                    $item['dep_name'] = $item['ks'];
                } else {
                    $item['dep_name'] = $department[$item['BRKS']] ?? $item['BRKS'] ?? '';
                }

                // 医生签名名称
                if (!empty($item['SXYS'])) {
                    $staffData = Staff::query()->where('code', $item['SXYS'])->first();
                    $item['SXYS_NAME'] = !empty($staffData) ? $staffData->name : '';
                }

                // 规则提示说明：根据每条记录自身的 rule_id 映射
                if (!empty($item['rule_id'])) {
                    $rid = (int)$item['rule_id'];
                    if ($rid < 1000000) {
                        // 标准规则
                        $item['rule_notice'] = !empty($ruleInfo[$rid]) ? $ruleInfo[$rid] : '';
                    } else {
                        // 自定义规则
                        $customRuleId = $rid - 1000000;
                        $item['rule_notice'] = !empty($customRules[$customRuleId]['description']) ? $customRules[$customRuleId]['description'] : '';
                    }
                } else {
                    $item['rule_notice'] = '';
                }

                // 注意：与 errorList 不同，这里保留 rule_id 字段
                $list[] = $item;
            }
        }

        $returnData = [
            'list' => $list,
            'count' => $count,
        ];

        return ToolsService::returnData(200, $returnData, '');
    }

    /**
     * 导出所有缺陷问题列表（按病历编号 BLBH 分组，流式导出）
     * 同一门诊号可能多次就诊，按 OMR_BL01 的 BLBH 分组可区分每次就诊
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    private function exportDefectIssuesAll($query)
    {
        // 预先加载规则、科室等映射数据
        $ruleInfo = OmrRule::query()->pluck('notice', 'id')->toArray();
        $customRules = RuleSetting::query()
            ->where('rule_type', '=', '门诊规则')
            ->get(['id', 'object', 'description'])
            ->keyBy('id')
            ->toArray();
        $department = Department::getDepartmentData();

        // 1）获取所有包含的唯一规则ID，确定有多少个缺陷列
        $queryForRules = clone $query;
        $queryForRules->whereNotNull('omr_quality.BLBH')->where('omr_quality.BLBH', '!=', '')
            ->whereNotNull('omr_quality.mzh')->where('omr_quality.mzh', '!=', '');

        $uniqueRuleIds = $queryForRules->whereNotNull('omr_quality.rule_id')
            ->select('omr_quality.rule_id')
            ->distinct()
            ->pluck('omr_quality.rule_id')
            ->toArray();

        $uniqueRuleIds = array_values(array_filter($uniqueRuleIds));

        $ruleIdColumnIndexMap = [];
        foreach ($uniqueRuleIds as $index => $rId) {
            $ruleIdColumnIndexMap[$rId] = $index;
        }
        $issueColumnsCount = count($uniqueRuleIds);

        // 如果没有数据，直接导出只有表头的空文件
        $title = ['就诊时间', '门诊号', '患者姓名', '科室', '性别', '年龄', '初步诊断', '身份证号', '医生签名', '审核状态', '质控审核医师', '质控审核时间'];
        if ($issueColumnsCount <= 0) {
            $rowGenerator = function () {
                if (false) {
                    yield [];
                }
            };

            $fileName = '缺陷问题列表_' . date('YmdHis') . '.xlsx';

            return Excel::download(
                new DefectIssuesAllExport($title, $rowGenerator, ['B', 'H']),
                $fileName
            );
        }

        // 构建表头：基本信息 + 动态缺陷列
        for ($i = 1; $i <= $issueColumnsCount; $i++) {
            $title[] = '问题' . $i;
        }

        // 医生签名名称缓存，避免大量重复查询
        $staffNameMap = [];

        // 按病历编号缓存反馈信息，避免导出时重复查询
        $feedbackByBlbhCache = [];

        // 2）使用 cursor + 按病历编号(BLBH)分组，流式生成每一行
        $rowGenerator = function () use ($query, $issueColumnsCount, $ruleIdColumnIndexMap, $ruleInfo, $customRules, $department, &$staffNameMap, &$feedbackByBlbhCache) {
            $loadFeedbackByBlbh = function ($blbh) use (&$feedbackByBlbhCache) {
                if (isset($feedbackByBlbhCache[$blbh])) {
                    return $feedbackByBlbhCache[$blbh];
                }

                $feedbackMap = [];
                $feedbackList = MZFK::query()
                    ->select(['blbh', 'rule_id', 'fsr', 'fssj'])
                    ->where('blbh', $blbh)
                    ->orderBy('fssj', 'desc')
                    ->get()
                    ->toArray();

                foreach ($feedbackList as $feedback) {
                    $feedbackKey = ($feedback['blbh'] ?? '') . '_' . ($feedback['rule_id'] ?? '');
                    if (!isset($feedbackMap[$feedbackKey])) {
                        $feedbackMap[$feedbackKey] = $feedback;
                    }
                }

                $feedbackByBlbhCache[$blbh] = $feedbackMap;
                return $feedbackMap;
            };
            $currentBlbh = null;
            $currentRow = null;

            $rowsQuery = clone $query;
            $rowsQuery->whereNotNull('omr_quality.BLBH')->where('omr_quality.BLBH', '!=', '')
                ->whereNotNull('omr_quality.mzh')->where('omr_quality.mzh', '!=', '')
                ->select('omr_quality.*', 'OMR_BL01.BRKS', 'OMR_BL01.SXYS', 'OMR_BL01.JLSJ')
                ->orderBy('omr_quality.BLBH')
                ->orderBy('OMR_BL01.JLSJ', 'desc');

            foreach ($rowsQuery->cursor() as $item) {
                $data = $item->toArray();

                // 身份证号脱敏
                if (!empty($data['SFZH'])) {
                    //$data['SFZH'] = desensitize($data['SFZH'], 6, 8);
                    $data['SFZH'] = $data['SFZH'];
                }

                // 病人科室名称
                if (!empty($data['ks'])) {
                    $data['dep_name'] = $data['ks'];
                } else {
                    $data['dep_name'] = $department[$data['BRKS']] ?? $data['BRKS'] ?? '';
                }

                // 医生签名名称（带简单缓存）
                if (!empty($data['SXYS'])) {
                    if (!isset($staffNameMap[$data['SXYS']])) {
                        $staffData = Staff::query()->where('code', $data['SXYS'])->first();
                        $staffNameMap[$data['SXYS']] = !empty($staffData) ? $staffData->name : '';
                    }
                    $data['SXYS_NAME'] = $staffNameMap[$data['SXYS']];
                } else {
                    $data['SXYS_NAME'] = '';
                }

                // 规则提示说明：根据每条记录自身的 rule_id 映射
                if (!empty($data['rule_id'])) {
                    $rid = (int)$data['rule_id'];
                    if ($rid < 1000000) {
                        // 标准规则
                        $data['rule_notice'] = !empty($ruleInfo[$rid]) ? $ruleInfo[$rid] : '';
                    } else {
                        // 自定义规则
                        $customRuleId = $rid - 1000000;
                        $data['rule_notice'] = !empty($customRules[$customRuleId]['description']) ? $customRules[$customRuleId]['description'] : '';
                    }
                } else {
                    $data['rule_notice'] = '';
                }
                $blbh = $data['BLBH'] ?? '';
                $currentFeedbackMap = $loadFeedbackByBlbh($blbh);
                $feedbackKey = $blbh . '_' . ($data['rule_id'] ?? '');
                $feedback = $currentFeedbackMap[$feedbackKey] ?? null;

                // 初始化当前病历编号
                if ($currentBlbh === null) {
                    $currentBlbh = $blbh;
                    $currentRow = [
                        'JLSJ' => $data['JLSJ'] ?? '',
                        'mzh' => $data['mzh'] ?? '',
                        'xm' => $data['xm'] ?? '',
                        'dep_name' => $data['dep_name'] ?? '',
                        'xb' => $data['xb'] ?? '',
                        'nl' => $data['nl'] ?? '',
                        'cbzd' => $data['cbzd'] ?? '',
                        'SFZH' => $data['SFZH'] ?? '',
                        'SXYS_NAME' => $data['SXYS_NAME'] ?? '',
                        'review_status' => '未审核',
                        'review_doctors' => [],
                        'review_times' => [],
                        'defects' => array_fill(0, $issueColumnsCount, ''),
                    ];
                }

                // 如果病历编号变化，先输出上一条，再初始化新的 BLBH
                if ($blbh !== $currentBlbh) {
                    $row = [
                        $currentRow['JLSJ'],
                        (string)$currentRow['mzh'],
                        $currentRow['xm'],
                        $currentRow['dep_name'],
                        $currentRow['xb'],
                        $currentRow['nl'],
                        $currentRow['cbzd'],
                        (string)$currentRow['SFZH'],
                        $currentRow['SXYS_NAME'],
                        $currentRow['review_status'],
                        implode('；', $currentRow['review_doctors']),
                        implode('；', $currentRow['review_times']),
                    ];

                    for ($i = 0; $i < $issueColumnsCount; $i++) {
                        $row[] = $currentRow['defects'][$i] ?? '';
                    }

                    yield $row;

                    // 切换到新的病历编号
                    $currentBlbh = $blbh;
                    $currentRow = [
                        'JLSJ' => $data['JLSJ'] ?? '',
                        'mzh' => $data['mzh'] ?? '',
                        'xm' => $data['xm'] ?? '',
                        'dep_name' => $data['dep_name'] ?? '',
                        'xb' => $data['xb'] ?? '',
                        'nl' => $data['nl'] ?? '',
                        'cbzd' => $data['cbzd'] ?? '',
                        'SFZH' => $data['SFZH'] ?? '',
                        'SXYS_NAME' => $data['SXYS_NAME'] ?? '',
                        'review_status' => '未审核',
                        'review_doctors' => [],
                        'review_times' => [],
                        'defects' => array_fill(0, $issueColumnsCount, ''),
                    ];
                }

                if (!empty($feedback)) {
                    $currentRow['review_status'] = '已审核';
                    if (!empty($feedback['fsr']) && !in_array($feedback['fsr'], $currentRow['review_doctors'])) {
                        $currentRow['review_doctors'][] = $feedback['fsr'];
                    }
                    if (!empty($feedback['fssj']) && !in_array($feedback['fssj'], $currentRow['review_times'])) {
                        $currentRow['review_times'][] = $feedback['fssj'];
                    }
                }

                // 同一个病历编号(BLBH)下追加缺陷描述
                $defectRuleId = $data['rule_id'] ?? null;
                if ($defectRuleId && isset($ruleIdColumnIndexMap[$defectRuleId])) {
                    $idx = $ruleIdColumnIndexMap[$defectRuleId];
                    $notice = $data['rule_notice'] ?? '';
                    if ($currentRow['defects'][$idx] !== '' && $currentRow['defects'][$idx] !== $notice) {
                        $currentRow['defects'][$idx] .= '；' . $notice;
                    } else {
                        $currentRow['defects'][$idx] = $notice;
                    }
                }
            }

            // 输出最后一条病历的数据
            if ($currentRow !== null) {
                $row = [
                    $currentRow['JLSJ'],
                    (string)$currentRow['mzh'],
                    $currentRow['xm'],
                    $currentRow['dep_name'],
                    $currentRow['xb'],
                    $currentRow['nl'],
                    $currentRow['cbzd'],
                    (string)$currentRow['SFZH'],
                    $currentRow['SXYS_NAME'],
                    $currentRow['review_status'],
                    implode('；', $currentRow['review_doctors']),
                    implode('；', $currentRow['review_times']),
                ];

                for ($i = 0; $i < $issueColumnsCount; $i++) {
                    $row[] = $currentRow['defects'][$i] ?? '';
                }

                yield $row;
            }
        };

        $fileName = '缺陷问题列表_' . date('YmdHis') . '.xlsx';

        return Excel::download(
            new DefectIssuesAllExport($title, $rowGenerator, ['B', 'H']),
            $fileName
        );
    }

    /**
     * 缺陷列表
     * @param Request $request
     * @return array
     */
    public function errorList(Request $request)
    {
        $ruleId = $request->post('rule_id', '');         // 规则ID
        $depId = $request->post('dep_id', '');           // 科室ID
        $sfzh = $request->post('sfzh', '');              // 身份证号
        $doctorId = $request->post('doctor_id', '');     // 医生签名
        $startTime = $request->post('start_time', '');   // 开始时间
        $endTime = $request->post('end_time', '');       // 结束时间
        $isError = $request->post('is_error', 0);        // 是否查询错误信息
        $mzh = $request->post('mzh', '');                // 门诊号
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        $isExport = $request->post('is_export', 0);      // 是否导出
        $isDep = $request->post('is_dep', 0);            // 是否导出科室统计（1=科室统计，0=缺陷列表）

        if (!$startTime) {
            $startTime = date('Y-m-01');
        }
        if (!$endTime) {
            $endTime = date('Y-m-d');
        }
        $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
        $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';

        // 当查询错误信息且有规则ID时，使用数据库查询（因为需要关联 OMR_BL01 来过滤科室和医师）
        if ($isError && $ruleId) {
            $query = Omr_Quality::query()
                ->leftJoin('OMR_BL01', 'omr_quality.BLBH', '=', 'OMR_BL01.BLBH')
                ->leftJoin('MS_BRDA', 'MS_BRDA.BRID', '=', 'OMR_BL01.BRID')
                ->where('OMR_BL01.BLZT', 1);

            // 规则ID
            if ($ruleId) {
                $query->where('omr_quality.rule_id', $ruleId);
            }

            // 科室ID
            if ($depId) {
                $query->where('OMR_BL01.BRKS', $depId);
            }

            // 医师ID
            if ($doctorId) {
                $query->where('OMR_BL01.SXYS', $doctorId);
            }

            // 身份证号
            if ($sfzh) {
                $query->where(function ($q) use ($sfzh) {
                    $q->where('omr_quality.SFZH', $sfzh)
                        ->orWhere('OMR_BL01.SFZH', $sfzh)
                        ->orWhere('MS_BRDA.SFZH', $sfzh);
                });
            }

            // 门诊号
            if ($mzh) {
                $query->where(function ($q) use ($mzh) {
                    $q->where('omr_quality.mzh', 'like', '%' . $mzh . '%')
                        ->orWhere('OMR_BL01.mzh', 'like', '%' . $mzh . '%')
                        ->orWhere('MS_BRDA.MZHM', 'like', '%' . $mzh . '%');
                });
            }

            // 时间范围
            $query->whereBetween('OMR_BL01.JLSJ', [$startTime, $endTime]);

            // 获取总数
            $count = $query->count();

            // 分页查询 - 如果是导出则不分页
            $selectColumns = [
                'omr_quality.*',
                'OMR_BL01.BRKS',
                'OMR_BL01.SXYS',
                'OMR_BL01.BLZT',
                'OMR_BL01.ks',
                DB::raw('COALESCE(NULLIF(omr_quality.mzh, \'\'), NULLIF(OMR_BL01.mzh, \'\'), MS_BRDA.MZHM) as mzh'),
                DB::raw('COALESCE(NULLIF(omr_quality.SFZH, \'\'), NULLIF(OMR_BL01.SFZH, \'\'), MS_BRDA.SFZH) as SFZH'),
                DB::raw('COALESCE(NULLIF(omr_quality.xm, \'\'), NULLIF(OMR_BL01.xm, \'\'), MS_BRDA.BRXM) as xm'),
                DB::raw('COALESCE(NULLIF(omr_quality.xb, \'\'), NULLIF(OMR_BL01.xb, \'\')) as xb'),
                DB::raw('MS_BRDA.BRXB as xb1'),
            ];
            if ($isExport == 1) {
                $rawList = $query->select($selectColumns)
                    ->orderBy('omr_quality.id', 'desc')
                    ->get()
                    ->toArray();
            } else {
                $rawList = $query->select($selectColumns)
                    ->orderBy('omr_quality.id', 'desc')
                    ->offset(($page - 1) * $pageSize)
                    ->limit($pageSize)
                    ->get()
                    ->toArray();
            }

            $data = [$rawList, $count];
        } else {
            $query = OMR_BL01::query()
                ->leftJoin('MS_BRDA', 'MS_BRDA.BRID', '=', 'OMR_BL01.BRID')
                ->where('OMR_BL01.BLZT', 1)
                ->whereBetween('OMR_BL01.JLSJ', [$startTime, $endTime]);

            if ($depId) {
                $query->where('OMR_BL01.BRKS', $depId);
            }

            if ($sfzh) {
                $query->where(function ($q) use ($sfzh) {
                    $q->where('OMR_BL01.SFZH', $sfzh)
                        ->orWhere('MS_BRDA.SFZH', $sfzh);
                });
            }

            if ($doctorId) {
                $query->where('OMR_BL01.SXYS', $doctorId);
            }

            if ($mzh) {
                $query->where(function ($q) use ($mzh) {
                    $q->where('OMR_BL01.mzh', 'like', '%' . $mzh . '%')
                        ->orWhere('MS_BRDA.MZHM', 'like', '%' . $mzh . '%');
                });
            }

            if ($isError) {
                $query->where('OMR_BL01.is_defect', 1);
            }

            $count = (clone $query)->count();

            $selectColumns = [
                'OMR_BL01.*',
                DB::raw('COALESCE(NULLIF(OMR_BL01.mzh, \'\'), MS_BRDA.MZHM) as mzh'),
                DB::raw('COALESCE(NULLIF(OMR_BL01.SFZH, \'\'), MS_BRDA.SFZH) as SFZH'),
                DB::raw('COALESCE(NULLIF(OMR_BL01.xm, \'\'), MS_BRDA.BRXM) as xm'),
                DB::raw('MS_BRDA.BRXB as xb1'),
            ];
            if ($isExport == 1) {
                $rawList = (clone $query)
                    ->select($selectColumns)
                    ->orderBy('OMR_BL01.JLSJ', 'desc')
                    ->get()
                    ->toArray();
            } else {
                $rawList = (clone $query)
                    ->select($selectColumns)
                    ->orderBy('OMR_BL01.JLSJ', 'desc')
                    ->offset(($page - 1) * $pageSize)
                    ->limit($pageSize)
                    ->get()
                    ->toArray();
            }

            $data = [$rawList, $count];
        }

        $list = [];
        if ($data[0]) {
            $ruleInfo = OmrRule::query()->pluck('notice', 'id')->toArray();
            // 自定义规则
            $customRules = RuleSetting::query()->where('rule_type', '=', '门诊规则')->get(['id', 'object', 'description'])->keyBy('id')->toArray();
            // 获取科室
            $department = OmrDepartment::getOmrDepartmentData();
            $staffNameMap = [];
            $staffCodes = array_filter(array_unique(array_column($data[0], 'SXYS')));
            if (!empty($staffCodes)) {
                $staffNameMap = Staff::query()
                    ->whereIn('code', array_values($staffCodes))
                    ->pluck('name', 'code')
                    ->toArray();
            }

            // 收集所有 BLBH，用于批量查询问题数量
            $blbhList = array_column($data[0], 'BLBH');
            $blbhList = array_filter($blbhList);

            // 批量查询每个 BLBH 在 omr_quality 表中的问题数量
            $issueCountMap = [];
            if (!empty($blbhList)) {
                $issueQuery = Omr_Quality::query()->whereIn('BLBH', $blbhList);

                // 如果传入了 rule_id，只统计该规则的问题数量
                if ($ruleId) {
                    $issueQuery->where('rule_id', $ruleId);
                }

                $issueCounts = $issueQuery->selectRaw('BLBH, COUNT(*) as issue_count')
                    ->groupBy('BLBH')
                    ->pluck('issue_count', 'BLBH')
                    ->toArray();
                $issueCountMap = $issueCounts;
            }

            foreach ($data[0] as $value) {
                if (!empty($value['SFZH'])) {
                    // 身份证号脱敏
                    //$value['SFZH'] = desensitize($value['SFZH'], 6, 8);
                    $value['SFZH'] = $value['SFZH'];
                }

                if (empty($value['xb']) && !empty($value['xb1'])) {
                    if ($value['xb1'] == 1) {
                        $value['xb'] = '男';
                    } elseif ($value['xb1'] == 2) {
                        $value['xb'] = '女';
                    } else {
                        $value['xb'] = '未知';
                    }
                }

                // 病人科室
                if (!empty($value["ks"])) {
                    $value['dep_name'] = $value["ks"];
                } else {
                    $value['dep_name'] = $department[$value['BRKS']] ?? $value['BRKS'];
                }

                // 医生签名
                if (!empty($value['SXYS'])) {
                    $value['SXYS_NAME'] = $staffNameMap[$value['SXYS']] ?? '';
                }
                // 规则描述处理
                if (!empty($ruleId)) {
                    $ruleId = (int)$ruleId;
                    if ($ruleId < 1000000) {
                        // 标准规则
                        $value['rule_notice'] = !empty($ruleInfo[$ruleId]) ? $ruleInfo[$ruleId] : '';
                    } else {
                        // 自定义规则
                        $customRuleId = $ruleId - 1000000;
                        $value['rule_notice'] = !empty($customRules[$customRuleId]['description']) ? $customRules[$customRuleId]['description'] : '';
                    }
                } else {
                    $value['rule_notice'] = '';
                }

                // 添加问题数量字段
                $value['issue_count'] = isset($issueCountMap[$value['BLBH']]) ? (int)$issueCountMap[$value['BLBH']] : 0;

                $list[] = $value;
            }
        }

        $returnData = [
            'list' => $list,
            'count' => $data[1]
        ];

        // 当 is_error 和 rule_id 都传入时，返回科室维度的缺陷统计
        if ($isError && $ruleId) {
            $departmentStats = $this->getDepartmentDefectStats($ruleId, $startTime, $endTime, $doctorId, $sfzh, $mzh, $depId);
            $returnData['department_stats'] = $departmentStats;

            // 如果是导出
            if ($isExport == 1) {
                if ($isDep == 1) {
                    // 导出科室统计
                    return $this->exportErrorListDepartment($departmentStats);
                } else {
                    // 导出缺陷列表
                    return $this->exportErrorListDefect($list);
                }
            }
        } else {
            // 非 is_error 和 rule_id 的情况也支持导出
            if ($isExport == 1) {
                return $this->exportErrorListDefect($list, '病历列表');
            }
        }

        return ToolsService::returnData(200, $returnData, '');
    }

    /**
     * 获取科室维度的缺陷统计
     * @param string $ruleId 规则ID
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param string $doctorId 医生ID
     * @param string $sfzh 身份证号
     * @param string $mzh 门诊号
     * @param string $depId 科室ID
     * @return array
     */
    private function getDepartmentDefectStats($ruleId, $startTime, $endTime, $doctorId = '', $sfzh = '', $mzh = '', $depId = '')
    {
        // 查询科室维度的缺陷统计
        $query = Omr_Quality::query()
            ->leftJoin('OMR_BL01', 'omr_quality.BLBH', '=', 'OMR_BL01.BLBH')
            ->where('OMR_BL01.BLZT', 1)
            ->where('omr_quality.rule_id', $ruleId)
            ->whereBetween('OMR_BL01.JLSJ', [$startTime, $endTime]);

        // 科室ID
        if ($depId) {
            $query->where('OMR_BL01.BRKS', $depId);
        }

        // 医师ID
        if ($doctorId) {
            $query->where('OMR_BL01.SXYS', $doctorId);
        }

        // 身份证号
        if ($sfzh) {
            $query->where('omr_quality.SFZH', $sfzh);
        }

        // 门诊号
        if ($mzh) {
            $query->where('omr_quality.mzh', 'like', '%' . $mzh . '%');
        }

        // 按科室分组统计
        $stats = $query->selectRaw('OMR_BL01.BRKS as dep_id, COUNT(*) as defect_count')
            ->groupBy('OMR_BL01.BRKS')
            ->orderBy('defect_count', 'desc')
            ->get()
            ->toArray();

        // 获取科室名称和规则描述
        $department = OmrDepartment::getOmrDepartmentData();

        // 根据 rule_id 判断是标准规则还是自定义规则
        $ruleNotice = '';
        if ($ruleId < 1000000) {
            // 标准规则
            $ruleInfo = OmrRule::query()->where('id', $ruleId)->first();
            $ruleNotice = $ruleInfo ? $ruleInfo->notice : '';
        } else {
            // 自定义规则
            $customRuleId = $ruleId - 1000000;
            $customRule = RuleSetting::query()->where('id', $customRuleId)->where('rule_type', '=', '门诊规则')->first();
            $ruleNotice = $customRule ? $customRule->description : '';
        }

        $result = [];
        foreach ($stats as $item) {
            $result[] = [
                'dep_id' => $item['dep_id'],
                'dep_name' => $department[$item['dep_id']] ?? $item['dep_id'],
                'rule_id' => $ruleId,
                'rule_notice' => $ruleNotice,
                'defect_count' => (int)$item['defect_count']
            ];
        }

        return $result;
    }

    /**
     * 导出缺陷列表 - 科室统计
     * @param array $data
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    private function exportErrorListDepartment($data)
    {
        $title = ['科室名称', '缺陷描述', '缺陷数量'];
        $exportData = [];

        foreach ($data as $item) {
            $exportData[] = [
                $item['dep_name'],
                $item['rule_notice'],
                $item['defect_count'],
            ];
        }

        $fileName = '科室缺陷统计_' . date('YmdHis') . '.xlsx';
        return Excel::download(new ExportData($title, $exportData), $fileName);
    }

    /**
     * 导出缺陷列表 - 缺陷问题
     * @param array $data
     * @param string $fileNamePrefix 文件名前缀，默认为"缺陷问题列表"
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    private function exportErrorListDefect($data, $fileNamePrefix = '缺陷问题列表')
    {
        $title = ['就诊时间', '门诊号码', '患者姓名', '就诊科室', '性别', '年龄', '初步诊断', '身份证号', '签名医师', '医生所属科室', '病历状态', '记录时间'];
        $exportData = [];
        $doctorDepartmentMap = $this->buildDoctorDepartmentNameMap($data);

        foreach ($data as $item) {
            $sxys = $item['SXYS'] ?? '';
            $departmentName = $doctorDepartmentMap[$sxys] ?? '';

            //如果BLZT = 1完成，9作废，0书写中
            $blzt = '';
            if (($item['BLZT'] ?? '') == 1) {
                $blzt = '完成';
            } elseif (($item['BLZT'] ?? '') == 9) {
                $blzt = '作废';
            } elseif (($item['BLZT'] ?? '') == 0) {
                $blzt = '书写中';
            }
            $exportData[] = [
                $item['jzsj'] ?? '',
                (string)($item['mzh'] ?? ''),
                $item['xm'] ?? '',
                $item['dep_name'] ?? '',
                $item['xb'] ?? '',
                $item['nl'] ?? '',
                $item['cbzd'] ?? '',
                (string)($item['SFZH'] ?? ''),
                $item['SXYS_NAME'] ?? '',
                $departmentName ?? '',
                $blzt,
                $item['JLSJ'] ?? ''
            ];
        }

        $fileName = $fileNamePrefix . '_' . date('YmdHis') . '.xlsx';
        return Excel::download(new ExportData($title, $exportData, ['B', 'H']), $fileName);
    }

    /**
     * 缺陷列表导出
     * @param Request $request
     * @return string|null
     */
    public function errorListExport(Request $request)
    {
        $ruleId = $request->post('rule_id', '');         // 规则ID
        $depId = $request->post('dep_id', '');           // 科室ID
        $sfzh = $request->post('sfzh', '');              // 身份证号
        $doctorId = $request->post('doctor_id', '');     // 医生签名
        $startTime = $request->post('start_time', '');   // 开始时间
        $endTime = $request->post('end_time', '');       // 结束时间
        $isError = $request->post('is_error', 1);        // 是否查询错误信息
        $mzh = $request->post('mzh', '');                // 门诊号

        $must = [];
        // 质控规则ID
        if ($ruleId) {
            $must[] = ['term' => ['rule_id' => $ruleId]];
        }
        // 病人科室ID
        if ($depId) {
            $must[] = ['term' => ['BRKS' => $depId]];
        }
        // 身份证号
        if ($sfzh) {
            $must[] = ['term' => ['SFZH' => $sfzh]];
        }
        // 书写医生ID
        if ($doctorId) {
            $must[] = ['term' => ['SXYS' => $doctorId]];
        }
        // 门诊号
        if ($mzh) {
            $must[] = ['match_phrase' => ['mzh' => $mzh]];
        }
        // 就诊时间
        if ($startTime && $endTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $must[] = ['range' => [(($isError && $ruleId) ? 'jzsj.keyword' : 'JLSJ') => ['gte' => $startTime, 'lte' => $endTime]]];
            if (empty($isError)) {
                $must[] = ['range' => ['JLSJ' => ['gte' => $startTime, 'lte' => $endTime]]];
            }
        } elseif ($startTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $must[] = ['range' => [(($isError && $ruleId) ? 'jzsj.keyword' : 'JLSJ') => ['gte' => $startTime]]];
            if (empty($isError)) {
                $must[] = ['range' => ['JLSJ' => ['gte' => $startTime]]];
            }
        } elseif ($endTime) {
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $must[] = ['range' => [(($isError && $ruleId) ? 'jzsj.keyword' : 'JLSJ') => ['lte' => $endTime]]];
            if (empty($isError)) {
                $must[] = ['range' => ['JLSJ' => ['lte' => $endTime]]];
            }
        }

        $omrQualityService = new ElasticsearchService('omr_quality_2023');
        $omrBl01Service = new ElasticsearchService('omr_bl01_2023');
        $staffService = new ElasticsearchService('staff_2023');

        if ($isError && $ruleId) {
            $params = $omrQualityService->clearMust()
                ->queryByMustBatch($must)
                ->source(['jzsj', 'mzh', 'xm', 'BRKS', 'xb', 'nl', 'cbzd', 'SFZH', 'SXYS'])
                ->trackTotalHits()
                ->getParams();
            $restful = app('es')->search($params);
            $data = $omrQualityService->getDataByEs($restful);
        } else {
            $params = $omrBl01Service->clearMust()
                ->queryByMustBatch($must)
                ->queryByMust(['term' => ['BLZT' => 1]])
                ->source(['jzsj', 'mzh', 'xm', 'BRKS', 'xb', 'nl', 'cbzd', 'SFZH', 'SXYS'])
                ->trackTotalHits()
                ->getParams();
            $restful = app('es')->search($params);
            $data = $omrBl01Service->getDataByEs($restful);
        }

        $list[] = ['就诊时间', '门诊号', '姓名', '科室', '性别', '年龄', '初步诊断', '身份证号', '医生签名'];
        if (!empty($data[0])) {
            $data = $data[0];
            // 获取科室
            $department = OmrDepartment::getOmrDepartmentData();
            // 获取医生
            $doctorList = Staff::query()->pluck('name', 'code')->toArray();
            foreach ($data as $value) {
                // 姓名脱敏
                //$value['xm'] = desensitize($value['xm'], 1, 2);
                $value['xm'] = $value['xm'];

                // 身份证号脱敏
                if (!empty($value['SFZH'])) {
                    //$value['SFZH'] = desensitize($value['SFZH'], 6, 8);
                    $value['SFZH'] = $value['SFZH'];
                }

                // 病人科室
                $value['BRKS'] = $department[$value['BRKS']] ?? $value['BRKS'];

                // 医生签名
                if (!empty($value['SXYS'])) {
                    $value['SXYS'] = $doctorList[$value['SXYS']] ?? $value['SXYS'];
                }

                $list[] = [
                    'jzsj' => $value['jzsj'],
                    'mzh' => $this->formatCsvTextValue($value['mzh']),
                    'xm' => $value['xm'],
                    'BRKS' => $value['BRKS'],
                    'xb' => $value['xb'],
                    'nl' => $value['nl'],
                    'cbzd' => $value['cbzd'],
                    'SFZH' => $this->formatCsvTextValue($value['SFZH'] ?? ''),
                    'SXYS' => $value['SXYS'],
                ];
            }
        }

        $csv = new CsvService();
        $csv->filename = $csv->charset('缺陷列表导出', 'UTF-8');

        return $csv->export($list, false);
    }

    /**
     * 获取医生列表
     * @param Request $request
     * @return array
     */
    public function getDoctorList(Request $request)
    {
        $doctorName = $request->post('doctor_name', '');
        $where = [];
        if ($doctorName) {
            $where[] = ['name', 'like', "%" . $doctorName . "%"];
        }
        $doctorData = Staff::query()->where($where)->get(['code as id', 'name'])->toArray();

        return ToolsService::returnData(200, $doctorData, '');
    }

    /**
     * 获取科室列表
     * @param Request $request
     * @return array
     */
    public function getDepartmentList(Request $request)
    {
        $depName = $request->post('dep_name', '');
        $where = [
            ['dep_id', '>', 0],
            ['dep_name', '!=', ''],
            ['status', '=', 0]
        ];
        if ($depName) {
            $where[] = ['dep_name', 'like', "%" . $depName . "%"];
        }
        $departmentList = Department::query()
            ->where($where)
            ->get(['dep_id as id', 'dep_name as name'])->toArray();

        return ToolsService::returnData(200, $departmentList, '');
    }

    /**
     * 门诊号获取（去重）
     * @param Request $request
     * @return array
     */
    public function getMzh(Request $request)
    {
        return OMR_BL01::query()->groupBy('mzh')->pluck('mzh')->toArray();
    }

    /**
     * 二维数组排序
     * @param $array
     * @param $keys
     * @param $sort
     * @return mixed
     */
    public function arraySort($array, $keys, $sort = SORT_DESC)
    {
        $keysValue = [];
        foreach ($array as $k => $v) {
            $keysValue[$k] = $v[$keys];
        }
        array_multisort($keysValue, $sort, $array);
        return $array;
    }

    /**
     * 门诊 - 搜索类型获取
     * @param Request $request
     * @return array
     */
    public function serachTypeList(Request $request)
    {
        $serachList = [
            ['key' => 'BLNR_TXT', 'value' => '全文'],
            ['key' => 'xy', 'value' => '西药'],
            ['key' => 'mzh', 'value' => '门诊号'],
            ['key' => 'ks', 'value' => '科室'],
            ['key' => 'xb', 'value' => '性别'],
            ['key' => 'xm', 'value' => '姓名'],
            // ['key' => 'SFZH', 'value' => '身份证号'],
            ['key' => 'zs', 'value' => '主诉'],
            ['key' => 'xbs', 'value' => '现病史'],
            ['key' => 'jws', 'value' => '既往史'],
            ['key' => 'cbzd', 'value' => '初步诊断'],
            ['key' => 'tgjc', 'value' => '体格检查'],
            ['key' => 'fzjc', 'value' => '辅助检查'],
            ['key' => 'zlyj', 'value' => '诊疗意见'],
            // ['key' => 'bl_type', 'value' => '病历类型']
        ];

        return ToolsService::returnData(200, $serachList, '');
    }

    /**
     * 门诊病历详情接口
     * @param Request $request
     * @return array
     */
    public function omrInfo(Request $request)
    {
        $blbh = $request->post('blbh', '');

        if (empty($blbh)) {
            return ToolsService::returnData(4001, '参数错误', $msg ?? '');
        }
        $userDepId = UserService::getCurrentUserDep($request);
        $isTm = $userDepId == 'admin' ? 0 : 1;

        $omrBl01Service = new ElasticsearchService('omr_bl01_2023');
        $must = ['term' => ['BLBH' => $blbh]];
        $params = $omrBl01Service->clearMust()
            ->queryByMust($must)
            ->getParams();
        $restful = app('es')->search($params);
        $data = $omrBl01Service->getDataByEs($restful);
        $omrInfo = [];
        if (!empty($data[0])) {
            $data = $data[0][0];
            // 表头处理
            $title = '急诊病历';
            $subtitle = '';
            if (env('APP_NAME') == 'binyi') {
                $array = explode('滨州医学院烟台附属医院', $data['BLNR_TXT']);
                if (!empty($array[1])) {
                    if (stripos($array[1], '初诊') !== false) {
                        $title = '门诊病历';
                        $subtitle = '初诊';
                    } elseif (stripos($array[1], '复诊') !== false) {
                        $title = '门诊病历';
                        $subtitle = '复诊';
                    }
                }
            } else {
                if (!empty($data['BLNR_TXT'])) {
                    if (stripos($data['BLNR_TXT'], '初诊') !== false) {
                        $title = '门诊病历';
                        $subtitle = '初诊';
                    } elseif (stripos($data['BLNR_TXT'], '复诊') !== false) {
                        $title = '门诊病历';
                        $subtitle = '复诊';
                    }
                }
            }


            // 查询签名医生信息
            $SYYS = OMR_BLSY::query()
                ->where('BLBH', '=', $data['BLBH'])
                ->pluck('SYYS')->toArray();
            $doctorName = '';
            if ($SYYS) {
                $staffList = Staff::query()->whereIn('code', $SYYS)->get(['name', 'ygjb_text'])->toArray();
                $nameList = [];
                foreach ($staffList as $staffInfo) {
                    $nameList[] = $staffInfo['name'] . "（" . $staffInfo['ygjb_text'] . "）";
                }
                $doctorName = implode('、', $nameList);
            }

            // 查询首次签名时间：omr_blsy 按照 jlsj 正序排序取第一个的 jlsj
            $firstSignTime = OMR_BLSY::query()
                ->where('BLBH', '=', $data['BLBH'])
                ->orderBy('JLSJ', 'asc')
                ->value('JLSJ');

            // 获取创建时间
            $createTime = $data['CJSJ'] ?? '';
            // 查询书写医生
            $staffList = Staff::query()->where(function ($query) use ($data) {
                $query->where('code', $data['SXYS'])
                    ->orWhere('YGBH', $data['SXYS']);
            })->get(['name', 'ygjb_text'])->toArray();
            $SXYS_NAME = !empty($staffList[0]['name']) ? $staffList[0]['name'] . '（' . $staffList[0]['ygjb_text'] . '）' : $data['SXYS'];

            $zlyj = !empty($data['zlyj']) ? explode("\n", $data['zlyj']) : [];
            $xy = !empty($data['xy']) ? explode("\n", $data['xy']) : [];
            $jzsj = $data['jzsj'];
            //如果包含1970，设置为‘’
            if (strpos($jzsj, '1970') !== false) {
                $jzsj = '';
            }
            $omrInfo = [
                'title' => $title,
                'subtitle' => $subtitle,
                'mzh' => $data['mzh'],
                //'xm' => $isTm ? desensitize($data['xm'], 1, 1) : $data['xm'],
                'xm' => $data['xm'],
                'jzsj' => $jzsj,
                'CJSJ' => $data['CJSJ'],
                'ks' => $data['ks'],
                'xb' => $data['xb'],
                'nl' => $data['nl'],
                'nl1' => $data['nl1'],
                'zs' => $data['zs'],
                'xbs' => $data['xbs'],
                'jws' => $data['jws'],
                'tgjc' => $data['tgjc'],
                'fzjc' => $data['fzjc'],
                'cbzd' => $data['cbzd'],
                'zlyj' => $zlyj,
                'xy' => $xy,
                'SXYS' => $SXYS_NAME,
                'SXYS_CODE' => $data['SXYS'],
                'KS_CODE' => $data['BRKS'],
                'doctor_name' => $doctorName,
                'zyzd' => $data['zyzd'] ?? '',
                'xyzd' => $data['xyzd'] ?? '',
                'qjjl' => $data['qjjl'] ?? '',
                'sz' => $data['sz'] ?? '',
                'yz' => $data['yz'] ?? '',
                'wangz' => $data['wangz'] ?? '',
                'wenz' => $data['wenz'] ?? '',
                'wz' => $data['wz'] ?? '',
                'qiez' => $data['qiez'] ?? '',
                'first_sign_time' => $firstSignTime ?? '',  // 首次签名时间
                'create_time' => $createTime,  // 创建时间
            ];
        }

        return ToolsService::returnData(200, $omrInfo, '');
    }

    /**
     * 获取门诊病历质控结果
     * @param Request $request
     * @return array
     */
    public function getOmrQuality(Request $request)
    {
        $blbh = (string)$request->post('blbh', '');

        if (empty($blbh)) {
            return ToolsService::returnData(4001, '参数错误', $msg ?? '');
        }

        // 查询病历的质控时间
        $qualityTime = OMR_BL01::query()->where('BLBH', $blbh)->value('quality_time');

        $omrQualityService = new ElasticsearchService('omr_quality_2023');
        $must = [
            ['term' => ['BLBH' => $blbh]]
        ];
        $params = $omrQualityService->clearMust()
            ->queryByMustBatch($must)
            ->paginate(1, 1000)
            ->getParams();
        $restful = app('es')->search($params);
        $omrQualityData = $omrQualityService->getDataByEs($restful);
        $returnData = [];
        $score = 100;

        if (!empty($omrQualityData[0])) {
            $qualityList = $omrQualityData[0];
            $ruleIds = array_values(array_unique(array_filter(array_column($qualityList, 'rule_id'))));

            // 批量查询反馈状态，避免循环中逐条查询
            $feedbackMap = [];
            if (!empty($ruleIds)) {
                $feedbackList = MZFK::query()
                    ->where('blbh', $blbh)
                    ->whereIn('rule_id', $ruleIds)
                    ->select(['rule_id', 'status'])
                    ->get()
                    ->keyBy('rule_id')
                    ->toArray();
                $feedbackMap = $feedbackList;
            }

            // 批量查询标准规则与自定义规则，避免 N+1 查询
            $standardRuleIds = [];
            $customRuleIds = [];
            foreach ($ruleIds as $ruleId) {
                $ruleId = (int)$ruleId;
                if ($ruleId < 1000000) {
                    $standardRuleIds[] = $ruleId;
                } else {
                    $customRuleIds[] = $ruleId - 1000000;
                }
            }

            $omrRuleMap = [];
            if (!empty($standardRuleIds)) {
                $omrRuleMap = OmrRule::query()
                    ->whereIn('id', $standardRuleIds)
                    ->get()
                    ->keyBy('id')
                    ->toArray();
            }

            $ruleSettingMap = [];
            if (!empty($customRuleIds)) {
                $ruleSettingMap = RuleSetting::query()
                    ->whereIn('id', $customRuleIds)
                    ->get()
                    ->keyBy('id')
                    ->toArray();
            }

            foreach ($qualityList as $value) {
                $ruleId = (int)($value['rule_id'] ?? 0);
                $feedback = $feedbackMap[$ruleId] ?? null;

                // 添加反馈状态字段
                $value['is_fk'] = $feedback ? 1 : 0;
                $value['fk_status'] = $feedback['status'] ?? '';

                if ($ruleId < 1000000) {
                    $omrRule = $omrRuleMap[$ruleId] ?? null;
                    if ($omrRule) {
                        $score -= $omrRule['score'];
                        $value['score'] = $omrRule['score'];
                        $value['notice'] = $omrRule['notice'];
                        $value['basis'] = json_decode($value['basis'], true);
                        $returnData[$omrRule['category']][] = $value;
                    }
                } else {
                    $rid = $ruleId - 1000000;
                    $ruleSetting = $ruleSettingMap[$rid] ?? [];
                    if ($ruleSetting) {
                        $score -= $ruleSetting['score'];
                        $value['score'] = $ruleSetting['score'];
                        $value['notice'] = $ruleSetting['description'];
                        $value['basis'] = json_decode($value['basis'], true);
                        $returnData[$ruleSetting['case_type']][] = $value;
                    }
                }
            }
        }

        return ToolsService::returnData(200, ['score' => $score, 'data' => $returnData, 'quality_time' => $qualityTime], $msg ?? '');
    }

    /**
     * 应有门诊病历列表
     * @param Request $request
     * @return array
     */
    public function getShouldBeBlList(Request $request)
    {
        $depId = $request->post('dep_id', '');           // 科室ID
        $sfzh = $request->post('sfzh', '');              // 身份证号
        $doctorId = $request->post('doctor_id', '');     // 医生签名
        $startTime = $request->post('start_time', '');   // 开始时间
        $endTime = $request->post('end_time', '');       // 结束时间
        $mzh = $request->post('mzh', '');                // 门诊号
        $status = $request->post('status', '');          // 病历状态
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        $isExport = $request->post('is_export', 0);      // 是否导出

        /* $must = [];
        // 病人科室ID
        if ($depId) {
            $must[] = ['term' => ['BRKS' => $depId]];
        }
        // 身份证号
        if ($sfzh) {
            $must[] = ['term' => ['SFZH' => $sfzh]];
        }
        // 书写医生ID
        if ($doctorId) {
            $must[] = ['term' => ['SXYS' => $doctorId]];
        }
        // 门诊号
        if ($mzh) {
            $must[] = ['match_phrase' => ['mzh' => $mzh]];
        }
        // 就诊时间
        if ($startTime && $endTime) {
            $startTime = date('Y-m-d', strtotime($startTime)).' 00:00:00';
            $endTime = date('Y-m-d', strtotime($endTime)).' 23:59:59';
            $must[] = ['range' => ['jzsj' => ['gte' => $startTime, 'lte' => $endTime]]];
        } elseif ($startTime) {
            $startTime = date('Y-m-d', strtotime($startTime)).' 00:00:00';
            $must[] = ['range' => ['jzsj' => ['gte' => $startTime]]];
        } elseif ($endTime) {
            $endTime = date('Y-m-d', strtotime($endTime)).' 23:59:59';
            $must[] = ['range' => ['jzsj' => ['lte' => $endTime]]];
        }

        $omrQualityUniqueService = new ElasticsearchService('omr_bl01_2023');
        $staffService = new ElasticsearchService('staff_2023');

        $params = $omrQualityUniqueService->clearMust()
            ->queryByMustBatch($must)
            ->paginate($page,$pageSize)
            ->trackTotalHits()
            ->getParams();
        $restful = app('es')->search($params);
        $data = $omrQualityUniqueService->getDataByEs($restful); */
        $staffService = new ElasticsearchService('staff_2023');
        //调整为数据表查询
        $startTotalTime = microtime(true);
        Log::info('[性能日志] getShouldBeBlList 开始执行', ['time' => $startTotalTime]);

        // 查询不适用科室配置
        $excludeDepartments = [];
        $ruleWordMap = RuleWordMap::query()->where('id', 8106)->value('keyword');
        if ($ruleWordMap) {
            if (strpos($ruleWordMap, ',') !== false) {
                $excludeDepartments = explode(',', $ruleWordMap);
            } elseif (strpos($ruleWordMap, '，') !== false) {
                $excludeDepartments = explode('，', $ruleWordMap);
            } else {
                $excludeDepartments = [$ruleWordMap];
            }
            // 去除空格
            $excludeDepartments = array_map('trim', $excludeDepartments);
            $excludeDepartments = array_filter($excludeDepartments);
        }

        // 查询需要排除的诊断名称关键词
        $excludeDiagnosis = [];
        $diagnosisRuleWordMap = RuleWordMap::query()->where('id', 8210)->value('keyword');
        if ($diagnosisRuleWordMap) {
            if (strpos($diagnosisRuleWordMap, ',') !== false) {
                $excludeDiagnosis = explode(',', $diagnosisRuleWordMap);
            } elseif (strpos($diagnosisRuleWordMap, '，') !== false) {
                $excludeDiagnosis = explode('，', $diagnosisRuleWordMap);
            } else {
                $excludeDiagnosis = [$diagnosisRuleWordMap];
            }
            // 去除空格
            $excludeDiagnosis = array_map('trim', $excludeDiagnosis);
            $excludeDiagnosis = array_filter($excludeDiagnosis);
        }

        $excludeDepartmentIds = [];
        if (!empty($excludeDepartments)) {
            $excludeDepartmentIds = OmrDepartment::query()
                ->where(function ($q) use ($excludeDepartments) {
                    foreach ($excludeDepartments as $excludeDep) {
                        $q->orWhere('dep_name', 'like', '%' . $excludeDep . '%');
                    }
                })
                ->pluck('dep_id')
                ->filter()
                ->values()
                ->toArray();
        }

        $applyExcludeDepartmentCondition = function ($builder, $status) use ($excludeDepartments, $excludeDepartmentIds) {
            if (!in_array($status, ['未创建', '不适用'], true)) {
                return;
            }

            if ($status === '不适用') {
                if (empty($excludeDepartments) && empty($excludeDepartmentIds)) {
                    $builder->whereRaw('1 = 0');
                    return;
                }

                if (!empty($excludeDepartmentIds)) {
                    $builder->whereIn('YS_MZ_JZLS.KSDM', $excludeDepartmentIds);
                    return;
                }

                $builder->where(function ($q) use ($excludeDepartments, $excludeDepartmentIds) {
                    foreach ($excludeDepartments as $excludeDep) {
                        $q->orWhere('OMR_BL01.ks', 'like', '%' . $excludeDep . '%');
                    }
                });
                return;
            }

            if (!empty($excludeDepartmentIds)) {
                $builder->whereNotIn('YS_MZ_JZLS.KSDM', $excludeDepartmentIds);
                return;
            }

            foreach ($excludeDepartments as $excludeDep) {
                $builder->where(function ($q) use ($excludeDep) {
                    $q->whereNull('OMR_BL01.ks')
                        ->orWhere('OMR_BL01.ks', '=', '')
                        ->orWhere('OMR_BL01.ks', 'not like', '%' . $excludeDep . '%');
                });
            }
        };

        $applyExcludeDiagnosisCondition = function ($builder) use ($excludeDiagnosis) {
            if (empty($excludeDiagnosis)) {
                return;
            }

            $builder->where(function ($q) use ($excludeDiagnosis) {
                $q->whereNull('YS_MZ_JZLS.ZDMC')
                    ->orWhere(function ($subQ) use ($excludeDiagnosis) {
                        foreach ($excludeDiagnosis as $keyword) {
                            $subQ->where('YS_MZ_JZLS.ZDMC', 'not like', '%' . $keyword . '%');
                        }
                    });
            });
        };

        $time1 = microtime(true);

        // 使用 YS_MZ_JZLS 为主表进行查询
        // 优化：将过滤条件放在 WHERE 子句中，在 GROUP BY 之前过滤，减少需要分组的数据量
        $query = YS_MZ_JZLS::query()
            ->select([
                'YS_MZ_JZLS.JZXH',
                DB::raw('MAX(YS_MZ_JZLS.KSSJ) as jzsj'),
                // 使用 COALESCE 和 NULLIF 处理空字符串的情况
                // NULLIF(value, '') 将空字符串转换为 NULL，然后 COALESCE 选择第一个非 NULL 值
                DB::raw('COALESCE(NULLIF(MAX(OMR_BL01.mzh), \'\'), MAX(MS_BRDA.MZHM)) as mzh'),
                DB::raw('COALESCE(NULLIF(MAX(OMR_BL01.xm), \'\'), MAX(MS_BRDA.BRXM)) as xm'),
                DB::raw('MAX(OMR_BL01.BLBH) as BLBH'),
                DB::raw('MAX(OMR_BL01.BLZT) as BLZT'),
                // 科室信息：BRKS 从 YS_MZ_JZLS.KSDM 获取，ks 从 OMR_BL01.ks 获取
                DB::raw('MAX(YS_MZ_JZLS.KSDM) as BRKS'),
                DB::raw('MAX(OMR_BL01.ks) as ks'),
                DB::raw('COALESCE(NULLIF(MAX(OMR_BL01.SFZH), \'\'), MAX(MS_BRDA.SFZH)) as SFZH'),
                DB::raw('MAX(OMR_BL01.xb) as xb'),
                DB::raw('MAX(MS_BRDA.BRXB) as xb1'),
                DB::raw('MAX(OMR_BL01.nl) as nl'),
                DB::raw('MAX(YS_MZ_JZLS.ZDMC) as cbzd'),
                // 医生信息从 YS_MZ_JZLS.YSDM 获取
                DB::raw('MAX(YS_MZ_JZLS.YSDM) as SXYS'),
                DB::raw('MAX(YS_MZ_JZLS.BRBH) as BRBH'),
                DB::raw('MAX(YS_MZ_JZLS.JZXH) as JZXH')
            ])
            ->leftJoin('MS_BRDA', 'MS_BRDA.BRID', '=', 'YS_MZ_JZLS.BRBH')
            ->leftJoin('OMR_BL01', 'OMR_BL01.JZXH', '=', 'YS_MZ_JZLS.JZXH');

        // 时间条件使用 KSSJ 字段 - 在 WHERE 子句中先过滤，减少 JOIN 的数据量
        if ($startTime && $endTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $query->where('YS_MZ_JZLS.KSSJ', '>=', $startTime)
                ->where('YS_MZ_JZLS.KSSJ', '<=', $endTime);
        } elseif ($startTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $query->where('YS_MZ_JZLS.KSSJ', '>=', $startTime);
        } elseif ($endTime) {
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $query->where('YS_MZ_JZLS.KSSJ', '<=', $endTime);
        }

        // 优化：将过滤条件放在 WHERE 子句中，在 GROUP BY 之前过滤
        // 这样可以利用索引，减少需要分组的数据量
        if ($depId) {
            // 修改：科室查询改为从 YS_MZ_JZLS 表的 KSDM 字段获取
            $query->where('YS_MZ_JZLS.KSDM', '=', $depId);
        }
        if ($sfzh) {
            $query->where('OMR_BL01.SFZH', 'like', '%' . $sfzh . '%');
        }
        if ($doctorId) {
            // 修改：医生查询改为从 YS_MZ_JZLS 表的 YSDM 字段获取
            $query->where('YS_MZ_JZLS.YSDM', '=', $doctorId);
        }
        if ($mzh) {
            // 对于 mzh，需要同时检查两个表，使用 OR 条件
            $query->where(function ($q) use ($mzh) {
                $q->where('OMR_BL01.mzh', '=', $mzh)
                    ->orWhere('MS_BRDA.MZHM', '=', $mzh);
            });
        }

        $applyExcludeDiagnosisCondition($query);

        // 状态筛选
        if (!empty($status)) {
            switch ($status) {
                case '未创建':
                    // BLBH 为空，且不在排除科室列表中
                    $query->whereNull('OMR_BL01.BLBH');
                    $applyExcludeDepartmentCondition($query, $status);
                    break;
                case '不适用':
                    // BLBH 为空，且在排除科室列表中
                    $query->whereNull('OMR_BL01.BLBH');
                    $applyExcludeDepartmentCondition($query, $status);
                    break;
                case '书写中':
                    // BLBH 不为空，且 BLZT = 0
                    $query->whereNotNull('OMR_BL01.BLBH')
                        ->where('OMR_BL01.BLZT', '=', '0');
                    break;
                case '完成':
                    // BLBH 不为空，且 BLZT = 1
                    $query->whereNotNull('OMR_BL01.BLBH')
                        ->where('OMR_BL01.BLZT', '=', '1');
                    break;
                case '删除':
                    // BLBH 不为空，且 BLZT = 9
                    $query->whereNotNull('OMR_BL01.BLBH')
                        ->where('OMR_BL01.BLZT', '=', '9');
                    break;
            }
        }

        // GROUP BY 放在最后，此时数据已经通过 WHERE 条件过滤过了
        $query->groupBy('YS_MZ_JZLS.JZXH')
            ->orderBy(DB::raw('MAX(YS_MZ_JZLS.KSSJ)'), 'desc');  // 按就诊时间倒序排序

        $time2 = microtime(true);
        Log::info('[性能日志] 构建查询条件耗时', ['耗时(秒)' => round($time2 - $time1, 4)]);

        // 查看执行计划，检查索引使用情况
        $explainQuery = clone $query;
        $explainSql = $explainQuery->toSql();
        $explainBindings = $explainQuery->getBindings();
        $explainResult = DB::select("EXPLAIN " . $explainSql, $explainBindings);
        Log::info('[性能日志] 查询执行计划', [
            'SQL' => $explainSql,
            '执行计划' => json_decode(json_encode($explainResult), true)
        ]);

        // 优化：分离COUNT和SELECT查询，先查询数据，总数可以延迟计算
        // 注意：分页已经在查询时应用（offset + limit），不会先查询所有数据再分页
        DB::enableQueryLog();
        $time3 = microtime(true);

        // 先查询分页数据（offset 和 limit 在数据库层面应用，只返回需要的记录）
        $time3_1 = microtime(true);
        // 如果是导出，不进行分页限制
        if ($isExport == 1) {
            $items = (clone $query)->get();
        } else {
            $offset = ($page - 1) * $pageSize;
            $items = (clone $query)->offset($offset)->limit($pageSize)->get();
        }
        $time3_2 = microtime(true);

        // 再查询总数（对于 GROUP BY 查询，优化COUNT查询性能）
        // 优化策略：根据是否有过滤条件，选择最优的查询方式
        $time3_3 = microtime(true);

        // 判断是否有涉及关联表的过滤条件
        // 科室和医生条件已改为从 YS_MZ_JZLS 获取，只有身份证号属于 OMR_BL01 过滤条件
        $hasOmrBl01Filter = $sfzh;
        $hasMzhFilter = !empty($mzh);

        if (!$hasOmrBl01Filter && !$hasMzhFilter) {
            // 情况1：只有时间条件和/或科室条件和/或医生条件，直接在主表上COUNT，最快
            $countQuery = YS_MZ_JZLS::query();
            if ($startTime && $endTime) {
                $countQuery->where('YS_MZ_JZLS.KSSJ', '>=', $startTime)
                    ->where('YS_MZ_JZLS.KSSJ', '<=', $endTime);
            } elseif ($startTime) {
                $countQuery->where('YS_MZ_JZLS.KSSJ', '>=', $startTime);
            } elseif ($endTime) {
                $countQuery->where('YS_MZ_JZLS.KSSJ', '<=', $endTime);
            }
            // 科室条件
            if ($depId) {
                $countQuery->where('YS_MZ_JZLS.KSDM', '=', $depId);
            }
            // 医生条件
            if ($doctorId) {
                $countQuery->where('YS_MZ_JZLS.YSDM', '=', $doctorId);
            }

            $applyExcludeDiagnosisCondition($countQuery);

            // 状态筛选（需要关联 OMR_BL01 表）
            if (!empty($status)) {
                $countQuery->leftJoin('OMR_BL01', 'OMR_BL01.JZXH', '=', 'YS_MZ_JZLS.JZXH');
                switch ($status) {
                    case '未创建':
                        $countQuery->whereNull('OMR_BL01.BLBH');
                        $applyExcludeDepartmentCondition($countQuery, $status);
                        break;
                    case '不适用':
                        $countQuery->whereNull('OMR_BL01.BLBH');
                        $applyExcludeDepartmentCondition($countQuery, $status);
                        break;
                    case '书写中':
                        $countQuery->whereNotNull('OMR_BL01.BLBH')
                            ->where('OMR_BL01.BLZT', '=', '0');
                        break;
                    case '完成':
                        $countQuery->whereNotNull('OMR_BL01.BLBH')
                            ->where('OMR_BL01.BLZT', '=', '1');
                        break;
                    case '删除':
                        $countQuery->whereNotNull('OMR_BL01.BLBH')
                            ->where('OMR_BL01.BLZT', '=', '9');
                        break;
                }
            }

            $total = $countQuery->distinct()->count('YS_MZ_JZLS.JZXH');
        } else {
            // 情况2：有过滤条件，使用EXISTS子查询代替JOIN，性能更好
            $countQuery = YS_MZ_JZLS::query();

            // 时间条件（主表条件，必须）
            if ($startTime && $endTime) {
                $countQuery->where('YS_MZ_JZLS.KSSJ', '>=', $startTime)
                    ->where('YS_MZ_JZLS.KSSJ', '<=', $endTime);
            } elseif ($startTime) {
                $countQuery->where('YS_MZ_JZLS.KSSJ', '>=', $startTime);
            } elseif ($endTime) {
                $countQuery->where('YS_MZ_JZLS.KSSJ', '<=', $endTime);
            }

            // 科室条件（主表条件）
            if ($depId) {
                $countQuery->where('YS_MZ_JZLS.KSDM', '=', $depId);
            }

            // 医生条件（主表条件）
            if ($doctorId) {
                $countQuery->where('YS_MZ_JZLS.YSDM', '=', $doctorId);
            }

            $applyExcludeDiagnosisCondition($countQuery);

            // 如果过滤条件涉及OMR_BL01表，使用EXISTS子查询
            // EXISTS比LEFT JOIN更快，因为一旦找到匹配就停止搜索
            if ($hasOmrBl01Filter) {
                $countQuery->whereExists(function ($q) use ($sfzh) {
                    $q->select(DB::raw(1))
                        ->from('OMR_BL01')
                        ->whereColumn('OMR_BL01.JZXH', 'YS_MZ_JZLS.JZXH');

                    // 只有身份证号条件在 OMR_BL01 表中
                    if ($sfzh) {
                        $q->where('OMR_BL01.SFZH', 'like', '%' . $sfzh . '%');
                    }
                });
            }

            // 状态筛选
            if (!empty($status)) {
                switch ($status) {
                    case '未创建':
                    case '不适用':
                        $countQuery->leftJoin('OMR_BL01', 'OMR_BL01.JZXH', '=', 'YS_MZ_JZLS.JZXH')
                            ->whereNull('OMR_BL01.BLBH');
                        $applyExcludeDepartmentCondition($countQuery, $status);
                        break;
                    case '书写中':
                        $countQuery->whereExists(function ($q) {
                            $q->select(DB::raw(1))
                                ->from('OMR_BL01')
                                ->whereColumn('OMR_BL01.JZXH', 'YS_MZ_JZLS.JZXH')
                                ->whereNotNull('OMR_BL01.BLBH')
                                ->where('OMR_BL01.BLZT', '=', '0');
                        });
                        break;
                    case '完成':
                        $countQuery->whereExists(function ($q) {
                            $q->select(DB::raw(1))
                                ->from('OMR_BL01')
                                ->whereColumn('OMR_BL01.JZXH', 'YS_MZ_JZLS.JZXH')
                                ->whereNotNull('OMR_BL01.BLBH')
                                ->where('OMR_BL01.BLZT', '=', '1');
                        });
                        break;
                    case '删除':
                        $countQuery->whereExists(function ($q) {
                            $q->select(DB::raw(1))
                                ->from('OMR_BL01')
                                ->whereColumn('OMR_BL01.JZXH', 'YS_MZ_JZLS.JZXH')
                                ->whereNotNull('OMR_BL01.BLBH')
                                ->where('OMR_BL01.BLZT', '=', '9');
                        });
                        break;
                }
            }

            // 处理mzh条件（可能涉及两个表）
            if ($hasMzhFilter) {
                $countQuery->where(function ($q) use ($mzh) {
                    // 检查OMR_BL01表
                    $q->whereExists(function ($subQ) use ($mzh) {
                        $subQ->select(DB::raw(1))
                            ->from('OMR_BL01')
                            ->whereColumn('OMR_BL01.JZXH', 'YS_MZ_JZLS.JZXH')
                            ->where('OMR_BL01.mzh', '=', $mzh);
                    })
                        // 或者检查MS_BRDA表
                        ->orWhereExists(function ($subQ) use ($mzh) {
                            $subQ->select(DB::raw(1))
                                ->from('MS_BRDA')
                                ->whereColumn('MS_BRDA.BRID', 'YS_MZ_JZLS.BRBH')
                                ->where('MS_BRDA.MZHM', '=', $mzh);
                        });
                });
            }

            // 使用 COUNT(DISTINCT JZXH) 统计唯一就诊序号
            $total = $countQuery->distinct()->count('YS_MZ_JZLS.JZXH');
        }

        $time3_4 = microtime(true);

        $time4 = microtime(true);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // 记录SQL查询详情
        $sqlDetails = [];
        foreach ($queries as $query) {
            $sqlDetails[] = [
                'sql' => $query['query'],
                'bindings' => $query['bindings'],
                'time' => $query['time'] . 'ms'
            ];
        }

        Log::info('[性能日志] 数据库查询耗时', [
            'SELECT查询耗时(秒)' => round($time3_2 - $time3_1, 4),
            'COUNT查询耗时(秒)' => round($time3_4 - $time3_3, 4),
            '总耗时(秒)' => round($time4 - $time3, 4),
            '查询总数' => $total,
            '当前页数据量' => count($items),
            '页码' => $page,
            '每页数量' => $pageSize,
            '执行的SQL数量' => count($queries),
            'SQL详情' => $sqlDetails
        ]);

        // 创建分页对象（手动构建，避免再次查询）
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $pageSize,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
        $data = $items->toArray();  // 转换为数组，因为后面使用array_column需要数组

        $list = [];
        if ($data) {
            $time5 = microtime(true);
            // 获取科室
            $department = OmrDepartment::query()->pluck('dep_name', 'dep_id')->toArray();
            $time6 = microtime(true);
            Log::info('[性能日志] 查询科室信息耗时', ['耗时(秒)' => round($time6 - $time5, 4), '科室数量' => count($department)]);

            // 批量查询医生信息，避免N+1查询问题
            $time7 = microtime(true);
            $doctorIds = array_filter(array_unique(array_column($data, 'SXYS')));
            $doctorMap = [];
            if (!empty($doctorIds)) {
                $time8 = microtime(true);
                // 改为数据库查询
                $staffData = Staff::query()
                    ->whereIn('code', array_values($doctorIds))
                    ->select(['code', 'name'])
                    ->get()
                    ->toArray();
                $time9 = microtime(true);

                Log::info('[性能日志] 数据库查询医生信息耗时', [
                    '查询耗时(秒)' => round($time9 - $time8, 4),
                    '总耗时(秒)' => round($time9 - $time7, 4),
                    '医生ID数量' => count($doctorIds),
                    '返回医生数量' => count($staffData)
                ]);

                if (!empty($staffData)) {
                    foreach ($staffData as $staff) {
                        if (isset($staff['code'])) {
                            $doctorMap[$staff['code']] = $staff['name'] ?? '';
                        }
                    }
                }
            } else {
                Log::info('[性能日志] 无医生ID需要查询');
            }

            $time12 = microtime(true);
            foreach ($data as $value) {
                if ($value['SFZH']) {
                    // 身份证号脱敏
                    //$value['SFZH'] = desensitize($value['SFZH'], 6, 8);
                    $value['SFZH'] = $value['SFZH'];
                }

                //如果xb为空且xb1不为空给xb赋值
                if (empty($value['xb']) && !empty($value['xb1'])) {
                    //xb1为数字，1男2女 0未知
                    if ($value['xb1'] == 1) {
                        $value['xb'] = '男';
                    } elseif ($value['xb1'] == 2) {
                        $value['xb'] = '女';
                    } else {
                        $value['xb'] = '未知';
                    }
                }

                // 病人科室
                if ($value["ks"]) {
                    $value['dep_name'] = $value["ks"];
                } else {
                    $value['dep_name'] = $department[$value['BRKS']] ?? $value['BRKS'];
                }

                // 医生签名 - 从批量查询的映射中获取
                if ($value['SXYS']) {
                    $value['SXYS_NAME'] = $doctorMap[$value['SXYS']] ?? '';
                }

                // 病历状态
                if (empty($value['BLBH'])) {
                    // 判断是否为不适用科室
                    $isExcludeDepartment = false;
                    if (!empty($excludeDepartments) && !empty($value['dep_name'])) {
                        foreach ($excludeDepartments as $excludeDep) {
                            if (strpos($value['dep_name'], $excludeDep) !== false) {
                                $isExcludeDepartment = true;
                                break;
                            }
                        }
                    }

                    $value['status'] = $isExcludeDepartment ? '不适用' : '未创建';
                } else {
                    switch ($value['BLZT']) {
                        case '0':
                            $value['status'] = '书写中';
                            break;
                        case '1':
                            $value['status'] = '完成';
                            break;
                        case '9':
                            $value['status'] = '删除';
                            break;
                        default:
                            $value['status'] = '未知';
                            break;
                    }
                }

                $list[] = $value;
            }
            $time13 = microtime(true);
            Log::info('[性能日志] 数据处理循环耗时', ['耗时(秒)' => round($time13 - $time12, 4), '处理数据量' => count($list)]);
        }

        $endTotalTime = microtime(true);
        Log::info('[性能日志] getShouldBeBlList 总耗时', ['总耗时(秒)' => round($endTotalTime - $startTotalTime, 4)]);

        // 如果是导出，直接返回导出文件
        if ($isExport == 1) {
            return $this->exportShouldBeBlList($list);
        }

        $returnData = [
            'list' => $list,
            'count' => $total
        ];

        return ToolsService::returnData(200, $returnData, '');
    }

    /**
     * 导出就诊记录列表
     * @param array $data
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    private function exportShouldBeBlList($data)
    {
        $title = ['记录时间', '门诊号码', '患者姓名', '就诊科室', '性别', '年龄', '初步诊断', '身份证号', '签名医师', '医生所属科室', '病历状态'];
        $exportData = [];
        $doctorDepartmentMap = $this->buildDoctorDepartmentNameMap($data);

        foreach ($data as $item) {
            $sxys = $item['SXYS'] ?? '';
            $departmentName = $doctorDepartmentMap[$sxys] ?? '';
            $exportData[] = [
                $item['jzsj'] ?? '',
                (string)($item['mzh'] ?? ''),
                $item['xm'] ?? '',
                $item['dep_name'] ?? '',
                $item['xb'] ?? '',
                $item['nl'] ?? '',
                $item['cbzd'] ?? '',
                (string)($item['SFZH'] ?? ''),
                $item['SXYS_NAME'] ?? '',
                $departmentName ?? '',
                $item['status'] ?? '',
            ];
        }

        $fileName = '就诊记录列表_' . date('YmdHis') . '.xlsx';
        return Excel::download(new ExportData($title, $exportData, ['B', 'H']), $fileName);
    }

    /**
     * 应有门诊病历导出
     * @param Request $request
     * @return string|null
     */
    public function getShouldBeBlExport(Request $request)
    {
        $depId = $request->post('dep_id', '');           // 科室ID
        $sfzh = $request->post('sfzh', '');              // 身份证号
        $doctorId = $request->post('doctor_id', '');     // 医生签名
        $startTime = $request->post('start_time', '');   // 开始时间
        $endTime = $request->post('end_time', '');       // 结束时间
        $mzh = $request->post('mzh', '');                // 门诊号

        $must = [];
        // 病人科室ID
        if ($depId) {
            $must[] = ['term' => ['BRKS' => $depId]];
        }
        // 身份证号
        if ($sfzh) {
            $must[] = ['term' => ['SFZH' => $sfzh]];
        }
        // 书写医生ID
        if ($doctorId) {
            $must[] = ['term' => ['SXYS' => $doctorId]];
        }
        // 门诊号
        if ($mzh) {
            $must[] = ['match_phrase' => ['mzh' => $mzh]];
        }
        // 就诊时间
        if ($startTime && $endTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $must[] = ['range' => ['jzsj' => ['gte' => $startTime, 'lte' => $endTime]]];
        } elseif ($startTime) {
            $startTime = date('Y-m-d', strtotime($startTime)) . ' 00:00:00';
            $must[] = ['range' => ['jzsj' => ['gte' => $startTime]]];
        } elseif ($endTime) {
            $endTime = date('Y-m-d', strtotime($endTime)) . ' 23:59:59';
            $must[] = ['range' => ['jzsj' => ['lte' => $endTime]]];
        }

        $omrQualityUniqueService = new ElasticsearchService('omr_quality_unique_2023');
        $staffService = new ElasticsearchService('staff_2023');

        $params = $omrQualityUniqueService->clearMust()
            ->queryByMustBatch($must)
            ->source(['BLBH', 'jzsj', 'mzh', 'xm', 'BRKS', 'xb', 'nl', 'cbzd', 'SFZH', 'SXYS'])
            ->paginate(1, 10000000)
            ->trackTotalHits()
            ->getParams();
        $restful = app('es')->search($params);
        $data = $omrQualityUniqueService->getDataByEs($restful);

        $list = [];
        $list[] = ['病历病号', '就诊时间', '门诊号', '姓名', '科室', '性别', '年龄', '初步诊断', '身份证号', '医生'];
        if ($data[0]) {
            // 获取科室
            $department = OmrDepartment::query()->pluck('dep_name', 'dep_id')->toArray();
            foreach ($data[0] as $value) {
                if ($value['SFZH']) {
                    // 身份证号脱敏
                    //$value['SFZH'] = desensitize($value['SFZH'], 6, 8);
                    $value['SFZH'] = $value['SFZH'];
                }

                // 病人科室
                $value['BRKS'] = $department[$value['BRKS']] ?? $value['BRKS'];

                // 医生签名
                if ($value['SXYS']) {
                    $must = [
                        ['term' => ['code' => $value['SXYS']]]
                    ];
                    $params = $staffService->clearMust()
                        ->queryByMustBatch($must)
                        ->getParams();
                    $restful = app('es')->search($params);
                    $staffData = $staffService->getDataByEs($restful);
                    $value['SXYS'] = !empty($staffData[0][0]['name']) ? $staffData[0][0]['name'] : '';
                }

                $value['mzh'] = $this->formatCsvTextValue($value['mzh'] ?? '');
                $value['SFZH'] = $this->formatCsvTextValue($value['SFZH'] ?? '');
                $list[] = $value;
            }
        }

        $csv = new CsvService();
        $csv->filename = $csv->charset('应有门诊病历导出', 'UTF-8');

        return $csv->export($list, false);
    }

    /**
     * 获取门诊科室
     * @return array
     */
    public function getOmrDepartmentList()
    {
        $depData = OmrDepartment::getOmrDepartmentData();

        $data = [];
        foreach ($depData as $key => $value) {
            $data[] = ['id' => $key, 'name' => $value];
        }

        return ToolsService::returnData(200, $data, '');
    }
}
