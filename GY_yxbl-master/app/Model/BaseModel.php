<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{


    /**
     * @param int $id
     * @param array|string[] $columns
     * @return array
     * 根据ID查询数据
     */
    public function getById(int $id, array $columns = ['*']): array
    {
        if (empty($id)) {
            return [];
        }
        $obj = self::query()->where('id', $id)->first($columns);
        if (empty($obj)) {
            return [];
        }
        return $obj->toArray();
    }

    /**
     * @param int $id
     * @param array $data
     * @param string $remark
     * @return int
     * 根据主键ID修改数据
     */
    public function updateById(int $id, array $data): int
    {
        if (empty($id) || empty($data)) {
            return 0;
        }
        return self::query()->where('id', $id)->update($data);
    }

    /**
     * @param array $ids
     * @param array $data
     * @param string $remark
     * @return int
     * 根据主键ID修改数据批量修改数据
     */
    public function updateByIds(array $ids, array $data, string $remark = ''): int
    {
        if (empty($ids) || empty($data)) {
            return 0;
        }

        return self::query()->whereIn('id', $ids)->update($data);
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
