<?php

namespace App\Services;

use App\Model\Coder;
use App\Model\Error;
use App\Model\OtherDiagnosis;
use App\Model\ErrorRule;
use App\Model\PatientInfo;
use Pheanstalk\Pheanstalk;

class ErrorValidateService
{
    public static function quality($AAA28)
    {
        ini_set('default_socket_timeout', 24 * 60 * 60);
        //$data = MedicalRecordService::getData($AAA28);
        $data = MedicalRecordService::getQualityData($AAA28);
        if(empty($data)){
            var_dump('不存在的AAA28：'.$AAA28);
            echo "data结果不存在 {AAA28} " . PHP_EOL;
            //continue;
            return;
        }
        if (empty($data['AEE04_CODE'])) {
            $data['AEE04_CODE'] = '';
        }
        $orderId = self::getCoderId($data);
        $rule = ErrorRuleService::getRuleList();
        //print_r($rule);
        $c_year = date("Y", strtotime($data['AAC01']));
        $c_month = date("m", strtotime($data['AAC01']));
        $insetData = [];

        foreach ($rule as $item) {
            if ($item['rule'] == 'required') {
                $result = self::required($data, $item);
                if ($result) {
                    $insetData[] = [
                        'year' => $c_year,
                        'month' => $c_month,
                        'AAA28' => $data['MED_REC_ID'],
                        'desc' => $item['desc'],
                        'error_field' => $item['auth'],
                        'error_name' => $item['field'],
                        'level' => $item['level'],
                        'error_rule' => $item['id'],
                        'type' => $item['type'],
                        'coder_id' => $orderId,
                        'AAC11C' => $data['AAC11C'] ?? '',
                        'down' => $item['down'],
                        'category' => $item['category'],
                        'error_type' => $item['error_type'],
                        'source' => $data['source']
                    ];
                }
                continue;
            } elseif ($item['rule'] == 'exec_func') {
                $func = explode(':', $item['relation_rule'])[1] ?? '';
                if(empty($func)){
                    continue;
                }
                $result = self::$func($data, $item);
                if ($result) {
                    $insetData[] = [
                        'year' => $c_year,
                        'month' => $c_month,
                        'AAA28' => $data['MED_REC_ID'],
                        'desc' => $item['desc'],
                        'error_field' => $item['auth'],
                        'error_name' => $item['field'],
                        'level' => $item['level'],
                        'error_rule' => $item['id'],
                        'type' => $item['type'],
                        'error_type' => $item['error_type'],
                        'AAC11C' => $data['AAC11C'] ?: '',
                        'coder_id' => $orderId,
                        'down' => $item['down'],
                        'category' => $item['category'],
                        'source' => $data['source']
                    ];
                }
                continue;
            } elseif ($item['rule'] == 'age') {
                $result = self::age($data, $item);
                if ($result) {
                    $insetData[] = [
                        'year' => $c_year,
                        'month' => $c_month,
                        'AAA28' => $data['MED_REC_ID'],
                        'desc' => $item['desc'],
                        'error_field' => $item['auth'],
                        'error_name' => $item['field'],
                        'level' => $item['level'],
                        'error_rule' => $item['id'],
                        'type' => $item['type'],
                        'error_type' => $item['error_type'],
                        'AAC11C' => $data['AAC11C'] ?: '',
                        'coder_id' => $orderId,
                        'down' => $item['down'],
                        'category' => $item['category'],
                        'source' => $data['source']
                    ];
                }
                continue;
            } elseif ($item['rule'] == 'gender') {
                $result = self::gender($data, $item);
                if ($result) {
                    $insetData[] = [
                        'year' => $c_year,
                        'month' => $c_month,
                        'AAA28' => $data['MED_REC_ID'],
                        'desc' => $item['desc'],
                        'error_field' => $item['auth'],
                        'error_name' => $item['field'],
                        'level' => $item['level'],
                        'error_rule' => $item['id'],
                        'type' => $item['type'],
                        'error_type' => $item['error_type'],
                        'AAC11C' => $data['AAC11C'] ?? '',
                        'coder_id' => $orderId,
                        'down' => $item['down'],
                        'category' => $item['category'],
                        'source' => $data['source']
                    ];
                }
                continue;
            } elseif ($item['rule'] == 'special') {
                if (preg_match('[@_!#\$%\^&\*\(\)<>\?/|}\{~:]', $data[$item['auth']])) {
                    $insetData[] = [
                        'year' => $c_year,
                        'month' => $c_month,
                        'AAA28' => $data['MED_REC_ID'],
                        'desc' => $item['desc'],
                        'error_field' => $item['auth'],
                        'error_name' => $item['field'],
                        'level' => $item['level'],
                        'error_rule' => $item['id'],
                        'type' => $item['type'],
                        'error_type' => $item['error_type'],
                        'coder_id' => $orderId,
                        'AAC11C' => $data['AAC11C'] ?: '',
                        'down' => $item['down'],
                        'category' => $item['category'],
                        'source' => $data['source']
                    ];
                }
                continue;
            } elseif ($item['rule'] == 'code') {
                $codeResult = false;
                if (isset($data[$item['auth']]) && $data[$item['auth']] != '') {
                    if ($item['relation_rule'] == '*') {
                        if (preg_match('[\*]', $data[$item['auth']])) {
                            $codeResult = true;
                        }
                    } elseif ($item['relation_rule'] == 'M') {
                        if (preg_match('/^M?$/', $data[$item['auth']])) {
                            $codeResult = true;
                        }
                    } elseif ($item['relation_rule'] == 'no_die') {
                        $no_die = explode('|', $item['relation']);
                        if (in_array($data['ABC01C'], $no_die)) {
                            $codeResult = true;
                        }
                    } else {
                        $relation_rule = explode('|', $item['relation_rule']);
                        $relation_rule1 = explode(':', $relation_rule[0]);
                        if ($relation_rule1[0] == 'equ') {
                            $other = OtherDiagnosis::query()
                                ->where('AAA28', $data['MED_REC_ID'])
                                ->where('ICD10_ID1', $relation_rule1[1])
                                ->first();
                            if (isset($relation_rule[1]) && $other) {
                                $relation_rule2 = explode(':', $relation_rule[1]);
                                if ($relation_rule2[0] == '!empty') {
                                    if ($data[$relation_rule2[1]] == '') {
                                        $codeResult = true;
                                    }
                                }
                            }
                        }
                    }
                }
                if ($codeResult) {
                    $insetData[] = [
                        'year' => $c_year,
                        'month' => $c_month,
                        'AAA28' => $data['MED_REC_ID'],
                        'desc' => $item['desc'],
                        'error_field' => $item['auth'],
                        'error_name' => $item['field'],
                        'level' => $item['level'],
                        'error_rule' => $item['id'],
                        'type' => $item['type'],
                        'error_type' => $item['error_type'],
                        'coder_id' => $orderId,
                        'AAC11C' => $data['AAC11C'] ?: '',
                        'down' => $item['down'],
                        'category' => $item['category'],
                        'source' => $data['source']
                    ];
                }
                continue;
            } elseif ($item['rule'] == 'condition') {
                $result = self::condition($data, $item);
                if ($result) {
                    $insetData[] = [
                        'year' => $c_year,
                        'month' => $c_month,
                        'AAA28' => $data['MED_REC_ID'],
                        'desc' => $item['desc'],
                        'error_field' => $item['auth'],
                        'error_name' => $item['field'],
                        'level' => $item['level'],
                        'error_rule' => $item['id'],
                        'type' => $item['type'],
                        'error_type' => $item['error_type'],
                        'coder_id' => $orderId,
                        'AAC11C' => $data['AAC11C'] ?: '',
                        'down' => $item['down'],
                        'category' => $item['category'],
                        'source' => $data['source']
                    ];
                }
                continue;
            } elseif ($item['rule'] == 'or') {
                $result = self::orValue($data, $item);
                if ($result) {
                    $insetData[] = [
                        'year' => $c_year,
                        'month' => $c_month,
                        'AAA28' => $data['MED_REC_ID'],
                        'desc' => $item['desc'],
                        'error_field' => $item['auth'],
                        'error_name' => $item['field'],
                        'level' => $item['level'],
                        'error_rule' => $item['id'],
                        'type' => $item['type'],
                        'error_type' => $item['error_type'],
                        'coder_id' => $orderId,
                        'AAC11C' => $data['AAC11C'] ?? '',
                        'down' => $item['down'],
                        'category' => $item['category'],
                        'source' => $data['source']
                    ];
                }
                continue;
            } elseif ($item['rule'] == 'main_no_check') {
                $errorNotice = '';
                $result = self::mainNoCheck($data, $item, $errorNotice);
                if ($result) {
                    $insetData[] = [
                        'year' => $c_year,
                        'month' => $c_month,
                        'AAA28' => $data['MED_REC_ID'],
                        'desc' => trim($errorNotice, '-') . $item['desc'],
                        'error_field' => $item['auth'],
                        'error_name' => $item['field'],
                        'level' => $item['level'],
                        'error_rule' => $item['id'],
                        'type' => $item['type'],
                        'coder_id' => $orderId,
                        'AAC11C' => $data['AAC11C'] ?? '',
                        'down' => $item['down'],
                        'category' => $item['category'],
                        'error_type' => $item['error_type'],
                        'source' => $data['source']
                    ];
                }
                continue;
            } elseif ($item['rule'] == 'operation') {
                $errorNotice = '';
                $result = self::operation($data, $item, $errorNotice);
                if ($result) {
                    $insetData[] = [
                        'year' => $c_year,
                        'month' => $c_month,
                        'AAA28' => $data['MED_REC_ID'],
                        'desc' => trim($errorNotice, '-') . $item['desc'],
                        'error_field' => $item['auth'],
                        'error_name' => $item['field'],
                        'level' => $item['level'],
                        'error_rule' => $item['id'],
                        'type' => $item['type'],
                        'coder_id' => $orderId,
                        'AAC11C' => $data['AAC11C'] ?? '',
                        'down' => $item['down'],
                        'category' => $item['category'],
                        'error_type' => $item['error_type'],
                        'source' => $data['source']
                    ];
                }
                continue;
            } elseif ($item['rule'] == 'hospital') {
                $errorNotice = '';
                $result = self::hospital($data, $item, $errorNotice);
                if ($result) {
                    $insetData[] = [
                        'year' => $c_year,
                        'month' => $c_month,
                        'AAA28' => $data['MED_REC_ID'],
                        'desc' => trim($errorNotice, '-') . $item['desc'],
                        'error_field' => $item['auth'],
                        'error_name' => $item['field'],
                        'level' => $item['level'],
                        'error_rule' => $item['id'],
                        'type' => $item['type'],
                        'coder_id' => $orderId,
                        'AAC11C' => $data['AAC11C'] ?? '',
                        'down' => $item['down'],
                        'category' => $item['category'],
                        'error_type' => $item['error_type'],
                        'source' => $data['source']
                    ];
                }
                continue;
            } elseif ($item['rule'] == 'time') {
                $errorNotice = '';
                $result = self::timeRole($data, $item, $errorNotice);
                if ($result) {
                    $insetData[] = [
                        'year' => $c_year,
                        'month' => $c_month,
                        'AAA28' => $data['MED_REC_ID'],
                        'desc' => trim($errorNotice, '-') . $item['desc'],
                        'error_field' => $item['auth'],
                        'error_name' => $item['field'],
                        'level' => $item['level'],
                        'error_rule' => $item['id'],
                        'type' => $item['type'],
                        'coder_id' => $orderId,
                        'AAC11C' => $data['AAC11C'] ?? '',
                        'down' => $item['down'],
                        'category' => $item['category'],
                        'error_type' => $item['error_type'],
                        'source' => $data['source']
                    ];
                }
                continue;
            } else {
                if (is_string($item) === false) {
                    continue;
                }
                $expRule = explode(':', $item);
                switch ($expRule[0]) {
                    case 'min';
                        $result = $data[$item['auth']] < $expRule[1] ? 1 : 0;
                        break;
                    case 'max';
                        $result = $data[$item['auth']] > $expRule[1] ? 1 : 0;
                        break;
                    case 'equ';
                        $result = $data[$item['auth']] != $expRule[1] ? 1 : 0;
                        break;
                    case 'nequ';
                        $result = $data[$item['auth']] == $expRule[1] ? 1 : 0;
                        break;
                    case 'len';
                        $result = strlen($data[$item['auth']]) > $expRule[1] ? 1 : 0;
                        break;
                    case 'in';
                        $aa = config('dictionaries.' . $expRule[1]);
                        $result = isset($aa[$data[$expRule[1]]]) ? 1 : 0;
                        break;
                    default;
                        $result = 0;
                        break;
                }
                if ($result) {
                    $insetData[] = [
                        'year' => $c_year,
                        'month' => $c_month,
                        'AAA28' => $data['MED_REC_ID'],
                        'desc' => $item['desc'],
                        'error_field' => $item['auth'],
                        'error_name' => $item['field'],
                        'level' => $item['level'],
                        'error_rule' => $item['id'],
                        'type' => $item['type'],
                        'error_type' => $item['error_type'],
                        'coder_id' => $orderId,
                        'AAC11C' => $data['AAC11C'] ?? '',
                        'down' => $item['down'],
                        'category' => $item['category'],
                        'source' => $data['source']
                    ];
                }
                continue;
            }
        }

        //type = 3 的是手术名称遗漏
        Error::query()->where('AAA28',$AAA28)->delete();
        echo "质控出来的结果 {$data['MED_REC_ID']} " . count($insetData) . PHP_EOL;
        if(count($insetData) == 0){
            echo "Testcommand data" . $data['MED_REC_ID'] . " count " . count($insetData) . " json: " . json_encode($data) . PHP_EOL;
            echo "Testcommand rule" . $data['MED_REC_ID'] . " count " . count($insetData) . " json: " . json_encode($rule) . PHP_EOL;
        }
        if (!empty($insetData)) {
            echo "Testcommand data" . $data['MED_REC_ID'] . " count " . count($insetData) . " json: " . json_encode($data) . PHP_EOL;
            echo "Testcommand " . $data['MED_REC_ID'] . " count " . count($insetData) . " json: " . json_encode($insetData) . PHP_EOL;;
            Error::query()->insert($insetData, $insetData);
            self::updateScore($AAA28, $insetData);
        }

    }

