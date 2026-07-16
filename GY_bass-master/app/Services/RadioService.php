<?php

namespace App\Services;

use App\Model\EMR_BL_BL01;
use App\Model\FeeDetailed;
use App\Model\MainOperation;
use App\Model\MedicinalInfo;
use App\Model\OperationInfo;
use App\Model\Pacs;
use App\Model\PatientHospitalInfo;
use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use App\Model\Yzb;
use Carbon\Carbon;
use Exception;
use App\Model\PatientMedicalInfo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\EventListener\ValidateRequestListener;

/**
 * 比例数据
 */
class RadioService
{
    // 手术判别是手术和介入治疗
    public const SSPB14 = [1, 4];

    /**
     * @param array $where
     * @param int $type
     * @return mixed
     * @throws Exception
     * 获取绩效考核指标数据
     */
    public function getList(array $where = [], int $type = 0)
    {
        if ($type === 0) {
            $res = $this->leaveHospital($where); // 出院患者手术占比
        } elseif ($type === 1) {
            $res = $this->gradeFour($where); // 出院患者四级手术占比
        } elseif ($type === 2) {
            $res = $this->infectRadio($where);// I类切口手术部位感染率
        } elseif ($type === 3) {
            $res = $this->miniInvasive($where); // 出院患者微创手术占比
        } elseif ($type === 4) {
            $res = $this->complication($where); // 手术患者并发症发生率
        } elseif ($type === 32) {
            $res = $this->bingli($where); // 病理检查记录符合率
        } elseif ($type === 41) {
            $res = $this->kjyw($where); // 抗菌药物使用记录符合率
        } elseif ($type === 42) {
            $res = $this->exzlhxzl($where); // 恶性肿瘤化学治疗记录符合率
        } elseif ($type === 43) {
            $res = $this->exzlfszl($where); // 恶性肿瘤放射治疗记录符合率
        } elseif ($type === 57) {
            $res = $this->bfhfsl($where); // 不合理复制病历发生率
        } elseif ($type === 31) {
            $res = $this->getIrcrData($where); // CT/MRI检查记录符合率
        } elseif ($type === 45) {
            $res = $this->getZrwData($where); // 植入物相关记录符合率
        } elseif ($type === 33) {
            $res = $this->getXjpyData($where); // 细菌培养相关记录符合率
        } elseif ($type === 46) {
            $res = $this->getLcyxData($where); // 临床用血相关记录符合率
        } elseif ($type === 44) {
            $res = $this->ssxgjl($where); // 手术相关记录完整率
        }

        if (empty($res)) {
            return ['numerator' => 0, 'denominator' => 0, 'res' => 0];
        }

        return $res;
    }

    /**
     * @param array $where
     * @return array|int
     * 出院患者手术占比
     */
    public function leaveHospital(array $where = [])
    {
        // 出院患者手术台次数，手术判别SSPB是手术1和介入治疗4相加总人数。
        $where['SSPB'] = self::SSPB14;
        $operation = MainOperation::getLeaveHospitalData($where);
        if (!$operation) {
            return 0;
        }

        // 设置手术判别为空，获取所有的同期出院人数
        unset($where['SSPB']);
        $operationTotal = MainOperation::getLeaveHospitalData($where);
        if (!$operationTotal) {
            return 0;
        }

        // 出院比例 = 手术判别是手术和介入治疗相加总人数 / 同期出院总人数
        $res = bcdiv((string)$operation, (string)$operationTotal, 4);

        return ['numerator' => $operation, 'denominator' => $operationTotal, 'res' => $res];
    }

    /**
     * @param array $where
     * @return array|int
     * 出院患者四级手术占比
     */
    public function gradeFour(array $where = [])
    {
        // 获取四级手术的信息
        $operationInfo = OperationInfo::getList(['type' => 0], ['ICD9_ID1']);
        if (empty($operationInfo)) {
            return 0;
        }
        $operationIds = array_column($operationInfo, 'ICD9_ID1');

        // 出院患者住院期间实施四级手术和按照四级手术管理的介入诊疗人数之和
        $where['SSPB'] = self::SSPB14;
        $where['ICD9_ID1'] = $operationIds;
        $operation = MainOperation::getLeaveHospitalData($where);
        if (!$operation) {
            return 0;
        }

        // 出院患者手术（含介入）人数。
        unset($where['ICD9_ID1']);
        $operationTotal = MainOperation::getLeaveHospitalData($where);
        if (!$operationTotal) {
            return 0;
        }

        $res = bcdiv((string)$operation, (string)$operationTotal, 4);

        return ['numerator' => $operation, 'denominator' => $operationTotal, 'res' => $res];
    }

    /**
     * @param array $where
     * @return array|int
     * I类切口手术部位感染率
     */
    public function infectRadio(array $where = [])
    {
        // INCISION_GRADE_ID 切口愈合等级ID
        // 手术为I类切口且切口愈合等级为“丙级愈合”（代码为3）选项的人数
        $where['INCISION_GRADE_ID'] = '1-3';
        $operation = MainOperation::getLeaveHospitalData($where);
        if (!$operation) {
            return 0;
        }

        // 同期出院患者手术为I类切口人数
        $where['INCISION_GRADE_ID'] = ['1-0', '1-1', '1-2', '1-3'];
        $operationTotal = MainOperation::getLeaveHospitalData($where);
        if (!$operationTotal) {
            return 0;
        }

        $res = bcdiv((string)$operation, (string)$operationTotal, 4);
        return ['numerator' => $operation, 'denominator' => $operationTotal, 'res' => $res];
    }

    /**
     * @param array $where
     * @return array|int
     * 出院患者微创手术占比
     */
    public function miniInvasive(array $where = [])
    {
        // 获取微创手术的信息
        $operationInfo = OperationInfo::getList(['type' => 1], ['ICD9_ID1']);
        if (empty($operationInfo)) {
            return 0;
        }
        $operationIds = array_column($operationInfo, 'ICD9_ID1');

        // 出院患者住院期间实施四级手术和按照四级手术管理的介入诊疗人数之和
        $where['ICD9_ID1'] = $operationIds;
        $operation = MainOperation::getLeaveHospitalData($where);
        if (!$operation) {
            return 0;
        }

        // 出院患者手术（含介入）人数。
        unset($where['ICD9_ID1']);
        $where['SSPB'] = self::SSPB14;
        $operationTotal = MainOperation::getLeaveHospitalData($where);
        if (!$operationTotal) {
            return 0;
        }

        $res = bcdiv((string)$operation, (string)$operationTotal, 4);
        return ['numerator' => $operation, 'denominator' => $operationTotal, 'res' => $res];
    }

