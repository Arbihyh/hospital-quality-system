<?php

namespace App\Console\Commands;

use App\Model\CaseQuality;
use App\Model\Staff;
use Illuminate\Console\Command;
use App\Services\CaseService;
use Illuminate\Support\Facades\Log;
use mysql_xdevapi\Exception;
use Pheanstalk\Pheanstalk;

class CaseMq extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'case_mq';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 定时生成执行相关诊断信息的统计信息';

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
     *
     */
    public function handle()
    {

        echo 'case_mq start';
        $beanstalkd = Pheanstalk::create(env('BEANSTALKD') ?? 'localhost')->watch('case');
        while (1) {
            $job = $beanstalkd->reserveWithTimeout(1);
            if ($job !== null) {
                $blbh = intval($job->getData()); // 获取病例编号
                if (!$blbh) {
                    $beanstalkd->delete($job);
                    Log::error("消息队列获取病例编号失败");
                    continue;
                }
                $errorNotice = CaseService::checkCase($blbh);
                if (!$errorNotice) {
                    $beanstalkd->delete($job);
                    Log::error($blbh . "病例质控结果返回失败");
                    continue;
                }

                $res = CaseQuality::addData($errorNotice);
                if ($res) {
                    Log::info($blbh . "病例质控结果添加成功");
                    $beanstalkd->delete($job);
                } else {
                    $beanstalkd->release($job);
                }
            }
            sleep(1);
        }

        echo 'case_mq end';
    }

    public function sendMq()
    {

        ini_set('default_socket_timeout', 24 * 60 * 60);
        $beanstalkd = Pheanstalk::create(env('BEANSTALKD') ?? 'localhost');
        $job = $beanstalkd->useTube('case')->put('this is my 2nd job !');

        var_dump($job);
    }
}
