<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;

use App\Http\Service\FrontDataService;
use App\Model\CoderData;
use App\Model\DepartmentData;
use App\Model\HospitalData;
use App\Model\IndicationsData;
use App\Model\PatientHospitalInfo;
use App\Services\QualityService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Model\Count;

class RankingController extends Controller
{
    /**
     * department
     * /api/ranking_department
     *科室排名 前10条
     * @group ranking
     * @bodyParam title string start_time 开始时间  时间戳or时间
     * @bodyParam time string end_time 结束时间
     * @bodyParam type string 按年(1)、季度(2)、月(3);
     *
     * @response {
     *  "code":200,
     *    "msg":"结果",
     *  "data": {
     *      "list":[{
     *          "average_score":"平均分",
     *          "name":"科室名称",
     *          "total_medical":"病案数",
     *          "total_error_medical":"缺陷病案数",
     *          "total_error":"总缺陷",
     *          "average_error":"平均缺陷",
     *          "average_score":"平均分",
     *          "max_score":"最高分",
     *          "min_score":"最低分",
     *          "outstanding":"优秀率"
     *      }]
     *  },
     * "time":123787842
     * }
     */
    public function department(Request $request)
    {
        $start_time = $request->post("start_time");//时间戳
        $end_time = $request->post("end_time");
        $type = $request->post("type", 1);
        $isExport = $request->post("is_export", 0);
        $isPage = $request->post("isPage", 1);
        $num = 10;
        $offset = ($isPage - 1) * $num;
        ## 导出
        if ($isExport == 1) {
            $exportService = new FrontDataService();
            return $exportService->departmentExport($start_time, $end_time);
        }

        if (empty($start_time)) {
            $start_time = date('Y-01-01');
        } else {
            $start_time = date('Y-m-d H:i:s', strtotime($start_time));
        }
        if (empty($end_time)) {
            $end_time = date('Y-12-31');
        } else {
            $end_time = date('Y-m-d H:i:s', strtotime($end_time));
        }
        $model = DepartmentData::query()->whereBetween('created_at', [$start_time, $end_time]);
        $result = $model
            ->groupBy('department_id')
            ->get([
                'department_id',
                DB::raw('sum(total_score) as total_score'),
                DB::raw('sum(total_medical) as total_medical'),
                DB::raw('sum(total_error) as total_error'),
                DB::raw('max(max_score) as max_score'),
                DB::raw('min(min_score) as min_score'),
                DB::raw('sum(total_error_medical) as total_error_medical')
            ]);
        if ($result->isEmpty()) {
            $code = 200;
            $data = ['list' => [], 'count' => 0];
            $msg = '没有数据！';
        } else {
            $result = $result->toArray();
            foreach ($result as &$item)//按department_id分组
            {
                $item['average_error'] = sprintf('%.2f', $item['total_error_medical'] / $item['total_medical']);
                $item['average_score'] = sprintf('%.2f', $item['total_score'] / $item['total_medical']);
            }
            $res = $this->sortByKey($result, 'average_error', 2, $offset, $num);
            $count = count($result);
            $names = config('dictionaries.ABAS02');
            $list = array_column($names, 'code');
            $outstanding = PatientHospitalInfo::query()
                ->whereRaw("(AAA28 in (select MED_REC_ID from patient_info where AAC01 between '$start_time' and '$end_time' and level = 0))")
                ->whereIn('AAC11C', $list)
                ->groupBy('AAC11C')
                ->get([
                    'AAC11C',
                    DB::raw('count(AAA28) as count')
                ])
                ->keyBy('AAC11C')
                ->toArray();
            foreach ($res as &$v) {
                $v['name'] = $names[$v['department_id']];
                $v['outstanding'] = isset($outstanding[$v['department_id']]) ? sprintf('%.2f', $outstanding[$names[$v['department_id']]['code']]['count'] / $v['total_medical']) : 0;
            }

            $code = 200;
            $data = ['list' => $res, 'count' => $count];
            $msg = '成功！';
        }
        return ToolsService::returnData($code, $data, $msg);
    }