    // 手术患者并发症发生率▲
    // 8.1.手术患者并发症发生例数
    // 做过手术，出院诊断【3-ICD10_NAME】符合“手术并发症诊断相关名称”且该诊断入院病情【ABC03C】为“无”（代码为4）的病例。
    //（同一次住院就诊期间患有同一疾病或不同疾病施行多次手术者，按1人统计）
    // 8.2.同期出院的手术患者人数
    // 同期出院的手术患者人数是指同期出院患者【SSLX】择期手术人数。
    // | 代码 |名称|
    // | 1  |择期手术|
    // | 2  |急诊手术|
    // | 3  |限期手术|
    // 统计单位以人数计算，总数为实施择期手术和介入治疗人数累加求和。
    // 不包括妊娠、分娩、围产期、新生儿患者。
    //（同一次住院就诊期间患有同一疾病或不同疾病施行多次手术者，按1人统计）
    /**
     * @param array $where
     * @return array|int
     * 手术患者并发症发生率
     */
    public function complication(array $where = [])
    {
        // 做过手术，出院诊断【3-ICD10_NAME】符合“手术并发症诊断相关名称”且该诊断入院病情【ABC03C】为“无”（代码为4）的病例。
        $startTime = $where['start_time'];
        $endTime = $where['end_time'];
        $obj = PatientMedicalInfo::query()
            ->Join("main_operation", "main_operation.AAA28", "=", "patient_medical_info.AAA28")
            ->Join("main_diagnosis", "main_diagnosis.AAA28", "=", "patient_medical_info.AAA28")
            ->join('disease_diagnosis_code as ddc', 'ddc.OLD_ICD10_ID1', '=', 'main_diagnosis.ICD10_ID1')
            ->where('patient_medical_info.ABC03C', '=', 4);
        if ($startTime && $endTime) {
            $obj = $obj->whereBetween(Db::raw('UNIX_TIMESTAMP(main_operation.OPE_DATE)'), [$startTime, $endTime]);
        }
        $bfzArr = ['I26', 'I80.2', 'I82.8', 'A40.0', 'A40.9', 'A41.0', 'A41.9', 'T81.411', 'B37.700', 'B49.x00x019', 'T81.0', 'T81.3', 'R96.0', 'R96.1', 'I46.1', 'J95.800x004', 'J96.0', 'J96.1', 'J96.9', 'E89.0', 'E89.9', 'T81.4', 'T81.5', 'T81.6', 'T88.2', 'T88.5', 'J95.1', 'J95.4', 'J95.8', 'J95.9', 'J98.4', 'J15', 'J16', 'J18', 'T81.2', 'N17.0', 'N17.9', 'N99.0', 'K91.0', 'K91.9', 'I97.0', 'I97.1', 'I97.8', 'I97.9', 'G97.0', 'G97.1', 'G97.2', 'G97.8', 'G97.9', 'I60', 'I64', 'H59.0', 'H59.8', 'H59.9', 'H95.0', 'H95.1', 'H95.8', 'H95.9', 'M96.0', 'M96.9', 'N98.0', 'N99.9', 'K11.4', 'T81.2', 'T82.0', 'T82.9', 'T83.0', 'T83.9', 'T84.0', 'T84.9', 'T85.0', 'T85.9', 'T86.0', 'T86.9', 'T87.0', 'T87.6', 'T81.1', 'T81.7', 'T81.8', 'T81.9'];
        $likeRawSql = '(';
        foreach ($bfzArr as $sk => $sv) {
            if (count($bfzArr) == $sk + 1) {
                $likeRawSql .= 'ddc.ICD10_ID1 like "' . $sv . '%"';
            } else {
                $likeRawSql .= 'ddc.ICD10_ID1 like "' . $sv . '%" or ';
            }
        }
        $likeRawSql .= ')';
        $obj = $obj->whereRaw($likeRawSql);
        $operation = $obj->count();

        if (!$operation) {
            return 0;
        }

        // 同期出院的手术患者人数是指同期出院患者【SSLX】择期手术人数,OPE_TYPE对应的是dis中的SSLX
        $obj = PatientMedicalInfo::query()
            ->Join("patient_info", "patient_info.MED_REC_ID", "=", "patient_medical_info.AAA28")
            ->Join("main_operation", "main_operation.AAA28", "=", "patient_medical_info.AAA28")
            ->Join("main_diagnosis", "main_diagnosis.AAA28", "=", "patient_medical_info.AAA28")
            ->join('disease_diagnosis_code as ddc', 'ddc.OLD_ICD10_ID1', '=', 'main_diagnosis.ICD10_ID1')
            ->where('main_operation.OPE_TYPE', '=', 4);
        if ($startTime && $endTime) {
            $obj = $obj->whereBetween(Db::raw('UNIX_TIMESTAMP(main_operation.OPE_DATE)'), [$startTime, $endTime]);
        }
        $obj = $obj->whereRaw('ddc.ICD10_ID1 not like "O%" AND ddc.ICD10_ID1 not like "P%" and (patient_info.AAA04 > 0 or (patient_info.AAA04 = 0 and patient_info.AAA40 > 28))');
        $operationTotal = $obj->count();

        if (!$operationTotal) {
            return 0;
        }

        $res = bcdiv((string)$operation, (string)$operationTotal, 4);

        return ['numerator' => $operation, 'denominator' => $operationTotal, 'res' => $res];
    }

    /**
     * 病理检查记录符合率
     */
    public function bingli(array $where = [])
    {
        $year = $where['year'] ?? date('Y');
        $resArr = [];
        for ($i = 1; $i <= 12; $i++) {
            $startTime = strtotime($year . '-' . $i . '-01');
            $t = date('t', $startTime);
            $endTime = $startTime + $t * 3600 * 24;
            // 所有符合条件的病例信息
            $denominator = PatientInfo::query()
                ->join('patient_info_target as target', 'target.ZYH', '=', 'patient_info.MED_REC_ID')
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->where('target.denominator_bl', '=', 1)->count();

            if (!$denominator) {
                $resArr[] = ['numerator' => 0, 'denominator' => 0, 'res' => 0, 'time' => $year . '-' . sprintf('%02s', $i), 'source' => '系统提取'];
                continue;
            }
            // 所有符合条件的病例信息
            $numerator = $bgdObj = PatientInfo::query()
                ->join('patient_info_target as target', 'target.ZYH', '=', 'patient_info.MED_REC_ID')
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->where('target.numerator_bl', '=', 1)->count();

            $res = bcdiv((string)$numerator, (string)$denominator, 4);
            $resArr[] = ['numerator' => $numerator, 'denominator' => $denominator, 'res' => $res, 'time' => $year . '-' . sprintf('%02s', $i), 'source' => '系统提取'];

        }
        return $resArr;
    }

