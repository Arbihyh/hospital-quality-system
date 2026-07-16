<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class CaseRule extends Model
{
    protected $table = 'case_rule';

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
        return $obj->select($column)->offset($pageStart)->LIMIT($pageSize)->get()->toArray();
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
            if (!empty($map['department'])) {
                $obj = $obj->where('department', 'like', '%' . $map['department'] . '%');
            }
            if (!empty($map['title'])) {
                $obj = $obj->where('title', 'like', '%' . $map['title'] . '%');
            }
            if (!empty($map['notice'])) {
                $obj = $obj->where('notice', 'like', '%' . $map['notice'] . '%');
            }
            unset($map['title'], $map['notice'], $map['department']);
            foreach ($map as $key => $val) {
                if (is_array($val)) {
                    $obj = $obj->whereIn($key, $val);
                } elseif(isset($val) && (is_numeric($val) || !empty($val))) {
                    $obj = $obj->where($key, '=', $val);
                }
            }
        }
        return $obj;
    }
}
