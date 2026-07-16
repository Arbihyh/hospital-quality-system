<?php

namespace App\Services;

use App\Model\Department;
use App\Model\ICD09;
use App\Model\ICD10;
use App\Model\RuleWordMap;
use App\Model\Staff;
use App\Model\Yzb;
use App\Model\ZY_ZLXZ;
use Illuminate\Support\Facades\Log;

class MedicalHistoryService
{
    // 主信息字段
    public static $patientKey = [
        'AAD01C',
        'AAB02C',
        'AAC02C',
        'AAA28',
        'AAA01',
        'AAA02C',
        'AAC11N',
        'AAA08C',
        'AAB01',
        'AAC04',
        'ADA01',
        'ADA0101',
        'AAA29',
        'AAA40',
        'AAA04',
        'AEM01C',
        'AAB06C',
        'AAA26C',
        'AEE08',
        'RYJL_ZHS',
        'RYJL_XBS',
        'RYJL_JWS',
        'RYJL_GRS',
        'RYJL_YJJHYS',
        'RYJL_JZS',
        'RYJL_TGJC',
        'RYJL_ZHUANKE',
        'RYJL_FZJC',
        'RYJL_CBZD',
        'RYJL_CBZB_FIRST',
        'RYJL_HJNR',
        'BCJL_SCBC_BLTD',
        'BCJL_SCBC_CBZD',
        'BCJL_SCBC_CBZD_ONE',
        'BCJL_SCBC_CBZD_OTHER',
        'BCJL_SCBC_ZDYJ',
        'BCJL_SCBC_JBZD',
        'BCJL_SCBC_JBZDMC',
        'BCJL_SCBC_ZLJH',
        'BCJL_SCBC_HJNR',
        'CYJL_HJNR',
        'CYJL_RYQK',
        'CYJL_CBZD',
        'CYJL_CBZD_FIRST',
        'CYJL_ZLJG',
        'CYJL_CYQK',
        'CYJL_CYZD',
        'CYJL_CYZD_FIRST',
        'CYJL_CYYZ',
        'MD_ICD10_NAME',
        'MD_ICD10_ID1',
        'MD_RYQK',
        'MO_ICD9_NAME',
        'MO_ICD9_ID1',
        'MO_OPE_DATE',
        'MO_OPE_LEVEL',
        'MO_SSPB',
        'MO_OPE_MAN_NAME',
        'MO_FRIST_ASSISTANT_NAME',
        'MO_SECOND_ASSISTANT_NAME',
        'MO_HOCUS_WAY_ID',
        'MO_HOCUS_MAN_NAME'
    ];

    // 手术记录
    public static $bllb303 = ['SSJL_HJNR', 'SSJL_SQZD', 'SSJL_SZZD', 'SSJL_SZZD_ONE', 'SSJL_SSMC', 'SSJL_SSMC_ONE', 'SSJL_SSZD', 'SSJL_SSZ', 'SSJL_ZS', 'SSJL_CH', 'SSJL_SSRQ',];

    // 其他诊断搜索字段
    public static $otherDiagnosisKey = ['OD_ICD10_NAME', 'OD_ICD10_ID1'];
    public static $yzbKey = ['YZQX', 'YZMC', 'KZYS', 'KZKS', 'JJYZ', 'KZSJ', 'YDYZLB'];
    // 其他手术搜索字段
    public static $secondaryOperationKey = ['SO_ICD9_NAME', 'SO_ICD9_ID1'];

    // 输血病程记录
    public static $bllb294_45 = ['BCJL_SXBC_HJNR', 'BCJL_SXBC_TIWEN', 'BCJL_SXBC_JCZB', 'BCJL_SXBC_JCJG', 'BCJL_SXBC_SXKSSJ', 'BCJL_SXBC_SXJSSJ', 'BCJL_SXBC_SZZ', 'BCJL_SXBC_HDZ'];

    public static $feeKey = ['FYMC', 'FYSL', 'FYDJ', 'ZJE'];
    // 返回数据分组
    public static $returnGroupKey = [
        'patient_info' => ['AAA28', 'AAC11N', 'AAC01', 'AAA01', 'AAA02C', 'AAB01', 'AAC04', 'AAA04', 'AAA40'],
        'ryjl' => ['RYJL_HJNR', 'RYJL_ZHS', 'RYJL_XBS', 'RYJL_JWS', 'RYJL_GRS', 'RYJL_YJJHYS', 'RYJL_JZS', 'RYJL_TGJC', 'RYJL_ZHUANKE', 'RYJL_FZJC', 'RYJL_CBZD', 'RYJL_CBZB_FIRST'],
        'bcjl_scbc' => ['BCJL_SCBC_HJNR', 'BCJL_SCBC_BLTD', 'BCJL_SCBC_CBZD_ONE', 'BCJL_SCBC_CBZD_OTHER', 'BCJL_SCBC_ZDYJ', 'BCJL_SCBC_JBZD', 'BCJL_SCBC_JBZDMC', 'BCJL_SCBC_ZLJH'],
        'cyjl' => ['CYJL_HJNR', 'CYJL_RYQK', 'CYJL_CBZD', 'CYJL_CBZD_FIRST', 'CYJL_ZLJG', 'CYJL_CYQK', 'CYJL_CYZD', 'CYJL_CYZD_FIRST', 'CYJL_CYYZ'],
        'ssjl' => ['SSJL_HJNR', 'SSJL_SQZD', 'SSJL_SZZD', 'SSJL_SZZD_ONE', 'SSJL_SSMC', 'SSJL_SSMC_ONE', 'SSJL_SSZD', 'SSJL_SSZ', 'SSJL_ZS', 'SSJL_CH', 'SSJL_SSRQ'],
        'zyzd' => ['MD_ICD10_NAME', 'MD_ICD10_ID1'],
        'zyss' => ['MO_ICD9_NAME', 'MO_ICD9_ID1'],
        'bcjl_sxbc' => ['BCJL_SXBC_HJNR', 'BCJL_SXBC_TIWEN', 'BCJL_SXBC_JCZB', 'BCJL_SXBC_JCJG', 'BCJL_SXBC_SXKSSJ', 'BCJL_SXBC_SXJSSJ', 'BCJL_SXBC_SZZ', 'BCJL_SXBC_HDZ'],
    ];

    /**
     * @param int $id
     * @return array
     *
     * 根据ID获取字典映射的值
     */
    public function getRuleWordMap($id = 0)
    {
        $map = RuleWordMap::query()->where('id', $id)->first();
        if (!$map) {
            return [];
        }
        $keyWordMapping = explode(',', $map->keyword);
        $res = [];
        foreach ($keyWordMapping as $v) {
            $tmp = explode('.', $v);
            $res[] = ["name" => $tmp[0], "value" => $tmp[1]];
        }

        return $res ?: [];
    }

    // 在 MedicalHistoryService 类中添加以下批量查询方法

    /**
     * 批量查询医嘱本
     * @param array $field
     * @param array $zyhList
     * @return array
     */
    public function yzbSearchBatch($field = [], $zyhList = [])
    {
        if (empty($field) || empty($zyhList)) {
            return [];
        }

        $mhService = new MedicalHistoryService();
        $blRes = $mhService->getBlSerachSelectData();
        $selectType = array_column($blRes['bl'], null, 'key');
        $yzbFiled = isset($selectType['YZB']) ? array_column($selectType['YZB']['children'], 'key') : [];

        return $this->searchPublicBatch($field, $zyhList, $yzbFiled, 'yzb_2023', 'yzb');
    }

    /**
     * 批量查询费用明细
     * @param array $field
     * @param array $zyhList
     * @return array
     */
    public function feeSearchBatch($field = [], $zyhList = [])
    {
        if (empty($field) || empty($zyhList)) {
            return [];
        }

        $mhService = new MedicalHistoryService();
        $blRes = $mhService->getBlSerachSelectData();
        $selectType = array_column($blRes['bl'], null, 'key');
        $yzbFiled = isset($selectType['FYMX']) ? array_column($selectType['FYMX']['children'], 'key') : [];

        return $this->searchPublicBatch($field, $zyhList, $yzbFiled, 'fee_detailed', 'fymx');
    }

    /**
     * 批量查询手术申请
     * @param array $field
     * @param array $zyhList
     * @return array
     */
    public function sssqSearchBatch($field = [], $zyhList = [])
    {
        if (empty($field) || empty($zyhList)) {
            return [];
        }

        $mhService = new MedicalHistoryService();
        $blRes = $mhService->getBlSerachSelectData();
        $selectType = array_column($blRes['bl'], null, 'key');
        $JYBG = isset($selectType['SSJL']) ? array_column($selectType['SSJL']['children'], null, 'key') : [];
        $yzbFiled = isset($JYBG['SSSQ']) ? array_column($JYBG['SSSQ']['children'], 'key') : [];

        return $this->searchPublicBatch($field, $zyhList, $yzbFiled, 'sssq_2023', 'sssq');
    }

    /**
     * 批量查询检验
     * @param array $field
     * @param array $zyhList
     * @return array
     */
    public function jySearchBatch($field = [], $zyhList = [])
    {
        if (empty($field) || empty($zyhList)) {
            return [];
        }

        $mhService = new MedicalHistoryService();
        $blRes = $mhService->getBlSerachSelectData();
        $selectType = array_column($blRes['bl'], null, 'key');
        $JYBG = isset($selectType['JYBG']) ? array_column($selectType['JYBG']['children'], null, 'key') : [];
        $yzbFiled = isset($JYBG['JY']) ? array_column($JYBG['JY']['children'], 'key') : [];

        return $this->searchPublicBatch($field, $zyhList, $yzbFiled, 'v_jmgs_testresult_2023', 'test_result');
    }

    /**
     * 批量查询药敏
     * @param array $field
     * @param array $zyhList
     * @return array
     */
    public function ymSearchBatch($field = [], $zyhList = [])
    {
        if (empty($field) || empty($zyhList)) {
            return [];
        }

        $mhService = new MedicalHistoryService();
        $blRes = $mhService->getBlSerachSelectData();
        $selectType = array_column($blRes['bl'], null, 'key');
        $JYBG = isset($selectType['JYBG']) ? array_column($selectType['JYBG']['children'], null, 'key') : [];
        $yzbFiled = isset($JYBG['YM']) ? array_column($JYBG['YM']['children'], 'key') : [];

        return $this->searchPublicBatch($field, $zyhList, $yzbFiled, 'v_jmgs_ymresult_2023', 'ym_result');
    }