    /**
     * 病理检查记录符合率
     */
    public function bingliData(array $where = [])
    {

        $page = 0;
        $pageSize = 100;
        while (1) {
            $page++;
            $pageStart = ($page - 1) * $pageSize;
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->join('fee_detailed', 'fee_detailed.AAA28', '=', 'patient_info.MED_REC_ID')
                ->where('fee_detailed.is_bingli', '=', 1)
                ->offset($pageStart)
                ->limit($pageSize)
                ->get(['patient_info.MED_REC_ID', 'fee_detailed.FYMC'])->toArray();
            if(empty($data)){
                break;
            }
            $denominator = [];
            $numerator = [];

            foreach ($data as $d) {
                $isError = 1;
                $bl_error = '收费项目【' . $d['FYMC'] . '】';
                $denominator[] = $d['MED_REC_ID'];

                $pack = Pacs::query()
                    ->where('PACS.ZYH', '=', $d['MED_REC_ID'])
                    ->where('PACS.JCBW', 'LIKE', "%液基%")
                    ->get(['YXZD'])->toArray();
                if (!$pack) {
                    $bl_error .= '，检查报告单： 【关键字：液基（无）】';
                    $isError = 0;
                } else {
                    $bl_error .= '，检查报告单： 【关键字：液基（有）】';
                }

                $pack = Pacs::query()
                    ->where('PACS.ZYH', '=', $d['MED_REC_ID'])
                    ->where('PACS.YXZD', '<>', "")
                    ->get(['YXZD'])->toArray();
                if (!$pack) {
                    $bl_error .= '，检查报告单： 【检查诊断或提示为空】';
                    $isError = 0;
                } else {
                    $bl_error .= '，检查报告单： 【检查诊断或提示不为空】';
                }

                // 手术记录
                $shoushu = EMR_BL_BL01::query()
                    ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                    ->where('EMR_BL_BL01.JZHM', '=', $d['MED_REC_ID'])
                    ->where('EMR_BL_BL01.BLLB', '=', 294)
                    ->whereRaw('(EMR_BL_BLXG.HJNR like "%液基%" or EMR_BL_BLXG.HJNR like "%tct%")')
                    ->count();
                if (!$shoushu) {
                    $bl_error .= '，手术记录： 【关键字：“液基” 或 “tct”（无）】';
                    $isError = 0;
                } else {
                    $bl_error .= '，手术记录： 【关键字：“液基” 或 “tct”（有）】';
                }

                if($isError){
                    $numerator[] = ['ZYH' => $d['MED_REC_ID'], 'numerator_bl' => $isError, 'bl_error' => $bl_error];
                    continue;
                }

                $isError = 1;
                $bl_error = '收费项目【' . $d['FYMC'] . '】';

//                $yzb = Yzb::query()
//                    ->where('ZYH', '=', $d['MED_REC_ID'])
//                    ->where('YZMC', '=', '图文病理报告')
//                    ->count();
//                if (!$yzb) {
//                    $bl_error .= '，医嘱【图文病理报告（无）】';
//                    $isError = 0;
//                } else {
//                    $bl_error .= '，医嘱【图文病理报告】';
//                }

                // 报告单  备注：报告单在其他数据表
                $shoushu = Pacs::query()
                    ->where('PACS.ZYH', '=', $d['MED_REC_ID'])
                    ->where('PACS.YXZD', '<>', "")
                    ->where('PACS.ExamType', '=', 7)
                    ->get(['YXZD'])->toArray();
                if (!$shoushu) {
                    $bl_error .= '，检查报告单： （无）';
                    $isError = 0;
                } else {
                    $bl_error .= '，检查报告单： 【' . $shoushu[0]['YXZD'] . '】';
                }

                // 手术记录
                $shoushu = EMR_BL_BL01::query()
                    ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                    ->where('EMR_BL_BL01.JZHM', '=', $d['MED_REC_ID'])
                    ->where('EMR_BL_BL01.BLLB', '=', 303)
                    ->whereRaw('(EMR_BL_BLXG.HJNR like "%病理%" or EMR_BL_BLXG.HJNR like "%取材%")')
                    ->count();
                if (!$shoushu) {
                    $bl_error .= '，手术记录： 【关键字：“病理” 或 “取材”（无）】';
                    $isError = 0;
                } else {
                    $bl_error .= '，手术记录： 【关键字：“病理” 或 “取材”（有）】';
                }

                // 病程记录
                $bingcheng = EMR_BL_BL01::query()
                    ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                    ->where('EMR_BL_BL01.JZHM', '=', $d['MED_REC_ID'])
                    ->where('EMR_BL_BL01.BLLB', '=', 294)
                    ->whereRaw('(EMR_BL_BLXG.HJNR like "%病理%" or EMR_BL_BLXG.HJNR like "%取材%")')
                    ->count();

                if (!$bingcheng) {
                    $bl_error .= '，病程记录：【关键字：“病理” 或 “取材”（无）】';
                    $isError = 0;
                } else {
                    $bl_error .= '，病程记录：【关键字：“病理” 或 “取材”（有）】';
                }
                $numerator[] = ['ZYH' => $d['MED_REC_ID'], 'numerator_bl' => $isError, 'bl_error' => $bl_error];
            }

            if ($denominator) {
                $denominator = array_unique($denominator);
                foreach ($denominator as $d) {
                    PatientInfoTarget::query()->updateOrInsert(['ZYH' => $d], ['denominator_bl' => 1]);
                }
            }
            if ($numerator) {
                foreach ($numerator as $d) {
                    PatientInfoTarget::query()->updateOrInsert(['ZYH' => $d['ZYH']], ['numerator_bl' => $d['numerator_bl'], 'bl_error' => $d['bl_error']]);
                }
            }
            var_dump($numerator);
        }
        var_dump('bingli_en');
    }

    /**
     * 抗菌药物使用记录符合率
     */
    public function kjyw(array $where = [])
    {
        $year = $where['year'] ?? date('Y');
        $resArr = [];
        for ($i = 1; $i <= 12; $i++) {
            $startTime = strtotime($year . '-' . $i . '-01');
            $t = date('t', $startTime);
            $endTime = $startTime + $t * 3600 * 24;
            // 所有符合条件的病例信息
            $denominator = PatientInfo::query()
                ->join('patient_info_target as target', 'target.ZYH', '=', 'patient_info.MED_REC_ID')
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->where('target.denominator_kjyw', '=', 1)->count();

            if (!$denominator) {
                $resArr[] = ['numerator' => 0, 'denominator' => 0, 'res' => 0, 'time' => $year . '-' . sprintf('%02s', $i), 'source' => '系统提取'];
                continue;
            }
            // 所有符合条件的病例信息
            $numerator = $bgdObj = PatientInfo::query()
                ->join('patient_info_target as target', 'target.ZYH', '=', 'patient_info.MED_REC_ID')
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->where('target.numerator_kjyw', '=', 1)->count();

            $res = bcdiv((string)$numerator, (string)$denominator, 4);
            $resArr[] = ['numerator' => $numerator, 'denominator' => $denominator, 'res' => $res, 'time' => $year . '-' . sprintf('%02s', $i), 'source' => '系统提取'];

        }
        return $resArr;
    }


    public function cacheData()
    {
        // 病理
        $this->bingliData();
        // 抗菌药物检查
        $this->kjywData();
        // 化疗药物检查
        $this->exzlhxzlData();
        // 医嘱名称“放疗”关键字检查
        $this->exzlfszlData();
        // 不合理复制病历信息检查
        $this->buheliCopy();
        // 手术相关记录完整率
        $this->operationComplete();
    }

    /**
     * 抗菌药物病例的数据清洗，将含有抗菌药物的病例信息打上标识
     */
    public function kjywData()
    {
        $page = 0;
        $pageSize = 100;
        while (1) {
            $page++;
            // 所有符合条件的病例信息
            // SELECT * FROM `patient_info` as a left join yzb as b on a.MED_REC_ID=b.ZYH
            // where a.denominator_kjyw=0 and b.is_has_kjyw=1
            $offset = ($page - 1) * $pageSize;
            $data = PatientInfo::query()
                ->Join('yzb', 'patient_info.MED_REC_ID', '=', 'yzb.ZYH')
                ->where('yzb.is_has_kjyw', '=', 1)
                ->offset($offset)
                ->limit($pageSize)
                ->get(['yzb.ZYH', 'yzb.YZMC', 'yzb.KZSJ', 'yzb.kjyw_name'])->toArray();
            if (!$data) {
                break;
            }
            $newData = [];
            foreach ($data as $d) {
                $newData[$d['ZYH']][] = $d;
            }
            $allData = array_column($data, 'ZYH');
            $fenzi = [];
            foreach ($newData as $med => $data) {
                $flag = true;
                $kjyw_name = '';
                foreach ($data as $y) {
                    $kjyw_name = $y['kjyw_name'];
                    $bingcheng = EMR_BL_BL01::query()
                        ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                        ->where('EMR_BL_BL01.JZHM', '=', $y['ZYH'])
                        ->where('EMR_BL_BL01.BLLB', '=', 294)
                        ->where('EMR_BL_BLXG.HJNR', 'like', '%' . $y['kjyw_name'] . '%')
                        ->get(['EMR_BL_BLXG.HJNR'])->toArray();
                    if (!$bingcheng) {
                        $flag = false;
                        $fenzi[] = ['ZYH' => $med, 'numerator_kjyw' => 0, 'kjyw_error' => '医嘱【' . $kjyw_name . '】' . '病程记录【 无  ' . $kjyw_name . '】'];
                        break;
                    }

                    // 查询24小时内的有抗菌药物的病程记录
                    $sTime = strtotime($y['KZSJ']);
                    $eTime = $sTime + 24 * 3600;

                    $bingcheng = EMR_BL_BL01::query()
                        ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                        ->where('EMR_BL_BL01.JZHM', '=', $y['ZYH'])
                        ->where('EMR_BL_BL01.BLLB', '=', 294)
                        ->where('EMR_BL_BLXG.HJNR', 'like', '%' . $y['kjyw_name'] . '%')
                        ->where(Db::raw('UNIX_TIMESTAMP(EMR_BL_BL01.ZXSJ)'), '<', $eTime)
                        ->get(['EMR_BL_BLXG.HJNR'])->toArray();
                    if (!$bingcheng) {
                        $flag = false;
                        $fenzi[] = ['ZYH' => $med, 'numerator_kjyw' => 0, 'kjyw_error' => '医嘱【' . $kjyw_name . '】病程【' . $kjyw_name . '】但是病程记录【 时间超过24小时 】'];
                        break;
                    }
                }
                if ($flag === true) {
                    $fenzi[] = ['ZYH' => $med, 'numerator_kjyw' => 1, 'kjyw_error' => '医嘱有【' . $kjyw_name . '】病程有【' . $kjyw_name . '】'];
                }
            }

            $allData = array_unique($allData);
            if ($allData) {
                foreach ($allData as $d) {
                    PatientInfoTarget::query()->updateOrInsert(['ZYH' => $d], ['denominator_kjyw' => 1]);
                }
            }
            if ($fenzi) {
                foreach ($fenzi as $d) {
                    PatientInfoTarget::query()->updateOrInsert(
                        ['ZYH' => $d['ZYH']],
                        [
                            'numerator_kjyw' => $d['numerator_kjyw'],
                            'kjyw_error' => $d['kjyw_error']
                        ]
                    );
                }
            }
            var_dump($fenzi);
        }
        var_dump('kjywData_end');
    }

