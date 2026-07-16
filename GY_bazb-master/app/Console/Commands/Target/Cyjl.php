<?php

namespace App\Console\Commands\Target;

use App\Model\EMR_BL_BL01;
use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use App\Model\Yzb;
use App\Model\ZbBagl;
use App\Services\ElasticsearchService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Cyjl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zb:cyjl {page?}';

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
        $cyzs = ZbBagl::getFirstById(1,true); //出院总数 分母
        $cysj = ZbBagl::getFirstById(6,true); //出院时间 分母
        $swsj = ZbBagl::getFirstById(7,true); //死亡时间
        $cyjlsj = ZbBagl::getFirstById(8,true); //出院记录完成时间
        $cyswjlbllb = ZbBagl::getFirstById(9,true); //出院记录、24小时内入出院记录、死亡记录BLLB
        $cyjlwcsj = ZbBagl::getFirstById(10,true); //出院记录、24小时内入出院记录、死亡记录BLLB

        $bllbs = explode(',',$cyswjlbllb->keyword);


        $bl01Service = new ElasticsearchService($cyjlsj->table_name);
        $yzbService = new ElasticsearchService($cysj->table_name);
        $conf = config('confAdmin');
        $endTime = date('Y-m-d H:i:s', time());

        while (true) {
            // 所有符合条件的病例信息
//            $data = PatientInfo::query()
            $data = DB::table($cyzs->table_name)
                ->leftJoin('patient_info_target','patient_info.MED_REC_ID','=','patient_info_target.ZYH')
//                ->where('MED_REC_ID','110069971')
                ->when($cyzs->condition,function($query)use($cyzs){
                    return $query->whereRaw($cyzs->condition);
                })
                ->whereBetween('AAC01',[$conf['zb_start_time'], $endTime])
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
                $ZYH = data_get($value,'MED_REC_ID');

                // 查询出院记录
                $cyjlInfo1 = $this->getCyjlError($bl01Service,$yzbService,$ZYH,$bllbs[0],$cysj->keyword,$cyjlsj->table_field,$cyjlwcsj->keyword);
                $cyjlInfo2 = [];
                $cyjlInfo3 = [];
                if (!$cyjlInfo1['numerator']) {
                    // 24小时记录类
                    $cyjlInfo2 = $this->getCyjlError($bl01Service,$yzbService,$ZYH,$bllbs[1],$cysj->keyword,$cyjlsj->table_field,$cyjlwcsj->keyword);
                    if (!$cyjlInfo2['numerator']) {
                         // 查询死亡记录
                        $cyjlInfo3 = $this->getCyjlError($bl01Service,$yzbService,$ZYH,$bllbs[2],$swsj->keyword,$cyjlsj->table_field,$cyjlwcsj->keyword);
                    }
                }

                $numerator = '';
                $cyjlError = '';
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
                PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
            }
        }

        return true;
    }

    protected function getCyjlError($bl01Service,$yzbService,$ZYH,$BLLB,$YDYZLB,$key_field,$time)
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
        $cjsj = !empty($bl01Data[0][0][$key_field]) ? $bl01Data[0][0][$key_field] : '';


        // 分子 - 查询医嘱表 XZJDSJ
        $must = [
            ["term" => ['ZYH' => $ZYH]],//id6
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
                if (!in_array($value['XZJDSJ'],$xzjdsjList)) {//id6
                    $xzjdsjList[] = $value['XZJDSJ'];
                }
            }
        }

        $numerator = 0;
        if ($xzjdsjList) {
            $cyjlError = '';
            foreach ($xzjdsjList as $xzjdsj) {
                if ($cjsj) {
                    $xzjdsjEnd = date('Y-m-d H:i:s',strtotime($xzjdsj)+(3600*$time));
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
