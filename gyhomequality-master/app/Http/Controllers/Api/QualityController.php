<?php

namespace App\Http\Controllers\Api;

use App\Model\Yzb;
use App\Model\User;
use App\Model\Error;
use App\Model\Staff;
use App\Model\ICD10;
use App\Model\ICD9;
use App\Model\CaseRule;
use App\Model\ErrorData;
use App\Model\Department;
use App\Model\TableFiled;
use App\Model\CaseQuality;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLSY;
use App\Model\FeeDetailed;
use App\Model\PatientInfo;
use App\Model\RuleWordMap;
use App\Exports\ExportData;
use App\Model\SearchCollect;
use App\Services\CsvService;
use Illuminate\Http\Request;
use App\Services\UserService;
use App\Services\ToolsService;
use App\Services\TargetService;
use App\Services\QualityService;
use App\Services\MysqlCaseSearchService;
use App\Services\ErrorRuleService;
use Illuminate\Support\Facades\DB;
use App\Http\Service\ExportService;
use App\Model\SearchCollectDetails;
use App\Services\DepartmentService;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Model\SearchCollectDepartment;
use App\Model\YS_ZY_HZSQ;
use App\Model\ZY_BRRY;
use App\Services\ElasticsearchService;
use App\Services\ExportWTAndGKService;
use Illuminate\Support\Facades\Session;

class QualityController extends Controller
{

    /**
     * ICD10 诊断名称列表（支持模糊匹配）
     * @param Request $request
     * @return array
     */
    public function icd10DiagnosisList(Request $request)
    {
        $keyword = (string)$request->post('keyword', '');
        $limit = (int)$request->post('limit', 0);

        $query = ICD10::query()
            ->select('ZDMC')
            ->whereNotNull('ZDMC')
            ->where('ZDMC', '!=', '');

        if ($keyword !== '') {
            $query->where('ZDMC', 'like', '%' . $keyword . '%');
        }

        $query->distinct()->orderBy('ZDMC');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $list = $query->pluck('ZDMC')->toArray();

        return ToolsService::returnData(200, ['count' => count($list), 'list' => $list]);
    }

    /**
     * ICD10 诊断编码列表（支持模糊匹配）
     * @param Request $request
     * @return array
     */
    public function icd10DiagnosisCodeList(Request $request)
    {
        $keyword = (string)$request->post('keyword', '');
        $limit = (int)$request->post('limit', 0);

        $query = ICD10::query()
            ->select('ZDBM')
            ->whereNotNull('ZDBM')
            ->where('ZDBM', '!=', '');

        if ($keyword !== '') {
            $query->where('ZDBM', 'like', '%' . $keyword . '%');
        }

        $query->distinct()->orderBy('ZDBM');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $list = $query->pluck('ZDBM')->toArray();

        return ToolsService::returnData(200, ['count' => count($list), 'list' => $list]);
    }

    /**
     * ICD09 手术名称列表（支持模糊匹配）
     * @param Request $request
     * @return array
     */
    public function icd09OperationNameList(Request $request)
    {
        $keyword = (string)$request->post('keyword', '');
        $limit = (int)$request->post('limit', 0);

        $query = ICD9::query()
            ->select('SSCZMC')
            ->whereNotNull('SSCZMC')
            ->where('SSCZMC', '!=', '');

        if ($keyword !== '') {
            $query->where('SSCZMC', 'like', '%' . $keyword . '%');
        }

        $query->distinct()->orderBy('SSCZMC');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $list = $query->pluck('SSCZMC')->toArray();

        return ToolsService::returnData(200, ['count' => count($list), 'list' => $list]);
    }

    /**
     * ICD09 手术编码列表（支持模糊匹配）
     * @param Request $request
     * @return array
     */
    public function icd09OperationCodeList(Request $request)
    {
        $keyword = (string)$request->post('keyword', '');
        $limit = (int)$request->post('limit', 0);

        $query = ICD9::query()
            ->select('SSCZBM')
            ->whereNotNull('SSCZBM')
            ->where('SSCZBM', '!=', '');

        if ($keyword !== '') {
            $query->where('SSCZBM', 'like', '%' . $keyword . '%');
        }

        $query->distinct()->orderBy('SSCZBM');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $list = $query->pluck('SSCZBM')->toArray();

        return ToolsService::returnData(200, ['count' => count($list), 'list' => $list]);
    }

