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

/**
 * 导出服务层
 */
class ExportService
{
    /**
     * 科室排名导出
     */
    public function departmentExport($startTime,$endTime)
    {
        $model = DepartmentData::query();
        $model = $this->getCondition($model, $startTime, $endTime);
        $data = $model
            ->select('department_id','name','average_score','total_medical','total_error','average_error','average_score','max_score','min_score','total_error_medical')
            ->leftJoin('department','department_data.department_id','=','department.id')
            ->get()->toArray();

        $exportData = [];
        $index = 1;
        foreach ($data as $key => $val) {
            $averageError = round(($val['total_error'] / $val['total_medical']), 2);
            $averageScore= round(($val['average_score']*$val['total_medical']) / $val['total_medical'], 2);
            $outstanding = round(100 - ($val['total_error'] / $val['total_medical']), 2);
            $exportData[] = [
                $index,
                $val['name'] ?? '',
                $val['total_medical'] ?? '',
                $val['total_error'] ?? '',
                $averageError ?? '',
                $averageScore ?? '',
                $val['max_score'] ?? '',
                $val['min_score'] ?? '',
                $outstanding,
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
    public function attendingGroupExport($startTime,$endTime)
    {
        $model = AttendingGroupData::query();
        $model = $this->getCondition($model, $startTime, $endTime);
        $data = $model
            ->select('attending_group_id', 'attending_group.name as group_name','department.name as dep_name', 'total_medical', 'total_error_medical', 'error_medical', 'complete_error_medical', 'logic_error_medical', 'standard_error_medical', 'code_error_medical')
            ->leftJoin('attending_group', 'attending_group_data.attending_group_id', '=', 'attending_group.id')
            ->leftJoin('department','attending_group.department_id','=','department.id')
            ->get()->toArray();

        $exportData = [];
        $index = 1;
        foreach ($data as $key => $val) {
            $exportData[] = [
                $index,                         //序号
                $val['dep_name'],               //科室
                $val['group_name'] ?? '',       //主诊组
                $val['total_medical'] ?? '',    //病案数
                $val['total_medical'] ?? '',    //质控病案数
                $val['total_error_medical'],    //问题病案数
                round($val['total_error_medical'] / $val['total_medical'], 2),//问题比例
                $val['complete_error_medical'],    //完整性问题病案数
                round($val['complete_error_medical'] / $val['total_medical'], 1),//完整性问题病案比例
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
    public function indicationsExport($startTime,$endTime)
    {
        $model = IndicationsData::query();
        $model = $this->getCondition($model, $startTime, $endTime);
        $data = $model
            ->select('indications_id','indications.name as indications_name','department.name as dep_name','total_medical','total_error_medical','error_medical','complete_error_medical','logic_error_medical','standard_error_medical','code_error_medical')
            ->leftJoin('indications','indications_data.indications_id','=','indications.id')
            ->leftJoin('department','indications.department_id','=','department.id')
            ->get()->toArray();

        $exportData = [];
        $index = 1;
        foreach ($data as $key => $val) {
            $exportData[] = [
                $index,                         //序号
                $val['dep_name'],               //科室
                $val['indications_name'] ?? '', //主治医师
                $val['total_medical'] ?? '',    //病案数
                $val['total_medical'] ?? '',    //质控病案数
                $val['total_error_medical'],    //问题病案数
                round($val['total_error_medical'] / $val['total_medical'], 2),//问题比例
                $val['complete_error_medical'],    //完整性问题病案数
                round($val['complete_error_medical'] / $val['total_medical'], 1),//完整性问题病案比例
                $val['logic_error_medical'],    //准确性问题逻辑性
                $val['standard_error_medical'], //准确性问题规范性
                $val['code_error_medical'],     //准确性问题编码错误
            ];
            $index++;
        }

        $fileName = '主治医师.xlsx';
        $title = ['序号', '科室名称', '主治医师', '病案数', '质控病案数', '问题病案数', '问题比例', '完整性问题病案数', '完整性问题病案比例', '准确性问题逻辑性', '准确性问题规范性', '准确性问题编码错误'];

        return $this->exportDownload($exportData, $fileName, $title);
    }

    /**
     * 住院医师导出
     */
    public function hospitalExport($startTime,$endTime)
    {
        $model = HospitalData::query();
        $model = $this->getCondition($model, $startTime, $endTime);
        $data = $model
            ->select('hospital_id','hospital.name as hospital_name','department.name as dep_name','total_medical','total_error_medical','error_medical','complete_error_medical','logic_error_medical','standard_error_medical','code_error_medical')
            ->leftJoin('hospital','hospital_data.hospital_id','=','hospital.id')
            ->leftJoin('department','hospital.department_id','=','department.id')
            ->get()->toArray();

        $exportData = [];
        $index = 1;
        foreach ($data as $key => $val) {
            $exportData[] = [
                $index,                         //序号
                $val['dep_name'],               //科室
                $val['hospital_name'] ?? '',    //住院医师
                $val['total_medical'] ?? '',    //病案数
                $val['total_medical'] ?? '',    //质控病案数
                $val['total_error_medical'],    //问题病案数
                round($val['total_error_medical'] / $val['total_medical'], 2),//问题比例
                $val['complete_error_medical'],    //完整性问题病案数
                round($val['complete_error_medical'] / $val['total_medical'], 1),//完整性问题病案比例
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
     */
    public function coderExport($startTime,$endTime)
    {
        $model = CoderData::query();
        $model = $this->getCondition($model, $startTime, $endTime);
        $data = $model
            ->select('coder_id','coder.name','total_medical', 'total_error_medical', 'code_error_medical', 'scores', 'error_medical')
            ->leftJoin('coder', "coder_data.coder_id",'=','coder.id')
            ->get()->toArray();

        $exportData = [];
        $index = 1;
        foreach ($data as $key => $val) {
            $exportData[] = [
                $index,
                $val['name'] ?? '',
                $val['scores'] ?? '',
                $val['total_error_medical'] ?? '',
                round(($val['total_error_medical'] - $val['error_medical']) / $val['total_medical'],2),
                $val['code_error_medical'] ?? '',
                round($val['code_error_medical'] / $val['total_medical'],2),
            ];
            $index++;
        }

        $fileName = '编码员.xlsx';
        $title = ['序号','编码员','分数','处理病案数','处理病案占比','编码问题病案数','编码问题病案占比'];

        return $this->exportDownload($exportData, $fileName, $title);
    }

    /**
     * 缺陷问题导出
     */
    public function errorDataExport($startTime,$endTime,$source,$type,$level)
    {
        //$model = Error::query()->where('type',$type)->where('error.source',$source);
        $model = Error::query()->where('error.source',$source);
        if ($level != 'all') {
            $model->where('error.level',$level);
        }
        $model = $this->getCondition($model, $startTime, $endTime);
        //$data = $model
            //->leftJoin('patient_info','error.AAA28','=','patient_info.AAA28')
            //->orderByDesc('error.id')
            //->get()->toArray();
        $data = $model->orderByDesc('error.id')->get()->toArray();

        $exportData = [];
        $index = 1;
        foreach ($data as $key => $val) {
            $level = $val['level']==1 ? '强制' : '建议';
            $exportData[] = [
                $index,
                //$val['AAA28'],
                //$val['AAA01'],
                $val['created_at'] ?? '',
                $val['desc'] ?? '',
                $val['error_name'] ?? '',
                $level,
            ];
            $index++;
        }

        $fileName = '缺陷问题.xlsx';
        //$title = ['序号','患者编号','患者姓名','缺陷描述','缺陷字段','缺陷分级'];
        $title = ['序号','统计时间','缺陷描述','缺陷字段','缺陷分级'];

        return $this->exportDownload($exportData, $fileName, $title);
    }

    /**
     * 上报平台导出
     * type 1-国考 2-卫统 3-医保
     */
    public function historyExport($startTime,$endTime,$type)
    {
        $model = ReportingHistory::query();
        if ($type) {
            //$model->where('report_platform',$type);
        }
        if ($startTime) {
            $model->where('hospital_time', '>=', $startTime);
        }
        if ($endTime) {
            $model->where('hospital_time', '<=', $endTime);
        }
        $data = $model
            ->select('hospital_time','hospital_num','medical_num','report_num','report_probability','report_platform')
            ->orderBy('id')
            ->get()->toArray();

        $exportData = [];
        $index = 1;
        foreach ($data as $key => $val) {
            $exportData[] = [
                $index,
                $val['hospital_time'] ?? '',
                $val['hospital_num'] ?? '',
                $val['medical_num'] ?? '',
                $val['report_num'] ?? '',
                $val['report_probability'] ?? '',
                ReportingHistory::PLATFORM[$val['report_platform']] ?? '',
            ];
            $index++;
        }

        $fileName = '上报历史.xlsx';
        $title = ['序号','病案月份（出院日期）','出院人次','病案数量','上报数量','上报率','上报平台'];

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

    /**
     * 处理条件
     * @param $model
     * @param $start_time
     * @param $end_time
     * @param $type
     * @return mixed
     */
    public function getCondition($model, $start_time, $end_time)
    {
        $model = $model
            ->when($start_time, function ($query, $start_time) {
                //时间格式20220808
                $year = substr($start_time,0,4);
                $month = substr($start_time,4,2);
                return $query->where('year', '>=', $year)->where('month', '>=', $month);
            })
            ->when($end_time, function ($query, $end_time) {
                //转换时间戳
                //时间格式20220808
                $year = substr($end_time,0,4);
                $month = substr($end_time,4,2);
                return $query->where('year', '<=', $year)->where('month', '<=', $month);
            });
        return $model;
    }

}
