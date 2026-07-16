<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ElasticsearchService;
use App\Model\TableDict;
use App\Model\RuleSetting;
use App\Model\RuleSettingDetail;
use DateTime;
use Illuminate\Support\Facades\Log;
use App\Model\CaseQuality;
use App\Model\ErrorV2;
use App\Model\HomeQuality;
use App\Model\PatientInfo;
use App\Model\PrimaryKeyControl;
use App\Model\SYZK_PZ;
use App\Model\TableDictSY;
use App\Services\DataxSync\DataSyncService;
use Illuminate\Support\Facades\DB;

class CustomZKController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * @param array $info
     * 自定义规则
     */
    public function customizeRule($ZYH, $data, $type)
    {
        $tableDict = TableDictSY::query()->get()->toArray();
        Log::info('zdy--------------');
        $tableDict = array_column($tableDict, null, 'field');
        $score = 0;
        $errorNotices = [];
        //根据type筛选ruleSetting,如果type是1,changjing字段包含'编码员',2包含医生站,3包含病案室
        if ($type == 1) {
            $ruleSetting = RuleSetting::query()->where('status', '=', 1)->where('rule_type', '=', '首页规则')->where('changjing', 'like', '%编码员%')->get()->toArray();
        } elseif ($type == 2) {
            $ruleSetting = RuleSetting::query()->where('status', '=', 1)->where('rule_type', '=', '首页规则')->where('changjing', 'like', '%医生站%')->get()->toArray();
        } elseif ($type == 3) {
            $ruleSetting = RuleSetting::query()->where('status', '=', 1)->where('rule_type', '=', '首页规则')->where('changjing', 'like', '%病案室%')->get()->toArray();
        }
        $insertData = [];
        $info = [];
        Log::info('首页规则：', $ruleSetting);
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
                    $field_name = $content["param1"][1];
                    $fdata = null;
                    if ($content["param1"][0] == 'zy_brry' && $content["param1"][1] == 'dqsj') {
                        //fdata取当前时间
                        $fdata = date('Y-m-d H:i:s');
                    } else {
                        $filterData = [];
                        try {
                            if ($type == 1) {
                                $tableName = $esName;
                                $zyhField = $tableDict[$esName]["zyh_field"];
                                $filterData = DB::table($tableName)
                                    ->where($zyhField, $ZYH)
                                    ->get()
                                    ->toArray();
                                // 将stdClass对象转换为数组
                                $filterData = json_decode(json_encode($filterData), true);
                            } elseif ($type == 2) {
                                $tableName = $tableDict[$esName]["field_v2"];
                                $zyhField = 'ZYH';
                                $field_name = $tableDict[$field_name]["field_v2"];
                                if(empty($field_name)){
                                    continue;
                                }
                                $filterData = DB::table($tableName)
                                    ->where($zyhField, $ZYH);
                                if($esName == 'main_diagnosis'){
                                    $filterData = $filterData->where('type', '=', '1');
                                }elseif($esName == 'main_operation'){
                                    $filterData = $filterData->where('type', '=', '1');
                                }elseif($esName == 'other_diagnosis'){
                                    $filterData = $filterData->where('type', '!=', '1');
                                }elseif($esName == 'secondary_operation'){
                                    $filterData = $filterData->where('type', '!=', '1');
                                }
                                $filterData = $filterData->get()
                                    ->toArray();
                                // 将stdClass对象转换为数组
                                $filterData = json_decode(json_encode($filterData), true);
                            }
                        } catch (\Throwable $e) {
                            continue;
                        }

                        if (empty($filterData) || empty($filterData[0])) {
                            continue;
                        }
                        // $content["param1"][1] es数据中的字段
                        //判断filterData，如果filterData只有一条数据,则fdata为该条数据,如果有多条数据,则fdata为数组
                        if (count($filterData) > 1) {
                            //开循环赋值
                            foreach ($filterData as $val) {
                                $fdata[] = $val[$field_name];
                            }
                        } else {
                            $fdata = $filterData[0][$field_name];
                        }
                    }

                    $content2 = $content["param2"];
                    $condition = $content["condition"];
                    $conditionMet = false;

                    if ($fdata === '0' || $fdata === 0) {
                        $fdata = 0;
                    }

                    // 添加数据验证和错误处理
                    if (!$this->hasRuleValue($fdata)) {
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

                        $bcContentArray = preg_split('//u', $fdata, -1, PREG_SPLIT_NO_EMPTY);
                        $bcContentArray = array_unique(array_filter($bcContentArray));

                        $bcContentArray1 = preg_split('//u', $value2, -1, PREG_SPLIT_NO_EMPTY);
                        $bcContentArray1 = array_unique(array_filter($bcContentArray1));
                        // 获取交集
                        $res = array_intersect($bcContentArray, $bcContentArray1);
                        $subtract_value = $content["subtract_value"];
                        if (count($res) / count($bcContentArray) * 100 < intval($subtract_value)) {
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
                                if (strpos($content2, ",") !== false) {
                                    $content2Array = explode(",", $content2);
                                    foreach ($content2Array as $value) {
                                        //$value = trim($value); // 去除可能的空格

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
                                if (strpos($content2, ",") !== false) {
                                    $content2Array = explode(",", $content2);
                                    foreach ($content2Array as $value) {
                                        if (strpos($fdata1, $value) === false) {
                                            $conditionMet = true;
                                            break;
                                        }
                                    }
                                } else {
                                    // 如果content2不包含逗号,则直接判断是否不包含
                                    $conditionMet = strpos($fdata1, $content2) === false;
                                    //Log::info('不包含', ['value' => $content2, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                                }
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
                                    if (strpos($fdata, $value) === false) {
                                        $conditionMet = true;
                                        break;
                                    }
                                }
                            } else {
                                // 如果content2不包含逗号,则直接判断是否不包含
                                $conditionMet = strpos($fdata, $content2) === false;
                                //Log::info('不包含', ['value' => $content2, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                            }
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
                        $conditionMet = ($condition == "等于" && $fdata == $content2) ||
                            ($condition == "不等于" && $fdata != $content2) ||
                            ($condition == "大于" && $fdata > $content2) ||
                            ($condition == "小于" && $fdata < $content2) ||
                            ($condition == "大于等于" && $fdata >= $content2) ||
                            ($condition == "小于等于" && $fdata <= $content2) ||
                            ($condition == "不为空" && $this->hasRuleValue($fdata));
                    }


                    if ($conditionMet) {
                        $tmpFlag = 1;
                        // 条件之间是或的关系，只要有一个满足条件就退出循环
                        if ($item['condition_relation'] == 2) {
                            break;
                        }
                    } else {
                        $tmpFlag = 0;
                        // 条件之间是且的关系，只要有一个不满足条件就退出循环
                        if ($item['condition_relation'] == 1) {
                            break;
                        }
                    }
                    Log::info('tmpFlag', ['tmpFlag' . $item['rule_id'] => $tmpFlag]);
                }
                //有一个前置不满足就跳出循环
                if ($tmpFlag == 0) {
                    break;
                }
            }

            Log::info('tmpFlag', ['tmpFlag' => $tmpFlag]);

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
                            ->queryByMust(['term' => [$tableDict[$esName]["zyh_field"] => $ZYH]])
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
                    Log::info('content', ['content' => $content]);
                    $ruleFlag = 1;
                    if (empty($content["param1"])) {
                        continue;
                    }
                    $esName = $content["param1"][0];
                    $field_name = $content["param1"][1];
                    $fdata = null;
                    if ($content["param1"][0] == 'zy_brry' && $content["param1"][1] == 'dqsj') {
                        //fdata取当前时间
                        $fdata = date('Y-m-d H:i:s');
                    } else {
                        $filterData = [];
                        try {
                            if ($type == 1) {
                                $tableName = $esName;
                                $zyhField = $tableDict[$esName]["zyh_field"];
                                $filterData = DB::table($tableName)
                                    ->where($zyhField, $ZYH)
                                    ->get()
                                    ->toArray();
                                // 将stdClass对象转换为数组
                                $filterData = json_decode(json_encode($filterData), true);
                            } elseif ($type == 2) {
                                $tableName = $tableDict[$esName]["field_v2"];
                                $zyhField = 'ZYH';
                                $field_name = $tableDict[$field_name]["field_v2"];
                                if(empty($field_name)){
                                    continue;
                                }
                                $filterData = DB::table($tableName)
                                    ->where($zyhField, $ZYH);
                                if($esName == 'main_diagnosis'){
                                    $filterData = $filterData->where('type', '=', '1');
                                }elseif($esName == 'main_operation'){
                                    $filterData = $filterData->where('type', '=', '1');
                                }elseif($esName == 'other_diagnosis'){
                                    $filterData = $filterData->where('type', '!=', '1');
                                }elseif($esName == 'secondary_operation'){
                                    $filterData = $filterData->where('type', '!=', '1');
                                }
                                $filterData = $filterData->get()
                                    ->toArray();
                                // 将stdClass对象转换为数组
                                $filterData = json_decode(json_encode($filterData), true);
                            }
                        } catch (\Throwable $e) {
                            continue;
                        }

                        if (empty($filterData) || empty($filterData[0])) {
                            continue;
                        }
                        // $content["param1"][1] es数据中的字段

                        //判断filterData，如果filterData只有一条数据,则fdata为该条数据,如果有多条数据,则fdata为数组
                        if (count($filterData) > 1) {
                            //开循环赋值
                            foreach ($filterData as $val) {
                                $fdata[] = $val[$field_name];
                            }
                        } else {
                            $fdata = $filterData[0][$field_name];
                        }
                    }

                    //Log::info('fata=>', ['ff'=>$fdata]);

                    $content2 = $content["param2"];
                    $condition = $content["condition"];
                    $conditionMet = null;


                    if ($fdata === '0' || $fdata === 0) {
                        $fdata = 0;
                    }
                    // 空值通常不参与规则计算，但“不能为空”类规则需要继续往下判断
                    if (!$this->hasRuleValue($fdata) && $condition != "不为空") {
                        continue;
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
                        Log::info('时效', ['esName' => $esName, 'content' => $content]);
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
                            Log::info('filterDataparam', ['param' => $params]);
                            $res = app('es')->search($params);
                            $filterData = $esService->getDataByEs($res);


                            Log::info('filterData', ['filterData' => $filterData]);

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
                    }elseif ($condition == "包含") {
                        //Log::info('fdata=>',['f'=>$fdata]);
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
                                        'fdata' => $fdata1,
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
                                    Log::info('测试包含', ['fdata-v' => $fdata, 'value-v' => $value]);
                                    if (strpos($fdata, $value) !== false) {
                                        $conditionMet = true;
                                        $v = $value;
                                        break;
                                    }
                                    //Log::info('包含', ['value' => $value, 'fdata' => $fdata, 'conditionMet' => $conditionMet]);
                                }
                            } else {
                                // 如果content2不包含逗号,则直接判断是否包含
                                $conditionMet = strpos($fdata, $content2) !== false;
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
                                    'fdata' => $fdata,
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
                                        if (strpos($fdata1, $value) === false) {
                                            $conditionMet = true;
                                        }else{
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
                                        'fdata' => $fdata1,
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
                                    if (strpos($fdata, $value) === false) {
                                        $conditionMet = true;
                                    }else{
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
                                    'fdata' => $fdata,
                                    'conditionMet' => $conditionMet,
                                    'condition' => $condition
                                ];
                            //}
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
                        $conditionMet = ($condition == "等于" && $fdata == $content2) ||
                            ($condition == "不等于" && $fdata != $content2) ||
                            ($condition == "大于" && $fdata > $content2) ||
                            ($condition == "小于" && $fdata < $content2) ||
                            ($condition == "大于等于" && $fdata >= $content2) ||
                            ($condition == "小于等于" && $fdata <= $content2) ||
                            ($condition == "不为空" && $this->hasRuleValue($fdata));
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

                    // 根据 detail_status 判断是否需要质控
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



            if (!$ruleFlag) {
                $score += $r['score'];
                if (!empty($customBasis)) {
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
                        foreach ($basisData as $basisdata) {
                            if (!empty($cb['param1']) && $basisdata['es_name'] == $cb['param1'][0] && $basisdata['field_name'] == $cb['param1'][1]) {
                                //如果$basis['fdata']长度过长(超过20个汉字)就只展示一部分
                                $con = is_array($basisdata['fdata']) ? $basisdata['fdata'][0] : $basisdata['fdata'];
                                if (mb_strlen($con) > 20) {
                                    $con = mb_substr($con, 0, 20) . '...';
                                }
                                $input1 = '';
                                if (!empty($cb['input1'])) {
                                    $input1 = $cb['input1'];
                                } else {
                                    $input1 = '';
                                }
                                $str = $input1 . '【' . $con . '】' . $cb['input2'];
                                $basis[] = ['desc' => $str, 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                            }else{
                                $input1 = '';
                                if (!empty($cb['input1'])) {
                                    $input1 = $cb['input1'];
                                } else {
                                    $input1 = '';
                                }
                                $str = $input1 . '' . $cb['input2'];
                                $basis[] = ['desc' => $str, 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                            }
                        }
                        //}
                    }
                }
                try {
                    /* $field = PrimaryKeyControl::query()->where('tableName', '=', 'patient_info')->first()->toArray()['field'];
                    $info = DataSyncService::getInstance()->setByNameSql('patient_info', 1)
                        ->setWhere($field, $ZYH)
                        ->getResult();
                    $info = $info[0]; */
                    //Log::info($info, ['info' => $info]);
                    $insertData = [];
                    if($type == 1){
                        $insertData[] = [
                            'hospital_name' => $data['data']['hospital_name'] ?? '',
                            'AAA28' => $data['data']['AAA28'] ?? '',
                            'ZYH' => $ZYH ?? '',
                            'AAC01' => $data['data']['AAC01'] ?? '',
                            'error_rule' => $currentRuleId,
                            'basis' => $basis,
                        ];
                    }elseif($type == 2){
                        $basisstr = '';
                        foreach ($basis as $key => $value) {
                            $basisstr .= $value['desc'].',';
                        }
                        $basisstr = rtrim($basisstr, ',');
                        $insertData[] = [
                            'hospital_name' => $data["USERNAME"] ?? '',
                            'AAA28' => $data["BAH"] ?? '',
                            'ZYH' => $ZYH ?? '',
                            'AAB01' => $data["RYSJ"] ?? '',
                            'AAC01' => $data["AAC01"] ?? '',
                            'AAA01' => $data["XM"] ?? '',
                            'CYKB' => $data["CYKB"] ?? '',
                            'error_rule' => $currentRuleId,
                            'desc' => $r['description'],
                            'basis' => $basisstr,
                        ];
                    }
                    
                    Log::info($insertData, ['insertData' => $insertData]);
                } catch (\Exception $e) {
                    Log::error('Error creating error notice', [
                        'error' => $e->getMessage(),
                        'rule_id' => $currentRuleId,
                        'ZYH' => $ZYH
                    ]);
                }
            }
        }

        //$errorNotice = array_filter($errorNotices);
        $insertData = array_filter($insertData);
        Log::info('自定义首页质控准备入库', [
            'ZYH' => $ZYH,
            'type' => $type,
            'rule_count' => isset($ruleSetting) ? count($ruleSetting) : 0,
            'insert_count' => count($insertData),
            'insert_rules' => array_map(function ($item) {
                return $item['error_rule'] ?? null;
            }, $insertData),
        ]);
        $customInsertedCount = 0;
        if ($insertData) {
            foreach ($insertData as $v) {
                try {
                    $v['error_rule'] = $v['error_rule'] + 1000000;
                    //CaseQuality::addData($v);
                    //HomeQuality::query()->where('ZYH', '=', $ZYH)->update(['is_del' => 1]);
                    Log::info($insertData, ['insertData-v' => $v]);
                    if (!empty($v)) {
                        // 确保basis字段被正确编码为JSON字符串
                        if (isset($v['basis']) && is_array($v['basis'])) {
                            $v['basis'] = json_encode($v['basis'], JSON_UNESCAPED_UNICODE);
                        }

                        if (isset($v['desc']) && is_array($v['desc'])) {
                            $v['desc'] = json_encode($v['desc'], JSON_UNESCAPED_UNICODE);
                        }

                        // 添加时间戳和调试信息
                        $v['created_at'] = now();
                        $v['updated_at'] = now();

                        Log::info('准备插入数据', ['data' => $v]);

                        $result = null;
                        if($type == 1){
                            $result = HomeQuality::query()->insert($v);
                        }elseif($type == 2){
                            $result = ErrorV2::query()->insert($v);
                        }

                        

                        Log::info('插入结果', ['result' => $result, 'data' => $v]);

                        if (!$result) {
                            Log::error('插入失败', ['data' => $v]);
                        } else {
                            $customInsertedCount++;
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('HomeQuality addData error', [
                        'error' => $e->getMessage(),
                        'data' => $v,
                        'error_rule' => $v['error_rule']
                    ]);
                }
            }
        }
        Log::info('自定义首页质控入库完成', [
            'ZYH' => $ZYH,
            'type' => $type,
            'inserted_count' => $customInsertedCount,
        ]);

        //$this->syncHomeQualityEs($info['MED_REC_ID'], $insertData);

        return $score;
    }

    protected function hasRuleValue($value)
    {
        if ($value === 0 || $value === '0') {
            return true;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->hasRuleValue($item)) {
                    return true;
                }
            }

            return false;
        }

        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return !empty($value);
    }

    /**
     * 质控结果同步到Es
     * @param $ZYH
     * @param $errorRuleData
     * @return true
     */
    public function syncHomeQualityEs($ZYH, $errorRuleData)
    {
        $errorRuleData = RuleSetting::query()->where('status', '=', 1)->where('rule_type', '=', '首页规则')->get()->toArray();
        //$ruleSetting = RuleSetting::query()->where('status', '=', 1)->where('rule_type', '=', '首页规则')->get()->toArray();
        $errorRuleData = array_column($errorRuleData, null, 'id');

        $homeQualityData = HomeQuality::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($homeQualityData)) {
            return true;
        }

        $es_params1 = [];
        $es_params2 = [];
        foreach ($homeQualityData as $k => $value) {
            $errorrule = $value['error_rule'];
            if (empty($errorRuleData[$errorrule])) {
                continue;
            }
            //基本信息设置0,诊疗信息设置1,费用信息设置2
            $type = $errorRuleData[$errorrule]['department'][0];
            if ($type == 0) {
                $type = 1;
            } else if ($type == 1) {
                $type = 2;
            }
            //1设置0,2设置1
            $level = $errorRuleData[$errorrule]['error_level'];
            if ($level == 1) {
                $level = 0;
            } else if ($level == 2) {
                $level = 1;
            }
            //A类0,B类1,C类2,D类3
            $category = $errorRuleData[$errorrule]['object'];
            if ($category == 'A类') {
                $category = 0;
            } else if ($category == 'B类') {
                $category = 1;
            } else if ($category == 'C类') {
                $category = 2;
            } else if ($category == 'D类') {
                $category = 3;
            }
            if ($value['is_del'] == 1) {
                $es_params1['body'][] = ['delete' => ['_index' => 'home_quality', '_id' => $value['id']]];
            } else {
                $es_params2['body'][] = ['update' => ['_index' => 'home_quality', '_id' => $value['id']]];
                $es_params2['body'][] = ['doc' => [
                    'data_id' => $value['id'],
                    'hospital_name' => $value['hospital_name'],
                    'AAA28' => $value['AAA28'],
                    'ZYH' => $ZYH,
                    'AAC01' => $value['AAC01'],
                    'error_rule' => $value['error_rule'],
                    'field' => $errorRuleData[$errorrule]['case_type'],
                    'field_name' => $errorRuleData[$errorrule]['case_type'],
                    'desc' => $errorRuleData[$errorrule]['description'],
                    'level' => $level,
                    'type' => $type,
                    'down' => $errorRuleData[$errorrule]['score'],
                    'error_type' => '1',
                    'category' => $category,
                    'ZKDX' => '0',
                    'ZKFL' => '0',
                    //'field','field_name','desc','level','type','down','error_type','category','ZKDX','ZKFL'
                    'basis' => $value['basis'],
                    'AAC11C' => $value['AAC11C'],
                    'AEE03_CODE' => $value['AEE03_CODE'],
                    'AEE04_CODE' => $value['AEE04_CODE'],
                    'AEE08_CODE' => $value['AEE08_CODE'],
                    'is_del' => $value['is_del'],
                    'ICD10_ID1' => $value['ICD10_ID1'],
                    'ICD10_NAME' => $value['ICD10_NAME'],
                    'ICD9_ID1' => $value['ICD9_ID1'] ?? "",
                    'ICD9_NAME' => $value['ICD9_NAME'],
                    'YQ_CODE' => $value['YQ_CODE'],
                    'YQ_NAME' => $value['YQ_NAME'] ?? '',
                    'AAC02C' => $value['AAC02C'],
                    'is_CATA' => $value['is_CATA'],
                    'in_hospital' => $value['in_hospital'],
                ], 'doc_as_upsert' => true];
            }
        }
        if ($es_params1) {
            app('es')->bulk($es_params1);
        }
        if ($es_params2) {
            app('es')->bulk($es_params2);
        }

        return true;
    }

    /**
     * 将秒数转换为可读的时间格式（小时和分钟）
     * @param int $seconds 秒数
     * @return string 格式化后的时间字符串
     */
    private function formatRemainingTime($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        $result = '';
        if ($hours > 0) {
            $result .= $hours . '小时';
        }
        if ($minutes > 0 || $hours == 0) {
            $result .= $minutes . '分钟';
        }

        return $result;
    }
}
