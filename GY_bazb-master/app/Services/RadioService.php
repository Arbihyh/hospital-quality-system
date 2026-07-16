<?php

namespace App\Services;

use App\Model\Bllb294_295;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Model\FeeDetailed;
use App\Model\MainOperation;
use App\Model\MedicinalInfo;
use App\Model\OperationInfo;
use App\Model\PACS;
use App\Model\Mzjl;
use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use App\Model\PatientInfoTargetNew;
use App\Model\Pszb;
use App\Model\Setting;
use App\Model\SSSQ;
use App\Model\Staff;
use App\Model\Yzb;
use App\Model\ZbBagl;
use App\Services\ElasticsearchService;
use Carbon\Carbon;
use Exception;
use App\Model\PatientMedicalInfo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\EventListener\ValidateRequestListener;
use App\Model\EMR_BL_BLSY;
use App\Model\RuleWordMap;
use App\Model\YK_TYPK;
use PHPUnit\Framework\Constraint\IsFalse;

/**
 * 比例数据
 */
class RadioService
{
    // 手术判别是手术和介入治疗
    public const SSPB14 = [1, 4];
    public $startTime = '2024-01-01 00:00:00';
    public $endTime = '2025-03-01 00:00:00';

    public function __construct()
    {
        //$this->startTime = time() - 24 * 3600 * 30;
    }

    public function cacheData()
    {
        // 病理
        $this->bingliData();
        // 抗菌药物检查
        $this->kjywData();
        // 化疗药物检查
        $this->exzlhxzlData();
        // 医嘱名称“放疗”关键字检查
        $this->exzlfszlData();
        // 不合理复制病历信息检查
        $this->buheliCopy();
        // 手术相关记录完整率
        $this->operationComplete();
        // 手术记录24小时内完成率（MER-TL-02）
        $this->operateCompletionRate();
        //        医师查房记录完整率
        $this->chafangCompletionRate();
    }

