<?php


namespace App\Services;

use App\Model\Department;
use App\Model\EMR_BL_BL01;
use App\Model\FeeDetailed;
use App\Model\Implants;
use App\Model\PACS;
use App\Model\PatientHospitalInfo;
use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use App\Model\PatientInfoTargetNew;
use App\Model\V_JMGS_YMresult;
use App\Model\XJPYZD;
use App\Model\Yzb;
use Carbon\Carbon;

class TargetService
{
    /**
     * CT/MRI检查记录符合率 - 数据处理
     * @param $page
     * @return true
     */
    public function IrcrDataHandle($page)
    {
        while (true) {
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->leftJoin('patient_info_target','patient_info.MED_REC_ID','=','patient_info_target.ZYH')
                ->whereBetween('AAC01',['2022-01-01 00:00:00','2022-12:31 23:59:59'])
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

                echo $ZYH.PHP_EOL;

                if (!$val['ZYH']) {
                    PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],[]);
                }

                // 查询报告单
                $bgdData = $this->bgd($ZYH,$val['AAA28'],$val['AAB01'],$val['AAC01']);
                if ($bgdData) {
                    if (count($bgdData) > 100) {
                        continue;
                    }

                    $ctError = [];
                    $num = $okNum = $index = 0;
                    foreach ($bgdData as $pacs) {
                        $jcmcList = explode(',',$pacs['JCMC']);
                        foreach ($jcmcList as $jcmc) {
                            $num++;
                            $ctError[$index] = ($index+1).'、检查报告单【'.$jcmc.'（'.$pacs['BGSJ'].'）】';

                            // 查询医嘱是否符合
                            $yz = $this->yzb($ZYH,$jcmc);
                            $ctError[$index] .= $yz['msg'];

                            // 病程记录
                            $bcjl = $this->bcjl($ZYH,$jcmc,$pacs['BGSJ']);
                            $ctError[$index] .= $bcjl['msg'];

                            // 判断分子是否符合
                            if ($yz['is_error'] == 200 && $bcjl['is_error'] == 200) {
                                $okNum++;
                            }
                            $index++;
                        }
                    }

                    $numerator = $num==$okNum ? 1 : 0;

                    // 记录
                    if ($ctError) {
                        $saveData = ['denominator_ct'=>1,'numerator_ct'=>$numerator,'ct_error'=>implode("，",$ctError)];
                        PatientInfoTarget::query()->where('ZYH', '=',$ZYH)->update($saveData);
                    }
                }
            }
        }

        return true;
    }

    /**
     * 查询报告单
     * @param $ZYH
     * @return array
     */
    protected function bgd($ZYH,$AAA28,$AAB01,$AAC01)
    {
        // 查询病例报告单有效数据
        $pacsData = PACS::query()->select(['ZYH','JCMC','BGSJ'])
//            ->where('ZYH','=',$ZYH)
            ->where('JZLSH','=',$AAA28)
            ->whereBetween('KDSJ',[$AAB01,$AAC01])
            ->whereNotNull('YXZD')
            ->where('YXZD','!=','')
            ->where('JCMC','not like',"%oct%")
            ->where(function($query){
                $query->where('JCMC','like',"%ct%")->orWhere('JCMC','like',"%mri%");
            })
            ->get()->toArray();

        return $pacsData ? $pacsData : [];
    }

    /**
     * 查询医嘱
     * @param $zyh
     * @param $jcmc
     * @return array
     */
    protected function yzb($zyh,$jcmc)
    {
        // 获取24小时之后的时间
//        $endDateTime = Carbon::parse($pacs['BGSJ'])->addDay(1)->toDateTimeString();

        // 查询医嘱有效数据
        $count = Yzb::query()->select(['YZBXH','YZMC','KZSJ'])
            ->where('ZYH', '=', $zyh)
            ->where('YZMC','not like',"%oct%")
            ->where(function($query){
                $query->where('YZMC','like',"%ct%")->orWhere('YZMC','like',"%mr%")->orWhere('YZMC','like',"%磁共振%");
            })
//            ->where('YZMC', '=', $pacs['JCMC'])
//            ->wcounthereBetween('KZSJ', [$pacs['BGSJ'], $endDateTime])
            ->count();
        if ($count) {
            return ['is_error'=>200,'msg'=>'医嘱【'.$jcmc.'（有）】'];
        }

        return ['is_error'=>1,'msg'=>'医嘱【'.$jcmc.'（无）】'];

        // 查询医嘱无效数据
//        $num = Yzb::query()
//            ->where('ZYH', '=', $pacs['ZYH'])
//            ->where('YZMC', '!=', $pacs['JCMC'])
//            ->count();
//
//        $returnData['is_error'] = 1;
//        $returnData['msg'] = $num ? '医嘱【时间超过24小时】' : '医嘱【无 '.$pacs['JCMC'].'】';
//
//        return $returnData;
    }

    /**
     * 查询病程记录
     * @param $zyh
     * @param $jcmc
     * @param $bgsj
     * @return array
     */
    protected function bcjl($zyh,$jcmc,$bgsj)
    {
        $keyVal = '';
        if (stripos($jcmc,'CT') !== false) {
            $keyVal = 'CT';
        } elseif (stripos($jcmc,'MR') !== false || stripos($jcmc,'磁共振') !== false) {
            $keyVal = 'MR';
        }

        // 24小时内记录类
        $data = EMR_BL_BL01::query()
            ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
            ->where('EMR_BL_BL01.JZHM', '=', $zyh)
            ->where('EMR_BL_BL01.BLLB', '=', 18)
            ->where('EMR_BL_BL01.BLZT','!=',9)
            ->where('EMR_BL_BL01.BLMC','not like',"%首次病程记录%")
//            ->where('HJNR','not like',"%oct%")
            ->where('HJNR','like',"%".$keyVal."%")
            ->get(['ZXSJ','HJNR'])->toArray();
        if ($keyVal == 'CT') {
            $count = false;
            foreach ($data as $val) {
                $hjnr = str_replace('OCT','',$val['HJNR']);
                if (stripos($hjnr,'CT') !== false) {
                    $count = true;
                }
            }
        } else {
            $count = $data ? true : false;
        }

        $zxsj = '';
        if (!$count && $keyVal=='MR') {
            $zxsj = EMR_BL_BL01::query()
                ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                ->where('EMR_BL_BL01.JZHM', '=', $zyh)
                ->where('EMR_BL_BL01.BLLB', '=', 18)
                ->where('EMR_BL_BL01.BLZT','!=',9)
                ->where('EMR_BL_BL01.BLMC','not like',"%首次病程记录%")
                ->where('HJNR','not like',"%oct%")
                ->where('HJNR','like',"%磁共振%")
                ->value('ZXSJ');
            if ($zxsj) {
                $keyVal = '磁共振';
            }
        }
        $bcjlTitle = ['CT'=>'含“ct（oct除外）”','MR'=>'含“mr”','磁共振'=>'含“磁共振”'];
        if ($zxsj) {
            return ['is_error'=>200,'msg'=>'24小时内记录【'.$bcjlTitle[$keyVal].'，'.$zxsj.'（24小时内）】'];
        }

        $data = EMR_BL_BL01::query()
            ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
            ->where('EMR_BL_BL01.JZHM', '=', $zyh)
            ->where('EMR_BL_BL01.BLLB', '=', 294)
            ->where('EMR_BL_BL01.BLZT','!=',9)
            ->where('EMR_BL_BL01.BLMC','not like',"%首次病程记录%")
            ->where('HJNR','like',"%".$keyVal."%")
            ->get(['ZXSJ','HJNR'])->toArray();
        $zxsjDate = [];
        if ($keyVal == 'CT') {
            foreach ($data as $val) {
                $hjnr = str_replace('OCT','',$val['HJNR']);
                if (stripos($hjnr,'CT') !== false) {
                    $zxsjDate[] = $val['ZXSJ'];
                }
            }
        } else {
            foreach ($data as $val) {
                $zxsjDate[] = $val['ZXSJ'];
            }
        }

        if (!$zxsjDate && $keyVal=='MR') {
            $zxsjDate = EMR_BL_BL01::query()
                ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                ->where('EMR_BL_BL01.JZHM', '=', $zyh)
                ->where('EMR_BL_BL01.BLLB', '=', 294)
                ->where('EMR_BL_BL01.BLZT','!=',9)
                ->where('EMR_BL_BL01.BLMC','not like',"%首次病程记录%")
                ->where('HJNR','not like',"%oct%")
                ->where('HJNR','like',"%磁共振%")
                ->distinct()
                ->pluck('ZXSJ')->toArray();
            if ($zxsjDate) {
                $keyVal = '磁共振';
            }
        }

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
     * 植入物相关记录符合率指标 - 数据处理
     * @param $page
     * @return true
     */
    public function ImplantsDataHandle($page)
    {
        // 获取所有植入物信息
        $implantsList = Implants::query()->pluck('name','manufactor')->toArray();

        while (true) {
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->leftJoin('patient_info_target','patient_info.MED_REC_ID','=','patient_info_target.ZYH')
                ->whereBetween('AAC01',['2022-01-01 00:00:00','2022-12:31 23:59:59'])
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

            foreach ($data['data'] as $value) {
                $ZYH = $value['MED_REC_ID'];

                echo $ZYH.PHP_EOL;

                $zrwName = [];
                if (!$value['ZYH']) {
                    PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],[]);
                }

                // 查询费用名称
                $feeList = FeeDetailed::query()
                    ->where('AAA28','=',$ZYH)
                    ->groupBy('FYMC')
                    ->get(['FYMC','FYSL'])->toArray();

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

                if (empty($fymcArr)) {
                    continue;
                }

                $zrwName[] = '收费项目【'.implode('，',$sfxm).'】';

                // 分子 - 查询手术记录
                $scList = EMR_BL_BL01::query()
                    ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                    ->where('EMR_BL_BL01.JZHM', '=', $ZYH)
                    ->whereIn('EMR_BL_BLXG.HJNR', $implantsList)
                    ->where('EMR_BL_BL01.BLLB', '=',303)
                    ->where('BLZT','!=',9)
                    ->pluck('HJNR')->toArray();
                $zrwName[] = $scList ? '手术记录【'.implode('，',$scList).'】' : '手术记录【无】';

                // 分子 - 病程记录
                $bcList = EMR_BL_BL01::query()
                    ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                    ->where('EMR_BL_BL01.JZHM', '=', $ZYH)
                    ->whereIn('EMR_BL_BLXG.HJNR', $implantsList)
                    ->where('EMR_BL_BL01.BLLB', '=',294)
                    ->where('BLZT','!=',9)
                    ->pluck('HJNR')->toArray();
                $zrwName[] = $bcList ? '病程记录【'.implode(',',$bcList).'】' : '病程记录【无】';

                $numerator = 0;
                if ($scList || $bcList) {
                    $numerator = 1;
                }

                // 记录
                $saveData = ['denominator_zrw'=>1,'numerator_zrw'=>$numerator,'zrw_name'=>implode('，',$zrwName)];
                PatientInfoTarget::query()->where('ZYH','=',$ZYH)->update($saveData);
            }
        }

        return true;
    }

    /**
     * 细菌培养检查记录符合率 - 数据处理
     * @param $page
     * @return true
     */
    public function germFosterDataHandle($page)
    {
        // 查询细菌培养字典
        $xjpyFyzdList = XJPYZD::query()
            ->where('mc', '!=', '')
            ->distinct()
            ->pluck('MC')->toArray();

        while (true) {
            $data = PatientInfo::query()
                ->leftJoin('patient_info_target','patient_info.MED_REC_ID','=','patient_info_target.ZYH')
                ->whereBetween('AAC01',['2022-01-01 00:00:00','2022-12:31 23:59:59'])
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
                echo $ZYH.PHP_EOL;

                // 查询分母（费用明细）
                $fymxNameList = FeeDetailed::query()
                    ->where('AAA28', '=', $ZYH)
                    ->where(function ($query) use ($xjpyFyzdList){
                        $query->where('FYMC','！=', "");
                        foreach ($xjpyFyzdList as $mc) {
                            $query->orWhere('FYMC','like', "%" . $mc . "%");
                        }
                    })
                    ->distinct()
                    ->pluck('FYMC')->toArray();
                $denominator_xjpy = $fymxNameList ? 1 : 0;
//                if (!$fymxNameList) {
//                    continue;
//                }
//                $denominator_xjpy = 1;

                // 分子
                $ymresultList = V_JMGS_YMresult::query()
                    ->where('ZYH', '=', $ZYH)
                    ->where('EXAMINAIM', '!=', '')
                    ->whereNotNull('EXAMINAIM')
                    ->groupBy('EXAMINAIM')
                    ->orderByDesc('BGSJ')
                    ->get(['CJSJ','EXAMINAIM'])->toArray();
                if (!$ymresultList) {
                    if ($denominator_xjpy) {
                        $pyZXSJ = EMR_BL_BL01::query()
                            ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                            ->where('EMR_BL_BL01.JZHM', '=', $ZYH)
                            ->where('EMR_BL_BL01.BLLB', '=', 294)
                            ->where('EMR_BL_BL01.BLZT', '!=', 9)
                            ->where('HJNR', 'like', "%培养%")
                            ->value('ZXSJ');
                        if ($pyZXSJ) {
                            $xjpyErrorStr = '1、费用明细【' . implode('，', $fymxNameList) . '】检验报告单【无】医嘱【无】病程记录【培养（'.$pyZXSJ.'）】';
                        } else {
                            $xjpyErrorStr = '1、费用明细【' . implode('，', $fymxNameList) . '】检验报告单【无】医嘱【无】病程记录【无】';
                        }
                    } else {
                        $xjpyErrorStr = '';
                    }
                    $saveData = ['denominator_xjpy' => $denominator_xjpy, 'numerator_xjpy' => 0, 'xjpy_error' => $xjpyErrorStr];
                    PatientInfoTarget::query()->where('ZYH', '=', $ZYH)->update($saveData);
                    continue;
                }

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
                    $EXAMINAIM_NEW = str_replace('加药敏','',$EXAMINAIM);
                    $yz = Yzb::query()
                        ->where('ZYH', $ZYH)
                        ->where(function ($query) use ($EXAMINAIM,$EXAMINAIM_NEW) {
                            $query->where('YZMC', '=', $EXAMINAIM)->orWhere('YZMC', '=', $EXAMINAIM_NEW);
                        })
                        ->value('YZMC');
                    if (empty($yz)) {
                        $EXAMINAIM_ARR = explode('+',$EXAMINAIM);
                        $name = '';
                        foreach ($EXAMINAIM_ARR as $EXAMINAIM_VAL) {
                            $EXAMINAIM_VAL_NEW = str_replace('加药敏','',$EXAMINAIM_VAL);
                            $yz = Yzb::query()
                                ->where('ZYH', $ZYH)
                                ->where(function ($query) use ($EXAMINAIM_VAL,$EXAMINAIM_VAL_NEW) {
                                    $query->where('YZMC', '=', $EXAMINAIM_VAL)
                                        ->orWhere('YZMC', '=', $EXAMINAIM_VAL_NEW);
                                })
                                ->value('YZMC');
                            if ($yz) {
                                $name = $EXAMINAIM_VAL;
                            }
                        }
                        if ($name) {
                            $xjpyError[$key] .= '医嘱【' . $name . '（有）】';
                        } else {
                            $xjpyError[$key] .= '医嘱【' . $EXAMINAIM . '（无）】';
                        }
                    } else {
                        if ($yz) {
                            $xjpyError[$key] .= '医嘱【' . $EXAMINAIM . '（有）】';
                        } else {
                            $xjpyError[$key] .= '医嘱【' . $EXAMINAIM . '（无）】';
                        }
                    }

                    $xjpyError[$key] .= '检验报告单【' . $EXAMINAIM . ' '.$CJSJ.'】';

                    // 分子 - 病程记录查询
                    $xjmcList = V_JMGS_YMresult::query()
                        ->where('ZYH', '=', $ZYH)
                        ->where('EXAMINAIM', '=', $EXAMINAIM)
                        ->where('XJMC','!=','')
                        ->whereNotNull('XJMC')
                        ->distinct()
                        ->pluck('XJMC')->toArray();
                    $xjmcArr = [];
                    $xjmcCount = 0;
                    if ($xjmcList) {
                        foreach ($xjmcList as $xjmcVal) {
                            $bcjlZXSJ = EMR_BL_BL01::query()
                                ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                                ->where('EMR_BL_BL01.JZHM', '=', $ZYH)
                                ->where('EMR_BL_BL01.BLLB', '=', 294)
                                ->where('EMR_BL_BL01.BLZT', '!=', 9)
                                ->where('ZXSJ','>',$EXAMINAIM_CJSJ)
                                ->where('HJNR', 'like', "%" . $xjmcVal . "%")
                                ->value('ZXSJ');
                            if (!$bcjlZXSJ) {
                                $bcjlZXSJ = EMR_BL_BL01::query()
                                    ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                                    ->where('EMR_BL_BL01.JZHM', '=', $ZYH)
                                    ->where('EMR_BL_BL01.BLLB', '=', 294)
                                    ->where('EMR_BL_BL01.BLZT', '!=', 9)
                                    ->where('ZXSJ','>',$EXAMINAIM_CJSJ)
                                    ->where('HJNR', 'like', "%培养%")
                                    ->value('ZXSJ');
                                if ($bcjlZXSJ) {
                                    $xjmcCount++;
                                    $xjmcArr[] = '培养 '.$bcjlZXSJ.' > 检验时间';
                                } else {
                                    $bcjlZXSJ = EMR_BL_BL01::query()
                                        ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                                        ->where('EMR_BL_BL01.JZHM', '=', $ZYH)
                                        ->where('EMR_BL_BL01.BLLB', '=', 294)
                                        ->where('EMR_BL_BL01.BLZT', '!=', 9)
                                        ->where('ZXSJ','<',$EXAMINAIM_CJSJ)
                                        ->where('HJNR', 'like', "%" . $xjmcVal . "%")
                                        ->value('ZXSJ');
                                    if ($bcjlZXSJ) {
                                        $xjmcArr[] = $xjmcVal.' '.$bcjlZXSJ.' < 检验时间';
                                    } else {
//                                        $xjmcArr[] = '无';//$xjmcVal.
                                    }
                                }
                            } else {
                                $xjmcCount++;
                                $xjmcArr[] = $xjmcVal.' '.$bcjlZXSJ.' > 检验时间';
                            }
                        }
                    } else {
                        $xjmcList[] = '培养';
                        $bcjlZXSJ = EMR_BL_BL01::query()
                            ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                            ->where('EMR_BL_BL01.JZHM', '=', $ZYH)
                            ->where('EMR_BL_BL01.BLLB', '=', 294)
                            ->where('EMR_BL_BL01.BLZT', '!=', 9)
                            ->where('ZXSJ','>',$EXAMINAIM_CJSJ)
                            ->where('HJNR', 'like', "%培养%")
                            ->value('ZXSJ');
                        if ($bcjlZXSJ) {
                            $xjmcCount++;
                            $xjmcArr[] = '培养 '.$bcjlZXSJ.' > 检验时间';
                        } else {
                            $bcjlZXSJ = EMR_BL_BL01::query()
                                ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                                ->where('EMR_BL_BL01.JZHM', '=', $ZYH)
                                ->where('EMR_BL_BL01.BLLB', '=', 294)
                                ->where('EMR_BL_BL01.BLZT', '!=', 9)
                                ->where('ZXSJ','<',$EXAMINAIM_CJSJ)
                                ->where('HJNR', 'like', "%培养%")
                                ->value('ZXSJ');
                            if ($bcjlZXSJ) {
                                $xjmcArr[] = '培养 '.$bcjlZXSJ.' < 检验时间';
                            } else {
//                                $xjmcArr[] = '无';
                            }
                        }
                    }

                    if (empty($xjmcArr)) {
                        $xjmcArr[] = '采集时间之后（无）';
                    }

                    if ($xjmcArr) {
                        $xjpyError[$key] .= '病程记录【' . implode(',', $xjmcArr) . '】';
                    } else {
                        $xjpyError[$key] .= '病程记录【（无）】';
                    }

                    if ($yz && !empty($xjmcList) && $xjmcCount == count($xjmcList)) {
                        $index++;
                    }
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
        return true;
    }

    /**
     * 临床用血相关记录符合率 - 数据处理
     * @param $page
     * @return true
     */
    public function clinicalBloodDataHandle($page)
    {
        while (true) {
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->leftJoin('patient_info_target','patient_info.MED_REC_ID','=','patient_info_target.ZYH')
                ->whereBetween('AAC01',['2022-01-01 00:00:00','2022-12:31 23:59:59'])
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

            $medRecId = array_column($data['data'], 'MED_REC_ID');

            // 分母
            $denominator = FeeDetailed::query()
                ->whereIn('AAA28',$medRecId)
                ->distinct()
                ->where('FYMC','like',"%储血费%")
                ->pluck('AAA28')->toArray();
            if (!$denominator) {
                continue;
            }

            // 分子
            foreach ($denominator as $MED_REC_ID) {
                echo $MED_REC_ID.PHP_EOL;
                PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$MED_REC_ID]);

                // 输血同意书
                $bcjlData = [];
                $sxtys = EMR_BL_BL01::query()
                    ->join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                    ->where('JZHM','=',$MED_REC_ID)
                    ->where('BLLB','=',329)
                    ->where(function($query){
                        $query->where('BLMC','like',"%输血同意书%")->orWhere('HJNR','like',"%输血治疗知情同意书%");
                    })
                    ->where('BLZT','!=',9)
                    ->orderBy('ZXSJ')
                    ->value('ZXSJ');
                $sxzltys = '无';
                $zxsj = '';
                if ($sxtys) {
                    $sxzltys = '有';
                    $zxsj = $sxtys;
                }

                // 查询备血
                $bxsxData = [];
                if ($zxsj) {
                    $bxCount = Yzb::query()->where('ZYH','=',$MED_REC_ID)
                        ->where('YZMC','like',"%备血%")
                        ->where('KZSJ','>=',$zxsj)
                        ->count();
                    if ($bxCount) {
                        $bxsxData[] = '备血（有）';
                    } else {
                        $bxNoCount = Yzb::query()->where('ZYH','=',$MED_REC_ID)
                            ->where('YZMC','like',"%备血%")
                            ->where('KZSJ','<',$zxsj)
                            ->count();
                        $bxsxData[] = $bxNoCount ? '备血（小于ZXSJ时间）' : '备血（无）';
                    }
                } else {
                    $bxCount = Yzb::query()->where('ZYH','=',$MED_REC_ID)
                        ->where('YZMC','like',"%备血%")
                        ->count();
                    $bxsxData[] = $bxCount ? '备血（有）' : '备血（无）';
                }

                // 查询输
                if ($zxsj) {
                    $kzsjList = Yzb::query()->where('ZYH','=',$MED_REC_ID)
                        ->where('YZMC','like',"%输%")
                        ->where('KZSJ','>=',$zxsj)
                        ->groupBy('KZSJ')
                        ->orderBy('KZSJ')
                        ->pluck('KZSJ')->toArray();
                    if ($kzsjList) {
                        $kzsj = $kzsjList[count($kzsjList)-1];
                        $bxsxData[] = '输（有），'.$kzsj.' > 输血治疗知情同意书执行时间';
                    } else {
                        $kzsjNoCount = Yzb::query()->where('ZYH','=',$MED_REC_ID)
                            ->where('YZMC','like',"%输%")
                            ->where('KZSJ','<',$zxsj)
                            ->groupBy('KZSJ')
                            ->orderBy('KZSJ')
                            ->count();
                        $bxsxData[] = $kzsjNoCount ? '输（有），'.$zxsj.' < 输血治疗知情同意书执行时间' : '输（无）';
                        $kzsj = '';
                    }
                } else {
                    $kzsjList = Yzb::query()->where('ZYH','=',$MED_REC_ID)
                        ->where('YZMC','like',"%输%")
                        ->groupBy('KZSJ')
                        ->orderBy('KZSJ')
                        ->pluck('KZSJ')->toArray();
                    if ($kzsjList) {
                        $kzsj = $kzsjList[count($kzsjList)-1];
                        $bxsxData[] = '输（有），'.$zxsj.'（输血治疗知情同意书执行时间 无）';
                    } else {
                        $bxsxData[] = '输（无）';
                        $kzsj = '';
                    }
                }

                // 病程记录 - 输血病程记录
                $lcyxBcjl3 = $this->lcyxBcjl3($MED_REC_ID, $kzsj);
                $sxbcjlData = $lcyxBcjl3['data'];
//                $bcjlData[] = $lcyxBcjl3['msg'];
                $sxbcjlError = $lcyxBcjl3['msg'];
                $sxbcjl = $lcyxBcjl3['code']==200 ? 1 : 0;

                // 病程记录 - 效果、血红蛋白、HGB、Hb
                $arr = ['血红蛋白','HGB','Hb'];
                $isOk = false;
                $msgArr = [];
                foreach ($arr as $kk => $v) {
                    if ($kk < 3) {
                        $info = $this->lcyxBcjl4($MED_REC_ID, $sxbcjlData, $v);
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
                if ($sxtys && $bxCount && $kzsjList && $sxbcjl && $isOk) {
                    $numerator = 1;
                }

                // 记录
                $lcyxError = '费用明细【储血费】'.'输血治疗知情同意书【'.$sxzltys.' '.$zxsj.'】'.'，医嘱【'.implode('+',$bxsxData).'】'.'，输血病程记录【'.$sxbcjlError.'】，病程记录【'.implode('，',$bcjlData).'】';
                PatientInfoTarget::query()->where('ZYH',$MED_REC_ID)->update(['denominator_lcyx'=>1,'numerator_lcyx'=>$numerator,'lcyx_error'=>$lcyxError]);
            }
        }

        return true;
    }

    protected function lcyxBcjl3($MED_REC_ID, $kzsj)
    {
        $bcjlIsExist = $bcjlIsExistDate = 0;
        $KZSJData = [$kzsj,Carbon::parse($kzsj)->addDay(1)->toDateTimeString()];
        $sxbcjl = EMR_BL_BL01::query()
            ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
            ->where('EMR_BL_BL01.JZHM', '=', $MED_REC_ID)
            ->where('EMR_BL_BL01.BLLB', '=', 294)
            ->where('EMR_BL_BLXG.HJNR', 'like', "%输血病程记录%")
            ->pluck('ZXSJ')->toArray();
        if ($sxbcjl) {
            return ['code'=>200,'msg'=>'有，'.$sxbcjl[0],'data'=>$sxbcjl];
        } else {
            return ['code'=>1,'msg'=>'无','data'=>$sxbcjl];
        }



        if ($sxbcjl && !$kzsj) {
            return ['code'=>200,'msg'=>'有，'.$sxbcjl[0].'无','data'=>$sxbcjl];
        } elseif(!$sxbcjl && !$kzsj) {
            return ['code'=>1,'msg'=>'无，无，无','data'=>$sxbcjl];
        }

        $zxsj = '无';
        if ($sxbcjl) {
            // 记录有数据
            $bcjlIsExist = 1;
            foreach ($sxbcjl as $v) {
                if ($v>=$KZSJData[0] && $v<=$KZSJData[1]) {
                    // 记录24小时内有数据
                    $zxsj = $v;
                    $bcjlIsExistDate = 1;
                    break;
                }
            }
        }

        $returnData = [];
        if ($bcjlIsExist && $bcjlIsExistDate ) {
            $returnData = ['code'=>200,'msg'=>'有'.'，'.$zxsj.'，输血开嘱时间24小时内','data'=>$sxbcjl];
        } elseif (!$bcjlIsExist && !$bcjlIsExistDate ) {
            $returnData = ['code'=>1,'msg'=>'无'.' '.$zxsj.'，无','data'=>$sxbcjl];
        } elseif (!$bcjlIsExist) {
            $returnData = ['code'=>1,'msg'=>'无'.' '.$zxsj.'，无','data'=>$sxbcjl];
        } elseif (!$bcjlIsExistDate) {
            $returnData = ['code'=>1,'msg'=>'时间超过24小时'.' '.$zxsj.'，无','data'=>$sxbcjl];
        }

        return $returnData;
    }

    protected function lcyxBcjl4($MED_REC_ID, $zxsjList, $key)
    {
        $isExist = $isExistDate = 0;
        $xgIsExist = $xgIsExistDate = 0;
        foreach ($zxsjList as $zxsjDate) {
            $ZXSJData = [$zxsjDate,Carbon::parse($zxsjDate)->addDay(2)->toDateTimeString()];
            $xg = EMR_BL_BL01::query()
                ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                ->where('EMR_BL_BL01.JZHM', '=', $MED_REC_ID)
                ->where('EMR_BL_BL01.BLLB', '=', 294)
                ->where('EMR_BL_BLXG.HJNR', 'like', "%效果%")
                ->pluck('ZXSJ')->toArray();
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

            $ZXSJ = EMR_BL_BL01::query()
                ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                ->where('EMR_BL_BL01.JZHM', '=', $MED_REC_ID)
                ->where('EMR_BL_BL01.BLLB', '=', 294)
                ->where('EMR_BL_BLXG.HJNR', 'like', "%".$key."%")
                ->pluck('ZXSJ')->toArray();
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
     * 获取子菜单相关菜单
     * @param $zyh
     * @param $bllb
     * @return array
     */
    public function getSurgeryList($zyh,$bllb)
    {
        $bllbList = EMR_BL_BL01::query()
            ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
            ->where('JZHM','=',$zyh)
            ->where('BLLB','=',$bllb)
            ->where('BLZT','!=',9)
            ->orderBy('ZXSJ')
            ->get(['JZHM','EMR_BL_BL01.BLBH','BLMC','ZXSJ','HJNR'])->toArray();

        $list = [];
        foreach ($bllbList as $val) {
            $hjnr = $val['HJNR'];
            if ($bllb == 294) { // 病程记录子菜单
                $nameArr = [1=>'术前小结及术前讨论结论记录',2=>'术后首次病程记录',3=>'首次病程记录',4=>'查房记录'];
                if (stripos($hjnr,'术前小结及术前讨论结论记录')) {
                    $surgeryType = 1;
                } elseif (stripos($hjnr,'术后首次病程记录')) {
                    $surgeryType = 2;
                } elseif (stripos($hjnr,'首次病程记录')) {
                    $surgeryType = 3;
                } elseif (stripos($hjnr,'查房记录')) {
                    $surgeryType = 4;
                }
            } elseif ($bllb == 303) { // 手术子菜单
                $nameArr = [1=>'手术风险评估表',2=>'手术安全核查表',3=>'手术同意书',4=>'手术记录'];
                if (stripos($hjnr,'手术风险评估表')) {
                    $surgeryType = 1;
                } elseif (stripos($hjnr,'手术安全核查表')) {
                    $surgeryType = 2;
                } elseif (stripos($hjnr,'手术同意书')) {
                    $surgeryType = 3;
                } elseif (stripos($hjnr,'手术记录')) {
                    $surgeryType = 4;
                }
            }
            if (!empty($nameArr[$surgeryType])) {
                $name = $nameArr[$surgeryType].' '.$val['ZXSJ'];
            } else {
                $name = $val['BLMC'];
            }

            $list[] = [
                'jzhm' => $val['JZHM'],
                'blbh' => $val['BLBH'],
                'name' => $name,
                'type' => $surgeryType,
            ];
        }

        return $list;
    }

    public function depZbStatistics($type,$where,$page,$pageSize,$index='patient_info_target')
    {
        $zbService = new ElasticsearchService($index);

        // 获取科室

        if ($where['depName']) {
            $depList = [$where['depName']];
        } else {
//            $depList = Department::query()->pluck('dep_name')->toArray();
            $depList = PatientInfo::query()->groupBy('AAC11N')->pluck('AAC11N')->toArray();
        }

        // 获取要统计得指标字段
        $zbFieldList = config('zb.zb_field_list');
        $zbField = $zbFieldList[$type];

        if (empty($zbField[1])) {
            return ['data'=>[],'count'=>0];
        }

        $startTime = '';
        $endTime = '';
        $returnData = [];
        foreach ($depList as $depName) {
            // 开始时间 && 结束时间
            if ($where['startTime'] && $where['endTime']) {
                $startTime = date('Y-m-d',strtotime($where['startTime'])).' 00:00:00';
                $endTime = date('Y-m-d',strtotime($where['endTime'])).' 23:59:59';
            } elseif ($where['startTime']) {
                $startTime = date('Y-m-d',strtotime($where['startTime'])).' 00:00:00';
                $endTime = date('Y-m-d H:i:s',time());
            } elseif ($where['endTime']) {
                $startTime = '2015-01-01 00:00:00';
                $endTime = date('Y-m-d',strtotime($where['endTime'])).' 23:59:59';
            }

            // 分母
            $must = [];
            if ($depName){
                $must[] = ["term" => ['AAC11N' => $depName]];
            }
            $must[] = ["term" => [$zbField[1] => 1]];
            if ($startTime && $endTime) {
                $must[] = ['range' => ['AAC01' => ['gte' => $startTime,'lte'=>$endTime]]];
            }
            $params = $zbService->clearMust()
                ->queryByMustBatch($must)
                ->getParams();
            $restful = app('es')->search($params);
            $zbData = $zbService->getDataByEs($restful);
            $denominator = !empty($zbData[1]) ? $zbData[1] : 0;

            // 分子
            $must = [];
            if ($depName){
                $must[] = ["term" => ['AAC11N' => $depName]];
            }
            //$must[] = ["term" => ['AAC11N' => $depName]];
            $must[] = ["term" => [$zbField[0] => 1]];
            $must[] = ["term" => [$zbField[1] => 1]];
            if ($startTime && $endTime) {
                $must[] = ['range' => ['AAC01' => ['gte' => $startTime,'lte'=>$endTime]]];
            }
            $params = $zbService->clearMust()
                ->queryByMustBatch($must)
                ->getParams();
            $restful = app('es')->search($params);
            $zbData = $zbService->getDataByEs($restful);
            $numerator = !empty($zbData[1]) ? $zbData[1] : 0;

            $res = 0;
            if ($numerator && $denominator) {
                $res = bcdiv((string)$numerator, (string)$denominator, 4);
            }

            if ($numerator || $denominator) {
                $returnData[] = [
                    'dep_name' => $depName,
                    'res' => $res,
                    'numerator' => $numerator,
                    'denominator' => $denominator
                ];
            }
        }

        $sort = $where['sort']=='SORT_DESC' ? 3 : 4;
        if ($pageSize == 10) {
            $sort = 3;
        }
        $returnData = $this->arraySort($returnData,'res',$sort);

        $page = ($page-1)*$pageSize;
        $limit = $page+$pageSize;
        $depData = [];
        foreach ($returnData as $key => $value) {
            if ($key >= $page && $key < $limit) {
                $depData[] = $value;
            }
        }

        if ($pageSize > 10) {
            $numerator = array_sum(array_column($depData,'numerator'));
            $denominator = array_sum(array_column($depData,'denominator'));
            $res = 0;
            if ($numerator && $denominator) {
                $res = bcdiv((string)$numerator, (string)$denominator, 4);
            }
            $depData[] = [
                'dep_name' => '全部',
                'res' => $res,
                'numerator' => $numerator,
                'denominator' => $denominator
            ];
        }

        return ['data'=>$depData,'count'=>count($depList)];
    }

    protected function getZbFmFzCount($patientService,$patientInfoTargetService,$page,$depName,$type,$startTime,$endTime,$zbField,$numerator,$denominator)
    {
        $pageSize = 1000;

        $must = ["term" => ['AAC11N' => $depName]];
        $params = $patientService->clearMust()
            ->queryByMust($must)
            ->paginate($page,$pageSize)
            ->getParams();
        $restful = app('es')->search($params);
        $patientData = $patientService->getDataByEs($restful);
        if (!empty($patientData[0])) {
            $must = ['term' => [$zbField[1]=>1]];
            $should = [];
            foreach ($patientData[0] as $patientInfo) {
                $should[] = ["term" => ['ZYH' => $patientInfo['MED_REC_ID']]];
            }

            $params = $patientInfoTargetService->clearMust()
                ->queryByMust($must)
                ->queryByShouldBatch($should)
                ->minimumShouldMatch()
                ->getParams();
            $restful = app('es')->search($params);
            $patientInfoTargetData = $patientInfoTargetService->getDataByEs($restful);
            if (!empty($patientInfoTargetData[0])) {
                foreach ($patientInfoTargetData[0] as $value) {
                    $numerator += $value[$zbField[0]];
                    $denominator += $value[$zbField[1]];
                }

                $numerator += array_sum(array_column($patientInfoTargetData[0],$zbField[0]));
                $denominator += array_sum(array_column($patientInfoTargetData[0],$zbField[0]));
            }

            $sumPage = (int)ceil($patientData[1]/$pageSize);
            if ($page < $sumPage) {
                $page++;
                $this->getZbFmFzCount($patientService,$patientInfoTargetService,$page,$depName,$type,$startTime,$endTime,$zbField,$numerator,$denominator);
            }
        }

        $res = 0;
        if ($numerator && $denominator) {
            $res = bcdiv((string)$numerator, (string)$denominator, 4);
        }
        $returnData = [
            'dep_name' => $depName,
            'res' => $res,
            'numerator' => $numerator,
            'denominator' => $denominator
        ];

        return $returnData;
    }

    public function arraySort($array, $keys, $sort = SORT_DESC)
    {
        $keysValue = [];
        foreach ($array as $k => $v) {
            $keysValue[$k] = $v[$keys];
        }
        array_multisort($keysValue, $sort, $array);
        return $array;
    }

    public function depZbStatisticsList($type,$where,$page,$pageSize,$index='patient_info_target')
    {
        // 获取要统计得指标字段
        $zbFieldList = config('zb.zb_field_list');
        $zbField = $zbFieldList[$type];

        // 获取科室
        $depName = $where['depName'];

        $startTime = '2019-01-01 00:00:00';
        $endTime = '2099-12-31 23:59:59';

//        // 年
//        if ($where['year']) {
//            $startTime = $where['year'].'-01-01 00:00:00';
//            $endTime = $where['year'].'-12-31 23:59:59';
//        }
//        // 季度
//        $quarterData = [
//            1 => [
//                'startTime' => $where['year'].'-01-01 00:00:00',
//                'endTime' => $where['year'].'-03-31 23:59:59'
//            ],
//            2 => [
//                'startTime' => $where['year'].'-04-01 00:00:00',
//                'endTime' => $where['year'].'-06-30 23:59:59'
//            ],
//            3 => [
//                'startTime' => $where['year'].'-07-01 00:00:00',
//                'endTime' => $where['year'].'-09-30 23:59:59'
//            ],
//            4 => [
//                'startTime' => $where['year'].'-10-01 00:00:00',
//                'endTime' => $where['year'].'-12-31 23:59:59'
//            ]
//        ];
//        if ($where['year'] && $where['quarter']) {
//            $quarter = $quarterData[$where['quarter']];
//            $startTime = $quarter['startTime'];
//            $endTime = $quarter['endTime'];
//        }

        // 开始时间 && 结束时间
        if ($where['startTime'] && $where['endTime']) {
            $startTime = date('Y-m-d',strtotime($where['startTime'])).' 00:00:00';
            $endTime = date('Y-m-d',strtotime($where['endTime'])).' 23:59:59';
        } elseif ($where['startTime']) {
            $startTime = date('Y-m-d',strtotime($where['startTime'])).' 00:00:00';
            $endTime = date('Y-m-d H:i:s',time());
        } elseif ($where['endTime']) {
            $startTime = '2015-01-01 00:00:00';
            $endTime = date('Y-m-d',strtotime($where['endTime'])).' 23:59:59';
        }


        if ($type == 60 || $type == 61 || $type == 62) {
            $field = ['patient_info.AAA28','patient_info.MED_REC_ID','patient_info.AAA01','patient_info.AAC11N','patient_info.AAC01','patient_info.AAB01',$index.'.'.$zbField[0],$index.'.'.$zbField[1],$index.'.'.$zbField[2],$index.'.NG_BLBH',$index.'.XG_BLBH'];
        }else{
            $field = ['patient_info.AAA28','patient_info.MED_REC_ID','patient_info.AAA01','patient_info.AAC11N','patient_info.AAC01','patient_info.AAB01',$index.'.'.$zbField[0],$index.'.'.$zbField[1],$index.'.'.$zbField[2]];
        }

        if ($where['status'] == 1) {
            $query = PatientInfo::query()
                ->leftJoin($index,'patient_info.MED_REC_ID','=',$index.'.ZYH')
                ->where($index.'.'.$zbField[1],'=',1)
                ->where($index.'.'.$zbField[0],'=',1)
                ->whereBetween('AAC01',[$startTime,$endTime]);
            if ($depName) {
                $query->where('AAC11N', '=', $depName);
            }
            $data = $query->paginate($pageSize,$field,'page',$page)->toArray();
        } elseif ($where['status'] === 0) {
            $query = PatientInfo::query()
                ->leftJoin($index, 'patient_info.MED_REC_ID', '=', $index.'.ZYH')
                ->where($index. '.' . $zbField[1], '=', 1)
                ->where($index.'.' . $zbField[0], '=', 0)
                ->whereBetween('AAC01', [$startTime, $endTime]);
            if ($depName) {
                $query->where('AAC11N', '=', $depName);
            }
            $data = $query->paginate($pageSize, $field, 'page', $page)->toArray();
        } else {
            $query = PatientInfo::query()
                ->leftJoin($index,'patient_info.MED_REC_ID','=',$index.'.ZYH')
                ->where($index.'.'.$zbField[1],'=',1)
                ->whereBetween('AAC01',[$startTime,$endTime]);
            if ($depName) {
                $query->where('AAC11N', '=', $depName);
            }
            $data = $query->paginate($pageSize, $field, 'page', $page)->toArray();
        }

        $list = [];
        if ($data['data']) {
            foreach ($data['data'] as $key=> $value) {
                if($type == 57){
                    $status = empty($value[$zbField[0]]) ? '正确' : '错误';
                }else{
                    $status = !empty($value[$zbField[0]]) ? '正确' : '错误';
                }
                $describe = !empty($value[$zbField[2]]) ? $value[$zbField[2]] : '';
                $list[$key] = [
                    'AAA28' => $value['AAA28'],
                    'AAA01' => $value['AAA01'],
                    'AAC11N' => $value['AAC11N'],
                    'AAC01' => $value['AAC01'],
                    'AAB01' => $value['AAB01'],
                    'status' => $status,
                    'describe' => $describe,
                    'MED_REC_ID' => $value['MED_REC_ID'],
                ];

                //心梗 脑梗 发病时间
                if ($type == 60 || $type == 61) {
                    if ($describe) {
                        /** @var RadioService $radioService */
                        $radioService = app(RadioService::class);
                        $ngXgData = $radioService->getNgXgData($type, $value);
                        if (!$ngXgData){
                            $list[$key]['fbsj'] = '';
                            $list[$key]['fbsj_s'] = ''; //小时格式的发病时间
                            $list[$key]['zhusu'] = '';
                            $list[$key]['sssj'] = '';
                            $list[$key]['zyzbbh'] = '';
                            $list[$key]['zyzdmc'] = '';
                            $list[$key]['ssbh'] = '';
                            $list[$key]['ssmc'] = '';
                        }else{
                            $list[$key]['fbsj'] = $ngXgData->fbsj_datetime;
                            $list[$key]['fbsj_s'] = $ngXgData->fbsj_s;
                            $list[$key]['zhusu'] = $ngXgData->zhusu;
                            $list[$key]['sssj'] = $ngXgData->sssj_datetime;
                            $list[$key]['zyzbbh'] = $ngXgData->ICD10_ID1;
                            $list[$key]['zyzdmc'] = $ngXgData->ICD10_NAME;
                            $list[$key]['ssbh'] = $ngXgData->ICD9_ID1;
                            $list[$key]['ssmc'] = $ngXgData->ICD9_NAME;
                        }

                        preg_replace("/发病时间【(.*?)】/", '发病时间【' . $list[$key]['fbsj'] . '】', $describe);
                        preg_replace("/发病时间-时【(.*?)】/", '发病时间-时【' . $list[$key]['fbsj_s'] . '】', $describe);
                    }
                }
                if ($type == 62){
                    if ($describe) {
                        /** @var RadioService $radioService */
                        $radioService = app(RadioService::class);
                        $ngXgData = $radioService->getNgXgData($type, $value);
                        if (!$ngXgData){
                            $list[$key]['fbsj'] = '';
                            $list[$key]['zyzdmc'] = '';
                            $list[$key]['zyzbbh'] = '';
                            $list[$key]['rssj'] = '';
                            $list[$key]['yzmc'] = '';
                            $list[$key]['XZJDSJ'] = '';
                        }else{
                            $list[$key]['fbsj'] = $ngXgData->fbsj_datetime;
                            $list[$key]['zyzdmc'] = $ngXgData->ICD10_NAME;
                            $list[$key]['zyzbbh'] = $ngXgData->ICD10_ID1;
                            $list[$key]['rssj'] = $ngXgData->rssj;
                            $list[$key]['yzmc'] = $ngXgData->yzmc;
                            $list[$key]['XZJDSJ'] = $ngXgData->XZJDSJ;
                        }
                    }
                }
            }
        }

        $count = !empty($data['total']) ? $data['total'] : 0;

        return ['data'=>$list,'count'=>$count];
    }

}
