<?php

namespace App\Model;

use DateTimeInterface;
use App\Model\BaseModel;
use Illuminate\Support\Facades\DB;

class OperationRelations extends BaseModel
{
    protected $table = 'operation_relations';

    protected static $field = ["id", "fee_name", "operation_name", "code", "price", "fee_unit", "status", "created_at", "updated_at"];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }


    /**
     * @param array $where
     * @param array $column
     * @return array
     */
    public static function getList(array $where = [], array $column = ['*'])
    {
        $obj = self::getWhere($where)->select($column);
        return $obj->get()->toArray();
    }

    //查询费用名称是否存在
    public static function findFeeName($name)
    {
        $data = self::query()->where(["fee_name" => $name])->first(self::$field);
        if ($data) {
            return $data->toArray();
        } else {
            return false;
        }
    }

    //查询编码是否存在
    public static function findCode($code)
    {
        $data = self::query()->where(["code" => $code])->first(self::$field);
        if ($data) {
            return $data->toArray();
        } else {
            return false;
        }
    }

    //查询手术名称是否存在
    public static function findOperationName($operationName)
    {
        $data = self::query()->where(["operation_name" => $operationName])->first(self::$field);
        if ($data) {
            return $data->toArray();
        } else {
            return false;
        }
    }

    // 添加
    public static function add($data)
    {
        return DB::table("operation_relations")->insert($data);
    }

    // 修改
    public static function edit($id, $data)
    {
        return DB::table("operation_relations")->where(["id"=>$id])->update($data);
    }

    // 删除
    public static function deleteById($id)
    {
        return self::query()->where(["id"=>$id])->delete();
    }

    //获取列表
    public static function getPageAll($where = [], $page = 1, $limit = 16)
    {
        $query = self::query();
        if (count($where) > 0) {
            $query = $query->where($where);
        }
        $data = $query->forPage($page, $limit)->orderBy('id', 'desc')->get(self::$field);
        if ($data) {
            return $data->toArray();
        } else {
            return false;
        }
    }

    //获取列表
    public static function getPageCount($where = [])
    {
        $query = self::query();
        if (count($where) > 0) {
            $query = $query->where($where);
        }
        return $query->count(self::$field[0]);
    }
}
