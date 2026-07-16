<?php

namespace App\Http\Controllers\Api;

use App\Model\DataSyncLog;
use App\Model\Error;
use App\Model\Staff;
use App\Model\CaseRule;
use App\Model\QueueList;
use App\Model\Department;
use App\Model\CaseQuality;
use App\Model\ErrorV2;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLSY;
use App\Model\FeeDetailed;
use App\Model\PatientInfo;
use App\Model\RuleWordMap;
use App\Model\RuleSetting;
use App\Exports\ExportData;
use App\Model\BigModelList;
use App\Services\CsvService;
use Illuminate\Http\Request;
use App\Services\CaseService;
use App\Services\UserService;
use App\Services\ToolsService;
use App\Services\TargetService;
use Illuminate\Support\Facades\DB;
use App\Http\Service\ExportService;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Model\BlsyQulist;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\ElasticsearchService;

class QualityController extends Controller
{
    /**
     * 获取质控栏 tab 菜单
     */
    public function getQualityTabMenu()
    {
        // 读取三个功能的开关状态（通过ID）
        $homeStatus = (int)RuleWordMap::query()->where('id', 8207)->value('keyword');
        $recordStatus = (int)RuleWordMap::query()->where('id', 8208)->value('keyword');
        $generateStatus = (int)RuleWordMap::query()->where('id', 8209)->value('keyword');

        $data = [];

        // 根据开关状态返回对应的菜单项
        if ($homeStatus === 1) {
            $data[] = '首页问题';
        }
        if ($recordStatus === 1) {
            $data[] = '病历问题';
        }
        if ($generateStatus === 1) {
            $data[] = '病历生成';
        }

        return ToolsService::returnData(200, $data, 'success');
    }

