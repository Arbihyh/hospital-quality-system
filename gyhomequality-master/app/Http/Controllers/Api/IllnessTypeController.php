<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use App\Services\IllnessTypeService;

class IllnessTypeController extends Controller
{
    /**
     * @param Request $request
     * @param IllnessTypeService $illnessTypeService
     * @return array
     * 单病种质量
     */
    public function getList(Request $request, IllnessTypeService $illnessTypeService){
        $type = (int)$request->post('type'); // 病种类型下标
        $where['data_type'] = $type;
        $year = $request->post('year');
        $where['year'] = $year ?: date('Y');
        try{
            $data = $illnessTypeService->getList($where);
            $code = 200;
        }catch (\Exception $e){
            $code = 4001;
            $msg = $e->getMessage();
        }

        return ToolsService::returnData($code, ($data ?? []), $msg ?? '');
    }

}