    public static function updateScore($MED_REC_ID, $insetData)
    {
        //$score = sum()
        $score = array_sum(array_column($insetData, 'down'));
        //print_r(['score'=>(100-$score)]);
        PatientInfo::query()->where(['MED_REC_ID'=>$MED_REC_ID])->update(['score'=>(100-$score)]);

    }

    public static function required($data, $item)
    {
        $auth = self::getAuth($data, $item['auth']);
        if ($auth == '') {
            return true;
        } else {
            $expRule = explode(':', $item['relation_rule']);
            if (empty($expRule)) {
                return false;
            }
            switch ($expRule[0]) {
                case 'min';
                    $result = $auth < $expRule[1] ? 1 : 0;
                    break;
                case 'max';
                    if (is_numeric($expRule[1])) {
                        $result = $auth > $expRule[1] ? 1 : 0;
                    } else {
                        $result = $auth > $data[$expRule[1]] ? 1 : 0;
                    }
                    break;
                case 'equ';
                    $result = $auth == $expRule[1] ? 1 : 0;
                    break;
                case 'nequ';
                    $result = $auth != $expRule[1] ? 1 : 0;
                    break;
                case 'len';
                    $result = strlen($auth) <= $expRule[1] ? 1 : 0;
                    break;
                case 'in';
                    $aa = config('dictionaries.' . $expRule[1]);
                    $result = isset($aa[$auth]) ? 1 : 0;
                    break;
                case 'in_value';
                    $values = explode(',', $expRule[1]);
                    $result = in_array($auth, $values) ? 0 : 1;
                    break;
                case 'unique';
                    // 校验其他诊断编码是否有重复的
                    $result = count($auth) == count(array_unique($auth)) ? 1 : 0;
                    break;
                default;
                    $result = 1;
                    break;
            }
            if ($result) {
                return false;
            }
            return true;
        }
    }

