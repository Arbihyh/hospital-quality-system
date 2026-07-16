<?php


namespace App\Services;


use App\Model\Error;
use App\Model\FeeDetailed;
use App\Model\MainOperation;
use App\Model\OperationRelations;
use App\Model\SecondaryOperation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pheanstalk\Pheanstalk;

class OperationFollow
{
    /**根据收费明细质控遗漏的手术
     * @param $code
     * @param $data
     * @param string $msg
     * @return array
     */
    public static function follow($AAA28){
        Log::info('command:operation-follow', ['action'=>'start']);
        $relations = OperationRelations::getList([], ['fee_name','operation_name','code']);
        //$feeNames = array_column($relations, 'fee_name');
        //print_r($relations);

        $feeList = FeeDetailed::query()
            ->where('AAA28', $AAA28)
            ->orderBy('FYXH')
            ->get(['id', 'FYMC']);
        $feeList = $feeList ? $feeList->toArray() : [];
        $feeOperations = [];
        foreach ($feeList as $feeRow){
            foreach ($relations as $feeName){
                if(strpos($feeRow['FYMC'], $feeName['fee_name']) !== false){
                    $feeOperations[] = ['FYMC'=>$feeName['operation_name'], 'fee_FYMC'=>$feeRow['FYMC'], 'code'=>$feeName['code']];
                }
            }
        }

        //患者主要-次要手术信息
        $operations = self::getOperation($AAA28);

        //print_r($operations);
        $rule = ErrorRuleService::getRuleList(['rule'=>'operation_name_is_exist']);
        $errors = Error::query()
            ->where('AAA28', $AAA28)
            //->where('type', 3)
            ->where('error_rule', $rule[0]['id'])
            ->where('status', 0)
            ->get(['id','status','desc','coder_id']);
        if($errors){
            $errors = $errors->toArray();
        }
        $feeOperationCodes = $uniqueList = [];
        foreach ($feeOperations as $feeOperation){
            if(in_array($feeOperation['code'], $uniqueList)){
                continue;
            }
            if(!in_array($feeOperation['FYMC'], array_column($operations, 'ICD9_NAME'))){
                $feeOperationCodes[] = $feeOperation['code'];
                if(!in_array($feeOperation['code'], array_column($errors, 'coder_id'))){
                    self::appendError($AAA28, $feeOperation['FYMC'], $feeOperation['code'], $rule);
                }
            }
            $uniqueList[] = $feeOperation['code'];
        }
        foreach ($errors as $error){
            if(!in_array($error['coder_id'], $feeOperationCodes)){
                self::deleteError($error['id']);
            }
        }
        //self::appendError($AAA28, $feeOperationNames);
    }

    public static function getOperation($AAA28){
        //患者主要手术信息
        $main_operation_field = ['ICD9_NAME'];
        $main_operation = MainOperation::query()->where('AAA28',$AAA28)->orderBy('OPE_ORDER','asc')->get($main_operation_field);
        if ($main_operation){
            $main_operation = $main_operation->toArray();
        }else{
            $main_operation = [];
        }
        //患者次要手术信息
        $secondary_operation_field = ['ICD9_NAME'];
        $secondary_operation = SecondaryOperation::query()->where('AAA28',$AAA28)->orderBy('OPE_ORDER','asc')->get($secondary_operation_field);
        if ($secondary_operation){
            $secondary_operation = $secondary_operation->toArray();
        }else{
            $secondary_operation = [];
        }
        return array_merge($main_operation,$secondary_operation);
    }

    public static function deleteError($id){
        Db::table('error')->where('id',$id)->delete();
    }

    public static function appendError($AAA28, $desc, $coderId, $rule){
        if(empty($desc)){
            return false;
        }
        $data = MedicalRecordService::getData($AAA28);
        //$rule = ErrorRuleService::getRuleList(['rule'=>'operation_name_is_exist']);

        $insetData[] = [
            'year' => date("Y", strtotime($data['AAC01'])),
            'month' => date("m", strtotime($data['AAC01'])),
            'AAA28' => $data['MED_REC_ID'],
            //'desc' => '忘记质控的手术名称：' . implode(',', $desc),
            'desc' => '遗漏手术：' . $desc,
            'coder_id' => $coderId,
            'error_field' => 'ICD9_NAME',
            'error_name' => '手术名称遗漏',
            //'level' => $item['level'],
            'error_rule' => $rule[0]['id'] ?? 0,
            'type' => $rule[0]['type'] ?? 0,//手术遗漏
            //'coder_id' => $orderId,
            'AAC11C' => $data['AAC11C'],
            //'down' => $item['down'],
            'category' => $rule[0]['category'] ?? 0,
            'error_type' => $rule[0]['error_type'] ?? 0,
            'source' => $data['source']
        ];
        if (!empty($insetData)) {
            Error::query()->insert($insetData);
            //$beanstalkd = Pheanstalk::create(env('BEANSTALKD') ?? 'localhost');
            //$beanstalkd->useTube('count')->put(json_encode(['AAA28' => $data['MED_REC_ID']]));
        }
    }
}