    /**
     * getHomeQualityList
     * 病案列表
     * @group quality
     * @bodyParam AAA28 string  病案号
     * @bodyParam AAC11N string  出院科室
     * @bodyParam AAA26C string  付款方式
     * @bodyParam status string  编辑状态
     * @bodyParam level string  编辑状态
     * @bodyParam AAC01 string  出院时间
     * @bodyParam AAA04 string  年龄
     * @bodyParam AAA40 string  不足一周岁年龄
     * @bodyParam coder_id string  编码员ID
     * @bodyParam field object  字段条件
     * @bodyParam page string required 页码
     * @bodyParam limit string required 条数
     *
     * @response {
     *  "code":200,
     *  "msg":"",
     *  "data":{
     *      "list": [{
     *          "AAA28":"",
     *          "AAA01":"",
     *          "AAA02C":"",
     *          "AAA04":"",
     *          "AAA29":"",
     *          "AAC11N":"",
     *          "AAC01":"",
     *          "ADA01":"",
     *          "F_D":"",
     *          "J":"",
     *          "ABC01N":"",
     *          "ICD9_NAME":"",
     *          "AAC04":"",
     *          "ATTEND_GRP_NAME":"",
     *          "AAC01":"",
     *          "AAB06C":""
     *      }],
     *      "count":100
     *  },
     *  "time":123787842
     * }
     */
    public function getHomeQualityList(Request $request)
    {
        $isExport = $request->post("is_export", 0);
        $where = [];
        //病案号
        $AAA28 = $request->post('AAA28', '');
        if (!empty($AAA28)) {
            $where['patient_info.AAA28'] = $AAA28;
        }
        //天-年龄
        $age_start = $request->post('AAA40', 0);
        $age_end = $request->post('AAA04', 0);
        $age_end_type = $request->post('age_end_type', 0);
        $age_start_type = $request->post('age_start_type', 0);

        //出院科室
        $AAC11C = $request->post('AAC11C', 'all');
        if ($AAC11C != 'all' && !is_null($AAC11C)) {
            $where['AAC11C'] = $AAC11C;
        }
        //付款方式
        $AAA26C = $request->post('AAA26C', 'all');
        if ($AAA26C != 'all' && !is_null($AAA26C)) {
            $where['AAA26C'] = $AAA26C;
        }
        //编辑状态
        $status = $request->post('status', 'all');
        if ($status != 'all' && !is_null($status)) {
            $where['status'] = $status;
        }
        //质控状态
        $ORG_STATE = $request->post('ORG_STATE', 'all');
        if ($ORG_STATE != 'all' && !is_null($ORG_STATE)) {
            $where['ORG_STATE'] = $ORG_STATE;
        }
        //编码员ID
        $coder_id = $request->post('coder_id', 'all');
        if ($coder_id != 'all' && !is_null($coder_id)) {
            $where['AEE08'] = $coder_id;
        }
        //字段条件
        $field = $request->post('field', '');
        if ($field != '') {
            if ($isExport == 1) {
                $field = json_decode($field, true);
            }
            $field = array_column($field, null, 'key');
        } else {
            $field = [];
        }
        $page = $isExport == 1 ? 1 : $request->post('page', 1);
        $limit = $isExport == 1 ? 1000000 : $request->post('limit', 20);
        $offset = ($page - 1) * $limit;
        //问题属性
        $level = $request->post('level', 'all');
        $source = $request->post('source', 0);
        if ($level != 'all') {
            $inWhereQuery = Error::query()->where('source', $source);
            $inWhereQuery->where('level', $level);
            $count = $inWhereQuery->count('id');
            $inWhereQuery = $inWhereQuery->groupBy('AAA28')
                ->orderBy('created_at', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->pluck('AAA28');
            $inWhere = !empty($inWhereQuery->toArray()) ? $inWhereQuery->toArray() : [];
        } else {
            $count = null;
            $inWhere = [];
        }

        //出院时间
        $startDate = $request->post('AAC01_start_date', null);
        $endDate = $request->post('AAC01_end_date', null);
        $betweenWhere = [];
        if (!empty($startDate) && !empty($endDate)) {
            $startDate = date('Y-m-d', strtotime($startDate)) . ' 00:00:00';
            $endDate = date('Y-m-d', strtotime($endDate)) . ' 23:59:59';
            $betweenWhere = [$startDate, $endDate];
        }
        $data = QualityService::getHomeQualityList($where, $betweenWhere, $inWhere, $offset, $limit, $age_start, $age_end, $field, $age_start_type, $age_end_type);
        if ($count != null) {
            $data['count'] = $count;
        }
        if ($isExport == 1) {
            return $this->qualityListExportData($data, $source);
        }

        return ToolsService::returnData(200, $data);
    }

    public static function isNull($value)
    {
        return is_null($value) || $value == 'null';
    }

    /**
     *qualityList
     * 病案列表
     * @group quality
     * @bodyParam bllb string 病例名称
     * @bodyParam search_keyword string 关键词搜索
     * @bodyParam AAA28 string  病案号
     * @bodyParam AAC11N string  出院科室
     * @bodyParam AAA26C string  付款方式
     * @bodyParam status string  编辑状态
     * @bodyParam level string  编辑状态
     * @bodyParam AAC01 string  出院时间
     * @bodyParam AAA04 string  年龄
     * @bodyParam AAA40 string  不足一周岁年龄
     * @bodyParam coder_id string  编码员ID
     * @bodyParam field object  字段条件
     * @bodyParam page string required 页码
     * @bodyParam limit string required 条数
     *
     * @response {
     *  "code":200,
     *  "msg":"",
     *  "data":{
     *      "list": [{
     *          "AAA28":"",
     *          "AAA01":"",
     *          "AAA02C":"",
     *          "AAA04":"",
     *          "AAA29":"",
     *          "AAC11N":"",
     *          "AAC01":"",
     *          "ADA01":"",
     *          "F_D":"",
     *          "J":"",
     *          "ABC01N":"",
     *          "ICD9_NAME":"",
     *          "AAC04":"",
     *          "ATTEND_GRP_NAME":"",
     *          "AAC01":"",
     *          "AAB06C":""
     *      }],
     *      "count":100
     *  },
     *  "time":123787842
     * }
     */
    public function qualityList(Request $request)
    {
        $isExport = $request->post("is_export", 0);
        $patientInfo = [];
        $patientHospitalInfo = [];
        $patientDoctorInfo = [];
        $mainDiagnosis = [];
        $otherDiagnosis = [];
        $mainOperation = [];
        $secondaryOperation = [];
        $patientMedicalInfo = [];
        $icu = [];
        $patientAdd = [];
        $LNSSQ = '';
        $LNSSH = '';
        $error = [];
        $emr_bl_bl01Info = [];
        $bllb_info = [];
        $page = $isExport == 1 ? 1 : $request->post('page', 1);
        $limit = $isExport == 1 ? 1000000 : $request->post('limit', 20);
        $offset = ($page - 1) * $limit;
        //病案号
        $AAA28 = $request->post('AAA28', '');
        if (!empty($AAA28) && !static::isNull($AAA28)) {
            $patientInfo['and'][] = ['patient_info.AAA28', '=', $AAA28];
        }
        //出院科室
        $AAC11C = $request->post('AAC11C', 'all');
        if ($AAC11C != 'all' && !static::isNull($AAC11C)) {
            //$patientHospitalInfo['phi.AAC11C'] = $AAC11C;
            $patientHospitalInfo['and'][] = ['phi.AAC11C', '=', $AAC11C, 'and'];
        }
        //问题属性
        $level = $request->post('level', 'all');
        $source = $request->post('source', 0);
        if ($level != 'all') {
            $error['and'][] = ['e.level', '=', $level];
            $error['and'][] = ['e.source', '=', $source];
        }
        //付款方式
        $AAA26C = $request->post('AAA26C', 'all');
        if ($AAA26C != 'all' && !static::isNull($AAA26C)) {
            $patientInfo['and'][] = ['AAA26C', '=', $AAA26C];
        }
        //质控状态
        $status = $request->post('status', 'all');
        if ($status != 'all' && !static::isNull($status)) {
            $patientInfo['and'][] = ['status', '=', $status];
        }
        //质控状态
        $ORG_STATE = $request->post('ORG_STATE', 'all');
        if ($ORG_STATE != 'all' && !static::isNull($ORG_STATE)) {
            $patientInfo['and'][] = ['ORG_STATE', '=', $ORG_STATE];
        }
        //出院时间
        $startDate = $request->post('AAC01_start_date', null);
        $endDate = $request->post('AAC01_end_date', null);
        $start = $request->post('start_time');
        if (!empty($start)) {
            $startDate = $start;
        }
        $end = $request->post('end_time');
        if (!empty($end)) {
            $endDate = $end;
        }
        if (!empty($startDate)) {
            $startDate = date('Y-m-d', strtotime($startDate)) . ' 00:00:00';
            $patientInfo['and'][] = ['patient_info.AAC01', '>=', $startDate];
        }
        if (!empty($endDate)) {
            $endDate = date('Y-m-d', strtotime($endDate)) . ' 23:59:59';
            $patientInfo['and'][] = ['patient_info.AAC01', '<=', $endDate];
        }
        //编码员ID
        $coder_id = $request->post('coder_id', 'all');
        if ($coder_id != 'all' && !static::isNull($coder_id)) {
            $patientDoctorInfo['AEE08'] = $coder_id;
        }
        //字段条件
        $field = $request->post('field', '');
        if ($isExport == 1) {
            $field = json_decode($field, true);
        }
        if ($field != '' && !self::isNull($field)) {
            $field = array_column($field, null, 'key');
            foreach ($field as $item) {
                if ($item['select_type'] == 1) {
                    $item['select_type'] = "or";
                } elseif ($item['select_type'] == 2) {
                    $item['select_type'] = "no";
                } else {
                    $item['select_type'] = "and";
                }
                if ($item['type'] == 0) {
                    if ($item['select_type'] == 'no') {
                        $operator = 'not like';
                    } else {
                        $operator = 'like';
                    }
                    $value = "%" . $item['value'] . "%";
                } else {
                    if ($item['select_type'] == 'no') {
                        $operator = '!=';
                    } else {
                        $operator = '=';
                    }
                    $value = $item['value'];
                }
                switch ($item['key']) {
                    case 'ABC01N';
                        $mainDiagnosis[$item['select_type']][] = ['md.ICD10_NAME', $operator, $value];
                        break;
                    case 'ABC01C';
                        $mainDiagnosis[$item['select_type']][] = ['md.ICD10_ID1', $operator, $value,];
                        break;
                    case 'ICD10_ID1_first';
                        $otherDiagnosis[$item['select_type']][] = ['ICD10_ID1', $operator, $value];
                        $otherDiagnosis[$item['select_type']][] = ['DIA_ORDER', '=', 1];
                        break;
                    case 'ICD10_NAME_first';
                        $otherDiagnosis[$item['select_type']][] = ['ICD10_NAME', $operator, $value];
                        $otherDiagnosis[$item['select_type']][] = ['DIA_ORDER', '=', 1];
                        break;
                    case 'ICD10_ID1';
                        $otherDiagnosis[$item['select_type']][] = ['ICD10_ID1', $operator, $value];
                        break;
                    case 'ICD10_NAME';
                        $otherDiagnosis[$item['select_type']][] = ['ICD10_NAME', $operator, $value];
                        break;
                    case 'ICD9_ID1';
                        $mainOperation[$item['select_type']][] = ['ICD9_ID1', $operator, $value];
                        $secondaryOperation[$item['select_type']][] = ['ICD9_ID1', $operator, $value];
                        break;
                    case 'ICD9_NAME';
                        $mainOperation[$item['select_type']][] = ['ICD9_NAME', $operator, $value];
                        $secondaryOperation[$item['select_type']][] = ['ICD9_NAME', $operator, $value];
                        break;
                    case 'ICD8_ID1';
                        $secondaryOperation[$item['select_type']][] = ['ICD9_ID1', $operator, $value];
                        break;
                    case 'ICD8_NAME';
                        $secondaryOperation[$item['select_type']][] = ['ICD9_NAME', $operator, $value];
                        break;
                    case 'ABC03C'; //主要诊断入院病情
                        if ($item['value'] > 0) {
                            $patientMedicalInfo[$item['select_type']][] = ["pmi.ABC03C", $operator, $item['value']];
                        }
                        break;
                    case 'RYQK'; //其他诊断入院病情
                        if ($item['value'] > 0) {
                            $otherDiagnosis[$item['select_type']][] = ["RYQK", $operator, $item['value']];
                        }
                        break;
                    case 'OPE_LEVEL'; //手术级别
                        if ($item['value'] != 0) {
                            $mainOperation[$item['select_type']][] = ['OPE_LEVEL', $operator, $item['value']];
                            $secondaryOperation[$item['select_type']][] = ['OPE_LEVEL', $operator, $item['value']];
                        }
                        break;
                    case 'IS_MAIN_WAY'; //重症监护室名称
                        $icu[$item['select_type']][] = ['icu.IS_MAIN_WAY', $operator, $value];
                        break;
                    case 'AEM01C'; //离院方式
                        if ($item['value'] != 0) {
                            $patientInfo[$item['select_type']][] = ['patient_info.AEM01C', $operator, $item['value']];
                        }
                        break;
                    case 'ABA01N'; //门急诊诊断
                        $patientMedicalInfo[$item['select_type']][] = ['pmi.ABA01N', $operator, $value];
                        break;
                    case 'ABF01N'; //病理诊断名称
                        $patientMedicalInfo[$item['select_type']][] = ['pmi.ABF01N', $operator, $value];
                        break;
                    case 'ABF01C'; //病理诊断编码
                        $patientMedicalInfo[$item['select_type']][] = ['pmi.ABF01C', $operator, $value];
                        break;
                    case 'ABA01C'; //门急诊疾病编码
                        $patientMedicalInfo[$item['select_type']][] = ['pmi.ABA01C', $operator, $value];
                        break;
                    case 'AEL01'; //呼吸机
                        if ($item['value'] == 1) {
                            $patientMedicalInfo[$item['select_type']][] = ['pmi.AEL01', '>', 0];
                        } elseif ($item['value'] == 2) {
                            $patientMedicalInfo[$item['select_type']][] = ['pmi.AEL01', '<=', 0];
                        }
                        break;
                    case 'RJSS'; //日间手术
                        if ($item['value'] != 0) {
                            $sSType = ['', '是', '否'];
                            $mainOperation[$item['select_type']][] = ['RJSS', '=', $sSType[$item['value']]];
                            $secondaryOperation[$item['select_type']][] = ['RJSS', '=', $sSType[$item['value']]];
                        }
                        break;
                    case 'LNSSQ'; //颅脑损伤前昏迷
                        if ($item['value'] == 1) {
                            $LNSSQ = '(pmi.AEJ01 > 0 or pmi.AEJ02 > 0 or pmi.AEJ03 > 0)';
                        } elseif ($item['value'] == 2) {
                            $LNSSQ = '(pmi.AEJ01 <= 0 or pmi.AEJ02 <= 0 or pmi.AEJ03 <= 0)';
                        }
                        break;
                    case 'LNSSH'; //颅脑损伤后昏迷
                        if ($item['value'] == 1) {
                            $LNSSH = '(pmi.AEJ04 > 0 or pmi.AEJ05 > 0 or pmi.AEJ06 > 0)';
                        } elseif ($item['value'] == 2) {
                            $LNSSH = '(pmi.AEJ04 <= 0 or pmi.AEJ05 <= 0 or pmi.AEJ06 <= 0)';
                        }
                        break;
                    case 'AAA28'; //病案号
                        $patientInfo[$item['select_type']][] = ['patient_info.AAA28', $operator, $value];
                        break;
                    case 'AAA01'; //姓名
                        $patientInfo[$item['select_type']][] = ['patient_info.AAA01', $operator, $value];
                        break;
                    case 'AAA02C'; //性别
                        $patientInfo[$item['select_type']][] = ['patient_info.AAA02C', $operator, $value];
                        break;
                    case 'AAB06C'; //入院途径
                        $patientInfo[$item['select_type']][] = ['patient_info.AAB06C', $operator, $value];
                        break;
                    //                    case 'AAA04';//年龄
                    //                        $patientInfo[$item['select_type']][] = ['patient_info.AAA04', '=', $item['value']];
                    //                        break;
                    case 'AAA29'; //住院次数
                        $patientInfo[$item['select_type']][] = ['patient_info.AAA29', $operator, $item['value']];
                        break;
                    case 'SSPB': // 手术判别
                        if ($value < 5) {
                            $mainOperation[$item['select_type']][] = ['SSPB', $operator, $item['value']];
                            $secondaryOperation[$item['select_type']][] = ['SSPB', $operator, $item['value']];
                        }
                        break;
                    case 'AAC11N': // 出院科室
                        if ($item['value'] != 'all') {
                            $patientHospitalInfo[$item['select_type']][] = ['phi.AAC11C', $operator, $item['value']];
                        }
                        break;
                    default;
                }
            }
        }
        //天-年龄
        $age_start = $request->post('AAA40', 0);
        $age_end = $request->post('AAA04', 0);
        $age_end_type = $request->post('age_end_type', 0);
        $age_start_type = $request->post('age_start_type', 0);
        if ($age_start_type == $age_end_type) {
            if ($age_start_type == 1) { //天
                if ($age_start > 0 && $age_end > 0) {
                    $patientInfo['and'][] = ['patient_info.AAA40', '>=', $age_start];
                    $patientInfo['and'][] = ['patient_info.AAA40', '<=', $age_end];
                } elseif ($age_start > 0 && $age_end <= 0) {
                    $patientInfo['and'][] = ['patient_info.AAA40', '>=', $age_start];
                } elseif ($age_start <= 0 && $age_end > 0) {
                    $patientInfo['and'][] = ['patient_info.AAA40', '<=', $age_end];
                }
            }
            if ($age_start_type == 2) { //年
                if ($age_start > 0 && $age_end > 0) {
                    $patientInfo['and'][] = ['patient_info.AAA04', '>=', $age_start];
                    $patientInfo['and'][] = ['patient_info.AAA04', '<=', $age_end];
                } elseif ($age_start > 0 && $age_end <= 0) {
                    $patientInfo['and'][] = ['patient_info.AAA04', '>=', $age_start];
                } elseif ($age_start <= 0 && $age_end > 0) {
                    $patientInfo['and'][] = ['patient_info.AAA04', '<=', $age_end];
                }
            }
        } elseif (($age_start_type == 1) && ($age_end_type == 2)) { //多少天-多少年
            if ($age_start > 0 && $age_end > 0) {
                $patientInfo['and'][] = ['patient_info.AAA40', '>=', $age_start];
                $patientInfo['and'][] = ['patient_info.AAA04', '<=', $age_end];
            } elseif ($age_start > 0 && $age_end <= 0) {
                $patientInfo['and'][] = ['patient_info.AAA40', '>=', $age_start];
            } elseif ($age_start <= 0 && $age_end > 0) {
                $patientInfo['and'][] = ['patient_info.AAA04', '<=', $age_end];
            }
        }
        //住院天数
        $AAC0401 = $request->post('AAC0401', 0);
        $AAC0402 = $request->post('AAC0402', 0);
        if ($AAC0401 > 0 && $AAC0402 > 0) {
            $patientInfo['and'][] = ['patient_info.AAC04', [$AAC0401, $AAC0402]];
        } elseif ($AAC0401 > 0 && $AAC0402 <= 0) {
            $patientInfo['and'][] = ['patient_info.AAC04', '>=', $AAC0401];
        } elseif ($AAC0401 <= 0 && $AAC0402 > 0) {
            $patientInfo['and'][] = ['patient_info.AAC04', '<=', $AAC0402];
        }
        //关键词搜索
        $search_keyword = $request->post("search_keyword", "");
        if (!empty($search_keyword)) {
            $emr_bl_bl01Info = ["EMR_BL_BL01.BLMC", 'like', "%" . $search_keyword . "%"];
            $bllb = $request->post("bllb", 0);
            if ($bllb == 0) {
                $bllb_config = [
                    1, //出院记录
                    292, //入院记录
                    294, //病程记录
                    43, //病历讨论记录   会诊记录？
                    303, //手术记录
                    49, //医嘱
                    //收费明细？
                    2000002, //报告单
                ];
                $bllb_info = ['EMR_BL_BL01.BLLB', 'in', $bllb_config];
            } else {
                $bllb_info = ['EMR_BL_BL01.BLLB', '=', $bllb];
            }
        }
        $data = QualityService::getList(
            $patientInfo,
            $patientHospitalInfo,
            $patientDoctorInfo,
            $mainDiagnosis,
            $otherDiagnosis,
            $mainOperation,
            $secondaryOperation,
            $patientMedicalInfo,
            $icu,
            $patientAdd,
            $emr_bl_bl01Info,
            $bllb_info,
            $isExport,
            $offset,
            $limit,
            $LNSSQ,
            $LNSSH,
            $error
        );

        if ($isExport == 1) {
            return $this->qualityListExportData($data, $source);
        }

        if ($request->post('is_tm') == 1) {
            foreach ($data['list'] as &$datum) {
                $datum['AAA01'] = desensitize($datum['AAA01'], 1, 0, '*');
            }
        }
        return ToolsService::returnData(200, $data);
    }

    /**
     * 病案列表导出
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    protected function qualityListExportData($data, $source)
    {
        $exportData = [];
        $exportData[] = [
            '序号',
            '病案号',
            '患者名称',
            '性别',
            '是否位日间手术',
            '年龄',
            '主诊断名称',
            '主诊断编码',
            '主手术名称',
            '主手术编码',
            '住院次数',
            '医保类型',
            '出院科室',
            '出院日期',
            '总费用',
            '药品费用',
            '材料费用',
            // '主诊断','主手术',
            '实际住院（天）',
            '主诊组',
            '离院方式',
            '入院途径',
            // 新增
            '平均费用',
            '平均住院日',
            '手术判别',
            '入院科室',
            '入院时间',
            '入院病情',
            '手术级别',
            '门急诊诊断',
            '门急诊诊断编码',
            '有创呼吸机使用时间',
            '颅脑损伤昏迷时间 入院前',
            '颅脑损伤昏迷时间 入院后'
        ];
        $index = 1;
        foreach ($data['list'] as $key => $val) {
            $exportData[$key + 1] = [
                $index,
                $val['AAA28'] ?? '',
                $val['AAA01'] ?? '',
                $val['AAA02C'] ?? '',
                $val['RJSS'] ?? '',
                $val['AAA04'] ?? '',
                $val['ABC01N'] ?? '',
                $val['ABC01C'] ?? '',
                $val['ICD9_NAME'] ?? '',
                $val['ICD9_ID1'] ?? '',
                $val['AAA29'] ?? '',
                $val['AAA26C'] ?? '',
                $val['AAC11N'] ?? '',
                $val['AAC01'] ?? '',
                $val['ADA01'] ?? '',
                $val['F_D'] ?? '',
                $val['J'] ?? '',
                // $val['ABC01N']??'',$val['ICD9_NAME']??'',
                $val['AAC04'] ?? '',
                $val['ATTEND_GRP_NAME'] ?? '',
                $val['AEM01C'] ?? '',
                $val['AAB06C'] ?? '',

                $val['ARG_F_D'] ?? '',
                $val['ARG_STAY'] ?? '',
                $val['SSPB'] ?? '',
                $val['AAB11N'] ?? '',
                $val['AAB01'] ?? '',
                $val['AAC04'] ?? '',
                $val['OPE_LEVEL'] ?? '',
                $val['ABA01N'] ?? '',
                $val['ABA01C'] ?? '',
                $val['AEL01'] ?? '',
                $val['LNSSQ'] ?? '',
                $val['LNSSH'] ?? '',
            ];
            $index++;
        }

        ## 导出CSV
        $csv = new CsvService();
        $csv->filename = $csv->charset('病案列表', 'UTF-8');

        return $csv->export($exportData, false);

        /**
         * $exportData = [];
         * $index = 1;
         * foreach ($data['list'] as $key => $val) {
         * $exportData[$key] = [
         * $index,
         * $val['AAA28'] ?? '', $val['AAA01'] ?? '', $val['AAA02C'] ?? '', $val['RJSS'] ?? '', $val['AAA04'] ?? '',
         * $val['ABC01N'] ?? '', $val['ABC01C'] ?? '', $val['ICD9_NAME'] ?? '', $val['ICD9_ID1'] ?? '', $val['AAA29'] ?? '',
         * $val['AAA26C'] ?? '', $val['AAC11N'] ?? '', $val['AAC01'] ?? '', $val['ADA01'] ?? '', $val['F_D'] ?? '',
         * $val['J'] ?? '',
         * // $val['ABC01N']??'',$val['ICD9_NAME']??'',
         * $val['AAC04'] ?? '', $val['ATTEND_GRP_NAME'] ?? '', $val['AEM01C'] ?? '', $val['AAB06C'] ?? '',
         *
         * $val['ARG_F_D'] ?? '', $val['ARG_STAY'] ?? '', $val['SSPB'] ?? '', $val['AAB11N'] ?? '', $val['AAB01'] ?? '',
         * $val['AAC04'] ?? '', $val['OPE_LEVEL'] ?? '', $val['ABA01N'] ?? '', $val['ABA01C'] ?? '', $val['AEL01'] ?? '',
         * $val['LNSSQ'] ?? '', $val['LNSSH'] ?? '',
         * ];
         * $index++;
         * }
         * ## 文件名
         * $fileName = $source == 1 ? '病案列表.xlsx' : '病案列表.xlsx';
         * ## 表头
         * $title = [
         * '序号',
         * '病案号', '患者名称', '性别', '是否位日间手术', '年龄',
         * '主诊断名称', '主诊断编码', '主手术名称', '主手术编码', '住院次数',
         * '医保类型', '出院科室', '出院日期', '总费用', '药品费用',
         * '材料费用',
         * // '主诊断','主手术',
         * '实际住院（天）', '主诊组', '离院方式', '入院途径',
         * // 新增
         * '平均费用', '平均住院日', '手术判别', '入院科室', '入院时间',
         * '入院病情', '手术级别', '门急诊诊断', '门急诊诊断编码', '有创呼吸机使用时间',
         * '颅脑损伤昏迷时间 入院前', '颅脑损伤昏迷时间 入院后'
         * ];
         * ## 公共导出
         * return Excel::download(new ExportData($title, $exportData), $fileName);
         * */
    }

    /**
     * 获取时间配置信息
     * @return array
     */
    public static function getYearConfig()
    {
        return [
            [
                'id' => 4,
                'name' => date('Y', strtotime('-3 year')),
                'start' => date('Y-01-01', strtotime('-3 year')),
                'end' => date('Y-12-31', strtotime('-3 year'))
            ],
            [
                'id' => 3,
                'name' => date('Y', strtotime('-2 year')),
                'start' => date('Y-01-01', strtotime('-2 year')),
                'end' => date('Y-12-31', strtotime('-2 year'))
            ],
            [
                'id' => 2,
                'name' => date('Y', strtotime('-1 year')),
                'start' => date('Y-01-01', strtotime('-1 year')),
                'end' => date('Y-12-31', strtotime('-1 year'))
            ],
            [
                'id' => 1,
                'name' => date('Y'),
                'start' => date('Y-01-01'),
                'end' => date('Y-12-31')
            ],
        ];
    }

    /**
     * 获取季度配置信息
     * @return array
     */
    public static function getQuarterConfig()
    {
        return [
            [
                'id' => 1,
                'name' => '第一季度',
                'start' => '01-01',
                'end' => '03-31'
            ],
            [
                'id' => 2,
                'name' => '第二季度',
                'start' => '04-01',
                'end' => '06-31'
            ],
            [
                'id' => 3,
                'name' => '第三季度',
                'start' => '07-01',
                'end' => '09-31'
            ],
            [
                'id' => 4,
                'name' => '第四季度',
                'start' => '10-01',
                'end' => '12-31'
            ],
        ];
    }

