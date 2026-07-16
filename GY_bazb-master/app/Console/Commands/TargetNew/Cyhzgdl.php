<?php

namespace App\Console\Commands\TargetNew;

use App\Model\BA_RECEIVE;
use App\Model\PatientHospitalInfo;
use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use App\Model\PatientInfoTargetTemporary;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;

class Cyhzgdl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'newZb:cyhzgdl {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '出院患者病历归档完整率';

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
        $this->info('出院患者病历归档完整率 - 数据开始处理');

        $page = (int)$this->argument('page') ?: 1;
        $this->cyhzgdl($page);

        $this->info('出院患者病历归档完整率 - 数据处理完毕');
    }

    protected function cyhzgdl($page)
    {
        $bl01NewService = new ElasticsearchService('emr_bl_bl01_new');
        $bl01Service = new ElasticsearchService('bl01_202303');
        $mzjlService = new ElasticsearchService('mzjl_2023');
        $pacsService = new ElasticsearchService('pacs');
        $ymresultService = new ElasticsearchService('v_jmgs_ymresult_2023');
        $yzbService = new ElasticsearchService('yzb_2023');
        $bmcnService = new ElasticsearchService('ba_mr_class_number_2023');

        while (true) {
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->whereBetween('AAC01',['2023-01-01 00:00:00','2023-12-31 23:59:59'])
                ->paginate(500, ['AAA28','MED_REC_ID','AAB01','AAC01','AAA29'],'page', $page)
                ->toArray();

            if ($page == 1) {
                echo '数据总条数：'.$data['total'].PHP_EOL.'总页数：'.$data['last_page'].PHP_EOL;
            } elseif ($page > $data['last_page']) {
                // 如果当前页码大于最大页码则结束
                break;
            }
            echo $page.PHP_EOL;
            $page++;

            foreach ($data['data'] as $value) {
                $AAA28 = $value['AAA28'];
                $ZYH = $value['MED_REC_ID'];
                $AAB01 = $value['AAB01'];
                $AAA29 = $value['AAA29'];

                $numerator = 1;
                $cyhzgdlError = '';

                // 病案首页
                $must = ['term' => ["JZHM" => $ZYH]];
                $params = $bl01NewService->clearMust()->queryByMust($must)->getParams();
                $restful = app('es')->search($params);
                $bl01NewData = $bl01NewService->getDataByEs($restful);
                if (!empty($bl01NewData[0])) {
                    $bmcnData = $this->baMrClassNumber($bmcnService,$AAA28,$AAA29,'首页');
                    if ($bmcnData) {
                        $cyhzgdlError = '病案首页【有，有】';
                    } else {
                        $numerator = 0;
                        $cyhzgdlError = '病案首页【有，无】';
                    }
                }

                // 出院记录（或 24小时出入院记录 或 死亡记录）
                $must = [['term' => ["JZHM" => $ZYH]]];
                $should = [['term' => ["BLLB" => 1]], ['term' => ["BLLB" => 288]]];
                $cyjl = $this->bl01Data($bl01Service,$must,$should);
                if (empty($cyjl)) {
                    $must = [
                        ['term' => ["JZHM" => $ZYH]],
                        ['term' => ["BLLB" => 18]],
                        ['match_phrase' => ["HJNR" => '出院记录']]
                    ];
                    $cyjl = $this->bl01Data($bl01Service,$must);
                }
                if (!empty($cyjl)) {
                    $bmcnData = $this->baMrClassNumber($bmcnService,$AAA28,$AAA29,'出院记录');
                    if ($bmcnData) {
                        $cyhzgdlError .= '出院记录【有，有】';
                    } else {
                        $numerator = 0;
                        $cyhzgdlError .= '出院记录【有，无】';
                    }
                }

                // 入院记录（或 24小时出入院记录）
                $must = [['term' => ["JZHM" => $ZYH]], ['term' => ["BLLB" => 292]]];
                $ryjl = $this->bl01Data($bl01Service,$must);
                if (empty($ryjl)) {
                    $must = [
                        ['term' => ["JZHM" => $ZYH]], ['term' => ["BLLB" => 18]],
                        ['match_phrase' => ["HJNR" => '入院记录']]
                    ];
                    $ryjl = $this->bl01Data($bl01Service,$must);
                }
                if (!empty($ryjl)) {
                    $bmcnData = $this->baMrClassNumber($bmcnService,$AAA28,$AAA29,'入院记录');
                    if ($bmcnData) {
                        $cyhzgdlError .= '入院记录【有，有】';
                    } else {
                        $numerator = 0;
                        $cyhzgdlError .= '入院记录【有，无】';
                    }
                }

                // 病程记录
                $must = [['term' => ["JZHM" => $ZYH]], ['term' => ["BLLB" => 294]]];
                $bcjl = $this->bl01Data($bl01Service,$must);
                if (!empty($bcjl)) {
                    $bmcnData = $this->baMrClassNumber($bmcnService,$AAA28,$AAA29,'病程');
                    if ($bmcnData) {
                        $cyhzgdlError .= '病程记录【有，有】';
                    } else {
                        $numerator = 0;
                        $cyhzgdlError .= '病程记录【有，无】';
                    }
                }

                // 手术记录
                $must = [['term' => ["JZHM" => $ZYH]], ['term' => ["BLLB" => 303]]];
                $should = [['term' => ['MBLB' => 306]], ['term' => ['MBLB' => 74]]];
                $ssjl = $this->bl01Data($bl01Service,$must,$should);
                if (!empty($ssjl)) {
                    $bmcnData = $this->baMrClassNumber($bmcnService,$AAA28,$AAA29,'手术记录');
                    if ($bmcnData) {
                        $cyhzgdlError .= '手术记录【有，有】';
                    } else {
                        $numerator = 0;
                        $cyhzgdlError .= '手术记录【有，无】';
                    }
                }

                // 手术麻醉相关记录（麻醉记录单）
                $must = [["term" => ['HOSPIZATIONID' => $ZYH]]];
                $params = $mzjlService->clearMust()->queryByMustBatch($must)->getParams();
                $restful = app('es')->search($params);
                $mzjl = $mzjlService->getDataByEs($restful);
                if (!empty($mzjl[0])) {
                    $bmcnData = $this->baMrClassNumber($bmcnService,$AAA28,$AAA29,'手术麻醉相关记录');
                    if ($bmcnData) {
                        $cyhzgdlError .= '手术麻醉相关记录【有，有】';
                    } else {
                        $numerator = 0;
                        $cyhzgdlError .= '手术麻醉相关记录【有，无】';
                    }
                }

                // 会诊记录
//                $must = [['term' => ["JZHM" => $ZYH]], ['term' => ["BLLB" => 101]]];
//                $hzjl = $this->bl01Data($bl01Service,$must);
//                if (!empty($hzjl)) {
//                    $bmcnData = $this->baMrClassNumber($bmcnService,$AAA28,$AAA29,'会诊记录');
//                    if ($bmcnData) {
//                        $cyhzgdlError .= '会诊记录【有，有】';
//                    } else {
//                        $numerator = 0;
//                        $cyhzgdlError .= '会诊记录【有，无】';
//                    }
//                }

                // 知情同意书
                $must = [['term' => ["JZHM" => $ZYH]], ['term' => ["BLLB" => 329]]];
                $zqtys = $this->bl01Data($bl01Service,$must);
                if (!empty($zqtys)) {
                    $bmcnData = $this->baMrClassNumber($bmcnService,$AAA28,$AAA29,'知情同意书');
                    if ($bmcnData) {
                        $cyhzgdlError .= '知情同意书【有，有】';
                    } else {
                        $numerator = 0;
                        $cyhzgdlError .= '知情同意书【有，无】';
                    }
                }

                // 病理辅助检查报告单（病历图文报告：ExamType=7）
                if ($AAB01 && $value['AAC01']) {
                    $must = [
                        ['term' => ['JZLSH' => $value['AAA28']]],
                        ['range' => ['JYSJ' => ['gte' => $AAB01,'lte'=>$value['AAC01']]]],
                        ['term' => ['ExamType'=>'07']]
                    ];
                    $blfzjc = $this->pacsData($pacsService,$must);
                    if (!empty($blfzjc)) {
                        $bmcnData = $this->baMrClassNumber($bmcnService,$AAA28,$AAA29,'病理辅助检查报告单');
                        if ($bmcnData) {
                            $cyhzgdlError .= '病理辅助检查报告单【有，有】';
                        } else {
                            $numerator = 0;
                            $cyhzgdlError .= '病理辅助检查报告单【有，无】';
                        }
                    }

                    // 影像辅助检查报告单（影像诊断报告：ExamType=1 或 2）
                    $must = [
                        ['term' => ['JZLSH' => $value['AAA28']]],
                        ['range' => ['KDSJ' => ['gte' => $AAB01,'lte'=>$value['AAC01']]]]
                    ];
                    $should = [['term' => ['ExamType'=>'01']], ['term' => ['ExamType'=>'02']]];
                    $yxfzjc = $this->pacsData($pacsService,$must,$should);
                    if (!empty($yxfzjc)) {
                        $bmcnData = $this->baMrClassNumber($bmcnService,$AAA28,$AAA29,'影像辅助检查报告单');
                        if ($bmcnData) {
                            $cyhzgdlError .= '影像辅助检查报告单【有，有】';
                        } else {
                            $numerator = 0;
                            $cyhzgdlError .= '影像辅助检查报告单【有，无】';
                        }
                    }

                    // 心电图（心电图诊断：ExamType=6）
//                    $must = [
//                        ['term' => ['JZLSH' => $value['AAA28']]],
//                        ['range' => ['KDSJ' => ['gte' => $AAB01,'lte'=>$value['AAC01']]]],
//                        ['term' => ['ExamType'=>'06']]
//                    ];
//                    $xdt = $this->pacsData($pacsService,$must);
//                    if (!empty($xdt)) {
//                        $bmcnData = $this->baMrClassNumber($bmcnService,$AAA28,$AAA29,'心电图');
//                        if ($bmcnData) {
//                            $cyhzgdlError .= '心电图【有，有】';
//                        } else {
//                            $numerator = 0;
//                            $cyhzgdlError .= '心电图【有，无】';
//                        }
//                    }

                    // 其他辅助检查报告单（检查报告单：ExamType=3 或 4 或 5 或 8 或9 或10 或11）
//                    $must = [
//                        ['term' => ['JZLSH' => $value['AAA28']]],
//                        ['range' => ['KDSJ' => ['gte' => $AAB01,'lte'=>$value['AAC01']]]]
//                    ];
//                    $should = [
//                        ['term' => ['ExamType'=>'03']],
//                        ['term' => ['ExamType'=>'04']],
//                        ['term' => ['ExamType'=>'05']],
//                        ['term' => ['ExamType'=>'08']],
//                        ['term' => ['ExamType'=>'09']],
//                        ['term' => ['ExamType'=>'10']],
//                        ['term' => ['ExamType'=>'11']]
//                    ];
//                    $qtfzjc = $this->pacsData($pacsService,$must,$should);
//                    if (!empty($qtfzjc)) {
//                        $bmcnData = $this->baMrClassNumber($bmcnService,$AAA28,$AAA29,'其他辅助检查报告单');
//                        if ($bmcnData) {
//                            $cyhzgdlError .= '其他辅助检查报告单【有，有】';
//                        } else {
//                            $numerator = 0;
//                            $cyhzgdlError .= '其他辅助检查报告单【有，无】';
//                        }
//                    }
                }

                // 检验（检验报告单）
                $must = [['term' => ['ZYH' => $ZYH]], ['term' => ['STAYHOSPITALMODE' => 2]]];
                $params = $ymresultService->clearMust()->queryByMustBatch($must)->getParams();
                $restful = app('es')->search($params);
                $jybgd = $ymresultService->getDataByEs($restful);
                if (!empty($jybgd[0])) {
                    $bmcnData = $this->baMrClassNumber($bmcnService,$AAA28,$AAA29,'检验');
                    if ($bmcnData) {
                        $cyhzgdlError .= '检验报告单【有，有】';
                    } else {
                        $numerator = 0;
                        $cyhzgdlError .= '检验报告单【有，无】';
                    }
                }

                // 医嘱
                $must = [['term' => ['ZYH' => $ZYH]]];
                $params = $yzbService->clearMust()->queryByMustBatch($must)->getParams();
                $restful = app('es')->search($params);
                $yz = $yzbService->getDataByEs($restful);
                if (!empty($yz[0])) {
                    $bmcnData = $this->baMrClassNumber($bmcnService,$AAA28,$AAA29,'医嘱');
                    if ($bmcnData) {
                        $cyhzgdlError .= '医嘱【有，有】';
                    } else {
                        $numerator = 0;
                        $cyhzgdlError .= '医嘱【有，无】';
                    }
                }

                // 记录
                if ($cyhzgdlError) {
                    $saveData = ['denominator_cyhzgdl'=>1,'numerator_cyhzgdl'=>$numerator,'cyhzgdl_error'=>$cyhzgdlError];
                    PatientInfoTargetTemporary::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
                }
            }
        }

        return true;
    }

    protected function bl01Data($bl01Service,$must=[],$should=[])
    {
        if ($should) {
            $params = $bl01Service->clearMust()
                ->queryByMustBatch($must)
                ->queryByShouldBatch($should)
                ->minimumShouldMatch()
                ->getParams();
        } else {
            $params = $bl01Service->clearMust()->queryByMustBatch($must)->getParams();
        }

        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);

        return !empty($bl01Data[0]) ? $bl01Data[0] : [];
    }

    protected function pacsData($pacsService,$must=[],$should=[])
    {
        if ($should) {
            $params = $pacsService->clearMust()
                ->queryByMustBatch($must)
                ->queryByShouldBatch($should)
                ->minimumShouldMatch()
                ->getParams();
        } else {
            $params = $pacsService->clearMust()->queryByMustBatch($must)->getParams();
        }

        $restful = app('es')->search($params);
        $pacsData = $pacsService->getDataByEs($restful);

        return !empty($pacsData[0]) ? $pacsData[0] : [];
    }

    protected function baMrClassNumber($bmcnService,$AAA28,$AAA29,$MrClass)
    {
        $bmcnService = new ElasticsearchService('ba_mr_class_number_2023');
        $must = [
            ['term' => ["patient_id" => $AAA28]],
            ['term' => ["visit_id" => $AAA29]],
            ['match_phrase' => ["MrClass" => $MrClass]]
        ];
        $params = $bmcnService->clearMust()->queryByMustBatch($must)->getParams();
        $restful = app('es')->search($params);
        $bmcnData = $bmcnService->getDataByEs($restful);
        if (!empty($bmcnData[0])) {
            return $bmcnData[0];
        }

        return [];
    }

}
