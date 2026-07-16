<?php

namespace App\Console\Commands;

use App\Model\Coder;
use App\Model\Error;
use App\Model\OtherDiagnosis;
use App\Services\ErrorRuleService;
use App\Services\MedicalRecordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Pheanstalk\Pheanstalk;

class OperationFollow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:operation-follow';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '根据收费明细质控遗漏的手术';

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
        Log::info('command:operation-follow', []);
        //\App\Services\OperationFollow::follow(646899);exit;
        $beanstalkd = Pheanstalk::create(env('BEANSTALKD') ?? 'localhost')->watch('patient_follow');

        while (true) {
            $job = $beanstalkd->reserve();
            $AAA28 = (array)json_decode($job->getData(), true);
            Log::info('command:operation-follow', $AAA28);
            if (!$AAA28) {
                $beanstalkd->delete($job);
                continue;
            }
            \App\Services\OperationFollow::follow($AAA28['AAA28']);
            //Pheanstalk::create(env('BEANSTALKD') ?? 'localhost')->useTube('count')->put(json_encode(['AAA28' => $AAA28['AAA28']]));
            //$data = MedicalRecordService::getData($AAA28['AAA28']);

            //Cache::put('error_'.$data['MED_REC_ID'],$insetData,600);
            //$beanstalkd->useTube()->put(json_encode(['AAA28' => $data['MED_REC_ID']]));
            $beanstalkd->delete($job);
        }
    }
}
