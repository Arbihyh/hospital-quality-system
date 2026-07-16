<?php

namespace App\Console\Commands\Queue;

use App\Model\QueueList;
use Illuminate\Console\Command;

class QueueConsume extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:consume {type : 队列类型，会调用 HomeData 中 add + type 的方法}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 统一消息队列消费脚本';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $type = trim($this->argument('type'));
        if ($type === '') {
            $this->error('队列类型不能为空');

            return 1;
        }

        $methodName = $this->buildMethodName($type);
        $moduleName = env('APP_NAME', '');
        $className = "\\App\\Services\\MysqlDataSync\\{$moduleName}\\HomeData";

        if (!class_exists($className)) {
            $this->error("未找到数据同步服务：{$className}");

            return 1;
        }

        $homeDataService = new $className();
        if (!method_exists($homeDataService, $methodName)) {
            $this->error("未找到队列处理方法：{$className}::{$methodName}");

            return 1;
        }

        QueueList::query()->where('type', $type)->update(['status' => 0]);

        while (true) {
            if (date('H:i') >= '23:55') {
                echo '到达队列消费停止时间：' . date('Y-m-d H:i:s') . PHP_EOL;
                break;
            }

            $data = QueueList::query()
                ->where(['type' => $type, 'status' => 0])
                ->orderBy('id', 'desc')
                ->first();

            if (!$data) {
                sleep(10);
                continue;
            }

            $data = $data->toArray();
            echo $data['data'] . '-' . $data['created_at'] . PHP_EOL;

            // 改为消费中
            QueueList::query()->where('id', $data['id'])->update(['status' => 1]);
            $homeDataService->{$methodName}($data['data']);
            QueueList::query()->where('id', $data['id'])->delete();
        }

        return 0;
    }

    /**
     * 根据队列类型生成 HomeData 的处理方法名。
     *
     * @param string $type
     * @return string
     */
    private function buildMethodName($type)
    {
        $methodSuffix = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $type)));

        return 'add' . $methodSuffix;
    }
}
