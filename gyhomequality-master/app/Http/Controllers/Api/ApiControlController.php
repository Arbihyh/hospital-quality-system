<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\PatientInfo;
use App\Model\ZY_BRRY;
use App\Services\ToolsService;
use Illuminate\Http\Request;
/**
 * 质控数据对外接口
 */
class ApiControlController extends Controller
{
    /**
     * @return void
     * 获取质控数据审核状态
     */
    public function getDataExamine(Request $request)
    {
        /**
         * 接收住院号信息
         */
        $ZYH = $request->post('ZYH', '');

        if (empty($ZYH)) {
            return ToolsService::returnData(1, '', '住院号不能为空');
        }

        /**
         * 查询质控状态
         */
        $reviewStatus = ZY_BRRY::query()
            ->where('ZYH', '=', $ZYH)
            ->value('review_status');

        return ToolsService::returnData(200, ['review_status' => $reviewStatus], '获取成功');
    }
}
