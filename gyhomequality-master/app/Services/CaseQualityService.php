<?php

namespace App\Services;

use App\Model\CaseQuality;
use App\Model\Error;
use App\Model\OtherDiagnosis;
use App\Model\RuleWordMap;
use Illuminate\Support\Facades\DB;
use App\Model\DepartmentData;
use App\Model\CoderData;
use App\Model\ErrorData;
use App\Model\PatientScore;
use App\Model\IndicationsData;
use App\Model\AttendingGroupData;
use App\Model\HospitalData;
use App\Model\PatientInfo;
use App\Services\MedicalRecordService;

class CaseQualityService
{

    /**
     * 获取病例数量
     *
     * @param $queryCond
     * @return int
     */
    public static function getCaseTotal($queryCond)
    {
        // 获取病例数量
        $sql = 'SELECT count(1) as nums from patient_info as pi left join (select t2.JZHM,t2.is_defect from (SELECT MAX(BLBH) as BLBH FROM `EMR_BL_BL01` GROUP BY JZHM) as t1 left join EMR_BL_BL01 as t2 on t1.BLBH=t2.BLBH) as t3 on pi.MED_REC_ID=t3.JZHM';
        $whereCase = ' where pi.AAC01 > "' . $queryCond->start_time . '" and pi.AAC01 < "' . $queryCond->end_time . ' 23:59:59"';
        $caseTotal = DB::selectOne($sql . $whereCase)->nums;

        DebugItemsService::getInstance()->putDebugItem('病例数量', $sql . $whereCase);

        return $caseTotal;
    }

    /**
     * 获取病例数量
     *
     * @param $queryCond
     * @return int
     */
    public static function getCaseTotalByEs($queryCond)
    {
        $count = PatientInfo::query()->whereBetween('AAC01',[
            $queryCond->start_time . ' 00:00:00',
            $queryCond->end_time . ' 23:59:59'
        ])->count();

        return $count;
    }

    /**
     * 获取缺陷病案数
     *
     * @param $queryCond
     * @return int
     */
    public static function getDefectCaseTotal($queryCond)
    {
        return CaseQuality::query()
            ->join('patient_info','case_quality.JZHM','=','patient_info.MED_REC_ID')
            ->when($queryCond->start_time && $queryCond->end_time,function($query)use($queryCond){
                return $query->whereBetween('patient_info.AAC01',[
                    $queryCond->start_time . ' 00:00:00',
                    $queryCond->end_time . ' 23:59:59'
                ]);
            })
            ->whereNotNull('patient_info.score')
            ->count();
        /**
         * 废弃
         */
//        $piService = new ElasticsearchService('patient_info');
//        $must = [
//            [
//                'range' => [
//                    "AAC01" => [
//                        'gt' => $queryCond->start_time . ' 00:00:00',
//                        'lt' => $queryCond->end_time . ' 23:59:59',
//                    ]
//                ]
//            ],
//            [
//                'term' => [
//                    "is_defect" => 1
//                ]
//            ]
//        ];
//        $params = $piService->clearMust()->queryByMustBatch($must)->trackTotalHits()->getParams();
//        $mzRes = app('es')->search($params);
//        $mzRes = $piService->getDataByEs($mzRes);
//        return !empty($mzRes[1]) ? $mzRes[1] : 0;
    }


    /**
     * 获取科室的病例数量
     *
     * @param $queryCond
     * @return int
     */
    public static function getDepartmentCases($queryCond)
    {
        $piService = new ElasticsearchService('patient_info');
        $must = [
            'range' => [
                "AAC01" => [
                    'gt' => $queryCond->start_time . ' 00:00:00',
                    'lt' => $queryCond->end_time . ' 23:59:59',
                ]
            ]
        ];
        $aggs = [
            'aac11n' => [
                'terms' => [
                    "field" => 'AAC11N',
                    "size" => 10,
                ]
            ]
        ];
        $params = $piService->clearMust()->queryByMust($must)->aggs($aggs)->paginate(1, 0)->getParams();
        $mzRes = app('es')->search($params);
        $mzRes = $piService->getDataByEs($mzRes);
        if (empty($mzRes[2]) || empty($mzRes[2]['aac11n']) || empty($mzRes[2]['aac11n']['buckets'])) {
            return [];
        }

        return $mzRes[2]['aac11n']['buckets'];

        // 获取病例数量
//        $sql = 'SELECT AAC11N,count(1) as nums from patient_info as pi left join (select t2.JZHM,t2.is_defect from (SELECT MAX(BLBH) as BLBH FROM `EMR_BL_BL01` GROUP BY JZHM) as t1 left join EMR_BL_BL01 as t2 on t1.BLBH=t2.BLBH) as t3 on pi.MED_REC_ID=t3.JZHM';
//        $whereCase = ' where pi.AAC01 > "' . $queryCond->start_time . '" and pi.AAC01 < "' . $queryCond->end_time . ' 23:59:59"';
//        $others = ' group by AAC11N ';
//        $cases = DB::select($sql . $whereCase . $others);
//
//        DebugItemsService::getInstance()->putDebugItem('科室的病例数量', $sql . $whereCase . $others);
//
//        return $cases;
    }

    /**
     * 获取科室的缺陷病案数
     *
     * @param $queryCond
     * @return int
     */
    public static function getDepartmentDefectCases($queryCond, $limit = 0)
    {
        $piService = new ElasticsearchService('patient_info');
        $must = [
            [
                'range' => [
                    "AAC01" => [
                        'gt' => $queryCond->start_time . ' 00:00:00',
                        'lt' => $queryCond->end_time . ' 23:59:59',
                    ]
                ]
            ],
            [
                'term' => [
                    "is_defect" => 1
                ]
            ]
        ];
        $aggs = [
            'aac11n' => [
                'terms' => [
                    "field" => 'AAC11N',
                    "size" => 2000,
                ]
            ]
        ];
        $params = $piService->clearMust()->queryByMustBatch($must)->paginate(1, 0)->aggs($aggs)->getParams();
        $mzRes = app('es')->search($params);
        $mzRes = $piService->getDataByEs($mzRes);
        if (empty($mzRes[2]) || empty($mzRes[2]['aac11n']) || empty($mzRes[2]['aac11n']['buckets'])) {
            return [];
        }
        return $mzRes[2]['aac11n']['buckets'];
        // 获取病例数量
//        $sql = 'SELECT AAC11N,count(1) as nums from patient_info as pi inner join (select t2.JZHM,t2.is_defect from (SELECT MAX(BLBH) as BLBH FROM `EMR_BL_BL01` where is_defect=1 GROUP BY JZHM) as t1 left join EMR_BL_BL01 as t2 on t1.BLBH=t2.BLBH) as t3 on pi.MED_REC_ID=t3.JZHM';
//        $whereCase = ' where pi.AAC01 > "' . $queryCond->start_time . '" and pi.AAC01 < "' . $queryCond->end_time . ' 23:59:59"';
//        $others = ' group by AAC11N  order by nums asc';
//        if ($limit > 0) {
//            $others .= ' LIMIT ' . $limit;
//        }
//        $cases = DB::select($sql . $whereCase . $others);
//
//        DebugItemsService::getInstance()->putDebugItem('科室的缺陷病案数', $sql . $whereCase . $others);
//
//        return $cases;
    }

