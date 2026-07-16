<?php

namespace App\Console\Commands\Queue;

use App\Model\QueueList;
use App\Services\CaseService;
use Illuminate\Console\Command;

class QueuePacs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'queue:pacs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command PACS的消息队列消费脚本';

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
        QueueList::query()->update(['status' => 0]);
        $caseService = new CaseService();
        $moduleName = env("APP_NAME", "");
        $className = "\\App\\Services\\MysqlDataSync\\{$moduleName}\\HomeData";
        $homeDataService = new $className();

        while (true) {
            if (date("H") == "23") {
                break;
            }
            $data = QueueList::query()->where(["type" => "pacs", "status" => 0])->orderBy("id", "desc")->first();
            if (!$data) {
                sleep(10);
                continue;
            }
            $data = $data->toArray();
            echo $data["data"] . "-" . $data["created_at"] . PHP_EOL;
            // 改为消费中
            try{
                QueueList::query()->where("id", $data["id"])->update(["status" => 1]);
                $homeDataService->pacs($data["data"]);
                // 删除
                QueueList::query()->where("id", $data["id"])->delete();
            } catch (\Exception $e) {
                var_dump($e->getMessage());
                continue;
            }
        }
    }
}