    /**
     * 批量查询检查(PACS)
     * @param array $field
     * @param array $zyhList
     * @return array
     */
    public function jcSearchBatch($field = [], $zyhList = [])
    {
        if (empty($field) || empty($zyhList)) {
            return [];
        }

        $mhService = new MedicalHistoryService();
        $blRes = $mhService->getBlSerachSelectData();
        $selectType = array_column($blRes['bl'], null, 'key');
        $yzbFiled = isset($selectType['JCBG']) ? array_column($selectType['JCBG']['children'], 'key') : [];

        return $this->searchPublicBatch($field, $zyhList, $yzbFiled, 'pacs', 'jc_result');
    }

    /**
     * 批量搜索的公共方法
     * @param array $field
     * @param array $zyhList
     * @param array $searchFile
     * @param string $index
     * @param string $alias
     * @return array
     */
    private function searchPublicBatch($field = [], $zyhList = [], $searchFile = [], $index = 'v_jmgs_yzb_2023', $alias = 'yzb')
    {
        $must = [];
        $should = [];
        $mustNot = [];

        // 构建查询条件
        foreach ($field as $item) {
            if (empty($item['value'])) {
                continue;
            }

            if (!in_array($item['key'], $searchFile)) {
                continue;
            }

            if ($item['select_type'] === 1) {
                $should[] = ['match_phrase' => [$item['key'] => $item['value']]];
            } elseif ($item['select_type'] === 2) {
                $mustNot[] = ['match_phrase' => [$item['key'] => $item['value']]];
            } else {
                if($item['key'] == 'KZSJ'){
                    $time = explode(',', $item['value']);
                    $must[] = ['range' => [$item['key'] => ['gte' => date('Y-m-d H:i:s', $time[0]/1000), 'lte' => date('Y-m-d H:i:s', $time[1]/1000)]]];
                }else{
                    $must[] = ['match_phrase' => [$item['key'] => $item['value']]];
                }
            }
        }

        // 判断本次搜索是否包含指定的字段
        $isSearch = array_intersect(array_column($field, 'key'), $searchFile);
        if (empty($isSearch)) {
            return array_fill_keys($zyhList, []);
        }

        if (empty($should) && empty($mustNot) && empty($must)) {
            return array_fill_keys($zyhList, []);
        }

        // 特殊处理 PACS - 需要先查询医嘱本获取 SQDH
        if ($index == 'pacs') {
            $sqdhMap = [];
            $yzbs = \App\Model\Yzb::query()->whereIn("ZYH", $zyhList)->get(['ZYH', 'SQDH'])->toArray();
            foreach ($yzbs as $yzb) {
                if (!isset($sqdhMap[$yzb['ZYH']])) {
                    $sqdhMap[$yzb['ZYH']] = [];
                }
                $sqdhMap[$yzb['ZYH']][] = $yzb['SQDH'];
            }

            // 按 ZYH 分批查询
            $result = [];
            foreach ($zyhList as $zyh) {
                if (empty($sqdhMap[$zyh])) {
                    $result[$zyh] = [];
                    continue;
                }

                $tempMust = $must;
                $tempMust[] = ['terms' => ['SQDH' => $sqdhMap[$zyh]]];

                $yzbService = new ElasticsearchService($index);
                $params = $yzbService->clearMust()
                    ->queryByMustBatch($tempMust)
                    ->queryByMustNotBatch($mustNot)
                    ->queryByShouldBatch($should)
                    ->size(1000)
                    ->getParams();

                $restful = app('es')->search($params);
                $blData = $yzbService->getDataByEs($restful);
                $result[$zyh] = $blData[0] ?? [];
            }

            return $result;
        }

        // 使用 terms 查询批量获取所有 ZYH 的数据
        $zyhField = ($index == 'fee_detailed') ? 'MED_REC_ID' : 'ZYH';
        $must[] = ['terms' => [$zyhField => $zyhList]];

        $yzbService = new ElasticsearchService($index);
        $params = $yzbService->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNotBatch($mustNot)
            ->queryByShouldBatch($should)
            ->size(10000) // 批量查询需要更大的 size
            ->getParams();

        // 数据查询
        $restful = app('es')->search($params);
        $blData = $yzbService->getDataByEs($restful);
        $allData = $blData[0] ?? [];

        // 按 ZYH 分组
        $result = array_fill_keys($zyhList, []);
        foreach ($allData as $item) {
            $itemZyh = $item[$zyhField] ?? null;
            if ($itemZyh && isset($result[$itemZyh])) {
                $result[$itemZyh][] = $item;
            }
        }

        return $result;
    }

