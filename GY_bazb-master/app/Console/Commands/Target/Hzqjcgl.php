<?php

namespace App\Console\Commands\Target;

use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;

class Hzqjcgl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zb:hzqjcgl {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '患者抢救成功率 - 数据处理';

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
        $this->info('患者抢救成功率 - 数据开始处理');

        $page = (int)$this->argument('page') ?: 1;

        // 细菌培养检查记录符合率处理
        $this->hzqjcglDataHandle($page);

        $this->info('患者抢救成功率 - 数据处理完毕');
    }

    protected function hzqjcglDataHandle($page)
    {
        $conf = config('confAdmin');
        $endTime = date('Y-m-d H:i:s', time());
        $feeService = new ElasticsearchService('fee_detailed');
        $bl01Service = new ElasticsearchService('bl01_202303');
        $yzbService = new ElasticsearchService('yzb_2023');

        while (true) {
            $data = PatientInfo::query()
                ->whereBetween('AAC01', [$conf['zb_start_time'], $endTime])
                ->paginate(500, ['AAA28', 'MED_REC_ID', 'AAB01', 'AAC01'], 'page', $page)
                ->toArray();
            if ($page == 1) {
                echo '数据总条数：' . $data['total'] . PHP_EOL . '总页数：' . $data['last_page'] . PHP_EOL;
            } elseif ($page > $data['last_page']) {
                // 如果当前页码大于最大页码则结束
                break;
            }

            echo $page . PHP_EOL;
            $page++;

            foreach ($data['data'] as $val) {
                $ZYH = $val['MED_REC_ID'];

                // 分母
                $feeList = $this->feeDetailed($feeService,$ZYH);
                if (empty($feeList)) {
                    continue;
                }
                $JFQR = !empty($feeList[0]['JFQR']) ? $feeList[0]['JFQR'] : '无';

                // 分子 - 医嘱
                $yzbList = $this->yzb($yzbService,$ZYH);

                // 分子 - 病程记录
                $bcjlList = $this->bcjl($bl01Service,$ZYH);
                $numerator = 0;
                if (!empty($bcjlList)) {
                    foreach ($bcjlList as $bcjl) {
                        if ($yzbList) {
                            foreach ($yzbList as $value) {
                                $KZSJ_START = $value['KZSJ'];
                                $KZSJ_END = date('Y-m-d H:i:s', strtotime($value['KZSJ'])+(3600*6));
                                //$KZSJ_START <= $bcjl['CJSJ'] &&
                                if ($bcjl['CJSJ'] <= $KZSJ_END) {
                                    $numerator = 1;
                                    $errorDate = '收费项目【'.$feeList[0]['FYMC'].'，'.$JFQR.'】，医嘱【'.$value['YZMC'].'，'.$value['KZSJ'].'】，病程记录【'.$bcjl['CJSJ'].'，不含“死亡”，6小时内】';
                                } else {
                                    $errorDate = '收费项目【'.$feeList[0]['FYMC'].'，'.$JFQR.'】，医嘱【'.$value['YZMC'].'，'.$value['KZSJ'].'】，病程记录【'.$bcjl['CJSJ'].'，不含“死亡”，未在6小时内】';
                                }
                            }
                        } else {
                            $errorDate = '收费项目【'.$feeList[0]['FYMC'].'，'.$JFQR.'】，医嘱【无】，病程记录【'.$bcjl['CJSJ'].'】';
                        }
                    }
                } else {
                    $errorDate = '收费项目【'.$feeList[0]['FYMC'].'，'.$JFQR.'】，病程记录【无】';
                }

                // 记录
                $saveData = ['denominator_hzqjcgl' => 1, 'numerator_hzqjcgl' => $numerator, 'hzqjcgl_error' => $errorDate];
                PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
            }
        }

        return true;
    }

    protected function yzb($yzbService,$ZYH)
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

    protected function feeDetailed($feeService,$ZYH)
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

    protected function bcjl($bl01Service,$ZYH)
    {
        $must = [
            ["term" => ['JZHM' => $ZYH]],
            ['match_phrase' => ['HJNR'=>'抢救']]
        ];
        $notMust = [
            ['match_phrase' => ['HJNR'=>'死亡']]
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
                $bl01List[] = $value;//$value['ZXSJ'];
            }
        }

        return $bl01List;
    }


}
