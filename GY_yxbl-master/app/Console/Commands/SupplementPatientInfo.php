<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Model\ErrorRule;
use App\Model\Department;
use App\Model\PatientInfo;
use Illuminate\Console\Command;
use App\Model\PrimaryKeyControl;
use App\Services\BasyQualityService;
use App\Services\HomeQualityService;
use App\Console\Commands\Format\BasySz;
use App\Services\DataxSync\DataSyncService;
use App\Http\Controllers\Api\CustomZKController;

class SupplementPatientInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'supplement-patient-info {startDate?} {endDate?} {zyh?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '补充patient_info表数据';

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
        $this->info('补充patient_info表数据 - 开始');
        $bmyQuality = new BmyQuality();
        $yszQuality = new YszQuality();
        $basySz = new BasySz();
        $basyQualityService = new BasyQualityService();
        $homeQualityService = new HomeQualityService();
        $depData = Department::query()->pluck('dep_id', 'dep_name')->toArray();

        // 获取质控规则
        $errorRuleData = ErrorRule::query()
            ->where('status', '=', 0)
            ->Where(function ($query) {
                $query->where('node', 'like', "%终末%")
                    ->orWhere('node', 'like', "%运行%");
            })
            ->orderBy('id')
            ->get()->toArray();
        $errorRuleData = array_column($errorRuleData, null, 'id');

        // 获取起始日期
        $startDate = $this->argument('startDate') ?: '';
        $endDate = $this->argument('endDate') ?: '';
        $zyh = $this->argument('zyh') ?: '';

        if (!empty($startDate)) {
            $currentDate = Carbon::parse($startDate);
        } else {
            // 默认从30天前开始
            $currentDate = Carbon::parse()->addDay(-30);
        }

        if (!empty($endDate)) {
            $endTime = $endDate . ' 23:59:59';
        } else {
            // 当天
            $date = Carbon::parse()->addDay(0)->toDateString();
            $endTime = $date . ' 23:59:59';
        }

        $connect = DataSyncService::getInstance(2);

        // 循环处理每一天，直到日期超过当前时间
        while ($currentDate->lte($endTime)) {
            $dayStart = $currentDate->copy()->startOfDay()->format('Y-m-d H:i:s');
            $dayEnd = $currentDate->copy()->endOfDay()->format('Y-m-d H:i:s');

            $this->info("正在同步日期: {$currentDate->toDateString()}");

            // 根据不同的APP_NAME执行不同的查询
            if (env('APP_NAME') == 'dancheng') {
                $sql = "SELECT ZYH as MED_REC_ID,ZYCS as AAA29,patient_id,"
                    . "TO_CHAR(RYSJ, 'YYYY-MM-DD HH24:MI:SS') as AAB01, "
                    . "TO_CHAR(CYSJ, 'YYYY-MM-DD HH24:MI:SS') as AAC01 "
                    . "from NEIHANZK_QM.ZY_BASY  WHERE CYSJ BETWEEN "
                    . "TO_DATE('{$dayStart}', 'yyyy-MM-dd HH24:mi:ss') AND "
                    . "TO_DATE('{$dayEnd}', 'yyyy-MM-dd HH24:mi:ss')";
                if ($zyh) {
                    $patientInfo = PatientInfo::query()->where('MED_REC_ID', $zyh)->first();
                    if ($patientInfo) {
                        $sql .= " AND PATIENT_ID = '{$patientInfo->AAA28}' AND ZYCS={$patientInfo->AAA29}";
                    }
                }
                $patientInfo = $connect->setSql($sql)->getResult();
            } elseif (env('APP_NAME') == 'laizhou') {
                $sql = "SELECT TPATIENTVISIT.FPRN AS MED_REC_ID,"
                    . "FORMAT(DATEADD(SECOND, DATEDIFF(SECOND, 0, TRY_CAST(FCYTIME AS TIME)), FCYDATE), 'yyyy-MM-dd HH:mm:ss') AS AAC01 "
                    . "FROM BAGL.dbo.TPATIENTVISIT LEFT JOIN TDIAGNOSE ON "
                    . "TPATIENTVISIT.FPRN = TDIAGNOSE.FPRN where FCYDATE>='{$dayStart}' "
                    . "and FCYDATE<='{$dayEnd}'";
                $patientInfo = $connect->setSql($sql)->getResult();
            } else {
                $field = PrimaryKeyControl::query()
                    ->where('tableName', '=', 'patient_info.AAC01')
                    ->first()->toArray()['field'];
                $patientInfo = $connect->setByNameSql('patient_info', 1)
                    ->setTime($field, "'" . $dayStart . "'", "'" . $dayEnd . "'")
                    ->getResult();
            }

            if (!empty($zyh)) {
                $patientInfo = array_filter($patientInfo, function ($value) use ($zyh) {
                    return $value['MED_REC_ID'] == $zyh;
                });
            }

            $dayCount = count($patientInfo);
            $this->info("日期 {$currentDate->toDateString()} 共 {$dayCount} 条数据");

            // 处理当天的数据
            foreach ($patientInfo as $value) {
                $ZYH = $value['MED_REC_ID'];
                $exists = PatientInfo::query()->where('MED_REC_ID', $ZYH)->where('IS_CATA', 1)->exists();
                if ($exists) {
                    continue;
                }
                echo 'start ------------------------------------' . PHP_EOL;
                echo $ZYH . " 出院时间:" . $value['AAC01'] . PHP_EOL;
                $content = $bmyQuality->zkInfo($value); // 获取质控数据

                if (isset($content['data']) && !empty($content['data'])) {
                    $basyQualityService->qualityContrl($content, $errorRuleData); //处理质控数据
                    echo $value['MED_REC_ID'] . "编码员质控完成" . date("Y-m-d H:i:s") . "\n";
                } else {
                    echo $value['MED_REC_ID'] . "没有质控数据" . date("Y-m-d H:i:s") . "\n";
                }

                $content = $yszQuality->zkInfo($ZYH); // 获取质控数据
                if (isset($content) && !empty($content)) {
                    $content['ZYH'] = $ZYH;
                    // 判断是否为空
                    $content['RELATION_FIELD'] = $content['ZYH'];

                    // 主表
                    $patientInfoV2Id = $basySz->addPatientInfoV2($content, $depData);
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
                    $this->info("住院号: {$ZYH} 补充V2数据完成");
                } else {
                    $this->error("住院号: {$ZYH} 补充V2数据未完成");
                }

                // 处理质控数据
                $homeQualityService->qualityContrl($ZYH, $content, $errorRuleData, $depData);
                // 执行自定义质控
                if (class_exists(CustomZKController::class)) {
                    $customZK = new CustomZKController();
                    $customZK->customizeRule($ZYH, $content, 2);
                }
                echo 'end ------------------------------------' . PHP_EOL;
            }

            // 移动到下一天
            $currentDate->addDay();
        }

        $this->info('补充patient_info表数据 - 完成');
        return 0;
    }
}
