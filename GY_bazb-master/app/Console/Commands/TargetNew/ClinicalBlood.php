<?php

namespace App\Console\Commands\TargetNew;

use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use App\Model\PatientInfoTargetTemporary;
use App\Model\RuleWordMap;
use App\Model\ZbBagl;
use App\Services\ElasticsearchService;
use App\Services\TargetService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ClinicalBlood extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'newZb:lcyx {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '临床用血相关记录符合率 - 数据处理';

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
        $this->info('临床用血相关记录符合率 - 数据开始处理');

        $page = (int)$this->argument('page') ?: 1;


        // 临床用血相关记录符合率处理
        //        $targetService = new TargetService();
        //        $targetService->clinicalBloodDataHandle($page);

        $this->clinicalBloodDataHandle($page);


        $this->info('临床用血相关记录符合率 - 数据处理完毕');
    }

    protected function clinicalBloodDataHandle($page)
    {
        $bagl_19 = ZbBagl::getFirstById(19, true); //临床用血收费
        $bagl_20 = ZbBagl::getFirstById(20, true); //输血知情同意书MBLB
        $bagl_21 = ZbBagl::getFirstById(21, true); //备血医嘱
        $bagl_22 = ZbBagl::getFirstById(22, true); //输血医嘱
        $bagl_23 = ZbBagl::getFirstById(23, true); //输血效果评估
        $bagl_24 = ZbBagl::getFirstById(24, true); //输血病程记录MBLB
        $bagl_26 = ZbBagl::getFirstById(26, true); //输血效果评估2
        $feeService = new ElasticsearchService('fee_detailed');
        $bl01Service = new ElasticsearchService('bl01_202303');
        $yzbService = new ElasticsearchService('yzb_2023');

        while (true) {
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->whereBetween('AAC01', ['2024-01-01 00:00:00', '2025-12-31 23:59:59']) //配置,住院号
                ->paginate(500, ['AAA28', 'MED_REC_ID', 'ZYH', 'AAC01'], 'page', $page)
                ->toArray();

            if ($page == 1) {
                echo '数据总条数：' . $data['total'] . PHP_EOL . '总页数：' . $data['last_page'] . PHP_EOL;
            } elseif ($page > $data['last_page']) {
                // 如果当前页码大于最大页码则结束
                break;
            }
            echo $page . PHP_EOL;
            $page++;

            // 分母
            $zyhList = array_column($data['data'], 'MED_REC_ID');
            $denominator = $this->feeDetailed1($feeService, $zyhList, $bagl_19);
            if (!$denominator) {
                continue;
            }

            // 分子
            foreach ($denominator as $MED_REC_ID) {
                // 输血同意书
                $bcjlData = [];
                $sxtys = $this->bl01Value($bl01Service, $MED_REC_ID, $bagl_20);

                $sxzltys = '无';
                $zxsj = '';
                if ($sxtys) {
                    $sxzltys = '有';
                    $zxsj = $sxtys;
                }

                // 查询备血
                $bxsxData = [];
                if ($zxsj) {
                    $range = ['range' => ['KZSJ' => ['gte' => $zxsj]]];
                    $bxCount = $this->yzb1($yzbService, $MED_REC_ID, $bagl_21, $range);
                    if ($bxCount) {
                        $bxsxData[] = '备血（有）';
                    } else {
                        $range = ['range' => ['KZSJ' => ['lt' => $zxsj]]];
                        $bxNoCount = $this->yzb1($yzbService, $MED_REC_ID, $bagl_21, $range);
                        $bxsxData[] = $bxNoCount ? '备血（小于完成时间）' : '备血（无）';
                    }
                } else {
                    $bxCount = $this->yzb1($yzbService, $MED_REC_ID, $bagl_21, []);
                    $bxsxData[] = $bxCount ? '备血（有）' : '备血（无）';
                }

                // 查询输
                if ($zxsj) {
                    $range = ['range' => ['KZSJ' => ['gte' => $zxsj]]];
                    $kzsj = $this->yzb1($yzbService, $MED_REC_ID, $bagl_22, $range);
                    if ($kzsj) {
                        $bxsxData[] = '输（有），' . $kzsj . ' > 输血治疗知情同意书完成时间';
                    } else {
                        $range = ['range' => ['KZSJ' => ['lt' => $zxsj]]];
                        $kzsj = $this->yzb1($yzbService, $MED_REC_ID, $bagl_22, $range);
                        $bxsxData[] = $kzsj ? '输（有），' . $zxsj . ' < 输血治疗知情同意书完成时间' : '输（无）';
                    }
                } else {
                    $kzsj = $this->yzb1($yzbService, $MED_REC_ID, $bagl_22, []);
                    if ($kzsj) {
                        $bxsxData[] = '输（有），' . $zxsj . '（输血治疗知情同意书完成时间 无）';
                    } else {
                        $bxsxData[] = '输（无）';
                    }
                }

                // 病程记录 - 输血病程记录
                $lcyxBcjl3 = $this->lcyxBcjl3($bl01Service, $MED_REC_ID, '', $bagl_24);
                $sxbcjlError = $lcyxBcjl3['msg'];
                $sxbcjlData = $lcyxBcjl3['data'];
                $sxbcjl = $lcyxBcjl3['code'] == 200 ? 1 : 0;

                // 病程记录 - 效果 + （血红蛋白 or HGB or Hb）
                //                $ruleMap2038 = RuleWordMap::query()->where('id', '=', 2038)->value('keyword');
                //                $ruleMap2039 = RuleWordMap::query()->where('id', '=', 2039)->value('keyword');
                //$arr = ['血红蛋白','HGB','Hb']; //rulewordmap id 2038或者2039
                //                $arr = array_merge(explode(',', $ruleMap2038),explode(',', $ruleMap2039));
                $arr = explode(',', $bagl_23->keyword);

                $isOk = false;
                $msgArr = [];
                foreach ($arr as $kk => $v) {
                    if ($kk < 3) {
                        $info = $this->lcyxBcjl4($bl01Service, $MED_REC_ID, $sxbcjlData, $v);
                        $msgArr[] = $info['msg'];
                        if ($info['code'] == 200) {
                            $isOk = true;
                            $bcjlData[] = $info['msg'];
                            break;
                        }
                    }
                }
                if (!$isOk) {
                    foreach ($msgArr as $kk => $val) {
                        if ($kk < 3) {
                            $bcjlData[] = $val;
                        }
                    }
                }

                // 判断分子是否符合
                $numerator = 0;
                if ($sxtys && $bxCount && $kzsj && $sxbcjl && $isOk) {
                    $numerator = 1;
                }

                // 记录
                $lcyxError = '费用明细【储血费】' . '输血治疗知情同意书【' . $sxzltys . ' ' . $zxsj . '】' . '，医嘱【' . implode('+', $bxsxData) . '】' . '，输血病程记录【' . $sxbcjlError . '】' . "输血效果【" . implode('+', $bcjlData) . "】";
                $saveData = ['denominator_lcyx' => 1, 'numerator_lcyx' => $numerator, 'lcyx_error' => $lcyxError];
                PatientInfoTarget::query()->updateOrInsert(['ZYH' => $MED_REC_ID], $saveData);
            }
        }

        return true;
    }

    protected function feeDetailed1($feeService, $zyhList, $bagl)
    {
        $should = [];
        foreach ($zyhList as $zyh) {
            $should[] = ["term" => ['MED_REC_ID' => $zyh]];
        }
        $must = [["match_phrase" => ["FYMC" => $bagl->keyword]]];
        $params = $feeService->clearMust()
            ->queryByMustBatch($must)
            ->queryByShouldBatch($should)
            ->paginate(1, 500)
            ->minimumShouldMatch(1)
            ->getParams();
        $restful = app('es')->search($params);
        $feeData = $feeService->getDataByEs($restful);
        $zyhData = [];
        if (!empty($feeData[0])) {
            foreach ($feeData[0] as $value) {
                if (!in_array($value['MED_REC_ID'], $zyhData)) {
                    $zyhData[] = $value['MED_REC_ID'];
                }
            }
        }

        return $zyhData;
    }

    protected function bl01Value($bl01Service, $MED_REC_ID, $bagl)
    {
        $bagl_11 = ZbBagl::getFirstById(11, true);
        $must = [
            ["term" => ['JZHM' => $MED_REC_ID]],
            //            ["term" => ['BLLB' => 329]]
            ["term" => ['MBLB' => $bagl->keyword]]

        ];
        //        $should = [
        //            ["match_phrase" => ['HJNR' => '输血同意书']],
        //            ["match_phrase" => ['HJNR' => '输血治疗知情同意书']]
        //        ];
        $notMust = ['term' => ["BLZT" => 9]];
        $params = $bl01Service->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNot($notMust)
            //            ->queryByShouldBatch($should)
            //            ->minimumShouldMatch(1)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);
        $sxtys = !empty($bl01Data[0][0][$bagl_11->table_field]) ? $bl01Data[0][0][$bagl_11->table_field] : ''; //id 11

        return $sxtys;
    }

    protected function yzb1($yzbService, $MED_REC_ID, $keyValue, $range)
    {
        $must = [
            ["term" => ['ZYH' => $MED_REC_ID]],
            ["match_phrase" => ['YZMC' => $keyValue->keyword]]
        ];
        if ($range) {
            $must[] = $range;
        }

        if ($keyValue->id == 22) {
            $params = $yzbService->clearMust()
                ->queryByMustBatch($must)
                ->orderBy('KZSJ.keyword', 'desc')
                ->getParams();
            $restful = app('es')->search($params);
            $yzbData = $yzbService->getDataByEs($restful);
            $bxCount = !empty($yzbData[0]) ? $yzbData[0][0]['KZSJ'] : '';
        } else {
            $params = $yzbService->clearMust()
                ->queryByMustBatch($must)
                ->getParams();
            $restful = app('es')->search($params);
            $yzbData = $yzbService->getDataByEs($restful);
            $bxCount = !empty($yzbData[0]) ? 1 : 0;
        }

        return $bxCount;
    }

    protected function lcyxBcjl3($bl01Service, $MED_REC_ID, $keyValue, $bagl = null)
    {
        $bagl_11 = ZbBagl::getFirstById(11, true);
        $bagl_24 = ZbBagl::getFirstById(24, true);
        $must = [
            ["term" => ['JZHM' => $MED_REC_ID]],
            //            ["term" =>['BLLB' => 294]],
            ["term" => $bagl ? ['MBLB' => $bagl->keyword] : ['MBLB' => $bagl_24->keyword]]
            //            ["match_phrase" => ['HJNR' => $keyValue]]
        ];
        if ($keyValue) $must[] = ["match_phrase" => ['HJNR' => $keyValue]];
        $notMust = ['term' => ["BLZT" => 9]];
        $params = $bl01Service->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNot($notMust)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);
        $sxbcjl = [];
        if (!empty($bl01Data[0])) {
            foreach ($bl01Data[0] as $value) {
                if (!in_array($value[$bagl_11->table_field], $sxbcjl)) { //id 11
                    $sxbcjl[] = $value[$bagl_11->table_field];
                }
            }
        }

        if ($sxbcjl) {
            return ['code' => 200, 'msg' => '有，' . $sxbcjl[0], 'data' => $sxbcjl];
        }

        return ['code' => 1, 'msg' => '无', 'data' => $sxbcjl];
    }

    protected function lcyxBcjl4($bl01Service, $MED_REC_ID, $zxsjList, $key)
    {
        $isExist = $isExistDate = 0;
        $bagl_26 = ZbBagl::getFirstById(26, true); //输血效果评估2
        $arr1 = [];
        if (!empty($bagl_26->keyword)) {
            if(stripos($bagl_26->keyword,',') !== false) {
                $arr1 = explode(',', $bagl_26->keyword);
            }else{
                $arr1 = [$bagl_26->keyword];
            }

        }

        //        $xgIsExist = $xgIsExistDate = 0;
        foreach ($zxsjList as $zxsjDate) {
            $ZXSJData = [$zxsjDate, Carbon::parse($zxsjDate)->addDay(2)->toDateTimeString()];
            //            $xgData = $this->lcyxBcjl3($bl01Service,$MED_REC_ID,'效果');
            //            $xg = $xgData['data'];
            //            if ($xg) {
            //                // 记录有数据
            //                $xgIsExist = 1;
            //                foreach ($xg as $v) {
            //                    if ($v>=$ZXSJData[0] && $v<=$ZXSJData[1]) {
            //                        // 记录48小时内有数据
            //                        $xgIsExistDate = 1;
            //                        break;
            //                    }
            //                }
            //            }

            $keyData = $this->lcyxBcjl3($bl01Service, $MED_REC_ID, $key);
            $keyData1 = true;
            foreach ($arr1 as $k => $v) {
                $code = $this->lcyxBcjl3($bl01Service, $MED_REC_ID, $v)['code'];
                if($code == 200){
                    $keyData1 = true;
                    break;
                }else{
                    $keyData1 = false;
                }
            }
            if (!empty($keyData1) && !empty($keyData)) {
                $ZXSJ = $keyData['data'];
                if ($ZXSJ) {
                    // 记录有数据
                    $isExist = 1;
                    foreach ($ZXSJ as $v) {
                        if ($v >= $ZXSJData[0] && $v <= $ZXSJData[1]) {
                            // 记录48小时内有数据
                            $isExistDate = 1;
                            break;
                        }
                    }
                }
            } else {
                $isExist = 0;
            }
        }

        //        $xgIsOk = 0;
        //        if ($xgIsExist && $xgIsExistDate) {
        //            $xgMsg = '（有）';
        //            $xgIsOk = 1;
        //        } elseif ((!$xgIsExist && !$xgIsExistDate) && !$xgIsExist) {
        //            $xgMsg = '（无）';
        //        } elseif (!$xgIsExistDate) {
        //            $xgMsg = '（时间超过48小时）';
        //        }

        $isMc = 0;
        if ($isExist && $isExistDate) {
            $str = '有 输血病程记录48小时内';
            $isMc = 1;
        } elseif ((!$isExist && !$isExistDate) && !$isExist) {
            $str = '无';
        } elseif (!$isExistDate) {
            $str = '有 超过输血病程记录48小时';
        }

        //        if ($xgIsOk && $isMc) {
        //            return ['code'=>200,'msg'=>'输血效果'.$xgMsg.' + '.$key.'（48小时内有数据）'];
        //        }
        if ($isMc) {
            return ['code' => 200, 'msg' => $key . '（48小时内有数据）'];
        }

        return ['code' => 1, 'msg' => $key . '（' . $str . '）'];
    }
}
