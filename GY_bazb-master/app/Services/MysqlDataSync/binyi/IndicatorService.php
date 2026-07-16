<?php

namespace App\Services\MysqlDataSync\binyi;

use App\Model\KZR;
use App\Model\WJZ;
use App\Model\Yzb;
use App\Model\Mzjl;
use App\Model\Staff;
use App\Model\Bllb303;
use App\Model\SM_SSAP;
use App\Model\SSCZ;
use App\Model\XJSXXMSS;
use App\Model\Indicator;
use App\Model\Bllb294_45;
use App\Model\EMR_BL_BLSY;
use App\Model\EMR_BL_BLXG;
use App\Model\EMR_BL_BL01;
use App\Model\PatientInfo;
use App\Model\RuleWordMap;
use App\Model\IndexCatalog;
use App\Model\MainDiagnosis;
use App\Model\MainOperation;
use App\Model\PatientInfoV2;
use App\Model\OtherDiagnosis;
use Illuminate\Support\Carbon;
use App\Model\HospitalDaySurgery;
use App\Model\PatientHospitalInfo;
use App\Model\SecondaryOperation;
use App\Services\RadioService;
use App\Model\RYQX;
use App\Model\ZY_SS;
use App\Model\MedicinalInfo;
use App\Model\SSSQ;
use App\Model\YS_ZY_HZSQ;
use App\Model\YS_ZY_HZYJ;
use App\Model\IENR_FXPG;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\ElasticsearchService;
use App\Services\NewElasticsearchService;


class IndicatorService
{
    protected $timeField; //设置的时间字段

    protected $ruleMap2035 = 3; //医嘱状态   0新开、1提交、2疑问、3作废、4停嘱、5复核通过
    protected $ruleMap2036 = 9; //作废病历标识 0书写 1完成 2封存(归档) 9删除
    protected $ruleMap2030 = 294; //病程记录BLLB
    protected $hospitalName;
    protected $ruleMap1045 = 44; //疑难病例讨论结论记录MBLB
    protected $ruleMap1050 = 50; //上级医师查房记录MBLB

    public function __Construct()
    {
        //医嘱状态   0新开、1提交、2疑问、3作废、4停嘱、5复核通过
        $ruleMap2035 = RuleWordMap::query()->where('id', '=', 2035)->value('keyword');
        $this->ruleMap2035 = !empty($ruleMap2035) ? $ruleMap2035 : $this->ruleMap2035;
        //作废病历标识 0书写 1完成 2封存(归档) 9删除
        $ruleMap2036 = RuleWordMap::query()->where('id', '=', 2036)->value('keyword');
        $this->ruleMap2036 = !empty($ruleMap2036) ? $ruleMap2036 : $this->ruleMap2036;
        $ruleMap2030 = RuleWordMap::query()->where('id', '=', 2030)->value('keyword');
        $this->ruleMap2030 = !empty($ruleMap2030) ? $ruleMap2030 : $this->ruleMap2030;
        //疑难病例讨论结论记录MBLB
        $ruleMap1045 = RuleWordMap::query()->where('id', '=', 1045)->value('keyword');
        $this->ruleMap1045 = !empty($ruleMap1045) ? $ruleMap1045 : $this->ruleMap1045;
        //上级医师查房记录MBLB
        $ruleMap1050 = RuleWordMap::query()->where('id', '=', 1050)->value('keyword');
        $this->ruleMap1050 = !empty($ruleMap1050) ? $ruleMap1050 : $this->ruleMap1050;
        //医院名称
        $this->hospitalName = config('confAdmin.hospital_name');
        //获取map里面设定的创建时间字段
        $timeField = RuleWordMap::query()->where('id', '=', 2001)->value('keyword');
        $this->timeField = !empty($timeField) ? $timeField : "first_blsy_time";
    }

    /**
     * 获取患者信息(出院患者)
     * @param $zyh
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public function getPatientInfoData($zyh, $startTime, $endTime)
    {
        // 只查询需要的字段，提升查询性能
        // AAC11N 直接使用 department.dep_name，如果为空则使用 patient_info.AAC11N
        $filed = [
            'patient_info.MED_REC_ID',
            'patient_info.AAB01',
            'patient_info.AAC01',
            'patient_info.AAC04',
            'patient_info.AEN01',
            'patient_info.AEM01C',
            'ZY_BRRY.ZZYSMC as AEE03',
            // AAC11N 使用 department.dep_name（通过 BRKS 关联）
            DB::raw('COALESCE(NULLIF(TRIM(department.dep_name), \'\'), \'\') as AAC11N')
        ];
        $query = PatientInfo::query()
            ->leftJoin('ZY_BRRY', 'patient_info.MED_REC_ID', '=', 'ZY_BRRY.ZYH')
            // 使用 TRIM 去除空格，确保 JOIN 条件匹配准确
            ->leftJoin('department', function ($join) {
                $join->on(DB::raw('TRIM(ZY_BRRY.BRKS)'), '=', DB::raw('TRIM(department.dep_id)'));
            })
            ->where("patient_info.AAC01", "!=", "1970-01-01 00:00:00")
            ->where("patient_info.AAC01", "!=", "0000-00-00 00:00:00")
            ->where("patient_info.AAC01", "!=", "")
            ->whereNotNull("patient_info.AAC01")
            ->whereNotNull("patient_info.AAB01") // 确保入院时间不为空
            ->where("patient_info.AAB01", "!=", "")
            ->whereColumn("patient_info.AAC01", ">", "patient_info.AAB01"); // 排除出院时间早于入院时间的异常数据
        if (!empty($zyh)) {
            $query = $query->where('patient_info.MED_REC_ID', '=', $zyh);
        } elseif (!empty($startTime) && !empty($endTime)) {
            $query = $query->whereBetween("patient_info.AAC01", [$startTime, $endTime]);
        }
        $data = $query->get($filed)->toArray();
        return !empty($data) ? $data : [];
    }

    /**
     * 获取患者信息(所有患者)
     * @param $zyh
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public function getPatientInfoDataAll($zyh, $startTime, $endTime)
    {
        // 只查询需要的字段，提升查询性能
        // AAC11N 直接使用 department.dep_name，如果为空则使用 patient_info.AAC11N
        $filed = [
            'patient_info.MED_REC_ID',
            'patient_info.AAB01',
            'patient_info.AAC01',
            'patient_info.AAC04',
            'patient_info.AEN01',
            'patient_info.AEM01C',
            'ZY_BRRY.ZZYSMC as AEE03',
            // AAC11N 使用 department.dep_name（通过 BRKS 关联）
            DB::raw('COALESCE(NULLIF(TRIM(department.dep_name), \'\'), \'\') as AAC11N')
        ];
        $query = PatientInfo::query()
            ->leftJoin('ZY_BRRY', 'patient_info.MED_REC_ID', '=', 'ZY_BRRY.ZYH')
            // 使用 TRIM 去除空格，确保 JOIN 条件匹配准确
            ->leftJoin('department', function ($join) {
                $join->on(DB::raw('TRIM(ZY_BRRY.BRKS)'), '=', DB::raw('TRIM(department.dep_id)'));
            })
            ->where("patient_info.AAB01", "!=", "1970-01-01 00:00:00")
            ->where("patient_info.AAB01", "!=", "0000-00-00 00:00:00")
            ->where("patient_info.AAB01", "!=", "")
            ->whereNotNull("patient_info.AAB01");
        if (!empty($zyh)) {
            $query = $query->where('patient_info.MED_REC_ID', '=', $zyh);
        } elseif (!empty($startTime) && !empty($endTime)) {
            $query = $query->whereBetween("patient_info.AAB01", [$startTime, $endTime]);
        }
        $data = $query->get($filed)->toArray();
        return !empty($data) ? $data : [];
    }


    /**
     * 三级医师查房频次达标率
     * @param $data
     * @return void
     */
    public function handleSjyscf($data)
    {
        //初始化es
        $BL01ESServer = new ElasticsearchService('bl01_202303');
        //三级医师查房关键字
        $sjyscfMap = RuleWordMap::query()->where('id', '=', 1020)->value('keyword');
        $sjyscfMap = !empty($sjyscfMap) ? explode(",", $sjyscfMap) : ["主任医师查房记录", "副主任医师查房记录"];

        //获取三级医师查房的周期
        $sjyscfPeriod = RuleWordMap::query()->where('id', '=', 1029)->value('keyword');
        $sjyscfPeriod = !empty($sjyscfPeriod) ? $sjyscfPeriod : 7;

        //获取三级医师每周期内应查房次数
        $sjyscfNum = RuleWordMap::query()->where('id', '=', 1030)->value('keyword');
        $sjyscfNum = !empty($sjyscfNum) ? $sjyscfNum : 2;

        $ruleMap1058 = RuleWordMap::query()->where('id', '=', 1058)->value('keyword');
        $ruleMap1058 = !empty($ruleMap1058) ? $ruleMap1058 : 'JLSJ';

        $this->UpdateIndexCataLog('sjyscf', 1, time());
        $should = [];
        foreach ($sjyscfMap as $v) {
            $should[] = ['match_phrase' => ['BLMC' => $v]];
        }
        foreach ($data as $item) {
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['sjyscf_fm' => 0, 'sjyscf_fz' => 0, 'sjyscf_error' => null]);
            $error = [];
            //如果出院时间和入院时间在24小时内的排除
            /**
             * 日间病历排除
             */
            if (strtotime($item['AAC01']) - strtotime($item['AAB01']) <= 86400) {
                continue;
            }
            $insert = [
                'zyh' => $item['MED_REC_ID'],
                'sjyscf_fz' => 0,
                'sjyscf_fm' => 0,
                'sjyscf_error' => null,
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($item['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($item['AAC01'])),
                'AAC01' => $item['AAC01']
            ];
            $error = [];
            $startTime = $item['AAB01'];
            $endTime = $item['AAC01'];
            $zyDayNum = intval($item['AAC04']); //住院天数

            $ycfNum = $sjyscfNum;
            //分母：住院天数/周期数  取商 比如住院10天，分母就是 10/7 = 2
            $zqNum = intval(ceil($zyDayNum / $sjyscfPeriod));
            $insert['sjyscf_fm'] += $zqNum;
            $carbon = new Carbon();
            $tempNum = 0;
            while (true) {
                $tempContent1 = [];
                $tempContent2 = [];
                $tempContent3 = [];
                //                $startTimeEnd = date('Y-m-d H:i:s', strtotime($startTime) + $sjyscfPeriod * 24 * 3600 - 1);
                $startTimeEnd = $carbon::parse($carbon::parse($startTime)->format('Y-m-d'))
                    ->addDays($sjyscfPeriod)->subSeconds(1)->format('Y-m-d H:i:s');

                if ($carbon::parse($startTimeEnd)->timestamp >= $carbon::parse($endTime)->timestamp) {
                    $startTimeEnd = $endTime;
                    //算算几天
                    $syDayNum = ceil((strtotime($endTime) - strtotime($startTime)) / (24 * 3600));
                    $tempNum = intdiv($syDayNum, 3); //计算出来剩余天数应该查房次数
                    $ycfNum = $tempNum;
                    $str = "查房周期【{$syDayNum}天/{$ycfNum}次】";
                    $str .= "【" . date('Y-m-d H:i:s', strtotime($startTime)) . "】至【" . date('Y-m-d H:i:s', strtotime($endTime)) . "】";
                    $tempContent1[] = ["status" => 1, "content" => $str];
                } else {
                    $str = "查房周期【{$sjyscfPeriod}天/{$ycfNum}次】";
                    $str .= "【" . date('Y-m-d H:i:s', strtotime($startTime)) . "】至【" . date('Y-m-d H:i:s', strtotime($startTimeEnd)) . "】";
                    $tempContent1[] = ['status' => 1, 'content' => $str];
                }
                $must = [
                    ['term' => ['JZHM' => $item['MED_REC_ID']]],
                    ['term' => ['BLLB' => 294]],
                    ['bool' => ['should' => $should]],
                    ['range' => [$ruleMap1058 => ['gte' => $startTime, 'lte' => $startTimeEnd]]]
                ];
                $mustNot = ['term' => ['BLZT' => $this->ruleMap2036]];
                $params = $BL01ESServer->clearMust()->queryByMustBatch($must)->queryByMustNot($mustNot)->orderBy($ruleMap1058, 'asc')->getParams();
                $res = app('es')->search($params);
                $data = $BL01ESServer->getDataByEs($res);

                $tempNum2 = 0;
                if (!empty($data[1])) {
                    //                    Log::info('病程信息',$data[0]);
                    foreach ($data[0] as $val) {
                        //表头符合周期时间，但是首次签名时间不符合 (废弃)
                        //                        if ($val[$this->timeField] < $startTime) {
                        //                            $tempContent3[] = ["status" => 0, "content" => "【" . $val['BLMC'] . "(提前)】"];
                        //                        } else
                        //判断周期内签名时间是否正确
                        if ($val[$this->timeField] >= $startTime && $val[$this->timeField] <= $startTimeEnd) {
                            $tempNum2 += 1;
                            $tempContent3[] = ["status" => 1, "content" => "【" . $val['BLMC'] . "】"];
                        } else {
                            //                            $tempContent3[] = ["status" => 0, "content" => "【" . $val['BLMC'] . "(超时)】"];
                            $tempContent3[] = ["status" => 0, "content" => "【" . $val['BLMC'] . "】"];
                        }
                    }

                    if ($tempNum2 < $ycfNum) {
                        $tempContent2[] = ["status" => 0, "content" => "医师查房【" . $tempNum2 . "次】"];
                    } else {
                        $insert['sjyscf_fz'] += 1;
                        $tempContent2[] = ["status" => 1, "content" => "医师查房【" . $tempNum2 . "次】"];
                        $tempContent3 = array_filter($tempContent3, function ($val) {
                            return ($val['status'] == 1);
                        });
                    }
                } else {
                    if (0 == $ycfNum) {
                        $insert['sjyscf_fz'] += 1;
                        $tempContent2[] = ['status' => 1, "content" => "医师查房【0次】"];
                    } else {
                        $tempContent2[] = ['status' => 0, "content" => "医师查房【0次】"];
                    }
                }

                $tempContent4 = array_merge($tempContent1, $tempContent2, $tempContent3);
                if (!empty($tempContent4)) {
                    if ($tempNum2 >= $ycfNum || 0 == $ycfNum) {
                        $error[] = ["status" => 1, "content" => array_merge($tempContent4)];
                    } else {
                        $error[] = ["status" => 0, "content" => array_merge($tempContent4)];
                    }
                }
                if ($startTimeEnd >= $endTime) {
                    break;
                } else {
                    //                    $startTime = date('Y-m-d H:i:s', strtotime($startTimeEnd) + 1);
                    $startTime = $carbon::parse($startTimeEnd)->addDays(1)->format("Y-m-d 00:00:00");
                }
            }

            $insert['sjyscf_fz'] = $insert['sjyscf_fz'] > $insert['sjyscf_fm'] ? $insert['sjyscf_fm'] : $insert['sjyscf_fz'];
            $insert['sjyscf_error'] = !empty($error) ? json_encode($error, 256) : '';
            Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
        }
        $this->UpdateIndexCataLog('sjyscf', 2, time());
    }

    /**
     * 二级医师查房频次达标率
     * @param $data
     * @return void
     */
    public function handleEjyscf($data)
    {
        // 使用单例模式初始化ES服务
        $elasticService = NewElasticsearchService::getInstance();
        $carbon = new Carbon();

        // 二级医师查房关键字
        $ejyscfMap = RuleWordMap::query()->where('id', '=', 1021)->value('keyword');
        $ejyscfMap = !empty($ejyscfMap) ? explode(",", $ejyscfMap) : ["主治医师"];
        // 获取二级医师查房的周期
        $ejyscfPeriod = RuleWordMap::query()->where('id', '=', 1031)->value('keyword');
        $ejyscfPeriod = !empty($ejyscfPeriod) ? $ejyscfPeriod : 7;
        // 获取二级医师每周期内应查房次数
        $ejyscfNum = RuleWordMap::query()->where('id', '=', 1033)->value('keyword');
        $ejyscfNum = !empty($ejyscfNum) ? $ejyscfNum : 3;

        $ruleMap1058 = RuleWordMap::query()->where('id', '=', 1058)->value('keyword');
        $ruleMap1058 = !empty($ruleMap1058) ? $ruleMap1058 : 'ZXSJ';

        //是否排除产科1是0否，id8096=1是，0否
        $excludeProduc = RuleWordMap::query()->where('id', '=', 8096)->value('keyword');
        $excludeProduc = !empty($excludeProduc) ? $excludeProduc : 0;
        $this->UpdateIndexCataLog('ejyscf', 1, time());

        foreach ($data as $item) {

            //删除已经存在的数据
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['ejyscf_fm' => 0, 'ejyscf_fz' => 0, 'ejyscf_error' => null]);

            // 如果出院时间和入院时间在24小时内的排除
            /**
             * 日间病历排除
             */
            if (strtotime($item['AAC01']) - strtotime($item['AAB01']) <= 86400) {
                continue;
            }

            // 检查诊断和手术条件
            $whereMust = ['term' => ['ZYH' => data_get($item, 'MED_REC_ID')]];

            // 检查主诊断：ICD10_ID1 包含 Z37
            $shouldDiagnosis = ['prefix' => ['ICD10_ID1.keyword' => 'Z37']];
            list($mainDiagnosisRes) = $elasticService->setIndex('main_diagnosis')->clearMust()
                ->queryByMust($whereMust)
                ->queryByShould($shouldDiagnosis)
                ->minimumShouldMatch()
                ->search();

            // 检查其他诊断：ICD10_ID1 包含 Z37
            list($otherDiagnosisRes) = $elasticService->setIndex('other_diagnosis_2023')->clearMust()
                ->queryByMust($whereMust)
                ->queryByShould($shouldDiagnosis)
                ->minimumShouldMatch()
                ->search();

            // 检查主手术：ICD9_ID1 包含 74.1
            $shouldOperation = ['prefix' => ['ICD9_ID1.keyword' => '74.1']];
            list($mainOperationRes) = $elasticService->setIndex('main_operation')->clearMust()
                ->queryByMust(['term' => ['AAA28' => data_get($item, 'MED_REC_ID')]])
                ->queryByShould(['prefix' => ['ICD9_ID1' => '74.1']])
                ->minimumShouldMatch()
                ->search();

            // 检查次要手术：ICD9_ID1 包含 74.1
            list($secondaryOperationRes) = $elasticService->setIndex('secondary_operation_2023')->clearMust()
                ->queryByMust($whereMust)
                ->queryByShould($shouldOperation)
                ->minimumShouldMatch()
                ->search();

            // 合并诊断和手术数据
            $arrayICD10 = array_merge($mainDiagnosisRes, $otherDiagnosisRes); // 首页诊断编码
            $arrayICD9 = array_merge($mainOperationRes, $secondaryOperationRes); // 首页手术编码

            // 首页诊断编码含【Z37】且首页手术编码不含【74.1】的病历剔除
            if ($arrayICD10 && empty($arrayICD9) && $excludeProduc == 1) {
                continue;
            }

            $insert = [
                'zyh' => data_get($item, 'MED_REC_ID'),
                'ejyscf_fz' => 0,
                'ejyscf_fm' => 0,
                'ejyscf_error' => null,
                'AAC11N' => data_get($item, 'AAC11N'),
                'AEE03' => data_get($item, 'AEE03', ''),
                'AAC01_YEAR' => $carbon::parse(data_get($item, 'AAC01'))->format("Y"),
                'AAC01_MONTH' => $carbon::parse(data_get($item, 'AAC01'))->format("m"),
                'AAC01' => data_get($item, 'AAC01')
            ];
            $errorMsg = [];
            $startTime = data_get($item, 'AAB01'); // 入院时间
            $endTime = data_get($item, 'AAC01') ?? ''; // 出院时间

            if (empty($endTime)) {
                continue;
            } else {
                while (true) {
                    $tempContent1 = $tempContent2 = $tempContent3 = [];

                    $weekStr = "查房周期【%s天/%s次】【%s】至【%s】";

                    // 确保 startTime 格式化为 Y-m-d H:i:s 格式，以匹配 Elasticsearch 索引格式
                    $startTime = $carbon::parse($startTime)->format('Y-m-d H:i:s');

                    $startTimeEnd = $carbon::parse($carbon::parse($startTime)->format('Y-m-d'))
                        ->addDays($ejyscfPeriod)->subSeconds(1)->format('Y-m-d H:i:s');

                    $strContent1 = sprintf(
                        $weekStr,
                        $ejyscfPeriod,
                        $ejyscfNum,
                        $carbon::parse($startTime)->format('Y-m-d H:i:s'),
                        $carbon::parse($startTimeEnd)->format('Y-m-d H:i:s')
                    );

                    if ($carbon::parse($startTimeEnd)->gte($carbon::parse($endTime))) {
                        // 确保 endTime 格式化为 Y-m-d H:i:s 格式，以匹配 Elasticsearch 索引格式
                        $startTimeEnd = $carbon::parse($endTime)->format('Y-m-d H:i:s');
                        // 计算剩余天数
                        $syDayNum = ceil((strtotime($endTime) - strtotime($startTime)) / (24 * 3600));
                        // 实际应该查房数量
                        $ycfNum = $this->calculateYcfNum($syDayNum);

                        $strContent1 = sprintf(
                            $weekStr,
                            $syDayNum,
                            $ycfNum,
                            $carbon::parse($startTime)->format('Y-m-d H:i:s'),
                            $carbon::parse($endTime)->format('Y-m-d H:i:s')
                        );
                    } else {
                        $ycfNum = $ejyscfNum;
                    }

                    // 所有周期都计入分母（与循环内实际处理的周期保持一致）
                    $insert['ejyscf_fm']++;

                    $tempContent1[] = ["status" => 1, "content" => $strContent1];

                    // 查询周期内的病程记录
                    $must = [
                        ['term' => ['JZHM' => $item['MED_REC_ID']]],
                        ['term' => ['BLLB' => 294]],
                        ['range' => [$ruleMap1058 => ['gte' => $startTime, 'lte' => $startTimeEnd]]]
                    ];
                    $mustNot = ['term' => ['BLZT' => $this->ruleMap2036]];

                    list($bl01Data) = $elasticService->setIndex('bl01_202303')->clearMust()
                        ->queryByMustBatch($must)
                        ->queryByMustNot($mustNot)
                        ->orderBy($ruleMap1058, 'asc')
                        ->search();

                    $cf_str = "医师查房【%s次】";
                    $qm_str = "【%s】";

                    $realNum = 0; // 医生实际查房数量
                    $strContent2 = ["status" => 0, "content" => sprintf($cf_str, 0)];

                    if (empty($bl01Data) && (int) $ycfNum <= 0) {
                        $insert['ejyscf_fz']++;
                        data_set($strContent2, 'status', 1);
                    } else {
                        foreach ($bl01Data as $val) {
                            $t = $this->timeField;
                            if (empty($val[$t])) {
                                continue;
                            }

                            $strContent3 = ["status" => 0, "content" => sprintf($qm_str, $val['BLMC'])];
                            $first = $val['first_blsy_time'] ?? '';

                            // 查询签名信息
                            $blsy = $this->helpEjyscf($val['BLBH']);
                            $blsyStr = '';
                            if (!empty($blsy)) {
                                foreach ($blsy as $sy) {
                                    $blsyStr .= $sy['name'] . '(' . $sy['ygjb_text'] . '),';
                                }
                                $blsyStr = rtrim($blsyStr, ',');
                            }
                            $strContent4 = ["status" => 0, "content" => 'CA签名【' . ($blsyStr ?: '无') . '】'];

                            // 判断时间是否在周期内
                            if (
                                $carbon::parse($val[$t])->gte($startTime)
                                && $carbon::parse($val[$t])->lte($startTimeEnd)
                            ) {
                                // 时间在周期内，检查签名是否符合要求
                                $strContent5 = ["status" => 0, "content" => '首次签名时间【' . $first . '】'];

                                // 检查是否有符合条件的医师签名
                                $hasValidDoctor = false;
                                if (!empty($blsy)) {
                                    foreach ($blsy as $sy) {
                                        if (in_array($sy['ygjb_text'], $ejyscfMap)) {
                                            $hasValidDoctor = true;
                                            break;
                                        }
                                    }
                                }

                                if ($hasValidDoctor) {
                                    $realNum++; // 实际查房数量
                                    data_set($strContent3, 'status', 1);
                                    data_set($strContent4, 'status', 1);
                                    data_set($strContent5, 'status', 1);
                                }

                                $tempContent3[] = $strContent3;
                                $tempContent3[] = $strContent5;
                                $tempContent3[] = $strContent4;
                                //添加一行空信息用来分隔
                                $tempContent3[] = ["status" => 0, "content" => ""];
                            } else {
                                // 超时或提前
                                $timeStatus = $carbon::parse($val[$t])->gt($startTimeEnd) ? '(超时)' : '(提前)';
                                $strContent5 = ["status" => 0, "content" => '首次签名时间【' . $first . '】' . $timeStatus];

                                $tempContent3[] = $strContent3;
                                $tempContent3[] = $strContent5;
                                $tempContent3[] = $strContent4;
                                //添加一行空信息用来分隔
                                $tempContent3[] = ["status" => 0, "content" => ""];
                            }
                        }

                        if (empty($bl01Data)) {
                            $tempContent3[] = ["status" => 0, "content" => "病程记录【无】"];
                        }

                        data_set($strContent2, 'status', ($realNum < $ycfNum) ? 0 : 1);
                        data_set($strContent2, 'content', sprintf($cf_str, $realNum));

                        if ($realNum >= $ycfNum) {
                            $insert['ejyscf_fz']++;
                            // 如果满足查房次数，只存储符合的数据（添加 null 检查）
                            $tempContent3 = array_filter($tempContent3, function ($val) {
                                return !is_null($val) && isset($val['status']) && ($val['status'] == 1);
                            });
                        }
                    }

                    $tempContent2[] = $strContent2;
                    $tempContent4 = array_merge($tempContent1, $tempContent2, $tempContent3);

                    if (!empty($tempContent4)) {
                        $errorMsg[] = ["status" => ((($realNum >= $ycfNum) || (0 == $ycfNum)) ? 1 : 0), "content" => $tempContent4];
                    }

                    // 如果周期时间 >= 结束时间，结束周期循环
                    if ($startTimeEnd >= $endTime) {
                        break;
                    }

                    // 将开始时间设置为上周期结束的下一天
                    $startTime = $carbon::parse($startTimeEnd)->addDays(1)->format("Y-m-d 00:00:00");
                }
            }

            $insert['ejyscf_fz'] = $insert['ejyscf_fz'] > $insert['ejyscf_fm'] ? $insert['ejyscf_fm'] : $insert['ejyscf_fz'];
            $insert['ejyscf_error'] = !empty($errorMsg) ? json_encode($errorMsg, 256) : '';
            Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
        }
        $this->UpdateIndexCataLog('ejyscf', 2, time());
    }

