<?php

namespace App\Http\Middleware;

use App\Services\AdminService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WriteAdminLog
{
    protected static $paths = [
        'admin/login' => '管理员登陆',
        'admin/admin/addAdmin' => '添加管理员',
        'admin/admin/editAdmin' => '修改管理员',
        'admin/admin/delAdmin' => '删除管理员',
        'admin/admin/addAdminGroup' => '添加管理员部门',
        'admin/admin/editAdminGroup' => '修改管理员部门',
        'admin/admin/delAdminGroup' => '删除管理员部门',
        'admin/user/addUser' => '添加用户',
        'admin/user/editUser' => '修改用户',
        'admin/user/delUser' => '删除用户',
        'admin/user/addUserGroup' => '添加用户部门',
        'admin/user/editUserGroup' => '修改用户部门',
        'admin/user/delUserGroup' => '删除用户部门',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $path = $request->path();
        $userData = $request->user();
        $queryParams = $request->all();
        if(!$userData) {
            $response = $next($request);
            if($response instanceof JsonResponse) {
                $respData = $response->getData(true);
                if(!empty($respData['p'])) {
                    $userData = $respData['p'];
                }
            }
        }
        if (!$request->isMethod('get') && array_key_exists($path, self::$paths)
            && !empty($userData['name']) && !empty($userData['id'])) {
            $data = [
                'userId' => $userData['id'],
                'name' => $userData['name'],
                'path'=> $path,
                'method'=> $request->method(),
                'queryParams' => $queryParams,
                'ip' => $request->ip(),
                'title' =>self::$paths[$path],
                'userAgent' => $request->userAgent()
            ];
            AdminService::writeAdminLog($data);
        }
        return $next($request);
    }
}
