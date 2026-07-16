<?php

namespace App\Services\Api;

use App\Model\Department;
use App\Model\RuleWordMap;
use App\Model\User;
use App\Model\UserGroup;
use App\Services\ToolsService;
use Illuminate\Support\Facades\DB;

class NewUserService
{
    /**
     * 获取科室下拉列表数据
     * @param $params
     * @return array
     */
    public static function getDepDropDownList($params)
    {
        /**
         * 查询管理科室
         */
        $KSID = RuleWordMap::query()->where('id', '=', 4001)->value('keyword');

        if (!empty($KSID)) {
            $KSID = explode(',', $KSID);
        } else {
            $KSID = [];
        }

        /**
         * 获取数组相同的科室id
         */
        $department = json_decode($params['dep_id'], true) ?? [];
        $dId = array_intersect($department, $KSID);

        //超级管理员将所有的科室数据返回
        if ($params['group_id'] == 1 || !empty($dId)) {
            $data = Department::query()->where('type_id', 2)->get(['dep_name as name', 'dep_id'])->toArray();
            return (array)$data ?? [];
        }
        //非超级管理员获取自己能看到的科室数据
        $depIds = User::query()->where('id', $params['user_id'])->value('dep_id');
        if (empty($depIds)) {
            return [];
        }
        $depIds = json_decode($depIds, true);
        $data = Department::query()->where('type_id', 2)->whereIn('dep_id', $depIds)->get(['dep_name as name', 'dep_id'])->toArray();
        return $data ?? [];

    }

    /**
     * 用户列表
     * @param $params
     * @return array
     */
    public static function userList($params)
    {
        $field = ['id', 'name', 'created_at', 'updated_at', 'group_id', 'desc', 'phone', 'realname', 'dep_id', 'w_id', 's_id', 'department_review', 'is_toexamine'];
        $query = User::query()
            ->whereNull('deleted_at')
            ->where('status', 0);
        if ($params['login_group_id'] != 1) {
            $query = $query->where('group_id', '!=', 1);
        }
        //搜索条件--工号
        if (!empty($params['search_gh'])) {
            $query = $query->where('name', 'like', '%' . $params['search_gh'] . '%');
        }
        //搜索条件--姓名
        if (!empty($params['search_xm'])) {
            $query = $query->where('realname', 'like', '%' . $params['search_xm'] . '%');
        }
        //搜索条件--科室
        if (!empty($params['search_ks'])) {
            $search_ks = $params['search_ks'];
            $query = $query->where(function ($query) use ($search_ks) {
                foreach ($search_ks as $v) {
                    $query->orWhereRaw("JSON_CONTAINS(dep_id, \"".$v."\")");
                }
            });
        }
        //搜索条件--角色
        if (!empty($params['search_js'])) {
            $query->where('group_id', $params['search_js']);
        }
        //搜索条件--更新开始时间
        if (!empty($params['start_time'])) {
            $start_time = date('Y-m-d 00:00:00', strtotime($params['start_time']));
            $query = $query->where('updated_at', '>=', $start_time);
        }
        //搜索条件--更新结束时间
        if (!empty($params['end_time'])) {
            $end_time = date('Y-m-d 23:59:59', strtotime($params['end_time']));
            $query = $query->where('updated_at', '<=', $end_time);
        }

        //非超级管理员，那就查看自己所具备的角色等级以及以下的用户，超级管理员查看所有的
        if ($params['login_group_id'] != 1) {
            $ids = UserGroupService::getSelfGroupIds($params['login_group_id']);
            $Ids = array_column($ids, 'id');
            $query = $query->whereIn('group_id', $Ids);
            $query = $query->orWhere('id',$params['user_id']);
        }

        $data = $query->orderBy('id')->paginate($params['len'], $field, 'page', $params['page'])->toArray();
        $result = ['list' => [], 'count' => 0];
        if (!$data) {
            return $result;
        }
        //获取科室数据
        $department = Department::query()->pluck('dep_name', 'dep_id')->toArray();
        //获取所有的用户组
        $userGroup = UserGroup::query()->whereNull('deleted_at')->pluck('name', 'id')->toArray();

        $result['list'] = $data['data'];
        $result['count'] = $data['total'];
        foreach ($result['list'] as &$v) {
            $dep_name_arr = [];

            $v['dep_name'] = "";

            //科室转换成文字
            if (!empty($v['dep_id'])) {
                $v['dep_id'] = $dep_ids = json_decode($v['dep_id'], true);
                if (is_array($dep_ids)) {
                    foreach ($dep_ids as $vs) {
                        if (isset($department[$vs])) {
                            $dep_name_arr[] = $department[$vs];
                        }
                    }
                }
                $v['dep_name'] = empty($dep_name_arr) ? "" : implode(',', $dep_name_arr);
            }
            //角色转换成文字
            if (!empty($v['group_id'])) {
                $v['group_name'] = isset($userGroup[$v['group_id']]) ? $userGroup[$v['group_id']] : "";
            }
            //处理一下null
            $v['phone'] = !empty($v['phone']) ? $v['phone'] : "";
            $v['realname'] = !empty($v['realname']) ? $v['realname'] : "";
            $v['w_id'] = json_decode($v['w_id'], true) ?: [];
            $v['s_id'] = json_decode($v['s_id'], true) ?: [];
        }
        return $result;
    }

