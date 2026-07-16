<?php

namespace App\Services\Api;

use App\Model\UserGroup;
use App\Model\UserMenu;
use App\Services\ToolsService;
use App\Model\User;

/**
 * 用户组相关服务
 */
class UserGroupService
{
    /**
     * 检查添加的权限是否在响应的范围内
     * @param $all 0 从所有的权限里面进行判断，是否存在违规的， 1 从当前登录的用户所在权限判断，是否有越权限的
     * @param $userGroupId 当前登录的用户组id
     * @return bool
     */
    public static function checkRole($roleArray = [], $userGroupId)
    {
        //非超级管理员判断权限是否在当前用户组权限内
        $role = UserGroup::query()->where('id', $userGroupId)->value('role');

        $role = json_decode($role, true);
        foreach ($roleArray as $v) {
            if (!in_array($v, $role)) {
                return false;
            }
        }
        return true;

    }

    /**
     * 检查用户组名称是否重复
     * @param $name
     * @return bool
     */
    public static function checkUserGroupNameUnique($name)
    {
        $userGroup = UserGroup::query()->where('name', $name)
            ->whereNull('deleted_at')
            ->first();
        if (!empty($userGroup)) {
            return false;
        }
        return true;
    }


    /**
     * 用户组列表
     * @param $params
     * @return array
     */
    public static function getUserGroupList($params)
    {
        $field = ['id', 'name', 'role', 'updated_at', 'level'];
        $query = UserGroup::query()->whereNull("deleted_at")->where('id', '!=', 1);
        //搜索条件,角色名称
        if (!empty($params['search_name'])) {
            $query = $query->where('name', 'like', '%' . $params['search_name'] . '%');
        }
        //搜索条件，更新开始时间
        if (!empty($params['start_time'])) {
            $params['start_time'] = date('Y-m-d 00:00:00', strtotime($params['start_time']));
            $query = $query->where('updated_at', '>=', $params['start_time']);
        }
        //搜索条件，更新结束时间
        if (!empty($params['end_time'])) {
            $params['end_time'] = date('Y-m-d 23:59:59', strtotime($params['end_time']));
            $query = $query->where('updated_at', '<=', $params['end_time']);
        }

        //非超级管理员，查看与自己当前角色所在等级同级或者比自己角色等级低的
        if ($params['group_id'] != 1) {
            $ids = self::getSelfGroupIds($params['group_id']);
            $ids = array_column($ids, 'id');
            $query = $query->whereIn('id', $ids);
        }
        $query = $query->orderBy('id', 'desc');

        $data = $query->paginate($params['len'], $field, 'page', $params['page'])->toArray();

        $result = ['list' => [], 'count' => 0];
        if (!$data) {
            return $result;
        }

        $result['list'] = $data['data'];
        $result['count'] = $data['total'];
        foreach ($result['list'] as $k => $v) {
            if ($v["role"]) {
                // $roleArr = explode(',', $v['role']);
                $roleArr = json_decode($v['role'], true);
                $title = UserMenu::query()->whereIn('id', $roleArr)->get(['title'])->toArray();
                $title = array_column($title, 'title');
                $result['list'][$k]["role_text"] = implode(',', $title);
                $result['list'][$k]["role"] = implode(',', $roleArr);
            }
        }
        return $result;
    }

    public static function getSelfGroupIds($parent_id)
    {
        $list = UserGroup::query()->where('parent_id', $parent_id)->get(['id', 'parent_id'])->toArray();
        $ids = $list;
        foreach ($list as $v) {
            $ids = array_merge($ids, self::getSelfGroupIds($v['id']));
        }
        return $ids;

    }


