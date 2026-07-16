<?php


namespace App\Services;

use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Model\FeeDetailed;
use App\model\Implants;
use App\Model\Pacs;
use App\Model\PatientHospitalInfo;
use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use App\Model\V_JMGS_YMresult;
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
                ->paginate(500, ['AAA28','MED_REC_ID','ZYH'],'page', $page)
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
                if (!$val['ZYH']) {
                    PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],[]);
                }

                // 查询报告单
                $bgdData = $this->bgd($ZYH);
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
                            $ctError[$index] = ($index+1).'、检查报告单【'.$jcmc.'】';

                            // 查询医嘱是否符合
                            $yz = $this->yzb($pacs['ZYH'],$jcmc);
                            $ctError[$index] .= $yz['msg'];

                            // 病程记录
                            $bcjl = $this->bcjl($pacs['ZYH'],$jcmc,$pacs['BGSJ']);
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
                        $saveData = ['denominator_ct'=>1,'numerator_ct'=>$numerator,'ct_error'=>implode("\n\r",$ctError)];
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
    protected function bgd($ZYH)
    {
        // 查询病例报告单有效数据
        $pacsData = Pacs::query()->select(['ZYH','JCMC','BGSJ'])
            ->where('ZYH','=',$ZYH)
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
                $query->where('YZMC','like',"%ct%")->orWhere('YZMC','like',"%mri%")->orWhere('YZMC','like',"%磁共振%");
            })
//            ->where('YZMC', '=', $pacs['JCMC'])
//            ->wcounthereBetween('KZSJ', [$pacs['BGSJ'], $endDateTime])
            ->count();
        if ($count) {
            return ['is_error'=>200,'msg'=>'医嘱【'.$jcmc.'】'];
        }

        return ['is_error'=>1,'msg'=>'医嘱【无 '.$jcmc.'】'];

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
        // 24小时内记录类
        $count = EMR_BL_BL01::query()
            ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
            ->where('EMR_BL_BL01.JZHM', '=', $zyh)
            ->where('EMR_BL_BL01.BLLB', '=', 18)
            ->where('EMR_BL_BL01.BLZT','!=',9)
            ->where('EMR_BL_BL01.BLMC','not like',"%首次病程记录%")
            ->where('HJNR','not like',"%oct%")
            ->where(function($query){
                $query->where('HJNR','like',"%ct%")->orWhere('HJNR','like',"%mri%")->orWhere('HJNR','like',"%磁共振%");
            })
            ->count();
        if ($count) {
            return ['is_error'=>200,'msg'=>'病程记录【'.$jcmc.'】'];
        }

        // 病程记录有效数据
        $zxsjDate = EMR_BL_BL01::query()
            ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
            ->where('EMR_BL_BL01.JZHM', '=', $zyh)
            ->where('EMR_BL_BL01.BLLB', '=', 294)
            ->where('EMR_BL_BL01.BLZT','!=',9)
            ->where('EMR_BL_BL01.BLMC','not like',"%首次病程记录%")
            ->where('HJNR','not like',"%oct%")
            ->where(function($query){
                $query->where('HJNR','like',"%ct%")->orWhere('HJNR','like',"%mri%")->orWhere('HJNR','like',"%磁共振%");
            })
