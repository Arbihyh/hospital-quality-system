<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\ManLog;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

/**
 * 运营日志接口
 */
class ManLogController extends Controller
{
    /**
     * 日志列表
     * 支持筛选：账号、姓名、登录ip、操作内容、起止时间；并分页返回
     * @param Request $request
     * @return array
     */
    public function list(Request $request)
    {
        // 兼容多种请求方式：GET、POST、JSON（input方法会自动处理）
        // 前端参数嵌套在params对象中，需要从params中获取
        $params = $request->input('params', []);
        
        $name = trim((string)($params['name'] ?? $request->input('name', '')));
        $realname = trim((string)($params['realname'] ?? $request->input('realname', '')));
        $loginip = trim((string)($params['loginip'] ?? $request->input('loginip', '')));
        $content = trim((string)($params['content'] ?? $request->input('content', '')));
        $startTime = trim((string)($params['start_time'] ?? $request->input('start_time', '')));
        $endTime = trim((string)($params['end_time'] ?? $request->input('end_time', '')));
        $page = (int)($params['page'] ?? $request->input('page', 1));
        $pageSize = (int)($params['page_size'] ?? $request->input('page_size', 10));

        // 调试日志：记录接收到的参数
        Log::info('ManLog list 接收到的参数', [
            'params_object' => $params,
            'parsed_name' => $name,
            'parsed_realname' => $realname,
            'parsed_loginip' => $loginip,
            'parsed_content' => $content,
            'parsed_start_time' => $startTime,
            'parsed_end_time' => $endTime,
            'parsed_page' => $page,
            'parsed_page_size' => $pageSize,
            'request_method' => $request->method(),
        ]);

        $page = $page > 0 ? $page : 1;
        $pageSize = $pageSize > 0 ? $pageSize : 10;

        try {
            $query = ManLog::query();

            if ($name !== '') {
                $query->where('name', 'like', '%' . $name . '%');
            }
            if ($realname !== '') {
                $query->where('realname', 'like', '%' . $realname . '%');
            }
            if ($loginip !== '') {
                $query->where('loginip', 'like', '%' . $loginip . '%');
            }
            if ($content !== '') {
                $query->where('content', 'like', '%' . $content . '%');
            }
            // created_at 是 varchar，使用字符串比较，要求传入格式 YYYY-MM-DD HH:mm:ss
            if ($startTime !== '' && $endTime !== '') {
                $query->whereBetween('created_at', [$startTime, $endTime]);
            } elseif ($startTime !== '') {
                $query->where('created_at', '>=', $startTime);
            } elseif ($endTime !== '') {
                $query->where('created_at', '<=', $endTime);
            }

            // 记录查询SQL用于调试
            $sql = $query->toSql();
            $bindings = $query->getBindings();
            Log::info('ManLog list 查询SQL', [
                'sql' => $sql,
                'bindings' => $bindings,
            ]);

            $count = $query->count();

            $pageStart = ($page - 1) * $pageSize;
            $list = $query->orderBy('created_at', 'desc')
                ->offset($pageStart)
                ->limit($pageSize)
                ->get(['id', 'name', 'realname', 'loginip', 'content', 'created_at'])
                ->toArray();

            foreach ($list as $idx => &$row) {
                $row['serial_number'] = $pageStart + $idx + 1;
            }

            return ToolsService::returnData(200, [
                'count' => $count,
                'list' => $list
            ], '');
        } catch (\Exception $e) {
            return ToolsService::returnData(4001, [], $e->getMessage());
        }
    }

    /**
     * 新增一条运营日志
     * 从当前登录用户获取账号和姓名，只接收操作内容和登录IP
     * @param Request $request
     * @return array
     */
    public function add(Request $request)
    {
        $content = trim($request->post('content', ''));
        $loginip = trim($request->post('loginip', ''));

        if ($content === '') {
            return ToolsService::returnData(4001, [], '操作内容不能为空');
        }

        // 获取当前登录用户信息（从 Session 获取）
        $token = $request->header('token');
        if (empty($token)) {
            return ToolsService::returnData(4001, [], '未登录，请先登录');
        }

        $userInfo = Session::get($token);
        if (empty($userInfo)) {
            return ToolsService::returnData(4001, [], '登录已过期，请重新登录');
        }

        // 从用户信息中获取账号和姓名
        $name = $userInfo['name'] ?? '';
        $realname = $userInfo['realname'] ?? '';

        if (empty($name)) {
            return ToolsService::returnData(4001, [], '无法获取用户账号信息');
        }

        // 如果没有传入登录IP，使用请求的IP地址
        if (empty($loginip)) {
            $loginip = $request->ip();
        }

        try {
            ManLog::query()->insert([
                'name' => $name,
                'realname' => $realname,
                'loginip' => $loginip,
                'content' => $content,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            return ToolsService::returnData(200, true, '添加成功');
        } catch (\Exception $e) {
            return ToolsService::returnData(4001, [], $e->getMessage());
        }
    }
}


