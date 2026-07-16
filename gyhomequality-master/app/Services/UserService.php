<?php


namespace App\Services;


use App\Model\Department;
use App\Model\RuleWordMap;
use App\Model\User;
use App\Model\UserGroup;
use App\Model\UserLog;
use App\Model\UserMenu;
use App\Model\UserMenuList;
use App\Model\UserSearchLog;
use Illuminate\Http\Request;

class UserService
{
    /** 是否有权限
     * @param $userGroupId
     * @param $path
     * @return bool
     */
    public static function therePermission($userGroupId, $path)
    {
        // 查询用户组
        $userGroupData = UserGroup::findRole($userGroupId);
        if (!$userGroupData) {
            return false;
        }
        if ($userGroupData['role'] == "all") {
            return true;
        }
        if ($path == "api/user/info" || $path == "api/user/menus") {
            return true;
        }
        // 判断用户组是否有权限
        $pathData = UserMenu::findWhereUrl($path);

        if (!$pathData) {
            return false;
        }
        $roleArr = json_decode($userGroupData['role'], true);
        if (!in_array($pathData['id'], $roleArr) && $pathData['menu_id'] != 12) {
            return false;
        }
        return true;
    }

    /** 获取前台用户列表
     * @param $group_id
     * @param $name
     * @param $page
     * @param $length
     * @return array
     */
    public static function userList($group_id, $name, $page, $length,$realname)
    {
        $where = [];
        if (!empty($name)) {
            $where[] = ['name','like',"%".$name."%"];
        }
        if (!empty($group_id)) {
            $where[] = ['group_id','=',$group_id];
        }
        if (!empty($realname)) {
            $where[] = ['realname','like',"%".$realname."%"];
        }
        $count = User::getPageCount($where);
        if ($count > 0) {
            $depList = Department::getDepartmentData();
            $data = User::getPageAll($where, $page, $length);
            if (!$data) {
                return ["list" => [], "count" => 0];
            } else {
                $ids = array_column($data, 'group_id');
                $userGroup = UserGroup::getInIdAll($ids);
                $userGroup = array_column($userGroup, null, 'id');
                foreach ($data as $k => &$v) {
                    if (stripos($v['dep_id'],']') !== false) {
                        $v['dep_id'] = json_decode($v['dep_id'],true);
                        $arr = [];
                        foreach ($v['dep_id'] as $dep_id) {
                            $arr[] = $depList[$dep_id] ?? $dep_id;
                        }
                        $v['dep_name'] = implode('，',$arr);
                    } else {
                        $v['dep_name'] = '';
                        if (!empty($v['dep_id'])) {
                            $v['dep_name'] = !empty($depList[$v['dep_id']]) ? $depList[$v['dep_id']] : $v['dep_id'];
                        }
                    }
                    $v["group_name"] = $userGroup[$v["group_id"]]["name"];
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


    // 添加前台用户
    public static function addUser($name, $pwd, $groupId, $phone, $realname, $depId, $desc)
    {
        return User::add($name, $pwd, $groupId, $phone, $realname, $depId, $desc);
    }

    // 修改前台用户
    public static function editUser($id, $name, $pwd, $groupId, $phone, $realname, $depId, $desc)
    {
        $data = [];
        if (!empty($name)) {
            $data['name'] = $name;
        }
        if (!empty($pwd)) {
            $data['password'] = $pwd;
        }
        if (!empty($groupId)) {
            $data['group_id'] = $groupId;
        }
        if (!empty($phone)) {
            $data['phone'] = $phone;
        }
        if (!empty($realname)) {
            $data['realname'] = $realname;
        }
        if (!empty($desc)) {
            $data['desc'] = $desc;
        }
        $data['dep_id'] = $depId;
        return User::edit(["id" => $id], $data);
    }

    // 查看用户组
    public static function userGroup()
    {
        return UserGroup::getAll(['id','name']);
    }

    // 写入后台用户操作记录
    public static function writeUserLog(array $data)
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
                $content = json_encode($params);
            }
            $log = new UserLog();
            $log->path = $data['path'];
            $log->method = $data['method'];
            $log->title = $data['title'];
            $log->content = $content;
            $log->username = $data['name'];
            $log->user_id = $data['userId'];
            $log->user_agent = $data['userAgent'];
            $log->ip = $data['ip'];
            return $log->save();
        }
        return false;
    }

    public static function writeSearchUserLog($data)
    {
        if(!empty($data['queryParams'])){
            $params = $data['queryParams'];
            $content = json_encode($params);

            $insertData = [
                'path' =>  $data['path'],
                'method' => $data['method'],
                'title' => $data['title'],
                'type' => $data['type'],
                'content' => $content,
                'user_id' => $data['userId'],
                'username' => $data['name'],
                'user_agent' => $data['userAgent'],
                'ip' => $data['ip'],
                'is_code' => $data['is_code']
            ];
            return UserSearchLog::query()->insert($insertData);
        }

        return false;
    }

    /**
     * @param Request $request
     *
     * 获取当前登录用户可查看数据的部门数据
     */
    public static function getCurrentUserDep(Request $request)
    {
        $token = $request->header('token');
        $user = User::query()->where("token", "=", $token)->get()->toArray();
        $depId = json_decode($user[0]['dep_id'], true);
        $depId = is_array($depId) ? $depId : [$depId];

        $white = RuleWordMap::getFirstById(4001);

        // 超管可以查看所有部门
        $dep = [];
        if (array_intersect($depId, $white) || $user[0]['group_id'] == '1') {
            // 返回具有超级管理员权限的标识
            return 'admin';
        } elseif (!empty($depId)) {
            // 非管理员只能看关联的部门
            $dep = Department::query()->whereIn("dep_id", $depId)->get(['dep_id', 'dep_name'])->toArray();
        }

        // 如果当前登录人的所属部门不在指定的查看所有预警信息的科室信息中，则只能查看指定部门的信息
        $depIds = [];
        if (!empty($dep)) {
            $depIds = array_column($dep, "dep_id");
        }
        return $depIds;
    }
}
