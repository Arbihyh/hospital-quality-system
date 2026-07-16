<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\SearchLabel;
use App\Services\CaseService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use App\Model\CaseRule;
use Illuminate\Support\Facades\Session;

class LabelController extends Controller
{
    /**
     * @param Request $request
     * @param CaseService $caseService
     * @return array
     */
    public function saveLabel(Request $request)
    {
        $params = $request->post();

        $token = $request->header('token', '');
        if ($token) {
            $userInfo = Session::get($token);
            SearchLabel::query()->insert([
                'user_id' => $userInfo['id'],
                'params' => json_encode($params, 256),
                'created_at' => time(),
            ]);
        } else {
            $code = 4001;
            $data = [];
            $msg = '用户未登录，请登录后重试';
        }
        return ToolsService::returnData($code ?? 200, $data ?? [], $msg ?? "保存成功");
    }

    /**
     * @param Request $request
     * @param CaseService $caseService
     * @return array
     */
    public function getLabel(Request $request)
    {
        $token = $request->header('token', '');
        $code = 200;
        if ($token) {
            $userInfo = Session::get($token);

            $searchLabel = SearchLabel::query()->where('user_id', '=', $userInfo['id'])->get()->toArray();
            if($searchLabel){
                foreach ($searchLabel as $k=>&$s) {
                    $s['params'] = json_decode($s['params']);
                }
            }
            $data = $searchLabel;
        } else {
            $code = 4001;
            $data = [];
            $msg = '用户未登录，请登录后重试';
        }
        return ToolsService::returnData($code, $data ?? [], $msg ?? "");
    }

}
