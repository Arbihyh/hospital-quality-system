<?php

namespace App\Console\Commands\Target;

use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use App\Model\ZbBagl;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;

class Zqtysgfqs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zb:zqtysgfqs {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '知情同意书规范签署 - 数据处理';

    private $bagl_11;

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
        $this->bagl_11 = ZbBagl::getFirstById(11,true);
        $this->info('知情同意书规范签署 - 数据开始处理');

        $page = (int)$this->argument('page') ?: 1;

        // 细菌培养检查记录符合率处理
        $this->zqtysgfqsDataHandle($page);

        $this->info('知情同意书规范签署 - 数据处理完毕');
    }

    protected function zqtysgfqsDataHandle($page)
    {
        $conf = config('confAdmin');
        $endTime = date('Y-m-d H:i:s', time());
        $bl01Service = new ElasticsearchService('bl01_202303');
        $yzbService = new ElasticsearchService('yzb_2023');
        $mzjlService = new ElasticsearchService('mzjl_2023');
        $blsyService = new ElasticsearchService('blsy_2023');
        $staffService = new ElasticsearchService('staff_2023');

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
                $bl01Data = $this->bl01($bl01Service,$ZYH);
                if (empty($bl01Data)) {
                    continue;
                }

                // 分子 - 授权同意类
                $sqtylError = '';
                $sqtylIsOk = 0;
                foreach ($bl01Data as $value) {
                    $blsy = $this->blsy($blsyService,$value['BLBH']);
                    if ($blsy) {
                        $str = '未在出入院时间之内';
                        if ($value['AAB01'] <= $blsy[0]['SYSJ'] && $blsy[0]['SYSJ'] <= $value['AAC01']) {
                            $str = '在出入院时间之内';
                        }
                        $name = $this->staff($staffService,$blsy[0]['SYYS']);
                        if ($name && $str=='在出入院时间之内') {
                            $sqtylIsOk = 1;
                            $sqtylError = '授权同意类【'.$name.'，'.$blsy[0]['SYSJ'].'，'.$str.'】';
                            break;
                        } else {
                            $name = '无';
                            $sqtylError = '授权同意类【'.$name.'，'.$blsy[0]['SYSJ'].'，'.$str.'】';
                        }
                    } else {
                        $sqtylError = '授权同意类【无】';
                    }
                }

                // 分子 - 输血治疗同意书
                $sxzltysData = $this->sxzltys($bl01Service,$ZYH);
                $sxzlIsOk = 0;
                if ($sxzltysData) {
                    $sxsj = $this->sxsj($bl01Service,$ZYH);
                    foreach ($sxzltysData as $value) {
                        if ($value[data_get($this->bagl_11,'table_field')] <= $sxsj) {
                            $sxzlIsOk = 1;
                            $sxzltysError = '输血治疗同意书【'.$value[data_get($this->bagl_11,'table_field')].' < 输血时间：'.$sxsj.'】';
                            break;
                        } else {
                            $sxzltysError = '输血治疗同意书【'.$value[data_get($this->bagl_11,'table_field')].' > 输血时间：'.$sxsj.'】';
                        }
                    }
                } else {
//                    $sxzltysError = '输血治疗同意书【无】';
                    $sxzltysError = '';
                    $sxzlIsOk = 1;
                }

                // 分子 - 手术知情同意书
                $sszqtysData = $this->sszqtys($bl01Service,$ZYH);
                $sszqtyIsOk = 0;
                if ($sszqtysData) {
                    // 查询麻醉记录
                    $mzjlData = $this->mzjl($mzjlService,$ZYH);
                    if ($mzjlData) {
                        foreach ($sszqtysData as $value) {
                            if ($value[data_get($this->bagl_11,'table_field')] <= $mzjlData[0]['OPERATESTARTTIME']) {
                                $sszqtyIsOk = 1;
                                $sszqtysError = '手术知情同意书【'.$value[data_get($this->bagl_11,'table_field')].' ＜ 手术开始时间：'.$mzjlData[0]['OPERATESTARTTIME'].'】';
                                break;
                            } else {
                                $sszqtysError = '手术知情同意书【'.$value[data_get($this->bagl_11,'table_field')].' > 手术开始时间：'.$mzjlData[0]['OPERATESTARTTIME'].'】';
                            }
                        }
                    } else {
                        $sszqtysError = '手术知情同意书【'.$sszqtysData[0][data_get($this->bagl_11,'table_field')].' ＜ 手术开始时间：无】';
                    }
                } else {
                    $sszqtysError = '手术知情同意书【无】';
                }

                $numerator = 0;
                if ($sqtylIsOk && $sxzlIsOk && $sszqtyIsOk) {
                    $numerator = 1;
                }
                $errorDate = $sqtylError.$sxzltysError.$sszqtysError;

                // 记录
                $saveData = ['denominator_zqtysgfqs' => 1, 'numerator_zqtysgfqs' => $numerator, 'zqtysgfqs_error' => $errorDate];
                PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
            }
        }
    }

    protected function bl01($bl01Service,$ZYH)
    {
        $must = [
            ["term" => ['JZHM' => $ZYH]],
            ["term" => ['BLLB' => 329]]
        ];
        $params = $bl01Service->clearMust()
            ->queryByMustBatch($must)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);

        return !empty($bl01Data[0]) ? $bl01Data[0] : [];
    }

    protected function blsy($blsyService,$BLBH)
    {
        $must = [
            ["term" => ['BLBH' => $BLBH]]
        ];
        $params = $blsyService->clearMust()
            ->queryByMustBatch($must)
            ->getParams();
        $restful = app('es')->search($params);
        $blsyData = $blsyService->getDataByEs($restful);

        return !empty($blsyData[0]) ? $blsyData[0] : [];
    }

    protected function staff($staffService,$SYYS)
    {
        $must = [
            ["term" => ['code' => $SYYS]]
        ];
        $params = $staffService->clearMust()
            ->queryByMustBatch($must)
            ->getParams();
        $restful = app('es')->search($params);
        $staffData = $staffService->getDataByEs($restful);

        return !empty($staffData[0]) ? $staffData[0][0]['name'] : '';
    }

    protected function sxsj($bl01Service,$ZYH)
    {
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
            ->orderBy(data_get($this->bagl_11,'table_field'),'ASC')
            ->getParams();
        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);

        return !empty($bl01Data[0]) ? $bl01Data[0][0][data_get($this->bagl_11,'table_field')] : '';
    }

    protected function sxzltys($bl01Service,$ZYH)
    {
        $must = [
            ["term" => ['JZHM' => $ZYH]],
            ["term" => ['MBLB' => 59]]
        ];
        $params = $bl01Service->clearMust()
            ->queryByMustBatch($must)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);

        return !empty($bl01Data[0]) ? $bl01Data[0] : [];
    }

    protected function sszqtys($bl01Service,$ZYH)
    {
        $must = [
            ["term" => ['JZHM' => $ZYH]],
            ["term" => ['BLLB' => 329]]
        ];
        $params = $bl01Service->clearMust()
            ->queryByMustBatch($must)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);

        return $bl01Data[0];
    }

    protected function mzjl($mzjlService,$ZYH)
    {
        $must = [
            ["term" => ['HOSPIZATIONID' => $ZYH]]
        ];
        $params = $mzjlService->clearMust()
            ->queryByMustBatch($must)
            ->getParams();
        $restful = app('es')->search($params);
        $mzjlData = $mzjlService->getDataByEs($restful);

        return $mzjlData[0];
    }


}
