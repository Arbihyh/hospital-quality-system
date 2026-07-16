<?php


namespace App\Services;

use App\Model\Error;
use App\Model\MainDiagnosis;
use App\Model\MainOperation;
use App\Model\PatientScore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Model\PatientInfo;
use App\Model\PatientOtherInfo;
use App\Model\PatientMedicalInfo;
use App\Model\PatientHospitalInfo;
use App\Model\PatientDoctorInfo;
use App\Model\PatientAddressInfo;
use App\Model\PatientWorkInfo;
use App\Model\PatientContactsInfo;
use App\Model\Icu;
use App\Model\OtherDiagnosis;
use App\Model\PatientCostInfo;
use App\Model\PatientAdd;
use App\Model\SecondaryOperation;
use App\Services\ToolsService;
use Illuminate\Support\Facades\Log;
use function GuzzleHttp\json_encode;
use App\Model\BaZdlr;
use App\Model\BaBrsy;

class MedicalRecordService
{
    /**
     * 获取患者数据
     * @param $id
     * @return array|mixed
     */
    public static function getData($id)
    {
        //患者信息
        $field = ['ABC01N', 'AAA28', 'ADA0101', 'MED_REC_ID', 'ADA01', 'ABG01N', 'ABG01C', 'AEM01C', 'AAC04', 'AAB06C', 'AAA26C', 'AAA01', 'AAA02C', 'AAA03', 'AAA29', 'AAA04', 'AAA05C', 'AAA40', 'AEN01', 'AAA42', 'AAA26C', 'AAA06C', 'AAA07', 'AAC01', 'AAA08C', 'ATTEND_GRP_CODE', 'ATTEND_GRP_NAME', 'source'];
        $patient_info = PatientInfo::query()->where('MED_REC_ID', $id)->first($field);
        if ($patient_info) {
            $patient_info = $patient_info->toArray();
        } else {
            return false;
        }
        //患者信息
        $other_field = '*';
        $patient_other_info = PatientOtherInfo::query()->where('AAA28', $patient_info['MED_REC_ID'])->first($other_field);
        if ($patient_other_info) {
            $patient_other_info = $patient_other_info->toArray();
            unset($patient_other_info['id']);
        } else {
            $patient_other_info = [];
        }
        //患者地址相关信息
        $address_field = ['AAA09', 'AAA10', 'AAA11', 'AAA43', 'AAA44', 'AAA45', 'AAA46', 'AAA47', 'AAA12', 'AAA14C', 'AAA15', 'AAA48', 'AAA49', 'AAA50', 'AAA51', 'AAA17C'];
        $patient_address_info = PatientAddressInfo::query()->where('AAA28', $patient_info['MED_REC_ID'])->first($address_field);
        if ($patient_address_info) {
            $patient_address_info = $patient_address_info->toArray();
        } else {
            $patient_address_info = [];
        }
        //患者工作信息
        $work_field = ['AAA18C', 'AAA19', 'AAA20', 'AAA21C'];
        $patient_work_info = PatientWorkInfo::query()->where('AAA28', $patient_info['MED_REC_ID'])->first($work_field);
        if ($patient_work_info) {
            $patient_work_info = $patient_work_info->toArray();
        } else {
            $patient_work_info = [];
        }
        //患者联系人信息
        $patient_field = ['AAA22', 'AAA24', 'AAA25', 'AAA23C'];
        $patient_contacts_info = PatientContactsInfo::query()->where('AAA28', $patient_info['MED_REC_ID'])->first($patient_field);
        if ($patient_contacts_info) {
            $patient_contacts_info = $patient_contacts_info->toArray();
        } else {
            $patient_contacts_info = [];
        }
        //患者住院信息
        $hospital_info_field = ['ABC01C', 'AAA27', 'AAB01', 'AAB02C', 'AAB03', 'AAC02C', 'AAC03', 'AAD01C', 'AEI01C', 'AEM03C', 'AEM04', 'AAC11C', 'AAB11C', 'AAB11N', 'ABC01C'];
        $patient_hospital_info = PatientHospitalInfo::query()->where('AAA28', $patient_info['MED_REC_ID'])->first($hospital_info_field);
        if ($patient_hospital_info) {
            $patient_hospital_info = $patient_hospital_info->toArray();
        } else {
            $patient_hospital_info = [];
        }
        //患者医疗信息
        $medical_info_field = ['ABC03C', 'ABF01C', 'ABF04', 'ABF01N', 'AEL01', 'ABA01N', 'ABA01C', 'AEB02C', 'AEB01', 'AEG01C', 'AEG02C', 'AED01C', 'AEJ01', 'AEJ02', 'AEJ03', 'AEJ04', 'AEJ05', 'AEJ06', 'ABF02C'];
        $patient_medical_info = PatientMedicalInfo::query()->where('AAA28', $patient_info['MED_REC_ID'])->first($medical_info_field);
        if ($patient_medical_info) {
            $patient_medical_info = $patient_medical_info->toArray();
            $patient_medical_info['AEJ01'] = $patient_medical_info['AEJ01'] ?: '-';
            $patient_medical_info['AEJ02'] = $patient_medical_info['AEJ02'] ?: '-';
            $patient_medical_info['AEJ03'] = $patient_medical_info['AEJ03'] ?: '-';
            $patient_medical_info['AEJ04'] = $patient_medical_info['AEJ04'] ?: '-';
            $patient_medical_info['AEJ05'] = $patient_medical_info['AEJ05'] ?: '-';
            $patient_medical_info['AEJ06'] = $patient_medical_info['AEJ06'] ?: '-';
        } else {
            $patient_medical_info = [];
        }
        //病案得分
        $patient_score_field = ['score', 'level'];
        $patient_score_info = PatientScore::query()->where('ZYH', $patient_info['MED_REC_ID'])->first($patient_score_field);
        if ($patient_score_info) {
            $patient_score_info = $patient_score_info->toArray();
        } else {
            $patient_score_info = [];
        }
        //者主要诊断信息
        $diagnosis_field = ['ICD10_NAME', 'ICD10_ID1', 'id', 'DIA_ORDER', 'DIA_ORDER', 'RYQK'];
        $main_diagnosis = MainDiagnosis::query()->where('AAA28', $patient_info['MED_REC_ID'])->orderBy('DIA_ORDER', 'asc')->get($diagnosis_field);
        if ($main_diagnosis) {
            $main_diagnosis = $main_diagnosis->toArray();
            foreach ($main_diagnosis as $k => $v) {
                $main_diagnosis[$k]['class'] = 'main';
                $patient_medical_info['ABC03C'] = $v['RYQK'];
                $patient_hospital_info['ABC01C'] = $v['ICD10_ID1'];
            }
        } else {
            $main_diagnosis = [];
        }
        //患者其他诊断信息
        $other_diagnosis_field = ['ICD10_NAME', 'ICD10_ID1', 'id', 'DIA_ORDER', 'DIA_ORDER', 'RYQK'];
        $other_diagnosis = OtherDiagnosis::query()->where('AAA28', $patient_info['MED_REC_ID'])->orderBy('DIA_ORDER', 'asc')->get($other_diagnosis_field);
        if ($other_diagnosis) {
            $other_diagnosis = $other_diagnosis->toArray();
            foreach ($other_diagnosis as $k => $v) {
                $other_diagnosis[$k]['class'] = 'other';
                $patient_hospital_info['ABC01C'] = $v['ICD10_ID1'];
            }
        } else {
            $other_diagnosis = [];
        }
        $diagnosis = array_merge($main_diagnosis, $other_diagnosis);
        //患者医生信息
        $doctor_info_field = ['QUALITY_CONTROL', 'AEE01', 'AEE01_CODE', 'AEE02', 'AEE02_CODE', 'AEE03', 'AEE03_CODE', 'AEE04', 'AEE04_CODE', 'AEE10', 'AEE05', 'AEE07', 'AEE08', 'AED02', 'AED03', 'AED04'];
        $patient_doctor_info = PatientDoctorInfo::query()->where('AAA28', $patient_info['MED_REC_ID'])->first($doctor_info_field);
        if ($patient_doctor_info) {
            $patient_doctor_info = $patient_doctor_info->toArray();
        } else {
            $patient_doctor_info = [];
        }
        //患者其他补充信息
        $other_field = ['SFZJLX', 'TYSHXYDM', 'JKKH', 'BFRY', 'ZKKB', 'SJHL', 'EJHL', 'YJHL', 'TJHL', 'ZRHSBM', 'BMY', 'ZKRQ', 'SSLX1', 'SSLX2', 'SSLX3', 'SSLX4', 'SSLX5', 'SSLX6', 'SSLX7', 'SSLX8', 'SSLX9', 'SSLX10', 'SSLX11', 'SSLX12', 'SSLX13', 'SSLX14', 'SSLX15', 'LCLJ', 'WCQK', 'SSPB'];
        $other = PatientAdd::query()->where(['AAA28' => $patient_info['MED_REC_ID']])->first($other_field);
        if ($other) {
            $other = $other->toArray();
            $other['ZKRQ'] = date("Y-m-d", strtotime($other['ZKRQ']));//质控日期：年月日
            $other['SSPB'] = collect(config('dictionaries.SSPB'))->get($other['SSPB'], '');
        } else {
            $other = [];
        }
        $ba_brsy = BaBrsy::query()->where(['AAA28' => $patient_info['MED_REC_ID']])->first();
        if ($ba_brsy) {
            $ba_brsy = $ba_brsy->toArray();
            $patient_hospital_info['AAC03'] = $ba_brsy['CYBQ'];//出院病房
        }
        $SSPB = config('dictionaries.SSPB');
        //患者主要手术信息
        $main_operation_field = ['HOCUS_MAN_CODE', 'OPE_ORDER', 'RJSS', 'SSPB', 'id', 'ICD9_ID1', 'OPE_DATE', 'OPE_LEVEL', 'ICD9_NAME', 'OPE_MAN_NAME', 'FRIST_ASSISTANT_NAME', 'SECOND_ASSISTANT_NAME', 'INCISION_GRADE_ID', 'HOCUS_WAY_ID', 'HOCUS_MAN_NAME'];
        $main_operation = MainOperation::query()->where('AAA28', $patient_info['MED_REC_ID'])->orderBy('OPE_ORDER', 'asc')->get($main_operation_field);

        if ($main_operation) {
            $main_operation = $main_operation->toArray();
            foreach ($main_operation as $k => $v) {
                $main_operation[$k]['class'] = 'main';
                $main_operation[$k]['SSPB'] = $SSPB[$v['SSPB']] ?? '无';
                if (isset($other['SSLX' . $v['OPE_ORDER']])) {
                    $main_operation[$k]['SSLX'] = $other['SSLX' . $v['OPE_ORDER']];
                } else {
                    $main_operation[$k]['SSLX'] = '';
                }

                if (strpos($v['OPE_DATE'], 'T')) {
                    $main_operation[$k]['OPE_DATE'] = str_replace('T', ' ', $v['OPE_DATE']);
                }
            }
        } else {
            $main_operation = [];
        }
        //患者次要手术信息
        $secondary_operation_field = ['HOCUS_MAN_CODE', 'OPE_ORDER', 'RJSS', 'SSPB', 'id', 'ICD9_ID1', 'OPE_DATE', 'OPE_LEVEL', 'ICD9_NAME', 'OPE_MAN_NAME', 'FRIST_ASSISTANT_NAME', 'SECOND_ASSISTANT_NAME', 'INCISION_GRADE_ID', 'HOCUS_WAY_ID', 'HOCUS_MAN_NAME'];
        $secondary_operation = SecondaryOperation::query()->where('AAA28', $patient_info['MED_REC_ID'])->orderBy('OPE_ORDER', 'asc')->get($secondary_operation_field);
        if ($secondary_operation) {
            $secondary_operation = $secondary_operation->toArray();
            foreach ($secondary_operation as $k => $v) {
                $secondary_operation[$k]['class'] = 'other';
                $secondary_operation[$k]['SSPB'] = $SSPB[$v['SSPB']] ?? '无';
                if (isset($other['SSLX' . $v['OPE_ORDER']])) {
                    $secondary_operation[$k]['SSLX'] = $other['SSLX' . $v['OPE_ORDER']];
                } else {
                    $secondary_operation[$k]['SSLX'] = '';
                }
                if (strpos($v['OPE_DATE'], 'T')) {
                    $secondary_operation[$k]['OPE_DATE'] = str_replace('T', ' ', $v['OPE_DATE']);
                }
            }
        } else {
            $secondary_operation = [];
        }
        $operation = array_merge($main_operation, $secondary_operation);
        //患者费用信息
        $patient_cost_info = PatientCostInfo::query()->where('AAA28', $patient_info['MED_REC_ID'])->first();
        if ($patient_cost_info) {
            $patient_cost_info = $patient_cost_info->toArray();
        } else {
            $patient_cost_info = [];
        }
        //患者重症信息
        $icu_field = ['OUT_TIME', 'IN_TIME', 'IS_MAIN_WAY'];
        $icu = Icu::query()->where('AAA28', $patient_info['MED_REC_ID'])->first($icu_field);
        if ($icu) {
            $icu = $icu->toArray();
        } else {
            $icu = [];
        }

        //error评分
        $error = Error::query()->where(['ZYH' => $patient_info['MED_REC_ID']])->where('status', 0)->select('down', 'desc', 'error_field', 'error_name', 'category')->orderBy('category')->get()->toArray();

        //合并查询结果
        $datas = array_merge($patient_info, $patient_score_info,$patient_address_info, $patient_work_info, $patient_contacts_info, $patient_hospital_info, $patient_medical_info, $patient_doctor_info, $patient_cost_info, $icu, $patient_other_info, $other);
        $datas['error'] = $error;
        $datas['operation'] = $operation;
        $datas['diagnosis'] = $diagnosis;//诊断
        $datas['AAA28'] = $patient_info['AAA28'];
        $datas['ADA0101'] = $patient_info['ADA0101'];

        return $datas;
    }

