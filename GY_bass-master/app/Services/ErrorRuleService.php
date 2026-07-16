<?php


namespace App\Services;

use App\Model\ErrorRule;
use Illuminate\Support\Facades\Cache;

class ErrorRuleService
{
    public static $ruleDataCache = 'rule';
    public static $getList = ['auth','id','field','rule','relation','relation_rule','level','type','error_type','desc','down','category'];
    public static $ruleList = [];
    public static function getRuleList(array $where = []){
        if(self::$ruleList){
            return self::$ruleList;
        }
        $query = ErrorRule::query()
            ->where($where)
            ->orderBy('id', 'asc')
            ->get(self::$getList);
        if (!$query){
            return [];
        }else{
            self::$ruleList = $query->toArray();

        }
        return self::$ruleList;
    }

    public static function handleData(array $data){
        $returnData = [];
        foreach ($data as $item){
            $returnData[] = [
                'id' => $item['id'],
                'field' => $item['field'],
                'desc' => $item['desc']
            ];
        }
        return $returnData;
    }
}
