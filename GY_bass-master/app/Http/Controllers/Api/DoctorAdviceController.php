<?php

namespace App\Http\Controllers\Api;

use App\Exports\ExportData;
use App\Http\Controllers\Controller;
use App\Model\PatientInfo;
use App\Model\Yzb;
use App\Services\DepartmentService;
use App\Services\ElasticsearchService;
use App\Services\QualityService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class DoctorAdviceController extends Controller
{
    /**
     *long
     * 长期医嘱
     * @group temporary
     * @bodyParam AAA28 string required 病案号
     * @bodyParam page string 页码
     * @bodyParam limit string 条数
     *
     * @response {
     *  "code":200,
     *  "msg":"",
     *  "data":{
     *      "list": [{
     *          "YZMC": "医嘱名称",
     *          "KZSJ": "2020-12-28 08:12:58",
     *          "TZSJ": "2020-12-29 08:04:00",
     *          "KZYS": "开嘱医师",
     *          "TZYS": "停嘱医师",
     *          "XZJDGH": "护士",
     *          "KSDATE": "开始日期",
     *          "KSTIME": "开始时间",
     *          "TZDATE": "停止日期",
     *          "TZTIME": "停止时间"
     *      }],
     *      "info": {
     *           "AAA28": "住院号",
     *           "AAA01": "姓名",
     *           "AAA02C": "性别",
     *           "AAA04": "年龄",
     *           "AAB02C": "科别"
     *      }
     *  },
     *  "time":123787842
     * }
     */
    public static function long(Request $request){
        $AAA28 = $request->post('AAA28');
        $page = $request->post('page',1);
        $info = self::getInfo($AAA28);
        if (empty($info)){
            return ToolsService::returnData(200,[]);
        }
        $list = Yzb::query()
            ->where('ZYH',$AAA28)
            ->where('YZQX',1)
            ->orderBy('KZSJ')
            ->get(['YZMC','KZSJ','TZSJ','KZYS','TZYS','XZJDGH']);
        if ($list){
            $list = $list->toArray();
        }else{
            $list = [];
        }
        foreach ($list as &$item){
            $item['KZYS'] = QualityService::getStaffInfo($item['KZYS'],'name');
            $item['TZYS'] = QualityService::getStaffInfo($item['TZYS'],'name');
            $item['XZJDGH'] = QualityService::getStaffInfo($item['XZJDGH'],'name');
            $item['KSDATE'] = date('Y-m-d',strtotime($item['KZSJ']));
            $item['KSTIME'] = date('H:i:s',strtotime($item['KZSJ']));
            $item['TZDATE'] = date('Y-m-d',strtotime($item['TZSJ']));
            $item['TZTIME'] = date('H:i:s',strtotime($item['TZSJ']));
        }
        $data = [
            'list' => $list,
            'info' => $info
        ];
        return ToolsService::returnData(200,$data);
    }
    /**
     *temporary
     * 临时医嘱
     * @group temporary
     * @bodyParam AAA28 string required 病案号
     * @bodyParam page string 页码
     * @bodyParam limit string 条数
     *
     * @response {
     *  "code":200,
     *  "msg":"",
     *  "data":{
     *      "list": [{
     *           "YZMC": "医嘱名称",
     *           "KZSJ": "2020-12-31 10:10:28",
     *           "TZSJ": "2020-12-31 10:10:28",
     *           "KZYS": "开嘱医师",
     *           "XZJDGH": "护士",
     *           "XZJDSJ": "执行时间",
     *           "DATE": "开嘱日期",
     *           "TIME": "开嘱时间"
     *       }],
     *      "info": {
     *           "AAA28": "住院号",
     *           "AAA01": "姓名",
     *           "AAA02C": "性别",
     *           "AAA04": "年龄",
     *           "AAB02C": "科别"
     *      }
     *  },
     *  "time":123787842
     * }
     */
    public static function temporary(Request $request){
        $AAA28 = $request->post('AAA28');
        $page = $request->post('page',1);
        $limit = $request->post('limit',20);
        $offset = ($page - 1) * $limit;
        $info = self::getInfo($AAA28);
        if (empty($info)){
            return ToolsService::returnData(200,[]);
        }
        $list = Yzb::query()
            ->where('ZYH',$AAA28)
            ->where('YZQX',2)
            ->orderBy('XZJDSJ')
            ->get(['YZMC','KZSJ','TZSJ','KZYS','XZJDSJ','XZJDGH']);
        if ($list){
            $list = $list->toArray();
        }else{
            $list = [];
        }
        foreach ($list as &$item){
            $item['KZYS'] = QualityService::getStaffInfo($item['KZYS'],'name');
            $item['XZJDGH'] = QualityService::getStaffInfo($item['XZJDGH'],'name');
            $item['DATE'] = date('Y-m-d',strtotime($item['XZJDSJ']));
            $item['TIME'] = date('H:i:s',strtotime($item['XZJDSJ']));
        }
        $data = [
            'list' => $list,
            'info' => $info
        ];
        return ToolsService::returnData(200,$data);
    }
    public static function getInfo($AAA28){
        $info = PatientInfo::query()
            ->join('patient_hospital_info','patient_info.MED_REC_ID','=','patient_hospital_info.AAA28')
            ->where('MED_REC_ID',$AAA28)
            ->first(['patient_info.AAA28','patient_info.AAA01','patient_info.AAA02C','patient_info.AAA04','patient_hospital_info.AAB02C']);
        if ($info){
            $info = $info->toArray();
        }else{
            $info = [];
        }
        if (empty($info)){
            return [];
        }
        if ($info['AAA02C'] == 1){
            $info['AAA02C'] = '男';
        }else{
            $info['AAA02C'] = '女';
        }
        $conf = config('dictionaries.AAB02C');
        $info['AAB02C'] = $conf[$info['AAB02C']] ?? '';
        return $info;
    }
    /**
     *doctorAdviceSelect
     * 医嘱查询条件
     * @group temporary
     *
     * @response {
     *  "code":200,
     *  "msg":"",
     *  "data":[
     *      {
     *          "key": "YZMC",
     *          "name": "医嘱名称",
     *          "type": "input",
     *          "value": ""
     *       },
     *       {
     *          "key": "AAA28",
     *          "name": "病案号",
     *          "type": "input",
     *          "value": ""
     *       }
     *  ],
     *  "time":123787842
     * }
     */
    public function doctorAdviceSelect(Request $request){
        $department = config('dictionaries.deportment');
        $data = [
            [
                'key' => 'YZMC',
                'name' => '医嘱名称',
                'type' => 'input',
                'value' => ''
            ],[
                'key' => 'AAA28',
                'name' => '病案号',
                'type' => 'input',
                'value' => ''
            ],[
                'key' => 'YZQX',
                'name' => '医嘱期效',
                'type' => 'select',
                'value' => ['1'=>'长期医嘱','2'=>'临时医嘱']
            ],[
                'key' => 'KZKS',
                'name' => '开嘱科室',
                'type' => 'select',
                'value' => $department
            ],[
                'key' => 'BRKS',
                'name' => '病人科室',
                'type' => 'select',
                'value' => $department
            ]
        ];
        return ToolsService::returnData(200,$data);
    }
    /**
     *getDoctorAdvice
     * 医嘱查询
     * @group temporary
     * @bodyParam AAA28 string 病案号
     * @bodyParam start string 开始时间
     * @bodyParam end   string 结束时间
     * @bodyParam YZQX  int 医嘱期效
     * @bodyParam BRKS  int 病人科室
     * @bodyParam KZKS  int 开嘱科室
     * @bodyParam KZKS  int 开嘱科室
     * @bodyParam YZMC  string 医嘱名称
     * @bodyParam page string 页码
     * @bodyParam limit string 条数
     * @response {
     *  "code":200,
     *  "msg":"",
     *  "data":{
     *      "list":[{
     *          "ZYH": "住院号",
     *          "YZMC": "医嘱名称",
     *          "BRKS": "病人科室",
     *          "KZKS": "开嘱科室",
     *          "YZQX": "医嘱期效",
     *          "KZSJ": "开嘱时间",
     *          "AAA28": "病案号",
     *          "AAC01": "出院时间",
     *          "AAB01": "入院时间"
     *      }],
     *      "total":200
     *  },
     *  "time":123787842
     * }
     */
    public function getDoctorAdvice(Request $request)
    {
        $where = [];
        $YZQX = $request->post('YZQX',0);
        if (!empty($YZQX)){
            $where['YZQX'] = $YZQX;
        }
        $BRKS = $request->post('BRKS',0);
        if (!empty($BRKS)){
            $where['BRKS'] = $BRKS;
        }
        $KZKS = $request->post('KZKS',0);
        if (!empty($KZKS)){
            $where['KZKS'] = $KZKS;
        }
        $YZMC = $request->post('YZMC','');
        $query = Yzb::query();
        if (!empty($where)){
            $query->where($where);
        }
        if (!empty($YZMC)){
            $query->where('YZMC','like','%'.$YZMC.'%');
        }
        $start_time = $request->post('start');
        $end_time = $request->post('end',date('Y-m-d H:i:s',strtotime($start_time) + 86400));
        if (!empty($start_time)){
            $query->whereBetween('KZSJ',[$start_time,$end_time]);
        }

        $zyh = $request->post('AAA28','');
        if (!empty($zyh)){
            $AAA28 = PatientInfo::query()
                ->where('AAA28',$zyh)
                ->pluck('MED_REC_ID');
            if ($AAA28){
                $AAA28 = $AAA28->toArray();
                if (!empty($AAA28)){
                    $query->whereIn('ZYH',$AAA28);
                }
            }
        }

        $page = $request->post('page',1);
        $limit = $request->post('limit',20);
        $offset = ($page - 1) * $limit;
        $total = $query->count('ZYH');
        $data = $query->offset($offset)->limit($limit)->get(['ZYH','YZMC','BRKS','KZKS','YZQX','KZSJ']);
        if ($data){
            $data = $data->toArray();
        }else{
            $data = [];
        }
        if (!empty($data)){
            $list = array_column($data,'ZYH');
            $department = DepartmentService::getDepartmentList();
            $info = PatientInfo::query()
                ->whereIn('MED_REC_ID',$list)
                ->get(['AAA28','MED_REC_ID','AAC01','AAB01'])
                ->keyBy('MED_REC_ID')
                ->toArray();
            foreach ($data as &$item){
                $item['YZQX'] = $item['YZQX'] == 1 ? '长期医嘱': '临时医嘱';
                $item['BRKS'] = $department[$item['BRKS']] ?? '';
                $item['KZKS'] = $department[$item['KZKS']] ?? '';
                $item['AAA28'] = $info[$item['ZYH']]['AAA28'] ?? '';
                $item['AAC01'] = $info[$item['ZYH']]['AAC01'] ?? '';
                $item['AAB01'] = $info[$item['ZYH']]['AAB01'] ?? '';
            }
        }

        return ToolsService::returnData(200,['list'=>$data,'total'=>$total]);
    }

    /**
     *doctorAdviceExport
     * 医嘱查询
     * @group temporary
     * @bodyParam AAA28 string 病案号
     * @bodyParam start string 开始时间
     * @bodyParam end   string 结束时间
     * @bodyParam YZQX  int 医嘱期效
     * @bodyParam BRKS  int 病人科室
     * @bodyParam KZKS  int 开嘱科室
     * @bodyParam KZKS  int 开嘱科室
     * @bodyParam YZMC  string 医嘱名称
     */
    public function doctorAdviceExport(Request $request){
        $where = [];
        $YZQX = $request->post('YZQX',0);
        if (!empty($YZQX)){
            $where['YZQX'] = $YZQX;
        }
        $BRKS = $request->post('BRKS',0);
        if (!empty($BRKS)){
            $where['BRKS'] = $BRKS;
        }
        $KZKS = $request->post('KZKS',0);
        if (!empty($KZKS)){
            $where['KZKS'] = $KZKS;
        }
        $YZMC = $request->post('YZMC','');
        $query = Yzb::query();
        if (!empty($where)){
            $query->where($where);
        }
        if (!empty($YZMC)){
            $query->where('YZMC','like','%'.$YZMC.'%');
        }
        $start_time = $request->post('start');
        $end_time = $request->post('end',date('Y-m-d H:i:s',strtotime($start_time) + 86400));
        if (!empty($start_time)){
            $query->whereBetween('KZSJ',[$start_time,$end_time]);
        }

        $zyh = $request->post('AAA28','');
        if (!empty($zyh)){
            $AAA28 = PatientInfo::query()
                ->where('AAA28',$zyh)
                ->pluck('MED_REC_ID');
            if ($AAA28){
                $AAA28 = $AAA28->toArray();
                if (!empty($AAA28)){
                    $query->whereIn('ZYH',$AAA28);
                }
            }
        }

        $data = $query->get(['ZYH','YZMC','BRKS','KZKS','YZQX','KZSJ']);
        if ($data){
            $data = $data->toArray();
        }else{
            $data = [];
        }
        if (!empty($data)){
            $list = array_column($data,'ZYH');
            $department = DepartmentService::getDepartmentList();
            $info = PatientInfo::query()
                ->join('patient_hospital_info as phi','patient_info.MED_REC_ID','=','phi.AAA28')
                ->whereIn('patient_info.MED_REC_ID',$list)
                ->get(['patient_info.AAA28','MED_REC_ID','AAC01','AAB01'])
                ->keyBy('MED_REC_ID');

            foreach ($data as &$item){
                $item['YZQX'] = $item['YZQX'] == 1 ? '长期医嘱': '临时医嘱';
                $item['BRKS'] = $department[$item['BRKS']] ?? '';
                $item['KZKS'] = $department[$item['KZKS']] ?? '';
                $item['AAA28'] = $info[$item['ZYH']]['AAA28'] ?? '';
                $item['AAC01'] = $info[$item['ZYH']]['AAC01'] ?? '';
                $item['AAB01'] = $info[$item['ZYH']]['AAB01'] ?? '';
            }
        }
        $exportData = [];
        $index = 1;
        foreach ($data as $key => $val) {
            $exportData[$key] = [
                $index,
                $val['ZYH']??'',
                $val['YZMC']??'',
                $val['BRKS']??'',
                $val['KZKS']??'',
                $val['KZSJ']??'',
                $val['YZQX']??'',
                $val['AAB01']??'',
                $val['AAC01']??'',
            ];
            $index++;
        }
        ## 病案首页缺陷分析
        $fileName = '医嘱本.xlsx';
        ## 表头
        $title = ['序号','住院号','医嘱名称','病人科室','开嘱科室','开嘱时间','医嘱期效','入院时间','出院时间'];
        ## 公共导出
        return Excel::download(new ExportData($title,$exportData), $fileName);
    }
}