    private function calculateYcfNum($days)
    {
        if ($days >= 7) {
            return 3;
        } elseif ($days == 6) {
            return 2;
        } elseif ($days >= 3) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * 获取病历签名信息（优化版：直接从MySQL查询）
     * @param string $blbh 病历编号
     * @return array
     */
    public function helpEjyscf($blbh)
    {
        $result = [];

        // 直接从MySQL查询病历签名表
        $blsyData = EMR_BL_BLSY::query()
            ->where('BLBH', $blbh)
            ->where('FG_ACTIVE', 1)
            ->orderBy('JLSJ', 'ASC')
            ->get()
            ->toArray();

        if (!empty($blsyData)) {
            // 获取所有签名医生编号
            $syysArray = array_unique(array_column($blsyData, 'SYYS'));

            // 查询医生信息
            if (!empty($syysArray)) {
                $result = Staff::query()
                    ->whereIn('code', $syysArray)
                    ->get()
                    ->toArray();
            }
        }

        return $result;
    }


    /**
     * 指标：诊疗方案决策权医师审核率
     *
     * @param [type] $data
     * @return void
     */
    public function handleZlfajcqyssh($data)
    {
        $BL01ESServer = new ElasticsearchService('bl01_202303');
        $BLSYESServer = new ElasticsearchService('blsy_2023');
        //获取诊疗方案决策权医师审核
        $zlfajcqysshMap = RuleWordMap::query()->where('id', '=', 1039)->value('keyword');
        $zlfajcqysshMap = !empty($zlfajcqysshMap) ? explode(",", $zlfajcqysshMap) : ["主治医师", "主任医师", "副主任医师"];

        $ruleMap1054 = RuleWordMap::query()->where('id', '=', 1054)->value('keyword');
        $ruleMap1054 = !empty($ruleMap1054) ? $ruleMap1054 : 295;
        $this->UpdateIndexCataLog('zlfajcqyssh', 1, time());
        foreach ($data as $item) {
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['zlfajcqyssh_fz' => 0, 'zlfajcqyssh_fm' => 0, 'zlfajcqyssh_error' => null]);
            /**
             * 日间病历排除
             */
            if (strtotime($item['AAC01']) - strtotime($item['AAB01']) <= 86400) {
                continue;
            }
            $insert = [
                'zyh' => $item['MED_REC_ID'],
                'zlfajcqyssh_fz' => 0,
                'zlfajcqyssh_fm' => 0,
                'zlfajcqyssh_error' => '',
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($item['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($item['AAC01'])),
                'AAC01' => $item['AAC01']
            ];
            $insert['zlfajcqyssh_fm'] += 1;
            // 查询首次病程的签名里面是否有主治以上的以上签名即可
            $must = [
                ['term' => ['JZHM' => $item['MED_REC_ID']]], //住院号
                ['term' => ['BLLB' => 294]],
                ['term' => ['MBLB' => $ruleMap1054]]
            ];
            $mustNot = ['term' => ['BLZT' => $this->ruleMap2036]];
            $error = [];
            $content = [];
            $content1 = [];
            $content2 = [];

            $params = $BL01ESServer->clearMust()->queryByMustBatch($must)->queryByMustNot($mustNot)->getParams();
            $res = app('es')->search($params);
            $bl01Data = $BL01ESServer->getDataByEs($res);
            if (!empty($bl01Data[0])) {
                $content1[] = ["status" => 1, "content" => "首次病程记录【" . $bl01Data[0][0]['BLMC'] . "】"];
                $content2[] = ["status" => 1, "content" => "入院时间【" . $item['AAB01'] . "】"];
                $content2[] = ["status" => 1, "content" => "出院时间【" . $item['AAC01'] . "】"];
                //获取签名数据
                /* $blsyParams = $BLSYESServer->clearMust()->queryByMust(['term' => ['BLBH' => $bl01Data[0][0]['BLBH']]])->source(['SYYS'])->getParams();
                $res = app('es')->search($blsyParams);
                $blsyData = $BLSYESServer->getDataByEs($res); */

                $blsyData = EMR_BL_BLSY::query()->where('BLBH', $bl01Data[0][0]['BLBH'])->where('FG_ACTIVE', 1)->get(['SYYS'])->toArray();
                if (!empty($blsyData)) {
                    $syysArr = array_unique(array_column($blsyData, 'SYYS'));
                    $staff = Staff::query()->whereIn('code', $syysArr)->get()->toArray();
                    if (!empty($staff)) {
                        $flag = 0;
                        foreach ($staff as $value) {
                            if (in_array($value['ygjb_text'], $zlfajcqysshMap)) {
                                $insert['zlfajcqyssh_fz'] += 1;
                                $content1[] = ["status" => 1, "content" => "医师签名【" . $value['name'] . "(" . $value['ygjb_text'] . ")】"];
                                $flag = 1;
                                break;
                            }
                        }
                        if ($flag == 0) {
                            $tempArr = [];
                            foreach ($staff as $val) {
                                $tempArr[] = $val['name'] . "(" . $val['ygjb_text'] . ")";
                            }
                            $tempStr = implode('|', $tempArr);
                            $content1[] = ["status" => 0, "content" => "医师签名【" . $tempStr . "】"];
                        }
                    } else {
                        $content1[] = ["status" => 0, "content" => "医师签名【无】"];
                    }
                } else {
                    $content1[] = ["status" => 0, "content" => "医师签名【无】"];
                }
            } else {
                $content1[] = ["status" => 0, "content" => "首次病程记录【无】"];
            }
            $content = array_merge($content1, $content2);
            if (!empty($content)) {
                $status2 = array_unique(array_column($content, 'status'));
                if (!in_array(0, $status2)) {
                    $error[] = ["status" => 1, "content" => $content];
                } else {
                    $error[] = ["status" => 0, "content" => $content];
                }
                $insert['zlfajcqyssh_error'] = !empty($error) ? json_encode($error, 256) : '';
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }
        $this->UpdateIndexCataLog('zlfajcqyssh', 2, time());
    }

    /**
     * 指标：术前非计划再次手术完成疑难病历讨论率
     * @param mixed $data
     * @return void
     */
    public function handleSqfjhzssbltl($data)
    {
        $BL01ESServer = new ElasticsearchService('bl01_202303');
        $SSSQESServer = new ElasticsearchService('sssq_2023');
        $YZBESServer = new ElasticsearchService('yzb_2023');
        $this->UpdateIndexCataLog('sqfjhzssbltlsq', 1, time());
        foreach ($data as $item) {
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['sqfjhzssbltlsq_fz' => 0, 'sqfjhzssbltlsq_fm' => 0, 'sqfjhzssbltlsq_error' => null]);
            if (strtotime($item['AAC01']) < strtotime($item['AAB01']) + 24 * 3600) {
                continue;
            }
            $insert = [
                'zyh' => $item['MED_REC_ID'],
                'sqfjhzssbltlsq_fz' => 0,
                'sqfjhzssbltlsq_fm' => 0,
                'sqfjhzssbltlsq_error' => '',
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($item['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($item['AAC01'])),
                'AAC01' => $item['AAC01']
            ];
            $error = [];
            //分母，获取患者非计划手术
            /* $must = [];
            $must[] = ['term' => ['ZYH' => $item['MED_REC_ID']]];
            $must[] = ['term' => ['FJHZCSS' => '1']];
            $must[] = ['term' => ['ZFBZ' => '0']];

            $params = $SSSQESServer->clearMust()->queryByMustBatch($must)->getParams();
            $res = app('es')->search($params);
            $sssqData = $SSSQESServer->getDataByEs($res); */
            //更换成数据表查询
            $sssqData = SSSQ::query()->where('ZYH', $item['MED_REC_ID'])->where('FJHZCSS', '1')->where('ZFBZ', '0')->get()->toArray();
            if (empty($sssqData) || count($sssqData) == 0) {
                continue;
            }
            foreach ($sssqData as $value) {
                $content = [];
                $insert['sqfjhzssbltlsq_fm'] += 1;
                //分子
                $SQDH = $value['SQDH'];
                $ssmc = $value['NSSMC'] ?? "";
                if (empty($ssmc)) {
                    continue;
                }
                $content[] = ["status" => 1, "content" => "(申请单)手术名称【" . $ssmc . "】(非计划再次手术)"];
                $ssap = SM_SSAP::query()->where('SQDH', $SQDH)->get()->toArray();
                if (empty($ssap)) {
                    continue;
                }

                $SSRQ = $ssap[0]['SSRQ'] ?? "";
                $JSRQ = $ssap[0]['JSRQ'] ?? "";
                if (empty($SSRQ) || empty($JSRQ)) {
                    continue;
                }
                $content[] = ["status" => 1, "content" => "(手麻)手术开始时间【" . $SSRQ . "】"];
                //$content[] = ["status" => 1, "content" => "(手麻)手术结束时间【" . $JSRQ . "】"];

                $kzsjStart = date('Y-m-d H:i:s', strtotime($SSRQ) - 48 * 3600);
                //$kzsjEnd = date('Y-m-d H:i:s', strtotime($JSRQ) + 72 * 3600);
                $kzsjEnd = date('Y-m-d H:i:s', strtotime($SSRQ));
                $must = [
                    ['term' => ['JZHM' => $item['MED_REC_ID']]],
                    ['term' => ['BLLB' => 43]],
                    ['term' => ['MBLB' => $this->ruleMap1045]],
                    ['range' => ['ZXSJ' => ['gte' => $kzsjStart, 'lte' => $kzsjEnd]]]
                ];
                $mustNot = ['term' => ['BLZT' => $this->ruleMap2036]];
                $params = $BL01ESServer->clearMust()->queryByMustBatch($must)->queryByMustNot($mustNot)->getParams();
                $res = app('es')->search($params);
                $bl01Data = $BL01ESServer->getDataByEs($res);
                if (!empty($bl01Data[1])) {
                    $firstTime = $bl01Data[0][0]['ZXSJ'] ?? "";
                    if (!empty($firstTime)) {
                        if (strtotime($firstTime) >= strtotime($kzsjStart) && strtotime($firstTime) <= strtotime($kzsjEnd)) {
                            $insert['sqfjhzssbltlsq_fz'] += 1;
                            $content[] = ["status" => 1, "content" => "疑难病历讨论记录【" . $bl01Data[0][0]['BLMC'] . "】"];
                            $content[] = ["status" => 1, "content" => "首次签名时间【" . $firstTime . "】"];
                        } else if (strtotime($firstTime) < strtotime($kzsjStart)) {
                            $content[] = ["status" => 0, "content" => "疑难病历讨论记录【" . $bl01Data[0][0]['BLMC'] . "】"];
                            $content[] = ["status" => 0, "content" => "首次签名时间【" . $firstTime . "】(提前)"];
                        } else if (strtotime($firstTime) > strtotime($kzsjEnd)) {
                            $content[] = ["status" => 0, "content" => "疑难病历讨论记录【" . $bl01Data[0][0]['BLMC'] . "】"];
                            $content[] = ["status" => 0, "content" => "首次签名时间【" . $firstTime . "】(超时)"];
                        }
                    } else {
                        $content[] = ["status" => 0, "content" => "疑难病历讨论记录【" . $bl01Data[0][0]['BLMC'] . "】"];
                        $content[] = ["status" => 0, "content" => "首次签名时间【无】"];
                    }
                    /* $insert['sqfjhzssbltl_fz'] += 1;
                                foreach ($bl01Data[0] as $va) {
                                    $content[] = ["status" => 1, "content" => "疑难病历讨论记录【" . $va['BLMC'] . "】"];
                                    $content[] = ["status" => 1, "content" => "首次完成时间【" . $va[$this->timeField] . "】(开嘱时间前24小时内)"];
                    } */
                } else {
                    $content[] = ['status' => 0, 'content' => "疑难病历讨论记录【无】"];
                }



                if (!empty($content)) {
                    $status2 = array_unique(array_column($content, 'status'));
                    if (!in_array(0, $status2)) {
                        $error[] = ["status" => 1, "content" => $content];
                    } else {
                        $error[] = ["status" => 0, "content" => $content];
                    }
                }
            }
            if (!empty($error)) {
                $insert['sqfjhzssbltlsq_error'] = !empty($error) ? json_encode($error, 256) : '';
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }
        $this->UpdateIndexCataLog('sqfjhzssbltlsq', 2, time());
    }

    /**
     * 指标：术后非计划再次手术完成疑难病历讨论率
     * @param mixed $data
     * @return void
     */
    public function handleShfjhzssbltl($data)
    {
        $BL01ESServer = new ElasticsearchService('bl01_202303');
        $SSSQESServer = new ElasticsearchService('sssq_2023');
        $YZBESServer = new ElasticsearchService('yzb_2023');
        $this->UpdateIndexCataLog('shfjhzssbltlsh', 1, time());
        foreach ($data as $item) {
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['shfjhzssbltlsh_fz' => 0, 'shfjhzssbltlsh_fm' => 0, 'shfjhzssbltlsh_error' => null]);
            /**
             * 日间病历排除
             */
            if (strtotime($item['AAC01']) - strtotime($item['AAB01']) <= 86400) {
                continue;
            }
            $insert = [
                'zyh' => $item['MED_REC_ID'],
                'shfjhzssbltlsh_fz' => 0,
                'shfjhzssbltlsh_fm' => 0,
                'shfjhzssbltlsh_error' => '',
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($item['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($item['AAC01'])),
                'AAC01' => $item['AAC01']
            ];
            $error = [];
            //分母，获取患者非计划手术
            /* $must = [];
            $must[] = ['term' => ['ZYH' => $item['MED_REC_ID']]];
            $must[] = ['term' => ['FJHZCSS' => '1']];
            $must[] = ['term' => ['ZFBZ' => '0']];

            $params = $SSSQESServer->clearMust()->queryByMustBatch($must)->getParams();
            $res = app('es')->search($params);
            $sssqData = $SSSQESServer->getDataByEs($res); */
            //更换成数据表查询
            $sssqData = SSSQ::query()->where('ZYH', $item['MED_REC_ID'])->where('FJHZCSS', '1')->where('ZFBZ', '0')->get()->toArray();
            if (empty($sssqData) || count($sssqData) == 0) {
                continue;
            }
            foreach ($sssqData as $value) {
                $content = [];
                $insert['shfjhzssbltlsh_fm'] += 1;
                //分子
                $SQDH = $value['SQDH'];
                $ssmc = $value['NSSMC'] ?? "";
                if (empty($ssmc)) {
                    continue;
                }
                $content[] = ["status" => 1, "content" => "(申请单)手术名称【" . $ssmc . "】(非计划再次手术)"];
                $ssap = SM_SSAP::query()->where('SQDH', $SQDH)->get()->toArray();
                if (empty($ssap)) {
                    continue;
                }

                $SSRQ = $ssap[0]['SSRQ'] ?? "";
                $JSRQ = $ssap[0]['JSRQ'] ?? "";
                if (empty($SSRQ) || empty($JSRQ)) {
                    continue;
                }
                //$content[] = ["status" => 1, "content" => "(手麻)手术开始时间【" . $SSRQ . "】"];
                $content[] = ["status" => 1, "content" => "(手麻)手术结束时间【" . $JSRQ . "】"];

                //$kzsjStart = date('Y-m-d H:i:s', strtotime($SSRQ) - 48 * 3600);
                $kzsjStart = date('Y-m-d H:i:s', strtotime($JSRQ));
                $kzsjEnd = date('Y-m-d H:i:s', strtotime($JSRQ) + 72 * 3600);
                $must = [
                    ['term' => ['JZHM' => $item['MED_REC_ID']]],
                    ['term' => ['BLLB' => 43]],
                    ['term' => ['MBLB' => $this->ruleMap1045]],
                    ['range' => ['ZXSJ' => ['gte' => $kzsjStart, 'lte' => $kzsjEnd]]]
                ];
                $mustNot = ['term' => ['BLZT' => $this->ruleMap2036]];
                $params = $BL01ESServer->clearMust()->queryByMustBatch($must)->queryByMustNot($mustNot)->getParams();
                $res = app('es')->search($params);
                $bl01Data = $BL01ESServer->getDataByEs($res);
                if (!empty($bl01Data[1])) {
                    $firstTime = $bl01Data[0][0]['ZXSJ'] ?? "";
                    if (!empty($firstTime)) {
                        if (strtotime($firstTime) >= strtotime($kzsjStart) && strtotime($firstTime) <= strtotime($kzsjEnd)) {
                            $insert['shfjhzssbltlsh_fz'] += 1;
                            $content[] = ["status" => 1, "content" => "疑难病历讨论记录【" . $bl01Data[0][0]['BLMC'] . "】"];
                            $content[] = ["status" => 1, "content" => "首次签名时间【" . $firstTime . "】"];
                        } else if (strtotime($firstTime) < strtotime($kzsjStart)) {
                            $content[] = ["status" => 0, "content" => "疑难病历讨论记录【" . $bl01Data[0][0]['BLMC'] . "】"];
                            $content[] = ["status" => 0, "content" => "首次签名时间【" . $firstTime . "】(提前)"];
                        } else if (strtotime($firstTime) > strtotime($kzsjEnd)) {
                            $content[] = ["status" => 0, "content" => "疑难病历讨论记录【" . $bl01Data[0][0]['BLMC'] . "】"];
                            $content[] = ["status" => 0, "content" => "首次签名时间【" . $firstTime . "】(超时)"];
                        }
                    } else {
                        $content[] = ["status" => 0, "content" => "疑难病历讨论记录【" . $bl01Data[0][0]['BLMC'] . "】"];
                        $content[] = ["status" => 0, "content" => "首次签名时间【无】"];
                    }
                    /* $insert['sqfjhzssbltl_fz'] += 1;
                    foreach ($bl01Data[0] as $va) {
                        $content[] = ["status" => 1, "content" => "疑难病历讨论记录【" . $va['BLMC'] . "】"];
                        $content[] = ["status" => 1, "content" => "首次完成时间【" . $va[$this->timeField] . "】(开嘱时间前24小时内)"];
                    } */
                } else {
                    $content[] = ['status' => 0, 'content' => "疑难病历讨论记录【无】"];
                }



                if (!empty($content)) {
                    $status2 = array_unique(array_column($content, 'status'));
                    if (!in_array(0, $status2)) {
                        $error[] = ["status" => 1, "content" => $content];
                    } else {
                        $error[] = ["status" => 0, "content" => $content];
                    }
                }
            }
            if (!empty($error)) {
                $insert['shfjhzssbltlsh_error'] = !empty($error) ? json_encode($error, 256) : '';
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }
        $this->UpdateIndexCataLog('shfjhzssbltlsh', 2, time());
    }

    /**
     *
     * 指标-临床用血后评估记录率
     * @param mixed $data
     * @return void
     */
    public function handleLcyxhpg($data)
    {
        $BL01ESServer = new ElasticsearchService('bl01_202303');
        // 参照 rule1012：分母以输血系统 ZY_SS 记录为准
        // 病程记录MBLB（输血病程）
        $ruleMap2029 = RuleWordMap::query()->where('id', '=', 2029)->value('keyword');
        $ruleMap2029 = !empty($ruleMap2029) ? $ruleMap2029 : 45;
        //用血后评估2
        $ruleMap2039 = RuleWordMap::query()->where('id', '=', 2039)->value('keyword');
        $ruleMap2039 = !empty($ruleMap2039) ? explode(",", $ruleMap2039) : ["输血有效", "凝血功能", "血红蛋白较前", "血小板数值较前"];
        //用血后24小时（使用配置，默认24小时）
        $ruleMap2052 = RuleWordMap::query()->where('id', '=', 2052)->value('keyword');
        $ruleMap2052 = !empty($ruleMap2052) ? $ruleMap2052 : 24;
        //cfx排除关键词，8094
        $ruleMap8094 = RuleWordMap::query()->where('id', '=', 8094)->value('keyword');
        if (strpos($ruleMap8094, ',') !== false) {
            $excludeKeywords8094 = explode(',', $ruleMap8094);
        } else {
            $excludeKeywords8094 = [$ruleMap8094];
        }

        $this->UpdateIndexCataLog('lcyxhpg', 1, time());

        foreach ($data as $item) {
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['lcyxhpg_fz' => 0, 'lcyxhpg_fm' => 0, 'lcyxhpg_error' => null]);
            $insert = [
                'zyh' => $item['MED_REC_ID'],
                'lcyxhpg_fz' => 0,
                'lcyxhpg_fm' => 0,
                'lcyxhpg_error' => '',
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($item['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($item['AAC01'])),
                'AAC01' => $item['AAC01']
            ];
            $error = [];
            // 分母：按输血系统 ZY_SS 的输血记录数量计算
            $sxList = ZY_SS::query()->where('ZYH', '=', $item['MED_REC_ID'])->get()->toArray();
            if (empty($sxList)) {
                continue;
            }
            foreach ($sxList as $sx) {
                $content = [];
                $jssj = !empty($sx['JSSJ']) ? date('Y-m-d H:i:s', strtotime($sx['JSSJ'])) : '';
                $cfx = isset($sx['CFX']) ? $sx['CFX'] : '';
                $insert['lcyxhpg_fm'] += 1;
                if (!empty($cfx)) {
                    $content[] = ["status" => 1, "content" => "输注品种【" . $cfx . "】"];
                    foreach ($excludeKeywords8094 as $kw) {
                        if (strpos($cfx, $kw) !== false) {
                            //fz+1
                            $insert['lcyxhpg_fz'] += 1;
                            $error[] = ["status" => 1, "content" => $content];
                            continue 2;
                        }
                    }
                } else {
                    continue;
                }

                if (empty($jssj) || $jssj == '1970-01-01 00:00:00' || $jssj == '0000-00-00 00:00:00') {
                    // 无效结束时间，记录并跳过分子判断
                    $content[] = ["status" => 0, "content" => "输血结束时间【无效】"];
                } else {
                    $end = date('Y-m-d H:i:00', strtotime($jssj) + $ruleMap2052 * 3600);
                    // 展示输注品种、输血结束时间

                    $content[] = ["status" => 1, "content" => "输血结束时间【" . $jssj . "】"];
                    // 在结束时间后24小时内查找病程记录
                    $must3 = [];
                    $must3[] = ['term' => ['JZHM' => $item['MED_REC_ID']]];
                    $must3[] = ['term' => ['BLLB' => 294]];
                    $must3[] = ['range' => ['ZXSJ' => ['from' => $jssj, 'to' => $end]]];
                    $params = $BL01ESServer->clearMust()->queryByMustBatch($must3)->queryByMustNot(['term' => ['BLZT' => $this->ruleMap2036]])->getParams();
                    $res = app('es')->search($params);
                    $bl01Data = $BL01ESServer->getDataByEs($res);
                    $found = 0;
                    $errrecord = [];
                    if (!empty($bl01Data[1])) {
                        foreach ($bl01Data[0] as $v) {
                            $hjnr = $v['HJNR'];
                            $hjnr = str_replace(["："], ":", $hjnr);
                            $hjnr = str_replace(["{", "}"], "", $hjnr);
                            foreach ($ruleMap2039 as $kw) {
                                if (strpos($hjnr, $kw) !== false) {
                                    $found = 1;
                                    $insert['lcyxhpg_fz'] += 1;
                                    $content[] = ["status" => 1, "content" => "病程记录【" . $v['BLMC'] . "】"];
                                    $content[] = ["status" => 1, "content" => "输血评估【" . $kw . "】（有）"];
                                    break 2;
                                }
                            }
                            $errrecord[] = $v['BLMC'];
                        }
                        if ($found == 0) {
                            foreach ($errrecord as $v) {
                                $content[] = ["status" => 0, "content" => "病程记录【" . $v . "】"];
                            }
                            $content[] = ["status" => 0, "content" => "输血评估【无】"];
                        }
                    } else {
                        $content[] = ["status" => 0, "content" => "病程记录【无】"];
                    }
                }
                if (!empty($content)) {
                    $status2 = array_unique(array_column($content, 'status'));
                    if (in_array(0, $status2)) {
                        $error[] = ["status" => 0, "content" => $content];
                    } else {
                        $error[] = ["status" => 1, "content" => $content];
                    }
                }
            }
            if (!empty($error)) {
                $insert['lcyxhpg_error'] = !empty($error) ? json_encode($error, 256) : '';
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }
        $this->UpdateIndexCataLog('lcyxhpg', 2, time());
    }

    private function helpLcyxhpg($sxjssj, $zyh, $hjnr, $ruleMap2052)
    {
        $BL01ESServer = new ElasticsearchService('bl01_202303');
        $result = ['fenzi' => 0, 'content' => []];


        //用血后评估复查项目(满足其中之一即可)
        $ruleMap1043 = RuleWordMap::query()->where('id', '=', 1043)->value('keyword');
        $ruleMap1043 = !empty($ruleMap1043) ? explode(",", $ruleMap1043) : ["血小板***/L", "血红蛋白***g/L", "凝血酶原时间***s", "活化部分凝血活酶时间***s", "凝血酶时间***s", "纤维蛋白原***g/L"];
        $ruleMap1043 = str_replace("***", "(.*)", implode(",", $ruleMap1043));
        $ruleMap1043 = str_replace("/", "\/", $ruleMap1043);
        $ruleMap1043 = explode(",", $ruleMap1043);

        //用血后评估2
        $ruleMap2039 = RuleWordMap::query()->where('id', '=', 2039)->value('keyword');
        $ruleMap2039 = !empty($ruleMap2039) ? explode(",", $ruleMap2039) : ["输血有效", "凝血功能", "血红蛋白较前", "血小板数值较前"];

        $sxjssjEnd = date('Y-m-d H:i:00', strtotime($sxjssj) + $ruleMap2052 * 3600);
        //取输血范围内的病程记录
        $must3 = [];
        $must3[] = ['term' => ['JZHM' => $zyh]];
        $must3[] = ['term' => ['BLLB' => $this->ruleMap2030]];
        $must3[] = ['range' => [$this->timeField => ['from' => $sxjssj, 'to' => $sxjssjEnd]]];
        $flag = 0;
        $flag2 = 0;
        $params = $BL01ESServer->clearMust()->queryByMustBatch($must3)->queryByMustNot(['term' => ['BLZT' => $this->ruleMap2036]])->getParams();
        $res = app('es')->search($params);
        $bl01Data = $BL01ESServer->getDataByEs($res);
        if (!empty($bl01Data[1])) {
            foreach ($bl01Data[0] as $v) {
                $HJNR = $hjnr;
                $content[] = ["status" => 1, "content" => "病程记录【" . $v['BLMC'] . "】"];
                $content[] = ["status" => 1, "content" => "首次签名时间【" . $v[$this->timeField] . "】(输血结束时间" . $ruleMap2052 . "小时内)"];
                $HJNR = str_replace(["："], ":", $HJNR);
                $HJNR = str_replace(["{", "}"], "", $HJNR);
                foreach ($ruleMap1043 as $p) {
                    $pattern2 = "/" . $p . "/";
                    if (preg_match($pattern2, $HJNR, $matches2) && !empty($matches2[1])) {
                        $flag = 1;
                        $content[] = ["status" => 1, "content" => "病程记录中存在【" . $matches2[1] . "】"];
                        break;
                    }
                }
                if ($flag == 1) {
                    foreach ($ruleMap2039 as $p) {
                        if (strpos($HJNR, $p) !== false) {
                            $result['fenzi'] += 1;
                            $content[] = ["status" => 1, "content" => "病程记录中存在【" . $p . "】"];
                            $flag2 = 1;
                            break;
                        }
                    }
                    if ($flag2 == 1) {
                        break;
                    }
                } else {
                    $tempStr = str_replace("\/", "/", implode(',', $ruleMap1043));
                    $tempStr = str_replace("(.*)", "***", $tempStr);
                    $content[] = ["status" => 0, "content" => "病程记录中不存在【" . $tempStr . "】任意一种格式"];
                }
            }
            if ($flag2 == 0) {
                $content[] = ["status" => 0, "content" => "病程记录中不存在【" . implode(',', $ruleMap2039) . "】任意一种"];
            }
        } else {
            $content[] = ["status" => 0, "content" => "病程记录【无】(输血结束时间" . $ruleMap2052 . "小时内)"];
        }
        $result['content'] = $content;
        return $result;
    }

    /**
     * 指标-日间手术病历术前讨论及时完成率
     * @param mixed $data
     * @return void
     */
    public function handleRjssblsqtl($data)
    {
        $this->UpdateIndexCataLog('rjssblsqtl', 1, time());
        $carbon = new Carbon();

        // 获取配置
        $ruleMap8016 = RuleWordMap::query()->where('id', '=', 8016)->value('keyword');
        $ruleMap8016 = !empty($ruleMap8016) ? $ruleMap8016 : '82,304';
        if (strpos($ruleMap8016, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8016);
        } else {
            $recordTypes = [$ruleMap8016];
        }

        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ';

        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';

        // 获取排除的手术类别配置
        $ruleMap8047 = RuleWordMap::query()->where('id', '=', 8047)->value('keyword');
        $ruleMap8048 = RuleWordMap::query()->where('id', '=', 8048)->value('keyword');
        if (strpos($ruleMap8047, ',') !== false) {
            $excludeKeywords8047 = explode(',', $ruleMap8047);
        } else {
            $excludeKeywords8047 = !empty($ruleMap8047) ? [$ruleMap8047] : [];
        }
        if (strpos($ruleMap8048, ',') !== false) {
            $excludeKeywords8048 = explode(',', $ruleMap8048);
        } else {
            $excludeKeywords8048 = !empty($ruleMap8048) ? [$ruleMap8048] : [];
        }

        // 获取日间手术目录
        $daySurgeryData = HospitalDaySurgery::query()->get()->toArray();
        // 尝试获取 HOSPITAL_NAME 字段，如果没有则使用 ssmc 字段
        $daySurgeryNames = [];
        if (!empty($daySurgeryData)) {
            foreach ($daySurgeryData as $daySurgery) {
                $name = $daySurgery['ssmc'] ?? '';
                if (!empty($name)) {
                    $daySurgeryNames[] = $name;
                }
            }
        }

        foreach ($data as $item) {
            echo $item['MED_REC_ID'] . PHP_EOL;
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['rjssblsqtl_fz' => 0, 'rjssblsqtl_fm' => 0, 'rjssblsqtl_error' => null]);

            $insert = [
                'zyh' => $item['MED_REC_ID'],
                'rjssblsqtl_fz' => 0,
                'rjssblsqtl_fm' => 0,
                'rjssblsqtl_error' => null,
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => $carbon::parse($item['AAC01'])->format("Y"),
                'AAC01_MONTH' => $carbon::parse($item['AAC01'])->format("m"),
                'AAC01' => $item['AAC01']
            ];

            // 检查住院天数是否<24小时
            $hospitalDays = strtotime($item['AAC01']) - strtotime($item['AAB01']);
            if ($hospitalDays > 24 * 3600) {
                continue; // 住院天数>=24小时，跳过
            }

            // 查询手麻系统的手术记录，按手术开始时间排序
            $ssapData = SM_SSAP::query()
                ->where('ZYH', '=', $item['MED_REC_ID'])
                ->orderBy('SSRQ', 'asc')
                ->get()
                ->toArray();
            if (empty($ssapData)) {
                continue; // 没有手术记录，跳过
            }

            $errorMsg = [];
            $isMultiSurgery = false;
            $previousSurgeryEndTime = '';

            // 处理每个手术记录
            foreach ($ssapData as $surgery) {
                $surgeryStartTime = $surgery['SSRQ'] ?? '';
                $surgeryEndTime = $surgery['JSRQ'] ?? '';
                $surgeryName = $surgery['ICD9_SSCZMC'] ?? '';
                $surgeon = $surgery['SZ'] ?? ''; // 术者

                // 检查必要字段
                if (
                    empty($surgeryStartTime) || empty($surgeryName) ||
                    strpos($surgeryStartTime, '1970-01-01') !== false || $surgeryName == 'NULL'
                ) {
                    continue;
                }

                // 手术类别过滤
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

                // 检查手术开始时间是否有效
                if (empty($surgeryStartTime) || $surgeryStartTime == '1970-01-01 00:00:00' || $surgeryStartTime == '0000-00-00 00:00:00') {
                    continue;
                }

                // 检查手术名称是否在日间手术目录中（精确匹配）
                $isDaySurgery = false;
                foreach ($daySurgeryNames as $daySurgeryName) {
                    if ($surgeryName == $daySurgeryName) {
                        $isDaySurgery = true;
                        break;
                    }
                }

                if (!$isDaySurgery) {
                    // 不是日间手术，但需要更新上一台手术结束时间，用于下一台手术的查询
                    if ($isMultiSurgery && !empty($surgeryEndTime) && $surgeryEndTime != '1970-01-01 00:00:00' && $surgeryEndTime != '0000-00-00 00:00:00') {
                        $previousSurgeryEndTime = $surgeryEndTime;
                    }
                    continue; // 不是日间手术，跳过
                }

                // 获取手术类型（从SSCZ表查询SSLB）
                $surgeryType = '';
                $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->first();
                if (!empty($ssCZ)) {
                    $surgeryType = $ssCZ->SSLB ?? '';
                }

                // 分母：日间手术病历数
                $insert['rjssblsqtl_fm'] += 1;

                $surgeryGroup = [
                    'status' => 0,
                    'content' => []
                ];

                // 添加手术信息
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "（手麻）手术名称【" . $surgeryName . "】"
                ];
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "（手麻）手术类型【" . ($surgeryType ?: '未知') . "】"
                ];
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "（手麻）术者【" . ($surgeon ?: '未知') . "】"
                ];
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "（手麻）手术开始时间【" . $surgeryStartTime . "】"
                ];

                // 查询术前小结及术前讨论结论记录
                // 如果是多台手术，查询上一台手术结束时间到本次手术开始时间之间的病程
                $recordsQuery = EMR_BL_BL01::query()
                    ->where('JZHM', $item['MED_REC_ID'])
                    ->whereIn('MBLB', $recordTypes);

                if ($isMultiSurgery && !empty($previousSurgeryEndTime)) {
                    // 多台手术：查询上一台手术结束时间到本次手术开始时间之间
                    $recordsQuery->where($ruleMap8011, '>=', $previousSurgeryEndTime)
                        ->where($ruleMap8011, '<=', $surgeryStartTime);
                } else {
                    // 第一台手术：查询手术开始时间之前的记录
                    $recordsQuery->where($ruleMap8011, '<=', $surgeryStartTime);
                }

                $records = $recordsQuery->get()->toArray();

                if (empty($records)) {
                    $surgeryGroup['content'][] = [
                        'status' => 0,
                        'content' => "术前讨论【无】"
                    ];
                    $errorMsg[] = $surgeryGroup;
                    // 更新上一台手术结束时间
                    if (!empty($surgeryEndTime) && $surgeryEndTime != '1970-01-01 00:00:00' && $surgeryEndTime != '0000-00-00 00:00:00') {
                        $previousSurgeryEndTime = $surgeryEndTime;
                        $isMultiSurgery = true;
                    }
                    continue;
                }

                // 检查每条记录的签名时间和签名与术者是否一致
                $hasValidRecord = false;
                foreach ($records as $record) {
                    $blmc = $record['BLMC'] ?? '';
                    $blbh = $record['BLBH'] ?? '';
                    $zxsj = $record[$ruleMap8011] ?? ''; // 标题时间
                    $firstBlsyTime = $record[$firstBlsyTimeField] ?? ''; // 首次签名时间

                    // 检查标题时间是否早于手术开始时间
                    if (!empty($zxsj) && strtotime($zxsj) > strtotime($surgeryStartTime)) {
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "术前讨论【" . $blmc . "】标题时间【" . $zxsj . "】（晚于手术开始时间）"
                        ];
                        continue;
                    }

                    // 检查是否有签名
                    if (empty($firstBlsyTime)) {
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "术前讨论【" . $blmc . "】"
                        ];
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "首次签名时间【未签名】"
                        ];
                        continue;
                    }

                    // 检查首次签名时间是否早于手术开始时间
                    if (strtotime($firstBlsyTime) > strtotime($surgeryStartTime)) {
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "术前讨论【" . $blmc . "】"
                        ];
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "首次签名时间【" . $firstBlsyTime . "】（超24小时）"
                        ];
                        continue;
                    }

                    // 检查签名与术者是否一致（签名存在或内容中存在，满足一项即可）
                    $hasSurgeonInContent = false; // 病历内容中包含术者
                    $hasSurgeonInSignature = false; // 签名中包含术者
                    $surgeonInRecord = '';

                    if (!empty($blbh) && !empty($surgeon)) {
                        // 1. 检查病历内容中是否包含术者姓名
                        $blxg = EMR_BL_BLXG::query()->where('BLBH', $blbh)->first();
                        if (!empty($blxg)) {
                            $hjnr = $blxg['HJNR'] ?? '';
                            if (!empty($hjnr) && strpos($hjnr, $surgeon) !== false) {
                                $hasSurgeonInContent = true;
                                $surgeonInRecord = $surgeon;
                            }
                        }

                        // 2. 检查签名中是否包含术者姓名
                        $blsy = EMR_BL_BLSY::query()
                            ->where('BLBH', $blbh)
                            ->where('FG_ACTIVE', 1)
                            ->get()
                            ->toArray();

                        if (!empty($blsy)) {
                            foreach ($blsy as $blsyItem) {
                                if (empty($blsyItem['SYYS'])) {
                                    continue;
                                }
                                $ysname = Staff::query()->where('code', $blsyItem['SYYS'])->value('name') ?? '';
                                if (!empty($ysname) && (strpos($ysname, $surgeon) !== false || strpos($surgeon, $ysname) !== false)) {
                                    $hasSurgeonInSignature = true;
                                    $surgeonInRecord = $ysname;
                                    break;
                                }
                            }
                        }
                    }

                    // 签名存在或内容中存在，满足一项即可
                    if ($hasSurgeonInContent || $hasSurgeonInSignature) {
                        // 标题时间和首次签名时间都符合，且签名或内容中有术者
                        $hasValidRecord = true;
                        $surgeryGroup['status'] = 1;
                        $surgeryGroup['content'][] = [
                            'status' => 1,
                            'content' => "术前讨论【" . $blmc . "】"
                        ];
                        $surgeryGroup['content'][] = [
                            'status' => 1,
                            'content' => "首次签名时间【" . $firstBlsyTime . "】"
                        ];
                        break; // 找到一条有效记录即可
                    } else {
                        // 签名与术者不一致
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "术前讨论【" . $blmc . "】"
                        ];
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "首次签名时间【" . $firstBlsyTime . "】"
                        ];
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "术前讨论中的术者与手麻系统不一致"
                        ];
                    }
                }

                // 如果有有效记录，分子+1
                if ($hasValidRecord) {
                    $insert['rjssblsqtl_fz'] += 1;
                }

                $errorMsg[] = $surgeryGroup;

                // 更新上一台手术结束时间，用于下一台手术的查询
                if (!empty($surgeryEndTime) && $surgeryEndTime != '1970-01-01 00:00:00' && $surgeryEndTime != '0000-00-00 00:00:00') {
                    $previousSurgeryEndTime = $surgeryEndTime;
                    $isMultiSurgery = true;
                }
            }

            if (!empty($errorMsg)) {
                $insert['rjssblsqtl_error'] = json_encode($errorMsg, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        $this->UpdateIndexCataLog('rjssblsqtl', 2, time());
    }

    /**
     * 日间手术病历术前评估及时完成率
     * 这个指标和日间手术病历术前讨论及时完成率逻辑完全一样
     * @param mixed $data
     * @return void
     */
    public function handleRjssblsqpg($data)
    {
        $this->UpdateIndexCataLog('rjssblsqpg', 1, time());
        $carbon = new Carbon();

        // 获取配置
        $ruleMap8016 = RuleWordMap::query()->where('id', '=', 8016)->value('keyword');
        $ruleMap8016 = !empty($ruleMap8016) ? $ruleMap8016 : '82,304';
        if (strpos($ruleMap8016, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8016);
        } else {
            $recordTypes = [$ruleMap8016];
        }

        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ';

        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';

        // 获取排除的手术类别配置
        $ruleMap8047 = RuleWordMap::query()->where('id', '=', 8047)->value('keyword');
        $ruleMap8048 = RuleWordMap::query()->where('id', '=', 8048)->value('keyword');
        if (strpos($ruleMap8047, ',') !== false) {
            $excludeKeywords8047 = explode(',', $ruleMap8047);
        } else {
            $excludeKeywords8047 = !empty($ruleMap8047) ? [$ruleMap8047] : [];
        }
        if (strpos($ruleMap8048, ',') !== false) {
            $excludeKeywords8048 = explode(',', $ruleMap8048);
        } else {
            $excludeKeywords8048 = !empty($ruleMap8048) ? [$ruleMap8048] : [];
        }

        // 获取日间手术目录
        $daySurgeryData = HospitalDaySurgery::query()->get()->toArray();
        // 尝试获取 HOSPITAL_NAME 字段，如果没有则使用 ssmc 字段
        $daySurgeryNames = [];
        if (!empty($daySurgeryData)) {
            foreach ($daySurgeryData as $daySurgery) {
                $name = $daySurgery['ssmc'] ?? '';
                if (!empty($name)) {
                    $daySurgeryNames[] = $name;
                }
            }
        }

        foreach ($data as $item) {
            echo $item['MED_REC_ID'] . PHP_EOL;
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['rjssblsqpg_fz' => 0, 'rjssblsqpg_fm' => 0, 'rjssblsqpg_error' => null]);

            $insert = [
                'zyh' => $item['MED_REC_ID'],
                'rjssblsqpg_fz' => 0,
                'rjssblsqpg_fm' => 0,
                'rjssblsqpg_error' => null,
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => $carbon::parse($item['AAC01'])->format("Y"),
                'AAC01_MONTH' => $carbon::parse($item['AAC01'])->format("m"),
                'AAC01' => $item['AAC01']
            ];

            // 检查住院天数是否<24小时
            $hospitalDays = strtotime($item['AAC01']) - strtotime($item['AAB01']);
            if ($hospitalDays > 24 * 3600) {
                continue; // 住院天数>=24小时，跳过
            }

            // 查询手麻系统的手术记录，按手术开始时间排序
            $ssapData = SM_SSAP::query()
                ->where('ZYH', '=', $item['MED_REC_ID'])
                ->orderBy('SSRQ', 'asc')
                ->get()
                ->toArray();
            if (empty($ssapData)) {
                continue; // 没有手术记录，跳过
            }

            $errorMsg = [];
            $isMultiSurgery = false;
            $previousSurgeryEndTime = '';

            // 处理每个手术记录
            foreach ($ssapData as $surgery) {
                $surgeryStartTime = $surgery['SSRQ'] ?? '';
                $surgeryEndTime = $surgery['JSRQ'] ?? '';
                $surgeryName = $surgery['ICD9_SSCZMC'] ?? '';
                $surgeon = $surgery['SZ'] ?? ''; // 术者

                // 检查必要字段
                if (
                    empty($surgeryStartTime) || empty($surgeryName) ||
                    strpos($surgeryStartTime, '1970-01-01') !== false || $surgeryName == 'NULL'
                ) {
                    continue;
                }

                // 手术类别过滤
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

                // 检查手术开始时间是否有效
                if (empty($surgeryStartTime) || $surgeryStartTime == '1970-01-01 00:00:00' || $surgeryStartTime == '0000-00-00 00:00:00') {
                    continue;
                }

                // 检查手术名称是否在日间手术目录中（精确匹配）
                $isDaySurgery = false;
                foreach ($daySurgeryNames as $daySurgeryName) {
                    if ($surgeryName == $daySurgeryName) {
                        $isDaySurgery = true;
                        break;
                    }
                }

                if (!$isDaySurgery) {
                    // 不是日间手术，但需要更新上一台手术结束时间，用于下一台手术的查询
                    if ($isMultiSurgery && !empty($surgeryEndTime) && $surgeryEndTime != '1970-01-01 00:00:00' && $surgeryEndTime != '0000-00-00 00:00:00') {
                        $previousSurgeryEndTime = $surgeryEndTime;
                    }
                    continue; // 不是日间手术，跳过
                }

                // 获取手术类型（从SSCZ表查询SSLB）
                $surgeryType = '';
                $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->first();
                if (!empty($ssCZ)) {
                    $surgeryType = $ssCZ->SSLB ?? '';
                }

                // 分母：日间手术病历数
                $insert['rjssblsqpg_fm'] += 1;

                $surgeryGroup = [
                    'status' => 0,
                    'content' => []
                ];

                // 添加手术信息
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "（手麻）手术名称【" . $surgeryName . "】"
                ];
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "（手麻）手术类型【" . ($surgeryType ?: '未知') . "】"
                ];
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "（手麻）术者【" . ($surgeon ?: '未知') . "】"
                ];
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "（手麻）手术开始时间【" . $surgeryStartTime . "】"
                ];

                // 查询术前小结及术前讨论结论记录
                // 如果是多台手术，查询上一台手术结束时间到本次手术开始时间之间的病程
                $recordsQuery = EMR_BL_BL01::query()
                    ->where('JZHM', $item['MED_REC_ID'])
                    ->whereIn('MBLB', $recordTypes);

                if ($isMultiSurgery && !empty($previousSurgeryEndTime)) {
                    // 多台手术：查询上一台手术结束时间到本次手术开始时间之间
                    $recordsQuery->where($ruleMap8011, '>=', $previousSurgeryEndTime)
                        ->where($ruleMap8011, '<=', $surgeryStartTime);
                } else {
                    // 第一台手术：查询手术开始时间之前的记录
                    $recordsQuery->where($ruleMap8011, '<=', $surgeryStartTime);
                }

                $records = $recordsQuery->get()->toArray();

                if (empty($records)) {
                    $surgeryGroup['content'][] = [
                        'status' => 0,
                        'content' => "术前讨论【无】"
                    ];
                    $errorMsg[] = $surgeryGroup;
                    // 更新上一台手术结束时间
                    if (!empty($surgeryEndTime) && $surgeryEndTime != '1970-01-01 00:00:00' && $surgeryEndTime != '0000-00-00 00:00:00') {
                        $previousSurgeryEndTime = $surgeryEndTime;
                        $isMultiSurgery = true;
                    }
                    continue;
                }

                // 检查每条记录的签名时间和签名与术者是否一致
                $hasValidRecord = false;
                foreach ($records as $record) {
                    $blmc = $record['BLMC'] ?? '';
                    $blbh = $record['BLBH'] ?? '';
                    $zxsj = $record[$ruleMap8011] ?? ''; // 标题时间
                    $firstBlsyTime = $record[$firstBlsyTimeField] ?? ''; // 首次签名时间

                    // 检查标题时间是否早于手术开始时间
                    if (!empty($zxsj) && strtotime($zxsj) > strtotime($surgeryStartTime)) {
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "术前讨论【" . $blmc . "】标题时间【" . $zxsj . "】（晚于手术开始时间）"
                        ];
                        continue;
                    }

                    // 检查是否有签名
                    if (empty($firstBlsyTime)) {
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "术前讨论【" . $blmc . "】"
                        ];
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "首次签名时间【未签名】"
                        ];
                        continue;
                    }

                    // 检查首次签名时间是否早于手术开始时间
                    if (strtotime($firstBlsyTime) > strtotime($surgeryStartTime)) {
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "术前讨论【" . $blmc . "】"
                        ];
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "首次签名时间【" . $firstBlsyTime . "】（超24小时）"
                        ];
                        continue;
                    }

                    // 检查签名与术者是否一致（签名存在或内容中存在，满足一项即可）
                    $hasSurgeonInContent = false; // 病历内容中包含术者
                    $hasSurgeonInSignature = false; // 签名中包含术者
                    $surgeonInRecord = '';

                    if (!empty($blbh) && !empty($surgeon)) {
                        // 1. 检查病历内容中是否包含术者姓名
                        $blxg = EMR_BL_BLXG::query()->where('BLBH', $blbh)->first();
                        if (!empty($blxg)) {
                            $hjnr = $blxg['HJNR'] ?? '';
                            if (!empty($hjnr) && strpos($hjnr, $surgeon) !== false) {
                                $hasSurgeonInContent = true;
                                $surgeonInRecord = $surgeon;
                            }
                        }

                        // 2. 检查签名中是否包含术者姓名
                        $blsy = EMR_BL_BLSY::query()
                            ->where('BLBH', $blbh)
                            ->where('FG_ACTIVE', 1)
                            ->get()
                            ->toArray();

                        if (!empty($blsy)) {
                            foreach ($blsy as $blsyItem) {
                                if (empty($blsyItem['SYYS'])) {
                                    continue;
                                }
                                $ysname = Staff::query()->where('code', $blsyItem['SYYS'])->value('name') ?? '';
                                if (!empty($ysname) && (strpos($ysname, $surgeon) !== false || strpos($surgeon, $ysname) !== false)) {
                                    $hasSurgeonInSignature = true;
                                    $surgeonInRecord = $ysname;
                                    break;
                                }
                            }
                        }
                    }

                    // 签名存在或内容中存在，满足一项即可
                    if ($hasSurgeonInContent || $hasSurgeonInSignature) {
                        // 标题时间和首次签名时间都符合，且签名或内容中有术者
                        $hasValidRecord = true;
                        $surgeryGroup['status'] = 1;
                        $surgeryGroup['content'][] = [
                            'status' => 1,
                            'content' => "术前讨论【" . $blmc . "】"
                        ];
                        $surgeryGroup['content'][] = [
                            'status' => 1,
                            'content' => "首次签名时间【" . $firstBlsyTime . "】"
                        ];
                        break; // 找到一条有效记录即可
                    } else {
                        // 签名与术者不一致
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "术前讨论【" . $blmc . "】"
                        ];
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "首次签名时间【" . $firstBlsyTime . "】"
                        ];
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "术前讨论中的术者与手麻系统不一致"
                        ];
                    }
                }

                // 如果有有效记录，分子+1
                if ($hasValidRecord) {
                    $insert['rjssblsqpg_fz'] += 1;
                }

                $errorMsg[] = $surgeryGroup;

                // 更新上一台手术结束时间，用于下一台手术的查询
                if (!empty($surgeryEndTime) && $surgeryEndTime != '1970-01-01 00:00:00' && $surgeryEndTime != '0000-00-00 00:00:00') {
                    $previousSurgeryEndTime = $surgeryEndTime;
                    $isMultiSurgery = true;
                }
            }

            if (!empty($errorMsg)) {
                $insert['rjssblsqpg_error'] = json_encode($errorMsg, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        $this->UpdateIndexCataLog('rjssblsqpg', 2, time());
    }

    /**
     * 指标：日间手术病历24小时入出院记录的术前部分及时完成率
     * @param mixed $data
     * @return void
     */
    public function handleRjssblrcyjl24($data)
    {

        $this->UpdateIndexCataLog('rjssblrcyjl24', 1, time());
        $carbon = new Carbon();

        // 获取配置
        $ruleMap8016 = RuleWordMap::query()->where('id', '=', 8016)->value('keyword');
        $ruleMap8016 = !empty($ruleMap8016) ? $ruleMap8016 : '82,304';
        if (strpos($ruleMap8016, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8016);
        } else {
            $recordTypes = [$ruleMap8016];
        }

        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ';

        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';

        // 获取排除的手术类别配置
        $ruleMap8047 = RuleWordMap::query()->where('id', '=', 8047)->value('keyword');
        $ruleMap8048 = RuleWordMap::query()->where('id', '=', 8048)->value('keyword');
        if (strpos($ruleMap8047, ',') !== false) {
            $excludeKeywords8047 = explode(',', $ruleMap8047);
        } else {
            $excludeKeywords8047 = !empty($ruleMap8047) ? [$ruleMap8047] : [];
        }
        if (strpos($ruleMap8048, ',') !== false) {
            $excludeKeywords8048 = explode(',', $ruleMap8048);
        } else {
            $excludeKeywords8048 = !empty($ruleMap8048) ? [$ruleMap8048] : [];
        }

        // 获取日间手术目录
        $daySurgeryData = HospitalDaySurgery::query()->get()->toArray();
        // 尝试获取 HOSPITAL_NAME 字段，如果没有则使用 ssmc 字段
        $daySurgeryNames = [];
        if (!empty($daySurgeryData)) {
            foreach ($daySurgeryData as $daySurgery) {
                $name = $daySurgery['ssmc'] ?? '';
                if (!empty($name)) {
                    $daySurgeryNames[] = $name;
                }
            }
        }

        foreach ($data as $item) {
            echo $item['MED_REC_ID'] . PHP_EOL;
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['rjssblrcyjl24_fz' => 0, 'rjssblrcyjl24_fm' => 0, 'rjssblrcyjl24_error' => null]);

            $insert = [
                'zyh' => $item['MED_REC_ID'],
                'rjssblrcyjl24_fz' => 0,
                'rjssblrcyjl24_fm' => 0,
                'rjssblrcyjl24_error' => null,
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => $carbon::parse($item['AAC01'])->format("Y"),
                'AAC01_MONTH' => $carbon::parse($item['AAC01'])->format("m"),
                'AAC01' => $item['AAC01']
            ];

            // 检查住院天数是否<24小时
            $hospitalDays = strtotime($item['AAC01']) - strtotime($item['AAB01']);
            if ($hospitalDays > 24 * 3600) {
                continue; // 住院天数>=24小时，跳过
            }

            // 查询手麻系统的手术记录，按手术开始时间排序
            $ssapData = SM_SSAP::query()
                ->where('ZYH', '=', $item['MED_REC_ID'])
                ->orderBy('SSRQ', 'asc')
                ->get()
                ->toArray();
            if (empty($ssapData)) {
                continue; // 没有手术记录，跳过
            }

            $errorMsg = [];
            $isMultiSurgery = false;
            $previousSurgeryEndTime = '';

            // 处理每个手术记录
            foreach ($ssapData as $surgery) {
                $surgeryStartTime = $surgery['SSRQ'] ?? '';
                $surgeryEndTime = $surgery['JSRQ'] ?? '';
                $surgeryName = $surgery['ICD9_SSCZMC'] ?? '';
                $surgeon = $surgery['SZ'] ?? ''; // 术者

                // 检查必要字段
                if (
                    empty($surgeryStartTime) || empty($surgeryName) ||
                    strpos($surgeryStartTime, '1970-01-01') !== false || $surgeryName == 'NULL'
                ) {
                    continue;
                }

                // 手术类别过滤
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

                // 检查手术开始时间是否有效
                if (empty($surgeryStartTime) || $surgeryStartTime == '1970-01-01 00:00:00' || $surgeryStartTime == '0000-00-00 00:00:00') {
                    continue;
                }

                // 检查手术名称是否在日间手术目录中（精确匹配）
                $isDaySurgery = false;
                foreach ($daySurgeryNames as $daySurgeryName) {
                    if ($surgeryName == $daySurgeryName) {
                        $isDaySurgery = true;
                        break;
                    }
                }

                if (!$isDaySurgery) {
                    // 不是日间手术，但需要更新上一台手术结束时间，用于下一台手术的查询
                    if ($isMultiSurgery && !empty($surgeryEndTime) && $surgeryEndTime != '1970-01-01 00:00:00' && $surgeryEndTime != '0000-00-00 00:00:00') {
                        $previousSurgeryEndTime = $surgeryEndTime;
                    }
                    continue; // 不是日间手术，跳过
                }

                // 获取手术类型（从SSCZ表查询SSLB）
                $surgeryType = '';
                $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->first();
                if (!empty($ssCZ)) {
                    $surgeryType = $ssCZ->SSLB ?? '';
                }

                // 分母：日间手术病历数
                $insert['rjssblrcyjl24_fm'] += 1;

                $surgeryGroup = [
                    'status' => 0,
                    'content' => []
                ];

                // 添加手术信息
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "（手麻）手术名称【" . $surgeryName . "】"
                ];
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "（手麻）手术类型【" . ($surgeryType ?: '未知') . "】"
                ];
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "（手麻）术者【" . ($surgeon ?: '未知') . "】"
                ];
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "（手麻）手术开始时间【" . $surgeryStartTime . "】"
                ];

                // 查询术前小结及术前讨论结论记录
                // 如果是多台手术，查询上一台手术结束时间到本次手术开始时间之间的病程
                $recordsQuery = EMR_BL_BL01::query()
                    ->where('JZHM', $item['MED_REC_ID'])
                    ->whereIn('MBLB', $recordTypes);

                if ($isMultiSurgery && !empty($previousSurgeryEndTime)) {
                    // 多台手术：查询上一台手术结束时间到本次手术开始时间之间
                    $recordsQuery->where($ruleMap8011, '>=', $previousSurgeryEndTime)
                        ->where($ruleMap8011, '<=', $surgeryStartTime);
                } else {
                    // 第一台手术：查询手术开始时间之前的记录
                    $recordsQuery->where($ruleMap8011, '<=', $surgeryStartTime);
                }

                $records = $recordsQuery->get()->toArray();

                if (empty($records)) {
                    $surgeryGroup['content'][] = [
                        'status' => 0,
                        'content' => "术前讨论【无】"
                    ];
                    $errorMsg[] = $surgeryGroup;
                    // 更新上一台手术结束时间
                    if (!empty($surgeryEndTime) && $surgeryEndTime != '1970-01-01 00:00:00' && $surgeryEndTime != '0000-00-00 00:00:00') {
                        $previousSurgeryEndTime = $surgeryEndTime;
                        $isMultiSurgery = true;
                    }
                    continue;
                }

                // 检查每条记录的签名时间和签名与术者是否一致
                $hasValidRecord = false;
                foreach ($records as $record) {
                    $blmc = $record['BLMC'] ?? '';
                    $blbh = $record['BLBH'] ?? '';
                    $zxsj = $record[$ruleMap8011] ?? ''; // 标题时间
                    $firstBlsyTime = $record[$firstBlsyTimeField] ?? ''; // 首次签名时间

                    // 检查标题时间是否早于手术开始时间
                    if (!empty($zxsj) && strtotime($zxsj) > strtotime($surgeryStartTime)) {
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "术前讨论【" . $blmc . "】标题时间【" . $zxsj . "】（晚于手术开始时间）"
                        ];
                        continue;
                    }

                    // 检查是否有签名
                    if (empty($firstBlsyTime)) {
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "术前讨论【" . $blmc . "】"
                        ];
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "首次签名时间【未签名】"
                        ];
                        continue;
                    }

                    // 检查首次签名时间是否早于手术开始时间
                    if (strtotime($firstBlsyTime) > strtotime($surgeryStartTime)) {
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "术前讨论【" . $blmc . "】"
                        ];
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "首次签名时间【" . $firstBlsyTime . "】（超24小时）"
                        ];
                        continue;
                    }

                    // 检查签名与术者是否一致（签名存在或内容中存在，满足一项即可）
                    $hasSurgeonInContent = false; // 病历内容中包含术者
                    $hasSurgeonInSignature = false; // 签名中包含术者
                    $surgeonInRecord = '';

                    if (!empty($blbh) && !empty($surgeon)) {
                        // 1. 检查病历内容中是否包含术者姓名
                        $blxg = EMR_BL_BLXG::query()->where('BLBH', $blbh)->first();
                        if (!empty($blxg)) {
                            $hjnr = $blxg['HJNR'] ?? '';
                            if (!empty($hjnr) && strpos($hjnr, $surgeon) !== false) {
                                $hasSurgeonInContent = true;
                                $surgeonInRecord = $surgeon;
                            }
                        }

                        // 2. 检查签名中是否包含术者姓名
                        $blsy = EMR_BL_BLSY::query()
                            ->where('BLBH', $blbh)
                            ->where('FG_ACTIVE', 1)
                            ->get()
                            ->toArray();

                        if (!empty($blsy)) {
                            foreach ($blsy as $blsyItem) {
                                if (empty($blsyItem['SYYS'])) {
                                    continue;
                                }
                                $ysname = Staff::query()->where('code', $blsyItem['SYYS'])->value('name') ?? '';
                                if (!empty($ysname) && (strpos($ysname, $surgeon) !== false || strpos($surgeon, $ysname) !== false)) {
                                    $hasSurgeonInSignature = true;
                                    $surgeonInRecord = $ysname;
                                    break;
                                }
                            }
                        }
                    }

                    // 签名存在或内容中存在，满足一项即可
                    if ($hasSurgeonInContent || $hasSurgeonInSignature) {
                        // 标题时间和首次签名时间都符合，且签名或内容中有术者
                        $hasValidRecord = true;
                        $surgeryGroup['status'] = 1;
                        $surgeryGroup['content'][] = [
                            'status' => 1,
                            'content' => "术前讨论【" . $blmc . "】"
                        ];
                        $surgeryGroup['content'][] = [
                            'status' => 1,
                            'content' => "首次签名时间【" . $firstBlsyTime . "】"
                        ];
                        break; // 找到一条有效记录即可
                    } else {
                        // 签名与术者不一致
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "术前讨论【" . $blmc . "】"
                        ];
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "首次签名时间【" . $firstBlsyTime . "】"
                        ];
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "术前讨论中的术者与手麻系统不一致"
                        ];
                    }
                }

                // 如果有有效记录，分子+1
                if ($hasValidRecord) {
                    $insert['rjssblrcyjl24_fz'] += 1;
                }

                $errorMsg[] = $surgeryGroup;

                // 更新上一台手术结束时间，用于下一台手术的查询
                if (!empty($surgeryEndTime) && $surgeryEndTime != '1970-01-01 00:00:00' && $surgeryEndTime != '0000-00-00 00:00:00') {
                    $previousSurgeryEndTime = $surgeryEndTime;
                    $isMultiSurgery = true;
                }
            }

            if (!empty($errorMsg)) {
                $insert['rjssblrcyjl24_error'] = json_encode($errorMsg, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        $this->UpdateIndexCataLog('rjssblrcyjl24', 2, time());
    }

    /**
     * 指标：疑难病历讨论规范开展率
     * @param mixed $data
     * @return void
     */
    public function handleYnbltlgf($data)
    {
        $BL01ESServer = new ElasticsearchService('bl01_202303');
        //疑难病例讨论结论开展规范率截取规则
        // $ruleMap1049 = RuleWordMap::query()->where('id', '=', 1049)->value('keyword');
        // $ruleMap1049 = !empty($ruleMap1049) ? $ruleMap1049 : "在***科主任***的主持下";
        // $ruleMap1049 = str_replace("***", "(.*)", $ruleMap1049);
        //获取科主任数据
        $kzrData = KZR::query()->where('hospital_name', '=', $this->hospitalName)->get(['KZRMC', 'KZRZC'])->toArray();
        $this->UpdateIndexCataLog('ynbltlgf', 1, time());
        foreach ($data as $item) {
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['ynbltlgf_fz' => 0, 'ynbltlgf_fm' => 0, 'ynbltlgf_error' => null]);
            $must = [
                ['term' => ['JZHM' => $item['MED_REC_ID']]],
                ['term' => ['MBLB' => $this->ruleMap1045]]
            ];
            $error = $content = [];
            $mustNot = ['term' => ['BLZT' => $this->ruleMap2036]];
            $params = $BL01ESServer->clearMust()->queryByMustBatch($must)->queryByMustNot($mustNot)->getParams();
            $res = app('es')->search($params);
            $bl01Data = $BL01ESServer->getDataByEs($res);
            if (!empty($bl01Data)) {
                $insert = [
                    'zyh' => $item['MED_REC_ID'],
                    'ynbltlgf_fz' => 0,
                    'ynbltlgf_fm' => 0,
                    'ynbltlgf_error' => '',
                    'AAC11N' => $item['AAC11N'],
                    'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                    'AAC01_YEAR' => date('Y', strtotime($item['AAC01'])),
                    'AAC01_MONTH' => date('m', strtotime($item['AAC01'])),
                    'AAC01' => $item['AAC01']
                ];
                foreach ($bl01Data[0] as $value) {
                    $content = [];
                    $insert['ynbltlgf_fm'] += 1;
                    $content[] = ["status" => 1, "content" => "疑难病例讨论记录【" . $value['BLMC'] . "】"];
                    $HJNR = $value['HJNR'];
                    $HJNR = str_replace('：', ':', $HJNR);
                    $HJNR = str_replace(["{", "}"], '', $HJNR);
                    //去除空格
                    $HJNR = str_replace(' ', '', $HJNR);

                    //"在科主任李国庆主任医师的主持下"，提取"李国庆主任医师"
                    // 尝试多种匹配模式
                    $matches = [];

                    // 模式1: 直接匹配"科主任XXX主持下"（最精确）
                    if (preg_match("/(科|病区)主任([^\s，、。；]{2,20}?)(主持下|的主持下)/u", $HJNR, $temp)) {
                        $matches = $temp;
                    }

                    // 模式2: 在...科主任XXX主持下（限制"科"字前的字符数，避免匹配到"医务科"）
                    if (empty($matches[2]) && preg_match("/在[^科]{0,6}(科|病区)主任([^\s，、。；]{2,20}?)(主持下|的主持下)/u", $HJNR, $temp)) {
                        $matches = $temp;
                    }

                    // 模式3: 匹配顿号或逗号后的科主任
                    if (empty($matches[2]) && preg_match("/[、，](科|病区)主任([^\s，、。；]{2,20}?)(主持下|的主持下)/u", $HJNR, $temp)) {
                        $matches = $temp;
                    }


                    // 确定要检查的科主任信息
                    $kzrInfo = '';
                    if (!empty($matches) && !empty($matches[2])) {
                        $kzrInfo = $matches[2];
                    } elseif (!empty($matches) && !empty($matches[1])) {
                        // 如果是第二种模式（顿号后），使用 matches[2]
                        $kzrInfo = $matches[2];
                    }

                    // 如果上面都没匹配到，尝试更宽泛的匹配（兜底模式）
                    if (empty($kzrInfo)) {
                        // 模式4: 匹配 "在XXX的主持下" 或 "在XXX主持下"（不要求有"科主任"字样）
                        if (preg_match("/在(.{2,20}?)(的)?主持下/u", $HJNR, $matches2)) {
                            $kzrInfo = $matches2[1];
                        }
                    }

                    if (!empty($kzrInfo)) {
                        $content[] = ["status" => 1, "content" => "科主任及职称【" . $kzrInfo . "】"];
                        //找到对应的科主任记录下来即可
                        $flag = false;
                        foreach ($kzrData as $kzr) {
                            //$tempStr = $kzr['KZRMC'] . $kzr['KZRZC'];
                            if (strpos($kzrInfo, $kzr['KZRMC']) !== false && strpos($kzrInfo, $kzr['KZRZC']) !== false) {
                                $insert['ynbltlgf_fz'] += 1;
                                $flag = true;
                                break;
                            } else {
                                if (strpos($kzrInfo, $kzr['KZRMC']) !== false) {
                                    $content[] = ["status" => 0, "content" => "科主任院内职称【" . $kzr['KZRZC'] . "】（病历文书中科主任职称与院内职称不一致）"];
                                    $flag = true;
                                    break;
                                }
                            }
                        }
                        if (!$flag) {
                            $content[] = ["status" => 0, "content" => "科主任主持【科主任名单中未查到该医师】"];
                        }
                    } else {
                        $content[] = ["status" => 0, "content" => "科主任主持【无】"];
                    }
                }
                if (!empty($content)) {
                    $status2 = array_unique(array_column($content, "status"));
                    if (!in_array(0, $status2)) {
                        $error[] = ["status" => 1, "content" => $content];
                    } else {
                        $error[] = ["status" => 0, "content" => $content];
                    }
                }
            }
            if (!empty($error)) {
                $insert['ynbltlgf_error'] = !empty($error) ? json_encode($error, 256) : '';
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }
        $this->UpdateIndexCataLog('ynbltlgf', 2, time());
    }

    /**
     * 指标-抗菌药物使用记录符合率
     * 参考 rule109 逻辑：抗菌药开嘱时间前24小时至开嘱后72小时，病程中搜索抗菌药（抗菌药物别名 或 id2053）
     * @param mixed $data
     * @return void
     */
    public function handleKjywsyjl($data)
    {
        $BL01ESServer = new ElasticsearchService('bl01_202303');

        // 医嘱状态（0新开、1提交、2疑问、3作废、4停嘱、5复核通过）
        $ruleMap2035 = RuleWordMap::query()->where('id', '=', 2035)->value('keyword');
        $ruleMap2035 = !empty($ruleMap2035) ? $ruleMap2035 : 3;

        // 病程记录BLLB
        $ruleMap2030 = RuleWordMap::query()->where('id', '=', 2030)->value('keyword');
        $ruleMap2030 = !empty($ruleMap2030) ? $ruleMap2030 : 294;

        // 获取配置
        // 抗菌药物关键词（id2053）
        $map2053 = RuleWordMap::getFirstById(2053);
        if (empty($map2053)) {
            $map2053 = ["青霉素", "抗生素", "抗菌药"];
        }

        // 病程记录BLLB配置（id8068）
        $map9009 = RuleWordMap::query()->where('id', 8068)->value('keyword');
        if (strpos($map9009, ',') !== false) {
            $map9009 = explode(',', $map9009);
        } else {
            $map9009 = !empty($map9009) ? [$map9009] : [$ruleMap2030];
        }

        // 获取执行时间字段名
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ';

        // 获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';

        $this->UpdateIndexCataLog('kjywsyjl', 1, time());

        foreach ($data as $item) {
            // 删除之前的数据
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['kjywsyjl_fz' => 0, 'kjywsyjl_fm' => 0, 'kjywsyjl_error' => null]);
            var_dump($item['MED_REC_ID']);
            $insert = [
                'zyh' => $item['MED_REC_ID'],
                'kjywsyjl_fz' => 0,
                'kjywsyjl_fm' => 0,
                'kjywsyjl_error' => null,
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($item['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($item['AAC01'])),
                'AAC01' => $item['AAC01']
            ];

            $error = [];

            // 查询抗菌药医嘱
            $yzbData = Yzb::query()
                ->select(['YZMC', 'JLDW', 'SYPC', 'YCJL', 'KZSJ', 'kjyw_name', 'YZBXH', 'YZQX'])
                ->where('ZYH', '=', (string)$item['MED_REC_ID'])
                ->where('YZZT', '!=', $ruleMap2035)
                ->where('PSBZ', '!=', 1)
                ->where('is_has_kjyw', '=', 1)
                ->get()
                ->toArray();

            if (!empty($yzbData)) {
                foreach ($yzbData as $yzb) {
                    // 跳过皮试医嘱
                    if (strpos($yzb['YZMC'], '皮试') !== false) {
                        continue;
                    }

                    $kjywName = $yzb['kjyw_name'];
                    $kzsj = $yzb['KZSJ'];

                    // 计算时间范围：开嘱时间前24小时至开嘱后72小时
                    $startTime = date("Y-m-d H:i:s", strtotime($kzsj) - 24 * 3600);
                    $endTime = date("Y-m-d H:i:s", strtotime($kzsj) + 72 * 3600);

                    // 分母：每条抗菌药医嘱计入
                    $insert['kjywsyjl_fm'] += 1;

                    $content = [];
                    $content[] = ["status" => 1, "content" => "医嘱名称【" . $yzb['YZMC'] . "】"];
                    $content[] = ["status" => 1, "content" => "开嘱时间【" . $kzsj . "】"];

                    // 构建搜索关键词列表（参考 rule109）
                    $shouldList = [];

                    // 1. 添加抗菌药名称本身
                    $shouldList[] = ['match_phrase_prefix' => ['HJNR' => $kjywName]];

                    // 2. 从 MedicinalInfo 表获取抗菌药别名
                    try {
                        $kjyalias = MedicinalInfo::query()->where('name', '=', $kjywName)->get()->toArray();
                        if (!empty($kjyalias)) {
                            foreach ($kjyalias as $alias) {
                                if (!empty($alias['alias'])) {
                                    if (strpos($alias['alias'], ',') !== false) {
                                        $aliasList = explode(',', $alias['alias']);
                                    } else {
                                        $aliasList = [$alias['alias']];
                                    }
                                    foreach ($aliasList as $itemAlias) {
                                        $shouldList[] = ['match_phrase_prefix' => ['HJNR' => trim($itemAlias)]];
                                    }
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // 查询失败，继续处理
                    }

                    // 3. 添加 id2053 配置的关键词
                    foreach ($map2053 as $item2053) {
                        $shouldList[] = ['match_phrase_prefix' => ['HJNR' => $item2053]];
                    }

                    // 构建查询条件
                    $must = [
                        ['term' => ['JZHM' => $item['MED_REC_ID']]],
                        ['terms' => ['BLLB' => $map9009]],
                        ['range' => [$ruleMap8011 => ['from' => $startTime, 'to' => $endTime]]]
                    ];

                    // 查询病程记录
                    $params = $BL01ESServer->clearMust()
                        ->queryByMustBatch($must)
                        ->queryByShouldBatch($shouldList)
                        ->minimumShouldMatch(1)
                        ->getParams();
                    $res = app('es')->search($params);
                    $bl01Data = $BL01ESServer->getDataByEs($res);

                    $hasValidRecord = false;
                    $hasAntibioticDesc = false;
                    $foundRecord = null;
                    $matchedKeyword = ''; // 记录匹配到的关键词
                    $matchedRecord = [];

                    if (!empty($bl01Data[1]) && !empty($bl01Data[0])) {
                        // 过滤会诊记录：如果会诊记录中不包含抗菌药名称，则移除
                        foreach ($bl01Data[0] as $key => $record) {
                            $blmc = $record["BLMC"];
                            $hjnr = $record["HJNR"];
                            if (strpos($blmc, '会诊') !== false) {
                                // 会诊记录必须包含抗菌药名称
                                if (strpos($hjnr, $kjywName) === false) {
                                    unset($bl01Data[0][$key]);
                                }
                            }
                        }
                        // 移除空元素
                        $bl01Data[0] = array_values($bl01Data[0]);

                        if (!empty($bl01Data[0])) {
                            foreach ($bl01Data[0] as $record) {

                                // 找到有效记录
                                $hasValidRecord = true;
                                // 检查病程记录中是否包含抗生素描述
                                $hjnr = $record["HJNR"];
                                if (strpos($hjnr, $kjywName) !== false) {
                                    $hasAntibioticDesc = true;
                                    $matchedKeyword = $kjywName;
                                    $foundRecord = $record;
                                } else {
                                    // 检查是否包含别名或关键词
                                    foreach ($shouldList as $shouldItem) {
                                        if (isset($shouldItem['match_phrase_prefix']['HJNR'])) {
                                            $keyword = $shouldItem['match_phrase_prefix']['HJNR'];
                                            if (strpos($hjnr, $keyword) !== false) {
                                                $hasAntibioticDesc = true;
                                                $matchedKeyword = $keyword;
                                                $foundRecord = $record;
                                                break;
                                            }
                                        }
                                    }
                                }
                                if ($hasAntibioticDesc) {
                                    break;
                                } else {
                                    $matchedRecord[] = $record;
                                }
                            }
                        }
                    }

                    // 输出结果
                    if ($hasValidRecord) {
                        if ($foundRecord) {
                            $content[] = ["status" => 1, "content" => "病程记录【" . $foundRecord['BLMC'] . "】"];
                            $content[] = ["status" => 1, "content" => "抗生素描述【'" . $matchedKeyword . "'】（有）"];
                            // 分子：有病程记录且包含抗生素描述
                            $insert['kjywsyjl_fz'] += 1;
                        } else {
                            if ($matchedRecord) {
                                foreach ($matchedRecord as $key => $value) {
                                    $content[] = ["status" => 0, "content" => "病程记录【" . $value['BLMC'] . "】"];
                                }
                                $content[] = ["status" => 0, "content" => "抗生素描述【无】"];
                            }
                        }
                    } else {
                        $content[] = ["status" => 0, "content" => "病程记录【无】"];
                    }

                    if (!empty($content)) {
                        $status2 = array_unique(array_column($content, 'status'));
                        if (in_array(0, $status2)) {
                            $error[] = ["status" => 0, "content" => $content];
                        } else {
                            $error[] = ["status" => 1, "content" => $content];
                        }
                    }
                }
            }

            if (!empty($error)) {
                $insert['kjywsyjl_error'] = !empty($error) ? json_encode($error, 256) : '';
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        $this->UpdateIndexCataLog('kjywsyjl', 2, time());
    }

    /**
     * 指标：科主任主持死亡讨论率
     * @param mixed $data
     * @return void
     */
    public function handleKzrzcswtll($data)
    {
        $BL01ESServer = new ElasticsearchService('bl01_202303');
        $ruleMap2009 = RuleWordMap::query()->where('id', '=', 2009)->value('keyword');
        $ruleMap2009 = !empty($ruleMap2009) ? $ruleMap2009 : "死亡";
        //医嘱状态   0新开、1提交、2疑问、3作废、4停嘱、5复核通过
        $ruleMap2035 = RuleWordMap::query()->where('id', '=', 2035)->value('keyword');
        $ruleMap2035 = !empty($ruleMap2035) ? $ruleMap2035 : 3;
        //死亡病例讨论结论记录MBLB
        $ruleMap1051 = RuleWordMap::query()->where('id', '=', 1051)->value('keyword');
        $ruleMap1051 = !empty($ruleMap1051) ? $ruleMap1051 : 4302;
        //获取科主任数据
        $kzrData = KZR::query()->where('hospital_name', '=', $this->hospitalName)->get(['KZRMC', 'KZRZC'])->toArray();
        $this->UpdateIndexCataLog('kzrzcswtll', 1, time());
        foreach ($data as $item) {
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['kzrzcswtll_fz' => 0, 'kzrzcswtll_fm' => 0, 'kzrzcswtll_error' => null]);
            $must = [
                ['term' => ['JZHM' => $item['MED_REC_ID']]],
                ['term' => ['MBLB' => $ruleMap1051]]
            ];
            $error = $content = [];
            $mustNot = ['term' => ['BLZT' => $this->ruleMap2036]];
            $params = $BL01ESServer->clearMust()->queryByMustBatch($must)->queryByMustNot($mustNot)->getParams();
            $res = app('es')->search($params);
            $bl01Data = $BL01ESServer->getDataByEs($res);
            if (!empty($bl01Data)) {
                $insert = [
                    'zyh' => $item['MED_REC_ID'],
                    'kzrzcswtll_fz' => 0,
                    'kzrzcswtll_fm' => 0,
                    'kzrzcswtll_error' => '',
                    'AAC11N' => $item['AAC11N'],
                    'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                    'AAC01_YEAR' => date('Y', strtotime($item['AAC01'])),
                    'AAC01_MONTH' => date('m', strtotime($item['AAC01'])),
                    'AAC01' => $item['AAC01']
                ];

                // 查询该患者的医嘱，检查是否包含"死亡"关键字
                $yzb = Yzb::query()->where('ZYH', '=', $item['MED_REC_ID'])->where('YZMC', 'like', '%' . $ruleMap2009 . '%')->get(['ZYH', 'KZSJ', 'YZMC', 'XZJDSJ', 'YZZT'])->toArray();
                $hasSwYzb = false;
                $swYzbName = '';
                if (!empty($yzb)) {
                    $hasSwYzb = true;
                    $swYzbName = $yzb[0]['YZMC'];
                }

                // 分母要求：医嘱包含死亡，且有死亡病例讨论记录
                if ($hasSwYzb) {
                    if (!empty($bl01Data[0][0])) {
                        $bl01 = $bl01Data[0][0];
                        $content = [];
                        $insert['kzrzcswtll_fm'] += 1;
                        $content[] = ["status" => 1, "content" => "医嘱名称【" . $swYzbName . "】"];
                        $content[] = ["status" => 1, "content" => "死亡病例讨论记录【" . $bl01['BLMC'] . "】"];
                        $HJNR = $bl01['HJNR'];
                        $HJNR = str_replace('：', ':', $HJNR);
                        $HJNR = str_replace(["{", "}"], '', $HJNR);
                        //去除空格
                        $HJNR = str_replace(' ', '', $HJNR);

                        //"在科主任李国庆主任医师的主持下"，提取"李国庆主任医师"
                        // 尝试多种匹配模式
                        $matches = [];

                        // 模式1: 直接匹配"科主任XXX主持下"（最精确）
                        if (preg_match("/(科|病区)主任([^\s，、。；]{2,20}?)(主持下|的主持下)/u", $HJNR, $temp)) {
                            $matches = $temp;
                        }

                        // 模式2: 在...科主任XXX主持下（限制"科"字前的字符数，避免匹配到"医务科"）
                        if (empty($matches[2]) && preg_match("/在[^科]{0,6}(科|病区)主任([^\s，、。；]{2,20}?)(主持下|的主持下)/u", $HJNR, $temp)) {
                            $matches = $temp;
                        }

                        // 模式3: 匹配顿号或逗号后的科主任
                        if (empty($matches[2]) && preg_match("/[、，](科|病区)主任([^\s，、。；]{2,20}?)(主持下|的主持下)/u", $HJNR, $temp)) {
                            $matches = $temp;
                        }

                        // 确定要检查的科主任信息
                        $kzrInfo = '';
                        if (!empty($matches) && !empty($matches[2])) {
                            $kzrInfo = $matches[2];
                        } elseif (!empty($matches) && !empty($matches[1])) {
                            // 如果是第二种模式（顿号后），使用 matches[2]
                            $kzrInfo = $matches[2];
                        }

                        // 如果上面都没匹配到，尝试更宽泛的匹配（兜底模式）
                        if (empty($kzrInfo)) {
                            // 模式4: 匹配 "在XXX的主持下" 或 "在XXX主持下"（不要求有"科主任"字样）
                            if (preg_match("/在(.{2,20}?)(的)?主持下/u", $HJNR, $matches2)) {
                                $kzrInfo = $matches2[1];
                            }
                        }

                        if (!empty($kzrInfo)) {
                            $content[] = ["status" => 1, "content" => "科主任及职称【" . $kzrInfo . "】"];
                            //找到对应的科主任记录下来即可
                            $flag = false;
                            foreach ($kzrData as $kzr) {
                                //$tempStr = $kzr['KZRMC'] . $kzr['KZRZC'];
                                if (strpos($kzrInfo, $kzr['KZRMC']) !== false && strpos($kzrInfo, $kzr['KZRZC']) !== false) {
                                    $insert['kzrzcswtll_fz'] += 1;
                                    $flag = true;
                                    break;
                                } else {
                                    if (strpos($kzrInfo, $kzr['KZRMC']) !== false) {
                                        $content[] = ["status" => 0, "content" => "科主任院内职称【" . $kzr['KZRZC'] . "】（病历文书中科主任职称与院内职称不一致）"];
                                        $flag = true;
                                        break;
                                    }
                                }
                            }
                            if (!$flag) {
                                $content[] = ["status" => 0, "content" => "科主任主持【科主任名单中未查到该医师】"];
                            }
                        } else {
                            $content[] = ["status" => 0, "content" => "科主任主持【无】"];
                        }
                    }
                    if (!empty($content)) {
                        $status2 = array_unique(array_column($content, "status"));
                        if (!in_array(0, $status2)) {
                            $error[] = ["status" => 1, "content" => $content];
                        } else {
                            $error[] = ["status" => 0, "content" => $content];
                        }
                    }
                }
            }
            if (!empty($error)) {
                $insert['kzrzcswtll_error'] = !empty($error) ? json_encode($error, 256) : '';
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }
        $this->UpdateIndexCataLog('kzrzcswtll', 2, time());
    }

    /**
     * 危急值记录符合率
     * @param mixed $data
     * @return void
     */
    public function handleWjzjlfhl($data)
    {
        $this->UpdateIndexCataLog('wjzjlfhl', 1, time());
        $carbon = new Carbon();

        // 获取危急值记录相关配置
        // 获取病程记录类型
        $ruleMap8010 = RuleWordMap::query()->where('id', '=', 8010)->value('keyword');
        $ruleMap8010 = !empty($ruleMap8010) ? $ruleMap8010 : '294';
        if (strpos($ruleMap8010, ',') !== false) {
            $blTypes = explode(',', $ruleMap8010);
        } else {
            $blTypes = [$ruleMap8010];
        }

        // 获取危急值记录的时间字段
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ';

        // 获取危急值记录的名称关键词
        $ruleMap8030 = RuleWordMap::query()->where('id', '=', 8030)->value('keyword');
        $ruleMap8030 = !empty($ruleMap8030) ? $ruleMap8030 : '危急值记录';

        // 获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';

        // 获取处置医嘱关键词
        $ruleMap8032 = RuleWordMap::query()->where('id', '=', 8032)->value('keyword');
        $ruleMap8032 = !empty($ruleMap8032) ? $ruleMap8032 : '处置';
        if (strpos($ruleMap8032, ',') !== false) {
            $disposalKeywords = explode(',', $ruleMap8032);
        } else {
            $disposalKeywords = [$ruleMap8032];
        }

        foreach ($data as $item) {
            echo $item['MED_REC_ID'] . PHP_EOL;
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['wjzjlfhl_fz' => 0, 'wjzjlfhl_fm' => 0, 'wjzjlfhl_error' => null]);

            $insert = [
                'zyh' => $item['MED_REC_ID'],
                'wjzjlfhl_fz' => 0,
                'wjzjlfhl_fm' => 0,
                'wjzjlfhl_error' => null,
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => $carbon::parse($item['AAC01'])->format("Y"),
                'AAC01_MONTH' => $carbon::parse($item['AAC01'])->format("m"),
                'AAC01' => $item['AAC01']
            ];

            // 获取危急值数据，按照WJZSJ去重（一次危急值记录算1个分母）
            try {
                $wjzData = WJZ::query()->where('ZYH', '=', $item['MED_REC_ID'])->groupBy('WJZSJ')->get(['WJZSJ', 'WJZNR'])->toArray();
                if (empty($wjzData)) {
                    continue; // 没有危急值数据，跳过
                }
            } catch (\Exception $e) {
                Log::error("handleWjzjlfhl-{$item['MED_REC_ID']}-error: " . $e->getMessage());
                continue;
            }

            $errorMsg = [];

            // 处理每条危急值记录
            foreach ($wjzData as $wjz) {
                $wjzsj = isset($wjz['WJZSJ']) ? $wjz['WJZSJ'] : '';

                // 检查危急值时间是否有效
                if (empty($wjzsj) || $wjzsj == '1970-01-01 00:00:00' || $wjzsj == '0000-00-00 00:00:00') {
                    continue;
                }

                // 根据wjzsj重新获取危急值内容，多个危急值内容用逗号分割
                //$wjznrList = WJZ::query()->where('WJZSJ', '=', $wjzsj)->get(['WJZNR'])->toArray();
                $wjznr_str = $wjz['WJZNR'] ?? '';


                // 分母：一次危急值记录算1个分母
                $insert['wjzjlfhl_fm'] += 1;

                $wjzGroup = [
                    'status' => 0,
                    'content' => []
                ];

                // 添加危急值信息
                $wjzGroup['content'][] = [
                    'status' => 1,
                    'content' => "危急值内容【" . $wjznr_str . "】"
                ];
                $wjzGroup['content'][] = [
                    'status' => 1,
                    'content' => "危急值发送时间【" . $wjzsj . "】"
                ];

                // 计算6小时期限
                $sixHoursLimit = date('Y-m-d H:i:s', strtotime($wjzsj) + 6 * 3600);

                // 查询危急值记录（6小时内）
                try {
                    $bl01Data = EMR_BL_BL01::query()
                        ->where('JZHM', $item['MED_REC_ID'])
                        ->whereIn('BLLB', $blTypes)
                        ->where('BLMC', 'like', '%' . $ruleMap8030 . '%')
                        ->where($ruleMap8011, '>=', date('Y-m-d H:i', strtotime($wjzsj)))
                        ->where($ruleMap8011, '<=', $sixHoursLimit)
                        ->where('BLZT', '<>', $this->ruleMap2036)
                        ->orderBy($ruleMap8011, 'asc')
                        ->get()
                        ->toArray();
                } catch (\Exception $e) {
                    Log::error("handleWjzjlfhl-{$item['MED_REC_ID']}-error: " . $e->getMessage());
                    continue;
                }

                if (!empty($bl01Data)) {
                    // 取第一条记录
                    $record = $bl01Data[0];
                    $blbh = $record['BLBH'] ?? '';
                    $blmc = $record['BLMC'] ?? '';
                    $zxsj = $record[$ruleMap8011] ?? ''; // 标题时间
                    $firstBlsyTime = $record[$firstBlsyTimeField] ?? ''; // 首次签名时间


                    // 检查首次签名时间是否在6小时内
                    //$signTimeValid = !empty($firstBlsyTime) && strtotime($firstBlsyTime) <= strtotime($sixHoursLimit);

                    $wjzGroup['content'][] = [
                        'status' => 1,
                        'content' => "危急值记录【" . $blmc . "】"
                    ];

                    if (strtotime($firstBlsyTime) <= strtotime($sixHoursLimit) && strtotime($firstBlsyTime) >= strtotime($wjzsj)) {
                        $insert['wjzjlfhl_fz'] += 1;
                        $wjzGroup['status'] = 1;
                        $wjzGroup['content'][] = [
                            'status' => 1,
                            'content' => "首次签名时间【" . $firstBlsyTime . "】"
                        ];
                    } else if (strtotime($firstBlsyTime) > strtotime($sixHoursLimit)) {
                        $wjzGroup['content'][] = [
                            'status' => 0,
                            'content' => "首次签名时间【" . $firstBlsyTime . "】（超6小时）"
                        ];
                    } else if (strtotime($firstBlsyTime) < strtotime($wjzsj)) {
                        $wjzGroup['content'][] = [
                            'status' => 0,
                            'content' => "首次签名时间【" . $firstBlsyTime . "】（提前创建）"
                        ];
                    } else {
                        $wjzGroup['content'][] = [
                            'status' => 0,
                            'content' => "首次签名时间【" . $firstBlsyTime . "】（无效）"
                        ];
                    }
                } else {
                    // 没有危急值记录
                    $wjzGroup['content'][] = [
                        'status' => 0,
                        'content' => "危急值记录【无】"
                    ];
                }

                $errorMsg[] = $wjzGroup;
            }

            if (!empty($errorMsg)) {
                $insert['wjzjlfhl_error'] = json_encode($errorMsg, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        $this->UpdateIndexCataLog('wjzjlfhl', 2, time());
    }

    /**
     * 入院记录24小时完成率
     * @param mixed $data
     * @return void
     */
    public function handleRyjl24wcl($data)
    {
        $this->UpdateIndexCataLog('ryjlxswcl24', 1, time());
        $carbon = new Carbon();

        // 获取配置
        $ruleMap2001 = RuleWordMap::query()->where('id', '=', 2001)->first();
        $ruleMap2001 = !empty($ruleMap2001) ? $ruleMap2001->toArray() : ['keyword' => 'CJSJ'];
        $timeField = $ruleMap2001['keyword'] ?? 'CJSJ';

        $ruleMap2007 = RuleWordMap::query()->where('id', '=', 2007)->first();
        $ruleMap2007 = !empty($ruleMap2007) ? $ruleMap2007->toArray() : ['keyword' => '292'];
        $mblb = $ruleMap2007['keyword'] ?? '292';

        foreach ($data as $item) {
            echo $item['MED_REC_ID'] . PHP_EOL;
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['ryjlxswcl24_fz' => 0, 'ryjlxswcl24_fm' => 0, 'ryjlxswcl24_error' => null]);

            $insert = [
                'zyh' => $item['MED_REC_ID'],
                'ryjlxswcl24_fz' => 0,
                'ryjlxswcl24_fm' => 0,
                'ryjlxswcl24_error' => null,
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => $carbon::parse($item['AAC01'])->format("Y"),
                'AAC01_MONTH' => $carbon::parse($item['AAC01'])->format("m"),
                'AAC01' => $item['AAC01']
            ];

            /**
             * 日间病历排除
             */
            if (strtotime($item['AAC01']) - strtotime($item['AAB01']) <= 86400) {
                continue;
            }

            $errorContent = [];
            $errorContent[] = ["status" => 1, "content" => "入院时间【" . ($item['AAB01'] ?? '') . "】"];

            // 查询入院记录
            $bl01 = EMR_BL_BL01::query()
                ->where('JZHM', '=', $item['MED_REC_ID'])
                ->where('MBLB', '=', $mblb)
                ->get([$timeField, 'BLMC'])
                ->toArray();

            // 如果入院记录不存在 并且 存在24小时入院记录或24小时死亡记录 跳过
            /* if (empty($bl01)) {
                $has24HourRecord = EMR_BL_BL01::query()
                    ->where('JZHM', '=', $item['MED_REC_ID'])
                    ->where(function ($query) {
                        $query->where('MBLB', 306)->orWhere('MBLB', 21);
                    })
                    ->get([$timeField, 'BLMC'])
                    ->toArray();

                if (!empty($has24HourRecord)) {
                    continue; // 有24小时入院记录或24小时死亡记录，跳过
                }
            } */

            // 分母：住院天数大于等于1天的患者
            $insert['ryjlxswcl24_fm'] = 1;

            if (empty($bl01)) {
                $errorContent[] = ["status" => 1, "content" => "住院天数【" . ($item['AAC04'] ?? '') . "】"];
                $errorContent[] = ["status" => 0, "content" => "入院记录【无】"];
            } else {
                $tmpTime = $bl01[0][$timeField] ?? '';
                $blmc = $bl01[0]['BLMC'] ?? '';
                $errorContent[] = ["status" => 1, "content" => "住院天数【" . ($item['AAC04'] ?? '') . "】"];
                $errorContent[] = ["status" => 1, "content" => "入院记录【" . $blmc . "】"];

                if (empty($tmpTime) || $tmpTime == '1970-01-01 00:00:00' || $tmpTime == '0000-00-00 00:00:00') {
                    $errorContent[] = ["status" => 0, "content" => "首次签名时间【无效】"];
                } elseif (strtotime($tmpTime) < strtotime($item['AAB01'])) {
                    $errorContent[] = ["status" => 0, "content" => "首次签名时间【" . $tmpTime . "】（提前创建）"];
                } elseif (strtotime($tmpTime) > strtotime($item['AAB01']) + 24 * 3600) {
                    $errorContent[] = ["status" => 0, "content" => "首次签名时间【" . $tmpTime . "】（超24小时）"];
                } else {
                    // 24小时内完成
                    $insert['ryjlxswcl24_fz'] = 1;
                    $errorContent[] = ["status" => 1, "content" => "首次签名时间【" . $tmpTime . "】（24小时内）"];
                }
            }

            if (!empty($errorContent)) {
                $insert['ryjlxswcl24_error'] = json_encode([["status" => $insert['ryjlxswcl24_fz'], "content" => $errorContent]], JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        $this->UpdateIndexCataLog('ryjlxswcl24', 2, time());
    }


    /**
     * 入院记录24小时完成率
     * @param mixed $data
     * @return void
     */
    public function handlescbc8wcl($data)
    {
        $this->UpdateIndexCataLog('scbcjlwc8', 1, time());
        $carbon = new Carbon();

        // 获取配置
        $ruleMap2001 = RuleWordMap::query()->where('id', '=', 2001)->first();
        $ruleMap2001 = !empty($ruleMap2001) ? $ruleMap2001->toArray() : ['keyword' => 'CJSJ'];
        $timeField = $ruleMap2001['keyword'] ?? 'CJSJ';


        $mblb = '295';

        foreach ($data as $item) {
            echo $item['MED_REC_ID'] . PHP_EOL;
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['scbcjlwc8_fz' => 0, 'scbcjlwc8_fm' => 0, 'scbcjlwc8_error' => null]);

            $insert = [
                'zyh' => $item['MED_REC_ID'],
                'scbcjlwc8_fz' => 0,
                'scbcjlwc8_fm' => 0,
                'scbcjlwc8_error' => null,
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => $carbon::parse($item['AAC01'])->format("Y"),
                'AAC01_MONTH' => $carbon::parse($item['AAC01'])->format("m"),
                'AAC01' => $item['AAC01']
            ];

            /**
             * 日间病历排除
             */
            if (strtotime($item['AAC01']) - strtotime($item['AAB01']) <= 86400) {
                continue;
            }

            $errorContent = [];
            $errorContent[] = ["status" => 1, "content" => "入院时间【" . ($item['AAB01'] ?? '') . "】"];

            // 查询首次病程记录
            $bl01 = EMR_BL_BL01::query()
                ->where('JZHM', '=', $item['MED_REC_ID'])
                ->where('MBLB', '=', $mblb)
                ->get([$timeField, 'BLMC'])
                ->toArray();


            // 分母：住院天数大于等于24小时的患者
            $insert['scbcjlwc8_fm'] = 1;

            if (empty($bl01)) {
                $errorContent[] = ["status" => 1, "content" => "住院天数【" . ($item['AAC04'] ?? '') . "】"];
                $errorContent[] = ["status" => 0, "content" => "首次病程记录【无】"];
            } else {
                $tmpTime = $bl01[0][$timeField] ?? '';
                $blmc = $bl01[0]['BLMC'] ?? '';
                $errorContent[] = ["status" => 1, "content" => "住院天数【" . ($item['AAC04'] ?? '') . "】"];
                $errorContent[] = ["status" => 1, "content" => "首次病程记录【" . $blmc . "】"];

                if (empty($tmpTime) || $tmpTime == '1970-01-01 00:00:00' || $tmpTime == '0000-00-00 00:00:00') {
                    $errorContent[] = ["status" => 0, "content" => "首次签名时间【无效】"];
                } elseif (strtotime($tmpTime) < strtotime($item['AAB01'])) {
                    $errorContent[] = ["status" => 0, "content" => "首次签名时间【" . $tmpTime . "】（提前创建）"];
                } elseif (strtotime($tmpTime) > strtotime($item['AAB01']) + 24 * 3600) {
                    $errorContent[] = ["status" => 0, "content" => "首次签名时间【" . $tmpTime . "】（超24小时）"];
                } else {
                    // 24小时内完成
                    $insert['scbcjlwc8_fz'] = 1;
                    $errorContent[] = ["status" => 1, "content" => "首次签名时间【" . $tmpTime . "】（24小时内）"];
                }
            }

            if (!empty($errorContent)) {
                $insert['scbcjlwc8_error'] = json_encode([["status" => $insert['scbcjlwc8_fz'], "content" => $errorContent]], JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        $this->UpdateIndexCataLog('scbcjlwc8', 2, time());
    }

    /**
     * 手术记录24小时完成率
     * @param mixed $data
     * @return void
     */
    public function handleSsjlwc24($data)
    {
        $this->UpdateIndexCataLog('ssjlwc24', 1, time());
        $carbon = new Carbon();

        // 获取配置
        $ruleMap8019 = RuleWordMap::query()->where('id', '=', 8019)->value('keyword');
        $ruleMap8019 = !empty($ruleMap8019) ? $ruleMap8019 : '306';
        if (strpos($ruleMap8019, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8019);
        } else {
            $recordTypes = [$ruleMap8019];
        }

        $ruleMap8021 = RuleWordMap::query()->where('id', '=', 8021)->value('keyword');
        $ruleMap8021 = !empty($ruleMap8021) ? $ruleMap8021 : '303';

        $ruleMap8020 = RuleWordMap::query()->where('id', '=', 8020)->value('keyword');
        $ruleMap8020 = !empty($ruleMap8020) ? $ruleMap8020 : '手术记录';

        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ';

        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';

        // 获取排除的手术类别配置
        $ruleMap8047 = RuleWordMap::query()->where('id', '=', 8047)->value('keyword');
        $ruleMap8048 = RuleWordMap::query()->where('id', '=', 8048)->value('keyword');
        if (strpos($ruleMap8047, ',') !== false) {
            $excludeKeywords8047 = explode(',', $ruleMap8047);
        } else {
            $excludeKeywords8047 = !empty($ruleMap8047) ? [$ruleMap8047] : [];
        }
        if (strpos($ruleMap8048, ',') !== false) {
            $excludeKeywords8048 = explode(',', $ruleMap8048);
        } else {
            $excludeKeywords8048 = !empty($ruleMap8048) ? [$ruleMap8048] : [];
        }

        foreach ($data as $item) {
            echo $item['MED_REC_ID'] . PHP_EOL;
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['ssjlwc24_fz' => 0, 'ssjlwc24_fm' => 0, 'ssjlwc24_error' => null]);

            $insert = [
                'zyh' => $item['MED_REC_ID'],
                'ssjlwc24_fz' => 0,
                'ssjlwc24_fm' => 0,
                'ssjlwc24_error' => null,
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => $carbon::parse($item['AAC01'])->format("Y"),
                'AAC01_MONTH' => $carbon::parse($item['AAC01'])->format("m"),
                'AAC01' => $item['AAC01']
            ];

            // 查询手麻系统的手术记录
            $ssapData = SM_SSAP::query()->where('ZYH', '=', $item['MED_REC_ID'])->get()->toArray();
            if (empty($ssapData)) {
                continue; // 没有手术记录，跳过
            }

            $errorMsg = [];

            // 处理每个手术记录
            foreach ($ssapData as $surgery) {
                $surgeryEndTime = $surgery['JSRQ'] ?? '';
                $surgeryName = $surgery['ICD9_SSCZMC'] ?? '';
                $surgeon = $surgery['SZ'] ?? ''; // 术者

                // 检查必要字段
                if (
                    empty($surgery['SSRQ']) || empty($surgeryName) || empty($surgeryEndTime) ||
                    strpos($surgeryEndTime, '1970-01-01') !== false || $surgeryName == 'NULL'
                ) {
                    continue;
                }

                // 手术类别过滤
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

                // 检查手术结束时间是否有效
                if (empty($surgeryEndTime) || $surgeryEndTime == '1970-01-01 00:00:00' || $surgeryEndTime == '0000-00-00 00:00:00') {
                    continue;
                }

                // 计算手术结束后24小时的时间点
                $oneDayAfterSurgery = date('Y-m-d H:i:s', strtotime($surgeryEndTime) + 24 * 3600);

                // 获取当前时间，如果当前时间小于手术结束时间+24小时就跳过
                $currentDate = Carbon::now()->toDateTimeString();
                if (strtotime($currentDate) < strtotime($oneDayAfterSurgery)) {
                    continue;
                }

                // 获取手术类型（从SSCZ表查询SSLB）
                $surgeryType = '';
                $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->first();
                if (!empty($ssCZ)) {
                    $surgeryType = $ssCZ->SSLB ?? '';
                }

                // 分母：所有手术的总数
                $insert['ssjlwc24_fm'] += 1;

                $surgeryGroup = [
                    'status' => 0,
                    'content' => []
                ];

                // 添加手术信息
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "（手麻）手术名称【" . $surgeryName . "】"
                ];
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "（手麻）术者【" . $surgeon . "】"
                ];
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "（手麻）手术类型【" . ($surgeryType ?: '未知') . "】"
                ];
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "（手麻）手术结束时间【" . $surgeryEndTime . "】"
                ];

                // 查询手术记录
                $records = null;
                $recordsmblb = EMR_BL_BL01::query()
                    ->where('JZHM', $item['MED_REC_ID'])
                    ->whereIn('MBLB', $recordTypes)
                    ->where($ruleMap8011, '>=', date('Y-m-d H:i', strtotime($surgeryEndTime)))
                    ->where($ruleMap8011, '<=', $oneDayAfterSurgery)
                    ->get()
                    ->toArray();

                if (!empty($recordsmblb)) {
                    $records = $recordsmblb;
                } else {
                    $recordsbllb = EMR_BL_BL01::query()
                        ->where('JZHM', $item['MED_REC_ID'])
                        ->where('BLLB', $ruleMap8021)
                        ->where('BLMC', 'like', '%' . $ruleMap8020 . '%')
                        ->where($ruleMap8011, '>=', date('Y-m-d H:i', strtotime($surgeryEndTime)))
                        ->where($ruleMap8011, '<=', $oneDayAfterSurgery)
                        ->get()
                        ->toArray();
                    $records = $recordsbllb;
                }

                // 如果没有找到记录
                if (empty($records)) {
                    $surgeryGroup['content'][] = [
                        'status' => 0,
                        'content' => "手术记录【无】"
                    ];
                    $errorMsg[] = $surgeryGroup;
                    continue;
                }

                // 检查每条记录的签名时间和签名与术者是否一致
                $hasValidRecord = false;
                foreach ($records as $record) {
                    $blmc = $record['BLMC'] ?? '';
                    $blbh = $record['BLBH'] ?? '';
                    $firstBlsyTime = $record[$firstBlsyTimeField] ?? '';

                    // 检查是否有签名
                    if (empty($firstBlsyTime)) {
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "手术记录【" . $blmc . "】"
                        ];
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "首次签名时间【未签名】"
                        ];
                        continue;
                    }

                    // 检查签名时间是否在手术后24小时内
                    if (strtotime($firstBlsyTime) > strtotime($oneDayAfterSurgery)) {
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "手术记录【" . $blmc . "】"
                        ];
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "首次签名时间【" . $firstBlsyTime . "】（超24小时）"
                        ];
                        continue;
                    }

                    // 检查签名与术者是否一致
                    $hasSurgeonSignature = false;
                    if (!empty($blbh) && !empty($surgeon)) {
                        $blsy = EMR_BL_BLSY::query()
                            ->where('BLBH', $blbh)
                            ->where('FG_ACTIVE', 1)
                            ->get()
                            ->toArray();

                        if (!empty($blsy)) {
                            foreach ($blsy as $blsyItem) {
                                if (empty($blsyItem['SYYS'])) {
                                    continue;
                                }
                                $ysname = Staff::query()->where('code', $blsyItem['SYYS'])->value('name') ?? '';
                                if (!empty($ysname) && (strpos($ysname, $surgeon) !== false || strpos($surgeon, $ysname) !== false)) {
                                    $hasSurgeonSignature = true;
                                    break;
                                }
                            }
                        }
                    }

                    if ($hasSurgeonSignature) {
                        // 签名时间在24小时内且签名与术者一致
                        $hasValidRecord = true;
                        $surgeryGroup['status'] = 1;
                        $surgeryGroup['content'][] = [
                            'status' => 1,
                            'content' => "手术记录【" . $blmc . "】"
                        ];
                        $surgeryGroup['content'][] = [
                            'status' => 1,
                            'content' => "首次签名时间【" . $firstBlsyTime . "】"
                        ];
                        break; // 找到一条有效记录即可
                    } else {
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "手术记录【" . $blmc . "】"
                        ];
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "首次签名时间【" . $firstBlsyTime . "】（无术者签名）"
                        ];
                    }
                }

                // 如果有有效记录，分子+1
                if ($hasValidRecord) {
                    $insert['ssjlwc24_fz'] += 1;
                }

                $errorMsg[] = $surgeryGroup;
            }

            if (!empty($errorMsg)) {
                $insert['ssjlwc24_error'] = json_encode($errorMsg, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        $this->UpdateIndexCataLog('ssjlwc24', 2, time());
    }

    /**
     * 临床新技术和新项目（手术）实施人符合率
     * @param mixed $data
     * @return void
     */
    public function handleXjshxxmssr($data)
    {
        $this->UpdateIndexCataLog('xjshxxmssr', 1, time());
        $carbon = new Carbon();
        foreach ($data as $item) {
            echo $item['MED_REC_ID'] . PHP_EOL;
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['xjshxxmssr_fz' => 0, 'xjshxxmssr_fm' => 0, 'xjshxxmssr_error' => null]);

            $insert = [
                'zyh' => $item['MED_REC_ID'],
                'xjshxxmssr_fz' => 0,
                'xjshxxmssr_fm' => 0,
                'xjshxxmssr_error' => null,
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => $carbon::parse($item['AAC01'])->format("Y"),
                'AAC01_MONTH' => $carbon::parse($item['AAC01'])->format("m"),
                'AAC01' => $item['AAC01']
            ];

            // 查询首页的手术（主手术和次手术）
            $mainOperation = MainOperation::query()->where('AAA28', '=', $item['MED_REC_ID'])->get(['ICD9_ID1', 'ICD9_NAME'])->toArray();
            $secondaryOperation = SecondaryOperation::query()->where('AAA28', '=', $item['MED_REC_ID'])->get(['ICD9_ID1', 'ICD9_NAME'])->toArray();
            $allOperation = array_merge($mainOperation, $secondaryOperation);

            if (empty($allOperation)) {
                continue; // 没有手术，跳过
            }

            // 收集所有新项目手术
            $newProjectOperations = [];
            foreach ($allOperation as $op) {
                $icd9Id1 = $op['ICD9_ID1'] ?? '';
                if (empty($icd9Id1)) {
                    continue;
                }

                // 检查是否是新技术新项目手术（ICD9_ID1 = SSCZBM）
                $xjsxxmss = XJSXXMSS::query()->where('SSCZBM', '=', $icd9Id1)->first();
                if (!empty($xjsxxmss)) {
                    $newProjectOperations[] = [
                        'ICD9_ID1' => $icd9Id1,
                        'ICD9_NAME' => $op['ICD9_NAME'] ?? '',
                        'XJSXXMSS' => $xjsxxmss->toArray()
                    ];
                }
            }

            // 如果没有新项目手术，跳过
            if (empty($newProjectOperations)) {
                continue;
            }

            // 分母：按患者计算，有新技术新项目手术就算1个分母
            $insert['xjshxxmssr_fm'] = 1;

            $errorMsg = [];
            $allSatisfied = true; // 是否所有手术都满足条件

            // 检查每个新项目手术是否满足条件
            foreach ($newProjectOperations as $newOp) {
                $xjsxxmss = $newOp['XJSXXMSS'];
                $ssczbm = $xjsxxmss['SSCZBM'] ?? '';
                $xmfzrmc = $xjsxxmss['XMFZRMC'] ?? '';

                $surgeryGroup = [
                    'status' => 0,
                    'content' => []
                ];

                // 添加手术信息
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "手术名称【" . ($newOp['ICD9_NAME'] ?? '') . "】"
                ];
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "手术编码【" . ($newOp['ICD9_ID1'] ?? '') . "】"
                ];

                // 查询SM_SSAP，使用SSCZBM关联ICD9_SSCZBM，SZ关联XMFZRMC
                $ssap = SM_SSAP::query()
                    ->where('ZYH', '=', $item['MED_REC_ID'])
                    ->where('ICD9_SSCZBM', '=', $ssczbm)
                    //->where('SZ', '=', $xmfzrmc)
                    ->first();

                if (!empty($ssap)) {
                    if ($ssap->SZ == $xmfzrmc) {
                        $surgeryGroup['status'] = 1;
                        $surgeryGroup['content'][] = [
                            'status' => 1,
                            'content' => "（手麻）术者【" . ($ssap->SZ ?? '') . "】与目录清单中的实施人一致"
                        ];
                    } else {
                        $allSatisfied = false;
                        $surgeryGroup['status'] = 0;
                        $surgeryGroup['content'][] = [
                            'status' => 0,
                            'content' => "（手麻）术者【" . ($ssap->SZ ?? '') . "】与目录清单中的实施人不一致"
                        ];
                    }
                } else {
                    $allSatisfied = false;
                    $surgeryGroup['content'][] = [
                        'status' => 0,
                        'content' => "手麻没有编码为【" . $ssczbm . "】的手术"
                    ];
                }

                $errorMsg[] = $surgeryGroup;
            }

            // 分子：按患者计算，如果患者所有新项目手术都满足条件，算1个分子
            if ($allSatisfied) {
                $insert['xjshxxmssr_fz'] = 1;
            }

            $insert['xjshxxmssr_error'] = !empty($errorMsg) ? json_encode($errorMsg, JSON_UNESCAPED_UNICODE) : '';
            Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
        }

        $this->UpdateIndexCataLog('xjshxxmssr', 2, time());
    }



    /**
     * 从手术记录HJNR里面获取手术开始时间和手术结束时间
     */
    private function getOperationStartTimAndEndTimeByHJNR($hjnr)
    {
        $ruleMap1041 = RuleWordMap::query()->where('id', '=', 1041)->value('keyword');
        $ruleMap1041 = !empty($ruleMap1041) ? $ruleMap1041 : "/手术日期\{(.*)\}手术时间\{(.*)\}-\{(.*)\}术前诊断/";

        $ruleMap1042 = RuleWordMap::query()->where('id', '=', 1042)->value('keyword');
        $ruleMap1042 = !empty($ruleMap1042) ? $ruleMap1042 : "/手术日期开始时间[: ](.*?)结束时间[: ](.*)术前诊断/";


        $hjnr = str_replace('：', ':', $hjnr);
        $hjnr = str_replace(["{", "}"], '', $hjnr);


        $hjnr = str_replace(["："], "", $hjnr);
        if (preg_match($ruleMap1041, $hjnr, $matches)) {
            $shrq = str_replace(["{", "}", "[", "]"], "", $matches[1]);
            $kssj = str_replace(["{", "}", "[", "]"], "", $matches[2]);
            $jssj = str_replace(["{", "}", "[", "]"], "", $matches[3]);
            $notArr = ['手术日期', '手术时间', '开始时间', '结束时间', '手术日期开始时间', '手术日期结束时间', 'aN时aN分'];
            if (in_array($shrq, $notArr) || in_array($kssj, $notArr) || in_array($jssj, $notArr)) {
                return [];
            }
            $kssj = str_replace("时", ":", $kssj);
            $kssj = str_replace("分", "", $kssj);
            $sskssj = $shrq . " " . $kssj . ":00";
            $sskssj = date('Y-m-d H:i:00', strtotime($sskssj));
            $jssj = str_replace("时", ":", $jssj);
            $jssj = str_replace("分", "", $jssj);
            $ssjssj = $shrq . " " . $jssj . ":00";
            $ssjssj = date('Y-m-d H:i:00', strtotime($ssjssj));
            return [$sskssj, $ssjssj];
        } elseif (preg_match($ruleMap1042, $hjnr, $matches)) {
            $kssj = str_replace(["{", "}", "[", "]"], "", $matches[1]);
            $jssj = str_replace(["{", "}", "[", "]"], "", $matches[2]);
            $notArr = ['手术日期', '手术时间', '开始时间', '结束时间', '手术日期开始时间', '手术日期结束时间'];
            if (in_array($kssj, $notArr) || in_array($jssj, $notArr)) {
                return [];
            }
            $sskssj = $kssj . ":00";
            $sskssj = date('Y-m-d H:i:00', strtotime($sskssj));
            $ssjssj = $jssj . ":00";
            $ssjssj = date('Y-m-d H:i:00', strtotime($ssjssj));
            return [$sskssj, $ssjssj];
        }
        return [];
    }


    /**
     * 更新指标执行状态
     * @param mixed $indexName
     * @param mixed $status
     * @param mixed $dateTime
     * @return void
     */
    public function UpdateIndexCataLog($indexName, $status, $dateTime)
    {
        $indexCatalog = IndexCatalog::query()->where('index_name', '=', $indexName)->first();
        if (empty($indexCatalog)) {
            return;
        }
        $indexCatalog = $indexCatalog->toArray();
        $update = ['status' => $status, 'quality_time' => $dateTime];
        IndexCatalog::query()->where('id', '=', $indexCatalog['id'])->update($update);
    }


    /**
     * 三级医师查房频次达标率
     * @param $data
     */
    public function newHandleSjyscf($data_content)
    {
        if (!$data_content)
            return true;

        $elasticService = NewElasticsearchService::getInstance();

        //三级医师查房关键字
        $checkValue = RuleWordMap::getFirstById(1020);
        $keyword = $checkValue ?: ["主任医师查房记录", "副主任医师查房记录"];

        //获取三级医师查房的周期
        $weekTime = RuleWordMap::getFirstById(1029);
        $weekTimeNum = data_get($weekTime, 'keyword', 7);

        //获取三级医师每周期内应查房次数
        $weekCheckNum = RuleWordMap::getFirstById(1030);
        $checkNum = data_get($weekCheckNum, 'keyword', 2);

        //筛选字段
        $fieldName = RuleWordMap::getFirstById(1058);
        $checkFieldName = data_get($fieldName, 'keyword', 'JLSJ');

        //是否排除产科1是0否，id8097=1是，0否
        $excludeProduc = RuleWordMap::query()->where('id', '=', 8097)->value('keyword');
        $excludeProduc = !empty($excludeProduc) ? $excludeProduc : 0;

        //记录任务执行开始更新时间
        $this->UpdateIndexCataLog('sjyscf', 1, time());

        $carbon = new Carbon();

        foreach ($data_content as $item) {
            //删除已经存在的数据
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['sjyscf_fm' => 0, 'sjyscf_fz' => 0, 'sjyscf_error' => null]);
            //如果出院时间和入院时间在24小时内的排除 （出院时间是否小于入院时间加一天） 小于则跳过
            /**
             * 日间病历排除
             */
            if (strtotime($item['AAC01']) - strtotime($item['AAB01']) <= 86400) {
                continue;
            }

            //$mainDiagnosisService 或 $odService 首页诊断 ICD10_ID1 包含 【Z37】
            //$mainOperationService 或 $secondaryOperationService 首页手术 ICD9_ID1 不包含 74.1

            $whereMust = ['term' => ['ZYH' => data_get($item, 'MED_REC_ID')]];
            //首页诊断
            $shouldDiagnosis = ['prefix' => ['ICD10_ID1.keyword' => 'Z37']];
            list($mainDiagnosisRes) = $elasticService->setIndex('main_diagnosis')->clearMust()->queryByMust($whereMust)->queryByShould($shouldDiagnosis)->minimumShouldMatch()->search();
            list($otherDiagnosisRes) = $elasticService->setIndex('other_diagnosis_2023')->clearMust()->queryByMust($whereMust)->queryByShould($shouldDiagnosis)->minimumShouldMatch()->search();

            //首页手术
            $shouldOperation = ['prefix' => ['ICD9_ID1.keyword' => '74.1']];
            list($mainOperationRes) = $elasticService->setIndex('main_operation')->clearMust()
                ->queryByMust(['term' => ['AAA28' => data_get($item, 'MED_REC_ID')]])
                ->queryByShould(['prefix' => ['ICD9_ID1' => '74.1']])->minimumShouldMatch()->search();

            list($secondaryOperationRes) = $elasticService->setIndex('secondary_operation_2023')->clearMust()->queryByMust($whereMust)->queryByShould($shouldOperation)->minimumShouldMatch()->search();
            //Log::error('首页诊断及手术数据::',compact('mainDiagnosisRes','mainOperationRes','otherDiagnosisRes','secondaryOperationRes'));
            //合并数组并去重
            $arrayICD10 = array_merge($mainDiagnosisRes, $otherDiagnosisRes); //首页诊断编码
            $arrayICD9 = array_merge($mainOperationRes, $secondaryOperationRes); //首页手术编码

            //首页诊断编码含【Z37】 且 首页手术编码不含【74.1】的病历剔除
            if ($arrayICD10 && empty($arrayICD9) && $excludeProduc == 1)
                continue;

            $insert = [
                'zyh' => data_get($item, 'MED_REC_ID'),
                'sjyscf_fz' => 0,
                'sjyscf_fm' => 0,
                'sjyscf_error' => null,
                'AAC11N' => data_get($item, 'AAC11N'),
                'AEE03' => data_get($item, 'AEE03', ''),
                'AAC01_YEAR' => $carbon::parse(data_get($item, 'AAC01'))->format("Y"),
                'AAC01_MONTH' => $carbon::parse(data_get($item, 'AAC01'))->format("m"),
                'AAC01' => data_get($item, 'AAC01')
            ];

            $errorMsg = [];
            $startTime = data_get($item, 'AAB01'); //入院时间
            $endTime = data_get($item, 'AAC01') ?? ''; //出院时间
            //$endTime = NULL;

            //获取医嘱表信息
            /* $shouldYzb = [['match_phrase' => ['YZMC' => '出院']], ['match_phrase' => ['YZMC' => '死亡']]];
            list($yzInfo) = $elasticService->setIndex('yzb_2023')->clearMust()->queryByMust($whereMust)->queryByShouldBatch($shouldYzb)->minimumShouldMatch()->orderBy('KZSJ', 'desc')->search();

            $yzDetails = data_get($yzInfo, '0', []); //获取首个医嘱数据
            if (strripos(data_get($yzDetails, 'YZMC'), '出院') || strripos(data_get($yzDetails, 'YZMC'), '死亡')) {
                $endTime = data_get($yzDetails, 'KZSJ') ? $carbon::parse(data_get($yzDetails, 'KZSJ'))->format("Y-m-d 23:59:59") : ''; //开医嘱时间
            } */

            if (empty($endTime)) {
                continue;
                /* $errorMsg = [
                    [
                        'status' => 0,
                        'content' => [
                            ['status' => 0, 'content' => "查房周期【-】【{$startTime}】至【-】"],
                            ['status' => 0, 'content' => "医师查房【-】"],
                            ['status' => 0, 'content' => "【无出院医嘱】"]
                        ]
                    ]
                ]; */
            } else {
                //$zyDay = intval($item['AAC04']); //住院天数

                $ycfNum = $checkNum;
                //分母：住院天数/周期数  取商 比如住院10天，分母就是 10/7 = 2
                //$zqNum = intval(ceil($zyDay / $weekTimeNum));

                while (true) {

                    $tempContent1 = $tempContent2 = $tempContent3 = [];

                    $weekStr = "查房周期【%s天/%s次】【%s】至【%s】";
                    // 确保 startTime 格式化为 Y-m-d H:i:s 格式，以匹配 Elasticsearch 索引格式
                    $startTime = $carbon::parse($startTime)->format('Y-m-d H:i:s');

                    $startTimeEnd = $carbon::parse($carbon::parse($startTime)->format('Y-m-d'))
                        ->addDays($weekTimeNum)->subSeconds(1)->format('Y-m-d H:i:s');

                    $strContent1 = sprintf($weekStr, $weekTimeNum, $ycfNum, $carbon::parse($startTime)->format('Y-m-d H:i:s'), $carbon::parse($startTimeEnd)->format('Y-m-d H:i:s'));

                    if ($carbon::parse($startTimeEnd)->gte($carbon::parse($endTime))) {
                        $startTimeEnd = $carbon::parse($endTime)->format('Y-m-d H:i:s');
                        //算算几天
                        $syDayNum = ceil((strtotime($endTime) - strtotime($startTime)) / (24 * 3600));
                        //实际应该查房数量
                        $ycfNum = intdiv($syDayNum, 3); //计算出来剩余天数应该查房次数

                        $strContent1 = sprintf($weekStr, $syDayNum, $ycfNum, $carbon::parse($startTime)->format('Y-m-d H:i:s'), $carbon::parse($endTime)->format('Y-m-d H:i:s'));
                    }

                    // 所有周期都计入分母（与循环内实际处理的周期保持一致）
                    $insert['sjyscf_fm']++;

                    $tempContent1[] = ["status" => 1, "content" => $strContent1];

                    $must = [
                        ['term' => ['JZHM' => $item['MED_REC_ID']]],
                        ['term' => ['BLLB' => 294]],
                        [
                            'bool' => [
                                'should' => (function () use ($keyword) {
                                    $_ = [];
                                    foreach ($keyword as $v) {
                                        $_[] = ['match_phrase' => ['BLMC' => $v]];
                                    }
                                    return $_;
                                })()
                            ]
                        ],
                        ['range' => [$checkFieldName => ['gte' => $startTime, 'lte' => $startTimeEnd]]]
                    ];
                    $mustNot = ['term' => ['BLZT' => $this->ruleMap2036]];

                    list($data) = $elasticService->setIndex('bl01_202303')->clearMust()->queryByMustBatch($must)->queryByMustNot($mustNot)->orderBy($checkFieldName, 'asc')->search();


                    $cf_str = "医师查房【%s次】";
                    $qm_str = "【%s】";

                    $actualNum2 = 0; //医生实际查房数量

                    $strContent2 = ["status" => 0, "content" => sprintf($cf_str, 0)];

                    if (empty($data) && (int) $ycfNum <= 0) {
                        $insert['sjyscf_fz']++;
                        data_set($strContent2, 'status', 1);
                    } else {
                        foreach ($data as $val) {
                            $strContent3 = ["status" => 0, "content" => sprintf($qm_str, $val['BLMC'])];
                            //查询签名
                            $blsy = '';
                            $blsydata = EMR_BL_BLSY::query()->where('BLBH', $val['BLBH'])->where('FG_ACTIVE', 1)->get()->toArray();
                            if (!empty($blsydata)) {
                                foreach ($blsydata as $blsyitem) {
                                    $staff = Staff::query()->where('code', $blsyitem['SYYS'])->first();
                                    if (!empty($staff)) {
                                        $blsy .= $staff['name'] . ',';
                                    }
                                }
                            }
                            // 初始化 strContent4，确保始终有值
                            if (!empty($blsy)) {
                                $blsy = rtrim($blsy, ',');
                                $strContent4 = ["status" => 0, "content" => 'CA签名【' . $blsy . '】'];
                            } else {
                                $strContent4 = ["status" => 0, "content" => 'CA签名【无】'];
                            }

                            // 初始化 strContent5，确保始终有值
                            $first = $val['first_blsy_time'] ?? '';
                            if (!empty($first)) {
                                $strContent5 = ["status" => 0, "content" => '首次签名时间【' . $first . '】(超时)'];
                            } else {
                                $strContent5 = ["status" => 0, "content" => '首次签名时间【无】'];
                            }

                            if (
                                $carbon::parse($val[$this->timeField])->gte($startTime)
                                && $carbon::parse($val[$this->timeField])->lte($startTimeEnd)
                            ) {
                                $actualNum2++; //实际查房数量
                                data_set($strContent3, 'status', 1);
                                data_set($strContent4, 'status', 1);
                                $strContent5 = ["status" => 1, "content" => '首次签名时间【' . $first . '】'];
                            }
                            $tempContent3[] = $strContent3;
                            $tempContent3[] = $strContent5;
                            $tempContent3[] = $strContent4;
                            //添加一行空信息用来分隔
                            $tempContent3[] = ["status" => 0, "content" => ""];
                        }

                        data_set($strContent2, 'status', ($actualNum2 < $ycfNum) ? 0 : 1);
                        data_set($strContent2, 'content', sprintf($cf_str, $actualNum2));
                        if ($actualNum2 >= $ycfNum) {
                            $insert['sjyscf_fz']++;
                            // 添加 null 检查，过滤掉可能的空值
                            $tempContent3 = array_filter($tempContent3, function ($val) {
                                return !is_null($val) && isset($val['status']) && ($val['status'] == 1);
                            });
                        }
                    }
                    $tempContent2[] = $strContent2;

                    $tempContent4 = array_merge($tempContent1, $tempContent2, $tempContent3);
                    if (!empty($tempContent4)) {
                        $errorMsg[] = ["status" => ((($actualNum2 >= $ycfNum) || (0 == $ycfNum)) ? 1 : 0), "content" => $tempContent4];
                    }
                    //如果周期时间 >= 结束时间 结束时间周期循环
                    if ($startTimeEnd >= $endTime)
                        break;

                    //将开始时间设置为上周期结束的下一天
                    $startTime = $carbon::parse($startTimeEnd)->addDays(1)->format("Y-m-d 00:00:00");
                }
            }

            $insert['sjyscf_fz'] = $insert['sjyscf_fz'] > $insert['sjyscf_fm'] ? $insert['sjyscf_fm'] : $insert['sjyscf_fz'];
            $insert['sjyscf_error'] = !empty($errorMsg) ? json_encode($errorMsg, 256) : '';
            Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
        }
        $this->UpdateIndexCataLog('sjyscf', 2, time());
    }

    /**
     * 患者入院48小时内转科率
     */
    public function handleHzry48xsnzk($data, $startTime)
    {
        $checkValue8034 = RuleWordMap::getFirstById(8034);
        $keyword = data_get($checkValue8034, 'keyword', '转科,转到');
        if (strpos($keyword, ',') !== false) {
            $keyword = explode(',', $keyword);
        } else {
            $keyword = [$keyword];
        }

        //YZMC不包含关键字
        $ruleMap9030 = RuleWordMap::query()->where('id', '=', 9030)->value('keyword');
        $ruleMap9030 = !empty($ruleMap9030) ? $ruleMap9030 : '';
        if (!empty($ruleMap9030)) {
            if (strpos($ruleMap9030, ',') !== false) {
                $ruleMap9030 = explode(',', $ruleMap9030);
            } else {
                $ruleMap9030 = [$ruleMap9030];
            }
        }

        $checkValue2035 = RuleWordMap::getFirstById(2035);
        $keyword2035 = $checkValue2035 ?: "3";

        $carbon = new Carbon();

        $this->UpdateIndexCataLog('hzry48xsnzk', 1, time());
        foreach ($data as $item) {
            var_dump('AAC11N---' . $item['AAC11N']);
            $insert = [
                'zyh' => data_get($item, 'MED_REC_ID'),
                'hzry48xsnzk_fz' => 0,
                'hzry48xsnzk_fm' => 0,
                'hzry48xsnzk_error' => null,
                'AAC11N' => data_get($item, 'AAC11N'),
                'AEE03' => data_get($item, 'AEE03', ''),
                'AAC01_YEAR' => $carbon::parse(data_get($item, 'AAC01'))->format("Y"),
                'AAC01_MONTH' => $carbon::parse(data_get($item, 'AAC01'))->format("m"),
                'AAC01' => data_get($item, 'AAC01')
            ];
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['hzry48xsnzk_fm' => null, 'hzry48xsnzk_fz' => 0, 'hzry48xsnzk_error' => null]);


            $errorMsg = [
                [
                    'status' => 0,
                    'content' => [
                        ['status' => 0, 'content' => "【患者入院48小时内未存在转科医嘱】"]
                    ]
                ]
            ];

            /**
             * 日间病历排除
             */
            if (strtotime($item['AAC01']) - strtotime($item['AAB01']) <= 86400) {
                continue;
            }

            $insert['hzry48xsnzk_fm'] += 1;

            /**
             * 查询医嘱中的转科医嘱
             */
            $endTime = date('Y-m-d H:i:s', strtotime($item['AAB01']) + (86400 * 2));

            $yzInfo = Yzb::query()
                ->where('ZYH', '=', $insert['zyh'])
                ->where('YZZT', '!=', $keyword2035)
                ->where('KZSJ', '<=', $endTime)
                ->orderBy('KZSJ', 'asc')
                //->first();
                ->get()->toArray();

            $kzsj = '';
            $YZMC = '';
            $iszz = '';
            $iszzkzsj = '';

            foreach ($yzInfo as $i => $val) {
                foreach ($keyword as $v) {
                    if (strripos(data_get($val, 'YZMC'), $v)) {
                        $YZMC = data_get($val, 'YZMC');
                        $kzsj = data_get($val, 'KZSJ');
                        break;
                    }
                }
                foreach ($ruleMap9030 as $v) {
                    if (strripos(data_get($val, 'YZMC'), $v) !== false) {
                        $iszz = data_get($val, 'YZMC');
                        $iszzkzsj = data_get($val, 'KZSJ');
                        break;
                    }
                }
                if (!empty($iszz) && !empty($YZMC)) {
                    break;
                }
            }

            //入院时间、医嘱名称、开嘱时间信息
            $errorMsg[0]['status'] = 0;
            $errorMsg[0]['content'][0]['status'] = 1;
            $errorMsg[0]['content'][0]['content'] = "入院时间【" . $item['AAB01'] . "】";

            if (empty($iszz) && !empty($YZMC)) {
                if (!empty($YZMC)) {
                    $errorMsg[0]['content'][1]['status'] = 1;
                    $errorMsg[0]['content'][1]['content'] = "医嘱名称【" . $YZMC . "】";
                } else {
                    $errorMsg[0]['content'][1]['status'] = 0;
                    $errorMsg[0]['content'][1]['content'] = "医嘱名称【无】";
                }

                if (!empty($kzsj)) {
                    $msg = strtotime($kzsj) <= strtotime($endTime) ? ' 小于48小时' : ' 大于48小时';
                    $errorMsg[0]['content'][2]['status'] = 1;
                    $errorMsg[0]['content'][2]['content'] = "开嘱时间【" . $kzsj . $msg . "】";
                } else {
                    $errorMsg[0]['content'][2]['status'] = 0;
                    $errorMsg[0]['content'][2]['content'] = "开嘱时间【无】";
                }

                if (!empty($kzsj) && strtotime($kzsj) <= strtotime($endTime) && strtotime($kzsj) >= strtotime($item['AAB01'])) {
                    $insert['hzry48xsnzk_fz'] += 1;
                    $errorMsg[0]['status'] = 1;
                }
            } else if (!empty($iszz) && !empty($YZMC)) {
                $errorMsg[0]['content'][1]['status'] = 0;
                $errorMsg[0]['content'][1]['content'] = "医嘱名称【" . $iszz . "】";
                $errorMsg[0]['content'][2]['status'] = 0;
                $errorMsg[0]['content'][2]['content'] = "开嘱时间【" . $iszzkzsj . "】";
                $errorMsg[0]['content'][3]['status'] = 0;
                $errorMsg[0]['content'][3]['content'] = "【已排除】";
            } else if (empty($YZMC)) {
                $errorMsg[0]['content'][1]['status'] = 0;
                $errorMsg[0]['content'][1]['content'] = "【患者入院48小时内未存在转科医嘱】";
            }

            $insert['hzry48xsnzk_fz'] = $insert['hzry48xsnzk_fz'] > $insert['hzry48xsnzk_fm'] ? $insert['hzry48xsnzk_fm'] : $insert['hzry48xsnzk_fz'];
            $insert['hzry48xsnzk_error'] = !empty($errorMsg) ? json_encode($errorMsg, 256) : '';
            Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
        }

        $this->UpdateIndexCataLog('hzry48xsnzk', 2, time());
    }

    /**
     * 患者入院8小时内查房率
     */
    public function handleHzry8xsncf($data, $startTime)
    {
        $checkValue2035 = RuleWordMap::getFirstById(2035);
        $keyword2035 = $checkValue2035 ? explode(",", data_get($checkValue2035, 'keyword')) : "3";
        $carbon = new Carbon();
        $this->UpdateIndexCataLog('hzry8xsncf', 1, time());
        foreach ($data as $item) {
            $insert = [
                'zyh' => data_get($item, 'MED_REC_ID'),
                'hzry8xsncf_fz' => 0,
                'hzry8xsncf_fm' => 0,
                'hzry8xsncf_error' => null,
                'AAC11N' => data_get($item, 'AAC11N'),
                'AEE03' => data_get($item, 'AEE03', ''),
                'AAC01_YEAR' => $carbon::parse(data_get($item, 'AAC01'))->format("Y"),
                'AAC01_MONTH' => $carbon::parse(data_get($item, 'AAC01'))->format("m"),
                'AAC01' => data_get($item, 'AAC01')
            ];

            //删除
            Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], ['hzry8xsncf_fm' => null, 'hzry8xsncf_fz' => 0, 'hzry8xsncf_error' => null]);

            $errorMsg = [
                [
                    'status' => 0,
                    'content' => [
                        ['status' => 1, 'content' => "入院时间【" . $item['AAB01'] . "】"],
                        ['status' => 0, 'content' => "【患者入院8小时内未存在检查或治疗医嘱】"]
                    ]
                ]
            ];

            /**
             * 日间病历排除
             */
            if (strtotime($item['AAC01']) - strtotime($item['AAB01']) <= 86400) {
                continue;
            }

            $insert['hzry8xsncf_fm'] += 1;

            $endTime = strtotime($item['AAB01']) + (8 * 3600);

            $yzInfo = Yzb::query()
                ->where('ZYH', '=', $insert['zyh'])
                ->where('YZZT', '!=', $keyword2035)
                ->where('KZSJ', '<=', $endTime)
                ->orderBy('KZSJ', 'asc')
                ->get()
                ->toArray();

            $kzsj = '';
            $YZMC = '';

            foreach ($yzInfo as $val) {
                $YZMC = $val['YZMC'];
                $kzsj = $val['KZSJ'];
                break;
            }


            if (!empty($YZMC)) {
                $insert['hzry8xsncf_fz'] += 1;
                //入院时间、医嘱名称、开嘱时间信息
                $errorMsg[0]['status'] = 0;
                $errorMsg[0]['content'][0]['status'] = 1;
                $errorMsg[0]['content'][0]['content'] = "入院时间【" . $item['AAB01'] . "】";
                $errorMsg[0]['content'][1]['status'] = 1;
                $errorMsg[0]['content'][1]['content'] = "医嘱【" . $YZMC . "】";
                $errorMsg[0]['content'][2]['status'] = 1;
                $errorMsg[0]['content'][2]['content'] = "开嘱时间【" . $kzsj . "】";
                $errorMsg[0]['content'][3]['status'] = 1;
                $errorMsg[0]['content'][3]['content'] = "【入院8小时内开具的第一条医嘱】";
            }

            $insert['hzry8xsncf_fz'] = $insert['hzry8xsncf_fz'] > $insert['hzry8xsncf_fm'] ? $insert['hzry8xsncf_fm'] : $insert['hzry8xsncf_fz'];
            $insert['hzry8xsncf_error'] = !empty($errorMsg) ? json_encode($errorMsg, 256) : '';
            Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
        }

        $this->UpdateIndexCataLog('hzry8xsncf', 2, time());
    }

    /**
     * 二级护理/三级护理出院率
     */
    public function handleEjhlsjhlcy($data, $startTime)
    {
        $carbon = new Carbon();
        $YZBESServer = new ElasticsearchService('yzb_2023');

        //更改状态
        $this->UpdateIndexCataLog('ejhlsjhlcy', 1, time());

        foreach ($data as $item) {
            //删除之前的数据
            Indicator::query()->updateOrInsert(['zyh' => data_get($item, 'MED_REC_ID')], ['ejhlsjhlcy_fm' => 0, 'ejhlsjhlcy_fz' => 0, 'ejhlsjhlcy_error' => null]);

            $insert = [
                'zyh' => data_get($item, 'MED_REC_ID'),
                'ejhlsjhlcy_fz' => 0,
                'ejhlsjhlcy_fm' => 0,
                'ejhlsjhlcy_error' => '',
                'AAC11N' => data_get($item, 'AAC11N'),
                'AEE03' => data_get($item, 'AEE03', ''),
                'AAC01_YEAR' => $carbon::parse(data_get($item, 'AAC01'))->format("Y"),
                'AAC01_MONTH' => $carbon::parse(data_get($item, 'AAC01'))->format("m"),
                'AAC01' => data_get($item, 'AAC01')
            ];

            $AAC01 = data_get($item, 'AAC01');
            if (empty($AAC01)) {
                continue;
            }

            /**
             * 只记录出院方式为医嘱离院的数据
             */
            if (data_get($item, 'AEM01C') != 1) {
                continue;
            }

            $errorMsg = [
                [
                    'status' => 0,
                    'content' => [
                        ['status' => 0, 'content' => "离院方式【医嘱离院】"]
                    ]
                ]
            ];

            $insert['ejhlsjhlcy_fm'] += 1;

            //构建yzb查询
            $must = [
                ['term' => ['ZYH' => data_get($item, 'MED_REC_ID')]]
            ];
            //停嘱时间在患者出院时间当天的0点-23:59:59
            $startTime = $carbon::parse($AAC01)->format('Y-m-d 00:00:00');
            $endTime = $carbon::parse($AAC01)->format('Y-m-d 23:59:59');
            $must[] = ['range' => ['TZSJ' => ['gte' => $startTime, 'lte' => $endTime]]];
            //排序
            //$orderBy = ['KZSJ' => 'desc'];  
            $should = [];
            $should[] = ['match_phrase' => ['YZMC' => '一级护理']];
            $should[] = ['match_phrase' => ['YZMC' => '二级护理']];
            $should[] = ['match_phrase' => ['YZMC' => '三级护理']];
            $should[] = ['match_phrase' => ['YZMC' => '特级护理']];
            $should[] = ['match_phrase' => ['YZMC' => 'Ⅰ级护理']];
            $should[] = ['match_phrase' => ['YZMC' => 'II级护理']];
            $should[] = ['match_phrase' => ['YZMC' => 'III级护理']];
            $should[] = ['match_phrase' => ['YZMC' => 'Ⅰ级护理']];
            $should[] = ['match_phrase' => ['YZMC' => 'Ⅱ级护理']];
            $should[] = ['match_phrase' => ['YZMC' => 'Ⅲ级护理']];
            $params = $YZBESServer->clearMust()
                ->queryByMustBatch($must)
                ->queryByShouldBatch($should)
                ->minimumShouldMatch()
                ->orderBy('KZSJ', 'desc')
                ->getParams();
            $res = app('es')->search($params);
            $yzbData = $YZBESServer->getDataByEs($res);

            if (!empty($yzbData[0])) {
                if ($yzbData[0][0]['YZMC'] == '一级护理' || $yzbData[0][0]['YZMC'] == '特级护理' || $yzbData[0][0]['YZMC'] == 'I级护理' || $yzbData[0][0]['YZMC'] == 'Ⅰ级护理') {
                    $errorMsg[0]['status'] = 0;
                    $errorMsg[0]['content'][1]['status'] = 0;
                    $errorMsg[0]['content'][1]['content'] = "医嘱名称【" . $yzbData[0][0]['YZMC'] . "】";
                    $errorMsg[0]['content'][2]['status'] = 0;
                    $errorMsg[0]['content'][2]['content'] = "停嘱时间【" . $yzbData[0][0]['TZSJ'] . "】";
                } else if ($yzbData[0][0]['YZMC'] == '三级护理' || $yzbData[0][0]['YZMC'] == '二级护理' || $yzbData[0][0]['YZMC'] == 'II级护理' || $yzbData[0][0]['YZMC'] == 'III级护理' || $yzbData[0][0]['YZMC'] == 'Ⅱ级护理' || $yzbData[0][0]['YZMC'] == 'Ⅲ级护理') {
                    $insert['ejhlsjhlcy_fz'] += 1;
                    $errorMsg[0]['status'] = 1;
                    $errorMsg[0]['content'][0]['status'] = 1;
                    $errorMsg[0]['content'][1]['status'] = 1;
                    $errorMsg[0]['content'][1]['content'] = "医嘱名称【" . $yzbData[0][0]['YZMC'] . "】";
                    $errorMsg[0]['content'][2]['status'] = 1;
                    $errorMsg[0]['content'][2]['content'] = "停嘱时间【" . $yzbData[0][0]['TZSJ'] . "】";
                }
            }


            /* $infoV2 = PatientInfoV2::query()
                ->where('ZYH', '=', data_get($item, 'MED_REC_ID'))
                ->first();

            if (!empty($infoV2) && (!empty($infoV2->EJHL) || !empty($infoV2->SJHL))) {
                $insert['ejhlsjhlcy_fz'] += 1;
                $errorMsg[0]['status'] = 1;
                $errorMsg[0]['content'][0]['status'] = 1;
                if (!empty($infoV2->EJHL)) {
                    $errorMsg[0]['content'][0]['content'] = "患者出院时为二级护理";
                } else {
                    $errorMsg[0]['content'][0]['content'] = "患者出院时为三级护理";
                }
            } */

            $insert['ejhlsjhlcy_fz'] = $insert['ejhlsjhlcy_fz'] > $insert['ejhlsjhlcy_fm'] ? $insert['ejhlsjhlcy_fm'] : $insert['ejhlsjhlcy_fz'];
            $insert['ejhlsjhlcy_error'] = !empty($errorMsg) ? json_encode($errorMsg, 256) : '';
            Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
        }

        //更改状态
        $this->UpdateIndexCataLog('ejhlsjhlcy', 2, time());
    }

    /**
     * 抢救成功率
     */
    public function handleQjcg($data)
    {
        $ruleMap2001 = RuleWordMap::query()->where("id", "=", 2001)->first()->toArray();
        $ruleMap2002 = RuleWordMap::query()->where("id", "=", 2002)->first()->toArray();
        $carbon = new Carbon();
        $bl01esService = new ElasticsearchService('bl01_202303');

        foreach ($data as $item) {
            $insert = [
                'zyh' => data_get($item, 'MED_REC_ID'),
                'qjcg_fz' => 0,
                'qjcg_fm' => 0,
                'qjcg_error' => '',
                'AAC11N' => data_get($item, 'AAC11N'),
                'AEE03' => data_get($item, 'AEE03', ''),
                'AAC01_YEAR' => $carbon::parse(data_get($item, 'AAC01'))->format("Y"),
                'AAC01_MONTH' => $carbon::parse(data_get($item, 'AAC01'))->format("m"),
                'AAC01' => data_get($item, 'AAC01')
            ];

            $errorMsg = [
                [
                    'status' => 0,
                    'content' => [
                        ['status' => 0, 'content' => "【抢救记录无】"]
                    ]
                ]
            ];

            $yzbInfo = Yzb::query()
                ->where('ZYH', '=', $insert['zyh'])
                ->where('YZMC', 'LIKE', '大抢救')
                ->get()
                ->toArray();

            if (empty($yzbInfo)) {
                continue;
            }

            $insert['qjcg_fm'] += 1;

            $params = $bl01esService->clearMust()
                ->queryByMust(['term' => ['JZHM' => data_get($item, 'MED_REC_ID')]])
                ->queryByMust(['match_phrase' => ['BLMC' => $ruleMap2002['keyword']]])
                ->source([$ruleMap2001['keyword'], 'HJNR', 'BLMC', "BLBH"])->getParams();
            $res = app('es')->search($params);
            $resData = $bl01esService->getDataByEs($res);

            if ($resData[1]) {
                $qjjl = $resData[0];
                foreach ($qjjl as $val) {
                    $val['HJNR'] = str_replace("：", ":", $val['HJNR']);
                    $val['HJNR'] = str_replace(["{", "}", "(", ")"], "", $val['HJNR']);

                    if (strripos(data_get($val, 'HJNR'), '抢救成功') || strripos(data_get($val, 'HJNR'), '病情好转')) {
                        $insert['qjcg_fz'] += 1;
                        $errorMsg[0]['status'] = 1;
                        $errorMsg[0]['content'][0]['status'] = 1;
                        $errorMsg[0]['content'][0]['content'] = "【患者病情好转】";
                    }
                }
            }

            $insert['qjcg_fz'] = $insert['qjcg_fz'] > $insert['qjcg_fm'] ? $insert['qjcg_fm'] : $insert['qjcg_fz'];
            $insert['qjcg_error'] = !empty($errorMsg) ? json_encode($errorMsg, 256) : '';
            Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
        }

        $this->UpdateIndexCataLog('qjcg', 2, time());
    }

    /**
     * 长期医嘱当日终止率
     */
    public function handleCqyzdrzz($data)
    {
        $this->UpdateIndexCataLog('cqyzdrzz', 1, time());
        echo '病历数量：' . count($data) . PHP_EOL;
        $ruleMap2040 = RuleWordMap::query()->where("id", "=", 2040)->first()->toArray();
        $carbon = new Carbon();

        foreach ($data as $item) {
            //删除之前的数据
            Indicator::query()->updateOrInsert(['zyh' => data_get($item, 'MED_REC_ID')], ['cqyzdrzz_fm' => 0, 'cqyzdrzz_fz' => 0, 'cqyzdrzz_error' => null]);
            $insert = [
                'zyh' => data_get($item, 'MED_REC_ID'),
                'cqyzdrzz_fz' => 0,
                'cqyzdrzz_fm' => 0,
                'cqyzdrzz_error' => null,
                'AAC11N' => data_get($item, 'AAC11N'),
                'AEE03' => data_get($item, 'AEE03', ''),
                'AAC01_YEAR' => $carbon::parse(data_get($item, 'AAC01'))->format("Y"),
                'AAC01_MONTH' => $carbon::parse(data_get($item, 'AAC01'))->format("m"),
                'AAC01' => data_get($item, 'AAC01')
            ];

            $errorMsg = [];

            echo 'zyh----：' . $insert['zyh'] . PHP_EOL;

            $yzbInfo = Yzb::query()
                ->where('ZYH', '=', $insert['zyh'])
                ->where('YZQX', '=', $ruleMap2040['keyword'])
                ->get(['KZSJ', 'TZSJ', 'YZMC'])
                ->toArray();

            echo 'yzbInfo：' . count($yzbInfo) . PHP_EOL;

            if (empty($yzbInfo)) {
                continue;
            }
            $yzbnum = 0;
            foreach ($yzbInfo as $val) {
                $group = [
                    'status' => 0,
                    'content' => []
                ];
                $insert['cqyzdrzz_fm'] += 1;
                if (empty(data_get($val, 'KZSJ')) || empty(data_get($val, 'TZSJ'))) {
                    continue;
                }
                echo 'KZSJ：' . data_get($val, 'KZSJ') . PHP_EOL;
                echo 'TZSJ：' . data_get($val, 'TZSJ') . PHP_EOL;
                $KZSJ = data_get($val, 'KZSJ') ? $carbon::parse(data_get($val, 'KZSJ'))->format("Y-m-d") : '';
                $TZSJ = data_get($val, 'TZSJ') ? $carbon::parse(data_get($val, 'TZSJ'))->format("Y-m-d") : '';

                if ($KZSJ == $TZSJ) {
                    $insert['cqyzdrzz_fz'] += 1;
                    $group['status'] = 1;
                    $group['content'][] = ['status' => 1, 'content' => "医嘱名称【" . $val['YZMC'] . "】"];
                    $group['content'][] = ['status' => 1, 'content' => "开嘱时间【" . $val['KZSJ'] . "】"];
                    $group['content'][] = ['status' => 1, 'content' => "停嘱时间【" . $val['TZSJ'] . "】（当日终止）"];
                } else {
                    $yzbnum += 1;
                }
                if ($group['status'] == 1) {
                    $errorMsg[] = $group;
                }
            }

            if ($yzbnum > 0) {
                $group = [
                    'status' => 0,
                    'content' => []
                ];
                //长期医嘱【  **条】均为未在当天终止
                $group['content'][] = ['status' => 0, 'content' => "长期医嘱【" . $yzbnum . "条】均为未在当天终止"];
                $errorMsg[] = $group;
            }


            $insert['cqyzdrzz_fz'] = $insert['cqyzdrzz_fz'] > $insert['cqyzdrzz_fm'] ? $insert['cqyzdrzz_fm'] : $insert['cqyzdrzz_fz'];
            $insert['cqyzdrzz_error'] = !empty($errorMsg) ? json_encode($errorMsg, 256) : '';
            Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
        }

        $this->UpdateIndexCataLog('cqyzdrzz', 2, time());
    }

    /**
     * 手术医师手术时间重合率
     */
    public function handleSsyssssjch($data)
    {
        $carbon = new Carbon();
        foreach ($data as $item) {
            $insert = [
                'zyh' => data_get($item, 'MED_REC_ID'),
                'ssyssssjch_fz' => 0,
                'ssyssssjch_fm' => 0,
                'ssyssssjch_error' => '',
                'AAC11N' => data_get($item, 'AAC11N'),
                'AEE03' => data_get($item, 'AEE03', ''),
                'AAC01_YEAR' => $carbon::parse(data_get($item, 'AAC01'))->format("Y"),
                'AAC01_MONTH' => $carbon::parse(data_get($item, 'AAC01'))->format("m"),
                'AAC01' => data_get($item, 'AAC01')
            ];

            $errorMsg = [
                [
                    'status' => 0,
                    'content' => [
                        ['status' => 0, 'content' => "【当前患者手术术者未存在重合手术】"]
                    ]
                ]
            ];

            /**
             * 查询主要手术与次要手术
             */
            $mainOperation = mainOperation::query()
                ->where('AAA28', '=', data_get($item, 'MED_REC_ID'))
                ->get()->toArray();

            $secondaryOperation = secondaryOperation::query()
                ->where('AAA28', '=', data_get($item, 'MED_REC_ID'))
                ->get()->toArray();

            if (empty($mainOperation) && empty($secondaryOperation)) {
                continue;
            }

            $insert['ssyssssjch_fm'] += (count($mainOperation) + count($secondaryOperation));

            foreach ($mainOperation as $val) {
                $mainOperationRes = mainOperation::query()
                    ->where('OPE_MAN_CODE', '=', $val['OPE_MAN_CODE'])
                    ->where('START_TIME', '>=', $val['START_TIME'])
                    ->where('START_TIME', '<=', $val['END_TIME'])
                    ->get()->toArray();

                $secondaryOperationRes = secondaryOperation::query()
                    ->where('OPE_MAN_CODE', '=', $val['OPE_MAN_CODE'])
                    ->where('START_TIME', '>=', $val['START_TIME'])
                    ->where('START_TIME', '<=', $val['END_TIME'])
                    ->get()->toArray();

                if (empty($mainOperationRes) && empty($secondaryOperationRes)) {
                    continue;
                }

                $insert['ssyssssjch_fz'] += (count($mainOperationRes) + count($secondaryOperationRes));

                $errorMsg[0]['status'] = 1;
                $errorMsg[0]['content'][0]['status'] = 1;
                $errorMsg[0]['content'][0]['content'] = "【当前患者手术术者存在重合手术】";
            }

            $insert['ssyssssjch_fz'] = $insert['ssyssssjch_fz'] > $insert['ssyssssjch_fm'] ? $insert['ssyssssjch_fm'] : $insert['ssyssssjch_fz'];
            $insert['ssyssssjch_error'] = !empty($errorMsg) ? json_encode($errorMsg, 256) : '';
            Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
        }

        $this->UpdateIndexCataLog('ssyssssjch', 2, time());
    }

    /**
     * 麻醉医师手术时间重合率
     */
    public function handleMzysmzsjch($data)
    {
        $carbon = new Carbon();

        foreach ($data as $item) {
            $insert = [
                'zyh' => data_get($item, 'MED_REC_ID'),
                'mzysmzsjch_fz' => 0,
                'mzysmzsjch_fm' => 0,
                'mzysmzsjch_error' => '',
                'AAC11N' => data_get($item, 'AAC11N'),
                'AEE03' => data_get($item, 'AEE03', ''),
                'AAC01_YEAR' => $carbon::parse(data_get($item, 'AAC01'))->format("Y"),
                'AAC01_MONTH' => $carbon::parse(data_get($item, 'AAC01'))->format("m"),
                'AAC01' => data_get($item, 'AAC01')
            ];

            $errorMsg = [
                [
                    'status' => 0,
                    'content' => [
                        ['status' => 0, 'content' => "【当前患者手术麻醉医师未存在重合手术】"]
                    ]
                ]
            ];

            /**
             * 查询主要手术与次要手术
             */
            $mainOperation = mainOperation::query()
                ->where('AAA28', '=', data_get($item, 'MED_REC_ID'))
                ->get()->toArray();

            $secondaryOperation = secondaryOperation::query()
                ->where('AAA28', '=', data_get($item, 'MED_REC_ID'))
                ->get()->toArray();

            if (empty($mainOperation) && empty($secondaryOperation)) {
                continue;
            }

            $insert['mzysmzsjch_fm'] += (count($mainOperation) + count($secondaryOperation));

            foreach ($mainOperation as $val) {
                $mainOperationRes = mainOperation::query()
                    ->where('HOCUS_MAN_CODE', '=', $val['HOCUS_MAN_CODE'])
                    ->where('HOCUS_MAN_CODE', '<>', '')
                    ->where('START_TIME', '>=', $val['START_TIME'])
                    ->where('START_TIME', '<=', $val['END_TIME'])
                    ->get()->toArray();

                $secondaryOperationRes = secondaryOperation::query()
                    ->where('HOCUS_MAN_CODE', '=', $val['HOCUS_MAN_CODE'])
                    ->where('HOCUS_MAN_CODE', '<>', '')
                    ->where('START_TIME', '>=', $val['START_TIME'])
                    ->where('START_TIME', '<=', $val['END_TIME'])
                    ->get()->toArray();

                if (empty($mainOperationRes) && empty($secondaryOperationRes)) {
                    continue;
                }

                $insert['mzysmzsjch_fz'] += (count($mainOperationRes) + count($secondaryOperationRes));

                $errorMsg[0]['status'] = 1;
                $errorMsg[0]['content'][0]['status'] = 1;
                $errorMsg[0]['content'][0]['content'] = "【当前患者手术麻醉医师存在重合手术】";
            }

            $insert['mzysmzsjch_fz'] = $insert['mzysmzsjch_fz'] > $insert['mzysmzsjch_fm'] ? $insert['mzysmzsjch_fm'] : $insert['mzysmzsjch_fz'];
            $insert['mzysmzsjch_error'] = !empty($errorMsg) ? json_encode($errorMsg, 256) : '';
            Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
        }

        $this->UpdateIndexCataLog('mzysmzsjch', 2, time());
    }

    /**
     * 四级手术与三级手术并发症发生率比
     */
    public function handleSjssysjssbfzfsl($data)
    {
        $carbon = new Carbon();

        foreach ($data as $item) {
            $insert = [
                'zyh' => data_get($item, 'MED_REC_ID'),
                'sjssysjssbfzfs_fz' => 0,
                'sjssysjssbfzfs_fm' => 0,
                'sjssysjssbfzfs_error' => '',
                'AAC11N' => data_get($item, 'AAC11N'),
                'AEE03' => data_get($item, 'AEE03', ''),
                'AAC01_YEAR' => $carbon::parse(data_get($item, 'AAC01'))->format("Y"),
                'AAC01_MONTH' => $carbon::parse(data_get($item, 'AAC01'))->format("m"),
                'AAC01' => data_get($item, 'AAC01')
            ];

            $errorMsg = [
                [
                    'status' => 0,
                    'content' => []
                ]
            ];
            $bfz = ['T80', 'T81', 'T82', 'T83', 'T84'];
            $MainDiagnosis = MainDiagnosis::query()->where('AAA28', '=', data_get($item, 'MED_REC_ID'))->get(['ICD10_ID1', 'ICD10_NAME'])->toArray();
            $bfzNum = 0;
            foreach ($MainDiagnosis as $val) {
                foreach ($bfz as $v) {
                    if (strpos($val['ICD10_ID1'], $v) !== false) {
                        $bfzNum += 1;
                        break;
                    }
                }
            }

            $OtherDiagnosis = OtherDiagnosis::query()->where('AAA28', '=', data_get($item, 'MED_REC_ID'))->get(['ICD10_ID1', 'ICD10_NAME'])->toArray();
            $bfzOtherNum = 0;
            foreach ($OtherDiagnosis as $val) {
                foreach ($bfz as $v) {
                    if (strpos($val['ICD10_ID1'], $v) !== false) {
                        $bfzOtherNum = 1;
                        break;
                    }
                }
            }
            $bfzNum += $bfzOtherNum;


            $bfz = array_column($bfz, 'ICD10_ID1');

            $mainOperation = MainOperation::query()->where('AAA28', '=', data_get($item, 'MED_REC_ID'))->whereIn('ope_level', [3, 4])->get(['ope_level', 'ICD9_NAME'])->toArray();
            $secondaryOperation = SecondaryOperation::query()->where('AAA28', '=', data_get($item, 'MED_REC_ID'))->whereIn('ope_level', [3, 4])->get(['ope_level', 'ICD9_NAME'])->toArray();
            $operation = array_merge($mainOperation, $secondaryOperation);

            $opeLevel3 = 0;
            $opeLevel4 = 0;
            foreach ($operation as $val) {
                if ($val['ope_level'] == 3) {
                    $opeLevel3 += 1;
                } else if ($val['ope_level'] == 4) {
                    $opeLevel4 += 1;
                }
            }

            $errorMsg[0]['content'] = [];
            echo 'bfzNum：' . $bfzNum . '---' . $opeLevel3 . '---' . $opeLevel4 . PHP_EOL;
            $insert['sjssysjssbfzfs_fm'] = $opeLevel3 > 0 ? round($bfzNum / $opeLevel3, 2) : 0;
            $insert['sjssysjssbfzfs_fz'] = $opeLevel4 > 0 ? round($bfzNum / $opeLevel4, 2) : 0;
            $insert['sjssysjssbfzfs_error'] = !empty($errorMsg) ? json_encode($errorMsg, 256) : '';
            Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
        }

        $this->UpdateIndexCataLog('sjssysjssbfzfs', 2, time());
    }

    /**
     * 四级手术与三级手术死亡率比
     */
    public function handleSjssysjsshzswl($data)
    {
        $carbon = new Carbon();
        foreach ($data as $key => $item) {
            if ($item['IS_CATA'] != 1 || $item['AEM01C'] != 5) {
                continue;
            }
            echo $item['MED_REC_ID'] . PHP_EOL;
            $insert = [
                'zyh' => data_get($item, 'MED_REC_ID'),
                'sjssysjssswlb_fz' => 0,
                'sjssysjssswlb_fm' => 0,
                'sjssysjssswlb_error' => '',
                'AAC11N' => data_get($item, 'AAC11N'),
                'AEE03' => data_get($item, 'AEE03', ''),
                'AAC01_YEAR' => $carbon::parse(data_get($item, 'AAC01'))->format("Y"),
                'AAC01_MONTH' => $carbon::parse(data_get($item, 'AAC01'))->format("m"),
                'AAC01' => data_get($item, 'AAC01')
            ];
            $operation = [];
            $mainOperation = MainOperation::query()->where('AAA28', '=', data_get($item, 'MED_REC_ID'))->whereIn('ope_level', [3, 4])->get(['ope_level', 'ICD9_NAME'])->toArray();
            $secondaryOperation = SecondaryOperation::query()->where('AAA28', '=', data_get($item, 'MED_REC_ID'))->whereIn('ope_level', [3, 4])->get(['ope_level', 'ICD9_NAME'])->toArray();
            $operation = array_merge($mainOperation, $secondaryOperation);
            echo '手术台次：' . count($operation) . PHP_EOL;
            if (!$operation) {
                continue; // 没有记录，跳过
            }
            $content = [];
            foreach ($operation as $k => $items) {
                // 按满足条件的数量增加分子分母的数量
                if (isset($items['ope_level']) && $items['ope_level'] == 3 && $item['AEM01C'] == 5) {
                    $insert['sjssysjssswlb_fm'] = $insert['sjssysjssswlb_fm'] + 1;
                    $content[] = ["status" => 1, "content" => "三级手术名称【" . $items['ICD9_NAME'] . "】"];
                } else if (isset($items['ope_level']) && $items['ope_level'] == 4 && $item['AEM01C'] == 5) {
                    $insert['sjssysjssswlb_fz'] = $insert['sjssysjssswlb_fz'] + 1;
                    $content[] = ["status" => 1, "content" => "四级手术名称【" . $items['ICD9_NAME'] . "】"];
                }
            }
            if (!empty($content)) {
                $status = array_unique(array_column($content, 'status'));
                if (in_array(0, $status)) {
                    $errorMsg[] = ["status" => 0, "content" => $content];
                } else {
                    $errorMsg[] = ["status" => 1, "content" => $content];
                }
            } else {
                $errorMsg = [
                    [
                        'status' => 0,
                        'content' => [
                            ['status' => 0, 'content' => "【当前不存在四级手术与三级手术死亡率比】"]
                        ]
                    ]
                ];
            }
            // $insert['sjssysjssbfzfs_fz'] = $insert['sjssysjssbfzfs_fz'] > $insert['sjssysjssbfzfs_fm'] ? $insert['sjssysjssbfzfs_fm'] : $insert['sjssysjssbfzfs_fz'];
            $insert['sjssysjssswlb_error'] = !empty($errorMsg) ? json_encode($errorMsg, 256) : '';
            Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
        }

        $this->UpdateIndexCataLog('sjssysjssswlb', 2, time());
    }

    /**
     * 三级手术并发症发生率
     */
    public function handleSjssbfzfsl($data)
    {
        $this->UpdateIndexCataLog('sjssbfz', 1, time());
        $carbon = new Carbon();
        foreach ($data as $key => $item) {

            echo $item['MED_REC_ID'] . PHP_EOL;
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['sjssbfz_fz' => 0, 'sjssbfz_fm' => 0, 'sjssbfz_error' => null]);
            $insert = [
                'zyh' => data_get($item, 'MED_REC_ID'),
                'sjssbfz_fz' => 0,
                'sjssbfz_fm' => 0,
                'sjssbfz_error' => null,
                'AAC11N' => data_get($item, 'AAC11N'),
                'AEE03' => data_get($item, 'AEE03', ''),
                'AAC01_YEAR' => $carbon::parse(data_get($item, 'AAC01'))->format("Y"),
                'AAC01_MONTH' => $carbon::parse(data_get($item, 'AAC01'))->format("m"),
                'AAC01' => data_get($item, 'AAC01')
            ];

            // 并发症编码（T80-T84）
            $bfz = ['T80', 'T81', 'T82', 'T83', 'T84'];

            // 获取三级手术
            $mainOperation = MainOperation::query()->where('AAA28', '=', data_get($item, 'MED_REC_ID'))->where('ope_level', 3)->get(['ope_level', 'ICD9_NAME', 'ICD9_ID1'])->toArray();
            $secondaryOperation = SecondaryOperation::query()->where('AAA28', '=', data_get($item, 'MED_REC_ID'))->where('ope_level', 3)->get(['ope_level', 'ICD9_NAME', 'ICD9_ID1'])->toArray();
            $operation = array_merge($mainOperation, $secondaryOperation);

            echo '三级手术台次：' . count($operation) . PHP_EOL;
            if (empty($operation)) {
                continue; // 没有三级手术，跳过
            }

            // 分母：按患者计算，有三级手术就算1个分母
            $insert['sjssbfz_fm'] = 1;

            // 获取主诊断和其他诊断
            $mainDiagnosis = MainDiagnosis::query()->where('AAA28', '=', data_get($item, 'MED_REC_ID'))->get(['ICD10_ID1', 'ICD10_NAME'])->toArray();
            $otherDiagnosis = OtherDiagnosis::query()->where('AAA28', '=', data_get($item, 'MED_REC_ID'))->get(['ICD10_ID1', 'ICD10_NAME'])->toArray();
            $allDiagnosis = array_merge($mainDiagnosis, $otherDiagnosis);

            $errorMsg = [];
            $hasComplication = false; // 是否有并发症
            $complicationDiagnosis = [];
            $nonComplicationDiagnosis = [];

            // 检查是否有并发症诊断
            foreach ($allDiagnosis as $diagnosis) {
                $diagnosisCode = $diagnosis['ICD10_ID1'] ?? '';
                $diagnosisName = $diagnosis['ICD10_NAME'] ?? '';
                $isComplication = false;

                // 判断是否为并发症编码（前三位匹配T80-T84）
                foreach ($bfz as $bfzCode) {
                    if (strpos($diagnosisCode, $bfzCode) !== false) {
                        $isComplication = true;
                        $hasComplication = true;
                        break;
                    }
                }

                if ($isComplication) {
                    $complicationDiagnosis[] = [
                        'code' => $diagnosisCode,
                        'name' => $diagnosisName
                    ];
                } else {
                    $nonComplicationDiagnosis[] = [
                        'code' => $diagnosisCode,
                        'name' => $diagnosisName
                    ];
                }
            }

            // 分子：按患者计算，有三级手术且诊断编码符合并发症编码就算1个分子
            if ($hasComplication) {
                $insert['sjssbfz_fz'] = 1;
            }

            // 构建显示信息
            $surgeryGroup = [
                'status' => $hasComplication ? 1 : 0,
                'content' => []
            ];

            // 添加所有三级手术信息
            foreach ($operation as $opItem) {
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "手术名称【" . ($opItem['ICD9_NAME'] ?? '') . "】"
                ];
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "手术级别【3级】"
                ];
            }

            // 添加诊断信息
            if (!empty($complicationDiagnosis)) {
                foreach ($complicationDiagnosis as $comp) {
                    $surgeryGroup['content'][] = [
                        'status' => 1,
                        'content' => "诊断名称【" . $comp['name'] . "】"
                    ];
                    $surgeryGroup['content'][] = [
                        'status' => 1,
                        'content' => "诊断编码【" . $comp['code'] . "】（属于术后并发症）"
                    ];
                }
            }

            if (!empty($nonComplicationDiagnosis) && empty($complicationDiagnosis)) {
                foreach ($nonComplicationDiagnosis as $nonComp) {
                    $surgeryGroup['content'][] = [
                        'status' => 0,
                        'content' => "诊断编码【" . $nonComp['code'] . "】（不是术后并发症）"
                    ];
                }
            }

            // 如果没有诊断信息
            if (empty($complicationDiagnosis) && empty($nonComplicationDiagnosis)) {
                $surgeryGroup['content'][] = [
                    'status' => 0,
                    'content' => "诊断信息【无】"
                ];
            }

            $errorMsg[] = $surgeryGroup;

            $insert['sjssbfz_error'] = !empty($errorMsg) ? json_encode($errorMsg, JSON_UNESCAPED_UNICODE) : '';
            Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
        }

        $this->UpdateIndexCataLog('sjssbfz', 2, time());
    }


    /**
     * 四级手术并发症发生率
     */
    public function handleSijssbfzfsl($data)
    {
        $this->UpdateIndexCataLog('sijssbfz', 1, time());
        $carbon = new Carbon();
        foreach ($data as $key => $item) {
            echo $item['MED_REC_ID'] . PHP_EOL;
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['sijssbfz_fz' => 0, 'sijssbfz_fm' => 0, 'sijssbfz_error' => null]);
            $insert = [
                'zyh' => data_get($item, 'MED_REC_ID'),
                'sijssbfz_fz' => 0,
                'sijssbfz_fm' => 0,
                'sijssbfz_error' => null,
                'AAC11N' => data_get($item, 'AAC11N'),
                'AEE03' => data_get($item, 'AEE03', ''),
                'AAC01_YEAR' => $carbon::parse(data_get($item, 'AAC01'))->format("Y"),
                'AAC01_MONTH' => $carbon::parse(data_get($item, 'AAC01'))->format("m"),
                'AAC01' => data_get($item, 'AAC01')
            ];

            // 并发症编码（T80-T84）
            $bfz = ['T80', 'T81', 'T82', 'T83', 'T84'];

            // 获取三级手术
            $mainOperation = MainOperation::query()->where('AAA28', '=', data_get($item, 'MED_REC_ID'))->where('ope_level', 4)->get(['ope_level', 'ICD9_NAME', 'ICD9_ID1'])->toArray();
            $secondaryOperation = SecondaryOperation::query()->where('AAA28', '=', data_get($item, 'MED_REC_ID'))->where('ope_level', 4)->get(['ope_level', 'ICD9_NAME', 'ICD9_ID1'])->toArray();
            $operation = array_merge($mainOperation, $secondaryOperation);

            echo '四级手术台次：' . count($operation) . PHP_EOL;
            if (empty($operation)) {
                continue; // 没有四级手术，跳过
            }

            // 分母：按患者计算，有四级手术就算1个分母
            $insert['sijssbfz_fm'] = 1;

            // 获取主诊断和其他诊断
            $mainDiagnosis = MainDiagnosis::query()->where('AAA28', '=', data_get($item, 'MED_REC_ID'))->get(['ICD10_ID1', 'ICD10_NAME'])->toArray();
            $otherDiagnosis = OtherDiagnosis::query()->where('AAA28', '=', data_get($item, 'MED_REC_ID'))->get(['ICD10_ID1', 'ICD10_NAME'])->toArray();
            $allDiagnosis = array_merge($mainDiagnosis, $otherDiagnosis);

            $errorMsg = [];
            $hasComplication = false; // 是否有并发症
            $complicationDiagnosis = [];
            $nonComplicationDiagnosis = [];

            // 检查是否有并发症诊断
            foreach ($allDiagnosis as $diagnosis) {
                $diagnosisCode = $diagnosis['ICD10_ID1'] ?? '';
                $diagnosisName = $diagnosis['ICD10_NAME'] ?? '';
                $isComplication = false;

                // 判断是否为并发症编码（前三位匹配T80-T84）
                foreach ($bfz as $bfzCode) {
                    if (strpos($diagnosisCode, $bfzCode) !== false) {
                        $isComplication = true;
                        $hasComplication = true;
                        break;
                    }
                }

                if ($isComplication) {
                    $complicationDiagnosis[] = [
                        'code' => $diagnosisCode,
                        'name' => $diagnosisName
                    ];
                } else {
                    $nonComplicationDiagnosis[] = [
                        'code' => $diagnosisCode,
                        'name' => $diagnosisName
                    ];
                }
            }

            // 分子：按患者计算，有四级手术且诊断编码符合并发症编码就算1个分子
            if ($hasComplication) {
                $insert['sijssbfz_fz'] = 1;
            }

            // 构建显示信息
            $surgeryGroup = [
                'status' => $hasComplication ? 1 : 0,
                'content' => []
            ];

            // 添加所有四级手术信息
            foreach ($operation as $opItem) {
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "手术名称【" . ($opItem['ICD9_NAME'] ?? '') . "】"
                ];
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "手术级别【4级】"
                ];
            }

            // 添加诊断信息
            if (!empty($complicationDiagnosis)) {
                foreach ($complicationDiagnosis as $comp) {
                    $surgeryGroup['content'][] = [
                        'status' => 1,
                        'content' => "诊断名称【" . $comp['name'] . "】"
                    ];
                    $surgeryGroup['content'][] = [
                        'status' => 1,
                        'content' => "诊断编码【" . $comp['code'] . "】（属于术后并发症）"
                    ];
                }
            }

            if (!empty($nonComplicationDiagnosis) && empty($complicationDiagnosis)) {
                foreach ($nonComplicationDiagnosis as $nonComp) {
                    $surgeryGroup['content'][] = [
                        'status' => 0,
                        'content' => "诊断编码【" . $nonComp['code'] . "】（不是术后并发症）"
                    ];
                }
            }

            // 如果没有诊断信息
            if (empty($complicationDiagnosis) && empty($nonComplicationDiagnosis)) {
                $surgeryGroup['content'][] = [
                    'status' => 0,
                    'content' => "诊断信息【无】"
                ];
            }

            $errorMsg[] = $surgeryGroup;

            $insert['sijssbfz_error'] = !empty($errorMsg) ? json_encode($errorMsg, JSON_UNESCAPED_UNICODE) : '';
            Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
        }

        $this->UpdateIndexCataLog('sijssbfz', 2, time());
    }

    /**
     * 三级手术死亡
     */
    public function handleSjsssw($data)
    {
        $this->UpdateIndexCataLog('sjsssw', 1, time());
        $carbon = new Carbon();

        // 获取死亡医嘱关键字
        $ruleMap8003 = RuleWordMap::query()->where('id', '=', 8003)->value('keyword');
        $ruleMap8003 = !empty($ruleMap8003) ? $ruleMap8003 : "死亡";
        // 判断是否包含逗号
        if (strpos($ruleMap8003, ',') !== false) {
            $deathKeywords = explode(',', $ruleMap8003);
        } else {
            $deathKeywords = [$ruleMap8003];
        }

        foreach ($data as $key => $item) {
            echo $item['MED_REC_ID'] . PHP_EOL;
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['sjsssw_fz' => 0, 'sjsssw_fm' => 0, 'sjsssw_error' => null]);
            $insert = [
                'zyh' => data_get($item, 'MED_REC_ID'),
                'sjsssw_fz' => 0,
                'sjsssw_fm' => 0,
                'sjsssw_error' => null,
                'AAC11N' => data_get($item, 'AAC11N'),
                'AEE03' => data_get($item, 'AEE03', ''),
                'AAC01_YEAR' => $carbon::parse(data_get($item, 'AAC01'))->format("Y"),
                'AAC01_MONTH' => $carbon::parse(data_get($item, 'AAC01'))->format("m"),
                'AAC01' => data_get($item, 'AAC01')
            ];

            // 获取三级手术
            $mainOperation = MainOperation::query()->where('AAA28', '=', data_get($item, 'MED_REC_ID'))->where('ope_level', 3)->get(['ope_level', 'ICD9_NAME', 'ICD9_ID1'])->toArray();
            $secondaryOperation = SecondaryOperation::query()->where('AAA28', '=', data_get($item, 'MED_REC_ID'))->where('ope_level', 3)->get(['ope_level', 'ICD9_NAME', 'ICD9_ID1'])->toArray();
            $operation = array_merge($mainOperation, $secondaryOperation);

            echo '三级手术台次：' . count($operation) . PHP_EOL;
            if (empty($operation)) {
                continue; // 没有三级手术，跳过
            }

            // 分母：按患者计算，有三级手术就算1个分母
            $insert['sjsssw_fm'] = 1;

            // 查询医嘱中是否包含死亡关键字
            $yzbQuery = Yzb::query()->where('ZYH', '=', data_get($item, 'MED_REC_ID'));
            $yzbQuery->where(function ($query) use ($deathKeywords) {
                foreach ($deathKeywords as $keyword) {
                    $query->orWhere('YZMC', 'like', '%' . trim($keyword) . '%');
                }
            });
            $yzbData = $yzbQuery->get(['YZMC'])->toArray();

            $errorMsg = [];
            $hasDeathOrder = false; // 是否有死亡医嘱

            // 如果有死亡医嘱，分子+1
            if (!empty($yzbData)) {
                $hasDeathOrder = true;
                $insert['sjsssw_fz'] = 1;
            }

            // 构建显示信息
            $surgeryGroup = [
                'status' => $hasDeathOrder ? 1 : 0,
                'content' => []
            ];

            // 添加所有三级手术信息
            foreach ($operation as $opItem) {
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "手术名称【" . ($opItem['ICD9_NAME'] ?? '') . "】"
                ];
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "手术级别【3级】"
                ];
            }

            // 添加死亡医嘱信息
            if ($hasDeathOrder && !empty($yzbData)) {
                foreach ($yzbData as $yzb) {
                    $surgeryGroup['content'][] = [
                        'status' => 1,
                        'content' => "医嘱名称【" . ($yzb['YZMC'] ?? '') . "】（死亡患者）"
                    ];
                }
            } else {
                $surgeryGroup['content'][] = [
                    'status' => 0,
                    'content' => "医嘱名称【无死亡医嘱】"
                ];
            }

            $errorMsg[] = $surgeryGroup;

            $insert['sjsssw_error'] = !empty($errorMsg) ? json_encode($errorMsg, JSON_UNESCAPED_UNICODE) : '';
            Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
        }

        $this->UpdateIndexCataLog('sjsssw', 2, time());
    }

    /**
     * 四级手术死亡
     */
    public function handleSijsssw($data)
    {
        $this->UpdateIndexCataLog('sijsssw', 1, time());
        $carbon = new Carbon();

        // 获取死亡医嘱关键字
        $ruleMap8003 = RuleWordMap::query()->where('id', '=', 8003)->value('keyword');
        $ruleMap8003 = !empty($ruleMap8003) ? $ruleMap8003 : "死亡";
        // 判断是否包含逗号
        if (strpos($ruleMap8003, ',') !== false) {
            $deathKeywords = explode(',', $ruleMap8003);
        } else {
            $deathKeywords = [$ruleMap8003];
        }

        foreach ($data as $key => $item) {
            echo $item['MED_REC_ID'] . PHP_EOL;
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['sijsssw_fz' => 0, 'sijsssw_fm' => 0, 'sijsssw_error' => null]);
            $insert = [
                'zyh' => data_get($item, 'MED_REC_ID'),
                'sijsssw_fz' => 0,
                'sijsssw_fm' => 0,
                'sijsssw_error' => null,
                'AAC11N' => data_get($item, 'AAC11N'),
                'AEE03' => data_get($item, 'AEE03', ''),
                'AAC01_YEAR' => $carbon::parse(data_get($item, 'AAC01'))->format("Y"),
                'AAC01_MONTH' => $carbon::parse(data_get($item, 'AAC01'))->format("m"),
                'AAC01' => data_get($item, 'AAC01')
            ];

            // 获取四级手术
            $mainOperation = MainOperation::query()->where('AAA28', '=', data_get($item, 'MED_REC_ID'))->where('ope_level', 4)->get(['ope_level', 'ICD9_NAME', 'ICD9_ID1'])->toArray();
            $secondaryOperation = SecondaryOperation::query()->where('AAA28', '=', data_get($item, 'MED_REC_ID'))->where('ope_level', 4)->get(['ope_level', 'ICD9_NAME', 'ICD9_ID1'])->toArray();
            $operation = array_merge($mainOperation, $secondaryOperation);

            echo '四级手术台次：' . count($operation) . PHP_EOL;
            if (empty($operation)) {
                continue; // 没有四级手术，跳过
            }

            // 分母：按患者计算，有四级手术就算1个分母
            $insert['sijsssw_fm'] = 1;

            // 查询医嘱中是否包含死亡关键字
            $yzbQuery = Yzb::query()->where('ZYH', '=', data_get($item, 'MED_REC_ID'));
            $yzbQuery->where(function ($query) use ($deathKeywords) {
                foreach ($deathKeywords as $keyword) {
                    $query->orWhere('YZMC', 'like', '%' . trim($keyword) . '%');
                }
            });
            $yzbData = $yzbQuery->get(['YZMC'])->toArray();

            $errorMsg = [];
            $hasDeathOrder = false; // 是否有死亡医嘱

            // 如果有死亡医嘱，分子+1
            if (!empty($yzbData)) {
                $hasDeathOrder = true;
                $insert['sijsssw_fz'] = 1;
            }

            // 构建显示信息
            $surgeryGroup = [
                'status' => $hasDeathOrder ? 1 : 0,
                'content' => []
            ];

            // 添加所有四级手术信息
            foreach ($operation as $opItem) {
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "手术名称【" . ($opItem['ICD9_NAME'] ?? '') . "】"
                ];
                $surgeryGroup['content'][] = [
                    'status' => 1,
                    'content' => "手术级别【4级】"
                ];
            }

            // 添加死亡医嘱信息
            if ($hasDeathOrder && !empty($yzbData)) {
                foreach ($yzbData as $yzb) {
                    $surgeryGroup['content'][] = [
                        'status' => 1,
                        'content' => "医嘱名称【" . ($yzb['YZMC'] ?? '') . "】（死亡患者）"
                    ];
                }
            } else {
                $surgeryGroup['content'][] = [
                    'status' => 0,
                    'content' => "医嘱名称【无死亡医嘱】"
                ];
            }

            $errorMsg[] = $surgeryGroup;

            $insert['sijsssw_error'] = !empty($errorMsg) ? json_encode($errorMsg, JSON_UNESCAPED_UNICODE) : '';
            Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
        }

        $this->UpdateIndexCataLog('sijsssw', 2, time());
    }

    /**
     * 三、四级手术实际开展率
     */
    public function handleSjsjsssjkzl($data)
    {
        $carbon = new Carbon();
        // 同期本机构三、四级手术术种数
        $fm = DB::select("SELECT * FROM surgical_catalog WHERE OPE_LEVEL IN (3,4)");
        $fm = json_decode(json_encode($fm), true);
        $ICD9_ID1 = array_column($fm, 'ICD9_ID1');
        foreach ($data as $item) {
            $insert = [
                'zyh' => data_get($item, 'MED_REC_ID'),
                'sjsjsssjkzl_fz' => 0,
                'sjsjsssjkzl_fm' => count($fm),
                'sjsjsssjkzl_error' => '',
                'AAC11N' => data_get($item, 'AAC11N'),
                'AEE03' => data_get($item, 'AEE03', ''),
                'AAC01_YEAR' => $carbon::parse(data_get($item, 'AAC01'))->format("Y"),
                'AAC01_MONTH' => $carbon::parse(data_get($item, 'AAC01'))->format("m"),
                'AAC01' => data_get($item, 'AAC01')
            ];

            // 实际开展的三、四级手术术种数
            $mainOperation = MainOperation::query()->where('AAA28', '=', $item['MED_REC_ID'])->whereIn('ope_level', [3, 4])->get(['ICD9_ID1', 'ICD9_NAME', 'ope_level'])->toArray();
            $secondaryOperation = SecondaryOperation::query()->where('AAA28', '=', $item['MED_REC_ID'])->whereIn('ope_level', [3, 4])->get(['ICD9_ID1', 'ICD9_NAME', 'ope_level'])->toArray();
            $fz = array_merge($mainOperation, $secondaryOperation);
            echo '分子：' . count($fz) . PHP_EOL;
            if (!$fz) {
                continue; // 没有记录，跳过
            }
            foreach ($fz as $val) {
                if (in_array($val['ICD9_ID1'], $ICD9_ID1)) {
                    $insert['sjsjsssjkzl_fz'] += 1;

                    $content = [];
                    $content[] = ["status" => 1, "content" => "手术名称【" . $val['ICD9_NAME'] . "】"];
                    $content[] = ["status" => 1, "content" => "手术编码【" . $val['ICD9_ID1'] . "】"];
                    $content[] = ["status" => 1, "content" => "手术等级【" . $val['ope_level'] . "】"];
                    if (!empty($content)) {
                        $status = array_unique(array_column($content, 'status'));
                        if (in_array(0, $status)) {
                            $errorMsg[] = ["status" => 0, "content" => $content];
                        } else {
                            $errorMsg[] = ["status" => 1, "content" => $content];
                        }
                    } else {
                        $errorMsg = [
                            [
                                'status' => 0,
                                'content' => [
                                    ['status' => 0, 'content' => "【当前不存在三、四级手术实际开展率】"]
                                ]
                            ]
                        ];
                    }
                }
            }
            $insert['sjsjsssjkzl_error'] = !empty($errorMsg) ? json_encode($errorMsg, 256) : '';
            Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
        }

        $this->UpdateIndexCataLog('sjsjsssjkzl', 2, time());
    }

    /**
     * 指标：术前非计划再次手术完成疑难病历讨论率
     * @param mixed $data
     * @return void
     */
    public function handleNewSqfjhzssbltl($data)
    {
        $BL01ESServer = new ElasticsearchService('bl01_202303');
        $this->UpdateIndexCataLog('sqfjhzssbltl', 1, time());
        foreach ($data as $item) {
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['sqfjhzssbltl_fz' => 0, 'sqfjhzssbltl_fm' => 0, 'sqfjhzssbltl_error' => null]);
            /**
             * 日间病历排除
             */
            if (strtotime($item['AAC01']) - strtotime($item['AAB01']) <= 86400) {
                continue;
            }
            $insert = [
                'zyh' => $item['MED_REC_ID'],
                'sqfjhzssbltl_fz' => 0,
                'sqfjhzssbltl_fm' => 0,
                'sqfjhzssbltl_error' => '',
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($item['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($item['AAC01'])),
                'AAC01' => $item['AAC01']
            ];
            $error = [];

            // 查询SM_SSAP表，获取该患者的所有手术记录，按手术开始时间排序
            $ssapData = SM_SSAP::query()
                ->where('ZYH', $item['MED_REC_ID'])
                ->whereNotNull('SSRQ')
                ->whereNotNull('JSRQ')
                ->where('SSRQ', '<>', '')
                ->where('JSRQ', '<>', '')
                ->orderBy('SSRQ', 'asc')
                ->get()
                ->toArray();

            if (empty($ssapData) || count($ssapData) == 0) {
                continue;
            }

            // 判断手术时间段是否重合，将重合的手术归为一组
            $surgeryGroups = [];
            foreach ($ssapData as $surgery) {
                $ssrq = strtotime($surgery['SSRQ']);
                $jsrq = strtotime($surgery['JSRQ']);

                // 查找是否与现有组有重合
                $foundGroup = false;
                foreach ($surgeryGroups as &$group) {
                    // 判断当前手术与组内任何手术是否重合
                    foreach ($group['surgeries'] as $groupSurgery) {
                        $groupStart = strtotime($groupSurgery['SSRQ']);
                        $groupEnd = strtotime($groupSurgery['JSRQ']);

                        // 判断时间段是否重合：当前手术开始时间在已有手术时间段内，或当前手术结束时间在已有手术时间段内，或当前手术完全包含已有手术
                        if (($ssrq >= $groupStart && $ssrq <= $groupEnd) ||
                            ($jsrq >= $groupStart && $jsrq <= $groupEnd) ||
                            ($ssrq <= $groupStart && $jsrq >= $groupEnd)
                        ) {
                            // 有重合，加入该组
                            $group['surgeries'][] = $surgery;
                            // 更新组的时间范围
                            $group['start'] = min($group['start'], $ssrq);
                            $group['end'] = max($group['end'], $jsrq);
                            $foundGroup = true;
                            break 2;
                        }
                    }
                }

                // 如果没有找到重合的组，创建新组
                if (!$foundGroup) {
                    $surgeryGroups[] = [
                        'surgeries' => [$surgery],
                        'start' => $ssrq,
                        'end' => $jsrq
                    ];
                }
            }
            unset($group); // 解除引用

            // 如果只有一组手术，说明没有多台非重合手术，跳过
            if (count($surgeryGroups) <= 1) {
                continue;
            }

            // 取最后一组作为非计划手术组
            $lastGroup = end($surgeryGroups);

            // 只取最后一组中的最后一台手术（按手术开始时间排序的最后一台）
            $lastSurgery = end($lastGroup['surgeries']);

            $content = [];
            $insert['sqfjhzssbltl_fm'] += 1;

            $ssmc = $lastSurgery['ICD9_SSCZMC'] ?? "";
            $SSRQ = $lastSurgery['SSRQ'] ?? "";
            $JSRQ = $lastSurgery['JSRQ'] ?? "";

            if (empty($SSRQ) || empty($JSRQ)) {
                continue;
            }

            $content[] = ["status" => 1, "content" => "(手麻)手术名称【" . $ssmc . "】(非计划再次手术-最后一台)"];
            $content[] = ["status" => 1, "content" => "(手麻)手术开始时间【" . $SSRQ . "】"];
            $content[] = ["status" => 1, "content" => "(手麻)手术结束时间【" . $JSRQ . "】"];

            // 查询疑难病历讨论记录（手术开始时间前48小时到结束时间后72小时）
            $kzsjStart = date('Y-m-d H:i:s', strtotime($SSRQ) - 48 * 3600);
            $kzsjEnd = date('Y-m-d H:i:s', strtotime($JSRQ) + 72 * 3600);
            $must = [
                ['term' => ['JZHM' => $item['MED_REC_ID']]],
                ['term' => ['BLLB' => 43]],
                ['term' => ['MBLB' => $this->ruleMap1045]],
                ['range' => ['ZXSJ' => ['gte' => $kzsjStart, 'lte' => $kzsjEnd]]]
            ];
            $mustNot = ['term' => ['BLZT' => $this->ruleMap2036]];
            $params = $BL01ESServer->clearMust()->queryByMustBatch($must)->queryByMustNot($mustNot)->getParams();
            $res = app('es')->search($params);
            $bl01Data = $BL01ESServer->getDataByEs($res);

            if (!empty($bl01Data[1])) {
                $firstTime = $bl01Data[0][0]['ZXSJ'] ?? "";
                if (!empty($firstTime)) {
                    if (strtotime($firstTime) >= strtotime($kzsjStart) && strtotime($firstTime) <= strtotime($kzsjEnd)) {
                        $insert['sqfjhzssbltl_fz'] += 1;
                        $content[] = ["status" => 1, "content" => "疑难病历讨论记录【" . $bl01Data[0][0]['BLMC'] . "】"];
                        $content[] = ["status" => 1, "content" => "首次签名时间【" . $firstTime . "】"];
                    } else if (strtotime($firstTime) < strtotime($kzsjStart)) {
                        $content[] = ["status" => 0, "content" => "疑难病历讨论记录【" . $bl01Data[0][0]['BLMC'] . "】"];
                        $content[] = ["status" => 0, "content" => "首次签名时间【" . $firstTime . "】(提前)"];
                    } else if (strtotime($firstTime) > strtotime($kzsjEnd)) {
                        $content[] = ["status" => 0, "content" => "疑难病历讨论记录【" . $bl01Data[0][0]['BLMC'] . "】"];
                        $content[] = ["status" => 0, "content" => "首次签名时间【" . $firstTime . "】(超时)"];
                    }
                } else {
                    $content[] = ["status" => 0, "content" => "疑难病历讨论记录【" . $bl01Data[0][0]['BLMC'] . "】"];
                    $content[] = ["status" => 0, "content" => "首次签名时间【无】"];
                }
            } else {
                $content[] = ['status' => 0, 'content' => "疑难病历讨论记录【无】"];
            }

            if (!empty($content)) {
                $status2 = array_unique(array_column($content, 'status'));
                if (!in_array(0, $status2)) {
                    $error[] = ["status" => 1, "content" => $content];
                } else {
                    $error[] = ["status" => 0, "content" => $content];
                }
            }

            if (!empty($error)) {
                $insert['sqfjhzssbltl_error'] = !empty($error) ? json_encode($error, 256) : '';
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }
        $this->UpdateIndexCataLog('sqfjhzssbltl', 2, time());
    }

    /**
     * 指标：非计划再次入院完成疑难病历讨论率
     * @param mixed $data
     * @return void
     */
    public function handleNewShfjhzssbltl($data)
    {
        $this->UpdateIndexCataLog('shfjhzssbltl', 1, time());
        foreach ($data as $item) {
            //删除
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['shfjhzssbltl_fz' => 0, 'shfjhzssbltl_fm' => 0, 'shfjhzssbltl_error' => null]);
            $insert = [
                'zyh' => $item['MED_REC_ID'],
                'shfjhzssbltl_fz' => 0,
                'shfjhzssbltl_fm' => 0,
                'shfjhzssbltl_error' => null,
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($item['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($item['AAC01'])),
                'AAC01' => $item['AAC01']
            ];
            $error = [];
            //查询诊断
            $mainDiagnosis = MainDiagnosis::query()->where('AAA28', $item['MED_REC_ID'])->get()->toArray();
            $otherDiagnosis = OtherDiagnosis::query()->where('AAA28', $item['MED_REC_ID'])->get()->toArray();
            $diagnosis = array_merge($mainDiagnosis, $otherDiagnosis);
            if (empty($diagnosis)) {
                continue;
            }
            $iszd = false;
            foreach ($diagnosis as $value) {
                $diagnosisName = $value['ICD10_ID1'];
                //提取前5位字符比如Z50.122，提取Z50.1，带小数点
                $diagnosisName = substr($diagnosisName, 0, 5);
                //var_dump($diagnosisName);
                if ($diagnosisName == 'Z51.0' || $diagnosisName == 'Z51.1' || $diagnosisName == 'Z51.2' || $diagnosisName == 'Z50.8') {
                    $iszd = true;
                    break;
                }
            }
            if ($iszd) {
                continue;
            }
            //住院次数
            $zycs = data_get($item, 'AAA29', 0);
            if ($zycs == 1 || empty($zycs)) {
                continue;
            }

            //上次住院次数
            $sczycs = $zycs - 1;

            if ($sczycs <= 0) {
                continue;
            }
            $AAA28 = data_get($item, 'AAA28', '');
            if (empty($AAA28)) {
                continue;
            }
            //查询上次住院记录
            $sczyData = PatientInfo::query()->where('AAA28', $AAA28)->where('AAA29', $sczycs)->get()->toArray();
            if (empty($sczyData)) {
                continue;
            }
            $sczyh = $sczyData[0]['MED_REC_ID'] ?? '';
            if (empty($sczyh)) {
                continue;
            }
            $hosdata = PatientHospitalInfo::query()->where('AAA28', $sczyh)->get()->toArray();
            if (empty($hosdata)) {
                continue;
            }

            $zzyjh = $hosdata[0]['AEM03C'] ?? '';

            if ($zzyjh == '2') {
                continue;
            }

            //现在主要诊断编码
            $nowDiagnosisName = $mainDiagnosis[0]['ICD10_ID1'] ?? '';
            if (empty($nowDiagnosisName)) {
                continue;
            }

            //上次主要诊断名称
            $sczyDiagnosis = MainDiagnosis::query()->where('AAA28', $sczyh)->get()->toArray();
            if (empty($sczyDiagnosis)) {
                continue;
            }
            $sczyDiagnosisName = $sczyDiagnosis[0]['ICD10_ID1'];

            //都提取前5位字符比如Z50.122，提取Z50.1，带小数点
            $nowDiagnosisName = substr($nowDiagnosisName, 0, 5);
            $sczyDiagnosisName = substr($sczyDiagnosisName, 0, 5);

            if ($nowDiagnosisName != $sczyDiagnosisName) {
                continue;
            }

            //分母+1
            $insert['shfjhzssbltl_fm'] += 1;

            //查询是否有是否有疑难病例讨论记录
            $must = [
                ['term' => ['JZHM' => $item['MED_REC_ID']]],
                ['term' => ['BLLB' => 43]],
                ['term' => ['MBLB' => $this->ruleMap1045]]
            ];
            $BL01ESServer = new ElasticsearchService('bl01_202303');
            $mustNot = ['term' => ['BLZT' => $this->ruleMap2036]];
            $params = $BL01ESServer->clearMust()->queryByMustBatch($must)->queryByMustNot($mustNot)->getParams();
            $res = app('es')->search($params);
            $bl01Data = $BL01ESServer->getDataByEs($res);
            $content = [];
            //当前住院次数
            $content[] = ["status" => 1, "content" => "当前住院次数【" . $zycs . "】"];

            //前一次住院31天再住院计划
            if ($zzyjh == '1') {
                $zzyjh = '无';
            } else if (empty($zzyjh)) {
                $zzyjh = '空';
            }
            $content[] = ["status" => 1, "content" => "(前一次)住院31天再住院计划【" . $zzyjh . "】"];
            //主要诊断编码
            $content[] = ["status" => 1, "content" => "主要诊断编码【" . $mainDiagnosis[0]['ICD10_ID1'] . "】"];
            //主要诊断名称
            $content[] = ["status" => 1, "content" => "主要诊断名称【" . $mainDiagnosis[0]['ICD10_NAME'] . "】"];
            //上次主要诊断编码
            $content[] = ["status" => 1, "content" => "(前一次)主要诊断编码【" . $sczyDiagnosis[0]['ICD10_ID1'] . "】"];
            //上次主要诊断名称
            $content[] = ["status" => 1, "content" => "(前一次)主要诊断名称【" . $sczyDiagnosis[0]['ICD10_NAME'] . "】"];
            if (!empty($bl01Data[1])) {
                $insert['shfjhzssbltl_fz'] += 1;
                $content[] = ["status" => 1, "content" => "疑难病历讨论记录【" . $bl01Data[0][0]['BLMC'] . "】"];
            } else {
                $content[] = ["status" => 0, "content" => "疑难病历讨论记录【无】"];
            }

            if (!empty($content)) {
                $status2 = array_unique(array_column($content, 'status'));
                if (!in_array(0, $status2)) {
                    $error[] = ["status" => 1, "content" => $content];
                } else {
                    $error[] = ["status" => 0, "content" => $content];
                }
            }

            if (!empty($error)) {
                $insert['shfjhzssbltl_error'] = !empty($error) ? json_encode($error, 256) : '';
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }
        $this->UpdateIndexCataLog('shfjhzssbltl', 2, time());
    }

    /**
     * 指标：抗菌药物处方权落实合格率
     * @param mixed $data
     * @return void
     */
    public function handleKjywcfqlshg($data)
    {
        $this->UpdateIndexCataLog('kjywcfqlshg', 1, time());
        $carbon = new Carbon();

        // 获取配置
        $ruleMap2040 = RuleWordMap::query()->where('id', '=', 2040)->value('keyword');
        $ruleMap2035 = RuleWordMap::query()->where('id', '=', 2035)->value('keyword');
        $ruleMap2035 = !empty($ruleMap2035) ? $ruleMap2035 : $this->ruleMap2035;

        // 抗菌药级别映射
        $kjjb = [1 => '非限制使用级', 2 => '限制使用级', 3 => '特殊使用级'];
        // 权限编码映射
        $qxbm = [1 => '010106', 2 => '010107', 3 => '010108'];

        // 获取所有医师信息（工号 => 姓名）
        $staff = Staff::query()->get()->toArray();
        $staffMap = array_column($staff, 'name', 'code');

        foreach ($data as $item) {
            echo $item['MED_REC_ID'] . PHP_EOL;
            // 删除
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['kjywcfqlshg_fz' => 0, 'kjywcfqlshg_fm' => 0, 'kjywcfqlshg_error' => null]);

            $insert = [
                'zyh' => $item['MED_REC_ID'],
                'kjywcfqlshg_fz' => 0,
                'kjywcfqlshg_fm' => 0,
                'kjywcfqlshg_error' => null,
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => $carbon::parse($item['AAC01'])->format("Y"),
                'AAC01_MONTH' => $carbon::parse($item['AAC01'])->format("m"),
                'AAC01' => $item['AAC01']
            ];
            //先清洗yzb
            RadioService::filterField($item['MED_REC_ID']);

            // 查询抗菌药物医嘱
            $yzbQuery = Yzb::query()
                ->where('ZYH', '=', $item['MED_REC_ID'])
                ->where('is_has_kjyw', '=', 1)
                ->where('YDYZLB', '!=', 901)
                ->where('YZQX', '=', $ruleMap2040)
                ->where('YZZT', '<>', $ruleMap2035);

            $yzbData = $yzbQuery->get(['KZYS', 'KJJB', 'YZMC', 'KZSJ'])->toArray();

            if (empty($yzbData)) {
                continue; // 没有抗菌药物医嘱，跳过
            }

            // 获取所有开嘱医师的工号
            $kzysList = array_column($yzbData, 'KZYS');
            $kzysList = array_unique(array_filter($kzysList));

            // 查询这些医师的权限
            $ryqxData = [];
            if (!empty($kzysList)) {
                $ryqxData = RYQX::query()->whereIn('YSDM', $kzysList)->get()->toArray();
            }

            $errorMsg = [];

            // 处理每条抗菌药物医嘱
            foreach ($yzbData as $yzb) {
                $yzmc = $yzb['YZMC'] ?? '';
                $kzys = $yzb['KZYS'] ?? '';
                $kjjbValue = $yzb['KJJB'] ?? '';
                $kzsj = $yzb['KZSJ'] ?? '';

                // 分母：所有抗菌药物医嘱
                $insert['kjywcfqlshg_fm'] += 1;

                $yzbGroup = [
                    'status' => 0,
                    'content' => []
                ];

                // 添加医嘱信息
                $yzbGroup['content'][] = [
                    'status' => 1,
                    'content' => "医嘱名称【" . $yzmc . "】"
                ];

                // 添加开嘱医师信息
                $staffName = $staffMap[$kzys] ?? '';
                $yzbGroup['content'][] = [
                    'status' => 1,
                    'content' => "开嘱医师【" . ($staffName ?: '未知') . "（" . $kzys . "）】"
                ];

                // 检查是否有权限
                $hasPermission = false;
                $permissionName = '';

                if (!empty($kzys) && !empty($kjjbValue) && isset($kjjb[$kjjbValue]) && isset($qxbm[$kjjbValue])) {
                    // 查找该医师是否有对应级别的权限
                    foreach ($ryqxData as $ryqx) {
                        if ($ryqx['YSDM'] == $kzys && $ryqx['QXBM'] == $qxbm[$kjjbValue]) {
                            $hasPermission = true;
                            break;
                        }
                    }
                }

                // 添加抗菌药级别信息
                $kjjbName = $kjjb[$kjjbValue] ?? '未知';
                if ($hasPermission) {
                    // 有权限
                    $insert['kjywcfqlshg_fz'] += 1;
                    $yzbGroup['status'] = 1;
                    $yzbGroup['content'][] = [
                        'status' => 1,
                        'content' => "抗菌药级别【" . $kjjbName . "】（有权限）"
                    ];
                } else {
                    // 无权限
                    if ($kjjbValue == 9) {
                        // 未知级别
                        $yzbGroup['content'][] = [
                            'status' => 0,
                            'content' => "抗菌药级别【未知】（无权限）"
                        ];
                    } else {
                        $yzbGroup['content'][] = [
                            'status' => 0,
                            'content' => "抗菌药级别【" . $kjjbName . "】（无权限）"
                        ];
                    }
                }

                $errorMsg[] = $yzbGroup;
            }

            if (!empty($errorMsg)) {
                $insert['kjywcfqlshg_error'] = json_encode($errorMsg, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        $this->UpdateIndexCataLog('kjywcfqlshg', 2, time());
    }

    /**
     * 指标：特殊使用级抗菌药物会诊率
     * @param mixed $data
     * @return void
     */
    public function handleTsjkjywhz($data)
    {
        $this->UpdateIndexCataLog('tsjkjywhz', 1, time());
        $carbon = new Carbon();

        // 字段映射与配置
        $ruleMap2035 = RuleWordMap::query()->where('id', '=', 2035)->value('keyword'); // 医嘱状态-作废
        $ruleMap2035 = !empty($ruleMap2035) ? $ruleMap2035 : $this->ruleMap2035;
        $ruleMap2040 = RuleWordMap::query()->where('id', '=', 2040)->value('keyword'); // 长期/临时

        // 抗菌药级别映射
        $kjjbMap = [1 => '非限制使用级', 2 => '限制使用级', 3 => '特殊使用级'];

        foreach ($data as $item) {
            echo $item['MED_REC_ID'] . PHP_EOL;
            // 重置
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['tsjkjywhz_fz' => 0, 'tsjkjywhz_fm' => 0, 'tsjkjywhz_error' => null]);

            $insert = [
                'zyh' => $item['MED_REC_ID'],
                'tsjkjywhz_fz' => 0,
                'tsjkjywhz_fm' => 0,
                'tsjkjywhz_error' => null,
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => $carbon::parse($item['AAC01'])->format('Y'),
                'AAC01_MONTH' => $carbon::parse($item['AAC01'])->format('m'),
                'AAC01' => $item['AAC01']
            ];

            // 查询特殊级抗菌药物长期医嘱（未作废）
            $yzbList = Yzb::query()
                ->where('ZYH', '=', $item['MED_REC_ID'])
                ->where('is_has_kjyw', '=', 1)
                ->where('YZQX', '=', $ruleMap2040)
                ->where('YZZT', '<>', $ruleMap2035)
                ->where('KJJB', '=', 3)
                ->orderBy('KZSJ', 'asc')
                ->get(['YZMC', 'KJJB', 'KZSJ', 'TZSJ'])
                ->toArray();

            if (empty($yzbList)) {
                continue;
            }

            $errorMsg = [];
            $prevStopTime = '';

            foreach ($yzbList as $idx => $y) {
                $yzmc = $y['YZMC'] ?? '';
                $kjjb = intval($y['KJJB'] ?? 0);
                $kzsj = $y['KZSJ'] ?? '';
                $tzsj = $y['TZSJ'] ?? '';

                // 分母：每条特殊级抗菌药长期医嘱计入
                $insert['tsjkjywhz_fm'] += 1;

                $grp = ['status' => 0, 'content' => []];
                $grp['content'][] = ['status' => 1, 'content' => '医嘱名称【' . $yzmc . '】'];
                $grp['content'][] = ['status' => 1, 'content' => '抗生素级别【' . ($kjjbMap[$kjjb] ?? '未知') . '】'];
                $grp['content'][] = ['status' => 1, 'content' => '开嘱时间【' . $kzsj . '】'];

                // 查询会诊申请，通过 zyh 关联
                $hzsqQuery = YS_ZY_HZSQ::query()->where('JZHM', '=', $item['MED_REC_ID']);

                // 时间范围：第一条查 KZSJ 之前；后续查 上一条医嘱的 TZSJ ~ 当前 KZSJ
                if (!empty($prevStopTime) && $idx > 0 && $prevStopTime != '0000-00-00 00:00:00' && $prevStopTime != '1970-01-01 00:00:00') {
                    $hzsqQuery->where('SQSJ', '>', $prevStopTime)->where('SQSJ', '<=', $kzsj);
                } else {
                    $hzsqQuery->where('SQSJ', '<=', $kzsj);
                }

                $hzsqList = $hzsqQuery->get(['SQXH'])->toArray();

                // 如果有会诊申请，查询对应的会诊意见
                $foundConsultOpinion = false;
                if (!empty($hzsqList)) {
                    foreach ($hzsqList as $hzsq) {
                        $sqxh = $hzsq['SQXH'] ?? '';
                        if (empty($sqxh)) {
                            continue;
                        }

                        // 通过 SQXH 查询会诊意见，获取首次签名时间
                        $hzyj = YS_ZY_HZYJ::query()
                            ->where('SQXH', '=', $sqxh)
                            ->orderBy('QMSJ', 'desc')
                            ->first(['QMSJ']);

                        if (!empty($hzyj) && !empty($hzyj['QMSJ'])) {
                            // 命中会诊意见
                            $insert['tsjkjywhz_fz'] += 1;
                            $grp['status'] = 1;
                            $grp['content'][] = ['status' => 1, 'content' => '会诊意见首次签名时间【' . $hzyj['QMSJ'] . '】'];
                            $foundConsultOpinion = true;
                            break; // 找到一条即可
                        }
                    }
                }

                if (!$foundConsultOpinion) {
                    $grp['content'][] = ['status' => 0, 'content' => '会诊意见【无】'];
                }

                $errorMsg[] = $grp;

                // 更新上一条特殊级抗菌药物医嘱的停嘱时间
                $prevStopTime = $tzsj ?: $prevStopTime;
            }

            if (!empty($errorMsg)) {
                $insert['tsjkjywhz_error'] = json_encode($errorMsg, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        $this->UpdateIndexCataLog('tsjkjywhz', 2, time());
    }

    /**
     * 指标-术后首次病程即刻完成率
     * @param mixed $data
     * @return void
     */
    public function handleShscbcjkwc($data)
    {
        $BL01ESServer = new ElasticsearchService('bl01_202303');

        // 获取配置
        // 术后首次病程记录MBLB值
        $ruleMap8017 = RuleWordMap::query()->where('id', '=', 8017)->value('keyword');
        $ruleMap8017 = !empty($ruleMap8017) ? $ruleMap8017 : '42';
        if (strpos($ruleMap8017, ',') !== false) {
            $recordTypes = explode(',', $ruleMap8017);
        } else {
            $recordTypes = [$ruleMap8017];
        }

        // 病程记录BLLB值
        $ruleMap8010 = RuleWordMap::query()->where('id', '=', 8010)->value('keyword');
        $ruleMap8010 = !empty($ruleMap8010) ? $ruleMap8010 : '294';

        // 病程记录标题包含的关键词
        $ruleMap8018 = RuleWordMap::query()->where('id', '=', 8018)->value('keyword');
        $ruleMap8018 = !empty($ruleMap8018) ? $ruleMap8018 : '术后首次病程记录';

        // 获取执行时间字段名
        $ruleMap8011 = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $ruleMap8011 = !empty($ruleMap8011) ? $ruleMap8011 : 'ZXSJ';


        //获取8047,8048
        $ruleMap8047 = RuleWordMap::query()->where('id', '=', 8047)->value('keyword');
        $ruleMap8048 = RuleWordMap::query()->where('id', '=', 8048)->value('keyword');
        //是否包含逗号
        if (strpos($ruleMap8047, ',') !== false) {
            $excludeKeywords8047 = explode(',', $ruleMap8047);
        } else {
            $excludeKeywords8047 = [$ruleMap8047];
        }
        if (strpos($ruleMap8048, ',') !== false) {
            $excludeKeywords8048 = explode(',', $ruleMap8048);
        } else {
            $excludeKeywords8048 = [$ruleMap8048];
        }
        // 获取首次签名时间字段名
        $firstBlsyTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $firstBlsyTimeField = !empty($firstBlsyTimeField) ? $firstBlsyTimeField : 'first_blsy_time';

        $this->UpdateIndexCataLog('shscbcjkwc', 1, time());

        foreach ($data as $item) {
            // 删除之前的数据
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['shscbcjkwc_fz' => 0, 'shscbcjkwc_fm' => 0, 'shscbcjkwc_error' => null]);

            $insert = [
                'zyh' => $item['MED_REC_ID'],
                'shscbcjkwc_fz' => 0,
                'shscbcjkwc_fm' => 0,
                'shscbcjkwc_error' => '',
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($item['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($item['AAC01'])),
                'AAC01' => $item['AAC01']
            ];

            $error = [];

            // 1. 获取该患者的所有手术（与calculateSqtlwcl保持一致，按开始时间排序）
            try {
                $ssapList = SM_SSAP::query()
                    ->where('ZYH', $item['MED_REC_ID'])
                    ->orderBy('SSRQ', 'asc')
                    ->get()
                    ->toArray();

                if (empty($ssapList)) {
                    continue;
                }
            } catch (\Exception $e) {
                Log::error("handleShscbcjkwc-{$item['MED_REC_ID']}-error: " . $e->getMessage());
                continue;
            }

            // 2. 与calculateSqtlwcl保持一致，使用基于索引的循环
            for ($i = 0; $i < count($ssapList); $i++) {
                $op = $ssapList[$i];

                // 与calculateSqtlwcl一致的字段
                $surgeryStart = $op['SSRQ'] ?? '';
                $surgeryEnd = $op['JSRQ'] ?? '';
                $surgeryName = $op['ICD9_SSCZMC'] ?? '';
                $surgeryEndTime = $surgeryEnd;
                $surgeryType = '';

                // 与calculateSqtlwcl一致的过滤条件
                if (
                    empty($surgeryStart) || empty($surgeryName) || empty($surgeryEnd) ||
                    strpos($surgeryEnd, '1970-01-01') !== false || $surgeryName === 'NULL'
                ) {
                    continue;
                }

                // 手术级别过滤（与calculateSqtlwcl保持一致：ruleMap8047/8048）
                try {
                    $ssLB = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
                    if (!empty($ssLB)) {
                        $surgeryType = $ssLB;
                        if (!empty($ruleMap8047)) {
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
                } catch (\Exception $e) {
                    // 查询失败，继续处理
                }


                // 计算手术结束后6小时的时间点
                $sixHoursAfterSurgery = date('Y-m-d H:i:s', strtotime($surgeryEndTime) + 6 * 3600);

                $currentDate = Carbon::now()->toDateTimeString();
                if (strtotime($currentDate) < strtotime($sixHoursAfterSurgery)) {
                    continue;
                }

                // 分母：每条手术记录计入
                $insert['shscbcjkwc_fm'] += 1;

                $content = [];
                $content[] = ["status" => 1, "content" => "（手麻）手术名称【" . $surgeryName . "】"];
                $content[] = ["status" => 1, "content" => "（手麻）手术类型【" . ($surgeryType ?: '未知') . "】"];
                $content[] = ["status" => 1, "content" => "（手麻）手术结束时间【" . $surgeryEndTime . "】"];

                // 3. 查询术后首次病程记录
                // 先查询MBLB符合的记录
                $recordsmblb = EMR_BL_BL01::query()
                    ->where('JZHM', $item['MED_REC_ID'])
                    ->whereIn('MBLB', $recordTypes)
                    ->where($ruleMap8011, '>=', $surgeryEndTime)
                    ->where($ruleMap8011, '<=', $sixHoursAfterSurgery)
                    ->get()
                    ->toArray();

                $records = $recordsmblb;

                // 如果没有MBLB符合的记录，查询BLLB和BLMC符合的记录
                if (empty($records)) {
                    $recordsbllb = EMR_BL_BL01::query()
                        ->where('JZHM', $item['MED_REC_ID'])
                        ->where('BLLB', $ruleMap8010)
                        ->where('BLMC', 'like', '%' . $ruleMap8018 . '%')
                        ->where($ruleMap8011, '>=', $surgeryEndTime)
                        ->where($ruleMap8011, '<=', $sixHoursAfterSurgery)
                        ->get()
                        ->toArray();
                    $records = $recordsbllb;
                }

                // 4. 检查是否有有效的术后首次病程记录
                $hasValidRecord = false;

                if (!empty($records)) {
                    foreach ($records as $record) {
                        $firstBlsyTime = isset($record[$firstBlsyTimeField]) ? $record[$firstBlsyTimeField] : '';

                        if (!empty($firstBlsyTime)) {
                            // 检查签名时间是否在手术后6小时内
                            if (strtotime($firstBlsyTime) <= strtotime($sixHoursAfterSurgery)) {
                                // 有效记录
                                $hasValidRecord = true;
                                $insert['shscbcjkwc_fz'] += 1;
                                $content[] = ["status" => 1, "content" => "术后首程【" . $record['BLMC'] . "】"];
                                $content[] = ["status" => 1, "content" => "首次签名时间【" . $firstBlsyTime . "】"];
                                break;
                            } else {
                                // 签名时间超过6小时
                                $content[] = ["status" => 0, "content" => "术后首程【" . $record['BLMC'] . "】"];
                                $content[] = ["status" => 0, "content" => "首次签名时间【" . $firstBlsyTime . "】（超6小时）"];
                            }
                        } else {
                            // 未签名
                            $content[] = ["status" => 0, "content" => "术后首程【" . $record['BLMC'] . "】"];
                            $content[] = ["status" => 0, "content" => "首次签名时间【未签名】"];
                        }
                    }
                } else {
                    // 没有找到术后首次病程记录
                    $content[] = ["status" => 0, "content" => "术后首程【无】"];
                }

                if (!empty($content)) {
                    $status2 = array_unique(array_column($content, 'status'));
                    if (in_array(0, $status2)) {
                        $error[] = ["status" => 0, "content" => $content];
                    } else {
                        $error[] = ["status" => 1, "content" => $content];
                    }
                }
            }

            if (!empty($error)) {
                $insert['shscbcjkwc_error'] = !empty($error) ? json_encode($error, 256) : '';
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        $this->UpdateIndexCataLog('shscbcjkwc', 2, time());
    }

    /**
     * 指标-临床用血前评估记录率
     * 参考 handleLcyxhpg 和 lcyxqpgjl 逻辑
     * 分母：输血的医嘱数量（从输血系统 ZY_SS 获取）
     * 分子：输血开始时间前24小时内具备输血对应的病程记录（包含"输"或"贫血"）
     * @param mixed $data
     * @return void
     */
    public function handleLcyxqpg($data)
    {
        $BL01ESServer = new ElasticsearchService('bl01_202303');

        // 获取配置
        // 病程记录MBLB（输血病程）
        $ruleMap2029 = RuleWordMap::query()->where('id', '=', 2029)->value('keyword');
        $ruleMap2029 = !empty($ruleMap2029) ? $ruleMap2029 : 45;

        // 用血前评估关键词配置（根据输注品种不同使用不同关键词）
        // 红细胞 - id2031
        $ruleMap2031 = RuleWordMap::query()->where('id', '=', 2031)->value('keyword'); //改善贫血,纠正失血,补充血容量
        if (strpos($ruleMap2031, ',') !== false) {
            $ruleMap2031 = explode(',', $ruleMap2031);
        } else {
            $ruleMap2031 = [$ruleMap2031];
        }

        // 血浆 - id2032
        $ruleMap2032 = RuleWordMap::query()->where('id', '=', 2032)->value('keyword'); //改善凝血,补充凝血因子
        if (strpos($ruleMap2032, ',') !== false) {
            $ruleMap2032 = explode(',', $ruleMap2032);
        } else {
            $ruleMap2032 = [$ruleMap2032];
        }

        // 凝血因子 - id2033
        $ruleMap2033 = RuleWordMap::query()->where('id', '=', 2033)->value('keyword'); //改善凝血,补充凝血因子
        if (strpos($ruleMap2033, ',') !== false) {
            $ruleMap2033 = explode(',', $ruleMap2033);
        } else {
            $ruleMap2033 = [$ruleMap2033];
        }

        // 血小板 - id2034
        $ruleMap2034 = RuleWordMap::query()->where('id', '=', 2034)->value('keyword'); //改善凝血,补充其他血液成分
        if (strpos($ruleMap2034, ',') !== false) {
            $ruleMap2034 = explode(',', $ruleMap2034);
        } else {
            $ruleMap2034 = [$ruleMap2034];
        }

        $this->UpdateIndexCataLog('lcyxqpgjl', 1, time());

        foreach ($data as $item) {
            // 删除之前的数据
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['lcyxqpgjl_fz' => 0, 'lcyxqpgjl_fm' => 0, 'lcyxqpgjl_error' => null]);

            $insert = [
                'zyh' => $item['MED_REC_ID'],
                'lcyxqpgjl_fz' => 0,
                'lcyxqpgjl_fm' => 0,
                'lcyxqpgjl_error' => '',
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($item['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($item['AAC01'])),
                'AAC01' => $item['AAC01']
            ];

            $error = [];

            // 分母：按输血系统 ZY_SS 的输血记录数量计算
            $sxList = ZY_SS::query()->where('ZYH', '=', $item['MED_REC_ID'])->get()->toArray();
            if (empty($sxList)) {
                continue;
            }

            foreach ($sxList as $sx) {
                $content = [];
                $insert['lcyxqpgjl_fm'] += 1;

                // 获取输血开始时间
                $kssj = !empty($sx['KSSJ']) ? date('Y-m-d H:i:s', strtotime($sx['KSSJ'])) : '';
                if (empty($kssj) || $kssj == '1970-01-01 00:00:00' || $kssj == '0000-00-00 00:00:00') {
                    // 无效开始时间，记录并跳过分子判断
                    $content[] = ["status" => 0, "content" => "输血开始时间【无效】"];
                } else {
                    // 计算时间范围：输血开始时间前24小时
                    $startTime = date('Y-m-d H:i:00', strtotime($kssj) - 24 * 3600);
                    $endTime = date('Y-m-d H:i:00', strtotime($kssj));

                    // 展示输注品种、输血开始时间
                    $cfx = isset($sx['CFX']) ? $sx['CFX'] : '';
                    if (!empty($cfx)) {
                        $content[] = ["status" => 1, "content" => "输注【" . $cfx . "】"];
                    }
                    $content[] = ["status" => 1, "content" => "输血开始时间【" . $kssj . "】"];

                    // 根据输注品种（CFX）选择对应的关键词配置
                    $keywords = [];
                    if (strpos($cfx, '红细胞') !== false) {
                        $keywords = $ruleMap2031;
                    } elseif (strpos($cfx, '血浆') !== false) {
                        $keywords = $ruleMap2032;
                    } elseif (strpos($cfx, '凝血因子') !== false) {
                        $keywords = $ruleMap2033;
                    } elseif (strpos($cfx, '血小板') !== false) {
                        $keywords = $ruleMap2034;
                    } else {
                        // 默认使用红细胞的关键词
                        $keywords = $ruleMap2031;
                    }

                    // 在开始时间前24小时内查找病程记录
                    $must3 = [];
                    $must3[] = ['term' => ['JZHM' => $item['MED_REC_ID']]];
                    $must3[] = ['term' => ['BLLB' => 294]];
                    $must3[] = ['range' => ['ZXSJ' => ['from' => $startTime, 'to' => $endTime]]];

                    $params = $BL01ESServer->clearMust()
                        ->queryByMustBatch($must3)
                        ->queryByMustNot(['term' => ['BLZT' => $this->ruleMap2036]])
                        ->getParams();
                    $res = app('es')->search($params);
                    $bl01Data = $BL01ESServer->getDataByEs($res);

                    $found = 0;
                    $foundKeyword = '';
                    $errrecord = [];

                    if (!empty($bl01Data[1]) && !empty($bl01Data[0])) {
                        foreach ($bl01Data[0] as $v) {
                            $hjnr = $v['HJNR'];
                            $hjnr = str_replace(["："], ":", $hjnr);
                            $hjnr = str_replace(["{", "}"], "", $hjnr);

                            // 根据输注品种检查病程记录中是否包含对应的关键词
                            foreach ($keywords as $kw) {
                                if (strpos($hjnr, $kw) !== false) {
                                    $found = 1;
                                    $foundKeyword = $kw;
                                    $insert['lcyxqpgjl_fz'] += 1;
                                    $content[] = ["status" => 1, "content" => "病程记录【" . $v['BLMC'] . "】"];
                                    $content[] = ["status" => 1, "content" => "术前评估【'" . $foundKeyword . "'】（有）"];
                                    break 2;
                                }
                            }
                            $errrecord[] = $v['BLMC'];
                        }

                        if ($found == 0) {
                            foreach ($errrecord as $v) {
                                $content[] = ["status" => 0, "content" => "病程记录【" . $v . "】"];
                            }
                            $content[] = ["status" => 0, "content" => "术前评估【无】"];
                        }
                    } else {
                        $content[] = ["status" => 0, "content" => "病程记录【无】"];
                    }
                }

                if (!empty($content)) {
                    $status2 = array_unique(array_column($content, 'status'));
                    if (in_array(0, $status2)) {
                        $error[] = ["status" => 0, "content" => $content];
                    } else {
                        $error[] = ["status" => 1, "content" => $content];
                    }
                }
            }

            if (!empty($error)) {
                $insert['lcyxqpgjl_error'] = !empty($error) ? json_encode($error, 256) : '';
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        $this->UpdateIndexCataLog('lcyxqpgjl', 2, time());
    }

    /**
     * 指标-超越权限的抗菌药物药物处方时限合格率
     * 参考 handleKjywcfqlshg 逻辑
     * 分母：同期越权开具抗菌药物医嘱的医嘱总数
     * 分子：越权开具抗菌药物医嘱时间低于24小时的医嘱数量（同一个患者开具同一个越权药物的第一次和最后一次的开嘱时间差<=24小时）
     * @param mixed $data
     * @return void
     */
    public function handleCyqxdkjywy($data)
    {
        $this->UpdateIndexCataLog('cyqxdkjywy', 1, time());
        $carbon = new Carbon();

        // 获取配置
        $ruleMap2040 = RuleWordMap::query()->where('id', '=', 2040)->value('keyword');
        $ruleMap2035 = RuleWordMap::query()->where('id', '=', 2035)->value('keyword');
        $ruleMap2035 = !empty($ruleMap2035) ? $ruleMap2035 : $this->ruleMap2035;

        // 抗菌药级别映射
        $kjjb = [1 => '非限制使用级', 2 => '限制使用级', 3 => '特殊使用级'];
        // 权限编码映射
        $qxbm = [1 => '010106', 2 => '010107', 3 => '010108'];

        // 获取所有医师信息（工号 => 姓名）
        $staff = Staff::query()->get()->toArray();
        $staffMap = array_column($staff, 'name', 'code');

        foreach ($data as $item) {
            // 删除之前的数据
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], ['cyqxdkjywy_fz' => 0, 'cyqxdkjywy_fm' => 0, 'cyqxdkjywy_error' => null]);

            $insert = [
                'zyh' => $item['MED_REC_ID'],
                'cyqxdkjywy_fz' => 0,
                'cyqxdkjywy_fm' => 0,
                'cyqxdkjywy_error' => '',
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => $carbon::parse($item['AAC01'])->format("Y"),
                'AAC01_MONTH' => $carbon::parse($item['AAC01'])->format("m"),
                'AAC01' => $item['AAC01']
            ];

            // 先清洗yzb
            //RadioService::filterField($item['MED_REC_ID']);

            // 查询抗菌药物医嘱
            $yzbQuery = Yzb::query()
                ->where('ZYH', '=', $item['MED_REC_ID'])
                ->where('is_has_kjyw', '=', 1)
                ->where('YZQX', '=', $ruleMap2040)
                ->where('YZZT', '<>', $ruleMap2035);

            $yzbData = $yzbQuery->get(['KZYS', 'KJJB', 'YZMC', 'KZSJ', 'kjyw_name'])->toArray();

            if (empty($yzbData)) {
                continue; // 没有抗菌药物医嘱，跳过
            }

            // 获取所有开嘱医师的工号
            $kzysList = array_column($yzbData, 'KZYS');
            $kzysList = array_unique(array_filter($kzysList));

            // 查询这些医师的权限
            $ryqxData = [];
            if (!empty($kzysList)) {
                $ryqxData = RYQX::query()->whereIn('YSDM', $kzysList)->get()->toArray();
            }

            $errorMsg = [];

            // 处理每条抗菌药物医嘱，找出越权的医嘱
            foreach ($yzbData as $yzb) {
                $yzmc = $yzb['YZMC'] ?? '';
                $kzys = $yzb['KZYS'] ?? '';
                $kjjbValue = $yzb['KJJB'] ?? '';
                $kzsj = $yzb['KZSJ'] ?? '';
                $kjywName = $yzb['kjyw_name'] ?? '';

                // 检查是否有权限
                $hasPermission = false;
                if (!empty($kzys) && !empty($kjjbValue) && isset($kjjb[$kjjbValue]) && isset($qxbm[$kjjbValue])) {
                    // 查找该医师是否有对应级别的权限
                    foreach ($ryqxData as $ryqx) {
                        if ($ryqx['YSDM'] == $kzys && $ryqx['QXBM'] == $qxbm[$kjjbValue]) {
                            $hasPermission = true;
                            break;
                        }
                    }
                }

                // 只处理越权的医嘱（无权限的）
                if ($hasPermission) {
                    continue; // 有权限的跳过
                }

                // 分母：越权开具抗菌药物医嘱
                $insert['cyqxdkjywy_fm'] += 1;

                $yzbGroup = [
                    'status' => 0,
                    'content' => []
                ];

                // 添加医嘱信息
                $yzbGroup['content'][] = [
                    'status' => 1,
                    'content' => "医嘱名称【" . $yzmc . "】"
                ];

                // 添加开嘱医师信息
                $staffName = $staffMap[$kzys] ?? '';
                $yzbGroup['content'][] = [
                    'status' => 1,
                    'content' => "开嘱医师【" . ($staffName ?: '未知') . "（" . $kzys . "）】"
                ];

                // 添加权限信息
                $kjjbName = $kjjb[$kjjbValue] ?? '未知';
                $yzbGroup['content'][] = [
                    'status' => 0,
                    'content' => "权限【" . $kjjbName . "】（越权）"
                ];

                // 添加开嘱时间
                $yzbGroup['content'][] = [
                    'status' => 1,
                    'content' => "开嘱时间【" . $kzsj . "】"
                ];

                // 查询该医嘱的kzsj之后，同一个患者，医嘱名称（YZMC）包含kjyw_name的医嘱
                $lastYzb = null;
                if (!empty($kjywName) && !empty($kzsj)) {
                    $lastYzbQuery = Yzb::query()
                        ->where('ZYH', '=', $item['MED_REC_ID'])
                        ->where('is_has_kjyw', '=', 1)
                        ->where('YZMC', 'like', '%' . $kjywName . '%')
                        ->where('YZQX', '=', $ruleMap2040)
                        ->where('YZZT', '<>', $ruleMap2035)
                        ->where('KZSJ', '>', $kzsj)
                        ->orderBy('KZSJ', 'desc')
                        ->first(['YZMC', 'KZSJ']);

                    if ($lastYzbQuery) {
                        $lastYzb = $lastYzbQuery->toArray();
                    }
                }

                // 检查最后一条的kzsj和当前医嘱的kzsj时间差是否<=24小时
                $isWithin24Hours = false;
                if ($lastYzb && !empty($lastYzb['KZSJ'])) {
                    $currentTime = strtotime($kzsj);
                    $lastTime = strtotime($lastYzb['KZSJ']);
                    $timeDiff = $lastTime - $currentTime;

                    if ($timeDiff <= 24 * 3600 && $timeDiff >= 0) {
                        $isWithin24Hours = true;
                        $insert['cyqxdkjywy_fz'] += 1;
                        $yzbGroup['status'] = 1;
                    }
                }

                // 添加最后一次抗菌药医嘱信息
                if ($lastYzb) {
                    $yzbGroup['content'][] = [
                        'status' => 1,
                        'content' => "最后一次抗菌药医嘱名称【" . $lastYzb['YZMC'] . "】"
                    ];

                    if ($isWithin24Hours) {
                        $yzbGroup['content'][] = [
                            'status' => 1,
                            'content' => "最后一次抗菌药开嘱时间【" . $lastYzb['KZSJ'] . "】（≤24小时）"
                        ];
                    } else {
                        $yzbGroup['content'][] = [
                            'status' => 0,
                            'content' => "最后一次抗菌药开嘱时间【" . $lastYzb['KZSJ'] . "】（>24小时）"
                        ];
                    }
                } else {
                    $yzbGroup['content'][] = [
                        'status' => 0,
                        'content' => "最后一次抗菌药医嘱名称【无】"
                    ];
                }

                $errorMsg[] = $yzbGroup;
            }

            if (!empty($errorMsg)) {
                $insert['cyqxdkjywy_error'] = json_encode($errorMsg, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        $this->UpdateIndexCataLog('cyqxdkjywy', 2, time());
    }

    /**
     * 指标：术前手术知情同意书签署完成率
     * @param mixed $data
     * @return void
     */
    public function handleSstysqs($data)
    {
        $this->UpdateIndexCataLog('sstysqs', 1, time());

        // 获取标题时间字段名配置
        $zxsjField = RuleWordMap::query()->where('id', 8011)->value('keyword');
        $zxsjField = !empty($zxsjField) ? $zxsjField : 'ZXSJ';

        // 获取手术类别过滤配置
        $ruleMap8047 = RuleWordMap::query()->where('id', '=', 8047)->value('keyword');
        $ruleMap8048 = RuleWordMap::query()->where('id', '=', 8048)->value('keyword');
        if (strpos($ruleMap8047, ',') !== false) {
            $excludeKeywords8047 = explode(',', $ruleMap8047);
        } else {
            $excludeKeywords8047 = !empty($ruleMap8047) ? [$ruleMap8047] : [];
        }
        if (strpos($ruleMap8048, ',') !== false) {
            $excludeKeywords8048 = explode(',', $ruleMap8048);
        } else {
            $excludeKeywords8048 = !empty($ruleMap8048) ? [$ruleMap8048] : [];
        }

        foreach ($data as $item) {
            // 删除旧数据
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], [
                'sstysqs_fz' => 0,
                'sstysqs_fm' => 0,
                'sstysqs_error' => null
            ]);

            $ZYH = $item['MED_REC_ID'];

            $insert = [
                'zyh' => $ZYH,
                'sstysqs_fz' => 0,
                'sstysqs_fm' => 0,
                'sstysqs_error' => null,
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($item['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($item['AAC01'])),
                'AAC01' => $item['AAC01']
            ];

            $errorContent = [];

            // 查询SM_SSAP表，获取该患者的所有手术记录（手术+介入），按手术开始时间排序
            $ssapDataRaw = SM_SSAP::query()
                ->where('ZYH', $ZYH)
                ->whereNotNull('SSRQ')
                ->whereNotNull('JSRQ')
                ->where('SSRQ', '<>', '')
                ->where('JSRQ', '<>', '')
                ->orderBy('SSRQ', 'asc')
                ->get()
                ->toArray();

            if (empty($ssapDataRaw)) {
                continue;
            }

            // 手术类别过滤
            $ssapData = $ssapDataRaw;
            /* foreach ($ssapDataRaw as $surgery) {
                $surgeryName = $surgery['ICD9_SSCZMC'] ?? '';
                
                // 检查必要字段
                if (empty($surgeryName) || $surgeryName == 'NULL') {
                    continue;
                }
                
                // 手术类别过滤（参考handleRjssblsqtl）
                if (!empty($ruleMap8047)) {
                    $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
                    if (!empty($ssCZ)) {
                        if (!in_array($ssCZ, $excludeKeywords8047)) {
                            if (!empty($ruleMap8048)) {
                                if (!in_array($surgeryName, $excludeKeywords8048)) {
                                    continue; // 不符合手术类别要求，跳过
                                }
                            } else {
                                continue; // 不符合手术类别要求，跳过
                            }
                        }
                    }
                }
                
                // 通过过滤的手术
                $ssapData[] = $surgery;
            } */

            if (empty($ssapData)) {
                continue;
            }

            // 判断手术时间段是否重合，将重合的手术归为一组
            $surgeryGroups = [];
            foreach ($ssapData as $surgery) {
                $ssrq = strtotime($surgery['SSRQ']);
                $jsrq = strtotime($surgery['JSRQ']);

                // 查找是否与现有组有重合
                $foundGroup = false;
                foreach ($surgeryGroups as &$group) {
                    // 判断当前手术与组内任何手术是否重合
                    foreach ($group['surgeries'] as $groupSurgery) {
                        $groupStart = strtotime($groupSurgery['SSRQ']);
                        $groupEnd = strtotime($groupSurgery['JSRQ']);

                        // 判断时间段是否重合
                        if (($ssrq >= $groupStart && $ssrq <= $groupEnd) ||
                            ($jsrq >= $groupStart && $jsrq <= $groupEnd) ||
                            ($ssrq <= $groupStart && $jsrq >= $groupEnd)
                        ) {
                            // 有重合，加入该组
                            $group['surgeries'][] = $surgery;
                            // 更新组的时间范围
                            $group['start'] = min($group['start'], $ssrq);
                            $group['end'] = max($group['end'], $jsrq);
                            $foundGroup = true;
                            break 2;
                        }
                    }
                }

                // 如果没有找到重合的组，创建新组
                if (!$foundGroup) {
                    $surgeryGroups[] = [
                        'surgeries' => [$surgery],
                        'start' => $ssrq,
                        'end' => $jsrq
                    ];
                }
            }
            unset($group); // 解除引用

            // 遍历每一组手术
            $previousGroupEnd = null;
            foreach ($surgeryGroups as $groupIndex => $surgeryGroup) {
                // 获取当前组的最早开始时间
                $currentGroupStart = date('Y-m-d H:i:s', $surgeryGroup['start']);

                // 确定查询手术同意书的时间范围
                $queryStartTime = null;
                $queryEndTime = $currentGroupStart;

                if ($groupIndex === 0) {
                    // 第一组：查询当前组最早手术开始时间之前的所有手术同意书
                    $queryStartTime = '1970-01-01 00:00:00';
                } else {
                    // 后续组：查询上一组最晚结束时间到当前组最早开始时间之间
                    $queryStartTime = $previousGroupEnd;
                }

                // 查询手术同意书（使用组内最早的手术开始时间作为查询条件）
                $consentForms = EMR_BL_BL01::query()
                    ->where('JZHM', $ZYH)
                    /* ->where(function ($query) {
                        $query->where('BLMC', 'like', '%手术同意书%')
                              ->orWhere('BLMC', 'like', '%手术知情同意书%');
                    }) */
                    ->where('BLLB', 329)
                    ->where($zxsjField, '>=', $queryStartTime)
                    ->where($zxsjField, '<', $queryEndTime)
                    ->orderBy($zxsjField, 'asc')
                    ->get()
                    ->toArray();

                foreach ($consentForms as $key => $consentForm) {
                    if (!strpos($consentForm['BLMC'], "手术同意书") && !strpos($consentForm['BLMC'], "手术知情同意书")) {
                        $HTML_PRINT = !empty($consentForm['HTML_PRINT']) ? $consentForm['HTML_PRINT'] : '';
                        if (strpos($HTML_PRINT, "手术知情同意书") !== false) {
                            $consentForm['BLMC'] = $consentForm['BLMC'] . "手术知情同意书";
                            EMR_BL_BL01::query()->where('BLBH', $consentForm['BLBH'])->update(['BLMC' => $consentForm['BLMC']]);
                        } else {
                            //标题不包含且HTML_PRINT中不包含手术知情同意书，则从consentForms删除这条记录
                            unset($consentForms[$key]);
                            continue;
                        }
                    }
                }

                //重新排序
                $consentForms = array_values($consentForms);
                // 遍历组内的每一台手术
                foreach ($surgeryGroup['surgeries'] as $surgery) {
                    $surgeryName = $surgery['ICD9_SSCZMC'] ?? '未知';
                    $surgeryStartTime = $surgery['SSRQ'] ?? '';

                    // 手术类别过滤（参考handleRjssblsqtl）
                    if (!empty($ruleMap8047)) {
                        $ssCZ = SSCZ::query()->where('SSMC', $surgeryName)->value('SSLB');
                        if (!empty($ssCZ)) {
                            if (!in_array($ssCZ, $excludeKeywords8047)) {
                                if (!empty($ruleMap8048)) {
                                    if (!in_array($surgeryName, $excludeKeywords8048)) {
                                        continue; // 不符合手术类别要求，跳过该台手术
                                    }
                                } else {
                                    continue; // 不符合手术类别要求，跳过该台手术
                                }
                            }
                        }
                    }
                    // 分母+1（组内每台手术都算一个分母）
                    $insert['sstysqs_fm'] += 1;

                    $group = [
                        'status' => 0,
                        'content' => []
                    ];

                    $group['content'][] = ['status' => 1, 'content' => "（手麻）手术名称【{$surgeryName}】"];
                    $group['content'][] = ['status' => 1, 'content' => "（手麻）手术开始时间【{$surgeryStartTime}】"];

                    if (empty($consentForms)) {
                        // 没有找到手术同意书
                        $group['content'][] = ['status' => 0, 'content' => "手术同意书【无】"];
                    } else {
                        // 取第一个手术同意书的首次签署时间
                        $firstConsentForm = $consentForms[0];
                        $consentBlmc = $firstConsentForm['BLMC'] ?? '';
                        $firstSignTime = $firstConsentForm['first_blsy_time'] ?? '';

                        if (empty($firstSignTime)) {
                            $group['content'][] = ['status' => 1, 'content' => "手术同意书【{$consentBlmc}】"];
                            $group['content'][] = ['status' => 0, 'content' => "手术同意书首次签署时间【无】"];
                        } else {
                            // 判断签署时间是否早于当前手术的开始时间
                            if (strtotime($firstSignTime) < strtotime($surgeryStartTime)) {
                                // 符合要求
                                $insert['sstysqs_fz'] += 1;
                                $group['status'] = 1;
                                $group['content'][] = ['status' => 1, 'content' => "手术同意书【{$consentBlmc}】"];
                                $group['content'][] = ['status' => 1, 'content' => "手术同意书首次签署时间【{$firstSignTime}】"];
                            } else {
                                // 签署时间晚于或等于手术开始时间
                                $group['content'][] = ['status' => 1, 'content' => "手术同意书【{$consentBlmc}】"];
                                $group['content'][] = ['status' => 0, 'content' => "手术同意书首次签署时间【{$firstSignTime}】晚于手术开始时间"];
                            }
                        }
                    }

                    $errorContent[] = $group;
                }

                // 更新上一组的结束时间
                $previousGroupEnd = date('Y-m-d H:i:s', $surgeryGroup['end']);
            }

            if (!empty($errorContent)) {
                $insert['sstysqs_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        $this->UpdateIndexCataLog('sstysqs', 2, time());
    }

    /**
     * 指标：手术知情同意书签署时间规范率
     * @param mixed $data
     * @return void
     */
    public function handleSstysqssj($data)
    {
        $this->UpdateIndexCataLog('sstysqssj', 1, time());

        // 获取术前小结及术前讨论MBLB配置
        $mblbRaw = RuleWordMap::query()->where('id', '=', 8016)->value('keyword');
        $mblbList = empty($mblbRaw) ? [] : (strpos($mblbRaw, ',') !== false ? explode(',', $mblbRaw) : [$mblbRaw]);

        // 获取标题时间字段名配置
        $zxsjField = RuleWordMap::query()->where('id', 8011)->value('keyword');
        $zxsjField = !empty($zxsjField) ? $zxsjField : 'ZXSJ';

        foreach ($data as $item) {
            // 删除旧数据
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], [
                'sstysqssj_fz' => 0,
                'sstysqssj_fm' => 0,
                'sstysqssj_error' => null
            ]);

            $ZYH = $item['MED_REC_ID'];

            $insert = [
                'zyh' => $ZYH,
                'sstysqssj_fz' => 0,
                'sstysqssj_fm' => 0,
                'sstysqssj_error' => null,
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($item['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($item['AAC01'])),
                'AAC01' => $item['AAC01']
            ];

            $errorContent = [];

            // 查询手术同意书（已签名）
            $consentForms = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->where(function ($query) {
                    $query->where('BLMC', 'like', '%手术同意书%')
                        ->orWhere('BLMC', 'like', '%手术知情同意书%');
                })
                ->whereNotNull('first_blsy_time')
                ->where('first_blsy_time', '<>', '')
                ->where('first_blsy_time', '<>', '0000-00-00 00:00:00')
                ->where('first_blsy_time', '<>', '1970-01-01 00:00:00')
                ->get(['BLBH', 'BLMC', $zxsjField, 'first_blsy_time'])
                ->toArray();

            if (empty($consentForms)) {
                continue; // 没有手术同意书，跳过
            }

            // 查询所有术前小结记录
            $recordsQuery = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->orderBy('ZXSJ', 'desc');
            if (!empty($mblbList)) {
                $recordsQuery->whereIn('MBLB', $mblbList);
            }
            $records = $recordsQuery->get()->toArray();

            // 遍历每个手术同意书
            foreach ($consentForms as $consentForm) {
                $consentBlmc = $consentForm['BLMC'] ?? '';
                $firstSignTime = $consentForm['first_blsy_time'] ?? '';

                if (empty($firstSignTime)) {
                    continue; // 没有签名时间，跳过
                }

                // 分母+1（每个已签名的手术同意书算一个分母）
                $insert['sstysqssj_fm'] += 1;

                $group = [
                    'status' => 0,
                    'content' => []
                ];

                if (empty($records)) {
                    // 没有术前讨论记录
                    $group['content'][] = ['status' => 0, 'content' => "术前讨论【无】"];
                    $group['content'][] = ['status' => 1, 'content' => "手术同意书【{$consentBlmc}】"];
                    $group['content'][] = ['status' => 1, 'content' => "手术同意书首次签署时间【{$firstSignTime}】"];
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

                    $discussionTime = '';
                    if (preg_match('/(\d{4}-\d{1,2}-\d{1,2}\s+\d{1,2}:\d{1,2}(?::\d{1,2})?)/', $sub, $m)) {
                        $discussionTime = $m[1];
                    }

                    if (empty($discussionTime)) {
                        // 未提取到时间，跳过该条
                        continue;
                    }

                    // 判断讨论时间是否早于签署时间
                    if (strtotime($discussionTime) < strtotime($firstSignTime)) {
                        // 符合要求：讨论时间早于签署时间
                        $insert['sstysqssj_fz'] += 1;
                        $group['status'] = 1;
                        $group['content'][] = ['status' => 1, 'content' => "术前讨论【{$blmc}】"];
                        $group['content'][] = ['status' => 1, 'content' => "术前讨论时间【{$discussionTime}】"];
                        $group['content'][] = ['status' => 1, 'content' => "手术同意书【{$consentBlmc}】"];
                        $group['content'][] = ['status' => 1, 'content' => "手术同意书首次签署时间【{$firstSignTime}】"];
                        $found = true;
                        break; // 找到符合条件的即可
                    }
                }

                if (!$found) {
                    // 未找到符合条件的术前讨论，或签署时间早于讨论时间
                    if (!empty($records)) {
                        // 有术前讨论但时间不符合，展示所有不符合的讨论记录
                        $hasDiscussionTime = false;
                        foreach ($records as $rec) {
                            $blmc = $rec['BLMC'] ?? '';
                            $blbh = $rec['BLBH'] ?? '';

                            $hjnr = '';
                            try {
                                $hjnr = EMR_BL_BLXG::query()->where('BLBH', $blbh)->value('HJNR') ?? '';
                            } catch (\Exception $e) {
                                $hjnr = '';
                            }

                            $sub = $hjnr;
                            $pos1 = mb_strpos($hjnr, '术前小结及术前讨论结论记录');
                            if ($pos1 !== false) {
                                $sub = mb_substr($hjnr, $pos1);
                            }

                            $discussionTime = '';
                            if (preg_match('/(\d{4}-\d{1,2}-\d{1,2}\s+\d{1,2}:\d{1,2}(?::\d{1,2})?)/', $sub, $m)) {
                                $discussionTime = $m[1];
                            }

                            if (!empty($discussionTime)) {
                                $hasDiscussionTime = true;
                                $group['content'][] = ['status' => 0, 'content' => "术前讨论【{$blmc}】"];
                                $group['content'][] = ['status' => 0, 'content' => "术前讨论时间【{$discussionTime}】"];
                                // 不break，继续展示所有不符合的记录
                            }
                        }

                        // 展示手术同意书信息
                        if ($hasDiscussionTime) {
                            $group['content'][] = ['status' => 1, 'content' => "手术同意书【{$consentBlmc}】"];
                            $group['content'][] = ['status' => 0, 'content' => "手术同意书首次签署时间【{$firstSignTime}】早于术前讨论时间"];
                        } else {
                            // 如果还是没有找到有时间的术前讨论
                            $group['content'][] = ['status' => 0, 'content' => "术前讨论【有，但未提取到讨论时间】"];
                            $group['content'][] = ['status' => 1, 'content' => "手术同意书【{$consentBlmc}】"];
                            $group['content'][] = ['status' => 1, 'content' => "手术同意书首次签署时间【{$firstSignTime}】"];
                        }
                    } else {
                        $group['content'][] = ['status' => 0, 'content' => "术前讨论【无】"];
                        $group['content'][] = ['status' => 1, 'content' => "手术同意书【{$consentBlmc}】"];
                        $group['content'][] = ['status' => 1, 'content' => "手术同意书首次签署时间【{$firstSignTime}】"];
                    }
                }

                $errorContent[] = $group;
            }

            if (!empty($errorContent)) {
                $insert['sstysqssj_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        $this->UpdateIndexCataLog('sstysqssj', 2, time());
    }

    /**
     * 指标：手术知情同意书签署顺序规范率
     * @param mixed $data
     * @return void
     */
    public function handleSstysqssx($data)
    {
        $this->UpdateIndexCataLog('sstysqssx', 1, time());

        // 获取标题时间字段名配置
        $zxsjField = RuleWordMap::query()->where('id', 8011)->value('keyword');
        $zxsjField = !empty($zxsjField) ? $zxsjField : 'ZXSJ';

        foreach ($data as $item) {
            // 删除旧数据
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], [
                'sstysqssx_fz' => 0,
                'sstysqssx_fm' => 0,
                'sstysqssx_error' => null
            ]);

            $ZYH = $item['MED_REC_ID'];

            $insert = [
                'zyh' => $ZYH,
                'sstysqssx_fz' => 0,
                'sstysqssx_fm' => 0,
                'sstysqssx_error' => null,
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($item['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($item['AAC01'])),
                'AAC01' => $item['AAC01']
            ];

            $errorContent = [];

            // 查询手术同意书（已签名）
            $consentForms = EMR_BL_BL01::query()
                ->where('JZHM', $ZYH)
                ->where(function ($query) {
                    $query->where('BLMC', 'like', '%手术同意书%')
                        ->orWhere('BLMC', 'like', '%手术知情同意书%');
                })
                ->whereNotNull('first_blsy_time')
                ->where('first_blsy_time', '<>', '')
                ->where('first_blsy_time', '<>', '0000-00-00 00:00:00')
                ->where('first_blsy_time', '<>', '1970-01-01 00:00:00')
                ->get(['BLBH', 'BLMC', $zxsjField, 'first_blsy_time'])
                ->toArray();

            if (empty($consentForms)) {
                continue; // 没有手术同意书，跳过
            }

            // 遍历每个手术同意书
            foreach ($consentForms as $consentForm) {
                $consentBlmc = $consentForm['BLMC'] ?? '';
                $blbh = $consentForm['BLBH'] ?? '';

                if (empty($blbh)) {
                    continue;
                }

                // 分母+1（每个已签名的手术同意书算一个分母）
                $insert['sstysqssx_fm'] += 1;

                $group = [
                    'status' => 0,
                    'content' => []
                ];

                $group['content'][] = ['status' => 1, 'content' => "手术同意书【{$consentBlmc}】"];

                // 查询签名记录
                $signatures = EMR_BL_BLSY::query()
                    ->where('BLBH', $blbh)
                    ->orderBy('JLSJ', 'asc')
                    ->get(['QMLX', 'JLSJ', 'SYYS'])
                    ->toArray();

                if (empty($signatures)) {
                    // 没有签名记录（理论上不应该出现，因为已过滤有first_blsy_time的）
                    $group['content'][] = ['status' => 0, 'content' => "签名记录【无】"];
                    $errorContent[] = $group;
                    continue;
                }

                // 提取医生和患者的首次签署时间
                $doctorFirstSignTime = null;
                $patientFirstSignTime = null;

                foreach ($signatures as $sig) {
                    $qmlx = $sig['QMLX'] ?? '';
                    $jlsj = $sig['JLSJ'] ?? '';

                    if (empty($jlsj) || $jlsj == '0000-00-00 00:00:00' || $jlsj == '1970-01-01 00:00:00') {
                        continue;
                    }

                    // QMLX=1是医生，不等于1是患者
                    if ($qmlx == '1') {
                        // 医生签名
                        if (is_null($doctorFirstSignTime)) {
                            $doctorFirstSignTime = $jlsj;
                        }
                    } else {
                        // 患者签名
                        if (is_null($patientFirstSignTime)) {
                            $patientFirstSignTime = $jlsj;
                        }
                    }

                    // 如果两个都找到了就不用继续循环
                    if (!is_null($doctorFirstSignTime) && !is_null($patientFirstSignTime)) {
                        break;
                    }
                }

                // 判断签署顺序
                if (is_null($patientFirstSignTime)) {
                    // 没有患者签名时间
                    $group['content'][] = ['status' => 0, 'content' => "患者首次签署时间【无】"];
                    if (!is_null($doctorFirstSignTime)) {
                        $group['content'][] = ['status' => 1, 'content' => "医生首次签署时间【{$doctorFirstSignTime}】"];
                    } else {
                        $group['content'][] = ['status' => 0, 'content' => "医生首次签署时间【无】"];
                    }
                } elseif (is_null($doctorFirstSignTime)) {
                    // 没有医生签名时间
                    $group['content'][] = ['status' => 1, 'content' => "患者首次签署时间【{$patientFirstSignTime}】"];
                    $group['content'][] = ['status' => 0, 'content' => "医生首次签署时间【无】"];
                } else {
                    // 都有签名时间，比较顺序
                    if (strtotime($doctorFirstSignTime) < strtotime($patientFirstSignTime)) {
                        // 符合要求：医生签署时间早于患者签署时间
                        $insert['sstysqssx_fz'] += 1;
                        $group['status'] = 1;
                        $group['content'][] = ['status' => 1, 'content' => "患者首次签署时间【{$patientFirstSignTime}】"];
                        $group['content'][] = ['status' => 1, 'content' => "医生首次签署时间【{$doctorFirstSignTime}】"];
                    } else {
                        // 不符合：医生签署时间晚于或等于患者签署时间
                        $group['content'][] = ['status' => 1, 'content' => "患者首次签署时间【{$patientFirstSignTime}】"];
                        $group['content'][] = ['status' => 0, 'content' => "医生首次签署时间【{$doctorFirstSignTime}】晚于患者签名时间"];
                    }
                }

                $errorContent[] = $group;
            }

            if (!empty($errorContent)) {
                $insert['sstysqssx_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        $this->UpdateIndexCataLog('sstysqssx', 2, time());
    }

    /**
     * 指标：院患者自理能力及时评估率
     * @param mixed $data
     * @return void
     */
    public function handleZyhzzlnljspg($data)
    {
        $this->UpdateIndexCataLog('zyhzzlnljspg', 1, time());

        // 获取护理级别关键词配置
        $ruleMap8095 = RuleWordMap::query()->where('id', '=', 8095)->value('keyword');
        if (strpos($ruleMap8095, ',') !== false) {
            $nursingLevelKeywords = explode(',', $ruleMap8095);
        } else {
            $nursingLevelKeywords = !empty($ruleMap8095) ? [$ruleMap8095] : ['特级护理', 'I级护理', 'II级护理', 'III级护理', '一级护理', '二级护理', '三级护理'];
        }

        // 医嘱状态配置（排除作废医嘱）
        $ruleMap2035 = RuleWordMap::query()->where('id', '=', 2035)->value('keyword');
        $ruleMap2035 = !empty($ruleMap2035) ? $ruleMap2035 : 3;

        foreach ($data as $item) {
            // 删除旧数据
            Indicator::query()->updateOrInsert(['zyh' => $item['MED_REC_ID']], [
                'zyhzzlnljspg_fz' => 0,
                'zyhzzlnljspg_fm' => 0,
                'zyhzzlnljspg_error' => null
            ]);

            $ZYH = $item['MED_REC_ID'];

            $insert = [
                'zyh' => $ZYH,
                'zyhzzlnljspg_fz' => 0,
                'zyhzzlnljspg_fm' => 0,
                'zyhzzlnljspg_error' => null,
                'AAC11N' => $item['AAC11N'],
                'AEE03' => !empty($item['AEE03']) ? $item['AEE03'] : '',
                'AAC01_YEAR' => date('Y', strtotime($item['AAC01'])),
                'AAC01_MONTH' => date('m', strtotime($item['AAC01'])),
                'AAC01' => $item['AAC01']
            ];

            $errorContent = [];

            // 分母：出院患者（所有传入的患者都是出院患者）
            $insert['zyhzzlnljspg_fm'] = 1;

            // 查询入院评估（PGDH=8）
            $fxpgList = IENR_FXPG::query()
                ->where('ZYH', $ZYH)
                ->where('PGDH', '8')
                ->whereNotNull('PGSJ')
                ->where('PGSJ', '<>', '')
                ->where('PGSJ', '<>', '0000-00-00 00:00:00')
                ->where('PGSJ', '<>', '1970-01-01 00:00:00')
                ->orderBy('PGSJ', 'asc')
                ->get(['PGSJ'])
                ->toArray();

            $group = [
                'status' => 0,
                'content' => []
            ];

            if (empty($fxpgList)) {
                // 没有自理能力评估记录
                $group['content'][] = ['status' => 0, 'content' => "自理能力评估时间【无】"];
                $errorContent[] = $group;
            } else {
                // 取第一条评估记录
                $assessmentTime = $fxpgList[0]['PGSJ'] ?? '';

                if (empty($assessmentTime)) {
                    $group['content'][] = ['status' => 0, 'content' => "自理能力评估时间【无】"];
                    $errorContent[] = $group;
                } else {
                    $group['content'][] = ['status' => 1, 'content' => "自理能力评估时间【{$assessmentTime}】"];

                    // 计算2小时后的时间
                    $endTime = date('Y-m-d H:i:s', strtotime($assessmentTime) + 2 * 3600);

                    // 查询2小时内的护理级别医嘱
                    $nursingOrders = Yzb::query()
                        ->where('ZYH', $ZYH)
                        ->where('KZSJ', '>=', $assessmentTime)
                        ->where('KZSJ', '<=', $endTime)
                        ->where('YZZT', '<>', $ruleMap2035)
                        ->where(function ($query) use ($nursingLevelKeywords) {
                            foreach ($nursingLevelKeywords as $keyword) {
                                $query->orWhere('YZMC', 'like', '%' . $keyword . '%');
                            }
                        })
                        ->orderBy('KZSJ', 'asc')
                        ->get(['YZMC', 'KZSJ'])
                        ->toArray();

                    if (!empty($nursingOrders)) {
                        // 找到护理医嘱
                        $firstOrder = $nursingOrders[0];
                        $yzmc = $firstOrder['YZMC'] ?? '';
                        $kzsj = $firstOrder['KZSJ'] ?? '';

                        $insert['zyhzzlnljspg_fz'] = 1;
                        $group['status'] = 1;
                        $group['content'][] = ['status' => 1, 'content' => "医嘱名称【{$yzmc}】"];
                        $group['content'][] = ['status' => 1, 'content' => "开嘱时间【{$kzsj}】在护理评估2小时内开具"];
                    } else {
                        // 2小时内未找到护理医嘱
                        $group['content'][] = ['status' => 0, 'content' => "医嘱名称【2小时内未找到护理医嘱】"];
                    }

                    $errorContent[] = $group;
                }
            }

            if (!empty($errorContent)) {
                $insert['zyhzzlnljspg_error'] = json_encode($errorContent, JSON_UNESCAPED_UNICODE);
                Indicator::query()->updateOrInsert(['zyh' => $insert['zyh']], $insert);
            }
        }

        $this->UpdateIndexCataLog('zyhzzlnljspg', 2, time());
    }
}
