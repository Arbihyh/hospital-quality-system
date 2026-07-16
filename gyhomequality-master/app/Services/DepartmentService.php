<?php


namespace App\Services;


use App\Model\Department;

class DepartmentService
{
    public static $getList = ['name','code as id'];
    public static function getDepartmentList(array $where = []){
//        $query = Department::query()
//            ->where($where)
//            ->get(self::$getList);
//        if (!$query){
//            return [];
//        }else{
//            return $query->toArray();
//        }
        return config('dictionaries.ABAS02');
    }
}