    /**
     * 恶性肿瘤化学治疗记录符合率，将含有化学药品的病例信息打上标识
     */
    public function exzlhxzlData()
    {
        $page = 0;
        $pageSize = 100;
        while (1) {
            $page++;
            $offset = ($page - 1) * $pageSize;
            // 所有符合条件的病例信息
            $data = $bgdObj = PatientInfo::query()
                ->Join('yzb', 'patient_info.MED_REC_ID', '=', 'yzb.ZYH')
                ->where('yzb.is_has_hlyw', '=', 1)
                ->offset($offset)
                ->limit($pageSize)
                ->get(['yzb.ZYH', 'yzb.YZMC', 'yzb.KZSJ', 'yzb.hlyw_name'])->toArray();

            if (!$data) {
                break;
            }
            $newData = [];
            foreach ($data as $d) {
                $newData[$d['ZYH']][] = $d;
            }
            $allData = array_column($data, 'ZYH');
            $fenzi = [];
            foreach ($newData as $med => $data) {
                $flag = true;
                $hlyw_name = '';
                foreach ($data as $y) {
                    $hlyw_name = $y['hlyw_name'];
                    $bingcheng = EMR_BL_BL01::query()
                        ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                        ->where('EMR_BL_BL01.JZHM', '=', $y['ZYH'])
                        ->where('EMR_BL_BL01.BLLB', '=', 294)
                        ->where('EMR_BL_BLXG.HJNR', 'like', '%' . $hlyw_name . '%')
                        ->get(['EMR_BL_BLXG.HJNR'])->toArray();
                    if (!$bingcheng) {
                        $flag = false;
                        $fenzi[] = ['ZYH' => $med, 'numerator_exzlhxzl' => 0, 'exzlhxzl_error' => '医嘱【' . $hlyw_name . '】' . '病程记录【 无  ' . $hlyw_name . '】'];
                        break;
                    }

                    $sTime = strtotime($y['KZSJ']);
                    $eTime = $sTime + 24 * 3600;
                    $bingcheng = EMR_BL_BL01::query()
                        ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                        ->where('EMR_BL_BL01.JZHM', '=', $y['ZYH'])
                        ->where('EMR_BL_BL01.BLLB', '=', 294)
                        ->where('EMR_BL_BLXG.HJNR', 'like', '%' . $hlyw_name . '%')
                        ->where(Db::raw('UNIX_TIMESTAMP(EMR_BL_BL01.ZXSJ)'), '<', $eTime)
                        ->get(['EMR_BL_BLXG.HJNR'])->toArray();
                    if (!$bingcheng) {
                        $flag = false;
                        $fenzi[] = ['ZYH' => $med, 'numerator_exzlhxzl' => 0, 'exzlhxzl_error' => '医嘱【' . $hlyw_name . '】' . '病程记录【 时间超过24小时 】'];
                        break;
                    }
                }
                if ($flag === true) {
                    $fenzi[] = ['ZYH' => $med, 'numerator_exzlhxzl' => 1, 'exzlhxzl_error' => '医嘱有【' . $hlyw_name . '】病程有【' . $hlyw_name . '】'];
                }
            }

            $allData = array_unique($allData);
            if ($allData) {
                foreach ($allData as $d) {
                    PatientInfoTarget::query()->updateOrInsert(['ZYH' => $d], ['denominator_kjyw' => 1]);
                }
            }
            if ($fenzi) {
                foreach ($fenzi as $d) {
                    PatientInfoTarget::query()->updateOrInsert(
                        ['ZYH' => $d['ZYH']],
                        [
                            'numerator_exzlhxzl' => $d['numerator_exzlhxzl'],
                            'exzlhxzl_error' => $d['exzlhxzl_error']
                        ]
                    );
                }
            }
        }
        var_dump('exzlhxzlData_end');
    }

    /**
     * 恶性肿瘤放射治疗记录符合率
     */
    public function exzlfszlData(array $where = [])
    {
        $page = 0;
        $pageSize = 100;
        while (1) {
            $page++;
            $pageStart = ($page - 1) * $pageSize;
            // 所有符合条件的病例信息
            $data = $bgdObj = PatientInfo::query()
                ->Join('yzb', 'patient_info.MED_REC_ID', '=', 'yzb.ZYH')
                ->where('yzb.is_fangliao', '=', 1)
                ->offset($pageStart)
                ->limit($pageSize)
                ->get(['MED_REC_ID', 'ZYH', 'YZMC', 'KZSJ'])->toArray();
            if (!$data) {
                break;
            }

            $allData = $fenzi = [];
            foreach ($data as $y) {
                $yzmc = $y['YZMC'] ?: '';
                $yzmc = str_replace(' ', '', $yzmc);
                if (empty($yzmc)) {
                    continue;
                }
                preg_match_all("/放疗(\d+)次/", $yzmc, $res);
                if (!$res[1]) {
                    continue;
                }
                // 如果医嘱中有放疗*次，则记录下来，负责分母的要求
                $allData[] = ['ZYH' => $y['ZYH'], 'exzlfszl_error' => '医嘱【放疗*次】'];

                // 病程记录
                $bingcheng = EMR_BL_BL01::query()
                    ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                    ->where('EMR_BL_BL01.JZHM', '=', $y['ZYH'])
                    ->where('EMR_BL_BL01.BLLB', '=', 294)
                    ->where('EMR_BL_BLXG.is_fangliao', '=', 1)
                    ->get()->toArray();
                if ($bingcheng) {
                    $fenzi[] = ['ZYH' => $y['ZYH'], 'numerator_exzlfszl' => 1, 'exzlfszl_error' => '医嘱【放疗*次】病程记录【有放疗关键字】'];
                } else {
                    $fenzi[] = ['ZYH' => $y['ZYH'], 'numerator_exzlfszl' => 0, 'exzlfszl_error' => '医嘱【放疗*次】病程记录【无放疗关键字】'];
                }
            }

            if ($allData) {
                foreach ($allData as $d) {
                    PatientInfoTarget::query()->updateOrInsert(['ZYH' => $d['ZYH']], ['denominator_exzlfszl' => 1, 'exzlfszl_error' => $d['exzlfszl_error']]);
                }
            }
            if ($fenzi) {
                foreach ($fenzi as $d) {
                    PatientInfoTarget::query()->updateOrInsert(['ZYH' => $d['ZYH']], ['numerator_exzlfszl' => $d['numerator_exzlfszl'], 'exzlfszl_error' => $d['exzlfszl_error']]);
                }
            }
            var_dump($fenzi);
        }
        var_dump('exzlfszlData_end');
    }