    /**
     * attendingGroup
     *主诊组排名 前10条
     * @group ranking
     * @bodyParam title string start_time 开始时间  时间戳or时间
     * @bodyParam time string end_time 结束时间
     * @bodyParam type string 按年(1)、季度(2)、月(3);
     *
     * @response {
     *  "code":200,
     *    "msg":"结果",
     *  "data": {
     *      "list":[{
     *          "total_medical":"总病案数量",
     *          "total_error_medical":"累计有问题病案数",
     *          "control_medical":"质控病案数",
     *          "name":"科室",
     *          "department_name":"主诊组",
     *          "total_error_proportion":"问题比例",
     *          "complete_error_medical":"完整性问题-病案数",
     *          "complete_error_proportion":"完整性问题-病案比例",
     *          "logic_error_medical":"逻辑性",
     *          "standard_error_medical":"规范性",
     *          "code_error_medical":"编码错误"
     *      }]
     *  },
     * "time":123787842
     * }
     */
    public function attendingGroup(Request $request)
    {
        $start_time = $request->post("start_time");//时间戳
        $end_time = $request->post("end_time");
        $type = $request->post("type", 1);
        $isExport = $request->post("is_export", 0);
        ## 导出
        // if ($isExport == 1) {
        //     $exportService = new ExportWTAndGKService();
        //     return $exportService->attendingGroupExport($start_time,$end_time);
        // }

        if (empty($start_time)) {
            $start_time = date('Y-01-01');
        }
        if (empty($end_time)) {
            $end_time = date('Y-12-31');
        }


        $model = Db::table('attending_group_data');
        $model = $this->getCondition($model, $start_time, $end_time, $type);
        $result = $model
            ->groupBy('attending_group_id')
            ->select('attending_group_id', DB::raw("sum(total_medical) as total_medical"), DB::raw("sum(total_error_medical) as total_error_medical"), DB::raw("sum(complete_error_medical) as complete_error_medical"), DB::raw("sum(logic_error_medical) as logic_error_medical"), DB::raw("sum(standard_error_medical) as standard_error_medical"), DB::raw("sum(code_error_medical) as code_error_medical"))
            ->orderBy('attending_group_id', 'desc')
            ->get();
        if ($result->isEmpty()) {
            $code = 200;
            $data = [];
            $msg = '没有数据！';
        } else {
            $datas = json_decode(json_encode($result), TRUE);
            $res = $this->sortByKey($datas, 'total_medical', 2, 0, 10);//排名
            $department_names = [];
            $name = config('dictionaries.ABAS02');
            foreach ($res as $k => $v) {
                $res[$k]['name'] = '';//科室
                $res[$k]['department_name'] = '';//主诊组
                $res[$k]['total_error_proportion'] = $v['total_error_medical'] / $v['total_medical'];//问题比例
                $res[$k]['complete_error_proportion'] = $v['complete_error_medical'] / $v['total_medical'];//病案比例
                $res[$k]['control_medical'] = $res[$k]['total_medical'];//质控病案数
                $res[$k]['total'] = $v['total_medical'];//和
                $res[$k]['total_error_proportion'] = round($res[$k]['total_error_proportion'], 2);
                $res[$k]['complete_error_proportion'] = round($res[$k]['complete_error_proportion'], 2);

            }

            $code = 200;
            $data = $res;
            $msg = '成功！';
        }

        // 导出
        if ($isExport == 1) {
            $exportService = new FrontDataService();
            return $exportService->attendingGroupExport($data, $start_time, $end_time);
        }

        return ToolsService::returnData($code, $data, $msg);
    }

