<?php

namespace App\Services;

use App\Model\ErrorHomeBmy;

class HomeBmyQualityService
{
    public static $noPhone = [11111111111, 12345678911, 1111111, 1234567];

    /**
     * 病案首页质控
     * @param $ZYH
     * @param $data
     * @param $errorRuleData
     * @param $depData
     * @return true
     */
    public function qualityContrl($ZYH, $data, $errorRuleData, $depData)
    {
        $insertData = [];
        foreach ($errorRuleData as $ruleId => $errorRule) {
            $method = 'rule' . $ruleId;

            if (!method_exists(new HomeBmyQualityService(), $method)) {
                continue;
            }

            // 质控
            $res = $this->$method($data);
            if (!empty($res)) {
                $hospitalName = !empty($data['data']['ZA03']) ? $data['data']['ZA03'] : config('confAdmin.hospital_name');
                $desc = $errorRule['desc'];
                if (in_array($ruleId, [39, 54, 1439, 1440, 1441, 1458])) {
                    $desc = $res;
                }

                $insertData[] = [
                    'hospital_name' => $hospitalName,   // 医院名称
                    'error_rule' => $ruleId,            // 规则id
                    'AAA28' => $data['data']['AAA28'],  // 住院号码
                    'ZYH' => $ZYH,                      // 住院号
                    'AAB01' => $data['data']['AAB01'],  // 入院时间
                    'AAC01' => $data['data']['AAC01'],  // 出院时间
                    'desc' => $desc,                    // 错误内容
                    'coder_id' => $data['data']['AEE08'] ?? '',    // 编码员编号
                    'coder_name' => '',                 // 编码员姓名
                    'CYKSBM' => $data['data']['AAC11C'] ?? '',  // 出院科室编码
                    'CYKB' => $data['data']['AAC11N'],          // 出院科室名称
                    'ZZYS_BH' => $data['data']['AEE03_CODE'],   // 主治医师编码
                    'ZZYS' => $data['data']['AEE03'],           // 主治医师姓名
                    'ZYYS_BH' => $data['data']['AEE04_CODE'],   // 住院医师编码
                    'ZYYS' => $data['data']['AEE04'],           // 住院医师姓名
                ];
            }
        }

        ErrorHomeBmy::query()->where('ZYH', '=', $ZYH)->update(['status' => 1]);
        if (!empty($insertData)) {
            ErrorHomeBmy::query()->insert($insertData);
        }

        return true;
    }

    /**
     * 组织机构代码
     * @param $data
     * @return bool
     */
    public function rule1($data)
    {
        if (mb_strlen($data['data']['UNT_ID']) < 6 || mb_strlen($data['data']['UNT_ID']) > 22 || $data['data']['UNT_ID'] == 123456) {
            return true;
        }
        return false;
    }

    /**
     * 病案号
     * @param $data
     * @return bool
     */
    public function rule2($data)
    {
        if (mb_strlen($data['data']['AAA28']) < 6 || mb_strlen($data['data']['AAA28']) > 50) {
            return true;
        }
        return false;
    }

    /**
     * 医疗机构名称
     * @param $data
     * @return bool
     */
    public function rule3($data)
    {
        $res = preg_match('/^[\x7f-\xff]+$/', $data['data']['ZA03']);
        if (mb_strlen($data['data']['ZA03']) < 4 || $data['data']['ZA03'] > 80 || !$res) {
            return true;
        }
        return false;
    }

    /**
     * 住院次数
     * @param $data
     * @return bool
     */
    public function rule4($data)
    {
        if (!preg_match("/^[1-9][0-9]*$/", $data['data']['AAA29'])) {
            return true;
        }
        return false;
    }

    /**
     * 入院时间
     * @param $ZYH
     * @param $data
     * @return bool
     */
    public function rule5($data)
    {
        $AAB01 = date('Y-m-d', strtotime($data['data']['AAB01']));
        $AAC01 = date('Y-m-d', strtotime($data['data']['AAC01']));
        if (empty($AAB01) || $AAB01 >= $AAC01) {
            return true;
        }
        return false;
    }

    /**
     * 健康卡号
     * @param $data
     * @return bool
     */
    public function rule6($data)
    {
        return false;
    }

    /**
     * 患者姓名
     * @param $data
     * @return bool
     */
    public function rule7($data)
    {
        $XM = $data['data']['AAA01'];
        if (mb_strlen($XM) < 2 || mb_strlen($XM) > 40) {
            return true;
        } else {
            // 获取第一位
            $firstStr = mb_substr($XM, 0, 1);
            // 获取最后一位
            $xmLen = mb_strlen($XM);
            $endStr = mb_substr($XM, $xmLen - 1, 1);

            $res1 = PublicService::pregMatchTszf($firstStr, 'digit');
            $res2 = PublicService::pregMatchTszf($firstStr, 'punct');

            $res3 = PublicService::pregMatchTszf($endStr, 'digit');
            $res4 = PublicService::pregMatchTszf($endStr, 'punct');

            $res5 = PublicService::pregMatchTszf($XM, 'space');
            if ($res1 || $res2 || $res3 || $res4 || $res5) {
                return true;
            }
        }

        return false;
    }

    /**
     * 出生地省（不能为空，不能全是数字，不能是空格）
     * @param $data
     * @return bool
     */
    public function rule8($data)
    {
        if (mb_strlen(trim($data['data']['AAA09'])) < 2 || is_numeric(trim($data['data']['AAA09']))) {
            return true;
        }
        return false;
    }

    /**
     * 籍贯省
     * @param $data
     * @return bool
     */
    public function rule9($data)
    {
        if (mb_strlen(trim($data['data']['AAA43'])) < 2 || is_numeric(trim($data['data']['AAA43']))) {
            return true;
        }
        return false;
    }

    /**
     * 民族
     * @param $data
     * @return bool
     */
    public function rule10($data)
    {
        if (empty($data['data']['AAA06C']) || $data['data']['AAA06C'] == '-') {
            return true;
        }
        return false;
    }

    /**
     * 身份证号
     * @param $data
     * @return bool
     */
    public function rule11($data)
    {
        if (mb_strlen($data['data']['AAA07']) != 15 && mb_strlen($data['data']['AAA07']) != 18) {
            return true;
        }
        return false;
    }

    /**
     * 职业
     * @param $data
     * @return bool
     */
    public function rule12($data)
    {
        if (empty($data['data']['AAA18C'])) {
            return true;
        }
        return false;
    }

    /**
     * 婚姻状况
     * @param $data
     * @return bool
     */
    public function rule13($data)
    {
        if (mb_strlen($data['data']['AAA08C']) < 1) {
            return true;
        }
        return false;
    }

    /**
     * 现住址省
     * @param $data
     * @return bool
     */
    public function rule14($data)
    {
        if (mb_strlen(trim($data['data']['AAA48'])) < 2 || is_numeric(trim($data['data']['AAA48']))) {
            return true;
        }
        return false;
    }

    /**
     * 电话
     * @param $data
     * @return bool
     */
    public function rule15($data)
    {
        $AAA51 = $data['data']['AAA51'] ?? '';
        if (mb_strlen($AAA51) < 7 || in_array($AAA51, self::$noPhone)) {
            return true;
        }

        $res1 = preg_match('/^1[0-9]{10}$/', $AAA51);
        $res2 = preg_match('/^[0-9]{4}[\-][0-9]{7}$/', $AAA51);
        $res3 = preg_match('/^[0-9]{7}$/', $AAA51);
        if (!$res1 && !$res2 && !$res3) {
            return true;
        }
        return false;
    }

    /**
     * 现住址邮政编码
     * @param $data
     * @return bool
     */
    public function rule16($data)
    {
        if (mb_strlen($data['data']['AAA17C']) != 6 || $data['data']['AAA17C'] == 123456) {
            return true;
        }
        return false;
    }

    /**
     * 户籍省
     * @param $data
     * @return bool
     */
    public function rule17($data)
    {
        if (mb_strlen(trim($data['data']['AAA45'])) < 2 || is_numeric(trim($data['data']['AAA45']))) {
            return true;
        }
        return false;
    }

    /**
     * 户籍邮编
     * @param $data
     * @return bool
     */
    public function rule18($data)
    {
        if (mb_strlen($data['data']['AAA13C']) != 6 || $data['data']['AAA13C'] == 123456) {
            return true;
        }
        return false;
    }

    /**
     * 工作单位及地址
     * @param $data
     * @return bool
     */
    public function rule19($data)
    {
        if (mb_strlen(trim($data['data']['AAA19'])) < 2 || is_numeric(trim($data['data']['AAA19']))) {
            return true;
        }
        return false;
    }

    /**
     * 单位电话
     * @param $data
     * @return bool
     */
    public function rule20($data)
    {
        $AAA20 = $data['data']['AAA20'] ?? '';
        if (mb_strlen($AAA20) < 7 || in_array($AAA20, self::$noPhone)) {
            return true;
        }

        $res1 = preg_match('/^1[0-9]{10}$/', $AAA20);
        $res2 = preg_match('/^[0-9]{4}[\-][0-9]{7}$/', $AAA20);
        $res3 = preg_match('/^[0-9]{7}$/', $AAA20);
        if (!$res1 && !$res2 && !$res3) {
            return true;
        }
        return false;
    }

    /**
     * 单位邮编
     * @param $data
     * @return bool
     */
    public function rule21($data)
    {
        if (mb_strlen($data['data']['AAA21C']) != 6 || $data['data']['AAA21C'] == 123456) {
            return true;
        }
        return false;
    }

    /**
     * 联系人姓名
     * @param $data
     * @return bool
     */
    public function rule22($data)
    {
        $XM = $data['data']['AAA22'];
        if (mb_strlen($XM) < 2 || mb_strlen($XM) > 40) {
            return true;
        } else {
            // 获取第一位
            $firstStr = mb_substr($XM, 0, 1);
            // 获取最后一位
            $xmLen = mb_strlen($XM);
            $endStr = mb_substr($XM, $xmLen - 1, 1);

            $res1 = PublicService::pregMatchTszf($firstStr, 'digit');
            $res2 = PublicService::pregMatchTszf($firstStr, 'punct');

            $res3 = PublicService::pregMatchTszf($endStr, 'digit');
            $res4 = PublicService::pregMatchTszf($endStr, 'punct');

            $res5 = PublicService::pregMatchTszf($XM, 'space');
            if ($res1 || $res2 || $res3 || $res4 || $res5) {
                return true;
            }
        }

        return false;
    }

    /**
     * 联系人关系
     * @param $data
     * @return bool
     */
    public function rule23($data)
    {
        if (mb_strlen($data['data']['AAA23C']) < 1) {
            return true;
        }
        return false;
    }

    /**
     * 联系人地址
     * @param $data
     * @return bool
     */
    public function rule24($data)
    {
        if (mb_strlen(trim($data['data']['AAA24'])) < 2 || is_numeric(trim($data['data']['AAA24']))) {
            return true;
        }
        return false;
    }

    /**
     * 联系人电话
     * @param $data
     * @return bool
     */
    public function rule25($data)
    {
        $AAA25 = $data['data']['AAA25'] ?? '';
        if (mb_strlen($AAA25) < 7 || in_array($AAA25, self::$noPhone)) {
            return true;
        }
        $res1 = preg_match('/^1[0-9]{10}$/', $AAA25);
        $res2 = preg_match('/^[0-9]{4}[\-][0-9]{7}$/', $AAA25);
        $res3 = preg_match('/^[0-9]{7}$/', $AAA25);
        if (!$res1 && !$res2 && !$res3) {
            return true;
        }
        return false;
    }

    /**
     * 性别
     * @param $data
     * @return bool
     */
    public function rule26($data)
    {
        if (mb_strlen($data['data']['AAA02C']) < 1) {
            return true;
        }
        return false;
    }

    /**
     * 出生日期
     * @param $data
     * @return bool
     */
    public function rule27($data)
    {
        if (mb_strlen($data['data']['AAA03']) < 1) {
            return true;
        }
        return false;
    }

    /**
     * 年龄
     * @param $data
     * @return bool
     */
    public function rule28($data)
    {
        if ($data['data']['AAA04'] == 0 && $data['data']['AAA40'] == 0 && $data['data']['AAC11C'] == 287) {
            return false;
        }

        $res1 = preg_match('/^[1-9][0-9]*$/', $data['data']['AAA04']);
        $arr = [];
        for ($i = 1; $i < 365; $i++) {
            $arr[] = $i;
        }

        if (!$res1 && !in_array($data['data']['AAA40'], $arr)) {
            return true;
        }
        return false;
    }

    /**
     * 国籍
     * @param $data
     * @return bool
     */
    public function rule29($data)
    {
        if (mb_strlen($data['data']['AAA05C']) < 1) {
            return true;
        }
        return false;
    }

