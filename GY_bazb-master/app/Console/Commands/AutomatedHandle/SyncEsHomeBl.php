<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\Setting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncEsHomeBl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:home_bl {startDate?} {endDate?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $setName = 'es_index_home_bl';
        $index = 'home_bl';

        $startDate = $this->argument('startDate') ?: '';
        $endDate = $this->argument('endDate') ?: '';
        if (!empty($startDate) && !empty($endDate)) {
            $startTime = $startDate . ' 00:00:00';
            $endTime = $endDate . ' 23:59:59';
        } else {
            $date = Carbon::parse()->addDay(-1)->toDateString();
            $startTime = $date . ' 00:00:00';
            $endTime = $date . ' 23:59:59';
        }

        $page = 1;
        while (true) {
            $data = DB::select("select MED_REC_ID as id, MED_REC_ID, AAA28,AAA01,AAA02C,AAA03,AAA05C,AAC11N,AAA42,AEN01,AAA06C,AAA07,AAA08C,if(AAB01 != '',AAB01, '1970-01-01 00:00:00') AS AAB01,if(AAC01 != '', AAC01, '1970-01-01 00:00:00') AS AAC01,AAA04,AAA40,AEM01C,AAC04,if(ADA01 != '',ADA01,'0') AS ADA01,ADA0101,AAA29,AAB06C,AAA26C,ABC01N,ORG_STATE,ICD9_NAME,created_at,ATTEND_GRP_CODE, ATTEND_GRP_NAME,F_D,J,coder_id,score,is_error,ABG01N,ABG01C,source,level,is_defect FROM patient_info where AAC01 BETWEEN '{$startTime}' and '{$endTime}' limit {$page},100");

            if (empty($data)) {
                break;
            }
            $page++;

            foreach ($data as $value) {
                $ZYH = $value->MED_REC_ID;

                // 病历
                $EMR_BL_BL01Data = DB::select("SELECT JZHM,JSON_ARRAYAGG(JSON_OBJECT('JZHM',JZHM,'BLMC',BLMC,'BLLB',BLLB)) as data FROM EMR_BL_BL01 where JZHM={$ZYH} GROUP BY JZHM");
                $EMR_BL_BL01 = !empty($EMR_BL_BL01Data) ? json_decode($EMR_BL_BL01Data[0]->data,true) : [];

                // error
                $errorData = DB::select("select ZYH,JSON_ARRAYAGG(JSON_OBJECT('year',`year`,'month',`month`,'desc',`desc`,'error_field',error_field,'error_name',error_name,'level',level,'type',type,'error_type',error_type,'error_rule',error_rule,'down',down,'coder_id',coder_id,'AAC11C',AAC11C,'status',`status`,'source',source,'category',category)) as data from error where ZYH={$ZYH} GROUP BY ZYH");
                $error = !empty($errorData) ?  json_decode($errorData[0]->data,true) : [];

                // icu
                $icuData = DB::select("select AAA28,JSON_ARRAYAGG(JSON_OBJECT('AAA28',AAA28,'IS_MAIN_WAY',IS_MAIN_WAY,'IN_TIME',IN_TIME,'OUT_TIME',OUT_TIME,'AEL01',AEL01,'AREA_ID',AREA_ID,'BATCH_ID',BATCH_ID)) as data from icu where AAA28={$ZYH} GROUP BY AAA28");
                $icu = !empty($icuData) ?  json_decode($icuData[0]->data,true) : [];

                // main_diagnosis
                $mainDiagnosisData = DB::select("SELECT AAA28,JSON_ARRAYAGG(JSON_OBJECT('AAA28',AAA28,'ICD10_NAME',ICD10_NAME,'ICD10_ID1',ICD10_ID1, 'DIA_ORDER',DIA_ORDER,'AREA_ID',AREA_ID,'BATCH_ID',BATCH_ID,'LBMC',LBMC,'RYQK', CASE RYQK WHEN '有' THEN '1' WHEN '临床未确定' THEN '2' WHEN '情况不明' THEN '3' WHEN '无' THEN '4'  ELSE '4'END )) as data FROM main_diagnosis where AAA28={$ZYH} GROUP BY AAA28");
                $mainDiagnosis = !empty($mainDiagnosisData) ?  json_decode($mainDiagnosisData[0]->data,true): [];

                // main_operation
                $mainOperationData = DB::select("select AAA28,JSON_ARRAYAGG(JSON_OBJECT('AAA28',AAA28,'ICD9_ID1',ICD9_ID1,'ICD9_NAME',ICD9_NAME,'OPE_DATE',OPE_DATE,'OPE_MAN_NAME',OPE_MAN_NAME,'OPE_MAN_CODE',OPE_MAN_CODE,'FRIST_ASSISTANT_CODE',FRIST_ASSISTANT_CODE,'FRIST_ASSISTANT_NAME',FRIST_ASSISTANT_NAME,'SECOND_ASSISTANT_CODE',SECOND_ASSISTANT_CODE,'SECOND_ASSISTANT_NAME',SECOND_ASSISTANT_NAME,'HOCUS_WAY_ID',HOCUS_WAY_ID,'INCISION_GRADE_ID',INCISION_GRADE_ID,'HOCUS_MAN_CODE',HOCUS_MAN_CODE,'HOCUS_MAN_NAME',HOCUS_MAN_NAME,'START_TIME',START_TIME,'END_TIME',END_TIME,'OPE_ORDER',OPE_ORDER,'OPE_LEVEL',OPE_LEVEL,'RJSS',RJSS,'AREA_ID',AREA_ID,'BATCH_ID',BATCH_ID,'OPE_TYPE',OPE_TYPE,'SSPB',SSPB,'HEAL_ID',HEAL_ID)) as data from main_operation where AAA28={$ZYH} GROUP BY AAA28");
                $mainOperation = !empty($mainOperationData) ?  json_decode($mainOperationData[0]->data,true): [];

                // other_diagnosis
                $otherDiagnosisData = DB::select("SELECT AAA28,JSON_ARRAYAGG(JSON_OBJECT('AAA28',AAA28,'ICD10_NAME',ICD10_NAME,'ICD10_ID1',ICD10_ID1,'DIA_ORDER',DIA_ORDER,'AREA_ID',AREA_ID,'BATCH_ID',BATCH_ID,'LBMC',LBMC,'RYQK',CASE RYQK WHEN '有' THEN '1' WHEN '临床未确定' THEN '2' WHEN '情况不明' THEN '3' WHEN '无' THEN '4' ELSE '4' END)) as data FROM other_diagnosis where AAA28={$ZYH} GROUP BY AAA28");
                $otherDiagnosis = !empty($otherDiagnosisData) ?  json_decode($otherDiagnosisData[0]->data,true): [];

                // other_diagnosis
                $patientDoctorInfoData = DB::select("SELECT AAA28,JSON_ARRAYAGG(JSON_OBJECT('AAA28',AAA28,'AED02',AED02,'AED03',AED03,'AED04',AED04,'AEE01',AEE01,'AEE01_CODE',AEE01_CODE,'AEE02',AEE02,'AEE03',AEE03,'AEE11',AEE11,'AEE09',AEE09,'AEE04',AEE04,'AEE05',AEE05,'AEE07',AEE07,'AEE08',AEE08,'AEE10',AEE10,'CODE_DATE',CODE_DATE,'COMPLETION_DATE',COMPLETION_DATE,'SIGN_IN_DATE',SIGN_IN_DATE,'QUALITY_CONTROL',QUALITY_CONTROL,'AEE02_CODE',AEE02_CODE,'AEE03_CODE',AEE03_CODE,'AEE04_CODE',AEE04_CODE)) as data FROM patient_doctor_info where AAA28={$ZYH} GROUP BY AAA28");
                $patientDoctorInfo = !empty($patientDoctorInfoData) ? json_decode($patientDoctorInfoData[0]->data,true) : [];

                // patient_hospital_info
                $patientHospitalInfoData = DB::select("SELECT AAA28,JSON_ARRAYAGG(JSON_OBJECT('AAA28',AAA28,'AAA30',AAA30,'ABC01C',ABC01C,'AAA27',AAA27,'AAC001',AAC001,'AAB01',AAB01,'AAB02C',AAB02C,'AAB03',AAB03,'AAB11C',AAB11C,'AAB11N',AAB11N,'AAC02C',AAC02C,'AAC03',AAC03,'AAC11C',AAC11C,'AAD01C',AAD01C,'AEM02',AEM02,'AEM03C',AEM03C,'AEM04',AEM04,'AEI01C',AEI01C)) as data FROM patient_hospital_info where AAA28={$ZYH} GROUP BY AAA28");
                $patientHospitalInfo = !empty($patientHospitalInfoData) ?  json_decode($patientHospitalInfoData[0]->data,true): [];

                // patient_medical_info
                $patientMedicalInfoData = DB::select("SELECT AAA28,JSON_ARRAYAGG(JSON_OBJECT('AAA28',AAA28,'ABA01C',ABA01C,'ABA01N',ABA01N,'ABC03C',ABC03C,'ABF01C',ABF01C,'ABF01N',ABF01N,'ABF04',ABF04,'ABF02C',ABF02C,'ABF03C',ABF03C,'ABH01C',ABH01C,'ABH0201C',ABH0201C,'ABH0202C',ABH0202C,'ABH0203C',ABH0203C,'ABH03C',ABH03C,'AEB02C',AEB02C,'AEB01',AEB01,'AED01C',AED01C,'AEG01C',AEG01C,'AEG02C',AEG02C,'AEG04',AEG04,'AEG05',AEG05,'AEG06',AEG06,'AEG07',AEG07,'AEG08',AEG08,'AEJ01',AEJ01,'AEJ02',AEJ02,'AEJ03',AEJ03,'AEJ04',AEJ04,'AEJ05',AEJ05,'AEJ06',AEJ06,'AEL01',AEL01,'AEN02C',AEN02C,'AEN02N',AEN02N,'AEI09',AEI09,'AEI10',AEI10,'AEI08',AEI08))as data FROM patient_medical_info where AAA28={$ZYH} GROUP BY AAA28");
                $patientMedicalInfo = !empty($patientMedicalInfoData) ?   json_decode($patientMedicalInfoData[0]->data,true) : [];

                // secondary_operation
                $secondaryOperationData = DB::select("select AAA28,JSON_ARRAYAGG(JSON_OBJECT('AAA28',AAA28,'ICD9_ID1',ICD9_ID1,'ICD9_NAME',ICD9_NAME,'OPE_DATE',OPE_DATE,'OPE_MAN_NAME',OPE_MAN_NAME,'OPE_MAN_CODE',OPE_MAN_CODE,'FRIST_ASSISTANT_CODE',FRIST_ASSISTANT_CODE,'FRIST_ASSISTANT_NAME',FRIST_ASSISTANT_NAME,'SECOND_ASSISTANT_CODE',SECOND_ASSISTANT_CODE,'SECOND_ASSISTANT_NAME',SECOND_ASSISTANT_NAME,'HOCUS_WAY_ID',HOCUS_WAY_ID,'INCISION_GRADE_ID',INCISION_GRADE_ID,'HOCUS_MAN_CODE',HOCUS_MAN_CODE,'HOCUS_MAN_NAME',HOCUS_MAN_NAME,'START_TIME',START_TIME,'END_TIME',END_TIME,'OPE_ORDER',OPE_ORDER,'OPE_LEVEL',OPE_LEVEL,'RJSS',RJSS,'AREA_ID',AREA_ID,'BATCH_ID',BATCH_ID,'OPE_TYPE',OPE_TYPE,'SSPB',SSPB,'HEAL_ID',HEAL_ID)) as data from secondary_operation where AAA28={$ZYH} GROUP BY AAA28");
                $secondaryOperation = !empty($secondaryOperationData) ? json_decode($secondaryOperationData[0]->data,true)  : [];

                // 要同步到Es的数据
                $es_params = [];
                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $ZYH]];
                $es_params['body'][] = ['doc' => [
                    "id" => $value->id,
                    "MED_REC_ID" => $ZYH,
                    "AAA28" => $value->AAA28,
                    "AAA01" => $value->AAA01,
                    "AAA02C" => $value->AAA02C,
                    "AAA03" => $value->AAA03,
                    "AAA05C" => $value->AAA05C,
                    "AAC11N" => $value->AAC11N,
                    "AAA42" => $value->AAA42,
                    "AEN01" => $value->AEN01,
                    "AAA06C" => $value->AAA06C,
                    "AAA07" => $value->AAA07,
                    "AAA08C" => $value->AAA08C,
                    "AAB01" => $value->AAB01,
                    "AAC01" => $value->AAC01,
                    "AAA04" => $value->AAA04,
                    "AAA40" => $value->AAA40,
                    "AEM01C" => $value->AEM01C,
                    "AAC04" => $value->AAC04,
                    "ADA01" => $value->ADA01,
                    "ADA0101" => $value->ADA0101,
                    "AAA29" => $value->AAA29,
                    "AAB06C" => $value->AAB06C,
                    "AAA26C" => $value->AAA26C,
                    "ABC01N" => $value->ABC01N,
                    "ORG_STATE" => $value->ORG_STATE,
                    "ICD9_NAME" => $value->ICD9_NAME,
                    "created_at" => $value->created_at,
                    "ATTEND_GRP_CODE" => $value->ATTEND_GRP_CODE,
                    "ATTEND_GRP_NAME" => $value->ATTEND_GRP_NAME,
                    "F_D" => $value->F_D,
                    "J" => $value->J,
                    "coder_id" => $value->coder_id,
                    "score" => $value->score,
                    "is_error" => $value->is_error,
                    "ABG01N" => $value->ABG01N,
                    "ABG01C" => $value->ABG01C,
                    "source" => $value->source,
                    "level" => $value->level,
                    "is_defect" => $value->is_defect,
                    "error" => $error,
                    "icu" => $icu,
                    "main_diagnosis" => $mainDiagnosis,
                    "main_operation" => $mainOperation,
                    "other_diagnosis" => $otherDiagnosis,
                    "patient_doctorInfo" => $patientDoctorInfo,
                    "patient_hospital_info" => $patientHospitalInfo,
                    "patient_medical_info" => $patientMedicalInfo,
                    "secondary_operation" => $secondaryOperation,
                    "bl01" => $EMR_BL_BL01
                ], 'doc_as_upsert' => true];
                app('es')->bulk($es_params);
            }

            Setting::query()->where('name', '=', $setName)->update(['content' => date('Y-m-d', strtotime($endTime))]);
        }

        $this->info('索引：' . $index . ' 数据处理完成');
    }
}
