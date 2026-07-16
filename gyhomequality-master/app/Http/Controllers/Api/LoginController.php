<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\Department;
use App\Model\ManLog;
use App\Model\User;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class LoginController extends Controller
{
    /**
     * 获取真实客户端IP地址
     * 优先从HTTP头中获取，适用于代理/负载均衡器场景
     * @param Request $request
     * @return string
     */
    private function getRealIp(Request $request)
    {
        // 优先从 X-Forwarded-For 获取（可能包含多个IP，取第一个）
        $xForwardedFor = $request->header('X-Forwarded-For');
        if (!empty($xForwardedFor)) {
            $ips = explode(',', $xForwardedFor);
            $ip = trim($ips[0]);
            if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                // 如果不是本地回环地址，直接返回
                if ($ip !== '127.0.0.1' && $ip !== '::1') {
                    return $ip;
                }
            }
        }

        // 从 X-Real-IP 获取
        $xRealIp = $request->header('X-Real-IP');
        if (!empty($xRealIp) && filter_var($xRealIp, FILTER_VALIDATE_IP)) {
            if ($xRealIp !== '127.0.0.1' && $xRealIp !== '::1') {
                return $xRealIp;
            }
        }

        // 从 X-Client-IP 获取
        $xClientIp = $request->header('X-Client-IP');
        if (!empty($xClientIp) && filter_var($xClientIp, FILTER_VALIDATE_IP)) {
            if ($xClientIp !== '127.0.0.1' && $xClientIp !== '::1') {
                return $xClientIp;
            }
        }

        // 尝试从 REMOTE_ADDR 获取
        $remoteAddr = $request->server('REMOTE_ADDR');
        if (!empty($remoteAddr) && filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
            if ($remoteAddr !== '127.0.0.1' && $remoteAddr !== '::1') {
                return $remoteAddr;
            }
        }

        // 最后使用 Laravel 的 getClientIp 方法
        $ip = $request->getClientIp();
        if (!empty($ip) && $ip !== '127.0.0.1' && $ip !== '::1') {
            return $ip;
        }

        // 如果所有方法都返回本地IP，至少返回 REMOTE_ADDR（即使是127.0.0.1也比空值好）
        return $remoteAddr ?: ($ip ?: '127.0.0.1');
    }

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
        $department_id = $request->post('department_id');
        if (!$name && !$password) {
            $name = $request->get('name');
            $password = $request->get('password');
            $department_id = $request->get('department_id');
        }
        $user = User::query()
            ->where('name', $name)
            ->whereNull('deleted_at')
            ->first(['id', 'name', 'password', 'group_id', 'status', 'realname','dep_id', 'w_id', 's_id', 'department_review']);
        if (!$user) {
            $code = 4001;
            $data = [];
            $msg = '登录失败，用户名或密码不正确，请重新输入';
        } else {
            $result = $user->toArray();
            $token = md5($result['name'] . $result['password']);
            Session::put($token, $result);
            Session::put($token . '_department_id', $department_id);
            if ($result['password'] == md5($password)) {
                // 密码正确，清除失败次数记录
                Session::forget('login_failed_' . $name);
                $loginIp = $this->getRealIp($request);
                User::updateLogin($result['id'], $token, date("Y-m-d H:i:s"), $loginIp);
                
                // 记录登录日志
                try {
                    ManLog::query()->insert([
                        'name' => $result['name'],
                        'realname' => $result['realname'] ?? '',
                        'loginip' => $loginIp,
                        'content' => '用户登录',
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                } catch (\Exception $e) {
                    // 日志记录失败不影响登录流程
                }
                
                $code = 200;
                $data = ['token' => $token, 'realname' => $result['realname'], 'group_id' => $result['group_id'], "name"=>$result["name"], "id"=>$result["id"]];
                $msg = '';
            } else {
                // 密码错误，记录失败次数
                $failedKey = 'login_failed_' . $name;
                $failedAttempts = Session::get($failedKey, []);
                $currentTime = time();
                
                // 清除一分钟前的失败记录
                $failedAttempts = array_filter($failedAttempts, function($timestamp) use ($currentTime) {
                    return ($currentTime - $timestamp) <= 60;
                });
                
                // 添加当前失败记录
                $failedAttempts[] = $currentTime;
                Session::put($failedKey, $failedAttempts);
                
                // 检查是否达到3次失败
                if (count($failedAttempts) >= 300) {
                    // 锁定账户
                    User::where('id', $result['id'])->update(['status' => 1]);
                    $code = 4001;
                    $data = [];
                    $msg = '登录失败，账户已锁定';
                } else {
                    $code = 4001;
                    $data = [];
                    $msg = '登录失败，用户名或密码不正确，请重新输入';
                }
            }
            if ($result['status'] == 1) {
                $data = [];
                $code = 4001;
                $msg = '登录失败，账户已锁定';
            }
        }
        return ToolsService::returnData($code, $data, $msg);
    }

    public function thirdLogin(Request $request)
    {
        $name = $request->post('name');
        if(!$name){
            return ToolsService::returnData(4001, [], '请输入工号！！');
        }
        $user = User::query()
            ->where('name', $name)
            ->whereNull('deleted_at')
            ->first(['id', 'name', 'password', 'group_id', 'status', 'realname','dep_id', 'w_id', 's_id', 'department_review']);
        if (!$user || $user->status == 1) return ToolsService::returnData(4001, [], '用户不存在或已停用');
        $result = $user->toArray();
        if($user->realname !== '超级管理员') {
            $depId = json_decode($result['dep_id'], true);
            if (empty($depId)) return ToolsService::returnData(4001, [], '权限不足,请联系管理员处理!!');
            $dep = Department::query()->select('type_id')->whereIn("dep_id", $depId)->groupBy('type_id')->get()->toArray();
            $dep_array = data_get($dep, "*.type_id", []);
            if (!in_array(2, $dep_array) && !in_array(4, $dep_array)) {
                return ToolsService::returnData(4001, [], '权限不足,请联系管理员处理~');
            }
        }
        $token = md5($result['name'] . $result['password']);
        Session::put($token,$user);
        $loginIp = $this->getRealIp($request);
        User::updateLogin($user['id'], $token, date("Y-m-d H:i:s"), $loginIp);
        
        // 记录登录日志
        try {
            ManLog::query()->insert([
                'name' => $result['name'],
                'realname' => $result['realname'] ?? '',
                'loginip' => $loginIp,
                'content' => '用户登录',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            // 日志记录失败不影响登录流程
        }
        
        return ToolsService::returnData(200, [
            'token' => $token,
            'realname' => $result['realname'],
            'group_id' => $result['group_id'],
            "name"=>$result["name"],
            "id"=>$result["id"]], '');
    }

    /**
     * 退出登录
     * @param Request $request
     * @return array
     */
    public function logout(Request $request)
    {
        $token = $request->header('token');
        if (empty($token)) {
            return ToolsService::returnData(200, [], '');
        }

        Session::put($token,[]);

        return ToolsService::returnData(200, [], '');
    }

    /**
     * 忘记密码 - 第一步：验证身份
     * @group Index
     * @bodyParam name string required 用户名
     * @bodyParam realname string required 真实姓名
     * @param Request $request
     * @response {
     *  "code":200,
     *  "msg":"验证成功，可以重置密码",
     *  "data":[]
     * }
     */
    public function verifyForReset(Request $request)
    {
        $name = $request->post('name');
        $realname = $request->post('realname');
        
        if (!$name && !$realname) {
            $name = $request->get('name');
            $realname = $request->get('realname');
        }

        if (empty($name) || empty($realname)) {
            return ToolsService::returnData(4001, [], '用户名和真实姓名不能为空');
        }

        $user = User::query()
            ->where('name', $name)
            ->where('realname', $realname)
            ->whereNull('deleted_at')
            ->first(['id', 'name', 'realname', 'status']);

        if (!$user) {
            return ToolsService::returnData(4001, [], '用户名或真实姓名不匹配');
        }

        if ($user->status == 1) {
            return ToolsService::returnData(4001, [], '账户已锁定，无法重置密码');
        }

        // 生成验证令牌，存储在 Session 中，有效期 10 分钟
        $verifyToken = md5($name . $realname . time());
        Session::put('reset_password_verify_' . $name, [
            'token' => $verifyToken,
            'name' => $name,
            'expires_at' => time() + 600 // 10分钟有效期
        ]);

        return ToolsService::returnData(200, ['verify_token' => $verifyToken], '验证成功，可以重置密码');
    }

    /**
     * 忘记密码 - 第二步：重置密码
     * @group Index
     * @bodyParam name string required 用户名
     * @bodyParam password string required 新密码
     * @bodyParam verify_token string optional 验证令牌（如果第一步验证通过会返回）
     * @param Request $request
     * @response {
     *  "code":200,
     *  "msg":"密码重置成功",
     *  "data":[]
     * }
     */
    public function resetPassword(Request $request)
    {
        $name = $request->post('name');
        $password = $request->post('password');
        $verifyToken = $request->post('verify_token');

        if (!$name && !$password) {
            $name = $request->get('name');
            $password = $request->get('password');
            $verifyToken = $request->get('verify_token');
        }

        if (empty($name) || empty($password)) {
            return ToolsService::returnData(4001, [], '用户名和新密码不能为空');
        }

        // 检查第一步验证是否通过
        $verifyKey = 'reset_password_verify_' . $name;
        $verifyData = Session::get($verifyKey);

        if (!$verifyData) {
            return ToolsService::returnData(4001, [], '请先完成身份验证');
        }

        // 检查验证令牌是否匹配
        if (!empty($verifyToken) && $verifyData['token'] !== $verifyToken) {
            return ToolsService::returnData(4001, [], '验证令牌不匹配');
        }

        // 检查验证是否过期
        if (isset($verifyData['expires_at']) && time() > $verifyData['expires_at']) {
            Session::forget($verifyKey);
            return ToolsService::returnData(4001, [], '验证已过期，请重新验证');
        }

        // 验证用户名是否匹配
        if ($verifyData['name'] !== $name) {
            return ToolsService::returnData(4001, [], '用户名不匹配');
        }

        // 查找用户
        $user = User::query()
            ->where('name', $name)
            ->whereNull('deleted_at')
            ->first();

        if (!$user) {
            return ToolsService::returnData(4001, [], '用户不存在');
        }

        if ($user->status == 1) {
            return ToolsService::returnData(4001, [], '账户已锁定，无法重置密码');
        }

        // 更新密码（使用 md5 加密，与登录方法保持一致）
        $newPassword = md5($password);
        $user->password = $newPassword;
        $user->save();

        // 清除验证令牌
        Session::forget($verifyKey);

        return ToolsService::returnData(200, [], '密码重置成功');
    }


}
