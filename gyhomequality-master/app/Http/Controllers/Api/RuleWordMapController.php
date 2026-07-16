<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\RuleWordMap;
use App\Model\User;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class RuleWordMapController extends Controller
{
    /**
     * @param Request $request
     * @return array
     * 添加规则信息
     */
    public function addWordMap(Request $request)
    {
        $name = $request->post('name');
        $keywords = $request->post('keywords');
        $bzmc = $request->post('BZMC') ?? null;
        if (!$name || !$keywords) {
            return ToolsService::returnData(4001, [], '参数不能为空');
        }

        // 获取当前登录用户信息（从 Session 获取）
        $token = $request->header('token');
        $gxr = '';
        if (!empty($token)) {
            $userInfo = Session::get($token);
            if ($userInfo) {
                // 优先使用 realname，如果没有则使用 name
                $gxr = !empty($userInfo['realname']) ? $userInfo['realname'] : ($userInfo['name'] ?? '');
            }
        }

        // 处理 keywords，统一转换为逗号分隔的字符串
        if (is_array($keywords)) {
            // 如果是数组，转换为逗号分隔的字符串
            $keywordsStr = implode(',', $keywords);
        } else if (is_string($keywords)) {
            // 如果是字符串，尝试解析 JSON
            $decoded = json_decode($keywords, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // 如果是 JSON 数组，转换为逗号分隔的字符串
                $keywordsStr = implode(',', $decoded);
            } else {
                // 如果不是 JSON，保持原样（已经是逗号分隔的字符串）
                $keywordsStr = $keywords;
            }
        } else {
            // 其他情况，转换为字符串
            $keywordsStr = (string)$keywords;
        }

        $msg = '';
        try {
            RuleWordMap::query()->insert([
                'name' => $name,
                'keyword' => $keywordsStr,
                'GXR' => $gxr,
                'BZMC' => $bzmc,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $code = 200;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }
        return ToolsService::returnData($code, $code == 200 ? true : [], $msg);
    }

    /**
     * @param Request $request
     * @return array
     * 修改字段
     */
    public function editWordMap(Request $request)
    {
        $id = intval($request->post('id'));
        $name = $request->post('name');
        $keywords = $request->post('keywords');
        $BZMC = $request->post('BZMC') ?? null;

        if (!$name || !$keywords) {
            return ToolsService::returnData(4001, [], '参数不能为空');
        }

        // 获取当前登录用户信息（从 Session 获取）
        $token = $request->header('token');
        $gxr = '';
        if (!empty($token)) {
            $userInfo = Session::get($token);
            if ($userInfo) {
                // 优先使用 realname，如果没有则使用 name
                $gxr = !empty($userInfo['realname']) ? $userInfo['realname'] : ($userInfo['name'] ?? '');
            }
        }

        // 处理 keywords，统一转换为逗号分隔的字符串
        if (is_array($keywords)) {
            // 如果是数组，转换为逗号分隔的字符串
            $keywordsStr = implode(',', $keywords);
        } else if (is_string($keywords)) {
            // 如果是字符串，尝试解析 JSON
            $decoded = json_decode($keywords, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // 如果是 JSON 数组，转换为逗号分隔的字符串
                $keywordsStr = implode(',', $decoded);
            } else {
                // 如果不是 JSON，保持原样（已经是逗号分隔的字符串）
                $keywordsStr = $keywords;
            }
        } else {
            // 其他情况，转换为字符串
            $keywordsStr = (string)$keywords;
        }

        $msg = '';
        try {
            RuleWordMap::query()->where(['id' => $id])->update([
                'name' => $name,
                'keyword' => $keywordsStr,
                'BZMC' => $BZMC,
                'GXR' => $gxr,
            ]);
            $code = 200;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }
        return ToolsService::returnData($code, $code == 200 ? true : [], $msg);
    }

    /**
     * @param Request $request
     * @return array
     * 获取规则列表
     */
    public function getWordMap(Request $request)
    {
        $name = $request->post('name', '');
        $keyword = $request->post('keyword', '');
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);
        $id = $request->post('id', '');

        try {
            $obj = RuleWordMap::query();
            if (!empty($id)) {
                $obj = $obj->where('id', '=', $id);
            }
            if (!empty($name)) {
                $obj = $obj->where('name', 'like', '%' . $name . '%');
            }
            if (!empty($keyword)) {
                $obj = $obj->where('keyword', 'like', '%' . $keyword . '%');
            }
            $count = $obj->count();

            $pageStart = ($page - 1) * $pageSize;
            $data = $obj->offset($pageStart)->LIMIT($pageSize)->get()->toArray();

            foreach ($data as &$v) {
                if (!empty($v['keyword'])) {
                    $v['keywords'] = explode(',', $v['keyword']);
                } else {
                    $v['keywords'] = [];
                }
            }

            $code = 200;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }

        return ToolsService::returnData($code, ['count' => $count, 'list' => $data], $msg ?? '');
    }

    /**
     * @param Request $request
     * @return array
     * 获取所有规则列表
     */
    public function getAllWordMap(Request $request)
    {
        $name = $request->post('name', '');
        try {
            $obj = RuleWordMap::query();
            if (!empty($name)) {
                $obj = $obj->where('name', 'like', '%' . $name . '%');
            }
            $data = $obj->get()->toArray();

            foreach ($data as &$v) {
                if (!empty($v['keyword'])) {
                    $v['keywords'] = explode(',', $v['keyword']);
                } else {
                    $v['keywords'] = [];
                }
            }

            $code = 200;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }

        return ToolsService::returnData($code, $data, $msg ?? '');
    }

    /**
     * @param Request $request
     * @return array
     * 删除字段
     */
    public function delWordMap(Request $request)
    {
        $id = intval($request->post('id'));
        if (!$id) {
            return ToolsService::returnData(4001, [], '参数不能为空');
        }

        $msg = '';
        try {
            RuleWordMap::query()->where(['id' => $id])->delete();
            $code = 200;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }
        return ToolsService::returnData($code, $code == 200 ? true : [], $msg);
    }

    /**
     * @param Request $request
     * @return array
     * 获取版本记录列表
     */
    public function getVersionRecord(Request $request)
    {
        // 前端参数嵌套在params对象中，需要从params中获取
        $params = $request->input('params', []);
        
        $page = intval($params['page'] ?? $request->input('page', 1));
        $pageSize = intval($params['page_size'] ?? $request->input('page_size', 20));
        $name = trim((string)($params['name'] ?? $request->input('name', '')));
        $keyword = trim((string)($params['keyword'] ?? $request->input('keyword', '')));

        try {
            $obj = RuleWordMap::query()->where('is_bb', '=', '1');
            
            if (!empty($name)) {
                $obj = $obj->where('name', 'like', '%' . $name . '%');
            }
            if (!empty($keyword)) {
                $obj = $obj->where('keyword', 'like', '%' . $keyword . '%');
            }
            
            $count = $obj->count();
            $pageStart = ($page - 1) * $pageSize;
            $data = $obj->orderBy('updated_at', 'desc')
                ->offset($pageStart)
                ->limit($pageSize)
                ->get()
                ->toArray();

            foreach ($data as &$v) {
                if (!empty($v['keyword'])) {
                    $v['keywords'] = explode(',', $v['keyword']);
                } else {
                    $v['keywords'] = [];
                }
            }

            $code = 200;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage();
        }

        return ToolsService::returnData($code, ['count' => $count, 'list' => $data], $msg ?? '');
    }
}

