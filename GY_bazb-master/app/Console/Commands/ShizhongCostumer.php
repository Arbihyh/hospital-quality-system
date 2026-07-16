<?php

namespace App\Console\Commands;

use App\Model\QueueList;
use App\Model\DataSyncLog;
use App\Services\CaseService;
use Illuminate\Console\Command;

class ShizhongCostumer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'cos:shizhong';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 事中质控的消息队列消费脚本';

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
        QueueList::query()->update(['status'=>0]);
        $caseService = new CaseService();
        while (true) {
            if (date("H") == "23") {
                break;
            }
            $data = QueueList::query()->where(["type" => "analysis", "status" => 0])->orderBy("id", "desc")->first();
            if (!$data) {
                sleep(10);
                continue;
            }
            $data = $data->toArray();
            echo $data["data"] . "-" . $data["created_at"] . PHP_EOL;
            // 改为消费中
            QueueList::query()->where("id", $data["id"])->update(["status" => 1]);
            try {
                echo ('-------------质控开始时间：' . date("Y-m-d H:i:s")) . PHP_EOL;
                DataSyncLog::addData(['zyh'=>$data["data"], 'content'=>'1、消息队列质控开始']);
                $caseService->qualityContrlV2('', '', [$data["data"]], 1);
                echo ('-------------质控结束时间：' . date("Y-m-d H:i:s")) . PHP_EOL;
                DataSyncLog::addData(['zyh'=>$data["data"], 'content'=>'消息队列质控结束']);
                $caseService->qualityContrlV2('', '', [$data["data"]], 4);
                QueueList::query()->where("id", $data["id"])->delete();
            } catch (\Throwable $e) {
                DataSyncLog::addData(['zyh'=>$data["data"], 'content'=>'消息队列质控失败：'.$e->getMessage()]);
                echo $e->getMessage() . '，所在行：' . $e->getLine() . PHP_EOL;
            }

        }
    }

}
