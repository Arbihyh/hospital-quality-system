<?php


namespace App\Services;

use App\Model\BA_RECEIVE;
use App\Model\EMR_BL_BL01;
use App\Model\PatientInfoTarget;
use App\Model\Yzb;
use Carbon\Carbon;

class ZbAutoAllService
{
    /**
     * 自动化指标处理
     * @param $patientInfo
     * @param $xjpyFyzdList
     * @param $implantsList
     * @return void
     */
    public function zbAutoAll($patientInfo,$xjpyFyzdList,$implantsList)
    {
        // 所有出院患者
        $this->publicCyjl($patientInfo);

        // 病案首页24小时内完成率
        $this->basy($patientInfo);

        // 临床用血相关记录符合率
        $this->lcyx($patientInfo);

        // 出院患者病历2日归档率
        $this->cyhzgd($patientInfo);

        // 出院患者病历归档完整率
        $this->cyhzgdl($patientInfo);

        // 出院记录24小时内完成率
        $this->cyjl($patientInfo);

        // 细菌培养检查记录符合率
        $this->xjpy($patientInfo,$xjpyFyzdList);

        // 患者抢救成功率
        $this->hzqjcgl($patientInfo);

        // 患者抢救记录符合率
        $this->hzqjjl($patientInfo);

        // 植入物相关记录符合率指标
        $this->zrw($patientInfo,$implantsList);

        // CT/MRI检查记录符合率
        $this->ircr($patientInfo);

        // 入院记录24小时内完成率
        $this->ryjl($patientInfo);

        // 细菌培养检查记录符合率指标-子指标
        $this->xjpyNew($patientInfo);

        // 知情同意书规范签署
        $this->zqtysgfqs($patientInfo);

        // 主要手术编码正确率
        $this->zyssbm($patientInfo);

        // 主要手术填写正确率
        $this->zysstx($patientInfo);

        // 主要诊断编码正确率
        $this->zyzdbm($patientInfo);

        // 主要诊断填写正确率
        $this->zyzdtx($patientInfo);
    }

    /**
     * 病案首页24小时内完成率
     * @param $patientInfo
     * @return void
     */
    public function basy($patientInfo)
    {
        $ZYH = $patientInfo['MED_REC_ID'];

        $bl01NewService = new ElasticsearchService('emr_bl_bl01_new');
        $yzbService = new ElasticsearchService('yzb_2023');

        // 分母 查询出院记录创建时间
        $must = [
            ["term" => ['JZHM' => $ZYH]],
            ["term" => ['BLLB' => 2000001]]
        ];
        $notMust = [
            ["term" => ['BLZT' => 9]],
        ];
        $params = $bl01NewService->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNotBatch($notMust)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01NewData = $bl01NewService->getDataByEs($restful);
        $cjsj = !empty($bl01NewData[0]) ? $bl01NewData[0][0]['CJSJ'] : '';

        // 分子 医嘱查询
        $must = [
            ["term" => ['ZYH' => $ZYH]]
        ];
        $should = [
            ["term" => ['YDYZLB' => 303]],
            ["term" => ['YDYZLB' => 305]],
        ];
        $params = $yzbService->clearMust()
            ->queryByMustBatch($must)
            ->queryByShouldBatch($should)
            ->minimumShouldMatch(1)
            ->getParams();
        $restful = app('es')->search($params);
        $yzbData = $yzbService->getDataByEs($restful);
        $xzjdsjList = [];
        if (!empty($yzbData[0])) {
            foreach ($yzbData[0] as $val) {
                if (!in_array($val['XZJDSJ'],$xzjdsjList)) {
                    $xzjdsjList[] = $val['XZJDSJ'];
                }
            }
        }

        $numerator = 0;
        $basyError = '';
        if ($xzjdsjList) {
            foreach ($xzjdsjList as $xzjdsj) {
                if ($cjsj) {
                    $xzjdsjEnd = Carbon::parse($xzjdsj)->addDay(1)->toDateTimeString();
                    if ($cjsj <= $xzjdsjEnd) {
                        $numerator = 1;
                        $basyError = '出院时间【'.$xzjdsj.'】，病案首页【'.$cjsj.'（24小时内）】';
                        break;
                    } else {
                        $basyError = '出院时间【'.$xzjdsj.'】，病案首页【'.$cjsj.'（创建时间超24小时）】';
                    }
                } else {
                    $basyError = '出院时间【'.$xzjdsj.'】，病案首页【无】';
                }
            }
        } else {
            $str = $cjsj ?? '无';
            $basyError = '出院时间【无】，病案首页【'.$str.'】';
        }

        // 记录
        $saveData = ['denominator_basy'=>1,'numerator_basy'=>$numerator,'basy_error'=>$basyError];
        PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
    }

