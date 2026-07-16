<?php

namespace App\Console\Commands\TargetNew;

use App\Model\PatientInfo;
use App\Model\PatientInfoTargetTemporary;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;

class Cyjl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'newZb:cyjl {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '出院记录24小时内完成率 - 数据处理';

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
        $this->info('出院记录24小时内完成率 - 开始处理');

        $page = (int)$this->argument('page') ?: 1;
        $this->cyjlDataHandle($page);

        $this->info('出院记录24小时内完成率 - 处理完毕');
    }

    /**
     * 出院记录24小时内完成率
     * @param $page
     * @return true
     */
    protected function cyjlDataHandle($page)
    {
        $bl01Service = new ElasticsearchService('bl01_202303');
        $yzbService = new ElasticsearchService('yzb_2023');

        while (true) {
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->leftJoin('patient_info_target','patient_info.MED_REC_ID','=','patient_info_target.ZYH')
                ->whereBetween('AAC01',['2022-01-01 00:00:00','2023-12-31 23:59:59'])
                ->paginate(500, ['AAA28','MED_REC_ID','ZYH','AAB01','AAC01'],'page', $page)
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

                // 查询出院记录
                $cyjlInfo1 = $this->getCyjlError($bl01Service,$yzbService,$ZYH,1,303);
                $cyjlInfo2 = [];
                $cyjlInfo3 = [];
                if (!$cyjlInfo1['numerator']) {
                    // 24小时记录类
                    $cyjlInfo2 = $this->getCyjlError($bl01Service,$yzbService,$ZYH,18,303);
                    if (!$cyjlInfo2['numerator']) {
                        // 查询死亡记录
                        $cyjlInfo3 = $this->getCyjlError($bl01Service,$yzbService,$ZYH,288,305);
                    }
                }

                if (!empty($cyjlInfo1['numerator'])) {
                    $numerator = $cyjlInfo1['numerator'];
                    $cyjlError = $cyjlInfo1['cyjl_error'];
                } elseif (!empty($cyjlInfo2['numerator'])) {
                    $numerator = $cyjlInfo2['numerator'];
                    $cyjlError = $cyjlInfo2['cyjl_error'];
                } elseif (!empty($cyjlInfo3['numerator'])) {
                    $numerator = $cyjlInfo3['numerator'];
                    $cyjlError = $cyjlInfo3['cyjl_error'];
                } elseif (!empty($cyjlInfo1['cjsj'])) {
                    $numerator = $cyjlInfo1['numerator'];
                    $cyjlError = $cyjlInfo1['cyjl_error'];
                } elseif (!empty($cyjlInfo2['cjsj'])) {
                    $numerator = $cyjlInfo2['numerator'];
                    $cyjlError = $cyjlInfo2['cyjl_error'];
                } elseif (!empty($cyjlInfo3['cjsj'])) {
                    $numerator = $cyjlInfo3['numerator'];
                    $cyjlError = $cyjlInfo3['cyjl_error'];
                }

                // 记录
                $saveData = ['denominator_cyjl'=>1,'numerator_cyjl'=>$numerator,'cyjl_error'=>$cyjlError];
                PatientInfoTargetTemporary::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
            }
        }

        return true;
    }

    protected function getCyjlError($bl01Service,$yzbService,$ZYH,$BLLB,$YDYZLB)
    {
        $title = $BLLB==288 ? '出院时间（死亡）' : '出院时间';
        $bllb = [1=>'出院记录',18=>'24小时内记录类',288=>'死亡记录'];

        // 分子 - 查询出院记录创建时间
        $must = [
            ["term" => ['JZHM' => $ZYH]],
            ["term" => ['BLLB' => $BLLB]]
        ];
        $notMust = ['term' => ["BLZT" => 9]];
        $params = $bl01Service->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNot($notMust)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);
        $cjsj = !empty($bl01Data[0][0]['CJSJ']) ? $bl01Data[0][0]['CJSJ'] : '';

        // 分子 - 查询医嘱表 XZJDSJ
        $must = [
            ["term" => ['ZYH' => $ZYH]],
            ["term" => ['YDYZLB' => $YDYZLB]]
        ];
        $params = $yzbService->clearMust()
            ->queryByMustBatch($must)
            ->paginate(1,10000)
            ->getParams();
        $restful = app('es')->search($params);
        $yzbData = $yzbService->getDataByEs($restful);
        $xzjdsjList = [];
        if (!empty($yzbData[0])) {
            foreach ($yzbData[0] as $value) {
                if (!in_array($value['XZJDSJ'],$xzjdsjList)) {
                    $xzjdsjList[] = $value['XZJDSJ'];
                }
            }
        }

        $numerator = 0;
        if ($xzjdsjList) {
            $cyjlError = '';
            foreach ($xzjdsjList as $xzjdsj) {
                if ($cjsj) {
                    $xzjdsjEnd = date('Y-m-d H:i:s',strtotime($xzjdsj)+(3600*24));
//                    if ($xzjdsj < $cjsj && $cjsj < $xzjdsjEnd ) {
                    if ($cjsj < $xzjdsjEnd) {
                        $numerator = 1;
                        $cyjlError = $title.'【'.$xzjdsj.'】'.$bllb[$BLLB].'【'.$cjsj.'（24小时内）】';
                        break;
                    } else {
                        $cyjlError = $title.'【'.$xzjdsj.'】'.$bllb[$BLLB].'【'.$cjsj.'（创建时间超24小时）】';
                    }
                } else {
                    $cyjlError = $title.'【'.$xzjdsj.'】'.$bllb[$BLLB].'【无】';
                }
            }
        } else {
            $cyjlError = $title.'【无】';
            if ($cjsj) {
                $cyjlError .= $bllb[$BLLB].'【'.$cjsj.'】';
            } else {
                $cyjlError .= $bllb[$BLLB].'【无】';
            }
        }

        return ['numerator'=>$numerator,'cyjl_error'=>$cyjlError,'cjsj'=>$cjsj];
    }
}