    /**
     * 首页质控(病案室)
     */
    public static function caseQualityBas($AAA28)
    {
        echo $AAA28 . "\n";
        echo '首页质控(病案室)-开始:' . date('Y-m-d H:i:s') . "\r\n";

        // 获取病例数据
        $data = MedicalRecordService::getData($AAA28);
        if (empty($data['AAC01'])) {
            return false;
        }

        if (empty($data['AEE04_CODE'])) {
            $data['AEE04_CODE'] = '';
        }
        $orderId = $data['AEE04_CODE'];

        $errorRuleWhere = [
            ['status', '=', 0]
        ];

        // 获取年月
        $year = date("Y", strtotime($data['AAC01']));
        $month = date("m", strtotime($data['AAC01']));

        // 质控处理
        $errorRuleWhere = [['status', '=', 0]];
        $rule = ErrorRuleService::getRuleList($errorRuleWhere);
        $insetData = [];

        $blHomeService = new BlHomeQualityService();
        foreach ($rule as $item) {
            if ($item['status'] == 1) {
                continue;
            }

            $errorNotice = '';
            $result = false;
            if (in_array($item['id'], BlHomeQualityService::$ruleAct)) {
                $result = $blHomeService->homeQuality($data, $item);
                if ($item['id'] == 1452) {
                    $result = $blHomeService->rule1452($data, $item);
                }
                if ($result) {
                    if ($item['id'] == 1458) {
                        $item['desc'] = $result;
                    }
                }
            } elseif ($item['rule'] == 'required') {
                $result = self::required($data, $item);
            } elseif ($item['rule'] == 'age') {
                $result = self::age($data, $item);
            } elseif ($item['rule'] == 'gender') {
                $result = self::gender($data, $item);
            } elseif ($item['rule'] == 'special') {
                if (preg_match('[@_!#\$%\^&\*\(\)<>\?/|}\{~:]', $data[$item['auth']])) {
                    $result = true;
                }
            } elseif ($item['rule'] == 'code') {
                if (isset($data[$item['auth']]) && $data[$item['auth']] != '') {
                    if ($item['relation_rule'] == '*') {
                        if (preg_match('[\*]', $data[$item['auth']])) {
                            $result = true;
                        }
                    } elseif ($item['relation_rule'] == 'M') {
                        if (preg_match('/^M?$/', $data[$item['auth']])) {
                            $result = true;
                        }
                    } elseif ($item['relation_rule'] == 'no_die') {
                        $no_die = explode('|', $item['relation']);
                        if (in_array($data['ABC01C'], $no_die)) {
                            $result = true;
                        }
                    } else {
                        $relation_rule = explode('|', $item['relation_rule']);
                        $relation_rule1 = explode(':', $relation_rule[0]);
                        if ($relation_rule1[0] == 'equ') {
                            $other = OtherDiagnosis::query()
                                ->where('AAA28', $data['MED_REC_ID'])
                                ->where('ICD10_ID1', $relation_rule1[1])
                                ->first();
                            if (isset($relation_rule[1]) && $other) {
                                $relation_rule2 = explode(':', $relation_rule[1]);
                                if ($relation_rule2[0] == '!empty') {
                                    if ($data[$relation_rule2[1]] == '') {
                                        $result = true;
                                    }
                                }
                            }
                        }
                    }
                }
            } elseif ($item['rule'] == 'condition') {
                $result = self::condition($data, $item);
            } elseif ($item['rule'] == 'or') {
                $result = self::orValue($data, $item);
            } elseif ($item['rule'] == 'main_no_check') {
                $result = self::mainNoCheck($data, $item, $errorNotice);
            } elseif ($item['rule'] == 'operation') {
                $result = self::operation($data, $item, $errorNotice);
            } elseif ($item['rule'] == 'operationName') {
                $result = self::operationName($data, $item, $errorNotice);
            } elseif ($item['rule'] == 'huxiji') {
                $result = self::huxiji($data, $item, $errorNotice);
            } elseif ($item['rule'] == 'hospital') {
                $result = self::hospital($data, $item, $errorNotice);
            } elseif ($item['rule'] == 'time') {
                $result = self::timeRole($data, $item, $errorNotice);
            } elseif ($item['rule'] == '12-ICD10_ID1') {
                $result = self::ICD10_ID1_12($data, $item);
            } elseif ($item['rule'] == 'ICD10_ID1-not') {
                $result = self::isNotICD10ID1($data, $item);
            } elseif ($item['rule'] == 'ICD10_ID1-not-v2') {
                $ICD10_ID1 = self::getDiagnosis($data, 'main');

                if (!empty($ICD10_ID1)) {
                    $setKeyword = config('confAdmin.not_main_ICD10');
                    $res = array_intersect(array_column($ICD10_ID1, 'ICD10_ID1'), array_keys($setKeyword));
                    if ($res) {
                        $item['desc'] = "【" . $setKeyword[$res[0]] . "】主要诊断不入组，请核实";
                        $result = true;
                    }
                }
            } elseif ($item['rule'] == 'ICD9_ID1-not-v2') {

                $operation = self::getOperation($data, 'main');

                if (!empty($operation)) {
                    $setKeyword = config('confAdmin.not_main_ICD9');
                    $res = array_intersect(array_column($operation, 'ICD9_ID1'), array_keys($setKeyword));
                    if ($res) {
                        $item['desc'] = "【" . $setKeyword[$res[0]] . "】主要手术不入组，请核对";
                        $result = true;
                    }
                }
            } elseif ($item['rule'] == 'ryqk_required') {
                $mains = self::getDiagnosis($data, 'main');
                $others = self::getDiagnosis($data, 'other');

                if (!empty($mains['ICD10_NAME']) && empty($mains[$item['auth']])) {
                    $result = true;
                }

                foreach ($others as $other) {
                    if (!empty($other['ICD10_NAME']) && empty($other[$item['auth']])) {
                        $result = true;
                    }
                }
            } elseif ($item['rule'] == 'cyqk_required') {
                $mains = self::getDiagnosis($data, 'main');
                $others = self::getDiagnosis($data, 'other');

                if (!empty($mains['ICD10_NAME']) && empty($mains[$item['auth']])) {
                    $result = true;
                }

                foreach ($others as $other) {
                    if (!empty($other['ICD10_NAME']) && empty($other[$item['auth']])) {
                        $result = true;
                    }
                }
            } elseif ($item['rule'] == 'I50.9_contain') {
                $mains = self::getDiagnosis($data, 'main');

                if (!empty($mains['ICD10_ID1'])) {
                    $substring = substr($mains['ICD10_ID1'], 0, 5);

                    if ($substring == 'i50.9') {
                        $result = true;
                    }
                }
            } elseif ($item['rule'] == 'M801_unique') {
                $ABF01C = $data['ABF01C'] ?? null;
                $mains = self::getDiagnosis($data, 'main');

                if (!empty($ABF01C)) {
                    $substring = substr($ABF01C, 0, 4);
                    $substring2 = substr($ABF01C, -2);
                    $subArray = ['M801', 'M802', 'M803', 'M804', 'M805', 'M806', 'M807', 'M808'];
                    $subArray2 = ['C44', 'C46.0', 'C51', 'C52', 'C53', 'C60', 'C63.2'];

                    if (in_array($substring, $subArray) && $substring2 == '/3' && in_array($mains['ICD10_ID1'], $subArray2)) {
                        $result = true;
                    }
                }
            } elseif ($item['rule'] == 'M8050_unique') {
                $ABF01C = $data['ABF01C'] ?? null;
                $mains = self::getDiagnosis($data, 'main');

                if (!empty($ABF01C)) {
                    $substring = substr($ABF01C, 0, 5);
                    $substring2 = substr($ABF01C, -2);
                    $subArray = ['M8050', 'M8051', 'M8052', 'M8053', 'M8060'];
                    $subArray2 = ['D23', 'D10.0', 'D12.9', 'D26.0', 'D28.0', 'D28.1', 'D29.0', 'D29.4'];

                    if (in_array($substring, $subArray) && $substring2 == '/0' && in_array($mains['ICD10_ID1'], $subArray2)) {
                        $result = true;
                    }
                }
            } elseif ($item['rule'] == 'M808_unique') {
                $ABF01C = $data['ABF01C'] ?? null;
                $mains = self::getDiagnosis($data, 'main');

                if (!empty($ABF01C)) {
                    $substring = substr($ABF01C, 0, 4);
                    $substring2 = substr($ABF01C, -2);
                    $subArray = ['M801', 'M802', 'M803', 'M804', 'M805', 'M806', 'M807', 'M808'];
                    $subArray2 = ['C44', 'C46.0', 'C51', 'C52', 'C60', 'C63.2'];

                    if (in_array($substring, $subArray) && $substring2 == '/3' && in_array($mains['ICD10_ID1'], $subArray2)) {
                        $result = true;
                    }
                }
            } elseif ($item['rule'] == 'M8053_unique') {
                $ABF01C = $data['ABF01C'] ?? null;
                $mains = self::getDiagnosis($data, 'main');

                if (!empty($ABF01C)) {
                    $substring = substr($ABF01C, 0, 5);
                    $substring2 = substr($ABF01C, -2);
                    $subArray = ['M8050', 'M8051', 'M8052', 'M8053', 'M8060'];
                    $subArray2 = ['D23', 'D10.0', 'D12.9', 'D28.0', 'D28.1', 'D29.0', 'D29.4'];

                    if (in_array($substring, $subArray) && $substring2 == '/3' && in_array($mains['ICD10_ID1'], $subArray2)) {
                        $result = true;
                    }
                }
            } elseif ($item['rule'] == 'M918_unique') {
                $ABF01C = $data['ABF01C'] ?? null;
                $mains = self::getDiagnosis($data, 'main');

                if (!empty($ABF01C)) {
                    $substring = substr($ABF01C, 0, 4);
                    $substring2 = substr($ABF01C, -2);
                    $substring3 = substr($ABF01C, 0, 5);
                    $subArray = ['M918','M919','M920','M921','M922','M923','M924','M925','M926','M927','M928','M929','M930','M931','M932','M933','M934'];

                    if (!in_array($substring, $subArray) && $substring3 !== 'M8812' && $substring2 == '/3' && ($mains['ICD10_ID1'] == 'C40' || $mains['ICD10_ID1'] == 'C41')) {
                        $result = true;
                    }
                }
            } elseif ($item['rule'] == 'C71_required') {
                $mains = self::getDiagnosis($data, 'main');

                if (!empty($mains['ICD10_ID1'])) {
                    $substring = substr($mains['ICD10_ID1'], 0, 3);
                    $subArray = ['C91', 'C92', 'C93', 'C94', 'C95', 'K70', 'K71', 'K72', 'K73', 'K74', 'K75', 'K76', 'K77', 'C71', 'C78', 'C79', 'G20', 'E10', 'E11', 'E12', 'E13', 'E14'];

                    if (in_array($substring, $subArray)) {
                        $result = true;
                    }
                }
            } else {
                if (is_string($item) === false) {
                    continue;
                }
                $expRule = explode(':', $item);
                switch ($expRule[0]) {
                    case 'min';
                        $result = $data[$item['auth']] < $expRule[1] ? 1 : 0;
                        break;
                    case 'max';
                        $result = $data[$item['auth']] > $expRule[1] ? 1 : 0;
                        break;
                    case 'equ';
                        $result = $data[$item['auth']] != $expRule[1] ? 1 : 0;
                        break;
                    case 'nequ';
                        $result = $data[$item['auth']] == $expRule[1] ? 1 : 0;
                        break;
                    case 'len';
                        $result = strlen($data[$item['auth']]) > $expRule[1] ? 1 : 0;
                        break;
                    case 'in';
                        $aa = config('dictionaries.' . $expRule[1]);
                        $result = isset($aa[$data[$expRule[1]]]) ? 1 : 0;
                        break;
                    default;
                        $result = 0;
                        break;
                }
            }

            if ($result) {

                $diagnosis = self::getDiagnosis($data, 'main');
                $operation = self::getOperation($data, 'main');
                $insetData[] = [
                    'year' => $year,
                    'month' => $month,
                    'ZYH' => $data['MED_REC_ID'],
                    'desc' => trim($errorNotice, '-') . $item['desc'],
                    'error_field' => $item['auth'],
                    'error_name' => $item['field'],
                    'level' => $item['level'],
                    'error_rule' => $item['id'],
                    'type' => $item['type'],
                    'coder_id' => $data['AEE04_CODE'],
                    'AAC11C' => $data['AAC11C'] ?? '',
                    'down' => $item['down'] ?? '',
                    'category' => $item['category'] ?? '',
                    'error_type' => $item['error_type'] ?? '',
                    'is_bas' => $item['is_bas'] ?? 0,
                    'source' => $data['source'] ?? '',
                    'AAA28' => $data['AAA28'] ?? '',
                    'AEE04' => $data['AEE04'] ?? '',
                    'AEE08' => $data['AEE08'] ?? '',
                    'AAC01' => $data['AAC01'] ?? '',
                    'AAC03' => $data['AAC03'] ?? '',
                    'AAA01' => $data['AAA01'] ?? '',
                    'AAC11N' => $data['AAC11N'] ?? '',
                    'ICD10_ID1' => $diagnosis[0]['ICD10_ID1'] ?? '',
                    'ICD10_NAME' => $diagnosis[0]['ICD10_NAME'] ?? '',
                    'ICD9_NAME' => $operation[0]['ICD9_NAME'] ?? '',
                    'ICD9_ID1' => $operation[0]['ICD9_ID1'] ?? '',
                ];
            }
        }
        //type = 3 的是手术名称遗漏
        if (!empty($insetData)) {
            // 将所有问题改为已修改
            //Error::query()->where('ZYH', $AAA28)->update(['is_edit' => 1]);
            // 1. 先删除该病例的所有错误记录
            Error::query()->where('ZYH', '=', $AAA28)->delete();
            foreach ($insetData as $key => $errorInfo) {
                $errorInfo['is_edit'] = 2;
                $errorInfo['AAA28'] = $data['AAA28'] ?? '';
                $errorInfo['hospital_name'] = $data['hospital_name'] ?? '';

                Error::query()->updateOrInsert(
                    ['error_rule' => $errorInfo['error_rule'], 'ZYH' => $errorInfo['ZYH']],
                    $errorInfo
                );
            }
        }

        // 如果有错误数据，进行统计
        if (!empty($insetData)) {
            DB::transaction(function () use ($data, $insetData, $year, $month, $AAA28) {
                // 1. 插入错误数据
//                Error::query()->insert($insetData);

                // 2. 计算统计数据
                $count = count($insetData);
                $down = self::getScore($insetData); // 使用 Count 类的计分逻辑
                $l = 0;
                $g = 0;
                $b = 0;
                $A = false;

                foreach ($insetData as $item) {
                    if ($item['error_type'] == 0)
                        $l += 1;
                    if ($item['error_type'] == 1)
                        $g += 1;
                    if ($item['error_type'] == 2)
                        $b += 1;
                    if ($item['category'] == 0)
                        $A = true;
                }

                // 3. 科室统计
                self::ks($data, $count, $year, $month, $down);

                // 4. 主诊组统计
                self::zzz($data, $count, $l, $g, $b, $year, $month);

                // 5. 主治医师统计
                self::zzys($data, $count, $l, $g, $b, $year, $month);

                // 6. 住院医师统计
                self::zyys($data, $count, $l, $g, $b, $year, $month);

                // 7. 编码员统计
                self::bmy($data, $count, $year, $month);

                // 8. 总体统计信息
                $level = self::count($count, $year, $month, $down, $A, $data['hospital_name'], $AAA28);

                // 9. 缺陷问题统计
//                self::errorData($insetData, $year, $month, $data['hospital_name']);

                // 10. 病案分数记录
                PatientScore::query()->updateOrInsert(
                    ['ZYH' => $AAA28],
                    [
                        'score' => $down,
                        'is_error' => $down == 100 ? 0 : 1,
                        'level' => $level
                    ]
                );

            });
        } else {
            // 无缺陷病例统计
            self::insertNotError($AAA28, $data['hospital_name'], $year, $month);
        }

        echo '首页质控(病案室)-结束:' . date('Y-M-D H:i:s') . "\r\n";

        return $insetData;
    }

