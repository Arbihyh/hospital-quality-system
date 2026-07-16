<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use App\Model\MainOperation;
use App\Services\RadioService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use function Symfony\Component\VarDumper\Dumper\esc;

class RadioController extends Controller
{
    /**
     * @param Request $request
     * @param RadioService $radioService
     * @return array
     * 绩效考核列表
     */
    public function getList(Request $request, RadioService $radioService)
    {

        $startTime = $request->post('start_time');
        $endTime = $request->post('end_time');
        $year = $request->post('year');
        $where['start_time'] = $startTime ? mbDateToTime($startTime) : '';
        $where['end_time'] = $endTime ? mbDateToTime($endTime) : '';
        if ($year) {
            $where['year'] = $year ?: '';
        }

        $type = (int)$request->post('type');
        try {
            $radio = $radioService->getList($where, $type);
            $code = 200;
        } catch (\Exception $e) {
            $code = 4001;
            Log::error("绩效考核列表数据获取失败", ["msg" => $e->getMessage()]);
            $msg = $e->getMessage();
        }

        return ToolsService::returnData($code, $radio ?? [], $msg ?? '');
    }

    /**
     * @param Request $request
     * @param RadioService $radioService
     * @return array
     * 指标列表
     */
    public function getZbList(Request $request, RadioService $radioService)
    {
        $time = $request->post('time', '');
        if (!$time) {
            return ToolsService::returnData(4001, '时间参数不能为空', $msg ?? '');
        }
        $where['time'] = $time;
        $id = $request->post('id', 0);
        if (!$id) {
            return ToolsService::returnData(4001, '菜单ID不能为空', $msg ?? '');
        }
        $where['id'] = $id;
        $dataType = $request->post('data_type', 0);
        if (!in_array($dataType, [0, 1])) {
            return ToolsService::returnData(4001, '数据类型不能为空', $msg ?? '');
        }
        $where['data_type'] = $dataType ?? 0;
        $where['page'] = $request->post('page', 1);
        $where['page_size'] = $request->post('page_size', 20);

        $isError = $request->post('is_error');
        $where['is_error'] = $isError ?? 200;

        try {
            $radio = $radioService->getZbList($where);
            $code = 200;
        } catch (\Exception $e) {
            $code = 4001;
            Log::error("绩效考核列表数据获取失败", ["msg" => $e->getMessage()]);
            $msg = $e->getMessage();
        }

        return ToolsService::returnData($code, $radio ?? [], $msg ?? '');
    }

}
