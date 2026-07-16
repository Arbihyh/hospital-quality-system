<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\ErrorRule;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ErrorRuleController extends Controller
{
    /**
     * addErrorRule
     * 添加规则
     * @bodyParam auth string required 验证字段
     * @bodyParam field string required 验证字段名称
     * @bodyParam rule string required 验证规则
     * @bodyParam relation string required 关联字段
     * @bodyParam relation_rule string required 关联规则
     * @bodyParam level string required 错误等级
     * @bodyParam desc string required 规则描述
     * @bodyParam down int required 扣分
     * @bodyParam type int required 缺陷分类0患者基本信息1诊疗信息2费用信息
     * @bodyParam error_type int required 缺陷类型0逻辑性1规范性2编码
     * @response {
     *  "code":0,
     * 	"msg":"结果"
     * }
     */
    public function addErrorRule(Request $request)
    {
        //验证字段

        //插入数据库
        $data['auth'] = $request->input('auth');
        $data['field'] = $request->input('field');
        $data['rule'] = $request->input('rule');
        $data['relation'] = $request->input('relation');
        $data['relation_rule'] = $request->input('relation_rule');
        $data['level'] = $request->input('level');
        $data['bmy_level'] = $request->input('bmy_level');
        $data['desc'] = $request->input('desc');
        $data['down'] = $request->input('down');
        $data['type'] = $request->input('type');
        $data['error_type'] = $request->input('error_type');
        $data['category'] = $request->input('category');
        $data['status'] = $request->input('status');
        $data['ZKDX'] = $request->input('ZKDX', 0);
        $data['ZKFL'] = $request->input('ZKFL', 0);
        $data['BZ'] = $request->input('BZ');
        $data['node'] = $request->input('node');

        $res = Db::table('error_rule')->insert($data);
        if ($res) {
            return ToolsService::returnAdmin(0, '', '添加成功！');
        }

        return ToolsService::returnAdmin(1, '', '添加失败！');
    }

    /**
     * getList
     * 获取规则列表
     * @group work
     * @bodyParam limit string end_time 每页的数据条数
     * @bodyParam page string end_time 当前第几页
     *
     * @response {
     *  "code":0,
     * 	"msg":"结果",
     *    "data":{
     *         "list": [{
     *              "id":""
     *          }],
     *        "count":1
     *     }
     * }
     */
    public function getList(Request $request)
    {
        $page = $request->input("page", 1);
        $limit = $request->input("limit", 10);
        $auth = $request->input('auth');
        $field = $request->input('field');
        $desc = $request->input('desc');
        $level = $request->input('level');
        $bmyLevel = $request->input('bmy_level');
        $category = $request->input('category');
        $type = $request->input('type');
        $errorType = $request->input('errorType');
        $ZKDX = $request->input('ZKDX', '');
        $ZKFL = $request->input('ZKFL', '');
        $node = $request->input('node', '');

        $where = [];
        $db = Db::table('error_rule')->whereNull('deleted_at');
        if (!empty($auth)) {
            $where[] = ['auth', 'like', '%' . $auth . '%'];
        }
        if (!empty($field)) {
            $where[] = ['field', 'like', '%' . $field . '%'];
        }
        if (is_numeric($level)) {
            $where[] = ['level', '=', $level];
        }
        if (is_numeric($bmyLevel)) {
            $where[] = ['bmy_level', '=', $bmyLevel];
        }
        if (is_numeric($category)) {
            $where[] = ['category', '=', $category];
        }
        if (is_numeric($type)) {
            $where[] = ['type', '=', $type];
        }
        if (is_numeric($errorType)) {
            $where[] = ['error_type', '=', $errorType];
        }
        if (!empty($desc)) {
            $where[] = ['desc', 'like', '%' . $desc . '%'];
        }

        // 质控对象
        if ($ZKDX !== null && $ZKDX !== '') {
            $where[] = ['ZKDX', '=', $ZKDX];
        }
        // 质控分类
        if ($ZKFL !== null && $ZKFL !== '') {
            $where[] = ['ZKFL', '=', $ZKFL];
        }
        // 运行节点：前端传入数字（0=终末，1=运行），需转为中文再匹配数据库
        $nodeMap = ['0' => '终末', '1' => '运行'];
        if (!empty($node)) {
            if (strpos($node, '、') !== false) {
                // 包含顿号，按顿号分隔后逐个映射为中文
                $nodeArr = explode('、', $node);
                foreach ($nodeArr as $item) {
                    $item = trim($item);
                    if (isset($nodeMap[$item])) {
                        $db = $db->where('node', 'like', '%' . $nodeMap[$item] . '%');
                    }
                }
            } else {
                // 单个值，转为中文后模糊匹配
                $nodeCn = $nodeMap[trim($node)] ?? $node;
                $where[] = ['node', 'like', '%' . $nodeCn . '%'];
            }
        }
        $status = $request->post("status");
        if ((is_int($status) || is_string($status)) && in_array($status, [0, 1])) {
            $where[] = ['status', '=', $status];
        }
        $count = $db->where($where)->count();
        $result = $db->select('*')
            ->orderBy('id', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        // 反映射：将数据库中的中文node转回数字返回给前端
        $nodeReverseMap = array_flip($nodeMap); // ['终末' => '0', '运行' => '1']
        if ($result->isEmpty()) {
            $code = 0;
            $data = [];
            $msg = '没有数据！';
        } else {
            $code = 0;
            $list = $result->items();
            foreach ($list as &$item) {
                if (!empty($item->node)) {
                    if (strpos($item->node, '、') !== false) {
                        // 包含顿号，逐个反转为数字
                        $parts = explode('、', $item->node);
                        $numParts = [];
                        foreach ($parts as $part) {
                            $part = trim($part);
                            $numParts[] = $nodeReverseMap[$part] ?? $part;
                        }
                        $item->node = implode('、', $numParts);
                    } else {
                        // 单个值反转，转为字符串
                        $item->node = (string)($nodeReverseMap[trim($item->node)] ?? $item->node);
                    }
                }
            }
            unset($item);
            $data['list'] = $list;
            $data['count'] = $count;
            $msg = '成功！';
        }
        return ToolsService::returnAdmin($code, $data, $msg);
    }

    /**
     * editErrorRule
     * 编辑规则
     * @bodyParam id int required id
     * @bodyParam auth string required 验证字段
     * @bodyParam field string required 验证字段名称
     * @bodyParam rule string required 验证规则
     * @bodyParam relation string required 关联字段
     * @bodyParam relation_rule string required 关联规则
     * @bodyParam level string required 错误等级
     * @bodyParam desc string required 规则描述
     * @bodyParam down int required 扣分
     * @bodyParam type int required 缺陷分类0患者基本信息1诊疗信息2费用信息
     * @bodyParam error_type int required 缺陷类型0逻辑性1规范性2编码
     * @response {
     *  "code":0,
     * 	"msg":"结果"
     * }
     */
    public function editErrorRule(Request $request)
    {
        $id = $request->post('id');
        $data['auth'] = $request->post('auth') ?? '';
        $data['field'] = $request->post('field') ?? '';
        $data['level'] = $request->post('level') ?? 0;
        $data['bmy_level'] = $request->post('bmy_level') ?? 0;
        $data['desc'] = $request->post('desc') ?? '';
        $data['down'] = $request->post('down') ?? 1;
        $data['type'] = $request->post('type') ?? 0;
        $data['error_type'] = $request->input('error_type') ?? 0;
        $data['category'] = $request->input('category') ?? 0;
        $data['status'] = $request->input('status') ?? 0;
        $data['ZKDX'] = $request->input('ZKDX', 0);
        $data['ZKFL'] = $request->input('ZKFL', 0);
        $data['BZ'] = $request->input('BZ');
        $node = '';
        // 映射关系：0=终末，1=运行
        $nodeMap = ['0' => '终末', '1' => '运行'];
        $inputNode = $request->input('node', '');
        if (strpos($inputNode, '、') !== false) {
            // 包含顿号，按顿号分隔后逐个映射
            $nodeArr = explode('、', $inputNode);
            $nodeResult = [];
            foreach ($nodeArr as $item) {
                $item = trim($item);
                if (isset($nodeMap[$item])) {
                    $nodeResult[] = $nodeMap[$item];
                }
            }
            $node = implode('、', $nodeResult);
        } else {
            // 不包含顿号，直接按映射转换
            $inputNode = trim($inputNode);
            $node = $nodeMap[$inputNode] ?? '';
        }
        $data['node'] = $node;

        // 验证规则
        $rule = $request->post('rule', '');
        if ($rule) {
            $data['rule'] = $rule;
        }
        // 关联字段
        $relation = $request->post('relation', '');
        if ($relation) {
            $data['relation'] = $relation;
        }
        // 关联规则
        $relation_rule = $request->post('relation_rule', '');
        if ($relation_rule) {
            $data['relation_rule'] = $relation_rule;
        }

        $res = Db::table('error_rule')->where('id', $id)->update($data);
        if ($res !== false) {
            return ToolsService::returnAdmin(0, false, '编辑成功！');
        }

        return ToolsService::returnAdmin(1, false, '编辑失败！');
    }

    /**
     * delErrorRule
     * 删除
     * @bodyParam id int required id
     * @response {
     *  "code":0,
     * 	"msg":"结果",
     *    "data":{
     *
     *     }
     * }
     */
    public function delErrorRule(Request $request)
    {
        $id = $request->input('id');

        $result = Db::table('error_rule')->where(['id' => $id])->first();
        if (!$result) {
            $code = 0;
            $data = null;
            $msg = '没有数据！';
            return ToolsService::returnAdmin($code, $data, $msg);
        }
        $result->deleted_at = date('Y-m-d H:i:s');
        $res = $result->save();

        if (!$res) {
            $code = 1;
            $data = null;
            $msg = '删除失败！';
        } else {
            $code = 0;
            $data = $result;
            $msg = '删除成功！';
        }
        return ToolsService::returnAdmin($code, $data, $msg);
    }

    //更新状态
    public function updateStatus(Request $request)
    {
        $id = $request->post("id");
        if (!is_numeric($id)) {
            return ToolsService::returnAdmin(401);
        }
        $status = $request->post("status");
        if (!in_array($status, [0, 1])) {
            return ToolsService::returnAdmin(401, [], 'status is failed');
        }
        $model = ErrorRule::find($id);
        $model->status = $status;
        $state = $model->save();
        if ($state) {
            return ToolsService::returnAdmin(0, [], '操作成功');
        } else {
            return ToolsService::returnAdmin(1, [], '操作失败');
        }
    }

    //更新编码员验证等级状态
    public function updateBmyLevel(Request $request)
    {
        $id = $request->post("id");
        if (!is_numeric($id)) {
            return ToolsService::returnAdmin(401);
        }
        $status = $request->post("status");
        if (!in_array($status, [0, 1])) {
            return ToolsService::returnAdmin(401, [], 'status is failed');
        }
        $model = ErrorRule::find($id);
        $model->bmy_level = $status;
        $state = $model->save();
        if ($state) {
            return ToolsService::returnAdmin(0, [], '操作成功');
        } else {
            return ToolsService::returnAdmin(1, [], '操作失败');
        }
    }
}
