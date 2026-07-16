<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class PACS extends Model
{
	const UPDATED_AT = null;
	//去掉自动更新updat_at

    const EXAM_TYPE = [
        '01' => 'CT（计算机X线断层摄影）',
        '02' => 'MR（核磁共振成像）',
        '03' => 'DSA（数字减影血管造影）',
        '04' => 'X-Ray（普通X光摄影）',
        '05' => 'X-Ray（特殊X光摄影）',
        '06' => 'US（超声检查）',
        '07' => 'Microscopy（病理检查）',
        '08' => 'ES（内窥镜检查）',
        '09' => 'NM（核医学检查）',
        '10' => 'OT（其它检查）',
        '11' => '介入',
    ];

    // 报告单表
    protected $table = 'PACS';
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
            if (!empty($map['title'])) {
                $obj = $obj->where('title', 'like', '%' . $map['title'] . '%');
            }
            if (!empty($map['notice'])) {
                $obj = $obj->where('notice', 'like', '%' . $map['notice'] . '%');
            }
            unset($map['title'], $map['notice']);
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
