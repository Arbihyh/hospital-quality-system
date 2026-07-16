<?php

namespace App\Console\Commands;

use App\Model\MS_GHMX;
use App\Model\OMR_BL01;
use App\Model\OmrQuality;
use App\Model\OmrRule;
use App\Services\ElasticsearchService;
use App\Services\PublicService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class OmrZkRule extends Command
{
    protected $insertData = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rule:omrRule {page?} {type?} {startTime?} {endTime?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '门诊病历质控（规则）处理';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $page = $this->argument('page') ?: 1;
        $type = $this->argument('type') ?: 'omr_bl01';

        $startTime = $this->argument('startTime');
        $endTime = $this->argument('endTime');

        if ($type == 'omr_bl01') {
            // 门诊病历
            $this->info('门诊病历质控（规则）处理 - 开始处理');
            $this->omrZk($page,$startTime,$endTime);
            $this->info('门诊病历质控（规则）处理 - 处理完毕');
        } elseif($type == 'ghmx') {
            // 挂号明细
            $this->info('挂号明细（规则）处理 - 处理完毕');
            $this->msGhmx($page,$startTime,$endTime);
            $this->info('挂号明细（规则）处理 - 处理完毕');
        }
    }

    /**
     * update OMR_BL01 set is_defect=0 where is_defect=1;
     * truncate `omr_quality`;
     * @param $page
     * @return true
     */
    protected function omrZk($page,$startTime,$endTime)
    {
        // 获取规则
        $omrRule = OmrRule::query()->get()->toArray();
        $omrRuleData = array_column($omrRule, null, 'id');

        // 要调用的方法
        $methodList = [
            1 => 'rule1',    // 整体（收费项目）
            2 => 'rule2',    // 整体（初诊）
            3 => 'rule3',    // 整体（复诊）
            5 => 'rule5',    // 整体（有 {}）
            6 => 'rule6',    // 整体（复诊）重复率
            7 => 'rule7',    // 主诉（不超过20个字）
            8 => 'rule8',    // 诊疗意见（药品名称）
        ];

        if ($startTime && $endTime) {
            $field = 'CJSJ';
            $startTime .= ' 00:00:00';
            $endTime .= ' 23:59:59';
        } else {
            $field = 'created_at';
            $date = Carbon::parse()->addDay(-1)->toDateString();
            $startTime .= $date.' 00:00:00';
            $endTime .= $date.' 23:59:59';
        }

        while (true) {
            $data = OMR_BL01::query()
                ->whereBetween($field,[$startTime, $endTime])
                ->paginate(500, ['*'],'page', $page)
                ->toArray();

            if ($page == 1) {
                echo '数据总条数：'.$data['total'].PHP_EOL.'总页数：'.$data['last_page'].PHP_EOL;
            } elseif ($page > $data['last_page']) {
                // 如果当前页码大于最大页码则结束
                break;
            }
            echo $page.PHP_EOL;
            $page++;

            $insert = [];
            $updateOmrBl01Id = [];
            foreach ($data['data'] as $omrInfo) {
                $this->insertData = [];

                foreach ($methodList as $ruleId => $method) {
                    // 规则关闭则不进行质控
                    if (empty($omrRuleData[$ruleId]['status'])) {
                        continue;
                    }

                    // 调用质控规则
                    $this->$method($omrRuleData,$ruleId,$omrInfo);
                }

                // 数据写入
                $insertDataList = $this->insertData;
                if ($insertDataList) {
                    $updateOmrBl01Id[] = $omrInfo['id'];
                    foreach ($insertDataList as &$value) {
                        $value['mzh'] = $omrInfo['mzh'];
                        $value['xm'] = $omrInfo['xm'];
                        $value['xb'] = $omrInfo['xb'];
                        $value['nl'] = $omrInfo['nl'];
                        $value['cbzd'] = $omrInfo['cbzd'];
                        $value['BRKS'] = $omrInfo['BRKS'];
                        $value['SFZH'] = $omrInfo['SFZH'];
                        $value['SXYS'] = $omrInfo['SXYS'];
                        $value['jzsj'] = $omrInfo['jzsj'];
                        $value['bl_type'] = $omrInfo['bl_type'];

                        $insert[] = $value;
                    }
                }
            }

            // 质控后数据记录
            OmrQuality::query()->insert($insert);
            // 门诊病历状态标记
            OMR_BL01::query()->whereIn('id',$updateOmrBl01Id)->update(['is_defect'=>1]);
        }

        return true;
    }

    /**
     * 整体（收费项目）
     * @param $omrRuleData
     * @param $omrInfo
     * @return array|true
     */
    public function rule1($omrRuleData,$ruleId,$omrInfo)
    {
        // 过滤科室
        // $ksIdList = [213,1041,4285]; // 住院病历科室
        $ksIdList = [129,162,272,372,365,366,314,315];  // 门诊病历科室

        if (in_array($omrInfo['BRKS'],$ksIdList) || empty($omrInfo['jzsj']) || $omrInfo['jzsj'] == '0000-00-00 00:00:00') {
            return true;
        }

        $msGhmxService = new ElasticsearchService('ms_ghmx_2023');

        $BRID = $omrInfo['BRID'];
        $CJSJ = $omrInfo['CJSJ'];
        $JZSJ = $omrInfo['jzsj'];
        $JZSJ = date('Y-m-d',strtotime($JZSJ));

        $must = [
            ['term' => ['BRID' => $BRID]],
            ['range' => ['JZRQ' => ['gte' => $JZSJ.' 00:00:00', 'lte' => $JZSJ. ' 23:59:59']]],
        ];
        $params = $msGhmxService->clearMust()
            ->queryByMustBatch($must)
            ->orderBy('JZRQ','asc')
            ->getParams();
        $restful = app('es')->search($params);
        $data = $msGhmxService->getDataByEs($restful);
        $JZRQ_ARR = [];
        if (!empty($data[0])) {
            $msThmxService = new ElasticsearchService('ms_thmx_2023');
            foreach ($data[0] as $value) {
                $must = [
                    ['term' => ['SBXH' => $value['SBXH']]]
                ];
                $params = $msThmxService->clearMust()
                    ->queryByMustBatch($must)
                    ->getParams();
                $restful = app('es')->search($params);
                $data = $msThmxService->getDataByEs($restful);
                if (empty($data[0])) {
                    $JZRQ_ARR[] = $value['JZRQ'];
                }
            }
        }

        if (!empty($JZRQ_ARR)) {
            $isOk = 0;
            foreach ($JZRQ_ARR as $val) {
                $JZRQ = date('Y-m-d',strtotime($val));
                $newCJSJ = date('Y-m-d',strtotime($CJSJ));
                if ($JZRQ == $newCJSJ) {
                    $isOk = 1;
                    break;
                }
            }

            if (!$isOk) {
                $this->insertData[] = [
                    'BLBH' => $omrInfo['BLBH'],
                    'JZXH' => $omrInfo['JZXH'],
                    'BRID' => $BRID,
                    'rule_id' => $ruleId,
                    'code' => 'omr_rule_'.$ruleId,
                    'error_field' => $omrRuleData[$ruleId]['title'],
                    'basis' => json_encode([['挂号费日期【'.$JZRQ.'】，病历创建时间【'.$newCJSJ.'，不在当天】']],JSON_UNESCAPED_UNICODE)
                ];
            }
        }

        return true;
    }

    /**
     * 整体（初诊）
     * @param $omrRuleData
     * @param $omrInfo
     * @return true
     */
    public function rule2($omrRuleData,$ruleId,$omrInfo)
    {
        // 判断是不是初诊病历
        if ($omrInfo['bl_type'] != '初诊') {
            return true;
        }
        $array = explode('滨州医学院烟台附属医院',$omrInfo['BLNR_TXT']);
        if (empty($array[1])) {
            return true;
        }
        if (stripos($array[1],'初诊') === false) {
            return true;
        }

        $omrBlsyService = new ElasticsearchService('omr_blsy_2023');
        $staffService = new ElasticsearchService('staff_2023');

        $zkFiidle = [
            'zs' => '主诉',
            'xbs' => '现病史',
            'jws' => '既往史',
            'tgjc' => '体格检查',
            'fzjc' => '辅助检查',
            'cbzd' => '初步诊断',
            'zlyj' => '诊疗意见'
        ];

        $basis = [];
        foreach ($zkFiidle as $key => $value) {
            if (empty($omrInfo[$key])) {
                $basis[] = ['缺少：'.$zkFiidle[$key]];
            }
        }

        // 门诊医生签名
        if (!empty($omrInfo['SXYS'])) {
            $must = [
                ['term' => ['BLBH'=>$omrInfo['BLBH']]],
                ['term' => ['SYYS'=>$omrInfo['SXYS']]]
            ];
            $params = $omrBlsyService->clearMust()
                ->queryByMustBatch($must)
                ->getParams();
            $restful = app('es')->search($params);
            $data = $omrBlsyService->getDataByEs($restful);
            if (empty($data[0])) {
                $must = [
                    ['term' => ['code'=>$omrInfo['SXYS']]]
                ];
                $params = $staffService->clearMust()
                    ->queryByMustBatch($must)
                    ->getParams();
                $restful = app('es')->search($params);
                $data = $staffService->getDataByEs($restful);
                $ysqm = !empty($data[0]) ? $data[0][0]['name'].'（无）' : '（无）';

                $basis[] = ['门诊医生签名：'.$ysqm];
            }
        }

        if ($basis) {
            $this->insertData[] = [
                'BLBH' => $omrInfo['BLBH'],
                'JZXH' => $omrInfo['JZXH'],
                'BRID' => $omrInfo['BRID'],
                'rule_id' => $ruleId,
                'code' => 'omr_rule_'.$ruleId,
                'error_field' => $omrRuleData[$ruleId]['title'],
                'basis' => json_encode($basis,JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 整体（复诊）
     * @param $omrRuleData
     * @param $omrInfo
     * @return true
     */
    public function rule3($omrRuleData,$ruleId,$omrInfo)
    {
        // 判断是不是复诊病历
        if ($omrInfo['bl_type'] != '复诊') {
            return true;
        }
        $array = explode('滨州医学院烟台附属医院',$omrInfo['BLNR_TXT']);
        if (empty($array[1])) {
            return true;
        }
        if (stripos($array[1],'复诊') === false) {
            return true;
        }

        $omrBlsyService = new ElasticsearchService('omr_blsy_2023');
        $staffService = new ElasticsearchService('staff_2023');

        $zkFiidle = [
            'zs' => '主诉',
            'xbs' => '现病史',
            'tgjc' => '体格检查',
            'fzjc' => '辅助检查',
            'cbzd' => '初步诊断',
            'zlyj' => '诊疗意见'
        ];

        $basis = [];
        foreach ($zkFiidle as $key => $value) {
            if (empty($omrInfo[$key])) {
                $basis[] = ['缺少：'.$zkFiidle[$key]];
            }
        }

        // 门诊医生签名
        if (!empty($omrInfo['SXYS'])) {
            $must = [
                ['term' => ['BLBH'=>$omrInfo['BLBH']]],
                ['term' => ['SYYS'=>$omrInfo['SXYS']]]
            ];
            $params = $omrBlsyService->clearMust()
                ->queryByMustBatch($must)
                ->getParams();
            $restful = app('es')->search($params);
            $data = $omrBlsyService->getDataByEs($restful);
            if (empty($data[0])) {
                $must = [
                    ['term' => ['code'=>$omrInfo['SXYS']]]
                ];
                $params = $staffService->clearMust()
                    ->queryByMustBatch($must)
                    ->getParams();
                $restful = app('es')->search($params);
                $data = $staffService->getDataByEs($restful);
                $ysqm = !empty($data[0]) ? $data[0][0]['name'].'（无）' : '（无）';

                $basis[] = ['门诊医生签名：'.$ysqm];
            }
        }

        if ($basis) {
            $this->insertData[] = [
                'BLBH' => $omrInfo['BLBH'],
                'JZXH' => $omrInfo['JZXH'],
                'BRID' => $omrInfo['BRID'],
                'rule_id' => $ruleId,
                'code' => 'omr_rule_'.$ruleId,
                'error_field' => $omrRuleData[$ruleId]['title'],
                'basis' => json_encode($basis,JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 整体（有 {}）
     * @param $omrRuleData
     * @param $omrInfo
     * @return true
     */
    public function rule5($omrRuleData,$ruleId,$omrInfo)
    {
        $field = ['zs','xbs','jws','tgjc','fzjc','cbzd','zlyj','tx','mzh','xm','xb','jzsj','ks','nl1','xy'];
        $basis = [];
        foreach ($field as $value) {
            $result = [];
            preg_match_all("/(?:\{)(.*)(?:\})/i", $omrInfo[$value], $result);
            if ($result[0]) {
                $basis[] = [OMR_BL01::FIELD_LIST[$value].'：'.implode('，',$result[0])];
            }
        }
        if (stripos($omrInfo['lxbxs'],"{有无}") != false && !in_array("{有无}",$basis) ) {
            $basis[] = ['流行病学史：{有无}'];
        }

        if ($basis) {
            $this->insertData[] = [
                'BLBH' => $omrInfo['BLBH'],
                'JZXH' => $omrInfo['JZXH'],
                'BRID' => $omrInfo['BRID'],
                'rule_id' => $ruleId,
                'code' => 'omr_rule_'.$ruleId,
                'error_field' => $omrRuleData[$ruleId]['title'],
                'basis' => json_encode($basis,JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 整体（复诊）重复率
     * @param $omrRuleData
     * @param $omrInfo
     * @return true
     */
    public function rule6($omrRuleData,$ruleId,$omrInfo)
    {
        // 判断是不是复诊病历
        if ($omrInfo['bl_type'] != '复诊') {
            return true;
        }
        $array = explode('滨州医学院烟台附属医院',$omrInfo['BLNR_TXT']);
        if (empty($array[1])) {
            return true;
        }
        if (stripos($array[1],'复诊') === false) {
            return true;
        }

        // 查询初诊
        $omrBl01Service = new ElasticsearchService('omr_bl01_2023');
        $must = [
            ['term' => ['BRID' => $omrInfo['BRID']]],
            ['term' => ['bl_type' => '初诊']],
            ['range' => ['CJSJ' => ['lt' => $omrInfo['CJSJ']]]]
        ];
        $params = $omrBl01Service->clearMust()
            ->queryByMustBatch($must)
            ->orderBy('CJSJ','desc')
            ->getParams();
        $restful = app('es')->search($params);
        $data = $omrBl01Service->getDataByEs($restful);
        $czInfo = !empty($data[0]) ? $data[0][0] : [];
        if (empty($czInfo)) {
            return true;
        }

        $publicService = new PublicService();
        $basis = [];
        // 判断 现病史 的重复率，是否大于等于 80%
        if ($omrInfo['xbs'] && $czInfo['xbs']) {
            $xbsCfl = $publicService->getSimilar($omrInfo['xbs'],$czInfo['xbs']);
            $xbsCfl = (int)round($xbsCfl*100);
            if ($xbsCfl >= 80) {
                $basis[] = ['初诊和复诊【现病史80%雷同】',$czInfo['BLBH']];
            }
        }

        // 判断 诊疗意见 的重复率，是否大于等于 80%
        if ($omrInfo['zlyj'] && $czInfo['zlyj']) {
            $zlyjCfl = $publicService->getSimilar($omrInfo['zlyj'],$czInfo['zlyj']);
            $zlyjCfl = (int)round($zlyjCfl*100);
            if ($zlyjCfl >= 80) {
                $basis[] = ['初诊和复诊【诊疗意见80%雷同】',$czInfo['BLBH']];
            }
        }

        if ($basis) {
            $this->insertData[] = [
                'BLBH' => $omrInfo['BLBH'],
                'JZXH' => $omrInfo['JZXH'],
                'BRID' => $omrInfo['BRID'],
                'rule_id' => $ruleId,
                'code' => 'omr_rule_'.$ruleId,
                'error_field' => $omrRuleData[$ruleId]['title'],
                'basis' => json_encode($basis,JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 主诉（不超过20个字）
     * @param $omrRuleData
     * @param $omrInfo
     * @return true
     */
    public function rule7($omrRuleData,$ruleId,$omrInfo)
    {
        $zs = trim($omrInfo['zs']);
        if (!empty($zs)) {
            $arr = [' ','、','。','.','-','{','}'];
            foreach ($arr as $value) {
                $zs = str_replace($value,'',$zs);
            }
        }

        $zs = str_replace('。','',$zs);
        $zs = str_replace('.','',$zs);
        $zs = str_replace('-','',$zs);
        $zs = str_replace('{','',$zs);
        $zs = str_replace('}','',$zs);
        $len = mb_strlen($zs);

        if ($len > 20) {
            $this->insertData[] = [
                'BLBH' => $omrInfo['BLBH'],
                'JZXH' => $omrInfo['JZXH'],
                'BRID' => $omrInfo['BRID'],
                'rule_id' => $ruleId,
                'code' => 'omr_rule_'.$ruleId,
                'error_field' => $omrRuleData[$ruleId]['title'],
                'basis' => json_encode([['主诉超过20个字']],JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 诊疗意见（药品名称）
     * @param $omrRuleData
     * @param $omrInfo
     * @return true
     */
    public function rule8($omrRuleData,$ruleId,$omrInfo)
    {
        $omrMc1Service = new ElasticsearchService('omr_mc1_2023');
        $omrMc2Service = new ElasticsearchService('omr_mc2_2023');
        $omrYtService = new ElasticsearchService('omr_yt_2023');

        $ypmcList = [];

        $must = ['term' => ['JZXH' => $omrInfo['JZXH']]];
        $params = $omrMc1Service->clearMust()
            ->queryByMust($must)
            ->paginate(1,100)
            ->getParams();
        $restful = app('es')->search($params);
        $mc1data = $omrMc1Service->getDataByEs($restful);
        if (!empty($mc1data[0])) {
            $mc2Should = [];
            foreach ($mc1data[0] as $val) {
                if (!in_array($val['CFSB'],$mc2Should)) {
                    $mc2Should[] = ['term' => ['CFSB' => $val['CFSB']]];
                }
            }

            $params = $omrMc2Service->clearMust()
                ->queryByShouldBatch($mc2Should)
                ->minimumShouldMatch()
                ->paginate(1,100)
                ->getParams();
            $restful = app('es')->search($params);
            $mc2data = $omrMc2Service->getDataByEs($restful);
            if (!empty($mc2data[0])) {
                $ytShould = [];
                foreach ($mc2data[0] as $val) {
                    if (!in_array($val['YPXH'],$ytShould)) {
                        $ytShould[] = ['term' => ['YPXH' => $val['YPXH']]];
                    }
                }

                $params = $omrYtService->clearMust()
                    ->queryByShouldBatch($ytShould)
                    ->minimumShouldMatch()
                    ->paginate(1,100)
                    ->getParams();
                $restful = app('es')->search($params);
                $ytdata = $omrYtService->getDataByEs($restful);
                if (!empty($ytdata[0])) {
                    foreach ($ytdata[0] as $val) {
                        if (!in_array($val['YPMC'],$ypmcList)) {
                            $ypmcList[] = $val['YPMC'];
                        }
                    }
                }
            }
        }

        $basis = [];
        if (!empty($ypmcList)) {
            foreach ($ypmcList as $ym) {
                // 获取 ”［“ 第一次出现的位置
                $ym = str_replace('［','[',$ym);
                $stratLen = stripos($ym,'[');
                if ($stratLen) {
                    $ym = substr($ym,0,$stratLen);
                }
                // 获取 ”（“ 第一次出现的位置
                $ym = str_replace('（','(',$ym);
                $stratLen = stripos($ym,'(');
                if ($stratLen) {
                    $ym = substr($ym,0,$stratLen);
                }
                // 获取 ”#“ 第一次出现的位置
                $stratLen = stripos($ym,'#');
                if ($stratLen) {
                    $ym = substr($ym,0,$stratLen);
                }

                // 判断是否在药品库存在，不存在则记录
                if (stripos($omrInfo['xy'],$ym) === false && stripos($omrInfo['zlyj'],$ym) === false) {
                    $basis[] = ['无药品：'.$ym];
                }
            }
        }

        if ($basis) {
            $this->insertData[] = [
                'BLBH' => $omrInfo['BLBH'],
                'JZXH' => $omrInfo['JZXH'],
                'BRID' => $omrInfo['BRID'],
                'rule_id' => $ruleId,
                'code' => 'omr_rule_'.$ruleId,
                'error_field' => $omrRuleData[$ruleId]['title'],
                'basis' => json_encode($basis,JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 收挂号费，无病历 规则处理
     * @param $page
     * @return true
     */
    public function msGhmx($page,$startTime,$endTime)
    {
        $omrRule = OmrRule::query()->get()->toArray();
        $omrRuleData = array_column($omrRule, null, 'id');

        // 规则关闭则不进行质控
        if (empty($omrRuleData[9]['status'])) {
            return true;
        }

        // 查询最后一条无病历的病历病号
        $index = OmrQuality::query()
            ->where('BLBH','>',1000000000)
            ->orderByDesc('BLBH')->value('BLBH');
        $index = !empty($index) ? $index+1 : 1000000001;

        if ($startTime && $endTime) {
            $field = 'JZRQ';
            $startTime .= ' 00:00:00';
            $endTime .= ' 23:59:59';
        } else {
            $field = 'created_at';
            $date = Carbon::parse()->addDay(-1)->toDateString();
            $startTime .= $date.' 00:00:00';
            $endTime .= $date.' 23:59:59';
        }

        while (true) {
            $data = MS_GHMX::query()
                ->where('YSDM','!=',10201)
                ->whereBetween($field, [$startTime, $endTime])
                ->whereNotIn('KSDM',[213,1041,4285])
                ->paginate(500, ['*'], 'page', $page)
                ->toArray();

            if ($page == 1) {
                echo '数据总条数：' . $data['total'] . PHP_EOL . '总页数：' . $data['last_page'] . PHP_EOL;
            } elseif ($page > $data['last_page']) {
                // 如果当前页码大于最大页码则结束
                break;
            }
            echo $page . PHP_EOL;
            $page++;

            $msThmxService = new ElasticsearchService('ms_thmx_2023');
            $omrBl01Service = new ElasticsearchService('omr_bl01_2023');
            $msBrdaService = new ElasticsearchService('ms_brda_2023');

            $xbArr = [0=>'未知的性别',1=>'男',2=>'女',9=>'未说明的性别'];
            $insertData = [];
            foreach ($data['data'] as $value) {
                $must = [
                    ['term' => ['SBXH' => $value['SBXH']]]
                ];
                $params = $msThmxService->clearMust()
                    ->queryByMustBatch($must)
                    ->getParams();
                $restful = app('es')->search($params);
                $thmxData = $msThmxService->getDataByEs($restful);
                if (empty($thmxData[0])) {
                    $JZRQ = date('Y-m-d', strtotime($value['JZRQ']));
                    $must = [
                        ['term' => ['BRID' => $value['BRID']]],
                        ['range' => ['jzsj' => ['gte' => $JZRQ.' 00:00:00','lte' => $JZRQ.' 23:59:59']]],
                    ];
                    $params = $omrBl01Service->clearMust()
                        ->queryByMustBatch($must)
                        ->getParams();
                    $restful = app('es')->search($params);
                    $omrBl01Data = $omrBl01Service->getDataByEs($restful);
                    if (empty($omrBl01Data[0])) {
                        $must = [
                            ['term' => ['BRID' => $value['BRID']]],
                        ];
                        $params = $msBrdaService->clearMust()
                            ->queryByMustBatch($must)
                            ->getParams();
                        $restful = app('es')->search($params);
                        $msBrdaData = $msBrdaService->getDataByEs($restful);
                        $xb = '';
                        if (!empty($msBrdaData[0][0])) {
                            $xb = $xbArr[$msBrdaData[0][0]['BRXB']] ?? '';
                        }
                        $insertData[] = [
                            'BLBH' => $index,
                            'JZXH' => '',
                            'BRID' => $value['BRID'],
                            'mzh' => $msBrdaData[0][0]['MZHM'] ?? '',
                            'xm' => $msBrdaData[0][0]['BRXM'] ?? '',
                            'xb' => $xb,
                            'SFZH' => $msBrdaData[0][0]['SFZH'] ?? '',
                            'BRKS' => $value['KSDM'],
                            'SXYS' => $value['YSDM'],
                            'jzsj' => $value['JZRQ'],
                            'rule_id' => 9,
                            'code' => 'omr_rule_9',
                            'error_field' => $omrRuleData[9]['title'],
                            'basis' => json_encode([['挂号费日期【'.$JZRQ.'】，无门（急）诊病历']],JSON_UNESCAPED_UNICODE)
                        ];
                        $index++;
                    } else {
                        $isOk = 0;
                        foreach ($omrBl01Data[0] as $omrInfo) {
                            $cjsj = date('Y-m-d', strtotime($omrInfo['CJSJ']));
                            if ($cjsj == $JZRQ) {
                                $isOk = 1;
                                break;
                            }
                        }

                        if ($isOk) {
                            $insertData[] = [
                                'BLBH' => $omrBl01Data[0][0]['BLBH'],
                                'JZXH' => $omrBl01Data[0][0]['JZXH'],
                                'BRID' => $omrBl01Data[0][0]['BRID'],
                                'mzh' => $omrBl01Data[0][0]['mzh'] ?? '',
                                'xm' => $omrBl01Data[0][0]['xm'] ?? '',
                                'xb' => $omrBl01Data[0][0]['xb'] ?? '',
                                'SFZH' => $omrBl01Data[0][0]['SFZH'] ?? '',
                                'BRKS' => $omrBl01Data[0][0]['BRKS'],
                                'SXYS' => $omrBl01Data[0][0]['SXYS'],
                                'jzsj' => $omrBl01Data[0][0]['jzsj'],
                                'rule_id' => 0,
                                'code' => '0',
                                'error_field' => '',
                                'basis' => ''
                            ];
                        } else {
                            $where = ['BLBH'=>$omrBl01Data[0][0]['BLBH'], 'rule_id'=>9];
                            $id = OmrQuality::query()->where($where)->value('id');
                            if (empty($id)) {
                                $insertData[] = [
                                    'BLBH' => $omrBl01Data[0][0]['BLBH'],
                                    'JZXH' => $omrBl01Data[0][0]['JZXH'],
                                    'BRID' => $omrBl01Data[0][0]['BRID'],
                                    'mzh' => $omrBl01Data[0][0]['mzh'] ?? '',
                                    'xm' => $omrBl01Data[0][0]['xm'] ?? '',
                                    'xb' => $omrBl01Data[0][0]['xb'] ?? '',
                                    'SFZH' => $omrBl01Data[0][0]['SFZH'] ?? '',
                                    'BRKS' => $omrBl01Data[0][0]['BRKS'],
                                    'SXYS' => $omrBl01Data[0][0]['SXYS'],
                                    'jzsj' => $omrBl01Data[0][0]['jzsj'],
                                    'rule_id' => 9,
                                    'code' => 'omr_rule_9',
                                    'error_field' => $omrRuleData[9]['title'],
                                    'basis' => json_encode([['挂号费日期【'.$JZRQ.'】，门（急）诊病历【'.$cjsj.'，不在当天】']],JSON_UNESCAPED_UNICODE)
                                ];
                            }
                        }
                    }
                }
            }

            if ($insertData) {
                OmrQuality::query()->insert($insertData);
            }
        }

        return true;
    }

}