    //质控时候用
    public static function getQualityData($id)
    {
        //患者信息
        $field = ['ABC01N', 'AAA28', 'ADA0101', 'MED_REC_ID', 'ADA01', 'ABG01N', 'ABG01C', 'AEM01C', 'AAC04', 'AAB06C', 'AAA26C', 'AAA01', 'AAA02C', 'AAA03', 'AAA29', 'AAA04', 'AAA05C', 'AAA40', 'AEN01', 'AAA42', 'AAA26C', 'AAA06C', 'AAA07', 'AAC01', 'AAA08C', 'ATTEND_GRP_CODE', 'ATTEND_GRP_NAME', 'score', 'source', 'level'];
        $patient_info = PatientInfo::query()->where('MED_REC_ID', $id)->first($field);
        if ($patient_info) {
            $patient_info = $patient_info->toArray();
        } else {
            return false;
        }
        //患者信息
        $other_field = '*';
        $patient_other_info = PatientOtherInfo::query()->where('AAA28', $patient_info['MED_REC_ID'])->first($other_field);
        if ($patient_other_info) {
            $patient_other_info = $patient_other_info->toArray();
            unset($patient_other_info['id']);
        } else {
            $patient_other_info = [];
        }
        //患者地址相关信息
        $address_field = ['AAA09', 'AAA10', 'AAA11', 'AAA43', 'AAA44', 'AAA45', 'AAA46', 'AAA47', 'AAA12', 'AAA14C', 'AAA15', 'AAA48', 'AAA49', 'AAA50', 'AAA51', 'AAA17C'];
        $patient_address_info = PatientAddressInfo::query()->where('AAA28', $patient_info['MED_REC_ID'])->first($address_field);
        if ($patient_address_info) {
            $patient_address_info = $patient_address_info->toArray();
        } else {
            $patient_address_info = [];
        }
        //患者工作信息
        $work_field = ['AAA18C', 'AAA19', 'AAA20', 'AAA21C'];
        $patient_work_info = PatientWorkInfo::query()->where('AAA28', $patient_info['MED_REC_ID'])->first($work_field);
        if ($patient_work_info) {
            $patient_work_info = $patient_work_info->toArray();
        } else {
            $patient_work_info = [];
        }
        //患者联系人信息
        $patient_field = ['AAA22', 'AAA24', 'AAA25', 'AAA23C'];
        $patient_contacts_info = PatientContactsInfo::query()->where('AAA28', $patient_info['MED_REC_ID'])->first($patient_field);
        if ($patient_contacts_info) {
            $patient_contacts_info = $patient_contacts_info->toArray();
        } else {
            $patient_contacts_info = [];
        }
        //患者住院信息
        $hospital_info_field = ['ABC01C', 'AAA27', 'AAB01', 'AAB02C', 'AAB03', 'AAC02C', 'AAC03', 'AAD01C', 'AEI01C', 'AEM03C', 'AEM04', 'AAC11C', 'AAB11C', 'AAB11N', 'ABC01C'];
        $patient_hospital_info = PatientHospitalInfo::query()->where('AAA28', $patient_info['MED_REC_ID'])->first($hospital_info_field);
        if ($patient_hospital_info) {
            $patient_hospital_info = $patient_hospital_info->toArray();
        } else {
            $patient_hospital_info = [];
        }
        //患者医疗信息
        $medical_info_field = ['ABC03C', 'ABF01C', 'ABF04', 'ABF01N', 'AEL01', 'ABA01N', 'ABA01C', 'AEB02C', 'AEB01', 'AEG01C', 'AEG02C', 'AED01C', 'AEJ01', 'AEJ02', 'AEJ03', 'AEJ04', 'AEJ05', 'AEJ06', 'ABF02C'];
        $patient_medical_info = PatientMedicalInfo::query()->where('AAA28', $patient_info['MED_REC_ID'])->first($medical_info_field);
        if ($patient_medical_info) {
            $patient_medical_info = $patient_medical_info->toArray();
            $patient_medical_info['AEJ01'] = $patient_medical_info['AEJ01'] ?: '-';
            $patient_medical_info['AEJ02'] = $patient_medical_info['AEJ02'] ?: '-';
            $patient_medical_info['AEJ03'] = $patient_medical_info['AEJ03'] ?: '-';
            $patient_medical_info['AEJ04'] = $patient_medical_info['AEJ04'] ?: '-';
            $patient_medical_info['AEJ05'] = $patient_medical_info['AEJ05'] ?: '-';
            $patient_medical_info['AEJ06'] = $patient_medical_info['AEJ06'] ?: '-';
        } else {
            $patient_medical_info = [];
        }
        //者主要诊断信息
        $diagnosis_field = ['ICD10_NAME', 'ICD10_ID1', 'id', 'DIA_ORDER', 'DIA_ORDER', 'RYQK'];
        $main_diagnosis = MainDiagnosis::query()->where('AAA28', $patient_info['MED_REC_ID'])->orderBy('DIA_ORDER', 'asc')->get($diagnosis_field);
        if ($main_diagnosis) {
            $main_diagnosis = $main_diagnosis->toArray();
            foreach ($main_diagnosis as $k => $v) {
                $main_diagnosis[$k]['class'] = 'main';
                $patient_medical_info['ABC03C'] = $v['RYQK'];
                $patient_hospital_info['ABC01C'] = $v['ICD10_ID1'];
            }
        } else {
            $main_diagnosis = [];
        }
        //患者其他诊断信息
        $other_diagnosis_field = ['ICD10_NAME', 'ICD10_ID1', 'id', 'DIA_ORDER', 'DIA_ORDER', 'RYQK'];
        $other_diagnosis = OtherDiagnosis::query()->where('AAA28', $patient_info['MED_REC_ID'])->orderBy('DIA_ORDER', 'asc')->get($other_diagnosis_field);
        if ($other_diagnosis) {
            $other_diagnosis = $other_diagnosis->toArray();
            foreach ($other_diagnosis as $k => $v) {
                $other_diagnosis[$k]['class'] = 'other';
                $patient_hospital_info['ABC01C'] = $v['ICD10_ID1'];
            }
        } else {
            $other_diagnosis = [];
        }
        $diagnosis = array_merge($main_diagnosis, $other_diagnosis);

        //患者医生信息
        $doctor_info_field = ['QUALITY_CONTROL', 'AEE01', 'AEE01_CODE', 'AEE02', 'AEE02_CODE', 'AEE03', 'AEE03_CODE', 'AEE04', 'AEE04_CODE', 'AEE10', 'AEE05', 'AEE07', 'AEE08', 'AED02', 'AED03', 'AED04'];
        $patient_doctor_info = PatientDoctorInfo::query()->where('AAA28', $patient_info['MED_REC_ID'])->first($doctor_info_field);
        if ($patient_doctor_info) {
            $patient_doctor_info = $patient_doctor_info->toArray();
        } else {
            $patient_doctor_info = [];
        }
        //患者其他补充信息
        $other_field = ['SFZJLX', 'TYSHXYDM', 'JKKH', 'BFRY', 'ZKKB', 'SJHL', 'EJHL', 'YJHL', 'TJHL', 'ZRHSBM', 'BMY', 'ZKRQ', 'SSLX1', 'SSLX2', 'SSLX3', 'SSLX4', 'SSLX5', 'SSLX6', 'SSLX7', 'SSLX8', 'SSLX9', 'SSLX10', 'SSLX11', 'SSLX12', 'SSLX13', 'SSLX14', 'SSLX15', 'LCLJ', 'WCQK', 'SSPB'];
        $other = PatientAdd::query()->where(['AAA28' => $patient_info['MED_REC_ID']])->first($other_field);
        if ($other) {
            $other = $other->toArray();
            $other['ZKRQ'] = date("Y-m-d", strtotime($other['ZKRQ']));//质控日期：年月日
            $other['SSPB'] = collect(config('dictionaries.SSPB'))->get($other['SSPB'], '');
        } else {
            $other = [];
        }
        $ba_brsy = BaBrsy::query()->where(['AAA28' => $patient_info['MED_REC_ID']])->first();
        if ($ba_brsy) {
            $ba_brsy = $ba_brsy->toArray();
            $patient_hospital_info['AAC03'] = $ba_brsy['CYBQ'];//出院病房
        }
        $SSPB = config('dictionaries.SSPB');
        //患者主要手术信息
        $main_operation_field = ['HOCUS_MAN_CODE', 'OPE_ORDER', 'RJSS', 'SSPB', 'id', 'ICD9_ID1', 'OPE_DATE', 'OPE_LEVEL', 'ICD9_NAME', 'OPE_MAN_NAME', 'FRIST_ASSISTANT_NAME', 'SECOND_ASSISTANT_NAME', 'INCISION_GRADE_ID', 'HOCUS_WAY_ID', 'HOCUS_MAN_NAME'];
        $main_operation = MainOperation::query()->where('AAA28', $patient_info['MED_REC_ID'])->orderBy('OPE_ORDER', 'asc')->get($main_operation_field);

        if ($main_operation) {
            $main_operation = $main_operation->toArray();
            foreach ($main_operation as $k => $v) {
                $main_operation[$k]['class'] = 'main';
                $main_operation[$k]['SSPB'] = $SSPB[$v['SSPB']] ?? '无';
                if (isset($other['SSLX' . $v['OPE_ORDER']])) {
                    $main_operation[$k]['SSLX'] = $other['SSLX' . $v['OPE_ORDER']];
                } else {
                    $main_operation[$k]['SSLX'] = '';
                }

                if (strpos($v['OPE_DATE'], 'T')) {
                    $main_operation[$k]['OPE_DATE'] = str_replace('T', ' ', $v['OPE_DATE']);
                }
            }
        } else {
            $main_operation = [];
        }
        //患者次要手术信息
        $secondary_operation_field = ['HOCUS_MAN_CODE', 'OPE_ORDER', 'RJSS', 'SSPB', 'id', 'ICD9_ID1', 'OPE_DATE', 'OPE_LEVEL', 'ICD9_NAME', 'OPE_MAN_NAME', 'FRIST_ASSISTANT_NAME', 'SECOND_ASSISTANT_NAME', 'INCISION_GRADE_ID', 'HOCUS_WAY_ID', 'HOCUS_MAN_NAME'];
        $secondary_operation = SecondaryOperation::query()->where('AAA28', $patient_info['MED_REC_ID'])->orderBy('OPE_ORDER', 'asc')->get($secondary_operation_field);
        if ($secondary_operation) {
            $secondary_operation = $secondary_operation->toArray();
            foreach ($secondary_operation as $k => $v) {
                $secondary_operation[$k]['class'] = 'other';
                $secondary_operation[$k]['SSPB'] = $SSPB[$v['SSPB']] ?? '无';
                if (isset($other['SSLX' . $v['OPE_ORDER']])) {
                    $secondary_operation[$k]['SSLX'] = $other['SSLX' . $v['OPE_ORDER']];
                } else {
                    $secondary_operation[$k]['SSLX'] = '';
                }
                if (strpos($v['OPE_DATE'], 'T')) {
                    $secondary_operation[$k]['OPE_DATE'] = str_replace('T', ' ', $v['OPE_DATE']);
                }
            }
        } else {
            $secondary_operation = [];
        }
        $operation = array_merge($main_operation, $secondary_operation);

        //患者费用信息
        $patient_cost_info = PatientCostInfo::query()->where('AAA28', $patient_info['MED_REC_ID'])->first();
        if ($patient_cost_info) {
            $patient_cost_info = $patient_cost_info->toArray();
        } else {
            $patient_cost_info = [];
        }
        //患者重症信息
        $icu_field = ['OUT_TIME', 'IN_TIME', 'IS_MAIN_WAY'];
        $icu = Icu::query()->where('AAA28', $patient_info['MED_REC_ID'])->first($icu_field);
        if ($icu) {
            $icu = $icu->toArray();
        } else {
            $icu = [];
        }
        $datas = array_merge($patient_other_info, $patient_info, $patient_address_info, $patient_work_info, $patient_contacts_info, $patient_hospital_info, $patient_medical_info, $patient_doctor_info, $patient_cost_info, $icu, $other);
        $datas['operation'] = $operation;
        $datas['diagnosis'] = $diagnosis;//诊断
        return $datas;
    }

