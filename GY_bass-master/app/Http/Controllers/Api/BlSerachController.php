<?php

namespace App\Http\Controllers\Api;

use App\Model\User;
use App\Model\Staff;
use App\Model\Department;
use App\Model\RuleWordMap;
use App\Services\CsvService;
use App\Services\YzbService;
use Illuminate\Http\Request;
use App\Services\UserService;
use App\Services\ToolsService;
use App\Http\Controllers\Controller;
use App\Model\ZY_BRRY;
use Illuminate\Support\Facades\Cache;
use App\Services\ElasticsearchService;
use App\Services\MedicalHistoryService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class BlSerachController extends Controller
{
    public static $depName = '';
    protected static $userData = [];
    protected static $userCode = '';

    public function __construct(Request $request)
    {
        //Log::info('request', $request->all());
        $userCode = $request->post('user_code');

        if (!$userCode) {
            $userCode = $request->post('code');
        }
        if (!empty($userCode)) {
            $staffInfo = Staff::query()->where('YGBH', '=', $userCode)->first();
            $userInfo = ['id' => '', 'name' => '', 'dep_id' => [$staffInfo->ksdm]];
        } else {
            $token = $request->header('token');
            $user = Session::get($token);
            $userInfo = ['id' => '', 'name' => '', 'dep_id' => ''];
            if (!empty($user['id'])) {
                $depId = User::query()->where('id', '=', $user['id'])->value('dep_id');
                $userInfo['dep_id'] = json_decode($depId, true);
            }
        }

        $userInfo['dep_name'] = '';
        if (!empty($userInfo['dep_id'])) {
            $userInfo['dep_name'] = Department::query()->whereIn('dep_id', $userInfo['dep_id'])->value('dep_name');
        }
        self::$userCode = $userCode;
        self::$userData = $userInfo;
    }

    /**
     * 病历搜索条件获取
     * @return array
     */
    public function getSerachWhere(Request $request)
    {
        $mhService = new MedicalHistoryService();
        $data = $mhService->getBlSerachSelectData();
        //var_dump(self::$userData);
        $data['dep_name'] = "";
        if (empty(self::$userCode)) {
            /* //$userDep = UserService::getCurrentUserDep($request);
            $userDep = Department::query()->get()->pluck('dep_id');
            $userDep = $userDep->toArray();
            //只要depid的数组，比如[432,435]



            if (is_array($userDep)) {
                $data['dep_name'] = $userDep;
            } */
            //$data['dep_name'] = Department::query()->pluck('dep_name')->unique()->values()->toArray();
            $data['dep_name'] = '';
        } else {
            $data['dep_name'] = [self::$userData['dep_id']];
        }


        return ToolsService::returnData(200, $data, $msg ?? '');
    }

    /**
     * 病历搜索
     * @param Request $request
     * @return array
     */
    public function blSerach(Request $request)
    {
        $serachData = $request->all();
        $serachData['field'] = $request->post('field', []);
        if (!empty($serachData['field'])) {
            foreach ($serachData['field'] as $k => $v) {
                if (empty($v['key'])) {
                    unset($serachData['field'][$k]);
                }
            }
        }
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        $page = checkPageStart($page, $pageSize);

        $blSerachService = new ElasticsearchService('bl_serach_2023');
        $mhService = new MedicalHistoryService();

        // 数据查询
        $returnData = $mhService->blSerach($serachData, $page, $pageSize);
        $restful = $returnData['restful'];
        $serachField = $returnData['serach_field'];

        $ssjlField = $returnData['ssjlField'];
        $qtzdField = $returnData['qtzdField'];
        $qtssField = $returnData['qtssField'];
        $bcjlSxjlField = $returnData['bcjlSxjlField'];

        // 手术记录
        $bllb303 = $returnData['bllb303'];
        // 其它诊断
        $qtzd = $returnData['qtzd'];
        // 其它搜索
        $qtss = $returnData['qtss'];
        // 病程类-输血记录
        $bcjlSxjl = $returnData['bcjl_sxjl'];
        // 体温搜索-病程记录
        $tiwen = $returnData['tiwen'];
        $tiwenKey = $tiwen['tiwen_key'];
        $tiwenList = $tiwen['tiwen_list'];
        // 病程类-整体
        $bcjlQw = $returnData['bcjl_qw'];
        // 手术类-整体
        $ssZt = $returnData['ss_zt'];

        $bllb = $returnData['bllb'];

        // 【优化1】合并重复查询，只执行一次 ES 查询
        $blDetail = $blSerachService->getDataByEsToArray($restful);
        $total = $blDetail[1] ?? 0;

        // 使用缓存获取科室映射
        $depMap = Cache::remember('department_map', 3600, function () {
            return Department::query()->pluck('dep_name', 'dep_id');
        });

        // 构建列表数据
        $list = [];
        if (!empty($blDetail[0])) {
            foreach ($blDetail[0] as $value) {
                $zyh = strip_tags($value['ZYH']);
                //获取brry的brks
                $brry = ZY_BRRY::query()->where('ZYH', $zyh)->first();
                $brks = $brry['BRKS'] ?? '';
                $depName = Department::query()->where('dep_id', $brks)->value('dep_name') ?? '';
                $list[] = [
                    'AAA28' => strip_tags($value['AAA28']),
                    'ZYH' => strip_tags($value['ZYH']),
                    'AAB01' => $value['AAB01'],
                    'AAC01' => $value['AAC01'],
                    'AAA04' => $value['AAA04'],
                    'AAB02C' => $value['AAB02C'] ?? '',
                    'AAD01C' => $value['AAD01C'] ?? '',
                    'AAC02C' => $brks,
                    'AAC11N' => $depName
                ];
            }
        }
        // 解析详情数据
        $blDetail = $blSerachService->getDataByEsToArray($restful);
        $detailList = [];
        if (!empty($blDetail[0])) {
            $zyhList = array_column($blDetail[0], 'ZYH');

            // 【优化3】批量查询所有关联数据（一次性获取，避免 N+1 问题）
            $yzbBatch = $mhService->yzbSearchBatch($serachData['field'] ?? [], $zyhList);
            $fymxBatch = $mhService->feeSearchBatch($serachData['field'] ?? [], $zyhList);
            $sssqBatch = $mhService->sssqSearchBatch($serachData['field'] ?? [], $zyhList);
            $jyBatch = $mhService->jySearchBatch($serachData['field'] ?? [], $zyhList);
            $ymBatch = $mhService->ymSearchBatch($serachData['field'] ?? [], $zyhList);
            $jcBatch = $mhService->jcSearchBatch($serachData['field'] ?? [], $zyhList);

            // 【优化4】使用 Multi-Search 批量查询 ES 数据
            $bllb303Batch = [];
            $qtzdBatch = [];
            $qtssBatch = [];
            $bcjlSxjlBatch = [];
            $tiwenBatch = [];
            $bcjlQwBatch = [];
            $ssZtBatch = [];

            // 构建 msearch 请求
            $msearchBody = [];
            $msearchMap = []; // 用于映射结果到对应的 ZYH 和类型

            foreach ($blDetail[0] as $value) {
                $zyh = $value['ZYH'];

                // 手术记录 - bllb303
                if (!empty($bllb303)) {
                    $bllb303Must = $bllb303['must'] ?? [];
                    $bllb303Must[] = ['term' => ['ZYH' => $zyh]];
                    $bllb303MustNot = $bllb303['must_not'] ?? [];
                    $bllb303Should = $bllb303['should'] ?? [];

                    $query = ['bool' => ['must' => $bllb303Must]];
                    if (!empty($bllb303MustNot)) {
                        $query['bool']['must_not'] = $bllb303MustNot;
                    }
                    if (!empty($bllb303Should)) {
                        $query['bool']['should'] = $bllb303Should;
                        $query['bool']['minimum_should_match'] = 1;
                    }

                    $msearchBody[] = ['index' => 'bllb303_2023'];
                    $msearchBody[] = [
                        'query' => $query,
                        '_source' => $ssjlField,
                        'highlight' => ['fields' => ['*' => new \stdClass()]],
                        'size' => 100
                    ];
                    $msearchMap[] = ['zyh' => $zyh, 'type' => 'bllb303'];
                }

                // 其它诊断 - qtzd
                if (!empty($qtzd)) {
                    $odMust = $qtzd['must'] ?? [];
                    $odMust[] = ['term' => ['ZYH' => $zyh]];
                    $odMustNot = $qtzd['must_not'] ?? [];
                    $odShould = $qtzd['should'] ?? [];

                    $query = ['bool' => ['must' => $odMust]];
                    if (!empty($odMustNot)) {
                        $query['bool']['must_not'] = $odMustNot;
                    }
                    if (!empty($odShould)) {
                        $query['bool']['should'] = $odShould;
                        $query['bool']['minimum_should_match'] = 1;
                    }

                    $msearchBody[] = ['index' => 'other_diagnosis_2023'];
                    $msearchBody[] = [
                        'query' => $query,
                        '_source' => $qtzdField,
                        'highlight' => ['fields' => ['*' => new \stdClass()]],
                        'size' => 1000
                    ];
                    $msearchMap[] = ['zyh' => $zyh, 'type' => 'qtzd'];
                }

                // 其它手术 - qtss
                if (!empty($qtss)) {
                    $soMust = $qtss['must'] ?? [];
                    $soMust[] = ['term' => ['ZYH' => $zyh]];
                    $soMustNot = $qtss['must_not'] ?? [];
                    $soShould = $qtss['should'] ?? [];

                    $query = ['bool' => ['must' => $soMust]];
                    if (!empty($soMustNot)) {
                        $query['bool']['must_not'] = $soMustNot;
                    }
                    if (!empty($soShould)) {
                        $query['bool']['should'] = $soShould;
                        $query['bool']['minimum_should_match'] = 1;
                    }

                    $msearchBody[] = ['index' => 'secondary_operation_2023'];
                    $msearchBody[] = [
                        'query' => $query,
                        '_source' => $qtssField,
                        'highlight' => ['fields' => ['*' => new \stdClass()]],
                        'size' => 1000
                    ];
                    $msearchMap[] = ['zyh' => $zyh, 'type' => 'qtss'];
                }

                // 病程类-输血记录
                if (!empty($bcjlSxjl)) {
                    $bcjlSxjlFieldWithBlmc = array_merge($bcjlSxjlField, ['BLMC']);
                    $must = $bcjlSxjl['must'] ?? [];
                    $must[] = ['term' => ['ZYH' => $zyh]];
                    $mustNot = $bcjlSxjl['must_not'] ?? [];
                    $should = $bcjlSxjl['should'] ?? [];

                    $query = ['bool' => ['must' => $must]];
                    if (!empty($mustNot)) {
                        $query['bool']['must_not'] = $mustNot;
                    }
                    if (!empty($should)) {
                        $query['bool']['should'] = $should;
                        $query['bool']['minimum_should_match'] = 1;
                    }

                    $msearchBody[] = ['index' => 'bllb294_45_2023'];
                    $msearchBody[] = [
                        'query' => $query,
                        '_source' => $bcjlSxjlFieldWithBlmc,
                        'highlight' => ['fields' => ['*' => new \stdClass()]],
                        'size' => 1000
                    ];
                    $msearchMap[] = ['zyh' => $zyh, 'type' => 'bcjl_sxjl'];
                }

                // 体温搜索 - 病程记录
                if (!empty($tiwenKey) && !empty($tiwenList)) {
                    foreach ($tiwenKey as $twValue) {
                        if (in_array($twValue, ['bllb18_HJNR', 'bllb294_HJNR'])) {
                            $twArr = explode("_", $twValue);
                            $bllb = str_replace('bllb', '', $twArr[0]);

                            $must = [
                                ['term' => ['JZHM' => $zyh]],
                                ['term' => ['BLLB' => $bllb]]
                            ];
                            $mustNot = [
                                ['term' => ['BLZT' => 9]]
                            ];
                            $should = [];
                            foreach ($tiwenList as $twVal) {
                                $should[] = ['match_phrase' => ['HJNR' => '体温:' . $twVal . '℃']];
                                $should[] = ['match_phrase' => ['HJNR' => '体温：' . $twVal . '℃']];
                                $should[] = ['match_phrase' => ['HJNR' => 'T:' . $twVal . '℃']];
                                $should[] = ['match_phrase' => ['HJNR' => 'T：' . $twVal . '℃']];
                            }

                            $msearchBody[] = ['index' => 'bl01_202303'];
                            $msearchBody[] = [
                                'query' => ['bool' => ['must' => $must, 'must_not' => $mustNot, 'should' => $should, 'minimum_should_match' => 1]],
                                'highlight' => ['fields' => ['*' => new \stdClass()]],
                                'size' => 1000
                            ];
                            $msearchMap[] = ['zyh' => $zyh, 'type' => 'tiwen_' . $twArr[0]];
                        }
                    }
                }

                // 病程类 - 整体
                if (!empty($bcjlQw)) {
                    $must = $bcjlQw['must'] ?? [];
                    $must[] = ['term' => ['JZHM' => $zyh]];
                    if ($bllb == 'bllb306') {
                        $must[] = ['term' => ['MBLB' => 306]];
                    } else if ($bllb == 'bllb294') {
                        $must[] = ['term' => ['BLLB' => 294]];
                    } else if ($bllb == 'bllb1') {
                        $must[] = ['term' => ['BLLB' => 1]];
                    } else if ($bllb == 'bllb292') {
                        $must[] = ['term' => ['BLLB' => 292]];
                    } else if ($bllb == 'bllb288') {
                        $must[] = ['term' => ['BLLB' => 288]];
                    } else if ($bllb == 'bllb18') {
                        $must[] = ['term' => ['BLLB' => 18]];
                    } else if ($bllb == 'bllb4344') {
                        $must[] = ['term' => ['BLLB' => 43]];
                    } else if ($bllb == 'bllb8') {
                        $must[] = ['term' => ['BLLB' => 329]];
                    }

                    $mustNot = $bcjlQw['must_not'] ?? [];
                    $should = $bcjlQw['should'] ?? [];

                    $query = ['bool' => ['must' => $must]];
                    if (!empty($mustNot)) {
                        $query['bool']['must_not'] = $mustNot;
                    }
                    if (!empty($should)) {
                        $query['bool']['should'] = $should;
                        $query['bool']['minimum_should_match'] = 1;
                    }

                    $msearchBody[] = ['index' => 'bl01_202303'];
                    $msearchBody[] = [
                        'query' => $query,
                        '_source' => ['BLMC', 'HJNR'],
                        'highlight' => ['fields' => ['*' => new \stdClass()]],
                        'size' => 1000
                    ];
                    $msearchMap[] = ['zyh' => $zyh, 'type' => 'bcjl_qw'];
                }

                // 手术类 - 整体
                if (!empty($ssZt)) {
                    $must = $ssZt['must'] ?? [];
                    $must[] = ['term' => ['JZHM' => $zyh]];
                    $must[] = ['term' => ['BLLB' => 303]];
                    $mustNot = $ssZt['must_not'] ?? [];
                    $should = $ssZt['should'] ?? [];

                    $query = ['bool' => ['must' => $must]];
                    if (!empty($mustNot)) {
                        $query['bool']['must_not'] = $mustNot;
                    }
                    if (!empty($should)) {
                        $query['bool']['should'] = $should;
                        $query['bool']['minimum_should_match'] = 1;
                    }

                    $msearchBody[] = ['index' => 'bl01_202303'];
                    $msearchBody[] = [
                        'query' => $query,
                        '_source' => ['BLMC', 'HJNR'],
                        'highlight' => ['fields' => ['*' => new \stdClass()]],
                        'size' => 1000
                    ];
                    $msearchMap[] = ['zyh' => $zyh, 'type' => 'ss_zt'];
                }
            }

            // 执行 Multi-Search
            if (!empty($msearchBody)) {
                $msearchResults = app('es')->msearch(['body' => $msearchBody]);

                // 解析 Multi-Search 结果
                foreach ($msearchResults['responses'] as $index => $response) {
                    $mapInfo = $msearchMap[$index];
                    $zyh = $mapInfo['zyh'];
                    $type = $mapInfo['type'];

                    if (isset($response['hits']['hits']) && !empty($response['hits']['hits'])) {
                        $data = [];
                        foreach ($response['hits']['hits'] as $hit) {
                            $item = $hit['_source'];
                            // 处理高亮
                            if (!empty($hit['highlight'])) {
                                foreach ($hit['highlight'] as $field => $highlights) {
                                    $item[$field] = implode(' ', $highlights);
                                }
                            }
                            $data[] = $item;
                        }

                        if (strpos($type, 'tiwen_') === 0) {
                            $tiwenBatch[$zyh][str_replace('tiwen_', '', $type)] = $data;
                        } else {
                            switch ($type) {
                                case 'bllb303':
                                    $bllb303Batch[$zyh] = $data;
                                    break;
                                case 'qtzd':
                                    $qtzdBatch[$zyh] = $data;
                                    break;
                                case 'qtss':
                                    $qtssBatch[$zyh] = $data;
                                    break;
                                case 'bcjl_sxjl':
                                    $bcjlSxjlBatch[$zyh] = $data;
                                    break;
                                case 'bcjl_qw':
                                    $bcjlQwBatch[$zyh] = $data;
                                    break;
                                case 'ss_zt':
                                    $ssZtBatch[$zyh] = $data;
                                    break;
                            }
                        }
                    }
                }
            }

            // 解析详情数据
            $returnGroupKey = MedicalHistoryService::$returnGroupKey;
            foreach ($blDetail[0] as $value) {
                $zyh = strip_tags($value['ZYH']);
                $arr = [];

                if (!empty($serachField)) {
                    foreach ($serachField as $field) {
                        if (in_array($field, $returnGroupKey['patient_info'])) {
                            // 用户信息
                            if ($field == 'AAA02C') {
                                $sex = [0 => '未知的性别', 1 => '男', 2 => '女', 9 => '未说明的性别'];
                                $arr['patient_info'][$field] = !empty($sex[$value[$field]]) ? "<font color='red'>" . $sex[$value[$field]] . "</font>" : $value[$field];
                            } else {
                                $arr['patient_info'][$field] = $value[$field];
                            }
                        } elseif (!empty($value[$field])) {
                            if (in_array($field, $returnGroupKey['ryjl'])) {
                                // 入院记录
                                if (stripos($value[$field], "<font color='red'>") !== false) {
                                    $arr['ryjl'][$field] = $value[$field] ?? '';
                                }
                            } elseif (in_array($field, $returnGroupKey['bcjl_scbc'])) {
                                // 病程记录-首次病程
                                if (stripos($value[$field], "<font color='red'>") !== false) {
                                    $arr['bcjl_scbc'][$field] = $value[$field];
                                }
                            } elseif (in_array($field, $returnGroupKey['cyjl'])) {
                                // 出院记录
                                if (stripos($value[$field], "<font color='red'>") !== false) {
                                    $fieldKey = str_replace('CYJL_', '', $field);
                                    $arr['cyjl'][$fieldKey] = $value[$field] ?? '';
                                }
                            } elseif (in_array($field, $returnGroupKey['zyzd'])) {
                                // 主要诊断
                                $arr['zyzd'][$field] = $value[$field];
                            } elseif (in_array($field, $returnGroupKey['zyss'])) {
                                // 主要手术
                                $arr['zyss'][$field] = $value[$field];
                            }
                        }
                    }
                }

                // 使用批量查询的结果
                if (!empty($bllb303Batch[$zyh])) {
                    $arr['ssjl'] = $bllb303Batch[$zyh];
                }
                if (!empty($qtzdBatch[$zyh])) {
                    $arr['qtzd'] = $qtzdBatch[$zyh];
                }
                if (!empty($qtssBatch[$zyh])) {
                    $arr['qtss'] = $qtssBatch[$zyh];
                }
                if (!empty($bcjlSxjlBatch[$zyh])) {
                    $arr['bcjl_sxjl'] = $bcjlSxjlBatch[$zyh];
                }
                if (!empty($tiwenBatch[$zyh])) {
                    foreach ($tiwenBatch[$zyh] as $key => $data) {
                        $arr[$key] = $data;
                    }
                }
                if (!empty($bcjlQwBatch[$zyh])) {
                    if (empty($arr['bllb294'])) {
                        $arr['bllb294'] = $bcjlQwBatch[$zyh];
                    } else {
                        $arr['bllb294'] = array_merge($bcjlQwBatch[$zyh], $arr['bllb294']);
                    }
                }
                if (!empty($ssZtBatch[$zyh])) {
                    if (empty($arr['ssjl'])) {
                        $arr['ssjl'] = $ssZtBatch[$zyh];
                    } else {
                        $arr['ssjl'] = array_merge($ssZtBatch[$zyh], $arr['ssjl']);
                    }
                }

                // 使用批量查询的结果（替代原来的循环查询）
                $arr['patient_info']['AAA28'] = $value['AAA28'];
                $arr['patient_info']['AAC01'] = $value['AAC01'];
                $arr['patient_info']['AAC11N'] = $depMap[strip_tags($value['AAC02C'])] ?? $value['AAC02C'];
                $arr['patient_info']['ZYH'] = $value['ZYH'];
                $arr['yzb'] = $yzbBatch[$zyh] ?? [];
                $arr['fymx'] = $fymxBatch[$zyh] ?? [];
                $arr['sssq'] = $sssqBatch[$zyh] ?? [];
                $arr['test_result'] = $jyBatch[$zyh] ?? [];
                $arr['ym_result'] = $ymBatch[$zyh] ?? [];
                $arr['pacs'] = $jcBatch[$zyh] ?? [];

                $detailList[] = $arr;
            }
        }

        return ToolsService::returnData(200, ['total' => $total, 'list' => $list, 'detail' => $detailList]);
    }

    /**
     * 病历搜索导出
     * @param Request $request
     * @return string|null
     */
    public function blSerachExport(Request $request)
    {
        // 参数接收
        $serachData = $request->all();
        $page = 1;
        $pageSize = 10000;

        $blSerachService = new ElasticsearchService('bl_serach_2023');
        $mhService = new MedicalHistoryService();

        // 数据查询
        $returnData = $mhService->blSerach($serachData, $page, $pageSize);
        $restful = $returnData['restful'];

        // 使用缓存获取管理员科室配置
        $adminDep = Cache::remember('admin_dep_28', 3600, function () {
            return RuleWordMap::query()->where('id', '=', '28')->value('keyword');
        });
        $adminDepArr = explode(',', $adminDep);
        if (!empty(self::$userData['dep_name'])) {
            $serachData['AAC11N'] = self::$userData['dep_name'];
        }
        if (in_array(self::$userData['dep_id'], $adminDepArr)) {
            $serachData['AAC11N'] = '';
        }

        // 解析列表页数据
        $blData = $blSerachService->getDataByEs($restful);
        $list = [];
        $list[] = ['住院号', '年龄', '出院科室', '入院时间', '出院时间'];
        if (!empty($blData[0])) {
            foreach ($blData[0] as $value) {
                $list[] = [
                    'AAA28' => $value['AAA28'],
                    'AAA04' => $value['AAA04'],
                    'AAC11N' => $value['AAC11N'],
                    'AAB01' => $value['AAB01'],
                    'AAC01' => $value['AAC01'],
                ];
            }
        }

        $csv = new CsvService();
        $csv->filename = $csv->charset('缺陷列表导出', 'UTF-8');

        return $csv->export($list, false);
    }

    public function getTiWenWhere()
    {
        $arr = [
            ['id' => 'RYJL_HJNR', 'name' => '入院记录'],
            ['id' => 'CYJL_HJNR', 'name' => '出院记录'],
            ['id' => 'SSJL_HJNR', 'name' => '手术记录'],
            ['id' => 'bllb294_HJNR', 'name' => '病程记录'],
            ['id' => 'bllb18_HJNR', 'name' => '24小时出入院记录'],
        ];

        return ToolsService::returnData(200, $arr);
    }
}