    public static function insertNotError($AAA28, $hospitalName, $year, $month)
    {
        $down = 100;//分数
        // 先获取当前病例是否已统计过
        $patientScore = PatientScore::query()
            ->where('ZYH', $AAA28)
            ->first();

        // 如果已经统计过且分数是100，说明之前就是无缺陷病例，不需要重复统计
        if ($patientScore && $patientScore->score == 100) {
            return;
        }

        $DepartmentQuery = \App\Model\Count::query();
        $Department = $DepartmentQuery
            ->where('hospital_name', '=', $hospitalName)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($Department) {
            // 如果存在记录，只更新不累加
            $DepartmentQuery
                ->where('id', $Department->id)
                ->update([
                    'total_medical' => $Department->total_medical + 1,
                    'total_score' => $Department->total_score + $down,
                    'total_score_qa' => $Department->total_score_qa + $down
                ]);
        } else {
            // 新记录
            $agd = [
                'hospital_name' => $hospitalName,
                'total_medical' => 1,
                'cumulative_medical' => 0,
                'error_medical' => 0,
                'total_score' => $down,
                'total_score_qa' => $down,
                'year' => $year,
                'month' => $month,
                'created_at' => $year . '-' . $month . '-' . '01 00:00:00',
            ];
            $DepartmentQuery->insert($agd);
        }
        //患者所得分数更新或者插入
        PatientScore::query()->updateOrInsert(['ZYH' => $AAA28], [
            'ZYH' => $AAA28,
            'score' => $down,
            'is_error' => 0,
            'level' => 0
        ]);
    }

    public static function ICD10_ID1_12($data = [], $item = [])
    {
        if ($data['AAA04'] > 12) {
            return [];
        }

        $ICD10_ID1 = array_column($data['diagnosis'], 'ICD10_ID1');
        $wordMap = RuleWordMap::query()->where(['id' => $item['relation_rule']])->first()->toArray();
        $res = array_intersect($ICD10_ID1, explode(',', $wordMap['keyword']));
        if (!$res) {
            return false;
        }
        return true;
    }

