<?php

namespace App\Services;

use App\Model\Bllb292;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Model\FeeDetailed;
use App\Model\MainOperation;
use App\Model\MedicinalInfo;
use App\Model\OperationInfo;
use App\Model\PACS;
use App\Model\PatientHospitalInfo;
use App\Model\PatientInfo;
use App\Model\PatientInfoTargetTemporary as PatientInfoTarget;
use App\Model\PatientInfoTargetNew;
use App\Model\Pszb;
use App\Model\SecondaryOperation;
use App\Model\Setting;
use App\Model\SSSQ;
use App\Model\Staff;
use App\Model\Yzb;
use App\Services\ElasticsearchService;
use Carbon\Carbon;
use Exception;
use App\Model\PatientMedicalInfo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\EventListener\ValidateRequestListener;
use App\Model\EMR_BL_BLSY;

/**
 * 指标数据v2
 */
class RadioServiceV2
{
    // 手术判别是手术和介入治疗
    public const SSPB14 = [1, 4];
    public $startTime = '2022-01-01 00:00:00';

    public function __construct()
    {
        // 只处理两天前的数据
        $this->startTime = date('Y-m-d H:i:s', time()-48*3600);
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
        $setName = 'quality_zb_operationComplete_v2';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;

        $staff = Staff::query()->get()->toArray();
        $staffArr = [];
        foreach ($staff as $v) {
            $staffArr[$v['code']] = $v;
        }

        $page = 0;
        $pageSize = 100;
        $piEesService = new ElasticsearchService('patient_info');
        $mzjlesService = new ElasticsearchService('mzjl_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');
        $sssqesService = new ElasticsearchService('sssq_2023');
        $zyHcmxEsService = new ElasticsearchService('zy_hcmx');

        while (1) {
            $page++;
            $params = $piEesService
                ->paginate($page, $pageSize)
                ->queryByMust(['range' => ['MED_REC_ID' => ['gt' => $lastId]]])
                ->queryByMust(['range' => ['AAC01' => ['lt' => $this->startTime]]])
                ->source(['MED_REC_ID', 'AAB01', 'AAC01', 'AAC04'])
                ->orderBy('MED_REC_ID', 'desc')
                ->getParams();
            $res = app('es')->search($params);
            $res = $piEesService->getDataByEs($res);

            if (empty($res[0])) {
                break;
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

            // 护士分床时间
            $params = $zyHcmxEsService->clearMust()->paginate(1, 10000)
                ->queryByMust(['terms' => ["ZYH" => $zyh]])
                ->queryByMust(['term' => ["HCLX" => 0]])
                ->source(['ZYH', 'HCRQ'])->getParams();
            $mzRes = app('es')->search($params);
            $mzRes = $zyHcmxEsService->getDataByEs($mzRes);
            $zyHcmxData = [];
            if (!empty($mzRes[0])) {
                $zyHcmxData = array_column($mzRes[0], null, 'ZYH');
            }

            // 医嘱本
            $yzb = Yzb::query()->whereIn('ZYH', $zyh)
                ->whereIn('YDYZLB', [303, 305])
                ->get(['ZYH', 'XZJDSJ'])->toArray();
            $yzb = array_column($yzb, null, 'ZYH');

            foreach ($patientInfo as $p) {
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
                                        "ZXSJ" => [
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
                                $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['ZXSJ'])->getParams();
                                $bl01Res = app('es')->search($params);
                                $bl01Res = $bl01esService->getDataByEs($bl01Res);

                                if (empty($bl01Res[0]) || empty($bl01Res[0][0])) {
                                    $flag = 0;
                                    $sqe .= '，术前病程【无】';
                                } else {
                                    $sqe .= '，术前病程【' . $bl01Res[0][0]['ZXSJ'] . '、术前24h内】';
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
                                        "ZXSJ" => [
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
                                $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['ZXSJ'])->getParams();
                                $bl01Res = app('es')->search($params);
                                $bl01Res = $bl01esService->getDataByEs($bl01Res);
                                if (empty($bl01Res[0]) || empty($bl01Res[0][0])) {
                                    $flag = 0;
                                    $sqe .= '，手术病程【无】';
                                } else {
                                    $sqe .= '，手术病程【' . $bl01Res[0][0]['ZXSJ'] . '、术后24h内】';

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
                                                "ZXSJ" => [
                                                    'from' => date("Y-m-d H:i:s", $operationTime),
                                                    'to' => date("Y-m-d H:i:s", $operationTime + 24 * 3600)
                                                ]
                                            ]
                                        ];
                                        $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['ZXSJ'])->getParams();
                                        $bl01Res = app('es')->search($params);
                                        $bl01Res = $bl01esService->getDataByEs($bl01Res);
                                        $bingcheng1 = empty($bl01Res[0][0]) ? [] : $bl01Res[0][0];
                                        $bingcheng2 = ['ZXSJ' => '-'];
                                        $bingcheng3 = ['ZXSJ' => '-'];
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
                                                    "ZXSJ" => [
                                                        'from' => date("Y-m-d H:i:s", $operationTime + 24 * 3600),
                                                        'to' => date("Y-m-d H:i:s", $operationTime + 24 * 3600 * 2)
                                                    ]
                                                ]
                                            ];
                                            $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['ZXSJ'])->getParams();
                                            $bl01Res = app('es')->search($params);
                                            $bl01Res = $bl01esService->getDataByEs($bl01Res);
                                            $bingcheng1 = empty($bl01Res[0][0]) ? [] : $bl01Res[0][0];
                                            $bingcheng2 = ['ZXSJ' => '-'];
                                            $bingcheng3 = ['ZXSJ' => '-'];

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
                                                    "ZXSJ" => [
                                                        'from' => date("Y-m-d H:i:s", $operationTime + 24 * 3600 * 2),
                                                        'to' => date("Y-m-d H:i:s", $operationTime + 24 * 3600 * 3)
                                                    ]
                                                ]
                                            ];
                                            $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['ZXSJ'])->getParams();
                                            $bl01Res = app('es')->search($params);
                                            $bl01Res = $bl01esService->getDataByEs($bl01Res);
                                            $bingcheng2 = empty($bl01Res[0][0]) ? [] : $bl01Res[0][0];
                                            $bingcheng3 = ['ZXSJ' => '-'];
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
                                                    "ZXSJ" => [
                                                        'from' => date("Y-m-d H:i:s", $operationTime + 24 * 3600 * 3),
                                                        'to' => date("Y-m-d H:i:s", $operationTime + 24 * 3600 * 4)
                                                    ]
                                                ]
                                            ];
                                            $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['ZXSJ'])->getParams();
                                            $bl01Res = app('es')->search($params);
                                            $bl01Res = $bl01esService->getDataByEs($bl01Res);
                                            $bingcheng3 = empty($bl01Res[0][0]) ? [] : $bl01Res[0][0];
                                        }
                                    }

                                    if (!$bingcheng1 || !$bingcheng2 || !$bingcheng3) {
                                        $sqe .= '，术后病程【术后病程记录（无）】';
                                        $flag = 0;
                                    } else {
                                        $sqe .= '，术后病程【3天，' . $bingcheng1['ZXSJ'] . '、' . $bingcheng2['ZXSJ'] . '、' . $bingcheng3['ZXSJ'] . '、】';
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

                if (empty($zyHcmxData[$p['MED_REC_ID']])) {
                    $error .= '护士分床数据不存在';
                }
                if (empty($yzb[$p['MED_REC_ID']])) {
                    $error .= '，医嘱本信息不存在';
                }
                if ($p['AAC04'] && !empty($zyHcmxData[$p['MED_REC_ID']]) && !empty($yzb[$p['MED_REC_ID']])) {
                    $zhengName = $fuName = '';
                    $error .= '，住院天数【' . $p['AAC04'] . '】';
                    $error .= '，分床时间：' . $zyHcmxData[$p['MED_REC_ID']]['HCRQ'] . '-' . $yzb[$p['MED_REC_ID']]['XZJDSJ'];
                    if (
                        !empty($yzb[$p['MED_REC_ID']]['XZJDSJ']) &&
                        !empty($zyHcmxData[$p['MED_REC_ID']]['HCRQ']) &&
                        strtotime($yzb[$p['MED_REC_ID']]['XZJDSJ']) > 0 &&
                        strtotime($zyHcmxData[$p['MED_REC_ID']]['HCRQ']) > 0
                    ) {
                        $startTime = $fenchuangTime = $zyHcmxData[$p['MED_REC_ID']]['HCRQ'];
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
                                    ['range' => ["ZXSJ" => ['from' => $fromTime, 'to' => $toTime]]]
                                ];
                                $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['BLBH', 'ZXSJ'])->getParams();
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
                                            if ($b['ygjb'] == 1) {
                                                $zhengName = $b['name'];
                                                $zheng[] = $item['ZXSJ'];
                                            } elseif ($b['ygjb'] == 2) {
                                                $fuName = $b['name'];
                                                $fu[] = $item['ZXSJ'];
                                            } elseif ($b['ygjb'] == 6) {
                                                $zhong[] = $item['ZXSJ'];
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
                                ['range' => ["ZXSJ" => ['from' => $startTime, 'to' => $endTime]]]
                            ];
                            $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['BLBH', 'ZXSJ'])->getParams();
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
                                        if ($b['ygjb'] == 1) {
                                            $zhengName = $b['name'];
                                            $zheng[] = $item['ZXSJ'];
                                        } elseif ($b['ygjb'] == 2) {
                                            $fuName = $b['name'];
                                            $fu[] = $item['ZXSJ'];
                                        } elseif ($b['ygjb'] == 6) {
                                            $zhong[] = $item['ZXSJ'];
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
                            ['range' => ["ZXSJ" => ['from' => date('Y-m-d H:i:s', strtotime($endTime) - 48 * 3600), 'to' => $endTime]]]
                        ];
                        $params = $bl01esService->clearMust()->queryByMustNot(['term' => ["MBLB" => 295]])->queryByMustBatch($bl01must)->source(['ZXSJ', 'CJSJ'])->getParams();
                        $bl01Res = app('es')->search($params);
                        $bl01Res = $bl01esService->getDataByEs($bl01Res);
                        $bl01294 = empty($bl01Res[0]) ? [] : $bl01Res[0];

                        // 24小时出入院记录BLLB=18
                        $bl01must = [
                            ['term' => ["JZHM" => $p['MED_REC_ID']]],
                            ['term' => ["BLLB" => 18]]
                        ];
                        $params = $bl01esService->clearMust()->queryByMustNot(['term' => ["MBLB" => 295]])->queryByMustBatch($bl01must)->source(['ZXSJ'])->getParams();
                        $bl01Res = app('es')->search($params);
                        $bl01Res = $bl01esService->getDataByEs($bl01Res);
                        $bl0118 = empty($bl01Res[0]) ? [] : $bl01Res[0];

                        if (empty($bl01294) && empty($bl0118)) {
                            $flag = 0;
                            $error .= '，出院48小时内病程【无】';
                        } elseif (!empty($bl01294)) {
                            $cjsj = strtotime($bl01294[0]['CJSJ']);
                            if(strtotime($endTime) - 48 * 3600 > $cjsj || $cjsj > strtotime($endTime)){
                                $error .= '，出院48小时内病程【创建时间' . $bl01294[0]['CJSJ'] . '超48小时】';
                            }else{
                                $error .= '，出院48小时内病程【' . $bl01294[0]['CJSJ'] . '】';
                            }
                        } elseif ($bl0118) {
                            $error .= '，24小时出入院';
                        }

                        // 护士分床后48小时内，病程记录中，需要有一个 副高或正高签名（首次病程除外）
                        $bl01must = [
                            ['term' => ["JZHM" => $p['MED_REC_ID']]],
                            ['term' => ["BLLB" => 294]],
                            ['match_phrase' => ["HJNR" => $zhengName]],
                            ['range' => ["ZXSJ" => ['from' => $fenchuangTime, 'to' => date('Y-m-d H:i:s', strtotime($fenchuangTime) + 48 * 3600)]]]
                        ];
                        $params = $bl01esService->clearMust()->queryByMustNot(['term' => ["MBLB" => 295]])->queryByMustBatch($bl01must)->source(['BLBH', 'ZXSJ'])->getParams();
                        $bl01Res = app('es')->search($params);
                        $bl01Res = $bl01esService->getDataByEs($bl01Res);
                        $bl01294Zheng = empty($bl01Res[0]) ? [] : $bl01Res[0];

                        $bl01294Fu = [];
                        if ($fuName) {
                            $bl01must = [
                                ['term' => ["JZHM" => $p['MED_REC_ID']]],
                                ['term' => ["BLLB" => 294]],
                                ['match_phrase' => ["HJNR" => $fuName]],
                                ['range' => ["ZXSJ" => ['from' => $fenchuangTime, 'to' => date('Y-m-d H:i:s', strtotime($fenchuangTime) + 48 * 3600)]]]
                            ];
                            $params = $bl01esService->clearMust()->queryByMustNot(['term' => ["MBLB" => 295]])->queryByMustBatch($bl01must)->source(['BLBH', 'ZXSJ'])->getParams();
                            $bl01Res = app('es')->search($params);
                            $bl01Res = $bl01esService->getDataByEs($bl01Res);
                            $bl01294Fu = empty($bl01Res[0]) ? [] : $bl01Res[0];
                        }
                        if (empty($bl01294Zheng) && empty($bl01294Fu) && empty($bl0118)) {
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
                                    $error .= '正' . $item['ZXSJ'] . '、';
                                }
                            }
                            if ($bl01294Fu) {
                                foreach ($bl01294Fu as $item) {
                                    $error .= '副' . $item['ZXSJ'] . '、';
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
        $setName = 'quality_zb_operationComplete_v2';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        $page = 0;
        $pageSize = 100;
        $piEesService = new ElasticsearchService('patient_info');
        $mzjlesService = new ElasticsearchService('mzjl_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');
        $sssqesService = new ElasticsearchService('sssq_2023');
        while (1) {
            $params = $piEesService->paginate($page, $pageSize)
                ->queryByMust(['range' => ['MED_REC_ID' => ['gt' => $lastId]]])
                ->orderBy('MED_REC_ID', 'asc')
                ->queryByMust(['range' => ['AAC01' => ['lt' => $this->startTime]]])
                ->getParams();

            $res = app('es')->search($params);
            $res = $piEesService->getDataByEs($res);
            if (empty($res[0])) {
                break;
            }
            $fenzi = [];
            $patientInfo = $res[0];
            foreach ($patientInfo as $p) {
                $lastId = $p['MED_REC_ID'];
                // 获取手术申请单，如果存在，则满足分母的条件
                $sssqMust = [];
                $sssqMust[] = [
                    'term' => [
                        "ZYH" => $p['MED_REC_ID']
                    ]
                ];
                $sssqMust[] = [
                    'term' => [
                        "ZFBZ" => 0
                    ]
                ];
                $params = $sssqesService->clearMust()->queryByMustBatch($sssqMust)->getParams();
                $mzRes = app('es')->search($params);
                $mzRes = $sssqesService->getDataByEs($mzRes);
                if (empty($mzRes[0])) {
                    continue;
                }

                $operateCompletionRateError = [];
                $flag = 1;
                $sssq = $mzRes[0];
                // 获取手术申请相关的麻醉记录信息
                foreach ($sssq as $item) {
                    $error = '手术名称【' . $item['NSSMC'] . '】';
                    $mzjlmust = [];
                    $mzjlmust[] = ['term' => ["HOSPIZATIONID" => $p['MED_REC_ID']]];
                    $mzjlmust[] = ['term' => ["PREOPERATIONNAME" => $item['NSSMC']]];
                    $params = $mzjlesService->clearMust()->queryByMustBatch($mzjlmust)->source(['OPERATEENDTIME'])->getParams();
                    $mzRes = app('es')->search($params);
                    $mzRes = $mzjlesService->getDataByEs($mzRes);
                    $startTime = 0;
                    if (empty($mzRes[0]) || empty($mzRes[0][0])) {
                        $error .= "麻醉记录【无】";
                    } else {
                        $startTime = strtotime($mzRes[0][0]['OPERATEENDTIME']);
                        $error .= '，手术结束时间（手麻）【' . $mzRes[0][0]['OPERATEENDTIME'] . '】';
                    }

                    // 获取手术记录
                    $bl01must = [];
                    $bl01must[] = ['term' => ["JZHM" => $p['MED_REC_ID']]];
                    $bl01must[] = ['term' => ["BLLB" => 303]];
                    $bl01Should = [];
                    $bl01Should[] = ['terms' => ["MBLB" => [306, 74]]];
                    $bl01Should[] = ['match_phrase' => ["BLMC" => "手术记录"]];
                    $params = $bl01esService->clearMust()->queryByShouldBatch($bl01Should)->queryByMustBatch($bl01must)->minimumShouldMatch(1)->source(['ZXSJ', 'MBLB', 'operation_end_time'])->getParams();
                    $bl01Res = app('es')->search($params);
                    $bl01Res = $bl01esService->getDataByEs($bl01Res);
                    if (empty($bl01Res[0]) || empty($bl01Res[0][0])) {
                        $flag = 0;
                        $error .= '，手术记录【无】';
                    } else {
                        if (empty($startTime) && !empty($bl01Res[0][0]['operation_end_time'])) {
                            $startTime = $bl01Res[0][0]['operation_end_time'];
                            $error .= '，手术结束时间【' . date("Y-m-d H:i:s", $bl01Res[0][0]['operation_end_time']) . '】';
                        }
                        $cjsj = strtotime($bl01Res[0][0]['ZXSJ']);
                        $error .= '，手术记录【' . $bl01Res[0][0]['ZXSJ'] . '';
                        if ($startTime) {
                            $endTime = $startTime + 24 * 3600;

                            if ($cjsj > $endTime) {
                                $flag = 0;
                                $error .= '、执行时间超24小时】';
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
        Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
    }

    /**
     * @param array $where
     * @param int $type
     * @return mixed
     * @throws Exception
     * 获取绩效考核指标数据
     */
    public
    function getList(array $where = [], int $type = 0)
    {

        if ($type === 0) {
            $res = $this->leaveHospital($where); // 出院患者手术占比
        } elseif ($type === 1) {
            $res = $this->gradeFour($where); // 出院患者四级手术占比
        } elseif ($type === 2) {
            $res = $this->infectRadio($where);// I类切口手术部位感染率
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
    function getZbv2($type = 0, $where = [])
    {

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

        $esService = new ElasticsearchService('patient_info_target_temporary');
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

            $res = bcdiv((string)$numerator, (string)$denominator, 4);
            $resArr[] = ['numerator' => $numerator, 'denominator' => $denominator, 'res' => $res, 'time' => $key, 'source' => '系统提取'];
        }
        $resArr[] = self::zbCount($resArr);
        return $resArr;
    }

    public
    static function zbCount($resArr = [])
    {
        $numerator = array_sum(array_column($resArr, 'numerator'));
        $denominator = array_sum(array_column($resArr, 'denominator'));
        $count = [
            'numerator' => $numerator,
            'denominator' => $denominator,
            'res' => $denominator ? round($numerator / $denominator, 4) : 0,
            'time' => '全年', 'source' => '系统提取'];
        return $count;
    }

    /**
     * @param array $where
     * @return array|int
     * 出院患者手术占比
     */
    public
    function leaveHospital(array $where = [])
    {
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
        $res = bcdiv((string)$operation, (string)$operationTotal, 4);

        return ['numerator' => $operation, 'denominator' => $operationTotal, 'res' => $res];
    }

    /**
     * @param array $where
     * @return array|int
     * 出院患者四级手术占比
     */
    public
    function gradeFour(array $where = [])
    {
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

        $res = bcdiv((string)$operation, (string)$operationTotal, 4);

        return ['numerator' => $operation, 'denominator' => $operationTotal, 'res' => $res];
    }

    /**
     * @param array $where
     * @return array|int
     * I类切口手术部位感染率
     */
    public
    function infectRadio(array $where = [])
    {
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

        $res = bcdiv((string)$operation, (string)$operationTotal, 4);
        return ['numerator' => $operation, 'denominator' => $operationTotal, 'res' => $res];
    }

    /**
     * @param array $where
     * @return array|int
     * 出院患者微创手术占比
     */
    public
    function miniInvasive(array $where = [])
    {
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

        $res = bcdiv((string)$operation, (string)$operationTotal, 4);
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
    function complication(array $where = [])
    {
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

        $res = bcdiv((string)$operation, (string)$operationTotal, 4);

        return ['numerator' => $operation, 'denominator' => $operationTotal, 'res' => $res];
    }


    /**
     * 病理检查记录符合率
     * php artisan command:feeClean
     * update patient_info_target set denominator_bl=0,numerator_bl=0,bl_error="";
     */
    public
    function bingliData(array $where = [])
    {

        $setName = 'quality_zb_bingli_v2';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        $pageSize = 500;
        while (1) {
            var_dump('最新住院号：' . $lastId);
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->join('fee_detailed', 'fee_detailed.AAA28', '=', 'patient_info.MED_REC_ID')
                ->where('fee_detailed.is_bingli', '=', 1)
                ->where('patient_info.id', '>', $lastId)
                ->where("patient_info.AAC01", '<', $this->startTime)
                ->orderBy('patient_info.id', 'asc')
                ->limit($pageSize)
                ->get(['patient_info.MED_REC_ID', 'patient_info.AAA28', 'patient_info.AAB01', 'patient_info.AAC01', 'fee_detailed.FYMC', 'fee_detailed.pre_FYMC'])->toArray();
            if (empty($data)) {
                break;
            }

            $newData = [];
            foreach ($data as $d) {
                $lastId = $d['id'];
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
                    }else{
                        continue;
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
                    ->where('PACS.ExamType', '=', 7)
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
                $numerator[] = ['ZYH' => $d['MED_REC_ID'], 'numerator_bl' => $isError, 'bl_error' => $bl_error];
            }

            if ($numerator) {
                foreach ($numerator as $d) {
                    PatientInfoTarget::query()->updateOrInsert(
                        ['ZYH' => $d['ZYH']],
                        ['denominator_bl' => 1, 'numerator_bl' => $d['numerator_bl'], 'bl_error' => $d['bl_error']]
                    );
                }
            }
            var_dump(count($numerator));
        }
        Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
        var_dump('bingli_en');
    }

    /**
     * 抗菌药物使用记录符合率
     */
    public
    function kjyw($type = 0)
    {

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

            $res = bcdiv((string)$numerator, (string)$denominator, 4);
            $resArr[] = ['numerator' => $numerator, 'denominator' => $denominator, 'res' => $res, 'time' => $year . '-' . sprintf('%02s', $i), 'source' => '系统提取'];
        }
        $resArr[] = self::zbCount($resArr);
        return $resArr;
    }


    /**
     * 抗菌药物病例的数据清洗，将含有抗菌药物的病例信息打上标识
     * update patient_info_target set denominator_kjyw=0,numerator_kjyw=0,kjyw_error="";
     */
    public
    function kjywData()
    {
        $setName = 'quality_zb_kjyw_v2';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        $pageSize = 500;
        while (1) {
            // 所有符合条件的病例信息
            // SELECT * FROM `patient_info` as a left join yzb as b on a.MED_REC_ID=b.ZYH
            // where a.denominator_kjyw=0 and b.is_has_kjyw=1
            $data = PatientInfo::query()
                ->Join('yzb', 'patient_info.MED_REC_ID', '=', 'yzb.ZYH')
                ->where('yzb.is_has_kjyw', '=', 1)
                ->where('patient_info.id', '>', $lastId)
                ->where("patient_info.AAC01", '<', $this->startTime)
                ->whereNotIn('yzb.kjyw_name', ['哌拉西林钠他唑巴坦钠','头孢唑林钠'])
                ->limit($pageSize)
                ->orderBy('patient_info.id', 'asc')
                ->groupBy(['yzb.ZYH', 'yzb.YZMC'])
                ->get(['patient_info.id', 'yzb.XMLB', 'yzb.YZQX', 'yzb.ZYH', 'yzb.YZMC', 'yzb.KZSJ', 'yzb.kjyw_name', 'yzb.JLDW', 'yzb.YCJL', 'yzb.SYPC'])->toArray();

            if (!$data) {
                break;
            }
            $newData = [];
            foreach ($data as $d) {
                $lastId = $d['id'];
                $newData[$d['ZYH']][] = $d;
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
                    $kjyw_name = $y['KZSJ'] . '-' . $y['YZMC'] . ($y['YZQX'] == 1 ? '（长期医嘱）' : '') . ($y['YZQX'] == 2 ? '（临时医嘱）' : '');
                    $bingcheng = EMR_BL_BL01::query()
                        ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                        ->where('EMR_BL_BL01.JZHM', '=', $y['ZYH'])
                        ->where('EMR_BL_BL01.BLLB', '=', 294)
                        ->where('EMR_BL_BL01.BLZT', '!=', 9)
                        ->where('EMR_BL_BLXG.HJNR', 'like', '%' . $y['kjyw_name'] . '%')
                        ->get(['EMR_BL_BLXG.HJNR', 'EMR_BL_BL01.ZXSJ'])->toArray();
                    if (!$bingcheng) {
                        $flag = false;
                        $kjywError[] = '医嘱【' . $kjyw_name . '】' . '病程记录【 无  ' . $y['kjyw_name'] . '】';
                    }else {
                        $kjywError[] = '医嘱有【' . $kjyw_name . '】' . '病程记录有【' . $bingcheng[0]['ZXSJ'] . ' - ' . $y['kjyw_name'] . '】';
                    }
                }
                $kjywErrorContent = '';
                foreach ($kjywError as $k => $item) {
                    $kjywErrorContent .= ($k + 1) . '：' . $item;
                }
                $fenzi[] = ['ZYH' => $med, 'numerator_kjyw' => ($flag === true ? 1 : 0), 'kjyw_error' => $kjywErrorContent];
                var_dump($med);
            }

            if ($fenzi) {
                foreach ($fenzi as $d) {
                    PatientInfoTarget::query()->updateOrInsert(
                        ['ZYH' => $d['ZYH']],
                        [
                            'denominator_kjyw' => 1,
                            'numerator_kjyw' => $d['numerator_kjyw'],
                            'kjyw_error' => $d['kjyw_error']
                        ]
                    );
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
        $setName = 'quality_zb_exzlhxzl_v2';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        $pageSize = 500;
        while (1) {
            // 所有符合条件的病例信息
            $data = $bgdObj = PatientInfo::query()
                ->Join('yzb', 'patient_info.MED_REC_ID', '=', 'yzb.ZYH')
                ->where('yzb.is_has_hlyw', '=', 1)
                ->where("patient_info.AAC01", '<', $this->startTime)
                ->where('patient_info.id', '>', $lastId)
                ->limit($pageSize)
                ->groupBy(['yzb.ZYH', 'yzb.YZMC', 'yzb.SYPC', 'yzb.YCJL'])
                ->orderBy('patient_info.id', 'asc')
                ->get(['patient_info.id', 'yzb.YZQX', 'yzb.ZYH', 'yzb.YZMC', 'yzb.KZSJ', 'yzb.hlyw_name', 'yzb.SYPC', 'yzb.YCJL'])->toArray();

            if (!$data) {
                break;
            }
            $newData = [];
            foreach ($data as $d) {
                $lastId = $d['id'];
                $newData[$d['ZYH']][] = $d;
            }
            $fenzi = [];
            foreach ($newData as $med => $data) {
                $flag = true;
                $exzlhxzlError = [];
                foreach ($data as $y) {
                    $hlyw_name = $y['KZSJ'] . '-' . $y['YZMC'] . ($y['YZQX'] == 1 ? '（长期医嘱）' : '') . ($y['YZQX'] == 2 ? '（临时医嘱）' : '') . '/' . $y['SYPC'] . '/' . $y['YCJL'];
                    $bingcheng = EMR_BL_BL01::query()
                        ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                        ->where('EMR_BL_BL01.JZHM', '=', $y['ZYH'])
                        ->where('EMR_BL_BL01.BLLB', '=', 294)
                        ->where('EMR_BL_BL01.BLZT', '<>', 9)
                        ->where('EMR_BL_BLXG.HJNR', 'like', '%' . $y['hlyw_name'] . '%')
                        ->get(['EMR_BL_BLXG.HJNR', 'EMR_BL_BL01.ZXSJ'])->toArray();
                    if (!$bingcheng) {
                        $flag = false;
                        $exzlhxzlError[] = '医嘱有【' . $hlyw_name . '】' . '病程记录【 无  ' . $y['hlyw_name'] . '】';
                    } else {
                        $sTime = strtotime($y['KZSJ']);
                        $ZXSJTime = strtotime($bingcheng[0]['ZXSJ']);
                        $eTime = $sTime + 24 * 3600;
                        if ($ZXSJTime > $eTime) {
                            $flag = false;
                            $exzlhxzlError[] = '医嘱【' . $hlyw_name . '】' . '病程记录【 执行时间超过24小时 】';
                        } else {
                            $exzlhxzlError[] = '医嘱有【' . $hlyw_name . '】病程有【' . $bingcheng[0]['ZXSJ'] . ' - ' . $y['hlyw_name'] . '】';
                        }
                    }
                }

                $exzlhxzlErrorContent = '';
                foreach ($exzlhxzlError as $k => $item) {
                    $exzlhxzlErrorContent .= ($k + 1) . '：' . $item;
                }
                $fenzi[] = ['ZYH' => $med, 'numerator_exzlhxzl' => ($flag === true ? 1 : 0), 'exzlhxzl_error' => $exzlhxzlErrorContent];
                var_dump($med);
            }

            if ($fenzi) {
                foreach ($fenzi as $d) {
                    PatientInfoTarget::query()->updateOrInsert(
                        ['ZYH' => $d['ZYH']],
                        [
                            'denominator_exzlhxzl' => 1,
                            'numerator_exzlhxzl' => $d['numerator_exzlhxzl'],
                            'exzlhxzl_error' => $d['exzlhxzl_error']
                        ]
                    );
                }
            }
        }
        Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
        var_dump('exzlhxzlData_end');
    }

    /**
     * 恶性肿瘤放射治疗记录符合率
     */
    public
    function exzlfszlData(array $where = [])
    {
        $operationNo = ['92.21', '92.22', '92.2201', '92.23', '92.2301', '92.2302', '92.2303', '92.24', '92.2400x002', '92.2400x003', '92.2400x004', '92.2400x005', '92.2400x006', '92.2400x007', '92.25', '92.2501', '92.26', '92.2600x001', '92.2601', '92.2602', '92.27', '92.2700x002', '92.2700x004', '92.2701', '92.2702', '92.2703', '92.2704', '92.2705', '92.2706', '92.28', '92.2801', '92.29', '92.2900x001', '92.2900x002', '92.2900x003', '92.3', '92.3001', '92.3002', '92.31', '92.3101', '92.3102', '92.32', '92.3200x001', '92.3201', '92.3202', '92.33', '92.39', '92.41'];
        $setName = 'quality_zb_exzlfszl_MainOperation_v2';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        $pageSize = 100;
        while (1) {
            $aaa28 = MainOperation::query()
                ->whereIn('ICD9_ID1', $operationNo)
                ->select('AAA28', 'id')
                ->where('id', '>', $lastId)
                ->orderBy('id', 'asc')
                ->limit($pageSize)
                ->get()->toArray();
            if (!$aaa28) {
                break;
            }
            $aaa28 = array_column($aaa28, 'AAA28');
            $this->getFszl($aaa28);
            $lastData = array_pop($aaa28);
            $lastId = $lastData['id'];
        }
        Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);

        $setName = 'quality_zb_exzlfszl_SecondaryOperation_v2';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        while (1) {
            $aaa28 = SecondaryOperation::query()
                ->whereIn('ICD9_ID1', $operationNo)
                ->select('AAA28', 'id')
                ->where('id', '>', $lastId)
                ->orderBy('id', 'asc')
                ->limit($pageSize)
                ->get()->toArray();
            if (!$aaa28) {
                break;
            }
            $aaa28 = array_column($aaa28, 'AAA28');
            $this->getFszl($aaa28);
            $lastData = array_pop($aaa28);
            $lastId = $lastData['id'];
        }
        Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
        var_dump('exzlfszlData_end');
    }

    public function getFszl($aaa28 = [])
    {
        // 所有符合条件的病例信息
        $data = $bgdObj = PatientInfo::query()
            ->Join('yzb', 'patient_info.MED_REC_ID', '=', 'yzb.ZYH')
            ->whereIn('MED_REC_ID', $aaa28)
            ->get(['MED_REC_ID', 'ZYH', 'YZMC', 'KZSJ'])->toArray();

        $newData = [];
        foreach ($data as $v) {
            $newData[$v['ZYH']][] = $v;
        }

        $fenzi = [];
        foreach ($newData as $zyh=>$data){
            var_dump($zyh);
            $exzlfszl_error = '医嘱【无放疗*次】病程记录【无放疗关键字】';
            $numerator_exzlfszl = 0;
            foreach ($data as $y) {
                $yzmc = $y['YZMC'] ?: '';
                $yzmc = str_replace(' ', '', $yzmc);
                if (empty($yzmc)) {
                    continue;
                }
                preg_match_all("/放疗(\d+)次/", $yzmc, $res);
                if (!$res[1]) {
                    continue;
                }
                // 病程记录
                $bingcheng = EMR_BL_BL01::query()
                    ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                    ->where('EMR_BL_BL01.JZHM', '=', $y['ZYH'])
                    ->where('EMR_BL_BL01.BLLB', '=', 294)
                    ->where('EMR_BL_BL01.BLZT', '<>', 9)
                    ->where('EMR_BL_BLXG.is_fangliao', '=', 1)
                    ->get()->toArray();
                if ($bingcheng) {
                    $numerator_exzlfszl = 1;
                    $exzlfszl_error = '医嘱【放疗*次】病程记录【有放疗关键字】';
                    break;
                } else {
                    $exzlfszl_error = '医嘱【放疗*次】病程记录【无放疗关键字】';
                }
            }

            $fenzi[] = ['ZYH' => $zyh, 'numerator_exzlfszl' => $numerator_exzlfszl, 'exzlfszl_error' => $exzlfszl_error];
        }
        if ($fenzi) {
            foreach ($fenzi as $d) {
                PatientInfoTarget::query()->updateOrInsert(['ZYH' => $d['ZYH']], ['denominator_exzlfszl' => 1,'numerator_exzlfszl' => $d['numerator_exzlfszl'], 'exzlfszl_error' => $d['exzlfszl_error']]);
            }
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
    function getZbList($where = [])
    {
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
            $startTime = $where['start_time']. ' 00:00:00';
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

        $esService = new ElasticsearchService('patient_info_target_temporary');
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
            if ($id == 60 || $id == 61){
                $ngXgData = $this->getNgXgData($id, $d);
                if (!$ngXgData){
                    $d['fbsj'] = '';
                    $d['fbsj_s'] = ''; //小时格式的发病时间
                    $d['zhusu'] = '';
                    $d['sssj'] = '';
                    $d['zyzbbh'] = '';
                    $d['zyzdmc'] = '';
                    $d['ssbh'] = '';
                    $d['ssmc'] = '';
                }else{
                    $d['fbsj'] = $ngXgData->fbsj_datetime;
                    $d['fbsj_s'] = $ngXgData->fbsj_s;
                    $d['zhusu'] = $ngXgData->zhusu;
                    $d['sssj'] = $ngXgData->sssj_datetime;
                    $d['zyzbbh'] = $ngXgData->ICD10_ID1;
                    $d['zyzdmc'] = $ngXgData->ICD10_NAME;
                    $d['ssbh'] = $ngXgData->ICD9_ID1;
                    $d['ssmc'] = $ngXgData->ICD9_NAME;
                }

                if ($d['description']) {
                    preg_replace("/发病时间【(.*?)】/", '发病时间【' . $d['fbsj'] . '】', $d['description']);
                    preg_replace("/发病时间-时【(.*?)】/", '发病时间-时【' . $d['fbsj_s'] . '】', $d['description']);
                }
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
    public function getNgXgData($type,$data)
    {
        $BLBH = '';
        if ($type == 60){
            $BLBH = $data['NG_BLBH'];
        }
        if ($type == 61){
            $BLBH = $data['XG_BLBH'];
        }

        return Pszb::query()->where('BLBH',$BLBH)->first();
    }



    /**
     * 不合理的数据复制
     * update patient_info_target set numerator_bhlbl=0,denominator_bhlbl=0,bhlbl_content="";
     */
    public
    function buheliCopy()
    {

        $setName = 'quality_zb_buheli_v2';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;

        $pageSize = 1000;
        while (1) {
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->where("AAC01", '<', $this->startTime)
                ->where("id", '>', $lastId)
                ->limit($pageSize)
                ->orderBy('id', 'asc')
                ->get(['MED_REC_ID', 'id'])->toArray();

            if (!$data) {
                break;
            }

            $nos = array_column($data, 'MED_REC_ID');
            // 获取病例对应的病程信息
            $bingcheng = EMR_BL_BL01::query()
                ->whereIn('JZHM', $nos)
                ->where('EMR_BL_BL01.BLZT', '<>', 9)
                ->where('BLLB', '=', '294')
                ->get(['JZHM', 'bcts', 'bc_content', 'MBLB', 'BLMC'])
                ->toArray();
            if (empty($bingcheng)) {
                continue;
            }

            $newBingcheng = [];
            foreach ($bingcheng as $val) {
                if (!$val['bcts'] && !$val['bc_content']) {
                    continue;
                }
                if (strpos($val['BLMC'], '首次病程') !== false) {
                    $newBingcheng[$val['JZHM']]['tese'] = $val;
                }
                $newBingcheng[$val['JZHM']]['other'][] = $val;
            }
            // 获取病例对应的入院信息
            $xbs = Bllb292::query()->whereIn('ZYH', $nos)->get(['ZYH', 'XBS'])->toArray();
            $xbs = array_column($xbs, null, 'ZYH');

            $fenzi = [];
            foreach ($data as $y) {
                $lastId = $y['id'];

                if (empty($xbs[$y['MED_REC_ID']])) {
                    continue;
                }

                if (empty($newBingcheng[$y['MED_REC_ID']]['tese'])) {
                    continue;
                }
                $tese = $newBingcheng[$y['MED_REC_ID']]['tese'] ? $newBingcheng[$y['MED_REC_ID']]['tese']['bcts'] : '';
                if (!$tese) {
                    continue;
                }

                $tese = str_replace("：", ":", $tese);
                $tese = str_replace(['.', '(', ')', '（', '）', "“", "”", "，", "。", "；", "、", ' '], "", $tese);
                // 病程特色内容
                $teseContentArray = preg_split('//u', $tese, null, PREG_SPLIT_NO_EMPTY);
                $teseContentArray = array_unique(array_filter($teseContentArray));

                // 入院记录
                $ryjlXbs = $xbs[$y['MED_REC_ID']]['XBS'];
                if(empty($ryjlXbs)){
                    continue;
                }
                $ryjlXbs = str_replace("：", ":", $ryjlXbs);
                $ryjlXbs = str_replace(['.', '(', ')', '（', '）', "“", "”", "，", "。", "；", "、", ' '], "", $ryjlXbs);
                $ryjlXbsContentArray = preg_split('//u', $ryjlXbs, null, PREG_SPLIT_NO_EMPTY);
                $ryjlXbsContentArray = array_unique(array_filter($ryjlXbsContentArray));
                // 获取交集
                $res = array_intersect($teseContentArray, $ryjlXbsContentArray);
                var_dump($lastId.' - '.(count($res) / count($teseContentArray)));
                if (count($res) / count($teseContentArray) >= 0.90) {
                    $fenzi[] = ['MED_REC_ID' => $y['MED_REC_ID'], 'bhlbl_content' => '病例特点【95%内容】和入院记录雷同'];
                }


//                // 1、 整个病程中，每次记录的病程不能相同
//                $other = !empty($newBingcheng[$y['MED_REC_ID']]['other']) ? $newBingcheng[$y['MED_REC_ID']]['other'] : [];
//                $isIdentical = false;
//                $resBc = '';
//                foreach ($other as $key => $val) {
//                    $bcContent = $val['bc_content'] ?: '';
//                    if (empty($bcContent)) {
//                        continue;
//                    }

                    // 将连续内容按照75%拆分
//                    $chuckStr = [];
////                    $strLength = mb_strlen($bcContent);
////                    $checkLength = ceil($strLength * 0.75);
////                    for ($i = 0; $i < $strLength; $i++) {
////                        $resStr = mb_substr($bcContent, $i, $checkLength);
////                        if (mb_strlen($resStr) < $checkLength) {
////                            break;
////                        }
////                        if ($resStr) {
////                            $chuckStr[] = $resStr;
////                        }
////                    }
///
///

//                    $bcContentArray = preg_split('//u', $bcContent, null, PREG_SPLIT_NO_EMPTY);
//                    $bcContentArray = array_unique(array_filter($bcContentArray));
//
//                    $isIdenticalOther = false;
//                    foreach ($other as $key1 => $val1) {
//                        // 比较过的和数据本身不比较
//                        if ($key >= $key1 || empty($val1['bc_content'])) {
//                            continue;
//                        }
//
//                        $bcContentArray1 = preg_split('//u', $val1['bc_content'], null, PREG_SPLIT_NO_EMPTY);
//                        $bcContentArray1 = array_unique(array_filter($bcContentArray1));
//                        // 获取交集
//                        $res = array_intersect($bcContentArray, $bcContentArray1);
//                        if (count($res) / count($bcContentArray) >= 0.75) {
//                            $fenzi[] = ['MED_REC_ID' => $y['MED_REC_ID'], 'bhlbl_content' => ' 病程记录 【' . $val['BLMC'] . '】和病程【' . $val1['BLMC'] . '】 75%雷同'];
//                            $isIdenticalOther = true;
//                            $isIdentical = true;
//                            break;
//                        }
//
////                        foreach ($chuckStr as $item) {
////                            if (strpos($val1['bc_content'], $item) !== false) {
////                                $fenzi[] = ['MED_REC_ID' => $y['MED_REC_ID'], 'bhlbl_content' => ' 病程记录 【' . $val['BLMC'] . '】和病程【' . $val1['BLMC'] . '】 75%雷同'];
////                                $isIdenticalOther = true;
////                                $isIdentical = true;
////                                break;
////                            }
////                        }
//                    }
//                    if ($isIdenticalOther === true) {
//                        break;
//                    }
//                }
//
//                // 如果病程之间没有雷同，则校验病例特色和现病史之间的雷同
//                if ($isIdentical === false) {
//
//                    if (empty($xbs[$y['MED_REC_ID']])) {
//                        continue;
//                    }
//
//                    if (empty($newBingcheng[$y['MED_REC_ID']]['tese'])) {
//                        continue;
//                    }
//                    $tese = $newBingcheng[$y['MED_REC_ID']]['tese'] ? $newBingcheng[$y['MED_REC_ID']]['tese']['bcts'] : '';
//                    if (!$tese) {
//                        continue;
//                    }
//                    // 病程特色内容
//                    $teseContentArray = preg_split('//u', $tese, null, PREG_SPLIT_NO_EMPTY);
//                    $teseContentArray = array_unique(array_filter($teseContentArray));
//
//                    // 入院记录
//                    $ryjlXbs = $xbs[$y['MED_REC_ID']]['HJNR'];
//                    $ryjlXbsContentArray = preg_split('//u', $ryjlXbs, null, PREG_SPLIT_NO_EMPTY);
//                    $ryjlXbsContentArray = array_unique(array_filter($ryjlXbsContentArray));
//                    // 获取交集
//                    $res = array_intersect($teseContentArray, $ryjlXbsContentArray);
//                    if (count($res) / count($teseContentArray) >= 0.75) {
//                        $fenzi[] = ['MED_REC_ID' => $y['MED_REC_ID'], 'bhlbl_content' => '病例特点【75%内容】和入院记录雷同'];
//                    }
//
//                    // 病程特色内容
////                    $chuckStr = [];
////                    $strLength = mb_strlen($tese);
////                    $checkLength = ceil($strLength * 0.75);
////                    for ($i = 0; $i < $strLength; $i++) {
////                        $resStr = mb_substr($tese, $i, $checkLength);
////                        if (mb_strlen($resStr) < $checkLength) {
////                            break;
////                        }
////                        if ($resStr) {
////                            $chuckStr[] = $resStr;
////                        }
////                    }
////
////                    // 入院记录
////                    $ryjl = $xbs[$y['MED_REC_ID']]['HJNR'];
////                    foreach ($chuckStr as $item) {
////                        if (strpos($ryjl, $item) !== false) {
////                            $fenzi[] = ['MED_REC_ID' => $y['MED_REC_ID'], 'bhlbl_content' => '病例特点【75%内容】和入院记录雷同'];
////                            break;
////                        }
////                    }
//                }
            }


            $allData = array_unique($nos);
            if ($allData) {
                foreach ($allData as $d) {
                    PatientInfoTarget::query()->updateOrInsert(['ZYH' => $d], ['denominator_bhlbl' => 1]);
                }
            }
            if ($fenzi) {
                foreach ($fenzi as $d) {
                    PatientInfoTarget::query()->updateOrInsert(['ZYH' => $d['MED_REC_ID']], ['numerator_bhlbl' => 1, 'bhlbl_content' => $d['bhlbl_content']]);
                }
            }
        }
        Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
        var_dump('numerator_bhlbl_end');
    }

    /**
     * 手术相关记录完整率
     * update EMR_BL_BLXG set is_operation=1 where HJNR like "%术前小结及术前讨论结论记录%"
     * update patient_info_target set denominator_operation=0,numerator_operation=0,operation_error="";
     */
    public
    function operationComplete()
    {
        $setName = 'quality_zb_operationComplete_v2';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        $pageSize = 500;
        while (1) {
            // 所有符合条件的病例信息
            $patientInfo = $patientInfoCopy = PatientInfo::query()
                ->where('id', '>', (int)$lastId)
                ->orderBy('id', 'asc')
                ->where("AAC01", '<', $this->startTime)
                ->limit($pageSize)
                ->get(['MED_REC_ID', 'AAC01', 'id'])->toArray();
            if (!$patientInfo) {
                break;
            }
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
            $lastId = $lastData['id'];
            var_dump($lastId);

            $fenzi = [];
            foreach ($newData as $zyh => $data) {
                $zyh = (string)$zyh;
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
                            $bingcheng = EMR_BL_BL01::query()
                                ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                                ->where('EMR_BL_BL01.JZHM', '=', $y['ZYH'])
                                ->where('EMR_BL_BL01.BLLB', '=', 294)
                                ->where('EMR_BL_BL01.BLZT', '<>', 9)
                                ->where('EMR_BL_BLXG.is_operation', '=', 1)
                                ->limit(1)
                                ->get(['ZXSJ'])->toArray();
                            if (!$bingcheng) {
                                $operation_error .= '术前小结及术前讨论结论记录【无】';
                                $isError = 0;
                            } else {
                                $cjsj = strtotime($bingcheng[0]['ZXSJ']);
                                $operation_error .= '术前小结及术前讨论结论记录【有、 ' . $bingcheng[0]['ZXSJ'] . '、';
                                if ($cjsj < $kzsj) {
                                    $operation_error .= '<开嘱时间】';
                                } else {
                                    $operation_error .= '>开嘱时间】';
                                }
                            }
                        }


                        // 3、手术记录时间在开嘱时间之后
                        $ssrq = strtotime(date("Y-m-d", strtotime($ssrq))); // 手术日期
                        $ssjl = EMR_BL_BL01::query()
                            ->where('JZHM', '=', $y['ZYH'])
                            ->where('BLMC', 'like', '%手术记录%')
                            ->where(Db::raw('UNIX_TIMESTAMP(ZXSJ)'), '>', $kzsj)
                            ->where('operation_time', '=', $ssrq)
                            ->where('EMR_BL_BL01.BLZT', '<>', 9)
                            ->limit(1)
                            ->get(['ZXSJ', 'operation_time'])->toArray();
                        if (!$ssjl) {
                            $operation_error .= '手术记录【无】';
                            $isError = 0;
                        } else {
                            $operation_error .= '手术记录【（有）、' . $ssjl[0]['ZXSJ'] . '、';
                            if (!empty($bingcheng)) {
                                $cjsj = strtotime($bingcheng[0]['ZXSJ']);
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
//                            ->where(Db::raw('UNIX_TIMESTAMP(EMR_BL_BL01.ZXSJ)'), '>', $kzsj)
                            ->where('EMR_BL_BL01.JZHM', '=', $y['ZYH'])
                            ->whereIn('EMR_BL_BL01.BLLB', [303, 329])
                            ->where('EMR_BL_BL01.BLZT', '<>', 9)
                            ->where('EMR_BL_BLXG.HJNR', 'like', "%手术同意书%")
                            ->limit(1)
                            ->get(['ZXSJ', 'BLMC'])->toArray();
                        if (!empty($sstys1)) {
                            $operation_error .= '手术同意书【' . $sstys1[0]['BLMC'] . '（有） 】';
                        } else {
                            $sstys2 = EMR_BL_BL01::query()
                                ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
//                            ->where(Db::raw('UNIX_TIMESTAMP(EMR_BL_BL01.ZXSJ)'), '>', $kzsj)
                                ->where('EMR_BL_BL01.JZHM', '=', $y['ZYH'])
                                ->whereIn('EMR_BL_BL01.BLLB', [303, 329])
                                ->where('EMR_BL_BL01.BLZT', '<>', 9)
                                ->where('EMR_BL_BLXG.HJNR', 'like', "%知情同意书%")
                                ->limit(1)
                                ->get(['ZXSJ', 'BLMC'])->toArray();
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
                            $bingcheng1 = 0;
                            $bingcheng2 = 0;
                            $bingcheng3 = 0;
                            // 当天出院则只需要出院当天的病程记录
                            if ($cysj == $operationTime) {
                                $bingcheng1 = EMR_BL_BL01::query()
                                    ->where('JZHM', '=', $y['ZYH'])
                                    ->where('BLLB', '=', 294)
                                    ->where('EMR_BL_BL01.BLZT', '<>', 9)
                                    ->whereBetween(Db::raw('UNIX_TIMESTAMP(ZXSJ)'), [$operationTime, $operationTime + 24 * 3600])
                                    ->get(['ZXSJ', 'BLMC'])->toArray();
                                $bingcheng2 = 1;
                                $bingcheng3 = 1;
                            } else {
                                // 如果不是手术当天出院，则需要需要查术后三天
                                if ($cysj && $cysj >= $operationTime + 24 * 3600) {
                                    $bingcheng1 = EMR_BL_BL01::query()
                                        ->where('JZHM', '=', $y['ZYH'])
                                        ->where('BLLB', '=', 294)
                                        ->where('EMR_BL_BL01.BLZT', '!=', 9)
                                        ->whereBetween(Db::raw('UNIX_TIMESTAMP(ZXSJ)'), [$operationTime + 24 * 3600, $operationTime + 24 * 3600 * 2])
                                        ->get(['ZXSJ', 'BLMC'])->toArray();
                                    $bingcheng2 = 1;
                                    $bingcheng3 = 1;
                                }
                                if ($cysj && $cysj >= $operationTime + 2 * 24 * 3600) {
                                    $bingcheng2 = EMR_BL_BL01::query()
                                        ->where('JZHM', '=', $y['ZYH'])
                                        ->where('BLLB', '=', 294)
                                        ->where('EMR_BL_BL01.BLZT', '!=', 9)
                                        ->whereBetween(Db::raw('UNIX_TIMESTAMP(ZXSJ)'), [$operationTime + 2 * 24 * 3600, $operationTime + 24 * 3600 * 3])
                                        ->get(['ZXSJ', 'BLMC'])->toArray();
                                    $bingcheng3 = 1;
                                }
                                if ($cysj && $cysj >= $operationTime + 3 * 24 * 3600) {
                                    $bingcheng3 = EMR_BL_BL01::query()
                                        ->where('JZHM', '=', $y['ZYH'])
                                        ->where('BLLB', '=', 294)
                                        ->where('EMR_BL_BL01.BLZT', '!=', 9)
                                        ->whereBetween(Db::raw('UNIX_TIMESTAMP(ZXSJ)'), [$operationTime + 3 * 24 * 3600, $operationTime + 24 * 3600 * 4])
                                        ->get(['ZXSJ', 'BLMC'])->toArray();
                                }
                            }

                            if (!$bingcheng1 || !$bingcheng2 || !$bingcheng3) {
                                $operation_error .= '术后病程记录【（无）】';
                                $isError = 0;
                            } else {
                                $operation_error .= '术后病程记录【（有）、';
                                if (!empty($bingcheng1[0]['ZXSJ'])) {
                                    $operation_error .= $bingcheng1[0]['ZXSJ'] . '、';
                                }
                                if (!empty($bingcheng2[0]['ZXSJ'])) {
                                    $operation_error .= $bingcheng2[0]['ZXSJ'] . '、';
                                }
                                if (!empty($bingcheng3[0]['ZXSJ'])) {
                                    $operation_error .= $bingcheng3[0]['ZXSJ'] . '、';
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
    function getIrcrData(array $where = [])
    {
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
            $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
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
        $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
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
    function getZrwData(array $where = [])
    {
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
            $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
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
        $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
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
    function getXjpyData(array $where = [])
    {
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
            $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
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
        $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
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
    function getLcyxData(array $where = [])
    {
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
            $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
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
        $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
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
                    $res = bcdiv((string)$numerator, (string)$zbData->denominator, 4);
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
            $yearData['res'] = bcdiv((string)$yearData['numerator'], (string)$yearData['denominator'], 4);
        }
        $data[] = $yearData;

        return $data;
    }


    public function checkCaseList()
    {

        $page = 1;
        $pageSize = 10;
        $column = ['BLBH', 'MBLB'];
        while (1) {
            $res = EMR_BL_BL01::getList($page, $pageSize, $column, ['BLLB' => 294]);
            if (!$res) {
                echo '获取数据完毕';
                break;
            }
            foreach ($res as $no) {
                $checkRes = self::checkCase($no['BLBH'], $no['MBLB']);
                if (empty($checkRes)) {
                    continue;
                }
                if ($no['MBLB'] == 295) {
                    $eidtData = ['bcts' => $checkRes];
                } else {
                    $eidtData = ['bc_content' => $checkRes];
                }
                $res = EMR_BL_BL01::updateById($no['BLBH'], $eidtData);
                var_dump($res);
            }
            $page++;
        }

        var_dump("病程信息处理完毕");
    }

    /**
     * @param string $no
     * @param string $mblb
     * @return array|mixed|string
     */
    public static function checkCase($no = '', $mblb = '')
    {
        $errorNotice = [];
        $title = ['病例特点', '诊断依据', '鉴别诊断', '诊疗计划'];

        if (empty($no)) {
            $no = self::ID;
        }
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

        return $bcStr;
    }
}
