<?php

namespace App\Console\Commands;

use App\Model\Coder;
use App\Model\Error;
use App\Model\OtherDiagnosis;
use App\Model\PatientInfo;
use App\Services\BlHomeQualityService;
use App\Services\CaseQualityService;
use App\Services\ErrorRuleService;
use App\Services\MedicalRecordService;
use Illuminate\Console\Command;
use Pheanstalk\Pheanstalk;

class Test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:test';

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
        ini_set('default_socket_timeout', 24 * 60 * 60);
        $beanstalkd = Pheanstalk::create(env("BEANSTALKD") ?? 'beanstalkd')->watch('validate');
        echo 'validate' . "\n";
        while (true) {
            $job = $beanstalkd->reserve();
            $AAA28 = json_decode($job->getData(), true);
            if (!$AAA28) {
                $beanstalkd->delete($job);
                continue;
            }
            $patient_info = PatientInfo::query()->where('MED_REC_ID', $AAA28['AAA28'])->first(['MED_REC_ID', 'AAC01', 'AAA28']);
            if ($patient_info) {
                $patient_info = $patient_info->toArray();
            } else {
                continue;
            }
            if (empty($patient_info['AAC01'])) {
                $beanstalkd->delete($job);
                continue;
            }

            // 调用病案首页-病案室质控
            try{
                CaseQualityService::caseQualityBas($AAA28['AAA28']);
            }catch (\Throwable $e) {
                continue;
            }

            $beanstalkd->useTube('count')->put(json_encode(['AAA28' => $patient_info['MED_REC_ID']]));
            $beanstalkd->delete($job);
        }
    }

}
