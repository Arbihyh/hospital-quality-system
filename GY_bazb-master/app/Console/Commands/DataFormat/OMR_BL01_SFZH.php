<?php

namespace App\Console\Commands\DataFormat;

use App\Model\MS_BRDA;
use App\Model\OMR_BL01;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;

class OMR_BL01_SFZH extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:omr_bl01_sfzh {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'omr_bl01表身份证号处理';

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
        $this->info('omr_bl01表身份证号处理 - 数据开始处理');

        $page = (int)$this->argument('page') ?: 1;
        $this->omrBl01Format($page);

        $this->info('omr_bl01表身份证号处理 - 数据处理完毕');
    }

    /**
     * @param $page
     * @return true
     */
    protected function omrBl01Format($page)
    {
        $msBrdaService = new ElasticsearchService('ms_brda_2023');
        while (true) {
            $data = OMR_BL01::query()
                ->where('CJSJ','>=','2023-06-09 11:46:10')
                ->paginate(500,['id','BRID'],'page',$page)
                ->toArray();

            if ($page == 1) {
                echo '数据总条数：'.$data['total'].PHP_EOL.'总页数：'.$data['last_page'].PHP_EOL;
            } elseif ($page > $data['last_page']) {
                // 如果当前页码大于最大页码则结束
                break;
            }
            echo $page.PHP_EOL;
            $page++;

            foreach ($data['data'] as $value) {
                $must = ['term' => ["BRID" => $value['BRID']]];
                $params = $msBrdaService->clearMust()->queryByMust($must)->getParams();
                $restful = app('es')->search($params);
                $msBrdaData = $msBrdaService->getDataByEs($restful);
                $SFZH = !empty($msBrdaData[0]) ? $msBrdaData[0][0]['SFZH'] : '';
                if (strlen($SFZH) < 15) {
                    continue;
                }
                OMR_BL01::query()->where('id','=',$value['id'])->update(['SFZH'=>$SFZH]);
            }
        }

        return true;
    }
}