    /**
     * 添加用户
     * @param $params
     * @return array
     */
    public static function addUser($params)
    {
        //判断工号是否重复
        $userInfo = User::query()->where('name', $params['name'])->whereNull('deleted_at')->first();
        if (!empty($userInfo)) {
            return ToolsService::returnData(1, '', '工号已经存在');
        }

        /**
         * 查询科室是否存在自动审核人
         */
        if (!empty($params['is_toexamine'])) {
            $user = User::query()
                ->where('is_toexamine', '=', 1)
                ->whereNull('deleted_at')
                ->whereIn('dep_id', $params['dep_id'])
                ->first();

            if (!empty($user)) {
                return ToolsService::returnData(1, '', '当前科室已经存在审核员');
            }
        }

        /**
         * 查询管理科室
         */
        $KSID = RuleWordMap::query()->where('id', '=', 4001)->value('keyword');

        if (!empty($KSID)) {
            $KSID = explode(',', $KSID);
        } else {
            $KSID = [];
        }

        /**
         * 获取数组相同的科室id
         */
        $department = json_decode($params['login_dep_id'], true) ?? [];
        $dId = array_intersect($department, $KSID);

        //非超级管理员
        if ($params['login_group_id'] != 1 && empty($dId)) {
            //判断科室是不是当前登录人员所具备的科室
            $dep_id = $params['dep_id'];
            $dep_id2 = json_decode($params['login_dep_id'], true) ?: [];
            foreach ($dep_id as $v) {
                if (!in_array($v, $dep_id2)) {
                    return ToolsService::returnData(1, '', '科室不在当前登录人员所具备的科室内');
                }
            }
            //角色
            $editGroupLevel = UserGroup::query()->where('id',$params['group_id'])->value('level');
            $loginGroupLevel = UserGroup::query()->where('id',$params['login_group_id'])->value('level');
            if ($loginGroupLevel > $editGroupLevel){
                return ToolsService::returnData(1, '', '角色权限不足');
            }
        }

        $insertData = [
            'name'       => $params['name'],            //工号
            'password'   => md5($params['pwd']),             //密码
            'group_id'   => $params['group_id'],        //角色
            'dep_id'     => json_encode($params['dep_id']), //科室
            'created_at' => date('Y-m-d H:i:s'), //创建时间
            'updated_at' => date('Y-m-d H:i:s'), //更新时间
            'phone'      => $params['phone'],           //电话,手机号
            'desc'       => $params['desc'],            //描述
            'realname'   => $params['realname'],        //姓名
            'w_id'       => empty($params['w_id']) ? '' : json_encode($params['w_id']),  //病区科室代码
            's_id'       => empty($params['s_id']) ? '' : json_encode($params['s_id']),  //员工id
            'department_review' => $params['department_review'], //科室审核
            'is_toexamine' => $params['is_toexamine'], //自动审核人
        ];
        $userId = DB::table('user')->insertGetId($insertData);

        return ToolsService::returnData(200, '', '添加成功');
    }