    /**
     * 获取医生搜索 搜索条件数据
     * @return array
     */
    public function getBlSerachSelectData()
    {
        // 医师
        $doctor = Staff::query()->get(['code as name', 'name as value'])->toArray();
        $department = Department::query()->get(['dep_id as name', 'dep_name as value'])->toArray();
        $ZY_ZLXZ = ZY_ZLXZ::query()->get(['XZXH as name', 'XZMC as value'])->toArray();
        $ICD10 = ICD10::query()->get(['ZDBM', "ZDMC"])->toArray();
        $ICD09 = ICD09::query()->get(['SSCZBM', "SSCZMC"])->toArray();
        // 患者病历信息
        $blSelectList = [
            [
                'key' => 'BCJL',
                'name' => '病程类',
                'children' => [
                    ['key' => 'BCJL_QW', 'name' => '全部病程', 'type' => 'input', 'value' => ''],
                    ['key' => 'CYJL_QW', 'name' => '出院记录', 'type' => 'input', 'value' => ''],
                    ['key' => 'RYJL_QW', 'name' => '入院记录', 'type' => 'input', 'value' => ''],
                    ['key' => 'SWJL_QW', 'name' => '死亡记录', 'type' => 'input', 'value' => ''],
                    ['key' => '24XSCRYJL_QW', 'name' => '24小时出入院记录', 'type' => 'input', 'value' => ''],
                    //                    [
                    //                        'key' => 'BCJL_SXBC',
                    //                        'name' => '输血病程记录',
                    //                        'children' => [
                    //                            ['key' => 'BCJL_SXBC_HJNR', 'name' => '输血记录全文', 'type' => 'input', 'value' => ''],
                    //                            //                            ['key'=>'BCJL_SXBC_TIWEN','name'=>'体温','type'=>'input','value'=>''],
                    //                            //                            ['key' => 'BCJL_SXBC_JCZB', 'name' => '检查指标', 'type' => 'input', 'value' => ''],
                    //                            //                            ['key' => 'BCJL_SXBC_JCJG', 'name' => '检测结构', 'type' => 'input', 'value' => ''],
                    //                            //                            ['key'=>'BCJL_SXBC_SXKSSJ','name'=>'输血开始时间','type'=>'input','value'=>''],
                    //                            //                            ['key'=>'BCJL_SXBC_SXJSSJ','name'=>'输血结束时间','type'=>'input','value'=>''],
                    //                            //                            ['key' => 'BCJL_SXBC_SZZ', 'name' => '输注者', 'type' => 'input', 'value' => ''],
                    //                            //                            ['key' => 'BCJL_SXBC_HDZ', 'name' => '核对者', 'type' => 'input', 'value' => '']
                    //                        ],
                    //                    ],
                ],
            ],
            [
                'key' => 'SSJL',
                'name' => '手术类',
                'children' => [
                    ['key' => 'SSJL_QW', 'name' => '手术记录', 'type' => 'input', 'value' => ''],
                    //                    [
                    //                        'key' => 'SSSQ',
                    //                        'name' => '手术申请',
                    //                        'children' => [
                    //                            //                            ['key' => 'SSNM', 'name' => '手术内码', 'type' => 'input', 'value' => ''],
                    //                            ['key' => 'SSMC', 'name' => '手术名称', 'type' => 'input', 'value' => ''],
                    //                            //                            ['key' => 'SSDM', 'name' => '手术代码', 'type' => 'input', 'value' => ''],
                    //                            ['key' => 'KSSJ', 'name' => '手术开始时间', 'type' => 'time', 'value' => ''],
                    //                            ['key' => 'JSSJ', 'name' => '手术结束时间', 'type' => 'time', 'value' => ''],
                    //                        ]
                    //                    ]
                ],
            ],
            [
                'key' => "BASY",
                'name' => "病案首页",
                'children' => [
                    [
                        'key' => 'home',
                        'name' => '首页',
                        'children' => [
                            //                            ['key' => 'ZYH', 'name' => '唯一标识', 'type' => 'input', 'value' => ''],
                            ['key' => 'AAA28', 'name' => '住院号码', 'type' => 'input', 'value' => ''],
                            ['key' => 'AAA29', 'name' => '住院次数', 'type' => 'range', 'value' => ''],
                            ['key' => 'AAA01', 'name' => '患者姓名', 'type' => 'input', 'value' => ''],
                            ['key' => 'AAA02C', 'name' => '患者性别', 'type' => 'select', 'value' => $this->getRuleWordMap(121)],
                            ['key' => 'AAA04', 'name' => '患者年龄', 'type' => 'range', 'value' => ''],
                            ['key' => 'AAA40', 'name' => '患者天龄', 'type' => 'range', 'value' => ''],
                            ['key' => 'AAA08C', 'name' => '婚姻状况', 'type' => 'select', 'value' => $this->getRuleWordMap(122)],
                            ['key' => 'AEM01C', 'name' => '离院方式', 'type' => 'select', 'value' => $this->getRuleWordMap(123)],
                            //                            ['key' => 'AAB01', 'name' => '入院时间', 'type' => 'time', 'value' => ''],
                            ['key' => 'AAC04', 'name' => '住院天数', 'type' => 'range', 'value' => ''],
                            ['key' => 'AAD01C', 'name' => '转科科室', 'type' => 'select', 'value' => $department],
                            ['key' => 'AAB02C', 'name' => '入院科室', 'type' => 'select', 'value' => $department],
                            ['key' => 'AAC02C', 'name' => '出院科室', 'type' => 'select', 'value' => $department],
                            ['key' => 'AAB06C', 'name' => '入院途径', 'type' => 'select', 'value' => $this->getRuleWordMap(124)],
                            ['key' => 'AAA26C', 'name' => '医疗付费方式', 'type' => 'select', 'value' => $this->getRuleWordMap(125)],
                            ['key' => 'ADA01', 'name' => '住院总费用', 'type' => 'range', 'value' => ""],
                            ['key' => 'ADA0101', 'name' => '自付费用', 'type' => 'range', 'value' => ""],
                            ['key' => 'AEE08', 'name' => '编码员', 'type' => 'input', 'value' => ''],
                        ],
                    ],
                    [
                        'key' => 'main_diagnosis',
                        'name' => '主诊断',
                        'children' => [
                            ['key' => 'MD_ICD10_ID1', 'name' => '主要诊断编码', 'type' => 'input', 'value' => ''],
                            ['key' => 'MD_ICD10_NAME', 'name' => '主要诊断名称', 'type' => 'input', 'value' => ''],
                            ['key' => 'MD_RYQK', 'name' => '入院病情', 'type' => 'select', 'value' => $this->getRuleWordMap(126)],
                        ],
                    ],
                    [
                        'key' => 'other_diagnosis',
                        'name' => '其他诊断',
                        'children' => [
                            ['key' => 'OD_ICD10_ID1', 'name' => '其他诊断编码', 'type' => 'input', 'value' => ''],
                            ['key' => 'OD_ICD10_NAME', 'name' => '其他诊断名称', 'type' => 'input', 'value' => ''],
                            ['key' => 'RYQK', 'name' => '入院病情（其他诊断）', 'type' => 'select', 'value' => $this->getRuleWordMap(126)],
                        ],
                    ],
                    [
                        'key' => 'main_operation',
                        'name' => '主手术',
                        'children' => [
                            ['key' => 'MO_ICD9_ID1', 'name' => '主手术编码', 'type' => 'input', 'value' => ''],
                            ['key' => 'MO_ICD9_NAME', 'name' => '主手术名称', 'type' => 'input', 'value' => ''],
                            ['key' => 'MO_OPE_DATE', 'name' => '主手术操作日期', 'type' => 'time', 'value' => ""],
                            ['key' => 'MO_OPE_LEVEL', 'name' => '手术级别', 'type' => 'select', 'value' => $this->getRuleWordMap(127)],
                            ['key' => 'MO_SSPB', 'name' => '手术判别', 'type' => 'select', 'value' => $this->getRuleWordMap(128)],
                            ['key' => 'MO_OPE_MAN_NAME', 'name' => '术者', 'type' => 'select', 'value' => $doctor],
                            ['key' => 'MO_FRIST_ASSISTANT_NAME', 'name' => '一助', 'type' => 'select', 'value' => $doctor],
                            ['key' => 'MO_SECOND_ASSISTANT_NAME', 'name' => '二助', 'type' => 'select', 'value' => $doctor],
                            ['key' => 'MO_HOCUS_WAY_ID', 'name' => '麻醉方式', 'type' => 'select', 'value' => $this->getRuleWordMap(129)],
                            //                            ['key' => 'QKDJ', 'name' => '切口等级', 'type' => 'select', 'value' => $this->getRuleWordMap(130)],
                            //                            ['key' => 'YHDJ', 'name' => '愈合等级', 'type' => 'select', 'value' => $this->getRuleWordMap(131)],
                            ['key' => 'MO_HOCUS_MAN_NAME', 'name' => '麻醉医师', 'type' => 'select', 'value' => $doctor],
                        ],
                    ],
                    [
                        'key' => 'main_operation',
                        'name' => '其他手术',
                        'children' => [
                            ['key' => 'ICD9_ID1', 'name' => '其他手术编码', 'type' => 'input', 'value' => ''],
                            ['key' => 'ICD09_NAME', 'name' => '其他手术名称', 'type' => 'input', 'value' => ''],
                            ['key' => 'OPE_DATE', 'name' => '其他手术操作日期', 'type' => 'time', 'value' => ""],
                            ['key' => 'OPE_LEVEL', 'name' => '手术级别', 'type' => 'select', 'value' => $this->getRuleWordMap(127)],
                            ['key' => 'SSPB', 'name' => '手术判别', 'type' => 'select', 'value' => $this->getRuleWordMap(128)],
                            ['key' => 'OPE_MAN_NAME', 'name' => '术者', 'type' => 'select', 'value' => $doctor],
                            ['key' => 'FRIST_ASSISTANT_NAME', 'name' => '一助', 'type' => 'select', 'value' => $doctor],
                            ['key' => 'SECOND_ASSISTANT_NAME', 'name' => '二助', 'type' => 'select', 'value' => $doctor],
                            ['key' => 'HOCUS_WAY_ID', 'name' => '麻醉方式', 'type' => 'select', 'value' => $this->getRuleWordMap(129)],
                            //                            ['key' => 'QKDJ', 'name' => '切口等级', 'type' => 'select', 'value' => $this->getRuleWordMap(130)],
                            //                            ['key' => 'YHDJ', 'name' => '愈合等级', 'type' => 'select', 'value' => $this->getRuleWordMap(131)],
                            ['key' => 'HOCUS_MAN_NAME', 'name' => '麻醉医师', 'type' => 'select', 'value' => $doctor],
                        ],
                    ]
                ],
                [
                    'key' => 'SSJL',
                    'name' => '手术记录',
                    'children' => [
                        ['key' => 'SSNM', 'name' => '手术内码', 'type' => 'input', 'value' => ''],
                        ['key' => 'SSMC', 'name' => '手术名称', 'type' => 'input', 'value' => ''],
                        ['key' => 'SSDM', 'name' => '手术代码', 'type' => 'input', 'value' => ''],
                        ['key' => 'SSSJ', 'name' => '手术时间', 'type' => 'time', 'value' => ''],
                    ]
                ],
            ],
            [
                'key' => 'RYJL',
                'name' => '入院记录格式化',
                'children' => [
                    ['key' => 'RYJL_HJNR', 'name' => '入院记录全文', 'type' => 'input', 'value' => ''],
                    ['key' => 'RYJL_ZHS', 'name' => '主诉', 'type' => 'input', 'value' => ''],
                    ['key' => 'RYJL_XBS', 'name' => '现病史', 'type' => 'input', 'value' => ''],
                    ['key' => 'RYJL_JWS', 'name' => '既往史', 'type' => 'input', 'value' => ''],
                    ['key' => 'RYJL_GRS', 'name' => '个人史', 'type' => 'input', 'value' => ''],
                    ['key' => 'RYJL_YJJHYS', 'name' => '月经生育史+婚育史', 'type' => 'input', 'value' => ''],
                    ['key' => 'RYJL_JZS', 'name' => '家族史', 'type' => 'input', 'value' => ''],
                    ['key' => 'RYJL_TGJC', 'name' => '体格检查', 'type' => 'input', 'value' => ''],
                    ['key' => 'RYJL_ZHUANKE', 'name' => '专科检查', 'type' => 'input', 'value' => ''],
                    ['key' => 'RYJL_FZJC', 'name' => '辅助检查', 'type' => 'input', 'value' => ''],
                    ['key' => 'RYJL_CBZD', 'name' => '初步诊断', 'type' => 'input', 'value' => ''],
                    ['key' => 'RYJL_CBZB_FIRST', 'name' => '第一初步诊断', 'type' => 'input', 'value' => ''],
                ]
            ],
            [
                'key' => 'CYJL',
                'name' => '出院记录格式化',
                'children' => [
                    ['key' => 'CYJL_HJNR', 'name' => '出院记录全文', 'type' => 'input', 'value' => ''],
                    ['key' => 'CYJL_RYQK', 'name' => '入院情况', 'type' => 'input', 'value' => ''],
                    ['key' => 'CYJL_CBZD', 'name' => '初步诊断', 'type' => 'input', 'value' => ''],
                    ['key' => 'CYJL_CBZD_FIRST', 'name' => '第一初步诊断', 'type' => 'input', 'value' => ''],
                    ['key' => 'CYJL_ZLJG', 'name' => '诊疗经过', 'type' => 'input', 'value' => ''],
                    ['key' => 'CYJL_CYQK', 'name' => '出院情况', 'type' => 'input', 'value' => ''],
                    ['key' => 'CYJL_CYZD', 'name' => '出院诊断', 'type' => 'input', 'value' => ''],
                    ['key' => 'CYJL_CYZD_FIRST', 'name' => '第一出院诊断', 'type' => 'input', 'value' => ''],
                    //                    ['key' => 'CYJL_CYYZ', 'name' => '出院医嘱', 'type' => 'input', 'value' => '']
                ]
            ],
            [
                'key' => 'BCJL_SCBC',
                'name' => '首次病程格式化',
                'children' => [
                    ['key' => 'BCJL_SCBC_HJNR', 'name' => '首次病程全文', 'type' => 'input', 'value' => ''],
                    ['key' => 'BCJL_SCBC_BLTD', 'name' => '病历特点', 'type' => 'input', 'value' => ''],
                    ['key' => 'BCJL_SCBC_CBZD_ONE', 'name' => '第一初步诊断', 'type' => 'input', 'value' => ''],
                    ['key' => 'BCJL_SCBC_CBZD_OTHER', 'name' => '其他初步诊断', 'type' => 'input', 'value' => ''],
                    ['key' => 'BCJL_SCBC_ZDYJ', 'name' => '诊断依据', 'type' => 'input', 'value' => ''],
                    ['key' => 'BCJL_SCBC_JBZD', 'name' => '鉴别诊断', 'type' => 'input', 'value' => ''],
                    ['key' => 'BCJL_SCBC_JBZDMC', 'name' => '鉴别诊断名称', 'type' => 'input', 'value' => ''],
                    ['key' => 'BCJL_SCBC_ZLJH', 'name' => '诊疗记录', 'type' => 'input', 'value' => '']
                ],
            ],
            [
                'key' => 'SSJL_SSJL',
                'name' => '手术记录格式化',
                'children' => [
                    ['key' => 'SSJL_SQZD', 'name' => '术前诊断', 'type' => 'input', 'value' => ''],
                    ['key' => 'SSJL_SZZD', 'name' => '术中诊断', 'type' => 'input', 'value' => ''],
                    ['key' => 'SSJL_SZZD_ONE', 'name' => '第一个术中诊断名称', 'type' => 'input', 'value' => ''],
                    ['key' => 'SSJL_SSMC', 'name' => '手术名称', 'type' => 'input', 'value' => ''],
                    //                            ['key' => 'SSJL_SSMC_ONE', 'name' => '第一个手术名称', 'type' => 'input', 'value' => ''],
                    ['key' => 'SSJL_SSZD', 'name' => '手术指导', 'type' => 'input', 'value' => ''],
                    ['key' => 'SSJL_SSZ', 'name' => '手术者', 'type' => 'input', 'value' => ''],
                    ['key' => 'SSJL_ZS', 'name' => '助手', 'type' => 'input', 'value' => ''],
                    //                            ['key' => 'SSJL_CH', 'name' => '床号', 'type' => 'input', 'value' => ''],
                    //                    ['key'=>'SSJL_SSRQ','name'=>'手术日期','type'=>'input','value'=>'']
                ]
            ],
            ['key' => 'TLJL_QW', 'name' => '讨论记录', 'type' => 'input', 'value' => ''],
            ['key' => 'SQTYS_QW', 'name' => '授权同意书', 'type' => 'input', 'value' => ''],
            [
                'key' => 'YZB',
                'name' => '住院医嘱',
                'children' => [
                    [
                        'key' => 'YZQX',
                        'name' => '医嘱期效',
                        'type' => 'select',
                        'value' => [
                            ['name' => 1, 'value' => '长期医嘱'],
                            ['name' => 2, 'value' => '临时医嘱'],
                        ]
                    ],
                    ['key' => 'YZMC', 'name' => '医嘱名称', 'type' => 'input', 'value' => ''],
                    //['key' => 'KZYS', 'name' => '开嘱医生', 'type' => 'select', 'value' => $doctor],
                    //['key' => 'KZKS', 'name' => '开嘱科室', 'type' => 'select', 'value' => $department],
                    ['key' => 'JJYZ', 'name' => '紧急医嘱', 'type' => 'select', 'value' => [
                        ['name' => 0, 'value' => '否'],
                        ['name' => 1, 'value' => '是'],
                    ]],
                    ['key' => 'KZSJ', 'name' => '开嘱时间', 'type' => 'time', 'value' => ''],
                    ['key' => 'YDYZLB', 'name' => '医嘱类别', 'type' => 'select', 'value' => [
                        ['name' => 212, 'value' => '理疗'],
                        ['name' => 101, 'value' => '西药'],
                        ['name' => 102, 'value' => '成药'],
                        ['name' => 103, 'value' => '草药'],
                        ['name' => 201, 'value' => '治疗'],
                        ['name' => 202, 'value' => '检验'],
                        ['name' => 203, 'value' => '检查'],
                        ['name' => 204, 'value' => '手术'],
                        ['name' => 205, 'value' => '麻醉'],
                        ['name' => 206, 'value' => '护理'],
                        ['name' => 207, 'value' => '饮食'],
                        ['name' => 208, 'value' => '输氧'],
                        ['name' => 209, 'value' => '材料'],
                        ['name' => 210, 'value' => '其他'],
                        ['name' => 301, 'value' => '会诊'],
                        ['name' => 302, 'value' => '转科'],
                        ['name' => 303, 'value' => '出院'],
                        ['name' => 304, 'value' => '转院'],
                        ['name' => 305, 'value' => '死亡'],
                        ['name' => 310, 'value' => '手术后'],
                        ['name' => 311, 'value' => '分娩后'],
                        ['name' => 312, 'value' => '转科后'],
                        ['name' => 313, 'value' => '重整后'],
                        ['name' => 901, 'value' => '文字医嘱'],
                        ['name' => 401, 'value' => '材料医嘱'],
                        ['name' => 501, 'value' => '草药方医嘱'],
                        ['name' => 104, 'value' => '摆药'],
                        ['name' => 211, 'value' => '输血'],
                    ]],
                ]
            ],
            [
                'key' => 'FYMX',
                'name' => '费用明细',
                'children' => [
                    //                    ['key' => 'JFRQ', 'name' => '计费日期', 'type' => 'time', 'value' => ''],
                    //                    ['key' => 'YBBM', 'name' => '医保编码', 'type' => 'input', 'value' => ''],
                    ['key' => 'FYMC', 'name' => '费用名称', 'type' => 'input', 'value' => ''],
                    ['key' => 'FYSL', 'name' => '费用数量', 'type' => 'range', 'value' => ''],
                    ['key' => 'FYDJ', 'name' => '费用单价', 'type' => 'range', 'value' => ''],
                    ['key' => 'ZJE', 'name' => '总金额', 'type' => 'range', 'value' => ''],
                    //                    ['key' => 'YSGH', 'name' => '医生工号', 'type' => 'select', 'value' => $doctor],
                    //                    ['key' => 'ZLXZ', 'name' => '诊疗小组', 'type' => 'select', 'value' => $ZY_ZLXZ],
                    //                    ['key' => 'FYKS', 'name' => '费用科室', 'type' => 'select', 'value' => $department],
                    //                    ['key' => 'ZXKS', 'name' => '执行科室', 'type' => 'select', 'value' => $department],
                    //                    ['key' => 'FYGB', 'name' => '费用归并', 'type' => 'input', 'value' => ''],
                    //                    ['key' => 'SYFYGB', 'name' => '首页费用归并', 'type' => 'input', 'value' => ''],
                    //                    ['key' => 'YPLX', 'name' => '药品类型', 'type' => 'select', 'value' => $this->getRuleWordMap(132)],
                ]
            ],
            [
                'key' => 'JYBG',
                'name' => '检验报告',
                'children' => [
                    ['key' => 'JY', 'name' => '检验', 'children' => [
                        ['key' => 'YBLX', 'name' => '样本类型', 'type' => 'input', 'value' => ''],
                        ['key' => 'LCZD', 'name' => '临床诊断', 'type' => 'input', 'value' => ''],
                        ['key' => 'JYXM', 'name' => '检验项目', 'type' => 'input', 'value' => ''],
                        ['key' => 'JG', 'name' => '检验结果', 'type' => 'input', 'value' => ''],
                        ['key' => 'TS', 'name' => '检验提示', 'type' => 'input', 'value' => ''],
                        ['key' => 'SJYS', 'name' => '检验医生', 'type' => 'input', 'value' => ''],
                        ['key' => 'BGSJ', 'name' => '报告时间', 'type' => 'time', 'value' => ''],
                    ]],
                    ['key' => 'YM', 'name' => '药敏', 'children' => [
                        ['key' => 'YBLX', 'name' => '样本类型', 'type' => 'input', 'value' => ''],
                        ['key' => 'LCZD', 'name' => '临床诊断', 'type' => 'input', 'value' => ''],
                        ['key' => 'PYJG', 'name' => '细菌培养结果', 'type' => 'input', 'value' => ''],
                        ['key' => 'XJMC', 'name' => '细菌名称', 'type' => 'input', 'value' => ''],
                        ['key' => 'XJJL', 'name' => '细菌数量', 'type' => 'input', 'value' => ''],
                        ['key' => 'YMMC', 'name' => '药敏名称', 'type' => 'input', 'value' => ''],
                        ['key' => 'YMJG', 'name' => '药敏结果', 'type' => 'input', 'value' => ''],
                        ['key' => 'YMBW', 'name' => '药敏部位', 'type' => 'input', 'value' => ''],
                        ['key' => 'JYY', 'name' => '检验员', 'type' => 'input', 'value' => ''],
                        ['key' => 'BGSJ', 'name' => '报告时间', 'type' => 'time', 'value' => ''],
                    ]],
                ]
            ],
            [
                'key' => 'JCBG',
                'name' => '检查报告',
                'children' => [
                    ['key' => 'MZZYBZ', 'name' => '检查标志', 'type' => 'select', 'value' => [
                        ['name' => 1, 'value' => '门诊'],
                        ['name' => 2, 'value' => '住院'],
                        ['name' => 3, 'value' => '体检'],
                        ['name' => 4, 'value' => '绿色通道'],
                        ['name' => 41, 'value' => '门诊绿色通道'],
                        ['name' => 42, 'value' => '住院绿色通道'],
                        ['name' => 9, 'value' => '其他'],
                    ]],
                    ['key' => 'ExamType', 'name' => '检查类型', 'type' => 'input', 'value' => [
                        ['name' => '01', 'value' => '计算机X线断层摄影 CT'],
                        ['name' => '02', 'value' => '核磁共振成像MR'],
                        ['name' => '04', 'value' => '普通X光摄影X-Ray'],
                        ['name' => '06', 'value' => '超声检查US'],
                        ['name' => '07', 'value' => '病理检查Microscopy'],
                        ['name' => '08', 'value' => '內窥镜检查ES'],
                        ['name' => '09', 'value' => '核医学检查NM'],
                        ['name' => '10', 'value' => '其他检查OT'],
                        ['name' => '11', 'value' => '介入'],
                    ]],
                    //                    ['key' => 'SBBM', 'name' => '检查设备仪器型号', 'type' => 'input', 'value' => ''],
                    ['key' => 'JCKSMC', 'name' => '检查科室名称', 'type' => 'select', 'value' => $department],
                    ['key' => 'JCYS', 'name' => '检查医生姓名', 'type' => 'select', 'value' => $doctor],
                    ['key' => 'BGSJ', 'name' => '报告时间', 'type' => 'time', 'value' => ''],
                    ['key' => 'JCBW', 'name' => '检查部位', 'type' => 'input', 'value' => ''],
                    ['key' => 'JCMC', 'name' => '检查名称', 'type' => 'input', 'value' => ''],
                    ['key' => 'YXBX', 'name' => '影像表现', 'type' => 'input', 'value' => ''],
                    ['key' => 'YXZD', 'name' => '检查诊断', 'type' => 'input', 'value' => ''],
                ]
            ],
            //            ['key' => 'BGD', 'name' => '报告单', 'type' => 'input', 'value' => ''],
            //            ['key' => 'FYMC', 'name' => '费用明细', 'type' => 'input', 'value' => ''],
            /* ['key' => 'MD_ICD10_NAME', 'name' => '主要诊断名称', 'type' => 'input', 'value' => ''],
            ['key' => 'MD_ICD10_ID1', 'name' => '主要诊断编码', 'type' => 'input', 'value' => ''],
            ['key' => 'OD_ICD10_NAME', 'name' => '其他诊断名称', 'type' => 'input', 'value' => ''],
            ['key' => 'OD_ICD10_ID1', 'name' => '其他诊断编码', 'type' => 'input', 'value' => ''],
            ['key' => 'MO_ICD9_NAME', 'name' => '主要手术名称', 'type' => 'input', 'value' => ''],
            ['key' => 'MO_ICD9_ID1', 'name' => '主要手术编码', 'type' => 'input', 'value' => ''],
            ['key' => 'SO_ICD9_NAME', 'name' => '其他手术名称', 'type' => 'input', 'value' => ''],
            ['key' => 'SO_ICD9_ID1', 'name' => '其他手术编码', 'type' => 'input', 'value' => ''], */
            /* ['key' => 'SQTYL', 'name' => '授权同意类', 'type' => 'input', 'value' => ''],
            ['key' => 'YHGTL', 'name' => '医患沟通类', 'type' => 'input', 'value' => ''],
            ['key' => 'BLTLJL', 'name' => '病历讨论记录', 'type' => 'input', 'value' => ''], */
            //            ['key' => 'PGPFB', 'name' => '评估评分表类', 'type' => 'input', 'value' => ''],
            //            ['key' => 'YLCYBG', 'name' => '医疗常用表格', 'type' => 'input', 'value' => ''],
        ];

        return ['bl' => $blSelectList];
    }

