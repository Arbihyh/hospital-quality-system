<?php

namespace App\Services;

use App\Model\Appeal;
use App\Model\CaseQuality;
use App\Model\ErrorV2;
use App\Model\HomeQuality;
use App\Model\PatientInfo;
use App\Model\PatientInfoV2;

class BasySzQualityService
{
    public static $noPhone = [11111111111,12345678911,1111111,1234567];

    public $appealRuleIds = [];
    /**
     * 病案首页质控
     * @param $data
     * @param $errorRuleData
     * @param $staffData
     * @return void
     */
    public function qualityContrl($data,$errorRuleData,$staffData)
    {
        // 姓名脱敏
        $AAA01 = !empty($data['data']['AAA01']) ? desensitize($data['data']['AAA01'], 1, 1, '*') : '';

        // 住院号
        $ZYH = $data['data']['MED_REC_ID'];

        // 主要诊断
        $ICD10 = ['ICD10_ID1'=>'','ICD10_NAME'=>''];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $ICD10 = ['ICD10_ID1'=>$diagnosis['ZDBM'],'ICD10_NAME'=>$diagnosis['ZDMC']];
                break;
            }
        }

        // 主要手术
        $ICD9 = ['ICD9_ID1'=>'','ICD9_NAME'=>''];
        foreach ($data['operation'] as $operation) {
            if ($operation['SFZYSS'] == 1) {
                $ICD9 = ['ICD9_ID1'=>$operation['SSCZBM'],'ICD9_NAME'=>$operation['SSCZMC']];
                break;
            }
        }

        // 获取申诉数据，如果有质控结果在申诉中或者审核通过，则不在质控
        $appeal = Appeal::query()
            ->where(["quality_type" => 1, "type" => 2, "ZYH" => $ZYH])->get(["error_id"])
            ->whereIn("status", [0, 1])
            ->toArray();
        $appealRuleIds = [];
        if ($appeal) {
            $appealRuleIds = array_column($appeal, "error_id");
            // 删除历史质控数据
            $appealRuleIds = array_filter($appealRuleIds);
        }
        ErrorV2::query()->where('ZYH','=',$ZYH)
            ->where("is_artificial", "=", 0)
            ->whereNotIn("rule_id", $appealRuleIds)
            ->delete();

        $insertData = [];
        $homeBmyScore = 100;
        foreach ($errorRuleData as $ruleId => $errorRule) {
            // 质控方法
            $method = 'rule'.$ruleId;
            if (!method_exists(new BasySzQualityService(), $method)) {
                continue;
            }
            // 如果规则已经在申诉中，则不需要再次质控
            if(!empty($appealRuleIds) && in_array($ruleId, $appealRuleIds)){
                continue;
            }

            // 质控
            $res = $this->$method($data);
            if (!empty($res)) {
                $homeBmyScore -= $errorRule['down'];
                $hospitalName = $data['data']['ZA03'] ?? '';
                $insertData[] = [
                    'hospital_name' => $hospitalName,   // 医院名称
                    'error_rule' => $ruleId,            // 规则id
                    'AAA28' => $data['data']['AAA28'],  // 住院号码
                    'ZYH' => $ZYH,                      // 住院号
                    'AAA01' => !empty($AAA01) ? desensitize($AAA01,1,1) : $AAA01,
                    'AAB01' => $data['data']['AAB01'],  // 入院时间
                    'AAC01' => $data['data']['AAC01'],  // 出院时间
                    'desc' => $errorRule['desc'],       // 错误内容
                    'coder_id' => $data['data']['AEE08'],   // 编码员编号
                    'coder_name' => $staffData[$data['data']['AEE08']] ?? '', // 编码员姓名
                    'CYKSBM' => $data['data']['AAC11C'],// 出院科室编码
                    'CYKB' => $data['data']['AAC11N'],  // 出院科室名称
                    'ZZYS_BH' => $data['data']['AEE03_CODE'],   // 主治医师编码
                    'ZZYS' => $data['data']['AEE03'],   // 主治医师姓名
                    'ZYYS_BH' => $data['data']['AEE04_CODE'],   // 住院医师编码
                    'ZYYS' => $data['data']['AEE04'],   // 住院医师姓名
                    'ICD10_ID1' => $ICD10['ICD10_ID1'],     // 主要诊断编码
                    'ICD10_NAME' => $ICD10['ICD10_NAME'],   // 主要诊断名称
                    'ICD9_ID1' => $ICD9['ICD9_ID1'],    // 主要手术编码
                    'ICD9_NAME' => $ICD9['ICD9_NAME'],  // 主要手术名称
                ];
            }
        }

        PatientInfoV2::query()
            ->where('ZYH','=',$ZYH)
            ->where('status','=',0)
            ->update(['score'=>$homeBmyScore]);

        if (!empty($insertData)) {
            ErrorV2::query()->insert($insertData);
        }

        $score_lv = "优";
        if ($homeBmyScore >= 97) {
            $score_lv = '优';
        } elseif ($homeBmyScore >= 90 && $homeBmyScore < 97) {
            $score_lv = '良';
        } elseif ($homeBmyScore >= 75 && $homeBmyScore < 90) {
            $score_lv = '中';
        } elseif ($homeBmyScore < 75) {
            $score_lv = '差';
        }
        // is_case 将病例的质控状态改为未质控
        PatientInfo::query()->where('MED_REC_ID', '=', $ZYH)->update(['home_ysz_score' => $homeBmyScore, 'home_ysz_score_lv' => $score_lv]);
    }

    /**
     * 质控结果同步到Es
     * @param $ZYH
     * @param $errorRuleData
     * @return true
     */
    public function syncHomeQualityEs($ZYH,$errorRuleData)
    {
        $homeQualityData = HomeQuality::query()->where('ZYH','=',$ZYH)->get()->toArray();
        if (empty($homeQualityData)) {
            return true;
        }

        $es_params = [];
        foreach ($homeQualityData as $value) {
            $es_params['body'][] = ['update' => ['_index' => 'home_quality', '_id' => $value['id']]];
            $es_params['body'][] = ['doc' => [
                'data_id'       => $value['id'],
                'hospital_name' => $value['hospital_name'],
                'AAA28'         => $value['AAA28'],
                'ZYH'           => $ZYH,
                'AAC01'         => $value['AAC01'],
                'error_rule'    => $value['error_rule'],
                'field'         => $errorRuleData[$value['error_rule']]['auth'],
                'field_name'    => $errorRuleData[$value['error_rule']]['field'],
                'desc'          => $errorRuleData[$value['error_rule']]['desc'],
                'level'         => $errorRuleData[$value['error_rule']]['level'],
                'type'          => $errorRuleData[$value['error_rule']]['type'],
                'down'          => $errorRuleData[$value['error_rule']]['down'],
                'error_type'    => $errorRuleData[$value['error_rule']]['error_type'],
                'category'      => $errorRuleData[$value['error_rule']]['category'],
                'ZKDX'          => $errorRuleData[$value['error_rule']]['ZKDX'],
                'ZKFL'          => $errorRuleData[$value['error_rule']]['ZKFL'],
                'basis'         => $value['basis'],
                'AAC11C'        => $value['AAC11C'],
                'AEE03_CODE'    => $value['AEE03_CODE'],
                'AEE04_CODE'    => $value['AEE04_CODE'],
                'AEE08_CODE'    => $value['AEE08_CODE'],
                'is_del'        => $value['is_del'],
                'ICD10_ID1'     => $value['ICD10_ID1'],
                'ICD10_NAME'    => $value['ICD10_NAME'],
                'ICD9_ID1'      => $value['ICD9_ID1'],
                'ICD9_NAME'     => $value['ICD9_NAME'],
            ], 'doc_as_upsert' => true];
        }

        app('es')->bulk($es_params);

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
        if (mb_strlen($UNT_ID) < 6 || mb_strlen($UNT_ID) > 22 || $UNT_ID==123456) {
            $basis[] = ['desc'=>'组织机构代码错误','location'=>['user'=>['UNT_ID']]];
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
            $basis[] = ['desc'=>'主要号码填写错误','location'=>['user'=>['AAA28']]];
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
        if (mb_strlen($ZA03) < 4 || $ZA03 > 80 || !$res) {
            $basis[] = ['desc'=>'医疗机构名称填写错误','location'=>['user'=>['ZA03']]];
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
        if (!preg_match("/^[1-9][0-9]*$/" ,$data['data']['AAA29'])) {
            $basis[] = ['desc'=>'住院次数只能填写大于0的正整数','location'=>['user'=>['AAA29']]];
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
        $AAB01 = $data['data']['AAB01'] ? date('Y-m-d',strtotime($data['data']['AAB01'])) : '';
        $AAC01 = $data['data']['AAC01'] ? date('Y-m-d',strtotime($data['data']['AAC01'])) : '';

        $basis = [];
        if (empty($AAB01)) {
            $basis[] = ['desc'=>'入院时间未填写','location'=>['user'=>['AAB01']]];
        } elseif ($AAB01 > $AAC01) {
            $basis[] = ['desc'=>'入院时间不能大于出院时间','location'=>['user'=>['AAB01','AAC01']]];
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
            $basis[] = ['desc'=>'健康卡号未填写','location'=>['user'=>['JKKH']]];
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
            $basis[] = ['desc'=>'患者姓名长度应在2~40之间','location'=>['user'=>['AAA01']]];
        } else {
            // 获取第一位
            $firstStr = mb_substr($XM,0,1);
            // 获取最后一位
            $xmLen = mb_strlen($XM);
            $endStr = mb_substr($XM,$xmLen-1,1);

            $res1 = PublicService::pregMatchTszf($firstStr,'digit');
            $res2 = PublicService::pregMatchTszf($firstStr,'punct');

            $res3 = PublicService::pregMatchTszf($endStr,'digit');
            $res4 = PublicService::pregMatchTszf($endStr,'punct');

            $res5 = PublicService::pregMatchTszf($XM,'space');
            if ($res1 || $res2 || $res3 || $res4) {
                $basis[] = ['desc'=>'患者姓名的第一位和最后一位不能是数字或标点符号','location'=>['user'=>['AAA01']]];
            } elseif ($res5) {
                $basis[] = ['desc'=>'患者姓名中有空格','location'=>['user'=>['AAA01']]];
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
            $basis[] = ['desc'=>'出生地省长度不能小于2位','location'=>['user'=>['AAA09']]];
        } elseif (preg_match("/^[0-9]*$/" , $AAA09)) {
            $basis[] = ['desc'=>'出生地省不能全是数字','location'=>['user'=>['AAA09']]];
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
            $basis[] = ['desc'=>'籍贯省长度不能小于2位','location'=>['user'=>['AAA43']]];
        } elseif (preg_match("/^[0-9]*$/" , $AAA43)) {
            $basis[] = ['desc'=>'籍贯省不能全是数字','location'=>['user'=>['AAA43']]];
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
            $basis[] = ['desc'=>'民族不能为空','location'=>['user'=>['AAA06C']]];
        } elseif ($AAA06C == '-') {
            $basis[] = ['desc'=>'民族不能是 - ','location'=>['user'=>['AAA06C']]];
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
        $basis = [];
        if ($data['data']['AAC11C'] != 287 && mb_strlen($AAA07) != 15 && mb_strlen($AAA07) != 18) {
            $basis[] = ['desc'=>'身份证号只能是15位或18位','location'=>['user'=>['AAA07']]];
        }

        // 匹配身份证号的正确性
        $pattern = "/^[1-9]\d{5}(18|19|20|21|22)?\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\d{3}(\d|[Xx])$/";
        $pregRes = preg_match($pattern, $AAA07);
        if (!$pregRes && $AAA07 != '-') {
            $basis[] = ['desc'=>'身份证号不能为空','location'=>['user'=>['AAA07']]];
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
            $basis[] = ['desc'=>'职业未填写','location'=>['user'=>['AAA18C']]];
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
            $basis[] = ['desc'=>'婚姻状况未填写','location'=>['user'=>['AAA08C']]];
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
        $AAA48 = str_replace(' ','',$AAA48);
        $basis = [];
        if (mb_strlen($AAA48) < 2) {
            $basis[] = ['desc'=>'现住址省不能少于2位','location'=>['user'=>['AAA48']]];
        } elseif (preg_match("/^[0-9]*$/" , $AAA48)) {
            $basis[] = ['desc'=>'出生地省不能全是数字','location'=>['user'=>['AAA48']]];
        }
        return $basis;
    }

    /**
     * 电话
     * @param $data
     * @return array
     */
    public function rule15($data)
    {
        $AAA51 = !empty($data['data']['AAA51']) ? trim($data['data']['AAA51']) : '';

        $basis = [];
        if (mb_strlen($AAA51)<7) {
            $basis[] = ['desc'=>'电话号码不能少于7位','location'=>['user'=>['AAA51']]];
        } elseif (in_array($AAA51, self::$noPhone)) {
            $basis[] = ['desc'=>'电话号码不能是：'.$AAA51,'location'=>['user'=>['AAA51']]];
        } else {
            $res1 = preg_match('/^1[0-9]{10}$/', $AAA51);
            $res2 = preg_match('/^[0-9]{4}[\-][0-9]{7}$/', $AAA51);
            $res3 = preg_match('/^[0-9]{7}$/', $AAA51);
            if (!$res1 && !$res2 && !$res3) {
                $basis[] = ['desc'=>'电话号码格式错误','location'=>['user'=>['AAA51']]];
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
        $AAA17C = str_replace(' ','',$AAA17C);
        $basis = [];
        if (mb_strlen($AAA17C) != 6) {
            $basis[] = ['desc'=>'请填写6位现住址邮政编码','location'=>['user'=>['AAA17C']]];
        } elseif ($AAA17C == 123456) {
            $basis[] = ['desc'=>'现住址邮政编码不能是：123456','location'=>['user'=>['AAA17C']]];
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
        $AAA45 = str_replace(' ','',$AAA45);
        $basis = [];
        if (mb_strlen($AAA45) < 2) {
            $basis[] = ['desc'=>'户籍省不能少于2位','location'=>['user'=>['AAA45']]];
        } elseif (preg_match("/^[0-9]*$/" , $AAA45)) {
            $basis[] = ['desc'=>'户籍省不能全是数字','location'=>['user'=>['AAA45']]];
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
        $AAA13C = !empty($data['data']['AAA13C']) ? trim($data['data']['AAA13C']) : '';
        $AAA13C = str_replace(' ','',$AAA13C);
        $basis = [];
        if (mb_strlen($AAA13C) != 6) {
            $basis[] = ['desc'=>'请填写6位现住址邮政编码','location'=>['user'=>['AAA13C']]];
        } elseif ($AAA13C == 123456) {
            $basis[] = ['desc'=>'现住址邮政编码不能是：123456','location'=>['user'=>['AAA13C']]];
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
        $AAA19 = str_replace(' ','',$AAA19);
        $basis = [];

        if ($AAA19 == '无' || $AAA19 =='-') {
            return $basis;
        }

        if (mb_strlen($AAA19) < 2) {
            $basis[] = ['desc'=>'工作单位及地址不能少于2位','location'=>['user'=>['AAA19']]];
        } elseif (preg_match("/^[0-9]*$/" , $AAA19)) {
            $basis[] = ['desc'=>'工作单位及地址不能全是数字','location'=>['user'=>['AAA19']]];
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

        if($AAA20 == '无' || $AAA20== '-'){
            return $basis;
        }

        if (mb_strlen($AAA20) < 7) {
            $basis[] = ['desc'=>'单位电话不能少于7位','location'=>['user'=>['AAA20']]];
        } elseif (in_array($AAA20, self::$noPhone)) {
            $basis[] = ['desc'=>'单位电话不能是：'.$AAA20,'location'=>['user'=>['AAA20']]];
        } else {
            $res1 = preg_match('/^1[0-9]{10}$/', $AAA20);
            $res2 = preg_match('/^[0-9]{4}[\-][0-9]{7}$/', $AAA20);
            $res3 = preg_match('/^[0-9]{7}$/', $AAA20);
            if (!$res1 && !$res2 && !$res3) {
                $basis[] = ['desc'=>'单位电话格式错误','location'=>['user'=>['AAA20']]];
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
        $AAA21C = str_replace(' ','',$AAA21C);
        $basis = [];
        if (mb_strlen($AAA21C) != 6) {
            $basis[] = ['desc'=>'请填写6位单位邮编号码','location'=>['user'=>['AAA21C']]];
        } elseif ($AAA21C == 123456) {
            $basis[] = ['desc'=>'单位邮编号码不能是：123456','location'=>['user'=>['AAA21C']]];
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
            $basis[] = ['desc'=>'联系人姓名长度应在2~40之间','location'=>['user'=>['AAA22']]];
        } else {
            // 获取第一位
            $firstStr = mb_substr($AAA22,0,1);
            // 获取最后一位
            $xmLen = mb_strlen($AAA22);
            $endStr = mb_substr($AAA22,$xmLen-1,1);

            $res1 = PublicService::pregMatchTszf($firstStr,'digit');
            $res2 = PublicService::pregMatchTszf($firstStr,'punct');

            $res3 = PublicService::pregMatchTszf($endStr,'digit');
            $res4 = PublicService::pregMatchTszf($endStr,'punct');

            $res5 = PublicService::pregMatchTszf($AAA22,'space');
            if ($res1 || $res2 || $res3 || $res4) {
                $basis[] = ['desc'=>'联系人姓名的第一位和最后一位不能是数字或标点符号','location'=>['user'=>['AAA22']]];
            } elseif ($res5) {
                $basis[] = ['desc'=>'联系人姓名中有空格','location'=>['user'=>['AAA22']]];
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
        if (mb_strlen($data['data']['AAA23C']) < 1) {
            $basis[] = ['desc'=>'请填写联系人','location'=>['user'=>['AAA23C']]];
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
        $AAA24 = str_replace(' ','',$AAA24);
        $basis = [];
        if (mb_strlen($AAA24) < 2) {
            $basis[] = ['desc'=>'联系人地址不能少于2位','location'=>['user'=>['AAA24']]];
        } elseif (preg_match("/^[0-9]*$/" , $AAA24)) {
            $basis[] = ['desc'=>'联系人地址不能全是数字','location'=>['user'=>['AAA24']]];
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
        if (mb_strlen($AAA25) < 7) {
            $basis[] = ['desc'=>'联系人电话不能少于7位','location'=>['user'=>['AAA25']]];
        } elseif (in_array($AAA25, self::$noPhone)) {
            $basis[] = ['desc'=>'联系人电话不能是'.$AAA25,'location'=>['user'=>['AAA25']]];
        } else {
            $res1 = preg_match('/^1[0-9]{10}$/', $AAA25);
            $res2 = preg_match('/^[0-9]{4}[\-][0-9]{7}$/', $AAA25);
            $res3 = preg_match('/^[0-9]{7}$/', $AAA25);
            if (!$res1 && !$res2 && !$res3) {
                $basis[] = ['desc'=>'联系人电话格式错误','location'=>['user'=>['AAA25']]];
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
            $basis[] = ['desc'=>'性别未填写','location'=>['user'=>['AAA02C']]];
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
        $AAA03 = !empty($data['data']['AAA03']) ? trim($data['data']['AAA03']) : '';
        $basis = [];
        if (mb_strlen($AAA03) < 1) {
            $basis[] = ['desc'=>'出生日期未填写','location'=>['user'=>['AAA03']]];
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
        if ($AAA04==0 && $AAA40==0 && $AAC11C=='287') {
            return $basis;
        }

        $res1 = preg_match('/^[1-9][0-9]*$/', $data['data']['AAA04']);
        $arr = [];
        for ($i=1;$i<365;$i++) {
            $arr[] = $i;
        }

        if (!$res1 && !in_array($data['data']['AAA40'],$arr)) {
            $basis[] = ['desc'=>'年龄或天龄填写错误','location'=>['user'=>['AAA04','AAA40']]];
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
            $basis[] = ['desc'=>'国籍未填写','location'=>['user'=>['AAA05C']]];
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
            $basis[] = ['desc'=>'入院科别未填写','location'=>['user'=>['AAB02C']]];
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
            $basis[] = ['desc'=>'入院途径未填写','location'=>['user'=>['AAB06C']]];
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
            $basis[] = ['desc'=>'入院病房未填写','location'=>['user'=>['AAB11N']]];
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
            if (!empty($data['data']['AAD01C'])) {
                return [['desc'=>'转科科别填写错误','location'=>['user'=>['AAD01C']]]];
            }
            return [];
        }

        $res = false;
        foreach ($zyZkjlData as $value) {
            if (in_array($value['HCLX'],[1,3])) {
                $res = true;
                break;
            }
        }

        $basis = [];
        if ($res && empty($data['data']['AAD01C'])) {
            $basis[] = ['desc'=>'转科科别填写错误','location'=>['user'=>['AAD01C']]];
        } elseif (!empty($data['data']['AAD01C'])) {
            $basis[] = ['desc'=>'转科科别填写错误','location'=>['user'=>['AAD01C']]];
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
            $basis[] = ['desc'=>'出院科别未填写','location'=>['user'=>['AAC02C']]];
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
            $basis[] = ['desc'=>'实际住院天数只能填写大于0的正整数','location'=>['user'=>['AAC04']]];
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
            $basis[] = ['desc'=>'门(急)诊诊断编码未填写','location'=>['user'=>['ABA01C']]];
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
            $basis[] = ['desc'=>'门(急)诊诊断名称未填写','location'=>['user'=>['ABA01N']]];
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
            $basis[] = ['desc'=>'主要诊断编码未填写','location'=>['zd'=>[1]]];
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
            $basis[] = ['desc'=>'主要诊断名称未填写','location'=>['zd'=>[1]]];
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
        if (empty($data['data']['AEB02C'])) {
            $basis[] = ['desc'=>'有无药物过敏未填写','location'=>['user'=>['AEB02C']]];
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
            $basis[] = ['desc'=>'科主任姓名要在2~40之间','location'=>['user'=>['AEE01']]];
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
            $basis[] = ['desc'=>'主(副主)任医师姓名要在2~40之间','location'=>['user'=>['AEE02']]];
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
            $basis[] = ['desc'=>'主治医师姓名要在2~40之间','location'=>['user'=>['AEE03']]];
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
            $basis[] = ['desc'=>'住院医师姓名要在2~40之间','location'=>['user'=>['AEE04']]];
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
            $basis[] = ['desc'=>'责任护士姓名要在2~40之间','location'=>['user'=>['AEE10']]];
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
            $basis[] = ['desc'=>'责任护士未填写','location'=>['user'=>['AEE08']]];
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
            $basis[] = ['desc'=>'ABO血型未填写','location'=>['user'=>['AEG01C']]];
        } else {
            $AEG01C = config('dictionaries.AEG01C');
            if (empty($AEG01C[$data['data']['AEG01C']])) {
                $basis[] = ['desc'=>'ABO血型填写错误','location'=>['user'=>['AEG01C']]];
            }
        }
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
            $basis[] = ['desc'=>'RH血型未填写','location'=>['user'=>['AEG02C']]];
        } else {
            $AEG02C = config('dictionaries.AEG02C');
            if (empty($AEG02C[$data['data']['AEG02C']])) {
                $basis[] = ['desc'=>'RH血型填写错误','location'=>['user'=>['AEG02C']]];
            }
        }
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

        foreach ($data['operation'] as $operation) {
            if (!empty($operation['SSCZMC']) && empty($operation['SSCZBM'])) {
                $basis[] = ['desc'=>'手术【'.$operation['SSCZMC'].'】操作编码未填写','location'=>['ss'=>[$operation['SSSX']]]];
            }
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

        foreach ($data['operation'] as $operation) {
            if (empty($operation['SSCZMC'])) {
                $basis[] = ['desc'=>'手术【'.$operation['SSCZBM'].'】手术名称未填写','location'=>['ss'=>[$operation['SSSX']]]];
            }
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

        foreach ($data['operation'] as $operation) {
            if (!empty($operation['SSCZMC']) && empty($operation['SSCZRQ'])) {
                $basis[] = ['desc'=>'手术【'.$operation['SSCZMC'].'】手术日期未填写','location'=>['ss'=>[$operation['SSSX']]]];
            }
        }

        return $basis;
    }

    /**
     * 是否有出院31日内再住院计划
     * @param $data
     * @return array
     */
    public function rule63($data)
    {
        $basis = [];
        if (empty($data['data']['AEM03C'])) {
            $basis[] = ['desc'=>'是否有出院31日内再住院计划未填写','location'=>['user'=>['AEM03C']]];
        } else {
            $AEM03C = config('dictionaries.AEM03C');
            if (empty($AEM03C[$data['data']['AEM03C']])) {
                $basis[] = ['desc'=>'是否有出院31日内再住院计划，填写错误','location'=>['user'=>['AEM03C']]];
            }
        }
        return $basis;
    }

    /**
     * 离院方式
     * @param $data
     * @return array
     */
    public function rule64($data)
    {
        $basis = [];
        if ($data['data']['AEM01C'] == 9) {
            $basis[] = ['desc'=>'离院方式不能是其他','location'=>['user'=>['AEM01C']]];
        } elseif (mb_strlen($data['data']['AEM01C']) < 1) {
            $basis[] = ['desc'=>'离院方式未填写','location'=>['user'=>['AEM01C']]];
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
            $basis[] = ['desc'=>'住院总费用填写错误','location'=>['user'=>['ADA01']]];
        } elseif ($ADA01 < $ADA0101) {
            $basis[] = ['desc'=>'自付金额不能大于住院总费用','location'=>['user'=>['ADA01','ADA0101']]];
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
            $basis[] = ['desc'=>'自付金额未填写','location'=>['user'=>['ADA0101']]];
        } elseif ($ADA01 < $ADA0101) {
            $basis[] = ['desc'=>'自付金额不能大于住院总费用','location'=>['user'=>['ADA01','ADA0101']]];
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
            if ($diagnosis['ZZPB']==1) {
                $zyzd = $diagnosis['ZDBM'];
                break;
            }
        }

        if ($zyzd) {
            $arr = ['C','D00','D01','D02','D03','D04','D05','D06','D07','D08','D09'];
            for ($i=10;$i<=48;$i++) {
                $arr[] = 'D'.$i;
            }

            $res = false;
            foreach ($arr as $value) {
                if (stripos($zyzd,$value) === 0) {
                    $res = true;
                    break;
                }
            }
            if ($res && empty($data['data']['ABF01C'])) {
                $basis[] = ['desc'=>'病理诊断编码未填写','location'=>['user'=>['ABF01C']]];
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
            if ($diagnosis['ZZPB']==1) {
                $zyzd = $diagnosis['ZDBM'];
                break;
            }
        }

        if ($zyzd) {
            $arr = ['C','D00','D01','D02','D03','D04','D05','D06','D07','D08','D09'];
            for ($i=10;$i<=48;$i++) {
                $arr[] = 'D'.$i;
            }

            $res = false;
            foreach ($arr as $value) {
                if (stripos($zyzd,$value) === 0) {
                    $res = true;
                    break;
                }
            }
            if ($res && empty($data['data']['ABF01N'])) {
                $basis[] = ['desc'=>'病理诊断名称未填写','location'=>['user'=>['ABF01N']]];
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
        if (!empty($data['data']['ABF01C']) && empty($data['data']['ABF04'])) {
            $basis[] = ['desc'=>'病理号未填写','location'=>['user'=>['ABF04']]];
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
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB']==1) {
                $zyzd = $diagnosis['ZDBM'];
                break;
            }
        }

        if (stripos($zyzd,'S') === 0 || stripos($zyzd,'T') === 0) {
            if (empty($data['data']['ABG01C'])) {
                $basis[] = ['desc'=>'损伤和中毒外部原因编码未填写','location'=>['user'=>['ABG01C']]];
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
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB']==1) {
                $zyzd = $diagnosis['ZDBM'];
                break;
            }
        }

        if (stripos($zyzd,'S') === 0 || stripos($zyzd,'T') === 0) {
            if (empty($data['data']['ABG01N'])) {
                $basis[] = ['desc'=>'损伤和中毒外部原因未填写','location'=>['user'=>['ABG01N']]];
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
        if ($AEB02C == 1 && !empty($AEB01)) {
            $basis[] = ['desc'=>'无药物过敏，过敏药物名称应为空','location'=>['user'=>['AEB01']]];
        } elseif($AEB02C == 2 && (empty($AEB01) || $AEB01=='无' || $AEB01=='-' || is_numeric($AEB01))) {
            $basis[] = ['desc'=>'过敏药物名称填写错误','location'=>['user'=>['AEB01']]];
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

        foreach ($data['operation'] as $operation) {
            if (in_array($operation['SSPB'],['手术','介入治疗']) && mb_strlen($operation['SSJB'])<1) {
                $basis[] = ['desc'=>'手术【'.$operation['SSCZMC'].'】操作级别未填写','location'=>['ss'=>[$operation['SSSX']]]];
            }
        }

        return $basis;
    }

    /**
     * 切口愈合等级
     * @param $data
     * @return array
     */
    public function rule77($data)
    {
        $basis = [];
        if (empty($data['operation'])) {
            return $basis;
        }

        foreach ($data['operation'] as $operation) {
            if (!empty($operation['SSJB']) && mb_strlen(trim($operation['QKDJ']))<1) {
                $basis[] = ['desc'=>'手术【'.$operation['SSCZMC'].'】切口愈合等级未填写','location'=>['ss'=>[$operation['SSSX']]]];
            }
        }

        return $basis;
    }

    /**
     * 新生儿入院体重(克)
     * @param $data
     * @return array
     */
    public function rule82($data)
    {
        $res = preg_match("/^[1-9][0-9]{2,3}$/", $data['data']['AAA42']);
        $basis = [];
        if ($data['data']['AAC11C'] == 287 && !$res) {
            $basis[] = ['desc'=>'新生儿入院体重（克）填写错误','location'=>['user'=>['AAA42']]];
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
                $basis[] = ['desc'=>'无31天在入院计划，在计划目的应为空','location'=>['user'=>['AEM04']]];
            }
        } elseif($data['data']['AEM03C'] == 2) {
            if (empty($data['data']['AEM04'])) {
                $basis[] = ['desc'=>'有31天在入院计划，入院目的不能为空','location'=>['user'=>['AEM03C']]];
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
        if (empty($data['diagnosis'])) {
            return $basis;
        }

        $zyzd = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB']==1) {
                $zyzd = $diagnosis['ZDBM'];
                break;
            }
        }

        if ($zyzd) {
            $arr = ['C','D00','D01','D02','D03','D04','D05','D06','D07','D08','D09'];
            for ($i=10;$i<=48;$i++) {
                $arr[] = 'D'.$i;
            }

            $res = false;
            foreach ($arr as $value) {
                if (stripos($zyzd,$value) === 0) {
                    $res = true;
                    break;
                }
            }
            if ($res && !empty($data['data']['ABF01C'])) {
                $str = mb_substr($data['data']['ABF01C'],0,1);
                if ($str != 'M') {
                    $basis[] = ['desc'=>'病理诊断编码只能以M开头','location'=>['user'=>['ABF01C']]];
                }
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

        $arr = ['C50','C51','C52','C53','C54','C55','C56','C57','C58','C59','C60','C61','C62','C63'];
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $basis[] = ['desc'=>'12岁及以下儿童不能应用编码【'.$diagnosis['ZDBM'].'】','location'=>['user'=>['AAA04'],'zd'=>[$diagnosis['ZDXH']]]];
                }
            }
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

        $arr = ['O00','O01','O02','O03','O04','O05','O06','O07','O08','O09'];
        for ($i=10;$i<=99;$i++) {
            $arr[] = 'O'.$i;
        }

        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $basis[] = ['desc'=>'12岁及以下儿童不能应用编码【'.$diagnosis['ZDBM'].'】','location'=>['user'=>['AAA04'],'zd'=>[$diagnosis['ZDXH']]]];
                }
            }
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

        $arr = ['D24','D25','D26','D27','D28','D29'];
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $basis[] = ['desc'=>'12岁及以下儿童不能应用编码【'.$diagnosis['ZDBM'].'】','location'=>['user'=>['AAA04'],'zd'=>[$diagnosis['ZDXH']]]];
                }
            }
        }

        return $basis;
    }

    /**
     * 出院诊断编码（5岁以上儿童出院诊断不应编为P00-P96（起源于围生期某些情况））
     * @param $data
     * @return array
     */
    public function rule91($data)
    {
        $AAA04 = !empty($data['data']['AAA04']) ? $data['data']['AAA04'] : 0;
        $basis = [];
        if ($AAA04 <= 5 || empty($data['diagnosis'])) {
            return $basis;
        }

        $str = 'P00,P01,P02,P03,P04,P05,P06,P07,P08,P09,P10,P11,P12,P13,P14,,P15,P16,P17,P18,P19,P20,P21,P22,P23,P24,P25,P26,P27,P28,P29,P30,P31,P32,P33,P34,P35,P36,P37,P38,P39,P40,P41,P42,P43,P44,P45,P46,P47,P48,P49,P50,P51,P52,P53,P54,P55,P56,P57,P58,P59,P60,P61,P62,P63,P64,P65,P66,P67,P68,P69,P70,P71,P72,P73,P74,P75,P76,P77,P78,P79,P80,P81,P82,P83,P84,P85,P86,P87,P88,P89,P90,P91,P92,P93,P94,P95,P96';
        $arr = explode(',',$str);
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $basis[] = ['desc'=>'5岁以上儿童不能应用编码【'.$diagnosis['ZDBM'].'】','location'=>['user'=>['AAA04'],'zd'=>[$diagnosis['ZDXH']]]];
                }
            }
        }

        return $basis;
    }

    /**
     * 出院诊断编码（女性出院诊断不应编"D29.1"(前列腺良性肿瘤)。）
     * @param $data
     * @return array
     */
    public function rule92($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        $basis = [];
        if ($AAA02C != 2 || empty($data['diagnosis'])) {
            return $basis;
        }

        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'D29.1') === 0) {
                $basis[] = ['desc'=>'女性出院诊断不应编【'.$diagnosis['ZDBM'].'】','location'=>['user'=>['AAA02C'],'zd'=>[$diagnosis['ZDXH']]]];
            }
        }

        return $basis;
    }

    /**
     * 女性出院诊断不应编"C60-C63"(男性生殖器官恶性肿瘤)。
     * @param $data
     * @return array
     */
    public function rule93($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        $basis = [];
        if ($AAA02C != 2 || empty($data['diagnosis'])) {
            return $basis;
        }

        $str = 'C60,C61,C62,C63';
        $arr = explode(',',$str);
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $basis[] = ['desc'=>'女性出院诊断不应编【'.$diagnosis['ZDBM'].'】','location'=>['user'=>['AAA02C'],'zd'=>[$diagnosis['ZDXH']]]];
                }
            }
        }

        return $basis;
    }

    /**
     * 女性出院诊断不应编"N40-N51"(男性生殖器官疾病)。
     * @param $data
     * @return array
     */
    public function rule94($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        $basis = [];
        if ($AAA02C != 2 || empty($data['diagnosis'])) {
            return $basis;
        }

        $str = 'N40,N41,N42,N43,N44,N45,N46,N47,N48,N49,N50,N51';
        $arr = explode(',',$str);
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $basis[] = ['desc'=>'女性出院诊断不应编【'.$diagnosis['ZDBM'].'】','location'=>['user'=>['AAA02C'],'zd'=>[$diagnosis['ZDXH']]]];
                }
            }
        }

        return $basis;
    }

    /**
     * 女性出院诊断不应编"K40"(腹股沟疝)。
     * @param $data
     * @return array
     */
    public function rule95($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        $basis = [];
        if ($AAA02C != 2 || empty($data['diagnosis'])) {
            return $basis;
        }

        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'K40') === 0) {
                $basis[] = ['desc'=>'女性出院诊断不应编【'.$diagnosis['ZDBM'].'】','location'=>['user'=>['AAA02C'],'zd'=>[$diagnosis['ZDXH']]]];
            }
        }

        return $basis;
    }

    /**
     * 男性出院诊断不应编"C51-C58"(女性生殖器官恶性肿瘤)。
     * @param $data
     * @return array
     */
    public function rule96($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        $basis = [];
        if ($AAA02C != 1 || empty($data['diagnosis'])) {
            return $basis;
        }

        $str = 'C51,C52,C53,C54,C55,C56,C57,C58';
        $arr = explode(',',$str);
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $basis[] = ['desc'=>'男性出院诊断不应编【'.$diagnosis['ZDBM'].'】','location'=>['user'=>['AAA02C'],'zd'=>[$diagnosis['ZDXH']]]];
                }
            }
        }

        return $basis;
    }

    /**
     * 男性出院诊断不应编"D06"(子宫颈原位恶性肿瘤)。
     * @param $data
     * @return array
     */
    public function rule97($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        $basis = [];
        if ($AAA02C != 1 || empty($data['diagnosis'])) {
            return $basis;
        }

        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'D06') === 0) {
                $basis[] = ['desc'=>'男性出院诊断不应编【'.$diagnosis['ZDBM'].'】','location'=>['user'=>['AAA02C'],'zd'=>[$diagnosis['ZDXH']]]];
            }
        }

        return $basis;
    }

    /**
     * 男性出院诊断不应编"D24"(乳房良性肿瘤)。
     * @param $data
     * @return array
     */
    public function rule98($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        $basis = [];
        if ($AAA02C != 1 || empty($data['diagnosis'])) {
            return $basis;
        }

        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'D24') === 0) {
                $basis[] = ['desc'=>'男性出院诊断不应编【'.$diagnosis['ZDBM'].'】','location'=>['user'=>['AAA02C'],'zd'=>[$diagnosis['ZDXH']]]];
            }
        }

        return $basis;
    }

    /**
     * 男性出院诊断不应编"D25"(子宫平滑肌瘤)。
     * @param $data
     * @return array
     */
    public function rule99($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        $basis = [];
        if ($AAA02C != 1 || empty($data['diagnosis'])) {
            return $basis;
        }

        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'D25') === 0) {
                $basis[] = ['desc'=>'男性出院诊断不应编【'.$diagnosis['ZDBM'].'】','location'=>['user'=>['AAA02C'],'zd'=>[$diagnosis['ZDXH']]]];
            }
        }

        return $basis;
    }

    /**
     * 男性出院诊断不应编"D27"(卵巢良性肿瘤)
     * @param $data
     * @return array
     */
    public function rule100($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        $basis = [];
        if ($AAA02C != 1 || empty($data['diagnosis'])) {
            return $basis;
        }

        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'D27') === 0) {
                $basis[] = ['desc'=>'男性出院诊断不应编【'.$diagnosis['ZDBM'].'】','location'=>['user'=>['AAA02C'],'zd'=>[$diagnosis['ZDXH']]]];
            }
        }

        return $basis;
    }

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

        $str = 'N70,N71,N72,N3,N74,N75,N76,N77';
        $arr = explode(',',$str);
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $basis[] = ['desc'=>'男性出院诊断不应编【'.$diagnosis['ZDBM'].'】','location'=>['user'=>['AAA02C'],'zd'=>[$diagnosis['ZDXH']]]];
                }
            }
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

        $str = 'N80,N81,N82,N83,N84,N85,N86,N87,N88,N89,N90,N91,N92,N93,N94,N95,N96,N97,N98';
        $arr = explode(',',$str);
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $basis[] = ['desc'=>'男性出院诊断不应编【'.$diagnosis['ZDBM'].'】','location'=>['user'=>['AAA02C'],'zd'=>[$diagnosis['ZDXH']]]];
                }
            }
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

        $str = 'O00,O01,O02,O03,O4,O05,O06,O07,O8,O09,O10,O11,O12,O13,O14,O15,O16,O17,O18,O19,O20,O21,O22,O23,O24,O25,O26,O27,O28,O29,O30,O31,O32,O33,O34,O35,O36,O37,O38,O39,O40,O41,O42,O43,O44,O45,O46,O47,O48,O49,O50,O51,O52,O53,O54,O55,O56,O57,O58,O59,O60,O61,O62,O63,O64,O65,O66,O67,O68,O69,O70,O71,O72,O73,O74,O75,O76,O77,O78,O79,O80,O81,O82,O83,O84,O85,O86,O87,O88,O89,O90,O91,O92,O93,O94,O95,O96,O97,O98,O99';
        $arr = explode(',',$str);
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $basis[] = ['desc'=>'男性出院诊断不应编【'.$diagnosis['ZDBM'].'】','location'=>['user'=>['AAA02C'],'zd'=>[$diagnosis['ZDXH']]]];
                }
            }
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
        $arr = explode(',',$str);
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if ($diagnosis['ZZPB']==1 && stripos($diagnosis['ZDBM'], $value) === 0) {
                    $basis[] = ['desc'=>'男性主要诊断编码不应编【'.$diagnosis['ZDBM'].'】','location'=>['user'=>['AAA02C'],'zd'=>[$diagnosis['ZDXH']]]];
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
        $arr = explode(',',$str);
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if ($diagnosis['ZZPB']==1 && stripos($diagnosis['ZDBM'], $value) === 0) {
                    $basis[] = ['desc'=>'女性主要诊断编码不应编【'.$diagnosis['ZDBM'].'】','location'=>['user'=>['AAA02C'],'zd'=>[$diagnosis['ZDXH']]]];
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
            $basis[] = ['desc'=>'出生地市不能小于两位','location'=>['user'=>['AAA10']]];
        } elseif (is_numeric($AAA10)) {
            $basis[] = ['desc'=>'出生地市不能全是数字','location'=>['user'=>['AAA10']]];
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
            $basis[] = ['desc'=>'出生地县不能小于两位','location'=>['user'=>['AAA11']]];
        } elseif (is_numeric($AAA11)) {
            $basis[] = ['desc'=>'出生地县不能全是数字','location'=>['user'=>['AAA11']]];
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
            $basis[] = ['desc'=>'籍贯市不能小于两位','location'=>['user'=>['AAA44']]];
        } elseif (is_numeric($AAA44)) {
            $basis[] = ['desc'=>'籍贯市不能全是数字','location'=>['user'=>['AAA44']]];
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
            $basis[] = ['desc'=>'户籍市不能小于两位','location'=>['user'=>['AAA46']]];
        } elseif (is_numeric($AAA46)) {
            $basis[] = ['desc'=>'户籍市不能全是数字','location'=>['user'=>['AAA46']]];
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
            $basis[] = ['desc'=>'户籍县不能小于两位','location'=>['user'=>['AAA47']]];
        } elseif (is_numeric($AAA47)) {
            $basis[] = ['desc'=>'户籍县不能全是数字','location'=>['user'=>['AAA47']]];
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
            $basis[] = ['desc'=>'户籍详细地址不能小于两位','location'=>['user'=>['AAA12']]];
        } elseif (is_numeric($AAA12)) {
            $basis[] = ['desc'=>'户籍详细地址不能全是数字','location'=>['user'=>['AAA12']]];
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
            $basis[] = ['desc'=>'现住址市不能小于两位','location'=>['user'=>['AAA49']]];
        } elseif (is_numeric($AAA49)) {
            $basis[] = ['desc'=>'现住址市不能全是数字','location'=>['user'=>['AAA49']]];
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
            $basis[] = ['desc'=>'现住址县不能小于两位','location'=>['user'=>['AAA50']]];
        } elseif (is_numeric($AAA50)) {
            $basis[] = ['desc'=>'现住址县不能全是数字','location'=>['user'=>['AAA50']]];
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
            $basis[] = ['desc'=>'现住址详细地址不能小于两位','location'=>['user'=>['AAA15']]];
        } elseif (is_numeric($AAA15)) {
            $basis[] = ['desc'=>'现住址详细地址不能全是数字','location'=>['user'=>['AAA15']]];
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
        $arr = explode(',',$str);
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if ($diagnosis['ZZPB']==1 && stripos($diagnosis['ZDBM'], $value) === 0) {
                    $basis[] = ['desc'=>'主要诊断编码不应编【'.$diagnosis['ZDBM'].'】','location'=>['zd'=>[$diagnosis['ZDXH']]]];
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
        if (empty(trim($data['data']['AAC03']))) {
            $basis[] = ['desc'=>'出院病房不能为空','location'=>['user'=>['AAC03']]];
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

        $str = 'I50.905,I50.904,I50.903,I50.902,I50.900x023,I50.900x016,I50.900x015,I50.900x014,I50.900x010,I50.900x009,I50.900x008,I50.900x007,I50.900x002';
        $arr = explode(',',$str);
        foreach ($arr as $value) {
            foreach ($data['diagnosis'] as $diagnosis) {
                if ($diagnosis['ZZPB']==1 && stripos($diagnosis['ZDBM'], $value) === 0) {
                    $basis[] = ['desc'=>'【'.$diagnosis['ZDBM'].'】不能作为主要诊断','location'=>['zd'=>[$diagnosis['ZDXH']]]];
                }
            }
        }

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
            if ($diagnosis['ZZPB']==1) {
                $zyzdArr = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            } else {
                $qtzdArr[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            }
        }

        if (empty($zyzdArr)) {
            return $basis;
        }

        foreach ($qtzdArr as $value) {
            if ($zyzdArr['ZDBM'] == $value['ZDBM']) {
                $basis[] = ['desc'=>'主要诊断编码【'.$zyzdArr['ZDBM'].'】与其他诊断编码【'.$value['ZDBM'].'】重复','location'=>['zd'=>[$zyzdArr['ZDXH'],$value['ZDXH']]]];
            }
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
                $qtzdArr[$diagnosis['ZDBM']][] = $diagnosis['ZDXH'];
            }
        }

        foreach ($qtzdArr as $ZDBM => $value) {
            if (count($value) > 1) {
                $basis[] = ['desc'=>'其他诊断编码【'.$ZDBM.'】重复','location'=>['zd'=>$value]];
            }
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

        $str = 'B05.0,B05.1,B05.2,B05.3,B05.4,B05.5,B05.6,B05.7,B05.1,B05.8';
        $arr = explode(',',$str);
        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'],'B05.9') === 0) {
                $res2[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            } else {
                foreach ($arr as $value) {
                    if (stripos($diagnosis['ZDBM'],$value) === 0) {
                        $res1[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
                    }
                }
            }
        }

        if ($res1 && $res2) {
            foreach ($res1 as $value) {
                foreach ($res2 as $val) {
                    $basis[] = ['desc'=>'诊断编码【'.$val['ZDBM'].'】与【'.$value['ZDBM'].'】不能同时存在','location'=>['zd'=>[$value['ZDXH'],$val['ZDXH']]]];
                }
            }
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
            if (stripos($diagnosis['ZDBM'],'H33.0') === 0) {
                $res1[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            } elseif (stripos($diagnosis['ZDBM'],'H33.3') === 0) {
                $res2[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            }
        }

        if ($res1 && $res2) {
            foreach ($res1 as $value) {
                foreach ($res2 as $val) {
                    $basis[] = ['desc'=>'诊断编码【'.$val['ZDBM'].'】与【'.$value['ZDBM'].'】不能同时存在','location'=>['zd'=>[$value['ZDXH'],$val['ZDXH']]]];
                }
            }
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
            if (stripos($diagnosis['ZDBM'],'K35.0') === 0) {
                $res1[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            } elseif (stripos($diagnosis['ZDBM'],'K35.9') === 0) {
                $res2[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            }
        }

        if ($res1 && $res2) {
            foreach ($res1 as $value) {
                foreach ($res2 as $val) {
                    $basis[] = ['desc'=>'诊断编码【'.$val['ZDBM'].'】与【'.$value['ZDBM'].'】不能同时存在','location'=>['zd'=>[$value['ZDXH'],$val['ZDXH']]]];
                }
            }
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
            if (stripos($diagnosis['ZDBM'],'K72') === 0) {
                $res1[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            } elseif (stripos($diagnosis['ZDBM'],'K70.4') === 0) {
                $res2[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            }
        }

        if ($res1 && $res2) {
            foreach ($res1 as $value) {
                foreach ($res2 as $val) {
                    $basis[] = ['desc'=>'诊断编码【'.$val['ZDBM'].'】与【'.$value['ZDBM'].'】不能同时存在','location'=>['zd'=>[$value['ZDXH'],$val['ZDXH']]]];
                }
            }
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

        $arr = ['B15','B16','B17','B18','B19'];
        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'],'K72') === 0) {
                $res1[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            } else {
                foreach ($arr as $value) {
                    if (stripos($diagnosis['ZDBM'],$value) === 0) {
                        $res2[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
                    }
                }
            }
        }

        if ($res1 && $res2) {
            foreach ($res1 as $value) {
                foreach ($res2 as $val) {
                    $basis[] = ['desc'=>'诊断编码【'.$val['ZDBM'].'】与【'.$value['ZDBM'].'】不能同时存在','location'=>['zd'=>[$value['ZDXH'],$val['ZDXH']]]];
                }
            }
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
        $arr = explode(',',$str);
        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'],'K80.2') === 0) {
                $res1[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            } else {
                foreach ($arr as $value) {
                    if (stripos($diagnosis['ZDBM'],$value) === 0) {
                        $res2[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
                    }
                }
            }
        }

        if ($res1 && $res2) {
            foreach ($res1 as $value) {
                foreach ($res2 as $val) {
                    $basis[] = ['desc'=>'诊断【'.$val['ZDBM'].'】+【'.$value['ZDBM'].'】应合并为【K80.1】','location'=>['zd'=>[$value['ZDXH'],$val['ZDXH']]]];
                }
            }
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
            if (stripos($diagnosis['ZDBM'],'K80.2') === 0) {
                $res1[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            } elseif (stripos($diagnosis['ZDBM'],'K81.0') === 0) {
                $res2[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            }
        }

        if ($res1 && $res2) {
            foreach ($res1 as $value) {
                foreach ($res2 as $val) {
                    $basis[] = ['desc'=>'诊断【'.$val['ZDBM'].'】+【'.$value['ZDBM'].'】应合并为【K80.0】','location'=>['zd'=>[$value['ZDXH'],$val['ZDXH']]]];
                }
            }
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
            if (stripos($diagnosis['ZDBM'],'K80.5') === 0) {
                $res1[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            } elseif (stripos($diagnosis['ZDBM'],'K81') === 0) {
                $res2[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            }
        }

        if ($res1 && $res2) {
            foreach ($res1 as $value) {
                foreach ($res2 as $val) {
                    $basis[] = ['desc'=>'诊断【'.$val['ZDBM'].'】+【'.$value['ZDBM'].'】应合并为【K80.4】','location'=>['zd'=>[$value['ZDXH'],$val['ZDXH']]]];
                }
            }
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
            if (stripos($diagnosis['ZDBM'],'K80.5') === 0) {
                $res1[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            } elseif (stripos($diagnosis['ZDBM'],'K83.0') === 0) {
                $res2[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            }
        }

        if ($res1 && $res2) {
            foreach ($res1 as $value) {
                foreach ($res2 as $val) {
                    $basis[] = ['desc'=>'诊断【'.$val['ZDBM'].'】+【'.$value['ZDBM'].'】应合并为【K80.3】','location'=>['zd'=>[$value['ZDXH'],$val['ZDXH']]]];
                }
            }
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
            if (stripos($diagnosis['ZDBM'],'I11') === 0) {
                $res1[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            } elseif (stripos($diagnosis['ZDBM'],'I12') === 0) {
                $res2[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            }
        }

        if ($res1 && $res2) {
            foreach ($res1 as $value) {
                foreach ($res2 as $val) {
                    $basis[] = ['desc'=>'诊断【'.$val['ZDBM'].'】+【'.$value['ZDBM'].'】应合并为【I13】','location'=>['zd'=>[$value['ZDXH'],$val['ZDXH']]]];
                }
            }
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
            if (stripos($diagnosis['ZDBM'],'J44.900') === 0) {
                $res1[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            } elseif (stripos($diagnosis['ZDBM'],'J18.9') === 0) {
                $res2[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            }
        }

        if ($res1 && $res2) {
            foreach ($res1 as $value) {
                foreach ($res2 as $val) {
                    $basis[] = ['desc'=>'诊断【'.$val['ZDBM'].'】+【'.$value['ZDBM'].'】应合并为【J44.000】','location'=>['zd'=>[$value['ZDXH'],$val['ZDXH']]]];
                }
            }
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
            if (stripos($diagnosis['ZDBM'],'J44.100') === 0) {
                $res1[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            } elseif (stripos($diagnosis['ZDBM'],'J42.x00') === 0) {
                $res2[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            } elseif (stripos($diagnosis['ZDBM'],'J43.904') === 0) {
                $res3[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
            }
        }

        if ($res1 && $res2) {
            foreach ($res1 as $value) {
                foreach ($res2 as $val) {
                    foreach ($res3 as $v) {
                        $basis[] = ['desc'=>'诊断【'.$val['ZDBM'].'】+【'.$value['ZDBM'].'】+【'.$v['ZDBM'].'】应合并为【J44.100x001】','location'=>['zd'=>[$value['ZDXH'],$val['ZDXH'],$v['ZDXH']]]];
                    }
                }
            }
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

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDMC'] == '2型糖尿病性肾病') {
                $res1 = ['ZDMC'=>$diagnosis['ZDMC'],'ZDXH'=>$diagnosis['ZDXH']];
            } elseif ($diagnosis['ZDMC'] == '慢性肾脏病2期') {
                $res2 = ['ZDMC'=>$diagnosis['ZDMC'],'ZDXH'=>$diagnosis['ZDXH']];
            }
        }

        if ($res1 && $res2) {
            $basis[] = ['desc'=>'诊断【'.$res1['ZDMC'].'】+【'.$res2['ZDMC'].'】应合并为【2型糖尿病肾病II期】','location'=>['zd'=>[$res1['ZDXH'],$res2['ZDXH']]]];
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

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDMC'] == '2型糖尿病') {
                $res1 = ['ZDMC'=>$diagnosis['ZDMC'],'ZDXH'=>$diagnosis['ZDXH']];
            } elseif ($diagnosis['ZDMC'] == '慢性肾衰竭尿毒症期') {
                $res2 = ['ZDMC'=>$diagnosis['ZDMC'],'ZDXH'=>$diagnosis['ZDXH']];
            }
        }

        if ($res1 && $res2) {
            $basis[] = ['desc'=>'诊断【'.$res1['ZDMC'].'】+【'.$res2['ZDMC'].'】应合并为【2型糖尿病肾病V期】','location'=>['zd'=>[$res1['ZDXH'],$res2['ZDXH']]]];
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

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDMC'] == '喉梗阻') {
                $res1 = ['ZDMC'=>$diagnosis['ZDMC'],'ZDXH'=>$diagnosis['ZDXH']];
            } elseif ($diagnosis['ZDMC'] == '急性喉炎') {
                $res2 = ['ZDMC'=>$diagnosis['ZDMC'],'ZDXH'=>$diagnosis['ZDXH']];
            }
        }

        if ($res1 && $res2) {
            $basis[] = ['desc'=>'诊断【'.$res1['ZDMC'].'】+【'.$res2['ZDMC'].'】应合并为【急性梗阻性喉炎[哮吼]】','location'=>['zd'=>[$res1['ZDXH'],$res2['ZDXH']]]];
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

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDMC'] == '急性喉炎') {
                $res1 = ['ZDMC'=>$diagnosis['ZDMC'],'ZDXH'=>$diagnosis['ZDXH']];
            } elseif ($diagnosis['ZDMC'] == '急性咽炎') {
                $res2 = ['ZDMC'=>$diagnosis['ZDMC'],'ZDXH'=>$diagnosis['ZDXH']];
            }
        }

        if ($res1 && $res2) {
            $basis[] = ['desc'=>'诊断【'.$res1['ZDMC'].'】+【'.$res2['ZDMC'].'】应合并为【急性咽喉炎】','location'=>['zd'=>[$res1['ZDXH'],$res2['ZDXH']]]];
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

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDMC'] == '扁桃体肥大') {
                $res1 = ['ZDMC'=>$diagnosis['ZDMC'],'ZDXH'=>$diagnosis['ZDXH']];
            } elseif ($diagnosis['ZDMC'] == '腺样体肥大') {
                $res2 = ['ZDMC'=>$diagnosis['ZDMC'],'ZDXH'=>$diagnosis['ZDXH']];
            }
        }

        if ($res1 && $res2) {
            $basis[] = ['desc'=>'诊断【'.$res1['ZDMC'].'】+【'.$res2['ZDMC'].'】应合并为【扁桃体肥大伴有腺样体肥大】','location'=>['zd'=>[$res1['ZDXH'],$res2['ZDXH']]]];
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

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDMC'] == '慢性支气管炎') {
                $res1 = ['ZDMC'=>$diagnosis['ZDMC'],'ZDXH'=>$diagnosis['ZDXH']];
            } elseif ($diagnosis['ZDMC'] == '肺气肿') {
                $res2 = ['ZDMC'=>$diagnosis['ZDMC'],'ZDXH'=>$diagnosis['ZDXH']];
            }
        }

        if ($res1 && $res2) {
            $basis[] = ['desc'=>'诊断【'.$res1['ZDMC'].'】+【'.$res2['ZDMC'].'】应合并为【慢性支气管炎伴肺气肿】','location'=>['zd'=>[$res1['ZDXH'],$res2['ZDXH']]]];
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

        $res1 = [];
        $res2 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDMC'] == '肺气肿') {
                $res1 = ['ZDMC'=>$diagnosis['ZDMC'],'ZDXH'=>$diagnosis['ZDXH']];
            } elseif ($diagnosis['ZDMC'] == '肺大疱') {
                $res2 = ['ZDMC'=>$diagnosis['ZDMC'],'ZDXH'=>$diagnosis['ZDXH']];
            }
        }

        if ($res1 && $res2) {
            $basis[] = ['desc'=>'诊断【'.$res1['ZDMC'].'】+【'.$res2['ZDMC'].'】应合并为【大疱性肺气肿】','location'=>['zd'=>[$res1['ZDXH'],$res2['ZDXH']]]];
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

        $res1 = [];
        $res2 = [];
        $res3 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2 && $res3) {
                break;
            }
            if ($diagnosis['ZDMC'] == '变应性鼻炎') {
                $res1 = ['ZDMC'=>$diagnosis['ZDMC'],'ZDXH'=>$diagnosis['ZDXH']];
            } elseif ($diagnosis['ZDMC'] == '咳嗽变异性哮喘') {
                $res1 = ['ZDMC'=>$diagnosis['ZDMC'],'ZDXH'=>$diagnosis['ZDXH']];
            } elseif ($diagnosis['ZDMC'] == '支气管哮喘') {
                $res1 = ['ZDMC'=>$diagnosis['ZDMC'],'ZDXH'=>$diagnosis['ZDXH']];
            }
        }

        if ($res1 && $res2 && $res3) {
            $basis[] = ['desc'=>'诊断【'.$res1['ZDMC'].'】+【'.$res2['ZDMC'].'】+【'.$res3['ZDMC'].'】应合并为【过敏性鼻炎伴哮喘】','location'=>['zd'=>[$res1['ZDXH'],$res2['ZDXH'],$res3['ZDXH']]]];
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

        $res1 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDBM'] == 'P07.000x001') {
                $res1 = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
                break;
            }
        }
        if ($res1 && ($data['data']['AEN01'] < 750 || $data['data']['AEN01'] > 999)) {
            $location = ['zd'=>[$res1['ZDXH']],'user'=>['AEN01']];
            $basis[] = ['desc'=>'有诊断编码【P07.000x001】新生儿体重范围应为【750-999g】','location'=>$location];
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

        $res1 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDBM'] == 'P07.100x003') {
                $res1 = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
                break;
            }
        }
        if ($res1 && ($data['data']['AEN01'] < 1250 || $data['data']['AEN01'] > 1499)) {
            $location = ['zd'=>[$res1['ZDXH']],'user'=>['AEN01']];
            $basis[] = ['desc'=>'有诊断编码【P07.100x003】新生儿体重范围应为【1250-1499g】','location'=>$location];
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

        $res1 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDBM'] == 'P07.100x002') {
                $res1 = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
                break;
            }
        }
        if ($res1 && ($data['data']['AEN01'] < 1000 || $data['data']['AEN01'] > 1249)) {
            $location = ['zd'=>[$res1['ZDXH']],'user'=>['AEN01']];
            $basis[] = ['desc'=>'有诊断编码【P07.100x002】新生儿体重范围应为【1000-1249g】','location'=>$location];
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

        $res1 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDBM'] == 'P07.100x004') {
                $res1 = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
                break;
            }
        }
        if ($res1 && ($data['data']['AEN01'] < 1500 || $data['data']['AEN01'] > 2499)) {
            $location = ['zd'=>[$res1['ZDXH']],'user'=>['AEN01']];
            $basis[] = ['desc'=>'有诊断编码【P07.100x004】新生儿体重范围应为【1500-2499】','location'=>$location];
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

        $res1 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDBM'] == 'P08.000') {
                $res1 = ['ZDBM'=>$diagnosis['ZDMC'],'ZDBM'=>$diagnosis['ZDXH']];
                break;
            }
        }
        if ($res1 && $data['data']['AEN01'] < 4500) {
            $location = ['zd'=>[$res1['ZDXH']],'user'=>['AEN01']];
            $basis[] = ['desc'=>'有诊断编码【P08.000】新生儿体重范围应为【4500g以上】','location'=>$location];
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

        $res1 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDBM'] == 'P08.000') {
                $res1 = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
                break;
            }
        }
        if ($res1 && $data['data']['AEN01'] < 4500) {
            $location = ['zd'=>[$res1['ZDXH']],'user'=>['AEN01']];
            $basis[] = ['desc'=>'有诊断编码【P08.000】，新生儿体重【4500g以下】，应更换诊断编码为【P08.100x001】','location'=>$location];
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
        $arr = explode(',',$str);
        $res1 = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            foreach ($arr as $value) {
                if (stripos($diagnosis['ZDBM'],$value) === 0) {
                    $res1[] = ['ZDBM'=>$diagnosis['ZDBM'],'ZDXH'=>$diagnosis['ZDXH']];
                }
            }
        }

        if ($res1 && $data['data']['AAA04'] > 17) {
            foreach ($res1 as $val) {
                $location = ['user'=>['AAA04'],'zd'=>[$val['ZDXH']]];
                $basis[] = ['desc'=>'有诊断编码【'.$val['ZDBM'].'】，年龄范围应为【0-17岁】','location'=>$location];
            }
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

        $arr = [];
        $ZDXH = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'],'I05') === 0) {
                $arr[] = $diagnosis['ZDBM'];
                $ZDXH[] = $diagnosis['ZDXH'];
            } elseif (stripos($diagnosis['ZDBM'],'I06') === 0) {
                $arr[] = $diagnosis['ZDBM'];
                $ZDXH[] = $diagnosis['ZDXH'];
            } elseif (stripos($diagnosis['ZDBM'],'I07') === 0) {
                $arr[] = $diagnosis['ZDBM'];
                $ZDXH[] = $diagnosis['ZDXH'];
            } elseif (stripos($diagnosis['ZDBM'],'I09') === 0) {
                $arr[] = $diagnosis['ZDBM'];
                $ZDXH[] = $diagnosis['ZDXH'];
            }
        }

        if (count($arr) >= 2) {
            $basis[] = ['desc'=>'诊断编码【'.implode('、',$arr).'】需合并到I08','location'=>['zd'=>$ZDXH]];
        }

        return $basis;
    }

    /**
     * 诊断中出现疾病诊断+肿瘤形态学诊断，更换诊断
     * @param $data
     * @return false|string
     */
    public function rule1397($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $str = 'C22.101M81600/3,C81.001M96590/3,C83.812M96730/3,C84.000M97000/3,C85.707M97190/3,C85.710M97160/3,C88.100M97620/3,C88.200M97630/3,C90.101M98310/1,C91.306M98341/3,C96.701M97271/3';
        $arr = explode(',',$str);
        $newArr = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (in_array($diagnosis['ZDBM'],$arr) && !in_array($diagnosis['ZDBM'],$newArr)) {
                $newArr[] = $diagnosis['ZDBM'];
            }
        }

        if (count($newArr) > 1) {
            return '诊断中出现疾病诊断+肿瘤形态学诊断，更换诊断';
        }

        return false;
    }

    /**
     * 手术操作不能同时存在：07.62垂体腺部分切除术，经蝶骨入路；07.14垂体腺活组织检查，经蝶骨入路
     * @param $data
     * @return false|string
     */
    public function rule1398($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $res1 = '';
        $res2 = '';
        foreach ($data['operation'] as $operation) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($operation['SSCZBM'], '07.62') === 0) {
                $res1 = $operation['SSCZBM'];
            } elseif (stripos($operation['SSCZBM'], '07.14') === 0) {
                $res2 = $operation['SSCZBM'];
            }
        }
        if ($res1 && $res2) {
            return '手术编码'.$res1.'垂体腺部分切除术与'.$res2.'垂体腺活组织检查，不能同时存在';
        }
        return false;
    }

    /**
     * 手术操作不能同时存在：07.63垂体腺部分切除术，未特指入路；07.15垂体腺活组织检查NOS
     * @param $data
     * @return false|string
     */
    public function rule1399($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $res1 = '';
        $res2 = '';
        foreach ($data['operation'] as $operation) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($operation['SSCZBM'], '07.63') === 0) {
                $res1 = $operation['SSCZBM'];
            } elseif (stripos($operation['SSCZBM'], '07.15') === 0) {
                $res2 = $operation['SSCZBM'];
            }
        }
        if ($res1 && $res2) {
            return '手术'.$res1.'垂体腺部分切除术与'.$res2.'垂体腺活组织检查NOS，不能同时存在';
        }
        return false;
    }

    /**
     * 手术操作不能同时存在：07.81胸腺部分切除术，开放性胸腺部分切除术；07.16胸腺活组织检查
     * @param $data
     * @return false|string
     */
    public function rule1400($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $res1 = '';
        $res2 = '';
        foreach ($data['operation'] as $operation) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($operation['SSCZBM'], '07.81') === 0) {
                $res1 = $operation['SSCZBM'];
            } elseif (stripos($operation['SSCZBM'], '07.16') === 0) {
                $res2 = $operation['SSCZBM'];
            }
        }
        if ($res1 && $res2) {
            return '手术'.$res1.'胸腺部分切除术与'.$res2.'胸腺活组织检查，不能同时存在';
        }
        return false;
    }

    /**
     * 手术操作不能同时存在：07.81胸腺部分切除术，开放性胸腺部分切除术；07.83胸腔镜下胸腺部分切除术
     * @param $data
     * @return false|string
     */
    public function rule1401($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $res1 = '';
        $res2 = '';
        foreach ($data['operation'] as $operation) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($operation['SSCZBM'], '07.81') === 0) {
                $res1 = $operation['SSCZBM'];
            } elseif (stripos($operation['SSCZBM'], '07.83') === 0) {
                $res2 = $operation['SSCZBM'];
            }
        }
        if ($res1 && $res2) {
            return '手术'.$res1.'胸腺部分切除术，开放性胸腺部分切除术与'.$res2.'胸腔镜下胸腺部分切除术，不能同时存在';
        }
        return false;
    }

    /**
     * 手术编码有13.0000去除晶状体异物NOS
     * 应更换手术为13.0100用磁吸法的去除晶状体异物/13.0200不使用磁吸法的去除晶状体异物/13.0201晶状体切开异物取出术
     * @param $data
     * @return false|string
     */
    public function rule1403($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $res1 = 0;
        foreach ($data['operation'] as $operation) {
            if ($operation['SSCZBM'] == '13.0000') {
                $res1 = 1;
                break;
            }
        }
        if ($res1) {
            return '手术编码有13.0000去除晶状体异物NOS，应更换手术为13.0100用磁吸法的去除晶状体异物/13.0200不使用磁吸法的去除晶状体异物/13.0201晶状体切开异物取出术';
        }
        return false;
    }

    /**
     * 手术编码有14.0000去除眼后节异物NOS
     * 应更换手术为14.0100用磁吸法去除眼后节异物/14.0101玻璃体异物磁吸术/14.0200不用磁吸法去除眼后节异物/14.0200x001眼后节异物去除术/14.0200x002玻璃体腔异物取出术/14.0201脉络膜切开异物取出术/14.0202后段眼球壁异物取出术
     * @param $data
     * @return false|string
     */
    public function rule1404($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $res1 = 0;
        foreach ($data['operation'] as $operation) {
            if ($operation['SSCZBM'] == '14.0000') {
                $res1 = 1;
            }
        }
        if ($res1) {
            return '手术编码有14.0000去除眼后节异物NOS，应更换手术为14.0100用磁吸法去除眼后节异物/14.0101玻璃体异物磁吸术/14.0200不用磁吸法去除眼后节异物/14.0200x001眼后节异物去除术/14.0200x002玻璃体腔异物取出术/14.0201脉络膜切开异物取出术/14.0202后段眼球壁异物取出术';
        }
        return false;
    }

    /**
     * 手术编码有37.80首次或置换永久起搏器置入，装置类型未特指，更换手术编码为37.81-37.89
     * @param $data
     * @return false|string
     */
    public function rule1405($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $res1 = '';
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['SSCZBM'],'37.80') === 0) {
                $res1 = $operation['SSCZBM'];
            }
        }
        if ($res1) {
            return '手术编码有'.$res1.'首次或置换永久起搏器置入，装置类型未特指，更换手术编码为37.81-37.89';
        }
        return false;
    }

    /**
     * 手术编码为79.2骨折开放性复位术不伴内固定，应更换手术编码为79.3骨折开放性复位术伴内固定
     * @param $data
     * @return false|string
     */
    public function rule1406($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $res1 = '';
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['SSCZBM'],'79.2') === 0) {
                $res1 = $operation['SSCZBM'];
            }
        }
        if ($res1) {
            return '手术编码为'.$res1.'骨折开放性复位术不伴内固定，应更换手术编码为79.3骨折开放性复位术伴内固定';
        }
        return false;
    }

    /**
     * 提示另编码包括：置入血管支架的数量00.45-00.48，治疗血管的数量00.40-00.43，入脑前血管经皮血管成形术00.61，颅外血管经皮粥样硬化切除术17.53，分支血管操作00.44
     * @param $data
     * @return false|string
     */
    public function rule1407($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $res1 = 0;
        foreach ($data['operation'] as $operation) {
            if ($operation['SSCZBM'] == '00.6300') {
                $res1 = 1;
            }
        }
        if ($res1) {
            return '手术编码有00.6300，应另编码：置入血管支架的数量00.45-00.48，治疗血管的数量00.40-00.43，入脑前血管经皮血管成形术00.61，颅外血管经皮粥样硬化切除术17.53，分支血管操作00.44';
        }
        return false;
    }

    /**
     * 提示另编码包括：非端对端吻合术（胸内或胸骨前食管吻合术及间置术）42.51-42.69，食管造口术42.10-42.19，胃造口术43.11-43.19
     * @param $data
     * @return false|string
     */
    public function rule1408($data)
    {
        if (empty($data['operation'])) {
            return false;
        }

        $res1 = 0;
        foreach ($data['operation'] as $operation) {
            if ($operation['SSCZBM'] == '42.4100') {
                $res1 = 1;
            }
        }
        if ($res1) {
            return '手术编码有42.4100，另编码：非端对端吻合术42.51-42.69，食管造口术42.10-42.19，胃造口术43.11-43.19';
        }
        return false;
    }

    /**
     * 提示另编码包括：非端对端的间置术或吻合术（胸内或胸骨前食管吻合术及间置术）42.51-42.69
     * @param $data
     * @return false|string
     */
    public function rule1409($data)
    {
        if (empty($data['operation'])) {
            return false;
        }

        $res1 = 0;
        foreach ($data['operation'] as $operation) {
            if ($operation['SSCZBM'] == '42.4200') {
                $res1 = 1;
            }
        }
        if ($res1) {
            return '手术编码有42.4200，应另编码42.51-42.69非端对端的间置术或吻合术';
        }
        return false;
    }

    /**
     * 手术编码需合并：12.5100x001前房角穿刺术+12.5200x001前房角切开术，需要合并到12.5300眼前房角切开伴眼前房角穿刺
     * @param $data
     * @return false|string
     */
    public function rule1410($data)
    {
        if (empty($data['operation'])) {
            return false;
        }

        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            if ($operation['SSCZBM'] == '12.5100x001') {
                $res1 = 1;
            } elseif ($operation['SSCZBM'] == '12.5200x001') {
                $res2 = 1;
            }
        }
        if ($res1 && $res2) {
            return '手术编码12.5100x001(前房角穿刺术)+12.5200x001(前房角切开术)，需要合并到12.5300(眼前房角切开伴眼前房角穿刺)';
        }
        return false;
    }

    /**
     * 手术编码需合并：14.7401后入路玻璃体切割术+14.7500x001玻璃体腔内替代物注射术，需要合并到14.7202后入路玻璃体切割术伴替代物注入
     * @param $data
     * @return false|string
     */
    public function rule1411($data)
    {
        if (empty($data['operation'])) {
            return false;
        }

        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            if ($operation['SSCZBM'] == '14.7401') {
                $res1 = 1;
            } elseif ($operation['SSCZBM'] == '14.7500x001') {
                $res2 = 1;
            }
        }
        if ($res1 && $res2) {
            return '手术编码14.7401(后入路玻璃体切割术)+14.7500x001(玻璃体腔内替代物注射术)，需要合并到14.7202(后入路玻璃体切割术伴替代物注入)';
        }
        return false;
    }

    /**
     * 男性出院手术不应编：65-71女性生殖器官手术
     * @param $data
     * @return false|string
     */
    public function rule1413($data)
    {
        if ($data['data']['AAA02C']!=1 || empty($data['operation'])) {
            return false;
        }
        $arr = ['65','66','67','68','69','70','71'];
        $res1 = 0;
        foreach ($data['operation'] as $operation) {
            if ($res1) {
                break;
            }
            foreach ($arr as $value) {
                if (stripos($operation['SSCZBM'],$value) === 0) {
                    $res1 = 1;
                    break;
                }
            }
        }
        if ($res1) {
            return '男性出院手术不应编：65-71(女性生殖器官手术)';
        }
        return false;
    }

    /**
     * 女性出院手术不应编：60-64男性生殖器官手术
     * @param $data
     * @return false|string
     */
    public function rule1414($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $arr = ['60','61','62','63','64'];
        $res1 = 0;
        foreach ($data['operation'] as $operation) {
            if ($res1) {
                break;
            }
            foreach ($arr as $value) {
                if (stripos($operation['SSCZBM'],$value) === 0) {
                    $res1 = 1;
                    break;
                }
            }
        }
        if ($res1 && $data['data']['AAA02C']==2) {
            return '女性出院手术不应编：60-64(男性生殖器官手术)';
        }
        return false;
    }

    /**
     * 17.3为腹腔镜大肠部分切除术，48.6为直肠其他切除术
     * @param $data
     * @return bool
     */
    public function rule1415($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $res1 = '';
        $res2 = '';
        foreach ($data['operation'] as $operation) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($operation['SSCZBM'], '17.3') === 0) {
                $res1 = $operation['SSCZBM'];
            } elseif (stripos($operation['SSCZBM'], '48.6') === 0) {
                $res2 = $operation['SSCZBM'];
            }
        }
        if ($res1 && $res2) {
            return true;
        }
        return false;
    }

    /**
     * 01.3为大脑和脑膜切开术，92.3为立体定向放射外科
     * @param $data
     * @return bool
     */
    public function rule1416($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $res1 = '';
        $res2 = '';
        foreach ($data['operation'] as $operation) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($operation['SSCZBM'], '01.3') === 0) {
                $res1 = $operation['SSCZBM'];
            } elseif (stripos($operation['SSCZBM'], '92.3') === 0) {
                $res2 = $operation['SSCZBM'];
            }
        }
        if ($res1 && $res2) {
            return true;
        }
        return false;
    }

    /**
     * 不能作为主要手术的编码范围：00.40-00.43手术血管的数量、00.45-00.48置入支架的数量、00.9其他操作和介入、00.74-00.77任何轴面类型
     * @param $data
     * @return false|string
     */
    public function rule1417($data)
    {
        if (empty($data['operation'])) {
            return false;
        }

        $str = '00.4000,00.4100,00.4200,00.4300,00.4301,00.4302,00.4500,00.4600,00.4700,00.4801,00.4802,00.9100,00.9200,00.9300,00.9400,00.7400,00.7500,00.7600,00.7601,00.7700';
        $arr = explode(',',$str);
        $SSCZBM = '';
        foreach ($data['operation'] as $operation) {
            if ($operation['SFZYSS'] == 1) {
                $SSCZBM = $operation['SSCZBM'];
            }
        }
        if (in_array($SSCZBM,$arr)) {
            return $SSCZBM.' 不能作为主要手术';
        }
        return false;
    }

    /**
     * 手术编码81.0为脊柱融合术，共有5个手术步骤。包括：
     * （1）81.0脊柱融合术
     * （2）84.51任何椎体融合装置置入
     * （3）84.52任何重组骨形态形成蛋白的植入
     * （4）77.70-77.79为移植进行的自身成熟骨切除术
     * （5）81.62-81.64融合椎体的数量
     * @param $data
     * @return bool
     */
    public function rule1418($data)
    {
        if (empty($data['operation'])) {
            return false;
        }

        $res = false;
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['SSCZBM'], '81.0') === 0) {
                $res = true;
                break;
            }
        }

        if ($res && count($data['operation'])<5) {
            return true;
        } elseif ($res) {
            $res1 = ''; $res2 = ''; $res3 = ''; $res4 = ''; $res5 = '';
            foreach ($data['operation'] as $operation) {
                if ($res1 && $res2 && $res3 && $res4 && $res5) {
                    break;
                }
                if (stripos($operation['SSCZBM'], '81.0') === 0) {
                    $res1 = $operation['SSCZBM'];
                } elseif (stripos($operation['SSCZBM'], '84.51') === 0) {
                    $res2 = $operation['SSCZBM'];
                } elseif (stripos($operation['SSCZBM'], '84.52') === 0) {
                    $res3 = $operation['SSCZBM'];
                } elseif (stripos($operation['SSCZBM'], '77.7') === 0) {
                    $arr = ['77.70','77.71','77.72','77.73','77.74','77.75','77.76','77.77','77.78','77.79'];
                    foreach ($arr as $val) {
                        if (stripos($operation['SSCZBM'], $val) === 0) {
                            $res4 = $operation['SSCZBM'];
                        }
                    }
                } elseif (stripos($operation['SSCZBM'], '81.62') === 0 || stripos($operation['SSCZBM'], '81.63') === 0 || stripos($operation['SSCZBM'], '81.64') === 0) {
                    $res5 = $operation['SSCZBM'];
                }
            }
            if (!$res1 || !$res2 || !$res3 || !$res4 || !$res5) {
                return true;
            }
        }

        return false;
    }

    /**
     * 是否有出院31日内再住院计划（病案首页是否有出院31天内再住院计划，如果选择无，目的不能为空格、-、文字。）
     * @param $data
     * @return false|string
     */
    public function rule1419($data)
    {
        if ($data['data']['AEM03C'] == 1 && mb_strlen(trim($data['data']['AEM04']))>0) {
            return '无31日内再住院计划，不应填写目的';
        }
        return false;
    }

    /**
     * 是否有出院31日内再住院计划（病案首页是否有出院31天内再住院计划，如果选择有，目的必填）
     * @param $data
     * @return false|string
     */
    public function rule1420($data)
    {
        if ($data['data']['AEM03C'] == 2 && strlen(trim($data['data']['AEM04']))<1) {
            return '有31日内再住院计划，目的必填';
        }
        return false;
    }

    /**
     * 手术编码（病案首页有手术操作，手术级别、手术类型、术者必填【术者信息没有】）
     * @param $data
     * @return false|string
     */
    public function rule1421($data)
    {
        if (empty($data['operation'])) {
            return false;
        }

        $arr = [];
        foreach ($data['operation'] as $operation) {
            if (in_array($operation['SSPB'],['手术','介入治疗'])) {
                $str = '手术：'.$operation['SSCZMC'];
                $str1 = '';
                if (empty($operation['SSCZBM'])) {
                    $str1 .= '、编码未填写';
                } elseif (empty($operation['SSJB'])) {
                    $str1 .= '、手术级别未填写';
                } elseif (empty($operation['SSLX'])) {
                    $str1 .= '、手术类型未填写';
                } elseif (empty($operation['SZXM'])) {
                    $str1 .= '、术者未填写';
                }
                if (!empty($str1)) {
                    $arr[] = $str.$str1;
                }
            }
        }
        if (!empty($arr)) {
            return implode(',', $arr);
        }
        return false;
    }

    /**
     * 病案首页有麻醉方式，麻醉医师必填
     * @param $data
     * @return false|string
     */
    public function rule1422($data)
    {
        if (empty($data['operation'])) {
            return false;
        }

        $res = 0;
        foreach ($data['operation'] as $operation) {
            if (!empty($operation['MZFS']) && empty($operation['MZYSXM'])) {
                $res = 1;
                break;
            }
        }
        if ($res) {
            return '有麻醉方式，麻醉医师未填写';
        }
        return false;
    }

    /**
     * 病案首页有麻醉医师，麻醉方式必填
     * @param $data
     * @return false|string
     */
    public function rule1423($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $res = 0;
        foreach ($data['operation'] as $operation) {
            if (!empty($operation['MZYSXM']) && empty($operation['MZFS'])) {
                $res = 1;
                break;
            }
        }
        if ($res) {
            return '有麻醉医师，麻醉方式未填写';
        }
        return false;
    }

    /**
     * 主要诊断编码（主要诊断为T88.6-T88.7，药物过敏应为有，且应填写过敏药物）
     * @param $data
     * @return false|string
     */
    public function rule1424($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }
        $arr = ['T88.6','T88.7'];
        $res = '';
        foreach ($arr as $value) {
            if ($res) {
                break;
            }
            foreach ($data['diagnosis'] as $diagnosis) {
                if ($diagnosis['ZZPB']==1 && stripos($diagnosis['ZDBM'], $value) === 0) {
                    $res = $diagnosis['ZDBM'];
                    break;
                }
            }
        }

        if ($res && ($data['data']['AEB02C']!=2 || empty($data['data']['AEB01']))) {
            return '主要诊断为T88.6-T88.7，药物过敏应为有，且应填写过敏药物';
        }

        return false;
    }

    /**
     * 主要诊断为C77-C79，病理诊断编码应为M****\/6
     * @param $data
     * @return false|string
     */
    public function rule1425($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }
        $arr = ['C77','C78','C79'];
        $res = false;
        foreach ($arr as $value) {
            if ($res) {
                break;
            }
            foreach ($data['diagnosis'] as $diagnosis) {
                if ($diagnosis['ZZPB']==1 && stripos($diagnosis['ZDBM'], $value) === 0) {
                    $res = true;
                    break;
                }
            }
        }
        $res = true;
        if (!$res) {
            return false;
        }
        $res2 = preg_match('/^[M](.*)[\/][6]$/', $data['data']['ABF01C']);
        if (!$res2) {
            return '主要诊断为C77-C79，病理诊断编码应为M****/6';
        }

        return false;
    }

    /**
     * 病案首页离院方式为 医嘱转院 和 医嘱转社区卫生服务机构/乡镇卫生院，拟接受医疗机构名称必填
     * @param $data
     * @return false|string
     */
    public function rule1427($data)
    {
        if (in_array($data['data']['AEM01C'],[2,3]) && $data['data']['ZA03']=='') {
            return '医嘱转院或医嘱转社区卫生服务机构/乡镇卫生院，拟接受医疗机构名称必填';
        }
        return false;
    }

    /**
     * 病案质量（病案首页病案质量为乙或丙，提示是否应修改为甲）
     * @param $data
     * @return false|string
     */
    public function rule1428($data)
    {
        if ($data['data']['AED01C'] == 2) {
            return '当前病案质量为：乙，是否应修改为甲';
        } elseif ($data['data']['AED01C'] == 3) {
            return '当前病案质量为：丙，是否应修改为甲';
        }
        return false;
    }

    /**
     * 年龄（病案首页年龄小于6岁，职业应选择其他）
     * @param $data
     * @return false|string
     */
    public function rule1429($data)
    {
        if (($data['data']['AAA04']=='' || $data['data']['AAA04']==null) && ($data['data']['AAA40']=='' || $data['data']['AAA40']==null)) {
            return false;
        }

        $AAA04 = !empty($data['data']['AAA04']) ? $data['data']['AAA04'] : 0;
        $AAA18C = !empty($data['data']['AAA18C']) ? $data['data']['AAA18C'] : '';
        if ($AAA04<6 && !in_array($AAA18C,[14,17])) {
            return '年龄小于6岁，职业应选择其他';
        }
        return false;
    }

    /**
     * 病案首页质控日期应大于等于出院时间
     * @param $data
     * @return false|string
     */
    public function rule1430($data)
    {
        $AED04 = date('Y-m-d', strtotime($data['data']['AED04']));
        $AAC01 = date('Y-m-d', strtotime($data['data']['AAC01']));
        if ($AED04 < $AAC01) {
            return '质控日期应大于等于出院时间';
        }
        return false;
    }

    /**
     * 身份号 男最后二位奇数，女最后二位是偶数
     * @param $data
     * @return false|string
     */
    public function rule1431($data)
    {
        $AAA07 = $data['data']['AAA07'];
        if (empty($AAA07)) {
            return false;
        }
        $str = mb_substr($AAA07,16,1);
        $AAA02C = $data['data']['AAA02C'];
        if ($str%2 === 0) {
            if ($AAA02C != 2) {
                return '身份号，倒数第二位是偶数，性别应为女';
            }
        } elseif ($AAA02C != 1) {
            return '身份号，倒数第二位是奇数，性别应为男';
        }
        return false;
    }

    /**
     * 收费中含“视网膜激光光凝术”手术名称无关键字“视网膜”
     * @param $data
     * @return false|string
     */
    public function rule1432($data)
    {
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return false;
        }
        $fymcList = array_unique(array_column($fymcArr,'FYMC'));
        $res1 = false;
        foreach ($fymcList as $fymc) {
            if (stripos($fymc,'视网膜激光光凝术') !== false) {
                $res1 = true;
                break;
            }
        }

        if ($res1) {
            $res2 = false;
            foreach ($data['operation'] as $operation) {
                if (stripos($operation['SSCZMC'],'视网膜') !== false) {
                    $res2 = true;
                    break;
                }
            }
            if (!$res2) {
                return '收费中含【视网膜激光光凝术】，手术名称无【视网膜】';
            }
        }

        return false;
    }

    /**
     * 收费明细 含【纤维支气管镜检查】 手术名称不含【纤维支气管镜检查】
     * @param $data
     * @return false|string
     */
    public function rule1433($data)
    {
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return false;
        }
        $fymcList = array_unique(array_column($fymcArr,'FYMC'));
        $res1 = false;
        foreach ($fymcList as $fymc) {
            if (stripos($fymc,'纤维支气管镜检查') !== false) {
                $res1 = true;
                break;
            }
        }

        if ($res1) {
            $res2 = false;
            foreach ($data['operation'] as $operation) {
                if (stripos($operation['SSCZMC'],'纤维支气管镜检查') !== false) {
                    $res2 = true;
                    break;
                }
            }
            if (!$res2) {
                return '收费明细含【纤维支气管镜检查】，手术名称无【纤维支气管镜检查】';
            }
        }

        return false;
    }

    /**
     * 收费明细 含【宫颈扩张术】手术名称不含【子宫颈扩张引产】
     * @param $data
     * @return false|string
     */
    public function rule1434($data)
    {
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return false;
        }
        $fymcList = array_unique(array_column($fymcArr,'FYMC'));
        $res1 = false;
        foreach ($fymcList as $fymc) {
            if (stripos($fymc,'宫颈扩张术') !== false) {
                $res1 = true;
                break;
            }
        }

        if ($res1) {
            $res2 = false;
            foreach ($data['operation'] as $operation) {
                if (stripos($operation['SSCZMC'],'子宫颈扩张引产') !== false) {
                    $res2 = true;
                    break;
                }
            }
            if (!$res2) {
                return '收费明细含【宫颈扩张术】，手术名称无【子宫颈扩张引产】';
            }
        }

        return false;
    }

    /**
     * 收费明细 含【急性缺血性脑卒中静脉溶栓治疗】 手术名称不含【脑动脉血栓溶解剂灌注】
     * @param $data
     * @return false|string
     */
    public function rule1435($data)
    {
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return false;
        }
        $fymcList = array_unique(array_column($fymcArr,'FYMC'));
        $res1 = false;
        foreach ($fymcList as $fymc) {
            if (stripos($fymc,'急性缺血性脑卒中静脉溶栓治疗') !== false) {
                $res1 = true;
                break;
            }
        }

        if ($res1) {
            $res2 = false;
            foreach ($data['operation'] as $operation) {
                if (stripos($operation['SSCZMC'],'脑动脉血栓溶解剂灌注') !== false) {
                    $res2 = true;
                    break;
                }
            }
            if (!$res2) {
                return '收费明细含【急性缺血性脑卒中静脉溶栓治疗】，手术名称无【脑动脉血栓溶解剂灌注】';
            }
        }

        return false;
    }

    /**
     * 收费明细 含【人工破膜术】手术名称不含【人工破膜引产】
     * @param $data
     * @return false|string
     */
    public function rule1436($data)
    {
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return false;
        }
        $fymcList = array_unique(array_column($fymcArr,'FYMC'));
        $res1 = false;
        foreach ($fymcList as $fymc) {
            if (stripos($fymc,'人工破膜术') !== false) {
                $res1 = true;
                break;
            }
        }

        if ($res1) {
            $res2 = false;
            foreach ($data['operation'] as $operation) {
                if (stripos($operation['SSCZMC'],'人工破膜引产') !== false) {
                    $res2 = true;
                    break;
                }
            }
            if (!$res2) {
                return '收费明细含【人工破膜术】，手术名称无【人工破膜引产】';
            }
        }

        return false;
    }

    /**
     * 收费明细 含【手取胎盘术】 手术名称不含【手取胎盘】
     * @param $data
     * @return false|string
     */
    public function rule1437($data)
    {
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return false;
        }
        $fymcList = array_unique(array_column($fymcArr,'FYMC'));
        $res1 = false;
        foreach ($fymcList as $fymc) {
            if (stripos($fymc,'手取胎盘术') !== false) {
                $res1 = true;
                break;
            }
        }

        if ($res1) {
            $res2 = false;
            foreach ($data['operation'] as $operation) {
                if (stripos($operation['SSCZMC'],'手取胎盘') !== false) {
                    $res2 = true;
                    break;
                }
            }
            if (!$res2) {
                return '收费明细含【手取胎盘术】，手术名称无【手取胎盘】';
            }
        }

        return false;
    }

    /**
     * 收费明细 含【无创呼吸机辅助呼吸，手术名称不含【无创呼吸机辅助通气】
     * @param $data
     * @return false|string
     */
    public function rule1438($data)
    {
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return false;
        }
        $fymcList = array_unique(array_column($fymcArr,'FYMC'));
        $res1 = false;
        foreach ($fymcList as $fymc) {
            if (stripos($fymc,'无创呼吸机辅助呼吸') !== false) {
                $res1 = true;
                break;
            }
        }

        if ($res1) {
            $res2 = false;
            foreach ($data['operation'] as $operation) {
                if (stripos($operation['SSCZMC'],'无创呼吸机辅助通气') !== false) {
                    $res2 = true;
                    break;
                }
            }
            if (!$res2) {
                return '收费明细含【无创呼吸机辅助呼吸】，手术名称无【无创呼吸机辅助通气】';
            }
        }

        return false;
    }

    /**
     * 收费明细 含【呼吸机辅助呼吸】 且 收费数量≥96，手术名称不含【呼吸机治疗[大于等于96小时]】
     * @param $data
     * @return false|string
     */
    public function rule1439($data)
    {
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return false;
        }

        $FYSL = 0;
        foreach ($fymcArr as $fyInfo) {
            if (stripos($fyInfo['FYMC'],'呼吸机辅助呼吸') !== false) {
                $FYSL += $fyInfo['FYSL'];
            }
        }

        if ($FYSL >= 96) {
            $res2 = false;
            foreach ($data['operation'] as $operation) {
                if (stripos($operation['SSCZMC'],'呼吸机治疗[大于等于96小时]') !== false) {
                    $res2 = true;
                    break;
                }
            }
            if (!$res2) {
                return '收费明细含【呼吸机辅助呼吸】且收费数量≥96，手术名称无【呼吸机治疗[大于等于96小时]】';
            }
        }

        return false;
    }

    /**
     * 收费明细含【呼吸机辅助呼吸】 且收费数量＜96，手术名称不含【呼吸机治疗[小于96小时]】
     * @param $data
     * @return false|string
     */
    public function rule1440($data)
    {
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return false;
        }

        $FYSL = 0;
        foreach ($fymcArr as $fyInfo) {
            if (stripos($fyInfo['FYMC'],'呼吸机辅助呼吸') !== false) {
                $FYSL += $fyInfo['FYSL'];
                break;
            }
        }

        if ($FYSL > 0 && $FYSL < 96) {
            $res2 = false;
            foreach ($data['operation'] as $operation) {
                if (stripos($operation['SSCZMC'],'呼吸机治疗[小于96小时]') !== false) {
                    $res2 = true;
                    break;
                }
            }
            if (!$res2) {
                return '收费明细含【呼吸机辅助呼吸】且收费数量＜96，手术名称不含【呼吸机治疗[小于96小时]】';
            }
        }

        return false;
    }

    /**
     * 收费明细含【呼吸机辅助呼吸】 ，有创呼吸机使用时间【不能为0，不能为空】
     * @param $data
     * @return false|string
     */
    public function rule1441($data)
    {
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return false;
        }

        $res1 = false;
        $FYSL = 0;
        foreach ($fymcArr as $fyInfo) {
            if (stripos($fyInfo['FYMC'],'呼吸机辅助呼吸') !== false) {
                $res1 = true;
                $FYSL += $fyInfo['FYSL'];
            }
        }

        if ($res1 && empty($data['data']['AEL01'])) {
            $days = floor($FYSL / 24); // 计算天数
            $remainingHours = $FYSL % 24;   // 计算剩余的小时数
            return '有创呼吸机实际使用时间【'.$days.'天'.$remainingHours.'小时】';
        }

        return false;
    }

    /**
     * 收费明细 含【体外人工膜肺(ECMO)】 手术名称不含【体外膜氧合[ECMO]】
     * @param $data
     * @return false|string
     */
    public function rule1442($data)
    {
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return false;
        }
        $fymcList = array_unique(array_column($fymcArr,'FYMC'));
        $res1 = false;
        foreach ($fymcList as $fymc) {
            if (stripos($fymc,'体外人工膜肺(ECMO)') !== false) {
                $res1 = true;
                break;
            }
        }

        if ($res1) {
            $res2 = false;
            foreach ($data['operation'] as $operation) {
                if (stripos($operation['SSCZMC'],'体外膜氧合[ECMO]') !== false) {
                    $res2 = true;
                    break;
                }
            }
            if (!$res2) {
                return '收费明细含【体外人工膜肺(ECMO)】，手术名称无【体外膜氧合[ECMO]】';
            }
        }

        return false;
    }

    /**
     * 诊断编码范围：N70 - N77或 Q50.401，应为女性病例
     * @param $data
     * @return false|string
     */
    public function rule1443($data)
    {
        $arr = ['N70','N71','N72','N73','N74','N75','N76','N77','Q50.401'];
        $res = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res) {
                break;
            }
            foreach ($arr as $value) {
                if (stripos($diagnosis['ZDBM'],$value) === 0) {
                    $res = true;
                    break;
                }
            }
        }

        if ($res && $data['data']['AAA02C'] != 2) {
            return '诊断编码范围：N70-N77或Q50.401，应为女性病例';
        }

        return $res;
    }

    /**
     * 新生儿病例，出院时天龄大于28天，出院诊断不能有P编码的诊断
     * @param $data
     * @return false|string
     */
    public function rule1444($data)
    {
        $AAA04 = !empty($data['data']['AAA04']) ? $data['data']['AAA04'] : 0;
        $AAA40 = !empty($data['data']['AAA40']) ? $data['data']['AAA40'] : 0;
        if ($AAA04 < 1 || $AAA40 < 28) {
            return false;
        }

        $res = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'P') === 0) {
                $res = true;
                break;
            }
        }

        if ($res) {
            return '出院天龄大于28天，出院诊断不能有P编码的诊断';
        }
        return false;
    }

    /**
     * 新生儿病例，出院时天龄小于28天，不能出现Z编码诊断
     * @param $data
     * @return false|string
     */
    public function rule1447($data)
    {
        $AAA04 = !empty($data['data']['AAA04']) ? $data['data']['AAA04'] : 0;
        $AAA40 = !empty($data['data']['AAA40']) ? $data['data']['AAA40'] : 0;
        if ($AAA04 > 0 || $AAA40 > 28) {
            return false;
        }

        $res = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'Z') === 0) {
                $res = true;
                break;
            }
        }

        if ($res) {
            return '出院天龄小于28天，出院诊断不能有Z编码诊断';
        }
        return false;
    }

    /**
     * 其他诊断编码为T81-T88，容易出现医疗事故的编码，请核对
     * @param $data
     * @return false|string
     */
    public function rule1448($data)
    {
        $arr = ['T81','T82','T83','T84','T85','T86','T87','T88'];
        $res = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res) {
                break;
            }
            foreach ($arr as $value) {
                if ($diagnosis['ZZPB'] != 1 && stripos($diagnosis['ZDBM'], $value) === 0) {
                    $res = true;
                    break;
                }
            }
        }

        if ($res) {
            return '其他诊断编码为T81-T88，容易出现医疗事故的编码，请核对';
        }
        return false;
    }

    /**
     * 含“小儿肠炎”，年龄要小于2岁
     * @param $data
     * @return false|string
     */
    public function rule1449($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDMC'], '小儿肠炎') !== false) {
                $res = true;
                break;
            }
        }

        if ($res) {
            $AAA04 = !empty($data['data']['AAA04']) ? $data['data']['AAA04'] : 0;
            if ($AAA04 >= 2) {
                return '含“小儿肠炎”，年龄要小于2岁';
            }
        }

        return false;
    }

    /**
     * K83.1梗阻性黄疸和K80胆结石，不能同时存在
     * @param $data
     * @return false|string
     */
    public function rule1450($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = false;
        $res2 = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($diagnosis['ZDBM'], 'K83.1') === 0) {
                $res1 = true;
            } elseif (stripos($diagnosis['ZDBM'], 'K80') === 0) {
                $res2 = true;
            }
        }

        if ($res1 && $res2) {
            return 'K83.1梗阻性黄疸和K80胆结石，不能同时存在';
        }

        return false;
    }

    /**
     * 身份证与出生日期要对应
     * @param $data
     * @return false|string
     */
    public function rule1451($data)
    {
        $AAA07 = !empty($data['data']['AAA07']) ? mb_substr($data['data']['AAA07'],6,8) : '';
        $AAA03 = !empty($data['data']['AAA03']) ? date("Ymd",strtotime($data['data']['AAA03'])) : '';
        if (empty($AAA07) || empty($AAA03)) {
            return false;
        }
        if ($AAA07 != $AAA03) {
            return '身份证号与出生日期不对应';
        }
        return false;
    }

    /**
     * 护理天数之和要等于住院天数
     * @param $data
     * @return false|string
     */
    public function rule1452($data)
    {
        // 住院天数
        $AAC04 = !empty($data['data']['AAC04']) ? $data['data']['AAC04'] : 0;

        // 特技护理、一级护理、二级护理、三级护理 之和
        $TJHLTS = !empty($data['data']['TJHLTS']) ? $data['data']['TJHLTS'] : 0;
        $YJHLTS = !empty($data['data']['YJHLTS']) ? $data['data']['YJHLTS'] : 0;
        $EJHLTS = !empty($data['data']['EJHLTS']) ? $data['data']['EJHLTS'] : 0;
        $SJHLTS = !empty($data['data']['SJHLTS']) ? $data['data']['SJHLTS'] : 0;
        $HLTS_SUM = $TJHLTS+$YJHLTS+$EJHLTS+$SJHLTS;

        if ($AAC04 != $HLTS_SUM) {
            return '护理天数之和不等于住院天数';
        }
        return false;
    }

    /**
     * 入院病情有,诊断时误填或漏填
     * @param $data
     * @return false|string
     */
    public function rule1453($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $ABC03C = config('dictionaries.ABC03C');
        $res = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if (!empty($diagnosis['ZDBM']) && !in_array($diagnosis['RYQK'],$ABC03C)) {
                $res = true;
                break;
            }
        }

        if ($res) {
            return '入院情况未填写';
        }
        return false;
    }

    /**
     * 手术编码有96.7101，有创呼吸机使用时间应小于96小时
     * @param $data
     * @return false|string
     */
    public function rule1454($data)
    {
        if (empty($data['operation'])) {
            return false;
        }

        $res = false;
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['SSCZBM'],'96.7101') === 0) {
                $res = true;
                break;
            }
        }

        $AEL01 = !empty($data['data']['AEL01']) ? $data['data']['AEL01'] : 0;
        if ($res && $AEL01>=96) {
            return '手术编码有96.7101，有创呼吸机使用时间应小于96小时';
        }

        return false;
    }

    /**
     * 手术编码有96.7201，有创呼吸机使用时间应大于等于96小时
     * @param $data
     * @return false|string
     */
    public function rule1455($data)
    {
        if (empty($data['operation'])) {
            return false;
        }

        $res = false;
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['SSCZBM'],'96.7201') === 0) {
                $res = true;
                break;
            }
        }

        $AEL01 = !empty($data['data']['AEL01']) ? $data['data']['AEL01'] : 0;
        if ($res && $AEL01<96) {
            return '手术编码有96.7201，有创呼吸机使用时间应大于等于96小时';
        }

        return false;
    }

    /**
     * 一级切口，愈合类别不能为丙级
     * @param $data
     * @return false|string
     */
    public function rule1456($data)
    {
        if (empty($data['operation'])) {
            return false;
        }

        $res = false;
        foreach ($data['operation'] as $operation) {
            if ($operation['QKDJ']==1 && $operation['YHDJ']==3) {
                $res = true;
                break;
            }
        }

        if ($res) {
            return '手术一级切口，愈合类别不能为丙级';
        }
        return false;
    }

    /**
     * 诊断编码出现S00-S09，颅内损伤昏迷时间6个空必填一个数字
     * @param $data
     * @return false|string
     */
    public function rule1457($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $arr = ['S00','S01','S02','S03','S04','S05','S06','S07','S08','S09'];
        $res = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res) {
                break;
            }
            foreach ($arr as $value) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $res = true;
                    break;
                }
            }
        }

        if ($res) {
            if (!is_numeric($data['data']['AEJ01']) && !is_numeric($data['data']['AEJ02']) && !is_numeric($data['data']['AEJ03']) && !is_numeric($data['data']['AEJ04']) && !is_numeric($data['data']['AEJ05']) && !is_numeric($data['data']['AEJ06'])) {
                return '诊断编码出现S00-S09，颅内损伤昏迷时间不能全部为空';
            }
        }

        return false;
    }

    /**
     * 无效的主要诊断编码
     * @param $data
     * @return false|string
     */
    public function rule1458($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $JBDM = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $JBDM = $diagnosis['ZDBM'];
                break;
            }
        }
        if (!$JBDM) {
            return false;
        }
        $str = "T92.600x002:创伤性手指缺如|T90.501:陈旧性颅脑损伤|T75:陈旧性跟骨骨折|R65|B95:病原体的附加编码|B96:病原体的附加编码|B97:病原体的附加编码|T30:烧伤部位面积未特指|T31:烧伤部位面积未特指|T32:腐蚀上的面积编码|Z33:单纯妊娠状态|Z37:分娩结局 活产儿分娩地点|Z38:分娩结局 活产儿分娩地点|Z53:由于XX原因 治疗未实施|Z80:家族史|Z81:家族史|Z82:家族史|Z83:家族史|Z84:家族史|Z85:恶行肿瘤个人史|Z86:其他疾病个人史|Z87:其他疾病个人史|Z88:药物 生物制剂过敏史|Z89:肢体 器官后天缺失|Z90:肢体 器官后天缺失|Z91:危险因素个人史|Z92:医疗个人史|Z93:单纯人工造口状态|Z94:组织和器官移植状态|Z95:具有心脏 血管的植入物和移植物|Z96:具有其它功能性植入物和装置|Z97:具有其它功能性植入物和装置|Z98:其它单纯的手术后状态|Z99:依赖于可启动装置和机器|U80:耐药菌感染|U81:耐药菌感染|U82:耐药菌感染|U83:耐药菌感染|U84:耐药菌感染|U85:耐药菌感染|U86";
        $arr = explode('|', $str);
        $res = '';
        foreach ($arr as $val) {
            $zdbm = explode(":",$val);
            if (stripos($JBDM, $zdbm[0]) === 0) {
                $res = $zdbm[0];
                if (!empty($zdbm[1])) {
                    $res .= '（'.$zdbm[1].'）';
                } else {
                    $res .= ' ';
                }
                $res .= '无效的诊断编码';
                break;
            }
        }

        if ($res) {
            return $res;
        }
        return false;
    }

    /**
     * 离院方式等于2或3时，接收医疗机构名称不能为空
     * @param $data
     * @return false|string
     */
    public function rule1459($data)
    {
        $AEM01C = config('dictionaries.AEM01C');
        if (in_array($data['data']['AEM01C'],[2,3]) && empty($AEM01C[$data['data']['AEM02']])) {
            return '离院方式是 医嘱转院或医嘱转社区卫生服务机构/乡镇卫生院，接收医疗机构名称不能为空';
        }
        return false;
    }

    /**
     * 临床路径选1，完成情况必填
     * @param $data
     * @return bool
     */
    public function rule1461($data)
    {
        $LCLJ = !empty($data['other']['LCLJ']) ? $data['other']['LCLJ'] : '';
        $WCQK = !empty($data['other']['WCQK']) ? $data['other']['WCQK'] : '';

        if ($LCLJ == 1 && mb_strlen($WCQK) < 1) {
            return true;
        }

        return false;
    }

    /**
     * 临床路径选1，变异情况必填
     * @param $data
     * @return bool
     */
    public function rule1462($data)
    {
        $LCLJ = !empty($data['other']['LCLJ']) ? $data['other']['LCLJ'] : '';
        $BYQK = !empty($data['other']['BYQK']) ? $data['other']['BYQK'] : '';

        if ($LCLJ == 1 && mb_strlen($BYQK) < 1) {
            return true;
        }

        return false;
    }

    /**
     * 手术操作名称【有】术者【有】【长度2-40】【不能全是数字】
     * @param $data
     * @return false|string
     */
    public function rule1463($data)
    {
        if (empty($data['operation'])) {
            return false;
        }

        $res = false;
        foreach ($data['operation'] as $operation) {
            if (!empty($operation['SSCZMC']) && (is_numeric($operation['SZXM']) || mb_strlen($operation['SZXM'])<2 || mb_strlen($operation['SZXM'])>40)) {
                $res = true;
                break;
            }
        }

        if ($res) {
            return '术者填写错误';
        }
        return false;
    }

    /**
     * 不能全部是【4】
     * @param $data
     * @return false|string
     */
    public function rule1464($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $count1 = 0;
        $count2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $count1++;
            if ($diagnosis['RYQK'] == '无') {
                $count2++;
            }
        }
        if ($count1 == $count2) {
            return '入院情况不能全是【无】';
        }
        return false;
    }

    /**
     * 证件类别【必填1、2、3、4、5、6、9】
     * @param $data
     * @return bool
     */
    public function rule1465($data)
    {
        if (empty($data['other']['ZJLB'])) {
            return '证件类别未填写';
        }
        return false;
    }

    /**
     * Z37、O80-O84、o26.9应同时存在
     * @param $data
     * @return false|string
     */
    public function rule1467($data)
    {
        if (empty($data['diagnosis']) || $data['data']['AAC11N']!='产科') {
            return false;
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
            ['term' => ['JZHM' => $data['MED_REC_ID']]],
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
            return false;
        }

        $Z37 = 0; $O8 = 0; $O26 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if (stripos($ICD10_ID1, 'Z37')!==false) {
                $Z37 = 1;
            } elseif (stripos($ICD10_ID1, 'O26.9')!==false) {
                $O26 = 1;
            } else if (stripos($ICD10_ID1, 'O80')!==false || stripos($ICD10_ID1, 'O81')!==false || stripos($ICD10_ID1, 'O82')!==false || stripos($ICD10_ID1, 'O83')!==false || stripos($ICD10_ID1, 'O84')!==false) {
                $O8 = 1;
            }
        }

        $sumScore = $Z37+$O8+$O26;
        if (!in_array($sumScore, [0,3])) {
            return '诊断编码：Z37、O80-O84、O26.9应同时存在';
        }

        return false;
    }

    /**
     * 出院科室是【产科】，Z37码段不能重复编码
     * @param $data
     * @return false|string
     */
    public function rule1468($data)
    {
        if ($data['data']['AAC11N']!='产科') {
            return false;
        }

        $count = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if (stripos($ICD10_ID1, 'Z37')!==false) {
                $count++;
            }
        }

        if ($count > 1) {
            return '出院科室是【产科】，Z37码段不能重复编码';
        }
        return false;
    }

    /**
     * 出院诊断:有【K56.700 肠梗阻】和【K66.002 肠粘连】应合并编码为【K56.500x003 粘连性肠梗阻】
     * @param $data
     * @return false|string
     */
    public function rule1469($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if ($ICD10_ID1 == 'K56.700') {
                $res1 = 1;
            } elseif ($ICD10_ID1 == 'K66.002') {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return '诊断编码【K56.700】和【K66.002】应合并编码为【K56.500x003】';
        }
        return false;
    }

    /**
     * 出院诊断:【S52.500x001 桡骨远端骨折】和【S52.802 尺骨茎突骨折】应合并编码为【S52.600x002 尺骨茎突骨折伴桡骨远端骨折】
     * @param $data
     * @return false|string
     */
    public function rule1470($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if ($ICD10_ID1 == 'S52.500x001') {
                $res1 = 1;
            } elseif ($ICD10_ID1 == 'S52.802') {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return '诊断编码【S52.500x001】和【S52.802】应合并编码为【S52.600x002】';
        }
        return false;
    }

    /**
     * 出院诊断:【I50.101 急性左心衰竭】和【J81.x00 肺水肿】应合并编码为【I50.103 左心衰竭合并肺水肿】
     * @param $data
     * @return false|string
     */
    public function rule1471($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if ($ICD10_ID1 == 'I50.101') {
                $res1 = 1;
            } elseif ($ICD10_ID1 == 'J81.x00') {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return '诊断编码【I50.101】和【J81.x00】应合并编码为【I50.103】';
        }
        return false;
    }

    /**
     * 出院诊断:【M51.202 腰椎间盘突出】和【M54.300 坐骨神经痛】应合并编码为【M51.101+ 腰椎间盘脱出伴坐骨神经痛】
     * @param $data
     * @return false|string
     */
    public function rule1472($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if ($ICD10_ID1 == 'M51.202') {
                $res1 = 1;
            } elseif ($ICD10_ID1 == 'J81.x00') {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return '诊断编码【M51.202】和【M54.300】应合并编码为【M51.101+】';
        }
        return false;
    }

    /**
     * 出院诊断:【H02.003 睑内翻】和【H02.004 倒睫】应合并编码为【H02.000 睑内翻和倒睫】
     * @param $data
     * @return false|string
     */
    public function rule1473($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if ($ICD10_ID1 == 'H02.003') {
                $res1 = 1;
            } elseif ($ICD10_ID1 == 'H02.004') {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return '诊断编码【H02.003】和【H02.004】应合并编码为【H02.000】';
        }
        return false;
    }

    /**
     * 【食管静脉曲张】或 【胃底静脉曲张】和【肝硬化】应合并为【肝硬化伴食管静脉曲张】或【肝硬化伴胃底静脉曲张】
     * @param $data
     * @return false|string
     */
    public function rule1474($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if ($ICD10_ID1 == 'I86.400x001' || $ICD10_ID1 == 'I85.900x001') {
                $res1 = 1;
            } elseif ($ICD10_ID1 == 'K74.100') {
                $res2 = 1;
            }
        }
        if ($res1 && $res2) {
            return '诊断【食管静脉曲张】或 【胃底静脉曲张】和【肝硬化】应合并为【肝硬化伴食管静脉曲张】或【肝硬化伴胃底静脉曲张】';
        }

        return false;
    }

    /**
     * 联系人关系不合理
     * @param $data
     * @return false|string
     */
    public function rule1476($data)
    {
        $AAA23C = !empty($data['data']['AAA23C']) ? $data['data']['AAA23C'] : '';
        if ($data['data']['AAA04'] < 20 && in_array($AAA23C, [2,3])) {
            return '年龄小于20岁，联系人关系不能是【子和女】';
        }
        return false;
    }

    /**
     * 【出院代码=213（产科），诊断编码：Z37，新生儿出生体重必填】
     * 【出院代码=287（新生儿科、儿童重症医学科），新生儿出生体重必填】
     * 体重区间【100克-9999克】
     * @param $data
     * @return false|string
     */
    public function rule1477($data)
    {
        $res = preg_match("/^[1-9][0-9]{2,3}$/", $data['data']['AEN01']);
        if ($data['data']['AAC11C'] == 213) {
            $ICD10_ID1 = '';
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], 'Z37') === 0) {
                    $ICD10_ID1 = $diagnosis['ZDBM'];
                    break;
                }
            }

            if (!empty($ICD10_ID1) && !$res) {
                return '新生儿出生体重填写错误';
            }
        } elseif ($data['data']['AAC11C'] == 287 && !$res) {
            return '新生儿出生体重填写错误';
        }

        return false;
    }

    /**
     * 首页收费【麻醉费】，麻醉方式不能为空
     * @param $data
     * @return false|string
     */
    public function rule1478($data)
    {
        // 麻醉费
        if (empty($data['other']['MZF'])) {
            return false;
        }

        $res = false;
        if (!empty($data['operation'])) {
            foreach ($data['operation'] as $operation) {
                if (!empty($operation['SSCZMC']) && empty($operation['MZFS'])) {
                    $res = true;
                    break;
                }
            }
        }

        if ($res) {
            return '收费中有【麻醉费】，麻醉方式不能为空';
        }
        return false;
    }

    /**
     *  出院诊断:【E14】与【E11或 E10】不能同时存在
     * @param $data
     * @return false|string
     */
    public function rule1479($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if (stripos($ICD10_ID1, 'E14') === 0) {
                $res1 = 1;
            } elseif (stripos($ICD10_ID1, 'E11') === 0 || stripos($ICD10_ID1, 'E10') === 0) {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return '诊断编码【E14】与【E11或E10】不能同时存在';
        }
        return false;
    }

    /**
     * 【I25.103 冠状动脉粥样硬化性心脏病】或【I20.000不稳定型心绞痛】与【I21急性心肌梗死】同时存在，
     * 【I25.103 】或【I20.000】不能作为主要诊断
     * @param $data
     * @return false|string
     */
    public function rule1481($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }
        $res1 = 0;
        $res2 = 0;
        $ZYZD = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $ZYZD = $diagnosis['ZDBM'];
            }
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if ($ICD10_ID1 == 'I25.103' || $ICD10_ID1 == 'I20.000') {
                $res1 = 1;
            } elseif (stripos($ICD10_ID1,'I21') === 0) {
                $res2 = 1;
            }
        }
        if ($res1 && $res2 && in_array($ZYZD,['I25.103','I20.000'])) {
            return '【I25.103】或【I20.000】与【I21】同时存在，【I25.103】或【I20.000】不能作为主要诊断';
        }

        return false;
    }

    /**
     * 出院诊断:【E10.9】与【E10.0-E10.8】不能同时存在
     * 出院诊断:【E11.9】与【E11.0-E11.8】不能同时存在
     * @param $data
     * @return false|string
     */
    public function rule1482($data)
    {
        $arr1 = ['E10.0','E10.1','E10.2','E10.3','E10.4','E10.5','E10.6','E10.7','E10.8'];
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if (stripos($ICD10_ID1, 'E10.9') === 0) {
                $res1 = 1;
            } elseif (stripos($ICD10_ID1, 'E10') === 0) {
                foreach ($arr1 as $val) {
                    if (stripos($ICD10_ID1, $val) === 0) {
                        $res2 = 1;
                        break;
                    }
                }
            }
        }
        if ($res1 && $res2) {
            return '诊断编码【E10.9】与【E10.0-E10.8】不能同时存在';
        }

        $arr2 = ['E11.0','E11.1','E11.2','E11.3','E11.4','E11.5','E11.6','E11.7','E11.8'];
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if (stripos($ICD10_ID1, 'E11.9') === 0) {
                $res1 = 1;
            } elseif (stripos($ICD10_ID1, 'E11') === 0) {
                foreach ($arr2 as $val) {
                    if (stripos($ICD10_ID1, $val) === 0) {
                        $res2 = 1;
                        break;
                    }
                }
            }
        }
        if ($res1 && $res2) {
            return '诊断编码【E11.9】与【E11.0-E11.8】不能同时存在';
        }

        return false;
    }

    /**
     * 诊断【i63.9 脑梗死】 + 手术【88.4101脑血管造影】 + 【诊断有 i65】 建议更换为 i63.0 - i63.8
     * @param $data
     * @return false|string
     */
    public function rule1483($data)
    {
        $ICD9_ID1 = '';
        foreach ($data['operation'] as $operation) {
            if ($operation['SSCZBM'] == '88.4101') {
                $ICD9_ID1 = $operation['SSCZBM'];
                break;
            }
        }
        if (empty($ICD9_ID1)) {
            return false;
        }

        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if (stripos($ICD10_ID1, 'i63.9') === 0) {
                $res1 = 1;
            } elseif (stripos($ICD10_ID1, 'i65') === 0) {
                $res2 = 1;
            }
        }
        if ($res1 && $res2) {
            return '诊断【i63.9 脑梗死】+手术【88.4101脑血管造影】+【诊断有 i65】，建议更换为i63.0-i63.8';
        }

        return false;
    }

    /**
     * 诊断【K21.9 胃-食管反流性疾病不伴有食管炎】与【K21.0 胃-食管反流性疾病伴有食管炎】逻辑冲突(伴~不伴)
     * @param $data
     * @return false|string
     */
    public function rule1484($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if (stripos($ICD10_ID1, 'K21.9') === 0) {
                $res1 = 1;
            } elseif (stripos($ICD10_ID1, 'K21.0') === 0) {
                $res2 = 1;
            }
        }
        if ($res1 && $res2) {
            return '诊断编码【K21.9】与【K21.0】逻辑冲突';
        }

        return false;
    }

    /**
     * 手术【51.1】 不能 同时有手术【51.64 或 51.84-51.88 或 52.14 或 52.21 或 52.93 h或 52.94 或 52.97 或 52.98】（双向质控）
     * @param $data
     * @return false|string
     */
    public function rule1485($data)
    {
        $arr = ['51.64','51.84','51.85','51.86','51.87','51.88','52.14','52.21','52.93','52.94','52.97','52.98'];
        $res1 = 0;
        $res2 = '';
        foreach ($data['operation'] as $operation) {
            if ($res1 && $res2) {
                break;
            }
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1,'51.1') === 0) {
                $res1 = 1;
            } else {
                foreach ($arr as $val) {
                    if (stripos($ICD9_ID1,$val) === 0) {
                        $res2 = $val;
                        break;
                    }
                }
            }
        }

        if ($res1 && $res2) {
            return '手术编码【51.1】与【'.$res2.'】不能同时存在';
        }

        return false;
    }

    /**
     * 诊断【Z51.0】手术操作必须有【92.2 - 92.3】
     * @param $data
     * @return false|string
     */
    public function rule1486($data)
    {
        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'Z51.0') === 0) {
                $ICD10_ID1 = $diagnosis['ZDBM'];
                break;
            }
        }
        if (empty($ICD10_ID1)) {
            return false;
        }

        $ICD9_ID1 = '';
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['SSCZBM'], '92.2') === 0 || stripos($operation['SSCZBM'], '92.3') === 0) {
                $ICD9_ID1 = $operation['SSCZBM'];
                break;
            }
        }
        if (empty($ICD9_ID1)) {
            return '有诊断编码【Z51.0】，无手术操作【92.2-92.3】';
        }

        return false;
    }

    /**
     * 产科【产科】诊断【O36.4】 其他诊断不应有【Z37 或 O80 - O84】
     * @param $data
     * @return false|string
     */
    public function rule1487($data)
    {
        if ($data['data']['AAC11N'] != '产科') {
            return false;
        }

        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'O36.4') === 0) {
                $ICD10_ID1 = $diagnosis['ZDBM'];
                break;
            }
        }
        if (empty($ICD10_ID1)) {
            return false;
        }

        $arr = ['Z37','O80','O81','O82','O83','O84'];
        $res = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res) {
                break;
            }
            foreach ($arr as $val) {
                if ($diagnosis['ZZPB']!=1 && stripos($diagnosis['ZDBM'], $val) === 0) {
                    $res = 1;
                    break;
                }
            }
        }
        if ($res) {
            return '出院科室【产科】，诊断编码含【O36.4】，其他诊断不应有【Z37或O80-O84】';
        }

        return false;
    }

    /**
     * 产科【产科】诊断【O36.4】和 手术【74.1】手术应换成74.9
     * @param $data
     * @return false|string
     */
    public function rule1488($data)
    {
        if ($data['data']['AAC11N'] != '产科') {
            return false;
        }

        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'O36.4') === 0) {
                $ICD10_ID1 = $diagnosis['ZDBM'];
                break;
            }
        }
        if (empty($ICD10_ID1)) {
            return false;
        }

        $ICD9_ID1 = '';
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['SSCZBM'],'74.1') === 0) {
                $ICD9_ID1 = $operation['SSCZBM'];
            }
        }
        if ($ICD9_ID1) {
            return '出院科室【产科】，诊断编码有【O36.4】，手术编码有【74.1】应换成【74.9】';
        }

        return false;
    }

    /**
     * 主诊断【C00-C76】病理诊断编码必须是 M****\/3
     * @param $data
     * @return false|string
     */
    public function rule1489($data)
    {
        $arr = [
            'C00','C01','C02','C03','C04','C05','C06','C07','C08','C09','C10','C11','C12','C13','C14','C15','C16',
            'C17','C18','C19','C20','C21','C22','C23','C24','C25','C26','C27','C28','C29','C30','C31','C32','C33',
            'C34','C35','C36','C37','C38','C39','C40','C41','C42','C43','C44','C45','C46','C47','C48','C49','C50',
            'C51','C52','C53','C54','C55','C56','C57','C58','C59','C60','C61','C62','C63','C64','C65','C66','C67',
            'C68','C69','C70','C71','C72','C72','C74','C75','C76'
        ];
        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $ICD10_ID1 = $diagnosis['ZDBM'];
                break;
            }
        }
        if (!$ICD10_ID1) {
            return false;
        }

        $res1 = 0;
        foreach ($arr as $val) {
            if (stripos($ICD10_ID1, $val) === 0) {
                $res1 = 1;
                break;
            }
        }
        if (!$res1) {
            return false;
        }

        $ABF01C = !empty($data['data']['ABF01C']) ? $data['data']['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][3]$/', $ABF01C);
        if (!$res2) {
            return '主诊断【C00-C76】病理诊断编码必须是 M****/3';
        }

        return false;
    }

    /**
     * 主诊断【D00-D09】病理诊断编码必须是 M****\/2
     * @param $data
     * @return false|string
     */
    public function rule1490($data)
    {
        $arr = [
            'D00','D01','D02','D03','D04','D05','D06','D07','D08','D09'
        ];
        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $ICD10_ID1 = $diagnosis['ZDBM'];
                break;
            }
        }
        if (!$ICD10_ID1) {
            return false;
        }

        $res1 = 0;
        foreach ($arr as $val) {
            if (stripos($ICD10_ID1, $val) === 0) {
                $res1 = 1;
                break;
            }
        }
        if (!$res1) {
            return false;
        }

        $ABF01C = !empty($data['data']['ABF01C']) ? $data['data']['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][2]$/', $ABF01C);
        if (!$res2) {
            return '主诊断【D00-D09】病理诊断编码必须是 M****/2';
        }

        return false;
    }

    /**
     * 主诊断【D10-D36】病理诊断编码必须是 M****\/0
     * @param $data
     * @return false|string
     */
    public function rule1491($data)
    {
        $arr = [
            'D10','D11','D12','D13','D14','D15','D16','D17','D18','D19','D20','D21','D22','D23','D24','D25','D26',
            'D27','D28','D29','D30','D31','D32','D33','D34','D35','D36'
        ];
        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $ICD10_ID1 = $diagnosis['ZDBM'];
                break;
            }
        }
        if (!$ICD10_ID1) {
            return false;
        }

        $res1 = 0;
        foreach ($arr as $val) {
            if (stripos($ICD10_ID1, $val) === 0) {
                $res1 = 1;
                break;
            }
        }
        if (!$res1) {
            return false;
        }

        $ABF01C = !empty($data['data']['ABF01C']) ? $data['data']['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][0]$/', $ABF01C);
        if (!$res2) {
            return '主诊断【D10-D36】病理诊断编码必须是 M****/0';
        }

        return false;
    }

    /**
     * 主诊断【D37-D48】病理诊断编码必须是 M****\/1
     * @param $data
     * @return false|string
     */
    public function rule1492($data)
    {
        $arr = [
            'D37','D38','D39','D40','D41','D42','D43','D44','D45','D46','D47','D48'
        ];
        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $ICD10_ID1 = $diagnosis['ZDBM'];
                break;
            }
        }
        if (!$ICD10_ID1) {
            return false;
        }

        $res1 = 0;
        foreach ($arr as $val) {
            if (stripos($ICD10_ID1, $val) === 0) {
                $res1 = 1;
                break;
            }
        }
        if (!$res1) {
            return false;
        }

        $ABF01C = !empty($data['data']['ABF01C']) ? $data['data']['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][1]$/', $ABF01C);
        if (!$res2) {
            return '主诊断【D37-D48】病理诊断编码必须是 M****/1';
        }

        return false;
    }

    /**
     * 主诊断【C77-C79】 病理诊断编码必须是 M****\/6
     * @param $data
     * @return false|string
     */
    public function rule1493($data)
    {
        $arr = ['C77','C78','C79'];
        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $ICD10_ID1 = $diagnosis['ZDBM'];
                break;
            }
        }
        if (!$ICD10_ID1) {
            return false;
        }

        $res1 = 0;
        foreach ($arr as $val) {
            if (stripos($ICD10_ID1, $val) === 0) {
                $res1 = 1;
                break;
            }
        }
        if (!$res1) {
            return false;
        }

        $ABF01C = !empty($data['data']['ABF01C']) ? $data['data']['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][6]$/', $ABF01C);
        if (!$res2) {
            return '主诊断【C77-C79】 病理诊断编码必须是 M****/6';
        }

        return false;
    }

    /**
     * 主诊断【C80-C97】病理诊断编码必须是 M****\/3
     * @param $data
     * @return false|string
     */
    public function rule1494($data)
    {
        $arr = [
            'C80','C81','C82','C83','C84','C85','C86','C87','C88','C89','C90','C91','C92','C93','C94','C95','C96','C99'
        ];
        $ICD10_ID1 = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $ICD10_ID1 = $diagnosis['ZDBM'];
                break;
            }
        }
        if (!$ICD10_ID1) {
            return false;
        }

        $res1 = 0;
        foreach ($arr as $val) {
            if (stripos($ICD10_ID1, $val) === 0) {
                $res1 = 1;
                break;
            }
        }
        if (!$res1) {
            return false;
        }

        $ABF01C = !empty($data['data']['ABF01C']) ? $data['data']['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][3]$/', $ABF01C);
        if (!$res2) {
            return '主诊断【C80-C97】病理诊断编码必须是 M****/3';
        }

        return false;
    }

    /**
     * 【68.1200x001 宫腔镜检查】不能和【宫腔镜其他诊断】同时存在
     * @param $data
     * @return false|string
     */
    public function rule1495($data)
    {
        $str = "66.2900x003,66.8x03,66.9600x003,67.2x01,67.3203,67.3902,67.4x08,68.1602,68.2101,68.2204,68.2206,68.2300x005,68.2302,68.2900x048,68.2913,68.2914,68.2915,68.2916,68.2917,69.0902,69.4900x006,69.4904,69.5103,70.1408,74.3x00x016,74.3x00x017,74.3x00x018,74.3x09,97.7102,98.1600x002";
        $arr = explode(",",$str);
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            if ($res1 && $res2) {
                break;
            }
            if ($operation['SSCZBM'] == '68.1200x001') {
                $res1 = 1;
            } elseif (in_array($operation['SSCZBM'], $arr)) {
                $res2 = 1;
            }
        }
        if ($res1 && $res2) {
            return '手术【宫腔镜检查】和【宫腔镜其他诊断】不能同时存在';
        }
        return false;
    }

    /**
     * 【54.2100 腹腔镜检查】不能和【腹腔镜手术】同时存在
     * @param $data
     * @return false|string
     */
    public function rule1496($data)
    {
        $str = "02.3405,05.2301,05.2401,07.1200x003,07.2102,07.2201,07.2902,07.3x01,07.4102,17.1100,17.1100x001,17.1200,17.1200x001,17.1300,17.1300x001,17.1300x002,17.2100,17.2100x001,17.2200,17.2200x001,17.2300,17.2300x001,17.2400,17.2400x001,17.3100,17.3101,17.3200,17.3200x001,17.3200x002,17.3300,17.3300x002,17.3400,17.3401,17.3500,17.3500x001,17.3600,17.3600x001,17.3900,17.3900x002,17.3900x003,17.3901,17.4200,34.8100x002,38.8700x009,38.8700x011,38.8700x012,40.1100x003,40.2900x027,40.5301,40.5400x002,40.5900x010,40.5911,40.5912,41.2x03,41.2x04,41.4301,41.5x01,41.9301,41.9504,42.7x02,43.0x03,43.1900x006,43.3x01,43.4203,43.5x03,43.6x02,43.7x00x002,43.7x03,43.8200,43.8200x001,43.8201,43.9102,43.9900x003,43.9900x005,43.9904,43.9905,44.0001,44.2900x003,44.3800,44.3801,44.3802,44.3803,44.3804,44.4102,44.4200x001,44.4202,44.6401,44.6700,44.6701,44.6800,44.6800x002,44.6801,44.6902,44.9100x005,44.9500,44.9501,44.9600,44.9601,44.9602,44.9700,44.9701,44.9800,44.9801,44.9802,45.0204,45.3303,45.3304,45.4100x002,45.4100x003,45.4100x004,45.4100x005,45.6100x001,45.6200x001,45.6200x002,45.6200x003,45.6200x004,45.6200x005,45.6200x006,45.6208,45.6300x001,45.7900x004,45.8100,45.8100x001,46.1000x007,46.1100x002,46.1301,46.2001,46.2301,46.3900x006,46.3900x007,46.3905,46.4201,46.4202,46.6400x001,46.7303,46.7506,46.7604,46.7900x009,46.8100x001,46.8100x002,46.8200x001,46.8200x002,47.0100,47.1100,47.2x01,48.3507,48.4106,48.4200,48.4903,48.5100,48.5100x002,48.6100x001,48.6100x002,48.6201,48.6300x001,48.6300x002,48.6300x003,48.6302,48.6303,48.6900x002,48.6909,48.6910,48.6911,48.6912,48.6913,48.7101,48.7605,48.8205,48.8206,49.7904,50.0x00x004,50.0x03,50.0x04,50.0x05,50.1400,50.2203,50.2204,50.2205,50.2206,50.2500,50.2501,50.2502,50.2503,50.2900x020,50.2900x021,50.2909,50.2910,50.3x05,50.3x06,51.0301,51.0400x005,51.0404,51.1104,51.1105,51.2300,51.2301,51.2400,51.2401,51.3100x001,51.3203,51.3204,51.3301,51.3700x001,51.3700x002,51.3907,51.5900x006,51.6100x002,51.6300x001,51.6900x013,51.7909,51.7910,51.8701,51.8800x006,51.8803,51.8805,51.9101,52.0101,52.0102,52.0900x001,52.0904,52.1200x001,52.1302,52.2100x001,52.2100x002,52.2100x003,52.2100x004,52.2101,52.4x04,52.4x05,52.4x06,52.4x07,52.5204,52.5205,52.5206,52.5301,52.5905,52.5906,52.6x02,52.6x03,52.7x01,52.9301,52.9605,53.0002,53.0203,53.0204,53.1200x001,53.1203,53.2100x001,53.2900x001,53.3100x001,53.4200,53.4201,53.4300,53.4301,53.5101,53.5902,53.6200,53.6300,53.6301,53.6302,53.7100,53.7100x001,53.7101,53.8300,53.9x00x020,53.9x00x021,53.9x00x022,54.1101,54.1900x005,54.1900x006,54.2100,54.2100x005,54.2200x003,54.2300x004,54.2300x005,54.2300x006,54.3x02,54.4x00x050,54.4x00x052,54.4x00x053,54.4x10,54.4x11,54.4x12,54.4x13,54.4x14,54.4x15,54.4x16,54.5100,54.5100x005,54.5100x009,54.5101,54.5102,54.5103,54.6400x001,54.9202,54.9300x005,54.9300x009,54.9500x005,54.9703,54.9900x010,54.9900x011,54.9903,54.9904,55.0106,55.0109,55.0110,55.0111,55.0201,55.1108,55.1109,55.3400,55.3400x001,55.3400x002,55.3900x004,55.4x03,55.5103,55.5104,55.5105,55.5106,55.5401,55.7x01,55.8501,55.8600x006,55.8606,55.8703,55.8704,55.8900x003,55.9903,56.2x04,56.4100x009,56.4100x011,56.4105,56.4201,56.6100x004,56.7100x004,56.7402,56.8200x002,56.8900x006,56.8908,56.8909,56.9500x001,57.5100x003,57.5102,57.6x06,57.7103,57.7901,57.8400x004,57.8700x005,57.8700x006,57.8700x007,57.8700x008,57.8900x003,57.8905,58.4305,58.4702,59.0300,59.0300x002,59.0301,59.0302,59.0303,59.0904,59.1200,59.5x02,60.5x02,60.6101,60.6900x002,60.7300x003,60.7300x004,61.4905,62.0x01,62.2x00x003,62.3x04,62.4103,62.4105,62.5x01,63.1x03,63.6x00x005,65.0100,65.0100x002,65.0100x003,65.0101,65.0102,65.0103,65.0104,65.0105,65.1300,65.1400,65.2300,65.2400,65.2500,65.2500x003,65.2500x005,65.2500x011,65.2501,65.2502,65.2503,65.2504,65.2505,65.3100,65.4100,65.5300,65.5400,65.6300,65.6300x001,65.6400,65.7400,65.7500,65.7600,65.7900x008,65.7900x009,65.7904,65.7905,65.8100,65.8101,65.8102,65.9101,65.9900x006,65.9902,66.0100x003,66.0101,66.0102,66.0103,66.0202,66.0203,66.1101,66.2101,66.2102,66.2200x001,66.2201,66.2900x001,66.2901,66.2902,66.2903,66.4x02,66.5102,66.5201,66.6100x002,66.6100x003,66.6100x006,66.6100x007,66.6103,66.6104,66.6200x004,66.6201,66.6301,66.6902,66.69x002,66.7100x002,66.7301,66.7900x008,66.7900x009,66.7905,66.7906,66.8x02,66.9100x003,66.9203,66.9204,66.9205,66.9500x001,66.9502,66.9600x002,67.3903,67.4x05,67.4x06,67.4x07,67.5101,68.0x00x006,68.0x01,68.1501,68.1601,68.2203,68.2205,68.2401,68.2501,68.2900x013,68.2908,68.2909,68.2910,68.2911,68.2912,68.2918,68.3100,68.3102,68.3103,68.3104,68.3105,68.3106,68.4100,68.4101,68.4102,68.4103,68.4104,68.5100,68.5100x004,68.5100x005,68.5101,68.5102,68.5103,68.6100,68.6100x001,68.6100x002,68.6101,68.7100,68.7100x001,69.1900x022,69.1907,69.1908,69.1909,69.2200x007,69.2200x008,69.2200x009,69.2200x016,69.2200x017,69.2200x018,69.2208,69.2209,69.2210,69.2211,69.2212,69.3x02,69.4201,69.4902,69.4903,70.1200x002,70.1202,70.1400x002,70.1407,70.2301,70.3201,70.3305,70.4x00x001,70.4x05,70.5002,70.5102,70.5202,70.6101,70.6300x001,70.6300x002,70.6300x003,70.7700x004,70.7802,70.7909,74.3x00x012,74.3x00x014,74.3x00x015,74.3x05,74.3x06,74.3x07,74.3x08,74.9100x001,74.9101,84.6501";
        $arr = explode(",", $str);
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            if ($res1 && $res2) {
                break;
            }
            if ($operation['SSCZBM'] == '54.2100') {
                $res1 = 1;
            } elseif (in_array($operation['SSCZBM'], $arr)) {
                $res2 = 1;
            }
        }
        if ($res1 && $res2) {
            return '手术【腹腔镜检查】和【腹腔镜手术】不能同时存在';
        }
        return false;
    }

    /**
     * 70.78另编码使用生物学物质（70.94）或人造物质（70.95）
     * @param $data
     * @return false|string
     */
    public function rule1497($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1,'70.78') === 0) {
                $res1 = 1;
            } elseif (stripos($ICD9_ID1,'70.94') === 0 || stripos($ICD9_ID1,'70.95') === 0) {
                $res2 = 1;
            }
        }

        if (empty($res1)) {
            return false;
        }
        if (empty($res2)) {
            return '手术编码70.78，应另编码【70.94 生物学物质】或【70.95 人造物质】';
        }
        return false;
    }

    /**
     * 手术【81.0或81.3】手术中要有【81.62-81.64】融合椎骨的总数
     * @param $data
     * @return false|string
     */
    public function rule1498($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1,'81.0')===0 || stripos($ICD9_ID1,'81.3')===0) {
                $res1 = 1;
            } elseif (stripos($ICD9_ID1,'81.62')===0 || stripos($ICD9_ID1,'81.63')===0 || stripos($ICD9_ID1,'81.64')===0) {
                $res2 = 1;
            }
        }

        if (empty($res1)) {
            return false;
        }
        if(empty($res2)) {
            return '手术【81.0或81.3】，手术中要有【81.62-81.64】';
        }

        return false;
    }

    /**
     * 手术【81.51-81.53髋关节置换术】需同时编码【00.74-00.78】任何明确类型轴面
     * @param $data
     * @return false|string
     */
    public function rule1499($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1,'81.51')===0 || stripos($ICD9_ID1,'81.52')===0 || stripos($ICD9_ID1,'81.53')===0) {
                $res1 = 1;
            } elseif (stripos($ICD9_ID1,'00.74')===0 || stripos($ICD9_ID1,'00.75')===0 || stripos($ICD9_ID1,'00.76')===0 || stripos($ICD9_ID1,'00.77')===0 || stripos($ICD9_ID1,'00.78')===0) {
                $res2 = 1;
            }
        }

        if (empty($res1)) {
            return false;
        }

        if (empty($res2)) {
            return '手术【81.51-81.53 髋关节置换术】需同时编码【00.74-00.78 任何明确类型轴面】';
        }

        return false;
    }

    /**
     * 手术【39.61】不能有【50.92或39.65或39.95或39.66】
     * @param $data
     * @return false|string
     */
    public function rule1500($data)
    {
        $arr = ['50.92','39.65','39.95','39.66'];
        $res1 = '';
        $res2 = '';
        foreach ($data['operation'] as $operation) {
            if ($res1 && $res2) {
                break;
            }
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1,'39.61')===0) {
                $res1 = '39.61';
            } else {
                foreach ($arr as $value) {
                    if (stripos($ICD9_ID1,$value)===0) {
                        $res2 = $value;
                    }
                }
            }
        }

        if ($res1 && $res2) {
            return '手术编码【39.61】和【'.$res2.'】不能同时存在';
        }
        return false;
    }

    /**
     * 诊断【T20-T30】烧伤，同时需要编【T31或T32】面积
     * @param $data
     * @return false|string
     */
    public function rule1501($data)
    {
        $arr = ['T20','T21','T22','T23','T24','T25','T26','T27','T28','T29','T30'];
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }

            $ICD10_ID1 = $diagnosis['ZDBM'];
            if (stripos($ICD10_ID1, 'T31') === 0 || stripos($ICD10_ID1, 'T32') === 0) {
                $res2 = 1;
            } else {
                foreach ($arr as $val) {
                    if (stripos($ICD10_ID1, $val) === 0) {
                        $res1 = 1;
                        break;
                    }
                }
            }
        }

        if (empty($res1)) {
            return false;
        }
        if (empty($res2)) {
            return '诊断【T20-T30】烧伤，同时需要编码【T31或T32】面积';
        }

        return false;
    }

    /**
     * 38.45另编码心肺搭桥[体外循环]（39.61）
     * @param $data
     * @return false|string
     */
    public function rule1502($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1,'38.45') === 0) {
                $res1 = 1;
            } elseif (stripos($ICD9_ID1,'39.61') === 0) {
                $res2 = 1;
            }
        }

        if (empty($res1)) {
            return false;
        }
        if (empty($res2)) {
            return '手术【38.45】另编码【39.61 心肺搭桥[体外循环]】';
        }
        return false;
    }

    /**
     * 手术【39.90或36.06或36.07或00.55或00.63或00.64或00.65】，手术中需要有【00.45-00.48 + 治疗血管的数量00.40-00.43】
     * @param $data
     * @return false|string
     */
    public function rule1503($data)
    {
        $arr1 = ['39.90','36.06','36.07','00.55','00.63','00.64','00.65'];
        $res1 = 0; $res2 = 0; $res3 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            foreach ($arr1 as $val) {
                if (stripos($ICD9_ID1,$val)===0) {
                    $res1 = 1;
                }
            }

            if (stripos($ICD9_ID1,'00.45')===0 || stripos($ICD9_ID1,'00.46')===0 || stripos($ICD9_ID1,'00.47')===0 || stripos($ICD9_ID1,'00.48')===0) {
                $res2 = 1;
            } elseif (stripos($ICD9_ID1,'00.40')===0 || stripos($ICD9_ID1,'00.41')===0 || stripos($ICD9_ID1,'00.42')===0 || stripos($ICD9_ID1,'00.43')===0) {
                $res3 = 1;
            }
        }

        if (empty($res1)) {
            return false;
        }
        if (empty($res2) || empty($res3)) {
            return '手术【39.90或36.06或36.07或00.55或00.63或00.64或00.65】，手术中需要有【00.45-00.48 + 治疗血管的数量00.40-00.43】';
        }
        return false;
    }

    /**
     * 手术【39.74】手术中需要有治疗血管的数量【00.40-00.43】
     * @param $data
     * @return false|string
     */
    public function rule1504($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1, '39.74')===0) {
                $res1 = 1;
            } elseif (stripos($ICD9_ID1, '00.40')===0 || stripos($ICD9_ID1, '00.41')===0 || stripos($ICD9_ID1, '00.42')===0 || stripos($ICD9_ID1, '00.43')===0) {
                $res2 = 1;
            }
        }

        if (empty($res1)) {
            return false;
        }
        if (empty($res2)) {
            return '有手术【39.74】，手术中需要有治疗血管的数量【00.40-00.43】';
        }
        return false;
    }

    /**
     * 收费【阿替普酶】手术要有【99.1005 脑动脉血栓溶解剂灌注】
     * @param $data
     * @return false|string
     */
    public function rule1505($data)
    {
        $FYMC_ARR = array_column($data['fy'],'FYMC');
        $res = false;
        foreach ($FYMC_ARR as $value) {
            if (stripos($value,'阿替普酶') !== false) {
                $res = true;
            }
        }
        if (!$res) {
            return false;
        }

        $res1 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if ($ICD9_ID1 == '99.1005') {
                $res1 = 1;
            }
        }

        if (empty($res1)) {
            return '收费中含【阿替普酶】，手术要有【99.1005 脑动脉血栓溶解剂灌注】';
        }

        return false;
    }

    /**
     * 手术中 03.90另编码输注泵的置入（86.06）
     * @param $data
     * @return false|string
     */
    public function rule1506($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1,'03.90') === 0) {
                $res1 = 1;
            } elseif (stripos($ICD9_ID1,'86.06') === 0) {
                $res2 = 1;
            }
        }

        if (empty($res1)) {
            return false;
        }
        if (empty($res2)) {
            return '手术【03.90】，应另编码【86.06 输注泵的置入】';
        }
        return false;
    }

    /**
     * 37.8有导线起搏器另编导线置入、置换、去除和修复（37.70-37.77）
     * @param $data
     * @return false|string
     */
    public function rule1507($data)
    {
        $arr = ['37.70','37.71','37.72','37.73','37.74','37.75','37.76','37.77'];
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            if ($res1 && $res2) {
                break;
            }
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1,'37.8') === 0) {
                $res1 = 1;
            } else {
                foreach ($arr as $val) {
                    if (stripos($ICD9_ID1,$val) === 0) {
                        $res2 = 1;
                        break;
                    }
                }
            }
        }

        if (empty($res1)) {
            return false;
        }
        if (empty($res2)) {
            return '手术【37.8 有导线起搏器】另编【37.70-37.77 导线置入、置换、去除和修复】';
        }
        return false;
    }

    /**
     * 手术名称有【***粘连松解术】 诊断名称要有【 ***粘连】
     * @param $data
     * @return false|string
     */
    public function rule1508($data)
    {
        $res1 = 0;
        foreach ($data['operation'] as $operation) {
            if (preg_match('/^(.*)粘连松解术$/', $operation['SSCZMC'])) {
                $res1 = 1;
            }
        }
        if (!$res1) {
            return false;
        }

        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if (preg_match('/^(.*)粘连$/', $diagnosis['ZDMC'])) {
                $res2 = 1;
            }
        }
        if (!$res2) {
            return '手术名称有【***粘连松解术】，诊断名称要有【***粘连】';
        }

        return false;
    }

    /**
     * 诊断【N20.0和N20.1】需要合并到N20.2
     * @param $data
     * @return false|string
     */
    public function rule1509($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'],'N20.0') === 0) {
                $res1 = 1;
            } elseif (stripos($diagnosis['ZDBM'],'N20.1') === 0) {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return '诊断【N20.0和N20.1】需要合并到N20.2';
        }
        return false;
    }

    /**
     * 诊断【N20和N13.3】需要合并到N13.2
     * @param $data
     * @return false|string
     */
    public function rule1510($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'],'N20') === 0) {
                $res1 = 1;
            } elseif (stripos($diagnosis['ZDBM'],'N13.3') === 0) {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return '诊断【N20和N13.3】需要合并到N13.2';
        }
        return false;
    }

    /**
     * 诊断【N13.0-N13.5和 N15.9】需要合并到N13.6
     * @param $data
     * @return false|string
     */
    public function rule1511($data)
    {
        $arr = ['N13.0','N13.1','N13.2','N13.3','N13.4','N13.5'];
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($diagnosis['ZDBM'],'N15.9') === 0) {
                $res2 = 1;
            } else {
                foreach ($arr as $val) {
                    if (stripos($diagnosis['ZDBM'],$val) === 0) {
                        $res1 = 1;
                        break;
                    }
                }
            }
        }

        if ($res1 && $res2) {
            return '诊断【N13.0-N13.5 和 N15.9】需要合并到N13.6';
        }
        return false;
    }

    /**
     * 诊断信息除最后一条外，诊断编码或名称不能为空
     * @param $data
     * @return false|string
     */
    public function rule1514($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }
        $res = '';
        foreach ($data['diagnosis'] as $key => $diagnosis) {
            if (empty($diagnosis['ZDBM']) || empty($diagnosis['ZDMC'])) {
                $res = $key;
                break;
            }
        }

        if ($res !== '') {
            $res1 = count($data['diagnosis'])-1;
            if ($res < $res1) {
                return '诊断编码或名称不能为空';
            }
        }

        return false;
    }

    /**
     * 离院方式
     * @param $data
     * @return false|string
     */
    public function rule1515($data)
    {
        if (empty($data['yz'])) {
            return false;
        }

        $arr = array_unique(array_column($data['yz'],'YDYZLB'));
        if (in_array(305,$arr) && $data['data']['AEM01C']!=5) {
            return '医嘱中含【死亡】，离院方式不是【死亡】';
        }

        return false;
    }

    /**
     * 新增：新生儿入院体重(克)填写  ，天龄（不足1周岁）应≤28天
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule1526($data)
    {
        $res = preg_match("/^[1-9][0-9]{2,3}$/", $data['data']['AAA42']);


        if (empty($res)) {
            return false;
        }

        if ($data['data']['AAC11C'] == 287 && (empty($data['data']['AAA40']) || $data['data']['AAA40'] > 28)) {
             return  '天龄（不足1周岁）应≤28天';
        }

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
        if (!empty($data['other']['SSZLF'])) {
            foreach ($data['operation'] as $operation) {


                if ($operation['SSSX'] && empty($operation['SZXM'])) {
                    $ssList[] = ['OPE_ORDER' => $operation['SZXM'], 'field' => 'OPE_MAN_NAME'];
                }

            }
        }


        if (!empty($ssList)) {
            $basis[] = ['desc' => '手术操作栏不能为空', 'location' => ['user' => ['AAA40', 'AAC11N'], 'zd' => [], 'ss' => []]];
        }
        return $basis;
    }


}

