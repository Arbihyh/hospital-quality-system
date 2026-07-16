<?php

namespace App\Http\Controllers\Api;

use App\Model\User;
use App\Model\Department;
use Illuminate\Http\Request;
use App\Model\CaseQualityPlan;
use App\Services\ToolsService;
use App\Model\CaseQualityPlanList;
use App\Http\Controllers\Controller;
use App\Model\ZY_BRRY;
use Illuminate\Support\Facades\Session;

class CaseQualityPlanController extends Controller
{
    /**
     * 保存质控计划基本信息
     *
     * @param  Request $request
     * @return array
     */
    public function save(Request $request)
    {
        $planId = $request->post('id');
        $data["type"] = $request->post('type'); // 质控计划类型，1:院级质控、2:科级质控
        if (!$data["type"]) {
            // return ToolsService::returnData(400, [], '质控计划类型不能为空');
        }
        $data["unit"] = $request->post('unit'); // 分配单元,1科室，2病区，3诊疗组
        if (!$data["unit"]) {
            return ToolsService::returnData(400, [], '分配单元不能为空');
        }
        $data["title"] = $request->post('title');
        if (!$data["title"]) {
            return ToolsService::returnData(400, [], '质控计划名称不能为空');
        }
        $data["is_random"] = $request->post('is_random'); // 是否随机，1是0否
        $data["AAC01_start_time"] = $request->post('AAC01_start_time');
        if ($data["AAC01_start_time"]) {
            $data["AAC01_start_time"] = date('Y-m-d 00:00:00', strtotime($data["AAC01_start_time"]));
        } else {
            return ToolsService::returnData(400, [], '开始时间不能为空');
        }
        $data["AAC01_end_time"] = $request->post('AAC01_end_time');
        if ($data["AAC01_end_time"]) {
            $data["AAC01_end_time"] = date('Y-m-d 23:59:59', strtotime($data["AAC01_end_time"]));
        } else {
            return ToolsService::returnData(400, [], '结束时间不能为空');
        }
        $data["nums"] = $request->post('nums');
        if ($data["is_random"] == 1 && !$data["nums"]) {
            return ToolsService::returnData(400, [], '抽取数量不能为空');
        }
        $data["end_time"] = $request->post('end_time');
        $data["start_time"] = $request->post('start_time');
        $data["start_time"] = strtotime($data["start_time"].' 00:00:00');
        $data["end_time"] = strtotime($data["end_time"].' 23:59:59');
        $data["add_user"] = $request->user()["id"];
        $data["created_at"] = date('Y-m-d H:i:s');

        if ($planId) {
            CaseQualityPlan::query()->where("id", $planId)->update($data);
        } else {
            $planId = CaseQualityPlan::query()->insertGetId($data);
        }

        // 院级质控，默认随机抽取
        if ($data["type"] == 1) {
            $data["is_random"] = 1;
        }
        
        // 科级质控，默认科室抽取
        if ($data["type"] == 2) {
            $data["unit"] = 1;
        }

        if ($data["is_random"] == 1) {

            // 清空历史数据重新生成
            CaseQualityPlanList::query()->where("plan_id", $planId)->delete();

            // 分配单元,1科室，2病区，3诊疗组
            if ($data["unit"] == 1) {
                // 获取所有科室信息
                $departments = Department::query()->where("type_id", 2)->get();

                foreach ($departments as $department) {
                    // 获取指定科室在出院时间范围内的住院号
                    $zyhList = ZY_BRRY::query()
                        ->where('BRKS', $department->dep_id)
                        ->whereBetween('AAC01', [$data["AAC01_start_time"].' 00:00:00', $data["AAC01_end_time"].' 23:59:59'])
                        ->pluck('ZYH'); // 假设住院号字段为 zyh
                    
                    // 随机从$zyhList中抽取$data["nums"]条数据，如果不满$data["nums"]条，则抽取所有数据
                    $selectedZyhList = $zyhList;
                    if (count($zyhList) > $data["nums"]) {
                        $selectedZyhList = $zyhList->random($data["nums"])->all();
                        // random() 返回的是集合，如果需要数组可以加 ->all()
                    }
                    $arr = [];
                    foreach ($selectedZyhList as $zyh) {
                        // 生成每一个科室对应的住院号数据
                        $arr[] = [
                            'plan_id' => $planId,
                            'KSID' => $department->dep_id,
                            'zyh' => $zyh
                        ];
                    }
                    CaseQualityPlanList::insert($arr);
                }
            } else if ($data["unit"] == 2) {
                // 获取所有病区信息
                $departments = Department::query()->where("type_id", 3)->get();

                foreach ($departments as $department) {
                    // 获取指定病区在出院时间范围内的住院号
                    $zyhList = ZY_BRRY::query()
                        ->where('BRBQ', $department->dep_id)
                        ->whereBetween('AAC01', [$data["AAC01_start_time"].' 00:00:00', $data["AAC01_end_time"].' 23:59:59'])
                        ->pluck('ZYH'); // 假设住院号字段为 zyh
                    
                    // 随机从$zyhList中抽取$data["nums"]条数据，如果不满$data["nums"]条，则抽取所有数据
                    $selectedZyhList = $zyhList;
                    if (count($zyhList) > $data["nums"]) {
                        $selectedZyhList = $zyhList->random($data["nums"])->all();
                        // random() 返回的是集合，如果需要数组可以加 ->all()
                    }
                    $arr = [];
                    foreach ($selectedZyhList as $zyh) {
                        // 生成每一个病区对应的住院号数据
                        $arr[] = [
                            'plan_id' => $planId,
                            'BQID' => $department->dep_id,
                            'zyh' => $zyh
                        ];
                    }
                    CaseQualityPlanList::insert($arr);
                }
            } elseif ($data["unit"] == 3) {
                // 获取所有诊疗组信息
                $departments = Department::query()->where("type_id", 4)->get();

                foreach ($departments as $department) {
                    // 获取指定诊疗组在出院时间范围内的住院号
                    $zyhList = ZY_BRRY::query()
                        ->where('ZLZZDM', $department->dep_id)
                        ->whereBetween('AAC01', [$data["AAC01_start_time"].' 00:00:00', $data["AAC01_end_time"].' 23:59:59'])
                        ->pluck('ZYH'); // 假设住院号字段为 zyh
                    
                    // 随机从$zyhList中抽取$data["nums"]条数据，如果不满$data["nums"]条，则抽取所有数据
                    $selectedZyhList = $zyhList;
                    if (count($zyhList) > $data["nums"]) {
                        $selectedZyhList = $zyhList->random($data["nums"])->all();
                        // random() 返回的是集合，如果需要数组可以加 ->all()
                    }
                    $arr = [];
                    foreach ($selectedZyhList as $zyh) {
                        // 生成每一个病区对应的住院号数据
                        $arr[] = [
                            'plan_id' => $planId,
                            'ZLZID' => $department->dep_id,
                            'zyh' => $zyh
                        ];
                    }
                    CaseQualityPlanList::insert($arr);
                }
            }
        }

        return ToolsService::returnData(200, [], '');
    }

    /**
     * 获取质控计划列表
     *
     * @param Request $request
     * @return array
     */
    public function getList(Request $request)
    {
        $page = $request->get('page', 1);
        $pageSize = $request->get('page_size', 10);

        $startTime = $request->get('start_time', '');
        $endTime = $request->get('end_time', '');
        $addUser = $request->get('add_user', '');
        $title = $request->get('title', '');
        $progress = $request->get('progress', '');  
        
        $list = CaseQualityPlan::getList($page, $pageSize, $startTime, $endTime, $addUser, $title, $progress);
        return ToolsService::returnData(200, $list, '');
    }

    public function delete(Request $request)
    {
        $id = $request->post('id');
        CaseQualityPlan::query()->where("id", $id)->delete();
        return ToolsService::returnData(200, [], '');
    }
}
