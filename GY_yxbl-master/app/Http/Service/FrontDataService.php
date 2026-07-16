<?php

namespace App\Http\Service;

use App\Model\AttendingGroupData;
use App\Model\CoderData;
use App\Model\DepartmentData;
use App\Model\Error;
use App\Model\HospitalData;
use App\Model\IndicationsData;
use App\Model\ReportingHistory;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ExportData;
use DB;

/**
 * 导出服务层
 */
class FrontDataService
{
    /**
     * 科室排名导出
     */
    public function departmentExport($startTime,$endTime)
    {
        $model = DepartmentData::query();
        $data = $model
            ->select([
                DB::raw('`department_id`'),
                DB::raw('max(name) as department_name'),
                DB::raw('sum( `average_score` * total_medical ) / sum(total_medical) average_score'),
                DB::raw('sum(`total_medical`) total_medical'),
                DB::raw('sum(`total_error_medical`) as total_error_medical'),
                DB::raw('round(sum(`total_error`)/sum(`total_medical`),2) average_error'),
                DB::raw('max(`max_score`) max_score'),
                DB::raw('min(`min_score`) min_score'),
	            DB::raw('round(100 - round(sum(`total_error`)/sum(`total_medical`),2),2) as outstanding')
            ])
            ->whereBetween('department_data.created_at', [date('Y-m-d 00:00:00', strtotime($startTime)), date('Y-m-d 23:59:59', strtotime($endTime))])
            ->leftJoin('department','department_data.department_id','=','department.id')
            ->orderBy('total_medical', 'desc')
            ->groupBy('department_id')
            ->get()->toArray();

        $exportData = [];
        $index = 1;
        foreach ($data as $key => $val) {
            $exportData[] = [
                $index,
                $val['department_name'] ?? '',
                $val['total_medical'] ?? '',
                $val['total_error_medical'] ?? '',
                $val['average_error'] ?? '',
                $val['average_score'] ?? '',
                $val['max_score'] ?? '',
                $val['min_score'] ?? '',
                $val['outstanding'] ?? '',
            ];
            $index++;
        }

        $fileName = '科室最佳.xlsx';
        $title = ['序号','科室','病案数','总缺陷','平均缺陷','平均分','最高分','最低分','优秀率'];

        return $this->exportDownload($exportData,$fileName,$title);
    }

    /**
     * 主诊组导出
     */
    public function attendingGroupExport($data,$startTime,$endTime)
    {
        // $model = AttendingGroupData::query();
        // $model = $this->getCondition($model, $startTime, $endTime);
        // $data = $model
        //     ->select('attending_group_id', 'attending_group.name as group_name','department.name as dep_name', 'total_medical', 'total_error_medical', 'error_medical', 'complete_error_medical', 'logic_error_medical', 'standard_error_medical', 'code_error_medical')
        //     ->leftJoin('attending_group', 'attending_group_data.attending_group_id', '=', 'attending_group.id')
        //     ->leftJoin('department','attending_group.department_id','=','department.id')
        //     ->get()->toArray();

        $exportData = [];
        $index = 1;
        foreach ($data as $key => $val) {
            $exportData[] = [
                $index,                         //序号
                $val['name'],               //科室
                $val['group_name'] ?? '',       //主诊组
                $val['total_medical'] ?? '',    //病案数
                $val['total_medical'] ?? '',    //质控病案数
                $val['total_error_medical'],    //问题病案数
                round($val['total_error_medical'] / $val['total_medical'], 2),//问题比例
                $val['complete_error_medical'],    //完整性问题病案数
                (string)round($val['complete_error_medical'] / $val['total_medical'], 1),//完整性问题病案比例
                $val['logic_error_medical'],    //准确性问题逻辑性
                $val['standard_error_medical'], //准确性问题规范性
                $val['code_error_medical'],     //准确性问题编码错误
            ];
            $index++;
        }

        $fileName = '主诊组.xlsx';
        $title = ['序号', '科室名称', '组诊组', '病案数', '质控病案数', '问题病案数', '问题比例', '完整性问题病案数', '完整性问题病案比例', '准确性问题逻辑性', '准确性问题规范性', '准确性问题编码错误'];

        return $this->exportDownload($exportData, $fileName, $title);
    }