    // 获取导出数据
    public function exportData(Request $request)
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
        $patientMedicalInfoAEL01 = [];
        $icu = [];
        $patientAdd = [];
        $LNSSQ = [];
        $LNSSH = [];
        $error = [];
        $emr_bl_bl01Info = [];
        $bllb_info = [];
        $zyts = [];
        $operation = [];
        $page = $isExport == 1 ? 1 : $request->post('page', 1);
        // 导出时使用分批获取，每次最多 5000 条（确保 from + size <= 10000，即使第二页 from=5000, size=5000 也不会超过限制）
        $limit = $isExport == 1 ? 5000 : $request->post('limit', 20);
        $offset = ($page - 1) * $limit < 1 ? 1 : ($page - 1) * $limit;
        //病案号
        $AAA28 = $request->post('AAA28', '');
        if (!empty($AAA28) && !static::isNull($AAA28)) {
            $patientInfo['and'][] = ['select_field' => 'AAA28', 'field_value' => $AAA28, 'select_type' => 'term'];
        }
        //出院科室
        $AAC11C = $request->post('AAC11C', 'all');
        if ($AAC11C != 'all' && !static::isNull($AAC11C)) {
            if (is_numeric($AAC11C)) {
                $AAC11C = Department::query()->where('dep_id', '=', $AAC11C)->value('dep_name');
            }
            $patientInfo['and'][] = ['select_field' => 'AAC11N', 'field_value' => $AAC11C, 'select_type' => 'term'];
        }
        //问题属性
        $level = $request->post('level', 'all');
        $source = $request->post('source', 0);
        if ($level != 'all') {
            $error['and'][] = ['select_field' => 'level', 'field_value' => $level, 'select_type' => 'term'];
            $error['and'][] = ['select_field' => 'source', 'field_value' => $source, 'select_type' => 'term'];
        }
        //付款方式
        $AAA26C = $request->post('AAA26C', 'all');
        if ($AAA26C != 'all' && !static::isNull($AAA26C)) {
            $patientInfo['and'][] = ['select_field' => 'AAA26C', 'field_value' => $AAA26C, 'select_type' => 'term'];
        }
        //质控状态
        $status = $request->post('status', 'all');
        if ($status != 'all' && !static::isNull($status)) {
            $patientInfo['and'][] = ['select_field' => 'status', 'field_value' => $status, 'select_type' => 'term'];
        }
        //质控状态
        $ORG_STATE = $request->post('ORG_STATE', 'all');
        if ($ORG_STATE != 'all' && !static::isNull($ORG_STATE)) {
            $patientInfo['and'][] = ['select_field' => 'ORG_STATE', 'field_value' => $ORG_STATE, 'select_type' => 'term'];
        }
        //入院时间
        $startDateaab01 = $request->post('AAB01_start_time', null);
        $endDateaab01 = $request->post('AAB01_end_time', null);
        if (!empty($startDateaab01)) {
            $startDateaab01 = date('Y-m-d', strtotime($startDateaab01)) . ' 00:00:00';
            $zyts['and'][] = ['select_field' => 'AAB01', 'field_value' => $startDateaab01, 'select_type' => 'gte'];
        }
        if (!empty($endDateaab01)) {
            $endDateaab01 = date('Y-m-d', strtotime($endDateaab01)) . ' 23:59:59';
            $zyts['and'][] = ['select_field' => 'AAB01', 'field_value' => $endDateaab01, 'select_type' => 'lte'];
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
            $zyts['and'][] = ['select_field' => 'AAC01', 'field_value' => $startDate, 'select_type' => 'gte'];
        }
        if (!empty($endDate)) {
            $endDate = date('Y-m-d', strtotime($endDate)) . ' 23:59:59';
            $zyts['and'][] = ['select_field' => 'AAC01', 'field_value' => $endDate, 'select_type' => 'lte'];
        }
        //编码员ID
        $coder_id = $request->post('coder_id', 'all');
        if ($coder_id != 'all' && !static::isNull($coder_id)) {
            $patientDoctorInfo['and'][] = ['select_field' => 'AEE08', 'field_value' => $coder_id, 'select_type' => 'term'];
        }
        //字段条件
        $field = $request->post("field", '');
        if ($isExport == 1) {
            //            $field = json_decode($field, true);
        }
        if ($field != '' && !self::isNull($field)) {
            //            $field = array_column($field, null, 'key');
            foreach ($field as $item) {
                if ($item['select_type'] == 1) {
                    $item['select_type'] = "or";
                } elseif ($item['select_type'] == 2) {
                    $item['select_type'] = "no";
                } else {
                    $item['select_type'] = "and";
                }
                if ($item['type'] == 0) {
                    $operator = 'match_phrase_prefix';
                } else {
                    $operator = 'term';
                }
                switch ($item['key']) {
                    case 'ABC01N';
                        $mainDiagnosis[$item['select_type']][] = ['select_field' => 'ICD10_NAME', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'ABC01C';
                        $mainDiagnosis[$item['select_type']][] = ['select_field' => 'ICD10_ID1', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'ICD10_ID1_first';
                        $otherDiagnosis[$item['select_type']][] = ['select_field' => 'ICD10_ID1.keyword', 'field_value' => $item['value'], 'select_type' => $operator];
                        $otherDiagnosis[$item['select_type']][] = ['select_field' => 'DIA_ORDER.keyword', 'field_value' => 1, 'select_type' => $operator];
                        break;
                    case 'ICD10_NAME_first';
                        $otherDiagnosis[$item['select_type']][] = ['select_field' => 'ICD10_NAME.keyword', 'field_value' => $item['value'], 'select_type' => $operator];
                        $otherDiagnosis[$item['select_type']][] = ['select_field' => 'DIA_ORDER.keyword', 'field_value' => 1, 'select_type' => $operator];
                        break;
                    case 'ICD10_ID1';
                        $otherDiagnosis[$item['select_type']][] = ['select_field' => 'ICD10_ID1', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'ICD10_NAME';
                        $otherDiagnosis[$item['select_type']][] = ['select_field' => 'ICD10_NAME', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'ICD9_ID1';
                        $operation[$item['select_type']][] = ['select_field' => 'ICD9_ID1', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'ICD9_NAME';
                        $operation[$item['select_type']][] = ['select_field' => 'ICD9_NAME', 'field_value' => $item['value'], 'select_type' => 'match_phrase_prefix'];
                        break;
                    case 'ICD8_ID1';
                        $secondaryOperation[$item['select_type']][] = ['select_field' => 'ICD9_ID1', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'ICD8_NAME';
                        $secondaryOperation[$item['select_type']][] = ['select_field' => 'ICD9_NAME', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'ABC03C'; //主要诊断入院病情
                        if ($item['value'] > 0) {
                            $patientMedicalInfo[$item['select_type']][] = ['select_field' => 'ABC03C', 'field_value' => $item['value'], 'select_type' => $operator];
                        }
                        break;
                    case 'RYQK'; //其他诊断入院病情
                        if ($item['value'] > 0) {
                            $otherDiagnosis[$item['select_type']][] = ['select_field' => 'RYQK', 'field_value' => $item['value'], 'select_type' => 'term'];
                        }
                        break;
                    case 'OPE_LEVEL'; //手术级别
                        if ($item['value'] != 0) {
                            $operation[$item['select_type']][] = ['select_field' => 'OPE_LEVEL', 'field_value' => $item['value'], 'select_type' => 'term'];
                        }
                        break;
                    case 'IS_MAIN_WAY'; //重症监护室名称
                        $icu[$item['select_type']][] = ['select_field' => 'IS_MAIN_WAY', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'AEM01C'; //离院方式
                        if ($item['value'] != 0) {
                            $patientInfo[$item['select_type']][] = ['select_field' => 'AEM01C', 'field_value' => $item['value'], 'select_type' => $operator];
                        }
                        break;
                    case 'ABA01N'; //门急诊诊断
                        $patientMedicalInfo[$item['select_type']][] = ['select_field' => 'ABA01N', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'ABF01N'; //病理诊断名称
                        $patientMedicalInfo[$item['select_type']][] = ['select_field' => 'ABF01N', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'ABF01C'; //病理诊断编码
                        $patientMedicalInfo[$item['select_type']][] = ['select_field' => 'ABF01C', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'ABA01C'; //门急诊疾病编码
                        $patientMedicalInfo[$item['select_type']][] = ['select_field' => 'ABA01C', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'AEL01'; //呼吸机
                        if ($item['value'] == 1) {
                            $patientMedicalInfoAEL01[$item['select_type']][] = ['select_field' => 'patient_medical_info.AEL01', 'field_value' => 0, 'select_type' => 'gt'];
                        } elseif ($item['value'] == 2) {
                            $patientMedicalInfoAEL01[$item['select_type']][] = ['select_field' => 'patient_medical_info.AEL01', 'field_value' => 0, 'select_type' => 'lte'];
                        }
                        break;
                    case 'RJSS'; //日间手术
                        if ($item['value'] != 0) {
                            $sSType = ['', '是', '否'];
                            $operation[$item['select_type']][] = ['select_field' => 'RJSS.keyword', 'field_value' => $item['value'], 'select_type' => $operator];
                        }
                        break;
                    case 'LNSSQ'; //颅脑损伤前昏迷
                        if ($item['value'] == 1) {
                            $LNSSQ[$item['select_type']][] = ['select_field' => 'patient_medical_info.AEJ01', 'field_value' => 0, 'select_type' => 'gt'];
                            $LNSSQ[$item['select_type']][] = ['select_field' => 'patient_medical_info.AEJ02', 'field_value' => 0, 'select_type' => 'gt'];
                            $LNSSQ[$item['select_type']][] = ['select_field' => 'patient_medical_info.AEJ03', 'field_value' => 0, 'select_type' => 'gt'];
                        } elseif ($item['value'] == 2) {
                            $LNSSQ[$item['select_type']][] = ['select_field' => 'patient_medical_info.AEJ01', 'field_value' => 0, 'select_type' => 'lte'];
                            $LNSSQ[$item['select_type']][] = ['select_field' => 'patient_medical_info.AEJ02', 'field_value' => 0, 'select_type' => 'lte'];
                            $LNSSQ[$item['select_type']][] = ['select_field' => 'patient_medical_info.AEJ03', 'field_value' => 0, 'select_type' => 'lte'];
                        }
                        break;
                    case 'LNSSH'; //颅脑损伤后昏迷
                        if ($item['value'] == 1) {
                            //                            $LNSSH = '(pmi.AEJ04 > 0 or pmi.AEJ05 > 0 or pmi.AEJ06 > 0)';
                            $LNSSH[$item['select_type']][] = ['select_field' => 'patient_medical_info.AEJ04', 'field_value' => 0, 'select_type' => 'gt'];
                            $LNSSH[$item['select_type']][] = ['select_field' => 'patient_medical_info.AEJ05', 'field_value' => 0, 'select_type' => 'gt'];
                            $LNSSH[$item['select_type']][] = ['select_field' => 'patient_medical_info.AEJ06', 'field_value' => 0, 'select_type' => 'gt'];
                        } elseif ($item['value'] == 2) {
                            $LNSSH[$item['select_type']][] = ['select_field' => 'patient_medical_info.AEJ04', 'field_value' => 0, 'select_type' => 'lte'];
                            $LNSSH[$item['select_type']][] = ['select_field' => 'patient_medical_info.AEJ05', 'field_value' => 0, 'select_type' => 'lte'];
                            $LNSSH[$item['select_type']][] = ['select_field' => 'patient_medical_info.AEJ06', 'field_value' => 0, 'select_type' => 'lte'];
                        }
                        break;
                    case 'AAA28'; //病案号
                        $patientInfo[$item['select_type']][] = ['select_field' => 'AAA28', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'AAA01'; //姓名
                        $patientInfo[$item['select_type']][] = ['select_field' => 'AAA01', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'AAA02C'; //性别
                        $patientInfo[$item['select_type']][] = ['select_field' => 'AAA02C', 'field_value' => $item['value'], 'select_type' => 'term'];
                        break;
                    case 'AAB06C'; //入院途径
                        $patientInfo[$item['select_type']][] = ['select_field' => 'AAB06C', 'field_value' => $item['value'], 'select_type' => 'term'];
                        break;
                    //                    case 'AAA04';//年龄
                    //                        $patientInfo[$item['select_type']][] = ['AAA04'=>$item['value']];
                    //                        break;
                    case 'AAA29'; //住院次数
                        $patientInfo[$item['select_type']][] = ['select_field' => 'AAA29', 'field_value' => $item['value'], 'select_type' => 'term'];
                        break;
                    case 'SSPB': // 手术判别
                        if ($item['value'] < 5) {
                            $operation[$item['select_type']][] = ['select_field' => 'SSPB', 'field_value' => $item['value'], 'select_type' => 'term'];
                        }
                        break;
                    case 'AAC11N': // 出院科室
                        if ($item['value'] != 'all') {
                            if (is_numeric($item['value'])) {
                                $item['value'] = Department::query()->where('dep_id', '=', $item['value'])->value('dep_name');
                            }
                            $patientInfo[$item['select_type']][] = ['select_field' => 'AAC11N', 'field_value' => $item['value'], 'select_type' => 'term'];
                        }
                        break;

                    case 'MO_OPE_TYPE'; //主要手术类型
                        if ($item['value'] != 0) {
                            $mainOperation[$item['select_type']][] = ['select_field' => 'OPE_TYPE', 'field_value' => $item['value'], 'select_type' => 'term'];
                        }
                        break;
                    case 'SO_OPE_TYPE'; //其他手术类型
                        if ($item['value'] != 0) {
                            $secondaryOperation[$item['select_type']][] = ['select_field' => 'OPE_TYPE', 'field_value' => $item['value'], 'select_type' => 'term'];
                        }
                        break;
                    case 'MO_ICD9_ID1'; //主要手术编码
                        $mainOperation[$item['select_type']][] = ['select_field' => 'ICD9_ID1', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'SO_ICD9_ID1'; //其他手术编码
                        $secondaryOperation[$item['select_type']][] = ['select_field' => 'ICD9_ID1', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'MO_ICD9_NAME'; //主要手术名称
                        $mainOperation[$item['select_type']][] = ['select_field' => 'ICD9_NAME', 'field_value' => $item['value'], 'select_type' => 'match_phrase_prefix'];
                        break;
                    case 'SO_ICD9_NAME'; //其他手术名称
                        $secondaryOperation[$item['select_type']][] = ['select_field' => 'ICD9_NAME', 'field_value' => $item['value'], 'select_type' => 'match_phrase_prefix'];
                        break;
                    case 'MO_OPE_LEVEL'; //主要手术级别
                        if ($item['value'] != 0) {
                            $mainOperation[$item['select_type']][] = ['select_field' => 'OPE_LEVEL', 'field_value' => $item['value'], 'select_type' => 'term'];
                        }
                        break;
                    case 'SO_OPE_LEVEL'; //其他手术手术级别
                        if ($item['value'] != 0) {
                            $secondaryOperation[$item['select_type']][] = ['select_field' => 'OPE_LEVEL', 'field_value' => $item['value'], 'select_type' => 'term'];
                        }
                        break;
                    case 'MO_SSPB': // 主要手术判别
                        if ($item['value'] < 5) {
                            $mainOperation[$item['select_type']][] = ['select_field' => 'SSPB', 'field_value' => $item['value'], 'select_type' => 'term'];
                        }
                        break;
                    case 'SO_SSPB': // 其他手术判别
                        if ($item['value'] < 5) {
                            $secondaryOperation[$item['select_type']][] = ['select_field' => 'SSPB', 'field_value' => $item['value'], 'select_type' => 'term'];
                        }
                        break;
                    case 'AEE10': // 责任护士
                        if ($item['value'] != 0) {
                            $name = Staff::query()->where('code', '=', $item['value'])->value('name');
                            $patientDoctorInfo[$item['select_type']][] = ['select_field' => 'AEE10', 'field_value' => $name, 'select_type' => 'match_phrase'];
                        }
                        break;
                    case 'AEE03': // 主治医师
                        if ($item['value'] != 0) {
                            $patientDoctorInfo[$item['select_type']][] = ['select_field' => 'AEE03_CODE', 'field_value' => $item['value'], 'select_type' => 'term'];
                        }
                        break;
                    case 'AEE04': // 住院医师
                        if ($item['value'] != 0) {
                            $patientDoctorInfo[$item['select_type']][] = ['select_field' => 'AEE04_CODE', 'field_value' => $item['value'], 'select_type' => 'term'];
                        }
                        break;
                    case 'AEE01': // 科主任
                        if ($item['value'] != 0) {
                            $patientDoctorInfo[$item['select_type']][] = ['select_field' => 'AEE01_CODE', 'field_value' => $item['value'], 'select_type' => 'term'];
                        }
                        break;
                    case 'AEE02': // 主(副主)任医师
                        if ($item['value'] != 0) {
                            $patientDoctorInfo[$item['select_type']][] = ['select_field' => 'AEE02_CODE', 'field_value' => $item['value'], 'select_type' => 'term'];
                        }
                        break;
                    case 'ZZYISXM': // 医疗组长
                        if ($item['value'] != 0) {
                            $name = Staff::query()->where('code', '=', $item['value'])->value('name');
                            $patientAdd[$item['select_type']][] = ['select_field' => 'ZZYISXM', 'field_value' => $name, 'select_type' => 'match_phrase'];
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
                    $zyts['and'][] = ['select_field' => 'AAA40', 'field_value' => $age_start, 'select_type' => 'gte'];
                    $zyts['and'][] = ['select_field' => 'AAA40', 'field_value' => $age_end, 'select_type' => 'lte'];
                } elseif ($age_start > 0 && $age_end <= 0) {
                    $zyts['and'][] = ['select_field' => 'AAA40', 'field_value' => $age_start, 'select_type' => 'gte'];
                } elseif ($age_start <= 0 && $age_end > 0) {
                    $zyts['and'][] = ['select_field' => 'AAA40', 'field_value' => $age_end, 'select_type' => 'lte'];
                }
            }
            if ($age_start_type == 2) { //年
                if ($age_start > 0 && $age_end > 0) {
                    $zyts['and'][] = ['select_field' => 'AAA04', 'field_value' => $age_start, 'select_type' => 'gte'];
                    $zyts['and'][] = ['select_field' => 'AAA04', 'field_value' => $age_end, 'select_type' => 'lte'];
                } elseif ($age_start > 0 && $age_end <= 0) {
                    $zyts['and'][] = ['select_field' => 'AAA04', 'field_value' => $age_start, 'select_type' => 'gte'];
                } elseif ($age_start <= 0 && $age_end > 0) {
                    $zyts['and'][] = ['select_field' => 'AAA04', 'field_value' => $age_end, 'select_type' => 'lte'];
                }
            }
        } elseif (($age_start_type == 1) && ($age_end_type == 2)) { //多少天-多少年
            if ($age_start > 0 && $age_end > 0) {
                $zyts['and'][] = ['select_field' => 'AAA40', 'field_value' => $age_start, 'select_type' => 'gte'];
                $zyts['and'][] = ['select_field' => 'AAA04', 'field_value' => $age_end, 'select_type' => 'lte'];
            } elseif ($age_start > 0 && $age_end <= 0) {
                $zyts['and'][] = ['select_field' => 'AAA40', 'field_value' => $age_start, 'select_type' => 'gte'];
            } elseif ($age_start <= 0 && $age_end > 0) {
                $zyts['and'][] = ['select_field' => 'AAA04', 'field_value' => $age_end, 'select_type' => 'lte'];
            }
        }
        //住院天数
        $AAC0401 = $request->post('AAC0401', 0);
        $AAC0402 = $request->post('AAC0402', 0);
        if ($AAC0401 > 0 && $AAC0402 > 0) {
            $zyts['and'][] = ['select_field' => 'AAC04', 'field_value' => $AAC0401, 'select_type' => 'gte'];
            $zyts['and'][] = ['select_field' => 'AAC04', 'field_value' => $AAC0402, 'select_type' => 'lte'];
        } elseif ($AAC0401 > 0 && $AAC0402 <= 0) {
            $zyts['and'][] = ['select_field' => 'AAC04', 'field_value' => $AAC0401, 'select_type' => 'gte'];
        } elseif ($AAC0401 <= 0 && $AAC0402 > 0) {
            $zyts['and'][] = ['select_field' => 'AAC04', 'field_value' => $AAC0402, 'select_type' => 'lte'];
        }
        //关键词搜索
        $search_keyword = $request->post("search_keyword", "");
        if (!empty($search_keyword)) {
            $emr_bl_bl01Info['and'][] = ['select_field' => 'BLMC', 'field_value' => $search_keyword, 'select_type' => 'match_phrase_prefix'];
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
                $bllb_info['and'][] = ['select_field' => 'BLLB', 'field_value' => $bllb_config, 'select_type' => 'terms'];
            } else {
                $bllb_info['and'][] = ['select_field' => 'BLLB', 'field_value' => $bllb, 'select_type' => 'terms'];
            }
        }
        //范围搜索
        $rangeName = $request->post('rangeName', '');
        $bmStart = $request->post('bmStart', '');
        $bmEnd = $request->post('bmEnd', '');
        $bm = [];
        if (is_numeric($rangeName)) {
            if (!empty($bmStart) && !empty($bmEnd)) {
                switch ($rangeName) {
                    case 1: //主要诊断编码
                    case 6: //主要诊断编码
                        $bm = $this->generateICD10RangeQuery($bmStart, $bmEnd);
                        break;
                    case 2: //其他诊断编码
                        $bm = $this->generateICD10RangeQuery($bmStart, $bmEnd);
                        break;
                    case 3: //主要手术编码
                    case 5: //主要手术编码
                        $bm = $this->generateICD9RangeQuery($bmStart, $bmEnd);
                        break;
                    case 4: //其他手术编码
                        $bm = $this->generateICD9RangeQuery($bmStart, $bmEnd);
                        break;
                }
            }
        }
        // 导出时 QualityService::getList 会自动使用 Scroll API 获取所有数据
        // 只需要调用一次，传入 isExport=1, offset=0, page=1
        if ($isExport == 1) {
            // 重置为第一页，使用 Scroll API 获取所有数据
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
                0, // offset 必须为 0，Scroll API 从 0 开始
                5000, // limit 用于 Scroll API 的批次大小
                $LNSSQ,
                $LNSSH,
                $error,
                $zyts,
                1, // page 必须为 1
                $patientMedicalInfoAEL01,
                $operation,
                $bm,
                $rangeName
            );
        } else {
            // 非导出模式，正常获取单页数据
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
                $error,
                $zyts,
                $page,
                $patientMedicalInfoAEL01,
                $operation,
                $bm,
                $rangeName
            );
        }

        $fieldIds = $request->post('filedIds', '');
        if (empty($fieldIds)) return;
        $filedTable = TableFiled::query()->whereIn('id', $fieldIds)->get(['id', 'table_name', 'filed_name', 'name', 'filed_where', 'array_id'])->toArray();
        //获取表头
        $exportHeader = [];
        foreach ($filedTable as $k => $v) {
            $exportHeader[0][] = $v['name'];
        }
        //获取数据
        $exportData = [];
        foreach ($data['list'] as $k => $v) {
            foreach ($filedTable as $vv) {
                //序号
                if ($vv['filed_name'] == 'id') {
                    $exportData[$k][] = $k + 1;
                    continue;
                }
                //如果是patient_info表(主表)则直接取数据
                if ($vv['table_name'] == 'patient_info') {
                    $exportData[$k][] = $v[$vv['filed_name']] ?? "";
                } else {
                    $subTable = $v[$vv['table_name']] ?? "";
                    if (!empty($subTable)) {
                        $exportData[$k][] = $subTable[$vv['array_id']][$vv['filed_name']] ?? "";
                    }
                }
            }
        }
        $exportData = array_merge($exportHeader, $exportData);

        ## 导出CSV
        $csv = new CsvService();
        $csv->filename = $csv->charset('病案列表', 'UTF-8');
        //export这里10000条报错
        return $csv->export($exportData, false);
    }

    //获取导出字段
    public function getExportField()
    {
        $fieldTable = TableFiled::query()
            ->where('is_enabled', 1)
            ->orderBy('disabled', 'desc')
            ->orderBy('is_check', 'desc')
            ->orderBy('order_id', 'desc')
            ->orderBy('id')
            ->get(['id', 'name', 'is_disabled as disabled', 'is_check', 'filed_name', 'filed_width', 'table_name', 'array_id'])
            ->toArray();
        $checkList = [];
        foreach ($fieldTable as $k => $v) {
            if ($v['is_check'] === 1) $checkList[] = $v['id'];
            $v['disabled'] = $v['disabled'] == 1 ? true : false;
            $fieldTable[$k] = $v;
        }
        return ToolsService::returnData(200, ['fieldTable' => $fieldTable, 'checkList' => $checkList]);
    }

    //获取搜索收藏详情
    public function getCollect(Request $request)
    {
        $collectId = intval($request->post('collect_id', 0));
        $collectArray = SearchCollectDetails::query()->where('collect_id', $collectId)->get()->toArray();
        foreach ($collectArray as $k => $v) {
            if ($v['name'] == 'seniorList') {

                // $v['search_value'] = json_decode($v['search_value'],true);
            }
            $collectArray[$k] = $v;
        }
        return ToolsService::returnData(200, $collectArray);
    }

    /**
     * 获取所有搜索收藏
     */
    public function getSearchCollect(Request $request)
    {
        $post = $request->post();
        $user = Session::get($request->header('token'));
        $userId = intval($user['id']);
        $query = SearchCollect::query();
        //关键字
        $kwd = $post['searchCollectSearch'];
        if (!empty($kwd)) {
            $query->where("name", "like", "%{$kwd}%");
        }
        //是否为公共收藏
        $is_public = intval($post['is_public']);
        if ($is_public == 1) {
            $query->where('is_public', $is_public);
        } else {
            $query->where('user_id', $userId)
                ->where('is_public', $is_public);
        }
        //所属部门
        if ($is_public == 1) {
            $depIdsArray = json_decode($user['dep_id'], true);
            $collectArray = SearchCollectDepartment::query()
                ->whereIn('dep_id', $depIdsArray)
                ->groupBy('collect_id')
                ->get(['collect_id'])
                ->toArray();
            $collectId = [];
            foreach ($collectArray as $v) {
                $collectId[] = $v['collect_id'];
            }
            $query->whereIn('id', $collectId);
        }
        $collectArray = $query->get(['id', 'name'])->toArray();
        //关键字高亮
        if (!empty($kwd)) {
            foreach ($collectArray as $k => $v) {
                $v['name'] = str_ireplace("{$kwd}", "<span style='color:red'>{$kwd}</span>", $v['name']);
                $collectArray[$k] = $v;
            }
        }
        return ToolsService::returnData(200, $collectArray);
    }

    /**
     *首页搜索收藏删除
     */
    public function deleteSearchCollect(Request $request)
    {
        $id = intval($request->post('id', 0));
        $row = SearchCollect::query()->where('id', $id)->first();
        if (empty($row)) return ToolsService::jsonError("找不到此条记录");
        if (!SearchCollect::query()->where('id', $id)->delete()) return ToolsService::jsonError("删除收藏失败");
        if ($row['is_public'] == 1) {
            if (!SearchCollectDepartment::query()->where('collect_id', $id)->delete()) return
                ToolsService::jsonError("删除收藏科室失败");
        }
        if (!SearchCollectDetails::query()->where('collect_id', $id)->delete()) return
            ToolsService::jsonError("删除收藏详情失败");
        return ToolsService::jsonSuccess("", "删除成功");
    }

    /**
     * 首页搜索收藏保存
     */
    public function searchCollectSave(Request $request)
    {
        //开启事务
        DB::transaction(function () use ($request) {
            $post = $request->post();

            //检验数据
            $data = [];
            //收藏名称
            $data['name'] = $post['name'];
            if (empty($data['name'])) return ToolsService::jsonError("收藏名称不能为空");
            $data['is_public'] = intval($post['is_public']) === 1 ? 1 : 0; //是否为公共收藏
            if ($data['is_public'] === 1) {
                $data['dep_ids'] = implode(',', $post['dep_ids']); //公用科室
                if (empty($data['dep_ids'])) return ToolsService::jsonError("请至少选择一个公用科室");
            }
            $data['created_at'] = date('Y-m-d H:i:s'); //获取当前时间
            $user = Session::get($request->header('token')); //获取当前用户信息
            $data['user_id'] = intval($user['id']); //收藏账号id
            $insertId = SearchCollect::query()->insertGetId($data);

            //更新科室表
            if ($data['is_public'] === 1) {
                $dep_id = $post['dep_ids'];;
                if (count($dep_id) > 0) {
                    foreach ($dep_id as $v) {
                        $department = [];
                        $department['collect_id'] = $insertId;
                        $department['dep_id'] = $v;
                        SearchCollectDepartment::query()->insert($department);
                    }
                }
            }

            //开始处理搜索内容
            $searchArray = $post['searchArray'];
            foreach ($searchArray as $k => $v) {
                if (empty($v)) continue;
                //如果age_end_type与age_start_type两个字段有值，ageday与ageyear两个字段都是空则跳过
                if ($k == 'age_end_type' || $k == "age_start_type") {
                    if (empty($searchArray['ageday']) && empty($searchArray['ageyear'])) continue;
                }
                $details = [];
                $details['name'] = $k;
                if ($k == 'seniorList') {
                    $details['search_value'] = json_encode($v);
                } else {

                    $details['search_value'] = $v;
                }
                $details['collect_id'] = $insertId;
                SearchCollectDetails::query()->insert($details);
            }
        });
        return ToolsService::jsonSuccess("", "收藏成功");
    }


    /**
     * 获取科室
     */
    public function getDeportmentList()
    {
        $depArray = Department::query()->where('type_id', 2)->get(['id', 'dep_id', 'dep_name'])->toArray();
        return ToolsService::returnData(200, $depArray);
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
     * 生成 ICD10 范围查询的正则表达式
     * @param string $start 开始编码 (例如: 'i63.1', 'i63.12', 'i502')
     * @param string $end 结束编码 (例如: 'i64', 'i63.55', 'i509')
     * @return array ES查询条件
     */
    private function generateICD10RangeQuery(string $start, string $end): array
    {
        // 只去除首尾空格，保持原始大小写
        $start = trim($start);
        $end = trim($end);

        // 提取字母前缀（可能是一个或多个字母，支持大小写）
        preg_match('/^([a-zA-Z]+)/', $start, $startPrefixMatch);
        preg_match('/^([a-zA-Z]+)/', $end, $endPrefixMatch);

        if (empty($startPrefixMatch[1]) || empty($endPrefixMatch[1])) {
            return [];
        }

        $prefix = $startPrefixMatch[1];

        // 前缀必须一致（不区分大小写比较，但使用起始编码的前缀大小写）
        if (strcasecmp($prefix, $endPrefixMatch[1]) !== 0) {
            return [];
        }

        // 解析起始编码：提取主数字部分和小数部分
        $startMainPart = preg_replace('/^[a-zA-Z]+/', '', $start);
        $endMainPart = preg_replace('/^[a-zA-Z]+/', '', $end);

        // 检查是否有小数点
        $startHasDecimal = strpos($startMainPart, '.') !== false;
        $endHasDecimal = strpos($endMainPart, '.') !== false;

        // 提取主数字部分（小数点前的数字）
        $startMainNum = $startHasDecimal ? (int)explode('.', $startMainPart)[0] : (int)$startMainPart;
        $endMainNum = $endHasDecimal ? (int)explode('.', $endMainPart)[0] : (int)$endMainPart;

        // 提取小数部分
        $startDecimal = $startHasDecimal ? explode('.', $startMainPart)[1] : '';
        $endDecimal = $endHasDecimal ? explode('.', $endMainPart)[1] : '';

        // 小数位数以起始编码为主
        $decimalLength = strlen($startDecimal);

        // 如果结束编码有小数部分但位数少于起始编码，需要补齐
        if ($endHasDecimal && strlen($endDecimal) < $decimalLength && $decimalLength > 0) {
            $endDecimal = str_pad($endDecimal, $decimalLength, '0', STR_PAD_RIGHT);
        }

        // 生成所有需要匹配的编码前缀
        $prefixes = [];

        // 情况1: 起始编码和结束编码的主数字部分相同（如 i63.12 到 i63.55）
        if ($startMainNum === $endMainNum) {
            if ($decimalLength > 0) {
                // 有小数部分，生成小数范围
                $startDecimalNum = (int)$startDecimal;
                $endDecimalNum = $endHasDecimal ? (int)$endDecimal : (int)str_repeat('9', $decimalLength);

                // 确保结束小数不超过该位数的最大值
                $maxDecimal = (int)str_repeat('9', $decimalLength);
                if ($endDecimalNum > $maxDecimal) {
                    $endDecimalNum = $maxDecimal;
                }

                for ($i = $startDecimalNum; $i <= $endDecimalNum; $i++) {
                    $decimalStr = str_pad((string)$i, $decimalLength, '0', STR_PAD_LEFT);
                    $prefixes[] = $prefix . $startMainNum . '.' . $decimalStr;
                }
            } else {
                // 无小数部分，直接添加
                $prefixes[] = $prefix . $startMainNum;
            }
        }
        // 情况2: 起始编码和结束编码的主数字部分不同
        else {
            // 2.1 处理起始编码所在的主数字（从起始编码的小数部分开始到该主数字的最大值）
            if ($decimalLength > 0) {
                // 有小数部分
                $startDecimalNum = (int)$startDecimal;
                $maxDecimalForStart = (int)str_repeat('9', $decimalLength);

                for ($i = $startDecimalNum; $i <= $maxDecimalForStart; $i++) {
                    $decimalStr = str_pad((string)$i, $decimalLength, '0', STR_PAD_LEFT);
                    $prefixes[] = $prefix . $startMainNum . '.' . $decimalStr;
                }
            } else {
                // 无小数部分
                $prefixes[] = $prefix . $startMainNum;
            }

            // 2.2 处理中间的主数字（完整范围）
            if ($endMainNum - $startMainNum > 1) {
                for ($mainNum = $startMainNum + 1; $mainNum < $endMainNum; $mainNum++) {
                    if ($decimalLength > 0) {
                        // 有小数部分，生成该主数字的所有小数组合
                        $maxDecimal = (int)str_repeat('9', $decimalLength);
                        for ($i = 0; $i <= $maxDecimal; $i++) {
                            $decimalStr = str_pad((string)$i, $decimalLength, '0', STR_PAD_LEFT);
                            $prefixes[] = $prefix . $mainNum . '.' . $decimalStr;
                        }
                    } else {
                        // 无小数部分
                        $prefixes[] = $prefix . $mainNum;
                    }
                }
            }

            // 2.3 处理结束编码所在的主数字
            // 如果结束编码有小数部分，则从0或指定小数到结束编码的小数部分
            // 如果结束编码无小数部分且起始编码也无小数部分，则添加结束主数字
            // 如果结束编码无小数部分但起始编码有小数部分，需要处理结束主数字的所有小数组合
            if ($endHasDecimal && $decimalLength > 0) {
                // 结束编码有小数部分，生成从0到结束小数的小数组合
                $endDecimalNum = (int)$endDecimal;
                $maxDecimalForEnd = (int)str_repeat('9', $decimalLength);
                if ($endDecimalNum > $maxDecimalForEnd) {
                    $endDecimalNum = $maxDecimalForEnd;
                }

                for ($i = 0; $i <= $endDecimalNum; $i++) {
                    $decimalStr = str_pad((string)$i, $decimalLength, '0', STR_PAD_LEFT);
                    $prefixes[] = $prefix . $endMainNum . '.' . $decimalStr;
                }
            } elseif (!$endHasDecimal && $decimalLength == 0) {
                // 结束编码无小数部分，且起始编码也无小数部分，添加结束主数字
                $prefixes[] = $prefix . $endMainNum;
            } elseif (!$endHasDecimal && $decimalLength > 0) {
                // 结束编码无小数部分，但起始编码有小数部分，需要处理结束主数字的所有小数组合
                $maxDecimal = (int)str_repeat('9', $decimalLength);
                for ($i = 0; $i <= $maxDecimal; $i++) {
                    $decimalStr = str_pad((string)$i, $decimalLength, '0', STR_PAD_LEFT);
                    $prefixes[] = $prefix . $endMainNum . '.' . $decimalStr;
                }
            }
        }

        // 去重并排序
        $prefixes = array_unique($prefixes);
        sort($prefixes);

        // 如果编码前缀数量过多（超过50个），使用紧凑的正则表达式模式
        if (count($prefixes) > 50) {
            $regexp = $this->buildCompactRegexp($prefix, $startMainNum, $endMainNum, $startHasDecimal, $endHasDecimal, $startDecimal, $endDecimal, $decimalLength);
        } else {
            // 构建正则表达式：转义特殊字符并组合
            $escapedPrefixes = array_map(function ($p) {
                return preg_quote($p, '/');
            }, $prefixes);

            $regexp = implode('|', $escapedPrefixes);
        }

        return ['regexp' => "({$regexp}).*"];
    }

    /**
     * 构建紧凑的正则表达式模式，避免枚举所有编码
     * @param string $prefix 字母前缀
     * @param int $startMainNum 起始主数字
     * @param int $endMainNum 结束主数字
     * @param bool $startHasDecimal 起始编码是否有小数
     * @param bool $endHasDecimal 结束编码是否有小数
     * @param string $startDecimal 起始小数部分
     * @param string $endDecimal 结束小数部分
     * @param int $decimalLength 小数位数
     * @return string 紧凑的正则表达式
     */
    private function buildCompactRegexp(string $prefix, int $startMainNum, int $endMainNum, bool $startHasDecimal, bool $endHasDecimal, string $startDecimal, string $endDecimal, int $decimalLength): string
    {
        $escapedPrefix = preg_quote($prefix, '/');
        $patterns = [];

        // 如果结束编码有小数部分但位数少于起始编码，需要补齐
        if ($endHasDecimal && strlen($endDecimal) < $decimalLength && $decimalLength > 0) {
            $endDecimal = str_pad($endDecimal, $decimalLength, '0', STR_PAD_RIGHT);
        }

        // 情况1: 起始编码和结束编码的主数字部分相同
        if ($startMainNum === $endMainNum) {
            if ($decimalLength > 0) {
                // 有小数部分，生成小数范围的正则表达式
                $startDecimalNum = (int)$startDecimal;
                $endDecimalNum = $endHasDecimal ? (int)$endDecimal : (int)str_repeat('9', $decimalLength);
                $maxDecimal = (int)str_repeat('9', $decimalLength);
                if ($endDecimalNum > $maxDecimal) {
                    $endDecimalNum = $maxDecimal;
                }

                $decimalPattern = $this->buildDecimalRangePattern($startDecimalNum, $endDecimalNum, $decimalLength);
                $patterns[] = $escapedPrefix . $startMainNum . '\.' . $decimalPattern;
            } else {
                // 无小数部分
                $patterns[] = $escapedPrefix . $startMainNum;
            }
        }
        // 情况2: 起始编码和结束编码的主数字部分不同
        else {
            // 2.1 处理起始编码所在的主数字
            if ($decimalLength > 0) {
                $startDecimalNum = (int)$startDecimal;
                $maxDecimalForStart = (int)str_repeat('9', $decimalLength);
                $decimalPattern = $this->buildDecimalRangePattern($startDecimalNum, $maxDecimalForStart, $decimalLength);
                $patterns[] = $escapedPrefix . $startMainNum . '\.' . $decimalPattern;
            } else {
                $patterns[] = $escapedPrefix . $startMainNum;
            }

            // 2.2 处理中间的主数字（完整范围）
            if ($endMainNum - $startMainNum > 1) {
                if ($decimalLength > 0) {
                    // 有小数部分，中间主数字的所有小数组合
                    $maxDecimal = (int)str_repeat('9', $decimalLength);
                    $decimalPattern = $this->buildDecimalRangePattern(0, $maxDecimal, $decimalLength);
                    $middleMainNums = [];
                    for ($mainNum = $startMainNum + 1; $mainNum < $endMainNum; $mainNum++) {
                        $middleMainNums[] = $mainNum;
                    }
                    if (count($middleMainNums) > 0) {
                        // 为每个中间主数字生成模式（因为多位数不能使用简单的范围模式）
                        $middlePatterns = [];
                        foreach ($middleMainNums as $mainNum) {
                            $middlePatterns[] = $escapedPrefix . preg_quote((string)$mainNum, '/') . '\.' . $decimalPattern;
                        }
                        $patterns = array_merge($patterns, $middlePatterns);
                    }
                } else {
                    // 无小数部分，中间主数字范围
                    if ($endMainNum - $startMainNum > 2) {
                        $patterns[] = $escapedPrefix . '[' . ($startMainNum + 1) . '-' . ($endMainNum - 1) . ']';
                    } else {
                        $patterns[] = $escapedPrefix . ($startMainNum + 1);
                    }
                }
            }

            // 2.3 处理结束编码所在的主数字
            if ($endHasDecimal && $decimalLength > 0) {
                // 结束编码有小数部分，生成从0到结束小数的小数组合
                $endDecimalNum = (int)$endDecimal;
                $maxDecimalForEnd = (int)str_repeat('9', $decimalLength);
                if ($endDecimalNum > $maxDecimalForEnd) {
                    $endDecimalNum = $maxDecimalForEnd;
                }
                $decimalPattern = $this->buildDecimalRangePattern(0, $endDecimalNum, $decimalLength);
                $patterns[] = $escapedPrefix . $endMainNum . '\.' . $decimalPattern;
            } elseif (!$endHasDecimal && $decimalLength == 0) {
                // 结束编码无小数部分，且起始编码也无小数部分，添加结束主数字
                $patterns[] = $escapedPrefix . $endMainNum;
            } elseif (!$endHasDecimal && $decimalLength > 0) {
                // 结束编码无小数部分，但起始编码有小数部分，需要处理结束主数字的所有小数组合
                $maxDecimal = (int)str_repeat('9', $decimalLength);
                $decimalPattern = $this->buildDecimalRangePattern(0, $maxDecimal, $decimalLength);
                $patterns[] = $escapedPrefix . $endMainNum . '\.' . $decimalPattern;
            }
        }

        return implode('|', $patterns);
    }

    /**
     * 构建小数范围的正则表达式模式
     * @param int $start 起始小数
     * @param int $end 结束小数
     * @param int $length 小数位数
     * @return string 正则表达式模式
     */
    private function buildDecimalRangePattern(int $start, int $end, int $length): string
    {
        if ($start === $end) {
            return str_pad((string)$start, $length, '0', STR_PAD_LEFT);
        }

        $maxValue = (int)str_repeat('9', $length);
        if ($end > $maxValue) {
            $end = $maxValue;
        }

        $startStr = str_pad((string)$start, $length, '0', STR_PAD_LEFT);
        $endStr = str_pad((string)$end, $length, '0', STR_PAD_LEFT);

        // 找到第一个不同的位置
        $diffPos = 0;
        for ($pos = 0; $pos < $length; $pos++) {
            if ($startStr[$pos] !== $endStr[$pos]) {
                $diffPos = $pos;
                break;
            }
        }

        $prefix = substr($startStr, 0, $diffPos);
        $startDigit = (int)$startStr[$diffPos];
        $endDigit = (int)$endStr[$diffPos];
        $suffixLength = $length - $diffPos - 1;

        // 如果差异位置后面的数字需要处理
        if ($suffixLength > 0) {
            $startSuffix = substr($startStr, $diffPos + 1);
            $endSuffix = substr($endStr, $diffPos + 1);
            $startSuffixNum = (int)$startSuffix;
            $endSuffixNum = (int)$endSuffix;
            $maxSuffix = (int)str_repeat('9', $suffixLength);

            $patterns = [];

            if ($startDigit < $endDigit) {
                // 起始数字到结束数字-1的完整范围
                if ($startDigit < $endDigit - 1) {
                    $patterns[] = $prefix . '[' . $startDigit . '-' . ($endDigit - 1) . '][0-9]{' . $suffixLength . '}';
                }

                // 起始数字的特殊范围
                if ($startSuffixNum < $maxSuffix) {
                    $startSuffixPattern = $this->buildSuffixPattern($startSuffixNum, $maxSuffix, $suffixLength);
                    $patterns[] = $prefix . $startDigit . $startSuffixPattern;
                } else {
                    $patterns[] = $prefix . $startDigit . str_repeat('9', $suffixLength);
                }

                // 结束数字的特殊范围
                if ($endSuffixNum > 0) {
                    $endSuffixPattern = $this->buildSuffixPattern(0, $endSuffixNum, $suffixLength);
                    $patterns[] = $prefix . $endDigit . $endSuffixPattern;
                } else {
                    $patterns[] = $prefix . $endDigit . str_repeat('0', $suffixLength);
                }
            } else {
                // 同一数字，只有后缀不同
                $suffixPattern = $this->buildSuffixPattern($startSuffixNum, $endSuffixNum, $suffixLength);
                $patterns[] = $prefix . $startDigit . $suffixPattern;
            }

            // 如果模式太多，使用通用模式
            if (count($patterns) > 10) {
                return '[0-9]{' . $length . '}';
            }

            return '(' . implode('|', $patterns) . ')';
        } else {
            // 最后一位不同，简单范围
            return $prefix . '[' . $startDigit . '-' . $endDigit . ']';
        }
    }

    /**
     * 构建后缀范围的正则表达式模式
     * @param int $start 起始值
     * @param int $end 结束值
     * @param int $length 位数
     * @return string 正则表达式模式
     */
    private function buildSuffixPattern(int $start, int $end, int $length): string
    {
        if ($start === $end) {
            return str_pad((string)$start, $length, '0', STR_PAD_LEFT);
        }

        $startStr = str_pad((string)$start, $length, '0', STR_PAD_LEFT);
        $endStr = str_pad((string)$end, $length, '0', STR_PAD_LEFT);

        // 如果范围太大，使用通用模式
        if ($end - $start > 90) {
            return '[0-9]{' . $length . '}';
        }

        // 找到第一个不同的位置
        $diffPos = 0;
        for ($pos = 0; $pos < $length; $pos++) {
            if ($startStr[$pos] !== $endStr[$pos]) {
                $diffPos = $pos;
                break;
            }
        }

        $prefix = substr($startStr, 0, $diffPos);
        $startDigit = (int)$startStr[$diffPos];
        $endDigit = (int)$endStr[$diffPos];
        $suffixLength = $length - $diffPos - 1;

        if ($suffixLength > 0) {
            $startSuffix = substr($startStr, $diffPos + 1);
            $endSuffix = substr($endStr, $diffPos + 1);
            $startSuffixNum = (int)$startSuffix;
            $endSuffixNum = (int)$endSuffix;
            $maxSuffix = (int)str_repeat('9', $suffixLength);

            $patterns = [];

            if ($startDigit < $endDigit) {
                // 中间数字的完整范围
                if ($startDigit < $endDigit - 1) {
                    $patterns[] = '[' . $startDigit . '-' . ($endDigit - 1) . '][0-9]{' . $suffixLength . '}';
                }

                // 起始数字的范围
                if ($startSuffixNum < $maxSuffix) {
                    $patterns[] = $startDigit . $this->buildSuffixPattern($startSuffixNum, $maxSuffix, $suffixLength);
                } else {
                    $patterns[] = $startDigit . str_repeat('9', $suffixLength);
                }

                // 结束数字的范围
                if ($endSuffixNum > 0) {
                    $patterns[] = $endDigit . $this->buildSuffixPattern(0, $endSuffixNum, $suffixLength);
                } else {
                    $patterns[] = $endDigit . str_repeat('0', $suffixLength);
                }
            } else {
                // 同一数字，递归处理后缀
                $patterns[] = $startDigit . $this->buildSuffixPattern($startSuffixNum, $endSuffixNum, $suffixLength);
            }

            if (count($patterns) > 10) {
                return '[0-9]{' . $length . '}';
            }

            return '(' . implode('|', $patterns) . ')';
        } else {
            return '[' . $startDigit . '-' . $endDigit . ']';
        }
    }

    /**
     * 生成 ICD09 范围查询的正则表达式(手术编码)
     * @param string $start 开始编码 (例如: '39.1', '39.12', '39')
     * @param string $end 结束编码 (例如: '39.7', '39.55', '40')
     * @return array ES查询条件
     */
    private function generateICD9RangeQuery(string $start, string $end): array
    {
        // 只去除首尾空格，保持原始格式
        $start = trim($start);
        $end = trim($end);

        // 检查是否有小数点
        $startHasDecimal = strpos($start, '.') !== false;
        $endHasDecimal = strpos($end, '.') !== false;

        // 提取主数字部分（小数点前的数字）
        $startMainNum = $startHasDecimal ? (int)explode('.', $start)[0] : (int)$start;
        $endMainNum = $endHasDecimal ? (int)explode('.', $end)[0] : (int)$end;

        // 提取小数部分
        $startDecimal = $startHasDecimal ? explode('.', $start)[1] : '';
        $endDecimal = $endHasDecimal ? explode('.', $end)[1] : '';

        // 小数位数以起始编码为主
        $decimalLength = strlen($startDecimal);

        // 如果结束编码有小数部分但位数少于起始编码，需要补齐
        if ($endHasDecimal && strlen($endDecimal) < $decimalLength && $decimalLength > 0) {
            $endDecimal = str_pad($endDecimal, $decimalLength, '0', STR_PAD_RIGHT);
        }

        // 生成所有需要匹配的编码前缀
        $prefixes = [];

        // 情况1: 起始编码和结束编码的主数字部分相同（如 39.12 到 39.55）
        if ($startMainNum === $endMainNum) {
            if ($decimalLength > 0) {
                // 有小数部分，生成小数范围
                $startDecimalNum = (int)$startDecimal;
                $endDecimalNum = $endHasDecimal ? (int)$endDecimal : (int)str_repeat('9', $decimalLength);

                // 确保结束小数不超过该位数的最大值
                $maxDecimal = (int)str_repeat('9', $decimalLength);
                if ($endDecimalNum > $maxDecimal) {
                    $endDecimalNum = $maxDecimal;
                }

                for ($i = $startDecimalNum; $i <= $endDecimalNum; $i++) {
                    $decimalStr = str_pad((string)$i, $decimalLength, '0', STR_PAD_LEFT);
                    $prefixes[] = $startMainNum . '.' . $decimalStr;
                }
            } else {
                // 无小数部分，直接添加
                $prefixes[] = (string)$startMainNum;
            }
        }
        // 情况2: 起始编码和结束编码的主数字部分不同
        else {
            // 2.1 处理起始编码所在的主数字（从起始编码的小数部分开始到该主数字的最大值）
            if ($decimalLength > 0) {
                // 有小数部分
                $startDecimalNum = (int)$startDecimal;
                $maxDecimalForStart = (int)str_repeat('9', $decimalLength);

                for ($i = $startDecimalNum; $i <= $maxDecimalForStart; $i++) {
                    $decimalStr = str_pad((string)$i, $decimalLength, '0', STR_PAD_LEFT);
                    $prefixes[] = $startMainNum . '.' . $decimalStr;
                }
            } else {
                // 无小数部分
                $prefixes[] = (string)$startMainNum;
            }

            // 2.2 处理中间的主数字（完整范围）
            if ($endMainNum - $startMainNum > 1) {
                for ($mainNum = $startMainNum + 1; $mainNum < $endMainNum; $mainNum++) {
                    if ($decimalLength > 0) {
                        // 有小数部分，生成该主数字的所有小数组合
                        $maxDecimal = (int)str_repeat('9', $decimalLength);
                        for ($i = 0; $i <= $maxDecimal; $i++) {
                            $decimalStr = str_pad((string)$i, $decimalLength, '0', STR_PAD_LEFT);
                            $prefixes[] = $mainNum . '.' . $decimalStr;
                        }
                    } else {
                        // 无小数部分
                        $prefixes[] = (string)$mainNum;
                    }
                }
            }

            // 2.3 处理结束编码所在的主数字
            // 如果结束编码有小数部分，则从0或指定小数到结束编码的小数部分
            // 如果结束编码无小数部分且起始编码也无小数部分，则添加结束主数字
            // 如果结束编码无小数部分但起始编码有小数部分，需要处理结束主数字的所有小数组合
            if ($endHasDecimal && $decimalLength > 0) {
                // 结束编码有小数部分，生成从0到结束小数的小数组合
                $endDecimalNum = (int)$endDecimal;
                $maxDecimalForEnd = (int)str_repeat('9', $decimalLength);
                if ($endDecimalNum > $maxDecimalForEnd) {
                    $endDecimalNum = $maxDecimalForEnd;
                }

                for ($i = 0; $i <= $endDecimalNum; $i++) {
                    $decimalStr = str_pad((string)$i, $decimalLength, '0', STR_PAD_LEFT);
                    $prefixes[] = $endMainNum . '.' . $decimalStr;
                }
            } elseif (!$endHasDecimal && $decimalLength == 0) {
                // 结束编码无小数部分，且起始编码也无小数部分，添加结束主数字
                $prefixes[] = (string)$endMainNum;
            } elseif (!$endHasDecimal && $decimalLength > 0) {
                // 结束编码无小数部分，但起始编码有小数部分，需要处理结束主数字的所有小数组合
                $maxDecimal = (int)str_repeat('9', $decimalLength);
                for ($i = 0; $i <= $maxDecimal; $i++) {
                    $decimalStr = str_pad((string)$i, $decimalLength, '0', STR_PAD_LEFT);
                    $prefixes[] = $endMainNum . '.' . $decimalStr;
                }
            }
        }

        // 去重并排序
        $prefixes = array_unique($prefixes);
        sort($prefixes);

        // 如果编码前缀数量过多（超过50个），使用紧凑的正则表达式模式
        if (count($prefixes) > 50) {
            $regexp = $this->buildCompactRegexpForICD9($startMainNum, $endMainNum, $startHasDecimal, $endHasDecimal, $startDecimal, $endDecimal, $decimalLength);
        } else {
            // 构建正则表达式：转义特殊字符并组合
            $escapedPrefixes = array_map(function ($p) {
                return preg_quote($p, '/');
            }, $prefixes);

            $regexp = implode('|', $escapedPrefixes);
        }

        return ['regexp' => "({$regexp}).*"];
    }

    /**
     * 构建紧凑的正则表达式模式（ICD9，无字母前缀）
     * @param int $startMainNum 起始主数字
     * @param int $endMainNum 结束主数字
     * @param bool $startHasDecimal 起始编码是否有小数
     * @param bool $endHasDecimal 结束编码是否有小数
     * @param string $startDecimal 起始小数部分
     * @param string $endDecimal 结束小数部分
     * @param int $decimalLength 小数位数
     * @return string 紧凑的正则表达式
     */
    private function buildCompactRegexpForICD9(int $startMainNum, int $endMainNum, bool $startHasDecimal, bool $endHasDecimal, string $startDecimal, string $endDecimal, int $decimalLength): string
    {
        $patterns = [];

        // 如果结束编码有小数部分但位数少于起始编码，需要补齐
        if ($endHasDecimal && strlen($endDecimal) < $decimalLength && $decimalLength > 0) {
            $endDecimal = str_pad($endDecimal, $decimalLength, '0', STR_PAD_RIGHT);
        }

        // 情况1: 起始编码和结束编码的主数字部分相同
        if ($startMainNum === $endMainNum) {
            if ($decimalLength > 0) {
                // 有小数部分，生成小数范围的正则表达式
                $startDecimalNum = (int)$startDecimal;
                $endDecimalNum = $endHasDecimal ? (int)$endDecimal : (int)str_repeat('9', $decimalLength);
                $maxDecimal = (int)str_repeat('9', $decimalLength);
                if ($endDecimalNum > $maxDecimal) {
                    $endDecimalNum = $maxDecimal;
                }

                $decimalPattern = $this->buildDecimalRangePattern($startDecimalNum, $endDecimalNum, $decimalLength);
                $patterns[] = preg_quote((string)$startMainNum, '/') . '\.' . $decimalPattern;
            } else {
                // 无小数部分
                $patterns[] = preg_quote((string)$startMainNum, '/');
            }
        }
        // 情况2: 起始编码和结束编码的主数字部分不同
        else {
            // 2.1 处理起始编码所在的主数字
            if ($decimalLength > 0) {
                $startDecimalNum = (int)$startDecimal;
                $maxDecimalForStart = (int)str_repeat('9', $decimalLength);
                $decimalPattern = $this->buildDecimalRangePattern($startDecimalNum, $maxDecimalForStart, $decimalLength);
                $patterns[] = preg_quote((string)$startMainNum, '/') . '\.' . $decimalPattern;
            } else {
                $patterns[] = preg_quote((string)$startMainNum, '/');
            }

            // 2.2 处理中间的主数字（完整范围）
            if ($endMainNum - $startMainNum > 1) {
                if ($decimalLength > 0) {
                    // 有小数部分，中间主数字的所有小数组合
                    $maxDecimal = (int)str_repeat('9', $decimalLength);
                    $decimalPattern = $this->buildDecimalRangePattern(0, $maxDecimal, $decimalLength);
                    $middleMainNums = [];
                    for ($mainNum = $startMainNum + 1; $mainNum < $endMainNum; $mainNum++) {
                        $middleMainNums[] = $mainNum;
                    }
                    if (count($middleMainNums) > 0) {
                        // 为每个中间主数字生成模式（因为多位数不能使用简单的范围模式）
                        foreach ($middleMainNums as $mainNum) {
                            $patterns[] = preg_quote((string)$mainNum, '/') . '\.' . $decimalPattern;
                        }
                    }
                } else {
                    // 无小数部分，中间主数字范围
                    // 对于多位数主数字，需要为每个主数字生成单独的模式
                    $middleMainNums = [];
                    for ($mainNum = $startMainNum + 1; $mainNum < $endMainNum; $mainNum++) {
                        $middleMainNums[] = $mainNum;
                    }
                    if (count($middleMainNums) > 0) {
                        foreach ($middleMainNums as $mainNum) {
                            $patterns[] = preg_quote((string)$mainNum, '/');
                        }
                    }
                }
            }

            // 2.3 处理结束编码所在的主数字
            if ($endHasDecimal && $decimalLength > 0) {
                // 结束编码有小数部分，生成从0到结束小数的小数组合
                $endDecimalNum = (int)$endDecimal;
                $maxDecimalForEnd = (int)str_repeat('9', $decimalLength);
                if ($endDecimalNum > $maxDecimalForEnd) {
                    $endDecimalNum = $maxDecimalForEnd;
                }
                $decimalPattern = $this->buildDecimalRangePattern(0, $endDecimalNum, $decimalLength);
                $patterns[] = preg_quote((string)$endMainNum, '/') . '\.' . $decimalPattern;
            } elseif (!$endHasDecimal && $decimalLength == 0) {
                // 结束编码无小数部分，且起始编码也无小数部分，添加结束主数字
                $patterns[] = preg_quote((string)$endMainNum, '/');
            } elseif (!$endHasDecimal && $decimalLength > 0) {
                // 结束编码无小数部分，但起始编码有小数部分，需要处理结束主数字的所有小数组合
                $maxDecimal = (int)str_repeat('9', $decimalLength);
                $decimalPattern = $this->buildDecimalRangePattern(0, $maxDecimal, $decimalLength);
                $patterns[] = preg_quote((string)$endMainNum, '/') . '\.' . $decimalPattern;
            }
        }

        return implode('|', $patterns);
    }

    /**
     * 构建主数字范围的正则表达式模式
     * @param array $mainNums 主数字数组
     * @return string 正则表达式模式
     */
    private function buildMainNumRangePattern(array $mainNums): string
    {
        if (count($mainNums) === 1) {
            return preg_quote((string)$mainNums[0], '/');
        }

        // 检查是否连续
        $isConsecutive = true;
        for ($i = 1; $i < count($mainNums); $i++) {
            if ($mainNums[$i] !== $mainNums[$i - 1] + 1) {
                $isConsecutive = false;
                break;
            }
        }

        if ($isConsecutive) {
            // 连续范围
            return '[' . $mainNums[0] . '-' . $mainNums[count($mainNums) - 1] . ']';
        } else {
            // 不连续，使用或操作符
            $escapedNums = array_map(function ($n) {
                return preg_quote((string)$n, '/');
            }, $mainNums);
            return '(' . implode('|', $escapedNums) . ')';
        }
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
        $patientMedicalInfoAEL01 = [];
        $icu = [];
        $patientAdd = [];
        $LNSSQ = [];
        $LNSSH = [];
        $error = [];
        $emr_bl_bl01Info = [];
        $bllb_info = [];
        $zyts = [];
        $operation = [];
        $page = $isExport == 1 ? 1 : $request->post('page', 1);
        $limit = $isExport == 1 ? 100000 : $request->post('limit', 20);
        $offset = ($page - 1) * $limit < 1 ? 1 : ($page - 1) * $limit;
        $page = checkPageStart($page, $limit);
        //病案号
        $AAA28 = $request->post('AAA28', '');
        if (!empty($AAA28) && !static::isNull($AAA28)) {
            $patientInfo['and'][] = ['select_field' => 'AAA28', 'field_value' => $AAA28, 'select_type' => 'term'];
        }
        //$patientInfo['and'][] = ['select_field' => 'is_CATA', 'field_value' => 1, 'select_type' => 'term'];
        //出院科室
        $AAC11C = $request->post('AAC11C', 'all');
        if ($AAC11C != 'all' && !static::isNull($AAC11C)) {
            $patientInfo['and'][] = ['select_field' => 'AAC02C', 'field_value' => $AAC11C, 'select_type' => 'term'];
        }
        //问题属性
        $level = $request->post('level', 'all');
        $source = $request->post('source', 0);
        if ($level != 'all') {
            $error['and'][] = ['select_field' => 'level', 'field_value' => $level, 'select_type' => 'term'];
            $error['and'][] = ['select_field' => 'source', 'field_value' => $source, 'select_type' => 'term'];
        }
        //付款方式
        $AAA26C = $request->post('AAA26C', 'all');
        if ($AAA26C != 'all' && !static::isNull($AAA26C)) {
            $patientInfo['and'][] = ['select_field' => 'AAA26C', 'field_value' => $AAA26C, 'select_type' => 'term'];
        }
        //质控状态
        $status = $request->post('status', 'all');
        if ($status != 'all' && !static::isNull($status)) {
            $patientInfo['and'][] = ['select_field' => 'status', 'field_value' => $status, 'select_type' => 'term'];
        }
        //质控状态
        $ORG_STATE = $request->post('ORG_STATE', 'all');
        if ($ORG_STATE != 'all' && !static::isNull($ORG_STATE)) {
            $patientInfo['and'][] = ['select_field' => 'ORG_STATE', 'field_value' => $ORG_STATE, 'select_type' => 'term'];
        }
        //入院时间
        $startDateaab01 = $request->post('AAB01_start_time', null);
        $endDateaab01 = $request->post('AAB01_end_time', null);
        if (!empty($startDateaab01)) {
            $startDateaab01 = date('Y-m-d', strtotime($startDateaab01)) . ' 00:00:00';
            $zyts['and'][] = ['select_field' => 'AAB01', 'field_value' => $startDateaab01, 'select_type' => 'gte'];
        }
        if (!empty($endDateaab01)) {
            $endDateaab01 = date('Y-m-d', strtotime($endDateaab01)) . ' 23:59:59';
            $zyts['and'][] = ['select_field' => 'AAB01', 'field_value' => $endDateaab01, 'select_type' => 'lte'];
        }
        //出院时间
        $startDate = $request->post('AAC01_start_date', null);
        $endDate = $request->post('AAC01_end_date', null);
        if (!empty($startDate)) {
            $startDate = date('Y-m-d', strtotime($startDate)) . ' 00:00:00';
            $zyts['and'][] = ['select_field' => 'AAC01', 'field_value' => $startDate, 'select_type' => 'gte'];
        }
        if (!empty($endDate)) {
            $endDate = date('Y-m-d', strtotime($endDate)) . ' 23:59:59';
            $zyts['and'][] = ['select_field' => 'AAC01', 'field_value' => $endDate, 'select_type' => 'lte'];
        }
        //编码员ID
        $coder_id = $request->post('coder_id', 'all');
        if ($coder_id != 'all' && !static::isNull($coder_id)) {
            $patientDoctorInfo['and'][] = ['select_field' => 'AEE08', 'field_value' => $coder_id, 'select_type' => 'term'];
        }
        //字段条件
        $field = $request->post('field', '');
        if ($isExport == 1) {
            //            $field = json_decode($field, true);
        }
        if ($field != '' && !self::isNull($field)) {
            //            $field = array_column($field, null, 'key');
            foreach ($field as $item) {
                if (empty($item['key'])) {
                    continue;
                }
                if ($item['select_type'] == 1) {
                    $item['select_type'] = "or";
                } elseif ($item['select_type'] == 2) {
                    $item['select_type'] = "no";
                } else {
                    $item['select_type'] = "and";
                }
                if ($item['type'] == 0) {
                    $operator = 'match_phrase_prefix';
                } else {
                    if (is_array($item['value'])) {
                        $operator = 'terms';
                    } else {
                        $operator = 'term';
                    }
                }
                if (!is_numeric($item['value']) && empty($item['value'])) {
                    return ToolsService::returnData(0, [], "搜索条件必须有值");
                }
                if (is_array($item['value']) && $item['type'] == 0) {
                    return ToolsService::returnData(0, [], "请选择精准搜索");
                }
                switch ($item['key']) {
                    case 'ABC01N';
                        $mainDiagnosis[$item['select_type']][] = ['select_field' => 'ICD10_NAME', 'field_value' => $item['value'], 'select_type' => 'match_phrase_prefix'];
                        break;
                    case 'ABC01C';
                        $mainDiagnosis[$item['select_type']][] = ['select_field' => 'ICD10_ID1', 'field_value' => $item['value'], 'select_type' => 'match_phrase_prefix'];
                        break;
                    case 'ICD10_ID1_first';
                        $otherDiagnosis[$item['select_type']][] = [
                            ['select_field' => 'ICD10_ID1', 'field_value' => $item['value'], 'select_type' => 'match_phrase_prefix'],
                            ['select_field' => 'DIA_ORDER', 'field_value' => 1, 'select_type' => "term"]
                        ];
                        break;
                    case 'ICD10_NAME_first';
                        $otherDiagnosis[$item['select_type']][] = [
                            ['select_field' => 'ICD10_NAME', 'field_value' => $item['value'], 'select_type' => 'match_phrase_prefix'],
                            ['select_field' => 'DIA_ORDER', 'field_value' => 1, 'select_type' => "term"]
                        ];
                        break;
                    case 'ICD10_ID1';
                        $otherDiagnosis[$item['select_type']][] = ['select_field' => 'ICD10_ID1', 'field_value' => $item['value'], 'select_type' => 'match_phrase_prefix'];
                        break;
                    case 'ICD10_NAME';
                        $otherDiagnosis[$item['select_type']][] = ['select_field' => 'ICD10_NAME', 'field_value' => $item['value'], 'select_type' => 'match_phrase'];
                        break;
                    case 'ICD9_ID1';
                        $operation[$item['select_type']][] = ['select_field' => 'ICD9_ID1', 'field_value' => $item['value'], 'select_type' => 'match_phrase_prefix'];
                        break;
                    case 'ICD9_NAME';
                        $operation[$item['select_type']][] = ['select_field' => 'ICD9_NAME', 'field_value' => $item['value'], 'select_type' => 'match_phrase_prefix'];
                        break;
                    case 'ABC03C'; //主要诊断入院病情
                        if ($item['value'] > 0) {
                            $patientMedicalInfo[$item['select_type']][] = ['select_field' => 'ABC03C', 'field_value' => $item['value'], 'select_type' => $operator];
                        }
                        break;
                    case 'RYQK'; //其他诊断入院病情
                        if ($item['value'] > 0) {
                            $otherDiagnosis[$item['select_type']][] = ['select_field' => 'RYQK', 'field_value' => $item['value'], 'select_type' => $operator];
                        }
                        break;
                    case 'OPE_LEVEL'; //手术级别
                        if ($item['value'] != 0) {
                            $operation[$item['select_type']][] = ['select_field' => 'OPE_LEVEL', 'field_value' => $item['value'], 'select_type' => $operator];
                        }
                        break;
                    case 'IS_MAIN_WAY'; //重症监护室名称
                        $icu[$item['select_type']][] = ['select_field' => 'IS_MAIN_WAY', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'AEM01C'; //离院方式
                        if ($item['value'] != 0) {
                            $patientInfo[$item['select_type']][] = ['select_field' => 'AEM01C', 'field_value' => $item['value'], 'select_type' => $operator];
                        }
                        break;
                    case 'ABA01N'; //门急诊诊断
                        $patientMedicalInfo[$item['select_type']][] = ['select_field' => 'ABA01N', 'field_value' => $item['value'], 'select_type' => 'match_phrase_prefix'];
                        break;
                    case 'ABF01N'; //病理诊断名称
                        $patientMedicalInfo[$item['select_type']][] = ['select_field' => 'ABF01N', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'ABF01C'; //病理诊断编码
                        $patientMedicalInfo[$item['select_type']][] = ['select_field' => 'ABF01C', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'ABA01C'; //门急诊疾病编码
                        $patientMedicalInfo[$item['select_type']][] = ['select_field' => 'ABA01C', 'field_value' => $item['value'], 'select_type' => 'match_phrase_prefix'];
                        break;
                    case 'AEL01'; //呼吸机
                        if (in_array(1, $item['value'])) {
                            $patientMedicalInfoAEL01[$item['select_type']][] = ['select_field' => 'patient_medical_info.AEL01', 'field_value' => 0, 'select_type' => 'exists'];
                        }
                        if (in_array(2, $item['value'])) {
                            $patientMedicalInfoAEL01[$item['select_type']][] = ['select_field' => 'patient_medical_info.AEL01', 'field_value' => 0, 'select_type' => 'not_exists'];
                        }
                        break;
                    case 'RJSS'; //日间手术
                        if ($item['value'] != 0) {
                            $sSType = ['', '是', '否'];
                            $operation[$item['select_type']][] = ['select_field' => 'RJSS.keyword', 'field_value' => $item['value'], 'select_type' => $operator];
                        }
                        break;
                    case 'LNSSQ'; //颅脑损伤前昏迷
                        if (in_array(1, $item['value'])) {
                            $LNSSQ[$item['select_type']] = 'exists';
                        }
                        if (in_array(2, $item['value'])) {
                            $LNSSQ[$item['select_type']] = 'not_exists';
                        }
                        break;
                    case 'LNSSH'; //颅脑损伤后昏迷
                        if (in_array(1, $item['value'])) {
                            $LNSSH[$item['select_type']] = 'exists';
                        }
                        if (in_array(2, $item['value'])) {
                            $LNSSH[$item['select_type']] = 'not_exists';
                        }
                        break;
                    case 'AAA28'; //病案号
                        $patientInfo[$item['select_type']][] = ['select_field' => 'AAA28', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'AAA01'; //姓名
                        $patientInfo[$item['select_type']][] = ['select_field' => 'AAA01', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'AAA02C'; //性别
                        $patientInfo[$item['select_type']][] = ['select_field' => 'AAA02C', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'AAB06C'; //入院途径
                        $patientInfo[$item['select_type']][] = ['select_field' => 'AAB06C', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    //                    case 'AAA04';//年龄
                    //                        $patientInfo[$item['select_type']][] = ['AAA04'=>$item['value']];
                    //                        break;
                    case 'AAA29'; //住院次数
                        $patientInfo[$item['select_type']][] = ['select_field' => 'AAA29', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'SSPB': // 手术判别
                        if ($item['value'] < 5) {
                            $operation[$item['select_type']][] = ['select_field' => 'SSPB', 'field_value' => $item['value'], 'select_type' => $operator];
                        }
                        break;
                    case 'AAC11N': // 出院科室
                        if ($item['value'] != 'all') {
                            $patientInfo[$item['select_type']][] = ['select_field' => 'AAC02C', 'field_value' => $item['value'], 'select_type' => $operator];
                        }
                        break;
                    case 'AAC02C': // 出院科室
                        if ($item['value'] != 'all') {
                            $patientInfo[$item['select_type']][] = ['select_field' => 'AAC02C', 'field_value' => $item['value'], 'select_type' => $operator];
                        }
                        break;
                    case 'AAD01C': // 转科科室
                        $patientHospitalInfo[$item['select_type']][] = ['select_field' => 'ZKKBMC', 'field_value' => $item['value'], 'select_type' => 'match_phrase_prefix'];
                        break;

                    case 'AAB02C': // 入院科室
                        if ($item['value'] != 'all') {
                            $patientHospitalInfo[$item['select_type']][] = ['select_field' => 'AAB02C', 'field_value' => $item['value'], 'select_type' => $operator];
                        }
                        break;

                    case 'MO_OPE_TYPE': //主要手术类型
                        if (!empty($item['value'])) {
                            $mainOperation[$item['select_type']][] = ['select_field' => 'OPE_TYPE', 'field_value' => $item['value'], 'select_type' => $operator];
                        }
                        break;
                    case 'SO_OPE_TYPE': //其他手术类型
                        if (!empty($item['value'])) {
                            $secondaryOperation[$item['select_type']][] = ['select_field' => 'OPE_TYPE', 'field_value' => $item['value'], 'select_type' => $operator];
                        }
                        break;
                    case 'MO_ICD9_ID1': //主要手术编码
                        $mainOperation[$item['select_type']][] = ['select_field' => 'ICD9_ID1', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'SO_ICD9_ID1': //其他手术编码
                        $secondaryOperation[$item['select_type']][] = ['select_field' => 'ICD9_ID1', 'field_value' => $item['value'], 'select_type' => $operator];
                        break;
                    case 'MO_ICD9_NAME': //主要手术名称
                        $mainOperation[$item['select_type']][] = ['select_field' => 'ICD9_NAME', 'field_value' => $item['value'], 'select_type' => 'match_phrase_prefix'];
                        break;
                    case 'SO_ICD9_NAME': //其他手术名称
                        $secondaryOperation[$item['select_type']][] = ['select_field' => 'ICD9_NAME', 'field_value' => $item['value'], 'select_type' => 'match_phrase_prefix'];
                        break;
                    case 'MO_OPE_LEVEL': //主要手术级别
                        if ($item['value'] != 0) {
                            $mainOperation[$item['select_type']][] = ['select_field' => 'OPE_LEVEL', 'field_value' => $item['value'], 'select_type' => $operator];
                        }
                        break;
                    case 'SO_OPE_LEVEL': //其他手术手术级别
                        if ($item['value'] != 0) {
                            $secondaryOperation[$item['select_type']][] = ['select_field' => 'OPE_LEVEL', 'field_value' => $item['value'], 'select_type' => $operator];
                        }
                        break;
                    case 'MO_SSPB': // 主要手术判别
                        if ($item['value'] < 5) {
                            $mainOperation[$item['select_type']][] = ['select_field' => 'SSPB', 'field_value' => $item['value'], 'select_type' => $operator];
                        }
                        break;
                    case 'SO_SSPB': // 其他手术判别
                        if ($item['value'] < 5) {
                            $secondaryOperation[$item['select_type']][] = ['select_field' => 'SSPB', 'field_value' => $item['value'], 'select_type' => $operator];
                        }
                        break;
                    case 'AEE10': // 责任护士
                        if ($item['value'] != 0) {
                            $name = Staff::query()->where('code', '=', $item['value'])->value('name');
                            $patientDoctorInfo[$item['select_type']][] = ['select_field' => 'AEE10', 'field_value' => $name, 'select_type' => 'match_phrase'];
                        }
                        break;
                    case 'AEE03': // 主治医师
                        if ($item['value'] != 0) {
                            $patientDoctorInfo[$item['select_type']][] = ['select_field' => 'AEE03_CODE', 'field_value' => $item['value'], 'select_type' => $operator];
                        }
                        break;
                    case 'AEE04': // 住院医师
                        if ($item['value'] != 0) {
                            $patientDoctorInfo[$item['select_type']][] = ['select_field' => 'AEE04_CODE', 'field_value' => $item['value'], 'select_type' => $operator];
                        }
                        break;
                    case 'AEE01': // 科主任
                        if ($item['value'] != 0) {
                            $patientDoctorInfo[$item['select_type']][] = ['select_field' => 'AEE01_CODE', 'field_value' => $item['value'], 'select_type' => $operator];
                        }
                        break;
                    case 'AEE02': // 主(副主)任医师
                        if ($item['value'] != 0) {
                            $patientDoctorInfo[$item['select_type']][] = ['select_field' => 'AEE02_CODE', 'field_value' => $item['value'], 'select_type' => $operator];
                        }
                        break;
                    case 'ZZYISXM': // 医疗组长
                        if ($item['value'] != 0) {
                            $name = Staff::query()->where('code', '=', $item['value'])->value('name');
                            $patientAdd[$item['select_type']][] = ['select_field' => 'ZZYISXM', 'field_value' => $name, 'select_type' => 'match_phrase'];
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
                    $zyts['and'][] = ['select_field' => 'AAA40', 'field_value' => $age_start, 'select_type' => 'gte'];
                    $zyts['and'][] = ['select_field' => 'AAA40', 'field_value' => $age_end, 'select_type' => 'lte'];
                } elseif ($age_start > 0 && $age_end <= 0) {
                    $zyts['and'][] = ['select_field' => 'AAA40', 'field_value' => $age_start, 'select_type' => 'gte'];
                } elseif ($age_start <= 0 && $age_end > 0) {
                    $zyts['and'][] = ['select_field' => 'AAA40', 'field_value' => $age_end, 'select_type' => 'lte'];
                }
            }
            if ($age_start_type == 2) { //年
                if ($age_start > 0 && $age_end > 0) {
                    $zyts['and'][] = ['select_field' => 'AAA04', 'field_value' => $age_start, 'select_type' => 'gte'];
                    $zyts['and'][] = ['select_field' => 'AAA04', 'field_value' => $age_end, 'select_type' => 'lte'];
                } elseif ($age_start > 0 && $age_end <= 0) {
                    $zyts['and'][] = ['select_field' => 'AAA04', 'field_value' => $age_start, 'select_type' => 'gte'];
                } elseif ($age_start <= 0 && $age_end > 0) {
                    $zyts['and'][] = ['select_field' => 'AAA04', 'field_value' => $age_end, 'select_type' => 'lte'];
                }
            }
        } elseif (($age_start_type == 1) && ($age_end_type == 2)) { //多少天-多少年
            if ($age_start > 0 && $age_end > 0) {
                $zyts['and'][] = ['select_field' => 'AAA40', 'field_value' => $age_start, 'select_type' => 'gte'];
                $zyts['and'][] = ['select_field' => 'AAA04', 'field_value' => $age_end, 'select_type' => 'lte'];
            } elseif ($age_start > 0 && $age_end <= 0) {
                $zyts['and'][] = ['select_field' => 'AAA40', 'field_value' => $age_start, 'select_type' => 'gte'];
            } elseif ($age_start <= 0 && $age_end > 0) {
                $zyts['and'][] = ['select_field' => 'AAA04', 'field_value' => $age_end, 'select_type' => 'lte'];
            }
        }
        //住院天数
        $AAC0401 = $request->post('AAC0401', 0);
        $AAC0402 = $request->post('AAC0402', 0);
        if ($AAC0401 > 0 && $AAC0402 > 0) {
            $zyts['and'][] = ['select_field' => 'AAC04', 'field_value' => $AAC0401, 'select_type' => 'gte'];
            $zyts['and'][] = ['select_field' => 'AAC04', 'field_value' => $AAC0402, 'select_type' => 'lte'];
        } elseif ($AAC0401 > 0 && $AAC0402 <= 0) {
            $zyts['and'][] = ['select_field' => 'AAC04', 'field_value' => $AAC0401, 'select_type' => 'gte'];
        } elseif ($AAC0401 <= 0 && $AAC0402 > 0) {
            $zyts['and'][] = ['select_field' => 'AAC04', 'field_value' => $AAC0402, 'select_type' => 'lte'];
        }
        //关键词搜索
        $search_keyword = $request->post("search_keyword", "");
        if (!empty($search_keyword)) {
            $emr_bl_bl01Info['and'][] = ['select_field' => 'BLMC', 'field_value' => $search_keyword, 'select_type' => 'match_phrase_prefix'];
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
                $bllb_info['and'][] = ['select_field' => 'BLLB', 'field_value' => $bllb_config, 'select_type' => 'terms'];
            } else {
                $bllb_info['and'][] = ['select_field' => 'BLLB', 'field_value' => $bllb, 'select_type' => 'terms'];
            }
        }

        //范围搜索
        $rangeName = $request->post('rangeName', '');
        $bmStart = $request->post('bmStart', '');
        $bmEnd = $request->post('bmEnd', '');
        $bm = [];
        if (is_numeric($rangeName)) {
            if (!empty($bmStart) && !empty($bmEnd)) {
                switch ($rangeName) {
                    case 1: //主要诊断编码
                    case 6: //主要诊断编码
                        $bm = $this->generateICD10RangeQuery($bmStart, $bmEnd);
                        break;
                    case 2: //其他诊断编码
                        $bm = $this->generateICD10RangeQuery($bmStart, $bmEnd);
                        break;
                    case 3: //主要手术编码
                    case 5: //主要手术编码
                        $bm = $this->generateICD9RangeQuery($bmStart, $bmEnd);
                        break;
                    case 4: //其他手术编码
                        $bm = $this->generateICD9RangeQuery($bmStart, $bmEnd);
                        break;
                }
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
            $error,
            $zyts,
            $page,
            $patientMedicalInfoAEL01,
            $operation,
            $bm,
            $rangeName
        );

        if ($isExport == 1) {
            return $this->qualityHomeListExportData($data, $source);
        }

        if ($request->post('is_tm') == 1) {
            foreach ($data['list'] as &$datum) {
                $datum['AAA01'] = desensitize($datum['AAA01'], 1, 0, '*');
            }
        }
        return ToolsService::returnData(200, $data);
    }

    public function qualityListV2(Request $request)
    {
        $isExport = $request->post("is_export", 0);
        $isDefect = $request->post("is_defect", 0); // 是否有缺陷
        $AAC11N = $request->post("AAC11N", '');
        $AAA28 = $request->post("AAA28", '');
        $ruleId = $request->post("error_rule", '');
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

        // 获取当前登录用户所属科室
        $dep_id = UserService::getCurrentUserDep($request);

        if (empty($dep_id)) {
            return ToolsService::returnData(200, ['list' => [], 'count' => 0]);
        }

        $query = PatientInfo::query()
            ->leftJoin('ZY_BRRY as brry', 'patient_info.MED_REC_ID', '=', 'brry.ZYH')
            ->where(function ($query) {
                $query->where('patient_info.IS_CATA', 1)->orWhere('patient_info.AAC01', '>', date("Y-m-d 00:00:00"));
            });
        $query = $query->whereNotNull('patient_info.AAA28'); //AAA28不为null

        if (is_array($dep_id)) {
            $query = $query->whereIn('brry.BRKS', $dep_id);
        }

        if (!empty($ruleId)) {
            $query = $query->leftJoin('case_quality_v2 as cq', 'patient_info.MED_REC_ID', '=', 'cq.JZHM');
            $query = $query->where('cq.rule_id', '=', $ruleId);

            $rule = CaseRule::query()->where('id', '=', $ruleId)->get()->toArray();
        }
        if (!empty($AAA28)) {
            $query = $query->where('patient_info.AAA28', '=', $AAA28);
        }
        if ($start_time && $end_time) {
            $whereCase = 'patient_info.AAB01 > "' . $start_time . '" and patient_info.AAB01 < "' . $end_time . '"';
            $query = $query->whereRaw($whereCase);
        }
        if (!empty($AAC11N)) {
            $query = $query->where('patient_info.AAC02C', '=', $AAC11N);
        }
        if ($isDefect == 1) {
            $query = $query->where('patient_info.score', '<', 100);
        }
        $count = $query->count();

        $list = $query
            ->offset($offset)
            ->limit($limit)
            // 获取病人科室，病案号，患者姓名，评分，评分等级，入院时间，床位号，管床医生，主治医师，诊疗组长，科主任
            ->get([
                'patient_info.AAC11N',
                'patient_info.AAA28',
                'patient_info.AAA01',
                'patient_info.AAC01',
                'patient_info.score',
                'patient_info.score_lv',
                'brry.CH',
                'brry.GCYSMC',
                'brry.ZZYSMC',
                'brry.ZLZZMC',
                'brry.ZRYS_MC'
            ])->toArray();
        if ($isExport == 1) {
            return $this->errorDataListExportData($list);
        }
        foreach ($list as &$l) {
            $l['rule_notice'] = !empty($rule[0]) ? $rule[0]['notice'] : '';
        }

        return ToolsService::returnData(200, ['list' => $list, 'count' => $count]);
    }

    /**
     * 首页病案列表导出
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    protected function qualityHomeListExportData($data, $source)
    {
        $exportData = [];
        $exportData[] = [
            '序号',
            '病案号',
            '患者名称',
            '性别',
            '年龄',
            '住院次数',
            '出院科室',
            '出院日期',
            '主要诊断名称',
            '主要诊断编码',
            '主要手术名称',
            '主要手术编码',
            '手术判别',
            '手术级别',
            '总费用',
            '药品费用',
            '材料费用',
            '实际住院（天）',
            '离院方式',
            '入院途径',
            '入院科室',
            '入院日期',
            '入院病情'
        ];
        $index = 1;
        foreach ($data['list'] as $key => $val) {
            $exportData[$key + 1] = [
                $index,
                $val['AAA28'] ? $val['AAA28'] . "\t" : '',
                $val['AAA01'] ?? '',
                $val['AAA02C'] ?? '',
                $val['AAA04'] ?? '',
                $val['AAA29'] ?? '',
                $val['AAC11N'] ?? '',
                $val['AAC01'] ?? '',
                $val['ICD10_NAME'] ?? '',
                $val['ICD10_ID1'] ?? '',
                $val['ICD9_NAME'] ?? '',
                $val['ICD9_ID1'] ?? '',
                $val['SSPB'] ?? '',
                $val['OPE_LEVEL'] ?? '',
                $val['ADA01'] ?? '',
                $val['F_D'] ?? '',
                $val['J'] ?? '',
                $val['AAC04'] ?? '',
                $val['AEM01C'] ?? '',
                $val['AAB06C'] ?? '',
                $val['AAB11N'] ?? '',
                $val['AAB01'] ?? '',
                $val['ABC03C'] ?? ''
            ];
            $index++;
        }
        ## 导出CSV
        $csv = new CsvService();
        $csv->filename = $csv->charset('病案列表', 'UTF-8');

        return $csv->export($exportData, false);
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
                $val['ABC03C'] ?? '',
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
                'end' => '06-30'
            ],
            [
                'id' => 3,
                'name' => '第三季度',
                'start' => '07-01',
                'end' => '09-30'
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
        $coder = Staff::query()->get(["code as id", 'name'])->toArray();
        $status = [['id' => 'all', 'name' => '全部'], ['id' => '0', 'name' => '未编辑'], ['id' => '1', 'name' => '已编辑']];

        $pay = config('dictionaries.AAA26C');
        $payData[] = ['id' => 'all', 'name' => '全部'];
        foreach ($pay as $key => $item) {
            $payData[] = ['id' => $key, 'name' => $item];
        }

        //        $department[] = ['id'=>'all','name'=>'全部'];
        //        $department = config('dictionaries.gjss_cyks');//DepartmentService::getDepartmentList();
        $department = Department::getDepartmentList();
        foreach ($department as $key => &$item) {
            $item['id'] = $item['dep_id'];
            $item['name'] = $item['dep_name'];
        }

        $IN_STATUS = config("dictionaries.IN_STATUS");
        $IN_STATUS_data[] = ['id' => 'all', 'name' => '全部'];
        foreach ($IN_STATUS as $key => $item) {
            $IN_STATUS_data[] = ['id' => $key, 'name' => $item];
        }

        $staffData = Staff::query()
            ->where('name', '!=', '')
            ->get(['code as key', 'name as value'])
            ->toArray();

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
                    // ['id' => 'ABC03C', 'name' => '主要诊断入院病情'],
                    ['id' => 'RYQK', 'name' => '其他诊断入院病情'],
                    // ['id' => 'OPE_LEVEL', 'name' => '手术级别'],
                    //                    ['id'=>'IS_MAIN_WAY','name'=>'重症监护室名称'],
                    ['id' => 'AEM01C', 'name' => '离院方式'],
                    ['id' => 'ABA01N', 'name' => '门（急）诊诊断'],
                    ['id' => 'ABA01C', 'name' => '门急诊的疾病编码'],
                    ['id' => 'AEL01', 'name' => '有创呼吸机使用时间', 'value' => ['0' => ['key' => '1', 'value' => '有'], '1' => ['key' => '2', 'value' => '无']]],
                    // ['id' => 'RJSS', 'name' => '是否为日间手术'],
                    // ['id' => 'LNSSQ', 'name' => '颅脑损伤昏迷时间 入院前', 'value' => ['0' => ['key' => '1', 'value' => '有'], '1' => ['key' => '2', 'value' => '无']]],
                    ['id' => 'LNSSH', 'name' => '颅脑损伤昏迷时间 入院后', 'value' => ['0' => ['key' => '1', 'value' => '有'], '1' => ['key' => '2', 'value' => '无']]],
                    ['id' => 'AAA28', 'name' => '病案号'],
                    ['id' => 'AAA01', 'name' => '姓名'],
                    ['id' => 'AAA02C', 'name' => '性别'],
                    //                    ['id'=>'AAA04','name'=>'年龄'],
                    ['id' => 'AAA29', 'name' => '住院次数'],
                    ['id' => 'AAD01C', 'name' => '转科科室', 'value' => $department], // 新增
                    ['id' => 'AAC02C', 'name' => '出院科室', 'value' => $department],
                    ['id' => 'AAB02C', 'name' => '入院科室', 'value' => $department],
                    // [
                    //     'id' => 'AAB06C',
                    //     'name' => '入院途径',
                    //     'value' =>
                    //     [
                    //         '0' => ['key' => '1', 'value' => '急诊'],
                    //         '1' => ['key' => '2', 'value' => '门诊'],
                    //         '2' => ['key' => '3', 'value' => '其他医疗机构转入'],
                    //         '3' => ['key' => '9', 'value' => '其他']
                    //     ],
                    // ],
                    // ['id' => 'ABF01N', 'name' => '病理诊断名称'],
                    // ['id' => 'ABF01C', 'name' => '病理诊断编码'],

                    ['id' => 'MO_ICD9_NAME', 'name' => '主要手术名称'],
                    ['id' => 'MO_ICD9_ID1', 'name' => '主要手术编码'],
                    [
                        'id' => 'MO_OPE_LEVEL',
                        'name' => '主要手术级别',
                        'value' =>
                        [
                            ['key' => '1', 'value' => '一级手术'],
                            ['key' => '2', 'value' => '二级手术'],
                            ['key' => '3', 'value' => '三级手术'],
                            ['key' => '4', 'value' => '四级手术']
                        ],
                    ],
                    ['id' => 'MO_SSPB', 'name' => '主要手术判别'],
                    [
                        'id' => 'MO_OPE_TYPE',
                        'name' => '主要手术类型',
                        'value' =>
                        [
                            ['key' => '择期', 'value' => '择期'],
                            ['key' => '急症', 'value' => '急症'],
                            ['key' => '限期', 'value' => '限期']
                        ],
                    ],

                    ['id' => 'SO_ICD9_NAME', 'name' => '其他手术名称'],
                    ['id' => 'SO_ICD9_ID1', 'name' => '其他手术编码'],
                    [
                        'id' => 'SO_OPE_LEVEL',
                        'name' => '其他手术级别',
                        'value' =>
                        [
                            ['key' => '1', 'value' => '一级手术'],
                            ['key' => '2', 'value' => '二级手术'],
                            ['key' => '3', 'value' => '三级手术'],
                            ['key' => '4', 'value' => '四级手术']
                        ],
                    ],
                    ['id' => 'SO_SSPB', 'name' => '其他手术判别'],
                    [
                        'id' => 'SO_OPE_TYPE',
                        'name' => '其他手术类型',
                        'value' =>
                        [
                            ['key' => '择期', 'value' => '择期'],
                            ['key' => '急症', 'value' => '急症'],
                            ['key' => '限期', 'value' => '限期']
                        ],
                    ],
                    // ['id' => 'AEE10', 'name' => '责任护士', 'value' => $staffData],
                    ['id' => 'AEE03', 'name' => '主治医师', 'value' => $staffData],
                    ['id' => 'AEE04', 'name' => '住院医师', 'value' => $staffData],

                    ['id' => 'AEE01', 'name' => '科主任', 'value' => $staffData],
                    ['id' => 'AEE02', 'name' => '主(副主)任医师', 'value' => $staffData],
                    ['id' => 'ZZYISXM', 'name' => '医疗组长', 'value' => $staffData],
                ],
                'year' => self::getYearConfig(),
                'quarter' => self::getQuarterConfig(),
                'month' => self::getMonthConfig(),
            ]
        );
    }

    public function selectStaff()
    {
        $staffData = Staff::query()
            ->where('name', '!=', '')
            ->get(['code', 'name'])
            ->toArray();

        $res = [];
        foreach ($staffData as $v) {
            $res[] = ['id' => (string)$v['code'], 'label' => (string)$v['name']];
        }

        return ToolsService::returnData(200, $res);
    }

    public function selectBmyStaff()
    {
        $staffData = Staff::query()
            ->where('is_bmy', '=', 1)
            ->get(['code', 'name'])
            ->toArray();

        $res = [];
        foreach ($staffData as $v) {
            $res[] = ['id' => (string)$v['code'], 'label' => (string)$v['name']];
        }

        return ToolsService::returnData(200, $res);
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
        $hospital_name = $request->post('hospital_name', '');
        $hospital_name = !empty($hospital_name) ? $hospital_name : config('confAdmin.hospital_name');

        //来源
        $source = $request->post('source', 0);
        // 是否导出 0-返数据 1-导出
        $isExport = $request->post("is_export", 0);
        if ($isExport == 1) {
            $exportService = new ExportService();
            return $exportService->errorDataExport($start_time, $end_time, $source, $type, $level, $hospital_name);
        }
        $AAC11C = $request->post("AAC11C");
        $AAC11C = $AAC11C ? $AAC11C : 'all';
        $level = $level ? $level : 'all';
        $query = ErrorData::query()->where('error_data.hospital_name', '=', $hospital_name);
        if ($level != 'all') {
            $query->where('er.level', $level);
        }
        $SYear = date('Y', strtotime($start_time));
        $SMonth = date('m', strtotime($start_time));
        $EYear = date('Y', strtotime($end_time));
        $EMonth = date('m', strtotime($end_time));

        if ($AAC11C != 'all') {
            $error = Error::query()
                ->where('hospital_name', '=', $hospital_name)
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
            ->leftJoin('error_rule as er', 'error_data.error_rule', '=', 'er.id')
            ->where('field', '!=', '')
            ->groupBy('error_rule')
            ->orderBy('count', 'desc')
            ->orderBy('error_data.error_rule')
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
            ->where('hospital_name', '=', $hospital_name)
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
        $AAA28 = $request->input('AAA28', '');

        // 医院名称
        $hospitalName = $request->input("hospitalName", '');
        $hospitalName = !empty($hospitalName) ? $hospitalName : config('confAdmin.hospital_name');

        //来源
        $source = $request->post('source', 0);
        $query = Error::query()
            ->where('error.source', $source)
            ->where('error.hospital_name', '=', $hospitalName);
        if ($AAC11C != 'all' && !empty($AAC11C) && $AAC11C != 'undefined') {
            //            $AAC11C = DepartmentService::getDepartmentList($AAC11C);
            $query->where('error.AAC11C', $AAC11C ?? '-1');
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

        // 住院号
        if ($AAA28) {
            $query->where('pi.AAA28', $AAA28);
        }

        $query
            ->leftJoin('patient_info as pi', 'error.ZYH', '=', 'pi.MED_REC_ID')
            ->leftJoin('patient_hospital_info as phi', 'pi.MED_REC_ID', '=', 'phi.AAA28')
            ->leftJoin('patient_doctor_info as pdi', 'phi.AAA28', '=', 'pdi.AAA28');
        $count = $query->count(DB::raw('DISTINCT pi.MED_REC_ID'));
        $list = $query
            ->offset($offset)
            ->limit($limit)
            ->groupBy('pi.MED_REC_ID')
            ->get(['pi.AAA28', 'MED_REC_ID', 'pi.AAA01', 'pi.AAC11N', 'pi.AAC01', 'error_field', 'error.level', 'desc', 'phi.AAC03'])->toArray();
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
        $AAC11C = $request->post("AAC11C", 'all');
        $AAC11N = $request->post("AAC11N", '');
        $AAA28 = $request->post("AAA28", '');
        $ruleId = $request->post("error_rule", '');
        $ruleType = $request->post("rule_type", '');
        $level = $request->post("level", 'all');
        $error_type = $request->post("error_type", 'all');
        $doctor_name = $request->input("doctor_name", '');
        $hospital_name = $request->input("hospital_name", '');
        $department = $request->input("department", '');
        $coder_name = $request->input("coder_name", '');
        $error_rule = $request->input("error_rule", '');
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

        //        $field = ['patient_info.AAA28', 'MED_REC_ID', 'AAA01', 'AAC11N', 'patient_info.AAC01', 'AAC03', 'AAA01 as AEE04', 'cq.rule_id', 'patient_info.score'];
        $field = ['patient_info.AAA28', 'MED_REC_ID', 'AAA01', 'AAC11N', 'patient_info.AAC01', 'AAC03', 'AAA01 as AEE04', 'case_quality.rule_id', 'patient_info.score'];
        $field[] = "pa.KZRXM";
        $field[] = "pa.ZHFZRYSXM";
        $field[] = "pa.ZZYSXM";
        $field[] = "pa.ZYYSXM";
        $field[] = "pa.ZZYISXM";

        //        $query = PatientInfo::query()
        $query = CaseQuality::query()
            ->leftJoin('patient_info', 'patient_info.MED_REC_ID', '=', 'case_quality.JZHM')
            ->leftJoin('patient_hospital_info as phi', 'patient_info.MED_REC_ID', '=', 'phi.AAA28')
            ->leftJoin('patient_doctor_info as pdi', 'phi.AAA28', '=', 'pdi.AAA28');
        //        $query = $query->leftJoin('case_quality as cq', 'patient_info.MED_REC_ID', '=', 'cq.JZHM');
        //        $query = $query->leftJoin('case_rule as cr', 'cr.id', '=', 'cq.rule_id');
        $query = $query->leftJoin('case_rule as cr', 'cr.id', '=', 'case_quality.rule_id');
        $query = $query->leftJoin('patient_add as pa', 'patient_info.MED_REC_ID', '=', 'pa.AAA28');

        //院区
        $YQ_CODE = $request->post('YQ_CODE', '');
        if (!empty($YQ_CODE)) $query->where('patient_info.YQ_CODE', $YQ_CODE);
        //科室
        $KS_CODE = $request->post('KS_CODE', '');
        if (!empty($KS_CODE)) $query->where('patient_info.AAC02C', $KS_CODE);
        //病区
        $BQ_CODE = $request->post('BQ_CODE', '');
        if (!empty($BQ_CODE)) $query->where('patient_info.BQ_CODE', $BQ_CODE);

        if (!empty($doctor_name)) {
            if (is_array($doctor_name) === false) {
                $doctor_name = [$doctor_name];
            }

            $doctor_name = implode('","', $doctor_name);
            $query = $query->whereRaw('(pa.KZRXM in ("' . $doctor_name . '") or pa.ZHFZRYSXM in ("' . $doctor_name . '") or pa.ZZYSXM in ("' . $doctor_name . '") or pa.ZYYSXM in ("' . $doctor_name . '") or pa.ZZYISXM in ("' . $doctor_name . '"))');
        }
        if ($ruleType) {
            $query = $query->where('cr.type', '=', $ruleType);
        }
        if ($ruleId) {
            //            $query = $query->where('cq.rule_id', '=', $ruleId);
            $query = $query->where('case_quality.rule_id', '=', $ruleId);
        }
        if (!empty($AAA28)) {
            $query = $query->where('patient_info.AAA28', '=', $AAA28);
        }
        if ($start_time && $end_time) {
            $whereCase = 'patient_info.AAC01 > "' . $start_time . '" and patient_info.AAC01 < "' . $end_time . '"';
            $query = $query->whereRaw($whereCase);
        }
        if (!empty($AAC11N)) {
            $query = $query->where('patient_info.AAC11N', '=', $AAC11N);
        }
        $count = $query->count();

        $list = $query
            ->offset($offset)
            ->limit($limit)
            ->orderBy('patient_info.AAC01', 'desc')
            ->get($field)->toArray();
        $ruleIds = array_column($list, 'rule_id');
        if ($isExport == 1) {
            return $this->errorDataListExportData($list);
        }
        $rule = CaseRule::query()->whereIn('id', $ruleIds)->get()->toArray();
        $rule = array_column($rule, null, 'id');

        foreach ($list as &$l) {
            $l['rule_notice'] = !empty($rule[$l['rule_id']]) ? $rule[$l['rule_id']]['notice'] : '';
        }
        return ToolsService::returnData(200, ['list' => $list, 'count' => $count]);

        //来源
        $source = $request->post('source', 0);

        //            $model = Db::table('department_data')->whereBetween('created_at',[$start_time,$end_time]);
        //            $result = $model
        //                ->select('department_id', 'average_score', 'total_medical', 'total_error', 'average_error', 'max_score', 'min_score', 'total_error_medical')
        //                ->orderBy('average_score', "desc")
        //                ->get()
        //                ->groupBy('department_id');
        //           var_dump(json_decode(json_encode($result), true));exit;
        //
        //
        //


        $bestBl = EMR_BL_BL01::query()
            ->selectRaw("max(BLBH) as BLBH")
            ->where('EMR_BL_BL01.is_defect', 1)
            ->groupBy('JZHM')
            ->get()->toArray();
        $bestBlbh = array_column($bestBl, 'BLBH');

        $query = EMR_BL_BL01::query()
            ->leftJoin('patient_info as pi', 'EMR_BL_BL01.JZHM', '=', 'pi.MED_REC_ID')
            ->leftJoin('patient_hospital_info as phi', 'pi.MED_REC_ID', '=', 'phi.AAA28')
            ->leftJoin('patient_doctor_info as pdi', 'phi.AAA28', '=', 'pdi.AAA28')
            ->whereIn('EMR_BL_BL01.BLBH', $bestBlbh);
        $count = $query->count();
        $list = $query
            ->offset($offset)
            ->limit($limit)
            ->groupBy('pi.MED_REC_ID')
            ->get(['pi.AAA28', 'MED_REC_ID', 'AAA01', 'AAC11N', 'AAC01', 'AAC03', 'AEE04'])->toArray();

        if ($AAC11C != 'all') {
            $AAC11C = DepartmentService::getDepartmentList($AAC11C);
            $query->where('error.AAC11C', $AAC11C['code']);
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
            ->join('patient_info as pi', 'error.AAA28', '=', 'pi.MED_REC_ID')
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
     * 导出
     * @param $data
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
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
        $id = (string)$request->post('id');
        if (empty($id)) {
            return ToolsService::returnData(4001, [], '参数错误');
        }

        $info = EMR_BL_BL01::query()
            ->leftJoin("EMR_BL_BLXG", 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
            ->where('EMR_BL_BL01.JZHM', '=', $id)
            ->orderBy("EMR_BL_BL01.ZXSJ", 'ASC') //兰陵按照业务时间排序，其他都按照执行时间排序
            ->get(['EMR_BL_BL01.first_blsy_time', 'EMR_BL_BL01.WCSJ', 'EMR_BL_BL01.JZHM', 'EMR_BL_BL01.BLBH', 'EMR_BL_BL01.BLLB', 'EMR_BL_BL01.BLZT', 'EMR_BL_BL01.BLMC', 'EMR_BL_BL01.ZXSJ', 'EMR_BL_BLXG.HJNR'])->toArray();
        if (!empty($info)) {
            foreach ($info as $k => $v) {
                if ($v['BLZT'] == 9) {
                    unset($info[$k]);
                }
            }
        }
        $targetService = new TargetService();

        $bllb = array_unique(array_column($info, 'BLLB'));
        $config = [];
        if ($info) {
            $bllbInfo = array_column($info, null, 'BLLB');
            // 79儿童营养风险评估
            // 104 外周血管活性药物
            $config = [
                ['name' => '24小时内记录类', 'bllb' => 18],
                ['name' => '死亡记录', 'bllb' => 288],
                ['name' => '出院记录', 'bllb' => 1, 'blbh' => data_get($bllbInfo, "1.BLBH", ""), 'first_blsy_time' => data_get($bllbInfo, "1.first_blsy_time", ""), 'WCSJ' => data_get($bllbInfo, "1.WCSJ", "")],
                ['name' => '入院记录', 'bllb' => 292, 'blbh' => data_get($bllbInfo, "292.BLBH", ""), 'first_blsy_time' => data_get($bllbInfo, "292.first_blsy_time", ""), 'WCSJ' => data_get($bllbInfo, "292.WCSJ", "")],
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
                ['name' => '急诊留观病历', 'bllb' => 2004], // no
                ['name' => '化疗记录类', 'bllb' => 83], // no
                ['name' => '检查申请类', 'bllb' => 66], // no
                ['name' => '医患沟通类', 'bllb' => 34],
                ['name' => '医疗常用表格', 'bllb' => 87],
            ];

            foreach ($config as $k => $v) {
                if (!in_array($v['bllb'], $bllb)) {
                    unset($config[$k]);
                }
                if (in_array($v['bllb'], [329, 43, 2000185, 34])) {
                    foreach ($info as $i) {
                        if ($i["BLLB"] == $v['bllb']) {
                            $i["blbh"] = $i["BLBH"];
                            $i["name"] = $i["BLMC"];
                            $config[$k]['list'][] = $i;
                        }
                    }
                }
                if (in_array($v['bllb'], [294, 303])) {
                    // 获取病程记录、手术二级菜单
                    $bllbList = $targetService->getSurgeryMenu($info, $v['bllb']);
                    if (!empty($bllbList)) {
                        $config[$k]['list'] = $bllbList;
                    }
                }
            }

            if (!empty($config[4]) && !empty($config[7])) {
                $config[4]["list"] = array_merge($config[4]["list"], $config[7]["list"]);
                unset($config[7]);
            }
        }

        // 获取报告单二级菜单
        $pacsMenuList = $targetService->getPacsMenu($info, $id);
        if (!empty($pacsMenuList)) {
            $config[] = ['name' => '报告单', 'bllb' => 2000002, 'list' => $pacsMenuList];
        }
        $yzb = Yzb::query()->where('ZYH', '=', $id)->count();
        if ($yzb) {
            $config[] = ['name' => '医嘱', 'bllb' => 49];
        }

        //获取会诊记录单
        $hzxx = YS_ZY_HZSQ::query()->where('JZHM', '=', $id)->orderBy('SQSJ', 'asc')->get()->toArray();
        $hzxxList = [];
        if (!empty($hzxx)) {

            foreach ($hzxx as $v) {
                $name = $v['SQSJ'] . ' 会诊记录单';
                if ($v['OA'] == 1) {
                    $name = '(OA)'.$name;
                }
                $hzxxList[] = [
                    'jzhm' => $v['JZHM'],
                    'blbh' => $v['SQXH'],
                    'name' => $name,
                ];
            }
            $config[] = ['name' => '会诊记录单','blbh' => 'hzxx', 'list' => $hzxxList];
        }


        return ToolsService::returnData(200, $config);
    }

    public function getHzxx(Request $request)
    {
        $jzhm = $request->post('jzhm');
        $blbh = $request->post('blbh');
        //查询科室表和员工表
        
        $hzxx = YS_ZY_HZSQ::query()->leftJoin('YS_ZY_HZYJ', 'YS_ZY_HZYJ.SQXH', '=', 'YS_ZY_HZSQ.SQXH')->where('JZHM', '=', $jzhm)->where('YS_ZY_HZSQ.SQXH', '=', $blbh)->orderBy('YS_ZY_HZSQ.SQSJ', 'asc')->get()->toArray();
        //查询基本信息
        $brry = ZY_BRRY::query()->where('ZYH', (string)$jzhm)->get()->toArray();
        $hzxx[0]['SQKS'] = Department::query()->where('dep_id', $hzxx[0]['SQKS'])->value('dep_name');
        $hzxx[0]['KSDM'] = Department::query()->where('dep_id', $hzxx[0]['KSDM'])->value('dep_name');
        $hzxx[0]['SQYS'] = Staff::query()->where('code', $hzxx[0]['SQYS'])->value('name');
        $hzxx[0]['TJYS'] = Staff::query()->where('code', $hzxx[0]['TJYS'])->value('name');
        $hzxx[0]['TXRY'] = Staff::query()->where('code', $hzxx[0]['TXRY'])->value('name');
        $hzxx[0]['SSYS'] = Staff::query()->where('code', $hzxx[0]['SSYS'])->value('name');
        $hzxx[0]['SXYS'] = Staff::query()->where('code', $hzxx[0]['SXYS'])->value('name');
        $hzxx[0]['QMYS'] = Staff::query()->where('code', $hzxx[0]['QMYS'])->value('name');
        $hzxx['基本信息'] = [];
        $hzxx['基本信息'][] = [
            'title' => '姓名',
            'value' => $brry[0]['BRXM'] ?? '',
        ];

        $hzxx['基本信息'][] = [
            'title' => '床号',
            'value' => $brry[0]['CH'] ?? '',
        ];

        $brks = $brry[0]['BRKS'] ?? '';
        if (!empty($brks)) {
            $brks = Department::query()->where('dep_id', $brks)->value('dep_name');
        }
        $hzxx['基本信息'][] = [
            'title' => '科室',
            'value' => $brks,
        ];

        $hzxx['基本信息'][] = [
            'title' => '病案号',
            'value' => $brry[0]['AAA28'] ?? '',
        ];

        return ToolsService::returnData(200, $hzxx);
    }

    public function getAllCase(Request $request)
    {
        $bllb = $request->post('bllb', 1);
        $MED_REC_ID = $request->post('MED_REC_ID');

        // 如果 bllb=232，查询会诊信息
        if ($bllb == 232) {
            $hzxxData = YS_ZY_HZSQ::query()
                ->leftJoin('YS_ZY_HZYJ', 'YS_ZY_HZYJ.SQXH', '=', 'YS_ZY_HZSQ.SQXH')
                ->where('YS_ZY_HZSQ.JZHM', $MED_REC_ID)
                ->orderBy('YS_ZY_HZSQ.SQSJ', 'asc')
                ->get([
                    'YS_ZY_HZSQ.SQXH as BLBH',
                    'YS_ZY_HZSQ.SQSJ as CJSJ',
                    'YS_ZY_HZSQ.SQSJ as ZXSJ',
                    'YS_ZY_HZSQ.SQSJ as WCSJ',
                    'SQKS',
                    'KSDM',
                    'SQYS',
                    'TJYS',
                    'TXRY',
                    'SSYS',
                    'SXYS',
                    'QMYS',
                    'HZMD',
                    'HZLX',
                    'HZYJ'
                ])->toArray();

            $data = [];
            if (!empty($hzxxData)) {
                foreach ($hzxxData as $hzInfo) {
                    // 查询科室和医生名称
                    $sqksName = Department::query()->where('dep_id', $hzInfo['SQKS'])->value('dep_name');
                    $ksdmName = Department::query()->where('dep_id', $hzInfo['KSDM'])->value('dep_name');
                    $sqysName = Staff::query()->where('code', $hzInfo['SQYS'])->value('name');
                    $tjysName = Staff::query()->where('code', $hzInfo['TJYS'])->value('name');
                    $txryName = Staff::query()->where('code', $hzInfo['TXRY'])->value('name');
                    $ssysName = Staff::query()->where('code', $hzInfo['SSYS'])->value('name');
                    $sxysName = Staff::query()->where('code', $hzInfo['SXYS'])->value('name');
                    $qmysName = Staff::query()->where('code', $hzInfo['QMYS'])->value('name');

                    // 构建会诊内容
                    $hjnr = "会诊记录单\n\n";
                    $hjnr .= "申请科室：" . ($sqksName ?: '') . "\n";
                    $hjnr .= "会诊科室：" . ($ksdmName ?: '') . "\n";
                    $hjnr .= "申请医生：" . ($sqysName ?: '') . "\n";
                    $hjnr .= "会诊类型：" . ($hzInfo['HZLX'] ?: '') . "\n";
                    $hjnr .= "会诊目的：" . ($hzInfo['HZMD'] ?: '') . "\n\n";
                    $hjnr .= "会诊意见：" . ($hzInfo['HZYJ'] ?: '') . "\n";

                    $data[] = [
                        'BLBH' => $hzInfo['BLBH'],
                        'MBLB' => 232,
                        'CJSJ' => $hzInfo['CJSJ'],
                        'ZXSJ' => $hzInfo['ZXSJ'],
                        'WCSJ' => $hzInfo['WCSJ'] ?? '',
                        'SXYS' => $sqysName ?: '',
                        'HJNR' => $hjnr,
                        'title' => '会诊记录单',
                        'doctor_name' => implode('、', array_filter([
                            $sqysName ? $sqysName . '（申请医生）' : '',
                            $tjysName ? $tjysName . '（提交医生）' : '',
                            $qmysName ? $qmysName . '（签名医生）' : ''
                        ]))
                    ];
                }
            }

            return ToolsService::returnData(200, $data);
        }

        // 原有的 bl01 查询逻辑
        $bl01Data = EMR_BL_BL01::query()
            ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BLXG.BLBH', '=', 'EMR_BL_BL01.BLBH')
            ->where('EMR_BL_BL01.JZHM', $MED_REC_ID)
            ->where('EMR_BL_BL01.BLLB', $bllb)
            ->where('EMR_BL_BL01.BLZT', '<>', 9)
            ->get(['EMR_BL_BL01.BLBH', 'MBLB', 'CJSJ', 'ZXSJ', 'WCSJ', 'SXYS', 'HJNR', 'first_blsy_time', 'HTML_PRINT'])->toArray();

        $data = [];
        if (!empty($bl01Data)) {
            foreach ($bl01Data as $bl01Info) {
                $data[] = [
                    'BLBH' => $bl01Info['BLBH'],
                    'MBLB' => $bl01Info['MBLB'],
                    'CJSJ' => $bl01Info['CJSJ'],
                    'ZXSJ' => $bl01Info['first_blsy_time'],
                    'WCSJ' => $bl01Info['WCSJ'] ?? '',
                    'SXYS' => $bl01Info['SXYS'],
                    'HJNR' => $bl01Info['HTML_PRINT'] ?: $bl01Info['HJNR'],
                ];
            }
        }

        $brxm = PatientInfo::query()->where('MED_REC_ID', '=', $MED_REC_ID)->value('AAA01');

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
                    $doctorName = [];
                    $staff = Staff::query()->whereIn('code', $SYYS)->get(['code', 'name', 'ygjb_text'])->toArray();
                    if ($staff) {
                        foreach ($staff as $val) {
                            $doctorName[$val['code']] = $val['name'] . '（' . $val['ygjb_text'] . '）';
                        }
                        $doctorList = implode('、', $doctorName);
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
        return MysqlCaseSearchService::normalSearch($request);

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
        return MysqlCaseSearchService::searchData($request);

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

        // 手术名称（主+其他）
        $ICD9_NAME = $request->post("ICD9_NAME", "");
        if ($ICD9_NAME) {
            $params['body']['query']['bool']['must'][] = [
                'query' => [
                    'bool' => [
                        'should' => [
                            [
                                'nested' => [
                                    "path" => "secondary_operation",
                                    "query" => [
                                        'bool' => [
                                            'must' => [
                                                ["match_phrase" => ["secondary_operation.ICD9_NAME" => $ICD9_NAME]]
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            [
                                'nested' => [
                                    "path" => "main_operation",
                                    "query" => [
                                        'bool' => [
                                            'must' => [
                                                ["match_phrase" => ["main_operation.ICD9_NAME" => $ICD9_NAME]]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]

            ];
        }

        //手术编码（主+其他）
        $ICD9_ID1 = $request->post("ICD9_ID1", "");
        if ($ICD9_ID1) {
            $params['body']['query']['bool']['must'][] = [
                'query' => [
                    'bool' => [
                        'should' => [
                            [
                                'nested' => [
                                    "path" => "secondary_operation",
                                    "query" => [
                                        'bool' => [
                                            'must' => [
                                                ["match_phrase" => ["secondary_operation.ICD9_ID1" => $ICD9_ID1]]
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            [
                                'nested' => [
                                    "path" => "main_operation",
                                    "query" => [
                                        'bool' => [
                                            'must' => [
                                                ["match_phrase" => ["main_operation.ICD9_ID1" => $ICD9_ID1]]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]

            ];
        }

        // 其他手术名称
        $secondary_operation_ICD9_NAME = $request->post("secondary_operation_ICD9_NAME", "");
        if ($secondary_operation_ICD9_NAME) {

            $params['body']['query']['bool']['must'][] = [
                'nested' => [
                    "path" => "secondary_operation",
                    "query" => [
                        'bool' => [
                            'must' => [
                                ["match_phrase" => ["secondary_operation.ICD9_NAME" => $secondary_operation_ICD9_NAME]]
                            ]
                        ]
                    ]
                ]
            ];
        }
        // 其他手术编码
        $secondary_operation_ICD9_ID1 = $request->post("secondary_operation_ICD9_ID1", "");
        if ($secondary_operation_ICD9_ID1) {

            $params['body']['query']['bool']['must'][] = [
                'nested' => [
                    "path" => "secondary_operation",
                    "query" => [
                        'bool' => [
                            'must' => [
                                ["term" => ["secondary_operation.ICD9_ID1" => $secondary_operation_ICD9_ID1]]
                            ]
                        ]
                    ]
                ]
            ];
        }
        // 主要手术名称
        $main_operation_ICD9_NAME = $request->post("main_operation_ICD9_NAME", "");
        if ($main_operation_ICD9_NAME) {

            $params['body']['query']['bool']['must'][] = [
                'nested' => [
                    "path" => "main_operation",
                    "query" => [
                        'bool' => [
                            'must' => [
                                ["match_phrase" => ["main_operation.ICD9_NAME" => $main_operation_ICD9_NAME]]
                            ]
                        ]
                    ]
                ]
            ];
        }
        // 主要手术编码
        $main_operation_ICD9_ID1 = $request->post("main_operation_ICD9_ID1", "");
        if ($main_operation_ICD9_ID1) {

            $params['body']['query']['bool']['must'][] = [
                'nested' => [
                    "path" => "main_operation",
                    "query" => [
                        'bool' => [
                            'must' => [
                                ["term" => ["main_operation.ICD9_ID1" => $main_operation_ICD9_ID1]]
                            ]
                        ]
                    ]
                ]
            ];
        }
        // 其他诊断名称
        $other_diagnosis_ICD10_NAME = $request->post("other_diagnosis_ICD10_NAME", "");
        if ($other_diagnosis_ICD10_NAME) {

            $params['body']['query']['bool']['must'][] = [
                'nested' => [
                    "path" => "other_diagnosis",
                    "query" => [
                        'bool' => [
                            'must' => [
                                ["match_phrase" => ["other_diagnosis.ICD10_NAME" => $other_diagnosis_ICD10_NAME]]
                            ]
                        ]
                    ]
                ]
            ];
        }
        // 其他诊断编码
        $other_diagnosis_ICD10_ID1 = $request->post("other_diagnosis_ICD10_ID1", "");
        if ($other_diagnosis_ICD10_ID1) {

            $params['body']['query']['bool']['must'][] = [
                'nested' => [
                    "path" => "other_diagnosis",
                    "query" => [
                        'bool' => [
                            'must' => [
                                ["term" => ["other_diagnosis.ICD10_ID1" => $other_diagnosis_ICD10_ID1]]
                            ]
                        ]
                    ]
                ]
            ];
        }
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
}