    /**
     * @param $data
     * @param $item
     * @return bool
     * 主要诊断编码校验
     */
    public static function mainNoCheck($data, $item, &$errorNotice = "")
    {
        $auth = self::getAuth($data, $item['auth'], 'main');

        if ($auth == '' || empty($auth[0])) {
            return false;
        } else {
            $no = $auth[0]; // 主要诊断编号

            $result = false;
            $expRule = explode(':', $item['relation_rule']);
            if (empty($expRule)) {
                return false;
            }
            if (isset($expRule[1])) {
                $roleValue = explode(',', $expRule[1]); // 规则判断的值
            }
            switch ($expRule[0]) {
                case 'no_in';
                    if ($roleValue && $no) {
                        $result = in_array($no, $roleValue) ? true : false;
                        $errorNotice = $no . '-';
                    }
                    break;
                case 'not_in_other_no';
                    $otherNo = $auth;
                    unset($otherNo[0]);
                    $result = in_array($no, $otherNo) ? true : false;
                    $errorNotice = $no;
                    break;
                case 'exist';

                    // 如果所有诊断编码超过1个，并且规定的编码都在诊断编码内则给出提示
                    $flagNums = 0;
                    if (count($auth) > 1) {
                        $authStr = implode(',', $auth);
                        foreach ($roleValue as $item) {
                            if (strpos($authStr, $item) !== false) {
                                $errorNotice .= $item . '-';
                                $flagNums++;
                            }
                        }
                    }
                    if ($flagNums >= 2) {
                        $result = true;
                    } else {
                        $result = false;
                    }
                    break;
                case 'name_exist';
                    // 疾病国临2.0库中要求合并疾病名称
                    if (count($auth) > 1) {
                        $resArr = array_intersect($roleValue, $auth);
                        $result = count($resArr) == count($roleValue) ? true : false;
                        if ($result == true) {
                            $errorNotice = implode('-', $resArr);
                        }
                    }
                    break;
                case 'must_bl';
                    if (empty($roleValue)) {
                        break;
                    }
                    // 主要诊断中出现了C00到D48，病理诊断编码必须填写，且格式为M****/*
                    $blMustNos = config('blMust');
                    if (in_array($no, $blMustNos)) {

                        preg_match_all("/^M(\d+\/\d+)$/", $data['ABF01C'], $jwsDataNoRes);
                        if (empty($jwsDataNoRes[1])) {
                            $result = true;
                            break;
                        }
                    }
                    break;
                case 'multi':
                    $multi = explode('|', $expRule[1]);
                    if (empty($multi)) {
                        break;
                    }
                    $flag = true;
                    foreach ($multi as $k => $m) {
                        $paramsArr = explode(',', $m);
                        if (empty($paramsArr[0]) || empty($paramsArr[1]) || empty($paramsArr[2])) {
                            continue;
                        }
                        $flag = true;
                        switch ($paramsArr[1]) {
                            case 'in':
                                if ($paramsArr[0] == 'ICD10_ID1' || $paramsArr[0] == 'ICD10_NAME') {
                                    $data[$paramsArr[0]] = self::getAuth($data, $paramsArr[0]);
                                }
                                if (!in_array($paramsArr[2], $data[$paramsArr[0]])) {
                                    $flag = false;
                                }

                                break;
                            case '=':

                                if ($data[$paramsArr[0]] != $paramsArr[2]) {
                                    $flag = false;
                                }
                                break;
                            case '<':
                                if ($data[$paramsArr[0]] > $paramsArr[2]) {
                                    $flag = false;
                                }
                                break;
                            case '>':
                                if ($data[$paramsArr[0]] < $paramsArr[2]) {
                                    $flag = false;
                                }
                                break;
                            case 'main_no_pro': // 校验主要诊断编码中是否包含指定的开头编码字符
                                if (empty($paramsArr[2])) {
                                    $flag = false;
                                    break;
                                }
                                $nos = explode('&&', $paramsArr[2]);
                                $elFlag = false;
                                foreach ($nos as $n) {
                                    if (strpos($no, $n) !== false) {
                                        $elFlag = true;
                                        break;
                                    }
                                }
                                $flag = $elFlag;
                                break;
                            case 'start_word':// ABF01C,start_word,M| 判断是否已指定字符开头
                                if (strpos($data[$paramsArr[0]], $paramsArr[2]) !== 0) {
                                    $flag = false;
                                    break;
                                }
                                break;
                            case 'end_word': // ABF01C,end_word,/6 判断是否已指定字符结尾
                                if (substr($data[$paramsArr[0]], strpos($data[$paramsArr[0]], $paramsArr[2])) != $paramsArr[2]) {
                                    $flag = false;
                                    break;
                                }
                                break;
                            case 'intersection';
                                if (empty($paramsArr[2])) {
                                    $flag = false;
                                    break;
                                }
                                $errNo = explode('&&', $paramsArr[2]);
                                $intersectNo = array_intersect($auth, $errNo);
                                if (!$intersectNo) {
                                    $flag = false;
                                }

                                break;
                        }
                        // 如果第一个条件不满足，则之后的将不在校验
                        if ($k == 0 && !$flag) {
                            $flag = true;
                            break;
                        }
                        if (!$flag) {
                            break;
                        }

                    }
                    if (!$flag) {
                        $result = true;
                    }
                    break;
                case 'intersection';
                    $intersectNo = array_intersect($auth, $roleValue);
                    if ($intersectNo) {
                        $errorNotice = implode('-', $intersectNo);
                        $result = true;
                    }
                    break;
            }


            return $result;
        }
    }