    /**
     * 临床用血相关记录符合率
     * @param $patientInfo
     * @return void
     */
    public function lcyx($patientInfo)
    {
        $ZYH = $patientInfo['MED_REC_ID'];

        $feeService = new ElasticsearchService('fee_detailed');
        $bl01Service = new ElasticsearchService('bl01_202303');
        $yzbService = new ElasticsearchService('yzb_2023');

        // 分母
        $must = [
            ["term" => ['MED_REC_ID' => $ZYH]],
            ["match_phrase" => ["FYMC" => "储血费"]]
        ];
        $params = $feeService->clearMust()->queryByMustBatch($must)->getParams();
        $restful = app('es')->search($params);
        $feeData = $feeService->getDataByEs($restful);
        if (!empty($feeData[0])) {
            $bcjlData = [];

            // 输血同意书
            $must = [
                ["term" => ['JZHM' => $ZYH]],
                ["term" => ['BLLB' => 329]]
            ];
            $should = [
                ["match_phrase" => ['HJNR' => '输血同意书']],
                ["match_phrase" => ['HJNR' => '输血治疗知情同意书']]
            ];
            $notMust = ['term' => ["BLZT" => 9]];
            $params = $bl01Service->clearMust()
                ->queryByMustBatch($must)
                ->queryByMustNot($notMust)
                ->queryByShouldBatch($should)
                ->minimumShouldMatch(1)
                ->getParams();
            $restful = app('es')->search($params);
            $bl01Data = $bl01Service->getDataByEs($restful);
            $sxtys = !empty($bl01Data[0][0]['ZXSJ']) ? $bl01Data[0][0]['ZXSJ'] : '';

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
                $bxCount = $this->yzb($yzbService,$ZYH,'备血',$range);
                if ($bxCount) {
                    $bxsxData[] = '备血（有）';
                } else {
                    $range = ['range' => ['KZSJ' => ['lt' => $zxsj]]];
                    $bxNoCount = $this->yzb($yzbService,$ZYH,'备血',$range);
                    $bxsxData[] = $bxNoCount ? '备血（小于ZXSJ时间）' : '备血（无）';
                }
            } else {
                $bxCount = $this->yzb($yzbService,$ZYH,'备血',[]);
                $bxsxData[] = $bxCount ? '备血（有）' : '备血（无）';
            }

            // 查询输
            if ($zxsj) {
                $range = ['range' => ['KZSJ' => ['gte' => $zxsj]]];
                $kzsj = $this->yzb($yzbService,$ZYH,'输',$range);
                if ($kzsj) {
                    $bxsxData[] = '输（有），'.$kzsj.' > 输血治疗知情同意书执行时间';
                } else {
                    $range = ['range' => ['KZSJ' => ['lt' => $zxsj]]];
                    $kzsj = $this->yzb($yzbService,$ZYH,'输',$range);
                    $bxsxData[] = $kzsj ? '输（有），'.$zxsj.' < 输血治疗知情同意书执行时间' : '输（无）';
                }
            } else {
                $kzsj = $this->yzb($yzbService,$ZYH,'输',[]);
                if ($kzsj) {
                    $bxsxData[] = '输（有），'.$zxsj.'（输血治疗知情同意书执行时间 无）';
                } else {
                    $bxsxData[] = '输（无）';
                }
            }

            // 病程记录 - 输血病程记录
            $lcyxBcjl3 = $this->lcyxBcjl3($bl01Service, $ZYH, '输血病程记录');
            $sxbcjlData = $lcyxBcjl3['data'];
            $sxbcjlError = $lcyxBcjl3['msg'];
            $sxbcjl = $lcyxBcjl3['code']==200 ? 1 : 0;

            // 病程记录 - 效果 + （血红蛋白 or HGB or Hb）
            $arr = ['血红蛋白','HGB','Hb'];
            $isOk = false;
            $msgArr = [];
            foreach ($arr as $kk => $v) {
                if ($kk < 3) {
                    $info = $this->lcyxBcjl4($bl01Service, $ZYH, $sxbcjlData, $v);
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
            $lcyxError = '费用明细【储血费】'.'输血治疗知情同意书【'.$sxzltys.' '.$zxsj.'】'.'，医嘱【'.implode('+',$bxsxData).'】'.'，输血病程记录【'.$sxbcjlError.'】，病程记录【'.implode('，',$bcjlData).'】';
            $saveData = ['denominator_lcyx'=>1,'numerator_lcyx'=>$numerator,'lcyx_error'=>$lcyxError];
            PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH], $saveData);
        }
    }
    protected function yzb($yzbService,$MED_REC_ID,$keyValue,$range)
    {
        $must = [
            ["term" => ['ZYH' => $MED_REC_ID]],
            ["match_phrase" => ['YZMC' => $keyValue]]
        ];
        if ($range) {
            $must[] = $range;
        }

        if ($keyValue == '输') {
            $params = $yzbService->clearMust()
                ->queryByMustBatch($must)
                ->orderBy('KZSJ','desc')
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
    protected function lcyxBcjl3($bl01Service,$MED_REC_ID,$keyValue)
    {
        $must = [
            ["term" => ['JZHM' => $MED_REC_ID]],
            ["term" => ['BLLB' => 294]],
            ["match_phrase" => ['HJNR' => $keyValue]]
        ];
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
                if (!in_array($value['ZXSJ'],$sxbcjl)) {
                    $sxbcjl[] = $value['ZXSJ'];
                }
            }
        }

        if ($sxbcjl) {
            return ['code'=>200,'msg'=>'有，'.$sxbcjl[0],'data'=>$sxbcjl];
        }

        return ['code'=>1,'msg'=>'无','data'=>$sxbcjl];
    }
    protected function lcyxBcjl4($bl01Service,$MED_REC_ID,$zxsjList,$key)
    {
        $isExist = $isExistDate = 0;
        $xgIsExist = $xgIsExistDate = 0;
        foreach ($zxsjList as $zxsjDate) {
            $ZXSJData = [$zxsjDate,Carbon::parse($zxsjDate)->addDay(2)->toDateTimeString()];
            $xgData = $this->lcyxBcjl3($bl01Service,$MED_REC_ID,'效果');
            $xg = $xgData['data'];
            if ($xg) {
                // 记录有数据
                $xgIsExist = 1;
                foreach ($xg as $v) {
                    if ($v>=$ZXSJData[0] && $v<=$ZXSJData[1]) {
                        // 记录48小时内有数据
                        $xgIsExistDate = 1;
                        break;
                    }
                }
            }

            $keyData = $this->lcyxBcjl3($bl01Service,$MED_REC_ID,$key);
            $ZXSJ = $keyData['data'];
            if ($ZXSJ) {
                // 记录有数据
                $isExist = 1;
                foreach ($ZXSJ as $v) {
                    if ($v>=$ZXSJData[0] && $v<=$ZXSJData[1]) {
                        // 记录48小时内有数据
                        $isExistDate = 1;
                        break;
                    }
                }
            }
        }

        $xgIsOk = 0;
        if ($xgIsExist && $xgIsExistDate) {
            $xgMsg = '（有）';
            $xgIsOk = 1;
        } elseif ((!$xgIsExist && !$xgIsExistDate) && !$xgIsExist) {
            $xgMsg = '（无）';
        } elseif (!$xgIsExistDate) {
            $xgMsg = '（时间超过48小时）';
        }

        $isMc = 0;
        if ($isExist && $isExistDate) {
            $str = '有 输血病程记录48小时内';
            $isMc = 1;
        } elseif ((!$isExist && !$isExistDate) && !$isExist) {
            $str = '无';
        } elseif (!$isExistDate) {
            $str = '有 超过输血病程记录48小时';
        }

        if ($xgIsOk && $isMc) {
            return ['code'=>200,'msg'=>'效果'.$xgMsg.' + '.$key.'（48小时内有数据）'];
        }

        return ['code'=>1,'msg'=>'效果'.$xgMsg.' + '.$key.'（'.$str.'）'];
    }

    /**
     * 出院患者病历2日归档率
     * @param $patientInfo
     * @return void
     */
    public function cyhzgd($patientInfo)
    {
        $ZYH = $patientInfo['MED_REC_ID'];
        $AAA28 = $patientInfo['AAA28'];
        $AAC01 = $patientInfo['AAC01'];

        $yzbEsService = new ElasticsearchService('yzb_2023');

        $baReceive = BA_RECEIVE::query()
            ->where('bah','=',$AAA28)
            ->whereNotNull('MaxCheckTime')
            ->get()->toArray();

        // 查询归档时间
        $MaxCheckTime = '';
        if (!empty($baReceive)) {
            foreach ($baReceive as $val) {
                if ($val['cysj'] == $AAC01) {
                    $MaxCheckTime = $val['MaxCheckTime'];
                    break;
                }
            }
        }

        // 查询医嘱 出院的 XZJDSJ
        $numerator = 0;
        $yzbCyData = $this->getYzbDataEs($yzbEsService,$ZYH,303,$MaxCheckTime);
        $cyhzgdError = '';
        if ($yzbCyData['numerator']) {
            $numerator = $yzbCyData['numerator'];
            $cyhzgdError = $yzbCyData['msg'];
        } else {
            // 查询医嘱 死亡的 XZJDSJ
            $yzbSwData = $this->getYzbDataEs($yzbEsService,$ZYH,305,$MaxCheckTime);
            if ($yzbSwData['numerator']) {
                $numerator = $yzbSwData['numerator'];
                $cyhzgdError = $yzbSwData['msg'];
            }
        }

        if (!$MaxCheckTime) {
            $numerator = 0;
        }

        if (!$numerator) {
            if ($yzbCyData['is_data'] == 1) {
                $cyhzgdError = $yzbCyData['msg'];
            } elseif ($yzbSwData['is_data'] == 1) {
                $cyhzgdError = $yzbSwData['msg'];
            } else {
                $cyhzgdError = $yzbCyData['msg'];
            }
        }

        // 记录
        $saveData = ['denominator_cyhzgd'=>1,'numerator_cyhzgd'=>$numerator,'cyhzgd_error'=>$cyhzgdError];
        PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
    }
    protected function getYzbDataEs($yzbEsService,$ZYH,$YDYZLB,$MaxCheckTime)
    {
        $publicService = new PublicService();

        $title = $YDYZLB==303 ? '出院时间' : '死亡时间';

        $must = [
            ['term' => ["ZYH" => $ZYH]],
            ['term' => ["YDYZLB" => $YDYZLB]]
        ];
        $params = $yzbEsService->clearMust()->queryByMustBatch($must)->getParams();
        $yzbRes = app('es')->search($params);
        $yzbData = $yzbEsService->getDataByEs($yzbRes);
        $XZJDSJ = !empty($yzbData[0]) ? $yzbData[0][0]['XZJDSJ'] : '';

        $numerator = 0;
        $is_data = 0;
        if ($XZJDSJ) {
            $is_data = 1;
            if ($MaxCheckTime) {
                // 获取2日后时间（不包含节假日、休息日）
                $XZJDSJ_END = $publicService->getDay($XZJDSJ, 2);
                if ($XZJDSJ_END > $MaxCheckTime) {
                    $numerator = 1;
                    $cyhzgdError = $title.'【'.$XZJDSJ.'】 归档时间【'.$MaxCheckTime.'，2个工作日内】';
                } else {
                    $cyhzgdError = $title.'【'.$XZJDSJ.'】 归档时间【'.$MaxCheckTime.'，超过2个工作日】';
                }
            } else {
                $cyhzgdError = $title.'【'.$XZJDSJ.'】 归档时间【无】';
            }
        } else {
            $MaxCheckTime = !empty($MaxCheckTime) ? $MaxCheckTime : '无';
            $cyhzgdError = $title.'【无】 归档时间【'.$MaxCheckTime.'】';
        }

        return ['numerator'=>$numerator,'msg'=>$cyhzgdError,'is_data'=>$is_data];
    }

    /**
     * 出院患者病历归档完整率
     * @param $patientInfo
     * @return void
     */
    public function cyhzgdl($patientInfo)
    {
        $bl01NewService = new ElasticsearchService('emr_bl_bl01_new');
        $bl01Service = new ElasticsearchService('bl01_202303');
        $mzjlService = new ElasticsearchService('mzjl_2023');
        $pacsService = new ElasticsearchService('pacs');
        $ymresultService = new ElasticsearchService('v_jmgs_ymresult_2023');
        $yzbService = new ElasticsearchService('yzb_2023');
        $bmcnService = new ElasticsearchService('ba_mr_class_number_2023');

        $AAA28 = $patientInfo['AAA28'];
        $ZYH = $patientInfo['MED_REC_ID'];
        $AAB01 = $patientInfo['AAB01'];
        $AAA29 = $patientInfo['AAA29'];
        $AAC01 = $patientInfo['AAC01'];

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
        if ($AAB01 && $AAC01) {
            $must = [
                ['term' => ['JZLSH' => $AAA28]],
                ['range' => ['JYSJ' => ['gte' => $AAB01,'lte' => $AAC01]]],
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
                ['term' => ['JZLSH' => $AAA28]],
                ['range' => ['JYSJ' => ['gte' => $AAB01,'lte' => $AAC01]]],
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
            PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
        }
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

    /**
     * 出院记录
     * @param $patientInfo
     * @return void
     */
    public function cyjl($patientInfo)
    {
        $bl01Service = new ElasticsearchService('bl01_202303');
        $yzbService = new ElasticsearchService('yzb_2023');

        $ZYH = $patientInfo['MED_REC_ID'];

        // 查询出院记录
        $cyjlInfo1 = $this->getCyjlError($bl01Service,$yzbService,$ZYH,1,303);
        $cyjlInfo2 = [];
        $cyjlInfo3 = [];
        if (!$cyjlInfo1['numerator']) {
            // 24小时记录类
            $cyjlInfo2 = $this->getCyjlError($bl01Service,$yzbService,$ZYH,18,303);
            if (!$cyjlInfo2['numerator']) {
                // 查询死亡记录
                $cyjlInfo3 = $this->getCyjlError($bl01Service,$yzbService,$ZYH,288,305);
            }
        }

        if (!empty($cyjlInfo1['numerator'])) {
            $numerator = $cyjlInfo1['numerator'];
            $cyjlError = $cyjlInfo1['cyjl_error'];
        } elseif (!empty($cyjlInfo2['numerator'])) {
            $numerator = $cyjlInfo2['numerator'];
            $cyjlError = $cyjlInfo2['cyjl_error'];
        } elseif (!empty($cyjlInfo3['numerator'])) {
            $numerator = $cyjlInfo3['numerator'];
            $cyjlError = $cyjlInfo3['cyjl_error'];
        } elseif (!empty($cyjlInfo1['cjsj'])) {
            $numerator = $cyjlInfo1['numerator'];
            $cyjlError = $cyjlInfo1['cyjl_error'];
        } elseif (!empty($cyjlInfo2['cjsj'])) {
            $numerator = $cyjlInfo2['numerator'];
            $cyjlError = $cyjlInfo2['cyjl_error'];
        } elseif (!empty($cyjlInfo3['cjsj'])) {
            $numerator = $cyjlInfo3['numerator'];
            $cyjlError = $cyjlInfo3['cyjl_error'];
        } else {
            $numerator = $cyjlInfo1['numerator'];
            $cyjlError = $cyjlInfo1['cyjl_error'];
        }

        // 记录
        $saveData = ['denominator_cyjl'=>1,'numerator_cyjl'=>$numerator,'cyjl_error'=>$cyjlError];
        PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
    }
    protected function getCyjlError($bl01Service,$yzbService,$ZYH,$BLLB,$YDYZLB)
    {
        $title = $BLLB==288 ? '出院时间（死亡）' : '出院时间';
        $bllb = [1=>'出院记录',18=>'24小时内记录类',288=>'死亡记录'];

        // 分子 - 查询出院记录创建时间
        $must = [
            ["term" => ['JZHM' => $ZYH]],
            ["term" => ['BLLB' => $BLLB]]
        ];
        $notMust = ['term' => ["BLZT" => 9]];
        $params = $bl01Service->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNot($notMust)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);
        $cjsj = !empty($bl01Data[0][0]['CJSJ']) ? $bl01Data[0][0]['CJSJ'] : '';

        // 分子 - 查询医嘱表 XZJDSJ
        $must = [
            ["term" => ['ZYH' => $ZYH]],
            ["term" => ['YDYZLB' => $YDYZLB]]
        ];
        $params = $yzbService->clearMust()
            ->queryByMustBatch($must)
            ->paginate(1,10000)
            ->getParams();
        $restful = app('es')->search($params);
        $yzbData = $yzbService->getDataByEs($restful);
        $xzjdsjList = [];
        if (!empty($yzbData[0])) {
            foreach ($yzbData[0] as $value) {
                if (!in_array($value['XZJDSJ'],$xzjdsjList)) {
                    $xzjdsjList[] = $value['XZJDSJ'];
                }
            }
        }

        $numerator = 0;
        if ($xzjdsjList) {
            $cyjlError = '';
            foreach ($xzjdsjList as $xzjdsj) {
                if ($cjsj) {
                    $xzjdsjEnd = date('Y-m-d H:i:s',strtotime($xzjdsj)+(3600*24));
                    if ($cjsj < $xzjdsjEnd) {
                        $numerator = 1;
                        $cyjlError = $title.'【'.$xzjdsj.'】'.$bllb[$BLLB].'【'.$cjsj.'（24小时内）】';
                        break;
                    } else {
                        $cyjlError = $title.'【'.$xzjdsj.'】'.$bllb[$BLLB].'【'.$cjsj.'（创建时间超24小时）】';
                    }
                } else {
                    $cyjlError = $title.'【'.$xzjdsj.'】'.$bllb[$BLLB].'【无】';
                }
            }
        } else {
            $cyjlError = $title.'【无】';
            if ($cjsj) {
                $cyjlError .= $bllb[$BLLB].'【'.$cjsj.'】';
            } else {
                $cyjlError .= $bllb[$BLLB].'【无】';
            }
        }

        return ['numerator'=>$numerator,'cyjl_error'=>$cyjlError,'cjsj'=>$cjsj];
    }

    /**
     * 细菌培养检查记录符合率
     * @param $patientInfo
     * @param $xjpyFyzdList
     * @return void
     */
    public function xjpy($patientInfo,$xjpyFyzdList)
    {
        $feeService = new ElasticsearchService('fee_detailed');
        $vjyService = new ElasticsearchService('v_jmgs_ymresult_2023');
        $bl01Service = new ElasticsearchService('bl01_202303');
        $yzbService = new ElasticsearchService('yzb_2023');

        $ZYH = $patientInfo['MED_REC_ID'];
        $xjpyError = [];

        // 分母（费用明细）
        $must = [["term" => ['MED_REC_ID' => $ZYH]]];
        $should = [];
        foreach ($xjpyFyzdList as $mc) {
            $should[] = ["match_phrase" => ['FYMC' => $mc]];
        }
        $params = $feeService->clearMust()
            ->queryByMustBatch($must)
            ->queryByShouldBatch($should)
            ->minimumShouldMatch(1)
            ->getParams();
        $restful = app('es')->search($params);
        $feeData = $feeService->getDataByEs($restful);
        $fymxNameList = [];
        if (!empty($feeData[0])) {
            foreach ($feeData[0] as $value) {
                if (!in_array($value['FYMC'],$fymxNameList)) {
                    $fymxNameList[] = $value['FYMC'];
                }
            }
        }
        if (!empty($fymxNameList)) {
            $denominator_xjpy = !empty($fymxNameList) ? 1 : 0;

            // 分子
            $ymresultList = $this->V_JMGS_YMresult($vjyService,$ZYH);
            if (!$ymresultList) {
                if ($denominator_xjpy) {
                    $pyZXSJ = $this->bl01Value($bl01Service,$ZYH,'培养');
                    if ($pyZXSJ) {
                        $xjpyErrorStr = '1、费用明细【' . implode('，', $fymxNameList) . '】医嘱【无】检验报告单【无】病程记录【培养（'.$pyZXSJ.'）】';
                    } else {
                        $xjpyErrorStr = '1、费用明细【' . implode('，', $fymxNameList) . '】医嘱【无】检验报告单【无】病程记录【无】';
                    }
                } else {
                    $xjpyErrorStr = '';
                }
                $saveData = ['denominator_xjpy' => $denominator_xjpy, 'numerator_xjpy' => 0, 'xjpy_error' => $xjpyErrorStr];
                PatientInfoTarget::query()->where('ZYH', '=', $ZYH)->update($saveData);
            } else {
                $index = 0;
                foreach ($ymresultList as $key => $value) {
                    $CJSJ = $value['CJSJ'];
                    $EXAMINAIM = $value['EXAMINAIM'];
                    $EXAMINAIM_CJSJ = $value['CJSJ'];
                    if ($fymxNameList) {
                        $xjpyError[$key] = ($key + 1) . '、费用明细【' . implode('，', $fymxNameList) . '】';
                    } else {
                        $xjpyError[$key] = ($key + 1) . '、费用明细【无】';
                    }

                    // 分子 - 医嘱查询
                    $EXAMINAIM_ARR = explode('+',$EXAMINAIM);
                    foreach ($EXAMINAIM_ARR as $k => $v) {
                        $EXAMINAIM_ARR[$k] = str_replace('加药敏','',$v);
                    }
                    $EXAMINAIM_ARR[] = $EXAMINAIM;

                    $must = ["term" => ['ZYH' => $ZYH]];
                    $should = [];
                    foreach ($EXAMINAIM_ARR as $v) {
                        $should[] = ["match_phrase" => ['YZMC' => $v]];
                    }

                    $params = $yzbService->clearMust()
                        ->queryByMust($must)
                        ->queryByShouldBatch($should)
                        ->minimumShouldMatch(1)
                        ->getParams();
                    $restful = app('es')->search($params);
                    $yzbData = $yzbService->getDataByEs($restful);
                    $yzmc = !empty($yzbData[0]) ? $yzbData[0][0]['YZMC'] : '';
                    if ($yzmc) {
                        $xjpyError[$key] .= '医嘱【' . $yzmc . '（有）】';
                    } else {
                        $xjpyError[$key] .= '医嘱【' . $yzmc . '（无）】';
                    }

                    // 分子 - 报告单
                    $xjpyError[$key] .= '检验报告单【' . $EXAMINAIM . ' '.$CJSJ.'】';

                    // 分子
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
                $saveData = ['denominator_xjpy' => $denominator_xjpy, 'numerator_xjpy' => $numerator, 'xjpy_error' => implode("，", $xjpyError)];
                PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
            }
        }
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
                ->orderBy('BGSJ','desc')
                ->paginate(1,100)
                ->getParams();
            $restful = app('es')->search($params);
            $vjyData = $vjyService->getDataByEs($restful);
            $EXAMINAIM_ARR = [];
            if (!empty($vjyData[0])) {
                foreach ($vjyData[0] as $value) {
                    if ($value['EXAMINAIM'] && !in_array($value['EXAMINAIM'],$EXAMINAIM_ARR)) {
                        $EXAMINAIM_ARR[] = $value['EXAMINAIM'];
                        $ymresultList[] = [
                            'CJSJ' => $value['CJSJ'],
                            'EXAMINAIM' => $value['EXAMINAIM']
                        ];
                    }
                }
            }
        }

        return $ymresultList;
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

    /**
     * 患者抢救成功率
     * @param $patientInfo
     * @return void
     */
    public function hzqjcgl($patientInfo)
    {
        $feeService = new ElasticsearchService('fee_detailed');
        $bl01Service = new ElasticsearchService('bl01_202303');
        $yzbService = new ElasticsearchService('yzb_2023');

        $ZYH = $patientInfo['MED_REC_ID'];

        // 分母
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
        if (!empty($feeList)) {
            $JFQR = !empty($feeList[0]['JFQR']) ? $feeList[0]['JFQR'] : '无';

            // 分子 - 医嘱
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

            // 分子 - 病程记录
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
            $bcjlList = !empty($bl01Data[0]) ? $bl01Data[0] : [];
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

    /**
     * 患者抢救记录符合率
     * @param $patientInfo
     * @return void
     */
    public function hzqjjl($patientInfo)
    {
        $feeDetailedService = new ElasticsearchService('fee_detailed');
        $bl01Service = new ElasticsearchService('bl01_202303');
        $yzbService = new ElasticsearchService('yzb_2023');

        $ZYH = $patientInfo['MED_REC_ID'];

        // 分母
        $must = [
            ["term" => ['MED_REC_ID' => $ZYH]],
            ["match_phrase" => ['FYMC' => '抢救']]
        ];
        $params = $feeDetailedService->clearMust()->queryByMustBatch($must)->getParams();
        $restful = app('es')->search($params);
        $feeData = $feeDetailedService->getDataByEs($restful);
        $fymc = '';
        if (!empty($feeData[0])) {
            $fymc = !empty($feeData[0][0]['pre_FYMC']) ? $feeData[0][0]['pre_FYMC'] : $feeData[0][0]['FYMC'];
        }
        if (!empty($fymc)) {
            $must = [
                ["term" => ['JZHM' => $ZYH]],
                ['match_phrase' => ['HJNR' => '抢救记录']]
            ];
            $notMust = [
                ['term' => ['BLZT' => 9]]
            ];
            $params = $bl01Service->clearMust()
                ->queryByMustBatch($must)
                ->queryByMustNotBatch($notMust)
                ->getParams();
            $restful = app('es')->search($params);
            $bl01Data = $bl01Service->getDataByEs($restful);
            $cjsj = !empty($bl01Data[0]) ? $bl01Data[0][0]['CJSJ'] : '';

            // 分子查询医嘱
            $must = [
                ["term" => ['ZYH' => $ZYH]],
                ["match_phrase" => ['YZMC' => '抢救']]
            ];
            $params = $yzbService->clearMust()
                ->queryByMustBatch($must)
                ->paginate(1,1000)
                ->getParams();
            $restful = app('es')->search($params);
            $yzbData = $yzbService->getDataByEs($restful);
            $yzbData = !empty($yzbData[0]) ? $yzbData[0] : [];

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

    /**
     * 植入物相关记录符合率指标
     * @param $patientInfo
     * @param $implantsList
     * @return void
     */
    public function zrw($patientInfo,$implantsList)
    {
        $feeService = new ElasticsearchService('fee_detailed');
        $bl01Service = new ElasticsearchService('bl01_202303');

        $ZYH = $patientInfo['MED_REC_ID'];
        $zrwName = [];

        // 查询费用名称
        $must = [
            ["term" => ['MED_REC_ID' => $ZYH]]
        ];
        $params = $feeService->clearMust()
            ->queryByMustBatch($must)
            ->paginate(1,10000)
            ->getParams();
        $restful = app('es')->search($params);
        $feeData = $feeService->getDataByEs($restful);
        $fymcList = [];
        $feeList = [];
        if (!empty($feeData[0])) {
            foreach ($feeData[0] as $value) {
                if (!in_array($value['FYMC'],$fymcList)) {
                    $fymcList[] = $value['FYMC'];
                    $feeList[] = [
                        'FYMC' => $value['FYMC'],
                        'FYSL' => $value['FYSL']
                    ];
                }
            }
        }

        $fymcArr = [];
        $sfxm = [];
        // 分母 - 匹配出包含植入名称的数据
        foreach ($implantsList as $manufactor => $name) {
            // 记录匹配到的植入名称
            foreach ($feeList as $val) {
                if ($name == '连接管') {
                    if ($val['FYMC']==$name && !in_array($name,$fymcArr)) {
                        $fymcArr[] = $name;
                        $sfxm[] = '商品名称：'.$name.'，数量：'.$val['FYSL'].'，厂家：'.$manufactor;
                    }
                } elseif (stripos($val['FYMC'],$name) !== false && !in_array($name,$fymcArr)) {
                    $fymcArr[] = $name;
                    $sfxm[] = '商品名称：'.$name.'，数量：'.$val['FYSL'].'，厂家：'.$manufactor;
                }
            }
        }

        if (!empty($fymcArr)) {
            $zrwName[] = '收费项目【'.implode('，',$sfxm).'】';

            // 分子 - 查询手术记录
            $ssList = $this->zrwBl01Value($bl01Service,$ZYH,303,$implantsList);
            $zrwName[] = $ssList ? '手术记录【'.implode('，',$ssList).'】' : '手术记录【无】';

            // 分子 - 病程记录
            $bcList = $this->zrwBl01Value($bl01Service,$ZYH,294,$implantsList);
            $zrwName[] = $bcList ? '病程记录【'.implode(',',$bcList).'】' : '病程记录【无】';

            $numerator = 0;
            if ($ssList || $bcList) {
                $numerator = 1;
            }

            // 记录
            $saveData = ['denominator_zrw'=>1,'numerator_zrw'=>$numerator,'zrw_name'=>implode('，',$zrwName)];
            PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
        }
    }
    protected function zrwBl01Value($bl01Service,$ZYH,$bllb,$implantsList)
    {
        $must = [
            ["term" => ['JZHM' => $ZYH]],
            ["term" => ['BLLB' => $bllb]]
        ];
        $notMust = ['term' => ["BLZT" => 9]];
        $params = $bl01Service->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNot($notMust)
            ->paginate(1,1000)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);

        $hjnrList = [];
        if (!empty($bl01Data[0])) {
            foreach ($bl01Data[0] as $value) {
                if (in_array($value['HJNR'],$implantsList) && !in_array($value['HJNR'],$hjnrList)) {
                    $hjnrList[] = $value['HJNR'];
                }
            }
        }

        return $hjnrList;
    }

    /**
     * CT/MRI检查记录符合率
     * @param $patientInfo
     * @return void
     */
    public function ircr($patientInfo)
    {
        $pacsService = new ElasticsearchService('pacs');
        $yzbService = new ElasticsearchService('yzb_2023');
        $bl01Service = new ElasticsearchService('bl01_202303');

        $ZYH = $patientInfo['MED_REC_ID'];
        $AAA28 = $patientInfo['AAA28'];
        $AAB01 = $patientInfo['AAB01'];
        $AAC01 = $patientInfo['AAC01'];

        // 查询报告单
        $bgdData = [];
        if ($AAB01 && $AAC01) {
            $must = [
                ["term" => ['JZLSH' => $AAA28]],
                ['range' => ['KDSJ' => ['gte' => $AAB01, 'lte' => $AAC01]]]
            ];
            $notMust = [
                ['match_phrase' => ["JCMC" => "OCT"]]
            ];
            $params = $pacsService->clearMust()->queryByMustNotBatch($notMust)->queryByMustBatch($must)->getParams();
            $restful = app('es')->search($params);
            $pacsData = $pacsService->getDataByEs($restful);
            foreach ($pacsData[0] as $value) {
                if (!empty($value['YXZD'])) {
                    $jcmc = str_replace('OCT','',$value['JCMC']);
                    if (stripos($jcmc,'CT') !== false || stripos($jcmc,'MR') !== false || stripos($jcmc,'磁共振') !== false) {
                        $bgdData[] = [
                            'ZYH' => $value['ZYH'],
                            'JZLSH' => $value['JZLSH'],
                            'JCMC' => $value['JCMC'],
                            'BGSJ' => $value['BGSJ']
                        ];
                    }
                }
            }
        }

        if ($bgdData) {
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
                    $yz = $this->ctYzb($yzbService,$ZYH,$jcmc);
                    $ctError[$index] .= $yz['msg'];

                    // 病程记录
                    $bcjl = $this->ctBcjl($bl01Service,$ZYH,$jcmc,$pacs['BGSJ']);
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
            PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
        }
    }
    protected function ctYzb($yzbService,$ZYH,$jcmc)
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
    protected function ctBcjl($bl01Service,$zyh,$jcmc,$bgsj)
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
        $zxsj = '';
        if ($keyVal == 'MR') {
            $zxsj = EMR_BL_BL01::query()
                ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                ->where('EMR_BL_BL01.JZHM', '=', $zyh)
                ->where('EMR_BL_BL01.BLLB', '=', 18)
                ->where('EMR_BL_BL01.BLZT','!=',9)
                ->where('EMR_BL_BL01.BLMC','not like',"%首次病程记录%")
                ->where('HJNR','like',"%".$keyVal."%")
                ->value('ZXSJ');
            if (!$zxsj) {
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
//                    $zxsj = $bl01Data[0][0]['ZXSJ'];
                    return ['is_error'=>200,'msg'=>'24小时内记录【含“磁共振”，'.$bl01Data[0][0]['ZXSJ'].'（24小时内）】'];
                }
            } else {
                return ['is_error'=>200,'msg'=>'24小时内记录【含“MR”，'.$zxsj.'（24小时内）】'];
            }
        }

        if (!$zxsj) {
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
            if ($bl01Data[0]) {
                if ($keyVal == 'CT') {
                    $hjnr = str_replace('OCT','',$bl01Data[0][0]['HJNR']);
                    if (stripos($hjnr,'CT') !== false) {
                        $zxsj = $bl01Data[0][0]['ZXSJ'];
                    }
                } else {
                    if (!empty($bl01Data[0])) {
                        $zxsj = $bl01Data[0][0]['ZXSJ'];
                    }
                }
            }
        }

        $bcjlTitle = ['CT'=>'含“CT（OCT除外）”','MR'=>'含“MR”','磁共振'=>'含“磁共振”'];
        if ($zxsj) {
            return ['is_error'=>200,'msg'=>'24小时内记录【'.$bcjlTitle[$keyVal].'，'.$zxsj.'（24小时内）】'];
        }

        return [];
    }
    protected function bcjl294($bl01Service,$zyh,$bgsj,$keyVal)
    {
        $zxsjDate = [];
        if ($keyVal == 'MR') {
            $data = EMR_BL_BL01::query()
                ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                ->where('EMR_BL_BL01.JZHM', '=', $zyh)
                ->where('EMR_BL_BL01.BLLB', '=', 294)
                ->where('EMR_BL_BL01.BLZT','!=',9)
                ->where('EMR_BL_BL01.BLMC','not like',"%首次病程记录%")
                ->where('HJNR','like',"%".$keyVal."%")
                ->get(['ZXSJ','HJNR'])->toArray();
            if (!$data) {
                $must = [
                    ["term" => ['JZHM' => $zyh]],
                    ["term" => ['BLLB' => 294]],
                    ["match_phrase" => ['HJNR' => "磁共振"]]
                ];
                $notMust = [
                    ['term' => ["BLZT" => 9]],
                    ['match_phrase' => ["BLMC" => "首次病程记录"]]
                ];
                $params = $bl01Service->clearMust()->queryByMustNotBatch($notMust)->queryByMustBatch($must)->getParams();
                $restful = app('es')->search($params);
                $bl01Data = $bl01Service->getDataByEs($restful);
                if ($bl01Data[0]) {
                    foreach ($bl01Data[0] as $value) {
                        $zxsjDate[] = $value['ZXSJ'];
                    }
                    return $this->returnData('磁共振',$zxsjDate,$bgsj);
                } else {
                    return ['is_error'=>1,'msg'=>'病程记录【无】'];
                }
            } else {
                foreach ($data as $val) {
                    $zxsjDate[] = $val['ZXSJ'];
                }
                return $this->returnData($keyVal,$zxsjDate,$bgsj);
            }
        }

        $must = [
            ["term" => ['JZHM' => $zyh]],
            ["term" => ['BLLB' => 294]],
            ["match_phrase" => ['HJNR' => $keyVal]]
        ];
        $notMust = [
            ['term' => ["BLZT" => 9]],
            ['term' => ["BLMC" => "首次病程记录"]]
        ];
        $params = $bl01Service->clearMust()->queryByMustNotBatch($notMust)->queryByMustBatch($must)->getParams();
        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);
        $zxsjDate = [];
        if ($bl01Data[0]) {
            foreach ($bl01Data[0] as $value) {
                if ($keyVal == 'CT') {
                    $hjnr = str_replace('OCT','',$value['HJNR']);
                    if (stripos($hjnr,'CT') !== false) {
                        $zxsjDate[] = $value['ZXSJ'];
                    }
                } else {
                    $zxsjDate[] = $value['ZXSJ'];
                }
            }
        }

        return $this->returnData($keyVal,$zxsjDate,$bgsj);
    }
    protected function returnData($keyVal,$zxsjDate,$bgsj)
    {
        $bgsj = [$bgsj,Carbon::parse($bgsj)->addDay(1)->toDateTimeString()];
        $bcjlIsExist = $bcjlDateIsExist = 0;
        $zxsj = '';
        if ($zxsjDate) {
            // 记录有数据
            $bcjlIsExist = 1;
            foreach ($zxsjDate as $v) {
                if ($v>=$bgsj[0] && $v<=$bgsj[1]) {
                    // 记录24小时内有数据
                    $bcjlDateIsExist = 1;
                    $zxsj = $v;
                    break;
                }
            }
        }

        $bcjlTitle = ['CT'=>'含“CT（OCT除外）”','MR'=>'含“MR”','磁共振'=>'含“磁共振”'];
        if ($bcjlIsExist && $bcjlDateIsExist) {
            $returnData = ['is_error'=>200,'msg'=>'病程记录【'.$bcjlTitle[$keyVal].'，'.$zxsj.'（24小时内）】'];
        } elseif ((!$bcjlIsExist && !$bcjlDateIsExist) && !$bcjlIsExist) {
            $returnData = ['is_error'=>1,'msg'=>'病程记录【无】'];
        } elseif (!$bcjlDateIsExist) {
            $returnData = ['is_error'=>1,'msg'=>'病程记录【'.$bcjlTitle[$keyVal].'，'.$zxsj.'（时间超过24小时）】'];
        }

        return $returnData;
    }

    /**
     * 所有出院患者
     * @param $patientInfo
     * @return void
     */
    public function publicCyjl($patientInfo)
    {
        $saveData = ['numerator_public_cyjl'=>1];
        PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$patientInfo['MED_REC_ID']], $saveData);
    }

    /**
     * 入院记录24小时内完成率
     * @param $patientInfo
     * @return void
     */
    public function ryjl($patientInfo)
    {
        $zyHcmxService = new ElasticsearchService('zy_hcmx');
        $bl01Service = new ElasticsearchService('bl01_202303');

        $ZYH = $patientInfo['MED_REC_ID'];
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
        PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
    }

    /**
     * 细菌培养检查记录符合率指标-子指标
     * @param $patientInfo
     * @return void
     */
    public function xjpyNew($patientInfo)
    {
        $vjyService = new ElasticsearchService('v_jmgs_ymresult_2023');
        $bl01Service = new ElasticsearchService('bl01_202303');
        $yzbService = new ElasticsearchService('yzb_2023');

        $ZYH = $patientInfo['MED_REC_ID'];
        $xjpyError = [];

        // 查询分母
        $ymresultList = $this->V_JMGS_YMresult($vjyService,$ZYH);
        if (!empty($ymresultList)) {
            // 分子
            $index = 0;
            foreach ($ymresultList as $key => $value) {
                $CJSJ = $value['CJSJ'];
                $EXAMINAIM = $value['EXAMINAIM'];
                $EXAMINAIM_CJSJ = $value['CJSJ'];

                // 分子 - 医嘱查询
                $yzmc = $this->xjpyNewYzb($yzbService,$ZYH,$EXAMINAIM);
                if ($yzmc) {
                    $xjpyError[$key] = '医嘱【' . $yzmc . '（有）】';
                } else {
                    $xjpyError[$key] = '医嘱【' . $yzmc . '（无）】';
                }

                // 分子 - 报告单
                $xjpyError[$key] .='检验报告单【' . $EXAMINAIM . ' '.$CJSJ.'】';

                // 分子 - 细菌培养报告
                $xjmcList = $this->xjpyNew_V_JMGS_YMresult($vjyService,$ZYH,$EXAMINAIM);

                // 分子 - 病程
                $xjmcArr = [];
                $xjmcCount = 0;
                if ($xjmcList) {
                    foreach ($xjmcList as $xjmcVal) {
                        $range = ['range' => ['ZXSJ' => ['gte' => $EXAMINAIM_CJSJ]]];
                        $bcjlZXSJ = $this->xjpyNewBl01Value($bl01Service,$ZYH,$xjmcVal,$range);
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
                    $bcjlZXSJ = $this->xjpyNewBl01Value($bl01Service,$ZYH,'培养',$range);
                    if ($bcjlZXSJ) {
                        $xjmcCount++;
                        $xjmcArr[] = '培养 '.$bcjlZXSJ.' > 检验时间';
                    } else {
                        $range = ['range' => ['ZXSJ' => ['lte' => $EXAMINAIM_CJSJ]]];
                        $bcjlZXSJ = $this->xjpyNewBl01Value($bl01Service,$ZYH,'培养',$range);
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
    protected function xjpyNew_V_JMGS_YMresult($vjyService,$ZYH,$EXAMINAIM='')
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
    protected function xjpyNewYzb($yzbService,$ZYH,$EXAMINAIM)
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
    protected function xjpyNewBl01Value($bl01Service,$ZYH,$keyValue,$range=[])
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

    /**
     * 知情同意书规范签署
     * @param $patientInfo
     * @return void
     */
    public function zqtysgfqs($patientInfo)
    {
        $bl01Service = new ElasticsearchService('bl01_202303');
        $yzbService = new ElasticsearchService('yzb_2023');
        $mzjlService = new ElasticsearchService('mzjl_2023');
        $blsyService = new ElasticsearchService('blsy_2023');
        $staffService = new ElasticsearchService('staff_2023');

        $ZYH = $patientInfo['MED_REC_ID'];

        // 分母
        $must = [
            ["term" => ['JZHM' => $ZYH]],
            ["term" => ['BLLB' => 329]]
        ];
        $params = $bl01Service->clearMust()
            ->queryByMustBatch($must)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);
        $bl01Data = !empty($bl01Data[0]) ? $bl01Data[0] : [];
        if (!empty($bl01Data[0])) {
            // 分子 - 授权同意类
            $sqtylError = '';
            $sqtylIsOk = 0;
            foreach ($bl01Data as $value) {
                $must = [
                    ["term" => ['BLBH' => $value['BLBH']]]
                ];
                $params = $blsyService->clearMust()->queryByMustBatch($must)->getParams();
                $restful = app('es')->search($params);
                $blsyData = $blsyService->getDataByEs($restful);
                $blsy = !empty($blsyData[0]) ? $blsyData[0] : [];
                if ($blsy) {
                    $str = '未在出入院时间之内';
                    if ($value['AAB01'] <= $blsy[0]['SYSJ'] && $blsy[0]['SYSJ'] <= $value['AAC01']) {
                        $str = '在出入院时间之内';
                    }
                    $must = [
                        ["term" => ['code' => $blsy[0]['SYYS']]]
                    ];
                    $params = $staffService->clearMust()
                        ->queryByMustBatch($must)
                        ->getParams();
                    $restful = app('es')->search($params);
                    $staffData = $staffService->getDataByEs($restful);
                    $name = !empty($staffData[0]) ? $staffData[0][0]['name'] : '';
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
            $must = [
                ["term" => ['JZHM' => $ZYH]],
                ["term" => ['MBLB' => 59]]
            ];
            $params = $bl01Service->clearMust()
                ->queryByMustBatch($must)
                ->getParams();
            $restful = app('es')->search($params);
            $bl01Data = $bl01Service->getDataByEs($restful);
            $sxzltysData = !empty($bl01Data[0]) ? $bl01Data[0] : [];

            $sxzlIsOk = 0;
            if ($sxzltysData) {
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
                    ->orderBy('ZXSJ','ASC')
                    ->getParams();
                $restful = app('es')->search($params);
                $bl01Data = $bl01Service->getDataByEs($restful);
                $sxsj = !empty($bl01Data[0]) ? $bl01Data[0][0]['ZXSJ'] : '';
                foreach ($sxzltysData as $value) {
                    if ($value['ZXSJ'] <= $sxsj) {
                        $sxzlIsOk = 1;
                        $sxzltysError = '输血治疗同意书【'.$value['ZXSJ'].' < 输血时间：'.$sxsj.'】';
                        break;
                    } else {
                        $sxzltysError = '输血治疗同意书【'.$value['ZXSJ'].' > 输血时间：'.$sxsj.'】';
                    }
                }
            } else {
                $sxzltysError = '';
                $sxzlIsOk = 1;
            }

            // 分子 - 手术知情同意书
            $must = [
                ["term" => ['JZHM' => $ZYH]],
                ["term" => ['BLLB' => 329]]
            ];
            $params = $bl01Service->clearMust()
                ->queryByMustBatch($must)
                ->getParams();
            $restful = app('es')->search($params);
            $bl01Data = $bl01Service->getDataByEs($restful);
            $sszqtysData = $bl01Data[0];

            $sszqtyIsOk = 0;
            if ($sszqtysData) {
                // 查询麻醉记录
                $must = [
                    ["term" => ['HOSPIZATIONID' => $ZYH]]
                ];
                $params = $mzjlService->clearMust()
                    ->queryByMustBatch($must)
                    ->getParams();
                $restful = app('es')->search($params);
                $mzjlData = $mzjlService->getDataByEs($restful);
                $mzjlData = $mzjlData[0];
                if ($mzjlData) {
                    foreach ($sszqtysData as $value) {
                        if ($value['ZXSJ'] <= $mzjlData[0]['OPERATESTARTTIME']) {
                            $sszqtyIsOk = 1;
                            $sszqtysError = '手术知情同意书【'.$value['ZXSJ'].' ＜ 手术开始时间：'.$mzjlData[0]['OPERATESTARTTIME'].'】';
                            break;
                        } else {
                            $sszqtysError = '手术知情同意书【'.$value['ZXSJ'].' > 手术开始时间：'.$mzjlData[0]['OPERATESTARTTIME'].'】';
                        }
                    }
                } else {
                    $sszqtysError = '手术知情同意书【'.$sszqtysData[0]['ZXSJ'].' ＜ 手术开始时间：无】';
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

    /**
     * 主要手术编码正确率
     * @param $patientInfo
     * @return void
     */
    public function zyssbm($patientInfo)
    {
        $errorService = new ElasticsearchService('error');
        $mainOperationService = new ElasticsearchService('main_operation');

        $ZYH = $patientInfo['MED_REC_ID'];

        // 编码员主要手术编码
        $must = [
            ["term" => ['AAA28' => $ZYH]],
        ];
        $params = $mainOperationService->clearMust()->queryByMustBatch($must)->getParams();
        $restful = app('es')->search($params);
        $mainOperationData = $mainOperationService->getDataByEs($restful);
        $ICD9_NAME = !empty($mainOperationData[0][0]['ICD9_NAME']) ? $mainOperationData[0][0]['ICD9_NAME'] : '';
        $ICD9_ID1 = !empty($mainOperationData[0][0]['ICD9_ID1']) ? $mainOperationData[0][0]['ICD9_ID1'] : '';
        if ($ICD9_NAME) {
            // 质控后主要手术编码
            $must = [
                ["term" => ['ZYH' => $ZYH]],
                ["term" => ['error_field' => 'ICD9_NAME']]
            ];
            $params = $errorService->clearMust()->queryByMustBatch($must)->getParams();
            $restful = app('es')->search($params);
            $errorData = $errorService->getDataByEs($restful);
            $errorDesc = !empty($errorData[0][0]['desc']) ? $errorData[0][0]['desc'] : '';
            if ($errorDesc) {
                $numerator = 0;
                $errorMsg = '编码员主要手术编码【'.$ICD9_NAME.'，'.$ICD9_ID1.'】质控后主要手术编码【'.$errorDesc.'（无效）】';
            } else {
                $numerator = 1;
                $errorMsg = '编码员主要手术编码【'.$ICD9_NAME.'，'.$ICD9_ID1.'】质控后主要手术编码【正确】';
            }

            // 记录
            $saveData = ['denominator_zyssbm'=>1,'numerator_zyssbm'=>$numerator,'zyssbm_error'=>$errorMsg];
            PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
        }
    }

    /**
     * 主要手术填写正确率
     * @param $patientInfo
     * @return void
     */
    public function zysstx($patientInfo)
    {
        $bl01NewService = new ElasticsearchService('emr_bl_bl01_new');
        $emrBlBasysjService = new ElasticsearchService('emr_bl_basysj');
        $mainOperationService = new ElasticsearchService('main_operation');

        $ZYH = $patientInfo['MED_REC_ID'];

        $numerator = 0;
        $errorMsg = '';
        // 分母 - 查询手术
        $must = [
            ["term" => ['AAA28' => $ZYH]],
        ];
        $params = $mainOperationService->clearMust()->queryByMustBatch($must)->getParams();
        $restful = app('es')->search($params);
        $mainOperationData = $mainOperationService->getDataByEs($restful);
        $ICD9_NAME = !empty($mainOperationData[0]) ? $mainOperationData[0][0]['ICD9_NAME'] : '';
        if ($ICD9_NAME) {
            // 查询病例病号
            $must = [
                ["term" => ['JZHM' => $ZYH]],
                ["term" => ['BLLB' => 2000001]]
            ];
            $notMust = [
                ["term" => ['BLZT' => 9]]
            ];
            $params = $bl01NewService->clearMust()
                ->queryByMustBatch($must)
                ->queryByMustNotBatch($notMust)
                ->getParams();
            $restful = app('es')->search($params);
            $bl01NewData = $bl01NewService->getDataByEs($restful);
            $blbh = !empty($bl01NewData[0]) ? $bl01NewData[0][0]['BLBH'] : '';
            if ($blbh) {
                // 查询
                $must = [
                    ["term" => ['BLBH' => $blbh]],
                    ["term" => ['XMXH' => 638]]
                ];
                $params = $emrBlBasysjService->clearMust()->queryByMustBatch($must)->getParams();
                $restful = app('es')->search($params);
                $emrBlBasysjData = $emrBlBasysjService->getDataByEs($restful);
                $xmqzList = [];
                if (!empty($emrBlBasysjData[0])) {
                    foreach ($emrBlBasysjData[0] as $val) {
                        if (!empty($val['XMQZ'])) {
                            $xmqzList[] = $val['XMQZ'];
                        }
                    }
                }
                if ($xmqzList) {
                    foreach ($xmqzList as $xmqz) {
                        if (stripos($xmqz,$ICD9_NAME) !== false) {
                            $numerator = 1;
                            $errorMsg = '医生主手术【'.$xmqz.'】 编码后主手术【'.$ICD9_NAME.'（相同）】';
                            break;
                        } else {
                            $errorMsg = '医生主手术【'.$xmqz.'】 编码后主手术【'.$ICD9_NAME.'（不一致）】';
                        }
                    }
                } else {
                    $errorMsg = '医生主手术【无】 编码后主手术【'.$ICD9_NAME.'】';
                }
            } else {
                $errorMsg = '医生主手术【无】 编码后主手术【'.$ICD9_NAME.'】';
            }

            // 记录
            $saveData = ['denominator_zysstx'=>1,'numerator_zysstx'=>$numerator,'zysstx_error'=>$errorMsg];
            PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
        }
    }

    /**
     * 主要诊断编码正确率
     * @param $patientInfo
     * @return void
     */
    public function zyzdbm($patientInfo)
    {
        $errorService = new ElasticsearchService('error');
        $mainDiagnosisService = new ElasticsearchService('main_diagnosis');

        $ZYH = $patientInfo['MED_REC_ID'];

        // 编码员主要诊断
        $must = [
            ["term" => ['ZYH' => $ZYH]]
        ];
        $params = $mainDiagnosisService->clearMust()->queryByMustBatch($must)->getParams();
        $restful = app('es')->search($params);
        $mainDiagnosisData = $mainDiagnosisService->getDataByEs($restful);
        $mainDiagnosisInfo = !empty($mainDiagnosisData[0]) ? $mainDiagnosisData[0][0] : [];
        $ICD10_ID1 = !empty($mainDiagnosisInfo['ICD10_ID1']) ? $mainDiagnosisInfo['ICD10_ID1'] : '无';
        $ICD10_NAME = !empty($mainDiagnosisInfo['ICD10_NAME']) ? $mainDiagnosisInfo['ICD10_NAME'] : '无';

        // 质控后主要诊断
        $must = [
            ["term" => ['ZYH' => $ZYH]],
            ["term" => ['error_field' => 'ABC01N']]
        ];
        $params = $errorService->clearMust()->queryByMustBatch($must)->getParams();
        $restful = app('es')->search($params);
        $errorData = $errorService->getDataByEs($restful);
        $errorDesc = !empty($errorData[0][0]['desc']) ? $errorData[0][0]['desc'] : '';
        if ($errorDesc) {
            $numerator = 0;
            $errorMsg = '编码员主要诊断编码【'.$ICD10_NAME.'，'.$ICD10_ID1.'】质控后主要诊断编码【'.$errorDesc.'（错误）】';
        } else {
            $numerator = 1;
            if ($ICD10_NAME=='无' || $ICD10_ID1=='无') {
                $numerator = 0;
            }
            $errorMsg = '编码员主要诊断编码【'.$ICD10_NAME.'，'.$ICD10_ID1.'】质控后主要诊断编码【正确】';
        }

        // 记录
        $saveData = ['denominator_zyzdbm'=>1,'numerator_zyzdbm'=>$numerator,'zyzdbm_error'=>$errorMsg];
        PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
    }

    /**
     * 主要诊断填写正确率
     * @param $patientInfo
     * @return void
     */
    public function zyzdtx($patientInfo)
    {
        $bl01NewService = new ElasticsearchService('emr_bl_bl01_new');
        $emrBlBasysjService = new ElasticsearchService('emr_bl_basysj');

        $ZYH = $patientInfo['MED_REC_ID'];
        $ABC01N = $patientInfo['ABC01N'];

        // 查询病例病号
        $must = [
            ["term" => ['JZHM' => $ZYH]],
            ["term" => ['BLLB' => 2000001]]
        ];
        $notMust = [
            ["term" => ['BLZT' => 9]]
        ];
        $params = $bl01NewService->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNotBatch($notMust)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01NewData = $bl01NewService->getDataByEs($restful);
        $blbh = !empty($bl01NewData[0]) ? $bl01NewData[0][0]['BLBH'] : '';

        $numerator = 0;
        $errorMsg = '';
        if ($blbh) {
            $must = [
                ["term" => ['BLBH' => $blbh]],
                ["term" => ['XMXH' => 498]]
            ];
            $params = $emrBlBasysjService->clearMust()->queryByMustBatch($must)->getParams();
            $restful = app('es')->search($params);
            $emrBlBasysjData = $emrBlBasysjService->getDataByEs($restful);
            $xmqzList = [];
            if (!empty($emrBlBasysjData[0])) {
                foreach ($emrBlBasysjData[0] as $val) {
                    if (!empty($val['XMQZ'])) {
                        $xmqzList[] = $val['XMQZ'];
                    }
                }
            }
            if ($xmqzList) {
                foreach ($xmqzList as $xmqz) {
                    if (stripos($xmqz,$ABC01N) !== false) {
                        $numerator = 1;
                        $errorMsg = '医生主诊【'.$xmqz.'】 编码后主诊【'.$ABC01N.'（相同）】';
                        break;
                    } else {
                        $errorMsg = '医生主诊【'.$xmqz.'】 编码后主诊【'.$ABC01N.'（不一致）】';
                    }
                }
            } else {
                $errorMsg = '医生主诊【无】 编码后主诊【'.$ABC01N.'】';
            }
        } else {
            $errorMsg = '医生主诊【无】 编码后主诊【'.$ABC01N.'】';
        }

        // 记录
        $saveData = ['denominator_zyzdtx'=>1,'numerator_zyzdtx'=>$numerator,'zyzdtx_error'=>$errorMsg];
        PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
    }



}