    /**
     * indications
     *主治医师排名 前5条
     * @group ranking
     * @bodyParam  start_time string  开始时间  时间戳or时间
     * @bodyParam  end_time string  结束时间
     * @bodyParam type int  按年(1)、季度(2)、月(3);
     * @bodyParam num int  5条/10条
     *
     * @response {
     *  "code":200,
     *    "msg":"结果",
     *  "data": {
     *      "list":[{
     *          "total_medical":"总病案数量",
     *          "total_error_medical":"累计有问题病案数",
     *          "control_medical":"质控病案数",
     *          "name":"科室",
     *          "department_name":"主治医师姓名",
     *          "total_error_proportion":"问题比例",
     *          "complete_error_medical":"完整性问题-病案数",
     *          "complete_error_proportion":"完整性问题-病案比例",
     *          "logic_error_medical":"逻辑性",
     *          "standard_error_medical":"规范性",
     *          "code_error_medical":"编码错误"
     *      }]
     *  },
     * "time":123787842
     * }
     */
    public function indications(Request $request)
    {
        $start_time = $request->post("start_time");//时间戳
        $end_time = $request->post("end_time");
        $num = $request->post("num", 10);
        $isExport = $request->post("is_export", 0);

        if (empty($start_time)) {
            $start_time = date('Y-01-01');
        }
        if (empty($end_time)) {
            $end_time = date('Y-12-31');
        }
        $query = IndicationsData::query()
            ->whereBetween('created_at', [date('Y-m-d 00:00:00', strtotime($start_time)), date('Y-m-d 23:59:59', strtotime($end_time))]);
        $page = $request->post("page", 1);
        $offset = ($page - 1) * 10;


        $result = $query
            ->groupBy('indications_id')
            ->get([
                'indications_id',
                'department_id',
                DB::raw("sum(code_error_medical) as code_error_medical"),
                DB::raw("sum(total_medical) as total_medical"),
                DB::raw("sum(total_error_medical) as total_error_medical"),
//                DB::raw("sum(complete_error_medical) as complete_error_medical"),
                DB::raw("sum(logic_error_medical) as logic_error_medical"),
                DB::raw("sum(standard_error_medical) as standard_error_medical")
            ]);
        $list = $result->toArray();
        $department = config('dictionaries.ABAS02');
        foreach ($list as &$item) {
            $item['name'] = QualityService::getStaffInfo($item['indications_id'], 'name');
            $item['department_name'] = $department['department_id'] ?? '';
            $item['control_medical'] = $item['total_medical'];
            $item['total_error_proportion'] = sprintf('%.2f', $item['total_error_medical'] / $item['total_medical']);
//            $item['complete_error_proportion'] = sprintf('%.2f', $item['complete_error_medical'] / $item['total_medical']);
        }
        if ($isExport == 1) {
            $exportService = new FrontDataService();
            return $exportService->indicationsExport($result, $start_time, $end_time);
        }

        $res = $this->sortByKey($list, 'total_error_proportion', 2, $offset, $num);//排名
        $count = count($list);
        $code = 200;
        $data = ['list' => $res, 'count' => $count];
        $msg = '成功！';

        return ToolsService::returnData($code, $data, $msg);
    }