    /**
     * 主治医师导出
     */
    public function indicationsExport($data, $startTime,$endTime)
    {
        // $model = IndicationsData::query();
        // $model = $this->getCondition($model, $startTime, $endTime);
        // $data = $model
        //     ->select('indications_id','indications.name as indications_name','department.name as dep_name','total_medical','total_error_medical','error_medical','complete_error_medical','logic_error_medical','standard_error_medical','code_error_medical')
        //     ->leftJoin('indications','indications_data.indications_id','=','indications.id')
        //     ->leftJoin('department','indications.department_id','=','department.id')
        //     ->get()->toArray();

        $exportData = [];
        $index = 1;
        foreach ($data as $key => $val) {
            $exportData[] = [
                $index,                         //序号
                $val['name'] ?? '', //主治医师
                $val['department_name'],               //科室
                $val['total_medical'] ?? '',    //病案数
                $val['total_medical'] ?? '',    //质控病案数
                $val['total_error_medical'],    //问题病案数
                round($val['total_error_medical'] / $val['total_medical'], 2),//问题比例
                $val['complete_error_medical'],    //完整性问题病案数
                (string)round($val['complete_error_medical'] / $val['total_medical'], 1),//完整性问题病案比例
                $val['logic_error_medical'],    //准确性问题逻辑性
                $val['standard_error_medical'], //准确性问题规范性
                $val['code_error_medical'],     //准确性问题编码错误
            ];
            $index++;
        }

        $fileName = '主治医师.xlsx';
        $title = ['序号',  '主治医师', '科室名称', '病案数', '质控病案数', '问题病案数', '问题比例', '完整性问题病案数', '完整性问题病案比例', '准确性问题逻辑性', '准确性问题规范性', '准确性问题编码错误'];

        return $this->exportDownload($exportData, $fileName, $title);
    }

    /**
     * 住院医师导出
     */
    public function hospitalExport($data, $startTime,$endTime)
    {
        // $model = HospitalData::query();
        // $model = $this->getCondition($model, $startTime, $endTime);
        // $data = $model
        //     ->select('hospital_id','hospital.name as hospital_name','department.name as dep_name','total_medical','total_error_medical','error_medical','complete_error_medical','logic_error_medical','standard_error_medical','code_error_medical')
        //     ->leftJoin('hospital','hospital_data.hospital_id','=','hospital.id')
        //     ->leftJoin('department','hospital.department_id','=','department.id')
        //     ->get()->toArray();

        $exportData = [];
        $index = 1;
        foreach ($data as $key => $val) {
            $exportData[] = [
                $index,                         //序号
                $val['name'],               //科室
                $val['hospital_name'] ?? '',    //住院医师
                $val['total_medical'] ?? '',    //病案数
                $val['total_medical'] ?? '',    //质控病案数
                $val['total_error_medical'],    //问题病案数
                round($val['total_error_medical'] / $val['total_medical'], 2),//问题比例
                $val['complete_error_medical'],    //完整性问题病案数
                (string)round($val['complete_error_medical'] / $val['total_medical'], 1),//完整性问题病案比例
                $val['logic_error_medical'],    //准确性问题逻辑性
                $val['standard_error_medical'], //准确性问题规范性
                $val['code_error_medical'],     //准确性问题编码错误
            ];
            $index++;
        }

        $fileName = '住院医师.xlsx';
        $title = ['序号', '科室名称', '住院医师', '病案数', '质控病案数', '问题病案数', '问题比例', '完整性问题病案数', '完整性问题病案比例', '准确性问题逻辑性', '准确性问题规范性', '准确性问题编码错误'];

        return $this->exportDownload($exportData, $fileName, $title);
    }

    /**
     * 编码员导出
     * 
     * 
     */
    public function coderExport($data, $startTime,$endTime)
    {
        // $model = CoderData::query();
        // $model = $this->getCondition($model, $startTime, $endTime);
        // $data = $model
        //     ->select('coder_id','coder.name','total_medical', 'total_error_medical', 'code_error_medical', 'scores', 'error_medical')
        //     ->leftJoin('coder', "coder_data.coder_id",'=','coder.id')
        //     ->get()->toArray();
        $exportData = [];
        $index = 1;
        foreach ($data as $key => $val) {
            $exportData[] = [
                $index,
                $val['name'] ?? '',
                $val['scores'] ?? '',
                $val['total_error_medical'] ?? '',
                (string)round(($val['total_error_medical'] - $val['error_medical']) / $val['total_medical'],2),
                $val['code_error_medical'] ?? '',
                $val['code_proportion'],
            ];
            $index++;
        }

        $fileName = '编码员.xlsx';
        $title = ['序号','编码员','分数','处理病案数','处理病案占比','编码问题病案数','编码问题病案占比'];

        return $this->exportDownload($exportData, $fileName, $title);
    }



        /**
     * 公共导出
     * @param $exportData
     * @param $fileName
     * @param $title
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportDownload($exportData,$fileName,$title)
    {
        ## 公共导出
        return Excel::download(new ExportData($title,$exportData), $fileName);
    }

}
