<?php

namespace App\Http\Middleware;

use App\Services\AdminService;
use App\Services\ToolsService;
use Closure;
use \Illuminate\Http\Request;

class AdminRbac
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->header("token");
        if (empty($token)) {
            return ToolsService::returnAdmin(1004, '', '无效token');
        }
        $userData = AdminService::tokenGetUser($token);
        if (!$userData) {
            return ToolsService::returnAdmin(1004, '', '无效token');
        }
        $therePermission = AdminService::therePermission($userData["group_id"], $request->path());
        if (!$therePermission) {
            return ToolsService::returnAdmin(1, '', '没有此接口访问权限');
        }
        $request->setUserResolver(function () use ($userData){
            return $userData;
        });
        return $next($request);
    }
}