    /**
     * @param array $field
     * @param bool $getFilter
     * @param string $zyh 指定住院号搜索
     * @param array $searchFile 需要匹配的字段
     * @param string $index 要搜索的索引
     * @param string $prefix 病案首页中要搜索的字段前缀
     * @return array
     * 专业搜索
     */
    public function searchPublic($field = [], $getFilter = true, $zyh = "", $searchFile = [], $index = "", $prefix = "")
    {

        $should = $mustNot = $must = [];
        if (empty($field)) {
            return [];
        }

        foreach ($field as $item) {
            if (empty($item['value'])) {
                continue;
            }
            if (in_array($item['key'], $searchFile)) {
                $time = [];
                if ($item['type'] == 'time') {
                    $time = explode(',', $item['value']);
                }
                $key = ($zyh ? '' : $prefix . '.') . $item['key'];

                // 主信息信息
                // 不同类型的筛选条件,还需要一个判断搜索值是输入的还是下拉选择的
                $filter = ['match_phrase' => [$key => $item['value']]];
                if ($item['type'] == 'time') {
                    $filter = ['range' => [$key => ['gte' => date("Y-m-d H:i:s", $time[0] / 1000), 'lte' => date("Y-m-d H:i:s", $time[1] / 1000)]]];
                } elseif ($item['type'] == 'select') {
                    $filter = ['term' => [$key => $item['value']]];
                }
                // select_type 0 并且，1或者，2不包含
                if ($item['select_type'] === 1) {
                    $should[] = $filter;
                } else if ($item['select_type'] == 2) {
                    $mustNot[] = $filter;
                } else {
                    $must[] = $filter;
                }
            }
        }

        if ($getFilter) {
            return ['must' => $must, 'mustNot' => $mustNot, 'should' => $should];
        }
        // 根据住院号搜索
        if (!empty($zyh)) {
            // 根据住院号搜索
            if ($index == 'pacs') {
                // pacs无法直接查，需要通过医嘱本中的SQDH
                $yzb = Yzb::query()->where("ZYH", '=', $zyh)->get(['SQDH'])->toArray();
                $SQDH = array_column($yzb, 'SQDH');
                $must[] = ['terms' => ['SQDH' => $SQDH]];
            } else {
                if ($index == 'fee_detailed') {
                    $must[] = ['term' => ['MED_REC_ID' => $zyh]];
                } else {
                    $must[] = ['term' => ['ZYH' => $zyh]];
                }
            }
        }

        // 判断本次搜索是否包含指定的字段
        $isSearch = array_intersect(array_column($field, 'key'), $searchFile);
        if (empty($isSearch)) {
            return [];
        }
        if (empty($should) && empty($mustNot) && empty($must)) {
            return [];
        }

        $yzbService = new ElasticsearchService($index);
        // 滚动获取所有满足条件的数据
        $params = $yzbService->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNotBatch($mustNot)
            ->queryByShouldBatch($should)
            ->size(1000);
        $params = $params->getParams();
        // 数据查询
        $restful = app('es')->search($params);
        $blData = $yzbService->getDataByEs($restful);
        $yzb = $blData[0];

        return $yzb;
    }

