<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\Department;
use App\Model\ProblemFeedback;
use App\Services\ToolsService;
use Illuminate\Http\Request;

class ProblemFeedbackController extends Controller
{
    /**
     * @param Request $request
     * @return array
     */
    public function addFeedback(Request $request)
    {
        $typeId = $request->post('type_id','');    // 反馈类型ID
        $userName = $request->post('user_name','');// 反馈人
        $depId = $request->post('dep_id','');      // 科室ID
        $content = $request->post('content','');   // 反馈内容

        // 必填参数验证
        if (!$typeId || !$content) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }

        try {
            // 要写入的数据
            $insertData = [
                'type_id' => $typeId,
                'user_name' => $userName,
                'dep_id' => $depId,
                'content' => $content,
            ];
            // 数据写入
            $res = ProblemFeedback::query()->insert($insertData);
            $code = $res ? 200 : 4001;
        } catch (\Exception $e) {
            // 写入报错
            $code = 4001;
            $msg = $e->getMessage();
        }

        return ToolsService::returnData($code, $res, $msg ?? '');
    }

    public function getDepartmentList(Request $request)
    {
        $depList = Department::query()->where('type_id', '=', 2)->get(['dep_id','dep_name as name'])->toArray();

        return ToolsService::returnData(200, $depList, $msg ?? '');
    }

}
