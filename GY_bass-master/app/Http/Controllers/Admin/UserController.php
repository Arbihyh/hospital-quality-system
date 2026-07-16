<?php
/*
 * Created by PhpStorm
 * User: moquan
 * Date: 2023/2/9
 * Time: 17:54
 */

namespace App\Http\Controllers\Admin;


use App\Http\Controllers\Controller;
use App\Model\User;
use App\Model\UserGroup;
use App\Model\UserLog;
use App\Services\MenuService;
use App\Services\ToolsService;
use App\Services\UserGroupService;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function userList(Request $request)
    {
        $page = $request->post("page");
        if (!is_numeric($page)) {
            $page = 1;
        }
        $length = $request->post("length");
        if (!is_numeric($length)) {
            $length = 16;
        }

        $name = $request->post("name");
        $group_id = $request->post("group_id");
        $data = UserService::userList($group_id, $name, $page, $length);
        return ToolsService::returnAdmin(0, $data);
    }

    public function addUser(Request $request)
    {
        $name = $request->post("name");
        if (empty($name)) {
            return ToolsService::returnAdmin(1, '',  '工号不能为空');
        }
        $pwd = $request->post("pwd");
        if (empty($pwd)) {
            return ToolsService::returnAdmin(1, '',  '密码不能为空');
        }
        $group_id = $request->post("group_id");
        if (empty($group_id)) {
            return ToolsService::returnAdmin(1, '',  '用户部门不能为空');
        }
        $phone = $request->post("phone", "");
        if(!empty($phone)){
            if(!phoneFormatCheck($phone)) {
                return ToolsService::returnAdmin(1, '',  '手机号码格式不正确');
            }
        }
//        $status = $request->post("status");
//        if ( !is_numeric($status + 0) ) {
//            return ToolsService::returnAdmin(1, '',  '用户状态不能为空');
//        }
        $realname = $request->post("realname", "");
        $data = User::findName($name);
        if ($data) {
            return ToolsService::returnAdmin(1,false,"userRepeat");
        }
        $state = UserService::addUser($name, $pwd, $group_id, $phone, $realname);
        if ($state) {
            return ToolsService::returnAdmin(0, '', '操作成功');
        } else {
            return ToolsService::returnAdmin(0, '', '操作成功');
        }
    }

    public function editUser(Request $request)
    {
        $id = $request->post("id");
        if (empty($id) || !is_numeric($id)) {
            return ToolsService::returnAdmin(1, '', '用户ID错误');
        }
        $name = $request->post("name");
        if (empty($name)) {
            return ToolsService::returnAdmin(1, '',  '工号不能为空');
        }
        $group_id = $request->post("group_id");
        if (empty($group_id)) {
            return ToolsService::returnAdmin(1, '',  '用户部门不能为空');
        }
        $phone = $request->post("phone", "");
        if(!empty($phone)){
            if(!phoneFormatCheck($phone)) {
                return ToolsService::returnAdmin(1, '',  '手机号码格式不正确');
            }
        }
//        $status = $request->post("status");
//        if ( !is_numeric($status + 0) ) {
//            return ToolsService::returnAdmin(0);
//        }
        $realname = $request->post("realname", "");
        $pwd = $request->post("pwd");
        $state = UserService::editUser($id, $name, $pwd, $group_id, $phone, $realname);
        if ($state) {
            return ToolsService::returnAdmin(0, '', '操作成功');
        } else {
            return ToolsService::returnAdmin(0, '', '操作成功');
        }
    }

    public function userGroup()
    {
        $data = UserService::userGroup();
        return ToolsService::returnAdmin(0,$data);
    }


    public function delUser(Request $request)
    {
        $id = $request->post("id");
        if(!is_numeric($id)){
            return ToolsService::returnAdmin(1, '用户参数错误');
        }
        $state = User::delIdUser($id);
        if ($state) {
            return ToolsService::returnAdmin(0, '', '操作成功');
        } else {
            return ToolsService::returnAdmin(0, '', '操作成功');
        }
    }

    public function groupList(Request $request)
    {
        $page = $request->post("page");
        if (!is_numeric($page)) {
            $page = 1;
        }
        $len = $request->post("len");
        if (!is_numeric($len)) {
            $len = 15;
        }
        $data = UserGroupService::getUserGroupList($page, $len);
        return ToolsService::returnAdmin(0, $data);
    }


    public function delUserGroup(Request $request)
    {
        $userGroupIds = [1];
        $id = $request->post("id");
        if(!is_numeric($id)){
            return ToolsService::returnAdmin(1, '', '组参数错误');
        }
        if(in_array($id,$userGroupIds)){
            return ToolsService::returnAdmin(1, '', '组参数错误');
        }
        $userDate = User::getWhereGroupId($id,['id','name']);
        if($userDate){
            return ToolsService::returnAdmin(1, '', '使用中不能删除');
        }
        $state = UserGroup::delIdUserGroup($id);
        if ($state) {
            return ToolsService::returnAdmin(0, '', '操作成功');
        } else {
            return ToolsService::returnAdmin(0, '', '操作成功');
        }
    }

    public function addUserGroup(Request $request)
    {
        $groupName = $request->post("name");
        if (empty($groupName)) {
            return ToolsService::returnAdmin(1, '', '用户部门名称不能为空');
        }
        $desc = $request->post("desc", '');
//        if (empty($desc)) {
//            return ToolsService::returnAdmin(1, '', '描述不能为空');
//        }
        $role = $request->post("role");
        if (!is_array($role) || count($role) < 1) {
            return ToolsService::returnAdmin(1, '', '权限不能为空');
        }
        $id = $request->user()["id"];
        $state = UserGroupService::add($groupName, $desc, $role, $id);
        if ($state) {
            return ToolsService::returnAdmin(0, '', '操作成功');
        } else {
            return ToolsService::returnAdmin(0, '', '操作成功');
        }
    }

    public function editUserGroup(Request $request)
    {
        $id = $request->post("id");
        if (empty($id) || !is_numeric($id)) {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }
        $groupName = $request->post("name");
        if (empty($groupName)) {
            return ToolsService::returnAdmin(1, '', '用户部门名称不能为空');
        }
        $role = $request->post("role");
        if (!is_array($role) || count($role) < 1) {
            return ToolsService::returnAdmin(1, '', '权限参数错误');
        }
        $desc = $request->post("desc");
//        if (empty($desc)) {
//            return ToolsService::returnAdmin(1, '', '描述不能为空');
//        }
        $state = UserGroupService::edit($id, $groupName, $desc, $role);
        if ($state) {
            return ToolsService::returnAdmin(0, '', '操作成功');
        } else {
            return ToolsService::returnAdmin(0, '', '操作成功');
        }
    }

    public function userLogList(Request $request)
    {
        $page = $request->post("page", 1);
        $limit = $request->input("limit",10);
        $name = $request->post('name');
        $query = UserLog::query();
        $where = [];
        if(!empty($name)){
            $where[] = ['username', 'like', '%'.$name.'%'];
        }
        if($where){
            $query->where($where);
        }
        $count = $query->where($where)->count();
        $list = $query->orderBy('id','desc')->paginate($limit, ['*'], 'page', $page);

        return ToolsService::returnAdmin(0, [
            'list'=> $list->items(),
            'count' => $count
        ]);
    }
}
