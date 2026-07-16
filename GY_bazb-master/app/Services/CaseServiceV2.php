<?php

namespace App\Services;

use App\Model\CaseQualityV2;
use App\Model\CaseQuality;
use App\Model\EMR_BL_BLSY;
use App\Model\MedicinalInfo;
use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use App\Model\PatientInfoTargetTemporary;
use App\Model\Setting;
use App\Model\Staff;
use App\Model\Bllb292;
use App\Services\QualityControl\SzzkService;
use App\Services\QualityControl\ZkzkService;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Model\PatientQualityScore;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\EventListener\ValidateRequestListener;
use App\Model\CaseRule;
use function GuzzleHttp\Psr7\str;
use App\Elastic\Zyhcmx;

/**
 * 病例分析
 */
class CaseServiceV2
{
    public $caseRule = [];

    public function __construct()
    {
        //所有添加的质控规则
        $caseRule = CaseRule::query()->get()->toArray();
        $this->caseRule = array_column($caseRule, null, 'id');
    }

    /**
     *
     * update patient_info set is_defect=0;
     * update setting set content=0 where name="quality_last_zyh";
     * truncate `case_quality`;
     *
     */
    public function qualityContrl()
    {
        // 获取最后一次质控的住院号
        $setName = 'quality_last_zyh_v2';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        // 获取新想需要质控的病例信息
        $piesService = new ElasticsearchService('patient_info');
        $startTime = date("Y-m-d H:i:s", time() - 48 * 3600);
        $endTime = date("Y-m-d H:i:s", time() - 24 * 3600);
        while (true) {
            $params = $piesService->clearMust()
//                ->queryByMust(['range' => ['AAC01' => ['gt' => "2022-01-01 00:00:00"]]])
                ->queryByMust(['range' => ['AAC01' => ['gt' => $startTime, 'lt' => $endTime]]])
                ->queryByMust(['range' => ['MED_REC_ID' => ['gt' => $lastId]]])
                ->paginate(1, 1000)
                ->orderBy('MED_REC_ID', 'asc')
                ->source(['MED_REC_ID', 'AAC04', 'AAB01', 'AAC01', 'AAA28'])
                ->getParams();
            $res = app('es')->search($params);
            $resData = $piesService->getDataByEs($res);
            if (empty($resData[1])) {
                break;
            }

            // 数据处理
            foreach ($resData[0] as $info) {
                $info['MED_REC_ID'] = (string)$info['MED_REC_ID'];
                var_dump($info['MED_REC_ID']);
                $this->getPatientScore($info, 0);
            }

            // 更新
            $lastInfo = array_pop($resData[0]);
            $lastId = $lastInfo['MED_REC_ID'];
        }
        Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);
    }

    /**
     * @param array $info 病例信息
     * @return float|int|true
     * 获取病例的总得分
     */
    public function getPatientScore($info = [], $isGetScore=0)
    {
        // 获取规则
        $caseRule = $this->caseRule;
        $score = $this->quality($info);

        // 质控规则服务层
        $szzkService = new SzzkService();
        // 调用质控规则，返回分值
        $score1 = $szzkService->qualityControl($caseRule, $info);

        $scoreRes = 100 - $score - $score1;
        if(!$isGetScore){
            PatientInfoTarget::query()->updateOrInsert(
                ['ZYH' => $info['MED_REC_ID']],
                ['score' => $scoreRes, 'numerator_A' => ($scoreRes >= 90 ? 1 : 0)]
            );
            PatientInfoTargetTemporary::query()->updateOrInsert(
                ['ZYH' => $info['MED_REC_ID']],
                ['score' => $scoreRes, 'numerator_A' => ($scoreRes >= 90 ? 1 : 0)]
            );
        }

        return $scoreRes;
    }

    /**
     * 病例质控的所有指标集合
     */
    public function quality($info = [])
    {
        $errorNotice = [];

        $method = [
            'checkRy8', // 入院后8小时内完成首次病程记录
            'kjy',// 有抗菌药，开嘱时间+24小时 内要有病程记录
            'rule132',// 日常病程
            'rule114', // 护士分床后48小时内，病程记录中，需要有一个 副高或正高签名（首次病程除外）
            'rule118', // 术后3天连续病程记录
            'rule125',// 出院之前    (出院当天或出院前一天  ），需要写一个病程（首次病程除外）或 24小时出入院

//            'checkCt', // 检查报告单有ct（OCT除外）结果，24小时内要写病程记录
//            'checkMr', // 检查报告单有mr结果，24小时内要写病程记录，或 24小时内出入院记录：关键字 "MR” 或 “磁共振”
//            'hly', // 有化疗药，开嘱时间+24小时 内要有病程记录
//            'sq24', // 术前24小时内，有术者查房（搜签名）
//            'sh24',// 术后24小时内，有术者查房（搜签名）
//            'zyts7',// 住院天数≥7天，病程记录：副高或正高级职称一周2次，中级职称一周3次
//            'rule115', // 术前小结及术前讨论结论记录 在医嘱之前
//            'rule116', // 术后即刻完成（当天完成）
//            'rule117', // 术后48小时内须有术者查房记录
//            'rule119',  // 会诊记录，当天完成
//            'rule120', // 会诊记录单，当天完成
//            'rule121',// 阶段小结
//            'rule122', // 由转入科室医师于患者转入后24小时内完成
//            'rule123',// 抢救记录在开嘱6小时内
//            'rule124',// 输血记录 - 输血当天要有病程记录
//            'rule133',// 医嘱中 YDYZLB=901 医嘱名称中含“病危” ，  XZJDSJ 开始 - TZQRSJ 止，每1天有病程  （包含当天）             病程记录：通过 表：EMR_BL_BL01 中【BLLB =294 且 mblb=32的剔除且 blzt不等于9】中【blbh】去
//            'rule134',// 医嘱中 YDYZLB=901 医嘱名称中含“病重” ，  XZJDSJ 开始 - TZQRSJ 止，每2天有病程  （包含当天）             病程记录：通过 表：EMR_BL_BL01 中【BLLB =294 且 mblb=32的剔除且 blzt不等于9】中【blbh】去
        ];

        foreach ($method as $m) {
            $errorNotice[] = $this->$m($info);
        }

        $errorNotice = array_filter($errorNotice);
        $score = 0;
        if ($errorNotice) {
            $score = array_sum(array_column($errorNotice, 'score'));
            PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_defect_v2' => 1]);
            foreach ($errorNotice as $key => $v) {
                CaseQualityV2::query()->updateOrInsert(
                    ['rule_id' => $v['rule_id'], 'JZHM' => $v['JZHM']],
                    ['code' => $v['code'], 'basis' => $v['basis'], 'error_field' => $v['error_field']]
                );
            }
        }
        return $score;
    }

    public function qualityControl($caseRule = [], $info = [])
    {

        $errorNotice = [];

        $errorNotice[] = $this->jbzd($info);
        $errorNotice = array_filter($errorNotice);
        if ($errorNotice) {
            var_dump(count($errorNotice));
            PatientInfo::query()->where('MED_REC_ID', '=', $info['MED_REC_ID'])->update(['is_defect_v2' => 1]);
            CaseQualityV2::addData($errorNotice);
        }
    }

    /**
     * @return array
     * 首次病程记录 鉴别诊断条数 小于 2
     */
    public
    function jbzd($info = []): array
    {
        $errorNotice = [];
        $caseRule = $this->caseRule;
        $bl01esService = new ElasticsearchService('bl01_202303');
        // 首次病程记录 鉴别诊断条数 小于 2
        $params = $bl01esService->clearMust()
            ->queryByMust(['term' => ['JZHM' => $info['MED_REC_ID']]])
            ->queryByMust(['term' => ['MBLB' => 295]])
            ->getParams();

        $res = app('es')->search($params);
        $resData = $bl01esService->getDataByEs($res);

        if (!empty($resData[0])) {
            $caseContent = $resData[0][0]['HJNR'];
            // 整理数据
            $caseContent = str_replace("\r\n", "!!", $caseContent);
            $caseContent = str_replace("\n", "!!", $caseContent);
            $caseContent = str_replace(" ", "", $caseContent);
            $caseContent = str_replace("：", ":", $caseContent);
            $caseContent = str_replace("“", "\"", $caseContent);
            $caseContent = str_replace("”", "\"", $caseContent);
            preg_match_all("/鉴别诊断:(.*?)诊疗计划/u", $caseContent, $jbzd);
            $jbzdArr = [];

            if (array_filter($jbzd)) {
                $jbzdArr = explode('!!', $jbzd[1][0]);
            }
            if (count($jbzdArr) < 3) {
                preg_match_all("/(.*?):/u", Arr::get($jbzdArr, 0), $res1);
                if (array_filter($res1)) {
                    $basis = [['鉴别诊断【' . $res1[1][0] . '】']];
                } else {
                    $str = str_replace("!!", "", $jbzd[1]);
                    $basis = [$str];
                }
                $errorNotice = [
                    'basis' => json_encode($basis, 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => 126,
                    'notice' => '必须要有鉴别诊断，数量>=2',
                    'code' => 'bingcheng_first',
                    'error_field' => $caseRule[101]['title']
                ];
            }
        }
        return $errorNotice;
    }


    /**
     * @return array
     */
    public function rule133($info = [])
    {
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        $yzbesService = new ElasticsearchService('yzb_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');
        $params = $yzbesService
            ->queryByMust(['term' => ['ZYH' => $info['MED_REC_ID']]])
            ->queryByMust(['match_phrase' => ['YZMC' => '病危']])
            ->getParams();
        $yzb = app('es')->search($params);
        $yzb = $yzbesService->getDataByEs($yzb);
        if (empty($yzb[1])) {
            return $errorNotice;
        }

        $startTime = date("Y-m-d", strtotime($yzb[0][0]['XZJDSJ']));
        $startTime = date("Y-m-d H:i:s", strtotime($startTime));
        $endTime = $yzb[0][0]['TZQRSJ'];

        while (true) {
            $toTime = date("Y-m-d H:i:s", strtotime($startTime) + 24 * 3600 - 1);

            if (strtotime($toTime) > strtotime($endTime)) {
                break;
            }
            $bl01must = [
                ['term' => ['BLLB' => 294]],
                ['range' => ['ZXSJ' => ['gt' => $startTime, 'lt' => $toTime]]]
            ];

            $params = $bl01esService->clearMust()
                ->queryByMustNot(['term' => ['BLZT' => 9]])
                ->queryByMustNot(['term' => ['mblb' => 32]])
                ->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ', 'BLMC'])->getParams();
            $bl01Res = app('es')->search($params);
            $bl01Res = $bl01esService->getDataByEs($bl01Res);

            $basis = [];
            $basis[] = '时间段【' . substr($toTime, 0, 10) . '】，';
            if (empty($bl01Res[1])) {
                $basis[] = '病程记录【无】';
                $basisList[] = $basis;
            } elseif (strtotime($bl01Res[0][0]['CJSJ']) < strtotime($startTime) || strtotime($bl01Res[0][0]['CJSJ']) > strtotime($toTime)) {
                $basis[] = '病程记录【' . $bl01Res[0][0]['BLMC'] . '、超24小时】';
                $basisList[] = $basis;
            }
            $startTime = $toTime;
        }

        if ($basisList) {
            array_unshift($basisList, ['病危：' . $yzb[0][0]['XZJDSJ'] . ' - ' . $endTime]);
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 133,
                'code' => 'rcbc',
                'error_field' => $caseRule[133]['title']
            ];
        }

        return $errorNotice;
    }

    /**
     * @return array
     */
    public function rule134($info = [])
    {
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        $yzbesService = new ElasticsearchService('yzb_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');
        $params = $yzbesService
            ->queryByMust(['term' => ['ZYH' => $info['MED_REC_ID']]])
            ->queryByMust(['match_phrase' => ['YZMC' => '病重']])
            ->source(['XZJDSJ', 'TZQRSJ'])
            ->getParams();
        $yzb = app('es')->search($params);
        $yzb = $bl01esService->getDataByEs($yzb);
        if (empty($yzb[1])) {
            return $errorNotice;
        }

        $startTime = date("Y-m-d", strtotime($yzb[0][0]['XZJDSJ']));
        $startTime = date("Y-m-d H:i:s", strtotime($startTime));
        $endTime = $yzb[0][0]['TZQRSJ'];
        if (strtotime($startTime) > strtotime($endTime)) {
            return $errorNotice;
        }

        while (true) {
            $toTime = date("Y-m-d H:i:s", strtotime($startTime) + 2 * 24 * 3600);

            // 从开始时间加7天超过住院结束时间，则失败
            if (strtotime($toTime) > strtotime($endTime)) {
                break;
            }
            $bl01must = [
                ['term' => ['BLLB' => 294]],
                ['range' => ['CJSJ' => ['gt' => $startTime, 'lt' => $toTime]]]
            ];

            $params = $bl01esService->clearMust()
                ->queryByMustNot(['term' => ['BLZT' => 9]])
                ->queryByMustNot(['term' => ['mblb' => 32]])
                ->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ', 'BLMC'])->getParams();
            $bl01Res = app('es')->search($params);
            $bl01Res = $bl01esService->getDataByEs($bl01Res);

            $basis = [];
            if (empty($bl01Res[1])) {
                $basis[] = '时间段【' . substr($startTime, 0, 10) . '-' . substr($toTime, 0, 10) . '】，';
                $basis[] = '病程记录【无】';
                $basisList[] = $basis;
            }
            $startTime = $toTime;
        }

        if ($basisList) {
            array_unshift($basisList, ['病重：' . $yzb[0][0]['XZJDSJ'] . ' - ' . $endTime]);
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 134,
                'code' => 'rcbc',
                'error_field' => $caseRule[134]['title']
            ];
        }

        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 日常病程记录
     */
    public function rule132($info = [])
    {
        $errorNotice = [];
        $basisList = [];
        $startTime = $info['AAB01'];
        $endTime = $info['AAC01'];
        if (empty($startTime) || empty($endTime)) {
            return $errorNotice;
        }
        $caseRule = $this->caseRule;
        $bl01esService = new ElasticsearchService('bl01_202303');
        $startTime = date("Y-m-d H:i:s", strtotime(date('Y-m-d', strtotime($startTime))));

        while (true) {
            $toTime = date("Y-m-d H:i:s", strtotime($startTime) + 3 * 24 * 3600);

            // 从开始时间加7天超过住院结束时间，则失败
            if (strtotime($toTime) > strtotime($endTime)) {
                break;
            }
            $bl01must = [
                ['term' => ['BLLB' => 294]],
                ['range' => ['ZXSJ' => ['gt' => $startTime, 'lt' => $toTime]]]
            ];

            $params = $bl01esService->clearMust()
                ->queryByMust(['term' => ['JZHM' => $info['MED_REC_ID']]])
                ->queryByMustNot(['term' => ['BLZT' => 9]])
                ->queryByMustNot(['term' => ['mblb' => 32]])
                ->queryByMustBatch($bl01must)->source(['BLBH', 'ZXSJ', 'BLMC'])->getParams();
            $bl01Res = app('es')->search($params);
            $bl01Res = $bl01esService->getDataByEs($bl01Res);

            $basis = [];
            $basis[] = '时间段【' . substr($startTime, 0, 10) . ' / ' . substr(date("Y-m-d H:i:s", strtotime($toTime) - 1), 0, 10) . '】，';
            if (empty($bl01Res[1])) {
                $basis[] = '病程记录【无】';
                $basisList[] = $basis;
            } else {
                foreach ($bl01Res[0] as $item) {
                    if (strtotime($item['ZXSJ']) < strtotime($startTime) || strtotime($item['ZXSJ']) > strtotime($toTime)) {
                        $basis[] = '【' . $item['BLMC'] . '创建时间超24小时】';
                    } else {
                        $basis = [];
                        break;
                    }
                }
                if (count($basis)) {
                    $basisList[] = $basis;
                }
            }
            $startTime = $toTime;
        }

        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 132,
                'code' => 'rcbc',
                'error_field' => $caseRule[132]['title'],
                'score' => 2
            ];
        }

        return $errorNotice;
    }


    /**
     * @param array $info
     * @return array
     * 出院上级医师查房记录
     */
    public function rule125($info = [])
    {
        $errorNotice = [];
        $basis = [];
        $aac01 = $info['AAC01'];
        $caseRule = $this->caseRule;
        $bl01esService = new ElasticsearchService('bl01_202303');
        $startTime = date('Y-m-d H:i:s', strtotime(date('Y-m-d', strtotime($aac01))) - 24 * 3600);

        // 【EMR_BL_BL01】【BLLB =294 且 mblb=32的剔除且 blzt不等于9】【cjSJ】   或
        //  24小时出入院记录：表：EMR_BL_BL01中【BLLB =18 且 blzt≠9】的【CJSJ】
        $bl01must = [
            ['term' => ["JZHM" => $info['MED_REC_ID']]],
            ['terms' => ["BLLB" => [294, 18]]],
            ['range' => ["ZXSJ" => ['from' => $startTime, 'to' => date('Y-m-d H:i:s', strtotime($startTime) + 48 * 3600)]]]
        ];
        $params = $bl01esService->clearMust()
            ->queryByMustNot(['term' => ['BLZT' => 9]])
            ->queryByMustNot(['terms' => ['MBLB' => [32, 295]]])
            ->queryByMustBatch($bl01must)->source(['BLBH', 'ZXSJ', 'BLMC'])->getParams();
        $bl01Res = app('es')->search($params);
        $bl01Res = $bl01esService->getDataByEs($bl01Res);
        if (empty($bl01Res[1])) {
            $basis[] = '出院时间【' . $aac01 . '】';
            $basis[] = '病程记录【无】';
        } elseif (strtotime($bl01Res[0][0]['ZXSJ']) < strtotime($startTime) || strtotime($bl01Res[0][0]['ZXSJ']) > strtotime($startTime) + 48 * 3600) {
            $basis[] = '【' . $bl01Res[0][0]['BLMC'] . '、创建时间超24小时】';
        }
        if ($basis) {
            $errorNotice = [
                'basis' => json_encode([$basis], 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 125,
                'code' => 'cyjl', // 出院记录
                'error_field' => $caseRule[125]['title'],
                'score' => 2
            ];
        }
        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 输血当天要有病程记录
     */
    public function rule124($info = [])
    {
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        $yzbesService = new ElasticsearchService('yzb_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');
        // 获取医嘱本信息
        $aggs = [
            "KZSJ" => [
                "date_histogram" => [
                    "field" => "KZSJ",
                    "calendar_interval" => "day",
                    "format" => "yyyy-MM-dd",
                    "keyed" => true
                ],
            ]
        ];
        $params = $yzbesService->clearMust()
            ->queryByMust(['term' => ['ZYH' => $info['MED_REC_ID']]])
            ->queryByMust(['term' => ['YZQX' => '2']])
            ->queryByMust(['match_phrase' => ['YZMC' => '输']])
            ->queryByShould(['match_phrase' => ['YZMC' => '备血']])
            ->queryByShould(['match_phrase' => ['YZMC' => '配血']])
            ->minimumShouldMatch(1)
            ->source(['KZSJ'])
            ->aggs($aggs)
            ->getParams();
        $res = app('es')->search($params);
        $resData = $yzbesService->getDataByEsToArray($res);

        if (empty($resData[2]) || empty($resData[2]['KZSJ']) || empty($resData[2]['KZSJ']['buckets'])) {
            return $errorNotice;
        }
        $esData = $resData[2]['KZSJ']['buckets'];

        foreach ($esData as $item) {
            if (!$item['doc_count']) {
                continue;
            }
            $key_as_string = $item['key_as_string'];
            // 当天的时间
            $kzsj = strtotime(date('Y-m-d', strtotime($key_as_string)));
            $endTime = $kzsj + 48 * 3600;

            // 先搜索MBLB是45的数据，如果没有则搜索bllb=295并且blmc中包含“输血记录”的数据
            $bl01must = [
                ['term' => ["JZHM" => $info['MED_REC_ID']]],
                ['term' => ["MBLB" => 45]],
                ['range' => ["ZXSJ" => ['from' => date('Y-m-d H:i:s', $kzsj), 'to' => date('Y-m-d H:i:s', $endTime)]]]
            ];
            $params = $bl01esService->clearMust()
                ->queryByMustNot(['term' => ['BLZT' => 9]])
                ->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ', 'BLMC'])->getParams();
            $bl01Res = app('es')->search($params);
            $bl01Res = $bl01esService->getDataByEs($bl01Res);
            if (empty($bl01Res[1])) {
                $bl01must = [
                    ['term' => ["JZHM" => $info['MED_REC_ID']]],
                    ['term' => ["BLLB" => 294]],
                    ['match_phrase' => ["BLMC" => "输血记录"]],
                    ['range' => ["ZXSJ" => ['from' => date('Y-m-d H:i:s', $kzsj), 'to' => date('Y-m-d H:i:s', $endTime)]]]
                ];
                $params = $bl01esService->clearMust()
                    ->queryByMustNot(['term' => ['BLZT' => 9]])
                    ->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ', 'BLMC'])->getParams();
                $bl01Res = app('es')->search($params);
                $bl01Res = $bl01esService->getDataByEs($bl01Res);
                $basis = [];
                $basis[] = '输血的开嘱时间【' . substr($key_as_string, 0, 10) . '】';
                if (empty($bl01Res[1])) {
                    $basis[] = '病程时间【无】';
                    $basisList[] = $basis;
                }
            }

            if (!empty($bl01Res[1]) && (strtotime($bl01Res[0][0]['CJSJ']) < $kzsj || strtotime($bl01Res[0][0]['CJSJ']) > $endTime)) {
                $basis[] = '病程时间【' . $bl01Res[0][0]['BLMC'] . '、超出24小时】';
                $basisList[] = $basis;
            }
        }

        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 124,
                'code' => 'sxjl',
                'error_field' => $caseRule[124]['title']
            ];
        }

        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 抢救记录在开嘱6小时内
     */
    public function rule123($info = [])
    {

        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        $yzbesService = new ElasticsearchService('yzb_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');
        // 获取医嘱本信息
        $params = $yzbesService->clearMust()
            ->queryByMust(['term' => ['ZYH' => $info['MED_REC_ID']]])
            ->queryByMust(['match_phrase' => ['YZMC' => '抢救']])
            ->getParams();
        $res = app('es')->search($params);
        $resData = $yzbesService->getDataByEs($res);
        if (empty($resData[1])) {
            return $errorNotice;
        }

        foreach ($resData[0] as $item) {
            $basis = [];
            $kzsj = strtotime($item['KZSJ']);
            $endTime = $kzsj + 6 * 3600;
            // 抢救记录
            $bl01must = [
                ['term' => ["JZHM" => $info['MED_REC_ID']]],
                ['term' => ["BLLB" => 294]],
                ['match_phrase' => ["HJNR" => '抢救记录']],
                ['range' => ["ZXSJ" => ['from' => date('Y-m-d H:i:s', $kzsj), 'to' => date('Y-m-d H:i:s', $endTime)]]]
            ];
            $params = $bl01esService->clearMust()
                ->queryByMustNot(['term' => ['BLZT' => 9]])
                ->queryByMustBatch($bl01must)
                ->source(['BLBH', 'CJSJ'])->getParams();
            $bl01Res = app('es')->search($params);
            $bl01Res = $bl01esService->getDataByEs($bl01Res);
            if (empty($bl01Res[1])) {
                $basis[] = '开嘱时间【' . $item['KZSJ'] . '】';
                $basis[] = '抢救记录病程时间【无】';
            } elseif (strtotime($bl01Res[0][0]['CJSJ']) < $kzsj || strtotime($bl01Res[0][0]['CJSJ']) > $endTime) {
                $basis[] = '开嘱时间【' . $item['KZSJ'] . '】';
                $basis[] = '抢救记录病程时间【' . $bl01Res[0][0]['CJSJ'] . '、超6小时】';
                $basisList[] = $basis;
            }
        }
        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 123,
                'code' => 'qjjl',
                'error_field' => $caseRule[123]['title']
            ];
        }

        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 转入记录
     */
    public function rule122($info = [])
    {

        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;

        $yzbesService = new ElasticsearchService('yzb_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');

        // 获取医嘱本信息
        $params = $yzbesService->clearMust()
            ->queryByMust(['term' => ['ZYH' => $info['MED_REC_ID']]])
            ->queryByMust(['term' => ['YDYZLB' => 901]])
            ->queryByMust(['match_phrase' => ['YZMC' => '转入']])
            ->getParams();
        $res = app('es')->search($params);
        $resData = $yzbesService->getDataByEs($res);
        if (empty($resData[1])) {
            return $errorNotice;
        }
        foreach ($resData[0] as $item) {
            $basis = [];

            $xzjdsj = strtotime($item['XZJDSJ']);
            $endTime = $xzjdsj + 24 * 3600;

            // 搜索24小时内的病程记录
            $bl01must = [
                ['term' => ["JZHM" => $info['MED_REC_ID']]],
                ['term' => ["MBLB" => 30]],
                ['match_phrase' => ["BLMC" => '转入记录']],
                ['range' => ["ZXSJ" => ['from' => date('Y-m-d H:i:s', $xzjdsj), 'to' => date('Y-m-d H:i:s', $endTime)]]]
            ];
            $params = $bl01esService->clearMust()
                ->queryByMustNot(['term' => ['BLZT' => 9]])
                ->queryByMustBatch($bl01must)
                ->source(['BLBH', 'CJSJ', 'BLMC'])->getParams();
            $bl01Res = app('es')->search($params);
            $bl01Res = $bl01esService->getDataByEs($bl01Res);

            if (empty($bl01Res[1])) {
                $basis[] = '转入时间【' . $item['XZJDSJ'] . '】';
                $basis[] = '转入记录的病程时间【无】';
            } elseif (strtotime($bl01Res[0][0]['CJSJ']) < $xzjdsj || strtotime($bl01Res[0][0]['CJSJ']) > $endTime) {
                $basis[] = '转入时间【' . $item['XZJDSJ'] . '】';
                $basis[] = '转入记录的病程时间【' . $bl01Res[0][0]['BLMC'] . '、' . $bl01Res[0][0]['CJSJ'] . '超24小时】';
            }
            $basisList[] = $basis;
        }

        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 122,
                'code' => 'zrjl',
                'error_field' => $caseRule[122]['title']
            ];
        }

        return $errorNotice;

    }

    /**
     * @param array $info
     * @return array
     * 阶段小结
     */
    public function rule121($info = [])
    {

        $errorNotice = [];
        $basislist = [];
        $caseRule = $this->caseRule;

        $bl01esService = new ElasticsearchService('bl01_202303');
        // 获取护士分床时间
        $zyHcmxesService = new Zyhcmx();
        $mzRes = $zyHcmxesService->getHCRQ($info['MED_REC_ID']);
        if (empty($mzRes[1])) {
            return $errorNotice;
        }

        $startTime = strtotime($mzRes[0][0]['HCRQ']); // 开始时间
        $basis[] = '护士分床时间【' . $mzRes[0][0]['HCRQ'] . '】';
        $endTime = strtotime($info['AAC01']); // 结束时间
        // 从开始时间加7天超过住院结束时间，则失败
        if (empty($startTime) || empty($endTime)) {
            return $errorNotice;
        }
        // 如果开始时间到出院时间小于30天，则不校验
        if ($startTime + 30 * 24 * 3600 > $endTime) {
            return $errorNotice;
        }

        $flag = 0;
        while (true) {
            $fromTime = $startTime;
            $toTime = $startTime + 30 * 24 * 3600;
            if ($startTime + 30 * 24 * 3600 > $endTime) {
                break;
            }
            // 查找出院小结
            $bl01must = [
                ['term' => ["JZHM" => $info['MED_REC_ID']]],
                ['terms' => ["MBLB" => [26, 30, 35, 98]]],
                ['range' => ["ZXSJ" => ['from' => date('Y-m-d H:i:s', $fromTime), 'to' => date('Y-m-d H:i:s', $toTime)]]]
            ];
            $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ', 'BLMC'])->getParams();
            $bl01Res = app('es')->search($params);
            $bl01Res = $bl01esService->getDataByEs($bl01Res);
            $startTime = $toTime;
            $basis = [];
            $basis[] = '时间段【' . date('Y-m-d H:i:s', $fromTime) . '-' . date('Y-m-d H:i:s', $toTime) . '】';
            if (empty($bl01Res[1])) {
                $basis[] = '阶段小结时间【无】';
                $basislist[] = $basis;
            } elseif (strtotime($bl01Res[0][0]['CJSJ']) < $fromTime || strtotime($bl01Res[0][0]['CJSJ']) > $toTime) {
                $basis[] = '阶段小结时间【' . $bl01Res[0][0]['BLMC'] . '、创建时间' . $bl01Res[0][0]['CJSJ'] . '】';
                $basislist[] = $basis;
            }
        }
        if ($flag) {
            $errorNotice = [
                'basis' => json_encode($basislist, 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 121,
                'code' => 'jdxj',
                'error_field' => $caseRule[121]['title']
            ];
        }

        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 住院天数≥7天
     * 病程记录：副高或正高级职称一周2次，中级职称一周3次
     */
    public function zyts7($info = [])
    {

        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        $flag = 1;

        // 住院天数大于7天
        if ($info['AAC04'] >= 7) {

            $bl01esService = new ElasticsearchService('bl01_202303');
            // 获取护士分床时间
            $zyHcmxesService = new Zyhcmx();
            $mzRes = $zyHcmxesService->getHCRQ($info['MED_REC_ID']);
            if (empty($mzRes[1])) {
                return $errorNotice;
            }
            $startTime = $mzRes[0][0]['HCRQ']; // 开始时间
            $endTime = $info['AAC01']; // 结束时间

            // 从开始时间加7天超过住院结束时间，则失败
            if (empty($startTime) || empty($endTime)) {
                return $errorNotice;
            }
            $startTime = date("Y-m-d", strtotime($startTime));
            $startTime = date("Y-m-d H:i:s", strtotime($startTime));
            while (true) {
                $toTime = date("Y-m-d H:i:s", strtotime($startTime) + 7 * 24 * 3600);

                // 从开始时间加7天超过住院结束时间，则失败
                if (strtotime($toTime) > strtotime($endTime)) {
                    break;
                }
                var_dump('时间段【' . substr($startTime, 0, 10) . '-' . substr($toTime, 0, 10) . '】');
                $bl01must = [
                    ['term' => ["JZHM" => $info['MED_REC_ID']]],
                    ['term' => ["BLLB" => 294]],
                    ['range' => ["ZXSJ" => ['from' => $startTime, 'to' => $toTime]]]
                ];
                $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ', 'BLMC', 'ZXSJ'])->getParams();
                $bl01Res = app('es')->search($params);
                $bl01Res = $bl01esService->getDataByEs($bl01Res);

                $basis = [];
                if (empty($bl01Res[1])) {
                    $basis[] = '时间段【' . $startTime . '-' . $toTime . '】';
                    $basis[] = '病程记录【无】';
                    $basisList[] = $basis;
                } else {
                    $bl01294 = empty($bl01Res[0]) ? [] : $bl01Res[0];
                    //  获取7天所有病程中的所有正副科的数据
                    $zheng = $zhong = [];
                    $zhengNum = $zhongnum = 0;

                    foreach ($bl01294 as $item) {

                        $blsy = EMR_BL_BLSY::query()
                            ->where('BLBH', '=', $item['BLBH'])
                            ->get()->toArray();
                        $code = array_column($blsy, 'SYYS');
                        if ($blsy) {
                            $staff = Staff::query()->whereIn('code', $code)->get()->toArray();
                            foreach ($staff as $b) {
                                if (in_array($b['ygjb'], [1, 2])) {
                                    $zhengNum++;
                                    if (strtotime($item['CJSJ']) > $item['ZXSJ']) {
                                        $zheng[] = '【' . $item['BLMC'] . '，' . $b['name'] . '(' . ($b['ygjb'] == 1 ? '正高' : '副高') . ')、创建时间超24小时】';
                                    }
                                } elseif ($b['ygjb'] == 6) {
                                    $zhongnum++;
                                    if (strtotime($item['CJSJ']) > $item['ZXSJ']) {
                                        $zhong[] = '【' . $item['BLMC'] . '，' . $b['name'] . '(中级职称)、创建时间超24小时】';
                                    }
                                }
                            }
                        }
                    }

                    if ($zhengNum < 2 && $zhongnum < 3) {
                        $basis[] = '时间段【' . substr($startTime, 0, 10) . ' / ' . date("Y-m-d", strtotime($toTime) - 1) . '】';
                        $basis[] = '正副高/' . $zhengNum . '次，中级职称/' . $zhongnum . '次';
                        $basis = array_merge($basis, $zheng, $zhong);
                        $basisList[] = $basis;
                    }
                }
                $startTime = $toTime;
            }
        }
        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 113,
                'code' => 'bingcheng_first',
                'error_field' => $caseRule[113]['title']
            ];
        }
        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 护士分床后48小时内，病程记录中，需要有一个 副高或正高签名（首次病程除外）
     */
    public function rule114($info = [])
    {

        $errorNotice = [];
        $basis = [];
        $caseRule = $this->caseRule;

        $bl01esService = new ElasticsearchService('bl01_202303');
        // 获取护士分床时间
        $zyHcmxesService = new Zyhcmx();
        $mzRes = $zyHcmxesService->getHCRQ($info['MED_REC_ID']);
        if (empty($mzRes[1])) {
            return $errorNotice;
        }

        $startTime = $mzRes[0][0]['HCRQ']; // 开始时间
        $basis[] = '护士分床时间【' . $startTime . '】';
        $endTime = date("Y-m-d H:i:s", strtotime($startTime) + 2 * 24 * 3600);

        // 从开始时间加7天超过住院结束时间，则失败
        if (empty($endTime) || empty($endTime)) {
            return $errorNotice;
        }

        $bl01must = [
            ['term' => ["JZHM" => $info['MED_REC_ID']]],
            ['term' => ["BLLB" => 294]],
            ['range' => ["ZXSJ" => ['from' => $startTime, 'to' => $endTime]]]
        ];
        $params = $bl01esService->clearMust()->queryByMustBatch($bl01must)->source(['BLBH', 'ZXSJ'])->getParams();
        $bl01Res = app('es')->search($params);
        $bl01Res = $bl01esService->getDataByEs($bl01Res);
        if (empty($bl01Res[1])) {
            $basis[] = '病程记录【无】';
            $errorNotice = [
                'basis' => json_encode([$basis], 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 114,
                'code' => 'fenchuanghouchafang',
                'error_field' => $caseRule[114]['title'],
                'score' => 10
            ];
        } else {
            $bl01294 = empty($bl01Res[0]) ? [] : $bl01Res[0];
            $zheng = [];
            foreach ($bl01294 as $item) {
                $blsy = EMR_BL_BLSY::query()
                    ->where('BLBH', '=', $item['BLBH'])
                    ->get()->toArray();
                $code = array_column($blsy, 'SYYS');
                if ($blsy) {
                    $staff = Staff::query()->whereIn('code', $code)->get()->toArray();
                    foreach ($staff as $b) {
                        if (in_array($b['ygjb'], [1, 2])) {
                            $zheng[] = $item['ZXSJ'];
                        }
                    }
                }
            }
            if (empty($zheng)) {
                $basis[] = '副高或正高签名【无】';
                $errorNotice = [
                    'basis' => json_encode([$basis], 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => 114,
                    'code' => 'bingcheng_first',
                    'error_field' => $caseRule[114]['title'],
                    'score' => 10
                ];
            }
        }
        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 术前小结及术前讨论结论记录 在医嘱之前
     */
    public function rule115($info = [])
    {

        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        $sssqesService = new ElasticsearchService('sssq_2023');
        $mustNot = ['term' => ['BLZT' => 9]];
        $bl01must = [
            ['term' => ["ZYH" => $info['MED_REC_ID']]],
            ['term' => ["zfbz" => 0]],
        ];
        $params = $sssqesService->clearMust()->queryByMustNot($mustNot)->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ'])->getParams();
        $res = app('es')->search($params);
        $resData = $sssqesService->getDataByEs($res);
        if (!empty($resData[1])) {
            foreach ($resData[0] as $item) {
                // yzb中含有关键字   【拟***年*月*日 】的数据
                $sssqesService = new ElasticsearchService('yzb_2023');
                $bl01must = [
                    ['term' => ["ZYH" => $info['MED_REC_ID']]],
                    ['terms' => ["is_operation" => 1]],
                    ['match_phrase' => ['YZMC' => $item['NSSMC']]]
                ];
                $params = $sssqesService->clearMust()->queryByMustNot($mustNot)->queryByMustBatch($bl01must)->source(['KZSJ'])->getParams();
                $res = app('es')->search($params);
                $resData = $sssqesService->getDataByEs($res);
                if (!empty($resData[1])) {

                    // 术前小结及术前讨论结论记录
                    $bl01esService = new ElasticsearchService('bl01_202303');
                    $bl01must = [
                        ['term' => ["JZHM" => $info['MED_REC_ID']]],
                        ['terms' => ["MBLB" => [82]]],
                        ['range' => ['ZXSJ' => ['lt' => $resData[0][0]['KZSJ']]]]
                    ];
                    $params = $bl01esService->clearMust()
                        ->queryByMustNot($mustNot)
                        ->orderBy('ZXSJ', 'asc')
                        ->paginate(1, 1)
                        ->queryByMustBatch($bl01must)
                        ->source(['BLBH', 'CJSJ'])->getParams();
                    $bl01Res = app('es')->search($params);
                    $bl01Res = $bl01esService->getDataByEs($bl01Res);
                    if (empty($bl01Res[1])) {
                        $basis = [];
                        $basis[] = '手术名称【' . $item['NSSMC'] . '】';
                        $basis[] = '开嘱时间【' . $resData[0][0]['KZSJ'] . '】';
                        $basis[] = '术前小结及术前讨论结论记录【无】';
                        $basisList[] = $basis;
                    } else {
                        $cjsj = $bl01Res[0][0]['CJSJ'];
                        if (strtotime($cjsj) > strtotime($resData[0][0]['KZSJ'])) {
                            $basis = [];
                            $basis[] = '手术名称【' . $item['NSSMC'] . '】';
                            $basis[] = '开嘱时间【' . $resData[0][0]['KZSJ'] . '】';
                            $basis[] = '术前小结及术前讨论结论记录【' . $cjsj . '不在开嘱时间之前】';
                            $basisList[] = $basis;
                        }
                    }
                }
            }
        }
        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 115,
                'code' => 'sqxj',
                'error_field' => $caseRule[115]['title']
            ];
        }

        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 术后即刻完成（当天完成）
     */
    public function rule116($info = [])
    {

        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;

        $bl01esService = new ElasticsearchService('bl01_202303');
        // 手术记录找术者
        $bl01must = [
            ['term' => ["BLLB" => 303]],
            ['term' => ["JZHM" => $info['MED_REC_ID']]],
            ['match_phrase' => ['BLMC' => '手术记录']],
        ];
        $params = $bl01esService->clearMust()
            ->queryByMustNot(['term' => ['BLZT' => 9]])
            ->queryByMustBatch($bl01must)
            ->queryByShould(['terms' => ['MBLB' => [306, 74]]])
            ->minimumShouldMatch(1)
            ->source(['BLBH', 'JZHM', 'CJSJ', 'operation_handler', 'operation_time', 'MBLB', 'BLLB', 'BLMC'])
            ->paginate(1, 1000)
            ->getParams();

        $bl01Res = app('es')->search($params);
        $mzRes = $bl01esService->getDataByEs($bl01Res);
        if (!empty($mzRes[1])) {
            foreach ($mzRes[0] as $v) {
                $basis = [];
                $startTime = $v['operation_time'];
                if ($startTime) {
                    $basis[] = '手术日期【' . date("Y-m-d", $startTime) . '】';
                    $from = date("Y-m-d H:i:s", $startTime);
                    $to = date("Y-m-d H:i:s", $startTime + 48 * 3600);
                    $bl01must = [
                        ['term' => ["JZHM" => $info['MED_REC_ID']]],
                        ['term' => ["MBLB" => 54]],
                        [
                            'range' => [
                                "ZXSJ" => [
                                    'from' => $from,
                                    'to' => $to
                                ]
                            ]
                        ]
                    ];
                    $mustNot = ['term' => ['BLZT' => 9]];
                    $params = $bl01esService->clearMust()->queryByMustNot($mustNot)->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ', 'BLMC'])->getParams();
                    $bl01Res = app('es')->search($params);
                    $bl01Res = $bl01esService->getDataByEs($bl01Res);

                    if (empty($bl01Res[1])) {
                        $basis[] = '术后首次病程【无】';
                        $basisList[] = $basis;
                    } elseif (strtotime($bl01Res[0][0]['CJSJ']) > 24 * 3600 + $startTime) {
                        $basis[] = '术后首次病程【' . $bl01Res[0][0]['BLMC'] . '、超时】';
                        $basisList[] = $basis;
                    }

                }
            }
        }
        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 116,
                'code' => 'fenchuanghouchafang',
                'error_field' => $caseRule[116]['title']
            ];
        }

        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 术后48小时内须有术者查房记录
     */
    public function rule117($info = [])
    {

        $zyh = $info['MED_REC_ID'];
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        // 获取麻醉记录的信息，用户获取手术时间
        $mzjlEsService = new ElasticsearchService('mzjl_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');

        // 手术记录找术者
        $bl01must = [
            ['term' => ["JZHM" => $zyh]],
            ['term' => ["BLLB" => 303]],
            ['match_phrase' => ['BLMC' => '手术记录']],
        ];
        $params = $bl01esService->clearMust()
            ->queryByMustNot(['term' => ['BLZT' => 9]])
            ->queryByMustBatch($bl01must)
            ->queryByShould(['terms' => ['MBLB' => [306, 74]]])
            ->minimumShouldMatch(1)
            ->source(['BLBH', 'JZHM', 'CJSJ', 'operation_handler', 'operation_time', 'MBLB', 'BLLB', 'BLMC'])
            ->paginate(1, 1000)
            ->getParams();

        $bl01Res = app('es')->search($params);
        $bl01Res = $bl01esService->getDataByEs($bl01Res);

        if (!empty($bl01Res[1])) {
            foreach ($bl01Res[0] as $b) {
                $operation_time = $b['operation_time'];
                $start = date('Y-m-d H:i:s', $operation_time);
                $end = date('Y-m-d H:i:s', $operation_time + 48 * 3600);
                $params = $mzjlEsService->clearMust()
                    ->queryByMust(['term' => ['HOSPIZATIONID' => $b['JZHM']]])
                    ->queryByMust(['range' => ['OPERATESTARTTIME' => ['gt' => $start, 'lt' => $end]]])
                    ->getParams();
                $res = app('es')->search($params);
                $resData = $mzjlEsService->getDataByEs($res);
                if (!empty($resData[1])) {
                    $startTime = $resData[0][0]['OPERATEENDTIME'];
                } else {
                    $bl = EMR_BL_BL01::query()->select(['BLBH', 'surgery_content'])->where('BLBH', '=', $b['BLBH'])->get()->toArray();

                    $surgery_content = !empty($bl[0]['surgery_content']) ? json_decode($bl[0]['surgery_content'], true) : '';
                    $startTime = '';
                    if ($surgery_content) {
                        $ssrq = str_replace(' ', '', $surgery_content['sssj']);
                        $ssrq = explode('-', $ssrq);
                        if (!empty($ssrq[1])) {
                            $ssrq = $ssrq[1];
                            $startTime = date('Y-m-d H:i:s', strtotime($surgery_content['ssrq'] . ' ' . $ssrq));
                        }
                    }
                }
                if (empty($startTime) || !strtotime($startTime)) {
                    break;
                }
                $basis = [];
                // 如果手术时间存在，则获取手术时间48内的所有病程信息
                $endTime = date("Y-m-d H:i:s", strtotime($startTime) + 48 * 3600);
                $bl01must = [
                    ['term' => ["JZHM" => $zyh]],
                    ['term' => ["BLLB" => 294]],
                    ['range' => ["ZXSJ" => ['gt' => $startTime, 'lt' => $endTime]]]
                ];
                $params = $bl01esService->clearMust()
                    ->queryByMustNot(['terms' => ['MBLB' => [32, 54]]])
                    ->queryByMustNot(['term' => ['BLZT' => 9]])
                    ->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ', 'BLMC'])->getParams();
                $bl01Res = app('es')->search($params);
                $bl01Res = $bl01esService->getDataByEs($bl01Res);
                // 如果病程信息不存在，则提示质控错误信息
                if (empty($bl01Res[1])) {
                    $basis[0] = '手术结束时间【' . $startTime . '】';
                    $basis[1] = '术者【' . $b['operation_handler'] . '】';
                    $basis[2] = '病程记录【无】';
                } else {
                    $res = $bl01Res[0];
                    $zfzhi = $this->shuzhe($res, $b['operation_handler']);

                    if (!empty($zfzhi[0]) &&
                        strtotime($bl01Res[0][0]['CJSJ']) < strtotime($endTime) ||
                        strtotime($bl01Res[0][0]['CJSJ']) > strtotime($startTime)
                    ) {
                        $basis[2] = '病程记录【' . $zfzhi[1] . '、创建时间' . $bl01Res[0][0]['CJSJ'] . '超48小时】';
                    } else {
                        $basis[2] = '病程记录【无】';
                    }
                }

                if ($basis) {
                    $basisList[] = $basis;
                }
            }
        }
        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $zyh,
                'rule_id' => 117,
                'code' => 'sq48',
                'error_field' => $caseRule[117]['title']
            ];
        }
        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 会诊记录，当天完成
     */
    public function rule119($info = [])
    {

        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;

        $yzbesService = new ElasticsearchService('yzb_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');

        // 获取医嘱本信息
        $params = $yzbesService->clearMust()
            ->queryByMust(['term' => ['ZYH' => $info['MED_REC_ID']]])
            ->queryByMust(['term' => ['YDYZLB' => 901]])
            ->queryByMust(['match_phrase' => ['YZMC' => '会诊']])
            ->queryByMustNot(['match_phrase' => ['YZMC' => 'PICC']])
            ->queryByMustNot(['match_phrase' => ['YZMC' => '取消']])
            ->source(['XZJDSJ', 'KZSJ'])
            ->getParams();
        $res = app('es')->search($params);
        $resData = $yzbesService->getDataByEs($res);
        if (!empty($resData[1])) {
            foreach ($resData[0] as $item) {
                $basis = [];
                $XZJDSJ = strtotime(date("Y-m-d", strtotime($item['XZJDSJ'])));
                $basis[] = '医嘱会诊时间【' . $item['XZJDSJ'] . '】';
                if (!empty($XZJDSJ)) {

                    $bl01must = [
                        ['term' => ["JZHM" => $info['MED_REC_ID']]],
                        ['term' => ["MBLB" => 32]],
                        [
                            'range' => [
                                "ZXSJ" => [
                                    'from' => date("Y-m-d H:i:s", $XZJDSJ),
                                    'to' => date("Y-m-d H:i:s", $XZJDSJ + 24 * 3600)
                                ]
                            ]
                        ]
                    ];
                    $mustNot = [
                        ['term' => ['BLZT' => 9]],
                    ];
                    $params = $bl01esService->clearMust()
                        ->queryByMustNotBatch($mustNot)
                        ->queryByMustBatch($bl01must)
                        ->source(['BLBH', 'CJSJ'])->getParams();
                    $bl01Res = app('es')->search($params);
                    $bl01Res = $bl01esService->getDataByEs($bl01Res);
                    if (!empty($bl01Res[1])) {
                        $bl01294 = empty($bl01Res[0]) ? [] : $bl01Res[0];
                        $zheng = [];
                        foreach ($bl01294 as $item) {
                            $blsy = EMR_BL_BLSY::query()
                                ->where('BLBH', '=', $item['BLBH'])
                                ->get()->toArray();
                            $code = array_column($blsy, 'SYYS');
                            if ($blsy) {
                                $staff = Staff::query()->whereIn('code', $code)->get()->toArray();
                                foreach ($staff as $b) {
                                    if (in_array($b['ygjb'], [1, 2])) {
                                        $zheng[] = $item['CJSJ'];
                                    }
                                }
                            }
                        }
                        if (empty($zheng)) {
                            $basis[] = '会诊记录【无】';
                            $basisList[] = $basis;
                        }
                    } else {
                        $basis[] = '会诊记录【无】';
                        $basisList[] = $basis;
                    }
                }
            }
        }

        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 119,
                'code' => 'sh48',
                'error_field' => $caseRule[119]['title']
            ];
        }

        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 会诊记录单，当天完成
     */
    public function rule120($info = [])
    {

        $errorNotice = [];
        $basis = [];
        $caseRule = $this->caseRule;

        $yzbesService = new ElasticsearchService('yzb_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');

        // 获取医嘱本信息
        $params = $yzbesService->clearMust()
            ->queryByMust(['term' => ['ZYH' => $info['MED_REC_ID']]])
            ->queryByMust(['term' => ['YDYZLB' => 901]])
            ->queryByMust(['match_phrase' => ['YZMC' => '会诊']])
            ->queryByMustNot(['match_phrase' => ['YZMC' => 'PICC']])
            ->queryByMustNot(['match_phrase' => ['YZMC' => '取消']])
            ->getParams();
        $res = app('es')->search($params);
        $resData = $yzbesService->getDataByEs($res);
        if (!empty($resData[1])) {
            $XZJDSJ = strtotime($resData[0][0]['XZJDSJ']);
            if (!empty($XZJDSJ)) {

                $bl01must = [
                    ['term' => ["JZHM" => $info['MED_REC_ID']]],
                    ['term' => ["MBLB" => 32]],
                    [
                        'range' => [
                            "CJSJ" => [
                                'from' => date("Y-m-d H:i:s", $XZJDSJ),
                                'to' => date("Y-m-d H:i:s", $XZJDSJ + 24 * 3600)
                            ]
                        ]
                    ]
                ];
                $mustNot = [
                    ['term' => ['BLZT' => 9]],
                ];
                $params = $bl01esService->clearMust()->queryByMustNotBatch($mustNot)->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ'])->getParams();
                $bl01Res = app('es')->search($params);
                $bl01Res = $bl01esService->getDataByEs($bl01Res);
                if (!empty($bl01Res[1])) {
                    $bl01294 = empty($bl01Res[0]) ? [] : $bl01Res[0];
                    $zheng = [];
                    foreach ($bl01294 as $item) {
                        $blsy = EMR_BL_BLSY::query()
                            ->where('BLBH', '=', $item['BLBH'])
                            ->get()->toArray();
                        $code = array_column($blsy, 'SYYS');
                        if ($blsy) {
                            $staff = Staff::query()->whereIn('code', $code)->get()->toArray();
                            foreach ($staff as $b) {
                                if (in_array($b['ygjb'], [1, 2])) {
                                    $zheng[] = $item['CJSJ'];
                                }
                            }
                        }
                    }
                    if (empty($zheng)) {
                        $basis[] = '会诊记录【无正或副高签名】';
                        $errorNotice = [
                            'basis' => json_encode($basis),
                            'JZHM' => $info['MED_REC_ID'],
                            'rule_id' => 119,
                            'code' => 'sh48',
                            'error_field' => $caseRule[119]['title']
                        ];
                    }
                } else {
                    $basis[] = '会诊记录【无】';
                    $errorNotice = [
                        'basis' => json_encode($basis),
                        'JZHM' => $info['MED_REC_ID'],
                        'rule_id' => 119,
                        'code' => 'sh48',
                        'error_field' => $caseRule[119]['title']
                    ];
                }
            }
        }
        return $errorNotice;
    }

    /**
     * @param array $info
     * @return array
     * 术后3天连续病程记录
     */
    public function rule118($info = [])
    {
        $zyh = $info['MED_REC_ID'];
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;

        $mzjlesService = new ElasticsearchService('mzjl_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');

        // 手术记录找术者
        $bl01must = [
            ['term' => ["JZHM" => $zyh]],
            ['term' => ["BLLB" => 303]],
            ['match_phrase' => ['BLMC' => '手术记录']],
        ];
        $params = $bl01esService->clearMust()
            ->queryByMustNot(['term' => ['BLZT' => 9]])
            ->queryByMustBatch($bl01must)
            ->queryByShould(['terms' => ['MBLB' => [306, 74]]])
            ->minimumShouldMatch(1)
            ->source(['BLBH', 'JZHM', 'ZXSJ', 'operation_handler', 'operation_time', 'MBLB', 'BLLB', 'BLMC'])
            ->paginate(1, 1000)
            ->getParams();

        $bl01Res = app('es')->search($params);
        $bl01Res = $bl01esService->getDataByEs($bl01Res);

        if (!empty($bl01Res[1])) {
            foreach ($bl01Res[0] as $b) {
                $basis = [];
                $operation_time = $b['operation_time'];
                $start = date('Y-m-d H:i:s', $operation_time);
                $end = date('Y-m-d H:i:s', $operation_time + 24 * 3600);
                $params = $mzjlesService->clearMust()
                    ->queryByMust(['term' => ['HOSPIZATIONID' => $b['JZHM']]])
                    ->queryByMust(['range' => ['OPERATESTARTTIME' => ['gt' => $start, 'lt' => $end]]])
                    ->getParams();
                $res = app('es')->search($params);
                $resData = $mzjlesService->getDataByEs($res);
                if (!empty($resData[1])) {
                    $startTime = $resData[0][0]['OPERATEENDTIME'];
                } else {
                    $bl = EMR_BL_BL01::query()->select(['BLBH', 'surgery_content'])->where('BLBH', '=', $b['BLBH'])->get()->toArray();

                    $surgery_content = !empty($bl[0]['surgery_content']) ? json_decode($bl[0]['surgery_content'], true) : '';
                    $startTime = '';
                    if ($surgery_content) {
                        $ssrq = str_replace(' ', '', $surgery_content['sssj']);
                        $ssrq = explode('-', $ssrq);
                        if (!empty($ssrq[1])) {
                            $ssrq = $ssrq[1];
                            $startTime = date('Y-m-d H:i:s', strtotime($surgery_content['ssrq'] . ' ' . $ssrq));
                        }
                    }
                }
                if (empty($startTime) || !strtotime($startTime)) {
                    break;
                }

                if ($startTime) {

                    $basis[] = '手术结束时间【' . $startTime . '】';
                    $cysj = strtotime(date("Y-m-d", strtotime($info['AAC01'])));
                    $startTime = strtotime(date("Y-m-d", strtotime($startTime)));
                    // 出院当天, 查出院当天是否有病程
                    if ($cysj == $startTime) {
                        $from = $startTime;
                        $to = $startTime + 24 * 3600;
                        $bingcheng = $this->rule118Helper($info['MED_REC_ID'], $from, $to, 1);
                        if (empty($bingcheng)) {
                            $bingcheng = $this->rule118Helper($info['MED_REC_ID'], $from, $to);
                            if (empty($bingcheng)) {
                                $basis[] = '时间【' . date("Y-m-d", $from) . '】';
                                $basis[] = '病程记录【无】';
                            } else {
                                $cjsj = strtotime($bingcheng[0]['ZXSJ']);
                                if ($cjsj < $from || $cjsj > $to) {
                                    $basis[] = $bingcheng[0]['BLMC'] . '、创建时间超时';
                                }
                            }
                        }
                    } else {
                        if ($cysj && $cysj >= $startTime + 24 * 3600) {

                            $from = $startTime + 24 * 3600;
                            $to = $startTime + 24 * 3600 * 2;
                            $bingcheng = $this->rule118Helper($info['MED_REC_ID'], $from, $to, 1);
                            if (empty($bingcheng)) {
                                $bingcheng = $this->rule118Helper($info['MED_REC_ID'], $from, $to);
                                if (empty($bingcheng)) {
                                    $basis[] = '时间【' . date("Y-m-d", $from) . '】';
                                    $basis[] = '病程记录【无】';
                                } else {
                                    $cjsj = strtotime($bingcheng[0]['ZXSJ']);
                                    if ($cjsj < $from || $cjsj > $to) {
                                        $basis[] = '时间【' . date("Y-m-d", $from) . '】';
                                        $basis[] = $bingcheng[0]['BLMC'] . '、创建时间超时';
                                    }
                                }
                            }
                        }
                        if ($cysj && $cysj >= $startTime + 2 * 24 * 3600) {
                            $from = $startTime + 24 * 3600 * 2;
                            $to = $startTime + 24 * 3600 * 3;
                            $bingcheng = $this->rule118Helper($info['MED_REC_ID'], $from, $to, 1);
                            if (empty($bingcheng)) {
                                $bingcheng = $this->rule118Helper($info['MED_REC_ID'], $from, $to);
                                if (empty($bingcheng)) {
                                    $basis[] = '时间【' . date("Y-m-d", $from) . '】';
                                    $basis[] = '病程记录【无】';
                                } else {
                                    $cjsj = strtotime($bingcheng[0]['ZXSJ']);
                                    if ($cjsj < $from || $cjsj > $to) {
                                        $basis[] = '时间【' . date("Y-m-d", $from) . '】';
                                        $basis[] = $bingcheng[0]['BLMC'] . '、创建时间超时';
                                    }
                                }
                            }
                        }
                        if ($cysj && $cysj >= $startTime + 3 * 24 * 3600) {

                            $from = $startTime + 24 * 3600 * 3;
                            $to = $startTime + 24 * 3600 * 4;
                            $bingcheng = $this->rule118Helper($info['MED_REC_ID'], $from, $to, 1);
                            if (empty($bingcheng)) {
                                $bingcheng = $this->rule118Helper($info['MED_REC_ID'], $from, $to);
                                if (empty($bingcheng)) {
                                    $basis[] = '时间【' . date("Y-m-d", $from) . '】';
                                    $basis[] = '病程记录【无】';
                                } else {
                                    $cjsj = strtotime($bingcheng[0]['ZXSJ']);
                                    if ($cjsj < $from || $cjsj > $to) {
                                        $basis[] = '时间【' . date("Y-m-d", $from) . '】';
                                        $basis[] = $bingcheng[0]['BLMC'] . '、创建时间超时';
                                    }
                                }
                            }
                        }
                    }
                    if (count($basis) > 1) {
                        $basisList[] = $basis;
                    }
                }
            }
        }
        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 118,
                'code' => 'sh3t',
                'error_field' => $caseRule[118]['title'],
                'score' => 1
            ];
        }

        return $errorNotice;
    }

    /**
     * @param string $zyh
     * @param string $from
     * @param string $to
     * 规则118的辅助方法
     */
    public function rule118Helper($zyh = '', $from = '', $to = '', $type = 0)
    {
        $bl01esService = new ElasticsearchService('bl01_202303');
        $bl01must = [
            ['term' => ["JZHM" => $zyh]],
            ['term' => ["BLLB" => 294]],
            [
                'range' => [
                    "ZXSJ" => [
                        'from' => date("Y-m-d H:i:s", $from),
                        'to' => date("Y-m-d H:i:s", $to)
                    ]
                ]
            ]
        ];
        $cjsj = [
            'range' => [
                "ZXSJ" => [
                    'from' => date("Y-m-d H:i:s", $from),
                    'to' => date("Y-m-d H:i:s", $to)
                ]
            ]
        ];
        // 如果type=1则是获取包含创建条件的搜索
        if ($type) {
            $bl01must[] = $cjsj;
        }
        $mustNot = [
            ['term' => ['BLZT' => 9]],
            ['terms' => ['MBLB' => [54, 32]]],
        ];
        $params = $bl01esService->clearMust()
            ->queryByMustNotBatch($mustNot)
            ->queryByMustBatch($bl01must)
            ->orderBy("ZXSJ", "asc")
            ->source(['BLBH', 'ZXSJ', 'BLMC'])
            ->getParams();
        $bl01Res = app('es')->search($params);
        $bl01Res = $bl01esService->getDataByEs($bl01Res);

        $bingcheng = empty($bl01Res[1]) ? [] : $bl01Res[0];
        return $bingcheng;
    }

    /**
     * @param array $info
     * @return array
     * 入院后8小时内完成首次病程记录
     */
    public function checkRy8($info = [])
    {
        $errorNotice = [];
        $caseRule = $this->caseRule;

        $basis = [];
        $bl01esService = new ElasticsearchService('bl01_202303');
        // 获取护士分床时间
        $zyHcmxesService = new Zyhcmx();
        $zyHcmx = $zyHcmxesService->getHCRQ($info['MED_REC_ID']);

        //首次病程记录表：EMR_BL_BL01中【MBLB =295 且 blzt≠9】的【CJSJ】
        $flag = 1;
        if (!empty($zyHcmx[1])) {
            $hcrq = strtotime($zyHcmx[0][0]['HCRQ']);
            $basis[] = '入院时间【' . $zyHcmx[0][0]['HCRQ'] . '】';
            $bgsj = $hcrq + 8 * 3600;
            // 首次病程
            $params = $bl01esService->clearMust()
                ->queryByMust(['term' => ['JZHM' => $info['MED_REC_ID']]])
                ->queryByMustNot(['term' => ['BLZT' => 9]])
                ->queryByMust([
                    "bool" => [
                        "should" => [
                            ['term' => ['MBLB' => 295]],
                            [
                                "bool" => [
                                    "must" => [
                                        ['term' => ['MBLB' => 294]],
                                        ['match_phrase' => ['HJNR' => '首次病程记录']],
                                    ]
                                ]
                            ],
                        ]
                    ]
                ])
                ->source(['BLBH', 'ZXSJ'])
                ->getParams();
            $res = app('es')->search($params);
            $resData = $bl01esService->getDataByEs($res);
            if (empty($resData[1])) {
                $flag = 0;
                $basis[] = '首次病程记录创建时间【无】';
            } else {
                $cjsj = strtotime($resData[0][0]['ZXSJ']);
                if ($bgsj < $cjsj) {
                    $flag = 0;
                    $basis[] = '首次病程记录创建时间【' . $resData[0][0]['ZXSJ'] . '、超8小时】';
                }
            }
        }
        if (empty($flag)) {
            $errorNotice = [
                'basis' => json_encode([$basis], 256),
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 101,
                'code' => 'bingcheng_first',
                'error_field' => $caseRule[101]['title'],
                'score' => 10
            ];
        }

        return $errorNotice;
    }

    /**
     * 检查报告单有ct（OCT除外）结果，24小时内要写病程记录
     * 或 24小时出入院记录  ,且   含 关键字“CT”（OCT除外）
     */
    public
    function checkCt($info = [])
    {
        $aab01 = $info['AAB01'];
        $aac01 = $info['AAC01'];
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        $bl01esService = new ElasticsearchService('bl01_202303');
        $pacsesService = new ElasticsearchService('pacs');

        $params = $pacsesService->clearMust()
            ->queryByMust(['term' => ['JZLSH' => $info['AAA28']]])
            ->queryByMust(['range' => ['KDSJ' => ["gt" => $aab01, 'lt' => $aac01]]])
            ->queryByMust(['match_phrase' => ['JCMC' => "CT"]])
            ->queryByMustNot(['match_phrase' => ['JCMC' => "OCT"]])
            ->source(['BGSJ', 'JCMC'])
            ->getParams();
        $res = app('es')->search($params);
        $resData = $pacsesService->getDataByEs($res);
        if (!empty($resData[1])) {
            foreach ($resData[0] as $v) {
                $basis = [];
                $basis[] = '检查报告单时间【' . $v['BGSJ'] . '】';
                $bgsj = date("Y-m-d H:i:s", strtotime($v['BGSJ']) + 24 * 3600);
                // 病程记录
                $params = $bl01esService->clearMust()
                    ->queryByMust(['term' => ['JZHM' => $info['MED_REC_ID']]])
                    ->queryByMust(['terms' => ['BLLB' => [294, 18]]])
                    ->queryByMust(['match_phrase' => ['HJNR' => 'CT']])
                    ->queryByMust(['range' => ['ZXSJ' => ['gt' => $v['BGSJ'], 'lt' => $bgsj]]])
                    ->queryByMustNot(['term' => ['BLZT' => 9]])
                    ->queryByMustNot(['term' => ['MBLB' => 32]])
                    ->queryByMustNot(['match_phrase' => ['HJNR' => 'OCT']])
                    ->source(['BLBH', 'CJSJ', 'BLMC'])
                    ->getParams();
                $res = app('es')->search($params);
                $resData = $bl01esService->getDataByEs($res);
                if (empty($resData[1])) {
                    $basis[] = '病程记录【无】';
                    $basisList[] = $basis;
                } elseif (strtotime($resData[0][0]['CJSJ']) < strtotime($v['BGSJ']) || strtotime($resData[0][0]['CJSJ']) > strtotime($bgsj)) {
                    $basis[] = '病程记录【' . $resData[0][0]['BLMC'] . '、超24小时】';
                    $basisList[] = $basis;
                }
            }

            //
            if (count($basisList)) {
                $errorNotice = [
                    'basis' => json_encode($basisList, 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => 102,
                    'code' => 'jcbgd',
                    'error_field' => $caseRule[102]['title']
                ];
            }

        }

        return $errorNotice;
    }

    /*
     * 检查报告单有mr结果，24小时内要写病程记录，或 24小时内出入院记录：关键字 "MR” 或 “磁共振”
     */
    public
    function checkMr($info = [])
    {

        $aab01 = $info['AAB01'];
        $aac01 = $info['AAC01'];
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        $bl01esService = new ElasticsearchService('bl01_202303');
        $pacsesService = new ElasticsearchService('pacs');

        $params = $pacsesService->clearMust()
            ->queryByMust(['term' => ['JZLSH' => $info['AAA28']]])
            ->queryByMust(['range' => ['KDSJ' => ["gt" => $aab01, 'lt' => $aac01]]])
            ->queryByMust(['match_phrase' => ['JCMC' => "MR"]])
            ->getParams();
        $res = app('es')->search($params);
        $resData = $pacsesService->getDataByEs($res);
        if (!empty($resData[1])) {
            foreach ($resData[0] as $v) {
                $basis = [];
                $basis[] = '检查报告单时间【' . $v['BGSJ'] . '】';
                $bgsj = date("Y-m-d H:i:s", strtotime($v['BGSJ']) + 24 * 3600);
                // 病程记录
                $params = $bl01esService->clearMust()
                    ->queryByMust(['term' => ['JZHM' => $info['MED_REC_ID']]])
                    ->queryByMust(['terms' => ['BLLB' => [294, 18]]])
                    ->queryByMust([
                        "bool" => [
                            "should" => [
                                ['match_phrase' => ['HJNR' => "MR"]],
                                ['match_phrase' => ['HJNR' => "磁共振"]],
                            ]
                        ]
                    ])
                    ->queryByMust(['range' => ['ZXSJ' => ['gt' => $v['BGSJ'], 'lt' => $bgsj]]])
                    ->queryByMustNot(['term' => ['BLZT' => 9]])
                    ->queryByMustNot(['term' => ['MBLB' => 32]])
                    ->source(['BLBH', 'CJSJ', 'BLMC'])
                    ->getParams();
                $res = app('es')->search($params);
                $resData = $bl01esService->getDataByEs($res);
                if (empty($resData[1])) {
                    $basis[] = '病程记录【无】';
                    $basisList[] = $basis;
                } elseif (strtotime($resData[0][0]['CJSJ']) < strtotime($v['BGSJ']) || strtotime($resData[0][0]['CJSJ']) > strtotime($bgsj)) {
                    $basis[] = '病程记录【' . $resData[0][0]['BLMC'] . '、超24小时】';
                    $basisList[] = $basis;
                }
            }

            //
            if (count($basisList)) {
                $errorNotice = [
                    'basis' => json_encode($basisList, 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => 103,
                    'code' => 'jcbgd',
                    'error_field' => $caseRule[103]['title']
                ];
            }

        }

        return $errorNotice;
    }

    /**
     * @return array
     * 有抗菌药，开嘱时间+24小时 内要有病程记录
     */
    public
    function kjy($info = []): array
    {
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        $yzbesService = new ElasticsearchService('yzb_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');

        $params = $yzbesService->clearMust()
            ->queryByMust(['term' => ['ZYH' => $info['MED_REC_ID']]])
            ->queryByMust(['term' => ['is_has_kjyw' => 1]])
            ->getParams();
        $res = app('es')->search($params);
        $resData = $yzbesService->getDataByEs($res);
        if (!empty($resData[1])) {
            $yzbInfo = $resData[0];
            $resArr = [];
            foreach ($yzbInfo as $item) {
                $resArr[md5($item['YZMC'] . $item['JLDW'] . $item['SYPC'] . $item['YCJL'])] = $item;
            }

            foreach ($resArr as $v) {
                $kzsj = date("Y-m-d H:i:s", strtotime($v['KZSJ']) + 24 * 3600);
                // 24小时内的病程记录
                $params = $bl01esService->clearMust()
                    ->queryByMust(['term' => ['JZHM' => $info['MED_REC_ID']]])
                    ->queryByMust(['term' => ['BLLB' => 294]])
                    ->queryByMust(['range' => ['ZXSJ' => ['lt' => $kzsj]]])
                    ->queryByMust(['match_phrase' => ['HJNR' => $v['YZMC']]])
                    ->queryByMustNot(['term' => ['BLZT' => 9]])
                    ->getParams();
                $res = app('es')->search($params);
                $resData1 = $bl01esService->getDataByEs($res);
                $basis = [];
                $basis[] = '抗菌药名称【' . $v['YZMC'] . '】';
                $basis[] = '开嘱时间【' . $v['KZSJ'] . '】';
                if (!empty($resData[1]) && !empty($resData1[1])) {
                    $basis[] = '病程记录时间【无】';
                    $basisList[] = $basis;
                }
            }

            if ($basisList) {
                $errorNotice = [
                    'basis' => json_encode($basisList, 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => 109,
                    'code' => 'kjy',
                    'error_field' => $caseRule[109]['title'],
                    'score' => 1
                ];
            }
        }

        return $errorNotice;
    }

    /**
     * @return array
     * 有化疗药，开嘱时间+24小时 内要有病程记录
     */
    public
    function hly($info = []): array
    {
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        $yzbesService = new ElasticsearchService('yzb_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');

        // 有化疗药，开嘱时间+24小时 内要有病程记录
        $params = $yzbesService->clearMust()
            ->queryByMust(['term' => ['ZYH' => $info['MED_REC_ID']]])
            ->queryByMust(['term' => ['is_has_hlyw' => 1]])
            ->getParams();
        $res = app('es')->search($params);
        $resData = $yzbesService->getDataByEs($res);
        if (!empty($resData[1])) {
            $yzbInfo = $resData[0];
            $resArr = [];
            foreach ($yzbInfo as $item) {
                $resArr[md5($item['YZMC'] . $item['JLDW'] . $item['SYPC'] . $item['YCJL'])] = $item;
            }

            foreach ($resArr as $v) {
                $basis = [];
                $kzsj = date("Y-m-d H:i:s", strtotime($v['KZSJ']) + 24 * 3600);

                $params = $bl01esService->clearMust()
                    ->queryByMust(['term' => ['JZHM' => $info['MED_REC_ID']]])
                    ->queryByMust(['term' => ['BLLB' => 294]])
                    ->queryByMust(['range' => ['CJSJ' => ['gt' => $v['KZSJ'], 'lt' => $kzsj]]])
                    ->queryByMust(['match_phrase' => ['HJNR' => $v['YZMC']]])
                    ->queryByMustNot(['term' => ['BLZT' => 9]])
                    ->queryByMustNot(['term' => ['MBLB' => 32]])
                    ->getParams();
                $res = app('es')->search($params);
                $resData1 = $bl01esService->getDataByEs($res);
                $basis[] = '化疗药名称【' . $v['YZMC'] . '】';
                $basis[] = '开嘱时间【' . $v['KZSJ'] . '】';
                if (!empty($resData[1]) && !empty($resData1[1])) {
                    $basis[] = '病程记录时间【无】';
                    $basisList[] = $basis;
                }
            }

            if ($basisList) {
                $errorNotice = [
                    'basis' => json_encode($basisList, 256),
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => 110,
                    'code' => 'hly',
                    'error_field' => $caseRule[110]['title']
                ];
            }
        }

        return $errorNotice;
    }

    /**
     * @param string $info
     * @return array
     * 术前24小时内，有术者查房（搜签名）
     */
    public
    function sq24($info = [])
    {
        $zyh = $info['MED_REC_ID'];
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        // 获取麻醉记录的信息，用户获取手术时间
        $mzjlEsService = new ElasticsearchService('mzjl_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');

        // 手术记录找术者
        $bl01must = [
            ['term' => ["JZHM" => $zyh]],
            ['term' => ["BLLB" => 303]],
            ['match_phrase' => ['BLMC' => '手术记录']],
        ];
        $params = $bl01esService->clearMust()
            ->queryByMustNot(['term' => ['BLZT' => 9]])
            ->queryByMustBatch($bl01must)
            ->queryByShould(['terms' => ['MBLB' => [306, 74]]])
            ->minimumShouldMatch(1)
            ->source(['BLBH', 'JZHM', 'CJSJ', 'operation_handler', 'operation_time', 'MBLB', 'BLLB', 'BLMC'])
            ->paginate(1, 1000)
            ->getParams();

        $bl01Res = app('es')->search($params);
        $bl01Res = $bl01esService->getDataByEs($bl01Res);

        if (!empty($bl01Res[1])) {
            foreach ($bl01Res[0] as $b) {
                $operation_time = $b['operation_time'];
                $start = date('Y-m-d H:i:s', $operation_time);
                $end = date('Y-m-d H:i:s', $operation_time + 24 * 3600);
                $params = $mzjlEsService->clearMust()
                    ->queryByMust(['term' => ['HOSPIZATIONID' => $b['JZHM']]])
                    ->queryByMust(['range' => ['OPERATESTARTTIME' => ['gt' => $start, 'lt' => $end]]])
                    ->getParams();
                $res = app('es')->search($params);
                $resData = $mzjlEsService->getDataByEs($res);
                if (!empty($resData[1])) {
                    $startTime = $resData[0][0]['OPERATESTARTTIME'];
                } else {
                    $bl = EMR_BL_BL01::query()->select(['BLBH', 'surgery_content'])->where('BLBH', '=', $b['BLBH'])->get()->toArray();

                    $surgery_content = !empty($bl[0]['surgery_content']) ? json_decode($bl[0]['surgery_content'], true) : '';
                    $startTime = '';
                    if ($surgery_content) {
                        $ssrq = str_replace(' ', '', $surgery_content['sssj']);
                        $ssrq = explode('-', $ssrq);
                        $ssrq = $ssrq[0];
                        $startTime = date('Y-m-d H:i:s', strtotime($surgery_content['ssrq'] . ' ' . $ssrq));
                    }
                }
                if (empty($startTime) || !strtotime($startTime)) {
                    break;
                }
                $basis = [];
                // 如果手术时间存在，则获取手术时间24内的所有病程信息
                $endTime = date("Y-m-d H:i:s", strtotime($startTime) - 24 * 3600);
                $bl01must = [
                    ['term' => ["JZHM" => $zyh]],
                    ['term' => ["BLLB" => 294]],
                    ['range' => ["ZXSJ" => ['gt' => $endTime, 'lt' => $startTime]]]
                ];
                $params = $bl01esService->clearMust()
                    ->queryByMustNot(['terms' => ['MBLB' => [32, 54]]])
                    ->queryByMustNot(['term' => ['BLZT' => 9]])
                    ->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ', 'BLMC'])->getParams();
                $bl01Res = app('es')->search($params);
                $bl01Res = $bl01esService->getDataByEs($bl01Res);
                // 如果病程信息不存在，则提示质控错误信息
                $zfzhi = [];
                if (!empty($bl01Res[1])) {
                    $res = $bl01Res[0];
                    $zfzhi = $this->shuzhe($res, $b['operation_handler']);
                }
                // 检查是否有正副高职签字的病程
                if (empty($bl01Res[1]) || empty($zfzhi[0])) {
                    $basis[0] = '手术开始时间【' . $startTime . '】';
                    $basis[1] = '术者【' . $b['operation_handler'] . '】';

                    // 查找24之后的病程信息
                    $bl01must = [
                        ['term' => ["JZHM" => $zyh]],
                        ['term' => ["BLLB" => 294]],
                        ['range' => ["ZXSJ" => ['lt' => $endTime]]]
                    ];
                    $params = $bl01esService->clearMust()
                        ->queryByMustNot(['terms' => ['MBLB' => [32, 54]]])
                        ->queryByMustNot(['term' => ['BLZT' => 9]])
                        ->queryByMustBatch($bl01must)->paginate(1, 1)->orderBy('ZXSJ', 'desc')->source(['BLBH', 'CJSJ', 'BLMC'])->getParams();
                    $bl01Res = app('es')->search($params);
                    $bl01Res = $bl01esService->getDataByEs($bl01Res);

                    if (empty($bl01Res[1])) {
                        $basis[2] = '病程记录【无】';
                    } else {
                        $res = $bl01Res[0];
                        $zfzhi = $this->shuzhe($res, $b['operation_handler']);
                        if (!empty($zfzhi[0])) {
                            $basis[2] = '病程记录【' . $zfzhi[1] . '、 超24小时】';
                        } else {
                            $basis[2] = '病程记录【无】';
                        }
                    }
                }

                if ($basis) {
                    $basisList[] = $basis;
                }
            }
        }
        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $zyh,
                'rule_id' => 111,
                'code' => 'sq24',
                'error_field' => $caseRule[111]['title']
            ];
        }
        return $errorNotice;
    }

    /**
     * @param array $res
     * @return array
     *
     */
    public function shuzhe($res = [], $operator = '')
    {
        $zhengName = '';
        $cjsj = '';
        foreach ($res as $r) {
            $blsy = EMR_BL_BLSY::query()
                ->where('BLBH', '=', $r['BLBH'])
                ->get()->toArray();
            $code = array_column($blsy, 'SYYS');
            if ($blsy) {
                $staff = Staff::query()->whereIn('code', $code)->get()->toArray();
                foreach ($staff as $b) {
                    if ($b['name'] == $operator) {
                        $zhengName = $b['name'];
                        $cjsj = $r['BLMC'];
                        break;
                    }
                }
                if (!empty($cjsj)) {
                    break;
                }
            }
        }

        return [$zhengName, $cjsj];
    }

    /**
     * @param array $res
     * @return array
     * 获取病程中的正副高职信息
     */
    public function getZhengFu($res = [])
    {
        $zhengName = '';
        $fuName = '';
        $cjsj = '';
        foreach ($res as $r) {
            $blsy = EMR_BL_BLSY::query()
                ->where('BLBH', '=', $r['BLBH'])
                ->get()->toArray();
            $code = array_column($blsy, 'SYYS');
            if ($blsy) {
                $staff = Staff::query()->whereIn('code', $code)->get()->toArray();
                foreach ($staff as $b) {
                    if ($b['ygjb'] == 1) {
                        $zhengName = $b['name'];
                        $cjsj = $r['CJSJ'];
                        break;
                    } elseif ($b['ygjb'] == 2) {
                        $fuName = $b['name'];
                        $cjsj = $r['CJSJ'];
                        break;
                    }
                }
            }
        }

        return [$zhengName, $fuName, $cjsj];
    }

    /**
     * @param array $zyh
     * @return array
     * 术后24小时内，有术者查房（搜签名）
     */
    public
    function sh24($info = [])
    {

        $zyh = $info['MED_REC_ID'];
        $errorNotice = [];
        $basisList = [];
        $caseRule = $this->caseRule;
        // 获取麻醉记录的信息，用户获取手术时间
        $mzjlEsService = new ElasticsearchService('mzjl_2023');
        $bl01esService = new ElasticsearchService('bl01_202303');

        // 手术记录找术者
        $bl01must = [
            ['term' => ["JZHM" => $zyh]],
            ['term' => ["BLLB" => 303]],
            ['match_phrase' => ['BLMC' => '手术记录']],
        ];
        $params = $bl01esService->clearMust()
            ->queryByMustNot(['term' => ['BLZT' => 9]])
            ->queryByMustBatch($bl01must)
            ->queryByShould(['terms' => ['MBLB' => [306, 74]]])
            ->minimumShouldMatch(1)
            ->source(['BLBH', 'JZHM', 'CJSJ', 'operation_handler', 'operation_time', 'MBLB', 'BLLB', 'BLMC'])
            ->paginate(1, 1000)
            ->getParams();

        $bl01Res = app('es')->search($params);
        $bl01Res = $bl01esService->getDataByEs($bl01Res);

        if (!empty($bl01Res[1])) {
            foreach ($bl01Res[0] as $b) {
                $operation_time = $b['operation_time'];
                $start = date('Y-m-d H:i:s', $operation_time);
                $end = date('Y-m-d H:i:s', $operation_time + 24 * 3600);
                $params = $mzjlEsService->clearMust()
                    ->queryByMust(['term' => ['HOSPIZATIONID' => $b['JZHM']]])
                    ->queryByMust(['range' => ['OPERATESTARTTIME' => ['gt' => $start, 'lt' => $end]]])
                    ->getParams();
                $res = app('es')->search($params);
                $resData = $mzjlEsService->getDataByEs($res);
                if (!empty($resData[1])) {
                    $startTime = $resData[0][0]['OPERATEENDTIME'];
                } else {
                    $bl = EMR_BL_BL01::query()->select(['BLBH', 'surgery_content'])->where('BLBH', '=', $b['BLBH'])->get()->toArray();

                    $surgery_content = !empty($bl[0]['surgery_content']) ? json_decode($bl[0]['surgery_content'], true) : '';
                    $startTime = '';
                    if ($surgery_content) {
                        $ssrq = str_replace(' ', '', $surgery_content['sssj']);
                        $ssrq = explode('-', $ssrq);
                        if (!empty($ssrq[1])) {
                            $ssrq = $ssrq[1];
                            $startTime = date('Y-m-d H:i:s', strtotime($surgery_content['ssrq'] . ' ' . $ssrq));
                        }
                    }
                }
                if (empty($startTime) || !strtotime($startTime)) {
                    break;
                }
                $basis = [];
                $basis[0] = '手术结束时间【' . $startTime . '】';
                $basis[1] = '术者【' . $b['operation_handler'] . '】';
                // 如果手术时间存在，则获取手术时间24内的所有病程信息
                $endTime = date("Y-m-d H:i:s", strtotime($startTime) + 24 * 3600);


                // 术后首次病程会后的病程信息
                $bl01must = [
                    ['term' => ["JZHM" => $zyh]],
                    ['term' => ["BLLB" => 294]],
                    ['term' => ["MBLB" => 54]],
                    ['range' => ["ZXSJ" => ['gt' => $startTime, 'lt' => $endTime]]]
                ];
                $params = $bl01esService->clearMust()
                    ->queryByMustNot(['term' => ['BLZT' => 9]])
                    ->queryByMustBatch($bl01must)->paginate(1, 1)->source(['BLBH', 'ZXSJ', 'BLMC'])->getParams();
                $bl01Res = app('es')->search($params);
                $bl01Res = $bl01esService->getDataByEs($bl01Res);
                if (!empty($bl01Res[1])) {
                    $startTime = $bl01Res[0][0]['ZXSJ'];
                } else {
                    $basis[2] = '病程记录【无】';
                    $basisList[] = $basis;
                    continue;
                }


                $bl01must = [
                    ['term' => ["JZHM" => $zyh]],
                    ['term' => ["BLLB" => 294]],
                    ['range' => ["ZXSJ" => ['gt' => $startTime, 'lt' => $endTime]]]
                ];
                $params = $bl01esService->clearMust()
                    ->queryByMustNot(['terms' => ['MBLB' => [32, 54]]])
                    ->queryByMustNot(['term' => ['BLZT' => 9]])
                    ->queryByMustBatch($bl01must)->source(['BLBH', 'CJSJ', 'BLMC'])->getParams();
                $bl01Res = app('es')->search($params);
                $bl01Res = $bl01esService->getDataByEs($bl01Res);
                // 如果病程信息不存在，则提示质控错误信息
                $zfzhi = [];
                if (!empty($bl01Res[1])) {
                    $res = $bl01Res[0];
                    $zfzhi = $this->shuzhe($res, $b['operation_handler']);
                    if (!empty($zfzhi[0])) {
                        if (
                            strtotime($bl01Res[0][0]['CJSJ']) < strtotime($startTime) ||
                            strtotime($bl01Res[0][0]['CJSJ']) > strtotime($endTime)
                        ) {
                            $basis[2] = '病程记录【' . $bl01Res[0][0]['BLMC'] . '、 创建时间' . $bl01Res[0][0]['CJSJ'] . '超24小时】';
                            $basisList[] = $basis;
                            continue;
                        }
                    }
                }
                // 检查是否有正副高职签字的病程
                if (empty($bl01Res[1]) || empty($zfzhi[0])) {


                    // 查找24之后的病程信息
                    $bl01must = [
                        ['term' => ["JZHM" => $zyh]],
                        ['term' => ["BLLB" => 294]],
                        ['range' => ["ZXSJ" => ['gt' => $endTime]]]
                    ];
                    $params = $bl01esService->clearMust()
                        ->queryByMustNot(['terms' => ['MBLB' => [32, 54]]])
                        ->queryByMustNot(['term' => ['BLZT' => 9]])
                        ->queryByMustBatch($bl01must)->paginate(1, 1)->orderBy('ZXSJ', 'asc')->source(['BLBH', 'CJSJ', 'BLMC'])->getParams();
                    $bl01Res = app('es')->search($params);
                    $bl01Res = $bl01esService->getDataByEs($bl01Res);

                    if (empty($bl01Res[1])) {
                        $basis[2] = ['病程记录【无】'];
                        $basisList[] = $basis;
                    } else {
                        $res = $bl01Res[0];
                        $zfzhi = $this->shuzhe($res, $b['operation_handler']);
                        if (!empty($zfzhi[0])) {
                            $basis[2] = ['病程记录【' . $bl01Res[0][0]['BLMC'] . '、 创建时间超24小时】'];
                            $basisList[] = $basis;
                        } else {
                            $basis[2] = '病程记录【无】';
                            $basisList[] = $basis;
                        }
                    }
                }

            }
        }
        if ($basisList) {
            $errorNotice = [
                'basis' => json_encode($basisList, 256),
                'JZHM' => $zyh,
                'rule_id' => 112,
                'code' => 'sh24',
                'error_field' => $caseRule[112]['title']
            ];
        }
        return $errorNotice;
    }


    public
    static function getCaseDetail($blbh = 0)
    {

        $blxg = EMR_BL_BL01::query()->where("BRBH", '=', $blbh)->orWhere("JZHM", '=', $blbh)->get()->toArray();
        if (!$blxg) {
            return [];
        }
        $blbh = $blxg[0]['BLBH'];
        $res = CaseQualityV2::getById($blbh);
        return $res;
    }

    public
    static function getCasePlatform($blbh = 0, $bllb = 1)
    {

//        $deparment = config("confAdmin")['department'];
        $blxg = EMR_BL_BL01::query()
            ->where('BLLB', '=', $bllb)
            ->where('BLZT', '!=', 9)
            ->whereRaw("(BRBH='{$blbh}' or JZHM='{$blbh}')")
            ->get(['analysis_case', 'diagnose_list', 'BLLB', 'BRKS'])
            ->toArray();

        if (!$blxg) {
            return [];
        }
        $analysiscase = json_decode($blxg[0]['analysis_case'], true);
        if (!$analysiscase) {
            $analysiscase = unserialize($blxg[0]['analysis_case']);
        }
        if (empty($analysiscase)) {
            return [];
        }
        $newData = [];
//        $newData['department'] = $deparment[$blxg[0]['BRKS']] ?? '';

        $keyMap = [
            'keyMap1' => [
                '75d15251ab7048d305b33eae0892444c' => "name",
                '021ab6d8680070343a50dab2c39a0290' => "ry_time",
                '2191d6819eccdd461d5badea3c2e800e' => "sex",
                'bd2cd0d5df70bf13b2d41da868cdde8d' => "cy_time", // 出院日期
                '0d975c61efda46ea809d62a18e553199' => "age",
                '2ab32984a6d66e3a47647d42d2fb8b38' => "zyts", // 住院天数
                'f56edc6122c32828c56ca2dbeb55d272' => "ryqk", // 入院情况:
                'd2b79a1d3d68a674c8b5779a4a265868' => "cbzd", // 初步诊断::
                '258fed9a8d9579aa182b9a8beab3781d' => "zljg", // 诊疗经过
                '0364be74c74e074c11eff6405ec15e62' => "cyqk", // 出院情况
                '3b0ff71ed4b1e15a5e7351a6268a532f' => "cyzd", // 出院诊断:
                'a1485e3eaca998bd8264c499e0b0cf0b' => "cyyz", // 出院医嘱
            ],
            'keyMap292' => [
                '75d15251ab7048d305b33eae0892444c' => "name",
                '53ccdd18ccb05f150c8feebaabbf858a' => "local_address",
                '2191d6819eccdd461d5badea3c2e800e' => "sex",
                'ef7666cc4c6dbd61ebc70ef243e4617a' => "job",
                '0d975c61efda46ea809d62a18e553199' => "age",
                '21f3568c10772f3776fc00635b05f9d2' => "ry_time",
                '98633d6e2c44bc01e6ac07b2ecae32e3' => "nation", // 民族
                '796cfada7d19a3b5ff024fc5f2e40412' => "record_time", // 记录时间
                '7b1ee861fa4672b6369aa4654946d3f2' => "marriage", // 婚姻
                '88422e115c799a30aa52391acdd764c0' => "narrator", // 陈述者
                'cbb1ab4ca9f5e907bedf7b1fe33fafee' => "zhusu", // 主诉
                'f5417c43295c0d67294f141ea6d47db1' => "xianbingshi", // 现病史
                '9d0550ebc6ce5175e0878caf9555312c' => "jiwangshi", // 既往史
                'ad0415504601b0e5e7af0da0a9e354b9' => "gerenshi", // 个人史
                '6f153a5f8946a7392a04789be355b007' => "hys", // 婚育史
                'e88960f01b3be7a35a1c1c9ac1f75c30' => "yjjhys", // 月经及婚育史
                'e62fd2ba94f47c94b9e47e1fb96e3914' => "jzs", // 家族史
                'd53647eedb2e963afa459cda02aad90f' => "tgjc", // 体格检查
                'ca9fa6c44dd8c758647c3155b5fbf753' => "fzjc", // 辅助检查
                '4f418fcdffbf606e1a66cae82ff104b1' => "bed_no", // 床号
                'e98d75d1ee6c335d1d34157fa0c93bb3' => "hospital_no", // 住院号
                'diagnose_list' => "diagnose_list", // 主要诊断
            ]
        ];

        $keyMap = $keyMap['keyMap' . $blxg[0]['BLLB']];
        foreach ($analysiscase as $key => $item) {
            if (!isset($keyMap[$key])) {
                continue;
            }
            $newData[$keyMap[$key]] = $item;
        }
        if (!empty($blxg[0]['diagnose_list'])) {
            $diagnoseList = base64_decode($blxg[0]['diagnose_list']);
            $diagnoseList = $diagnoseList ? explode(',', $diagnoseList) : [];
            $newData['diagnose_list'] = $diagnoseList ?: [];
        }

        return $newData;
    }

    /**
     * @param string $no
     * @return array|bool
     * 解析病例内容
     */
    public
    static function analysisCase()
    {
        $pageSize = 100;
        $lastNo = 0;
        try {
            while (1) {
                $res01 = EMR_BL_BL01::query()
                    ->where('analysis_index', '<', 5)
                    ->whereIn("BLLB", [1, 292])
                    ->where('BLBH', '>', $lastNo)
                    ->where('BLZT', '!=', 9)
                    ->orderBy("BLBH", "asc")
                    ->LIMIT($pageSize)
                    ->get()->toArray();

                if (!$res01) {
                    echo '获取数据完毕';
                    break;
                }
                foreach ($res01 as $bl01) {
                    $lastNo = $bl01['BLBH'];
                    $res = EMR_BL_BLXG::getById($bl01['BLBH']);
                    if (!$res) {
                        continue;
                    }
                    $caseContent = $res[0]['HJNR'];
                    if ($bl01['BLLB'] == 1) {
                        self::analysisCaseCy($bl01, $res);
                    } elseif ($bl01['BLLB'] == 292) {
                        self::analysisCaseRy($bl01, $caseContent);
                    }
                }
            }

        } catch (\Throwable $e) {
            var_dump("病例处理失败", ['msg' => $e->getMessage(), 'line' => $e->getLine()]);
            Log::error("病例处理失败", ['msg' => $e->getMessage(), 'line' => $e->getLine()]);
            echo $e->getMessage() . '，所在行：' . $e->getLine();
        }
        return true;
    }


    /**
     * @param array $no
     * @param string $caseContent
     * @return array|bool
     * 出院记录解析病例内容
     */
    public
    static function analysisCaseRy($bl01 = [], $caseContent = "")
    {
        if (!$caseContent || !$bl01) {
            return false;
        }
        $title = ["姓名:", "出生地:", "性别:", "职业:", "年龄:",
            "入院时间:", "民族:", "记录时间:", "婚姻:",
            "病史陈述者:", "主诉:", "现病史:", "既往史:", "个人史:",
            "月经及婚育史:", "婚育史:", "家族史:", "体格检查", "辅助检查",
            "初步诊断", "医师签名", "床号:", "住院号:"];

        // 整理数据，将数据整理成数组结构
        $caseContent = str_replace("月经婚育史", "月经及婚育史", $caseContent);
        $caseContent = str_replace("入院诊断", "初步诊断", $caseContent);

        $isWomen = strpos($caseContent, '月经及婚育史');
        $caseContent = str_replace(" ", "", $caseContent);
        $caseContent = str_replace("：", ":", $caseContent);
        foreach ($title as $t) {
            // 如果病例中有月经及婚育史，则不通过婚育史提取数据
            if ($isWomen && $t == '婚育史:') {
                unset($title[15]);
                continue;
            }

            $caseContent = str_replace($t, "|&|" . $t . '||', $caseContent);
        }

        $caseContentArr = explode("|&|", $caseContent);
        $caseContentArr = array_filter($caseContentArr);

        $newData = [];
        foreach ($caseContentArr as $item) {
            $itemArr = explode("||", $item);
            if (!in_array($itemArr[0], $title) || !isset($itemArr[1])) {
                continue;
            }
            $info['title'] = $itemArr[0];
            $info['value'] = $itemArr[1] ?? '';
            if ($info['title'] == '初步诊断') {
                $cbzd = $info['value'];
                $cbzd = str_replace("\r\n", "!!", $cbzd);
                $cbzd = str_replace("\n", "!!", $cbzd);
                $info['value'] = str_replace('!!', '|', $cbzd);
            } elseif ($info['title'] == '辅助检查') {
                $fzjc = trim($info['value']);
                $fzjc = str_replace("\r\n", "!!", $fzjc);
                $fzjc = str_replace("\n", "!!", $fzjc);
                $fzjc = explode("!!", $fzjc);
                $fzjc = array_chunk($fzjc, 4);
                $info['value'] = $fzjc;
            } elseif ($info['title'] == '体格检查') {
                $tgjc = trim($info['value']);
                $tgjc = str_replace("\r\n", "<br />", $tgjc);
                $tgjc = str_replace("脉搏", "  脉搏", $tgjc);
                $tgjc = str_replace("呼吸", "  呼吸", $tgjc);
                $tgjc = str_replace("血压", "  血压", $tgjc);
                $tgjc = str_replace("BP", "  BP", $tgjc);
                $info['value'] = $tgjc;
            }
            $info['md5'] = md5($itemArr[0]);
            $newData[] = $info;
        }
        $newData = array_column($newData, null, 'md5');

        // 初步诊断数据解析成数组
        $cbzdData = '';
        $diagnosList = [];
        if (isset($newData[md5('初步诊断')])) {
            $cbzdData = $newData[md5('初步诊断')]['value'];
        }
        if ($cbzdData) {
            $cbzdData = str_replace([':', '滨医_住院签名', '滨医_', '住院签名', '主治签名', '{}', '!'], '', $cbzdData);
            $cbzdData = preg_replace("/(\d)+[\.、]/", '|', $cbzdData);
            $cbzdData = explode("|", $cbzdData);
            $cbzdData = array_filter($cbzdData);
            foreach ($cbzdData as $c) {
                preg_match_all("/第(\d+)页/", $c, $pagePregRes);
                if ($pagePregRes[1]) {
                    break;
                }
                $diagnosList[] = str_replace(' ', '', $c);
            }
        }
        $addData = [
            'ZYH' => $bl01['JZHM'],
            'AAA28' => $bl01['BRBH'],
            'BLBH' => $bl01['BLBH'],
            'AAB01' => $bl01['AAB01'],
            'AAC01' => $bl01['AAC01'],
            'XM' => (!empty($newData[md5('姓名:')]) ? trim($newData[md5('姓名:')]['value']) : ''),
            'CSD' => (!empty($newData[md5('出生地:')]) ? trim($newData[md5('出生地:')]['value']) : ''),
            'XB' => (!empty($newData[md5('性别:')]) ? trim($newData[md5('性别:')]['value']) : ''),
            'ZHY' => (!empty($newData[md5('职业:')]) ? trim($newData[md5('职业:')]['value']) : ''),
            'NL' => (!empty($newData[md5('年龄:')]) ? trim($newData[md5('年龄:')]['value']) : ''),
            'RYSJ' => (!empty($newData[md5('入院时间:')]) ? trim($newData[md5('入院时间:')]['value']) : ''),
            'MZ' => (!empty($newData[md5('民族:')]) ? trim($newData[md5('民族:')]['value']) : ''),
            'JLSJ' => (!empty($newData[md5('记录时间:')]) ? trim($newData[md5('记录时间:')]['value']) : ''),
            'HY' => (!empty($newData[md5('婚姻:')]) ? trim($newData[md5('婚姻:')]['value']) : ''),
            'BSCSZ' => (!empty($newData[md5('病史陈述者:')]) ? trim($newData[md5('病史陈述者:')]['value']) : ''),
            'ZHS' => (!empty($newData[md5('主诉:')]) ? trim($newData[md5('主诉:')]['value']) : ''),
            'XBS' => (!empty($newData[md5('现病史:')]) ? trim($newData[md5('现病史:')]['value']) : ''),
            'JWS' => (!empty($newData[md5('既往史:')]) ? trim($newData[md5('既往史:')]['value']) : ''),
            'GRS' => (!empty($newData[md5('个人史:')]) ? trim($newData[md5('个人史:')]['value']) : ''),
            'YJJHYS' => (!empty($newData[md5('月经及婚育史:')]) ? trim($newData[md5('月经及婚育史:')]['value']) : ''),
            'HYS' => (!empty($newData[md5('婚育史:')]) ? trim($newData[md5('婚育史:')]['value']) : ''),
            'JZS' => (!empty($newData[md5('家族史:')]) ? trim($newData[md5('家族史:')]['value']) : ''),
            'TGJC' => (!empty($newData[md5('体格检查')]) ? trim($newData[md5('体格检查')]['value']) : ''),
            'FZJC' => (!empty($newData[md5('辅助检查')]) ? json_encode($newData[md5('辅助检查')]['value'], 256) : ''),
            'CBZD' => json_encode($diagnosList, 256),
            'YSQM' => (!empty($newData[md5('医师签名')]) ? trim($newData[md5('医师签名')]['value']) : ''),
            'CHH' => (!empty($newData[md5('床号:')]) ? trim($newData[md5('床号:')]['value']) : ''),
        ];
        $jsonData = json_encode($newData, 256);
        if ($diagnosList && is_array($diagnosList)) {
            $diagnosList = implode(',', $diagnosList);
        } elseif (!is_array($diagnosList)) {
            exit;
        }
        $updata = ['analysis_index' => ($bl01['analysis_index'] + 1), 'diagnose_list' => ($diagnosList ? base64_encode($diagnosList) : ''), 'analysis_case' => $jsonData];
        if (!empty($newData[md5('现病史:')])) {
            $xbsData = $newData[md5('现病史:')]['value'];
            // 整理数据，将数据整理成数组结构
            $xbsData = str_replace("\r\n", "", $xbsData);
            $xbsData = str_replace("\n", "", $xbsData);
            $xbsData = str_replace("：", ":", $xbsData);
            $updata['ryjl_xbs'] = $xbsData ?: '';
        }
        $res = EMR_BL_BL01::updateById($bl01['BLBH'], $updata);
        Bllb292::query()->insertOrIgnore($addData);
        var_dump($res);
    }


    /**
     * @param string $no
     * @return array|bool
     * 出院记录解析病例内容
     */
    public
    static function analysisCaseCy($bl01 = [], $res = [])
    {
        $caseContent = $res[0]['HJNR'];
        if (!$caseContent || !$bl01) {
            return false;
        }
        $title = ["姓名:", "入院日期:", "性别:", "出院日期:", "年龄:",
            "住院天数:", "入院情况:", "初步诊断:", "诊疗经过:",
            "出院情况:", "出院诊断:", "出院医嘱:", "床号:", "住院号:", "每次复查时请携带"];

        $caseContent = str_replace(" ", "", $caseContent);
        $caseContent = str_replace("：", ":", $caseContent);
        foreach ($title as $t) {
            $caseContent = str_replace($t, "|&|" . $t . '||', $caseContent);
        }

        $caseContentArr = explode("|&|", $caseContent);
        $caseContentArr = array_filter($caseContentArr);

        $newData = [];
        foreach ($caseContentArr as $item) {
            $itemArr = explode("||", $item);
            if (!in_array($itemArr[0], $title) || !isset($itemArr[1])) {
                continue;
            }
            $info['title'] = $itemArr[0];
            $info['value'] = $itemArr[1] ?? '';
            if ($info['title'] == '初步诊断') {
                $cbzd = $info['value'];
                $cbzd = str_replace("\r\n", "!!", $cbzd);
                $cbzd = str_replace("\n", "!!", $cbzd);
                $info['value'] = str_replace('!!', '|', $cbzd);
            }
            $info['md5'] = md5($itemArr[0]);
            $newData[] = $info;
        }
        $newData = array_column($newData, null, 'md5');
        unset($newData['78e02f7fc69578f7a22f1ad1eaa710a2']);

        // 初步诊断数据解析成数组
        $jsonData = json_encode($newData, 256);
        $updata = [
            'analysis_index' => ($bl01['analysis_index'] + 1),
            'analysis_case' => $jsonData
        ];
        $res = EMR_BL_BL01::updateById($bl01['BLBH'], $updata);

        var_dump($res);
        return true;
    }

    public
    function getSurgeryData($blbh, $bllb)
    {
        $dataList = EMR_BL_BL01::query()
            ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
            ->where('EMR_BL_BL01.BLBH', '=', $blbh)
            ->where('BLLB', '=', $bllb)
            ->where('BLZT', '!=', 9)
            ->get(['EMR_BL_BL01.BLBH', 'BRXM', 'JZHM', 'BLMC', 'BRBH', 'BRXM', 'HJNR', 'surgery_content'])
            ->toArray();

        $returnData = [];
        foreach ($dataList as $value) {
            if (!empty($value['surgery_content'])) {
                $returnData[] = [
                    'is_format' => 1,
                    'surgery_data' => json_decode($value['surgery_content'], true),
                ];
            } else {
                $surgeryType = '';
                if (stripos($value['HJNR'], '手术风险评估表')) {
                    $surgeryType = 1;
                } elseif (stripos($value['HJNR'], '手术安全核查表')) {
                    $surgeryType = 2;
                } elseif (stripos($value['HJNR'], '手术同意书')) {
                    $surgeryType = 3;
                } elseif (stripos($value['HJNR'], '手术记录')) {
                    $surgeryType = 4;
                }
                $returnData[] = [
                    'is_format' => 0,
                    'surgery_data' => [
                        'type' => $surgeryType,
                        'content' => $value['HJNR']
                    ],
                ];
            }
        }

        return $returnData;
    }

    /**
     * @param int $zyh
     * @return array
     * 获取病例的质控结果
     */
    public function getCaseQuality($zyh = 0)
    {

        $caseRule = CaseRule::query()->get()->toArray();
        $caseRule = array_column($caseRule, null, 'id');

        $res = CaseQualityV2::getByJZHM($zyh);
        $newData = [];
        $score = 0;
        if ($res) {
            foreach ($res as &$v) {
                $v['basis'] = json_decode($v['basis']);
                $v['notice'] = $caseRule[$v['rule_id']]['notice'];
                $v['score'] = $caseRule[$v['rule_id']]['score'];
                $v['category'] = $caseRule[$v['rule_id']]['category'];
                $newData[$v['category']][] = $v;

                $score += $v['score'];
            }
        }
        $summary = [];
        foreach ($newData as $k => $v) {
            $summary[] = [
                'category' => $k,
                'nums' => count($v)
            ];
        }

        // 获取运行时病例信息
//        $info = PatientInfo::query()
//            ->where('MED_REC_ID', $zyh)
//            ->select(['MED_REC_ID', 'AAC04', 'AAB01', 'AAC01', 'AAA28'])
//            ->get()->toArray();
//        $runScore = 0;
//        if($info){
//            $runScore = $this->getPatientScore($info[0], 1);
//        }
        return ['score' => 100 - $score, 'data' => $newData, 'total' => count($res), 'summary' => $summary, 'run_score' => 0];
    }

}