    /**
     * 获取月份配置信息
     * @return array
     */
    public static function getMonthConfig()
    {
        return [
            [
                'id' => 1,
                'name' => '一月份',
                'start' => '01-01',
                'end' => '01-31'
            ],
            [
                'id' => 2,
                'name' => '二月份',
                'start' => '02-01',
                'end' => '02-30'
            ],
            [
                'id' => 3,
                'name' => '三月份',
                'start' => '03-01',
                'end' => '03-31'
            ],
            [
                'id' => 4,
                'name' => '四月份',
                'start' => '4-01',
                'end' => '4-30'
            ],
            [
                'id' => 5,
                'name' => '五月份',
                'start' => '05-01',
                'end' => '05-31'
            ],
            [
                'id' => 6,
                'name' => '六月份',
                'start' => '06-01',
                'end' => '06-30'
            ],
            [
                'id' => 7,
                'name' => '七月份',
                'start' => '07-01',
                'end' => '07-31'
            ],
            [
                'id' => 8,
                'name' => '八月份',
                'start' => '08-01',
                'end' => '08-31'
            ],
            [
                'id' => 9,
                'name' => '九月份',
                'start' => '09-01',
                'end' => '09-30'
            ],
            [
                'id' => 10,
                'name' => '十月份',
                'start' => '10-01',
                'end' => '10-31'
            ],
            [
                'id' => 11,
                'name' => '十一月份',
                'start' => '11-01',
                'end' => '11-30'
            ],
            [
                'id' => 12,
                'name' => '十二月份',
                'start' => '12-01',
                'end' => '12-31'
            ],
        ];
    }

    /**
     * selectInfo
     * 筛选数据
     * @group quality
     *
     * @response {
     *  "code": 200,
     *  "msg": "",
     *  "data":{
     *      "coder": [{
     *          "id":"1",
     *          "name":"text"
     *      }],
     *      "pay": [{
     *          "id":"1.1",
     *          "name":"1.1 - 本市城镇职工基本医疗保险"
     *      }],
     *      "editStatus": [{
     *              "id":"all",
     *              "name":"全部"
     *          },
     *          {
     *              "id":"0",
     *              "name":"未编辑"
     *          },
     *          {
     *          "id":"1",
     *          "name":"已编辑"
     *      }],
     *      "department": [{
     *          "id":"2",
     *          "name":"text"
     *      }],
     *      "level":[{
     *          "id":"all",
     *          "name":"全部"
     *      },{
     *          "id":"0",
     *          "name":"强制"
     *      },{
     *          "id":"1",
     *          "name":"建议"
     *      }],
     *      "IN_STATUS":[{
     *          "id":"all",
     *          "name":"全部"
     *      }],
     *      "field":[{
     *          "id":"1",
     *          "name":"text"
     *      }]
     *  },
     *  "time": 123787842
     * }
     */
    public function selectInfo(Request $request)
    {
        $coder = array_merge([['id' => 'all', 'name' => '全部']]);
        $status = [['id' => 'all', 'name' => '全部'], ['id' => '0', 'name' => '未编辑'], ['id' => '1', 'name' => '已编辑']];

        $pay = config('dictionaries.AAA26C');
        $payData[] = ['id' => 'all', 'name' => '全部'];
        foreach ($pay as $key => $item) {
            $payData[] = ['id' => $key, 'name' => $item];
        }

        //        $department[] = ['id'=>'all','name'=>'全部'];
        $department = config('dictionaries.gjss_cyks'); //DepartmentService::getDepartmentList();
        //        $department = array_merge([['id'=>'all','name'=>'全部']],DepartmentService::getDepartmentList());

        $IN_STATUS = config("dictionaries.IN_STATUS");
        $IN_STATUS_data[] = ['id' => 'all', 'name' => '全部'];
        foreach ($IN_STATUS as $key => $item) {
            $IN_STATUS_data[] = ['id' => $key, 'name' => $item];
        }

        return ToolsService::returnData(
            200,
            [
                'coder' => $coder,
                'status' => $status,
                'pay' => $payData,
                'IN_STATUS' => $IN_STATUS_data,
                'department' => $department,
                'level' => [
                    ['id' => 'all', 'name' => '全部'],
                    ['id' => '0', 'name' => '强制'],
                    ['id' => '1', 'name' => '建议']
                ],
                'field' => [
                    ['id' => 'ABC01N', 'name' => '主要诊断名称'],
                    ['id' => 'ABC01C', 'name' => '主要诊断编码'],
                    ['id' => 'ICD10_ID1_first', 'name' => '第一其他诊断编码'],
                    ['id' => 'ICD10_NAME_first', 'name' => '第一其他诊断名称'],
                    ['id' => 'ICD10_ID1', 'name' => '其他诊断编码'],
                    ['id' => 'ICD10_NAME', 'name' => '其他诊断名称'],
                    ['id' => 'ICD9_ID1', 'name' => '手术编码'],
                    ['id' => 'ICD9_NAME', 'name' => '手术名称'],
                    ['id' => 'ABC03C', 'name' => '主要诊断入院病情'],
                    ['id' => 'RYQK', 'name' => '其他诊断入院病情'],
                    ['id' => 'OPE_LEVEL', 'name' => '手术级别'],
                    //                    ['id'=>'IS_MAIN_WAY','name'=>'重症监护室名称'],
                    ['id' => 'AEM01C', 'name' => '离院方式'],
                    ['id' => 'ABA01N', 'name' => '门（急）诊诊断'],
                    ['id' => 'ABA01C', 'name' => '门急诊的疾病编码'],
                    ['id' => 'AEL01', 'name' => '有创呼吸机使用时间', 'value' => ['0' => ['key' => '1', 'value' => '有'], '1' => ['key' => '2', 'value' => '无']]],
                    ['id' => 'RJSS', 'name' => '是否为日间手术'],
                    ['id' => 'LNSSQ', 'name' => '颅脑损伤昏迷时间 入院前', 'value' => ['0' => ['key' => '1', 'value' => '有'], '1' => ['key' => '2', 'value' => '无']]],
                    ['id' => 'LNSSH', 'name' => '颅脑损伤昏迷时间 入院后', 'value' => ['0' => ['key' => '1', 'value' => '有'], '1' => ['key' => '2', 'value' => '无']]],
                    ['id' => 'AAA28', 'name' => '病案号'],
                    ['id' => 'AAA01', 'name' => '姓名'],
                    ['id' => 'AAA02C', 'name' => '性别'],
                    //                    ['id'=>'AAA04','name'=>'年龄'],
                    ['id' => 'AAA29', 'name' => '住院次数'],
                    ['id' => 'SSPB', 'name' => '手术判别'], // 新增
                    ['id' => 'AAC11N', 'name' => '出院科室'],
                    [
                        'id' => 'AAB06C',
                        'name' => '入院途径',
                        'value' =>
                        [
                            '0' => ['key' => '1', 'value' => '急诊'],
                            '1' => ['key' => '2', 'value' => '门诊'],
                            '2' => ['key' => '3', 'value' => '其他医疗机构转入'],
                            '3' => ['key' => '9', 'value' => '其他']
                        ],
                    ],
                    ['id' => 'ABF01N', 'name' => '病理诊断名称'],
                    ['id' => 'ABF01C', 'name' => '病理诊断编码'],
                ],
                'year' => self::getYearConfig(),
                'quarter' => self::getQuarterConfig(),
                'month' => self::getMonthConfig(),
            ]
        );
    }


    /**
     * ruleList
     * 规则列表
     * @group quality
     * @bodyParam text string 缺陷项目
     *
     * @response {
     *  "code": 200,
     *  "msg": "",
     *  "data":[{
     *      "id":1,
     *      "field":"",
     *      "desc":""
     *  }],
     *  "time": 123787842
     * }
     */
    public function ruleList(Request $request)
    {
        $text = $request->post('text');
        $where = [];
        if (!empty($text)) {
            $where = ['field', ['like', '%' . $text . "%"]];
        }
        $data = ErrorRuleService::handleData(ErrorRuleService::getRuleList($where));
        return ToolsService::returnData(200, $data);
    }

    /**
     * errorList
     * 缺陷分析列表
     * @group quality
     * @bodyParam title string start_time 开始时间  时间戳or时间
     * @bodyParam time string end_time 结束时间
     * @bodyParam type int 按年(1)、季度(2)、月(3);
     * @bodyParam error_id int required 错误规则ID
     * @bodyParam coder int required 编码员ID
     * @bodyParam page string required 页码
     * @bodyParam limit string required 条数
     *
     * @response {
     *  "code": 200,
     *  "msg": "",
     *  "data":{
     *      "list":[{
     *         "AAA28":"",
     *         "AAB01":"",
     *         "AAC11N":"",
     *         "AAC01":"",
     *         "AAA01":"",
     *         "desc":"",
     *         "error_name":""
     *      }],
     *      "count":0
     *  },
     *  "time": 123787842
     * }
     */
    public function errorList(Request $request)
    {
        $type = $request->post('type', 1);
        if (empty($type)) {
            $type = 1;
        }
        $errorId = $request->post('error_id');
        if (empty($errorId)) {
            $errorId = false;
        }
        $start_time = $request->post("start_time");
        if (empty($start_time)) {
            $start_time = date('Y-m-d H:i:s', strtotime('-1mouth'));
        }
        $end_time = $request->post("end_time");
        if (empty($end_time)) {
            $end_time = date('Y-m-d H:i:s');
        }
        $coder = $request->post('coder');
        if (empty($coder)) {
            $coder = false;
        }
        $page = $request->post('page', 1);
        $limit = $request->post('limit', 20);
        $query = Error::query();
        if ($type == 1) {
            //年
            $syear = date('Y', strtotime($start_time));
            $eyear = date('Y', strtotime($end_time));
            $query->whereBetween('year', [$syear, $eyear]);
        } else {
            //月
            $smonth = date('m', strtotime($start_time));
            $emonth = date('m', strtotime($end_time));
            $query->whereBetween('year', [$smonth, $emonth]);
        }
        if ($coder) {
            $query->where('order', $coder);
        }
        if ($errorId) {
            $query->where('error_rule', $errorId);
        }
        $offset = ($page - 1) * $limit;
        $count = $query->count('id');
        $data = $query
            ->offset($offset)
            ->limit($limit)
            ->orderBy('created_at', 'desc')
            ->get(['ZYH', 'error_name', 'desc']);
        if ($data) {
            $data = $data->toArray();
        } else {
            $data = [];
        }
        $bahList = array_column($data, 'ZYH');
        $qtData = PatientInfo::query()
            ->join('patient_hospital_info as ph', 'patient_info.MED_REC_ID', '=', 'ph.AAA28')
            ->whereIn('patient_info.MED_REC_ID', $bahList)
            ->get(['patient_info.MED_REC_ID', 'patient_info.AAA28', 'AAA01', 'AAC01', 'AAC11N', 'ph.AAB01'])
            ->keyBy('MED_REC_ID');
        if ($qtData) {
            $qtData = $qtData->toArray();
        } else {
            $qtData = [];
        }
        foreach ($data as &$item) {
            $item['AAA28'] = $qtData[$item['ZYH']]['AAA28'] ?? '';
            $item['AAA01'] = $qtData[$item['ZYH']]['AAA01'] ?? '';
            $item['AAC01'] = $qtData[$item['ZYH']]['AAC01'] ?? '';
            $item['AAC11N'] = $qtData[$item['ZYH']]['AAC11N'] ?? '';
            $item['AAB01'] = $qtData[$item['ZYH']]['AAB01'] ?? '';
            $item['MED_REC_ID'] = $qtData[$item['ZYH']]['MED_REC_ID'] ?? '';
        }

        $isExport = $request->post("is_export", 0);
        if ($isExport == 1) {
            return $this->errorListExportData($data);
        }

        return ToolsService::returnData(200, ['list' => $data, 'count' => $count]);
    }

    /**
     * 导出
     * @param $data
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    protected function errorListExportData($data)
    {
        $exportData = [];
        $index = 1;
        foreach ($data as $key => $val) {
            $exportData[$key] = [$index, $val['AAA28'] ?? '', $val['AAA01'] ?? '', $val['AAC11N'] ?? '', $val['AAB01'] ?? '', $val['AAC01'] ?? '', '', $val['error_name'] ?? '', $val['desc'] ?? ''];
            $index++;
        }
        ## 病案首页缺陷分析
        $fileName = '缺陷分析.xlsx';
        ## 表头
        $title = ['序号', '病案号', '患者姓名', '出院科室', '入院时间', '出院时间', '数据项', '缺陷字段', '缺陷描述'];
        ## 公共导出
        return Excel::download(new ExportData($title, $exportData), $fileName);
    }

    /**
     * errorData
     * 缺陷问题
     * @group quality
     * @bodyParam AAC11C string 科室编码 all:全部
     * @bodyParam level string 错误等级 all:全部0:强制1:建议
     * @bodyParam start_time string 开始时间 时间戳
     * @bodyParam end_time string  结束时间
     * @bodyParam type int 按年(1)、季度(2)、月(3)
     *
     * @response {
     *  "code": 200,
     *  "msg": "",
     *  "data":{
     *      "list":[{
     *         "count":"缺陷数量",
     *         "field":"缺陷字段",
     *         "desc":"缺陷描述",
     *         "level":"缺陷分级",
     *         "error_rule":"缺陷id,点击查询缺陷列表用的id"
     *      }],
     *      "count":{
     *          "base":"患者基本信息",
     *          "diagnosis":"诊疗信息",
     *          "cost":"费用信息"
     *      }
     *  },
     *  "time": 123787842
     * }
     */
    public function errorData(Request $request)
    {
        $type = $request->post('type', 1);
        $start_time = $request->post("start_time");
        $end_time = $request->post("end_time");
        $level = $request->post("level");
        $page = $request->post("page", 1);
        //来源
        $source = $request->post('source', 0);
        // 是否导出 0-返数据 1-导出
        $isExport = $request->post("is_export", 0);
        if ($isExport == 1) {
            $exportService = new ExportService();
            return $exportService->errorDataExport($start_time, $end_time, $source, $type, $level);
        }
        $AAC11C = $request->post("AAC11C");
        $AAC11C = $AAC11C ? $AAC11C : 'all';
        $level = $level ? $level : 'all';
        $query = ErrorData::query();
        if ($level != 'all') {
            $query->where('er.level', $level);
        }
        $SYear = date('Y', strtotime($start_time));
        $SMonth = date('m', strtotime($start_time));
        $EYear = date('Y', strtotime($end_time));
        $EMonth = date('m', strtotime($end_time));
        if ($AAC11C != 'all') {
            $error = Error::query()
                ->where('AAC11C', $AAC11C)
                ->whereRaw("(year >= $SYear and month >= $SMonth)")
                ->whereRaw("(year <= $EYear and month <= $EMonth)")
                ->groupBy('error_rule')
                ->pluck('error_rule');
            if ($error) {
                $error = $error->toArray();
            } else {
                $error = [];
            }
        } else {
            $error = [];
        }
        if (!empty($error)) {
            $query->whereIn('error_rule', $error);
        }
        $offset = ($page - 1) * 10;
        $query->where('error_data.source', $source);
        $list = $query
            ->join('error_rule as er', 'error_data.error_rule', '=', 'er.id')
            ->groupBy('error_rule')
            ->orderBy('count', 'desc')
            ->whereRaw("(error_data.year >= $SYear and error_data.month >= $SMonth)")
            ->whereRaw("(error_data.year <= $EYear and error_data.month <= $EMonth)")
            ->offset($offset)
            ->limit(10)
            ->get(['error_data.error_rule', DB::raw('sum(`count`) as count'), 'field', 'er.desc', 'er.level', 'er.auth']);
        if ($list) {
            $list = $list->toArray();
        } else {
            $list = [];
        }
        $next = 1;
        if (count($list) < 10) {
            $next = 0;
        }
        $ErrorList = Error::query()
            ->whereRaw("(year >= $SYear and month >= $SMonth) or (year <= $EYear and month <= $EMonth)")
            ->groupBy('type')
            ->get([DB::raw('count(type) as count'), 'type'])
            ->toArray();
        if (empty($ErrorList)) {
            $countData = ["base" => "0.00", "diagnosis" => "0.00", "cost" => "0.00", 'other' => "0.00"];
        } else {
            $data = array_column($ErrorList, 'count', 'type');
            $count = array_sum($data);
            $base = sprintf("%.2f", (($data[0] ?? 0) / $count) * 100);
            $diagnosis = sprintf("%.2f", (($data[1] ?? 0) / $count) * 100);
            $cost = sprintf("%.2f", (($data[2] ?? 0) / $count) * 100);
            $other = sprintf("%.2f", (100 - ($base + $diagnosis + $cost)));
            $countData = [
                "base" => $base,
                "diagnosis" => $diagnosis,
                "cost" => $cost,
                'other' => $other
            ];
        }

        return ToolsService::returnData(200, ['list' => $list, 'count' => $countData, 'next' => $next]);
    }


