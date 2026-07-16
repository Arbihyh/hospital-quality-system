<?php

namespace App\Console\Commands\Target;

use App\Model\EMR_BL_BL01;
use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use App\Model\V_JMGS_YMresult;
use App\Model\XJPYZD;
use App\Model\Yzb;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;

class Xjpy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zb:xjpy {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '新-细菌培养检查记录符合率指标';

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
        $this->info('新-细菌培养检查记录符合率指标 - 数据开始处理');

        $page = (int)$this->argument('page') ?: 1;

        // 细菌培养检查记录符合率处理
        $this->germFosterDataHandle($page);

        $this->info('新-细菌培养检查记录符合率指标 - 数据处理完毕');
    }

    protected function germFosterDataHandle($page)
    {
        $conf = config('confAdmin');
        $endTime = date('Y-m-d H:i:s', time());
        $vjyService = new ElasticsearchService('v_jmgs_ymresult_2023');
        $bl01Service = new ElasticsearchService('bl01_202303');
        $yzbService = new ElasticsearchService('yzb_2023');

        while (true) {
            $data = PatientInfo::query()
                ->leftJoin('patient_info_target','patient_info.MED_REC_ID','=','patient_info_target.ZYH')
                ->whereBetween('AAC01',[$conf['zb_start_time'], $endTime])
                ->paginate(500, ['AAA28','MED_REC_ID','ZYH','AAC01'],'page', $page)
                ->toArray();
            if ($page == 1) {
                echo '数据总条数：'.$data['total'].PHP_EOL.'总页数：'.$data['last_page'].PHP_EOL;
            } elseif ($page > $data['last_page']) {
                // 如果当前页码大于最大页码则结束
                break;
            }
            echo $page.PHP_EOL;
            $page++;

            foreach ($data['data'] as $val) {
                $ZYH = $val['MED_REC_ID'];
                $xjpyError = [];

                // 查询分母
                $ymresultList = $this->V_JMGS_YMresult($vjyService,$ZYH);
                if (empty($ymresultList)) {
                    continue;
                }

                // 分子
                $index = 0;
                foreach ($ymresultList as $key => $value) {
                    $CJSJ = $value['CJSJ'];
                    $EXAMINAIM = $value['EXAMINAIM'];
                    $EXAMINAIM_CJSJ = $value['CJSJ'];

                    // 分子 - 医嘱查询
                    $yzmc = $this->yzb($yzbService,$ZYH,$EXAMINAIM);
                    if ($yzmc) {
                        $xjpyError[$key] = '医嘱【' . $yzmc . '（有）】';
                    } else {
                        $xjpyError[$key] = '医嘱【' . $yzmc . '（无）】';
                    }

                    // 分子 - 报告单
                    $xjpyError[$key] .='检验报告单【' . $EXAMINAIM . ' '.$CJSJ.'】';

                    // 分子 - 细菌培养报告
                    $xjmcList = $this->V_JMGS_YMresult($vjyService,$ZYH,$EXAMINAIM);

                    // 分子 - 病程
                    $xjmcArr = [];
                    $xjmcCount = 0;
                    if ($xjmcList) {
                        foreach ($xjmcList as $xjmcVal) {
                            $range = ['range' => ['ZXSJ' => ['gte' => $EXAMINAIM_CJSJ]]];
                            $bcjlZXSJ = $this->bl01Value($bl01Service,$ZYH,$xjmcVal,$range);
                            if ($bcjlZXSJ) {
                                $xjmcCount++;
                                $xjmcArr[] = $xjmcVal.' '.$bcjlZXSJ.' > 检验时间';
                            }
                        }
                    }

                    // 分子 - 培养
                    if (empty($xjmcArr)) {
                        $xjmcList[] = '培养';
                        $range = ['range' => ['ZXSJ' => ['gte' => $EXAMINAIM_CJSJ]]];
                        $bcjlZXSJ = $this->bl01Value($bl01Service,$ZYH,'培养',$range);
                        if ($bcjlZXSJ) {
                            $xjmcCount++;
                            $xjmcArr[] = '培养 '.$bcjlZXSJ.' > 检验时间';
                        } else {
                            $range = ['range' => ['ZXSJ' => ['lte' => $EXAMINAIM_CJSJ]]];
                            $bcjlZXSJ = $this->bl01Value($bl01Service,$ZYH,'培养',$range);
                            if ($bcjlZXSJ) {
                                $xjmcArr[] = '培养 '.$bcjlZXSJ.' < 检验时间';
                            }
                        }
                    }

                    if ($yzmc && !empty($xjmcArr)) {
                        $index++;
                    }

                    $xjmcArr = $xjmcArr ?: ['采集时间之后（无）'];
                    $xjpyError[$key] .= '病程记录【' . implode(',', $xjmcArr) . '】';
                }

                $numerator = 0;
                if (!empty($ymresultList) && count($ymresultList) == $index) {
                    $numerator = 1;
                }

                // 记录
                $saveData = ['denominator_xjpy1' => 1, 'numerator_xjpy1' => $numerator, 'xjpy1_error' => implode("，", $xjpyError)];
                PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
            }
        }
        return true;
    }

    protected function V_JMGS_YMresult($vjyService,$ZYH,$EXAMINAIM='')
    {
        $ymresultList = [];
        if ($EXAMINAIM) {
            $must = [
                ["term" => ['ZYH' => $ZYH]],
                ["term" => ['EXAMINAIM' => $EXAMINAIM]],
                ["term" => ['STAYHOSPITALMODE' => 2]]
            ];
            $params = $vjyService->clearMust()
                ->queryByMustBatch($must)
                ->paginate(1,100)
                ->getParams();
            $restful = app('es')->search($params);
            $vjyData = $vjyService->getDataByEs($restful);
            if (!empty($vjyData[0])) {
                foreach ($vjyData[0] as $value) {
                    if ($value['PYJG'] && $value['XJMC'] && !in_array($value['XJMC'],$ymresultList)) {
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
                ->paginate(1,100)
                ->getParams();
            $restful = app('es')->search($params);
            $vjyData = $vjyService->getDataByEs($restful);
            if (!empty($vjyData[0])) {
                $arr = [];
                foreach ($vjyData[0] as $value) {
                    if ($value['XJMC'] && $value['EXAMINAIM'] && !in_array($value['EXAMINAIM'],$arr)) {
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

    protected function yzb($yzbService,$ZYH,$EXAMINAIM)
    {
        $EXAMINAIM_ARR = explode('+',$EXAMINAIM);
        foreach ($EXAMINAIM_ARR as $key => $value) {
            $EXAMINAIM_ARR[$key] = str_replace('加药敏','',$value);
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

    protected function bl01Value($bl01Service,$ZYH,$keyValue,$range=[])
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
}
