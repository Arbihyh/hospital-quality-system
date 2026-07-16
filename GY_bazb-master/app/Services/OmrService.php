<?php

namespace App\Services;

use App\Model\CaseRule;
use App\Model\Department;
use App\Model\RuleSetting;
use App\Model\RuleSettingDetail;
use App\Model\Setting;
use App\Model\TableDictMz;
use DateTime;
use Illuminate\Support\Facades\Log;

/**
 * 门诊服务类
 */
class OmrService
{

    const ID = 4257465;
    public $caseRule = [];

    public $ruleSetting = [];
    public $yzzt = [0, 1, 5];
    public $diffHoure = 2;
    public $appealRuleIds = [];
    public $is_sz = 0; // 是否是事中质控
    public $ygjb = ["副主任护师", "副主任检验师", "副主任技师", "副主任医师", "主任医师", "主任护士", "主任检验师", "主治医师", "主管技师", "主管护师", "主管检验师", "主管药师", "医师", "实习医生", "技师", "护士", "护士长", "护师", "检验师", "药师"];


    public function __construct()
    {

        $setting = Setting::query()->where('name', '=', 'diff_houre')->get()->toArray();
        $this->diffHoure = (int)$setting[0]['content'];
        //所有添加的质控规则
        $caseRule = CaseRule::query()->where("status", 1)->get()->toArray();
        $this->caseRule = array_column($caseRule, null, 'id');

        $ruleSetting = RuleSetting::query()->get()->toArray();
        $this->ruleSetting = array_column($ruleSetting, null, 'id');
    }

