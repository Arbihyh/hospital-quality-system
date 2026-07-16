<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\User;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class LoginController extends Controller
{
    /**
     * login
     * @group Index
     * @bodyParam name string required 用户名
     * @bodyParam password string required 密码
     * @param Request $request
     * @response {
     *  "code":200,
     *  "msg":"",
     *  "data":{
     *      "token": "jkjhdjkshkjsj"
     *  },
     *  "time":123787842
     * }
     */
    public function login(Request $request)
    {
        $name = $request->post('name');
        $password = $request->post('password');
        $user = User::query()
            ->where('name', $name)
            ->first(['id', 'name', 'password', 'group_id', 'status', 'dep_id']);
        if (!$user) {
            $code = 4001;
            $data = [];
            $msg = '用户不存在';
        } else {
            $result = $user->toArray();
            $token = md5($result['name'] . $result['password']);
            Session::put($token,$result);
            if ($user['password'] == $password) {
                User::updateLogin($user['id'], $token, date("Y-m-d H:i:s"), request()->getClientIp());
                $code = 200;
                $data = ['token' => $token];
                $msg = '';
            } else {
                $code = 4001;
                $data = [];
                $msg = '密码错误';
            }
            if ($result['status'] == 1) {
                $data = [];
                $code = 4001;
                $msg = '用户已被停用';
            }
        }
        return ToolsService::returnData($code, $data, $msg);
    }
}