    // 医师查房记录完整率
    // update patient_info_target set denominator_chafangCompletionRate=0,numerator_chafangCompletionRate=0,chafangCompletionRate_error="";
    public function chafangCompletionRate()
    {
        $setName = 'quality_zb_chafangCompletionRate';
        $bagl_12 = ZbBagl::getFirstById(12, true);
        $bagl_11 = ZbBagl::getFirstById(11, true);
        $bagl_13 = ZbBagl::getFirstById(13, true);
        $bagl_14 = ZbBagl::getFirstById(14, true);
        $bagl_15 = ZbBagl::getFirstById(15, true);
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        $staff = Staff::query()->get()->toArray();
        $staffArr = [];
        foreach ($staff as $v) {
            $staffArr[$v['code']] = $v;
        }
        $baglWhere = [];
        if (!empty($bagl_12->condition) && stripos(',', $bagl_12->condition)) {
            $wheres = explode(',', $bagl_12->condition);
            foreach ($wheres as $val) {
                $where = explode('=', $val);
                $baglWhere[] = ['term' => [$where[0] => $where[1]]];
            }
        } elseif (!empty($bagl_12->condition)) {
            $where = explode('=', $bagl_12->condition);
            $baglWhere[] = ['term' => [$where[0] => $where[1]]];
        }

        $page = 1;
        $pageSize = 10000;
        $piEesService = new ElasticsearchService('patient_info');
        $mzjlesService = new ElasticsearchService('mzjl_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');
        $sssqesService = new ElasticsearchService('sssq_2023');
        $zyHcmxEsService = new ElasticsearchService($bagl_12->table_name); //id12
        $star = $this->startTime;

        while (1) {
            if ($star > time()) {
                break;
            }
            //结束时间是开始时间加一天,开始时间是2024-01-01 00:00:00
            $entTime = date('Y-m-d H:i:s', strtotime($star) + 86400);


            //$entTime = date('Y-m-d H:i:s', $star);
            var_dump('开始时间：' . $star, '结束时间' . $entTime);
            $params = $piEesService->paginate($page, $pageSize)
                ->clearMust()
                ->queryByMust(['range' => ['AAC01' => ['gte' => $star, 'lte' => $entTime]]])
                ->source(['MED_REC_ID', 'AAB01', 'AAC01', 'AAC04', 'AAC01'])
                ->getParams();
            $res = app('es')->search($params);
            $res = $piEesService->getDataByEs($res);
            $star = $entTime;
            if (empty($res[0])) {
                continue;
            }
            $fenzi = [];
            $patientInfo = $res[0];

            // 手术申请单
            $zyh = array_column($patientInfo, 'MED_REC_ID');
            $sssqMust = [
                'terms' => [
                    "ZYH" => $zyh
                ]
            ];
            $params = $sssqesService->clearMust()->queryByMust($sssqMust)->source(['ZYH', 'NSSMC'])->getParams();
            $mzRes = app('es')->search($params);
            $mzRes = $sssqesService->getDataByEs($mzRes);
            $sssqData = [];
            if (!empty($mzRes[0])) {
                foreach ($mzRes[0] as $m) {
                    $sssqData[$m['ZYH']][] = $m;
                }
            }

            // 护士分床时间  根据id 12配置
            $must = [
                [
                    "terms" => [
                        $bagl_12->MED_REC_ID => array_values($zyh)  // 确保是索引数组
                    ]
                ]
            ];

            $must = array_merge($must, $baglWhere);


            $params = $zyHcmxEsService->clearMust()->queryByMustBatch($must)->getParams();
            //var_dump('ES Query Params:', $params);

            $mzRes = app('es')->search($params);
            $mzRes = $zyHcmxEsService->getDataByEs($mzRes);
            //var_dump('ES Raw Response:', $mzRes);
            $zyHcmxData = [];
            if (!empty($mzRes[0])) {
                $zyHcmxData = array_column($mzRes[0], null, $bagl_12->MED_REC_ID);
            }

            // 医嘱本
            $yzb = Yzb::query()->whereIn('ZYH', $zyh)
                ->whereIn('YDYZLB', [303, 305])
                ->get(['ZYH', 'XZJDSJ'])->toArray();
            $yzb = array_column($yzb, null, 'ZYH');

            foreach ($patientInfo as $p) {
                $lastId = $p['AAC01'];
                var_dump($lastId);
                $sssqError = '';
                $flag = 1;
                $operateCompletionRateError = [];
                $sssq = empty($sssqData[$p['MED_REC_ID']]) ? [] : $sssqData[$p['MED_REC_ID']];

                $error = '';
                if ($sssq) {
                    $sssqError = [];
                    foreach ($sssq as $s) {
                        $sqe = '';
                        // 通过病历文本搜“手术记录”，获取“术者姓名”，再去匹配“工号”
                        $bl01must = [];
                        $bl01must[] = [
                            'term' => [
                                "JZHM" => $p['MED_REC_ID']
                            ]
                        ];
                        $bl01must[] = [
                            'match_phrase' => [
                                "HJNR" => "手术记录"
                            ]
                        ];
                        $bl01must[] = [
                            'match_phrase' => [
                                "HJNR" => $s['NSSMC']
                            ]
                        ];
                        $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['BLBH', 'operation_time', 'operation_handler', 'operation_handler_code'])->getParams();
                        $bl01Res = app('es')->search($params);
                        $bl01Res = $bl01esService->getDataByEs($bl01Res);

                        if (empty($bl01Res[0]) || empty($bl01Res[0][0]) || empty($bl01Res[0][0]['operation_handler'])) {
                            $flag = 0;
                            $sqe .= '手术记录【无】';
                        } else {

                            // 获取“麻醉记录单中手术开始时间”
                            $operationHandler = $bl01Res[0][0]['operation_handler']; // 术者
                            $operationHandlerCode = $bl01Res[0][0]['operation_handler_code']; // 术者工号
                            $BLBH = $bl01Res[0][0]['BLBH']; // 病例编号
                            $operationTime = $bl01Res[0][0]['operation_time']; // 手术时间

                            $sqe .= '术者【EMR_BL_BLXG ' . $operationHandler . '】';
                            $mzjlmust = [];
                            $mzjlmust[] = [
                                'term' => [
                                    "HOSPIZATIONID" => $p['MED_REC_ID']
                                ]
                            ];
                            $mzjlmust[] = [
                                'term' => [
                                    "PREOPERATIONNAME" => $s['NSSMC']
                                ]
                            ];
                            $params = $mzjlesService->clearMust()->queryByMustBatch($mzjlmust)->source(['OPERATESTARTTIME', 'OPERATEENDTIME'])->getParams();
                            $mzRes = app('es')->search($params);
                            $mzRes = $mzjlesService->getDataByEs($mzRes);

                            if (empty($mzRes[0])) {
                                $flag = 0;
                                $sqe .= "麻醉记录【无】";
                            } else {
                                // 术前病程
                                $startTime = $mzRes[0][0]['OPERATESTARTTIME'];
                                $endTime = date("Y-m-d H:i:s", strtotime($startTime) - 24 * 3600);
                                $bl01must = [];
                                $bl01must[] = [
                                    'term' => [
                                        "JZHM" => $p['MED_REC_ID']
                                    ]
                                ];
                                $bl01must[] = [
                                    'term' => [
                                        "BLLB" => 294
                                    ]
                                ];
                                $bl01must[] = [
                                    'range' => [
                                        "CJSJ" => [
                                            'from' => $endTime,
                                            'to' => $startTime
                                        ]
                                    ]
                                ];
                                $bl01must[] = [
                                    'match_phrase' => [
                                        "HJNR" => $operationHandler
                                    ]
                                ];
                                $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['CJSJ'])->getParams();
                                $bl01Res = app('es')->search($params);
                                $bl01Res = $bl01esService->getDataByEs($bl01Res);

                                if (empty($bl01Res[0]) || empty($bl01Res[0][0])) {
                                    $flag = 0;
                                    $sqe .= '，术前病程【无】';
                                } else {
                                    $sqe .= '，术前病程【' . $bl01Res[0][0]['CJSJ'] . '、术前24h内】';
                                }

                                // 术后病程
                                $startTime = $mzRes[0][0]['OPERATEENDTIME'];
                                $endTime = date("Y-m-d H:i:s", strtotime($startTime) + 24 * 3600);
                                $bl01must = [];
                                $bl01must[] = [
                                    'term' => [
                                        "JZHM" => $p['MED_REC_ID']
                                    ]
                                ];
                                $bl01must[] = [
                                    'term' => [
                                        "BLLB" => 294
                                    ]
                                ];
                                $bl01must[] = [
                                    'range' => [
                                        "CJSJ" => [
                                            'from' => $startTime,
                                            'to' => $endTime
                                        ]
                                    ]
                                ];
                                $bl01must[] = [
                                    'match_phrase' => [
                                        "HJNR" => $operationHandler
                                    ]
                                ];
                                $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['CJSJ'])->getParams();
                                $bl01Res = app('es')->search($params);
                                $bl01Res = $bl01esService->getDataByEs($bl01Res);
                                if (empty($bl01Res[0]) || empty($bl01Res[0][0])) {
                                    $flag = 0;
                                    $sqe .= '，手术病程【无】';
                                } else {
                                    $sqe .= '，手术病程【' . $bl01Res[0][0]['CJSJ'] . '、术后24h内】';

                                    // 4、手术时间术后3天有病程
                                    $cysj = strtotime(date("Y-m-d", strtotime($p['AAC01'])));
                                    $bingcheng1 = 0;
                                    $bingcheng2 = 0;
                                    $bingcheng3 = 0;
                                    // 如果当天出院则只需要出院当天的病程记录
                                    if ($cysj == $operationTime) {
                                        $bl01must = [];
                                        $bl01must[] = [
                                            'term' => [
                                                "JZHM" => $p['MED_REC_ID']
                                            ]
                                        ];
                                        $bl01must[] = [
                                            'term' => [
                                                "BLLB" => 294
                                            ]
                                        ];
                                        $bl01must[] = [
                                            'range' => [
                                                "CJSJ" => [
                                                    'from' => date("Y-m-d H:i:s", $operationTime),
                                                    'to' => date("Y-m-d H:i:s", $operationTime + 24 * 3600)
                                                ]
                                            ]
                                        ];
                                        $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['CJSJ'])->getParams();
                                        $bl01Res = app('es')->search($params);
                                        $bl01Res = $bl01esService->getDataByEs($bl01Res);
                                        $bingcheng1 = empty($bl01Res[0][0]) ? [] : $bl01Res[0][0];
                                        $bingcheng2 = ['CJSJ' => '-'];
                                        $bingcheng3 = ['CJSJ' => '-'];
                                    } else {
                                        if ($cysj && $cysj >= $operationTime + 24 * 3600) {

                                            $bl01must = [];
                                            $bl01must[] = [
                                                'term' => [
                                                    "JZHM" => $p['MED_REC_ID']
                                                ]
                                            ];
                                            $bl01must[] = [
                                                'term' => [
                                                    "BLLB" => 294
                                                ]
                                            ];
                                            $bl01must[] = [
                                                'range' => [
                                                    "CJSJ" => [
                                                        'from' => date("Y-m-d H:i:s", $operationTime + 24 * 3600),
                                                        'to' => date("Y-m-d H:i:s", $operationTime + 24 * 3600 * 2)
                                                    ]
                                                ]
                                            ];
                                            $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['CJSJ'])->getParams();
                                            $bl01Res = app('es')->search($params);
                                            $bl01Res = $bl01esService->getDataByEs($bl01Res);
                                            $bingcheng1 = empty($bl01Res[0][0]) ? [] : $bl01Res[0][0];
                                            $bingcheng2 = ['CJSJ' => '-'];
                                            $bingcheng3 = ['CJSJ' => '-'];
                                        }
                                        if ($cysj && $cysj >= $operationTime + 2 * 24 * 3600) {

                                            $bl01must = [];
                                            $bl01must[] = [
                                                'term' => [
                                                    "JZHM" => $p['MED_REC_ID']
                                                ]
                                            ];
                                            $bl01must[] = [
                                                'term' => [
                                                    "BLLB" => 294
                                                ]
                                            ];
                                            $bl01must[] = [
                                                'range' => [
                                                    "CJSJ" => [
                                                        'from' => date("Y-m-d H:i:s", $operationTime + 24 * 3600 * 2),
                                                        'to' => date("Y-m-d H:i:s", $operationTime + 24 * 3600 * 3)
                                                    ]
                                                ]
                                            ];
                                            $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['CJSJ'])->getParams();
                                            $bl01Res = app('es')->search($params);
                                            $bl01Res = $bl01esService->getDataByEs($bl01Res);
                                            $bingcheng2 = empty($bl01Res[0][0]) ? [] : $bl01Res[0][0];
                                            $bingcheng3 = ['CJSJ' => '-'];
                                        }
                                        if ($cysj && $cysj >= $operationTime + 3 * 24 * 3600) {

                                            $bl01must = [];
                                            $bl01must[] = [
                                                'term' => [
                                                    "JZHM" => $p['MED_REC_ID']
                                                ]
                                            ];
                                            $bl01must[] = [
                                                'term' => [
                                                    "BLLB" => 294
                                                ]
                                            ];
                                            $bl01must[] = [
                                                'range' => [
                                                    "CJSJ" => [
                                                        'from' => date("Y-m-d H:i:s", $operationTime + 24 * 3600 * 3),
                                                        'to' => date("Y-m-d H:i:s", $operationTime + 24 * 3600 * 4)
                                                    ]
                                                ]
                                            ];
                                            $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['CJSJ'])->getParams();
                                            $bl01Res = app('es')->search($params);
                                            $bl01Res = $bl01esService->getDataByEs($bl01Res);
                                            $bingcheng3 = empty($bl01Res[0][0]) ? [] : $bl01Res[0][0];
                                        }
                                    }

                                    if (!$bingcheng1 || !$bingcheng2 || !$bingcheng3) {
                                        $sqe .= '，术后病程【术后病程记录（无）】';
                                        $flag = 0;
                                    } else {
                                        $sqe .= '，术后病程【3天，' . $bingcheng1['CJSJ'] . '、' . $bingcheng2['CJSJ'] . '、' . $bingcheng3['CJSJ'] . '、】';
                                    }
                                }
                            }
                        }
                        $sssqError[] = $sqe;
                    }
                    if ($sssqError) {

                        $errorContent = '';
                        foreach ($sssqError as $k => $item) {
                            $errorContent .= ($k + 1) . '：' . $item;
                        }
                        $error .= $errorContent . '，';
                    }
                }
                // 如果上面的条件不正确则错误信息也不显示
                if (!$flag) {
                    $error = '';
                    $flag = 1;
                }
                var_dump($p['MED_REC_ID']);
                var_dump([
                    'original_id' => $p['MED_REC_ID'],
                    'zyHcmxData_keys' => array_keys($zyHcmxData),  // 打印所有可用的键
                    'data_exists' => isset($zyHcmxData[$p['MED_REC_ID']]),
                ]);
                //var_dump($p['MED_REC_ID'], $zyHcmxData[$p['MED_REC_ID']]);
                if (empty($zyHcmxData[$p['MED_REC_ID']])) {
                    $error .= '护士分床数据不存在';
                }
                if (empty($yzb[$p['MED_REC_ID']])) {
                    $error .= '，医嘱本信息不存在';
                }
                if ($p['AAC04'] && !empty($zyHcmxData[$p['MED_REC_ID']]) && !empty($yzb[$p['MED_REC_ID']])) {
                    $zhengName = $fuName = '';
                    $error .= '，住院天数【' . $p['AAC04'] . '】';
                    $error .= '，分床时间：' . $zyHcmxData[$p['MED_REC_ID']][$bagl_12->table_field] . '-' . $yzb[$p['MED_REC_ID']]['XZJDSJ']; //id12
                    if (
                        !empty($yzb[$p['MED_REC_ID']]['XZJDSJ']) &&
                        !empty($zyHcmxData[$p['MED_REC_ID']][$bagl_12->table_field]) && //id12
                        strtotime($yzb[$p['MED_REC_ID']]['XZJDSJ']) > 0 &&
                        strtotime($zyHcmxData[$p['MED_REC_ID']][$bagl_12->table_field]) > 0
                    ) {
                        $startTime = $fenchuangTime = $zyHcmxData[$p['MED_REC_ID']][$bagl_12->table_field];
                        $endTime = $yzb[$p['MED_REC_ID']]['XZJDSJ'];

                        // 住院天数大于7天
                        if ($p['AAC04'] > 7) {
                            while (true) {
                                $fromTime = $startTime;
                                $toTime = date("Y-m-d H:i:s", strtotime($startTime) + 7 * 24 * 3600);

                                if (strtotime($toTime) > strtotime($endTime)) {
                                    break;
                                }

                                $bl01must = [
                                    ['term' => ["JZHM" => $p['MED_REC_ID']]],
                                    ['term' => ["BLLB" => 294]],
                                    ['range' => [$bagl_11->table_field => ['from' => $fromTime, 'to' => $toTime]]] //id11
                                ];
                                $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['BLBH', $bagl_11->table_field])->getParams(); //id11
                                $bl01Res = app('es')->search($params);
                                $bl01Res = $bl01esService->getDataByEs($bl01Res);
                                $bl01294 = empty($bl01Res[0]) ? [] : $bl01Res[0];

                                //                                    获取7天所有病程中的所有正副科的数据
                                $zheng = $fu = $zhong = [];
                                foreach ($bl01294 as $item) {

                                    $blsy = EMR_BL_BLSY::query()
                                        ->where('BLBH', '=', $item['BLBH'])
                                        ->get()->toArray();
                                    $code = array_column($blsy, 'SYYS');
                                    if ($blsy) {
                                        $staff = Staff::query()->whereIn('code', $code)->get()->toArray();
                                        foreach ($staff as $b) {
                                            //在$bagl_13[keyword]中是否存在
                                            if (in_array($b['ygjb'], explode(',', $bagl_13['keyword']))) {
                                                $zhengName = $b['name'];
                                                $zheng[] = $item[$bagl_11->table_field]; //id11
                                            } elseif (in_array($b['ygjb'], explode(',', $bagl_14['keyword']))) { //id14
                                                $fuName = $b['name'];
                                                $fu[] = $item[$bagl_11->table_field]; //id11
                                            } elseif (in_array($b['ygjb'], explode(',', $bagl_15->keyword))) { //id15
                                                $zhong[] = $item[$bagl_11->table_field]; //id11
                                            }
                                        }
                                    }
                                }

                                if (!empty($zheng)) {
                                    $error .= '，正高查房【' . count($zheng) . '次/7d，' . implode(',', $zheng) . '】';
                                }
                                if (!empty($fu)) {
                                    $error .= '，副高查房【' . count($fu) . '次/7d，' . implode(',', $fu) . '】';
                                }
                                if (!empty($zhong)) {
                                    $error .= '，中级职称查房【' . count($zhong) . '次/7d，' . implode(',', $zhong) . '】';
                                }

                                $startTime = $toTime;
                            }
                        }

                        $startDay = date('z', strtotime($startTime));
                        $endDay = date('z', strtotime($endTime));
                        $diffDay = $endDay - $startDay;
                        if ($diffDay >= 3) {

                            $bl01must = [
                                ['term' => ["JZHM" => $p['MED_REC_ID']]],
                                ['term' => ["BLLB" => 294]],
                                ['range' => [$bagl_11->table_field => ['from' => $startTime, 'to' => $endTime]]] //id11
                            ];
                            $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['BLBH', $bagl_11->table_field])->getParams(); //id11
                            $bl01Res = app('es')->search($params);
                            $bl01Res = $bl01esService->getDataByEs($bl01Res);
                            $bl01294 = empty($bl01Res[0]) ? [] : $bl01Res[0];

                            //                                    获取7天所有病程中的所有正副科的数据
                            $zheng = $fu = $zhong = [];
                            foreach ($bl01294 as $item) {

                                $blsy = EMR_BL_BLSY::query()
                                    ->where('BLBH', '=', $item['BLBH'])
                                    ->get()->toArray();
                                $code = array_column($blsy, 'SYYS');
                                if ($blsy) {
                                    $staff = Staff::query()->whereIn('code', $code)->get()->toArray();
                                    foreach ($staff as $b) {
                                        if (in_array($b['ygjb'], explode(',', $bagl_13['keyword']))) { //id13
                                            $zhengName = $b['name'];
                                            $zheng[] = $item[$bagl_11->table_field]; //id11
                                        } elseif (in_array($b['ygjb'], explode(',', $bagl_14['keyword']))) { //id14
                                            $fuName = $b['name'];
                                            $fu[] = $item[$bagl_11->table_field]; //id11
                                        } elseif (in_array($b['ygjb'], explode(',', $bagl_15->keyword))) { //id15
                                            $zhong[] = $item[$bagl_11->table_field]; //id11
                                        }
                                    }
                                }
                            }

                            if (!empty($zheng)) {
                                $error .= '，正高查房【' . count($zheng) . '次/' . $diffDay . 'd，' . implode(',', $zheng) . '】';
                            }
                            if (!empty($fu)) {
                                $error .= '，副高查房【' . count($fu) . '次/' . $diffDay . 'd，' . implode(',', $fu) . '】';
                            }
                            if (!empty($zhong)) {
                                $error .= '，中级职称查房【' . count($zhong) . '次/' . $diffDay . 'd，' . implode(',', $zhong) . '】';
                            }
                        }

                        // 出院前48小时内的病程记录
                        $bl01must = [
                            ['term' => ["JZHM" => $p['MED_REC_ID']]],
                            ['term' => ["BLLB" => 294]],
                            ['range' => [$bagl_11->table_field => ['from' => date('Y-m-d H:i:s', strtotime($endTime) - 48 * 3600), 'to' => $endTime]]]
                        ];
                        $params = $bl01esService->clearMust()->queryByMustNot(['term' => ["MBLB" => 295]])->queryByMustBatch($bl01must)->source([$bagl_11->table_field])->getParams();
                        $bl01Res = app('es')->search($params);
                        $bl01Res = $bl01esService->getDataByEs($bl01Res);
                        $bl01294 = empty($bl01Res[0]) ? [] : $bl01Res[0];

                        // 24小时出入院记录BLLB=18
                        $bl01must = [
                            ['term' => ["JZHM" => $p['MED_REC_ID']]],
                            ['term' => ["BLLB" => 18]]
                        ];
                        $params = $bl01esService->clearMust()->queryByMustNot(['term' => ["MBLB" => 295]])->queryByMustBatch($bl01must)->source([$bagl_11->table_field])->getParams(); //id11
                        $bl01Res = app('es')->search($params);
                        $bl01Res = $bl01esService->getDataByEs($bl01Res);
                        $bl0118 = empty($bl01Res[0]) ? [] : $bl01Res[0];

                        if (empty($bl01294) && empty($bl0118)) {
                            $flag = 0;
                            $error .= '，出院48小时内病程【无】';
                        } elseif (!empty($bl01294)) {
                            $error .= '，出院48小时内病程【' . $bl01294[0][$bagl_11->table_field] . '】'; //id11
                        } elseif ($bl0118) {
                            $error .= '，24小时出入院';
                        }

                        // 护士分床后48小时内，病程记录中，需要有一个 副高或正高签名（首次病程除外）
                        $bl01must = [
                            ['term' => ["JZHM" => $p['MED_REC_ID']]],
                            ['term' => ["BLLB" => 294]],
                            ['match_phrase' => ["HJNR" => $zhengName]],
                            ['range' => [$bagl_11->table_field => ['from' => $fenchuangTime, 'to' => date('Y-m-d H:i:s', strtotime($fenchuangTime) + 48 * 3600)]]]
                        ];
                        $params = $bl01esService->clearMust()->queryByMustNot(['term' => ["MBLB" => 295]])->queryByMustBatch($bl01must)->source(['BLBH', $bagl_11->table_field])->getParams(); //id11
                        $bl01Res = app('es')->search($params);
                        $bl01Res = $bl01esService->getDataByEs($bl01Res);
                        $bl01294Zheng = empty($bl01Res[0]) ? [] : $bl01Res[0];

                        $bl01294Fu = [];
                        if ($fuName) {
                            $bl01must = [
                                ['term' => ["JZHM" => $p['MED_REC_ID']]],
                                ['term' => ["BLLB" => 294]],
                                ['match_phrase' => ["HJNR" => $fuName]],
                                ['range' => [$bagl_11->table_field => ['from' => $fenchuangTime, 'to' => date('Y-m-d H:i:s', strtotime($fenchuangTime) + 48 * 3600)]]] //id11
                            ];
                            $params = $bl01esService->clearMust()->queryByMustNot(['term' => ["MBLB" => 295]])->queryByMustBatch($bl01must)->source(['BLBH', $bagl_11->table_field])->getParams(); //id11
                            $bl01Res = app('es')->search($params);
                            $bl01Res = $bl01esService->getDataByEs($bl01Res);
                            $bl01294Fu = empty($bl01Res[0]) ? [] : $bl01Res[0];
                        }
                        if (empty($zhengName) && empty($fuName) && empty($bl01294Zheng) && empty($bl01294Fu) && empty($bl0118)) {
                            $flag = 0;
                            $error .= '，入院48小时内正或副高查房【无】';
                        } elseif ($bl01294Zheng || $bl01294Fu) {
                            $error .= '，入院48小时内正或副高查房【';
                            $total = [];
                            $total = array_merge($total, array_column($bl01294Zheng, 'BLBH'));
                            $total = array_merge($total, array_column($bl01294Fu, 'BLBH'));
                            $error .= count(array_unique($total)) . '、';
                            if ($bl01294Zheng) {
                                foreach ($bl01294Zheng as $item) {
                                    $error .= '正' . $item[$bagl_11->table_field] . '、'; //id11
                                }
                            }
                            if ($bl01294Fu) {
                                foreach ($bl01294Fu as $item) {
                                    $error .= '副' . $item[$bagl_11->table_field] . '、'; //id11
                                }
                            }
                            $error .= '】';
                        } elseif ($bl0118) {
                            $error .= '，护士分床后48小时内有24小时出入院';
                        }
                    }
                } else {
                    $flag = 0;
                    $error .= '，住院天数【无】';
                }
                $operateCompletionRateError[] = $error;

                $kjywErrorContent = '';
                foreach ($operateCompletionRateError as $k => $item) {
                    $kjywErrorContent .= ($k + 1) . '：' . $item;
                }

                $kjywErrorContent = $kjywErrorContent ?: $sssqError;
                $fenzi[] = ['ZYH' => $p['MED_REC_ID'], 'numerator_chafangCompletionRate' => $flag, 'chafangCompletionRate_error' => $kjywErrorContent];
            }

            if ($fenzi) {
                foreach ($fenzi as $d) {
                    $d['ZYH'] = (string) $d['ZYH'];
                    PatientInfoTarget::query()->updateOrInsert(
                        ['ZYH' => $d['ZYH']],
                        [
                            'denominator_chafangCompletionRate' => 1,
                            'numerator_chafangCompletionRate' => $d['numerator_chafangCompletionRate'],
                            'chafangCompletionRate_error' => $d['chafangCompletionRate_error']
                        ]
                    );
                }
                var_dump($page);
            }
        }
    }

    /**
     * 手术记录24小时内完成率（MER-TL-02）
     * update patient_info_target set denominator_operateCompletionRate=0,numerator_operateCompletionRate=0,operateCompletionRate_error="";
     * ALTER TABLE `quality`.`patient_info_target`
     * ADD COLUMN `denominator_operateCompletionRate` tinyint(1) NOT NULL DEFAULT 0 COMMENT '手术记录24小时内完成率分母' AFTER `ryjl_error`,
     * ADD COLUMN `numerator_operateCompletionRate` tinyint(1) NOT NULL DEFAULT 0 COMMENT '手术记录24小时内完成率分子' AFTER `denominator_operateCompletionRate`,
     * ADD COLUMN `operateCompletionRate_error` text NULL AFTER `numerator_operateCompletionRate`,
     * ADD COLUMN `denominator_chafangCompletionRate` tinyint(1) NOT NULL DEFAULT 0 COMMENT '医师查房记录完整率分母',
     * ADD COLUMN `numerator_chafangCompletionRate` tinyint(1) NOT NULL DEFAULT 0 COMMENT '医师查房记录完整率分子',
     * ADD COLUMN `chafangCompletionRate_error` text NULL;
     */
    public
    function operateCompletionRate()
    {
        $page = 1;
        $pageSize = 100000;
        $piEesService = new ElasticsearchService('patient_info');
        $mzjlesService = new ElasticsearchService('mzjl_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');
        $sssqesService = new ElasticsearchService('sssq_2023');
        $startTime = $this->startTime;
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > time()) {
                break;
            }
            var_dump('时间：' . date("Y-m-d H:i:s", $startTime));
            $patientInfo = PatientInfo::query()
                ->where('AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                ->where('AAC01', '<=', date("Y-m-d H:i:s", $entTime))
                ->get(['MED_REC_ID', 'id', 'AAC01'])->toArray();
            $startTime = $entTime;
            if (empty($patientInfo)) {
                continue;
            }
            $fenzi = [];
            foreach ($patientInfo as $p) {
                $lastId = $p['MED_REC_ID'];
                var_dump($lastId);
                // 获取手术申请单，如果存在，则满足分母的条件
                $mzRes = SSSQ::query()->where(['ZYH' => $p['MED_REC_ID'], 'ZFBZ' => 0])->get()->toArray();
                if (empty($mzRes)) {
                    continue;
                }

                $operateCompletionRateError = [];
                $flag = 1;
                $sssq = $mzRes;
                // 获取手术申请相关的麻醉记录信息
                foreach ($sssq as $item) {
                    $error = '手术名称【' . $item['NSSMC'] . '】';

                    $mzRes = mzjl::query()->where(['HOSPIZATIONID' => $p['MED_REC_ID'], 'PREOPERATIONNAME' => $item['NSSMC']])->get()->toArray();
                    $ssstartTime = 0;
                    if (empty($mzRes)) {
                        $error .= "麻醉记录【无】";
                    } else {
                        $ssstartTime = strtotime($mzRes[0]['OPERATEENDTIME']);
                        $error .= '，手术结束时间（手麻）【' . $mzRes[0]['OPERATEENDTIME'] . '】';
                    }

                    // 获取手术记录
                    $bl01must = [];
                    $bl01must[] = ['term' => ["JZHM" => $p['MED_REC_ID']]];
                    $bl01must[] = ['term' => ["BLLB" => 303]];
                    $bl01Should = [];
                    $bl01Should[] = ['terms' => ["MBLB" => [306, 74]]];
                    $bl01Should[] = ['match_phrase' => ["BLMC" => "手术记录"]];
                    $params = $bl01esService->clearMust()->queryByShouldBatch($bl01Should)->queryByMustBatch($bl01must)->minimumShouldMatch(1)->source(['CJSJ', 'MBLB', 'operation_end_time'])->getParams();
                    $bl01Res = app('es')->search($params);
                    $bl01Res = $bl01esService->getDataByEs($bl01Res);
                    if (empty($bl01Res[0]) || empty($bl01Res[0][0])) {
                        $flag = 0;
                        $error .= '，手术记录【无】';
                    } else {
                        if (empty($ssstartTime) && !empty($bl01Res[0][0]['operation_end_time'])) {
                            $ssstartTime = $bl01Res[0][0]['operation_end_time'];
                            $error .= '，手术结束时间【' . date("Y-m-d H:i:s", $bl01Res[0][0]['operation_end_time']) . '】';
                        }
                        $cjsj = strtotime($bl01Res[0][0]['CJSJ']);
                        $error .= '，手术记录【' . $bl01Res[0][0]['CJSJ'] . '';
                        if ($ssstartTime) {
                            $endTime = $ssstartTime + 24 * 3600;

                            if ($cjsj > $endTime) {
                                $flag = 0;
                                $error .= '、创建时间超24小时】';
                            } else {
                                $error .= '、24小时内】';
                            }
                        } else {
                            $flag = 0;
                            $error .= '，手术结束时间【无】';
                        }
                    }

                    $operateCompletionRateError[] = $error;
                }

                $kjywErrorContent = '';
                foreach ($operateCompletionRateError as $k => $item) {
                    $kjywErrorContent .= ($k + 1) . '：' . $item;
                }
                $fenzi[] = ['ZYH' => $p['MED_REC_ID'], 'numerator_operateCompletionRate' => $flag, 'operateCompletionRate_error' => $kjywErrorContent];
            }

            if ($fenzi) {
                foreach ($fenzi as $d) {
                    $d['ZYH'] = (string) $d['ZYH'];
                    PatientInfoTarget::query()->updateOrInsert(
                        ['ZYH' => $d['ZYH']],
                        [
                            'denominator_operateCompletionRate' => 1,
                            'numerator_operateCompletionRate' => $d['numerator_operateCompletionRate'],
                            'operateCompletionRate_error' => $d['operateCompletionRate_error']
                        ]
                    );
                }
            }
        }
    }

    /**
     * @param array $where
     * @param int $type
     * @return mixed
     * @throws Exception
     * 获取绩效考核指标数据
     */
    public
    function getList(
        array $where = [],
        int $type = 0
    ) {

        if ($type === 0) {
            $res = $this->leaveHospital($where); // 出院患者手术占比
        } elseif ($type === 1) {
            $res = $this->gradeFour($where); // 出院患者四级手术占比
        } elseif ($type === 2) {
            $res = $this->infectRadio($where); // I类切口手术部位感染率
        } elseif ($type === 3) {
            $res = $this->miniInvasive($where); // 出院患者微创手术占比
        } elseif ($type === 4) {
            $res = $this->complication($where); // 手术患者并发症发生率
        } elseif (in_array($type, [11, 12, 13])) {
            $res = $this->hrConfigZb($type, $where);
        } else {
            $res = $this->getZbv2($type, $where);
        }

        if (empty($res)) {
            return ['numerator' => 0, 'denominator' => 0, 'res' => 0];
        }

        return $res;
    }

    /**
     * 获取新指标数据
     */
    public
    function getZbv2(
        $type = 0,
        $where = []
    ) {

        $dateType = [
            31 => ['numerator_ct', 'denominator_ct'],
            32 => ['numerator_bl', 'denominator_bl'],
            33 => ['numerator_xjpy', 'denominator_xjpy'],
            41 => ['numerator_kjyw', 'denominator_kjyw'],
            42 => ['numerator_exzlhxzl', 'denominator_exzlhxzl'],
            43 => ['numerator_exzlfszl', 'denominator_exzlfszl'],
            44 => ['numerator_operation', 'denominator_operation'],
            45 => ['numerator_zrw', 'denominator_zrw'],
            57 => ['numerator_bhlbl', 'denominator_bhlbl'],
            46 => ['numerator_lcyx', 'denominator_lcyx'],
            23 => ['numerator_cyjl', 'denominator_cyjl'],
            24 => ['numerator_basy', 'denominator_basy'],
            48 => ['numerator_hzqjjl', 'denominator_hzqjjl'],
            21 => ['numerator_ryjl', 'denominator_ryjl'],
            34 => ['numerator_xjpy1', 'denominator_xjpy1'],
            22 => ['numerator_operateCompletionRate', 'denominator_operateCompletionRate'],
            51 => ['numerator_cyhzgd', 'denominator_cyhzgd'],
            47 => ['numerator_chafangCompletionRate', 'denominator_chafangCompletionRate'],
            53 => ['numerator_zyzdtx', 'denominator_zyzdtx'],
            54 => ['numerator_zyzdbm', 'denominator_zyzdbm'],
            55 => ['numerator_zysstx', 'denominator_zysstx'],
            56 => ['numerator_zyssbm', 'denominator_zyssbm'],
            58 => ['numerator_zqtysgfqs', 'denominator_zqtysgfqs'],
            49 => ['numerator_hzqjcgl', 'denominator_hzqjcgl'],
            52 => ['numerator_cyhzgdl', 'denominator_cyhzgdl'],
            59 => ['numerator_A', 'denominator_A'],
            60 => ['numerator_ngzb', 'denominator_ngzb'],
            61 => ['numerator_xgzb', 'denominator_xgzb'],
            62 => ['numerator_ngrszb', 'denominator_ngrszb'],
        ];

        $year = $where['year'] ?? date('Y');

        $esService = new ElasticsearchService('patient_info_target');
        $aggs = [
            "AAC01" => [
                "date_histogram" => [
                    "field" => "AAC01",
                    "calendar_interval" => "1M",
                    "format" => "yyyy-MM",
                    'extended_bounds' => [
                        "min" => $year . "-01",
                        "max" => $year . "-12"
                    ],
                    "keyed" => true
                ],
            ]
        ];
        $fenmu = [
            "term" => [
                $dateType[$type][1] => 1
            ]
        ];
        $fenzi = [
            "term" => [
                $dateType[$type][0] => 1
            ]
        ];

        // 分母
        $params = $esService->queryByMust($fenmu)->source(['MED_REC_ID'])->aggs($aggs)->getParams();
        $restful = app('es')->search($params);
        $fenmuBuckets = $restful['aggregations']['AAC01']['buckets'];

        // 分子
        $params = $esService->queryByMust($fenzi)->source(['MED_REC_ID'])->aggs($aggs)->getParams();
        $restful = app('es')->search($params);
        $fenziBuckets = $restful['aggregations']['AAC01']['buckets'];


        $resArr = [];
        for ($i = 1; $i <= 12; $i++) {

            $key = $year . '-' . sprintf('%02s', $i);
            $denominator = !empty($fenmuBuckets[$key]) ? $fenmuBuckets[$key]['doc_count'] : 0;
            $numerator = !empty($fenziBuckets[$key]) ? $fenziBuckets[$key]['doc_count'] : 0;
            if (!$denominator) {
                $resArr[] = ['numerator' => 0, 'denominator' => 0, 'res' => 0, 'time' => $key, 'source' => '系统提取'];
                continue;
            }

            $res = bcdiv((string) $numerator, (string) $denominator, 4);
            $resArr[] = ['numerator' => $numerator, 'denominator' => $denominator, 'res' => $res, 'time' => $key, 'source' => '系统提取'];
        }
        $resArr[] = self::zbCount($resArr);
        return $resArr;
    }

    public
    static function zbCount(
        $resArr = []
    ) {
        $numerator = array_sum(array_column($resArr, 'numerator'));
        $denominator = array_sum(array_column($resArr, 'denominator'));
        $count = [
            'numerator' => $numerator,
            'denominator' => $denominator,
            'res' => $denominator ? round($numerator / $denominator, 4) : 0,
            'time' => '全年',
            'source' => '系统提取'
        ];
        return $count;
    }

    /**
     * @param array $where
     * @return array|int
     * 出院患者手术占比
     */
    public
    function leaveHospital(
        array $where = []
    ) {
        // 出院患者手术台次数，手术判别SSPB是手术1和介入治疗4相加总人数。
        $where['SSPB'] = self::SSPB14;
        $operation = MainOperation::getLeaveHospitalData($where);
        if (!$operation) {
            return 0;
        }

        // 设置手术判别为空，获取所有的同期出院人数
        unset($where['SSPB']);
        $operationTotal = MainOperation::getLeaveHospitalData($where);
        if (!$operationTotal) {
            return 0;
        }

        // 出院比例 = 手术判别是手术和介入治疗相加总人数 / 同期出院总人数
        $res = bcdiv((string) $operation, (string) $operationTotal, 4);

        return ['numerator' => $operation, 'denominator' => $operationTotal, 'res' => $res];
    }

    /**
     * @param array $where
     * @return array|int
     * 出院患者四级手术占比
     */
    public
    function gradeFour(
        array $where = []
    ) {
        // 获取四级手术的信息
        $operationInfo = OperationInfo::getList(['type' => 0], ['ICD9_ID1']);
        if (empty($operationInfo)) {
            return 0;
        }
        $operationIds = array_column($operationInfo, 'ICD9_ID1');

        // 出院患者住院期间实施四级手术和按照四级手术管理的介入诊疗人数之和
        $where['SSPB'] = self::SSPB14;
        $where['ICD9_ID1'] = $operationIds;
        $operation = MainOperation::getLeaveHospitalData($where);
        if (!$operation) {
            return 0;
        }

        // 出院患者手术（含介入）人数。
        unset($where['ICD9_ID1']);
        $operationTotal = MainOperation::getLeaveHospitalData($where);
        if (!$operationTotal) {
            return 0;
        }

        $res = bcdiv((string) $operation, (string) $operationTotal, 4);

        return ['numerator' => $operation, 'denominator' => $operationTotal, 'res' => $res];
    }

    /**
     * @param array $where
     * @return array|int
     * I类切口手术部位感染率
     */
    public
    function infectRadio(
        array $where = []
    ) {
        // INCISION_GRADE_ID 切口愈合等级ID
        // 手术为I类切口且切口愈合等级为“丙级愈合”（代码为3）选项的人数
        $where['INCISION_GRADE_ID'] = '1-3';
        $operation = MainOperation::getLeaveHospitalData($where);
        if (!$operation) {
            return 0;
        }

        // 同期出院患者手术为I类切口人数
        $where['INCISION_GRADE_ID'] = ['1-0', '1-1', '1-2', '1-3'];
        $operationTotal = MainOperation::getLeaveHospitalData($where);
        if (!$operationTotal) {
            return 0;
        }

        $res = bcdiv((string) $operation, (string) $operationTotal, 4);
        return ['numerator' => $operation, 'denominator' => $operationTotal, 'res' => $res];
    }

    /**
     * @param array $where
     * @return array|int
     * 出院患者微创手术占比
     */
    public
    function miniInvasive(
        array $where = []
    ) {
        // 获取微创手术的信息
        $operationInfo = OperationInfo::getList(['type' => 1], ['ICD9_ID1']);
        if (empty($operationInfo)) {
            return 0;
        }
        $operationIds = array_column($operationInfo, 'ICD9_ID1');

        // 出院患者住院期间实施四级手术和按照四级手术管理的介入诊疗人数之和
        $where['ICD9_ID1'] = $operationIds;
        $operation = MainOperation::getLeaveHospitalData($where);
        if (!$operation) {
            return 0;
        }

        // 出院患者手术（含介入）人数。
        unset($where['ICD9_ID1']);
        $where['SSPB'] = self::SSPB14;
        $operationTotal = MainOperation::getLeaveHospitalData($where);
        if (!$operationTotal) {
            return 0;
        }

        $res = bcdiv((string) $operation, (string) $operationTotal, 4);
        return ['numerator' => $operation, 'denominator' => $operationTotal, 'res' => $res];
    }

    // 手术患者并发症发生率▲
    // 8.1.手术患者并发症发生例数
    // 做过手术，出院诊断【3-ICD10_NAME】符合“手术并发症诊断相关名称”且该诊断入院病情【ABC03C】为“无”（代码为4）的病例。
    //（同一次住院就诊期间患有同一疾病或不同疾病施行多次手术者，按1人统计）
    // 8.2.同期出院的手术患者人数
    // 同期出院的手术患者人数是指同期出院患者【SSLX】择期手术人数。
    // | 代码 |名称|
    // | 1  |择期手术|
    // | 2  |急诊手术|
    // | 3  |限期手术|
    // 统计单位以人数计算，总数为实施择期手术和介入治疗人数累加求和。
    // 不包括妊娠、分娩、围产期、新生儿患者。
    //（同一次住院就诊期间患有同一疾病或不同疾病施行多次手术者，按1人统计）
    /**
     * @param array $where
     * @return array|int
     * 手术患者并发症发生率
     */
    public
    function complication(
        array $where = []
    ) {
        // 做过手术，出院诊断【3-ICD10_NAME】符合“手术并发症诊断相关名称”且该诊断入院病情【ABC03C】为“无”（代码为4）的病例。
        $startTime = $where['start_time'];
        $endTime = $where['end_time'];
        $obj = PatientMedicalInfo::query()
            ->Join("main_operation", "main_operation.AAA28", "=", "patient_medical_info.AAA28")
            ->Join("main_diagnosis", "main_diagnosis.AAA28", "=", "patient_medical_info.AAA28")
            ->join('disease_diagnosis_code as ddc', 'ddc.OLD_ICD10_ID1', '=', 'main_diagnosis.ICD10_ID1')
            ->where('patient_medical_info.ABC03C', '=', 4);
        if ($startTime && $endTime) {
            $obj = $obj->whereBetween(Db::raw('UNIX_TIMESTAMP(main_operation.OPE_DATE)'), [$startTime, $endTime]);
        }
        $bfzArr = ['I26', 'I80.2', 'I82.8', 'A40.0', 'A40.9', 'A41.0', 'A41.9', 'T81.411', 'B37.700', 'B49.x00x019', 'T81.0', 'T81.3', 'R96.0', 'R96.1', 'I46.1', 'J95.800x004', 'J96.0', 'J96.1', 'J96.9', 'E89.0', 'E89.9', 'T81.4', 'T81.5', 'T81.6', 'T88.2', 'T88.5', 'J95.1', 'J95.4', 'J95.8', 'J95.9', 'J98.4', 'J15', 'J16', 'J18', 'T81.2', 'N17.0', 'N17.9', 'N99.0', 'K91.0', 'K91.9', 'I97.0', 'I97.1', 'I97.8', 'I97.9', 'G97.0', 'G97.1', 'G97.2', 'G97.8', 'G97.9', 'I60', 'I64', 'H59.0', 'H59.8', 'H59.9', 'H95.0', 'H95.1', 'H95.8', 'H95.9', 'M96.0', 'M96.9', 'N98.0', 'N99.9', 'K11.4', 'T81.2', 'T82.0', 'T82.9', 'T83.0', 'T83.9', 'T84.0', 'T84.9', 'T85.0', 'T85.9', 'T86.0', 'T86.9', 'T87.0', 'T87.6', 'T81.1', 'T81.7', 'T81.8', 'T81.9'];
        $likeRawSql = '(';
        foreach ($bfzArr as $sk => $sv) {
            if (count($bfzArr) == $sk + 1) {
                $likeRawSql .= 'ddc.ICD10_ID1 like "' . $sv . '%"';
            } else {
                $likeRawSql .= 'ddc.ICD10_ID1 like "' . $sv . '%" or ';
            }
        }
        $likeRawSql .= ')';
        $obj = $obj->whereRaw($likeRawSql);
        $operation = $obj->count();

        if (!$operation) {
            return 0;
        }

        // 同期出院的手术患者人数是指同期出院患者【SSLX】择期手术人数,OPE_TYPE对应的是dis中的SSLX
        $obj = PatientMedicalInfo::query()
            ->Join("patient_info", "patient_info.MED_REC_ID", "=", "patient_medical_info.AAA28")
            ->Join("main_operation", "main_operation.AAA28", "=", "patient_medical_info.AAA28")
            ->Join("main_diagnosis", "main_diagnosis.AAA28", "=", "patient_medical_info.AAA28")
            ->join('disease_diagnosis_code as ddc', 'ddc.OLD_ICD10_ID1', '=', 'main_diagnosis.ICD10_ID1')
            ->where('main_operation.OPE_TYPE', '=', 4);
        if ($startTime && $endTime) {
            $obj = $obj->whereBetween(Db::raw('UNIX_TIMESTAMP(main_operation.OPE_DATE)'), [$startTime, $endTime]);
        }
        $obj = $obj->whereRaw('ddc.ICD10_ID1 not like "O%" AND ddc.ICD10_ID1 not like "P%" and (patient_info.AAA04 > 0 or (patient_info.AAA04 = 0 and patient_info.AAA40 > 28))');
        $operationTotal = $obj->count();

        if (!$operationTotal) {
            return 0;
        }

        $res = bcdiv((string) $operation, (string) $operationTotal, 4);

        return ['numerator' => $operation, 'denominator' => $operationTotal, 'res' => $res];
    }


    /**
     * 病理检查记录符合率
     * php artisan command:feeClean
     * update patient_info_target set denominator_bl=0,numerator_bl=0,bl_error="";
     */
    public
    function bingliData(
        array $where = []
    ) {

        $setName = 'quality_zb_bingli';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        $pageSize = 500;
        $startTime = $this->startTime;
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > time()) {
                break;
            }
            var_dump('时间：' . date("Y-m-d H:i:s", $startTime));
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->join('fee_detailed', 'fee_detailed.AAA28', '=', 'patient_info.MED_REC_ID')
                ->where('fee_detailed.is_bingli', '=', 1)
                ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime))
                ->get(['patient_info.id', 'patient_info.AAC01', 'patient_info.MED_REC_ID', 'patient_info.AAA28', 'patient_info.AAB01', 'patient_info.AAC01', 'fee_detailed.FYMC', 'fee_detailed.pre_FYMC'])->toArray();

            $startTime = $entTime;

            $newData = [];
            foreach ($data as $d) {
                $lastId = $d['AAC01'];
                $newData[$d['MED_REC_ID']][] = $d;
            }

            $numerator = [];
            $firstBlKw = ['图文病理报告', '液基细胞学病理检查', '病理液基细胞学(TCT)', '液基薄层细胞制片术'];

            foreach ($newData as $zyh => $data) {

                $isError = 1;
                $sfxmError = '收费项目【';
                $sfxm = [];
                foreach ($data as $d) {
                    $yzb = Yzb::query()
                        ->where('YZMC', '=', $d['pre_FYMC'])
                        ->count();
                    if ($yzb) {
                        $sfxm[] = $d['pre_FYMC'];
                    }
                }
                if ($sfxm) {
                    $sfxm = array_unique($sfxm);
                    $tuyj = array_intersect($firstBlKw, $sfxm);
                    if ($tuyj) {
                        $sfxmError .= implode(',', $tuyj) . '】';
                    } else {
                        $sfxmError .= implode(',', $sfxm) . '】';
                    }
                } else {
                    $sfxmError .= '收费项目匹配失败，默认第一个收费项目：' . $data[0]['pre_FYMC'] . '】';
                }
                $bl_error = $sfxmError;
                $fee = FeeDetailed::query()
                    ->where('AAA28', '=', $data[0]['MED_REC_ID'])
                    ->where('FYMC', 'LIKE', "%液基%")
                    ->count();
                if (!$fee) {
                    $bl_error .= '，收费项目： 【关键字：液基（无）】';
                    $isError = 0;
                } else {
                    $bl_error .= '，收费项目： 【关键字：液基（有）】';
                }

                $aab01 = $data[0]['AAB01'];
                $aac01 = $data[0]['AAC01'];
                // 报告单  备注：报告单在其他数据表
                $bgd = PACS::query()
                    ->where('PACS.JZLSH', '=', $data[0]['AAA28'])
                    ->whereBetween('KDSJ', [$aab01, $aac01])
                    ->where('PACS.YXZD', '<>', "")
                    ->whereRaw('PACS.BGSJ > PACS.KDSJ')
                    ->where('PACS.ExamType', '=', "07")
                    ->get(['YXZD'])->toArray();
                if (!$bgd) {
                    $bl_error .= '，检查报告单： （无）';
                    $isError = 0;
                } else {
                    $bl_error .= '，检查报告单： 【' . $bgd[0]['YXZD'] . '】';
                }

                // 病程
                $shoushu = EMR_BL_BL01::query()
                    ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                    ->where('EMR_BL_BL01.JZHM', '=', $data[0]['MED_REC_ID'])
                    ->where('EMR_BL_BL01.BLLB', '=', 294)
                    ->where('EMR_BL_BL01.BLZT', '!=', 9)
                    ->whereRaw('(EMR_BL_BLXG.HJNR like "%液基%" or EMR_BL_BLXG.HJNR like "%tct%" or EMR_BL_BLXG.HJNR like "%病理%")')
                    ->count();
                if (!$shoushu) {
                    $bl_error .= '，病程记录： 【关键字：“液基” 或 “tct”或“病理”（无）】';
                    $isError = 0;
                } else {
                    $bl_error .= '，病程记录： 【关键字：“液基” 或 “tct”或“病理”（有）】';
                }
                if ($isError) {
                    $numerator[] = ['ZYH' => $d['MED_REC_ID'], 'numerator_bl' => $isError, 'bl_error' => $bl_error];
                    continue;
                }
                // 两组条件，上面逻辑符合或者下面的逻辑符合
                $isError = 1;
                // 检查报告单
                if (!$bgd) {
                    $isError = 0;
                }
                // 手术记录
                $shoushu = EMR_BL_BL01::query()
                    ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                    ->where('EMR_BL_BL01.JZHM', '=', $data[0]['MED_REC_ID'])
                    ->where('EMR_BL_BL01.BLLB', '=', 303)
                    ->where('EMR_BL_BL01.BLZT', '!=', 9)
                    ->whereRaw('(EMR_BL_BLXG.HJNR like "%病理%" or EMR_BL_BLXG.HJNR like "%取%")')
                    ->count();
                if ($shoushu) {
                    $bl_error .= '，手术记录： 【关键字：“病理” 或 “取”（有）】';
                }

                // 病程记录
                $bingchengBl = EMR_BL_BL01::query()
                    ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                    ->where('EMR_BL_BL01.JZHM', '=', $data[0]['MED_REC_ID'])
                    ->where('EMR_BL_BL01.BLLB', '=', 294)
                    ->whereRaw('(EMR_BL_BLXG.HJNR like "%病理%")')
                    ->count();
                if ($bingchengBl) {
                    $bl_error .= '，病程记录：【关键字：“病理”（有）】';
                } else {
                    // 病程记录
                    $bingchengQ = EMR_BL_BL01::query()
                        ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                        ->where('EMR_BL_BL01.JZHM', '=', $data[0]['MED_REC_ID'])
                        ->where('EMR_BL_BL01.BLLB', '=', 294)
                        ->whereRaw('(EMR_BL_BLXG.HJNR like "%取%")')
                        ->count();
                    if ($bingchengQ) {
                        $bl_error .= '，病程记录：【关键字：“取”（有）】';
                    } else {
                        $bl_error .= '，病程记录：【关键字：“病理”或“取”（无）】';
                        $isError = 0;
                    }
                }
                var_dump($d['MED_REC_ID']);
                $numerator[] = ['ZYH' => $d['MED_REC_ID'], 'numerator_bl' => $isError, 'bl_error' => $bl_error];
            }

            if ($numerator) {
                foreach ($numerator as $d) {
                    $d['ZYH'] = (string) $d['ZYH'];
                    PatientInfoTarget::query()->updateOrInsert(
                        ['ZYH' => $d['ZYH']],
                        ['denominator_bl' => 1, 'numerator_bl' => $d['numerator_bl'], 'bl_error' => $d['bl_error']]
                    );
                }
            }
        }
        Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
    }

    /**
     * 抗菌药物使用记录符合率
     */
    public
    function kjyw(
        $type = 0
    ) {

        $dateType = [
            31 => ['numerator_ct', 'denominator_ct'],
            32 => ['numerator_bl', 'denominator_bl'],
            33 => ['numerator_xjpy', 'denominator_xjpy'],
            41 => ['numerator_kjyw', 'denominator_kjyw'],
            42 => ['numerator_exzlhxzl', 'denominator_exzlhxzl'],
            43 => ['numerator_exzlfszl', 'denominator_exzlfszl'],
            44 => ['numerator_operation', 'denominator_operation'],
            45 => ['numerator_zrw', 'denominator_zrw'],
            57 => ['numerator_bhlbl', 'denominator_bhlbl'],
            46 => ['numerator_lcyx', 'denominator_lcyx'],
        ];

        $year = $where['year'] ?? date('Y');


        $resArr = [];
        for ($i = 1; $i <= 12; $i++) {
            $startTime = strtotime($year . '-' . $i . '-01');
            $t = date('t', $startTime);
            $endTime = $startTime + $t * 3600 * 24;
            // 所有符合条件的病例信息
            $denominator = PatientInfo::query()
                ->join('patient_info_target as target', 'target.ZYH', '=', 'patient_info.MED_REC_ID')
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->where('target.denominator_kjyw', '=', 1)->count();

            if (!$denominator) {
                $resArr[] = ['numerator' => 0, 'denominator' => 0, 'res' => 0, 'time' => $year . '-' . sprintf('%02s', $i), 'source' => '系统提取'];
                continue;
            }
            // 所有符合条件的病例信息
            $numerator = $bgdObj = PatientInfo::query()
                ->join('patient_info_target as target', 'target.ZYH', '=', 'patient_info.MED_REC_ID')
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->where('target.numerator_kjyw', '=', 1)->count();

            $res = bcdiv((string) $numerator, (string) $denominator, 4);
            $resArr[] = ['numerator' => $numerator, 'denominator' => $denominator, 'res' => $res, 'time' => $year . '-' . sprintf('%02s', $i), 'source' => '系统提取'];
        }
        $resArr[] = self::zbCount($resArr);
        return $resArr;
    }


    /**
     * 抗菌药物病例的数据清洗，将含有抗菌药物的病例信息打上标识
     * update patient_info_target set denominator_kjyw=0,numerator_kjyw=0,kjyw_error="";
     */
    public function kjywData()
    {
        $setName = 'quality_zb_kjyw';
        $bagl_11 = ZbBagl::getFirstById(11, true);
        $ruleMap2053 = RuleWordMap::query()->where('id', '=', 2053)->value('keyword');
        $ruleMap2053 = explode(',', $ruleMap2053);
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        $pageSize = 500;
        $startTime = $this->startTime;
        $bl01esService = new ElasticsearchService($bagl_11->table_name); //gid 11

        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > time()) {
                break;
            }
            var_dump('时间：' . date("Y-m-d H:i:s", $startTime));
            // 所有符合条件的病例信息
            // SELECT * FROM `patient_info` as a left join yzb as b on a.MED_REC_ID=b.ZYH
            // where a.denominator_kjyw=0 and b.is_has_kjyw=1
            $data = PatientInfo::query()
                ->Join('yzb', 'patient_info.ZYH_ID', '=', 'yzb.ZYH_ID')
                ->where('yzb.is_has_kjyw', '=', 1)
                ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime))
                ->get(['patient_info.AAC01', 'patient_info.id', 'yzb.XMLB', 'yzb.YZQX', 'yzb.ZYH', 'yzb.YZMC', 'yzb.KZSJ', 'yzb.kjyw_name', 'yzb.JLDW', 'yzb.YCJL', 'yzb.SYPC'])->toArray();

            $startTime = $entTime;

            $newData = [];
            foreach ($data as $d) {
                $lastId = $d['AAC01'];
                $uniqueId = md5($d['ZYH'] . $d['YZMC'] . $d['JLDW'] . $d['YCJL'] . $d['SYPC']);
                $newData[$d['ZYH']][$uniqueId] = $d;
            }
            $fenzi = [];
            foreach ($newData as $med => $data) {
                $flag = true;
                $kjywError = [];
                $XMLBData = [];
                foreach ($data as $y) {
                    if ($y['XMLB'] == 9 && in_array($y['YZMC'], $XMLBData)) {
                        continue;
                    }
                    $XMLBData[] = $y['YZMC'];
                    $kjyw_name = $y['KZSJ'] . '-' . $y['YZMC'] . ($y['YZQX'] == 1 ? '（长期医嘱）' : '') . ($y['YZQX'] == 2 ? '（临时医嘱）' : '') . '/' . $y['YCJL'] . $y['JLDW'] . '/' . $y['SYPC'];

                    $should = [];
                    $should[] = ['match_phrase' => ["HJNR" => $y['kjyw_name']]];

                    foreach ($ruleMap2053 as $rule) {
                        $should[] = ['match_phrase' => ["HJNR" => $rule]];
                    }

                    $bl01must = [
                        ['term' => ["JZHM" => $y['ZYH']]],
                        ['term' => ["BLLB" => 294]],
                        [
                            'bool' => [
                                'should' => $should,
                                'minimum_should_match' => 1
                            ]
                        ]
                    ];
                    $params = $bl01esService->clearMust()
                        ->queryByMustNot(['term' => ['BLZT' => 9]])
                        ->queryByMustBatch($bl01must)->source(['BLBH', 'BLMC', $bagl_11->table_field])->getParams(); //gid 11
                    $bl01Res = app('es')->search($params);
                    $bl01Res = $bl01esService->getDataByEs($bl01Res);
                    var_dump($bl01Res);
                    if (!$bl01Res[1]) {
                        $flag = false;
                        $kjywError[] = '医嘱【' . $kjyw_name . '】' . '病程记录【 无  ' . $y['kjyw_name'] . '】';
                    } else {
                        $sTime = strtotime($y['KZSJ']);
                        $ZXSJTime = strtotime($bl01Res[0][0][$bagl_11->table_field]);
                        $eTime = $sTime + 24 * 3600;
                        if ($ZXSJTime > $eTime) {
                            $flag = false;
                            $kjywError[] = '医嘱【' . $kjyw_name . '】病程【' . $y['YZMC'] . '】但是病程记录【 执行时间超过24小时 】';
                        } else {
                            $kjywError[] = '医嘱有【' . $kjyw_name . '】' . '病程记录有【' . $bl01Res[0][0][$bagl_11['table_field']] . ' - ' . $y['kjyw_name'] . '】';
                        }
                    }
                }
                $kjywErrorContent = '';
                foreach ($kjywError as $k => $item) {
                    $kjywErrorContent .= ($k + 1) . '：' . $item;
                }
                var_dump($med);
                $fenzi[] = ['ZYH' => (string) $med, 'numerator_kjyw' => ($flag === true ? 1 : 0), 'kjyw_error' => $kjywErrorContent];
            }

            if ($fenzi) {
                foreach ($fenzi as $d) {
                    $d['ZYH'] = (string) $d['ZYH'];
                    $has = PatientInfoTarget::query()->where('ZYH', '=', $d['ZYH'])->count();
                    if ($has) {
                        PatientInfoTarget::query()->where('ZYH', '=', $d['ZYH'])->update([
                            'denominator_kjyw' => 1,
                            'numerator_kjyw' => $d['numerator_kjyw'],
                            'kjyw_error' => $d['kjyw_error']
                        ]);
                    } else {
                        PatientInfoTarget::query()->insert(
                            [
                                'ZYH' => $d['ZYH'],
                                'denominator_kjyw' => 1,
                                'numerator_kjyw' => $d['numerator_kjyw'],
                                'kjyw_error' => $d['kjyw_error']
                            ]
                        );
                    }
                }
            }
        }
        Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
        var_dump('kjywData_end');
    }

    /**
     * 恶性肿瘤化学治疗记录符合率，将含有化学药品的病例信息打上标识
     * update patient_info_target set denominator_exzlhxzl=0,numerator_exzlhxzl=0,exzlhxzl_error="";
     */
    public
    function exzlhxzlData()
    {
        $setName = 'quality_zb_exzlhxzl';
        $bagl_11 = ZbBagl::getFirstById(11, true);
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        $pageSize = 500;
        $bl01esService = new ElasticsearchService($bagl_11->table_name); //gid 11
        $startTime = $this->startTime;
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > time()) {
                break;
            }
            var_dump('时间：' . date("Y-m-d H:i:s", $startTime));
            // 所有符合条件的病例信息
            $data = $bgdObj = PatientInfo::query()
                ->Join('yzb', 'patient_info.MED_REC_ID', '=', 'yzb.ZYH')
                ->where('yzb.is_has_hlyw', '=', 1)
                ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime))
                ->get(['patient_info.AAC01', 'patient_info.id', 'yzb.YZQX', 'yzb.ZYH', 'yzb.YZMC', 'yzb.KZSJ', 'yzb.hlyw_name', 'yzb.SYPC', 'yzb.YCJL'])->toArray();

            $startTime = $entTime;

            $newData = [];
            foreach ($data as $d) {
                $lastId = $d['AAC01'];
                $uniqueId = md5($d['ZYH'] . $d['YZMC'] . $d['YCJL'] . $d['SYPC']);
                $newData[$d['ZYH']][$uniqueId] = $d;
            }
            $fenzi = [];
            foreach ($newData as $med => $data) {
                $flag = true;
                $exzlhxzlError = [];
                foreach ($data as $y) {
                    $hlyw_name = $y['KZSJ'] . '-' . $y['YZMC'] . ($y['YZQX'] == 1 ? '（长期医嘱）' : '') . ($y['YZQX'] == 2 ? '（临时医嘱）' : '') . '/' . $y['SYPC'] . '/' . $y['YCJL'];

                    $bl01must = [
                        ['term' => ["JZHM" => $y['ZYH']]],
                        ['term' => ["BLLB" => 294]],
                        ['match_phrase' => ["HJNR" => $y['hlyw_name']]],
                    ];
                    $params = $bl01esService->clearMust()
                        ->queryByMustNot(['term' => ['BLZT' => 9]])
                        ->queryByMustBatch($bl01must)->source(['BLBH', $bagl_11->table_field, 'BLMC'])->getParams(); //id 11
                    $bl01Res = app('es')->search($params);
                    $bl01Res = $bl01esService->getDataByEs($bl01Res);
                    var_dump($bl01Res);

                    if (!$bl01Res[1]) {
                        $flag = false;
                        $exzlhxzlError[] = '医嘱有【' . $hlyw_name . '】' . '病程记录【 无  ' . $y['hlyw_name'] . '】';
                    } else {
                        $sTime = strtotime($y['KZSJ']);
                        $ZXSJTime = strtotime($bl01Res[0][0][$bagl_11->table_field]);
                        $eTime = $sTime + 24 * 3600;
                        if ($ZXSJTime > $eTime) {
                            $flag = false;
                            $exzlhxzlError[] = '医嘱【' . $hlyw_name . '】' . '病程记录【 执行时间超过24小时 】';
                        } else {
                            $exzlhxzlError[] = '医嘱有【' . $hlyw_name . '】病程有【' . $bl01Res[0][0][$bagl_11->table_field] . ' - ' . $y['hlyw_name'] . '】';
                        }
                    }
                }

                $exzlhxzlErrorContent = '';
                foreach ($exzlhxzlError as $k => $item) {
                    $exzlhxzlErrorContent .= ($k + 1) . '：' . $item;
                }
                var_dump($med);
                $fenzi[] = ['ZYH' => $med, 'numerator_exzlhxzl' => ($flag === true ? 1 : 0), 'exzlhxzl_error' => $exzlhxzlErrorContent];
            }

            if ($fenzi) {
                foreach ($fenzi as $d) {
                    $d['ZYH'] = (string) $d['ZYH'];
                    $has = PatientInfoTarget::query()->where('ZYH', '=', $d['ZYH'])->count();
                    if ($has) {
                        PatientInfoTarget::query()->where('ZYH', '=', $d['ZYH'])->update([
                            'denominator_exzlhxzl' => 1,
                            'numerator_exzlhxzl' => $d['numerator_exzlhxzl'],
                            'exzlhxzl_error' => $d['exzlhxzl_error']
                        ]);
                    } else {
                        PatientInfoTarget::query()->insert(
                            [
                                'ZYH' => $d['ZYH'],
                                'denominator_exzlhxzl' => 1,
                                'numerator_exzlhxzl' => $d['numerator_exzlhxzl'],
                                'exzlhxzl_error' => $d['exzlhxzl_error']
                            ]
                        );
                    }
                }
            }
        }
        Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
    }

    /**
     * 恶性肿瘤放射治疗记录符合率
     */
    public function exzlfszlData(array $where = [])
    {
        $setName = 'quality_zb_exzlfszl';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        $pageSize = 100;
        $startTime = $this->startTime;
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > time()) {
                break;
            }
            var_dump('时间：' . date("Y-m-d H:i:s", $startTime));
            // 所有符合条件的病例信息
            $data = $bgdObj = PatientInfo::query()
                ->Join('yzb', 'patient_info.MED_REC_ID', '=', 'yzb.ZYH')
                ->where('yzb.is_fangliao', '=', 1)
                ->where('patient_info.AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                ->where('patient_info.AAC01', '<=', date("Y-m-d H:i:s", $entTime))
                ->get(['patient_info.AAC01', 'patient_info.id', 'MED_REC_ID', 'ZYH', 'YZMC', 'KZSJ'])->toArray();
            $startTime = $entTime;

            $allData = $fenzi = [];
            foreach ($data as $y) {
                $lastId = $y['AAC01'];
                var_dump($y['id']);
                $yzmc = $y['YZMC'] ?: '';
                $yzmc = str_replace(' ', '', $yzmc);
                if (empty($yzmc)) {
                    continue;
                }
                preg_match_all("/放疗(\d+)次/", $yzmc, $res);
                if (!$res[1]) {
                    continue;
                }
                // 如果医嘱中有放疗*次，则记录下来，负责分母的要求
                $allData[] = ['ZYH' => $y['ZYH'], 'exzlfszl_error' => '医嘱【放疗*次】'];

                // 病程记录

                $esService = new ElasticsearchService('bl01_202303');
                $mustBl = [
                    ["term" => ['JZHM' => $y['ZYH']]],
                    ["term" => ['BLLB' => 294]],
                    ["match_phrase" => ['HJNR' => "放疗"]]
                ];
                $notMust = ['term' => ["BLZT" => 9]];
                $params = $esService->clearMust()
                    ->queryByMustBatch($mustBl)
                    ->queryByMustNot($notMust)
                    ->paginate(1, 1000)
                    ->getParams();
                $res = app('es')->search($params);
                $res = $esService->getDataByEs($res);

                if ($res[1]) {
                    $fenzi[] = ['ZYH' => $y['ZYH'], 'numerator_exzlfszl' => 1, 'exzlfszl_error' => '医嘱【放疗*次】病程记录【有放疗关键字】'];
                } else {
                    $fenzi[] = ['ZYH' => $y['ZYH'], 'numerator_exzlfszl' => 0, 'exzlfszl_error' => '医嘱【放疗*次】病程记录【无放疗关键字】'];
                }
            }

            if ($allData) {
                foreach ($allData as $d) {
                    $d['ZYH'] = (string) $d['ZYH'];
                    PatientInfoTarget::query()->updateOrInsert(['ZYH' => $d['ZYH']], ['denominator_exzlfszl' => 1, 'exzlfszl_error' => $d['exzlfszl_error']]);
                }
            }
            if ($fenzi) {
                foreach ($fenzi as $d) {
                    $d['ZYH'] = (string) $d['ZYH'];
                    PatientInfoTarget::query()->updateOrInsert(['ZYH' => $d['ZYH']], ['numerator_exzlfszl' => $d['numerator_exzlfszl'], 'exzlfszl_error' => $d['exzlfszl_error']]);
                }
            }
        }
        Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
    }


    /**
     * 清洗医嘱本中医嘱名称是否包含抗菌药物或者化疗药物
     * update `yzb` set is_has_kjyw=0,is_has_hlyw=0,kjyw_name="",hlyw_name="";
     */
    public static function filterField($zyh = '')
    {
        $setName = 'qx_YzbClean_last_id';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content') ?: 0;
        $pageSize = 1000; // 每次处理1000条记录

        // 获取额外的匹配条件
        $ruleWord = RuleWordMap::query()->where('id', '=', 2055)->first();
        $additionalConditions = [];
        if ($ruleWord && $ruleWord->keyword) {
            $additionalConditions = array_map('trim', explode(',', $ruleWord->keyword));
        }

        // 获取药物列表
        $medicianlKjyw = MedicinalInfo::query()->where(['type' => 1])->orderBy('id', 'asc')->pluck('name')->toArray(); // 抗菌药物
        $medicianlHlyw = MedicinalInfo::query()->where(['type' => 2])->orderBy('id', 'asc')->pluck('name')->toArray(); // 化疗药物

        //获取yzb最大id
        $maxId = Yzb::query()->max('id');

        while (true) {
            // 获取一批数据
            $query = Yzb::query();

            if ($zyh) {
                $query->where('ZYH', $zyh);
            } else {
                $query->where('id', '>', $lastId)
                    ->where('id', '<=', $lastId + $pageSize);
            }

            $data = $query->orderBy('id', 'asc')->get();
            if (empty($data)) {
                break;
            }
            // 处理抗菌药物
            foreach ($data as $record) {
                $updates = [];

                // 检查抗菌药
                foreach ($medicianlKjyw as $h) {
                    if (
                        strpos($record->YZMC, $h) !== false
                        && $record->YYSX != 4
                        && (strpos($record->YZMC, '皮试') === false)
                        && !self::hasExcludedConditions($record->YZMC, $additionalConditions)
                    ) {
                        $updates['is_has_kjyw'] = 1;
                        $updates['kjyw_name'] = $h;
                        $kjjb = 0; 
                        $typk = self::getYkTYPK($h);
                        if (!empty($typk)) {
                            foreach ($typk as $t) {
                                $kjjb = 9; //未知
                                //胶囊、注射液、片、注射用、颗粒、混悬液、分散片、缓释片
                                $t['YPMC'] = str_replace('注射用', '', $t['YPMC']);
                                $t['YPMC'] = str_replace('胶囊', '', $t['YPMC']);
                                $t['YPMC'] = str_replace('片', '', $t['YPMC']);
                                $t['YPMC'] = str_replace('注射液', '', $t['YPMC']);
                                $t['YPMC'] = str_replace('颗粒', '', $t['YPMC']);
                                $t['YPMC'] = str_replace('混悬液', '', $t['YPMC']);
                                $t['YPMC'] = str_replace('分散片', '', $t['YPMC']);
                                $t['YPMC'] = str_replace('缓释片', '', $t['YPMC']);
                                if (strpos($record->YZMC, $t['YPMC']) !== false) {
                                    $kjjb = $t['KJJB']; //找到了就是那个抗菌级别
                                    break;
                                }
                            }
                        }
                        $updates['KJJB'] = $kjjb;
                        break;
                    }
                }

                // 检查化疗药
                foreach ($medicianlHlyw as $h) {
                    if (
                        strpos($record->YZMC, $h) !== false
                        && $record->YYSX != 4
                        && !self::hasExcludedConditions($record->YZMC, $additionalConditions)
                    ) {
                        $updates['is_has_hlyw'] = 1;
                        $updates['hlyw_name'] = $h;
                        break;
                    }
                }

                // 检查放疗
                if (strpos($record->YZMC, '放射治疗') !== false || strpos($record->YZMC, '放疗') !== false) {
                    $updates['is_fangliao'] = 1;
                }

                // 如果有更新，执行更新
                if (!empty($updates)) {
                    Yzb::query()->where('id', $record->id)->update($updates);
                }
            }

            // 处理长期医嘱和临时医嘱的对比
            $data = Yzb::query()->where('ZYH', $zyh)->orderBy('KZSJ', 'asc')->get();
            self::processOrderComparison($data, 'kjyw');
            self::processOrderComparison($data, 'hlyw');

            // 更新进度
            $lastId = $lastId + $pageSize;

            if (!$zyh) {
                Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
            } else {
                break;
            }
        }

        return true;
    }

    /**
     * 根据medicinal_info表的名称获取药品库中的抗菌药物的药品名称和抗菌等级--注意：可能多个
     * @param mixed $kjyw_name
     * @return array
     */
    public static function getYkTYPK($kjyw_name)
    {
        $yk_typk = YK_TYPK::query()->where('KSBZ', '>', 0)
            ->where('YPMC', 'like', '%' . $kjyw_name . '%')
            ->distinct()->get(['YPMC', 'KJJB'])->toArray();
        return $yk_typk ?? [];
    }

    private static function hasExcludedConditions($yzmc, $conditions)
    {
        foreach ($conditions as $condition) {
            if (strpos($yzmc, $condition) !== false) {
                return true;
            }
        }
        return false;
    }

    private static function processOrderComparison($data, $type)
    {
        // 分离长期医嘱和临时医嘱
        $cqyzArr = [];
        $lsyzArr = [];

        foreach ($data as $record) {
            $field = "is_has_{$type}";
            $nameField = "{$type}_name";

            if ($record->$field == 1) {
                if ($record->YZQX == '1') {
                    $cqyzArr[$record->ZYH][] = [
                        'id' => $record->id,
                        'name' => $record->$nameField,
                        'KZSJ' => $record->KZSJ,
                        'TZSJ' => $record->TZSJ,
                        'YCJL' => $record->YCJL,
                        'JLDW' => $record->JLDW
                    ];
                } elseif ($record->YZQX == '2') {
                    $lsyzArr[$record->ZYH][] = [
                        'id' => $record->id,
                        'name' => $record->$nameField,
                        'KZSJ' => $record->KZSJ,
                        'YCJL' => $record->YCJL,
                        'JLDW' => $record->JLDW
                    ];
                }
            }
        }

        // 比较并更新
        foreach ($cqyzArr as $zyh => $cqyzList) {
            if (!empty($lsyzArr[$zyh])) {
                foreach ($cqyzList as $cqyz) {
                    foreach ($lsyzArr[$zyh] as $lsyz) {
                        if (
                            $cqyz['name'] == $lsyz['name']
                            && $cqyz['YCJL'] == $lsyz['YCJL']
                            && $cqyz['JLDW'] == $lsyz['JLDW']
                            && strtotime($cqyz['KZSJ']) <= strtotime($lsyz['KZSJ'])
                            && strtotime($cqyz['TZSJ']) >= strtotime($lsyz['KZSJ'])
                        ) {
                            Yzb::query()
                                ->where('id', $lsyz['id'])
                                ->update(["is_has_{$type}" => 0]);
                        }
                    }
                }
            }
        }

        foreach ($lsyzArr as $zyh => $lsyzList) {
            $count = count($lsyzList);
            if ($count <= 1) {
                continue;
            }
            for ($i = 1; $i < $count; $i++) {
                $prev = $lsyzList[$i - 1];
                $curr = $lsyzList[$i];
                if (
                    $curr['name'] == $prev['name'] &&
                    $curr['YCJL'] == $prev['YCJL'] &&
                    $curr['JLDW'] == $prev['JLDW']
                ) {
                    Yzb::query()
                        ->where('id', $curr['id'])
                        ->update(["is_has_{$type}" => 0]);
                }
            }
        }
    }

    public function cleanPreFymc()
    {
        $setName = 'qx_cleanPreFymc_last_id';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;

        $maxId = FeeDetailed::query()->max('id');
        while (true) {
            $end = $lastId + 10000;
            echo $lastId . "\r\n";
            DB::update("update `fee_detailed` set pre_FYMC=SUBSTRING_INDEX(FYMC,'/', 1) where id between {$lastId} and {$end}");
            if ($end > $maxId) {
                $lastId = $maxId;
                break;
            }
            $lastId = $end;
        }
        Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
    }

    /**
     * 清洗费用明细中的病理费用
     * update `fee_detailed` set is_clean=0,is_bingli=0;
     */
    public
    function feeClean()
    {
        $blKeyword = [
            "病理费",
            "病理活检标本",
            "病理单切标本",
            "病理大标本",
            "病理快速切片诊断",
            "病理穿刺标本",
            "病理组织化学",
            "病理免疫荧光",
            "病理免疫组化",
            "病理DNA探针(分子病理诊断)",
            "RDNA探针(分子病理诊断)",
            "病理DNA倍体分析",
            "尸体病理诊断",
            "病理疑难会诊",
            "病理普通会诊",
            "病细胞学诊断",
            "显微图像肿瘤细胞分析",
            "液基细胞学病理检查",
            "肾穿刺病理费",
            "病理脱钙标本",
            "病理蜡块增收",
            "病理快速增收",
            "病理图文报告",
            "病理细针穿刺细胞检查",
            "病理液基细胞学(TCT)",
            "尸体解剖与防腐处理",
            "尸检病理诊断",
            "儿童及胎儿尸检病理诊断",
            "尸体化学防腐处理",
            "细胞病理学检查与诊断",
            "体液细胞学检查与诊断",
            "图文病理报告",
            "拉网细胞学检查与诊断",
            "细针穿刺细胞学检查与诊断",
            "脱落细胞学检查与诊断",
            "细胞学计数",
            "组织病理学检查与诊断",
            "穿刺组织活检检查与诊断",
            "蜡块增加",
            "涂片增加",
            "内镜组织活检与诊断",
            "局部切除组织活检检查与诊断",
            "骨髓组织活检检查与诊断",
            "手术标本检查与诊断",
            "塑料包埋",
            "截肢标本病理检查与诊断",
            "不脱钙直接切片",
            "牙齿及骨骼磨片诊断（不脱钙）",
            "牙齿及骨骼磨片诊断（脱钙）",
            "颌骨样本及牙体周样本诊断",
            "冰冻切片与快速石蜡切片检查与诊断",
            "冰冻切片检查与诊断",
            "特异性感染标本",
            "部位增加",
            "快速石蜡切片检查与诊断",
            "特殊染色诊断技术",
            "特殊染色及酶组织化学染色诊断",
            "免疫组织化学染色诊断",
            "免疫荧光染色诊断",
            "电镜病理诊断",
            "普通投射电镜检查与诊断",
            "免疫电镜检查与诊断",
            "扫描电镜检查与诊断",
            "分子病理学诊断技术",
            "原位杂交技术",
            "印迹杂交技术",
            "脱氧核糖核酸（DNA）测序",
            "基因芯片技术",
            "其它病理技术项目",
            "病理体视学检查与图像分析",
            "宫颈细胞学计算机辅助诊断",
            "膜式病变细胞采集技术",
            "液基薄层细胞制片术",
            "病理大体标本摄影",
            "显微摄影术",
            "疑难病理会诊",
            "普通病理会诊",
            "手术标本检查与诊断（单切）",
            "根治",
            "手术标本检查与诊断(单切)",
            "手术标本检查与诊断(根治)",
            "内镜组织活检与诊断（增加部位加收）",
            "人乳头瘤病毒（HPV)核酸检测",
            "荧光原位杂交（FISH）",
            "荧光原位杂交（FISH）（三项及以上）",
            "免疫组织化学染色诊断（液盖膜涡流混均加收）",
            "儿童及胎儿尸检病理诊断",
            "儿童及胎儿尸检病理诊断（开颅加收）",
            "尸检病理诊断（开颅加收）",
            "尸检病理诊断（传染病和特异性感染病尸加收）",
            "宫颈细胞学计算机辅导诊断",
            "细胞蜡块诊断",
            "手术标本检查与诊断（内镜切除）",
            "液基薄层细胞制片术（超过两片每片加收）",
            "纤维喉镜检查（床旁检查加收）",
            "全自动病理组织特殊染色"
        ];
        $blKeyword = array_unique($blKeyword);

        $setName = 'qx_feeClean_last_id';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;

        $maxId = FeeDetailed::query()->max('id');
        foreach ($blKeyword as $item) {

            $limit = 10000;
            $start = $lastId;
            while (true) {
                $end = $start + $limit;
                DB::update("update `fee_detailed` set is_bingli=1 where pre_FYMC='" . $item . "' and id between {$start} and {$end}");
                if ($end > $maxId) {
                    break;
                }
                $start = $end;
            }
        }
        Setting::query()->where('name', '=', $setName)->update(['content' => $maxId]);
    }


    /**
     * 清洗医嘱本中有手术数据信息的病例
     */
    public
    static function operationClean()
    {
        $setName = 'qx_operationClean_last_id';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;

        // 清洗医嘱本中有手术数据信息的病例
        $sql = 'SELECT id FROM `yzb` WHERE id>' . $lastId . ' AND is_operation=0 AND YZMC LIKE "拟%" and  YZMC LIKE "%年%" AND YZMC LIKE "%月%" AND  YZMC LIKE "%日%" AND ZYH not in (SELECT ZYH FROM yzb WHERE YZMC LIKE "%取消手术%" AND  YZMC LIKE "%手术取消%")';
        $res = DB::select($sql);
        if ($res) {
            $YzbIds = array_column($res, 'id');
            $chunkIds = array_chunk($YzbIds, 1000);
            foreach ($chunkIds as $ids) {
                DB::table('yzb')->whereIn('id', $ids)->update(['is_operation' => 1]);
                var_dump(count($ids));
            }
            $lastId = array_pop($YzbIds);
            Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
        }
    }

    /**
     * @param array $where
     * @return array
     * @throws Exception
     * 指标详细数据列表
     *
     */
    public
    function getZbList(
        $where = []
    ) {
        $time = $where['time'];
        $id = $where['id'];
        $isError = $where['is_error'] ?? 200;
        $startTimeInt = strtotime($time . '-01');
        $startTime = date("Y-m-d H:i:s", $startTimeInt);
        $t = date('t', $startTimeInt);
        $endTime = $startTimeInt + $t * 3600 * 24;
        $endTime = date("Y-m-d H:i:s", $endTime);

        if ($time == '全年') {
            $startTime = $where['year'] . '-01-01 00:00:00';
            $endTime = $where['year'] . '-12-31 23:59:59';
        }

        //特殊处理 心梗 脑梗 出院时间筛选
        if ($where['start_time']) {
            $startTime = $where['start_time'] . ' 00:00:00';
            $endTime = $where['end_time'] . ' 23:59:59';
        }

        $dateType = [
            31 => ['numerator_ct', 'denominator_ct', 'ct_error'],
            32 => ['numerator_bl', 'denominator_bl', 'bl_error'],
            33 => ['numerator_xjpy', 'denominator_xjpy', 'xjpy_error'],
            41 => ['numerator_kjyw', 'denominator_kjyw', 'kjyw_error'],
            42 => ['numerator_exzlhxzl', 'denominator_exzlhxzl', 'exzlhxzl_error'],
            43 => ['numerator_exzlfszl', 'denominator_exzlfszl', 'exzlfszl_error'],
            44 => ['numerator_operation', 'denominator_operation', 'operation_error'],
            45 => ['numerator_zrw', 'denominator_zrw', 'zrw_name'],
            57 => ['numerator_bhlbl', 'denominator_bhlbl', 'bhlbl_content'],
            46 => ['numerator_lcyx', 'denominator_lcyx', 'lcyx_error'],
            23 => ['numerator_cyjl', 'denominator_cyjl', 'cyjl_error'],
            24 => ['numerator_basy', 'denominator_basy', 'basy_error'],
            48 => ['numerator_hzqjjl', 'denominator_hzqjjl', 'hzqjjl_error'],
            21 => ['numerator_ryjl', 'denominator_ryjl', 'ryjl_error'],
            34 => ['numerator_xjpy1', 'denominator_xjpy1', 'xjpy1_error'],
            22 => ['numerator_operateCompletionRate', 'denominator_operateCompletionRate', 'operateCompletionRate_error'],
            51 => ['numerator_cyhzgd', 'denominator_cyhzgd', 'cyhzgd_error'],
            47 => ['numerator_chafangCompletionRate', 'denominator_chafangCompletionRate', 'chafangCompletionRate_error'],
            53 => ['numerator_zyzdtx', 'denominator_zyzdtx', 'zyzdtx_error'],
            54 => ['numerator_zyzdbm', 'denominator_zyzdbm', 'zyzdbm_error'],
            55 => ['numerator_zysstx', 'denominator_zysstx', 'zysstx_error'],
            56 => ['numerator_zyssbm', 'denominator_zyssbm', 'zyssbm_error'],
            11 => ['numerator_public_cyjl', '', ''],
            13 => ['numerator_public_cyjl', '', ''],
            58 => ['numerator_zqtysgfqs', 'denominator_zqtysgfqs', 'zqtysgfqs_error'],
            49 => ['numerator_hzqjcgl', 'denominator_hzqjcgl', 'hzqjcgl_error'],
            52 => ['numerator_cyhzgdl', 'denominator_cyhzgdl', 'cyhzgdl_error'],
            59 => ['numerator_A', 'denominator_A', 'score'],
            60 => ['numerator_ngzb', 'denominator_ngzb', 'ngzb_describe'],
            61 => ['numerator_xgzb', 'denominator_xgzb', 'xgzb_describe'],
            62 => ['numerator_ngrszb', 'denominator_ngrszb', 'ngrszb_describe'],
        ];

        $dateTypeMap = [
            31 => ['numerator_ct', 'denominator_ct'],
            32 => ['numerator_bl', 'denominator_bl'],
            33 => ['numerator_xjpy', 'denominator_xjpy'],
            41 => ['numerator_kjyw', 'denominator_kjyw'],
            42 => ['numerator_exzlhxzl', 'denominator_exzlhxzl'],
            43 => ['numerator_exzlfszl', 'denominator_exzlfszl'],
            44 => ['numerator_operation', 'denominator_operation'],
            45 => ['numerator_zrw', 'denominator_zrw'],
            46 => ['numerator_lcyx', 'denominator_lcyx'],
            57 => ['numerator_bhlbl', 'denominator_bhlbl'],
            23 => ['numerator_cyjl', 'denominator_cyjl'],
            24 => ['numerator_basy', 'denominator_basy'],
            48 => ['numerator_hzqjjl', 'denominator_hzqjjl'],
            21 => ['numerator_ryjl', 'denominator_ryjl'],
            34 => ['numerator_xjpy1', 'denominator_xjpy1'],
            22 => ['numerator_operateCompletionRate', 'denominator_operateCompletionRate'],
            51 => ['numerator_cyhzgd', 'denominator_cyhzgd'],
            47 => ['numerator_chafangCompletionRate', 'denominator_chafangCompletionRate'],
            53 => ['numerator_zyzdtx', 'denominator_zyzdtx'],
            54 => ['numerator_zyzdbm', 'denominator_zyzdbm'],
            55 => ['numerator_zysstx', 'denominator_zysstx'],
            56 => ['numerator_zyssbm', 'denominator_zyssbm'],
            11 => ['numerator_public_cyjl', ''],
            13 => ['numerator_public_cyjl', ''],
            58 => ['numerator_zqtysgfqs', 'denominator_zqtysgfqs'],
            49 => ['numerator_hzqjcgl', 'denominator_hzqjcgl'],
            52 => ['numerator_cyhzgdl', 'denominator_cyhzgdl'],
            59 => ['numerator_A', 'denominator_A'],
            60 => ['numerator_ngzb', 'denominator_ngzb'],
            61 => ['numerator_xgzb', 'denominator_xgzb'],
            62 => ['numerator_ngrszb', 'denominator_ngrszb'],
        ];
        if (empty($dateType[$id])) {
            throw new Exception('参数有误');
        }

        $esService = new ElasticsearchService('patient_info_target');
        // 分子搜索还是分母搜索
        $must[] = [
            "term" => [
                $dateType[$id][$where['data_type']] => 1
            ]
        ];
        // 时间范围
        $must[] = [
            'range' => [
                'AAC01' => [
                    'gte' => $startTime,
                    'lte' => $endTime,
                ]
            ]
        ];
        if ($isError != 200) {
            // 57指标正确性正好和其他相反
            if ($id == 57) {
                $isError = $isError == 1 ? 0 : 1;
            }
            $must[] = [
                "term" => [
                    $dateType[$id][0] => $isError
                ]
            ];
        }
        // 住院号搜索
        if (!empty($where['AAA28'])) {
            $must[] = [
                "term" => [
                    'AAA28' => $where['AAA28']
                ]
            ];
        }
        // 住院号搜索
        if (!empty($where['AAC11N'])) {
            $must[] = [
                "term" => [
                    'AAC11N' => $where['AAC11N']
                ]
            ];
        }

        // 排序
        $order = !empty($where['order']) ? $where['order'] : 'AAB01';
        $order_sort = !empty($where['order_sort']) ? $where['order_sort'] : 'asc';

        // 分母
        $params = $esService->queryByMustBatch($must);
        $params = $params->paginate($where['page'], $where['page_size'])
            ->orderBy($order, $order_sort)
            ->trackTotalHits()
            ->getParams();
        $restful = app('es')->search($params);
        $hits = $esService->getDataByEs($restful);
        if (!$hits[1]) {
            return ['count' => 0, 'data' => []];
        }
        $data = $hits[0];
        $count = $hits[1];
        foreach ($data as &$d) {
            $d['description'] = !empty($dateType[$id][2]) ? $d[$dateType[$id][2]] : '';

            $den = $dateTypeMap[$id];
            if ($id == 57) { // 57指标正确性正好和其他相反
                $d['numerator'] = $d[$den[0]] == 1 ? 0 : 1;
            } else {
                $d['numerator'] = $d[$den[0]];
            }
            $d['denominator'] = !empty($d[$den[1]]) ? $d[$den[1]] : '';
            //心梗 脑梗 发病时间
            if ($id == 60 || $id == 61) {
                $ngXgData = $this->getNgXgData($id, $d);
                $d['fbsj'] = $ngXgData ? $ngXgData->fbsj_datetime : '';
                $d['fbsj_s'] = $ngXgData ? $ngXgData->fbsj_s : '';
                $d['zhusu'] = $ngXgData ? $ngXgData->zhusu : '';
                $d['sssj'] = $ngXgData ? $ngXgData->sssj_datetime : '';
                $d['zyzbbh'] = $ngXgData ? $ngXgData->ICD10_ID1 : '';
                $d['zyzdmc'] = $ngXgData ? $ngXgData->ICD10_NAME : '';
                $d['ssbh'] = $ngXgData ? $ngXgData->ICD9_ID1 : '';
                $d['ssmc'] = $ngXgData ? $ngXgData->ICD9_NAME : '';

                if ($d['description']) {
                    preg_replace("/发病时间【(.*?)】/", '发病时间【' . $d['fbsj'] . '】', $d['description']);
                    preg_replace("/发病时间-时【(.*?)】/", '发病时间-时【' . $d['fbsj_s'] . '】', $d['description']);
                }
            }
            if ($id == 62) {
                $ngXgData = $this->getNgXgData($id, $d);
                $d['fbsj'] = $ngXgData ? $ngXgData->fbsj_datetime : '';
                $d['rssj'] = $ngXgData ? $ngXgData->rssj : '';
                $d['yzmc'] = $ngXgData ? $ngXgData->yzmc : '';
                $d['XZJDSJ'] = $ngXgData ? $ngXgData->XZJDSJ : '';
                $d['zyzbbh'] = $ngXgData ? $ngXgData->ICD10_ID1 : '';
                $d['zyzdmc'] = $ngXgData ? $ngXgData->ICD10_NAME : '';
            }
            $d['AAB01'] = $d['AAB01'] == '1970-01-01 00:00:00' ? '' : $d['AAB01'];
            $d['AAC01'] = $d['AAC01'] == '1970-01-01 00:00:00' ? '' : $d['AAC01'];
        }

        return ['count' => $count, 'data' => $data];
    }

    /**
     * 获取心梗 脑梗指标数据
     * @param $type
     * @param $data
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getNgXgData($type, $data)
    {
        $BLBH = '';
        if ($type == 60 || $type == 62) {
            $BLBH = $data['NG_BLBH'];
        }
        if ($type == 61) {
            $BLBH = $data['XG_BLBH'];
        }

        return Pszb::query()->where('BLBH', $BLBH)->first();
    }


    /**
     * 不合理的数据复制
     * update patient_info_target set numerator_bhlbl=0,denominator_bhlbl=0,bhlbl_content="";
     */
    public function buheliCopy()
    {
        $startTime = $this->startTime; //开始时间
        $entTime = date('Y-m-d H:i:s', time()); //结束时间 等于其实时间加一天
        //$startTime = time() - 60 * 60 * 24 * 365 * 2;
        //$startTime = "2024-01-01 00:00:00";
        $ryZbBagl = ZbBagl::query()->where('id', 16)->first(['id', 'keyword', 'table_name', 'table_field']); //入院记录
        $rcZbBagl = ZbBagl::query()->where('id', 17)->first(['id', 'keyword', 'table_field']); //日常病程记录

        while (true) {
            echo '开始时间:' . $startTime . "\n";
            echo '结束世界:' . $entTime . "\n";
            if ($startTime > time() - 60 * 60 * 24)
                break;

            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->whereBetween('AAC01', [$startTime, $entTime])
                ->get(['MED_REC_ID', 'id', 'AAC01', 'ZYH_ID'])->toArray();
            $startTime = $entTime;
            if (empty($data))
                continue;
            //开始循环处理数据
            foreach ($data as $k => $v) {
                echo $v['MED_REC_ID'] . date('Y-m-d H:i:s') . "\n";
                $numerator = 0;
                $error = [];
                //region  首次病程记录与入院记录对比
                //获取首次病程记录（病历特点）
                $BLTD = ""; //首次病程记录(病例特点)
                $bllb294295Row = Bllb294_295::query()
                    ->where('ZYH', $v['MED_REC_ID'])
                    ->first();
                if (!empty($bllb294295Row)) {
                    $BLTD = $bllb294295Row->toArray()['BLTD'];
                    $BLTDArray = preg_split('//u', $BLTD, 0, PREG_SPLIT_NO_EMPTY);
                    $BLTDArray = array_unique(array_filter($BLTDArray));
                }

                //获取入院记录
                $RYJR = ""; //入院记录
                $tablename = $ryZbBagl['table_name'] . ".BLBH";
                $ryRow = DB::table($ryZbBagl['table_name'])
                    ->leftJoin('EMR_BL_BLXG', $tablename, '=', 'EMR_BL_BLXG.BLBH')
                    ->where('JZHM', $v['MED_REC_ID'])
                    ->where($ryZbBagl['table_field'], '=', $ryZbBagl['keyword'])
                    ->first();

                if (!empty($ryRow)) {
                    $RYJR = $ryRow->HJNR;
                    $RYJRArray = preg_split('//u', $RYJR, 0, PREG_SPLIT_NO_EMPTY);
                    $RYJRArray = array_unique(array_filter($RYJRArray));
                }
                $res = array_intersect($BLTDArray, $RYJRArray);

                if (!empty($bllb294295Row) && !empty($ryRow)) {
                    //判断相似度
                    if (!empty($BLTDArray) && count($BLTDArray) > 0 && count($res) / count($BLTDArray) >= 0.9) {
                        $error[] = ['MED_REC_ID' => $v['MED_REC_ID'], 'bhlbl_content' => ' 首次病程记录 【' . $bllb294295Row->BLMC . '】和入院记录【' . $ryRow->BLMC . '】 90%雷同'];
                        $numerator = 1;
                    }
                }


                //endregion

                //region 日常病程
                $esService = new ElasticsearchService('bl01_202303');

                $must = [
                    ["term" => ['JZHM' => $v['MED_REC_ID']]],
                    ['terms' => ['MBLB' => explode(',', $rcZbBagl['keyword'])]]
                ];
                $params = $esService->clearMust()
                    ->queryByMustBatch($must)
                    ->getParams();
                $result = app('es')->search($params);
                $result = $esService->getDataByEs($result);
                $rcblNum = count($result[0]); //日常病例总数量
                //var_dump($result, $rcblNum);

                if ($rcblNum > 1) {
                    foreach ($result[0] as $kk => $vv) {
                        $nextNum = $kk + 1;
                        $HJNR = "";
                        $HJNR = preg_split('//u', $vv['HJNR'], 0, PREG_SPLIT_NO_EMPTY); //病程记录内容
                        $HJNR = array_unique(array_filter($HJNR));

                        for ($i = $kk; $nextNum < $rcblNum - 1; $i++) {
                            //var_dump($nextNum,$rcblNum);
                            if (isset($result[0][$i]) && isset($result[0][$i]['HJNR'])) {
                                //var_dump($result[0][$i]['HJNR']);
                                $iHJNR = "";
                                //var_dump($result[0][$i]['HJNR']);
                                $iHJNR = preg_split('//u', $result[0][$i]['HJNR'], 0, PREG_SPLIT_NO_EMPTY);
                                $iHJNR = array_unique(array_filter($iHJNR));
                                $rcResult = array_intersect($HJNR, $iHJNR); //获取入院记录与病例特点的交集度
                                //如果相似度90%以上，则认为该病例不符合规则
                                //排除本身,如果相同就跳过
                                if ($vv['BLMC'] == $result[0][$i]['BLMC']) {
                                    continue;
                                }
                                if (!empty($rcResult) && count($rcResult) > 0 && count($rcResult) / count($HJNR) >= 0.9) {
                                    $error[] = [
                                        'MED_REC_ID' => $v['MED_REC_ID'],
                                        'bhlbl_content' => ' 病程记录 【' .
                                            $vv['BLMC'] . '】和病程【' . $result[0][$i]['BLMC'] . '】 90%雷同'
                                    ];
                                    $numerator = 1;
                                }
                            } else {
                                // 处理索引不存在的情况
                                echo "索引 $i 或 'HJNR' 键不存在\n";
                                //continue;
                            }
                            $nextNum++;
                        }
                    }
                }
                //endregion
                $ryjlError = '';
                //如果有错误的质控结果
                if (!empty($error)) {
                    foreach ($error as $vvv) {
                        //PatientInfoTarget::query()->updateOrInsert(['ZYH' => $vvv['MED_REC_ID']], ['numerator_bhlbl' => 1, 'bhlbl_content' => $vvv['bhlbl_content']]);
                        $ryjlError .= $vvv['bhlbl_content'];
                    }
                }
                $saveData = ['denominator_bhlbl' => 1, 'numerator_bhlbl' => $numerator, 'bhlbl_content' => $ryjlError];
                PatientInfoTarget::query()->updateOrInsert(['ZYH' => $v['MED_REC_ID']], $saveData);
            }
        }
    }

    /**
     * 手术相关记录完整率
     * update EMR_BL_BLXG set is_operation=1 where HJNR like "%术前小结及术前讨论结论记录%"
     * update patient_info_target set denominator_operation=0,numerator_operation=0,operation_error="";
     */
    public
    function operationComplete()
    {
        $setName = 'quality_zb_operationComplete';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        $pageSize = 500;
        $startTime = $this->startTime;
        while (1) {
            $entTime = $startTime + 86400;
            if ($startTime > time()) {
                break;
            }
            var_dump('时间：' . date("Y-m-d H:i:s", $startTime));
            // 所有符合条件的病例信息
            $patientInfo = $patientInfoCopy = PatientInfo::query()
                ->where('AAC01', '>=', date("Y-m-d H:i:s", $startTime))
                ->where('AAC01', '<=', date("Y-m-d H:i:s", $entTime))
                ->get(['MED_REC_ID', 'AAC01', 'id', 'AAC01'])->toArray();
            $startTime = $entTime;
            $patientInfo = array_column($patientInfo, 'AAC01', 'MED_REC_ID');

            // 所有住院号
            $ids = array_keys($patientInfo);
            // 所有符合条件的病例信息
            $data = SSSQ::query()
                ->where('ZFBZ', '=', 0)
                ->whereIn('ZYH', $ids)
                ->get(['ZYH', 'NSSMC', 'SSRQ'])->toArray();
            if (!$data) {
                continue;
            }
            $newData = [];
            foreach ($data as $item) {
                $item['AAC01'] = $patientInfo[$item['ZYH']];
                $newData[$item['ZYH']][] = $item;
            }

            // 获取数据的最后一条
            $lastData = array_pop($patientInfoCopy);
            $lastId = $lastData['AAC01'];

            $fenzi = [];
            foreach ($newData as $zyh => $data) {
                $zyh = (string) $zyh;
                var_dump($zyh);
                $operationError = [];
                $isError = 1;
                foreach ($data as $y) {
                    $ssrq = $y['SSRQ'] ?: '';
                    $operation_error = '';
                    if (empty($y['NSSMC'])) {
                        $isError = 0;
                        $operationError[] = '拟手术名称为空';
                        continue;
                    }

                    // 获取医嘱信息
                    $yizhu = Yzb::query()
                        ->where('ZYH', '=', $y['ZYH'])
                        ->where('is_operation', '=', 1)
                        ->where('YZMC', 'like', '%' . $y['NSSMC'] . '%')
                        ->limit(1)
                        ->get(['KZSJ', 'JJYZ'])->toArray();
                    if (!$yizhu) {
                        $operation_error .= '医嘱【 拟*年*月*日' . $y['NSSMC'] . '（无） 】';
                        $isError = 0;
                    } else {
                        $operation_error .= '医嘱【 拟*年*月*日' . $y['NSSMC'] . '（有） 】';

                        $kzsj = $yizhu[0]['KZSJ'];
                        $kzsj = strtotime($kzsj);
                        // 不是紧急医嘱则校验
                        if ($yizhu[0]['JJYZ'] == 0) {

                            $esService = new ElasticsearchService('bl01_202303');
                            $mustBl = [
                                ["term" => ['JZHM' => $y['ZYH']]],
                                ["term" => ['BLLB' => 294]],
                                ["match_phrase" => ['HJNR' => "术前小结及术前讨论结论记录"]]
                            ];
                            $notMust = ['term' => ["BLZT" => 9]];
                            $params = $esService->clearMust()
                                ->queryByMustBatch($mustBl)
                                ->queryByMustNot($notMust)
                                ->paginate(1, 1000)
                                ->getParams();
                            $res = app('es')->search($params);
                            $res = $esService->getDataByEs($res);

                            if (!$res[1]) {
                                $operation_error .= '术前小结及术前讨论结论记录【无】';
                                $isError = 0;
                            } else {
                                $cjsj = strtotime($res[0]['CJSJ']);
                                $operation_error .= '术前小结及术前讨论结论记录【有、 ' . $res[0]['CJSJ'] . '、';
                                if ($cjsj < $kzsj) {
                                    $operation_error .= '<开嘱时间】';
                                } else {
                                    $operation_error .= '>开嘱时间】';
                                }
                            }
                        }

                        // 3、手术记录时间在开嘱时间之后
                        $ssrq = strtotime(date("Y-m-d", strtotime($ssrq))); // 手术日期

                        $mustBl = [
                            ["term" => ['JZHM' => $y['ZYH']]],
                            ['range' => ['CJSJ' => ['from' => date('Y-m-d H:i:s', $kzsj)]]],
                            [
                                "bool" => [
                                    "should" => [
                                        ["match_phrase" => ['HJNR' => "手术记录"]],
                                        ["match_phrase" => ['HJNR' => "剖宫产记录"]],
                                    ]
                                ]
                            ]
                        ];
                        $notMust = ['term' => ["BLZT" => 9]];
                        $params = $esService->clearMust()
                            ->queryByMustBatch($mustBl)
                            ->queryByMustNot($notMust)
                            ->paginate(1, 1000)
                            ->getParams();
                        $res = app('es')->search($params);
                        $res = $esService->getDataByEs($res);

                        if (!$res[1]) {
                            $operation_error .= '手术记录【无】';
                            $isError = 0;
                        } else {
                            $operation_error .= '手术记录【（有）、' . $res[0]['CJSJ'] . '、';
                            if (!empty($bingcheng)) {
                                $cjsj = strtotime($bingcheng[0]['CJSJ']);
                                if ($cjsj < $kzsj) {
                                    $operation_error .= '<开嘱时间';
                                } else {
                                    $operation_error .= '>开嘱时间';
                                }
                            }
                            $operation_error .= '】';
                        }

                        // 手术同意书
                        $sstys1 = EMR_BL_BL01::query()
                            ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                            //                            ->where(Db::raw('UNIX_TIMESTAMP(EMR_BL_BL01.CJSJ)'), '>', $kzsj)
                            ->where('EMR_BL_BL01.JZHM', '=', $y['ZYH'])
                            ->whereIn('EMR_BL_BL01.BLLB', [303, 329])
                            ->where('EMR_BL_BL01.BLZT', '<>', 9)
                            ->where('EMR_BL_BLXG.HJNR', 'like', "%手术同意书%")
                            ->limit(1)
                            ->get(['CJSJ', 'BLMC'])->toArray();
                        if (!empty($sstys1)) {
                            $operation_error .= '手术同意书【' . $sstys1[0]['BLMC'] . '（有） 】';
                        } else {
                            $sstys2 = EMR_BL_BL01::query()
                                ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                                //                            ->where(Db::raw('UNIX_TIMESTAMP(EMR_BL_BL01.CJSJ)'), '>', $kzsj)
                                ->where('EMR_BL_BL01.JZHM', '=', $y['ZYH'])
                                ->whereIn('EMR_BL_BL01.BLLB', [303, 329])
                                ->where('EMR_BL_BL01.BLZT', '<>', 9)
                                ->where('EMR_BL_BLXG.HJNR', 'like', "%知情同意书%")
                                ->limit(1)
                                ->get(['CJSJ', 'BLMC'])->toArray();
                            if (!empty($sstys2)) {
                                $operation_error .= '知情同意书【' . $sstys2[0]['BLMC'] . '（有） 】';
                            } else {
                                $operation_error .= '手术记录【手术同意书 或者 知情同意书（无） 】';
                                $isError = 0;
                            }
                        }

                        // 4、手术时间术后3天有病程
                        if (!empty($ssjl[0])) {
                            $cysj = strtotime(date("Y-m-d", strtotime($y['AAC01'])));
                            $operationTime = $ssjl[0]['operation_time'];
                            // 如果当天出院则只需要出院当天的病程记录

                            $bingcheng1 = 0;
                            $bingcheng2 = 0;
                            $bingcheng3 = 0;
                            if ($cysj == $operationTime) {
                                $bingcheng1 = EMR_BL_BL01::query()
                                    ->where('JZHM', '=', $y['ZYH'])
                                    ->where('BLLB', '=', 294)
                                    ->where('EMR_BL_BL01.BLZT', '<>', 9)
                                    ->whereBetween(Db::raw('UNIX_TIMESTAMP(ZXSJ)'), [$operationTime, $operationTime + 24 * 3600])
                                    ->get(['CJSJ', 'BLMC'])->toArray();
                                $bingcheng2 = 1;
                                $bingcheng3 = 1;
                            } else {
                                if ($cysj && $cysj >= $operationTime + 24 * 3600) {
                                    $bingcheng1 = EMR_BL_BL01::query()
                                        ->where('JZHM', '=', $y['ZYH'])
                                        ->where('BLLB', '=', 294)
                                        ->where('EMR_BL_BL01.BLZT', '!=', 9)
                                        ->whereBetween(Db::raw('UNIX_TIMESTAMP(ZXSJ)'), [$operationTime + 24 * 3600, $operationTime + 24 * 3600 * 2])
                                        ->get(['CJSJ', 'BLMC'])->toArray();
                                }
                                if ($cysj && $cysj >= $operationTime + 2 * 24 * 3600) {
                                    $bingcheng2 = EMR_BL_BL01::query()
                                        ->where('JZHM', '=', $y['ZYH'])
                                        ->where('BLLB', '=', 294)
                                        ->where('EMR_BL_BL01.BLZT', '!=', 9)
                                        ->whereBetween(Db::raw('UNIX_TIMESTAMP(ZXSJ)'), [$operationTime + 2 * 24 * 3600, $operationTime + 24 * 3600 * 3])
                                        ->get(['CJSJ', 'BLMC'])->toArray();
                                }
                                if ($cysj && $cysj >= $operationTime + 3 * 24 * 3600) {
                                    $bingcheng3 = EMR_BL_BL01::query()
                                        ->where('JZHM', '=', $y['ZYH'])
                                        ->where('BLLB', '=', 294)
                                        ->where('EMR_BL_BL01.BLZT', '!=', 9)
                                        ->whereBetween(Db::raw('UNIX_TIMESTAMP(ZXSJ)'), [$operationTime + 3 * 24 * 3600, $operationTime + 24 * 3600 * 4])
                                        ->get(['CJSJ', 'BLMC'])->toArray();
                                }
                            }

                            if (!$bingcheng1 || !$bingcheng2 || !$bingcheng3) {
                                $operation_error .= '术后病程记录【（无）】';
                                $isError = 0;
                            } else {
                                $operation_error .= '术后病程记录【（有）、';
                                if (!empty($bingcheng1[0]['CJSJ'])) {
                                    $operation_error .= $bingcheng1[0]['CJSJ'] . '、';
                                }
                                if (!empty($bingcheng2[0]['CJSJ'])) {
                                    $operation_error .= $bingcheng2[0]['CJSJ'] . '、';
                                }
                                if (!empty($bingcheng3[0]['CJSJ'])) {
                                    $operation_error .= $bingcheng3[0]['CJSJ'] . '、';
                                }
                                $operation_error .= '】';
                            }
                        }
                    }

                    $operationError[] = $operation_error;
                }

                $operationErrorContent = '';
                foreach ($operationError as $k => $item) {
                    $operationErrorContent .= ($k + 1) . '：' . $item;
                }
                $fenzi[] = ['ZYH' => $zyh, 'numerator_operation' => $isError, 'operation_error' => $operationErrorContent]; // 分子+1
            }

            if ($fenzi) {
                foreach ($fenzi as $d) {
                    $d['ZYH'] = (string) $d['ZYH'];
                    PatientInfoTarget::query()->updateOrInsert(['ZYH' => $d['ZYH']], ['denominator_operation' => 1, 'numerator_operation' => $d['numerator_operation'], 'operation_error' => $d['operation_error']]);
                }
            }
        }
        Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
        var_dump('手术相关记录完整率_end');
    }


    /**
     * wcy
     * CT/MRI检查记录符合率接口
     * @param $where
     * @return array
     */
    protected
    function getIrcrData(
        array $where = []
    ) {
        $year = $where['year'] ?? Carbon::now()->year;
        $data = [];

        for ($month = 1; $month <= 12; $month++) {
            $startTime = strtotime($year . '-' . $month . '-01');
            $t = date('t', $startTime);
            $endTime = $startTime + $t * 3600 * 24;

            // 分母
            $denominatorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_ct', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');

            // 分子
            $numeratorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_ct', '=', 1)
                ->where('patient_info_target.numerator_ct', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');

            // 计算符合率
            $complianceRate = $denominatorCount > 0 ? bcdiv((string) $numeratorCount, (string) $denominatorCount, 4) : 0;
            $data[] = [
                'title' => 'CT/MRI检查记录符合率',
                'time' => $year . '-' . sprintf('%02s', $month),
                'denominator' => $denominatorCount,
                'numerator' => $numeratorCount,
                'res' => $complianceRate,
                'source' => '系统提取',
            ];
        }

        // 分母
        $denominatorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_ct', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');
        // 分子
        $numeratorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_ct', '=', 1)
            ->where('patient_info_target.numerator_ct', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');

        // 计算符合率
        $complianceRate = $denominatorCount > 0 ? bcdiv((string) $numeratorCount, (string) $denominatorCount, 4) : 0;
        $data[] = [
            'title' => 'CT/MRI检查记录符合率',
            'time' => '全年',
            'denominator' => $denominatorCount,
            'numerator' => $numeratorCount,
            'res' => $complianceRate,
            'source' => '系统提取',
        ];

        return $data;
    }

    /**
     * wcy
     * 植入物相关记录符合率
     * @param $where
     * @return array
     */
    protected
    function getZrwData(
        array $where = []
    ) {
        $year = $where['year'] ?? Carbon::now()->year;
        $data = [];

        for ($month = 1; $month <= 12; $month++) {
            $startTime = strtotime($year . '-' . $month . '-01');
            $t = date('t', $startTime);
            $endTime = $startTime + $t * 3600 * 24;

            // 分母
            $denominatorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_zrw', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');;
            // 分子
            $numeratorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_zrw', '=', 1)
                ->where('patient_info_target.numerator_zrw', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');

            // 计算符合率
            $complianceRate = $denominatorCount > 0 ? bcdiv((string) $numeratorCount, (string) $denominatorCount, 4) : 0;
            $data[] = [
                'title' => '植入物相关记录符合率',
                'time' => $year . '-' . sprintf('%02s', $month),
                'denominator' => $denominatorCount,
                'numerator' => $numeratorCount,
                'res' => $complianceRate,
                'source' => '系统提取',
            ];
        }

        // 分母
        $denominatorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_zrw', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');
        // 分子
        $numeratorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_zrw', '=', 1)
            ->where('patient_info_target.numerator_zrw', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');
        // 计算符合率
        $complianceRate = $denominatorCount > 0 ? bcdiv((string) $numeratorCount, (string) $denominatorCount, 4) : 0;
        $data[] = [
            'title' => '植入物相关记录符合率',
            'time' => '全年',
            'denominator' => $denominatorCount,
            'numerator' => $numeratorCount,
            'res' => $complianceRate,
            'source' => '系统提取',
        ];

        return $data;
    }

    /**
     * wcy
     * 细菌培养相关记录符合率
     * @param $where
     * @return array
     */
    protected
    function getXjpyData(
        array $where = []
    ) {
        $year = $where['year'] ?? Carbon::now()->year;
        $data = [];

        for ($month = 1; $month <= 12; $month++) {
            $startTime = strtotime($year . '-' . $month . '-01');
            $t = date('t', $startTime);
            $endTime = $startTime + $t * 3600 * 24;

            // 分母
            $denominatorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_xjpy', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');;
            // 分子
            $numeratorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_xjpy', '=', 1)
                ->where('patient_info_target.numerator_xjpy', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');

            // 计算符合率
            $complianceRate = $denominatorCount > 0 ? bcdiv((string) $numeratorCount, (string) $denominatorCount, 4) : 0;
            $data[] = [
                'title' => '细菌培养相关记录符合率',
                'time' => $year . '-' . sprintf('%02s', $month),
                'denominator' => $denominatorCount,
                'numerator' => $numeratorCount,
                'res' => $complianceRate,
                'source' => '系统提取',
            ];
        }

        // 分母
        $denominatorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_xjpy', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');
        // 分子
        $numeratorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_xjpy', '=', 1)
            ->where('patient_info_target.numerator_xjpy', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');
        // 计算符合率
        $complianceRate = $denominatorCount > 0 ? bcdiv((string) $numeratorCount, (string) $denominatorCount, 4) : 0;
        $data[] = [
            'title' => '细菌培养相关记录符合率',
            'time' => '全年',
            'denominator' => $denominatorCount,
            'numerator' => $numeratorCount,
            'res' => $complianceRate,
            'source' => '系统提取',
        ];

        return $data;
    }

    /**
     * wcy
     * 临床用血相关记录符合率
     * @param $where
     * @return array
     */
    protected
    function getLcyxData(
        array $where = []
    ) {
        $year = $where['year'] ?? Carbon::now()->year;
        $data = [];

        for ($month = 1; $month <= 12; $month++) {
            $startTime = strtotime($year . '-' . $month . '-01');
            $t = date('t', $startTime);
            $endTime = $startTime + $t * 3600 * 24;

            // 分母
            $denominatorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_lcyx', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');
            // 分子
            $numeratorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_lcyx', '=', 1)
                ->where('patient_info_target.numerator_lcyx', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');
            // 计算符合率
            $complianceRate = $denominatorCount > 0 ? bcdiv((string) $numeratorCount, (string) $denominatorCount, 4) : 0;
            $data[] = [
                'title' => '临床用血相关记录符合率',
                'time' => $year . '-' . sprintf('%02s', $month),
                'denominator' => $denominatorCount,
                'numerator' => $numeratorCount,
                'res' => $complianceRate,
                'source' => '系统提取',
            ];
        }

        // 分母
        $denominatorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_lcyx', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');
        // 分子
        $numeratorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_lcyx', '=', 1)
            ->where('patient_info_target.numerator_lcyx', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');
        // 计算符合率
        $complianceRate = $denominatorCount > 0 ? bcdiv((string) $numeratorCount, (string) $denominatorCount, 4) : 0;
        $data[] = [
            'title' => '临床用血相关记录符合率',
            'time' => '全年',
            'denominator' => $denominatorCount,
            'numerator' => $numeratorCount,
            'res' => $complianceRate,
            'source' => '系统提取',
        ];

        return $data;
    }

    public function addZbData($saveData)
    {
        foreach ($saveData as $value) {
            $where = [
                'year' => $value['year'],
                'month' => $value['month'],
                'flag' => $value['flag']
            ];
            $saveData = [
                $value['type'] => $value['num']
            ];

            PatientInfoTargetNew::query()->updateOrInsert($where, $saveData);
        }


        return true;
    }

    public function hrConfigZb($type, $where)
    {
        $year = $where['year'] ?? date('Y');

        $data = [];
        $source = '系统提取';
        if ($type == 11 && $where['request_source'] == 2) {
            $source = '人工录入';
        } elseif ($type == 12) {
            $source = '人工录入';
        } elseif ($type == 13 && $where['request_source'] == 2) {
            $source = '人工录入';
        }
        for ($i = 1; $i <= 12; $i++) {
            $month = sprintf('%02s', $i);
            $zbData = PatientInfoTargetNew::query()
                ->where('year', '=', $year)
                ->where('month', '=', $month)
                ->where('flag', '=', $type)
                ->first();
            if (!$zbData) {
                $data[] = [
                    'time' => $year . '-' . $month,
                    'denominator' => 0,
                    'numerator' => 0,
                    'res' => 0,
                    'source' => $source
                ];
            } else {
                $time = $year . '-' . $month;
                if ($type == 12) {
                    $numerator = $zbData->numerator;
                } else {
                    $numerator = PatientInfo::query()
                        ->whereBetween('AAC01', [$time . '-01 00:00:00', $time . '-31 23:59:59'])
                        ->count();
                }

                $res = 0;
                if ($numerator > 0 && $zbData->denominator > 0) {
                    $res = bcdiv((string) $numerator, (string) $zbData->denominator, 4);
                }

                $data[] = [
                    'time' => $time,
                    'denominator' => $zbData->denominator,
                    'numerator' => $numerator,
                    'res' => $res,
                    'source' => $source
                ];
            }
        }

        $yearData = ['time' => '全年', 'denominator' => 0, 'numerator' => 0, 'res' => 0, 'source' => $source];
        foreach ($data as $val) {
            $yearData['denominator'] += $val['denominator'];
            $yearData['numerator'] += $val['numerator'];
        }

        $yearData['res'] = 0;
        if ($yearData['numerator'] > 0 && $yearData['denominator'] > 0) {
            $yearData['res'] = bcdiv((string) $yearData['numerator'], (string) $yearData['denominator'], 4);
        }
        $data[] = $yearData;

        return $data;
    }


    public function checkCaseList($zyh = 0)
    {

        $pageSize = 100;
        $setName = 'qx_bc_last_id';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastNo = $lastId ?: 0;
        if ($zyh) {
            $lastNo = 0;
        }
        $column = ['BLBH', 'MBLB'];
        while (1) {

            var_dump($lastNo);
            $query = EMR_BL_BL01::query()
                ->whereIn("BLLB", [1, 294])
                ->where('BLBH', '>', $lastNo)
                ->where('BLZT', '!=', 9)
                ->orderBy("BLBH", "asc")
                ->select($column)
                ->LIMIT($pageSize);

            if ($zyh) {
                $query = $query->whereIn('JZHM', $zyh);
            }
            $res = $query->get()->toArray();
            if (!$res) {
                break;
            }
            foreach ($res as $no) {
                $lastNo = $no['BLBH'];
                self::checkCase($no['BLBH'], $no['MBLB']);
            }
        }
        Setting::query()->where('name', '=', $setName)->update(['content' => $lastNo]);
        return true;
    }

    /**
     * @param string $no
     * @param string $mblb
     * @return array|mixed|string
     */
    public static function checkCase($no = '', $mblb = '')
    {
        $title = ['病例特点', '诊断依据', '鉴别诊断', '诊疗计划'];
        $res = EMR_BL_BLXG::getById($no);
        if (!$res) {
            return '';
        }
        $caseContent = $res[0]['HJNR'];

        // 整理数据，将数据整理成数组结构
        $caseContent = str_replace("\r\n", "", $caseContent);
        $caseContent = str_replace("\n", "", $caseContent);
        //        $caseContent = str_replace("：", ":", $caseContent);
        //        $caseContent = str_replace(['.', '(', ')', '（', '）', "“", "”", "，", "。", "；", "、", ' '], "", $caseContent);

        // 非首次病程则直接返回数据
        if ($mblb != 295) {
            return $caseContent;
        }
        foreach ($title as $t) {
            $caseContent = str_replace($t, "|&|" . $t . '||', $caseContent);
        }
        $caseContentArr = explode("|&|", $caseContent);

        $newData = [];
        foreach ($caseContentArr as $item) {
            $itemArr = explode("||", $item);
            if (!in_array($itemArr[0], $title) || !isset($itemArr[1])) {
                continue;
            }
            $info['title'] = $itemArr[0];
            $info['value'] = $itemArr[1] ?? '';
            $newData[] = $info;
        }
        if (empty($newData)) {
            return '';
        }
        $bcStr = $newData[0]['value'];
        //        $myArray = preg_split('//u', $bcStr, null, PREG_SPLIT_NO_EMPTY);
        if ($mblb == 295) {
            $eidtData = ['bcts' => $bcStr];
        } else {
            $eidtData = ['bc_content' => $bcStr];
        }
        EMR_BL_BL01::updateById($no, $eidtData);
    }
}