    /**
     * hospital
     *住院医师排名 前5条
     * @group ranking
     * @bodyParam title string start_time 开始时间  时间戳or时间
     * @bodyParam time string end_time 结束时间
     * @bodyParam type int 按年(1)、季度(2)、月(3);
     * @bodyParam num int 几条数据
     *
     * @response {
     *  "code":200,
     *    "msg":"结果",
     *  "data": {
     *      "list":[{
     *          "total_medical":"总病案数量",
     *          "total_error_medical":"累计有问题病案数",
     *          "control_medical":"质控病案数?",
     *          "name":"科室",
     *          "hospital_name":"住院医师",
     *          "total_error_proportion":"问题比例",
     *          "complete_error_medical":"完整性问题-病案数",
     *          "complete_error_proportion":"完整性问题-病案比例",
     *          "logic_error_medical":"逻辑性",
     *          "standard_error_medical":"规范性",
     *          "code_error_medical":"编码错误?"
     *      }]
     *  }
     * }
     */
    public function hospital(Request $request)
    {
        $start_time = $request->post("start_time");//时间戳
        $end_time = $request->post("end_time");
        $num = $request->post("limit", 10);
        $page = $request->post("page", 1);
        $isExport = $request->post("is_export", 0);
        $offset = ($page - 1) * $num;

        if (empty($start_time)) {
            $start_time = date('Y-01-01 00:00:00');
        }
        if (empty($end_time)) {
            $end_time = date('Y-12-31 23:59:59');
        }

        $query = HospitalData::query()
            ->whereBetween('created_at', [date('Y-m-d 00:00:00', strtotime($start_time)), date('Y-m-d 23:59:59', strtotime($end_time))]);
        $result = $query
            ->groupBy('hospital_id')
            ->get([
                'hospital_id',
                'department_id',
                DB::raw("sum(code_error_medical) as code_error_medical"),
                DB::raw("sum(total_medical) as total_medical"),
                DB::raw("sum(total_error_medical) as total_error_medical"),
//                DB::raw("sum(complete_error_medical) as complete_error_medical"),
                DB::raw("sum(logic_error_medical) as logic_error_medical"),
                DB::raw("sum(standard_error_medical) as standard_error_medical")]);
        if ($result) {
            $data = $result->toArray();
            $count = count($data);
            $names = config('dictionaries.ABAS02');
            foreach ($data as &$v) {
                $v['name'] = $names['department_id'] ?? '';//科室
                $v['hospital_name'] = QualityService::getStaffInfo($v['hospital_id'], 'name');
                $v['total_error_proportion'] = sprintf('%.2f', $v['total_error_medical'] / $v['total_medical']);//问题比例
//                $v['complete_error_proportion'] = sprintf('%.2f', $v['complete_error_medical'] / $v['total_medical']);//病案比例
                $v['control_medical'] = $v['total_medical'];//病案比例
            };

            // 导出
            if ($isExport == 1) {
                $exportService = new FrontDataService();
                return $exportService->hospitalExport($data, $start_time, $end_time);
            }
            $res = $this->sortByKey($data, 'total_error_proportion', 2, $offset, $num);//排名
            $code = 200;
            $data = ['list' => $res, 'count' => $count];
            $msg = '成功！';
        } else {
            $code = 200;
            $data = ['list' => [], 'count' => 0];
            $msg = '没有数据！';
        }

        return ToolsService::returnData($code, $data, $msg);
    }

    /**
     * coder
     *编码员排名 前5条
     * @group ranking
     * @bodyParam title string  开始时间  时间戳or时间
     * @bodyParam time string  结束时间
     * @bodyParam type string 按年(1)、季度(2)、月(3);
     * @bodyParam num int 几条数据
     *
     * @response {
     *  "code":200,
     *    "msg":"结果",
     *  "data": {
     *     "list":[{
     *          "total_error_medical":"累计有问题病案数",
     *          "scores":"分数",
     *          "name":"姓名",
     *          "error_proportion":"处理病案占比",
     *          "total_error":"处理病案数",
     *          "total_medical":"问题比例",
     *          "code_proportion":"编码问题占比",
     *          "code_error_medical":"编码问题病案数"
     *      }]
     *  },
     *  "time":123787842
     * }
     */
    public function coder(Request $request)
    {
        $start_time = $request->post("start_time");//时间戳
        $end_time = $request->post("end_time");
        $num = $request->post("limit", 10);
        $page = $request->post("page", 1);
        $isExport = $request->post("is_export", 0);
        $offset = ($page - 1) * $num;
        if (empty($start_time)) {
            $start_time = date('Y-01-01 00:00:00');
        }
        if (empty($end_time)) {
            $end_time = date('Y-12-31 23:59:59');
        }

        $result = CoderData::query()
            ->whereBetween('created_at', [$start_time, $end_time])
            ->groupBy('coder_id')
            ->get([
                'coder_id',
                DB::raw('sum(total_medical) as total_medical'),
                DB::raw('sum(total_error_medical) as total_error_medical'),
                DB::raw('sum(code_error_medical) as code_error_medical'),
                'scores',
                DB::raw('sum(error_medical) as error_medical'),
                'id']);
        if ($result) {
            $result = $result->toArray();
            $count = count($result);
            foreach ($result as &$item)//按id分组 算出总缺陷数量
            {
                $item['error_proportion'] = sprintf('%.2f', ($item['total_error_medical'] - $item['error_medical']) / $item['total_medical']);//处理病比
                $item['code_proportion'] = sprintf('%.2f', $item['code_error_medical'] / $item['total_medical']);//编码问题比
                $item['total_error'] = $item['total_error_medical'] - $item['error_medical'];//编码问题比
                $item['name'] = QualityService::getStaffInfo($item['coder_id'], 'name');
            }

            if ($isExport == 1) {
                $exportService = new FrontDataService();
                return $exportService->coderExport($result, $start_time, $end_time);
            }
            $res = $this->sortByKey($result, 'error_proportion', 2, $offset, $num);//排名
            $code = 200;
            $data = ['list' => $res, 'count' => $count];
            $msg = '成功！';
        } else {

            $code = 200;
            $data = ['list' => [], 'count' => 0];
            $msg = '没有数据！';
        }

        return ToolsService::returnData($code, $data, $msg);
    }

