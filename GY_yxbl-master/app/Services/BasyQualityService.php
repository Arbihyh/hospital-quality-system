<?php

namespace App\Services;

use App\Jobs\BmyAsynData;
use App\Model\Appeal;
use App\Model\DRG2;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Model\ErrorRule;
use App\Model\HomeQuality;
use App\Model\ICD10;
use App\Model\ICD9;
use App\Model\PatientAddressInfo;
use App\Model\PatientContactsInfo;
use App\Model\PatientInfo;
use App\Model\PatientWorkInfo;
use App\Model\RuleWordMap;
use App\Model\Staff;
use App\Model\ZY_BRRY;
use Illuminate\Support\Facades\Log;

class BasyQualityService
{
    public static $noPhone = [11111111111, 12345678911, 1111111, 1234567];

    /**
     * 病案首页质控
     * @param $data
     * @param $errorRuleData
     * @return bool
     */
    public function qualityContrl($data, $errorRuleData)
    {
        // 姓名脱敏
        $AAA01 = !empty($data['data']['AAA01']) ? desensitize($data['data']['AAA01'], 1, 1, '*') : '';

        // 住院号
        $ZYH = $data['data']['MED_REC_ID'];

        // 主要诊断
        $ICD10 = ['ICD10_ID1' => '', 'ICD10_NAME' => ''];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $ICD10 = ['ICD10_ID1' => $diagnosis['ZDBM'], 'ICD10_NAME' => $diagnosis['ZDMC']];
                break;
            }
        }

        // 主要手术
        $ICD9 = ['ICD9_ID1' => '', 'ICD9_NAME' => ''];
        foreach ($data['operation'] as $operation) {

            if ($operation['SFZYSS'] == '1') {
                $ICD9 = ['ICD9_ID1' => $operation['SSCZBM'], 'ICD9_NAME' => $operation['SSCZMC']];
                break;
            }
        }

        $insertData = [];
        $homeBmyScore = 100;
        $score = 0;
        $isQz = 0;

        $appealRuleIds = [];
        $appeal = Appeal::query()
            ->where(["quality_type" => 1, "type" => 2, "ZYH" => $ZYH])->get(["error_id"])
            ->whereIn("status", ['1', '3'])
            ->toArray();
        if ($appeal) {
            $appealRuleIds = array_column($appeal, "error_id");
        }
        // 删除历史质控数据
        $appealRuleIds = array_values(array_filter($appealRuleIds));

        foreach ($errorRuleData as $ruleId => $errorRule) {
            if (in_array($ruleId, $appealRuleIds)) {
                continue;
            }
            // 跳过字典中设置的
            if (!empty($errorRuleData[$ruleId . "_" . $ZYH])) {
                continue;
            }
            // 质控方法
            $method = 'rule' . $ruleId;

            if (!method_exists(new BasyQualityService(), $method)) {
                continue;
            }
            // 质控
            $res = $this->$method($data);
            if (!empty($res)) {
                //如果是强制,isQz+1
                if ($errorRule['level'] == 0) {
                    $isQz++;
                }
                Log::info('AAA28-----------', ['errorRule' => $data['data']['AAA28']]);
                $score += $errorRule['down'];
                $homeBmyScore -= $errorRule['down']; //扣分
                $hospitalName = $data['data']['HOSPITAL_NAME'] ?? '';
                $basis = $res === true ? '' : json_encode($res, 256);
                $insertData[] = [
                    'hospital_name' => $hospitalName,
                    'AAA28' => $data['data']['AAA28'] ?? '',
                    'ZYH' => $ZYH,
                    'AAC01' => $data['data']['AAC01'] ?? '',
                    'error_rule' => $ruleId,
                    'basis' => $basis,
                    'AAC11C' => $data['data']['AAC11C'] ?? '',
                    'AEE03_CODE' => $data['data']['AEE03_CODE'] ?? '',
                    'AEE04_CODE' => $data['data']['AEE04_CODE'] ?? '',
                    'AEE08_CODE' => $data['data']['AEE08'] ?? '',
                    'ICD10_ID1' => $ICD10['ICD10_ID1'],
                    'ICD10_NAME' => $ICD10['ICD10_NAME'],
                    'ICD9_ID1' => $ICD9['ICD9_ID1'],
                    'ICD9_NAME' => $ICD9['ICD9_NAME'],
                    'YQ_CODE' => $data['data']['YQ_CODE'],
                    'AAC02C' => $data['data']['AAC02C'],
                    'AEE01_CODE' => $data['data']['AEE01_CODE'] ?? "",
                    'AEE02_CODE' => $data['data']['AEE02_CODE'] ?? "",
                    'is_CATA' => $data['data']['is_CATA'] ?? 0,
                    'in_hospital' => $data['data']['in_hospital'] ?? 0,
                    'is_del' => 0,
                ];
            }
        }
        // 更新用户信息
        $patientInfoInsertData = [
            'hospital_name' => $data['data']['HOSPITAL_NAME'] ?? '',
            'AAA01' => $AAA01,
            'AAA28' => $data['data']['AAA28'] ?? '',
            'AAB01' => $data['data']['AAB01'] ?? '',
            'AAC01' => $data['data']['AAC01'] ?? '',
            'home_bmy_score' => $homeBmyScore,
            'AAC11C' => $data['data']['AAC11C'] ?? '',
            'AEE03_CODE' => $data['data']['AEE03_CODE'] ?? '',
            'AEE04_CODE' => $data['data']['AEE04_CODE'] ?? '',
            'AEE08_CODE' => $data['data']['AEE08'] ?? '',
            'ICD10_NAME' => $ICD10['ICD10_NAME'],
            'ICD9_NAME' => $ICD9['ICD9_NAME'],
            'updated_at' => date('Y-m-d H:i:s', time()),
            'YQ_CODE' => $data['data']['YQ_CODE'],
            'AAC02C' => $data['data']['AAC02C'],
        ];
        PatientInfo::query()->updateOrInsert(['MED_REC_ID' => $ZYH], $patientInfoInsertData);

        $home_bmy_score_lv = "优";
        if ($homeBmyScore >= 97) {
            $home_bmy_score_lv = '优';
        } elseif ($homeBmyScore >= 90 && $homeBmyScore < 97) {
            // 注意：这里需要检查A类错误的逻辑，如果你有相关字段
            $home_bmy_score_lv = '良';
        } elseif ($homeBmyScore >= 75 && $homeBmyScore < 90) {
            // 注意：这里需要检查A类错误的逻辑，如果你有相关字段
            $home_bmy_score_lv = '中';
        } elseif ($homeBmyScore < 75) {
            $home_bmy_score_lv = '差';
        }
        ZY_BRRY::query()->updateOrInsert(['ZYH' => $ZYH], ["home_bmy_score" => $homeBmyScore, "home_bmy_score_lv" => $home_bmy_score_lv]);

        // 质控结果处理
        $existingHomeQualityCount = HomeQuality::where('ZYH', '=', $ZYH)->count();
        Log::info('首页质控结果准备刷新', [
            'ZYH' => $ZYH,
            'existing_count' => $existingHomeQualityCount,
            'new_count' => count($insertData),
            'new_rules' => array_column($insertData, 'error_rule'),
            'score' => $score,
            'isQz' => $isQz,
        ]);
        HomeQuality::where('ZYH', '=', $ZYH)->delete();
        $appealids = [];
        $insertedCount = 0;
        if (!empty($insertData)) {
            foreach ($insertData as $key => $v) {
                $appeal1 = Appeal::where('ZYH', $v['ZYH'])->where('error_id', $v['error_rule'])->get()->toArray();
                if (!empty($appeal1)) {
                    $insertData[$key]['appeal_id'] = $appeal1[0]['id'];
                    $insertData[$key]['is_appeal'] = 1;
                    $appealids[] = $appeal1[0]['id'];
                } else {
                    $insertData[$key]['appeal_id'] = 0;
                    $insertData[$key]['is_appeal'] = 0;
                }
            }
            HomeQuality::query()->insert($insertData);
            $insertedCount = count($insertData);
        }
        $finalHomeQualityCount = HomeQuality::where('ZYH', '=', $ZYH)->count();
        Log::info('首页质控结果刷新完成', [
            'ZYH' => $ZYH,
            'deleted_count' => $existingHomeQualityCount,
            'inserted_count' => $insertedCount,
            'final_count' => $finalHomeQualityCount,
        ]);

        //Appeal::whereNotIn('id', $appealids)->where('ZYH', $ZYH)->update(['status' => 3]);
        if (empty($appealids)) {
            Appeal::where('ZYH', $ZYH)->where('status', '=', '0')->update(['status' => 3]);
        } else {
            Appeal::whereNotIn('id', is_array($appealids) ? $appealids : [$appealids])->where('ZYH', $ZYH)->where('status', '=', '0')->update(['status' => 3]);
        }

        //质控结束脱敏处理
        //patient_info表脱敏
        $update = [];
        $update['AAA01'] = !empty($data['data']['AAA01']) ? desensitize($data['data']['AAA01'], 1, 1, '*') : '';
        $update['AAA07'] = !empty($data['data']['AAA07']) ? desensitize($data['data']['AAA07'], 6, 8, '*') : '';
        if (!empty($update)) PatientInfo::query()->where('MED_REC_ID', '=', $ZYH)->update($update);
        //patient_address_info表脱敏
        $addressUpdate = [];
        $addressUpdate['AAA51'] = !empty($data['data']['AAA51']) ? desensitize($data['data']['AAA51'], 3, 4, '*') : '';
        if (!empty($addressUpdate)) PatientAddressInfo::query()->where('AAA28', '=', $ZYH)->update($addressUpdate);
        //patient_contacts_info表脱敏
        $contactsUpdate = [];
        $contactsUpdate['AAA22'] = !empty($data['data']['AAA22']) ? desensitize($data['data']['AAA22'], 1, 1, '*') : '';
        $contactsUpdate['AAA25'] = !empty($data['data']['AAA25']) ? desensitize($data['data']['AAA25'], 3, 4, '*') : '';
        if (!empty($contactsUpdate)) PatientContactsInfo::query()->where('AAA28', '=', $ZYH)->update($contactsUpdate);
        //patient_work_info表脱敏
        $workUpdate = [];
        $workUpdate['AAA20'] = !empty($data['data']['AAA20']) ? desensitize($data['data']['AAA20'], 3, 4, '*') : '';
        if (!empty($workUpdate)) PatientWorkInfo::query()->where('AAA28', '=', $ZYH)->update($workUpdate);


        // 质控结果同步到Es
        
        //$this->syncHomeQualityEs($ZYH, $errorRuleData);
        //Log::info('ZYH删除',['ZYH'=>$ZYH]);
        //HomeQuality::query()->where('ZYH', '=', $ZYH)->where('is_del', '=', 1)->delete();
        //Log::info('ZYH删除完毕',['ZYH'=>$ZYH]);
        $isQz = ['isQz' => $isQz, 'score' => $score];
        //Log::info('质控结果', ['isQz' => $isQz]);
        return $isQz;
    }

    /**
     * 质控结果同步到Es
     * @param $ZYH
     * @param $errorRuleData
     * @return true
     */
    public function syncHomeQualityEs($ZYH, $errorRuleData)
    {
        $errorRuleData = ErrorRule::query()->get()->toArray();
        $errorRuleData = array_column($errorRuleData, null, 'id');

        $homeQualityData = HomeQuality::query()->where('ZYH', '=', $ZYH)->get()->toArray();
        if (empty($homeQualityData)) {
            return true;
        }

        $es_params1 = [];
        $es_params2 = [];
        //删除es
        try {
            $params = [
                'index' => 'home_quality',
                'body' => [
                    'query' => [
                        'term' => [
                            'ZYH' => $ZYH
                        ]
                    ]
                ]
            ];
            app('es')->deleteByQuery($params);
        } catch (\Exception $e) {
            Log::error("删除ES质控数据失败:" . $e->getMessage());
        }

        foreach ($homeQualityData as $k => $value) {
            if (empty($errorRuleData[$value['error_rule']])) {
                continue;
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
                    'field' => $errorRuleData[$value['error_rule']]['auth'],
                    'field_name' => $errorRuleData[$value['error_rule']]['field'],
                    'desc' => $errorRuleData[$value['error_rule']]['desc'],
                    'level' => $errorRuleData[$value['error_rule']]['level'],
                    'type' => $errorRuleData[$value['error_rule']]['type'],
                    'down' => $errorRuleData[$value['error_rule']]['down'],
                    'error_type' => $errorRuleData[$value['error_rule']]['error_type'],
                    'category' => $errorRuleData[$value['error_rule']]['category'],
                    'ZKDX' => $errorRuleData[$value['error_rule']]['ZKDX'],
                    'ZKFL' => $errorRuleData[$value['error_rule']]['ZKFL'],
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
     * 组织机构代码
     * @param $data
     * @return array
     */
    public function rule1($data)
    {
        $basis = [];
        $UNT_ID = !empty($data['data']['UNT_ID']) ? trim($data['data']['UNT_ID']) : '';
        if (mb_strlen($UNT_ID) < 6 || mb_strlen($UNT_ID) > 22 || $UNT_ID == 123456) {
            $basis[] = ['desc' => '组织机构代码应在6~22位之间', 'location' => ['user' => ['UNT_ID'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 病案号
     * @param $data
     * @return array
     */
    public function rule2($data)
    {
        $basis = [];
        $AAA28 = !empty($data['data']['AAA28']) ? trim($data['data']['AAA28']) : '';
        if (mb_strlen($AAA28) < 6 || mb_strlen($AAA28) > 50) {
            $basis[] = ['desc' => '住院号码应在6~50位之间', 'location' => ['user' => ['AAA28'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 医疗机构名称
     * @param $data
     * @return array
     */
    public function rule3($data)
    {
        $basis = [];
        $ZA03 = !empty($data['data']['ZA03']) ? trim($data['data']['ZA03']) : '';
        $res = preg_match('/^[\x7f-\xff]+$/', $ZA03);
        if (mb_strlen($ZA03) < 4 || mb_strlen($ZA03) > 80 || !$res) {
            $basis[] = ['desc' => '医疗机构名称应在4~80位之间，只能是汉字', 'location' => ['user' => ['ZA03'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 住院次数
     * @param $data
     * @return array
     */
    public function rule4($data)
    {
        $basis = [];
        if (!preg_match("/^[1-9][0-9]*$/", $data['data']['AAA29'])) {
            $basis[] = ['desc' => '住院次数只能填写大于0的正整数', 'location' => ['user' => ['AAA29'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 入院时间
     * @param $data
     * @return array
     */
    public function rule5($data)
    {
        if (empty($data['data']['AAB01']) || empty($data['data']['AAC01']) || stripos($data['data']['AAC01'], "1970") !== false || stripos($data['data']['AAC01'], "0000") !== false) {
            return false;
        }
        $AAB01 = $data['data']['AAB01'] ? date('Y-m-d', strtotime($data['data']['AAB01'])) : '';
        $AAC01 = $data['data']['AAC01'] ? date('Y-m-d', strtotime($data['data']['AAC01'])) : '';

        $basis = [];
        if (empty($AAB01)) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['AAB01']]];
        } elseif ($AAB01 > $AAC01) {
            $basis[] = ['desc' => '入院时间【' . $AAB01 . '】不能大于出院时间【' . $AAC01 . '】', 'location' => ['user' => ['AAB01', 'AAC01'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 健康卡号
     * @param $data
     * @return array
     */
    public function rule6($data)
    {
        $JKKH = !empty($data['other']['JKKH']) ? $data['other']['JKKH'] : '';
        $basis = [];
        if (empty($JKKH)) {
            $basis[] = ['desc' => '尚未发送“健康卡”的地区填写“-”', 'location' => ['user' => ['JKKH'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 患者姓名
     * @param $data
     * @return array
     */
    public function rule7($data)
    {
        $XM = !empty($data['data']['AAA01']) ? trim($data['data']['AAA01']) : '';
        $basis = [];
        if (mb_strlen($XM) < 2 || mb_strlen($XM) > 40) {
            $basis[] = ['desc' => '患者姓名长度应在2~40之间', 'location' => ['user' => ['AAA01'], 'zd' => [], 'ss' => []]];
        } else {
            // 获取第一位
            $firstStr = mb_substr($XM, 0, 1);
            // 获取最后一位
            $xmLen = mb_strlen($XM);
            $endStr = mb_substr($XM, $xmLen - 1, 1);

            // 验证是否有标点符号
            $res2 = PublicService::pregMatchTszf($firstStr, 'punct');
            $res4 = PublicService::pregMatchTszf($endStr, 'punct');

            // 验证是否有数字
            $res1 = PublicService::pregMatchTszf($firstStr, 'digit');
            $res3 = PublicService::pregMatchTszf($endStr, 'digit');

            // 验证是否有空格
            $res5 = PublicService::pregMatchTszf($XM, 'space');

            if ($res2 || $res4) {
                $basis[] = ['desc' => '患者姓名中有标点符号', 'location' => ['user' => ['AAA01'], 'zd' => [], 'ss' => []]];
            } elseif ($res1 || $res3) {
                $basis[] = ['desc' => '患者姓名中有数字', 'location' => ['user' => ['AAA01'], 'zd' => [], 'ss' => []]];
            } elseif ($res5) {
                $basis[] = ['desc' => '患者姓名中有空格', 'location' => ['user' => ['AAA01'], 'zd' => [], 'ss' => []]];
            }
        }

        return $basis;
    }

    /**
     * 出生地省（不能为空，不能全是数字，不能是空格）
     * @param $data
     * @return array
     */
    public function rule8($data)
    {
        $AAA09 = !empty($data['data']['AAA09']) ? trim($data['data']['AAA09']) : '';
        $AAA09 = str_replace(' ', '', $AAA09);
        $basis = [];
        if (mb_strlen(trim($AAA09)) < 2) {
            $basis[] = ['desc' => '出生地省长度不能小于2位', 'location' => ['user' => ['AAA09']]];
        } elseif (preg_match("/^[0-9]*$/", $AAA09)) {
            $basis[] = ['desc' => '出生地省不能全是数字', 'location' => ['user' => ['AAA09'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 籍贯省
     * @param $data
     * @return array
     */
    public function rule9($data)
    {
        $AAA43 = !empty($data['data']['AAA43']) ? trim($data['data']['AAA43']) : '';
        $AAA43 = str_replace(' ', '', $AAA43);
        $basis = [];
        if (mb_strlen(trim($AAA43)) < 2) {
            $basis[] = ['desc' => '籍贯省长度不能小于2位', 'location' => ['user' => ['AAA43'], 'zd' => [], 'ss' => []]];
        } elseif (preg_match("/^[0-9]*$/", $AAA43)) {
            $basis[] = ['desc' => '籍贯省不能全是数字', 'location' => ['user' => ['AAA43'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 民族
     * @param $data
     * @return array
     */
    public function rule10($data)
    {
        $AAA06C = !empty($data['data']['AAA06C']) ? trim($data['data']['AAA06C']) : '';
        $basis = [];
        if (empty($AAA06C)) {
            $basis[] = ['desc' => '民族不能为空', 'location' => ['user' => ['AAA06C'], 'zd' => [], 'ss' => []]];
        } elseif ($AAA06C == '-') {
            $basis[] = ['desc' => '民族不能是“-”', 'location' => ['user' => ['AAA06C'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 身份证号
     * @param $data
     * @return array
     */
    public function rule11($data)
    {
        $AAA07 = !empty($data['data']['AAA07']) ? trim($data['data']['AAA07']) : '';
        $NL = !empty($data['data']['AAA04']) ? trim($data['data']['AAA04']) : 0;

        //只保留数字
        $NL = preg_replace('/[^0-9]/', '', $NL);
        //如果年龄小于等于1就跳过
        $basis = [];
        if ($NL <= 1) {
            return $basis;
        }
        if ($data['data']['AAC11C'] != 287 && mb_strlen($AAA07) != 15 && mb_strlen($AAA07) != 18) {
            $basis[] = ['desc' => '身份证号只能是15位或18位', 'location' => ['user' => ['AAA07'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 职业
     * @param $data
     * @return array
     */
    public function rule12($data)
    {
        $AAA18C = $data['data']['AAA18C'] ?? '';
        $basis = [];
        if (mb_strlen($AAA18C) < 1) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['AAA18C'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 婚姻状况
     * @param $data
     * @return array
     */
    public function rule13($data)
    {
        $AAA08C = $data['data']['AAA08C'] ?? '';
        $basis = [];
        if (mb_strlen($AAA08C) < 1) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['AAA08C'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 现住址省
     * @param $data
     * @return array
     */
    public function rule14($data)
    {
        $AAA48 = !empty($data['data']['AAA48']) ? trim($data['data']['AAA48']) : '';
        $AAA48 = str_replace(' ', '', $AAA48);
        $basis = [];
        if (mb_strlen($AAA48) < 2) {
            $basis[] = ['desc' => '现住址省不能少于2位', 'location' => ['user' => ['AAA48'], 'zd' => [], 'ss' => []]];
        } elseif (preg_match("/^[0-9]*$/", $AAA48)) {
            $basis[] = ['desc' => '出生地省不能全是数字', 'location' => ['user' => ['AAA48'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 现住址电话
     * @param $data
     * @return array
     */
    public function rule15($data)
    {
        $AAA51 = !empty($data['data']['AAA51']) ? trim($data['data']['AAA51']) : '';

        $basis = [];
        if ($AAA51 == '-') {
            return $basis;
        }

        if (mb_strlen($AAA51) < 7) {
            $basis[] = ['desc' => '现住址电话不能少于7位', 'location' => ['user' => ['AAA51'], 'zd' => [], 'ss' => []]];
        } elseif (in_array($AAA51, self::$noPhone)) {
            $basis[] = ['desc' => '现住址电话填写不规范', 'location' => ['user' => ['AAA51'], 'zd' => [], 'ss' => []]];
        } else {
            $res1 = preg_match('/^1[0-9]{10}$/', $AAA51);
            $res2 = preg_match('/^[0-9]{4}[\-][0-9]{7}$/', $AAA51);
            $res3 = preg_match('/^[0-9]{7}$/', $AAA51);
            if (!$res1 && !$res2 && !$res3) {
                $basis[] = ['desc' => '现住址电话应是7位或11位', 'location' => ['user' => ['AAA51'], 'zd' => [], 'ss' => []]];
            }
        }

        return $basis;
    }

    /**
     * 现住址邮政编码
     * @param $data
     * @return array
     */
    public function rule16($data)
    {
        $AAA17C = !empty($data['data']['AAA17C']) ? trim($data['data']['AAA17C']) : '';
        $AAA17C = str_replace(' ', '', $AAA17C);
        $basis = [];
        if (mb_strlen($AAA17C) != 6) {
            $basis[] = ['desc' => '请填写6位现住址邮政编码', 'location' => ['user' => ['AAA17C']]];
        } elseif ($AAA17C == 123456) {
            $basis[] = ['desc' => '现住址邮政编码不能是：123456', 'location' => ['user' => ['AAA17C'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 户籍省
     * @param $data
     * @return array
     */
    public function rule17($data)
    {
        $AAA45 = !empty($data['data']['AAA45']) ? trim($data['data']['AAA45']) : '';
        $AAA45 = str_replace(' ', '', $AAA45);
        $basis = [];
        if (mb_strlen($AAA45) < 2) {
            $basis[] = ['desc' => '户籍省不能少于2位', 'location' => ['user' => ['AAA45'], 'zd' => [], 'ss' => []]];
        } elseif (preg_match("/^[0-9]*$/", $AAA45)) {
            $basis[] = ['desc' => '户籍省不能全是数字', 'location' => ['user' => ['AAA45'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 户口邮编
     * @param $data
     * @return array
     */
    public function rule18($data)
    {
        $AAA14C = !empty($data['data']['AAA14C']) ? trim($data['data']['AAA14C']) : '';
        $AAA14C = str_replace(' ', '', $AAA14C);
        $basis = [];
        if (mb_strlen($AAA14C) != 6) {
            $basis[] = ['desc' => '请填写6位户口邮编', 'location' => ['user' => ['AAA14C'], 'zd' => [], 'ss' => []]];
        } elseif ($AAA14C == 123456) {
            $basis[] = ['desc' => '户口邮编不能是：123456', 'location' => ['user' => ['AAA14C'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 工作单位及地址
     * @param $data
     * @return array
     */
    public function rule19($data)
    {
        $AAA19 = !empty($data['data']['AAA19']) ? trim($data['data']['AAA19']) : '';
        $AAA19 = str_replace(' ', '', $AAA19);

        $basis = [];
        if ($AAA19 == '无' || $AAA19 == '-') {
            return $basis;
        }

        if (mb_strlen($AAA19) < 2) {
            $basis[] = ['desc' => '工作单位及地址不能少于2位', 'location' => ['user' => ['AAA19'], 'zd' => [], 'ss' => []]];
        } elseif (preg_match("/^[0-9]*$/", $AAA19)) {
            $basis[] = ['desc' => '工作单位及地址不能全是数字', 'location' => ['user' => ['AAA19'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 单位电话
     * @param $data
     * @return array
     */
    public function rule20($data)
    {
        $AAA20 = !empty($data['data']['AAA20']) ? trim($data['data']['AAA20']) : '';

        $basis = [];
        if ($AAA20 == '-' || $AAA20 == '无') {
            return $basis;
        }

        if (mb_strlen($AAA20) < 7) {
            $basis[] = ['desc' => '单位电话不能少于7位', 'location' => ['user' => ['AAA20'], 'zd' => [], 'ss' => []]];
        } elseif (in_array($AAA20, self::$noPhone)) {
            $basis[] = ['desc' => '单位电话填写不规范', 'location' => ['user' => ['AAA20'], 'zd' => [], 'ss' => []]];
        } else {
            $res1 = preg_match('/^1[0-9]{10}$/', $AAA20);
            $res2 = preg_match('/^[0-9]{4}[\-][0-9]{7}$/', $AAA20);
            $res3 = preg_match('/^[0-9]{7}$/', $AAA20);
            if (!$res1 && !$res2 && !$res3) {
                $basis[] = ['desc' => '单位电话应是7位或11位', 'location' => ['user' => ['AAA20'], 'zd' => [], 'ss' => []]];
            }
        }

        return $basis;
    }

    /**
     * 单位邮编
     * @param $data
     * @return array
     */
    public function rule21($data)
    {
        $AAA21C = !empty($data['data']['AAA21C']) ? trim($data['data']['AAA21C']) : '';
        $AAA21C = str_replace(' ', '', $AAA21C);
        $basis = [];
        if (mb_strlen($AAA21C) != 6) {
            $basis[] = ['desc' => '请填写6位单位邮编号码', 'location' => ['user' => ['AAA21C'], 'zd' => [], 'ss' => []]];
        } elseif ($AAA21C == 123456) {
            $basis[] = ['desc' => '单位邮编号码不能是：123456', 'location' => ['user' => ['AAA21C'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 联系人姓名
     * @param $data
     * @return array
     */
    public function rule22($data)
    {
        $AAA22 = !empty($data['data']['AAA22']) ? trim($data['data']['AAA22']) : '';
        $basis = [];
        if (mb_strlen($AAA22) < 2 || mb_strlen($AAA22) > 40) {
            $basis[] = ['desc' => '联系人姓名长度应在2~40之间', 'location' => ['user' => ['AAA22'], 'zd' => [], 'ss' => []]];
        } else {
            // 获取第一位
            $firstStr = mb_substr($AAA22, 0, 1);
            // 获取最后一位
            $xmLen = mb_strlen($AAA22);
            $endStr = mb_substr($AAA22, $xmLen - 1, 1);

            // 标点符号
            $res2 = PublicService::pregMatchTszf($firstStr, 'punct');
            $res4 = PublicService::pregMatchTszf($endStr, 'punct');

            // 数字
            $res1 = PublicService::pregMatchTszf($firstStr, 'digit');
            $res3 = PublicService::pregMatchTszf($endStr, 'digit');

            $res5 = PublicService::pregMatchTszf($AAA22, 'space');
            if ($res2 || $res4) {
                $basis[] = ['desc' => '联系人姓名中有标点符号', 'location' => ['user' => ['AAA22'], 'zd' => [], 'ss' => []]];
            } elseif ($res1 || $res3) {
                $basis[] = ['desc' => '联系人姓名中有数字', 'location' => ['user' => ['AAA22'], 'zd' => [], 'ss' => []]];
            } elseif ($res5) {
                $basis[] = ['desc' => '联系人姓名中有空格', 'location' => ['user' => ['AAA22'], 'zd' => [], 'ss' => []]];
            }
        }

        return $basis;
    }

    /**
     * 联系人关系
     * @param $data
     * @return array
     */
    public function rule23($data)
    {
        $basis = [];
        if (mb_strlen($data['data']['GX_MC']) < 1) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['AAA23C'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 联系人地址
     * @param $data
     * @return array
     */
    public function rule24($data)
    {
        $AAA24 = !empty($data['data']['AAA24']) ? trim($data['data']['AAA24']) : '';
        $AAA24 = str_replace(' ', '', $AAA24);
        $basis = [];
        if (mb_strlen($AAA24) < 2) {
            $basis[] = ['desc' => '联系人地址不能少于2位', 'location' => ['user' => ['AAA24'], 'zd' => [], 'ss' => []]];
        } elseif (preg_match("/^[0-9]*$/", $AAA24)) {
            $basis[] = ['desc' => '联系人地址不能全是数字', 'location' => ['user' => ['AAA24'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 联系人电话
     * @param $data
     * @return array
     */
    public function rule25($data)
    {
        $AAA25 = $data['data']['AAA25'] ?? '';

        $basis = [];
        if ($AAA25 == '-') {
            return $basis;
        }

        if (mb_strlen($AAA25) < 7) {
            $basis[] = ['desc' => '联系人电话不能少于7位', 'location' => ['user' => ['AAA25'], 'zd' => [], 'ss' => []]];
        } elseif (in_array($AAA25, self::$noPhone)) {
            $basis[] = ['desc' => '联系人电话填写不规范', 'location' => ['user' => ['AAA25'], 'zd' => [], 'ss' => []]];
        } else {
            $res1 = preg_match('/^1[0-9]{10}$/', $AAA25);
            $res2 = preg_match('/^[0-9]{4}[\-][0-9]{7}$/', $AAA25);
            $res3 = preg_match('/^[0-9]{7}$/', $AAA25);
            if (!$res1 && !$res2 && !$res3) {
                $basis[] = ['desc' => '联系人电话应是7位或11位0-9的数字组合', 'location' => ['user' => ['AAA25'], 'zd' => [], 'ss' => []]];
            }
        }

        return $basis;
    }

    /**
     * 性别
     * @param $data
     * @return array
     */
    public function rule26($data)
    {
        $basis = [];
        if (mb_strlen($data['data']['AAA02C']) < 1) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['AAA02C'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 出生日期
     * @param $data
     * @return array
     */
    public function rule27($data)
    {
        $AAA03 = !empty($data['data']['AAA03']) ? date("Ymd", strtotime($data['data']['AAA03'])) : '';
        $basis = [];
        if (mb_strlen($AAA03) < 1) {
            $basis[] = ['desc' => '出生日期未填写', 'location' => ['user' => ['AAA03'], 'zd' => [], 'ss' => []]];
        } else {
            $AAA07 = !empty($data['data']['AAA07']) ? mb_substr($data['data']['AAA07'], 6, 8) : '';
            if (empty($AAA07) || empty($AAA03)) {
                return $basis;
            }
            if ($AAA07 != $AAA03) {
                $basis[] = ['desc' => '出生日期与身份证号不匹配', 'location' => ['user' => ['AAA03', 'AAA07'], 'zd' => [], 'ss' => []]];
            }
        }
        return $basis;
    }

    /**
     * 年龄
     * @param $data
     * @return array
     */
    public function rule28($data)
    {
        $AAA04 = !empty($data['data']['AAA04']) ? trim($data['data']['AAA04']) : 0;
        $AAA40 = !empty($data['data']['AAA40']) ? trim($data['data']['AAA40']) : 0;
        $AAC11C = !empty($data['data']['AAC11C']) ? trim($data['data']['AAC11C']) : '';
        $basis = [];
        if ($AAA04 == 0 && $AAA40 == 0 && $AAC11C == '287') {
            return $basis;
        }

        $res1 = preg_match('/^[1-9][0-9]*$/', $data['data']['AAA04']);
        $arr = [];
        for ($i = 1; $i < 365; $i++) {
            $arr[] = $i;
        }

        if (!$res1 && !in_array($data['data']['AAA40'], $arr)) {
            $basis[] = ['desc' => '非新生儿科患者请核对年龄', 'location' => ['user' => ['AAA04', 'AAA40'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 国籍
     * @param $data
     * @return array
     */
    public function rule29($data)
    {
        $basis = [];
        if (mb_strlen($data['data']['AAA05C']) < 1) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['AAA05C'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 入院科别
     * @param $data
     * @return array
     */
    public function rule30($data)
    {
        $basis = [];
        if (mb_strlen($data['data']['AAB02C']) < 1) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['AAB02C'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 入院途径
     * @param $data
     * @return array
     */
    public function rule31($data)
    {
        $basis = [];
        if (mb_strlen($data['data']['AAB06C']) < 1) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['AAB06C'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 转诊医疗机构名称
     * @param $data
     * @return array
     */
    public function rule1517($data)
    {
        //        $AAB06C = !empty($data['data']['AAB06C']) ? $data['data']['AAB06C'] : '';
        //        $ZZYLJG = $data['ba_brsy']['ZZYLJG'] ? trim($data['ba_brsy']['ZZYLJG']) : '';

        $basis = [];
        //        if ($AAB06C == 3 && mb_strlen($ZZYLJG) < 2) {
        //            $basis[] = ['desc' => '', 'location' => ['user' => ['AAB06C', 'ZZYLJG'], 'zd' => [], 'ss' => []]];
        //        }

        if (in_array($data['data']['AEM01C'], [3]) && (empty($data['data']['AEM02']) || $data['data']['AEM02'] == '-' || $data['data']['AEM02'] == '无' || $data['data']['AEM02'] == '没有')) {
            $basis[] = ['desc' => '离院方式是【医嘱转院或医嘱转社区卫生服务机构/乡镇卫生院】接收医疗机构名称不能为空', 'location' => ['user' => ['AAB06C', 'ZZYLJG'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 入院病房
     * @param $data
     * @return array
     */
    public function rule32($data)
    {
        $basis = [];
        if (empty($data['data']['AAB11N'])) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['AAB11N'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 转科科别
     * @param $data
     * @return array
     */
    public function rule33($data)
    {
        $zyZkjlData = $data['zy_zkjl'];
        if (empty($zyZkjlData)) {
            if (!empty($data['data']['ZKKBMC'])) {
                return [['desc' => '转科科别填写错误', 'location' => ['user' => ['ZKKBMC'], 'zd' => [], 'ss' => []]]];
            }
            return [];
        }

        $res = false;
        foreach ($zyZkjlData as $value) {
            if (in_array($value['HCLX'], [1, 3])) {
                $res = true;
                break;
            }
        }

        $basis = [];
        if ($res && empty($data['data']['ZKKBMC'])) {
            $basis[] = ['desc' => '转科科别应填写【' . $data['data']['ZKKBMC'] . '】', 'location' => ['user' => ['ZKKBMC'], 'zd' => [], 'ss' => []]];
        } elseif (!$res && !empty($data['data']['ZKKBMC'])) {
            $basis[] = ['desc' => '【2.护士站转床 4:医生站换医生 5:护士站分配床位】不应填写转科科别', 'location' => ['user' => ['ZKKBMC'], 'zd' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 出院科别
     * @param $data
     * @return array
     */
    public function rule34($data)
    {
        $basis = [];
        if (empty($data['data']['AAC02C'])) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['AAC02C'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 实际住院天数
     * @param $data
     * @return array
     */
    public function rule36($data)
    {
        $basis = [];
        if (!preg_match('/^[1-9]\d*$/', $data['data']['AAC04'])) {
            $basis[] = ['desc' => '实际住院天数只能填写大于0的正整数', 'location' => ['user' => ['AAC04'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 门(急)诊诊断编码
     * @param $data
     * @return array
     */
    public function rule37($data)
    {
        $basis = [];
        if (empty($data['data']['ABA01C'])) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['ABA01C'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 门(急)诊诊断名称
     * @param $data
     * @return array
     */
    public function rule38($data)
    {
        $basis = [];
        if (empty($data['data']['ABA01N'])) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['ABA01N'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 出院主要诊断编码
     * @param $data
     * @return array
     */
    public function rule39($data)
    {
        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $ICD10_ID1 = $diagnosis['ZDBM'];
                break;
            }
        }

        $basis = [];
        if (empty($ICD10_ID1)) {
            $basis[] = ['desc' => '', 'location' => ['zd' => [['ZZPB' => 1, 'DIA_ORDER' => 1, 'field' => 'ICD10_ID1']], 'user' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 出院主要诊断名称
     * @param $data
     * @return array
     */
    public function rule40($data)
    {
        $ICD10_NAME = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $ICD10_NAME = $diagnosis['ZDMC'];
                break;
            }
        }

        $basis = [];
        if (empty($ICD10_NAME)) {
            $basis[] = ['desc' => '', 'location' => ['zd' => [['ZZPB' => 1, 'DIA_ORDER' => 1, 'field' => 'ICD10_NAME']], 'user' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 有无药物过敏
     * @param $data
     * @return array
     */
    public function rule42($data)
    {
        $basis = [];
        $AEB02C = $data['data']['AEB02C'];
        if ($AEB02C == '-') {
            return $basis;
        }
        //        $AEB02C_DATA = config('dictionaries.AEB02C');
        if (mb_strlen($AEB02C) < 1) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['AEB02C'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 科主任
     * @param $data
     * @return array
     */
    public function rule43($data)
    {
        $AEE01 = !empty($data['data']['AEE01']) ? trim($data['data']['AEE01']) : '';
        $basis = [];
        if (mb_strlen($AEE01) < 2 || mb_strlen($AEE01) > 40) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['AEE01'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 主(副主)任医师
     * @param $data
     * @return array
     */
    public function rule44($data)
    {
        $AEE02 = !empty($data['data']['AEE02']) ? trim($data['data']['AEE02']) : '';
        $basis = [];
        if (mb_strlen($AEE02) < 2 || mb_strlen($AEE02) > 40) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['AEE02'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 主治医师
     * @param $data
     * @return array
     */
    public function rule47($data)
    {
        $AEE03 = !empty($data['data']['AEE03']) ? trim($data['data']['AEE03']) : '';
        $basis = [];
        if (mb_strlen($AEE03) < 2 || mb_strlen($AEE03) > 40) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['AEE03'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 住院医师
     * @param $data
     * @return array
     */
    public function rule49($data)
    {
        $AEE04 = !empty($data['data']['AEE04']) ? trim($data['data']['AEE04']) : '';
        $basis = [];
        if (mb_strlen($AEE04) < 2 || mb_strlen($AEE04) > 40) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['AEE04'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 责任护士
     * @param $data
     * @return array
     */
    public function rule50($data)
    {
        $AEE10 = !empty($data['data']['AEE10']) ? trim($data['data']['AEE10']) : '';
        $basis = [];
        if (mb_strlen($AEE10) < 2 || mb_strlen($AEE10) > 40) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['AEE10'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 编码员
     * @param $data
     * @return array
     */
    public function rule51($data)
    {
        $basis = [];
        if (mb_strlen($data['data']['AEE08']) < 1) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['AEE08'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * ABO血型
     * @param $data
     * @return array
     */
    public function rule52($data)
    {
        $basis = [];
        if (empty($data['data']['AEG01C'])) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['AEG01C'], 'zd' => [], 'ss' => []]];
        }
        //        else {
        //            $AEG01C = config('dictionaries.AEG01C');
        //            if (empty($AEG01C[$data['data']['AEG01C']])) {
        //                $basis[] = ['desc'=>'ABO血型填写错误','location'=>['user'=>['AEG01C']]];
        //            }
        //        }
        return $basis;
    }

    /**
     * RH血型
     * @param $data
     * @return array
     */
    public function rule53($data)
    {
        $basis = [];
        if (empty($data['data']['AEG02C'])) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['AEG02C'], 'zd' => [], 'ss' => []]];
        }
        //        else {
        //            $AEG02C = config('dictionaries.AEG02C');
        //            if (empty($AEG02C[$data['data']['AEG02C']])) {
        //                $basis[] = ['desc'=>'RH血型填写错误','location'=>['user'=>['AEG02C']]];
        //            }
        //        }
        return $basis;
    }

    /**
     * 手术操作编码
     * @param $data
     * @return array
     */
    public function rule54($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $ssmcList = [];
        $ssList = [];
        foreach ($data['operation'] as $operation) {
            if (!empty($operation['SSCZMC']) && empty($operation['SSCZBM'])) {
                $ssmcList[] = $operation['SSCZMC'];
                $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }

        if (!empty($ssList)) {
            $basis[] = ['desc' => '手术【' . implode('、', $ssmcList) . '】操作编码未填写', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 手术操作名称
     * @param $data
     * @return array
     */
    public function rule55($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $ssbmList = [];
        $ssList = [];
        foreach ($data['operation'] as $operation) {
            if (!empty($operation['SSCZBM']) && empty($operation['SSCZMC'])) {
                $ssbmList[] = $operation['SSCZBM'];
                $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_NAME'];
            }
        }

        if (!empty($ssList)) {
            $basis[] = ['desc' => '手术【' . implode('、', $ssbmList) . '】手术名称未填写', 'location' => ['ss' => $ssList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 手术操作日期
     * @param $data
     * @return array
     */
    public function rule56($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $ssmcList = [];
        $ssList = [];
        foreach ($data['operation'] as $operation) {
            if (!empty($operation['SSCZMC']) && empty($operation['SSCZRQ'])) {
                $ssmcList[] = $operation['SSCZMC'];
                $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'OPE_DATE'];
            }
        }

        if (!empty($ssList)) {
            $basis[] = ['desc' => '手术【' . implode('、', $ssmcList) . '】手术日期未填写', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 是否有出院31日内再住院计划
     * @param $data
     * @return array
     */
    /* public function rule63($data)
    {
        $basis = [];
        if (empty($data['data']['AEM03C'])) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['AEM03C'], 'zd' => [], 'ss' => []]];
        }
        //        else {
        //            $AEM03C = config('dictionaries.AEM03C');
        //            if (empty($AEM03C[$data['data']['AEM03C']])) {
        //                $basis[] = ['desc'=>'是否有出院31日内再住院计划，填写错误','location'=>['user'=>['AEM03C']]];
        //            }
        //        }
        return $basis;
    } */

    /**
     * 离院方式
     * @param $data
     * @return array
     */
    public function rule64($data)
    {
        $basis = [];
        if ($data['data']['AEM01C'] == 9) {
            $basis[] = ['desc' => '离院方式不能是其他', 'location' => ['user' => ['AEM01C'], 'zd' => [], 'ss' => []]];
        } elseif (mb_strlen($data['data']['AEM01C']) < 1) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['AEM01C'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 住院总费用
     * @param $data
     * @return array
     */
    public function rule65($data)
    {
        $ADA01 = $data['data']['ADA01'] ?? '';
        $ADA0101 = $data['data']['ADA0101'] ?? '';
        $basis = [];
        if (!is_numeric($ADA01)) {
            $basis[] = ['desc' => '住院总费用填写错误', 'location' => ['user' => ['ADA01'], 'zd' => [], 'ss' => []]];
        } elseif ($ADA01 < $ADA0101) {
            $basis[] = ['desc' => '自付金额不能大于住院总费用', 'location' => ['user' => ['ADA01', 'ADA0101'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 住院总费用其中自付金额
     * @param $data
     * @return array
     */
    public function rule66($data)
    {
        $ADA01 = $data['data']['ADA01'] ?? '';
        $ADA0101 = $data['data']['ADA0101'] ?? '';
        $basis = [];
        if (!is_numeric($ADA0101)) {
            $basis[] = ['desc' => '自付金额未填写', 'location' => ['user' => ['ADA0101'], 'zd' => [], 'ss' => []]];
        } elseif ($ADA01 < $ADA0101) {
            $basis[] = ['desc' => '自付金额不能大于住院总费用', 'location' => ['user' => ['ADA01', 'ADA0101'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 病理诊断编码
     * @param $data
     * @return array
     */
    public function rule67($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $zyzd = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $zyzd = $diagnosis['ZDBM'];
                break;
            }
        }

        if ($zyzd) {
            $arr = ['C', 'D00', 'D01', 'D02', 'D03', 'D04', 'D05', 'D06', 'D07', 'D08', 'D09'];
            for ($i = 10; $i <= 48; $i++) {
                $arr[] = 'D' . $i;
            }

            $res = false;
            foreach ($arr as $value) {
                if (stripos($zyzd, $value) === 0) {
                    $res = true;
                    break;
                }
            }
            if ($res && empty($data['data']['ABF01C'])) {
                $basis[] = ['desc' => '主要诊断编码【' . $zyzd . '】，需填写病理诊断编码', 'location' => ['user' => ['ABF01C'], 'zd' => [], 'ss' => []]];
            }
        }

        return $basis;
    }

    /**
     * 病理诊断名称
     * @param $data
     * @return array
     */
    public function rule68($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $zyzd = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $zyzd = $diagnosis['ZDBM'];
                break;
            }
        }

        if ($zyzd) {
            $arr = ['C', 'D00', 'D01', 'D02', 'D03', 'D04', 'D05', 'D06', 'D07', 'D08', 'D09'];
            for ($i = 10; $i <= 48; $i++) {
                $arr[] = 'D' . $i;
            }

            $res = false;
            foreach ($arr as $value) {
                if (stripos($zyzd, $value) === 0) {
                    $res = true;
                    break;
                }
            }
            if ($res && empty($data['data']['ABF01N'])) {
                $basis[] = ['desc' => '主要诊断编码【' . $zyzd . '】，需填写病理诊断名称', 'location' => ['user' => ['ABF01N'], 'zd' => [], 'ss' => []]];
            }
        }

        return $basis;
    }

    /**
     * 病理号
     * @param $data
     * @return array
     */
    public function rule69($data)
    {
        $basis = [];

        if (($data['data']['ABF01N'] == '-' || $data['data']['ABF01N'] == '无' || $data['data']['ABF01N'] == '未出' || $data['data']['ABF01N'] == '未归') && empty($data['data']['ABF04'])) {
            return $basis;
        }

        if (!empty($data['data']['ABF01N']) && empty($data['data']['ABF04'])) {
            $basis[] = ['desc' => '（病理）诊断名称【' . $data['data']['ABF01N'] . '】，需填写病理号', 'location' => ['user' => ['ABF04'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 损伤和中毒外部原因编码（主要诊断编码首字母为S或T时必填）
     * @param $data
     * @return array
     */
    public function rule70($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $zyzd = '';
        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $zyzd = $diagnosis['ZDBM'];
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
                break;
            }
        }

        if (empty($zyzd)) {
            return $basis;
        }

        if (stripos($zyzd, 'S') === 0 || stripos($zyzd, 'T') === 0) {
            if (empty($data['data']['ABG01C'])) {
                $basis[] = ['desc' => '主要诊断编码【' . $zyzd . '】，应填写疾病编码', 'location' => ['user' => ['ABG01C'], 'zd' => $zdList, 'ss' => []]];
            }
        }

        return $basis;
    }

    /**
     * 损伤和中毒外部原因（主要诊断编码首字母为S或T时必填）
     * @param $data
     * @return array
     */
    public function rule71($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $zyzd = '';
        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $zyzd = $diagnosis['ZDBM'];
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
                break;
            }
        }

        if (stripos($zyzd, 'S') === 0 || stripos($zyzd, 'T') === 0) {
            if (empty($data['data']['ABG01N'])) {
                $basis[] = ['desc' => '主要诊断编码【' . $zyzd . '】，应填写损伤和中毒外部原因', 'location' => ['user' => ['ABG01N'], 'zd' => $zdList, 'ss' => []]];
            }
        }

        return $basis;
    }

    /**
     * 过敏药物名称
     * @param $data
     * @return array
     */
    public function rule72($data)
    {
        $AEB02C = $data['data']['AEB02C'] ?? '';
        $AEB01 = $data['data']['AEB01'] ?? '';
        $basis = [];

        if ($AEB02C == 1 && $AEB01 == '-') {
            return $basis;
        }

        if ($AEB02C == 1 && !empty($AEB01)) {
            $basis[] = ['desc' => '药物过敏【无】，过敏药物名称应为空', 'location' => ['user' => ['AEB01'], 'zd' => [], 'ss' => []]];
        } elseif ($AEB02C == 2 && empty($AEB01)) {
            $basis[] = ['desc' => '药物过敏【有】，应填写过敏药物名称', 'location' => ['user' => ['AEB01'], 'zd' => [], 'ss' => []]];
        } elseif ($AEB02C == 2 && ($AEB01 == '无' || is_numeric($AEB01))) {
            //  $AEB01=='-' ||
            $basis[] = ['desc' => '药物过敏【有】，过敏药物名称填写不规范', 'location' => ['user' => ['AEB01'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 手术操作级别
     * @param $data
     * @return array
     */
    public function rule73($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $ssmcList = [];
        $ssList = [];
        foreach ($data['operation'] as $operation) {
            if (in_array($operation['SSPB'], ['手术', '介入治疗']) && mb_strlen($operation['OPE_LEVEL']) < 1) {
                $ssmcList[] = $operation['SSCZMC'];
                $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'OPE_LEVEL'];
            }
        }

        if (!empty($ssList)) {
            $basis[] = ['desc' => '手术【' . implode('、', $ssmcList) . '】操作级别未填写', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 切口等级
     * @param $data
     * @return array
     */
    /* public function rule77($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $ssmcList = [];
        $ssList = [];
        foreach ($data['operation'] as $operation) {
            if (!empty($operation['SSJB']) && mb_strlen(trim($operation['QKDJ'])) < 1) {
                $ssmcList[] = $operation['SSJB'];
                $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'QKDJ'];
            }
        }

        if (!empty($ssList)) {
            $basis[] = ['desc' => '手术级别【' . implode('、', $ssmcList) . '】切口必填', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }

        return $basis;
    } */

    /**
     * 新生儿入院体重(克)
     * @param $data
     * @return array
     */
    public function rule82($data)
    {
        $basis = [];
        if (empty($data['data']['AAA40'])) {
            return $basis;
        }

        //$res = preg_match("/^[1-9][0-9]{2,3}$/", $data['data']['AAA42']);
        //只保留数字
        //$XSERYTZ = preg_replace("/[^0-9]/", "", $data['data']['AAA42']);
        $XSERYTZ = '';
        if (isset($data['data']['AAA42'])) {
            preg_match('/\d+/', $data['data']['AAA42'], $matches);
            if (!empty($matches)) {
                $XSERYTZ = $matches[0];
            }
        }

        if ((empty($XSERYTZ) || $XSERYTZ < 100 || $XSERYTZ > 9999) && $data['data']['AAA40'] <= 28) {
            $basis[] = ['desc' => '出院科室【' . $data['data']['AAC11N'] . '】，新生儿入院体重（克）必填', 'location' => ['user' => ['AAA42', 'AAC11N'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 出院31天再住院目的
     * @param $data
     * @return array
     */
    public function rule83($data)
    {
        $basis = [];
        if ($data['data']['AEM03C'] == 1) {
            if (!empty($data['data']['AEM04'])) {
                $basis[] = ['desc' => '31天在入院计划【无】，在计划目的应为空', 'location' => ['user' => ['AEM04'], 'zd' => [], 'ss' => []]];
            }
        } elseif ($data['data']['AEM03C'] == 2) {
            if (empty($data['data']['AEM04'])) {
                $basis[] = ['desc' => '31天在入院计划【有】，入院目的不能为空', 'location' => ['user' => ['AEM03C'], 'zd' => [], 'ss' => []]];
            }

            if ($data['data']['AEM04'] == '-' || $data['data']['AEM04'] == '无') {
                $basis[] = ['desc' => '31天在入院计划【有】，入院目的不能为无或-', 'location' => ['user' => ['AEM03C'], 'zd' => [], 'ss' => []]];
            }
        }
        return $basis;
    }

    /**
     * 病理诊断编码只能以M开头
     * @param $data
     * @return array
     */
    public function rule86($data)
    {
        $basis = [];
        if (empty($data['ABF01C'])) {
            return $basis;
        }

        $zyzd = $data['ABF01C'];


        if ($zyzd) {

            if (stripos($zyzd, 'M') !== 0) {
                $basis[] = ['desc' => '主要诊断编码【' . $zyzd . '】，病理诊断编码只能以M开头', 'location' => ['user' => ['ABF01C'], 'zd' => [['ZZPB' => 1, 'field' => 'ICD10_ID1']], 'ss' => []]];
            }
        }

        return $basis;
    }

    /**
     * 出院诊断编码（12岁及以下儿童出院诊断不应编为C50-C63(乳房、女性及男性生殖器恶性肿瘤)）
     * @param $data
     * @return array
     */
    public function rule87($data)
    {
        $AAA04 = !empty($data['data']['AAA04']) ? $data['data']['AAA04'] : 0;
        $basis = [];
        if ($AAA04 > 12 || empty($data['diagnosis'])) {
            return $basis;
        }

        $arr = ['C50', 'C51', 'C52', 'C53', 'C54', 'C55', 'C56', 'C57', 'C58', 'C59', 'C60', 'C61', 'C62', 'C63'];
        $zdList = [];
        $zdbmList = [];
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $zdbmList[] = $diagnosis['ZDBM'];
                    $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
                }
            }
        }
        if (!empty($zdList)) {
            $basis[] = ['desc' => '12岁及以下儿童不能应用编码【' . implode('、', $zdbmList) . '】', 'location' => ['user' => ['AAA04'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 出院诊断编码（12岁及以下儿童出院诊断不应编为O00-O99(妊娠、分娩和产褥期疾病)）
     * @param $data
     * @return array
     */
    public function rule88($data)
    {
        $AAA04 = !empty($data['data']['AAA04']) ? $data['data']['AAA04'] : 0;
        $basis = [];
        if ($AAA04 > 12 || empty($data['diagnosis'])) {
            return $basis;
        }

        $arr = ['O00', 'O01', 'O02', 'O03', 'O04', 'O05', 'O06', 'O07', 'O08', 'O09'];
        for ($i = 10; $i <= 99; $i++) {
            $arr[] = 'O' . $i;
        }

        $zdList = [];
        $zdbmList = [];
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $zdbmList[] = $diagnosis['ZDBM'];
                    $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
                }
            }
        }

        if (!empty($zdList)) {
            $basis[] = ['desc' => '12岁及以下儿童不能应用编码【' . implode('、', $zdbmList) . '】', 'location' => ['user' => ['AAA04'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 出院诊断编码（12岁及以下儿童出院诊断不应编为"D24-D29"女性及男性生殖器官良性肿瘤）
     * @param $data
     * @return array
     */
    public function rule89($data)
    {
        $AAA04 = !empty($data['data']['AAA04']) ? $data['data']['AAA04'] : 0;
        $basis = [];
        if ($AAA04 > 12 || empty($data['diagnosis'])) {
            return $basis;
        }

        $zdList = [];
        $zdbmList = [];
        $arr = ['D24', 'D25', 'D26', 'D27', 'D28', 'D29'];
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $zdbmList[] = $diagnosis['ZDBM'];
                    $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
                }
            }
        }

        if (!empty($zdList)) {
            $basis[] = ['desc' => '12岁及以下儿童不能应用编码【' . implode('、', $zdbmList) . '】', 'location' => ['user' => ['AAA04'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 出院诊断编码（5岁以上儿童出院诊断不应编为P00-P96（起源于围生期某些情况））
     * @param $data
     * @return array
     */
    /* public function rule91($data)
    {
        $AAA04 = !empty($data['data']['AAA04']) ? $data['data']['AAA04'] : 0;
        $basis = [];
        if ($AAA04 <= 5 || empty($data['diagnosis'])) {
            return $basis;
        }

        $zdList = [];
        $zdbmList = [];
        $str = 'P00,P01,P02,P03,P04,P05,P06,P07,P08,P09,P10,P11,P12,P13,P14,,P15,P16,P17,P18,P19,P20,P21,P22,P23,P24,P25,P26,P27,P28,P29,P30,P31,P32,P33,P34,P35,P36,P37,P38,P39,P40,P41,P42,P43,P44,P45,P46,P47,P48,P49,P50,P51,P52,P53,P54,P55,P56,P57,P58,P59,P60,P61,P62,P63,P64,P65,P66,P67,P68,P69,P70,P71,P72,P73,P74,P75,P76,P77,P78,P79,P80,P81,P82,P83,P84,P85,P86,P87,P88,P89,P90,P91,P92,P93,P94,P95,P96';
        $arr = explode(',', $str);
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $zdbmList[] = $diagnosis['ZDBM'];
                    $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
                }
            }
        }

        if (!empty($zdList)) {
            $basis[] = ['desc' => '5岁以上儿童不能应用编码【' . implode('、', $zdbmList) . '】', 'location' => ['user' => ['AAA04'], 'zd' => $zdbmList, 'ss' => []]];
        }

        return $basis;
    } */

    /**
     * 出院诊断编码（女性出院诊断不应编"D29.1"(前列腺良性肿瘤)。）
     * @param $data
     * @return array
     */
    /* public function rule92($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        $basis = [];
        if ($AAA02C != 2 || empty($data['diagnosis'])) {
            return $basis;
        }

        $zdList = [];
        $zdbmList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'D29.1') === 0) {
                $zdbmList[] = $diagnosis['ZDBM'];
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }

        if (!empty($zdList)) {
            $basis[] = ['desc' => '女性出院诊断不应编【' . implode('、', $zdbmList) . '】', 'location' => ['user' => ['AAA02C'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    } */

    /**
     * 女性出院诊断不应编"C60-C63"(男性生殖器官恶性肿瘤)。
     * @param $data
     * @return array
     */
    /* public function rule93($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        $basis = [];
        if ($AAA02C != 2 || empty($data['diagnosis'])) {
            return $basis;
        }

        $zdList = [];
        $zdbmList = [];
        $str = 'C60,C61,C62,C63';
        $arr = explode(',', $str);
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $zdbmList[] = $diagnosis['ZDBM'];
                        $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
                }
            }
        }

        if (!empty($zdList)) {
            $basis[] = ['desc' => '女性出院诊断不应编【' . implode('、', $zdbmList) . '】', 'location' => ['user' => ['AAA02C'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    } */

    /**
     * 女性出院诊断不应编"N40-N51"(男性生殖器官疾病)。
     * @param $data
     * @return array
     */
    /* public function rule94($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        $basis = [];
        if ($AAA02C != 2 || empty($data['diagnosis'])) {
            return $basis;
        }

        $zdList = [];
        $zdbmList = [];
        $str = 'N40,N41,N42,N43,N44,N45,N46,N47,N48,N49,N50,N51';
        $arr = explode(',', $str);
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $zdbmList[] = $diagnosis['ZDBM'];
                    $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
                }
            }
        }

        if (!empty($zdList)) {
            $basis[] = ['desc' => '女性出院诊断不应编【' . implode('、', $zdbmList) . '】', 'location' => ['user' => ['AAA02C'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    } */

    /**
     * 女性出院诊断不应编"K40"(腹股沟疝)。
     * @param $data
     * @return array
     */
    /* public function rule95($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        $basis = [];
        if ($AAA02C != 2 || empty($data['diagnosis'])) {
            return $basis;
        }

        $zdList = [];
        $zdbmList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'K40') === 0) {
                $zdbmList[] = $diagnosis['ZDBM'];
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }

        if (!empty($zdList)) {
            $basis[] = ['desc' => '女性出院诊断不应编【' . implode('、', $zdbmList) . '】', 'location' => ['user' => ['AAA02C'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    } */

    /**
     * 男性出院诊断不应编"C51-C58"(女性生殖器官恶性肿瘤)。
     * @param $data
     * @return array
     */
    /* public function rule96($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        $basis = [];
        if ($AAA02C != 1 || empty($data['diagnosis'])) {
            return $basis;
        }

        $zdList = [];
        $zdbmList = [];
        $str = 'C51,C52,C53,C54,C55,C56,C57,C58';
        $arr = explode(',', $str);
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $zdbmList[] = $diagnosis['ZDBM'];
                    $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
                }
            }
        }

        if (!empty($zdList)) {
            $basis[] = ['desc' => '男性出院诊断不应编【' . implode('、', $zdbmList) . '】', 'location' => ['user' => ['AAA02C'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    } */

    /**
     * 男性出院诊断不应编"D06"(子宫颈原位恶性肿瘤)。
     * @param $data
     * @return array
     */
    /* public function rule97($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        $basis = [];
        if ($AAA02C != 1 || empty($data['diagnosis'])) {
            return $basis;
        }

        $zdList = [];
        $zdbmList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'D06') === 0) {
                $zdbmList[] = $diagnosis['ZDBM'];
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }

        if (!empty($zdList)) {
            $basis[] = ['desc' => '男性出院诊断不应编【' . implode('、', $zdbmList) . '】', 'location' => ['user' => ['AAA02C'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    } */

    /**
     * 男性出院诊断不应编"D24"(乳房良性肿瘤)。
     * @param $data
     * @return array
     */
    /* public function rule98($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        $basis = [];
        if ($AAA02C != 1 || empty($data['diagnosis'])) {
            return $basis;
        }

        $zdList = [];
        $zdbmList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'D24') === 0) {
                $zdbmList[] = $diagnosis['ZDBM'];
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }

        if (!empty($zdList)) {
            $basis[] = ['desc' => '男性出院诊断不应编【' . implode('、', $zdbmList) . '】', 'location' => ['user' => ['AAA02C'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    } */

    /**
     * 男性出院诊断不应编"D25"(子宫平滑肌瘤)。
     * @param $data
     * @return array
     */
    /* public function rule99($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        $basis = [];
        if ($AAA02C != 1 || empty($data['diagnosis'])) {
            return $basis;
        }

        $zdList = [];
        $zdbmList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'D25') === 0) {
                $zdbmList[] = $diagnosis['ZDBM'];
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }

        if (!empty($zdList)) {
            $basis[] = ['desc' => '男性出院诊断不应编【' . implode('、', $zdbmList) . '】', 'location' => ['user' => ['AAA02C'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    } */

    /**
     * 男性出院诊断不应编"D27"(卵巢良性肿瘤)
     * @param $data
     * @return array
     */
    /* public function rule100($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        $basis = [];
        if ($AAA02C != 1 || empty($data['diagnosis'])) {
            return $basis;
        }

        $zdList = [];
        $zdbmList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'D27') === 0) {
                $zdbmList[] = $diagnosis['ZDBM'];
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }

        if (!empty($zdList)) {
            $basis[] = ['desc' => '男性出院诊断不应编【' . implode('、', $zdbmList) . '】', 'location' => ['user' => ['AAA02C'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    } */

    /**
     * 男性出院诊断不应编"N70-N77"(女性盆腔气管炎性疾病)。
     * @param $data
     * @return array
     */
    public function rule101($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        $basis = [];
        if ($AAA02C != 1 || empty($data['diagnosis'])) {
            return $basis;
        }

        $zdList = [];
        $zdbmList = [];
        $str = 'N70,N71,N72,N73,N74,N75,N76,N77';
        $arr = explode(',', $str);
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $zdbmList[] = $diagnosis['ZDBM'];
                    $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
                }
            }
        }

        if (!empty($zdList)) {
            $basis[] = ['desc' => '男性出院诊断不应编【' . implode('、', $zdbmList) . '】', 'location' => ['user' => ['AAA02C'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 男性出院诊断不应编"N80-N98"(女性生殖道非炎性疾病)。
     * @param $data
     * @return array
     */
    public function rule102($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        $basis = [];
        if ($AAA02C != 1 || empty($data['diagnosis'])) {
            return $basis;
        }

        $zdList = [];
        $zdbmList = [];
        $str = 'N80,N81,N82,N83,N84,N85,N86,N87,N88,N89,N90,N91,N92,N93,N94,N95,N96,N97,N98';
        $arr = explode(',', $str);
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $zdbmList[] = $diagnosis['ZDBM'];
                    $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
                }
            }
        }

        if (!empty($zdList)) {
            $basis[] = ['desc' => '男性出院诊断不应编【' . implode('、', $zdbmList) . '】', 'location' => ['user' => ['AAA02C'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 男性出院诊断不应编"O00-O99"(妊娠、分娩和产褥期疾病)。
     * @param $data
     * @return array
     */
    public function rule103($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        $basis = [];
        if ($AAA02C != 1 || empty($data['diagnosis'])) {
            return $basis;
        }

        $zdList = [];
        $zdbmList = [];
        $str = 'O00,O01,O02,O03,O4,O05,O06,O07,O8,O09,O10,O11,O12,O13,O14,O15,O16,O17,O18,O19,O20,O21,O22,O23,O24,O25,O26,O27,O28,O29,O30,O31,O32,O33,O34,O35,O36,O37,O38,O39,O40,O41,O42,O43,O44,O45,O46,O47,O48,O49,O50,O51,O52,O53,O54,O55,O56,O57,O58,O59,O60,O61,O62,O63,O64,O65,O66,O67,O68,O69,O70,O71,O72,O73,O74,O75,O76,O77,O78,O79,O80,O81,O82,O83,O84,O85,O86,O87,O88,O89,O90,O91,O92,O93,O94,O95,O96,O97,O98,O99';
        $arr = explode(',', $str);
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $zdbmList[] = $diagnosis['ZDBM'];
                    $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
                }
            }
        }

        if (!empty($zdList)) {
            $basis[] = ['desc' => '男性出院诊断不应编【' . implode('、', $zdbmList) . '】', 'location' => ['user' => ['AAA02C'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 男性主要诊断编码不规范
     * @param $data
     * @return array
     */
    public function rule104($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        $basis = [];
        if ($AAA02C != 1 || empty($data['diagnosis'])) {
            return $basis;
        }

        $str = 'A34,B37.3,C79.6,D07.0,D07.1,D07.2,D07.3,D26,D28,D39,E28,E89.4,F52.5,F53,I86.3,L29.2,M80.0,M80.1,M81.0,M81.1,M83.0,N78,N79,N80,N81,N82,N83,N84,N85,N86,N87,N88,N89,N90,N91,N92,N93,N94,N95,N96,N97,N98,N99.2,N99.3,P54.6,Q50,Q51,Q52,R87,S31.4,S37.4,S37.5,S37.6,T19.2,T19.3,T83.3,Z01.4,Z12.4,Z30.1,Z30.3,Z30.5,Z31.1,Z31.2,Z32,Z33,Z34,Z35,Z36,Z37,Z39,Z287.5,Z97.5,O00,O01,O02,O03,O04,O05,O06,O07,O8,O09,O10,O11,O12,O13,O14,O15,O16,O17,O18,O19,O20,O21,O22,O23,O24,O25,O26,O27,O28,O29,O30,O31,O32,O33,O34,O35,O36,O37,O38,O39,O40,O41,O42,O43,O44,O45,O46,O47,O48,O49,O50,O51,O52,O53,O54,O55,O56,O57,O58,O59,O60,O61,O62,O63,O64,O65,O66,O67,O68,O69,O70,O71,O72,O73,O74,O75,O76,O77,O78,O79,O80,O81,O82,O83,O84,O85,O86,O87,O88,O89,O90,O91,O92,O93,O94,O95,O96,O97,O98,O99';
        $arr = explode(',', $str);
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if ($diagnosis['ZZPB'] == 1 && stripos($diagnosis['ZDBM'], $value) === 0) {
                    $basis[] = ['desc' => '男性主要诊断编码不应编【' . $diagnosis['ZDBM'] . '】', 'location' => ['user' => ['AAA02C'], 'zd' => [['ZZPB' => 1, 'field' => 'ICD10_ICD1']], 'ss' => []]];
                    break;
                }
            }
        }

        return $basis;
    }

    /**
     * 女性主要诊断编码不规范
     * @param $data
     * @return array
     */
    public function rule105($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        $basis = [];
        if ($AAA02C != 2 || empty($data['diagnosis'])) {
            return $basis;
        }

        $str = 'B26.0,C60,C61,C62,C63,D07.4,D07.5,D07.6,D17.6,D29,D40,E29,E89.5,F52.4,I86.1,L29.1,N40,N41,N42,N43,N44,N45,N46,N47,N48,N49,N50,N51,Q53,Q54,Q55,R86,S31.2,S31.3,Z12.5';
        $arr = explode(',', $str);
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if ($diagnosis['ZZPB'] == 1 && stripos($diagnosis['ZDBM'], $value) === 0) {
                    $basis[] = ['desc' => '女性主要诊断编码不应编【' . $diagnosis['ZDBM'] . '】', 'location' => ['user' => ['AAA02C'], 'zd' => [['ZZPB' => 1, 'field' => 'ICD10_NAME']], 'ss' => []]];
                    break;
                }
            }
        }

        return $basis;
    }

    /**
     * 出生地市
     * @param $data
     * @return array
     */
    public function rule1344($data)
    {
        $AAA10 = !empty($data['data']['AAA10']) ? trim($data['data']['AAA10']) : '';
        $basis = [];
        if (mb_strlen($AAA10) < 2) {
            $basis[] = ['desc' => '出生地市不能小于两位', 'location' => ['user' => ['AAA10'], 'zd' => [], 'ss' => []]];
        } elseif (is_numeric($AAA10)) {
            $basis[] = ['desc' => '出生地市不能全是数字', 'location' => ['user' => ['AAA10'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 出生地县
     * @param $data
     * @return array
     */
    public function rule1345($data)
    {
        $AAA11 = !empty($data['data']['AAA11']) ? trim($data['data']['AAA11']) : '';
        $basis = [];
        if (mb_strlen($AAA11) < 2) {
            $basis[] = ['desc' => '出生地县不能小于两位', 'location' => ['user' => ['AAA11'], 'zd' => [], 'ss' => []]];
        } elseif (is_numeric($AAA11)) {
            $basis[] = ['desc' => '出生地县不能全是数字', 'location' => ['user' => ['AAA11'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 籍贯市（籍贯市未填写）
     * @param $data
     * @return array
     */
    public function rule1346($data)
    {
        $AAA44 = !empty($data['data']['AAA44']) ? trim($data['data']['AAA44']) : '';
        $basis = [];
        if (mb_strlen($AAA44) < 2) {
            $basis[] = ['desc' => '籍贯市不能小于两位', 'location' => ['user' => ['AAA44'], 'zd' => [], 'ss' => []]];
        } elseif (is_numeric($AAA44)) {
            $basis[] = ['desc' => '籍贯市不能全是数字', 'location' => ['user' => ['AAA44'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 户籍市（户籍市未填写）
     * @param $data
     * @return array
     */
    public function rule1347($data)
    {
        $AAA46 = !empty($data['data']['AAA46']) ? trim($data['data']['AAA46']) : '';
        $basis = [];
        if (mb_strlen($AAA46) < 2) {
            $basis[] = ['desc' => '户籍市不能小于两位', 'location' => ['user' => ['AAA46'], 'zd' => [], 'ss' => []]];
        } elseif (is_numeric($AAA46)) {
            $basis[] = ['desc' => '户籍市不能全是数字', 'location' => ['user' => ['AAA46'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 户籍县（户籍县未填写）
     * @param $data
     * @return array
     */
    public function rule1348($data)
    {
        $AAA47 = !empty($data['data']['AAA47']) ? trim($data['data']['AAA47']) : '';
        $basis = [];
        if (mb_strlen($AAA47) < 2) {
            $basis[] = ['desc' => '户籍县不能小于两位', 'location' => ['user' => ['AAA47'], 'zd' => [], 'ss' => []]];
        } elseif (is_numeric($AAA47)) {
            $basis[] = ['desc' => '户籍县不能全是数字', 'location' => ['user' => ['AAA47'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 户籍详细地址（户籍详细地址未填写）
     * @param $data
     * @return array
     */
    public function rule1349($data)
    {
        $AAA12 = !empty($data['data']['AAA12']) ? trim($data['data']['AAA12']) : '';
        $basis = [];
        if (mb_strlen($AAA12) < 2) {
            $basis[] = ['desc' => '户籍详细地址不能小于两位', 'location' => ['user' => ['AAA12'], 'zd' => [], 'ss' => []]];
        } elseif (is_numeric($AAA12)) {
            $basis[] = ['desc' => '户籍详细地址不能全是数字', 'location' => ['user' => ['AAA12'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 现住址市
     * @param $data
     * @return array
     */
    public function rule1350($data)
    {
        $AAA49 = !empty($data['data']['AAA49']) ? trim($data['data']['AAA49']) : '';
        $basis = [];
        if (mb_strlen($AAA49) < 2) {
            $basis[] = ['desc' => '现住址市不能小于两位', 'location' => ['user' => ['AAA49'], 'zd' => [], 'ss' => []]];
        } elseif (is_numeric($AAA49)) {
            $basis[] = ['desc' => '现住址市不能全是数字', 'location' => ['user' => ['AAA49'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 现住址县
     * @param $data
     * @return array
     */
    public function rule1351($data)
    {
        $AAA50 = !empty($data['data']['AAA50']) ? trim($data['data']['AAA50']) : '';
        $basis = [];
        if (mb_strlen($AAA50) < 2) {
            $basis[] = ['desc' => '现住址县不能小于两位', 'location' => ['user' => ['AAA50'], 'zd' => [], 'ss' => []]];
        } elseif (is_numeric($AAA50)) {
            $basis[] = ['desc' => '现住址县不能全是数字', 'location' => ['user' => ['AAA50'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 现住址详细地址
     * @param $data
     * @return array
     */
    public function rule1352($data)
    {
        $AAA15 = !empty($data['data']['AAA15']) ? trim($data['data']['AAA15']) : '';
        $basis = [];
        if (mb_strlen($AAA15) < 2) {
            $basis[] = ['desc' => '现住址详细地址不能小于两位', 'location' => ['user' => ['AAA15'], 'zd' => [], 'ss' => []]];
        } elseif (is_numeric($AAA15)) {
            $basis[] = ['desc' => '现住址详细地址不能全是数字', 'location' => ['user' => ['AAA15'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 主要诊断编码
     * @param $data
     * @return array
     */
    public function rule1356($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $str = 'B95,B96,B97';
        $arr = explode(',', $str);
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if ($diagnosis['ZZPB'] == 1 && stripos($diagnosis['ZDBM'], $value) === 0) {
                    $basis[] = ['desc' => '主要诊断编码不应编【' . $diagnosis['ZDBM'] . '】', 'location' => ['zd' => [['ZZPB' => 1, 'field' => 'ICD10_ID1']], 'user' => [], 'ss' => []]];
                    break;
                }
            }
        }

        return $basis;
    }

    /**
     * 出院病房不能为空
     * @param $data
     * @return array
     */
    public function rule1359($data)
    {
        $basis = [];
        $CYKB = $data['data']['AAC03'] ?? '';
        if (empty(trim($CYKB))) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['AAC11N'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * I50.9是未特指的心力衰竭，原则上不能作为主要诊断
     * @param $data
     * @return array
     */
    public function rule1362($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $str = 'I50.900';
        /* $arr = explode(',', $str);
        foreach ($arr as $value) { */
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1 && $diagnosis['ZDBM'] == $str) {
                $basis[] = ['desc' => '【' . $diagnosis['ZDBM'] . '】不能作为主要诊断', 'location' => ['zd' => [['ZZPB' => 1, 'DIA_ORDER' => 1, 'field' => 'ICD10_ID1']], 'user' => [], 'ss' => []]];
                break;
            }
        }
        //}

        return $basis;
    }

    /**
     * 主要诊断编码不能与其他诊断编码重复
     * @param $data
     * @return array
     */
    public function rule1363($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $zyzdArr = [];
        $qtzdArr = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $zyzdArr = ['ZZPB' => $diagnosis['ZZPB'], 'ZDBM' => $diagnosis['ZDBM']];
            } else {
                $qtzdArr[] = ['ZZPB' => $diagnosis['ZZPB'], 'ZDBM' => $diagnosis['ZDBM']];
            }
        }

        if (empty($zyzdArr)) {
            return $basis;
        }

        $qtZdBm = '';
        $zdList = [];
        foreach ($qtzdArr as $value) {
            if ($zyzdArr['ZDBM'] == $value['ZDBM']) {
                $qtZdBm = $value['ZDBM'];
                $zdList[] = ['ZZPB' => $value['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }

        if (!empty($zdList)) {
            $basis[] = ['desc' => '主要诊断编码【' . $zyzdArr['ZDBM'] . '】与其他诊断编码【' . $qtZdBm . '】重复', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 其他诊断编码中不能出现重复诊断编码
     * @param $data
     * @return array
     */
    public function rule1364($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $qtzdArr = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] != 1) {
                $qtzdArr[$diagnosis['ZDBM']][] = $diagnosis;
            }
        }

        $zdList = [];
        $zdbmList = [];
        foreach ($qtzdArr as $ZDBM => $value) {
            if (count($value) > 1) {
                $zdbmList[] = $value[0]['ZDBM'];
                foreach ($value as $val) {
                    $zdList[] = ['ZZPB' => $val['ZZPB'], 'field' => 'ICD10_ID1'];
                }
            }
        }

        if (!empty($zdList)) {
            $basis[] = ['desc' => '其他诊断编码【' . implode('、', $zdbmList) . '】重复', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 诊断编码不能同时存在：B05.0-B05.8为麻疹伴并发症，B05.9为麻疹不伴并发症
     * @param $data
     * @return array
     */
    public function rule1365($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $str = 'B05.0,B05.1,B05.2,B05.3,B05.4,B05.5,B05.6,B05.7,B05.8';
        $arr = explode(',', $str);
        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'B05.9') === 0) {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            } else {
                foreach ($arr as $value) {
                    if (stripos($diagnosis['ZDBM'], $value) === 0) {

                        $res1[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
                    }
                }
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '（来源：P120）诊断编码【B05.0-B05.8 麻疹伴并发症】和【B05.9 麻疹不伴并发症】不能同时存在', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 诊断编码不能同时存在：H33.0视网膜脱离伴视网膜断裂，H33.3视网膜断裂不伴有脱离
     * @param $data
     * @return array
     */
    public function rule1366($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'H33.0') === 0) {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            } elseif (stripos($diagnosis['ZDBM'], 'H33.3') === 0) {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '（来源：P356）【H33.0 视网膜脱离伴视网膜断裂】和【H33.3 视网膜断裂不伴有脱离】不能同时存在', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 诊断编码不能同时存在：K35.0急性阑尾炎伴有弥漫性腹膜炎和K35.9未特指的急性阑尾炎不伴有弥漫性腹膜炎
     * @param $data
     * @return array
     */
    public function rule1368($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'K35.0') === 0) {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            } elseif (stripos($diagnosis['ZDBM'], 'K35.9') === 0) {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '（来源：P453）【K35.0 急性阑尾炎伴有弥漫性腹膜炎】和【K35.9 未特指的急性阑尾炎（急性阑尾炎不伴有弥漫性腹膜炎）】不能同时存在', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * K72肝衰竭和K70.4酒精性肝衰竭，相对编码不能同时存在
     * @param $data
     * @return array
     */
    public function rule1369($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'K72') === 0) {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            } elseif (stripos($diagnosis['ZDBM'], 'K70.4') === 0) {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '（来源：P466）【K72 肝衰竭】和【K70.4 酒精性肝衰竭】不能同时存在', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * K72肝衰竭和B15-B19病毒性肝炎，相对编码不能同时存在
     * @param $data
     * @return array
     */
    public function rule1370($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $arr = ['B15', 'B16', 'B17', 'B18', 'B19'];
        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'K72') === 0) {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            } else {
                foreach ($arr as $value) {
                    if (stripos($diagnosis['ZDBM'], $value) === 0) {
                        $res2[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
                    }
                }
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '（来源：P466，P122）【K72 肝衰竭】和【B15-B19 病毒性肝炎】不能同时存在', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 同时有K80.2胆囊结石+K81.1-K81.9胆囊炎，应合并编码为K80.1胆囊结石伴胆囊炎
     * @param $data
     * @return array
     */
    public function rule1371($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $str = 'K81.1,K81.2,K81.3,K81.4,K81.5,K81.6,K81.7,K81.8,K81.9';
        $arr = explode(',', $str);
        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'K80.2') === 0) {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            } else {
                foreach ($arr as $value) {
                    if (stripos($diagnosis['ZDBM'], $value) === 0) {
                        $res2[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
                    }
                }
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '（来源：P469）【K80.2 胆囊结石不伴有胆囊炎】+【K81.1-K81.9 胆囊炎】应合并为【K80.1 胆囊结石伴有其他胆囊炎】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 同时有K80.2胆囊结石+K81.0急性胆囊炎，应合并编码为K80.0胆囊结石伴急性胆囊炎
     * @param $data
     * @return array
     */
    public function rule1372($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'K80.2') === 0) {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            } elseif (stripos($diagnosis['ZDBM'], 'K81.0') === 0) {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '（来源：P469）【K80.2 胆囊结石不伴有胆囊炎】+【K81.0 急性胆囊炎】应合并为【K80.0 胆囊结石伴有急性胆囊炎】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 同时有K80.5胆管结石+K81胆囊炎，应合并编码为K80.4胆管结石伴胆囊炎
     * @param $data
     * @return array
     */
    public function rule1373($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'K80.5') === 0) {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            } elseif (stripos($diagnosis['ZDBM'], 'K81') === 0) {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '（来源：P469）【K80.5 胆管结石不伴有胆管炎或胆囊炎】+【K81 胆囊炎】应合并为【K80.4 胆管结石伴有胆囊炎】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 同时有K80.5胆管结石+K83.0胆管炎，应合并编码为K80.3胆管结石伴胆管炎
     * @param $data
     * @return array
     */
    public function rule1374($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'K80.5') === 0) {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            } elseif (stripos($diagnosis['ZDBM'], 'K83.0') === 0) {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '（来源：P469）【K80.5 胆管结石不伴有胆管炎或胆囊炎】+【K83.0 胆管炎】应合并为【K80.3 胆管结石伴有胆管炎】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 同时有I11高血压心脏病+I12高血压肾脏病，应合并编码为I13高血压心脏和肾脏病
     * @param $data
     * @return array
     */
    public function rule1375($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'I11') === 0) {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            } elseif (stripos($diagnosis['ZDBM'], 'I12') === 0) {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '（来源：P381）【I11 高血压心脏病】+【I12 高血压肾脏病】应合并为【I13 高血压心脏和肾脏病】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 同时有J44.900慢性阻塞性肺疾病+J18.9肺炎，应合并编码为J44.000慢性阻塞性肺病伴有急性下呼吸道感染
     * @param $data
     * @return array
     */
    public function rule1376($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'J44.900') === 0) {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            } elseif (stripos($diagnosis['ZDBM'], 'J18.9') === 0) {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '【J44.900 慢性阻塞性肺疾病】+【J18.9 肺炎】应合并为【J44.000 慢性阻塞性肺病伴有急性下呼吸道感染】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 同时有J44.100慢性阻塞性肺病伴有急性加重+J42.x00慢性支气管炎+J43.904阻塞性肺气肿，应合并编码为J44.100x001慢性阻塞性肺气肿性支气管炎伴急性加重
     * @param $data
     * @return array
     */
    public function rule1377($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        $res3 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'J44.100') === 0) {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            } elseif (stripos($diagnosis['ZDBM'], 'J42.x00') === 0) {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            } elseif (stripos($diagnosis['ZDBM'], 'J43.904') === 0) {
                $res3[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2 && $res3) {
            $zdList = array_merge(array_merge($res1, $res2), $res3);
            $basis[] = ['desc' => '【J44.100 慢性阻塞性肺病伴有急性加重】+【J42.x00 慢性支气管炎】+【J43.904 阻塞性肺气肿】应合并为【J44.100x001 慢性阻塞性肺气肿性支气管炎伴急性加重】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 同时有2型糖尿病性肾病+慢性肾脏病2期，应合并名称为2型糖尿病肾病II期
     * @param $data
     * @return array
     */
    public function rule1378($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = false;
        $res2 = false;
        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDMC'] == '2型糖尿病性肾病') {
                $res1 = true;
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            } elseif ($diagnosis['ZDMC'] == '慢性肾脏病2期') {
                $res2 = true;
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $basis[] = ['desc' => '【2型糖尿病性肾病】+【慢性肾脏病2期】应合并为【2型糖尿病肾病II期】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 同时有2型糖尿病肾病+慢性肾衰竭尿毒症期，应合并名称为2型糖尿病肾病V期
     * @param $data
     * @return array
     */
    public function rule1379($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = false;
        $res2 = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDMC'] == '2型糖尿病肾病') {
                $res1 = true;
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            } elseif ($diagnosis['ZDMC'] == '慢性肾衰竭尿毒症期') {
                $res2 = true;
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $basis[] = ['desc' => '【2型糖尿病肾病】+【慢性肾衰竭尿毒症期】应合并为【2型糖尿病肾病V期】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 同时有急性喉炎+喉梗阻，应合并名称为急性梗阻性喉炎[哮吼]
     * @param $data
     * @return array
     */
    public function rule1380($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = false;
        $res2 = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDMC'] == '喉梗阻') {
                $res1 = true;
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            } elseif ($diagnosis['ZDMC'] == '急性喉炎') {
                $res2 = true;
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $basis[] = ['desc' => '【急性喉炎】+【喉梗阻】应合并为【急性梗阻性喉炎[哮吼]】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 同时有急性喉炎+急性咽炎，应合并名称为急性咽喉炎
     * @param $data
     * @return array
     */
    public function rule1381($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = false;
        $res2 = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDMC'] == '急性喉炎') {
                $res1 = true;
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            } elseif ($diagnosis['ZDMC'] == '急性咽炎') {
                $res2 = true;
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $basis[] = ['desc' => '【急性喉炎】+【急性咽炎】应合并为【急性咽喉炎】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 同时有扁桃体肥大+腺样体肥大，应合并名称为扁桃体肥大伴有腺样体肥大
     * @param $data
     * @return array
     */
    public function rule1382($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = false;
        $res2 = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDMC'] == '扁桃体肥大') {
                $res1 = true;
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            } elseif ($diagnosis['ZDMC'] == '腺样体肥大') {
                $res2 = true;
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $basis[] = ['desc' => '【扁桃体肥大】+【腺样体肥大】应合并为【扁桃体肥大伴有腺样体肥大】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 同时有慢性支气管炎+肺气肿，应合并名称为慢性支气管炎伴肺气肿
     * @param $data
     * @return array
     */
    public function rule1383($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = false;
        $res2 = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDMC'] == '慢性支气管炎') {
                $res1 = true;
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            } elseif ($diagnosis['ZDMC'] == '肺气肿') {
                $res2 = true;
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $basis[] = ['desc' => '【慢性支气管炎】+【肺气肿】应合并为【慢性支气管炎伴肺气肿】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 同时有肺气肿+肺大疱，应合并名称为大疱性肺气肿
     * @param $data
     * @return array
     */
    public function rule1384($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = false;
        $res2 = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDMC'] == '肺气肿') {
                $res1 = true;
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            } elseif ($diagnosis['ZDMC'] == '肺大疱') {
                $res2 = true;
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $basis[] = ['desc' => '【肺气肿】+【肺大疱】应合并为【大疱性肺气肿】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 同时有变应性鼻炎+咳嗽变异性哮喘+支气管哮喘，应合并名称为过敏性鼻炎伴哮喘
     * @param $data
     * @return array
     */
    public function rule1385($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = false;
        $res2 = false;
        $res3 = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDMC'] == '变应性鼻炎') {
                $res1 = true;
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            } elseif ($diagnosis['ZDMC'] == '咳嗽变异性哮喘') {
                $res2 = true;
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            } elseif ($diagnosis['ZDMC'] == '支气管哮喘') {
                $res3 = true;
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2 && $res3) {
            $basis[] = ['desc' => '【变应性鼻炎】+【咳嗽变异性哮喘】+【支气管哮喘】应合并为【过敏性鼻炎伴哮喘】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 疾病诊断编码为 P07.000x001，新生儿体重范围应为750-999g
     * @param $data
     * @return array
     */
    public function rule1388($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDBM'] == 'P07.000x001') {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }
        if ($zdList && ($data['data']['AEN01'] < 750 || $data['data']['AEN01'] > 999)) {
            $basis[] = ['desc' => '有诊断编码【P07.000x001】新生儿体重范围应为【750-999g】', 'location' => ['user' => ['AEN01'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 疾病诊断编码为P07.100x003极低出生体重儿(1250-1499g)，新生儿体重范围应为1250-1499g
     * @param $data
     * @return array
     */
    public function rule1389($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDBM'] == 'P07.100x003') {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }
        if ($zdList && ($data['data']['AEN01'] < 1250 || $data['data']['AEN01'] > 1499)) {
            $basis[] = ['desc' => '有诊断编码【P07.100x003】新生儿体重范围应为【1250-1499g】', 'location' => ['user' => ['AEN01'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 疾病诊断编码为P07.100x002极低出生体重儿(1000-1249g)，新生儿体重范围应为1000-1249g
     * @param $data
     * @return array
     */
    public function rule1390($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDBM'] == 'P07.100x002') {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'], 'field' => 'ICD10_ID1'];
            }
        }
        if ($zdList && ($data['data']['AEN01'] < 1000 || $data['data']['AEN01'] > 1249)) {
            $basis[] = ['desc' => '有诊断编码【P07.100x002】新生儿体重范围应为【1000-1249g】', 'location' => ['user' => ['AEN01'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 疾病诊断编码为P07.100x004低出生体重儿(1500-2499g)，新生儿体重范围应为1500-2499g
     * @param $data
     * @return array
     */
    public function rule1391($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDBM'] == 'P07.100x004') {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }
        if ($zdList && ($data['data']['AEN01'] < 1500 || $data['data']['AEN01'] > 2499)) {
            $basis[] = ['desc' => '有诊断编码【P07.100x004】新生儿体重范围应为【1500-2499】', 'location' => ['user' => ['AEN01'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 疾病编码P08.000特大婴儿，新生儿体重应在4500g以上
     * @param $data
     * @return array
     */
    public function rule1392($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDBM'] == 'P08.000') {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }
        if ($zdList && $data['data']['AEN01'] < 4500) {
            $basis[] = ['desc' => '有诊断编码【P08.000】新生儿体重范围应为【4500g以上】', 'location' => ['user' => ['AEN01'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 疾病编码P08.000特大婴儿，新生儿体重在4500g以下的，应更换疾病诊断为P08.100x001大于胎龄儿
     * @param $data
     * @return array
     */
    public function rule1393($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDBM'] == 'P08.000') {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }
        if ($zdList && $data['data']['AEN01'] < 4500) {
            $basis[] = ['desc' => '有诊断编码【P08.000】，新生儿体重【4500g以下】，应更换诊断编码为【P08.100x001】', 'location' => ['user' => ['AEN01'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 疾病编码范围在【M08】幼年型关节炎—【M09】分类于他处的疾病引起的幼年型关节炎，年龄范围应为0-17岁（除外新生儿）
     * @param $data
     * @return array
     */
    public function rule1395($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $str = 'M08,M09';
        $arr = explode(',', $str);
        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            foreach ($arr as $value) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
                }
            }
        }

        if ($zdList && $data['data']['AAA04'] > 17) {
            $basis[] = ['desc' => '疾病编码范围在【M08】幼年型关节炎—【M09】分类于他处的疾病引起的幼年型关节炎，年龄范围应为0-17岁', 'location' => ['user' => ['AAA04'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 码段为I05-I09的两个以上诊断编码，需合并到I08。
     * @param $data
     * @return array
     */
    public function rule1396($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'I05') === 0) {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            } elseif (stripos($diagnosis['ZDBM'], 'I06') === 0) {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            } elseif (stripos($diagnosis['ZDBM'], 'I07') === 0) {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            } elseif (stripos($diagnosis['ZDBM'], 'I09') === 0) {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }

        if (count($zdList) >= 2) {
            $basis[] = ['desc' => '诊断编码【I05】-【I09】的两个以上诊断编码，需合并到【I08】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 诊断中出现疾病诊断+肿瘤形态学诊断，更换诊断
     * @param $data
     * @return array
     */
    public function rule1397($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $str = 'C22.101M81600/3,C81.001M96590/3,C83.812M96730/3,C84.000M97000/3,C85.707M97190/3,C85.710M97160/3,C88.100M97620/3,C88.200M97630/3,C90.101M98310/1,C91.306M98341/3,C96.701M97271/3';
        $arr = explode(',', $str);
        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (in_array($diagnosis['ZDBM'], $arr)) {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }

        if (count(array_unique($zdList)) > 1) {
            $basis[] = ['desc' => '诊断中出现疾病诊断+肿瘤形态学诊断，更换诊断', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 手术操作不能同时存在：07.62垂体腺部分切除术，经蝶骨入路；07.14垂体腺活组织检查，经蝶骨入路
     * @param $data
     * @return array
     */
    public function rule1398($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['SSCZBM'], '07.62') === 0) {
                $res1[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            } elseif (stripos($operation['SSCZBM'], '07.14') === 0) {
                $res2[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }

        $ssList = [];
        if ($res1 && $res2) {
            $ssList = array_merge($res1, $res2);
        }

        if (!empty($ssList)) {
            $basis[] = ['desc' => '手术操作【07.62 垂体腺部分切除术】和【07.14 垂体腺活组织检查】不能同时存在', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 手术操作不能同时存在：07.63垂体腺部分切除术，未特指入路；07.15垂体腺活组织检查NOS
     * @param $data
     * @return array
     */
    public function rule1399($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['SSCZBM'], '07.63') === 0) {
                $res1[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            } elseif (stripos($operation['SSCZBM'], '07.15') === 0) {
                $res2[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }

        $ssList = [];
        if ($res1 && $res2) {
            $ssList = array_merge($res1, $res2);
        }

        if (!empty($ssList)) {
            $basis[] = ['desc' => '手术操作【07.63 垂体腺部分切除术】和【07.15 垂体腺活组织检查】不能同时存在', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 手术操作不能同时存在：07.81胸腺部分切除术，开放性胸腺部分切除术；07.16胸腺活组织检查
     * @param $data
     * @return array
     */
    public function rule1400($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['SSCZBM'], '07.81') === 0) {
                $res1[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            } elseif (stripos($operation['SSCZBM'], '07.16') === 0) {
                $res2[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }

        $ssList = [];
        if ($res1 && $res2) {
            $ssList = array_merge($res1, $res2);
        }

        if (!empty($ssList)) {
            $basis[] = ['desc' => '手术操作【07.81 胸腺部分切除术，开放性胸腺部分切除术】和【07.16 胸腺活组织检查】不能同时存在', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 手术操作不能同时存在：07.81胸腺部分切除术，开放性胸腺部分切除术；07.83胸腔镜下胸腺部分切除术
     * @param $data
     * @return array
     */
    public function rule1401($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['SSCZBM'], '07.81') === 0) {
                $res1[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            } elseif (stripos($operation['SSCZBM'], '07.83') === 0) {
                $res2[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }

        $ssList = [];
        if ($res1 && $res2) {
            $ssList = array_merge($res1, $res2);
        }

        if (!empty($ssList)) {
            $basis[] = ['desc' => '手术操作【07.81 胸腺部分切除术，开放性胸腺部分切除术】和【07.83 胸腔镜下胸腺部分切除术】不能同时存在', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 手术编码有13.0000去除晶状体异物NOS
     * 应更换手术为13.0100用磁吸法的去除晶状体异物/13.0200不使用磁吸法的去除晶状体异物/13.0201晶状体切开异物取出术
     * @param $data
     * @return array
     */
    public function rule1403($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $ssList = [];
        foreach ($data['operation'] as $operation) {
            if ($operation['SSCZBM'] == '13.0000') {
                $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }
        if (!empty($ssList)) {
            $basis[] = ['desc' => '手术编码有【13.0000 去除晶状体异物NOS】，应更换手术为【13.0100 用磁吸法的去除晶状体异物】或【13.0200 不使用磁吸法的去除晶状体异物】或【13.0201 晶状体切开异物取出术】', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 手术编码有14.0000去除眼后节异物NOS
     * 应更换手术为14.0100用磁吸法去除眼后节异物/14.0101玻璃体异物磁吸术/14.0200不用磁吸法去除眼后节异物/14.0200x001眼后节异物去除术/14.0200x002玻璃体腔异物取出术/14.0201脉络膜切开异物取出术/14.0202后段眼球壁异物取出术
     * @param $data
     * @return array
     */
    public function rule1404($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $ssList = [];
        foreach ($data['operation'] as $operation) {
            if ($operation['SSCZBM'] == '14.0000') {
                $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }
        if (!empty($ssList)) {
            $basis[] = ['desc' => '手术编码有【14.0000 去除眼后节异物NOS】，应更换手术为【14.0100 用磁吸法去除眼后节异物】/【14.0101 玻璃体异物磁吸术】/【14.0200不用磁吸法去除眼后节异物/【14.0200x001 眼后节异物去除术】/【14.0200x002 玻璃体腔异物取出术】/【14.0201 脉络膜切开异物取出术】/【14.0202 后段眼球壁异物取出术】', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 手术编码有37.80首次或置换永久起搏器置入，装置类型未特指，更换手术编码为37.81-37.89
     * @param $data
     * @return array
     */
    public function rule1405($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $ssList = [];
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['SSCZBM'], '37.80') === 0) {
                $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }
        if (!empty($ssList)) {
            $basis[] = ['desc' => '手术编码有【37.80 首次或置换永久起搏器置入】，应更换手术编码为【37.81-37.89】', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 手术编码为79.2骨折开放性复位术不伴内固定，应更换手术编码为79.3骨折开放性复位术伴内固定
     * @param $data
     * @return array
     */
    public function rule1406($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $ssList = [];
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['SSCZBM'], '79.2') === 0) {
                $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }
        if (!empty($ssList)) {
            $basis[] = ['desc' => '手术编码有【79.2 骨折开放性复位术不伴内固定】，应更换手术编码为【79.3 骨折开放性复位术伴内固定】', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 提示另编码包括：置入血管支架的数量00.45-00.48，治疗血管的数量00.40-00.43，入脑前血管经皮血管成形术00.61，颅外血管经皮粥样硬化切除术17.53，分支血管操作00.44
     * @param $data
     * @return array
     */
    public function rule1407($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }
        $is4043 = false;
        $is404548 = false;
        $is6300 = false;
        foreach ($data['operation'] as $operation) {
            if ($operation['SSCZBM'] == '00.6300') {
                $is6300 = true;
            }
        }
        if (!$is6300) {
            return $basis;
        }
        foreach ($data['operation'] as $operation) {
            $ssbmstr = substr($operation['SSCZBM'], 0, 5);
            if ($ssbmstr == '00.40' || $ssbmstr == '00.41' || $ssbmstr == '00.42' || $ssbmstr == '00.43') {
                $is4043 = true;
            }
            if ($ssbmstr == '00.45' || $ssbmstr == '00.46' || $ssbmstr == '00.47' || $ssbmstr == '00.48') {
                $is404548 = true;
            }
        }

        if (!$is4043 || !$is404548) {
            $basis[] = ['desc' => '手术编码有【00.6300 颈动脉支架经皮置入术】，应另编码【00.45-00.48 置入血管支架的数量】，【00.40-00.43治疗血管的数量】，【00.61 入脑前血管经皮血管成形术】，【17.53】颅外血管经皮粥样硬化切除术，【00.44】分支血管操作', 'location' => ['ss' => '', 'user' => [], 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 提示另编码包括：非端对端吻合术（胸内或胸骨前食管吻合术及间置术）42.51-42.69，食管造口术42.10-42.19，胃造口术43.11-43.19
     * @param $data
     * @return array
     */
    public function rule1408($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $ssList = [];
        foreach ($data['operation'] as $operation) {
            if ($operation['SSCZBM'] == '42.4100') {
                $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }
        if (!empty($ssList)) {
            $basis[] = ['desc' => '手术编码有【42.4100 部分食管切除术】，另编码【42.51-42.69 非端对端吻合术】，【42.10-42.19 食管造口术】，【43.11-43.19 胃造口术】', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 提示另编码包括：非端对端的间置术或吻合术（胸内或胸骨前食管吻合术及间置术）42.51-42.69
     * @param $data
     * @return array
     */
    public function rule1409($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $ssList = [];
        foreach ($data['operation'] as $operation) {
            if ($operation['SSCZBM'] == '42.4200') {
                $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }
        if (!empty($ssList)) {
            $basis[] = ['desc' => '手术编码有【42.4200】，应另编码【42.51-42.69 非端对端的间置术或吻合术】', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 手术编码需合并：12.5100x001前房角穿刺术+12.5200x001前房角切开术，需要合并到12.5300眼前房角切开伴眼前房角穿刺
     * @param $data
     * @return array
     */
    public function rule1410($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['operation'] as $operation) {
            if ($operation['SSCZBM'] == '12.5100x001') {
                $res1[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            } elseif ($operation['SSCZBM'] == '12.5200x001') {
                $res2[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }

        $ssList = [];
        if ($res1 && $res2) {
            $ssList[] = array_merge($res1, $res2);
        }

        if (!empty($ssList)) {
            $basis[] = ['desc' => '手术编码【12.5100x001 前房角穿刺术】+【12.5200x001 前房角切开术】，需要合并到【12.5300 眼前房角切开伴眼前房角穿刺】', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 手术编码需合并：14.7401后入路玻璃体切割术+14.7500x001玻璃体腔内替代物注射术，需要合并到14.7202后入路玻璃体切割术伴替代物注入
     * @param $data
     * @return array
     */
    public function rule1411($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['operation'] as $operation) {
            if ($operation['SSCZBM'] == '14.7401') {
                $res1[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            } elseif ($operation['SSCZBM'] == '14.7500x001') {
                $res2[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }

        $ssList = [];
        if ($res1 && $res2) {
            $ssList[] = array_merge($res1, $res2);
        }

        if (!empty($ssList)) {
            $basis[] = ['desc' => '手术编码【14.7401 后入路玻璃体切割术】+【14.7500x001 玻璃体腔内替代物注射术】，需要合并到【14.7202 后入路玻璃体切割术伴替代物注入】', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 男性出院手术不应编：65-71女性生殖器官手术
     * @param $data
     * @return array
     */
    public function rule1413($data)
    {
        $basis = [];
        if ($data['data']['AAA02C'] != 1 || empty($data['operation'])) {
            return $basis;
        }

        $arr = ['65', '66', '67', '68', '69', '70', '71'];
        $ssList = [];
        foreach ($data['operation'] as $operation) {
            foreach ($arr as $value) {
                if (stripos($operation['SSCZBM'], $value) === 0) {
                    $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
                }
            }
        }
        if (!empty($ssList)) {
            $basis[] = ['desc' => '男性出院手术不应编【65-71 女性生殖器官手术】', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }
        return $basis;
    }

    /**
     * 女性出院手术不应编：60-64男性生殖器官手术
     * @param $data
     * @return array
     */
    public function rule1414($data)
    {
        $basis = [];
        if ($data['data']['AAA02C'] != 2 || empty($data['operation'])) {
            return $basis;
        }
        $arr = ['60', '61', '62', '63', '64'];
        $ssList = [];
        foreach ($data['operation'] as $operation) {
            foreach ($arr as $value) {
                if (stripos($operation['SSCZBM'], $value) === 0) {
                    $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
                }
            }
        }
        if (!empty($ssList)) {
            $basis[] = ['desc' => '女性出院手术不应编【60-64 男性生殖器官手术】', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }
        return $basis;
    }

    /**
     * 17.3为腹腔镜大肠部分切除术，48.6为直肠其他切除术，不能同时存在
     * @param $data
     * @return array
     */
    public function rule1415($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['SSCZBM'], '17.3') === 0) {
                $res1[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            } elseif (stripos($operation['SSCZBM'], '48.6') === 0) {
                $res2[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }

        $ssList = [];
        if ($res1 && $res2) {
            $ssList = array_merge($res1, $res2);
        }

        if (!empty($ssList)) {
            $basis[] = ['desc' => '手术【17.3 为腹腔镜大肠部分切除术】+【48.6为直肠其他切除术】不能同时存在', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 01.3为大脑和脑膜切开术，92.3为立体定向放射外科，不能同时存在
     * @param $data
     * @return array
     */
    public function rule1416($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['SSCZBM'], '01.3') === 0) {
                $res1[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            } elseif (stripos($operation['SSCZBM'], '92.3') === 0) {
                $res2[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }

        $ssList = [];
        if ($res1 && $res2) {
            $ssList = array_merge($res1, $res2);
        }

        if (!empty($ssList)) {
            $basis[] = ['desc' => '手术【01.3 为大脑和脑膜切开术】+【92.3 为立体定向放射外科】不能同时存在', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 不能作为主要手术的编码范围：00.40-00.43手术血管的数量、00.45-00.48置入支架的数量、00.9其他操作和介入、00.74-00.77任何轴面类型
     * @param $data
     * @return array
     */
    public function rule1417($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $str = '00.4000,00.4100,00.4200,00.4300,00.4301,00.4302,00.4500,00.4600,00.4700,00.4801,00.4802,00.9100,00.9200,00.9300,00.9400,00.7400,00.7500,00.7600,00.7601,00.7700';
        $arr = explode(',', $str);
        $SSCZBM = '';
        $ssList = [];
        foreach ($data['operation'] as $operation) {
            if ($operation['SFZYSS'] == 1 && in_array($operation['SSCZBM'], $arr)) {
                $SSCZBM = $operation['SSCZBM'];
                $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }
        if (!empty($ssList)) {
            $basis[] = ['desc' => '【' . $SSCZBM . '】不能作为主要手术', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }
        return $basis;
    }

    /**
     * 手术编码81.0为脊柱融合术，共有5个手术步骤。包括：
     * （1）81.0脊柱融合术
     * （2）84.51任何椎体融合装置置入
     * （3）84.52任何重组骨形态形成蛋白的植入
     * （4）77.70-77.79为移植进行的自身成熟骨切除术
     * （5）81.62-81.64融合椎体的数量
     * @param $data
     * @return array
     */
    public function rule1418($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $res = false;
        $ssList = [];
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['SSCZBM'], '81.0') === 0) {
                $res = true;
                $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
                break;
            }
        }

        if ($res && count($data['operation']) < 3) {
            $basis[] = ['desc' => '手术编码81.0为脊柱融合术，共有5个手术步骤。必填有以下3个步骤：（1）81.0脊柱融合术（2）84.51任何椎体融合装置置入（5）81.62-81.64融合椎体的数量', 'location' => ['ss' => $ssList]];
        } elseif ($res) {
            $res1 = '';
            $res2 = '';
            $res3 = '';

            foreach ($data['operation'] as $operation) {
                if ($res1 && $res2 && $res3) {
                    break;
                }
                //取前四位
                $ssCZBM = substr($operation['SSCZBM'], 0, 4);
                if ($ssCZBM == '81.0') {
                    $res1 = $operation['SSCZBM'];
                }
                //取前五位
                $ssCZBM = substr($operation['SSCZBM'], 0, 5);
                if ($ssCZBM == '84.51') {
                    $res2 = $operation['SSCZBM'];
                }
                if ($ssCZBM == '81.62' || $ssCZBM == '81.63' || $ssCZBM == '81.64') {
                    $res3 = $operation['SSCZBM'];
                }
            }
            if (!$res1 || !$res2 || !$res3) {
                $basis[] = ['desc' => '手术编码81.0为脊柱融合术，共有5个手术步骤。必填有以下3个步骤：（1）81.0脊柱融合术（2）84.51任何椎体融合装置置入（5）81.62-81.64融合椎体的数量', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
            }
        }

        return $basis;
    }

    /**
     * 是否有出院31日内再住院计划（病案首页是否有出院31天内再住院计划，如果选择无，目的不能为空格、-、文字。）
     * @param $data
     * @return array
     */
    public function rule1419($data)
    {
        $basis = [];
        if ($data['data']['AEM03C'] == 1 && mb_strlen(trim($data['data']['AEM04'])) > 0) {
            $basis[] = ['desc' => '31日内再住院计划【无】，不应填写目的', 'location' => ['user' => ['AEM03C', 'AEM04'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 是否有出院31日内再住院计划（病案首页是否有出院31天内再住院计划，如果选择有，目的必填）
     * @param $data
     * @return array
     */
    public function rule1420($data)
    {
        $basis = [];
        if (!empty($data['data']['AEM03C']) && ($data['data']['AEM03C'] == 2 || $data['data']['AEM03C'] == '有' || $data['data']['AEM03C'] == '2') && empty($data['data']['AEM04'])) {
            $basis[] = ['desc' => '31日内再住院计划【有】，目的必填', 'location' => ['user' => ['AEM03C', 'AEM04'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 手术编码（病案首页有手术操作，手术级别、手术类型、术者必填【术者信息没有】）
     * @param $data
     * @return array
     */
    public function rule1421($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $ssList = [];
        foreach ($data['operation'] as $operation) {
            if (in_array($operation['SSPB'], ['手术', '介入治疗'])) {
                if (empty($operation['SSCZMC'])) {
                    $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_NAME'];
                }
                if (empty($operation['SSCZBM'])) {
                    $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
                }
                if (empty($operation['SSJB'])) {
                    $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'OPE_LEVEL'];
                }
                if (empty($operation['SZXM'])) {
                    $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'OPE_MAN_NAME'];
                }
            }
        }
        if (!empty($ssList)) {
            $basis[] = ['desc' => '手术判别是【手术】或【介入治疗】，【手术编码、手术名称、手术级别、术者】必填', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }
        return $basis;
    }

    /**
     * 病案首页有麻醉方式，麻醉医师必填
     * @param $data
     * @return array
     */
    public function rule1422($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $ssList = [];
        foreach ($data['operation'] as $operation) {
            if (!empty($operation['MZFS']) && mb_strlen($operation['MZFS']) > 2 && (empty($operation['MZYSXM']) || $operation['MZYSXM'] == '-' || is_numeric($operation['MZYSXM']))) {
                $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'OPE_MAN_NAME'];
            }
        }

        if (!empty($ssList)) {
            $basis[] = ['desc' => '有麻醉方式，麻醉医师未填写', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }
        return $basis;
    }

    /**
     * 病案首页有麻醉医师，麻醉方式必填
     * @param $data
     * @return array
     */
    public function rule1423($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }
        $ssList = [];
        foreach ($data['operation'] as $operation) {
            if (!empty($operation['MZYSXM']) && mb_strlen($operation['MZYSXM']) > 2 && (empty($operation['MZFS']) || $operation['MZFS'] == '-')) {
                $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'OPE_MAN_NAME'];
            }
        }

        if (!empty($ssList)) {
            $basis[] = ['desc' => '有麻醉医师，麻醉方式未填写', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }
        return $basis;
    }

    /**
     * 主要诊断编码（主要诊断为T88.6-T88.7，药物过敏应为有，且应填写过敏药物）
     * @param $data
     * @return array
     */
    public function rule1424($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $arr = ['T88.6', 'T88.7'];
        $zdList = [];
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if ($diagnosis['ZZPB'] == 1 && stripos($diagnosis['ZDBM'], $value) === 0) {
                    $zdList[] = ['ZZPB' => 1,  'field' => 'ICD10_ID1'];
                    break;
                }
            }
        }

        if (!empty($zdList) && ($data['data']['AEB02C'] != 2 || empty($data['data']['AEB01']))) {
            $basis[] = ['desc' => '主要诊断为T88.6-T88.7，药物过敏应为有，且应填写过敏药物', 'location' => ['user' => ['AEB02C', 'AEB01'], 'ss' => $zdList, 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 主要诊断为C77-C79，病理诊断编码应为M****\/6
     * @param $data
     * @return array
     */
    public function rule1425($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $arr = ['C77', 'C78', 'C79'];
        $zdList = [];
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if ($diagnosis['ZZPB'] == 1 && stripos($diagnosis['ZDBM'], $value) === 0) {
                    $zdList[] = ['ZZPB' => 1,  'field' => 'ICD10_ID1'];
                    break;
                }
            }
        }

        $res2 = preg_match('/^[M](.*)[\/][6]$/', $data['data']['ABF01C']);
        if (!empty($zdList) && !$res2) {
            $basis[] = ['desc' => '主要诊断为C77-C79，病理诊断编码应为M****/6', 'location' => ['user' => ['AEB02C', 'AEB01'], 'ss' => $zdList, 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 病案首页离院方式为 医嘱转院 和 医嘱转社区卫生服务机构/乡镇卫生院，拟接受医疗机构名称必填
     * @param $data
     * @return array
     */
    public function rule1427($data)
    {
        $AEM01C = $data['data']['AEM01C'] ?? '';
        $YZZY_JGMC = $data['data']['YZZY_JGMC'] ?? '';

        $basis = [];
        if (in_array($AEM01C, [2, 3]) && empty($YZZY_JGMC)) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['AEM01C'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 病案质量（病案首页病案质量为乙或丙，提示是否应修改为甲）
     * @param $data
     * @return array
     */
    public function rule1428($data)
    {
        $basis = [];
        if ($data['data']['AED01C'] == 2) {
            $basis[] = ['desc' => '当前病案质量为【乙】，是否应修改为甲', 'location' => ['user' => ['AED01C'], 'zd' => [], 'ss' => []]];
        } elseif ($data['data']['AED01C'] == 3) {
            $basis[] = ['desc' => '当前病案质量为【丙】，是否应修改为甲', 'location' => ['user' => ['AED01C'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 年龄（病案首页年龄小于6岁，职业应选择其他）
     * @param $data
     * @return array
     */
    public function rule1429($data)
    {
        $basis = [];
        if (($data['data']['AAA04'] == '' || $data['data']['AAA04'] == null) && ($data['data']['AAA40'] == '' || $data['data']['AAA40'] == null)) {
            return $basis;
        }

        $AAA04 = !empty($data['data']['AAA04']) ? $data['data']['AAA04'] : 0;
        $AAA18C = !empty($data['data']['AAA18C']) ? $data['data']['AAA18C'] : '';
        if ($AAA04 >= 6 || empty($AAA18C)) {
            return [];
        }
        $code = config('dictionaries.AAA18C');
        $aaa18c =  $code[$AAA18C] ?? $AAA18C;


        if (in_array($aaa18c, ["其他", "其它", "学生", "90", '无业人员'])) {
            return [];
        }

        $basis[] = ['desc' => '年龄小于6岁，职业应选择无职业', 'location' => ['user' => ['AAA04', 'AAA18C'], 'zd' => [], 'ss' => []]];
        return $basis;
    }

    /**
     * 病案首页质控日期应大于等于出院时间
     * @param $data
     * @return array
     */
    public function rule1430($data)
    {
        $basis = [];
        if (empty($data['data']['AED04']) || empty($data['data']['AAC01'])) {
            return $basis;
        }
        $AED04 = date('Y-m-d', strtotime($data['data']['AED04']));
        $AAC01 = date('Y-m-d', strtotime($data['data']['AAC01']));

        if ($AED04 < $AAC01) {
            $basis[] = ['desc' => '质控日期应大于等于出院时间', 'location' => ['user' => ['AED04', 'AAC01'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 身份号 男最后二位奇数，女最后二位是偶数
     * @param $data
     * @return array
     */
    public function rule1431($data)
    {
        $basis = [];
        $AAA07 = $data['data']['AAA07'];
        if (empty($AAA07)) {
            return $basis;
        }
        //$str = intval(mb_substr($AAA07, 16, 1));
        //取倒数第二位
        $str = intval(mb_substr($AAA07, -2, 1));
        $AAA02C = $data['data']['AAA02C'];
        if (strpos($AAA02C, '男') !== false) {
            $AAA02C = 1;
        } elseif (strpos($AAA02C, '女') !== false) {
            $AAA02C = 2;
        }
        if ($str % 2 === 0) {
            if ($AAA02C != 2 || $AAA02C != '2') {
                $basis[] = ['desc' => '身份号倒数第二位是偶数，性别应为【女】', 'location' => ['user' => ['AAA02C'], 'zd' => [], 'ss' => []]];
            }
        } else {
            if ($AAA02C != 1 || $AAA02C != '1') {
                $basis[] = ['desc' => '身份号倒数第二位是奇数，性别应为【男】', 'location' => ['user' => ['AAA02C'], 'zd' => [], 'ss' => []]];
            }
        }
        return $basis;
    }

    /**
     * 收费中含“视网膜激光光凝术”手术名称无关键字“视网膜”
     * @param $data
     * @return array
     */
    public function rule1432($data)
    {
        $basis = [];
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return $basis;
        }
        $fymcList = array_unique(array_column($fymcArr, 'FYMC'));
        $res1 = false;
        foreach ($fymcList as $fymc) {
            if (stripos($fymc, '视网膜激光光凝术') !== false) {
                $res1 = true;
                break;
            }
        }

        if ($res1) {
            $res2 = false;
            foreach ($data['operation'] as $operation) {
                if (stripos($operation['SSCZMC'], '视网膜') !== false) {
                    $res2 = true;
                    break;
                }
            }
            if (!$res2) {
                $basis[] = ['desc' => '收费中含【视网膜激光光凝术】，手术名称应含【视网膜】', 'location' => ['user' => [], 'zd' => [], 'ss' => [['OPE_ORDER' => 1000, 'field' => '']]]];
            }
        }

        return $basis;
    }

    /**
     * 收费明细 含【纤维支气管镜检查】 手术名称不含【纤维支气管镜检查】
     * @param $data
     * @return array
     */
    public function rule1433($data)
    {
        $basis = [];
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return $basis;
        }
        $fymcList = array_unique(array_column($fymcArr, 'FYMC'));
        $res1 = false;
        foreach ($fymcList as $fymc) {
            if (stripos($fymc, '纤维支气管镜检查') !== false) {
                $res1 = true;
                break;
            }
        }

        if ($res1) {
            $res2 = false;
            //纤维支气管镜检查,经人工造口的支气管镜检查,闭合性[内镜的]支气管活组织检查,支气管镜下支气管活检,支气管镜下诊断性支气管肺泡灌洗[BAL],超声内镜下支气管穿刺活组织检查术,纤维支气管镜检查伴肺泡灌洗术,气管镜刷检术,支气管灌洗,支气管刷检术
            foreach ($data['operation'] as $operation) {
                if (stripos($operation['SSCZMC'], '纤维支气管镜检查') !== false || stripos($operation['SSCZMC'], '经人工造口的支气管镜检查') !== false || stripos($operation['SSCZMC'], '闭合性[内镜的]支气管活组织检查') !== false || stripos($operation['SSCZMC'], '支气管镜下支气管活检') !== false || stripos($operation['SSCZMC'], '支气管镜下诊断性支气管肺泡灌洗[BAL]') !== false || stripos($operation['SSCZMC'], '超声内镜下支气管穿刺活组织检查术') !== false || stripos($operation['SSCZMC'], '纤维支气管镜检查伴肺泡灌洗术') !== false || stripos($operation['SSCZMC'], '气管镜刷检术') !== false || stripos($operation['SSCZMC'], '支气管灌洗') !== false || stripos($operation['SSCZMC'], '支气管刷检术') !== false || stripos($operation['SSCZMC'], '电子支气管镜检查') !== false) {
                    $res2 = true;
                    break;
                }
            }
            if (!$res2) {
                //手术名称中应包含其中一项,否则提示
                $basis[] = ['desc' => '收费中含【纤维支气管镜检查】，手术名称应包含【纤维支气管镜检查,经人工造口的支气管镜检查,闭合性[内镜的]支气管活组织检查,支气管镜下支气管活检,支气管镜下诊断性支气管肺泡灌洗[BAL],超声内镜下支气管穿刺活组织检查术,纤维支气管镜检查伴肺泡灌洗术,气管镜刷检术,支气管灌洗,支气管刷检术】中的的一项', 'location' => ['ss' => [['OPE_ORDER' => 1000, 'field' => '']], 'user' => [], 'zd' => []]];
            }
        }

        return $basis;
    }

    /**
     * 收费明细 含【宫颈扩张术】手术名称不含【子宫颈扩张引产】（不是产科不质控）
     * @param $data
     * @return array
     */
    public function rule1434($data)
    {
        $basis = [];
        $AAC11C = !empty($data['data']['AAC11C']) ? trim($data['data']['AAC11C']) : '';
        if ($AAC11C != 213) {
            return $basis;
        }

        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return $basis;
        }
        $fymcList = array_unique(array_column($fymcArr, 'FYMC'));
        $res1 = false;
        foreach ($fymcList as $fymc) {
            if (stripos($fymc, '宫颈扩张术') !== false) {
                $res1 = true;
                break;
            }
        }

        if ($res1) {
            $res2 = false;
            foreach ($data['operation'] as $operation) {
                if (stripos($operation['SSCZMC'], '子宫颈扩张引产') !== false) {
                    $res2 = true;
                    break;
                }
            }
            if (!$res2) {
                $basis[] = ['desc' => '收费中含【宫颈扩张术】，手术名称应含【子宫颈扩张引产】', 'location' => ['ss' => [['OPE_ORDER' => 1000, 'field' => '']], 'user' => [], 'zd' => []]];
            }
        }

        return $basis;
    }

    /**
     * 收费明细 含【急性缺血性脑卒中静脉溶栓治疗】 手术名称不含【脑动脉血栓溶解剂灌注】
     * @param $data
     * @return array
     */
    public function rule1435($data)
    {
        $basis = [];
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return $basis;
        }
        $fymcList = array_unique(array_column($fymcArr, 'FYMC'));
        $res1 = false;
        foreach ($fymcList as $fymc) {
            if (stripos($fymc, '急性缺血性脑卒中静脉溶栓治疗') !== false) {
                $res1 = true;
                break;
            }
        }

        if ($res1) {
            $res2 = false;
            foreach ($data['operation'] as $operation) {
                if (stripos($operation['SSCZMC'], '脑动脉血栓溶解剂灌注') !== false) {
                    $res2 = true;
                    break;
                }
            }
            if (!$res2) {
                $basis[] = ['desc' => '收费中含【急性缺血性脑卒中静脉溶栓治疗】，手术名称应含【脑动脉血栓溶解剂灌注】', 'location' => ['ss' => [['OPE_ORDER' => 1000, 'field' => '']], 'user' => [], 'zd' => []]];
            }
        }

        return $basis;
    }

    /**
     * 收费明细 含【人工破膜术】手术名称不含【人工破膜引产】
     * @param $data
     * @return array
     */
    public function rule1436($data)
    {
        $basis = [];
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return $basis;
        }
        $fymcList = array_unique(array_column($fymcArr, 'FYMC'));
        $res1 = false;
        foreach ($fymcList as $fymc) {
            if (stripos($fymc, '人工破膜术') !== false) {
                $res1 = true;
                break;
            }
        }

        if ($res1) {
            $res2 = false;
            foreach ($data['operation'] as $operation) {
                if (stripos($operation['SSCZMC'], '人工破膜') !== false) {
                    $res2 = true;
                    break;
                }
            }
            if (!$res2) {
                $basis[] = ['desc' => '收费中含【人工破膜术】，手术名称应含【人工破膜】', 'location' => ['ss' => [['OPE_ORDER' => 1000, 'field' => '']], 'user' => [], 'zd' => []]];
            }
        }

        return $basis;
    }

    /**
     * 收费明细 含【手取胎盘术】 手术名称不含【手取胎盘】
     * @param $data
     * @return array
     */
    public function rule1437($data)
    {
        $basis = [];
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return $basis;
        }
        $fymcList = array_unique(array_column($fymcArr, 'FYMC'));
        $res1 = false;
        foreach ($fymcList as $fymc) {
            if (stripos($fymc, '手取胎盘术') !== false) {
                $res1 = true;
                break;
            }
        }

        if ($res1) {
            $res2 = false;
            foreach ($data['operation'] as $operation) {
                if (stripos($operation['SSCZMC'], '手取胎盘') !== false) {
                    $res2 = true;
                    break;
                }
            }
            if (!$res2) {
                $basis[] = ['desc' => '收费中含【手取胎盘术】，手术名称应含【手取胎盘】', 'location' => ['ss' => [['OPE_ORDER' => 1000, 'field' => '']], 'user' => [], 'zd' => []]];
            }
        }

        return $basis;
    }

    /**
     * 收费明细 含【无创呼吸机辅助呼吸，手术名称不含【无创呼吸机辅助通气】
     * @param $data
     * @return array
     */
    public function rule1438($data)
    {
        $basis = [];
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return $basis;
        }
        $fymcList = array_unique(array_column($fymcArr, 'FYMC'));
        $res1 = false;
        foreach ($fymcList as $fymc) {
            if (stripos($fymc, '无创呼吸机辅助呼吸') !== false) {
                $res1 = true;
                break;
            }
        }

        if ($res1) {
            $res2 = false;
            foreach ($data['operation'] as $operation) {
                if (stripos($operation['SSCZMC'], '无创呼吸机辅助通气') !== false) {
                    $res2 = true;
                    break;
                }
            }
            if (!$res2) {
                $basis[] = ['desc' => '收费中含【无创呼吸机辅助呼吸】，手术名称应含【无创呼吸机辅助通气】', 'location' => ['ss' => [['OPE_ORDER' => 1000, 'field' => '']], 'user' => [], 'zd' => []]];
            }
        }

        return $basis;
    }

    /**
     * 收费明细 含【呼吸机辅助呼吸】 且 收费数量≥96，手术名称不含【呼吸机治疗[大于等于96小时]】
     * @param $data
     * @return array
     */
    public function rule1439($data)
    {
        $basis = [];
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return $basis;
        }

        $FYSL = 0;
        foreach ($fymcArr as $fyInfo) {
            if (stripos($fyInfo['FYMC'], '呼吸机辅助呼吸') !== false) {
                $FYSL += $fyInfo['FYSL'];
            }
        }

        if ($FYSL >= 96) {
            $res2 = false;
            foreach ($data['operation'] as $operation) {
                if (stripos($operation['SSCZMC'], '呼吸机治疗[大于等于96小时]') !== false) {
                    $res2 = true;
                    break;
                }
            }
            if (!$res2) {
                $basis[] = ['desc' => '收费中含【呼吸机辅助呼吸】且收费数量≥96，手术名称应含【呼吸机治疗[大于等于96小时]】', 'location' => ['ss' => [['OPE_ORDER' => 1000, 'field' => '']], 'user' => [], 'zd' => []]];
            }
        }

        return $basis;
    }

    /**
     * 收费明细含【呼吸机辅助呼吸】 且收费数量＜96，手术名称不含【呼吸机治疗[小于96小时]】
     * @param $data
     * @return array
     */
    public function rule1440($data)
    {
        $basis = [];
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return $basis;
        }

        $FYSL = 0;
        foreach ($fymcArr as $fyInfo) {
            if (stripos($fyInfo['FYMC'], '呼吸机辅助呼吸') !== false) {
                $FYSL += $fyInfo['FYSL'];
                //break;
            }
        }

        if ($FYSL > 0 && $FYSL < 96) {
            $res2 = false;
            foreach ($data['operation'] as $operation) {
                if (stripos($operation['SSCZMC'], '呼吸机治疗[小于96小时]') !== false) {
                    $res2 = true;
                    break;
                }
            }
            if (!$res2) {
                $basis[] = ['desc' => '收费中含【呼吸机辅助呼吸】且收费数量＜96，手术名称应含【呼吸机治疗[小于96小时]】', 'location' => ['ss' => [['OPE_ORDER' => 1000, 'field' => '']], 'user' => [], 'zd' => []]];
            }
        }

        return $basis;
    }

    /**
     * 收费明细含【呼吸机辅助呼吸】 ，有创呼吸机使用时间【不能为0，不能为空】
     * @param $data
     * @return array
     */
    public function rule1441($data)
    {
        $basis = [];
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return $basis;
        }

        $res1 = false;
        $FYSL = 0;
        foreach ($fymcArr as $fyInfo) {
            if (stripos($fyInfo['FYMC'], '呼吸机辅助呼吸') !== false) {
                $res1 = true;
                $FYSL += $fyInfo['FYSL'];
                break;
            }
        }

        if ($res1 && empty($data['data']['AEL01'])) {
            $days = floor($FYSL / 24); // 计算天数
            $remainingHours = $FYSL % 24;   // 计算剩余的小时数
            $basis[] = ['desc' => '收费明细含【呼吸机辅助呼吸 ' . $days . '天' . $remainingHours . '小时】有创呼吸机使用时间【不能为0，不能为空】', 'location' => ['user' => ['AEL01'], 'zd' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 收费明细 含【体外人工膜肺(ECMO)】 手术名称不含【体外膜氧合[ECMO]】
     * @param $data
     * @return array
     */
    public function rule1442($data)
    {
        $basis = [];
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return $basis;
        }
        $fymcList = array_unique(array_column($fymcArr, 'FYMC'));
        $res1 = false;
        foreach ($fymcList as $fymc) {
            if (stripos($fymc, '体外人工膜肺(ECMO)') !== false) {
                $res1 = true;
                break;
            }
        }

        if ($res1) {
            $res2 = false;
            foreach ($data['operation'] as $operation) {
                if (stripos($operation['SSCZMC'], '体外膜氧合[ECMO]') !== false) {
                    $res2 = true;
                    break;
                }
            }
            if (!$res2) {
                $basis[] = ['desc' => '收费中含【体外人工膜肺(ECMO)】，手术名称应含【体外膜氧合[ECMO]】', 'location' => ['ss' => [['OPE_ORDER' => 1000, 'field' => '']], 'user' => [], 'zd' => []]];
            }
        }

        return $basis;
    }

    /**
     * 诊断编码范围：N70 - N77或 Q50.401，应为女性病例
     * @param $data
     * @return array
     */
    public function rule1443($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }
        $arr = ['N70', 'N71', 'N72', 'N73', 'N74', 'N75', 'N76', 'N77', 'Q50.401'];
        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            foreach ($arr as $value) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
                }
            }
        }

        if (!empty($zdList) && $data['data']['AAA02C'] != 2) {
            $basis[] = ['desc' => '诊断编码范围【N70-N77】或【Q50.401】应为女性病例', 'location' => ['user' => ['AAA02C'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 新生儿病例，出院时天龄大于28天，出院诊断不能有P编码的诊断
     * @param $data
     * @return array
     */
    public function rule1444($data)
    {
        $basis = [];
        $AAA04 = !empty($data['data']['AAA04']) ? $data['data']['AAA04'] : 0;
        $AAA40 = !empty($data['data']['AAA40']) ? $data['data']['AAA40'] : 0;
        if ($AAA04 < 1 || $AAA40 < 28) {
            return $basis;
        }

        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'P') === 0) {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }

        if (!empty($zdList)) {
            $basis[] = ['desc' => '出院天龄大于28天，出院诊断不能有P编码的诊断', 'location' => ['user' => ['AAA04', 'AAA40'], 'zd' => $zdList, 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 新生儿病例，出院时天龄小于28天，不能出现Z编码诊断
     * @param $data
     * @return array
     */
    public function rule1447($data)
    {
        $basis = [];
        $AAA04 = !empty($data['data']['AAA04']) ? $data['data']['AAA04'] : 0;
        $AAA40 = !empty($data['data']['AAA40']) ? $data['data']['AAA40'] : 0;
        if ($AAA04 > 0 || $AAA40 > 28) {
            return $basis;
        }

        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'Z') === 0) {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }

        if (!empty($zdList)) {
            $basis[] = ['desc' => '出院天龄小于28天，出院诊断不能有Z编码诊断', 'location' => ['user' => ['AAA04', 'AAA40'], 'zd' => $zdList, 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 其他诊断编码为T81-T88，容易出现医疗事故的编码，请核对
     * @param $data
     * @return array
     */
    public function rule1448($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $arr = ['T81', 'T82', 'T83', 'T84', 'T85', 'T86', 'T87', 'T88'];
        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            foreach ($arr as $value) {
                if ($diagnosis['ZZPB'] != 1 && stripos($diagnosis['ZDBM'], $value) === 0) {
                    $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
                }
            }
        }

        if (!empty($zdList)) {
            $basis[] = ['desc' => '其他诊断编码为【T81-T88】，容易出现医疗事故的编码，请核对', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 含“小儿肠炎”，年龄要小于2岁
     * @param $data
     * @return array
     */
    public function rule1449($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDMC'], '小儿肠炎') !== false) {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }

        if (!empty($zdList)) {
            $AAA04 = !empty($data['data']['AAA04']) ? $data['data']['AAA04'] : 0;
            if ($AAA04 >= 2) {
                $basis[] = ['desc' => '诊断名称含【小儿肠炎】，年龄要小于2岁', 'location' => ['user' => 'AAA04', 'zd' => $zdList, 'ss' => []]];
            }
        }

        return $basis;
    }

    /**
     * K83.1梗阻性黄疸和K80胆结石，不能同时存在
     * @param $data
     * @return array
     */
    public function rule1450($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'K83.1') === 0) {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            } elseif (stripos($diagnosis['ZDBM'], 'K80') === 0) {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '【K83.1 梗阻性黄疸】和【K80 胆结石】不能同时存在', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 身份证与出生日期要对应（与 rule27 合并）
     * @param $data
     * @return array
     */
    public function rule1451($data)
    {
        $basis = [];
        $AAA07 = !empty($data['data']['AAA07']) ? mb_substr($data['data']['AAA07'], 6, 8) : '';
        $AAA03 = !empty($data['data']['AAA03']) ? date("Ymd", strtotime($data['data']['AAA03'])) : '';
        if (empty($AAA07) || empty($AAA03) || $data['data']['AAA07'] == "-") {
            return $basis;
        }
        if ($AAA07 != $AAA03) {
            $basis[] = ['desc' => '身份证号与出生日期不对应', 'location' => ['user' => ['AAA07', 'AAA03'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 护理天数之和要等于住院天数
     * @param $data
     * @return array
     */
    public function rule1452($data)
    {
        // 住院天数
        $AAC04 = !empty($data['data']['AAC04']) ? $data['data']['AAC04'] : 0;

        // 特技护理、一级护理、二级护理、三级护理 之和
        $TJHLTS = !empty($data['data']['TJHL']) ? $data['data']['TJHL'] : 0;
        $YJHLTS = !empty($data['data']['YJHL']) ? $data['data']['YJHL'] : 0;
        $EJHLTS = !empty($data['data']['EJHL']) ? $data['data']['EJHL'] : 0;
        $SJHLTS = !empty($data['data']['SJHL']) ? $data['data']['SJHL'] : 0;
        $HLTS_SUM = intval($TJHLTS) + intval($YJHLTS) + intval($EJHLTS) + intval($SJHLTS);

        $basis = [];
        if ($data['data']['AAC11C'] == 459 || $data['data']['AAC11C'] == 474 || $data['data']['AAC11C'] == 294 || $data['data']['AAC11C'] == 4129) {
            if ($AAC04 == $HLTS_SUM || $AAC04 == $HLTS_SUM - 1) {
                $basis = [];
            } else {
                $basis[] = ['desc' => '护理天数之和不等于住院天数', 'location' => ['user' => ['AAC04', 'TJHL', 'YJHL', 'EJHL', 'SJHL'], 'zd' => [], 'ss' => []]];
            }
        } elseif ($AAC04 != $HLTS_SUM) {
            $basis[] = ['desc' => '护理天数之和不等于住院天数', 'location' => ['user' => ['AAC04', 'TJHL', 'YJHL', 'EJHL', 'SJHL'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 入院病情有,诊断时误填或漏填
     * @param $data
     * @return array
     */
    public function rule1453($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $ABC03C = config('dictionaries.ABC03C');

        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            //!in_array($diagnosis['RYQK'], $ABC03C)修改为empty($diagnosis['RYQK'])
            if (!empty($diagnosis['ZDBM']) && empty($diagnosis['RYQK'])) {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'RYQK'];
            }
        }
        if (!empty($zdList)) {
            $basis[] = ['desc' => '入院情况未填写', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 手术编码有96.7101，有创呼吸机使用时间应小于96小时
     * @param $data
     * @return array
     */
    public function rule1454($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $ssList = [];
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['SSCZBM'], '96.7101') === 0) {
                $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }

        $AEL01 = !empty($data['data']['AEL01']) ? $data['data']['AEL01'] : 0;
        $AEL01_T = !empty($data['data']['AEL01_T']) ? $data['data']['AEL01_T'] : 0;
        if ($AEL01_T > 0) {
            $AEL01 = $AEL01 + ($AEL01_T * 24);
        }
        if (!empty($ssList) && $AEL01 >= 96) {
            $basis[] = ['desc' => '手术编码有【96.7101】有创呼吸机使用时间应小于96小时', 'location' => ['user' => ['AEL01'], 'ss' => $ssList, 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 手术编码有96.7201，有创呼吸机使用时间应大于等于96小时
     * @param $data
     * @return array
     */
    public function rule1455($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $ssList = [];
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['SSCZBM'], '96.7201') === 0) {
                $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }

        $AEL01 = !empty($data['data']['AEL01']) ? $data['data']['AEL01'] : 0;
        $AEL01_T = !empty($data['data']['AEL01_T']) ? $data['data']['AEL01_T'] : 0;
        if ($AEL01_T > 0) {
            $AEL01 = $AEL01 + ($AEL01_T * 24);
        }

        if (!empty($ssList) && $AEL01 < 96) {
            $basis[] = ['desc' => '手术编码有【96.7201】有创呼吸机使用时间应大于等于96小时', 'location' => ['user' => ['AEL01'], 'ss' => $ssList, 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 一级切口，愈合类别不能为丙级
     * @param $data
     * @return array
     */
    public function rule1456($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $ssList = [];
        foreach ($data['operation'] as $operation) {
            if ($operation['QKDJ'] == 1 && $operation['YHDJ'] == 3) {
                $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'QKDJ'];
                $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'YHDJ'];
            }
        }

        if (!empty($ssList)) {
            $basis[] = ['desc' => '手术一级切口，愈合类别不能为丙级', 'location' => ['user' => ['AEL01'], 'ss' => $ssList, 'zd' => []]];
        }
        return $basis;
    }

    /**
     * 诊断编码出现S00-S09，颅内损伤昏迷时间6个空必填一个数字
     * @param $data
     * @return array
     */
    public function rule1457($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $arr = ['S00', 'S01', 'S02', 'S03', 'S04', 'S05', 'S06', 'S07', 'S08', 'S09'];
        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            foreach ($arr as $value) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
                }
            }
        }

        if (!empty($zdList)) {
            if (!is_numeric($data['data']['AEJ01']) && !is_numeric($data['data']['AEJ02']) && !is_numeric($data['data']['AEJ03']) && !is_numeric($data['data']['AEJ04']) && !is_numeric($data['data']['AEJ05']) && !is_numeric($data['data']['AEJ06'])) {
                $basis[] = ['desc' => '诊断编码出现【S00-S09】颅内损伤昏迷时间不能全部为空', 'location' => ['user' => ['AEJ01', 'AEJ02', 'AEJ03', 'AEJ04', 'AEJ05', 'AEJ06'], 'zd' => $zdList, 'ss' => []]];
            }
        }

        return $basis;
    }

    /**
     * 无效的主要诊断编码
     * @param $data
     * @return array
     */
    public function rule1458($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $JBDM = '';
        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $JBDM = $diagnosis['ZDBM'];
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
                break;
            }
        }
        if (!$JBDM) {
            return $basis;
        }
        $str = "T92.600x002:创伤性手指缺如|T90.501:陈旧性颅脑损伤|T75:陈旧性跟骨骨折|R65|B95:病原体的附加编码|B96:病原体的附加编码|B97:病原体的附加编码|T30:烧伤部位面积未特指|T31:烧伤部位面积未特指|T32:腐蚀上的面积编码|Z33:单纯妊娠状态|Z37:分娩结局 活产儿分娩地点|Z38:分娩结局 活产儿分娩地点|Z53:由于XX原因 治疗未实施|Z80:家族史|Z81:家族史|Z82:家族史|Z83:家族史|Z84:家族史|Z85:恶行肿瘤个人史|Z86:其他疾病个人史|Z87:其他疾病个人史|Z88:药物 生物制剂过敏史|Z89:肢体 器官后天缺失|Z90:肢体 器官后天缺失|Z91:危险因素个人史|Z92:医疗个人史|Z93:单纯人工造口状态|Z94:组织和器官移植状态|Z95:具有心脏 血管的植入物和移植物|Z96:具有其它功能性植入物和装置|Z97:具有其它功能性植入物和装置|Z98:其它单纯的手术后状态|Z99:依赖于可启动装置和机器|U80:耐药菌感染|U81:耐药菌感染|U82:耐药菌感染|U83:耐药菌感染|U84:耐药菌感染|U85:耐药菌感染|U86";
        $arr = explode('|', $str);
        $res = '';
        foreach ($arr as $val) {
            $zdbm = explode(":", $val);
            if (stripos($JBDM, $zdbm[0]) === 0) {
                $res = $zdbm[0];
                if (!empty($zdbm[1])) {
                    $res .= '【' . $zdbm[1] . '】';
                } else {
                    $res .= ' ';
                }
                $res .= '无效的诊断编码';
                break;
            }
        }

        if ($res) {
            $basis[] = ['desc' => $res, 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 离院方式等于2或3时，接收医疗机构名称不能为空
     * @param $data
     * @return array
     */
    public function rule1459($data)
    {
        $basis = [];
        if (in_array($data['data']['AEM01C'], [2]) && (empty($data['data']['AEM02']) || $data['data']['AEM02'] == '-' || $data['data']['AEM02'] == '无' || $data['data']['AEM02'] == '没有')) {
            $basis[] = ['desc' => '离院方式是【医嘱转院或医嘱转社区卫生服务机构/乡镇卫生院】接收医疗机构名称不能为空', 'location' => ['user' => ['AEM01C', 'AEM02'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 临床路径选1，完成情况必填
     * @param $data
     * @return array
     */
    public function rule1461($data)
    {
        $LCLJ = !empty($data['other']['LCLJ']) ? $data['other']['LCLJ'] : '';
        $WCQK = !empty($data['other']['WCQK']) ? $data['other']['WCQK'] : '';

        $basis = [];
        if ($LCLJ == 1 && mb_strlen($WCQK) < 1) {
            $basis[] = ['desc' => '临床路径选1，完成情况必填', 'location' => ['user' => ['LCLJ', 'WCQK'], 'zd' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 临床路径选1，变异情况必填
     * @param $data
     * @return array
     */
    public function rule1462($data)
    {
        $LCLJ = !empty($data['other']['LCLJ']) ? $data['other']['LCLJ'] : '';
        $BYQK = !empty($data['other']['BYQK']) ? $data['other']['BYQK'] : '';

        $basis = [];
        if ($LCLJ == 1 && mb_strlen($BYQK) < 1) {
            $basis[] = ['desc' => '临床路径选1，变异情况必填', 'location' => ['user' => ['LCLJ', 'BYQK'], 'zd' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 手术操作名称【有】术者【有】【长度2-40】【不能全是数字】
     * @param $data
     * @return array
     */
    public function rule1463($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $ssList = [];
        foreach ($data['operation'] as $operation) {
            if (!empty($operation['SSCZMC']) && (is_numeric($operation['SZXM']) || mb_strlen($operation['SZXM']) < 2 || mb_strlen($operation['SZXM']) > 40)) {
                $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'OPE_MAN_NAME'];
            }
        }

        if (!empty($ssList)) {
            $basis[] = ['desc' => '术者填写错误', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }
        return $basis;
    }

    /**
     * 不能全部是【4】
     * @param $data
     * @return array
     */
    public function rule1464($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $count1 = 0;
        $count2 = 0;
        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            $count1++;
            if ($diagnosis['RYQK'] == '无') {
                $count2++;
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }
        if ($count1 == $count2) {
            $basis[] = ['desc' => '入院情况不能全是【无】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 证件类别【必填1、2、3、4、5、6、9】
     * @param $data
     * @return array
     */
    public function rule1465($data)
    {
        $basis = [];
        if (empty($data['data']['ZJLB_MC']) && empty($data['data']['SFZJLX'])) {
            $basis[] = ['desc' => '', 'location' => ['user' => ['ZJLB'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * Z37、O80-O84、o26.9应同时存在
     * @param $data
     * @return array
     */
    public function rule1467($data)
    {
        $basis = [];
        if (empty($data['diagnosis']) || $data['data']['AAC11N'] != '产科') {
            return $basis;
        }

        $bl01Service = new ElasticsearchService('bl01_202303');

        // 病程记录
        $must = [
            ['term' => ['JZHM' => $data['data']['MED_REC_ID']]],
            ['term' => ['BLLB' => 294]],
            ['match_phrase' => ['HJNR' => '分娩记录']]
        ];
        $mustNot = ['term' => ['BLZT' => 9]];
        $params = $bl01Service->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNot($mustNot)
            ->trackTotalHits()
            ->getParams();
        $restful = app('es')->search($params);
        $bcjlData = $bl01Service->getDataByEsToArray($restful);

        // 手术记录
        $must = [
            ['term' => ['JZHM' => $data['data']['MED_REC_ID']]],
            ['term' => ['BLLB' => 303]]
        ];
        $should = [
            ['match_phrase' => ['HJNR' => '刨宫产记录']],
            ['match_phrase' => ['HJNR' => '刨宮产记录']]
        ];
        $params = $bl01Service->clearMust()
            ->queryByMustBatch($must)
            ->queryByShouldBatch($should)
            ->queryByMustNot($mustNot)
            ->minimumShouldMatch()
            ->getParams();
        $restful = app('es')->search($params);
        $ssjlData = $bl01Service->getDataByEsToArray($restful);

        // 验证
        if (empty($bcjlData[0]) && empty($ssjlData[0])) {
            return $basis;
        }

        $Z37 = 0;
        $O8 = 0;
        $O26 = 0;
        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if (stripos($ICD10_ID1, 'Z37') !== false) {
                $Z37 = 1;
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            } elseif (stripos($ICD10_ID1, 'O26.9') !== false) {
                $O26 = 1;
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            } else if (stripos($ICD10_ID1, 'O80') !== false || stripos($ICD10_ID1, 'O81') !== false || stripos($ICD10_ID1, 'O82') !== false || stripos($ICD10_ID1, 'O83') !== false || stripos($ICD10_ID1, 'O84') !== false) {
                $O8 = 1;
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }

        $sumScore = $Z37 + $O8 + $O26;
        if (!in_array($sumScore, [0, 3])) {
            $basis[] = ['desc' => '诊断编码【Z37】、【O80-O84】、【O26.9】应同时存在', 'location' => ['user' => ['AAC11N'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 出院科室是【产科】，Z37码段不能重复编码
     * @param $data
     * @return array
     */
    public function rule1468($data)
    {
        $basis = [];
        if ($data['data']['AAC11N'] != '产科') {
            return $basis;
        }

        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if (stripos($ICD10_ID1, 'Z37') !== false) {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }

        if (count($zdList) > 1) {
            $basis[] = ['desc' => '出院科室是【产科】，Z37码段不能重复编码', 'location' => ['user' => ['AAC11N'], 'zd' => $zdList, 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 出院诊断:有【K56.700 肠梗阻】和【K66.002 肠粘连】应合并编码为【K56.500x003 粘连性肠梗阻】
     * @param $data
     * @return array
     */
    public function rule1469($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDBM'] == 'K56.700') {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            } elseif ($diagnosis['ZDBM'] == 'K66.002') {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '【K56.700 肠梗阻】+【K66.002 肠粘连】应合并为【K56.500x003 粘连性肠梗阻】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 出院诊断:【S52.500x001 桡骨远端骨折】和【S52.802 尺骨茎突骨折】应合并编码为【S52.600x002 尺骨茎突骨折伴桡骨远端骨折】
     * @param $data
     * @return array
     */
    public function rule1470($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDBM'] == 'S52.500x001') {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            } elseif ($diagnosis['ZDBM'] == 'S52.802') {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '【S52.500x001 桡骨远端骨折】+【S52.802 尺骨茎突骨折】应合并为【S52.600x002 尺骨茎突骨折伴桡骨远端骨折】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 出院诊断:【I50.101 急性左心衰竭】和【J81.x00 肺水肿】应合并编码为【I50.103 左心衰竭合并肺水肿】
     * @param $data
     * @return array
     */
    public function rule1471($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDBM'] == 'I50.101') {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            } elseif ($diagnosis['ZDBM'] == 'J81.x00') {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '【I50.101 急性左心衰竭】+【J81.x00 肺水肿】应合并为【I50.103 左心衰竭合并肺水肿】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 出院诊断:【M51.202 腰椎间盘突出】和【M54.300 坐骨神经痛】应合并编码为【M51.101+ 腰椎间盘脱出伴坐骨神经痛】
     * @param $data
     * @return array
     */
    public function rule1472($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDBM'] == 'M51.202') {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            } elseif ($diagnosis['ZDBM'] == 'M54.300') {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '【M51.202 腰椎间盘突出】+【M54.300 坐骨神经痛】应合并为【M51.101+ 腰椎间盘脱出伴坐骨神经痛】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 出院诊断:【H02.003 睑内翻】和【H02.004 倒睫】应合并编码为【H02.000 睑内翻和倒睫】
     * @param $data
     * @return array
     */
    public function rule1473($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDBM'] == 'H02.003') {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            } elseif ($diagnosis['ZDBM'] == 'H02.004') {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '【H02.003 睑内翻】+【H02.004 倒睫】应合并为【H02.000 睑内翻和倒睫】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 【食管静脉曲张】或 【胃底静脉曲张】和【肝硬化】应合并为【肝硬化伴食管静脉曲张】或【肝硬化伴胃底静脉曲张】
     * @param $data
     * @return array
     */
    public function rule1474($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDMC'] == '食管静脉曲张' || $diagnosis['ZDMC'] == '胃底静脉曲张') {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            } elseif ($diagnosis['ZDMC'] == '肝硬化') {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '【食管静脉曲张】或【胃底静脉曲张】+【肝硬化】应合并为【肝硬化伴食管静脉曲张】或【肝硬化伴胃底静脉曲张】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 联系人关系不合理
     * @param $data
     * @return array
     */
    public function rule1476($data)
    {
        $basis = [];
        $AAA23C = !empty($data['data']['AAA23C']) ? $data['data']['AAA23C'] : '';
        if ($data['data']['AAA04'] < 20 && in_array($AAA23C, [2, 3])) {
            $basis[] = ['desc' => '年龄小于20岁，联系人关系不能是【子和女】', 'location' => ['user' => ['AAA04', 'AAA23C'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 【出院代码=213（产科），诊断编码：Z37，新生儿出生体重必填】
     * 【出院代码=287（新生儿科、儿童重症医学科），新生儿出生体重必填】
     * 体重区间【100克-9999克】
     * @param $data
     * @return array
     */
    public function rule1477($data)
    {
        $basis = [];

        if (strpos($data['data']['AAC02C_MC'], '新生儿科') !== false || strpos($data['data']['AAC02C_MC'], '儿童重症医学科') !== false) {
            $res = preg_match("/^[1-9][0-9]{2,3}$/", $data['data']['AEN01']);

            if (!$res || substr($data['data']['AEN01'], -2, 1) == 0) {

                $basis[] = ['desc' => '出院科室【新生儿科、儿童重症医学科】天龄【＞0且≤28天】新生儿出生体重必填', 'location' => ['user' => ['AAC11N', 'AEN01'], 'zd' => [], 'ss' => []]];
                return $basis;
            }
        }

        if (strpos($data['data']['AAC02C_MC'], '产科') !== false) {
            $fymcArr = $data['fy'];

            foreach ($fymcArr as $fyInfo) {
                if (stripos($fyInfo['FYMC'], '死胎接生') !== false) {
                    return $basis;
                }
            }


            $re = 0;

            foreach ($fymcArr as $fyInfo) {
                if (stripos($fyInfo['FYMC'], '接生') !== false) {
                    $re = 1;
                    break;
                }
            }


            if (!$re) {
                return $basis;
            }
            //$res = preg_match("/^[1-9][0-9]{2,3}$/", $data['data']['AEN01']);
            $XSERYTZ = '';
            if (isset($data['data']['AEN01'])) {
                preg_match('/\d+/', $data['data']['AEN01'], $matches);
                if (!empty($matches)) {
                    $XSERYTZ = $matches[0];
                }
            }
            if (empty($XSERYTZ) || $XSERYTZ < 100 || $XSERYTZ > 9999) {

                $basis[] = ['desc' => '出院科室【新生儿科、儿童重症医学科】天龄【＞0且≤28天】新生儿出生体重必填', 'location' => ['user' => ['AAC11N', 'AEN01'], 'zd' => [], 'ss' => []]];
                return $basis;
            }
        }


        //$AAA04 = !empty($data['data']['AAA04']) ? $data['data']['AAA04'] : 0; // 年龄
        //$AAA40 = !empty($data['data']['AAA40']) ? $data['data']['AAA40'] : 0; // 天龄


        //        $res = preg_match("/^[1-9][0-9]{2,3}$/", $data['data']['AEN01']);
        //        if ($data['data']['AAC11C'] == 213) {
        //            $zdList = [];
        //            foreach ($data['diagnosis'] as $diagnosis) {
        //                if (stripos($diagnosis['ZDBM'], 'Z37') === 0) {
        //                    $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
        //                }
        //            }
        //
        //            if (!empty($zdList) && !$res) {
        //                $basis[] = ['desc' => '出院科室【产科】诊断编码【Z37】新生儿出生体重必填', 'location' => ['user' => ['AAC11N', 'AEN01'], 'zd' => $zdList, 'ss' => []]];
        //            }
        //        } elseif ($data['data']['AAC11C'] == 287 && $AAA04 < 1 && $AAA40 > 0 && $AAA40 <= 28 && !$res) {
        //            $basis[] = ['desc' => '出院科室【新生儿科、儿童重症医学科】天龄【＞0且≤28天】新生儿出生体重必填', 'location' => ['user' => ['AAC11N', 'AEN01'], 'zd' => [], 'ss' => []]];
        //        }

        return $basis;
    }

    /**
     * 首页收费【麻醉费】，要填写麻醉医师和麻醉方式
     * @param $data
     * @return array
     */
    public function rule1478($data)
    {
        $basis = [];
        // 麻醉费
        if (empty($data['other']['MZF']) || $data['other']['MZF'] <= 0) {
            return $basis;
        }
        $res = false;
        $ssList = [];
        if (!empty($data['operation'])) {
            foreach ($data['operation'] as $operation) {
                if (empty($operation['MZFS'])) {
                    $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
                } else {
                    $res = true;
                    break;
                }
            }
        }

        if (!$res && !empty($ssList)) {
            $basis[] = ['desc' => '首页收费【麻醉费】，要填写麻醉方式', 'location' => ['user' => ['D20X01'], 'ss' => $ssList, 'zd' => []]];
        }
        return $basis;
    }

    /**
     *  出院诊断:【E14】与【E11或 E10】不能同时存在
     * @param $data
     * @return array
     */
    public function rule1479($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }
        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if (stripos($ICD10_ID1, 'E14') === 0) {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            } elseif (stripos($ICD10_ID1, 'E11') === 0 || stripos($ICD10_ID1, 'E10') === 0) {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '诊断编码【E14】与【E11或E10】不能同时存在', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 【I25.103 冠状动脉粥样硬化性心脏病】或【I20.000不稳定型心绞痛】与【I21急性心肌梗死】同时存在，
     * 【I25.103 】或【I20.000】不能作为主要诊断
     * @param $data
     * @return array
     */
    public function rule1481($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }
        $res1 = [];
        $res2 = [];
        $res3 = [];
        $ZYZD = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $ZYZD = $diagnosis['ZDBM'];
                $res3[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if ($ICD10_ID1 == 'I25.103' || $ICD10_ID1 == 'I20.000') {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            } elseif (stripos($ICD10_ID1, 'I21') === 0) {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2 && in_array($ZYZD, ['I25.103', 'I20.000'])) {
            $arr = array_merge($res1, $res2);
            $zdList = array_merge($arr, $res3);
            $basis[] = ['desc' => '【I25.103】或【I20.000】与【I21】同时存在，【I25.103】或【I20.000】不能作为主要诊断', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 出院诊断:【E10.9】与【E10.0-E10.8】不能同时存在
     * 出院诊断:【E11.9】与【E11.0-E11.8】不能同时存在
     * @param $data
     * @return array
     */
    public function rule1482($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $arr1 = ['E10.0', 'E10.1', 'E10.2', 'E10.3', 'E10.4', 'E10.5', 'E10.6', 'E10.7', 'E10.8'];
        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if (stripos($ICD10_ID1, 'E10.9') === 0) {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            } elseif (stripos($ICD10_ID1, 'E10') === 0) {
                foreach ($arr1 as $val) {
                    if (stripos($ICD10_ID1, $val) === 0) {
                        $res2[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
                    }
                }
            }
        }

        $desc = [];
        $zdList = [];
        if ($res1 && $res2) {
            $desc[] = '【E10.9】与【E10.0-E10.8】不能同时存在';
            $zdList = array_merge($res1, $res2);
        }

        $arr2 = ['E11.0', 'E11.1', 'E11.2', 'E11.3', 'E11.4', 'E11.5', 'E11.6', 'E11.7', 'E11.8'];
        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if (stripos($ICD10_ID1, 'E11.9') === 0) {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            } elseif (stripos($ICD10_ID1, 'E11') === 0) {
                foreach ($arr2 as $val) {
                    if (stripos($ICD10_ID1, $val) === 0) {
                        $res2[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
                    }
                }
            }
        }
        if ($res1 && $res2) {
            $desc[] = '诊断编码【E11.9】与【E11.0-E11.8】不能同时存在';
            $zdList = array_merge($zdList, array_merge($res1, $res2));
        }

        if (!empty($zdList)) {
            $basis[] = ['desc' => implode('，', $desc), 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 诊断【I63.9 脑梗死】 + 手术【88.4101脑血管造影】 + 【诊断有 I65】 建议更换为 I63.0 - I63.8
     * @param $data
     * @return array
     */
    public function rule1483($data)
    {
        $basis = [];
        if (empty($data['diagnosis']) || empty($data['operation'])) {
            return $basis;
        }

        $ssList = [];
        foreach ($data['operation'] as $operation) {
            if ($operation['SSCZBM'] == '88.4101') {
                $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }
        if (empty($ssList)) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if (stripos($ICD10_ID1, 'I63.9') === 0) {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            } elseif (stripos($ICD10_ID1, 'I65') === 0) {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '诊断【I63.9 脑梗死】+手术【88.4101 脑血管造影】+诊断【I65】，建议更换为【I63.0-I63.8】', 'location' => ['zd' => $zdList, 'ss' => $ssList, 'user' => []]];
        }

        return $basis;
    }

    /**
     * 诊断【K21.9 胃-食管反流性疾病不伴有食管炎】与【K21.0 胃-食管反流性疾病伴有食管炎】逻辑冲突(伴~不伴)
     * @param $data
     * @return array
     */
    public function rule1484($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if (stripos($ICD10_ID1, 'K21.9') === 0) {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            } elseif (stripos($ICD10_ID1, 'K21.0') === 0) {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '诊断编码【K21.9 胃-食管反流性疾病不伴有食管炎】与【K21.0 胃-食管反流性疾病伴有食管炎】逻辑冲突', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 手术【51.1】 不能 同时有手术【51.64 或 51.84-51.88 或 52.14 或 52.21 或 52.93 h或 52.94 或 52.97 或 52.98】（双向质控）
     * @param $data
     * @return array
     */
    public function rule1485($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $arr = ['51.64', '51.84', '51.85', '51.86', '51.87', '51.88', '52.14', '52.21', '52.93', '52.94', '52.97', '52.98'];
        $res1 = [];
        $res2 = [];
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1, '51.1') === 0) {
                $res1[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            } else {
                foreach ($arr as $val) {
                    if (stripos($ICD9_ID1, $val) === 0) {
                        $res2[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
                    }
                }
            }
        }

        $ssList = [];
        if ($res1 && $res2) {
            $ssList = array_merge($res1, $res2);
        }

        if (!empty($ssList)) {
            $basis[] = ['desc' => '手术编码【51.1】和【51.64】或【51.84-51.88】或【52.14】或【52.21】或【52.93】或【52.94】或【52.97】或【52.98】同时存在', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 有诊断编码【Z51.0】手术操作必须有【92.2-92.3】
     * @param $data
     * @return array
     */
    public function rule1486($data)
    {
        $basis = [];
        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'Z51.0') === 0) {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }
        if (empty($zdList)) {
            return $basis;
        }

        $ssList = [];
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['SSCZBM'], '92.2') === 0 || stripos($operation['SSCZBM'], '92.3') === 0) {
                $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }
        if (empty($ssList)) {
            $basis[] = ['desc' => '有诊断编码【Z51.0】手术操作必须有【92.2-92.3】', 'location' => ['zd' => $zdList, 'ss' => [], 'user' => []]];
        }

        return $basis;
    }

    /**
     * 产科【产科】诊断【O36.4】 其他诊断不应有【Z37 或 O80 - O84】
     * @param $data
     * @return array
     */
    public function rule1487($data)
    {
        $basis = [];
        if ($data['data']['AAC11N'] != '产科') {
            return $basis;
        }

        $zdList1 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'O36.4') === 0) {
                $zdList1[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }
        if (empty($zdList)) {
            return $basis;
        }

        $arr = ['Z37', 'O80', 'O81', 'O82', 'O83', 'O84'];
        $zdList2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            foreach ($arr as $val) {
                if ($diagnosis['ZZPB'] != 1 && stripos($diagnosis['ZDBM'], $val) === 0) {
                    $zdList2[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
                }
            }
        }

        $zdList = [];
        if (!empty($qtZdList)) {
            $zdList = array_merge($zdList1, $zdList2);
        }

        if (empty($zdList)) {
            $basis[] = ['desc' => '出院科室【产科】，诊断编码含【O36.4】，其他诊断不应有【Z37或O80-O84】', 'location' => ['user' => ['AAC11N'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 产科【产科】诊断【O36.4】和 手术【74.1】手术应换成74.9
     * @param $data
     * @return array
     */
    public function rule1488($data)
    {
        $basis = [];
        if ($data['data']['AAC11N'] != '产科') {
            return $basis;
        }

        $zdList = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'O36.4') === 0) {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }
        if (empty($zdList)) {
            return $basis;
        }

        $ssList = [];
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['SSCZBM'], '74.1') === 0) {
                $ssList[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }
        if (!empty($ssList)) {
            $basis[] = ['desc' => '出院科室【产科】，诊断编码有【O36.4】，手术编码有【74.1】应换成【74.9】', 'location' => ['user' => ['AAC11N'], 'zd' => $zdList, 'ss' => $ssList]];
        }

        return $basis;
    }

    /**
     * 主诊断【C00-C76】病理诊断编码必须是 M****\/3
     * @param $data
     * @return array
     */
    public function rule1489($data)
    {
        $basis = [];
        $arr = [
            'C00',
            'C01',
            'C02',
            'C03',
            'C04',
            'C05',
            'C06',
            'C07',
            'C08',
            'C09',
            'C10',
            'C11',
            'C12',
            'C13',
            'C14',
            'C15',
            'C16',
            'C17',
            'C18',
            'C19',
            'C20',
            'C21',
            'C22',
            'C23',
            'C24',
            'C25',
            'C26',
            'C27',
            'C28',
            'C29',
            'C30',
            'C31',
            'C32',
            'C33',
            'C34',
            'C35',
            'C36',
            'C37',
            'C38',
            'C39',
            'C40',
            'C41',
            'C42',
            'C43',
            'C44',
            'C45',
            'C46',
            'C47',
            'C48',
            'C49',
            'C50',
            'C51',
            'C52',
            'C53',
            'C54',
            'C55',
            'C56',
            'C57',
            'C58',
            'C59',
            'C60',
            'C61',
            'C62',
            'C63',
            'C64',
            'C65',
            'C66',
            'C67',
            'C68',
            'C69',
            'C70',
            'C71',
            'C72',
            'C72',
            'C74',
            'C75',
            'C76'
        ];
        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $ICD10_ID1 = $diagnosis['ZDBM'];
                break;
            }
        }
        if (!$ICD10_ID1) {
            return $basis;
        }

        $zdList = [];
        foreach ($arr as $val) {
            if (stripos($ICD10_ID1, $val) === 0) {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
                break;
            }
        }

        $ABF01C = !empty($data['data']['ABF01C']) ? $data['data']['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][3]$/', $ABF01C);
        if (!empty($zdList) && !$res2) {
            $basis[] = ['desc' => '主诊断【C00-C76】病理诊断编码必须是 M****/3', 'location' => ['user' => ['ABF01C'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 主诊断【D00-D09】病理诊断编码必须是 M****\/2
     * @param $data
     * @return array
     */
    public function rule1490($data)
    {
        $basis = [];
        $arr = ['D00', 'D01', 'D02', 'D03', 'D04', 'D05', 'D06', 'D07', 'D08', 'D09'];
        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $ICD10_ID1 = $diagnosis['ZDBM'];
                break;
            }
        }
        if (!$ICD10_ID1) {
            return $basis;
        }

        $zdList = [];
        foreach ($arr as $val) {
            if (stripos($ICD10_ID1, $val) === 0) {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
                break;
            }
        }

        $ABF01C = !empty($data['data']['ABF01C']) ? $data['data']['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][2]$/', $ABF01C);
        if (!empty($zdList) && !$res2) {
            $basis[] = ['desc' => '主诊断【D00-D09】病理诊断编码必须是 M****/2', 'location' => ['user' => ['ABF01C'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 主诊断【D10-D36】病理诊断编码必须是 M****\/0
     * @param $data
     * @return array
     */
    public function rule1491($data)
    {
        $basis = [];
        $arr = [
            'D10',
            'D11',
            'D12',
            'D13',
            'D14',
            'D15',
            'D16',
            'D17',
            'D18',
            'D19',
            'D20',
            'D21',
            'D22',
            'D23',
            'D24',
            'D25',
            'D26',
            'D27',
            'D28',
            'D29',
            'D30',
            'D31',
            'D32',
            'D33',
            'D34',
            'D35',
            'D36'
        ];
        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $ICD10_ID1 = $diagnosis['ZDBM'];
                break;
            }
        }
        if (!$ICD10_ID1) {
            return $basis;
        }

        $zdList = [];
        foreach ($arr as $val) {
            if (stripos($ICD10_ID1, $val) === 0) {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
                break;
            }
        }

        $ABF01C = !empty($data['data']['ABF01C']) ? $data['data']['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][0]$/', $ABF01C);
        if (!empty($zdList) && !$res2) {
            $basis[] = ['desc' => '主诊断【D10-D36】病理诊断编码必须是 M****/0', 'location' => ['user' => ['ABF01C'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 主诊断【D37-D48】病理诊断编码必须是 M****\/1
     * @param $data
     * @return array
     */
    public function rule1492($data)
    {
        $basis = [];
        $arr = ['D37', 'D38', 'D39', 'D40', 'D41', 'D42', 'D43', 'D44', 'D45', 'D46', 'D47', 'D48'];
        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $ICD10_ID1 = $diagnosis['ZDBM'];
                break;
            }
        }
        if (!$ICD10_ID1) {
            return $basis;
        }

        $zdList = [];
        foreach ($arr as $val) {
            if (stripos($ICD10_ID1, $val) === 0) {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
                break;
            }
        }

        $ABF01C = !empty($data['data']['ABF01C']) ? $data['data']['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][1]$/', $ABF01C);
        if (!empty($zdList) && !$res2) {
            $basis[] = ['desc' => '主诊断【D37-D48】病理诊断编码必须是 M****/1', 'location' => ['user' => ['ABF01C'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 主诊断【C77-C79】 病理诊断编码必须是 M****\/6
     * @param $data
     * @return array
     */
    public function rule1493($data)
    {
        $basis = [];
        $arr = ['C77', 'C78', 'C79'];
        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $ICD10_ID1 = $diagnosis['ZDBM'];
                break;
            }
        }
        if (!$ICD10_ID1) {
            return $basis;
        }

        $zdList = [];
        foreach ($arr as $val) {
            if (stripos($ICD10_ID1, $val) === 0) {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
                break;
            }
        }

        $ABF01C = !empty($data['data']['ABF01C']) ? $data['data']['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][6]$/', $ABF01C);
        if (!empty($zdList) && !$res2) {
            $basis[] = ['desc' => '主诊断【C77-C79】 病理诊断编码必须是 M****/6', 'location' => ['user' => ['ABF01C'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 主诊断【C80-C97】病理诊断编码必须是 M****\/3
     * @param $data
     * @return array
     */
    public function rule1494($data)
    {
        $basis = [];
        $arr = [
            'C80',
            'C81',
            'C82',
            'C83',
            'C84',
            'C85',
            'C86',
            'C87',
            'C88',
            'C89',
            'C90',
            'C91',
            'C92',
            'C93',
            'C94',
            'C95',
            'C96',
            'C99'
        ];
        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $ICD10_ID1 = $diagnosis['ZDBM'];
                break;
            }
        }
        if (!$ICD10_ID1) {
            return $basis;
        }

        $zdList = [];
        foreach ($arr as $val) {
            if (stripos($ICD10_ID1, $val) === 0) {
                $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
                break;
            }
        }

        $ABF01C = !empty($data['data']['ABF01C']) ? $data['data']['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][3]$/', $ABF01C);
        if (!empty($zdList) && !$res2) {
            $basis[] = ['desc' => '主诊断【C80-C97】病理诊断编码必须是 M****/3', 'location' => ['user' => ['ABF01C'], 'zd' => $zdList, 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 【68.1200x001 宫腔镜检查】不能和【宫腔镜其他诊断】同时存在
     * @param $data
     * @return array
     */
    public function rule1495($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['operation'] as $operation) {
            //如果手术名称中包含宫腔镜检查，其余手术如果包含“宫腔镜”
            if (stripos($operation['SSCZMC'], '宫腔镜检查') !== false) {
                $res1[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            } elseif (stripos($operation['SSCZMC'], '宫腔镜') !== false) {
                $res2[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }

        if ($res1 && $res2) {
            $ssList = array_merge($res1, $res2);
            $basis[] = ['desc' => '【宫腔镜检查】和【宫腔镜其他手术】不能同时存在', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }
        return $basis;
    }

    /**
     * 【54.2100 腹腔镜检查】不能和【腹腔镜手术】同时存在
     * @param $data
     * @return array
     */
    public function rule1496($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['operation'] as $operation) {
            //如果手术名称中包含腹腔镜检查，其余手术如果包含“腹腔镜”
            if (stripos($operation['SSCZMC'], '腹腔镜检查') !== false) {
                $res1[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            } elseif (stripos($operation['SSCZMC'], '腹腔镜') !== false) {
                $res2[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }
        if ($res1 && $res2) {
            $ssList = array_merge($res1, $res2);
            $basis[] = ['desc' => '手术【腹腔镜检查】和【腹腔镜其他手术】不能同时存在', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }
        return $basis;
    }

    /**
     * 70.78另编码使用生物学物质（70.94）或人造物质（70.95）
     * @param $data
     * @return array
     */
    public function rule1497($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1, '70.78') === 0) {
                $res1[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            } elseif (stripos($ICD9_ID1, '70.94') === 0 || stripos($ICD9_ID1, '70.95') === 0) {
                $res2[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }

        if (empty($res1)) {
            return $basis;
        }
        if (empty($res2)) {
            $basis[] = ['desc' => '手术编码【70.78】需同时编码【70.94 生物学物质】或【70.95 人造物质】', 'location' => ['ss' => $res1, 'user' => [], 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 手术【81.0或81.3】手术中要有【81.62-81.64】融合椎骨的总数
     * @param $data
     * @return array
     */
    public function rule1498($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1, '81.0') === 0 || stripos($ICD9_ID1, '81.3') === 0) {
                $res1[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            } elseif (stripos($ICD9_ID1, '81.62') === 0 || stripos($ICD9_ID1, '81.63') === 0 || stripos($ICD9_ID1, '81.64') === 0) {
                $res2[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }

        if (empty($res1)) {
            return $basis;
        }
        if (empty($res2)) {
            $basis[] = ['desc' => '手术【81.0】或【81.3】需同时编码【81.62-81.64 融合椎骨的总数】', 'location' => ['ss' => $res1, 'user' => [], 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 手术【81.51-81.53髋关节置换术】需同时编码【00.74-00.78】任何明确类型轴面
     * @param $data
     * @return array
     */
    public function rule1499($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1, '81.51') === 0 || stripos($ICD9_ID1, '81.52') === 0 || stripos($ICD9_ID1, '81.53') === 0) {
                $res1[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            } elseif (stripos($ICD9_ID1, '00.74') === 0 || stripos($ICD9_ID1, '00.75') === 0 || stripos($ICD9_ID1, '00.76') === 0 || stripos($ICD9_ID1, '00.77') === 0 || stripos($ICD9_ID1, '00.78') === 0) {
                $res2[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }

        if (empty($res1)) {
            return $basis;
        }

        if (empty($res2)) {
            $basis[] = ['desc' => '手术【81.51-81.53 髋关节置换术】需同时编码【00.74-00.78 任何明确类型轴面】', 'location' => ['ss' => $res1, 'user' => [], 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 手术【39.61】不能有【50.92或39.65或39.95或39.66】
     * @param $data
     * @return array
     */
    public function rule1500($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $arr = ['50.92', '39.65', '39.95', '39.66'];
        $res1 = [];
        $res2 = [];
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1, '39.61') === 0) {
                $res1[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            } else {
                foreach ($arr as $value) {
                    if (stripos($ICD9_ID1, $value) === 0) {
                        $res2[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
                    }
                }
            }
        }

        if ($res1 && $res2) {
            $ssList = array_merge($res1, $res2);
            $basis[] = ['desc' => '手术【39.61】与【50.92或39.65或39.95或39.66】不能同时存在', 'location' => ['ss' => $ssList, 'user' => [], 'zd' => []]];
        }
        return $basis;
    }

    /**
     * 诊断【T20-T30】烧伤，同时需要编【T31或T32】面积
     * @param $data
     * @return array
     */
    public function rule1501($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $arr = ['T20', 'T21', 'T22', 'T23', 'T24', 'T25', 'T26', 'T27', 'T28', 'T29', 'T30'];
        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if (stripos($ICD10_ID1, 'T31') === 0 || stripos($ICD10_ID1, 'T32') === 0) {
                $res2 = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            } else {
                foreach ($arr as $val) {
                    if (stripos($ICD10_ID1, $val) === 0) {
                        $res1 = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
                    }
                }
            }
        }

        if (empty($res1)) {
            return $basis;
        }
        if (empty($res2)) {
            $basis[] = ['desc' => '诊断【T20-T30 烧伤】需同时编码【T31或T32 面积】', 'location' => ['zd' => $res1, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 38.45另编码心肺搭桥[体外循环]（39.61）
     * @param $data
     * @return array
     */
    public function rule1502($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1, '38.45') === 0) {
                $res1[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            } elseif (stripos($ICD9_ID1, '39.61') === 0) {
                $res2[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }

        if (empty($res1)) {
            return $basis;
        }
        if (empty($res2)) {
            $basis[] = ['desc' => '手术【38.45】另编码【39.61 心肺搭桥[体外循环]】', 'location' => ['ss' => $res1, 'user' => [], 'zd' => []]];
        }
        return $basis;
    }

    /**
     * 手术【39.90或36.06或36.07或00.55或00.63或00.64或00.65】，手术中需要有【00.45-00.48 + 治疗血管的数量00.40-00.43】
     * @param $data
     * @return array
     */
    public function rule1503($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $arr1 = ['39.90', '36.06', '36.07', '00.55', '00.63', '00.64', '00.65'];
        $res1 = [];
        $res2 = [];
        $res3 = [];
        $ssbmList = [];
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            foreach ($arr1 as $val) {
                if (stripos($ICD9_ID1, $val) === 0) {
                    $ssbmList[] = $operation['SSCZBM'];
                    $res1[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
                }
            }

            if (stripos($ICD9_ID1, '00.45') === 0 || stripos($ICD9_ID1, '00.46') === 0 || stripos($ICD9_ID1, '00.47') === 0 || stripos($ICD9_ID1, '00.48') === 0) {
                $res2[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            } elseif (stripos($ICD9_ID1, '00.40') === 0 || stripos($ICD9_ID1, '00.41') === 0 || stripos($ICD9_ID1, '00.42') === 0 || stripos($ICD9_ID1, '00.43') === 0) {
                $res3[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }

        if (empty($res1)) {
            return $basis;
        }
        $str = '';
        if (empty($res2) && empty($res3)) {
            $str .= '手术中需要有【00.45-00.48】+【治疗血管的数量 00.40-00.43】';
        } elseif (empty($res2)) {
            $str .= '手术中需要有【00.45-00.48】';
        } elseif (empty($res3)) {
            $str .= '手术中需要有【治疗血管的数量 00.40-00.43】';
        }
        if (!empty($str)) {
            $basis[] = ['desc' => '手术编码【' . implode('、', $ssbmList) . '】' . $str, 'location' => ['ss' => $res1, 'user' => [], 'zd' => []]];
        }
        return $basis;
    }

    /**
     * 手术【39.74】手术中需要有治疗血管的数量【00.40-00.43】
     * @param $data
     * @return array
     */
    public function rule1504($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1, '39.74') === 0) {
                $res1 = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            } elseif (stripos($ICD9_ID1, '00.40') === 0 || stripos($ICD9_ID1, '00.41') === 0 || stripos($ICD9_ID1, '00.42') === 0 || stripos($ICD9_ID1, '00.43') === 0) {
                $res2 = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }

        if (empty($res1)) {
            return $basis;
        }
        if (empty($res2)) {
            $basis[] = ['desc' => '有手术编码【39.74】，手术中需要有【00.40-00.43 治疗血管的数量】', 'location' => ['ss' => $res1, 'user' => [], 'zd' => []]];
        }
        return $basis;
    }

    /**
     * 收费【阿替普酶】手术要有【99.1005 脑动脉血栓溶解剂灌注】
     * @param $data
     * @return array
     */
    public function rule1505($data)
    {
        $basis = [];
        if (empty($data['fy'])) {
            return $basis;
        }

        $FYMC_ARR = array_column($data['fy'], 'FYMC');
        $res = false;
        foreach ($FYMC_ARR as $value) {
            if (stripos($value, '阿替普酶') !== false) {
                $res = true;
                break;
            }
        }
        if (!$res) {
            return $basis;
        }

        $res1 = [];
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            // 截取前五位字符，判断是否等于'99.100'
            if (substr($ICD9_ID1, 0, 5) === '99.10') {
                $res1 = [
                    'OPE_ORDER' => $operation['SSSX'],
                    'field' => 'ICD9_ID1'
                ];
            }
        }

        if (empty($res1)) {
            $basis[] = ['desc' => '收费中含【阿替普酶】', 'location' => ['ss' => [['OPE_ORDER' => 1000, 'field' => '']], 'user' => [], 'zd' => []]];
        }

        return $basis;
    }

    /**
     * 手术中 03.90另编码输注泵的置入（86.06）
     * @param $data
     * @return array
     */
    public function rule1506($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1, '03.90') === 0) {
                $res1[] = 1;
            } elseif (stripos($ICD9_ID1, '86.06') === 0) {
                $res2[] = 1;
            }
        }

        if (empty($res1)) {
            return $basis;
        }
        if (empty($res2)) {
            $basis[] = ['desc' => '手术【03.90】，应另编码【86.06 输注泵的置入】', 'location' => ['ss' => $res1, 'user' => [], 'zd' => []]];
        }
        return $basis;
    }

    /**
     * 37.8有导线起搏器另编导线置入、置换、去除和修复（37.70-37.77）
     * @param $data
     * @return array
     */
    public function rule1507($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $arr = ['37.70', '37.71', '37.72', '37.73', '37.74', '37.75', '37.76', '37.77'];
        $res1 = [];
        $res2 = [];
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1, '37.8') === 0) {
                $res1[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            } else {
                foreach ($arr as $val) {
                    if (stripos($ICD9_ID1, $val) === 0) {
                        $res2[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
                    }
                }
            }
        }

        if (empty($res1)) {
            return $basis;
        }
        if (empty($res2)) {
            $basis[] = ['desc' => '手术【37.8 有导线起搏器】另编【37.70-37.77 导线置入、置换、去除和修复】', 'location' => ['ss' => $res1, 'user' => [], 'zd' => []]];
        }
        return $basis;
    }

    /**
     * 手术名称有【***粘连松解术】 诊断名称要有【 ***粘连】
     * @param $data
     * @return array
     */
    public function rule1508($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        $res1 = [];
        foreach ($data['operation'] as $operation) {
            if (preg_match('/^(.*)粘连松解术$/', $operation['SSCZMC'])) {
                $res1[] = ['OPE_ORDER' => $operation['SSSX'], 'field' => 'ICD9_ID1'];
            }
        }
        if (!$res1) {
            return $basis;
        }

        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (preg_match('/^(.*)粘连$/', $diagnosis['ZDMC'])) {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }
        if (!$res2) {
            $basis[] = ['desc' => '手术名称有【***粘连松解术】，诊断名称要有【***粘连】', 'location' => ['ss' => $res1, 'zd' => [['ZZPB' => '', 'DIA_ORDER' => 1000, 'field' => '']], 'user' => []]];
        }

        return $basis;
    }

    /**
     * 诊断【N20.0和N20.1】需要合并到N20.2
     * @param $data
     * @return array
     */
    public function rule1509($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'N20.0') === 0) {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            } elseif (stripos($diagnosis['ZDBM'], 'N20.1') === 0) {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '诊断编码【N20.0和N20.1】需合并为【N20.2】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 诊断【N20和N13.3】需要合并到N13.2
     * @param $data
     * @return array
     */
    public function rule1510($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'N20') === 0) {
                $res1[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            } elseif (stripos($diagnosis['ZDBM'], 'N13.3') === 0) {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '诊断编码【N20和N13.3】需合并为【N13.2】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 诊断【N13.0-N13.5和 N15.9】需要合并到N13.6
     * @param $data
     * @return array
     */
    public function rule1511($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $arr = ['N13.0', 'N13.1', 'N13.2', 'N13.3', 'N13.4', 'N13.5'];
        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'N15.9') === 0) {
                $res2[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
            } else {
                foreach ($arr as $val) {
                    if (stripos($diagnosis['ZDBM'], $val) === 0) {
                        $res1[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
                    }
                }
            }
        }

        if ($res1 && $res2) {
            $zdList = array_merge($res1, $res2);
            $basis[] = ['desc' => '诊断编码【N13.0-N13.5】和【N15.9】需合并为【N13.6】', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 手术时间不能早于入院时间且不能晚于出院时间
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1513($data)
    {

        $AAB01 = !empty($data['data']["AAB01"])
            ? date("Y-m-d", strtotime($data['data']["AAB01"]))
            : "";
        $AAC01 = !empty($data['data']["AAC01"])
            ? date("Y-m-d", strtotime($data['data']["AAC01"]))
            : "";

        $res = false;

        if (empty($AAB01) || empty($AAC01)) {
            return false;
        }
        $rulemap9033 = RuleWordMap::query()->where('id', '=', 9033)->value('keyword');
        if (!empty($rulemap9033)) {
            if (strpos($rulemap9033, ',') !== false) {
                $rulemap9033 = explode(',', $rulemap9033);
            } else {
                $rulemap9033 = [$rulemap9033];
            }
            $zyh = $data['data']['MED_REC_ID'];
            $ryjl = EMR_BL_BL01::query()->where('JZHM', '=', (string)$zyh)->where('BLLB', '=', 292)->get()->toArray();
            if (!empty($ryjl)) {
                $isbh = false;
                $blbh = $ryjl[0]['BLBH'];
                $hjnr = EMR_BL_BLXG::query()->where('BLBH', '=', $blbh)->value('HJNR');
                if (!empty($hjnr)) {
                    foreach ($rulemap9033 as $item) {
                        if (strpos($hjnr, $item) !== false) {
                            $isbh = true;
                            break;
                        }
                    }
                }
                if ($isbh) {
                    return false;
                }
            }
        }

        if (empty($data['operation'])) {
            return false;
        }

        foreach ($data['operation'] as $operation) {
            if (empty($operation['SSCZBM']) || empty($operation['SSCZMC'])) {
                continue;
            }
            $ssrq = $operation['SSCZRQ'] ?? "";
            if (empty($ssrq)) {
                continue;
            }
            $ssrq = date('Y-m-d', strtotime($ssrq));
            if ($AAB01 > $ssrq || $AAC01 < $ssrq) {
                $res = true;
                break;
            }
        }


        return $res;
    }

    /**
     * 诊断信息除最后一条外，诊断编码或名称不能为空
     * @param $data
     * @return array
     */
    public function rule1514($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }
        $zdList = [];
        foreach ($data['diagnosis'] as $key => $diagnosis) {
            if (empty($diagnosis['ZDBM']) || empty($diagnosis['ZDMC'])) {
                if (!empty($data['diagnosis'][$key + 1])) {
                    $zdList[] = ['ZZPB' => $diagnosis['ZZPB'],  'field' => 'ICD10_ID1'];
                }
            }
        }

        if (!empty($zdList)) {
            $basis[] = ['desc' => '诊断编码或名称不能为空', 'location' => ['zd' => $zdList, 'user' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 离院方式
     * @param $data
     * @return array
     */
    public function rule1515($data)
    {
        $basis = [];
        if (empty($data['yz'])) {
            return $basis;
        }

        $arr = array_unique(array_column($data['yz'], 'YDYZLB'));
        if (in_array(305, $arr) && $data['data']['AEM01C'] != 5) {
            $basis[] = ['desc' => '医嘱中含【死亡】，离院方式不是【死亡】', 'location' => ['user' => ['AEM01C'], 'zd' => [], 'ss' => []]];
        }

        return $basis;
    }

    /**
     * 费用或者医嘱单有“阿替普酶”或者“急性缺血性脑卒中静脉溶栓治疗”，病案首页的手术操作要有“脑动脉血栓溶解剂灌注”
     * @param $data
     * @return array
     */
    public function rule1516($data)
    {
        $basis = [];
        $res = false;
        $name1 = "阿替普酶";
        $name2 = "急性缺血性脑卒中静脉溶栓治疗";

        // 费用
        if (!$res && !empty($data['fy'])) {
            $fymcList = array_column($data['fy'], 'FYMC');
            $fymcStr = implode(',', $fymcList);
            if (
                stripos($fymcStr, $name1) !== false ||
                stripos($fymcStr, $name2) !== false
            ) {
                $res = true;
            }
        }
        if (!$res && !empty($data["yz"])) {
            $yzmcList = array_column($data["yz"], "YZMC");
            $yzmcStr = implode(",", $yzmcList);
            if (
                stripos($yzmcStr, $name1) !== false ||
                stripos($yzmcStr, $name2) !== false
            ) {
                $res = true;
            }
        }

        // 医嘱和费用都不存在，不质控
        if (!$res) {
            return $basis;
        }

        $res = false;
        foreach ($data['operation'] as $operation) {
            /* if (stripos($operation['SSCZMC'], '脑动脉血栓溶解剂灌注') !== false) {
                $res = true;
            } */
            //手术编码前五位99.10
            $ssbm = $operation['SSCZBM'];
            $ssbmstr = substr($ssbm, 0, 5);
            if ($ssbmstr == '99.10') {
                $res = true;
                break;
            }
        }

        if ($res) {
            return $basis;
        }

        $basis[] = ['desc' => '费用有【急性缺血性脑卒中静脉溶栓治疗】', 'location' => ['user' => [], 'zd' => [], 'ss' => [['OPE_ORDER' => 1000, 'field' => '']]]];

        return $basis;
    }


    /**
     * 住院天数等于出入院天数
     * @param $data
     * @return array
     */
    public function rule1519($data)
    {
        $AAB01 = $data['data']['AAB01'] ? date('Y-m-d', strtotime($data['data']['AAB01'])) : '';
        $AAC01 = $data['data']['AAC01'] ? date('Y-m-d', strtotime($data['data']['AAC01'])) : '';

        // 住院天数
        $AAC04 = !empty($data['data']['AAC04']) ? $data['data']['AAC04'] : 0;

        //        $CYSJ = substr(str_replace('月', '-', str_replace('年','-', $data['CYSJ'])), 0, 10);
        //        $RYSJ = substr(str_replace('月', '-', str_replace('年','-', $data['RYSJ'])), 0, 10);

        $diff = strtotime($AAC01) - strtotime($AAB01);

        if ($diff) {
            $d = abs(round($diff / 86400));
        } else {
            $d = 1;
        }


        $basis = [];
        if ($AAC04 != $d) {
            $basis[] = ['desc' => '住院天数不等于出入院天数-实际为' . $d . '天', 'location' => ['user' => ['AAC04', 'TJHLTS', 'YJHLTS', 'EJHLTS', 'SJHLTS'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }


    /**
     * 诊断名称 含【盆腔积液】，患者性别不为男性
     * @param $data
     * @return array
     */
    public function rule1520($data)
    {

        $ICD10_NAME = '';
        foreach ($data['diagnosis'] as $diagnosis) {

            if (!empty($diagnosis['ZDMC']) && false !== strpos($diagnosis['ZDMC'], '盆腔积液') && $data['data']['AAA02C'] == 1) {
                $ICD10_NAME = $diagnosis['ZDMC'];
                break;
            }
        }

        $basis = [];
        if (!empty($ICD10_NAME)) {
            $basis[] = ['desc' => $ICD10_NAME, 'location' => ['zd' => [['field' => 'ICD10_ID1']], 'user' => [], 'ss' => []]];
        }

        return $basis;
    }


    /**
     * 付费方式不能为空
     * @param $data
     * @return array
     */
    public function rule1521($data)
    {

        $basis = [];
        if (empty($data['data']['AAA26C'])) {
            $basis[] = ['desc' => '', 'location' => ['zd' => [['ZZPB' => 1, 'DIA_ORDER' => 1, 'field' => 'ICD10_NAME']], 'user' => [], 'ss' => []]];
        }
        return $basis;
    }


    //质控医师不能为空
    public function rule1522($data)
    {
        $basis = [];

        if (empty($data['data']['AED02'])) {
            $basis[] = ['desc' => '质控医师不能为空', 'location' => ['user' => ['AED02'], 'zd' => [], 'ss' => []]];
        }

        return $basis;
    }


    //质控护士 不能为空
    public function rule1523($data)
    {

        $basis = [];
        if (empty($data['data']['AED03'])) {
            $basis[] = ['desc' => '质控护士不能为空', 'location' => ['user' => ['AED02'], 'zd' => [], 'ss' => []]];
        }

        return $basis;
    }

    //质控医师  只能填医师姓名
    public function rule1524($data)
    {
        return "";
        $basis = [];

        $info = (new HomeSzService())->GY_YGDM($data['data']['AED02']);
        if (empty($info)) {
            $basis[] = ['desc' => '质控医师姓名有误', 'location' => ['user' => ['AED02'], 'zd' => [], 'ss' => []]];
        } else {
            if (!empty($info[0]['YGJB']) && !in_array($info[0]['YGJB'], [1, 2, 3, 6, 7])) {
                $basis[] = ['desc' => '质控医师姓名有误', 'location' => ['user' => ['AED02'], 'zd' => [], 'ss' => []]];
            }
        }

        return $basis;
    }

    //质控护士  只能填护士姓名
    public function rule1525($data)
    {
        return "";
        $basis = [];

        $info = (new HomeSzService())->GY_YGDM($data['data']['AED03']);
        if (empty($info)) {
            $basis[] = ['desc' => '质控护士姓名有误', 'location' => ['user' => ['AED03'], 'zd' => [], 'ss' => []]];
        } else {
            if (!empty($info[0]['YGJB']) && !in_array($info[0]['YGJB'], [4, 5, 8, 9, 10, 20])) {
                $basis[] = ['desc' => '质控护士姓名有误', 'location' => ['user' => ['AED03'], 'zd' => [], 'ss' => []]];
            }
        }

        return $basis;
    }


    /**
     * 新增：新生儿入院体重(克)填写  ，天龄（不足1周岁）应≤28天
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1526($data)
    {
        $basis = [];
        if (empty($data['data']['AAA42'])) {
            return $basis;
        }


        if ($data['data']['AAC11C'] == 287 && (empty($data['data']['AAA40']) || $data['data']['AAA40'] > 28)) {
            $basis[] = ['desc' => '出院科室【' . $data['data']['AAC11N'] . '】，天龄（不足1周岁）应≤28天', 'location' => ['user' => ['AAA40', 'AAC11N'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }


    /**
     * 新增：【手术治疗费】有 ，手术操作栏不能为空
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1527($data)
    {

        $basis = [];

        $ssList = [];
        if (!empty($data['data']['D20X01']) && $data['data']['D20X01'] > 0) {
            if (empty($data['operation'])) {
                $basis[] = ['desc' => '有【手术费】 ，手术操作栏不能为空', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
            } else {
                $isss = false;
                foreach ($data['operation'] as $operation) {
                    if (!empty($operation['SSCZMC']) && $operation['SSCZMC'] != '-') {
                        $isss = true;
                        break;
                    }
                }
                if (!$isss) {
                    $basis[] = ['desc' => '有【手术费】 ，手术操作栏不能为空', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                }
            }
        }

        return $basis;
    }


    /**
     * 新增：有【血费】，血型不能填“5.不详”或“6.未查”
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1528($data)
    {
        $basis = [];

        if (!empty($data['other']['XF']) && !in_array($data['data']['AEG01C'], [1, 2, 3, 4])) {
            $basis[] = ['desc' => '血型不能填“不详”或“未查”', 'location' => ['user' => ['AEG01C'], 'zd' => [], 'ss' => []]];
        }

        return $basis;
    }


    /**
     * 新增：收费名称有【经肠镜特殊治疗】，手术操作名称未填
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1529($data)
    {
        $basis = [];

        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return $basis;
        }

        $res1 = false;
        foreach ($fymcArr as $fyInfo) {
            if (stripos($fyInfo['FYMC'], '经肠镜特殊治疗') !== false) {
                $res1 = true;
                break;
            }
        }

        if (!$res1) {
            return $basis;
        }

        $mc = '内镜下十二指肠病损切除术或破坏术
                内镜下十二指肠病损切除术
                内镜下十二指肠病损氩离子凝固治疗术
                内镜下十二指肠病损光动力治疗（PDT)
                内镜下十二指肠黏膜下剥离术(ESD)
                内镜下十二指肠黏膜切除术(EMR)
                内镜下十二指肠病损射频消融术
                内镜下经黏膜下隧道十二指肠病损切除术(STER)
                内镜下小肠黏膜切除术(EMR)
                内镜下小肠黏膜下剥离术(ESD)
                内镜下经黏膜下隧道小肠病损切除术(STER)
                内镜下小肠病损切除术
                内镜下空肠病损氩气刀治疗术（APC)
                内镜下回肠病损氩气刀治疗术（APC)
                内镜下大肠息肉切除术
                内镜下结肠息肉消融术
                内镜下乙状结肠息肉切除术
                内镜下大肠其他病损或组织破坏术
                内镜下结肠黏膜下剥离术(ESD)
                内镜下结肠黏膜切除术(EMR)
                内镜下经黏膜下隧道结肠病损切除术(STER)
                内镜下结肠病损氩气刀治疗术（APC)
                内镜下乙状结肠病损切除术
                内镜下结肠病损切除术
                内镜下盲肠病损切除术
                内镜下直肠病损氩离子凝固术
                内镜下直肠病损切除术
                内镜下直肠黏膜下剥离术(ESD)
                内镜下直肠黏膜切除术(EMR)
                内镜下直肠病损光动力治疗术（PDT)
                直肠[内镜的]息肉切除术
                内镜下直肠息肉氩离子凝固术（APC)
                纤维结肠镜下结肠息肉切除术
                结肠镜下结肠病损电凝术
                直肠-乙状结肠镜下直肠病损电切术
                直肠-乙状结肠镜下直肠息肉切除术
                内镜下内痔套扎治疗
                内痔硬化剂注射治疗';

        $res = false;

        foreach ($data['operation'] as $operation) {
            if (stripos($mc, $operation['SSCZMC']) !== false) {
                $res = true;
                break;
            }
        }

        if ($res) {
            return $basis;
        }

        $basis[] = ['desc' => '手术操作名称未填”', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];

        return $basis;
    }


    /**
     * 新增：新增：有【手术操作名称】，收费名称中无【经肠镜特殊治疗】，请核实
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1530($data)
    {
        $basis = [];

        $mc = '内镜下十二指肠病损切除术或破坏术
                内镜下十二指肠病损切除术
                内镜下十二指肠病损氩离子凝固治疗术
                内镜下十二指肠病损光动力治疗（PDT)
                内镜下十二指肠黏膜下剥离术(ESD)
                内镜下十二指肠黏膜切除术(EMR)
                内镜下十二指肠病损射频消融术
                内镜下经黏膜下隧道十二指肠病损切除术(STER)
                内镜下小肠黏膜切除术(EMR)
                内镜下小肠黏膜下剥离术(ESD)
                内镜下经黏膜下隧道小肠病损切除术(STER)
                内镜下小肠病损切除术
                内镜下空肠病损氩气刀治疗术（APC)
                内镜下回肠病损氩气刀治疗术（APC)
                内镜下大肠息肉切除术
                内镜下结肠息肉消融术
                内镜下乙状结肠息肉切除术
                内镜下大肠其他病损或组织破坏术
                内镜下结肠黏膜下剥离术(ESD)
                内镜下结肠黏膜切除术(EMR)
                内镜下经黏膜下隧道结肠病损切除术(STER)
                内镜下结肠病损氩气刀治疗术（APC)
                内镜下乙状结肠病损切除术
                内镜下结肠病损切除术
                内镜下盲肠病损切除术
                内镜下直肠病损氩离子凝固术
                内镜下直肠病损切除术
                内镜下直肠黏膜下剥离术(ESD)
                内镜下直肠黏膜切除术(EMR)
                内镜下直肠病损光动力治疗术（PDT)
                直肠[内镜的]息肉切除术
                内镜下直肠息肉氩离子凝固术（APC)
                纤维结肠镜下结肠息肉切除术
                结肠镜下结肠病损电凝术
                直肠-乙状结肠镜下直肠病损电切术
                直肠-乙状结肠镜下直肠息肉切除术';

        $res = false;

        foreach ($data['operation'] as $operation) {
            if (stripos($mc, $operation['SSCZMC']) !== false) {
                $res = true;
                break;
            }
        }

        if (!$res) {
            return $basis;
        }


        $fymcArr = $data['fy'];
        $res1 = false;
        foreach ($fymcArr as $fyInfo) {
            if (stripos($fyInfo['FYMC'], '经肠镜特殊治疗') !== false) {
                $res1 = true;
                break;
            }
        }

        if (!$res1) {
            $basis[] = ['desc' => '收费名称中无【经肠镜特殊治疗】，请核实。”', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
            return $basis;
        }

        return $basis;
    }

    /**
     * @param $data
     * @return array
     * 入院情况
     */
    public function rule1540($data)
    {
        $basis = [];

        foreach ($data['diagnosis'] as $diagnosis) {
            if (!empty($diagnosis['ZDMC']) && empty($diagnosis['RYQK'])) {
                $basis[] = ['desc' => '有诊断时，入院情况不能为空，请核实。', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                break;
            }
        }

        return $basis;
    }

    /**
     * 出院情况
     */
    public function rule1541($data)
    {
        $basis = [];

        foreach ($data['diagnosis'] as $diagnosis) {
            if (!empty($diagnosis['ZDMC']) && empty($diagnosis['CYQK'])) {
                $basis[] = ['desc' => '有诊断时，出院情况不能为空，请核实。', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                break;
            }
        }

        return $basis;
    }

    /**
     * 心功能NYHA分级和Killip分级
     */
    public function rule1542($data)
    {
        $basis = [];

        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                if (!empty($diagnosis['ZDMC'])) {
                    /* if ($diagnosis['ZDBM'] == 'I50.900x002') {
                        $basis[] = ['desc' => 'I50.900x002   心功能不全, 不能作为主诊断编码', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                        break;
                    } else if ($diagnosis['ZDBM'] == 'I50.900x007') {
                        $basis[] = ['desc' => 'I50.900x007  心功能II级(NYHA分级), 不能作为主诊断编码', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                        break;
                    } else if ($diagnosis['ZDBM'] == 'I50.900x008') {
                        $basis[] = ['desc' => 'I50.900x008  心功能III级(NYHA分级), 不能作为主诊断编码', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                        break;
                    } else if ($diagnosis['ZDBM'] == 'I50.900x009') {
                        $basis[] = ['desc' => 'I50.900x009  心功能II - III级(NYHA分级), 不能作为主诊断编码', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                        break;
                    } else if ($diagnosis['ZDBM'] == 'I50.900x010') {
                        $basis[] = ['desc' => 'I50.900x010  心功能IV级(NYHA分级), 不能作为主诊断编码', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                        break;
                    } else if ($diagnosis['ZDBM'] == 'I50.900x014') {
                        $basis[] = ['desc' => 'I50.900x014  illip II分级, 不能作为主诊断编码', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                        break;
                    } */
                    if (stripos($diagnosis['ZDMC'], 'killip') !== false) {
                        $basis[] = ['desc' => '诊断名称【' . $diagnosis['ZDMC'] . '】不能作为主要诊断', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                        break;
                    } else if (stripos($diagnosis['ZDMC'], 'Killip') !== false) {
                        $basis[] = ['desc' => '诊断名称【' . $diagnosis['ZDMC'] . '】不能作为主要诊断', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                        break;
                    } else if (stripos($diagnosis['ZDMC'], 'NYHA') !== false) {
                        $basis[] = ['desc' => '诊断名称【' . $diagnosis['ZDMC'] . '】不能作为主要诊断', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                        break;
                    }
                }
            }
        }

        return $basis;
    }

    /**
     * M801
     * 如病理诊断为M801/3-M808/3时，其主要诊断应为C44或C46.0或C51或C52或C53或C60或C63.2或C13或C14或C15或C34或C67
     */
    public function rule1543($data)
    {
        $basis = [];
        $ABF01C = !empty($data['data']['ABF01C']) ? $data['data']['ABF01C'] : '';

        if (!empty($ABF01C)) {
            $substring = substr($ABF01C, 0, 4);
            $substring2 = substr($ABF01C, -2);
            $subArray = ['M801', 'M802', 'M803', 'M804', 'M805', 'M806', 'M807', 'M808'];
            $subArray2 = ['C44', 'C46.0', 'C51', 'C52', 'C53', 'C60', 'C63.2', 'C13', 'C14', 'C15', 'C34', 'C67'];

            if (in_array($substring, $subArray) && $substring2 == '/3') {
                foreach ($data['diagnosis'] as $diagnosis) {
                    if ($diagnosis['ZZPB'] == 1) {
                        Log::info('zdbm====' . $diagnosis['ZDBM']);
                        $isMatch = false;
                        foreach ($subArray2 as $sub) {
                            if (strpos($diagnosis['ZDBM'], $sub) !== false) {
                                $isMatch = true;
                                break;
                            }
                        }
                        if (!$isMatch) {
                            $basis[] = ['desc' => '主要诊断与病理诊断不匹配，此病理诊断对应的主要诊断应是皮肤或上皮的恶性肿瘤（C44或C46.0或C51或C52或C53或C60或C63.2或C13或C14或C15或C34或C67）,否则主要诊断应选择继发性恶性肿瘤，请核实。', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                        }
                    }
                }
            }
        }

        return $basis;
    }

    /**
     * M8050
     */
    public function rule1544($data)
    {
        $basis = [];
        $ABF01C = !empty($data['data']['ABF01C']) ? $data['data']['ABF01C'] : '';

        if (!empty($ABF01C)) {
            $substring = substr($ABF01C, 0, 5);
            $substring2 = substr($ABF01C, -2);
            $subArray = ['M8050', 'M8051', 'M8052', 'M8053', 'M8060'];
            $subArray2 = ['D23', 'D10.0', 'D12.9', 'D26.0', 'D28.0', 'D28.1', 'D29.0', 'D29.4'];

            if (in_array($substring, $subArray) && $substring2 == '/0') {
                foreach ($data['diagnosis'] as $diagnosis) {
                    if ($diagnosis['ZZPB'] == 1) {
                        foreach ($subArray2 as $sub) {
                            if (strpos($diagnosis['ZDBM'], $sub) === false) {
                                $basis[] = ['desc' => '主要诊断与病理诊断不匹配，此病理诊断对应的主要诊断应是皮肤或上皮的良性肿瘤（D23或D10.0或D12.9或D26.0或D28. 0或D28.1或D29.0或D29.4），请核实。', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                                break;
                            }
                        }
                    }
                }
            }
        }

        return $basis;
    }

    /**
     * M808
     */
    //    public function rule1545($data)
    //    {
    //        $basis = [];
    //        $ABF01C = !empty($data['data']['ABF01C']) ? $data['data']['ABF01C'] : '';
    //
    //        if (!empty($ABF01C)) {
    //            $substring = substr($ABF01C, 0, 4);
    //            $substring2 = substr($ABF01C, -2);
    //            $subArray = ['M801', 'M802', 'M803', 'M804', 'M805', 'M806', 'M807', 'M808'];
    //            $subArray2 = ['C44', 'C46.0', 'C51', 'C52', 'C60', 'C63.2'];
    //
    //            if (in_array($substring, $subArray) && $substring2 == '/3') {
    //                foreach ($data['diagnosis'] as $diagnosis) {
    //                    if ($diagnosis['ZZPB'] == 1) {
    //                        foreach ($subArray2 as $sub) {
    //                            if (strpos($diagnosis['ZDBM'], $sub) !== false) {
    //                                $basis[] = ['desc' => '主要诊断与病理诊断不匹配，此病理诊断对应的主要诊断应是皮肤或上皮的恶性肿瘤（C44或C46.0或C51或C52或C60或C63.2）,否则主要诊断应选择继发性恶性肿瘤，请核实。', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
    //                                break;
    //                            }
    //                        }
    //                    }
    //                }
    //            }
    //        }
    //
    //        return $basis;
    //    }

    /**
     * M8053
     */
    //    public function rule1546($data)
    //    {
    //        $basis = [];
    //        $ABF01C = !empty($data['data']['ABF01C']) ? $data['data']['ABF01C'] : '';
    //
    //        if (!empty($ABF01C)) {
    //            $substring = substr($ABF01C, 0, 5);
    //            $substring2 = substr($ABF01C, -2);
    //            $subArray = ['M8050', 'M8051', 'M8052', 'M8053', 'M8060'];
    //            $subArray2 = ['D23', 'D10.0', 'D12.9', 'D28.0', 'D28.1', 'D29.0', 'D29.4'];
    //
    //            if (in_array($substring, $subArray) && $substring2 == '/3') {
    //                foreach ($data['diagnosis'] as $diagnosis) {
    //                    if ($diagnosis['ZZPB'] == 1) {
    //                        foreach ($subArray2 as $sub) {
    //                            if (strpos($diagnosis['ZDBM'], $sub) !== false) {
    //                                $basis[] = ['desc' => '主要诊断与病理诊断不匹配，此病理诊断对应的主要诊断应是皮肤或上皮的良性肿瘤（D23或D10.0或D12.9或D28. 0或D28.1或D29.0或D29.4，请核实。', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
    //                                break;
    //                            }
    //                        }
    //                    }
    //                }
    //            }
    //        }
    //
    //        return $basis;
    //    }

    /**
     * M918
     */
    public function rule1547($data)
    {
        $basis = [];
        $ABF01C = !empty($data['data']['ABF01C']) ? $data['data']['ABF01C'] : '';

        if (!empty($ABF01C)) {
            $substring = substr($ABF01C, 0, 4);
            $substring2 = substr($ABF01C, -2);
            $substring3 = substr($ABF01C, 0, 5);
            $subArray = ['M918', 'M919', 'M920', 'M921', 'M922', 'M923', 'M924', 'M925', 'M926', 'M927', 'M928', 'M929', 'M930', 'M931', 'M932', 'M933', 'M934'];

            if (!in_array($substring, $subArray) && $substring3 !== 'M8812' && $substring2 == '/3') {
                foreach ($data['diagnosis'] as $diagnosis) {
                    if ($diagnosis['ZZPB'] == 1) {
                        if (strpos($diagnosis['ZDBM'], 'C40') !== false || strpos($diagnosis['ZDBM'], 'C41') !== false) {
                            $basis[] = ['desc' => '主要诊断与病理诊断不匹配，此病理诊断说明该主要诊断应为骨和骨髓继发性恶性肿瘤C79.5，请核实。', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                            break;
                        }
                    }
                }
            }
        }

        return $basis;
    }

    /**
     * C71
     */
    public function rule1548($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $substring = substr($diagnosis['ZDBM'], 0, 3);
                $subArray = ['C91', 'C92', 'C93', 'C94', 'C95', 'K70', 'K71', 'K72', 'K73', 'K74', 'K75', 'K76', 'K77', 'C71', 'C78', 'C79', 'G20', 'E10', 'E11', 'E12', 'E13', 'E14'];

                foreach ($subArray as $sub) {
                    if ($substring == $sub && $diagnosis['CYQK'] == '治愈') {
                        $basis[] = ['desc' => '诊断编码' . $diagnosis['ZDBM'] . '主要诊断不可治愈，请核实。', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                        break;
                    }
                }
            }
        }

        return $basis;
    }

    /**
     * 主要诊断        编码：”V01-Y98 ”不能作为主要诊断 
     */
    public function rule2001($data)
    {
        $ICD10_ID1 = '';
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        foreach ($data['diagnosis'] as $diagnosis) {
            //主要诊断不能是V01-V99 W01-W99 Y01-Y98
            if ($diagnosis['ZZPB'] == 1) {
                $ICD10_ID1 = $diagnosis['ZDBM'];
                break;
            }
        }
        if (empty($ICD10_ID1)) {
            return $basis;
        }
        $res = $this->isProhibitedDiagnosisCode($ICD10_ID1);
        if (empty($res)) {
            return $basis;
        } else {

            $desc = $res . '不能作为主要诊断';
            $basis[] = ['desc' => $desc, 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
            return $basis;
        }

        return $basis;
    }

    /**
     * 出院情况检查（rule1548之外的情况）
     * 若主要诊断编码的前三位不属于不可治愈列表（C91–C95、C71、C78、C79、G20、E10–E14、K70–K77），
     * 则出院情况通常应为"治愈"或"好转"
     */
    public function rule2021($data)
    {
        $basis = [];
        Log::info('subzd1==', $data['diagnosis']);
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        // 不可治愈的疾病编码列表（前三位）
        $incurableCodes = ['C91', 'C92', 'C93', 'C94', 'C95', 'C71', 'C78', 'C79', 'G20', 'E10', 'E11', 'E12', 'E13', 'E14', 'K70', 'K71', 'K72', 'K73', 'K74', 'K75', 'K76', 'K77'];

        foreach ($data['diagnosis'] as $diagnosis) {
            // 只检查主要诊断
            if ($diagnosis['ZZPB'] == 1) {
                $substring = substr($diagnosis['ZDBM'], 0, 3);

                Log::info('subzd==' . $substring);

                // 判断是否在不可治愈列表中
                $isIncurable = false;
                foreach ($incurableCodes as $code) {
                    if ($substring == $code) {
                        $isIncurable = true;
                        break;
                    }
                }

                // 如果不在不可治愈列表中（rule1548之外的情况），且出院情况不包含"治愈"或"好转"
                if (!$isIncurable) {
                    $cyqk = $diagnosis['CYQK'] ?? '';
                    // 检查出院情况是否为空，或者不包含"治愈"和"好转"
                    if (empty($cyqk) || (mb_strpos($cyqk, '治愈') === false && mb_strpos($cyqk, '好转') === false)) {
                        $basis[] = ['desc' => '出院诊断【' . $diagnosis['ZDMC'] . '】，通常应为"1.治愈"或"2.好转"，请核实', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                        break;
                    }
                }
            }
        }

        return $basis;
    }

    /**
     * 诊断名称填写错误
     * 查询所有诊断，查询ICD10模型 ZDBM=诊断编码获取ZDMC，如果和查到的不一致就质控
     */
    public function rule2022($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        foreach ($data['diagnosis'] as $diagnosis) {
            if (empty($diagnosis['ZDBM'])) {
                continue;
            }

            // 查询ICD10模型获取规范诊断名称
            $icd10 = ICD10::query()->where('ZDBM', $diagnosis['ZDBM'])->first();
            if ($icd10 && !empty($icd10->ZDMC)) {
                $standardZdmc = trim($icd10->ZDMC);
                $actualZdmc = trim($diagnosis['ZDMC'] ?? '');

                // 如果诊断名称不一致，则质控
                if ($actualZdmc !== $standardZdmc) {
                    $basis[] = ['desc' => '诊断编码【' . $diagnosis['ZDBM'] . '】对应的规范诊断名称应为【' . $standardZdmc . '】', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                }
            }
        }

        return $basis;
    }

    /**
     * 诊断编码填写错误
     * 查询所有诊断，查询ICD10模型 ZDMC=诊断名称获取ZDBM，如果和查到的不一致就质控
     */
    public function rule2023($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        foreach ($data['diagnosis'] as $diagnosis) {
            $actualZdmc = trim($diagnosis['ZDMC'] ?? '');
            $actualZdbm = trim($diagnosis['ZDBM'] ?? '');
            if (empty($actualZdmc) || empty($actualZdbm)) {
                continue;
            }

            // 查询ICD10模型获取规范诊断编码（通过诊断名称反查）
            $icd10 = ICD10::query()->where('ZDMC', $actualZdmc)->first();
            if ($icd10 && !empty($icd10->ZDBM)) {
                $standardZdbm = trim($icd10->ZDBM);
                if ($actualZdbm !== $standardZdbm) {
                    $basis[] = ['desc' => '诊断名称【' . $actualZdmc . '】对应的规范诊断编码应为【' . $standardZdbm . '】', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                }
            }
        }

        return $basis;
    }

    /**
     * 手术名称填写错误
     * 查询所有手术，查询ICD9模型 SSCZBM=手术编码获取SSCZMC，如果和查到的不一致就质控
     */
    public function rule2024($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        foreach ($data['operation'] as $operation) {
            $actualSsbm = trim($operation['SSCZBM'] ?? '');
            if (empty($actualSsbm)) {
                continue;
            }

            $icd9 = ICD9::query()->where('SSCZBM', $actualSsbm)->first();
            if ($icd9 && !empty($icd9->SSCZMC)) {
                $standardSsmc = trim($icd9->SSCZMC);
                $actualSsmc = trim($operation['SSCZMC'] ?? '');
                if ($actualSsmc !== $standardSsmc) {
                    $basis[] = ['desc' => '手术编码【' . $actualSsbm . '】对应的规范手术名称应为【' . $standardSsmc . '】', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                }
            }
        }

        return $basis;
    }

    /**
     * 手术编码填写错误
     * 查询所有手术，查询ICD9模型 SSCZMC=手术名称获取SSCZBM，如果和查到的不一致就质控
     */
    public function rule2025($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        foreach ($data['operation'] as $operation) {
            $actualSsmc = trim($operation['SSCZMC'] ?? '');
            $actualSsbm = trim($operation['SSCZBM'] ?? '');
            if (empty($actualSsmc) || empty($actualSsbm)) {
                continue;
            }

            // 查询ICD9模型获取规范手术编码（通过手术名称反查）
            $icd9 = ICD9::query()->where('SSCZMC', $actualSsmc)->first();
            if ($icd9 && !empty($icd9->SSCZBM)) {
                $standardSsbm = trim($icd9->SSCZBM);
                if ($actualSsbm !== $standardSsbm) {
                    $basis[] = ['desc' => '手术名称【' . $actualSsmc . '】对应的规范手术编码应为【' . $standardSsbm . '】', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                }
            }
        }

        return $basis;
    }

    /** 
     * 检查诊断代码是否属于禁止范围 
     * 
     * @param string $code 诊断代码 
     * @return string|bool 如果属于禁止范围返回具体的编码（如'V01'），否则返回false 
     */
    private function isProhibitedDiagnosisCode($code)
    {
        // 只取前三位字符进行检查 
        $checkCode = substr($code, 0, 3);

        // 提取字母和数字部分 
        if (preg_match('/^([A-Z])(\d+)/i', $checkCode, $matches)) {
            $letter = strtoupper($matches[1]);
            $number = (int)$matches[2];

            // 检查是否在禁止范围内 
            if (($letter === 'V' && $number >= 1 && $number <= 99) ||
                ($letter === 'W' && $number >= 1 && $number <= 99) ||
                ($letter === 'Y' && $number >= 1 && $number <= 98)
            ) {
                return $checkCode;
            }
        }

        return '';
    }

    /**
     * 有手术日期手术名称不能为空
     * @param $data
     * @return bool
     */
    public function rule2002($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }
        foreach ($data['operation'] as $operation) {
            if (!empty($operation['SSCZRI']) && empty($operation['SSCZMC'])) {
                $desc = '手术日期【' . $operation['SSCZRI'] . '】对应的手术名称未填写';
                $basis[] = ['desc' => $desc, 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
            }
        }
        return $basis;
    }

    /**
     * 切口类型为0时，愈合等级应为空
     * @param $data
     * @return bool
     */
    public function rule2003($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }
        foreach ($data['operation'] as $operation) {
            if (($operation['QKDJ'] == '0' || empty($operation['QKDJ'])) && !empty($operation['YHDJ'])) {
                $desc = $operation['SSCZMC'] . '切口类型为0时，愈合等级应为空';
                $basis[] = ['desc' => $desc, 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
            }
        }
        return $basis;
    }


    /**
     * 有手术名称，手术类型不能为空
     * @param $data
     * @return bool
     */
    public function rule2007($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }
        foreach ($data['operation'] as $operation) {
            if (!empty($operation['SSCZMC']) && empty($operation['OPE_TYPE'])) {
                $desc = '手术名称【' . $operation['SSCZMC'] . '】，手术类型不能为空';
                $basis[] = ['desc' => $desc, 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
            }
        }
        return $basis;
    }


    /**
     * 麻醉方式（首页麻醉费用不为空，至少有一条麻醉方式不为空）
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule2008($data)
    {
        $basis = [];
        if (empty($data['data']["D20X01"])) {
            return $basis;
        }
        $res = false;
        foreach ($data['operation'] as $operation) {
            // 麻醉方式
            if (!empty($operation['MZFS'])) {
                $res = true;
                break;
            }
        }
        if (!$res) {
            $basis[] = ['desc' => '首页收费【麻醉费】，麻醉方式不能为空', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 主诊断不可入组
     * 主诊断编码不能包含DRG2.0-ZD表中的zdbm字段
     */
    public function rule2009($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $res = DRG2::query()->where('zdbm', $diagnosis['ZDBM'])->first();
                if ($res) {
                    $basis[] = ['desc' => '主诊断编码【' . $res->zdbm . '】，主诊断名称【' . $res->zdmc . '】不能作为主要诊断', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                }
            }
        }
        return $basis;
    }

    /**
     * 离院方式为死亡的，所有出院情况需要填死亡
     */

    public function rule2010($data)
    {
        $basis = [];
        //AEM01C = 5 或者=死亡
        if ($data['data']['AEM01C'] != 5 && $data['data']['AEM01C'] != '死亡') {
            return $basis;
        } else {
            //诊断
            foreach ($data['diagnosis'] as $diagnosis) {
                if (!empty($diagnosis['ZDMC']) && !empty($diagnosis['CYQK']) && $diagnosis['CYQK'] != '死亡') {
                    $desc = '离院方式为死亡的，所有出院情况需要填死亡';
                    $basis[] = ['desc' => $desc, 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                    break;
                }
            }
        }

        return $basis;
    }

    /**
     * 所有诊断编码如果包含‘*’必须包含‘+’
     */
    public function rule2011($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }
        foreach ($data['diagnosis'] as $diagnosis) {
            if (strpos($diagnosis['ZDBM'], '*') !== false && strpos($diagnosis['ZDBM'], '+') === false) {
                $basis[] = ['desc' => '诊断编码【' . $diagnosis['ZDBM'] . '】包含【*】必须同时包含【+】', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
            }
        }
        return $basis;
    }

    /**
     * 年龄小于15周岁患者，诊断不能下【支气管炎J40.x00】，应为【急性支气管炎J20.900】
     */
    public function rule2012($data)
    {
        $basis = [];
        $age = $data['data']['AAA04'] ?? 0;
        if ($age >= 15) {
            return $basis;
        }
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        foreach ($data['diagnosis'] as $diagnosis) {
            $zdbm = $diagnosis['ZDBM'];
            //取前7位
            $zdbmstr = substr($zdbm, 0, 7);
            if ($zdbmstr == 'J40.x00') {
                $basis[] = ['desc' => '年龄小于15周岁患者，诊断不能下【支气管炎' . $zdbm . '】，应为【急性支气管炎J20.900】', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                break;
            }
        }
        return $basis;
    }

    /**
     * T93不能作为主诊断
     */
    public function rule2013($data)
    {
        $basis = [];
        if (empty($data['diagnosis'])) {
            return $basis;
        }
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $zdbm = $diagnosis['ZDBM'];
                $zdbmstr = substr($zdbm, 0, 3);
                if ($zdbmstr == 'T93') {
                    $basis[] = ['desc' => '诊断编码【' . $zdbm . '】不能作为主诊断', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
                    break;
                }
            }
        }
        return $basis;
    }

    /**
     * 手术名称包含血管支架植入，需另编码0.40-0.43，00.44，00.45-00.48
     */
    public function rule2014($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }
        $isxgzj = false;
        foreach ($data['operation'] as $operation) {
            if (strpos($operation['SSCZMC'], '血管支架植入') !== false) {
                $isxgzj = true;
                break;
            }
        }
        if (!$isxgzj) {
            return $basis;
        }
        $is4043 = false;
        $is404548 = false;
        foreach ($data['operation'] as $operation) {

            $ssbm = $operation['SSCZBM'];

            $ssbmstr = substr($ssbm, 0, 4);
            if ($ssbmstr == '0.40' || $ssbmstr == '0.41' || $ssbmstr == '0.42' || $ssbmstr == '0.43') {
                $is4043 = true;
            }
            $ssbmstr = substr($ssbm, 0, 5);
            if ($ssbmstr == '00.45' || $ssbmstr == '00.46' || $ssbmstr == '00.47' || $ssbmstr == '00.48') {
                $is404548 = true;
            }
        }

        if (!$is4043 || !$is404548) {
            $basis[] = ['desc' => '手术名称包含血管支架植入，需另编码0.40-0.43，00.44，00.45-00.48', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 手术名称包含球囊扩张，需另编码0.40-0.43，00.44
     */
    public function rule2015($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }
        $isxgzj = false;
        foreach ($data['operation'] as $operation) {
            if (strpos($operation['SSCZMC'], '球囊扩张') !== false) {
                $isxgzj = true;
                break;
            }
        }
        if (!$isxgzj) {
            return $basis;
        }
        $is4043 = false;
        foreach ($data['operation'] as $operation) {

            $ssbm = $operation['SSCZBM'];

            $ssbmstr = substr($ssbm, 0, 4);
            if ($ssbmstr == '0.40' || $ssbmstr == '0.41' || $ssbmstr == '0.42' || $ssbmstr == '0.43') {
                $is4043 = true;
            }
        }

        if (!$is4043) {
            $basis[] = ['desc' => '手术名称包含球囊扩张，需另编码0.40-0.43，00.44', 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }

    /**
     * 有手术名称，麻醉方式不能为空
     * @param $data
     * @return bool
     */
    public function rule2016($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }
        foreach ($data['operation'] as $operation) {
            if (!empty($operation['SSCZMC']) && empty($operation['MZFS'])) {
                $desc = '手术名称【' . $operation['SSCZMC'] . '】，麻醉方式不能为空';
                $basis[] = ['desc' => $desc, 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
            }
        }
        return $basis;
    }

    /**
     * 【入院后确诊日期】需在 入院时间 至 出院时间之间
     */
    public function rule2017($data)
    {
        $basis = [];
        if (empty($data['data']['QZSJ'])) {
            return $basis;
        }
        if (empty($data['data']['AAB01'])) {
            return $basis;
        }
        if (empty($data['data']['AAC01'])) {
            return $basis;
        }
        //转换成y-m-d
        $QZSJ = date('Y-m-d', strtotime($data['data']['QZSJ']));
        $AAB01 = date('Y-m-d', strtotime($data['data']['AAB01']));
        $AAC01 = date('Y-m-d', strtotime($data['data']['AAC01']));
        if ($QZSJ < $AAB01 || $QZSJ > $AAC01) {
            $basis[] = ['desc' => '入院后确诊日期【' . $QZSJ . '】需在 入院时间【' . $AAB01 . '】至 出院时间【' . $AAC01 . '】之间', 'location' => ['user' => ['QZSJ', 'AAB01', 'AAC01']]];
        }
        return $basis;
    }

    /**
     * 规则2026：当户籍省（AAA45）为“浙江”且户籍市（AAA46）为“上海”时，提示户籍市【上海】不属于【浙江】
     * @param array $data 首页信息，需包含 'data' 键。例如：['data' => ['AAA45' => '浙江', 'AAA46' => '上海', ...]]
     * @return array 返回质控提示数组
     */
    public function rule2026($data)
    {
        $basis = [];
        $province = $data['data']['AAA45'] ?? '';
        $city = $data['data']['AAA46'] ?? '';
        //Log::info('省..'.$province);
        //Log::info('市..'.$city);
        if ($province === '浙江' && $city === '上海') {
            $basis[] = [
                'desc' => '户籍市【上海】不属于【浙江】',
                'location' => ['user' => ['AAA45', 'AAA46'], 'zd' => [], 'ss' => []]
            ];
        }
        return $basis;
    }

    /**
     * 规则2027：当户籍省（AAA46）为“上海”且户籍市（AAA47）为“郑东新区”时，
     * 提示：户籍县【郑东新区】不属于省级【浙江】，不属于市级【上海】
     * @param array $data 包含户籍省、户籍市相关信息的数据
     * @return array 返回质控提示数组
     */
    public function rule2027($data)
    {
        $basis = [];
        $province = $data['data']['AAA46'] ?? '';
        $city = $data['data']['AAA47'] ?? '';
        Log::info('市..' . $province);
        Log::info('县..' . $city);
        if ($province === '上海' && $city === '郑东新区') {
            $basis[] = [
                'desc' => '户籍县【郑东新区】不属于省级【浙江】，不属于市级【上海】',
                'location' => ['user' => ['AAA46', 'AAA47'], 'zd' => [], 'ss' => []]
            ];
        }
        return $basis;
    }

    /**
     * 病案首页脑梗死、后循环缺血不满足DRG审核要求
     */
    public function rule2034($data)
    {
        $basis = [];

        if (!$this->isFangchengAppName()) {
            return $basis;
        }

        $rawDiagnosisCode = $this->getPrimaryDiagnosisCode($data);
        $diagnosisCode = strtoupper($rawDiagnosisCode);
        if ($diagnosisCode === '' || strpos($diagnosisCode, 'I63') === false) {
            return $basis;
        }

        $orderNames = $this->getMedicalOrderNames($data['yz'] ?? []);
        $antiThromboticDrugs = [
            '阿司匹林肠溶片',
            '硫酸氢氯吡格雷片',
            '替罗非班注射液',
            '利伐沙班片',
            '华法林片',
        ];
        $lipidStabilizingDrugs = [
            '阿托伐他汀钙片',
            '瑞舒伐他汀钙片',
        ];

        $matchedAntiThromboticDrugs = $this->matchOrderDrugNames($orderNames, $antiThromboticDrugs);
        $matchedLipidStabilizingDrugs = $this->matchOrderDrugNames($orderNames, $lipidStabilizingDrugs);
        if (!empty($matchedAntiThromboticDrugs) && !empty($matchedLipidStabilizingDrugs)) {
            return $basis;
        }

        $matchedDrugNames = array_values(array_unique(array_merge($matchedAntiThromboticDrugs, $matchedLipidStabilizingDrugs)));
        if (empty($matchedDrugNames)) {
            $desc = '主诊【编码 ' . $rawDiagnosisCode . '】医嘱中无“抗血栓药物 + 降脂稳斑药物”';
        } else {
            $desc = '主诊【编码 ' . $rawDiagnosisCode . '】医嘱【' . implode('】、【', $matchedDrugNames) . '】';
        }

        $basis[] = [
            'desc' => $desc,
            'location' => ['user' => [], 'zd' => [], 'ss' => []]
        ];

        return $basis;
    }

    private function isFangchengAppName()
    {
        $appName = env('app_name', env('APP_NAME'));

        return strtolower((string)$appName) === 'fangcheng';
    }

    private function getPrimaryDiagnosisCode($data)
    {
        if (!empty($data['diagnosis']) && is_array($data['diagnosis'])) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if (($diagnosis['ZZPB'] ?? '') == 1) {
                    return trim((string)($diagnosis['ZDBM'] ?? ''));
                }
            }
        }

        return trim((string)($data['data']['ICD10_ID1'] ?? ''));
    }

    private function getMedicalOrderNames($orders)
    {
        if (empty($orders) || !is_array($orders)) {
            return [];
        }

        $orderNames = [];
        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }

            $orderName = trim((string)($order['YZMC'] ?? ''));
            if ($orderName === '') {
                continue;
            }

            $orderNames[] = $orderName;
        }

        return $orderNames;
    }

    private function matchOrderDrugNames($orderNames, $drugNames)
    {
        $matchedDrugNames = [];
        foreach ($orderNames as $orderName) {
            foreach ($drugNames as $drugName) {
                if (strpos($orderName, $drugName) !== false) {
                    $matchedDrugNames[] = $drugName;
                }
            }
        }

        return array_values(array_unique($matchedDrugNames));
    }
}