    /**
     * 添加用户组
     * @param $params
     * @return array
     * @author lch
     * @date 2024/07/24 15:24
     */
    public static function addUserGroup($params)
    {
        //用户组名称的判重
        $res = self::checkUserGroupNameUnique($params['name']);
        if (!$res) {
            return ToolsService::returnData(1, [], '用户组名称已存在');
        }
        $groupInfo = UserGroup::query()->where('id', $params['group_id'])->whereNull('deleted_at')->first()->toArray();
        $params['parent_id'] = $groupInfo['id'];
        //角色的判断权限,非超管
        if ($params['group_id'] != 1) {
            $res = self::checkRole($params['role'], $params['group_id']);
            if (!$res) {
                return ToolsService::returnData(1, [], '用户组权限不足');
            }
            //根据当前登录用户的角色数据
            if (empty($params['level'])) {
                $params['level'] = $groupInfo['level'] + 1;
            } else {
                //看看传递的level是否比自己高
                if ($params['level'] <= $groupInfo['level']) {
                    return ToolsService::returnData(1, [], '角色等级不能超过上级');
                }
            }
        }

        $params['role'] = array_unique($params['role']);
        //添加用户组
        $insert = [
            // 'role' => implode(',', $params['role']),
            'role' => json_encode($params['role'], 256),
            'name' => $params['name'],
            'desc' => $params['desc'],
            'user_id' => $params['user_id'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'), //更新时间
            'parent_id' => $params['parent_id'],
            'level' => $params['level']
        ];
        UserGroup::query()->insert($insert);
        return ToolsService::returnData(200, [], '操作成功');
    }

    /**
     * 编辑用户组
     * @param $params
     * @return array
     */
    public static function editUserGroup($params)
    {
        //判断名称是否存在
        $userGroup = UserGroup::query()->where('name', $params)
            ->where('id', '!=', $params['id'])
            ->whereNull('deleted_at')
            ->first();
        if (!empty($userGroup)) {
            return ToolsService::returnData(1, [], '用户组名称已存在');
        }
        $editInfo = UserGroup::query()->where('id', $params['id'])
            ->whereNull('deleted_at')->first()->toArray();
        $groupInfo = UserGroup::query()->where('id', $params['group_id'])->whereNull('deleted_at')->first()->toArray();
        $params['parent_id'] = $groupInfo['id'];
        if ($params['group_id'] != 1) {
            //非超级管理员判断权限是否在当前用户组权限内
            $oldRoles = json_decode($editInfo['role'], true);//数据有原有的
            $editRoles = $params['role'];//编辑传递的参数
            sort($oldRoles);
            sort($editRoles);
            if ($oldRoles !== $editRoles) {
                $arrDiff = array_diff($editRoles, $oldRoles);
                if (!empty($arrDiff) && !self::checkRole($arrDiff, $params['group_id'])) {
                    return ToolsService::returnData(1, [], '用户组权限不足');
                }
                $arrDiff2 = array_diff($oldRoles, $editRoles);
                if (!empty($arrDiff) && !self::checkRole($arrDiff2, $params['group_id'])) {
                    return ToolsService::returnData(1, [], '用户组权限不足');
                }
            }
            //不是自己的,或者自己所在权限比要修改的这条数据的权限低
            $selfGroupInfo = UserGroup::query()->where('id', $params['group_id'])->first()->toArray();
            $info = UserGroup::query()->where('id', $params['id'])->first()->toArray();
            if ($selfGroupInfo['level'] >= $info['level']) {
                return ToolsService::returnData(1, [], '无权限操作');
            }

            //根据当前登录用户的角色数据
            if (empty($params['level'])) {
                $params['level'] = $groupInfo['level'] + 1;
            } else {
                //看看传递的level是否比自己高
                if ($params['level'] <= $groupInfo['level']) {
                    return ToolsService::returnData(1, [], '角色等级不能超过上级');
                }
            }
        }

        $params['role'] = array_unique($params['role']);
        //修改用户组
        $update = [
            // 'role' => implode(',', $params['role']),
            'role' => json_encode($params['role'], 256),
            'name' => $params['name'],
            'desc' => $params['desc'],
            'updated_at' => date('Y-m-d H:i:s')
        ];
        UserGroup::query()->where('id', $params['id'])->update($update);
        return ToolsService::returnData(200, [], '操作成功');
    }


    /**
     * 删除权限组
     * @param $params
     * @return array|void
     */
    public static function delUserGroup($params)
    {
        //超级管理员用户组禁止编辑，禁止删除
        if ($params['id'] == 1) {
            return ToolsService::returnData(1, [], '无权限操作');
        }
        //已经被删除了
        $info = UserGroup::query()->where('id', $params['id'])->first()->toArray();
        if (!empty($info['deleted_at'])) {
            return ToolsService::returnData(1, [], '用户组已删除');
        }
        if ($params['group_id'] != 1) {
            //自己所在权限比要修改的这条数据的权限低
            $selfGroupInfo = UserGroup::query()->where('id', $params['group_id'])->first()->toArray();
            $info = UserGroup::query()->where('id', $params['id'])->first()->toArray();
            if ($selfGroupInfo['level'] >= $info['level']) {
                return ToolsService::returnData(1, [], '无权限操作');
            }
        }
        //权限下有人禁止删除
        $user = User::query()->where('group_id', $params['id'])->whereNull('deleted_at')->first();
        if (!empty($user)) {
            return ToolsService::returnData(1, [], '该用户组下有用户，禁止删除');
        }

        UserGroup::query()->where('id', $params['id'])->update(['deleted_at' => date('Y-m-d H:i:s')]);
        return ToolsService::returnData(200, [], '操作成功');
    }


}