    /**
     * @param $data
     * @param $item
     * @param string $errorNotice
     * @return bool
     * 手术相关的规则校验
     */
    public static function operation($data, $item, &$errorNotice = "")
    {
        $auth = self::getAuth($data, $item['auth'], 'main');

        if ($auth == '' || empty($auth[0])) {
            return false;
        } else {
            $no = $auth[0] ?? ''; // 主要手术编号

            $result = false;
            $expRule = explode(':', $item['relation_rule']);
            if (empty($expRule)) {
                return false;
            }
            if (isset($expRule[1])) {
                $roleValue = explode(',', $expRule[1]); // 规则判断的值
            }
            switch ($expRule[0]) {
                // 手术编码同时存在就报错
                case 'concurrent';
                    $existIndex = 0;
                    foreach ($roleValue as $childNo) {
                        if (in_array($childNo, $auth)) {
                            $existIndex++;
                            $errorNotice .= $childNo . '-';
                        }
                    }
                    // 如果累加的数量 = 指定的编码组数量，则表示都存在，则有问题
                    $result = $existIndex == count($roleValue) ? true : false;
                    break;

                case 'consistent'; // 手术编码和给定的规则编码完全一致，不一致报错
                    foreach ($roleValue as $childNo) {
                        if (!in_array($childNo, $auth)) {
                            $errorNotice .= $childNo . '-';
                            $result = true;
                        }
                    }
                    break;
                case 'in';
                    if ($roleValue) {
                        $result = in_array($no, $roleValue) ? true : false;
                        $errorNotice = $no;
                    }
                    break;
                case 'no_in';
                    if ($expRule[1] && $auth) {
                        $result = in_array($expRule[1], $auth) ? true : false;
                        $errorNotice = $expRule[1];
                    }
                    break;
                case 'exist';
                    $flagNums = 0;
                    if (count($auth) > 1) {
                        $authStr = implode(',', $auth);
                        foreach ($roleValue as $item) {
                            if (strpos($authStr, $item) !== false) {
                                $errorNotice .= $item . '-';
                                $flagNums++;
                            }
                        }
                    }
                    if ($flagNums >= 2) {
                        $result = true;
                    } else {
                        $result = false;
                    }
                    break;
                case 'intersection';
                    if (empty($roleValue)) {
                        $result = false;
                        break;
                    }
                    $intersectNo = array_intersect($auth, $roleValue);
                    if (!$intersectNo) {
                        $flag = false;
                    }
                    break;
                case 'multi':
                    $multi = explode('|', $expRule[1]);
                    if (empty($multi)) {
                        break;
                    }
                    $flag = true;
                    foreach ($multi as $k => $m) {
                        $paramsArr = explode(',', $m);
                        if (empty($paramsArr[0]) || empty($paramsArr[1]) || empty($paramsArr[2])) {
                            continue;
                        }
                        switch ($paramsArr[1]) {
                            case '!=':
                                if ($data[$paramsArr[0]] == $paramsArr[2]) {
                                    $flag = false;
                                }
                                break;
                            case '=':
                                if ($data[$paramsArr[0]] != $paramsArr[2]) {
                                    $flag = false;
                                }
                                break;
                            case '!empty':
                                $fields = ['HOCUS_MAN_CODE', 'HOCUS_WAY_ID'];
                                if (in_array($item['auth'], $fields)) {
                                    if (empty($data['operation'][0][$paramsArr[0]])) {
                                        $flag = false;
                                    }
                                } else {
                                    if (empty($data[$paramsArr[0]])) {
                                        $flag = false;
                                    }
                                }

                                break;
                            case 'empty':
                                if (empty($data[$paramsArr[0]])) {
                                    $flag = false;
                                }
                                break;
                            case 'pre_like':
                                if (empty($paramsArr[2])) {
                                    $flag = false;
                                    break;
                                }
                                $nos = explode('&&', $paramsArr[2]);
                                foreach ($nos as $n) {
                                    if (strpos($no, $n) === 0) {
                                        $flag = false;
                                        break;
                                    }
                                }
                                break;
                        }
                        // 如果第一个条件不满足，则之后的将不在校验
                        if ($k == 0 && !$flag) {
                            $flag = true;
                            break;
                        }
                        if (!$flag) {
                            break;
                        }

                    }
                    if (!$flag) {
                        $result = true;
                    }
                    break;
            }


            return $result;
        }
    }