    public static function isNotICD10ID1($data = [], $item = [])
    {
        $ICD10_ID1 = self::getDiagnosis($data, 'main');

        $setKeyword = explode(',', $item['relation_rule']);
        if (empty($ICD10_ID1) || empty($setKeyword)) {
            return false;
        }
        $res = array_intersect(array_column($ICD10_ID1, 'ICD10_ID1'), $setKeyword);
        if ($res) {
            return true;
        }
        return false;
    }


    public static function required($data, $item)
    {
        $auth = self::getAuth($data, $item['auth']);
        if ($auth == '') {
            return true;
        } else {
            $expRule = explode(':', $item['relation_rule']);
            if (empty($expRule)) {
                return false;
            }
            switch ($expRule[0]) {
                case 'min';
                    $result = $auth < $expRule[1] ? 1 : 0;
                    break;
                case 'max';
                    if (is_numeric($expRule[1])) {
                        $result = $auth > $expRule[1] ? 1 : 0;
                    } else {
                        $result = $auth > $data[$expRule[1]] ? 1 : 0;
                    }
                    break;
                case 'equ';
                    $result = $auth == $expRule[1] ? 1 : 0;
                    break;
                case 'nequ';
                    $result = $auth != $expRule[1] ? 1 : 0;
                    break;
                case 'len';
                    $result = strlen($auth) <= $expRule[1] ? 1 : 0;
                    break;
                case 'in';
                    $aa = config('dictionaries.' . $expRule[1]);
                    $result = isset($aa[$auth]) ? 1 : 0;
                    break;
                case 'in_value';
                    $values = explode(',', $expRule[1]);
                    $result = in_array($auth, $values) ? 0 : 1;
                    break;
                case 'unique';
                    // 校验其他诊断编码是否有重复的
                    $result = count($auth) == count(array_unique($auth)) ? 1 : 0;
                    break;
                default;
                    $result = 1;
                    break;
            }
            if ($result) {
                return false;
            }
            return true;
        }
    }

    /**
     * @param $data
     * @param $item
     * @return bool
     * 主要诊断编码校验
     */
    public static function mainNoCheck($data, $item, &$errorNotice = "")
    {
        $auth = self::getAuth($data, $item['auth'], 'main');

        if ($auth == '' || empty($auth[0])) {
            return false;
        } else {
            $no = $auth[0]; // 主要诊断编号

            $result = false;
            $expRule = explode(':', $item['relation_rule']);
            if (empty($expRule)) {
                return false;
            }
            if (isset($expRule[1])) {
                $roleValue = explode(',', $expRule[1]); // 规则判断的值
            }
            switch ($expRule[0]) {
                case 'no_in';
                    if ($roleValue && $no) {
                        $result = in_array($no, $roleValue) ? true : false;
                        $errorNotice = $no . '-';
                    }
                    break;
                case 'not_in_other_no';
                    $otherNo = $auth;
                    unset($otherNo[0]);
                    $result = in_array($no, $otherNo) ? true : false;
                    $errorNotice = $no;
                    break;
                case 'exist';

                    // 如果所有诊断编码超过1个，并且规定的编码都在诊断编码内则给出提示
                    $flagNums = 0;
                    if (count($auth) > 1) {
                        $authStr = implode(',', $auth);
                        foreach ($roleValue as $item) {
                            if (strpos($authStr, $item) !== false) {
                                $errorNotice .= $item . '-';
                                $flagNums++;
                            }
                        }
                    }
                    if ($flagNums >= 2) {
                        $result = true;
                    } else {
                        $result = false;
                    }
                    break;
                case 'name_exist';
                    // 疾病国临2.0库中要求合并疾病名称
                    if (count($auth) > 1) {
                        $resArr = array_intersect($roleValue, $auth);
                        $result = count($resArr) == count($roleValue) ? true : false;
                        if ($result == true) {
                            $errorNotice = implode('-', $resArr);
                        }
                    }
                    break;
                case 'must_bl';
                    if (empty($roleValue)) {
                        break;
                    }
                    // 主要诊断中出现了C00到D48，病理诊断编码必须填写，且格式为M****/*
                    $blMustNos = config('blMust');
                    if (in_array($no, $blMustNos)) {

                        preg_match_all("/^M(\d+\/\d+)$/", $data['ABF01C'], $jwsDataNoRes);
                        if (empty($jwsDataNoRes[1])) {
                            $result = true;
                            break;
                        }
                    }
                    break;
                case 'multi':
                    $multi = explode('|', $expRule[1]);
                    if (empty($multi)) {
                        break;
                    }
                    $flag = true;
                    foreach ($multi as $k => $m) {
                        $paramsArr = explode(',', $m);
                        if (empty($paramsArr[0]) || empty($paramsArr[1]) || empty($paramsArr[2])) {
                            continue;
                        }
                        $flag = true;
                        switch ($paramsArr[1]) {
                            case 'in':
                                if ($paramsArr[0] == 'ICD10_ID1' || $paramsArr[0] == 'ICD10_NAME') {
                                    $data[$paramsArr[0]] = self::getAuth($data, $paramsArr[0]);
                                }
                                if (!in_array($paramsArr[2], $data[$paramsArr[0]])) {
                                    $flag = false;
                                }

                                break;
                            case '=':

                                if ($data[$paramsArr[0]] != $paramsArr[2]) {
                                    $flag = false;
                                }
                                break;
                            case '<':
                                if ($data[$paramsArr[0]] > $paramsArr[2]) {
                                    $flag = false;
                                }
                                break;
                            case '>':
                                if ($data[$paramsArr[0]] < $paramsArr[2]) {
                                    $flag = false;
                                }
                                break;
                            case 'main_no_pro': // 校验主要诊断编码中是否包含指定的开头编码字符
                                if (empty($paramsArr[2])) {
                                    $flag = false;
                                    break;
                                }
                                $nos = explode('&&', $paramsArr[2]);
                                $elFlag = false;
                                foreach ($nos as $n) {
                                    if (strpos($no, $n) !== false) {
                                        $elFlag = true;
                                        break;
                                    }
                                }
                                $flag = $elFlag;
                                break;
                            case 'start_word':// ABF01C,start_word,M| 判断是否已指定字符开头
                                if (strpos($data[$paramsArr[0]], $paramsArr[2]) !== 0) {
                                    $flag = false;
                                    break;
                                }
                                break;
                            case 'end_word': // ABF01C,end_word,/6 判断是否已指定字符结尾
                                if (substr($data[$paramsArr[0]], strpos($data[$paramsArr[0]], $paramsArr[2])) != $paramsArr[2]) {
                                    $flag = false;
                                    break;
                                }
                                break;
                            case 'intersection';
                                if (empty($paramsArr[2])) {
                                    $flag = false;
                                    break;
                                }
                                $errNo = explode('&&', $paramsArr[2]);
                                $intersectNo = array_intersect($auth, $errNo);
                                if (!$intersectNo) {
                                    $flag = false;
                                }

                                break;
                        }
                        // 如果第一个条件不满足，则之后的将不在校验
                        if ($k == 0 && !$flag) {
                            $flag = true;
                            break;
                        }
                        if (!$flag) {
                            break;
                        }

                    }
                    if (!$flag) {
                        $result = true;
                    }
                    break;
                case 'intersection';
                    $intersectNo = array_intersect($auth, $roleValue);
                    if ($intersectNo) {
                        $errorNotice = implode('-', $intersectNo);
                        $result = true;
                    }
                    break;
            }


            return $result;
        }
    }

