<?php

namespace App\Http\Controllers\Api;

use App\Exports\ExportData;
use App\Http\Controllers\Controller;
use App\Model\Department;
use App\Model\EMR_BL_BL01;
use App\Model\PatientInfo;
use App\Model\Yzb;
use App\Model\ZY_HCMX;
use App\Services\DepartmentService;
use App\Services\DoctorAdviceService;
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
            ->where('ZYH',(string)$AAA28)
            ->where('YZQX',1)
            ->orderBy('KZSJ')
            ->get(['YZMC','KZSJ','TZSJ','KZYS','TZYS','XZJDGH','SYPC','YCJL']);
        if ($list){
            $list = $list->toArray();
        }else{
            $list = [];
        }
        foreach ($list as &$item){
            $item['KZYS'] = QualityService::getStaffInfo($item['KZYS'],'name');
            $item['TZYS'] = QualityService::getStaffInfo($item['TZYS'],'name');
            $item['XZJDGH'] = QualityService::getStaffInfo($item['XZJDGH'],'name');
            $item['KSDATE'] = !empty($item['KZSJ']) ? date('Y-m-d',strtotime($item['KZSJ'])) : '';
            $item['KSTIME'] = !empty($item['KZSJ']) ? date('H:i:s',strtotime($item['KZSJ'])) : '';
            $item['TZDATE'] = !empty($item['TZSJ']) ? date('Y-m-d',strtotime($item['TZSJ'])) : '';
            $item['TZTIME'] = !empty($item['TZSJ']) ? date('H:i:s',strtotime($item['TZSJ'])) : '';
        }
        if (!empty(request()->post('is_tm'))) {
            $info['AAA01'] = desensitize($info['AAA01'], 1, 0, '*');
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
            ->where('ZYH',(string)$AAA28)
            ->where('YZQX',2)
            ->orderBy('XZJDSJ')
            ->get(['YZMC','KZSJ','TZSJ','KZYS','XZJDSJ','XZJDGH','SYPC','YCJL']);
        if ($list){
            $list = $list->toArray();
        }else{
            $list = [];
        }
        foreach ($list as &$item){
            $item['KZYS'] = QualityService::getStaffInfo($item['KZYS'],'name');
            $item['XZJDGH'] = QualityService::getStaffInfo($item['XZJDGH'],'name');
            $item['DATE'] = !empty($item['KZSJ']) ? date('Y-m-d',strtotime($item['KZSJ'])) : '';
            $item['TIME'] = !empty($item['KZSJ']) ? date('H:i:s',strtotime($item['KZSJ'])) : '';
        }
        if (!empty(request()->post('is_tm'))) {
            $info['AAA01'] = desensitize($info['AAA01'], 1, 0, '*');
        }

        // 获取护士分床时间、死亡时间、出院时间
        $info['HCRQ'] = ZY_HCMX::query()->where('ZYH','=',(string)$AAA28)->where('HCLX','=',0)->value('HCRQ');
        // 查询出院时间
        $info['AAC01'] = Yzb::query()->where('ZYH','=',(string)$AAA28)->where('YDYZLB','=',303)->value('XZJDSJ');
        // 查询死亡时间
        $info['SWSJ'] = Yzb::query()->where('ZYH','=',(string)$AAA28)->where('YDYZLB','=',305)->value('XZJDSJ');

        $data = [
            'list' => $list,
            'info' => $info
        ];
        return ToolsService::returnData(200,$data);
    }
    public static function getInfo($AAA28){
        $info = PatientInfo::query()
            ->leftJoin('patient_hospital_info','patient_info.MED_REC_ID','=','patient_hospital_info.AAA28')
            ->where('MED_REC_ID',$AAA28)
            ->leftjoin('ZY_BRRY','patient_info.MED_REC_ID','=','ZY_BRRY.ZYH')
            ->first(['patient_info.AAA28','patient_info.AAA01','patient_info.AAA02C','patient_info.AAA04','patient_hospital_info.AAB02C','patient_info.AAC01','ZY_BRRY.BRKS','ZY_BRRY.CH']);
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
        //从departments表中获取科别名称
        //$info['AAB02C'] = $conf[$info['BRKS']] ?? '';
        $info['AAB02C'] = Department::query()->where('dep_id',$info['BRKS'])->value('dep_name') ?? '';
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
            ['key' => 'YZMC', 'name' => '医嘱名称', 'type' => 'input', 'value' => ''],
            ['key' => 'AAA28', 'name' => '病案号', 'type' => 'input', 'value' => ''],
            ['key' => 'YZQX', 'name' => '医嘱期效', 'type' => 'select', 'value' => ['1' => '长期医嘱', '2' => '临时医嘱']],
            ['key' => 'KZKS', 'name' => '开嘱科室', 'type' => 'select', 'value' => $department],
            ['key' => 'BRKS', 'name' => '病人科室', 'type' => 'select', 'value' => $department],
            ['key' => 'ABC01N', 'name' => '主要诊断名称', 'type' => 'input', 'value' => ''],
            ['key' => 'ABC01C', 'name' => '主要诊断编码', 'type' => 'input', 'value' => ''],
            ['key' => 'ICD10_ID1_first', 'name' => '第一其他诊断编码', 'type' => 'input', 'value' => ''],
            ['key' => 'ICD10_NAME_first', 'name' => '第一其他诊断名称', 'type' => 'input', 'value' => ''],
            ['key' => 'ICD10_ID1', 'name' => '其他诊断编码', 'type' => 'input', 'value' => ''],
            ['key' => 'ICD10_NAME', 'name' => '其他诊断名称', 'type' => 'input', 'value' => ''],
            ['key' => 'ICD9_ID1', 'name' => '手术编码', 'type' => 'input', 'value' => ''],
            ['key' => 'ICD9_NAME', 'name' => '手术名称', 'type' => 'input', 'value' => ''],
            ['key' => 'ABC03C', 'name' => '主要诊断入院病情', 'type' => 'input', 'value' => ''],
            ['key' => 'RYQK', 'name' => '其他诊断入院病情', 'type' => 'input', 'value' => ''],
            ['key' => 'OPE_LEVEL', 'name' => '手术级别', 'type' => 'input', 'value' => ''],
            ['key' => 'AEM01C', 'name' => '离院方式', 'type' => 'input', 'value' => ''],
            ['key' => 'ABA01N', 'name' => '门（急）诊诊断', 'type' => 'input', 'value' => ''],
            ['key' => 'RJSS', 'name' => '是否为日间手术', 'type' => 'select', 'value' => ['0'=>['key'=>'0','value'=>'是'],'1'=>['key'=>'1','value'=>'否']]],
            ['key' => 'AAA28', 'name' => '病案号', 'type' => 'input', 'value' => ''],
            ['key' => 'AAA01', 'name' => '姓名', 'type' => 'input', 'value' => ''],
            ['key' => 'AAA02C', 'name' => '性别', 'type' => 'input', 'value' => ''],
            ['key' => 'AAA04', 'name' => '年龄', 'type' => 'input', 'value' => ''],
            ['key' => 'AAA29', 'name' => '住院次数', 'type' => 'input', 'value' => ''],
            ['key' => 'AAC04', 'name' => '住院天数', 'type' => 'input', 'value' => ''],
            ['key' => 'SSPB', 'name' => '手术判别', 'type' => 'input', 'value' => ''], // 新增

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
    public function getDoctorAdvice(Request $request){
        $page = $request->post('page',1);
        $limit = $request->post('limit',10);
        $offset = ($page - 1) * $limit;
        $patientInfo = [];
        $mainDiagnosis = [];
        $otherDiagnosis = [];
        $mainOperation = [];
        $secondaryOperation = [];
        $patientMedicalInfo = [];
        $YZB = [];
        //出院时间
        $aac01_s = $request->post('AAC01_start',date('Ym01'));
        $aac01_s = date('Y-m-d 00:00:00',strtotime($aac01_s));
        if (!empty($aac01_s)){
            $aac01_e = $request->post('AAC01_end',date('Ym31'));
            $aac01_e = date('Y-m-d 00:00:00',strtotime($aac01_e));
            $patientInfo['and'][] = ['patient_info.AAC01','>=',$aac01_s];
            $patientInfo['and'][] = ['patient_info.AAC01','<=',$aac01_e];
        }
        //年龄
        $aaa04_s = $request->post('AAA04_start');
        if (!empty($aaa04_s)){
            $aaa04_s = json_decode($aaa04_s,true);
            if ($aaa04_s['type' == 0]){
                $patientInfo['and'][] = ['patient_info.AAA40', '>=', $aaa04_s['value']];
            }else{
                $patientInfo['and'][] = ['patient_info.AAA04', '>=', $aaa04_s['value']];
            }
        }
        $aaa04_e = $request->post('AAA04_end');
        if (!empty($aaa04_e)){
            $aaa04_e = json_decode($aaa04_e,true);
            if ($aaa04_e['type' == 0]){
                $patientInfo['and'][] = ['patient_info.AAA40', '>=', $aaa04_e['value']];
            }else{
                $patientInfo['and'][] = ['patient_info.AAA04', '>=', $aaa04_e['value']];
            }
        }
        //住院天数
        $aac04_s = $request->post('AAC04_start');
        if (!empty($aac04_s)){
            $aac04_e = $request->post('AAC04_end');
            if (!empty($aac04_e)){
                $patientInfo['and'][] = ['patient_info.AAC04', '>=',$aac04_s];
                $patientInfo['and'][] = ['patient_info.AAC04', '<=',$aac04_e];
            }else{
                $patientInfo['and'][] = ['patient_info.AAC04', '>=',$aac04_s];
                $patientInfo['and'][] = ['patient_info.AAC04', '<=',$aac04_s+5];
            }
        }
        $field = $request->post('field', '');
        if (!empty($field)) {
            foreach ($field as $item) {
                if ($item['select_type'] == 1){
                    $select_type = 'or';
                }elseif ($item['select_type'] == 2){
                    $select_type = 'no';
                }else{
                    $select_type = "and";
                }
                if ($item['type'] == 0) {
                    $operator = 'like';
                    $value = "%" . $item['value'] . "%";
                } else {
                    if ($select_type == 'no'){
                        $operator = '!=';
                    }else{
                        $operator = '=';
                    }
                    $value = $item['value'];
                }
                switch ($item['key']) {
                    case 'ABC01N';
                        $mainDiagnosis[$select_type][] = ['md.ICD10_NAME', $operator, $value];
                        break;
                    case 'ABC01C';
                        $mainDiagnosis[$select_type][] = ['md.ICD10_ID1', $operator, $value];
                        break;
                    case 'ICD10_ID1_first';
                        $otherDiagnosis[$select_type][] = ['ICD10_ID1', $operator, $value];
                        $otherDiagnosis[$select_type][] = ['DIA_ORDER', '=', 1];
                        break;
                    case 'ICD10_NAME_first';
                        $otherDiagnosis[$select_type][] = ['ICD10_NAME', $operator, $value];
                        $otherDiagnosis[$select_type][] = ['DIA_ORDER', '=', 1];
                        break;
                    case 'ICD10_ID1';
                        $otherDiagnosis[$select_type][] = ['ICD10_ID1', $operator, $value];
                        break;
                    case 'ICD10_NAME';
                        $otherDiagnosis[$select_type][] = ['ICD10_NAME', $operator, $value];
                        break;
                    case 'ICD9_ID1';
                        $mainOperation[$select_type][] = ['ICD9_ID1', $operator, $value];
                        $secondaryOperation[$select_type][] = ['ICD9_ID1', $operator, $value];
                        break;
                    case 'ICD9_NAME';
                        $mainOperation[$select_type][] = ['ICD9_NAME', $operator, $value];
                        $secondaryOperation[$select_type][] = ['ICD9_NAME', $operator, $value];
                        break;
                    case 'ICD8_ID1';
                        $secondaryOperation[$select_type][] = ['ICD9_ID1', $operator, $value];
                        break;
                    case 'ICD8_NAME';
                        $secondaryOperation[$select_type][] = ['ICD9_NAME', $operator, $value];
                        break;
                    case 'ABC03C';//主要诊断入院病情
                        if ($item['value'] > 0) {
                            $patientMedicalInfo[$select_type][] = ["pmi.ABC03C",$operator , $value];
                        }
                        break;
                    case 'RYQK';//其他诊断入院病情
                        if ($item['value'] > 0) {
                            $otherDiagnosis[$select_type][] = ["RYQK",$operator, $value];
                        }
                        break;
                    case 'OPE_LEVEL';//手术级别
                        if ($item['value'] != 0) {
                            $mainOperation[$select_type][] = ['OPE_LEVEL', $operator, $value];
                            $secondaryOperation[$select_type][] = ['OPE_LEVEL', $operator, $value];
                        }
                        break;
                    case 'AEM01C';//离院方式
                        if ($item['value'] != 0) {
                            $patientInfo[$select_type][] = ['patient_info.AEM01C', $operator, $value];
                        }
                        break;
                    case 'ABA01N';//门急诊诊断
                        $patientMedicalInfo[$select_type][] = ['pmi.ABA01N', $operator, $value];
                        break;
                    case 'ABA01C';//门急诊疾病编码
                        $patientMedicalInfo[$select_type][] = ['pmi.ABA01C', $operator, $value];
                        break;
                    case 'RJSS';//日间手术
                        if ($item['value'] != 0) {
                            $sSType = ['', '是', '否'];
                            $mainOperation[$select_type][] = ['SFWRJSS', $operator, $sSType[$item['value']]];
                            $secondaryOperation[$select_type][] = ['SFWRJSS', $operator, $sSType[$item['value']]];
                        }
                        break;
                    case 'AAA28';//病案号
                        $patientInfo[$select_type][] = ['patient_info.AAA28', $operator, $value];
                        break;
                    case 'AAA01';//姓名
                        $patientInfo[$select_type][] = ['patient_info.AAA01', $operator, $value];
                        break;
                    case 'AAA02C';//性别
                        $patientInfo[$select_type][] = ['patient_info.AAA02C', $operator, $value];
                        break;
                    case 'AAA29';//住院次数
                        $patientInfo[$select_type][] = ['patient_info.AAA29', $operator, $value];
                        break;
                    case 'YZMC';//医嘱名称
                        $YZB[$select_type][] = ['yzb.YZMC', $operator, $value];
                        break;
                    case 'BRKS';//医嘱名称
                        $YZB[$select_type][] = ['yzb.BRKS', $operator, $value];
                        break;
                    case 'YZQX';//医嘱名称
                        $YZB[$select_type][] = ['yzb.YZQX', $operator, $value];
                        break;
                    case 'KZKS';//医嘱名称
                        $YZB[$select_type][] = ['yzb.KZKS', $operator, $value];
                        break;
                    case 'SSPB': // 手术判别
                        if ($value < 5) {
                            $mainOperation[$select_type][] = ['SSPB', $operator, $value];
                            $secondaryOperation[$select_type][] = ['SSPB', $operator, $value];
                        }
                        break;
                    default;
                }
            }
        }
        $data = DoctorAdviceService::getList(
            $patientInfo,
            $mainDiagnosis,
            $otherDiagnosis,
            $mainOperation,
            $secondaryOperation,
            $patientMedicalInfo,
            $YZB,
            $offset,
            $limit
        );
        if (!empty($data['list'])){
            $department = DepartmentService::getDepartmentList();
            foreach ($data['list'] as &$item){
                $item['YZQX'] = $item['YZQX'] == 1 ? '长期医嘱': '临时医嘱';
                $item['BRKS'] = $department[$item['BRKS']] ?? '';
                $item['KZKS'] = $department[$item['KZKS']] ?? '';
            }
        }
        return ToolsService::returnData(200,$data);
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
