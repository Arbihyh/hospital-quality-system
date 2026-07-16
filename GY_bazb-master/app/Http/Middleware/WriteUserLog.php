<?php

namespace App\Http\Middleware;

use App\Model\AdminLog;
use App\Model\UserLog;
use App\Services\UserService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\JsonResponse;

class WriteUserLog
{
    protected static $paths = [
        'api/login' => '用户登陆',
        'api/gtExport' => '导出国考反馈信息',
        'api/wtExport' => '导出卫统反馈信息',
        'api/excel_error' => '缺陷问题-导出excel'
    ];

    protected static $replaceKeys = ['token', 'pwd', 'password'];
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
            $token = '';
            if($response instanceof \Illuminate\Http\JsonResponse) {
                $respData = $response->getData(true);
                if(!empty($respData['data'])) {
                    $data = $respData['data'];
                    $token = isset($data['token']) ? $data['token'] : '';
                }
            }
            $userData = Session::get($token);
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
            UserService::writeUserLog($data);
        }
        return $next($request);
    }
}
