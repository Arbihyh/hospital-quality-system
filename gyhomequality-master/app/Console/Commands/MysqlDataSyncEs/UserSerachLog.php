<?php

namespace App\Console\Commands\MysqlDataSyncEs;

use App\Model\Staff;
use App\Model\User;
use App\Model\UserSearchLog;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;

class UserSerachLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:userSerachLog';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步user_serach_log数据到Es';

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
        //* * * * * docker exec homeQuality /bin/bash -c "cd /data/api && php artisan sync:userSerachLog>user_serach_log.txt &"

        $esService = new ElasticsearchService('user_search_log');

        // 查询Es中user_search_log索引最后一条记录
        $params = $esService->clearMust()->orderBy('created_at','desc')->getParams();
        $restful = app('es')->search($params);
        //$data = $esService->getDataByEs($restful);
        $lastId = !empty($restful['hits']['hits'][0]['_id']) ? $restful['hits']['hits'][0]['_id'] : 0;

        $page = 1;
        while (true) {
            // 所有符合条件的病例信息
            $data = UserSearchLog::query()
                ->where('id','>',$lastId)
                ->paginate(100, ['*'], 'page', $page)
                ->toArray();

            if ($page == 1) {
                echo '数据总条数：' . $data['total'] . PHP_EOL . '总页数：' . $data['last_page'] . PHP_EOL;
            } elseif ($page > $data['last_page']) {
                // 如果当前页码大于最大页码则结束
                break;
            }
            echo $page . PHP_EOL;
            $page++;

            if (!empty($data['data'])) {
                $insertData = [];
                foreach ($data['data'] as $key => &$val) {
                    if ($key == count($data['data'])) {
                        // 记录最后一条数据id
                        $lastId = $val['id'];
                    }

                    if (!empty($val['is_code'])) {
                        $staffInfo = Staff::query()->where('code','=',$val['user_id'])->first();
                        $val['username'] = $staffInfo->code ?? '';
                        $val['realname'] = $staffInfo->name ?? '';
                        $val['dep_id'] = $staffInfo->ksdm ?? '';
                    } else {
                        $userData = User::query()->where('id','=',$val['user_id'])->first();
                        $val['username'] = $userData->name ?? '';
                        $val['realname'] = $userData->realname ?? '';
                        $val['dep_id'] = $userData->dep_id ?? '';
                    }

                    $insertData[] = [
                        'index' => ['_id' => $val['id']]
                    ];
                    $insertData[] = [
                        'path' => $val['path'],
                        'method' => $val['method'],
                        'title' => $val['title'],
                        'type' => $val['type'],
                        'content' => $val['content'],
                        'user_id' => $val['user_id'],
                        'username' => $val['username'],
                        'user_agent' => $val['user_agent'],
                        'ip' => $val['ip'],
                        'created_at' => $val['created_at'],
                        'dep_id' => $val['dep_id'] ?? '',
                        'realname' => $val['realname'],
                        'is_code' => $val['is_code'],
                    ];
                }

                if ($insertData) {
                    // 批量写入
                    $esService->insertBatch('user_search_log',$insertData);
                }
            }
        }

        return 0;
    }
}
