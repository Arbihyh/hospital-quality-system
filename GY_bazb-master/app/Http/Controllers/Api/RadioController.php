<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\Pszb;
use App\Services\CsvService;
use App\Services\TargetService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use App\Services\RadioService;
use App\Services\RadioServiceV2;
use Illuminate\Support\Facades\Log;

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
        $where['request_source'] = $request->post('request_source', '');
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
     * 绩效考核列表
     */
    public function getListV2(Request $request)
    {

        $startTime = $request->post('start_time');
        $endTime = $request->post('end_time');
        $year = $request->post('year');
        $where['start_time'] = $startTime ? mbDateToTime($startTime) : '';
        $where['end_time'] = $endTime ? mbDateToTime($endTime) : '';
        $where['request_source'] = $request->post('request_source', '');
        if ($year) {
            $where['year'] = $year ?: '';
        }

        $type = (int)$request->post('type');
        try {
            $radioService = new RadioServiceV2();
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
        $where['AAA28'] = $request->post('AAA28', '');
        $where['AAC11N'] = $request->post('AAC11N', '');
        $where['order'] = $request->post('order', '');
        $where['order_sort'] = $request->post('order_sort', '');
        $isError = $request->post('is_error');
        $where['is_error'] = $isError ?? 200;
        $where['year'] = $request->post('year', 2022);
        $where['start_time'] = $request->post('start_time', '');
        $where['end_time'] = $request->post('end_time', '');

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

    /**
     * @param Request $request
     * @param RadioService $radioService
     * @return array
     * 指标列表
     */
    public function getZbListV2(Request $request, RadioServiceV2 $radioService)
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
        $where['AAA28'] = $request->post('AAA28', '');
        $where['AAC11N'] = $request->post('AAC11N', '');
        $where['order'] = $request->post('order', '');
        $where['order_sort'] = $request->post('order_sort', '');
        $isError = $request->post('is_error');
        $where['is_error'] = $isError ?? 200;
        $where['year'] = $request->post('year', 2022);
        $where['start_time'] = $request->post('start_time', '');
        $where['end_time'] = $request->post('end_time', '');

        try {
            $radioService = new RadioServiceV2();
            $radio = $radioService->getZbList($where);
            $code = 200;
        } catch (\Exception $e) {
            $code = 4001;
            Log::error("绩效考核列表数据获取失败", ["msg" => $e->getMessage()]);
            $msg = $e->getMessage();
        }

        return ToolsService::returnData($code, $radio ?? [], $msg ?? '');
    }

    public function addZb(Request $request)
    {
//        $year = $request->post('year', '');
//        $month = $request->post('month', '');
//        $flag = $request->post('flag', '');
//        $type = $request->post('type', '');
//        $num = $request->post('num', '');

        $saveData = $request->post('save_data', []);
        if (empty($saveData)) {
            return ToolsService::returnData(4001, '参数错误', $msg ?? '');
        }

//        if (!$year || !$month || !$flag || !$type || $num==='') {
//            return ToolsService::returnData(4001, '参数错误', $msg ?? '');
//        }

        try {
            $radioService = new RadioService();
            $radio = $radioService->addZbData($saveData);
            $code = 200;
        } catch (\Exception $e) {
            $code = 4001;
            Log::error("指标添加失败", ["msg" => $e->getMessage()]);
            $msg = $e->getMessage();
        }

        return ToolsService::returnData($code, $radio ?? [], $msg ?? '');
    }

    /**
     * 科室排名统计
     * @param Request $request
     * @return array
     */
    public function depStatistics(Request $request)
    {
        $type = $request->post('type', '');
        $where['year'] = $request->post('year', '');
        $where['quarter'] = $request->post('quarter', '');
        $where['startTime'] = $request->post('start_time', '');
        $where['endTime'] = $request->post('end_time', '');
        $where['depName'] = $request->post('dep_name', '');
        $where['sort'] = $request->post('sort', 'SORT_ASC');
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);

        if ($type == 57) {
            $where['sort'] = 'SORT_DESC';
        }

        if (!$type) {
            return ToolsService::returnData(4001, '参数错误', $msg ?? '');
        }

        $targetService = new TargetService();
        $data = $targetService->depZbStatistics($type, $where, $page, $pageSize);

        return ToolsService::returnData(200, $data, $msg ?? '');
    }

    /**
     * 科室排名统计导出
     * @param Request $request
     * @return array|string|null
     */
    public function depStatisticsExport(Request $request)
    {
        $type = $request->post('type', '');
        $where['year'] = $request->post('year', '');
        $where['quarter'] = $request->post('quarter', '');
        $where['startTime'] = $request->post('start_time', '');
        $where['endTime'] = $request->post('end_time', '');
        $where['depName'] = $request->post('dep_name', '');
        $where['sort'] = $request->post('sort', 'SORT_ASC');
        $page = 1;
        $pageSize = 1000000;

        if (!$type) {
            return ToolsService::returnData(4001, '参数错误', $msg ?? '');
        }

        $targetService = new TargetService();
        $data = $targetService->depZbStatistics($type, $where, $page, $pageSize);

        $zbName = config('zb.zb_name_list');
        $title = $zbName[$type];
        $titleArr = ['dep_id' => '科室ID', 'dep_name' => '科室名称', 'res' => $title . '完成率', 'numerator' => '正确病例数', 'denominator' => '病例总数'];
        array_unshift($data['data'], $titleArr);
        $csv = new CsvService();
        $csv->filename = $csv->charset($title, 'UTF-8');

        return $csv->export($data['data'], false);
    }

    /**
     * 科室病例统计
     * @param Request $request
     * @return array
     */
    public function depBlList(Request $request)
    {
        $type = $request->post('type', '');
        $where['year'] = $request->post('year', '');
        $where['quarter'] = $request->post('quarter', '');
        $where['startTime'] = $request->post('start_time', '');
        $where['endTime'] = $request->post('end_time', '');
        $where['depName'] = $request->post('dep_name', '');
        $where['status'] = $request->post('status', '');
        if ($type == 57 && is_numeric($where['status'])) {
            $where['status'] = $where['status'] == 1 ? 0 : 1;
        }
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);

        if (!$type || !$where['depName']) {
            return ToolsService::returnData(4001, '参数错误', $msg ?? '');
        }

        $targetService = new TargetService();
        $data = $targetService->depZbStatisticsList($type, $where, $page, $pageSize);

        return ToolsService::returnData(200, $data, $msg ?? '');
    }

    /**
     * 科室病例统计导出
     * @param Request $request
     * @return array|string|null
     */
    public function depBlExport(Request $request)
    {
        $type = $request->post('type', '');
        $where['year'] = $request->post('year', '');
        $where['quarter'] = $request->post('quarter', '');
        $where['startTime'] = $request->post('start_time', '');
        $where['endTime'] = $request->post('end_time', '');
        $where['depName'] = $request->post('dep_name', '');
        $where['status'] = $request->post('status', '');
        $page = 1;
        $pageSize = 1000000;

        if ($type == 60 || $type == 61 || $type == 62) {
            if (!$type) {
                return ToolsService::returnData(4001, '参数错误', $msg ?? '');
            }
        } else {
            if (!$type || !$where['depName']) {
                return ToolsService::returnData(4001, '参数错误', $msg ?? '');
            }
        }


        $targetService = new TargetService();
        $data = $targetService->depZbStatisticsList($type, $where, $page, $pageSize);

        $zbName = config('zb.zb_name_list');
        $title = $zbName[$type];
        $titleArr = ['MED_REC_ID' => '住院号', 'AAA01' => '患者姓名', 'AAC11N' => '出院科室', 'AAC01' => '出院时间', 'AAB01' => '入院时间', 'status' => '状态', 'describe' => '描述'];

        if($type == 60 || $type == 61) {
            $titleArr = [
                'AAA28' => '住院号码',
                'AAA01' => '患者姓名',
                'AAC11N' => '出院科室',
                'zyzdmc' => '主要诊断名称',
                'zyzbbh' => '主要诊断编码',
                'AAC01' => '出院时间',
                'AAB01' => '入院时间',
                'status' => '状态',
                'fbsj' => '发病时间',
                'fbsj_s' => '发病时间(时)',
                'zhusu' => '主诉',
                'ssmc' => '手术名称',
                'ssbh' => '手术编码',
                'sssj' => '手术时间'
            ];
        }

        if ($type == 62) {
            $titleArr = ['AAA28' => '住院号码', 'AAA01' => '患者姓名', 'zyzdmc' => '主要诊断名称', 'zyzbbh' => '主要诊断编码','rssj' => '溶栓时间', 'yzmc' => '医嘱名称', 'XZJDSJ' => '首次护士执行时间', 'fbsj' => '发病时间', 'AAB01' => '入院时间', 'AAC01' => '出院时间', 'AAC11N' => '出院科室', 'status' => '状态'];
        }

        // 在导出前重新排序数据
        $exportData = [];
        foreach ($data['data'] as $row) {
            $orderedRow = [];
            foreach ($titleArr as $key => $title) {
                // 对住院号码特殊处理，添加 =".." 格式强制Excel以文本格式显示
                if ($key === 'AAA28') {
                    $orderedRow[$key] = '="' . $row[$key] . '"';
                } else {
                    $orderedRow[$key] = $row[$key] ?? '';
                }
            }
            $exportData[] = $orderedRow;
        }

        $data['data'] = $exportData;
        array_unshift($data['data'], $titleArr);

        $csv = new CsvService();
        // 根据type设置正确的文件名
        $fileName = $type == 61 ? '心梗指标.csv' : $title;
        $csv->filename = $csv->charset($fileName, 'UTF-8');

        return $csv->export($data['data'], false);
    }

    /**
     * 修改脑梗 心梗发病时间
     * @param Request $request
     * @return array
     */
    public function updateZlFbsj(Request $request)
    {
        $BLBH = $request->post('blbh');
        $type = $request->post('type');
        $time = $request->post('time');
        $field = $request->post('field');

        Pszb::query()->where('BLBH', $BLBH)->where('type', $type)->update([$field => $time]);

        return ToolsService::returnData(200,'','修改成功');
    }

    /**
     * 科室排名统计
     * @param Request $request
     * @return array
     */
    public function depStatisticsNew(Request $request)
    {
        $type = $request->post('type', '');
        $where['year'] = $request->post('year', '');
        $where['quarter'] = $request->post('quarter', '');
        $where['startTime'] = $request->post('start_time', '');
        $where['endTime'] = $request->post('end_time', '');
        $where['depName'] = $request->post('dep_name', '');
        $where['sort'] = $request->post('sort', 'SORT_ASC');
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);

        if ($type == 57) {
            $where['sort'] = 'SORT_DESC';
        }

        if (!$type) {
            return ToolsService::returnData(4001, '参数错误', $msg ?? '');
        }

        $targetService = new TargetService();
        $data = $targetService->depZbStatistics($type, $where, $page, $pageSize,'patient_info_target_temporary');

        return ToolsService::returnData(200, $data, $msg ?? '');
    }

    /**
     * 科室排名统计导出
     * @param Request $request
     * @return array|string|null
     */
    public function depStatisticsExportNew(Request $request)
    {
        $type = $request->post('type', '');
        $where['year'] = $request->post('year', '');
        $where['quarter'] = $request->post('quarter', '');
        $where['startTime'] = $request->post('start_time', '');
        $where['endTime'] = $request->post('end_time', '');
        $where['depName'] = $request->post('dep_name', '');
        $where['sort'] = $request->post('sort', 'SORT_ASC');
        $page = 1;
        $pageSize = 1000000;

        if (!$type) {
            return ToolsService::returnData(4001, '参数错误', $msg ?? '');
        }

        $targetService = new TargetService();
        $data = $targetService->depZbStatistics($type, $where, $page, $pageSize,'patient_info_target_temporary');

        $zbName = config('zb.zb_name_list');
        $title = $zbName[$type];
        $titleArr = ['dep_id' => '科室ID', 'dep_name' => '科室名称', 'res' => $title . '完成率', 'numerator' => '正确病例数', 'denominator' => '病例总数'];
        array_unshift($data['data'], $titleArr);
        $csv = new CsvService();
        $csv->filename = $csv->charset($title, 'UTF-8');

        return $csv->export($data['data'], false);
    }

    /**
     * 科室病例统计
     * @param Request $request
     * @return array
     */
    public function depBlListNew(Request $request)
    {
        $type = $request->post('type', '');
        $where['year'] = $request->post('year', '');
        $where['quarter'] = $request->post('quarter', '');
        $where['startTime'] = $request->post('start_time', '');
        $where['endTime'] = $request->post('end_time', '');
        $where['depName'] = $request->post('dep_name', '');
        $where['status'] = $request->post('status', '');
        if ($type == 57 && is_numeric($where['status'])) {
            $where['status'] = $where['status'] == 1 ? 0 : 1;
        }
        $page = $request->post('page', 1);
        $pageSize = $request->post('page_size', 10);

        if (!$type || !$where['depName']) {
            return ToolsService::returnData(4001, '参数错误', $msg ?? '');
        }

        $targetService = new TargetService();
        $data = $targetService->depZbStatisticsList($type, $where, $page, $pageSize, 'patient_info_target_temporary');

        return ToolsService::returnData(200, $data, $msg ?? '');
    }

    /**
     * 科室病例统计导出
     * @param Request $request
     * @return array|string|null
     */
    public function depBlExportNew(Request $request)
    {
        $type = $request->post('type', '');
        $where['year'] = $request->post('year', '');
        $where['quarter'] = $request->post('quarter', '');
        $where['startTime'] = $request->post('start_time', '');
        $where['endTime'] = $request->post('end_time', '');
        $where['depName'] = $request->post('dep_name', '');
        $where['status'] = $request->post('status', '');
        $page = 1;
        $pageSize = 1000000;

        if ($type == 60 || $type == 61) {
            if (!$type) {
                return ToolsService::returnData(4001, '参数错误', $msg ?? '');
            }
        } else {
            if (!$type || !$where['depName']) {
                return ToolsService::returnData(4001, '参数错误', $msg ?? '');
            }
        }


        $targetService = new TargetService();
        $data = $targetService->depZbStatisticsList($type, $where, $page, $pageSize, 'patient_info_target_temporary');

        $zbName = config('zb.zb_name_list');
        $title = $zbName[$type];
        $titleArr = ['MED_REC_ID' => '住院号', 'AAA01' => '患者姓名', 'AAC11N' => '出院科室', 'AAC01' => '出院时间', 'AAB01' => '入院时间', 'status' => '状态', 'describe' => '描述'];

        if($type == 60 || $type == 61) {
            $titleArr = ['AAA28' => '住院号码', 'AAA01' => '患者姓名', 'zyzdmc' => '主要诊断名称', 'zyzbbh' => '主要诊断编码', 'fbsj' => '发病时间', 'fbsj_s' => '发病时间(时)', 'AAB01' => '入院时间', 'AAC01' => '出院时间', 'zhusu' => '主诉', 'ssmc' => '手术名称', 'ssbh' => '手术编码', 'sssj' => '手术时间', 'AAC11N' => '出院科室', 'status' => '状态', 'describe' => '描述'];
        }

        array_unshift($data['data'], $titleArr);
        $csv = new CsvService();
        $csv->filename = $csv->charset($title, 'UTF-8');

        return $csv->export($data['data'], false);
    }

}
