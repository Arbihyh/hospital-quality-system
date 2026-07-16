<?php

namespace App\Console\Commands;

use App\Model\QualityIndex;
use App\Model\MainDiagnosis;
use App\Model\PatientHospitalInfo;
use Illuminate\Console\Command;
use App\Services\ElasticsearchService;


class CapSeverityAssessmentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:cap';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command CAP 指标数据';

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


        $this->runData();


        echo 'end ' . date("Y-m-d H:i:s");
    }


    public function runData()
    {

        $page = 1;
        $pageSize = 1000;
        $startTime = '2024-01-01';
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
            [
                'range' => [

                    "AAA04" => [
                        'gt' => 17
                    ]
                ]
            ]
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

            $zyh = [];
            $patientInfoArr = [];
            foreach ($patientInfo as $p) {
                $zyh[] = $p['MED_REC_ID'];
                $patientInfoArr[$p['MED_REC_ID']] = $p;
            }

            //patient_hospital_info 中【AAB02C】 为【205 呼吸内科一病区】或  【2012 呼吸与危重症医学科  】或【4270 呼吸重症监护室】
            $patientHospitalList = PatientHospitalInfo::query()->whereIn('AAA28', $zyh)
                ->whereIn('AAB02C', [205, 2012, 4270])
                ->get();

            $AAA28 = [];
            $kesi = [];
            foreach ($patientHospitalList as $hospital) {
                $AAA28[] = $hospital['AAA28'];
                $kesi[$hospital['AAA28']] = ['name' => $hospital['AAB11N'], 'code' => $hospital['AAB11C']];

            }

            $ID1 = ['J13', 'J14', 'J15', 'J16'];
            $fenmuRecId = [];
            $mainDiagArr = [];
            $mainDiagnosis = MainDiagnosis::query()->whereIn('AAA28', $AAA28)->get();
            foreach ($mainDiagnosis as $main) {
                $mainDiagArr[$main['AAA28']] = ['code' => $main['ICD10_ID1'], 'name' => $main['ICD10_NAME']];
                foreach ($ID1 as $j) {
                    if (false !== stripos($main['ICD10_ID1'], $j)) {
                        $fenmuRecId[$main['AAA28']] = $main['AAA28'];
                        break;
                    }
                }
            }


            $fenmuData = [];
            foreach ($patientInfoArr as $item) {
                if (isset($fenmuRecId[$item['MED_REC_ID']])) {
                    $item['kesi'] = $kesi[$item['MED_REC_ID']];
                    $fenmuData[$item['MED_REC_ID']] = $item;
                }
            }


            $bl01Service = new ElasticsearchService('bl01_202303');

            $fenzhiData = [];
            foreach ($fenmuData as $fuemukey => $fuemuItem) {
                $fenzhiData[$fuemukey] = ['status' => 0, 'blmc' => ''];

                // 查询病例病号
                $blbh = $this->bl01NewData($bl01Service, $fuemuItem['MED_REC_ID']);
                if (empty($blbh)) {
                    continue;
                }

                foreach ($blbh as $bl) {

                    if (!empty($bl)) {
                        if (false !== stripos($bl['HJNR'], 'CURB-65')) {
                            preg_match('/CURB-65评分(.*?)分/is', $bl['HJNR'], $matches);
                            if (empty($matches)) {
                            } else {
                                preg_match('/\d+/', $matches[1], $matches2);
                                $fenzhiData[$fuemukey] = ['status' => 1, 'blmc' => $bl['BLMC'], 'fenshu' => (isset($matches2[0]) && !empty($matches2[0])) ? $matches2[0] : 0];
                            }

                            break;
                        } else {
                            $fenzhiData[$fuemukey] = ['status' => 0, 'blmc' => $bl['BLMC']];
                        }
                    }
                }

            }

            //（十六）住院成人社区获得性肺炎（CAP）患者进行CAP严重程度评估的比例
            $data = [];
            foreach ($fenmuData as $fk => $fv) {

                $data[$fk]['category'] = 1;
                $data[$fk]['zyh'] = $fv['MED_REC_ID'];
                $data[$fk]['chuyuanshijian'] = $fv['AAC01'];
                $data[$fk]['ruyuanshijian'] = $fv['AAB01'];

                $data[$fk]['year'] = substr($fv['AAC01'], 0, 4);
                $data[$fk]['month'] = substr($fv['AAC01'], 5, 2);;

                $data[$fk]['xingming'] = $fv['AAA01'];
                $data[$fk]['chuyuankesi'] = $fv['kesi']['name'];
                $data[$fk]['zhuzhenbianma'] = $mainDiagArr[$fv['MED_REC_ID']]['code'];

                $data[$fk]['zhuangtai'] = $fenzhiData[$fk]['status'];
                $data[$fk]['created_at'] = date('Y-m-d H:i:s');
                $data[$fk]['zhuyuanhao'] = $fv['AAA28'];


                if (isset($fenzhiData[$fk]['fenshu'])) {
                    $data[$fk]['pingfenleixing'] = 'CURB-65';
                    $data[$fk]['pingfen'] = $fenzhiData[$fk]['fenshu'];
                    $fenshuStr = "病程记录【" . $fenzhiData[$fk]['blmc'] . "】【" . $fenzhiData[$fk]['fenshu'] . "分】";
                } else {
                    $data[$fk]['pingfen'] = '';
                    $fenshuStr = "【空】";
                    $data[$fk]['pingfenleixing'] = '';
                }

                $data[$fk]['jisuanxiangqing'] = "入院科室【" . $fv['kesi']['name'] . $fv['kesi']['code'] . "】|主要诊断编码【" . $mainDiagArr[$fv['MED_REC_ID']]['code'] . $mainDiagArr[$fv['MED_REC_ID']]['name']. "】|年龄【" . $fv['AAA04'] . "】| CURB-65" . $fenshuStr;

                $data[$fk]['kesibianma'] = $fv['kesi']['code'];
                $data[$fk]['zhuzhenmingcheng'] = $fenzhiData[$fk]['blmc'];
            }


            (new QualityIndex())->insert($data);


            //
            $fenzhiData = [];
            foreach ($fenmuData as $fuemukey => $fuemuItem) {
                $fenzhiData[$fuemukey] = ['status' => 0, 'blmc' => ''];

                // 查询病例病号
                $blbh = $this->bl01NewData($bl01Service, $fuemuItem['MED_REC_ID'], 294, 295);
                if (empty($blbh)) {
                    continue;
                }

                foreach ($blbh as $bl) {
                    if (!empty($bl)) {
                        if (false !== stripos($bl['HJNR'], 'CURB-65')) {
                            preg_match('/CURB-65评分(.*?)分/is', $bl['HJNR'], $matches);
                            if (empty($matches)) {
                            } else {
                                preg_match('/\d+/', $matches[1], $matches2);
                                $fenzhiData[$fuemukey] = ['status' => 1, 'blmc' => $bl['BLMC'], 'fenshu' => (isset($matches2[0]) && !empty($matches2[0])) ? $matches2[0] : 0];
                            }
                            break;
                        } else {
                            $fenzhiData[$fuemukey] = ['status' => 0, 'blmc' => $bl['BLMC']];
                        }
                    }
                }
            }

            $data = [];
            foreach ($fenmuData as $fk => $fv) {

                $data[$fk]['category'] = 2;
                $data[$fk]['zyh'] = $fv['MED_REC_ID'];
                $data[$fk]['chuyuanshijian'] = $fv['AAC01'];
                $data[$fk]['ruyuanshijian'] = $fv['AAB01'];

                $data[$fk]['year'] = substr($fv['AAC01'], 0, 4);
                $data[$fk]['month'] = substr($fv['AAC01'], 5, 2);;

                $data[$fk]['xingming'] = $fv['AAA01'];
                $data[$fk]['chuyuankesi'] = $fv['kesi']['name'];
                $data[$fk]['zhuzhenbianma'] = $mainDiagArr[$fv['MED_REC_ID']]['code'];


                $data[$fk]['zhuangtai'] = $fenzhiData[$fk]['status'];
                $data[$fk]['created_at'] = date('Y-m-d H:i:s');

                $data[$fk]['zhuyuanhao'] = $fv['AAA28'];


                if (isset($fenzhiData[$fk]['fenshu'])) {
                    $data[$fk]['pingfenleixing'] = 'CURB-65';
                    $data[$fk]['pingfen'] = $fenzhiData[$fk]['fenshu'];
                    $fenshuStr = "病程记录【" . $fenzhiData[$fk]['blmc'] . "】【" . $fenzhiData[$fk]['fenshu'] . "分】";
                } else {
                    $data[$fk]['pingfen'] = '';
                    $fenshuStr = "【空】";
                    $data[$fk]['pingfenleixing'] = '';
                }


                $data[$fk]['jisuanxiangqing'] = "入院科室【" . $fv['kesi']['name'] . $fv['kesi']['code'] . "】| 主要诊断编码【" . $mainDiagArr[$fv['MED_REC_ID']]['code'] . $mainDiagArr[$fv['MED_REC_ID']]['name']."】| 年龄【" . $fv['AAA04'] . "】 | CURB-65" . $fenshuStr;

                $data[$fk]['kesibianma'] = $fv['kesi']['code'];
                $data[$fk]['zhuzhenmingcheng'] = $fenzhiData[$fk]['blmc'];
            }


            (new QualityIndex())->insert($data);

        }
    }

    protected function bl01NewData($bl01NewService, $ZYH, $BLLB = 294, $MBLB = 0)
    {
        $must = [
            ["term" => ['JZHM' => $ZYH]],
            ["term" => ['BLLB' => $BLLB]]
        ];

        if ($MBLB) {
            $must[] = ["term" => ['MBLB' => $MBLB]];
        }
        $notMust = [
            ["term" => ['BLZT' => 9]]
        ];
        $params = $bl01NewService->clearMust()
            ->queryByMustBatch($must)
            //->queryByMustNotBatch($notMust)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01NewData = $bl01NewService->getDataByEs($restful);
        $blbh = !empty($bl01NewData[0]) ? $bl01NewData[0] : '';

        return $blbh;
    }

    protected function blxgNewData($bl01NewService, $blbh)
    {
        $must = [
            ["term" => ['BLBH' => $blbh]],
        ];

        $params = $bl01NewService->clearMust()
            ->queryByMustBatch($must)
            //->queryByMustNotBatch($notMust)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01NewData = $bl01NewService->getDataByEs($restful);
        $blbh = !empty($bl01NewData[0]) ? $bl01NewData[0][0]['HJNR'] : '';

        return $blbh;
    }
}
