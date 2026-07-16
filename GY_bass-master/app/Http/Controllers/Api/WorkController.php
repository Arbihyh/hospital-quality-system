<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Services\ToolsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

use App\Model\Workrecord;
use App\Model\Workrepoet;

class WorkController extends Controller
{
    /**
     * record
     * @group work
     * @bodyParam title string start_time 业务时间-开始时间  时间戳or时间
     * @bodyParam time string end_time 业务实践-结束时间
     * @bodyParam limit string end_time 每页的数据条数
     * @bodyParam page string end_time 当前第几页
     *
     * @response {
     *  "code":200,
     * 	"msg":"结果",
     *    "data":{
     *         "list": [{
     *              "id":"",
     *              "AAA28":"病案号",
     *              "time":"业务时间",
     *              "desc":"业务操作详情",
     *              "operator":"业务操作人"
     *          }],
     *        "count":1
     *     }
     * }
     */
    public function record(Request $request) {
        $start_time =  $request->post("start_time");//时间戳
        $end_time =  $request->post("end_time");

        $page = $request->post("page",1);
        $limit = $request->post("limit",10);

        $result = Workrecord::query()
            ->when($start_time,function($query, $start_time){
                return $query->where('time', '>=', $start_time);
            })
            ->when($end_time,function($query, $end_time){
                return $query->where('time', '<=', $end_time);
            })
            ->select('AAA28','time','desc','operator')
            ->orderBy('time','asc')
            ->paginate($limit, ['*'], 'page', $page);
        if($result->isEmpty())
        {
            $code = 400;
            $data = null;
            $msg = '没有数据！';
        }else{
            $code = 200;
            $data['list'] = $result->items();
            $data['count'] = $result->count();
            $msg = '成功！';
        }
        return ToolsService::returnData($code, $data, $msg);
    }

    /**
     * repoet
     * @group work
     * @bodyParam title string start_time 业务时间-开始时间  时间戳or时间
     * @bodyParam time string end_time 业务实践-结束时间
     * @bodyParam limit string end_time 每页的数据条数
     * @bodyParam page string end_time 当前第几页
     *
     * @response {
     *  "code":200,
     * 	"msg":"结果",
     *    "data":{
     *         "list": [{
     *              "id":"",
     *              "type":"报告类型",
     *              "time":"数据范围时间",
     *          }],
     *        "count":1
     *     }
     * }
     */
    public function repoet(Request $request) {
        $start_time =  $request->post("start_time");//时间戳
        $end_time =  $request->post("end_time");
        $type =  $request->post("type");
        $page = $request->input("page",1);
        $limit = $request->input("limit",10);

        $result = Workrepoet::query()
            ->when($start_time,function($query, $start_time){
                return $query->where('recode_time', '>=', $start_time);
            })
            ->when($end_time,function($query, $end_time){
                return $query->where('recode_time', '<=', $end_time);
            })
            ->when($type,function($query, $type){
                return $query->where(['type'=>$type]);
            })
            ->paginate($limit, ['*'], 'page', $page);

        if($result->isEmpty())
        {
            $code = 400;
            $data = null;
            $msg = '没有数据！';
        }else{
            $code = 200;
            $data['list'] = $result->items();
            $data['count'] = $result->count();
            $msg = '成功！';
        }

        return ToolsService::returnData($code, $data, $msg);
    }


}
