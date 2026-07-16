<?php

namespace App\Console\Commands\TargetNew;

use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use App\Model\PatientInfoTargetTemporary;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;

class Zqtysgfqs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'newZb:zqtysgfqs {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '知情同意书规范签署 - 数据处理';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        //zz 测试123
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('知情同意书规范签署 - 数据开始处理');

        $page = (int)$this->argument('page') ?: 1;

        // 细菌培养检查记录符合率处理
        $this->zqtysgfqsDataHandle($page);

        $this->info('知情同意书规范签署 - 数据处理完毕');
    }

    protected function zqtysgfqsDataHandle($page)
    {
        $bl01Service = new ElasticsearchService('bl01_202303');
        $blsyService = new ElasticsearchService('blsy_2023');
        $staffService = new ElasticsearchService('staff_2023');


        while (true) {
            $data = PatientInfo::query()
                ->whereBetween('AAC01', ['2022-01-01 00:00:00', '2023-12-31 23:59:59'])
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

            foreach ($data['data'] as $val) {
                $ZYH = $val['MED_REC_ID'];

                // 分母
                $bl01Data = $this->bl01($bl01Service,$ZYH);
                if (empty($bl01Data)) {
                    continue;
                }

                // 分子 - 输血治疗同意书
                $sxzltys = $this->returnData($bl01Service,$blsyService,$staffService,$ZYH,329,59);

                // 分子 - 手术知情同意书
                $sszqtys = $this->returnData($bl01Service,$blsyService,$staffService,$ZYH,303,8);

                if ($sxzltys['data'] || $sszqtys['data']) {
                    $numerator = 0;
                    if ($sxzltys['code'] && $sszqtys['code']) {
                        $numerator = 1;
                    }

                    $errorDate = array_merge($sxzltys['data'],$sszqtys['data']);
                    $errorDate = implode("，", $errorDate);

                    // 记录
                    $saveData = ['denominator_zqtysgfqs' => 1, 'numerator_zqtysgfqs' => $numerator, 'zqtysgfqs_error' => $errorDate];

                    PatientInfoTargetTemporary::query()->updateOrInsert(['ZYH'=>$ZYH],$saveData);
                }
            }
        }
    }

    protected function bl01($bl01Service,$ZYH)
    {
        $must = [["term" => ['JZHM' => $ZYH]], ["term" => ['BLLB' => 329]]];
        $params = $bl01Service->clearMust()->queryByMustBatch($must)->getParams();
        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);

        return !empty($bl01Data[0]) ? $bl01Data[0] : [];
    }

    protected function blsy($blsyService,$BLBH)
    {
        $must = [["term" => ['BLBH' => $BLBH]]];
        $params = $blsyService->clearMust()->queryByMustBatch($must)->getParams();
        $restful = app('es')->search($params);
        $blsyData = $blsyService->getDataByEs($restful);

        return !empty($blsyData[0]) ? $blsyData[0] : [];
    }

    protected function staff($staffService,$SYYS)
    {
        $must = [["term" => ['code' => $SYYS]]];
        $params = $staffService->clearMust()->queryByMustBatch($must)->getParams();
        $restful = app('es')->search($params);
        $staffData = $staffService->getDataByEs($restful);

        return !empty($staffData[0]) ? $staffData[0][0]['name'] : '';
    }


    protected function returnData($bl01Service,$blsyService,$staffService,$ZYH,$BLLB,$MBLB)
    {
        $must = [["term" => ['JZHM' => $ZYH]], ["term" => ['BLLB' => $BLLB]], ["term" => ['MBLB' => $MBLB]]];
        $params = $bl01Service->clearMust()->queryByMustBatch($must)->getParams();
        $restful = app('es')->search($params);
        $bl01Data = $bl01Service->getDataByEs($restful);
        $error = [];
        $numerator = 1;
        foreach ($bl01Data[0] as $value) {
            // 医生签名
            $blsy = $this->blsy($blsyService,$value['BLBH']);

            $title = $this->getTitle($value['HJNR']);
            $str = !empty($title) ? $title : $value['BLMC'];
            $str1 = '';
            if ($blsy) {
                $name = $this->staff($staffService,$blsy[0]['SYYS']);
                if ($name) {
                    $str1 .= $name;
                } else {
                    $numerator = 0;
//                    $title = $this->getTitle($value['HJNR']);
                    $str1 .= '无电子签名';//!empty($title) ? $title : $value['BLMC'];
                }
            } else {
                $numerator = 0;
                $str1 .= '无电子签名';
//                $title = $this->getTitle($value['HJNR']);
//                $str1 .= !empty($title) ? $title : $value['BLMC'];
            }

            // 时间
            $HJNR = $value['HJNR'];
            $str2 = '';
            if (empty($HJNR)) {
                $str2 .= '签署时间（无）';
            } elseif (stripos($HJNR,'签署时间')) {
                $HJNR = explode("签署时间", $HJNR);
                $HJNR = $HJNR[1];
                if (stripos($HJNR,"患方明确意见")) {
                    $HJNR = explode("患方明确意见", $HJNR);
                } elseif (stripos($HJNR,"我已逐条阅读以上告知内容")) {
                    $HJNR = explode("我已逐条阅读以上告知内容", $HJNR);
                } elseif (stripos($HJNR,"患者签名")) {
                    $HJNR = explode("患者签名", $HJNR);
                } elseif (stripos($HJNR,"签字时间")) {
                    $HJNR = explode("签字时间", $HJNR);
                }
                $HJNR = trim($HJNR[0]);
                $HJNR = str_replace("：",":", $HJNR);
                $HJNR = str_replace("   "," ", $HJNR);
                $HJNR = str_replace("  "," ", $HJNR);

                $preg = '/\d{4}(\-|\~|\－|\年|\.)\d{1,2}(\-|\~|\－|\月|\.)\d{1,2}(\日){0,1}(\s+)\d{1,2}(\:|\.|\时)\d{1,2}/';
                if (preg_match($preg, $HJNR, $dateTime)) {
                    $str2 .= $dateTime[0];
                } else {
                    $numerator = 0;
                    $str2 .= '签署时间未精确到分钟';
                }
            } else {
                $str2 .= '签署时间（无）';
            }

            if ($str1 != '无电子签名' && $str2 != '签署时间未精确到分钟') {
                $error[] = $str."【".$str1."，".$str2."】";
            } elseif ($str1 == '无电子签名' && $str2 == '签署时间未精确到分钟') {
                $error[] = $str."【".$str1."，".$str2."】";
            } elseif ($str1 == '无电子签名') {
                $error[] = $str."【".$str1."】";
            } else {
                $error[] = $str."【".$str2."】";
            }
        }

        return ['code'=>$numerator,'data'=>$error];
    }

    public function getTitle($HJNR)
    {
        if (empty($HJNR)) {
            return '';
        }

        $str = '';
        //if (stripos($HJNR,'滨州医学院烟台附属医院')) {
           // $HJNR = explode("滨州医学院烟台附属医院", $HJNR);
            if (!empty($HJNR)) {
                if (stripos($HJNR[1],'输血治疗知情同意书') !== false) {
                    $str = '输血治疗知情同意书';
                } elseif (stripos($HJNR[1],'输血治疗同意书') !== false) {
                    $str = '输血治疗同意书';
                } elseif (stripos($HJNR[1],'手术知情同意书') !== false) {
                    $str = '手术知情同意书';
                } elseif (stripos($HJNR[1],'手术同意书') !== false) {
                    $str = '手术同意书';
                }
            }
       // }

        return $str;
    }

}
