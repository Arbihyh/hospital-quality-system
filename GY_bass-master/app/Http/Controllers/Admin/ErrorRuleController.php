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
        $data['desc'] = $request->input('desc');
        $data['down'] = $request->input('down');
        $data['type'] = $request->input('type');
        $data['error_type'] = $request->input('error_type');
        $data['category'] = $request->input('category');
        $data['status'] = $request->input('status');

        $res = Db::table('error_rule')->insert($data);
        if($res){
            return ToolsService::returnAdmin(0,'','添加成功！');
        }

        return ToolsService::returnAdmin(1,'','添加失败！');

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

        $page = $request->input("page",1);
        $limit = $request->input("limit",10);
        $auth = $request->input('auth');
        $field = $request->input('field');
        $desc = $request->input('desc');
        $level = $request->input('level');
        $category = $request->input('category');
        $type = $request->input('type');
        $errorType = $request->input('errorType');

        $where = [];
        $db = Db::table('error_rule');
        if(!empty($auth)){
            $where[] = ['auth', 'like', '%'.$auth.'%'];
        }
        if(!empty($field)){
            $where[] = ['field', 'like', '%'.$field.'%'];
        }
        if(is_numeric($level)){
            $where[] = ['level', '=', $level];
        }
        if(is_numeric($category)){
            $where[] = ['category', '=', $category];
        }
        if(is_numeric($type)){
            $where[] = ['type', '=', $type];
        }
        if(is_numeric($errorType)){
            $where[] = ['error_type', '=', $errorType];
        }
        if(!empty($desc)){
            $where[] = ['desc', 'like', '%'.$desc.'%'];
        }
        $status = $request->post("status");
        if((is_int($status) || is_string($status)) && in_array($status, [0,1])){
            $where[] = ['status', '=', $status];
        }
        $count = $db->where($where)->count();
        $result = $db->select('*')
            ->orderBy('id','desc')
            ->paginate($limit, ['*'], 'page', $page);
        if($result->isEmpty())
        {
            $code = 0;
            $data = [];
            $msg = '没有数据！';
        }else{
            $code = 0;
            $data['list'] = $result->items();
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
        $data['rule'] = $request->post('rule') ?? '';
        $data['relation'] = $request->post('relation') ?? '';
        $data['relation_rule'] = $request->post('relation_rule') ?? '';
        $data['level'] = $request->post('level') ?? 0;
        $data['desc'] = $request->post('desc') ?? '';
        $data['down'] = $request->post('down') ?? 1;
        $data['type'] = $request->post('type') ?? 0;
        $data['error_type'] = $request->input('error_type') ?? 0;
        $data['category'] = $request->input('category') ?? 0;
        $data['status'] = $request->input('status') ?? 0;
        $res = Db::table('error_rule')->where('id',$id)->update($data);

        if($res){
            return ToolsService::returnAdmin(0,false,'编辑成功！');
        }

        return ToolsService::returnAdmin(1,false,'编辑失败！');
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

        $result = Db::table('error_rule')->where(['id'=>$id])->first();

        if(!$result)
        {
            $code = 0;
            $data = null;
            $msg = '没有数据！';
            return ToolsService::returnAdmin($code, $data, $msg);
        }

        $res = Db::table('error_rule')->where(['id'=>$id])->delete();

        if(!$res)
        {
            $code = 1;
            $data = null;
            $msg = '删除失败！';
        }else{
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
        if(!is_numeric($id)){
            return ToolsService::returnAdmin(401);
        }
        $status = $request->post("status");
        if(!in_array($status, [0,1])){
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

}
