<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Model\GY_SJQX;
use App\Model\Department;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Console\Commands\Format\BasySz;

class SupplementPatientInfoV2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'supplement-patient-info-v2 {startDate?} {endDate?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '补充patient_info_v2表数据';

    protected $addressRegexp;
    protected $ageRegexp;
    protected $ageYcRegexp;
    protected $basySz;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->addressRegexp = GY_SJQX::query()->whereIn('id', ['4041', '4042', '4011', '4012', '4021', '4022', '4023', '4031', '4032', '4033'])->pluck('keyword', 'id')->toArray();
        $this->ageRegexp = [1 => '/(\d+)岁/', 2 => '/(\d+)月/'];
        $this->ageYcRegexp = [1 => '/(.*?(岁))/u', 2 => '/(.*?(月))/u'];
        $this->basySz = new BasySz();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('运行时质控 - 开始');
        $yszQuality = new YszQuality();
        $basySz = new BasySz();
        // 获取质控时间
        $startDate = $this->argument('startDate') ?: '';
        $endDate = $this->argument('endDate') ?: '';
        
        if (!empty($startDate)) {
            $startTime = $startDate . ' 00:00:00';
        } else {
            // 前一天
            $date = Carbon::parse()->addDay(-30)->toDateString();
            $startTime = $date . ' 00:00:00';
        }
        
        if (!empty($endDate)) {
            $endTime = $endDate . ' 23:59:59';
        } else {
            // 当天
            $date = Carbon::parse()->addDay(0)->toDateString();
            $endTime = $date . ' 23:59:59';
        }
        $depData = Department::query()->pluck('dep_id', 'dep_name')->toArray();

        // 从PatientInfo表中获取数据
        $sql = 'SELECT MED_REC_ID,AAB01 FROM patient_info where MED_REC_ID not in (select ZYH from patient_info_v2 where AAC01<>"") and AAB01>="'.$startTime.'" and AAB01<="'.$endTime.'" ORDER BY id desc';
        echo $sql . "\n";
        $patientData = DB::select($sql);
        echo '总条数: ' . count($patientData) . "\n";
        foreach ($patientData as $value) {
            $ZYH = $value->MED_REC_ID;
            echo $ZYH . " 入院时间:" . $value->AAB01 . "\n";
            $content = $yszQuality->zkInfo($ZYH); // 获取质控数据
            if (isset($content) && !empty($content)) {
                $content['ZYH'] = $ZYH;
                // 判断是否为空
                $content['RELATION_FIELD'] = $content['ZYH'];

                // 主表
                $patientInfoV2Id = $basySz->addPatientInfoV2($content, $depData);
                echo $patientInfoV2Id . "\n";

                // 费用
                $basySz->addPatientInfoCostV2($patientInfoV2Id, $content);

                // 诊断
                $basySz->addPatientInfoDiagnosisV2($patientInfoV2Id, $content);

                // 手术
                $basySz->addPatientInfoOperationV2($patientInfoV2Id, $content);

                // 重症
                $basySz->addPatientInfoIcuV2($patientInfoV2Id, $content);

                // 费用明细
                if (!empty($patientInfoV2Id)) {
                    $basySz->addFeeDetailed($ZYH, $patientInfoV2Id, json_encode($content['fee_detailed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                }
            } else {
                echo '未找到质控数据,住院号: ' . $ZYH . "\n";
            }
        }

        return 0;
    }

}