//            ->whereNotBetween('ZXSJ',[$pacs['BGSJ'],$endDateTime])
            ->distinct()
            ->pluck('ZXSJ')->toArray();

        $bgsj = [$bgsj,Carbon::parse($bgsj)->addDay(1)->toDateTimeString()];
        $bcjlIsExist = $bcjlDateIsExist = 0;
        if ($zxsjDate) {
            // 记录有数据
            $bcjlIsExist = 1;
            foreach ($zxsjDate as $v) {
                if ($v>=$bgsj[0] && $v<=$bgsj[1]) {
                    // 记录24小时内有数据
                    $bcjlDateIsExist = 1;
                }
            }
        }
        if ($bcjlIsExist && $bcjlDateIsExist) {
            $returnData = ['is_error'=>200,'msg'=>'病程记录【'.$jcmc.'】'];
        } elseif ((!$bcjlIsExist && !$bcjlDateIsExist) && !$bcjlIsExist) {
            $returnData = ['is_error'=>1,'msg'=>'病程记录【无 '.$jcmc.'】'];
        } elseif (!$bcjlDateIsExist) {
            $returnData = ['is_error'=>1,'msg'=>'病程记录【时间超过24小时 '.$jcmc.'】'];
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
                ->paginate(500, ['AAA28','MED_REC_ID','ZYH'],'page', $page)
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
                $scList = EMR_BL_BL01::query()->select(['JZHM','HJNR','ZXSJ'])
                    ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                    ->where('EMR_BL_BL01.JZHM', '=', $ZYH)
                    ->whereIn('EMR_BL_BLXG.HJNR', $implantsList)
                    ->where('EMR_BL_BL01.BLLB', '=',303)
                    ->where('BLZT','!=',9)
                    ->pluck('HJNR')->toArray();
                $zrwName[] = $scList ? '手术记录【'.implode('，',$scList).'】' : '手术记录【无】';

                // 分子 - 病程记录
                $bcList = EMR_BL_BL01::query()->select(['JZHM','HJNR','ZXSJ'])
                    ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                    ->where('EMR_BL_BL01.JZHM', '=', $ZYH)
                    ->whereIn('EMR_BL_BLXG.HJNR', $implantsList)
                    ->where('EMR_BL_BL01.BLLB', '=',294)
                    ->where('BLZT','!=',9)
                    ->pluck('HJNR')->toArray();
                $zrwName[] = $bcList ? '病程记录【'.implode('，',$bcList).'】' : '病程记录【无】';

                $numerator = 0;
                if ($scList || $bcList) {
                    $numerator = 1;
                }

                // 记录
                $saveData = ['denominator_zrw'=>1,'numerator_zrw'=>$numerator,'zrw_name'=>implode(',',$zrwName)];
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
        while (true) {
            $data = PatientInfo::query()
                ->leftJoin('patient_info_target','patient_info.MED_REC_ID','=','patient_info_target.ZYH')
                ->paginate(500, ['AAA28','MED_REC_ID','ZYH'],'page', $page)
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
                if (!$val['ZYH']) {
                    PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$val['MED_REC_ID']],[]);
                }
                $ZYH = $val['MED_REC_ID'];
                $xjpyError = [];
                // 分母查询 - 检验报告单查询
                $EXAMINAIMList = V_JMGS_YMresult::query()
                    ->where('ZYH','=',$ZYH)
                    ->where('EXAMINAIM','!=','')
                    ->whereNotNull('EXAMINAIM')
                    ->groupBy('EXAMINAIM')
                    ->get()->toArray();

                if (!$EXAMINAIMList) {
                    continue;
                }

                $index = 0;
                foreach ($EXAMINAIMList as $key => $value) {
                    $EXAMINAIM = $value['EXAMINAIM'];
                    $XJMC = $value['XJMC'];
                    $xjpyError[$key] = ($key+1).'、检验报告单【'.$EXAMINAIM.'】';

                    // 分子 - 检查报告
                    $jcbg = V_JMGS_YMresult::query()
                        ->where('ZYH',$ZYH)
                        ->where('EXAMINAIM','!=','')
                        ->whereNotNull('EXAMINAIM')
                        ->where('PYJG','!=','')
                        ->get()->toArray();

                    // 分子 - 医嘱查询
                    $yz = Yzb::query()
                        ->where('ZYH',$ZYH)
                        ->where('YZMC','=',$EXAMINAIM)
                        ->value('YZMC');
                    if ($yz) {
                        $xjpyError[$key] .= '医嘱【'.$yz.'】（有）';
                    } else {
                        $xjpyError[$key] .= '医嘱【'.$EXAMINAIM.'（无）】';
                    }

                    // 分子 - 病程记录查询
                    $bcjl = EMR_BL_BL01::query()
                        ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                        ->where('EMR_BL_BL01.JZHM', '=', $ZYH)
                        ->where('EMR_BL_BL01.BLLB','=',294)
                        ->where('EMR_BL_BL01.BLZT','!=',9)
                        ->where('HJNR','like',"%".$XJMC."%")
                        ->count();

                    if ($bcjl) {
                        $xjpyError[$key] .= '病程记录【'.$XJMC.'】（有）';
                    } else {
                        $xjpyError[$key] .= '病程记录【'.$XJMC.'（无）】';
                    }

                    if ($jcbg && $yz && $bcjl) {
                        $index++;
                    }
                }

                $numerator = 0;
                if (count($EXAMINAIMList) == $index) {
                    $numerator = 1;
                }

                // 记录
                $saveData = ['denominator_xjpy'=>1,'numerator_xjpy'=>$numerator,'xjpy_error'=>implode("\n\r",$xjpyError)];
                PatientInfoTarget::query()->where('ZYH','=',$ZYH)->update($saveData);
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
                ->paginate(500, ['AAA28','MED_REC_ID','ZYH'],'page', $page)
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
                PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$MED_REC_ID]);

                // 输血同意书
                $bcjlData = [];
                $sxtys = EMR_BL_BL01::query()
                    ->where('JZHM','=',$MED_REC_ID)
                    ->where('BLLB','=',329)
                    ->where('BLMC', 'like', "%输血同意书%")
                    ->where('BLZT','!=',9)
                    ->orderBy('ZXSJ')
                    ->value('ZXSJ');
                $bcjlData[] = $sxtys ? '输血治疗知情同意书（有）' : '输血治疗知情同意书（无）';
                $zxsj = $sxtys ? $sxtys : '';

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
                        $bxsxData[] = '输（有）';
                    } else {
                        $kzsjNoCount = Yzb::query()->where('ZYH','=',$MED_REC_ID)
                            ->where('YZMC','like',"%输%")
                            ->where('KZSJ','<',$zxsj)
                            ->groupBy('KZSJ')
                            ->orderBy('KZSJ')
                            ->count();
                        $bxsxData[] = $kzsjNoCount ? '输（小于ZXSJ时间）' : '输（无）';
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
                        $bxsxData[] = '输（有）';
                    } else {
                        $bxsxData[] = '输（无）';
                        $kzsj = '';
                    }
                }

                // 病程记录 - 输血病程记录
                $lcyxBcjl3 = $this->lcyxBcjl3($MED_REC_ID, $kzsj);
                $sxbcjlData = $lcyxBcjl3['data'];
                $bcjlData[] = $lcyxBcjl3['msg'];
                $sxbcjl = $lcyxBcjl3['code']==200 ? 1 : 0;

                // 病程记录 - 效果、血红蛋白、HGB、Hb
                $arr = ['血红蛋白','HGB','Hb'];
                $isOk = false;
                foreach ($arr as $v) {
                    $info = $this->lcyxBcjl4($MED_REC_ID, $sxbcjlData, $v);
                    $bcjlData[] = $info['msg'];
                    if ($info['code'] == 200) {
                        $isOk = true;
                    }
                }
                // 判断分子是否符合
                $numerator = 0;
                if ($sxtys && $bxCount && $kzsjList && $sxbcjl && $isOk) {
                    $numerator = 1;
                }

                // 记录
                $lcyxError = '费用名称【储血费】'.'，医嘱【'.implode('+',$bxsxData).'】'.'，病程记录【'.implode('+',$bcjlData).'】';
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
        if ($sxbcjl && !$kzsj) {
            return ['code'=>200,'msg'=>'输血病程记录（有）','data'=>$sxbcjl];
        } elseif(!$sxbcjl && !$kzsj) {
            return ['code'=>1,'msg'=>'输血病程记录（无）','data'=>$sxbcjl];
        }

        if ($sxbcjl) {
            // 记录有数据
            $bcjlIsExist = 1;
            foreach ($sxbcjl as $v) {
                if ($v>=$KZSJData[0] && $v<=$KZSJData[1]) {
                    // 记录24小时内有数据
                    $bcjlIsExistDate = 1;
                }
            }
        }

        $returnData = [];
        if ($bcjlIsExist && $bcjlIsExistDate ) {
            $returnData = ['code'=>200,'msg'=>'输血病程记录（有）','data'=>$sxbcjl];
        } elseif (!$bcjlIsExist && !$bcjlIsExistDate ) {
            $returnData = ['code'=>1,'msg'=>'输血病程记录（无）','data'=>$sxbcjl];
        } elseif (!$bcjlIsExist) {
            $returnData = ['code'=>1,'msg'=>'输血病程记录（无）','data'=>$sxbcjl];
        } elseif (!$bcjlIsExistDate) {
            $returnData = ['code'=>1,'msg'=>'输血病程记录（时间超过24小时）','data'=>$sxbcjl];
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
                    }
                }
            }
        }

        $xgIsOk = 0;
        if ($xgIsExist && $xgIsExistDate) {
            $xgMsg = '有';
            $xgIsOk = 1;
        } elseif ((!$xgIsExist && !$xgIsExistDate) && !$xgIsExist) {
            $xgMsg = '无';
        } elseif (!$xgIsExistDate) {
            $xgMsg = '时间超过48小时';
        }

        $isMc = 0;
        if ($isExist && $isExistDate) {
            $str = '有';
            $isMc = 1;
        } elseif ((!$isExist && !$isExistDate) && !$isExist) {
            $str = '无';
        } elseif (!$isExistDate) {
            $str = '时间超过48小时';
        }

        if ($xgIsOk && $isMc) {
            return ['code'=>200,'msg'=>'（效果 '.$xgMsg.' + '.$key.' 有）'];
        }

        return ['code'=>1,'msg'=>'（效果 '.$xgMsg.' + '.$key.' '.$str.'）'];
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

    /**
     * 获取病程记录、手术二级菜单
     * @param $zyh
     * @param $bllb
     * @return array
     */
    public function getSurgeryMenu($zyh,$bllb)
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
            $surgeryType = '';
            $name = '';
            if ($bllb == 294) { // 病程记录子菜单
                if (stripos($hjnr,'术前小结及术前讨论结论记录')) {
                    $surgeryType = 2;
                } elseif (stripos($hjnr,'术后首次病程记录')) {
                    $surgeryType = 3;
                } elseif (stripos($hjnr,'首次病程记录')) {
                    $surgeryType = 1;
                } elseif (stripos($hjnr,'查房记录')) {
                    $surgeryType = 4;
                }
                $title = trim(mb_substr($val['BLMC'],17));
                $date = trim(mb_substr($val['BLMC'],0,17));
                $name = !empty($title) ? $title.' '.$date : $date;
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

                $name = !empty($nameArr[$surgeryType]) ? $nameArr[$surgeryType].' '.$val['ZXSJ'] : $val['BLMC'];
            }

            if ($surgeryType) {
                $list[] = [
                    'jzhm' => $val['JZHM'],
                    'blbh' => $val['BLBH'],
                    'name' => $name,
                    'type' => $surgeryType,
                ];
            }
        }

        return $list;
    }

    /**
     * 获取报告单二级菜单
     * @param $zyh
     * @return array
     */
    public function getPacsMenu($zyh)
    {
        $menuList = [];

        // 获取入院时间、出院时间
        $patientInfo = PatientInfo::query()
            ->where('MED_REC_ID','=',$zyh)
            ->first(['AAB01','AAC01']);
        $AAB01 = $patientInfo->AAB01;
        $AAC01 = Carbon::parse($patientInfo->AAC01)->addDay(7)->toDateTimeString();

        // 获取报告单类型
        $pacsData = PACS::query()
            ->where('ZYH','=', $zyh)
            ->whereBetween('JYSJ',[$AAB01,$AAC01])
            ->groupBy('ExamType')
            ->get(['ZYH','JYSJ','BGSJ','ExamType'])->toArray();

        foreach ($pacsData as $val) {
            if (in_array($val['ExamType'], ['07','08'])) {
                $menuList[0] = ['name'=>'病历图文报告单 ','ZYH'=>$val['ZYH'],'type'=>1];
            } elseif (in_array($val['ExamType'], ['06'])) {
                $menuList[1] = ['name'=>'超声诊断报告 ','ZYH'=>$val['ZYH'],'type'=>2];
            } elseif (in_array($val['ExamType'], ['01','02','03','04','05','09','11'])) {
                $menuList[2] = ['name'=>'影像诊断报告单 ','ZYH'=>$val['ZYH'],'type'=>3];
            } elseif (in_array($val['ExamType'], ['10'])) {
                $menuList[3] = ['name'=>'心电图诊断报告 ','ZYH'=>$val['ZYH'],'type'=>4];
            }
        }

        // 查询是否有检查报告单菜单
        $count = V_JMGS_YMresult::query()
            ->where('ZYH','=', $zyh)
            ->whereBetween('BGSJ',[$AAB01,$AAC01])
            ->count();
        if ($count) {
            $menuList[4] = ['name'=>'检查报告单 ','ZYH'=>$val['ZYH'],'type'=>5];
        }

        if ($menuList) {
            $menu = array_column($menuList,'type');
            array_multisort($menu,SORT_ASC,$menuList);
        }

        return $menuList;
    }

}
