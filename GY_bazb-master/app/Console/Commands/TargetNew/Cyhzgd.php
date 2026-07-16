<?php

namespace App\Console\Commands\TargetNew;

use App\Model\BA_RECEIVE;
use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use App\Model\PatientInfoTargetTemporary;
use App\Model\Yzb;
use App\Services\ElasticsearchService;
use App\Services\PublicService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use zjkal\ChinaHoliday;

class Cyhzgd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'newZb:cyhzgd {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '出院患者病历2日归档率';

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
        $this->info('出院患者病历2日归档率 - 数据开始处理');

        $page = (int)$this->argument('page') ?: 1;
        $this->cyhzgdES($page);

        $this->info('出院患者病历2日归档率 - 数据处理完毕');
    }

    protected function cyhzgdEs($page)
    {
        $yzbEsService = new ElasticsearchService('yzb_2023');
        while (true) {
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->whereBetween('AAC01',['2023-01-01 00:00:00','2023-12-31 23:59:59'])
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
                $AAA28 = $value['AAA28'];
                $ZYH = $value['MED_REC_ID'];

                $baReceive = BA_RECEIVE::query()
                    ->where('bah','=',$AAA28)
                    ->whereNotNull('MaxCheckTime')
                    ->get()->toArray();

                // 查询归档时间
                $MaxCheckTime = '';
                if (!empty($baReceive)) {
                    foreach ($baReceive as $val) {
                        if ($val['cysj'] == $value['AAC01']) {
                            $MaxCheckTime = $val['MaxCheckTime'];
                            break;
                        }
                    }
                }

                // 查询医嘱 出院的 XZJDSJ
                $numerator = 0;
                $yzbCyData = $this->getYzbDataEs($yzbEsService,$ZYH,303,$MaxCheckTime);
                $cyhzgdError = '';
                if ($yzbCyData['numerator']) {
                    $numerator = $yzbCyData['numerator'];
                    $cyhzgdError = $yzbCyData['msg'];
                } else {
                    // 查询医嘱 死亡的 XZJDSJ
                    $yzbSwData = $this->getYzbDataEs($yzbEsService,$ZYH,305,$MaxCheckTime);
                    if ($yzbSwData['numerator']) {
                        $numerator = $yzbSwData['numerator'];
                        $cyhzgdError = $yzbSwData['msg'];
                    }
                }

                if (!$MaxCheckTime) {
                    $numerator = 0;
                }

                if (!$numerator) {
                    if ($yzbCyData['is_data'] == 1) {
                        $cyhzgdError = $yzbCyData['msg'];
                    } elseif ($yzbSwData['is_data'] == 1) {
                        $cyhzgdError = $yzbSwData['msg'];
                    } else {
                        $cyhzgdError = $yzbCyData['msg'];
                    }
                }

                // 记录
                $saveData = ['denominator_cyhzgd'=>1,'numerator_cyhzgd'=>$numerator,'cyhzgd_error'=>$cyhzgdError];
                PatientInfoTargetTemporary::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
            }
        }

        return true;
    }

    protected function getYzbDataEs($yzbEsService,$ZYH,$YDYZLB,$MaxCheckTime)
    {
        $publicService = new PublicService();

        $title = $YDYZLB==303 ? '出院时间' : '死亡时间';

        $must = [
            ['term' => ["ZYH" => $ZYH]],
            ['term' => ["YDYZLB" => $YDYZLB]]
        ];
        $params = $yzbEsService->clearMust()->queryByMustBatch($must)->getParams();
        $yzbRes = app('es')->search($params);
        $yzbData = $yzbEsService->getDataByEs($yzbRes);
        $XZJDSJ = $yzbData[0][0]['XZJDSJ'] ?? '';

        $numerator = 0;
        $is_data = 0;
        if ($XZJDSJ) {
            $dateTime = date("Y-m-d", strtotime($XZJDSJ));
            $is_data = 1;
            if ($MaxCheckTime) {
                $MaxCheckTime = date('Y-m-d', strtotime($MaxCheckTime));

                // 获取2日后时间（不包含节假日、休息日）
                $XZJDSJ_END = $publicService->getDay($XZJDSJ, 2);
                $XZJDSJ_END = date("Y-m-d", strtotime($XZJDSJ_END));
                if ($XZJDSJ_END >= $MaxCheckTime) {
                    $numerator = 1;
                    $cyhzgdError = $title.'【'.$dateTime.'】 归档时间【'.$MaxCheckTime.'，2个工作日内】';
                } else {
                    $cyhzgdError = $title.'【'.$dateTime.'】 归档时间【'.$MaxCheckTime.'，超过2个工作日】';
                }
            } else {
                $cyhzgdError = $title.'【'.$dateTime.'】 归档时间【无】';
            }
        } else {
            $MaxCheckTime = !empty($MaxCheckTime) ? date('Y-m-d', strtotime($MaxCheckTime)) : '无';
            $cyhzgdError = $title.'【无】 归档时间【'.$MaxCheckTime.'】';
        }

        return ['numerator'=>$numerator,'msg'=>$cyhzgdError,'is_data'=>$is_data];
    }

}
