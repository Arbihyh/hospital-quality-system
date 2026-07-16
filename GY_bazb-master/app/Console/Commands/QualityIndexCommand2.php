<?php

namespace App\Console\Commands;

use App\Model\QualityIndex;
use App\Model\EMR_BL_BASYSJ;
use App\Model\MainDiagnosis;
use App\Model\OtherDiagnosis;
use App\Model\PatientHospitalInfo;
use App\Model\Yzb;
use Illuminate\Console\Command;
use App\Services\ElasticsearchService;


class QualityIndexCommand2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:quality-index_2 {time?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 质量控制指标';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        echo 'start ' . date("Y-m-d H:i:s");
        $star = $this->argument('time');

        $this->runData($star);

        echo 'end ' . date("Y-m-d H:i:s");
    }


    public function runData($star = "")
    {

        $page = 1;
        $pageSize = 1000;
        $startTime = $star ?: date("Y-01-01", time());
        $endTime = date("Y-m-d", time());

        $piEesService = new ElasticsearchService('patient_info');


        $must = [
            [
                'range' => [
                    "AAC01" => [
                        'gt' => $startTime . ' 00:00:00',
                        'lt' => $endTime . ' 23:59:59',
                    ]
                ]
            ],
//            [
//                'range' => [
//
//                    "AAA04" => [
//                        'gt' => 17
//                    ]
//                ]
//            ]
        ];

        while (1) {
            $params = $piEesService->paginate($page, $pageSize)
                ->queryByMustBatch($must)
                ->source(['MED_REC_ID', 'AAA01', 'AAB01', 'AAC01', 'AAC04', 'AAA04', 'AAA28'])
                ->orderBy('AAC01', 'asc')
                ->getParams();
            $res = app('es')->search($params);
            $res = $piEesService->getDataByEs($res);
            if (empty($res[0])) {
                break;
            }

            var_dump('page---' . $page . '---' . date('Y-m-d H:i:s'));

            $page++;

            $patientInfo = $res[0];


            $zyhArr = [];
            $patientInfoArr = [];
            foreach ($patientInfo as $p) {
                $zyhArr[] = $p['MED_REC_ID'];
                $patientInfoArr[$p['MED_REC_ID']] = $p;
            }


            //脑梗
            $this->naogengRate($patientInfoArr, $zyhArr);


        }
    }

    //脑梗
    public function naogengRate($patientInfoArr, $zyhArr)
    {


        $fenmu = [];
        $mainDiagnosis = MainDiagnosis::query()->whereIn('AAA28', $zyhArr)->get();
        foreach ($mainDiagnosis as $main) {

            if (false !== stripos($main['ICD10_ID1'], 'I63')) {
                $fenmu[$main['AAA28']] = $main;
            }
        }

        //bl01_202303
        $shoucibingcheng = [];
        $shoucichafang = [];
        $bl01Service = new ElasticsearchService('bl01_202303');//JZHM 1v2
        foreach ($fenmu as $zyh => $manVal) {
            $bl01 = $this->bl01NewData($bl01Service, $zyh);
            if (!empty($bl01)) {
                foreach ($bl01 as $bl01Item) {


                    if ($bl01Item['MBLB'] == 295 || $bl01Item['MBLB'] == 129) {
                        $fen = '';
                        preg_match('/(NIHSS评分|NIHSS)(.*?)分/is', $bl01Item['HJNR'], $matches);
                        if (isset($matches[2])) {
                            $fen = str_replace(['：', ' '], '', $matches[2]);
                        }

                        $shoucibingcheng[$zyh] = ['zyh' => $zyh, 'hjnr' => $bl01Item['HJNR'], 'blmc' => $bl01Item['BLMC'], 'nihss' => $fen, 'zxsj' => $bl01Item['ZXSJ']];
                    }

                    if ($bl01Item['BLLB'] == 294 && stripos($bl01Item['HJNR'], '含病例特点') && stripos($bl01Item['HJNR'], '诊断依据') && stripos($bl01Item['HJNR'], '鉴别诊断') && stripos($bl01Item['HJNR'], '诊疗计划')) {
                        $fen = '';
                        preg_match('/(NIHSS评分|NIHSS)(.*?)分/is', $bl01Item['HJNR'], $matches);
                        if (isset($matches[2])) {
                            $fen = str_replace(['：', ' '], '', $matches[2]);
                        }
                        $shoucibingcheng[$zyh] = ['zyh' => $zyh, 'hjnr' => $bl01Item['HJNR'], 'blmc' => $bl01Item['BLMC'], 'nihss' => $fen, 'zxsj' => $bl01Item['ZXSJ']];
                    }


                    $tt = time() < strtotime($patientInfoArr[$zyh]['AAB01']) + 3600 * 48 ? 1 : 0;
                    if ($bl01Item['BLLB'] == 294 && stripos($bl01Item['HJNR'], '上级医师') && $tt) {
                        $fen = '';
                        preg_match('/(NIHSS评分|NIHSS)(.*?)分/is', $bl01Item['HJNR'], $matches);
                        if (isset($matches[2])) {
                            $fen = str_replace(['：', ' '], '', $matches[2]);
                        }
                        $shoucichafang[$zyh] = ['zyh' => $zyh, 'hjnr' => $bl01Item['HJNR'], 'blmc' => $bl01Item['BLMC'], 'nihss' => $fen, 'zxsj' => $bl01Item['ZXSJ']];
                    }

                }
            }
        }
        //上级医师首次查房记录

        if (!empty($shoucibingcheng) && !empty($shoucichafang)) {
            foreach ($shoucichafang as $zyh => $zyhItem) {
                if (isset($shoucibingcheng[$zyh])) {
                    unset($shoucichafang[$zyh]);
                }
            }
        }


        //1.脑梗死患者神经功能缺损评估率
        $data = [];
        foreach ($fenmu as $fk => $fv) {
            $data[$fk]['category'] = 11;
            $data[$fk]['zyh'] = $fk;
            $data[$fk]['chuyuanshijian'] = $patientInfoArr[$fk]['AAC01'];
            $data[$fk]['ruyuanshijian'] = $patientInfoArr[$fk]['AAB01'];

            $data[$fk]['year'] = substr($patientInfoArr[$fk]['AAC01'], 0, 4);
            $data[$fk]['month'] = substr($patientInfoArr[$fk]['AAC01'], 5, 2);;

            $data[$fk]['xingming'] = $patientInfoArr[$fk]['AAA01'];
//            $data[$fk]['kesibianma'] = $fv['code'];
//            $data[$fk]['chuyuankesi'] = $fv['name'];
            // $data[$fk]['bianma'] = $mainDiagArr[$fv['MED_REC_ID']];


            if (isset($shoucibingcheng[$fk]) || isset($shoucichafang[$fk])) {
                $data[$fk]['pingfenleixing'] = 'NIHSS';
                $data[$fk]['zhuangtai'] = 1;
                $nihss = '';
                $pingfen = '';

                if (isset($shoucibingcheng[$fk]['nihss']) && $shoucibingcheng[$fk]['nihss'] != '') {
                    $pingfen = $shoucibingcheng[$fk]['nihss'];
                    $nihss = "|NIHSS评分【" . $pingfen . "】【" . $shoucibingcheng[$fk]['blmc'] . "】";
                }

                if (isset($shoucichafang[$fk]['nihss']) && $shoucichafang[$fk]['nihss'] != '') {
                    $pingfen = empty($pingfen) ? $shoucichafang[$fk]['nihss'] : $pingfen . '/' . $shoucichafang[$fk]['nihss'];
                    $nihss .= "|NIHSS评分【" . $pingfen . "】【" . $shoucibingcheng[$fk]['blmc'] . "】";
                }

                $data[$fk]['pingfen'] = $pingfen;

                if (empty($nihss)) {
                    $nihss = "|NIHSS评分【无】";
                }
                $data[$fk]['jisuanxiangqing'] = "主要诊断【" . $fenmu[$fk]['ICD10_NAME'] . '+' . $fenmu[$fk]['ICD10_ID1'] . "】$nihss";
            } else {
                $data[$fk]['pingfenleixing'] = '';
                $data[$fk]['zhuangtai'] = 0;
                $data[$fk]['jisuanxiangqing'] = "主要诊断【" . $fenmu[$fk]['ICD10_NAME'] . '+' . $fenmu[$fk]['ICD10_ID1'] . "】|NIHSS评分【无】";
                $data[$fk]['pingfen'] = '';
            }


            $data[$fk]['created_at'] = date('Y-m-d H:i:s');
            $data[$fk]['zhuyuanhao'] = $patientInfoArr[$fk]['AAA28'];
            //$data[$fk]['zhuzhenmingcheng'] = $shoushubianma;

        }

        (new QualityIndex())->insert($data);


        //8.非致残性脑梗死患者发病24小时内双重强化抗血小板药物治疗率

        $fenmu8 = [];
        $fenmu8Zyh = [];
        foreach ($fenmu as $fk => $fv) {
            if ((isset($shoucichafang[$fk]) && $shoucichafang[$fk]['nihss'] <= 3) || (isset($shoucibingcheng[$fk]) && $shoucibingcheng[$fk]['nihss'] <= 3)) {
                $fenmu8[$fk] = $fv;
                $fenmu8Zyh[] = $fk;
            }
        }

        $fenzi = [];
        $yzbRe = Yzb::query()->whereIn('ZYH', $fenmu8Zyh)->get();
        foreach ($yzbRe as $yzbItem) {
            if (empty($yzbItem['ZXSJ'])) {
                continue;
            }
            $diff = strtotime($yzbItem['ZXSJ']) - strtotime($patientInfoArr[$yzbItem['ZYH']]['AAC01']);
            if (false !== stripos($yzbItem['YZMC'], '含阿司匹林') && $diff <= 24 * 3600) {
                $fenzi[$yzbItem['ZYH']]['aspl'] = $yzbItem['ZXSJ'];
            }

            if (false !== stripos($yzbItem['YZMC'], '氯吡格雷') && $diff <= 24 * 3600) {
                $fenzi[$yzbItem['ZYH']]['lbgl'] = $yzbItem['ZXSJ'];
            }

        }


        $data = [];
        foreach ($fenmu8 as $fk => $fv) {
            $data[$fk]['category'] = 12;
            $data[$fk]['zyh'] = $fk;
            $data[$fk]['chuyuanshijian'] = $patientInfoArr[$fk]['AAC01'];
            $data[$fk]['ruyuanshijian'] = $patientInfoArr[$fk]['AAB01'];

            $data[$fk]['year'] = substr($patientInfoArr[$fk]['AAC01'], 0, 4);
            $data[$fk]['month'] = substr($patientInfoArr[$fk]['AAC01'], 5, 2);;

            $data[$fk]['xingming'] = $patientInfoArr[$fk]['AAA01'];
//            $data[$fk]['kesibianma'] = $fv['code'];
//            $data[$fk]['chuyuankesi'] = $fv['name'];
            // $data[$fk]['bianma'] = $mainDiagArr[$fv['MED_REC_ID']];


            if (isset($shoucibingcheng[$fk]['blmc'])) {
                $nihss = "|NIHSS评分【" . $shoucibingcheng[$fk]['nihss'] . "】【" . $shoucibingcheng[$fk]['blmc'] . "】";
            } else {
                $nihss = "|NIHSS评分【" . $shoucichafang[$fk]['nihss'] . "】【" . $shoucichafang[$fk]['blmc'] . "】";
            }

            $ruyuanshijian = "|入院时间【" . $patientInfoArr[$fk]['AAC01'] . "】";
            $yizhu1 = "|医嘱名称（阿司匹林）【无】";
            $yizhu2 = "|医嘱名称（氯吡格雷）【无】";

            if (isset($fenzi[$fk])) {
                $data[$fk]['pingfenleixing'] = 'NIHSS';
                $data[$fk]['zhuangtai'] = 1;

                if (isset($fenzi[$fk]['aspl'])) {
                    $yizhu1 = "|医嘱名称（阿司匹林）【≤24小时】【有】【" . $fenzi[$fk]['aspl'] . "】";
                }

                if (isset($fenzi[$fk]['lbgl'])) {
                    $yizhu2 = "|医嘱名称（氯吡格雷）【≤24小时】【有】【" . $fenzi[$fk]['lbgl'] . "】";
                }

                $data[$fk]['jisuanxiangqing'] = "主要诊断【" . $fenmu[$fk]['ICD10_NAME'] . '+' . $fenmu[$fk]['ICD10_ID1'] . "】" . $nihss . $ruyuanshijian . $yizhu1 . $yizhu2;
                $data[$fk]['pingfen'] = $fv['nihss'];
            } else {
                $data[$fk]['pingfenleixing'] = '';
                $data[$fk]['zhuangtai'] = 0;
                $data[$fk]['jisuanxiangqing'] = "主要诊断【" . $fenmu[$fk]['ICD10_NAME'] . '+' . $fenmu[$fk]['ICD10_ID1'] . "】" . $nihss . $ruyuanshijian . $yizhu1 . $yizhu2;
                $data[$fk]['pingfen'] = '';
            }


            $data[$fk]['created_at'] = date('Y-m-d H:i:s');
            $data[$fk]['zhuyuanhao'] = $patientInfoArr[$fk]['AAA28'];


        }

        (new QualityIndex())->insert($data);


        //14.脑梗死患者康复评估率
        $fenzi = [];

        $BLLB = [1, 292, 294, 43, 303, 101, 288, 14, 79, 18, 2000048];
        $bl01Service = new ElasticsearchService('bl01_202303');//JZHM 1v2
        foreach ($fenmu as $zyh => $manItem) {
            $bl01 = $this->bl01NewData($bl01Service, $zyh);
            if (!empty($bl01)) {
                foreach ($bl01 as $bl01Item) {

                    if (in_array($bl01Item['BLLB'], $BLLB) && false !== stripos($bl01Item['HJNR'], 'ADL')) {
                        $fenzi[$zyh] = $zyh;
                    }

                }
            }
        }

        $data = [];
        foreach ($fenmu as $fk => $fv) {
            $data[$fk]['category'] = 13;
            $data[$fk]['zyh'] = $fk;
            $data[$fk]['chuyuanshijian'] = $patientInfoArr[$fk]['AAC01'];
            $data[$fk]['ruyuanshijian'] = $patientInfoArr[$fk]['AAB01'];

            $data[$fk]['year'] = substr($patientInfoArr[$fk]['AAC01'], 0, 4);
            $data[$fk]['month'] = substr($patientInfoArr[$fk]['AAC01'], 5, 2);;

            $data[$fk]['xingming'] = $patientInfoArr[$fk]['AAA01'];
//            $data[$fk]['kesibianma'] = $fv['code'];
//            $data[$fk]['chuyuankesi'] = $fv['name'];
            // $data[$fk]['bianma'] = $mainDiagArr[$fv['MED_REC_ID']];


            if (isset($fenzi[$fk])) {
                $data[$fk]['zhuangtai'] = 1;
                $data[$fk]['jisuanxiangqing'] = "主要诊断【" . $fenmu[$fk]['ICD10_NAME'] . '+' . $fenmu[$fk]['ICD10_ID1'] . "】 |ADl【有】";
            } else {
                $data[$fk]['zhuangtai'] = 0;
                $data[$fk]['jisuanxiangqing'] = "主要诊断【" . $fenmu[$fk]['ICD10_NAME'] . '+' . $fenmu[$fk]['ICD10_ID1'] . "】 |ADl【无】";
            }


            $data[$fk]['created_at'] = date('Y-m-d H:i:s');
            $data[$fk]['zhuyuanhao'] = $patientInfoArr[$fk]['AAA28'];
            //$data[$fk]['zhuzhenmingcheng'] = $shoushubianma;

        }
        (new QualityIndex())->insert($data);


        //1.颈动脉支架置入术患者术前mRS评估率（NEU-CAS-01）

        $fenmu = [];

        $ICD9_ID1_Arr = ['00.63'];
        $mainOperationService = new ElasticsearchService('main_operation');//AAA28 多条
        $secondaryOperationService = new ElasticsearchService('secondary_operation_2023');//ZYH 多条


        foreach ($zyhArr as $recId) {
            $ICD9_ID1 = '';
            // 查询病例病号
            $operation = $this->esServiceList($mainOperationService, $recId, 2);
            if (!empty($operation)) {
                foreach ($operation as $operationItem) {
                    foreach ($ICD9_ID1_Arr as $icd9) {

                        if (false !== stripos($operationItem['ICD9_ID1'], $icd9)) {
                            $ICD9_ID1 = $operationItem;
                            break;
                        }
                    }
                }

            }
            if (empty($ICD9_ID1)) {
                $secondaryOperation = $this->esServiceList($secondaryOperationService, $recId, 0);
                if (!empty($secondaryOperation)) {
                    foreach ($secondaryOperation as $secondaryItem) {
                        foreach ($ICD9_ID1_Arr as $icd9) {

                            if (false !== stripos($secondaryItem['ICD9_ID1'], $icd9)) {
                                $ICD9_ID1 = $secondaryItem;
                                break;
                            }
                        }
                    }

                }
            }


            if (!empty($ICD9_ID1)) {
                $fenmu[$recId] = $ICD9_ID1;
            }

        }


        $fenzi = [];

        $blmc = ['mRS'];
        $bl01Service = new ElasticsearchService('bl01_202303');//JZHM 1v2
        foreach ($fenmu as $zyh => $manVal) {
            $bl01 = $this->bl01NewData($bl01Service, $zyh);
            if (!empty($bl01)) {
                foreach ($bl01 as $bl01Item) {

                    if (false !== stripos($bl01Item['HJNR'], $blmc[0]) && isset($shoucibingcheng[$zyh])) {
                        $fenzi[$zyh] = [$bl01Item['BLMC'], 0];
                    }

                }
            }
        }

        $data = [];
        foreach ($fenmu as $fk => $fv) {
            $data[$fk]['category'] = 14;
            $data[$fk]['zyh'] = $fk;
            $data[$fk]['chuyuanshijian'] = $patientInfoArr[$fk]['AAC01'];
            $data[$fk]['ruyuanshijian'] = $patientInfoArr[$fk]['AAB01'];

            $data[$fk]['year'] = substr($patientInfoArr[$fk]['AAC01'], 0, 4);
            $data[$fk]['month'] = substr($patientInfoArr[$fk]['AAC01'], 5, 2);;

            $data[$fk]['xingming'] = $patientInfoArr[$fk]['AAA01'];
//            $data[$fk]['kesibianma'] = $fv['code'];
//            $data[$fk]['chuyuankesi'] = $fv['name'];
            // $data[$fk]['bianma'] = $mainDiagArr[$fv['MED_REC_ID']];


            if (isset($fenzi[$fk])) {
                $data[$fk]['zhuangtai'] = 1;
                $data[$fk]['jisuanxiangqing'] = "手术【" . $fenmu[$fk]['ICD9_NAME'] . '+' . $fenmu[$fk]['ICD9_ID1'] . "】 |mRS 【" . $fenzi[$zyh][1] . "】【" . $fenzi[$zyh][0] . "】";
            } else {
                $data[$fk]['zhuangtai'] = 0;
                $data[$fk]['jisuanxiangqing'] = "手术【" . $fenmu[$fk]['ICD9_NAME'] . '+' . $fenmu[$fk]['ICD9_ID1'] . "】 |mRS【无】";
            }


            $data[$fk]['created_at'] = date('Y-m-d H:i:s');
            $data[$fk]['zhuyuanhao'] = $patientInfoArr[$fk]['AAA28'];
            //$data[$fk]['zhuzhenmingcheng'] = $shoushubianma;

        }
        (new QualityIndex())->insert($data);


        //无症状颈动脉狭窄患者颈动脉支架置入术手术指征符合率
        $fenmu15 = [];
        foreach ($fenmu as $fk => $fv) {
            if ((isset($shoucichafang[$fk]) && $shoucichafang[$fk]['nihss'] == 0) || (isset($shoucibingcheng[$fk]) && $shoucibingcheng[$fk]['nihss'] == 0)) {
                $fenmu15[$fk] = $fv;
                $fenmu15[] = $fk;
            }
        }

        $blmc = ['手术指征明确'];
        $bl01Service = new ElasticsearchService('bl01_202303');//JZHM 1v2
        foreach ($fenmu15 as $zyh => $manItem) {
            $bl01 = $this->bl01NewData($bl01Service, $zyh);
            if (!empty($bl01)) {
                foreach ($bl01 as $bl01Item) {

                    if (false !== stripos($bl01Item['HJNR'], $blmc[0]) && $bl01Item['MBLB'] == 82) {
                        $fenzi[$zyh] = $bl01Item['BLMC'];
                    }

                }
            }
        }

        $data = [];
        foreach ($fenmu15 as $fk => $fv) {
            $data[$fk]['category'] = 15;
            $data[$fk]['zyh'] = $fk;
            $data[$fk]['chuyuanshijian'] = $patientInfoArr[$fk]['AAC01'];
            $data[$fk]['ruyuanshijian'] = $patientInfoArr[$fk]['AAB01'];

            $data[$fk]['year'] = substr($patientInfoArr[$fk]['AAC01'], 0, 4);
            $data[$fk]['month'] = substr($patientInfoArr[$fk]['AAC01'], 5, 2);;

            $data[$fk]['xingming'] = $patientInfoArr[$fk]['AAA01'];
//            $data[$fk]['kesibianma'] = $fv['code'];
//            $data[$fk]['chuyuankesi'] = $fv['name'];
            // $data[$fk]['bianma'] = $mainDiagArr[$fv['MED_REC_ID']];


            if (isset($fenzi[$fk])) {
                $data[$fk]['zhuangtai'] = 1;
                $data[$fk]['jisuanxiangqing'] = "手术【" . $fenmu[$fk]['ICD9_NAME'] . '+' . $fenmu[$fk]['ICD9_ID1'] . "】 |NIHSS评分 【0】 术前小结记录“手术指征明确”【有】【 " . $fenzi[$fk] . " 】";
            } else {
                $data[$fk]['zhuangtai'] = 0;
                $data[$fk]['jisuanxiangqing'] = "手术【" . $fenmu[$fk]['ICD9_NAME'] . '+' . $fenmu[$fk]['ICD9_ID1'] . "】 |NIHSS评分 【0】 术前小结记录“手术指征明确”【无】";
            }


            $data[$fk]['created_at'] = date('Y-m-d H:i:s');
            $data[$fk]['zhuyuanhao'] = $patientInfoArr[$fk]['AAA28'];
            //$data[$fk]['zhuzhenmingcheng'] = $shoushubianma;

        }
        (new QualityIndex())->insert($data);


        //症状性颈动脉狭窄患者颈动脉支架置入手术指征符合率

        $fenmu16 = [];
        foreach ($fenmu as $fk => $fv) {
            if ((isset($shoucichafang[$fk]) && $shoucichafang[$fk]['nihss'] > 0) || (isset($shoucibingcheng[$fk]) && $shoucibingcheng[$fk]['nihss'] > 0)) {
                $fenmu16[$fk] = $fv + ['nihss' => $shoucichafang[$fk]['nihss'] > 1 ? $shoucichafang[$fk]['nihss'] : $shoucibingcheng[$fk]['nihss']];
            }
        }

        $blmc = ['手术指征明确'];
        $bl01Service = new ElasticsearchService('bl01_202303');//JZHM 1v2
        foreach ($fenmu16 as $zyh => $manItem) {
            $bl01 = $this->bl01NewData($bl01Service, $zyh);
            if (!empty($bl01)) {
                foreach ($bl01 as $bl01Item) {

                    if (false !== stripos($bl01Item['HJNR'], $blmc[0]) && $bl01Item['MBLB'] == 82) {
                        $fenzi[$zyh] = $bl01Item['BLMC'];
                    }

                }
            }
        }

        $data = [];
        foreach ($fenmu16 as $fk => $fv) {
            $data[$fk]['category'] = 16;
            $data[$fk]['zyh'] = $fk;
            $data[$fk]['chuyuanshijian'] = $patientInfoArr[$fk]['AAC01'];
            $data[$fk]['ruyuanshijian'] = $patientInfoArr[$fk]['AAB01'];

            $data[$fk]['year'] = substr($patientInfoArr[$fk]['AAC01'], 0, 4);
            $data[$fk]['month'] = substr($patientInfoArr[$fk]['AAC01'], 5, 2);;

            $data[$fk]['xingming'] = $patientInfoArr[$fk]['AAA01'];
//            $data[$fk]['kesibianma'] = $fv['code'];
//            $data[$fk]['chuyuankesi'] = $fv['name'];
            // $data[$fk]['bianma'] = $mainDiagArr[$fv['MED_REC_ID']];


            if (isset($fenzi[$fk])) {
                $data[$fk]['zhuangtai'] = 1;
                $data[$fk]['jisuanxiangqing'] = "手术【" . $fenmu[$fk]['ICD9_NAME'] . '+' . $fenmu[$fk]['ICD9_ID1'] . "】 |NIHSS评分 【" . $fv['nihss'] . "】术前小结记录“手术指征明确”【有】【 " . $fenzi[$fk] . " 】";
            } else {
                $data[$fk]['zhuangtai'] = 0;
                $data[$fk]['jisuanxiangqing'] = "手术【" . $fenmu[$fk]['ICD9_NAME'] . '+' . $fenmu[$fk]['ICD9_ID1'] . "】 |NIHSS评分 【" . $fv['nihss'] . "】术前小结记录“手术指征明确”【无】";
            }


            $data[$fk]['created_at'] = date('Y-m-d H:i:s');
            $data[$fk]['zhuyuanhao'] = $patientInfoArr[$fk]['AAA28'];
            //$data[$fk]['zhuzhenmingcheng'] = $shoushubianma;

        }
        (new QualityIndex())->insert($data);


        //6.颈动脉支架置入术技术成功率（NEU-CAS-06）
        //术后血流mTICI分级
        $blmc = ['颈动脉狭窄术后', '术后颈动脉狭窄度', '术后血流mTICI分级'];
        foreach ($fenmu as $zyh => $manItem) {
            $bl01 = $this->bl01NewData($bl01Service, $zyh);
            if (!empty($bl01)) {
                foreach ($bl01 as $bl01Item) {


                    foreach ($blmc as $serchWord) {
                        if (false !== stripos($bl01Item['BLMC'], $serchWord)) {
                            $fenzi[$zyh] = $serchWord;
                            break;
                        }

                    }


                }
            }
        }

        $data = [];
        foreach ($fenmu as $fk => $fv) {
            $data[$fk]['category'] = 17;
            $data[$fk]['zyh'] = $fk;
            $data[$fk]['chuyuanshijian'] = $patientInfoArr[$fk]['AAC01'];
            $data[$fk]['ruyuanshijian'] = $patientInfoArr[$fk]['AAB01'];

            $data[$fk]['year'] = substr($patientInfoArr[$fk]['AAC01'], 0, 4);
            $data[$fk]['month'] = substr($patientInfoArr[$fk]['AAC01'], 5, 2);;

            $data[$fk]['xingming'] = $patientInfoArr[$fk]['AAA01'];
//            $data[$fk]['kesibianma'] = $fv['code'];
//            $data[$fk]['chuyuankesi'] = $fv['name'];
            // $data[$fk]['bianma'] = $mainDiagArr[$fv['MED_REC_ID']];


            if (isset($fenzi[$fk])) {
                $data[$fk]['zhuangtai'] = 1;
                $data[$fk]['jisuanxiangqing'] = "手术【" . $fenmu[$fk]['ICD9_NAME'] . '+' . $fenmu[$fk]['ICD9_ID1'] . "】 |手术记录“术脑血管造影术的术中诊断【 " . $fenzi[$fk] . " 】";
            } else {
                $data[$fk]['zhuangtai'] = 0;
                $data[$fk]['jisuanxiangqing'] = "手术【" . $fenmu[$fk]['ICD9_NAME'] . '+' . $fenmu[$fk]['ICD9_ID1'] . "】 |手术记录“术后颈动脉狭窄度”【无】|手术记录“术后血流mTICI分级”【无】";
            }


            $data[$fk]['created_at'] = date('Y-m-d H:i:s');
            $data[$fk]['zhuyuanhao'] = $patientInfoArr[$fk]['AAA28'];
            //$data[$fk]['zhuzhenmingcheng'] = $shoushubianma;

        }
        (new QualityIndex())->insert($data);


        //4.脑血管造影术造影阳性率（NEU-DSA-04）

        $ICD9_ID1_Arr = ['88.41'];


        $mainOperationService = new ElasticsearchService('main_operation');//AAA28 多条
        $secondaryOperationService = new ElasticsearchService('secondary_operation_2023');//ZYH 多条

        $fenmu = [];
        foreach ($zyhArr as $recId) {
            $ICD9_ID1 = '';
            // 查询病例病号
            $operation = $this->esServiceList($mainOperationService, $recId, 2);
            if (!empty($operation)) {
                foreach ($operation as $operationItem) {
                    foreach ($ICD9_ID1_Arr as $icd9) {

                        if (false !== stripos($operationItem['ICD9_ID1'], $icd9)) {
                            $ICD9_ID1 = $operationItem;
                            break;
                        }
                    }
                }

            }
            if (empty($ICD9_ID1)) {
                $secondaryOperation = $this->esServiceList($secondaryOperationService, $recId, 0);
                if (!empty($secondaryOperation)) {
                    foreach ($secondaryOperation as $secondaryItem) {
                        foreach ($ICD9_ID1_Arr as $icd9) {

                            if (false !== stripos($secondaryItem['ICD9_ID1'], $icd9)) {
                                $ICD9_ID1 = $secondaryItem;
                                break;
                            }
                        }
                    }

                }
            }

            if (!empty($ICD9_ID1)) {
                $fenmu[$recId] = $ICD9_ID1;
            }
        }


        $blmc = ['动脉粥样硬化', '栓塞', '狭窄', '闭塞', '动脉瘤', '动静脉畸形', '动静脉瘘', '静脉窦闭塞', '静脉窦狭窄', '血管变异', '颅内占位性病变', '脑外血肿', '血管破裂出血'];
        $bl01Service = new ElasticsearchService('bl01_202303');//JZHM 1v2
        foreach ($fenmu as $zyh => $manItem) {
            $bl01 = $this->bl01NewData($bl01Service, $zyh);
            if (!empty($bl01)) {
                foreach ($bl01 as $bl01Item) {

                    if ($bl01Item['MBLB'] == 74 || $bl01Item['MBLB'] == 306) {
                        foreach ($blmc as $serchWord) {
                            if (false !== stripos($bl01Item['BLMC'], $serchWord)) {
                                $fenzi[$zyh] = $serchWord;
                                break;
                            }
                        }
                    }
                }
            }
        }

        $data = [];
        foreach ($fenmu as $fk => $fv) {
            $data[$fk]['category'] = 18;
            $data[$fk]['zyh'] = $fk;
            $data[$fk]['chuyuanshijian'] = $patientInfoArr[$fk]['AAC01'];
            $data[$fk]['ruyuanshijian'] = $patientInfoArr[$fk]['AAB01'];

            $data[$fk]['year'] = substr($patientInfoArr[$fk]['AAC01'], 0, 4);
            $data[$fk]['month'] = substr($patientInfoArr[$fk]['AAC01'], 5, 2);;

            $data[$fk]['xingming'] = $patientInfoArr[$fk]['AAA01'];
//            $data[$fk]['kesibianma'] = $fv['code'];
//            $data[$fk]['chuyuankesi'] = $fv['name'];
            // $data[$fk]['bianma'] = $mainDiagArr[$fv['MED_REC_ID']];


            if (isset($fenzi[$fk])) {
                $data[$fk]['zhuangtai'] = 1;
                $data[$fk]['jisuanxiangqing'] = "手术【" . $fenmu[$fk]['ICD9_NAME'] . '+' . $fenmu[$fk]['ICD9_ID1'] . "】 |手术记录“术脑血管造影术的术中诊断【 " . $fenzi[$fk] . " 】";
            } else {
                $data[$fk]['zhuangtai'] = 0;
                $data[$fk]['jisuanxiangqing'] = "手术【" . $fenmu[$fk]['ICD9_NAME'] . '+' . $fenmu[$fk]['ICD9_ID1'] . "】 |手术记录“术脑血管造影术的术中诊断【无】";
            }


            $data[$fk]['created_at'] = date('Y-m-d H:i:s');
            $data[$fk]['zhuyuanhao'] = $patientInfoArr[$fk]['AAA28'];
            //$data[$fk]['zhuzhenmingcheng'] = $shoushubianma;

        }
        (new QualityIndex())->insert($data);
    }


    protected function esServiceOne($service, $ZYH, $type = 0)
    {
        if ($type) {
            $must = [
                ["term" => ['JZHM' => $ZYH]],

            ];
        } else {
            $must = [
                ["term" => ['ZYH' => $ZYH]],

            ];
        }

        $notMust = [
            ["term" => ['BLZT' => 9]]
        ];
        $params = $service->clearMust()
            ->queryByMustBatch($must)
            //->queryByMustNotBatch($notMust)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01NewData = $service->getDataByEs($restful);
        $blbh = !empty($bl01NewData[0]) ? $bl01NewData[0][0] : '';

        return $blbh;
    }

    protected function esServiceList($service, $ZYH, $type = 0)
    {
        if ($type == 1) {
            $must = [
                ["term" => ['JZHM' => $ZYH]],

            ];
        } elseif ($type == 2) {
            $must = [
                ["term" => ['AAA28' => $ZYH]],

            ];
        } else {
            $must = [
                ["term" => ['ZYH' => $ZYH]],

            ];
        }

        $notMust = [
            ["term" => ['BLZT' => 9]]
        ];
        $params = $service->clearMust()
            ->queryByMustBatch($must)
            //->queryByMustNotBatch($notMust)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01NewData = $service->getDataByEs($restful);
        $blbh = !empty($bl01NewData[0]) ? $bl01NewData[0] : '';

        return $blbh;
    }


    protected function bl01NewData($bl01NewService, $ZYH, $BLLB = 0, $MBLB = 0)
    {
        $must = [
            ["term" => ['JZHM' => $ZYH]],
        ];

        if ($BLLB) {
            $must[] = ["term" => ['BLLB' => $BLLB]];
        }

        if ($MBLB) {
            $must[] = ["term" => ['MBLB' => $MBLB]];
        }

        $params = $bl01NewService->clearMust()
            ->queryByMustBatch($must)
            //->queryByMustNotBatch($notMust)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01NewData = $bl01NewService->getDataByEs($restful);
        $blbh = !empty($bl01NewData[0]) ? $bl01NewData[0] : '';

        return $blbh;
    }


}