    /**
     * @param array $info
     * 自定义规则
     */
    public function customizeRule($info = [], $type = "", $isSz = 0)
    {
        $tableDict = TableDictMz::query()->get()->toArray();
        $tableDict = array_column($tableDict, null, 'field');
        $errorNotices = [];
        $query = RuleSetting::query()->where('status', '=', 1);
        if ($type) {
            $query = $query->where("type", $type);
        }
        /* $appealRuleIds = [];
        $appeal = Appeal::query()
            ->where(["quality_type" => 2, "type" => 2, "ZYH" => $info['MED_REC_ID']])->get(["error_id"])
            ->whereIn("status", ['1', '3'])
            ->toArray();
        if ($appeal) {
            $appealRuleIds = array_column($appeal, "error_id");
        }
        // 删除历史质控数据
        $appealRuleIds = array_values(array_filter($appealRuleIds)); */
        if ($isSz == 1 || $isSz == 4) {
            $ruleSetting = $query->where("rule_type", "门诊规则")->where("changjing", "like", "%运行%")->get()->toArray();
        } else {
            $ruleSetting = $query->where("rule_type", "门诊规则")->get()->toArray();
        }

        foreach ($ruleSetting as $r) {
            $basis = [];
            $ruleSettingDetail = RuleSettingDetail::query()->where('rule_id', '=', $r["id"])->get()->toArray();
            $currentRuleId = $r["id"];
            $customBasis = null;
            $tmpFlag = 1; // 规则前置条件是否满足
            $basisData = [];
            // 前置条件处理
            foreach ($ruleSettingDetail as $item) {

                $condition_content = $item["condition_content"];
                if (empty($condition_content)) {
                    continue;
                }

                // 不是前置条件就跳过
                if ($item["is_pre_condition"] != 1) {
                    continue;
                }
                $tmpFlag = 0;
                $condition_content = json_decode($condition_content, true);

                // [{"param1":["入院记录格式化","主诉"],"param2":"111","condition":"包含"}]
                foreach ($condition_content as $content) {
                    //$ruleFlag = 1;
                    //获取参数1中对应的数据
                    $esName = $content["param1"][0]; // es的索引名称
                    $fdata = null;
                    $must = [];
                    $blmc1 = '';
                    $field = '';
                    if ($esName == 'zy_brry' && $content["param1"][1] == 'dqsj') {
                        //fdata取当前时间
                        $fdata = date('Y-m-d H:i:s');
                    } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'BRKS') {
                        $deps = $content["param2"];
                        if (strpos($deps, ',')) {
                            $deps = explode(',', $deps);
                        } else {
                            $deps = [$deps];
                        }
                        $depsid = '';
                        foreach ($deps as $dep) {
                            //根据dep_name获取dep_id
                            $depid = Department::query()->where('dep_name', 'like', '%' . $dep . '%')->get()->toArray();
                            foreach ($depid as $v) {
                                $depsid .= $v['dep_id'] . ',';
                            }
                        }
                        $depsid = rtrim($depsid, ',');
                        $content["param2"] = $depsid;
                        $must[] = ['term' => [$tableDict[$esName]["zyh_field"] => $info["MED_REC_ID"]]];
                        $field = $content["param1"][1];
                    } else {

                        if ($esName == 'zy_brry' && $content["param1"][1] == 'bcjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['BLLB' => 294]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ssjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 306]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'scbc') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 295]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sjyscf') {
                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 50]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sxjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 45]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'qjjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 27]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'zkjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 30]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'jdxx') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 26]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'shscbc') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 42]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sqxj') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 82]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'hzjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['BLLB' => 294]];
                            $must[] = ['match_phrase' => ['BLMC' => '会诊']];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'swbltl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 4302]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ynbltl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 44]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'mdt') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 46]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ydfm') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 3069999]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'jjb') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 511]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'zl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 131]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'pgc') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 30301]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'fm') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 3069999]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ssaqhc') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 30375]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ssaqpg') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 76]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ycczaqhc') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 303751]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sstys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 30308]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'mzzqtys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 77]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sxzltys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 59]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'tsjctys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 60]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'zlcztys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 88]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'mzjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 32977]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sqgzs') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 85]];
                            $field = 'HJNR';
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'qtzqtys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 32901]];
                            $field = 'HJNR';
                        } else {
                            $esName = $content["param1"][0]; // es的索引名称
                            $must[] = ['term' => [$tableDict[$esName]["zyh_field"] => $info["MED_REC_ID"]]];
                            $field = $content["param1"][1];
                        }
                    }
                    $filterData = [];
                    try {
                        $esService = new ElasticsearchService($esName);
                        $params = $esService->clearMust()
                            ->queryByMustBatch($must)
                            ->paginate(1, 10000)
                            ->getParams();
                        $res = app('es')->search($params);
                        $filterData = $esService->getDataByEs($res);
                    } catch (\Throwable $e) {
                        continue;
                    }

                    if (empty($filterData) || empty($filterData[0]) || empty($filterData[0][0])) {
                        continue;
                    }
                    // $content["param1"][1] es数据中的字段
                    //判断filterData，如果filterData只有一条数据,则fdata为该条数据,如果有多条数据,则fdata为数组
                    if (count($filterData[0]) > 1) {
                        //开循环赋值
                        foreach ($filterData[0] as $val) {
                            $fdata[] = $val[$field];
                        }
                    } else {
                        $fdata = $filterData[0][0][$field] ?? "";
                        $blmc1 = $filterData[0][0]['BLMC'] ?? "";
                    }

                    $content2 = $content["param2"];
                    $condition = $content["condition"];
                    $conditionMet = false;

                    if ($fdata === '0' || $fdata === 0) {
                        $fdata = 0;
                    }

                    // 添加数据验证和错误处理
                    if (empty($fdata) && $fdata !== 0 &&  $content["param1"][0] != 'zy_brry') {
                        $tmpFlag = 0;
                    }

                    //范围 100-200 减号分割,可能会存在100-或者-200的情况,需要处理,如果是100-就是>=100,如果是-200就是<=200
                    /**
                     * 时效规则待定
                     *
                     * [{"param1":["bllb292_2023","XBS"],"param2":"患者10年前无明显诱因下出现多尿","condition":"包含"},{"param1":["bllb303_2023","SSJSSJ"],"param2":"bllb303_2023.SSJSSJ-bllb303_2023.SSJSSJ+24","condition":"时效"}]
                     *
                     * 时效的情况下param2这个索引可能会和上面不一个,所以需要这样传,点前面是索引,后面是字段,然后也使用减号分割,后面的字段如果需要加时间,就像示例一样+24这样
                     *
                     * 时间校验规则
                     * 2.6 时间校验【月】【日】【时】【分】【秒】
                     * 判断选择的字段是否满足到月,日,时,分,秒  比如2024就不满足到月,2024-01就满足到月,以此类推,写分开的五个校验规则
                     * 时间校验【月】
                     * 时间校验【日】
                     * 时间校验【时】
                     * 时间校验【分】
                     * 时间校验【秒】
                     *
                     */

                    //判断是否是范围条件
                    if ($condition == "范围") {

                        $content2 = explode("-", $content2);

                        // 确保数值比较的类型一致
                        $fdata = is_numeric($fdata) ? floatval($fdata) : $fdata;

                        $content2[0] = is_numeric($content2[0]) ? floatval($content2[0]) : $content2[0];
                        $content2[1] = is_numeric($content2[1]) ? floatval($content2[1]) : $content2[1];

                        // 使用 isset() 而不是 empty() 来检查
                        if (isset($content2[0]) && $content2[0] !== '' && isset($content2[1]) && $content2[1] !== '') {
                            $min = is_numeric($content2[0]) ? floatval($content2[0]) : $content2[0];
                            $max = is_numeric($content2[1]) ? floatval($content2[1]) : $content2[1];
                            $conditionMet = $fdata >= $min && $fdata <= $max;
                        } else if (isset($content2[0]) && $content2[0] !== '') {
                            $min = is_numeric($content2[0]) ? floatval($content2[0]) : $content2[0];
                            $conditionMet = $fdata >= $min;
                        } else if (isset($content2[1]) && $content2[1] !== '') {
                            $max = is_numeric($content2[1]) ? floatval($content2[1]) : $content2[1];
                            $conditionMet = $fdata <= $max;
                        } else {
                            continue;
                        }
                    } // 重复率
                    elseif ($condition == "重复率") {
                        // 获取第二个参数的值
                        $p2 = explode(".", $content2);
                        $es = new ElasticsearchService($p2[0]);
                        $params = $es->clearMust()
                            ->queryByMust(['term' => [$tableDict[$p2[0]]["zyh_field"] => $info['MED_REC_ID']]])
                            ->getParams();
                        $res = app('es')->search($params);
                        $filData = $es->getDataByEs($res);

                        if (empty($filData) || empty($filData[0]) || empty($filData[0][0])) {
                            continue;
                        }

                        $value2 = $filData[0][0][$p2[1]];
                        if (is_array($fdata)) {
                            $fdata = array_reverse($fdata);
                            $fdata = $fdata[0];
                        }

                        // 获取fdata和value2的字数（不包含标点符号）
                        $fdataChars = preg_replace('/[^\p{L}\p{N}]/u', '', $fdata);
                        $fdataCharCount = mb_strlen($fdataChars, 'UTF-8');

                        $value2Chars = preg_replace('/[^\p{L}\p{N}]/u', '', $value2);
                        $value2CharCount = mb_strlen($value2Chars, 'UTF-8');

                        // 谁大谁做分母，两个字数相除如果小于0.85就跳过
                        $maxCharCount = max($fdataCharCount, $value2CharCount);
                        $minCharCount = min($fdataCharCount, $value2CharCount);

                        if ($maxCharCount > 0 && ($minCharCount / $maxCharCount) < 0.85) {
                            continue;
                        }

                        $bcContentArray = preg_split('//u', $fdata, -1, PREG_SPLIT_NO_EMPTY);
                        $bcContentArray = array_unique(array_filter($bcContentArray));

                        $bcContentArray1 = preg_split('//u', $value2, -1, PREG_SPLIT_NO_EMPTY);
                        $bcContentArray1 = array_unique(array_filter($bcContentArray1));
                        // 获取交集
                        $res = array_intersect($bcContentArray, $bcContentArray1);
                        $subtract_value = $content["subtract_value"];
                        if (count($bcContentArray) > 0 && count($res) / count($bcContentArray) * 100 < intval($subtract_value)) {
                            $conditionMet = true;
                        }
                    } elseif ($condition == "时间校验【月】") {
                        //时间校验是否到月,fdata时需要校验的数据
                        // 处理多种可能的时间格式
                        $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                        if (strlen($fdata) == 4) {
                            // 只有年份 "2024"
                            $conditionMet = false;
                        } else {
                            try {
                                // 统一处理斜杠和横杠
                                $fdata = str_replace('/', '-', $fdata);
                                $date = new DateTime($fdata);
                                // 提取年月部分 (前7个字符)
                                $yearMonth = substr($fdata, 0, 7);
                                // 验证年月格式
                                $conditionMet = (bool)preg_match('/^\d{4}-([0][1-9]|1[0-2])$/', $yearMonth);
                            } catch (\Exception $e) {
                                $conditionMet = false;
                            }
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } elseif ($condition == "时间校验【日】") {
                        try {
                            $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                            $fdata = str_replace('/', '-', $fdata);
                            $date = new DateTime($fdata);
                            $yearMonthDay = substr($fdata, 0, 10);
                            $conditionMet = (bool)preg_match('/^\d{4}-([0][1-9]|1[0-2])-([0][1-9]|[12][0-9]|3[01])$/', $yearMonthDay);
                        } catch (\Exception $e) {
                            $conditionMet = false;
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } elseif ($condition == "时间校验【时】") {
                        try {
                            $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                            $fdata = str_replace('/', '-', $fdata);
                            $date = new DateTime($fdata);
                            $withHour = substr($fdata, 0, 13);
                            $conditionMet = (bool)preg_match('/^\d{4}-([0][1-9]|1[0-2])-([0][1-9]|[12][0-9]|3[01]) ([01][0-9]|2[0-3])$/', $withHour);
                        } catch (\Exception $e) {
                            $conditionMet = false;
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } elseif ($condition == "时间校验【分】") {
                        try {
                            $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                            $fdata = str_replace('/', '-', $fdata);
                            $withMinute = mb_substr($fdata, 0, 16);
                            $conditionMet = (bool)preg_match('/^\d{4}-([0][1-9]|1[0-2])-([0][1-9]|[12][0-9]|3[01]) ([01][0-9]|2[0-3]):([0-5][0-9])$/', $withMinute);
                        } catch (\Exception $e) {
                            $conditionMet = false;
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } elseif ($condition == "时间校验【秒】") {
                        try {
                            $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                            $fdata = str_replace('/', '-', $fdata);
                            //$date = new DateTime($fdata);
                            $withSecond = substr($fdata, 0, 19);
                            $conditionMet = (bool)preg_match('/^\d{4}-([0][1-9]|1[0-2])-([0][1-9]|[12][0-9]|3[01]) ([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/', $withSecond);
                        } catch (\Exception $e) {
                            $conditionMet = false;
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } elseif ($condition == "时效") {

                        // 初始化条件匹配结果为false
                        $conditionMet = false;

                        try {
                            // 获取参数
                            $param1 = $content["param1"]; // [索引, 字段]
                            $param2 = $content["param2"]; // 条件值（可能包含逗号分隔的多值）
                            $param3 = $content["param3"]; // [索引, 时间字段]
                            $param4 = $content["param4"]; // 时间范围检查配置数组

                            // 获取索引和字段
                            $indexName = $param1[0];
                            $fieldName = $param1[1];

                            // 查询条件值可能包含逗号分隔的多个值
                            $conditionValues = [];
                            if (strpos($param2, ",") !== false) {
                                $conditionValues = explode(",", $param2);
                            } else {
                                $conditionValues = [$param2];
                            }

                            // 构建查询条件
                            $esService = new ElasticsearchService($indexName);
                            $params = $esService->clearMust()
                                ->queryByMust(['term' => [$tableDict[$indexName]["zyh_field"] => $info['MED_REC_ID']]]);

                            // 处理字段条件，支持多值查询
                            $shouldTerms = [];
                            foreach ($conditionValues as $value) {
                                $shouldTerms[] = ['term' => [$fieldName => trim($value)]];
                            }

                            if (count($shouldTerms) == 1) {
                                $params = $params->queryByMust($shouldTerms[0]);
                            } else if (count($shouldTerms) > 1) {
                                $params = $params->queryByMust(['bool' => ['should' => $shouldTerms, 'minimum_should_match' => 1]]);
                            }

                            $params = $params->getParams();
                            $res = app('es')->search($params);
                            $filterData = $esService->getDataByEs($res);

                            // 如果没有找到匹配的记录，则跳过
                            if (empty($filterData) || empty($filterData[0])) {
                                continue;
                            }

                            // 获取时间字段数据
                            $timeFieldName = $param3[1];
                            $timeRecords = [];

                            foreach ($filterData[0] as $record) {
                                if (!empty($record[$timeFieldName])) {
                                    $timeRecords[] = [
                                        'time' => $record[$timeFieldName],
                                        'record' => $record
                                    ];
                                }
                            }

                            // 如果没有有效的时间记录，则跳过
                            if (empty($timeRecords)) {
                                continue;
                            }

                            // 遍历每个时间记录
                            foreach ($timeRecords as $timeRecord) {
                                $recordTime = strtotime($timeRecord['time']);
                                $allConditionsMet = true;

                                // 检查每个param4配置
                                foreach ($param4 as $p4) {
                                    $kssj = $p4['kssj']; // 开始时间字段
                                    $jssj = $p4['jssj']; // 结束时间字段
                                    $sjjg = $p4['sjjg']; // 时间间隔(小时)
                                    $indexField = explode('.', $p4['index'])[1]; // 需要检索的字段
                                    $condition1 = $p4['condition1']; // 需要包含的关键词
                                    $condition2 = $p4['condition2']; // 需要排除的关键词

                                    // 解析索引和字段
                                    list($checkIndexName, $checkFieldName) = explode('.', $kssj);

                                    // 构建查询条件
                                    $checkEsService = new ElasticsearchService($checkIndexName);
                                    $checkParams = $checkEsService->clearMust()
                                        ->queryByMust(['term' => [$tableDict[$checkIndexName]["zyh_field"] => $info['MED_REC_ID']]]);

                                    // 如果有包含条件，添加到查询
                                    if (!empty($condition1)) {
                                        // 如果包含逗号，则按照逗号分隔
                                        if (strpos($condition1, ",") !== false) {
                                            $condition1Values = explode(',', $condition1);
                                            $shouldTerms1 = [];
                                            foreach ($condition1Values as $val) {
                                                $val = trim($val);
                                                if (!empty($val)) {
                                                    $shouldTerms1[] = ['match' => [$indexField => $val]];
                                                }
                                            }
                                            if (count($shouldTerms1) > 0) {
                                                $checkParams = $checkParams->queryByMust(['bool' => ['should' => $shouldTerms1, 'minimum_should_match' => 1]]);
                                            }
                                        } else {
                                            $checkParams = $checkParams->queryByMust(['match' => [$indexField => $condition1]]);
                                        }
                                    }

                                    // 如果有排除条件，添加到查询
                                    if (!empty($condition2)) {
                                        // 如果包含逗号，则按照逗号分隔
                                        if (strpos($condition2, ",") !== false) {
                                            $condition2Values = explode(',', $condition2);
                                            foreach ($condition2Values as $val) {
                                                $val = trim($val);
                                                if (!empty($val)) {
                                                    $checkParams = $checkParams->queryByMustNot(['match' => [$indexField => $val]]);
                                                }
                                            }
                                        } else {
                                            $checkParams = $checkParams->queryByMustNot(['match' => [$indexField => $condition2]]);
                                        }
                                    }

                                    $checkParams = $checkParams->getParams();
                                    $checkRes = app('es')->search($checkParams);
                                    $checkData = $checkEsService->getDataByEs($checkRes);

                                    // 如果没有找到匹配的检查记录，则标记条件未满足
                                    if (empty($checkData) || empty($checkData[0])) {
                                        $allConditionsMet = false;
                                        break;
                                    }

                                    // 检查每个检查记录，是否在时间范围内
                                    $checkConditionMet = false;
                                    foreach ($checkData[0] as $checkRecord) {
                                        // 获取开始和结束时间
                                        list($kssjIndex, $kssjField) = explode('.', $kssj);
                                        list($jssjIndex, $jssjField) = explode('.', $jssj);

                                        $startTime = strtotime($checkRecord[$kssjField]);
                                        $endTime = strtotime($checkRecord[$jssjField]);

                                        // 如果有时间间隔，应用到结束时间
                                        if (!empty($sjjg)) {
                                            $endTime = $startTime + (intval($sjjg) * 3600); // 转换为秒
                                        }

                                        // 检查记录时间是否在范围内
                                        if ($recordTime >= $startTime && $recordTime <= $endTime) {
                                            $checkConditionMet = true;
                                            break;
                                        }
                                    }

                                    // 如果当前param4条件未满足，则所有条件未满足
                                    if (!$checkConditionMet) {
                                        $allConditionsMet = false;
                                        break;
                                    }
                                }

                                // 如果所有条件都满足，则满足整体条件
                                if ($allConditionsMet) {
                                    $conditionMet = true;
                                    break;
                                }
                            }
                        } catch (\Exception $e) {
                            // 记录错误日志
                            Log::error('时效规则执行错误', [
                                'error' => $e->getMessage(),
                                'content' => $content,
                                'info' => $info
                            ]);
                            continue;
                        }
                    } elseif ($condition == "包含") {
                        $conditionMet = false; // 默认设置为 false
                        //新增delete_field参数，先删除对应字段，可能会包含逗号
                        $delete_field = $content['delete_field'] ?? '';
                        //如果fdata为数组,则循环
                        if (is_array($fdata)) {
                            foreach ($fdata as $val) {
                                if ($conditionMet) {
                                    break;
                                }

                                $fdata1 = $val;

                                // 添加到basisData,保存es索引名和字段名和对应的数据

                                if (!empty($delete_field)) {
                                    //如果包含逗号,则按照逗号分隔
                                    if (strpos($delete_field, ",") !== false) {
                                        $delete_field_array = explode(",", $delete_field);
                                        foreach ($delete_field_array as $val) {
                                            $fdata1 = str_replace($val, '', $fdata1);
                                        }
                                    } else {
                                        $fdata1 = str_replace($delete_field, '', $fdata1);
                                    }
                                }
                                $v = '';
                                if (strpos($content2, ",") !== false) {
                                    $content2Array = explode(",", $content2);
                                    foreach ($content2Array as $value) {
                                        //$value = trim($value); // 去除可能的空格
                                        if (empty($value)) {
                                            continue;
                                        }
                                        if (strpos($fdata1, $value) !== false) {
                                            $conditionMet = true;
                                            $v = $value;
                                            break;
                                        }
                                        //Log::info('包含', ['value' => $value, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                                    }
                                } else {
                                    // 如果content2不包含逗号,则直接判断是否包含
                                    $conditionMet = strpos($fdata1, $content2) !== false;
                                    if ($conditionMet) {
                                        $v = $content2;
                                    }
                                    //Log::info('包含', ['value' => $content2, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                                }

                                $basisData[] = [
                                    'es_name' => $content["param1"][0],
                                    'field_name' => $content["param1"][1],
                                    'fdata' => $blmc1,
                                    'blmc' => $fdata['blmc'] ?? '',
                                    'conditionMet' => $conditionMet,
                                    'condition' => $condition
                                ];
                            }
                        } else {
                            if (!empty($delete_field)) {
                                //如果包含逗号,则按照逗号分隔
                                if (strpos($delete_field, ",") !== false) {
                                    $delete_field_array = explode(",", $delete_field);
                                    foreach ($delete_field_array as $val) {
                                        $fdata = str_replace($val, '', $fdata);
                                    }
                                } else {
                                    $fdata = str_replace($delete_field, '', $fdata);
                                }
                            }
                            $v = '';
                            if (strpos($content2, ",") !== false) {
                                $content2Array = explode(",", $content2);
                                foreach ($content2Array as $value) {
                                    //$value = trim($value); // 去除可能的空格
                                    if (empty($value)) {
                                        continue;
                                    }
                                    //Log::info('测试包含', ['fdata-v' => $fdata, 'value-v' => $value]);
                                    if (strpos($fdata, $value) !== false) {
                                        $conditionMet = true;
                                        $v = $value;
                                        break;
                                    }
                                    //Log::info('包含', ['value' => $value, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                                }
                            } else {
                                // 如果content2不包含逗号,则直接判断是否包含
                                $conditionMet = strpos($fdata, (string)$content2) !== false;
                                if ($conditionMet) {
                                    $v = $content2;
                                }
                                //Log::info('包含', ['value' => $content2, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                            }
                            // 添加到basisData,保存es索引名和字段名和对应的数据
                            //Log::info('ceshi---------',['fdata'=>$fdata,'condition'=>$condition,'content'=>$content2,'met'=>$conditionMet]);
                            //if ($conditionMet) {
                            $basisData[] = [
                                'es_name' => $content["param1"][0],
                                'field_name' => $content["param1"][1],
                                'fdata' => $blmc1,
                                'blmc' => $blmc ?? '',
                                'conditionMet' => $conditionMet,
                                'condition' => $condition
                            ];
                            //}
                        }
                    } elseif ($condition == "不包含") { //包含和不包含分开
                        // 其他条件的判断
                        //修改一下.如果是包含不包含,就判断content2是否包含英文的逗号,如果包含就按照逗号分隔,然后判断是否包含分隔后的每一个值,满足一个就是true,如果没有逗号就正常判断
                        $conditionMet = false; // 默认设置为 false

                        //新增delete_field参数，先删除对应字段，可能会包含逗号
                        $delete_field = $content['delete_field'] ?? '';
                        //如果fdata为数组,则循环
                        if (is_array($fdata)) {
                            foreach ($fdata as $val) {
                                if ($conditionMet) {
                                    break;
                                }


                                $fdata1 = $val;


                                if (!empty($delete_field)) {
                                    //如果包含逗号,则按照逗号分隔
                                    if (strpos($delete_field, ",") !== false) {
                                        $delete_field_array = explode(",", $delete_field);
                                        foreach ($delete_field_array as $val) {
                                            $fdata1 = str_replace($val, '', $fdata1);
                                        }
                                    } else {
                                        $fdata1 = str_replace($delete_field, '', $fdata1);
                                    }
                                }
                                if (strpos($content2, ",") !== false) {
                                    $content2Array = explode(",", $content2);
                                    foreach ($content2Array as $value) {
                                        if (empty($value)) {
                                            continue;
                                        }
                                        if (strpos($fdata1, $value) === false) {
                                            $conditionMet = true;
                                        } else {
                                            $conditionMet = false;
                                            break;
                                        }
                                    }
                                } else {
                                    // 如果content2不包含逗号,则直接判断是否不包含
                                    $conditionMet = strpos($fdata1, $content2) === false;
                                    //Log::info('不包含', ['value' => $content2, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                                }
                                //if ($conditionMet) {
                                $basisData[] = [
                                    'es_name' => $content["param1"][0],
                                    'field_name' => $content["param1"][1],
                                    'fdata' => $blmc1,
                                    'blmc' => $fdata['blmc'] ?? '',
                                    'conditionMet' => $conditionMet,
                                    'condition' => $condition
                                ];
                                //}
                            }
                        } else {
                            if (!empty($delete_field)) {
                                //如果包含逗号,则按照逗号分隔
                                if (strpos($delete_field, ",") !== false) {
                                    $delete_field_array = explode(",", $delete_field);
                                    foreach ($delete_field_array as $val) {
                                        $fdata = str_replace($val, '', $fdata);
                                    }
                                } else {
                                    $fdata = str_replace($delete_field, '', $fdata);
                                }
                            }

                            if (strpos($content2, ",") !== false) {
                                $content2Array = explode(",", $content2);
                                foreach ($content2Array as $value) {
                                    //Log::info('不包含', ['value' => $value, 'fdata' => $fdata]);
                                    if (empty($value)) {
                                        continue;
                                    }
                                    if (strpos($fdata, $value) === false) {
                                        $conditionMet = true;
                                    } else {
                                        $conditionMet = false;
                                        break;
                                    }
                                }
                            } else {
                                // 如果content2不包含逗号,则直接判断是否不包含
                                $conditionMet = strpos($fdata, $content2) === false;
                                //Log::info('不包含', ['value' => $content2, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                            }
                            //Log::info('ceshi---------',['fdata'=>$fdata,'condition'=>$condition,'content'=>$content2,'met'=>$conditionMet]);
                            //if ($conditionMet) {
                            $basisData[] = [
                                'es_name' => $content["param1"][0],
                                'field_name' => $content["param1"][1],
                                'fdata' => $blmc1,
                                'blmc' => $blmc ?? '',
                                'conditionMet' => $conditionMet,
                                'condition' => $condition
                            ];
                            //}
                        }
                    } elseif ($condition == "病程记录关联") {
                        // 初始化条件匹配结果为false
                        $conditionMet = false;

                        // 获取病程记录数据
                        $indexName = $content["param1"][0]; // 索引名称，例如 bl01_202303
                        $mblbField = $content["param1"][1]; // MBLB字段
                        $blbhField = $content["param1"][2]; // BLBH字段
                        //可能会包含逗号
                        if (strpos($content["param2"], ",") !== false) {
                            $mblbValues = explode(",", $content["param2"]); // 可能包含逗号分隔的多个值
                        } else {
                            $mblbValues = [$content["param2"]];
                        }

                        // 查询条件
                        $must = [];
                        $must[] = ['term' => [$tableDict[$indexName]["zyh_field"] => $info['MED_REC_ID']]];

                        // 处理MBLB条件，支持多值查询
                        $shouldTerms = [];
                        foreach ($mblbValues as $value) {
                            $shouldTerms[] = ['term' => [$mblbField => trim($value)]];
                        }

                        if (count($shouldTerms) == 1) {
                            $must[] = $shouldTerms[0];
                        } else if (count($shouldTerms) > 1) {
                            $must[] = ['bool' => ['should' => $shouldTerms, 'minimum_should_match' => 1]];
                        }

                        // 执行ES查询
                        try {
                            $esService = new ElasticsearchService($indexName);
                            $params = $esService->clearMust();

                            foreach ($must as $m) {
                                $params = $params->queryByMust($m);
                            }

                            $params = $params->getParams();
                            $res = app('es')->search($params);
                            $filterData = $esService->getDataByEs($res);

                            // 如果没有找到匹配的记录，则跳过
                            if (empty($filterData) || empty($filterData[0])) {
                                continue;
                            }

                            // 保存first_blsy_time和BLBH信息
                            $blsyTimeField = $content["param3"][1]; // fisrt_blsy_time字段
                            $blRecords = [];

                            foreach ($filterData[0] as $record) {
                                if (!empty($record[$blsyTimeField]) && !empty($record[$blbhField])) {
                                    $blRecords[] = [
                                        'blsy_time' => $record[$blsyTimeField],
                                        'blbh' => $record[$blbhField]
                                    ];
                                }
                            }

                            // 如果没有有效的病程记录，则跳过
                            if (empty($blRecords)) {
                                continue;
                            }

                            // 获取param4参数
                            $param4 = $content["param4"];
                            $matchedRecords = [];

                            // 处理每个param4条件,param4只有一条,只是根据里面的条件和所有有可能查出多条,所以不使用foreach
                            foreach ($param4 as $p4) {
                                $kssj = $p4['kssj']; // 开始时间字段
                                $jssj = $p4['jssj']; // 结束时间字段
                                $sjjg = $p4['sjjg']; // 时间间隔(小时)
                                $params1 = $p4['params1']; // 需要获取的字段
                                $condition1 = $p4['condition1']; // 需要包含的关键词
                                $condition2 = $p4['condition2']; // 需要排除的关键词
                                $zj = $p4['zj']; // 关联字段

                                // 解析索引和字段
                                list($indexName, $fieldName) = explode('.', $kssj);
                                list($endTimeIndex, $endTimeField) = explode('.', $jssj);

                                // 处理包含条件,可能会包含逗号
                                $shouldTerms1 = [];
                                if (!empty($condition1)) {
                                    // 如果包含逗号,则按照逗号分隔
                                    if (strpos($condition1, ",") !== false) {
                                        $condition1Values = explode(',', $condition1);
                                        foreach ($condition1Values as $val) {
                                            $val = trim($val);
                                            if (!empty($val)) {
                                                $shouldTerms1[] = ['match' => ['bgd' => $val]];
                                            }
                                        }
                                    } else {
                                        $shouldTerms1[] = ['match' => ['bgd' => $condition1]];
                                    }
                                }

                                // 处理排除条件
                                $mustNotTerms = [];
                                if (!empty($condition2)) {
                                    // 如果包含逗号,则按照逗号分隔
                                    if (strpos($condition2, ",") !== false) {
                                        $condition2Values = explode(',', $condition2);
                                        foreach ($condition2Values as $val) {
                                            $val = trim($val);
                                            if (!empty($val)) {
                                                $mustNotTerms[] = ['match' => ['bgd' => $val]];
                                            }
                                        }
                                    } else {
                                        $mustNotTerms[] = ['match' => ['bgd' => $condition2]];
                                    }
                                }

                                // 构建查询
                                $esService = new ElasticsearchService($indexName);
                                $params = $esService->clearMust();

                                // 添加查询条件
                                $params = $params->queryByMust(['term' => [$tableDict[$indexName]["zyh_field"] => $info['MED_REC_ID']]]);

                                // 添加包含条件
                                if (!empty($shouldTerms1)) {
                                    if (count($shouldTerms1) == 1) {
                                        $params = $params->queryByMust($shouldTerms1[0]);
                                    } else {
                                        $params = $params->queryByMust(['bool' => ['should' => $shouldTerms1, 'minimum_should_match' => 1]]);
                                    }
                                }

                                // 添加排除条件
                                if (!empty($mustNotTerms)) {
                                    foreach ($mustNotTerms as $notTerm) {
                                        $params = $params->queryByMustNot($notTerm);
                                    }
                                }

                                $params = $params->getParams();
                                $res = app('es')->search($params);
                                $checkData = $esService->getDataByEs($res);
                                // 如果没有找到匹配的记录，则继续下一个
                                if (empty($checkData) || empty($checkData[0])) {
                                    continue;
                                }

                                // 对于每条检查记录，检查是否有病程记录在时间范围内
                                foreach ($checkData[0] as $checkRecord) {
                                    // 获取开始和结束时间
                                    $startTime = strtotime($checkRecord[$fieldName]);
                                    $endTime = strtotime($checkRecord[$endTimeField]);

                                    // 如果有时间间隔，加到结束时间上
                                    if (!empty($sjjg)) {
                                        $endTime = $startTime + (intval($sjjg) * 3600); // 转换为秒
                                    }

                                    // 检查每条病程记录是否在时间范围内
                                    foreach ($blRecords as $blRecord) {
                                        $blsyTime = strtotime($blRecord['blsy_time']);

                                        // 检查病程记录时间是否在范围内
                                        if ($blsyTime >= $startTime && $blsyTime <= $endTime) {
                                            // 保存匹配记录的信息
                                            $matchedRecords[] = [
                                                'blbh' => $blRecord['blbh'],
                                                'checkBLBH' => isset($checkRecord[$params1]) ? $checkRecord[$params1] : '',
                                                'relation_type' => $content["param5"] // gl或bgl
                                            ];
                                        }
                                    }
                                }
                            }

                            // 如果找到了匹配的记录，检查是否满足关联条件
                            if (!empty($matchedRecords)) {
                                $param5 = $content["param5"]; // gl(关联)或bgl(不关联)
                                $param6 = $content["param6"]; // [索引, 包含条件, 不包含条件]

                                $indexName = $param6[0];
                                list($indexName, $hjnrField) = explode('.', $indexName);
                                //如果包含逗号,则按照逗号分隔
                                if (strpos($param6[1], ",") !== false) {
                                    $includeTerms = explode(',', $param6[1]);
                                } else {
                                    $includeTerms = [$param6[1]];
                                }
                                if (strpos($param6[2], ",") !== false) {
                                    $excludeTerms = explode(',', $param6[2]);
                                } else {
                                    $excludeTerms = [$param6[2]];
                                }
                                // 处理每条匹配记录
                                foreach ($matchedRecords as $record) {
                                    // 构建查询条件
                                    $esService = new ElasticsearchService($indexName);
                                    $params = $esService->clearMust();

                                    //添加主键条件
                                    $params = $params->queryByMust(['term' => [$tableDict[$indexName]["zyh_field"] => $info['MED_REC_ID']]]);

                                    // 如果是关联(gl)模式，添加最开始保存的blbh条件，可能是多条
                                    if ($param5 == 'gl' && !empty($record['blbh'])) {
                                        $params = $params->queryByMust(['term' => [$blbhField => $record['blbh']]]);
                                    }

                                    $params = $params->getParams();
                                    $res = app('es')->search($params);
                                    $hjnrData = $esService->getDataByEs($res);

                                    // 如果没有找到病历内容，则继续下一个
                                    if (empty($hjnrData) || empty($hjnrData[0]) || empty($hjnrData[0][0]) || empty($hjnrData[0][0][$hjnrField])) {
                                        continue;
                                    }

                                    $hjnr = $hjnrData[0][0][$hjnrField];
                                    $passInclude = true;
                                    $passExclude = true;

                                    // 检查是否包含需要的关键词
                                    if (!empty($includeTerms)) {
                                        $passInclude = false;
                                        //是否是数组
                                        if (is_array($includeTerms)) {
                                            foreach ($includeTerms as $term) {
                                                $term = trim($term);
                                                if (!empty($term) && strpos($hjnr, $term) !== false) {
                                                    $passInclude = true;
                                                    break;
                                                }
                                            }
                                        } else {
                                            if (!empty($includeTerms) && strpos($hjnr, $includeTerms) !== false) {
                                                $passInclude = true;
                                            }
                                        }
                                    }

                                    // 检查是否不包含需要排除的关键词
                                    if (!empty($excludeTerms)) {
                                        $passExclude = true;
                                        //是否是数组
                                        if (is_array($excludeTerms)) {
                                            foreach ($excludeTerms as $term) {
                                                $term = trim($term);
                                                if (!empty($term) && strpos($hjnr, $term) !== false) {
                                                    $passExclude = false;
                                                    break;
                                                }
                                            }
                                        } else {
                                            if (!empty($excludeTerms) && strpos($hjnr, $excludeTerms) !== false) {
                                                $passExclude = false;
                                            }
                                        }
                                    }

                                    // 如果同时满足包含和排除条件，则条件满足
                                    if ($passInclude && $passExclude) {
                                        $conditionMet = true;
                                        break; // 只要有一条记录满足条件就可以
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            // 记录错误日志
                            Log::error('病程记录关联规则执行错误', [
                                'error' => $e->getMessage(),
                                'content' => $content,
                                'info' => $info
                            ]);
                            continue;
                        }
                    } elseif ($condition == "减") {
                        // 获取第二个参数的值
                        $p2 = [$content['subtract_param'][0], $content['subtract_param'][1]];
                        $value2 = null;
                        if ($p2[0] == 'zy_brry' && $p2[1] == 'dqsj') {
                            $value2 = date('Y-m-d H:i:s');
                        } else {
                            $es = new ElasticsearchService($p2[0]);
                            $params = $es->clearMust()
                                ->queryByMust(['term' => [$tableDict[$p2[0]]["zyh_field"] => $info['MED_REC_ID']]])
                                ->getParams();
                            $res = app('es')->search($params);
                            $filData = $es->getDataByEs($res);

                            if (empty($filData) || empty($filData[0]) || empty($filData[0][0])) {
                                continue;
                            }

                            $value2 = $filData[0][0][$p2[1]];
                        }
                        // 计算差值
                        if ($content['subtract_value_type'] == 'hour' || $content['subtract_value_type'] == 'minute') {
                            // 时间差值计算
                            $time1 = strtotime($fdata);
                            $time2 = strtotime($value2);
                            $diffSeconds = abs($time1 - $time2);

                            if ($content['subtract_value_type'] == 'hour') {
                                $diff = $diffSeconds / 3600; // 转换为小时
                            } else {
                                $diff = $diffSeconds / 60; // 转换为分钟
                            }
                        } else {
                            // 数值差值计算
                            $diff = abs(floatval($fdata) - floatval($value2));
                        }

                        // 根据比较条件判断
                        switch ($content['subtract_condition']) {
                            case 'gt':
                                $conditionMet = $diff > floatval($content['subtract_value']);
                                break;
                            case 'lt':
                                $conditionMet = $diff < floatval($content['subtract_value']);
                                break;
                            case 'eq':
                                $conditionMet = abs($diff - floatval($content['subtract_value'])) < 0.000001; // 浮点数比较
                                break;
                            default:
                                $conditionMet = false;
                        }
                    } else {
                        $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                        //如果是6岁8月这种格式，转换成6.8这样的数字
                        if (is_string($fdata) && preg_match('/(\d+)岁(\d+)月/', $fdata, $matches)) {
                            $years = intval($matches[1]);
                            $months = intval($matches[2]);
                            $fdata = $years + ($months / 12); // 转换为类似6.8的格式
                        }
                        //如果是45岁这种格式，转换成45这样的数字
                        if (is_string($fdata) && preg_match('/(\d+)岁/', $fdata, $matches)) {
                            $fdata = intval($matches[1]);
                        }
                        $conditionMet = ($condition == "等于" && $fdata == $content2) ||
                            ($condition == "不等于" && $fdata != $content2) ||
                            ($condition == "大于" && $fdata > $content2) ||
                            ($condition == "小于" && $fdata < $content2) ||
                            ($condition == "大于等于" && $fdata >= $content2) ||
                            ($condition == "小于等于" && $fdata <= $content2) ||
                            ($condition == "不为空" && !empty($fdata));
                        //Log::info('ceshi---------',['fdata'=>$fdata,'condition'=>$condition,'content'=>$content2,'met'=>$conditionMet]);
                    }


                    if (!$conditionMet) {
                        $tmpFlag = 0;
                        if ($item['condition_relation'] == 1) {
                            break;
                        }
                    } else {
                        $tmpFlag = 1;

                        if ($item['condition_relation'] == 2) {
                            //根据条件判断不满足就删除对应的basisData
                            break;
                        }
                    }
                    //Log::info('tmpFlag', ['tmpFlag' . $item['rule_id'] => $tmpFlag]);
                }
                //有一个前置不满足就跳出循环
                if ($tmpFlag == 0) {
                    break;
                }
            }

            //Log::info('tmpFlag', ['tmpFlag' => $tmpFlag]);

            if ($tmpFlag == 0) {
                continue;
            }



            // 免审处理
            foreach ($ruleSettingDetail as $item) {
                if ($item["condition_type"] == 2) {
                    continue 2;
                }
            }

            // 处理实际的规则
            $ruleFlag = 1;
            $preWarningTime = 0;
            $customMsg = null;
            $qtTime = null;
            foreach ($ruleSettingDetail as $item) {

                //$ruleFlag = 1;
                $condition_content = $item["condition_content"];
                $detailstatus = $item['detail_status'];
                if (empty($condition_content)) {
                    continue;
                }
                // 前置条件就跳过
                if ($item["is_pre_condition"] == 1) {
                    continue;
                }
                if (!empty($item['custom_basis'])) {
                    $customBasis = json_decode($item['custom_basis'], true);
                }
                $condition_content = json_decode($condition_content, true);
                if ($item['pre_warning_time'] > 0) {
                    $preWarningTime = $item['pre_warning_time'];
                    $customMsg = json_decode($item['custom_msg'], true);
                    if (!empty($customMsg)) {
                        $esName = $customMsg['param1'][0];
                        $esService = new ElasticsearchService($esName);
                        $params = $esService->clearMust()
                            ->queryByMust(['term' => [$tableDict[$esName]["zyh_field"] => $info['MED_REC_ID']]])
                            ->getParams();
                        $res = app('es')->search($params);
                        $filterData = $esService->getDataByEs($res);
                        if ($filterData && $filterData[0] && $filterData[0][0]) {
                            $qtTime = $filterData[0][0][$customMsg['param1'][1]];
                        }
                    }
                }

                // [{"param1":["入院记录格式化","主诉"],"param2":"111","condition":"包含"}]
                foreach ($condition_content as $content) {
                    $ruleFlag = 1;
                    if (empty($content["param1"])) {
                        continue;
                    }
                    $esName = $content["param1"][0]; // es的索引名称
                    $fdata = null;
                    $must = [];
                    $field = '';
                    $isblmc = false;
                    $blmc = '';
                    $blmc1 = '';
                    if ($esName == 'zy_brry' && $content["param1"][1] == 'dqsj') {
                        //fdata取当前时间
                        $fdata = date('Y-m-d H:i:s');
                    } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'BRKS') {
                        $deps = $content["param2"];
                        if (strpos($deps, ',')) {
                            $deps = explode(',', $deps);
                        } else {
                            $deps = [$deps];
                        }
                        $depsid = '';
                        foreach ($deps as $dep) {
                            //根据dep_name获取dep_id
                            $depid = Department::query()->where('dep_name', 'like', '%' . $dep . '%')->get()->toArray();
                            foreach ($depid as $v) {
                                $depsid .= $v['dep_id'] . ',';
                            }
                        }
                        $depsid = rtrim($depsid, ',');
                        $content["param2"] = $depsid;
                        $must[] = ['term' => [$tableDict[$esName]["zyh_field"] => $info["MED_REC_ID"]]];
                        $field = $content["param1"][1];
                    } else {

                        if ($esName == 'zy_brry' && $content["param1"][1] == 'bcjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['BLLB' => 294]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ssjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 306]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'scbc') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 295]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sjyscf') {
                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 50]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sxjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 45]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'qjjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 27]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'zkjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 30]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'jdxx') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 26]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'shscbc') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 42]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sqxj') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 82]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'hzjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['BLLB' => 294]];
                            $must[] = ['match_phrase' => ['BLMC' => '会诊']];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'swbltl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 4302]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ynbltl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 44]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'mdt') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 46]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ydfm') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 3069999]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'jjb') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 511]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'zl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 131]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'pgc') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 30301]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'fm') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 3069999]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ssaqhc') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 30375]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ssaqpg') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 76]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'ycczaqhc') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 303751]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sstys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 30308]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'mzzqtys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 77]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sxzltys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 59]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'tsjctys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 60]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'zlcztys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 88]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'mzjl') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 32977]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'sqgzs') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 85]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } elseif ($esName == 'zy_brry' && $content["param1"][1] == 'qtzqtys') {

                            $esName = 'bl01_202303';
                            $must[] = ['term' => ['JZHM' => $info["MED_REC_ID"]]];
                            $must[] = ['term' => ['MBLB' => 32901]];
                            $field = 'HJNR';
                            $isblmc = true;
                        } else {
                            $esName = $content["param1"][0]; // es的索引名称
                            $must[] = ['term' => [$tableDict[$esName]["zyh_field"] => $info["MED_REC_ID"]]];
                            $field = $content["param1"][1];
                        }
                    }

                    $filterData = [];
                    try {
                        $esService = new ElasticsearchService($esName);
                        $params = $esService->clearMust()
                            ->queryByMustBatch($must)
                            ->paginate(1, 10000)
                            ->getParams();
                        $res = app('es')->search($params);
                        $filterData = $esService->getDataByEs($res);
                    } catch (\Throwable $e) {
                        continue;
                    }

                    if (empty($filterData) || empty($filterData[0]) || empty($filterData[0][0])) {
                        continue;
                    }
                    // $content["param1"][1] es数据中的字段
                    //判断filterData，如果filterData只有一条数据,则fdata为该条数据,如果有多条数据,则fdata为数组
                    if (count($filterData[0]) > 1) {
                        //开循环赋值
                        foreach ($filterData[0] as $val) {
                            if ($isblmc) {
                                $fdata[] = ['blmc' => $val['BLMC'] ?? '', 'hjnr' => $val[$field] ?? ''];
                            } else {
                                $fdata[] = $val[$field] ?? '';
                            }
                        }
                    } else {
                        $fdata = !empty($filterData[0][0][$field]) ? $filterData[0][0][$field] : '';
                        $blmc1 = $filterData[0][0]['BLMC'] ?? '';
                        if ($isblmc) {
                            $blmc = $filterData[0][0]['BLMC'] ?? '';
                        }
                    }


                    $content2 = $content["param2"];
                    $condition = $content["condition"];
                    $conditionMet = null;


                    if ($fdata === '0' || $fdata === 0) {
                        $fdata = 0;
                    }
                    // 添加数据验证和错误处理
                    /* if (empty($fdata) && $fdata !== 0 && $content["param1"][0] != 'zy_brry') {
                        continue;
                    } */

                    //范围 100-200 减号分割,可能会存在100-或者-200的情况,需要处理,如果是100-就是>=100,如果是-200就是<=200
                    /**
                     * 时效规则待定
                     *
                     * [{"param1":["bllb292_2023","XBS"],"param2":"患者10年前无明显诱因下出现多尿","condition":"包含"},{"param1":["bllb303_2023","SSJSSJ"],"param2":"bllb303_2023.SSJSSJ-bllb303_2023.SSJSSJ+24","condition":"时效"}]
                     *
                     * 时效的情况下param2这个索引可能会和上面不一个,所以需要这样传,点前面是索引,后面是字段,然后也使用减号分割,后面的字段如果需要加时间,就像示例一样+24这样
                     *
                     * 时间校验规则
                     * 2.6 时间校验【月】【日】【时】【分】【秒】
                     * 判断选择的字段是否满足到月,日,时,分,秒  比如2024就不满足到月,2024-01就满足到月,以此类推,写分开的五个校验规则
                     * 时间校验【月】
                     * 时间校验【日】
                     * 时间校验【时】
                     * 时间校验【分】
                     * 时间校验【秒】
                     *
                     */

                    //判断是否是范围条件

                    if ($condition == "范围") {

                        $content2 = explode("-", $content2);

                        // 确保数值比较的类型一致
                        $fdata = is_numeric($fdata) ? floatval($fdata) : $fdata;

                        $content2[0] = is_numeric($content2[0]) ? floatval($content2[0]) : $content2[0];
                        $content2[1] = is_numeric($content2[1]) ? floatval($content2[1]) : $content2[1];

                        // 使用 isset() 而不是 empty() 来检查
                        if (isset($content2[0]) && $content2[0] !== '' && isset($content2[1]) && $content2[1] !== '') {
                            $min = is_numeric($content2[0]) ? floatval($content2[0]) : $content2[0];
                            $max = is_numeric($content2[1]) ? floatval($content2[1]) : $content2[1];
                            $conditionMet = $fdata >= $min && $fdata <= $max;
                        } else if (isset($content2[0]) && $content2[0] !== '') {
                            $min = is_numeric($content2[0]) ? floatval($content2[0]) : $content2[0];
                            $conditionMet = $fdata >= $min;
                        } else if (isset($content2[1]) && $content2[1] !== '') {
                            $max = is_numeric($content2[1]) ? floatval($content2[1]) : $content2[1];
                            $conditionMet = $fdata <= $max;
                        } else {
                            continue;
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } // 重复率
                    elseif ($condition == "重复率") {
                        // 获取第二个参数的值
                        $p2 = explode(".", $content2);
                        $es = new ElasticsearchService($p2[0]);
                        $params = $es->clearMust()
                            ->queryByMust(['term' => [$tableDict[$p2[0]]["zyh_field"] => $info['MED_REC_ID']]])
                            ->getParams();
                        $res = app('es')->search($params);
                        $filData = $es->getDataByEs($res);

                        if (empty($filData) || empty($filData[0]) || empty($filData[0][0])) {
                            continue;
                        }

                        $value2 = $filData[0][0][$p2[1]];

                        $ts = $fdata;
                        if (is_array($fdata)) {
                            $fdata = array_reverse($fdata);
                            $ts = $fdata[0];
                        }


                        // 获取fdata和value2的字数（不包含标点符号）
                        $fdataChars = preg_replace('/[^\p{L}\p{N}]/u', '', $ts);
                        $fdataCharCount = mb_strlen($fdataChars, 'UTF-8');

                        $value2Chars = preg_replace('/[^\p{L}\p{N}]/u', '', $value2);
                        $value2CharCount = mb_strlen($value2Chars, 'UTF-8');

                        // 谁大谁做分母，两个字数相除如果小于0.85就跳过
                        $maxCharCount = max($fdataCharCount, $value2CharCount);
                        $minCharCount = min($fdataCharCount, $value2CharCount);

                        if ($maxCharCount > 0 && ($minCharCount / $maxCharCount) < 0.85) {
                            continue;
                        }

                        $bcContentArray = preg_split('//u', $ts, 0, PREG_SPLIT_NO_EMPTY);
                        $bcContentArray = array_unique(array_filter($bcContentArray));

                        $bcContentArray1 = preg_split('//u', $value2, 0, PREG_SPLIT_NO_EMPTY);
                        $bcContentArray1 = array_unique(array_filter($bcContentArray1));
                        // 获取交集
                        $res = array_intersect($bcContentArray, $bcContentArray1);
                        $subtract_value = $content["subtract_value"];
                        if (count($bcContentArray) && count($res) / count($bcContentArray) * 100 < intval($subtract_value)) {
                            $conditionMet = true;
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据，这个条件中还有value2,需要保存,需要保存value2的索引名和字段名，分成数组
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                        $basisData[] = [
                            'es_name' => $p2[0],
                            'field_name' => $p2[1],
                            'fdata' => $value2,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } elseif ($condition == "时间校验【月】") {
                        //时间校验是否到月,fdata时需要校验的数据
                        // 处理多种可能的时间格式
                        $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                        if (strlen($fdata) == 4) {
                            // 只有年份 "2024"
                            $conditionMet = false;
                        } else {
                            try {
                                // 统一处理斜杠和横杠
                                $fdata = str_replace('/', '-', $fdata);
                                $date = new DateTime($fdata);
                                // 提取年月部分 (前7个字符)
                                $yearMonth = substr($fdata, 0, 7);
                                // 验证年月格式
                                $conditionMet = (bool)preg_match('/^\d{4}-([0][1-9]|1[0-2])$/', $yearMonth);
                            } catch (\Exception $e) {
                                $conditionMet = false;
                            }
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } elseif ($condition == "时间校验【日】") {
                        try {
                            $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                            $fdata = str_replace('/', '-', $fdata);
                            $date = new DateTime($fdata);
                            $yearMonthDay = substr($fdata, 0, 10);
                            $conditionMet = (bool)preg_match('/^\d{4}-([0][1-9]|1[0-2])-([0][1-9]|[12][0-9]|3[01])$/', $yearMonthDay);
                        } catch (\Exception $e) {
                            $conditionMet = false;
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } elseif ($condition == "时间校验【时】") {
                        try {
                            $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                            $fdata = str_replace('/', '-', $fdata);
                            $date = new DateTime($fdata);
                            $withHour = substr($fdata, 0, 13);
                            $conditionMet = (bool)preg_match('/^\d{4}-([0][1-9]|1[0-2])-([0][1-9]|[12][0-9]|3[01]) ([01][0-9]|2[0-3])$/', $withHour);
                        } catch (\Exception $e) {
                            $conditionMet = false;
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } elseif ($condition == "时间校验【分】") {
                        try {
                            $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                            $fdata = str_replace('/', '-', $fdata);
                            $withMinute = mb_substr($fdata, 0, 16);
                            $conditionMet = (bool)preg_match('/^\d{4}-([0][1-9]|1[0-2])-([0][1-9]|[12][0-9]|3[01]) ([01][0-9]|2[0-3]):([0-5][0-9])$/', $withMinute);
                        } catch (\Exception $e) {
                            $conditionMet = false;
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } elseif ($condition == "时间校验【秒】") {
                        try {
                            $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                            $fdata = str_replace('/', '-', $fdata);
                            //$date = new DateTime($fdata);
                            $withSecond = substr($fdata, 0, 19);
                            $conditionMet = (bool)preg_match('/^\d{4}-([0][1-9]|1[0-2])-([0][1-9]|[12][0-9]|3[01]) ([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/', $withSecond);
                        } catch (\Exception $e) {
                            $conditionMet = false;
                        }
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                    } elseif ($condition == "时效") {
                        //Log::info('时效', ['esName' => $esName, 'content' => $content]);
                        // 初始化条件匹配结果为false
                        $conditionMet = false;
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];

                        try {
                            // 获取参数
                            $param1 = $content["param1"]; // [索引, 字段]
                            $param2 = $content["param2"]; // 条件值（可能包含逗号分隔的多值）
                            $param3 = $content["param3"]; // [索引, 时间字段]
                            $param4 = $content["param4"]; // 时间范围检查配置数组

                            // 获取索引和字段
                            $indexName = $param1[0];
                            $fieldName = $param1[1];

                            // 查询条件值可能包含逗号分隔的多个值
                            $conditionValues = [];
                            if (strpos($param2, ",") !== false) {
                                $conditionValues = explode(",", $param2);
                            } else {
                                $conditionValues = [$param2];
                            }

                            // 构建查询条件
                            $esService = new ElasticsearchService($indexName);
                            $params = $esService->clearMust()
                                ->queryByMust(['term' => [$tableDict[$indexName]["zyh_field"] => $info['MED_REC_ID']]]);

                            // 处理字段条件，支持多值查询
                            $shouldTerms = [];
                            foreach ($conditionValues as $value) {
                                $shouldTerms[] = ['term' => [$fieldName => trim($value)]];
                            }

                            if (count($shouldTerms) == 1) {
                                $params = $params->queryByMust($shouldTerms[0]);
                            } else if (count($shouldTerms) > 1) {
                                $params = $params->queryByMust(['bool' => ['should' => $shouldTerms, 'minimum_should_match' => 1]]);
                            }

                            $params = $params->getParams();
                            //Log::info('filterDataparam', ['param' => $params]);
                            $res = app('es')->search($params);
                            $filterData = $esService->getDataByEs($res);


                            //Log::info('filterData', ['filterData' => $filterData]);

                            // 如果没有找到匹配的记录，则跳过
                            if (empty($filterData) || empty($filterData[0])) {
                                continue;
                            }


                            // 获取时间字段数据
                            $timeFieldName = $param3[1];
                            $timeRecords = [];

                            foreach ($filterData[0] as $record) {
                                if (!empty($record[$timeFieldName])) {
                                    $timeRecords[] = [
                                        'time' => $record[$timeFieldName],
                                        'record' => $record
                                    ];
                                    // 添加到basisData,保存es索引名和字段名和对应的数据，储存时间字段和记录
                                    $basisData[] = [
                                        'es_name' => $indexName,
                                        'field_name' => $timeFieldName,
                                        'fdata' => $record[$timeFieldName],
                                        'conditionMet' => $conditionMet,
                                        'condition' => $condition
                                    ];
                                }
                            }

                            // 如果没有有效的时间记录，则跳过
                            if (empty($timeRecords)) {
                                continue;
                            }

                            // 遍历每个时间记录
                            foreach ($timeRecords as $timeRecord) {
                                $recordTime = strtotime($timeRecord['time']);
                                $allConditionsMet = true;

                                // 检查每个param4配置
                                foreach ($param4 as $p4) {
                                    $kssj = $p4['kssj']; // 开始时间字段
                                    $jssj = $p4['jssj']; // 结束时间字段
                                    $sjjg = $p4['sjjg']; // 时间间隔(小时)
                                    //判断是否存在index字段
                                    if (isset($p4['index'])) {
                                        $indexField = explode('.', $p4['index'])[1]; // 需要检索的字段
                                        $condition1 = $p4['condition1']; // 需要包含的关键词
                                        $condition2 = $p4['condition2']; // 需要排除的关键词
                                    }


                                    // 解析索引和字段
                                    list($checkIndexName, $checkFieldName) = explode('.', $kssj);

                                    // 构建查询条件
                                    $checkEsService = new ElasticsearchService($checkIndexName);
                                    $checkParams = $checkEsService->clearMust()
                                        ->queryByMust(['term' => [$tableDict[$checkIndexName]["zyh_field"] => $info['MED_REC_ID']]]);

                                    // 如果有包含条件，添加到查询
                                    if (!empty($condition1)) {
                                        // 如果包含逗号，则按照逗号分隔
                                        if (strpos($condition1, ",") !== false) {
                                            $condition1Values = explode(',', $condition1);
                                            $shouldTerms1 = [];
                                            foreach ($condition1Values as $val) {
                                                $val = trim($val);
                                                if (!empty($val)) {
                                                    $shouldTerms1[] = ['match' => [$indexField => $val]];
                                                }
                                            }
                                            if (count($shouldTerms1) > 0) {
                                                $checkParams = $checkParams->queryByMust(['bool' => ['should' => $shouldTerms1, 'minimum_should_match' => 1]]);
                                            }
                                        } else {
                                            $checkParams = $checkParams->queryByMust(['match' => [$indexField => $condition1]]);
                                        }
                                    }

                                    // 如果有排除条件，添加到查询
                                    if (!empty($condition2)) {
                                        // 如果包含逗号，则按照逗号分隔
                                        if (strpos($condition2, ",") !== false) {
                                            $condition2Values = explode(',', $condition2);
                                            foreach ($condition2Values as $val) {
                                                $val = trim($val);
                                                if (!empty($val)) {
                                                    $checkParams = $checkParams->queryByMustNot(['match' => [$indexField => $val]]);
                                                }
                                            }
                                        } else {
                                            $checkParams = $checkParams->queryByMustNot(['match' => [$indexField => $condition2]]);
                                        }
                                    }

                                    $checkParams = $checkParams->getParams();
                                    $checkRes = app('es')->search($checkParams);
                                    $checkData = $checkEsService->getDataByEs($checkRes);

                                    // 如果没有找到匹配的检查记录，则标记条件未满足
                                    if (empty($checkData) || empty($checkData[0])) {
                                        $allConditionsMet = false;
                                        break;
                                    }

                                    // 检查每个检查记录，是否在时间范围内
                                    $checkConditionMet = false;
                                    foreach ($checkData[0] as $checkRecord) {
                                        // 获取开始和结束时间
                                        list($kssjIndex, $kssjField) = explode('.', $kssj);
                                        list($jssjIndex, $jssjField) = explode('.', $jssj);

                                        $startTime = strtotime($checkRecord[$kssjField]);
                                        $endTime = strtotime($checkRecord[$jssjField]);

                                        // 如果有时间间隔，应用到结束时间
                                        if (!empty($sjjg)) {
                                            $endTime = $startTime + (intval($sjjg) * 3600); // 转换为秒
                                        }

                                        // 检查记录时间是否在范围内
                                        if ($recordTime >= $startTime && $recordTime <= $endTime) {
                                            $checkConditionMet = true;
                                            break;
                                        }
                                        //储存开始时间字段和结束时间字段和对应的数据
                                        $basisData[] = [
                                            'es_name' => $checkIndexName,
                                            'field_name' => $kssjField,
                                            'fdata' => $checkRecord[$kssjField],
                                            'conditionMet' => $checkConditionMet,
                                            'condition' => $condition
                                        ];
                                    }

                                    // 如果当前param4条件未满足，则所有条件未满足
                                    if (!$checkConditionMet) {
                                        $allConditionsMet = false;
                                        break;
                                    }
                                }

                                // 如果所有条件都满足，则满足整体条件
                                if ($allConditionsMet) {
                                    $conditionMet = true;
                                    break;
                                }
                            }
                        } catch (\Exception $e) {
                            // 记录错误日志
                            Log::error('时效规则执行错误', [
                                'error' => $e->getMessage(),
                                'content' => $content,
                                'info' => $info
                            ]);
                            continue;
                        }
                    } elseif ($condition == "包含") {
                        $conditionMet = false; // 默认设置为 false
                        //新增delete_field参数，先删除对应字段，可能会包含逗号
                        $delete_field = $content['delete_field'] ?? '';
                        //如果fdata为数组,则循环
                        if (is_array($fdata)) {
                            foreach ($fdata as $val) {
                                if ($conditionMet) {
                                    break;
                                }
                                if ($isblmc) {
                                    $fdata1 = $val['hjnr'];
                                } else {
                                    $fdata1 = $val;
                                }
                                // 添加到basisData,保存es索引名和字段名和对应的数据

                                if (!empty($delete_field)) {
                                    //如果包含逗号,则按照逗号分隔
                                    if (strpos($delete_field, ",") !== false) {
                                        $delete_field_array = explode(",", $delete_field);
                                        foreach ($delete_field_array as $val1) {
                                            $fdata1 = str_replace($val1, '', $fdata1);
                                        }
                                    } else {
                                        $fdata1 = str_replace($delete_field, '', $fdata1);
                                    }
                                }
                                if (strpos($content2, ",") !== false) {
                                    $content2Array = explode(",", $content2);
                                    foreach ($content2Array as $value) {
                                        //$value = trim($value); // 去除可能的空格
                                        if (empty($value)) {
                                            continue;
                                        }
                                        if (empty($value)) {
                                            continue;
                                        }
                                        if (strpos($fdata1, $value) !== false) {
                                            $conditionMet = true;
                                            break;
                                        }
                                        //Log::info('包含', ['value' => $value, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                                    }
                                } else {
                                    // 如果content2不包含逗号,则直接判断是否包含
                                    $conditionMet = strpos($fdata1, $content2) !== false;
                                    //Log::info('包含', ['value' => $content2, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                                }
                                if (($conditionMet && $detailstatus == 2) || (!$conditionMet && $detailstatus == 1)) {
                                    $basisData[] = [
                                        'es_name' => $content["param1"][0],
                                        'field_name' => $content["param1"][1],
                                        'fdata' => $val['blmc'] ?? '',
                                        'blmc' => $val['blmc'] ?? '',
                                        'conditionMet' => $conditionMet,
                                        'condition' => $condition
                                    ];
                                    /* if ($info['MED_REC_ID'] == '588028' && $content2 == '*') {
                                        Log::info('basisData111', ['fdata' => $val]);
                                        Log::info('basisData', ['basisData' => $basisData]);
                                    } */
                                }
                                /*} elseif ($conditionMet && $detailstatus == 1) {
                                    $basisData[] = [
                                        'es_name' => $content["param1"][0],
                                        'field_name' => $content["param1"][1],
                                        'fdata' => $tableDict[$esName]["field_name"],
                                        'blmc' => $fdata['blmc'] ?? '',
                                        'conditionMet' => $conditionMet,
                                        'condition' => $condition
                                    ];
                                }elseif ($detailstatus == 1 && !$conditionMet) {
                                    $basisData[] = [
                                        'es_name' => $content["param1"][0],
                                        'field_name' => $content["param1"][1],
                                        'fdata' => $tableDict[$esName]["field_name"],
                                        'blmc' => $fdata['blmc'] ?? '',
                                        'conditionMet' => $conditionMet,
                                        'condition' => $condition
                                    ];
                                }elseif ($detailstatus == 1 && $conditionMet) {
                                    $basisData[] = [
                                        'es_name' => $content["param1"][0],
                                        'field_name' => $content["param1"][1],
                                        'fdata' => $tableDict[$esName]["field_name"],
                                        'blmc' => $fdata['blmc'] ?? '',
                                        'conditionMet' => $conditionMet,
                                        'condition' => $condition
                                    ];
                                } */
                            }
                        } else {
                            if (!empty($delete_field)) {
                                //如果包含逗号,则按照逗号分隔
                                if (strpos($delete_field, ",") !== false) {
                                    $delete_field_array = explode(",", $delete_field);
                                    foreach ($delete_field_array as $val) {
                                        $fdata = str_replace($val, '', $fdata);
                                    }
                                } else {
                                    $fdata = str_replace($delete_field, '', $fdata);
                                }
                            }
                            if (strpos($content2, ",") !== false) {
                                $content2Array = explode(",", $content2);
                                foreach ($content2Array as $value) {
                                    //$value = trim($value); // 去除可能的空格
                                    if (empty($value)) {
                                        continue;
                                    }
                                    //Log::info('测试包含', ['fdata-v' => $fdata, 'value-v' => $value]);
                                    if (strpos($fdata, $value) !== false) {
                                        $conditionMet = true;
                                        break;
                                    }
                                    //Log::info('包含', ['value' => $value, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                                }
                            } else {
                                // 如果content2不包含逗号,则直接判断是否包含
                                $conditionMet = strpos($fdata, $content2) !== false;
                                //Log::info('包含', ['value' => $content2, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                            }
                            // 添加到basisData,保存es索引名和字段名和对应的数据
                            //if ($conditionMet && $detailstatus == 2) {
                            $basisData[] = [
                                'es_name' => $content["param1"][0],
                                'field_name' => $content["param1"][1],
                                'fdata' => $blmc1,
                                'blmc' => $fdata['blmc'] ?? '',
                                'conditionMet' => $conditionMet,
                                'condition' => $condition
                            ];
                            /* }elseif ($conditionMet && $detailstatus == 1) {
                                $basisData[] = [
                                    'es_name' => $content["param1"][0],
                                    'field_name' => $content["param1"][1],
                                    'fdata' => $tableDict[$esName]["field_name"],
                                    'blmc' => $fdata['blmc'] ?? '',
                                    'conditionMet' => $conditionMet,
                                    'condition' => $condition
                                ];
                            }elseif ($detailstatus == 1 && !$conditionMet) {
                                $basisData[] = [
                                    'es_name' => $content["param1"][0],
                                    'field_name' => $content["param1"][1],
                                    'fdata' => $tableDict[$esName]["field_name"],
                                    'blmc' => $fdata['blmc'] ?? '',
                                    'conditionMet' => $conditionMet,
                                    'condition' => $condition
                                ];
                            }elseif ($detailstatus == 1 && $conditionMet) {
                                $basisData[] = [
                                    'es_name' => $content["param1"][0],
                                    'field_name' => $content["param1"][1],
                                    'fdata' => $tableDict[$esName]["field_name"],
                                    'blmc' => $fdata['blmc'] ?? '',
                                    'conditionMet' => $conditionMet,
                                    'condition' => $condition
                                ];
                            } */
                        }
                    } elseif ($condition == "不包含") { //包含和不包含分开
                        // 其他条件的判断
                        //修改一下.如果是包含不包含,就判断content2是否包含英文的逗号,如果包含就按照逗号分隔,然后判断是否包含分隔后的每一个值,满足一个就是true,如果没有逗号就正常判断
                        $conditionMet = false; // 默认设置为 false

                        //新增delete_field参数，先删除对应字段，可能会包含逗号
                        $delete_field = $content['delete_field'] ?? '';
                        //如果fdata为数组,则循环
                        if (is_array($fdata)) {
                            foreach ($fdata as $val) {
                                if ($conditionMet) {
                                    break;
                                }

                                if ($isblmc) {
                                    $fdata1 = $val['hjnr'];
                                } else {
                                    $fdata1 = $val;
                                }

                                if (!empty($delete_field)) {
                                    //如果包含逗号,则按照逗号分隔
                                    if (strpos($delete_field, ",") !== false) {
                                        $delete_field_array = explode(",", $delete_field);
                                        foreach ($delete_field_array as $val) {
                                            $fdata1 = str_replace($val, '', $fdata1);
                                        }
                                    } else {
                                        $fdata1 = str_replace($delete_field, '', $fdata1);
                                    }
                                }
                                if (strpos($content2, ",") !== false) {
                                    $content2Array = explode(",", $content2);
                                    foreach ($content2Array as $value) {
                                        if (empty($value)) {
                                            continue;
                                        }

                                        if (strpos($fdata1, $value) === false) {
                                            $conditionMet = true;
                                        } else {
                                            $conditionMet = false;
                                            break;
                                        }
                                    }
                                } else {
                                    // 如果content2不包含逗号,则直接判断是否不包含
                                    $conditionMet = strpos($fdata1, $content2) === false;
                                    //Log::info('不包含', ['value' => $content2, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                                }
                                //if ($conditionMet && $detailstatus == 2) {
                                if (($conditionMet && $detailstatus == 2) || (!$conditionMet && $detailstatus == 1)) {
                                    $basisData[] = [
                                        'es_name' => $content["param1"][0],
                                        'field_name' => $content["param1"][1],
                                        'fdata' => $val['blmc'] ?? '',
                                        'blmc' => $val['blmc'] ?? '',
                                        'conditionMet' => $conditionMet,
                                        'condition' => $condition
                                    ];
                                    /* if ($info['MED_REC_ID'] == '588028' && $content2 == '*') {
                                            Log::info('basisData111', ['fdata' => $val]);
                                            Log::info('basisData', ['basisData' => $basisData]);
                                        } */
                                }
                                /* }elseif ($conditionMet && $detailstatus == 1) {
                                    $basisData[] = [
                                        'es_name' => $content["param1"][0],
                                        'field_name' => $content["param1"][1],
                                        'fdata' => $fdata1,
                                        'blmc' => $fdata['blmc'] ?? '',
                                        'conditionMet' => $conditionMet,
                                        'condition' => $condition
                                    ];
                                }elseif ($detailstatus == 1 && !$conditionMet) {
                                    $basisData[] = [
                                        'es_name' => $content["param1"][0],
                                        'field_name' => $content["param1"][1],
                                        'fdata' => '',
                                        'blmc' => $fdata['blmc'] ?? '',
                                        'conditionMet' => $conditionMet,
                                        'condition' => $condition
                                    ];
                                }elseif ($detailstatus == 1 && $conditionMet) {
                                    $basisData[] = [
                                        'es_name' => $content["param1"][0],
                                        'field_name' => $content["param1"][1],
                                        'fdata' => $fdata1,
                                        'blmc' => $fdata['blmc'] ?? '',
                                        'conditionMet' => $conditionMet,
                                        'condition' => $condition
                                    ];
                                } */
                            }
                        } else {
                            if (!empty($delete_field)) {
                                //如果包含逗号,则按照逗号分隔
                                if (strpos($delete_field, ",") !== false) {
                                    $delete_field_array = explode(",", $delete_field);
                                    foreach ($delete_field_array as $val) {
                                        $fdata = str_replace($val, '', $fdata);
                                    }
                                } else {
                                    $fdata = str_replace($delete_field, '', $fdata);
                                }
                            }

                            if (strpos($content2, ",") !== false) {
                                $content2Array = explode(",", $content2);
                                foreach ($content2Array as $value) {
                                    //Log::info('不包含', ['value' => $value, 'fdata' => $fdata]);
                                    if (empty($value)) {
                                        continue;
                                    }
                                    if (strpos($fdata, $value) === false) {
                                        $conditionMet = true;
                                    } else {
                                        $conditionMet = false;
                                        break;
                                    }
                                }
                            } else {
                                // 如果content2不包含逗号,则直接判断是否不包含
                                $conditionMet = strpos($fdata, $content2) === false;
                                //Log::info('不包含', ['value' => $content2, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                            }
                            if ($conditionMet && $detailstatus == 2) {
                                $basisData[] = [
                                    'es_name' => $content["param1"][0],
                                    'field_name' => $content["param1"][1],
                                    'fdata' => '',
                                    'blmc' => $fdata['blmc'] ?? '',
                                    'conditionMet' => $conditionMet,
                                    'condition' => $condition
                                ];
                            } elseif ($conditionMet && $detailstatus == 1) {
                                $basisData[] = [
                                    'es_name' => $content["param1"][0],
                                    'field_name' => $content["param1"][1],
                                    'fdata' => $fdata,
                                    'blmc' => $fdata['blmc'] ?? '',
                                    'conditionMet' => $conditionMet,
                                    'condition' => $condition
                                ];
                            } elseif ($detailstatus == 1 && !$conditionMet) {
                                $basisData[] = [
                                    'es_name' => $content["param1"][0],
                                    'field_name' => $content["param1"][1],
                                    'fdata' => '',
                                    'blmc' => $fdata['blmc'] ?? '',
                                    'conditionMet' => $conditionMet,
                                    'condition' => $condition
                                ];
                            } elseif ($detailstatus == 1 && $conditionMet) {
                                $basisData[] = [
                                    'es_name' => $content["param1"][0],
                                    'field_name' => $content["param1"][1],
                                    'fdata' => $fdata,
                                    'blmc' => $fdata['blmc'] ?? '',
                                    'conditionMet' => $conditionMet,
                                    'condition' => $condition
                                ];
                            }
                        }
                    } elseif ($condition == "病程记录关联") {
                        // 初始化条件匹配结果为false
                        $conditionMet = false;
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        $basisData[] = [
                            'es_name' => $esName,
                            'field_name' => $content["param1"][1],
                            'fdata' => $fdata,
                            'conditionMet' => $conditionMet,
                            'condition' => $condition
                        ];
                        // 获取病程记录数据
                        $indexName = $content["param1"][0]; // 索引名称，例如 bl01_202303
                        $mblbField = $content["param1"][1]; // MBLB字段
                        $blbhField = $content["param1"][2]; // BLBH字段
                        //可能会包含逗号
                        if (strpos($content["param2"], ",") !== false) {
                            $mblbValues = explode(",", $content["param2"]); // 可能包含逗号分隔的多个值
                        } else {
                            $mblbValues = [$content["param2"]];
                        }

                        // 查询条件
                        $must = [];
                        $must[] = ['term' => [$tableDict[$indexName]["zyh_field"] => $info['MED_REC_ID']]];

                        // 处理MBLB条件，支持多值查询
                        $shouldTerms = [];
                        foreach ($mblbValues as $value) {
                            $shouldTerms[] = ['term' => [$mblbField => trim($value)]];
                        }

                        if (count($shouldTerms) == 1) {
                            $must[] = $shouldTerms[0];
                        } else if (count($shouldTerms) > 1) {
                            $must[] = ['bool' => ['should' => $shouldTerms, 'minimum_should_match' => 1]];
                        }

                        // 执行ES查询
                        try {
                            $esService = new ElasticsearchService($indexName);
                            $params = $esService->clearMust();

                            foreach ($must as $m) {
                                $params = $params->queryByMust($m);
                            }

                            $params = $params->getParams();
                            $res = app('es')->search($params);
                            $filterData = $esService->getDataByEs($res);
                            Log::info('病程记录', [
                                'params' => $params,
                                'filterData' => $filterData
                            ]);
                            // 如果没有找到匹配的记录，则跳过
                            if (empty($filterData) || empty($filterData[0])) {
                                continue;
                            }

                            // 保存first_blsy_time和BLBH信息
                            $blsyTimeField = $content["param3"][1]; // fisrt_blsy_time字段
                            $blRecords = [];

                            foreach ($filterData[0] as $record) {
                                if (!empty($record[$blsyTimeField]) && !empty($record[$blbhField])) {
                                    $blRecords[] = [
                                        'blsy_time' => $record[$blsyTimeField],
                                        'blbh' => $record[$blbhField]
                                    ];
                                    // 添加到basisData,保存es索引名和字段名和对应的数据，储存病程记录时间字段和病程记录编号
                                    $basisData[] = [
                                        'es_name' => $indexName,
                                        'field_name' => $blsyTimeField,
                                        'fdata' => $record[$blsyTimeField],
                                        'conditionMet' => $conditionMet,
                                        'condition' => $condition
                                    ];
                                }
                            }

                            // 如果没有有效的病程记录，则跳过
                            if (empty($blRecords)) {
                                continue;
                            }

                            // 获取param4参数
                            $param4 = $content["param4"];
                            $matchedRecords = [];

                            // 处理每个param4条件,param4只有一条,只是根据里面的条件和所有有可能查出多条,所以不使用foreach
                            foreach ($param4 as $p4) {
                                $kssj = $p4['kssj']; // 开始时间字段
                                $jssj = $p4['jssj']; // 结束时间字段
                                $sjjg = $p4['sjjg']; // 时间间隔(小时)
                                $params1 = $p4['params1']; // 需要获取的字段
                                $condition1 = $p4['condition1']; // 需要包含的关键词
                                $condition2 = $p4['condition2']; // 需要排除的关键词
                                $zj = $p4['zj']; // 关联字段

                                // 解析索引和字段
                                $tiaojian = explode('.', $param1)[1];
                                list($indexName, $fieldName) = explode('.', $kssj);
                                list($endTimeIndex, $endTimeField) = explode('.', $jssj);
                                // 处理包含条件,可能会包含逗号
                                $shouldTerms1 = [];
                                if (!empty($condition1)) {
                                    // 如果包含逗号,则按照逗号分隔
                                    if (strpos($condition1, ",") !== false) {
                                        $condition1Values = explode(',', $condition1);
                                        foreach ($condition1Values as $val) {
                                            $val = trim($val);
                                            if (!empty($val)) {
                                                $shouldTerms1[] = ['match' => [$tiaojian => $val]];
                                            }
                                        }
                                    } else {
                                        $shouldTerms1[] = ['match' => [$tiaojian => $condition1]];
                                    }
                                }

                                // 处理排除条件
                                $mustNotTerms = [];
                                if (!empty($codition2)) {
                                    // 如果包含逗号,则按照逗号分隔
                                    if (strpos($condition2, ",") !== false) {
                                        $condition2Values = explode(',', $condition2);
                                        foreach ($condition2Values as $val) {
                                            $val = trim($val);
                                            if (!empty($val)) {
                                                $mustNotTerms[] = ['match' => [$tiaojian => $val]];
                                            }
                                        }
                                    } else {
                                        $mustNotTerms[] = ['match' => [$tiaojian => $condition2]];
                                    }
                                }

                                // 构建查询
                                $esService = new ElasticsearchService($indexName);
                                $params = $esService->clearMust();

                                // 添加查询条件
                                $params = $params->queryByMust(['term' => [$tableDict[$indexName]["zyh_field"] => $info['MED_REC_ID']]]);

                                // 添加包含条件
                                if (!empty($shouldTerms1)) {
                                    if (count($shouldTerms1) == 1) {
                                        $params = $params->queryByMust($shouldTerms1[0]);
                                    } else {
                                        $params = $params->queryByMust(['bool' => ['should' => $shouldTerms1, 'minimum_should_match' => 1]]);
                                    }
                                }

                                // 添加排除条件
                                if (!empty($mustNotTerms)) {
                                    foreach ($mustNotTerms as $notTerm) {
                                        $params = $params->queryByMustNot($notTerm);
                                    }
                                }

                                $params = $params->getParams();
                                $res = app('es')->search($params);
                                $checkData = $esService->getDataByEs($res);
                                Log::info('病程记录检查数据', [
                                    'params' => $params,
                                    'checkData' => $checkData
                                ]);
                                // 如果没有找到匹配的记录，则继续下一个
                                if (empty($checkData) || empty($checkData[0])) {
                                    continue;
                                }

                                // 对于每条检查记录，检查是否有病程记录在时间范围内
                                foreach ($checkData[0] as $checkRecord) {
                                    // 获取开始和结束时间
                                    $startTime = strtotime($checkRecord[$fieldName]);
                                    $endTime = strtotime($checkRecord[$endTimeField]);

                                    // 如果有时间间隔，加到结束时间上
                                    if (!empty($sjjg)) {
                                        $endTime = $startTime + (intval($sjjg) * 3600); // 转换为秒
                                    }

                                    // 检查每条病程记录是否在时间范围内
                                    foreach ($blRecords as $blRecord) {
                                        $blsyTime = strtotime($blRecord['blsy_time']);

                                        // 检查病程记录时间是否在范围内
                                        if ($blsyTime >= $startTime && $blsyTime <= $endTime) {
                                            // 保存匹配记录的信息
                                            $matchedRecords[] = [
                                                'blbh' => $blRecord['blbh'],
                                                'checkBLBH' => isset($checkRecord[$params1]) ? $checkRecord[$params1] : '',
                                                'relation_type' => $content["param5"] // gl或bgl
                                            ];
                                            // 添加到basisData,保存es索引名和字段名和对应的数据，储存病程记录编号和病程记录时间字段
                                            $basisData[] = [
                                                'es_name' => $indexName,
                                                'field_name' => $blsyTimeField,
                                                'fdata' => $blRecord['blsy_time'],
                                                'conditionMet' => $conditionMet,
                                                'condition' => $condition
                                            ];
                                        }
                                    }
                                }
                            }

                            // 如果找到了匹配的记录，检查是否满足关联条件
                            Log::info('病程记录匹配记录', [
                                'matchedRecords' => $matchedRecords
                            ]);
                            if (!empty($matchedRecords)) {
                                $param5 = $content["param5"]; // gl(关联)或bgl(不关联)
                                $param6 = $content["param6"]; // [索引, 包含条件, 不包含条件]

                                $indexName = $param6[0];
                                list($indexName, $hjnrField) = explode('.', $indexName);
                                //如果包含逗号,则按照逗号分隔
                                if (strpos($param6[1], ",") !== false) {
                                    $includeTerms = explode(',', $param6[1]);
                                } else {
                                    $includeTerms = [$param6[1]];
                                }
                                if (strpos($param6[2], ",") !== false) {
                                    $excludeTerms = explode(',', $param6[2]);
                                } else {
                                    $excludeTerms = [$param6[2]];
                                }

                                // 处理每条匹配记录
                                foreach ($matchedRecords as $record) {
                                    // 构建查询条件
                                    $esService = new ElasticsearchService($indexName);
                                    $params = $esService->clearMust();

                                    //添加主键条件
                                    $params = $params->queryByMust(['term' => [$tableDict[$indexName]["zyh_field"] => $info['MED_REC_ID']]]);

                                    // 如果是关联(gl)模式，添加BLBH条件
                                    if ($param5 == 'gl' && !empty($record['blbh'])) {
                                        $params = $params->queryByMust(['term' => [$blbhField => $record['blbh']]]);
                                    }

                                    $params = $params->getParams();
                                    $res = app('es')->search($params);
                                    $hjnrData = $esService->getDataByEs($res);

                                    // 如果没有找到病历内容，则继续下一个
                                    if (empty($hjnrData) || empty($hjnrData[0]) || empty($hjnrData[0][0]) || empty($hjnrData[0][0][$hjnrField])) {
                                        continue;
                                    }

                                    $hjnr = $hjnrData[0][0][$hjnrField];
                                    $passInclude = true;
                                    $passExclude = true;

                                    // 检查是否包含需要的关键词
                                    if (!empty($includeTerms)) {
                                        $passInclude = false;
                                        //是否是数组
                                        if (is_array($includeTerms)) {
                                            foreach ($includeTerms as $term) {
                                                $term = trim($term);
                                                if (!empty($term) && strpos($hjnr, $term) !== false) {
                                                    $passInclude = true;
                                                    break;
                                                }
                                            }
                                        } else {
                                            if (!empty($includeTerms) && strpos($hjnr, $includeTerms) !== false) {
                                                $passInclude = true;
                                            }
                                        }
                                    }

                                    // 检查是否不包含需要排除的关键词
                                    if (!empty($excludeTerms)) {
                                        $passExclude = false;
                                        //是否是数组
                                        if (is_array($excludeTerms)) {
                                            foreach ($excludeTerms as $term) {
                                                $term = trim($term);
                                                if (!empty($term) && strpos($hjnr, $term) !== false) {
                                                    $passExclude = false;
                                                    break;
                                                }
                                            }
                                        } else {
                                            if (!empty($excludeTerms) && strpos($hjnr, $excludeTerms) !== false) {
                                                $passExclude = false;
                                            }
                                        }
                                    }

                                    // 如果同时满足包含和排除条件，则条件满足
                                    if ($passInclude && $passExclude) {
                                        $conditionMet = true;
                                        break; // 只要有一条记录满足条件就可以
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            // 记录错误日志
                            Log::error('病程记录关联规则执行错误', [
                                'error' => $e->getMessage(),
                                'content' => $content,
                                'info' => $info
                            ]);
                            continue;
                        }
                    } elseif ($condition == "减") {
                        // 获取第二个参数的值
                        $p2 = [$content['subtract_param'][0], $content['subtract_param'][1]];
                        $es = new ElasticsearchService($p2[0]);
                        $params = $es->clearMust()
                            ->queryByMust(['term' => [$tableDict[$p2[0]]["zyh_field"] => $info['MED_REC_ID']]])
                            ->getParams();
                        $res = app('es')->search($params);
                        $filData = $es->getDataByEs($res);

                        if (empty($filData) || empty($filData[0]) || empty($filData[0][0])) {
                            continue;
                        }

                        $value2 = $filData[0][0][$p2[1]];

                        // 计算差值
                        if ($content['subtract_value_type'] == 'hour' || $content['subtract_value_type'] == 'minute') {
                            // 时间差值计算
                            $time1 = strtotime($fdata);
                            $time2 = strtotime($value2);
                            $diffSeconds = abs($time1 - $time2);

                            if ($content['subtract_value_type'] == 'hour') {
                                $diff = $diffSeconds / 3600; // 转换为小时
                            } else {
                                $diff = $diffSeconds / 60; // 转换为分钟
                            }
                        } else {
                            // 数值差值计算
                            $diff = abs(floatval($fdata) - floatval($value2));
                        }

                        // 根据比较条件判断
                        switch ($content['subtract_condition']) {
                            case 'gt':
                                $conditionMet = $diff > floatval($content['subtract_value']);
                                break;
                            case 'lt':
                                $conditionMet = $diff < floatval($content['subtract_value']);
                                break;
                            case 'eq':
                                $conditionMet = abs($diff - floatval($content['subtract_value'])) < 0.000001; // 浮点数比较
                                break;
                            default:
                                $conditionMet = false;
                        }
                    } else {
                        $fdata = is_array($fdata) ? $fdata[0] : $fdata;
                        //如果是6岁8月这种格式，转换成6.8这样的数字
                        if (is_string($fdata) && preg_match('/(\d+)岁(\d+)月/', $fdata, $matches)) {
                            $years = intval($matches[1]);
                            $months = intval($matches[2]);
                            $fdata = $years + ($months / 12); // 转换为类似6.8的格式
                        }
                        //如果是45岁这种格式，转换成45这样的数字
                        if (is_string($fdata) && preg_match('/(\d+)岁/', $fdata, $matches)) {
                            $fdata = intval($matches[1]);
                        }
                        $conditionMet = ($condition == "等于" && $fdata == $content2) ||
                            ($condition == "不等于" && $fdata != $content2) ||
                            ($condition == "大于" && $fdata > $content2) ||
                            ($condition == "小于" && $fdata < $content2) ||
                            ($condition == "大于等于" && $fdata >= $content2) ||
                            ($condition == "小于等于" && $fdata <= $content2) ||
                            ($condition == "不为空" && !empty($fdata));
                        // 添加到basisData,保存es索引名和字段名和对应的数据
                        if ($conditionMet && $detailstatus == 2) {
                            $basisData[] = [
                                'es_name' => $content["param1"][0],
                                'field_name' => $content["param1"][1],
                                'fdata' => $fdata,
                                'blmc' => $blmc ?? '',
                                'conditionMet' => $conditionMet,
                                'condition' => $condition
                            ];
                        } elseif ($detailstatus == 1 && !$conditionMet) {
                            $basisData[] = [
                                'es_name' => $content["param1"][0],
                                'field_name' => $content["param1"][1],
                                'fdata' => $fdata,
                                'blmc' => $blmc ?? '',
                                'conditionMet' => $conditionMet,
                                'condition' => $condition
                            ];
                        }
                    }

                    // 条件状态：1正确（条件不符合质控），2错误（条件符合质控）
                    if ($item['detail_status'] == 1) {
                        if (!$conditionMet) {
                            $ruleFlag = 0;
                            if ($item['condition_relation'] == 1) {
                                break;
                            }
                        } else {
                            $ruleFlag = 1;

                            if ($item['condition_relation'] == 2) {
                                //根据条件判断不满足就删除对应的basisData
                                break;
                            }
                        }
                    } elseif ($item['detail_status'] == 2) {
                        if ($conditionMet) {
                            $ruleFlag = 0;
                            if ($item['condition_relation'] == 2) {
                                break;
                            }
                        } else {
                            $ruleFlag = 1;
                            if ($item['condition_relation'] == 1) {
                                break;
                            }
                        }
                    }
                }
            }

            /* if ($preWarningTime > 0 && !empty($qtTime)) {
                // 如果 customMsg 中有 aftertime，则将其加到 qtTime 上
                if (!empty($customMsg['aftertime'])) {
                    $qtTime = date('Y-m-d H:i:s', strtotime($qtTime) + $customMsg['aftertime'] * 60);
                } else {
                    $qtTime = date('Y-m-d H:i:s', strtotime($qtTime));
                }

                // 计算剩余时间（分钟）
                $currentTime = time();
                $qtTimestamp = strtotime($qtTime);
                $timeDifference = ($qtTimestamp - $currentTime) / 60; // 转换为分钟


                // 如果剩余时间小于预警时间，则发送预警信息
                if ($timeDifference < $preWarningTime && $timeDifference > 0) {
                    //$remainingTime = round($timeDifference); // 剩余时间取整
                    //remainingTime转换成多少小时多少分钟得字符串
                    $remainingTimeStr = remainderTime($timeDifference * 60);
                    $msg = $customMsg['input1'] . $remainingTimeStr . $customMsg['input2'];

                    // 处理包含 HTML 标签的数组，转换成简单字符串数组
                    $formattedBasis = [];
                    foreach ($basis as $item) {
                        if (is_array($item) && !empty($item[0])) {
                            // 去除 HTML 标签并清理空格
                            $text = trim(strip_tags($item[0]));
                            if ($text) {
                                $formattedBasis[] = $text;
                            }
                        }
                    }
                    $this->sendMsg($info['MED_REC_ID'], $msg, $currentRuleId + 1000000, $formattedBasis);
                } */
            if ($ruleFlag == 0) {
                //Log::info('测试包含', ['customBasis-v' => $customBasis]);
                $basis = [];
                if (!empty($customBasis)) {
                    // 根据fdata字段对$basisData进行去重
                    $uniqueBasisData = [];
                    $seenFdata = [];
                    foreach ($basisData as $item) {
                        $fdataKey = is_array($item['fdata']) ? serialize($item['fdata']) : $item['fdata'];
                        if (!in_array($fdataKey, $seenFdata)) {
                            $seenFdata[] = $fdataKey;
                            $uniqueBasisData[] = $item;
                        }
                    }
                    $basisData = $uniqueBasisData;

                    foreach ($customBasis as $cb) {
                        if ($info['MED_REC_ID'] == '588028') {
                            Log::info('basisData22222', ['basisData' => $basisData]);
                            Log::info('basis33333', ['customBasis' => $cb]);
                        }
                        //if ($cb['param1'][0] == $content['param1'][0] && $cb['param1'][1] == $content['param1'][1]) {
                        /* $esService = new ElasticsearchService($cb['param1'][0]);
                        $params = $esService->clearMust()
                            ->queryByMust(['term' => [$tableDict[$cb['param1'][0]]["zyh_field"] => $info['MED_REC_ID']]])
                            ->getParams();
                        $res = app('es')->search($params);
                        $filData = $esService->getDataByEs($res);
                        if (empty($filData) || empty($filData[0])) {
                            continue;
                        }
                        //如果$filData[0][0][$cb['param1'][1]]长度过长(超过20个汉字)就只展示一部分
                        $con = $filData[0][0][$cb['param1'][1]];
                        if (mb_strlen($con) > 20) {
                            $con = substr($con, 0, 20) . '...';
                        }
                        $basis[] = ['<span style="color:red;">' . $cb['input1'] . '【' . $con . '】' . $cb['input2'] . '</span><br>']; */
                        //读取basisData中的数据，设置相对应数据
                        //Log::info('测试包含', ['basisData-v' => $basisData]);
                        foreach ($basisData as $basisdata) {
                            if (!empty($cb['param1']) && $basisdata['es_name'] == $cb['param1'][0] && $basisdata['field_name'] == $cb['param1'][1]) {
                                //如果$basis['fdata']长度过长(超过20个汉字)就只展示一部分
                                $con = is_array($basisdata['fdata']) ? $basisdata['fdata'][0] : $basisdata['fdata'];
                                if (mb_strlen($con) > 20) {
                                    $con = mb_substr($con, 0, 20, 'utf-8') . '...';
                                }
                                $input1 = '';
                                if (!empty($cb['input1'])) {
                                    $input1 = $cb['input1'];
                                } else {
                                    $input1 = '';
                                }

                                // 确保basis始终是数组格式，不会变成false
                                if (!is_array($basis)) {
                                    $basis = [];
                                }

                                if (!empty($basisdata['blmc'])) {
                                    $str = '病程【' . $basisdata['blmc'] . '】' . $cb['input2'];
                                    $basis[] = $str;
                                } else {
                                    $str = $input1 . '【' . $con . '】' . $cb['input2'];
                                    $basis[] = [$str];
                                }
                                //Log::info('测试包含', ['basis-v' => $basis, 'basis_type' => gettype($basis)]);
                            } else {
                                $input1 = '';
                                if (!empty($cb['input1'])) {
                                    $input1 = $cb['input1'];
                                } else {
                                    $input1 = '';
                                }

                                $str = $input1 . '' . $cb['input2'];
                                $basis[] = [$str];
                            }
                        }
                        //}
                    }
                }
                try {
                    // 确保在json_encode之前basis是有效的数组
                    if (!is_array($basis)) {
                        $basis = [];
                        //Log::warning('basis不是数组类型，已重置为空数组', ['basis_original' => $basis]);
                    }
                    $customruleid = $currentRuleId + 1000000;
                    $object = $this->ruleSetting[$currentRuleId]['object'] ?? '';
                    //如果包含逗号
                    if (strpos($object, ',') !== false) {
                        $objects = explode(',', $object);
                        $object = $objects[1];
                    }
                    $errorNotices[] = [
                        'basis' => json_encode($basis, JSON_UNESCAPED_UNICODE),
                        'rule_id' => $customruleid,
                        'BLBH' => $info['BLBH'],
                        'JZXH' => $info['JZXH'],
                        'BRID' => $info['BRID'],
                        'code' => 'omr_rule_' . $currentRuleId,
                        'score' => $this->ruleSetting[$currentRuleId]['score'] ?? '',
                        'error_field' => $object,
                    ];
                    //Log::info('测试包含', ['errorNotices-v' => $errorNotices, 'basis_final' => $basis]);
                } catch (\Exception $e) {
                    Log::error('测试包含Error creating error notice', [
                        'error' => $e->getMessage(),
                        'rule_id' => $currentRuleId,
                        'info' => $info
                    ]);
                }
            }
        }

        $errorNotice = array_filter($errorNotices);

        return $errorNotice;
    }
}