    /**
     * 入院科别
     * @param $data
     * @return bool
     */
    public function rule30($data)
    {
        if (mb_strlen($data['data']['AAB02C']) < 1) {
            return true;
        }
        return false;
    }

    /**
     * 入院途径
     * @param $data
     * @return bool
     */
    public function rule31($data)
    {
        if (mb_strlen($data['data']['AAB06C']) < 1) {
            return true;
        }
        return false;
    }

    /**
     * 入院病房
     * @param $data
     * @return bool
     */
    public function rule32($data)
    {
        if (mb_strlen($data['data']['AAB11N']) < 1) {
            return true;
        }
        return false;
    }

    /**
     * 转科科别
     * @param $data
     * @return bool
     */
    public function rule33($data)
    {
        $homeSzService = new HomeSzService();
        $zyZkjlData = $homeSzService->ZY_ZKJL($data['data']['MED_REC_ID']);
        if (empty($zyZkjlData)) {
            if (!empty($data['data']['AAD01C'])) {
                return true;
            }
            return false;
        }

        $res = false;
        foreach ($zyZkjlData as $value) {
            if (in_array($value['HCLX'], [1, 3])) {
                $res = true;
                break;
            }
        }

        if ($res) {
            if (empty($data['data']['AAD01C'])) {
                return true;
            }
        } else {
            if (!empty($data['data']['AAD01C'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * 出院科别
     * @param $data
     * @return bool
     */
    public function rule34($data)
    {
        if (mb_strlen($data['data']['AAC02C']) < 1) {
            return true;
        }
        return false;
    }

    /**
     * 实际住院天数
     * @param $data
     * @return bool
     */
    public function rule36($data)
    {
        $res = preg_match('/^[1-9]\d*$/', $data['data']['AAC04']);
        if (!$res) {
            return true;
        }
        return false;
    }

    /**
     * 门(急)诊诊断编码
     * @param $data
     * @return bool
     */
    public function rule37($data)
    {
        if (empty($data['data']['ABA01C'])) {
            return true;
        }
        return false;
    }

    /**
     * 门(急)诊诊断名称
     * @param $data
     * @return bool
     */
    public function rule38($data)
    {
        if (empty($data['data']['ABA01N'])) {
            return true;
        }
        return false;
    }

    /**
     * 出院主要诊断编码
     * @param $data
     * @return bool
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

        if (empty($ICD10_ID1)) {
            return true;
        }
        return false;
    }

    /**
     * 出院主要诊断名称
     * @param $data
     * @return bool
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

        if (empty($ICD10_NAME)) {
            return true;
        }
        return false;
    }

    /**
     * 有无药物过敏
     * @param $data
     * @return bool
     */
    public function rule42($data)
    {
        $AEB02C = config('dictionaries.AEB02C');
        if (empty($AEB02C[$data['data']['AEB02C']])) {
            return true;
        }
        return false;
    }

    /**
     * 科主任
     * @param $data
     * @return bool
     */
    public function rule43($data)
    {
        if (mb_strlen($data['data']['AEE01']) < 2 || mb_strlen($data['data']['AEE01']) > 40) {
            return true;
        }
        return false;
    }

    /**
     * 主(副主)任医师
     * @param $data
     * @return bool
     */
    public function rule44($data)
    {
        if (mb_strlen($data['data']['AEE02']) < 2 || mb_strlen($data['data']['AEE02']) > 40) {
            return true;
        }
        return false;
    }

    /**
     * 主治医师
     * @param $data
     * @return bool
     */
    public function rule47($data)
    {
        if (mb_strlen($data['data']['AEE03']) < 2 || mb_strlen($data['data']['AEE03']) > 40) {
            return true;
        }
        return false;
    }

    /**
     * 住院医师
     * @param $data
     * @return bool
     */
    public function rule49($data)
    {
        if (mb_strlen($data['data']['AEE04']) < 2 || mb_strlen($data['data']['AEE04']) > 40) {
            return true;
        }
        return false;
    }

    /**
     * 责任护士
     * @param $data
     * @return bool
     */
    public function rule50($data)
    {
        if (mb_strlen($data['data']['AEE10']) < 2 || mb_strlen($data['data']['AEE10']) > 40) {
            return true;
        }
        return false;
    }

    /**
     * 编码员
     * @param $data
     * @return bool
     */
    public function rule51($data)
    {
        if (mb_strlen($data['data']['AEE08']) < 1) {
            return true;
        }
        return false;
    }

    /**
     * ABO血型
     * @param $data
     * @return bool
     */
    public function rule52($data)
    {
        $AEG01C = config('dictionaries.AEG01C');
        if (empty($AEG01C[$data['data']['AEG01C']])) {
            return true;
        }
        return false;
    }

    /**
     * RH血型
     * @param $data
     * @return bool
     */
    public function rule53($data)
    {
        $AEG02C = config('dictionaries.AEG02C');
        if (empty($AEG02C[$data['data']['AEG02C']])) {
            return true;
        }
        return false;
    }

    /**
     * 手术操作编码
     * @param $data
     * @return bool
     */
    public function rule54($data)
    {
        if (empty($data['operation'])) {
            return false;
        }

        $res = false;
        foreach ($data['operation'] as $operation) {
            if (empty($operation['SSCZBM'])) {
                $res = true;
                break;
            }
        }

        return $res;
    }

    /**
     * 手术操作名称
     * @param $data
     * @return bool
     */
    public function rule55($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $res = false;
        foreach ($data['operation'] as $operation) {
            if (empty($operation['SSCZMC'])) {
                $res = true;
                break;
            }
        }

        if ($res) {
            return true;
        }
        return false;
    }

    /**
     * 手术操作日期
     * @param $data
     * @return bool
     */
    public function rule56($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $res = false;
        foreach ($data['operation'] as $operation) {
            if (!empty($operation['SSCZMC']) && empty($operation['SSCZRQ'])) {
                $res = true;
                break;
            }
        }

        if ($res) {
            return true;
        }
        return false;
    }

    /**
     * 是否有出院31日内再住院计划
     * @param $data
     * @return bool
     */
    public function rule63($data)
    {
        $AEM03C = config('dictionaries.AEM03C');
        if (empty($AEM03C[$data['data']['AEM03C']])) {
            return true;
        }
        return false;
    }

    /**
     * 离院方式
     * @param $data
     * @return bool
     */
    public function rule64($data)
    {
        $AEM01C = config('dictionaries.AEM01C');
        if ($data['data']['AEM01C'] == 9 || empty($AEM01C[$data['data']['AEM01C']])) {
            return true;
        }
        return false;
    }

    /**
     * 住院总费用
     * @param $data
     * @return bool
     */
    public function rule65($data)
    {
        $ADA01 = $data['data']['ADA01'] ?? '';
        $ADA0101 = $data['data']['ADA0101'] ?? '';
        if ($ADA01 < $ADA0101 || !is_numeric($ADA01)) {
            return true;
        }
        return false;
    }

    /**
     * 住院总费用其中自付金额
     * @param $data
     * @return bool
     */
    public function rule66($data)
    {
        $ADA0101 = $data['data']['ADA0101'] ?? '';
        if (empty($ADA0101)) {
            return true;
        }
        return false;
    }

    /**
     * 病理诊断编码
     * @param $data
     * @return bool
     */
    public function rule67($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
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
                return true;
            }
        }

        return false;
    }

    /**
     * 病理诊断名称
     * @param $data
     * @return bool
     */
    public function rule68($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
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
                return true;
            }
        }

        return false;
    }

    /**
     * 病理号
     * @param $data
     * @return bool
     */
    public function rule69($data)
    {
        if (!empty($data['data']['ABF01C']) && empty($data['data']['ABF04'])) {
            return true;
        }
        return false;
    }

    /**
     * 损伤和中毒外部原因编码（主要诊断编码首字母为S或T时必填）
     * @param $data
     * @return bool
     */
    public function rule70($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $zyzd = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $zyzd = $diagnosis['ZDBM'];
                break;
            }
        }

        if (stripos($zyzd, 'S') === 0 || stripos($zyzd, 'T') === 0) {
            if (empty($data['data']['ABG01C'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * 损伤和中毒外部原因（主要诊断编码首字母为S或T时必填）
     * @param $data
     * @return bool
     */
    public function rule71($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $zyzd = '';
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $zyzd = $diagnosis['ZDBM'];
                break;
            }
        }

        if (stripos($zyzd, 'S') === 0 || stripos($zyzd, 'T') === 0) {
            if (empty($data['data']['ABG01N'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * 过敏药物名称
     * @param $data
     * @return bool
     */
    public function rule72($data)
    {
        $AEB02C = $data['data']['AEB02C'] ?? '';
        $AEB01 = $data['data']['AEB01'] ?? '';
        $res = false;
        if ($AEB02C == 1 && !empty($AEB01)) {
            $res = true;
        } elseif ($AEB02C == 2 && (empty($AEB01) || $AEB01 == '无' || $AEB01 == '-' || is_numeric($AEB01))) {
            $res = true;
        }
        return $res;
    }

    /**
     * 手术操作级别
     * @param $data
     * @return bool
     */
    public function rule73($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $SSJB = false;
        foreach ($data['operation'] as $operation) {
            if (in_array($operation['SSPB'], ['手术', '介入治疗']) && mb_strlen($operation['SSJB']) < 1) {
                $SSJB = true;
                break;
            }
        }
        if ($SSJB) {
            return true;
        }
        return false;
    }

    /**
     * 切口愈合等级
     * @param $data
     * @return bool
     */
    public function rule77($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $YHDJ = false;
        foreach ($data['operation'] as $operation) {
            if (!empty($operation['SSJB']) && mb_strlen(trim($operation['QKDJ'])) < 1) {
                $YHDJ = true;
                break;
            }
        }
        if ($YHDJ) {
            return true;
        }
        return false;
    }

    /**
     * 新生儿入院体重(克)
     * @param $data
     * @return bool
     */
    public function rule82($data)
    {
        $res = preg_match("/^[1-9][0-9]{2,3}$/", $data['data']['AAA42']);
        if ($data['data']['AAC11C'] == 287 && !$res) {
            return true;
        }
        return false;
    }

    /**
     * 出院31天再住院目的
     * @param $data
     * @return bool
     */
    public function rule83($data)
    {
        if ($data['data']['AEM03C'] == 1) {
            if (!empty($data['data']['AEM04'])) {
                return true;
            }
        } elseif ($data['data']['AEM03C'] == 2) {
            if (empty($data['data']['AEM04'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * 病理诊断编码只能以M开头
     * @param $data
     * @return bool
     */
    public function rule86($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
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
            if ($res && !empty($data['data']['ABF01C'])) {
                $str = mb_substr($data['data']['ABF01C'], 0, 1);
                if ($str != 'M') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 出院诊断编码（12岁及以下儿童出院诊断不应编为C50-C63(乳房、女性及男性生殖器恶性肿瘤)）
     * @param $data
     * @return bool
     */
    public function rule87($data)
    {
        $AAA04 = !empty($data['data']['AAA04']) ? $data['data']['AAA04'] : 0;
        if ($AAA04 > 12 || empty($data['diagnosis'])) {
            return false;
        }

        $arr = ['C50', 'C51', 'C52', 'C53', 'C54', 'C55', 'C56', 'C57', 'C58', 'C59', 'C60', 'C61', 'C62', 'C63'];
        $res = false;
        foreach ($arr as $value) {
            if ($res) {
                break;
            }
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $res = true;
                    break;
                }
            }
        }

        return $res;
    }

    /**
     * 出院诊断编码（12岁及以下儿童出院诊断不应编为O00-O99(妊娠、分娩和产褥期疾病)）
     * @param $data
     * @return bool
     */
    public function rule88($data)
    {
        $AAA04 = !empty($data['data']['AAA04']) ? $data['data']['AAA04'] : 0;
        if ($AAA04 > 12 || empty($data['diagnosis'])) {
            return false;
        }

        $arr = ['O00', 'O01', 'O02', 'O03', 'O04', 'O05', 'O06', 'O07', 'O08', 'O09'];
        for ($i = 10; $i <= 99; $i++) {
            $arr[] = 'O' . $i;
        }
        $res = false;
        foreach ($arr as $value) {
            if ($res) {
                break;
            }
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $res = true;
                    break;
                }
            }
        }

        return $res;
    }

    /**
     * 出院诊断编码（12岁及以下儿童出院诊断不应编为"D24-D29"女性及男性生殖器官良性肿瘤）
     * @param $data
     * @return bool
     */
    public function rule89($data)
    {
        $AAA04 = !empty($data['data']['AAA04']) ? $data['data']['AAA04'] : 0;
        if ($AAA04 > 12 || empty($data['diagnosis'])) {
            return false;
        }

        $arr = ['D24', 'D25', 'D26', 'D27', 'D28', 'D29'];
        $res = false;
        foreach ($arr as $value) {
            if ($res) {
                break;
            }
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $res = true;
                    break;
                }
            }
        }

        return $res;
    }

    /**
     * 出院诊断编码（5岁以上儿童出院诊断不应编为P00-P96（起源于围生期某些情况））
     * @param $data
     * @return bool
     */
    public function rule91($data)
    {
        $AAA04 = !empty($data['data']['AAA04']) ? $data['data']['AAA04'] : 0;
        if ($AAA04 <= 5 || empty($data['diagnosis'])) {
            return false;
        }

        $str = 'P00,P01,P02,P03,P04,P05,P06,P07,P08,P09,P10,P11,P12,P13,P14,,P15,P16,P17,P18,P19,P20,P21,P22,P23,P24,P25,P26,P27,P28,P29,P30,P31,P32,P33,P34,P35,P36,P37,P38,P39,P40,P41,P42,P43,P44,P45,P46,P47,P48,P49,P50,P51,P52,P53,P54,P55,P56,P57,P58,P59,P60,P61,P62,P63,P64,P65,P66,P67,P68,P69,P70,P71,P72,P73,P74,P75,P76,P77,P78,P79,P80,P81,P82,P83,P84,P85,P86,P87,P88,P89,P90,P91,P92,P93,P94,P95,P96';
        $arr = explode(',', $str);
        $res = false;
        foreach ($arr as $value) {
            if ($res) {
                break;
            }
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $res = true;
                    break;
                }
            }
        }

        return $res;
    }

    /**
     * 出院诊断编码（女性出院诊断不应编"D29.1"(前列腺良性肿瘤)。）
     * @param $data
     * @return bool
     */
    public function rule92($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        if ($AAA02C != 2 || empty($data['diagnosis'])) {
            return false;
        }

        $res = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'D29.1') === 0) {
                $res = true;
                break;
            }
        }

        return $res;
    }

    /**
     * 女性出院诊断不应编"C60-C63"(男性生殖器官恶性肿瘤)。
     * @param $data
     * @return bool
     */
    public function rule93($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        if ($AAA02C != 2 || empty($data['diagnosis'])) {
            return false;
        }

        $str = 'C60,C61,C62,C63';
        $arr = explode(',', $str);
        $res = false;
        foreach ($arr as $value) {
            if ($res) {
                break;
            }
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $res = true;
                    break;
                }
            }
        }

        return $res;
    }

    /**
     * 女性出院诊断不应编"N40-N51"(男性生殖器官疾病)。
     * @param $data
     * @return bool
     */
    public function rule94($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        if ($AAA02C != 2 || empty($data['diagnosis'])) {
            return false;
        }

        $str = 'N40,N41,N42,N43,N44,N45,N46,N47,N48,N49,N50,N51';
        $arr = explode(',', $str);
        $res = false;
        foreach ($arr as $value) {
            if ($res) {
                break;
            }
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $res = true;
                    break;
                }
            }
        }