    /**
     * 恶性肿瘤化学治疗记录符合率
     */
    public function exzlhxzl(array $where = [])
    {
        $year = $where['year'] ?? date('Y');
        $resArr = [];
        for ($i = 1; $i <= 12; $i++) {
            $startTime = strtotime($year . '-' . $i . '-01');
            $t = date('t', $startTime);
            $endTime = $startTime + $t * 3600 * 24;
            // 所有符合条件的病例信息
            $denominator = $bgdObj = PatientInfo::query()
                ->join('patient_info_target as target', 'target.ZYH', '=', 'patient_info.MED_REC_ID')
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->where('target.denominator_exzlhxzl', '=', 1)->count();

            if (!$denominator) {
                $resArr[] = ['numerator' => 0, 'denominator' => 0, 'res' => 0, 'time' => $year . '-' . sprintf('%02s', $i), 'source' => '系统提取'];
                continue;
            }
            // 所有符合条件的病例信息
            $numerator = $bgdObj = PatientInfo::query()
                ->join('patient_info_target as target', 'target.ZYH', '=', 'patient_info.MED_REC_ID')
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->where('target.numerator_exzlhxzl', '=', 1)->count();

            $res = bcdiv((string)$numerator, (string)$denominator, 4);
            $resArr[] = ['numerator' => $numerator, 'denominator' => $denominator, 'res' => $res, 'time' => $year . '-' . sprintf('%02s', $i), 'source' => '系统提取'];

        }
        return $resArr;
    }


    /**
     * 恶性肿瘤放射治疗记录符合率
     */
    public function exzlfszl(array $where = [])
    {
        $year = $where['year'] ?? date('Y');
        $resArr = [];
        for ($i = 1; $i <= 12; $i++) {
            $startTime = strtotime($year . '-' . $i . '-01');
            $t = date('t', $startTime);
            $endTime = $startTime + $t * 3600 * 24;
            // 所有符合条件的病例信息
            $denominator = $bgdObj = PatientInfo::query()
                ->join('patient_info_target as target', 'target.ZYH', '=', 'patient_info.MED_REC_ID')
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->where('target.denominator_exzlfszl', '=', 1)->count();

            if (!$denominator) {
                $resArr[] = ['numerator' => 0, 'denominator' => 0, 'res' => 0, 'time' => $year . '-' . sprintf('%02s', $i), 'source' => '系统提取'];
                continue;
            }
            // 所有符合条件的病例信息
            $numerator = $bgdObj = PatientInfo::query()
                ->join('patient_info_target as target', 'target.ZYH', '=', 'patient_info.MED_REC_ID')
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->where('target.numerator_exzlfszl', '=', 1)->count();

            $res = bcdiv((string)$numerator, (string)$denominator, 4);
            $resArr[] = ['numerator' => $numerator, 'denominator' => $denominator, 'res' => $res, 'time' => $year . '-' . sprintf('%02s', $i), 'source' => '系统提取'];

        }
        return $resArr;
    }


    /**
     * 清洗医嘱本中医嘱名称是否包含抗菌药物或者化疗药物
     */
    public static function filterField()
    {
        $medicianlHlyw = MedicinalInfo::query()->where(['type' => 2])->pluck('name')->toArray(); // 化疗药物
        $medicianlKjyw = MedicinalInfo::query()->where(['type' => 1])->pluck('name')->toArray(); // 抗菌药物
        foreach ($medicianlHlyw as $h){
            Yzb::query()
                ->where('YZMC', 'like', '%' . $h . '%')
                ->update(['is_has_kjyw'=>1,'kjyw_name'=>$h]);
            var_dump($h);
        }
        foreach ($medicianlKjyw as $h){
            Yzb::query()
                ->where('YZMC', 'like', '%' . $h . '%')
                ->update(['is_has_hlyw'=>1,'hlyw_name'=>$h]);
            var_dump($h);
        }
        echo 'end';
    }

    /**
     * 清洗费用明细中的病理费用
     */
    public static function feeClean()
    {
        echo 'end';
    }

    /**
     * 清洗医嘱本中有手术数据信息的病例
     */
    public static function operationClean()
    {
        // 清洗医嘱本中有手术数据信息的病例
        $sql = 'SELECT id FROM `yzb` where is_operation=0 and YZMC like "拟%" and  YZMC like "%年%" and YZMC like "%月%" and  YZMC like "%日%" and ZYH not in (select ZYH from yzb where YZMC like "%取消手术%" and  YZMC like "%手术取消%")';
        $res = DB::select($sql);
        if ($res) {
            $YzbIds = array_column($res, 'id');
            DB::table('yzb')->whereIn('id', $YzbIds)->update(['is_operation' => 1]);
        }
    }

    /**
     * @param array $where
     * @return array
     * @throws Exception
     * 指标详细数据列表
     */
    public function getZbList($where = [])
    {
        $time = $where['time'];
        $id = $where['id'];
        $isError = $where['is_error'];
        $startTime = strtotime($time . '-01');
        $t = date('t', $startTime);
        $endTime = $startTime + $t * 3600 * 24;

        $dateType = [
            31 => ['target.numerator_ct', 'target.denominator_ct', 'ct_error'],
            32 => ['target.numerator_bl', 'target.denominator_bl', 'bl_error'],
            33 => ['target.numerator_xjpy', 'target.denominator_xjpy', 'xjpy_error'],
            41 => ['target.numerator_kjyw', 'target.denominator_kjyw', 'kjyw_error'],
            42 => ['target.numerator_exzlhxzl', 'target.denominator_exzlhxzl', 'exzlhxzl_error'],
            43 => ['target.numerator_exzlfszl', 'target.denominator_exzlfszl', 'exzlfszl_error'],
            44 => ['target.numerator_operation', 'target.denominator_operation', 'operation_error'],
            45 => ['target.numerator_zrw', 'target.denominator_zrw', 'zrw_name'],
            57 => ['target.numerator_bhlbl', 'target.denominator_bhlbl', 'bhlbl_content'],
            46 => ['target.numerator_lcyx', 'target.denominator_lcyx', 'lcyx_error'],
        ];
        $dateTypeMap = [
            31 => ['target.numerator_ct as numerator', 'target.denominator_ct as denominator'],
            32 => ['target.numerator_bl as numerator', 'target.denominator_bl as denominator'],
            33 => ['target.numerator_xjpy as numerator', 'target.denominator_xjpy as denominator'],
            41 => ['target.numerator_kjyw as numerator', 'target.denominator_kjyw as denominator'],
            42 => ['target.numerator_exzlhxzl as numerator', 'target.denominator_exzlhxzl as denominator'],
            43 => ['target.numerator_exzlfszl as numerator', 'target.denominator_exzlfszl as denominator'],
            44 => ['target.numerator_operation as numerator', 'target.denominator_operation as denominator'],
            45 => ['target.numerator_zrw as numerator', 'target.denominator_zrw as denominator'],
            46 => ['target.numerator_lcyx as numerator', 'target.denominator_lcyx as denominator'],
            57 => ['target.numerator_bhlbl as numerator', 'target.denominator_bhlbl as denominator'],
        ];

        if (empty($dateType[$id])) {
            throw new Exception('参数有误');
        }

        // 所有符合条件的病例信息
        $obj = PatientInfo::query()
            ->join('patient_info_target as target', 'target.ZYH', '=', 'patient_info.MED_REC_ID')
            ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
            ->where($dateType[$id][$where['data_type']], '=', 1);
        if ($isError != 200) {
            // 57指标正确性正好和其他相反
            if ($id == 57) {
                $isError = $isError == 1 ? 0 : 1;
            }
            $obj = $obj->where($dateType[$id][0], '=', $isError);
        }
        $objCopy = clone $obj;
        $count = $obj->count();
        if (!$count) {
            return ['count' => 0, 'data' => []];
        }

        $pageSize = $where['page_size'];
        $page = $where['page'];
        $pageStart = ($page - 1) * $pageSize;

        $column = [
            'patient_info.AAA28',
            'patient_info.MED_REC_ID',
            'patient_info.AAA01',
            'patient_info.AAA03',
            'patient_info.AAA02C',
            'patient_info.AAC01',
            'patient_info.ADA01',
            'patient_info.AAA29',
            'patient_info.AAC11N',
            'target.*',
        ];
        $column = array_merge($column, $dateTypeMap[$id]);
        $data = $obj->select($column)->offset($pageStart)->LIMIT($pageSize)->get()->toArray();
        $recIds = array_column($data, 'MED_REC_ID');

        $hospitalInfo = PatientHospitalInfo::query()->whereIn('AAA28', $recIds)->get(['AAB01', 'AAA28'])->toArray();
        $hospitalInfo = array_column($hospitalInfo, null, 'AAA28');

        foreach ($data as &$d) {
            $d['description'] = !empty($dateType[$id][2]) ? $d[$dateType[$id][2]] : '';
            $d['AAB01'] = !empty($hospitalInfo[$d['MED_REC_ID']]) ? $hospitalInfo[$d['MED_REC_ID']]['AAB01'] : '';
            if ($id == 57) { // 57指标正确性正好和其他相反
                $d['numerator'] = $d['numerator'] == 1 ? 0 : 1;
            }

            // 错误描述放入了 $dateType数组的配置中
        }
        return ['count' => $count, 'data' => $data];
    }


