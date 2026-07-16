<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use App\Model\BaseModel;

class IllnessTypeCount extends BaseModel
{
    protected $table = 'illness_type_count';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function add(array $data = []){
        return self::query()->insert($data);
    }

    /**
     * @param array $where
     * @param string $orderKey
     * @param string $sortValue
     * @return array
     * 获取单病种质量的不同年份的统计数据
     */
    public static function getList(array $where = [], string $orderKey='month', string $sortValue = 'asc'){
        $obj = self::query();
        return self::getWhere($where)->orderBy($orderKey, $sortValue)->get()->toArray();
    }
}
