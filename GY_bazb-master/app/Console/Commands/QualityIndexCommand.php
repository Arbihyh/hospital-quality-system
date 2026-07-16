<?php

namespace App\Console\Commands;

use App\Model\QualityIndex;
use App\Model\EMR_BL_BASYSJ;
use App\Model\MainDiagnosis;
use App\Model\OtherDiagnosis;
use App\Model\PatientHospitalInfo;
use Illuminate\Console\Command;
use App\Services\ElasticsearchService;


class QualityIndexCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:quality-index {time?}';

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

            $page++;

            $patientInfo = $res[0];


            $zyhArr = [];
            $patientInfoArr = [];
            foreach ($patientInfo as $p) {
                $zyhArr[] = $p['MED_REC_ID'];
                $patientInfoArr[$p['MED_REC_ID']] = $p;
            }

            //201 康复医学科
            $patientHospitalList = PatientHospitalInfo::query()->whereIn('AAA28', $zyhArr)
                //->where('AAB02C', 201)
                ->get();


            //初产妇剖宫产率
            $this->paogongchan($patientInfoArr, $zyhArr, $patientHospitalList);

            $hospitalList = [];
            foreach ($patientHospitalList as $hospital) {
                if ($hospital['AAB02C'] == 201) {
                    $hospitalList[] = $hospital;
                }
            }
            $this->kangfuRate($patientInfoArr, $hospitalList);
        }
    }

    //日常生活活动能力(ADL)改善率(REH-ADL-01)
    public function kangfuRate($patientInfoArr, $patientHospitalList)
    {

        $AAA28 = [];
        $fenmu = [];
        foreach ($patientHospitalList as $hospital) {
            $AAA28[$hospital['AAA28']] = $hospital['AAA28'];

            $fenmu[$hospital['AAA28']] = ['name' => $hospital['AAB11N'], 'code' => $hospital['AAB11C']];
        }


        $fenzi = [];
        $bllb1 = new ElasticsearchService('bllb1_2023');//ZYH 1v1
        foreach ($AAA28 as $zyh) {
            $re = $this->esServiceOne($bllb1, $zyh);
            if (!empty($re['CYQK']) && !empty($re['RYQK'])) {

                preg_match('/(Barthel评分|Barthel指数|Barthel)(.*?)分/is', $re['CYQK'], $matches);
                if (!empty($matches)) {
                    preg_match('/(Barthel评分|Barthel指数|Barthel)(.*?)分/is', $re['RYQK'], $matches2);
                    if (!empty($matches2)) {
                        //var_dump($matches, $matches2);
                        preg_match('/\d+/', $matches[2], $m1);
                        preg_match('/\d+/', $matches2[2], $m2);
                        $CYQK = isset($m1[0]) ? trim($m1[0], ':') : 0;
                        $RYQK = isset($m2[0]) ? trim($m2[0], ':') : 0;

                        if ($CYQK - $RYQK > 0) {
                            $fenzi[$zyh] = [$RYQK, $CYQK, 1];
                        } else {
                            $fenzi[$zyh] = [$RYQK, $CYQK, 0];
                        }
                    }

                }
            }

        }

        //日常生活活动能力(ADL)改善率(REH-ADL-01)
        $data = [];
        foreach ($fenmu as $fk => $fv) {
            $data[$fk]['category'] = 4;
            $data[$fk]['zyh'] = $fk;
            $data[$fk]['chuyuanshijian'] = $patientInfoArr[$fk]['AAC01'];
            $data[$fk]['ruyuanshijian'] = $patientInfoArr[$fk]['AAB01'];

            $data[$fk]['year'] = substr($patientInfoArr[$fk]['AAC01'], 0, 4);
            $data[$fk]['month'] = substr($patientInfoArr[$fk]['AAC01'], 5, 2);;

            $data[$fk]['xingming'] = $patientInfoArr[$fk]['AAA01'];
            $data[$fk]['kesibianma'] = $fv['code'];
            $data[$fk]['chuyuankesi'] = $fv['name'];
            // $data[$fk]['bianma'] = $mainDiagArr[$fv['MED_REC_ID']];


            if (isset($fenzi[$fk])) {
                $data[$fk]['pingfenleixing'] = 'Barthel';
                $data[$fk]['zhuangtai'] = $fenzi[$fk][2];
                $data[$fk]['pingfen'] = $fenzi[$fk][0] . '/' . $fenzi[$fk][1];
                $fen = $fenzi[$fk][1] - $fenzi[$fk][0];
                $data[$fk]['jisuanxiangqing'] = "出院科室【201 康复医学科】|出院记录“入院情况”中Barthel分值【" . $fenzi[$fk][0] . "】|出院记录“出院情况”中Barthel分值【" . $fenzi[$fk][1] . "】|差【" . $fen . "】";
            } else {
                $data[$fk]['pingfenleixing'] = '';
                $data[$fk]['zhuangtai'] = 0;
                $data[$fk]['jisuanxiangqing'] = "出院科室【201 康复医学科】| Barthel分值【无】";
                $data[$fk]['pingfen'] = '';
            }


            $data[$fk]['created_at'] = date('Y-m-d H:i:s');
            $data[$fk]['zhuyuanhao'] = $patientInfoArr[$fk]['AAA28'];
            //$data[$fk]['zhuzhenmingcheng'] = $shoushubianma;

        }


        (new QualityIndex())->insert($data);


        //脊髓损伤患者ADL改善率(REH-ADL-02)
        $hospitalList = [];
        foreach ($patientHospitalList as $hospital) {
            //$AAA28[$hospital['AAA28']] = $hospital['AAA28'];
            $hospitalList[$hospital['AAA28']] = ['name' => $hospital['AAB11N'], 'code' => $hospital['AAB11C']];
        }

        $fenmu = [];
        //$ID1 = ['J13', 'J14', 'J15', 'J16'];
        $ID1 = ['S14.101', 'S24.101', 'S34.100x001', 'T06.000', 'T06.000x001', 'T06.100', 'T06.100x001', 'T09.300', 'T88.800x001'];
        $mainDiagnosis = MainDiagnosis::query()->whereIn('AAA28', $AAA28)->get()->toArray();
        foreach ($mainDiagnosis as $main) {
            $flag = 0;
            $mainDiagArr[$main['AAA28']]['code'] = $main['ICD10_ID1'];
            $mainDiagArr[$main['AAA28']]['name'] = $main['ICD10_NAME'];
            foreach ($ID1 as $j) {
                if (false !== stripos($main['ICD10_ID1'], $j)) {
                    $flag = 1;
                    break;
                }
            }

            if ($flag) {
                $fenmu[$main['AAA28']] = $main['AAA28'];
            }
        }

        $fenzi = [];
        foreach ($fenmu as $zyh) {
            $re = $this->esServiceOne($bllb1, $zyh);
            if (!empty($re['CYQK']) && !empty($re['RYQK'])) {

                preg_match('/(Barthel评分|Barthel指数|Barthel)(.*?)分/is', $re['CYQK'], $matches);
                if (!empty($matches)) {
                    preg_match('/(Barthel评分|Barthel指数|Barthel)(.*?)分/is', $re['RYQK'], $matches2);
                    if (!empty($matches2)) {
                        preg_match('/\d+/', $matches[2], $m1);
                        preg_match('/\d+/', $matches2[2], $m2);

                        $CYQK = isset($m1[0]) ? trim($m1[0], ':') : 0;
                        $RYQK = isset($m2[0]) ? trim($m2[0], ':') : 0;

                        if ($CYQK - $RYQK > 0) {
                            $fenzi[$zyh] = [$RYQK, $CYQK, 1];
                        } else {
                            $fenzi[$zyh] = [$RYQK, $CYQK, 0];
                        }
                    }

                }
            }
        }

        $data = [];
        foreach ($fenmu as $fk => $fv) {
            $data[$fk]['category'] = 5;
            $data[$fk]['zyh'] = $fk;
            $data[$fk]['chuyuanshijian'] = $patientInfoArr[$fk]['AAC01'];
            $data[$fk]['ruyuanshijian'] = $patientInfoArr[$fk]['AAB01'];

            $data[$fk]['year'] = substr($patientInfoArr[$fk]['AAC01'], 0, 4);
            $data[$fk]['month'] = substr($patientInfoArr[$fk]['AAC01'], 5, 2);;

            $data[$fk]['xingming'] = $patientInfoArr[$fk]['AAA01'];
            $data[$fk]['kesibianma'] = $hospitalList[$fk]['code'];
            $data[$fk]['chuyuankesi'] = $hospitalList[$fk]['name'];
            $data[$fk]['zhuzhenbianma'] = $mainDiagArr[$fk]['code'];


            if (isset($fenzi[$fk])) {
                $data[$fk]['pingfenleixing'] = 'Barthel';
                $data[$fk]['zhuangtai'] = $fenzi[$fk][2];
                $data[$fk]['pingfen'] = $fenzi[$fk][0] . '/' . $fenzi[$fk][1];
                $fen = $fenzi[$fk][1] - $fenzi[$fk][0];
                $data[$fk]['jisuanxiangqing'] = "出院科室【201 康复医学科】|主要诊断【 " . $mainDiagArr[$fk]['code'] . " + " . $mainDiagArr[$fk]['name'] . "】|出院记录“入院情况”中Barthel分值【" . $fenzi[$fk][0] . "】|出院记录“出院情况”中Barthel分值【" . $fenzi[$fk][1] . "】|差【" . $fen . "】";
            } else {
                $data[$fk]['zhuangtai'] = 0;
                $data[$fk]['jisuanxiangqing'] = "出院科室【201 康复医学科】|主要诊断【 " . $mainDiagArr[$fk]['code'] . " + " . $mainDiagArr[$fk]['name'] . "】|Barthel分值【无】";
                $data[$fk]['pingfen'] = '';
            }

            $data[$fk]['created_at'] = date('Y-m-d H:i:s');
            $data[$fk]['zhuyuanhao'] = $patientInfoArr[$fk]['AAA28'];
            $data[$fk]['zhuzhenmingcheng'] = $mainDiagArr[$fk]['name'];
        }

        (new QualityIndex())->insert($data);


        //脑卒中患者 ADL 改善率(REH-ADL-03)
        $hospitalList = [];
        foreach ($patientHospitalList as $hospital) {
            //$AAA28[$hospital['AAA28']] = $hospital['AAA28'];
            $hospitalList[$hospital['AAA28']] = ['name' => $hospital['AAB11N'], 'code' => $hospital['AAB11C']];
        }

        $fenmu = [];
        $ID1 = ['I63.0', 'I63.1', 'I63.2', 'I63.3', 'I63.4', 'I63.5', 'I63.6', 'I63.7', 'I63.8', 'I63.9',
            'I61.0', 'I61.1', 'I61.2', 'I61.3', 'I61.3', 'I61.4', 'I61.5', 'I61.6', 'I61.7', 'I61.8', 'I61.9', 'I64.x00'];
        //$mainDiagnosis = MainDiagnosis::query()->whereIn('AAA28', $AAA28)->get();
        foreach ($mainDiagnosis as $main) {
            $flag = 0;
            $mainDiagArr[$main['AAA28']]['code'] = $main['ICD10_ID1'];
            $mainDiagArr[$main['AAA28']]['name'] = $main['ICD10_NAME'];
            foreach ($ID1 as $j) {
                if (false !== stripos($main['ICD10_ID1'], $j)) {
                    $flag = 1;
                    break;
                }
            }

            if ($flag) {
                $fenmu[$main['AAA28']] = $main['AAA28'];
            }
        }

        $fenzi = [];
        foreach ($fenmu as $zyh) {
            $re = $this->esServiceOne($bllb1, $zyh);
            if (!empty($re['CYQK']) && !empty($re['RYQK'])) {

                preg_match('/(Barthel评分|Barthel指数|Barthel)(.*?)分/is', $re['CYQK'], $matches);
                if (!empty($matches)) {
                    preg_match('/(Barthel评分|Barthel指数|Barthel)(.*?)分/is', $re['RYQK'], $matches2);
                    if (!empty($matches2)) {
                        preg_match('/\d+/', $matches[2], $m1);
                        preg_match('/\d+/', $matches2[2], $m2);

                        $CYQK = isset($m1[0]) ? trim($m1[0], ':') : 0;
                        $RYQK = isset($m2[0]) ? trim($m2[0], ':') : 0;

                        if ($CYQK - $RYQK > 0) {
                            $fenzi[$zyh] = [$RYQK, $CYQK, 1];
                        } else {
                            $fenzi[$zyh] = [$RYQK, $CYQK, 0];
                        }
                    }

                }
            }
        }

        $data = [];
        foreach ($fenmu as $fk => $fv) {
            $data[$fk]['category'] = 6;
            $data[$fk]['zyh'] = $fk;
            $data[$fk]['chuyuanshijian'] = $patientInfoArr[$fk]['AAC01'];
            $data[$fk]['ruyuanshijian'] = $patientInfoArr[$fk]['AAB01'];

            $data[$fk]['year'] = substr($patientInfoArr[$fk]['AAC01'], 0, 4);
            $data[$fk]['month'] = substr($patientInfoArr[$fk]['AAC01'], 5, 2);;

            $data[$fk]['xingming'] = $patientInfoArr[$fk]['AAA01'];
            $data[$fk]['kesibianma'] = $hospitalList[$fk]['code'];
            $data[$fk]['chuyuankesi'] = $hospitalList[$fk]['name'];
            $data[$fk]['zhuzhenbianma'] = $mainDiagArr[$fk]['code'];


            if (isset($fenzi[$fk])) {
                $data[$fk]['pingfenleixing'] = 'Barthel';
                $data[$fk]['zhuangtai'] = $fenzi[$fk][2];
                $data[$fk]['pingfen'] = $fenzi[$fk][0] . '/' . $fenzi[$fk][1];
                $fen = $fenzi[$fk][1] - $fenzi[$fk][0];
                $data[$fk]['jisuanxiangqing'] = "出院科室【201 康复医学科】|主要诊断【 " . $mainDiagArr[$fk]['code'] . " + " . $mainDiagArr[$fk]['name'] . "】|出院记录“入院情况”中Barthel分值【" . $fenzi[$fk][0] . "】|出院记录“出院情况”中Barthel分值【" . $fenzi[$fk][1] . "】|差【" . $fen . "】";
            } else {
                $data[$fk]['pingfenleixing'] = '';
                $data[$fk]['zhuangtai'] = 0;
                $data[$fk]['jisuanxiangqing'] = "出院科室【201 康复医学科】|主要诊断【 " . $mainDiagArr[$fk]['code'] . " + " . $mainDiagArr[$fk]['name'] . "】|Barthel分值【无】";
                $data[$fk]['pingfen'] = '';
            }

            $data[$fk]['created_at'] = date('Y-m-d H:i:s');
            $data[$fk]['zhuyuanhao'] = $patientInfoArr[$fk]['AAA28'];
            $data[$fk]['zhuzhenmingcheng'] = $mainDiagArr[$fk]['name'];
        }

        (new QualityIndex())->insert($data);


        //脑卒中患者运动功能评定率(REH-EVA-01)
        $fenzi = [];
        $searchWord = ['Brunnstrom', '布氏分期'];
        $bl01Service = new ElasticsearchService('bl01_202303'); //JZHM 1v2
        foreach ($fenmu as $zyh) {

            $bl01 = $this->bl01NewData($bl01Service, $zyh, 294);
            if (!empty($bl01)) {
                $brunnstromStr = '';
                foreach ($bl01 as $bl01Item) {
                    //$fenzi[$zyh] = [$HJNR, 0];
                    foreach ($searchWord as $word) {
                        if (false !== stripos($bl01Item['HJNR'], $word)) {


                            preg_match_all('/(' . $word . '分期|' . $word . ')(.*?)。/is', $bl01Item['HJNR'], $matches);

                            if (!empty($matches[0])) {
                                foreach ($matches[2] as $brunnstrom) {
                                    $brunnstromStr .= trim($brunnstrom, ':');
                                }
                            }
                            $fenzi[$zyh] = [$word, 1, $bl01Item['BLMC'], ':' . $brunnstromStr];
                            break;
                        }


                    }
                }

            }
        }

        $data = [];
        foreach ($fenmu as $fk => $fv) {
            $data[$fk]['category'] = 7;
            $data[$fk]['zyh'] = $fk;
            $data[$fk]['chuyuanshijian'] = $patientInfoArr[$fk]['AAC01'];
            $data[$fk]['ruyuanshijian'] = $patientInfoArr[$fk]['AAB01'];

            $data[$fk]['year'] = substr($patientInfoArr[$fk]['AAC01'], 0, 4);
            $data[$fk]['month'] = substr($patientInfoArr[$fk]['AAC01'], 5, 2);;

            $data[$fk]['xingming'] = $patientInfoArr[$fk]['AAA01'];
            $data[$fk]['kesibianma'] = $hospitalList[$fk]['code'];
            $data[$fk]['chuyuankesi'] = $hospitalList[$fk]['name'];
            $data[$fk]['zhuzhenbianma'] = $mainDiagArr[$fk]['code'];


            if (isset($fenzi[$fk])) {
                $data[$fk]['zhuangtai'] = $fenzi[$fk][1];
                $data[$fk]['jisuanxiangqing'] = "出院科室【201 康复医学科】|主要诊断【 " . $mainDiagArr[$fk]['code'] . " + " . $mainDiagArr[$fk]['name'] . "】| 【" . $fenzi[$fk][2] . "】病程记录中含【" . $fenzi[$fk][0] . $fenzi[$fk][3] . "】";
            } else {
                $data[$fk]['zhuangtai'] = 0;
                $searchWordStr = '';
                foreach ($searchWord as $w) {
                    $searchWordStr .= '|' . $w . "【无】";
                }

                $data[$fk]['jisuanxiangqing'] = "出院科室【201 康复医学科】|主要诊断【 " . $mainDiagArr[$fk]['code'] . " + " . $mainDiagArr[$fk]['name'] . "】" . $searchWordStr;

            }

            $data[$fk]['created_at'] = date('Y-m-d H:i:s');
            $data[$fk]['zhuyuanhao'] = $patientInfoArr[$fk]['AAA28'];
            $data[$fk]['zhuzhenmingcheng'] = $mainDiagArr[$fk]['name'];
        }

        (new QualityIndex())->insert($data);


        //脑卒中患者吞咽功能评定率(REH-EVA-03)
        $fenzi = [];
        $searchWord = ['洼田饮水试验'];
        //$bl01Service = new ElasticsearchService('bl01_202303');
        foreach ($fenmu as $zyh) {

            $bl01 = $this->bl01NewData($bl01Service, $zyh);
            if (!empty($bl01)) {
                //$fenzi[$zyh] = [$HJNR, 0];
                foreach ($bl01 as $bl01Item) {
                    foreach ($searchWord as $word) {
                        if (false !== stripos($bl01Item['HJNR'], $word)) {
                            $fenzi[$zyh] = [$word, 1];
                            break;
                        }
                    }
                }

            }
        }

        $data = [];
        foreach ($fenmu as $fk => $fv) {
            $data[$fk]['category'] = 8;
            $data[$fk]['zyh'] = $fk;
            $data[$fk]['chuyuanshijian'] = $patientInfoArr[$fk]['AAC01'];
            $data[$fk]['ruyuanshijian'] = $patientInfoArr[$fk]['AAB01'];

            $data[$fk]['year'] = substr($patientInfoArr[$fk]['AAC01'], 0, 4);
            $data[$fk]['month'] = substr($patientInfoArr[$fk]['AAC01'], 5, 2);;

            $data[$fk]['xingming'] = $patientInfoArr[$fk]['AAA01'];
            $data[$fk]['kesibianma'] = $hospitalList[$fk]['code'];
            $data[$fk]['chuyuankesi'] = $hospitalList[$fk]['name'];
            $data[$fk]['zhuzhenbianma'] = $mainDiagArr[$fk]['code'];


            if (isset($fenzi[$fk])) {
                $data[$fk]['zhuangtai'] = $fenzi[$fk][1];
                $data[$fk]['jisuanxiangqing'] = "出院科室【201 康复医学科】|主要诊断【 " . $mainDiagArr[$fk]['code'] . " + " . $mainDiagArr[$fk]['name'] . "】|病程记录中含【" . $fenzi[$fk][0] . "】";
            } else {
                $searchWordStr = '';
                foreach ($searchWord as $w) {
                    $searchWordStr .= '|' . $w . "【无】";
                }

                $data[$fk]['zhuangtai'] = 0;
                $data[$fk]['jisuanxiangqing'] = "出院科室【201 康复医学科】|主要诊断【 " . $mainDiagArr[$fk]['code'] . " + " . $mainDiagArr[$fk]['name'] . "】" . $searchWordStr;
            }

            $data[$fk]['created_at'] = date('Y-m-d H:i:s');
            $data[$fk]['zhuyuanhao'] = $patientInfoArr[$fk]['AAA28'];
            $data[$fk]['zhuzhenmingcheng'] = $mainDiagArr[$fk]['name'];
        }

        (new QualityIndex())->insert($data);


        //脊髓损伤患者神经功能评定率(REH-EVA-04)
        $fenmu = [];
        $ID1 = ['S14.101', 'S24.101', 'S34.100x001', 'T06.000', 'T06.000x001', 'T06.100', 'T06.100x001', 'T09.300', 'T88.800x001'];
        //$mainDiagnosis = MainDiagnosis::query()->whereIn('AAA28', $AAA28)->get();
        foreach ($mainDiagnosis as $main) {
            $flag = 0;
            $mainDiagArr[$main['AAA28']]['code'] = $main['ICD10_ID1'];
            $mainDiagArr[$main['AAA28']]['name'] = $main['ICD10_NAME'];
            foreach ($ID1 as $j) {
                if (false !== stripos($main['ICD10_ID1'], $j)) {
                    $flag = 1;
                    break;
                }
            }

            if ($flag) {
                $fenmu[$main['AAA28']] = $main['AAA28'];
            }
        }

        $fenzi = [];
        $searchWord = ['ASIA', '感觉平面', '运动平面'];
        //$bl01Service = new ElasticsearchService('bl01_202303');
        foreach ($fenmu as $zyh) {

            $bl01 = $this->bl01NewData($bl01Service, $zyh, 292);
            if (!empty($bl01)) {
                foreach ($bl01 as $bl01Item) {
                    foreach ($searchWord as $word) {
                        if (false !== stripos($bl01Item['HJNR'], $word)) {
                            $fenzi[$zyh][$word] = [$word, 1];
                        }
                    }
                }

            }
        }

        $data = [];
        foreach ($fenmu as $fk => $fv) {
            $data[$fk]['category'] = 9;
            $data[$fk]['zyh'] = $fk;
            $data[$fk]['chuyuanshijian'] = $patientInfoArr[$fk]['AAC01'];
            $data[$fk]['ruyuanshijian'] = $patientInfoArr[$fk]['AAB01'];

            $data[$fk]['year'] = substr($patientInfoArr[$fk]['AAC01'], 0, 4);
            $data[$fk]['month'] = substr($patientInfoArr[$fk]['AAC01'], 5, 2);;

            $data[$fk]['xingming'] = $patientInfoArr[$fk]['AAA01'];
            $data[$fk]['kesibianma'] = $hospitalList[$fk]['code'];
            $data[$fk]['chuyuankesi'] = $hospitalList[$fk]['name'];
            $data[$fk]['zhuzhenbianma'] = $mainDiagArr[$fk]['code'];

            $searchWordStr = '';
            if (isset($fenzi[$fk]) && count($fenzi[$fk]) == count($searchWord)) {

                foreach ($searchWord as $w) {
                    $searchWordStr .= '|入院记录中含' . $w . "【有】";
                }

                $data[$fk]['zhuangtai'] = 1;
                $data[$fk]['jisuanxiangqing'] = "出院科室【201 康复医学科】|主要诊断【 " . $mainDiagArr[$fk]['code'] . " + " . $mainDiagArr[$fk]['name'] . "】" . $searchWordStr;
            } else {
                $data[$fk]['zhuangtai'] = 0;

                if (isset($fenzi[$fk])) {
                    $searchWordStr = '';
                    foreach ($searchWord as $w) {
                        $searchWordStr .= '|入院记录中含' . $w . "【" . isset($fenzi[$fk][$w]) ? "有" : "空" . "】";
                    }
                } else {
                    $searchWordStr = '';
                    foreach ($searchWord as $w) {
                        $searchWordStr .= '|入院记录中含' . $w . "【无】";
                    }
                }

                $data[$fk]['jisuanxiangqing'] = "出院科室【201 康复医学科】|主要诊断【 " . $mainDiagArr[$fk]['code'] . " + " . $mainDiagArr[$fk]['name'] . "】" . $searchWordStr;
            }

            $data[$fk]['created_at'] = date('Y-m-d H:i:s');
            $data[$fk]['zhuyuanhao'] = $patientInfoArr[$fk]['AAA28'];
            $data[$fk]['zhuzhenmingcheng'] = $mainDiagArr[$fk]['name'];
        }

        (new QualityIndex())->insert($data);


    }

    //初产妇剖宫产率
    public function paogongchan($patientInfoArr, $zyhArr, $hospitalList)
    {
        $ICD10ID1Arr1 = [
            'O26.900x403', 'O26.900x404', 'O26.900x405', "O26.900x406", 'O26.900x407', 'O26.900x408',
            'O26.900x501', 'O26.900x502', 'O26.900x503', 'O26.900x504', 'O26.900x505', 'O26.900x506', 'O26.900x507',
            'O26.900x508', 'O26.900x509', 'O26.900x510'
        ];

        $qieArr1 = [
            'Z37.0', 'Z37.1', 'Z37.900', 'Z37.900x002', 'Z37.900x002', 'Z37.900x003', 'Z37.900x004'
        ];
        $qieArr2 = [
            'Z37.2', 'Z37.3', 'Z37.4'
        ];

        $qieArr3 = [
            'Z37.5', 'Z37.6', 'Z37.7', 'Z37.900x001'
        ];

        // $otherArr = array_merge($ICD10ID1Arr1, $qieArr1, $qieArr2, $qieArr3);

        $otherDiagnosis = OtherDiagnosis::query()->whereIn('AAA28', $zyhArr)
            //->whereIn('ICD10_ID1', $otherArr)
            ->get();


        $baseArr = [];
        $arr1 = [];
        $arr2 = [];
        $arr3 = [];
        $otherDiagnosisData = [];
        // $AAA28Arr = [];
        foreach ($otherDiagnosis as $other) {
            //$otherDiagnosisData[$other->AAA28][] = $other;
            foreach ($ICD10ID1Arr1 as $a) {
                if (false !== stripos($other->ICD10_ID1, $a)) {
                    $baseArr[$other->AAA28][] = $other->toArray();
                    // $AAA28Arr[$other->AAA28] = $other->AAA28;
                }
            }


            foreach ($qieArr1 as $a1) {
                if (false !== stripos($other->ICD10_ID1, $a1)) {
                    $arr1[$other->AAA28] = $other->toArray();
                }
            }

            foreach ($qieArr2 as $a2) {
                if (false !== stripos($other->ICD10_ID1, $a2)) {
                    $arr2[$other->AAA28] = $other->toArray();
                }
            }

            foreach ($qieArr3 as $a3) {
                if (false !== stripos($other->ICD10_ID1, $a3)) {
                    $arr3[$other->AAA28] = $other->toArray();
                }
            }
        }


//        foreach ($otherDiagnosisData as $dk => $dv) {
//            if (count($dv) > 1) {
//                $AAA28Arr[] = $dk;
//            }
//        }

//        $patientHospitalList = PatientHospitalInfo::query()->whereIn('AAA28', $AAA28Arr)->get();
        $kesi = [];
        foreach ($hospitalList as $hospital) {
            $kesi[$hospital['AAA28']] = ['name' => $hospital['AAB11N'], 'code' => $hospital['AAB11C']];
        }


        //$basysjRe = EMR_BL_BASYSJ::query()->whereIn('JZHM', $zyhArr)
        // ->where('XMQZ', 'like', '%P%')
        //    ->get();

        $emrBlBasysjService = new ElasticsearchService('emr_bl_basysj');//JZHM 1v2

        $basysjRe = [];
        foreach ($zyhArr as $recId) {
            $emrArr = $this->esServiceList($emrBlBasysjService, $recId, 1);
            if (empty($emrArr)) {
                continue;
            }

            foreach ($emrArr as $emr) {
                $basysjRe[] = $emr;
            }
        }


        $fenmuData = [];
        $xmqzArr = ['P1', 'P2', 'P3'];
        $fenmuRecId = [];
        foreach ($basysjRe as $basysj) {
            if (empty($basysj)) {
                continue;
            }

            if (false !== stripos($basysj['XMQZ'], 'P1') && isset($baseArr[$basysj['JZHM']]) && isset($arr1[$basysj['JZHM']])) {

                $fenmuRecId[$basysj['JZHM']] = $basysj['JZHM'];
                foreach ($baseArr[$basysj['JZHM']] as $otnerItem) {
                    $fenmuData[$basysj['JZHM']][0] = $otnerItem + ['XMQZ' => $basysj['XMQZ']];
                    $fenmuData[$basysj['JZHM']][1] = $arr1[$basysj['JZHM']];
                }

                break;
            }

            if (false !== stripos($basysj['XMQZ'], 'P2') && isset($baseArr[$basysj['JZHM']]) && isset($arr2[$basysj['JZHM']])) {

                $fenmuRecId[$basysj['JZHM']] = $basysj['JZHM'];
                foreach ($baseArr[$basysj['JZHM']] as $otnerItem) {
                    $fenmuData[$basysj['JZHM']][0] = $otnerItem + ['XMQZ' => $basysj['XMQZ']];
                    $fenmuData[$basysj['JZHM']][1] = $arr2[$basysj['JZHM']];
                }

                break;
            }

            if (false !== stripos($basysj['XMQZ'], 'P3') && isset($baseArr[$basysj['JZHM']]) && isset($arr3[$basysj['JZHM']])) {

                $fenmuRecId[$basysj['JZHM']] = $basysj['JZHM'];
                foreach ($baseArr[$basysj['JZHM']] as $otnerItem) {
                    $fenmuData[$basysj['JZHM']][0] = $otnerItem + ['XMQZ' => $basysj['XMQZ']];
                    $fenmuData[$basysj['JZHM']][1] = $arr3[$basysj['JZHM']];
                }

                break;
            }

        }


        $ICD9_ID1_Arr = ['74.0', '74.1', '74.2', '74.4', '74.9'];
        $mainOperationService = new ElasticsearchService('main_operation');//AAA28 多条
        $secondaryOperationService = new ElasticsearchService('secondary_operation_2023');//ZYH 多条

        $fenzhiData = [];
        foreach ($fenmuRecId as $recId) {
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


            $fenzhiData[$recId] = $ICD9_ID1;
        }


        $data = [];

        foreach ($fenmuData as $fk => $fv) {
            $patientInfo = $patientInfoArr[$fk];
            $data[$fk]['category'] = 3;
            $data[$fk]['zyh'] = $fk;
            $data[$fk]['chuyuanshijian'] = $patientInfo['AAC01'];
            $data[$fk]['ruyuanshijian'] = $patientInfo['AAB01'];

            $data[$fk]['year'] = substr($patientInfo['AAC01'], 0, 4);
            $data[$fk]['month'] = substr($patientInfo['AAC01'], 5, 2);;

            $data[$fk]['xingming'] = $patientInfo['AAA01'];
            $data[$fk]['chuyuankesi'] = $kesi[$fk]['name'];
            // $data[$fk]['bianma'] = $mainDiagArr[$fv['MED_REC_ID']];


            $otherbianma = '';
            $shoushubianma = '';
            $num = 0;
            foreach ($fv as $fkk => $vv) {
                $num++;
                $otherbianma .= "其他诊断" . $num . "【 " . $vv['ICD10_NAME'] . " " . $vv['ICD10_ID1'] . " 】|";
                if(isset($vv['XMQZ'])){
                    $shoushubianma .= "医生诊断【 " . $vv['XMQZ'] . " 】|";
                }
            }


            $zhenduan = '无';
            if (isset($fenzhiData[$fk]) && !empty($fenzhiData[$fk])) {
                $zhenduan = $fenzhiData[$fk]['ICD9_NAME'] . $fenzhiData[$fk]['ICD9_ID1'];
            }


            $data[$fk]['jisuanxiangqing'] = $otherbianma . $shoushubianma . "手术名称【 $zhenduan 】";
            $data[$fk]['zhuangtai'] = (isset($fenzhiData[$fk]) && !empty($fenzhiData[$fk])) ? 1 : 0;
            $data[$fk]['created_at'] = date('Y-m-d H:i:s');
            $data[$fk]['zhuyuanhao'] = $patientInfo['AAA28'];
            $data[$fk]['kesibianma'] = $kesi[$fk]['code'];
            $data[$fk]['zhuzhenmingcheng'] = $shoushubianma;
        }


        (new QualityIndex())->insert($data);


        //足月新生儿5分钟Apgar评分＜7分发生率
        $fenmu = [];
        $where1Arr = [];
        $where1 = ['O26.900x504', 'O26.900x505', 'O26.900x506', 'O26.900x507', 'O26.900x508', 'O26.900x509', 'O26.900x510'];
        $otherDiagnosis = OtherDiagnosis::query()->whereIn('AAA28', $zyhArr)->get();
        foreach ($otherDiagnosis as $other) {

            $flag = 0;

            foreach ($where1 as $w1) {
                if (false !== stripos($other['ICD10_ID1'], $w1)) {
                    $flag = 1;
                    break;
                }
            }

            if ($flag) {
                $where1Arr[$other['AAA28']] = ['ICD10_ID1' => $other['ICD10_ID1'], 'ICD10_NAME' => $other['ICD10_NAME']];
            }
        }

        $where2 = ['Z37.0', 'Z37.2*2', 'Z37.3', 'Z37.5*3', 'Z37.600x011'];
        foreach ($otherDiagnosis as $other2) {
            $flag = 0;
            foreach ($where2 as $w2) {
                if (false !== stripos($other2['ICD10_ID1'], $w2)) {
                    $flag = 1;
                    break;
                }
            }

            if ($flag) {
                if (isset($where1Arr[$other2['AAA28']])) {
                    $fenmu[$other2['AAA28']][0] = $where1Arr[$other2['AAA28']];
                    $fenmu[$other2['AAA28']][1] = ['ICD10_ID1' => $other2['ICD10_ID1'], 'ICD10_NAME' => $other2['ICD10_NAME']];
                }
            }
        }


        $fenzi = [];
        $searchWord = ['剖宫产记录'];
        $bl01Service = new ElasticsearchService('bl01_202303');//JZHM 1v2
        foreach ($fenmu as $zyh => $fArr) {

            $bl01 = $this->bl01NewData($bl01Service, $zyh);
            if (!empty($bl01)) {
                foreach ($bl01 as $bl01Item) {

                    //$fenzi[$zyh] = [$HJNR, 0];
                    foreach ($searchWord as $word) {

                        if (false !== stripos($bl01Item['BLMC'], $word) && false !== stripos($bl01Item['HJNR'], 'Apqar')) {

                            if (false !== stripos($bl01Item['HJNR'], 'F1胎儿')) {
                                preg_match('/F1胎儿(.*?)。/is', $bl01Item['HJNR'], $matchesF1);
                                if (isset($matchesF1[1]) && !empty($matchesF1[1])) {

                                    preg_match('/5分钟评(.*?)分/is', $matchesF1[1], $matchesPing5);
                                    if (!empty($matchesPing5) && isset($matchesPing5[1])) {
                                        $fenzi[$zyh]['f1'] = $matchesPing5[1];
                                    } else {
                                        preg_match('/1分钟评(.*?)分/is', $matchesF1[1], $matchesPing1);
                                        if (!empty($matchesPing1) && isset($matchesPing1[1])) {
                                            $fenzi[$zyh]['f1'] = $matchesPing1[1];
                                        }
                                    }
                                }

                                preg_match('/F2胎儿(.*?)。/is', $bl01Item['HJNR'], $matchesF2);
                                if (isset($matchesF2[1]) && !empty($matchesF2[1])) {

                                    preg_match('/5分钟评(.*?)分/is', $matchesF2[1], $matchesPing5);
                                    if (!empty($matchesPing5) && isset($matchesPing5[1])) {
                                        $fenzi[$zyh]['f2'] = $matchesPing5[1];
                                    } else {
                                        preg_match('/1分钟评(.*?)分/is', $matchesF2[1], $matchesPing1);
                                        if (!empty($matchesPing1) && isset($matchesPing1[1])) {
                                            $fenzi[$zyh]['f2'] = $matchesPing1[1];
                                        }
                                    }
                                }

                                preg_match('/F3胎儿(.*?)。/is', $bl01Item['HJNR'], $matchesF3);
                                if (isset($matchesF3[1]) && !empty($matchesF3[1])) {

                                    preg_match('/5分钟评(.*?)分/is', $matchesF3[1], $matchesPing5);
                                    if (!empty($matchesPing5) && isset($matchesPing5[1])) {
                                        $fenzi[$zyh]['f3'] = $matchesPing5[1];
                                    } else {
                                        preg_match('/1分钟评(.*?)分/is', $matchesF3[1], $matchesPing1);
                                        if (!empty($matchesPing1) && isset($matchesPing1[1])) {
                                            $fenzi[$zyh]['f3'] = $matchesPing1[1];
                                        }
                                    }
                                }
                                $fenzi[$zyh]['blmc'] = $bl01Item['BLMC'];
                            } else {
                                preg_match('/(Apqar评分|Apqar)(.*?)分/is', $bl01Item['HJNR'], $matches);
                                $fenzi[$zyh]['fen'] =  str_replace(['：',' '], '', $matches[2]);
                                $fenzi[$zyh]['blmc'] = $bl01Item['BLMC'];
                            }

                        }
                    }
                }

            }
        }

        $data = [];
        foreach ($fenmu as $fk => $fv) {
            $data[$fk]['category'] = 10;
            $data[$fk]['zyh'] = $fk;
            $data[$fk]['chuyuanshijian'] = $patientInfoArr[$fk]['AAC01'];
            $data[$fk]['ruyuanshijian'] = $patientInfoArr[$fk]['AAB01'];

            $data[$fk]['year'] = substr($patientInfoArr[$fk]['AAC01'], 0, 4);
            $data[$fk]['month'] = substr($patientInfoArr[$fk]['AAC01'], 5, 2);;

            $data[$fk]['xingming'] = $patientInfoArr[$fk]['AAA01'];
            $data[$fk]['kesibianma'] = $kesi[$fk]['code'];
            $data[$fk]['chuyuankesi'] = $kesi[$fk]['name'];
            // $data[$fk]['zhuzhenbianma'] = $mainDiagArr[$fk]['code'];

            $zhenduanbianma = '';

            if (isset($fenmu[$fk][0])) {
                $zhenduanbianma = '其他诊断1【' . $fenmu[$fk][0]['ICD10_ID1'] . $fenmu[$fk][0]['ICD10_NAME'] . "】|";
            }

            if (isset($fenmu[$fk][1])) {
                $zhenduanbianma .= '其他诊断2【' . $fenmu[$fk][1]['ICD10_ID1'] . $fenmu[$fk][1]['ICD10_NAME'] . "】";
            }


            if (isset($fenzi[$fk])) {
                $data[$fk]['pingfenleixing'] = 'Apgar';

                $pingfen = '';
                $zhuangtai = 0;
                $pinfen5 = 0;

                if (isset($fenzi[$zyh]['f1'])) {
                    $pingfen = 'F1:' . $fenzi[$zyh]['f1'];
                    $pinfen5 = $fenzi[$zyh]['f1'];
                    if ($fenzi[$zyh]['f1'] < 7) {
                        $zhuangtai = 1;
                    } else {
                        $zhuangtai = 0;
                    }
                }

                if (isset($fenzi[$zyh]['f2'])) {
                    $pingfen .= 'F2:' . $fenzi[$zyh]['f2'];

                    if ($fenzi[$zyh]['f2'] < 7) {
                        $zhuangtai = 1;
                    } else {
                        $zhuangtai = 0;
                    }
                }

                if (isset($fenzi[$zyh]['f3'])) {
                    $pingfen .= 'F3:' . $fenzi[$zyh]['f3'];

                    if ($fenzi[$zyh]['f3'] < 7) {
                        $zhuangtai = 1;
                    } else {
                        $zhuangtai = 0;
                    }
                }

                if (empty($pingfen)) {
                    $pingfen = $fenzi[$fk]['fen'];
                    $pinfen5 = $fenzi[$fk]['fen'];

                    if ($pinfen5 < 7) {
                        $zhuangtai = 1;
                    } else {
                        $zhuangtai = 0;
                    }
                }

                $data[$fk]['pingfen'] = $pingfen;
                $data[$fk]['zhuangtai'] = $zhuangtai;
                $data[$fk]['jisuanxiangqing'] = $zhenduanbianma . "|Apgar评分5min【" . $pinfen5 . "】【" . $fenzi[$fk]['blmc'] . "】";
            } else {
                $data[$fk]['pingfenleixing'] = '';
                $data[$fk]['zhuangtai'] = 0;
                $data[$fk]['jisuanxiangqing'] = $zhenduanbianma . "|Apgar评分5min【无】";
                $data[$fk]['pingfen'] = '';
            }


            $data[$fk]['created_at'] = date('Y-m-d H:i:s');
            $data[$fk]['zhuyuanhao'] = $patientInfoArr[$fk]['AAA28'];
            $data[$fk]['zhuzhenmingcheng'] = $zhenduanbianma;
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
