<?php

namespace App\Console\Commands\Target;

use App\Model\EMR_BL_BL01;
use App\Model\FeeDetailed;
use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use App\Services\ElasticsearchService;
use App\Services\TargetService;
use Illuminate\Console\Command;

class Implants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zb:zrw {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '植入物相关记录符合率指标 - 数据处理';

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
        $this->info('植入物相关记录符合率指标 - 数据开始处理');

        $page = (int)$this->argument('page') ?: 1;

        // 植入物相关记录符合率指标 数据处理
//        $targetService = new TargetService();
//        $targetService->ImplantsDataHandle($page);

        $this->ImplantsDataHandle($page);

        $this->info('植入物相关记录符合率指标 - 数据处理完毕');
    }

    protected function ImplantsDataHandle($page)
    {
        $conf = config('confAdmin');
        $endTime = date('Y-m-d H:i:s', time());
        $feeService = new ElasticsearchService('fee_detailed');
        $bl01Service = new ElasticsearchService('bl01_202303');

        // 获取所有植入物信息
        $implantsList = \App\Model\Implants::query()->pluck('name','manufactor')->toArray();

        while (true) {
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->whereBetween('AAC01',[$conf['zb_start_time'], $endTime])
                ->paginate(500, ['AAA28','MED_REC_ID','AAC01'],'page', $page)
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
                $zrwName = [];

                // 查询费用名称
                $feeList = $this->feeDetailed($feeService,$ZYH);

                $fymcArr = [];
                $sfxm = [];
                // 分母 - 匹配出包含植入名称的数据
                foreach ($implantsList as $manufactor => $name) {
                    // 记录匹配到的植入名称
                    foreach ($feeList as $val) {
                        if ($name == '连接管') {
                            if ($val['FYMC']==$name && !in_array($name,$fymcArr)) {
                                $fymcArr[] = $name;
                                $sfxm[] = '商品名称：'.$name.'，数量：'.$val['FYSL'].'，厂家：'.$manufactor;
                            }
                        } elseif (stripos($val['FYMC'],$name) !== false && !in_array($name,$fymcArr)) {
                            $fymcArr[] = $name;
                            $sfxm[] = '商品名称：'.$name.'，数量：'.$val['FYSL'].'，厂家：'.$manufactor;
                        }
                    }
                }

                if (empty($fymcArr)) {
                    continue;
                }

                $zrwName[] = '收费项目【'.implode('，',$sfxm).'】';

                // 分子 - 查询手术记录
                $ssList = $this->bl01Value($bl01Service,$ZYH,303,$implantsList);
                $zrwName[] = $ssList ? '手术记录【'.implode('，',$ssList).'】' : '手术记录【无】';

                // 分子 - 病程记录
                $bcList = $this->bl01Value($bl01Service,$ZYH,294,$implantsList);
                $zrwName[] = $bcList ? '病程记录【'.implode(',',$bcList).'】' : '病程记录【无】';

                $numerator = 0;
                if ($ssList || $bcList) {
                    $numerator = 1;
                }

                // 记录
                $saveData = ['denominator_zrw'=>1,'numerator_zrw'=>$numerator,'zrw_name'=>implode('，',$zrwName)];
                PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
            }
        }

        return true;
    }

    protected function feeDetailed($feeService,$ZYH)
    {
        $must = [
            ["term" => ['MED_REC_ID' => $ZYH]]
        ];
        $params = $feeService->clearMust()
            ->queryByMustBatch($must)
            ->paginate(1,10000)
            ->getParams();
        $restful = app('es')->search($params);
        $feeData = $feeService->getDataByEs($restful);
        $fymcList = [];
        $fyData = [];
        if (!empty($feeData[0])) {
            foreach ($feeData[0] as $value) {
                if (!in_array($value['FYMC'],$fymcList)) {
                    $fymcList[] = $value['FYMC'];
                    $fyData[] = [
                        'FYMC' => $value['FYMC'],
                        'FYSL' => $value['FYSL']
                    ];
                }
            }
        }

        return $fyData;
    }

    protected function bl01Value($bl01Service,$ZYH,$bllb,$implantsList)
    {
        $must = [
            ["term" => ['JZHM' => $ZYH]],
            ["term" => ['BLLB' => $bllb]]
        ];
        $notMust = ['term' => ["BLZT" => 9]];
        $params = $bl01Service->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNot($notMust)
            ->paginate(1,1000)
            ->getParams();
        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);

        $hjnrList = [];
        if (!empty($bl01Data[0])) {
            foreach ($bl01Data[0] as $value) {
                if (in_array($value['HJNR'],$implantsList) && !in_array($value['HJNR'],$hjnrList)) {
                    $hjnrList[] = $value['HJNR'];
                }
            }
        }

        return $hjnrList;
    }
}
