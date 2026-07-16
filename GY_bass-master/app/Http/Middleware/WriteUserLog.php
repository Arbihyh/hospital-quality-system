<?php

namespace App\Http\Middleware;

use App\Model\AdminLog;
use App\Model\Staff;
use App\Model\User;
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

    protected static $searchPaths = [
        'bass/normalSearch' => '病案搜索',
        'bass/search' => '病案搜索',
        'bass/yz/serach' => '医嘱搜索',
        'bass/bl/serach' => '专业搜索',
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

        if (empty($userData)) {
            $userInfo = Session::get($request->header('token'));
            if (!empty($userInfo->id)) {
                $userData = [
                    'id'=>$userInfo->id ?? 0,
                    'name'=>$userInfo->name ?? '',
                    'dep_id'=>$userInfo->dep_id ?? 0
                ];
            }
        }

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
                'userId' => $userData['id'] ?? '',
                'name' => $userData['name'] ?? '',
                'path'=> $path,
                'method'=> $request->method(),
                'queryParams' => $queryParams,
                'ip' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $request->ip(),
                'title' =>self::$paths[$path],
                'userAgent' => $request->userAgent()
            ];
            UserService::writeUserLog($data);
        } else if (array_key_exists($path, self::$searchPaths)) {
            $userCode = $request->post('code');
            $is_code = 0;
            if (!empty($userCode)) {
                $is_code = 1;
                $staffInfo = Staff::query()->where('YGBH','=',$userCode)->first();
                if (empty($staffInfo)) {
                    $staffInfo = Staff::query()->where('code','=',$userCode)->first();
                }
                if ($staffInfo) {
                    $userData = ['id'=>$staffInfo->code,'name'=>$staffInfo->name,'dep_id'=>$staffInfo->ksdm];
                } else {
                    $userData = ['id'=>$userCode,'name'=>$userCode,'dep_id'=>''];
                }
            }

            $pathType = [
                'bass/normalSearch' => 1,
                'bass/search' => 1,
                'bass/yz/serach' => 3,
                'bass/bl/serach' => 4,
            ];
            $type = $pathType[$path];

            unset($queryParams['page'],$queryParams['page_size'],$queryParams['limit'],$queryParams['is_tm'],$queryParams['code']);
            $queryParams = array_filter($queryParams);
            if (empty($queryParams['ageStart']) && empty($queryParams['ageEnd'])) {
                unset($queryParams['ageType']);
            }
            if (!empty($queryParams['field'])) {
                foreach ($queryParams['field'] as $key => $value) {
                    if (empty($value['value'])) {
                        unset($queryParams['field'][$key]);
                    }
                }
                if (empty($queryParams['field'])) {
                    unset($queryParams['field']);
                }
            }

            if ($queryParams) {
                foreach ($queryParams as $k => $v) {
                    if (in_array($k,['AAC01_START','AAC01_END']) && !empty($v)) {
                        $queryParams[$k] = date("Y-m-d", ($v/1000));
                    }
                }
                if (!empty($userData['id']) && !empty($userData['name'])) {
                    $data = [
                        'userId' => $userData['id'] ?? '',
                        'name' => $userData['name'] ?? '',
                        'path'=> $path,
                        'method'=> $request->method(),
                        'queryParams' => $queryParams,
                        'ip' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $request->ip(),
                        'title' =>self::$searchPaths[$path],
                        'type' => $type,
                        'userAgent' => $request->userAgent(),
                        'is_code' => $is_code,
                    ];
                    UserService::writeSearchUserLog($data);
                }
            }
        }

        return $next($request);
    }


}