    /**
     * @param $data
     * @param $item
     * @param string $errorNotice
     * @return bool
     * 手术相关的规则校验
     */
    public static function operation($data, $item, &$errorNotice = "")
    {
        $auth = self::getAuth($data, $item['auth'], 'main');

        if ($auth == '' || empty($auth[0])) {
            return false;
        } else {
            $no = $auth[0] ?? ''; // 主要手术编号

            $result = false;
            $expRule = explode(':', $item['relation_rule']);
            if (empty($expRule)) {
                return false;
            }
            if (isset($expRule[1])) {
                $roleValue = explode(',', $expRule[1]); // 规则判断的值
            }
            switch ($expRule[0]) {
                // 手术编码同时存在就报错
                case 'concurrent';
                    $existIndex = 0;
                    foreach ($roleValue as $childNo) {
                        if (in_array($childNo, $auth)) {
                            $existIndex++;
                            $errorNotice .= $childNo . '-';
                        }
                    }
                    // 如果累加的数量 = 指定的编码组数量，则表示都存在，则有问题
                    $result = $existIndex == count($roleValue) ? true : false;
                    break;

                case 'consistent'; // 手术编码和给定的规则编码完全一致，不一致报错
                    foreach ($roleValue as $childNo) {
                        if (!in_array($childNo, $auth)) {
                            $errorNotice .= $childNo . '-';
                            $result = true;
                        }
                    }
                    break;
                case 'in';
                    if ($roleValue) {
                        $result = in_array($no, $roleValue) ? true : false;
                        $errorNotice = $no;
                    }
                    break;
                case 'no_in';
                    if ($expRule[1] && $auth) {
                        $result = in_array($expRule[1], $auth) ? true : false;
                        $errorNotice = $expRule[1];
                    }
                    break;
                case 'exist';
                    $flagNums = 0;
                    if (count($auth) > 1) {
                        $authStr = implode(',', $auth);
                        foreach ($roleValue as $item) {
                            if (strpos($authStr, $item) !== false) {
                                $errorNotice .= $item . '-';
                                $flagNums++;
                            }
                        }
                    }
                    if ($flagNums >= 2) {
                        $result = true;
                    } else {
                        $result = false;
                    }
                    break;
                case 'intersection';
                    if (empty($roleValue)) {
                        $result = false;
                        break;
                    }
                    $intersectNo = array_intersect($auth, $roleValue);
                    if (!$intersectNo) {
                        $flag = false;
                    }
                    break;
                case 'multi':
                    $multi = explode('|', $expRule[1]);
                    if (empty($multi)) {
                        break;
                    }
                    $flag = true;
                    foreach ($multi as $k => $m) {
                        $paramsArr = explode(',', $m);
                        if (empty($paramsArr[0]) || empty($paramsArr[1]) || empty($paramsArr[2])) {
                            continue;
                        }
                        switch ($paramsArr[1]) {
                            case '!=':
                                if ($data[$paramsArr[0]] == $paramsArr[2]) {
                                    $flag = false;
                                }
                                break;
                            case '=':
                                if ($data[$paramsArr[0]] != $paramsArr[2]) {
                                    $flag = false;
                                }
                                break;
                            case '!empty':
                                $fields = ['HOCUS_MAN_CODE', 'HOCUS_WAY_ID'];
                                if (in_array($item['auth'], $fields)) {
                                    if (empty($data['operation'][0][$paramsArr[0]])) {
                                        $flag = false;
                                    }
                                } else {
                                    if (empty($data[$paramsArr[0]])) {
                                        $flag = false;
                                    }
                                }

                                break;
                            case 'empty':
                                if (empty($data[$paramsArr[0]])) {
                                    $flag = false;
                                }
                                break;
                            case 'pre_like':
                                if (empty($paramsArr[2])) {
                                    $flag = false;
                                    break;
                                }
                                $nos = explode('&&', $paramsArr[2]);
                                foreach ($nos as $n) {
                                    if (strpos($no, $n) === 0) {
                                        $flag = false;
                                        break;
                                    }
                                }
                                break;
                        }
                        // 如果第一个条件不满足，则之后的将不在校验
                        if ($k == 0 && !$flag) {
                            $flag = true;
                            break;
                        }
                        if (!$flag) {
                            break;
                        }

                    }
                    if (!$flag) {
                        $result = true;
                    }
                    break;
            }

            return $result;
        }
    }

    /**
     * @param $data
     * @param $item
     * @param string $errorNotice
     * @return bool
     * 手术相关的规则校验
     */
    public static function operationName($data, $item, &$errorNotice = "")
    {
        $auth = self::getAuth($data, $item['auth']);
        $expRule = explode('|', $item['relation_rule']);
        if ($auth == '' || empty($auth[0]) || empty($expRule)) {
            return false;
        } else {
            $feeDetail = empty($data['feeDetail']) ? [] : $data['feeDetail'];

            // 检查费用明细是否包含指定的手术名称
            $fymc = array_column($feeDetail, 'FYMC');
            if (empty($fymc)) {
                return false;
            }
            $fymcStr = implode(',', $fymc);
            // 如果不包含则不校验
            if (strpos($fymcStr, $expRule[0]) === false) {
                return false;
            }

            // 检查手术中是否包含指定的名称
            $auth = implode(',', $auth);
            if (strpos($auth, $expRule[1]) === false) {
                return true;
            }

            return false;
        }
    }

    /**
     * @param $data
     * @param $item
     * @param string $errorNotice
     * @return bool
     * 呼吸机的规则校验
     */
    public static function huxiji($data, $item, &$errorNotice)
    {
        $auth = self::getAuth($data, $item['auth']);
        $expRule = explode('|', $item['relation_rule']);

        if ($auth == '' || empty($auth[0]) || empty($expRule)) {
            return false;
        } else {
            $feeDetail = empty($data['feeDetail']) ? [] : $data['feeDetail'];

            // 检查费用明细是否包含指定的手术名称
            $fymc = array_column($feeDetail, 'FYMC');
            if (empty($fymc)) {
                return false;
            }
            $fymcStr = implode(',', $fymc);
            // 如果不包含则不校验
            if (strpos($fymcStr, '呼吸机辅助呼吸') === false) {
                return false;
            }

            // 呼吸机使用时间
            $AEL01 = 0;
            foreach ($feeDetail as $v) {
                if (strpos($v['FYMC'], '呼吸机辅助呼吸') !== false) {
                    $AEL01 += $v['FYSL'];
                }
            }
            $AEL01 = intval($AEL01 ?: 0);
            $operationStr = implode(',', $auth);
            if (($expRule[0] == ">=96" && $AEL01 >= 96) || ($expRule[0] == "<96" && $AEL01 < 96)) {
                if (strpos($operationStr, $expRule[1]) === false) {
                    return true;
                }
            }

            // 有创呼吸机使用时间【不能为0   不能为空】
            $days = floor($AEL01 / 24); // 计算天数
            $remainingHours = $AEL01 % 24; // 计算剩余的小时数
            if ($expRule[0] == "yc=0" && empty($data['AEL01'])) {
                $errorNotice = ',实际使用时长：' . $days . "天 " . $remainingHours . "小时";
                return true;
            }

            return false;
        }
    }


    /**
     * @param $data
     * @param $item
     * @param string $errorNotice
     * @return bool
     * 住院相关规则
     */
    public static function hospital($data, $item, &$errorNotice = "")
    {
        $auth = self::getAuth($data, $item['auth']);

        if (empty($auth)) {
            return false;
        } else {

            $result = false;
            $expRule = explode(':', $item['relation_rule']);
            if (empty($expRule)) {
                return false;
            }
            if (isset($expRule[1])) {
                $roleValue = explode(',', $expRule[1]); // 规则判断的值
            }
            switch ($expRule[0]) {
                case 'multi':
                    $multi = explode('|', $expRule[1]);
                    if (empty($multi)) {
                        break;
                    }
                    $flag = true;
                    foreach ($multi as $k => $m) {
                        $paramsArr = explode(',', $m);
                        if (empty($paramsArr[0]) || empty($paramsArr[1]) || empty($paramsArr[2])) {
                            continue;
                        }
                        $flag = true;
                        switch ($paramsArr[1]) {
                            case 'in':
                                $nos = explode('&&', $paramsArr[2]);
                                if (!in_array($data[$paramsArr[0]], $nos)) {
                                    $flag = false;
                                    break;
                                }
                                break;
                            case '!=':
                                if ($data[$paramsArr[0]] == $paramsArr[2]) {
                                    $flag = false;
                                }
                                break;
                            case '=':
                                if ($data[$paramsArr[0]] != $paramsArr[2]) {
                                    $flag = false;
                                }
                                break;
                            case '!empty':
                                if (empty($data[$paramsArr[0]])) {
                                    $flag = false;
                                }
                                break;
                            case 'empty':
                                if (!empty($data[$paramsArr[0]])) {
                                    $flag = false;
                                }
                                break;
                        }
                        // 如果第一个条件不满足，则之后的将不在校验
                        if ($k == 0 && !$flag) {
                            $flag = true;
                            break;
                        }
                        if (!$flag) {
                            break;
                        }

                    }
                    if (!$flag) {
                        $result = true;
                    }
                    break;
                case 'intersection';
                    $intersectNo = array_intersect($auth, $roleValue);
                    if ($intersectNo) {
                        $errorNotice = implode('-', $intersectNo);
                        $result = true;
                    }
                    break;
            }


            return $result;
        }
    }

