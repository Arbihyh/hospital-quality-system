<?php

namespace App\Console\Commands;

use App\Model\YK_TYPK;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;

class YzbYpmcAnalysis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'yzb:ypmc {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '解析yzb中YZMC中的药品名称';

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
        $this->info("解析yzb中YZMC中的药品名称 - 开始");

        $page = $this->argument('page') ?: 1;
        $pageSize = 200;

        // 获取药品库中药品名称
        $ypmcList = YK_TYPK::getPymcList();

        $yzbService = new ElasticsearchService('yzb_2023');

        while (true) {
            // 所有符合条件的病例信息
            $must = [
                ['term' => ['YDYZLB' => 901]],
                ['range' => ['data_id' => ['gt' => 20676833]]],
            ];
            $params = $yzbService->clearMust()
                ->queryByMustBatch($must)
                ->paginate($page,$pageSize)
                ->trackTotalHits()
                ->getParams();
            $restful = app('es')->search($params);
            $data = $yzbService->getDataByEs($restful);
            if (empty($data[0])) {
                break;
            }

            $lastPage = (int)ceil($data[1]/$pageSize);
            if ($page == 1) {
                echo '数据总条数：'.$data[1].PHP_EOL.'每页执行条数：'.$pageSize.PHP_EOL.'总页数：'.$lastPage.PHP_EOL;
            }
            echo $page.PHP_EOL;
            $page++;

            $es_params = [];
            foreach ($data[0] as $value) {
                if (!empty($value['ypmc'])) {
                    continue;
                }
                $yzmc = $value['YZMC'];
                if (empty($yzmc)) {
                    continue;
                }
                foreach ($ypmcList as $ypmc) {
                    $stratLen = stripos($ypmc,'[');
                    if ($stratLen === 0) {
                        $ypmc = str_replace('］',']',$ypmc);
                        $stratLen = mb_stripos($ypmc,']');
                        $ypmc = mb_substr($ypmc,$stratLen+1);
                    }
                    $ypmc = str_replace('（','(',$ypmc);
                    $stratLen = stripos($ypmc,'(');
                    if ($stratLen === 0) {
                        $ypmc = str_replace('）',')',$ypmc);
                        $stratLen = mb_stripos($ypmc,')');
                        $ypmc = mb_substr($ypmc,$stratLen+1);
                    }

                    if (stripos($yzmc,$ypmc) === 0) {
                        $es_params['body'][] = ['update' => ['_index' => 'yzb_2023', '_id' => $value['data_id']]];
                        $es_params['body'][] = ['doc' => [
                            "ypmc" => $ypmc
                        ], 'doc_as_upsert' => true];

                        \App\Model\Yzb::query()->where('id','=',$value['data_id'])->update(['ypmc'=>$ypmc]);
                        break;
                    }
                }
            }

            if (!empty($es_params)) {
                app('es')->bulk($es_params);
            }
        }

        $this->info("解析yzb中YZMC中的药品名称 - 完毕");
    }
}
