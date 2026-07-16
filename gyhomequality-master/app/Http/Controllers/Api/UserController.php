<?php
/*
 * Created by PhpStorm
 * User: moquan
 * Date: 2023/2/9
 * Time: 17:54
 */

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Model\Department;
use App\Model\Staff;
use App\Model\UserGroup;
use App\Services\Api\NewUserService;
use App\Services\Api\UserGroupService;
use App\Services\MenuService;
use App\Services\ToolsService;
use App\Services\UserMenuService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function groupMenuTree(Request $request)
    {
        $params['group_id'] = $request->user()['group_id'];
        $data = UserMenuService::groupMenuTree($params);
        return ToolsService::returnData(200, $data);
    }

    public function userInfo(Request $request)
    {
        $user = $request->user();
        // 这先去掉,不用返回可访问接口列表了
        //        $menus = UserMenuService::groupMenus($user['group_id']);
        $data = [
            'roles'        => $user['group_id'],
            'introduction' => '',
            'avatar'       => 'https://wpimg.wallstcn.com/f778738c-e4f8-4870-b634-56703b4acafe.gif',
            'name'         => $user['name'],
            'nickname'     => $user['name'],
            'accessApi'    => []
        ];
        return ToolsService::returnData(200, $data);
    }

    /**
     * 添加编辑用户时使用的科室下拉数据
     * @param Request $request
     * @return array
     */
    public function depDropDownList(Request $request)
    {
        $params['group_id'] = $request->user()['group_id'];
        $params['dep_id'] = $request->user()['dep_id'];
        $params['user_id'] = $request->user()['id'];
        $data = NewUserService::getDepDropDownList($params);
        return ToolsService::returnData(200, $data);
    }


    /**
     * 获取全部科室列表
     * @return array
     */
    public function allDepartmentList()
    {
        $data = Department::query()->get(['dep_name as name', 'dep_id'])->toArray();
        return ToolsService::returnData(200, $data);
    }



    public function userList(Request $request)
    {
        $param['page'] = $request->post("page", 1); //当前页码
        $param['len'] = $request->post("len", 10); //每页条数
        $param['user_id'] = $request->user()["id"];
        $param['login_group_id'] = $request->user()["group_id"];
        $param['dep_id'] = $request->user()["dep_id"];
        $param['search_gh'] = $request->post("search_gh", ""); //搜索条件--工号
        $param['search_xm'] = $request->post("search_xm", ""); //搜索条件--姓名
        $param['search_ks'] = $request->post("search_ks", []); //搜索条件--科室
        $param['search_js'] = $request->post("search_js", ""); //搜索条件--角色
        $param['start_time'] = $request->post("start_time", ""); //搜索条件--更新开始时间
        $param['end_time'] = $request->post("end_time", ""); //搜索条件--更新结束时间
        $data = NewUserService::userList($param);
        return ToolsService::returnData(200, $data, '');
    }

    /**
     * 添加用户
     * @param Request $request
     * @return array|void
     */
    public function addUser(Request $request)
    {
        //工号，web登录账号
        $params['name'] = $request->post("name", "");
        if (empty($params['name'])) {
            return ToolsService::returnData(1, '', '工号不能为空');
        }
        //密码
        $params['pwd'] = $request->post("pwd", "");
        if (empty($params['pwd'])) {
            return ToolsService::returnData(1, '', '密码不能为空');
        }
        if (!validatePasswordIsEasy($params['pwd'])) {
            return ToolsService::returnData(1, '', '密码长度至少8位,必须包含大写字母,小写字母以及数字');
        }
        //姓名
        $params['realname'] = $request->post("realname", "");
        if (empty($params['realname'])) {
            return ToolsService::returnData(1, '', '姓名不能为空');
        }

        //科室可多选，这个记得验证一下，是不是当前登录人员所具备的科室
        $params['dep_id'] = $request->post('dep_id', []);
        if (empty($params['dep_id'])) {
            return ToolsService::returnData(1, '', '请选择科室');
        }

        //角色,这个记得验证一下
        $params['group_id'] = $request->post('group_id', "");
        if (empty($params['group_id'])) {
            return ToolsService::returnData(1, '', '请选择角色');
        }
        if (is_array($params['group_id'])) {
            $params['group_id'] = implode(',', $params['group_id']);
        }
        //手机号，非必填项
        $params['phone'] = $request->post('phone', "");
        if (!empty($params['phone'])) {
            if (!phoneFormatCheck($params['phone'])) {
                return ToolsService::returnData(1, '', '手机号码格式不正确');
            }
        }
        //备注，非必填项
        $params['desc'] = $request->post('desc', "");

        $params['user_id'] = $request->user()['id'];
        $params['login_group_id'] = $request->user()['group_id']; //当前登录人所在角色
        $params['login_dep_id'] = $request->user()['dep_id']; //当前登录人具备的科室
        $params['w_id'] = $request->post('w_id', ""); //当前登录人所能看的科室病区id
        $params['s_id'] = $request->post('s_id', ""); //当前登录人所能看的员工id
        $params['department_review'] = $request->post('department_review', ""); //科室审核
        $params['is_toexamine'] = $request->post('is_toexamine', 0); //是否自动审核人
        return NewUserService::addUser($params);
    }

    /**
     * 添加编辑用户时使用的用户组(角色)下拉数据
     * @param Request $request
     * @return array
     */
    public function groupDropDownList(Request $request)
    {
        $loginGroupId = $request->user()['group_id'];
        $selfGroupInfo = UserGroup::query()->where('id', $loginGroupId)->value('level');
        $query = UserGroup::query()
            ->whereNull('deleted_at')
            ->where('id', '!=', 1);
        //超级管理员，那就将所有的角色都展示出来
        if ($loginGroupId != 1) {
            $ids = UserGroupService::getSelfGroupIds($loginGroupId);
            $Ids = array_column($ids, 'id');
            array_unshift($Ids, $loginGroupId);
            $query = $query->whereIn('id', $Ids);
        }
        $data = $query->get(['name', 'id'])->toArray();
        return ToolsService::returnData(200, $data, '');
    }

    /**
     * 编辑用户
     * @param Request $request
     * @return array
     */
    public function editUser(Request $request)
    {
        $params['id'] = $request->post('id', '');
        if (empty($params['id'])) {
            return ToolsService::returnData(1, '', '参数缺失');
        }
        //工号，web登录账号
        $params['name'] = $request->post("name", "");
        if (empty($params['name'])) {
            return ToolsService::returnData(1, '', '工号不能为空');
        }

        //姓名
        $params['realname'] = $request->post("realname", "");
        if (empty($params['realname'])) {
            return ToolsService::returnData(1, '', '姓名不能为空');
        }

        //科室可多选，这个记得验证一下，是不是当前登录人员所具备的科室
        $params['dep_id'] = $request->post('dep_id', []);
        if (empty($params['dep_id'])) {
            return ToolsService::returnData(1, '', '请选择科室');
        }

        //角色,这个记得验证一下，是不是当前登录人员所具备的科室
        $params['group_id'] = $request->post('group_id', "");
        if (empty($params['group_id'])) {
            return ToolsService::returnData(1, '', '请选择角色');
        }
        //手机号，非必填项
        $params['phone'] = $request->post('phone', "");
        if (!empty($params['phone'])) {
            if (!phoneFormatCheck($params['phone'])) {
                return ToolsService::returnData(1, '', '手机号码格式不正确');
            }
        }
        //备注，非必填项
        $params['desc'] = $request->post('desc', "");

        $params['user_id'] = $request->user()['id']; //当前登录人的ID
        $params['login_group_id'] = $request->user()['group_id']; //当前登录人所在的角色ID
        $params['login_dep_id'] = $request->user()['dep_id']; //当前登录人所能看的科室id
        $params['w_id'] = $request->post('w_id', ""); //当前登录人所能看的科室病区id
        $params['s_id'] = $request->post('s_id', ""); //当前登录人所能看的员工id
        $params['department_review'] = $request->post('department_review', ""); //科室审核
        $params['is_toexamine'] = $request->post('is_toexamine', 0); //是否自动审核人
        return NewUserService::editUser($params);
    }

    /**
     * 删除用户（软删除）
     * @param Request $request
     * @return array
     */
    public function delUser(Request $request)
    {
        $params['id'] = $request->post('id', '');
        if (empty($params['id'])) {
            return ToolsService::returnData(1, '', '缺少必要参数');
        }
        $params['user_id'] = $request->user()['id'];
        $params['login_group_id'] = $request->user()['group_id'];
        return NewUserService::delUser($params);
    }

    /**
     * 锁定或解锁账户使用
     * @param Request $request
     * @return array
     */
    public function lockAndUnlockUser(Request $request)
    {
        $params['id'] = $request->post('id', '');
        if (empty($params['id'])) {
            return ToolsService::returnData(1, '', '缺少必要参数');
        }
        $params['user_id'] = $request->user()['id'];
        $params['login_group_id'] = $request->user()['group_id'];
        return NewUserService::lockAndUnlockUser($params);
    }

    /**
     * 修改密码
     * @param Request $request
     * @return array
     */
    public function editPassword(Request $request)
    {
        $params['id'] = $request->post('id', ''); //要修改的用户ID
        $params['password'] = $request->post('password', ''); //新的密码
        if (empty($params['id']) || empty($params['password'])) {
            return ToolsService::returnData(1, '', '缺少必要参数');
        }
        if (!validatePasswordIsEasy($params['password'])) {
            return ToolsService::returnData(1, '', '密码长度至少8位,必须包含大写字母,小写字母以及数字');
        }
        $params['user_id'] = $request->user()['id']; //当前登录人的ID
        $params['login_group_id'] = $request->user()['group_id'];

        return NewUserService::editPassword($params);
    }

    /**
     * 获取医师信息
     */
    public function getDoctorList()
    {
        $doctorList = Staff::query()->get(['id', 'name'])->toArray();
        return ToolsService::returnData(200, $doctorList, '');
    }
}
