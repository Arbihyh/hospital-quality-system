<?php

namespace App\Console\Commands\Target;

use App\Model\EMR_BL_BL01;
use App\Model\PatientHospitalInfo;
use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use App\Model\ZbBagl;
use App\Model\ZY_HCMX;
use App\Services\ElasticsearchService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Ryjl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zb:ryjl {page?}';

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
        $conf = config('confAdmin');
        $endTime = date('Y-m-d H:i:s', time());

        $cyzs = ZbBagl::getFirstById(1,true); //出院总数 分母
        $hsfcsj = ZbBagl::getFirstById(2,true); //护士分床时间
        $bcjlwcsj = ZbBagl::getFirstById(3,true); //病程记录完成时间
        $bcjlbllb = ZbBagl::getFirstById(4,true); //病程记录BLLB
        $ryjlwcsj = ZbBagl::getFirstById(5,true); //入院记录完成时间

        $bllbs = explode(',',$bcjlbllb->keyword);

        $hsfcsjWhere = [];
        if(stripos(',',$hsfcsj->condition)){
            $wheres = explode(',',$hsfcsj->condition);
            foreach ($wheres as $val){
                $where = explode('=',$val);
                $hsfcsjWhere[] = ['term' => [$where[0] => $where[1]]];
            }
        }elseif(!empty($hsfcsj->condition)){
            $where = explode('=',$hsfcsj->condition);
            $hsfcsjWhere[] = ['term' => [$where[0] => $where[1]]];
        }


        $zyHcmxService = new ElasticsearchService($hsfcsj->table_name);
        $bl01Service = new ElasticsearchService($bcjlwcsj->table_name);
        while (true) {
//            $data = PatientInfo::query()
            $data = DB::table($cyzs->table_name)
                ->leftJoin('patient_info_target','patient_info.MED_REC_ID','=','patient_info_target.ZYH')
                ->when($cyzs->condition,function($query)use($cyzs){
                    return $query->whereRaw($cyzs->condition);
                })
                ->whereBetween('AAC01',[$conf['zb_start_time'], $endTime])
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
                $ZYH = data_get($value,'MED_REC_ID');
                $must = [
                    ["term" => [$hsfcsj->MED_REC_ID => $ZYH]],
//                    ["term" => ['HCLX' => 0]]
                ];

                $must = array_merge($must,$hsfcsjWhere);

                $params = $zyHcmxService->clearMust()->queryByMustBatch($must)->orderBy($hsfcsj->table_field,'asc')->getParams();
                $restful = app('es')->search($params);
                $zyHcmxData = $zyHcmxService->getDataByEs($restful);
                $HCRQ = !empty($zyHcmxData[0][0][$hsfcsj->table_field]) ? $zyHcmxData[0][0][$hsfcsj->table_field] : '';

                $numerator = 0;
                $ryjlError = '入院时间【无】，入院记录【无】';
                if ($HCRQ) {
                    $ryjlError = '入院时间【'.$HCRQ.'】';
                    $HCRQ = Carbon::parse($HCRQ)->addHours($ryjlwcsj->keyword)->toDateTimeString();

                    $must = [
                        ["term" => ['JZHM' => $ZYH]],
                        ["term" => ['BLLB' => $bllbs[0]]],
                        ['range' => ['CJSJ' => ['lte' => $HCRQ]]]
                    ];

                    $params = $bl01Service->clearMust()->queryByMustBatch($must)->queryByMustNot(["term" => ['BLZT' => 9]])->getParams();
                    $restful = app('es')->search($params);
                    $bl01Data = $bl01Service->getDataByEs($restful);
                    $CJSJ = !empty($bl01Data[0][0][$bcjlwcsj->table_field]) ? $bl01Data[0][0][$bcjlwcsj->table_field] : '';
                    //var_dump($bl01Data,$must,$bcjlwcsj->table_name);die;
                    if ($CJSJ) {
                        $numerator = 1;
                        $ryjlError .= '，入院记录【'.$CJSJ.'（24小时内）】';
                    } else {
                        $must = [
                            ["term" => ['JZHM' => $ZYH]],
                            ["term" => ['BLLB' => $bllbs[1]]],
                            ['range' => ['CJSJ' => ['lte' => $HCRQ]]]
                        ];
                        $params = $bl01Service->clearMust()->queryByMustBatch($must)->queryByMustNot(["term" => ['BLZT' => 9]])->getParams();
                        $restful = app('es')->search($params);
                        $bl01Data = $bl01Service->getDataByEs($restful);
                        $CJSJ = !empty($bl01Data[0][0][$bcjlwcsj->table_field]) ? $bl01Data[0][0][$bcjlwcsj->table_field] : '';
                        if ($CJSJ) {
                            $numerator = 1;
                            $ryjlError .= '，24小时出入院记录【'.$CJSJ.'（24小时内）】';
                        } else {
                            $must = [
                                ["term" => ['JZHM' => $ZYH]],
                                ["term" => ['BLLB' => $bllbs[0]]],
                                ['range' => ['CJSJ' => ['gt' => $HCRQ]]]
                            ];
                            $params = $bl01Service->clearMust()->queryByMustBatch($must)->queryByMustNot(["term" => ['BLZT' => 9]])->getParams();
                            $restful = app('es')->search($params);
                            $bl01Data = $bl01Service->getDataByEs($restful);
                            $CJSJ = !empty($bl01Data[0][0][$bcjlwcsj->table_field]) ? $bl01Data[0][0][$bcjlwcsj->table_field] : '';
                            if ($CJSJ) {
                                $ryjlError .= '，入院记录【'.$CJSJ.'（创建时间超24小时）】';
                            } else {
                                $must = [
                                    ["term" => ['JZHM' => $ZYH]],
                                    ["term" => ['BLLB' => $bllbs[1]]],
                                    ['range' => ['CJSJ' => ['gt' => $HCRQ]]]
                                ];
                                $params = $bl01Service->clearMust()->queryByMustBatch($must)->queryByMustNot(["term" => ['BLZT' => 9]])->getParams();
                                $restful = app('es')->search($params);
                                $bl01Data = $bl01Service->getDataByEs($restful);
                                $CJSJ = !empty($bl01Data[0][0][$bcjlwcsj->table_field]) ? $bl01Data[0][0][$bcjlwcsj->table_field] : '';
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
                PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
            }
        }

        return true;
    }

    protected function ryjlDataHandle($page)
    {
        while (true) {
            // 所有符合条件的病例信息
//            $data = PatientHospitalInfo::query()
//                ->leftJoin('patient_info_target','patient_hospital_info.AAA28','=','patient_info_target.ZYH')
//                ->whereBetween('patient_hospital_info.AAB01',['2022-01-01 00:00:00','2022-12:31 23:59:59'])
//                ->paginate(500, ['AAA28','ZYH','patient_hospital_info.AAB01'],'page', $page)
//                ->toArray();
            $data = PatientInfo::query()
                ->leftJoin('patient_info_target','patient_info.MED_REC_ID','=','patient_info_target.ZYH')
                ->whereBetween('AAC01',['2022-01-01 00:00:00','2022-12:31 23:59:59'])
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
                if (empty($value['AAC01'])) {
                    continue;
                }
                $ZYH = $value['MED_REC_ID'];

                if (empty($value['ZYH'])) {
                    PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH]);
                }

                $HCRQ = ZY_HCMX::query()
                    ->where('ZYH','=',$ZYH)
                    ->where('HCLX','=',0)
                    ->orderBy('HCRQ')
                    ->value('HCRQ');

                $numerator = 0;
                $ryjlError = '入院时间【无】，入院记录【无】';
                if ($HCRQ) {
                    $ryjlError = '入院时间【'.$HCRQ.'】';
                    $HCRQ = Carbon::parse($HCRQ)->addDay(1)->toDateTimeString();
                    $CJSJ = EMR_BL_BL01::query()
                        ->where('JZHM','=',$ZYH)
                        ->where('BLLB','=',292)
                        ->where('CJSJ','<=',$HCRQ)
                        ->value('CJSJ');
                    if ($CJSJ) {
                        $numerator = 1;
                        $ryjlError .= '，入院记录【'.$CJSJ.'（24小时内有数据）】';
                    } else {
                        $CJSJ = EMR_BL_BL01::query()
                            ->where('JZHM','=',$ZYH)
                            ->where('BLLB','=',18)
                            ->where('CJSJ','<=',$HCRQ)
                            ->value('CJSJ');
                        if ($CJSJ) {
                            $numerator = 1;
                            $ryjlError .= '，24小时出入院记录【'.$CJSJ.'（24小时内有数据）】';
                        } else {
                            $CJSJ = EMR_BL_BL01::query()
                                ->where('JZHM','=',$ZYH)
                                ->where('BLLB','=',292)
                                ->where('CJSJ','>',$HCRQ)
                                ->value('CJSJ');
                            if ($CJSJ) {
                                $ryjlError .= '，入院记录【'.$CJSJ.'（超过24小时）】';
                            } else {
                                $ryjlError .= '，入院记录【无】';
                            }
                        }
                    }
                }

                // 记录
                $saveData = ['denominator_ryjl'=>1,'numerator_ryjl'=>$numerator,'ryjl_error'=>$ryjlError];
                PatientInfoTarget::query()->where('ZYH',$ZYH)->update($saveData);
            }
        }

        return true;
    }

}
