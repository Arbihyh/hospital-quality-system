<?php

namespace App\Http\Controllers\Api;

use App\Exports\ExportData;
use App\Http\Controllers\Controller;
use App\Model\ErrorData;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DataExport;

class ExcelController extends Controller
{
    /**
     * 导出
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function excel_error(Request $request)
    {
        $type = $request->post('type', 1);
        $start_time = $request->post("start_time",date('Y-m-d H:i:s'));
        $end_time = $request->post("end_time",date('Y-m-d H:i:s'));

        $query = ErrorData::query();
        if ($type == 1) {
            //年
            $syear = date('Y',strtotime($start_time));
            $eyear = date('Y',strtotime($end_time));
            $query->whereBetween('year',[$syear,$eyear]);
        } else {
            //月
            $smonth = date('m',strtotime($start_time));
            $emonth = date('m',strtotime($end_time));
            $query->whereBetween('year',[$smonth,$emonth]);
        }

//        $list = $query
//            ->join('error_rule as er','error_data.error_rule','=','er.id')
//            ->orderBy('count','desc')
//            ->get(['count','field','desc','level'])->toArray();

        ## 导入文件名
        $fileName = '测试导出.xlsx';
        ## 表头
        $title = ['姓名','性别','年龄'];
        ## 数据
        $data = [
            ['name'=>'张三','sex'=>'男','age'=>20],
            ['name'=>'李四','sex'=>'女','age'=>23]
        ];

        ## 公共导出
        return Excel::download(new ExportData($title,$data), $fileName);
    }


}

