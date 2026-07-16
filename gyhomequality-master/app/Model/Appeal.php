<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class Appeal extends Model
{
    protected $table = 'appeal';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }


    public static function getCount($where = [])
    {
        $obj = self::getWhere($where);
        return $obj->count();
    }

    public static function getList($page = 1, $pageSize = 20, $column = ['*'], $where = [])
    {
        $obj = self::getWhere($where);
        $pageStart = ($page - 1) * $pageSize;
        return $obj->select($column)->orderBy("status", "asc")->offset($pageStart)->LIMIT($pageSize)->get()->toArray();
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
            if (!empty($map['appeal_start_time']) && !empty($map['appeal_end_time'])) {
                $obj = $obj->whereBetween('appeal_time', [$map['appeal_start_time'] / 1000, $map['appeal_end_time'] / 1000]);
                unset($map['appeal_start_time'], $map['appeal_end_time']);
            }
            if (!empty($map['AAB01_start_time']) && !empty($map['AAB01_end_time'])) {
                $obj = $obj->where('AAB01', '>', date("Y-m-d H:i:s", $map['AAB01_start_time'] / 1000));
                $obj = $obj->where('AAB01', '<', date("Y-m-d H:i:s", $map['AAB01_end_time'] / 1000));
                unset($map['AAB01_start_time'], $map['AAB01_end_time']);
            }
            if (!empty($map['examine_start_time']) && !empty($map['examine_end_time'])) {
                $obj = $obj->whereBetween('examine_time', [$map['examine_start_time'] / 1000, $map['examine_end_time'] / 1000]);
                unset($map['examine_start_time'], $map['examine_end_time']);
            }
            if (!empty($map['defect_content'])) {
                $obj = $obj->where('defect_content', 'like', '%' . $map['defect_content'] . '%');
                unset($map['defect_content']);
            }
            foreach ($map as $key => $val) {
                if (is_array($val)) {
                    $obj = $obj->whereIn($key, $val);
                } elseif (!empty($val) && isset($val) && !is_numeric($val)) {
                    $obj = $obj->where($key, '=', $val);
                }
            }
        }
        return $obj;
    }
}
