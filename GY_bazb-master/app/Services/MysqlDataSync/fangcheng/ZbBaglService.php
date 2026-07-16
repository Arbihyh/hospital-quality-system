<?php

namespace App\Services\MysqlDataSync\fangcheng;

use App\Model\EMR_BL_BLSY;
use App\Model\Bllb294_295;
use App\Model\EMR_BL_BL01;
use App\Model\Indicator;
use App\Model\MedicinalInfo;
use App\Model\PatientInfo;
use App\Model\RuleWordMap;
use App\Model\Staff;
use App\Model\ZbBagl;
use App\Model\BA_RECEIVE;
use App\Model\HomeQuality;
use App\Model\ErrorRule;
use App\Model\MainOperation;
use App\Model\SecondaryOperation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Model\SM_SSAP;
use App\Model\SSCZ;
use App\Model\ZY_BLFY;
use Illuminate\Support\Facades\Log;
use App\Services\PublicService;
use App\Services\ElasticsearchService;
use App\Services\EsSaveService;

class ZbBaglService
{
    public $startTime = '2024-01-01 00:00:00';
    public function __construct() {}

    public function cacheData($zyh, $start, $end)
    {
        $this->ryjl24($zyh, $start, $end);
        $this->cyjl24($zyh, $start, $end);
        $this->basy24($zyh, $start, $end);
        $this->ssjl24($zyh, $start, $end);
        $this->bljcjl($zyh, $start, $end);
        $this->ctmrfhl($zyh, $start, $end);
        $this->hzqjjsl($zyh, $start, $end);
        $this->exzlhxzl($zyh, $start, $end);
        $this->kjywsy($zyh, $start, $end);
        $this->hzqjcgl($zyh, $start, $end);
        $this->bhlfzbl($zyh, $start, $end);
        $this->yscf($zyh, $start, $end);
        $this->lcyx($zyh, $start, $end);
        $this->jjbll($zyh, $start, $end);
        $this->exzlfl($zyh, $start, $end);
        $this->xjpyjcjl($zyh, $start, $end);
        $this->zrw($zyh, $start, $end);
        //$this->cdl($zyh, $start, $end);
        //$this->gdwzl($zyh, $start, $end);
        $this->zyzdbmzql($zyh, $start, $end);
        $this->zyzdzql($zyh, $start, $end);
        $this->zyssbmzql($zyh, $start, $end);
        $this->zysszql($zyh, $start, $end);
        $this->zqtys($zyh, $start, $end);
        $this->ssxgjl($zyh, $start, $end);
    }

