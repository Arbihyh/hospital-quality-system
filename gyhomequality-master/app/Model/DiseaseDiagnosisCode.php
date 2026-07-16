<?php


namespace App\Model;

use DateTimeInterface;
use App\Model\BaseModel;

class DiseaseDiagnosisCode extends BaseModel
{
    protected $table = 'disease_diagnosis_code';

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

    /**
     * @param string $name
     * @return array
     * 根据名称获取信息
     */
    public static function getInfoByName($name = '')
    {
        $obj = self::query()->where('ICD10_NAME', $name)->first();
        if(!$obj){
            return [];
        }
        return $obj->toArray();
    }
}