    /**
     * @param $data
     * @param $item
     * @param string $errorNotice
     * @return bool
     * 住院相关规则
     */
    public static function hospital($data, $item, &$errorNotice = "")
    {
        $auth = self::getAuth($data, $item['auth']);

        if (empty($auth)) {
            return false;
        } else {

            $result = false;
            $expRule = explode(':', $item['relation_rule']);
            if (empty($expRule)) {
                return false;
            }
            if (isset($expRule[1])) {
                $roleValue = explode(',', $expRule[1]); // 规则判断的值
            }
            switch ($expRule[0]) {
                case 'multi':
                    $multi = explode('|', $expRule[1]);
                    if (empty($multi)) {
                        break;
                    }
                    $flag = true;
                    foreach ($multi as $k => $m) {
                        $paramsArr = explode(',', $m);
                        if (empty($paramsArr[0]) || empty($paramsArr[1]) || empty($paramsArr[2])) {
                            continue;
                        }
                        $flag = true;
                        switch ($paramsArr[1]) {
                            case 'in':
                                $nos = explode('&&', $paramsArr[2]);
                                if (!in_array($data[$paramsArr[0]], $nos)) {
                                    $flag = false;
                                    break;
                                }
                                break;
                            case '!=':
                                if ($data[$paramsArr[0]] == $paramsArr[2]) {
                                    $flag = false;
                                }
                                break;
                            case '=':
                                if ($data[$paramsArr[0]] != $paramsArr[2]) {
                                    $flag = false;
                                }
                                break;
                            case '!empty':
                                if (empty($data[$paramsArr[0]])) {
                                    $flag = false;
                                }
                                break;
                            case 'empty':
                                if (!empty($data[$paramsArr[0]])) {
                                    $flag = false;
                                }
                                break;
                        }
                        // 如果第一个条件不满足，则之后的将不在校验
                        if ($k == 0 && !$flag) {
                            $flag = true;
                            break;
                        }
                        if (!$flag) {
                            break;
                        }

                    }
                    if (!$flag) {
                        $result = true;
                    }
                    break;
                case 'intersection';
                    $intersectNo = array_intersect($auth, $roleValue);
                    if ($intersectNo) {
                        $errorNotice = implode('-', $intersectNo);
                        $result = true;
                    }
                    break;
            }


            return $result;
        }
    }

