<?php

namespace App\Console\Commands\TargetNew;

use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Model\FeeDetailed;
use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use App\Model\PatientInfoTargetTemporary;
use App\Model\Yzb;
use App\Model\ZbBagl;
use App\Services\ElasticsearchService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class Hzqjjl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'newZb:hzqjjl {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '患者抢救记录符合率 - 数据处理';

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
        $this->info('患者抢救记录符合率 - 开始处理');

        $page = (int)$this->argument('page') ?: 1;
//        $this->hzqjjlDataHandle($page);
        $this->hzqjjlEsDataHandle($page);

        $this->info('患者抢救记录符合率 - 处理完毕');
    }

    /**
     * @param $page
     * @return true
     */
    protected function hzqjjlEsDataHandle($page)
    {
        $feeDetailedService = new ElasticsearchService('fee_detailed');
        $bl01esService = new ElasticsearchService('bl01_202303');
        $bagl_11 = ZbBagl::getFirstById(11,true);
        while (true) {
            // 所有符合条件的病例信息
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

                // 分母
                $must = [
                    ["term" => ['MED_REC_ID' => $ZYH]],
                    ["match_phrase" => ['FYMC' => '抢救']]
                ];
                $params = $feeDetailedService->clearMust()->queryByMustBatch($must)->getParams();
                $restful = app('es')->search($params);
                $feeData = $feeDetailedService->getDataByEs($restful);
                $fymc = !empty($feeData[0][0]['pre_FYMC']) ? $feeData[0][0]['pre_FYMC'] : '';
                if (!$fymc) {
                    $fymc = !empty($feeData[0][0]['FYMC']) ? $feeData[0][0]['FYMC'] : '';
                    if (!$fymc) {
                        continue;
                    }
                }

                $cjsj = EMR_BL_BL01::query()
                    ->leftJoin('EMR_BL_BLXG','EMR_BL_BL01.BLBH','=','EMR_BL_BLXG.BLBH')
                    ->where('JZHM','=',$ZYH)
                    ->where('BLZT','!=',9)
                    ->where('EMR_BL_BLXG.HJNR','like',"%抢救记录%")
                    ->value($bagl_11->table_field); //id11

                // 分子查询医嘱
                $yzbData = Yzb::query()
                    ->where('ZYH','=',$ZYH)
                    ->where('YZMC','like',"%抢救%")
                    ->distinct()
                    ->get(['YZMC','KZSJ'])->toArray();
                $numerator = 1;
                $errorData = [];
                $hzqjjlError = '收费项目【'.$fymc.'】';
                if ($yzbData) {
                    foreach ($yzbData as $val) {
                        $yzmc = $val['YZMC'];
                        $kzsj = $val['KZSJ'];
                        if ($cjsj) {
                            $xzjdsjEnd = date('Y-m-d H:i:s',strtotime($kzsj)+(3600*6));
                            if ($kzsj < $cjsj && $cjsj < $xzjdsjEnd) {
                                $errorData[] = '医嘱【'.$yzmc.'（'.$kzsj.'）】抢救记录【'.$cjsj.'（6小时内）】';
                            } else {
                                $numerator = 0;
                                $errorData[] = '医嘱【'.$yzmc.'（'.$kzsj.'）】抢救记录【'.$cjsj.'（超6小时）】';
                            }
                        } else {
                            $numerator = 0;
                            $errorData[] = '医嘱【'.$yzmc.'，'.$kzsj.'】抢救记录【无】';
                        }
                    }

                    $hzqjjlError .= implode('，',$errorData);
                } else {
                    $numerator = 0;
                    $hzqjjlError .= '医嘱【无】';
                    if ($cjsj) {
                        $hzqjjlError .= '抢救记录【'.$cjsj.'】';
                    } else {
                        $hzqjjlError .= '抢救记录【无】';
                    }
                }

                // 记录
                $saveData = ['denominator_hzqjjl'=>1,'numerator_hzqjjl'=>$numerator,'hzqjjl_error'=>$hzqjjlError];
                PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
            }
        }

        return true;
    }

    /**
     * @param $page
     * @return true
     */
    protected function hzqjjlDataHandle($page)
    {
        $bagl_11 = ZbBagl::getFirstById(11,true);
        while (true) {
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->leftJoin('patient_info_target','patient_info.MED_REC_ID','=','patient_info_target.ZYH')
                ->whereBetween('AAC01',['2022-01-01 00:00:00','2023-12:31 23:59:59'])
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

                // 分母
                $fymc = FeeDetailed::query()
                    ->where('AAA28','=',$ZYH)
                    ->where('FYMC','like',"%抢救%")
                    ->distinct()
                    ->value('pre_FYMC');
                if (!$fymc) {
                    continue;
                }

                $cjsjList = EMR_BL_BL01::query()
                    ->leftJoin('EMR_BL_BLXG','EMR_BL_BL01.BLBH','=','EMR_BL_BLXG.BLBH')
                    ->where('JZHM','=',$ZYH)
                    ->where('BLZT','!=',9)
                    ->where('HJNR','like',"%抢救记录%")
                    ->orderBy($bagl_11->table_field)
                    ->pluck($bagl_11->table_field)->toArray();

                // 分子查询医嘱
                $yzbData = Yzb::query()
                    ->where('ZYH','=',$ZYH)
                    ->where('YZMC','like',"%抢救%")
                    ->orderBy('KZSJ')
                    ->get(['YZMC','KZSJ'])->toArray();
                $numerator = 1;
                $errorData = [];
                $hzqjjlError = [];
                if ($yzbData) {
                    foreach ($yzbData as $key => $val) {
                        $yzmc = $val['YZMC'];
                        $kzsj = $val['KZSJ'];

                        if ($cjsjList) {
                            foreach ($cjsjList as $v) {
                                $xzjdsjEnd = date('Y-m-d H:i:s',strtotime($kzsj)+(3600*6));
                                if ($kzsj < $v && $v < $xzjdsjEnd) {
                                    $cjsj = $v;
                                    break;
                                } else {
                                    $cjsj = !empty($cjsjList[$key]) ? $cjsjList[$key] : $cjsjList[0];
                                }
                            }

                            $xzjdsjEnd = date('Y-m-d H:i:s',strtotime($kzsj)+(3600*6));
                            if ($kzsj < $cjsj && $cjsj < $xzjdsjEnd) {
                                $errorData[] = '医嘱【'.$yzmc.'（'.$kzsj.'）】抢救记录【'.$cjsj.'（6小时内）】';
                            } else {
                                $numerator = 0;
                                $errorData[] = '医嘱【'.$yzmc.'（'.$kzsj.'）】抢救记录【'.$cjsj.'（超6小时）】';
                            }
                        } else {
                            $numerator = 0;
                            $errorData[] = '医嘱【'.$yzmc.'，'.$kzsj.'】抢救记录【无】';
                        }

                        $hzqjjlError[] = ($key+1).'、收费项目【'.$fymc.'】'.implode('，',$errorData);
                    }
                } else {
                    $numerator = 0;
                    if ($cjsjList) {
                        foreach ($cjsjList as $cjsj) {
                            $hzqjjlError[] = '收费项目【'.$fymc.'】医嘱【无】抢救记录【'.$cjsj.'】';
                        }
                    } else {
                        $hzqjjlError[] = '收费项目【'.$fymc.'】医嘱【无】抢救记录【无】';
                    }
                }

                // 记录
                $saveData = ['denominator_hzqjjl'=>1,'numerator_hzqjjl'=>$numerator,'hzqjjl_error'=>implode('，',$hzqjjlError)];
                PatientInfoTargetTemporary::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
            }
        }

        return true;
    }
}
