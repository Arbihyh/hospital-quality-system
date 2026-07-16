<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Services\ToolsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;


class WorkrepoetController extends Controller
{
    /**
     * index
     * @group Index
     * @bodyParam type int  报告类型
     * @bodyParam start_time string  业务时间-开始时间  时间戳or时间
     * @bodyParam end_time string  业务实践-结束时间
     * @bodyParam limit int  每页的数据条数
     * @bodyParam page int  当前第几页
     *
     * @response {
     *  "code":200,
     * 	"msg":"结果"
     *  "data": {
     *      数据
     *  }
     * }
     * @return array
     */
    public function index(Request $request) {
        $start_time =  $request->post('start_time');//时间戳
        $end_time =  $request->post('end_time');
        $type =  $request->post('type');
        $page = $request->input('page') ? : 1;
        $limit = $request->input('limit') ? : 10;

        $result = DB::table('work_record')
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

		$res = $result->toArray()['data'];//判断有没有数据
		if($res){
		    $code = 200;
            $data = $result;
            $msg = '成功！';
		}else{
		    $code = 400;
            $data = null;
            $msg = '没有数据！';
		}

        return ToolsService::returnData($code, $data, $msg);
    }


}
