<?php

namespace App\Services;

use App\Model\DoctorAdvice;
use App\Model\OtherDiagnosis;
use App\Model\PatientInfo;
use App\Model\SecondaryOperation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DoctorAdviceService
{

    public static function getList($patientInfo, $mainDiagnosis, $otherDiagnosis, $mainOperation, $secondaryOperation, $patientMedicalInfo,$yzb,$offset,$limit)
    {
        $query = PatientInfo::query();
        if (!empty($patientInfo)) {
            if (isset($patientInfo['and'])){
                $query->where($patientInfo['and']);
            }
            if (isset($patientInfo['no'])){
                $query->where($patientInfo['no']);
            }
            if (isset($patientInfo['or'])){
                $query->orWhere($patientInfo['or']);
            }
        }
        $query->leftjoin('main_diagnosis as md', 'patient_info.MED_REC_ID', '=', 'md.AAA28');
        if (!empty($mainDiagnosis)) {
            if (isset($mainDiagnosis['and'])){
                $query->where($mainDiagnosis['and']);
            }
            if (isset($mainDiagnosis['no'])){
                $query->where($mainDiagnosis['no']);
            }
            if (isset($mainDiagnosis['or'])){
                $query->orWhere($mainDiagnosis['or']);
            }
        }
        $query->leftjoin('main_operation as mo', 'patient_info.MED_REC_ID', '=', 'mo.AAA28');
        if (!empty($mainOperation)) {
            if (isset($mainOperation['and'])){
                $query->where($mainOperation['and']);
            }
            if (isset($mainOperation['no'])){
                $query->where($mainOperation['no']);
            }
            if (isset($mainOperation['or'])){
                $query->orWhere($mainOperation['or']);
            }
        }
        if (!empty($secondaryOperation)) {
            $secondaryOperationSql = SecondaryOperation::query()
                ->select('AAA28');
            if (isset($secondaryOperation['and'])){
                $query->where($secondaryOperation['and']);
            }
            if (isset($secondaryOperation['no'])){
                $query->where($secondaryOperation['no']);
            }
            if (isset($secondaryOperation['or'])){
                $query->orWhere($secondaryOperation['or']);
            }
            $query->joinSub($secondaryOperationSql, 'so', 'patient_info.MED_REC_ID', '=', 'so.AAA28', 'left')
                ->whereNotNull('so.AAA28');
        }
        if (!empty($otherDiagnosis)) {
            $odQuery = OtherDiagnosis::query()
                ->select('AAA28');
            if (isset($otherDiagnosis['and'])){
                $query->where($otherDiagnosis['and']);
            }
            if (isset($otherDiagnosis['no'])){
                $query->where($otherDiagnosis['no']);
            }
            if (isset($otherDiagnosis['or'])){
                $query->orWhere($otherDiagnosis['or']);
            }
            $query->joinSub($odQuery, 'od', 'patient_info.MED_REC_ID', '=', 'od.AAA28', 'left')
                ->whereNotNull('od.AAA28');
        }
        if (!empty($patientMedicalInfo)) {
            $query->leftjoin('patient_medical_info as pmi', 'patient_info.MED_REC_ID', '=', 'pmi.AAA28');
            if (isset($patientMedicalInfo['and'])){
                $query->where($patientMedicalInfo['and']);
            }
            if (isset($patientMedicalInfo['no'])){
                $query->where($patientMedicalInfo['no']);
            }
            if (isset($patientMedicalInfo['or'])){
                $query->orWhere($patientMedicalInfo['or']);
            }
        }
        $infoSql =  $query->leftjoin('patient_hospital_info as phi', 'patient_info.MED_REC_ID', '=', 'phi.AAA28')
            ->orderBy('MED_REC_ID', 'desc')
            ->select('MED_REC_ID','patient_info.AAA28','AAC01','phi.AAB01' ,'md.ICD10_NAME' ,'mo.ICD9_NAME');
        $D_A_query = DoctorAdvice::query()
            ->joinSub($infoSql,'pi','pi.MED_REC_ID','=','yzb.ZYH','left')
            ->whereNotNull('pi.MED_REC_ID');
        if (!empty($yzb)){
            if (isset($yzb['and'])){
                $D_A_query->where($yzb['and']);
            }
            if (isset($yzb['no'])){
                $D_A_query->where($yzb['no']);
            }
            if (isset($yzb['or'])){
                $D_A_query->orWhere($yzb['or']);
            }
        }
        $count = $D_A_query->count(DB::raw('DISTINCT ZYH'));
        $data = $D_A_query
            ->groupBy('ZYH')
            ->offset($offset)
            ->limit($limit)
            ->get(['ZYH','YZMC','BRKS','KZKS','YZQX','KZSJ','pi.AAA28','pi.AAC01','pi.AAB01','pi.ICD10_NAME','pi.ICD9_NAME']);
        if ($data) {
            $data = $data->toArray();
        } else {
            $data = [];
        }
        return ['list' => $data, 'total' => $count];
    }
}
