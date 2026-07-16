<?php

namespace App\Services\QualityControl;

use App\Model\CaseQualityV2;
use App\Model\PatientInfo;
use App\Model\RuleWordMap;
use App\Services\ElasticsearchService;
use Carbon\Carbon;

/**
 * 事中质控
 */
class SzzkService
{
    protected $insertData = [];

    /**
     * 事中质控
     * @param $caseRule
     * @param $info
     * @return true
     */
    public function qualityControl($caseRule, $info)
    {
        $score = 0;
        $this->insertData = [];
        if (!empty($info['MED_REC_ID'])) {
            // 住院号
            $ZYH = $info['MED_REC_ID'];

            // 要调用的方法
            $methodList = [
                99 => 'rule99',
            ];

            // 计算分值
            foreach ($methodList as $ruleId => $method) {
                $score += $this->$method($caseRule, $ruleId, $ZYH);
            }

            $insertDataArr = $this->insertData;
            if (!empty($insertDataArr)) {
                // 同步修改病例数据的缺陷状态
                PatientInfo::query()->where('MED_REC_ID', '=', $ZYH)->update(['is_defect_v2' => 1]);
//                CaseQualityV2::query()->insert($insertDataArr);
                foreach ($insertDataArr as $key=>$v){
                    CaseQualityV2::query()->updateOrInsert(
                        ['rule_id'=>$v['rule_id'], 'JZHM'=>$v['JZHM']],
                        ['code'=>$v['code'], 'basis'=>$v['basis'],'error_field'=>$v['error_field']]
                    );
                }
            }
        }

        return $score;
    }

    /**
     * 入院记录
     * @param $caseRule
     * @param $ruleId
     * @param $ZYH
     * @return int|mixed
     */
    public function rule99($caseRule, $ruleId, $ZYH)
    {
        $zyHcmxService = new ElasticsearchService('zy_hcmx');
        $bl01Service = new ElasticsearchService('bl01_202303');

        $must = [
            ['term' => ['ZYH' => $ZYH]],
            ['term' => ['HCLX' => 0]]
        ];
        $params = $zyHcmxService->clearMust()
            ->queryByMustBatch($must)
            ->orderBy('HCRQ','asc')
            ->getParams();
        $restful = app('es')->search($params);
        $zyHcmxData = $zyHcmxService->getDataByEs($restful);

        $score = 0;
        if (!empty($zyHcmxData[0])) {
            $HCRQ = $zyHcmxData[0][0]['HCRQ'];
            $HCRQ_NED = Carbon::parse($HCRQ)->addDay()->toDateTimeString();
            $must = [
                ['term' => ['JZHM' => $ZYH]],
                ['term' => ['BLLB' => 292]]
            ];
            $notMust = ['term' => ['BLLB' => 9]];
            $params = $bl01Service->clearMust()
                ->queryByMustBatch($must)
                ->queryByMustNot($notMust)
                ->getParams();
            $restful = app('es')->search($params);
            $bl01Data = $bl01Service->getDataByEs($restful);
            //获取质控字典关键词映射
            $rulefirst = RuleWordMap::query()->where('name','完成时间')->first();

            if (!empty($bl01Data[0])) {
                if ($rulefirst){
                    $bldate = $bl01Data[0][0][$rulefirst['keyword']];
                }else{
                    $bldate = $bl01Data[0][0]['ZXSJ'];
                }
                if ($HCRQ > $bldate || $HCRQ_NED < $bldate) {
                    $score += $caseRule[$ruleId]['score'];

                    $this->insertData[] = [
                        'JZHM' => $ZYH,
                        'rule_id' => $ruleId,
                        'code' => 'rule_'.$ruleId,
                        'error_field' => $caseRule[$ruleId]['title'],
                        'basis' => json_encode([['入院时间【'.$HCRQ.'】，入院记录创建时间【'.$bldate.'（超24小时）】']],JSON_UNESCAPED_UNICODE)
                    ];
                }
            } else {
                // 24小时出入院记录
                $must = [
                    ['term' => ['JZHM' => $ZYH]],
                    ['term' => ['BLLB' => 18]],
                ];
                $notMust = ['term' => ['BLLB' => 9]];
                $params = $bl01Service->clearMust()
                    ->queryByMustBatch($must)
                    ->queryByMustNot($notMust)
                    ->getParams();
                $restful = app('es')->search($params);
                $bl0124Data = $bl01Service->getDataByEs($restful);
                if (!empty($bl0124Data[0])) {
                    if ($rulefirst){
                        $bldate = $bl0124Data[0][0][$rulefirst['keyword']];
                    }else{
                        $bldate = $bl0124Data[0][0]['ZXSJ'];
                    }
                    if ($HCRQ > $bldate || $HCRQ_NED < $bldate) {
                        $score += $caseRule[$ruleId]['score'];

                        $this->insertData[] = [
                            'JZHM' => $ZYH,
                            'rule_id' => $ruleId,
                            'code' => 'rule_'.$ruleId,
                            'error_field' => $caseRule[$ruleId]['title'],
                            'basis' => json_encode([['入院时间【'.$HCRQ.'】，24小时出入院记录执行时间【'.$bldate.'（超24小时）】']],JSON_UNESCAPED_UNICODE)
                        ];
                    }
                } else {
                    $score += $caseRule[$ruleId]['score'];

                    $this->insertData[] = [
                        'JZHM' => $ZYH,
                        'rule_id' => $ruleId,
                        'code' => 'rule_'.$ruleId,
                        'error_field' => $caseRule[$ruleId]['title'],
                        'basis' => json_encode([['入院时间【'.$HCRQ.'】，入院记录执行时间【无】']],JSON_UNESCAPED_UNICODE)
                    ];
                }
            }
        }

        return $score;
    }


}
