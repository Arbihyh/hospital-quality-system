<?php

namespace App\Services;

use App\Model\CaseQuality;
use App\Model\CaseRule;
use App\Model\PatientInfo;
use Carbon\Carbon;
use function GuzzleHttp\Psr7\str;

class BanhzkgzService
{
    // 时效性
    protected $insertData = [];

    // 内涵质控
    protected $insertNhzkData = [];

    /**
     * 质控控规则处理（时效性）
     * @param $caseRule
     * @param $info
     * @return true
     */
    public function zkgzHandle($caseRule, $info)
    {
        $ZYH = $info['MED_REC_ID'];
        $this->insertData = [];

        // 整体
        $this->ztRuleHandle($caseRule, $ZYH);

        // 病案首页
        $this->basyRuleHandle($caseRule, $ZYH);

        // 出院记录
        $this->cyjlRuleHandle($caseRule, $ZYH);

        // 24小时出入院记录
//        $this->cyjl24RuleHandle($caseRule, $ZYH);

        // 死亡记录
//        $this->swjlRuleHandle($caseRule, $ZYH);

        // 入院记录
        $this->ryjlRuleHandle($caseRule, $ZYH);

        // 手术知情同意书
        $this->sszqtysRuleHandle($caseRule, $ZYH);

        // 手术记录
        $this->ssjlRuleHandle($caseRule, $ZYH);

        // 麻醉类（先不做）
//        $this->mzlRuleHandle($caseRule, $ZYH);

        // 知情同意书（先不做）
//        $this->zqtysRuleHandle($caseRule, $ZYH);

        // 检验（细菌培养）报告单
        $this->jyXjpyRuleHandle($caseRule, $ZYH);

        $insertData = $this->insertData;
        if (!empty($insertData)) {
            // 同步修改病例数据的缺陷状态
            PatientInfo::query()->where('MED_REC_ID', '=', $ZYH)->update(['is_defect' => 1]);
            CaseQuality::query()->insert($insertData);
        }

        return true;
    }