    /**
     * @param $data
     * @param $item
     * @param string $errorNotice
     * @return bool
     * 时间规则
     */
    public static function timeRole($data, $item, &$errorNotice = "")
    {
        $auth = self::getAuth($data, $item['auth']);

        if (empty($auth)) {
            return false;
        } else {

            $result = false;
            $expRule = explode('|', $item['relation_rule']);
            if (empty($expRule)) {
                return false;
            }
            $flag = true;
            foreach ($expRule as $k => $m) {
                $paramsArr = explode(',', $m);
                if (empty($paramsArr[0]) || empty($paramsArr[1]) || empty($paramsArr[2])) {
                    continue;
                }
                $flag = true;
                switch ($paramsArr[1]) {
                    case '>':
                        if (
                            !empty($data[$paramsArr[0]])
                            && !empty($data[$paramsArr[2]])
                            && $data[$paramsArr[0]] < $data[$paramsArr[2]]
                        ) {
                            $flag = false;
                        }
                        break;
                    case '!=':
                        if ($data[$paramsArr[0]] == $paramsArr[2]) {
                            $flag = false;
                        }
                        break;
                    case '=':
                        if ($data[$paramsArr[0]] != $paramsArr[2]) {
                            $flag = false;
                        }
                        break;
                    case '!empty':
                        if (empty($data[$paramsArr[0]])) {
                            $flag = false;
                        }
                        break;
                    case 'empty':
                        if (!empty($data[$paramsArr[0]])) {
                            $flag = false;
                        }
                        break;
                }
                if (!$flag) {
                    break;
                }
                if (!$flag) {
                    $result = true;
                }

                return $result;
            }
        }
    }
    public static function orValue($data, $item)
    {
        $condition = self::getAuth($data, $item['relation']);
        $auth = self::getAuth($data, $item['auth']);
        if ($auth == '' && $condition == '') {
            return true;
        } else {
            $expRule = explode(':', $item['relation_rule']);
            switch ($expRule[0]) {
                case 'min';
                    $result = $auth < $expRule[1] ? 1 : 0;
                    break;
                case 'max';
                    if (is_numeric($expRule[1])) {
                        $result = $auth > $expRule[1] ? 1 : 0;
                    } else {
                        $result = $auth > $data[$expRule[1]] ? 1 : 0;
                    }
                    break;
                case 'equ';
                    $result = $auth == $expRule[1] ? 1 : 0;
                    break;
                case 'nequ';
                    $result = $auth != $expRule[1] ? 1 : 0;
                    break;
                case 'len';
                    $result = strlen($auth) <= $expRule[1] ? 1 : 0;
                    break;
                case 'in';
                    $aa = config('dictionaries.' . $expRule[1]);
                    $result = isset($aa[$auth]) ? 1 : 0;
                    break;
                default;
                    $result = 1;
                    break;
            }
            if ($result) {
                return false;
            }
            return true;
        }
    }

