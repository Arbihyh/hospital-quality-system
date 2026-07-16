<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AdminGroup extends Model
{
    protected $table = 'admin_group';
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

    // in查询管理员列表
    public static function getInIdAll($id)
    {
        $data = self::query()->whereIn("id", $id)->get(self::$field);
        if ($data) {
            return $data->toArray();
        } else {
            return false;
        }
    }

    // 获取管理员组
    public static function getAdminGroupPage($page = 1, $len = 15)
    {
        $data = self::query()->forPage($page, $len)->orderBy('id', 'desc')->get(self::$field);
        if ($data) {
            return $data->toArray();
        } else {
            return false;
        }
    }


    // 添加管理员组
    public static function add($name, $desc, $role, $admin_id)
    {
        return DB::table("admin_group")->insert(["name" => $name, "role" => $role, "desc" => $desc, "admin_id" => $admin_id]);
    }

    // 修改管理员组
    public static function edit($id,$name, $desc, $role)
    {
        return DB::table("admin_group")->where(["id"=>$id])->update(["name" => $name, "role" => $role, "desc" => $desc]);
    }

    // 查询全部管理员组昵称id
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

    // 删除管理员组
    public static function delIdAdminGroup($id)
    {
        return self::query()->where(["id"=>$id])->delete();
    }
}
