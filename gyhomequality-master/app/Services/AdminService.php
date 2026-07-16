<?php


namespace App\Services;


use App\Model\Admin;
use App\Model\AdminGroup;
use App\Model\AdminLog;
use App\Model\AdminMenu;
use Illuminate\Support\Facades\Cache;

class AdminService
{
    public static function login($data)
    {
        $token = md5($data["id"].$data["name"].$data['group_id']);
        $arr = ["id" => $data["id"], "name" => $data["name"], 'group_id' => $data['group_id']];
        Admin::updateLogin($data["id"], $token, date("Y-m-d H:i:s"), request()->getClientIp());
        $arr['token'] = $token;
        return $arr;
    }

    /** token 获取 用户信息
     * @param $token
     * @return array|false|mixed
     */
    public static function tokenGetUser($token)
    {
        $userData = Cache::get($token, false);
        if ($userData === false) {
            $userData = Admin::findWhereToken($token);
        }
        if (!$userData) {
            return false;
        }
        $userData = ["id" => $userData["id"], "name" => $userData["name"], "group_id" => $userData['group_id']];
        Cache::put($token, $userData);
        return $userData;
    }

    /** 是否有权限
     * @param $adminGroupId
     * @param $path
     * @return bool
     */
    public static function therePermission($adminGroupId, $path)
    {
        // 查询用户组
        $adminGroupData = AdminGroup::findRole($adminGroupId);
        if (!$adminGroupData) {
            return false;
        }
        if ($adminGroupData['role'] == "all") {
            return true;
        }
        // 需要登陆，但不需要认证的
        $pathArr = [
            'admin/admin/adminInfo',
            'admin/admin/adminGroup',
            'admin/admin/menuList',
            'admin/user/rbacList',
            'admin/user/userGroup',
            'admin/user/getDeportmentList',
            'admin/user/userSearchLog',
        ];
        if (in_array($path, $pathArr)) {
            return true;
        }
        // 判断用户组是否有权限
        $pathData = AdminMenu::findWhereUrl($path);
        if (!$pathData) {
            return false;
        }
        $roleArr = json_decode($adminGroupData['role'], true);

        if (!in_array($pathData['id'], $roleArr)) {
            return false;
        }
        return true;
    }

    /** 获取管理员列表
     * @param $group_id
     * @param $name
     * @param $page
     * @param $length
     * @return array
     */
    public static function adminList($group_id, $account, $page, $length, $name, $realname)
    {
        $where = [];
        if (!empty($account)) {
            $where[] = ['account','=',$account];
        }
        if (!empty($group_id)) {
            $where[] = ["group_id",'=',$group_id];
        }
        if (!empty($name)) {
            $where[] = ["name",'like',"%".$name."%"];
        }
        if (!empty($realname)) {
            $where[] = ["realname",'like',"%".$realname."%"];
        }
        $count = Admin::getPageCount($where);
        if ($count > 0) {
            $data = Admin::getPageAll($where, $page, $length);
            if (!$data) {
                return ["list" => [], "count" => 0];
            } else {
                $ids = array_column($data, 'group_id');
                $adminGroup = AdminGroup::getInIdAll($ids);
                $adminGroup = array_column($adminGroup, null, 'id');
                foreach ($data as $k => &$v) {
                    $v["group_name"] = $adminGroup[$v["group_id"]]["name"];
                    $v["status"] = $v['id'] == 1 ? 0 : 1;
                    unset($v["password"]);
                    unset($v["salt"]);
                    unset($v["token"]);
                }
                return ["list" => $data, "count" => $count];
            }
        } else {
            return ["list" => [], "count" => 0];
        }
    }


    // 添加管理员
    public static function addAdmin($name="", $account="", $pwd="", $groupId="", $desc="", $phone="", $realname="", $KSDM="", $KSMC="")
    {
        $passwordArr = $pwd;
        return Admin::add($name, $account, $passwordArr, $passwordArr, $groupId, $desc, $phone, $realname, $KSDM, $KSMC);
    }

    // 修改管理员
    public static function editAdmin($id, $name, $account, $pwd, $groupId, $desc, $phone, $realname, $KSDM, $KSMC)
    {
        $data = [];
        $data['KSDM'] = $KSDM ?? "";
        $data['KSMC'] = $KSMC ?? "";
        if (!empty($name)) {
            $data['name'] = $name;
        }
        if (!empty($account)) {
            $data['account'] = $account;
        }
        if (!empty($phone)) {
            $data['phone'] = $phone;
        }
        if (!empty($realname)) {
            $data['realname'] = $realname;
        }
        if (!empty($pwd)) {
//            $passwordArr = $pwd;
//            $data['password'] = $passwordArr["password"];
//            $data['salt'] = $passwordArr['salt'];

            $data['password'] = md5($pwd);
            $data['salt'] = $pwd;
        }
        if (!empty($groupId)) {
            $data['group_id'] = $groupId;
        }
        if (!empty($desc)) {
            $data['desc'] = $desc;
        }
        $adminData = Admin::findOne($id);
        Cache::forget($adminData["token"]);
        $data["token"] = "";
        return Admin::edit(["id" => $id], $data);
    }

    // 查看用户组
    public static function adminGroup()
    {
        return AdminGroup::getAll(['id','name']);
    }

    // 写入后台用户操作记录
    public static function writeAdminLog(array $data)
    {
        $mustDataKey = ['userId', 'name', 'path', 'method', 'queryParams', 'ip', 'title', 'userAgent'];
        $notCheck = ['queryParams'];
        $filter = array_filter(array_keys($data), function ($var) use ($mustDataKey, $notCheck, $data){
            return ( in_array($var, $mustDataKey) && isset($data[$var]) && !empty($data[$var]) ) || in_array($var, $notCheck);
        });
        if (count($filter) == count($mustDataKey)) {
            $content = '';
            if(!empty($data['queryParams'])){
                $params = $data['queryParams'];
                $replaceKeys = ['token', 'pwd', 'password'];
                foreach ($params as $key => &$value) {
                    if(in_array($key, $replaceKeys)){
                        $value = str_repeat('*', strlen($value) );
                    }
                }
                $content = json_encode($params, 256);
            }
            $adminLog = new AdminLog;
            $adminLog->path = $data['path'];
            $adminLog->method = $data['method'];
            $adminLog->title = $data['title'];
            $adminLog->content = $content;
            $adminLog->admin_name = $data['name'];
            $adminLog->admin_id = $data['userId'];
            $adminLog->user_agent = $data['userAgent'];
            $adminLog->ip = $data['ip'];
            $adminLog->created_at = date("Y-m-d H:i:s", time());
            return $adminLog->save();
        }
        return false;
    }
}
