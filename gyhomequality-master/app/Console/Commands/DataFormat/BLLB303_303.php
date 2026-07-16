<?php

namespace App\Console\Commands\DataFormat;

use App\Model\Setting;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;

class BLLB303_303 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ssl:pgcjl {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '手术类-剖宫产记录';

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
        $this->info('手术类-剖宫产记录 - bllb303-303 - 开始');

        $page = (int)$this->argument('page') ?: 1;
        $this->dataHandle($page);

        $this->info('手术类-剖宫产记录 - bllb303-303 - 完毕');
    }

    public function dataHandle($page)
    {
        $setName = 'gsh_bllb303_303';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $pageSize = 200;

        $bl01Service = new ElasticsearchService('bl01_202303');

        $lastBLBH = '';
        while (true) {
            $must = [
                ['term' => ['BLLB' => 303]],
                ['range' => ['CJSJ' => ['gte' => '2021-01-01 00:00:00']]],
                ['range' => ['BLBH' => ['gt' => $lastId]]],
            ];
            $should = [
                ['match_phrase' => ['HJNR'=>'剖宫产记录']],
                ['match_phrase' => ['HJNR'=>'剖宮产记录']],
            ];
            $params = $bl01Service->clearMust()
                ->queryByMustBatch($must)
                ->queryByShouldBatch($should)
                ->minimumShouldMatch()
                ->orderBy('BLBH','asc')
                ->paginate($page, $pageSize)
                ->trackTotalHits()
                ->getParams();
            $restful = app('es')->search($params);
            $data = $bl01Service->getDataByEs($restful);
            if (empty($data[0])) {
                break;
            }
            echo $page.PHP_EOL;
            $page++;

            foreach ($data[0] as $value) {
                $lastBLBH = $value['BLBH'];
                echo $lastBLBH.PHP_EOL;

                $insertData = ['ZYH'=>$value['JZHM']];

                $HJNR = $value['HJNR'];
                $HJNR = str_replace("：",":", $HJNR);

                // 患者姓名
                $insertData['XM'] = '';
                $arr = explode("患者姓名", $HJNR);
                if (!empty($arr[1])) {
                    if (stripos($arr[1],'性别') !== false) {
                        $arr = explode('性别', trim(str_replace(":","",$arr[1])));
                        $insertData['XM'] = !empty(trim($arr[0])) ? desensitize(trim($arr[0]), 1, 1, '*') : '';
                    }
                }

                // 性别
                $insertData['XB'] = '';
                $arr = explode("性别", $HJNR);
                if (!empty($arr[1])) {
                    if (stripos($arr[1],'年龄') !== false) {
                        $arr = explode('年龄', trim(str_replace(":","",$arr[1])));
                        $insertData['XB'] = trim($arr[0]);
                    }
                }

                // 年龄
                $insertData['NL'] = '';
                $arr = explode("年龄", $HJNR);
                if (!empty($arr[1])) {
                    if (stripos($arr[1],'病室') !== false) {
                        $arr = explode('病室', trim(str_replace(":","",$arr[1])));
                        $insertData['NL'] = str_replace("岁", "",trim($arr[0]));
                    }
                }

                // 病室
                $insertData['BS'] = '';
                $arr = explode("病室", $HJNR);
                if (!empty($arr[1])) {
                    if (stripos($arr[1],'床号') !== false) {
                        $arr = explode('床号', trim(str_replace(":","",$arr[1])));
                        $insertData['BS'] = trim($arr[0]);
                    }
                }

                // 床号
                $insertData['CH'] = '';
                $arr = explode("床号", $HJNR);
                if (!empty($arr[1])) {
                    if (stripos($arr[1],'手术日期') !== false) {
                        $arr = explode('手术日期', trim(str_replace(":","",$arr[1])));
                        $insertData['CH'] = trim($arr[0]);
                    }
                }

                // 手术日期
                $insertData['SSRQ'] = '';
                $arr = explode("手术日期", $HJNR);
                if (!empty($arr[1])) {
                    if (stripos($arr[1],'手术时间') !== false) {
                        $arr = explode('手术时间', trim(str_replace(":","",$arr[1])));
                        $insertData['SSRQ'] = trim($arr[0]);
                    }
                }

                // 手术时间
                $insertData['SSSJ'] = '';
                $arr = explode("手术时间", $HJNR);
                if (!empty($arr[1])) {
                    if (stripos($arr[1],'术前诊断') !== false) {
                        $arr = explode('术前诊断', trim(str_replace(":","",$arr[1])));
                        $SSSJ = str_replace("。",'', $arr[0]);
                        $SSSJ = str_replace("分钟",'', $SSSJ);
                        $insertData['SSSJ'] = trim($SSSJ);
                    }
                }

                // 术前诊断
                $insertData['SQZD'] = '';
                $arr = explode("术前诊断", $HJNR);
                if (!empty($arr[1])) {
                    if (stripos($arr[1],'孕次') !== false) {
                        $arr = explode('孕次', trim(str_replace(":","",$arr[1])));
                        $SQZD = trim($arr[0]);

                        $sqzdList = [];
                        if (stripos($SQZD, '1.') !== false) {
                            for ($i=1;$i<=20;$i++) {
                                if (stripos($SQZD, $i.'.') !== false) {
                                    $SQZD_ARR = explode($i.'.', $SQZD);
                                    $SQZD_ARR = explode(($i+1).'.', $SQZD_ARR[1]);
                                    $sqzdList[] = $SQZD_ARR[0];
                                } else {
                                    break;
                                }
                            }
                        } elseif (stripos($SQZD, '1、') !== false) {
                            for ($i=1;$i<=20;$i++) {
                                if (stripos($SQZD, $i.'、') !== false) {
                                    $SQZD_ARR = explode($i.'、', $SQZD);
                                    $SQZD_ARR = explode(($i+1).'、', $SQZD_ARR[1]);
                                    $sqzdList[] = $SQZD_ARR[0];
                                } else {
                                    break;
                                }
                            }
                        } elseif (stripos($SQZD, '、') !== false) {
                            $sqzdList[] = explode("、", $SQZD);
                        } else {
                            $sqzdList[] = $SQZD;
                        }
                        $insertData['SQZD'] = json_encode($sqzdList, 256);
                    }
                }

                // 孕次
                $insertData['YC'] = '';
                $arr = explode("孕次", $HJNR);
                if (!empty($arr[1])) {
                    if (stripos($arr[1],'产次') !== false) {
                        $arr = explode('产次', trim(str_replace(":","",$arr[1])));
                        $insertData['YC'] = trim($arr[0]);
                    }
                }

                // 产次
                $insertData['CC'] = '';
                $arr = explode("产次", $HJNR);
                if (!empty($arr[1])) {
                    if (stripos($arr[1],'术中诊断') !== false) {
                        $arr = explode('术中诊断', trim(str_replace(":","",$arr[1])));
                        $insertData['CC'] = trim($arr[0]);
                    }
                }

                // 术中诊断
                $insertData['SZZD'] = '';
                $arr = explode("术中诊断", $HJNR);
                if (!empty($arr[1])) {
                    if (stripos($arr[1],'待产日期') !== false) {
                        $arr = explode('待产日期', trim(str_replace(":","",$arr[1])));
                        $SZZD = trim($arr[0]);
                        $szzdList = [];
                        if (stripos($SZZD, '1.') !== false) {
                            for ($i=1;$i<=20;$i++) {
                                if (stripos($SZZD, $i.'.') !== false) {
                                    $SZZD_ARR = explode($i.'.', $SZZD);
                                    $SZZD_ARR = explode(($i+1).'.', $SZZD_ARR[1]);
                                    $szzdList[] = $SZZD_ARR[0];
                                } else {
                                    break;
                                }
                            }
                        } elseif (stripos($SZZD, '1、') !== false) {
                            for ($i=1;$i<=20;$i++) {
                                if (stripos($SZZD, $i.'、') !== false) {
                                    $SZZD_ARR = explode($i.'、', $SZZD);
                                    $SZZD_ARR = explode(($i+1).'、', $SZZD_ARR[1]);
                                    $szzdList[] = $SZZD_ARR[0];
                                } else {
                                    break;
                                }
                            }
                        } elseif (stripos($SZZD, '、') !== false) {
                            $szzdList[] = explode("、", $SZZD);
                        }  else {
                            $szzdList[] = $SZZD;
                        }
                        $insertData['SZZD'] = json_encode($szzdList, 256);
                    }
                }

                // 待产日期
                $insertData['DCRQ'] = '';
                $arr = explode("待产日期", $HJNR);
                if (!empty($arr[1])) {
                    if (stripos($arr[1],'手术名称') !== false) {
                        $arr = explode('手术名称', trim(str_replace(":","",$arr[1])));
                        $insertData['DCRQ'] = trim($arr[0]);
                    }
                }

                // 手术名称
                $insertData['SSMC'] = '';
                $insertData['SSMC_FIRST'] = '';
                $arr = explode("手术名称", $HJNR);
                if (!empty($arr[1])) {
                    if (stripos($arr[1],'麻醉方法') !== false) {
                        $arr = explode('麻醉方法', trim(str_replace(":","",$arr[1])));
                        $SSMC = str_replace("，",",", trim($arr[0]));
                        if (stripos($SSMC, ",") !== false) {
                            $SSMC = explode(",", $SSMC);
                        } elseif (stripos($SSMC, "、") !== false) {
                            $SSMC = explode("、", $SSMC);
                        } else {
                            $SSMC = [$SSMC];
                        }
                        $insertData['SSMC'] = json_encode($SSMC, 256);
                        $insertData['SSMC_FIRST'] = $SSMC[0] ?? '';
                    }
                }

                // 麻醉方法
                $insertData['MZFF'] = '';
                $arr = explode("麻醉方法", $HJNR);
                if (!empty($arr[1])) {
                    if (stripos($arr[1],'手术指导者') !== false) {
                        $arr = explode('手术指导者', trim(str_replace(":","",$arr[1])));
                        $insertData['MZFF'] = trim($arr[0]);
                    }
                }

                // 手术指导者
                $insertData['SSZDZ'] = '';
                $arr = explode("手术指导者", $HJNR);
                if (!empty($arr[1])) {
                    if (stripos($arr[1],'手术者') !== false) {
                        $arr = explode('手术者', trim(str_replace(":","",$arr[1])));
                        $insertData['SSZDZ'] = trim($arr[0]);
                    }
                }

                // 手术者
                $insertData['SSZ'] = '';
                $arr = explode("手术者", $HJNR);
                if (!empty($arr[1])) {
                    if (stripos($arr[1],'助手') !== false) {
                        $arr = explode('助手', trim(str_replace(":","",$arr[1])));
                        $insertData['SSZ'] = trim($arr[0]);
                    }
                }

                // 助手
                $insertData['ZS'] = '';
                $arr = explode("助手", $HJNR);
                if (!empty($arr[1])) {
                    if (stripos($arr[1],'手术经过') !== false) {
                        $arr = explode('手术经过', trim(str_replace(":","",$arr[1])));
                        $insertData['ZS'] = trim($arr[0]);
                    }
                }

                // 手术经过、术中发现的情况及处理
                $insertData['SSJG'] = '';
                $arr = explode("术中发现的情况及处理", $HJNR);
                if (!empty($arr[1])) {
                    $arr1 = [];
                    if (stripos($arr[1],'手术者签名') !== false) {
                        $arr1 = explode('手术者签名', trim(str_replace(":","",$arr[1])));
                    } elseif (stripos($arr[1],'记录时间') !== false) {
                        $arr1 = explode('记录时间', trim(str_replace(":","",$arr[1])));
                    }
                    if (!empty($arr1)) {
                        $SSJG = explode("\n", trim($arr1[0]));
                        foreach ($SSJG as $k => $v) {
                            $SSJG[$k] = trim($v);
                        }
                        $insertData['SSJG'] = json_encode($SSJG, 256);
                    }
                }

                // 手术者签名
                $insertData['SSZQM'] = '';
                $arr = explode("手术者签名", $HJNR);
                if (!empty($arr[1])) {
                    if (stripos($arr[1],'记录时间') !== false) {
                        $arr = explode('记录时间', trim(str_replace(":","",$arr[1])));
                        $insertData['SSZQM'] = trim($arr[0]);
                    }
                }

                // 记录时间
                $insertData['JLSJ'] = '';
                $arr = explode("记录时间", $HJNR);
                if (!empty($arr[1])) {
                    if (stripos($arr[1],'滨州医学院烟台附属医院') !== false) {
                        $arr = explode('滨州医学院烟台附属医院', trim(str_replace(":","",$arr[1])));
                        $insertData['JLSJ'] = trim($arr[0]);
                    }
                }

                // 数据记录
                $insertData['updated_at'] = date("Y-m-d H:i:s", time());
                \App\Model\BLLB303_303::query()->updateOrInsert(['BLBH'=>$value['BLBH']],$insertData);
            }
        }

        if (!empty($lastBLBH)) {
            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastBLBH]);
        }
    }


}