    /**
     * @param array $field
     * @param bool $getFilter
     * @param string $zyh 指定住院号搜索
     * @return array
     * 专业搜索中的医嘱本搜索
     */
    public function yzbSearch($field = [], $getFilter = true, $zyh = "")
    {
        $mhService = new MedicalHistoryService();
        $blRes = $mhService->getBlSerachSelectData();
        // 病例搜索的所有下拉选项
        $selectType = array_column($blRes['bl'], null, 'key');
        $yzbFiled = isset($selectType['YZB']) ? array_column($selectType['YZB']['children'], 'key') : [];

        return $this->searchPublic($field, $getFilter, $zyh, $yzbFiled, 'yzb_2023', 'yzb');
    }


    /**
     * @param array $field
     * @param bool $getFilter
     * @param string $zyh 指定住院号搜索
     * @return array
     * 专业搜索中的检查报告搜索
     */
    public function jcSearch($field = [], $getFilter = true, $zyh = "")
    {
        if (empty($field)) {
            return [];
        }
        $mhService = new MedicalHistoryService();
        $blRes = $mhService->getBlSerachSelectData();
        // 病例搜索的所有下拉选项
        $selectType = array_column($blRes['bl'], null, 'key');
        $yzbFiled = isset($selectType['JCBG']) ? array_column($selectType['JCBG']['children'], 'key') : [];
        return $this->searchPublic($field, $getFilter, $zyh, $yzbFiled, 'pacs', 'jc_result');
    }

    /**
     * @param array $field
     * @param bool $getFilter
     * @param string $zyh 指定住院号搜索
     * @return array
     * 检验查询
     * 根据条件获取所有满足条件的住院号
     */
    public function jySearch($field = [], $getFilter = true, $zyh = "")
    {
        if (empty($field)) {
            return [];
        }
        $mhService = new MedicalHistoryService();
        $blRes = $mhService->getBlSerachSelectData();
        // 病例搜索的所有下拉选项
        $selectType = array_column($blRes['bl'], null, 'key');
        $JYBG = isset($selectType['JYBG']) ? array_column($selectType['JYBG']['children'], null, 'key') : [];
        $yzbFiled = isset($JYBG['JY']) ? array_column($JYBG['JY']['children'], 'key') : [];

        return $this->searchPublic($field, $getFilter, $zyh, $yzbFiled, 'v_jmgs_testresult_2023', 'test_result');
    }

    /**
     * @param array $field
     * @param bool $getFilter
     * @param string $zyh 指定住院号搜索
     * @return array
     * 检验查询
     * 根据条件获取所有满足条件的住院号
     */
    public function ymSearch($field = [], $getFilter = true, $zyh = "")
    {
        if (empty($field)) {
            return [];
        }
        $mhService = new MedicalHistoryService();
        $blRes = $mhService->getBlSerachSelectData();
        // 病例搜索的所有下拉选项
        $selectType = array_column($blRes['bl'], null, 'key');
        $JYBG = isset($selectType['JYBG']) ? array_column($selectType['JYBG']['children'], null, 'key') : [];
        $yzbFiled = isset($JYBG['YM']) ? array_column($JYBG['YM']['children'], 'key') : [];
        return $this->searchPublic($field, $getFilter, $zyh, $yzbFiled, 'v_jmgs_ymresult_2023', 'ym_result');
    }

    /**
     * @param array $field
     * @param bool $getZyh 是否只获取住院号
     * @param string $zyh 指定住院号搜索
     * @return array
     * 专业搜索中的费用明细搜索
     * 根据条件获取所有满足条件的住院号
     */
    public function feeSearch($field = [], $getFilter = true, $zyh = "")
    {
        if (empty($field)) {
            return [];
        }
        $mhService = new MedicalHistoryService();
        $blRes = $mhService->getBlSerachSelectData();
        // 病例搜索的所有下拉选项
        $selectType = array_column($blRes['bl'], null, 'key');
        $yzbFiled = isset($selectType['FYMX']) ? array_column($selectType['FYMX']['children'], 'key') : [];
        return $this->searchPublic($field, $getFilter, $zyh, $yzbFiled, 'fee_detailed', 'fymx');
    }

    /**
     * @param array $field
     * @param bool $getZyh 是否只获取住院号
     * @param string $zyh 指定住院号搜索
     * @return array
     * 专业搜索中的费用明细搜索
     * 根据条件获取所有满足条件的住院号
     */
    public function sssqSearch($field = [], $getFilter = true, $zyh = "")
    {
        if (empty($field)) {
            return [];
        }
        $mhService = new MedicalHistoryService();
        $blRes = $mhService->getBlSerachSelectData();
        // 病例搜索的所有下拉选项
        $selectType = array_column($blRes['bl'], null, 'key');
        $JYBG = isset($selectType['SSJL']) ? array_column($selectType['SSJL']['children'], null, 'key') : [];
        $yzbFiled = isset($JYBG['SSSQ']) ? array_column($JYBG['SSSQ']['children'], 'key') : [];
        return $this->searchPublic($field, $getFilter, $zyh, $yzbFiled, 'sssq_2023', 'sssq');
    }

