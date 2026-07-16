<?php


namespace App\Services;


use App\Model\Coder;

class CoderService
{
    public static $getList = ['name','code as id'];
    public static function getCoderList(array $where = []){
        $query = Coder::query()
            ->where($where)
            ->get(self::$getList);
        if (!$query){
            return [];
        }else{
            return $query->toArray();
        }
    }
}