    /**
     * @param $data
     * @param $item
     * @param string $errorNotice
     * @return bool
     * 时间规则
     */
    public static function timeRole($data, $item, &$errorNotice = "")
    {
        $auth = self::getAuth($data, $item['auth']);

        if (empty($auth)) {
            return false;
        } else {

            $result = false;
            $expRule = explode('|', $item['relation_rule']);
            if (empty($expRule)) {
                return false;
            }
            $flag = true;
            foreach ($expRule as $k => $m) {
                $paramsArr = explode(',', $m);
                if (empty($paramsArr[0]) || empty($paramsArr[1]) || empty($paramsArr[2])) {
                    continue;
                }
                $flag = true;
                switch ($paramsArr[1]) {
                    case '>':
                        if (!empty($data[$paramsArr[0]])
                            && !empty($data[$paramsArr[2]])
                            && $data[$paramsArr[0]] < $data[$paramsArr[2]]) {
                            $flag = false;
                        }
                        break;
                    case '!=':
                        if ($data[$paramsArr[0]] == $paramsArr[2]) {
                            $flag = false;
                        }
                        break;
                    case '=':
                        if ($data[$paramsArr[0]] != $paramsArr[2]) {
                            $flag = false;
                        }
                        break;
                    case '!empty':
                        if (empty($data[$paramsArr[0]])) {
                            $flag = false;
                        }
                        break;
                    case 'empty':
                        if (!empty($data[$paramsArr[0]])) {
                            $flag = false;
                        }
                        break;
                }
                if (!$flag) {
                    break;
                }

            }
            if (!$flag) {
                $result = true;
            }

            return $result;
        }
    }

