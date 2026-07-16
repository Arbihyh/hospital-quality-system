<?php


namespace App\Services;

use App\Model\OperationRelations;

class OperationRelationsService
{
    /** 获取前台用户列表
     * @param $group_id
     * @param $name
     * @param $page
     * @param $length
     * @return array
     */
    public static function relationsList($operationName, $name, $code, $page, $length)
    {
        $where = [];
        if (!empty($name)) {
            $where["fee_name"] = $name;
        }
        if (!empty($code)) {
            $where["code"] = $code;
        }
        if (!empty($operationName)) {
            $where["operation_name"] = $operationName;
        }
        $count = OperationRelations::getPageCount($where);
        if ($count > 0) {
            $data = OperationRelations::getPageAll($where, $page, $length);
            if (!$data) {
                return ["list" => [], "count" => 0];
            } else {
                return ["list" => $data, "count" => $count];
            }
        } else {
            return ["list" => [], "count" => 0];
        }
    }

    public static function addOperationRelations($feeName, $operationName, $code, $price, $unit){
        $relations = OperationRelations::findFeeName($feeName);
        if ($relations) {
            return false;
        }
        $relations = OperationRelations::findOperationName($operationName);
        if ($relations) {
            return false;
        }
        $relations = OperationRelations::findCode($code);
        if ($relations) {
            return false;
        }
        $data = [
            'fee_name'=>$feeName,
            'operation_name'=>$operationName,
            'code'=>$code,
            'price'=>$price,
            'fee_unit'=>$unit
        ];
        return OperationRelations::add($data);
    }

    //编辑
    public static function editOperationRelationsById($id, $feeName, $operationName, $code, $price, $unit){
        $data = [
            'fee_name'=>$feeName,
            'operation_name'=>$operationName,
            'code'=>$code,
            'price'=>$price,
            'fee_unit'=>$unit
        ];
        return OperationRelations::edit($id, $data);
    }

    //删除
    public static function delOperationRelationsById($id){
        return OperationRelations::deleteById($id);
    }

    //编辑
    public static function updateStatusById($id, $status){
        $data = [
            'status'=>$status
        ];
        return OperationRelations::edit($id, $data);
    }
}
