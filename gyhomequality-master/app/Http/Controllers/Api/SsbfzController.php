<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\SsbfzStatistics;
use App\Services\CsvService;
use App\Services\ElasticsearchService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SsbfzController extends Controller
{
    /**
     * 手术并发症统计
     * @return array
     */
    public function getBfzData(Request $request)
    {
        $field = $request->post('field','');
        $startTime = $request->post('start_time','');
        $endTime = $request->post('end_time','');

        if (empty($field) || empty($startTime) || empty($endTime)) {
            return ToolsService::returnData(4001, [],'参数错误');
        }
        $startTime = date('Y-m-d', strtotime($startTime)).' 00:00:00';
        $endTime = date('Y-m-d', strtotime($endTime)).' 23:59:59';

        // 数据查询
        $data = SsbfzStatistics::query()
            ->where($field,'>',0)
            ->whereBetween('AAC01',[$startTime,$endTime])
            ->groupBy('month')
            ->get([DB::raw('sum('.$field.') as count'),'month'])->toArray();

        $data = array_column($data, null, 'month');
        $list = [];
        $sumCount = 0;
        $year = date('Y',strtotime($startTime));
        for ($i=1;$i<=12;$i++) {
            $list[] = [
                'count' => isset($data[$i]) ? $data[$i]['count'] : 0,
                'time' => $year . '-' . sprintf('%02s', $i),
                'source' => '系统提取'
            ];
            $sumCount+=$list[$i-1]['count'];
        }
        $list[] = [
            'count' => $sumCount,
            'time' => '全年',
            'source' => '系统提取'
        ];

        return ToolsService::returnData(200, $list);
    }

    /**
     * 手术并发症统计详情列表
     * @param Request $request
     * @return array|string|null
     */
    public function getBfzList(Request $request)
    {
        $field = $request->post('field','');
        $AAA28 = $request->post('AAA28','');
        $AAC11N = $request->post('AAC11N','');
        $startTime = $request->post('start_time','');
        $endTime = $request->post('end_time','');
        $page = $request->post("page", 1);
        $pageSize = $request->post("page_size", 10);
        $isExport = $request->post('is_export',0);
        if ($isExport) {
            $page = 1;
            $pageSize = 1000000;
        }

        if (empty($startTime) || empty($endTime)) {
            return ToolsService::returnData(4001, [],'参数错误');
        }
        $startTime = date('Y-m-d', strtotime($startTime)).' 00:00:00';
        $endTime = date('Y-m-d', strtotime($endTime)).' 23:59:59';

        $query = SsbfzStatistics::query();

        if (!empty($AAA28)) {
            $query->where('AAA28','=',$AAA28);
        }
        if (!empty($AAC11N)) {
            $query->where('AAC11N','like',"%".$AAC11N."%");
        }

        $data = $query
            ->where($field,'>',0)
            ->whereBetween('AAC01',[$startTime.' 00:00:00',$endTime.' 23:59:59'])
            ->paginate($pageSize,['AAA28','ZYH','AAC01','AAC11N'],'page',$page)
            ->toArray();

        $returnData = ['list'=>[],'count'=>$data['total']];
        if (!empty($data['data'])) {
            $list = [];
            $mainDiagnosisService = new ElasticsearchService('main_diagnosis');
            $otherDiagnosisService = new ElasticsearchService('other_diagnosis_2023');

            foreach ($data['data'] as $key => $val) {
                $source = SsbfzStatistics::$fieldList[$field];
                $selectField = $field;

                $arr = SsbfzStatistics::$list[$selectField];
                $should = [];
                foreach ($arr as $value) {
                    $should[] = ["match_phrase_prefix" => ['ICD10_ID1' => $value]];
                }

                $ZYH = $val['ZYH'];
                array_unshift($val,$source);
                unset($val['XTQGXH'],$val['XTQGXUNHUAN'],$val['XTQGSJ'],$val['XTQGYHFQ'],$val['XTQGEHRC'],$val['XTQGJRGG'],$val['XTQGMNSZ'],$val['XTQGKQ']);
                unset($val['ZRWXZHXG'],$val['ZRWMNSZD'],$val['ZRWGK'],$val['ZRWQT'],$val['field']);
                if ($isExport) {
                    unset($val['ZYH']);
                }
                $val['AAA28'] = $val['AAA28']."\t";
                $val['AAC01'] = $val['AAC01']."\t";
                $list[$key] = $val;

                // 主要诊断
                $must = [
                    ["term" => ['ZYH' => $ZYH]],
                    ["term" => ['RYQK' => '无']]
                ];
                $params = $mainDiagnosisService->clearMust()
                    ->queryByMustBatch($must)
                    ->queryByShouldBatch($should)
                    ->minimumShouldMatch(1)
                    ->getParams();
                $restful = app('es')->search($params);
                $mainDiagnosis = $mainDiagnosisService->getDataByEs($restful);
                $list[$key]['ZYZD_ID'] = !empty($mainDiagnosis[0]) ? $mainDiagnosis[0][0]['ICD10_ID1'] : '';
                $list[$key]['ZYZD_NAME'] = !empty($mainDiagnosis[0]) ? $mainDiagnosis[0][0]['ICD10_NAME'] : '';
                $list[$key]['ZYZD_RYQK'] = !empty($mainDiagnosis[0]) ? $mainDiagnosis[0][0]['RYQK'] : '';

                // 其他诊断
                $params = $otherDiagnosisService->clearMust()
                    ->queryByMustBatch($must)
                    ->queryByShouldBatch($should)
                    ->minimumShouldMatch(1)
                    ->getParams();
                $restful = app('es')->search($params);
                $otherDiagnosis = $otherDiagnosisService->getDataByEs($restful);
                for ($i=1;$i<=3;$i++) {
                    $list[$key]['QTZD_ID'.$i] = !empty($otherDiagnosis[0][$i-1]) ? $otherDiagnosis[0][$i-1]['ICD10_ID1'] : '';
                    $list[$key]['QTZD_NAME'.$i] = !empty($otherDiagnosis[0][$i-1]) ? $otherDiagnosis[0][$i-1]['ICD10_NAME'] : '';
                    $list[$key]['QTZDRYQK'.$i] = !empty($otherDiagnosis[0][$i-1]) ? $otherDiagnosis[0][$i-1]['RYQK'] : '';
                }
            }

            $returnData['list'] = $list;
        }

        if ($isExport) {
            $csv = new CsvService();
            $csv->filename = $csv->charset('手术并发症', 'UTF-8');

            $title = ['所属','住院号码','出院时间','科室','主要诊断编码','主要诊断名称','主要诊断入院情况','其他诊断编码1','其他诊断名称1','其他诊断入院情况1','其他诊断编码2','其他诊断名称2','其他诊断入院情况2','其他诊断编码3','其他诊断名称3','其他诊断入院情况3'];
            array_unshift($returnData['list'],$title);
            return $csv->export($returnData['list'], false);
        }

        return ToolsService::returnData(200, $returnData);
    }

    /**
     * 导出全部手术并发症
     * @param Request $request
     * @return string|null
     */
    public function exportAll(Request $request)
    {
        $AAA28 = $request->post('AAA28','');
        $AAC11N = $request->post('AAC11N','');
        $startTime = $request->post('start_time','');
        $endTime = $request->post('end_time','');
        $startTime = !empty($startTime) ? date('Y-m-d',strtotime($startTime)) : date('Y',time()).'-01-01';
        $endTime = !empty($endTime) ? date('Y-m-d',strtotime($endTime)) : date('Y',time()).'-12-31';

        $mainDiagnosisService = new ElasticsearchService('main_diagnosis');
        $otherDiagnosisService = new ElasticsearchService('other_diagnosis_2023');

        $exportData = [
            ['所属','住院号码','出院时间','科室','主要诊断编码','主要诊断名称','主要诊断入院情况','其他诊断编码1','其他诊断名称1','其他诊断入院情况1','其他诊断编码2','其他诊断名称2','其他诊断入院情况2','其他诊断编码3','其他诊断名称3','其他诊断入院情况3']
        ];
        $fieldList = SsbfzStatistics::$fieldList;
        foreach ($fieldList as $field => $name) {
            $query = SsbfzStatistics::query();
            if (!empty($AAA28)) {
                $query->where('AAA28','=',$AAA28);
            }
            if (!empty($AAC11N)) {
                $query->where('AAC11N','like',"%".$AAC11N."%");
            }
            $data = $query
                ->where($field,'>',0)
                ->whereBetween('AAC01',[$startTime.' 00:00:00',$endTime.' 23:59:59'])
                ->get(['AAA28','ZYH','AAC01','AAC11N'])->toArray();
            if (!empty($data)) {
                $should = [];
                $arr = SsbfzStatistics::$list[$field];
                foreach ($arr as $value) {
                    $should[] = ["match_phrase_prefix" => ['ICD10_ID1' => $value]];
                }

                foreach ($data as $val) {
                    array_unshift($val,$name);
                    $ZYH = $val['ZYH'];
                    unset($val['ZYH']);

                    // 主要诊断
                    $must = [
                        ["term" => ['ZYH' => $ZYH]],
                        ["term" => ['RYQK' => '无']]
                    ];
                    $params = $mainDiagnosisService->clearMust()
                        ->queryByMustBatch($must)
                        ->queryByShouldBatch($should)
                        ->minimumShouldMatch(1)
                        ->getParams();
                    $restful = app('es')->search($params);
                    $mainDiagnosis = $mainDiagnosisService->getDataByEs($restful);
                    $val['ZYZD_ID'] = !empty($mainDiagnosis[0]) ? $mainDiagnosis[0][0]['ICD10_ID1'] : '';
                    $val['ZYZD_NAME'] = !empty($mainDiagnosis[0]) ? $mainDiagnosis[0][0]['ICD10_NAME'] : '';
                    $val['ZYZD_RYQK'] = !empty($mainDiagnosis[0]) ? $mainDiagnosis[0][0]['RYQK'] : '';

                    // 其他诊断
                    $params = $otherDiagnosisService->clearMust()
                        ->queryByMustBatch($must)
                        ->queryByShouldBatch($should)
                        ->minimumShouldMatch(1)
                        ->getParams();
                    $restful = app('es')->search($params);
                    $otherDiagnosis = $otherDiagnosisService->getDataByEs($restful);
                    for ($i=1;$i<=3;$i++) {
                        $val['QTZD_ID'.$i] = !empty($otherDiagnosis[0][$i-1]) ? $otherDiagnosis[0][$i-1]['ICD10_ID1'] : '';
                        $val['QTZD_NAME'.$i] = !empty($otherDiagnosis[0][$i-1]) ? $otherDiagnosis[0][$i-1]['ICD10_NAME'] : '';
                        $val['QTZDRYQK'.$i] = !empty($otherDiagnosis[0][$i-1]) ? $otherDiagnosis[0][$i-1]['RYQK'] : '';
                    }

                    $exportData[] = $val;
                }
            }
        }

        $csv = new CsvService();
        $csv->filename = $csv->charset('手术并发症', 'UTF-8');

        return $csv->export($exportData);
    }

}
