<?php

namespace App\Console\Commands\TargetNew;

use App\Model\EMR_BL_BL01;
use App\Model\PACS;
use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use App\Model\PatientInfoTargetTemporary;
use App\Model\Yzb;
use App\Services\ElasticsearchService;
use App\Services\TargetService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class Ircr extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'newZb:ircr {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'CT/MRI检查记录符合率 - 数据处理';

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
        $this->info('CT/MRI检查记录符合率 - 数据开始处理');

        $page = (int)$this->argument('page') ?: 1;

        // CT/MRI检查记录符合率 数据处理
//        $targetService = new TargetService();
//        $targetService->IrcrDataHandle($page);
        $this->IrcrDataHandle($page);

        $this->info('CT/MRI检查记录符合率 - 数据处理完毕');
    }

    protected function IrcrDataHandle($page)
    {
        $pacsService = new ElasticsearchService('pacs');
        $yzbService = new ElasticsearchService('yzb_2023');
        $bl01Service = new ElasticsearchService('bl01_202303');
        while (true) {
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->leftJoin('patient_info_target','patient_info.MED_REC_ID','=','patient_info_target.ZYH')
                ->whereBetween('AAC01',['2022-01-01 00:00:00','2023-12-31 23:59:59'])
                ->paginate(500, ['AAA28','AAB01','AAC01','MED_REC_ID','ZYH'],'page', $page)
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

                // 查询报告单
                $bgdData = $this->bgd($pacsService,$ZYH,$val['AAA28'],$val['AAB01'],$val['AAC01']);
                if ($bgdData) {
                    if (count($bgdData) > 100) {
                        continue;
                    }

                    $ctError = [];
                    $num = $okNum = $index = 0;
                    foreach ($bgdData as $pacs) {
                        $jcmcList = explode(',',$pacs['JCMC']);
                        foreach ($jcmcList as $jcmc) {
                            if (stripos($jcmc,'CT') === false && stripos($jcmc,'MR') === false && stripos($jcmc,'磁共振') === false) {
                                continue;
                            }

                            $num++;
                            $ctError[$index] = ($index+1).'、检查报告单【'.$jcmc.'（'.$pacs['BGSJ'].'）】';

                            // 查询医嘱是否符合
                            $yz = $this->yzb($yzbService,$ZYH,$jcmc);
                            $ctError[$index] .= $yz['msg'];

                            // 病程记录
                            $bcjl = $this->bcjl($bl01Service,$ZYH,$jcmc,$pacs['BGSJ']);
                            $ctError[$index] .= $bcjl['msg'];

                            // 判断分子是否符合
                            if ($yz['is_error'] == 200 && $bcjl['is_error'] == 200) {
                                $okNum++;
                            }
                            $index++;
                        }
                    }

                    $numerator = $num==$okNum ? 1 : 0;

                    $saveData = ['denominator_ct'=>1,'numerator_ct'=>$numerator,'ct_error'=>implode("，",$ctError)];
                    PatientInfoTargetTemporary::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
                }
            }
        }

        return true;
    }

    protected function bgd($pacsService,$ZYH,$AAA28,$AAB01,$AAC01)
    {
        if (!$AAB01 || !$AAC01) {
            return [];
        }

        $must = [
            ["term" => ['JZLSH' => $AAA28]],
            ['range' => ['JYSJ' => ['gte' => $AAB01, 'lte' => $AAC01]]],
        ];
        $notMust = [
            ['match_phrase' => ["JCMC" => "OCT"]]
        ];
        $params = $pacsService->clearMust()->queryByMustNotBatch($notMust)->queryByMustBatch($must)->getParams();
        $restful = app('es')->search($params);
        $pacsData = $pacsService->getDataByEs($restful);

        $list = [];
        foreach ($pacsData[0] as $value) {
            if (!empty($value['YXZD'])) {
                $jcmc = str_replace('OCT','',$value['JCMC']);
                if (stripos($jcmc,'CT') !== false || stripos($jcmc,'MR') !== false || stripos($jcmc,'磁共振') !== false) {
                    $list[] = [
                        'ZYH' => $value['ZYH'],
                        'JZLSH' => $value['JZLSH'],
                        'JCMC' => $value['JCMC'],
                        'BGSJ' => $value['BGSJ']
                    ];
                }
            }
        }

        return $list;
    }

    protected function yzb($yzbService,$ZYH,$jcmc)
    {
        $value = '';
        if (stripos($jcmc,'CT') !== false) {
            $value = 'CT';
        } elseif (stripos($jcmc,'MR') !== false) {
            $value = 'MR';
        } elseif (stripos($jcmc,'磁共振') !== false) {
            $value = '磁共振';
        }

        if ($value != 'MR') {
            $must = [
                ["term" => ['ZYH' => $ZYH]],
                ["match_phrase" => ['YZMC' => $value]]
            ];
            $notMust = [
                'term' => ["YZMC" => 'oct']
            ];
            $params = $yzbService->clearMust()->queryByMustNot($notMust)->queryByMustBatch($must)->getParams();
            $restful = app('es')->search($params);
            $yzbData = $yzbService->getDataByEs($restful);
            $count = !empty($yzbData[0]) ? 1 :0;
        } else {
            $count = Yzb::query()
                ->where('ZYH', '=', $ZYH)
                ->where('YZMC','not like',"%oct%")
                ->where('YZMC','like',"%".$value."%")
                ->count();
        }

        if ($count) {
            return ['is_error'=>200,'msg'=>'医嘱【'.$jcmc.'（有）】'];
        }

        return ['is_error'=>1,'msg'=>'医嘱【'.$jcmc.'（无）】'];
    }

    protected function bcjl($bl01Service,$zyh,$jcmc,$bgsj)
    {
        $keyVal = '';
        if (stripos($jcmc,'CT') !== false) {
            $keyVal = 'CT';
        } elseif (stripos($jcmc,'MR') !== false || stripos($jcmc,'磁共振') !== false) {
            $keyVal = 'MR';
        }

        // 24小时记录类
        $data = $this->bcjl24Class($bl01Service,$zyh,$keyVal);
        if ($data) {
            return $data;
        }

        // 病程记录
        return $this->bcjl294($bl01Service,$zyh,$bgsj,$keyVal);

    }

    protected function bcjl24Class($bl01Service,$zyh,$keyVal)
    {
        $bcjlTitle = ['CT'=>'含“CT（OCT除外）”','MR'=>'含“MR”','磁共振'=>'含“磁共振”'];

        $must = [
            ["term" => ['JZHM' => $zyh]],
            ["term" => ['BLLB' => 18]],
            ["match_phrase" => ['HJNR' => $keyVal]]
        ];
        $notMust = [
            ['term' => ["BLZT" => 9]],
            ['term' => ["BLMC" => "首次病程记录"]]
        ];
        $params = $bl01Service->clearMust()->queryByMustNotBatch($notMust)->queryByMustBatch($must)->getParams();
        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);
        if (!empty($bl01Data[0])) {
            return ['is_error'=>200,'msg'=>$bcjlTitle[$keyVal]];
        } else {
            if ($keyVal == 'MR') {
                $must = [
                    ["term" => ['JZHM' => $zyh]],
                    ["term" => ['BLLB' => 18]],
                    ["match_phrase" => ['HJNR' => "磁共振"]]
                ];
                $notMust = [
                    ['term' => ["BLZT" => 9]],
                    ['match_phrase' => ["BLMC" => "首次病程记录"]]
                ];
                $params = $bl01Service->clearMust()->queryByMustNotBatch($notMust)->queryByMustBatch($must)->getParams();
                $restful = app('es')->search($params);
                $bl01Data = $bl01Service->getDataByEs($restful);
                if (!empty($bl01Data[0])) {
                    return ['is_error'=>200,'msg'=>'24小时内记录【含“磁共振”】'];
                }
            }
        }

        return [];
    }

    protected function bcjl294($bl01Service,$zyh,$bgsj,$keyVal)
    {
        $bcjlTitle = ['CT'=>'含“CT（OCT除外）”','MR'=>'含“MR”','磁共振'=>'含“磁共振”'];

        $must = [
            ["term" => ['JZHM' => $zyh]],
            ["term" => ['BLLB' => 294]],
            ["match_phrase" => ['HJNR' => $keyVal]],
            ["range" => ['ZXSJ' => ['gte' => $bgsj]]]
        ];
        $notMust = [
            ['term' => ["BLZT" => 9]],
            ['match_phrase' => ["BLMC" => "首次病程记录"]]
        ];
        $params = $bl01Service->clearMust()->queryByMustNotBatch($notMust)->queryByMustBatch($must)->getParams();
        $restful = app('es')->search($params);
        $dataList = $bl01Service->getDataByEs($restful);
        $data = !empty($dataList[0]) ? $dataList[0] : [];
        if (!empty($data[0])) {
            return ['is_error'=>200,'msg'=>'病程记录【'.$bcjlTitle[$keyVal]];
        } else {
            if ($keyVal == 'MR') {
                $must = [
                    ["term" => ['JZHM' => $zyh]],
                    ["term" => ['BLLB' => 294]],
                    ["match_phrase" => ['HJNR' => "磁共振"]],
                    ["range" => ['ZXSJ' => ['gte' => $bgsj]]]
                ];
                $params = $bl01Service->clearMust()->queryByMustNotBatch($notMust)->queryByMustBatch($must)->getParams();
                $restful = app('es')->search($params);
                $data = $bl01Service->getDataByEs($restful);
                if (!empty($data[0])) {
                    return ['is_error'=>200,'msg'=>'病程记录【'.$bcjlTitle[$keyVal]];
                }
            }
        }

        return ['is_error'=>1,'msg'=>'病程记录【无】'];
    }


}
