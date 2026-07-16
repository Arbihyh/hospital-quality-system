<?php
/*
 * Created by PhpStorm
 * User: moquan
 * Date: 2023/2/9
 * Time: 17:54
 */

namespace App\Http\Controllers\Admin;


use App\Http\Controllers\Controller;
use App\Model\Department;
use App\Model\ProblemFeedback;
use App\Model\User;
use App\Model\UserGroup;
use App\Model\UserLog;
use App\Services\CsvService;
use App\Services\ElasticsearchService;
use App\Services\MenuService;
use App\Services\TargetService;
use App\Services\ToolsService;
use App\Services\UserGroupService;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function userList(Request $request)
    {
        $page = $request->post("page");
        if (!is_numeric($page)) {
            $page = 1;
        }
        $length = $request->post("length");
        if (!is_numeric($length)) {
            $length = 16;
        }

        $realname = $request->post("realname",'');
        $name = $request->post("name");
        $group_id = $request->post("group_id");
        $data = UserService::userList($group_id, $name, $page, $length, $realname);
        return ToolsService::returnAdmin(0, $data);
    }

    public function addUser(Request $request)
    {
        $name = $request->post("name");
        if (empty($name)) {
            return ToolsService::returnAdmin(1, '',  '工号不能为空');
        }
        $pwd = $request->post("pwd");
        if (empty($pwd)) {
            return ToolsService::returnAdmin(1, '',  '密码不能为空');
        }
        $group_id = $request->post("group_id");
        if (empty($group_id)) {
            return ToolsService::returnAdmin(1, '',  '用户部门不能为空');
        }
        $phone = $request->post("phone", "");
        if(!empty($phone)){
            if(!phoneFormatCheck($phone)) {
                return ToolsService::returnAdmin(1, '',  '手机号码格式不正确');
            }
        }
//        $status = $request->post("status");
//        if ( !is_numeric($status + 0) ) {
//            return ToolsService::returnAdmin(1, '',  '用户状态不能为空');
//        }
        $realname = $request->post("realname", "");
        $data = User::findName($name);
        if ($data) {
            return ToolsService::returnAdmin(1,false,"账号已存在");
        }
        $depId = $request->post('dep_id',"");
//        $depId = !empty($depId) ? json_encode($depId,256) : '';
        $desc = $request->post('desc','');
        $state = UserService::addUser($name, $pwd, $group_id, $phone, $realname, $depId,$desc);
        if ($state) {
            return ToolsService::returnAdmin(0, '', '操作成功');
        } else {
            return ToolsService::returnAdmin(0, '', '操作成功');
        }
    }

    public function editUser(Request $request)
    {
        $id = $request->post("id");
        if (empty($id) || !is_numeric($id)) {
            return ToolsService::returnAdmin(1, '', '用户ID错误');
        }
        $name = $request->post("name");
        if (empty($name)) {
            return ToolsService::returnAdmin(1, '',  '工号不能为空');
        }
        $group_id = $request->post("group_id");
        if (empty($group_id)) {
            return ToolsService::returnAdmin(1, '',  '用户部门不能为空');
        }
        $phone = $request->post("phone", "");
        if(!empty($phone)){
            if(!phoneFormatCheck($phone)) {
                return ToolsService::returnAdmin(1, '',  '手机号码格式不正确');
            }
        }
//        $status = $request->post("status");
//        if ( !is_numeric($status + 0) ) {
//            return ToolsService::returnAdmin(0);
//        }
        $realname = $request->post("realname", "");
        $pwd = $request->post("pwd");
        $depId = $request->post('dep_id','');
//        $depId = !empty($depId) ? json_encode($depId,256) : '';
        $desc = $request->post('desc','');
        $state = UserService::editUser($id, $name, $pwd, $group_id, $phone, $realname, $depId, $desc);
        if ($state) {
            return ToolsService::returnAdmin(0, '', '操作成功');
        } else {
            return ToolsService::returnAdmin(0, '', '操作成功');
        }
    }

    public function userGroup()
    {
        $data = UserService::userGroup();
        return ToolsService::returnAdmin(0,$data);
    }


    public function delUser(Request $request)
    {
        $id = $request->post("id");
        if(!is_numeric($id)){
            return ToolsService::returnAdmin(1, '用户参数错误');
        }
        $state = User::delIdUser($id);
        if ($state) {
            return ToolsService::returnAdmin(0, '', '操作成功');
        } else {
            return ToolsService::returnAdmin(0, '', '操作成功');
        }
    }

    public function groupList(Request $request)
    {
        $page = $request->post("page");
        if (!is_numeric($page)) {
            $page = 1;
        }
        $len = $request->post("len");
        if (!is_numeric($len)) {
            $len = 15;
        }
        $data = UserGroupService::getUserGroupList($page, $len);
        return ToolsService::returnAdmin(0, $data);
    }


    public function delUserGroup(Request $request)
    {
        $userGroupIds = [1];
        $id = $request->post("id");
        if(!is_numeric($id)){
            return ToolsService::returnAdmin(1, '', '组参数错误');
        }
        if(in_array($id,$userGroupIds)){
            return ToolsService::returnAdmin(1, '', '组参数错误');
        }
        $userDate = User::getWhereGroupId($id,['id','name']);
        if($userDate){
            return ToolsService::returnAdmin(1, '', '使用中不能删除');
        }
        $state = UserGroup::delIdUserGroup($id);
        if ($state) {
            return ToolsService::returnAdmin(0, '', '操作成功');
        } else {
            return ToolsService::returnAdmin(0, '', '操作成功');
        }
    }

    public function addUserGroup(Request $request)
    {
        $groupName = $request->post("name");
        if (empty($groupName)) {
            return ToolsService::returnAdmin(1, '', '用户部门名称不能为空');
        }
        $desc = $request->post("desc", '');
//        if (empty($desc)) {
//            return ToolsService::returnAdmin(1, '', '描述不能为空');
//        }
        $role = $request->post("role");
        if (!is_array($role) || count($role) < 1) {
            return ToolsService::returnAdmin(1, '', '权限不能为空');
        }
        $id = $request->user()["id"];
        $state = UserGroupService::add($groupName, $desc, $role, $id);
        if ($state) {
            return ToolsService::returnAdmin(0, '', '操作成功');
        } else {
            return ToolsService::returnAdmin(0, '', '操作成功');
        }
    }

    public function editUserGroup(Request $request)
    {
        $id = $request->post("id");
        if (empty($id) || !is_numeric($id)) {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }
        $groupName = $request->post("name");
        if (empty($groupName)) {
            return ToolsService::returnAdmin(1, '', '用户部门名称不能为空');
        }
        $role = $request->post("role");
        if (!is_array($role) || count($role) < 1) {
            return ToolsService::returnAdmin(1, '', '权限参数错误');
        }
        $desc = $request->post("desc");
//        if (empty($desc)) {
//            return ToolsService::returnAdmin(1, '', '描述不能为空');
//        }
        $state = UserGroupService::edit($id, $groupName, $desc, $role);
        if ($state) {
            return ToolsService::returnAdmin(0, '', '操作成功');
        } else {
            return ToolsService::returnAdmin(0, '', '操作成功');
        }
    }

    public function userLogList(Request $request)
    {
        $page = $request->post("page", 1);
        $limit = $request->input("limit",10);
        $name = $request->post('name');
        $startTime = $request->post('start_time','');
        $endTime = $request->post('end_time','');
        $query = UserLog::query();
        $where = [];
        if(!empty($name)){
            $where[] = ['username', 'like', '%'.$name.'%'];
        }
        // 时间搜索条件
        if ($startTime && $endTime) {
            $startTime = date('Y-m-d', strtotime($startTime)).' 00:00:00';
            $endTime = date('Y-m-d', strtotime($endTime)).' 23:59:59';
            $where[] = ['created_at', '>=', $startTime];
            $where[] = ['created_at', '<=', $endTime];
        } elseif ($startTime) {
            $startTime = date('Y-m-d', strtotime($startTime)).' 00:00:00';
            $where[] = ['created_at', '>=', $startTime];
        } elseif ($endTime) {
            $endTime = date('Y-m-d', strtotime($endTime)).' 23:59:59';
            $where[] = ['created_at', '<=', $endTime];
        }
        if($where){
            $query->where($where);
        }
        $count = $query->where($where)->count();
        $list = $query->orderBy('id','desc')->paginate($limit, ['*'], 'page', $page);

        return ToolsService::returnAdmin(0, [
            'list'=> $list->items(),
            'count' => $count
        ]);
    }

    public function userSearchLogList(Request $request)
    {
        $type = $request->post("type", '');
        $name = $request->post("name", '');
        $depId = $request->post("dep_id", '');
        $keyword = $request->post('keyword','');
        $startDate = $request->post("start_time", '');
        $endDate = $request->post("end_time", '');
        $page = $request->post("page", 1);
        $limit = $request->post("limit",10);

        $esService = new ElasticsearchService('user_search_log');
        $must = [];
        if ($type) {
            $must[] = ['term' => ['type'=>$type]];
        }
        if ($name) {
            $must[] = ['match_phrase' => ['username'=>$name]];
        }
        if ($depId) {
            $must[] = ['term' => ['dep_id'=>$depId]];
        }
        if ($keyword) {
            $must[] = ['match_phrase' => ['content'=>$keyword]];
        }
        if ($startDate && $endDate) {
            $startTime = date('Y-m-d',strtotime($startDate)).' 00:00:00';
            $endTime = date('Y-m-d',strtotime($endDate)).' 23:59:59';
            $must[] = [
                'range' => [
                    'created_at' => [
                        'gte' => $startTime,
                        'lte' => $endTime
                    ]
                ]
            ];
        } elseif ($startDate) {
            $startTime = date('Y-m-d',strtotime($startDate)).' 00:00:00';
            $must[] = [
                'range' => [
                    'created_at' => [
                        'gte' => $startTime
                    ]
                ]
            ];
        } elseif ($endDate) {
            $endTime = date('Y-m-d',strtotime($endDate)).' 23:59:59';
            $must[] = [
                'range' => [
                    'created_at' => [
                        'lte' => $endTime
                    ]
                ]
            ];
        }
        $params = $esService->clearMust()
            ->queryByMustBatch($must)
            ->orderBy('created_at','desc')
            ->paginate($page,$limit)
            ->trackTotalHits()
            ->getParams();
        $restful = app('es')->search($params);
        $data = $esService->getDataByEs($restful);

        // 获取科室
        $depList = Department::query()->pluck('dep_name','dep_id')->toArray();
        
        $userList = User::query()->pluck('realname','id')->toArray();
        $returnData = [];
        if (!empty($data[0])) {
            $targetSetvice = new TargetService();
            foreach ($data[0] as $value) {
                // 搜索内容解析
                $content = $targetSetvice->serachContentAnalysis($value['content']);
                $value['content'] = $content;
                $depName = '质管办';
                if (!empty($value['dep_id'])) {
                    $depName = $depList[$value['dep_id']] ?? $value['dep_id'];
                }
                $value['dep_name'] = $depName;
                $value['realname'] = $userList[$value['user_id']] ?? $value['username'];
                $returnData[] = $value;
            }
        }

        return ToolsService::returnAdmin(0, [
            'list'=> $returnData,
            'count' => $data[1] ?? 0
        ]);
    }

    public function userSearchLogExport(Request $request)
    {
        $type = $request->post("type", '');
        $name = $request->post("name", '');
        $depId = $request->post("dep_id", '');
        $keyword = $request->post('keyword','');
        $startDate = $request->post("start_time", '');
        $endDate = $request->post("end_time", '');

        $esService = new ElasticsearchService('user_search_log');
        $must = [];
        if ($type) {
            $must[] = ['term' => ['type'=>$type]];
        }
        if ($name) {
            $must[] = ['match_phrase' => ['username'=>$name]];
        }
        if ($depId) {
            $must[] = ['term' => ['dep_id'=>$depId]];
        }
        if ($keyword) {
            $must[] = ['match_phrase' => ['content'=>$keyword]];
        }
        if ($startDate && $endDate) {
            $startTime = date('Y-m-d',strtotime($startDate)).' 00:00:00';
            $endTime = date('Y-m-d',strtotime($endDate)).' 23:59:59';
            $must[] = [
                'range' => [
                    'created_at' => [
                        'gte' => $startTime,
                        'lte' => $endTime
                    ]
                ]
            ];
        } elseif ($startDate) {
            $startTime = date('Y-m-d',strtotime($startDate)).' 00:00:00';
            $must[] = [
                'range' => [
                    'created_at' => [
                        'gte' => $startTime
                    ]
                ]
            ];
        } elseif ($endDate) {
            $endTime = date('Y-m-d',strtotime($endDate)).' 23:59:59';
            $must[] = [
                'range' => [
                    'created_at' => [
                        'lte' => $endTime
                    ]
                ]
            ];
        }
        $params = $esService->clearMust()
            ->queryByMustBatch($must)
            ->orderBy('created_at','desc')
            ->paginate(1,1000000)
            ->trackTotalHits()
            ->getParams();
        $restful = app('es')->search($params);
        $data = $esService->getDataByEs($restful);

        // 获取科室
        $depList = config('dictionaries.deportment');

        $targetSetvice = new TargetService();

        $excelData[] = ['序号','搜索名称','请求参数','账号','姓名','科室','请求ip','请求来源'];
        $index = 1;
        $userList = User::query()->pluck('realname','id')->toArray();
        foreach ($data[0] as $value) {
            $depName = '';
            if ($value['dep_id']) {
                $depName = $depList[$value['dep_id']];
            }

            $realname = '';
            if (!empty($value['user_id']) && !empty($value['is_code'])) {
                $realname = $userList[$value['user_id']] ?? $value['username'];
            } else {

            }

            // 搜索内容解析
            $content = $targetSetvice->serachContentAnalysis($value['content']);

            $excelData[] = [
                'id' => $index,
                'title' => $value['title'],
                'content' => $content,
                'username' => $value['username'],
                'realname' => $realname,
                'keshi' => $depName,
                'ip' => $value['ip'],
                'user_agent' => $value['user_agent'],
            ];
            $index++;
        }

        $csv = new CsvService();
        $csv->filename = $csv->charset('科研探索日志', 'UTF-8');

        return $csv->export($excelData, false);
    }

    public function getDeportmentList(Request $request)
    {
        $depList = Department::getDepartmentData();

        return ToolsService::returnAdmin(0, [
            'list'=> $depList,
            'count' => count($depList)
        ]);
    }

    public function feedbackList(Request $request)
    {
        $typeId = $request->post('type_id','');     // 反馈类型ID
        $depId = $request->post('dep_id','');       // 科室ID
        $userName = $request->post('user_name',''); // 反馈人
        $startTime= $request->post('start_time','');// 开始时间
        $endTime = $request->post('end_time','');   // 结束时间
        $page = $request->post('page',1);
        $pageSize = $request->post('page_size',10);

        // 查询条件
        $where = [];
        if ($typeId) {
            $where[] = ['type_id','=',$typeId];
        }
        if ($depId) {
            $where[] = ['dep_id','=',$depId];
        }
        if ($userName) {
            $where[] = ['user_name','like',"%".$userName."%"];
        }
        if ($startTime) {
            $startTime = date('Y-m-d',strtotime($startTime)).' 00:00:00';
            $where[] = ['created_at','>',$startTime];
        }
        if ($endTime) {
            $endTime = date('Y-m-d',strtotime($endTime)).'23:59:59';
            $where[] = ['created_at','<',$endTime];
        }

        // 分页查询
        $data = ProblemFeedback::query()
            ->where($where)
            ->paginate($pageSize,['*'],'page',$page)
            ->toArray();

        // 判断是否有数据，有则解析
        if (!empty($data['data'])) {
            $depList = config('dictionaries.deportment');
            foreach ($data['data'] as &$value) {
                $value['type_name'] = ProblemFeedback::TYPE_LIST[$value['type_id']] ?? '';
                $value['dep_name'] = $depList[$value['dep_id']] ?? '';
            }
        }

        return ToolsService::returnAdmin(0, [
            'list'=> $data['data'],
            'count' => $data['total']
        ]);
    }

    public function editPassword(Request $request)
    {
        $id = $request->post('id','');
        $password = $request->post('password','');

        if (!trim($id) || !trim($password)) {
            return ToolsService::returnAdmin(1, '', '参数错误');
        }

        $res = User::edit(['id'=>$id],['password'=>$password]);
        if ($res) {
            return ToolsService::returnAdmin(0, '', '操作成功');
        } else {
            return ToolsService::returnAdmin(0, '', '操作成功');
        }
    }


}
