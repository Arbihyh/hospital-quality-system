<?php

namespace App\Console\Commands\TargetNew;

use App\Model\PatientInfo;
use App\Model\PatientInfoTargetTemporary;
use App\Services\ElasticsearchService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class Basy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'newZb:basy {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '病案首页24小时内完成率 - 数据处理';

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
        $this->info('病案首页24小时内完成率 - 开始处理');

        $page = (int)$this->argument('page') ?: 1;
        $this->basyDataHandle($page);

        $this->info('病案首页24小时内完成率 - 处理完毕');
    }

    protected function basyDataHandle($page)
    {
        $bl01NewService = new ElasticsearchService('emr_bl_bl01_new');
        $yzbService = new ElasticsearchService('yzb_2023');

        while (true) {
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->whereBetween('AAC01',['2022-01-01 00:00:00','2023-12-31 23:59:59'])
                ->paginate(500, ['AAA28','MED_REC_ID','AAB01','AAC01'],'page', $page)
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
                $ZYH = $value['MED_REC_ID'];

                // 分母 查询出院记录创建时间
                $cjsj = $this->bl01NewData($bl01NewService,$ZYH,2000001);

                // 分子 医嘱查询
                $xzjdsjList = $this->yzbData($yzbService,$ZYH,[303,305]);

                $numerator = 0;
                $basyError = '';
                if ($xzjdsjList) {
                    foreach ($xzjdsjList as $xzjdsj) {
                        if ($cjsj) {
                            $xzjdsjEnd = Carbon::parse($xzjdsj)->addDay(1)->toDateTimeString();
//                            if ($xzjdsj <= $cjsj && $cjsj <= $xzjdsjEnd) {
                            if ($cjsj <= $xzjdsjEnd) {
                                $numerator = 1;
                                $basyError = '出院时间【'.$xzjdsj.'】，病案首页【'.$cjsj.'（24小时内）】';
                                break;
                            } else {
                                $basyError = '出院时间【'.$xzjdsj.'】，病案首页【'.$cjsj.'（创建时间超24小时）】';
                            }
                        } else {
                            $basyError = '出院时间【'.$xzjdsj.'】，病案首页【无】';
                        }
                    }
                } else {
                    $str = $cjsj ?? '无';
                    $basyError = '出院时间【无】，病案首页【'.$str.'】';
                }

                // 记录
                $saveData = ['denominator_basy'=>1,'numerator_basy'=>$numerator,'basy_error'=>$basyError];
                PatientInfoTargetTemporary::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
            }
        }

        return true;
    }

    protected function bl01NewData($bl01NewService,$ZYH,$bllb)
    {
        $must = [
            ["term" => ['JZHM' => $ZYH]],
            ["term" => ['BLLB' => $bllb]]
        ];
        $notMust = [
            ["term" => ['BLZT' => 9]],
        ];
        $params = $bl01NewService->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNotBatch($notMust)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01NewData = $bl01NewService->getDataByEs($restful);
        $cjsj = !empty($bl01NewData[0]) ? $bl01NewData[0][0]['CJSJ'] : '';

        return $cjsj;
    }

    protected function yzbData($yzbService,$ZYH,$YDYZLB)
    {
        $must = [
            ["term" => ['ZYH' => $ZYH]]
        ];
        foreach ($YDYZLB as $val) {
            $should[] = [
                "term" => ['YDYZLB' => $val]
            ];
        }
        $params = $yzbService->clearMust()
            ->queryByMustBatch($must)
            ->queryByShouldBatch($should)
            ->minimumShouldMatch(1)
            ->getParams();
        $restful = app('es')->search($params);
        $yzbData = $yzbService->getDataByEs($restful);
        $xzjdsjList = [];
        if (!empty($yzbData[0])) {
            foreach ($yzbData[0] as $val) {
                if (!in_array($val['XZJDSJ'],$xzjdsjList)) {
                    $xzjdsjList[] = $val['XZJDSJ'];
                }
            }
        }

        return $xzjdsjList;
    }

}
