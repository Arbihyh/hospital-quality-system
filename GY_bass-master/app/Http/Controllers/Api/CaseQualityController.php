<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Service\ExportService;
use App\Http\Service\FrontDataService;
use App\Model\Department;
use App\Model\DepartmentData;
use App\Model\EMR_BL_BL01;
use App\Model\Error;
use App\Model\ErrorData;
use App\Model\FeeDetailed;
use App\Model\PatientHospitalInfo;
use App\Model\PatientInfo;
use App\Services\CoderService;
use App\Services\DepartmentService;
use App\Services\ErrorRuleService;
use App\Services\ExportWTAndGKService;
use App\Services\QualityService;
use App\Services\ToolsService;
use App\Services\DebugItemsService;
use App\Services\CaseQualityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ExportData;

/**
 * 全病历质控
 *
 * Class CaseQualityController
 * @package App\Http\Controllers\Api
 * @group case-quality
 */
class CaseQualityController extends Controller
{

    /**
     * analysis
     * /api/case-quality/analysis
     * 质量分析
     * @group case-quality
     * @bodyParam start_time string  开始时间  日期字符串格式
     * @bodyParam end_time string  结束时间  日期字符串格式
     *
     * @response {
     *  "code":200,
     *    "msg":"结果",
     *  "data":{
     *       "case_total":"病案数量",
     *       "defect_case_total":"缺陷病案数",
     *  },
     * "time":123787842
     * }
     */
    public function analysis(Request $request)
    {
        $queryCond = $this->commonQueryCond($request);

        // 病例数量
        $caseTotal = CaseQualityService::getCaseTotal($queryCond);

        // 缺陷病案数
        $defectCaseTotal = CaseQualityService::getDefectCaseTotal($queryCond);

        $data['case_total'] = $caseTotal ?? 0;
        $data['defect_case_total'] = $defectCaseTotal ?? 0;

        DebugItemsService::getInstance()->calcDebugData($queryCond->debugis, $data);
        return ToolsService::jsonSuccess($data);
    }

    /**
     * ranking_department
     * /api/case-quality/ranking_department
     * 科室排名 前10条
     * @group case-quality
     * @bodyParam start_time string  开始时间  日期字符串格式
     * @bodyParam end_time string  结束时间  日期字符串格式
     *
     * @response {
     *  "code":200,
     *    "msg":"结果",
     *  "data": {
     *      "list":[{
     *          "name":"科室名称",
     *          "total_medical":"病案数",
     *          "total_error_medical":"缺陷病案数",
     *      }]
     *  },
     * "time":123787842
     * }
     */
    public function rankingDepartment(Request $request)
    {
        $queryCond = $this->commonQueryCond($request);

        $data = ['list'=>[],'count'=>0];

        // 总病例总数
        $caseTotal = CaseQualityService::getCaseTotal($queryCond);

        $departmentCases = CaseQualityService::getDepartmentCases($queryCond);
        $departmentDefectCases = CaseQualityService::getDepartmentDefectCases($queryCond);


        DebugItemsService::getInstance()->putDebugItem('department_data', [
            '$caseTotal' => $caseTotal,
            '$departmentCases' => $departmentCases,
            '$departmentDefectCases' => $departmentDefectCases,
        ], 'data');

        $collects = [];

        foreach ($departmentCases as $case) {
            $name = $case->AAC11N;
            $collects[$name]['name'] = $name;
            $collects[$name]['total_medical'] = $case->nums;
            $collects[$name]['total_error_medical'] = 0;
        }

        foreach ($departmentDefectCases as $ii => $case) {
            $name = $case->AAC11N;
            $collects[$name]['name'] = $name;
            $collects[$name]['total_error_medical'] = $case->nums;
        }

        $collects = array_values($collects);

        DebugItemsService::getInstance()->putDebugItem('merge', $collects, 'data:');

        if (!empty($collects)) {
            // 缺陷病案数/总病案数    数值小的在前
            $collects = collect($collects)->sortBy(function ($item, $key){
                return $item['total_error_medical'];
            })->values()->take(10)->all();

            $data = ['list' => $collects,'count' => count($collects)];
        }


        DebugItemsService::getInstance()->calcDebugData($queryCond->debugis, $data);
        return ToolsService::jsonSuccess($data);
    }

    /**
     * defect_issues
     * /api/case-quality/defect_issues
     * 缺陷问题列表
     * @group case-quality
     * @bodyParam start_time string  开始时间  日期字符串格式
     * @bodyParam end_time string  结束时间  日期字符串格式
     *
     * @response {
     *  "code":200,
     *    "msg":"结果",
     *  "data": {
     *      "list":[{
     *          "id":"序号",
     *          "desc":"缺陷描述",
     *          "field":"缺陷字段",
     *          "level":"缺陷分级",
     *          "total_num":"缺陷数量",
     *      }]
     *  },
     * "time":123787842
     * }
     */
    public function defectIssues(Request $request)
    {
        $queryCond = $this->commonQueryCond($request);

        $data = ['list'=>[],'count'=>0];

        for ($i = 0; $i < 20; $i++) {
            $data['list'][] = [
                'id' => random_int(100,777) . date('hi') . random_int(888,1999),
                'desc' => '缺陷描述',
                'field' => '缺陷字段',
                'level' => '缺陷分级',
                'total_num' => '缺陷数量',
            ];
        }

        $data['count'] = $data;

        DebugItemsService::getInstance()->calcDebugData($queryCond->debugis, $data);
        return ToolsService::jsonSuccess($data);
    }
    
    public function commonQueryCond(Request $request)
    {
        $start_time = $request->post("start_time");//日期字符串格式
        $end_time = $request->post("end_time");
        $debugis = $request->post("debugis", 0);

        if (empty($start_time)){
            $start_time = date('Y-01-01');
        }else{
            $start_time = date('Y-m-d',strtotime($start_time));
        }
        if (empty($end_time)){
            $end_time = date('Y-12-31');
        }else{
            $end_time = date('Y-m-d',strtotime($end_time));
        }

        $obj = new \stdClass();

        $obj->start_time = $start_time;
        $obj->end_time = $end_time;
        $obj->debugis = $debugis;

        return $obj;
    }


    /**
     * 返回数组指定键值组
     */
    private function keyGetval($arr, $key)
    {
        $arrs = [];
        foreach ($arr as $v) {
            $arrs[] = $v[$key];
        }
        return $arrs;
    }

}