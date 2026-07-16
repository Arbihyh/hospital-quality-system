<?php

namespace App\Services;

use App\Model\ErrorRule;
use App\Model\HomeQuality;
use App\Model\PatientInfo;

class HomeQualityService
{
    public static $bl_level = ['优','良','中','差'];

    public function getBlData($must,$aggs=[],$orderBy=[])
    {
        $piService = new ElasticsearchService('patient_info');

        if ($orderBy) {
            $params = $piService->clearMust()
                ->queryByMustBatch($must)
                ->aggs($aggs)
                ->orderBy($orderBy[0],$orderBy[1])
                ->trackTotalHits()
                ->getParams();
        } else {
            $params = $piService->clearMust()
                ->queryByMustBatch($must)
                ->aggs($aggs)
                ->trackTotalHits()
                ->getParams();
        }
        $result = app('es')->search($params);
        return $piService->getDataByEs($result);
    }

    /**
     * 优良中差数量获取
     * @param $hospitalName
     * @param $startTime
     * @param $endTime
     * @param $homeBmyScore
     * @return int
     */
    public function getYLZC($hospitalName,$startTime,$endTime,$homeBmyScore)
    {
        $count = PatientInfo::query()
            ->where('hospital_name','=',$hospitalName)
            ->whereBetween('AAC01',[$startTime,$endTime])
            ->whereBetween('home_bmy_score',$homeBmyScore)
            ->count();

        return $count;
    }

    /**
     * 缺陷问题分析
     * @param $must
     * @param $type
     * @return array
     */
    public function getRuleTypeStatistics($must,$type)
    {
        $ruleIdList = ErrorRule::query()
            ->where('type','=',$type)
            ->where('status','=',0)
            ->pluck('id')->toArray();

        $should = [];
        foreach ($ruleIdList as $ruleId) {
            $should[] = ['term' => ["error_rule" => $ruleId]];
        }

        $aggs = [
            'count' => ['cardinality'=>['field'=>'ZYH']]
        ];

        $hqService = new ElasticsearchService('home_quality');
        if ($should) {
            $params = $hqService->clearMust()
                ->queryByMustBatch($must)
                ->queryByShouldBatch($should)
                ->aggs($aggs)
                ->minimumShouldMatch()
                ->trackTotalHits()
                ->getParams();
        } else {
            $params = $hqService->clearMust()
                ->queryByMustBatch($must)
                ->queryByShouldBatch($should)
                ->aggs($aggs)
                ->trackTotalHits()
                ->getParams();
        }

        $result = app('es')->search($params);

        return $hqService->getDataByEs($result);
    }

    /**
     * 时间处理
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public static function getAAC01StartEndTime($startTime,$endTime)
    {
        if (!$startTime && !$endTime) {
            $startTime = PatientInfo::query()
                ->whereBetween('home_bmy_score',[0,100])
                ->orderBy('AAC01')
                ->value('AAC01');
            $endTime = PatientInfo::query()
                ->whereBetween('home_bmy_score',[0,100])
                ->orderByDesc('AAC01')
                ->value('AAC01');
        } elseif ($startTime && $endTime) {
            $startTime = date('Y-m-d', strtotime($startTime)).' 00:00:00';
            $endTime = date('Y-m-d', strtotime($endTime)).' 23:59:59';
        } elseif ($startTime) {
            $startTime = date('Y-m-d', strtotime($startTime)).' 00:00:00';
            $endTime = PatientInfo::query()
                ->whereBetween('home_bmy_score',[0,100])
                ->orderByDesc('AAC01')
                ->value('AAC01');
        } elseif ($endTime) {
            $startTime = PatientInfo::query()
                ->whereBetween('home_bmy_score',[0,100])
                ->orderBy('AAC01')
                ->value('AAC01');
            $endTime = date('Y-m-d', strtotime($endTime)).' 23:59:59';
        }

        return ['start_time'=>$startTime, 'end_time'=>$endTime];
    }

    /**
     * 根据病历得分计算病历等级
     * @param $down
     * @return int
     */
    public static function levelJs($down,$type=1)
    {
        if ($type == 1) {
            $score = 100-$down;
        } else {
            $score = $down;
        }

        if ($score >= 97) {
            $level = 0;
        } else if ($score >= 90 && $score <= 96.9) {
            $level = 1;
        } else if ($score >= 75 && $score <= 89.9) {
            $level = 2;
        } else {
            $level = 3;
        }

        return $level;
    }

    public function getBmyQualityList($ruleId, $AAC11C, $AEE03_CODE, $AEE04_CODE, $AEE08_CODE, $AAA28, $ICD10_ID1, $ICD10_NAME, $ICD9_ID1, $ICD9_NAME, $page, $pageSize, $isExport, $startTime, $endTime)
    {
        $fields = [
            'error_rule.field',
            'ZY_BRRY.AAA28',
            'ZY_BRRY.BRXM',
            'ZY_BRRY.AAC01',
            'home_quality.YQ_CODE',
            'ZY_BRRY.BRKS',
            'ZY_BRRY.ZYH',
            'phi.AAC03 as AAC11C',
            'home_quality.AEE08_CODE',
            'home_quality.AEE04_CODE',
            'home_quality.ICD10_NAME',
            'home_quality.ICD9_NAME',
            'home_quality.ICD10_ID1',
            'home_quality.ICD9_ID1',
        ];
        $query = HomeQuality::query()
            ->leftJoin('error_rule', 'home_quality.error_rule', '=', 'error_rule.id')
            ->leftJoin('ZY_BRRY', 'home_quality.ZYH', '=', 'ZY_BRRY.ZYH')
            ->leftJoin('patient_hospital_info as phi', 'home_quality.ZYH', '=', 'phi.AAA28')
            ->where('home_quality.is_del', '=', 0);

        if ($ruleId) {
            $query->where('home_quality.error_rule', $ruleId);
        }

        if ($AAA28) {
            $query->where('ZY_BRRY.AAA28', $AAA28);
        }

        if ($AAC11C) {
            $query->where('ZY_BRRY.BRKS', $AAC11C);
        }
        // 出院时间搜索
        if ($startTime && $endTime) {
            $query->whereBetween('ZY_BRRY.AAC01', [$startTime, $endTime]);
        }

        if ($AEE04_CODE) {
            $query->where('home_quality.AEE04_CODE', $AEE04_CODE);
        }

        if ($AEE08_CODE) {
            $query->where('home_quality.AEE08_CODE', $AEE08_CODE);
        }


        if ($AEE03_CODE) {
            $query->where('home_quality.AEE03_CODE', $AEE03_CODE);
        }

        if ($ICD10_ID1) {
            $query->where('home_quality.ICD10_ID1', $ICD10_ID1);
        }

        if ($ICD10_NAME) {
            $query->where('home_quality.ICD10_NAME', $ICD10_NAME);
        }

        if ($ICD9_ID1) {
            $query->where('home_quality.ICD9_ID1', $ICD9_ID1);
        }

        if ($ICD9_NAME) {
            $query->where('home_quality.ICD9_NAME', $ICD9_NAME);
        }

        $total = $query->count();

        $query->orderBy('home_quality.AAC01', 'desc');

        if (!$isExport && $page && $pageSize) {
            $query->offset(($page - 1) * $pageSize)->limit($pageSize);
        }   

        $list = $query->get($fields)->toArray();

        return ['total' => $total, 'data' => $list];
    }
}
