<?php

namespace App\Services;

use App\Model\Indicator;
use App\Model\PatientInfo;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Model\RuleWordMap;
use App\Model\SM_SSAP;
use App\Model\SSCZ;
use App\Model\SSSQ;
use App\Model\Staff;
use App\Model\Yzb;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Model\EMR_BL_BLSY;
use App\Model\PatientDoctorInfo;
use App\Services\IndicatorService;
use App\Services\ElasticsearchService;
use App\Model\IndexCatalog;
use App\Model\MainDiagnosis;
use App\Model\MainOperation;
use App\Model\SecondaryOperation;
use App\Model\OtherDiagnosis;
use App\Model\ZY_BRRY;
use App\Model\GY_SSML;
use DateTime;

/**
 * 指标计算服务类
 *
 * 该类负责计算各种医疗质量指标，包括但不限于：
 * - 术者术前查房完成率 calculateSzsqcf()
 * - 急会诊及时到位率 calculateJhzjsdwl()
 * - 普通会诊及时完成率 calculatePthzjswcl()
 * - 术者术后查房完成率 calculateSzshcf24()
 * - 手术后出现严重脏器功能损害的并发症的患者完成疑难病例讨论率 calculateShshbfzbltl()
 * - [未来可能添加的其他指标]
 */
class IndicatorCalcService
{


    protected $BL01ESServer;
    protected $BLSYESServer;

    protected $staffESServer;

    protected $mainOperation;

    protected $mainDiagnosis;

    protected $otherDiagnosis;


    /**
     * 构造函数
     *
     * 初始化默认开始时间上个月的第一天
     */
    public function __construct()
    {

        $this->BL01ESServer = new ElasticsearchService('bl01_202303');
        $this->BLSYESServer = new ElasticsearchService('blsy_2023');
        $this->staffESServer = new ElasticsearchService('staff_2023');
        $this->mainOperation = new ElasticsearchService('main_operation');
        $this->mainDiagnosis = new ElasticsearchService('main_diagnosis');
        $this->otherDiagnosis = new ElasticsearchService('other_diagnosis_2023');
    }

    /**
     * 计算所有指标
     *
     * @param string $zyh 住院号，可选
     * @param string $start_time 开始时间，可选
     * @param string $end_time 结束时间，可选
     */
    public function calculateAll($zyh = "", $start_time = "", $end_time = "")
    {
        // 计算术者术前查房完成率
        $this->calculateSzsqcf($zyh, $start_time, $end_time);
        // 计算急会诊及时到位率
        $this->calculateJhzjsdwl($zyh, $start_time, $end_time);
        // 计算普通会诊及时完成率
        $this->calculatePthzjswcl($zyh, $start_time, $end_time);
        // 计算术者术后查房完成率
        $this->calculateSzshcf24($zyh, $start_time, $end_time);
        // 计算手术后出现严重脏器功能损害的并发症的患者完成疑难病例讨论率
        $this->calculateShshbfzbltl($zyh, $start_time, $end_time);
        // 计算值班期间诊疗处置记录率
        $this->calculateZbqjzlcz($zyh, $start_time, $end_time);
        // 计算抢救记录及时完成率
        $this->calculateQjjljsjl($zyh, $start_time, $end_time);
        // 计算抢救记录审核率
        $this->calculateQjjlsh($zyh, $start_time, $end_time);
        // 计算抢救成功率
        $this->calculateQjcg($zyh, $start_time, $end_time);
        // 计算术前讨论及时完成率
        $this->calculateSqtlwcl($zyh, $start_time, $end_time);
        // 计算术者术前讨论参与率
        $this->calculateSqtlrygfcyl($zyh, $start_time, $end_time);
        // 计算手术医嘱规范开具率
        $this->calculateSsyzgfkjl($zyh, $start_time, $end_time);
        // 计算死亡病例讨论及时完成率
        $this->calculateSwtljswcl($zyh, $start_time, $end_time);
        // 计算术者符合授权目录一致率
        $this->calculateSzfhsqmlyzl($zyh, $start_time, $end_time);
        // 计算手术医师手术时间重合率
        $this->calculateSsyssssjch($zyh, $start_time, $end_time);
    }