    public static function condition($data, $item)
    {

        $condition = self::getAuth($data, $item['relation']);
        $auth = self::getAuth($data, $item['auth']);
        if ($condition != '') {
            $rule = explode('｜', $item['relation_rule']);
            $result = 0;
            foreach ($rule as $value) {
                $expRule = explode(':', $value);
                switch ($expRule[0]) {
                    case 'min';
                        $result = $auth < $expRule[1] ? 1 : 0;
                        break;
                    case 'relation_min';
                        $result = $condition < $expRule[1] ? 1 : 0;
                        break;
                    case 'relation_max';
                        if (is_int($expRule[1])) {
                            $result = $condition > $expRule[1] ? 1 : 0;
                        } else {
                            $result = $condition > $data[$expRule[1]] ? 1 : 0;
                        }
                        break;
                    case 'max';
                        if (is_int($expRule[1])) {
                            $result = $auth > $expRule[1] ? 1 : 0;
                        } else {
                            $result = $auth > $data[$expRule[1]] ? 1 : 0;
                        }
                        break;
                    case 'equ';
                        $result = $condition != $expRule[1] ? 1 : 0;
                        break;
                    case 'nequ';
                        $result = $condition == $expRule[1] ? 1 : 0;
                        break;
                    case 'len';
                        $result = strlen($condition) > $expRule[1] ? 1 : 0;
                        break;
                    case 'range';
                        $ruleData = explode(',', $expRule[1]);
                        if (is_string($condition)) {
                            $result = in_array($condition, $ruleData) ? 1 : 0;
                        } elseif (is_array($condition)) {
                            $firsts = array_column($condition, $item['relation']);
                            foreach ($firsts as $first) {
                                if (in_array($first, $ruleData)) {
                                    $result = 1;
                                    break;
                                } else {
                                    $result = 0;
                                }
                            }
                        }
                        break;
                    case '!empty';
                        $result = $condition == '' ? 1 : 0;
                        break;
                    case 'empty';
                        $result = !empty($condition) ? 1 : 0;
                        break;
                    case 'first';
                        if (is_string($condition)) {
                            $first = substr($condition, 0, 1);
                            if (in_array($first, explode(',', $expRule[1]))) {
                                $result = 1;
                            } else {
                                $result = 0;
                            }
                        } elseif (is_array($condition)) {
                            $firsts = array_column($condition, $item['relation']);
                            $ruleData = explode(',', $expRule[1]);
                            foreach ($firsts as $first) {
                                if (in_array($first, $ruleData)) {
                                    $result = 1;
                                    break;
                                } else {
                                    $result = 0;
                                }
                            }
                        }
                        break;
                    case 'in';
                        $aa = config('dictionaries.' . $item['relation']);
                        $result = !isset($aa[$condition]) ? 1 : 0;
                        break;
                    default;
                        $result = 0;
                        break;
                }
                if ($result) {
                    break;
                }
            }
            if ($result) {
                if (isset($data[$auth]) && $data[$auth] == '') {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }
    }

    public static function gender($data, $item)
    {
        $auth = self::getAuth($data, $item['auth']);
        if ($auth == '') {
            return true;
        } else {
            $rule = explode('|', $item['relation_rule']);
            $rule1 = explode(':', $rule[0]);
            switch ($rule1[0]) {
                case 'equ';
                    $result1 = $auth == $rule1[1] ? 1 : 0;
                    break;
                default;
                    $result1 = 0;
                    break;
            }
            $result2 = 0;
            if ($result1 && isset($rule[1])) {
                $rule2 = explode(':', $rule[1]);
                switch ($rule2[0]) {
                    case 'no';
                        $result2 = self::getAuth($data, $item['relation']) == $rule2[1] ? 1 : 0;
                        break;
                    case 'no_in';
                        $in = explode(',', $rule2[1]);
                        $auth1 = self::getAuth($data, $item['relation'], 'main');
                        if (is_string($auth1)) {
                            $result2 = in_array($auth1, $in) ? 1 : 0;
                        } else {
                            $result2 = array_intersect_assoc($auth1, $in) === [] ? 0 : 1;
                        }

                        break;
                    default;
                        $result2 = 0;
                        break;
                }
            }
            if ($result2) {
                return true;
            } else {
                return false;
            }
        }
    }

    public static function age($data, $item)
    {
        $rule = explode('|', $item['relation_rule']);
        $rule1 = explode(':', $rule[0]);
        switch ($rule1[0]) {
            case 'min';
                $result1 = $data[$item['auth']] < $rule1[1] ? 1 : 0;
                break;
            default;
                $result1 = 0;
                break;
        }
        $result2 = 0;
        if ($result1 && isset($rule[1])) {
            $rule2 = explode(':', $rule[1]);
            switch ($rule2[0]) {
                case 'no';
                    $result2 = isset($data[$item['relation']]) && $data[$item['relation']] == $rule2[1] ? 1 : 0;
                    break;
                case 'no_in';
                    $in = explode(',', $rule2[1]);
                    $result2 = isset($data[$item['relation']]) && in_array($data[$item['relation']], $in) ? 1 : 0;
                    break;
                case 'field';
                    $params = explode(',', $rule2[1]);
                    if ($params[1] == '=' && isset($data[$params[0]])) {
                        $result2 = $data[$params[0]] == $params[2] ? 0 : 1;
                    }
                    break;
                default;
                    $result2 = 0;
                    break;
            }
        }
        if ($result2) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param array $data
     * @param string $type
     * @return array
     * 获取病例新中的诊断信息
     */
    public static function getDiagnosis($data = [], $type = '')
    {
        if (empty($data)) {
            return [];
        }
        $result = [];
        foreach ($data['diagnosis'] as $v) {
            if ($v['class'] == $type) {
                $result[] = $v;
            }
        }

        return $result;
    }

    /**
     * @param array $data
     * @param string $type
     * @return array
     * 获取病例新中的手术信息
     */
    public static function getOperation($data = [], $type = '')
    {
        if (empty($data)) {
            return [];
        }
        $result = [];
        foreach ($data['operation'] as $v) {
            if ($v['class'] == $type) {
                $result[] = $v;
            }
        }

        return $result;
    }

    public static function getAuth($data, $auth, $isMain = '')
    {
        if (in_array($auth, ['ICD10_ID1', 'ICD10_NAME'])) {
            $result = [];
            foreach ($data['diagnosis'] as $value) {
                if ($value['class'] == $isMain) {
                    $result[] = $value[$auth];
                } else {
                    $result[] = $value[$auth];
                }
            }
        } elseif (
            in_array($auth, [
                'ICD9_ID1',
                'ICD9_NAME',
                'OPE_DATE',
                'OPE_MAN_NAME',
                'OPE_MAN_CODE',
                'FRIST_ASSISTANT_CODE',
                'FRIST_ASSISTANT_NAME',
                'SECOND_ASSISTANT_CODE',
                'SECOND_ASSISTANT_NAME',
                'HOCUS_WAY_ID',
                'INCISION_GRADE_ID',
                'HOCUS_MAN_CODE',
                'HOCUS_MAN_NAME',
                'START_TIME',
                'END_TIME',
                'OPE_ORDER',
                'OPE_LEVEL',
            ])
        ) {
            $result = [];
            foreach ($data['operation'] as $value) {
                if (!isset($value[$auth]) || empty($value[$auth])) {
                    continue;
                }
                if ($value['class'] == $isMain) {
                    $result[] = $value[$auth];
                } else {
                    $result[] = $value[$auth];
                }
            }
        } else {
            $result = $data[$auth] ?? '';
        }
        return $result;
    }

    //分数计算
    public static function getScore($errorList)
    {
        if (empty($errorList)) {
            return 100;
        }
        $bClass = ['其他诊断名称'];
        $cClass = ['其他诊断编码'];
        $dClass = ['其他手术或操作名称'];
        $eClass = ['其他手术或操作编码'];
        $fClass = ['损伤(中毒)外部原因及疾病编码', '病理诊断及编码和病历号', '药物过敏史', '尸检记录', '血型及Rh标识', '手术级别', '术者', '第一助手'];
        $gClass = ['综合医疗服务类', '诊断类', '治疗类', '康复类中医类', '西药类', '中药类', '血液和血制品类耗材类', '其他类'];
        $aClass = ['健康卡号', '患者姓名', '出生地', '籍贯', '民族', '身份证号', '职业', '婚姻状况', '现住址', '电话号码', '邮编', '户口地址及邮编', '工作单位及地址', '单位电话及邮编', '联系人姓名', '关系', '地址', '电话号码'];
        $score = 0;
        $aClassScore = 0;
        $bClassScore = 0;
        $cClassScore = 0;
        $dClassScore = 0;
        $eClassScore = 0;
        $fClassScore = 0;
        $gClassScore = 0;
        foreach ($errorList as $val) {
            if (in_array($val['error_name'], $aClass)) {
                $aClassScore += 1;
                if ($aClassScore > 8) {
                    continue;
                }
            } elseif (in_array($val['error_name'], $bClass)) {
                $bClassScore += 1;
                if ($bClassScore > 4) {
                    continue;
                }
            } elseif (in_array($val['error_name'], $cClass)) {
                $cClassScore += 1;
                if ($cClassScore > 4) {
                    continue;
                }
            } elseif (in_array($val['error_name'], $dClass)) {
                $dClassScore += 1;
                if ($dClassScore > 4) {
                    continue;
                }
            } elseif (in_array($val['error_name'], $eClass)) {
                $eClassScore += 1;
                if ($eClassScore > 4) {
                    continue;
                }
            } elseif (in_array($val['error_name'], $fClass)) {
                $fClassScore += 1;
                if ($fClassScore > 6) {
                    continue;
                }
            } elseif (in_array($val['error_name'], $gClass)) {
                $gClassScore += 1;
                if ($gClassScore > 4) {
                    continue;
                }
            }
            $score += $val['down'];
        }
        return sprintf("%.1f", 100 - $score);
    }

    //科室
    public static function ks($data, $count, $year, $month, $down)
    {
        if (empty($data['AAB11C'])) {
            return;
        }
        $attending_group_query = DepartmentData::query();
        $attending_group_data = $attending_group_query
            ->where('hospital_name', '=', $data['hospital_name'])
            ->where('department_id', $data['AAB11C'])
            ->where('year', $year)
            ->where('month', $month)
            ->first();
        if ($attending_group_data) {
            $d_data = $attending_group_data->toArray();
            $extra = [];
            if ($count > 0) {
                $extra['total_score'] = DB::raw('total_score+' . $down);
                $extra['total_error'] = DB::raw('total_error+' . $count);
                $extra['error_exist'] = DB::raw('error_exist+' . $count);
                $extra['total_error_medical'] = DB::raw('total_error_medical+1');
                $extra['error_medical'] = DB::raw('error_medical+1');
            }
            if ($d_data['max_score'] < $down) {
                $extra['max_score'] = $down;
            }
            if ($d_data['min_score'] > $down) {
                $extra['min_score'] = $down;
            }
            $attending_group_query
                ->where('hospital_name', '=', $data['hospital_name'])
                ->where('department_id', $data['AAB11C'])
                ->where('year', $year)
                ->where('month', $month)
                ->increment('total_medical', 1, $extra);
        } else {
            $agd = [
                'hospital_name' => $data['hospital_name'],
                'department_id' => $data['AAB11C'],
                'total_medical' => 1,
                'total_error_medical' => 1,
                'error_medical' => 1,
                'total_error' => $count,
                'error_exist' => $count,
                'total_score' => $down,
                'max_score' => $down,
                'min_score' => $down,
                'year' => $year,
                'month' => $month,
                'created_at' => $year . '-' . $month . '-' . '01 00:00:00'
            ];
            $attending_group_query->insert($agd);
        }
    }

    //主诊组
    public static function zzz($data, $count, $l, $g, $b, $year, $month)
    {
        if (empty($data['ATTEND_GRP_CODE'])) {
            return;
        }
        $attending_group_query = AttendingGroupData::query();
        $attending_group_data = $attending_group_query
            ->where('hospital_name', '=', $data['hospital_name'])
            ->where('attending_group_id', $data['ATTEND_GRP_CODE'])
            ->where('year', $year)
            ->where('month', $month)
            ->first();
        if ($attending_group_data) {
            $extra = [];
            if ($l > 0) {
                $extra['logic_error_medical'] = DB::raw('logic_error_medical+' . $l);
            }
            if ($g > 0) {
                $extra['standard_error_medical'] = DB::raw('standard_error_medical+' . $g);
            }
            if ($b > 0) {
                $extra['code_error_medical'] = DB::raw('code_error_medical+' . $b);
            }
            if ($count > 0) {
                $extra['total_error_medical'] = DB::raw('total_error_medical+1');
                $extra['error_medical'] = DB::raw('error_medical+1');
            }
            $attending_group_query
                ->where('hospital_name', '=', $data['hospital_name'])
                ->where('attending_group_id', $data['ATTEND_GRP_CODE'])
                ->where('year', $year)
                ->where('month', $month)
                ->increment('total_medical', 1, $extra);
        } else {
            $agd = [
                'hospital_name' => $data['hospital_name'],
                'attending_group_id' => $data['ATTEND_GRP_CODE'],
                'year' => $year,
                'month' => $month,
                'total_medical' => 1,
                'total_error_medical' => $count >= 0 ? 1 : 0,
                'error_medical' => $count >= 0 ? 1 : 0,
                'logic_error_medical' => $l,
                'standard_error_medical' => $g,
                'code_error_medical' => $b,
                'created_at' => $year . '-' . $month . '-' . '01 00:00:00'
            ];
            $attending_group_query->insert($agd);
        }
    }

    //主治医师
    public static function zzys($data, $count, $l, $g, $b, $year, $month)
    {
        if (empty($data['AEE03_CODE'])) {
            return;
        }
        $attending_group_query = IndicationsData::query();
        $attending_group_data = $attending_group_query
            ->where('hospital_name', '=', $data['hospital_name'])
            ->where('indications_id', $data['AEE03_CODE'])
            ->where('year', $year)
            ->where('month', $month)
            ->first();
        if ($attending_group_data) {
            $extra = [];
            if ($l > 0) {
                $extra['logic_error_medical'] = DB::raw('logic_error_medical+' . $l);
            }
            if ($g > 0) {
                $extra['standard_error_medical'] = DB::raw('standard_error_medical+' . $g);
            }
            if ($b > 0) {
                $extra['code_error_medical'] = DB::raw('code_error_medical+' . $b);
            }
            if ($count > 0) {
                $extra['total_error_medical'] = DB::raw('total_error_medical+1');
                $extra['error_medical'] = DB::raw('error_medical+1');
            }
            $attending_group_query
                ->where('hospital_name', '=', $data['hospital_name'])
                ->where('indications_id', $data['AEE03_CODE'])
                ->where('year', $year)
                ->where('month', $month)
                ->increment('total_medical', 1, $extra);
        } else {
            $agd = [
                'hospital_name' => $data['hospital_name'],
                'indications_id' => $data['AEE03_CODE'],
                'department_id' => $data['AAB11C'],
                'year' => $year,
                'month' => $month,
                'total_medical' => 1,
                'total_error_medical' => $count >= 0 ? 1 : 0,
                'error_medical' => $count >= 0 ? 1 : 0,
                'logic_error_medical' => $l,
                'standard_error_medical' => $g,
                'code_error_medical' => $b,
                'created_at' => $year . '-' . $month . '-' . '01 00:00:00'
            ];
            $attending_group_query->insert($agd);
        }
    }

    //住院医师
    public static function zyys($data, $count, $l, $g, $b, $year, $month)
    {
        if (empty($data['AEE04_CODE'])) {
            return;
        }
        $attending_group_query = HospitalData::query();
        $attending_group_data = $attending_group_query
            ->where('hospital_name', '=', $data['hospital_name'])
            ->where('hospital_id', $data['AEE04_CODE'])
            ->where('year', $year)
            ->where('month', $month)
            ->first();
        if ($attending_group_data) {
            $extra = [];
            if ($l > 0) {
                $extra['logic_error_medical'] = DB::raw('logic_error_medical+' . $l);
            }
            if ($g > 0) {
                $extra['standard_error_medical'] = DB::raw('standard_error_medical+' . $g);
            }
            if ($b > 0) {
                $extra['code_error_medical'] = DB::raw('code_error_medical+' . $b);
            }
            if ($count > 0) {
                $extra['total_error_medical'] = DB::raw('total_error_medical+1');
                $extra['error_medical'] = DB::raw('error_medical+1');
            }
            $attending_group_query
                ->where('hospital_name', '=', $data['hospital_name'])
                ->where('hospital_id', $data['AEE04_CODE'])
                ->where('year', $year)
                ->where('month', $month)
                ->increment('total_medical', 1, $extra);
        } else {
            $agd = [
                'hospital_name' => $data['hospital_name'],
                'hospital_id' => $data['AEE04_CODE'],
                'department_id' => $data['AAB11C'],
                'year' => $year,
                'month' => $month,
                'total_medical' => 1,
                'total_error_medical' => $count >= 0 ? 1 : 0,
                'error_medical' => $count >= 0 ? 1 : 0,
                'logic_error_medical' => $l,
                'standard_error_medical' => $g,
                'code_error_medical' => $b,
                'created_at' => $year . '-' . $month . '-' . '01 00:00:00'
            ];
            $attending_group_query->insert($agd);
        }
    }

    //编码员
    public static function bmy($data, $count, $year, $month)
    {
        if (empty($data['AEE08'])) {
            return;
        }
        $attending_group_query = CoderData::query();
        $attending_group_data = $attending_group_query
            ->where('hospital_name', '=', $data['hospital_name'])
            ->where('coder_id', $data['AEE08'])
            ->where('year', $year)
            ->where('month', $month)
            ->first();
        if ($attending_group_data) {
            $extra = [];
            if ($count > 0) {
                $extra = [
                    'total_error_medical' => DB::raw('total_error_medical+1'),
                    'error_medical' => DB::raw('error_medical+1')
                ];
            }
            $attending_group_query
                ->where('hospital_name', '=', $data['hospital_name'])
                ->where('coder_id', $data['AEE08'])
                ->where('year', $year)
                ->where('month', $month)
                ->increment('total_medical', 1, $extra);
        } else {
            $agd = [
                'hospital_name' => $data['hospital_name'],
                'coder_id' => $data['AEE08'],
                'year' => $year,
                'month' => $month,
                'total_medical' => 1,
                'total_error_medical' => $count >= 0 ? 1 : 0,
                'created_at' => $year . '-' . $month . '-' . '01 00:00:00'
            ];
            $attending_group_query->insert($agd);
        }
    }

    //统计信息
    public static function count($count, $year, $month, $down, $A, $hospitalName, $AAA28)
    {
        // 先检查病例是否已经质控过
        $patientScore = PatientScore::query()
            ->where('ZYH', $AAA28)
            ->first();

        // 如果已经质控过，不需要重复统计
        if ($patientScore) {
            return $patientScore->level;
        }

        $level = 0;
        $DepartmentQuery = \App\Model\Count::query();
        $Department = $DepartmentQuery
            ->where('hospital_name', '=', $hospitalName)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($Department) {
            // 如果存在记录，直接更新
            $extra = [];
            if ($count > 0) {
                $extra['cumulative_medical'] = $Department->cumulative_medical + 1;
                $extra['error_medical'] = $Department->error_medical + 1;
            }

            if ($down >= 97) {
                $extra['excellent'] = $Department->excellent + 1;
                $extra['excellent_qa'] = $Department->excellent_qa + 1;
            } else if ($down >= 90 && $down <= 96) {
                $extra['good'] = $Department->good + 1;
                $extra['good_qa'] = $Department->good_qa + 1;
                $level = 1;
            } else if ($down >= 75 && $down <= 89) {
                $extra['middle'] = $Department->middle + 1;
                $extra['middle_qa'] = $Department->middle_qa + 1;
                $level = 2;
            } else {
                $extra['fail'] = $Department->fail + 1;
                $extra['fail_qa'] = $Department->fail_qa + 1;
                $level = 3;
            }

            $extra['total_score'] = $Department->total_score + $down;
            $extra['total_score_qa'] = $Department->total_score_qa + $down;
            $extra['total_medical'] = $Department->total_medical + 1;

            $DepartmentQuery
                ->where('hospital_name', '=', $hospitalName)
                ->where('year', $year)
                ->where('month', $month)
                ->update($extra);
        } else {
            // 新记录
            $agd = [
                'hospital_name' => $hospitalName,
                'total_medical' => 1,
                'cumulative_medical' => $count > 0 ? 1 : 0,
                'error_medical' => $count > 0 ? 1 : 0,
                'total_score' => $down,
                'total_score_qa' => $down,
                'year' => $year,
                'month' => $month,
                'created_at' => $year . '-' . $month . '-' . '01 00:00:00',
            ];

            if ($down >= 97) {
                $agd['excellent'] = 1;
                $agd['excellent_qa'] = 1;
            } else if ($down >= 90 && $down <= 96) {
                $agd['good'] = 1;
                $agd['good_qa'] = 1;
                $level = 1;
            } else if ($down >= 75 && $down <= 89) {
                $agd['middle'] = 1;
                $agd['middle_qa'] = 1;
                $level = 2;
            } else {
                $agd['fail'] = 1;
                $agd['fail_qa'] = 1;
                $level = 3;
            }

            $DepartmentQuery->insert($agd);
        }
        return $level;
    }

    //缺陷问题
    public static function errorData($errorList, $year, $month, $hospitalName)
    {
        // 先按 error_rule 和 ZYH 去重
        $uniqueErrors = collect($errorList)
            ->unique(function ($item) {
                return $item['error_rule'] . '-' . $item['ZYH'];
            })
            ->values()
            ->all();

        foreach ($uniqueErrors as $item) {
            $errorDataId = ErrorData::query()
                ->where('hospital_name', '=', $hospitalName)
                ->where('error_rule', '=', $item['error_rule'])
                ->where('year', '=', $year)
                ->where('month', '=', $month)
                ->value('id');

            if ($errorDataId) {
                // 已存在记录，增加计数
                ErrorData::query()
                    ->where('id', '=', $errorDataId)
                    ->increment('count');
            } else {
                // 新记录
                $insertData = [
                    'hospital_name' => $hospitalName,
                    'error_rule' => $item['error_rule'],
                    'type' => $item['type'],
                    'year' => $year,
                    'month' => $month,
                    'count' => 1,
                    'created_at' => $year . '-' . $month . '-' . '01 00:00:00',
                ];
                ErrorData::query()->insert($insertData);
            }
        }
    }
}