    /**
     * 不合理的数据复制
     */
    public function buheliCopy()
    {

        $page = 0;
        $pageSize = 100;
        while (1) {
            $page++;
            $pageStart = ($page - 1) * $pageSize;
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->offset($pageStart)
                ->limit($pageSize)
                ->get(['MED_REC_ID'])->toArray();

            if (!$data) {
                break;
            }

            $nos = array_column($data, 'MED_REC_ID');
            // 获取病例对应的病程信息
            $bingcheng = EMR_BL_BL01::query()
                ->whereIn('JZHM', $nos)
                ->where('BLLB', '=', '294')
                ->get(['JZHM', 'bcts', 'bc_content', 'MBLB', 'BLMC'])
                ->toArray();
            if (empty($bingcheng)) {
                continue;
            }

            $newBingcheng = [];
            foreach ($bingcheng as $val) {
                if (!$val['bcts'] && !$val['bc_content']) {
                    continue;
                }
                if (strpos($val['BLMC'], '首次病程') !== false) {
                    $newBingcheng[$val['JZHM']]['tese'] = $val;
                }
                $newBingcheng[$val['JZHM']]['other'][] = $val;
            }
            // 获取病例对应的入院信息
            $xbs = EMR_BL_BL01::query()
                ->join('EMR_BL_BLXG as xg', 'EMR_BL_BL01.BLBH', '=', 'xg.BLBH')
                ->whereIn('EMR_BL_BL01.JZHM', $nos)
                ->where('EMR_BL_BL01.BLLB', '=', '292')
                ->get(['EMR_BL_BL01.JZHM', 'xg.HJNR'])
                ->toArray();
            $xbs = array_column($xbs, null, 'JZHM');

            $fenzi = [];
            foreach ($data as $y) {
                // 1、 整个病程中，每次记录的病程不能相同
                $other = !empty($newBingcheng[$y['MED_REC_ID']]['other']) ? $newBingcheng[$y['MED_REC_ID']]['other'] : [];
                $isIdentical = false;
                $resBc = '';
                foreach ($other as $key => $val) {
                    $bcContent = $val['bc_content'] ?: '';
                    if (empty($bcContent)) {
                        continue;
                    }

                    $chuckStr = [];
                    $strLength = mb_strlen($bcContent);
                    $checkLength = ceil($strLength * 0.75);
                    for ($i = 0; $i < $strLength; $i++) {
                        $resStr = mb_substr($bcContent, $i, $checkLength);
                        if (mb_strlen($resStr) < $checkLength) {
                            break;
                        }
                        if ($resStr) {
                            $chuckStr[] = $resStr;
                        }
                    }


                    $isIdenticalOther = false;
                    foreach ($other as $key1 => $val1) {
                        // 比较过的和数据本身不比较
                        if ($key >= $key1 || empty($val1['bc_content'])) {
                            continue;
                        }

                        foreach ($chuckStr as $item) {
                            if (strpos($val1['bc_content'], $item) !== false) {
                                $fenzi[] = ['MED_REC_ID' => $y['MED_REC_ID'], 'bhlbl_content' => ' 病程记录 【' . $val['BLMC'] . '】和病程【' . $val1['BLMC'] . '】 75%雷同'];
                                $isIdenticalOther = true;
                                $isIdentical = true;
                                break;
                            }
                        }
                    }
                    if ($isIdenticalOther === true) {
                        break;
                    }
                }

                // 如果病程之间没有雷同，则校验病例特色和现病史之间的雷同
                if ($isIdentical === false) {

                    if (empty($xbs[$y['MED_REC_ID']])) {
                        continue;
                    }

                    if (empty($newBingcheng[$y['MED_REC_ID']]['tese'])) {
                        continue;
                    }
                    $tese = $newBingcheng[$y['MED_REC_ID']]['tese'] ? $newBingcheng[$y['MED_REC_ID']]['tese']['bcts'] : '';
                    if (!$tese) {
                        continue;
                    }
                    // 病程特色内容
                    $chuckStr = [];
                    $strLength = mb_strlen($tese);
                    $checkLength = ceil($strLength * 0.75);
                    for ($i = 0; $i < $strLength; $i++) {
                        $resStr = mb_substr($tese, $i, $checkLength);
                        if (mb_strlen($resStr) < $checkLength) {
                            break;
                        }
                        if ($resStr) {
                            $chuckStr[] = $resStr;
                        }
                    }

                    // 入院记录
                    $ryjl = $xbs[$y['MED_REC_ID']]['HJNR'];
                    foreach ($chuckStr as $item) {
                        if (strpos($ryjl, $item) !== false) {
                            $fenzi[] = ['MED_REC_ID' => $y['MED_REC_ID'], 'bhlbl_content' => '病例特点【75%内容】和入院记录雷同'];
                            break;
                        }
                    }
                }
            }


            $allData = array_unique($nos);
            if ($allData) {
                foreach ($allData as $d) {
                    PatientInfoTarget::query()->updateOrInsert(['ZYH' => $d], ['denominator_bhlbl' => 1]);
                }
            }
            if ($fenzi) {
                foreach ($fenzi as $d) {
                    PatientInfoTarget::query()->updateOrInsert(['ZYH' => $d['MED_REC_ID']], ['numerator_bhlbl' => 1, 'bhlbl_content' => $d['bhlbl_content']]);
                }
            }
        }
        var_dump('numerator_bhlbl_end');
    }

