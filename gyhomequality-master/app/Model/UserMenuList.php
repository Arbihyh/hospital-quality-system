<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class UserMenuList extends Model
{
    protected $table = 'user_menu_list';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    protected static $field = ["id", "name", "url", "menu_id", "menu", "online", "desc", "is_show", "icon"];

    /**
     * @param array $id
     * @param bool $menu true是查菜单的 false查全部
     * @return array|false
     */
    public static function getMenuList($id, $menu = true)
    {
        $query = self::query();
        if ($menu) {
            $query = $query->where("menu", 1);
        }
        $data = $query->whereIn("id", $id)->get(self::$field);
        if (!$data) {
            return false;
        } else {
            return $data->toArray();
        }
    }

    /**
     * @param bool $menu true是查菜单的 false查全部
     * @return array|false
     */
    public static function getMenuAll($menu = true)
    {
        $query = self::query();
        if ($menu) {
            $query = $query->where("menu", 1);
        }
        $data = $query->get(self::$field);
        if (!$data) {
            return false;
        } else {
            return $data->toArray();
        }
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