    /**
     * 编辑用户
     * @param $params
     * @return array
     */
    public static function editUser($params)
    {
        $updateData = [];
        //判断工号是否重复
        $userInfo = User::query()
            ->where('id', '!=', $params['id'])
            ->where('name', $params['name'])->whereNull('deleted_at')->first();
        if (!empty($userInfo)) {
            return ToolsService::returnData(1, '', '工号已经存在');
        }

        /**
         * 查询科室是否存在自动审核人
         */
        if (!empty($params['is_toexamine'])) {
            $user = User::query()
                ->where('is_toexamine', '=', 1)
                ->where('id', '<>', $params['id'])
                ->whereNull('deleted_at')
                ->whereIn('dep_id', $params['dep_id'])
                ->first();

            if (!empty($user)) {
                return ToolsService::returnData(1, '', '当前科室已经存在审核员');
            }
        }

        $editUserInfo = User::query()->where('id', $params['id'])->first()->toArray();
        $loginUserGroupInfo = UserGroup::query()->where('id', $params['login_group_id'])->first()->toArray();
        $editUserGroupInfo = UserGroup::query()->where('id', $editUserInfo['group_id'])->first()->toArray();
        /**
         * 查询管理科室
         */
        $KSID = RuleWordMap::query()->where('id', '=', 4001)->value('keyword');

        if (!empty($KSID)) {
            $KSID = explode(',', $KSID);
        } else {
            $KSID = [];
        }

        /**
         * 获取数组相同的科室id
         */
        $department = json_decode($params['login_dep_id'], true) ?? [];
        $dId = array_intersect($department, $KSID);

        //非超级管理员
        if ($params['login_group_id'] != 1 && empty($dId)) {
            //当前登录人所在角色等级比要修改的用户所在角色等级低，则不能修改
            if ($loginUserGroupInfo['level'] > $editUserGroupInfo['level']) {
                return ToolsService::returnData(1, '', '权限不足');
            }

            //判断科室是不是当前登录人员所具备的科室
            $dep_id = $params['dep_id'];
            $oldDepId = json_decode($editUserInfo['dep_id'], true) ?: [];
            $login_dep_id = json_decode($params['login_dep_id'], true) ?: [];
            sort($dep_id); //传递的科室参数
            sort($oldDepId);//要编辑的用户原本有的科室数据
            sort($login_dep_id);//当前登录人所具备的科室数据
            if ($dep_id != $login_dep_id) {
                //看看传递的参数是不是有多的
                $arrDiff = array_diff($dep_id, $oldDepId);
                if (!empty($arrDiff) && !self::checkDep($arrDiff, $login_dep_id)) {
                    return ToolsService::returnData(1, '', '科室不在当前登录人员所具备的科室内');
                }
                //看看传递的参数是不是有少的
                $arrDiff2 = array_diff($oldDepId, $dep_id);
                if (!empty($arrDiff2) && !self::checkDep($arrDiff2, $login_dep_id)) {
                    return ToolsService::returnData(1, '', '无权限删除该用户的科室');
                }
            }
        }
        $updateData['name'] = $params['name'];
        $updateData['group_id'] = $params['group_id'];
        $updateData['dep_id'] = $params['dep_id'];
        $updateData['phone'] = $params['phone'];
        $updateData['desc'] = $params['desc'];
        $updateData['realname'] = $params['realname'];
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        $updateData['w_id'] = empty($params['w_id']) ? '' : json_encode($params['w_id']);
        $updateData['s_id'] = empty($params['s_id']) ? '' : json_encode($params['s_id']);
        $updateData['department_review'] = $params['department_review'];
        $updateData['is_toexamine'] = $params['is_toexamine'];

        User::query()->where('id', $params['id'])->update($updateData);
        return ToolsService::returnData(200, '', '操作成功');
    }

