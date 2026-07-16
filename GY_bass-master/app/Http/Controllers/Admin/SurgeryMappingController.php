<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SurgeryMappingController extends Controller
{
    /**
     * add
     * 添加
     * @bodyParam code string required 手术编码
     * @bodyParam name string required 手术名称
     * @bodyParam three_code string required 映射到3.0手术编码
     * @bodyParam three_name string required 映射到3.0手术名称
     * @bodyParam three_type string required 映射到3.0类别
     * @bodyParam three_option string required 映射到3.0录入选项
     * @response {
     *  "code":0,
     * 	"msg":"结果"
     * }
     */
    public function add(Request $request)
    {
        //验证字段

        //插入数据库
        $data['code'] = $request->input('code');
        $data['name'] = $request->input('name');
        $data['three_code'] = $request->input('three_code');
        $data['three_name'] = $request->input('three_name');
        $data['three_type'] = $request->input('three_type');
        $data['three_option'] = $request->input('three_option');

        $res = Db::table('surgery_class_mapping')->insert($data);
        if($res){
            return ToolsService::returnAdmin(0,'','添加成功！');
        }

        return ToolsService::returnAdmin(0,'','添加失败！');

    }


    /**
     * getList
     * 获取列表
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
        $keyword = $request->input('keyword');

        $page = $request->input("page",1);
        $limit = $request->input("limit",10);

        $result = Db::table('surgery_class_mapping')
            ->select('*')
            ->orderBy('id','desc')
            ->paginate($limit, ['*'], 'page', $page);
        if($result->isEmpty())
        {
            $code = 0;
            $data = null;
            $msg = '没有数据！';
        }else{
            $code = 0;
            $data['list'] = $result->items();
            $data['count'] = $result->count();
            $msg = '成功！';
        }
        return ToolsService::returnAdmin($code, $data, $msg);

    }



    /**
     * edit
     * 编辑
     * @bodyParam id int required id
     * @bodyParam code string required 手术编码
     * @bodyParam name string required 手术名称
     * @bodyParam three_code string required 映射到3.0手术编码
     * @bodyParam three_name string required 映射到3.0手术名称
     * @bodyParam three_type string required 映射到3.0类别
     * @bodyParam three_option string required 映射到3.0录入选项
     * @response {
     *  "code":0,
     * 	"msg":"结果"
     * }
     */
    public function edit(Request $request)
    {
        //验证字段

        //插入数据库
        $data['id'] = $request->input('id');
        $data['code'] = $request->input('code');
        $data['name'] = $request->input('name');
        $data['level'] = $request->input('level');
        $data['type'] = $request->input('type');
        $data['option'] = $request->input('option');

        $res = Db::table('surgery_class_mapping')->update($data);
        if($res){
            return ToolsService::returnAdmin(0,'','编辑成功！');
        }

        return ToolsService::returnAdmin(0,'','编辑失败！');

    }

    /**
     * delSurgery
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
    public function delSurgery(Request $request)
    {
        $id = $request->input('id');

        $result = Db::table('surgery_class_mapping')->where(['id'=>$id])->first();

        if(!$result)
        {
            $code = 0;
            $data = null;
            $msg = '没有数据！';
            return ToolsService::returnAdmin($code, $data, $msg);
        }

        $res = Db::table('surgery_class_mapping')->where(['id'=>$id])->delete();

        if(!$res)
        {
            $code = 0;
            $data = null;
            $msg = '删除失败！';
        }else{
            $code = 0;
            $data = $result;
            $msg = '删除成功！';
        }
        return ToolsService::returnAdmin($code, $data, $msg);
    }

}
