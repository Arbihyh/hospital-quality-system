<?php

namespace App\Console\Commands\DataFormat;

use App\Model\Setting;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;

class BLLB294_45 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bcjl:sxbcjl {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '病程记录-输血病程记录格式化';

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
        $this->info('病程记录-输血病程记录格式化 - bllb294-45 - 开始');

        $this->dataHandle();

        $this->info('病程记录-输血病程记录格式化 - bllb294-45 - 完毕');
    }

    public function dataHandle()
    {
        $setName = 'gsh_bllb294_45';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $page = 1;
        $pageSize = 500;

        $bl01Service = new ElasticsearchService('bl01_202303');

        while (true) {
            $must = [
                ["term" => ['BLLB' => 294]],
                ["term" => ['MBLB' => 45]],
//                ['range' => ['CJSJ' => ['gte' => '2021-01-01 00:00:00']]]
                ['range' => ['BLBH' => ['gt' => $lastId]]]
            ];
            $params = $bl01Service->clearMust()
                ->queryByMustBatch($must)
                ->orderBy('BLBH','asc')
                ->paginate($page,$pageSize)
                ->trackTotalHits()
                ->getParams();
            $restful = app('es')->search($params);
            $data = $bl01Service->getDataByEs($restful);
            if (empty($data[0])) {
                break;
            }

            $lastPage = (int)ceil($data[1]/$pageSize);
            if ($page == 1) {
                echo '数据总条数：'.$data[1].PHP_EOL.'每页执行条数：'.$pageSize.PHP_EOL.'总页数：'.$lastPage.PHP_EOL;
            } elseif ($page > $lastPage) {
                // 如果当前页码大于最大页码则结束
                break;
            }
            echo $page.PHP_EOL;
            $page++;

            foreach ($data[0] as $value) {
                if (empty($value['JZHM'])) {
                    continue;
                }
                $HJNR = str_replace("：",":", trim($value['HJNR']));

                if (!empty($HJNR)) {
                    $insertData = ['ZYH' => $value['JZHM']];

                    // 体温
                    $insertData['TIWEN'] = '';
                    if (stripos($HJNR, "℃")) {
                        preg_match_all("/(\d+\.\d+)(℃)/", $HJNR, $tiwen);
                        if (empty($tiwen[1][0])) {
                            preg_match_all("/(\d+\d+)(℃)/", $HJNR, $tiwen);
                        }
                        if (!empty($tiwen[1][0])) {
                            $insertData['TIWEN'] = trim($tiwen[1][0]);
                        }
                    }

                    // 实验室检查指标
                    $insertData['JCZB'] = '';
                    if (stripos($HJNR, "实验室检查指标:")) {
                        $arr = explode("实验室检查指标:", $HJNR);
                        if (!empty($arr[1])) {
                            $arr = explode("根据临床症状和实验室检测结果",$arr[1]);
                            $insertData['JCZB'] = trim($arr[0]);
                        }
                    }

                    // 根据临床症状和实验室检测结果，
                    $HJNR = str_replace("根据临床症状和实验室检测结果，","根据临床症状和实验室检测结果,",$HJNR);
                    $insertData['JCJG'] = '';
                    if (stripos($HJNR, "根据临床症状和实验室检测结果,")) {
                        $arr = explode("根据临床症状和实验室检测结果,", $HJNR);
                        if (!empty($arr[1])) {
                            $arr = explode("输血开始时间",$arr[1]);
                            $insertData['JCJG'] = trim($arr[0]);
                        }
                    }

                    $str = str_replace('： ',':',$HJNR);
                    $str = str_replace('：',':',$str);
                    $str = str_replace('起始时间','开始时间:',$str);
                    $str = str_replace('开始时间','开始时间:',$str);
                    $str = str_replace('结束时间','结束时间:',$str);
                    $str = str_replace('::',':',$str);
                    $str = str_replace('.','-',$str);
                    $str = str_replace('年','-',$str);
                    $str = str_replace('月','-',$str);
                    $str = str_replace('日',' ',$str);
                    $str = str_replace('时',':',$str);
                    $str = str_replace('分','',$str);
                    $str = str_replace(':间','时间',$str);
                    $str = str_replace('   ',' ',$str);
                    $str = str_replace('  ',' ',$str);

                    // 输血开始时间
                    preg_match_all("/开始时间:\d{4}-\d{1,2}-\d{1,2} \d{1,2}:\d{1,2}/", $str, $sskssj);
                    if (!empty($sskssj[0][0])) {
                        $sskssj = explode("开始时间:",$sskssj[0][0]);
                        $insertData['SXKSSJ'] = $sskssj[1].':00';
                    }

                    // 输血结束时间
                    preg_match_all("/结束时间:\d{4}-\d{1,2}-\d{1,2} \d{1,2}:\d{1,2}/", $str, $ssjssj);
                    if (!empty($ssjssj[0][0])) {
                        $ssjssj = explode("结束时间:",$ssjssj[0][0]);
                        $insertData['SXJSSJ'] = $ssjssj[1].':00';
                    }

                    // 输注者
                    $insertData['SZZ'] = '';
                    $SZZ_K = '';
                    if (stripos($HJNR, "输注者:")) {
                        $SZZ_K = '输注者:';
                    } elseif (stripos($HJNR, "输注血浆者:")) {
                        $SZZ_K = '输注血浆者:';
                    } elseif (stripos($HJNR, "输血者:")) {
                        $SZZ_K = '输血者:';
                    }
                    if ($SZZ_K) {
                        $arr = explode($SZZ_K, $HJNR);
                        if (!empty($arr[1])) {
                            if (stripos($HJNR, "核对者:")) {
                                $arr1 = explode("核对者",$arr[1]);
                            } elseif (stripos($HJNR, "核对血浆者:")) {
                                $arr1 = explode("核对血浆者",$arr[1]);
                            }
                            if (!empty($arr1)) {
                                $SZZ = str_replace("，","",trim($arr1[0]));
                                $SZZ = str_replace(",","",$SZZ);
                                $SZZ = str_replace("。","",$SZZ);
                                $insertData['SZZ'] = $SZZ;
                            }
                        }
                    }

                    // 核对者
                    $insertData['HDZ'] = '';
                    if (stripos($HJNR, "核对者:")) {
                        $arr = explode("核对者:", $HJNR);
                        if (!empty($arr[1])) {
                            $arr[1] = str_replace("，","。",$arr[1]);
                            $arr = explode("。",$arr[1]);
                            $insertData['HDZ'] = trim($arr[0]);
                        }
                    } elseif (stripos($HJNR, "核对血浆者:")) {
                        $arr = explode("核对血浆者:", $HJNR);
                        if (!empty($arr[1])) {
                            $arr[1] = str_replace("，","。",$arr[1]);
                            $arr = explode("。",$arr[1]);
                            $insertData['HDZ'] = trim($arr[0]);
                        }
                    }

                    $where = ['BLBH'=>$value['BLBH']];
                    \App\Model\BLLB294_45::query()->updateOrInsert($where,$insertData);

                    $lastBLBH = $value['BLBH'];
                }
            }
        }

        if (!empty($lastBLBH)) {
            Setting::query()->where('name', '=', $setName)->update(['content' => $lastBLBH]);
        }
    }

}
