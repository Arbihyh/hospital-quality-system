<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UserGroup extends Model
{
    protected $table = 'user_group';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    public $timestamps = false;
    //
//    protected $field = ["id","role","name","desc"];
    protected static $field = ["id", "role", "name", "desc", "admin_id"];

    //查询权限
    public static function findRole($id)
    {
        $data = self::query()->where("id", $id)->first(self::$field);
        if ($data) {
            return $data->toArray();
        } else {
            return false;
        }
    }

    // in查询前台用户列表
    public static function getInIdAll($id)
    {
        $data = self::query()->whereIn("id", $id)->get(self::$field);
        if ($data) {
            return $data->toArray();
        } else {
            return false;
        }
    }

    // 获取前台用户组
    public static function getUserGroupPage($page = 1, $len = 15)
    {
        $data = self::query()->forPage($page, $len)->orderBy('id', 'desc')->get(self::$field);
        if ($data) {
            return $data->toArray();
        } else {
            return false;
        }
    }


    // 添加前台用户组
    public static function add($name, $desc, $role, $admin_id)
    {
        return DB::table("user_group")->insert(["name" => $name, "role" => $role, "desc" => $desc, "admin_id" => $admin_id]);
    }

    // 修改前台用户组
    public static function edit($id,$name, $desc, $role)
    {
        return DB::table("user_group")->where(["id"=>$id])->update(["name" => $name, "role" => $role, "desc" => $desc]);
    }

    // 查询全部前台用户组昵称id
    public static function getAll($field=null)
    {
        if($field == null){
            $field = [self::$field[0],self::$field[2]];
        }
        $data = self::query()->get($field);
        if ($data) {
            return $data->toArray();
        } else {
            return false;
        }
    }

    // 删除前台用户组
    public static function delIdUserGroup($id)
    {
        return self::query()->where(["id"=>$id])->delete();
    }
}