    /**
     * 手术相关记录完整率
     */
    public function operationComplete()
    {
        $page = 0;
        $pageSize = 100;
        while (1) {
            $page++;
            $offset = ($page - 1) * $pageSize;
            // 所有符合条件的病例信息
            $data = EMR_BL_BL01::query()
                ->where('blmc', '=', '手术安全核查表')
                ->offset($offset)
                ->limit($pageSize)
                ->get(['JZHM'])->toArray();
            if (!$data) {
                break;
            }
            $allData = array_column($data, 'JZHM');
            $fenzi = [];
            foreach ($data as $y) {
                $operation_error = '';
                $isError = 1;
                // 11111111
                $bingcheng = EMR_BL_BL01::query()
                    ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                    ->where('EMR_BL_BL01.JZHM', '=', $y['JZHM'])
                    ->where('EMR_BL_BL01.BLLB', '=', 294)
                    ->where('EMR_BL_BLXG.is_operation', '=', 1)
                    ->limit(1)
                    ->get(['CJSJ'])->toArray();
                if (!$bingcheng) {
                    $operation_error .= '病程记录【术前小结及术前讨论结论记录（无） 】';
                    $isError = 0;
                } else {
                    $operation_error .= '病程记录【术前小结及术前讨论结论记录（有） 】';
                }

                // 2、开嘱时间在创建时间之后的
                if(!empty($bingcheng[0])){
                    $bingcheng = Yzb::query()
                        ->where('ZYH', '=', $y['JZHM'])
                        ->where('is_operation', '=', 1)
                        ->where(Db::raw('UNIX_TIMESTAMP(KZSJ)'), '>', strtotime($bingcheng[0]['CJSJ']))
                        ->limit(1)
                        ->get(['KZSJ'])->toArray();
                    if (!$bingcheng) {
                        $operation_error .= '医嘱【 拟*年*月*日（无） 】';
                        $isError = 0;
                    } else {
                        $operation_error .= '医嘱【 拟*年*月*日（有） 】';
                    }
                }

                // 3、时间在开嘱时间之后
                if (!empty($bingcheng[0])) {

                    $kzsj = $bingcheng[0]['KZSJ'];
                    $kzsj = strtotime($kzsj);
//                $bingcheng = EMR_BL_BL01::query()
////                    ->where('JZHM', '=', $y['JZHM'])
////                    ->where('blmc', 'like', '%手术知情同意书%')
////                    ->where(Db::raw('UNIX_TIMESTAMP(CJSJ)'), '>', $kzsj)
////                    ->count();
////                if (!$bingcheng) {
////                    $operation_error .= '手术【手术知情同意书（无）】';
////                    $isError = 0;
////                }else{
////                    $operation_error .= '手术【手术知情同意书（有）】';
////                }
                    $operation_error .= '手术【手术安全核查表（有）】';

                    $bingcheng = EMR_BL_BL01::query()
                        ->where('JZHM', '=', $y['JZHM'])
                        ->where('blmc', 'like', '%手术记录%')
                        ->where(Db::raw('UNIX_TIMESTAMP(CJSJ)'), '>', $kzsj)
                        ->limit(1)
                        ->get(['operation_time'])->toArray();
                    if (!$bingcheng) {
                        $operation_error .= '手术【手术记录（无）】';
                        $isError = 0;
                    } else {
                        $operation_error .= '手术【手术记录（有）】';
                    }
                }

                // 4、手术时间术后3天有病程
                if (!empty($bingcheng[0])) {
                    $yzb = Yzb::query()->where('ZYH', '=', $y['JZHM'])->where('YZMC', 'like', '%出院%')->get(['KZSJ'])->toArray();
                    $cysj = $yzb && $yzb[0] ? strtotime($yzb[0]['KZSJ']) : 0;

                    $operationTime = $bingcheng[0]['operation_time'];
                    if($cysj && $cysj < $operationTime + 24 * 3600){
                        $bingcheng1 = 1;
                    }else{
                        $bingcheng1 = EMR_BL_BL01::query()
                            ->where('JZHM', '=', $y['JZHM'])
                            ->where('BLLB', '=', 294)
                            ->whereBetween(Db::raw('UNIX_TIMESTAMP(ZXSJ)'), [$operationTime + 24 * 3600, $operationTime + 24 * 3600 * 2])
                            ->count();
                    }
                    if($cysj && $cysj < $operationTime + 24 * 3600 * 2){
                        $bingcheng2 = 1;
                    }else{
                        $bingcheng2 = EMR_BL_BL01::query()
                            ->where('JZHM', '=', $y['JZHM'])
                            ->where('BLLB', '=', 294)
                            ->whereBetween(Db::raw('UNIX_TIMESTAMP(ZXSJ)'), [$operationTime + 24 * 3600 * 2, $operationTime + 24 * 3600 * 3])
                            ->count();
                    }
                    if($cysj && $cysj < $operationTime + 24 * 3600 * 3){
                        $bingcheng3 = 1;
                    }else{
                        $bingcheng3 = EMR_BL_BL01::query()
                            ->where('JZHM', '=', $y['JZHM'])
                            ->where('BLLB', '=', 294)
                            ->whereBetween(Db::raw('UNIX_TIMESTAMP(ZXSJ)'), [$operationTime + 24 * 3600 * 3, $operationTime + 24 * 3600 * 4])
                            ->count();
                    }

                    if (!$bingcheng1 || !$bingcheng2 || !$bingcheng3) {
                        $operation_error .= '手术【术后病程记录（无）】';
                        $isError = 0;
                    } else {
                        $operation_error .= '手术【术后病程记录（有）】';
                    }
                }

                $fenzi[] = ['ZYH' => $y['JZHM'], 'numerator_operation' => $isError, 'operation_error' => $operation_error]; // 分子+1
            }

            $allData = array_unique($allData);
            if ($allData) {
                foreach ($allData as $d) {
                    PatientInfoTarget::query()->updateOrInsert(['ZYH' => $d], ['denominator_operation' => 1]);
                }
            }
            if ($fenzi) {
                foreach ($fenzi as $d) {
                    PatientInfoTarget::query()->updateOrInsert(['ZYH' => $d['ZYH']], ['numerator_operation' => $d['numerator_operation'], 'operation_error' => $d['operation_error']]);
                }
            }
        }
        var_dump('手术相关记录完整率_end');
    }


    /**
     * 恶性肿瘤放射治疗记录符合率
     */
    public function bfhfsl(array $where = [])
    {
        $year = $where['year'] ?? date('Y');
        $resArr = [];
        for ($i = 1; $i <= 12; $i++) {
            $startTime = strtotime($year . '-' . $i . '-01');
            $t = date('t', $startTime);
            $endTime = $startTime + $t * 3600 * 24;
            // 所有符合条件的病例信息
            $denominator = PatientInfo::query()
                ->join('patient_info_target as target', 'target.ZYH', '=', 'patient_info.MED_REC_ID')
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->where('target.denominator_bhlbl', '=', 1)->count();

            if (!$denominator) {
                $resArr[] = ['numerator' => 0, 'denominator' => 0, 'res' => 0, 'time' => $year . '-' . sprintf('%02s', $i), 'source' => '系统提取'];
                continue;
            }
            // 所有符合条件的病例信息
            $numerator = $bgdObj = PatientInfo::query()
                ->join('patient_info_target as target', 'target.ZYH', '=', 'patient_info.MED_REC_ID')
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->where('target.numerator_bhlbl', '=', 1)->count();

            $res = bcdiv((string)$numerator, (string)$denominator, 4);
            $resArr[] = ['numerator' => $numerator, 'denominator' => $denominator, 'res' => $res, 'time' => $year . '-' . sprintf('%02s', $i), 'source' => '系统提取'];

        }
        return $resArr;
    }

