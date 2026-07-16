<?php

namespace App\Console\Commands\MysqlDataSyncEs;

use App\Model\EMR_BL_BL01;
use App\Model\Error;
use App\Model\FeeDetailed;
use App\Model\Icu;
use App\Model\MainDiagnosis;
use App\Model\MainOperation;
use App\Model\OtherDiagnosis;
use App\Model\PatientAdd;
use App\Model\PatientAddressInfo;
use App\Model\PatientContactsInfo;
use App\Model\PatientCostInfo;
use App\Model\PatientDoctorInfo;
use App\Model\PatientHospitalInfo;
use App\Model\PatientInfo;
use App\Model\PatientMedicalInfo;
use App\Model\PatientOtherInfo;
use App\Model\PatientWorkInfo;
use App\Model\SecondaryOperation;
use App\Model\Yzb;
use App\Services\ElasticsearchService;
use Carbon\Carbon;
use Elasticsearch\ClientBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IdsHomeBl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:idshomebl';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'mysql同步数据到es(home_bl)';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public static $con;
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // 定义每次查询的数量
        $batchSize = 800;

        // 定义查询的起始位置
        $start = 1;

        $esService = ClientBuilder::create()->setHosts([env('ES_HOST')])->build();
        do {
            // 从 patient_info 表中获取数据
            $page = $start-1;
            $patients = $this->getPatientInfo($page,$batchSize);

            // 检查是否获取到数据
            if ($patients->isEmpty()) {
                echo '同步完成';
                break;
            }

            // 将查询结果的 id 字段转化为一维数组
            $ZYHS = $patients->pluck('id')->toArray();

            //查找 EMR_BL_BL01  住院病历
            $emrBl01Data = $this->getEmrBlBl01($ZYHS);

            //查找 error 病案首页缺陷问题汇总
            $errorData = $this->getError($ZYHS);

            //查找 icu 重症监护室
            $icuData = $this->getIcu($ZYHS);

            //查找 main_diagnosis 病案首页(诊断)
            $mainDiagnosisData = $this->getMainDiagnosis($ZYHS);

            //查找 main_operation 主要手术
            $mainOperationData = $this->getMainOperation($ZYHS);

            //查找 other_diagnosis 其他诊断
            $otherDiagnosis = $this->getOtherDiagnosis($ZYHS);

            //查找 patient_doctor_info 医生信息
            $patientDoctorInfoData = $this->getPatientDoctorInfo($ZYHS);

            //查找 patient_hospital_info 住院信息
            $patientHospitalInfoData = $this->getPatientHospitalInfo($ZYHS);

            //查找 patient_medical_info 病案首页(诊断信息)
            $patientMedicalInfoData = $this->getPatientMedicalInfo($ZYHS);

            //查找 secondary_operation 病案首页(其他手术)
            $secondaryOperationData = $this->getSecondaryOperation($ZYHS);

            $params = [];
            $content = [];
            foreach ($patients as $patientInfo) {
                if (empty($patientInfo->MED_REC_ID)){
                    continue;
                }
                $ZYH = $patientInfo->MED_REC_ID;
                $content = [
                    'AAA05C' => $patientInfo->AAA05C ?? '',
                    'AEM01C' => $patientInfo->AEM01C ?? '',
                    'ICD9_NAME' => $patientInfo->ICD9_NAME ?? '',
                    'ABC01N' => $patientInfo->ABC01N ?? '',
                    'created_at' => $patientInfo->created_at ?? '',
                    'ABG01N' => $patientInfo->ABG01N ?? '',
                    'F_D' => $patientInfo->F_D ?? '',
                    'J' => $patientInfo->J ?? '',
                    'source' => $patientInfo->source ?? '',
                    'ADA01' => $patientInfo->ADA01 ?? '',
                    'ATTEND_GRP_NAME' => $patientInfo->ATTEND_GRP_NAME ?? '',
                    'AAA01' => $patientInfo->AAA01 ?? '',
                    'AAB01' => $patientInfo->AAB01 ?? '',
                    'score' => $patientInfo->score ?? '',
                    'AAA28' => $patientInfo->AAA28 ?? '',
                    'AAC04' => $patientInfo->AAC04 ?? '',
                    'AAA03' => $patientInfo->AAA03 ?? '',
                    'AAC01' => $patientInfo->AAC01 ?? '',
                    'AAA04' => $patientInfo->AAA04 ?? '',
                    'ORG_STATE' => $patientInfo->ORG_STATE ?? '',
                    'AAA07' => $patientInfo->AAA07 ?? '',
                    'AAA29' => $patientInfo->AAA29 ?? '',
                    'is_error' => $patientInfo->is_error ?? '',
                    'AAA02C' => $patientInfo->AAA02C ?? '',
                    'MED_REC_ID' => $patientInfo->MED_REC_ID ?? '',
                    'AEN01' => $patientInfo->AEN01 ?? '',
                    'AAA06C' => $patientInfo->AAA06C ?? '',
                    'AAA26C' => $patientInfo->AAA26C ?? '',
                    'AAB06C' => $patientInfo->AAB06C ?? '',
                    'coder_id' => $patientInfo->coder_id ?? '',
                    'level' => $patientInfo->level ?? '',
                    'AAA08C' => $patientInfo->AAA08C ?? '',
                    'ADA0101' => $patientInfo->ADA0101 ?? '',
                    'is_defect' => $patientInfo->is_defect ?? '',
                    'AAA42' => $patientInfo->AAA42 ?? '',
                    'ATTEND_GRP_CODE' => $patientInfo->ATTEND_GRP_CODE ?? '',
                    'AAA40' => $patientInfo->AAA40 ?? '',
                    'ABG01C' => $patientInfo->ABG01C ?? '',
                    'AAC11N' => $patientInfo->AAC11N ?? '',
                ];
                if (!empty($emrBl01Data)){
                    foreach ($emrBl01Data as $a=>$b){
                        if ($ZYH == $b['JZHM']){
                            $content['bl01'][] = $b;
                        }
                    }
                }
                if (!empty($errorData)){
                    foreach ($errorData as $a=>$b){
                        if ($ZYH == $b['ZYH']){
                            unset($b['ZYH']);
                            $content['error'][] = $b;
                        }
                    }
                }
                if (!empty($icuData)){
                    foreach ($icuData as $a=>$b){
                        if ($ZYH == $b['AAA28']){
                            $content['icu'][] = $b;
                        }
                    }
                }
                if (!empty($mainDiagnosisData)){
                    foreach ($mainDiagnosisData as $a=>$b){
                        if ($ZYH == $b['AAA28']){
                            $content['main_diagnosis'][] = $b;
                        }
                    }
                }
                if (!empty($mainOperationData)){
                    foreach ($mainOperationData as $a=>$b){
                        if ($ZYH == $b['AAA28']){
                            $content['main_operation'][] = $b;
                        }
                    }
                }
                if (!empty($otherDiagnosis)){
                    foreach ($otherDiagnosis as $a=>$b){
                        if ($ZYH == $b['AAA28']){
                            $content['other_diagnosis'][] = $b;
                        }
                    }
                }
                if (!empty($patientDoctorInfoData)){
                    foreach ($patientDoctorInfoData as $a=>$b){
                        if ($ZYH == $b['AAA28']){
                            $content['patient_doctor_info'][] = $b;
                        }
                    }
                }
                if (!empty($patientHospitalInfoData)){
                    foreach ($patientHospitalInfoData as $a=>$b){
                        if ($ZYH == $b['AAA28']){
                            $content['patient_hospital_info'][] = $b;
                        }
                    }
                }
                if (!empty($patientMedicalInfoData)){
                    foreach ($patientMedicalInfoData as $a=>$b){
                        if ($ZYH == $b['AAA28']){
                            $content['patient_medical_info'][] = $b;
                        }
                    }
                }
                if (!empty($secondaryOperationData)){
                    foreach ($secondaryOperationData as $a=>$b){
                        if ($ZYH == $b['AAA28']){
                            $content['secondary_operation'][] = $b;
                        }
                    }
                }

                $params['body'][] = array(
                    'index' => array(
                        '_index' => 'home_bl',
                        '_type' => '_doc',
                        '_id' => $ZYH
                    )
                );
                $params['body'][] = $content;

            }
            $res = $esService->bulk($params);
            // 更新查询的起始位置
            $start += $batchSize;

        } while (true);

    }

    /**
     * 获取用户主信息
     * @return array
     */
    public function getPatientInfo($page,$batchSize)
    {
        //按入院时间查找数据
        $patient = DB::table('patient_info')->select("MED_REC_ID as id", "MED_REC_ID", "AAA28","AAA01","AAA02C","AAA03","AAA05C","AAC11N","AAA42","AEN01","AAA06C","AAA07","AAA08C",DB::raw("if(AAB01 != '',AAB01, '1970-01-01 00:00:00')AS AAB01") ,DB::raw("if(AAC01 != '', AAC01, '1970-01-01 00:00:00') AS AAC01"),"AAA04","AAA40","AEM01C","AAC04",DB::raw("if(ADA01 != '',ADA01,'0') AS ADA01"),"ADA0101","AAA29","AAB06C","AAA26C","ABC01N","ORG_STATE","ICD9_NAME","created_at","ATTEND_GRP_CODE", "ATTEND_GRP_NAME","F_D","J","coder_id","score","is_error","ABG01N","ABG01C","source","level","is_defect")
            ->offset($page)
            ->limit($batchSize)
            ->get();
        return $patient;
    }

    // 获取emr bl01
    public function getEmrBlBl01($zyhs){
        $data = EMR_BL_BL01::query()->whereIn('JZHM',$zyhs)->get(['JZHM','BLMC','BLLB'])->toArray();
        //$data = DB::select("SELECT JSON_ARRAYAGG(JSON_OBJECT('JZHM',JZHM,'BLMC',BLMC,'BLLB',BLLB)) pacs FROM EMR_BL_BL01 where JZHM = $zyh");
        return $data;
    }

    // 获取病案首页 缺陷问题汇总
    public function getError($zyh){
        $data = Error::query()->whereIn('ZYH',$zyh)->get(['ZYH','year','month','desc','error_field','error_name','level','type','error_type','error_rule','down','coder_id','AAC11C','status','source','category'])->toArray();
        //$data = DB::select("select JSON_ARRAYAGG(JSON_OBJECT('year',`year`,'month',`month`,'desc',`desc`,'error_field',error_field,'error_name',error_name,'level',error.level,'type',type,'error_type',error_type,'error_rule',error_rule,'down',down,'coder_id',error.coder_id,'AAC11C',error.AAC11C,'status',error.`status`,'source',error.source,'category',category)) pacs from error where ZYH = $zyh");
        return $data;
    }
    // 获取 病案首页 重症监护室
    public function getIcu($zyh){
        $data = Icu::query()->whereIn('AAA28',$zyh)->get(['AAA28','IS_MAIN_WAY','IN_TIME','OUT_TIME','AEL01','AREA_ID','BATCH_ID'])->toArray();
        //$data = DB::select("select JSON_ARRAYAGG(JSON_OBJECT('AAA28',icu.AAA28,'IS_MAIN_WAY',icu.IS_MAIN_WAY,'IN_TIME',icu.IN_TIME,'OUT_TIME',icu.OUT_TIME,'AEL01',AEL01,'AREA_ID',AREA_ID,'BATCH_ID',BATCH_ID)) pacs from icu where AAA28 = $zyh");
        return $data;
    }
    //获取 诊断
    public function getMainDiagnosis($zyh){
        $data = MainDiagnosis::query()->whereIn('AAA28',$zyh)->select('AAA28','ICD10_NAME','ICD10_ID1','DIA_ORDER','AREA_ID','BATCH_ID','LBMC',DB::raw("CASE RYQK WHEN '有' THEN '1' WHEN '临床未确定' THEN '2' WHEN '情况不明' THEN '3' WHEN '无' THEN '4'  ELSE '4'END as RYQK"))->get()->toArray();
        //$data = DB::select("SELECT JSON_ARRAYAGG(JSON_OBJECT('AAA28',main_diagnosis.AAA28,'ICD10_NAME',main_diagnosis.ICD10_NAME,'ICD10_ID1',main_diagnosis.ICD10_ID1,'DIA_ORDER',main_diagnosis.DIA_ORDER,'AREA_ID',main_diagnosis.AREA_ID,'BATCH_ID',main_diagnosis.BATCH_ID,'LBMC',LBMC,'RYQK', CASE RYQK WHEN '有' THEN '1' WHEN '临床未确定' THEN '2' WHEN '情况不明' THEN '3' WHEN '无' THEN '4'  ELSE '4'END )) pacs FROM main_diagnosis where AAA28 = $zyh");
        return $data;
    }
    // 获取 主要手术
    public function getMainOperation($zyh){
        $data = MainOperation::query()->whereIn('AAA28',$zyh)->get(['AAA28','ICD9_ID1','ICD9_NAME','OPE_DATE','OPE_MAN_NAME','OPE_MAN_CODE','FRIST_ASSISTANT_CODE','FRIST_ASSISTANT_NAME','SECOND_ASSISTANT_CODE','SECOND_ASSISTANT_NAME','HOCUS_WAY_ID','INCISION_GRADE_ID','HOCUS_MAN_CODE','HOCUS_MAN_NAME','START_TIME','END_TIME','OPE_ORDER','OPE_LEVEL','RJSS','AREA_ID','BATCH_ID','OPE_TYPE','SSPB','HEAL_ID'])->toArray();
        //$data = DB::select("select JSON_ARRAYAGG(JSON_OBJECT('AAA28',AAA28,'ICD9_ID1',ICD9_ID1,'ICD9_NAME',ICD9_NAME,'OPE_DATE',OPE_DATE,'OPE_MAN_NAME',OPE_MAN_NAME,'OPE_MAN_CODE',OPE_MAN_CODE,'FRIST_ASSISTANT_CODE',FRIST_ASSISTANT_CODE,'FRIST_ASSISTANT_NAME',FRIST_ASSISTANT_NAME,'SECOND_ASSISTANT_CODE',SECOND_ASSISTANT_CODE,'SECOND_ASSISTANT_NAME',SECOND_ASSISTANT_NAME,'HOCUS_WAY_ID',HOCUS_WAY_ID,'INCISION_GRADE_ID',INCISION_GRADE_ID,'HOCUS_MAN_CODE',HOCUS_MAN_CODE,'HOCUS_MAN_NAME',HOCUS_MAN_NAME,'START_TIME',START_TIME,'END_TIME',END_TIME,'OPE_ORDER',OPE_ORDER,'OPE_LEVEL',OPE_LEVEL,'RJSS',RJSS,'AREA_ID',AREA_ID,'BATCH_ID',BATCH_ID,'OPE_TYPE',OPE_TYPE,'SSPB',SSPB,'HEAL_ID',HEAL_ID)) main_operation from main_operation where AAA28 = $zyh");
        return $data;
    }
    // 获取 other_diagnosis 其他诊断
    public function getOtherDiagnosis($zyh){
        $data = OtherDiagnosis::query()->whereIn('AAA28',$zyh)->get(['AAA28','ICD10_NAME','ICD10_ID1','DIA_ORDER','AREA_ID','BATCH_ID','LBMC',DB::raw("CASE RYQK WHEN '有' THEN '1' WHEN '临床未确定' THEN '2' WHEN '情况不明' THEN '3' WHEN '无' THEN '4'  ELSE '4'END as RYQK")])->toArray();
        //$data = DB::select("SELECT JSON_ARRAYAGG(JSON_OBJECT('AAA28',other_diagnosis.AAA28,'ICD10_NAME',other_diagnosis.ICD10_NAME,'ICD10_ID1',other_diagnosis.ICD10_ID1,'DIA_ORDER',DIA_ORDER,'AREA_ID',AREA_ID,'BATCH_ID',BATCH_ID,'LBMC',LBMC,'RYQK',CASE RYQK WHEN '有' THEN '1' WHEN '临床未确定' THEN '2' WHEN '情况不明' THEN '3' WHEN '无' THEN '4' ELSE '4' END)) pacs FROM other_diagnosis where AAA28 = $zyh");
        return $data;
    }
    // 获取 patient_doctor_info 医生信息
    public function getPatientDoctorInfo($zyh){
        $data = PatientDoctorInfo::query()->whereIn('AAA28',$zyh)->get(['AAA28','AED02','AED03','AED04','AEE01','AEE01_CODE','AEE02','AEE03','AEE11','AEE09','AEE04','AEE05','AEE07','AEE08','AEE10','CODE_DATE','COMPLETION_DATE','SIGN_IN_DATE','QUALITY_CONTROL','AEE02_CODE','AEE03_CODE','AEE04_CODE'])->toArray();
        //$data = DB::select("SELECT JSON_ARRAYAGG(JSON_OBJECT('AAA28',patient_doctor_info.AAA28,'AED02',patient_doctor_info.AED02,'AED03',patient_doctor_info.AED03,'AED04',patient_doctor_info.AED04,'AEE01',patient_doctor_info.AEE01,'AEE01_CODE',patient_doctor_info.AEE01_CODE,'AEE02',patient_doctor_info.AEE02,'AEE03',patient_doctor_info.AEE03,'AEE11',AEE11,'AEE09',AEE09,'AEE04',AEE04,'AEE05',AEE05,'AEE07',AEE07,'AEE08',AEE08,'AEE10',AEE10,'CODE_DATE',CODE_DATE,'COMPLETION_DATE',COMPLETION_DATE,'SIGN_IN_DATE',SIGN_IN_DATE,'QUALITY_CONTROL',QUALITY_CONTROL,'AEE02_CODE',AEE02_CODE,'AEE03_CODE',patient_doctor_info.AEE03_CODE,'AEE04_CODE',patient_doctor_info.AEE04_CODE)) FROM patient_doctor_info where AAA28 = $zyh");
        return $data;
    }
    // 获取 patient_hospital_info 住院信息
    public function getPatientHospitalInfo($zyh){
        $data = PatientHospitalInfo::query()->whereIn('AAA28',$zyh)->get(['AAA28','AAA30','ABC01C','AAA27','AAC001','AAB01','AAB02C','AAB03','AAB11C','AAB11N','AAC02C','AAC03','AAC11C','AAD01C','AEM02','AEM03C','AEM04','AEI01C'])->toArray();
        //$data = DB::select("SELECT JSON_ARRAYAGG(JSON_OBJECT('AAA28',patient_hospital_info.AAA28,'AAA30',patient_hospital_info.AAA30,'ABC01C',patient_hospital_info.ABC01C,'AAA27',patient_hospital_info.AAA27,'AAC001',patient_hospital_info.AAC001,'AAB01',patient_hospital_info.AAB01,'AAB02C',patient_hospital_info.AAB02C,'AAB03',patient_hospital_info.AAB03,'AAB11C',patient_hospital_info.AAB11C,'AAB11N',patient_hospital_info.AAB11N,'AAC02C',patient_hospital_info.AAC02C,'AAC03',patient_hospital_info.AAC03,'AAC11C',patient_hospital_info.AAC11C,'AAD01C',patient_hospital_info.AAD01C,'AEM02',patient_hospital_info.AEM02,'AEM03C',patient_hospital_info.AEM03C,'AEM04',patient_hospital_info.AEM04,'AEI01C',patient_hospital_info.AEI01C)) FROM patient_hospital_info where AAA28 = $zyh");
        return $data;
    }
    // 获取 patient_medical_info 病案首页(诊断信息)
    public function getPatientMedicalInfo($zyh){
        $data = PatientMedicalInfo::query()->whereIn('AAA28',$zyh)->get(['AAA28','ABA01C','ABA01N','ABC03C','ABF01C','ABF01N','ABF04','ABF02C','ABF03C','ABH01C','ABH0201C','ABH0202C','ABH0203C','ABH03C','AEB02C','AEB01','AED01C','AEG01C','AEG02C','AEG04','AEG05','AEG06','AEG07','AEG08','AEJ01','AEJ02','AEJ03','AEJ04','AEJ05','AEJ06','AEL01','AEN02C','AEN02N','AEI09','AEI10','AEI08'])->toArray();
        //$data = DB::select("SELECT JSON_ARRAYAGG(JSON_OBJECT('AAA28',patient_medical_info.AAA28,'ABA01C',patient_medical_info.ABA01C,'ABA01N',patient_medical_info.ABA01N,'ABC03C',ABC03C,'ABF01C',ABF01C,'ABF01N',ABF01N,'ABF04',ABF04,'ABF02C',ABF02C,'ABF03C',ABF03C,'ABH01C',ABH01C,'ABH0201C',ABH0201C,'ABH0202C',ABH0202C,'ABH0203C',ABH0203C,'ABH03C',ABH03C,'AEB02C',AEB02C,'AEB01',AEB01,'AED01C',AED01C,'AEG01C',AEG01C,'AEG02C',AEG02C,'AEG04',AEG04,'AEG05',AEG05,'AEG06',AEG06,'AEG07',AEG07,'AEG08',AEG08,'AEJ01',AEJ01,'AEJ02',AEJ02,'AEJ03',AEJ03,'AEJ04',AEJ04,'AEJ05',AEJ05,'AEJ06',AEJ06,'AEL01',AEL01,'AEN02C',AEN02C,'AEN02N',AEN02N,'AEI09',AEI09,'AEI10',AEI10,'AEI08',AEI08)) FROM patient_medical_info where AAA28 = $zyh");
        return $data;
    }
    // 获取 secondary_operation 病案首页(其他手术)
    public function getSecondaryOperation($zyh){
        $data = SecondaryOperation::query()->whereIn('AAA28',$zyh)->get(['AAA28','ICD9_ID1','ICD9_NAME','OPE_DATE','OPE_MAN_NAME','OPE_MAN_CODE','FRIST_ASSISTANT_CODE','FRIST_ASSISTANT_NAME','SECOND_ASSISTANT_CODE','SECOND_ASSISTANT_NAME','HOCUS_WAY_ID','INCISION_GRADE_ID','HOCUS_MAN_CODE','HOCUS_MAN_NAME','START_TIME','END_TIME','OPE_ORDER','OPE_LEVEL','RJSS','AREA_ID','BATCH_ID','OPE_TYPE','SSPB','HEAL_ID'])->toArray();
        //$data = DB::select("select JSON_ARRAYAGG(JSON_OBJECT('AAA28',secondary_operation.AAA28,'ICD9_ID1',secondary_operation.ICD9_ID1,'ICD9_NAME',secondary_operation.ICD9_NAME,'OPE_DATE',secondary_operation.OPE_DATE,'OPE_MAN_NAME',OPE_MAN_NAME,'OPE_MAN_CODE',OPE_MAN_CODE,'FRIST_ASSISTANT_CODE',FRIST_ASSISTANT_CODE,'FRIST_ASSISTANT_NAME',FRIST_ASSISTANT_NAME,'SECOND_ASSISTANT_CODE',SECOND_ASSISTANT_CODE,'SECOND_ASSISTANT_NAME',SECOND_ASSISTANT_NAME,'HOCUS_WAY_ID',HOCUS_WAY_ID,'INCISION_GRADE_ID',INCISION_GRADE_ID,'HOCUS_MAN_CODE',HOCUS_MAN_CODE,'HOCUS_MAN_NAME',HOCUS_MAN_NAME,'START_TIME',START_TIME,'END_TIME',END_TIME,'OPE_ORDER',OPE_ORDER,'OPE_LEVEL',OPE_LEVEL,'RJSS',RJSS,'AREA_ID',AREA_ID,'BATCH_ID',BATCH_ID,'OPE_TYPE',OPE_TYPE,'SSPB',SSPB,'HEAL_ID',HEAL_ID)) pacs from secondary_operation where AAA28 = $zyh");
        return $data;
    }
}

