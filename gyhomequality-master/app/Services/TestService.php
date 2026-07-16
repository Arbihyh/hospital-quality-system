<?php

namespace App\Services;

use App\Model\Coder;
use App\Model\Error;
use App\Model\OtherDiagnosis;
use App\Model\ErrorRule;
use App\Model\PatientInfo;
use Pheanstalk\Pheanstalk;

class TestService
{
    public static function quality($AAA28)
    {
        ini_set('default_socket_timeout', 24 * 60 * 60);
            $data = MedicalRecordService::getData($AAA28);
            $orderId = self::getCoderId($data);
            $rule = ErrorRuleService::getRuleList();
            $c_year = date("Y", strtotime($data['AAC01']));
            $c_month = date("m", strtotime($data['AAC01']));
            $insetData = [];
            foreach ($rule as $item) {
                if ($item['rule'] == 'required') {
                    $result = self::required($data,$item);
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
                            'AAC11C' => $data['AAC11C'],
                            'down' => $item['down'],
                            'category' => $item['category'],
                            'error_type' => $item['error_type'],
                            'source' => $data['source']
                        ];
                    }
                    continue;
                } elseif ($item['rule'] == 'age') {
                    $result = self::age($data,$item);
                    if ($result) {
                        $insetData[] = [
                            'year' => $c_year,
                            'month' => $c_month,
                            'AAA28' => $data['MED_REC_ID'],
                            'desc' => $item['desc'],
                            'error_field' => $item['auth'],
                            'error_name' => $item['name'],
                            'level' => $item['level'],
                            'error_rule' => $item['id'],
                            'type' => $item['type'],
                            'error_type' => $item['error_type'],
                            'AAC11C' => $data['AAC11C'],
                            'coder_id' => $orderId,
                            'down' => $item['down'],
                            'category' => $item['category'],
                            'source' => $data['source']
                        ];
                    }
                    continue;
                }  elseif ($item['rule'] == 'gender') {
                    $result = self::gender($data,$item);
                    if ($result) {
                        $insetData[] = [
                            'year' => $c_year,
                            'month' => $c_month,
                            'AAA28' => $data['MED_REC_ID'],
                            'desc' => $item['desc'],
                            'error_field' => $item['auth'],
                            'error_name' => $item['name'],
                            'level' => $item['level'],
                            'error_rule' => $item['id'],
                            'type' => $item['type'],
                            'error_type' => $item['error_type'],
                            'AAC11C' => $data['AAC11C'],
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
                            'AAC11C' => $data['AAC11C'],
                            'down' => $item['down'],
                            'category' => $item['category'],
                            'source' => $data['source']
                        ];
                    }
                    continue;
                } elseif ($item['rule'] == 'code') {
                    $codeResult = false;
                    if ($item['relation_rule'] == '*'){
                        if (preg_match('[\*]', $data[$item['auth']])) {
                            $codeResult = true;
                        }
                    }elseif ($item['relation_rule'] == 'M'){
                        if (preg_match('/^M?$/', $data[$item['auth']])) {
                            $codeResult = true;
                        }
                    }elseif ($item['relation_rule'] == 'no_die'){
                        $no_die = explode('|',$item['relation']);
                        if (in_array($data['ABC01C'], $no_die)){
                            $codeResult = true;
                        }
                    }else{
                        $relation_rule = explode('|',$item['relation_rule']);
                        $relation_rule1 = explode(':',$relation_rule[0]);
                        if ($relation_rule1[0] == 'equ'){
                            $other = OtherDiagnosis::query()
                                ->where('AAA28', $data['MED_REC_ID'])
                                ->where('ICD10_ID1',$relation_rule1[1])
                                ->first();
                            if (isset($relation_rule[1]) && $other){
                                $relation_rule2 = explode(':',$relation_rule[1]);
                                if ($relation_rule2[0] == '!empty'){
                                    if ($data[$relation_rule2[1]] == '') {
                                        $codeResult = true;
                                    }
                                }
                            }
                        }
                    }
                    if ($codeResult){
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
                            'AAC11C' => $data['AAC11C'],
                            'down' => $item['down'],
                            'category' => $item['category'],
                            'source' => $data['source']
                        ];
                    }
                    continue;
                }elseif ($item['rule'] == 'condition'){
                    $result = self::condition($data,$item);
                    if ($result){
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
                            'AAC11C' => $data['AAC11C'],
                            'down' => $item['down'],
                            'category' => $item['category'],
                            'source' => $data['source']
                        ];
                    }
                    continue;
                } else {
                    $expRule = explode(':',$item);
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
                            'AAC11C' => $data['AAC11C'],
                            'down' => $item['down'],
                            'category' => $item['category'],
                            'source' => $data['source']
                        ];
                    }
                    continue;
                }
            }
            if (!empty($insetData)) {

                Error::query()->where(['AAA28'=>$data['MED_REC_ID']])->delete();
                Error::query()->insert($insetData);
                $res = self::updateScore($data['MED_REC_ID']);
                var_dump($res);exit;
//                $beanstalkd->useTube('count')->put(json_encode(['AAA28' => $data['MED_REC_ID']]));
            }

    }

    public static function required($data,$item)
    {
        $auth = self::getAuth($data,$item['auth']);
        if ($auth == '' ) {
            return true;
        } else {
            $expRule = explode(':',$item['relation_rule']);
            switch ($expRule[0]) {
                case 'min';
                    $result = $auth < $expRule[1] ? 1 : 0;
                    break;
                case 'max';
                    if (is_numeric($expRule[1])){
                        $result = $auth > $expRule[1] ? 1 : 0;
                    }else{
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
                    $result = strlen($auth) < $expRule[1] ? 1 : 0;
                    break;
                case 'in';
                    $aa = config('dictionaries.' . $expRule[1]);
                    $result = isset($aa[$auth]) ? 1 : 0;
                    break;
                default;
                    $result = 1;
                    break;
            }
            if ($result){
                return false;
            }
            return true;
        }
    }
    public static function condition($data,$item)
    {

        $auth = self::getAuth($data,$item['auth']);
        if ($auth == '' ) {
            return true;
        } else {
            $rule = explode('｜',$item['relation_rule']);
            $result = 0;
            foreach ($rule as $value){
                $expRule = explode(':',$value);
                switch ($expRule[0]) {
                    case 'min';
                        $result = $auth < $expRule[1] ? 1 : 0;
                        break;
                    case 'relation_min';
                        $result = self::getAuth($data,$item['relation']) < $expRule[1] ? 1 : 0;
                        break;
                    case 'relation_max';
                        if (is_int($expRule[1])){
                            $result = self::getAuth($data,$item['relation']) > $expRule[1] ? 1 : 0;
                        }else{
                            $result = self::getAuth($data,$item['relation']) > $data[$expRule[1]] ? 1 : 0;
                        }
                        break;
                    case 'max';
                        if (is_int($expRule[1])){
                            $result = $auth > $expRule[1] ? 1 : 0;
                        }else{
                            $result = $auth > $data[$expRule[1]] ? 1 : 0;
                        }
                        break;
                    case 'equ';
                        $result = self::getAuth($data,$item['relation']) != $expRule[1] ? 1 : 0;
                        break;
                    case 'nequ';
                        $result = self::getAuth($data,$item['relation']) == $expRule[1] ? 1 : 0;
                        break;
                    case 'len';
                        $result = self::getAuth($data,$item['relation']) > $expRule[1] ? 1 : 0;
                        break;
                    case 'range';
                        $result = in_array(self::getAuth($data,$item['relation']),explode(',',$expRule[1])) ? 1 : 0;
                        break;
                    case '!empty';
                        $result = self::getAuth($data,$item['relation']) == '' ? 1 : 0;
                        break;
                    case 'first';
                        $first = substr(self::getAuth($data,$item['relation']),0,1);
                        if (in_array($first,explode(',',$expRule[1]))){
                            $result = 1;
                        }else{
                            $result = 0;
                        }
                        break;
                    case 'in';
                        $aa = config('dictionaries.' . $item['relation']);
                        $result = !isset($aa[self::getAuth($data,$item['relation'])]) ? 1 : 0;
                        break;
                    default;
                        $result = 0;
                        break;
                }
                if ($result){
                    break;
                }
            }
            if ($result){
                if (isset($data[$auth]) && $data[$auth] == ''){
                    return true;
                }else{
                    return false;
                }
            }else{
                return false;
            }
        }
    }
    public static function gender($data,$item){
        $auth = self::getAuth($data,$item['auth']);
        if ($auth == '' ) {
            return true;
        } else {
            $rule = explode('|',$item['relation_rule']);
            $rule1 = explode(':',$rule[0]);
            switch ($rule1[0]){
                case 'equ';
                    $result1 = $auth == $rule1[1] ? 1 : 0;
                    break;
                default;
                    $result1 = 0;
                    break;
            }
            $result2 = 0;
            if ($result1 && isset($rule[1])){
                $rule2 = explode(':',$rule[1]);
                switch ($rule2[0]){
                    case 'no';
                        $result2 = self::getAuth($data,$item['relation']) == $rule2[1] ? 1 : 0;
                        break;
                    case 'no_in';
                        $in = explode(',',$rule2[1]);
                        $auth1 = self::getAuth($data,$item['relation'],'main');
                        if (is_string($auth1)){
                            $result2 = in_array($auth1,$in) ? 1 : 0;
                        }else{
                            $result2 = array_intersect_assoc($auth1,$in) === [] ? 0 : 1;
                        }

                        break;
                    default;
                        $result2 = 0;
                        break;
                }
            }
            if ($result2){
                return true;
            }else{
                return false;
            }
        }
    }
    public static function age($data,$item)
    {
        $rule = explode('|',$item['relation_rule']);
        $rule1 = explode(':',$rule[0]);
        switch ($rule1[0]){
            case 'min';
                $result1 = $data[$item['auth']] < $rule1[1] ? 1 : 0;
                break;
            default;
                $result1 = 0;
                break;
        }
        $result2 = 0;
        if ($result1 && isset($rule[1])){
            $rule2 = explode(':',$rule[1]);
            switch ($rule2[0]){
                case 'no';
                    $result2 = $data[$item['relation']] == $rule2[1] ? 1 : 0;
                    break;
                case 'no_in';
                    $in = explode(',',$rule2[1]);
                    $result2 = in_array($data[$item['relation']],$in) ? 1 : 0;
                    break;
                default;
                    $result2 = 0;
                    break;
            }
        }
        if ($result2){
            return true;
        }else{
            return false;
        }
    }
    public static function getCoderId($data)
    {
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
    public static function getAuth($data,$auth,$isMain=''){
        if (in_array($auth, ['ICD10_ID1','ICD10_NAME'])){
            $result = [];
            foreach ($data['diagnosis'] as $value){
                if ($value['class'] == $isMain){
                    $result[] = $value[$auth];
                }else{
                    $result[] = $value[$auth];
                }
            }
        }elseif (in_array($auth, [
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
        ])){
            $result = [];
            foreach ($data['operation'] as $value){
                if ($value['class'] == $isMain){
                    $result[] = $value[$auth];
                }else{
                    $result[] = $value[$auth];
                }
            }
        }
        else{
            $result = $data[$auth] ?? '';
        }
        return $result;
    }

    public static function updateScore($MED_REC_ID)
    {
        $ruleList = ErrorRule::query()->select(['auth','down'])->get()->toArray();
        foreach($ruleList as $v)
        {
            $ruleLists[$v['auth']] = $v['down'];
        }
        $jb_d = ['AAA29','AAA01','AAA09','AAA43','AAA06C','AAA07','AAA18C','AAA08C','AAA48','AAA51','AAA17C','AAA45','AAA14C','AAA19','AAA20','AAA21C','AAA22','AAA23C','AAA24','AAA25'];//单次0.5，最多4分
        $zl_d = [];
        $zl_a1 = [];
        $zl_a2 = [];
        $zl_b1 = [];
        $zl_b2 = [];
        $zl_d = ['ABG01C','ABG01C','ABF01N','ABF01C','ABF04','AEG01C','AEG02C','OPE_LEVEL','OPE_MAN_NAME','FRIST_ASSISTANT_NAME'];//单次0.5分，最多3分
        $fy_d = [];

        $data = Error::query()->where(['AAA28'=>$MED_REC_ID])->select(['error_field'])->get()->toArray();
        $jb_d_n = 0;
        $zl_d_n = 0;
        $score = 0;
        if(!count($data)){
            PatientInfo::query()->where(['MED_REC_ID'=>$MED_REC_ID])->update(['score'=>100]);
            return 200;
        }
        foreach($data as $v)
        {
            if(in_array($v['error_field'],$jb_d)){
                $jb_d_n = $jb_d_n + 1;
                if($jb_d_n <= 8){
                    $score = $score + $ruleLists[$v['error_field']];
                }
            }elseif(in_array($v['error_field'],$zl_d)){
                $zl_d_n = $jb_d_n + 1;
                if($zl_d_n <= 6){
                    $score = $score + $ruleLists[$v['error_field']];
                }
            }else{
                $score = $score + $ruleLists[$v['error_field']];
            }


        }
        $ret = PatientInfo::query()->where(['MED_REC_ID'=>$MED_REC_ID])->update(['score'=>(100-$score)]);
        echo 200;exit;

    }
}
