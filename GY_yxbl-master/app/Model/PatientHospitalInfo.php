<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class PatientHospitalInfo extends Model
{
    protected $table = 'patient_hospital_info';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function getOne(array $where = []){

        $obj = self::getWhere($where);
        return $obj->count();
    }

    /**
     * @param array $where
     * @param array $column
     * @return int
     *
     */
    public static function getList(array $where = [], array $column = ['*'])
    {
        $obj = self::getWhere($where);
        $obj = $obj->select($column);
        return $obj->count();
    }

    /**
     * @param array $map
     * @return \Illuminate\Database\Eloquent\Builder
     * 数据获取条件组合
     */
    public static function getWhere(array $map = [])
    {
        $obj = self::query();
        if ($map) {
            if (!empty($map['start_time']) && !empty($map['end_time'])) {
                $startTime = strtotime($map['start_time']);
                $endTime = strtotime($map['end_time']);
                $obj = $obj->whereBetween('UNIX_TIMESTAMP(OPE_DATE)', [$startTime, $endTime]);
                unset($map['start_time']);
                unset($map['end_time']);
            }
            foreach ($map as $key => $val) {
                if (is_array($val)) {
                    $obj = $obj->whereIn($key, $val);
                } else {
                    $obj = $obj->where($key, '=', $val);
                }
            }
        }
        return $obj;
    }
}