    /**
     * 整体规则验证
     * @param $caseRule
     * @param $ZYH
     * @return true
     */
    public function ztRuleHandle($caseRule, $ZYH)
    {
        $bl01NewService = new ElasticsearchService('emr_bl_bl01_new');
        $bl01Service = new ElasticsearchService('bl01_202303');
        $ymresultService = new ElasticsearchService('v_jmgs_ymresult_2023');

        $basis = [];

        // 病案首页
        $must = [
            ['term' => ['JZHM' => $ZYH]],
            ['term' => ['BLLB' => 2000001]]
        ];
        $notMust = ['term' => ['BLZT' => 9]];
        $params = $bl01NewService->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNot($notMust)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01NewData = $bl01NewService->getDataByEs($restful);
        if (!empty($bl01NewData[0])) {
            foreach ($bl01NewData[0] as $value) {
                if (strlen($value['CJSJ']) < 16) {
                    $basis[] = ['病案首页创建时间【'.$value['CJSJ'].'】'];
                }
//                if (strlen($value['ZXSJ']) < 16) {
//                    $basis[] = ['病案首页执行时间【'.$value['ZXSJ'].'】'];
//                }
                if (strlen($value['WCSJ']) < 16) {
                    $basis[] = ['病案首页完成时间【'.$value['WCSJ'].'】'];
                }
            }
        }

        // 出院记录、入院记录、病程类、手术类、知情同意书
        $bllbList = [1=>'出院记录',288=>'死亡记录类',18=>'24小时内记录类',292=>'入院记录',294=>'病程类',303=>'手术类',329=>'知情同意书'];
        foreach ($bllbList as $BLLB => $errorField) {
            $must = [
                ['term' => ['JZHM' => $ZYH]],
                ['term' => ['BLLB' => $BLLB]]
            ];
            $notMust = ['term' => ['BLZT' => 9]];
            $params = $bl01Service->clearMust()
                ->queryByMustBatch($must)
                ->queryByMustNot($notMust)
                ->getParams();
            $restful = app('es')->search($params);
            $bl01Data = $bl01Service->getDataByEs($restful);
            if (!empty($bl01Data[0])) {
                foreach ($bl01Data[0] as $value) {
                    if (strlen($value['ZXSJ']) < 16) {
                        $basis[] = [$errorField.'执行时间【'.$value['ZXSJ'].'】'];
                    }
                    if (strlen($value['CJSJ']) < 16) {
                        $basis[] = [$errorField.'创建时间【'.$value['CJSJ'].'】'];
                    }
//                    if (strlen($value['WCSJ']) < 16) {
//                        $basis[] = [$errorField.'完成时间【'.$value['WCSJ'].'】'];
//                    }
                }
            }
        }

        // 检查检验类
        $must = ['term' => ['ZYH' => $ZYH]];
        $params = $ymresultService->clearMust()
            ->queryByMust($must)
            ->getParams();
        $restful = app('es')->search($params);
        $ymresultData = $ymresultService->getDataByEs($restful);
        if (!empty($ymresultData[0])) {
            foreach ($ymresultData[0] as $value) {
                if (strlen($value['CJSJ']) < 16) {
                    $basis[] = ['检查检验类采集时间【'.$value['CJSJ'].'】'];
                }
                if (strlen($value['JSSJ']) < 16) {
                    $basis[] = ['检查检验类接收时间【'.$value['JSSJ'].'】'];
                }
                if (strlen($value['BGSJ']) < 16) {
                    $basis[] = ['检查检验类报告时间【'.$value['BGSJ'].'】'];
                }
            }
        }

        if ($basis) {
            $this->insertData[] = [
                'JZHM' => $ZYH,
                'rule_id' => 100,
                'code' => 'zhengti',
                'error_field' => $caseRule[100]['title'],
                'basis' => json_encode($basis, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 病案首页规则验证
     * @param $caseRule
     * @param $ZYH
     * @return true
     */
    public function basyRuleHandle($caseRule, $ZYH)
    {
        $yzbService = new ElasticsearchService('yzb_2023');
        $bl01NewService = new ElasticsearchService('emr_bl_bl01_new');

        $must = ['term' => ['ZYH' => $ZYH]];
        $should = [
            ['term' => ['YDYZLB' => 303]],
            ['term' => ['YDYZLB' => 305]]
        ];
        $params = $yzbService->clearMust()
            ->queryByMust($must)
            ->queryByShouldBatch($should)
            ->minimumShouldMatch()
            ->getParams();
        $restful = app('es')->search($params);
        $yzbData = $yzbService->getDataByEs($restful);

        if (!empty($yzbData[0])) {
            $XZJDSJ = $yzbData[0][0]['XZJDSJ'];
            $XZJDSJ_END = Carbon::parse($XZJDSJ)->addDay()->toDateTimeString();
            $must = [
                ['term' => ['JZHM' => $ZYH]],
                ['term' => ['BLLB' => 2000001]],
//                ['range' => ['CJSJ' => ['lte' => $XZJDSJ_END]]]
            ];
            $notMust = ['term' => ['BLLB' => 9]];
            $params = $bl01NewService->clearMust()
                ->queryByMustBatch($must)
                ->queryByMustNot($notMust)
                ->getParams();
            $restful = app('es')->search($params);
            $bl01NewData = $bl01NewService->getDataByEs($restful);
            if (!empty($bl01NewData[0])) {
                if ($XZJDSJ_END < $bl01NewData[0][0]['CJSJ']) {
                    $this->insertData[] = [
                        'JZHM' => $ZYH,
                        'rule_id' => 95,
                        'code' => 'basy',
                        'error_field' => $caseRule[95]['title'],
                        'basis' => json_encode([['出院时间【'.$XZJDSJ.'】，病案首页创建时间【'.$bl01NewData[0][0]['CJSJ'].'（超24小时）】']],JSON_UNESCAPED_UNICODE)
                    ];
                }
            } else {
                $this->insertData[] = [
                    'JZHM' => $ZYH,
                    'rule_id' => 95,
                    'code' => 'basy',
                    'error_field' => $caseRule[95]['title'],
                    'basis' => json_encode([['出院时间【'.$XZJDSJ.'】，病案首页创建时间【无】']],JSON_UNESCAPED_UNICODE)
                ];
            }
        }

        return true;
    }

    /**
     * 出院记录规则验证
     * @param $caseRule
     * @param $ZYH
     * @return true
     */
    public function cyjlRuleHandle($caseRule, $ZYH)
    {
        $yzbService = new ElasticsearchService('yzb_2023');
        $bl01Service = new ElasticsearchService('bl01_202303');

        $must = ['term' => ['ZYH' => $ZYH]];
        $should = [
            ['term' => ['YDYZLB' => 303]],
            ['term' => ['YDYZLB' => 305]]
        ];
        $params = $yzbService->clearMust()
            ->queryByMust($must)
            ->queryByShouldBatch($should)
            ->minimumShouldMatch()
            ->getParams();
        $restful = app('es')->search($params);
        $yzbData = $yzbService->getDataByEs($restful);
        if (!empty($yzbData[0])) {
            $XZJDSJ = $yzbData[0][0]['XZJDSJ'];
            $XZJDSJ_END = Carbon::parse($XZJDSJ)->addDay()->toDateTimeString();

            $isOk = 0;

            // 出院记录
            $must = [
                ['term' => ['JZHM' => $ZYH]],
                ['term' => ['BLLB' => 1]]
            ];
            $notMust = ['term' => ['BLLB' => 9]];
            $params = $bl01Service->clearMust()
                ->queryByMustBatch($must)
                ->queryByMustNot($notMust)
                ->getParams();
            $restful = app('es')->search($params);
            $bl01Data = $bl01Service->getDataByEs($restful);
            if (!empty($bl01Data[0])) {
                $isOk = 1;
                if ($XZJDSJ_END < $bl01Data[0][0]['CJSJ']) {
                    $this->insertData[] = [
                        'JZHM' => $ZYH,
                        'rule_id' => 96,
                        'code' => 'cyjl',
                        'error_field' => $caseRule[96]['title'],
                        'basis' => json_encode([['出院时间【'.$XZJDSJ.'】，出院记录创建时间【'.$bl01Data[0][0]['CJSJ'].'（超24小时）】']],JSON_UNESCAPED_UNICODE)
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
                $bl01Data = $bl01Service->getDataByEs($restful);
                if (!empty($bl01Data[0])) {
                    $isOk = 1;
                    if ($XZJDSJ_END < $bl01Data[0][0]['CJSJ']) {
                        $this->insertData[] = [
                            'JZHM' => $ZYH,
                            'rule_id' => 97,
                            'code' => '24cyjl',
                            'error_field' => $caseRule[97]['title'],
                            'basis' => json_encode([['出院时间【'.$XZJDSJ.'】，24小时出入院记录创建时间【'.$bl01Data[0][0]['CJSJ'].'（超24小时）】']],JSON_UNESCAPED_UNICODE)
                        ];
                    }
                } else {
                    // 死亡记录
                    $must = [
                        ['term' => ['ZYH' => $ZYH]],
                        ['term' => ['YDYZLB' => 305]],
                        ['match_phrase' => ['YZMC' => '死亡']]
                    ];
                    $params = $yzbService->clearMust()
                        ->queryByMustBatch($must)
                        ->getParams();
                    $restful = app('es')->search($params);
                    $yzbData = $yzbService->getDataByEs($restful);
                    if (!empty($yzbData[0])) {
                        $XZJDSJ = $yzbData[0][0]['XZJDSJ'];
                        $XZJDSJ_END = Carbon::parse($XZJDSJ)->addDay()->toDateTimeString();
                        $must = [
                            ['term' => ['JZHM' => $ZYH]],
                            ['term' => ['BLLB' => 288]]
                        ];
                        $should = [
                            ['term' => ['MBLB' => 290]],
                            ['term' => ['MBLB' => 288]]
                        ];
                        $notMust = ['term' => ['BLLB' => 9]];
                        $params = $bl01Service->clearMust()
                            ->queryByMustBatch($must)
                            ->queryByMustNot($notMust)
                            ->queryByShouldBatch($should)
                            ->minimumShouldMatch()
                            ->getParams();
                        $restful = app('es')->search($params);
                        $bl01Data = $bl01Service->getDataByEs($restful);
                        if (!empty($bl01Data[0])) {
                            $isOk = 1;
                            if ($XZJDSJ > $bl01Data[0][0]['CJSJ'] || $XZJDSJ_END < $bl01Data[0][0]['CJSJ']) {
                                $this->insertData[] = [
                                    'JZHM' => $ZYH,
                                    'rule_id' => 98,
                                    'code' => 'swjl',
                                    'error_field' => $caseRule[98]['title'],
                                    'basis' => json_encode([['出院时间【'.$XZJDSJ.'】，死亡记录创建时间【'.$bl01Data[0][0]['CJSJ'].'（超24小时）】']],JSON_UNESCAPED_UNICODE)
                                ];
                            }
                        }
                    }
                }
            }

            if (!$isOk) {
                $this->insertData[] = [
                    'JZHM' => $ZYH,
                    'rule_id' => 96,
                    'code' => 'cyjl',
                    'error_field' => $caseRule[96]['title'],
                    'basis' => json_encode([['出院时间【'.$XZJDSJ.'】，出院记录创建时间【无】']],JSON_UNESCAPED_UNICODE)
                ];
            }
        }

        return true;
    }

    /**
     * 24小时出入院记录
     * @param $caseRule
     * @param $ZYH
     * @return true
     */
    public function cyjl24RuleHandle($caseRule, $ZYH)
    {
        $yzbService = new ElasticsearchService('yzb_2023');
        $bl01Service = new ElasticsearchService('bl01_202303');

        $must = ['term' => ['ZYH' => $ZYH]];
        $should = [
            ['term' => ['YDYZLB' => 303]],
            ['term' => ['YDYZLB' => 305]]
        ];
        $params = $yzbService->clearMust()
            ->queryByMust($must)
            ->queryByShouldBatch($should)
            ->minimumShouldMatch()
            ->getParams();
        $restful = app('es')->search($params);
        $yzbData = $yzbService->getDataByEs($restful);
        if (!empty($yzbData[0])) {
            $XZJDSJ = $yzbData[0][0]['XZJDSJ'];
            $XZJDSJ_END = Carbon::parse($XZJDSJ)->addDay()->toDateTimeString();
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
            $bl01Data = $bl01Service->getDataByEs($restful);
            if (!empty($bl01Data[0])) {
                if ($XZJDSJ_END < $bl01Data[0][0]['CJSJ']) {
                    $this->insertData[] = [
                        'JZHM' => $ZYH,
                        'rule_id' => 97,
                        'code' => '24cyjl',
                        'error_field' => $caseRule[97]['title'],
                        'basis' => json_encode([['出院时间【'.$XZJDSJ.'】，24小时出入院记录创建时间【'.$bl01Data[0][0]['CJSJ'].'（超24小时）】']],JSON_UNESCAPED_UNICODE)
                    ];
                }
            } else {
                $this->insertData[] = [
                    'JZHM' => $ZYH,
                    'rule_id' => 97,
                    'code' => '24cyjl',
                    'error_field' => $caseRule[97]['title'],
                    'basis' => json_encode([['出院时间【'.$XZJDSJ.'】，24小时出入院记录创建时间【无】']],JSON_UNESCAPED_UNICODE)
                ];
            }
        }
        return true;
    }

    /**
     * 死亡记录
     * @param $caseRule
     * @param $ZYH
     * @return true
     */
    public function swjlRuleHandle($caseRule, $ZYH)
    {
        $yzbService = new ElasticsearchService('yzb_2023');
        $bl01Service = new ElasticsearchService('bl01_202303');

        $must = [
            ['term' => ['ZYH' => $ZYH]],
            ['term' => ['YDYZLB' => 305]],
            ['term' => ['YZMC' => '死亡']]
        ];
        $params = $yzbService->clearMust()
            ->queryByMustBatch($must)
            ->getParams();
        $restful = app('es')->search($params);
        $yzbData = $yzbService->getDataByEs($restful);
        if (!empty($yzbData[0])) {
            $XZJDSJ = $yzbData[0][0]['XZJDSJ'];
            $XZJDSJ_END = Carbon::parse($XZJDSJ)->addDay()->toDateTimeString();
            $must = [
                ['term' => ['JZHM' => $ZYH]],
                ['term' => ['BLLB' => 288]]
            ];
            $notMust = ['term' => ['BLLB' => 9]];
            $params = $bl01Service->clearMust()
                ->queryByMustBatch($must)
                ->queryByMustNot($notMust)
                ->getParams();
            $restful = app('es')->search($params);
            $bl01Data = $bl01Service->getDataByEs($restful);
            if (!empty($bl01Data[0])) {
                if ($XZJDSJ > $bl01Data[0][0]['CJSJ'] || $XZJDSJ_END < $bl01Data[0][0]['CJSJ']) {
                    $this->insertData[] = [
                        'JZHM' => $ZYH,
                        'rule_id' => 98,
                        'code' => 'swjl',
                        'error_field' => $caseRule[98]['title'],
                        'basis' => json_encode([['出院时间【'.$XZJDSJ.'】，死亡记录创建时间【'.$bl01Data[0][0]['CJSJ'].'（超24小时）】']],JSON_UNESCAPED_UNICODE)
                    ];
                }
            } else {
                $this->insertData[] = [
                    'JZHM' => $ZYH,
                    'rule_id' => 98,
                    'code' => 'swjl',
                    'error_field' => $caseRule[98]['title'],
                    'basis' => json_encode([['出院时间【'.$XZJDSJ.'】，死亡记录创建时间【无】']],JSON_UNESCAPED_UNICODE)
                ];
            }
        }

        return true;
    }

    /**
     * 入院记录
     * @param $caseRule
     * @param $ZYH
     * @return true
     */
    public function ryjlRuleHandle($caseRule, $ZYH)
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
            if (!empty($bl01Data[0])) {
                if ($HCRQ > $bl01Data[0][0]['CJSJ'] || $HCRQ_NED < $bl01Data[0][0]['CJSJ']) {
                    $this->insertData[] = [
                        'JZHM' => $ZYH,
                        'rule_id' => 99,
                        'code' => 'cyjl',
                        'error_field' => $caseRule[99]['title'],
                        'basis' => json_encode([['入院时间【'.$HCRQ.'】，入院记录创建时间【'.$bl01Data[0][0]['CJSJ'].'（超24小时）】']],JSON_UNESCAPED_UNICODE)
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
                    if ($HCRQ > $bl0124Data[0][0]['CJSJ'] || $HCRQ_NED < $bl0124Data[0][0]['CJSJ']) {
                        $this->insertData[] = [
                            'JZHM' => $ZYH,
                            'rule_id' => 99,
                            'code' => 'cyjl',
                            'error_field' => $caseRule[99]['title'],
                            'basis' => json_encode([['入院时间【'.$HCRQ.'】，24小时出入院记录创建时间【'.$bl0124Data[0][0]['CJSJ'].'（超24小时）】']],JSON_UNESCAPED_UNICODE)
                        ];
                    }
                } else {
                    $this->insertData[] = [
                        'JZHM' => $ZYH,
                        'rule_id' => 99,
                        'code' => 'cyjl',
                        'error_field' => $caseRule[99]['title'],
                        'basis' => json_encode([['入院时间【'.$HCRQ.'】，入院记录创建时间【无】']],JSON_UNESCAPED_UNICODE)
                    ];
                }
            }
        }

        return true;
    }

    /**
     * 手术知情同意书
     * @param $caseRule
     * @param $ZYH
     * @return true
     */
    public function sszqtysRuleHandle($caseRule, $ZYH)
    {
        $bl01Service = new ElasticsearchService('bl01_202303');
        $sssqService = new ElasticsearchService('sssq_2023');
        $yzbService = new ElasticsearchService('yzb_2023');

        $must = [
            ['term' => ['JZHM' => $ZYH]],
            ['term' => ['MBLB' => 8]]
        ];
        $notMust = ['term' => ['BLZT' => 9]];
        $params = $bl01Service->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNot($notMust)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);
        if (!empty($bl01Data[0])) {
            $CJSJ = $bl01Data[0][0]['CJSJ'];

            $must = [
                ['term' => ['ZYH' => $ZYH]],
                ['term' => ['ZFBZ' => '0']]
            ];
            $params = $sssqService->clearMust()
                ->queryByMustBatch($must)
                ->getParams();
            $restful = app('es')->search($params);
            $sssqData = $sssqService->getDataByEs($restful);
            if (!empty($sssqData[0])) {
                $NSSMC_LIST = [];
                $should = [];
                foreach ($sssqData[0] as $sssqInfo) {
                    $NSSMC_LIST[] = $sssqInfo['NSSMC'];
                    $should[] = ['match_phrase' => ['YZMC' => $sssqInfo['NSSMC']]];
                }
                $must = [
                    ['term' => ['ZYH' => $ZYH]]
                ];
                $params = $yzbService->clearMust()
                    ->queryByMustBatch($must)
                    ->queryByShouldBatch($should)
                    ->minimumShouldMatch()
                    ->orderBy('KZSJ','asc')
                    ->getParams();
                $restful = app('es')->search($params);
                $yzbData = $yzbService->getDataByEs($restful);
                if (!empty($yzbData[0])) {
                    $isOk = 0;
                    foreach ($yzbData[0] as $yzbInfo) {
                        $KZSJ_END = Carbon::parse($yzbInfo['KZSJ'])->addDay()->toDateTimeString();
                        if ($CJSJ < $KZSJ_END) {
                            $isOk = 1;
                            break;
                        }
                    }
                    if (!$isOk) {
                        $this->insertData[] = [
                            'JZHM' => $ZYH,
                            'rule_id' => 104,
                            'code' => 'sszqtys',
                            'error_field' => $caseRule[104]['title'],
                            'basis' => json_encode([['手术开嘱时间【'.$yzbData[0][0]['KZSJ'].'】，手术知情同意书时间【'.$CJSJ.'（超24小时）】']],JSON_UNESCAPED_UNICODE)
                        ];
                    }
                } else {
                    $this->insertData[] = [
                        'JZHM' => $ZYH,
                        'rule_id' => 104,
                        'code' => 'sszqtys',
                        'error_field' => $caseRule[104]['title'],
                        'basis' => json_encode([['医嘱名称【无（'.implode('、',$NSSMC_LIST).'）】，手术知情同意书时间【'.$CJSJ.'】']],JSON_UNESCAPED_UNICODE)
                    ];
                }
            }
        }

        return true;
    }

    /**
     * 手术记录
     * @param $caseRule
     * @param $ZYH
     * @return true
     */
    public function ssjlRuleHandle($caseRule, $ZYH)
    {
        $mzjlService = new ElasticsearchService('mzjl_2023');
        $bl01Service = new ElasticsearchService('bl01_202303');

        $must = [
            ["term" => ['HOSPIZATIONID' => $ZYH]]
        ];
        $params = $mzjlService->clearMust()
            ->queryByMustBatch($must)
            ->getParams();
        $restful = app('es')->search($params);
        $mzjlData = $mzjlService->getDataByEs($restful);
        if (!empty($mzjlData[0])) {
            $isOk = 0;
            $addData = [];
            foreach ($mzjlData[0] as $mzjlInfo) {
                $OPERATEENDTIME = $mzjlInfo['OPERATEENDTIME'];
                $OPERATEENDTIME_END = Carbon::parse($OPERATEENDTIME)->addDay()->toDateTimeString();

                $where = ['match_phrase' => ['BLMC' => '手术记录']];
                $CJSJ = $this->getSsCJSJ($bl01Service,$ZYH,$where,$OPERATEENDTIME);
                if (!$CJSJ) {
                    $where = ['term' => ['MBLB' => 306]];
                    $CJSJ = $this->getSsCJSJ($bl01Service,$ZYH,$where,$OPERATEENDTIME);
                    if (!$CJSJ) {
                        $where = ['term' => ['MBLB' => 74]];
                        $CJSJ = $this->getSsCJSJ($bl01Service,$ZYH,$where,$OPERATEENDTIME);
                    }
                }

                if ($CJSJ) {
                    if ($OPERATEENDTIME <= $CJSJ && $CJSJ <= $OPERATEENDTIME_END) {
                        $isOk = 1;
                        break;
                    } else {
                        $addData[] = ['手术结束时间【'.$OPERATEENDTIME.'】，手术记录书写时间【'.$CJSJ.'，超过24小时】'];
                    }
                }
            }

            if (!$isOk && $addData) {
                $this->insertData[] = [
                    'JZHM' => $ZYH,
                    'rule_id' => 105,
                    'code' => 'ssjl',
                    'error_field' => $caseRule[105]['title'],
                    'basis' => json_encode($addData,JSON_UNESCAPED_UNICODE)
                ];
            }
        }

        return true;
    }
    public function getSsCJSJ($bl01Service,$ZYH,$where,$OPERATEENDTIME)
    {
        $should = [
            ['match_phrase' => ['HJNR' => date('Y-m-d',strtotime($OPERATEENDTIME))]],
            ['match_phrase' => ['HJNR' => date('Y年m月d',strtotime($OPERATEENDTIME))]]
        ];
        $must = [
            ['term' => ['JZHM' => $ZYH]],
            ['term' => ['BLLB' => 303]]
        ];
        $must[] = $where;
        $notMust = ['term' => ['BLLB' => 9]];
        $params = $bl01Service->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNot($notMust)
            ->queryByShouldBatch($should)
            ->minimumShouldMatch()
            ->getParams();
        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);

        return !empty($bl01Data[0]) ? $bl01Data[0][0]['CJSJ'] : '';
    }

    /**
     * 麻醉类
     * @param $caseRule
     * @param $ZYH
     * @return true
     */
    public function mzlRuleHandle($caseRule, $ZYH)
    {


        return true;
    }

    /**
     * 知情同意书
     * @param $caseRule
     * @param $ZYH
     * @return true
     */
    public function zqtysRuleHandle($caseRule, $ZYH)
    {


        return true;
    }

    /**
     * 检验（细菌培养）
     * @param $caseRule
     * @param $ZYH
     * @return true
     */
    public function jyXjpyRuleHandle($caseRule, $ZYH)
    {
        $ymresultService = new ElasticsearchService('v_jmgs_ymresult_2023');
        $bl01Service = new ElasticsearchService('bl01_202303');

        $must = [
            ["term" => ['ZYH' => $ZYH]]
        ];
        $params = $ymresultService->clearMust()
            ->queryByMustBatch($must)
            ->getParams();
        $restful = app('es')->search($params);
        $ymresultData = $ymresultService->getDataByEs($restful);
        if (!empty($ymresultData[0])) {
            $isOk = 0;
            foreach ($ymresultData[0] as $ymresultInfo) {
                $BGSJ = $ymresultInfo['BGSJ'];

                $bgsjStart = date('Y-m-d',strtotime($BGSJ)).' 00:00:00';
                $bgsjEnd = date('Y-m-d',strtotime($BGSJ)).' 23:59:59';
                $must = [
                    ['term' => ['JZHM' => $ZYH]],
                    ['term' => ['BLLB' => 294]],
                    ['range' => ['CJSJ' => ['gte' => $bgsjStart,'lte' => $bgsjEnd]]]
                ];
                $notMust = ['term' => ['BLLB' => 9]];
                $params = $bl01Service->clearMust()
                    ->queryByMustBatch($must)
                    ->queryByMustNot($notMust)
                    ->orderBy('CJSJ','asc')
                    ->paginate(1,1000)
                    ->getParams();
                $restful = app('es')->search($params);
                $bl01Data = $bl01Service->getDataByEs($restful);
                if (!empty($bl01Data[0])) {
                    $isOk = 1;
                    break;
                }
            }

            if (!$isOk) {
                $this->insertData[] = [
                    'JZHM' => $ZYH,
                    'rule_id' => 108,
                    'code' => 'xyxjpy',
                    'error_field' => $caseRule[108]['title'],
                    'basis' => json_encode([['报告结果时间【'.$BGSJ.'】， 病程记录时间【无】']],JSON_UNESCAPED_UNICODE)
                ];
            }
        }

        return true;
    }


    /**
     * 质控控规则处理（内涵质控）
     * @param $caseRule 规则数据
     * @param $info 病历数据
     * @param $medicinalInfo 化疗药品名称
     * @return void
     */
    public function zkgzNhzkHandle($caseRule, $info, $medicinalInfo)
    {
        $ZYH = $info['MED_REC_ID'];
        $this->insertNhzkData = [];

        $bl01Service = new ElasticsearchService('bl01_202303');

        $must = [
            ['term' => ['JZHM' => $info['MED_REC_ID']]],
            ['term' => ['BLLB' => 1]]
        ];
        $mustNot = ['term' => ['BLZT' => 9]];
        $params = $bl01Service->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNot($mustNot)
            ->source(['BLBH','JZHM', 'HJNR'])
            ->getParams();
        $res = app('es')->search($params);
        $data = $bl01Service->getDataByEs($res);
        $bl01Info = !empty($data[0]) ? $data[0][0] : [];

        // 出院记录-整体
        $this->cyjlZt($caseRule, $info, $bl01Info);

        // 出院记录-出院日期
        $this->cyrqRuleHandle($caseRule, $info, $bl01Info);

        // 出院记录-诊疗经过-手术名称日期
        $this->zljgSsMcRqRuleHandle($caseRule, $info, $bl01Info);

        // 收费里有化疗药，诊疗经过中 搜“时间”+“化疗”
        $this->zljgSfmxSjHl($caseRule, $info, $bl01Info, $medicinalInfo);

        // 长期医嘱-用药
        $this->cqyzyy($caseRule, $info, $bl01Info);

        // 出院诊断 和 首页诊断名称\数量一样
        $this->cyzdMcSl($caseRule, $info, $bl01Info);

        // 收费里有化疗药，出院医嘱中含“血常规” 或 “血细胞分析”
        $this->sfhlyYzXcgXxb($caseRule, $info, $bl01Info, $medicinalInfo);

        // 死亡记录
        $this->swjl($caseRule, $info);

        // 重要辅助检查 :todo 先不做
//        $this->zyfzjc($caseRule, $info, $bl01Info);

        // 医嘱中 “出院带药” ，出院医嘱中有该药的“名称”、 “用量”、“用法” :todo 先不做
//        $this->cyyzCydy($caseRule, $info, $bl01Info);

        $insertData = $this->insertNhzkData;
        if (!empty($insertData)) {
            // 同步修改病例数据的缺陷状态
            PatientInfo::query()->where('MED_REC_ID', '=', $ZYH)->update(['is_defect' => 1]);
            CaseQuality::query()->insert($insertData);
        }
    }

    /**
     * 出院记录-整体（入院情况、初步诊断、诊疗经过、出院情况、出院诊断、出院医嘱内容不能为空）
     * @param $caseRule
     * @param $info
     * @param $bl01Info
     * @return true
     */
    public function cyjlZt($caseRule, $info, $bl01Info)
    {
        if (empty($bl01Info)) {
            return true;
        }

        $HJNR = trim($bl01Info['HJNR']);
        $HJNR = str_replace("：",":",$HJNR);
        $HJNR = str_replace("诊疗过程","诊疗经过",$HJNR);
        $HJNR = str_replace("入院诊断","初步诊断",$HJNR);

        $basis = [];

        // 入院情况
        $ryqk = '';
        $HJNR = str_replace("入院情况:入院情况:","入院情况:",$HJNR);
        if (stripos($HJNR, '入院情况:') !== false) {
            $arr = explode("入院情况:", $HJNR);
            if (!empty($arr[1])) {
                if (stripos($arr[1], '初步诊断:') !== false) {
                    $arr = explode("初步诊断:", $arr[1]);
                } elseif (stripos($arr[1], '诊疗经过:') !== false) {
                    $arr = explode("诊疗经过:", $arr[1]);
                }
                $ryqk = trim($arr[0]);
            }
        }
        if (!$ryqk) {
            $basis[] = ["入院情况（无）"];
        }

        // 初步诊断
        $cbzd = '';
        $HJNR = str_replace("初步诊断:初步诊断:","初步诊断:",$HJNR);
        if (stripos($HJNR, '初步诊断:') !== false) {
            $arr = explode("初步诊断:", $HJNR);
            if (!empty($arr[1])) {
                if (stripos($arr[1], '诊疗经过:') !== false) {
                    $arr = explode("诊疗经过:", $arr[1]);
                } elseif (stripos($arr[1], '出院情况:') !== false) {
                    $arr = explode("出院情况:", $arr[1]);
                }
                $cbzd = trim($arr[0]);
            }
        }
        if (!$cbzd) {
            $basis[] = ["初步诊断（无）"];
        }

        // 诊疗经过
        $zljg = '';
        $HJNR = str_replace("诊疗经过:诊疗经过:","诊疗经过:",$HJNR);
        if (stripos($HJNR, '诊疗经过:') !== false) {
            $arr = explode("诊疗经过:", $HJNR);
            if (!empty($arr[1])) {
                if (stripos($arr[1], '出院情况:') !== false) {
                    $arr = explode("出院情况:", $arr[1]);
                } elseif (stripos($arr[1], '出院诊断:') !== false) {
                    $arr = explode("出院诊断:", $arr[1]);
                }

                $zljg = $arr[0];
            }
        }
        if (!$zljg) {
            $basis[] = ["诊疗经过（无）"];
        }

        // 出院情况
        $cyqk = '';
        $HJNR = str_replace("出院情况:出院情况:","出院情况:",$HJNR);
        if (stripos($HJNR, '出院情况:') !== false) {
            $arr = explode("出院情况:", $HJNR);
            if (!empty($arr[1])) {
                if (stripos($arr[1], '出院诊断:') !== false) {
                    $arr = explode("出院诊断:", $arr[1]);
                } elseif (stripos($arr[1], '出院医嘱:') !== false) {
                    $arr = explode("出院诊断:", $arr[1]);
                }

                $cyqk = $arr[0];
            }
        }
        if (!$cyqk) {
            $basis[] = ["出院情况（无）"];
        }

        // 出院诊断
        $cyzd = '';
        $HJNR = str_replace("出院诊断:出院诊断:","出院诊断:",$HJNR);
        if (stripos($HJNR, '出院诊断:') !== false) {
            $arr = explode("出院诊断:", $HJNR);
            if (!empty($arr[1])) {
                if (stripos($arr[1], '出院医嘱:') !== false) {
                    $arr = explode("出院诊断:", $arr[1]);
                } elseif (stripos($arr[1], '医师签名:') !== false) {
                    $arr = explode("医师签名:", $arr[1]);
                }

                $cyzd = $arr[0];
            }
        }
        if (!$cyzd) {
            $basis[] = ["出院诊断（无）"];
        }

        // 出院医嘱
        $cyyz = '';
        $HJNR = str_replace("出院医嘱:出院医嘱:","出院医嘱:",$HJNR);
        $HJNR = str_replace(" ", "", $HJNR);
        if (stripos($HJNR, '出院医嘱:') !== false) {
            $arr = explode("出院医嘱:", $HJNR);
            if (!empty($arr[1])) {
                if (stripos($arr[1], '医师签名:') !== false) {
                    $arr = explode("医师签名:", $arr[1]);
                } elseif (stripos($arr[1], '医师签名') !== false) {
                    $arr = explode("医师签名", $arr[1]);
                } elseif (stripos($arr[1], '第1页') !== false) {
                    $arr = explode("第1页", $arr[1]);
                } elseif (stripos($arr[1], '第2页') !== false) {
                    $arr = explode("第2页", $arr[1]);
                } elseif (stripos($arr[1], '第3页') !== false) {
                    $arr = explode("第3页", $arr[1]);
                } elseif (stripos($arr[1], '滨州医学院烟台附属医院') !== false) {
                    $arr = explode("滨州医学院烟台附属医院", $arr[1]);
                }

                $cyyz = $arr[0];
            }
        }
        if (!$cyyz) {
            $basis[] = ["出院医嘱（无）"];
        }

        if ($basis) {
            $this->insertNhzkData[] = [
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 151,
                'code' => 'cyjl_zt',
                'error_field' => $caseRule[151]['title'],
                'basis' => json_encode($basis, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 出院记录日期（内涵质控）
     * @param $caseRule
     * @param $info
     * @param $bl01Info
     * @return true
     */
    public function cyrqRuleHandle($caseRule, $info, $bl01Info)
    {
        if (empty($bl01Info)) {
            return true;
        }

        // 取日期
        $AAC01_RQ = date('Y-m-d', strtotime($info['AAC01']));

        $HJNR = $bl01Info['HJNR'];
        $HJNR = str_replace("：",":",$HJNR);
        $arr = explode("出院日期", $HJNR);
        $basis = [];
        if (!empty($arr[1])) {
            $cyrqArr = explode("\n", $arr[1]);
            $cyrq = trim($cyrqArr[0]);
            $cyrq = trim(str_replace(":","",$cyrq));
            if ($cyrq) {
                $cyrq = str_replace("年","-",$cyrq);
                $cyrq = str_replace("月","-",$cyrq);
                $cyrq = str_replace("日","",$cyrq);

                //$cyrq = substr($cyrq,0,10);
                $cyrq = explode(" ", $cyrq);
                $cyrq = $cyrq[0];

                $cyrq = date('Y-m-d', strtotime($cyrq));
            } else {
                $cyrq = '无';
            }

            if ($cyrq != $AAC01_RQ) {
                $basis[] = ['出院记录中出院日期【'.$cyrq.'】首页出院时间【'.$AAC01_RQ.'】'];
            }
        } else {
            $basis[] = ['出院记录中出院日期【无】首页出院时间【'.$AAC01_RQ.'】'];
        }

        if ($basis) {
            $this->insertNhzkData[] = [
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 136,
                'code' => 'cyjl_cyrq',
                'error_field' => $caseRule[136]['title'],
                'basis' => json_encode($basis, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 出院记录-诊疗经过-手上名称日期
     * @param $caseRule
     * @param $info
     * @param $bl01Info
     * @return true
     */
    public function zljgSsMcRqRuleHandle($caseRule, $info, $bl01Info)
    {
        if (empty($bl01Info)) {
            return true;
        }
        $ZYH = $info['MED_REC_ID'];

        // 获取诊疗过程
        $HJNR = $bl01Info['HJNR'];
        $HJNR = str_replace("：",":",$HJNR);
        $HJNR = str_replace("诊疗经过","诊疗过程",$HJNR);
        $arr = explode("诊疗过程", $HJNR);
        $zlgc = '';
        if (!empty($arr[1])) {
            if (stripos($arr[1], '出院情况') !== false) {
                $arr = explode("出院情况", $arr[1]);
            } elseif (stripos($arr[1], '出院诊断') !== false) {
                $arr = explode("出院诊断", $arr[1]);
            } elseif (stripos($arr[1], '出院医嘱') !== false) {
                $arr = explode("出院医嘱", $arr[1]);
            }

            $zlgc = $arr[0];
        }

        // 查询手术记录
        $bllb303Service = new ElasticsearchService('bllb303_2023');
        $must = ['term' => ['ZYH' => $ZYH]];
        $params = $bllb303Service->clearMust()
            ->queryByMust($must)
            ->source(['SSRQ', 'SSMC'])
            ->getParams();
        $res = app('es')->search($params);
        $data = $bllb303Service->getDataByEs($res);
        $basis = [];
        if (!empty($data[0])) {
            foreach ($data[0] as $bllb303Info) {
                $str = '';
                $str1 = '';
                $isOk = 1;
                if ($bllb303Info['SSRQ']) {
                    $SSRQ = date('Y年m月d', strtotime($bllb303Info['SSRQ']));
                    $SSRQ1 = date('Y年n月j', strtotime($bllb303Info['SSRQ']));
                    $SSRQ2 = date('Y-n-j', strtotime($bllb303Info['SSRQ']));
                    $SSRQ3 = date('Y-m-d', strtotime($bllb303Info['SSRQ']));
                    $SSRQ4 = date('Y.n.j', strtotime($bllb303Info['SSRQ']));
                    $SSRQ5 = date('Y.m.d', strtotime($bllb303Info['SSRQ']));
                    if (stripos($zlgc, $bllb303Info['SSRQ']) === false && stripos($zlgc, $SSRQ) === false && stripos($zlgc, $SSRQ1) === false && stripos($zlgc, $SSRQ2) === false && stripos($zlgc, $SSRQ3) === false && stripos($zlgc, $SSRQ4) === false && stripos($zlgc, $SSRQ5) === false) {
                        $isOk = 0;
                        $str .= '手术记录【'.$bllb303Info['SSRQ'].'】';
                        $str1 .= '诊疗记录【时间（无）】';
                    }
                } else {
                    $isOk = 0;
                    $str .= '手术记录【手术日期（无）】';
                }
                if ($bllb303Info['SSMC']) {
                    if (stripos($zlgc, $bllb303Info['SSMC']) === false) {
                        $isOk = 0;
                        $str .= '【'.$bllb303Info['SSMC'].'】';
                        $str1 .= '【化疗（无）】';
                    }
                } else {
                    $isOk = 0;
                    $str .= '【手术名称（无）】';
                }
                if (!$isOk) {
                    $basis[] = [$str.'，'.$str1];
                }
            }

            if ($basis) {
                $this->insertNhzkData[] = [
                    'JZHM' => $info['MED_REC_ID'],
                    'rule_id' => 137,
                    'code' => 'cyjl_zljg_ssmcrq',
                    'error_field' => $caseRule[137]['title'],
                    'basis' => json_encode($basis, JSON_UNESCAPED_UNICODE)
                ];
            }
        }

        return true;
    }

    /**
     * 收费里有化疗药，诊疗经过中 搜“时间”+“化疗”
     * @param $caseRule
     * @param $info
     * @param $bl01Info
     * @param $medicinalInfo
     * @return true
     */
    public function zljgSfmxSjHl($caseRule, $info, $bl01Info, $medicinalInfo)
    {
        if (empty($bl01Info['HJNR'])) {
            return true;
        }

        $ZYH = $info['MED_REC_ID'];

        // 查询费用明细
        $feeService = new ElasticsearchService('fee_detailed');
        $should = [];
        foreach ($medicinalInfo as $name) {
            $should[] = ['match_phrase' => ['pre_FYMC' => $name]];
        }
        $must = ['term' => ['MED_REC_ID' => $ZYH]];
        $params = $feeService->clearMust()
            ->queryByMust($must)
            ->queryByShouldBatch($should)
            ->source(['MED_REC_ID','FYXH','pre_FYMC','FYMC','JFRQ'])
            ->minimumShouldMatch()
            ->paginate(1,10000)
            ->trackTotalHits()
            ->getParams();
        $res = app('es')->search($params);
        $data = $feeService->getDataByEs($res);
        if (empty($data[0])) {
            return true;
        }

        // 获取诊疗过程
        $HJNR = $bl01Info['HJNR'];
        $HJNR = str_replace("：",":",$HJNR);
        $HJNR = str_replace("诊疗经过","诊疗过程",$HJNR);
        $arr = explode("诊疗过程", $HJNR);
        $zlgc = '';
        if (!empty($arr[1])) {
            if (stripos($arr[1], '出院情况') !== false) {
                $arr = explode("出院情况", $arr[1]);
            } elseif (stripos($arr[1], '出院诊断') !== false) {
                $arr = explode("出院诊断", $arr[1]);
            } elseif (stripos($arr[1], '出院医嘱') !== false) {
                $arr = explode("出院医嘱", $arr[1]);
            }

            $zlgc = $arr[0];
        }

        $arr = [];
        $basis = [];
        foreach ($data[0] as $value) {
            if (!in_array($value['pre_FYMC'],$arr)) {
                $arr[] = $value['pre_FYMC'];

                // 匹配日期、化疗药品
                if (!preg_match('/\d{4}(\-|\/|.|年)\d{1,2}(\-|\/|.|月)\d{1,2}/', $zlgc) && stripos($zlgc, $value['pre_FYMC']) === false) {
                    $basis[] = ['收费【'.$value['pre_FYMC'].'】，诊疗过程【 时间（无），化疗（无）】'];
                } elseif (!preg_match('/\d{4}(\-|\/|.|年)\d{1,2}(\-|\/|.|月)\d{1,2}/', $zlgc)) {
                    $basis[] = ['收费【'.$value['pre_FYMC'].'】，诊疗过程【 时间（无）】'];
                } elseif (stripos($zlgc, $value['pre_FYMC']) === false) {
                    $basis[] = ['收费【'.$value['pre_FYMC'].'】，诊疗过程【化疗（无）】'];
                }
            }
        }

        if ($basis) {
            $this->insertNhzkData[] = [
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 138,
                'code' => 'cyjl_zljg_sfmxsjhl',
                'error_field' => $caseRule[138]['title'],
                'basis' => json_encode($basis, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 长期医嘱用药
     * @param $caseRule
     * @param $info
     * @param $bl01Info
     * @return true
     */
    public function cqyzyy($caseRule, $info, $bl01Info)
    {
        if (empty($bl01Info['HJNR'])) {
            return true;
        }

        $ZYH = $info['MED_REC_ID'];

        $yzbService = new ElasticsearchService('yzb_2023');
        $must = [
            ['term' => ['ZYH' => $ZYH]],
            ['term' => ['YZQX' => 1]],
            ['term' => ['XMLB' => 1]]
        ];
        $params = $yzbService->clearMust()
            ->queryByMustBatch($must)
            ->paginate(1,1000)
            ->trackTotalHits()
            ->getParams();
        $res = app('es')->search($params);
        $data = $yzbService->getDataByEs($res);
        $ypmcList = [];
        if (!empty($data[0])) {
            foreach ($data[0] as $val) {
                $ym = str_replace('［','[',$val['YZMC']);
                $stratLen = stripos($ym,'[');
                if ($stratLen) {
                    $ym = substr($ym,0,$stratLen);
                }

                $ym = str_replace('（','(',$ym);
                $stratLen = stripos($ym,'(');
                if ($stratLen) {
                    $ym = substr($ym,0,$stratLen);
                }

                $stratLen = stripos($ym,'#');
                if ($stratLen) {
                    $ym = substr($ym,0,$stratLen);
                }
                if (!in_array($ym,$ypmcList)) {
                    $ypmcList[] = $ym;
                }
            }
        }

        if (empty($ypmcList)) {
            return true;
        }

        // 获取诊疗过程
        $HJNR = $bl01Info['HJNR'];
        $HJNR = str_replace("：",":",$HJNR);
        $HJNR = str_replace("诊疗经过","诊疗过程",$HJNR);
        $arr = explode("诊疗过程", $HJNR);
        $zlgc = '';
        if (!empty($arr[1])) {
            if (stripos($arr[1], '出院情况') !== false) {
                $arr = explode("出院情况", $arr[1]);
            } elseif (stripos($arr[1], '出院诊断') !== false) {
                $arr = explode("出院诊断", $arr[1]);
            } elseif (stripos($arr[1], '出院医嘱') !== false) {
                $arr = explode("出院医嘱", $arr[1]);
            }

            $zlgc = $arr[0];
        }

        $basis = [];
        foreach ($ypmcList as $ypmc) {
            if (stripos($zlgc, $ypmc) === false) {
                $basis[] = ['无药品：'.$ym];
            }
        }

        if ($basis) {
            $this->insertNhzkData[] = [
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 141,
                'code' => 'cyjl_zljg_cqyzyy',
                'error_field' => $caseRule[141]['title'],
                'basis' => json_encode($basis, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 重要辅助检查
     * @param $caseRule
     * @param $info
     * @param $bl01Info
     * @return true
     */
    public function zyfzjc($caseRule, $info, $bl01Info)
    {
        if (empty($bl01Info['HJNR'])) {
            return true;
        }

        // 获取诊疗过程
        $HJNR = $bl01Info['HJNR'];
        $HJNR = str_replace("：",":",$HJNR);
        $HJNR = str_replace("诊疗经过","诊疗过程",$HJNR);
        $arr = explode("诊疗过程", $HJNR);
        $zlgc = '';
        if (!empty($arr[1])) {
            if (stripos($arr[1], '出院情况') !== false) {
                $arr = explode("出院情况", $arr[1]);
            } elseif (stripos($arr[1], '出院诊断') !== false) {
                $arr = explode("出院诊断", $arr[1]);
            } elseif (stripos($arr[1], '出院医嘱') !== false) {
                $arr = explode("出院医嘱", $arr[1]);
            }

            $zlgc = $arr[0];
        }

        dd($zlgc);

        return true;
    }

    /**
     * 收费里有化疗药，出院医嘱中含“血常规” 或 “血细胞分析”
     * @param $caseRule
     * @param $info
     * @return true
     */
    public function sfhlyYzXcgXxb($caseRule, $info, $bl01Info, $medicinalInfo)
    {
        if (empty($bl01Info['HJNR'])) {
            return true;
        }

        $ZYH = $info['MED_REC_ID'];

        // 查询费用明细
        $feeService = new ElasticsearchService('fee_detailed');
        $should = [];
        foreach ($medicinalInfo as $name) {
            $should[] = ['match_phrase' => ['pre_FYMC' => $name]];
        }
        $must = ['term' => ['MED_REC_ID' => $ZYH]];
        $params = $feeService->clearMust()
            ->queryByMust($must)
            ->queryByShouldBatch($should)
            ->source(['MED_REC_ID','FYXH','pre_FYMC','FYMC','JFRQ'])
            ->minimumShouldMatch()
            ->paginate(1,10000)
            ->trackTotalHits()
            ->getParams();
        $res = app('es')->search($params);
        $data = $feeService->getDataByEs($res);

        $basis = '';
        if (empty($data[0])) {
            $str = '收费里【化疗药（无）】';
        }

        // 获取诊疗过程
        $HJNR = $bl01Info['HJNR'];
        $arr = explode("出院医嘱", $HJNR);
        if (!empty($arr[1])) {
            if (stripos($arr[1], '医师签名') !== false) {
                $arr = explode("医师签名", $arr[1]);
            }
            $cyyz = $arr[0];
            if (stripos($cyyz, '血常规') === false || stripos($cyyz, '血细胞分析') === false) {
                $basis .= '出院医嘱【不含“血常规” 或 “血细胞分析”】';
            }
        } else {
            $basis .= '出院医嘱【不含“血常规” 或 “血细胞分析”】';
        }

        if ($basis) {
            $this->insertNhzkData[] = [
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 147,
                'code' => 'cyjl_cyyz_hlyXcgXxb',
                'error_field' => $caseRule[147]['title'],
                'basis' => json_encode([[$basis]], JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 医嘱中 “出院带药” ，出院医嘱中有该药的“名称”、 “用量”、“用法”
     * @param $caseRule
     * @param $info
     * @return true
     */
    public function cyyzCydy($caseRule, $info, $bl01Info)
    {
        $ZYH = $info['MED_REC_ID'];

        // 查询医嘱中 “出院带药”
        $yzbService = new ElasticsearchService('yzb_2023');
        $must = [
            ['term' => ['ZYH' => $ZYH]],
            ['match_phrase' => ['YZMC' => '出院带药']]
        ];
        $params = $yzbService->clearMust()
            ->queryByMustBatch($must)
            ->trackTotalHits()
            ->getParams();
        $res = app('es')->search($params);
        $data = $yzbService->getDataByEs($res);
        if (empty($data[0])) {
            return true;
        }

        // 获取诊疗过程
        $HJNR = $bl01Info['HJNR'];
        $HJNR = str_replace("：",":",$HJNR);
        $arr = explode("出院医嘱", $HJNR);
        $cyyz = '';
        if (!empty($arr[1])) {
            if (stripos($arr[1], '医师签名') !== false) {
                $arr = explode("医师签名", $arr[1]);
                $arr = explode("用药指导:",$arr[0]);
                if (!empty($arr[1])) {
                    $arr[1] = str_replace("3.疼痛与康复指导:","疼痛与康复指导:",$arr[1]);
                    $arr = explode("疼痛与康复指导:",$arr[1]);
                    $cyyz = explode("\n", trim($arr[0]));
                }
            }
        }
        $basis = [];
        if ($cyyz) {
            foreach ($cyyz as $yp) {
                $isOk = 1;
                $str = '';
                $yp = str_replace("   "," ",trim($yp));
                $yp = str_replace("  ", " ",trim($yp));
                $ypData = explode(" ",trim($yp));

                if (!empty($ypData[0])) {
                    $str .= "药品【”.$ypData[0].“】";
                } else {
                    $str .= "药品【无】";
                    $isOk = 0;
                }
                if (!empty($ypData[1])) {
                    $str .= "用量【”.$ypData[1].“】";
                } else {
                    $str .= "用量【无】";
                    $isOk = 0;
                }
                if (!empty($ypData[2])) {
                    $ypData[2] = str_replace("，",",",$ypData[2]);
                    $yf = explode(",",$ypData[2]);
                    $str .= "用法【”.$yf[0].“】";
                } else {
                    $str .= "用法【无】";
                    $isOk = 0;
                }

                if (!$isOk) {
                    $basis[] = [$str];
                }
            }
        }

        if ($basis) {
            $this->insertNhzkData[] = [
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 148,
                'code' => 'cyjl_cyyz_cydy',
                'error_field' => $caseRule[148]['title'],
                'basis' => json_encode($basis, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 出院诊断和首页诊断名称\数量一样
     * @param $caseRule
     * @param $info
     * @return true
     */
    public function cyzdMcSl($caseRule, $info, $bl01Info)
    {
        if (empty($bl01Info)) {
            return true;
        }
        $ZYH = $info['MED_REC_ID'];

        // 获取出院诊断
        $bllb1Service = new ElasticsearchService('bllb1_2023');
        $must = ['term' => ['BLBH' => $bl01Info['BLBH']]];
        $params = $bllb1Service->clearMust()
            ->queryByMust($must)
            ->paginate(1,500)
            ->getParams();
        $res = app('es')->search($params);
        $data = $bllb1Service->getDataByEs($res);
        $bl01CyzdNameList = [];
        if (!empty($data[0])) {
            foreach ($data[0] as $value) {
                if (!in_array($value['name'],$bl01CyzdNameList)) {
                    $bl01CyzdNameList[] = $value['name'];
                }
            }
        }

        // 查询主要诊断
        $mdService = new ElasticsearchService('main_diagnosis');
        $must = ['term' => ['ZYH' => $ZYH]];
        $params = $mdService->clearMust()
            ->queryByMust($must)
            ->source(['ICD10_NAME'])
            ->getParams();
        $res = app('es')->search($params);
        $data = $mdService->getDataByEs($res);
        $ICD10_NAME_LIST = [];
        if (!empty($data[0])) {
            $ICD10_NAME_LIST[] = $data[0][0]['ICD10_NAME'];
        }

        // 查询其它诊断
        $odService = new ElasticsearchService('other_diagnosis_2023');
        $must = ['term' => ['ZYH' => $ZYH]];
        $params = $odService->clearMust()
            ->queryByMust($must)
            ->source(['ICD10_NAME'])
            ->paginate(1,500)
            ->getParams();
        $res = app('es')->search($params);
        $data = $odService->getDataByEs($res);
        if (!empty($data[0])) {
            foreach ($data[0] as $val) {
                if (!in_array($val['ICD10_NAME'],$ICD10_NAME_LIST)) {
                    $ICD10_NAME_LIST[] = $val['ICD10_NAME'];
                }
            }
        }

        $basis = [];
        if (count($ICD10_NAME_LIST) != count($bl01CyzdNameList)) {
            $basis[] = ['首页诊断【数量：'.count($ICD10_NAME_LIST).'】，出院诊断【数量：'.count($bl01CyzdNameList).'】'];
        }

        if ($ICD10_NAME_LIST) {
            foreach ($ICD10_NAME_LIST as $ICD10_NAME) {
                if (!in_array($ICD10_NAME,$bl01CyzdNameList)) {
                    $basis[] = ['首页诊断【'.$ICD10_NAME.'】，出院诊断【无】'];
                }
            }
        }

        if ($basis) {
            $this->insertNhzkData[] = [
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 146,
                'code' => 'cyjl_cyzd',
                'error_field' => $caseRule[146]['title'],
                'basis' => json_encode($basis, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 死亡记录
     * @param $caseRule
     * @param $info
     * @return true
     */
    public function swjl($caseRule, $info)
    {
        // 获取死亡记录
        $bl01Service = new ElasticsearchService('bl01_202303');
        $must = [
            ['term' => ['JZHM' => $info['MED_REC_ID']]],
            ['term' => ['BLLB' => 288]]
        ];
        $should = [
            ['term' => ['MBLB' => 288]],
            ['term' => ['MBLB' => 290]]
        ];
        $mustNot = ['term' => ['BLZT' => 9]];
        $params = $bl01Service->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNot($mustNot)
            ->queryByShouldBatch($should)
            ->minimumShouldMatch()
            ->source(['BLBH','JZHM', 'HJNR'])
            ->getParams();
        $res = app('es')->search($params);
        $data = $bl01Service->getDataByEs($res);
        if (empty($data[0])) {
            return true;
        }

        $HJNR = $data[0][0]['HJNR'];
        $time = preg_match('/\d{1,2}(\：|\:|\/|.|)\d{1,2}/', $HJNR);
        $res1 = stripos($HJNR, '呼吸停止');
        $res2 = stripos($HJNR, '心跳停止');
        $res3 = stripos($HJNR, '停止抢救');
        $basis = [];
        if (!$time && $res1===false && $res2===false && $res3===false) {
            $basis[] = ['死亡记录【无（呼吸停止+时间 或 心跳停止+时间 或 停止抢救+时间）】'];
        } elseif (!$time) {
            $basis[] = ['死亡记录【无（时间）】'];
        } elseif ($res1===false && $res2===false && $res3===false) {
            $basis[] = ['死亡记录【无（呼吸停止 或 心跳停止 或 停止抢救）】'];
        }

        if ($basis) {
            $this->insertNhzkData[] = [
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => 150,
                'code' => 'swjl_swmcsj',
                'error_field' => $caseRule[150]['title'],
                'basis' => json_encode($basis, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }


}