        return $res;
    }

    /**
     * 女性出院诊断不应编"K40"(腹股沟疝)。
     * @param $data
     * @return bool
     */
    public function rule95($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        if ($AAA02C != 2 || empty($data['diagnosis'])) {
            return false;
        }

        $res = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'K40') === 0) {
                $res = true;
                break;
            }
        }

        return $res;
    }

    /**
     * 男性出院诊断不应编"C51-C58"(女性生殖器官恶性肿瘤)。
     * @param $data
     * @return bool
     */
    public function rule96($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        if ($AAA02C != 1 || empty($data['diagnosis'])) {
            return false;
        }

        $str = 'C51,C52,C53,C54,C55,C56,C57,C58';
        $arr = explode(',', $str);
        $res = false;
        foreach ($arr as $value) {
            if ($res) {
                break;
            }
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $res = true;
                    break;
                }
            }
        }

        return $res;
    }

    /**
     * 男性出院诊断不应编"D06"(子宫颈原位恶性肿瘤)。
     * @param $data
     * @return bool
     */
    public function rule97($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        if ($AAA02C != 1 || empty($data['diagnosis'])) {
            return false;
        }

        $res = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'D06') === 0) {
                $res = true;
                break;
            }
        }

        return $res;
    }

    /**
     * 男性出院诊断不应编"D24"(乳房良性肿瘤)。
     * @param $data
     * @return bool
     */
    public function rule98($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        if ($AAA02C != 1 || empty($data['diagnosis'])) {
            return false;
        }

        $res = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'D24') === 0) {
                $res = true;
                break;
            }
        }

        return $res;
    }

    /**
     * 男性出院诊断不应编"D25"(子宫平滑肌瘤)。
     * @param $data
     * @return bool
     */
    public function rule99($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        if ($AAA02C != 1 || empty($data['diagnosis'])) {
            return false;
        }

        $res = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'D25') === 0) {
                $res = true;
                break;
            }
        }

        return $res;
    }

    /**
     * 男性出院诊断不应编"D27"(卵巢良性肿瘤)
     * @param $data
     * @return bool
     */
    public function rule100($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        if ($AAA02C != 1 || empty($data['diagnosis'])) {
            return false;
        }

        $res = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'D27') === 0) {
                $res = true;
                break;
            }
        }

        return $res;
    }

    /**
     * 男性出院诊断不应编"N70-N77"(女性盆腔气管炎性疾病)。
     * @param $data
     * @return bool
     */
    public function rule101($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        if ($AAA02C != 1 || empty($data['diagnosis'])) {
            return false;
        }

        $str = 'N70,N71,N72,N3,N74,N75,N76,N77';
        $arr = explode(',', $str);
        $res = false;
        foreach ($arr as $value) {
            if ($res) {
                break;
            }
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $res = true;
                    break;
                }
            }
        }

        return $res;
    }

    /**
     * 男性出院诊断不应编"N80-N98"(女性生殖道非炎性疾病)。
     * @param $data
     * @return bool
     */
    public function rule102($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        if ($AAA02C != 1 || empty($data['diagnosis'])) {
            return false;
        }

        $str = 'N80,N81,N82,N83,N84,N85,N86,N87,N88,N89,N90,N91,N92,N93,N94,N95,N96,N97,N98';
        $arr = explode(',', $str);
        $res = false;
        foreach ($arr as $value) {
            if ($res) {
                break;
            }
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $res = true;
                    break;
                }
            }
        }

        return $res;
    }

    /**
     * 男性出院诊断不应编"O00-O99"(妊娠、分娩和产褥期疾病)。
     * @param $data
     * @return bool
     */
    public function rule103($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        if ($AAA02C != 1 || empty($data['diagnosis'])) {
            return false;
        }

        $str = 'O00,O01,O02,O03,O4,O05,O06,O07,O8,O09,O10,O11,O12,O13,O14,O15,O16,O17,O18,O19,O20,O21,O22,O23,O24,O25,O26,O27,O28,O29,O30,O31,O32,O33,O34,O35,O36,O37,O38,O39,O40,O41,O42,O43,O44,O45,O46,O47,O48,O49,O50,O51,O52,O53,O54,O55,O56,O57,O58,O59,O60,O61,O62,O63,O64,O65,O66,O67,O68,O69,O70,O71,O72,O73,O74,O75,O76,O77,O78,O79,O80,O81,O82,O83,O84,O85,O86,O87,O88,O89,O90,O91,O92,O93,O94,O95,O96,O97,O98,O99';
        $arr = explode(',', $str);
        $res = false;
        foreach ($arr as $value) {
            if ($res) {
                break;
            }
            foreach ($data['diagnosis'] as $diagnosis) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $res = true;
                    break;
                }
            }
        }

        return $res;
    }

    /**
     * 男性主要诊断编码不规范
     * @param $data
     * @return bool
     */
    public function rule104($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        if ($AAA02C != 1 || empty($data['diagnosis'])) {
            return false;
        }

        $str = 'A34,B37.3,C79.6,D07.0,D07.1,D07.2,D07.3,D26,D28,D39,E28,E89.4,F52.5,F53,I86.3,L29.2,M80.0,M80.1,M81.0,M81.1,M83.0,N78,N79,N80,N81,N82,N83,N84,N85,N86,N87,N88,N89,N90,N91,N92,N93,N94,N95,N96,N97,N98,N99.2,N99.3,P54.6,Q50,Q51,Q52,R87,S31.4,S37.4,S37.5,S37.6,T19.2,T19.3,T83.3,Z01.4,Z12.4,Z30.1,Z30.3,Z30.5,Z31.1,Z31.2,Z32,Z33,Z34,Z35,Z36,Z37,Z39,Z287.5,Z97.5,O00,O01,O02,O03,O04,O05,O06,O07,O8,O09,O10,O11,O12,O13,O14,O15,O16,O17,O18,O19,O20,O21,O22,O23,O24,O25,O26,O27,O28,O29,O30,O31,O32,O33,O34,O35,O36,O37,O38,O39,O40,O41,O42,O43,O44,O45,O46,O47,O48,O49,O50,O51,O52,O53,O54,O55,O56,O57,O58,O59,O60,O61,O62,O63,O64,O65,O66,O67,O68,O69,O70,O71,O72,O73,O74,O75,O76,O77,O78,O79,O80,O81,O82,O83,O84,O85,O86,O87,O88,O89,O90,O91,O92,O93,O94,O95,O96,O97,O98,O99';
        $arr = explode(',', $str);
        $res = false;
        foreach ($arr as $value) {
            if ($res) {
                break;
            }
            foreach ($data['diagnosis'] as $diagnosis) {
                if ($diagnosis['ZZPB'] == 1 && stripos($diagnosis['ZDBM'], $value) === 0) {
                    $res = true;
                    break;
                }
            }
        }

        return $res;
    }

    /**
     * 女性主要诊断编码不规范
     * @param $data
     * @return bool
     */
    public function rule105($data)
    {
        $AAA02C = !empty($data['data']['AAA02C']) ? $data['data']['AAA02C'] : '';
        if ($AAA02C != 2 || empty($data['diagnosis'])) {
            return false;
        }

        $str = 'B26.0,C60,C61,C62,C63,D07.4,D07.5,D07.6,D17.6,D29,D40,E29,E89.5,F52.4,I86.1,L29.1,N40,N41,N42,N43,N44,N45,N46,N47,N48,N49,N50,N51,Q53,Q54,Q55,R86,S31.2,S31.3,Z12.5';
        $arr = explode(',', $str);
        $res = false;
        foreach ($arr as $value) {
            if ($res) {
                break;
            }
            foreach ($data['diagnosis'] as $diagnosis) {
                if ($diagnosis['ZZPB'] == 1 && stripos($diagnosis['ZDBM'], $value) === 0) {
                    $res = true;
                    break;
                }
            }
        }

        return $res;
    }

    /**
     * 出生地市
     * @param $data
     * @return bool
     */
    public function rule1344($data)
    {
        if (mb_strlen(trim($data['data']['AAA10'])) < 2 || is_numeric(trim($data['data']['AAA10']))) {
            return true;
        }
        return false;
    }

    /**
     * 出生地县
     * @param $data
     * @return bool
     */
    public function rule1345($data)
    {
        if (mb_strlen(trim($data['data']['AAA11'])) < 2 || is_numeric(trim($data['data']['AAA11']))) {
            return true;
        }
        return false;
    }

    /**
     * 籍贯市（籍贯市未填写）
     * @param $data
     * @return bool
     */
    public function rule1346($data)
    {
        if (mb_strlen(trim($data['data']['AAA44'])) < 2 || is_numeric(trim($data['data']['AAA44']))) {
            return true;
        }
        return false;
    }

    /**
     * 户籍市（户籍市未填写）
     * @param $data
     * @return bool
     */
    public function rule1347($data)
    {
        if (mb_strlen(trim($data['data']['AAA46'])) < 2 || is_numeric(trim($data['data']['AAA46']))) {
            return true;
        }
        return false;
    }

    /**
     * 户籍县（户籍县未填写）
     * @param $data
     * @return bool
     */
    public function rule1348($data)
    {
        if (mb_strlen(trim($data['data']['AAA47'])) < 2 || is_numeric(trim($data['data']['AAA47']))) {
            return true;
        }
        return false;
    }

    /**
     * 户籍详细地址（户籍详细地址未填写）
     * @param $data
     * @return bool
     */
    public function rule1349($data)
    {
        if (mb_strlen(trim($data['data']['AAA12'])) < 2 || is_numeric(trim($data['data']['AAA12']))) {
            return true;
        }
        return false;
    }

    /**
     * 现住址市
     * @param $data
     * @return bool
     */
    public function rule1350($data)
    {
        if (mb_strlen(trim($data['data']['AAA49'])) < 2 || is_numeric(trim($data['data']['AAA49']))) {
            return true;
        }
        return false;
    }

    /**
     * 现住址县
     * @param $data
     * @return bool
     */
    public function rule1351($data)
    {
        if (mb_strlen(trim($data['data']['AAA50'])) < 2 || is_numeric(trim($data['data']['AAA50']))) {
            return true;
        }
        return false;
    }

    /**
     * 现住址详细地址
     * @param $data
     * @return bool
     */
    public function rule1352($data)
    {
        if ($data['data']['AAA15'] == '-') {
            return false;
        } elseif (strlen($data['data']['AAA15']) < 6 || is_numeric($data['data']['AAA15'])) {
            return true;
        }
        return false;
    }

    /**
     * 主要诊断编码
     * @param $data
     * @return bool
     */
    public function rule1356($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $str = 'B95,B96,B97';
        $arr = explode(',', $str);
        $res = false;
        foreach ($arr as $value) {
            if ($res) {
                break;
            }
            foreach ($data['diagnosis'] as $diagnosis) {
                if ($diagnosis['ZZPB'] == 1 && stripos($diagnosis['ZDBM'], $value) === 0) {
                    $res = true;
                    break;
                }
            }
        }

        return $res;
    }

    /**
     * 出院病房不能为空 todo
     * @param $data
     * @return bool
     */
    public function rule1359($data)
    {
        if (empty(trim($data['data']['AAC03']))) {
            return true;
        }
        return false;
    }

    /**
     * i50.9是未特指的心力衰竭，原则上不能作为主要诊断
     * @param $data
     * @return bool
     */
    public function rule1362($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $str = 'I50.905,I50.904,I50.903,I50.902,I50.900x023,I50.900x016,I50.900x015,I50.900x014,I50.900x010,I50.900x009,I50.900x008,I50.900x007,I50.900x002';
        $arr = explode(',', $str);
        $res = false;
        foreach ($arr as $value) {
            if ($res) {
                break;
            }
            foreach ($data['diagnosis'] as $diagnosis) {
                if ($diagnosis['ZZPB'] == 1 && stripos($diagnosis['ZDBM'], $value) === 0) {
                    $res = true;
                    break;
                }
            }
        }

        return $res;
    }

    /**
     * 主要诊断编码不能与其他诊断编码重复
     * @param $data
     * @return bool
     */
    public function rule1363($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $zyzdbm = '';
        $qtzdbmArr = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] == 1) {
                $zyzdbm = $diagnosis['ZDBM'];
            } else {
                $qtzdbmArr[] = $diagnosis['ZDBM'];
            }
        }
        if (empty($zyzdbm)) {
            return false;
        }
        if (in_array($zyzdbm, $qtzdbmArr)) {
            return true;
        }

        return false;
    }

    /**
     * 其他诊断编码中不能出现重复诊断编码
     * @param $data
     * @return bool
     */
    public function rule1364($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $qtzdbmArr = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZZPB'] != 1) {
                $qtzdbmArr[] = $diagnosis['ZDBM'];
            }
        }
        if (count($qtzdbmArr) <= 1) {
            return false;
        } elseif (count($qtzdbmArr) != count(array_unique($qtzdbmArr))) {
            return true;
        }

        return false;
    }

    /**
     * 诊断编码不能同时存在：B05.0-B05.8为麻疹伴并发症，B05.9为麻疹不伴并发症
     * @param $data
     * @return bool
     */
    public function rule1365($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $str = 'B05.0,B05.1,B05.2,B05.3,B05.4,B05.5,B05.6,B05.7,B05.1,B05.8';
        $arr = explode(',', $str);
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($diagnosis['ZDBM'], 'B05.9') === 0) {
                $res2 = 1;
            } else {
                foreach ($arr as $value) {
                    if (stripos($diagnosis['ZDBM'], $value) === 0) {
                        $res1 = 1;
                        break;
                    }
                }
            }
        }

        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * 诊断编码不能同时存在：H33.0视网膜脱离伴视网膜断裂，H33.3视网膜断裂不伴有脱离
     * @param $data
     * @return bool
     */
    public function rule1366($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($diagnosis['ZDBM'], 'H33.0') === 0) {
                $res1 = 1;
            } elseif (stripos($diagnosis['ZDBM'], 'H33.3') === 0) {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * 诊断编码不能同时存在：K35.0急性阑尾炎伴有弥漫性腹膜炎和K35.9未特指的急性阑尾炎，不伴有弥漫性腹膜炎
     * @param $data
     * @return bool
     */
    public function rule1368($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($diagnosis['ZDBM'], 'K35.0') === 0) {
                $res1 = 1;
            } elseif (stripos($diagnosis['ZDBM'], 'K35.9') === 0) {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * K72肝衰竭和K70.4酒精性肝衰竭，相对编码不能同时存在
     * @param $data
     * @return bool
     */
    public function rule1369($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($diagnosis['ZDBM'], 'K72') === 0) {
                $res1 = 1;
            } elseif (stripos($diagnosis['ZDBM'], 'K70.4') === 0) {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * K72肝衰竭和B15-B19病毒性肝炎，相对编码不能同时存在
     * @param $data
     * @return bool
     */
    public function rule1370($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $arr = ['B15', 'B16', 'B17', 'B18', 'B19'];
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($diagnosis['ZDBM'], 'K72') === 0) {
                $res1 = 1;
            } else {
                foreach ($arr as $value) {
                    if (stripos($diagnosis['ZDBM'], $value) === 0) {
                        $res2 = 1;
                        break;
                    }
                }
            }
        }

        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * 同时有K80.2胆囊结石+K81.1-K81.9胆囊炎，应合并编码为K80.1胆囊结石伴胆囊炎
     * @param $data
     * @return bool
     */
    public function rule1371($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $str = 'K81.1,K81.2,K81.3,K81.4,K81.5,K81.6,K81.7,K81.8,K81.9';
        $arr = explode(',', $str);
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($diagnosis['ZDBM'], 'K80.2') === 0) {
                $res1 = 1;
            } else {
                foreach ($arr as $value) {
                    if (stripos($diagnosis['ZDBM'], $value) === 0) {
                        $res2 = 1;
                        break;
                    }
                }
            }
        }

        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * 同时有K80.2胆囊结石+K81.0急性胆囊炎，应合并编码为K80.0胆囊结石伴急性胆囊炎
     * @param $data
     * @return bool
     */
    public function rule1372($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($diagnosis['ZDBM'], 'K80.2') === 0) {
                $res1 = 1;
            } elseif (stripos($diagnosis['ZDBM'], 'K81.0') === 0) {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * 同时有K80.5胆管结石+K81胆囊炎，应合并编码为K80.4胆管结石伴胆囊炎
     * @param $data
     * @return bool
     */
    public function rule1373($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($diagnosis['ZDBM'], 'K80.5') === 0) {
                $res1 = 1;
            } elseif (stripos($diagnosis['ZDBM'], 'K81') === 0) {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * 同时有K80.5胆管结石+K83.0胆管炎，应合并编码为K80.3胆管结石伴胆管炎
     * @param $data
     * @return bool
     */
    public function rule1374($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($diagnosis['ZDBM'], 'K80.5') === 0) {
                $res1 = 1;
            } elseif (stripos($diagnosis['ZDBM'], 'K83.0') === 0) {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * 同时有I11高血压心脏病+I12高血压肾脏病，应合并编码为I13高血压心脏和肾脏病
     * @param $data
     * @return bool
     */
    public function rule1375($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($diagnosis['ZDBM'], 'I11') === 0) {
                $res1 = 1;
            } elseif (stripos($diagnosis['ZDBM'], 'I12') === 0) {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * 同时有J44.900慢性阻塞性肺疾病+J18.9肺炎，应合并编码为J44.000慢性阻塞性肺病伴有急性下呼吸道感染
     * @param $data
     * @return bool
     */
    public function rule1376($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($diagnosis['ZDBM'], 'J44.900') === 0) {
                $res1 = 1;
            } elseif (stripos($diagnosis['ZDBM'], 'J18.9') === 0) {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * 同时有J44.100慢性阻塞性肺病伴有急性加重+J42.x00慢性支气管炎+J43.904阻塞性肺气肿，应合并编码为J44.100x001慢性阻塞性肺气肿性支气管炎伴急性加重
     * @param $data
     * @return bool
     */
    public function rule1377($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        $res2 = 0;
        $res3 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2 && $res3) {
                break;
            }
            if (stripos($diagnosis['ZDBM'], 'J44.100') === 0) {
                $res1 = 1;
            } elseif (stripos($diagnosis['ZDBM'], 'J42.x00') === 0) {
                $res2 = 1;
            } elseif (stripos($diagnosis['ZDBM'], 'J43.904') === 0) {
                $res3 = 1;
            }
        }

        if ($res1 && $res2 && $res3) {
            return true;
        }

        return false;
    }

    /**
     * 同时有2型糖尿病性肾病+慢性肾脏病2期，应合并名称为2型糖尿病肾病II期
     * @param $data
     * @return bool
     */
    public function rule1378($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            if ($diagnosis['ZDMC'] == '2型糖尿病性肾病') {
                $res1 = 1;
            } elseif ($diagnosis['ZDMC'] == '慢性肾脏病2期') {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * 同时有2型糖尿病肾病+慢性肾衰竭尿毒症期，应合并名称为2型糖尿病肾病V期
     * @param $data
     * @return bool
     */
    public function rule1379($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            if ($diagnosis['ZDMC'] == '2型糖尿病') {
                $res1 = 1;
            } elseif ($diagnosis['ZDMC'] == '慢性肾衰竭尿毒症期') {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * 同时有急性喉炎+喉梗阻，应合并名称为急性梗阻性喉炎[哮吼]
     * @param $data
     * @return bool
     */
    public function rule1380($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            if ($diagnosis['ZDMC'] == '喉梗阻') {
                $res1 = 1;
            } elseif ($diagnosis['ZDMC'] == '急性喉炎') {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * 同时有急性喉炎+急性咽炎，应合并名称为急性咽喉炎
     * @param $data
     * @return bool
     */
    public function rule1381($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            if ($diagnosis['ZDMC'] == '急性咽炎') {
                $res1 = 1;
            } elseif ($diagnosis['ZDMC'] == '急性喉炎') {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * 同时有扁桃体肥大+腺样体肥大，应合并名称为扁桃体肥大伴有腺样体肥大
     * @param $data
     * @return bool
     */
    public function rule1382($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            if ($diagnosis['ZDMC'] == '扁桃体肥大') {
                $res1 = 1;
            } elseif ($diagnosis['ZDMC'] == '腺样体肥大') {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * 同时有慢性支气管炎+肺气肿，应合并名称为慢性支气管炎伴肺气肿
     * @param $data
     * @return bool
     */
    public function rule1383($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            if ($diagnosis['ZDMC'] == '慢性支气管炎') {
                $res1 = 1;
            } elseif ($diagnosis['ZDMC'] == '肺气肿') {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * 同时有肺气肿+肺大疱，应合并名称为大疱性肺气肿
     * @param $data
     * @return bool
     */
    public function rule1384($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            if ($diagnosis['ZDMC'] == '肺气肿') {
                $res1 = 1;
            } elseif ($diagnosis['ZDMC'] == '肺大疱') {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * 同时有变应性鼻炎+咳嗽变异性哮喘+支气管哮喘，应合并名称为过敏性鼻炎伴哮喘
     * @param $data
     * @return bool
     */
    public function rule1385($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        $res2 = 0;
        $res3 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2 && $res3) {
                break;
            }
            if ($diagnosis['ZDMC'] == '变应性鼻炎') {
                $res1 = 1;
            } elseif ($diagnosis['ZDMC'] == '咳嗽变异性哮喘') {
                $res2 = 1;
            } elseif ($diagnosis['ZDMC'] == '支气管哮喘') {
                $res3 = 1;
            }
        }

        if ($res1 && $res2 && $res3) {
            return true;
        }

        return false;
    }

    /**
     * 疾病诊断编码为P07.000x001  诊断名称：超低出生体重儿(750-999g)，新生儿体重范围应为750-999g
     * @param $data
     * @return bool
     */
    public function rule1388($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDBM'] == 'P07.000x001') {
                $res1 = 1;
                break;
            }
        }
        if ($res1 && ($data['data']['AEN01'] < 750 || $data['data']['AEN01'] > 999)) {
            return true;
        }

        return false;
    }

    /**
     * 疾病诊断编码为P07.100x003极低出生体重儿(1250-1499g)，新生儿体重范围应为1250-1499g
     * @param $data
     * @return bool
     */
    public function rule1389($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDBM'] == 'P07.100x003') {
                $res1 = 1;
                break;
            }
        }
        if ($res1 && ($data['data']['AEN01'] < 1250 || $data['data']['AEN01'] > 1499)) {
            return true;
        }

        return false;
    }

    /**
     * 疾病诊断编码为P07.100x002极低出生体重儿(1000-1249g)，新生儿体重范围应为1000-1249g
     * @param $data
     * @return bool
     */
    public function rule1390($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDBM'] == 'P07.100x002') {
                $res1 = 1;
                break;
            }
        }
        if ($res1 && ($data['data']['AEN01'] < 1000 || $data['data']['AEN01'] > 1249)) {
            return true;
        }

        return false;
    }

    /**
     * 疾病诊断编码为P07.100x004低出生体重儿(1500-2499g)，新生儿体重范围应为1500-2499g
     * @param $data
     * @return bool
     */
    public function rule1391($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDBM'] == 'P07.100x004') {
                $res1 = 1;
                break;
            }
        }
        if ($res1 && ($data['data']['AEN01'] < 1500 || $data['data']['AEN01'] > 2499)) {
            return true;
        }

        return false;
    }

    /**
     * 疾病编码P08.000特大婴儿，新生儿体重应在4500g以上
     * @param $data
     * @return bool
     */
    public function rule1392($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDBM'] == 'P08.000') {
                $res1 = 1;
                break;
            }
        }
        if ($res1 && $data['data']['AEN01'] < 4500) {
            return true;
        }

        return false;
    }

    /**
     * 疾病编码P08.000特大婴儿，新生儿体重在4500g以下的，应更换疾病诊断为P08.100x001大于胎龄儿
     * @param $data
     * @return bool
     */
    public function rule1393($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($diagnosis['ZDBM'] == 'P08.000') {
                $res1 = 1;
                break;
            }
        }
        if ($res1 && $data['data']['AEN01'] < 4500) {
            return true;
        }

        return false;
    }

    /**
     * 疾病编码范围在M08幼年型关节炎—M09分类于他处的疾病引起的幼年型关节炎，年龄范围应为0-17岁（除外新生儿
     * @param $data
     * @return bool
     */
    public function rule1395($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $str = 'M08,M09';
        $arr = explode(',', $str);
        $res1 = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            foreach ($arr as $value) {
                if (stripos($diagnosis['ZDBM'], $value) === 0) {
                    $res1 = true;
                    break;
                }
            }
        }
        if ($res1 && $data['data']['AAA04'] > 17) {
            return true;
        }

        return false;
    }

    /**
     * 码段为I05-I09的两个以上诊断编码，需合并到I08。
     * @param $data
     * @return bool
     */
    public function rule1396($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $res1 = 0;
        $res2 = 0;
        $res3 = 0;
        $res4 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'I05') === 0) {
                $res1 = 1;
            } elseif (stripos($diagnosis['ZDBM'], 'I06') === 0) {
                $res2 = 1;
            } elseif (stripos($diagnosis['ZDBM'], 'I07') === 0) {
                $res3 = 1;
            } elseif (stripos($diagnosis['ZDBM'], 'I09') === 0) {
                $res4 = 1;
            }
        }

        $count = $res1 + $res2 + $res3 + $res4;
        if ($count >= 2) {
            return true;
        }

        return false;
    }

    /**
     * 诊断中出现疾病诊断+肿瘤形态学诊断，更换诊断
     * @param $data
     * @return bool
     */
    public function rule1397($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }

        $str = 'C22.101M81600/3,C81.001M96590/3,C83.812M96730/3,C84.000M97000/3,C85.707M97190/3,C85.710M97160/3,C88.100M97620/3,C88.200M97630/3,C90.101M98310/1,C91.306M98341/3,C96.701M97271/3';
        $arr = explode(',', $str);
        $newArr = [];
        foreach ($data['diagnosis'] as $diagnosis) {
            if (in_array($diagnosis['ZDBM'], $arr) && !in_array($diagnosis['ZDBM'], $newArr)) {
                $newArr[] = $diagnosis['ZDBM'];
            }
        }

        if (count($newArr) > 1) {
            return true;
        }

        return false;
    }

    /**
     * 手术操作不能同时存在：07.62垂体腺部分切除术，经蝶骨入路；07.14垂体腺活组织检查，经蝶骨入路
     * @param $data
     * @return bool
     */
    public function rule1398($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($operation['SSCZBM'], '07.62') === 0) {
                $res1 = 1;
            } elseif (stripos($operation['SSCZBM'], '07.14') === 0) {
                $res2 = 1;
            }
        }
        if ($res1 && $res2) {
            return true;
        }
        return false;
    }

    /**
     * 手术操作不能同时存在：07.63垂体腺部分切除术，未特指入路；07.15垂体腺活组织检查NOS
     * @param $data
     * @return bool
     */
    public function rule1399($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($operation['SSCZBM'], '07.63') === 0) {
                $res1 = 1;
            } elseif (stripos($operation['SSCZBM'], '07.15') === 0) {
                $res2 = 1;
            }
        }
        if ($res1 && $res2) {
            return true;
        }
        return false;
    }

    /**
     * 手术操作不能同时存在：07.81胸腺部分切除术，开放性胸腺部分切除术；07.16胸腺活组织检查
     * @param $data
     * @return bool
     */
    public function rule1400($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($operation['SSCZBM'], '07.81') === 0) {
                $res1 = 1;
            } elseif (stripos($operation['SSCZBM'], '07.16') === 0) {
                $res2 = 1;
            }
        }
        if ($res1 && $res2) {
            return true;
        }
        return false;
    }

    /**
     * 手术操作不能同时存在：07.81胸腺部分切除术，开放性胸腺部分切除术；07.83胸腔镜下胸腺部分切除术
     * @param $data
     * @return bool
     */
    public function rule1401($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($operation['SSCZBM'], '07.81') === 0) {
                $res1 = 1;
            } elseif (stripos($operation['SSCZBM'], '07.83') === 0) {
                $res2 = 1;
            }
        }
        if ($res1 && $res2) {
            return true;
        }
        return false;
    }

    /**
     * 手术编码有13.0000去除晶状体异物NOS
     * 应更换手术为13.0100用磁吸法的去除晶状体异物/13.0200不使用磁吸法的去除晶状体异物/13.0201晶状体切开异物取出术
     * @param $data
     * @return bool
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
            return true;
        }
        return false;
    }

    /**
     * 手术编码有14.0000去除眼后节异物NOS
     * 应更换手术为14.0100用磁吸法去除眼后节异物/14.0101玻璃体异物磁吸术/14.0200不用磁吸法去除眼后节异物/14.0200x001眼后节异物去除术/14.0200x002玻璃体腔异物取出术/14.0201脉络膜切开异物取出术/14.0202后段眼球壁异物取出术
     * @param $data
     * @return bool
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
            return true;
        }
        return false;
    }

    /**
     * 手术编码有37.80首次或置换永久起搏器置入，装置类型未特指，更换手术编码为37.81-37.89
     * @param $data
     * @return bool
     */
    public function rule1405($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $res1 = 0;
        foreach ($data['operation'] as $operation) {
            if ($operation['SSCZBM'] == '37.8000') {
                $res1 = 1;
            }
        }
        if ($res1) {
            return true;
        }
        return false;
    }

    /**
     * 手术编码为79.2骨折开放性复位术不伴内固定，应更换手术编码为79.3骨折开放性复位术伴内固定
     * @param $data
     * @return bool
     */
    public function rule1406($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $res1 = 0;
        foreach ($data['operation'] as $operation) {
            if ($operation['SSCZBM'] == '79.2000') {
                $res1 = 1;
            }
        }
        if ($res1) {
            return true;
        }
        return false;
    }

    /**
     * 提示另编码包括：置入血管支架的数量00.45-00.48，治疗血管的数量00.40-00.43，入脑前血管经皮血管成形术00.61，颅外血管经皮粥样硬化切除术17.53，分支血管操作00.44
     * @param $data
     * @return bool
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
            return true;
        }
        return false;
    }

    /**
     * 提示另编码包括：非端对端吻合术（胸内或胸骨前食管吻合术及间置术）42.51-42.69，食管造口术42.10-42.19，胃造口术43.11-43.19
     * @param $data
     * @return bool
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
            return true;
        }
        return false;
    }

    /**
     * 提示另编码包括：非端对端的间置术或吻合术（胸内或胸骨前食管吻合术及间置术）42.51-42.69
     * @param $data
     * @return bool
     */
    public function rule1409($data)
    {
        $res1 = 0;
        foreach ($data['operation'] as $operation) {
            if ($operation['SSCZBM'] == '42.4200') {
                $res1 = 1;
            }
        }
        if ($res1) {
            return true;
        }
        return false;
    }

    /**
     * 手术编码需合并：12.5100x001前房角穿刺术+12.5200x001前房角切开术，需要合并到12.5300眼前房角切开伴眼前房角穿刺
     * @param $data
     * @return bool
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
            return true;
        }
        return false;
    }

    /**
     * 手术编码需合并：14.7401后入路玻璃体切割术+14.7500x001玻璃体腔内替代物注射术，需要合并到14.7202后入路玻璃体切割术伴替代物注入
     * @param $data
     * @return bool
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
            return true;
        }
        return false;
    }

    /**
     * 男性出院手术不应编：65-71女性生殖器官手术
     * @param $data
     * @return bool
     */
    public function rule1413($data)
    {
        if ($data['data']['AAA02C'] != 1 || empty($data['operation'])) {
            return false;
        }
        $arr = ['65', '66', '67', '68', '69', '70', '71'];
        $res1 = 0;
        foreach ($data['operation'] as $operation) {
            if ($res1) {
                break;
            }
            foreach ($arr as $value) {
                if (stripos($operation['SSCZBM'], $value) === 0) {
                    $res1 = 1;
                    break;
                }
            }
        }
        if ($res1) {
            return true;
        }
        return false;
    }

    /**
     * 女性出院手术不应编：60-64男性生殖器官手术
     * @param $data
     * @return bool
     */
    public function rule1414($data)
    {
        if (empty($data['operation'])) {
            return false;
        }
        $arr = ['60', '61', '62', '63', '64'];
        $res1 = 0;
        foreach ($data['operation'] as $operation) {
            if ($res1) {
                break;
            }
            foreach ($arr as $value) {
                if (stripos($operation['SSCZBM'], $value) === 0) {
                    $res1 = 1;
                    break;
                }
            }
        }
        if ($res1 && $data['data']['AAA02C'] == 2) {
            return true;
        }
        return false;
    }

    /**
     * 17.3为腹腔镜大肠部分切除术，48.6为直肠其他切除术 todo
     * @param $data
     * @return bool
     */
    public function rule1415($data)
    {
        return false;
    }

    /**
     * 01.3为大脑和脑膜切开术，92.3为立体定向放射外科 todo
     * @param $data
     * @return bool
     */
    public function rule1416($data)
    {
        return false;
    }

    /**
     * 不能作为主要手术的编码范围：00.40-00.43手术血管的数量、00.45-00.48置入支架的数量、00.9其他操作和介入、00.74-00.77任何轴面类型
     * @param $data
     * @return bool
     */
    public function rule1417($data)
    {
        if (empty($data['operation'])) {
            return false;
        }

        $str = '00.4000,00.4100,00.4200,00.4300,00.4301,00.4302,00.4500,00.4600,00.4700,00.4801,00.4802,00.9100,00.9200,00.9300,00.9400,00.7400,00.7500,00.7600,00.7601,00.7700';
        $arr = explode(',', $str);
        $SSCZBM = '';
        foreach ($data['operation'] as $operation) {
            if ($operation['SFZYSS'] == 1) {
                $SSCZBM = $operation['SSCZBM'];
            }
        }
        if (in_array($SSCZBM, $arr)) {
            return $SSCZBM . ' 不能作为主要手术';
        }
        return false;
    }

    /**
     * 手术编码81.0为脊柱融合术，共有5个手术步骤。包括： todo
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
        return false;
    }

    /**
     * 是否有出院31日内再住院计划（病案首页是否有出院31天内再住院计划，如果选择无，目的不能为空格、-、文字。）
     * @param $data
     * @return bool
     */
    public function rule1419($data)
    {
        if ($data['data']['AEM03C'] == 1 && mb_strlen(trim($data['data']['AEM04'])) > 0) {
            return true;
        }
        return false;
    }

    /**
     * 是否有出院31日内再住院计划（病案首页是否有出院31天内再住院计划，如果选择有，目的必填）
     * @param $data
     * @return bool
     */
    public function rule1420($data)
    {
        if ($data['data']['AEM03C'] == 2 && strlen(trim($data['data']['AEM04'])) < 1) {
            return true;
        }
        return false;
    }

    /**
     * 手术编码（病案首页有手术操作，手术级别、手术类型、术者必填【术者信息没有】）
     * @param $data
     * @return bool
     */
    public function rule1421($data)
    {
        $res = 0;
        foreach ($data['operation'] as $operation) {
            if (in_array($operation['SSPB'], ['手术', '介入治疗'])) {
                if (empty($operation['SSCZBM']) || empty($operation['SSJB']) || empty($operation['SSLX']) || empty($operation['SZXM'])) {
                    $res = 1;
                    break;
                }
            }
        }
        if ($res) {
            return true;
        }
        return false;
    }

    /**
     * 病案首页有麻醉方式，麻醉医师必填
     * @param $data
     * @return bool
     */
    public function rule1422($data)
    {
        $res = 0;
        foreach ($data['operation'] as $operation) {
            if (!empty($operation['MZFS']) && empty($operation['MZYSXM'])) {
                $res = 1;
                break;
            }
        }
        if ($res) {
            return true;
        }
        return false;
    }

    /**
     * 病案首页有麻醉医师，麻醉方式必填
     * @param $data
     * @return bool
     */
    public function rule1423($data)
    {
        $res = 0;
        foreach ($data['operation'] as $operation) {
            if (!empty($operation['MZYSXM']) && empty($operation['MZFS'])) {
                $res = 1;
                break;
            }
        }
        if ($res) {
            return true;
        }
        return false;
    }

    /**
     * 主要诊断编码（主要诊断为T88.6-T88.7，药物过敏应为有，且应填写过敏药物）
     * @param $data
     * @return bool
     */
    public function rule1424($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }
        $arr = ['T88.6', 'T88.7'];
        $res = false;
        foreach ($arr as $value) {
            if ($res) {
                break;
            }
            foreach ($data['diagnosis'] as $diagnosis) {
                if ($diagnosis['ZZPB'] == 1 && stripos($diagnosis['ZDBM'], $value) === 0) {
                    $res = true;
                    break;
                }
            }
        }

        if ($res && ($data['data']['AEB02C'] != 2 || empty($data['data']['AEB01']))) {
            return true;
        }

        return false;
    }

    /**
     * 主要诊断为C77-C79，病理诊断编码应为M****\/6
     * @param $data
     * @return bool
     */
    public function rule1425($data)
    {
        if (empty($data['diagnosis'])) {
            return false;
        }
        $arr = ['C77', 'C78', 'C79'];
        $res = false;
        foreach ($arr as $value) {
            if ($res) {
                break;
            }
            foreach ($data['diagnosis'] as $diagnosis) {
                if ($diagnosis['ZZPB'] == 1 && stripos($diagnosis['ZDBM'], $value) === 0) {
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
            return true;
        }

        return false;
    }

    /**
     * 病案首页离院方式为 医嘱转院 和 医嘱转社区卫生服务机构/乡镇卫生院，拟接受医疗机构名称必填
     * @param $data
     * @return bool
     */
    public function rule1427($data)
    {
        if (in_array($data['data']['AEM01C'], [2, 3]) && $data['data']['ZA03'] == '') {
            return true;
        }
        return false;
    }

    /**
     * 病案首页病案质量为乙或丙，提示是否应修改为甲
     * @param $data
     * @return bool
     */
    public function rule1428($data)
    {
        if (in_array($data['data']['AED01C'], [2, 3])) {
            return true;
        }
        return false;
    }

    /**
     * 年龄（病案首页年龄小于6岁，职业应选择其他）
     * @param $data
     * @return bool
     */
    public function rule1429($data)
    {
        if (($data['data']['AAA04'] == '' || $data['data']['AAA04'] == null) && ($data['data']['AAA40'] == '' || $data['data']['AAA40'] == null)) {
            return false;
        }

        $AAA04 = !empty($data['data']['AAA04']) ? $data['data']['AAA04'] : 0;
        $AAA18C = !empty($data['data']['AAA18C']) ? $data['data']['AAA18C'] : '';
        if ($AAA04 < 6 && !in_array($AAA18C, [14, 17])) {
            return true;
        }
        return false;
    }

    /**
     * 病案首页质控日期应大于等于出院时间
     * @param $data
     * @return bool
     */
    public function rule1430($data)
    {
        $AED04 = date('Y-m-d', strtotime($data['data']['AED04']));
        $AAC01 = date('Y-m-d', strtotime($data['data']['AAC01']));
        if ($AED04 < $AAC01) {
            return true;
        }
        return false;
    }

    /**
     * 身份号 男最后二位奇数，女最后二位是偶数
     * @param $data
     * @return bool
     */
    public function rule1431($data)
    {
        $AAA07 = $data['data']['AAA07'];
        if (empty($AAA07)) {
            return false;
        }
        $str = mb_substr($AAA07, 16, 1);
        $AAA02C = $data['data']['AAA02C'];
        if ($str % 2 === 0) {
            if ($AAA02C != 2) {
                return true;
            }
        } elseif ($AAA02C != 1) {
            return true;
        }
        return false;
    }

    /**
     * 收费中含“视网膜激光光凝术”手术名称无关键字“视网膜”
     * @param $data
     * @return bool
     */
    public function rule1432($data)
    {
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return false;
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
                return true;
            }
        }

        return false;
    }

    /**
     * 收费明细 含【纤维支气管镜检查】 手术名称不含【纤维支气管镜检查】
     * @param $data
     * @return bool
     */
    public function rule1433($data)
    {
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return false;
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
            foreach ($data['operation'] as $operation) {
                if (stripos($operation['SSCZMC'], '纤维支气管镜检查') !== false) {
                    $res2 = true;
                    break;
                }
            }
            if (!$res2) {
                return true;
            }
        }

        return false;
    }

    /**
     * 收费明细 含【宫颈扩张术】手术名称不含【子宫颈扩张引产】
     * @param $data
     * @return bool
     */
    public function rule1434($data)
    {
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return false;
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
                return true;
            }
        }

        return false;
    }

    /**
     * 收费明细 含【急性缺血性脑卒中静脉溶栓治疗】 手术名称不含【脑动脉血栓溶解剂灌注】
     * @param $data
     * @return bool
     */
    public function rule1435($data)
    {
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return false;
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
                return true;
            }
        }

        return false;
    }

    /**
     * 收费明细 含【人工破膜术】手术名称不含【人工破膜引产】
     * @param $data
     * @return bool
     */
    public function rule1436($data)
    {
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return false;
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
                if (stripos($operation['SSCZMC'], '人工破膜引产') !== false) {
                    $res2 = true;
                    break;
                }
            }
            if (!$res2) {
                return true;
            }
        }

        return false;
    }

    /**
     * 收费明细 含【手取胎盘术】 手术名称不含【手取胎盘】
     * @param $data
     * @return bool
     */
    public function rule1437($data)
    {
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return false;
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
                return true;
            }
        }

        return false;
    }

    /**
     * 收费明细 含【无创呼吸机辅助呼吸，手术名称不含【无创呼吸机辅助通气】
     * @param $data
     * @return bool
     */
    public function rule1438($data)
    {
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return false;
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
                return true;
            }
        }

        return false;
    }

    /**
     * 收费明细 含【呼吸机辅助呼吸】 且 收费数量≥96，手术名称不含【呼吸机治疗[大于等于96小时]】
     * @param $data
     * @return bool
     */
    public function rule1439($data)
    {
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return false;
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
                return '收费明细含【呼吸机辅助呼吸】且收费数量≥96，手术名称无【呼吸机治疗[大于等于96小时]】';
            }
        }

        return false;
    }

    /**
     * 收费明细含【呼吸机辅助呼吸】 且收费数量＜96，手术名称不含【呼吸机治疗[小于96小时]】
     * @param $data
     * @return bool
     */
    public function rule1440($data)
    {
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return false;
        }

        $FYSL = 0;
        foreach ($fymcArr as $fyInfo) {
            if (stripos($fyInfo['FYMC'], '呼吸机辅助呼吸') !== false) {
                $FYSL += $fyInfo['FYSL'];
                break;
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
                return '收费明细含【呼吸机辅助呼吸】且收费数量＜96，手术名称不含【呼吸机治疗[小于96小时]】';
            }
        }

        return false;
    }

    /**
     * 收费明细含【呼吸机辅助呼吸】 ，有创呼吸机使用时间【不能为0，不能为空】
     * @param $data
     * @return bool
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
            if (stripos($fyInfo['FYMC'], '呼吸机辅助呼吸') !== false) {
                $res1 = true;
                $FYSL += $fyInfo['FYSL'];
            }
        }

        if ($res1 && empty($data['data']['AEL01'])) {
            $days = floor($FYSL / 24); // 计算天数
            $remainingHours = $FYSL % 24;   // 计算剩余的小时数
            return '有创呼吸机实际使用时间【' . $days . '天' . $remainingHours . '小时】';
        }

        return false;
    }

    /**
     * 收费明细 含【体外人工膜肺(ECMO)】 手术名称不含【体外膜氧合[ECMO]】
     * @param $data
     * @return bool
     */
    public function rule1442($data)
    {
        $fymcArr = $data['fy'];
        if (empty($fymcArr)) {
            return false;
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
                return true;
            }
        }

        return false;
    }

    /**
     * 诊断编码范围：N70 - N77或 Q50.401，应为女性病例
     * @param $data
     * @return bool
     */
    public function rule1443($data)
    {
        $arr = ['N70', 'N71', 'N72', 'N73', 'N74', 'N75', 'N76', 'N77', 'Q50.401'];
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

        if ($res && $data['data']['AAA02C'] != 2) {
            return true;
        }

        return $res;
    }

    /**
     * 新生儿病例，出院时天龄大于28天，出院诊断不能有P编码的诊断
     * @param $data
     * @return bool
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

        return $res;
    }

    /**
     * 新生儿病例，出院时天龄小于28天，不能出现Z编码诊断
     * @param $data
     * @return bool
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

        return $res;
    }

    /**
     * 其他诊断编码为T81-T88，容易出现医疗事故的编码，请核对
     * @param $data
     * @return bool
     */
    public function rule1448($data)
    {
        $arr = ['T81', 'T82', 'T83', 'T84', 'T85', 'T86', 'T87', 'T88'];
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

        return $res;
    }

    /**
     * 含“小儿肠炎”，年龄要小于2岁
     * @param $data
     * @return bool
     */
    public function rule1449($data)
    {
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
                return true;
            }
        }

        return false;
    }

    /**
     * K83.1梗阻性黄疸和K80胆结石，不能同时存在
     * @param $data
     * @return bool
     */
    public function rule1450($data)
    {
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
            return true;
        }

        return false;
    }

    /**
     * 身份证与出生日期要对应
     * @param $data
     * @return bool
     */
    public function rule1451($data)
    {
        $AAA07 = !empty($data['data']['AAA07']) ? mb_substr($data['data']['AAA07'], 6, 8) : '';
        $AAA03 = !empty($data['data']['AAA03']) ? date("Ymd", strtotime($data['data']['AAA03'])) : '';
        if (empty($AAA07) || empty($AAA03)) {
            return false;
        }
        if ($AAA07 != $AAA03) {
            return true;
        }
        return false;
    }

    /**
     * 护理天数之和要等于住院天数
     * @param $data
     * @return bool
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
        $HLTS_SUM = $TJHLTS + $YJHLTS + $EJHLTS + $SJHLTS;

        if ($AAC04 != $HLTS_SUM) {
            return true;
        }
        return false;
    }

    /**
     * 入院病情有诊断时误填或漏填
     * @param $data
     * @return bool
     */
    public function rule1453($data)
    {
        $ABC03C = config('dictionaries.ABC03C');
        $res = false;
        foreach ($data['diagnosis'] as $diagnosis) {
            if (!empty($diagnosis['ZDBM']) && empty($diagnosis['RYQK'])) {
                $res = true;
                break;
            }
        }

        return $res;
    }

    /**
     * 手术编码有96.7101，有创呼吸机使用时间应小于96小时
     * @param $data
     * @return bool
     */
    public function rule1454($data)
    {
        $res = false;
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['SSCZBM'], '96.7101') === 0) {
                $res = true;
                break;
            }
        }

        $AEL01 = !empty($data['data']['AEL01']) ? $data['data']['AEL01'] : 0;
        if ($res && $AEL01 >= 96) {
            return true;
        }

        return false;
    }

    /**
     * 手术编码有96.7201，有创呼吸机使用时间应大于等于96小时
     * @param $data
     * @return bool
     */
    public function rule1455($data)
    {
        $res = false;
        foreach ($data['operation'] as $operation) {
            if (stripos($operation['SSCZBM'], '96.7201') === 0) {
                $res = true;
                break;
            }
        }

        $AEL01 = !empty($data['data']['AEL01']) ? $data['data']['AEL01'] : 0;
        if ($res && $AEL01 < 96) {
            return true;
        }

        return false;
    }

    /**
     * 一级切口，愈合类别不能为丙级
     * @param $data
     * @return bool
     */
    public function rule1456($data)
    {
        $res = false;
        foreach ($data['operation'] as $operation) {
            if ($operation['QKDJ'] == 1 && $operation['YHDJ'] == 3) {
                $res = true;
                break;
            }
        }

        return $res;
    }

    /**
     * 诊断编码出现S00-S09，颅内损伤昏迷时间6个空必填一个数字
     * @param $data
     * @return bool
     */
    public function rule1457($data)
    {
        $arr = ['S00', 'S01', 'S02', 'S03', 'S04', 'S05', 'S06', 'S07', 'S08', 'S09'];
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
                return true;
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
        if ($JBDM) {
            return false;
        }
        $str = "T92.600x002:创伤性手指缺如|T90.501:陈旧性颅脑损伤|T75:陈旧性跟骨骨折|R65|B95:病原体的附加编码|B96:病原体的附加编码|B97:病原体的附加编码|T30:烧伤部位面积未特指|T31:烧伤部位面积未特指|T32:腐蚀上的面积编码|Z33:单纯妊娠状态|Z37:分娩结局 活产儿分娩地点|Z38:分娩结局 活产儿分娩地点|Z53:由于XX原因 治疗未实施|Z80:家族史|Z81:家族史|Z82:家族史|Z83:家族史|Z84:家族史|Z85:恶行肿瘤个人史|Z86:其他疾病个人史|Z87:其他疾病个人史|Z88:药物 生物制剂过敏史|Z89:肢体 器官后天缺失|Z90:肢体 器官后天缺失|Z91:危险因素个人史|Z92:医疗个人史|Z93:单纯人工造口状态|Z94:组织和器官移植状态|Z95:具有心脏 血管的植入物和移植物|Z96:具有其它功能性植入物和装置|Z97:具有其它功能性植入物和装置|Z98:其它单纯的手术后状态|Z99:依赖于可启动装置和机器|U80:耐药菌感染|U81:耐药菌感染|U82:耐药菌感染|U83:耐药菌感染|U84:耐药菌感染|U85:耐药菌感染|U86";
        $arr = explode('|', $str);
        foreach ($arr as $val) {
            $zdbm = explode(":", $val);
            if (stripos($JBDM, $zdbm[0]) === 0) {
                $res = $zdbm[0];
                if (!empty($zdbm[1])) {
                    $res .= '（' . $zdbm[1] . '）';
                } else {
                    $res .= ' ';
                }
                $res .= '无效的诊断编码';
                break;
            }
        }

        return $res;
    }

    /**
     * 离院方式等于2或3时，接收医疗机构名称不能为空
     * @param $data
     * @return bool
     */
    public function rule1459($data)
    {
        $AEM01C = config('dictionaries.AEM01C');
        if (in_array($data['data']['AEM01C'], [2, 3]) && empty($AEM01C[$data['data']['AEM02']])) {
            return true;
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
        return false;
    }

    /**
     * 临床路径选1，变异情况必填
     * @param $data
     * @return bool
     */
    public function rule1462($data)
    {
        return false;
    }

    /**
     * 手术操作名称【有】 术者【有】【长度2-40】【不能全是数字】
     * @param $data
     * @return bool
     */
    public function rule1463($data)
    {
        $res = false;
        foreach ($data['operation'] as $operation) {
            if (!empty($operation['SSCZMC']) && (is_numeric($operation['SZXM']) || mb_strlen($operation['SZXM']) < 2 || mb_strlen($operation['SZXM']) > 40)) {
                $res = true;
                break;
            }
        }
        return $res;
    }

    /**
     * 不能全部是【4】
     * @param $data
     * @return bool
     */
    public function rule1464($data)
    {
        $count1 = 0;
        $count2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $count1++;
            if ($diagnosis['RYQK'] == '无') {
                $count2++;
            }
        }
        if ($count1 == $count2) {
            return true;
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
        return false;
    }

    /**
     * Z37、O80-O84、o26.9应同时存在
     * @param $data
     * @return false|string
     */
    public function rule1467($data)
    {
        if (empty($data['diagnosis']) || $data['data']['AAC11N'] != '产科') {
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

        $Z37 = 0;
        $O8 = 0;
        $O26 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if (stripos($ICD10_ID1, 'Z37') !== false) {
                $Z37 = 1;
            } elseif (stripos($ICD10_ID1, 'O26.9') !== false) {
                $O26 = 1;
            } else if (stripos($ICD10_ID1, 'O80') !== false || stripos($ICD10_ID1, 'O81') !== false || stripos($ICD10_ID1, 'O82') !== false || stripos($ICD10_ID1, 'O83') !== false || stripos($ICD10_ID1, 'O84') !== false) {
                $O8 = 1;
            }
        }

        $sumScore = $Z37 + $O8 + $O26;
        if (!in_array($sumScore, [0, 3])) {
            return true;
        }

        return false;
    }

    /**
     * 出院科室是【产科】，Z37码段不能重复编码
     * @param $data
     * @return bool
     */
    public function rule1468($data)
    {
        if ($data['data']['AAC11N'] != '产科') {
            return false;
        }

        $count = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            $ICD10_ID1 = $diagnosis['ZDBM'];
            if (stripos($ICD10_ID1, 'Z37') !== false) {
                $count++;
            }
        }

        if ($count > 1) {
            return true;
        }
        return false;
    }

    /**
     * 出院诊断:有【K56.700 肠梗阻】和【K66.002 肠粘连】应合并编码为【K56.500x003 粘连性肠梗阻】
     * @param $data
     * @return bool
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
            return true;
        }
        return false;
    }

    /**
     * 出院诊断:【S52.500x001 桡骨远端骨折】和【S52.802 尺骨茎突骨折】应合并编码为【S52.600x002 尺骨茎突骨折伴桡骨远端骨折】
     * @param $data
     * @return bool
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
            return true;
        }
        return false;
    }

    /**
     * 出院诊断:【I50.101 急性左心衰竭】、【J81.x00 肺水肿】应合并编码为【I50.103 左心衰竭合并肺水肿】
     * @param $data
     * @return bool
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
            return true;
        }
        return false;
    }

    /**
     * 出院诊断:【M51.202 腰椎间盘突出】和【M54.300 坐骨神经痛】应合并编码为【M51.101+ 腰椎间盘脱出伴坐骨神经痛】
     * @param $data
     * @return bool
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
            return true;
        }
        return false;
    }

    /**
     * 出院诊断:【H02.003 睑内翻】和【H02.004 倒睫】应合并编码为【H02.000 睑内翻和倒睫】
     * @param $data
     * @return bool
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
            return true;
        }
        return false;
    }

    /**
     * 【食管静脉曲张】或 【胃底静脉曲张】和【肝硬化】应合并为【肝硬化伴食管静脉曲张】或【肝硬化伴胃底静脉曲张】
     * @param $data
     * @return bool
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
            return true;
        }

        return false;
    }

    /**
     * 联系人关系不合理
     * @param $data
     * @return bool
     */
    public function rule1476($data)
    {
        $AAA23C = !empty($data['data']['AAA23C']) ? $data['data']['AAA23C'] : '';
        if ($data['data']['AAA04'] < 20 && in_array($AAA23C, [2, 3])) {
            return true;
        }
        return false;
    }

    /**
     * 【出院代码=213（产科），诊断编码：Z37，新生儿出生体重必填】
     * 【出院代码=287（新生儿科、儿童重症医学科），新生儿出生体重必填】
     * 体重区间【100克-9999克】
     * @param $data
     * @return bool
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
                return true;
            }
        } elseif ($data['data']['AAC11C'] == 287 && !$res) {
            return true;
        }

        return false;
    }

    /**
     * 首页收费【麻醉费】，麻醉方式不能为空
     * @param $data
     * @return bool
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

        return $res;
    }

    /**
     *  出院诊断:【E14 】与【E11或 E10】不能同时存在
     * @param $data
     * @return bool
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
            return true;
        }
        return false;
    }

    /**
     * 【I25.103 冠状动脉粥样硬化性心脏病】或【I20.000不稳定型心绞痛】与【I21急性心肌梗死】同时存在，
     * 【I25.103 】或【I20.000】不能作为主要诊断
     * @param $data
     * @return bool
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
            } elseif (stripos($ICD10_ID1, 'I21') === 0) {
                $res2 = 1;
            }
        }
        if ($res1 && $res2 && in_array($ZYZD, ['I25.103', 'I20.000'])) {
            return true;
        }

        return false;
    }

    /**
     * 出院诊断:【E10.9】与【E10.0-E10.8】不能同时存在
     * 出院诊断:【E11.9】与【E11.0-E11.8】不能同时存在
     * @param $data
     * @return bool
     */
    public function rule1482($data)
    {
        $arr1 = ['E10.0', 'E10.1', 'E10.2', 'E10.3', 'E10.4', 'E10.5', 'E10.6', 'E10.7', 'E10.8'];
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
            return true;
        }

        $arr2 = ['E11.0', 'E11.1', 'E11.2', 'E11.3', 'E11.4', 'E11.5', 'E11.6', 'E11.7', 'E11.8'];
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
            return true;
        }

        return false;
    }

    /**
     * 诊断【i63.9 脑梗死】 + 手术【88.4101脑血管造影】 + 【诊断有 i65】 建议更换为 i63.0 - i63.8
     * @param $data
     * @return bool
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
            return true;
        }

        return false;
    }

    /**
     * 诊断【K21.9 胃-食管反流性疾病不伴有食管炎】与【K21.0 胃-食管反流性疾病伴有食管炎】逻辑冲突(伴~不伴)
     * @param $data
     * @return bool
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
            return true;
        }

        return false;
    }

    /**
     * 手术【51.1】 不能 同时有手术【51.64 或 51.84-51.88 或 52.14 或 52.21 或 52.93 h或 52.94 或 52.97 或 52.98】（双向质控）
     * @param $data
     * @return bool
     */
    public function rule1485($data)
    {
        $arr = ['51.64', '51.84', '51.85', '51.86', '51.87', '51.88', '52.14', '52.21', '52.93', '52.94', '52.97', '52.98'];
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            if ($res1 && $res2) {
                break;
            }
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1, '51.1') === 0) {
                $res1 = 1;
            } else {
                foreach ($arr as $val) {
                    if (stripos($ICD9_ID1, $val) === 0) {
                        $res2 = 1;
                        break;
                    }
                }
            }
        }

        if ($res1 && $res2) {
            return true;
        }

        return false;
    }

    /**
     * 诊断【Z51.0】手术操作必须有【92.2 - 92.3】
     * @param $data
     * @return bool
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
            return true;
        }

        return false;
    }

    /**
     * 产科【产科】诊断【O36.4】 其他诊断不应有【Z37 或 O80 - O84】
     * @param $data
     * @return bool
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

        $arr = ['Z37', 'O80', 'O81', 'O82', 'O83', 'O84'];
        $res = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res) {
                break;
            }
            foreach ($arr as $val) {
                if ($diagnosis['ZZPB'] != 1 && stripos($diagnosis['ZDBM'], $val) === 0) {
                    $res = 1;
                    break;
                }
            }
        }
        if ($res) {
            return true;
        }

        return false;
    }

    /**
     * 产科【产科】诊断【O36.4】和 手术【74.1】手术应换成74.9
     * @param $data
     * @return bool
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
            if (stripos($operation['SSCZBM'], '74.1') === 0) {
                $ICD9_ID1 = $operation['SSCZBM'];
            }
        }
        if ($ICD9_ID1) {
            return true;
        }

        return false;
    }

    /**
     * 主诊断【C00-C76】病理诊断编码必须是 M****\/3
     * @param $data
     * @return bool
     */
    public function rule1489($data)
    {
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
            return false;
        }

        $res1 = 0;
        foreach ($arr as $val) {
            if (stripos($ICD10_ID1, $val) === 0) {
                $res1 = 1;
            }
        }
        if (!$res1) {
            return false;
        }

        $ABF01C = !empty($data['data']['ABF01C']) ? $data['data']['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][3]$/', $ABF01C);
        if (!$res2) {
            return true;
        }

        return false;
    }

    /**
     * 主诊断【D00-D09】病理诊断编码必须是 M****\/2
     * @param $data
     * @return bool
     */
    public function rule1490($data)
    {
        $arr = [
            'D00',
            'D01',
            'D02',
            'D03',
            'D04',
            'D05',
            'D06',
            'D07',
            'D08',
            'D09'
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
            }
        }
        if (!$res1) {
            return false;
        }

        $ABF01C = !empty($data['data']['ABF01C']) ? $data['data']['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][2]$/', $ABF01C);
        if (!$res2) {
            return true;
        }

        return false;
    }

    /**
     * 主诊断【D10-D36】病理诊断编码必须是 M****\/0
     * @param $data
     * @return bool
     */
    public function rule1491($data)
    {
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
            return false;
        }

        $res1 = 0;
        foreach ($arr as $val) {
            if (stripos($ICD10_ID1, $val) === 0) {
                $res1 = 1;
            }
        }
        if (!$res1) {
            return false;
        }

        $ABF01C = !empty($data['data']['ABF01C']) ? $data['data']['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][0]$/', $ABF01C);
        if (!$res2) {
            return true;
        }

        return false;
    }

    /**
     * 主诊断【D37-D48】病理诊断编码必须是 M****\/1
     * @param $data
     * @return bool
     */
    public function rule1492($data)
    {
        $arr = [
            'D37',
            'D38',
            'D39',
            'D40',
            'D41',
            'D42',
            'D43',
            'D44',
            'D45',
            'D46',
            'D47',
            'D48'
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
            }
        }
        if (!$res1) {
            return false;
        }

        $ABF01C = !empty($data['data']['ABF01C']) ? $data['data']['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][1]$/', $ABF01C);
        if (!$res2) {
            return true;
        }

        return false;
    }

    /**
     * 主诊断【C77-C79】 病理诊断编码必须是 M****\/6
     * @param $data
     * @return bool
     */
    public function rule1493($data)
    {
        $arr = ['C77', 'C78', 'C79'];
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
            }
        }
        if (!$res1) {
            return false;
        }

        $ABF01C = !empty($data['data']['ABF01C']) ? $data['data']['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][6]$/', $ABF01C);
        if (!$res2) {
            return true;
        }

        return false;
    }

    /**
     * 主诊断【C80-C97】病理诊断编码必须是 M****\/3
     * @param $data
     * @return bool
     */
    public function rule1494($data)
    {
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
            return false;
        }

        $res1 = 0;
        foreach ($arr as $val) {
            if (stripos($ICD10_ID1, $val) === 0) {
                $res1 = 1;
            }
        }
        if (!$res1) {
            return false;
        }

        $ABF01C = !empty($data['data']['ABF01C']) ? $data['data']['ABF01C'] : '';
        $res2 = preg_match('/^[M](.*)[\/][3]$/', $ABF01C);
        if (!$res2) {
            return true;
        }

        return false;
    }

    /**
     * 【68.1200x001 宫腔镜检查】不能和【宫腔镜其他诊断】同时存在
     * @param $data
     * @return bool
     */
    public function rule1495($data)
    {
        $str = "66.2900x003,66.8x03,66.9600x003,67.2x01,67.3203,67.3902,67.4x08,68.1602,68.2101,68.2204,68.2206,68.2300x005,68.2302,68.2900x048,68.2913,68.2914,68.2915,68.2916,68.2917,69.0902,69.4900x006,69.4904,69.5103,70.1408,74.3x00x016,74.3x00x017,74.3x00x018,74.3x09,97.7102,98.1600x002";
        $arr = explode(",", $str);
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
            return true;
        }
        return false;
    }

    /**
     * 【54.2100 腹腔镜检查】不能和【腹腔镜手术】同时存在
     * @param $data
     * @return bool
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
            return true;
        }
        return false;
    }

    /**
     * 70.78另编码使用生物学物质（70.94）或人造物质（70.95）
     * @param $data
     * @return bool
     */
    public function rule1497($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1, '70.78') === 0) {
                $res1 = 1;
            } elseif (stripos($ICD9_ID1, '70.94') === 0 || stripos($ICD9_ID1, '70.95') === 0) {
                $res2 = 1;
            }
        }

        if (empty($res1)) {
            return false;
        }
        if (empty($res2)) {
            return true;
        }
        return false;
    }

    /**
     * 手术【81.0或81.3】手术中要有【81.62-81.64】融合椎骨的总数
     * @param $data
     * @return bool
     */
    public function rule1498($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1, '81.0') !== false || stripos($ICD9_ID1, '81.3') !== false) {
                $res1 = 1;
            } elseif (stripos($ICD9_ID1, '81.62') !== false || stripos($ICD9_ID1, '81.63') !== false || stripos($ICD9_ID1, '81.64') !== false) {
                $res2 = 1;
            }
        }

        if (empty($res1)) {
            return false;
        }
        if (empty($res2)) {
            return true;
        }

        return false;
    }

    /**
     * 手术【81.51-81.53髋关节置换术】需同时编码【00.74-00.78】任何明确类型轴面
     * @param $data
     * @return bool
     */
    public function rule1499($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1, '81.51') !== false || stripos($ICD9_ID1, '81.52') !== false || stripos($ICD9_ID1, '81.53') !== false) {
                $res1 = 1;
            } elseif (stripos($ICD9_ID1, '00.74') !== false || stripos($ICD9_ID1, '00.75') !== false || stripos($ICD9_ID1, '00.76') !== false || stripos($ICD9_ID1, '00.77') !== false || stripos($ICD9_ID1, '00.78') !== false) {
                $res2 = 1;
            }
        }

        if (empty($res1)) {
            return false;
        }

        if (empty($res2)) {
            return true;
        }

        return false;
    }

    /**
     * 手术【39.61】不能有【50.92或39.65或39.95或39.66】
     * @param $data
     * @return bool
     */
    public function rule1500($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1, '39.61') !== false) {
                $res1 = 1;
            } elseif (stripos($ICD9_ID1, '50.92') !== false || stripos($ICD9_ID1, '39.65') !== false || stripos($ICD9_ID1, '39.95') !== false || stripos($ICD9_ID1, '39.66') !== false) {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }
        return false;
    }

    /**
     * 诊断【T20-T30】烧伤，同时需要编【T31或T32】面积
     * @param $data
     * @return bool
     */
    public function rule1501($data)
    {
        $arr = ['T20', 'T21', 'T22', 'T23', 'T24', 'T25', 'T26', 'T27', 'T28', 'T29', 'T30'];
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
            return true;
        }

        return false;
    }

    /**
     * 38.45另编码心肺搭桥[体外循环]（39.61）
     * @param $data
     * @return bool
     */
    public function rule1502($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1, '38.45') === 0) {
                $res1 = 1;
            } elseif (stripos($ICD9_ID1, '39.61') === 0) {
                $res2 = 1;
            }
        }

        if (empty($res1)) {
            return false;
        }
        if (empty($res2)) {
            return true;
        }
        return false;
    }

    /**
     * 手术【39.90或36.06或36.07或00.55或00.63或00.64或00.65】手术中需要有【00.45-00.48 + 治疗血管的数量00.40-00.43】
     * @param $data
     * @return bool
     */
    public function rule1503($data)
    {
        $arr1 = ['39.90', '36.06', '36.07', '00.55', '00.63', '00.64', '00.65'];
        $res1 = 0;
        $res2 = 0;
        $res3 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            foreach ($arr1 as $val) {
                if (stripos($ICD9_ID1, $val) !== false) {
                    $res1 = 1;
                } elseif (stripos($ICD9_ID1, '00.45') !== false || stripos($ICD9_ID1, '00.46') !== false || stripos($ICD9_ID1, '00.47') !== false || stripos($ICD9_ID1, '00.48') !== false) {
                    $res2 = 1;
                } elseif (stripos($ICD9_ID1, '00.40') !== false || stripos($ICD9_ID1, '00.41') !== false || stripos($ICD9_ID1, '00.42') !== false || stripos($ICD9_ID1, '00.43') !== false) {
                    $res3 = 1;
                }
            }
        }

        if (empty($res1)) {
            return false;
        }
        if (empty($res2) || empty($res3)) {
            return true;
        }
        return false;
    }

    /**
     * 手术【39.74】手术中需要有治疗血管的数量【00.40-00.43】
     * @param $data
     * @return bool
     */
    public function rule1504($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1, '39.74') !== false) {
                $res1 = 1;
            } elseif (stripos($ICD9_ID1, '00.40') !== false || stripos($ICD9_ID1, '00.41') !== false || stripos($ICD9_ID1, '00.42') !== false || stripos($ICD9_ID1, '00.43') !== false) {
                $res2 = 1;
            }
        }

        if (empty($res1)) {
            return false;
        }
        if (empty($res2)) {
            return true;
        }
        return false;
    }

    /**
     * 收费【阿替普酶】手术要有【99.1005 脑动脉血栓溶解剂灌注】
     * @param $data
     * @return bool
     */
    public function rule1505($data)
    {
        $FYMC_ARR = array_column($data['fy'], 'FYMC');
        $res = false;
        foreach ($FYMC_ARR as $value) {
            if (stripos($value, '阿替普酶') !== false) {
                $res = true;
            }
        }
        if (!$res) {
            return false;
        }

        $res1 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if ($ICD9_ID1 == '99.10') {
                $res1 = 1;
            }
        }

        if (empty($res1)) {
            return true;
        }

        return false;
    }

    /**
     * 手术中 03.90另编码输注泵的置入（86.06）
     * @param $data
     * @return bool
     */
    public function rule1506($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1, '03.90') === 0) {
                $res1 = 1;
            } elseif (stripos($ICD9_ID1, '86.06') === 0) {
                $res2 = 1;
            }
        }

        if (empty($res1)) {
            return false;
        }
        if (empty($res2)) {
            return true;
        }
        return false;
    }

    /**
     * 37.8有导线起搏器另编导线置入、置换、去除和修复（37.70-37.77）
     * @param $data
     * @return bool
     */
    public function rule1507($data)
    {
        $arr = ['37.70', '37.71', '37.72', '37.73', '37.74', '37.75', '37.76', '37.77'];
        $res1 = 0;
        $res2 = 0;
        foreach ($data['operation'] as $operation) {
            if ($res1 && $res2) {
                break;
            }
            $ICD9_ID1 = $operation['SSCZBM'];
            if (stripos($ICD9_ID1, '37.8') === 0) {
                $res1 = 1;
            } else {
                foreach ($arr as $val) {
                    if (stripos($ICD9_ID1, $val) === 0) {
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
            return true;
        }
        return false;
    }

    /**
     * 手术名称有【***粘连松解术】 诊断名称要有【 ***粘连】
     * @param $data
     * @return bool
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
            return true;
        }

        return false;
    }

    /**
     * 诊断【N20.0和N20.1】需要合并到N20.2
     * @param $data
     * @return bool
     */
    public function rule1509($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'N20.0') === 0) {
                $res1 = 1;
            } elseif (stripos($diagnosis['ZDBM'], 'N20.1') === 0) {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }
        return false;
    }

    /**
     * 诊断【N20和N13.3】需要合并到N13.2
     * @param $data
     * @return bool
     */
    public function rule1510($data)
    {
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if (stripos($diagnosis['ZDBM'], 'N20') === 0) {
                $res1 = 1;
            } elseif (stripos($diagnosis['ZDBM'], 'N13.3') === 0) {
                $res2 = 1;
            }
        }

        if ($res1 && $res2) {
            return true;
        }
        return false;
    }

    /**
     * 诊断【N13.0-N13.5和 N15.9】需要合并到N13.6
     * @param $data
     * @return bool
     */
    public function rule1511($data)
    {
        $arr = ['N13.0', 'N13.1', 'N13.2', 'N13.3', 'N13.4', 'N13.5'];
        $res1 = 0;
        $res2 = 0;
        foreach ($data['diagnosis'] as $diagnosis) {
            if ($res1 && $res2) {
                break;
            }
            if (stripos($diagnosis['ZDBM'], 'N15.9') === 0) {
                $res2 = 1;
            } else {
                foreach ($arr as $val) {
                    if (stripos($diagnosis['ZDBM'], $val) === 0) {
                        $res1 = 1;
                        break;
                    }
                }
            }
        }

        if ($res1 && $res2) {
            return true;
        }
        return false;
    }

    /**
     * 诊断信息除最后一条外，诊断编码或名称不能为空
     * @param $data
     * @return bool
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
            $res1 = count($data['diagnosis']) - 1;
            if ($res < $res1) {
                return true;
            }
        }

        return false;
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
     * 切口类型为0时，愈合等级不能为空
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
            if ($operation['QKDJ'] == 0 && empty($operation['YHDJ'])) {
                $desc = '切口类型为0时，愈合等级不能为空';
                $basis[] = ['desc' => $desc, 'location' => ['user' => [], 'zd' => [], 'ss' => []]];
            }
        }
        return $basis;
    }

}