    /**
     * errorDataList
     * 缺陷列表
     * @group quality
     * @bodyParam AAC11C string 科室ID
     * @bodyParam level int   错误等级
     * @bodyParam error_type int   错误类型0：逻辑性1：规范性2：编码
     * @bodyParam hospital_name string  住院医师姓名
     * @bodyParam department_name string  主治医师姓名
     * @bodyParam coder_name string  编码员姓名
     * @bodyParam page string required 页码
     * @bodyParam limit string required 条数
     *
     * @response {
     *  "code": 200,
     *  "msg": "",
     *  "data":{
     *      "list":[{
     *         "AAA28":"病案号",
     *         "AAA01":"患者姓名",
     *         "AAC11N":"出院科室",
     *         "AAC01":"出院时间",
     *         "error_field":"缺陷项",
     *         "level":"缺陷级别",
     *         "desc":"修订建议",
     *         "AAC03":"出院病房",
     *         "AEE04":"住院医师"
     *      }],
     *      "count":0
     *  },
     *  "time": 123787842
     * }
     */
    public function homeErrorDataList(Request $request)
    {
        $isExport = $request->post("is_export", 0);
        $AAC11C = $request->post("AAC11C", 'all');
        $level = $request->post("level", 'all');
        $error_type = $request->post("error_type", 'all');
        $hospital_name = $request->input("hospital_name", '');
        $department_name = $request->input("department_name", '');
        $coder_name = $request->input("coder_name", '');
        $error_rule = $request->input("error_rule", '');
        $page = $isExport == 1 ? 1 : $request->post('page', 1);
        $limit = $isExport == 1 ? 1000000 : $request->post('limit', 20);
        $offset = ($page - 1) * $limit;
        $start_time = $request->input("start_time", '');
        $end_time = $request->input("end_time", '');
        //来源
        $source = $request->post('source', 0);
        $query = Error::query()->where('error.source', $source);
        if ($AAC11C != 'all' && !empty($AAC11C)) {
            $AAC11C = DepartmentService::getDepartmentList($AAC11C);
            $query->where('error.AAC11C', $AAC11C['code'] ?? '-1');
        }
        if ($level != 'all') {
            $query->where('error.level', $level);
        }
        if ($error_type != 'all') {
            $query->where('error.error_type', $error_type);
        }
        if ($hospital_name != '') {
            $query->where('pdi.AEE04', $hospital_name);
        }
        if ($department_name != '') {
            $query->where('pdi.AEE03', $department_name);
        }
        if ($coder_name != '') {
            $query->where('pdi.AEE08', $coder_name);
        }
        if ($error_rule != '') {
            $query->where('error.error_rule', $error_rule);
        }

        // add
        if ($start_time != '') {
            $query->where('error.year', '>=', date('Y', strtotime($start_time)));
            $query->where('error.month', '>=', date('m', strtotime($start_time)));
        }
        if ($end_time != '') {
            $query->where('error.year', '<=', date('Y', strtotime($end_time)));
            $query->where('error.month', '<=', date('m', strtotime($end_time)));
        }


        $query
            ->join('patient_info as pi', 'error.ZYH', '=', 'pi.MED_REC_ID')
            ->join('patient_hospital_info as phi', 'pi.MED_REC_ID', '=', 'phi.AAA28')
            ->join('patient_doctor_info as pdi', 'phi.AAA28', '=', 'pdi.AAA28');
        $count = $query->count(DB::raw('DISTINCT pi.MED_REC_ID'));
        $list = $query
            ->offset($offset)
            ->limit($limit)
            ->groupBy('pi.MED_REC_ID')
            ->get(['pi.AAA28', 'MED_REC_ID', 'AAA01', 'AAC11N', 'AAC01', 'error_field', 'error.level', 'desc', 'AAC03', 'AEE04'])->toArray();

        if ($isExport == 1) {
            return $this->errorDataListExportData($list);
        }

        return ToolsService::returnData(200, ['list' => $list, 'count' => $count]);
    }


    /**
     * errorDataList
     * 缺陷列表
     * @group quality
     * @bodyParam AAC11C string 科室ID
     * @bodyParam level int   错误等级
     * @bodyParam error_type int   错误类型0：逻辑性1：规范性2：编码
     * @bodyParam hospital_name string  住院医师姓名
     * @bodyParam department_name string  主治医师姓名
     * @bodyParam coder_name string  编码员姓名
     * @bodyParam page string required 页码
     * @bodyParam limit string required 条数
     *
     * @response {
     *  "code": 200,
     *  "msg": "",
     *  "data":{
     *      "list":[{
     *         "AAA28":"病案号",
     *         "AAA01":"患者姓名",
     *         "AAC11N":"出院科室",
     *         "AAC01":"出院时间",
     *         "error_field":"缺陷项",
     *         "level":"缺陷级别",
     *         "desc":"修订建议",
     *         "AAC03":"出院病房",
     *         "AEE04":"住院医师"
     *      }],
     *      "count":0
     *  },
     *  "time": 123787842
     * }
     */
    public function errorDataList(Request $request)
    {
        $isExport = $request->post("is_export", 0);
        $brks = $request->post("KS_CODE", '');
        if (empty($brks)) {
            $brks = $request->post("dep_id", '');
        }
        $AAA28 = $request->post("AAA28", '');
        $bah = $request->post("bah", '');
        $ruleId = $request->post("rule_id", '');
        $isDefect = $request->post("is_defect", 0);
        $page = $isExport == 1 ? 1 : $request->post('page', 1);
        $limit = $isExport == 1 ? 1000000 : $request->post('limit', 20);
        $offset = ($page - 1) * $limit;
        $start_time = $request->input("start_time", '');
        $end_time = $request->input("end_time", '');
        if (!empty($start_time)) {
            $start_time = date('Y-m-d', strtotime($start_time));
        }
        if (!empty($end_time)) {
            $end_time = date('Y-m-d H:i:s', strtotime($end_time . ' 23:59:59'));
        }

        $dep_id = UserService::getCurrentUserDep($request);

        if (empty($dep_id)) {
            return ToolsService::returnData(200, ['list' => [], 'count' => 0]);
        }
        $fields = [
            'patient_info.AAA28',    // 病案号
            'patient_info.AAA01',    // 患者姓名
            'patient_info.AAB01',    // 入院时间
            'patient_info.AAC01',    // 出院时间
            'patient_info.score',    // 评分
            'patient_info.score_lv', // 评分等级
            'brry.CH',               // 床号
            'patient_info.MED_REC_ID',              // 住院号
            'brry.BRKS',             // 科室
            'brry.GCYSMC',           // 管床医师名称
            'brry.ZZYSMC',           // 主治医师名称
            'brry.ZLZZMC',           // 质控组长名称
            'brry.ZRYS_MC'           // 主任医师名称
        ];
        $query = PatientInfo::query()
            ->leftJoin('ZY_BRRY as brry', 'patient_info.MED_REC_ID', '=', 'brry.ZYH');

        // 查看缺陷病案，则查询score小于100的病案
        if ($isDefect == 1) {
            $query = $query->where('patient_info.score', '<', 100);
        }

        $rule = [];
        if (!empty($ruleId)) {
            $query = $query->leftJoin('case_quality as cq', 'patient_info.MED_REC_ID', '=', 'cq.JZHM')
                ->join(DB::raw('(SELECT id,title,notice,type,category,status FROM case_rule union all select id+1000000,`object` as title,description as notice,type,case_type as category,status from rule_setting rs) as case_rule'), 'cq.rule_id', '=', 'case_rule.id')
                ->where("case_rule.status", 1)
                ->where("cq.is_correction", 0)
                ->where("cq.is_appeal", 0)
                ->where("cq.is_ignore", 0);
            $query = $query->where('cq.rule_id', '=', $ruleId);

            if ($ruleId > 1000000) {
                $ruleId -= 1000000;
                $rule = RuleSetting::query()->where('id', '=', $ruleId)->get()->toArray();
                $rule[0]['notice'] = $rule[0]['description'];
            } else {
                $rule = CaseRule::query()->where('id', '=', $ruleId)->get()->toArray();
            }
        }
        if (is_array($dep_id)) {
            $query = $query->whereIn('brry.BRKS', $dep_id);
        }

        $inHospital = $request->post("status", 0);
        if ($inHospital == 2) {
            $query = $query->where('patient_info.in_hospital', '=', 1);
        } elseif ($inHospital == 3) {
            $query->where("patient_info.AAC01", '>', date("Y-m-d 00:00:00"));
        } elseif ($inHospital == 4) {
            $query = $query->where('patient_info.in_hospital', '=', 2);
        } else {
            $query = $query->where(function ($query) {
                $query->where('patient_info.in_hospital', 1)->where('patient_info.AAC01', '=', '')->orWhere('patient_info.AAC01', '>', date("Y-m-d 00:00:00"));
            });
        }
        if (!empty($AAA28)) {
            $query = $query->where('patient_info.AAA28', '=', $AAA28);
        }
        if (!empty($bah)) {
            $query = $query->where('patient_info.AAA28', '=', $bah);
        }
        if ($start_time && $end_time) {
            $whereCase = 'patient_info.AAB01 >= "' . $start_time . '" and patient_info.AAB01 <= "' . $end_time . '"';
            $query = $query->whereRaw($whereCase);
        }

        if (!empty($brks)) {
            $query = $query->whereIn('brry.BRKS', is_array($brks) ? $brks : [$brks]);
        }

        $ZRYS = $request->post("ZRYS", '');
        if (!empty($ZRYS)) {
            $query = $query->where('brry.ZRYS', '=', $ZRYS);
        }

        $ZLZZDM = $request->post("ZLZZDM", '');
        if (!empty($ZLZZDM)) {
            $query = $query->where('brry.ZLZZDM', '=', $ZLZZDM);
        }
        $ZZYSDM = $request->post("ZZYSDM", '');
        if (!empty($ZZYSDM)) {
            $query = $query->where('brry.ZZYSDM', '=', $ZZYSDM);
        }
        $GCYSDM = $request->post("GCYSDM", '');
        if (!empty($GCYSDM)) {
            $query = $query->where('brry.GCYSDM', '=', $GCYSDM);
        }
        $count = $query->count();

        $orderKey = $request->post("order_key", '');
        $orderKey = $orderKey ?: "AAB01";
        $orderValue = $request->post("order_value", '');
        $orderValue = $orderValue ?: "desc";
        // 出院
        if ($inHospital == 2) {
            $orderValue = "desc";
            $orderKey = $orderKey ?: "AAC01";
        }
        if ($orderKey) {
            $query->orderBy($orderKey, $orderValue);
        }
        if ($orderKey) {
            // 入院时间排序
            $stringFields = ['AAB01', 'AAC01'];
            if (in_array($orderKey, $stringFields)) {
                $query->orderBy($orderKey, $orderValue);
            } else {
                // 检查是否为字符串类型字段，如果是则使用自然排序
                foreach ($fields as $field) {
                    if (strpos($field, $orderKey) !== false) {
                        $query->orderByRaw("CAST({$field} AS CHAR) {$orderValue}");
                        break;
                    }
                }
            }
        } else {
            $query->orderBy('AAB01', 'desc');
        }

        $list = $query
            ->offset($offset)
            ->limit($limit)
            ->get($fields)
            ->toArray();
        if ($isExport == 1) {
            return $this->errorDataListExportData($list);
        }

        $department = Department::query()->get()->toArray();
        $department = array_column($department, null, 'dep_id');
        foreach ($list as &$l) {
            $l['AAC11N'] = $department[$l['BRKS']]['dep_name'] ?? '';
            $l['rule_notice'] = !empty($rule) ? $rule[0]['notice'] : '';
        }

        return ToolsService::returnData(200, ['list' => $list, 'count' => $count]);
    }

