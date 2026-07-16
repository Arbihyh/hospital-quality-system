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
        return $next($request);
    }
}
