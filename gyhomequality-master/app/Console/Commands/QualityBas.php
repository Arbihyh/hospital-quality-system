<?php

namespace App\Console\Commands;

use App\Model\Coder;
use App\Model\Error;
use App\Model\ErrorData;
use App\Model\OtherDiagnosis;
use App\Model\PatientInfo;
use App\Services\BlHomeQualityService;
use App\Services\CaseQualityService;
use App\Services\ErrorRuleService;
use App\Services\MedicalRecordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Pheanstalk\Pheanstalk;

class QualityBas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quality:bas {start?} {end?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '首页质控-病案室-定时任务';

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
        $startTime = $this->argument('start');
        $endTime = $this->argument('end');
        if(!$startTime){
            $startTime = date('Y-m-d H:i:s', time() - 3600 * 24);
        }
        if(!$endTime){
            $endTime = date('Y-m-d H:i:s', time());
        }
        ini_set('default_socket_timeout', 24 * 60 * 60);
        // $beanstalkd = Pheanstalk::create(env("BEANSTALKD") ?? 'beanstalkd')->watch('validate');
        $patient_info = PatientInfo::query()
            ->where('AAC01', '>', $startTime)
            ->where('AAC01', '<', $endTime)
            ->select(['MED_REC_ID', 'AAC01', 'AAA28'])
            ->get()->toArray();
        foreach ($patient_info as $v) {
            // 调用病案首页-病案室质控
            try{
                CaseQualityService::caseQualityBas($v['MED_REC_ID']);
            }catch (\Throwable $e) {
                continue;
            }
            // $beanstalkd->useTube('count')->put(json_encode(['AAA28' => $v['MED_REC_ID']]));
        }

        Error::query()->select(DB::raw('COUNT(1) as num,error_rule,year,month,type'))
            ->groupBy(['error_rule','year','month','type'])
            ->get()->map(function($item){
                ErrorData::updateOrInsert([
                    'error_rule' => $item->error_rule,
                    'year' => $item->year,
                    'month' => $item->month,
                    'hospital_name' => '0001M3100000000000A7'
                ],['type' => $item->type,'count' => $item->num]);
            });

    }

}
