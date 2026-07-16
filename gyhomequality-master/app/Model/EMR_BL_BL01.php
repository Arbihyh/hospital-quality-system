<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class EMR_BL_BL01 extends Model
{
    protected $table = 'EMR_BL_BL01';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function getById(int $id = 0){
        return self::query()->where('BLBH', '=', $id)->get()->toArray();
    }

    public static function getList($page = 1, $pageSize = 20, $column = ['*'], $where = [])
    {
        $pageStart = ($page - 1) * $pageSize;

        $obj = self::query();
        if($where['BLLB']){
            $obj = $obj->where("BLLB", "=", $where['BLLB']);
        }
        if($where['MBLB']){
            $obj = $obj->whereIn("MBLB", $where['MBLB']);
        }
        return $obj->select($column)->orderBy("BLBH", "asc")->offset($pageStart)->LIMIT($pageSize)->get()->toArray();
    }

    public static function getByNo($no=''){
        $obj = self::query();
        return $obj->orderBy("BLBH", "asc")->where('JZHM', '=', $no)->get()->toArray();
    }

    public static function updateById($id=0, $data = []){
        if(!$data || !$id){
            return false;
        }

        return self::query()->where(['BLBH'=>$id])->update($data);
    }
}
