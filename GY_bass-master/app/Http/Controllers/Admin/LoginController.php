<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Model\Admin;
use App\Services\AdminService;
use App\Services\ToolsService;

class LoginController extends Controller
{
    public function login(Request $request){
        $account = $request->post("account");
        if (empty($account)) {
            return ToolsService::returnAdmin(400, false,"账号不能为空");
        }
        $pwd = $request->post("password");
        if (empty($pwd)) {
            return ToolsService::returnAdmin(400, false,"密码不能为空");
        }
        $data = Admin::findWhereAccount($account);
        if (!$data) {
            return ToolsService::returnAdmin(400, false,"用户不存在");
        }
        if ($data['password'] != $pwd) {
            return ToolsService::returnAdmin(400, false,"账号或密码不正确");
        }
        $data = AdminService::login($data);
        if(!$data){
            return ToolsService::returnAdmin(400, false,"登陆失败");
        }
        return ToolsService::returnAdmin(0,$data);
    }
}