    /**
     * 导出
     * @ param  $data 导出数据
     * @ return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    protected function errorDataListExportData($data)
    {
        $exportData = [];
        $index = 1;
        foreach ($data as $key => $val) {
            $exportData[$key] = [$index, $val['AAA28'] ?? '', $val['AAA01'] ?? '', $val['AAC11N'] ?? '', '', $val['AAC01'] ?? '', $val['AEE04'] ?? '', $val['error_field'] ?? '', '', $val['desc'] ?? ''];
            $index++;
        }
        ## 病案首页缺陷分析
        $fileName = '缺陷分析.xlsx';
        ## 表头
        $title = ['序号', '病案号', '患者姓名', '出院科室', '出院病房', '出院时间', '住院医师', '缺陷项', '取值', '修订建议'];
        ## 公共导出
        return Excel::download(new ExportData($title, $exportData), $fileName);
    }

    /**
     * errorCount
     * 缺陷统计
     * @group quality
     * @bodyParam AAC01 object 出院时间
     *
     * @response {
     *  "code": 200,
     *  "msg": "",
     *  "data":{
     *      "total":20,
     *      "total_error":0,
     *      "avg":95
     *  },
     *  "time": 123787842
     * }
     */
    public function errorCount(Request $request)
    {
        //来源
        $source = $request->post('source', 0);
        //出院时间
        $AAC01 = $request->post('AAC01', null);
        if (!empty($AAC01)) {
            $where = json_decode($AAC01, true);
        } else {
            $where = [date('Y-m', strtotime('-1 month')), date('Y-m')];
        }

        $where['source'] = $source;
        $data = Error::query()->whereBetween('created_at', $where)
            ->groupBy('AAA28')
            ->get(['AAA28', DB::raw('count(`id`) count'), DB::raw('sum(`down`) sum')])->toArray();
        if (empty($data)) {
            $return = ['total' => 0, 'total_error' => 0, 'avg' => 0];
        } else {
            $total = count($data);
            $sum = array_sum(array_column($data[], 'sum'));
            $total_sum = array_sum(array_column($data, 'count'));
            $avg = sprintf('%.2f', (($total * 100) - $sum) / $total);
            $return = ['total' => $total, 'total_error' => $total_sum, 'avg' => $avg];
        }

        return ToolsService::returnData(200, $return);
    }

    public function feeDetail(Request $request)
    {
        $med_red_id = $request->get('MED_REC_ID');
        $list = FeeDetailed::query()
            ->where('AAA28', $med_red_id)
            ->orderBy('FYXH')
            ->get();
        if ($list) {
            $list = $list->toArray();
        } else {
            $list = [];
        }
        return ToolsService::returnData(200, $list);
    }

    public function wtExport(Request $request)
    {
        $start = $request->post('start');
        $end = $request->post('end');
        if (!empty($start)) {
            $start = date('Y-m-d 00:00:00', strtotime($start));
        } else {
            $start = date('Y-m-d 00:00:00');
        }
        if (!empty($end)) {
            $end = date('Y-m-d 23:59:59', strtotime($end));
        } else {
            $end = date('Y-m-d 23:59:59');
        }
        $data = QualityService::getUserBaseInfo($start, $end, 0);
        if (empty($data)) {
            ToolsService::returnData(400, [], '未查询到数据');
        }
        $list = array_column($data, 'MED_REC_ID');
        $otherDiagnosis = QualityService::getUserOtherDiagnosis($list);
        $icu = QualityService::getUserIcu($list);
        $mainOption = QualityService::getMainOption($list);
        $otherOption = QualityService::getOtherOption($list);
        return ExportWTAndGKService::WTExport($data, $otherDiagnosis, $icu, $mainOption, $otherOption);
    }

    public function gkExport(Request $request)
    {
        $start = $request->post('start');
        $end = $request->post('end');
        if (!empty($start)) {
            $start = date('Y-m-d 00:00:00', strtotime($start));
        } else {
            $start = date('Y-m-d 00:00:00');
        }
        if (!empty($end)) {
            $end = date('Y-m-d 23:59:59', strtotime($end));
        } else {
            $end = date('Y-m-d 23:59:59');
        }
        $data = QualityService::getUserBaseInfo($start, $end, 1);
        if (empty($data)) {
            ToolsService::returnData(400, [], '未查询到数据');
        }
        $list = array_column($data, 'MED_REC_ID');
        $otherDiagnosis = QualityService::getUserOtherDiagnosis($list);
        $icu = QualityService::getUserIcu($list);
        $mainOption = QualityService::getMainOption($list);
        $otherOption = QualityService::getOtherOption($list);
        return ExportWTAndGKService::GKExport($data, $otherDiagnosis, $icu, $mainOption, $otherOption);
    }

    /**
     * getTree
     * 得到目录树
     * @group quality
     * @bodyParam id 在多个表中用到的就诊流水号
     *
     * @response
     *    {"code":200,
     *    "msg":"",
     *    "data":{
     *    "0":{
     *    "name":"出院记录",
     *    "bllb":1
     *    },
     *    "1":{
     *    "name":"入院记录",
     *    "bllb":292
     *    },
     *    "2":{
     *    "name":"病程记录",
     *    "bllb":294,
     *    "list": ["2022年04月17日 22:21 首次病程记录", "2022年04月18日 10:43 程一苇副主任医师查房记录", "2022年04月19日 09:07 张赟主治医师查房记录", "2022年04月19日 12:36", "2022年04月20日 09:30 张赟主治医师查房记录", "2022年04月22日 08:34 程一苇副主任医师查房记录", "2022年04月24日 16:16              杨杰勇副主任医师查房记录", "2022年04月25日 11:50", "2022年04月26日 12:46 术后首次病程记录", "2022年04月27日 08:27 程一苇副主任医师查房记录", "2022年04月28日 08:20 高倩主治医师查房记录", "2022年04月29日 13:46", "2022年04月30日 11:01 初萍副主任医师查房记录"]
     *    },
     *    "3":{
     *    "name":"手术",
     *    "bllb":303
     *    },
     *    "6":{
     *    "name":"授权同意类",
     *    "bllb":329
     *    },
     *    "24":{
     *    "name":"医患沟通类",
     *    "bllb":34
     *    },
     *    "25":{
     *    "name":"医疗常用表格",
     *    "bllb":87
     *    },
     *    "26":{
     *    "name":"报告单",
     *    "bllb":2000002,
     *    "list":[{
     *                "name":"计算机X线断层摄影",
     *                "ExamType":"01",
     *                "AAA28":"病案号",
     *                "AAB01":"开始时间",
     *                "AAC01":"结束时间"
     *            },
     *            {
     *                "name":"栐磁共振成像",
     *                "ExamType":"02",
     *                "AAA28":"病案号",
     *                "AAB01":"开始时间",
     *                "AAC01":"结束时间"
     *            },
     *            {
     *                "name":"数字减影血管造影",
     *                "ExamType":"03",
     *                "AAA28":"病案号",
     *                "AAB01":"开始时间",
     *                "AAC01":"结束时间"
     *            },
     *            {
     *                "name":"普通X光摄影",
     *                "ExamType":"04",
     *                "AAA28":"病案号",
     *                "AAB01":"开始时间",
     *                "AAC01":"结束时间"
     *            },
     *            {
     *                "name":"特殊X光摄影",
     *                "ExamType":"05",
     *                "AAA28":"病案号",
     *                "AAB01":"开始时间",
     *                "AAC01":"结束时间"
     *            },
     *            {
     *                "name":"超声检查",
     *                "ExamType":"06",
     *                "AAA28":"病案号",
     *                "AAB01":"开始时间",
     *                "AAC01":"结束时间"
     *            },
     *            {
     *                "name":"病理检查",
     *                "ExamType":"07",
     *                "AAA28":"病案号",
     *                "AAB01":"开始时间",
     *                "AAC01":"结束时间"
     *            },
     *            {
     *                "name":"内窥镜检查",
     *                "ExamType":"08",
     *                "AAA28":"病案号",
     *                "AAB01":"开始时间",
     *                "AAC01":"结束时间"
     *            },
     *            {
     *                "name":"核医学检查",
     *                "ExamType":"09",
     *                "AAA28":"病案号",
     *                "AAB01":"开始时间",
     *                "AAC01":"结束时间"
     *            },
     *            {
     *                "name":"其他检查",
     *                "ExamType":"10",
     *                "AAA28":"病案号",
     *                "AAB01":"开始时间",
     *                "AAC01":"结束时间"
     *            },
     *            {
     *                "name":"介入",
     *                "ExamType":"11",
     *                "AAA28":"病案号",
     *                "AAB01":"开始时间",
     *                "AAC01":"结束时间"
     *            },
     *            {
     *                "name":"检验报告单",
     *                "ExamType":"12",
     *                "AAA28":"病案号",
     *                "AAB01":"开始时间",
     *                "AAC01":"结束时间"
     *            }
     *        ]
     *    },
     *  "27":{
     *        "name":"医嘱",
     *    "bllb":49
     *    }
     *  },
     *  "time":1679929179
     * }
     */
    public function getTree(Request $request)
    {
        $id = $request->post('id');
        if (empty($id)) {
            return ToolsService::returnData(4001, [], '参数错误');
        }

        $esService = new ElasticsearchService('quality2');
        $must = [
            "term" => [
                'MED_REC_ID' => $id
            ]
        ];
        $params = $esService->queryByMust($must)->getParams();
        $res = app('es')->search($params);
        $res = $esService->getDataByEs($res);
        if (empty($res[1])) {
            return ToolsService::returnData(4001, [], '参数错误');
        }
        $info = !empty($res[0]) ? $res[0][0] : [];
        $bllb = array_keys($info['EMR_BL_BL01']);

        // 79儿童营养风险评估
        // 104 外周血管活性药物
        $config = [
            ['name' => '出院记录', 'bllb' => 1],
            ['name' => '入院记录', 'bllb' => 292],
            ['name' => '病程记录', 'bllb' => 294],
            ['name' => '手术', 'bllb' => 303],
            ['name' => '报告单', 'bllb' => 2000002], // no
            ['name' => '病历讨论记录', 'bllb' => 43],
            ['name' => '授权同意类', 'bllb' => 329],
            ['name' => '报告结果类', 'bllb' => 67], // no
            ['name' => '影像报告', 'bllb' => 78], // no
            ['name' => '住院病历类', 'bllb' => 14], // no
            ['name' => '准分子中心门急诊病历', 'bllb' => 2000177], // no
            ['name' => '评估评分表类', 'bllb' => 79],
            ['name' => '护理记录', 'bllb' => 2000049], // no
            ['name' => '护理病历', 'bllb' => 2000048], // no
            ['name' => '护理病程', 'bllb' => 2000146], // no
            ['name' => '门急诊病历', 'bllb' => 2000], // no
            ['name' => '门急诊病程', 'bllb' => 2005], // no
            ['name' => '门诊知情同意书', 'bllb' => 2007], // no
            ['name' => '重危报告类', 'bllb' => 2000185], // no
            ['name' => '死亡记录类', 'bllb' => 288],
            ['name' => '急诊留观病历', 'bllb' => 2004], // no
            ['name' => '化疗记录类', 'bllb' => 83], // no
            ['name' => '检查申请类', 'bllb' => 66], // no
            ['name' => '24小时内记录类', 'bllb' => 18],
            ['name' => '医患沟通类', 'bllb' => 34],
            ['name' => '医疗常用表格', 'bllb' => 87],
        ];


        $esService = new ElasticsearchService('bl01_202303');
        $mustBl[] = [
            "term" => [
                'JZHM' => $id
            ]
        ];
        $mustBl[] = [
            "terms" => [
                'BLLB' => [294, 303]
            ]
        ];
        $params = $esService->queryByMustBatch($mustBl)->paginate(1, 10000)->orderBy('ZXSJ', 'asc')->getParams();
        $res = app('es')->search($params);
        $res = $esService->getDataByEs($res);
        $bl01Data = empty($res[0]) ? [] : $res[0];

        $targetService = new TargetService();
        foreach ($config as $k => $v) {
            if (!in_array($v['bllb'], $bllb)) {
                unset($config[$k]);
            }
            if (in_array($v['bllb'], [294, 303])) {
                // 获取病程记录、手术二级菜单
                $bllbList = $targetService->getSurgeryMenu($bl01Data, $v['bllb']);
                if (!empty($bllbList)) {
                    $config[$k]['list'] = $bllbList;
                }
            } elseif ($v['bllb'] == 2000002) {
                // 获取报告单二级菜单
                $pacsMenuList = $targetService->getPacsMenu($info);
                if (!empty($pacsMenuList)) {
                    $config[$k] = ['name' => '报告单', 'bllb' => 2000002, 'list' => $pacsMenuList];
                }
            }
        }

        if ($info['YZB']) {
            $config[] = ['name' => '医嘱', 'bllb' => 49];
        }
        return ToolsService::returnData(200, $config);
    }

    public function getAllCase(Request $request)
    {
        $bllb = $request->post('bllb', 1);
        $MED_REC_ID = $request->post('MED_REC_ID');

        $data = EMR_BL_BL01::query()
            ->join('EMR_BL_BLXG', 'EMR_BL_BLXG.BLBH', '=', 'EMR_BL_BL01.BLBH')
            ->where('JZHM', $MED_REC_ID)
            ->where('BLLB', $bllb)
            ->groupBy('EMR_BL_BLXG.BLBH')
            ->orderBy('EMR_BL_BLXG.XGSJ')
            ->get(['EMR_BL_BL01.BLBH', 'MBLB', 'CJSJ', 'ZXSJ', 'WCSJ', 'SXYS', 'HJNR'])->toArray();

        $patientInfo = PatientInfo::query()
            ->where('MED_REC_ID', '=', $MED_REC_ID)
            ->get()->toArray();
        $brxm = !empty($patientInfo) ? $patientInfo[0]['AAA01'] : '';

        if ($data) {
            $bllbArray = [329 => '授权同意记录', 18 => '24小时记录记录', 34 => '医患沟通记录', 87 => '医疗常用表格'];
            $mblbArray = [21 => '24小时入院死亡记录', 20 => '24小时内入院记录', 288 => '死亡记录', 290 => '死亡记录', 291 => '死亡讨论记录'];
            foreach ($data as &$value) {
                if (array_key_exists($value['MBLB'], $mblbArray)) {
                    $value['title'] = $mblbArray[$value['MBLB']];
                } elseif (array_key_exists($bllb, $bllbArray)) {
                    $value['title'] = $bllbArray[$bllb];
                }
                if ($value['WCSJ'] == '0000-00-00 00:00:00') {
                    $value['WCSJ'] = '';
                }
                // 数据脱敏
                if (!empty(request()->post('is_tm')) && $brxm) {
                    $desensitizeData = desensitize($brxm, 1, 0, $re = '*');
                    $value['HJNR'] = str_replace($brxm, $desensitizeData, $value['HJNR']);
                }

                // 查询签名医生
                $SYYS = EMR_BL_BLSY::query()->where('BLBH', '=', $value['BLBH'])->pluck('SYYS')->toArray();
                $doctorList = '';
                if ($SYYS) {
                    $staff = Staff::query()->whereIn('code', $SYYS)->get(['name', 'ygjb_text'])->toArray();
                    if ($staff) {
                        foreach ($staff as $val) {
                            $doctorName[] = $val['name'] . '（' . $val['ygjb_text'] . '）';
                        }
                        $doctorList = implode('，', $doctorName);
                    }
                }
                $value['doctor_name'] = $doctorList;
            }
        }

        return ToolsService::returnData(200, $data);
    }