    /**
     * homeCensus
     * 首页统计
     * @bodyParam title string  开始时间  时间戳or时间
     * @bodyParam time string  结束时间
     * @bodyParam type integer 按年(1)、季度(2)、月(3);
     * @bodyParam type_id integer type选项;
     * @bodyParam qa_status integer 1（质控后）
     *
     * @response {
     *  "code":200,
     *    "msg":"结果",
     *  "data":{
     *       "total":"病案数量",
     *       "errorMedical":"总缺陷",
     *       "averageScore":"平均得分",
     *       "averageError":"平均缺陷",
     *       "highest_score":"优",
     *       "good":"良",
     *       "minimum_score":"差",
     *      "before":{
     *          "outstanding":"优秀率",
     *          "averageError":"平均缺陷",
     *          "averageScore":"平均分"
     *      },
     *      "last":{
     *          "outstanding":"优秀率",
     *          "averageError":"平均缺陷",
     *          "averageScore":"平均分"
     *      },
     *      "new":{
     *          "outstanding":"优秀率",
     *          "averageError":"平均缺陷",
     *          "averageScore":"平均分"
     *      }
     *  },
     * "time":123787842
     * }
     */
    public function homeCensus(Request $request)
    {
        $start_time = $request->post("start_time");//时间戳
        $end_time = $request->post("end_time");

        if (empty($start_time)) {
            $sy = date('Y');
            $sm = date('m');
        } else {
            $sy = date('Y', strtotime($start_time));
            $sm = date('m', strtotime($start_time));
        }
        if (empty($end_time)) {
            $ey = date('Y');
            $em = date('m');
        } else {
            $ey = date('Y', strtotime($end_time));
            $em = date('m', strtotime($end_time));
        }
        //图形数据
        $qa = $request->post("qu_status", 0);
        $years = [date('Y'),date('Y',strtotime('-1 year')),date('Y',strtotime('-2 year'))];
        if ($qa) {
            $field = [
                DB::raw('sum(total_medical) as total_medical'),
                DB::raw('sum(error_medical) as cumulative_medical'),
//                DB::raw('sum(average_score_qa) as average_score_qa'),
                DB::raw('sum(excellent_qa) as excellent_qa'),
                'year'
            ];
        } else {
            $field = [
                DB::raw('sum(total_medical) as total_medical'),
                DB::raw('sum(cumulative_medical) as cumulative_medical'),
//                DB::raw('sum(average_score) as average_score_qa'),
                DB::raw('sum(excellent) as excellent_qa'),
                'year'
            ];
        }
        $yearData = Count::query()->whereIn('year',$years)
            ->groupBy('year')
            ->get($field)
            ->keyBy('year');
        if ($yearData){
            $yearData = $yearData->toArray();
        }else{
            $yearData = [];
        }
        $result = [];
        foreach ($years as $item){
            $ydata = $yearData[$item] ?? [];
            if (empty($ydata)){
                $result[] = [
                    'outstanding' => 0,
                    'averageError' => 0,
                    'averageScore' => 0,
                    'year' => $item,
                ];
            }else{
                $result[] = [
                    'outstanding' => sprintf('%.2f', $ydata['excellent_qa'] / $ydata['total_medical']),
                    'averageError' => $ydata['total_medical'],
                    'averageScore' => $ydata['cumulative_medical'],
                    'year' => $ydata['year']
                ];
            }
        }
        //统计数据
        if ($qa) {
            $countField = [
                DB::raw('sum(total_medical) as total_medical'),
                DB::raw('sum(error_medical) as cumulative_medical'),
                DB::raw('sum(total_score_qa) as total_score_qa'),
                DB::raw('sum(excellent_qa) as excellent_qa'),
                DB::raw('sum(good_qa) as good_qa'),
                DB::raw('sum(fail_qa) as fail_qa'),
                DB::raw('sum(middle_qa) as middle_qa'),
            ];
        } else {
            $countField = [
                DB::raw('sum(total_medical) as total_medical'),
                DB::raw('sum(cumulative_medical) as cumulative_medical'),
                DB::raw('sum(total_score) as total_score_qa'),
                DB::raw('sum(excellent) as excellent_qa'),
                DB::raw('sum(good) as good_qa'),
                DB::raw('sum(fail) as fail_qa'),
                DB::raw('sum(middle) as middle_qa'),
            ];
        }
        $countData = Count::query()
            ->whereRaw("(year >= $sy and month >= $sm) or (year <= $ey and month <= $em)")
            ->first($countField);
        if ($countData){
            $countData = $countData->toArray();
        }else{
            $countData = [
                'total_medical' => 0,
                'cumulative_medical' => 0,
            ];
        }
        $data = [
            'total' => $countData['total_medical'] ?? 0,//总数量
            'averageError' => $countData['total_medical'] == 0 ? 0 : sprintf('%.2f', ($countData['cumulative_medical'] / $countData['total_medical']) * 100),//缺陷占比
            'errorMedical' => $countData['cumulative_medical'] ?? 0,//总缺陷
            'averageScore' => $countData['total_medical'] == 0 ? 0 : sprintf('%.2f',$countData['total_score_qa']/$countData['total_medical']),
            'highest_score' => $countData['good_qa'] ?? 0,
            'minimum_score' => $countData['middle_qa'] ?? 0,
            'good' => $countData['excellent_qa'] ?? 0,
            'middle' => $countData['fail_qa'] ?? 0,
            'before' => $result[2],
            'last' => $result[1],
            'new' => $result[0],
        ];
        $code = 200;
        $msg = '成功！';

        return ToolsService::returnData($code, $data, $msg);
    }


