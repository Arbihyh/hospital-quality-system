<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Api\UserGroupService;
use App\Services\ToolsService;
use App\Services\UserMenuService;
use Illuminate\Http\Request;

/**
 * web端-用户管理--用户组相关控制器
 * @author lch
 * @date 2024/07/24 15:24
 */
class UserGroupController extends Controller
{

    /**
     * 添加用户组时使用的权限下拉数据
     * @param Request $request
     * @return array
     */
    public function menuDropDownList(Request $request)
    {
        $user_id = $request->user()['id'];
        $group_id = $request->user()['group_id'];
        $data = UserMenuService::menuListByUserId($user_id, $group_id);
        return ToolsService::returnData(200, $data, '');
    }

    /**
     * 用户组列表
     * @param Request $request
     * @return array
     */
    public function userGroupList(Request $request)
    {
        $param['page'] = $request->post("page", 1); //当前页码
        $param['len'] = $request->post("len", 10);//每页条数
        $param['user_id'] = $request->user()["id"];
        $param['group_id'] = $request->user()["group_id"];
        $param['search_name'] = $request->post('search_name', '');//搜索条件--角色名称
        $param['start_time'] = $request->post('start_time', '');//搜索条件-更新-开始时间
        $param['end_time'] = $request->post('end_time', '');//搜索条件-更新-结束时间
//        $param['search_role'] = $request->post('search_role','');//搜索条件--权限(也就是菜单id ,隔开)

        $data = UserGroupService::getUserGroupList($param);
        return ToolsService::returnData(200, $data, '');
    }

    /**
     * 添加用户组
     * @author lch
     * @date 2024/07/24 15:24
     * @param Request $request
     * @return array
     */
    public function addUserGroup(Request $request)
    {
        $param['name'] = $request->post('name', '');
        if (empty($param['name'])) {
            return ToolsService::returnData(1, [], '用户组名称不能为空');
        }
        $param['role'] = $request->post("role"); //这个传递字符串，英文,分割
        if (empty($param['role'])) {
            return ToolsService::returnData(1, [], '权限不能为空');
        }
        $param['level'] = $request->post("level", 0);

        $param['desc'] = $request->post("desc", ""); //说明
        $param['user_id'] = $request->user()["id"];//当前登录人
        $param['group_id'] = $request->user()["group_id"];//当前登录人角色id

        return UserGroupService::addUserGroup($param);
    }

    /**
     * 编辑用户组
     * @param Request $request
     * @return array
     */
    public function editUserGroup(Request $request)
    {
        $param['id'] = $request->post('id', '');
        if (empty($param['id'])) {
            return ToolsService::returnData(1, [], '缺少必要的参数');
        }
        $param['name'] = $request->post('name', '');
        if (empty($param['name'])) {
            return ToolsService::returnData(1, [], '用户组名称不能为空');
        }
        $param['role'] = $request->post("role");
        if (empty($param['role'])) {
            return ToolsService::returnData(1, [], '权限不能为空');
        }
        $param['level'] = $request->post("level", 0);
        $param['desc'] = $request->post("desc", "");
        $param['user_id'] = $request->user()["id"];
        $param['group_id'] = $request->user()["group_id"];

        return UserGroupService::editUserGroup($param);
    }

    /**
     * 删除用户组
     * @param Request $request
     * @return array|null
     */
    public function delUserGroup(Request $request)
    {
        $param['id'] = $request->post('id', '');
        if (empty($param['id'])) {
            return ToolsService::returnData(1, [], '缺少必要的参数');
        }
        $param['user_id'] = $request->user()["id"];
        $param['group_id'] = $request->user()["group_id"];
        return UserGroupService::delUserGroup($param);
    }



}
