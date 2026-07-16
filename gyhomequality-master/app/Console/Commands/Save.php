<?php

namespace App\Console\Commands;

use App\Model\Coder;
use App\Model\Error;
use App\Model\ErrorData;
use App\Model\ErrorRule;
use App\Model\OtherDiagnosis;
use App\Services\ErrorRuleService;
use App\Services\MedicalRecordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Pheanstalk\Pheanstalk;

class Save extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:save';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        ini_set('default_socket_timeout', 24 * 60 * 60);
        $beanstalkd = Pheanstalk::create(env('BEANSTALKD') ?? 'localhost')->watch('save');
        while (true) {
            $job = $beanstalkd->reserve();
            $AAA28 = json_decode($job->getData(), true);
            if (!$AAA28) {
                $beanstalkd->delete($job);
            }
            $data = MedicalRecordService::getData($AAA28['AAA28']);
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
                            'error_name' => $item['field'],
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
                            'error_name' => $item['field'],
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
                    if ($data[$item['auth']] != ''){
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
                }elseif ($item['rule'] == 'or'){
                    $result = self::orValue($data,$item);
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
                    $expRule = explode(':',$item['relation_rule']);
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
                            $aa = config('dictionaries');
                            $result = isset($aa[$data[$item['auth']] ?? 'null']) ? 1 : 0;
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
                self::diffInsert($insetData,$data['MED_REC_ID']);
                $beanstalkd->useTube('saveCount')->put(json_encode(['AAA28' => $data['MED_REC_ID']]));
            }
            $beanstalkd->delete($job);
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
                    $result = strlen($auth) <= $expRule[1] ? 1 : 0;
                    break;
                case 'in';
                    $aa = config('dictionaries')[$expRule[1]] ?? [];
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
    public static function orValue($data,$item)
    {
        $condition = self::getAuth($data,$item['relation']);
        $auth = self::getAuth($data,$item['auth']);
        if ($auth == '' && $condition == '') {
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
                    $result = strlen($auth) <= $expRule[1] ? 1 : 0;
                    break;
                case 'in';
                    //$aa = config('dictionaries.' . $expRule[1]);
                    $aa = config('dictionaries')[$expRule[1]] ?? [];
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

        $condition = self::getAuth($data,$item['relation']);
        $auth = self::getAuth($data,$item['auth']);
        if ($condition != '' ) {
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
                        $result = $condition != $expRule[1] ? 1 : 0;
                        break;
                    case 'nequ';
                        $result = $condition == $expRule[1] ? 1 : 0;
                        break;
                    case 'len';
                        $result = strlen($condition) > $expRule[1] ? 1 : 0;
                        break;
                    case 'range';
                        $ruleData = explode(',',$expRule[1]);
                        if (is_string($condition)){
                            $result = in_array($condition,$ruleData) ? 1 : 0;
                        }elseif (is_array($condition)){
                            $firsts = array_column($condition,$item['relation']);
                            foreach ($firsts as $first){
                                if (in_array($first,$ruleData)){
                                    $result = 1;
                                    break;
                                }else{
                                    $result = 0;
                                }
                            }
                        }
                        break;
                    case '!empty';
                        $result = self::getAuth($data,$item['relation']) == '' ? 1 : 0;
                        break;
                    case 'empty';
                        $result = !empty(self::getAuth($data,$item['relation'])) ? 1 : 0;
                        break;
                    case 'first';
                        if (is_string($condition)){
                            $first = substr($condition,0,1);
                            if (in_array($first,explode(',',$expRule[1]))){
                                $result = 1;
                            }else{
                                $result = 0;
                            }
                        }elseif (is_array($condition)){
                            $firsts = array_column($condition,$item['relation']);
                            $ruleData = explode(',',$expRule[1]);
                            foreach ($firsts as $first){
                                if (in_array($first,$ruleData)){
                                    $result = 1;
                                    break;
                                }else{
                                    $result = 0;
                                }
                            }
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
    public static function diffInsert($insert,$aaa28)
    {
        //type = 3 的是手术名称遗漏
        $list = Error::query()->where('AAA28',$aaa28)->where('status',0)->whereIn('type', [0,1,2])->get();
        if ($list){
            $list = $list->toArray();
        }else{
            $list = [];
        }
        if (empty($list)){
            if (!empty($insert)){
                Error::query()->insert($insert);
            }
        }else{
            $list = array_column($list,null,'error_field');
            $insert = array_column($insert,null,'error_field');
            foreach ($list as $item){
                if (!isset($insert[$item['error_field']])){
                    Error::query()->where('id',$item['id'])->update(['status' => 1]);
                    DB::table('error_data')
                        ->where('error_rule',$item['error_rule'])
                        ->where('year',$item['year'])
                        ->where('month',$item['month'])
                        ->decrement('count');
                }
            }
            foreach ($insert as $item){
                if (!isset($list[$item['error_field']])){
                    Error::query()->insert($item);
                    DB::table('error_data')
                        ->where('error_rule',$item['error_rule'])
                        ->where('year',$item['year'])
                        ->where('month',$item['month'])
                        ->increment('count');
                }
            }
        }
    }
}
