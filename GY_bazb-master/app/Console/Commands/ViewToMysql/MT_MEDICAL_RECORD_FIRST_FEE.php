<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BL01_back;
use App\Model\EMR_BL_BLXG;
use App\Model\EMR_BL_BLXG_back;
use App\Model\PatientCostInfo;
use Illuminate\Console\Command;

class MT_MEDICAL_RECORD_FIRST_FEE extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:mt_medical_record_first_fee {start} {end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '费用明细 视图增量同步到mysql';

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
        ini_set('default_socket_timeout', 0);
        $star = $this->argument('start');
        $end = $this->argument('end');
        while (true){
            echo $star."\n";
            if ($star > $end) {
                break;
            }

            $con = oci_connect('zdyh', 'emr#2023', '172.16.9.8:1521/ODS', 'UTF8');
            if (!$con) {
                $e = oci_error();
                print_r(htmlentities($e['message'])) . "\n";
                die;
            }

            $sql = "SELECT a.*,to_char (SUBMISSION_TIME,'yyyy-mm-dd hh24:mi:ss') AS SUBMISSION_TIME,to_char (SAVE_TIME,'yyyy-mm-dd hh24:mi:ss') AS SAVE_TIME,to_char (DISCHARGE_TIME,'yyyy-mm-dd hh24:mi:ss') AS DISCHARGE_TIME,to_char (ADMISSION_TIME,'yyyy-mm-dd hh24:mi:ss') AS ADMISSION_TIME FROM PORTAL_HIS.MT_MEDICAL_RECORD_FIRST_PAT a WHERE (SAVE_TIME BETWEEN TO_DATE ('".$star." 00:00:00','yyyy-MM-dd HH24:mi:ss') AND TO_DATE ('".$star." 23:59:59','yyyy-MM-dd HH24:mi:ss')) OR (SUBMISSION_TIME BETWEEN TO_DATE ('".$star." 00:00:00','yyyy-MM-dd HH24:mi:ss') AND TO_DATE ('".$star." 23:59:59','yyyy-MM-dd HH24:mi:ss'))";
            $result = oci_parse($con, $sql);
            oci_execute($result,OCI_DEFAULT);
            $data = [];
            while ($row = oci_fetch_assoc($result)) {
                $data[] = $row;
            }
            if (empty($data)){
                break;
            }
            /**
             * `AAE040` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '结算时间',
            `D20X02` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '其中：手术费',
            `D21` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '康复费',
            `D22` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '中医治疗费',
            `D23` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '西药费',
            `D23X01` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '其中：抗菌药物费',
            `D24` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '中成药费',
            `D25` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '中草药费',
            `D26` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '血费',
            `D27` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '白蛋白类制品费',
            `D28` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '球蛋白类制品费',
            `D29` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '凝血因子类制品费',
            `D30` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '细胞因子类制品费',
            `D31` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '检查用一次性医用材料费',
            `D32` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '治疗用一次性医用材料费',
            `D33` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '手术用一次性医用材料费',
            `D34` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '其他费',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
            `AAC01` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
            PRIMARY KEY (`id`) USING BTREE,
             */
            $costData = [];
            foreach ($data as $item){
                $costData = [
                    'AAA28'=>$item['case_no'],
                    'ADA0101'=>$item['patient_pays'],
                    'AAE040'=>$item['patient_pays'],    //结算时间
                    'D11'=>$item['medical_service_fee'],
                    'D12'=>$item['treatmen_fee'],
                    'D13'=>$item['nursing_fee'],
                    'D14'=>$item['other_services'],
                    'D15'=>$item['pathological_diagnosis_fee'],
                    'D16'=>$item['laboratory_diagnosis_fee'],
                    'D17'=>$item['Imaging_diagnosis_fee'],
                    'D18'=>$item['clinical_diagnosis_project_fee'],
                    'D19'=>$item['non_operative_treatment_fee'],
                    'D19X01'=>$item['clinical_physical_therapy_fee'],
                    'D20'=>$item['operation_fee'],
                    'D20X01'=>$item['operation_anesthesia_fee'],
                    'D20X02'=>$item['operation_anesthesia_fee'],
                    'D21'=>$item['rehabilitation_fee'],
                    'D22'=>$item['tcm_treatment_fee'],
                    'D23'=>$item['medicine_fee'],
                    'D23X01'=>$item['antibacterial_drug_fee'],
                    'D24'=>$item['proprietary_medicine_fee'],
                    'D25'=>$item['herbs_fee'],
                    'D26'=>$item['blood transfusion fee'],
                    'D27'=>$item['albumin_fee'],
                    'D28'=>$item['globulin_fee'],
                    'D29'=>$item['blood_coagulation_factor_fee'],
                    'D30'=>$item['cytokine_fee'],
                    'D31'=>$item['exam_disposable_supply_fee'],
                    'D32'=>$item['treat_disposable_supply_fee'],
                    'D33'=>$item['surgery_disposable_supply_fee'],
                    'D34'=>$item['other_fee'],
                ];

                PatientCostInfo::query()->updateOrInsert(['AAA28'=>$costData['AAA28']],$costData);
            }
            $star = date('Y-m-d',strtotime($star)+86400);
        }
        return 0;
    }
}