    /**
     * get_bc
     * 得到病程
     * @group quality
     *
     * @bodyParam MED_REC_ID 住院号
     * @bodyParam BLMC 病程名称
     *
     * @response
     *    {
     *        "code": 200,
     *        "msg": "",
     *        "data": [
     *            {
     *                "date": "病程日期 2022年01月01日 00:31",
     *                "title": "病程名称 首次病程记录",
     *             "type": "结果为1234, 1为普通病程记录 2. 术前 3.4术后查房 这个是type为1",
     *                "BLTD": [
     *                    "病例特点 1.患者晁春丽，女，33岁，因“停经38 1/7周，阴道流液1小时”入院。\r",
     *                    "病例特点 2.患者平素月经规律，周期28天，G2P1，末次月经:2021年04月09日，预产期2022年01月14日。孕期平顺，11 5/7周妊娠于我院首次产检建档，于我院共产检12次，妊娠风险评级黄色，孕期行唐筛低风险，OGTT检查正常。1小时前阴道流液，无下腹痛及见红，来我院就诊，查胎儿彩超示“脐动脉血流频谱测值增高，RI：0.7，S/D：3.1”。\r",
     *                    "病例特点     20年前发现“乙肝病毒携带”。\r",
     *                    "病例特点 3.查体：T36.3℃,P90次/分,R20次/分,BP120/77mmHg，心肺听诊无异常，双下肢无水肿。产科检查：宫高28cm，腹围98cm，ROA  位，胎心140次/分，胎儿体重估计3000g左右，无宫缩，内诊：阴道通畅，宫颈未消，宫口容指，胎头棘上3cm，胎膜破，羊水清。骨盆外测量：髂嵴间径26cm，髂棘间径23cm，出口横径9cm，骶耻外径19cm。\r",
     *                    "病例特点 4.辅助检查：2021.12.18乙肝五项：HBsAg(+)HBsAb(-)HBeAg(-)HBeAb(-)HBcAb(+)；2021.12.31胎儿彩超：单胎ROA位，BPD9.3cm,HC32.4cm，AC33.1cm，FL7.3cm，AFI11.2cm,胎盘右侧壁，Ⅲ级，脐带绕颈1周，脐动脉血流频谱测值增高，RI：0.7，S/D：3.1。\r"
     *                ],
     *                "CBZD": [
     *                    "初步诊断 1.胎膜早破 2.38 1/7周妊娠G2P1 ROA 3.脐血流比值高 4.脐带绕颈 5.乙肝病毒携带\r"
     *                ],
     *                "ZDYJ": [
     *                    "诊断依据1.停经38 1/7周，阴道流液1小时。\r",
     *                    "诊断依据2.产科检查：宫高28cm，腹围98cm，ROA  位，胎心140次/分，胎儿体重估计3000g左右，无宫缩，内诊：阴道通畅，宫颈未消，宫口容指，胎头棘上3cm，胎膜破，羊水清。骨盆外测量：髂嵴间径26cm，髂棘间径23cm，出口横径9cm，骶耻外径19cm。\r",
     *                    "诊断依据3.2021.12.18乙肝五项：HBsAg(+)HBsAb(-)HBeAg(-)HBeAb(-)HBcAb(+)；2021.12.31胎儿彩超：单胎ROA位，BPD9.3cm,HC32.4cm，AC33.1cm，FL7.3cm，AFI11.2cm,胎盘右侧壁，Ⅲ级，脐带绕颈1周，脐动脉血流频谱测值增高，RI：0.7，S/D：3.1。\r"
     *                ],
     *                "JBZD": [
     *                    "鉴别诊断 1.压力性尿失禁：腹压突然增加（如咳嗽、大笑）时，尿液不自主的流出，检查时可见尿液自尿道口流出。阴道内无液体。\r",
     *                    "鉴别诊断 2.足月妊娠临产：出现规律并逐渐加强的宫缩，间隔5-6分钟，持续30秒，同时伴有宫颈管缩短、宫口开大及胎先露下降。\r"
     *                ],
     *                "ZLJH": [
     *                    "诊疗计划 王海宁副主任医师查看病人指示",
     *                    "诊疗计划（1）产科护理常规，二级护理，普食，臀高位，留陪人。\r",
     *                    "诊疗计划（2）完善必要的辅助检查，以指导治疗；行胎心监护以了解胎儿宫内情况；告知家属，患者为跌倒高风险，注意防跌倒。\r",
     *                    "诊疗计划（3）向孕妇及家属交待病情，患者胎儿彩超提示脐血流比值高，入院给予左侧卧位、吸氧，复查胎心监护，若胎心监护不理想，不排除胎儿窘迫，有急症剖宫产可能，若胎心监护良好，胎膜早破多于24小时内发动宫缩，目前可继续等待自然分娩，明日复查胎儿脐血流指导治疗，若破膜时间长无宫缩，可静滴催产素引产。在待产及分娩过程中可能发生宫内感染、胎儿窘迫，脐带脱垂，新生儿窒息，新生儿缺血缺氧性脑病，新生儿产伤，肩难产，产妇羊水栓塞，产道裂伤，产后出血，产褥感染等危险，孕妇及家属表示理解，同意目前诊疗方案，要求等待自然分娩并签字。患者乙肝病毒携带，新生儿出生后需肌注乙肝免疫球蛋白被动免疫。\r",
     *                    "诊疗计划（4）注意观察阴道流水、宫缩、胎心胎动变化。    \r"
     *                ],
     *                "SHRQ": "审核日期2022年01月01日 \r"
     *            }
     *        ],
     *        "time": 1680116429
     *    }
     *
     * @response
     * {
     *     "code": 200,
     *     "msg": "",
     *     "data": [
     *        {
     *             "date": "病程日期 2022年04月18日 10:43",
     *             "title": "病程名称 程一苇副主任医师查房记录",
     *             "desc": [
     *                 "病例特点    今日程一苇副主任医师查房，仔细阅读患者病历及各项辅助检查结果，根据患者病史、症状和体征，分析病情如下：患者因“经量增多3月，阴道流血4天。”入院。查体：T：36.5℃，P：92次/分，R：20次/分，BP：118/84mmHg，外阴：正常，阴道通畅，见鲜红色血迹，宫颈II度糜烂，宫口松，可见血块，子宫前位，呈球形增大，如3个月妊娠大小，质中，活动度可，压痛。双侧附件区未触及明显异常。辅助检查：2022-04-17妇科超声：子宫前位，宫体增大，形态饱满，宫腔至宫颈管内探及低回声团块，范围约7.68X5.55X3.72cm，形态不规则，边界欠清，周围可见液性暗区，深约1.0cm，CDFI：内见较丰富血流信号，PW：RI：0.7，内膜显示不清，双侧附件区未见明显异常回声。盆腔未探及游离无回声区，超声提示：宫腔至宫颈管内低回声团块。(本院)。2022-04-17 血常规：白细胞9.6*10^9/L,中性粒细胞比率60.8%，红细胞 4.04*10^12/L，血红蛋白131g/L，血小板314*10^9/L。(本院)。 目前诊断：1.宫腔占位2.异常子宫出血。鉴别诊断：1.子宫肌腺病：经量增多、经期延长，子宫常均匀性增大，多有继发性痛经，进行性加重，子宫很少超过妊娠3个月大小，且有经期子宫增大、经后缩小的特征。此患者无痛经，B超提示宫腔占位，可鉴别。2.子宫粘膜下肌瘤：患者多有月经改变等病史，妇科检查可见宫口突出肿物，有蒂与宫腔相连，触之出血，盆腔彩超提示宫腔内有包块，宫腔镜检查可进一步鉴别。3.子宫内膜癌：患者多为绝经后阴道异常流血、排液，妇科检查可有宫旁增厚等表现，彩超可协助诊断，子宫内膜病理检查可明确诊断。患者现仍有阴道流血，无腹痛腹胀，无发热乏力，无恶心呕吐，无胸闷憋气等不适，饮食睡眠可，大小便正常，查体：体温正常，心肺听诊无异常，腹软，无压痛反跳痛，入院血常规、凝血、肝肾功、感染标志物、乙肝五项结果未见明显异常，血型O+， 女性肿瘤系列：糖类抗原125 60.02U/mL，胃泌素释放肽前体 27.81pg/mL。肺CT结果示：1.双肺散在微结节，建议年度复查。2.双肺多发纤维条索。3.扫及肝低密度病变，建议结合临床进一步检查。心电图结果示： 窦性心律、房性早搏、ST段改变。嘱今日继续给予静滴静滴止血三联，注意观察阴道流血情况，排除禁忌症后拟行宫腔镜检查明确诊断，决定进一步治疗方案。",
     *                 "     ",
     *                 ""
     *             ],
     *            "type": 3,
     *             "SHRQ": "审核日期 2022年04月18日 "
     *         }
     *     ],
     *     "time": 1680122310
     * }
     *
     * @response
     *     {
     *    "code": 200,
     *     "msg": "",
     *    "data": [
     *        {
     *            "date": "2022年04月25日 11:50",
     *             "title": "术前小结及术前讨论结论记录",
     *            "type": 2,
     *           "SSZC": [
     *               [
     *                   "手术主持. 术前讨论由__杨杰勇副主任___医师主持",
     *                    "手术主持. 讨论结论及术前小结记录如下："
     *                ]
     *            ],
     *            "JYBQ": [
     *                "简要病情. 患者杨典兰，女，55岁，因“经量增多3月，阴道流血4天。”于2022-04-17 20:33入院。"
     *            ],
     *            "SQZD": [
     *                 "术前诊断. 1.宫腔占位，2.子宫平滑肌瘤，3.异常子宫出血，4.肝血管瘤？5.肺结节，6.房早。"
     *            ],
     *            "SSZZ": [
     *                "手术指征. 患者诊断明确，宫腔占位引起异常子宫出血，不能排除恶性可能，相关检查显示无明显手术禁忌，可行手术治疗。"
     *            ],
     *             "NSSS": [
     *                 "拟施手术名称和方式. 经腹全子宫+双侧附件切除术，术中行快速病理检查，如提示恶性可能行盆腹腔淋巴结清扫术。"
     *            ],
     *             "NSMZ": [
     *                "拟施麻醉方式.全身麻醉。"
     *             ],
     *             "desc": [
     *                 "其他描述, 1.辅助检查结果：血常规、凝血、肝肾功、感染标志物、乙肝五项结果未见明显异常，血型O+， 女性肿瘤系列：糖类抗原125 60.02U/mL，胃泌素释放肽前体 27.81pg/mL。肺CT结果示：1.双肺散在微结节，建议年度复查。2.双肺多发纤维条索。3.扫及肝低密度病变，建议结合临床进一步检查。心电图结果示： 窦性心律、房性早搏、ST段改变。经阴道超声示：子宫前位，体积增大，形态饱满，内膜欠清尚可辨，宫腔至宫颈管内探及低回声团块，呈长颈葫芦型，下段颈部部分约2.6*0.9cm，上段膨大部分约4.4*3.8cm，内回声较疏松，CDFI：内见较丰富血流信号，来自于后壁下段;子宫左后壁探及范围约4.60*4.09*3.07cm的低回声包块，向宫腔内突起，内回声呈漩涡状，CDFI：周边可见血流信号。双附件区未见明显异常回声。盆腔未探及游离无回声区。提示宫腔至宫颈管内低回声团块，考虑黏膜下肌瘤（血供来源于后壁下段），子宫左后壁肌瘤凸向宫腔。建议完善MRI及CT检查。MRI检查结果示：1.子宫腔富血供占位，请结合临床及病理；2.子宫前壁异常信号，考虑子宫肌瘤可能；3.子宫腔少量积液；4.子宫颈小囊肿可能；5.双侧盆壁多发小淋巴结。CT检查结果示：1.双肺散在微结节，建议年度复查。2.双肺多发纤维条索。3.扫及肝低密度病变，建议结合临床进一步检查。宫腔镜下诊断性刮宫病理结果回报：（宫腔组织）慢性子宫内膜炎，增殖期状态子宫内膜，另见少量平滑肌组织。 ",
     *                "其他描述, 2.手术区局部准备情况：已完善。",
     *                 "其他描述, 3.术中输血准备情况：暂不需术中输血。 ",
     *                 "其他描述, 4.术中快速病理准备情况：需要术中快速病理检查。已签署手术中冰冻切片检查知情同意书 ",
     *                "其他描述, 5.皮肤过敏试验：无过敏史。",
     *                "其他描述, 6.用药及特殊物品准备情况：已完善。",
     *                 "其他描述, 7.已签署手术知情同意书。",
     *                "                                          ",
     *                 "   "
     *             ],
     *            "SZZY": [
     *                 "术中注意事项. 避免周围脏器损伤，注意止血。"
     *             ],
     *            "SHCL": [
     *               "术后处理. 预防感染、血栓形成，补液、对症治疗。"
     *            ],
     *          "OTHER": [
     *               "手术者术前查看患者相关情况. 手术定于2022-04-26  09:30进行。手术者程一苇副主任医师查看患者，患者情况"
     *            ]
     *         }
     *     ],
     *     "time": 1680123518
     * }
     */
    public function getBc(Request $request)
    {
        $MED_REC_ID = $request->post('MED_REC_ID');
        $BLMC = $request->post('BLMC');
        $data = EMR_BL_BL01::query()
            ->join('EMR_BL_BLXG', 'EMR_BL_BLXG.BLBH', '=', 'EMR_BL_BL01.BLBH')
            ->where('JZHM', $MED_REC_ID)
            ->where('BLLB', 294)
            ->where('BLMC', $BLMC)
            ->groupBy('EMR_BL_BLXG.BLBH')
            ->orderBy('EMR_BL_BLXG.XGSJ')
            ->get();


        if ($data) {
            $data = $data->toArray();
        } else {
            $data = [];
        }


        $hjnr = array();

        $con_key = array(
            '病例特点' => 'BLTD',
            '初步诊断' => 'CBZD',
            '诊断依据' => 'ZDYJ',
            '鉴别诊断' => 'JBZD',
            '诊疗计划' => 'ZLJH',
            '上级医师' => 'SJYS',
            '审核日期' => 'SHRQ'
        );

        $con_key2 = array(
            '手术主持' => 'SSZC',
            '简要病情' => 'JYBQ',
            '术前诊断' => 'SQZD',
            '手术指征' => 'SSZZ',
            '拟施手术名称和方式' => 'NSSS',
            '拟施麻醉方式' => 'NSMZ',
            '术前准备' => 'SQZB',
            '术中注意事项' => 'SZZY',
            '术后处理' => 'SHCL',
            '手术者术前查看患者相关情况' => 'OTHER'
        );


        if (isset($data[0]['HJNR'])) {
            $arr = explode("\n", $data[0]['HJNR']);
            if (is_array($arr)) {
                foreach ($arr as $key => $val) {
                    //|============================================
                    //| 标题过滤title date
                    if ($key == 0) {
                        $tmp = explode(" ", $val);
                        if (count($tmp) == 1) {
                            $hjnr['title'] = $val;
                        } else {
                            $tmp = array_filter($tmp);
                            $i = 0;
                            foreach ($tmp as $key1 => $val1) {
                                if ($i > 1) {
                                    $hjnr['title'] = $val1;
                                } else {
                                    if (!isset($hjnr['date'])) {
                                        $hjnr['date'] = $val1;
                                    } else {
                                        $hjnr['date'] .= ' ' . $val1;
                                    }
                                }
                                $i++;
                            }
                        }

                        //| 标题 end
                        //|============================================
                    } else {
                        //|============================================
                        //| 1. 得到前4个字
                        //| 2. 看是否在数组中
                        //| 3. 对应得到key
                        //| 4. 不在将内容合并
                        $tmp = explode("：", $val);
                        $pre = $tmp[0];
                        $flag = 0;
                        $s_key = '';
                        foreach ($con_key as $key_c => $val_c) {
                            if ($pre == $key_c) {
                                $s_key = $val_c;
                                $hjnr['type'] = 1;
                                $flag = 1;
                            }
                        }

                        foreach ($con_key2 as $key_c => $val_c) {
                            if ($pre == $key_c) {
                                $s_key = $val_c;
                                $hjnr['type'] = 2;
                                $flag = 1;
                            }
                        }

                        if ($flag) {
                            $tmp = explode("：", $val);
                            if ($s_key == 'SJYS') {
                                $hjnr['SHRQ'] = $tmp[1];
                            } else {
                                if (isset($tmp[1])) {
                                    if (trim($tmp[1]) != '') {
                                        $hjnr[$s_key][] = $tmp[1];
                                    }
                                }
                            }
                        } else {
                            if ($s_key == 'SJYS') {
                            } else {
                                if ($s_key) {

                                    $hjnr[$s_key][] = $val;
                                } else {
                                    if (strstr($val, '术前小结及')) {
                                        $hjnr['title'] = trim($val);
                                    } else {
                                        if (strstr($val, '主持')) {
                                            $tmp = explode("，", $val);
                                            $hjnr['SSZC'][] = $tmp;
                                        } else {
                                            $hjnr['desc'][] = $val;
                                        }
                                    }

                                    if (!isset($hjnr['type'])) {
                                        $hjnr['type'] = 3;
                                    }
                                }
                            }
                        }
                        //|
                        //|============================================

                    }
                }
            }
        }


        return ToolsService::returnData(200, [$hjnr ?? '']);
    }