    /**
     * 恶性肿瘤放射治疗记录符合率
     */
    public function exzlfl($zyh, $start, $end)
    {
        $page = 1;

        $rule10002 = RuleWordMap::query()->where('id', 10002)->value('keyword');

        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母
        $yzbService = new ElasticsearchService('yzb_2023');
        $bl01Service = new ElasticsearchService('bl01_202303');

        while (true) {
            $data = DB::table($cyzs->table_name)
                ->leftJoin('patient_doctor_info', $cyzs->table_name . '.' . $cyzs->MED_REC_ID, '=', 'patient_doctor_info.AAA28')
                ->when($cyzs->condition, function ($query) use ($cyzs) {
                    return $query->whereRaw($cyzs->condition);
                })
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('ZYH', $zyh);
                    }
                    return $query;
                })
                ->whereBetween('AAC01', [$start, $end])
                ->paginate(500, [$cyzs->table_name . '.' . $cyzs->MED_REC_ID, 'AAB01', 'AAC01', 'AAC11N', 'AEE03'], 'page', $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                $errorContent = [];
                $insert = [
                    'zyh' => $ZYH,
                    'exzlfl_fz' => 0,
                    'exzlfl_fm' => 0,
                    'exzlfl_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                //删除旧数据
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['exzlfl_fm' => null, 'exzlfl_fz' => null, 'exzlfl_error' => null]);

                // 查询医嘱表，查找is_fangliao=1的医嘱
                $must = [
                    ["term" => ['ZYH' => $ZYH]],
                    ["term" => ['is_fangliao' => 1]]
                ];

                $params = $yzbService->clearMust()->queryByMustBatch($must)->paginate(1, 1000)->getParams();
                $yzbRes = app('es')->search($params);
                $yzbData = $yzbService->getDataByEs($yzbRes);

                if (empty($yzbData[0][0])) {
                    continue;
                }

                // 遍历所有放疗医嘱
                foreach ($yzbData[0] as $yzb) {
                    $yzmc = $yzb['YZMC'] ?: '';
                    $yzmc = str_replace(' ', '', $yzmc);

                    if (empty($yzmc) || (!empty($rule10002) && $yzb['YZZT'] != $rule10002)) {
                        continue;
                    }

                    // 符合分母条件
                    $insert['exzlfl_fm'] = 1;

                    $orderGroup = [
                        'status' => 0,
                        'content' => []
                    ];

                    $orderGroup['content'][] = [
                        'status' => 1,
                        'content' => "医嘱名称【" . $yzmc . "】"
                    ];

                    // 查询病程记录，BLLB=294且包含放疗关键字
                    $must = [
                        ["term" => ['JZHM' => $ZYH]],
                        ["term" => ['BLLB' => 294]],
                        ["match_phrase" => ['HJNR' => '放疗']]
                    ];

                    $mustnot = [
                        ["term" => ['BLZT' => 9]]
                    ];

                    $params = $bl01Service->clearMust()->queryByMustBatch($must)->queryByMustNotBatch($mustnot)->paginate(1, 1000)->getParams();
                    $bl01Res = app('es')->search($params);
                    $bl01Data = $bl01Service->getDataByEs($bl01Res);

                    if (empty($bl01Data[0])) {
                        $orderGroup['content'][] = [
                            'status' => 0,
                            'content' => "病程记录【无放疗关键字】"
                        ];
                    } else {
                        $orderGroup['status'] = 1;
                        $insert['exzlfl_fz'] = 1;

                        foreach ($bl01Data[0] as $bl01) {
                            $orderGroup['content'][] = [
                                'status' => 1,
                                'content' => "病程记录【" . $bl01['BLMC'] . "】包含放疗关键字"
                            ];
                        }
                    }

                    $errorContent[] = $orderGroup;
                }

                // 保存结果
                if ($insert['exzlfl_fm'] > 0) {
                    $insert['exzlfl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                    Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                }

                //如果zyh不为空，根据zyh更新或插入es
                if (!empty($zyh)) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }
        }

        return true;
    }

    /**
     * 入院记录24小时内完成率
     */
    public function ryjl24($zyh, $start, $end)
    {
        $page = 1;
        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母
        $hsfcsj = ZbBagl::getFirstById(2, true); //护士分床时间
        $bcjlwcsj = ZbBagl::getFirstById(3, true); //病程记录完成时间
        $bcjlbllb = ZbBagl::getFirstById(4, true); //病程记录BLLB
        $ryjlwcsj = ZbBagl::getFirstById(5, true); //入院记录完成时间

        if (strpos($bcjlbllb->keyword, ',')) {
            $bllbs = explode(',', $bcjlbllb->keyword);
        } else {
            $bllbs = [$bcjlbllb->keyword];
        }

        $hsfcsjWhere = [];
        if (strpos($hsfcsj->condition, ',')) {
            $wheres = explode(',', $hsfcsj->condition);
            foreach ($wheres as $val) {
                $where = explode('=', $val);
                $hsfcsjWhere[] = ['term' => [$where[0] => $where[1]]];
            }
        } elseif (!empty($hsfcsj->condition)) {
            $where = explode('=', $hsfcsj->condition);
            $hsfcsjWhere[] = ['term' => [$where[0] => $where[1]]];
        }


        $zyHcmxService = new ElasticsearchService($hsfcsj->table_name);
        $bl01Service = new ElasticsearchService($bcjlwcsj->table_name);
        while (true) {
            //            $data = PatientInfo::query()
            $data = DB::table($cyzs->table_name)
                ->leftJoin('patient_doctor_info', $cyzs->table_name . '.' . $cyzs->MED_REC_ID, '=', 'patient_doctor_info.AAA28')
                ->when($cyzs->condition, function ($query) use ($cyzs) {
                    return $query->whereRaw($cyzs->condition);
                })
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('ZYH', $zyh);
                    }
                    return $query;
                })
                ->whereBetween('AAC01', [$start, $end])
                ->paginate(500, [$cyzs->table_name . '.' . $cyzs->MED_REC_ID, 'AAB01', 'AAC01', 'AAC11N', 'AEE03'], 'page', $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                // 如果当前页码大于最大页码则结束
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                $errorContent = [];
                $insert = [
                    'zyh' => $ZYH,
                    'ryjl24_fz' => 0,
                    'ryjl24_fm' => 0,
                    'ryjl24_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];
                //删除
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['ryjl24_fm' => null, 'ryjl24_fz' => null, 'ryjl24_error' => null]);

                $insert['ryjl24_fm'] = 1;
                $must = [
                    ["term" => [$hsfcsj->MED_REC_ID => $ZYH]],
                ];

                $surgeryGroup = [
                    'status' => 0,
                    'content' => []
                ];

                $must = array_merge($must, $hsfcsjWhere);

                $params = $zyHcmxService->clearMust()->queryByMustBatch($must)->orderBy($hsfcsj->table_field, 'asc')->getParams();
                $restful = app('es')->search($params);
                $zyHcmxData = $zyHcmxService->getDataByEs($restful);
                $HCRQ = !empty($zyHcmxData[0][0][$hsfcsj->table_field]) ? $zyHcmxData[0][0][$hsfcsj->table_field] : '';

                if ($HCRQ) {
                    $surgeryGroup['content'][] = [
                        'status' => 1,
                        'content' => '入院时间【' . $HCRQ . '】'
                    ];
                    $endTime = Carbon::parse($HCRQ)->addHours($ryjlwcsj->keyword)->toDateTimeString();

                    $must = [
                        ["term" => ['JZHM' => $ZYH]],
                    ];

                    $should = [];
                    foreach ($bllbs as $bllb) {
                        $should[] = ["term" => ['BLLB' => $bllb]];
                    }

                    $params = $bl01Service->clearMust()->queryByMustBatch($must)->queryByShouldBatch($should)->queryByMustNot(["term" => ['BLZT' => 9]])->paginate(1, 1000)->getParams();
                    $restful = app('es')->search($params);
                    $bl01Data = $bl01Service->getDataByEs($restful);
                    $CJSJ = !empty($bl01Data[0][0][$bcjlwcsj->table_field]) ? $bl01Data[0][0][$bcjlwcsj->table_field] : '';
                    //var_dump($bl01Data,$must,$bcjlwcsj->table_name);die;
                    if (empty($bl01Data[0])) {
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => '入院记录【无】'
                        ];
                    } else {
                        $surgeryGroup['content'][] = [
                            'status' => 1,
                            'content' => '【' . $bl01Data[0][0]['BLMC'] . '】'
                        ];
                        if (empty($CJSJ)) {
                            $surgeryGroup['content'][] = [
                                'status' => 0,
                                'content' => '首次签名时间【无】'
                            ];
                        } else {

                            if ($CJSJ >= $HCRQ && $CJSJ <= $endTime) {
                                $surgeryGroup['status'] = 1;
                                $insert['ryjl24_fz'] += 1;
                                $surgeryGroup['content'][] = [
                                    'status' => 1,
                                    'content' => '首次签名时间【' . $CJSJ . '】'
                                ];
                            } elseif ($CJSJ < $HCRQ) {
                                $surgeryGroup['content'][] = [
                                    'status' => 0,
                                    'content' => '首次签名时间【' . $CJSJ . '】（提前创建）'
                                ];
                            } elseif ($CJSJ > $endTime) {
                                $surgeryGroup['content'][] = [
                                    'status' => 0,
                                    'content' => '首次签名时间【' . $CJSJ . '】（超24小时）'
                                ];
                            }
                        }
                    }
                }
                if ($insert['ryjl24_fm'] > 0) {
                    $errorContent[] = $surgeryGroup;
                    if (!empty($errorContent)) {
                        $insert['ryjl24_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                        Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                    }
                }
                //如果zyh不为空，根据zyh更新或插入es
                if ($zyh) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }
        }

        return true;
    }

    /**
     * 出院24小时内记录类
     */
    public function cyjl24($zyh, $start, $end)
    {
        $page = 1;

        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母
        $cysj = ZbBagl::getFirstById(6, true); //出院时间 分母
        $swsj = ZbBagl::getFirstById(7, true); //死亡时间
        $cyjlsj = ZbBagl::getFirstById(8, true); //出院记录完成时间
        $cyswjlbllb = ZbBagl::getFirstById(9, true); //出院记录、24小时内入出院记录、死亡记录BLLB
        $cyjlwcsj = ZbBagl::getFirstById(10, true); //出院记录、24小时内入出院记录、死亡记录BLLB

        $bllbs = explode(',', $cyswjlbllb->keyword);
        $bl01Service = new ElasticsearchService($cyjlsj->table_name);
        $yzbService = new ElasticsearchService($cysj->table_name);

        while (true) {
            $data = DB::table($cyzs->table_name)
                ->leftJoin('patient_doctor_info', $cyzs->table_name . '.' . $cyzs->MED_REC_ID, '=', 'patient_doctor_info.AAA28')
                ->when($cyzs->condition, function ($query) use ($cyzs) {
                    return $query->whereRaw($cyzs->condition);
                })
                ->when($zyh, function ($query) use ($zyh, $cyzs) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where($cyzs->table_name . '.' . $cyzs->MED_REC_ID, $zyh);
                    }
                    return $query;
                })
                ->whereBetween('AAC01', [$start, $end])
                ->paginate(500, [$cyzs->table_name . '.' . $cyzs->MED_REC_ID, 'AAB01', 'AAC01', 'AAC11N', 'AEE03'], 'page', $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                $errorContent = [];
                $insert = [
                    'zyh' => $ZYH,
                    'cyjl24_fz' => 0,
                    'cyjl24_fm' => 1, // 分母默认为1
                    'cyjl24_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                //删除
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['cyjl24_fm' => null, 'cyjl24_fz' => null, 'cyjl24_error' => null]);

                $surgeryGroup = [
                    'status' => 0,
                    'content' => []
                ];

                // 查询出院记录
                $cyjlInfo1 = $this->getCyjlError($bl01Service, $yzbService, $ZYH, $bllbs[0], $cysj->keyword, $cyjlsj->table_field, $cyjlwcsj->keyword);
                if ($cyjlInfo1['numerator']) {
                    $insert['cyjl24_fz'] = 1;
                    $surgeryGroup['status'] = 1;
                    $surgeryGroup['content'] = $cyjlInfo1['cyjl_error'];
                } else {
                    // 24小时记录类
                    $cyjlInfo2 = $this->getCyjlError($bl01Service, $yzbService, $ZYH, $bllbs[1], $cysj->keyword, $cyjlsj->table_field, $cyjlwcsj->keyword);
                    if ($cyjlInfo2['numerator']) {
                        $insert['cyjl24_fz'] = 1;
                        $surgeryGroup['status'] = 1;
                        $surgeryGroup['content'] = $cyjlInfo2['cyjl_error'];
                    } else {
                        // 查询死亡记录
                        $cyjlInfo3 = $this->getCyjlError($bl01Service, $yzbService, $ZYH, $bllbs[2], $swsj->keyword, $cyjlsj->table_field, $cyjlwcsj->keyword);
                        if ($cyjlInfo3['numerator']) {
                            $insert['cyjl24_fz'] = 1;
                            $surgeryGroup['status'] = 1;
                            $surgeryGroup['content'] = $cyjlInfo3['cyjl_error'];
                        } else {
                            // 记录未通过的情况
                            if (!empty($cyjlInfo1['cjsj'])) {
                                $surgeryGroup['content'] = $cyjlInfo1['cyjl_error'];
                            } elseif (!empty($cyjlInfo2['cjsj'])) {
                                $surgeryGroup['content'] = $cyjlInfo2['cyjl_error'];
                            } elseif (!empty($cyjlInfo3['cjsj'])) {
                                $surgeryGroup['content'] = $cyjlInfo3['cyjl_error'];
                            } else {
                                $surgeryGroup['content'] = [
                                    [
                                        'status' => 0,
                                        'content' => "出院记录【无】"
                                    ]
                                ];
                            }
                        }
                    }
                }

                if ($insert['cyjl24_fm'] > 0) {
                    $errorContent[] = $surgeryGroup;
                    if (!empty($errorContent)) {
                        $insert['cyjl24_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                        Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                    }
                }
                if (!empty($zyh)) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }
        }

        return true;
    }

    public function basy24($zyh, $start, $end)
    {
        $page = 1;
        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母
        $cysj = ZbBagl::getFirstById(6, true); //出院时间 分母
        $bl01 = ZbBagl::getFirstById(3, true); //id3
        $cyEsService = new ElasticsearchService($cysj->table_name);
        $bl01Service = new ElasticsearchService($bl01->table_name);
        //获取rulewordmap 8000，8001
        $rule8000 = RuleWordMap::query()->where('id', 8003)->value('keyword');
        //是否包含逗号
        $rule8000 = strpos($rule8000, ',') !== false ? explode(',', $rule8000) : [$rule8000];
        $rule8001 = RuleWordMap::query()->where('id', 8001)->value('keyword');
        $rule8001 = strpos($rule8001, ',') !== false ? explode(',', $rule8001) : [$rule8001];



        $cysjWhere = [];
        if (strpos($cysj->condition, ',')) {
            $wheres = explode(',', $cysj->condition);
            foreach ($wheres as $val) {
                $where = explode('=', $val);
                $cysjWhere[] = ['term' => [$where[0] => $where[1]]];
            }
        } elseif (!empty($cysj->condition)) {
            $where = explode('=', $cysj->condition);
            $cysjWhere[] = ['term' => [$where[0] => $where[1]]];
        }

        while (true) {
            $data = DB::table($cyzs->table_name)
                ->leftJoin('patient_doctor_info', $cyzs->table_name . '.' . $cyzs->MED_REC_ID, '=', 'patient_doctor_info.AAA28')
                ->when($cyzs->condition, function ($query) use ($cyzs) {
                    return $query->whereRaw($cyzs->condition);
                })
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('ZYH', $zyh);
                    }
                    return $query;
                })
                ->whereBetween('AAC01', [$start, $end])
                ->paginate(500, [$cyzs->table_name . '.' . $cyzs->MED_REC_ID, 'AAB01', 'AAC01', 'AAC11N', 'AEE03'], 'page', $page);
            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                $errorContent = [];
                $insert = [
                    'zyh' => $ZYH,
                    'basy24_fz' => 0,
                    'basy24_fm' => 1,
                    'basy24_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                //$insert['basy24_fm'] = 1;
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['basy24_fm' => null, 'basy24_fz' => null, 'basy24_error' => null]);

                $surgeryGroup = [
                    'status' => 0,
                    'content' => []
                ];


                $CYSJ = !empty($value->AAC01) ? $value->AAC01 : '';

                if (!empty($CYSJ)) {
                    $surgeryGroup['content'][] = [
                        'status' => 1,
                        'content' => '出院时间【' . $CYSJ . '】'
                    ];
                    //查询yzb_2023,如果yzmc中包含死亡，endtime设置为+7天，否则设置为24小时
                    $yzbService = new ElasticsearchService('yzb_2023');
                    $must = [
                        ["term" => ['ZYH' => $ZYH]],
                    ];
                    foreach ($rule8000 as $rule) {
                        $must[] = ["match_phrase" => ['YZMC' => $rule]];
                    }
                    $params = $yzbService->clearMust()->queryByMustBatch($must)->getParams();
                    $yzbRes = app('es')->search($params);
                    $yzbData = $yzbService->getDataByEs($yzbRes);
                    $endTime = null;
                    if (!empty($yzbData[0][0])) {
                        $endTime = date('Y-m-d H:i:s', strtotime($CYSJ) + (3600 * 24 * 7));
                    } else {
                        $endTime = date('Y-m-d H:i:s', strtotime($CYSJ) + (3600 * 24));
                    }
                    $must = [
                        ["term" => ['JZHM' => $ZYH]],
                        ["terms" => ['BLLB' => $rule8001]]
                    ];
                    //查询bl01符合的
                    $params = $bl01Service->clearMust()->queryByMustBatch($must)->getParams();
                    $bl01Res = app('es')->search($params);
                    $bl01Data = $bl01Service->getDataByEs($bl01Res);
                    $first_blsy_time = null;
                    if (!empty($bl01Data[0][0])) {
                        $first_blsy_time = $bl01Data[0][0][$bl01->table_field];
                        if ($first_blsy_time <= $endTime) {
                            $insert['basy24_fz'] = 1;
                            $surgeryGroup['status'] = 1;
                            $surgeryGroup['content'][] = [
                                'status' => 1,
                                'content' => '首次签名时间【' . $first_blsy_time . '】'
                            ];
                        } else {
                            $surgeryGroup['content'][] = [
                                'status' => 0,
                                'content' => '首次签名时间【' . $first_blsy_time . '(超时)】'
                            ];
                        }
                    } else {
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => '病案首页【无】'
                        ];
                    }
                } else {
                    $surgeryGroup['content'][] = [
                        'status' => 0,
                        'content' => '出院时间【无】'
                    ];
                }

                if ($insert['basy24_fm'] > 0) {
                    $errorContent[] = $surgeryGroup;
                    if (!empty($errorContent)) {
                        $insert['basy24_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                        Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                    }
                }
                if (!empty($zyh)) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }
        }
    }

    /**
     * 手术记录24小时内完成率
     */
    public function ssjl24($zyh, $start, $end)
    {
        $page = 1;

        // 获取规则配置
        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母
        $bagl_11 = ZbBagl::getFirstById(11, true); // 病程记录时间字段
        $rule8019 = RuleWordMap::query()->where('id', 8019)->value('keyword'); // 手术记录MBLB
        $rule8020 = RuleWordMap::query()->where('id', 8020)->value('keyword'); // 手术记录BLMC
        $rule8021 = RuleWordMap::query()->where('id', 8021)->value('keyword'); // 手术记录BLLB
        $rule8047 = RuleWordMap::query()->where('id', 8047)->value('keyword');
        if ($rule8047 === null) {
            $rule8047 = [];
        } else if (strpos($rule8047, ',') !== false) {
            $rule8047 = explode(',', $rule8047);
        } else {
            $rule8047 = [$rule8047];
        }
        $rule8048 = RuleWordMap::query()->where('id', 8048)->value('keyword');
        if ($rule8048 === null) {
            $rule8048 = [];
        } else if (strpos($rule8048, ',') !== false) {
            $rule8048 = explode(',', $rule8048);
        } else {
            $rule8048 = [$rule8048];
        }

        // 初始化服务
        $bl01Service = new ElasticsearchService('bl01_202303');

        while (true) {
            // 查询住院病人
            $data = DB::table($cyzs->table_name)
                ->leftJoin('patient_doctor_info', $cyzs->table_name . '.' . $cyzs->MED_REC_ID, '=', 'patient_doctor_info.AAA28')
                ->when($cyzs->condition, function ($query) use ($cyzs) {
                    return $query->whereRaw($cyzs->condition);
                })
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('patient_doctor_info.AAA28', $zyh);
                    }
                    return $query;
                })
                //->where('MED_REC_ID', '1000000000000000000000000000000000000000')
                ->whereBetween('AAC01', [$start, $end])
                ->paginate(500, [$cyzs->table_name . '.' . $cyzs->MED_REC_ID, 'AAB01', 'AAC01', 'AAC11N', 'AEE03'], 'page', $page);
            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                $errorContent = [];
                $insert = [
                    'zyh' => $ZYH,
                    'ssjl24_fz' => 0,
                    'ssjl24_fm' => 0,
                    'ssjl24_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['ssjl24_fm' => null, 'ssjl24_fz' => null, 'ssjl24_error' => null]);

                // 查询手术申请表
                $surgeries = SM_SSAP::query()
                    ->where('ZYH', $ZYH)
                    ->get();

                //var_dump($ZYH);

                if ($surgeries->isEmpty()) {
                    continue; // 没有手术记录，跳过
                }



                // 用于跟踪所有手术是否都符合条件
                $allSurgeriesCompliant = true;
                //是否有手术
                $hasSurgery = false;

                foreach ($surgeries as $surgery) {
                    $ssmc = $surgery->ICD9_SSCZMC;
                    $ssrq = $surgery->SSRQ;
                    $jsrq = $surgery->JSRQ;
                    if (
                        empty($ssrq) || empty($ssmc) || empty($jsrq) ||
                        strpos($jsrq, '1970-01-01') !== false || $ssmc == 'NULL'
                    ) {
                        continue;
                    }
                    //根据手术名称查询SSCZ的SSLB
                    $ssCZ = SSCZ::query()->where('SSMC', $ssmc)->value('SSLB');
                    if (!empty($ssCZ)) {
                        //如果ssCZ的SSLB不在8047中，则跳过
                        if (!in_array($ssCZ, $rule8047)) {
                            if (!empty($rule8048)) {
                                //如果ssCZ的SSLB在8048中，则跳过
                                if (!in_array($ssmc, $rule8048)) {
                                    continue;
                                }
                            } else {
                                continue;
                            }
                        }
                    }
                    // 只要有手术，分母就加1（每个患者只算一个分母）
                    //$insert['ssjl24_fm'] = 1;
                    $hasSurgery = true;
                    $surgeryGroup = [
                        'status' => 0,
                        'content' => []
                    ];

                    // 添加手术基本信息
                    $surgeryGroup['content'][] = [
                        'status' => 1,
                        'content' => "手术名称【{$surgery->ICD9_SSCZMC}】"
                    ];

                    // 检查手术结束时间
                    $JSRQ = $surgery->JSRQ;
                    if (empty($JSRQ)) {
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "手术结束时间【无】"
                        ];
                        $allSurgeriesCompliant = false; // 这个手术不符合条件
                        $errorContent[] = $surgeryGroup;
                        continue;
                    }

                    $surgeryGroup['content'][] = [
                        'status' => 1,
                        'content' => "手术结束时间【{$JSRQ}】"
                    ];

                    // 计算24小时后的时间点
                    $endTime = date('Y-m-d H:i:s', strtotime($JSRQ) + (24 * 3600));

                    // 查询手术记录
                    $must = [];
                    $must[] = ["term" => ['JZHM' => $ZYH]];

                    // 添加时间范围
                    $must[] = [
                        'range' => [
                            'ZXSJ' => [
                                'gte' => $JSRQ,
                                'lte' => $endTime
                            ]
                        ]
                    ];

                    // 第一组查询：MBLB条件
                    $mustmblb = $must;
                    //$shouldmblb = [];
                    if (!empty($rule8019)) {
                        if (strpos($rule8019, ',') !== false) {
                            $mustmblb[] = ["terms" => ['MBLB' => explode(',', $rule8019)]];
                        } else {
                            $mustmblb[] = ["term" => ['MBLB' => $rule8019]];
                        }
                    }

                    $params = $bl01Service->clearMust()
                        ->queryByMustBatch($mustmblb)
                        //->queryByShouldBatch($shouldmblb)
                        ->queryByMustNot(["term" => ['BLZT' => 9]])
                        ->getParams();

                    $restful = app('es')->search($params);
                    $bl01Datamblb = $bl01Service->getDataByEs($restful);



                    // 合并两组查询结果
                    $allResults = [];
                    if (!empty($bl01Datamblb[0])) {
                        $allResults = $bl01Datamblb[0];
                    } else {
                        // 第二组查询：BLLB条件和BLMC包含"手术"
                        $mustbllb = $must;
                        $shouldbllb = [];

                        if (!empty($rule8021)) {
                            if (strpos($rule8021, ',') !== false) {
                                $mustbllb[] = ["terms" => ['BLLB' => explode(',', $rule8021)]];
                            } else {
                                $mustbllb[] = ["term" => ['BLLB' => $rule8021]];
                            }
                        }

                        if (!empty($rule8020)) {
                            if (strpos($rule8020, ',') !== false) {
                                foreach (explode(',', $rule8020) as $rule) {
                                    $shouldbllb[] = ["match_phrase_prefix" => ['BLMC' => $rule]];
                                }
                            } else {
                                $shouldbllb[] = ["match_phrase_prefix" => ['BLMC' => $rule8020]];
                            }
                        }

                        $params1 = $bl01Service->clearMust()
                            ->queryByMustBatch($mustbllb)
                            ->queryByShouldBatch($shouldbllb)
                            ->minimumShouldMatch(1)
                            ->queryByMustNot(["term" => ['BLZT' => 9]])
                            ->paginate(1, 1000)
                            ->getParams();

                        $restful1 = app('es')->search($params1);
                        $bl01Databllb = $bl01Service->getDataByEs($restful1);
                        if (!empty($bl01Databllb[0])) {
                            $allResults = $bl01Databllb[0];
                        }
                    }

                    $surgeryCriteriaMet = false; // 默认这个手术不符合条件
                    $validRecords = []; // 满足条件的记录
                    $invalidRecords = []; // 不满足条件的记录

                    // 遍历所有病程记录，分类为满足条件和不满足条件的记录
                    if (!empty($allResults)) {
                        foreach ($allResults as $record) {
                            $recordTime = $record[$bagl_11->table_field];

                            // 计算时间差（分钟）
                            $timeDiff = round((strtotime($recordTime) - strtotime($JSRQ)) / 60);

                            if ($timeDiff <= 24 * 60 && $timeDiff >= 0) { // 24小时内
                                $validRecords[] = $record;
                            } else {
                                $invalidRecords[] = $record;
                            }
                        }

                        // 如果有满足条件的记录
                        if (!empty($validRecords)) {
                            $surgeryCriteriaMet = true;

                            // 按时间排序，取最早的一条
                            usort($validRecords, function ($a, $b) use ($bagl_11) {
                                return strtotime($a[$bagl_11->table_field]) - strtotime($b[$bagl_11->table_field]);
                            });

                            $bestRecord = $validRecords[0];
                            $bestRecordTime = $bestRecord[$bagl_11->table_field];

                            $surgeryGroup['status'] = 1;
                            $surgeryGroup['content'][] = [
                                'status' => 1,
                                'content' => "手术记录【{$bestRecord['BLMC']}】"
                            ];
                            $surgeryGroup['content'][] = [
                                'status' => 1,
                                'content' => "首次签名时间【{$bestRecordTime}】"
                            ];
                        } else {
                            // 如果没有满足条件的记录，显示所有不满足条件的记录
                            foreach ($invalidRecords as $record) {
                                $recordTime = $record[$bagl_11->table_field];

                                $surgeryGroup['content'][] = [
                                    'status' => 0,
                                    'content' => "手术记录【{$record['BLMC']}】"
                                ];
                                $surgeryGroup['content'][] = [
                                    'status' => 0,
                                    'content' => "首次签名时间【{$recordTime}】（超时）"
                                ];
                            }
                        }
                    } else {
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "手术记录【无】"
                        ];
                    }

                    if (!$surgeryCriteriaMet) {
                        $allSurgeriesCompliant = false; // 只要有一个手术不符合条件，整体就不符合
                    }

                    $errorContent[] = $surgeryGroup;
                }

                if ($hasSurgery) {
                    $insert['ssjl24_fm'] = 1;
                }

                // 只有当所有手术都符合条件时，分子才加1
                if ($allSurgeriesCompliant && count($surgeries) > 0) {
                    $insert['ssjl24_fz'] = 1;
                }

                if ($insert['ssjl24_fm'] > 0) {
                    $insert['ssjl24_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                    Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                }
                if (!empty($zyh)) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }
        }

        return true;
    }

    /**
     * CT/MRI检查记录符合率
     */
    public function ctmrfhl($zyh, $start, $end)
    {
        $page = 1;

        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母
        $bl01Service = new ElasticsearchService('bl01_202303');
        $bagl_11 = ZbBagl::getFirstById(11, true);
        $bagl_27 = ZbBagl::getFirstById(27, true);
        $rule25 = RuleWordMap::query()->where('id', 25)->value('keyword');
        if (strpos($rule25, ',') !== false) {
            $rule25 = explode(',', $rule25);
        } else {
            $rule25 = [$rule25];
        }
        $rule26 = RuleWordMap::query()->where('id', 26)->value('keyword');
        if (strpos($rule26, ',') !== false) {
            $rule26 = explode(',', $rule26);
        } else {
            $rule26 = [$rule26];
        }
        $rule27 = RuleWordMap::query()->where('id', 27)->value('keyword');
        if (strpos($rule27, ',') !== false) {
            $rule27 = explode(',', $rule27);
        } else {
            $rule27 = [$rule27];
        }
        $rule9009 = RuleWordMap::query()->where('id', 8068)->value('keyword');
        if ($rule9009 === null) {
            $rule9009 = [];
        } else if (strpos($rule9009, ',') !== false) {
            $rule9009 = explode(',', $rule9009);
        } else {
            $rule9009 = [$rule9009];
        }
        $rule9015 = RuleWordMap::query()->where('id', 9015)->value('keyword');
        if ($rule9015 === null) {
            $rule9015 = [];
        } else if (strpos($rule9015, ',') !== false) {
            $rule9015 = explode(',', $rule9015);
        } else {
            $rule9015 = [$rule9015];
        }
        $yzbService = new ElasticsearchService('yzb_2023');

        while (true) {
            // 查询住院病人
            $data = DB::table($cyzs->table_name)
                ->leftJoin('patient_doctor_info', $cyzs->table_name . '.' . $cyzs->MED_REC_ID, '=', 'patient_doctor_info.AAA28')
                ->when($cyzs->condition, function ($query) use ($cyzs) {
                    return $query->whereRaw($cyzs->condition);
                })
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('ZYH', $zyh);
                    }
                    return $query;
                })
                ->whereBetween('AAC01', [$start, $end])
                ->paginate(500, [$cyzs->table_name . '.' . $cyzs->MED_REC_ID, 'AAB01', 'AAC01', 'AAC11N', 'AEE03'], 'page', $page);
            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                $errorContent = [];
                $insert = [
                    'zyh' => $ZYH,
                    'ctmrfhl_fz' => 0,
                    'ctmrfhl_fm' => null,
                    'ctmrfhl_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                //删除
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['ctmrfhl_fm' => null, 'ctmrfhl_fz' => null, 'ctmrfhl_error' => null]);

                // 先查询pacs $pacs = \App\Model\PACS::query()->where("ZYH", $ZYH)->get(["JCMC", "BGSJ"])->toArray(); 
                $pacs = \App\Model\PACS::query()->where("ZYH", $ZYH);
                $pacs->where(function ($query) use ($rule25, $rule26) {
                    $firstItem = true;

                    // 遍历rule25的所有条件
                    foreach ($rule25 as $item) {
                        if ($firstItem) {
                            // 第一个条件使用where，避免生成多余的OR
                            $query->where("JCMC", "like", "%{$item}%");
                            $firstItem = false;
                        } else {
                            // 后续条件使用orWhere
                            $query->orWhere("JCMC", "like", "%{$item}%");
                        }
                    }

                    // 遍历rule26的所有条件
                    foreach ($rule26 as $item) {
                        $query->orWhere("JCMC", "like", "%{$item}%");
                    }
                });

                // 不包含条件：改为"或"关系，满足其中一个就排除
                $pacs->where(function ($query) use ($rule27) {
                    $firstItem = true;

                    foreach ($rule27 as $item) {
                        if ($firstItem) {
                            // 第一个条件使用where，避免生成多余的OR
                            $query->where("JCMC", "not like", "%{$item}%");
                            $firstItem = false;
                        } else {
                            // 后续条件使用andWhere（注意这里是AND关系，但因为是NOT LIKE，所以逻辑上是OR）
                            $query->andWhere("JCMC", "not like", "%{$item}%");
                        }
                    }
                });
                $pacs = $pacs->get(["JCMC", "BGSJ"])->toArray();
                //var_dump($pacs);
                // 用于跟踪所有检查是否都符合条件
                $allCtsCompliant = true;
                if (empty($pacs)) {
                    continue;
                }
                $insert['ctmrfhl_fm'] = 1;


                foreach ($pacs as $p) {
                    $orderGroup = [
                        'status' => 0,
                        'content' => []
                    ];
                    $jcmc = $p['JCMC'];
                    $bgsj = $p['BGSJ'];
                    //报告时间前48小时
                    $bgsj_start = date('Y-m-d H:i:s', strtotime($bgsj) - (48 * 3600));
                    $ctCompliant = false;
                    /* //查询医嘱
                    $must = [
                        ["term" => ['ZYH' => $ZYH]],
                        ["range" => ['KZSJ' => ['gte' => $bgsj_start, 'lte' => $bgsj]]]
                    ];
                    //kzsj倒序
                    $params = $yzbService->clearMust()->queryByMustBatch($must)->paginate(1, 1000)->orderBy("KZSJ", "desc")->getParams();
                    $yzbRes = app('es')->search($params);
                    $yzbData = $yzbService->getDataByEs($yzbRes); */
                    //var_dump($yzbData);
                    $orderGroup['content'][] = [
                        'status' => 1,
                        'content' => "检查记录【{$jcmc}】"
                    ];
                    $orderGroup['content'][] = [
                        'status' => 1,
                        'content' => "报告时间【{$bgsj}】"
                    ];
                    /* if (empty($yzbData[0])) {
                        $orderGroup['content'][] = [
                            'status' => 0,
                            'content' => "医嘱名称【无】"
                        ];
                    } else { */
                    if (!empty($bgsj_start)) {
                        $isYzb = true;
                        /* $isYzb = false;
                        foreach ($yzbData[0] as $yzb) {
                            $yzmc = $yzb['YZMC'];
                            //yzmc去除关键字中文括号左括号到右括号及中间的内容
                            $yzmc = preg_replace('/\（.*?\）/', '', $yzmc);
                            $yzmc = preg_replace('/\(.*?\)/', '', $yzmc);

                            if (strtotime($yzb['KZSJ']) > strtotime($bgsj_start) && strtotime($yzb['KZSJ']) < strtotime($bgsj)) {
                                //对比yzmc和jcmc,看jcmc中是否包含yzmc,如果包含则认为有开嘱
                                if (strpos($p['JCMC'], $yzmc) !== false) {
                                    $orderGroup['content'][] = [
                                        'status' => 1,
                                        'content' => "医嘱名称【{$yzb['YZMC']}】"
                                    ];
                                    $orderGroup['content'][] = [
                                        'status' => 1,
                                        'content' => "开嘱时间【{$yzb['KZSJ']}】"
                                    ];
                                    $isYzb = true;
                                    break;
                                }
                            }
                        } */
                        if (!$isYzb) {
                            $orderGroup['content'][] = [
                                'status' => 0,
                                'content' => "医嘱名称【无】"
                            ];
                        } else {
                            $validRecords = []; // 满足条件的记录
                            $invalidRecords = []; // 不满足条件的记录
                            //报告时间72小时后
                            $bgsj_end = date('Y-m-d H:i:s', strtotime($bgsj) + (72 * 3600));

                            // 遍历所有病程记录，分类为满足条件和不满足条件的记录
                            $must = [
                                ["term" => ['JZHM' => $ZYH]],
                                ["terms" => ['BLLB' => $rule9009]],
                                ["range" => ['ZXSJ' => ['gte' => $bgsj, 'lte' => $bgsj_end]]]
                            ];
                            $should = [];
                            foreach ($rule25 as $item) {
                                $should[] = ["match_phrase_prefix" => ['HJNR' => $item]];
                            }
                            foreach ($rule26 as $item) {
                                $should[] = ["match_phrase_prefix" => ['HJNR' => $item]];
                            }
                            $notMust = [];

                            foreach ($rule27 as $item) {
                                $notMust[] = ["match_phrase_prefix" => ['HJNR' => $item]];
                            }
                            $params = $bl01Service->clearMust()
                                ->queryByMustBatch($must)
                                ->queryByShouldBatch($should)
                                ->minimumShouldMatch(1)
                                ->queryByMustNotBatch($notMust)
                                ->orderBy("ZXSJ", 'asc')
                                ->getParams();
                            $bl01Res = app('es')->search($params);
                            $bl01Data = $bl01Service->getDataByEs($bl01Res);
                            if (!empty($bl01Data[0])) {
                                foreach ($bl01Data[0] as $bl01) {
                                    $first_blsy_time = $bl01['first_blsy_time'];
                                    if (strtotime($first_blsy_time) > strtotime($bgsj) && strtotime($first_blsy_time) < strtotime($bgsj_end)) {
                                        $validRecords[] = $bl01;
                                        $orderGroup['status'] = 1;
                                        //$ctCompliant = true;
                                        break;
                                    } else {
                                        $invalidRecords[] = $bl01;
                                    }
                                }
                                if (count($validRecords) > 0) {
                                    $ctCompliant = true;
                                    $orderGroup['content'][] = [
                                        'status' => 1,
                                        'content' => "病程记录【" . $validRecords[0]['BLMC'] . "】"
                                    ];
                                    $orderGroup['content'][] = [
                                        'status' => 1,
                                        'content' => "首次签名时间【" . $validRecords[0]['first_blsy_time'] . "】"
                                    ];
                                } else {
                                    foreach ($invalidRecords as $invalidRecord) {
                                        $orderGroup['content'][] = [
                                            'status' => 0,
                                            'content' => "病程记录【" . $invalidRecord['BLMC'] . "】"
                                        ];
                                        $orderGroup['content'][] = [
                                            'status' => 0,
                                            'content' => "首次签名时间【" . $invalidRecord['first_blsy_time'] . "(超时)】"
                                        ];
                                    }
                                }
                            } else {
                                $orderGroup['content'][] = [
                                    'status' => 0,
                                    'content' => "病程记录【无】"
                                ];
                            }
                        }
                    }
                    if (!$ctCompliant) {
                        $allCtsCompliant = false;
                    }
                    $errorContent[] = $orderGroup;
                }
                if ($allCtsCompliant && count($pacs) > 0) {
                    $insert['ctmrfhl_fz'] = 1;
                }
                if ($insert['ctmrfhl_fm'] > 0) {
                    $insert['ctmrfhl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                    Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                }
                if (!empty($zyh)) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }
        }
        return true;
    }

    /**
     * 病理检查记录符合率
     */
    public function bljcjl($zyh, $start, $end)
    {
        $page = 1;

        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母
        $bl01Service = new ElasticsearchService('bl01_202303');
        $yzbService = new ElasticsearchService('yzb_2023');
        $feeService = new ElasticsearchService('fee_detailed');

        $rule9015 = RuleWordMap::query()->where('id', 9015)->value('keyword');
        if ($rule9015 === null) {
            $rule9015 = [];
        } else if (strpos($rule9015, ',') !== false) {
            $rule9015 = explode(',', $rule9015);
        } else {
            $rule9015 = [$rule9015];
        }

        $rule9009 = RuleWordMap::query()->where('id', 9009)->value('keyword');
        if (strpos($rule9009, ',') !== false) {
            $rule9009 = explode(',', $rule9009);
        } else {
            $rule9009 = [$rule9009];
        }

        $rule9017 = RuleWordMap::query()->where('id', 9017)->value('keyword');
        if ($rule9017 === null) {
            $rule9017 = [];
        } else if (strpos($rule9017, ',') !== false) {
            $rule9017 = explode(',', $rule9017);
        } else {
            $rule9017 = [$rule9017];
        }

        $rule9018 = RuleWordMap::query()->where('id', 9018)->value('keyword');
        if ($rule9018 === null) {
            $rule9018 = [];
        } else if (strpos($rule9018, ',') !== false) {
            $rule9018 = explode(',', $rule9018);
        } else {
            $rule9018 = [$rule9018];
        }

        while (true) {
            // 查询住院病人
            $data = DB::table($cyzs->table_name)
                ->leftJoin('patient_doctor_info', $cyzs->table_name . '.' . $cyzs->MED_REC_ID, '=', 'patient_doctor_info.AAA28')
                ->when($cyzs->condition, function ($query) use ($cyzs) {
                    return $query->whereRaw($cyzs->condition);
                })
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('ZYH', $zyh);
                    }
                    return $query;
                })
                ->whereBetween('AAC01', [$start, $end])
                ->paginate(500, [$cyzs->table_name . '.' . $cyzs->MED_REC_ID, 'AAB01', 'AAC01', 'AAC11N', 'AEE03'], 'page', $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                $errorContent = [];
                $insert = [
                    'zyh' => $ZYH,
                    'bljcjl_fz' => 0,
                    'bljcjl_fm' => 0,
                    'bljcjl_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                //删除
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['bljcjl_fm' => null, 'bljcjl_fz' => null, 'bljcjl_error' => null]);

                //查询zy_blfy获取fymc
                $blfy = ZY_BLFY::query()->get(['fymc'])->toArray();

                // 先查询费用
                $must = [
                    ["term" => ['MED_REC_ID' => $ZYH]]
                ];
                $should = [];
                foreach ($blfy as $item) {
                    // 修复：正确的match_phrase_prefix格式
                    if (!empty($item['fymc'])) {
                        $should[] = ["match_phrase_prefix" => ['FYMC' => $item['fymc']]];
                    }
                }
                $params = $feeService->clearMust()->queryByMustBatch($must)->queryByShouldBatch($should)->minimumShouldMatch(1)->paginate(1, 1000)->getParams();
                $feeRes = app('es')->search($params);
                $feeData = $feeService->getDataByEs($feeRes);

                if (!empty($feeData[0][0])) {
                    $allFeeCompliant = true;
                    $insert['bljcjl_fm'] = 1;

                    foreach ($feeData[0] as $item) {
                        $feeCompliant = false;
                        $orderGroup = [
                            'status' => 0,
                            'content' => []
                        ];
                        $orderGroup['content'][] = [
                            'status' => 1,
                            'content' => "费用名称【" . $item['FYMC'] . "】"
                        ];
                        $orderGroup['content'][] = [
                            'status' => 1,
                            'content' => "计费日期【" . $item['JFRQ'] . "】"
                        ];
                        //pacs中【ExamType=7  病理报告】【YXZD 检查诊断或提示】 不为空
                        $pacs = \App\Model\PACS::query()
                            ->where("ZYH", $ZYH)
                            ->whereIn("ExamType", ["07", "7"])  // 使用 whereIn 更简洁
                            ->whereNotNull("YXZD")
                            ->get(["JCMC"])
                            ->toArray();

                        if (!empty($pacs)) {
                            $orderGroup['content'][] = [
                                'status' => 1,
                                'content' => "病理报告【" . $pacs[0]['JCMC'] . "】"
                            ];
                        } else {
                            $orderGroup['content'][] = [
                                'status' => 0,
                                'content' => "病理报告【无】"
                            ];
                        }

                        //查询手术记录
                        $must = [
                            ["term" => ['JZHM' => $ZYH]],
                            ["terms" => ['MBLB' => $rule9017]]
                        ];
                        $should = [];
                        if (!empty($rule9018)) {
                            foreach ($rule9018 as $item) {
                                if ($item !== null) {
                                    // 修复：正确的match_phrase_prefix格式
                                    $should[] = ["match_phrase_prefix" => ['HJNR' => $item]];
                                }
                            }
                        }
                        $params = $bl01Service->clearMust()->queryByMustBatch($must)->queryByShouldBatch($should)->minimumShouldMatch(1)->paginate(1, 1000)->getParams();
                        $ssjlRes = app('es')->search($params);
                        $ssData = $bl01Service->getDataByEs($ssjlRes);
                        if (!empty($ssData[0][0])) {
                            $orderGroup['content'][] = [
                                'status' => 1,
                                'content' => "手术记录【" . $ssData[0][0]['BLMC'] . "】"
                            ];
                        }
                        //查询病程
                        $must = [
                            ["term" => ['JZHM' => $ZYH]],
                            ["terms" => ['MBLB' => $rule9009]]
                        ];
                        $should = [];
                        if (!empty($rule9018)) {
                            foreach ($rule9018 as $item) {
                                if ($item !== null) {
                                    // 修复：正确的match_phrase_prefix格式
                                    $should[] = ["match_phrase_prefix" => ['HJNR' => $item]];
                                }
                            }
                        }
                        $mustnot = [];
                        if (!empty($rule9015)) {
                            foreach ($rule9015 as $item) {
                                if ($item !== null) {
                                    // 修复：正确的match_phrase_prefix格式
                                    $mustnot[] = ["match_phrase_prefix" => ['BLMC' => $item]];
                                }
                            }
                        }
                        $params = $bl01Service->clearMust()->queryByMustBatch($must)->queryByShouldBatch($should)->queryByMustNotBatch($mustnot)->minimumShouldMatch(1)->paginate(1, 1000)->getParams();
                        $bl01Res = app('es')->search($params);
                        $bl01Data = $bl01Service->getDataByEs($bl01Res);
                        if (!empty($bl01Data[0])) {
                            foreach ($bl01Data[0] as $bl01) {
                                $orderGroup['content'][] = [
                                    'status' => 1,
                                    'content' => "病程记录【" . $bl01['BLMC'] . "】"
                                ];
                            }
                            $orderGroup['status'] = 1;
                            $feeCompliant = true;
                        } else {
                            $orderGroup['content'][] = [
                                'status' => 0,
                                'content' => "病程记录【无】"
                            ];
                        }
                        if (!$feeCompliant) {
                            $allFeeCompliant = false;
                        }
                        $errorContent[] = $orderGroup;
                    }
                    if ($allFeeCompliant && count($feeData[0]) > 0) {
                        $insert['bljcjl_fz'] = 1;
                    }
                    if ($insert['bljcjl_fm'] > 0) {
                        $insert['bljcjl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                        Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                    }
                    if (!empty($zyh)) {
                        $esService = new ElasticsearchService('indicator');
                        $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                    }
                }
            }
        }
    }

    /**
     * 抗菌药物使用用符合率
     */
    public function kjywsy($zyh, $start, $end)
    {
        $page = 1;

        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母
        $bl01Service = new ElasticsearchService('bl01_202303');
        $yzbService = new ElasticsearchService('yzb_2023');

        //从数据表获取抗菌药名称，和2053中的满足一项即可表medicinal_info  且 type=1
        $kjywName = MedicinalInfo::query()->where('type', 1)->get()->toArray();


        $rule9009 = RuleWordMap::query()->where('id', 8068)->value('keyword');
        if ($rule9009 === null) {
            $rule9009 = [];
        } else if (strpos($rule9009, ',') !== false) {
            $rule9009 = explode(',', $rule9009);
        } else {
            $rule9009 = [$rule9009];
        }
        $rule9015 = RuleWordMap::query()->where('id', 9015)->value('keyword');
        if ($rule9015 === null) {
            $rule9015 = [];
        } else if (strpos($rule9015, ',') !== false) {
            $rule9015 = explode(',', $rule9015);
        } else {
            $rule9015 = [$rule9015];
        }
        $rule2053 = RuleWordMap::query()->where('id', 2053)->value('keyword');
        if ($rule2053 === null) {
            $rule2053 = [];
        } else if (strpos($rule2053, ',') !== false) {
            $rule2053 = explode(',', $rule2053);
        } else {
            $rule2053 = [$rule2053];
        }
        $rule2055 = RuleWordMap::query()->where('id', 2055)->value('keyword');
        if ($rule2055 === null) {
            $rule2055 = [];
        } else if (strpos($rule2055, ',') !== false) {
            $rule2055 = explode(',', $rule2055);
        } else {
            $rule2055 = [$rule2055];
        }

        while (true) {
            $data = DB::table($cyzs->table_name)
                ->leftJoin('patient_doctor_info', $cyzs->table_name . '.' . $cyzs->MED_REC_ID, '=', 'patient_doctor_info.AAA28')
                ->when($cyzs->condition, function ($query) use ($cyzs) {
                    return $query->whereRaw($cyzs->condition);
                })
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('ZYH', $zyh);
                    }
                    return $query;
                })
                ->whereBetween('AAC01', [$start, $end])
                ->paginate(500, [$cyzs->table_name . '.' . $cyzs->MED_REC_ID, 'AAB01', 'AAC01', 'AAC11N', 'AEE03'], 'page', $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, 'MED_REC_ID');
                $errorContent = [];
                $allkjyw = true;
                $insert = [
                    'zyh' => $ZYH,
                    'kjywsy_fz' => 0,
                    'kjywsy_fm' => 0,
                    'kjywsy_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                //删除
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['kjywsy_fm' => null, 'kjywsy_fz' => null, 'kjywsy_error' => null]);

                // 查询抗菌药物使用,查询yzb2023 is_has_kjyw=1 
                $must = [
                    ["term" => ['ZYH' => $ZYH]],
                    ["term" => ['is_has_kjyw' => 1]]
                ];
                $mustnot = [
                    ["term" => ['YYSX' => 4]],
                    ["match_phrase" => ['YZMC' => '皮试']],
                    ["term" => ['YZZT' => 5]],
                    ["term" => ['PSBZ' => 1]]
                ];
                $params = $yzbService->clearMust()->queryByMustBatch($must)->queryByMustNotBatch($mustnot)->paginate(1, 1000)->getParams();
                $yzbRes = app('es')->search($params);
                $yzbData = $yzbService->getDataByEs($yzbRes);
                if (empty($yzbData[0][0])) {
                    continue;
                }

                $insert['kjywsy_fm'] = 1;
                // 查询bl01_202303 病程记录 病程记录字段 病程记录内容 包含 抗菌药物使用
                foreach ($yzbData[0] as $yzb) {

                    $iskjyw = false;
                    $kzsj = $yzb['KZSJ'];
                    //储存符合的病程
                    $kjyw_bl01 = [];
                    //储存不符合的病程
                    $kjyw_bl01_no = [];
                    //获取开嘱时间前24小时和后72小时
                    $kzsj_start = date('Y-m-d H:i:s', strtotime($kzsj) - 24 * 3600);
                    $kzsj_end = date('Y-m-d H:i:s', strtotime($kzsj) + 72 * 3600);

                    /**
                     * 医嘱名称【奥硝唑氯化钠注射液(常)】
                     * 开嘱时间【2025-01-01 01:00:00】
                     * 病程记录【未记录抗菌药物使用情况】
                     */
                    $orderGroup = [
                        'status' => 0,
                        'content' => []
                    ];
                    $orderGroup['content'][] = [
                        'status' => 1,
                        'content' => "医嘱名称【" . $yzb['YZMC'] . "】"
                    ];
                    $orderGroup['content'][] = [
                        'status' => 1,
                        'content' => "开嘱时间【" . $kzsj . "】"
                    ];

                    $must = [
                        ["term" => ['JZHM' => $ZYH]],
                        ["terms" => ['BLLB' => $rule9009]],
                        ["range" => ['ZXSJ' => ['gte' => $kzsj_start, 'lte' => $kzsj_end]]]
                    ];
                    $should = [];
                    if (!empty($rule2053)) {
                        foreach ($rule2053 as $item) {
                            if ($item !== null) {
                                $should[] = ["match_phrase_prefix" => ['HJNR' => $item]];
                            }
                        }
                    }
                    foreach ($kjywName as $item) {
                        $should[] = ["match_phrase_prefix" => ['HJNR' => $item['name']]];
                    }
                    $mustnot = [];

                    foreach ($rule2055 as $item) {
                        $mustnot[] = ["match_phrase_prefix" => ['HJNR' => $item]];
                    }
                    $params = $bl01Service->clearMust()->queryByMustBatch($must)->queryByShouldBatch($should)->queryByMustNotBatch($mustnot)->minimumShouldMatch(1)->paginate(1, 1000)->getParams();
                    $bl01Res = app('es')->search($params);
                    $bl01Data = $bl01Service->getDataByEs($bl01Res);
                    if (empty($bl01Data[0])) {
                        $orderGroup['content'][] = [
                            'status' => 0,
                            'content' => "病程记录【无】"
                        ];
                    } else {
                        foreach ($bl01Data[0] as $bl01) {
                            //获取first_blsy_time 时间戳
                            $first_blsy_time = $bl01['first_blsy_time'];
                            if (strtotime($first_blsy_time) >= strtotime($kzsj_start) && strtotime($first_blsy_time) <= strtotime($kzsj_end)) {
                                $iskjyw = true;
                                $orderGroup['status'] = 1;
                                $kjyw_bl01[] = $bl01;
                            } else {
                                $kjyw_bl01_no[] = $bl01;
                            }
                        }
                        if ($iskjyw) {
                            foreach ($kjyw_bl01 as $bl01) {
                                $orderGroup['content'][] = [
                                    'status' => 1,
                                    'content' => "病程记录【" . $bl01['BLMC'] . "】"
                                ];
                                $orderGroup['content'][] = [
                                    'status' => 1,
                                    'content' => "首次签名时间【" . $bl01['first_blsy_time'] . "】"
                                ];
                            }
                        } else {
                            if (count($kjyw_bl01_no) > 0) {
                                foreach ($kjyw_bl01_no as $bl01) {
                                    $orderGroup['content'][] = [
                                        'status' => 0,
                                        'content' => "病程记录【" . $bl01['BLMC'] . "】"
                                    ];
                                    $orderGroup['content'][] = [
                                        'status' => 0,
                                        'content' => "首次签名时间【" . $bl01['first_blsy_time'] . "(超时)】"
                                    ];
                                }
                            } else {
                                $orderGroup['content'][] = [
                                    'status' => 0,
                                    'content' => "病程记录【无】"
                                ];
                            }
                        }
                    }
                    if (!$iskjyw) {
                        $allkjyw = false;
                    }
                    $errorContent[] = $orderGroup;
                }
                if ($allkjyw && $insert['kjywsy_fm'] > 0) {
                    $insert['kjywsy_fz'] = 1;
                }
                if ($insert['kjywsy_fm'] > 0) {
                    $insert['kjywsy_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                    Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                }
                if (!empty($zyh)) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }
        }
    }

    /**
     * 恶性肿瘤化疗药物使用符合率
     */
    public function exzlhxzl($zyh, $start, $end)
    {
        $page = 1;

        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母
        $bl01Service = new ElasticsearchService('bl01_202303');
        $yzbService = new ElasticsearchService('yzb_2023');

        //从数据表获取化疗药名称，和2053中的满足一项即可表medicinal_info  且 type=2
        $kjywName = MedicinalInfo::query()->where('type', 2)->get()->toArray();


        $rule9009 = RuleWordMap::query()->where('id', 8068)->value('keyword');
        if ($rule9009 === null) {
            $rule9009 = [];
        } else if (strpos($rule9009, ',') !== false) {
            $rule9009 = explode(',', $rule9009);
        } else {
            $rule9009 = [$rule9009];
        }
        $rule9015 = RuleWordMap::query()->where('id', 9015)->value('keyword');
        if ($rule9015 === null) {
            $rule9015 = [];
        } else if (strpos($rule9015, ',') !== false) {
            $rule9015 = explode(',', $rule9015);
        } else {
            $rule9015 = [$rule9015];
        }

        $rule2055 = RuleWordMap::query()->where('id', 2055)->value('keyword');
        if ($rule2055 === null) {
            $rule2055 = [];
        } else if (strpos($rule2055, ',') !== false) {
            $rule2055 = explode(',', $rule2055);
        } else {
            $rule2055 = [$rule2055];
        }

        $rule9016 = RuleWordMap::query()->where('id', 9016)->value('keyword');
        if ($rule9016 === null) {
            $rule9016 = [];
        } else if (strpos($rule9016, ',') !== false) {
            $rule9016 = explode(',', $rule9016);
        } else {
            $rule9016 = [$rule9016];
        }

        while (true) {
            $data = DB::table($cyzs->table_name)
                ->leftJoin('patient_doctor_info', $cyzs->table_name . '.' . $cyzs->MED_REC_ID, '=', 'patient_doctor_info.AAA28')
                ->when($cyzs->condition, function ($query) use ($cyzs) {
                    return $query->whereRaw($cyzs->condition);
                })
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('ZYH', $zyh);
                    }
                    return $query;
                })
                //->where('MED_REC_ID', '=', '559831')
                ->whereBetween('AAC01', [$start, $end])
                ->paginate(500, [$cyzs->table_name . '.' . $cyzs->MED_REC_ID, 'AAB01', 'AAC01', 'AAC11N', 'AEE03'], 'page', $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, 'MED_REC_ID');
                $errorContent = [];
                $allkjyw = true;
                $insert = [
                    'zyh' => $ZYH,
                    'exzlhxzl_fz' => 0,
                    'exzlhxzl_fm' => 0,
                    'exzlhxzl_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                //删除
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['exzlhxzl_fm' => null, 'exzlhxzl_fz' => null, 'exzlhxzl_error' => null]);

                // 查询抗菌药物使用,查询yzb2023 is_has_hlyw=1 
                $must = [
                    ["term" => ['ZYH' => $ZYH]],
                    ["term" => ['is_has_hlyw' => 1]]
                ];
                $mustnot = [
                    ["term" => ['YYSX' => 4]],
                    ["term" => ['YZZT' => 5]]
                ];
                //var_dump("must", $must);
                //var_dump("mustnot", $mustnot);
                $params = $yzbService->clearMust()->queryByMustBatch($must)->queryByMustNotBatch($mustnot)->paginate(1, 1000)->getParams();
                $yzbRes = app('es')->search($params);
                $yzbData = $yzbService->getDataByEs($yzbRes);
                //var_dump("yzbData", $yzbData);
                if (empty($yzbData[0][0])) {
                    continue;
                }

                $insert['exzlhxzl_fm'] = 1;

                // 查询bl01_202303 病程记录 病程记录字段 病程记录内容 包含 化疗药物使用
                foreach ($yzbData[0] as $yzb) {

                    $iskjyw = false;
                    $kzsj = $yzb['KZSJ'];
                    //储存符合的病程
                    $kjyw_bl01 = [];
                    //储存不符合的病程
                    $kjyw_bl01_no = [];
                    //获取开嘱时间前24小时和后72小时
                    $kzsj_start = date('Y-m-d H:i:s', strtotime($kzsj) - 24 * 3600);
                    $kzsj_end = date('Y-m-d H:i:s', strtotime($kzsj) + 72 * 3600);

                    /**
                     * 医嘱名称【奥硝唑氯化钠注射液(常)】
                     * 开嘱时间【2025-01-01 01:00:00】
                     * 病程记录【未记录化疗药物使用情况】
                     */
                    $orderGroup = [
                        'status' => 0,
                        'content' => []
                    ];
                    $orderGroup['content'][] = [
                        'status' => 1,
                        'content' => "医嘱名称【" . $yzb['YZMC'] . "】"
                    ];
                    $orderGroup['content'][] = [
                        'status' => 1,
                        'content' => "开嘱时间【" . $kzsj . "】"
                    ];

                    $must = [
                        ["term" => ['JZHM' => $ZYH]],
                        ["terms" => ['BLLB' => $rule9009]],
                        ["range" => ['ZXSJ' => ['gte' => $kzsj_start, 'lte' => $kzsj_end]]]
                    ];
                    $should = [];

                    foreach ($kjywName as $item) {
                        $should[] = ["match_phrase_prefix" => ['HJNR' => $item['name']]];
                    }

                    foreach ($rule9016 as $item) {
                        if ($item !== null) {
                            $should[] = ["match_phrase_prefix" => ['HJNR' => $item]];
                        }
                    }


                    $params = $bl01Service->clearMust()->queryByMustBatch($must)->queryByShouldBatch($should)->minimumShouldMatch(1)->paginate(1, 1000)->getParams();
                    $bl01Res = app('es')->search($params);
                    $bl01Data = $bl01Service->getDataByEs($bl01Res);
                    if (empty($bl01Data[0])) {
                        $orderGroup['content'][] = [
                            'status' => 0,
                            'content' => "病程记录【无】"
                        ];
                    } else {
                        foreach ($bl01Data[0] as $bl01) {
                            //获取first_blsy_time 时间戳
                            $first_blsy_time = $bl01['first_blsy_time'];
                            if (strtotime($first_blsy_time) >= strtotime($kzsj_start) && strtotime($first_blsy_time) <= strtotime($kzsj_end)) {
                                $iskjyw = true;
                                $orderGroup['status'] = 1;
                                $kjyw_bl01[] = $bl01;
                            } else {
                                $kjyw_bl01_no[] = $bl01;
                            }
                        }
                        if ($iskjyw) {
                            foreach ($kjyw_bl01 as $bl01) {
                                $orderGroup['content'][] = [
                                    'status' => 1,
                                    'content' => "病程记录【" . $bl01['BLMC'] . "】"
                                ];
                                $orderGroup['content'][] = [
                                    'status' => 1,
                                    'content' => "首次签名时间【" . $bl01['first_blsy_time'] . "】"
                                ];
                            }
                        } else {
                            if (count($kjyw_bl01_no) > 0) {
                                foreach ($kjyw_bl01_no as $bl01) {
                                    $orderGroup['content'][] = [
                                        'status' => 0,
                                        'content' => "病程记录【" . $bl01['BLMC'] . "】"
                                    ];
                                    $orderGroup['content'][] = [
                                        'status' => 0,
                                        'content' => "首次签名时间【" . $bl01['first_blsy_time'] . "(超时)】"
                                    ];
                                }
                            } else {
                                $orderGroup['content'][] = [
                                    'status' => 0,
                                    'content' => "病程记录【无】"
                                ];
                            }
                        }
                    }
                    if (!$iskjyw) {
                        $allkjyw = false;
                    }
                    $errorContent[] = $orderGroup;
                }
                if ($allkjyw && $insert['exzlhxzl_fm'] > 0) {
                    $insert['exzlhxzl_fz'] = 1;
                }
                if ($insert['exzlhxzl_fm'] > 0) {
                    $insert['exzlhxzl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                    Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                }
                if (!empty($zyh)) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }
        }
    }

    /**
     * 甲级病历率
     */
    public function jjbll($zyh, $start, $end)
    {
        $page = 1;
        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母
        while (true) {

            $data = DB::table($cyzs->table_name)
                ->leftJoin('patient_doctor_info', $cyzs->table_name . '.' . $cyzs->MED_REC_ID, '=', 'patient_doctor_info.AAA28')
                ->when($cyzs->condition, function ($query) use ($cyzs) {
                    return $query->whereRaw($cyzs->condition);
                })
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('ZYH', $zyh);
                    }
                    return $query;
                })
                ->whereBetween('AAC01', [$start, $end])
                ->paginate(500, [$cyzs->table_name . '.' . $cyzs->MED_REC_ID, 'AAB01', 'AAC01', 'AAC11N', 'AEE03', 'patient_info.score'], 'page', $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;
            foreach ($data->items() as $value) {
                $ZYH = data_get($value, 'MED_REC_ID');
                $errorContent = [];
                $insert = [
                    'zyh' => $ZYH,
                    'jjbll_fz' => 0,
                    'jjbll_fm' => 0,
                    'jjbll_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                //删除
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['jjbll_fm' => null, 'jjbll_fz' => null, 'jjbll_error' => null]);

                $insert['jjbll_fm'] = 1;
                $score = $value->score;
                $orderGroup = [
                    'status' => 0,
                    'content' => []
                ];
                $orderGroup['content'][] = [
                    'status' => 1,
                    'content' => '病历分数【' . $score . '】'
                ];
                if ($score >= 90) {
                    $insert['jjbll_fz'] = 1;
                    $orderGroup['status'] = 1;

                    $orderGroup['content'][] = [
                        'status' => 1,
                        'content' => '病历质量【甲级】'
                    ];
                } elseif ($score >= 75 && $score < 90) {
                    $orderGroup['content'][] = [
                        'status' => 0,
                        'content' => '病历质量【乙级】'
                    ];
                } else {
                    $orderGroup['content'][] = [
                        'status' => 0,
                        'content' => '病历质量【丙级】'
                    ];
                }
                $errorContent[] = $orderGroup;
                if ($insert['jjbll_fm'] > 0) {
                    $insert['jjbll_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                    Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                }
                if (!empty($zyh)) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }
        }
    }

    /**
     * 手术记录相关完整率
     */
    public function ssxgjl($zyh, $start, $end)
    {
        $page = 1;

        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母
        $yzbService = new ElasticsearchService('yzb_2023');
        $bl01Service = new ElasticsearchService('bl01_202303');
        $rule9015 = RuleWordMap::query()->where('id', 9015)->value('keyword');
        if ($rule9015 === null) {
            $rule9015 = [];
        } else if (strpos($rule9015, ',') !== false) {
            $rule9015 = explode(',', $rule9015);
        } else {
            $rule9015 = [$rule9015];
        }
        $rule8047 = RuleWordMap::query()->where('id', 8047)->value('keyword');
        if ($rule8047 === null) {
            $rule8047 = [];
        } else if (strpos($rule8047, ',') !== false) {
            $rule8047 = explode(',', $rule8047);
        } else {
            $rule8047 = [$rule8047];
        }
        $rule8048 = RuleWordMap::query()->where('id', 8048)->value('keyword');
        if ($rule8048 === null) {
            $rule8048 = [];
        } else if (strpos($rule8048, ',') !== false) {
            $rule8048 = explode(',', $rule8048);
        } else {
            $rule8048 = [$rule8048];
        }
        //8058-8066
        $rule8058 = RuleWordMap::query()->where('id', 8058)->value('keyword');
        if ($rule8058 === null) {
            $rule8058 = [];
        } else if (strpos($rule8058, ',') !== false) {
            $rule8058 = explode(',', $rule8058);
        } else {
            $rule8058 = [$rule8058];
        }
        $rule8059 = RuleWordMap::query()->where('id', 8059)->value('keyword');
        if ($rule8059 === null) {
            $rule8059 = [];
        } else if (strpos($rule8059, ',') !== false) {
            $rule8059 = explode(',', $rule8059);
        } else {
            $rule8059 = [$rule8059];
        }
        $rule8060 = RuleWordMap::query()->where('id', 8060)->value('keyword');
        if ($rule8060 === null) {
            $rule8060 = [];
        } else if (strpos($rule8060, ',') !== false) {
            $rule8060 = explode(',', $rule8060);
        } else {
            $rule8060 = [$rule8060];
        }
        $rule8061 = RuleWordMap::query()->where('id', 8061)->value('keyword');
        if ($rule8061 === null) {
            $rule8061 = [];
        } else if (strpos($rule8061, ',') !== false) {
            $rule8061 = explode(',', $rule8061);
        } else {
            $rule8061 = [$rule8061];
        }
        $rule8062 = RuleWordMap::query()->where('id', 8062)->value('keyword');
        if ($rule8062 === null) {
            $rule8062 = [];
        } else if (strpos($rule8062, ',') !== false) {
            $rule8062 = explode(',', $rule8062);
        } else {
            $rule8062 = [$rule8062];
        }
        $rule8063 = RuleWordMap::query()->where('id', 8063)->value('keyword');
        if ($rule8063 === null) {
            $rule8063 = [];
        } else if (strpos($rule8063, ',') !== false) {
            $rule8063 = explode(',', $rule8063);
        } else {
            $rule8063 = [$rule8063];
        }
        $rule8064 = RuleWordMap::query()->where('id', 8064)->value('keyword');
        if ($rule8064 === null) {
            $rule8064 = [];
        } else if (strpos($rule8064, ',') !== false) {
            $rule8064 = explode(',', $rule8064);
        } else {
            $rule8064 = [$rule8064];
        }
        $rule8065 = RuleWordMap::query()->where('id', 8065)->value('keyword');
        if ($rule8065 === null) {
            $rule8065 = [];
        } else if (strpos($rule8065, ',') !== false) {
            $rule8065 = explode(',', $rule8065);
        } else {
            $rule8065 = [$rule8065];
        }
        $rule8066 = RuleWordMap::query()->where('id', 8066)->value('keyword');
        if ($rule8066 === null) {
            $rule8066 = [];
        } else if (strpos($rule8066, ',') !== false) {
            $rule8066 = explode(',', $rule8066);
        } else {
            $rule8066 = [$rule8066];
        }
        while (true) {
            $data = DB::table($cyzs->table_name)
                ->leftJoin('patient_doctor_info', $cyzs->table_name . '.' . $cyzs->MED_REC_ID, '=', 'patient_doctor_info.AAA28')
                ->when($cyzs->condition, function ($query) use ($cyzs) {
                    return $query->whereRaw($cyzs->condition);
                })
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('MED_REC_ID', $zyh);
                    }
                    return $query;
                })
                //->where('MED_REC_ID', '=', '544365')
                ->whereBetween('AAC01', [$start, $end])
                ->paginate(500, [$cyzs->table_name . '.' . $cyzs->MED_REC_ID, 'AAB01', 'AAC01', 'AAC11N', 'AEE03'], 'page', $page);
            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;
            foreach ($data->items() as $value) {
                $ZYH = data_get($value, 'MED_REC_ID');
                $errorContent = [];
                $allssxgjl = true;
                $insert = [
                    'zyh' => $ZYH,
                    'ssxgjl_fz' => 0,
                    'ssxgjl_fm' => 0,
                    'ssxgjl_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                //删除
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['ssxgjl_fm' => null, 'ssxgjl_fz' => null, 'ssxgjl_error' => null]);


                /**【手术安排SM_SSAP】中手术类别SSLB  id8047（手术,介入治疗）或  手术名称 ICD9_SSCZMC 包含id8048 （脑血管造影，内镜食管黏膜下剥离术） 数据库查询SM_SSAP model*/
                /* $SSAP = SM_SSAP::query()->where('ZYH', $ZYH)->whereIn('SSLB', $rule8047);
                //是否包含8048
                foreach ($rule8048 as $item) {
                    $SSAP->orWhere('ICD9_SSCZMC', 'like', '%' . $item . '%');
                }
                $SSAP = $SSAP->get()->toArray();

                if (empty($SSAP)) {
                    continue;
                } */
                $SSAP = SM_SSAP::query()->where('ZYH', $ZYH)->get()->toArray();
                if (empty($SSAP)) {
                    continue;
                }
                $sssj = 0;
                $m = 1;
                foreach ($SSAP as $item) {
                    $ssxgjl = false;
                    $isyzb = false;
                    $issqxj = false;
                    $iszqty = false;
                    $isaqhc = false;
                    $isssjl = false;
                    $isshbc = false;

                    $ssmc = $item['ICD9_SSCZMC'];
                    $jsrq = $item['JSRQ'];
                    $ssrq = $item['SSRQ'];

                    if (empty($ssrq) || empty($jsrq) || strpos($jsrq, '1970-01-01') !== false || $ssmc == 'NULL' || empty($ssmc)) {
                        continue;
                    }

                    $orderGroup = [
                        'status' => 0,
                        'content' => []
                    ];

                    //根据手术名称查询SSCZ的SSLB
                    $ssCZ = SSCZ::query()->where('SSMC', $ssmc)->value('SSLB');
                    if (!empty($ssCZ)) {
                        //如果ssCZ的SSLB不在8047中，则跳过
                        if (!in_array($ssCZ, $rule8047)) {
                            if (!empty($rule8048)) {
                                //如果ssCZ的SSLB在8048中，则跳过
                                if (!in_array($ssmc, $rule8048)) {
                                    continue;
                                }
                            } else {
                                continue;
                            }
                        }
                    }
                    $insert['ssxgjl_fm'] = 1;
                    $orderGroup['content'][] = [
                        'status' => 1,
                        'content' => '手术安排【' . $ssmc . '】'
                    ];
                    $orderGroup['content'][] = [
                        'status' => 1,
                        'content' => '手术开始时间【' . $ssrq . '】'
                    ];
                    $orderGroup['content'][] = [
                        'status' => 1,
                        'content' => '手术结束时间【' . $jsrq . '】'
                    ];
                    $shdyt = date('Y-m-d', strtotime($item['JSRQ']) + (3600 * 24 * 2));
                    $shdet = date('Y-m-d', strtotime($item['JSRQ']) + (3600 * 24 * 3));
                    $shdst = date('Y-m-d', strtotime($item['JSRQ']) + (3600 * 24 * 4));
                    //查询yzb yzmc中包含 拟***行***术 正则匹配 或者包含手术名称
                    $must = [
                        ["term" => ['ZYH' => $ZYH]]
                    ];
                    $should = [
                        ["regexp" => ['YZMC.keyword' => '(拟|定于).*行.*术']],
                        ["match_phrase" => ['YZMC' => $ssmc]]
                    ];

                    if ($m == 1) {
                        $must[] = ["range" => ['KZSJ' => ['lte' => $item['SSRQ']]]];
                    } else {
                        $fdisssj = date('Y-m-d H:i:s', strtotime($sssj));
                        $must[] = ["range" => ['KZSJ' => ['gte' => $fdisssj, 'lte' => $item['SSRQ']]]];
                    }
                    $params = $yzbService->clearMust()->queryByMustBatch($must)->queryByShouldBatch($should)->minimumShouldMatch(1)->orderBy("KZSJ", 'desc')->paginate(1, 1000)->getParams();
                    $yzbRes = app('es')->search($params);
                    $yzbData = $yzbService->getDataByEs($yzbRes);
                    if (empty($yzbData[0])) {
                        $must = [
                            ["term" => ['ZYH' => $ZYH]]
                        ];
                        $should = [
                            ["regexp" => ['YZMC.keyword' => '(拟|定于).*行.*']],
                            ["match_phrase" => ['YZMC' => $ssmc]]
                        ];

                        if ($m == 1) {
                            $must[] = ["range" => ['KZSJ' => ['lte' => $item['SSRQ']]]];
                        } else {
                            $fdisssj = date('Y-m-d H:i:s', strtotime($sssj));
                            $must[] = ["range" => ['KZSJ' => ['gte' => $fdisssj, 'lte' => $item['SSRQ']]]];
                        }
                        $mustnot = [
                            ["match_phrase_prefix" => ['YZMC' => '检查']]
                        ];
                        $params = $yzbService->clearMust()->queryByMustBatch($must)->queryByShouldBatch($should)->minimumShouldMatch(1)->queryByMustNotBatch($mustnot)->orderBy("KZSJ", 'desc')->paginate(1, 1000)->getParams();
                        $yzbRes = app('es')->search($params);
                        $yzbData = $yzbService->getDataByEs($yzbRes);
                    }
                    if (empty($yzbData[0])) {
                        $orderGroup['content'][] = [
                            'status' => 0,
                            'content' => '医嘱名称【无】'
                        ];
                    } else {
                        $isyzb = true;
                        $orderGroup['content'][] = [
                            'status' => 1,
                            'content' => '医嘱名称【' . $yzbData[0][0]['YZMC'] . '】'
                        ];
                        $orderGroup['content'][] = [
                            'status' => 1,
                            'content' => '开嘱时间【' . $yzbData[0][0]['KZSJ'] . '】'
                        ];
                        //获取所有病程记录
                        $must = [
                            ["term" => ['JZHM' => $ZYH]]
                        ];
                        $params = $bl01Service->clearMust()->queryByMustBatch($must)->orderBy("ZXSJ", 'asc')->paginate(1, 1000)->getParams();
                        $bl01Res = app('es')->search($params);
                        $bl01Data = $bl01Service->getDataByEs($bl01Res);
                        if (empty($bl01Data[0])) {
                            $orderGroup['content'][] = [
                                'status' => 0,
                                'content' => '病程记录【无】'
                            ];
                        } else {
                            $issqxjData = [];
                            $iszqtyData = [];
                            $isaqhcData = [];
                            $isssjlData = [];
                            $isshbcData1 = [];
                            $isshbcData2 = [];
                            $isshbcData3 = [];
                            //术后三天的开始和结束时间，比如手术日期是2025-01-01 22:34:00，术后第一天算2025-01-03 00:00:00-2025-01-04 00:00:00，术后第二天算2025-01-04 00:00:00-2025-01-05 00:00:00，术后第三天算2025-01-05 00:00:00-2025-01-06 00:00:00

                            foreach ($bl01Data[0] as $bl01) {
                                //如果mblb在8058 或者病历名称 包含8059（strpos）（数组）
                                //var_dump('mblb:',$bl01['MBLB']);
                                //var_dump('bllb:',$bl01['BLLB']);
                                //var_dump('blmc:',$bl01['BLMC']);

                                if (in_array($bl01['MBLB'], $rule8058)) {
                                    if (strtotime($bl01['ZXSJ']) < strtotime($yzbData[0][0]['KZSJ'])) {
                                        if (strtotime($bl01['first_blsy_time']) < strtotime($yzbData[0][0]['KZSJ'])) {
                                            $issqxjData['yes'][] = $bl01;
                                        } else {
                                            $issqxjData['no'][] = $bl01;
                                        }
                                    }
                                } elseif (in_array($bl01['MBLB'], $rule8060)) {
                                    if (strtotime($bl01['ZXSJ']) < strtotime($ssrq) && strtotime($bl01['ZXSJ']) > $sssj) {
                                        if (strtotime($bl01['first_blsy_time']) < strtotime($ssrq)) {
                                            $iszqtyData['yes'][] = $bl01;
                                        } else {
                                            $iszqtyData['no'][] = $bl01;
                                        }
                                    }
                                } elseif (in_array($bl01['MBLB'], $rule8062)) {
                                    if (strtotime($bl01['ZXSJ']) < strtotime($ssrq) && strtotime($bl01['ZXSJ']) > $sssj) {

                                        if (strtotime($bl01['first_blsy_time']) < strtotime($ssrq)) {
                                            $isaqhcData['yes'][] = $bl01;
                                        } else {
                                            $isaqhcData['no'][] = $bl01;
                                        }
                                    }
                                } elseif (in_array($bl01['MBLB'], $rule8064)) {
                                    if (strtotime($bl01['ZXSJ']) > strtotime($jsrq) && strtotime($bl01['ZXSJ']) < strtotime(date('Y-m-d H:i:s', strtotime($jsrq) + (3600 * 24)))) { //手术结束到结束24小时内

                                        if (strtotime($bl01['first_blsy_time']) > strtotime($jsrq) && strtotime($bl01['first_blsy_time']) < strtotime(date('Y-m-d H:i:s', strtotime($jsrq) + (3600 * 24)))) {
                                            $isssjlData['yes'][] = $bl01;
                                        } else {
                                            $isssjlData['no'][] = $bl01;
                                        }
                                    }
                                } elseif (in_array($bl01['MBLB'], $rule8066)) {


                                    //blmc如果包含9015，跳过
                                    foreach ($rule9015 as $item) {
                                        if (strpos($bl01['BLMC'], $item) == false) {
                                            if (strtotime($bl01['ZXSJ']) > strtotime($shdyt . ' 00:00:00') && strtotime($bl01['ZXSJ']) < strtotime($shdyt . ' 23:59:59')) {
                                                if (strtotime($bl01['first_blsy_time']) > strtotime($shdyt . ' 00:00:00') && strtotime($bl01['first_blsy_time']) < strtotime($shdyt . ' 23:59:59')) {
                                                    $isshbcData1['yes'][] = $bl01;
                                                } else {
                                                    $isshbcData1['no'][] = $bl01;
                                                }
                                            } elseif (strtotime($bl01['ZXSJ']) > strtotime($shdet . ' 00:00:00') && strtotime($bl01['ZXSJ']) < strtotime($shdet . ' 23:59:59')) {
                                                if (strtotime($bl01['first_blsy_time']) > strtotime($shdet . ' 00:00:00') && strtotime($bl01['first_blsy_time']) < strtotime($shdet . ' 23:59:59')) {
                                                    $isshbcData2['yes'][] = $bl01;
                                                } else {
                                                    $isshbcData2['no'][] = $bl01;
                                                }
                                            } elseif (strtotime($bl01['ZXSJ']) > strtotime($shdst . ' 00:00:00') && strtotime($bl01['ZXSJ']) < strtotime($shdst . ' 23:59:59')) {
                                                if (strtotime($bl01['first_blsy_time']) > strtotime($shdst . ' 00:00:00') && strtotime($bl01['first_blsy_time']) < strtotime($shdst . ' 23:59:59')) {
                                                    $isshbcData3['yes'][] = $bl01;
                                                } else {
                                                    $isshbcData3['no'][] = $bl01;
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    $blmc = $bl01['BLMC'];
                                    if (count($issqxjData) == 0 && $bl01['BLLB'] == '294') {

                                        foreach ($rule8059 as $item) {
                                            if (strpos($blmc, $item) !== false) {
                                                if (strtotime($bl01['ZXSJ']) < strtotime($yzbData[0][0]['KZSJ'])) {

                                                    if (strtotime($bl01['first_blsy_time']) < strtotime($yzbData[0][0]['KZSJ'])) {
                                                        $issqxjData['yes'][] = $bl01;
                                                    } else {
                                                        $issqxjData['no'][] = $bl01;
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    if (count($iszqtyData) == 0 && $bl01['BLLB'] == '329') {

                                        foreach ($rule8061 as $item) {
                                            if (strpos($blmc, $item) !== false) {
                                                if (strtotime($bl01['ZXSJ']) < strtotime($ssrq) && strtotime($bl01['ZXSJ']) > $sssj) {

                                                    if (strtotime($bl01['first_blsy_time']) < strtotime($ssrq)) {
                                                        $iszqtyData['yes'][] = $bl01;
                                                    } else {
                                                        $iszqtyData['no'][] = $bl01;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    if (count($isaqhcData) == 0 && $bl01['BLLB'] == '303') {
                                        foreach ($rule8063 as $item) {
                                            if (strpos($blmc, $item) !== false) {
                                                if (strtotime($bl01['ZXSJ']) < strtotime($ssrq) && strtotime($bl01['ZXSJ']) > $sssj) {

                                                    if (strtotime($bl01['first_blsy_time']) < strtotime($ssrq)) {
                                                        $isaqhcData['yes'][] = $bl01;
                                                    } else {
                                                        $isaqhcData['no'][] = $bl01;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    if (count($isssjlData) == 0 && $bl01['BLLB'] == '303') {
                                        foreach ($rule8065 as $item) {
                                            if (strpos($blmc, $item) !== false) {
                                                if (strtotime($bl01['ZXSJ']) > strtotime($jsrq) && strtotime($bl01['ZXSJ']) < strtotime(date('Y-m-d H:i:s', strtotime($jsrq) + (3600 * 24)))) {

                                                    if (strtotime($bl01['first_blsy_time']) > strtotime($jsrq) && strtotime($bl01['first_blsy_time']) < strtotime(date('Y-m-d H:i:s', strtotime($jsrq) + (3600 * 24)))) {
                                                        $isssjlData['yes'][] = $bl01;
                                                    } else {
                                                        $isssjlData['no'][] = $bl01;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }

                            if (isset($issqxjData['yes']) && count($issqxjData['yes']) > 0) {
                                $issqxj = true;
                                foreach ($issqxjData['yes'] as $item1) {
                                    $orderGroup['content'][] = [
                                        'status' => 1,
                                        'content' => '术前小结【' . $item1['BLMC'] . '】'
                                    ];
                                    $orderGroup['content'][] = [
                                        'status' => 1,
                                        'content' => '首次签名时间【' . $item1['first_blsy_time'] . '】'
                                    ];
                                }
                            } elseif (isset($issqxjData['no']) && count($issqxjData['no']) > 0) {
                                foreach ($issqxjData['no'] as $item2) {
                                    $orderGroup['content'][] = [
                                        'status' => 0,
                                        'content' => '术前小结【' . $item2['BLMC'] . '】'
                                    ];
                                    $orderGroup['content'][] = [
                                        'status' => 0,
                                        'content' => '首次签名时间【' . $item2['first_blsy_time'] . '(超时)】'
                                    ];
                                }
                            } else {
                                $orderGroup['content'][] = [
                                    'status' => 0,
                                    'content' => '术前小结【无】'
                                ];
                            }
                            if (isset($iszqtyData['yes']) && count($iszqtyData['yes']) > 0) {
                                $iszqty = true;
                                //var_dump('iszqtyData', $iszqtyData);
                                foreach ($iszqtyData['yes'] as $item3) {
                                    $orderGroup['content'][] = [
                                        'status' => 1,
                                        'content' => '知情同意书【' . $item3['BLMC'] . '】'
                                    ];
                                    $orderGroup['content'][] = [
                                        'status' => 1,
                                        'content' => '首次签名时间【' . $item3['first_blsy_time'] . '】'
                                    ];
                                }
                            } elseif (isset($iszqtyData['no']) && count($iszqtyData['no']) > 0) {
                                foreach ($iszqtyData['no'] as $item4) {
                                    $orderGroup['content'][] = [
                                        'status' => 0,
                                        'content' => '知情同意书【' . $item4['BLMC'] . '】'
                                    ];
                                    $orderGroup['content'][] = [
                                        'status' => 0,
                                        'content' => '首次签名时间【' . $item4['first_blsy_time'] . '(超时)】'
                                    ];
                                }
                            } else {
                                $orderGroup['content'][] = [
                                    'status' => 0,
                                    'content' => '知情同意书【无】'
                                ];
                            }

                            // 郸城不查手术安全核查表
                            if (env('APP_NAME') == 'dancheng') {
                                $isaqhc = true;
                            } else {
                                if (isset($isaqhcData['yes']) && count($isaqhcData['yes']) > 0) {
                                    $isaqhc = true;
                                    foreach ($isaqhcData['yes'] as $item5) {
                                        $orderGroup['content'][] = [
                                            'status' => 1,
                                            'content' => '手术安全核查表【' . $item5['BLMC'] . '】'
                                        ];
                                        $orderGroup['content'][] = [
                                            'status' => 1,
                                            'content' => '首次签名时间【' . $item5['first_blsy_time'] . '】'
                                        ];
                                    }
                                } elseif (isset($isaqhcData['no']) && count($isaqhcData['no']) > 0) {
                                    foreach ($isaqhcData['no'] as $item6) {
                                        $orderGroup['content'][] = [
                                            'status' => 0,
                                            'content' => '手术安全核查表【' . $item6['BLMC'] . '】'
                                        ];
                                        $orderGroup['content'][] = [
                                            'status' => 0,
                                            'content' => '首次签名时间【' . $item6['first_blsy_time'] . '(超时)】'
                                        ];
                                    }
                                } else {
                                    $orderGroup['content'][] = [
                                        'status' => 0,
                                        'content' => '手术安全核查表【无】'
                                    ];
                                }
                            }

                            if (isset($isssjlData['yes']) && count($isssjlData['yes']) > 0) {
                                $isssjl = true;
                                foreach ($isssjlData['yes'] as $item7) {
                                    $orderGroup['content'][] = [
                                        'status' => 1,
                                        'content' => '手术记录【' . $item7['BLMC'] . '】'
                                    ];
                                    $orderGroup['content'][] = [
                                        'status' => 1,
                                        'content' => '首次签名时间【' . $item7['first_blsy_time'] . '】'
                                    ];
                                }
                            } elseif (isset($isssjlData['no']) && count($isssjlData['no']) > 0) {
                                foreach ($isssjlData['no'] as $item8) {
                                    $orderGroup['content'][] = [
                                        'status' => 0,
                                        'content' => '手术记录【' . $item8['BLMC'] . '】'
                                    ];
                                    $orderGroup['content'][] = [
                                        'status' => 0,
                                        'content' => '首次签名时间【' . $item8['first_blsy_time'] . '(超时)】'
                                    ];
                                }
                            } else {
                                $orderGroup['content'][] = [
                                    'status' => 0,
                                    'content' => '手术记录【无】'
                                ];
                            }

                            $AAC01 = date('Y-m-d', strtotime($value->AAC01));
                            $isshbc1 = true;
                            $isshbc2 = true;
                            $isshbc3 = true;
                            //如果小于等于第一天，不需要质控。isshbc=true，如果大于第一天小于第二天，那么第一天的病程需要有。第二天。。。第三天。。。
                            if (strtotime($AAC01) <= strtotime($shdyt)) {
                                $isshbc = true;
                            }
                            if (strtotime($AAC01) > strtotime($shdyt) && strtotime($AAC01) <= strtotime($shdet)) {
                                if (isset($isshbcData1['yes']) && count($isshbcData1['yes']) > 0) {
                                    $isshbc = true;
                                    foreach ($isshbcData1['yes'] as $item9) {
                                        $orderGroup['content'][] = [
                                            'status' => 1,
                                            'content' => '术后第一天病程【' . $item9['BLMC'] . '】'
                                        ];
                                        $orderGroup['content'][] = [
                                            'status' => 1,
                                            'content' => '首次签名时间【' . $item9['first_blsy_time'] . '】'
                                        ];
                                    }
                                } elseif (isset($isshbcData1['no']) && count($isshbcData1['no']) > 0) {
                                    foreach ($isshbcData1['no'] as $item10) {
                                        $orderGroup['content'][] = [
                                            'status' => 0,
                                            'content' => '术后第一天病程【' . $item10['BLMC'] . '】'
                                        ];
                                        $orderGroup['content'][] = [
                                            'status' => 0,
                                            'content' => '首次签名时间【' . $item10['first_blsy_time'] . '(超时)】'
                                        ];
                                    }
                                    $isshbc1 = false;
                                } else {
                                    $orderGroup['content'][] = [
                                        'status' => 0,
                                        'content' => '术后第一天病程【无】'
                                    ];
                                    $isshbc1 = false;
                                }
                            }
                            if (strtotime($AAC01) > strtotime($shdet) && strtotime($AAC01) <= strtotime($shdst)) {
                                if (isset($isshbcData2['yes']) && count($isshbcData2['yes']) > 0) {
                                    $isshbc = true;
                                    foreach ($isshbcData2['yes'] as $item11) {
                                        $orderGroup['content'][] = [
                                            'status' => 1,
                                            'content' => '术后第二天病程【' . $item11['BLMC'] . '】'
                                        ];
                                        $orderGroup['content'][] = [
                                            'status' => 1,
                                            'content' => '首次签名时间【' . $item11['first_blsy_time'] . '】'
                                        ];
                                    }
                                } elseif (isset($isshbcData2['no']) && count($isshbcData2['no']) > 0) {
                                    foreach ($isshbcData2['no'] as $item12) {
                                        $orderGroup['content'][] = [
                                            'status' => 0,
                                            'content' => '术后第二天病程【' . $item12['BLMC'] . '】'
                                        ];
                                        $orderGroup['content'][] = [
                                            'status' => 0,
                                            'content' => '首次签名时间【' . $item12['first_blsy_time'] . '(超时)】'
                                        ];
                                    }
                                    $isshbc2 = false;
                                } else {
                                    $orderGroup['content'][] = [
                                        'status' => 0,
                                        'content' => '术后第二天病程【无】'
                                    ];
                                    $isshbc2 = false;
                                }
                            }
                            if (strtotime($AAC01) > strtotime($shdst)) {
                                if (isset($isshbcData3['yes']) && count($isshbcData3['yes']) > 0) {
                                    $isshbc = true;
                                    foreach ($isshbcData3['yes'] as $item13) {
                                        $orderGroup['content'][] = [
                                            'status' => 1,
                                            'content' => '术后第三天病程【' . $item13['BLMC'] . '】'
                                        ];
                                        $orderGroup['content'][] = [
                                            'status' => 1,
                                            'content' => '首次签名时间【' . $item13['first_blsy_time'] . '】'
                                        ];
                                    }
                                } elseif (isset($isshbcData3['no']) && count($isshbcData3['no']) > 0) {
                                    foreach ($isshbcData3['no'] as $item14) {
                                        $orderGroup['content'][] = [
                                            'status' => 0,
                                            'content' => '术后第三天病程【' . $item14['BLMC'] . '】'
                                        ];
                                        $orderGroup['content'][] = [
                                            'status' => 0,
                                            'content' => '首次签名时间【' . $item14['first_blsy_time'] . '(超时)】'
                                        ];
                                    }
                                    $isshbc3 = false;
                                } else {
                                    $orderGroup['content'][] = [
                                        'status' => 0,
                                        'content' => '术后第三天病程【无】'
                                    ];
                                    $isshbc3 = false;
                                }
                            }
                            if ($isshbc1 && $isshbc2 && $isshbc3) {
                                $isshbc = true;
                            }
                        }
                    }
                    $sssj = strtotime($jsrq);
                    $m++;

                    if ($issqxj && $iszqty && $isaqhc && $isssjl && $isshbc) {
                        $ssxgjl = true;
                        $orderGroup['status'] = 1;
                    }
                    if (!$ssxgjl) {
                        $allssxgjl = false;
                    }
                    $errorContent[] = $orderGroup;
                }
                if ($allssxgjl && $insert['ssxgjl_fm'] > 0) {
                    $insert['ssxgjl_fz'] = 1;
                }
                if ($insert['ssxgjl_fm'] > 0) {
                    $insert['ssxgjl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                    Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                }
                if (!empty($zyh)) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }
        }
    }

    /**
     * 抢救记录完成率
     */
    public function hzqjcgl($zyh, $start, $end)
    {
        $page = 1;

        $feeService = new ElasticsearchService('fee_detailed');
        $bl01Service = new ElasticsearchService('bl01_202303');
        $yzbService = new ElasticsearchService('yzb_2023');
        $bagl_11 = ZbBagl::getFirstById(11, true);
        $bagl_27 = ZbBagl::getFirstById(27, true);
        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母

        while (true) {
            $data = DB::table($cyzs->table_name)
                ->leftJoin('patient_doctor_info', $cyzs->table_name . '.' . $cyzs->MED_REC_ID, '=', 'patient_doctor_info.AAA28')
                ->when($cyzs->condition, function ($query) use ($cyzs) {
                    return $query->whereRaw($cyzs->condition);
                })
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('ZYH', $zyh);
                    }
                    return $query;
                })
                ->whereBetween('AAC01', [$start, $end])
                ->paginate(500, [$cyzs->table_name . '.' . $cyzs->MED_REC_ID, 'AAB01', 'AAC01', 'AAC11N', 'AEE03'], 'page', $page);
            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, 'MED_REC_ID');
                $errorContent = [];
                $insert = [
                    'zyh' => $ZYH,
                    'hzqjcgl_fz' => 0,
                    'hzqjcgl_fm' => 0,
                    'hzqjcgl_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                //删除
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['hzqjcgl_fm' => null, 'hzqjcgl_fz' => null, 'hzqjcgl_error' => null]);

                // 查询抢救费用
                $must = [
                    ["term" => ['MED_REC_ID' => $ZYH]],
                    ["match_phrase" => ['FYMC' => '抢救']]
                ];
                $params = $feeService->clearMust()->queryByMustBatch($must)->paginate(1, 1000)->getParams();
                $feeRes = app('es')->search($params);
                $feeData = $feeService->getDataByEs($feeRes);

                if (!empty($feeData[0])) {
                    $insert['hzqjcgl_fm'] = 1;
                    $orderGroup = [
                        'status' => 0,
                        'content' => []
                    ];

                    // 添加费用信息
                    $orderGroup['content'][] = [
                        'status' => 1,
                        'content' => sprintf(
                            "收费项目【%s，%s】",
                            $feeData[0][0]['FYMC'],
                            !empty($feeData[0][0]['JFQR']) ? $feeData[0][0]['JFQR'] : '无'
                        )
                    ];

                    // 查询抢救医嘱
                    $must = [
                        ["term" => ['ZYH' => $ZYH]],
                        ["match_phrase" => ['YZMC' => '抢救']]
                    ];
                    $params = $yzbService->clearMust()->queryByMustBatch($must)->paginate(1, 1000)->getParams();
                    $yzbRes = app('es')->search($params);
                    $yzbData = $yzbService->getDataByEs($yzbRes);

                    if (!empty($yzbData[0])) {
                        foreach ($yzbData[0] as $yzb) {
                            $orderGroup['content'][] = [
                                'status' => 1,
                                'content' => sprintf("医嘱【%s，%s】", $yzb['YZMC'], $yzb['KZSJ'])
                            ];

                            // 查询病程记录
                            $must = [
                                ["term" => ['JZHM' => $ZYH]],
                                ['match_phrase' => ['HJNR' => '抢救']]
                            ];
                            $notMust = [
                                ['match_phrase' => ['HJNR' => '死亡']]
                            ];
                            $params = $bl01Service->clearMust()
                                ->queryByMustBatch($must)
                                ->queryByMustNotBatch($notMust)
                                ->orderBy("first_blsy_time", 'asc')
                                ->paginate(1, 1000)
                                ->getParams();
                            $bl01Res = app('es')->search($params);
                            $bcjlData = $bl01Service->getDataByEs($bl01Res);

                            if (!empty($bcjlData[0])) {
                                $bcjl = data_get($bcjlData, '0.0');
                                //                                foreach ($bcjlData[0] as $bcjl) {
                                $KZSJ_END = date('Y-m-d H:i:s', strtotime($yzb['KZSJ']) + (3600 * 6));

                                if ($bcjl[$bagl_11->table_field] <= $KZSJ_END) {
                                    $insert['hzqjcgl_fz'] = 1;
                                    $orderGroup['status'] = 1;
                                    $orderGroup['content'][] = [
                                        'status' => 1,
                                        'content' => '病程记录【' . $bcjl[$bagl_11->table_field] . '不含' . $bagl_27->keyword . '，6小时内】'
                                    ];
                                } else {
                                    $orderGroup['content'][] = [
                                        'status' => 0,
                                        'content' => '病程记录【' . $bcjl[$bagl_11->table_field] . '不含' . $bagl_27->keyword . '，未在6小时内】'
                                    ];
                                }
                                //                                }
                            } else {
                                $orderGroup['content'][] = [
                                    'status' => 0,
                                    'content' => "病程记录【无】"
                                ];
                            }
                        }
                    } else {
                        $orderGroup['content'][] = [
                            'status' => 0,
                            'content' => "医嘱【无】"
                        ];
                    }

                    $errorContent[] = $orderGroup;
                }

                if ($insert['hzqjcgl_fm'] > 0) {
                    $insert['hzqjcgl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                    Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                }
                if (!empty($zyh)) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }
        }

        return true;
    }

    /**
     * 抢救记录及时率
     */
    public function hzqjjsl($zyh, $start, $end)
    {
        $page = 1;

        $bl01Service = new ElasticsearchService('bl01_202303');
        $yzbService = new ElasticsearchService('yzb_2023');
        $feeService = new ElasticsearchService('fee_detailed');

        $bagl_11 = ZbBagl::getFirstById(11, true); // 抢救费用关键词
        $bagl_31 = ZbBagl::getFirstById(31, true); // 病历类别
        $bagl_32 = ZbBagl::getFirstById(32, true); // 病历字段
        $bagl_33 = ZbBagl::getFirstById(33, true); // 抢救关键词


        while (true) {
            $data = DB::table('patient_info')
                ->leftJoin('patient_doctor_info', 'patient_info.MED_REC_ID', '=', 'patient_doctor_info.AAA28')
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('ZYH', $zyh);
                    }
                    return $query;
                })
                ->whereBetween('AAC01', [$start, $end])
                ->whereNotNull('MED_REC_ID')
                ->paginate(500, ['patient_info.AAA28', 'MED_REC_ID', 'AAB01', 'AAC01', 'AAC11N', 'AEE03'], 'page', $page);

            if ($page == 1) {
                echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, 'MED_REC_ID');
                $errorContent = [];
                $insert = [
                    'zyh' => $ZYH,
                    'hzqjjsl_fz' => 0,
                    'hzqjjsl_fm' => 0,
                    'hzqjjsl_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                //删除
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['hzqjjsl_fm' => null, 'hzqjjsl_fz' => null, 'hzqjjsl_error' => null]);

                // 查询抢救费用
                $must = [
                    ["term" => ['MED_REC_ID' => $ZYH]],
                    ["match_phrase" => ['FYMC' => '抢救']]
                ];
                $params = $feeService->clearMust()->queryByMustBatch($must)->paginate(1, 1000)->getParams();
                $feeRes = app('es')->search($params);
                $feeData = $feeService->getDataByEs($feeRes);

                if (!empty($feeData[0])) {
                    $insert['hzqjjsl_fm'] = 1;
                    $allGroupsValid = true; // 用于跟踪所有组是否都满足条件

                    // 查询抢救医嘱
                    $must = [
                        ["term" => ['ZYH' => $ZYH]],
                        ["match_phrase" => ['YZMC' => '抢救']]
                    ];
                    $params = $yzbService->clearMust()->queryByMustBatch($must)->paginate(1, 1000)->getParams();
                    $yzbRes = app('es')->search($params);
                    $yzbData = $yzbService->getDataByEs($yzbRes);

                    $orderGroup = [
                        'status' => 0,
                        'content' => []
                    ];

                    if (!empty($yzbData[0])) {
                        foreach ($yzbData[0] as $yzb) {
                            //                            $orderGroup = [
                            //                                'status' => 0,
                            //                                'content' => []
                            //                            ];

                            // 添加费用信息
                            $orderGroup['content'][] = [
                                'status' => 1,
                                'content' => sprintf("收费项目【%s】", $feeData[0][0]['FYMC'])
                            ];

                            // 添加医嘱信息
                            $orderGroup['content'][] = [
                                'status' => 1,
                                'content' => sprintf("医嘱【%s】", $yzb['YZMC'])
                            ];

                            $orderGroup['content'][] = [
                                'status' => 1,
                                'content' => sprintf("开嘱时间【%s】", $yzb['KZSJ'])
                            ];

                            // 查询开嘱时间后的抢救记录
                            $must = [
                                ["term" => ['JZHM' => $ZYH]],
                                ["term" => ['BLLB' => $bagl_31->keyword]],
                                ["match_phrase" => [$bagl_32->keyword => $bagl_33->keyword]],
                                ['range' => [$bagl_11->table_field => ['gte' => $yzb['KZSJ']]]]
                            ];
                            $params = $bl01Service->clearMust()
                                ->queryByMustBatch($must)
                                ->orderBy($bagl_11->table_field, 'asc')
                                ->source(['BLBH', 'BLMC', 'ZXSJ', $bagl_11->table_field])
                                ->paginate(1, 1000)
                                ->getParams();
                            $bl01Res = app('es')->search($params);
                            $rescueRecords = $bl01Service->getDataByEs($bl01Res);

                            $groupValid = false; // 用于跟踪当前组是否满足条件
                            if (!empty($rescueRecords[0])) {
                                $firstRecord = $rescueRecords[0][0];
                                $timeDiff = strtotime($firstRecord[$bagl_11->table_field]) - strtotime($yzb['KZSJ']);

                                if ($timeDiff <= 6 * 60 * 60) {
                                    $insert['hzqjjsl_fz'] = 1;
                                    $orderGroup['status'] = 1;
                                    $orderGroup['content'][] = [
                                        'status' => 1,
                                        'content' => sprintf(
                                            "抢救记录【%s】（6小时内）",
                                            $firstRecord[$bagl_11->table_field]
                                        )
                                    ];
                                } else {
                                    $orderGroup['content'][] = [
                                        'status' => 0,
                                        'content' => sprintf(
                                            "抢救记录【%s】（超6小时）",
                                            $firstRecord[$bagl_11->table_field]
                                        )
                                    ];
                                }
                            } else {
                                $orderGroup['content'][] = [
                                    'status' => 0,
                                    'content' => "无抢救记录"
                                ];
                            }

                            if (!$groupValid) {
                                $allGroupsValid = false;
                            }

                            $errorContent[] = $orderGroup;
                        }

                        // 只有当所有组都满足条件时，才设置分子为1
                        if ($allGroupsValid) {
                            $insert['hzqjjsl_fz'] = 1;
                        }
                    } else {
                        $orderGroup['content'][] = [
                            'status' => 0,
                            'content' => "医嘱【无】"
                        ];
                        $errorContent[] = $orderGroup;
                    }
                }

                if ($insert['hzqjjsl_fm'] > 0) {
                    $insert['hzqjjsl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                    Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                }
                if (!empty($zyh)) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }
        }

        return true;
    }

    /**
     * 不合理复制病历发生率
     */
    public function bhlfzbl($zyh, $start, $end)
    {
        $page = 1;

        $ryZbBagl = ZbBagl::query()->where('id', 16)->first(['id', 'keyword', 'table_name', 'table_field']); //入院记录
        $rcZbBagl = ZbBagl::query()->where('id', 17)->first(['id', 'keyword', 'table_field']); //日常病程记录
        $esService = new ElasticsearchService('bl01_202303');

        while (true) {
            $data = DB::table('patient_info')
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->leftJoin('patient_doctor_info', 'patient_info.MED_REC_ID', '=', 'patient_doctor_info.AAA28')
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('ZYH', $zyh);
                    }
                    return $query;
                })
                ->whereBetween('AAC01', [$start, $end])
                ->paginate(500, ['patient_info.AAA28', 'MED_REC_ID', 'ZYH', 'AAB01', 'AAC01', 'AAC11N', 'AEE03'], 'page', $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, 'MED_REC_ID');
                $errorContent = [];
                $insert = [
                    'zyh' => $ZYH,
                    'bhlfzbl_fz' => 0,
                    'bhlfzbl_fm' => 1, // 分母默认为1
                    'bhlfzbl_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                //删除
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['bhlfzbl_fm' => null, 'bhlfzbl_fz' => null, 'bhlfzbl_error' => null]);

                $surgeryGroup = [
                    'status' => 0,
                    'content' => []
                ];

                // 获取首次病程记录（病历特点）
                $bllb294295Row = Bllb294_295::query()
                    ->where('ZYH', $ZYH)
                    ->first();

                $BLTD = "";
                $BLTDArray = [];
                if (!empty($bllb294295Row)) {
                    $BLTD = $bllb294295Row->BLTD;
                    $BLTDArray = preg_split('//u', $BLTD, 0, PREG_SPLIT_NO_EMPTY);
                    $BLTDArray = array_unique(array_filter($BLTDArray));
                }

                // 获取入院记录
                $tablename = $ryZbBagl['table_name'] . ".BLBH";
                $ryRow = DB::table($ryZbBagl['table_name'])
                    ->leftJoin('EMR_BL_BLXG', $tablename, '=', 'EMR_BL_BLXG.BLBH')
                    ->where($ryZbBagl['table_name'] . '.JZHM', $ZYH)
                    ->where($ryZbBagl['table_field'], '=', $ryZbBagl['keyword'])
                    ->first();

                $RYJR = "";
                $RYJRArray = [];
                if (!empty($ryRow)) {
                    $RYJR = $ryRow->HJNR;
                    $RYJRArray = preg_split('//u', $RYJR, 0, PREG_SPLIT_NO_EMPTY);
                    $RYJRArray = array_unique(array_filter($RYJRArray));
                }

                // 首次病程记录与入院记录对比
                if (!empty($bllb294295Row) && !empty($ryRow)) {
                    $res = array_intersect($BLTDArray, $RYJRArray);
                    if (!empty($BLTDArray) && count($BLTDArray) > 0 && count($res) / count($BLTDArray) >= 0.9) {
                        $insert['bhlfzbl_fz'] = 1;
                        $surgeryGroup['status'] = 1;
                        $surgeryGroup['content'][] = [
                            'status' => 1,
                            'content' => "首次病程记录【{$bllb294295Row->BLMC}】和入院记录【{$ryRow->BLMC}】90%雷同"
                        ];
                    }
                }

                // 日常病程记录比对
                $must = [
                    ["term" => ['JZHM' => $ZYH]],
                    ['terms' => ['MBLB' => explode(',', $rcZbBagl['keyword'])]]
                ];
                $params = $esService->clearMust()
                    ->queryByMustBatch($must)
                    ->getParams();
                $result = app('es')->search($params);
                $result = $esService->getDataByEs($result);

                if (!empty($result[0]) && count($result[0]) > 1) {
                    foreach ($result[0] as $kk => $vv) {
                        $HJNR = preg_split('//u', $vv['HJNR'], 0, PREG_SPLIT_NO_EMPTY);
                        $HJNR = array_unique(array_filter($HJNR));

                        for ($i = $kk + 1; $i < count($result[0]); $i++) {
                            if (!isset($result[0][$i]) || !isset($result[0][$i]['HJNR']) || $vv['BLMC'] == $result[0][$i]['BLMC']) {
                                continue;
                            }

                            $iHJNR = preg_split('//u', $result[0][$i]['HJNR'], 0, PREG_SPLIT_NO_EMPTY);
                            $iHJNR = array_unique(array_filter($iHJNR));
                            $rcResult = array_intersect($HJNR, $iHJNR);

                            if (!empty($rcResult) && count($rcResult) > 0 && count($rcResult) / count($HJNR) >= 0.9) {
                                $insert['bhlfzbl_fz'] = 1;
                                $surgeryGroup['status'] = 1;
                                $surgeryGroup['content'][] = [
                                    'status' => 1,
                                    'content' => "病程记录【{$vv['BLMC']}】和病程【{$result[0][$i]['BLMC']}】90%雷同"
                                ];
                            }
                        }
                    }
                }

                if ($insert['bhlfzbl_fm'] > 0) {
                    $errorContent[] = $surgeryGroup;
                    if (!empty($errorContent)) {
                        $insert['bhlfzbl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                        Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                    }
                }
                if (!empty($zyh)) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }
        }

        return true;
    }



    /**
     * 医师查房完整率
     */
    public function yscf($zyh, $start, $end)
    {
        $page = 1;

        $bagl_12 = ZbBagl::getFirstById(12, true);
        $bagl_11 = ZbBagl::getFirstById(11, true);
        $bagl_13 = ZbBagl::getFirstById(13, true);
        $bagl_14 = ZbBagl::getFirstById(14, true);
        $bagl_15 = ZbBagl::getFirstById(15, true);

        // 获取病程记录类型
        $ruleMap8010 = RuleWordMap::query()->where('id', '=', 8010)->value('keyword');
        $ruleMap8010 = !empty($ruleMap8010) ? $ruleMap8010 : 294; // 病程记录类型BLLB值

        // 判断是否包含逗号
        if (strpos($ruleMap8010, ',') !== false) {
            $blTypes = explode(',', $ruleMap8010);
        } else {
            $blTypes = [$ruleMap8010];
        }

        // 获取查房记录的时间字段
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ'; // 查房记录的时间字段名

        // 获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';

        // 获取职工信息
        $staff = Staff::query()->get()->toArray();
        $staffArr = [];
        foreach ($staff as $v) {
            $staffArr[$v['code']] = $v;
        }

        // 处理护士分床条件
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

        $bl01esService = new ElasticsearchService('bl01_202303');
        $zyHcmxEsService = new ElasticsearchService($bagl_12->table_name);
        $mzjlesService = new ElasticsearchService('mzjl_2023');
        $sssqesService = new ElasticsearchService('sssq_2023');

        while (true) {
            $data = DB::table('patient_info')
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->leftJoin('patient_doctor_info', 'patient_info.MED_REC_ID', '=', 'patient_doctor_info.AAA28')
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('ZYH', $zyh);
                    }
                    return $query;
                })
                ->whereBetween('AAC01', [$start, $end])
                ->whereNotNull('MED_REC_ID')
                ->paginate(500, ['patient_info.AAA28', 'MED_REC_ID', 'ZYH', 'AAC04', 'AAB01', 'AAC01', 'AAC11N', 'AEE03'], 'page', $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
                $total = $data->lastPage();
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, 'MED_REC_ID');
                $errorContent = [];
                $insert = [
                    'zyh' => $ZYH,
                    'yscf_fz' => 0,
                    'yscf_fm' => 1,
                    'yscf_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                //删除
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['yscf_fm' => null, 'yscf_fz' => null, 'yscf_error' => null]);

                $wardRoundGroup = [
                    'status' => 0,
                    'content' => []
                ];

                // 1. 获取护士分床数据
                $must = [
                    ["term" => [$bagl_12->MED_REC_ID => $ZYH]]
                ];
                $must = array_merge($must, $baglWhere);
                $params = $zyHcmxEsService->clearMust()->queryByMustBatch($must)->getParams();
                $zyHcmxRes = app('es')->search($params);
                $zyHcmxData = $zyHcmxEsService->getDataByEs($zyHcmxRes);

                if (empty($zyHcmxData[0])) {
                    $wardRoundGroup['content'][] = [
                        'status' => 0,
                        'content' => '护士分床数据不存在'
                    ];
                    continue;
                }

                $startTime = $zyHcmxData[0][0][$bagl_12->table_field];
                $endTime = $value->AAC01;

                // 计算住院天数
                $hospitalDays = $value->AAC04;

                $wardRoundGroup['content'][] = [
                    'status' => 1,
                    'content' => '住院天数【' . $hospitalDays . '】'
                ];

                $wardRoundGroup['content'][] = [
                    'status' => 1,
                    'content' => '分床时间：' . $startTime . '-' . $endTime
                ];

                // 2. 检查手术申请单和相关记录
                /* $sssqMust = [
                    'terms' => [
                        "ZYH" => [$ZYH]
                    ]
                ];
                $params = $sssqesService->clearMust()->queryByMust($sssqMust)->source(['ZYH', 'NSSMC'])->getParams();
                $mzRes = app('es')->search($params);
                $sssqData = $sssqesService->getDataByEs($mzRes);

                if (!empty($sssqData[0])) {
                    foreach ($sssqData[0] as $s) {
                        $surgeryResult = $this->processSurgeryRecords($bl01esService, $mzjlesService, $ZYH, $s['NSSMC'], $endTime);
                        $wardRoundGroup['content'] = array_merge($wardRoundGroup['content'], $surgeryResult);
                    }
                } */

                // 3. 检查查房记录 - 使用周期性检查逻辑
                if ($hospitalDays >= 7) {
                    // 获取所有查房记录
                    try {
                        $bl01Data = EMR_BL_BL01::query()
                            ->where('JZHM', $ZYH)
                            ->whereIn('BLLB', $blTypes)
                            ->where($ruleMap8011, '>=', $startTime)
                            ->where($ruleMap8011, '<=', $endTime)
                            ->get()
                            ->toArray();
                    } catch (\Exception $e) {
                        $wardRoundGroup['content'][] = [
                            'status' => 0,
                            'content' => '查询查房记录出错: ' . $e->getMessage()
                        ];
                        continue;
                    }

                    // 计算完整周期数量（每7天为一个周期）
                    $cycleCount = floor($hospitalDays / 7);
                    $cycleDays = 7; // 固定周期为7天

                    // 按周期检查查房记录
                    $startTimestamp = strtotime($startTime);
                    $endTimestamp = strtotime($endTime);

                    $allCyclesValid = true;

                    for ($i = 0; $i < $cycleCount; $i++) {
                        // 周期开始日期（当天00:00:00）
                        $cycleStartDate = date('Y-m-d', $startTimestamp + $i * $cycleDays * 24 * 3600);
                        $cycleStartTime = $cycleStartDate . ' 00:00:00';

                        // 周期结束日期（第七天的23:59:59）
                        $cycleEndDate = date('Y-m-d', $startTimestamp + ($i * $cycleDays + 6) * 24 * 3600);
                        $cycleEndTime = $cycleEndDate . ' 23:59:59';

                        // 查找当前周期内的查房记录
                        $validRoundRecords = [];
                        $highLevelRounds = [];
                        //超时的病程记录
                        $overtimeRecords = [];
                        //未签名的病程记录
                        $unSignedRecords = [];

                        foreach ($bl01Data as $record) {
                            $recordTime = $record[$ruleMap8011];
                            // 只检查在当前周期内的记录
                            if (
                                strtotime($recordTime) >= strtotime($cycleStartTime) &&
                                strtotime($recordTime) <= strtotime($cycleEndTime)
                            ) {
                                // 检查医生职称
                                try {
                                    $blsy = EMR_BL_BLSY::query()->where('BLBH', $record['BLBH'])->pluck('SYYS')->toArray();
                                    $blsy = array_unique($blsy);
                                } catch (\Exception $e) {
                                    continue; // 查询出错时跳过当前记录
                                }

                                $highLevelDoctors = [];

                                foreach ($blsy as $doctor) {
                                    $staff = isset($staffArr[$doctor]) ? $staffArr[$doctor] : null;
                                    if (!$staff) continue;

                                    $ygjb = $staff['ygjb_text'];
                                    $doctorName = $staff['name'];

                                    //查看是否包含主任关键词
                                    if (strpos($ygjb, '主任') !== false) {
                                        $highLevelDoctors[] = $doctorName . '（' . $staff['ygjb_text'] . '）';
                                    }

                                    // 检查是否是副高或正高级职称
                                    /* if (
                                        in_array($ygjb, explode(',', $bagl_13->keyword)) || 
                                        in_array($ygjb, explode(',', $bagl_14->keyword))
                                    ) {
                                        $highLevelDoctors[] = $doctorName . '（' . $staff['ygjb_text'] . '）';
                                    } */
                                }

                                // 如果有高级职称医师
                                if (!empty($highLevelDoctors)) {
                                    // 检查是否有签名时间
                                    $firstBlsyTime = isset($record[$firstBlsyTimeField]) ? $record[$firstBlsyTimeField] : '';

                                    if (!empty($firstBlsyTime)) {
                                        // 检查签名时间是否在周期内
                                        if (
                                            strtotime($firstBlsyTime) >= strtotime($cycleStartTime) &&
                                            strtotime($firstBlsyTime) <= strtotime($cycleEndTime)
                                        ) {
                                            $validRoundRecords[] = [
                                                'time' => $recordTime,
                                                'BLMC' => $record['BLMC'],
                                                'doctors' => $highLevelDoctors,
                                                'sign_time' => $firstBlsyTime
                                            ];
                                        } else {
                                            $overtimeRecords[] = [
                                                'BLMC' => $record['BLMC'],
                                                'time' => $recordTime,
                                                'sign_time' => $firstBlsyTime
                                            ];
                                        }
                                    } else {
                                        $unSignedRecords[] = [
                                            'BLMC' => $record['BLMC'],
                                            'time' => $recordTime,
                                            'sign_time' => $firstBlsyTime
                                        ];
                                    }
                                }
                            }
                        }

                        // 检查当前周期内的有效查房次数
                        if (count($validRoundRecords) >= 2) {
                            $doctorInfo = [];
                            foreach ($validRoundRecords as $record) {
                                $doctorInfo[] = implode("，", $record['doctors']) . '【' . $record['time'] . '】';
                            }

                            $wardRoundGroup['content'][] = [
                                'status' => 1,
                                'content' => sprintf(
                                    '周期【%s 至 %s】高级职称医师查房【%d次】',
                                    $cycleStartDate,
                                    $cycleEndDate,
                                    count($validRoundRecords),
                                )
                            ];
                        } else {
                            $allCyclesValid = false;
                            $wardRoundGroup['content'][] = [
                                'status' => 0,
                                'content' => sprintf(
                                    '周期【%s 至 %s】高级职称医师查房不足2次，实际【%d次】',
                                    $cycleStartDate,
                                    $cycleEndDate,
                                    count($validRoundRecords)
                                )
                            ];
                            if (!empty($overtimeRecords)) {
                                foreach ($overtimeRecords as $record) {
                                    $wardRoundGroup['content'][] = [
                                        'status' => 0,
                                        'content' => sprintf(
                                            '查房记录：【%s】首次签名时间【%s】（超时）',
                                            $record['BLMC'],
                                            $record['sign_time']
                                        )
                                    ];
                                }
                            }
                            if (!empty($unSignedRecords)) {
                                foreach ($unSignedRecords as $record) {
                                    $wardRoundGroup['content'][] = [
                                        'status' => 0,
                                        'content' => sprintf(
                                            '查房记录：【%s】未签名',
                                            $record['BLMC']
                                        )
                                    ];
                                }
                            }

                            // 添加已有的查房记录信息
                            if (!empty($validRoundRecords)) {
                                foreach ($validRoundRecords as $record) {
                                    $wardRoundGroup['content'][] = [
                                        'status' => 0,
                                        'content' => sprintf(
                                            '查房记录：【%s】首次签名时间【%s】',
                                            $record['BLMC'],
                                            $record['sign_time']
                                        )
                                    ];
                                }
                            }
                        }
                    }
                }

                /* // 4. 检查入院48小时内的查房记录
                $admissionEnd = date('Y-m-d H:i:s', strtotime($startTime) + 48 * 3600);
                $must = [
                    ["term" => ["JZHM" => $ZYH]],
                    ["term" => ["BLLB" => 294]],
                    [
                        'range' => [
                            $ruleMap8011 => [
                                'from' => $startTime,
                                'to' => $admissionEnd
                            ]
                        ]
                    ]
                ];

                $params = $bl01esService->clearMust()->queryByMustBatch($must)->source(['BLBH', $ruleMap8011])->paginate(1, 1000)->getParams();
                $result = app('es')->search($params);
                $admissionRecords = $bl01esService->getDataByEs($result);

                $hasHighLevelRounds = false;
                if (!empty($admissionRecords[0])) {
                    foreach ($admissionRecords[0] as $record) {
                        if (empty($record['BLMC'])) {
                            continue;
                        }
                        $blsy = EMR_BL_BLSY::query()
                            ->where('BLBH', '=', $record['BLBH'])
                            ->get()
                            ->toArray();

                        foreach ($blsy as $b) {
                            $staff = Staff::query()->where('code', $b['SYYS'])->first();
                            if ($staff) {
                                if (
                                    strpos($staff->ygjb_text, '主任') !== false
                                ) {
                                    $hasHighLevelRounds = true;
                                    $wardRoundGroup['content'][] = [
                                        'status' => 1,
                                        'content' => sprintf(
                                            '入院48小时内正/副高查房【%s】【%s】',
                                            $record['BLMC'],
                                            $staff->name . '（' . $staff->ygjb_text . '）'
                                        )
                                    ];
                                    break 2;
                                }
                            }
                        }
                    }
                }

                if (!$hasHighLevelRounds) {
                    $wardRoundGroup['content'][] = [
                        'status' => 0,
                        'content' => '入院48小时内无正/副高查房记录'
                    ];
                } */

                // 5. 设置分子值
                $allSuccess = true;
                foreach ($wardRoundGroup['content'] as $content) {
                    if ($content['status'] === 0) {
                        $allSuccess = false;
                        break;
                    }
                }
                $insert['yscf_fz'] = $allSuccess ? 1 : 0;
                $wardRoundGroup['status'] = $allSuccess ? 1 : 0;

                // 6. 保存错误信息
                if ($insert['yscf_fm'] > 0) {
                    $errorContent[] = $wardRoundGroup;
                    if (!empty($errorContent)) {
                        $insert['yscf_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                        Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                    }
                }
                if (!empty($zyh)) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }
        }

        return true;
    }



    /**
     * 临床用血相关记录符合率
     */
    public function lcyx($zyh, $start, $end)
    {
        $page = 1;
        $bagl_19 = ZbBagl::getFirstById(19, true); //临床用血收费
        // 处理逗号分隔的关键词
        if (strpos($bagl_19->keyword, ',') !== false) {
            $bagl_19_keywords = explode(',', $bagl_19->keyword);
        } else {
            $bagl_19_keywords = [$bagl_19->keyword];
        }
        $bagl_20 = ZbBagl::getFirstById(20, true); //输血知情同意书MBLB
        $bagl_21 = ZbBagl::getFirstById(21, true); //备血医嘱
        $bagl_22 = ZbBagl::getFirstById(22, true); //输血医嘱
        // 处理逗号分隔的关键词
        if (strpos($bagl_22->keyword, ',') !== false) {
            $bagl_22_keywords = explode(',', $bagl_22->keyword);
        } else {
            $bagl_22_keywords = [$bagl_22->keyword];
        }
        $bagl_23 = ZbBagl::getFirstById(23, true); //输血效果评估
        $bagl_24 = ZbBagl::getFirstById(24, true); //输血病程记录MBLB
        $bagl_26 = ZbBagl::getFirstById(26, true); //输血效果评估2
        $bagl_11 = ZbBagl::getFirstById(11, true); //病程记录
        $feeService = new ElasticsearchService('fee_detailed');
        $bl01Service = new ElasticsearchService($bagl_11->table_name);
        $yzbService = new ElasticsearchService('yzb_2023');
        $zb34 = ZbBagl::query()->where('id', 34)->value('keyword');
        if (strpos($zb34, ',') !== false) {
            $zb34 = explode(',', $zb34);
        } else {
            $zb34 = [$zb34];
        }

        while (true) {
            $data = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->leftJoin('patient_doctor_info', 'patient_info.MED_REC_ID', '=', 'patient_doctor_info.AAA28')
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('ZYH', $zyh);
                    }
                    return $query;
                })
                ->whereBetween('AAC01', [$start, $end])
                ->whereNotNull('MED_REC_ID')
                //->where('MED_REC_ID','110440781')
                ->paginate(500, ['patient_info.AAA28', 'MED_REC_ID', 'ZYH', 'AAC01', 'AAC11N', 'AEE03'], 'page', $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = $value->MED_REC_ID;

                // 检查是否有输血费用(分母)
                $must = [
                    ["term" => ['MED_REC_ID' => $ZYH]]
                ];
                $should = [];
                foreach ($bagl_19_keywords as $keyword) {
                    $should[] = ["match_phrase_prefix" => ['FYMC' => $keyword]];
                }
                $params = $feeService->clearMust()->queryByMustBatch($must)->queryByShouldBatch($should)->minimumShouldMatch(1)->paginate(1, 1000)->getParams();
                $restful = app('es')->search($params);
                $feeData = $feeService->getDataByEs($restful);

                // 如果没有费用数据,跳过此患者
                if (empty($feeData[0])) {
                    continue;
                }

                $insert = [
                    'zyh' => $ZYH,
                    'lcyx_fz' => 0,
                    'lcyx_fm' => 1, // 有费用就设置分母为1
                    'lcyx_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                //删除
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['lcyx_fm' => null, 'lcyx_fz' => null, 'lcyx_error' => null]);

                // 获取所有输血医嘱
                $must = [
                    ["term" => ['ZYH' => $ZYH]]
                ];
                $should = [];
                // 添加 bagl_22 的关键词到 should
                foreach ($bagl_22_keywords as $keyword) {
                    $should[] = ["match_phrase" => ['YZMC' => $keyword]];
                }
                // 添加 zb34 的关键词到 should
                if (!empty($zb34)) {
                    foreach ($zb34 as $item) {
                        $should[] = ["match_phrase" => ['YZMC' => $item]];
                    }
                }
                $params = $yzbService->clearMust()->queryByMustBatch($must)->queryByShouldBatch($should)->minimumShouldMatch(1)->orderBy('KZSJ', 'asc')->paginate(1, 1000)->getParams();
                $restful = app('es')->search($params);
                $yzbData = $yzbService->getDataByEs($restful);

                $errorContent = [];
                if (!empty($yzbData[0])) {
                    foreach ($yzbData[0] as $yz) {
                        $surgeryGroup = [
                            'status' => 0,
                            'content' => []
                        ];

                        // 1. 检查医嘱时间之前是否有输血同意书
                        $must = [
                            ["term" => [$bagl_11->MED_REC_ID => $ZYH]],
                            ["term" => ['MBLB' => $bagl_20->keyword]],
                            ['range' => ['ZXSJ' => ['lte' => $yz['KZSJ']]]]
                        ];
                        $params = $bl01Service->clearMust()->queryByMustBatch($must)->paginate(1, 1000)->getParams();
                        $restful = app('es')->search($params);
                        $tysData = $bl01Service->getDataByEs($restful);

                        $surgeryGroup['content'][] = [
                            'status' => !empty($tysData[0]) ? 1 : 0,
                            'content' => sprintf("输血同意书【%s】", !empty($tysData[0]) ? $tysData[0][0][$bagl_11->table_field] : '无')
                        ];

                        // 2. 检查48小时内是否有病程记录且包含效果评估
                        $endTime = date('Y-m-d H:i:s', strtotime($yz['KZSJ']) + 48 * 3600);
                        $must = [
                            ["term" => [$bagl_11->MED_REC_ID => $ZYH]],
                            ["term" => ['MBLB' => $bagl_24->keyword]],
                            [
                                'range' => [
                                    $bagl_11->table_field => [
                                        'gte' => $yz['KZSJ'],
                                        'lte' => $endTime
                                    ]
                                ]
                            ]
                        ];
                        $params = $bl01Service->clearMust()->queryByMustBatch($must)->paginate(1, 1000)->getParams();
                        $restful = app('es')->search($params);
                        $bcjlData = $bl01Service->getDataByEs($restful);

                        $hasEffect = 0;
                        $blmcList = [];  // 用于收集所有病历名称

                        if (!empty($bcjlData[0])) {
                            foreach ($bcjlData[0] as $bcjl) {
                                $blmcList[] = $bcjl['BLMC'];  //收集病历名称

                                // 检查 bagl_23 的效果评估关键词
                                $hasEffect23 = false;
                                if (stripos($bagl_23->keyword, ',') !== false) {
                                    $keywords23 = explode(',', $bagl_23->keyword);
                                    foreach ($keywords23 as $keyword) {
                                        if (strpos($bcjl['HJNR'], $keyword) !== false) {
                                            $hasEffect23 = true;
                                            break;
                                        }
                                    }
                                } else {
                                    $hasEffect23 = strpos($bcjl['HJNR'], $bagl_23->keyword) !== false;
                                }

                                // 检查 bagl_26 的效果评估关键词
                                $hasEffect26 = false;
                                if (!empty($bagl_26->keyword)) {
                                    if (stripos($bagl_26->keyword, ',') !== false) {
                                        $keywords26 = explode(',', $bagl_26->keyword);
                                        foreach ($keywords26 as $keyword) {
                                            if (strpos($bcjl['HJNR'], $keyword) !== false) {
                                                $hasEffect26 = true;
                                                break;
                                            }
                                        }
                                    } else {
                                        $hasEffect26 = strpos($bcjl['HJNR'], $bagl_26->keyword) !== false;
                                    }
                                }

                                // 如果 bagl_26 为空,只需要满足 bagl_23
                                // 如果 bagl_26 不为空,需要同时满足 bagl_23 和 bagl_26
                                if ($hasEffect23 && (empty($bagl_26->keyword) || $hasEffect26)) {
                                    $hasEffect = 1;
                                    break;
                                }
                            }
                        }

                        $surgeryGroup['content'][] = [
                            'status' => !empty($yz) ? 1 : 0,
                            'content' => sprintf("输血医嘱【%s】", $yz['KZSJ'])
                        ];
                        $surgeryGroup['content'][] = [
                            'status' => !empty($blmcList) ? 1 : 0,
                            'content' => sprintf("48小时内病程记录【%s】", !empty($blmcList) ? implode('、', $blmcList) : '无')
                        ];

                        $surgeryGroup['content'][] = [
                            'status' => $hasEffect,
                            'content' => sprintf("效果评估【%s】", $hasEffect ? '有' : '无')
                        ];

                        $surgeryGroup['status'] = !empty($tysData[0]) && $hasEffect ? 1 : 0;
                        $errorContent[] = $surgeryGroup;
                    }
                } else {
                    $surgeryGroup = [
                        'status' => 0,
                        'content' => [
                            [
                                'status' => 0,
                                'content' => '无输血医嘱记录'
                            ]
                        ]
                    ];
                    $errorContent[] = $surgeryGroup;
                }

                // 检查所有医嘱是否都满足条件
                $allValid = true;
                foreach ($errorContent as $group) {
                    if ($group['status'] === 0) {
                        $allValid = false;
                        break;
                    }
                }
                $insert['lcyx_fz'] = $allValid ? 1 : 0;

                if ($insert['lcyx_fm'] > 0) {
                    if (!empty($errorContent)) {
                        $insert['lcyx_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                        Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                    }
                }
                if (!empty($zyh)) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }
        }

        return true;
    }

    protected function feeDetailed1($feeService, $zyhList, $bagl)
    {
        $should = [];
        foreach ($zyhList as $zyh) {
            $should[] = ["term" => ['MED_REC_ID' => $zyh]];
        }
        $must = [["match_phrase" => ["FYMC" => $bagl->keyword]]];
        $params = $feeService->clearMust()
            ->queryByMustBatch($must)
            ->queryByShouldBatch($should)
            ->paginate(1, 500)
            ->minimumShouldMatch(1)
            ->getParams();
        $restful = app('es')->search($params);
        $feeData = $feeService->getDataByEs($restful);
        $zyhData = [];
        if (!empty($feeData[0])) {
            foreach ($feeData[0] as $value) {
                if (!in_array($value['MED_REC_ID'], $zyhData)) {
                    $zyhData[] = $value['MED_REC_ID'];
                }
            }
        }

        return $zyhData;
    }

    protected function bl01Value($bl01Service, $MED_REC_ID, $bagl)
    {
        $bagl_11 = ZbBagl::getFirstById(11, true);
        $must = [
            ["term" => ['JZHM' => $MED_REC_ID]],
            //            ["term" => ['BLLB' => 329]]
            ["term" => ['MBLB' => $bagl->keyword]]

        ];
        //        $should = [
        //            ["match_phrase" => ['HJNR' => '输血同意书']],
        //            ["match_phrase" => ['HJNR' => '输血治疗知情同意书']]
        //        ];
        $notMust = ['term' => ["BLZT" => 9]];
        $params = $bl01Service->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNot($notMust)
            //            ->queryByShouldBatch($should)
            //            ->minimumShouldMatch(1)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);
        $sxtys = !empty($bl01Data[0][0][$bagl_11->table_field]) ? $bl01Data[0][0][$bagl_11->table_field] : ''; //id 11

        return $sxtys;
    }

    protected function yzb1($yzbService, $MED_REC_ID, $keyValue, $range)
    {
        $must = [
            ["term" => ['ZYH' => $MED_REC_ID]],
            ["match_phrase" => ['YZMC' => $keyValue->keyword]]
        ];
        if ($range) {
            $must[] = $range;
        }

        if ($keyValue->id == 22) {
            $params = $yzbService->clearMust()
                ->queryByMustBatch($must)
                ->orderBy('KZSJ.keyword', 'desc')
                ->getParams();
            $restful = app('es')->search($params);
            $yzbData = $yzbService->getDataByEs($restful);
            $bxCount = !empty($yzbData[0]) ? $yzbData[0][0]['KZSJ'] : '';
        } else {
            $params = $yzbService->clearMust()
                ->queryByMustBatch($must)
                ->getParams();
            $restful = app('es')->search($params);
            $yzbData = $yzbService->getDataByEs($restful);
            $bxCount = !empty($yzbData[0]) ? 1 : 0;
        }

        return $bxCount;
    }

    protected function lcyxBcjl3($bl01Service, $MED_REC_ID, $keyValue, $bagl = null)
    {
        $bagl_11 = ZbBagl::getFirstById(11, true);
        $bagl_24 = ZbBagl::getFirstById(24, true);
        $must = [
            ["term" => ['JZHM' => $MED_REC_ID]],
            //            ["term" =>['BLLB' => 294]],
            ["term" => $bagl ? ['MBLB' => $bagl->keyword] : ['MBLB' => $bagl_24->keyword]]
            //            ["match_phrase" => ['HJNR' => $keyValue]]
        ];
        if ($keyValue)
            $must[] = ["match_phrase" => ['HJNR' => $keyValue]];
        $notMust = ['term' => ["BLZT" => 9]];
        $params = $bl01Service->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNot($notMust)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);
        $sxbcjl = [];
        if (!empty($bl01Data[0])) {
            foreach ($bl01Data[0] as $value) {
                if (!in_array($value[$bagl_11->table_field], $sxbcjl)) { //id 11
                    $sxbcjl[] = $value[$bagl_11->table_field];
                }
            }
        }

        if ($sxbcjl) {
            return ['code' => 200, 'msg' => '有，' . $sxbcjl[0], 'data' => $sxbcjl];
        }

        return ['code' => 1, 'msg' => '无', 'data' => $sxbcjl];
    }

    protected function lcyxBcjl4($bl01Service, $MED_REC_ID, $zxsjList, $key)
    {
        $isExist = $isExistDate = 0;
        $bagl_26 = ZbBagl::getFirstById(26, true); //输血效果评估2
        $arr1 = [];
        if (!empty($bagl_26->keyword)) {
            if (stripos($bagl_26->keyword, ',') !== false) {
                $arr1 = explode(',', $bagl_26->keyword);
            } else {
                $arr1 = [$bagl_26->keyword];
            }
        }

        //        $xgIsExist = $xgIsExistDate = 0;
        foreach ($zxsjList as $zxsjDate) {
            $ZXSJData = [$zxsjDate, Carbon::parse($zxsjDate)->addDay(2)->toDateTimeString()];
            //            $xgData = $this->lcyxBcjl3($bl01Service,$MED_REC_ID,'效果');
            //            $xg = $xgData['data'];
            //            if ($xg) {
            //                // 记录有数据
            //                $xgIsExist = 1;
            //                foreach ($xg as $v) {
            //                    if ($v>=$ZXSJData[0] && $v<=$ZXSJData[1]) {
            //                        // 记录48小时内有数据
            //                        $xgIsExistDate = 1;
            //                        break;
            //                    }
            //                }
            //            }

            $keyData = $this->lcyxBcjl3($bl01Service, $MED_REC_ID, $key);
            $keyData1 = true;
            foreach ($arr1 as $k => $v) {
                $code = $this->lcyxBcjl3($bl01Service, $MED_REC_ID, $v)['code'];
                if ($code == 200) {
                    $keyData1 = true;
                    break;
                } else {
                    $keyData1 = false;
                }
            }
            if (!empty($keyData1) && !empty($keyData)) {
                $ZXSJ = $keyData['data'];
                if ($ZXSJ) {
                    // 记录有数据
                    $isExist = 1;
                    foreach ($ZXSJ as $v) {
                        if ($v >= $ZXSJData[0] && $v <= $ZXSJData[1]) {
                            // 记录48小时内有数据
                            $isExistDate = 1;
                            break;
                        }
                    }
                }
            } else {
                $isExist = 0;
            }
        }

        //        $xgIsOk = 0;
        //        if ($xgIsExist && $xgIsExistDate) {
        //            $xgMsg = '（有）';
        //            $xgIsOk = 1;
        //        } elseif ((!$xgIsExist && !$xgIsExistDate) && !$xgIsExist) {
        //            $xgMsg = '（无）';
        //        } elseif (!$xgIsExistDate) {
        //            $xgMsg = '（时间超过48小时）';
        //        }

        $isMc = 0;
        if ($isExist && $isExistDate) {
            $str = '有 输血病程记录48小时内';
            $isMc = 1;
        } elseif ((!$isExist && !$isExistDate) && !$isExist) {
            $str = '无';
        } elseif (!$isExistDate) {
            $str = '有 超过输血病程记录48小时';
        }

        //        if ($xgIsOk && $isMc) {
        //            return ['code'=>200,'msg'=>'输血效果'.$xgMsg.' + '.$key.'（48小时内有数据）'];
        //        }
        if ($isMc) {
            return ['code' => 200, 'msg' => $key . '（48小时内有数据）'];
        }

        return ['code' => 1, 'msg' => $key . '（' . $str . '）'];
    }

    private function processWardRounds($bl01esService, $ZYH, $startTime, $endTime, $hospitalDays, $bagl_11, $bagl_13, $bagl_14, $bagl_15)
    {
        $content = [];

        if ($hospitalDays > 7) {
            $currentTime = strtotime($startTime);
            $endTimestamp = strtotime($endTime);

            while ($currentTime < $endTimestamp) {
                $periodEnd = min($endTimestamp, $currentTime + 7 * 24 * 3600);

                // 查询本周期内的查房记录
                $must = [
                    ["term" => ["JZHM" => $ZYH]],
                    ["term" => ["BLLB" => 294]],
                    [
                        'range' => [
                            $bagl_11->table_field => [
                                'from' => date('Y-m-d H:i:s', $currentTime),
                                'to' => date('Y-m-d H:i:s', $periodEnd)
                            ]
                        ]
                    ]
                ];

                $params = $bl01esService->clearMust()->queryByMustBatch($must)->source(['BLBH', $bagl_11->table_field])->getParams();
                $result = app('es')->search($params);
                $records = $bl01esService->getDataByEs($result);

                if (!empty($records[0])) {
                    $zheng = $fu = $zhong = [];
                    foreach ($records[0] as $record) {
                        $blsy = EMR_BL_BLSY::query()
                            ->where('BLBH', '=', $record['BLBH'])
                            ->get()
                            ->toArray();

                        foreach ($blsy as $b) {
                            $staff = Staff::query()->where('code', $b['SYYS'])->first();
                            if ($staff) {
                                if (in_array($staff->ygjb, explode(',', $bagl_13->keyword))) {
                                    $zheng[] = $record[$bagl_11->table_field];
                                } elseif (in_array($staff->ygjb, explode(',', $bagl_14->keyword))) {
                                    $fu[] = $record[$bagl_11->table_field];
                                } elseif (in_array($staff->ygjb, explode(',', $bagl_15->keyword))) {
                                    $zhong[] = $record[$bagl_11->table_field];
                                }
                            }
                        }
                    }

                    if (!empty($zheng)) {
                        $content[] = [
                            'status' => 1,
                            'content' => sprintf('正高查房【%d次/7d，%s】', count($zheng), implode(',', $zheng))
                        ];
                    }
                    if (!empty($fu)) {
                        $content[] = [
                            'status' => 1,
                            'content' => sprintf('副高查房【%d次/7d，%s】', count($fu), implode(',', $fu))
                        ];
                    }
                    if (!empty($zhong)) {
                        $content[] = [
                            'status' => 1,
                            'content' => sprintf('中级职称查房【%d次/7d，%s】', count($zhong), implode(',', $zhong))
                        ];
                    }
                }

                $currentTime = $periodEnd;
            }
        }

        return $content;
    }

    private function processSurgeryRecords($bl01esService, $mzjlesService, $ZYH, $surgeryName, $dischargeTime)
    {
        $content = [];

        // 查询手术记录
        $must = [
            ["term" => ["JZHM" => $ZYH]],
            ["match_phrase" => ["HJNR" => "手术记录"]],
            ["match_phrase" => ["HJNR" => $surgeryName]]
        ];

        $params = $bl01esService->clearMust()->queryByMustBatch($must)->source(['BLBH', 'operation_time', 'operation_handler', 'operation_handler_code'])->paginate(1, 1000)->getParams();
        $result = app('es')->search($params);
        $surgeryRecord = $bl01esService->getDataByEs($result);

        if (empty($surgeryRecord[0])) {
            $content[] = [
                'status' => 0,
                'content' => "手术【{$surgeryName}】记录缺失"
            ];
            return $content;
        }

        $surgery = $surgeryRecord[0][0];

        // 查询麻醉记录
        $must = [
            ["term" => ["HOSPIZATIONID" => $ZYH]],
            ["term" => ["PREOPERATIONNAME" => $surgeryName]]
        ];

        $params = $mzjlesService->clearMust()->queryByMustBatch($must)->source(['OPERATESTARTTIME', 'OPERATEENDTIME'])->paginate(1, 1000)->getParams();
        $result = app('es')->search($params);
        $anesthesiaRecord = $mzjlesService->getDataByEs($result);

        if (empty($anesthesiaRecord[0])) {
            $content[] = [
                'status' => 0,
                'content' => "手术【{$surgeryName}】麻醉记录缺失"
            ];
            return $content;
        }

        $content[] = [
            'status' => 1,
            'content' => sprintf(
                "手术记录完整：手术名称【%s】，手术时间【%s】，术者【%s】",
                $surgeryName,
                $surgery['operation_time'],
                $surgery['operation_handler']
            )
        ];

        return $content;
    }

    protected function getCyjlError($bl01Service, $yzbService, $ZYH, $BLLB, $YDYZLB, $key_field, $time)
    {
        $title = $BLLB == 288 ? '出院时间（死亡）' : '出院时间';
        $bllb = [1 => '出院记录', 18 => '24小时内记录类', 288 => '死亡记录'];
        $bagl = ZbBagl::getFirstById(6, true);

        // 分子 - 查询出院记录创建时间
        $must = [
            ["term" => ['JZHM' => $ZYH]],
            ["term" => ['BLLB' => $BLLB]]
        ];
        //var_dump($must);

        $notMust = ['term' => ["BLZT" => 9]];
        $params = $bl01Service->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNot($notMust)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);
        $cjsj = !empty($bl01Data[0][0][$key_field]) ? $bl01Data[0][0][$key_field] : '';

        // 分子 - 查询医嘱表 XZJDSJ
        $cysjWhere = [];
        if (stripos(',', $bagl->condition)) {
            $wheres = explode(',', $bagl->condition);
            foreach ($wheres as $val) {
                $where = explode('=', $val);
                $cysjWhere[] = ['term' => [$where[0] => $where[1]]];
            }
        } elseif (!empty($YDYZLB->condition)) {
            $where = explode('=', $bagl->condition);
            $cysjWhere[] = ['term' => [$where[0] => $where[1]]];
        }

        $must = [
            ["term" => [$bagl->MED_REC_ID => $ZYH]]
        ];
        $must = array_merge($must, $cysjWhere);


        /* $params = $yzbService->clearMust()
            ->queryByMustBatch($must)
            ->orderBy($bagl->table_field, 'asc')
            ->getParams();
        $restful = app('es')->search($params);
        $yzbData = $yzbService->getDataByEs($restful);
        $xzjdsj = !empty($yzbData[0][0][$bagl->table_field]) ? $yzbData[0][0][$bagl->table_field] : ''; */
        //修改为查数据表
        $xzjdsj = DB::table($bagl->table_name)
            ->where('MED_REC_ID', $ZYH)
            ->orderBy($bagl->table_field, 'asc')
            ->get()
            ->toArray();
        if ($xzjdsj) {
            $xzjdsj = $xzjdsj[0]->{$bagl->table_field};
        } else {
            $xzjdsj = '';
        }

        $numerator = 0;
        $errorContent = [];
        if ($xzjdsj) {
            // 添加出院时间信息
            $errorContent[] = [
                'status' => 1,
                'content' => $title . '【' . $xzjdsj . '】'
            ];

            if ($cjsj) {
                $errorContent[] = [
                    'status' => 1,
                    'content' => '【' . $bl01Data[0][0]['BLMC'] . '】'
                ];
                $xzjdsjEnd = date('Y-m-d H:i:s', strtotime($xzjdsj) + (3600 * $time));
                if ($cjsj < $xzjdsjEnd) {
                    $numerator = 1;
                    $errorContent[] = [
                        'status' => 1,
                        'content' => '首次签名时间【' . $cjsj . '（24小时内）】'
                    ];
                } elseif ($cjsj > $xzjdsjEnd) {
                    $errorContent[] = [
                        'status' => 0,
                        'content' => '首次签名时间【' . $cjsj . '（超24小时）】'
                    ];
                }
            } else {
                $errorContent[] = [
                    'status' => 0,
                    'content' => '首次签名时间【无】'
                ];
            }
        } else {
            $errorContent[] = [
                'status' => 0,
                'content' => $title . '【无】'
            ];
            if ($cjsj) {
                $errorContent[] = [
                    'status' => 0,
                    'content' => '首次签名时间【' . $cjsj . '】'
                ];
            } else {
                $errorContent[] = [
                    'status' => 0,
                    'content' => '首次签名时间【无】'
                ];
            }
        }

        return [
            'numerator' => $numerator,
            'cyjl_error' => $errorContent,
            'cjsj' => $cjsj
        ];
    }

    protected function yzb($yzbService, $ZYH)
    {
        $must = [
            ["term" => ['ZYH' => $ZYH]],
            ["match_phrase" => ['YZMC' => '抢救']]
        ];
        $params = $yzbService->clearMust()->queryByMustBatch($must)->getParams();
        $restful = app('es')->search($params);
        $yzbData = $yzbService->getDataByEs($restful);
        $yzbList = [];
        if (!empty($yzbData[0])) {
            foreach ($yzbData[0] as $value) {
                $yzbList[] = [
                    'YZMC' => $value['YZMC'],
                    'KZSJ' => $value['KZSJ'],
                ];
            }
        }

        return $yzbList;
    }

    protected function feeDetailed($feeService, $ZYH)
    {
        $must = [
            ["term" => ['MED_REC_ID' => $ZYH]],
            ["match_phrase" => ['FYMC' => '抢救']]
        ];
        $params = $feeService->clearMust()->queryByMustBatch($must)->getParams();
        $restful = app('es')->search($params);
        $feeData = $feeService->getDataByEs($restful);
        $feeList = [];
        if (!empty($feeData[0])) {
            foreach ($feeData[0] as $value) {
                $feeList[] = [
                    'FYMC' => $value['FYMC'],
                    'JFRQ' => $value['JFRQ'],
                ];
            }
        }

        return $feeList;
    }

    protected function bcjl($bl01Service, $ZYH)
    {
        $must = [
            ["term" => ['JZHM' => $ZYH]],
            ['match_phrase' => ['HJNR' => '抢救']]
        ];
        $notMust = [
            ['match_phrase' => ['HJNR' => '死亡']]
        ];
        $params = $bl01Service->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNotBatch($notMust)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);
        $bl01List = [];
        if (!empty($bl01Data[0])) {
            foreach ($bl01Data[0] as $value) {
                $bl01List[] = $value; //$value['ZXSJ'];
            }
        }

        return $bl01List;
    }

    /**
     * 细菌培养检查记录符合率
     */
    public function xjpyjcjl($zyh, $start, $end)
    {
        $page = 1;

        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母
        $vjyService = new ElasticsearchService('v_jmgs_ymresult_2023');
        $bl01Service = new ElasticsearchService('bl01_202303');
        $yzbService = new ElasticsearchService('yzb_2023');

        while (true) {
            $data = DB::table($cyzs->table_name)
                ->leftJoin('patient_doctor_info', $cyzs->table_name . '.' . $cyzs->MED_REC_ID, '=', 'patient_doctor_info.AAA28')
                ->when($cyzs->condition, function ($query) use ($cyzs) {
                    return $query->whereRaw($cyzs->condition);
                })
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('ZYH', $zyh);
                    }
                    return $query;
                })
                ->whereBetween('AAC01', [$start, $end])
                ->paginate(500, [$cyzs->table_name . '.' . $cyzs->MED_REC_ID, 'AAB01', 'AAC01', 'AAC11N', 'AEE03'], 'page', $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                $errorContent = [];
                $insert = [
                    'zyh' => $ZYH,
                    'xjpyjcjl_fz' => 0,
                    'xjpyjcjl_fm' => 0,
                    'xjpyjcjl_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                //删除旧数据
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['xjpyjcjl_fm' => null, 'xjpyjcjl_fz' => null, 'xjpyjcjl_error' => null]);

                // 查询分母 - 检验报告单
                $ymresultList = $this->getYmresultList($vjyService, $ZYH);
                if (empty($ymresultList)) {
                    continue;
                }

                $insert['xjpyjcjl_fm'] = 1;

                // 分子处理
                $allMatch = true;
                foreach ($ymresultList as $key => $ymValue) {
                    $CJSJ = $ymValue['CJSJ'];
                    $EXAMINAIM = $ymValue['EXAMINAIM'];
                    $EXAMINAIM_CJSJ = $ymValue['CJSJ'];

                    $reportGroup = [
                        'status' => 0,
                        'content' => []
                    ];

                    // 分子 - 医嘱查询
                    $yzmc = $this->getYzb($yzbService, $ZYH, $EXAMINAIM);
                    if ($yzmc) {
                        $reportGroup['content'][] = [
                            'status' => 1,
                            'content' => '医嘱【' . $yzmc . '】'
                        ];
                    } else {
                        $reportGroup['content'][] = [
                            'status' => 0,
                            'content' => '医嘱【无】'
                        ];
                    }

                    // 分子 - 报告单
                    $reportGroup['content'][] = [
                        'status' => 1,
                        'content' => '检验报告单【' . $EXAMINAIM . ' ' . $CJSJ . '】'
                    ];

                    // 分子 - 细菌培养报告
                    $xjmcList = $this->getXjmcList($vjyService, $ZYH, $EXAMINAIM);

                    // 分子 - 病程
                    $xjmcArr = [];
                    $xjmcCount = 0;
                    if ($xjmcList) {
                        foreach ($xjmcList as $xjmcVal) {
                            $range = ['range' => ['ZXSJ' => ['gte' => $EXAMINAIM_CJSJ]]];
                            $bcjlZXSJ = $this->getBl01Value($bl01Service, $ZYH, $xjmcVal, $range);
                            if ($bcjlZXSJ) {
                                $xjmcCount++;
                                $xjmcArr[] = [
                                    'status' => 1,
                                    'content' => $xjmcVal . ' ' . $bcjlZXSJ . ' > 检验时间'
                                ];
                            }
                        }
                    }

                    // 分子 - 培养
                    if (empty($xjmcArr)) {
                        $range = ['range' => ['ZXSJ' => ['gte' => $EXAMINAIM_CJSJ]]];
                        $bcjlZXSJ = $this->getBl01Value($bl01Service, $ZYH, '培养', $range);
                        if ($bcjlZXSJ) {
                            $xjmcCount++;
                            $xjmcArr[] = [
                                'status' => 1,
                                'content' => '培养 ' . $bcjlZXSJ . ' > 检验时间'
                            ];
                        } else {
                            $range = ['range' => ['ZXSJ' => ['lte' => $EXAMINAIM_CJSJ]]];
                            $bcjlZXSJ = $this->getBl01Value($bl01Service, $ZYH, '培养', $range);
                            if ($bcjlZXSJ) {
                                $xjmcArr[] = [
                                    'status' => 0,
                                    'content' => '培养 ' . $bcjlZXSJ . ' < 检验时间'
                                ];
                            }
                        }
                    }

                    if (empty($xjmcArr)) {
                        $xjmcArr[] = [
                            'status' => 0,
                            'content' => '采集时间之后【无】'
                        ];
                    }

                    $reportGroup['content'][] = [
                        'status' => 1,
                        'content' => '病程记录:'
                    ];
                    foreach ($xjmcArr as $xjmcItem) {
                        $reportGroup['content'][] = $xjmcItem;
                    }

                    if ($yzmc && $xjmcCount > 0) {
                        $reportGroup['status'] = 1;
                    } else {
                        $allMatch = false;
                    }

                    $errorContent[] = $reportGroup;
                }

                // 判断分子
                if ($allMatch && $insert['xjpyjcjl_fm'] > 0) {
                    $insert['xjpyjcjl_fz'] = 1;
                }

                // 保存结果
                if ($insert['xjpyjcjl_fm'] > 0) {
                    $insert['xjpyjcjl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                    Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                }

                //如果zyh不为空，根据zyh更新或插入es
                if (!empty($zyh)) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }
        }

        return true;
    }

    /**
     * 获取检验报告单列表
     */
    protected function getYmresultList($vjyService, $ZYH, $EXAMINAIM = '')
    {
        
        EsSaveService::vjmgsymresult($ZYH);
        $ymresultList = [];
        if ($EXAMINAIM) {
            $must = [
                ["term" => ['ZYH' => $ZYH]],
                ["term" => ['STAYHOSPITALMODE' => 2]]
            ];

            if(env('APP_NAME') == 'ningxia'){
                $must[] = ["match_phrase" => ['XJMC' => '菌']];
            }else{
                $must[] = ["term" => ['EXAMINAIM' => $EXAMINAIM]];
            }

            $params = $vjyService->clearMust()
                ->queryByMustBatch($must)
                ->paginate(1, 100)
                ->getParams();
            $restful = app('es')->search($params);
            $vjyData = $vjyService->getDataByEs($restful);
            if (!empty($vjyData[0])) {
                foreach ($vjyData[0] as $value) {
                    if ($value['PYJG'] && $value['XJMC'] && !in_array($value['XJMC'], $ymresultList)) {
                        $ymresultList[] = $value['XJMC'];
                    }
                }
            }
        } else {
            $must = [
                ["term" => ['ZYH' => $ZYH]],
                ["term" => ['STAYHOSPITALMODE' => 2]]
            ];
            $params = $vjyService->clearMust()
                ->queryByMustBatch($must)
                ->paginate(1, 100)
                ->getParams();
            $restful = app('es')->search($params);
            $vjyData = $vjyService->getDataByEs($restful);
            if (!empty($vjyData[0])) {
                $arr = [];
                foreach ($vjyData[0] as $value) {
                    if ($value['XJMC'] && $value['EXAMINAIM'] && !in_array($value['EXAMINAIM'], $arr)) {
                        $arr[] = $value['EXAMINAIM'];
                        $ymresultList[] = [
                            'XJMC' => $value['XJMC'],
                            'EXAMINAIM' => $value['EXAMINAIM'],
                            'CJSJ' => $value['CJSJ']
                        ];
                    }
                }
            }
        }

        return $ymresultList;
    }

    /**
     * 获取细菌名称列表
     */
    protected function getXjmcList($vjyService, $ZYH, $EXAMINAIM)
    {
        return $this->getYmresultList($vjyService, $ZYH, $EXAMINAIM);
    }

    /**
     * 查询医嘱
     */
    protected function getYzb($yzbService, $ZYH, $EXAMINAIM)
    {
        $EXAMINAIM_ARR = explode('+', $EXAMINAIM);
        foreach ($EXAMINAIM_ARR as $key => $value) {
            $EXAMINAIM_ARR[$key] = str_replace('加药敏', '', $value);
        }
        $EXAMINAIM_ARR[] = $EXAMINAIM;

        $must = ["term" => ['ZYH' => $ZYH]];
        $should = [];
        foreach ($EXAMINAIM_ARR as $value) {
            $should[] = ["match_phrase" => ['YZMC' => $value]];
        }

        $params = $yzbService->clearMust()
            ->queryByMust($must)
            ->queryByShouldBatch($should)
            ->minimumShouldMatch(1)
            ->getParams();
        $restful = app('es')->search($params);
        $yzbData = $yzbService->getDataByEs($restful);
        $yzmc = !empty($yzbData[0]) ? $yzbData[0][0]['YZMC'] : '';

        return $yzmc;
    }

    /**
     * 查询病程记录
     */
    protected function getBl01Value($bl01Service, $ZYH, $keyValue, $range = [])
    {
        $must = [
            ["term" => ['JZHM' => $ZYH]],
            ["term" => ['BLLB' => 294]],
            ["match_phrase" => ['HJNR' => $keyValue]]
        ];
        if ($range) {
            $must[] = $range;
        }
        $notMust = ['term' => ["BLZT" => 9]];

        $params = $bl01Service->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNot($notMust)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);
        $ZXSJ = !empty($bl01Data[0][0]['ZXSJ']) ? $bl01Data[0][0]['ZXSJ'] : '';

        return $ZXSJ;
    }

    /**
     * 植入物相关记录符合率
     */
    public function zrw($zyh, $start, $end)
    {
        $page = 1;

        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母
        $feeService = new ElasticsearchService('fee_detailed');
        $bl01Service = new ElasticsearchService('bl01_202303');

        // 获取所有植入物信息
        $implantsList = \App\Model\Implants::query()->pluck('name', 'manufactor')->toArray();

        while (true) {
            $data = DB::table($cyzs->table_name)
                ->leftJoin('patient_doctor_info', $cyzs->table_name . '.' . $cyzs->MED_REC_ID, '=', 'patient_doctor_info.AAA28')
                ->when($cyzs->condition, function ($query) use ($cyzs) {
                    return $query->whereRaw($cyzs->condition);
                })
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('ZYH', $zyh);
                    }
                    return $query;
                })
                ->whereBetween('AAC01', [$start, $end])
                ->paginate(500, [$cyzs->table_name . '.' . $cyzs->MED_REC_ID, 'AAB01', 'AAC01', 'AAC11N', 'AEE03'], 'page', $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                $errorContent = [];
                $insert = [
                    'zyh' => $ZYH,
                    'zrw_fz' => 0,
                    'zrw_fm' => 0,
                    'zrw_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                //删除旧数据
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['zrw_fm' => null, 'zrw_fz' => null, 'zrw_error' => null]);

                // 查询费用明细 - 合并原 feeDetailed 方法逻辑
                $must = [
                    ["term" => ['MED_REC_ID' => $ZYH]]
                ];
                $params = $feeService->clearMust()
                    ->queryByMustBatch($must)
                    ->paginate(1, 10000)
                    ->getParams();
                $restful = app('es')->search($params);
                $feeData = $feeService->getDataByEs($restful);

                $fymcList = [];
                $fyData = [];
                if (!empty($feeData[0])) {
                    foreach ($feeData[0] as $feeValue) {
                        if (!in_array($feeValue['FYMC'], $fymcList)) {
                            $fymcList[] = $feeValue['FYMC'];
                            $fyData[] = [
                                'FYMC' => $feeValue['FYMC'],
                                'FYSL' => $feeValue['FYSL']
                            ];
                        }
                    }
                }

                // 分母 - 匹配出包含植入名称的数据
                $fymcArr = [];
                $sfxmContent = [];
                foreach ($implantsList as $manufactor => $name) {
                    foreach ($fyData as $val) {
                        if ($name == '连接管') {
                            if ($val['FYMC'] == $name && !in_array($name, $fymcArr)) {
                                $fymcArr[] = $name;
                                $sfxmContent[] = [
                                    'status' => 1,
                                    'content' => '商品名称：' . $name . '，数量：' . $val['FYSL'] . '，厂家：' . $manufactor
                                ];
                            }
                        } elseif (stripos($val['FYMC'], $name) !== false && !in_array($name, $fymcArr)) {
                            $fymcArr[] = $name;
                            $sfxmContent[] = [
                                'status' => 1,
                                'content' => '商品名称：' . $name . '，数量：' . $val['FYSL'] . '，厂家：' . $manufactor
                            ];
                        }
                    }
                }

                if (empty($fymcArr)) {
                    continue;
                }

                $insert['zrw_fm'] = 1;

                $mainGroup = [
                    'status' => 0,
                    'content' => []
                ];

                // 添加收费项目信息
                if (!empty($sfxmContent)) {
                    $mainGroup['content'][] = [
                        'status' => 1,
                        'content' => '收费项目:'
                    ];
                    foreach ($sfxmContent as $sfxm) {
                        $mainGroup['content'][] = $sfxm;
                    }
                }

                // 分子 - 查询手术记录（BLLB=303）- 合并原 bl01Value 方法逻辑
                $must = [
                    ["term" => ['JZHM' => $ZYH]],
                    ["term" => ['BLLB' => 303]]
                ];
                //$notMust = ['term' => ["BLZT" => 9]];
                $params = $bl01Service->clearMust()
                    ->queryByMustBatch($must)
                    //->queryByMustNot($notMust)
                    ->paginate(1, 1000)
                    ->getParams();
                $restful = app('es')->search($params);
                $bl01Data = $bl01Service->getDataByEs($restful);

                $ssList = [];
                if (!empty($bl01Data[0])) {
                    foreach ($bl01Data[0] as $bl01Value) {
                        foreach ($fymcArr as $name) {
                            if (stripos($bl01Value['HJNR'], $name) !== false) {
                                $ssList[] = $bl01Value['BLMC'];
                            }
                        }
                    }
                }

                if (!empty($ssList)) {
                    $mainGroup['content'][] = [
                        'status' => 1,
                        'content' => '手术记录【' . implode('，', $ssList) . '】'
                    ];
                } else {
                    $mainGroup['content'][] = [
                        'status' => 0,
                        'content' => '手术记录【无】'
                    ];
                }

                // 分子 - 病程记录（BLLB=294）- 合并原 bl01Value 方法逻辑
                $must = [
                    ["term" => ['JZHM' => $ZYH]],
                    ["term" => ['BLLB' => 294]]
                ];
                $notMust = ['term' => ["BLZT" => 9]];
                $params = $bl01Service->clearMust()
                    ->queryByMustBatch($must)
                    ->queryByMustNot($notMust)
                    ->paginate(1, 1000)
                    ->getParams();
                $restful = app('es')->search($params);
                $bl01Data = $bl01Service->getDataByEs($restful);

                $bcList = [];
                if (!empty($bl01Data[0])) {
                    foreach ($bl01Data[0] as $bl01Value) {
                        foreach ($fymcArr as $name) {
                            if (stripos($bl01Value['HJNR'], $name) !== false) {
                                $bcList[] = $bl01Value['BLMC'];
                            }
                        }
                    }
                }

                if (!empty($bcList)) {
                    $mainGroup['content'][] = [
                        'status' => 1,
                        'content' => '病程记录【' . implode('，', $bcList) . '】'
                    ];
                } else {
                    $mainGroup['content'][] = [
                        'status' => 0,
                        'content' => '病程记录【无】'
                    ];
                }

                // 判断分子
                if ($ssList || $bcList) {
                    $insert['zrw_fz'] = 1;
                    $mainGroup['status'] = 1;
                }

                $errorContent[] = $mainGroup;

                // 保存结果
                if ($insert['zrw_fm'] > 0) {
                    $insert['zrw_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                    Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                }

                //如果zyh不为空，根据zyh更新或插入es
                if (!empty($zyh)) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }
        }

        return true;
    }

    /**
     * 出院患者病历2日归档率
     */
    public function cdl($zyh, $start, $end)
    {
        $page = 1;

        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母
        $yzbService = new ElasticsearchService('yzb_2023');
        $publicService = new PublicService();

        while (true) {
            $data = DB::table($cyzs->table_name)
                ->leftJoin('patient_doctor_info', $cyzs->table_name . '.' . $cyzs->MED_REC_ID, '=', 'patient_doctor_info.AAA28')
                ->when($cyzs->condition, function ($query) use ($cyzs) {
                    return $query->whereRaw($cyzs->condition);
                })
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('ZYH', $zyh);
                    }
                    return $query;
                })
                ->whereBetween('AAC01', [$start, $end])
                ->paginate(500, [$cyzs->table_name . '.' . $cyzs->MED_REC_ID, 'AAB01', 'AAC01', 'AAC11N', 'AEE03', 'AAA28'], 'page', $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                $AAA28 = $value->AAA28;
                $errorContent = [];
                $insert = [
                    'zyh' => $ZYH,
                    'cdl_fz' => 0,
                    'cdl_fm' => 1,
                    'cdl_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                //删除旧数据
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['cdl_fm' => null, 'cdl_fz' => null, 'cdl_error' => null]);

                $mainGroup = [
                    'status' => 0,
                    'content' => []
                ];

                // 查询归档时间
                $baReceive = BA_RECEIVE::query()
                    ->where('bah', '=', $AAA28)
                    ->whereNotNull('MaxCheckTime')
                    ->get()->toArray();

                $MaxCheckTime = '';
                if (!empty($baReceive)) {
                    foreach ($baReceive as $val) {
                        if ($val['cysj'] == $value->AAC01) {
                            $MaxCheckTime = $val['MaxCheckTime'];
                            break;
                        }
                    }
                }

                // 查询出院医嘱（YDYZLB=303）- 合并原 getYzbDataEs 方法逻辑
                $cyNumerator = 0;
                $cyIsData = 0;
                $cyError = '';

                $must = [
                    ['term' => ["ZYH" => $ZYH]],
                    ['term' => ["YDYZLB" => 303]]
                ];
                $params = $yzbService->clearMust()->queryByMustBatch($must)->getParams();
                $yzbRes = app('es')->search($params);
                $yzbData = $yzbService->getDataByEs($yzbRes);
                $cyXZJDSJ = !empty($yzbData[0]) ? $yzbData[0][0]['XZJDSJ'] : '';

                if ($cyXZJDSJ) {
                    $cyIsData = 1;
                    $mainGroup['content'][] = [
                        'status' => 1,
                        'content' => '出院时间【' . $cyXZJDSJ . '】'
                    ];

                    if ($MaxCheckTime) {
                        // 获取2日后时间（不包含节假日、休息日）
                        $XZJDSJ_END = $publicService->getDay($cyXZJDSJ, 2);
                        if ($XZJDSJ_END > $MaxCheckTime) {
                            $cyNumerator = 1;
                            $mainGroup['content'][] = [
                                'status' => 1,
                                'content' => '归档时间【' . $MaxCheckTime . '，2个工作日内】'
                            ];
                            $cyError = '出院时间【' . $cyXZJDSJ . '】 归档时间【' . $MaxCheckTime . '，2个工作日内】';
                        } else {
                            $mainGroup['content'][] = [
                                'status' => 0,
                                'content' => '归档时间【' . $MaxCheckTime . '，超过2个工作日】'
                            ];
                            $cyError = '出院时间【' . $cyXZJDSJ . '】 归档时间【' . $MaxCheckTime . '，超过2个工作日】';
                        }
                    } else {
                        $mainGroup['content'][] = [
                            'status' => 0,
                            'content' => '归档时间【无】'
                        ];
                        $cyError = '出院时间【' . $cyXZJDSJ . '】 归档时间【无】';
                    }
                } else {
                    $MaxCheckTimeDisplay = !empty($MaxCheckTime) ? $MaxCheckTime : '无';
                    $cyError = '出院时间【无】 归档时间【' . $MaxCheckTimeDisplay . '】';
                }

                // 查询死亡医嘱（YDYZLB=305）- 合并原 getYzbDataEs 方法逻辑
                $swNumerator = 0;
                $swIsData = 0;
                $swError = '';

                $must = [
                    ['term' => ["ZYH" => $ZYH]],
                    ['term' => ["YDYZLB" => 305]]
                ];
                $params = $yzbService->clearMust()->queryByMustBatch($must)->getParams();
                $yzbRes = app('es')->search($params);
                $yzbData = $yzbService->getDataByEs($yzbRes);
                $swXZJDSJ = !empty($yzbData[0]) ? $yzbData[0][0]['XZJDSJ'] : '';

                if ($swXZJDSJ) {
                    $swIsData = 1;
                    $mainGroup['content'][] = [
                        'status' => 1,
                        'content' => '死亡时间【' . $swXZJDSJ . '】'
                    ];

                    if ($MaxCheckTime) {
                        // 获取2日后时间（不包含节假日、休息日）
                        $XZJDSJ_END = $publicService->getDay($swXZJDSJ, 2);
                        if ($XZJDSJ_END > $MaxCheckTime) {
                            $swNumerator = 1;
                            $mainGroup['content'][] = [
                                'status' => 1,
                                'content' => '归档时间【' . $MaxCheckTime . '，2个工作日内】'
                            ];
                            $swError = '死亡时间【' . $swXZJDSJ . '】 归档时间【' . $MaxCheckTime . '，2个工作日内】';
                        } else {
                            $mainGroup['content'][] = [
                                'status' => 0,
                                'content' => '归档时间【' . $MaxCheckTime . '，超过2个工作日】'
                            ];
                            $swError = '死亡时间【' . $swXZJDSJ . '】 归档时间【' . $MaxCheckTime . '，超过2个工作日】';
                        }
                    } else {
                        $mainGroup['content'][] = [
                            'status' => 0,
                            'content' => '归档时间【无】'
                        ];
                        $swError = '死亡时间【' . $swXZJDSJ . '】 归档时间【无】';
                    }
                } else {
                    $MaxCheckTimeDisplay = !empty($MaxCheckTime) ? $MaxCheckTime : '无';
                    $swError = '死亡时间【无】 归档时间【' . $MaxCheckTimeDisplay . '】';
                }

                // 判断分子：优先使用出院医嘱，其次使用死亡医嘱
                $finalError = '';
                if ($cyNumerator) {
                    $insert['cdl_fz'] = 1;
                    $mainGroup['status'] = 1;
                    $finalError = $cyError;
                } elseif ($swNumerator) {
                    $insert['cdl_fz'] = 1;
                    $mainGroup['status'] = 1;
                    $finalError = $swError;
                } else {
                    // 没有符合条件的，选择有数据的错误信息
                    if ($cyIsData == 1) {
                        $finalError = $cyError;
                    } elseif ($swIsData == 1) {
                        $finalError = $swError;
                    } else {
                        $finalError = $cyError;
                    }
                }

                // 如果没有归档时间，分子为0
                if (!$MaxCheckTime) {
                    $insert['cdl_fz'] = 0;
                    $mainGroup['status'] = 0;
                }

                $errorContent[] = $mainGroup;

                // 保存结果
                $insert['cdl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);

                //如果zyh不为空，根据zyh更新或插入es
                if (!empty($zyh)) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }
        }

        return true;
    }

    /**
     * 出院患者病历归档完整率
     */
    public function gdwzl($zyh, $start, $end)
    {
        $page = 1;

        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母
        $bl01NewService = new ElasticsearchService('emr_bl_bl01_new'); //v2替代
        $bl01Service = new ElasticsearchService('bl01_202303');
        $mzjlService = new ElasticsearchService('mzjl_2023');
        $pacsService = new ElasticsearchService('pacs');
        $ymresultService = new ElasticsearchService('v_jmgs_ymresult_2023');
        $yzbService = new ElasticsearchService('yzb_2023');
        $bmcnService = new ElasticsearchService('ba_mr_class_number_2023');

        while (true) {
            $data = DB::table($cyzs->table_name)
                ->leftJoin('patient_doctor_info', $cyzs->table_name . '.' . $cyzs->MED_REC_ID, '=', 'patient_doctor_info.AAA28')
                ->when($cyzs->condition, function ($query) use ($cyzs) {
                    return $query->whereRaw($cyzs->condition);
                })
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('ZYH', $zyh);
                    }
                    return $query;
                })
                ->whereBetween('AAC01', [$start, $end])
                ->paginate(500, [$cyzs->table_name . '.' . $cyzs->MED_REC_ID, 'AAB01', 'AAC01', 'AAC11N', 'AEE03', $cyzs->table_name . '.AAA28', $cyzs->table_name . '.AAA29'], 'page', $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                $AAA28 = $value->AAA28;
                $AAA29 = $value->AAA29;
                $AAB01 = $value->AAB01;

                $errorContent = [];
                $insert = [
                    'zyh' => $ZYH,
                    'gdwzl_fz' => 1,
                    'gdwzl_fm' => 0,
                    'gdwzl_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                //删除旧数据
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['gdwzl_fm' => null, 'gdwzl_fz' => null, 'gdwzl_error' => null]);

                $hasData = false;

                // 1. 病案首页
                $must = ['term' => ["JZHM" => $ZYH]];
                $params = $bl01NewService->clearMust()->queryByMust($must)->getParams();
                $restful = app('es')->search($params);
                $bl01NewData = $bl01NewService->getDataByEs($restful);

                if (!empty($bl01NewData[0])) {
                    $hasData = true;
                    $must = [
                        ['term' => ["patient_id" => $AAA28]],
                        ['term' => ["visit_id" => $AAA29]],
                        ['match_phrase' => ["MrClass" => '首页']]
                    ];
                    $params = $bmcnService->clearMust()->queryByMustBatch($must)->getParams();
                    $restful = app('es')->search($params);
                    $bmcnData = $bmcnService->getDataByEs($restful);

                    if (!empty($bmcnData[0])) {
                        $errorContent[] = [
                            'status' => 1,
                            'content' => '病案首页【有，有】'
                        ];
                    } else {
                        $insert['gdwzl_fz'] = 0;
                        $errorContent[] = [
                            'status' => 0,
                            'content' => '病案首页【有，无】'
                        ];
                    }
                }

                // 2. 出院记录（或 24小时出入院记录 或 死亡记录）
                $must = [['term' => ["JZHM" => $ZYH]]];
                $should = [['term' => ["BLLB" => 1]], ['term' => ["BLLB" => 288]]];
                $params = $bl01Service->clearMust()
                    ->queryByMustBatch($must)
                    ->queryByShouldBatch($should)
                    ->minimumShouldMatch()
                    ->getParams();
                $restful = app('es')->search($params);
                $cyjl = $bl01Service->getDataByEs($restful);
                $cyjl = !empty($cyjl[0]) ? $cyjl[0] : [];

                if (empty($cyjl)) {
                    $must = [
                        ['term' => ["JZHM" => $ZYH]],
                        ['term' => ["BLLB" => 18]],
                        ['match_phrase' => ["HJNR" => '出院记录']]
                    ];
                    $params = $bl01Service->clearMust()->queryByMustBatch($must)->getParams();
                    $restful = app('es')->search($params);
                    $cyjl = $bl01Service->getDataByEs($restful);
                    $cyjl = !empty($cyjl[0]) ? $cyjl[0] : [];
                }

                if (!empty($cyjl)) {
                    $hasData = true;
                    $must = [
                        ['term' => ["patient_id" => $AAA28]],
                        ['term' => ["visit_id" => $AAA29]],
                        ['match_phrase' => ["MrClass" => '出院记录']]
                    ];
                    $params = $bmcnService->clearMust()->queryByMustBatch($must)->getParams();
                    $restful = app('es')->search($params);
                    $bmcnData = $bmcnService->getDataByEs($restful);

                    if (!empty($bmcnData[0])) {
                        $errorContent[] = [
                            'status' => 1,
                            'content' => '出院记录【有，有】'
                        ];
                    } else {
                        $insert['gdwzl_fz'] = 0;
                        $errorContent[] = [
                            'status' => 0,
                            'content' => '出院记录【有，无】'
                        ];
                    }
                }

                // 3. 入院记录（或 24小时出入院记录）
                $must = [['term' => ["JZHM" => $ZYH]], ['term' => ["BLLB" => 292]]];
                $params = $bl01Service->clearMust()->queryByMustBatch($must)->getParams();
                $restful = app('es')->search($params);
                $ryjl = $bl01Service->getDataByEs($restful);
                $ryjl = !empty($ryjl[0]) ? $ryjl[0] : [];

                if (empty($ryjl)) {
                    $must = [
                        ['term' => ["JZHM" => $ZYH]],
                        ['term' => ["BLLB" => 18]],
                        ['match_phrase' => ["HJNR" => '入院记录']]
                    ];
                    $params = $bl01Service->clearMust()->queryByMustBatch($must)->getParams();
                    $restful = app('es')->search($params);
                    $ryjl = $bl01Service->getDataByEs($restful);
                    $ryjl = !empty($ryjl[0]) ? $ryjl[0] : [];
                }

                if (!empty($ryjl)) {
                    $hasData = true;
                    $must = [
                        ['term' => ["patient_id" => $AAA28]],
                        ['term' => ["visit_id" => $AAA29]],
                        ['match_phrase' => ["MrClass" => '入院记录']]
                    ];
                    $params = $bmcnService->clearMust()->queryByMustBatch($must)->getParams();
                    $restful = app('es')->search($params);
                    $bmcnData = $bmcnService->getDataByEs($restful);

                    if (!empty($bmcnData[0])) {
                        $errorContent[] = [
                            'status' => 1,
                            'content' => '入院记录【有，有】'
                        ];
                    } else {
                        $insert['gdwzl_fz'] = 0;
                        $errorContent[] = [
                            'status' => 0,
                            'content' => '入院记录【有，无】'
                        ];
                    }
                }

                // 4. 病程记录
                $must = [['term' => ["JZHM" => $ZYH]], ['term' => ["BLLB" => 294]]];
                $params = $bl01Service->clearMust()->queryByMustBatch($must)->getParams();
                $restful = app('es')->search($params);
                $bcjl = $bl01Service->getDataByEs($restful);
                $bcjl = !empty($bcjl[0]) ? $bcjl[0] : [];

                if (!empty($bcjl)) {
                    $hasData = true;
                    $must = [
                        ['term' => ["patient_id" => $AAA28]],
                        ['term' => ["visit_id" => $AAA29]],
                        ['match_phrase' => ["MrClass" => '病程']]
                    ];
                    $params = $bmcnService->clearMust()->queryByMustBatch($must)->getParams();
                    $restful = app('es')->search($params);
                    $bmcnData = $bmcnService->getDataByEs($restful);

                    if (!empty($bmcnData[0])) {
                        $errorContent[] = [
                            'status' => 1,
                            'content' => '病程记录【有，有】'
                        ];
                    } else {
                        $insert['gdwzl_fz'] = 0;
                        $errorContent[] = [
                            'status' => 0,
                            'content' => '病程记录【有，无】'
                        ];
                    }
                }

                // 5. 手术记录
                $must = [['term' => ["JZHM" => $ZYH]], ['term' => ["BLLB" => 303]]];
                $should = [['term' => ['MBLB' => 306]], ['term' => ['MBLB' => 74]]];
                $params = $bl01Service->clearMust()
                    ->queryByMustBatch($must)
                    ->queryByShouldBatch($should)
                    ->minimumShouldMatch()
                    ->getParams();
                $restful = app('es')->search($params);
                $ssjl = $bl01Service->getDataByEs($restful);
                $ssjl = !empty($ssjl[0]) ? $ssjl[0] : [];

                if (!empty($ssjl)) {
                    $hasData = true;
                    $must = [
                        ['term' => ["patient_id" => $AAA28]],
                        ['term' => ["visit_id" => $AAA29]],
                        ['match_phrase' => ["MrClass" => '手术记录']]
                    ];
                    $params = $bmcnService->clearMust()->queryByMustBatch($must)->getParams();
                    $restful = app('es')->search($params);
                    $bmcnData = $bmcnService->getDataByEs($restful);

                    if (!empty($bmcnData[0])) {
                        $errorContent[] = [
                            'status' => 1,
                            'content' => '手术记录【有，有】'
                        ];
                    } else {
                        $insert['gdwzl_fz'] = 0;
                        $errorContent[] = [
                            'status' => 0,
                            'content' => '手术记录【有，无】'
                        ];
                    }
                }

                // 6. 手术麻醉相关记录（麻醉记录单）
                $must = [["term" => ['HOSPIZATIONID' => $ZYH]]];
                $params = $mzjlService->clearMust()->queryByMustBatch($must)->getParams();
                $restful = app('es')->search($params);
                $mzjl = $mzjlService->getDataByEs($restful);

                if (!empty($mzjl[0])) {
                    $hasData = true;
                    $must = [
                        ['term' => ["patient_id" => $AAA28]],
                        ['term' => ["visit_id" => $AAA29]],
                        ['match_phrase' => ["MrClass" => '手术麻醉相关记录']]
                    ];
                    $params = $bmcnService->clearMust()->queryByMustBatch($must)->getParams();
                    $restful = app('es')->search($params);
                    $bmcnData = $bmcnService->getDataByEs($restful);

                    if (!empty($bmcnData[0])) {
                        $errorContent[] = [
                            'status' => 1,
                            'content' => '手术麻醉相关记录【有，有】'
                        ];
                    } else {
                        $insert['gdwzl_fz'] = 0;
                        $errorContent[] = [
                            'status' => 0,
                            'content' => '手术麻醉相关记录【有，无】'
                        ];
                    }
                }

                // 7. 知情同意书
                $must = [['term' => ["JZHM" => $ZYH]], ['term' => ["BLLB" => 329]]];
                $params = $bl01Service->clearMust()->queryByMustBatch($must)->getParams();
                $restful = app('es')->search($params);
                $zqtys = $bl01Service->getDataByEs($restful);
                $zqtys = !empty($zqtys[0]) ? $zqtys[0] : [];

                if (!empty($zqtys)) {
                    $hasData = true;
                    $must = [
                        ['term' => ["patient_id" => $AAA28]],
                        ['term' => ["visit_id" => $AAA29]],
                        ['match_phrase' => ["MrClass" => '知情同意书']]
                    ];
                    $params = $bmcnService->clearMust()->queryByMustBatch($must)->getParams();
                    $restful = app('es')->search($params);
                    $bmcnData = $bmcnService->getDataByEs($restful);

                    if (!empty($bmcnData[0])) {
                        $errorContent[] = [
                            'status' => 1,
                            'content' => '知情同意书【有，有】'
                        ];
                    } else {
                        $insert['gdwzl_fz'] = 0;
                        $errorContent[] = [
                            'status' => 0,
                            'content' => '知情同意书【有，无】'
                        ];
                    }
                }

                // 8. 病理辅助检查报告单（病历图文报告：ExamType=7）
                if ($AAB01 && $value->AAC01) {
                    $must = [
                        ['term' => ['JZLSH' => $AAA28]],
                        ['range' => ['JYSJ' => ['gte' => $AAB01, 'lte' => $value->AAC01]]],
                        ['term' => ['ExamType' => '07']]
                    ];
                    $params = $pacsService->clearMust()->queryByMustBatch($must)->getParams();
                    $restful = app('es')->search($params);
                    $blfzjc = $pacsService->getDataByEs($restful);
                    $blfzjc = !empty($blfzjc[0]) ? $blfzjc[0] : [];

                    if (!empty($blfzjc)) {
                        $hasData = true;
                        $must = [
                            ['term' => ["patient_id" => $AAA28]],
                            ['term' => ["visit_id" => $AAA29]],
                            ['match_phrase' => ["MrClass" => '病理辅助检查报告单']]
                        ];
                        $params = $bmcnService->clearMust()->queryByMustBatch($must)->getParams();
                        $restful = app('es')->search($params);
                        $bmcnData = $bmcnService->getDataByEs($restful);

                        if (!empty($bmcnData[0])) {
                            $errorContent[] = [
                                'status' => 1,
                                'content' => '病理辅助检查报告单【有，有】'
                            ];
                        } else {
                            $insert['gdwzl_fz'] = 0;
                            $errorContent[] = [
                                'status' => 0,
                                'content' => '病理辅助检查报告单【有，无】'
                            ];
                        }
                    }

                    // 9. 影像辅助检查报告单（影像诊断报告：ExamType=1 或 2）
                    $must = [
                        ['term' => ['JZLSH' => $AAA28]],
                        ['range' => ['KDSJ' => ['gte' => $AAB01, 'lte' => $value->AAC01]]]
                    ];
                    $should = [['term' => ['ExamType' => '01']], ['term' => ['ExamType' => '02']]];
                    $params = $pacsService->clearMust()
                        ->queryByMustBatch($must)
                        ->queryByShouldBatch($should)
                        ->minimumShouldMatch()
                        ->getParams();
                    $restful = app('es')->search($params);
                    $yxfzjc = $pacsService->getDataByEs($restful);
                    $yxfzjc = !empty($yxfzjc[0]) ? $yxfzjc[0] : [];

                    if (!empty($yxfzjc)) {
                        $hasData = true;
                        $must = [
                            ['term' => ["patient_id" => $AAA28]],
                            ['term' => ["visit_id" => $AAA29]],
                            ['match_phrase' => ["MrClass" => '影像辅助检查报告单']]
                        ];
                        $params = $bmcnService->clearMust()->queryByMustBatch($must)->getParams();
                        $restful = app('es')->search($params);
                        $bmcnData = $bmcnService->getDataByEs($restful);

                        if (!empty($bmcnData[0])) {
                            $errorContent[] = [
                                'status' => 1,
                                'content' => '影像辅助检查报告单【有，有】'
                            ];
                        } else {
                            $insert['gdwzl_fz'] = 0;
                            $errorContent[] = [
                                'status' => 0,
                                'content' => '影像辅助检查报告单【有，无】'
                            ];
                        }
                    }
                }

                // 10. 检验（检验报告单）
                $must = [['term' => ['ZYH' => $ZYH]], ['term' => ['STAYHOSPITALMODE' => 2]]];
                $params = $ymresultService->clearMust()->queryByMustBatch($must)->getParams();
                $restful = app('es')->search($params);
                $jybgd = $ymresultService->getDataByEs($restful);

                if (!empty($jybgd[0])) {
                    $hasData = true;
                    $must = [
                        ['term' => ["patient_id" => $AAA28]],
                        ['term' => ["visit_id" => $AAA29]],
                        ['match_phrase' => ["MrClass" => '检验']]
                    ];
                    $params = $bmcnService->clearMust()->queryByMustBatch($must)->getParams();
                    $restful = app('es')->search($params);
                    $bmcnData = $bmcnService->getDataByEs($restful);

                    if (!empty($bmcnData[0])) {
                        $errorContent[] = [
                            'status' => 1,
                            'content' => '检验报告单【有，有】'
                        ];
                    } else {
                        $insert['gdwzl_fz'] = 0;
                        $errorContent[] = [
                            'status' => 0,
                            'content' => '检验报告单【有，无】'
                        ];
                    }
                }

                // 11. 医嘱
                $must = [['term' => ['ZYH' => $ZYH]]];
                $params = $yzbService->clearMust()->queryByMustBatch($must)->getParams();
                $restful = app('es')->search($params);
                $yz = $yzbService->getDataByEs($restful);

                if (!empty($yz[0])) {
                    $hasData = true;
                    $must = [
                        ['term' => ["patient_id" => $AAA28]],
                        ['term' => ["visit_id" => $AAA29]],
                        ['match_phrase' => ["MrClass" => '医嘱']]
                    ];
                    $params = $bmcnService->clearMust()->queryByMustBatch($must)->getParams();
                    $restful = app('es')->search($params);
                    $bmcnData = $bmcnService->getDataByEs($restful);

                    if (!empty($bmcnData[0])) {
                        $errorContent[] = [
                            'status' => 1,
                            'content' => '医嘱【有，有】'
                        ];
                    } else {
                        $insert['gdwzl_fz'] = 0;
                        $errorContent[] = [
                            'status' => 0,
                            'content' => '医嘱【有，无】'
                        ];
                    }
                }

                // 保存结果 - 只有当有数据时才保存
                if ($hasData) {
                    $insert['gdwzl_fm'] = 1;
                    $insert['gdwzl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                    Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);

                    //如果zyh不为空，根据zyh更新或插入es
                    if (!empty($zyh)) {
                        $esService = new ElasticsearchService('indicator');
                        $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                    }
                }
            }
        }

        return true;
    }

    /**
     * 主要诊断编码正确率
     */
    public function zyzdbmzql($zyh, $start, $end)
    {
        $page = 1;

        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母

        while (true) {
            $data = DB::table($cyzs->table_name)
                ->leftJoin('patient_doctor_info', $cyzs->table_name . '.' . $cyzs->MED_REC_ID, '=', 'patient_doctor_info.AAA28')
                ->when($cyzs->condition, function ($query) use ($cyzs) {
                    return $query->whereRaw($cyzs->condition);
                })
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('ZYH', $zyh);
                    }
                    return $query;
                })
                ->whereBetween('AAC01', [$start, $end])
                ->paginate(500, [$cyzs->table_name . '.' . $cyzs->MED_REC_ID, 'AAB01', 'AAC01', 'AAC11N', 'AEE03'], 'page', $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                $errorContent = [];
                $insert = [
                    'zyh' => $ZYH,
                    'zyzdbmzql_fz' => 1,
                    'zyzdbmzql_fm' => 1,
                    'zyzdbmzql_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                //删除旧数据
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['zyzdbmzql_fm' => null, 'zyzdbmzql_fz' => null, 'zyzdbmzql_error' => null]);

                $mainGroup = [
                    'status' => 1,
                    'content' => []
                ];

                // 用住院号查询home_quality表
                $homeQualityData = HomeQuality::query()->where('zyh', $ZYH)->get();

                if ($homeQualityData->isEmpty()) {
                    // 如果数据为空，分子为1
                    $insert['zyzdbmzql_fz'] = 1;
                    $mainGroup['content'][] = [
                        'status' => 1,
                        'content' => '质控后主要诊断编码【正确】'
                    ];
                } else {
                    // 如果数据不为空，用error_rule字段关联查询error_rule表
                    $hasMainDiagnosisError = false;
                    foreach ($homeQualityData as $quality) {
                        if (!empty($quality->error_rule)) {
                            $errorRuleIds = json_decode($quality->error_rule, true);
                            if (is_array($errorRuleIds)) {
                                // 查询error_rule表，判断field字段是否有"主要诊断编码"
                                $errorRules = ErrorRule::query()->whereIn('id', $errorRuleIds)
                                    ->pluck('field')
                                    ->toArray();

                                if (in_array('主要诊断编码', $errorRules)) {
                                    $hasMainDiagnosisError = true;
                                    break;
                                }
                            }
                        }
                    }

                    if ($hasMainDiagnosisError) {
                        // 如果有主要诊断编码错误，分子为0
                        $insert['zyzdbmzql_fz'] = 0;
                        $mainGroup['status'] = 0;
                        $mainGroup['content'][] = [
                            'status' => 0,
                            'content' => '质控后主要诊断编码【错误】'
                        ];
                    } else {
                        // 如果没有主要诊断编码错误，分子为1
                        $insert['zyzdbmzql_fz'] = 1;
                        $mainGroup['content'][] = [
                            'status' => 1,
                            'content' => '质控后主要诊断编码【正确】'
                        ];
                    }
                }

                $errorContent[] = $mainGroup;

                // 保存结果
                $insert['zyzdbmzql_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);

                //如果zyh不为空，根据zyh更新或插入es
                if (!empty($zyh)) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }
        }

        return true;
    }

    /**
     * 主要诊断填写正确率
     * //替换home_quality查字段名是否有主要诊断名称
     */
    public function zyzdzql($zyh, $start, $end)
    {
        $page = 1;

        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母

        while (true) {
            $data = DB::table($cyzs->table_name)
                ->leftJoin('patient_doctor_info', $cyzs->table_name . '.' . $cyzs->MED_REC_ID, '=', 'patient_doctor_info.AAA28')
                ->when($cyzs->condition, function ($query) use ($cyzs) {
                    return $query->whereRaw($cyzs->condition);
                })
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('ZYH', $zyh);
                    }
                    return $query;
                })
                ->whereBetween('AAC01', [$start, $end])
                ->paginate(500, [$cyzs->table_name . '.' . $cyzs->MED_REC_ID, 'AAB01', 'AAC01', 'AAC11N', 'AEE03', 'ABC01N'], 'page', $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                $ABC01N = $value->ABC01N ?? '';

                $errorContent = [];
                $insert = [
                    'zyh' => $ZYH,
                    'zyzdzql_fz' => 0,
                    'zyzdzql_fm' => 1,
                    'zyzdzql_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                //删除旧数据
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['zyzdzql_fm' => null, 'zyzdzql_fz' => null, 'zyzdzql_error' => null]);

                $mainGroup = [
                    'status' => 0,
                    'content' => []
                ];

                // 用住院号查询home_quality表
                $homeQualityData = HomeQuality::query()->where('zyh', $ZYH)->get();

                if ($homeQualityData->isEmpty()) {
                    // 如果数据为空，分子为1
                    $insert['zyzdzql_fz'] = 1;
                    $mainGroup['content'][] = [
                        'status' => 1,
                        'content' => '质控后主要诊断名称【正确】'
                    ];
                } else {
                    // 如果数据不为空，用error_rule字段关联查询error_rule表
                    $hasMainDiagnosisError = false;
                    foreach ($homeQualityData as $quality) {
                        if (!empty($quality->error_rule)) {
                            $errorRuleIds = json_decode($quality->error_rule, true);
                            if (is_array($errorRuleIds)) {
                                // 查询error_rule表，判断field字段是否有"主要诊断名称"
                                $errorRules = ErrorRule::query()->whereIn('id', $errorRuleIds)
                                    ->pluck('field')
                                    ->toArray();

                                if (in_array('主要诊断名称', $errorRules)) {
                                    $hasMainDiagnosisError = true;
                                    break;
                                }
                            }
                        }
                    }

                    if ($hasMainDiagnosisError) {
                        // 如果有主要诊断名称错误，分子为0
                        $insert['zyzdzql_fz'] = 0;
                        $mainGroup['status'] = 0;
                        $mainGroup['content'][] = [
                            'status' => 0,
                            'content' => '质控后主要诊断名称【错误】'
                        ];
                    } else {
                        // 如果没有主要诊断名称错误，分子为1
                        $insert['zyzdzql_fz'] = 1;
                        $mainGroup['content'][] = [
                            'status' => 1,
                            'content' => '质控后主要诊断名称【正确】'
                        ];
                    }
                }

                $errorContent[] = $mainGroup;

                // 保存结果
                $insert['zyzdzql_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);

                //如果zyh不为空，根据zyh更新或插入es
                if (!empty($zyh)) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }
        }

        return true;
    }

    /**
     * 主要手术编码正确率
     */
    public function zyssbmzql($zyh, $start, $end)
    {
        $page = 1;

        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母

        // 手术级别过滤（使用ruleMap8047/8048）
        $ruleMap8047 = RuleWordMap::query()->where('id', '=', 8047)->value('keyword');
        $excludeKeywords8047 = strpos($ruleMap8047, ',') !== false ? explode(',', $ruleMap8047) : [$ruleMap8047];
        $ruleMap8048 = RuleWordMap::query()->where('id', '=', 8048)->value('keyword');
        $excludeKeywords8048 = strpos($ruleMap8048, ',') !== false ? explode(',', $ruleMap8048) : [$ruleMap8048];

        while (true) {
            $data = DB::table($cyzs->table_name)
                ->leftJoin('patient_doctor_info', $cyzs->table_name . '.' . $cyzs->MED_REC_ID, '=', 'patient_doctor_info.AAA28')
                ->when($cyzs->condition, function ($query) use ($cyzs) {
                    return $query->whereRaw($cyzs->condition);
                })
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('ZYH', $zyh);
                    }
                    return $query;
                })
                ->whereBetween('AAC01', [$start, $end])
                ->paginate(500, [$cyzs->table_name . '.' . $cyzs->MED_REC_ID, 'AAB01', 'AAC01', 'AAC11N', 'AEE03'], 'page', $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);

                //查询是否有手术
                /* $ssdata = MainOperation::query()->where('ZYH', $ZYH)->first();
                if (!$ssdata) {
                    continue;
                } */

                $mainOperations = MainOperation::query()
                    ->where('AAA28', $ZYH)
                    ->get(['ICD9_NAME', 'OPE_MAN_NAME', 'OPE_MAN_CODE', 'OPE_DATE'])
                    ->toArray();

                $secondaryOperations = SecondaryOperation::query()
                    ->where('AAA28', $ZYH)
                    ->get(['ICD9_NAME', 'OPE_MAN_NAME', 'OPE_MAN_CODE', 'OPE_DATE'])
                    ->toArray();

                $allOperations = array_merge($mainOperations, $secondaryOperations);

                if (empty($allOperations)) {
                    continue; // 没有四级手术，跳过
                }

                // 用于记录符合分母条件的手术
                //$validOperations = [];
                $hasValidOperation = false;

                foreach ($allOperations as $operation) {
                    $surgeryName = $operation['ICD9_NAME'] ?? '';
                    $surgeonName = $operation['OPE_MAN_NAME'] ?? '';
                    $surgeonCode = $operation['OPE_MAN_CODE'] ?? '';
                    $surgeryDate = $operation['OPE_DATE'] ?? '';

                    if (empty($surgeryName)) {
                        continue;
                    }



                    // 使用8047/8048规则过滤手术
                    if (!empty($ruleMap8047)) {
                        $ssLB = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
                        if (!empty($ssLB)) {
                            // 如果SSLB不在8047包含列表中，需要检查8048例外列表
                            if (!in_array($ssLB, $excludeKeywords8047)) {
                                if (!empty($ruleMap8048)) {
                                    // 如果手术名称不在8048例外列表中，则跳过
                                    if (!in_array($surgeryName, $excludeKeywords8048)) {
                                        continue;
                                    }
                                } else {
                                    // 如果8048没有配置，直接跳过
                                    continue;
                                }
                            }
                        }
                    }

                    // 符合分母条件
                    $hasValidOperation = true;
                }

                if(!$hasValidOperation){
                    continue;
                }


                $errorContent = [];
                $insert = [
                    'zyh' => $ZYH,
                    'zyssbmzql_fz' => 0,
                    'zyssbmzql_fm' => 1,
                    'zyssbmzql_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                //删除旧数据
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['zyssbmzql_fm' => null, 'zyssbmzql_fz' => null, 'zyssbmzql_error' => null]);

                $mainGroup = [
                    'status' => 1,
                    'content' => []
                ];

                // 用住院号查询home_quality表
                $homeQualityData = HomeQuality::query()->where('zyh', $ZYH)->get();

                if ($homeQualityData->isEmpty()) {
                    // 如果数据为空，分子为1
                    $insert['zyssbmzql_fz'] = 1;
                    $mainGroup['content'][] = [
                        'status' => 1,
                        'content' => '质控后主要手术编码【正确】'
                    ];
                } else {
                    // 如果数据不为空，用error_rule字段关联查询error_rule表
                    $hasMainDiagnosisError = false;
                    foreach ($homeQualityData as $quality) {
                        if (!empty($quality->error_rule)) {
                            $errorRuleIds = json_decode($quality->error_rule, true);
                            if (is_array($errorRuleIds)) {
                                // 查询error_rule表，判断field字段是否有"主要手术编码"
                                $errorRules = ErrorRule::query()->whereIn('id', $errorRuleIds)
                                    ->pluck('field')
                                    ->toArray();

                                if (in_array('主要手术编码', $errorRules)) {
                                    $hasMainDiagnosisError = true;
                                    break;
                                }
                            }
                        }
                    }

                    if ($hasMainDiagnosisError) {
                        // 如果有主要手术编码错误，分子为0
                        $insert['zyssbmzql_fz'] = 0;
                        $mainGroup['status'] = 0;
                        $mainGroup['content'][] = [
                            'status' => 0,
                            'content' => '质控后主要手术编码【错误】'
                        ];
                    } else {
                        // 如果没有主要手术编码错误，分子为1
                        $insert['zyssbmzql_fz'] = 1;
                        $mainGroup['content'][] = [
                            'status' => 1,
                            'content' => '质控后主要手术编码【正确】'
                        ];
                    }
                }

                $errorContent[] = $mainGroup;

                // 保存结果
                $insert['zyssbmzql_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);

                //如果zyh不为空，根据zyh更新或插入es
                if (!empty($zyh)) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }
        }

        return true;
    }

    /**
     * 主要手术填写正确率
     */
    public function zysszql($zyh, $start, $end)
    {
        $page = 1;

        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母

        // 手术级别过滤（使用ruleMap8047/8048）
        $ruleMap8047 = RuleWordMap::query()->where('id', '=', 8047)->value('keyword');
        $excludeKeywords8047 = strpos($ruleMap8047, ',') !== false ? explode(',', $ruleMap8047) : [$ruleMap8047];
        $ruleMap8048 = RuleWordMap::query()->where('id', '=', 8048)->value('keyword');
        $excludeKeywords8048 = strpos($ruleMap8048, ',') !== false ? explode(',', $ruleMap8048) : [$ruleMap8048];

        while (true) {
            $data = DB::table($cyzs->table_name)
                ->leftJoin('patient_doctor_info', $cyzs->table_name . '.' . $cyzs->MED_REC_ID, '=', 'patient_doctor_info.AAA28')
                ->when($cyzs->condition, function ($query) use ($cyzs) {
                    return $query->whereRaw($cyzs->condition);
                })
                ->when($zyh, function ($query) use ($zyh) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where('ZYH', $zyh);
                    }
                    return $query;
                })
                ->whereBetween('AAC01', [$start, $end])
                ->paginate(500, [$cyzs->table_name . '.' . $cyzs->MED_REC_ID, 'AAB01', 'AAC01', 'AAC11N', 'AEE03'], 'page', $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);

                $mainOperations = MainOperation::query()
                    ->where('AAA28', $ZYH)
                    ->get(['ICD9_NAME', 'OPE_MAN_NAME', 'OPE_MAN_CODE', 'OPE_DATE'])
                    ->toArray();

                $secondaryOperations = SecondaryOperation::query()
                    ->where('AAA28', $ZYH)
                    ->get(['ICD9_NAME', 'OPE_MAN_NAME', 'OPE_MAN_CODE', 'OPE_DATE'])
                    ->toArray();

                $allOperations = array_merge($mainOperations, $secondaryOperations);

                if (empty($allOperations)) {
                    continue; // 没有四级手术，跳过
                }

                // 用于记录符合分母条件的手术
                //$validOperations = [];
                $hasValidOperation = false;

                foreach ($allOperations as $operation) {
                    $surgeryName = $operation['ICD9_NAME'] ?? '';
                    $surgeonName = $operation['OPE_MAN_NAME'] ?? '';
                    $surgeonCode = $operation['OPE_MAN_CODE'] ?? '';
                    $surgeryDate = $operation['OPE_DATE'] ?? '';

                    if (empty($surgeryName)) {
                        continue;
                    }



                    // 使用8047/8048规则过滤手术
                    if (!empty($ruleMap8047)) {
                        $ssLB = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
                        if (!empty($ssLB)) {
                            // 如果SSLB不在8047包含列表中，需要检查8048例外列表
                            if (!in_array($ssLB, $excludeKeywords8047)) {
                                if (!empty($ruleMap8048)) {
                                    // 如果手术名称不在8048例外列表中，则跳过
                                    if (!in_array($surgeryName, $excludeKeywords8048)) {
                                        continue;
                                    }
                                } else {
                                    // 如果8048没有配置，直接跳过
                                    continue;
                                }
                            }
                        }
                    }

                    // 符合分母条件
                    $hasValidOperation = true;
                }

                if(!$hasValidOperation){
                    continue;
                }

                $errorContent = [];
                $insert = [
                    'zyh' => $ZYH,
                    'zysszql_fz' => 0,
                    'zysszql_fm' => 1,
                    'zysszql_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                //删除旧数据
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['zysszql_fm' => null, 'zysszql_fz' => null, 'zysszql_error' => null]);

                $mainGroup = [
                    'status' => 0,
                    'content' => []
                ];

                // 用住院号查询home_quality表
                $homeQualityData = HomeQuality::query()->where('zyh', $ZYH)->get();

                if ($homeQualityData->isEmpty()) {
                    // 如果数据为空，分子为1
                    $insert['zysszql_fz'] = 1;
                    $mainGroup['content'][] = [
                        'status' => 1,
                        'content' => '质控后主要手术名称【正确】'
                    ];
                } else {
                    // 如果数据不为空，用error_rule字段关联查询error_rule表
                    $hasMainDiagnosisError = false;
                    foreach ($homeQualityData as $quality) {
                        if (!empty($quality->error_rule)) {
                            $errorRuleIds = json_decode($quality->error_rule, true);
                            if (is_array($errorRuleIds)) {
                                // 查询error_rule表，判断field字段是否有"主要手术编码"
                                $errorRules = ErrorRule::query()->whereIn('id', $errorRuleIds)
                                    ->pluck('field')
                                    ->toArray();

                                if (in_array('主要手术名称', $errorRules)) {
                                    $hasMainDiagnosisError = true;
                                    break;
                                }
                            }
                        }
                    }

                    if ($hasMainDiagnosisError) {
                        // 如果有主要手术编码错误，分子为0
                        $insert['zysszql_fz'] = 0;
                        $mainGroup['status'] = 0;
                        $mainGroup['content'][] = [
                            'status' => 0,
                            'content' => '质控后主要手术名称【错误】'
                        ];
                    } else {
                        // 如果没有主要手术编码错误，分子为1
                        $insert['zysszql_fz'] = 1;
                        $mainGroup['content'][] = [
                            'status' => 1,
                            'content' => '质控后主要手术名称【正确】'
                        ];
                    }
                }

                $errorContent[] = $mainGroup;

                // 保存结果
                $insert['zysszql_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);

                //如果zyh不为空，根据zyh更新或插入es
                if (!empty($zyh)) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }
        }

        return true;
    }

    /**
     * 知情同意书规范签署率
     */
    public function zqtys($zyh, $start, $end)
    {
        $page = 1;

        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母
        $bagl_11 = ZbBagl::getFirstById(11, true); //知情同意书配置
        $bl01Service = new ElasticsearchService('bl01_202303');
        $mzjlService = new ElasticsearchService('mzjl_2023');

        while (true) {
            $data = DB::table($cyzs->table_name)
                ->leftJoin('patient_doctor_info', $cyzs->table_name . '.' . $cyzs->MED_REC_ID, '=', 'patient_doctor_info.AAA28')
                ->when($cyzs->condition, function ($query) use ($cyzs) {
                    return $query->whereRaw($cyzs->condition);
                })
                ->when($zyh, function ($query) use ($zyh, $cyzs) {
                    //如果zyh不为空，则查询zyh
                    if (!empty($zyh)) {
                        return $query->where($cyzs->table_name . '.' . $cyzs->MED_REC_ID, $zyh);
                    }
                    return $query;
                })
                ->whereBetween('AAC01', [$start, $end])
                ->paginate(500, [$cyzs->table_name . '.' . $cyzs->MED_REC_ID, 'AAB01', 'AAC01', 'AAC11N', 'AEE03'], 'page', $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                $AAB01 = $value->AAB01;
                $AAC01 = $value->AAC01;

                $errorContent = [];
                $insert = [
                    'zyh' => $ZYH,
                    'zqtys_fz' => 0,
                    'zqtys_fm' => 0,
                    'zqtys_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                //删除旧数据
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['zqtys_fm' => null, 'zqtys_fz' => null, 'zqtys_error' => null]);

                // 查询知情同意书（BLLB=329）- 合并原 bl01 方法逻辑
                $must = [
                    ["term" => ['JZHM' => $ZYH]],
                    ["term" => ['BLLB' => 329]]
                ];
                $params = $bl01Service->clearMust()
                    ->queryByMustBatch($must)
                    ->getParams();
                $restful = app('es')->search($params);
                $bl01Data = $bl01Service->getDataByEs($restful);
                $bl01Data = !empty($bl01Data[0]) ? $bl01Data[0] : [];

                if (empty($bl01Data)) {
                    continue;
                }

                $insert['zqtys_fm'] = 1;

                // 分子1 - 授权同意类检查
                $sqtylGroup = [
                    'status' => 0,
                    'content' => []
                ];
                $sqtylIsOk = 0;

                foreach ($bl01Data as $bl01Value) {
                    $blsyData = EMR_BL_BLSY::query()->where("BLBH", $bl01Value['BLBH'])->get()->toArray();

                    if ($blsyData) {
                        $str = '未在出入院时间之内';
                        if ($AAB01 <= $blsyData[0]['SYSJ'] && $blsyData[0]['SYSJ'] <= $AAC01) {
                            $str = '在出入院时间之内';
                        }

                        // 查询医生信息 - 合并原 staff 方法逻辑
                        $staffData = Staff::query()->where('code', $blsyData[0]['SYYS'])->get()->toArray();
                        $name = !empty($staffData[0]) ? $staffData[0]['name'] : '';
                        if ($name && $str == '在出入院时间之内') {
                            $sqtylIsOk = 1;
                            $sqtylGroup['status'] = 1;
                            $sqtylGroup['content'][] = [
                                'status' => 1,
                                'content' => '授权同意类【' . $name . '，' . $blsyData[0]['SYSJ'] . '，' . $str . '】'
                            ];
                            break;
                        } else {
                            $name = $name ?: '无';
                            $sqtylGroup['content'][] = [
                                'status' => 0,
                                'content' => '授权同意类【' . $name . '，' . $blsyData[0]['SYSJ'] . '，' . $str . '】'
                            ];
                        }
                    } else {
                        $sqtylGroup['content'][] = [
                            'status' => 0,
                            'content' => '授权同意类【无】'
                        ];
                    }
                }

                $errorContent[] = $sqtylGroup;

                // 分子2 - 输血治疗同意书检查 - 合并原 sxzltys 方法逻辑
                $sxzlGroup = [
                    'status' => 1,
                    'content' => []
                ];
                $sxzlIsOk = 1; // 默认为1，如果没有输血则不检查

                $must = [
                    ["term" => ['JZHM' => $ZYH]],
                    ["term" => ['MBLB' => 59]]
                ];
                $params = $bl01Service->clearMust()
                    ->queryByMustBatch($must)
                    ->getParams();
                $restful = app('es')->search($params);
                $sxzltysData = $bl01Service->getDataByEs($restful);
                $sxzltysData = !empty($sxzltysData[0]) ? $sxzltysData[0] : [];

                if ($sxzltysData) {
                    // 查询输血时间 - 合并原 sxsj 方法逻辑
                    $must = [
                        ["term" => ['JZHM' => $ZYH]]
                    ];
                    $should = [
                        ["term" => ['MBLB' => 45]],
                        ["match_phrase" => ['BLMC' => '输血病程记录']]
                    ];
                    $params = $bl01Service->clearMust()
                        ->queryByMustBatch($must)
                        ->queryByShouldBatch($should)
                        ->minimumShouldMatch(1)
                        ->orderBy(data_get($bagl_11, 'table_field'), 'ASC')
                        ->getParams();
                    $restful = app('es')->search($params);
                    $sxsjData = $bl01Service->getDataByEs($restful);
                    $sxsj = !empty($sxsjData[0]) ? $sxsjData[0][0][data_get($bagl_11, 'table_field')] : '';

                    $sxzlIsOk = 0;
                    foreach ($sxzltysData as $sxValue) {
                        if ($sxValue[data_get($bagl_11, 'table_field')] <= $sxsj) {
                            $sxzlIsOk = 1;
                            $sxzlGroup['status'] = 1;
                            $sxzlGroup['content'][] = [
                                'status' => 1,
                                'content' => '输血治疗同意书【' . $sxValue[data_get($bagl_11, 'table_field')] . ' < 输血时间：' . $sxsj . '】'
                            ];
                            break;
                        } else {
                            $sxzlGroup['status'] = 0;
                            $sxzlGroup['content'][] = [
                                'status' => 0,
                                'content' => '输血治疗同意书【' . $sxValue[data_get($bagl_11, 'table_field')] . ' > 输血时间：' . $sxsj . '】'
                            ];
                        }
                    }
                }

                if (!empty($sxzlGroup['content'])) {
                    $errorContent[] = $sxzlGroup;
                }

                // 分子3 - 手术知情同意书检查 - 合并原 sszqtys 方法逻辑
                $sszqGroup = [
                    'status' => 0,
                    'content' => []
                ];
                $sszqtyIsOk = 0;

                $must = [
                    ["term" => ['JZHM' => $ZYH]],
                    ["term" => ['BLLB' => 329]]
                ];
                $params = $bl01Service->clearMust()
                    ->queryByMustBatch($must)
                    ->getParams();
                $restful = app('es')->search($params);
                $sszqtysData = $bl01Service->getDataByEs($restful);
                $sszqtysData = !empty($sszqtysData[0]) ? $sszqtysData[0] : [];

                if ($sszqtysData) {
                    // 查询麻醉记录 - 合并原 mzjl 方法逻辑
                    $must = [
                        ["term" => ['HOSPIZATIONID' => $ZYH]]
                    ];
                    $params = $mzjlService->clearMust()
                        ->queryByMustBatch($must)
                        ->getParams();
                    $restful = app('es')->search($params);
                    $mzjlData = $mzjlService->getDataByEs($restful);
                    $mzjlData = SM_SSAP::query()->where('ZYH', $ZYH)->get()->toArray();

                    if ($mzjlData) {
                        foreach ($sszqtysData as $ssValue) {
                            if ($ssValue[data_get($bagl_11, 'table_field')] <= $mzjlData[0]['SSRQ']) {
                                $sszqtyIsOk = 1;
                                $sszqGroup['status'] = 1;
                                $sszqGroup['content'][] = [
                                    'status' => 1,
                                    'content' => '手术知情同意书【' . $ssValue[data_get($bagl_11, 'table_field')] . ' ＜ 手术开始时间：' . $mzjlData[0]['SSRQ'] . '】'
                                ];
                                break;
                            } else {
                                $sszqGroup['content'][] = [
                                    'status' => 0,
                                    'content' => '手术知情同意书【' . $ssValue[data_get($bagl_11, 'table_field')] . ' > 手术开始时间：' . $mzjlData[0]['SSRQ'] . '】'
                                ];
                            }
                        }
                    } else {
                        $sszqGroup['content'][] = [
                            'status' => 0,
                            'content' => '手术知情同意书【' . $sszqtysData[0][data_get($bagl_11, 'table_field')] . ' ＜ 手术开始时间：无】'
                        ];
                    }
                } else {
                    $sszqGroup['content'][] = [
                        'status' => 0,
                        'content' => '手术知情同意书【无】'
                    ];
                }

                $errorContent[] = $sszqGroup;

                // 判断分子：三个条件都满足才为1
                if ($sqtylIsOk && $sxzlIsOk && $sszqtyIsOk) {
                    $insert['zqtys_fz'] = 1;
                }

                // 保存结果
                $insert['zqtys_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);

                //如果zyh不为空，根据zyh更新或插入es
                if (!empty($zyh)) {
                    $esService = new ElasticsearchService('indicator');
                    $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
                }
            }
        }

        return true;
    }
}