    /**
     * 计算术者术查房完成率
     *
     * @param string $zyh 住院号，可选
     * @param string $start_time 开始时间，可选
     * @param string $end_time 结束时间，可选
     */
    public function calculateSzsqcf($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("szsqcf", 1);
        $indicatorService = new IndicatorService();
        $patientData = $indicatorService->getPatientInfoData($zyh, $start_time, $end_time);

        $ruleMap = $this->getRuleMap();

        foreach ($patientData as $patient) {
            //删除已经存在的数据
            Indicator::query()->updateOrInsert(['zyh' => $patient['MED_REC_ID']], ['szsqcf_fm' => 0, 'szsqcf_fz' => 0, 'szsqcf_error' => null]);
            $ZYH = $patient['MED_REC_ID'];

            // 检查住院时间是否大于1天
            $enterTime = $patient['AAB01'];
            $exitTime = $patient['AAC01'];
            if (empty($exitTime)) {
                $exitTime = date('Y-m-d H:i:s');
            }
            $enterDate = date('Y-m-d', strtotime($enterTime));
            $exitDate = date('Y-m-d', strtotime($exitTime));
            $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);

            if ($diffDays <= 1) {
                continue;
            }

            $insert = [
                'zyh' => $ZYH,
                'szsqcf_fz' => 0,
                'szsqcf_fm' => 0,
                'szsqcf_error' => null,
                'AAC11N' => $patient['AAC11N'],
                'AEE03' => !empty($patient['AEE03']) ? $patient['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($patient['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($patient['AAC01'])),
                'AAC01' => $patient['AAC01']
            ];

            // 查询手术信息（按手术开始时间正序）
            try {
                $ssapData = SM_SSAP::query()->where('ZYH', '=', $ZYH)->orderBy('SSRQ', 'asc')->get()->toArray();
                if (empty($ssapData)) {
                    continue;
                }
            } catch (\Exception $e) {
                Log::error("calculateSzsqcf-$ZYH-error: " . $e->getMessage());
                continue;
            }

            // 获取配置
            $ruleMap8010 = RuleWordMap::query()->where('id', '=', 8010)->value('keyword');
            $ruleMap8010 = !empty($ruleMap8010) ? $ruleMap8010 : '294';

            $ruleMap8008 = RuleWordMap::query()->where('id', '=', 8008)->value('keyword');
            $ruleMap8008 = !empty($ruleMap8008) ? $ruleMap8008 : '295';
            if (strpos($ruleMap8008, ',') !== false) {
                $recordTypes = explode(',', $ruleMap8008);
            } else {
                $recordTypes = [$ruleMap8008];
            }

            $ruleMap8022 = RuleWordMap::query()->where('id', '=', 8022)->value('keyword');
            $ruleMap8022 = !empty($ruleMap8022) ? $ruleMap8022 : '会诊,危急值';
            if (strpos($ruleMap8022, ',') !== false) {
                $excludeKeywords = explode(',', $ruleMap8022);
            } else {
                $excludeKeywords = [$ruleMap8022];
            }

            $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
            $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ';

            $ruleMap8002 = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
            $ruleMap8002 = !empty($ruleMap8002) ? $ruleMap8002 : 'first_blsy_time';

            $ruleMap8047 = RuleWordMap::query()->where('id', '=', 8047)->value('keyword');
            if (strpos($ruleMap8047, ',') !== false) {
                $excludeKeywords8047 = explode(',', $ruleMap8047);
            } else {
                $excludeKeywords8047 = [$ruleMap8047];
            }

            $ruleMap8048 = RuleWordMap::query()->where('id', '=', 8048)->value('keyword');
            if (strpos($ruleMap8048, ',') !== false) {
                $excludeKeywords8048 = explode(',', $ruleMap8048);
            } else {
                $excludeKeywords8048 = [$ruleMap8048];
            }

            $errorContent = [];

            // 处理每个手术
            for ($i = 0; $i < count($ssapData); $i++) {
                $surgery = $ssapData[$i];

                // 检查基本字段有效性
                $surgeryStartTime = $surgery['SSRQ'];
                $surgeon = $surgery['SZ'];
                $surgeon1 = $surgery['SZ'];
                $surgeryName = $surgery['ICD9_SSCZMC'];
                $SQDH = $surgery['SQDH'];

                if (
                    empty($surgery['SSRQ']) || empty($surgery['ICD9_SSCZMC']) || empty($surgery['JSRQ']) ||
                    strpos($surgery['JSRQ'], '1970-01-01') !== false || $surgery['ICD9_SSCZMC'] == 'NULL'
                ) {
                    continue;
                }

                if (empty($surgeon1)) {
                    continue;
                }

                // 排除紧急手术（jjbz=1）
                if (!empty($SQDH)) {
                    $sssqData = SSSQ::query()->where('SQDH', $SQDH)->first();
                    if (!empty($sssqData)) {
                        $jjbz = $sssqData['JJBZ'] ?? 0;
                        if ($jjbz == 1) {
                            // 紧急手术，跳过统计
                            continue;
                        }
                    }
                }

                $surgeonCode = $surgery['SZDM'];

                // 手术级别过滤
                if (!empty($ruleMap8047)) {
                    $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
                    if (!empty($ssCZ)) {
                        if (!in_array($ssCZ, $excludeKeywords8047)) {
                            if (!empty($ruleMap8048)) {
                                if (!in_array($surgeryName, $excludeKeywords8048)) {
                                    continue;
                                }
                            } else {
                                continue;
                            }
                        }
                    }
                }

                // 从staff表获取术者工号
                $surgeonBH = Staff::query()->where('code', $surgeonCode)->value('YGBH');
                $surgeon = $surgeon . "(" . $surgeonBH . ")";

                // 检查手术开始时间有效性
                if (
                    empty($surgeryStartTime) || $surgeryStartTime == '1970-01-01 00:00:00' ||
                    $surgeryStartTime == '0000-00-00 00:00:00' || empty($surgeon)
                ) {
                    continue;
                }

                // 计数分母
                $insert['szsqcf_fm'] += 1;

                // 计算查房时间范围（手术开始前24小时）
                $startCheckTime = date('Y-m-d 00:00:00', strtotime($surgeryStartTime) - 24 * 3600);

                // 如果不是第一次手术，检查上一次手术结束时间
                /* if ($i > 0) {
                    $prevSurgeryEndTime = $ssapData[$i - 1]['JSRQ'];
                    if (
                        !empty($prevSurgeryEndTime) && $prevSurgeryEndTime != '1970-01-01 00:00:00' &&
                        $prevSurgeryEndTime != '0000-00-00 00:00:00' &&
                        strtotime($prevSurgeryEndTime) > strtotime($startCheckTime)
                    ) {
                        $startCheckTime = $prevSurgeryEndTime;
                    }
                } */

                // 查询病程记录（不包括首次病程）
                $startCheckTime = date('Y-m-d H:i:s', strtotime($startCheckTime));
                $surgeryStartTime = date('Y-m-d H:i:s', strtotime($surgeryStartTime));
                $records = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->where('BLLB', $ruleMap8010)
                    ->whereNotIn('MBLB', $recordTypes)
                    ->where($ruleMap8011, '>=', date('Y-m-d H:i', strtotime($startCheckTime)))
                    ->where($ruleMap8011, '<=', $surgeryStartTime)
                    ->orderBy($ruleMap8011, 'asc')
                    ->get()->toArray();

                $surgeryGroup = [
                    'status' => 0,
                    'content' => []
                ];

                // 创建手术基础信息
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "(手麻)手术名称【{$surgeryName}】"
                ];
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "(手麻)手术开始时间【{$surgeryStartTime}】"
                ];
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "(手麻)术者【{$surgeon}】"
                ];

                // 检查是否有病程记录
                $hasSurgeonSignature = false;
                $surgeonSignedRecords = [];
                $unsignedRecords = [];
                $untimelySignedRecords = [];

                if (empty($records)) {
                    // 没有病程记录
                    $surgeryGroup['content'][] = [
                        'status' => 0,
                        'content' => "术者术前查房记录【无】"
                    ];
                } else {
                    // 有病程记录，检查是否有术者签名
                    foreach ($records as $record) {
                        $blmc = $record['BLMC'] ?? '未知病历';

                        // 跳过会诊、危急值
                        foreach ($excludeKeywords as $keyword) {
                            if (strpos($blmc, $keyword) !== false) {
                                continue 2;
                            }
                        }

                        $issign = false;

                        // 检查标题是否包含术者
                        if (strpos($blmc, $surgeon1) !== false) {
                            $issign = true;
                        }

                        // 检查签名中是否包含术者
                        $blsy = EMR_BL_BLSY::query()->where('BLBH', $record['BLBH'])->where('FG_ACTIVE', 1)->get()->toArray();
                        if (!empty($blsy)) {
                            foreach ($blsy as $item) {
                                if (empty($item['SYYS'])) {
                                    continue;
                                }
                                $ysname = Staff::query()->where('code', $item['SYYS'])->first()['name'] ?? "";
                                if (strpos($ysname, $surgeon1) !== false) {
                                    $issign = true;
                                    break;
                                }
                            }
                        }

                        if ($issign) {
                            // 检查签名时间是否在规定时间内
                            $signTime = isset($record[$ruleMap8002]) ? $record[$ruleMap8002] : '';
                            if (!empty($signTime)) {
                                if (strtotime($startCheckTime) <= strtotime($signTime) && strtotime($signTime) <= strtotime($surgeryStartTime)) {
                                    $hasSurgeonSignature = true;
                                    $surgeonSignedRecords[] = $record;
                                } else {
                                    // 签名超时
                                    $untimelySignedRecords[] = $record;
                                }
                            } else {
                                // 虽然有术者签名，但无签名时间
                                $unsignedRecords[] = $record;
                            }
                        }
                    }

                    // 根据检查结果生成内容
                    if ($hasSurgeonSignature) {
                        // 有有效的术者签名
                        $insert['szsqcf_fz'] += 1;
                        $surgeryGroup['status'] = 1;
                        $surgeryGroup['content'][] = [
                            'status' => 1,
                            'content' => "术者术前查房记录【{$surgeonSignedRecords[0]['BLMC']}】"
                        ];
                        $surgeryGroup['content'][] = [
                            'status' => 1,
                            'content' => "首次签名时间【{$surgeonSignedRecords[0][$ruleMap8002]}】"
                        ];
                    } else {
                        // 没有有效的术者签名
                        if (!empty($unsignedRecords)) {
                            foreach ($unsignedRecords as $record) {
                                $surgeryGroup['content'][] = [
                                    'status' => 0,
                                    'content' => "术者术前查房记录【{$record['BLMC']}】【未签名】"
                                ];
                            }
                        } elseif (!empty($untimelySignedRecords)) {
                            foreach ($untimelySignedRecords as $record) {
                                $surgeryGroup['content'][] = [
                                    'status' => 0,
                                    'content' => "术者术前查房记录【{$record['BLMC']}】"
                                ];
                                $surgeryGroup['content'][] = [
                                    'status' => 0,
                                    'content' => "首次签名时间【{$record[$ruleMap8002]}（超时）】"
                                ];
                            }
                        } else {
                            $surgeryGroup['content'][] = [
                                'status' => 0,
                                'content' => "术者术前查房记录【无】"
                            ];
                        }
                    }
                }

                $errorContent[] = $surgeryGroup;
            }

            if (!empty($errorContent)) {
                $insert['szsqcf_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        IndexCatalog::query()->where("index_name", "=", "szsqcf")->update(["status" => 2, 'quality_time' => time()]);
    }


    /**
     * @param array $data
     * @return bool
     * 保存指标结果
     */
    public function saveIndexRes($data = [], $patient = [], $indexName = "")
    {

        Indicator::query()->updateOrInsert(
            ['zyh' => $data['zyh']],
            [
                'AAC11N' => $patient['AAC11N'],
                'AEE03' => $patient['AEE03'],
                'AAC01' => $patient['AAC01'],
                'AAC01_YEAR' => substr($patient['AAC01'], 0, 4),
                'AAC01_MONTH' => substr($patient['AAC01'], 5, 2),
                $indexName . '_fm' => $data['fm'],
                $indexName . '_fz' => $data['fz'],
                $indexName . '_error' => $data['error']
            ]
        );


        return true;
    }

    /**
     * 获取规则映射
     *
     * @return array 规则ID到关键词的映射
     */
    private function getRuleMap()
    {
        $ruleIds = array_merge(range(1038, 1045), range(2001, 2024), [1021, 1031, 1033, 1058, 2036]);
        $ruleMap = RuleWordMap::whereIn('id', $ruleIds)->pluck('keyword', 'id')->toArray();

        // 处理所有可包含逗号的规则值
        foreach ($ruleMap as $key => $value) {
            if (strpos($value, ',') !== false) {
                $ruleMap[$key] = explode(',', $value);
            }
        }

        return $ruleMap;
    }


    private function getSurgeon($surgery, $ruleMap)
    {
        $must = [
            ['term' => ['JZHM' => $surgery['ZYH']]]
        ];

        if (is_array($ruleMap[2023])) {
            $must[] = ['terms' => ['MBLB' => $ruleMap[2023]]];
        } else {
            $must[] = ['term' => ['MBLB' => $ruleMap[2023]]];
        }

        $params = $this->BL01ESServer->clearMust()
            ->queryByMustBatch($must)
            ->source(['HJNR'])
            ->getParams();

        try {
            $result = app('es')->search($params);
            list($data, $total) = $this->BL01ESServer->getDataByEs($result);

            if (empty($data[0]['HJNR'])) {
                return "无";
            }

            $content = $data[0]['HJNR'];
            $pattern = "/{$ruleMap[2019]}(.*?){$ruleMap[2020]}/";
            preg_match($pattern, $content, $matches);

            if (!empty($matches[1])) {
                return trim(trim($matches[1], '{}'));
            }

            return "无";
        } catch (\Exception $e) {
            return "无";
        }
    }



    // 增一个辅助方法来检查日期时间格式
    private function isValidDateTime($dateTime)
    {
        $format = 'Y-m-d H:i';

        $d = DateTime::createFromFormat($format, $dateTime);

        return $d && $d->format($format) === $dateTime;
    }

    private function getTimeField($ruleMap)
    {
        // 返回用于获取签名时间的字段名
        return $ruleMap[2001] ?? 'first_blsy_time';
    }



    private function getPreOpSurgeonSignature($surgery, $ruleMap)
    {
        // 先查询病历信息
        $must = [
            ['term' => ['JZHM' => $surgery['ZYH']]]
        ];

        if (is_array($ruleMap[2018])) {
            $must[] = ['terms' => ['MBLB' => $ruleMap[2018]]];
        } else {
            $must[] = ['term' => ['MBLB' => $ruleMap[2018]]];
        }

        $params = $this->BL01ESServer->clearMust()
            ->queryByMustBatch($must)
            ->source(['BLBH'])
            ->getParams();

        try {
            $result = app('es')->search($params);
            list($data, $total) = $this->BL01ESServer->getDataByEs($result);

            if (empty($data)) {
                return "无";
            }

            // 查询签名信息
            $blsyMust = [
                ['term' => ['BLBH' => $data[0]['BLBH']]]
            ];

            $blsyParams = $this->BLSYESServer->clearMust()
                ->queryByMustBatch($blsyMust)
                ->source(['SYYS'])
                ->getParams();

            $blsyResult = app('es')->search($blsyParams);
            list($blsyData, $blsyTotal) = $this->BLSYESServer->getDataByEs($blsyResult);

            if (empty($blsyData)) {
                return "无";
            }

            foreach ($blsyData as $record) {
                // 查询医生信息
                $staffMust = [
                    ['term' => ['code' => $record['SYYS']]]
                ];

                $staffParams = $this->staffESServer->clearMust()
                    ->queryByMustBatch($staffMust)
                    ->source(['name', 'code'])
                    ->getParams();

                $staffResult = app('es')->search($staffParams);
                list($staffData, $staffTotal) = $this->staffESServer->getDataByEs($staffResult);

                if (!empty($staffData[0]) && $staffData[0]['name'] == $this->getSurgeon($surgery, $ruleMap)) {
                    return "{$staffData[0]['name']} {$staffData[0]['code']}";
                }
            }

            return "无";
        } catch (\Exception $e) {
            return "无";
        }
    }

    /**
     * 计算急会诊及时到位率
     *
     * @param string $zyh 住院号，可选
     * @param string $start_time 开始时间，可选
     * @param string $end_time 结束时间，可选
     */
    public function calculateJhzjsdwl($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("jhzjsdwl", 1);
        $indicatorService = new IndicatorService();
        $patientData = $indicatorService->getPatientInfoData($zyh, $start_time, $end_time);

        $ruleMap = $this->getRuleMap();
        $timeLimit = $ruleMap[1038] ?? 10;

        foreach ($patientData as $patient) {
            $zyh = $patient['MED_REC_ID'];
            if (strtotime($patient['AAC01']) - strtotime($patient['AAB01']) <= 24 * 3600) {
                continue;
            }

            // 先查询会诊申请
            $consultations = DB::table('YS_ZY_HZSQ')
                ->where('JZHM', $zyh)
                ->where('JJBZ', 2)
                ->where('ZFBZ', 0)
                ->get();

            if ($consultations->isEmpty()) {
                continue;
            }

            $insert = [
                'zyh' => $zyh,
                'jhzjsdwl_fz' => 0,
                'jhzjsdwl_fm' => 0,
                'jhzjsdwl_error' => '',
                'AAC11N' => $patient['AAC11N'],
                'AEE03' => !empty($patient['AEE03']) ? $patient['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($patient['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($patient['AAC01'])),
                'AAC01' => $patient['AAC01']
            ];

            $errorContent = [];
            foreach ($consultations as $consultation) {
                $insert['jhzjsdwl_fm'] += 1;

                $consultationGroup = [
                    'status' => 0,
                    'content' => []
                ];

                $consultationGroup['content'][] = [
                    'status' => 1,
                    'content' => "急会诊申请时间【{$consultation->SQSJ}】"
                ];
                $consultationGroup['content'][] = [
                    'status' => 1,
                    'content' => "邀请科室【{$consultation->YQDX}】"
                ];

                $consultationOpinion = DB::table('YS_ZY_HZYJ')
                    ->where('SQXH', $consultation->SQXH)
                    ->orderBy('SXSJ', 'desc')
                    ->first();

                if ($consultationOpinion) {
                    $arrivalTime = Carbon::parse($consultationOpinion->JHZDDSJ);
                    $requestTime = Carbon::parse($consultation->SQSJ);

                    $timeDiff = $arrivalTime->diffInMinutes($requestTime);
                    $isOnTime = $timeDiff < $timeLimit;

                    if ($isOnTime) {
                        $insert['jhzjsdwl_fz'] += 1;
                        $consultationGroup['status'] = 1;
                    }

                    // 查询医生信息
                    $staffMust = [
                        ['term' => ['code' => $consultationOpinion->SXYS]]
                    ];

                    $staffParams = $this->staffESServer->clearMust()
                        ->queryByMustBatch($staffMust)
                        ->source(['name'])
                        ->getParams();

                    try {
                        $staffResult = app('es')->search($staffParams);
                        list($staffData, $staffTotal) = $this->staffESServer->getDataByEs($staffResult);
                        $writingDoctor = !empty($staffData[0]['name']) ? $staffData[0]['name'] : '未知';

                        $arrivalStatus = $isOnTime ? '正常' : "超{$timeLimit}分钟";

                        $consultationGroup['content'][] = [
                            'status' => 1,
                            'content' => "书写医师【{$writingDoctor}】"
                        ];
                        $consultationGroup['content'][] = [
                            'status' => 1,
                            'content' => "急会诊到达时间【{$consultationOpinion->JHZDDSJ}】"
                        ];
                        $consultationGroup['content'][] = [
                            'status' => $isOnTime ? 1 : 0,
                            'content' => "状态【{$arrivalStatus}】"
                        ];
                    } catch (\Exception $e) {
                        $consultationGroup['content'][] = [
                            'status' => 0,
                            'content' => "未找到医师信息"
                        ];
                    }
                } else {
                    $consultationGroup['content'][] = [
                        'status' => 0,
                        'content' => "未找到会诊意见"
                    ];
                }

                $errorContent[] = $consultationGroup;
            }

            if (!empty($errorContent) && count($errorContent) === $insert['jhzjsdwl_fm']) {
                $insert['jhzjsdwl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        IndexCatalog::query()->where("index_name", "=", "jhzjsdwl")->update(["status" => 2, 'quality_time' => time()]);
    }

    public function calculatePthzjswcl($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("pthzjswcl", 1);
        $indicatorService = new IndicatorService();
        $patientData = $indicatorService->getPatientInfoData($zyh, $start_time, $end_time);

        $ruleMap = $this->getRuleMap();
        $timeLimit = $ruleMap[1040] ?? 24;

        foreach ($patientData as $patient) {
            $zyh = $patient['MED_REC_ID'];
            if (strtotime($patient['AAC01']) - strtotime($patient['AAB01']) <= 24 * 3600) {
                continue;
            }

            // 先查询会诊申请
            $consultations = DB::table('YS_ZY_HZSQ')
                ->where('JZHM', $zyh)
                ->where('JJBZ', 1)
                ->where('ZFBZ', 0)
                ->get();

            if ($consultations->isEmpty()) {
                continue;
            }

            $insert = [
                'zyh' => $zyh,
                'pthzjswcl_fz' => 0,
                'pthzjswcl_fm' => 0,
                'pthzjswcl_error' => '',
                'AAC11N' => $patient['AAC11N'],
                'AEE03' => !empty($patient['AEE03']) ? $patient['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($patient['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($patient['AAC01'])),
                'AAC01' => $patient['AAC01']
            ];

            $errorContent = [];
            foreach ($consultations as $consultation) {
                $insert['pthzjswcl_fm'] += 1;

                $consultationGroup = [
                    'status' => 0,
                    'content' => []
                ];

                $consultationGroup['content'][] = [
                    'status' => 1,
                    'content' => "普通会诊申请时间【{$consultation->SQSJ}】"
                ];

                $consultationGroup['content'][] = [
                    'status' => 1,
                    'content' => "邀请科室【{$consultation->YQDX}】"
                ];

                $consultationOpinion = DB::table('YS_ZY_HZYJ')
                    ->where('SQXH', $consultation->SQXH)
                    ->orderBy('SXSJ', 'desc')
                    ->first();

                if ($consultationOpinion) {
                    $writeTime = Carbon::parse($consultationOpinion->SXSJ);
                    $requestTime = Carbon::parse($consultation->SQSJ);

                    $timeDiff = $writeTime->diffInHours($requestTime);
                    $isOnTime = $timeDiff < $timeLimit;

                    if ($isOnTime) {
                        $insert['pthzjswcl_fz'] += 1;
                        $consultationGroup['status'] = 1;
                    }

                    // 查询医生信息
                    $staffMust = [
                        ['term' => ['code' => $consultationOpinion->SXYS]]
                    ];

                    $staffParams = $this->staffESServer->clearMust()
                        ->queryByMustBatch($staffMust)
                        ->source(['name'])
                        ->getParams();

                    try {
                        $staffResult = app('es')->search($staffParams);
                        list($staffData, $staffTotal) = $this->staffESServer->getDataByEs($staffResult);
                        $writingDoctor = !empty($staffData[0]['name']) ? $staffData[0]['name'] : '未知';

                        $writeStatus = $isOnTime ? '正常' : "超{$timeLimit}小时";

                        $consultationGroup['content'][] = [
                            'status' => 1,
                            'content' => "书写医师【{$writingDoctor}】"
                        ];
                        $consultationGroup['content'][] = [
                            'status' => 1,
                            'content' => "书写时间【{$consultationOpinion->SXSJ}】"
                        ];
                        $consultationGroup['content'][] = [
                            'status' => $isOnTime ? 1 : 0,
                            'content' => "状态【{$writeStatus}】"
                        ];
                    } catch (\Exception $e) {
                        $consultationGroup['content'][] = [
                            'status' => 0,
                            'content' => "未找到医师信息"
                        ];
                    }
                } else {
                    $consultationGroup['content'][] = [
                        'status' => 0,
                        'content' => "未找到会诊意见"
                    ];
                }

                $errorContent[] = $consultationGroup;
            }

            if (!empty($errorContent) && count($errorContent) === $insert['pthzjswcl_fm']) {
                $insert['pthzjswcl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        IndexCatalog::query()->where("index_name", "=", "pthzjswcl")->update(["status" => 2, 'quality_time' => time()]);
    }

    /**
     * 计算术者术后24小时查房完成率
     *
     * @param string $zyh 住院号，可选
     * @param string $start_time 开始时间，可选
     * @param string $end_time 结束时间，可选
     */
    public function calculateSzshcf24($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("szshcf24", 1);
        $indicatorService = new IndicatorService();
        $patientData = $indicatorService->getPatientInfoData($zyh, $start_time, $end_time);

        $ruleMap = $this->getRuleMap();

        foreach ($patientData as $patient) {
            //删除之前的数据
            Indicator::query()->updateOrInsert(['zyh' => $patient['MED_REC_ID']], ['szshcf24_fm' => 0, 'szshcf24_fz' => 0, 'szshcf24_error' => null]);
            $ZYH = $patient['MED_REC_ID'];

            // 检查住院时间是否大于1天
            $enterTime = $patient['AAB01'];
            $exitTime = $patient['AAC01'];
            if (empty($exitTime)) {
                $exitTime = date('Y-m-d H:i:s');
            }
            $enterDate = date('Y-m-d', strtotime($enterTime));
            $exitDate = date('Y-m-d', strtotime($exitTime));
            $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);

            if ($diffDays <= 1) {
                continue;
            }

            $insert = [
                'zyh' => $ZYH,
                'szshcf24_fz' => 0,
                'szshcf24_fm' => 0,
                'szshcf24_error' => '',
                'AAC11N' => $patient['AAC11N'],
                'AEE03' => !empty($patient['AEE03']) ? $patient['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($patient['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($patient['AAC01'])),
                'AAC01' => $patient['AAC01']
            ];

            // 查询手术信息
            try {
                $ssapData = SM_SSAP::query()->where('ZYH', '=', $ZYH)->get()->toArray();
                if (empty($ssapData)) {
                    continue;
                }
            } catch (\Exception $e) {
                Log::error("calculateSzshcf24-$ZYH-error: " . $e->getMessage());
                continue;
            }

            // 获取配置
            $ruleMap8046 = RuleWordMap::query()->where('id', '=', 8046)->value('keyword');
            $ruleMap8046 = !empty($ruleMap8046) ? $ruleMap8046 : '50,296,42';
            if (strpos($ruleMap8046, ',') !== false) {
                $recordTypes = explode(',', $ruleMap8046);
            } else {
                $recordTypes = [$ruleMap8046];
            }

            $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
            $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ';

            $ruleMap8002 = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
            $ruleMap8002 = !empty($ruleMap8002) ? $ruleMap8002 : 'first_blsy_time';

            $ruleMap8047 = RuleWordMap::query()->where('id', '=', 8047)->value('keyword');
            if (strpos($ruleMap8047, ',') !== false) {
                $excludeKeywords8047 = explode(',', $ruleMap8047);
            } else {
                $excludeKeywords8047 = [$ruleMap8047];
            }

            $ruleMap8048 = RuleWordMap::query()->where('id', '=', 8048)->value('keyword');
            if (strpos($ruleMap8048, ',') !== false) {
                $excludeKeywords8048 = explode(',', $ruleMap8048);
            } else {
                $excludeKeywords8048 = [$ruleMap8048];
            }

            $errorContent = [];

            // 处理每个手术
            foreach ($ssapData as $surgery) {
                // 检查基本字段有效性
                if (
                    empty($surgery['SSRQ']) || empty($surgery['ICD9_SSCZMC']) || empty($surgery['JSRQ']) ||
                    strpos($surgery['JSRQ'], '1970-01-01') !== false || $surgery['ICD9_SSCZMC'] == 'NULL'
                ) {
                    continue;
                }

                $surgeryEndTime = $surgery['JSRQ'];
                $surgeon = $surgery['SZ'];
                $surgeon1 = $surgery['SZ'];
                $surgeonCode = $surgery['SZDM'];
                $surgeryName = $surgery['ICD9_SSCZMC'];
                $SQDH = $surgery['SQDH'];

                // 排除紧急手术（jjbz=1）
                if (!empty($SQDH)) {
                    $sssqData = SSSQ::query()->where('SQDH', $SQDH)->first();
                    if (!empty($sssqData)) {
                        $jjbz = $sssqData['JJBZ'] ?? 0;
                        if ($jjbz == 1) {
                            // 紧急手术，跳过统计
                            continue;
                        }
                    }
                }

                // 手术级别过滤
                if (!empty($ruleMap8047)) {
                    $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
                    if (!empty($ssCZ)) {
                        if (!in_array($ssCZ, $excludeKeywords8047)) {
                            if (!empty($ruleMap8048)) {
                                if (!in_array($surgeryName, $excludeKeywords8048)) {
                                    continue;
                                }
                            } else {
                                continue;
                            }
                        }
                    }
                }

                // 从staff表获取术者工号
                $surgeonBH = Staff::query()->where('code', $surgeonCode)->value('YGBH');
                $surgeon = $surgeon . "(" . $surgeonBH . ")";

                // 检查手术结束时间和术者是否有效
                if (
                    empty($surgeryEndTime) || $surgeryEndTime == '1970-01-01 00:00:00' ||
                    $surgeryEndTime == '0000-00-00 00:00:00' || empty($surgeon) || empty($surgeonCode) || empty($surgeon1)
                ) {
                    continue;
                }

                // 计数分母
                $insert['szshcf24_fm'] += 1;

                // 计算查房时间范围（手术结束后24小时）
                $endCheckTime = date('Y-m-d H:i:s', strtotime($surgeryEndTime) + 24 * 3600);

                $surgeryGroup = [
                    'status' => 0,
                    'content' => []
                ];

                // 创建手术基础信息
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "(手麻)手术名称【{$surgeryName}】"
                ];
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "(手麻)手术结束时间【{$surgeryEndTime}】"
                ];
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "(手麻)术者【{$surgeon}】"
                ];

                // 是否存在转科医嘱（手术结束后24小时内）
                $hasTransferOrder = false;
                try {
                    $hasTransferOrder = Yzb::query()
                        ->where('ZYH', $ZYH)
                        ->where('KZSJ', '>=', date('Y-m-d H:i', strtotime($surgeryEndTime)))
                        ->where('KZSJ', '<=', $endCheckTime)
                        ->where('YZMC', 'like', '%转科%')
                        ->exists();
                } catch (\Exception $e) {
                    Log::error("calculateSzshcf24-$ZYH-查询转科医嘱error: " . $e->getMessage());
                }

                // 查询病程记录（若存在转科医嘱，仅查BLLB=294；否则按配置MBLB）
                $bl01Query = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->where($ruleMap8011, '>=', date('Y-m-d H:i', strtotime($surgeryEndTime)))
                    ->where($ruleMap8011, '<=', $endCheckTime)
                    ->orderBy($ruleMap8011, 'asc');
                if ($hasTransferOrder) {
                    $bl01Query->where('BLLB', '294');
                } else {
                    $bl01Query->whereIn('MBLB', $recordTypes);
                }
                $records = $bl01Query->get()->toArray();

                // 检查是否有病程记录
                if (empty($records)) {
                    // 无查房记录
                    $surgeryGroup['content'][] = [
                        'status' => 0,
                        'content' => "手术结束后24小时内术者查房记录【无】"
                    ];
                } else {
                    // 检查病程记录是否有术者签名
                    $hasSurgeonSignature = false;
                    $surgeonSignedRecords = [];
                    $unsignedRecords = [];
                    $untimelySignedRecords = [];

                    foreach ($records as $record) {
                        $blmc = $record['BLMC'] ?? '未知病历';

                        // 查看是否有术者签名
                        $signatureData = false;
                        if (strpos($blmc, $surgeon1) !== false) {
                            $signatureData = true;
                        }
                        // 转科场景：正文包含术者也视为有效
                        if ($hasTransferOrder) {
                            $hjnr = EMR_BL_BLXG::query()->where('BLBH', $record['BLBH'])->value('HJNR');
                            if (!empty($hjnr) && strpos($hjnr, $surgeon1) !== false) {
                                $signatureData = true;
                            }
                        }

                        // 检查签名中是否包含术者
                        $blsy = EMR_BL_BLSY::query()->where('BLBH', $record['BLBH'])->where('FG_ACTIVE', 1)->get()->toArray();
                        if (!empty($blsy)) {
                            foreach ($blsy as $item) {
                                if (empty($item['SYYS'])) {
                                    continue;
                                }
                                $ysname = Staff::query()->where('code', $item['SYYS'])->first()['name'] ?? "";
                                if (strpos($ysname, $surgeon1) !== false) {
                                    $signatureData = true;
                                    break;
                                }
                            }
                        }

                        if ($signatureData) {
                            // 检查签名时间是否在规定时间内
                            $signTime = isset($record[$ruleMap8002]) ? $record[$ruleMap8002] : '';
                            if (!empty($signTime)) {
                                if (strtotime($signTime) <= strtotime($endCheckTime)) {
                                    $hasSurgeonSignature = true;
                                    $surgeonSignedRecords[] = $record;
                                    break; // 找到有效签名，跳出循环
                                } else {
                                    // 签名超时
                                    $untimelySignedRecords[] = $record;
                                }
                            } else {
                                // 虽然有术者签名，但无签名时间
                                $unsignedRecords[] = $record;
                            }
                        }
                    }

                    // 根据检查结果生成内容
                    if ($hasSurgeonSignature) {
                        // 有有效的术者签名
                        $insert['szshcf24_fz'] += 1;
                        $surgeryGroup['status'] = 1;
                        $surgeryGroup['content'][] = [
                            'status' => 1,
                            'content' => "术者术后查房记录【{$surgeonSignedRecords[0]['BLMC']}】"
                        ];
                        $surgeryGroup['content'][] = [
                            'status' => 1,
                            'content' => "首次签名时间【{$surgeonSignedRecords[0][$ruleMap8002]}】"
                        ];
                    } else {
                        // 没有有效的术者签名
                        if (!empty($unsignedRecords)) {
                            foreach ($unsignedRecords as $record) {
                                $surgeryGroup['content'][] = [
                                    'status' => 0,
                                    'content' => "术者术后查房记录【{$record['BLMC']}】【术者未签名】"
                                ];
                            }
                        } elseif (!empty($untimelySignedRecords)) {
                            foreach ($untimelySignedRecords as $record) {
                                $surgeryGroup['content'][] = [
                                    'status' => 0,
                                    'content' => "术者术后查房记录【{$record['BLMC']}】"
                                ];
                                $surgeryGroup['content'][] = [
                                    'status' => 0,
                                    'content' => "首次签名时间【{$record[$ruleMap8002]}（超时）】"
                                ];
                            }
                        } else {
                            $surgeryGroup['content'][] = [
                                'status' => 0,
                                'content' => "手术结束后24小时内术者查房记录【无】"
                            ];
                        }
                    }
                }

                $errorContent[] = $surgeryGroup;
            }

            if (!empty($errorContent)) {
                $insert['szshcf24_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        IndexCatalog::query()->where("index_name", "=", "szshcf24")->update(["status" => 2, 'quality_time' => time()]);
    }



    // 获取手术结束时间
    private function getSurgeryEndTime($surgery, $ruleMap)
    {
        $must = [
            ['term' => ['JZHM' => $surgery['ZYH']]]
        ];

        if (is_array($ruleMap[2023])) {
            $must[] = ['terms' => ['MBLB' => $ruleMap[2023]]];
        } else {
            $must[] = ['term' => ['MBLB' => $ruleMap[2023]]];
        }

        $params = $this->BL01ESServer->clearMust()
            ->queryByMustBatch($must)
            ->source(['HJNR'])
            ->getParams();

        try {
            $result = app('es')->search($params);
            list($data, $total) = $this->BL01ESServer->getDataByEs($result);

            if (empty($data)) {
                return "无";
            }

            $content = $data[0]['HJNR'] ?? '';
            if (empty($content)) {
                return "无";
            }

            // 处理内容，替换中文冒号为英文冒号
            $content = str_replace('：', ':', $content);

            // 尝试匹配第一种格式：手术日期结束时间：{yyyy-mm-dd hh:ii}
            if (preg_match("/{$ruleMap[2013]}.*?\{(.*?)\}/", $content, $matches1)) {
                return trim($matches1[1], '{}');
            }

            // 如果上面的匹配失，尝试匹配格式：手术日期开始时间：{yyyy-mm-dd hh:ii}结束时间：{yyyy-mm-dd hh:ii}
            if (preg_match("/{$ruleMap[2021]}.*?\{(.*?)\}.*?{$ruleMap[2022]}.*?\{(.*?)\}/", $content, $matches)) {
                return trim($matches[2]);
            }

            // 尝试匹配第二种格式：手术日期{yyyy-mm-dd} 手术时间{hh时ii分}-{hh时ii分}术前诊断
            if (preg_match("/{$ruleMap[2014]}.*?\{(\d{4}-\d{2}-\d{2})\}.*?{$ruleMap[2016]}.*?-\{(\d{1,2}时\d{1,2}分)\}/", $content, $matches2)) {
                $date = trim($matches2[1], '{}');
                $time = str_replace(['时', '分'], [':', ''], trim($matches2[2], '{}'));
                return $date . ' ' . $time;
            }

            return "无";
        } catch (\Exception $e) {
            return "无";
        }
    }

    private function getPostOpSummary($surgery, $ruleMap)
    {
        $must = [
            ['term' => ['JZHM' => $surgery['ZYH']]],
            ['terms' => ['MBLB' => $ruleMap[2024]]]  // ruleMap[2024] 是数组
        ];

        $params = $this->BL01ESServer->clearMust()
            ->queryByMustBatch($must)
            ->source(['BLMC', 'first_blsy_time'])
            ->from(0)
            ->size(1000)
            ->getParams();

        $signature = $this->getPreOpSurgeonSignature($surgery, $ruleMap);
        $surgeryEndTime = $this->getSurgeryEndTime($surgery, $ruleMap);

        try {
            $result = app('es')->search($params);
            list($data, $total) = $this->BL01ESServer->getDataByEs($result);

            if (empty($data)) {
                return [
                    'name' => '无',
                    'time' => '无',
                    'signTime' => '无',
                    'signature' => $signature,
                    'surgeryEndTime' => $surgeryEndTime,
                    'status' => 0
                ];
            }


            if ($surgeryEndTime == "无" || !$this->isValidDateTime($surgeryEndTime)) {
                $surgeryEndTime = "时间格式错误";
                return [
                    'name' => $data[0]['BLMC'],
                    'time' => $data[0]['first_blsy_time'],
                    'signTime' => $data[0]['first_blsy_time'] . '（结束时间获取失）',
                    'signature' => $this->getPreOpSurgeonSignature($surgery, $ruleMap),
                    'surgeryEndTime' => $surgeryEndTime,
                    'status' => 0
                ];
            }

            $surgeryEndTimeObj = Carbon::parse($surgeryEndTime);
            $surgeryEndPlus24Hours = $surgeryEndTimeObj->copy()->addHours(24);

            // 遍历所有病程记录，找到符合时间范围的记录
            foreach ($data as $record) {
                $signTime = $record['first_blsy_time'] ?? '无';
                if ($signTime == '无')
                    continue;

                $signTimeObj = Carbon::parse($signTime);

                // 检查签名时间是否在手术结束后24小时内
                if ($signTimeObj->between($surgeryEndTimeObj, $surgeryEndPlus24Hours)) {


                    $status = 1;
                    if ($signTimeObj->lt($surgeryEndTimeObj)) {
                        $signTimeStatus = '（手术结束前）';
                        $status = 0;
                    } elseif ($signTimeObj->gt($surgeryEndPlus24Hours)) {
                        $signTimeStatus = '（超24小时）';
                        $status = 0;
                    } else {
                        $signTimeStatus = '（24小时内）';
                    }


                    return [
                        'name' => $record['BLMC'],
                        'time' => $signTime,
                        'signTime' => "{$signTime}{$signTimeStatus}",
                        'signature' => $signature,
                        'surgeryEndTime' => $surgeryEndTime,
                        'status' => $status
                    ];
                }
            }

            // 如果没有找到符合条件的记录，返回第一记录的信息

            $signTime = $data[0]['first_blsy_time'] ?? '无';

            if ($signTime == '无') {
                return [
                    'name' => $data[0]['BLMC'],
                    'time' => $data[0]['first_blsy_time'],
                    'signTime' => '无',
                    'signature' => $signature,
                    'surgeryEndTime' => $surgeryEndTime,
                    'status' => 0
                ];
            }
            $signTimeObj = Carbon::parse($signTime);


            // 检查签名时间是否在手术结束后24小时内



            $status = 1;
            if ($signTimeObj->lt($surgeryEndTimeObj)) {
                $signTimeStatus = '（手术结束前）';
                $status = 0;
            } elseif ($signTimeObj->gt($surgeryEndPlus24Hours)) {
                $signTimeStatus = '（超24小时）';
                $status = 0;
            } else {
                $signTimeStatus = '（24小时内）';
            }


            return [
                'name' => $data[0]['BLMC'],
                'time' => $signTime,
                'signTime' => "{$signTime}{$signTimeStatus}",
                'signature' => $signature,
                'surgeryEndTime' => $surgeryEndTime,
                'status' => $status
            ];
        } catch (\Exception $e) {
            return [
                'name' => '无',
                'time' => '无',
                'signTime' => '无',
                'signature' => $signature,
                'surgeryEndTime' => $surgeryEndTime,
                'status' => 0
            ];
        }
    }


    // 计算手术后出现严重脏器功能损害的并发症的患者完成疑难病例讨论率
    public function calculateShshbfzbltl($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("shshbfzbltl", 1);
        $indicatorService = new IndicatorService();
        $patientData = $indicatorService->getPatientInfoData($zyh, $start_time, $end_time);

        $ruleMap = $this->getRuleMap();
        //$severeDiagnoses = $ruleMap[1044] ?? ['心脏衰竭', '肺衰竭', '肝脏衰竭', '肾脏衰竭'];
        $discussionMBLB = $ruleMap[1045] ?? '44';

        foreach ($patientData as $patient) {
            $zyh = $patient['MED_REC_ID'];
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $zyh], ['shshbfzbltl_fz' => 0, 'shshbfzbltl_fm' => 0, 'shshbfzbltl_error' => null]);
            // 检查病案首页是否有手术
            /* $mainOperation = MainOperation::query()->where('AAA28', $zyh)->get()->toArray();
            if (empty($mainOperation)) {
                continue;
            } */

            //查询手麻
            $isss = false;
            $ssap = SM_SSAP::query()->where('ZYH', $zyh)->get()->toArray();
            if (empty($ssap)) {
                continue;
            } else {
                foreach ($ssap as $s) {
                    // 手术级别过滤（复用szsqcf：ruleMap8047/8048）
                    $surgeryName = $s['ICD9_SSCZMC'];
                    $ruleMap8047 = RuleWordMap::query()->where('id', '=', 8047)->value('keyword');
                    $excludeKeywords8047 = strpos($ruleMap8047, ',') !== false ? explode(',', $ruleMap8047) : [$ruleMap8047];
                    $ruleMap8048 = RuleWordMap::query()->where('id', '=', 8048)->value('keyword');
                    $excludeKeywords8048 = strpos($ruleMap8048, ',') !== false ? explode(',', $ruleMap8048) : [$ruleMap8048];
                    if (!empty($ruleMap8047)) {
                        $ssLB = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
                        if (!empty($ssLB)) {
                            if (in_array($ssLB, $excludeKeywords8047)) {
                                $isss = true;
                                break;
                            }
                        }
                    }
                }
                if (!$isss) {
                    continue;
                }
            }

            // 检查是否有严重脏器功能损害的诊断
            //更换成查mysql表
            $mainDiagnosis = MainDiagnosis::query()->where('AAA28', $zyh)->where('RYQK', '无')->get()->toArray();
            $otherDiagnosis = OtherDiagnosis::query()->where('AAA28', $zyh)->where('RYQK', '无')->get()->toArray();
            $severeDiagnosis = array_merge($mainDiagnosis, $otherDiagnosis);
            if (empty($severeDiagnosis)) {
                continue;
            }

            $iszd = false;
            $zdid = '';
            $zdmc = '';
            foreach ($severeDiagnosis as $diagnosis) {
                $diagnosisName = $diagnosis['ICD10_ID1'];
                //提取前5位字符比如Z50.122，提取Z50.1，带小数点
                $diagnosisName = substr($diagnosisName, 0, 7);
                //var_dump($diagnosisName);
                if ($diagnosisName == 'J98.403') {
                    $iszd = true;
                    $zdid = $diagnosis['ICD10_ID1'];
                    $zdmc = $diagnosis['ICD10_NAME'];
                    break;
                } else {
                    $diagnosisName = substr($diagnosisName, 0, 3);
                    if ($diagnosisName == 'I50' || $diagnosisName == 'N17' || $diagnosisName == 'N18' || $diagnosisName == 'K72') {
                        $iszd = true;
                        $zdid = $diagnosis['ICD10_ID1'];
                        $zdmc = $diagnosis['ICD10_NAME'];
                        break;
                    }
                }
            }
            if (!$iszd) {
                continue;
            }

            $insert = [
                'zyh' => $zyh,
                'shshbfzbltl_fz' => 0,
                'shshbfzbltl_fm' => 0,
                'shshbfzbltl_error' => '',
                'AAC11N' => $patient['AAC11N'],
                'AEE03' => !empty($patient['AEE03']) ? $patient['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($patient['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($patient['AAC01'])),
                'AAC01' => $patient['AAC01']
            ];

            $insert['shshbfzbltl_fm'] += 1;

            $must = [
                ['term' => ['JZHM' => $zyh]]
            ];

            if (is_array($discussionMBLB)) {
                $must[] = ['terms' => ['MBLB' => $discussionMBLB]];
            } else {
                $must[] = ['term' => ['MBLB' => $discussionMBLB]];
            }

            $params = $this->BL01ESServer->clearMust()
                ->queryByMustBatch($must)
                ->source(['BLMC', 'first_blsy_time'])
                ->getParams();

            try {
                $result = app('es')->search($params);
                list($data, $total) = $this->BL01ESServer->getDataByEs($result);

                $surgeryGroup = [
                    'status' => 0,
                    'content' => []
                ];

                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "诊断编码【{$zdid}】"
                ];
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "诊断名称【{$zdmc}】"
                ];

                if (!empty($data)) {
                    $discussionRecord = $data[0];
                    $insert['shshbfzbltl_fz'] += 1;
                    $surgeryGroup['status'] = 1;

                    $surgeryGroup['content'][] = [
                        'status' => 1,
                        'content' => "疑难病例讨论结论记录【{$discussionRecord['BLMC']}】"
                    ];
                    $surgeryGroup['content'][] = [
                        'status' => 1,
                        'content' => "疑难病例讨论结论记录完成时间【{$discussionRecord['first_blsy_time']}】"
                    ];
                } else {
                    $surgeryGroup['content'][] = [
                        'status' => 0,
                        'content' => "疑难病例讨论结论记录【无】"
                    ];
                }

                $errorContent = [$surgeryGroup];
                $insert['shshbfzbltl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            } catch (\Exception $e) {
                continue;
            }
        }

        IndexCatalog::query()->where("index_name", "=", "shshbfzbltl")->update(["status" => 2, 'quality_time' => time()]);
    }


    /**
     * 批量获取签名信息
     */
    private function batchGetSignatures($blbhList)
    {
        $signatures = [];
        $must = [
            ['terms' => ['BLBH' => array_unique($blbhList)]]
        ];

        $params = $this->BLSYESServer->clearMust()
            ->queryByMustBatch($must)
            ->source(['BLBH', 'SYYS'])
            ->getParams();

        try {
            $result = app('es')->search($params);
            list($blsyData, $total) = $this->BLSYESServer->getDataByEs($result);
            foreach ($blsyData as $blsy) {
                if (!isset($signatures[$blsy['BLBH']])) {
                    $signatures[$blsy['BLBH']] = [];
                }
                $signatures[$blsy['BLBH']][] = $blsy['SYYS'];
            }
        } catch (\Exception $e) {
            // 处理异常
        }

        return $signatures;
    }

    /**
     * 计算二级医师查房频次达标率
     */
    public function calculateEjyscf($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("ejyscf", 1);
        $indicatorService = new IndicatorService();
        $patientData = $indicatorService->getPatientInfoData($zyh, $start_time, $end_time);

        $ruleMap = $this->getRuleMap();
        // 二级医师查房关键字
        $ejyscfMap = $ruleMap[1021];
        if (!is_array($ejyscfMap)) {
            $ejyscfMap = explode(",", $ejyscfMap);
        }
        // 获取时间字段
        $timeField = $ruleMap[2001] ?? 'first_blsy_time';

        foreach ($patientData as $patient) {
            $zyh = $patient['MED_REC_ID'];
            // 如果住院时间不超过24小时则跳过
            if (strtotime($patient['AAC01']) - strtotime($patient['AAB01']) <= 24 * 3600) {
                continue;
            }

            $insert = [
                'zyh' => $zyh,
                'ejyscf_fz' => 0,
                'ejyscf_fm' => 0,
                'ejyscf_error' => '',
                'AAC11N' => $patient['AAC11N'],
                'AEE03' => !empty($patient['AEE03']) ? $patient['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($patient['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($patient['AAC01'])),
                'AAC01' => $patient['AAC01']
            ];

            $startTime = $patient['AAB01'];  // 实际开始时间
            $endTime = $patient['AAC01'];    // 实际结束时间
            $errorContent = [];

            while (true) {
                // 计算当前周期的结束时间
                // 从开始日期的零点算起7天
                $cycleStartDay = date('Y-m-d', strtotime($startTime));  // 取开始日期的日期部分
                $cycleEndTime = date('Y-m-d 23:59:59', strtotime($cycleStartDay . ' +6 days'));  // 加6天到23:59:59

                if ($cycleEndTime > $endTime) {
                    $cycleEndTime = $endTime;
                }

                // 计算当前周期的天数
                $startDay = date('Y-m-d', strtotime($startTime));
                $endDay = date('Y-m-d', strtotime($cycleEndTime));
                $cycleDays = ((strtotime($endDay) - strtotime($startDay)) / (24 * 3600)) + 1;  // +1因为包含起始日

                // 计算应查房次数
                $requiredVisits = $this->getRequiredVisits($cycleDays);

                // 分母加1（每个周期算1）
                if ($requiredVisits > 0) {
                    $insert['ejyscf_fm'] += 1;
                }

                $surgeryGroup = [
                    'status' => 0,
                    'content' => []
                ];

                // 添加周期信息
                $surgeryGroup['content'] = [
                    [
                        'status' => 1,
                        'content' => "查房周期【{$cycleDays}天/{$requiredVisits}次】【{$startTime}】至【{$cycleEndTime}】"
                    ]
                ];

                // 查询该周期内的病程记录
                $must = [
                    ['term' => ['JZHM' => $zyh]],
                    ['term' => ['BLLB' => 294]],
                    ['range' => ['CJSJ' => ['from' => $startTime, 'to' => $cycleEndTime]]]  // 使用 CJSJ 查询
                ];
                $mustNot = ['term' => ['BLZT' => $ruleMap[2036]]];

                $params = $this->BL01ESServer->clearMust()
                    ->queryByMustBatch($must)
                    ->queryByMustNot($mustNot)
                    ->source(['BLMC', 'BLBH', 'CJSJ', $timeField])  // 同时获取 CJSJ 和 timeField
                    ->orderBy('CJSJ', 'desc')  // 按 CJSJ 排序
                    ->getParams();

                try {
                    $validVisits = 0;
                    $visitRecords = [];

                    $result = app('es')->search($params);
                    list($records, $total) = $this->BL01ESServer->getDataByEs($result);

                    foreach ($records as $record) {
                        // 查询签名信息
                        $blsyMust = [
                            ['term' => ['BLBH' => $record['BLBH']]]
                        ];

                        $blsyParams = $this->BLSYESServer->clearMust()
                            ->queryByMustBatch($blsyMust)
                            ->source(['SYYS'])
                            ->getParams();

                        $blsyResult = app('es')->search($blsyParams);
                        list($blsyData, $blsyTotal) = $this->BLSYESServer->getDataByEs($blsyResult);

                        $hasValidSignature = false;
                        $signatureInfo = [];

                        if (!empty($blsyData)) {
                            foreach ($blsyData as $blsy) {
                                $staffMust = [
                                    ['term' => ['code' => $blsy['SYYS']]]
                                ];

                                $staffParams = $this->staffESServer->clearMust()
                                    ->queryByMustBatch($staffMust)
                                    ->source(['name', 'ygjb_text'])
                                    ->getParams();

                                $staffResult = app('es')->search($staffParams);
                                list($staffData, $staffTotal) = $this->staffESServer->getDataByEs($staffResult);

                                if (!empty($staffData)) {
                                    $signatureInfo[] = [
                                        'name' => $staffData[0]['name'],
                                        'title' => $staffData[0]['ygjb_text']
                                    ];
                                    if (in_array($staffData[0]['ygjb_text'], $ejyscfMap)) {
                                        $hasValidSignature = true;
                                    }
                                }
                            }
                        }

                        // 使用 timeField 判断是否超时
                        $recordTime = strtotime($record[$timeField]);
                        $status = 0;
                        $timeStatus = '';

                        // 先判断是否有主治医师签名
                        if ($hasValidSignature) {
                            // 再判断时间是否在范围内
                            if ($recordTime < strtotime($startTime)) {
                                $timeStatus = '（提前）';
                            } elseif ($recordTime > strtotime($cycleEndTime)) {
                                $timeStatus = '（超时）';
                            } else {
                                $status = 1;
                                $validVisits++;
                            }
                        }

                        // 构建签名字符串
                        $signatureStr = '';
                        foreach ($signatureInfo as $sig) {
                            if ($signatureStr !== '') {
                                $signatureStr .= '|';
                            }
                            $signatureStr .= "{$sig['name']}】职称【{$sig['title']}";
                        }

                        $visitRecords[] = [
                            'status' => $status,
                            'content' => "【{$record['BLMC']}{$timeStatus}】医师签名【{$signatureStr}】"
                        ];
                    }

                    // 添加查房次数信息（在记录之前）
                    $surgeryGroup['content'][] = [
                        'status' => ($validVisits >= $requiredVisits) ? 1 : 0,  // 使用 1/0 而不是 true/false
                        'content' => "医师查房：【{$validVisits}次】"
                    ];

                    // 添加所有查房记录
                    $surgeryGroup['content'] = array_merge(
                        [
                            $surgeryGroup['content'][0],  // 周期信息
                            $surgeryGroup['content'][1]   // 查房次数信息
                        ],
                        $visitRecords
                    );

                    $errorContent[] = $surgeryGroup;
                } catch (\Exception $e) {
                    continue;
                }

                // 如果已经处理到最后一天，退出循环
                if ($cycleEndTime >= $endTime) {
                    break;
                }

                // 更新开始时间为下一个周期的开始（下一天的零点）
                $startTime = date('Y-m-d 00:00:00', strtotime($cycleEndTime) + 1);
            }

            if (!empty($errorContent)) {
                $insert['ejyscf_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        IndexCatalog::query()->where("index_name", "=", "ejyscf")->update(["status" => 2, 'quality_time' => time()]);
    }

    /**
     * 根据天数计算应查房次数
     */
    private function getRequiredVisits($days)
    {
        if ($days <= 2) {
            return 0;
        } elseif ($days <= 4) {  // 3-4天查1次
            return 1;
        } elseif ($days <= 5) {  // 5天查1次
            return 1;
        } elseif ($days <= 6) {  // 6天查2次
            return 2;
        } else {  // 7天查3次
            return 3;
        }
    }

    /**
     * 计算值班期间诊疗处置记录率
     *
     * @param string $zyh 住院号，可选
     * @param string $start_time 开始时间，可选
     * @param string $end_time 结束时间，可选
     */
    public function calculateZbqjzlcz($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("zbqjzlcz", 1);
        $indicatorService = new IndicatorService();
        $patientData = $indicatorService->getPatientInfoData($zyh, $start_time, $end_time);

        foreach ($patientData as $patient) {
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $patient['MED_REC_ID']], ['zbqjzlcz_fm' => 0, 'zbqjzlcz_fz' => 0, 'zbqjzlcz_error' => null]);

            $ZYH = $patient['MED_REC_ID'];

            // 检查住院时间是否大于1天
            $enterTime = $patient['AAB01'];
            $exitTime = $patient['AAC01'];
            if (empty($exitTime)) {
                $exitTime = date('Y-m-d H:i:s');
            }
            $enterDate = date('Y-m-d', strtotime($enterTime));
            $exitDate = date('Y-m-d', strtotime($exitTime));
            $diffDays = (strtotime($exitDate) - strtotime($enterDate)) / (24 * 3600);

            // 排除住院1天的患者
            if ($diffDays <= 1) {
                continue;
            }

            $insert = [
                'zyh' => $ZYH,
                'zbqjzlcz_fz' => 0,
                'zbqjzlcz_fm' => 0,
                'zbqjzlcz_error' => null,
                'AAC11N' => $patient['AAC11N'],
                'AEE03' => !empty($patient['AEE03']) ? $patient['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($patient['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($patient['AAC01'])),
                'AAC01' => $patient['AAC01']
            ];

            // 获取配置
            // 节假日日期列表
            $ruleMap8092 = RuleWordMap::query()->where('id', '=', 8092)->value('keyword');
            $holidays = [];
            if (!empty($ruleMap8092)) {
                if (strpos($ruleMap8092, ',') !== false) {
                    $holidays = explode(',', $ruleMap8092);
                } else {
                    $holidays = [$ruleMap8092];
                }
            }

            // 调休日期列表
            $ruleMap8093 = RuleWordMap::query()->where('id', '=', 8093)->value('keyword');
            $workdays = [];
            if (!empty($ruleMap8093)) {
                if (strpos($ruleMap8093, ',') !== false) {
                    $workdays = explode(',', $ruleMap8093);
                } else {
                    $workdays = [$ruleMap8093];
                }
            }

            $errorContent = [];

            // 从入院时间到出院时间按天循环
            $currentDate = $enterDate;
            while (strtotime($currentDate) <= strtotime($exitDate)) {
                $dayOfWeek = date('w', strtotime($currentDate)); // 0(周日)到6(周六)

                // 定义时间段
                $timeRanges = [];
                $isHoliday = false;
                $isWorkday = false;
                $isExitDay = ($currentDate === $exitDate); // 判断是否是出院日期

                // 检查是否是节假日
                if (in_array($currentDate, $holidays)) {
                    $isHoliday = true;
                }

                // 检查是否是调休日
                if (in_array($currentDate, $workdays)) {
                    $isWorkday = true;
                }

                // 出院当天特殊处理
                if ($isExitDay) {
                    // 判断出院当天是否为工作日
                    $isExitDayWorkday = false;
                    if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                        // 周一到周五且非节假日，是工作日
                        if (!$isHoliday) {
                            $isExitDayWorkday = true;
                        }
                    } else {
                        // 周六日如果是调休日，是工作日
                        if ($isWorkday) {
                            $isExitDayWorkday = true;
                        }
                    }

                    if ($isExitDayWorkday) {
                        // 出院当天是工作日，只查询0-8点
                        $timeRanges[] = [
                            'order_start' => $currentDate . ' 00:00:00',
                            'order_end' => $currentDate . ' 08:00:00',
                            'record_start' => $currentDate . ' 00:00:00',
                            'record_end' => $currentDate . ' 08:00:00',
                            'label' => '00:00-08:00（出院日-工作日）'
                        ];
                    } else {
                        // 出院当天是节假日，查询0-12点
                        $timeRanges[] = [
                            'order_start' => $currentDate . ' 00:00:00',
                            'order_end' => $currentDate . ' 12:00:00',
                            'record_start' => $currentDate . ' 00:00:00',
                            'record_end' => $currentDate . ' 12:00:00',
                            'label' => '00:00-12:00（出院日-节假日）'
                        ];
                    }
                } else {
                    // 非出院当天，保持原有逻辑
                    // 确定查询时间段
                    if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                        // 周一到周五
                        if ($isHoliday) {
                            // 节假日，查询全天（病程延伸至次日08:00）
                            $nextDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
                            $timeRanges[] = [
                                'order_start' => $currentDate . ' 00:00:00',
                                'order_end' => $currentDate . ' 23:59:59',
                                'record_start' => $currentDate . ' 00:00:00',
                                'record_end' => $nextDate . ' 08:00:00',
                                'label' => '全天-至次日08:00'
                            ];
                        } else {
                            // 非节假日，查询非工作时间
                            $timeRanges[] = [
                                'order_start' => $currentDate . ' 00:00:00',
                                'order_end' => $currentDate . ' 08:00:00',
                                'record_start' => $currentDate . ' 00:00:00',
                                'record_end' => $currentDate . ' 08:00:00',
                                'label' => '00:00-08:00'
                            ];
                            $timeRanges[] = [
                                'order_start' => $currentDate . ' 12:00:00',
                                'order_end' => $currentDate . ' 13:30:00',
                                'record_start' => $currentDate . ' 12:00:00',
                                'record_end' => $currentDate . ' 13:30:00',
                                'label' => '12:00-13:30'
                            ];
                            $nextDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
                            $timeRanges[] = [
                                'order_start' => $currentDate . ' 17:00:00',
                                'order_end' => $currentDate . ' 23:59:59',
                                'record_start' => $currentDate . ' 17:00:00',
                                'record_end' => $nextDate . ' 08:00:00',  // 查询到第二天08:00
                                'label' => '17:00-次日08:00'
                            ];
                        }
                    } else {
                        // 周六日
                        if ($isWorkday) {
                            // 调休，查询非工作时间
                            $timeRanges[] = [
                                'order_start' => $currentDate . ' 00:00:00',
                                'order_end' => $currentDate . ' 08:00:00',
                                'record_start' => $currentDate . ' 00:00:00',
                                'record_end' => $currentDate . ' 08:00:00',
                                'label' => '00:00-08:00'
                            ];
                            $timeRanges[] = [
                                'order_start' => $currentDate . ' 12:00:00',
                                'order_end' => $currentDate . ' 13:30:00',
                                'record_start' => $currentDate . ' 12:00:00',
                                'record_end' => $currentDate . ' 13:30:00',
                                'label' => '12:00-13:30'
                            ];
                            $nextDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
                            $timeRanges[] = [
                                'order_start' => $currentDate . ' 17:00:00',
                                'order_end' => $currentDate . ' 23:59:59',
                                'record_start' => $currentDate . ' 17:00:00',
                                'record_end' => $nextDate . ' 08:00:00',  // 查询到第二天08:00
                                'label' => '17:00-次日08:00'
                            ];
                        } else {
                            // 非调休，查询全天（病程延伸至次日08:00）
                            $nextDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
                            $timeRanges[] = [
                                'order_start' => $currentDate . ' 00:00:00',
                                'order_end' => $currentDate . ' 23:59:59',
                                'record_start' => $currentDate . ' 00:00:00',
                                'record_end' => $nextDate . ' 08:00:00',
                                'label' => '全天-至次日08:00'
                            ];
                        }
                    }
                }

                // 检查每个时间段内是否有医嘱，并记录（按KZSJ正序，后续逐条计分母/分子）
                $rangesWithOrders = [];
                $ordersListByIndex = [];
                foreach ($timeRanges as $index => $range) {
                    try {
                        $orders = Yzb::query()
                            ->where('ZYH', $ZYH)
                            ->where('KZSJ', '>=', $range['order_start'])
                            ->where('KZSJ', '<=', $range['order_end'])
                            ->orderBy('KZSJ', 'asc')
                            ->get()
                            ->toArray();
                        if (!empty($orders)) {
                            $rangesWithOrders[] = $index;
                            $ordersListByIndex[$index] = $orders;
                        }
                    } catch (\Exception $e) {
                        Log::error("calculateZbqjzlcz-$ZYH-查询医嘱error: " . $e->getMessage());
                    }
                }

                // 如果存在医嘱，逐条计分母/分子
                if (!empty($rangesWithOrders)) {
                    $dayGroup = [
                        'status' => 0,
                        'content' => []
                    ];

                    // 添加日期信息
                    $dayOfWeekText = ['日', '一', '二', '三', '四', '五', '六'][$dayOfWeek];
                    $dayType = '';
                    if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                        $dayType = $isHoliday ? '（节假日）' : '（工作日）';
                    } else {
                        $dayType = $isWorkday ? '（调休）' : '（周末）';
                    }

                    $dayGroup['content'][] = [
                        'status' => 1,
                        'content' => "日期【{$currentDate}】星期{$dayOfWeekText}{$dayType}"
                    ];
                    foreach ($rangesWithOrders as $index) {
                        $range = $timeRanges[$index];
                        $orders = $ordersListByIndex[$index];
                        foreach ($orders as $order) {
                            // 分母+1（每条医嘱都是一个分母）
                            $insert['zbqjzlcz_fm'] += 1;
                            // 时间段行
                            $dayGroup['content'][] = [
                                'status' => 1,
                                'content' => "医嘱时间段【{$range['order_start']} ~ {$range['order_end']}】"
                            ];
                            // 医嘱行
                            $orderName = $order['YZMC'] ?? '无';
                            $orderTime = $order['KZSJ'] ?? '无';
                            $dayGroup['content'][] = [
                                'status' => $orderName === '无' ? 0 : 1,
                                'content' => "医嘱名称【{$orderName}】"
                            ];
                            $dayGroup['content'][] = [
                                'status' => $orderTime === '无' ? 0 : 1,
                                'content' => "开嘱时间【{$orderTime}】"
                            ];
                            // 病程窗口：KZSJ -> KZSJ+24h
                            $recordStart = $orderTime;
                            $recordEnd = date('Y-m-d H:i:s', strtotime($orderTime) + 24 * 3600);
                            try {
                                $record = EMR_BL_BL01::query()
                                    ->where('JZHM', $ZYH)
                                    ->where('BLLB', '294')
                                    ->where('ZXSJ', '>=', $recordStart)
                                    ->where('ZXSJ', '<=', $recordEnd)
                                    ->orderBy('ZXSJ', 'asc')
                                    ->first();
                                if ($record) {
                                    $insert['zbqjzlcz_fz'] += 1;
                                    $dayGroup['status'] = 1;
                                    $dayGroup['content'][] = [
                                        'status' => 1,
                                        'content' => "病程记录【{$record['BLMC']}】"
                                    ];
                                    $dayGroup['content'][] = [
                                        'status' => 1,
                                        'content' => "标题时间【{$record['ZXSJ']}】"
                                    ];
                                } else {
                                    $dayGroup['content'][] = [
                                        'status' => 0,
                                        'content' => "病程记录【无】"
                                    ];
                                }
                            } catch (\Exception $e) {
                                Log::error("calculateZbqjzlcz-$ZYH-按医嘱查询病程error: " . $e->getMessage());
                            }
                        }
                    }

                    $errorContent[] = $dayGroup;
                }

                // 移动到下一天
                $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
            }

            if (!empty($errorContent)) {
                $insert['zbqjzlcz_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        IndexCatalog::query()->where("index_name", "=", "zbqjzlcz")->update(["status" => 2, 'quality_time' => time()]);
    }

    /**
     * 计算抢救记录及时完成率
     *
     * @param string $zyh 住院号，可选
     * @param string $start_time 开始时间，可选
     * @param string $end_time 结束时间，可选
     */
    public function calculateQjjljsjl($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("qjjljsjl", 1);
        $indicatorService = new IndicatorService();
        $patientData = $indicatorService->getPatientInfoData($zyh, $start_time, $end_time);

        foreach ($patientData as $patient) {
            //删除
            Indicator::updateOrInsert(['zyh' => $patient['MED_REC_ID']], ['qjjljsjl_fz' => 0, 'qjjljsjl_fm' => 0, 'qjjljsjl_error' => null]);
            $ZYH = $patient['MED_REC_ID'];

            $insert = [
                'zyh' => $ZYH,
                'qjjljsjl_fz' => 0,
                'qjjljsjl_fm' => 0,
                'qjjljsjl_error' => null,
                'AAC11N' => $patient['AAC11N'],
                'AEE03' => !empty($patient['AEE03']) ? $patient['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($patient['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($patient['AAC01'])),
                'AAC01' => $patient['AAC01']
            ];

            // 获取配置
            // 抢救关键词配置（2000）
            $ruleMap2000 = RuleWordMap::query()->where('id', '=', 2000)->value('keyword');
            $rescueKeywords = [];
            if (!empty($ruleMap2000)) {
                if (strpos($ruleMap2000, ',') !== false) {
                    $rescueKeywords = explode(',', $ruleMap2000);
                } else {
                    $rescueKeywords = [$ruleMap2000];
                }
            } else {
                // 默认值
                $rescueKeywords = ['大抢救', '小抢救'];
            }

            // 抢救记录MBLB配置（8027）
            $ruleMap8027 = RuleWordMap::query()->where('id', '=', 8027)->value('keyword');
            $ruleMap8027 = !empty($ruleMap8027) ? $ruleMap8027 : '27';
            if (strpos($ruleMap8027, ',') !== false) {
                $rescueRecordTypes = explode(',', $ruleMap8027);
            } else {
                $rescueRecordTypes = [$ruleMap8027];
            }

            $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
            $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ';

            $ruleMap8002 = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
            $ruleMap8002 = !empty($ruleMap8002) ? $ruleMap8002 : 'first_blsy_time';

            $errorContent = [];

            // 查询包含"大抢救"或"小抢救"的医嘱
            $rescueOrders = [];
            $orderIds = []; // 用于去重
            foreach ($rescueKeywords as $keyword) {
                try {
                    $orders = Yzb::query()
                        ->where('ZYH', $ZYH)
                        ->where('YZMC', 'like', '%' . $keyword . '%')
                        ->orderBy('KZSJ', 'asc')
                        ->get()
                        ->toArray();

                    if (!empty($orders)) {
                        foreach ($orders as $order) {
                            // 使用ID去重，避免同一条医嘱被多个关键词匹配到
                            $orderId = $order['ID'] ?? $order['KZSJ'] . '_' . $order['YZMC'];
                            if (!in_array($orderId, $orderIds)) {
                                $orderIds[] = $orderId;
                                $rescueOrders[] = $order;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("calculateQjjljsjl-$ZYH-查询抢救医嘱error: " . $e->getMessage());
                }
            }

            if (empty($rescueOrders)) {
                continue;
            }

            // 处理每条抢救医嘱
            foreach ($rescueOrders as $order) {
                // 分母+1
                $insert['qjjljsjl_fm'] += 1;

                $orderTime = $order['KZSJ'] ?? '';
                $orderName = $order['YZMC'] ?? '无';

                if (empty($orderTime)) {
                    continue;
                }

                $orderGroup = [
                    'status' => 0,
                    'content' => []
                ];

                $orderGroup['content'][] = [
                    'status' => 1,
                    'content' => "医嘱名称【{$orderName}】"
                ];
                $orderGroup['content'][] = [
                    'status' => 1,
                    'content' => "开嘱时间【{$orderTime}】"
                ];

                // 计算查询抢救记录的时间范围：开嘱时间前1小时到后6小时
                $recordStartTime = date('Y-m-d H:i:s', strtotime($orderTime) - 2 * 3600);
                $recordEndTime = date('Y-m-d H:i:s', strtotime($orderTime) + 6 * 3600);

                // 查询抢救记录（ZXSJ在时间范围内）
                try {
                    $rescueRecords = EMR_BL_BL01::query()
                        ->where('JZHM', $ZYH)
                        ->whereIn('MBLB', $rescueRecordTypes)
                        ->where($ruleMap8011, '>=', $recordStartTime)
                        ->where($ruleMap8011, '<=', $recordEndTime)
                        ->orderBy($ruleMap8011, 'asc')
                        ->get()
                        ->toArray();

                    if (empty($rescueRecords)) {
                        $orderGroup['content'][] = [
                            'status' => 0,
                            'content' => "抢救记录【无】"
                        ];
                        $errorContent[] = $orderGroup;
                        continue;
                    }

                    // 先收集所有抢救记录的信息
                    $validRecords = []; // 符合条件的记录
                    $invalidRecords = []; // 不符合条件的记录（超时或没签名）
                    //提取不到抢救结束时间的记录
                    $noRescueEndTimeRecords = [];

                    foreach ($rescueRecords as $record) {
                        $blmc = $record['BLMC'] ?? '未知病历';
                        $blbh = $record['BLBH'] ?? null;
                        $zxsj = $record[$ruleMap8011] ?? '';
                        $signatureTime = $record[$ruleMap8002] ?? '';

                        // 提取抢救结束时间（参考rule1021的逻辑）
                        $rescueEndTime = $this->extractRescueEndTime($blbh, $zxsj);

                        if (empty($rescueEndTime)) {
                            // 如果无法提取抢救结束时间，跳过该记录
                            $noRescueEndTimeRecords[] = $record;
                            continue;
                        }

                        // 检查签名时间是否在抢救结束时间后6小时内
                        $rescueEndTimeObj = strtotime($rescueEndTime);
                        $signatureTimeObj = !empty($signatureTime) && $signatureTime != '1970-01-01 00:00:00' && $signatureTime != '0000-00-00 00:00:00' ? strtotime($signatureTime) : null;

                        // 计算抢救结束时间+6小时
                        $rescueEndPlus6Hours = $rescueEndTimeObj + 6 * 3600;

                        $recordInfo = [
                            'blmc' => $blmc,
                            'zxsj' => $zxsj,
                            'rescueEndTime' => $rescueEndTime,
                            'signatureTime' => $signatureTime,
                            'signatureTimeObj' => $signatureTimeObj
                        ];

                        if (empty($signatureTimeObj)) {
                            // 没有签名时间，不符合条件
                            $invalidRecords[] = $recordInfo;
                        } else {
                            // 检查签名时间是否在抢救结束时间之后，且在抢救结束时间+6小时内
                            if ($signatureTimeObj >= $rescueEndTimeObj && $signatureTimeObj <= $rescueEndPlus6Hours) {
                                // 符合条件：签名时间在抢救结束时间后6小时内
                                $validRecords[] = $recordInfo;
                            } else {
                                // 不符合条件：签名时间超时
                                $invalidRecords[] = $recordInfo;
                            }
                        }
                    }

                    // 根据是否有符合的记录来决定输出
                    $hasValidRecord = !empty($validRecords);

                    if ($hasValidRecord) {
                        // 如果有符合的记录，只输出第一个符合的记录
                        $record = $validRecords[0];
                        $orderGroup['status'] = 1;
                        $orderGroup['content'][] = [
                            'status' => 1,
                            'content' => "抢救记录【{$record['blmc']}】"
                        ];
                        $orderGroup['content'][] = [
                            'status' => 1,
                            'content' => "标题时间【{$record['zxsj']}】"
                        ];
                        $orderGroup['content'][] = [
                            'status' => 1,
                            'content' => "抢救结束时间【{$record['rescueEndTime']}】"
                        ];
                        $orderGroup['content'][] = [
                            'status' => 1,
                            'content' => "首次签名时间【{$record['signatureTime']}】"
                        ];
                    } else {
                        // 如果所有记录都不符合条件，输出所有不符合的记录（包括超时和没签名的）
                        $orderGroup['status'] = 0;
                        if (!empty($invalidRecords)) {
                            foreach ($invalidRecords as $record) {
                                $orderGroup['content'][] = [
                                    'status' => 1,
                                    'content' => "抢救记录【{$record['blmc']}】"
                                ];
                                $orderGroup['content'][] = [
                                    'status' => 1,
                                    'content' => "标题时间【{$record['zxsj']}】"
                                ];
                                $orderGroup['content'][] = [
                                    'status' => 1,
                                    'content' => "抢救结束时间【{$record['rescueEndTime']}】"
                                ];

                                if (empty($record['signatureTimeObj'])) {
                                    // 没有签名时间
                                    $orderGroup['content'][] = [
                                        'status' => 0,
                                        'content' => "首次签名时间【无】"
                                    ];
                                } else {
                                    // 签名时间超时
                                    $orderGroup['content'][] = [
                                        'status' => 0,
                                        'content' => "首次签名时间【{$record['signatureTime']}（超时）】"
                                    ];
                                }
                            }
                        }
                        // 所有记录都无法提取抢救结束时间
                        if (!empty($noRescueEndTimeRecords)) {
                            foreach ($noRescueEndTimeRecords as $record) {
                                $orderGroup['content'][] = [
                                    'status' => 0,
                                    'content' => "抢救记录【{$record['BLMC']}】"
                                ];
                            }
                            $orderGroup['content'][] = [
                                'status' => 0,
                                'content' => "抢救记录【存在但无法提取抢救结束时间】"
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("calculateQjjljsjl-$ZYH-查询抢救记录error: " . $e->getMessage());
                    $orderGroup['content'][] = [
                        'status' => 0,
                        'content' => "查询抢救记录出错"
                    ];
                }

                if ($hasValidRecord) {
                    $insert['qjjljsjl_fz'] += 1;
                }

                $errorContent[] = $orderGroup;
            }

            if (!empty($errorContent)) {
                $insert['qjjljsjl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }

            //如果zyh不为空，根据zyh更新或插入es
            if (!empty($zyh)) {
                $esService = new ElasticsearchService('indicator');
                $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
            }
        }

        IndexCatalog::query()->where("index_name", "=", "qjjljsjl")->update(["status" => 2, 'quality_time' => time()]);
    }

    /**
     * 从抢救记录中提取抢救结束时间（参考rule1021的逻辑）
     *
     * @param string $blbh 病历编号
     * @param string $zxsj 执行时间
     * @return string 抢救结束时间，如果提取失败返回空字符串
     */
    private function extractRescueEndTime($blbh, $zxsj)
    {
        if (empty($blbh) || empty($zxsj)) {
            return '';
        }

        try {
            $hjnr = EMR_BL_BLXG::query()->where('BLBH', $blbh)->value('HJNR');
            if (empty($hjnr)) {
                return '';
            }

            $match_datetime_str = '';
            $year = date('Y', strtotime($zxsj));

            // 关键词列表
            $keywords = [
                '抢救成功',
                '病情',
                '血压',
                '测血压',
                '使用',
                '氧',
                '仍无自主呼吸心跳',
                '心电图示直线',
                '临床死亡',
                '宣布',
                '转入',
                '死亡',
                '出现意识不清',
                '心电图示无心电',
                '呼吸及血压'
            ];

            // 匹配抢救结束时间字段格式（在移除空格之前先匹配，确保格式正确）
            if (empty($match_datetime_str)) {
                // 尝试多个正则表达式模式（从严格到宽松）
                $patterns = [
                    // 模式1：标准格式，日期时间之间有空格
                    '/抢救结束时间[：:]\s*(\d{4}-\d{1,2}-\d{1,2}\s+\d{1,2}:\d{1,2}(?::\d{1,2})?)/u',
                    // 模式2：日期时间之间可以是任意空白字符（包括全角空格）
                    '/抢救结束时间[：:]\s*(\d{4}-\d{1,2}-\d{1,2}[\s\x{3000}]+\d{1,2}:\d{1,2}(?::\d{1,2})?)/u',
                    // 模式3：日期时间之间可以是0个或多个空白字符
                    '/抢救结束时间[：:]\s*(\d{4}-\d{1,2}-\d{1,2}\s*\d{1,2}:\d{1,2}(?::\d{1,2})?)/u',
                    // 模式4：最宽松，允许日期时间之间有任意字符
                    '/抢救结束时间[：:]\s*(\d{4}-\d{1,2}-\d{1,2}.*?\d{1,2}:\d{1,2}(?::\d{1,2})?)/u',
                ];

                foreach ($patterns as $idx => $pattern) {
                    if (preg_match($pattern, $hjnr, $matches)) {
                        $match_datetime_str = $matches[1];
                        // 如果是模式4（最宽松），需要清理日期时间字符串
                        if ($idx == 3) {
                            // 移除日期和时间之间的非数字字符，只保留一个空格
                            $match_datetime_str = preg_replace('/(\d{4}-\d{1,2}-\d{1,2})\s*(.*?)\s*(\d{1,2}:\d{1,2}(?::\d{1,2})?)/', '$1 $3', $match_datetime_str);
                        }
                        break;
                    }
                }
            }

            //去除空格，全角和半角（在提取抢救结束时间之后）
            $hjnr = str_replace([' ', '　'], '', $hjnr);

            if (empty($match_datetime_str)) {
                // 匹配格式：于YYYY-MM-DD HH:MM[:SS] + 关键词
                foreach ($keywords as $keyword) {
                    if (preg_match('/于\s*(\d{4}-\d{1,2}-\d{1,2}\s+\d{1,2}:\d{1,2}(?::\d{1,2})?)\s*' . preg_quote($keyword, '/') . '/', $hjnr, $matches)) {
                        $match_datetime_str = $matches[1];
                        break;
                    }
                }
            }

            // 匹配格式：于MM-DD HH时MM分 + 关键词
            if (empty($match_datetime_str)) {
                foreach ($keywords as $keyword) {
                    if (preg_match('/于\s*(\d{2}-\d{2})\s+(\d{1,2})时(\d{1,2})分\s*' . preg_quote($keyword, '/') . '/', $hjnr, $matches)) {
                        $monthDay = $matches[1];
                        $hour = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                        $minute = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
                        $match_datetime_str = "{$year}-{$monthDay} {$hour}:{$minute}";
                        break;
                    }
                }
            }

            // 匹配格式：xx时xx分 + 关键词（年月日根据zxsj判断，凌晨时间跨天处理）
            if (empty($match_datetime_str)) {
                foreach ($keywords as $keyword) {
                    if (preg_match('/于\s*(\d{1,2})时(\d{1,2})分\s*' . preg_quote($keyword, '/') . '/', $hjnr, $matches)) {
                        $rescueHour = (int)$matches[1];
                        $rescueMinute = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                        $rescueHourStr = str_pad($rescueHour, 2, '0', STR_PAD_LEFT);

                        // 获取zxsj的年月日和时分
                        $zxsjDate = date('Y-m-d', strtotime($zxsj));
                        $zxsjHour = (int)date('H', strtotime($zxsj));

                        // 判断是否需要跨天：如果抢救结束时间在凌晨0-8点，且zxsj在18:00之后，则+1天
                        if ($rescueHour >= 0 && $rescueHour <= 8 && $zxsjHour >= 18) {
                            // 抢救结束时间是凌晨（0-8点），且zxsj在18:00之后，需要+1天
                            $rescueDate = date('Y-m-d', strtotime($zxsjDate . ' +1 day'));
                        } else {
                            // 使用zxsj的日期
                            $rescueDate = $zxsjDate;
                        }

                        $match_datetime_str = "{$rescueDate} {$rescueHourStr}:{$rescueMinute}";
                        break;
                    }
                }
            }

            // 匹配格式：xx时xx分 + 关键词（年月日根据zxsj判断，凌晨时间跨天处理）(前面没有于字符)
            if (empty($match_datetime_str)) {
                foreach ($keywords as $keyword) {
                    if (preg_match('/(\d{1,2})时(\d{1,2})分\s*' . preg_quote($keyword, '/') . '/', $hjnr, $matches)) {
                        $rescueHour = (int)$matches[1];
                        $rescueMinute = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                        $rescueHourStr = str_pad($rescueHour, 2, '0', STR_PAD_LEFT);

                        // 获取zxsj的年月日和时分
                        $zxsjDate = date('Y-m-d', strtotime($zxsj));
                        $zxsjHour = (int)date('H', strtotime($zxsj));

                        // 判断是否需要跨天：如果抢救结束时间在凌晨0-8点，且zxsj在18:00之后，则+1天
                        if ($rescueHour >= 0 && $rescueHour <= 8 && $zxsjHour >= 18) {
                            // 抢救结束时间是凌晨（0-8点），且zxsj在18:00之后，需要+1天
                            $rescueDate = date('Y-m-d', strtotime($zxsjDate . ' +1 day'));
                        } else {
                            // 使用zxsj的日期
                            $rescueDate = $zxsjDate;
                        }

                        $match_datetime_str = "{$rescueDate} {$rescueHourStr}:{$rescueMinute}";
                        break;
                    }
                }
            }

            return $match_datetime_str;
        } catch (\Exception $e) {
            Log::error("extractRescueEndTime-error: " . $e->getMessage());
            return '';
        }
    }

    /**
     * 计算术前讨论及时完成率（sqtlwcl）
     * 参照 calculateSzsqcf 的时间窗：以手术开始时间为上限，起点与前台相邻手术结束时间择其大者
     * MBLB 来自 rulemap8016
     * 分母：符合手术过滤的手术例次
     * 分子：时间窗内存在术前讨论记录，且首次签名时间在窗口内
     */
    public function calculateSqtlwcl($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("sqtlwcl", 1);
        $indicatorService = new IndicatorService();
        $patientData = $indicatorService->getPatientInfoData($zyh, $start_time, $end_time);

        // 读取配置
        $ruleMap = $this->getRuleMap();
        $mblbRaw = RuleWordMap::query()->where('id', '=', 8016)->value('keyword');
        $mblbList = empty($mblbRaw) ? [] : (strpos($mblbRaw, ',') !== false ? explode(',', $mblbRaw) : [$mblbRaw]);

        foreach ($patientData as $patient) {
            //删除
            Indicator::updateOrInsert(['zyh' => $patient['MED_REC_ID']], ['sqtlwcl_fz' => 0, 'sqtlwcl_fm' => 0, 'sqtlwcl_error' => null]);
            $ZYH = $patient['MED_REC_ID'];

            $insert = [
                'zyh' => $ZYH,
                'sqtlwcl_fz' => 0,
                'sqtlwcl_fm' => 0,
                'sqtlwcl_error' => null,
                'AAC11N' => $patient['AAC11N'],
                'AEE03' => !empty($patient['AEE03']) ? $patient['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($patient['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($patient['AAC01'])),
                'AAC01' => $patient['AAC01']
            ];

            $errorContent = [];

            // 获取该患者的所有手术（按开始时间排序，字段与szsqcf一致）
            $ssapList = SM_SSAP::query()
                ->where('ZYH', $ZYH)
                ->orderBy('SSRQ', 'asc')
                ->get()
                ->toArray();

            if (empty($ssapList)) {
                continue;
            }

            // 与szsqcf保持一致，使用基于索引的循环以获取“上一台”手术结束时间
            for ($i = 0; $i < count($ssapList); $i++) {
                $op = $ssapList[$i];

                // 与szsqcf一致的字段
                $surgeryStart = $op['SSRQ'] ?? '';
                $surgeryEnd = $op['JSRQ'] ?? '';
                $surgeryName = $op['ICD9_SSCZMC'] ?? '';
                $surgeonName = $op['SZ'] ?? '';
                $surgeonCode = $op['SZDM'] ?? '';
                if (
                    empty($surgeryStart) || empty($surgeryName) || empty($surgeryEnd) ||
                    strpos($surgeryEnd, '1970-01-01') !== false || $surgeryName === 'NULL'
                ) {
                    continue;
                }

                // 手术级别过滤（复用szsqcf：ruleMap8047/8048）
                $ruleMap8047 = RuleWordMap::query()->where('id', '=', 8047)->value('keyword');
                $excludeKeywords8047 = strpos($ruleMap8047, ',') !== false ? explode(',', $ruleMap8047) : [$ruleMap8047];
                $ruleMap8048 = RuleWordMap::query()->where('id', '=', 8048)->value('keyword');
                $excludeKeywords8048 = strpos($ruleMap8048, ',') !== false ? explode(',', $ruleMap8048) : [$ruleMap8048];
                if (!empty($ruleMap8047)) {
                    $ssLB = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
                    if (!empty($ssLB)) {
                        if (!in_array($ssLB, $excludeKeywords8047)) {
                            if (!empty($ruleMap8048)) {
                                if (!in_array($surgeryName, $excludeKeywords8048)) {
                                    continue;
                                }
                            } else {
                                continue;
                            }
                        }
                    }
                }

                // 术者信息（附加工号）
                $surgeonBH = Staff::query()->where('code', $surgeonCode)->value('YGBH');
                $surgeon = $surgeonName . "(" . $surgeonBH . ")";

                // 统计分母
                $insert['sqtlwcl_fm'] += 1;

                // 计算时间窗（完全参考szsqcf）：手术开始前24小时，若上一台结束时间更晚则取上一台结束时间
                $startCheckTime = date('Y-m-d 00:00:00', strtotime($surgeryStart) - 24 * 3600);
                /* if ($i > 0) {
                    $prevSurgeryEndTime = $ssapList[$i - 1]['JSRQ'] ?? '';
                    if (
                        !empty($prevSurgeryEndTime) &&
                        $prevSurgeryEndTime != '1970-01-01 00:00:00' &&
                        $prevSurgeryEndTime != '0000-00-00 00:00:00' &&
                        strtotime($prevSurgeryEndTime) > strtotime($startCheckTime)
                    ) {
                        $startCheckTime = $prevSurgeryEndTime;
                    }
                } */
                // 统一精度到分钟，与szsqcf一致
                $startCheckTime = date('Y-m-d H:i:s', strtotime($startCheckTime));
                $endCheckTime = date('Y-m-d H:i:s', strtotime($surgeryStart));

                // 查询所有术前讨论记录（不限定ZXSJ时间范围）
                $recordsQuery = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->orderBy('ZXSJ', 'asc');
                if (!empty($mblbList)) {
                    $recordsQuery->whereIn('MBLB', $mblbList);
                }
                $records = $recordsQuery->get()->toArray();

                $group = [
                    'status' => 0,
                    'content' => []
                ];

                // 输出手术信息（按需求前缀“手麻”）
                $group['content'][] = ['status' => 1, 'content' => "（手麻）手术名称【{$surgeryName}】"];
                $group['content'][] = ['status' => 1, 'content' => "（手麻）术者【{$surgeon}】"];
                $group['content'][] = ['status' => 1, 'content' => "（手麻）手术开始时间【{$surgeryStart}】"];

                if (empty($records)) {
                    $group['content'][] = ['status' => 0, 'content' => "术前讨论【无】"];
                    $errorContent[] = $group;
                    continue;
                }

                // 遍历所有术前讨论记录，从痕迹内容中提取第一个时间
                $found = false;
                foreach ($records as $rec) {
                    $blmc = $rec['BLMC'] ?? '';
                    $blbh = $rec['BLBH'] ?? '';

                    // 取痕迹内容 HJNR
                    $hjnr = '';
                    try {
                        $hjnr = EMR_BL_BLXG::query()->where('BLBH', $blbh)->value('HJNR') ?? '';
                    } catch (\Exception $e) {
                        $hjnr = '';
                    }

                    // 从"术前小结及术前讨论结论记录"之后的内容中提取第一个时间（YYYY-MM-DD HH:MM[:SS]?）
                    $sub = $hjnr;
                    $pos1 = mb_strpos($hjnr, '术前小结及术前讨论结论记录');
                    $startPos = false;
                    if ($pos1 !== false) {
                        $startPos = $pos1;
                    }
                    if ($startPos !== false) {
                        $sub = mb_substr($hjnr, $startPos);
                    }

                    $firstTime = '';
                    if (preg_match('/(\d{4}-\d{1,2}-\d{1,2}\s+\d{1,2}:\d{1,2}(?::\d{1,2})?)/', $sub, $m)) {
                        $firstTime = $m[1];
                    }

                    if (empty($firstTime)) {
                        // 未提取到时间，跳过该条
                        continue;
                    }

                    // 判断时间是否在窗口内：[startCheckTime, endCheckTime]
                    if (strtotime($firstTime) >= strtotime($startCheckTime) && strtotime($firstTime) <= strtotime($endCheckTime)) {
                        // 合格
                        $group['status'] = 1;
                        $insert['sqtlwcl_fz'] += 1;
                        $group['content'][] = ['status' => 1, 'content' => "术前讨论【{$blmc}】"];
                        $group['content'][] = ['status' => 1, 'content' => "讨论时间【{$firstTime}】"];
                        $found = true;
                        break; // 找到符合条件的即可
                    }
                }

                if (!$found) {
                    // 未找到符合条件的记录
                    $group['content'][] = ['status' => 0, 'content' => "术前讨论【无】"];
                }

                $errorContent[] = $group;

                // for循环基于索引，上一台结束时间在下次循环使用
            }

            if (!empty($errorContent)) {
                $insert['sqtlwcl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        IndexCatalog::query()->where("index_name", "=", "sqtlwcl")->update(["status" => 2, 'quality_time' => time()]);
    }

    /**
     * 计算术者术前讨论参与率（sqtlrygfcyl）
     * - 分母：手术例次（同 szsqcf 的过滤、排序与紧急排除、级别过滤）
     * - 分子：手术开始前24小时内，存在“术前小结/术前讨论”记录，且：
     *   1) 从痕迹内容中（以“术前小结/术前讨论”之后的内容）提取到的第一个时间（YYYY-MM-DD HH:MM[:SS]?）
     *      位于 [手术开始-24h, 手术开始) 窗口内；
     *   2) 痕迹内容或CA签名人员包含术者姓名
     */
    public function calculateSqtlrygfcyl($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("sqtlrygfcyl", 1);
        $indicatorService = new IndicatorService();
        $patientData = $indicatorService->getPatientInfoData($zyh, $start_time, $end_time);

        // 术前小结/术前讨论 MBLB（使用 rulemap8016）
        $mblbRaw = RuleWordMap::query()->where('id', '=', 8016)->value('keyword');
        $mblbList = empty($mblbRaw) ? [] : (strpos($mblbRaw, ',') !== false ? explode(',', $mblbRaw) : [$mblbRaw]);

        foreach ($patientData as $patient) {
            // 删除旧结果
            Indicator::updateOrInsert(['zyh' => $patient['MED_REC_ID']], ['sqtlrygfcyl_fz' => 0, 'sqtlrygfcyl_fm' => 0, 'sqtlrygfcyl_error' => null]);
            $ZYH = $patient['MED_REC_ID'];

            $insert = [
                'zyh' => $ZYH,
                'sqtlrygfcyl_fz' => 0,
                'sqtlrygfcyl_fm' => 0,
                'sqtlrygfcyl_error' => null,
                'AAC11N' => $patient['AAC11N'],
                'AEE03' => !empty($patient['AEE03']) ? $patient['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($patient['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($patient['AAC01'])),
                'AAC01' => $patient['AAC01']
            ];

            $errorContent = [];

            // 手术列表（对齐 szsqcf）：按 SSRQ 排序
            $ssapList = SM_SSAP::query()
                ->where('ZYH', $ZYH)
                ->orderBy('SSRQ', 'asc')
                ->get()->toArray();
            if (empty($ssapList)) {
                continue;
            }

            for ($i = 0; $i < count($ssapList); $i++) {
                $op = $ssapList[$i];


                $surgeryStart = $op['SSRQ'] ?? '';
                $surgeryEnd = $op['JSRQ'] ?? '';
                $surgeryName = $op['ICD9_SSCZMC'] ?? '';
                $surgeonName = $op['SZ'] ?? '';
                $surgeonCode = $op['SZDM'] ?? '';
                if (
                    empty($surgeryStart) || empty($surgeryName) || empty($surgeryEnd) ||
                    strpos($surgeryEnd, '1970-01-01') !== false || $surgeryName === 'NULL'
                ) {
                    continue;
                }

                // 级别过滤（对齐 szsqcf）
                $ruleMap8047 = RuleWordMap::query()->where('id', '=', 8047)->value('keyword');
                $excludeKeywords8047 = strpos($ruleMap8047, ',') !== false ? explode(',', $ruleMap8047) : [$ruleMap8047];
                $ruleMap8048 = RuleWordMap::query()->where('id', '=', 8048)->value('keyword');
                $excludeKeywords8048 = strpos($ruleMap8048, ',') !== false ? explode(',', $ruleMap8048) : [$ruleMap8048];
                if (!empty($ruleMap8047)) {
                    $ssLB = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
                    if (!empty($ssLB)) {
                        if (!in_array($ssLB, $excludeKeywords8047)) {
                            if (!empty($ruleMap8048)) {
                                if (!in_array($surgeryName, $excludeKeywords8048)) {
                                    continue;
                                }
                            } else {
                                continue;
                            }
                        }
                    }
                }

                // 补充术者工号并展示
                $surgeonBH = Staff::query()->where('code', $surgeonCode)->value('YGBH');
                $surgeonDisp = $surgeonName . "(" . $surgeonBH . ")";

                // 分母+1
                $insert['sqtlrygfcyl_fm'] += 1;

                // 手术开始前24小时
                //如果是第一台手术，开始时间从1970-01-01 00:00:00开始，否则从上一台手术的结束时间开始
                $startCheckTime = '';
                if ($i === 0) {
                    $startCheckTime = date('Y-m-d H:i:s', strtotime('1970-01-01 00:00:00'));
                } else {
                    $prevSurgeryEndTime = $ssapList[$i - 1]['JSRQ'] ?? '';
                    if (!empty($prevSurgeryEndTime)) {
                        $startCheckTime = date('Y-m-d H:i:s', strtotime($prevSurgeryEndTime));
                    }
                }


                $endCheckTime = date('Y-m-d H:i:s', strtotime($surgeryStart));

                $group = [
                    'status' => 0,
                    'content' => []
                ];
                $group['content'][] = ['status' => 1, 'content' => "（手麻）手术名称【{$surgeryName}】"];
                $group['content'][] = ['status' => 1, 'content' => "（手麻）术者【{$surgeonDisp}】"];
                $group['content'][] = ['status' => 1, 'content' => "（手麻）手术开始时间【{$surgeryStart}】"];

                // 查询窗口内的术前小结/术前讨论
                $recordsQuery = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->orderBy('ZXSJ', 'asc');
                if (!empty($mblbList)) {
                    $recordsQuery->whereIn('MBLB', $mblbList);
                }
                $records = $recordsQuery->get()->toArray();

                if (empty($records)) {
                    $group['content'][] = ['status' => 0, 'content' => "术前小结及术前讨论结论记录【无】"];
                    $errorContent[] = $group;
                    continue;
                }

                // 遍历记录，找满足条件者；若时间命中但无术者，也要输出
                $hit = false;
                $firstTimeMatchedWithoutSurgeon = [];
                foreach ($records as $rec) {
                    $blmc = $rec['BLMC'] ?? '';
                    $blbh = $rec['BLBH'] ?? '';


                    // 取痕迹内容 HJNR
                    $hjnr = '';
                    try {
                        $hjnr = EMR_BL_BLXG::query()->where('BLBH', $blbh)->value('HJNR') ?? '';
                    } catch (\Exception $e) {
                        $hjnr = '';
                    }

                    // 从“术前小结及术前讨论结论记录”之后的内容中提取第一个时间（YYYY-MM-DD HH:MM[:SS]?）
                    $sub = $hjnr;
                    $pos1 = mb_strpos($hjnr, '术前小结及术前讨论结论记录');
                    $startPos = false;
                    if ($pos1 !== false) {
                        $startPos = $pos1;
                    }
                    if ($startPos !== false) {
                        $sub = mb_substr($hjnr, $startPos);
                    }

                    $firstTime = '';
                    if (preg_match('/(\d{4}-\d{1,2}-\d{1,2}\s+\d{1,2}:\d{1,2}(?::\d{1,2})?)/', $sub, $m)) {
                        $firstTime = $m[1];
                    }

                    if (empty($firstTime)) {
                        // 未提取到时间，跳过该条
                        continue;
                    }

                    // 是否在窗口内：[startCheckTime, endCheckTime]，不在就跳过
                    if (!(strtotime($firstTime) >= strtotime($startCheckTime) && strtotime($firstTime) <= strtotime($endCheckTime))) {
                        continue;
                    }

                    // 再看痕迹内容或签名是否包含术者姓名
                    $nameMatched = false;
                    if (!empty($surgeonName) && $hjnr !== '' && mb_strpos($hjnr, $surgeonName) !== false) {
                        $nameMatched = true;
                    }
                    if (!$nameMatched && !empty($blbh)) {
                        $codes = EMR_BL_BLSY::query()->where('BLBH', $blbh)->where('FG_ACTIVE', 1)->groupBy('SYYS')->pluck('SYYS')->toArray();
                        if (!empty($codes)) {
                            $names = Staff::query()->whereIn('code', $codes)->pluck('name')->toArray();
                            if (empty($names)) {
                                continue;
                            }
                            foreach ($names as $nm) {
                                if (empty($nm) || $nm === '' || empty($surgeonName)) {
                                    continue;
                                }
                                if ($nm !== '' && mb_strpos($surgeonName, $nm) !== false || mb_strpos($nm, $surgeonName) !== false) {
                                    $nameMatched = true;
                                    break;
                                }
                            }
                        }
                    }

                    if ($nameMatched) {
                        $group['status'] = 1;
                        $insert['sqtlrygfcyl_fz'] += 1;
                        $group['content'][] = ['status' => 1, 'content' => "术前小结及术前讨论结论记录【{$blmc}】"];
                        $group['content'][] = ['status' => 1, 'content' => "讨论时间【{$firstTime}】"];
                        break; // 命中一条即可
                    } else {
                        $firstTimeMatchedWithoutSurgeon[] = $rec;
                    }
                }

                if ($group['status'] === 0) {
                    if (!empty($firstTimeMatchedWithoutSurgeon)) {
                        foreach ($firstTimeMatchedWithoutSurgeon as $rec) {
                            $group['content'][] = ['status' => 0, 'content' => "术前小结及术前讨论结论记录【{$rec['BLMC']}】"];
                            $group['content'][] = ['status' => 0, 'content' => "术者未参与讨论"];
                        }
                    } else {
                        $group['content'][] = ['status' => 0, 'content' => "术前小结及术前讨论结论记录【无】"];
                    }
                }

                $errorContent[] = $group;
            }

            if (!empty($errorContent)) {
                $insert['sqtlrygfcyl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        IndexCatalog::query()->where("index_name", "=", "sqtlrygfcyl")->update(["status" => 2, 'quality_time' => time()]);
    }
    /**
     * 计算抢救成功率（qjcg）
     * 分母：同期“大抢救”医嘱次数
     * 分子：对应时间窗内（开嘱前1小时-后6小时）抢救记录 HJNR 含“抢救成功”或“病情好转”任一
     */
    public function calculateQjcg($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("qjcg", 1);
        $indicatorService = new IndicatorService();
        $patientData = $indicatorService->getPatientInfoData($zyh, $start_time, $end_time);

        // 抢救记录MBLB配置（8027）
        $ruleMap8027 = RuleWordMap::query()->where('id', '=', 8027)->value('keyword');
        $ruleMap8027 = !empty($ruleMap8027) ? $ruleMap8027 : '27';
        $rescueRecordTypes = strpos($ruleMap8027, ',') !== false ? explode(',', $ruleMap8027) : [$ruleMap8027];

        $bigRescueKeyword = '大抢救';


        foreach ($patientData as $patient) {
            //删除
            Indicator::updateOrInsert(['zyh' => $patient['MED_REC_ID']], ['qjcg_fz' => 0, 'qjcg_fm' => 0, 'qjcg_error' => null]);
            $ZYH = $patient['MED_REC_ID'];

            $insert = [
                'zyh' => $ZYH,
                'qjcg_fz' => 0,
                'qjcg_fm' => 0,
                'qjcg_error' => null,
                'AAC11N' => $patient['AAC11N'],
                'AEE03' => !empty($patient['AEE03']) ? $patient['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($patient['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($patient['AAC01'])),
                'AAC01' => $patient['AAC01']
            ];

            $errorContent = [];

            // 查询“大抢救”医嘱
            $orders = Yzb::query()
                ->where('ZYH', $ZYH)
                ->where('YZMC', 'like', '%' . $bigRescueKeyword . '%')
                ->orderBy('KZSJ', 'asc')
                ->get()
                ->toArray();

            if (empty($orders)) {
                continue;
            }

            foreach ($orders as $order) {
                $insert['qjcg_fm'] += 1; // 分母+1
                $orderName = $order['YZMC'] ?? '';
                $orderTime = $order['KZSJ'] ?? '';

                $group = [
                    'status' => 0,
                    'content' => []
                ];
                $group['content'][] = ['status' => 1, 'content' => "医嘱名称【{$orderName}】"];
                $group['content'][] = ['status' => 1, 'content' => "开嘱时间【{$orderTime}】"];

                if (empty($orderTime)) {
                    $group['content'][] = ['status' => 0, 'content' => "开嘱时间【无】"];
                    $errorContent[] = $group;
                    continue;
                }

                // 时间窗：开嘱前1小时 ~ 开嘱后6小时
                $recordStartTime = date('Y-m-d H:i:s', strtotime($orderTime) - 1 * 3600);
                $recordEndTime = date('Y-m-d H:i:s', strtotime($orderTime) + 6 * 3600);

                // 查询时间窗内的抢救记录
                $rescueRecords = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    ->whereIn('MBLB', $rescueRecordTypes)
                    ->where('ZXSJ', '>=', $recordStartTime)
                    ->where('ZXSJ', '<=', $recordEndTime)
                    ->orderBy('ZXSJ', 'asc')
                    ->get()
                    ->toArray();

                if (empty($rescueRecords)) {
                    $group['content'][] = ['status' => 0, 'content' => "抢救记录【无】"];
                    $errorContent[] = $group;
                    continue;
                }

                $successMatched = false;
                foreach ($rescueRecords as $record) {
                    $blmc = $record['BLMC'] ?? '';
                    $blbh = $record['BLBH'] ?? '';
                    $zxsj = $record['ZXSJ'] ?? '';

                    // 读取 HJNR，判断是否包含关键词
                    $hjnr = '';
                    try {
                        $hjnr = EMR_BL_BLXG::query()->where('BLBH', $blbh)->value('HJNR') ?? '';
                    } catch (\Exception $e) {
                        Log::error("calculateQjcg-$ZYH-读取HJNR error: " . $e->getMessage());
                    }

                    $hitWord = '';
                    if ($hjnr !== '') {
                        if (mb_strpos($hjnr, '抢救成功') !== false) {
                            $hitWord = '抢救成功';
                        } elseif (mb_strpos($hjnr, '病情好转') !== false) {
                            $hitWord = '病情好转';
                        }
                    }

                    if ($hitWord !== '') {
                        $successMatched = true;
                        $group['status'] = 1;
                        $group['content'][] = ['status' => 1, 'content' => "抢救记录【{$blmc}】"];
                        $group['content'][] = ['status' => 1, 'content' => "标题时间【{$zxsj}】"];
                        $group['content'][] = ['status' => 1, 'content' => "结果【{$hitWord}】"];
                        break; // 任意一条命中即可
                    }
                }

                if ($successMatched) {
                    $insert['qjcg_fz'] += 1;
                } else {
                    $group['content'][] = ['status' => 0, 'content' => "抢救记录【存在但未见‘抢救成功/病情好转’】"];
                }

                $errorContent[] = $group;
            }

            if (!empty($errorContent)) {
                $insert['qjcg_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        IndexCatalog::query()->where("index_name", "=", "qjcg")->update(["status" => 2, 'quality_time' => time()]);
    }
    /**
     * 计算抢救记录审核率（qjjlsh）
     * 分母：有抢救记录（MBLB=8027配置）
     * 分子：抢救记录正文中“抢救者：”后的第一个姓名，出现在该记录的签名医师列表中
     */
    public function calculateQjjlsh($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("qjjlsh", 1);
        $indicatorService = new IndicatorService();
        $patientData = $indicatorService->getPatientInfoData($zyh, $start_time, $end_time);

        // 抢救记录MBLB配置（8027）
        $ruleMap8027 = RuleWordMap::query()->where('id', '=', 8027)->value('keyword');
        $ruleMap8027 = !empty($ruleMap8027) ? $ruleMap8027 : '27';
        $rescueRecordTypes = strpos($ruleMap8027, ',') !== false ? explode(',', $ruleMap8027) : [$ruleMap8027];

        foreach ($patientData as $patient) {
            //删除
            Indicator::updateOrInsert(['zyh' => $patient['MED_REC_ID']], ['qjjlsh_fz' => 0, 'qjjlsh_fm' => 0, 'qjjlsh_error' => null]);
            $ZYH = $patient['MED_REC_ID'];

            $insert = [
                'zyh' => $ZYH,
                'qjjlsh_fz' => 0,
                'qjjlsh_fm' => 0,
                'qjjlsh_error' => null,
                'AAC11N' => $patient['AAC11N'],
                'AEE03' => !empty($patient['AEE03']) ? $patient['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($patient['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($patient['AAC01'])),
                'AAC01' => $patient['AAC01']
            ];

            // 查询抢救记录
            $records = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->whereIn('MBLB', $rescueRecordTypes)
                ->orderBy('ZXSJ', 'asc')
                ->get()
                ->toArray();

            if (empty($records)) {
                continue;
            }

            $errorContent = [];

            foreach ($records as $record) {
                $insert['qjjlsh_fm'] += 1; // 分母+1：每条抢救记录
                $blmc = $record['BLMC'] ?? '';
                $blbh = $record['BLBH'] ?? '';

                $group = [
                    'status' => 0,
                    'content' => []
                ];
                $group['content'][] = ['status' => 1, 'content' => "抢救记录【{$blmc}】"];

                // 提取“抢救者：”后的第一个姓名（支持多分隔符）
                $firstRescuer = '';
                try {
                    $hjnr = EMR_BL_BLXG::query()->where('BLBH', $blbh)->value('HJNR');
                    if (!empty($hjnr)) {
                        // 参加抢救人员：陈国栋副主任医师、杨强副主任医师、护士楚海亭、李晓梦。
                        // 允许：抢救者：张三、李四；抢救者:张三,李四；抢救者 张三 等
                        if (preg_match('/抢救者\s*[：: ]\s*([^\n\r]+)/u', $hjnr, $m)) {
                            $list = $m[1];
                            // 统一分隔符为中文逗号
                            $list = str_replace(['、', ';', '；'], '，', $list);
                            // 截断到句号或换行
                            $list = preg_split('/[。\n\r]/u', $list)[0];
                            $names = array_filter(array_map('trim', preg_split('/[，,\s]+/u', $list)));
                            if (!empty($names)) {
                                $firstRescuer = $names[0];
                            }
                        }

                        //参加抢救人员匹配方式
                        if (empty($firstRescuer)) {
                            if (preg_match('/参加抢救人员[：:]\s*([^\n\r]+)/u', $hjnr, $m)) {
                                $list = $m[1];
                                $list = str_replace(['、', ';', '；'], '，', $list);
                                $list = preg_split('/[。\n\r]/u', $list)[0];
                                $names = array_filter(array_map('trim', preg_split('/[，,\s]+/u', $list)));
                            }
                            if (!empty($names)) {
                                $firstRescuer = $names[0];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("calculateQjjlsh-$ZYH-提取抢救者error: " . $e->getMessage());
                }

                $group['content'][] = ['status' => 1, 'content' => "第一个抢救者【" . ($firstRescuer ?: '无') . "】"];

                // 获取签名医师列表（CA签名）：按 SYYS 关联 staff.code 获取 staff.name
                $signatureNames = [];
                try {
                    $blsyRows = EMR_BL_BLSY::query()
                        ->where('BLBH', '=', $blbh)
                        ->where('FG_ACTIVE', 1)
                        ->groupBy('SYYS')
                        ->get(['SYYS'])
                        ->toArray();
                    $codes = [];
                    foreach ($blsyRows as $row) {
                        $code = trim($row['SYYS'] ?? '');
                        if ($code !== '') {
                            $codes[] = $code;
                        }
                    }
                    if (!empty($codes)) {
                        $signatureNames = Staff::query()->whereIn('code', $codes)->pluck('name')->toArray();
                    }
                } catch (\Exception $e) {
                    Log::error("calculateQjjlsh-$ZYH-查询签名error: " . $e->getMessage());
                }

                $signatureDisplay = !empty($signatureNames) ? implode('，', $signatureNames) : '无';

                // 判断是否命中分子
                $hit = false;
                if ($firstRescuer !== '' && !empty($signatureNames)) {
                    foreach ($signatureNames as $sn) {
                        if (strpos($sn, $firstRescuer) !== false || strpos($firstRescuer, $sn) !== false) {
                            $hit = true;
                            break;
                        }
                    }
                }

                if ($hit) {
                    $group['status'] = 1;
                    $insert['qjjlsh_fz'] += 1;
                    $group['content'][] = ['status' => 1, 'content' => "CA签名【{$signatureDisplay}】"];
                } else {
                    // 错误提示
                    $group['content'][] = ['status' => 0, 'content' => "CA签名【{$signatureDisplay}】（均不是主持抢救医师）"];
                }

                $errorContent[] = $group;
            }

            if (!empty($errorContent)) {
                $insert['qjjlsh_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        IndexCatalog::query()->where("index_name", "=", "qjjlsh")->update(["status" => 2, 'quality_time' => time()]);
    }

    /**
     * 计算手术医嘱规范开具率（ssyzgfkjl）
     * - 分母：查询SM_SSAP，有数据就算分母
     * - 分子：查询YZMC中包含"拟。。。术"的医嘱，然后分组查询：
     *   1) 第一个医嘱查询范围是kzsj之前，查询术前小结内容讨论时间在符合范围的（参考calculateSqtlrygfcyl）
     *   2) 如果有多个医嘱，第二个医嘱范围就是上个医嘱kzsj到现在这个医嘱的kzsj
     */
    public function calculateSsyzgfkjl($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("ssyzgfkjl", 1);
        $indicatorService = new IndicatorService();
        $patientData = $indicatorService->getPatientInfoData($zyh, $start_time, $end_time);

        // 术前小结/术前讨论 MBLB（使用 rulemap8016）
        $mblbRaw = RuleWordMap::query()->where('id', '=', 8016)->value('keyword');
        $mblbList = empty($mblbRaw) ? [] : (strpos($mblbRaw, ',') !== false ? explode(',', $mblbRaw) : [$mblbRaw]);

        foreach ($patientData as $patient) {
            // 删除旧结果
            Indicator::updateOrInsert(['zyh' => $patient['MED_REC_ID']], ['ssyzgfkjl_fz' => 0, 'ssyzgfkjl_fm' => 0, 'ssyzgfkjl_error' => null]);
            $ZYH = $patient['MED_REC_ID'];

            $insert = [
                'zyh' => $ZYH,
                'ssyzgfkjl_fz' => 0,
                'ssyzgfkjl_fm' => 0,
                'ssyzgfkjl_error' => null,
                'AAC11N' => $patient['AAC11N'],
                'AEE03' => !empty($patient['AEE03']) ? $patient['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($patient['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($patient['AAC01'])),
                'AAC01' => $patient['AAC01']
            ];

            $errorContent = [];

            // 分母：查询SM_SSAP，有数据就算分母（只算一个）
            $ssapList = SM_SSAP::query()
                ->where('ZYH', $ZYH)
                ->get()
                ->toArray();

            if (empty($ssapList)) {
                continue;
            }

            // 统计分母：有数据只算一个
            $insert['ssyzgfkjl_fm'] = 1;

            // 查询YZMC中包含"拟。。。术"的医嘱
            $orders = Yzb::query()
                ->where('ZYH', $ZYH)
                ->where('YZMC', 'like', '%拟%术%')
                ->orderBy('KZSJ', 'asc')
                ->get()
                ->toArray();

            if (empty($orders)) {
                // 没有符合条件的医嘱，但分母已统计，保存数据（分子=0）
                //错误输出所有ssap
                $group = [
                    'status' => 0,
                    'content' => []
                ];
                foreach ($ssapList as $ssap) {
                    $group['content'][] = ['status' => 0, 'content' => "(手麻)手术名称【{$ssap['ICD9_SSCZMC']}】"];
                    $group['content'][] = ['status' => 0, 'content' => "(手麻)手术开始时间【{$ssap['SSRQ']}】"];
                }
                $group['content'][] = ['status' => 0, 'content' => "手术医嘱【无】"];
                $errorContent[] = $group;
                $insert['ssyzgfkjl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
                continue;
            }

            // 查询所有术前小结/术前讨论记录（用于后续匹配）
            $recordsQuery = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->orderBy('ZXSJ', 'asc');
            if (!empty($mblbList)) {
                $recordsQuery->whereIn('MBLB', $mblbList);
            }
            $allRecords = $recordsQuery->get()->toArray();

            // 检查所有医嘱是否都符合条件
            $allMatched = true;
            $errorContent = [];

            // 遍历每个医嘱
            foreach ($orders as $idx => $order) {
                $orderName = $order['YZMC'] ?? '';
                $orderKzsj = $order['KZSJ'] ?? '';

                if (empty($orderKzsj)) {
                    $allMatched = false;
                    $group = [
                        'status' => 0,
                        'content' => []
                    ];
                    $group['content'][] = ['status' => 1, 'content' => "手术医嘱【{$orderName}】"];
                    $group['content'][] = ['status' => 1, 'content' => "开嘱时间【无】"];
                    $group['content'][] = ['status' => 0, 'content' => "术前讨论【无】"];
                    $group['content'][] = ['status' => 0, 'content' => "讨论时间【无】"];
                    $errorContent[] = $group;
                    continue;
                }

                $group = [
                    'status' => 0,
                    'content' => []
                ];
                $group['content'][] = ['status' => 1, 'content' => "手术医嘱【{$orderName}】"];
                $group['content'][] = ['status' => 1, 'content' => "开嘱时间【{$orderKzsj}】"];

                // 确定查询时间范围
                $startCheckTime = '';
                $endCheckTime = $orderKzsj;

                if ($idx === 0) {
                    // 第一个医嘱：查询范围是kzsj之前
                    $startCheckTime = '1970-01-01 00:00:00'; // 从最早开始
                } else {
                    // 第二个及以后的医嘱：范围是上个医嘱kzsj到现在这个医嘱的kzsj
                    $prevOrderKzsj = $orders[$idx - 1]['KZSJ'] ?? '';
                    if (!empty($prevOrderKzsj)) {
                        $startCheckTime = $prevOrderKzsj;
                    } else {
                        $startCheckTime = '1970-01-01 00:00:00';
                    }
                }

                // 在时间范围内查找术前讨论记录
                $matched = false;
                $matchedRecord = null;
                $matchedTime = '';

                foreach ($allRecords as $rec) {
                    $blmc = $rec['BLMC'] ?? '';
                    $blbh = $rec['BLBH'] ?? '';
                    $zxsj = $rec['ZXSJ'] ?? '';

                    // 取痕迹内容 HJNR
                    $hjnr = '';
                    try {
                        $hjnr = EMR_BL_BLXG::query()->where('BLBH', $blbh)->value('HJNR') ?? '';
                    } catch (\Exception $e) {
                        $hjnr = '';
                    }

                    // 从"术前小结及术前讨论结论记录"之后的内容中提取第一个时间（YYYY-MM-DD HH:MM[:SS]?）
                    $sub = $hjnr;
                    $pos1 = mb_strpos($hjnr, '术前小结及术前讨论结论记录');
                    $startPos = false;
                    if ($pos1 !== false) {
                        $startPos = $pos1;
                    }
                    if ($startPos !== false) {
                        $sub = mb_substr($hjnr, $startPos);
                    }

                    $firstTime = '';
                    if (preg_match('/(\d{4}-\d{1,2}-\d{1,2}\s+\d{1,2}:\d{1,2}(?::\d{1,2})?)/', $sub, $m)) {
                        $firstTime = $m[1];
                    }

                    if (empty($firstTime)) {
                        continue;
                    }

                    // 判断时间是否在窗口内：[startCheckTime, endCheckTime]
                    if (strtotime($firstTime) >= strtotime($startCheckTime) && strtotime($firstTime) <= strtotime($endCheckTime)) {
                        $matched = true;
                        $matchedRecord = $rec;
                        $matchedTime = $firstTime;
                        break; // 找到一条即可
                    }
                }

                if ($matched) {
                    $group['status'] = 1;
                    $group['content'][] = ['status' => 1, 'content' => "术前讨论【{$matchedRecord['BLMC']}】"];
                    $group['content'][] = ['status' => 1, 'content' => "讨论时间【{$matchedTime}】（开嘱前完成）"];
                } else {
                    $allMatched = false;
                    $group['content'][] = ['status' => 0, 'content' => "术前讨论【无】"];
                    $group['content'][] = ['status' => 0, 'content' => "讨论时间【无】"];
                }

                $errorContent[] = $group;
            }

            // 所有医嘱都符合才算一个分子
            if ($allMatched) {
                $insert['ssyzgfkjl_fz'] = 1;
            }

            if (!empty($errorContent)) {
                $insert['ssyzgfkjl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        IndexCatalog::query()->where("index_name", "=", "ssyzgfkjl")->update(["status" => 2, 'quality_time' => time()]);
    }

    /**
     * 计算死亡病例讨论及时完成率（swtljswcl）
     * - 分母：查询包含特定关键字的医嘱（ruleMap2009）
     * - 分子：死亡病例讨论记录的时间字段（ruleMap2001）在死亡时间到死亡时间+120小时（5天）内
     */
    public function calculateSwtljswcl($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("swtljswcl", 1);
        $indicatorService = new IndicatorService();
        $patientData = $indicatorService->getPatientInfoData($zyh, $start_time, $end_time);

        // 获取配置
        $ruleMap2001 = RuleWordMap::query()->where("id", "=", 2001)->first();
        $timeField = $ruleMap2001 ? $ruleMap2001->keyword : 'CJSJ'; // 默认使用CJSJ

        $ruleMap2009 = RuleWordMap::query()->where("id", "=", 2009)->first();
        $orderKeyword = $ruleMap2009 ? $ruleMap2009->keyword : '死亡'; // 默认关键字

        foreach ($patientData as $patient) {
            // 删除旧结果
            Indicator::updateOrInsert(['zyh' => $patient['MED_REC_ID']], ['swtljswcl_fz' => 0, 'swtljswcl_fm' => 0, 'swtljswcl_error' => null]);
            $ZYH = $patient['MED_REC_ID'];

            $insert = [
                'zyh' => $ZYH,
                'swtljswcl_fz' => 0,
                'swtljswcl_fm' => 0,
                'swtljswcl_error' => null,
                'AAC11N' => $patient['AAC11N'],
                'AEE03' => !empty($patient['AEE03']) ? $patient['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($patient['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($patient['AAC01'])),
                'AAC01' => $patient['AAC01']
            ];

            $errorContent = [];


            // 查询包含特定关键字的医嘱
            $orders = Yzb::query()
                ->where('ZYH', $ZYH)
                ->where('YZMC', 'like', '%' . $orderKeyword . '%')
                ->orderBy('KZSJ', 'asc')
                ->get()
                ->toArray();

            if (empty($orders)) {
                continue;
            }
            var_dump("住院号：" . $ZYH);
            // 获取死亡时间（从ZY_BRRY表的AAC01字段）
            $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->first();
            if (empty($brry)) {
                continue;
            }
            $swsj = $brry->AAC01 ?? '';
            if (empty($swsj)) {
                continue;
            }
            var_dump("死亡时间：" . $swsj);

            // 统计分母
            $insert['swtljswcl_fm'] = 1;

            // 计算时间范围：死亡时间到死亡时间+120小时（5天）
            $swsjTimestamp = strtotime($swsj);
            $endTime = date("Y-m-d H:i:s", $swsjTimestamp + 120 * 3600);

            // 查询死亡病例讨论记录
            $records = EMR_BL_BL01::query()
                ->where("JZHM", '=', $ZYH)
                ->where('BLLB', '=', '43')
                ->where('BLMC', 'like', '%死亡病例讨论%')
                //->where('ZXSJ', '>=', $swsj)
                //->where('ZXSJ', '<=', $endTime)
                ->get()
                ->toArray();

            // 遍历每个医嘱
            $order = $orders[0];
            $orderName = $order['YZMC'] ?? '';
            var_dump("医嘱名称：" . $orderName);
            $group = [
                'status' => 0,
                'content' => []
            ];
            $group['content'][] = ['status' => 1, 'content' => "医嘱名称【{$orderName}】"];
            $group['content'][] = ['status' => 1, 'content' => "死亡时间【{$swsj}】"];



            if (empty($records)) {
                $group['content'][] = ['status' => 0, 'content' => "死亡病例讨论记录【无】"];
                $errorContent[] = $group;
                $insert['swtljswcl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::updateOrInsert(['zyh' => $ZYH], $insert);
                continue;
            }

            // 检查记录的时间字段是否在时间范围内
            $matched = false;
            $matchedTime = '';
            $matchedStatus = '';

            foreach ($records as $record) {
                $tmpTime = $record[$timeField] ?? '';
                if (empty($tmpTime)) {
                    continue;
                }
                $group['content'][] = ['status' => 1, 'content' => "死亡讨论【{$record['BLMC']}】"];
                $tmpTimeTimestamp = strtotime($tmpTime);
                if ($tmpTimeTimestamp >= $swsjTimestamp && $tmpTimeTimestamp <= ($swsjTimestamp + 120 * 3600)) {
                    // 在5天内
                    $matched = true;
                    $matchedTime = $tmpTime;
                    $matchedStatus = '（5天内）';
                    break;
                } elseif ($tmpTimeTimestamp > ($swsjTimestamp + 120 * 3600)) {
                    // 超5天
                    if (!$matched) {
                        $matchedTime = $tmpTime;
                        $matchedStatus = '（超5天）';
                    }
                } elseif ($tmpTimeTimestamp < $swsjTimestamp) {
                    // 提前创建
                    if (!$matched) {
                        $matchedTime = $tmpTime;
                        $matchedStatus = '（提前创建）';
                    }
                }
            }

            if ($matched) {
                $group['status'] = 1;
                $insert['swtljswcl_fz'] = 1;
                $group['content'][] = ['status' => 1, 'content' => "首次签名时间【{$matchedTime}{$matchedStatus}】"];
            } else {
                if (!empty($matchedTime)) {
                    $group['content'][] = ['status' => 0, 'content' => "首次签名时间【{$matchedTime}{$matchedStatus}】"];
                } else {
                    $group['content'][] = ['status' => 0, 'content' => "首次签名时间【无】"];
                }
            }

            $errorContent[] = $group;

            if (!empty($errorContent)) {
                $insert['swtljswcl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }

            //如果zyh不为空，根据zyh更新或插入es
            if (!empty($zyh)) {
                $esService = new ElasticsearchService('indicator');
                $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
            }
        }

        IndexCatalog::query()->where("index_name", "=", "swtljswcl")->update(["status" => 2, 'quality_time' => time()]);
    }

    /**
     * 计算术者符合授权目录一致率（szfhsqmlyzl）
     * - 分母：查询手麻系统（SM_SSAP），符合的算分母
     * - 分子：术者代码在手术授权目录中
     *   1) 术者是SZ，术者代码是SZDM
     *   2) 手术名称是ICD9_SSCZMC，手术编码是ICD9_SSCZBM
     *   3) 用手术编码关联ssml表的YY_SSCZBM
     *   4) 使用sz（术者姓名）关联查询staff的code（可能会查到多个），有任意一个在ssml的code中就算分子
     *   5) 手术权限是ssml表的YY_SSJBMC
     */
    public function calculateSzfhsqmlyzl($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("szfhsqmlyzl", 1);
        $indicatorService = new IndicatorService();
        $patientData = $indicatorService->getPatientInfoData($zyh, $start_time, $end_time);

        foreach ($patientData as $patient) {
            // 删除旧结果
            Indicator::updateOrInsert(['zyh' => $patient['MED_REC_ID']], ['szfhsqmlyzl_fz' => 0, 'szfhsqmlyzl_fm' => 0, 'szfhsqmlyzl_error' => null]);
            $ZYH = $patient['MED_REC_ID'];

            $insert = [
                'zyh' => $ZYH,
                'szfhsqmlyzl_fz' => 0,
                'szfhsqmlyzl_fm' => 0,
                'szfhsqmlyzl_error' => null,
                'AAC11N' => $patient['AAC11N'],
                'AEE03' => !empty($patient['AEE03']) ? $patient['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($patient['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($patient['AAC01'])),
                'AAC01' => $patient['AAC01']
            ];

            $errorContent = [];

            // 查询手麻系统（SM_SSAP），符合的算分母
            $ssapList = SM_SSAP::query()
                ->where('ZYH', $ZYH)
                ->whereNotNull('SZ')
                ->whereNotNull('SZDM')
                ->whereNotNull('ICD9_SSCZMC')
                ->whereNotNull('ICD9_SSCZBM')
                ->where('ICD9_SSCZMC', '<>', 'NULL')
                ->where('ICD9_SSCZBM', '<>', '')
                ->get()
                ->toArray();

            if (empty($ssapList)) {
                continue;
            }

            // 遍历每个手术
            foreach ($ssapList as $ssap) {
                $surgeryName = $ssap['ICD9_SSCZMC'] ?? '';
                $surgeryCode = $ssap['ICD9_SSCZBM'] ?? '';
                $surgeonName = $ssap['SZ'] ?? '';
                $surgeonCode = $ssap['SZDM'] ?? '';
                $surgeryType = '';

                if (empty($surgeryName) || empty($surgeryCode) || empty($surgeonName)) {
                    continue;
                }
                $ruleMap8047 = RuleWordMap::query()->where('id', '=', 8047)->value('keyword');
                $excludeKeywords8047 = strpos($ruleMap8047, ',') !== false ? explode(',', $ruleMap8047) : [$ruleMap8047];
                $ruleMap8048 = RuleWordMap::query()->where('id', '=', 8048)->value('keyword');
                $excludeKeywords8048 = strpos($ruleMap8048, ',') !== false ? explode(',', $ruleMap8048) : [$ruleMap8048];
                // 手术级别过滤
                if (!empty($ruleMap8047)) {
                    $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
                    if (!empty($ssCZ)) {
                        $surgeryType = $ssCZ;
                        if (!in_array($ssCZ, $excludeKeywords8047)) {
                            if (!empty($ruleMap8048)) {
                                if (!in_array($surgeryName, $excludeKeywords8048)) {
                                    continue;
                                }
                            } else {
                                continue;
                            }
                        }
                    }
                }

                $group = [
                    'status' => 0,
                    'content' => []
                ];
                $group['content'][] = ['status' => 1, 'content' => "（手麻）手术名称【{$surgeryName}】"];
                $group['content'][] = ['status' => 1, 'content' => "（手麻）手术编码【{$surgeryCode}】"];
                if (!empty($surgeryType)) {
                    $group['content'][] = ['status' => 1, 'content' => "（手麻）手术类型【{$surgeryType}】"];
                }
                if (!empty($surgeonCode)) {
                    $yggh = Staff::query()->where('code', $surgeonCode)->value('YGBH');
                    $group['content'][] = ['status' => 1, 'content' => "（手麻）术者【{$surgeonName}({$yggh})】"];
                    $group['content'][] = ['status' => 1, 'content' => "（手麻）术者代码【{$surgeonName}({$surgeonCode})】"];
                } else {
                    $group['content'][] = ['status' => 0, 'content' => "（手麻）术者【{$surgeonName}】"];
                }

                // 统计分母
                $insert['szfhsqmlyzl_fm'] += 1;

                // 用手术编码关联ssml表的YY_SSCZBM
                $ssmlList = GY_SSML::query()
                    ->where('YY_SSCZMC', $surgeryName)
                    ->orWhere('YY_SSCZBM', $surgeryCode)
                    ->get()
                    ->toArray();

                if (empty($ssmlList)) {
                    $group['content'][] = ['status' => 0, 'content' => "【未在医院手术分级目录中匹配到手麻中的手术】"];
                    $errorContent[] = $group;
                    continue;
                }


                // 检查是否有任意一个code在ssml的code中
                $matched = false;
                $matchedSsml = null;
                foreach ($ssmlList as $ssml) {
                    $ssmlCode = $ssml['code'] ?? '';
                    if (empty($ssmlCode)) {
                        continue;
                    }
                    // 检查是否有任意一个szdm在ssml的code中
                    if (strpos($ssmlCode, $surgeonCode) !== false) {
                        $matched = true;
                        $matchedSsml = $ssml;
                        break;
                    }
                }

                if ($matched && !empty($matchedSsml)) {
                    $group['status'] = 1;
                    $insert['szfhsqmlyzl_fz'] += 1;
                    $permission = $matchedSsml['YY_SSJBMC'] ?? '';
                    $group['content'][] = ['status' => 1, 'content' => "手术权限【{$permission}】"];
                } else {
                    $group['content'][] = ['status' => 0, 'content' => "手术权限【无对应权限】"];
                }

                $errorContent[] = $group;
            }

            if (!empty($errorContent)) {
                $insert['szfhsqmlyzl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        IndexCatalog::query()->where("index_name", "=", "szfhsqmlyzl")->update(["status" => 2, 'quality_time' => time()]);
    }

    /**
     * 计算手术医师手术时间重合率（ssyssssjch）
     * - 分母：查询SM_SSAP，按照SSRQ分组，一个手术是一个分母
     * - 分子：同一术者在同一时间段有其他手术（时间重合）
     *   1) 获取id，手术名称，术者，术者代码，手术开始时间，手术结束时间
     *   2) 查询SM_SSAP：SZ=当前sz，SSRQ >=手术开始时间且<=手术结束时间，id != 当前id
     *   3) 如果有数据就算做一个分子，输出所有重合时间段的手术
     */
    public function calculateSsyssssjch($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("ssyssssjch", 1);
        $indicatorService = new IndicatorService();
        $patientData = $indicatorService->getPatientInfoData($zyh, $start_time, $end_time);

        foreach ($patientData as $patient) {
            // 删除旧结果
            Indicator::updateOrInsert(['zyh' => $patient['MED_REC_ID']], ['ssyssssjch_fz' => 0, 'ssyssssjch_fm' => 0, 'ssyssssjch_error' => null]);
            $ZYH = $patient['MED_REC_ID'];

            $insert = [
                'zyh' => $ZYH,
                'ssyssssjch_fz' => 0,
                'ssyssssjch_fm' => 0,
                'ssyssssjch_error' => null,
                'AAC11N' => $patient['AAC11N'],
                'AEE03' => !empty($patient['AEE03']) ? $patient['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($patient['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($patient['AAC01'])),
                'AAC01' => $patient['AAC01']
            ];

            $errorContent = [];

            // 查询SM_SSAP，按照SSRQ分组，一个手术是一个分母
            $ssapList = SM_SSAP::query()
                ->where('ZYH', $ZYH)
                ->whereNotNull('SZ')
                ->whereNotNull('SSRQ')
                ->whereNotNull('JSRQ')
                ->where('SSRQ', '<>', '')
                ->where('JSRQ', '<>', '')
                ->where('SSRQ', 'not like', '1970-01-01%')
                ->where('JSRQ', 'not like', '1970-01-01%')
                ->orderBy('SSRQ', 'asc')
                ->get()
                ->toArray();

            if (empty($ssapList)) {
                continue;
            }

            // 遍历每个手术
            foreach ($ssapList as $ssap) {
                $currentId = $ssap['id'] ?? '';
                $surgeryName = $ssap['ICD9_SSCZMC'] ?? '';
                $surgeonName = $ssap['SZ'] ?? '';
                $surgeonCode = $ssap['SZDM'] ?? '';
                $surgeryStart = $ssap['SSRQ'] ?? '';
                $surgeryEnd = $ssap['JSRQ'] ?? '';
                $surgeryType = '';

                if (empty($currentId) || empty($surgeonName) || empty($surgeryStart) || empty($surgeryEnd)) {
                    continue;
                }

                $ruleMap8047 = RuleWordMap::query()->where('id', '=', 8047)->value('keyword');
                $excludeKeywords8047 = strpos($ruleMap8047, ',') !== false ? explode(',', $ruleMap8047) : [$ruleMap8047];
                $ruleMap8048 = RuleWordMap::query()->where('id', '=', 8048)->value('keyword');
                $excludeKeywords8048 = strpos($ruleMap8048, ',') !== false ? explode(',', $ruleMap8048) : [$ruleMap8048];
                // 手术级别过滤
                if (!empty($ruleMap8047)) {
                    $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
                    if (!empty($ssCZ)) {
                        $surgeryType = $ssCZ;
                        if (!in_array($ssCZ, $excludeKeywords8047)) {
                            if (!empty($ruleMap8048)) {
                                if (!in_array($surgeryName, $excludeKeywords8048)) {
                                    continue;
                                }
                            } else {
                                continue;
                            }
                        }
                    }
                }

                $group = [
                    'status' => 0,
                    'content' => []
                ];
                $group['content'][] = ['status' => 1, 'content' => "（手麻）手术名称【{$surgeryName}】"];
                $group['content'][] = ['status' => 1, 'content' => "（手麻）手术类型【{$surgeryType}】"];
                $group['content'][] = ['status' => 1, 'content' => "（手麻）术者【{$surgeonName}】"];
                $group['content'][] = ['status' => 1, 'content' => "（手麻）手术开始时间【{$surgeryStart}】"];
                $group['content'][] = ['status' => 1, 'content' => "（手麻）手术结束时间【{$surgeryEnd}】"];

                // 统计分母
                $insert['ssyssssjch_fm'] += 1;

                // 查询同术者的其他手术，时间有重合的
                // SZ=当前sz，SSRQ >=手术开始时间且<=手术结束时间，id != 当前id
                $overlappingSurgeries = SM_SSAP::query()
                    ->where('SZDM', $surgeonCode)
                    ->where('SSRQ', '>=', $surgeryStart)
                    ->where('SSRQ', '<=', $surgeryEnd)
                    ->where('id', '<>', $currentId)
                    ->whereNotNull('SSRQ')
                    ->whereNotNull('JSRQ')
                    ->where('SSRQ', '<>', '')
                    ->where('JSRQ', '<>', '')
                    ->where('SSRQ', 'not like', '1970-01-01%')
                    ->where('JSRQ', 'not like', '1970-01-01%')
                    ->get()
                    ->toArray();

                if (!empty($overlappingSurgeries)) {
                    // 有重合，算分子
                    $group['status'] = 1;
                    $insert['ssyssssjch_fz'] += 1;

                    // 输出所有重合时间段的手术
                    foreach ($overlappingSurgeries as $overlap) {
                        $overlapName = $overlap['ICD9_SSCZMC'] ?? '';
                        $overlapStart = $overlap['SSRQ'] ?? '';
                        $overlapEnd = $overlap['JSRQ'] ?? '';
                        //住院号
                        $overlapZYH = $overlap['ZYH'] ?? '';
                        //去brry查询aaa28和AAC01
                        $brry = ZY_BRRY::query()->where('ZYH', '=', $overlapZYH)->get()->toArray();
                        $group['content'][] = ['status' => 1, 'content' => "同时间段手术名称【{$overlapName}】"];
                        if (!empty($brry)) {
                            $group['content'][] = ['status' => 1, 'content' => "同时间段手术病案号【{$brry[0]['AAA28']}】"];
                            $group['content'][] = ['status' => 1, 'content' => "同时间段手术入院时间【{$brry[0]['AAB01']}】"];
                            $group['content'][] = ['status' => 1, 'content' => "同时间段手术出院时间【{$brry[0]['AAC01']}】"];
                        }
                        $group['content'][] = ['status' => 1, 'content' => "同时间段手术开始时间【{$overlapStart}】"];
                        $group['content'][] = ['status' => 1, 'content' => "同时间段手术结束时间【{$overlapEnd}】"];
                    }
                } else {
                    $group['content'][] = ['status' => 0, 'content' => "同时间段手术【无】"];
                }

                $errorContent[] = $group;
            }

            if (!empty($errorContent)) {
                $insert['ssyssssjch_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        IndexCatalog::query()->where("index_name", "=", "ssyssssjch")->update(["status" => 2, 'quality_time' => time()]);
    }

    /**
     * 四级手术术前多学科讨论率
     * 分母：四级手术台次（按START_TIME去重）
     * 分子：在手术开始时间前存在多学科讨论记录，且首次签名时间符合要求
     */
    public function calculateSjsssqdxktl($zyh = "", $start_time = "", $end_time = "")
    {
        IndexCatalog::editStatus("sjsssqdxktl", 1);
        $indicatorService = new IndicatorService();
        $patientData = $indicatorService->getPatientInfoData($zyh, $start_time, $end_time);

        // 获取规则配置
        $rule1055 = RuleWordMap::query()->where('id', 1055)->value('keyword'); // 四级手术术前多学科讨论结论记录关键词

        // 初始化ES服务
        $bl01esService = new ElasticsearchService('bl01_202303');

        foreach ($patientData as $patient) {
            //删除
            Indicator::updateOrInsert(['zyh' => $patient['MED_REC_ID']], ['sjsssqdxktl_fm' => 0, 'sjsssqdxktl_fz' => 0, 'sjsssqdxktl_error' => null]);
            $ZYH = $patient['MED_REC_ID'];

            $insert = [
                'zyh' => $ZYH,
                'sjsssqdxktl_fz' => 0,
                'sjsssqdxktl_fm' => 0,
                'sjsssqdxktl_error' => null,
                'AAC11N' => $patient['AAC11N'],
                'AEE03' => !empty($patient['AEE03']) ? $patient['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($patient['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($patient['AAC01'])),
                'AAC01' => $patient['AAC01']
            ];

            $errorContent = [];

            // 查询四级手术（使用病案首页数据）
            $mainoperation = MainOperation::query()->where('AAA28', '=', $ZYH)->where('OPE_LEVEL', '=', '4')->get()->toArray();
            $secondaryoperation = SecondaryOperation::query()->where('AAA28', '=', $ZYH)->where('OPE_LEVEL', '=', '4')->get()->toArray();
            $results = array_merge($mainoperation, $secondaryoperation);

            // 按照START_TIME分组去重
            $uniqueResults = [];
            foreach ($results as $item) {
                if (empty($item['START_TIME'])) {
                    continue; // 没有START_TIME则跳过
                }
                $uniqueResults[$item['START_TIME']] = $item; // 用START_TIME作为key，自动去重
            }
            $results = array_values($uniqueResults);

            if (!$results) {
                continue; // 没有四级手术记录，跳过
            }

            // 分母：按START_TIME去重的四级手术台次
            $insert['sjsssqdxktl_fm'] = count($results);

            $m = 1;
            $secsj = null;
            foreach ($results as $surgeryGroup) {
                $surgeryGroupContent = [
                    'status' => 0,
                    'content' => []
                ];

                //设置手术名称 以及 手术编码
                $surgeryGroupContent['content'][] = ["status" => 1, "content" => "首页手术名称【" . ($surgeryGroup['ICD9_NAME'] ?? '') . "】"];
                $surgeryGroupContent['content'][] = ["status" => 1, "content" => "首页手术编码【" . ($surgeryGroup['ICD9_ID1'] ?? '') . "】"];
                $surgeryGroupContent['content'][] = ["status" => 1, "content" => "首页手术级别【" . ($surgeryGroup['OPE_LEVEL'] ?? '') . "】"];
                $surgeryGroupContent['content'][] = ["status" => 1, "content" => "首页手术开始时间【" . ($surgeryGroup['START_TIME'] ?? '') . "】"];

                //获取手术开始时间
                $sskssj = $surgeryGroup['START_TIME'] ?? '';
                //获取手术结束时间
                $ssjssj = $surgeryGroup['END_TIME'] ?? '';

                if (empty($sskssj)) {
                    $surgeryGroupContent['content'][] = ["status" => 0, "content" => "手术开始时间【无】"];
                    $errorContent[] = $surgeryGroupContent;
                    $secsj = $ssjssj;
                    $m++;
                    continue;
                }

                //查询四级手术术前多学科讨论
                $bl01must = [
                    ['term' => ["JZHM" => $ZYH]],
                    ['term' => ["BLLB" => 294]]
                ];
                if ($m == 1) {
                    //小于开始时间
                    $bl01must[] = ['range' => ["ZXSJ" => ["lte" => $sskssj]]];
                } else {
                    $bl01must[] = ['range' => ["ZXSJ" => ["gt" => $secsj, "lte" => $sskssj]]];
                }
                $bl01must[] = ["match_phrase" => ["BLMC" => "多学科讨论"]];

                $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->getParams();
                $bl01Res = app('es')->search($params);
                $bl01Res = $bl01esService->getDataByEs($bl01Res);

                if (count($bl01Res[0]) > 0) {
                    $surgeryGroupContent['content'][] = ["status" => 1, "content" => "手术术前多学科讨论【" . ($bl01Res[0][0]['BLMC'] ?? '') . "】"];
                    //签名时间
                    $firstTime = $bl01Res[0][0]['first_blsy_time'] ?? '';
                    if ($m == 1) {
                        if (!empty($firstTime) && $firstTime > $sskssj) {
                            $surgeryGroupContent['content'][] = ["status" => 0, "content" => "首次签名时间【" . $firstTime . "(超时)】"];
                        } else {
                            //分子
                            $insert['sjsssqdxktl_fz'] += 1;
                            $surgeryGroupContent['status'] = 1;
                            $surgeryGroupContent['content'][] = ["status" => 1, "content" => "首次签名时间【" . $firstTime . "】"];
                        }
                        $errorContent[] = $surgeryGroupContent;
                    } else {
                        if (empty($firstTime)) {
                            $surgeryGroupContent['content'][] = ["status" => 0, "content" => "首次签名时间【无】"];
                        } elseif ($firstTime < $secsj) {
                            $surgeryGroupContent['content'][] = ["status" => 0, "content" => "首次签名时间【" . $firstTime . "(提前)】"];
                        } elseif ($firstTime > $ssjssj) {
                            $surgeryGroupContent['content'][] = ["status" => 0, "content" => "首次签名时间【" . $firstTime . "(超时)】"];
                        } else {
                            //分子
                            $insert['sjsssqdxktl_fz'] += 1;
                            $surgeryGroupContent['status'] = 1;
                            $surgeryGroupContent['content'][] = ["status" => 1, "content" => "首次签名时间【" . $firstTime . "】"];
                        }
                        $errorContent[] = $surgeryGroupContent;
                    }
                } else {
                    $surgeryGroupContent['content'][] = ["status" => 0, "content" => "术前多学科讨论【无】"];
                    $errorContent[] = $surgeryGroupContent;
                }
                $secsj = $ssjssj;
                $m++;
            }

            if ($insert['sjsssqdxktl_fm'] > 0) {
                $insert['sjsssqdxktl_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }

            if (!empty($zyh)) {
                $esService = new ElasticsearchService('indicator');
                $esService->updateOrInsertByCondition('zyh', $insert['zyh'], $insert);
            }
        }

        IndexCatalog::query()->where("index_name", "=", "sjsssqdxktl")->update(["status" => 2, 'quality_time' => time()]);
    }
}