    /**
     * 删除用户
     * @param $params
     * @return array
     */
    public static function delUser($params)
    {
        //判断该账户是否存在
        $info = User::query()->where('id', $params['id'])->whereNull('deleted_at')->first()->toArray();
        if (empty($info)) {
            return ToolsService::returnData(1, '', '用户不存在');
        }
        if ($params['login_group_id'] != 1) {
            $loginUserGroupInfo = UserGroup::query()->where('id', $params['login_group_id'])->first()->toArray();
            $editUserGroupInfo = UserGroup::query()->where('id', $info['group_id'])->first()->toArray();
            //不是自己的并且权限不够
            if ($params['id'] != $params['user_id'] && $loginUserGroupInfo['level'] > $editUserGroupInfo['level']) {
                return ToolsService::returnData(1, '', '权限不足');
            }
        }
        User::query()->where('id', $params['id'])->update(['deleted_at' => date('Y-m-d H:i:s')]);
        return ToolsService::returnData(200, '', '操作成功');
    }

    /**
     * 锁定或解锁用户
     * @param $params
     * @return array
     */
    public static function lockAndUnlockUser($params)
    {
        //判断该账户是否存在
        $info = User::query()->where('id', $params['id'])->whereNull('deleted_at')->first()->toArray();
        if (empty($info)) {
            return ToolsService::returnData(1, '', '用户不存在');
        }
        if ($params['login_group_id'] != 1) {
            $loginUserGroupInfo = UserGroup::query()->where('id', $params['login_group_id'])->first()->toArray();
            $editUserGroupInfo = UserGroup::query()->where('id', $info['group_id'])->first()->toArray();
            //不是自己的并且权限不够
            if ($params['id'] != $params['user_id'] && $loginUserGroupInfo['level'] > $editUserGroupInfo['level']) {
                return ToolsService::returnData(1, '', '权限不足');
            }
        }
        $status = User::query()->where('id', $params['id'])->value('status');
        $updateStatus = $status == 1 ? 0 : 1;
        User::query()->where('id', $params['id'])->update(['status' => $updateStatus]);
        return ToolsService::returnData(200, '', '操作成功');
    }

    /**
     * 修改密码
     * @param $params
     * @return array
     */
    public static function editPassword($params)
    {
        //判断该账户是否存在
        $info = User::query()->where('id', $params['id'])->whereNull('deleted_at')->first()->toArray();
        if (empty($info)) {
            return ToolsService::returnData(1, '', '用户不存在');
        }
        if ($params['login_group_id'] != 1) {
            $loginUserGroupInfo = UserGroup::query()->where('id', $params['login_group_id'])->first()->toArray();
            $editUserGroupInfo = UserGroup::query()->where('id', $info['group_id'])->first()->toArray();
            //不是自己的并且权限不够
            if ($params['id'] != $params['user_id'] && $loginUserGroupInfo['level'] > $editUserGroupInfo['level']) {
                return ToolsService::returnData(1, '', '权限不足');
            }
        }

        User::query()->where('id', $params['id'])->update(['password' => md5($params['password']), 'token' => '']);
        \Illuminate\Support\Facades\Session::put($info['token'], ""); //清空这个修改密码的人，强行下线
        return ToolsService::returnData(200, '', '操作成功');
    }

    public static function checkDep($depIdArr, $depIdArr2)
    {
        foreach ($depIdArr as $v) {
            if (in_array($v, $depIdArr2)) {
                return false;
            }
        }
        return true;
    }
}