    //普通搜索
    public function normalSearch(Request $request)
    {
        $limit = $request->post('limit', 20);
        $page = $request->post('page', 1);
        $keyword = $request->post("keyword", "");
        $detail = $request->post("detail", 1); // 1为详情，0为列表
        $page = $page > 1 ? $page - 1 : 0;
        $bllb_array = [1, 292, 294, 303, 329, 43, 79, 288, 18, 34, 87];
        //普通搜索
        if ($keyword) {
            $params = [
                'index' => 'other_detailed',
                'from' => $page * $limit,
                'size' => $limit,
                '_source' => [
                    "enabled" => false
                ],
            ];
            $nested = [];
            foreach ($bllb_array as $bllb) {
                $nested[] = [
                    'match_phrase' => [
                        'EMR_BL_BL01.' . $bllb => $keyword,
                    ]
                ];
            }
            $inner_hits = [];
            if ($detail == 1) {
                $inner_hits = [
                    'highlight' => [
                        'fields' => [
                            '*' => [
                                "pre_tags" => "<font color='red'>",
                                "post_tags" => "</font>",
                            ],
                        ],
                        "fragment_size" => 10000,
                        "number_of_fragments" => 0,
                    ],
                ];
            }
            $params_list[] = [
                'nested' => [
                    "path" => "EMR_BL_BL01",
                    "query" => [
                        'bool' => [
                            'should' => $nested
                        ]
                    ],
                    $inner_hits == [] ? '' : 'inner_hits' => $inner_hits
                ]
            ];
            $params_list[] = [
                'match_phrase' => [
                    'FYMC' => $keyword,
                ]
            ];
            $params_list[] = [
                'match_phrase' => [
                    'YZMC' => $keyword,
                ]
            ];
            $params['body']['query']['bool']['should'] = $params_list;
            $params['body']['query']['bool']["minimum_should_match"] = 1;

            $params['body']['aggs']['name'] = [
                'terms' => [
                    'field' => 'mark.keyword',
                    'size' => $limit,
                ]
            ];
            $params['body']['track_total_hits'] = true;
            if ($detail == 1) {
                // $params['_source'] = false;
                $params['body']['highlight'] = [
                    'fields' => [
                        'FYMC' => [
                            "pre_tags" => "<font color='red'>",
                            "post_tags" => "</font>",
                        ],
                        'YZMC' => [
                            "pre_tags" => "<font color='red'>",
                            "post_tags" => "</font>",
                        ],
                    ],
                    "fragment_size" => 10000,
                    "number_of_fragments" => 0,
                ];
            }
            $other_ret = app('es')->search($params);
            $med_ids = [];
            foreach ($other_ret['hits']['hits'] as $v) {
                if (!empty($v['highlight'])) {
                    foreach ($v['highlight'] as $key => $highlight) {
                        $v['_source'][$key] = $highlight;
                    }
                }
                if ($v['_source']['mark'] == "EMR_BL_BL01") {
                    foreach ($v['inner_hits']['EMR_BL_BL01']['hits']['hits'][0]['highlight'] as $inner_key => $inner_hits) {
                        $med_ids[$v['_source']['MED_REC_ID']][] = [
                            'mark' => "EMR_BL_BL01",
                            'BLLB' => str_replace("EMR_BL_BL01.", "", $inner_key),
                            'HJNR' => $inner_hits,
                        ];
                    }
                } else {
                    $med_ids[$v['_source']['MED_REC_ID']][] = $v['_source'];
                }
            }
            $params = [
                'index' => 'quality',
                'from' => 0,
                'size' => $limit,
                '_source' => [
                    'MED_REC_ID',
                    'AAA28',
                    'AAA04',
                    'AAC11N',
                    'AAB01',
                    'AAC01',
                ],
            ];
            $params['body']['query']['bool']['must'] = [
                'terms' => [
                    '_id' => array_keys($med_ids)
                ],
            ];

            //$params['body']['query']['bool']["minimum_should_match"] = 1;
        } else {
            $params = [
                'index' => 'quality',
                'from' => $page * $limit,
                'size' => $limit,
                '_source' => [
                    'MED_REC_ID',
                    'AAA28',
                    'AAA04',
                    'AAC11N',
                    'AAB01',
                    'AAC01',
                ],
            ];
            $params['body']['query']['bool'] = [
                "must" => [
                    "match_all" => (object)[],
                ]
            ];
        }
        $params['body']['track_total_hits'] = true;
        $ret = app('es')->search($params);
        if (!empty($other_ret['hits']['total']['value'])) {
            $total = $other_ret['hits']['total']['value'];
        } else {
            $total = $ret['hits']['total']['value'];
        }
        $total_page = ceil($total / $limit);
        // $data = $ret['hits'];
        $data['total_page'] = $total_page;
        $data['total'] = $total;
        if ($total > 0) {
            foreach ($ret['hits']['hits'] as $value) {
                $data['list'][] = $value['_source'];
                $emr_bl_01 = [];
                $fee_detail = [];
                $yz_detail = [];
                if (!empty($med_ids[$value['_id']])) {
                    $other_list = $med_ids[$value['_id']];
                    foreach ($other_list as $other) {
                        if ($other['mark'] == 'yzb') {
                            $yz_detail[] = $other;
                        } elseif ($other['mark'] == 'fee_detailed') {
                            $fee_detail[] = $other;
                        } elseif ($other['mark'] == 'EMR_BL_BL01') {
                            $emr_bl_01[] = $other;
                        }
                    }
                }
                $data['detail'][] = [
                    'EMR_BL_BL01' => $emr_bl_01,
                    'FeeDetailed' => $fee_detail,
                    'YZB' => $yz_detail,
                    'AAA28' => $value['_source']['AAA28'],
                    'MED_REC_ID' => $value['_source']['MED_REC_ID'],
                    'AAC01' => $value['_source']['AAC01'],
                ];
            }
        }
        return ToolsService::returnData(200, ['data' => $data]);
    }

    /**
     *
     * field 参数
     *      select_type  0：and, 1:or, 2:must_not
     *      type 0为like  1为准确查找
     *
     *
     */
    //高级搜索
    public function searchData(Request $request)
    {
        $limit = $request->post('limit', 20);
        $page = $request->post('page', 1);
        $detail = $request->post("detail", 1); // 1为详情，0为列表
        $page = $page > 1 ? $page - 1 : 0;
        $params = [
            'index' => 'other_detailed',
            'from' => $page * $limit,
            'size' => $limit,
            '_source' => [
                "enabled" => false
            ],
        ];
        //高级搜素
        $start_time = $request->post("AAC01_start", "");
        $end_time = $request->post("AAC01_end", "");
        if ($start_time && $end_time) {
            $start_time = date('Y-m-d', strtotime($start_time)) . ' 00:00:00';
            $end_time = date('Y-m-d', strtotime($end_time)) . ' 23:59:59';
            $params['body']['query']['bool']['must'][] = [
                'range' => [
                    'AAC01' => [
                        'gte' => $start_time,
                        'lte' => $end_time,
                    ]
                ]
            ];
        }
        $date_start = $request->post("AAC04_start", 0);
        $date_end = $request->post("AAC04_end", 0);
        if ($date_start >= 0 && $date_end) {
            $params['body']['query']['bool']['must'][] = [
                'range' => [
                    'AAC01' => [
                        'gte' => $date_start,
                        'lte' => $date_end,
                    ]
                ]
            ];
        }
        $age_start = $request->post("AAA04_start", 0);
        $age_end = $request->post("AAA04_end", 0);
        if ($age_start >= 0 && $age_end) {
            $params['body']['query']['bool']['must'][] = [
                'range' => [
                    'AAC01' => [
                        'gte' => $age_start,
                        'lte' => $age_end,
                    ]
                ]
            ];
        }
        $field = $request->post('field', '');
        //根据自定义字段生成elastic的搜索拼写
        $field_params = [];
        $bllb_array = [1, 292, 294, 303, 329, 43, 79, 288, 18, 34, 87];
        if (!empty($field)) {
            foreach ($field as $item) {
                if (empty($item['value'])) {
                    continue;
                }
                if ($item['select_type'] == "1") {
                    $select_type = "should";
                } else if ($item['select_type'] == 2) {
                    $select_type = "must_not";
                } else {
                    $select_type = "must";
                }

                if (in_array($item['key'], $bllb_array)) {
                    if ($item['type'] != 0) { //like
                        $key = '.keyword';
                    } else {
                        $key = '';
                    }
                    //数组为单个记录的搜索类型和搜索信息
                    $nested_list[] = [
                        'type' => $select_type,
                        'query' => [
                            'match_phrase' => [
                                'EMR_BL_BL01.' . $item['key'] . $key => $item['value'],
                            ],
                        ]
                    ];
                } elseif ($item['key'] == "全部") {
                    if ($item['type'] != 0) { //like
                        $key = '.HJNR.keyword';
                    } else {
                        $key = '.HJNR';
                    }
                    // $select_type = "should";
                    foreach ($bllb_array as $value) {
                        //数组为单个记录的搜索类型和搜索信息
                        $nested_params[$select_type]['EMR_BL_BL01_' . $value][$select_type][] = [
                            'match_phrase' => [
                                'EMR_BL_BL01_' . $value . $key => $item['value'],
                            ],
                        ];
                    }
                } elseif ($item['key'] == "49") { //医嘱本
                    if ($item['type'] != 0) { //like
                        $key = 'YZMC.keyword';
                    } else {
                        $key = 'YZMC';
                    }
                    $field_params[] = [
                        'type' => $select_type,
                        'query' => [
                            'match_phrase' => [
                                $key => $item['value'],
                            ]
                        ]
                    ];
                } elseif ($item['key'] == "AAC11N") { //科室
                    if ($item['value'] != "全部") {
                        $params['body']['query']['bool']['must'][] = [
                            'match_phrase' => [
                                'AAC11N' => $item['value']
                            ]
                        ];
                    }
                } else { //消费明细
                    if ($item['type'] != 0) { //like
                        $key = 'FYMC.keyword';
                    } else {
                        $key = 'FYMC';
                    }
                    $field_params[] = [
                        'type' => $select_type,
                        'query' => [
                            'match_phrase' => [
                                $key => $item['value'],
                            ]
                        ]

                    ];
                }
            }
            $query = [];
            if (!empty($nested_list)) {
                foreach ($nested_list as $nested) {
                    if (empty($query)) {
                        $query = $nested['query'];
                    } else {
                        if ($nested['type'] == "must_not") {
                            $temp = [
                                'bool' => [
                                    $query == [] ? '' : 'must' => [$query],
                                    $nested['type'] => [
                                        $nested['query'],
                                    ],
                                ]

                            ];
                        } else {
                            $temp = [
                                'bool' => [
                                    $nested['type'] => [
                                        $query,
                                        $nested['query'],
                                    ]
                                ]
                            ];
                        }
                        $query = $temp;
                    }
                }
                $inner_hits = [];
                if ($detail == 1) {
                    $inner_hits = [
                        'highlight' => [
                            'fields' => [
                                '*' => [
                                    "pre_tags" => "<font color='red'>",
                                    "post_tags" => "</font>",
                                ],
                            ],
                            "fragment_size" => 10000,
                            "number_of_fragments" => 0,
                        ],
                    ];
                }
                $temp = [
                    'nested' => [
                        "path" => "EMR_BL_BL01",
                        "query" => [
                            $query
                        ],
                        $inner_hits == [] ? '' : 'inner_hits' => $inner_hits
                    ]
                ];
                $query = $temp;
            }
            if (!empty($field_params)) {
                foreach ($field_params as $field) {
                    if ($field['type'] == "must_not") {
                        $temp = [
                            'bool' => [
                                $query == [] ? '' : 'must' => [$query],
                                $field['type'] => [
                                    $field['query'],
                                ],
                            ]
                        ];
                    } else {
                        $temp = [
                            'bool' => [
                                $field['type'] => [
                                    $query == [] ? '' : $query,
                                    $field['query'],
                                ]
                            ]
                        ];
                    }

                    $query = $temp;
                }
            }

            if (!empty($query)) {
                if (!empty($params['body']['query']['bool']['must'])) {
                    $params['body']['query']['bool']['must'][] = $query;
                } else {
                    $params['body']['query'] = $query;
                }
            }
            // if (!empty($field_params['should'])) {
            //     $params['body']['query']['bool']['should'] = $field_params['should'];
            // }
            // if ( !empty($field_params['must']) ){
            //     $field_params[] = [
            //         'bool' => [
            //             'must' => $field_params['must']
            //         ]
            //     ];
            // }
            // if ( !empty($field_params['must_not']) ){
            //     $params['body']['query']['bool']['must_not'] = $field_params['must_not'];
            // }
            // $params['body']['query']['bool']["minimum_should_match"] = 1;

            $params['body']['aggs']['name'] = [
                'terms' => [
                    'field' => 'mark.keyword',
                    'size' => $limit,
                ]
            ];
            if ($detail == 1) {
                // $params['_source'] = false;
                $params['body']['highlight'] = [
                    'fields' => [
                        'FYMC' => [
                            "pre_tags" => "<font color='red'>",
                            "post_tags" => "</font>",
                        ],
                        'YZMC' => [
                            "pre_tags" => "<font color='red'>",
                            "post_tags" => "</font>",
                        ],
                    ],
                    "fragment_size" => 10000,
                    "number_of_fragments" => 0,
                ];
            }
            $params['body']['track_total_hits'] = true;
            $other_ret = app('es')->search($params);
            $med_ids = [];
            foreach ($other_ret['hits']['hits'] as $v) {
                if (!empty($v['highlight'])) {
                    foreach ($v['highlight'] as $key => $highlight) {
                        $v['_source'][$key] = $highlight;
                    }
                }
                if ($v['_source']['mark'] == "EMR_BL_BL01") {
                    if (!empty($v['inner_hits'])) {
                        foreach ($v['inner_hits']['EMR_BL_BL01']['hits']['hits'][0]['highlight'] as $inner_key => $inner_hits) {
                            $med_ids[$v['_source']['MED_REC_ID']][] = [
                                'mark' => "EMR_BL_BL01",
                                'BLLB' => str_replace("EMR_BL_BL01.", "", $inner_key),
                                'HJNR' => $inner_hits,
                            ];
                        }
                        continue;
                    }
                }
                $med_ids[$v['_source']['MED_REC_ID']][] = $v['_source'];
            }
            $params = [
                'index' => 'quality',
                'from' => 0,
                'size' => $limit,
                '_source' => [
                    'MED_REC_ID',
                    'AAA28',
                    'AAA04',
                    'AAC11N',
                    'AAB01',
                    'AAC01',
                ],
            ];
            $params['body']['query']['bool']['must'] = [
                'terms' => [
                    '_id' => array_keys($med_ids)
                ],
            ];
        }

        if (empty($params['body']['query'])) {
            $params = [
                'index' => 'quality',
                'from' => $page * $limit,
                'size' => $limit,
                '_source' => [
                    'MED_REC_ID',
                    'AAA28',
                    'AAA04',
                    'AAC11N',
                    'AAB01',
                    'AAC01',
                ],
            ];
            $params['body']['query']['bool'] = [
                "must" => [
                    "match_all" => (object)[],
                ]
            ];
        }
        $params['body']['track_total_hits'] = true;
        $ret = app('es')->search($params);
        if (!empty($other_ret['hits']['total']['value'])) {
            $total = $other_ret['hits']['total']['value'];
        } else {
            $total = $ret['hits']['total']['value'];
        }
        $total_page = ceil($total / $limit);
        $ret['hits']['total_page'] = $total_page;
        // $data = $ret['hits'];
        $data['total_page'] = $total_page;
        $data['total'] = $total;
        if ($total > 0) {
            foreach ($ret['hits']['hits'] as $value) {
                $data['list'][] = $value['_source'];
                $emr_bl_01 = [];
                $fee_detail = [];
                $yz_detail = [];
                if (!empty($med_ids[$value['_id']])) {
                    $other_list = $med_ids[$value['_id']];
                    foreach ($other_list as $other) {
                        if ($other['mark'] == 'yzb') {
                            $yz_detail[] = $other;
                        } elseif ($other['mark'] == 'fee_detailed') {
                            $fee_detail[] = $other;
                        } elseif ($other['mark'] == 'EMR_BL_BL01') {
                            $emr_bl_01[] = $other;
                        }
                    }
                }
                $data['detail'][] = [
                    'EMR_BL_BL01' => $emr_bl_01,
                    'FeeDetailed' => $fee_detail,
                    'YZB' => $yz_detail,
                    'AAA28' => $value['_source']['AAA28'],
                    'MED_REC_ID' => $value['_source']['MED_REC_ID'],
                    'AAC01' => $value['_source']['AAC01'],
                ];
            }
        }
        return ToolsService::returnData(200, ['data' => $data]);
    }