    /**
     * 病历搜索
     * @param $serachData
     * @param $page
     * @param $pageSize
     * @return array
     */
    public function blSerach($serachData, $page, $pageSize)
    {
        // 查询条件处理
        $field = $serachData['field'] ?? [];
        $must = [];
        $should = [];
        $mustNot = [];
        // 搜索字段记录
        $serachField = [];

        // 住院号
        if (!empty($serachData['AAA28'])) {
            $must[] = ['term' => ['AAA28' => $serachData['AAA28']]];
        }

        // 出院科室
        if (!empty($serachData['AAC02C']) && $serachData['AAC02C'] != '全部') {
            $serachField[] = 'AAC02C';
            if (is_array($serachData['AAC02C'])) {
                $must[] = ['terms' => ['AAC02C' => $serachData['AAC02C']]];
            } else {
                $must[] = ['term' => ['AAC02C' => $serachData['AAC02C']]];
            }
        }

        // 出院时间
        if (!empty($serachData['AAC01_START'])) {
            $AAC01_start = date('Y-m-d', $serachData['AAC01_START'] / 1000) . ' 00:00:00';
            $must[] = ['range' => ['AAC01' => ['gte' => $AAC01_start]]];
        }
        if (!empty($serachData['AAC01_END'])) {
            $AAC01_end = date('Y-m-d', $serachData['AAC01_END'] / 1000) . ' 23:59:59';
            $must[] = ['range' => ['AAC01' => ['lte' => $AAC01_end]]];
        }



        // 姓名
        if (!empty($serachData['AAA01'])) {
            $serachField[] = 'AAA01';
            $must[] = ['match_phrase' => ['AAA01' => $serachData['AAA01']]];
        }

        // 性别
        if (isset($serachData['AAA02C']) && $serachData['AAA02C'] !== '') {
            $serachField[] = 'AAA02C';
            $must[] = ['term' => ['AAA02C' => $serachData['AAA02C']]];
        }

        // 入院时间
        if (!empty($serachData['AAB01_START']) && !empty($serachData['AAB01_END'])) {
            $serachField[] = 'AAB01';
            $AAB01_start = date('Y-m-d', $serachData['AAB01_START'] / 1000) . ' 00:00:00';
            $AAB01_end = date('Y-m-d', $serachData['AAB01_END'] / 1000) . ' 23:59:59';
            $must[] = ['range' => ['AAB01' => ['gte' => $AAB01_start, 'lte' => $AAB01_end]]];
        } elseif (!empty($serachData['AAB01_START'])) {
            $serachField[] = 'AAB01';
            $AAB01_start = date('Y-m-d', $serachData['AAB01_START'] / 1000) . ' 00:00:00';
            $must[] = ['range' => ['AAB01' => ['gte' => $AAB01_start]]];
        } elseif (!empty($serachData['AAB01_END'])) {
            $serachField[] = 'AAB01';
            $AAB01_end = date('Y-m-d', $serachData['AAB01_END'] / 1000) . ' 23:59:59';
            $must[] = ['range' => ['AAB01' => ['lte' => $AAB01_end]]];
        }

        // 住院天数
        if (!empty($serachData['AAC04_START']) && !empty($serachData['AAC04_END'])) {
            $serachField[] = 'AAC04';
            $must[] = ['range' => ['AAC04' => ['gte' => $serachData['AAC04_START'], 'lte' => $serachData['AAC04_END']]]];
        } elseif (!empty($serachData['AAC04_START'])) {
            $serachField[] = 'AAC04';
            $must[] = ['range' => ['AAC04' => ['gte' => $serachData['AAC04_START']]]];
        } elseif (!empty($serachData['AAC04_END'])) {
            $serachField[] = 'AAC04';
            $must[] = ['range' => ['AAC04' => ['lte' => $serachData['AAC04_END']]]];
        }

        // 患者年龄
        if (!empty($serachData['AAA04_START']) && !empty($serachData['AAA04_END'])) {
            $serachField[] = 'AAA04';
            $must[] = ['range' => ['AAA04' => ['gte' => $serachData['AAA04_START'], 'lte' => $serachData['AAA04_END']]]];
        } elseif (!empty($serachData['AAA04_START'])) {
            $serachField[] = 'AAA04';
            $must[] = ['range' => ['AAA04' => ['gte' => $serachData['AAA04_START']]]];
        } elseif (!empty($serachData['AAA04_END'])) {
            $serachField[] = 'AAA04';
            $must[] = ['range' => ['AAA04' => ['lte' => $serachData['AAA04_END']]]];
        }

        // 患者天数
        if (!empty($serachData['AAA40_START']) && !empty($serachData['AAA40_END'])) {
            $serachField[] = 'AAA40';
            $must[] = ['range' => ['AAA40' => ['gte' => $serachData['AAA40_START'], 'lte' => $serachData['AAA40_END']]]];
        } elseif (!empty($serachData['AAA40_START'])) {
            $serachField[] = 'AAA40';
            $must[] = ['range' => ['AAA40' => ['gte' => $serachData['AAA40_START']]]];
        } elseif (!empty($serachData['AAA40_END'])) {
            $serachField[] = 'AAA40';
            $must[] = ['range' => ['AAA40' => ['lte' => $serachData['AAA40_END']]]];
        }

        // 体温
        $tiwen_key = [];
        $TIWEN_DATA = !empty($serachData['TIWEN_FIELD']) ? $serachData['TIWEN_FIELD'] : [];
        if (!empty($TIWEN_DATA) && (!empty($serachData['TIWEN_START']) || !empty($serachData['TIWEN_END']))) {
            foreach ($TIWEN_DATA as $tiwenInfo) {
                //                $TIWEN_KEY = !empty($tiwenInfo[1]) ? $tiwenInfo[1] : $tiwenInfo[0];

                $TIWEN_KEY = $tiwenInfo;

                $tiwen_key[] = $TIWEN_KEY;
                $serachField[] = $TIWEN_KEY;

                $twList = [];
                $TIWEN_START = !empty($serachData['TIWEN_START']) ? $serachData['TIWEN_START'] : '';
                $TIWEN_END = !empty($serachData['TIWEN_END']) ? $serachData['TIWEN_END'] : '';
                if ($TIWEN_START && $TIWEN_END) {
                    $TIWEN_START = $TIWEN_START < 30 ? 30 : $TIWEN_START;
                    $TIWEN_END = $TIWEN_END > 45 ? 45 : $TIWEN_END;
                    $twList = self::getTiWen($TIWEN_START, $TIWEN_END);
                } elseif ($TIWEN_START) {
                    $TIWEN_START = $TIWEN_START < 30 ? 30 : $TIWEN_START;
                    $twList = self::getTiWen($TIWEN_START);
                } elseif ($TIWEN_END) {
                    $TIWEN_END = $TIWEN_END > 45 ? 45 : $TIWEN_END;
                    $twList = self::getTiWen(30, $TIWEN_END);
                }

                if ($twList) {
                    $twData = [];
                    $twArr = explode("_", $TIWEN_KEY);
                    foreach ($twList as $value) {
                        if (in_array($TIWEN_KEY, ['bllb18_HJNR', 'bllb294_HJNR'])) {
                            $twData['should'][] = ['match_phrase' => [$twArr[0] . '.' . $twArr[1] => '体温' . $value . '℃']];
                            //                            $twData['should'][] = ['match_phrase' => [$twArr[0].'.'.$twArr[1] => '体温：'.$value.'℃']];
                            $twData['should'][] = ['match_phrase' => [$twArr[0] . '.' . $twArr[1] => 'T' . $value . '℃']];
                            //                            $twData['should'][] = ['match_phrase' => [$twArr[0].'.'.$twArr[1] => 'T：'.$value.'℃']];
                        } else {
                            $should[] = ['match_phrase' => [$TIWEN_KEY => '体温' . $value . '℃']];
                            //                            $should[] = ['match_phrase' => [$TIWEN_KEY => '体温：'.$value.'℃']];
                            $should[] = ['match_phrase' => [$TIWEN_KEY => 'T' . $value . '℃']];
                            //                            $should[] = ['match_phrase' => [$TIWEN_KEY => 'T：'.$value.'℃']];
                        }
                    }

                    if (!empty($twData)) {
                        $should[] = $this->esWhereHandle($twArr[0], $twData);
                    }
                }
            }
        }

        // 患者基本信息、入院记录字段、出院记录（索引主信息）
        $patientKey = self::$patientKey;

        // 手术记录
        $bllb303 = $bllb303Serach = [];
        // 其它诊断
        $qtzd = $qtzdSerach = [];
        // 其它手术
        $qtss = $qtssSerach = [];
        // 病程类-输血记录
        $bcjlSxjl = $bcjlSxjlSerach = [];
        // 病程类-整体
        $bcjlQw = $bcjlQwSerach = [];
        // 手术类-整体
        $ssZt = $ssZtSerach = [];
        // 医嘱本搜索
        $yzb = $yzbSerach = [];
        // 费用明细
        $fymx = $fymxSerach = [];
        // 医生信息
        $doctor = $doctorSerach = [];
        $bllb = 'bllb294';

        $ssjlField = $qtssField = $qtzdField = $bcjlSxjlField = $doctorField = [];

        if (!empty($field)) {
            // 搜索方式
            $selectType = ['must', 'should', 'must_not'];

            // 需要特殊处理的查询
            $matchPhrasePrefix = ['MD_ICD10_ID1', 'OD_ICD10_ID1', 'MO_ICD9_ID1', 'SO_ICD9_ID1'];

            foreach ($field as $value) {
                if (empty($value['value']) && empty($value['min']) && empty($value['max']) && strpos($value['key'], '_QW') === false) {
                    continue;
                }

                // 记录搜索的字段
                if (!in_array($value['key'], $serachField) && $value['select_type'] != 2) {
                    $serachField[] = $value['key'];
                }

                $selectTypeValue = $selectType[$value['select_type']];

                // 判断是不是编码，编码用match_phrase_prefix查询，其它的用match_phrase
                $type = in_array($value['key'], $matchPhrasePrefix) ? 'match_phrase_prefix' : 'match_phrase';
                if ($value['type'] == 'select') {
                    $type = is_array($value['value']) ? 'terms' : 'term';
                } elseif ($value['type'] == 'range') {
                    $type = 'range';
                    $value['value'] = [
                        'gte' => $value['min'],
                        'lte' => $value['max'],
                    ];
                }


                if (in_array($value['key'], $patientKey)) {
                    // 主信息信息
                    if ($value['select_type'] === 1) {
                        $should[] = [$type => [$value['key'] => $value['value']]];
                    } else if ($value['select_type'] == 2) {
                        $mustNot[] = [$type => [$value['key'] => $value['value']]];
                    } else {
                        $must[] = [$type => [$value['key'] => $value['value']]];
                    }
                } elseif (in_array($value['key'], self::$bllb303)) {
                    // 手术记录
                    $key = str_replace('SSJL_', '', $value['key']);

                    if ($selectTypeValue != 'must_not') {
                        $ssjlField[] = $key;
                    }

                    if ($key == 'SSRQ_STRAT') {
                        $bllb303Serach[$selectTypeValue][] = ['range' => ['bllb303.' . $key => ['get' => $value['value']]]];
                        $bllb303[$selectTypeValue][] = ['range' => [$key => ['get' => $value['value']]]];
                    } elseif ($key == 'SSRQ_END') {
                        $bllb303Serach[$selectTypeValue][] = ['range' => ['bllb303.' . $key => ['lte' => $value['value']]]];
                        $bllb303[$selectTypeValue][] = ['range' => [$key => ['lte' => $value['value']]]];
                    } else {
                        $bllb303Serach[$selectTypeValue][] = [$type => ['bllb303.' . $key => $value['value']]];
                        $bllb303[$selectTypeValue][] = [$type => [$key => $value['value']]];
                    }

                    // 有或的关系，必须命中一次
                    if ($selectTypeValue == 'should') {
                        $bllb303Serach['minimum_should_match'] = 1;
                    }
                } elseif (in_array($value['key'], self::$otherDiagnosisKey)) {
                    // 其它诊断
                    $key = str_replace('OD_', '', $value['key']);

                    if ($selectTypeValue != 'must_not') {
                        $qtzdField[] = $key;
                    }

                    $qtzdSerach[$selectTypeValue][] = [$type => ['other_diagnosis.' . $key => $value['value']]];
                    $qtzd[$selectTypeValue][] = [$type => [$key => $value['value']]];

                    // 有或的关系，必须命中一次
                    if ($selectTypeValue == 'should') {
                        $qtzdSerach['minimum_should_match'] = 1;
                    }
                } elseif (in_array($value['key'], self::$secondaryOperationKey)) {
                    // 其他手术
                    $key = str_replace('SO_', '', $value['key']);

                    if ($selectTypeValue != 'must_not') {
                        $qtssField[] = $key;
                    }

                    $qtssSerach[$selectTypeValue][] = [$type => ['secondary_operation.' . $key => $value['value']]];
                    $qtss[$selectTypeValue][] = [$type => [$key => $value['value']]];

                    // 有或的关系，必须命中一次
                    if ($selectTypeValue == 'should') {
                        $qtssSerach['minimum_should_match'] = 1;
                    }
                } elseif (in_array($value['key'], self::$feeKey)) {

                    $fymxSerach[$selectTypeValue][] = [$type => ['fymx.' . $value['key'] => $value['value']]];
                    $fymx[$selectTypeValue][] = [$type => [$value['key'] => $value['value']]];

                    // 有或的关系，必须命中一次
                    if ($selectTypeValue == 'should') {
                        $fymxSerach['minimum_should_match'] = 1;
                    }
                } elseif (in_array($value['key'], self::$yzbKey)) {

                    if($value['key'] == 'KZSJ'){
                        $time = explode(',', $value['value']);
                        $yzbSerach[$selectTypeValue][] = ['range' => ['yzb.' . $value['key'] => ['gte' => date('Y-m-d H:i:s', $time[0]/1000), 'lte' => date('Y-m-d H:i:s', $time[1]/1000)]]];
                        $yzb[$selectTypeValue][] = ['range' => [$value['key'] => ['gte' => date('Y-m-d H:i:s', $time[0]/1000), 'lte' => date('Y-m-d H:i:s', $time[1]/1000)]]];
                    }else{
                        $yzbSerach[$selectTypeValue][] = [$type => ['yzb.' . $value['key'] => $value['value']]];
                        $yzb[$selectTypeValue][] = [$type => [$value['key'] => $value['value']]];
                    }

                    // 有或的关系，必须命中一次
                    if ($selectTypeValue == 'should') {
                        $yzbSerach['minimum_should_match'] = 1;
                    }
                } elseif (in_array($value['key'], self::$returnGroupKey['bcjl_sxbc'])) {
                    // 病程记录-输血记录
                    $key = str_replace('BCJL_SXBC_', '', $value['key']);

                    if ($selectTypeValue != 'must_not') {
                        $bcjlSxjlField[] = $key;
                    }

                    $bcjlSxjlSerach[$selectTypeValue][] = [$type => ['bllb294_45.' . $key => $value['value']]];
                    $bcjlSxjl[$selectTypeValue][] = [$type => [$key => $value['value']]];

                    // 有或的关系，必须命中一次
                    if ($selectTypeValue == 'should') {
                        $bcjlSxjlSerach['minimum_should_match'] = 1;
                    }
                } elseif ($value['key'] == 'BCJL_QW') {
                    // 病程类-整体
                    $bllb = 'bllb294';
                    if (empty($value['value'])) {
                        //查询bllb294不为空
                        $bcjlQwSerach[$selectTypeValue][] = ['exists' => ['field' => 'bllb294.HJNR']];
                        $bcjlQw[$selectTypeValue][] = ['exists' => ['field' => 'bllb294.HJNR']];
                    } else {
                        $bcjlQwSerach[$selectTypeValue][] = [$type => ['bllb294.HJNR' => $value['value']]];
                        $bcjlQw[$selectTypeValue][] = [$type => ['HJNR' => $value['value']]];
                    }


                    // 有或的关系，必须命中一次
                    if ($selectTypeValue == 'should') {
                        $bcjlQwSerach['minimum_should_match'] = 1;
                    }
                } elseif ($value['key'] == 'RYJL_QW') {
                    // 病程类-整体
                    $bllb = 'bllb292';
                    if (empty($value['value'])) {
                        //查询bllb292不为空
                        $bcjlQwSerach[$selectTypeValue][] = ['exists' => ['field' => 'bllb292.HJNR']];
                        $bcjlQw[$selectTypeValue][] = ['exists' => ['field' => 'bllb292.HJNR']];
                    } else {
                        $bcjlQwSerach[$selectTypeValue][] = [$type => ['bllb292.HJNR' => $value['value']]];
                        $bcjlQw[$selectTypeValue][] = [$type => ['HJNR' => $value['value']]];
                    }

                    // 有或的关系，必须命中一次
                    if ($selectTypeValue == 'should') {
                        $bcjlQwSerach['minimum_should_match'] = 1;
                    }
                } elseif ($value['key'] == 'CYJL_QW') {
                    // 病程类-整体
                    $bllb = 'bllb1';
                    if (empty($value['value'])) {
                        //查询bllb1不为空
                        $bcjlQwSerach[$selectTypeValue][] = ['exists' => ['field' => 'bllb1.HJNR']];
                        $bcjlQw[$selectTypeValue][] = ['exists' => ['field' => 'bllb1.HJNR']];
                    } else {
                        $bcjlQwSerach[$selectTypeValue][] = [$type => ['bllb1.HJNR' => $value['value']]];
                        $bcjlQw[$selectTypeValue][] = [$type => ['HJNR' => $value['value']]];
                    }

                    // 有或的关系，必须命中一次
                    if ($selectTypeValue == 'should') {
                        $bcjlQwSerach['minimum_should_match'] = 1;
                    }
                } elseif ($value['key'] == 'SWJL_QW') {
                    // 病程类-整体
                    $bllb = 'bllb288';
                    if (empty($value['value'])) {
                        //查询bllb288不为空
                        $bcjlQwSerach[$selectTypeValue][] = ['exists' => ['field' => 'bllb288.HJNR']];
                        $bcjlQw[$selectTypeValue][] = ['exists' => ['field' => 'bllb288.HJNR']];
                    } else {
                        $bcjlQwSerach[$selectTypeValue][] = [$type => ['bllb288.HJNR' => $value['value']]];
                        $bcjlQw[$selectTypeValue][] = [$type => ['HJNR' => $value['value']]];
                    }

                    // 有或的关系，必须命中一次
                    if ($selectTypeValue == 'should') {
                        $bcjlQwSerach['minimum_should_match'] = 1;
                    }
                } elseif ($value['key'] == '24XSCRYJL_QW') {
                    // 病程类-整体
                    $bllb = 'bllb18';
                    if (empty($value['value'])) {
                        //查询bllb18不为空
                        $bcjlQwSerach[$selectTypeValue][] = ['exists' => ['field' => 'bllb18.HJNR']];
                        $bcjlQw[$selectTypeValue][] = ['exists' => ['field' => 'bllb18.HJNR']];
                    } else {
                        $bcjlQwSerach[$selectTypeValue][] = [$type => ['bllb18.HJNR' => $value['value']]];
                        $bcjlQw[$selectTypeValue][] = [$type => ['HJNR' => $value['value']]];
                    }

                    // 有或的关系，必须命中一次
                    if ($selectTypeValue == 'should') {
                        $bcjlQwSerach['minimum_should_match'] = 1;
                    }
                } elseif ($value['key'] == 'SSJL_QW') {
                    // 病程类-整体
                    $bllb = 'bllb306';
                    if (empty($value['value'])) {
                        //查询bllb306不为空
                        $bcjlQwSerach[$selectTypeValue][] = ['exists' => ['field' => 'bllb306.HJNR']];
                        $bcjlQw[$selectTypeValue][] = ['exists' => ['field' => 'bllb306.HJNR']];
                    } else {
                        $bcjlQwSerach[$selectTypeValue][] = [$type => ['bllb306.HJNR' => $value['value']]];
                        $bcjlQw[$selectTypeValue][] = [$type => ['HJNR' => $value['value']]];
                    }

                    // 有或的关系，必须命中一次
                    if ($selectTypeValue == 'should') {
                        $bcjlQwSerach['minimum_should_match'] = 1;
                    }
                } elseif ($value['key'] == 'TLJL_QW') {
                    // 病程类-整体
                    $bllb = 'bllb4344';
                    if (empty($value['value'])) {
                        //查询bllb4344不为空
                        $bcjlQwSerach[$selectTypeValue][] = ['exists' => ['field' => 'bllb4344.HJNR']];
                        $bcjlQw[$selectTypeValue][] = ['exists' => ['field' => 'bllb4344.HJNR']];
                    } else {
                        $bcjlQwSerach[$selectTypeValue][] = [$type => ['bllb4344.HJNR' => $value['value']]];
                        $bcjlQw[$selectTypeValue][] = [$type => ['HJNR' => $value['value']]];
                    }
                    // 有或的关系，必须命中一次
                    if ($selectTypeValue == 'should') {
                        $bcjlQwSerach['minimum_should_match'] = 1;
                    }
                } elseif ($value['key'] == 'SQTYS_QW') {
                    // 病程类-整体
                    $bllb = 'bllb8';
                    if (empty($value['value'])) {
                        //查询bllb8不为空
                        $bcjlQwSerach[$selectTypeValue][] = ['exists' => ['field' => 'bllb8.HJNR']];
                        $bcjlQw[$selectTypeValue][] = ['exists' => ['field' => 'bllb8.HJNR']];
                    } else {
                        $bcjlQwSerach[$selectTypeValue][] = [$type => ['bllb8.HJNR' => $value['value']]];
                        $bcjlQw[$selectTypeValue][] = [$type => ['HJNR' => $value['value']]];
                    }
                    // 有或的关系，必须命中一次
                    if ($selectTypeValue == 'should') {
                        $bcjlQwSerach['minimum_should_match'] = 1;
                    }
                } elseif ($value['key'] == 'SSJL_ZT') {
                    // 手术类-整体
                    $ssZtSerach[$selectTypeValue][] = [$type => ['ssjl.HJNR' => $value['value']]];
                    $ssZt[$selectTypeValue][] = [$type => ['HJNR' => $value['value']]];

                    // 有或的关系，必须命中一次
                    if ($selectTypeValue == 'should') {
                        $ssZtSerach['minimum_should_match'] = 1;
                    }
                }
            }
        }

        // 手术记录
        if ($bllb303Serach) {
            if (!empty($bllb303Serach['must'])) {
                $must[] = $this->esWhereHandle('bllb303', ['must' => $bllb303Serach['must']]);
            }
            if (!empty($bllb303Serach['should'])) {
                $should[] = $this->esWhereHandle('bllb303', ['should' => $bllb303Serach['should']]);
            }
            if (!empty($bllb303Serach['must_not'])) {
                $mustNot[] = $this->esWhereHandle('bllb303', ['must_not' => $bllb303Serach['must_not']]);
            }
            //            $should[] = $this->esWhereHandle('bllb303',$bllb303Serach);
        }

        // 其它诊断
        if ($qtzdSerach) {
            if (!empty($qtzdSerach['must'])) {

                if (count($qtzdSerach['must']) > 1) {
                    foreach ($qtzdSerach['must'] as $k => $v) {
                        $must[] = [
                            'nested' => [
                                'path' => 'other_diagnosis',
                                'query' => [
                                    'bool' => [
                                        'must' => $qtzdSerach['must'][$k]
                                    ]
                                ]
                            ]
                        ];
                    }
                } else {
                    $must[] = $this->esWhereHandle('other_diagnosis', ['must' => $qtzdSerach['must']]);
                }
            }
            if (!empty($qtzdSerach['should'])) {
                $should[] = $this->esWhereHandle('other_diagnosis', ['should' => $qtzdSerach['should']]);
            }
            if (!empty($qtzdSerach['must_not'])) {
                $mustNot[] = $this->esWhereHandle('other_diagnosis', ['must_not' => $qtzdSerach['must_not']]);
            }
            //            $should[] = $this->esWhereHandle('other_diagnosis',$qtzdSerach);
        }

        // 其它手术
        if ($qtssSerach) {
            if (!empty($qtssSerach['must'])) {
                $must[] = $this->esWhereHandle('secondary_operation', ['must' => $qtssSerach['must']]);
            }
            if (!empty($qtssSerach['should'])) {
                $should[] = $this->esWhereHandle('secondary_operation', ['should' => $qtssSerach['should']]);
            }
            if (!empty($qtssSerach['must_not'])) {
                $mustNot[] = $this->esWhereHandle('secondary_operation', ['must_not' => $qtssSerach['must_not']]);
            }
        }

        // 费用明细
        if ($fymxSerach) {
            if (!empty($fymxSerach['must'])) {
                $must[] = $this->esWhereHandle('fymx', ['must' => $fymxSerach['must']]);
            }
            if (!empty($fymxSerach['should'])) {
                $should[] = $this->esWhereHandle('fymx', ['should' => $fymxSerach['should']]);
            }
            if (!empty($fymxSerach['must_not'])) {
                $mustNot[] = $this->esWhereHandle('fymx', ['must_not' => $fymxSerach['must_not']]);
            }
        }
        // 医嘱
        if ($yzbSerach) {
            if (!empty($yzbSerach['must'])) {
                $must[] = $this->esWhereHandle('yzb', ['must' => $yzbSerach['must']]);
            }
            if (!empty($yzbSerach['should'])) {
                $should[] = $this->esWhereHandle('yzb', ['should' => $yzbSerach['should']]);
            }
            if (!empty($yzbSerach['must_not'])) {
                $mustNot[] = $this->esWhereHandle('yzb', ['must_not' => $yzbSerach['must_not']]);
            }
        }

        // 病程类-输血记录
        if ($bcjlSxjlSerach) {
            if (!empty($bcjlSxjlSerach['must'])) {
                $must[] = $this->esWhereHandle('bllb294_45', ['must' => $bcjlSxjlSerach['must']]);
            }
            if (!empty($bcjlSxjlSerach['should'])) {
                $should[] = $this->esWhereHandle('bllb294_45', ['should' => $bcjlSxjlSerach['should']]);
            }
            if (!empty($bcjlSxjlSerach['must_not'])) {
                $mustNot[] = $this->esWhereHandle('bllb294_45', ['must_not' => $bcjlSxjlSerach['must_not']]);
            }
            //            $should[] = $this->esWhereHandle('bllb294_45',$bcjlSxjlSerach);
        }

        // 病程类-整体
        if ($bcjlQwSerach) {
            if (!empty($bcjlQwSerach['must'])) {
                $must[] = $this->esWhereHandle($bllb, ['must' => $bcjlQwSerach['must']]);
            }
            if (!empty($bcjlQwSerach['should'])) {
                $should[] = $this->esWhereHandle($bllb, ['should' => $bcjlQwSerach['should']]);
            }
            if (!empty($bcjlQwSerach['must_not'])) {
                $mustNot[] = $this->esWhereHandle($bllb, ['must_not' => $bcjlQwSerach['must_not']]);
            }
            //            $should[] = $this->esWhereHandle('bllb294',$bcjlQwSerach);
        }

        // 手术类-整体
        if ($ssZtSerach) {
            if (!empty($ssZtSerach['must'])) {
                $must[] = $this->esWhereHandle('ssjl', ['must' => $ssZtSerach['must']]);
            }
            if (!empty($ssZtSerach['should'])) {
                $should[] = $this->esWhereHandle('ssjl', ['should' => $ssZtSerach['should']]);
            }
            if (!empty($ssZtSerach['must_not'])) {
                $mustNot[] = $this->esWhereHandle('ssjl', ['must_not' => $ssZtSerach['must_not']]);
            }
            //            $should[] = $this->esWhereHandle('ssjl',$ssZtSerach);
        }

        // // 医嘱本
        // $yzbFilter = $this->yzbSearch($field);
        // if (!empty($yzbFilter['must'])) {
        //     $must[] = $this->esWhereHandle('yzb', ['must' => $yzbFilter['must']]);
        // }
        // if (!empty($yzbFilter['should'])) {
        //     $should[] = $this->esWhereHandle('yzb', ['should' => $yzbFilter['should']]);
        // }
        // if (!empty($yzbFilter['must_not'])) {
        //     $mustNot[] = $this->esWhereHandle('yzb', ['must_not' => $yzbFilter['must_not']]);
        // }

        // // 费用明细
        // $feeFilter = $this->feeSearch($field);
        // if (!empty($feeFilter['must'])) {
        //     $must[] = $this->esWhereHandle('fymx', ['must' => $feeFilter['must']]);
        // }
        // if (!empty($feeFilter['should'])) {
        //     $should[] = $this->esWhereHandle('fymx', ['should' => $feeFilter['should']]);
        // }
        // if (!empty($feeFilter['must_not'])) {
        //     $mustNot[] = $this->esWhereHandle('fymx', ['must_not' => $feeFilter['must_not']]);
        // }

        // 手术申请
        $sssqFilter = $this->sssqSearch($field);
        if (!empty($sssqFilter['must'])) {
            $must[] = $this->esWhereHandle('sssq', ['must' => $sssqFilter['must']]);
        }
        if (!empty($sssqFilter['should'])) {
            $should[] = $this->esWhereHandle('sssq', ['should' => $sssqFilter['should']]);
        }
        if (!empty($sssqFilter['must_not'])) {
            $mustNot[] = $this->esWhereHandle('sssq', ['must_not' => $sssqFilter['must_not']]);
        }

        // 检验
        $jyFilter = $this->jySearch($field);
        if (!empty($jyFilter['must'])) {
            $must[] = $this->esWhereHandle('test_result', ['must' => $jyFilter['must']]);
        }
        if (!empty($jyFilter['should'])) {
            $should[] = $this->esWhereHandle('test_result', ['should' => $jyFilter['should']]);
        }
        if (!empty($jyFilter['must_not'])) {
            $mustNot[] = $this->esWhereHandle('test_result', ['must_not' => $jyFilter['must_not']]);
        }

        // 药敏
        $ymFilter = $this->ymSearch($field);
        if (!empty($ymFilter['must'])) {
            $must[] = $this->esWhereHandle('ym_result', ['must' => $ymFilter['must']]);
        }
        if (!empty($ymFilter['should'])) {
            $should[] = $this->esWhereHandle('ym_result', ['should' => $ymFilter['should']]);
        }
        if (!empty($ymFilter['must_not'])) {
            $mustNot[] = $this->esWhereHandle('ym_result', ['must_not' => $ymFilter['must_not']]);
        }

        // 检查
        $jcFilter = $this->jcSearch($field);
        if (!empty($jcFilter['must'])) {
            $must[] = $this->esWhereHandle('jc_result', ['must' => $jcFilter['must']]);
        }
        if (!empty($jcFilter['should'])) {
            $should[] = $this->esWhereHandle('jc_result', ['should' => $jcFilter['should']]);
        }
        if (!empty($jcFilter['must_not'])) {
            $mustNot[] = $this->esWhereHandle('jc_result', ['must_not' => $jcFilter['must_not']]);
        }

        // 数据查询
        $blSerachService = new ElasticsearchService('bl_serach_2023');
        if (!$should) {
            $params = $blSerachService->clearMust()
                ->queryByMustBatch($must)
                ->queryByMustNotBatch($mustNot)
                ->highlight()
                ->paginate($page, $pageSize)
                ->trackTotalHits()
                ->getParams();
        } else {
            $params = $blSerachService->clearMust()
                ->queryByMustBatch($must)
                ->queryByShouldBatch($should)
                ->queryByMustNotBatch($mustNot)
                ->highlight()
                ->paginate($page, $pageSize)
                ->minimumShouldMatch(1)
                ->trackTotalHits()
                ->getParams();
        }

        // 排序
        $sort = ['AAC01' => 'desc'];
        if (!empty($serachData['sort'])) {
            $sort = [$serachData['sort'][0] => $serachData['sort'][1]];
        }
        $params['body']['sort'] = [$sort];
        // 数据查询
        $restful = app('es')->search($params);

        $fieldList = [];
        foreach ($serachField as $value) {
            if (in_array($value, $patientKey)) {
                $fieldList[] = $value;
            }
        }

        // 返回数据
        $returnData = [
            'restful' => $restful,
            'serach_field' => $fieldList,
            'bllb303' => $bllb303,
            'qtzd' => $qtzd,
            'qtss' => $qtss,
            'bcjl_sxjl' => $bcjlSxjl,
            'tiwen' => ['tiwen_key' => $tiwen_key ?? [], 'tiwen_list' => $twList ?? []],
            'ssjlField' => $ssjlField,
            'qtzdField' => $qtzdField,
            'qtssField' => $qtssField,
            'bcjlSxjlField' => $bcjlSxjlField,
            'bcjl_qw' => $bcjlQw,
            'ss_zt' => $ssZt,
            'bllb' => $bllb,
        ];

        return $returnData;
    }

