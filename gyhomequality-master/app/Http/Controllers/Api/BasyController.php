<?php

namespace App\Http\Controllers\Api;

use App\Exports\ExportData;
use App\Http\Controllers\Controller;
use App\Imports\BasyImport;
use App\Model\PatientInfo;
use App\Model\Setting;
use App\Services\ElasticsearchService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class BasyController extends Controller
{
    /**
     * 导入
     * @param Request $request
     * @return array
     */
    public function importData(Request $request)
    {
        ini_set("memory_limit", "-1");
        ini_set('default_socket_timeout', 24*3600);

        $file = $request->file('file','');
        if (empty($file)) {
            return ToolsService::returnData(4001, '', '参数错误');
        }

        // 文件大小验证
//        if ($file->getSize() > (4*1024*1024)){
//            return ToolsService::returnData(4001,'','文件大小超过限制');
//        }

        // 获取文件类型
        $type = $file->getClientOriginalExtension();
        if (!in_array($type,['xlsx'])) {
            return ToolsService::returnData(4001,'','请上传xlsx文件');
        }

        Excel::import(new BasyImport(), $file, 'local');

        $failData = BasyImport::$returnData;
        $count = BasyImport::$sumData;

        $data = [
            'list' => BasyImport::$returnData,
            'sum_count' => $count,
            'success_count' => $count-count($failData),
            'error_count' => count($failData),
        ];

        // 设置脚本执行
        Setting::query()->updateOrInsert(['name'=>'home_quality_is_zx'],['content'=>1]);

        return ToolsService::returnData(200, $data);
    }

    public function getBlAll(Request $request)
    {
        $AAA28 = $request->post('AAA28','');
        $depName= $request->post('dep_name','');
        $startTime = $request->post('start_time','');
        $endTime = $request->post('end_time','');
        $hospitalName = $request->input("hospitalName", '');
        $hospitalName = !empty($hospitalName) ? $hospitalName : config('confAdmin.hospital_name');
        $page = $request->post('page',1);
        $pageSize = $request->post('page_size',10);
        $isExport = $request->post('is_export',0);
        if ($isExport) {
            $page = 1;
            $pageSize = 100000;
        }

        $piService = new ElasticsearchService('patient_info');

        $must = [
            ['match_phrase' => ['hospital_name' => $hospitalName]]
        ];
        if ($AAA28) {
            $must[] = ['term' => ['AAA28' => $AAA28]];
        }
        if ($depName) {
            $must[] = ['match_phrase' => ['AAC11N' => $depName]];
        }
        if ($startTime && $endTime) {
            $startTime = date('Y-m-d', strtotime($startTime)).' 00:00:00';
            $endTime = date('Y-m-d', strtotime($endTime)).' 23:59:59';

            $must[] = ['range' => ['AAC01' => ['gte' => $startTime,'lte' => $endTime]]];
        } elseif ($startTime) {
            $startTime = date('Y-m-d', strtotime($startTime)).' 00:00:00';
            $must[] = ['range' => ['AAC01' => ['gte' => $startTime]]];
        } elseif ($endTime) {
            $endTime = date('Y-m-d', strtotime($endTime)).' 23:59:59';
            $must[] = ['range' => ['AAC01' => ['lte' => $endTime]]];
        }

        $fieldData = ['AAA28','MED_REC_ID','AAA01','AAA02C','AAA04','AAA29','AAC11N','AAC01','ADA01','F_D','J','ICD10_NAME','ICD9_NAME','AAC04','ATTEND_GRP_NAME','AEM01C','AAB06C'];
        $params = $piService->clearMust()
            ->queryByMustBatch($must)
            ->paginate($page,$pageSize)
            ->source($fieldData)
            ->getParams();
        $restful = app('es')->search($params);
        $piData = $piService->getDataByEs($restful);
        $data = !empty($piData[0]) ? $piData[0] : [];
        $count = !empty($piData[1]) ? $piData[1] : 0;

        $AAA02C = config('dictionaries.AAA02C');
        $AEM01C = config('dictionaries.AEM01C');
        $AAB06C = config('dictionaries.AAB06C');
        $list = [];
        foreach ($data as $value) {
            $value['AAA02C'] = !empty($AAA02C[$value['AAA02C']]) ? $AAA02C[$value['AAA02C']] : '';
            $value['AEM01C'] = !empty($AEM01C[$value['AEM01C']]) ? $AEM01C[$value['AEM01C']] : '';
            $value['AAB06C'] = !empty($AAB06C[$value['AAB06C']]) ? $AAB06C[$value['AAB06C']] : '';
            $array = [
                'AAA28' => $value['AAA28'],
                'AAA01' => $value['AAA01'],
                'AAA02C' => $value['AAA02C'],
                'AAA04' => $value['AAA04'],
                'AAA29' => $value['AAA29'],
                'AAC11N' => $value['AAC11N'],
                'AAC01' => $value['AAC01'],
                'ADA01' => $value['ADA01'],
                'F_D' => $value['F_D'],
                'J' => $value['J'],
                'ICD10_NAME' => trim($value['ICD10_NAME']),
                'ICD9_NAME' => trim($value['ICD9_NAME']),
                'AAC04' => $value['AAC04'],
                'ATTEND_GRP_NAME' => $value['ATTEND_GRP_NAME'],
                'AEM01C' => $value['AEM01C'],
                'AAB06C' => $value['AAB06C'],
            ];
            if (!$isExport) {
                $array['MED_REC_ID'] = $value['MED_REC_ID'];
            }
            $list[] = $array;
        }

        if ($isExport) {
            $title = ['住院号码','患者姓名','性别','年龄','住院次数','出院科室','出院日期','总费用','药品费用','材料费用','主诊断','主手术','实际住院天数','主诊组','离院方式','入院途径'];
            return Excel::download(new ExportData($title, $list), '病案首页列表.xlsx');
        }

        return ToolsService::returnData(200, ['count'=>$count,'list'=>$list]);
    }



}
