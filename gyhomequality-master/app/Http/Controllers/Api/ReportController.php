<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Http\Service\ExportService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * 上报历史
     * @bodyParam  string start_time 开始时间  时间戳or时间
     * @bodyParam  string end_time 结束时间
     * @bodyParam int type 上报平台（1：国考；2：卫统；3：医保）
     *  @response {
     *  "code":200,
     * 	"msg":"结果",
     *  "data": {
     *      "list":[{
     *          "hospital_time":"出院日期",
     *          "hospital_num":"出院人次",
     *          "medical_num":"病案数量",
     *          "report_num":"上报数量",
     *          "report_probability":"上报率",
     *          "report_platform":"上报平台"
     *      }],
     *      "page":1
     *  },
     * "time":123787842
     * }
     */
    public function history(Request $request)
    {
        $start_time =  $request->post("start_time");//时间戳
        $end_time =  $request->post("end_time");

        $type =  $request->post("int_type");
        $isExport = $request->post("is_export",0);
        if ($isExport == 1) {
            $exportService = new ExportService();
            return $exportService->historyExport($start_time,$end_time,$type);
        }

        $page = $request->post("page",1);
        $limit = $request->post("limit",10);

        $result = Db::table('reporting_history')
            //->where('report_platform',$type)
            ->when($start_time,function($query, $start_time){
                return $query->where('hospital_time', '>=', $start_time);
            })
            ->when($end_time,function($query, $end_time){
                return $query->where('hospital_time', '<=', $end_time);
            })
            ->select('hospital_time','hospital_num','medical_num','report_num','report_probability','report_platform')
            ->orderBy('id','asc')
            ->paginate($limit, ['*'], 'page', $page);
        if($result->isEmpty())
        {
            $code = 200;
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
