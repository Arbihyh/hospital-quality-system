<?php

namespace App\Console\Commands\DataFormat;

use App\Model\BLLB303;
use App\Model\BLLB303_EXT;
use App\Model\EMR_BL_BL01;
use App\Model\Setting;
use Illuminate\Console\Command;

class Surgery extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'format:surgery {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '手术数据格式化到bllb303表';

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
        $this->info('手术相关数据格式化 - bllb303 - 开始');
        $page = (int)$this->argument('page') ?: 1;
        $this->surgeryDataFormat($page);
        $this->info('手术相关数据格式化 - bllb303 - 完毕');

//        echo PHP_EOL.PHP_EOL;
//        $this->info('手术格式化数据扩展表数据处理 - bllb30_ext 开始');
//        $this->surgeryExtDataFormat(1);
//        $this->info('手术格式化数据扩展表数据处理 - bllb30_ext - 完毕');
    }

    /**
     * 手术数据格式化到bllb303表
     * @param $page
     * @return true
     */
    protected function surgeryDataFormat($page)
    {
        $setName = 'gsh_bllb303';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        // 查询的字段
        $field = ['EMR_BL_BL01.BLBH','patient_info.AAA28','patient_info.AAB01','patient_info.AAC01','CJSJ','ZXSJ','WCSJ','JZHM','BLMC','BRBH','BRXM','MBLB','HJNR'];
        while (true) {
            // 查询需要格式化的手术记录
            $data = EMR_BL_BL01::query()
                ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                ->leftJoin('patient_info','EMR_BL_BL01.JZHM', '=', 'patient_info.MED_REC_ID')
                ->where('BLLB', '=', 303)
                ->where('BLZT', '!=', 9)
//                ->where('EMR_BL_BL01.CJSJ','>=','2021-01-01 00:00:00')
                ->where('EMR_BL_BL01.BLBH','>',$lastId)
                ->where(function($query){
                    $query->where('MBLB','=',306)->orWhere('MBLB','=',74);
                })
                ->orderBy('EMR_BL_BL01.BLBH')
                ->paginate(500, $field, 'page', $page)
                ->toArray();
            if (empty($data['data'])) {
                break;
            }
            echo $page . PHP_EOL;
            $page++;

            // 数据处理
            foreach ($data['data'] as $bl01Info) {
                if (empty($bl01Info['BLBH'])) {
                    continue;
                }

                // 手术文本
                $hjnr = str_replace(':','：',$bl01Info['HJNR']);

                // 要写入的数据
                $insertData = [
                    'ZYH' => $bl01Info['JZHM'],
                    'AAA28' => $bl01Info['AAA28'],
                    'AAB01' => $bl01Info['AAB01'],
                    'AAC01' => $bl01Info['AAC01'],
                    'MBLB' => $bl01Info['MBLB'],
                    'CJSJ' => $bl01Info['CJSJ'],
                    'XGSJ' => $bl01Info['ZXSJ'],
                    'WCSJ' => $bl01Info['WCSJ'],
                ];

                $keyArr = [
                    'CH' => '床号：',
                    'SSRQ' => '手术日期：',
                    'SSSJ' => '手术时间：',
                    'SQZD' => '术前诊断：',
                    'SZZD' => '术中诊断：',
                    'SSMC' => '手术名称：',
                    'SSZD' => '手术指导者：',
                    'SSZ' => '手术者：',
                    'ZS' => '助手：',
                    'SSQM' => '手术者签名',
                    'JLSJ' => '记录时间',
                ];
                foreach ($keyArr as $key => $val) {
                    $insertData[$key] = $this->getVal($hjnr,$val);
                }

                // 记录时间处理
                if ($insertData['JLSJ']) {
                    $JLSJ = str_replace("：",":",$insertData['JLSJ']);
                    $JLSJ = str_replace("年","-",$JLSJ);
                    $JLSJ = str_replace("月","-",$JLSJ);
                    $JLSJ = str_replace("日","",$JLSJ);
                    $JLSJ = str_replace("时",":",$JLSJ);
                    $JLSJ = str_replace("分",":",$JLSJ);
                    $JLSJ = str_replace("秒","",$JLSJ);
                    $JLSJ = str_replace("   "," ",$JLSJ);
                    $JLSJ = str_replace("  "," ",$JLSJ);

                    if (strlen($JLSJ) == 10) {
                        $insertData['JLSJ'] = str_replace("::",":",$JLSJ." 00:00:00");
                    } else {
                        $insertData['JLSJ'] = str_replace("::",":",$JLSJ.":00");
                    }
                }

                // 手术开始时间、手术结束时间处理初始化
                $insertData['SSKSSJ'] = '';
                $insertData['SSJSSJ'] = '';

                // 手术日期处理
                if ($insertData['SSRQ']) {
                    $insertData['SSRQ'] = str_replace("年","-",$insertData['SSRQ']);
                    $insertData['SSRQ'] = str_replace("月","-",$insertData['SSRQ']);
                    $insertData['SSRQ'] = str_replace("日","",$insertData['SSRQ']);
                }
                $insertData['SSRQ'] = trim($insertData['SSRQ']);

                // 判断手术日期和手术时间是否同时存在
                if ($insertData['SSRQ'] && $insertData['SSSJ']) {
                    // 手术开始时间、手术结束时间处理
                    $SSSJ = str_replace("：",":",$insertData['SSSJ']);
                    $SSSJ = explode('-',$SSSJ);
                    if (!empty($SSSJ[0])) {
                        $insertData['SSKSSJ'] = $insertData['SSRQ'].' '.$SSSJ[0].':00';
                        $insertData['SSKSSJ'] = str_replace(" :",":",$insertData['SSKSSJ']);
                        $insertData['SSKSSJ'] = str_replace("  "," ",$insertData['SSKSSJ']);
                    }
                    if (!empty($SSSJ[1])) {
                        $insertData['SSJSSJ'] = $insertData['SSRQ'].' '.$SSSJ[1].':00';
                        $insertData['SSJSSJ'] = str_replace(" :",":",$insertData['SSJSSJ']);
                        $insertData['SSJSSJ'] = str_replace("  "," ",$insertData['SSJSSJ']);
                    }
                }
                unset($insertData['SSSJ']);

                // 手术名称（取第一个）
                $insertData['SSMC'] = trim($insertData['SSMC']);
                if (stripos($insertData['SSMC'], '1.') !== false) {
                    for ($i=1; $i<=100; $i++) {
                        $key = $i.'.';
                        $key1 = ($i+1).'.';
                        if (stripos($insertData['SSMC'], $key) !== false) {
                            $cyzdArr = explode($key, $insertData['SSMC']);
                            $cyzdArr = explode($key1, $cyzdArr[1]);
                            $nameStr = trim($cyzdArr[0]);
                            $SSMC[] = $nameStr;
                        }
                    }
                } elseif (stripos($insertData['SSMC'], '+') !== false) {
                    $SSMC = explode("+", $insertData['SSMC']);
                } elseif (stripos($insertData['SSMC'], '，') !== false) {
                    $SSMC = explode("，",$insertData['SSMC']);
                } elseif (stripos($insertData['SSMC'], '；') !== false) {
                    $SSMC = explode("；",$insertData['SSMC']);
                } elseif (stripos($insertData['SSMC'], '、') !== false) {
                    $SSMC = explode("、",$insertData['SSMC']);
                } elseif (stripos($insertData['SSMC'], ' ') !== false) {
                    $SSMC = explode(" ",$insertData['SSMC']);
                } else {
                    $SSMC = [$insertData['SSMC']];
                }

                $insertData['SSMC_ONE'] = !empty($SSMC[0]) ? trim($SSMC[0]) : '';
                $insertData['SSMC'] = !empty($SSMC[0]) ? json_encode($SSMC, JSON_UNESCAPED_UNICODE) : '';

                // 术中诊断（取第一个）
                $SZZD = str_replace(";","；",trim($insertData['SZZD']));
                $SZZD_ARR = [];
                if (stripos($SZZD, '1.') !== false) {
                    for ($i=1; $i<=100; $i++) {
                        $key = $i.'.';
                        $key1 = ($i+1).'.';
                        if (stripos($SZZD, $key) !== false) {
                            $cyzdArr = explode($key, $SZZD);
                            $cyzdArr = explode($key1, $cyzdArr[1]);
                            $nameStr = trim($cyzdArr[0]);
                            $SZZD_ARR[] = $nameStr;
                        }
                    }
                } elseif (stripos($SZZD, '+') !== false) {
                    $SZZD_ARR = explode("+", $SZZD);
                } elseif (stripos($SZZD, '，') !== false) {
                    $SZZD_ARR = explode("，",$SZZD);
                } elseif (stripos($SZZD, '；') !== false) {
                    $SZZD_ARR = explode("；",$SZZD);
                } elseif (stripos($SZZD, '、') !== false) {
                    $SZZD_ARR = explode("、",$SZZD);
                } elseif (stripos($SZZD, ' ') !== false) {
                    $SZZD_ARR = explode(" ",$SZZD);
                } else {
                    $SZZD_ARR = [$SZZD];
                }

                $insertData['SZZD_ONE'] = !empty($SZZD_ARR[0]) ? trim($SZZD_ARR[0]) : '';
                $insertData['SZZD'] = !empty($SZZD_ARR[0]) ? json_encode($SZZD_ARR,JSON_UNESCAPED_UNICODE) : '';

                // 数据写入
                BLLB303::query()->updateOrInsert(['BLBH'=>$bl01Info['BLBH']],$insertData);

                $lastBLBH = $bl01Info['BLBH'];
            }
        }

        if (!empty($lastBLBH)) {
            Setting::query()->where('name', '=', $setName)->update(['content' => $lastBLBH]);
        }

        return true;
    }

    /**
     * 手术格式化数据详细内容处理
     * @param $page
     * @return void
     */
    protected function surgeryExtDataFormat($page)
    {
        while (true) {
            // 查询需要格式化的手术记录
            $data = BLLB303::query()
                ->paginate(500, ['id','ZYH','BLBH','SSMC','SZZD'], 'page', $page)
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
                $insertData = [];

                // 手术名称
                if ($val['SSMC']) {
                    $ssmcList = explode('+',$val['SSMC']);
                    foreach ($ssmcList as $name) {
                        $insertData[] = [
                            'bllb303_id' => $val['id'],
                            'ZYH' => $val['ZYH'],
                            'BLBH' => $val['BLBH'],
                            'name' => $name,
                            'type' => 'ssmc'
                        ];
                    }
                }

                // 术中诊断
                if ($val['SZZD']) {
                    $szzdList = explode('；',$val['SZZD']);
                    foreach ($szzdList as $name) {
                        if ($name) {
                            $insertData[] = [
                                'bllb303_id' => $val['id'],
                                'ZYH' => $val['ZYH'],
                                'BLBH' => $val['BLBH'],
                                'name' => $name,
                                'type' => 'szzd'
                            ];
                        }
                    }
                }

                if ($insertData) {
                    BLLB303_EXT::query()->where('bllb303_id','=',$val['id'])->delete();
                    BLLB303_EXT::query()->insert($insertData);
                }
            }
        }
    }

    /**
     * 数据提取
     * @param $hjnr
     * @param $key
     * @return string
     */
    protected function getVal($hjnr,$key)
    {
        $returnData = '';
        $arr = explode($key,$hjnr);
        if (!empty($arr[1])) {
            $newArr = explode("\n",$arr[1]);
            if (!empty(trim($newArr[0]))) {
                $returnData = trim($newArr[0]);
            } elseif (!empty($newArr[1])) {
                $returnData = trim($newArr[1]);
            }
        }

        return $returnData;
    }
}