    /**
     * ES嵌套查询格式处理
     * @param $path
     * @param $data
     * @return array[]
     */
    public function esWhereHandle($path, $data)
    {
        $returnData = [
            'nested' => [
                'path' => $path,
                'query' => [
                    'bool' => $data
                ]
            ]
        ];

        return $returnData;
    }

    /**
     * 体温处理
     * @param $TW_START
     * @param $TW_END
     * @return array
     */
    public static function getTiWen($TW_START = 30, $TW_END = 50)
    {
        $array = [0];
        for ($i = 0; $i <= 50; $i++) {
            $array[] = $i;
        }

        $res = true;
        $twList = [$TW_START, $TW_END];
        while ($res) {
            if ($TW_START && $TW_END) {
                $TW_START += 0.1;
                if ($TW_START <= $TW_END) {
                    $twList[] = $TW_START;
                } else {
                    $res = false;
                }
            } elseif ($TW_START) {
                $TW_START += 0.1;
                if ($TW_START <= 50) {
                    $twList[] = $TW_START;
                } else {
                    $res = false;
                }
            } elseif ($TW_END) {
                $TW_END -= 0.1;
                if ($TW_END >= 30) {
                    $twList[] = $TW_END;
                } else {
                    $res = false;
                }
            }
        }

        $twData = [];
        foreach ($twList as $value) {
            $value = (string)$value;
            $twData[] = $value;
            if (in_array($value, $array)) {
                $twData[] = $value . '.0';
            }
        }

        return $twData;
    }
}
