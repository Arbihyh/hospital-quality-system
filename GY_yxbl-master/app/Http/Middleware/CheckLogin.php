<?php

namespace App\Http\Middleware;

use App\Services\ToolsService;
use App\Services\UserService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class CheckLogin
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
        $token = $request->header('token');
        if (empty($token)){
            return ToolsService::returnData(-1,[],'请先登录');
        }else{
            $user = Session::get($token);
            if (empty($user)){
                return ToolsService::returnData(-1,[],'请先登录');
            }
//            $therePermission = UserService::therePermission($user["group_id"], $request->path());
//            if (!$therePermission) {
//                return ToolsService::returnData(1004, '没有访问权限');
//            }
        }
        $request->setUserResolver(function () use($user){
            return $user;
        });
        return $next($request);
    }
}
