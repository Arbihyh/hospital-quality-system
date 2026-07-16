<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class CaseQuality extends Model
{
    protected $table = 'case_quality';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function getById(int $id = 0)
    {
        return self::query()->where('BLBH', '=', $id)->get()->toArray();
    }

    public static function getList($page = 1, $pageSize = 20, $column = ['*'])
    {
        $pageStart = ($page - 1) * $pageSize;
        return self::query()->select($column)->offset($pageStart)->LIMIT($pageSize)->get()->toArray();
    }

    /**
     * @param $data
     * @return bool
     * 添加数据
     */
    public static function addData(array $data = [])
    {

        if (!$data) {
            return false;
        }
        return self::query()->insert($data);
    }
}