    public static function orValue($data, $item)
    {
        $condition = self::getAuth($data, $item['relation']);
        $auth = self::getAuth($data, $item['auth']);
        if ($auth == '' && $condition == '') {
            return true;
        } else {
            $expRule = explode(':', $item['relation_rule']);
            switch ($expRule[0]) {
                case 'min';
                    $result = $auth < $expRule[1] ? 1 : 0;
                    break;
                case 'max';
                    if (is_numeric($expRule[1])) {
                        $result = $auth > $expRule[1] ? 1 : 0;
                    } else {
                        $result = $auth > $data[$expRule[1]] ? 1 : 0;
                    }
                    break;
                case 'equ';
                    $result = $auth == $expRule[1] ? 1 : 0;
                    break;
                case 'nequ';
                    $result = $auth != $expRule[1] ? 1 : 0;
                    break;
                case 'len';
                    $result = strlen($auth) <= $expRule[1] ? 1 : 0;
                    break;
                case 'in';
                    $aa = config('dictionaries.' . $expRule[1]);
                    $result = isset($aa[$auth]) ? 1 : 0;
                    break;
                default;
                    $result = 1;
                    break;
            }
            if ($result) {
                return false;
            }
            return true;
        }
    }

    public static function condition($data, $item)
    {

        $condition = self::getAuth($data, $item['relation']);
        $auth = self::getAuth($data, $item['auth']);
        if ($condition != '') {
            $rule = explode('｜', $item['relation_rule']);
            $result = 0;
            foreach ($rule as $value) {
                $expRule = explode(':', $value);
                switch ($expRule[0]) {
                    case 'min';
                        $result = $auth < $expRule[1] ? 1 : 0;
                        break;
                    case 'relation_min';
                        $result = $condition < $expRule[1] ? 1 : 0;
                        break;
                    case 'relation_max';
                        if (is_int($expRule[1])) {
                            $result = $condition > $expRule[1] ? 1 : 0;
                        } else {
                            $result = $condition > $data[$expRule[1]] ? 1 : 0;
                        }
                        break;
                    case 'max';
                        if (is_int($expRule[1])) {
                            $result = $auth > $expRule[1] ? 1 : 0;
                        } else {
                            $result = $auth > $data[$expRule[1]] ? 1 : 0;
                        }
                        break;
                    case 'equ';
                        $result = $condition != $expRule[1] ? 1 : 0;
                        break;
                    case 'nequ';
                        $result = $condition == $expRule[1] ? 1 : 0;
                        break;
                    case 'len';
                        $result = strlen($condition) > $expRule[1] ? 1 : 0;
                        break;
                    case 'range';
                        $ruleData = explode(',', $expRule[1]);
                        if (is_string($condition)) {
                            $result = in_array($condition, $ruleData) ? 1 : 0;
                        } elseif (is_array($condition)) {
                            $firsts = array_column($condition, $item['relation']);
                            foreach ($firsts as $first) {
                                if (in_array($first, $ruleData)) {
                                    $result = 1;
                                    break;
                                } else {
                                    $result = 0;
                                }
                            }
                        }
                        break;
                    case '!empty';
                        $result = $condition == '' ? 1 : 0;
                        break;
                    case 'empty';
                        $result = !empty($condition) ? 1 : 0;
                        break;
                    case 'first';
                        if (is_string($condition)) {
                            $first = substr($condition, 0, 1);
                            if (in_array($first, explode(',', $expRule[1]))) {
                                $result = 1;
                            } else {
                                $result = 0;
                            }
                        } elseif (is_array($condition)) {
                            $firsts = array_column($condition, $item['relation']);
                            $ruleData = explode(',', $expRule[1]);
                            foreach ($firsts as $first) {
                                if (in_array($first, $ruleData)) {
                                    $result = 1;
                                    break;
                                } else {
                                    $result = 0;
                                }
                            }
                        }
                        break;
                    case 'in';
                        $aa = config('dictionaries.' . $item['relation']);
                        $result = !isset($aa[$condition]) ? 1 : 0;
                        break;
                    default;
                        $result = 0;
                        break;
                }
                if ($result) {
                    break;
                }
            }
            if ($result) {
                if (isset($data[$auth]) && $data[$auth] == '') {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }
    }

    public static function gender($data, $item)
    {
        $auth = self::getAuth($data, $item['auth']);
        if ($auth == '') {
            return true;
        } else {
            $rule = explode('|', $item['relation_rule']);
            $rule1 = explode(':', $rule[0]);
            switch ($rule1[0]) {
                case 'equ';
                    $result1 = $auth == $rule1[1] ? 1 : 0;
                    break;
                default;
                    $result1 = 0;
                    break;
            }
            $result2 = 0;
            if ($result1 && isset($rule[1])) {
                $rule2 = explode(':', $rule[1]);
                switch ($rule2[0]) {
                    case 'no';
                        $result2 = self::getAuth($data, $item['relation']) == $rule2[1] ? 1 : 0;
                        break;
                    case 'no_in';
                        $in = explode(',', $rule2[1]);
                        $auth1 = self::getAuth($data, $item['relation'], 'main');
                        if (is_string($auth1)) {
                            $result2 = in_array($auth1, $in) ? 1 : 0;
                        } else {
                            $result2 = array_intersect_assoc($auth1, $in) === [] ? 0 : 1;
                        }

                        break;
                    default;
                        $result2 = 0;
                        break;
                }
            }
            if ($result2) {
                return true;
            } else {
                return false;
            }
        }
    }

    public static function age($data, $item)
    {
        $rule = explode('|', $item['relation_rule']);
        $rule1 = explode(':', $rule[0]);
        switch ($rule1[0]) {
            case 'min';
                $result1 = $data[$item['auth']] < $rule1[1] ? 1 : 0;
                break;
            default;
                $result1 = 0;
                break;
        }
        $result2 = 0;
        if ($result1 && isset($rule[1])) {
            $rule2 = explode(':', $rule[1]);
            switch ($rule2[0]) {
                case 'no';
                    $result2 = isset($data[$item['relation']]) && $data[$item['relation']] == $rule2[1] ? 1 : 0;
                    break;
                case 'no_in';
                    $in = explode(',', $rule2[1]);
                    $result2 = isset($data[$item['relation']]) && in_array($data[$item['relation']], $in) ? 1 : 0;
                    break;
                case 'field';
                    $params = explode(',', $rule2[1]);
                    if ($params[1] == '=' && isset($data[$params[0]])) {
                        $result2 = $data[$params[0]] == $params[2] ? 0 : 1;
                    }
                    break;
                default;
                    $result2 = 0;
                    break;
            }
        }
        if ($result2) {
            return true;
        } else {
            return false;
        }
    }

    public static function getCoderId($data)
    {
        if (empty($data['AEE04_CODE'])) {
            return 0;
        }
        $zzysQuery = Coder::query();
        $zzys = $zzysQuery
            ->where('code', $data['AEE04_CODE'])
            ->first('id');
        if ($zzys) {
            $zzys = $zzys->toArray();
            $zzz_id = $zzys['id'];
        } else {
            $zzzData = [
                'code' => $data['AEE04_CODE'],
                'name' => $data['AEE04']
            ];
            $zzz_id = $zzysQuery->insertGetId($zzzData);
        }
        return $zzz_id;
    }

    public static function getAuth($data, $auth, $isMain = '')
    {
        if (in_array($auth, ['ICD10_ID1', 'ICD10_NAME'])) {
            $result = [];
            foreach ($data['diagnosis'] as $value) {
                if ($value['class'] == $isMain) {
                    $result[] = $value[$auth];
                } else {
                    $result[] = $value[$auth];
                }
            }
        } elseif (in_array($auth, [
            'ICD9_ID1',
            'ICD9_NAME',
            'OPE_DATE',
            'OPE_MAN_NAME',
            'OPE_MAN_CODE',
            'FRIST_ASSISTANT_CODE',
            'FRIST_ASSISTANT_NAME',
            'SECOND_ASSISTANT_CODE',
            'SECOND_ASSISTANT_NAME',
            'HOCUS_WAY_ID',
            'INCISION_GRADE_ID',
            'HOCUS_MAN_CODE',
            'HOCUS_MAN_NAME',
            'START_TIME',
            'END_TIME',
            'OPE_ORDER',
            'OPE_LEVEL',
        ])) {
            $result = [];
            foreach ($data['operation'] as $value) {
                if (!isset($value[$auth]) || empty($value[$auth])) {
                    continue;
                }
                if ($value['class'] == $isMain) {
                    $result[] = $value[$auth];
                } else {
                    $result[] = $value[$auth];
                }
            }
        } else {
            $result = $data[$auth] ?? '';
        }
        return $result;
    }

    //身份号 男 最后二位奇数 女最后二位是 偶数
    public static function verify_card_sex($data, $auth){
        $idcard = $data[$auth['auth']];
        if(strlen($idcard) < 16 ){
            return false;
        }
        // 获取身份证倒数第二位数字
        $number = substr($idcard, strlen($idcard) - 2, 1);
        if ($number % 2 == 0) { // 偶数女
            return $data['AAA02C'] != '女';
        } else { // 基数男
            return $data['AAA02C'] != '男';
        }
    }
}
