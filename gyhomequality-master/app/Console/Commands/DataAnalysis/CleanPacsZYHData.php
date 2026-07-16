<?php

namespace App\Console\Commands\DataAnalysis;

use App\Model\PatientInfo;
use App\Model\PACS;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;

class CleanPacsZYHData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:cleanPacsZYH {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清洗PACS表中ZYH字段';

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
        $this->info("清洗PACS表中ZYH字段 - 开始");

        $pacsService = new ElasticsearchService('pacs');

        $page = (int)$this->argument('page') ?: 1;
        while (true) {
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->whereNotNull('MED_REC_ID')
                ->where('AAB01','!=','')
                ->where('AAC01','!=','')
                ->whereBetween('AAC01', ['2020-06-01 00:00:00', '2023-12-31 23:59:59'])
                ->paginate(500, ['AAA28', 'MED_REC_ID', 'AAB01', 'AAC01'], 'page', $page)
                ->toArray();

            if ($page == 1) {
                echo '数据总条数：' . $data['total'] . PHP_EOL . '总页数：' . $data['last_page'] . PHP_EOL;
            } elseif ($page > $data['last_page']) {
                // 如果当前页码大于最大页码则结束
                break;
            }
            echo $page . PHP_EOL;
            $page++;

            foreach ($data['data'] as $value) {
                $AAA28 = $value['AAA28'];
                $MED_REC_ID = $value['MED_REC_ID'];
                $AAB01 = $value['AAB01'];
                $AAC01 = $value['AAC01'];

                $must = [
                    ['term' => ['JZLSH' => $AAA28]],
                    ['range' => ['KDSJ' => ['gte' => $AAB01,'lte' => $AAC01]]]
                ];
                $params = $pacsService->clearMust()
                    ->queryByMustBatch($must)
                    ->paginate(1,1000)
                    ->getParams();
                $restful = app('es')->search($params);
                $pacsData = $pacsService->getDataByEs($restful);
                $pacsIdList = [];
                if (!empty($pacsData[0])) {
                    foreach ($pacsData[0] as $val) {
                        $pacsIdList[] = $val['pacs_id'];
                    }
                }

                if ($pacsIdList) {
                    PACS::query()->whereIn('id',$pacsIdList)->update(['ZYH'=>$MED_REC_ID]);
                }
            }
        }

        $this->info("清洗PACS表中ZYH字段 - 完毕");
    }
}