    /**
     * 手术相关记录完整率
     */
    public function ssxgjl(array $where = [])
    {
        $year = $where['year'] ?? date('Y');
        $resArr = [];
        for ($i = 1; $i <= 12; $i++) {
            $startTime = strtotime($year . '-' . $i . '-01');
            $t = date('t', $startTime);
            $endTime = $startTime + $t * 3600 * 24;
            // 所有符合条件的病例信息
            $denominator = PatientInfo::query()
                ->join('patient_info_target as target', 'target.ZYH', '=', 'patient_info.MED_REC_ID')
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->where('target.denominator_operation', '=', 1)->count();

            if (!$denominator) {
                $resArr[] = ['numerator' => 0, 'denominator' => 0, 'res' => 0, 'time' => $year . '-' . sprintf('%02s', $i), 'source' => '系统提取'];
                continue;
            }
            // 所有符合条件的病例信息
            $numerator = PatientInfo::query()
                ->join('patient_info_target as target', 'target.ZYH', '=', 'patient_info.MED_REC_ID')
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->where('target.numerator_operation', '=', 1)->count();

            $res = bcdiv((string)$numerator, (string)$denominator, 4);
            $resArr[] = ['numerator' => $numerator, 'denominator' => $denominator, 'res' => $res, 'time' => $year . '-' . sprintf('%02s', $i), 'source' => '系统提取'];

        }
        return $resArr;
    }

    /**
     * wcy
     * CT/MRI检查记录符合率接口
     * @param $where
     * @return array
     */
    protected function getIrcrData(array $where = [])
    {
        $year = $where['year'] ?? Carbon::now()->year;
        $data = [];

        for ($month = 1; $month <= 12; $month++) {
            $startTime = strtotime($year . '-' . $month . '-01');
            $t = date('t', $startTime);
            $endTime = $startTime + $t * 3600 * 24;

            // 分母
            $denominatorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_ct', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');

            // 分子
            $numeratorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_ct', '=', 1)
                ->where('patient_info_target.numerator_ct', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');

            // 计算符合率
            $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
            $data[] = [
                'title' => 'CT/MRI检查记录符合率',
                'time' => $year . '-' . sprintf('%02s', $month),
                'denominator' => $denominatorCount,
                'numerator' => $numeratorCount,
                'res' => $complianceRate,
                'source' => '系统提取',
            ];
        }

        // 分母
        $denominatorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_ct', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');
        // 分子
        $numeratorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_ct', '=', 1)
            ->where('patient_info_target.numerator_ct', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');

        // 计算符合率
        $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
        $data[] = [
            'title' => 'CT/MRI检查记录符合率',
            'time' => '全年',
            'denominator' => $denominatorCount,
            'numerator' => $numeratorCount,
            'res' => $complianceRate,
            'source' => '系统提取',
        ];

        return $data;
    }

    /**
     * wcy
     * 植入物相关记录符合率
     * @param $where
     * @return array
     */
    protected function getZrwData(array $where = [])
    {
        $year = $where['year'] ?? Carbon::now()->year;
        $data = [];

        for ($month = 1; $month <= 12; $month++) {
            $startTime = strtotime($year . '-' . $month . '-01');
            $t = date('t', $startTime);
            $endTime = $startTime + $t * 3600 * 24;

            // 分母
            $denominatorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_zrw', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');;
            // 分子
            $numeratorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_zrw', '=', 1)
                ->where('patient_info_target.numerator_zrw', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');

            // 计算符合率
            $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
            $data[] = [
                'title' => '植入物相关记录符合率',
                'time' => $year . '-' . sprintf('%02s', $month),
                'denominator' => $denominatorCount,
                'numerator' => $numeratorCount,
                'res' => $complianceRate,
                'source' => '系统提取',
            ];
        }

        // 分母
        $denominatorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_zrw', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');
        // 分子
        $numeratorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_zrw', '=', 1)
            ->where('patient_info_target.numerator_zrw', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');
        // 计算符合率
        $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
        $data[] = [
            'title' => '植入物相关记录符合率',
            'time' => '全年',
            'denominator' => $denominatorCount,
            'numerator' => $numeratorCount,
            'res' => $complianceRate,
            'source' => '系统提取',
        ];

        return $data;
    }

    /**
     * wcy
     * 细菌培养相关记录符合率
     * @param $where
     * @return array
     */
    protected function getXjpyData(array $where = [])
    {
        $year = $where['year'] ?? Carbon::now()->year;
        $data = [];

        for ($month = 1; $month <= 12; $month++) {
            $startTime = strtotime($year . '-' . $month . '-01');
            $t = date('t', $startTime);
            $endTime = $startTime + $t * 3600 * 24;

            // 分母
            $denominatorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_xjpy', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');;
            // 分子
            $numeratorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_xjpy', '=', 1)
                ->where('patient_info_target.numerator_xjpy', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');

            // 计算符合率
            $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
            $data[] = [
                'title' => '细菌培养相关记录符合率',
                'time' => $year . '-' . sprintf('%02s', $month),
                'denominator' => $denominatorCount,
                'numerator' => $numeratorCount,
                'res' => $complianceRate,
                'source' => '系统提取',
            ];
        }

        // 分母
        $denominatorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_xjpy', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');
        // 分子
        $numeratorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_xjpy', '=', 1)
            ->where('patient_info_target.numerator_xjpy', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');
        // 计算符合率
        $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
        $data[] = [
            'title' => '细菌培养相关记录符合率',
            'time' => '全年',
            'denominator' => $denominatorCount,
            'numerator' => $numeratorCount,
            'res' => $complianceRate,
            'source' => '系统提取',
        ];

        return $data;
    }

    /**
     * wcy
     * 临床用血相关记录符合率
     * @param $where
     * @return array
     */
    protected function getLcyxData(array $where = [])
    {
        $year = $where['year'] ?? Carbon::now()->year;
        $data = [];

        for ($month = 1; $month <= 12; $month++) {
            $startTime = strtotime($year . '-' . $month . '-01');
            $t = date('t', $startTime);
            $endTime = $startTime + $t * 3600 * 24;

            // 分母
            $denominatorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_lcyx', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');
            // 分子
            $numeratorCount = PatientInfo::query()
                ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
                ->where('patient_info_target.denominator_lcyx', '=', 1)
                ->where('patient_info_target.numerator_lcyx', '=', 1)
                ->whereBetween(Db::raw('UNIX_TIMESTAMP(patient_info.AAC01)'), [$startTime, $endTime])
                ->count('patient_info.id');
            // 计算符合率
            $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
            $data[] = [
                'title' => '临床用血相关记录符合率',
                'time' => $year . '-' . sprintf('%02s', $month),
                'denominator' => $denominatorCount,
                'numerator' => $numeratorCount,
                'res' => $complianceRate,
                'source' => '系统提取',
            ];
        }

        // 分母
        $denominatorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_lcyx', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');
        // 分子
        $numeratorCount = PatientInfo::query()
            ->leftJoin('patient_info_target', 'patient_info.MED_REC_ID', '=', 'patient_info_target.ZYH')
            ->where('patient_info_target.denominator_lcyx', '=', 1)
            ->where('patient_info_target.numerator_lcyx', '=', 1)
            ->where('AAC01', 'like', $year . '%')
            ->count('patient_info.id');
        // 计算符合率
        $complianceRate = $denominatorCount > 0 ? bcdiv((string)$numeratorCount, (string)$denominatorCount, 4) : 0;
        $data[] = [
            'title' => '临床用血相关记录符合率',
            'time' => '全年',
            'denominator' => $denominatorCount,
            'numerator' => $numeratorCount,
            'res' => $complianceRate,
            'source' => '系统提取',
        ];

        return $data;
    }


}
