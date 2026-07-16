<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class AdminMenu extends Model
{
    protected $table = 'admin_menu';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    protected static $field = ['id', 'name', 'parent_id', 'component', 'url', 'type', 'visible', 'status', 'icon', 'remark', 'sort'];

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
        return $query->select($fieldArr)->where($where)->get()->toArray();
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
