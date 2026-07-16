<?php

namespace App\Services\MysqlDataSync\hlw;

use App\Model\Yzb;
use Carbon\Carbon;
use App\Model\PACS;
use App\Model\SSCZ;
use App\Model\Staff;
use App\Model\ZbBagl;
use App\Model\SM_SSAP;
use App\Model\ZY_BLFY;
use App\Model\ZY_BRRY;
use App\Model\CaseRule;
use App\Model\ErrorRule;
use App\Model\Indicator;
use App\Model\BA_RECEIVE;
use App\Model\Bllb294_295;
use App\Model\CaseQuality;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLSY;
use App\Model\EMR_BL_BLXG;
use App\Model\FeeDetailed;
use App\Model\HomeQuality;
use App\Model\PatientInfo;
use App\Model\RuleWordMap;
use App\Model\CaseQualityZm;
use App\Model\MainOperation;
use App\Model\MainDiagnosis;
use App\Model\OtherDiagnosis;
use App\Model\MedicinalInfo;
use App\Model\BaMrClassNumber;
use App\Model\RuleSetting;
use App\Model\V_JMGS_YMresult;
use App\Services\EsSaveService;
use App\Services\PublicService;
use Illuminate\Validation\Rule;
use App\Model\SecondaryOperation;
use PhpParser\Node\Stmt\Foreach_;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\ElasticsearchService;

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
        $this->cdl($zyh, $start, $end);
        $this->gdwzl($zyh, $start, $end);
        $this->zyzdbmzql($zyh, $start, $end);
        $this->zyzdzql($zyh, $start, $end);
        $this->zyssbmzql($zyh, $start, $end);
        $this->zysszql($zyh, $start, $end);
        $this->zqtys($zyh, $start, $end);
        $this->ssxgjl($zyh, $start, $end);
    }

    public function getPatientInfo($cyzs, $zyh, $start, $end, $page)
    {
        return DB::table($cyzs->table_name)
            ->join('patient_doctor_info', $cyzs->table_name . '.' . $cyzs->MED_REC_ID, '=', 'patient_doctor_info.AAA28')
            ->join('ZY_BRRY', 'patient_info.MED_REC_ID', '=', 'ZY_BRRY.ZYH')
            ->leftJoin('department', function ($join) {
                $join->on(DB::raw('TRIM(ZY_BRRY.BRKS)'), '=', DB::raw('TRIM(department.dep_id)'));
            })
            ->when($cyzs->condition, function ($query) use ($cyzs) {
                return $query->whereRaw($cyzs->condition);
            })
            ->when($zyh, function ($query) use ($zyh, $cyzs) {
                if (!empty($zyh)) {
                    return $query->where($cyzs->table_name . '.' . $cyzs->MED_REC_ID, $zyh);
                }
                return $query;
            })
            //->whereRaw("patient_info.AAA28 NOT REGEXP '[A-Za-z]'")
            ->where('patient_info.IS_CATA', "=", 1)
            ->whereBetween('patient_info.AAC01', [$start, $end])
            ->paginate(500, [$cyzs->table_name . '.' . $cyzs->MED_REC_ID, 'patient_info.AAB01', $cyzs->table_name . '.AAA28', $cyzs->table_name . '.AAA29', 'patient_info.AAA01', 'patient_info.AAC01', 'patient_info.AAC04', 'patient_info.score', 'patient_doctor_info.AEE03', DB::raw('COALESCE(NULLIF(TRIM(department.dep_name), \'\'), \'\') as AAC11N')], 'page', $page);
    }

    public function skipAAA28($AAA28 = "")
    {
        return false;
        if (empty($AAA28)) {
            return true;
        }

        return preg_match('/[A-Za-z]/', $AAA28);
    }

    /**
     * 恶性肿瘤放射治疗记录符合率
     */
    public function exzlfl($zyh, $start, $end)
    {
        $page = 1;

        $keyword20060 = RuleWordMap::getArrayById(20060);
        $rule10002 = RuleWordMap::query()->where('id', 10002)->value('keyword');

        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母
        $yzbService = null;
        $bl01Service = null;

        while (true) {
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);
            echo '第' . $page . '页，共' . $data->total() . PHP_EOL;
            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                echo $ZYH . ' ' . $value->AAA28 . PHP_EOL;
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], [
                    'exzlfl_fz' => 0,
                    'exzlfl_fm' => 0,
                    'exzlfl_error' => null
                ]);
                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }

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

                $yzbData = Yzb::query()->where('ZYH', $ZYH)->where('is_fangliao', 1)->get()->toArray();
                echo '医嘱数据条数：' . count($yzbData) . PHP_EOL;
                if (empty($yzbData)) {
                    continue;
                }

                // 排除条件：主诊断和其他诊断的诊断编码（ICD10_ID1）中，
                // 至少有一个以字母 c/C 开头才计算分母，否则跳过
                $diagnosisCodeList = array_merge(
                    MainDiagnosis::query()->where('AAA28', $ZYH)->pluck('ICD10_ID1')->toArray(),
                    OtherDiagnosis::query()->where('AAA28', $ZYH)->pluck('ICD10_ID1')->toArray()
                );
                $hasCancerDiagnosis = false;
                foreach ($diagnosisCodeList as $diagnosisCode) {
                    if (stripos((string)$diagnosisCode, 'c') === 0) {
                        $hasCancerDiagnosis = true;
                        break;
                    }
                }
                if (!$hasCancerDiagnosis) {
                    continue;
                }

                // 遍历所有放疗医嘱
                foreach ($yzbData as $yzb) {
                    $yzmc = $yzb['YZMC'] ?: '';
                    $yzmc = str_replace(' ', '', $yzmc);

                    if (empty($yzmc) || (!empty($rule10002) && $yzb['YZZT'] == $rule10002)) {
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
                    $bl01 = EMR_BL_BL01::query()
                        ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                        ->where('EMR_BL_BL01.JZHM', $ZYH)
                        ->where('BLLB', 294)
                        ->get(['EMR_BL_BL01.BLBH', 'BLMC', 'HJNR'])->toArray();
                    $hasFl = false;
                    $blmc = '';
                    $flKeyword = '';
                    foreach ($bl01 as $v) {
                        foreach ($keyword20060 as $k => $v2) {
                            if (strpos($v['HJNR'], $v2) !== false) {
                                $hasFl = true;
                                $blmc = $v['BLMC'];
                                $flKeyword = $v2;
                                break;
                            }
                        }
                    }
                    var_dump($hasFl);
                    var_dump($blmc);

                    if (!$hasFl) {
                        $orderGroup['content'][] = [
                            'status' => 0,
                            'content' => "病程记录【无】"
                        ];
                    } else {
                        $orderGroup['status'] = 1;
                        $insert['exzlfl_fz'] = 1;
                        $orderGroup['content'][] = [
                            'status' => 1,
                            'content' => "病程记录【" . $blmc . "】包含“" . $flKeyword . "”"
                        ];
                    }

                    $errorContent[] = $orderGroup;
                }

                // 保存结果
                if ($insert['exzlfl_fm'] > 0) {
                    $insert['exzlfl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                    Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                    $insert['AAA28'] = $value->AAA28;
                    $insert['AAA01'] = $value->AAA01;
                    $insert['AAC11N'] = $value->AAC11N;
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


        $zyHcmxService = null;
        $bl01Service = null;
        while (true) {
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                // 如果当前页码大于最大页码则结束
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                //删除
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['ryjl24_fm' => null, 'ryjl24_fz' => null, 'ryjl24_error' => null]);

                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }


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
                $insert['ryjl24_fm'] = 1;
                $must = [
                    ["term" => [$hsfcsj->MED_REC_ID => $ZYH]],
                ];

                $surgeryGroup = [
                    'status' => 0,
                    'content' => []
                ];
                echo $ZYH . PHP_EOL;
                $must = array_merge($must, $hsfcsjWhere);

                // 查询护士分床时间（动态表 hsfcsj->table_name，按配置字段升序取首条）（MySQL）
                $zyHcmxQuery = DB::table($hsfcsj->table_name)->where($hsfcsj->MED_REC_ID, $ZYH);
                foreach ($hsfcsjWhere as $cond) {
                    $term = $cond['term'];
                    $zyHcmxQuery->where(key($term), reset($term));
                }
                $zyHcmxData = $zyHcmxQuery->orderBy($hsfcsj->table_field, 'asc')->get()->toArray();
                $HCRQ = !empty($zyHcmxData[0]->{$hsfcsj->table_field}) ? $zyHcmxData[0]->{$hsfcsj->table_field} : '';

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

                    // 查询入院记录病程（BLLB 属于配置类别之一，排除作废 BLZT=9）（MySQL）
                    $bcjlRows = DB::table($bcjlwcsj->table_name)
                        ->where('JZHM', $ZYH)
                        ->whereIn('BLLB', $bllbs)
                        ->where('BLZT', '<>', 9)
                        ->limit(1000)
                        ->get()->toArray();
                    // 转为数组结构，保持与原 ES 返回一致的下标层级
                    $bcjlRows = json_decode(json_encode($bcjlRows), true);
                    $bl01Data = [$bcjlRows];
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
                } else {
                    $surgeryGroup['content'][] = [
                        'status' => 0,
                        'content' => '入院时间【无】'
                    ];
                }
                var_dump($surgeryGroup);
                if ($insert['ryjl24_fm'] > 0) {
                    $errorContent[] = $surgeryGroup;
                    if (!empty($errorContent)) {
                        $insert['ryjl24_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                        Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                        $insert['AAA28'] = $value->AAA28;
                        $insert['AAA01'] = $value->AAA01;
                        $insert['AAC11N'] = $value->AAC11N;
                    }
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
        $cyswjlbllb = ZbBagl::getFirstById(9, true); //出院记录、24小时内入出院记录、死亡记录BLLB

        $bllbs = explode(',', $cyswjlbllb->keyword);

        while (true) {
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                echo $ZYH . PHP_EOL;
                //删除
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['cyjl24_fm' => null, 'cyjl24_fz' => null, 'cyjl24_error' => null]);

                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }

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

                $surgeryGroup = [
                    'status' => 0,
                    'content' => []
                ];
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => '出院时间【' . $value->AAC01 . '】'
                ];


                $bl01Data = EMR_BL_BL01::query()->where('JZHM', $ZYH)->whereIn('BLLB', $bllbs)->where('BLZT', '<>', 9)->get()->toArray();
                if (empty($bl01Data)) {
                    echo '出院记录【无】' . PHP_EOL;
                    $surgeryGroup['content'][] = [
                        'status' => 0,
                        'content' => "出院记录【无】"
                    ];
                } else {
                    echo '出院记录【有】' . PHP_EOL;
                    var_dump($bl01Data);
                    if (empty($bl01Data[0]['first_blsy_time'])) {
                        $surgeryGroup['content'][] =
                            [
                                'status' => 0,
                                'content' => "出院记录【上级医师未审签】"
                            ];
                    } elseif (strtotime($bl01Data[0]['first_blsy_time']) - strtotime($value->AAC01) > 86400) {
                        $surgeryGroup['content'][] =
                            [
                                'status' => 0,
                                'content' => "出院记录首次签名时间【" . $bl01Data[0]['first_blsy_time'] . "】，超24小时"
                            ];
                    } else {
                        $insert['cyjl24_fz'] = 1;
                        $surgeryGroup['status'] = 1;
                        $surgeryGroup['content'][] =
                            [
                                'status' => 1,
                                'content' => "出院记录首次签名时间【" . $bl01Data[0]['first_blsy_time'] . "】"
                            ];
                    }
                }

                if ($insert['cyjl24_fm'] > 0) {
                    $errorContent[] = $surgeryGroup;
                    if (!empty($errorContent)) {
                        $insert['cyjl24_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                        Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                        $insert['AAA28'] = $value->AAA28;
                        $insert['AAA01'] = $value->AAA01;
                    }
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
        $cyEsService = null;
        $bl01Service = null;
        //获取rulewordmap 8000，8001
        $rule8000 = RuleWordMap::getArrayById(8003);
        $rule8001 = RuleWordMap::getArrayById(8001);



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
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);
            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['basy24_fm' => null, 'basy24_fz' => null, 'basy24_error' => null]);

                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }

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
                    $yzb = Yzb::query()->where('ZYH', $ZYH)->get(['YZMC'])->toArray();
                    $yzbData = [];
                    foreach ($yzb as $val) {
                        foreach ($rule8000 as $rule) {
                            if (strpos($val['YZMC'], $rule) !== false) {
                                $yzbData[] = $val['YZMC'];
                                break;
                            }
                        }
                    }
                    $endTime = null;
                    if (!empty($yzbData)) {
                        $endTime = date('Y-m-d H:i:s', strtotime($CYSJ) + (3600 * 24 * 7));
                    } else {
                        $endTime = date('Y-m-d H:i:s', strtotime($CYSJ) + (3600 * 24));
                    }

                    //$blsy = EMR_BL_BLSY::query()->where('BLBH', $ZYH)->get()->toArray();
                    $blsy = EMR_BL_BL01::query()->where('JZHM', $ZYH)->whereIn('BLLB', $rule8001)->get()->toArray();
                    if (!empty($blsy[0])) {
                        $firstSignTime = $blsy[0]['first_blsy_time'];
                        if (strtotime($firstSignTime) < strtotime($endTime)) {
                            $insert['basy24_fz'] = 1;
                            $surgeryGroup['status'] = 1;
                            $surgeryGroup['content'][] = [
                                'status' => 1,
                                'content' => '病案首页完成时间【' . $blsy[0]['first_blsy_time'] . '】'
                            ];
                        } else {
                            $surgeryGroup['content'][] = [
                                'status' => 0,
                                'content' => '病案首页完成时间【' . $blsy[0]['first_blsy_time'] . '】'
                            ];
                        }
                    }
                } else {
                    continue;
                }

                $errorContent[] = $surgeryGroup;
                $insert['basy24_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                $insert['AAA28'] = $value->AAA28;
                $insert['AAA01'] = $value->AAA01;
                $insert['AAC11N'] = $value->AAC11N;
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
        $bl01Service = null;

        while (true) {
            // 查询住院病人
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);
            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['ssjl24_fm' => null, 'ssjl24_fz' => null, 'ssjl24_error' => null]);

                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }

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

                    // 第一组查询：MBLB条件（手术记录，执行时间在术后24小时内，排除作废）（MySQL）
                    $mblbQuery = EMR_BL_BL01::query()
                        ->where('JZHM', $ZYH)
                        ->where('ZXSJ', '>=', $JSRQ)
                        ->where('ZXSJ', '<=', $endTime)
                        ->where('BLZT', '<>', 9);
                    if (!empty($rule8019)) {
                        if (strpos($rule8019, ',') !== false) {
                            $mblbQuery->whereIn('MBLB', explode(',', $rule8019));
                        } else {
                            $mblbQuery->where('MBLB', $rule8019);
                        }
                    }
                    $bl01Datamblb = [json_decode(json_encode($mblbQuery->get()->toArray()), true)];



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

                        // 第二组查询：BLLB条件 且 BLMC 前缀匹配"手术"（排除作废）（MySQL）
                        $bllbQuery = EMR_BL_BL01::query()
                            ->where('JZHM', $ZYH)
                            ->where('ZXSJ', '>=', $JSRQ)
                            ->where('ZXSJ', '<=', $endTime)
                            ->where('BLZT', '<>', 9);
                        if (!empty($rule8021)) {
                            if (strpos($rule8021, ',') !== false) {
                                $bllbQuery->whereIn('BLLB', explode(',', $rule8021));
                            } else {
                                $bllbQuery->where('BLLB', $rule8021);
                            }
                        }
                        if (!empty($rule8020)) {
                            $prefixList = strpos($rule8020, ',') !== false ? explode(',', $rule8020) : [$rule8020];
                            $bllbQuery->where(function ($q) use ($prefixList) {
                                foreach ($prefixList as $rule) {
                                    // match_phrase_prefix 等价于前缀匹配
                                    $q->orWhere('BLMC', 'LIKE', $rule . '%');
                                }
                            });
                        }
                        $bl01Databllb = [json_decode(json_encode($bllbQuery->limit(1000)->get()->toArray()), true)];
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
                    $insert['AAA28'] = $value->AAA28;
                    $insert['AAA01'] = $value->AAA01;
                    $insert['AAC11N'] = $value->AAC11N;
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
        $bl01Service = null;
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
        $yzbService = null;

        while (true) {
            // 查询住院病人
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);
            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['ctmrfhl_fm' => null, 'ctmrfhl_fz' => null, 'ctmrfhl_error' => null]);

                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }

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

                // 先查询pacs $pacs = \App\Model\PACS::query()->where("ZYH", $ZYH)->get(["JCMC", "BGSJ"])->toArray(); 
                $pacs = \App\Model\PACS::query()->where("ZYH", $ZYH);
                $pacs->where(function ($query) use ($rule25, $rule26, $rule27) {
                    $query->where(function ($rule25Query) use ($rule25) {
                        foreach ($rule25 as $index => $item) {
                            $method = $index === 0 ? 'where' : 'orWhere';
                            $rule25Query->{$method}("JCMC", "like", "%{$item}%");
                        }
                    })->orWhere(function ($rule26Query) use ($rule26, $rule27) {
                        $rule26Query->where(function ($rule26KeywordQuery) use ($rule26) {
                            foreach ($rule26 as $index => $item) {
                                $method = $index === 0 ? 'where' : 'orWhere';
                                $rule26KeywordQuery->{$method}("JCMC", "like", "%{$item}%");
                            }
                        });

                        foreach ($rule27 as $item) {
                            $rule26Query->where("JCMC", "not like", "%{$item}%");
                        }
                    });
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
                    //如果bgsj为空，则跳过
                    if (empty($bgsj)) {
                        continue;
                    }
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

                            // 根据当前检查名称选择对应的病程内容规则，避免 rule25 和 rule26 交叉匹配。
                            $jcmcContainsRule25 = false;
                            foreach ($rule25 as $keyword) {
                                if (strpos($jcmc, $keyword) !== false) {
                                    $jcmcContainsRule25 = true;
                                    break;
                                }
                            }

                            $jcmcContainsRule26 = false;
                            foreach ($rule26 as $keyword) {
                                if (strpos($jcmc, $keyword) !== false) {
                                    $jcmcContainsRule26 = true;
                                    break;
                                }
                            }

                            $jcmcContainsRule27 = false;
                            foreach ($rule27 as $keyword) {
                                if (strpos($jcmc, $keyword) !== false) {
                                    $jcmcContainsRule27 = true;
                                    break;
                                }
                            }

                            $hjnrIncludedKeywords = [];
                            $hjnrExcludedKeywords = [];
                            if ($jcmcContainsRule25) {
                                $hjnrIncludedKeywords = $rule25;
                            } elseif ($jcmcContainsRule26 && !$jcmcContainsRule27) {
                                $hjnrIncludedKeywords = $rule26;
                                $hjnrExcludedKeywords = $rule27;
                            }

                            $bl01Query = EMR_BL_BL01::query()
                                ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                                ->where('EMR_BL_BL01.JZHM', $ZYH)
                                ->whereIn('EMR_BL_BL01.BLLB', $rule9009)
                                ->where('EMR_BL_BL01.ZXSJ', '>=', $bgsj)
                                ->where('EMR_BL_BL01.ZXSJ', '<=', $bgsj_end);
                            if (!empty($hjnrIncludedKeywords)) {
                                $bl01Query->where(function ($hjnrQuery) use ($hjnrIncludedKeywords) {
                                    foreach ($hjnrIncludedKeywords as $keyword) {
                                        $hjnrQuery->orWhere('EMR_BL_BLXG.HJNR', 'LIKE', '%' . $keyword . '%');
                                    }
                                });
                            }
                            foreach ($hjnrExcludedKeywords as $keyword) {
                                $bl01Query->where('EMR_BL_BLXG.HJNR', 'NOT LIKE', '%' . $keyword . '%');
                            }
                            Log::info('病程记录查询条件：' . json_encode($bl01Query->toSql()));
                            $bl01Data = [json_decode(json_encode(
                                $bl01Query->orderBy('EMR_BL_BL01.ZXSJ', 'asc')->get()->toArray()
                            ), true)];
                            if (!empty($bl01Data[0])) {
                                foreach ($bl01Data[0] as $bl01) {
                                    $matchedHjnrKeywords = [];
                                    $medicalRecordContent = (string)($bl01['HJNR'] ?? '');
                                    foreach ($hjnrIncludedKeywords as $keyword) {
                                        if (strpos($medicalRecordContent, $keyword) !== false) {
                                            $matchedHjnrKeywords[] = $keyword;
                                        }
                                    }
                                    $bl01['matched_hjnr_keywords'] = array_values(array_unique($matchedHjnrKeywords));

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
                                    $matchedKeywordText = implode('、', $validRecords[0]['matched_hjnr_keywords']);
                                    $orderGroup['content'][] = [
                                        'status' => 1,
                                        'content' => "病程记录【" . $validRecords[0]['BLMC'] . "】包含【" . $matchedKeywordText . "】"
                                    ];
                                    $orderGroup['content'][] = [
                                        'status' => 1,
                                        'content' => "首次签名时间【" . $validRecords[0]['first_blsy_time'] . "】"
                                    ];
                                } else {
                                    foreach ($invalidRecords as $invalidRecord) {
                                        $matchedKeywordText = implode('、', $invalidRecord['matched_hjnr_keywords']);
                                        $orderGroup['content'][] = [
                                            'status' => 0,
                                            'content' => "病程记录【" . $invalidRecord['BLMC'] . "】包含【" . $matchedKeywordText . "】"
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
                    $insert['AAA28'] = $value->AAA28;
                    $insert['AAA01'] = $value->AAA01;
                    $insert['AAC11N'] = $value->AAC11N;
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
        $bl01Service = null;
        $yzbService = null;
        $feeService = null;

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
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                //删除
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['bljcjl_fm' => null, 'bljcjl_fz' => null, 'bljcjl_error' => null]);

                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }

                echo $ZYH . PHP_EOL;
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

                //查询zy_blfy获取fymc
                $blfy = ZY_BLFY::query()->get(['fymc'])->toArray();
                $feeDetailed = FeeDetailed::query()->where('AAA28', $ZYH)->get(['FYMC', 'JFRQ'])->toArray();
                $feeData = [];
                foreach ($feeDetailed as $f) {

                    foreach ($blfy as $item) {
                        // 修复：正确的match_phrase_prefix格式
                        if (!empty($item['fymc']) && strpos($f['FYMC'], $item['fymc']) !== false) {
                            $feeData[] = $f;
                        }
                    }
                }

                if (!empty($feeData)) {
                    $allFeeCompliant = true;
                    $insert['bljcjl_fm'] = 1;

                    $jfsj = [];
                    foreach ($feeData as $item) {
                        if (in_array($item['JFRQ'], $jfsj)) {
                            continue;
                        }
                        $jfsj[] = $item['JFRQ'];
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
                        $getContainedKeywords = function ($recordContent) use ($rule9018) {
                            $containedKeywords = [];
                            foreach ($rule9018 as $keyword) {
                                if ($keyword !== null && $keyword !== '' && strpos((string)$recordContent, $keyword) !== false) {
                                    $containedKeywords[] = $keyword;
                                }
                            }

                            return array_values(array_unique($containedKeywords));
                        };

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
                        // 查询手术记录（模板类别属 rule9017，病程内容前缀匹配 rule9018 之一）（MySQL）
                        $ssQuery = EMR_BL_BL01::query()
                            ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                            ->where('EMR_BL_BL01.JZHM', $ZYH)
                            ->whereIn('EMR_BL_BL01.MBLB', $rule9017);
                        if (!empty($rule9018)) {
                            $ssQuery->where(function ($q) use ($rule9018) {
                                foreach ($rule9018 as $item) {
                                    if ($item !== null) {
                                        $q->orWhere('EMR_BL_BLXG.HJNR', 'LIKE', '%' . $item . '%');
                                    }
                                }
                            });
                        }
                        $ssData = [json_decode(json_encode($ssQuery->limit(1000)->get()->toArray()), true)];
                        if (!empty($ssData[0][0])) {
                            $surgeryRecord = $ssData[0][0];
                            $containedKeywords = $getContainedKeywords($surgeryRecord['HJNR'] ?? '');
                            $orderGroup['content'][] = [
                                'status' => 1,
                                'content' => "手术记录【" . $surgeryRecord['BLMC'] . "】（包含" . implode('、', $containedKeywords) . "）"
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
                        // 查询病程记录（模板类别属 rule9009，病程内容前缀匹配 rule9018 之一，
                        // 且病程名称不前缀匹配 rule9015）（MySQL）
                        $bcQuery = EMR_BL_BL01::query()
                            ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                            ->where('EMR_BL_BL01.JZHM', $ZYH)
                            ->whereIn('EMR_BL_BL01.MBLB', $rule9009);
                        if (!empty($rule9018)) {
                            $bcQuery->where(function ($q) use ($rule9018) {
                                foreach ($rule9018 as $item) {
                                    if ($item !== null) {
                                        $q->orWhere('EMR_BL_BLXG.HJNR', 'LIKE', '%' . $item . '%');
                                    }
                                }
                            });
                        }
                        if (!empty($rule9015)) {
                            foreach ($rule9015 as $item) {
                                if ($item !== null) {
                                    $bcQuery->where('EMR_BL_BL01.BLMC', 'NOT LIKE', '%' . $item . '%');
                                }
                            }
                        }
                        $bl01Data = [json_decode(json_encode($bcQuery->limit(1000)->get()->toArray()), true)];
                        if (!empty($bl01Data[0])) {
                            foreach ($bl01Data[0] as $bl01) {
                                $containedKeywords = $getContainedKeywords($bl01['HJNR'] ?? '');
                                $orderGroup['content'][] = [
                                    'status' => 1,
                                    'content' => "病程记录【" . $bl01['BLMC'] . "】（包含" . implode('、', $containedKeywords) . "）"
                                ];
                            }
                        } else {
                            $orderGroup['content'][] = [
                                'status' => 0,
                                'content' => "病程记录【无】"
                            ];
                        }

                        $orderGroup['status'] = 1;
                        foreach ($orderGroup['content'] as $item) {
                            if ($item['status'] == 0) {
                                $orderGroup['status'] = 0;
                                break;
                            }
                        }
                        if (!$orderGroup['status']) {
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

                        $insert['AAA28'] = $value->AAA28;
                        $insert['AAA01'] = $value->AAA01;
                        $insert['AAC11N'] = $value->AAC11N;
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
        $bl01Service = null;
        $yzbService = null;

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
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['kjywsy_fm' => null, 'kjywsy_fz' => null, 'kjywsy_error' => null]);

                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }

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
                // 查询抗菌药物使用医嘱（is_has_kjyw=1，排除皮试/停止等）（MySQL）
                $yzbData = [json_decode(json_encode(
                    Yzb::query()
                        ->where('ZYH', $ZYH)
                        ->where('is_has_kjyw', 1)
                        ->where('YYSX', '<>', 4)
                        ->where('YZMC', 'NOT LIKE', '%皮试%')
                        ->where('YZZT', '<>', 5)
                        ->where('PSBZ', '<>', 1)
                        ->limit(1000)
                        ->get()->toArray()
                ), true)];
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

                    // 查询病程记录（BLLB 属 rule9009，执行时间在开嘱前24h~后72h内，
                    // 病程内容前缀匹配 rule2053/抗菌药名之一，且不含 rule2055）（MySQL）
                    $shouldPrefix = [];
                    if (!empty($rule2053)) {
                        foreach ($rule2053 as $item) {
                            if ($item !== null) {
                                $shouldPrefix[] = $item;
                            }
                        }
                    }
                    foreach ($kjywName as $item) {
                        $shouldPrefix[] = $item['name'];
                    }
                    $kjywBl01Query = EMR_BL_BL01::query()
                        ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                        ->where('EMR_BL_BL01.JZHM', $ZYH)
                        ->whereIn('EMR_BL_BL01.BLLB', $rule9009)
                        ->where('EMR_BL_BL01.ZXSJ', '>=', $kzsj_start)
                        ->where('EMR_BL_BL01.ZXSJ', '<=', $kzsj_end);
                    if (!empty($shouldPrefix)) {
                        $kjywBl01Query->where(function ($q) use ($shouldPrefix) {
                            foreach ($shouldPrefix as $item) {
                                $q->orWhere('EMR_BL_BLXG.HJNR', 'LIKE', '%' . $item . '%');
                            }
                        });
                    }
                    foreach ($rule2055 as $item) {
                        $kjywBl01Query->where('EMR_BL_BLXG.HJNR', 'NOT LIKE', '%' . $item . '%');
                    }
                    $bl01Data = [json_decode(json_encode($kjywBl01Query->limit(1000)->get()->toArray()), true)];
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

                    $insert['AAA28'] = $value->AAA28;
                    $insert['AAA01'] = $value->AAA01;
                    $insert['AAC11N'] = $value->AAC11N;
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
        $bl01Service = null;
        $yzbService = null;

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
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                //删除
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['exzlhxzl_fm' => null, 'exzlhxzl_fz' => null, 'exzlhxzl_error' => null]);
                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }

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
                // 查询化疗药物使用医嘱（is_has_hlyw=1，排除停止等）（MySQL）
                $yzbData = [json_decode(json_encode(
                    Yzb::query()
                        ->where('ZYH', $ZYH)
                        ->where('is_has_hlyw', 1)
                        ->where('YYSX', '<>', 4)
                        ->where('YZZT', '<>', 5)
                        ->limit(1000)
                        ->get()->toArray()
                ), true)];
                //var_dump("yzbData", $yzbData);
                if (empty($yzbData[0][0])) {
                    continue;
                }

                // 排除条件：主诊断和其他诊断的诊断编码（ICD10_ID1）中，
                // 至少有一个以字母 c/C 开头才计算分母，否则跳过
                $diagnosisCodeList = array_merge(
                    MainDiagnosis::query()->where('AAA28', $ZYH)->pluck('ICD10_ID1')->toArray(),
                    OtherDiagnosis::query()->where('AAA28', $ZYH)->pluck('ICD10_ID1')->toArray()
                );
                $hasCancerDiagnosis = false;
                foreach ($diagnosisCodeList as $diagnosisCode) {
                    if (stripos((string)$diagnosisCode, 'c') === 0) {
                        $hasCancerDiagnosis = true;
                        break;
                    }
                }
                if (!$hasCancerDiagnosis) {
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

                    // 查询病程记录（BLLB 属 rule9009，执行时间在开嘱前24h~后72h内，
                    // 病程内容前缀匹配 化疗药名/rule9016 之一）（MySQL）
                    $shouldPrefix = [];
                    foreach ($kjywName as $item) {
                        $shouldPrefix[] = $item['name'];
                    }
                    foreach ($rule9016 as $item) {
                        if ($item !== null) {
                            $shouldPrefix[] = $item;
                        }
                    }
                    $hxzlBl01Query = EMR_BL_BL01::query()
                        ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                        ->where('EMR_BL_BL01.JZHM', $ZYH)
                        ->whereIn('EMR_BL_BL01.BLLB', $rule9009)
                        ->where('EMR_BL_BL01.ZXSJ', '>=', $kzsj_start)
                        ->where('EMR_BL_BL01.ZXSJ', '<=', $kzsj_end);
                    if (!empty($shouldPrefix)) {
                        $hxzlBl01Query->where(function ($q) use ($shouldPrefix) {
                            foreach ($shouldPrefix as $item) {
                                $q->orWhere('EMR_BL_BLXG.HJNR', 'LIKE', '%' . $item . '%');
                            }
                        });
                    }
                    $bl01Data = [json_decode(json_encode($hxzlBl01Query->limit(1000)->get()->toArray()), true)];
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

                    $insert['AAA28'] = $value->AAA28;
                    $insert['AAA01'] = $value->AAA01;
                    $insert['AAC11N'] = $value->AAC11N;
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
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;
            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                //删除
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['jjbll_fm' => null, 'jjbll_fz' => null, 'jjbll_error' => null]);
                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }

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
                $caseQualityZm = CaseQualityZm::query()->where('JZHM', $ZYH)->get()->toArray();
                if ($caseQualityZm) {
                    foreach ($caseQualityZm as $item) {
                        $orderGroup = [
                            'status' => 0,
                            'content' => []
                        ];
                        $ruleDesc = '';
                        if ($item['rule_id'] > 1000000) {
                            $rule = RuleSetting::query()->where('id', $item['rule_id'] - 1000000)->first();
                            $ruleDesc = $rule->description;
                        } else {
                            $rule = CaseRule::query()->where('id', $item['rule_id'])->first();
                            $ruleDesc = $rule->notice;
                        }
                        $orderGroup['content'][] = [
                            'status' => 0,
                            'content' => $ruleDesc
                        ];
                        $basis = json_decode($item['basis'], true);
                        if ($basis) {
                            $basis = array_values($basis);
                            foreach ($basis as $key => $val) {
                                if (is_array($val)) {
                                    $val = array_values($val);
                                    foreach ($val as $k => $v) {
                                        $orderGroup['content'][] = [
                                            'status' => 0,
                                            'content' => $v
                                        ];
                                    }
                                } else {
                                    $orderGroup['content'][] = [
                                        'status' => 0,
                                        'content' => $val
                                    ];
                                }
                            }
                        }

                        $errorContent[] = $orderGroup;
                    }
                }

                if ($insert['jjbll_fm'] > 0) {
                    $insert['jjbll_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                    Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);

                    $insert['AAA28'] = $value->AAA28;
                    $insert['AAA01'] = $value->AAA01;
                    $insert['AAC11N'] = $value->AAC11N;
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
        $yzbService = null;
        $bl01Service = null;
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
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);
            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;
            foreach ($data->items() as $value) {

                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                //删除
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['ssxgjl_fm' => null, 'ssxgjl_fz' => null, 'ssxgjl_error' => null]);
                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }

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
                    $isszcf = false;
                    $iszqty = false;
                    $isaqhc = false;
                    $isssjl = false;
                    $isshbc = false;

                    $ssmc = $item['ICD9_SSCZMC'];
                    $jsrq = $item['JSRQ'];
                    $ssrq = $item['SSRQ'];
                    $surgeonCode = $item['SZDM'];

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

                    // 查询医嘱（开嘱时间早于手术申请时间，医嘱名称正则"拟/定于...行...术"或含手术名称，按开嘱时间倒序）（MySQL）
                    $yzbData = [json_decode(json_encode(
                        Yzb::query()
                            ->where('ZYH', $ZYH)
                            ->where('KZSJ', '<=', $item['SSRQ'])
                            ->where(function ($q) use ($ssmc) {
                                $q->where('YZMC', 'REGEXP', '(拟|定于).*行.*术')
                                    ->orWhere('YZMC', 'LIKE', '%' . $ssmc . '%');
                            })
                            ->orderBy('KZSJ', 'desc')
                            ->limit(1000)
                            ->get()->toArray()
                    ), true)];
                    if (empty($yzbData[0])) {
                        // 放宽正则为"拟/定于...行..."，并排除"检查"开头医嘱（MySQL）
                        $yzbData = [json_decode(json_encode(
                            Yzb::query()
                                ->where('ZYH', $ZYH)
                                ->where('KZSJ', '<=', $item['SSRQ'])
                                ->where(function ($q) use ($ssmc) {
                                    $q->where('YZMC', 'REGEXP', '(拟|定于).*行.*')
                                        ->orWhere('YZMC', 'LIKE', '%' . $ssmc . '%');
                                })
                                ->where('YZMC', 'NOT LIKE', '检查%')
                                ->orderBy('KZSJ', 'desc')
                                ->limit(1000)
                                ->get()->toArray()
                        ), true)];
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
                        //获取所有病程记录（含病程内容 HJNR，按执行时间升序）（MySQL）
                        $bl01Data = [json_decode(json_encode(
                            EMR_BL_BL01::query()
                                ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                                ->where('EMR_BL_BL01.JZHM', $ZYH)
                                ->orderBy('EMR_BL_BL01.ZXSJ', 'asc')
                                ->limit(1000)
                                ->get()->toArray()
                        ), true)];
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
                            $isszcfData = []; // 术者术前查房数据

                            // 计算术前查房时间范围：手术开始前24小时到手术开始时间
                            $sqcfStartTime = date('Y-m-d H:i:s', strtotime($ssrq) - (24 * 3600));
                            $sqcfEndTime = $ssrq;

                            //术后三天的开始和结束时间，比如手术日期是2025-01-01 22:34:00，术后第一天算2025-01-03 00:00:00-2025-01-04 00:00:00，术后第二天算2025-01-04 00:00:00-2025-01-05 00:00:00，术后第三天算2025-01-05 00:00:00-2025-01-06 00:00:00

                            foreach ($bl01Data[0] as $bl01) {
                                //如果mblb在8058 或者病历名称 包含8059（strpos）（数组）
                                //var_dump('mblb:',$bl01['MBLB']);
                                //var_dump('bllb:',$bl01['BLLB']);
                                //var_dump('blmc:',$bl01['BLMC']);

                                if (in_array($bl01['MBLB'], $rule8058)) {
                                    if (strtotime($bl01['ZXSJ']) < strtotime($ssrq)) {
                                        if (strtotime($bl01['first_blsy_time']) < strtotime($ssrq)) {
                                            $issqxjData['yes'][] = $bl01;
                                        } else {
                                            $issqxjData['no'][] = $bl01;
                                        }
                                    }
                                } elseif (in_array($bl01['MBLB'], $rule8060)) {
                                    if (strtotime($bl01['CJSJ']) < strtotime($ssrq)) {
                                        if (strtotime($bl01['first_blsy_time']) < strtotime($ssrq)) {
                                            $iszqtyData['yes'][] = $bl01;
                                        } else {
                                            $iszqtyData['no'][] = $bl01;
                                        }
                                    }
                                } elseif (in_array($bl01['MBLB'], $rule8062)) {
                                    if (strtotime($bl01['ZXSJ']) < strtotime($ssrq)) {

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
                                                if (strtotime($bl01['ZXSJ']) < strtotime($ssrq)) {

                                                    if (strtotime($bl01['first_blsy_time']) < strtotime($ssrq)) {
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
                                                if (strtotime($bl01['CJSJ']) < strtotime($ssrq)) {

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

                                // 术者术前查房验证：独立判断，BLLB=294，时间在手术前24小时到手术开始时间范围内
                                if ($bl01['BLLB'] == '294') {
                                    $zxsj = $bl01['ZXSJ'] ?? '';
                                    if (!empty($zxsj) && strtotime($zxsj) >= strtotime($sqcfStartTime) && strtotime($zxsj) <= strtotime($sqcfEndTime)) {
                                        // 查询该病程的签名信息
                                        $blbh = $bl01['BLBH'] ?? '';
                                        $first_blsy_time = $bl01['first_blsy_time'] ?? '';
                                        if (!empty($blbh)) {
                                            $blsyList = EMR_BL_BLSY::query()
                                                ->where('BLBH', $blbh)
                                                ->where('FG_ACTIVE', 1)
                                                ->where('QMLX', 1)
                                                ->get()
                                                ->toArray();

                                            if (!empty($blsyList)) {
                                                $hasSurgeonSign = false;
                                                foreach ($blsyList as $blsy) {
                                                    $syys = $blsy['SYYS'] ?? '';
                                                    if (!empty($syys)) {
                                                        // 查询医生的base_code
                                                        $staff = Staff::query()->where('code', $syys)->first();
                                                        if (!empty($staff)) {
                                                            $baseCode = $staff->base_code ?? '';
                                                            // 判断是否是术者
                                                            if (!empty($baseCode) && !empty($surgeonCode) && $baseCode == $surgeonCode) {
                                                                $hasSurgeonSign = true;
                                                                break;
                                                            }
                                                        }
                                                    }
                                                }

                                                // 如果有术者签名，检查签名时间是否在范围内
                                                if ($hasSurgeonSign) {
                                                    if (!empty($first_blsy_time) && strtotime($first_blsy_time) >= strtotime($sqcfStartTime) && strtotime($first_blsy_time) <= strtotime($sqcfEndTime)) {
                                                        $isszcfData['yes'][] = $bl01;
                                                    } else {
                                                        $isszcfData['no'][] = $bl01;
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

                            // 显示术者术前查房结果
                            if (isset($isszcfData['yes']) && count($isszcfData['yes']) > 0) {
                                $isszcf = true;
                                foreach ($isszcfData['yes'] as $item) {
                                    $orderGroup['content'][] = [
                                        'status' => 1,
                                        'content' => '术者术前查房【' . $item['BLMC'] . '】'
                                    ];
                                    $orderGroup['content'][] = [
                                        'status' => 1,
                                        'content' => '首次签名时间【' . $item['first_blsy_time'] . '】'
                                    ];
                                }
                            } elseif (isset($isszcfData['no']) && count($isszcfData['no']) > 0) {
                                // 显示所有超时的
                                foreach ($isszcfData['no'] as $item) {
                                    $orderGroup['content'][] = [
                                        'status' => 0,
                                        'content' => '术者术前查房【' . $item['BLMC'] . '】'
                                    ];
                                    $orderGroup['content'][] = [
                                        'status' => 0,
                                        'content' => '首次签名时间【' . $item['first_blsy_time'] . '(超时)】'
                                    ];
                                }
                            } else {
                                $orderGroup['content'][] = [
                                    'status' => 0,
                                    'content' => '术者术前查房【无】'
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
                            if (env('APP_NAME') == 'dancheng' || env('APP_NAME') == 'ningxia') {
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

                    if ($issqxj && $isszcf && $iszqty && $isaqhc && $isssjl && $isshbc) {
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

                    $insert['AAA28'] = $value->AAA28;
                    $insert['AAA01'] = $value->AAA01;
                    $insert['AAC11N'] = $value->AAC11N;
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

        $feeService = null;
        $bl01Service = null;
        $yzbService = null;
        $bagl_11 = ZbBagl::getFirstById(11, true);
        $bagl_27 = ZbBagl::getFirstById(27, true);
        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母

        while (true) {
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);
            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                //删除
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['hzqjcgl_fm' => null, 'hzqjcgl_fz' => null, 'hzqjcgl_error' => null]);

                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }

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

                // 查询抢救费用（费用名称含"抢救"）（MySQL）
                $feeData = [json_decode(json_encode(
                    FeeDetailed::query()
                        ->where('AAA28', $ZYH)
                        ->where('FYMC', 'LIKE', '%抢救%')
                        ->limit(1000)
                        ->get()->toArray()
                ), true)];

                var_dump($feeData);
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

                    // 查询抢救医嘱（医嘱名称含"抢救"）（MySQL）
                    $yzbData = [json_decode(json_encode(
                        Yzb::query()
                            ->where('ZYH', $ZYH)
                            ->where('YZMC', 'LIKE', '%抢救%')
                            ->limit(1000)
                            ->get()->toArray()
                    ), true)];
                    var_dump($yzbData);

                    if (!empty($yzbData[0])) {
                        foreach ($yzbData[0] as $yzb) {
                            $orderGroup['content'][] = [
                                'status' => 1,
                                'content' => sprintf("医嘱【%s，%s】", $yzb['YZMC'], $yzb['KZSJ'])
                            ];

                            // 查询病程记录（病程内容含"抢救"但不含"死亡"，按首次签名时间升序）（MySQL）
                            $bcjlData = [json_decode(json_encode(
                                EMR_BL_BL01::query()
                                    ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                                    ->where('EMR_BL_BL01.JZHM', $ZYH)
                                    ->where('EMR_BL_BLXG.HJNR', 'LIKE', '%抢救%')
                                    ->where('EMR_BL_BLXG.HJNR', 'NOT LIKE', '%死亡%')
                                    ->orderBy('EMR_BL_BL01.first_blsy_time', 'asc')
                                    ->limit(1000)
                                    ->get()->toArray()
                            ), true)];
                            var_dump($bcjlData);

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
                    $insert['AAA28'] = $value->AAA28;
                    $insert['AAA01'] = $value->AAA01;
                    $insert['AAC11N'] = $value->AAC11N;
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
        $homeData = new HomeData();
        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母

        while (true) {
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);

            if ($page == 1) {
                echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, 'MED_REC_ID');
                echo $ZYH . $value->AAC01 . ' ' . $value->AAC11N . PHP_EOL;
                //删除
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['hzqjjsl_fm' => null, 'hzqjjsl_fz' => null, 'hzqjjsl_error' => null]);

                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }

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

                $qjjlBl01 = $homeData->getQjsj($ZYH);

                // 查询抢救费用
                $feeData = [];
                $feeDataAll = FeeDetailed::query()->where('AAA28', $ZYH)->get()->toArray();
                foreach ($feeDataAll as $fee) {
                    if (strpos($fee['FYMC'], '抢救费') !== false) {
                        $feeData[] = $fee;
                    }
                }
                $orderGroup = ['status' => 0, 'content' => []];
                if (!empty($feeData)) {
                    echo '抢救费用条数：' . count($feeData) . PHP_EOL;
                    echo '抢救记录条数：' . count($qjjlBl01) . PHP_EOL;
                    echo '有抢救费用：' . $feeData[0]['FYMC'] . PHP_EOL;
                    $insert['hzqjjsl_fm'] = 1;
                    $allGroupsValid = true; // 用于跟踪所有组是否都满足条件

                    // 添加费用信息
                    $orderGroup['content'][] = [
                        'status' => 1,
                        'content' => sprintf("费用【%s】", $feeData[0]['FYMC'])
                    ];

                    $groupValid = false; // 用于跟踪当前组是否满足条件
                    if (!empty($qjjlBl01)) {
                        foreach ($qjjlBl01 as $record) {

                            // 抢救时间
                            $orderGroup['content'][] = [
                                'status' => 1,
                                'content' => sprintf("抢救时间【%s】", $record['qjsj'])
                            ];
                            // 抢救时间
                            $orderGroup['content'][] = [
                                'status' => 1,
                                'content' => sprintf("抢救记录【%s】", $record['BLMC'])
                            ];

                            $timeDiff = strtotime($record['qjsj']) - strtotime($record['ZXSJ']);
                            if ($timeDiff < 6 * 60 * 60) {
                                $timeDiff1 = strtotime($record['qjsj']) - strtotime($record['first_blsy_time']);
                                if ($timeDiff1 < 6 * 60 * 60) {
                                    $insert['hzqjjsl_fz'] = 1;
                                    $orderGroup['status'] = 1;
                                    $orderGroup['content'][] = [
                                        'status' => 1,
                                        'content' => sprintf(
                                            "抢救记录【%s】（6小时内）",
                                            $record['BLMC']
                                        )
                                    ];
                                } else {
                                    $orderGroup['content'][] = [
                                        'status' => 1,
                                        'content' => sprintf(
                                            "抢救记录【%s】（超6小时）",
                                            $record['BLMC']
                                        )
                                    ];
                                }
                            } else {
                                $orderGroup['content'][] = [
                                    'status' => 1,
                                    'content' => sprintf(
                                        "抢救记录标题时间【%s】（超6小时）",
                                        $record['BLMC']
                                    )
                                ];
                            }
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

                    // 只有当所有组都满足条件时，才设置分子为1
                    if ($allGroupsValid) {
                        $insert['hzqjjsl_fz'] = 1;
                    }
                }

                if ($insert['hzqjjsl_fm'] > 0) {
                    $insert['hzqjjsl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                    Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                    $insert['AAA28'] = $value->AAA28;
                    $insert['AAA01'] = $value->AAA01;
                    $insert['AAC11N'] = $value->AAC11N;
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
        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母
        $ryZbBagl = ZbBagl::query()->where('id', 16)->first(['id', 'keyword', 'table_name', 'table_field']); //入院记录
        $rcZbBagl = ZbBagl::query()->where('id', 17)->first(['id', 'keyword', 'table_field']); //日常病程记录
        $esService = null;

        while (true) {
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {

                $ZYH = data_get($value, 'MED_REC_ID');
                //删除
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['bhlfzbl_fm' => null, 'bhlfzbl_fz' => null, 'bhlfzbl_error' => null]);

                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }

                echo $ZYH . PHP_EOL;
                $errorContent = [];
                $insert = [
                    'zyh' => $ZYH,
                    'bhlfzbl_fz' => 1,
                    'bhlfzbl_fm' => 1, // 分母默认为1
                    'bhlfzbl_error' => null,
                    'AAC11N' => $value->AAC11N,
                    'AEE03' => !empty($value->AEE03) ? $value->AEE03 : '',
                    'AAC01_YEAR' => date('Y', strtotime($value->AAC01)),
                    'AAC01_MONTH' => date('m', strtotime($value->AAC01)),
                    'AAC01' => $value->AAC01
                ];

                $surgeryGroup = [
                    'status' => 1,
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
                        $insert['bhlfzbl_fz'] = 0;
                        $surgeryGroup['status'] = 0;
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "首次病程记录【{$bllb294295Row->BLMC}】和入院记录【{$ryRow->BLMC}】90%雷同"
                        ];
                    }
                }

                // 日常病程记录比对（模板类别属 rcZbBagl 关键词，取病程内容与名称）（MySQL）
                $result = [json_decode(json_encode(
                    EMR_BL_BL01::query()
                        ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                        ->where('EMR_BL_BL01.JZHM', $ZYH)
                        ->whereIn('EMR_BL_BL01.MBLB', explode(',', $rcZbBagl['keyword']))
                        ->get()->toArray()
                ), true)];

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
                                $insert['bhlfzbl_fz'] = 0;
                                $surgeryGroup['status'] = 0;
                                $surgeryGroup['content'][] = [
                                    'status' => 0,
                                    'content' => "病程记录【{$vv['BLMC']}】和病程【{$result[0][$i]['BLMC']}】90%雷同"
                                ];
                            }
                        }
                    }
                }


                $errorContent[] = $surgeryGroup;
                $insert['bhlfzbl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                $insert['AAA28'] = $value->AAA28;
                $insert['AAA01'] = $value->AAA01;
                $insert['AAC11N'] = $value->AAC11N;
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
        $cyzs = ZbBagl::getFirstById(1, true);

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

        $zyHcmxEsService = null;

        while (true) {
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);

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
                //删除
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['yscf_fm' => null, 'yscf_fz' => null, 'yscf_error' => null]);

                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }

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

                $wardRoundGroup = [
                    'status' => 0,
                    'content' => []
                ];

                // 1. 获取护士分床数据（动态表 bagl_12->table_name + 附加条件）（MySQL）
                $zyHcmxQuery = DB::table($bagl_12->table_name)->where($bagl_12->MED_REC_ID, $ZYH);
                foreach ($baglWhere as $cond) {
                    $term = $cond['term'];
                    $zyHcmxQuery->where(key($term), reset($term));
                }
                $zyHcmxData = [json_decode(json_encode($zyHcmxQuery->get()->toArray()), true)];

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
                                    $blsy = EMR_BL_BLSY::query()->where('BLBH', $record['BLBH'])->where('QMLX', 1)->pluck('SYYS')->toArray();
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
                        $insert['AAA28'] = $value->AAA28;
                        $insert['AAA01'] = $value->AAA01;
                        $insert['AAC11N'] = $value->AAC11N;
                    }
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
        $cyzs = ZbBagl::getFirstById(1, true); //备血医嘱
        $bagl_22 = ZbBagl::getFirstById(22, true); //输血医嘱
        $key20039 = RuleWordMap::getArrayById(20039); //输血医嘱
        // 处理逗号分隔的关键词
        if (strpos($bagl_22->keyword, ',') !== false) {
            $bagl_22_keywords = explode(',', $bagl_22->keyword);
        } else {
            $bagl_22_keywords = [$bagl_22->keyword];
        }
        $bagl_23 = ZbBagl::getFirstById(23, true); //输血效果评估
        $bagl_24 = ZbBagl::getFirstById(24, true); //输血病程记录MBLB
        $bagl_24 = strpos($bagl_24->keyword, ',') !== false ? explode(',', $bagl_24->keyword) : $bagl_24->keyword;
        $bagl_26 = ZbBagl::getFirstById(26, true); //输血效果评估2
        $bagl_11 = ZbBagl::getFirstById(11, true); //病程记录
        $feeService = null;
        $bl01Service = null;
        $yzbService = null;
        $zb34 = ZbBagl::query()->where('id', 34)->value('keyword');
        if (strpos($zb34, ',') !== false) {
            $zb34 = explode(',', $zb34);
        } else {
            $zb34 = [$zb34];
        }

        while (true) {
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);
            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = $value->MED_REC_ID;
                //删除
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['lcyx_fm' => null, 'lcyx_fz' => null, 'lcyx_error' => null]);

                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }

                // 检查是否有输血费用(分母)（费用名称前缀匹配 bagl_19 之一）（MySQL）
                $feeData = [json_decode(json_encode(
                    FeeDetailed::query()
                        ->where('AAA28', $ZYH)
                        ->where(function ($q) use ($bagl_19_keywords) {
                            foreach ($bagl_19_keywords as $keyword) {
                                $q->orWhere('FYMC', 'LIKE', $keyword . '%');
                            }
                        })
                        ->limit(1000)
                        ->get()->toArray()
                ), true)];

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


                // 获取所有输血医嘱（医嘱名称含 bagl_22/zb34 关键词之一，按开嘱时间升序）（MySQL）
                $yzbShouldKeywords = $bagl_22_keywords;
                if (!empty($zb34)) {
                    foreach ($zb34 as $item) {
                        $yzbShouldKeywords[] = $item;
                    }
                }
                $yzbData = [json_decode(json_encode(
                    Yzb::query()
                        ->where('ZYH', $ZYH)
                        ->where(function ($q) use ($yzbShouldKeywords) {
                            foreach ($yzbShouldKeywords as $keyword) {
                                $q->orWhere('YZMC', 'LIKE', '%' . $keyword . '%');
                            }
                        })
                        ->orderBy('KZSJ', 'asc')
                        ->limit(1000)
                        ->get()->toArray()
                ), true)];

                $errorContent = [];
                if (!empty($yzbData[0])) {
                    foreach ($yzbData[0] as $yz) {
                        $surgeryGroup = [
                            'status' => 0,
                            'content' => []
                        ];

                        // 1. 检查医嘱时间之前是否有输血同意书（模板类别=bagl_20，执行时间早于开嘱时间）（MySQL）
                        $tysData = [json_decode(json_encode(
                            EMR_BL_BL01::query()
                                ->where('JZHM', $ZYH)
                                ->where('MBLB', $bagl_20->keyword)
                                ->where('ZXSJ', '<=', $yz['KZSJ'])
                                ->limit(1000)
                                ->get()->toArray()
                        ), true)];

                        if (empty($tysData[0])) {
                            $tysData[0] = EMR_BL_BL01::query()->where('JZHM', $ZYH)
                                ->where('BLLB', 329)
                                ->where(function ($query) use ($key20039) {
                                    foreach ($key20039 as $item) {
                                        $query->orWhere('BLMC', 'like', '%' . $item . '%');
                                    }
                                })
                                ->get()->toArray();
                        }

                        $surgeryGroup['content'][] = [
                            'status' => !empty($tysData[0]) ? 1 : 0,
                            'content' => sprintf("输血同意书【%s】", !empty($tysData[0]) ? $tysData[0][0][$bagl_11->table_field] : '无')
                        ];

                        // 2. 检查48小时内是否有病程记录且包含效果评估
                        $endTime = date('Y-m-d 23:59:59', strtotime($yz['KZSJ']) + 24 * 3600);
                        $must = [
                            ["term" => [$bagl_11->MED_REC_ID => $ZYH]],
                            ["terms" => ['MBLB' => $bagl_24]],
                            [
                                'range' => [
                                    'ZXSJ' => [
                                        'gte' => $yz['KZSJ'],
                                        'lte' => $endTime
                                    ]
                                ]
                            ]
                        ];
                        // 2. 检查48小时内是否有病程记录且包含效果评估（模板类别属 bagl_24，执行时间在开嘱后24h内）（MySQL）
                        $bcjlData = [json_decode(json_encode(
                            EMR_BL_BL01::query()
                                ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                                ->where('EMR_BL_BL01.JZHM', $ZYH)
                                ->whereIn('EMR_BL_BL01.MBLB', (array)$bagl_24)
                                ->where('EMR_BL_BL01.ZXSJ', '>=', $yz['KZSJ'])
                                ->where('EMR_BL_BL01.ZXSJ', '<=', $endTime)
                                ->limit(1000)
                                ->get()->toArray()
                        ), true)];

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

                        // 3. 新增：检查手术记录中是否有术中用血记录
                        $hasSurgeryBlood = 0;
                        $surgeryBloodMsg = '';
                        $surgeryRecordList = []; // 存储所有手术记录

                        // 查询手术记录：MBLB=306，时间在医嘱开始时间到开始时间+48小时内
                        $surgeryEndTime = date('Y-m-d H:i:s', strtotime($yz['KZSJ']) + 24 * 3600);
                        $must = [
                            ["term" => [$bagl_11->MED_REC_ID => $ZYH]],
                            ["term" => ['MBLB' => '306']],
                            [
                                'range' => [
                                    $bagl_11->table_field => [
                                        'gte' => $yz['KZSJ'],
                                        'lte' => $surgeryEndTime
                                    ]
                                ]
                            ]
                        ];
                        // 查询手术记录：MBLB=306，时间在医嘱开始时间到开始时间+48小时内（MySQL）
                        $surgeryData = [json_decode(json_encode(
                            EMR_BL_BL01::query()
                                ->where('JZHM', $ZYH)
                                ->where('MBLB', '306')
                                ->where($bagl_11->table_field, '>=', $yz['KZSJ'])
                                ->where($bagl_11->table_field, '<=', $surgeryEndTime)
                                ->limit(1000)
                                ->get()->toArray()
                        ), true)];

                        if (!empty($surgeryData[0])) {
                            foreach ($surgeryData[0] as $surgery) {
                                $blbh = $surgery['BLBH'] ?? '';
                                $blmc = $surgery['BLMC'] ?? '';
                                $hasBlood = false;

                                if (!empty($blbh)) {
                                    // 通过BLBH关联查询EMR_BL_BLXG获取HJNR
                                    $blxgData = EMR_BL_BLXG::query()
                                        ->where('BLBH', $blbh)
                                        ->get()
                                        ->toArray();

                                    if (!empty($blxgData)) {
                                        foreach ($blxgData as $blxg) {
                                            $hjnr = $blxg['HJNR'] ?? '';
                                            if (!empty($hjnr)) {
                                                // 删除【输血：无】和【输血：否】
                                                $hjnr = str_replace('输血：无', '', $hjnr);
                                                $hjnr = str_replace('输血：否', '', $hjnr);

                                                // 检查是否还包含【输血：】
                                                if (strpos($hjnr, '输血：') !== false) {
                                                    $hasBlood = true;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }

                                // 记录每个手术记录的状态
                                $surgeryRecordList[] = [
                                    'blmc' => $blmc,
                                    'hasBlood' => $hasBlood
                                ];

                                // 只要有一个手术记录有术中用血，就标记为有
                                if ($hasBlood) {
                                    $hasSurgeryBlood = 1;
                                }
                            }
                        }

                        // 显示手术记录结果
                        if (empty($surgeryRecordList)) {
                            // 完全没有查到手术记录，不验证这个条件，直接算通过
                            $hasSurgeryBlood = 1;
                        } elseif ($hasSurgeryBlood) {
                            // 有术中用血的记录，只显示有的
                            foreach ($surgeryRecordList as $record) {
                                if ($record['hasBlood']) {
                                    $surgeryGroup['content'][] = [
                                        'status' => 1,
                                        'content' => sprintf("手术记录【%s】有术中用血", $record['blmc'])
                                    ];
                                }
                            }
                        } else {
                            // 全都没有术中用血，显示所有没有的
                            foreach ($surgeryRecordList as $record) {
                                $surgeryGroup['content'][] = [
                                    'status' => 0,
                                    'content' => sprintf("手术记录【%s】无术中用血", $record['blmc'])
                                ];
                            }
                        }

                        $surgeryGroup['status'] = !empty($tysData[0]) && $hasEffect && $hasSurgeryBlood ? 1 : 0;
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
                        $insert['AAA28'] = $value->AAA28;
                        $insert['AAA01'] = $value->AAA01;
                        $insert['AAC11N'] = $value->AAC11N;
                    }
                }
            }
        }

        return true;
    }

    protected function feeDetailed1($feeService, $zyhList, $bagl)
    {
        // 查询住院收费明细中费用名称包含关键字、且住院号在列表内的记录（MySQL）
        $feeData = FeeDetailed::query()
            ->whereIn('AAA28', $zyhList)
            ->where('FYMC', 'LIKE', '%' . $bagl->keyword . '%')
            ->get(['AAA28'])->toArray();
        $zyhData = [];
        if (!empty($feeData)) {
            foreach ($feeData as $value) {
                if (!in_array($value['AAA28'], $zyhData)) {
                    $zyhData[] = $value['AAA28'];
                }
            }
        }

        return $zyhData;
    }

    protected function bl01Value($bl01Service, $MED_REC_ID, $bagl)
    {
        $bagl_11 = ZbBagl::getFirstById(11, true);
        // 查询病程记录（模板类别 MBLB=关键字，排除作废 BLZT=9），取 bagl_11 配置字段（MySQL）
        $bl01Data = EMR_BL_BL01::query()
            ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
            ->where('EMR_BL_BL01.JZHM', $MED_REC_ID)
            ->where('EMR_BL_BL01.MBLB', $bagl->keyword)
            ->where('EMR_BL_BL01.BLZT', '<>', 9)
            ->get()->toArray();
        $sxtys = !empty($bl01Data[0][$bagl_11->table_field]) ? $bl01Data[0][$bagl_11->table_field] : ''; //id 11

        return $sxtys;
    }

    protected function yzb1($yzbService, $MED_REC_ID, $keyValue, $range)
    {
        // 查询医嘱表（医嘱名称包含关键字，MySQL）
        $yzbQuery = Yzb::query()
            ->where('ZYH', $MED_REC_ID)
            ->where('YZMC', 'LIKE', '%' . $keyValue->keyword . '%');

        // 如果传入了时间范围（ES 的 range 条件），转换为对应字段区间过滤
        if ($range && !empty($range['range'])) {
            foreach ($range['range'] as $rangeField => $rangeValue) {
                if (isset($rangeValue['from'])) {
                    $yzbQuery->where($rangeField, '>=', $rangeValue['from']);
                }
                if (isset($rangeValue['to'])) {
                    $yzbQuery->where($rangeField, '<=', $rangeValue['to']);
                }
            }
        }

        if ($keyValue->id == 22) {
            $yzbData = $yzbQuery->orderBy('KZSJ', 'desc')->get()->toArray();
            $bxCount = !empty($yzbData) ? $yzbData[0]['KZSJ'] : '';
        } else {
            $yzbData = $yzbQuery->get()->toArray();
            $bxCount = !empty($yzbData) ? 1 : 0;
        }

        return $bxCount;
    }

    protected function lcyxBcjl3($bl01Service, $MED_REC_ID, $keyValue, $bagl = null)
    {
        $bagl_11 = ZbBagl::getFirstById(11, true);
        $bagl_24 = ZbBagl::getFirstById(24, true);
        // 查询病程记录（模板类别 MBLB=关键字，可选病程内容包含 keyValue，排除作废 BLZT=9）（MySQL）
        $mblbKeyword = $bagl ? $bagl->keyword : $bagl_24->keyword;
        $bl01Query = EMR_BL_BL01::query()
            ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
            ->where('EMR_BL_BL01.JZHM', $MED_REC_ID)
            ->where('EMR_BL_BL01.MBLB', $mblbKeyword)
            ->where('EMR_BL_BL01.BLZT', '<>', 9);
        if ($keyValue) {
            $bl01Query->where('EMR_BL_BLXG.HJNR', 'LIKE', '%' . $keyValue . '%');
        }
        $bl01Data = $bl01Query->get()->toArray();
        $sxbcjl = [];
        if (!empty($bl01Data)) {
            foreach ($bl01Data as $value) {
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

                // 查询本周期内的查房记录（BLLB=294，创建时间在周期内）（MySQL）
                $records = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->where('BLLB', 294)
                    ->where($bagl_11->table_field, '>=', date('Y-m-d H:i:s', $currentTime))
                    ->where($bagl_11->table_field, '<=', date('Y-m-d H:i:s', $periodEnd))
                    ->get(['BLBH', $bagl_11->table_field])->toArray();

                if (!empty($records)) {
                    $zheng = $fu = $zhong = [];
                    foreach ($records as $record) {
                        $blsy = EMR_BL_BLSY::query()
                            ->where('BLBH', '=', $record['BLBH'])
                            ->where('QMLX', 1)
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

        // 查询手术记录（病程内容同时包含"手术记录"和手术名称）（MySQL）
        $surgeryRecord = EMR_BL_BL01::query()
            ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
            ->where('EMR_BL_BL01.JZHM', $ZYH)
            ->where('EMR_BL_BLXG.HJNR', 'LIKE', '%手术记录%')
            ->where('EMR_BL_BLXG.HJNR', 'LIKE', '%' . $surgeryName . '%')
            ->get(['EMR_BL_BL01.BLBH', 'EMR_BL_BL01.operation_time', 'EMR_BL_BL01.operation_handler', 'EMR_BL_BL01.operation_handler_code'])->toArray();

        if (empty($surgeryRecord)) {
            $content[] = [
                'status' => 0,
                'content' => "手术【{$surgeryName}】记录缺失"
            ];
            return $content;
        }

        $surgery = $surgeryRecord[0];

        // 查询麻醉记录（住院号+术前诊断名称匹配）（MySQL）
        $anesthesiaRecord = DB::table('mzjl')
            ->where('HOSPIZATIONID', $ZYH)
            ->where('PREOPERATIONNAME', $surgeryName)
            ->get(['OPERATESTARTTIME', 'OPERATEENDTIME'])->toArray();

        if (empty($anesthesiaRecord)) {
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

        // 分子 - 查询出院记录创建时间（BLLB=指定类别，排除作废 BLZT=9）（MySQL）
        $bl01Data = EMR_BL_BL01::query()
            ->where('JZHM', $ZYH)
            ->where('BLLB', $BLLB)
            ->where('BLZT', '<>', 9)
            ->get()->toArray();
        $cjsj = !empty($bl01Data[0][$key_field]) ? $bl01Data[0][$key_field] : '';

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
        // 查询抢救医嘱（医嘱名称包含"抢救"）（MySQL）
        $yzbData = Yzb::query()
            ->where('ZYH', $ZYH)
            ->where('YZMC', 'LIKE', '%抢救%')
            ->get(['YZMC', 'KZSJ'])->toArray();
        $yzbList = [];
        if (!empty($yzbData)) {
            foreach ($yzbData as $value) {
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
        // 查询抢救收费明细（费用名称包含"抢救"）（MySQL）
        $feeData = FeeDetailed::query()
            ->where('AAA28', $ZYH)
            ->where('FYMC', 'LIKE', '%抢救%')
            ->get(['FYMC', 'JFRQ'])->toArray();
        $feeList = [];
        if (!empty($feeData)) {
            foreach ($feeData as $value) {
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
        // 查询抢救病程记录（病程内容含"抢救"但不含"死亡"）（MySQL）
        $bl01Data = EMR_BL_BL01::query()
            ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
            ->where('EMR_BL_BL01.JZHM', $ZYH)
            ->where('EMR_BL_BLXG.HJNR', 'LIKE', '%抢救%')
            ->where('EMR_BL_BLXG.HJNR', 'NOT LIKE', '%死亡%')
            ->get()->toArray();
        $bl01List = [];
        if (!empty($bl01Data)) {
            foreach ($bl01Data as $value) {
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
        $vjyService = null;
        $bl01Service = null;

        // 病程记录培养关键词（rule_word_map id=8211，英文逗号分割，支持后期扩展）
        $ruleMap8211 = RuleWordMap::query()->where('id', '=', 8211)->value('keyword');
        $pyKeywords = !empty($ruleMap8211)
            ? array_filter(array_map('trim', explode(',', $ruleMap8211)))
            : ['培养'];

        while (true) {
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                //删除旧数据
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['xjpyjcjl_fm' => null, 'xjpyjcjl_fz' => null, 'xjpyjcjl_error' => null]);

                echo $value->MED_REC_ID . PHP_EOL;
                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }

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

                // 分母：住院收费明细中包含细菌涂片检查等收费项目，能查到即算分母
                $feeDetailedList = FeeDetailed::query()->where('AAA28', $ZYH)->get()->toArray();
                $fymc = '';
                foreach ($feeDetailedList as $item) {
                    if (strpos($item['FYMC'], '一般细菌涂片检查') !== false || strpos($item['FYMC'], '抗酸杆菌涂片检查') !== false) {
                        $fymc = $item['FYMC'];
                    }
                }
                if (empty($fymc)) {
                    // 收费项目查不到，不计入分母
                    continue;
                }

                // 分母：收费项目能查到即算 1
                $insert['xjpyjcjl_fm'] = 1;

                // 分子：查细菌培养检查医嘱（排除 YZZT=3）
                $yzbList = Yzb::query()
                    ->where('ZYH', $ZYH)
                    ->get(['YZMC','KZSJ'])->toArray();
                $yzb = [];
                foreach ($yzbList as $item) {
                    if (strpos($item['YZMC'], '细菌培养') !== false) {
                        $yzb[] = ['YZMC' => $item['YZMC'],'KZSJ' => $item['KZSJ']];
                    }
                }

                // 检验报告单（药敏记录）
                $ymresultList = $this->getYmresultList($vjyService, $ZYH);

                // 病程记录
                $BL01 = EMR_BL_BL01::query()
                    ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                    ->where('EMR_BL_BL01.JZHM', $ZYH)
                    ->get(['EMR_BL_BL01.BLBH', 'EMR_BL_BL01.ZXSJ', 'EMR_BL_BL01.first_blsy_time', 'EMR_BL_BL01.BLMC', 'EMR_BL_BLXG.HJNR'])->toArray();

                $allMatch = true;

                if (empty($yzb)) {
                    // 没有查到细菌培养检查的医嘱
                    $errorContent[] = [
                        'status' => 0,
                        'content' => [
                            ['status' => 1, 'content' => '费用【' . $fymc . '】'],
                            ['status' => 0, 'content' => '医嘱【无细菌培养检查的医嘱】'],
                        ]
                    ];
                    $allMatch = false;
                } else {
                    // 逐个医嘱判断：查对应报告、查病程
                    foreach ($yzb as $yzmc) {
                        $group = [
                            'status' => 1,
                            'content' => []
                        ];
                        $group['content'][] = ['status' => 1, 'content' => '费用【' . $fymc . '】'];
                        $group['content'][] = ['status' => 1, 'content' => '医嘱【' . $yzmc['YZMC'] . '】'];
                        $group['content'][] = ['status' => 1, 'content' => '开嘱时间【' . $yzmc['KZSJ'] . '】'];

                        // 查该医嘱对应的药敏报告
                        $reportMatched = null;
                        foreach ($ymresultList as $ymItem) {
                            if ($ymItem['EXAMINAIM'] == $yzmc['YZMC'] && $ymItem['BGSJ'] >= $yzmc['KZSJ']) {
                                $reportMatched = $ymItem;
                                break;
                            }
                        }

                        if (empty($reportMatched)) {
                            $group['content'][] = ['status' => 0, 'content' => '报告单【无对应报告】'];
                            $group['status'] = 0;
                            $allMatch = false;
                            $errorContent[] = $group;
                            continue;
                        }

                        $group['content'][] = ['status' => 1, 'content' => '报告单【' . $reportMatched['XJMC'] . '】'];
                        $group['content'][] = ['status' => 1, 'content' => '报告时间【' . $reportMatched['BGSJ'] . '】'];

                        // 查病程记录：报告时间后24小时内，含培养关键词或检验名称
                        $flag = false;
                        $blmc = '';
                        $matchedKeyword = '';
                        foreach ($BL01 as $blItem) {
                            if ((strtotime($blItem['ZXSJ']) <= strtotime($reportMatched['BGSJ']) + 86400) && (strtotime($blItem['ZXSJ']) >= strtotime($reportMatched['BGSJ']))) {
                                $matched = '';
                                foreach ($pyKeywords as $kw) {
                                    if ($kw !== '' && strpos($blItem['HJNR'], $kw) !== false) {
                                        $matched = $kw;
                                        break;
                                    }
                                }
                                if ($matched === '' && !empty($reportMatched['XJMC']) && strpos($blItem['HJNR'], $reportMatched['XJMC']) !== false) {
                                    $matched = $reportMatched['XJMC'];
                                }
                                if ($matched !== '') {
                                    $flag = true;
                                    $blmc = $blItem['BLMC'];
                                    $matchedKeyword = $matched;
                                    break;
                                }
                            }
                        }

                        if ($flag) {
                            $group['content'][] = ['status' => 1, 'content' => '病程记录【' . $blmc . '】含"' . $matchedKeyword . '"'];
                        } else {
                            $group['content'][] = ['status' => 0, 'content' => '病程记录【无培养结果及分析】'];
                            $group['status'] = 0;
                            $allMatch = false;
                        }

                        $errorContent[] = $group;
                    }
                }

                // 判断分子
                if ($allMatch && $insert['xjpyjcjl_fm'] > 0) {
                    $insert['xjpyjcjl_fz'] = 1;
                }

                // 保存结果
                if ($insert['xjpyjcjl_fm'] > 0) {
                    $insert['xjpyjcjl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                    Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);

                    $insert['AAA28'] = $value->AAA28;
                    $insert['AAA01'] = $value->AAA01;
                    $insert['AAC11N'] = $value->AAC11N;
                }
            }
        }

        return true;
    }

    /**
     * 获取检验报告单列表
     */
    protected function getYmresultList($vjyService, $ZYH)
    {

        EsSaveService::vjmgsymresult($ZYH);
        $ymresultList = [];
        $vjyData = V_JMGS_YMresult::query()
            ->where('XJMC', 'like', '%菌%')
            ->where('ZYH', $ZYH)
            ->where('STAYHOSPITALMODE', 2)
            ->get()->toArray();
        if (!empty($vjyData)) {
            $arr = [];
            foreach ($vjyData as $value) {
                if ($value['XJMC'] && $value['EXAMINAIM'] && !in_array($value['EXAMINAIM'], $arr)) {
                    $arr[] = $value['EXAMINAIM'];
                    $ymresultList[] = [
                        'XJMC' => $value['XJMC'],
                        'YMJG' => $value['YMJG'],
                        'BGSJ' => $value['BGSJ'],
                        'EXAMINAIM' => $value['EXAMINAIM'],
                        'CJSJ' => $value['CJSJ']
                    ];
                }
            }
        }

        return $ymresultList;
    }

    /**
     * 获取细菌名称列表
     */
    protected function getXjmcList($vjyService, $ZYH)
    {
        return $this->getYmresultList($vjyService, $ZYH);
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

        // 查询医嘱（医嘱名称包含任一检查目标关键字，should + minimumShouldMatch(1)）（MySQL）
        $yzbData = Yzb::query()
            ->where('ZYH', $ZYH)
            ->where(function ($query) use ($EXAMINAIM_ARR) {
                foreach ($EXAMINAIM_ARR as $value) {
                    $query->orWhere('YZMC', 'LIKE', '%' . $value . '%');
                }
            })
            ->get(['YZMC'])->toArray();
        $yzmc = !empty($yzbData) ? $yzbData[0]['YZMC'] : '';

        return $yzmc;
    }

    /**
     * 查询病程记录
     */
    protected function getBl01Value($bl01Service, $ZYH, $keyValue, $range = [])
    {
        // 查询病程记录（BLLB=294 且病程内容包含关键字，排除作废 BLZT=9）
        $bl01Query = EMR_BL_BL01::query()
            ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
            ->where('EMR_BL_BL01.JZHM', $ZYH)
            ->where('EMR_BL_BL01.BLLB', 294)
            ->where('EMR_BL_BLXG.HJNR', 'LIKE', '%' . $keyValue . '%')
            ->where('EMR_BL_BL01.BLZT', '<>', 9);

        // 如果传入了执行时间范围（ES 的 range 条件），转换为 ZXSJ 区间过滤
        if ($range && !empty($range['range'])) {
            foreach ($range['range'] as $rangeField => $rangeValue) {
                if (isset($rangeValue['from'])) {
                    $bl01Query->where('EMR_BL_BL01.' . $rangeField, '>=', $rangeValue['from']);
                }
                if (isset($rangeValue['to'])) {
                    $bl01Query->where('EMR_BL_BL01.' . $rangeField, '<=', $rangeValue['to']);
                }
            }
        }

        $bl01Data = $bl01Query->get(['EMR_BL_BL01.ZXSJ'])->toArray();
        $ZXSJ = !empty($bl01Data[0]['ZXSJ']) ? $bl01Data[0]['ZXSJ'] : '';

        return $ZXSJ;
    }

    /**
     * 植入物相关记录符合率
     */
    public function zrw($zyh, $start, $end)
    {
        $page = 1;

        $cyzs = ZbBagl::getFirstById(1, true); //出院总数 分母
        $feeService = null;
        $bl01Service = null;

        // 获取所有植入物信息
        $implantsList = \App\Model\Implants::query()->get()->toArray();

        while (true) {
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                //删除旧数据
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['zrw_fm' => null, 'zrw_fz' => null, 'zrw_error' => null]);

                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }

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
                $feeData = FeeDetailed::query()->where('AAA28', $ZYH)->get(['FYMC', 'FYSL'])->toArray();

                $fymcList = [];
                $fyData = [];
                if (!empty($feeData)) {
                    foreach ($feeData as $feeValue) {
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
                foreach ($implantsList as $item) {
                    $name = $item['name'];
                    foreach ($fyData as $val) {
                        if ($name == '连接管') {
                            if ($val['FYMC'] == $name && !in_array($name, $fymcArr)) {
                                $fymcArr[] = $name;
                                $sfxmContent[] = [
                                    'status' => 1,
                                    'content' => '商品名称：' . $name . '，数量：' . $val['FYSL']
                                ];
                            }
                        } elseif (stripos($val['FYMC'], $name) !== false && !in_array($name, $fymcArr)) {
                            $fymcArr[] = $name;
                            $sfxmContent[] = [
                                'status' => 1,
                                'content' => '商品名称：' . $name . '，数量：' . $val['FYSL']
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

                // 分子 - 查询手术记录（BLLB=303）- 合并原 bl01Value 方法逻辑（MySQL）
                $bl01Data = [json_decode(json_encode(
                    EMR_BL_BL01::query()
                        ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                        ->where('EMR_BL_BL01.JZHM', $ZYH)
                        ->where('EMR_BL_BL01.BLLB', 303)
                        ->limit(1000)
                        ->get()->toArray()
                ), true)];

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
                // 分子 - 病程记录（BLLB=294，排除作废 BLZT=9）（MySQL）
                $bl01Data = [json_decode(json_encode(
                    EMR_BL_BL01::query()
                        ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                        ->where('EMR_BL_BL01.JZHM', $ZYH)
                        ->where('EMR_BL_BL01.BLLB', 294)
                        ->where('EMR_BL_BL01.BLZT', '<>', 9)
                        ->limit(1000)
                        ->get()->toArray()
                ), true)];

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
                    $insert['AAA28'] = $value->AAA28;
                    $insert['AAA01'] = $value->AAA01;
                    $insert['AAC11N'] = $value->AAC11N;
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
        $yzbService = null;
        $publicService = new PublicService();

        while (true) {
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                //删除旧数据
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['cdl_fm' => null, 'cdl_fz' => null, 'cdl_error' => null]);


                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }

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
                    $brry = ZY_BRRY::query()->where('ZYH', $ZYH)->get()->toArray();
                    foreach ($baReceive as $val) {
                        if ($val['cysj'] == $value->AAC01 || $val['zycs'] == $brry[0]['ZYCS']) {
                            $MaxCheckTime = $val['MaxCheckTime'];
                            break;
                        }
                    }
                }

                // 查询出院医嘱（YDYZLB=303）- 合并原 getYzbDataEs 方法逻辑
                $cyNumerator = 0;
                $cyIsData = 0;
                $cyError = '';
                $yzbData = Yzb::query()->where('ZYH', $ZYH)->where(function ($query) {
                    $query->where('YZMC', 'like', '%出院%');
                })->get()->toArray();

                $cyXZJDSJ = !empty($yzbData[0]) ? $yzbData[0]['XZJDSJ'] : '';

                if ($cyXZJDSJ) {
                    $cyIsData = 1;
                    $mainGroup['content'][] = [
                        'status' => 1,
                        'content' => '出院时间【' . $cyXZJDSJ . '】'
                    ];

                    if ($MaxCheckTime) {
                        // 获取2日后时间（不包含节假日、休息日）
                        $XZJDSJ_END = $publicService->getWorkDays($cyXZJDSJ, 2);
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

                $yzbData = Yzb::query()->where('ZYH', $ZYH)->where(function ($query) {
                    $query->where('YZMC', 'like', '%死亡%');
                })->get()->toArray();
                $swXZJDSJ = !empty($yzbData[0]) ? $yzbData[0]['XZJDSJ'] : '';

                if ($swXZJDSJ) {
                    $swIsData = 1;
                    $mainGroup['content'][] = [
                        'status' => 1,
                        'content' => '死亡时间【' . $swXZJDSJ . '】'
                    ];

                    if ($MaxCheckTime) {
                        // 获取2日后时间（不包含节假日、休息日）
                        $XZJDSJ_END = $publicService->getWorkDays($swXZJDSJ, 2);
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
                    $mainGroup['content'][] = [
                        'status' => 0,
                        'content' => $finalError
                    ];
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
                $insert['AAA28'] = $value->AAA28;
                $insert['AAA01'] = $value->AAA01;
                $insert['AAC11N'] = $value->AAC11N;
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

        while (true) {
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                //查询brry的AAB01和AAC01
                $brry = ZY_BRRY::query()->where('ZYH', $ZYH)->get()->toArray();
                $AAB01 = !empty($brry[0]['AAB01']) ? $brry[0]['AAB01'] : '';
                $AAC01 = !empty($brry[0]['AAC01']) ? $brry[0]['AAC01'] : '';
                if (empty($AAB01) || empty($AAC01)) {
                    continue;
                }
                //计算住院天数，天为单位
                $hospitalDays = ceil((strtotime($AAC01) - strtotime($AAB01)) / (24 * 3600));
                //删除旧数据
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['gdwzl_fm' => null, 'gdwzl_fz' => null, 'gdwzl_error' => null]);

                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }

                $AAA28 = $value->AAA28;
                $AAA29 = $value->AAA29;
                $AAB01 = $value->AAB01;

                $errorContent = [
                    'status' => 1,
                    'content' => []
                ];
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

                $bmcnData = BaMrClassNumber::query()
                    ->where('patient_id', $AAA28)
                    ->where('visit_id', $AAA29)
                    ->where('MrClass', '首页')
                    ->first();

                if (!empty($bmcnData)) {
                    $errorContent['content'][] = [
                        'status' => 1,
                        'content' => '病案首页【有，有】'
                    ];
                } else {
                    $insert['gdwzl_fz'] = 0;
                    $errorContent['status'] = 0;
                    $errorContent['content'][] = [
                        'status' => 0,
                        'content' => '病案首页【有，无】'
                    ];
                }

                // 2. 出院记录（或 24小时出入院记录 或 死亡记录）
                if ($hospitalDays > 1) {
                    $cyjl = EMR_BL_BL01::query()->where('JZHM', $ZYH)->whereIn('BLLB', [1, 288])->get()->toArray();
                    if (!empty($cyjl)) {
                        $hasData = true;
                        $bmcnData = BaMrClassNumber::query()
                            ->where('patient_id', $AAA28)
                            ->where('visit_id', $AAA29)
                            ->where('MrClass', 'like', '%出院%')
                            ->first();

                        if (!empty($bmcnData)) {
                            $errorContent['content'][] = [
                                'status' => 1,
                                'content' => '出院记录【有，有】'
                            ];
                        } else {
                            $insert['gdwzl_fz'] = 0;
                            $errorContent['status'] = 0;
                            $errorContent['content'][] = [
                                'status' => 0,
                                'content' => '出院记录【有，无】'
                            ];
                        }
                    }
                } else {
                    $cyjl = EMR_BL_BL01::query()->where('JZHM', $ZYH)->whereIn('BLLB', [1, 288, 18])->get()->toArray();
                    if (!empty($cyjl)) {
                        $hasData = true;
                        $bmcnData = BaMrClassNumber::query()
                            ->where('patient_id', $AAA28)
                            ->where('visit_id', $AAA29)
                            ->where('MrClass', 'like', '%入院%')
                            ->first();

                        if (!empty($bmcnData)) {
                            $errorContent['content'][] = [
                                'status' => 1,
                                'content' => '24小时记录【有，有】'
                            ];
                        } else {
                            $insert['gdwzl_fz'] = 0;
                            $errorContent['status'] = 0;
                            $errorContent['content'][] = [
                                'status' => 0,
                                'content' => '24小时记录【有，无】'
                            ];
                        }
                    }
                }

                // 3. 入院记录（或 24小时出入院记录）
                if ($hospitalDays > 1) {
                    $ryjl = EMR_BL_BL01::query()->where('JZHM', $ZYH)->whereIn('BLLB', [292])->get()->toArray();
                    if (!empty($ryjl)) {
                        $hasData = true;
                        $bmcnData = BaMrClassNumber::query()
                            ->where('patient_id', $AAA28)
                            ->where('visit_id', $AAA29)
                            ->where('MrClass', '入院记录')
                            ->first();

                        if (!empty($bmcnData)) {
                            $errorContent['content'][] = [
                                'status' => 1,
                                'content' => '入院记录【有，有】'
                            ];
                        } else {
                            $insert['gdwzl_fz'] = 0;
                            $errorContent['status'] = 0;
                            $errorContent['content'][] = [
                                'status' => 0,
                                'content' => '入院记录【有，无】'
                            ];
                        }
                    }
                }

                // 4. 病程记录
                $bcjl = EMR_BL_BL01::query()->where('JZHM', $ZYH)->where('BLLB', 294)->where('BLMC', 'not like', '%术前小结%')->where('BLMC', 'not like', '%术前讨论%')->get()->toArray();
                if (!empty($bcjl)) {
                    //病程记录数量
                    $bccount = count($bcjl);
                    $hasData = true;
                    $bmcnDataList = BaMrClassNumber::query()
                        ->where('patient_id', $AAA28)
                        ->where('visit_id', $AAA29)
                        ->where('MrClass', 'like', '%病程%')
                        ->get()->toArray();
                    $bmcnData = 0;
                    if (!empty($bmcnDataList)) {
                        $bmcnData = $bmcnDataList[0]['Quantity'];
                    } else {
                        $bmcnData = 0;
                    }

                    if ($bmcnData > 0 && $bccount == $bmcnData) {
                        $errorContent['content'][] = [
                            'status' => 1,
                            'content' => '病程记录【有(' . $bccount . ')，有(' . $bmcnData . ')】'
                        ];
                    } else if ($bmcnData > 0 && $bccount != $bmcnData) {
                        $insert['gdwzl_fz'] = 0;
                        $errorContent['status'] = 0;
                        $errorContent['content'][] = [
                            'status' => 0,
                            'content' => '病程记录【有(' . $bccount . ')，有(' . $bmcnData . ')】'
                        ];
                    } else {
                        $insert['gdwzl_fz'] = 0;
                        $errorContent['status'] = 0;
                        $errorContent['content'][] = [
                            'status' => 0,
                            'content' => '病程记录【有(' . $bccount . ')，无】'
                        ];
                    }
                }

                // 5. 手术记录
                $ssjl = EMR_BL_BL01::query()->where('JZHM', $ZYH)->where('BLLB', 303)->where('BLMC', 'like', '%手术记录%')->where('BLMC', 'not like', '%术前讨论%')->get()->toArray();
                if (!empty($ssjl)) {
                    $hasData = true;
                    $bmcnData = BaMrClassNumber::query()
                        ->where('patient_id', $AAA28)
                        ->where('visit_id', $AAA29)
                        ->where('MrClass', '手术资料')
                        ->first();

                    if (!empty($bmcnData)) {
                        $errorContent['content'][] = [
                            'status' => 1,
                            'content' => '手术记录【有，有】'
                        ];
                    } else {
                        $insert['gdwzl_fz'] = 0;
                        $errorContent['status'] = 0;
                        $errorContent['content'][] = [
                            'status' => 0,
                            'content' => '手术记录【有，无】'
                        ];
                    }
                }

                // 6. 手术麻醉相关记录（麻醉记录单）
                $mzjl = SM_SSAP::query()->where('ZYH', $ZYH)->get()->toArray();
                if (!empty($mzjl)) {
                    $hasData = true;
                    $bmcnData = BaMrClassNumber::query()
                        ->where('patient_id', $AAA28)
                        ->where('visit_id', $AAA29)
                        ->where('MrClass', 'like', '%麻醉%')
                        ->first();

                    if (!empty($bmcnData)) {
                        $errorContent['content'][] = [
                            'status' => 1,
                            'content' => '手术麻醉相关记录【有，有】'
                        ];
                    } else {
                        $insert['gdwzl_fz'] = 0;
                        $errorContent['status'] = 0;
                        $errorContent['content'][] = [
                            'status' => 0,
                            'content' => '手术麻醉相关记录【有，无】'
                        ];
                    }
                }

                // 7. 知情同意书
                $zqtys = EMR_BL_BL01::query()->where('JZHM', $ZYH)->where('BLLB', 329)->get()->toArray();
                if (!empty($zqtys)) {
                    $hasData = true;
                    $bmcnData = BaMrClassNumber::query()
                        ->where('patient_id', $AAA28)
                        ->where('visit_id', $AAA29)
                        ->where('MrClass', '知情同意书')
                        ->first();

                    if (!empty($bmcnData)) {
                        $errorContent['content'][] = [
                            'status' => 1,
                            'content' => '知情同意书【有，有】'
                        ];
                    } else {
                        $insert['gdwzl_fz'] = 0;
                        $errorContent['status'] = 0;
                        $errorContent['content'][] = [
                            'status' => 0,
                            'content' => '知情同意书【有，无】'
                        ];
                    }
                }

                // // 8. 病理辅助检查报告单（病历图文报告：ExamType=7）
                // if ($AAB01 && $value->AAC01) {
                //     $blfzjc = PACS::query()->where('ZYH', $ZYH)->where('ExamType', '07')->get();
                //     if (!empty($blfzjc)) {
                //         $hasData = true;
                //         $bmcnData = BaMrClassNumber::query()
                //             ->where('patient_id', $AAA28)
                //             ->where('visit_id', $AAA29)
                //             ->where('MrClass', '病理辅助检查报告单')
                //             ->first();

                //         if (!empty($bmcnData)) {
                //             $errorContent[] = [
                //                 'status' => 1,
                //                 'content' => '病理辅助检查报告单【有，有】'
                //             ];
                //         } else {
                //             $insert['gdwzl_fz'] = 0;
                //             $errorContent[] = [
                //                 'status' => 0,
                //                 'content' => '病理辅助检查报告单【有，无】'
                //             ];
                //         }
                //     }

                // 9. 影像辅助检查报告单（影像诊断报告：ExamType=1 或 2）
                $yxfzjc = PACS::query()->where('ZYH', $ZYH)->whereIn('ExamType', ['01', '02'])->get();
                if (!empty($yxfzjc)) {
                    $bmcnData = BaMrClassNumber::query()
                        ->where('patient_id', $AAA28)
                        ->where('visit_id', $AAA29)
                        ->where('MrClass', 'like', '%检查%')
                        ->first();

                    if (!empty($bmcnData)) {
                        $errorContent['content'][] = [
                            'status' => 1,
                            'content' => '影像辅助检查报告单【有，有】'
                        ];
                    } else {
                        $insert['gdwzl_fz'] = 0;
                        $errorContent['status'] = 0;
                        $errorContent['content'][] = [
                            'status' => 0,
                            'content' => '影像辅助检查报告单【有，无】'
                        ];
                    }
                }

                // 10. 检验（检验报告单）
                $jybgd = V_JMGS_YMresult::query()->where('ZYH', $ZYH)->where('STAYHOSPITALMODE', 2)->get()->toArray();
                if (!empty($jybgd)) {
                    $hasData = true;
                    $bmcnData = BaMrClassNumber::query()
                        ->where('patient_id', $AAA28)
                        ->where('visit_id', $AAA29)
                        ->where('MrClass', '检验')
                        ->first();

                    if (!empty($bmcnData)) {
                        $errorContent['content'][] = [
                            'status' => 1,
                            'content' => '检验报告单【有，有】'
                        ];
                    } else {
                        $insert['gdwzl_fz'] = 0;
                        $errorContent['status'] = 0;
                        $errorContent['content'][] = [
                            'status' => 0,
                            'content' => '检验报告单【有，无】'
                        ];
                    }
                }

                // 11. 医嘱
                $yz = Yzb::query()->where('ZYH', $ZYH)->get()->toArray();
                if (!empty($yz)) {
                    $hasData = true;
                    $bmcnData = BaMrClassNumber::query()
                        ->where('patient_id', $AAA28)
                        ->where('visit_id', $AAA29)
                        ->where('MrClass', '医嘱')
                        ->first();

                    if (!empty($bmcnData)) {
                        $errorContent['content'][] = [
                            'status' => 1,
                            'content' => '医嘱【有，有】'
                        ];
                    } else {
                        $insert['gdwzl_fz'] = 0;
                        $errorContent['status'] = 0;
                        $errorContent['content'][] = [
                            'status' => 0,
                            'content' => '医嘱【有，无】'
                        ];
                    }
                }

                // 保存结果 - 只有当有数据时才保存
                $insert['gdwzl_fm'] = 1;
                $insert['gdwzl_error'] = json_encode([$errorContent], JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                $insert['AAA28'] = $value->AAA28;
                $insert['AAA01'] = $value->AAA01;
                $insert['AAC11N'] = $value->AAC11N;
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
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                //删除旧数据
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['zyzdbmzql_fm' => null, 'zyzdbmzql_fz' => null, 'zyzdbmzql_error' => null]);

                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }

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
                $insert['AAA28'] = $value->AAA28;
                $insert['AAA01'] = $value->AAA01;
                $insert['AAC11N'] = $value->AAC11N;
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
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                //删除旧数据
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['zyzdzql_fm' => null, 'zyzdzql_fz' => null, 'zyzdzql_error' => null]);

                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }

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

                $mainGroup = [
                    'status' => 1,
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
                $insert['AAA28'] = $value->AAA28;
                $insert['AAA01'] = $value->AAA01;
                $insert['AAC11N'] = $value->AAC11N;
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
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);
            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                //删除旧数据
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['zyssbmzql_fm' => null, 'zyssbmzql_fz' => null, 'zyssbmzql_error' => null]);

                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }


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

                if (!$hasValidOperation) {
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
                        $mainGroup['status'] = 1;
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
                $insert['AAA28'] = $value->AAA28;
                $insert['AAA01'] = $value->AAA01;
                $insert['AAC11N'] = $value->AAC11N;
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
            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                //删除旧数据
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['zysszql_fm' => null, 'zysszql_fz' => null, 'zysszql_error' => null]);

                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }


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

                if (!$hasValidOperation) {
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

                $mainGroup = [
                    'status' => 0,
                    'content' => []
                ];

                // 用住院号查询home_quality表
                $homeQualityData = HomeQuality::query()->where('zyh', $ZYH)->get();

                if ($homeQualityData->isEmpty()) {
                    // 如果数据为空，分子为1
                    $insert['zysszql_fz'] = 1;
                    $mainGroup['status'] = 1;
                    $mainGroup['content'][] = [
                        'status' => 1,
                        'content' => '病案首页主要手术名称填写准确，未发现相关错误'
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
                        $mainGroup['status'] = 1;
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
                $insert['AAA28'] = $value->AAA28;
                $insert['AAA01'] = $value->AAA01;
                $insert['AAC11N'] = $value->AAC11N;
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
        $bl01Service = null;
        $mzjlService = null;

        while (true) {

            $data = $this->getPatientInfo($cyzs, $zyh, $start, $end, $page);

            if ($page == 1) {
                //echo '数据总条数：' . $data->total() . PHP_EOL . '总页数：' . $data->lastPage() . PHP_EOL;
            } elseif ($page > $data->lastPage()) {
                break;
            }
            //echo $page . PHP_EOL;
            $page++;

            foreach ($data->items() as $value) {
                $ZYH = data_get($value, $cyzs->MED_REC_ID);
                //删除旧数据
                Indicator::query()->updateOrInsert(['zyh' => $ZYH], ['zqtys_fm' => null, 'zqtys_fz' => null, 'zqtys_error' => null]);

                if ($this->skipAAA28($value->AAA28)) {
                    continue;
                }

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

                // 查询知情同意书（BLLB=329）- 合并原 bl01 方法逻辑
                $bl01Data = EMR_BL_BL01::query()->where("JZHM", $ZYH)->where("BLLB", 329)->get()->toArray();
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
                    $blsyData = EMR_BL_BLSY::query()->where("BLBH", $bl01Value['BLBH'])->where('QMLX', 1)->get()->toArray();

                    if ($blsyData) {
                        $str = '未在出入院时间之内';
                        if ($AAB01 <= $blsyData[0]['SYSJ'] && $blsyData[0]['SYSJ'] <= $AAC01) {
                            $str = '在出入院时间之内';
                        }

                        // 查询医生信息 - 合并原 staff 方法逻辑
                        $staffData = Staff::query()->where('code', $blsyData[0]['SYYS'])->get()->toArray();
                        if (!empty($staffData[0]) && $str == '在出入院时间之内') {
                            $name = implode(',', array_column($staffData, 'name'));
                            $sqtylIsOk = 1;
                            $sqtylGroup['status'] = 1;
                            $sqtylGroup['content'][] = [
                                'status' => 1,
                                'content' => '授权同意类【' . $name . '，' . $blsyData[0]['SYSJ'] . '，' . $str . '】'
                            ];
                            break;
                        } else {
                            $sqtylGroup['content'][] = [
                                'status' => 0,
                                'content' => '授权同意类【无，' . $blsyData[0]['SYSJ'] . '，' . $str . '】'
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

                // 分子2 - 输血治疗同意书检查（模板类别 MBLB=59）（MySQL）
                $sxzltysData = [json_decode(json_encode(
                    EMR_BL_BL01::query()
                        ->where('JZHM', $ZYH)
                        ->where('MBLB', 59)
                        ->get()->toArray()
                ), true)];
                $sxzltysData = !empty($sxzltysData[0]) ? $sxzltysData[0] : [];

                if ($sxzltysData) {
                    // 查询输血时间（MBLB=45 或 病程名称含"输血病程记录"，按配置字段升序）（MySQL）
                    $sxsjData = [json_decode(json_encode(
                        EMR_BL_BL01::query()
                            ->where('JZHM', $ZYH)
                            ->where(function ($q) {
                                $q->where('MBLB', 45)
                                    ->orWhere('BLMC', 'LIKE', '%输血病程记录%');
                            })
                            ->orderBy(data_get($bagl_11, 'table_field'), 'ASC')
                            ->get()->toArray()
                    ), true)];
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
                // 分子3 - 手术知情同意书检查（病程类别 BLLB=329）（MySQL）
                $sszqtysData = [json_decode(json_encode(
                    EMR_BL_BL01::query()
                        ->where('JZHM', $ZYH)
                        ->where('BLLB', 329)
                        ->get()->toArray()
                ), true)];
                $sszqtysData = !empty($sszqtysData[0]) ? $sszqtysData[0] : [];

                if ($sszqtysData) {
                    // 查询麻醉记录（手术麻醉安排表）（MySQL）
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
                $insert['AAA28'] = $value->AAA28;
                $insert['AAA01'] = $value->AAA01;
                $insert['AAC11N'] = $value->AAC11N;
            }
        }

        return true;
    }
}
