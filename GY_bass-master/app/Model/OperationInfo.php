<?php

namespace App\Model;

use DateTimeInterface;
use App\Model\BaseModel;

class OperationInfo extends BaseModel
{
    protected $table = 'operation_info';

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
}
