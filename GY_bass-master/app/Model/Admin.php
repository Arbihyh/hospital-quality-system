<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Admin extends Model
{
    protected $table = 'admin';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    public $timestamps = false;
    //
//    protected $fillable = ["id","name","account","password","salt","group_id"];
    protected static $field = ["id", "name",  "account", "phone", "realname", "password", "salt", "login_at", "login_ip", "group_id", "token", 'desc'];

    //获取单个
    public static function findOne($id)
    {
        $data = self::query()->where("id", $id)->first(self::$field);
        if ($data) {
            return $data->toArray();
        } else {
            return false;
        }
    }

    //获取单个
    public static function findWhereAccount($account)
    {
        $data = self::query()->where("account", $account)->first(self::$field);
        if ($data) {
            return $data->toArray();
        } else {
            return false;
        }
    }
    //获取单个
    public static function findWhereName($name)
    {
        $data = self::query()->where("name", $name)->first(self::$field);
        if ($data) {
            return $data->toArray();
        } else {
            return false;
        }
    }

    public static function findWhereToken($token)
    {
        $data = self::query()->where(["token" => $token])->first(self::$field);
        if ($data) {
            return $data->toArray();
        } else {
            return false;
        }
    }

    //获取列表
    public static function getInAll($ids)
    {
        $data = self::query()->whereIn("id", $ids)->orderBy('id', 'desc')->get(self::$field);
        if ($data) {
            return $data->toArray();
        } else {
            return false;
        }
    }

    //修改token
    public static function updateLogin($id, $token, $loginAt, $loginIp)
    {
        return self::query()->where(["id" => $id])->update(["token" => $token, "login_at" => $loginAt, "login_ip" => $loginIp]);
    }

    //获取列表
    public static function getPageAll($where = [], $page = 1, $limit = 16)
    {
        $query = self::query();
        if (count($where) > 0) {
            $query = $query->where($where);
        }
        $data = $query->forPage($page, $limit)->orderBy('id', 'desc')->get(self::$field);
        if ($data) {
            return $data->toArray();
        } else {
            return false;
        }
    }

    //获取列表
    public static function getPageCount($where = [])
    {
        $query = self::query();
        if (count($where) > 0) {
            $query = $query->where($where);
        }
        $data = $query->count(self::$field[0]);
        return $data;
    }

    // 添加
    public static function add($name, $account, $pwd, $salt, $groupId, $desc, $phone='', $realname='')
    {
        return self::query()->insert(["name" => $name, "account" => $account, "password" => $pwd, 'salt' => $salt, 'group_id' => $groupId, 'desc' => $desc, 'phone' => $phone, 'realname' => $realname]);
    }

    //查询用户名称和账号是否存在
    public static function findAccountOrName($account, $name)
    {
        $data = self::query()->orWhere(["account" => $account, "name" => $name])->first(self::$field);
        if ($data) {
            return $data->toArray();
        } else {
            return false;
        }
    }

    // 修改
    public static function edit($where, $data)
    {
        return self::query()->where($where)->update($data);
    }

    // 删除管理员组
    public static function delIdAdmin($id)
    {
        return self::query()->where(["id"=>$id])->delete();
    }

    //查看一个管理员组下怼管理员
    public static function getWhereGroupId($groupId,$field=null)
    {
        if($field == null){
            $field = self::$field;
        }
        $data = self::query()->where(["group_id"=>$groupId])->get($field);
        if($data){
            return $data->toArray();
        }else{
            return false;
        }
    }

    public static function getPluckName($where)
    {
        $data = self::query()
            ->where($where)
            ->pluck("name", 'id');
        if ($data) {
            return $data->toArray();
        } else {
            return false;
        }
    }

    public static function getAll($where, $field = null)
    {
        return self::query()
            ->where($where)
            ->get(!$field ? self::$field : $field)->toArray();
    }
}