    /**
     * 处理条件
     * @param $model
     * @param $start_time
     * @param $end_time
     * @param $type
     * @return mixed
     */
    public function getCondition($model, $start_time, $end_time, $type)
    {
        $model = $model
            ->when($start_time, function ($query, $start_time) {
                //转换时间戳
                $year = date('Y', strtotime($start_time));
                $month = date('m', strtotime($start_time));
                return $query->where('year', '>=', $year)->where('month', '>=', $month);
            })
            ->when($end_time, function ($query, $end_time) {
                //转换时间戳
                $year = date('Y', strtotime($end_time));
                $month = date('m', strtotime($end_time));
                return $query->where('year', '<=', $year)->where('month', '<=', $month);
            });
        return $model;
    }

    /**
     * 二维数组按照键值排序
     * @param array $arr 待排序数组
     * @param string $key 键值
     * @param string $sort SORT_DESC SORT_ASC  排序
     * @return mixed
     */
    public function sortByKey($arr, $key, $sort, $start, $num)
    {
        if ($sort == 1) {
            array_multisort(array_column($arr, $key), SORT_DESC, $arr);
        } else {
            array_multisort(array_column($arr, $key), SORT_ASC, $arr);
        }

        $arr = array_slice($arr, $start, $num);

        return $arr;
    }

    /**
     * 返回数组指定键值组
     */
    public function keyGetval($arr, $key)
    {
        $arrs = [];
        foreach ($arr as $v) {
            $arrs[] = $v[$key];
        }
        return $arrs;
    }
}
