<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\AdminGroup;
use App\Model\Admin;
use App\Model\AdminLog;
use App\Model\AdminMenu;
use App\Services\AdminGroupService;
use App\Services\AdminService;
use App\Services\MenuService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    public function adminList(Request $request)
    {
        $page = $request->post("page");
        if (!is_numeric($page)) {
            $page = 1;
        }
        $length = $request->post("limit");
        if (!is_numeric($length)) {
            $length = 16;
        }

        $account = $request->post("account");
        $group_id = $request->post("group_id");
        $data = AdminService::adminList($group_id, $account, $page, $length);
        return ToolsService::returnAdmin(0, $data);
    }

    public function addAdmin(Request $request)
    {
        $account = $request->post("account");
        if (empty($account)) {
            return ToolsService::returnAdmin(1, '',  '工号不能为空');
        }
        $phone = $request->post("phone", "");
        if(!empty($phone)){
            if(!phoneFormatCheck($phone)) {
                return ToolsService::returnAdmin(1, '',  '手机号码格式不正确');
            }
        }
        $pwd = $request->post("pwd");
        if (empty($pwd)) {
            return ToolsService::returnAdmin(1, '',  '密码不能为空');
        }
        $group_id = $request->post("group_id");
        if (empty($group_id)) {
            return ToolsService::returnAdmin(1, '',  '管理员组不能为空');
        }
        $name = $request->post("name", "");
        $desc = $request->post("desc", "");
        $realname = $request->post("realname", "");

        $data = Admin::findAccountOrName($account, $name);
        if ($data) {
            return ToolsService::returnAdmin(1,false,"管理员已存在");
        }
        $state = AdminService::addAdmin($name, $account, $pwd, $group_id, $desc, $phone, $realname);
        if ($state) {
            return ToolsService::returnAdmin(0, '', '操作成功');
        } else {
            return ToolsService::returnAdmin(0, '', '操作成功');
        }
    }

    public function editAdmin(Request $request)
    {
        $id = $request->post("id");
        if (empty($id) || !is_numeric($id)) {
            return ToolsService::returnAdmin(1, '', '管理员ID错误');
        }
        $account = $request->post("account");
        if (empty($account)) {
            return ToolsService::returnAdmin(1, '',  '工号不能为空');
        }
        $phone = $request->post("phone", "");
        if(!empty($phone)){
            if(!phoneFormatCheck($phone)) {
                return ToolsService::returnAdmin(1, '',  '手机号码格式不正确');
            }
        }
        $group_id = $request->post("group_id");
        if (empty($group_id)) {
            return ToolsService::returnAdmin(1, '',  '管理员组不能为空');
        }
        $pwd = $request->post("pwd");

        $name = $request->post("name", "");
        $desc = $request->post("desc", "");
        $phone = $request->post("phone", "");
        $realname = $request->post("realname", "");
        $state = AdminService::editAdmin($id, $name, $account, $pwd, $group_id, $desc, $phone, $realname);
        if ($state) {
            return ToolsService::returnAdmin(0, '', '操作成功');
        } else {
            return ToolsService::returnAdmin(0, '', '操作成功');
        }
    }

    public function adminGroup()
    {
        $data = AdminService::adminGroup();
        return ToolsService::returnAdmin(0,$data);
    }


    public function delAdmin(Request $request)
    {
        $adminIds = [1];
        $id = $request->post("id");
        if(!is_numeric($id)){
            return ToolsService::returnAdmin(1, '管理员信息错误');
        }
        if(in_array($id,$adminIds)){
            return ToolsService::returnAdmin(1,'',"此管理员不能删除");
        }
        $state = Admin::delIdAdmin($id);
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
        $data = AdminGroupService::getAdminGroupList($page, $len);
        return ToolsService::returnAdmin(0, $data);
    }


    public function delAdminGroup(Request $request)
    {
        $adminGroupIds = [1];
        $id = $request->post("id");
        if(!is_numeric($id)){
            return ToolsService::returnAdmin(1, '', '参数Id错误');
        }
        if(in_array($id,$adminGroupIds)){
            return ToolsService::returnAdmin(1, '', '不能删除');
        }
        $adminDate = Admin::getWhereGroupId($id,['id','name']);
        if($adminDate){
            return ToolsService::returnAdmin(1, '', '使用中不能删除');
        }
        $state = AdminGroup::delIdAdminGroup($id);
        if ($state) {
            return ToolsService::returnAdmin(0, '', '操作成功');
        } else {
            return ToolsService::returnAdmin(0, '', '操作成功');
        }
    }

    public function addAdminGroup(Request $request)
    {
        $groupName = $request->post("name");
        if (empty($groupName)) {
            return ToolsService::returnAdmin(1, '', '部门名称不能为空');
        }
        $desc = $request->post("desc", "");
        $role = $request->post("role");
        if (!is_array($role) || count($role) < 1) {
            return ToolsService::returnAdmin(1, '', '权限不能为空');
        }
        $id = $request->user()["id"];
        $state = AdminGroupService::add($groupName, $desc, $role, $id);
        if ($state) {
            return ToolsService::returnAdmin(0, '', '操作成功');
        } else {
            return ToolsService::returnAdmin(0, '', '操作成功');
        }
    }

    public function editAdminGroup(Request $request)
    {
        $id = $request->post("id");
        if (empty($id) || !is_numeric($id)) {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }
        $groupName = $request->post("name");
        if (empty($groupName)) {
            return ToolsService::returnAdmin(1, '', '部门名称不能为空');
        }
        $role = $request->post("role");
        if (!is_array($role) || count($role) < 1) {
            return ToolsService::returnAdmin(1, '', '权限参数错误');
        }
        $desc = $request->post("desc", "");

        $state = AdminGroupService::edit($id, $groupName, $desc, $role);
        if ($state) {
            return ToolsService::returnAdmin(0, '', '操作成功');
        } else {
            return ToolsService::returnAdmin(0, '', '操作成功');
        }
    }

    public function adminInfo(Request $request){
        $admin = $request->user();
        $menus = MenuService::groupMenus($admin['group_id']);
        $data = [
            'roles' => $admin['group_id'],
            'introduction' => 'I am a super administrator',
            'avatar' => 'https://wpimg.wallstcn.com/f778738c-e4f8-4870-b634-56703b4acafe.gif',
            'name' => $admin['name'],
            'nickname' => $admin['name'],
            'accessApi' => array_filter(array_column($menus, 'url'))
        ];
        return ToolsService::returnAdmin(0, $data);
    }

    public function adminLogList(Request $request)
    {
        $page = $request->post("page", 1);
        $limit = $request->input("limit",10);
        $name = $request->post('name');
        $query = AdminLog::query();
        $where = [];
        if(!empty($name)){
            $where[] = ['admin_name', 'like', '%'.$name.'%'];
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
