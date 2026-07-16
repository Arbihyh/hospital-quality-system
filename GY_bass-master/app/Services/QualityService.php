<?php


namespace App\Services;

use App\Model\CityLib;
use App\Model\Icu;
use App\Model\MainOperation;
use App\Model\OtherDiagnosis;
use App\Model\PatientCostInfo;
use App\Model\PatientInfo;
use App\Model\SecondaryOperation;
use App\Model\Staff;
use App\Model\SurgeryClassMapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QualityService
{
    public static $bllb_array = [1, 292, 294, 303, 329, 43, 79, 288, 18, 34, 87];

    public static $his_mzfs1 = [
        '1' => ['name' => '全身麻醉', 'value' => '1'],
        '3' => ['name' => '蛛网膜下腔阻滞', 'value' => '20101'],
        '4' => ['name' => '臂丛阻滞及上肢神经阻滞', 'value' => '20202'],
        '5' => ['name' => '颈从阻滞', 'value' => '20201'],
        '6' => ['name' => '表面麻醉', 'value' => '301'],
        '7' => ['name' => '局部浸润麻醉', 'value' => '302'],
        '10' => ['name' => '其他', 'value' => '10'],
        '11' => ['name' => '静脉局部麻醉', 'value' => '304'],
        '12' => ['name' => '局部麻醉', 'value' => '3'],
        '13' => ['name' => '其他', 'value' => '99'],
    ];

    //需要转换的字段
    public static $listCode = ['AEM01C', 'AAB06C', 'AAA26C', 'AAA02C'];
    public static $list = [
        "patient_info.AAA28",
        "AAA01",
        "AAA02C",
        "AAA04",
        "AAA29",
        "AAC11N",
        "AAC01",
        "ADA01",
        "F_D",
        "J",
        "ABC01C",
        "ABC01N",
        "pm.AEL01",
        "so.ICD9_NAME",
        "so.ICD9_ID1",
        "mo.RJSS",
        "pm.ABA01C",
        "pm.AEJ01",
        "pm.AEJ04",
        "pm.ABA01C",
        "pm.ABA01N",
        // "ABC03C",
        "of.RYQK as ABC03C",
        "od.ICD10_ID1",
        "od.ICD10_NAME",

        //        "ICD10_ID1",
        //        "ICD10_NAME",
        //        "ICD9_NAME",
        "mo.OPE_LEVEL",
        "icu.IS_MAIN_WAY",

        //        "patient_info.ICD9_NAME",
        "AAC04",
        "ATTEND_GRP_NAME",
        "AAC01",
        "AAB06C",
        "AEM01C",
        'AAA26C',
        'AAA30',
        'MED_REC_ID',
        'AAB11N', // 入院科室
        'AAB01', // 入院时间
        'patient_add.SSPB',
        'od.ICD10_NAME'
    ];
    public static $list2 = [
        "patient_info.AAA28",
        "patient_info.AAA01",
        "patient_info.AAA02C",
        "patient_info.AAA04",
        "patient_info.AAA29",
        "patient_info.AAC11N",
        "patient_info.AAC01",
        "patient_info.ADA01",
        "patient_info.F_D",
        "patient_info.J",
        "patient_info.ABC01N",
        "patient_info.AAB06C",
        "patient_info.AAC04",
        "patient_info.ATTEND_GRP_NAME",
        "patient_info.AEM01C",
        'patient_info.AAA26C',
        'patient_info.MED_REC_ID',
        "phi.ABC01C",
        'phi.AAA30',
        'phi.AAB11N',
        "phi.AAB01",
        "mo.ICD9_NAME",
        "mo.ICD9_ID1",
        "mo.RJSS",
        "mo.OPE_LEVEL",
        "pmi.ABA01C",
        "pmi.AEL01",
        "pmi.ABA01C",
        "pmi.ABA01N",
        "pmi.AEJ01",
        "pmi.AEJ02",
        "pmi.AEJ03",
        "pmi.AEJ04",
        "pmi.AEJ05",
        "pmi.AEJ06",
        "pmi.ABC03C",
        "od.RYQK",
        "od.ICD10_ID1",
        "od.ICD10_NAME",
        'od.ICD10_NAME',
        "icu.IS_MAIN_WAY",
        'pa.SSPB',
    ];
    public static $wt = [
        "patient_info.MED_REC_ID",
        "patient_info.AAA28",
        "patient_info.AAA29",
        "patient_info.AAA26C",
        "patient_info.AAA01",
        "patient_info.AAA02C",
        "patient_info.AAA03",
        "patient_info.AAA04",
        "patient_info.AAA05C",
        "patient_info.AAA40",
        "patient_info.AEN01",
        "patient_info.AAA42",
        "patient_info.AAA06C",
        "patient_info.AAA07",
        "patient_info.AAA08C",
        "patient_info.AAB06C",
        "patient_info.AAC01",
        "patient_info.AAC04",
        "patient_info.ABG01N",
        "patient_info.ABG01C",
        "patient_info.AEM01C",
        "patient_info.ADA01",
        "pwi.AAA18C",
        "pwi.AAA19",
        "pwi.AAA20",
        "pwi.AAA21C",
        "pcni.AAA22",
        "pcni.AAA23C",
        "pcni.AAA24",
        "pcni.AAA25",
        "pci.ADA0101",
        "pci.D11",
        "pci.D12",
        "pci.D13",
        "pci.D14",
        "pci.D15",
        "pci.D16",
        "pci.D17",
        "pci.D18",
        "pci.D19",
        "pci.D19X01",
        "pci.D20",
        "pci.D20X01",
        "pci.D20X02",
        "pci.D21",
        "pci.D22",
        "pci.D23",
        "pci.D23X01",
        "pci.D24",
        "pci.D25",
        "pci.D26",
        "pci.D27",
        "pci.D28",
        "pci.D29",
        "pci.D30",
        "pci.D31",
        "pci.D32",
        "pci.D33",
        "pci.D34",
        "pai.AAA09",
        "pai.AAA10",
        "pai.AAA11",
        "pai.AAA43",
        "pai.AAA44",
        "pai.AAA48",
        "pai.AAA49",
        "pai.AAA50",
        "pai.AAA15",
        "pai.AAA51",
        "pai.AAA17C",
        "pai.AAA45",
        "pai.AAA46",
        "pai.AAA47",
        "pai.AAA12",
        "pai.AAA14C",
        "phi.AEM02",
        "phi.AAB01",
        "phi.AAB02C",
        "phi.AAB03",
        "phi.AAD01C",
        "phi.AAC02C",
        "phi.AAC03",
        "phi.AEI01C",
        "phi.AEM02",
        "phi.AEM03C",
        "phi.AEM04",
        "pmi.ABA01C",
        "pmi.ABA01N",
        "pmi.ABF01N",
        "pmi.ABF01C",
        "pmi.ABF04",
        "pmi.ABF02C",
        "pmi.AEB02C",
        "pmi.AEB01",
        "pmi.AEG01C",
        "pmi.AEG02C",
        "pmi.AEG04",
        "pmi.AEG05",
        "pmi.AEG06",
        "pmi.AEG07",
        "pmi.AEL01",
        "pmi.AED01C",
        "pmi.AEJ01",
        "pmi.AEJ02",
        "pmi.AEJ03",
        "pmi.AEJ04",
        "pmi.AEJ05",
        "pmi.AEJ06",
        "pmi.ABC03C",
        "poi.AAB07",
        "poi.AAB07C",
        "poi.AAB07N",
        "poi.AAB07D",
        "poi.AFA08",
        "pdi.AEE01_CODE",
        "pdi.AEE01",
        "pdi.AEE05",
        "pdi.AEE07",
        "pdi.AEE08",
        "pdi.AEE09",
        "pdi.AEE05",
        "pdi.AED02",
        "pdi.AED04",
        "md.ICD10_ID1",
        "md.ICD10_NAME",
        "md.RYQK",
        'pa.JKKH',
        'pa.SFZJLX',
        'pa.TJHL',
        'pa.YJHL',
        'pa.EJHL',
        'pa.SJHL',
        'pa.BFRY',
        'pa.ZHFZRYS',
        'pa.ZHFZRYSXM',
        'pa.ZZYSBM',
        'pa.ZZYSXM',
        'pa.ZYYSBM',
        'pa.ZYYSXM',
        'pa.ZRHSBM',
        'pa.ZRHS',
        'pa.BMY',
        'pa.ZKHSBM',
        'pa.ZKHS',
        'pa.HB',
        'pa.HCV',
        'pa.HIV',
        'pa.LCLJ',
        'pa.WCQK',
        'pa.BYQK',
        'pa.BFRY',
    ];

    // 病案数量
    public static function getHomeQualityList($where, $betweenWhere, $inWhere, $offset, $limit, $age_start, $age_end, $field, $age_start_type, $age_end_type)
    {
        $query = PatientInfo::query()->where($where);
        if (!empty($betweenWhere)) {
            $query->whereBetween('AAC01', $betweenWhere);
        }
        if (!empty($inWhere)) {
            $query->whereIn('MED_REC_ID', $inWhere);
        }
        $query->leftjoin('patient_hospital_info as phi', 'patient_info.MED_REC_ID', '=', 'phi.AAA28');
        $query->leftjoin('patient_doctor_info as pdi', 'patient_info.MED_REC_ID', '=', 'pdi.AAA28');
        $query->leftjoin('main_operation as mo', 'patient_info.MED_REC_ID', '=', 'mo.AAA28');
        $query->leftjoin('patient_add as patient_add', 'patient_add.AAA28', '=', 'patient_info.MED_REC_ID');
        $query->leftjoin('other_diagnosis as od', 'patient_info.MED_REC_ID', '=', 'od.AAA28');
        $count = $query->count(DB::raw('DISTINCT patient_info.MED_REC_ID'));
        $data = $query
            ->offset($offset)
            ->limit($limit)
            ->orderBy('AAC01', 'desc')
            ->groupBy('patient_info.MED_REC_ID')
            ->get(self::$list);
        if (empty($data)) {
            return [];
        } else {
            return ['list' => self::fee(ToolsService::codeTransformationList(self::$listCode, $data->toArray())), 'count' => $count];
        }
    }

    /**获取病案列表
     * @param $where
     * @param $betweenWhere
     * @param $page
     * @param $limit
     * @return array
     */
    public static function getList($patientInfo, $patientHospitalInfo, $patientDoctorInfo, $mainDiagnosis, $otherDiagnosis, $mainOperation, $secondaryOperation, $patientMedicalInfo, $icu, $patientAdd, $emr_bl_bl01Info, $bllb_info, $isExport, $offset, $limit, $LNSSQ, $LNSSH, $error)
    {
        $query = PatientInfo::query();
        if (!empty($patientInfo)) {
            //判断条件里是否有or 条件，如果有，则进行orwhere 连接查询
            foreach ($patientInfo as $key => $val) {
                if (!empty($val[3])) {
                    $selectType = $val[3];
                    unset($val[3]);
                    if ($selectType == "or") {
                        $query->orwhere([$val]);
                    } else {
                        $query->where([$val]);
                    }
                } else {
                    $query->where([$val]);
                }
            }
        }
        if (!empty($patientHospitalInfo)) {
            $query->leftjoin('patient_hospital_info as phi', 'patient_info.MED_REC_ID', '=', 'phi.AAA28');
            foreach ($patientHospitalInfo as $key => $val) {
                if ($key == "phi.AAC11C") {
                    //$query->where([$key,$val]);
                    if ($val[2] != 'all') {
                        $query->where($val[0], $val[1], $val[2]);
                    }
                    continue;
                }
                if (!empty($val[3])) {
                    if ($val[3] == "or") {
                        unset($val[3]);
                        $query->orwhere([$val]);
                    } else {
                        unset($val[3]);
                        //$query->where([$val]);
                        if ($val[2] != 'all') {
                            $query->where($val[0], $val[1], $val[2]);
                        }
                    }
                } else {
                    $query->where([$val]);
                }
            }
        }
        if (!empty($patientDoctorInfo)) {
            $query->leftjoin('patient_doctor_info as pdi', 'patient_info.MED_REC_ID', '=', 'pdi.AAA28');
            foreach ($patientDoctorInfo as $key => $val) {
                if (!empty($val[3])) {
                    if ($val[3] == "or") {
                        unset($val[3]);
                        $query->orwhere([$val]);
                    } else {
                        unset($val[3]);
                        $query->where([$val]);
                    }
                } else {
                    $query->where([$val]);
                }
            }
            // $query->where($patientDoctorInfo);
        }
        if (!empty($mainDiagnosis)) {
            $query->leftjoin('main_diagnosis as md', 'patient_info.MED_REC_ID', '=', 'md.AAA28');
            foreach ($mainDiagnosis as $key => $val) {
                if (!empty($val[3])) {
                    if ($val[3] == "or") {
                        unset($val[3]);
                        $query->orwhere([$val]);
                    } else {
                        unset($val[3]);
                        $query->where([$val]);
                    }
                } else {
                    $query->where([$val]);
                }
            }
            // $query->where($mainDiagnosis);
        }
        //        if (!empty($mainOperation)){
        //            $query->leftjoin('main_operation as mo', 'patient_info.MED_REC_ID', '=', 'mo.AAA28')
        //                ->where($mainOperation);
        //        }
        if (!empty($secondaryOperation) || !empty($mainOperation)) {
            $mainOperationSql = MainOperation::query()
                ->select('AAA28');
            foreach ($mainOperation as $key => $val) {
                if (!empty($val[3])) {
                    if ($val[3] == "or") {
                        unset($val[3]);
                        $mainOperationSql->orwhere([$val]);
                    } else {
                        unset($val[3]);
                        $mainOperationSql->where([$val]);
                    }
                } else {
                    $mainOperationSql->where([$val]);
                }
            }
            // $mainOperationSql->where($mainOperation);

            $secondaryOperationSql = SecondaryOperation::query()
                ->select('AAA28');
            foreach ($secondaryOperation as $key => $val) {
                if (!empty($val[3])) {
                    if ($val[3] == "or") {
                        unset($val[3]);
                        $secondaryOperationSql->orwhere([$val]);
                    } else {
                        unset($val[3]);
                        $secondaryOperationSql->where([$val]);
                    }
                } else {
                    $where = implode("", $val);
                    $secondaryOperationSql->where([$val]);
                }
            }
            // $secondaryOperationSql->where($secondaryOperation);

            $secondaryOperationSql->union($mainOperationSql);
            $query->joinSub($secondaryOperationSql, 'mo', 'patient_info.MED_REC_ID', '=', 'mo.AAA28', 'left')
                ->whereNotNull('mo.AAA28');
        }
        if (!empty($otherDiagnosis)) {
            $odQuery = OtherDiagnosis::query()
                ->select('AAA28');
            foreach ($otherDiagnosis as $key => $val) {
                if (!empty($val[3])) {
                    if ($val[3] == "or") {
                        unset($val[3]);
                        $odQuery->orwhere([$val]);
                    } else {
                        unset($val[3]);
                        $odQuery->where([$val]);
                    }
                } else {
                    $odQuery->where([$val]);
                }
            }
            // $odQuery->where($otherDiagnosis);
            $query->joinSub($odQuery, 'od', 'patient_info.MED_REC_ID', '=', 'od.AAA28', 'left')
                ->whereNotNull('od.AAA28');
        }
        if (!empty($error)) {
            $query->leftjoin('error as e', 'patient_info.MED_REC_ID', '=', 'e.AAA28')
                ->where($error);
        }
        if (!empty($patientMedicalInfo) || !empty($LNSSQ) || !empty($LNSSH)) {
            $query->leftjoin('patient_medical_info as pmi', 'patient_info.MED_REC_ID', '=', 'pmi.AAA28');
            if (!empty($patientMedicalInfo)) {
                foreach ($patientMedicalInfo as $key => $val) {
                    if (!empty($val[3])) {
                        if ($val[3] == "or") {
                            unset($val[3]);
                            $query->orwhere([$val]);
                        } else {
                            unset($val[3]);
                            $query->where([$val]);
                        }
                    } else {
                        $query->where([$val]);
                    }
                }
                // $query->where($patientMedicalInfo);
            }
            if (!empty($LNSSQ)) {
                $query->whereRaw($LNSSQ);
            }
            if (!empty($LNSSH)) {
                $query->whereRaw($LNSSH);
            }
        }
        if (!empty($icu)) {
            $query->leftjoin('icu as icu', 'patient_info.MED_REC_ID', '=', 'icu.AAA28');
            foreach ($icu as $key => $val) {
                if (!empty($val[3])) {
                    if ($val[3] == "or") {
                        unset($val[3]);
                        $query->orwhere([$val]);
                    } else {
                        unset($val[3]);
                        $query->where([$val]);
                    }
                } else {
                    $query->where([$val]);
                }
            }
            // $query->where($icu);
        }
        if (!empty($patientAdd)) {
            $query->leftjoin('patient_add as pa', 'pa.AAA28', '=', 'patient_info.MED_REC_ID')
                ->where($patientAdd);
        }
        if (!empty($emr_bl_bl01Info)) {
            $query->leftjoin("EMR_BL_BL01 as EMR_BL_BL01", 'EMR_BL_BL01.JZHM', '=', 'patient_info.MED_REC_ID')
                ->where($emr_bl_bl01Info)->where($bllb_info);
        }
        if ($isExport == 1) {
            static::$list = array_merge(static::$list, [DB::raw('case when pm.AEJ01 > 0 then 1 when pm.AEJ02 > 0 then 1 when AEJ03 > 0 then 1 else 0 end as LNSSQ')]);
            static::$list = array_merge(static::$list, [DB::raw('case when pm.AEJ04 > 0 then 1 when pm.AEJ05 > 0 then 1 when AEJ06 > 0 then 1 else 0 end as LNSSH')]);
        }
        $count = $query->count(DB::raw('MED_REC_ID'));
        $polymerization = $query->first([
            DB::raw('AVG(AAC04) as AAC04'),
            DB::raw('AVG(ADA01) as ADA01'),
        ]);
        $data = $query
            ->offset($offset)
            ->limit($limit)
            ->orderBy('MED_REC_ID', 'desc')
            ->pluck('MED_REC_ID');
        if ($data) {
            $data = $data->toArray();
        } else {
            $data = [];
        }
        $AEM01C = $query->where('AEM01C', 5)->count('MED_REC_ID');
        $list = [];
        self::getMedicalRecordList($data, function ($medicalList) use (&$list) {
            $list = self::fee(ToolsService::codeTransformationList(self::$listCode, $medicalList));
        });
        if (empty($list)) {
            return ['list' => [], 'count' => 0, 'ARG_STAY' => 0, 'ARG_F_D' => 0, 'AEM01C' => 0];
        } else {
            return ['list' => $list, 'count' => $count, 'AEM01C' => $AEM01C, 'ARG_STAY' => sprintf('%.2f', $polymerization['AAC04']), 'ARG_F_D' => sprintf('%.2f', $polymerization['ADA01'])];
        }
    }

    public static function getAllData()
    {
        $data = PatientInfo::query()
            ->leftjoin('patient_hospital_info as ph', 'patient_info.MED_REC_ID', '=', 'ph.AAA28')
            ->get(['patient_info.*', 'ph.AAB01'])->toArray();
        return $data;
    }

    public static function getMedicalRecordList($list, callable $callback = null)
    {
        if (empty($list)) {
            return [];
        }
        $medicalInfoList = [];
        $medicalKeyList = [];
        $medicalKeyMap = [];
        foreach ($list as $id) {
            $medicalKey = 'medical_' . $id;
            $medicalKeyList[] = $medicalKey;
            $medicalKeyMap[$medicalKey] = $id;
        }
        $medicalList = [];
        foreach ($medicalKeyList as $item) {
            $medicalList[$item] = Cache::get($item);
        }
        Log::info(print_r($medicalList, true));
        $noCacheMedicalIdList = [];
        foreach ($medicalList as $key => $value) {
            if (is_null($value)) {
                $noCacheMedicalIdList[] = $medicalKeyMap[$key];
            } else {
                $medicalInfoList[] = $value;
            }
        }

        if (!empty($noCacheMedicalIdList)) {
            $medical = self::getmedicalListInAAA28($noCacheMedicalIdList);
            if (!empty($medical)) {
                $putMedicalKey = [];
                foreach ($medical as $item) {
                    if (in_array($item['MED_REC_ID'], $noCacheMedicalIdList)) {
                        $medicalKey = 'medical_' . $item['MED_REC_ID'];
                        $medicalInfoList[] = $item;
                        $putMedicalKey[$medicalKey] = $item;
                    }
                }
                if (!empty($putMedicalKey)) {
                    foreach ($putMedicalKey as $key => $value) {
                        Cache::put($key, $value, 86400);
                    }
                }
            }
        }
        //回调方法
        if ($callback) {
            $callback($medicalInfoList);
        }
        return $medicalInfoList;
    }

    public static function getMedicalListInAAA28($AAA28)
    {
        $data = PatientInfo::query()
            ->leftjoin('patient_hospital_info as phi', 'patient_info.MED_REC_ID', '=', 'phi.AAA28')
            ->leftjoin('patient_medical_info as pmi', 'patient_info.MED_REC_ID', '=', 'pmi.AAA28')
            ->leftjoin('main_operation as mo', 'patient_info.MED_REC_ID', '=', 'mo.AAA28')
            ->leftjoin('other_diagnosis as od', 'patient_info.MED_REC_ID', '=', 'od.AAA28')
            ->leftjoin('icu as icu', 'patient_info.MED_REC_ID', '=', 'icu.AAA28')
            ->leftjoin('patient_add as pa', 'pa.AAA28', '=', 'patient_info.MED_REC_ID')
            ->whereIn('patient_info.MED_REC_ID', $AAA28)
            ->groupBy('patient_info.MED_REC_ID')
            ->get(self::$list2);
        if ($data) {
            return $data->toArray();
        } else {
            return [];
        }
    }

    public static function fee($data)
    {
        $list = array_column($data, 'MED_REC_ID');
        $result = PatientCostInfo::query()
            ->whereIn('AAA28', $list)
            ->get();
        if ($result) {
            $result = $result->toArray();
        } else {
            $result = [];
        }
        $result = array_column($result, null, 'AAA28');
        $config = config('dictionaries');
        foreach ($data as &$item) {
            if (isset($result[$item['MED_REC_ID']])) {
                $item['F_D'] = sprintf('%.2f', $result[$item['MED_REC_ID']]['D23'] + $result[$item['MED_REC_ID']]['D24'] + $result[$item['MED_REC_ID']]['D25']) ?? 0;
                $item['J'] = sprintf('%.2f', $result[$item['MED_REC_ID']]['D31'] + $result[$item['MED_REC_ID']]['D32'] + $result[$item['MED_REC_ID']]['D33']) ?? 0;
            } else {
                $item['F_D'] = 0;
                $item['J'] = 0;
            }
            $lssq = ($item['AEJ01'] > 0 ? $item['AEJ01'] . '天' : '') . ($item['AEJ02'] > 0 ? $item['AEJ02'] . '小时' : '') . ($item['AEJ03'] > 0 ? $item['AEJ03'] . '分' : '');
            $lssh = ($item['AEJ04'] > 0 ? $item['AEJ04'] . '天' : '') . ($item['AEJ05'] > 0 ? $item['AEJ05'] . '小时' : '') . ($item['AEJ06'] > 0 ? $item['AEJ06'] . '分' : '');
            $item['LNSSQ'] = empty($lssq) ? '无' : $lssq;
            $item['LNSSH'] = empty($lssh) ? '无' : $lssh;
            $item['ICD10_ID1_first'] = $item['ICD10_ID1'];
            $item['ICD10_NAME_first'] = $item['ICD10_NAME'];
            $item['SSPB'] = $config['SSPB'][$item['SSPB']] ?? '无';
            $item['ABC03C'] = $config['RYQK'][$item['ABC03C']] ?? '无';
            $item['RYQK'] = $config['RYQK'][$item['RYQK']] ?? '无';
            $item['AEL01'] = is_numeric($item['AEL01']) ? $item['AEL01'] : '无';
        }

        return $data;
    }

    /**获取病案列表
     * new
     */
    public static function getMedicalList($where, $betweenWhere, $inWhere, $offset, $limit, $age_start, $age_end, $field, $age_start_type, $age_end_type, $AAC0401, $AAC0402, $isExport)
    {
        $query = PatientInfo::query()->where($where);
        if (!empty($betweenWhere)) {
            $query->whereBetween('AAC01', $betweenWhere);
        }
        if (!empty($inWhere)) {
            $query->whereIn('MED_REC_ID', $inWhere);
        }

        if ($AAC0401 > 0 && $AAC0402 > 0) {
            $query->where(function ($query) use ($AAC0401, $AAC0402) {
                $query->whereBetween('AAC04', [$AAC0401, $AAC0402]);
                //                    ->andWhere('AAC04','<=',$AAC0402);
            });
        } elseif ($AAC0401 > 0 && $AAC0402 <= 0) {
            $query->where('AAC04', '>=', $AAC0401);
        } elseif ($AAC0401 <= 0 && $AAC0402 > 0) {
            $query->where('AAC04', '<=', $AAC0402);
        }
        // }elseif ($AAA04 > 0 && $AAA40 <= 0){
        //     $query->where('AAC04','>=',$AAA04);
        // }elseif ($AAA04 <=0 && $AAA40 > 0){
        //     $query->where('AAC04','<=',$AAA40);
        // }


        if ($age_start_type == $age_end_type) {
            if ($age_start_type == 1) { //天
                if ($age_start > 0 && $age_end > 0) {
                    $query->where('AAA40', '>=', $age_start)
                        ->Where('AAA40', '<=', $age_end);
                } elseif ($age_start > 0 && $age_end <= 0) {
                    $query->where('AAA40', '>=', $age_start);
                } elseif ($age_start <= 0 && $age_end > 0) {
                    $query->where('AAA40', '<=', $age_end);
                }
            }
            if ($age_start_type == 2) { //年
                if ($age_start > 0 && $age_end > 0) {
                    $query->where('AAA04', '>=', $age_start)
                        ->Where('AAA04', '<=', $age_end);
                } elseif ($age_start > 0 && $age_end <= 0) {
                    $query->where('AAA04', '>=', $age_start);
                } elseif ($age_start <= 0 && $age_end > 0) {
                    $query->where('AAA04', '<=', $age_end);
                }
            }
        } elseif (($age_start_type == 1) && ($age_end_type == 2)) { //多少天-多少年
            if ($age_start > 0 && $age_end > 0) {
                $query->where('AAA40', '>=', $age_start)
                    ->Where('AAA04', '<=', $age_end);
            } elseif ($age_start > 0 && $age_end <= 0) {
                $query->where('AAA40', '>=', $age_start);
            } elseif ($age_start <= 0 && $age_end > 0) {
                $query->where('AAA04', '<=', $age_end);
            }
        }
        if ($isExport == 1) {
            static::$list = array_merge(static::$list, [DB::raw('case when pm.AEJ01 > 0 then 1 when pm.AEJ02 > 0 then 1 when AEJ03 > 0 then 1 else 0 end as LNSSQ')]);
            static::$list = array_merge(static::$list, [DB::raw('case when pm.AEJ04 > 0 then 1 when pm.AEJ05 > 0 then 1 when AEJ06 > 0 then 1 else 0 end as LNSSH')]);
        }
        $query->leftjoin('patient_hospital_info as phi', 'patient_info.MED_REC_ID', '=', 'phi.AAA28');
        $query->leftjoin('patient_doctor_info as pdi', 'patient_info.MED_REC_ID', '=', 'pdi.AAA28');
        $query->leftjoin('main_operation as mo', 'patient_info.MED_REC_ID', '=', 'mo.AAA28');
        $query->leftjoin('patient_add as patient_add', 'patient_add.AAA28', '=', 'patient_info.MED_REC_ID');
        $query->leftjoin('other_diagnosis as od', 'patient_info.MED_REC_ID', '=', 'od.AAA28');
        $query->leftjoin('patient_medical_info as pm', 'patient_info.MED_REC_ID', '=', 'pm.AAA28');
        $query->leftjoin('icu as icu', 'patient_info.MED_REC_ID', '=', 'icu.AAA28');
        $query->leftjoin(DB::raw('(SELECT AAA28,RYQK FROM ba_zdlr group by AAA28,RYQK) AS of'), 'of.AAA28', '=', 'patient_info.MED_REC_ID');
        if (!empty($field)) {
            $query
                ->leftjoin('main_diagnosis as md', 'patient_info.MED_REC_ID', '=', 'md.AAA28')
                ->leftjoin('secondary_operation as so', 'patient_info.MED_REC_ID', '=', 'so.AAA28')
                //                ->leftjoin('patient_medical_info as pm', 'patient_info.MED_REC_ID', '=', 'pm.AAA28')
                //                ->leftjoin('icu as icu', 'patient_info.MED_REC_ID', '=', 'icu.AAA28')
                // ->leftjoin('ba_zdlr as ba_zdlr', 'ba_zdlr.AAA28', '=', 'patient_info.MED_REC_ID')

            ;

            foreach ($field as $key => $item) {
                $value = $item['value'];
                //                $value = $item['type']==1?$item['value']:['like','%'.$item['value'].'%'];
                if ($item['type'] == 1) {
                    switch ($key) {
                        case 'ABC01N'; //主要诊断名称
                            $query->where('md.ICD10_NAME', $value);
                            break;
                        case 'ABC01C'; //主要诊断编码
                            $query->where('md.ICD10_ID1', $value);
                            break;
                        case 'ICD10_ID1_first';
                            $query->where(['od.ICD10_ID1' => $value, 'od.DIA_ORDER' => 1]);
                            break;
                        case 'ICD10_NAME_first';
                            $query->where(['od.ICD10_NAME' => $value, 'od.DIA_ORDER' => 1]);
                            break;
                        case 'ICD10_ID1';
                            $query->where('od.ICD10_ID1', $value);
                            break;
                        case 'ICD10_NAME';
                            $query->where('od.ICD10_NAME', $value);
                            break;
                        case 'ICD9_ID1';
                            $query->where('mo.ICD9_ID1', $value);
                            break;
                        case 'ICD9_NAME';
                            $query->where('mo.ICD9_NAME', $value);
                            break;
                        case 'ABC03C'; //入院病情
                            // $query->where('pm.ABC03C', $value);
                            $value = str_replace('%', '', $value);
                            $collection = collect(array_flip(config('dictionaries.RYQK')));
                            $value = $collection->get($value, $value);
                            $query->where('of.RYQK', $value);
                            break;
                        case 'OPE_LEVEL'; //手术级别
                            $query->where('mo.OPE_LEVEL', $value);
                            break;
                        case 'IS_MAIN_WAY'; //重症监护室名称
                            $query->where('icu.IS_MAIN_WAY', $value);
                            break;
                        case 'AEM01C'; //离院方式
                            $query->where('AEM01C', $value);
                            break;
                        case 'ABA01N'; //门急诊诊断
                            $query->where('pm.ABA01N', $value);
                            break;
                        case 'ABA01C'; //门急诊疾病编码
                            $query->where('pm.ABA01C', $value);
                            break;
                        case 'AEL01'; //呼吸机
                            //                            $query->where('icu.AEL01', $value);
                            if ($value == 1) {
                                $query->where('pm.AEL01', '>', 0);
                            } else {
                                $query->where('pm.AEL01', '<=', 0);
                            }
                            break;
                        case 'RJSS'; //日间手术
                            // $query->where('mo.RJSS', $value);
                            $value = str_replace('%', '', $value);
                            $collection = collect(config('dictionaries.RJSS'));
                            $value = $collection->get($value, $value);
                            $value = $value == 1 ? "是" : "否";
                            $query->where('mo.RJSS', $value);
                            break;
                        case 'LNSSQ'; //颅脑损伤前昏迷
                            // $query->where('pm.AEJ01','>',0)->orWhere('pm.AEJ02','>',0)->orWhere('AEJ03','>',0);
                            if ($value == 1) {
                                static::$list = array_merge(static::$list, [DB::raw('case when pm.AEJ01 > 0 then 1 when pm.AEJ02 > 0 then 1 when AEJ03 > 0 then 1 else 0 end as LNSSQ')]);
                                $query->where('pm.AEJ01', '>', 0)->orWhere('pm.AEJ02', '>', 0)->orWhere('AEJ03', '>', 0);
                            }

                            break;
                        case 'LNSSH'; //颅脑损伤后昏迷
                            if ($value == 1) {
                                static::$list = array_merge(static::$list, [DB::raw('case when pm.AEJ04 > 0 then 1 when pm.AEJ05 > 0 then 1 when AEJ06 > 0 then 1 else 0 end as LNSSH')]);
                                $query->where('pm.AEJ04', '>', 0)->orWhere('pm.AEJ05', '>', 0)->orWhere('AEJ06', '>', 0);
                            }
                            // $query->where('pm.AEJ04','>',0)->orWhere('pm.AEJ05','>',0)->orWhere('AEJ06','>',0);
                            break;
                        case 'AAA28'; //病案号
                            $query->where('patient_info.AAA28', $value);
                            break;
                        case 'AAA01'; //姓名
                            $query->where('patient_info.AAA01', $value);
                            break;
                        case 'AAA02C'; //性别
                            // $vs = array_search($value,config('dictionaries.AAA02C'));
                            // if($vs){
                            //     $query->where('patient_info.AAA02C', $vs);
                            // }
                            $value = str_replace('%', '', $value);
                            if ($value > 0) {
                                $collection = collect(array_flip(config('dictionaries.AAA02C')));
                                $vs = $collection->get($value, $value);
                                $query->where('patient_info.AAA02C', $value);
                            }
                            break;
                        case 'AAA04'; //年龄
                            $query->where('patient_info.AAA04', $value);
                            break;
                        case 'AAA29'; //住院次数
                            $query->where('patient_info.AAA29', $value);
                            break;
                        case 'SSPB': // 手术判别
                            $query->where('patient_add.SSPB', str_replace('%', '', $value));
                            break;
                        case 'AAC11N': // 出院科室
                            $query->where('patient_info.AAC11N', $value);
                            break;
                    }
                } else {
                    $value = '%' . $item['value'] . '%';
                    switch ($key) {
                        case 'ABC01N';
                            $query->where('md.ICD10_NAME', 'like', $value);
                            break;
                        case 'ABC01C';
                            $query->where('md.ICD10_ID1', 'like', $value);
                            break;
                        case 'ICD10_ID1_first';
                            $query->where('od.ICD10_ID1', 'like', $value)->where('od.DIA_ORDER', 1);
                            break;
                        case 'ICD10_NAME_first';
                            $query->where('od.ICD10_NAME', 'like', $value)->where('od.DIA_ORDER', 1);
                            break;
                        case 'ICD10_ID1';
                            $query->where('od.ICD10_ID1', 'like', $value);
                            break;
                        case 'ICD10_NAME';
                            $query->where('od.ICD10_NAME', 'like', $value);
                            break;
                        case 'ICD9_ID1';
                            $query->where('mo.ICD9_ID1', 'like', $value);
                            break;
                        case 'ICD9_NAME';
                            $query->where('mo.ICD9_NAME', 'like', $value);
                            break;
                        case 'ICD8_ID1';
                            $query->where('so.ICD9_ID1', 'like', $value);
                            break;
                        case 'ICD8_NAME';
                            $query->where('so.ICD9_NAME', 'like', $value);
                            break;
                        case 'ABC03C'; //入院病情
                            // $query->where('pm.ABC03C', 'like',$value);
                            $value = str_replace('%', '', $value);
                            if ($value > 0) {
                                $collection = collect(array_flip(config('dictionaries.RYQK')));
                                $value = $collection->get($value, $value);
                                $query->where('of.RYQK', $value);
                            }
                            break;
                        case 'OPE_LEVEL'; //手术级别
                            $query->where('mo.OPE_LEVEL', 'like', $value);
                            break;
                        case 'IS_MAIN_WAY'; //重症监护室名称
                            $query->where('icu.IS_MAIN_WAY', 'like', $value);
                            break;
                        case 'AEM01C'; //离院方式
                            $query->where('AEM01C', 'like', $value);
                            break;
                        case 'ABA01N'; //门急诊诊断
                            $query->where('pm.ABA01N', 'like', $value);
                            break;
                        case 'ABA01C'; //门急诊疾病编码
                            $query->where('pm.ABA01C', 'like', $value);
                            break;
                        case 'AEL01'; //呼吸机
                            $value = str_replace('%', '', $value);
                            if ($value == 1) {
                                $query->where('pm.AEL01', '>', 0);
                            } else {
                                $query->where('pm.AEL01', '<=', 0);
                            }

                            break;
                        case 'RJSS'; //日间手术
                            $value = str_replace('%', '', $value);
                            $collection = collect(config('dictionaries.RJSS'));
                            $value = $collection->get($value, $value);
                            $value = $value == 1 ? "是" : "否";
                            $query->where('mo.RJSS', 'like', $value);
                            // $query->where('mo.RJSS', 'like',$value);
                            break;
                        case 'LNSSQ'; //颅脑损伤前昏迷
                            $value = str_replace('%', '', $value);
                            // $query->where('pm.AEJ01','>',0)->orWhere('pm.AEJ02','>',0)->orWhere('AEJ03','>',0);
                            if ($value == 1) {
                                static::$list = array_merge(static::$list, [DB::raw('case when pm.AEJ01 > 0 then 1 when pm.AEJ02 > 0 then 1 when AEJ03 > 0 then 1 else 0 end as LNSSQ')]);
                                $query->where(function ($subQuery) {
                                    $subQuery->where('pm.AEJ01', '>', 0)->orWhere('pm.AEJ02', '>', 0)->orWhere('AEJ03', '>', 0);
                                });
                            }
                            break;
                        case 'LNSSH'; //颅脑损伤后昏迷
                            $value = str_replace('%', '', $value);
                            // $query->where('pm.AEJ04','>',0)->orWhere('pm.AEJ05','>',0)->orWhere('AEJ06','>',0);
                            if ($value == 1) {
                                static::$list = array_merge(static::$list, [DB::raw('case when pm.AEJ04 > 0 then 1 when pm.AEJ05 > 0 then 1 when AEJ06 > 0 then 1 else 0 end as LNSSH')]);
                                $query->where(function ($subQuery) {
                                    $subQuery->where('pm.AEJ04', '>', 0)->orWhere('pm.AEJ05', '>', 0)->orWhere('AEJ06', '>', 0);
                                });
                            }
                            break;
                        case 'AAA28'; //病案号
                            $query->where('patient_info.AAA28', 'like', $value);
                            break;
                        case 'AAA01'; //姓名
                            $query->where('patient_info.AAA01', 'like', $value);
                            break;
                        case 'AAA02C'; //性别
                            $value = str_replace('%', '', $value);
                            $collection = collect(array_flip(config('dictionaries.AAA02C')));
                            $vs = $collection->get($value, $value);
                            $query->where('patient_info.AAA02C', $value);
                            // $vs = array_search($item['value'],config('dictionaries.AAA02C'));
                            // if($vs){
                            // $value = '%'.$vs.'%';
                            // $query->where('patient_info.AAA02C', 'like',$value);
                            // }
                            break;
                        case 'AAA04'; //年龄
                            $query->where('patient_info.AAA04', 'like', $value);
                            break;
                        case 'AAA29'; //住院次数
                            $value = str_replace('%', '', $value);
                            //                            var_dump($value);exit;
                            //                            $query->where('patient_info.AAA29', 'like',$value);
                            $query->where('patient_info.AAA29', $value);
                            break;
                        case 'SSPB': // 手术判别
                            $value = str_replace('%', '', $value);
                            if ($value < 5) {
                                $query->where('patient_add.SSPB', str_replace('%', '', $value));
                            }
                            break;
                        case 'AAC11N': // 出院科室
                            $value = str_replace('%', '', $value);
                            $chuyaunName = '';
                            foreach ($item['selectList'] as $chuyuan) {
                                if ($chuyuan['id'] == $value) {
                                    $chuyaunName = $chuyuan['label'];
                                    break;
                                }
                            }
                            $value = '%' . $chuyaunName . '%';
                            $query->where('patient_info.AAC11N', 'like', $value);
                            break;
                            //                        case 'AAC04'://住院天数
                            //                            echo $value;
                            //                            exit;

                    }
                }
            }
        }
        $count = $query->count(DB::raw('DISTINCT MED_REC_ID'));
        $data = $query
            ->offset($offset)
            ->limit($limit)
            ->orderBy('MED_REC_ID', 'desc')
            ->groupBy('MED_REC_ID')
            ->get(self::$list);
        if (empty($data)) {
            return [];
        } else {
            return ['list' => self::fee(ToolsService::codeTransformationList(self::$listCode, $data->toArray())), 'count' => $count];
        }
    }

    public static function getUserBaseInfo($start, $end, $type)
    {
        $data = PatientInfo::query()
            ->leftjoin('patient_hospital_info as phi', 'patient_info.MED_REC_ID', '=', 'phi.AAA28')
            ->leftjoin('patient_medical_info as pmi', 'patient_info.MED_REC_ID', '=', 'pmi.AAA28')
            ->leftjoin('patient_other_info as poi', 'patient_info.MED_REC_ID', '=', 'poi.AAA28')
            ->leftjoin('patient_doctor_info as pdi', 'patient_info.MED_REC_ID', '=', 'pdi.AAA28')
            ->leftjoin('patient_work_info as pwi', 'patient_info.MED_REC_ID', '=', 'pwi.AAA28')
            ->leftjoin('patient_contacts_info as pcni', 'patient_info.MED_REC_ID', '=', 'pcni.AAA28')
            ->leftjoin('patient_cost_info as pci', 'patient_info.MED_REC_ID', '=', 'pci.AAA28')
            ->leftjoin('patient_address_info as pai', 'pai.AAA28', '=', 'patient_info.MED_REC_ID')
            ->leftjoin('patient_add as pa', 'pa.AAA28', '=', 'patient_info.MED_REC_ID')
            ->leftjoin('main_diagnosis as md', 'md.AAA28', '=', 'patient_info.MED_REC_ID')
            ->whereBetween('patient_info.AAC01', [$start, $end])
            ->groupBy('patient_info.MED_REC_ID')
            ->get(self::$wt);
        if ($data) {
            $data = $data->toArray();
        } else {
            $data = [];
        }
        foreach ($data as &$item) {
            if ($type == 0) {
                $item['AAA03'] = self::getDate($item['AAA03']);
            } else {
                $item['AAA03'] = self::getYMDDate($item['AAA03']);
            }
            $item['AAA23C'] = "\t" . self::getGX($item['AAA23C']);
            $item['AAA05C'] = self::getGJ($item['AAA05C']);
            $item['SFZJLX'] = self::getZJLX($item['SFZJLX'], $type);
            $item['AAA07'] = "\t" . $item['AAA07'];
            $item['AAA20'] = "\t" . $item['AAA20'];
            $item['AAA25'] = "\t" . $item['AAA25'];
            $item['AAA51'] = "\t" . $item['AAA51'];
            $item['AAB07D'] = self::getDate($item['AAB07D']);
            $item['AED04'] = self::getYMDDate($item['AED04']);
            $item['AAB01'] = self::getGDate($item['AAB01']);
            $item['AAC01'] = self::getGDate($item['AAC01']);
            $item['AAC02C'] = config('dictionaries.ABAS02.' . $item['AAC03']);
            $item['CYSJS'] = self::getS($item['AAC01']);
            $item['rys'] = self::getS($item['AAB01']);
            $item['AEE01_SFZ'] = "\t" . self::getStaffInfo($item['AEE01_CODE'], 'sfz');
            $item['AEE01_CODE'] = "\t" . self::getStaffInfo($item['AEE01_CODE'], 'base_code');
            $item['ZHFZRYS_SFZ'] = "\t" . self::getStaffInfo($item['ZHFZRYS'], 'sfz');
            $item['ZHFZRYS'] = self::getStaffInfo($item['ZHFZRYS'], 'base_code');
            $item['ZZYS_SFZ'] = "\t" . self::getStaffInfo($item['ZZYSBM'], 'sfz');
            $item['ZZYSBM'] = self::getStaffInfo($item['ZZYSBM'], 'base_code');
            $item['ZYYS_SFZ'] = "\t" . self::getStaffInfo($item['ZYYSBM'], 'sfz');
            $item['ZYYSBM'] = self::getStaffInfo($item['ZYYSBM'], 'base_code');
            $item['ZRHS_SFZ'] = "\t" . self::getStaffInfo($item['ZRHSBM'], 'sfz');
            $item['ZRHSBM'] = self::getStaffInfo($item['ZRHSBM'], 'base_code');
            $item['BMY_SFZ'] = "\t" . self::getStaffInfo($item['AEE08'], 'sfz');
            $item['ZKHS_SFZ'] = "\t" . self::getStaffInfo($item['ZKHSBM'], 'sfz');
            $item['ZKHSBM'] = self::getStaffInfo($item['ZKHSBM'], 'base_code');
            $item['AEE09_SFZ'] = "\t" . self::getStaffInfoByName($item['AEE09'], 'sfz');
            $item['AEE09_CODE'] = self::getStaffInfoByName($item['AEE09'], 'base_code');
            $item['AEE05_SFZ'] = "\t" . self::getStaffInfoByName($item['AEE05'], 'sfz');
            $item['AEE05_CODE'] = self::getStaffInfoByName($item['AEE05'], 'base_code');
            $item['AED02_SFZ'] = "\t" . self::getStaffInfoByName($item['AED02'], 'sfz');
            $item['AED02_CODE'] = self::getStaffInfoByName($item['AED02'], 'base_code');
            $item['AAA26C'] = "\t" . self::getYLFKFS($item['AAA26C']);
            $item['AAA18C'] = self::getZY($item['AAA18C']);
            $item['AAC03'] = self::getBF($item['AAC02C']);
            if ($type == 0) {
                $item['AAA08C'] = self::getHY($item['AAA08C']);
            }
            if ($type == 1) {
                $item['AAA09'] = $item['AAA09'] . $item['AAA10'] . $item['AAA11'];
                $item['AAA12'] = $item['AAA45'] . $item['AAA46'] . $item['AAA47'] . $item['AAA12'];
                $item['AAA48'] = $item['AAA48'] . $item['AAA49'] . $item['AAA50'] . $item['AAA15'];
                $jg = self::getCityCode($item['AAA43'], $item['AAA44']);
                $item['AAA43'] = $jg['province_code'] ?? '';
            } else {
                //出生地址
                $csd = self::getCityCode($item['AAA09'], $item['AAA10'], $item['AAA11']);
                $item['AAA09'] = $csd['province_code'] ?? '';
                $item['AAA10'] = $csd['city_code'] ?? '';
                $item['AAA11'] = $csd['region_code'] ?? '';
                //籍贯
                $jg = self::getCityCode($item['AAA43'], $item['AAA44']);
                $item['AAA43'] = $jg['province_code'] ?? '';
                $item['AAA44'] = $jg['city_code'] ?? '';
                //现住址
                $xzz = self::getCityCode($item['AAA48'], $item['AAA49'], $item['AAA50']);
                $item['AAA48'] = $xzz['province_code'] ?? '';
                $item['AAA49'] = $xzz['city_code'] ?? '';
                $item['AAA50'] = $xzz['region_code'] ?? '';
                //户籍
                $hj = self::getCityCode($item['AAA45'], $item['AAA46'], $item['AAA47']);
                $item['AAA45'] = $hj['province_code'] ?? '';
                $item['AAA46'] = $hj['city_code'] ?? '';
                $item['AAA47'] = $hj['region_code'] ?? '';
            }
            if ($item['AEM01C'] == 2) {
                $item['YZZY_YLJG'] = $item['AEM02'];
            } elseif ($item['AEM01C'] == 3) {
                $item['WSY_YLJG'] = $item['AEM02'];
            }
        }
        return $data;
    }

    public static function getUserOtherDiagnosis($list)
    {
        $data = OtherDiagnosis::query()
            ->whereIn('AAA28', $list)
            ->orderBy('DIA_ORDER')
            ->get(['AAA28', 'ICD10_ID1', 'ICD10_NAME', 'DIA_ORDER', 'RYQK']);
        if ($data) {
            $data = $data->toArray();
        } else {
            $data = [];
        }
        $diagnosis = [];
        foreach ($data as $item) {
            $diagnosis[$item['AAA28']][] = [
                'ICD10_ID1' => $item['ICD10_ID1'],
                'ICD10_NAME' => $item['ICD10_NAME'],
                'RYQK' => $item['RYQK'],
            ];
        }
        return $diagnosis;
    }

    public static function getUserIcu($list)
    {
        $data = Icu::query()
            ->whereIn('AAA28', $list)
            ->orderBy('IN_TIME')
            ->get(['AAA28', 'IS_MAIN_WAY', 'IN_TIME', 'OUT_TIME'])
            ->keyBy('AAA28');
        if ($data) {
            $data = $data->toArray();
        } else {
            $data = [];
        }
        $icu = [];
        foreach ($data as $item) {
            $icu[$item['AAA28']][] = [
                'IS_MAIN_WAY' => $item['IS_MAIN_WAY'],
                'IN_TIME' => self::getDate($item['IN_TIME']),
                'OUT_TIME' => self::getDate($item['OUT_TIME']),
            ];
        }
        return $icu;
    }

    public static function getMainOption($list)
    {
        $data = MainOperation::query()
            ->whereIn('AAA28', $list)
            ->get([
                'AAA28',
                'ICD9_ID1',
                'ICD9_NAME',
                'OPE_DATE',
                'OPE_LEVEL',
                'OPE_TYPE',
                'OPE_MAN_NAME',
                'OPE_MAN_CODE',
                'FRIST_ASSISTANT_NAME',
                'FRIST_ASSISTANT_CODE',
                'SECOND_ASSISTANT_NAME',
                'SECOND_ASSISTANT_CODE',
                'INCISION_GRADE_ID',
                'HEAL_ID',
                'HOCUS_WAY_ID',
                'HOCUS_MAN_NAME',
                'HOCUS_MAN_CODE',
                'START_TIME',
                'END_TIME',
                'RJSS',
            ]);
        if ($data) {
            $data = $data->toArray();
        } else {
            $data = [];
        }
        $option = [];
        foreach ($data as $item) {
            $option[$item['AAA28']] = [
                'ICD9_ID1' => self::getOptionCode($item['ICD9_ID1'], 'ICD9_ID1') ? self::getOptionCode($item['ICD9_ID1'], 'ICD9_ID1') : '-',
                'ICD9_NAME' => self::getOptionCode($item['ICD9_ID1'], 'ICD9_NAME') ?: '-',
                'OPE_DATE' => self::getGDate($item['OPE_DATE']) ?: '-',
                'OPE_LEVEL' => $item['OPE_LEVEL'],
                'OPE_TYPE' => self::getOptionCode($item['ICD9_ID1'], 'OPE_TYPE'),
                'OPE_MAN_NAME' => $item['OPE_MAN_NAME'],
                'OPE_MAN' => "\t" . self::getStaffInfo($item['OPE_MAN_CODE'], 'sfz'),
                'OPE_MAN_CODE' => "\t" . self::getStaffInfo($item['OPE_MAN_CODE'], 'base_code'),
                'FRIST_ASSISTANT_NAME' => $item['FRIST_ASSISTANT_NAME'],
                'FRIST_ASSISTANT' => "\t" . self::getStaffInfo($item['FRIST_ASSISTANT_CODE'], 'sfz'),
                'FRIST_ASSISTANT_CODE' => "\t" . self::getStaffInfo($item['FRIST_ASSISTANT_CODE'], 'base_code'),
                'SECOND_ASSISTANT_NAME' => $item['SECOND_ASSISTANT_NAME'],
                'SECOND_ASSISTANT' => "\t" . self::getStaffInfo($item['SECOND_ASSISTANT_CODE'], 'sfz'),
                'SECOND_ASSISTANT_CODE' => "\t" . self::getStaffInfo($item['SECOND_ASSISTANT_CODE'], 'base_code'),
                'INCISION_GRADE_ID' => $item['INCISION_GRADE_ID'],
                'HEAL_ID' => $item['HEAL_ID'],
                'HOCUS_WAY_ID' => $item['HOCUS_WAY_ID'] ? self::getMzfs1($item['HOCUS_WAY_ID']) : '', //
                'HOCUS_MAN_NAME' => $item['HOCUS_MAN_NAME'],
                'HOCUS_MAN' => "\t" . self::getStaffInfo($item['HOCUS_MAN_CODE'], 'sfz'),
                'HOCUS_MAN_CODE' => "\t" . self::getStaffInfo($item['HOCUS_MAN_CODE'], 'base_code'),
                'es' => strtotime($item['END_TIME']) - strtotime($item['START_TIME']), //秒
                'RJSS' => $item['RJSS'],
            ];
        }
        return $option;
    }

    public static function getOtherOption($list)
    {
        $data = SecondaryOperation::query()
            ->whereIn('AAA28', $list)
            ->orderBy('OPE_ORDER')
            ->get([
                'AAA28',
                'ICD9_ID1',
                'ICD9_NAME',
                'OPE_DATE',
                'OPE_LEVEL',
                'OPE_TYPE',
                'OPE_MAN_NAME',
                'OPE_MAN_CODE',
                'FRIST_ASSISTANT_NAME',
                'FRIST_ASSISTANT_CODE',
                'SECOND_ASSISTANT_NAME',
                'SECOND_ASSISTANT_CODE',
                'INCISION_GRADE_ID',
                'HEAL_ID',
                'HOCUS_WAY_ID',
                'HOCUS_MAN_NAME',
                'HOCUS_MAN_CODE',
                'START_TIME',
                'END_TIME',
            ]);
        if ($data) {
            $data = $data->toArray();
        } else {
            $data = [];
        }
        $option = [];
        foreach ($data as $item) {
            $option[$item['AAA28']][] = [
                'ICD9_ID1' => self::getOptionCode($item['ICD9_ID1'], 'ICD9_ID1') ?: '-',
                'ICD9_NAME' => self::getOptionCode($item['ICD9_ID1'], 'ICD9_NAME') ?: '-',
                'OPE_DATE' => self::getDate($item['OPE_DATE']) ?: '-',
                'OPE_LEVEL' => $item['OPE_LEVEL'],
                'OPE_TYPE' => self::getOptionCode($item['ICD9_ID1'], 'OPE_TYPE'),
                'OPE_MAN_NAME' => $item['OPE_MAN_NAME'],
                'OPE_MAN' => "\t" . self::getStaffInfo($item['OPE_MAN_CODE'], 'sfz'),
                'OPE_MAN_CODE' => "\t" . self::getStaffInfo($item['OPE_MAN_CODE'], 'base_code'),
                'FRIST_ASSISTANT_NAME' => $item['FRIST_ASSISTANT_NAME'],
                'FRIST_ASSISTANT' => "\t" . self::getStaffInfo($item['FRIST_ASSISTANT_CODE'], 'sfz'),
                'FRIST_ASSISTANT_CODE' => "\t" . self::getStaffInfo($item['FRIST_ASSISTANT_CODE'], 'base_code'),
                'SECOND_ASSISTANT_NAME' => $item['SECOND_ASSISTANT_NAME'],
                'SECOND_ASSISTANT' => "\t" . self::getStaffInfo($item['SECOND_ASSISTANT_CODE'], 'sfz'),
                'SECOND_ASSISTANT_CODE' => "\t" . self::getStaffInfo($item['SECOND_ASSISTANT_CODE'], 'base_code'),
                'INCISION_GRADE_ID' => $item['INCISION_GRADE_ID'],
                'HEAL_ID' => $item['HEAL_ID'],
                'HOCUS_WAY_ID' => "\t" . $item['HOCUS_WAY_ID'],
                'HOCUS_MAN_NAME' => $item['HOCUS_MAN_NAME'],
                'HOCUS_MAN' => "\t" . self::getStaffInfo($item['HOCUS_MAN_CODE'], 'sfz'),
                'HOCUS_MAN_CODE' => "\t" . self::getStaffInfo($item['HOCUS_MAN_CODE'], 'base_code'),
                'es' => strtotime($item['END_TIME']) - strtotime($item['START_TIME']), //秒
            ];
        }
        return $option;
    }

    public static function getStaffInfo($code, $field)
    {
        $info = Cache::get('staff');
        if ($info === null) {
            $info = Staff::query()
                ->get(['code', 'name', 'base_code', 'sfz'])
                ->keyBy('code');
            if ($info) {
                $info = $info->toArray();
                if (!empty($info)) {
                    Cache::put('staff', $info, 86400);
                }
            } else {
                $info = '';
            }
        }
        return $info[$code][$field] ?? '';
    }

    public static function getStaffInfoByName($name, $field)
    {
        if (empty($name)) {
            return '';
        }
        $info = Cache::get('staff');
        if ($info === null) {
            $info = Staff::query()
                ->get(['code', 'name', 'base_code', 'sfz'])
                ->keyBy('code');
            if ($info) {
                $info = $info->toArray();
                if (!empty($info)) {
                    Cache::put('staff', $info, 86400);
                }
            } else {
                $info = '';
            }
        }
        $info = array_column($info, null, 'name');
        return $info[$name][$field] ?? '';
    }

    public static function getOptionCode($code, $field)
    {
        $info = Cache::get('optionCode_' . $code);
        if ($info === null) {
            $info = SurgeryClassMapping::query()
                ->where('code', $code)
                ->first(['three_code as ICD9_ID1', 'three_name as ICD9_NAME', 'three_type as OPE_TYPE']);
            if ($info) {
                $info = $info->toArray();
                if (!empty($info)) {
                    Cache::put('optionCode_' . $code, $info, 86400);
                }
            } else {
                $info = [];
            }
        }
        return $info[$field] ?? '';
    }

    public static function getDate($date)
    {
        if (empty($date)) {
            return '';
        } else {
            return date('Ymd', strtotime($date));
        }
    }

    public static function getGDate($date)
    {
        if (empty($date)) {
            return '';
        } else {
            return date('Y/m/d H:i:s', strtotime($date));
        }
    }

    public static function getYMDDate($date)
    {
        if (empty($date)) {
            return '';
        } else {
            return date('Y/m/d', strtotime($date));
        }
    }

    public static function getS($date)
    {
        if (empty($date)) {
            return '';
        } else {
            return date('H', strtotime($date));
        }
    }

    public static function getYLFKFS($data)
    {
        $arr = [
            '1' => '1.1',
            '2' => '2.1',
            '3' => '3.1',
            '4' => '4',
            '5' => '5',
            '6' => '6',
            '7' => '7',
            '8' => '8',
            '9' => '9',
        ];
        return $arr[$data] ?? '';
    }

    public static function getGX($data)
    {
        if (in_array($data, [1, 2, 3])) {
            return 0;
        } elseif (in_array($data, [10, 11, 12])) {
            return 1;
        } elseif (in_array($data, [20, 21, 22, 23, 24, 25, 26, 27, 29])) {
            return 2;
        } elseif (in_array($data, [30, 31, 32, 33, 34, 35, 36, 37, 39])) {
            return 3;
        } elseif (in_array($data, [41, 42, 43, 44])) {
            return 4;
        } elseif (in_array($data, [51, 52, 52])) {
            return 5;
        } elseif (in_array($data, [61, 62, 63, 64])) {
            return 6;
        } elseif (in_array($data, [70, 71, 73, 75, 77])) {
            return 7;
        } elseif (in_array($data, [28, 38, 53, 54, 55, 56, 57, 58, 66, 67, 72, 74, 76, 78, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90, 91, 92, 93, 94, 95, 96, 97, 98, 99, 100])) {
            return 9;
        } else {
            return 8;
        }
    }

    public static function getGJ($data)
    {
        $GJ = [
            '1' => 'CHN',
            '4' => 'AFG',
            '36' => 'AUS',
            '104' => 'MMR',
            '124' => 'CAN',
            '144' => 'LKA',
            '250' => 'FRA',
            '344' => 'HKG',
            '392' => 'JPN',
            '410' => 'PRK',
            '578' => 'NOR',
            '586' => 'PAK',
            '616' => 'POL',
            '710' => 'ZAF',
            '716' => 'ZWE',
            '804' => 'UKR',
            '826' => 'GBR',
            '840' => 'USA',
            '894' => 'ZMB',
        ];
        return $GJ[$data] ?? '';
    }

    public static function getZJLX($data, $type)
    {
        if ($type == 0) {
            $LX = [
                '01' => '居民身份证',
                '02' => '居民户口簿',
                '03' => '护照',
                '04' => '军官证',
                '05' => '驾驶证',
                '06' => '港澳居民来往内地通行证',
                '07' => '台湾居民来往内地通行证',
                '99' => '其他法定有效证件',
            ];
            $mr = '01';
        } else {
            $LX = [
                '1' => '居民身份证',
                '2' => '中国人民解放军军人身份证件',
                '3' => '中国人民武装警察身份证件',
                '4' => '港澳居民来往内地通行证',
                '5' => '台湾居民来往大陆通行证',
                '6' => '护照',
                '9' => '其他',
            ];
            $mr = '1';
        }

        return array_search($data, $LX) ?? $mr;
    }

    public static function getZY($data)
    {
        if ($data == 13 || $data == 16) {
            return 11;
        } elseif ($data == 6 || $data == 10) {
            return 13;
        } elseif ($data == 7 || $data == 9) {
            return 51;
        } elseif ($data == 14 || $data == 17) {
            return 90;
        } elseif ($data == 8) {
            return 17;
        } elseif ($data == 12) {
            return 21;
        } elseif ($data == 1) {
            return 24;
        } elseif ($data == 2) {
            return 27;
        } elseif ($data == 4) {
            return 31;
        } elseif ($data == 3) {
            return 37;
        } elseif ($data == 18) {
            return 54;
        } elseif ($data == 5) {
            return 70;
        } elseif ($data == 15) {
            return 80;
        } else {
            return 0;
        }
    }

    public static function getCityCode($province, $city, $county = '')
    {
        $province_code = config('city.' . $province);
        $key = 'province_' . $province_code;
        Cache::forget($key);
        $data = Cache::get($key);
        if ($data === null) {
            $query = CityLib::query()
                ->where('province_code', $province_code)
                ->get(['province_code', 'city', 'city_code', 'region', 'region_code']);
            if ($query) {
                $query = $query->toArray();
            } else {
                $query = [];
            }
            if (!empty($query)) {
                $data['province_code'] = array_column($query, 'province_code')[0] .= '0000';
                $data['city'] = array_column($query, 'city_code', 'city');
                $data['region'] = array_column($query, 'region_code', 'region');
                Cache::put($key, $data, 86400);
            }
        }
        if (empty($data)) {
            return [];
        } else {
            $result['province_code'] = $data['province_code'];
            $citys = $data['city'];
            if (isset($citys[$city])) {
                $result['city_code'] = $citys[$city] . '00';
            } else {
                $result['city_code'] = '';
            }
            $region = $data['region'];
            $result['region_code'] = empty($county) ? '' : $region[$county] ?? $result['city_code'];
            return $result;
        }
    }

    public static function getHY($data)
    {
        $HY = [
            1 => 10,
            2 => 20,
            3 => 30,
            4 => 40,
            9 => 90,
        ];
        return $HY[$data] ?? '90';
    }

    public static function getBF($data)
    {
        return config('dictionaries.ABAS02.' . $data) ?? '';
    }

    public static function getMzfs1($hocus_way_id)
    {
        if (in_array($hocus_way_id, array_keys(self::$his_mzfs1))) {
            $hocus_way_name = self::$his_mzfs1[$hocus_way_id]['name'];
        } else {
            $hocus_way_name = '其他';
        }
        return $hocus_way_name;
    }

    public static function normalSearchByKeyword($params = [], $keyword = '', $detail = 0, $lastId = false)
    {
        if (!empty($keyword)) {
            $bllb_array = self::$bllb_array;
            $nested = [];
            foreach ($bllb_array as $bllb) {
                $nested[] = [
                    'match_phrase' => [
                        'EMR_BL_BL01.' . $bllb => $keyword,
                    ]
                ];
            }

            $params_list[] = [
                'nested' => [
                    "path" => "Fee_detailed",
                    "query" => [
                        'bool' => [
                            'must' => [
                                'match_phrase' => [
                                    'Fee_detailed.FYMC' => $keyword,
                                ]
                            ]
                        ]
                    ],
                ]

            ];
            $params_list[] = [
                'nested' => [
                    "path" => "YZB",
                    "query" => [
                        'bool' => [
                            'must' => [
                                'match_phrase' => [
                                    'YZB.YZMC' => $keyword,
                                ]
                            ]
                        ]
                    ],
                ]
            ];
        }

        $inner_hits = [];
        if ($detail == 1) {
            $inner_hits = [
                'highlight' => [
                    'fields' => [
                        '*' => [
                            "pre_tags" => "<font color='red'>",
                            "post_tags" => "</font>",
                        ],
                    ],
                    "fragment_size" => 10000,
                    "number_of_fragments" => 0,
                ],
            ];
        }
        if (!empty($nested)) {
            $params_list[] = [
                'nested' => [
                    "path" => "EMR_BL_BL01",
                    "query" => [
                        'bool' => [
                            'should' => $nested
                        ]
                    ],
                    $inner_hits == [] ? '' : 'inner_hits' => $inner_hits
                ]
            ];
        }
        if (!empty($params_list)) {
            $params['body']['query']['bool']['should'] = $params_list;
        }
        $params['body']['query']['bool']["minimum_should_match"] = 1;
        $params['body']['track_total_hits'] = true;
        //        if (empty($keyword)) {
        //            $params['body']['sort'] = [['MED_REC_ID.keyword' => 'asc']];
        //        } else {
        //            $params['body']['sort'] = [['MED_REC_ID' => 'asc']];
        //        }
        $params['body']['sort'] = [['AAC01' => 'desc']];
        if ($lastId !== false) {
            $params['body']['search_after'] = [$lastId];
        }
        $params['body']['_source'] = ['AAA28', 'MED_REC_ID', 'AAA04', 'AAC11N', 'AAB01', 'AAC01'];

        return $params;
    }

    /**
     * @param array $params
     * @param array $request
     * @param int $detail
     * @param bool $lastId
     * 高级搜索的参数
     */
    public static function getSearchDataParamsOld($params = [], $map = [], $detail = 0, $lastId = false)
    {
        //高级搜素
        //出院时间
        $start_time = $map["start_time"] ?? '';
        $end_time = $map["end_time"] ?? '';
        $date_start = $map["date_start"] ?? '';
        $date_end = $map["date_end"] ?? '';
        $age_start = $map["age_start"] ?? '';
        $age_end = $map["age_end"] ?? '';
        $day_start = $map["day_start"] ?? '';
        $day_end = $map["day_end"] ?? '';
        $field = $map['field'] ?? '';
        if ($start_time && $end_time) {
            $start_time = date('Y-m-d', strtotime($start_time)) . ' 00:00:00';
            $end_time = date('Y-m-d', strtotime($end_time)) . ' 23:59:59';
            $params['body']['query']['bool']['must'][] = [
                'range' => [
                    'AAC01' => [
                        'gte' => $start_time,
                        'lte' => $end_time,
                    ]
                ]
            ];
        }
        //住院天数
        if ($date_start >= 0 && $date_end) {
            $params['body']['query']['bool']['must'][] = [
                'range' => [
                    'AAC04' => [
                        'gte' => $date_start,
                        'lte' => $date_end,
                    ]
                ]
            ];
        }
        //年龄
        if ($age_start >= 0 && $age_end) {
            $params['body']['query']['bool']['must'][] = [
                'range' => [
                    'AAA04' => [
                        'gte' => $age_start,
                        'lte' => $age_end,
                    ]
                ]
            ];
        }
        //天龄
        if ($day_start >= 0 && $day_end) {
            $params['body']['query']['bool']['must'][] = [
                'range' => [
                    'AAA40' => [
                        'gte' => $day_start,
                        'lte' => $day_end,
                    ]
                ]
            ];
        }

        //根据自定义字段生成elastic的搜索拼写
        $field_params = [];
        $bllb_array = self::$bllb_array;
        if (!empty($field)) {
            foreach ($field as $item) {
                if (empty($item['value'])) {
                    continue;
                }
                if ($item['select_type'] == "1") {
                    $select_type = "should";
                } else if ($item['select_type'] == 2) {
                    $select_type = "must_not";
                } else {
                    $select_type = "must";
                }

                if (in_array($item['key'], $bllb_array)) {
                    if ($item['type'] != 0) { //like
                        $key = '.keyword';
                    } else {
                        $key = '';
                    }
                    //数组为单个记录的搜索类型和搜索信息
                    $field_params[] = [
                        'type' => $select_type,
                        'query' => [
                            'match_phrase' => [
                                'EMR_BL_BL01.' . $item['key'] . $key => $item['value'],
                            ],
                        ],
                        'nested_path' => 'EMR_BL_BL01',
                    ];
                } elseif ($item['key'] == "49") { //医嘱本
                    if ($item['type'] != 0) { //like
                        $key = 'YZMC.keyword';
                    } else {
                        $key = 'YZMC';
                    }
                    $field_params[] = [
                        'type' => $select_type,
                        'query' => [
                            'match_phrase' => [
                                'YZB.' . $key => $item['value'],
                            ]
                        ],
                        'nested_path' => 'YZB',
                        'key' => $key,
                    ];
                } elseif ($item['key'] == "AAC11N") { //科室
                    if ($item['value'] != "全部") {
                        $params['body']['query']['bool']['must'][] = [
                            'match_phrase' => [
                                'AAC11N' => $item['value']
                            ]
                        ];
                    }
                } elseif ($item['key'] == "FYMC") { //消费明细
                    if ($item['type'] != 0) { //like
                        $key = 'FYMC.keyword';
                    } else {
                        $key = 'FYMC';
                    }
                    $field_params[] = [
                        'type' => $select_type,
                        'query' => [
                            'match_phrase' => [
                                'Fee_detailed.' . $key => $item['value'],
                            ]
                        ],
                        'nested_path' => 'Fee_detailed',
                        'key' => $key,
                    ];
                } elseif ($item['key'] == "2000002") { //报告单
                    if ($item['type'] != 0) { //like
                        $key = 'JCMC.keyword';
                    } else {
                        $key = 'JCMC';
                    }
                    $field_params[] = [
                        'type' => $select_type,
                        'query' => [
                            'match_phrase' => [
                                'pacs_jcmc_list.' . $key => $item['value'],
                            ]
                        ],
                        'nested_path' => 'pacs_jcmc_list',
                        'key' => $key,
                    ];
                } else {
                    //不存在，直接continue
                    continue;
                }
            }

            //消费明细和医嘱本的查询条件需要提取出来，去查询相匹配的记录
            $query = [];
            if (!empty($field_params)) {
                $inner_hits = [];
                if ($detail == 1) {
                    $inner_hits = [
                        'highlight' => [
                            'fields' => [
                                '*' => [
                                    "pre_tags" => "<font color='red'>",
                                    "post_tags" => "</font>",
                                ],
                            ],
                            "fragment_size" => 10000,
                            "number_of_fragments" => 0,
                        ],
                    ];
                }

                $add = false;
                foreach ($field_params as $field) {
                    $nested = [
                        'nested' => [
                            "path" => $field['nested_path'],
                            "query" => [
                                'bool' => [
                                    'must' => [
                                        $field['query']
                                    ]
                                ]
                            ],
                        ]
                    ];
                    if ($field['nested_path'] == "EMR_BL_BL01" && !$add) {
                        $nested['nested']['inner_hits'] = $inner_hits;
                        $add = true;
                    }
                    if (empty($query)) {
                        $query = $nested;
                    } else {
                        if ($field['type'] == 'must_not') {
                            $temp = [
                                'bool' => [
                                    'must' => [
                                        $query == [] ? '' : $query,
                                        [
                                            'bool' => [
                                                'must_not' => $nested,
                                            ]
                                        ]
                                    ]
                                ]
                            ];
                        } else {
                            $temp = [
                                'bool' => [
                                    $field['type'] => [
                                        $query == [] ? '' : $query,
                                        $nested,
                                    ]
                                ]
                            ];
                        }

                        $query = $temp;
                    }
                }
            }

            //如果fields存在，则和外层的查询放在一起
            if (!empty($query)) {
                if (!empty($params['body']['query']['bool']['must'])) {
                    $params['body']['query']['bool']['must'][] = $query;
                } else {
                    $params['body']['query'] = $query;
                }
            }
            $params['body']['track_total_hits'] = true;
        }
        if ($lastId !== false) {
            $params['body']['search_after'] = [$lastId];
        }
        $params['_source'] = ['AAA28', 'MED_REC_ID', 'AAA04', 'AAC11N', 'AAB01', 'AAC01'];

        return $params;
    }

    /**
     * @param array $params
     * @param array $request
     * @param int $detail
     * @param bool $lastId
     * 高级搜索的参数
     */
    public static function getSearchDataParams($params = [], $map = [], $detail = 0, $lastId = false)
    {
        //高级搜素
        //出院时间
        $start_time = $map["start_time"] ?? '';
        $end_time = $map["end_time"] ?? '';
        $date_start = $map["date_start"] ?? '';
        $date_end = $map["date_end"] ?? '';
        $age_start = $map["age_start"] ?? '';
        $age_end = $map["age_end"] ?? '';
        $day_start = $map["day_start"] ?? '';
        $day_end = $map["day_end"] ?? '';
        $field = $map['field'] ?? '';
        $AAA28 = $map["AAA28"] ?? '';

        $fieldMap = array_column($field, null, "key");
        // 手术名称（主+其他）
        $ICD9_NAME = !empty($fieldMap["ICD9_NAME"]) ? $fieldMap["ICD9_NAME"]['value'] : "";
        if ($ICD9_NAME) {
            $params['body']['query']['bool']['must'][] = [
                'bool' => [
                    'should' => [
                        [
                            'nested' => [
                                "path" => "secondary_operation",
                                "query" => [
                                    'bool' => [
                                        'must' => [
                                            ["match_phrase" => ["secondary_operation.ICD9_NAME" => $ICD9_NAME]]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        [
                            'nested' => [
                                "path" => "main_operation",
                                "query" => [
                                    'bool' => [
                                        'must' => [
                                            ["match_phrase" => ["main_operation.ICD9_NAME" => $ICD9_NAME]]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        }

        //手术编码（主+其他）
        $ICD9_ID1 = !empty($fieldMap["ICD9_ID1"]) ? $fieldMap["ICD9_ID1"]['value'] : "";
        if ($ICD9_ID1) {
            $params['body']['query']['bool']['must'][] = [
                'bool' => [
                    'should' => [
                        [
                            'nested' => [
                                "path" => "secondary_operation",
                                "query" => [
                                    'bool' => [
                                        'must' => [
                                            ["match_phrase" => ["secondary_operation.ICD9_ID1" => $ICD9_ID1]]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        [
                            'nested' => [
                                "path" => "main_operation",
                                "query" => [
                                    'bool' => [
                                        'must' => [
                                            ["match_phrase" => ["main_operation.ICD9_ID1" => $ICD9_ID1]]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        }

        // 其他手术名称
        $secondary_operation_ICD9_NAME = !empty($fieldMap["secondary_operation_ICD9_NAME"]) ? $fieldMap["secondary_operation_ICD9_NAME"]['value'] : "";
        if ($secondary_operation_ICD9_NAME) {

            $params['body']['query']['bool']['must'][] = [
                'nested' => [
                    "path" => "secondary_operation",
                    "query" => [
                        'bool' => [
                            'must' => [
                                ["match_phrase" => ["secondary_operation.ICD9_NAME" => $secondary_operation_ICD9_NAME]]
                            ]
                        ]
                    ]
                ]
            ];
        }
        // 其他手术编码
        $secondary_operation_ICD9_ID1 = !empty($fieldMap["secondary_operation_ICD9_ID1"]) ? $fieldMap["secondary_operation_ICD9_ID1"]['value'] : "";
        if ($secondary_operation_ICD9_ID1) {

            $params['body']['query']['bool']['must'][] = [
                'nested' => [
                    "path" => "secondary_operation",
                    "query" => [
                        'bool' => [
                            'must' => [
                                ["term" => ["secondary_operation.ICD9_ID1" => $secondary_operation_ICD9_ID1]]
                            ]
                        ]
                    ]
                ]
            ];
        }
        // 主要手术名称
        $main_operation_ICD9_NAME = !empty($fieldMap["main_operation_ICD9_NAME"]) ? $fieldMap["main_operation_ICD9_NAME"]['value'] : "";
        if ($main_operation_ICD9_NAME) {

            $params['body']['query']['bool']['must'][] = [
                'nested' => [
                    "path" => "main_operation",
                    "query" => [
                        'bool' => [
                            'must' => [
                                ["match_phrase" => ["main_operation.ICD9_NAME" => $main_operation_ICD9_NAME]]
                            ]
                        ]
                    ]
                ]
            ];
        }
        // 主要手术编码
        $main_operation_ICD9_ID1 = !empty($fieldMap["main_operation_ICD9_ID1"]) ? $fieldMap["main_operation_ICD9_ID1"]['value'] : "";
        if ($main_operation_ICD9_ID1) {

            $params['body']['query']['bool']['must'][] = [
                'nested' => [
                    "path" => "main_operation",
                    "query" => [
                        'bool' => [
                            'must' => [
                                ["term" => ["main_operation.ICD9_ID1" => $main_operation_ICD9_ID1]]
                            ]
                        ]
                    ]
                ]
            ];
        }
        // 其他诊断名称
        $other_diagnosis_ICD10_NAME = !empty($fieldMap["other_diagnosis_ICD10_NAME"]) ? $fieldMap["other_diagnosis_ICD10_NAME"]['value'] : "";
        if ($other_diagnosis_ICD10_NAME) {

            $params['body']['query']['bool']['must'][] = [
                'nested' => [
                    "path" => "other_diagnosis",
                    "query" => [
                        'bool' => [
                            'must' => [
                                ["match_phrase" => ["other_diagnosis.ICD10_NAME" => $other_diagnosis_ICD10_NAME]]
                            ]
                        ]
                    ]
                ]
            ];
        }
        // 其他诊断编码
        $other_diagnosis_ICD10_ID1 = !empty($fieldMap["other_diagnosis_ICD10_ID1"]) ? $fieldMap["other_diagnosis_ICD10_ID1"]['value'] : "";
        if ($other_diagnosis_ICD10_ID1) {

            $params['body']['query']['bool']['must'][] = [
                'nested' => [
                    "path" => "other_diagnosis",
                    "query" => [
                        'bool' => [
                            'must' => [
                                ["term" => ["other_diagnosis.ICD10_ID1" => $other_diagnosis_ICD10_ID1]]
                            ]
                        ]
                    ]
                ]
            ];
        }
        if ($AAA28) {
            $params['body']['query']['bool']['must'][] = [
                'term' => [
                    'AAA28' => $AAA28
                ]
            ];
        }
        if ($start_time && $end_time) {
            $start_time = date('Y-m-d', strtotime($start_time)) . ' 00:00:00';
            $end_time = date('Y-m-d', strtotime($end_time)) . ' 23:59:59';
            $params['body']['query']['bool']['must'][] = [
                'range' => [
                    'AAC01' => [
                        'gte' => $start_time,
                        'lte' => $end_time,
                    ]
                ]
            ];
        } elseif ($start_time) {
            $start_time = date('Y-m-d', strtotime($start_time)) . ' 00:00:00';
            $params['body']['query']['bool']['must'][] = [
                'range' => [
                    'AAC01' => [
                        'gte' => $start_time,
                    ]
                ]
            ];
        } elseif ($end_time) {
            $end_time = date('Y-m-d', strtotime($end_time)) . ' 23:59:59';
            $params['body']['query']['bool']['must'][] = [
                'range' => [
                    'AAC01' => [
                        'gte' => '2010-01-01 00:00:00',
                        'lte' => $end_time,
                    ]
                ]
            ];
        } else {
            $params['body']['query']['bool']['must'][] = [
                'range' => [
                    'AAC01' => [
                        'gte' => '2010-01-01 00:00:00',
                        'lte' => '2099-12-31 23:59:59',
                    ]
                ]
            ];
        }

        //住院天数
        if ($date_start >= 0 && $date_end) {
            $params['body']['query']['bool']['must'][] = [
                'range' => [
                    'AAC04' => [
                        'gte' => $date_start,
                        'lte' => $date_end,
                    ]
                ]
            ];
        }
        //年龄
        if ($age_start >= 0 && $age_end) {
            $params['body']['query']['bool']['must'][] = [
                'range' => [
                    'AAA04' => [
                        'gte' => $age_start,
                        'lte' => $age_end,
                    ]
                ]
            ];
        }
        //天龄
        if ($day_start >= 0 && $day_end) {
            $params['body']['query']['bool']['must'][] = [
                'range' => [
                    'AAA40' => [
                        'gte' => $day_start,
                        'lte' => $day_end,
                    ]
                ]
            ];
        }

        //根据自定义字段生成elastic的搜索拼写
        $field_params = [];
        $bllb_array = self::$bllb_array;
        if (!empty($field)) {
            foreach ($field as $item) {
                if (empty($item['value']) && !in_array($item['key'], $bllb_array)) {
                    continue;
                }
                if ($item['select_type'] == "1") {
                    $select_type = "should";
                } else if ($item['select_type'] == 2) {
                    $select_type = "must_not";
                } else {
                    $select_type = "must";
                }

                if (in_array($item['key'], $bllb_array)) {
                    //如果empty($item['value']为空，查询相应字段是否为空
                    if ($item['type'] != 0) { //like
                        $key = '.keyword';
                    } else {
                        $key = '';
                    }
                    if (empty($item['value'])) {
                        //如果value为空，搜索字段存在且不为空的记录
                        $field_params[] = [
                            'type' => $select_type,
                            'query' => [
                                'exists' => [
                                    'field' => 'EMR_BL_BL01.' . $item['key'] . $key,
                                ],
                            ],
                            'nested_path' => 'EMR_BL_BL01',
                        ];
                    } else {
                        //数组为单个记录的搜索类型和搜索信息
                        $field_params[] = [
                            'type' => $select_type,
                            'query' => [
                                'match_phrase' => [
                                    'EMR_BL_BL01.' . $item['key'] . $key => $item['value'],
                                ],
                            ],
                            'nested_path' => 'EMR_BL_BL01',
                        ];
                    }
                } elseif ($item['key'] == "49") { //医嘱本
                    if ($item['type'] != 0) { //like
                        $key = 'YZMC.keyword';
                    } else {
                        $key = 'YZMC';
                    }
                    $field_params[] = [
                        'type' => $select_type,
                        'query' => [
                            'match_phrase' => [
                                'YZB.' . $key => $item['value'],
                            ]
                        ],
                        'nested_path' => 'YZB',
                        'key' => $key,
                    ];
                } elseif ($item['key'] == "AAC11N") { //科室
                    if ($item['value'] != "全部") {
                        $params['body']['query']['bool']['must'][] = [
                            'match_phrase' => [
                                'AAC11N' => $item['value']
                            ]
                        ];
                    }
                } elseif ($item['key'] == "FYMC") { //消费明细
                    if ($item['type'] != 0) { //like
                        $key = 'FYMC.keyword';
                    } else {
                        $key = 'FYMC';
                    }
                    $field_params[] = [
                        'type' => $select_type,
                        'query' => [
                            'match_phrase' => [
                                'Fee_detailed.' . $key => $item['value'],
                            ]
                        ],
                        'nested_path' => 'Fee_detailed',
                        'key' => $key,
                    ];
                } elseif ($item['key'] == "2000002") { //报告单
                    if ($item['type'] != 0) { //like
                        $key = 'JCMC.keyword';
                    } else {
                        $key = 'JCMC';
                    }
                    $field_params[] = [
                        'type' => $select_type,
                        'query' => [
                            'match_phrase' => [
                                'pacs_jcmc_list.' . $key => $item['value'],
                            ]
                        ],
                        'nested_path' => 'pacs_jcmc_list',
                        'key' => $key,
                    ];
                } elseif ($item['key'] == "ICD10_NAME") { //主要诊断
                    if ($item['value'] != "全部") {
                        // must
                        $params['body']['query']['bool'][$select_type][] = [
                            'match_phrase' => [
                                'ICD10_NAME' => $item['value']
                            ]
                        ];
                    }
                } elseif ($item['key'] == "ICD10_ID1") { //主要诊断编码
                    if ($item['value'] != "全部") {
                        // must
                        $params['body']['query']['bool'][$select_type][] = [
                            'match_phrase_prefix' => [
                                'ICD10_ID1' => $item['value']
                            ]
                        ];
                    }
                } else {
                    //不存在，直接continue
                    continue;
                }
            }

            $query = [];
            if (!empty($field_params)) {
                $inner_hits = [];
                if ($detail == 1) {
                    $inner_hits = [
                        'highlight' => [
                            'fields' => [
                                '*' => [
                                    "pre_tags" => "<font color='red'>",
                                    "post_tags" => "</font>",
                                ],
                            ],
                            "fragment_size" => 10000,
                            "number_of_fragments" => 0,
                        ],
                    ];
                }

                $add = false;
                foreach ($field_params as $field) {
                    $nested = [
                        'nested' => [
                            "path" => $field['nested_path'],
                            "query" => [
                                'bool' => [
                                    'must' => [
                                        $field['query']
                                    ]
                                ]
                            ],
                        ]
                    ];
                    if ($field['nested_path'] == "EMR_BL_BL01" && !$add) {
                        $nested['nested']['inner_hits'] = $inner_hits;
                        $add = true;
                    }
                    if (empty($query)) {
                        $query = $nested;
                    } else {
                        if ($field['type'] == 'must_not') {
                            $temp = [
                                'bool' => [
                                    'must' => [
                                        $query == [] ? '' : $query,
                                        [
                                            'bool' => [
                                                'must_not' => $nested,
                                            ]
                                        ]
                                    ]
                                ]
                            ];
                        } else {
                            $temp = [
                                'bool' => [
                                    $field['type'] => [
                                        $query == [] ? '' : $query,
                                        $nested,
                                    ]
                                ]
                            ];
                        }

                        $query = $temp;
                    }
                }
            }

            //如果fields存在，则和外层的查询放在一起
            if (!empty($query)) {
                if (!empty($params['body']['query']['bool']['must'])) {
                    $params['body']['query']['bool']['must'][] = $query;
                } else {
                    $params['body']['query'] = $query;
                }
            }

            $params['body']['track_total_hits'] = true;

            // 排序
            if (!empty($map['sort'])) {
                $params['body']['sort'] = [[$map['sort'][0] => $map['sort'][1]]];
            } else {
                $params['body']['sort'] = [['AAC01' => 'desc']];
            }

            $ret = app('es')->search($params);
        }

        return !empty($ret) ? $ret : [];
    }

    /**
     * 病案搜索 - 高级搜索 - 详情数据（医嘱、费用明细、报告单）
     * @param $ZYH
     * @param $AAA28
     * @param $AAB01
     * @param $AAC01
     * @param $field
     * @return array[]
     */
    public static function getSsInfoData($ZYH, $AAA28, $AAB01, $AAC01, $field)
    {
        $fee_params = [];
        $yzb_params = [];
        $pacs_params = [];
        foreach ($field as $item) {
            if (empty($item['value'])) {
                continue;
            }

            if ($item['key'] == "49") {
                // 医嘱
                if ($item['select_type'] === 1) {
                    $yzb_params['should'][] = [
                        'match_phrase' => ['YZMC' => $item['value']]
                    ];
                } elseif ($item['select_type'] === 2) {
                    $yzb_params['must_not'][] = [
                        'match_phrase' => ['YZMC' => $item['value']]
                    ];
                } else {
                    $yzb_params['must'][] = [
                        'match_phrase' => ['YZMC' => $item['value']]
                    ];
                    if ($item['value'] == 'mr' || $item['value'] == 'MR') {
                        $pacs_params['should'][] = [
                            'match_phrase' => ['JCMC' => 'MRI']
                        ];
                    }
                }
            } elseif ($item['key'] == "FYMC") {
                // 费用明细
                if ($item['select_type'] === 1) {
                    $fee_params['should'][] = [
                        'match_phrase' => ['FYMC' => $item['value']]
                    ];
                } elseif ($item['select_type'] === 2) {
                    $fee_params['must_not'][] = [
                        'match_phrase' => ['FYMC' => $item['value']]
                    ];
                } else {
                    $fee_params['must'][] = [
                        'match_phrase' => ['FYMC' => $item['value']]
                    ];
                    if ($item['value'] == 'mr' || $item['value'] == 'MR') {
                        $pacs_params['should'][] = [
                            'match_phrase' => ['JCMC' => 'MRI']
                        ];
                    }
                }
            } elseif ($item['key'] == "2000002") {
                // 报告单
                if ($item['select_type'] === 1) {
                    $pacs_params['should'][] = [
                        'match_phrase' => ['JCMC' => $item['value']]
                    ];
                } elseif ($item['select_type'] === 2) {
                    $pacs_params['must_not'][] = [
                        'match_phrase' => ['JCMC' => $item['value']]
                    ];
                } else {
                    $pacs_params['must'][] = [
                        'match_phrase' => ['JCMC' => $item['value']]
                    ];
                    if ($item['value'] == 'mr' || $item['value'] == 'MR') {
                        $pacs_params['should'][] = [
                            'match_phrase' => ['JCMC' => 'MRI']
                        ];
                    }
                }
            }
        }

        // 医嘱
        $yzbList = [];
        if ($yzb_params) {
            $yzb_params[] = ['range' => ['id' => ['gte' => 1]]];
            $depList = config('dictionaries.deportment');
            $yzb_params['must'][] = ['term' => ['ZYH' => $ZYH]];

            $yzbService = new ElasticsearchService('yzb_2023');
            $must = $yzb_params['must'];
            $mustNot = !empty($yzb_params['must_not']) ? $yzb_params['must_not'] : [];
            $should = !empty($yzb_params['should']) ? $yzb_params['should'] : [];
            if ($should) {
                //                $should[] = ['range' => ['id' => ['gte' => 1]]];
                $params = $yzbService->clearMust()
                    ->queryByMustBatch($must)
                    ->queryByMustNotBatch($mustNot)
                    ->queryByShouldBatch($should)
                    ->minimumShouldMatch()
                    ->source(['ZYH', 'YZMC', 'BRKS', 'KZSJ', 'YZQX'])
                    ->paginate(1, 1000)
                    ->getParams();
                $restful = app('es')->search($params);
                $yzbData = $yzbService->getDataByEs($restful);
                if (!empty($yzbData[0])) {
                    foreach ($yzbData[0] as $yz) {
                        $YZQX = $yz['YZQX'] == 1 ? '长期医嘱' : '临时医嘱';
                        $BRKS = !empty($depList[$yz['BRKS']]) ? $depList[$yz['BRKS']] : $yz['BRKS'];
                        $yzbList[] = [
                            'ZYH' => $yz['ZYH'],
                            'YZMC' => $yz['YZMC'],
                            'BRKS' => $BRKS,
                            'KZSJ' => $yz['KZSJ'],
                            'ZJE' => $YZQX,
                        ];
                    }
                }
            } else {
                $params = $yzbService->clearMust()
                    ->queryByMustBatch($must)
                    ->queryByMustNotBatch($mustNot)
                    ->source(['ZYH', 'YZMC', 'BRKS', 'KZSJ', 'YZQX'])
                    ->paginate(1, 1000)
                    ->getParams();
                $restful = app('es')->search($params);
                $yzbData = $yzbService->getDataByEs($restful);
                if (!empty($yzbData[0])) {
                    foreach ($yzbData[0] as $yz) {
                        $YZQX = $yz['YZQX'] == 1 ? '长期医嘱' : '临时医嘱';
                        $BRKS = !empty($depList[$yz['BRKS']]) ? $depList[$yz['BRKS']] : $yz['BRKS'];
                        $yzbList[] = [
                            'ZYH' => $yz['ZYH'],
                            'YZMC' => $yz['YZMC'],
                            'BRKS' => $BRKS,
                            'KZSJ' => $yz['KZSJ'],
                            'ZJE' => $YZQX,
                        ];
                    }
                }
            }
        }

        // 费用明细
        $feeList = [];
        if ($fee_params) {
            $fee_params['must'][] = ['term' => ['MED_REC_ID' => $ZYH]];

            $feeService = new ElasticsearchService('fee_detailed');
            $must = $fee_params['must'];
            $mustNot = !empty($fee_params['must_not']) ? $fee_params['must_not'] : [];
            $should = !empty($fee_params['should']) ? $fee_params['should'] : [];
            if ($should) {
                //                $should[] = ['range' => ['id' => ['gte' => 1]]];
                $params = $feeService->clearMust()
                    ->queryByMustBatch($must)
                    ->queryByMustNotBatch($mustNot)
                    ->queryByShouldBatch($should)
                    ->minimumShouldMatch()
                    ->source(['MED_REC_ID', 'FYMC', 'JFRQ', 'FYSL'])
                    ->paginate(1, 1000)
                    ->getParams();
                $restful = app('es')->search($params);
                $feeData = $feeService->getDataByEs($restful);
                if (!empty($feeData[0])) {
                    foreach ($feeData[0] as $fee) {
                        $feeList[] = [
                            'MED_REC_ID' => $fee['MED_REC_ID'],
                            'FYMC' => [$fee['FYMC']],
                            'JFRQ' => $fee['JFRQ'],
                            'FYSL' => $fee['FYSL'],
                        ];
                    }
                }
            } else {
                $params = $feeService->clearMust()
                    ->queryByMustBatch($must)
                    ->queryByMustNotBatch($mustNot)
                    ->source(['MED_REC_ID', 'FYMC', 'JFRQ', 'FYSL'])
                    ->paginate(1, 1000)
                    ->getParams();
                $restful = app('es')->search($params);
                $feeData = $feeService->getDataByEs($restful);
                if (!empty($feeData[0])) {
                    foreach ($feeData[0] as $fee) {
                        $feeList[] = [
                            'MED_REC_ID' => $fee['MED_REC_ID'],
                            'FYMC' => [$fee['FYMC']],
                            'JFRQ' => $fee['JFRQ'],
                            'FYSL' => $fee['FYSL'],
                        ];
                    }
                }
            }
        }

        // 报告单
        $pacsData = [];
        if ($pacs_params) {
            $pacs_params['must'][] = ['term' => ['JZLSH' => $AAA28]];
            $pacs_params['must'][] = ['range' => ['KDSJ' => ['gte' => $AAB01, 'lte' => $AAC01]]];

            $pageService = new ElasticsearchService('pacs');
            $must = $pacs_params['must'];
            $mustNot = !empty($pacs_params['must_not']) ? $pacs_params['must_not'] : [];
            $should = !empty($pacs_params['should']) ? $pacs_params['should'] : [];
            if (!empty($should)) {
                //                $should[] = ['range' => ['id' => ['gte' => 1]]];
                $params = $pageService->clearMust()
                    ->queryByMustBatch($must)
                    ->queryByMustNotBatch($mustNot)
                    ->queryByShouldBatch($should)
                    ->minimumShouldMatch()
                    ->source(['JZLSH', 'JCMC', 'BGSJ'])
                    ->paginate(1, 1000)
                    ->getParams();
                $restful = app('es')->search($params);
                $pacsData = $pageService->getDataByEs($restful);
                $pacsData = $pacsData[0];
            } else {
                $params = $pageService->clearMust()
                    ->queryByMustBatch($must)
                    ->queryByMustNotBatch($mustNot)
                    ->source(['JZLSH', 'JCMC', 'BGSJ'])
                    ->paginate(1, 1000)
                    ->getParams();
                $restful = app('es')->search($params);
                $pacsData = $pageService->getDataByEs($restful);
                $pacsData = $pacsData[0];
            }
        }

        return ['yzb' => $yzbList, 'fee' => $feeList, 'pacs' => $pacsData];
    }
}
