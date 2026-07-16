<?php

namespace App\Console\Commands;

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
use App\Model\PatientWorkInfo;
use App\Model\SecondaryOperation;
use Illuminate\Console\Command;

class UpdateRepeatCaseNumber extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update-repeat-case-number';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '重复病案号处理脚本';

    private $ZYH_ID = [

    ];//一维数组

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
        $this->info('开始重复数据清洗脚本');
        foreach ($this->getModels() as $key => $val){

            $query = data_get($val,'model'); //获取数据库Model
            $search_field = data_get($val,'search_field'); //获取要查询的字段
            $update_field = data_get($val,'update_field '); //获取要更新的字段
            $splicing_field = data_get($val,'splicing_field'); //获取要拼接的字段

            foreach ($this->ZYH_ID as $v){
                $res = $query->where($search_field,$v)->first();
                if($res && mb_stripos($res->$update_field,'999') == false) {
                    $res->$update_field = $res->$splicing_field . '999';
                    $res->save() ? $this->info($key . "ZYH_ID::" . $v . "执行成功") : $this->info("ZYH_ID::" . $v . "执行失败");
                }
            }
        }

        $this->info('重复数据清洗脚本执行结束');
    }

    private function getModels(){
        return [
            'patient_info' => [
                'model' => new PatientInfo(),
                'search_field' => 'ZYH_ID',
                'update_field' => 'MED_REC_ID',
                'splicing_field' => 'MED_REC_ID',
            ],
            'patient_add' => [
                'model' => new PatientAdd(),
                'search_field' => 'ZYH_ID',
                'update_field' => 'AAA28',
                'splicing_field' => 'AAA28',
            ],
            'patient_address_info' => [
                'model' => new PatientAddressInfo(),
                'search_field' => 'ZYH_ID',
                'update_field' => 'AAA28',
                'splicing_field' => 'AAA28',
            ],
            'patient_contacts_info' => [
                'model' => new PatientContactsInfo(),
                'search_field' => 'ZYH_ID',
                'update_field' => 'AAA28',
                'splicing_field' => 'AAA28',
            ],
            'patient_cost_info' => [
                'model' => new PatientCostInfo(),
                'search_field' => 'ZYH_ID',
                'update_field' => 'AAA28',
                'splicing_field' => 'AAA28',
            ],
            'patient_hospital_info' => [
                'model' => new PatientHospitalInfo(),
                'search_field' => 'ZYH_ID',
                'update_field' => 'AAA28',
                'splicing_field' => 'AAA28',
            ],
            'patient_work_info' => [
                'model' => new PatientWorkInfo(),
                'search_field' => 'ZYH_ID',
                'update_field' => 'AAA28',
                'splicing_field' => 'AAA28',
            ],
            'patient_medical_info' => [
                'model' => new PatientMedicalInfo(),
                'search_field' => 'ZYH_ID',
                'update_field' => 'AAA28',
                'splicing_field' => 'AAA28',
            ],
            'patient_doctor_info' => [
                'model' => new PatientDoctorInfo(),
                'search_field' => 'ZYH_ID',
                'update_field' => 'AAA28',
                'splicing_field' => 'AAA28',
            ],
            'main_diagnosis' => [
                'model' => new MainDiagnosis(),
                'search_field' => 'ZYH_ID',
                'update_field' => 'AAA28',
                'splicing_field' => 'AAA28',
            ],
            'main_operation' => [
                'model' => new MainOperation(),
                'search_field' => 'ZYH_ID',
                'update_field' => 'AAA28',
                'splicing_field' => 'AAA28',
            ],
            'secondary_operation' => [
                'model' => new SecondaryOperation(),
                'search_field' => 'ZYH_ID',
                'update_field' => 'AAA28',
                'splicing_field' => 'AAA28',
            ],
            'other_diagnosis' => [
                'model' => new OtherDiagnosis(),
                'search_field' => 'ZYH_ID',
                'update_field' => 'AAA28',
                'splicing_field' => 'AAA28',
            ]
        ];
    }
}
