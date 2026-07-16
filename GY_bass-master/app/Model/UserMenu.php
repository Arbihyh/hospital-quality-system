<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class UserMenu extends Model
{
    protected $table = 'user_menu';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    protected static $field = [
        'id', 'title', 'parent_id', 'path', 'component', 'name', 'redirect','always_show', 'keep_alive',
        'hidden', 'icon', 'url', 'type', 'visible', 'status', 'remark', 'sort'];

    /**
     * @return array
     */
    public static function getList(array $ids = [], $fields = [])
    {
        $query = self::query();
        if (!empty($ids)) {
            $query->whereIn("id", $ids);
        }
        $fieldArr = self::$field;
        if($fields) {
            $fieldArr = $fields;
        }
        $where = [
            ['visible', '=', 1],
            ['status', '=', 1]
        ];
        $data = $query->select($fieldArr)->where($where)->get()->toArray();
        return $data;
    }
    /**
     * @return array
     */
    public static function getListByWhere(array $where = [], $fields = [])
    {
        $query = self::query();
        if (!empty($ids)) {
            $query->whereIn("id", $ids);
        }
        $fieldArr = self::$field;
        if($fields) {
            $fieldArr = $fields;
        }

        $query->select($fieldArr);
        if(!empty($where)) {
            $query->where($where);
        }
        $cond = [
            ['visible', '=', 1],
            ['status', '=', 1]
        ];
        $data = $query->where($cond)->get()->toArray();
        return $data;
    }

    /** 根据url查询数据
     * @param $url
     * @return array|false
     */
    public static function findWhereUrl($url)
    {
        $data = self::query()->where("url", $url)->first(self::$field);
        if ($data) {
            return $data->toArray();
        } else {
            return false;
        }
    }
}
