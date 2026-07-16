<?php

namespace App\Console\Commands\DataFormat;

use App\Model\Setting;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;

class BLLB294_295 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bcjl:scbc {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '病程记录-首次病程格式化';

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
        $this->info('病程记录首次病程格式化 - bllb294-295 - 开始');

        $this->dataHandle();

        $this->info('病程记录首次病程格式化 - bllb294-295 - 完毕');
    }

    public function dataHandle()
    {
        $setName = 'gsh_bllb294_295';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $page = 1;
        $pageSize = 500;

        $bl01Service = new ElasticsearchService('bl01_202303');

        while (true) {
            $must = [
                ["term" => ['BLLB' => 294]],
                ["term" => ['MBLB' => 295]],
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

                $insertData = [
                    'ZYH' => $value['JZHM'],
                    'BLMC' => $value['BLMC'],
                    'CJSJ' => $value['CJSJ'],
                    'ZXSJ' => $value['ZXSJ'],
                    'BRKS' => $value['BRKS'],
                    'BRBH' => $value['BRBH'],
//                    'CJKS' => $value['CJKS'],
//                    'AAB01' => $value['AAB01'],
//                    'AAC01' => $value['AAC01'],
                    'SXYS' => $value['SXYS']
                ];
                if (!empty($HJNR)) {
                    // 病例特点
                    if (stripos($HJNR, "病例特点:")) {
                        $arr = explode("病例特点:", $HJNR);
                        if (!empty($arr[1])) {
                            if (stripos($arr[1], "初步诊断:") !== false) {
                                $arr = explode("初步诊断:",$arr[1]);
                                $bltd = explode("\n", trim($arr[0]));
                                $insertData['BLTD'] = json_encode($bltd, 256);
                            }
                        }
                    }

                    // 初步诊断
                    $insertData['CBZD'] = '';
                    if (stripos($HJNR, "初步诊断:")) {
                        $arr = explode("初步诊断:", $HJNR);
                        if (!empty($arr[1])) {
                            if (stripos($arr[1], "诊断依据:") !== false) {
                                $arr = explode("诊断依据:",$arr[1]);

                                $cbzdList = [];
                                for ($i=1;$i<=10;$i++) {
                                    if (stripos($arr[0], $i.".") !== false) {
                                        $cbzd = explode($i.".",$arr[0]);
                                        if (!empty(stripos($cbzd[1],($i+1).'.'))) {
                                            $cbzdArr = explode(($i+1).'.',$cbzd[1]);
                                            $cbzdList[] = trim($cbzdArr[0]);
                                        } else {
                                            $cbzdList[] = trim($cbzd[1]);
                                            break;
                                        }
                                    } elseif (stripos($arr[0], $i."、") !== false) {
                                        $cbzd = explode($i."、",$arr[0]);
                                        if (!empty(stripos($cbzd[1],($i+1).'、'))) {
                                            $cbzdArr = explode(($i+1).'、',$cbzd[1]);
                                            $cbzdList[] = trim($cbzdArr[0]);
                                        } else {
                                            $cbzdList[] = trim($cbzd[1]);
                                            break;
                                        }
                                    }
                                }

                                $cbzdList = !empty($cbzdList) ? $cbzdList : explode("\n", trim($arr[0]));

                                // 所有初步诊断
                                if (!empty($cbzdList)) {
                                    $insertData['CBZD'] = json_encode($cbzdList, 256);

                                    $qtcbzd = [];
                                    foreach ($cbzdList as $key => $val) {
                                        if ($key < 1) {
                                            // 第一初步诊断
                                            $insertData['CBZD_ONE'] = trim($val);
                                        } else {
                                            // 其它初步诊断
                                            $qtcbzd[] = trim($val);
                                        }
                                    }

                                    if (!empty($qtcbzd)) {
                                        $insertData['CBZD_OTHER'] = json_encode($qtcbzd, 256);
                                    }
                                }
                            }
                        }
                    }

                    // 诊断依据
                    $insertData['ZDYJ'] = '';
                    if (stripos($HJNR, "诊断依据:")) {
                        $arr = explode("诊断依据:", $HJNR);
                        if (!empty($arr[1])) {
                            if (stripos($arr[1], "鉴别诊断:") !== false) {
                                $arr = explode("鉴别诊断:",$arr[1]);
                                $zdyj = explode("\n", trim($arr[0]));
                                $insertData['ZDYJ'] = json_encode($zdyj, 256);
                            }
                        }
                    }

                    // 鉴别诊断
                    $insertData['JBZD'] = '';
                    if (stripos($HJNR, "鉴别诊断:")) {
                        $arr = explode("鉴别诊断:", $HJNR);
                        if (!empty($arr[1])) {
                            if (stripos($arr[1], "诊疗计划:") !== false) {
                                $arr = explode("诊疗计划:",$arr[1]);
                                $jbzd = explode("\n", trim($arr[0]));
                                $insertData['JBZD'] = json_encode($jbzd, 256);

                                // 鉴别诊断名称
                                $jbzdmc = [];
                                foreach ($jbzd as $val) {
                                    if (stripos($val,':') !== false) {
                                        $arr = explode(":",$val);
                                    } elseif (stripos($val,' ') !== false) {
                                        $arr = explode(" ",$val);
                                    }

                                    $jbmc = $arr[0];
                                    for ($i=1;$i<=count($jbzd);$i++) {
                                        if (stripos($jbmc, $i.".") !== false) {
                                            $jbmc = str_replace($i.".","",$jbmc);
                                        } elseif (stripos($jbmc, $i."、") !== false) {
                                            $jbmc = str_replace($i."、","",$jbmc);
                                        } else {
                                            $jbmc = str_replace($i,"",$jbmc);
                                        }
                                    }
                                    $jbzdmc[] = trim($jbmc);
                                }
                                if (!empty($jbzdmc)) {
                                    $insertData['JBZDMC'] = json_encode($jbzdmc, 256);
                                }
                            }
                        }
                    }

                    // 诊疗计划
                    $insertData['ZLJH'] = '';
                    if (stripos($HJNR, "诊疗计划:")) {
                        $arr = explode("诊疗计划:", $HJNR);
                        if (!empty($arr[1])) {
                            if (stripos($arr[1], "上级医师审核日期:") !== false) {
                                $arr = explode("上级医师审核日期:",$arr[1]);
                                $zljh = explode("\n", trim($arr[0]));
                                $insertData['ZLJH'] = json_encode($zljh, 256);
                            }
                        }
                    }

                    // 上级医师审核日期
                    $insertData['SHRQ'] = '';
                    if (stripos($HJNR, "上级医师审核日期:")) {
                        $arr = explode("上级医师审核日期:", $HJNR);
                        if (!empty($arr[1])) {
                            $insertData['SHRQ'] = trim($arr[1]);
                        }
                    }
                }

                $where = ['BLBH'=>$value['BLBH']];
                \App\Model\BLLB294_295::query()->updateOrInsert($where,$insertData);

                $lastBLBH = $value['BLBH'];
            }
        }

        if (!empty($lastBLBH)) {
            Setting::query()->where('name', '=', $setName)->update(['content' => $lastBLBH]);
        }
    }




}