    /**
     * 数据患者编辑
     * @param $all
     * @return int
     */
    public static function editData($all)
    {
        $MED_REC_ID = $all['MED_REC_ID'];

        $datas = MedicalRecordService::getData($MED_REC_ID);
        $arr = config('mydatabases');//数据库
        $dictionaries = config('dictionaries');//字典
        //找出不同
        $diff = [];
        $main_diagnosis = [];
        $other_diagnosis = [];
        $main_operation = [];
        $other_operation = [];
        //出院病房
        if ($all['AAC03']) {
            $AAC03 = array_search($all['AAC03'], $dictionaries['ABAS02']);
            if ($AAC03 === false) {
                return json_encode(['code' => 0, 'msg' => '出院病房不符合科室字典，请检查！']);
            } else {
                $all['AAC03'] = $AAC03;
            }
        }
        //年龄
        if (isset($all['AAA04'])) {
            if ($all['AAA04'] > 0) {
                if ($all['AAA42'] > 0) {
                    return json_encode(['code' => 0, 'msg' => '新生儿入院体重不规范，请检查！']);
                }
                if ($all['AEN01'] > 0) {
                    return json_encode(['code' => 0, 'msg' => '新生儿出生体重不规范，请检查！']);
                }
                if ($all['AAA40'] > 0) {
                    return json_encode(['code' => 0, 'msg' => '不足一周岁年龄不规范，请检查！']);
                }
            }
        }
        //入院时间
        if (isset($all['AAB01'])) {
            if (!preg_match("/^[0-9]{4}(\-|\/)[0-9]{1,2}(\\1)[0-9]{1,2}(|\s+[0-9]{1,2}(|:[0-9]{1,2}(|:[0-9]{1,2})))$/", $all['AAB01'])) {
                return json_encode(['code' => 0, 'msg' => '入院时间-不规范，请检查！']);
            }
        }
        //出院时间
        if (isset($all['AAC01'])) {
            if (!preg_match("/^[0-9]{4}(\-|\/)[0-9]{1,2}(\\1)[0-9]{1,2}(|\s+[0-9]{1,2}(|:[0-9]{1,2}(|:[0-9]{1,2})))$/", $all['AAC01'])) {
                return json_encode(['code' => 0, 'msg' => '出院时间-不规范，请检查！']);
            }
            if ($all['AAB01'] >= $all['AAC01']) {
                return json_encode(['code' => 0, 'msg' => '入院时间不能早于出院时间，请检查！']);
            }
        }
        //出生日期
        if (isset($all['AAA03'])) {
            if (!preg_match("/^[0-9]{4}(\-|\/)[0-9]{1,2}(\\1)[0-9]{1,2}$/", $all['AAA03'])) {
                return json_encode(['code' => 0, 'msg' => '出生日期-不规范（yyyy-mm-dd），请检查！']);
            }
        }
        //检查职业
        if (isset($all['AAA18C'])) {
            $AAA18C = array_search($all['AAA18C'], $dictionaries['AAA18C']);
            if ($AAA18C === false) {
                return json_encode(['code' => 0, 'msg' => '职业不得超出职业字典范围，请检查！']);
            } else {
                $all['AAA18C'] = $AAA18C;
            }
        }
        //性别
        if (isset($all['AAA02C'])) {
            $AAA02C = array_search($all['AAA02C'], $dictionaries['AAA02C']);
            if ($AAA02C === false) {
                return json_encode(['code' => 0, 'msg' => '性别-不规范，请检查！']);
            } else {
                $all['AAA02C'] = $AAA02C;
            }
        }
        //国籍
        if (isset($all['AAA05C'])) {
            $AAA05C = array_search($all['AAA05C'], $dictionaries['AAA05C']);
            if ($AAA05C === false) {
                return json_encode(['code' => 0, 'msg' => '国籍-不规范，请检查！']);
            } else {
                $all['AAA05C'] = $AAA05C;
            }
        }
        //民族
        if (isset($all['AAA06C'])) {
            $AAA06C = array_search($all['AAA06C'], $dictionaries['AAA06C']);
            if ($AAA06C === false) {
                return json_encode(['code' => 0, 'msg' => '民族-不规范，请检查！']);
            } else {
                $all['AAA06C'] = $AAA06C;
            }
        }
        //婚姻
        if (isset($all['AAA08C'])) {
            $AAA08C = array_search($all['AAA08C'], $dictionaries['AAA08C']);
            if ($AAA08C === false) {
                return json_encode(['code' => 0, 'msg' => '婚姻-不规范，请检查！']);
            } else {
                $all['AAA08C'] = $AAA08C;
            }
        }
        //医疗付费方式
        if (isset($all['AAA26C'])) {
            $AAA26C = array_search($all['AAA26C'], $dictionaries['AAA26C']);
            if ($AAA26C === false) {
                return json_encode(['code' => 0, 'msg' => '医疗付费方式-不规范，请检查！']);
            } else {
                $all['AAA26C'] = $AAA26C;
            }
        }
        //离院方式
        if (isset($all['AEM01C'])) {
            $AEM01C = array_search($all['AEM01C'], $dictionaries['AEM01C']);
            if ($AEM01C === false) {
                return json_encode(['code' => 0, 'msg' => '离院方式-不规范，请检查！']);
            } else {
                $all['AEM01C'] = $AEM01C;
            }
        }
        //入院途径
        if (isset($all['AAB06C'])) {
            $AAB06C = array_search($all['AAB06C'], $dictionaries['AAB06C']);
            if ($AAB06C === false) {
                return json_encode(['code' => 0, 'msg' => '入院途径-不规范，请检查！']);
            } else {
                $all['AAB06C'] = $AAB06C;
            }
        }
        //身份证号格式
        if (isset($all['AAA07'])) {
//            if(!self::isValid($all['AAA07'])){
//                return json_encode(['code'=>0,'msg'=>'身份证号格式-不规范，请检查！']);
//            }
        }
        //药物过敏
        if (isset($all['AEB02C'])) {
            $AEB02C = explode(',', $all['AEB02C']);
            if ($AEB02C[0] == '有') {
                if (empty($AEB02C[1])) {
                    return json_encode(['code' => 0, 'msg' => '药物过敏-不规范，请检查！']);
                } else {
                    $all['AEB01'] = $AEB02C[1];
                }
            }
            $all['AEB02C'] = $AEB02C[0];
        }
        //最高诊断依据代码
        if (isset($all['ABF02C'])) {
            $ABF02C = array_search($all['ABF02C'], $dictionaries['ABF02C']);
            if ($ABF02C === false) {
                return json_encode(['code' => 0, 'msg' => '最高诊断依据代码-不规范，请检查！']);
            } else {
                $all['ABF02C'] = $ABF02C;
            }
        }
        //血型
        if (isset($all['AEG01C'])) {
            $AEG01C = array_search($all['AEG01C'], $dictionaries['AEG01C']);
            if ($AEG01C === false) {
                return json_encode(['code' => 0, 'msg' => '血型-不规范，请检查！']);
            } else {
                $all['AEG01C'] = $AEG01C;
            }
        }
        //Rh 代码
        if (isset($all['AEG02C'])) {
            $AEG02C = array_search($all['AEG02C'], $dictionaries['AEG02C']);
            if ($AEG02C === false) {
                return json_encode(['code' => 0, 'msg' => 'Rh 代码-不规范，请检查！']);
            } else {
                $all['AEG02C'] = $AEG02C;
            }
        }
        //尸检
        if (isset($all['AEI01C'])) {
            $AEI01C = array_search($all['AEI01C'], $dictionaries['AEI01C']);
            if ($AEI01C === false) {
                return json_encode(['code' => 0, 'msg' => '尸检-不规范，请检查！']);
            } else {
                $all['AEI01C'] = $AEI01C;
            }
        }
        //是否有出院31日内再住院计划
        if (isset($all['AEM03C'])) {
            $AEM03C = array_search($all['AEM03C'], $dictionaries['AEM03C']);
            if ($AEM03C === false) {
                return json_encode(['code' => 0, 'msg' => '是否有出院31日内再住院计划-不规范，请检查！']);
            } else {
                $all['AEM03C'] = $AEM03C;
            }
        }
        //入院科别
        if (isset($all['AAB02C'])) {
            $AAB02C = array_search($all['AAB02C'], $dictionaries['AAB02C']);
            if ($AAB02C === false) {
                return json_encode(['code' => 0, 'msg' => '入院科别-不规范，请检查！']);
            } else {
                $all['AAB02C'] = $AAB02C;
            }
        }
        //出院科别
        if (isset($all['AAC02C'])) {
            $AAC02C = array_search($all['AAC02C'], $dictionaries['AAC02C']);
            if ($AAC02C === false) {
                return json_encode(['code' => 0, 'msg' => '出院科别-不规范，请检查！']);
            } else {
                $all['AAC02C'] = $AAC02C;
            }
        }
        //联系人关系
        if (isset($all['AAA23C'])) {
            $AAA23C = array_search($all['AAA23C'], $dictionaries['AAA23C']);
            if ($AAA23C === false) {
                return json_encode(['code' => 0, 'msg' => '联系人关系-不规范，请检查！']);
            } else {
                $all['AAA23C'] = $AAA23C;
            }
        }
        foreach ($all as $key => $item) {
            if ($key == 'error') {
                continue;
            } elseif ($key == 'diagnosis') {
                $item = array_column($item, null, 'id');
                unset($item['']);
                $oragin = array_column($datas['diagnosis'], null, 'id');
                if (empty($oragin) && !empty($item)) {
                    foreach ($item as $v) {
                        if ($v['class'] == 'main') {
                            $main_operation[] = $v;
                        } else {
                            $other_operation[] = $v;
                        }
                    }
                } else {
                    foreach ($item as $k => $v) {
                        if (!empty(array_diff_assoc($v, $oragin[$k]))) {
                            if ($v['class'] == 'main') {
                                $main_operation[] = $v;
                            } else {
                                $other_operation[] = $v;
                            }
                        }
                    }
                }
            } elseif ($key == 'operation') {
                $item = array_column($item, null, 'id');
                $oragin = array_column($datas['operation'], null, 'id');
                if (empty($oragin) && !empty($item)) {
                    foreach ($item as $v) {
                        if ($v['class'] == 'main') {
                            $main_diagnosis[] = $v;
                        } else {
                            $other_diagnosis[] = $v;
                        }
                    }
                } else {
                    foreach ($item as $k => $v) {
                        if (empty($k)) {
                            continue;
                        }
                        if (!empty(array_diff_assoc($v, $oragin[$k]))) {
                            if ($v['class'] == 'main') {
                                $main_diagnosis[] = $v;
                            } else {
                                $other_diagnosis[] = $v;
                            }
                        }
                    }
                }
            } else {
                if (isset($datas[$key])) {
                    if ($item != $datas[$key]) {
                        $diff[$key] = $item;
                    }
                }
            }
        }
        if (isset($diff['AEB02C'])) {
            $tempArr = explode(',', $diff['AEB02C']);
            $diff['AEB02C'] = $tempArr[0] == '无' ? 2 : 1;
            $diff['AEB01'] = $tempArr[1] ?? '';
        }
        $data = [];
        foreach ($diff as $key => $item) {
            if (strpos($item, '******') !== false) continue;
            $table = $arr[$key] ?? '';
            if (!empty($table)) {
                $data[$table][$key] = $item;
            }
        }

        //患者主要信息
        if (isset($data['patient_info']) && !empty($data['patient_info'])) {
            PatientInfo::query()->where(['MED_REC_ID' => $MED_REC_ID])->update($data['patient_info']);
        }
        //患者其他信息
        if (isset($data['patient_other_info']) && !empty($data['patient_other_info'])) {
            PatientOtherInfo::query()->where(['AAA28' => $MED_REC_ID])->update($data['patient_other_info']);
        }
        //患者住院信息
        if (isset($data['patient_hospital_info']) && !empty($data['patient_hospital_info'])) {
            PatientHospitalInfo::query()->where(['AAA28' => $MED_REC_ID])->update($data['patient_hospital_info']);
        }
        //患者医生信息
        if (isset($data['patient_doctor_info']) && !empty($data['patient_doctor_info'])) {
            PatientDoctorInfo::query()->where(['AAA28' => $MED_REC_ID])->update($data['patient_doctor_info']);
        }
        //患者地址相关信息
        if (isset($data['patient_address_info']) && !empty($data['patient_address_info'])) {
            PatientAddressInfo::query()->where(['AAA28' => $MED_REC_ID])->update($data['patient_address_info']);
        }
        //患者工作信息
        if (isset($data['patient_work_info']) && !empty($data['patient_work_info'])) {
            PatientWorkInfo::query()->where(['AAA28' => $MED_REC_ID])->update($data['patient_work_info']);
        }
        //患者联系人信息
        if (isset($data['patient_contacts_info']) && !empty($data['patient_contacts_info'])) {
            PatientContactsInfo::query()->where(['AAA28' => $MED_REC_ID])->update($data['patient_contacts_info']);
        }
        //患者重症信息
        if (isset($data['icu']) && !empty($data['icu'])) {
            Icu::query()->where(['AAA28' => $MED_REC_ID])->update($data['icu']);
        }
        //患者费用信息
        if (isset($data['patient_cost_info']) && !empty($data['patient_cost_info'])) {
            PatientCostInfo::query()->where(['AAA28' => $MED_REC_ID])->update($data['patient_cost_info']);
        }

        return json_encode(['code' => 200, 'msg' => 'ok']);
    }

    /**
     * @param string $num
     *      只检查身份证格式
     * @return bool
     */
    public static function isValid($num)
    {
        //老身份证长度15位，新身份证长度18位
        $length = strlen($num);
        if ($length == 15) { //如果是15位身份证

            //15位身份证没有字母
            if (!is_numeric($num)) {
                return false;
            }

        } else if ($length == 18) { //如果是18位身份证

            //基本格式校验
            if (!preg_match('/^\d{17}[0-9xX]$/', $num)) {
                return false;
            }

        } else { //假身份证
            return false;
        }

        return true;
    }
}
