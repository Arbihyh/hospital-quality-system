<?php

namespace App\Console\Commands\TargetNew;

use App\Model\EMR_BL_BL01;
use App\Model\PatientInfo;
use App\Model\PatientInfoTargetTemporary;
use App\Model\ZY_HCMX;
use App\Services\ElasticsearchService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class Ryjl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'newZb:ryjl {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '入院记录24小时内完成率 - 数据处理';

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
        $this->info('入院记录24小时内完成率 - 开始处理');

        $page = (int)$this->argument('page') ?: 1;
        $this->ryjlEsDataHandle($page);

        $this->info('入院记录24小时内完成率 - 处理完毕');
    }

    protected function ryjlEsDataHandle($page)
    {
        $zyHcmxService = new ElasticsearchService('zy_hcmx');
        $bl01Service = new ElasticsearchService('bl01_202303');
        $fm = 0;
        $fz = 0;
        while (true) {
            $data = PatientInfo::query()
                ->leftJoin('patient_info_target','patient_info.MED_REC_ID','=','patient_info_target.ZYH')
                ->whereBetween('AAC01',['2022-01-01 00:00:00','2023-12-31 23:59:59'])
                ->paginate(500, ['AAA28','MED_REC_ID','ZYH','AAB01','AAC01'],'page', $page)
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
                $ZYH = $value['MED_REC_ID'];
                $must = [
                    ["term" => ['ZYH' => $ZYH]],
                    ["term" => ['HCLX' => 0]]
                ];
                $params = $zyHcmxService->clearMust()->queryByMustBatch($must)->orderBy('HCRQ','asc')->getParams();
                $restful = app('es')->search($params);
                $zyHcmxData = $zyHcmxService->getDataByEs($restful);
                $HCRQ = !empty($zyHcmxData[0][0]['HCRQ']) ? $zyHcmxData[0][0]['HCRQ'] : '';

                $numerator = 0;
                $ryjlError = '入院时间【无】，入院记录【无】';
                if ($HCRQ) {
                    $ryjlError = '入院时间【'.$HCRQ.'】';
                    $HCRQ = Carbon::parse($HCRQ)->addDay(1)->toDateTimeString();

                    $must = [
                        ["term" => ['JZHM' => $ZYH]],
                        ["term" => ['BLLB' => 292]],
                        ['range' => ['CJSJ' => ['lte' => $HCRQ]]]
                    ];
                    $params = $bl01Service->clearMust()->queryByMustBatch($must)->getParams();
                    $restful = app('es')->search($params);
                    $bl01Data = $bl01Service->getDataByEs($restful);
                    $CJSJ = !empty($bl01Data[0][0]['CJSJ']) ? $bl01Data[0][0]['CJSJ'] : '';
                    if ($CJSJ) {
                        $numerator = 1;
                        $ryjlError .= '，入院记录【'.$CJSJ.'（24小时内）】';
                    } else {
                        $must = [
                            ["term" => ['JZHM' => $ZYH]],
                            ["term" => ['BLLB' => 18]],
                            ['range' => ['CJSJ' => ['lte' => $HCRQ]]]
                        ];
                        $params = $bl01Service->clearMust()->queryByMustBatch($must)->getParams();
                        $restful = app('es')->search($params);
                        $bl01Data = $bl01Service->getDataByEs($restful);
                        $CJSJ = !empty($bl01Data[0][0]['CJSJ']) ? $bl01Data[0][0]['CJSJ'] : '';
                        if ($CJSJ) {
                            $numerator = 1;
                            $ryjlError .= '，24小时出入院记录【'.$CJSJ.'（24小时内）】';
                        } else {
                            $must = [
                                ["term" => ['JZHM' => $ZYH]],
                                ["term" => ['BLLB' => 292]],
                                ['range' => ['CJSJ' => ['gt' => $HCRQ]]]
                            ];
                            $params = $bl01Service->clearMust()->queryByMustBatch($must)->getParams();
                            $restful = app('es')->search($params);
                            $bl01Data = $bl01Service->getDataByEs($restful);
                            $CJSJ = !empty($bl01Data[0][0]['CJSJ']) ? $bl01Data[0][0]['CJSJ'] : '';
                            if ($CJSJ) {
                                $ryjlError .= '，入院记录【'.$CJSJ.'（创建时间超24小时）】';
                            } else {
                                $must = [
                                    ["term" => ['JZHM' => $ZYH]],
                                    ["term" => ['BLLB' => 18]],
                                    ['range' => ['CJSJ' => ['gt' => $HCRQ]]]
                                ];
                                $params = $bl01Service->clearMust()->queryByMustBatch($must)->getParams();
                                $restful = app('es')->search($params);
                                $bl01Data = $bl01Service->getDataByEs($restful);
                                $CJSJ = !empty($bl01Data[0][0]['CJSJ']) ? $bl01Data[0][0]['CJSJ'] : '';
                                if ($CJSJ) {
                                    $ryjlError .= '，24小时出入院记录【'.$CJSJ.'（创建时间超24小时）】';
                                } else {
                                    $ryjlError .= '，入院记录【无】';
                                }
                            }
                        }
                    }
                }

                // 记录
                $saveData = ['denominator_ryjl'=>1,'numerator_ryjl'=>$numerator,'ryjl_error'=>$ryjlError];
                PatientInfoTargetTemporary::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
            }
        }

        return true;
    }


}