    private function getCombinedRequiredCounts(string $zyh): array
    {
        $activeUnlockedRuleIds = $this->getActiveShizhongUnlockedRuleIds($zyh);
        $homeReq = $this->getHomeRequiredCount($zyh);
        $caseReq = $this->getCaseRequiredCount($zyh, $activeUnlockedRuleIds);
        //获取rulewormap id=20066的keyword
        $keyword20066 = RuleWordMap::query()->where('id', 20066)->value('keyword') ?? '0';
        //如果=1或者='1',则req=$homeReq + $caseReq,否则req=$caseReq
        $req = $keyword20066 == 1 || $keyword20066 == '1' ? $homeReq + $caseReq : $caseReq;
        return [
            'req' => $req,
            'home_req' => $homeReq,
            'case_req' => $caseReq,
            'home_req_count' => $homeReq,
            'case_req_count' => $caseReq,
        ];
    }

    private function getHomeRequiredCount(string $zyh): int
    {
        $ruleCount = ErrorV2::query()
            ->where('ZYH', '=', $zyh)
            ->where('error_v2.status', '=', 0)
            ->join('error_rule', 'error_v2.error_rule', '=', 'error_rule.id')
            ->where('error_rule.level', '=', 0)
            ->count();

        $customRuleCount = ErrorV2::query()
            ->where('ZYH', '=', $zyh)
            ->where('error_v2.status', '=', 0)
            ->join('rule_setting', function ($join) {
                $join->on(
                    DB::raw('error_v2.error_rule'),
                    '=',
                    DB::raw('rule_setting.id + 1000000')
                );
            })
            ->where('rule_setting.error_level', '=', 1)
            ->count();

        return $ruleCount + $customRuleCount;
    }

    private function getCaseRequiredCount(string $zyh, array $activeUnlockedRuleIds = []): int
    {
        $ruleCount = CaseQuality::query()
            ->where('JZHM', '=', $zyh)
            ->when(!empty($activeUnlockedRuleIds), function ($query) use ($activeUnlockedRuleIds) {
                $query->whereNotIn('case_quality.rule_id', $activeUnlockedRuleIds);
            })
            ->join('case_rule', 'case_quality.rule_id', '=', 'case_rule.id')
            ->where('case_rule.status', '=', 1)
            ->where('case_rule.level', '=', 1)
            ->distinct()
            ->count('case_quality.rule_id');

        $customRuleCount = CaseQuality::query()
            ->where('JZHM', '=', $zyh)
            ->when(!empty($activeUnlockedRuleIds), function ($query) use ($activeUnlockedRuleIds) {
                $query->whereNotIn('case_quality.rule_id', $activeUnlockedRuleIds);
            })
            ->join('rule_setting', function ($join) {
                $join->on(
                    DB::raw('case_quality.rule_id'),
                    '=',
                    DB::raw('rule_setting.id + 1000000')
                );
            })
            ->where('rule_setting.status', '=', 1)
            ->where('rule_setting.error_level', '=', 1)
            ->distinct()
            ->count('case_quality.rule_id');

        return $ruleCount + $customRuleCount;
    }

    private function getActiveShizhongUnlockedRuleIds(string $zyh): array
    {
        $ruleIds = DB::table('case_quality_shizhong_unlock_records')
            ->where('zyh', '=', $zyh)
            ->where('expire_time', '>', date('Y-m-d H:i:s'))
            ->pluck('rule_id')
            ->toArray();

        return array_values(array_unique(array_map('intval', $ruleIds)));
    }

    /**
     * @param Request $request
     * @return array
     */
    public function qualityHandle(Request $request)
    {
        $zyh = $request->get('zyh', 0);
        $caseService = new CaseService();
        BigModelList::query()->insert([
            'zyh' => $zyh
        ]);
        //第一次质控
        Log::info('第一次质控开始--------------------------', [
            'zyh' => $zyh
        ]);
        $res = $caseService->qualityContrlV2('', '', [$zyh], 99);
        //第二次质控
        Log::info('第二次质控开始--------------------------', [
            'zyh' => $zyh
        ]);
        //$res = $caseService->qualityContrlV2('', '', [$zyh], 991);
        Log::info('第二次质控结束--------------------------', [
            'zyh' => $zyh,
            'res' => $res
        ]);
        return ToolsService::returnData(200, $res);
    }

    /**
     * 单病例质控V2
     * @param Request $request
     * @return array
     */
    public function qualityHandleV2(Request $request)
    {
        set_time_limit(300);
        Log::info('qualityHandleV2 请求开始--------------------------', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'params' => $request->all(),
            'headers' => $request->headers->all(),
            'ip' => $request->ip()
        ]);
        $zyh = $request->get('zyh', "");
        $blbh = $request->get('blbh', "");
        $cfjd = $request->get('cfjd', "");
        $qmys = "";
        $qmrq = "";
        if (empty($zyh)) {
            $json = file_get_contents('php://input');

            // 检测并移除UTF-8 BOM标记 (EF BB BF)
            if (strpos($json, "\xEF\xBB\xBF") === 0) {
                $json = substr($json, 3);
            }
            Log::info("qualityHandleV2 请求参数：" . $json);

            // 尝试解析JSON
            $content = json_decode(str_replace("\\", '', $json), true);

            // 检查解析是否成功
            if ($content === null && json_last_error() !== JSON_ERROR_NONE) {
                $content = $request->post();
                if (!is_array($content)) {
                    // 解析失败，尝试直接解析（不移除反斜杠）
                    $content = json_decode($request->post(), true);
                }


                // 如果仍然失败，记录错误并返回错误响应
                if ($content === null) {
                    return ToolsService::returnData(4001, [], '请求的参数格式有误: ' . json_last_error_msg());
                }
            }
            $zyh = (string)$content['ZYH'] ?? "";
            $blbh = !empty($content['BLBH']) ? (string)$content['BLBH'] : "";
            $cfjd = !empty($content['CFJD']) ? (string)$content['CFJD'] : "";
            $qmys = !empty($content['QMYS']) ? (string)$content['QMYS'] : "";
            $qmrq = !empty($content['QMRQ']) ? (string)$content['QMRQ'] : "";
        }
        if (!$zyh) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }
        DataSyncLog::addData(['zyh' => $zyh, 'content' => '1、医生站请求病历质控']);

        if ($cfjd == 2) {
            //插入blsy_qulist
            BlsyQulist::query()->insert([
                'zyh' => $zyh,
                'blbh' => $blbh,
                'created_at' => date("Y-m-d H:i:s")
            ]);
        }

        $moduleName = env("APP_NAME", "");
        if ($moduleName == "fangcheng") {
            Log::info("qualityHandleV2 加入消息队列：" . $zyh . " - " . $blbh);
            QueueList::query()->insert(["type" => "analysis", "status" => 0, "data" => $zyh, "blbh" => $blbh, "created_at" => date("Y-m-d H:i:s")]);
            $res1 = [];
            $res1['sftc'] = 1;
            return ToolsService::returnData(200, $res1);
        } /* elseif ($moduleName == "binyi") {
            Log::info("qualityHandleV2 加入消息队列：" . $zyh . " - " . $blbh);
            QueueList::query()->insert(["type" => "analysis", "status" => 0, "data" => $zyh, "blbh" => $blbh, "created_at" => date("Y-m-d H:i:s")]);
            $res1 = $this->getCombinedRequiredCounts($zyh);
            $res1['sftc'] = 1;
            return ToolsService::returnData(200, $res1);
        } */

        if ($moduleName == "sanyuan") {
            QueueList::query()->insert([
                ["type" => "pacs", "status" => 0, "data" => $zyh, "created_at" => date("Y-m-d H:i:s")],
                ["type" => "sssq", "status" => 0, "data" => $zyh, "created_at" => date("Y-m-d H:i:s")],
                //["type" => "ssap", "status" => 0, "data" => $zyh, "created_at" => date("Y-m-d H:i:s")],
                ["type" => "shuxue", "status" => 0, "data" => $zyh, "created_at" => date("Y-m-d H:i:s")],
                ["type" => "wjz", "status" => 0, "data" => $zyh, "created_at" => date("Y-m-d H:i:s")],
                ["type" => "hzxx", "status" => 0, "data" => $zyh, "created_at" => date("Y-m-d H:i:s")],
                ["type" => "hcmx", "status" => 0, "data" => $zyh, "created_at" => date("Y-m-d H:i:s")],
            ]);
        } else {
            // 批量添加duii
            QueueList::query()->insert([
                ["type" => "feedetailed", "status" => 0, "data" => $zyh, "created_at" => date("Y-m-d H:i:s")],
                ["type" => "jianyan", "status" => 0, "data" => $zyh, "created_at" => date("Y-m-d H:i:s")],
                ["type" => "yaomin", "status" => 0, "data" => $zyh, "created_at" => date("Y-m-d H:i:s")],
                ["type" => "pacs", "status" => 0, "data" => $zyh, "created_at" => date("Y-m-d H:i:s")],
            ]);
        }



        $caseService = new CaseService();
        try {
            DataSyncLog::addData(['zyh' => $zyh, 'content' => '2、开始质控']);
            Log::info('2、开始质控' . $zyh);
            $caseService->qualityContrlV2('', '', [$zyh], 1, $blbh, $cfjd, $qmys, $qmrq);
            DataSyncLog::addData(['zyh' => $zyh, 'content' => '2.1、第一次质控结束']);
        } catch (\Exception $e) {
            DataSyncLog::addData(['zyh' => $zyh, 'content' => '质控失败：' . $e->getMessage()]);
            QueueList::query()->insert(["type" => "analysis", "status" => 0, "data" => $zyh, "blbh" => $blbh, "created_at" => date("Y-m-d H:i:s")]);
            Log::error('qualityHandleV2 处理失败--------------------------------', ['error' => $e->getMessage()]);
            // 返回错误信息，避免继续执行后续代码
            return ToolsService::returnData(500, ['error' => '质控处理失败: ' . $e->getMessage()]);
        }
        // 执行两次质控
        DataSyncLog::addData(['zyh' => $zyh, 'content' => '2.2、开始第二次质控']);
        Log::info('2.2、开始第二次质控' . $zyh);
        //$caseService->qualityContrlV2('', '', [$zyh], 4, $blbh);
        DataSyncLog::addData(['zyh' => $zyh, 'content' => '2.3、第二次质控结束']);
        $res1 = $this->getCombinedRequiredCounts($zyh);
        //查询casequality有数据就sftc=1,没有就是0
        $caseQuality = CaseQuality::query()->where('JZHM', '=', $zyh)->count();
        $res1['sftc'] = $caseQuality > 0 ? 1 : 0;
        if ($moduleName == 'sanyuan') {
            \App\Model\EMR_BL_BLSY::query()->where('BLBH3', '=', $blbh)->where('is_cfjd', '=', '1')->delete();
        }

        Log::info('qualityHandleV2 请求结果--------------------------------', [
            'zyh' => $zyh,
            'res1' => $res1,
        ]);
        return ToolsService::returnData(200, $res1);
    }
}
