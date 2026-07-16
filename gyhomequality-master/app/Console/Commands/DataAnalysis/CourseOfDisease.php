<?php

namespace App\Console\Commands\DataAnalysis;

use App\Model\EMR_BL_BL01;
use App\Model\Setting;
use Illuminate\Console\Command;

class CourseOfDisease extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:bc {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '病程数据格式化';

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
        $this->info('病程数据格式化 - 开始');

        $setName = 'gsh_bl01_bc';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        $page = (int)$this->argument('page') ?: 1;
        while (true) {
            $dataList = EMR_BL_BL01::query()
                ->Join("EMR_BL_BLXG", 'EMR_BL_BL01.blbh', '=', 'EMR_BL_BLXG.BLBH')
                ->where('BLLB', '=', 294)
                ->where('BLZT', '!=', 9)
                ->where('EMR_BL_BL01.BLBH','>',$lastId)
                ->orderBy('EMR_BL_BL01.BLBH')
                ->paginate(500, ['EMR_BL_BL01.BLBH', 'JZHM', 'BLMC', 'BRBH', 'BRXM', 'HJNR'], 'page', $page)
                ->toArray();

            if ($page == 1) {
                echo '数据总条数：'.$dataList['total'].PHP_EOL.'总页数：'.$dataList['last_page'].PHP_EOL;
            } elseif ($page > $dataList['last_page']) {
                // 如果当前页码大于最大页码则结束
                break;
            }
            echo $page.PHP_EOL;
            $page++;

            $bl01Data = $dataList['data'];
            foreach ($bl01Data as $bl01) {
                $lastId = $bl01['BLBH'];
                echo $lastId.PHP_EOL;

                $surgeryType = $this->getBcType($bl01['HJNR']);
                switch ($surgeryType) {
                    case '术前小结及术前讨论结论记录':
                        $this->sqxjjl($bl01);
                        break;
                    case '术后首次病程记录':
                        $this->shscbcjl($bl01);
                        break;
                    case '首次病程记录':
                        $this->scbcjl($bl01);
                        break;
                    case '查房记录':
                        $this->cfjl($bl01);
                        break;
                    case '转出记录':
                        $this->zcjl($bl01);
                        break;
                    case '转入记录':
                        $this->zrjl($bl01);
                        break;
                    default:
                        break;
                }
            }

            Setting::query()->updateOrInsert(['name'=>$setName],['content' => $lastId]);
        }

        $this->info('病程数据格式化 - 完毕');
    }

    /**
     * 转出记录
     * @param $bl01
     * @return true
     */
    public function zcjl($bl01)
    {
        $HJNR = $bl01['HJNR'];
        $saveData['type'] = 5;

        // 转出时间
        $arr = explode("转出记录",$HJNR);
        $saveData['zc_time'] = !empty($arr[0]) ? trim($arr[0]) : '';

        // 患者信息
        $arr[1] = str_replace(":","：",$arr[1]);
        $array = explode('入院情况：',$arr[1]);
        $saveData['user_info'] = trim($array[0]);

        $HJNR = str_replace(":","：",$HJNR);

        // 入院情况
        $arr = explode('入院情况：',$HJNR);
        $saveData['ryqk'] = '';
        if (!empty($arr[1])) {
            $arr = explode('入院诊断：',$arr[1]);
            $saveData['ryqk'] = trim($arr[0]);
        }

        // 入院诊断
        $arr = explode('入院诊断：',$HJNR);
        $saveData['ryzd'] = '';
        if (!empty($arr[1])) {
            $arr = explode('诊疗经过：',$arr[1]);
//            $ryzd = explode(" ",$arr[0]);
//            $ryzd = array_filter($ryzd);
//            sort($ryzd);
            $saveData['ryzd'] = trim($arr[0]);
        }

        // 诊疗经过
        $arr = explode('诊疗经过：',$HJNR);
        $saveData['zljg'] = '';
        if (!empty($arr[1])) {
            $arr = explode('目前情况：',$arr[1]);
            $saveData['zljg'] = trim($arr[0]);
        }

        // 目前情况
        $arr = explode('目前情况：',$HJNR);
        $saveData['mqqk'] = '';
        if (!empty($arr[1])) {
            $arr = explode('目前诊断：',$arr[1]);
            $saveData['mqqk'] = trim($arr[0]);
        }

        // 目前诊断
        $arr = explode('目前诊断：',$HJNR);
        $saveData['mqzd'] = '';
        if (!empty($arr[1])) {
            $arr = explode('转科目的及注意事项：',$arr[1]);
            $saveData['mqzd'] = explode(" ",trim($arr[0]));
        }

        // 转科目的及注意事项
        $arr = explode('转科目的及注意事项：',$HJNR);
        $saveData['zysx'] = '';
        if (!empty($arr[1])) {
            $arr = explode('上级医师审核日期：',$arr[1]);
            $saveData['zysx'] = trim($arr[0]);
        }

        // 上级医师审核日期
        $arr = explode('上级医师审核日期：',$HJNR);
        $saveData['shrq'] = !empty($arr[1]) ? trim($arr[1]) : '';

        EMR_BL_BL01::query()
            ->where('BLBH','=',$bl01['BLBH'])
            ->update(['bingcheng_content'=>json_encode($saveData,JSON_UNESCAPED_UNICODE)]);

        // 要同步到Es的数据
        $es_params['body'][] = ['update' => ['_index' => 'bl01_2023', '_id' => $bl01['BLBH']]];
        $es_params['body'][] = ['doc' => [
            "bingcheng_content" => json_encode($saveData, JSON_UNESCAPED_UNICODE)
        ], 'doc_as_upsert' => true];
//        app('es')->bulk($es_params);

        return true;
    }

    /**
     * 转入记录
     * @param $bl01
     * @return true
     */
    public function zrjl($bl01)
    {
        $HJNR = $bl01['HJNR'];
        $saveData['type'] = 6;

        // 转入记录
        $arr = explode("转入记录",$HJNR);
        $saveData['zr_time'] = !empty($arr[0]) ? trim($arr[0]) : '';

        // 患者信息
        $arr[1] = str_replace(":","：",$arr[1]);
        $array = explode('目前情况：',$arr[1]);
        $saveData['zrjl'] = trim($array[0]);

        $HJNR = str_replace(":","：",$HJNR);

        // 目前情况
        $arr = explode('目前情况：',$HJNR);
        $saveData['mqqk'] = '';
        if (!empty($arr[1])) {
            $arr = explode('目前诊断：',$arr[1]);
            $saveData['mqqk'] = trim($arr[0]);
        }

        // 目前诊断
        $arr = explode('目前诊断：',$HJNR);
        $saveData['mqzd'] = '';
        if (!empty($arr[1])) {
            $arr = explode('转入诊疗计划：',$arr[1]);
            $mqzd = explode(" ",trim($arr[0]));
            $mqzd = array_filter($mqzd);
            sort($mqzd);
            $saveData['mqzd'] = $mqzd;
        }

        // 转入诊疗计划
        $arr = explode('转入诊疗计划：',$HJNR);
        $saveData['zljh'] = '';
        if (!empty($arr[1])) {
            $arr = explode('上级医师审核日期：',$arr[1]);
            $saveData['zljh'] = explode("\n",trim($arr[0]));
        }

        // 上级医师审核日期
        $arr = explode('上级医师审核日期：',$HJNR);
        $saveData['shrq'] = !empty($arr[1]) ? trim($arr[1]) : '';

        EMR_BL_BL01::query()
            ->where('BLBH','=',$bl01['BLBH'])
            ->update(['bingcheng_content'=>json_encode($saveData,JSON_UNESCAPED_UNICODE)]);

        // 要同步到Es的数据
        $es_params['body'][] = ['update' => ['_index' => 'bl01_2023', '_id' => $bl01['BLBH']]];
        $es_params['body'][] = ['doc' => [
            "bingcheng_content" => json_encode($saveData, JSON_UNESCAPED_UNICODE)
        ], 'doc_as_upsert' => true];
//        app('es')->bulk($es_params);

        return true;
    }

    /**
     * 首次病程记录
     * @param $bl01
     * @return true
     */
    protected function scbcjl($bl01)
    {
        $hjnr = str_replace(':','：',$bl01['HJNR']);
        $insertData = [
            'type' => 1,
            'date' => '',
            'title' => '首次病程记录',
            'keshi' => '', // :todo 暂无该字段
            'brxm' => $bl01['BRXM'],
            'ch' => '', // :todo 暂无该字段
            'brbh' => $bl01['BRBH'],
            'ZLJH' => [],
        ];

        // 病程日期
        $arr = explode("\n",$hjnr);
        if (!empty($arr)) {
            $arr = explode("首次病程记录",$arr[0]);
            if (!empty($arr)) {
                $insertData['date'] = trim($arr[0]);
            }
        }

        $keyList = [
            'BLTD' => ['病例特点：','初步诊断：'],
            'CBZD' => ['初步诊断：','诊断依据：'],
            'ZDYJ' => ['诊断依据：','鉴别诊断：'],
            'JBZD' => ['鉴别诊断：','诊疗计划：'],
        ];

        foreach ($keyList as $key => $value) {
            // 格式化数据
            $str = $this->keyGetValue($value[0],$value[1],$hjnr);
            $array = explode("\n",$str);
            $insertData[$key] = $array;
        }

        // 诊疗计划
        $arr = explode("诊疗计划：",$hjnr);
        if (!empty($arr[1])) {
            $str = $arr[1];
            if (!empty($arr[2])) {
                $arr = explode("上级医师审核日期：",$arr[2]);
                if (!empty($arr[0])) {
                    $array = explode("\n",trim($arr[0]));
                    $insertData['ZLJH'] = $array;
                }
            } else {
                $arr = explode("上级医师审核日期：",$str);
                $array = explode("\n",trim($arr[0]));
                $insertData['ZLJH'] = $array;
            }
        }

        // 审核日期
        $arr = explode("审核日期：",$hjnr);
        if (!empty($arr[1])) {
            $insertData['SHRQ'] = trim($arr[1]);
        }

        EMR_BL_BL01::query()
            ->where('BLBH','=',$bl01['BLBH'])
            ->update(['bingcheng_content'=>json_encode($insertData,JSON_UNESCAPED_UNICODE)]);

        // 要同步到Es的数据
        $es_params['body'][] = ['update' => ['_index' => 'bl01_2023', '_id' => $bl01['BLBH']]];
        $es_params['body'][] = ['doc' => [
            "bingcheng_content" => json_encode($insertData, JSON_UNESCAPED_UNICODE)
        ], 'doc_as_upsert' => true];
//        app('es')->bulk($es_params);

        return true;
    }

    /**
     * 术前小结及术前讨论结论记录
     * @param $bl01
     * @return true
     */
    protected function sqxjjl($bl01)
    {
        $hjnr = str_replace(':','：',$bl01['HJNR']);
        $insertData = [
            'type' => 2,
            'date' => '',
            'title' => '术前小结及术前讨论结论记录',
            'keshi' => '', // :todo 暂无该字段
            'brxm' => $bl01['BRXM'],
            'ch' => '', // :todo 暂无该字段
            'brbh' => $bl01['BRBH'],
            'SSZC' => '',
        ];

        // 病程日期
        $arr = explode("\n",$hjnr);
        if (!empty($arr)) {
            $arr = explode("术前小结及术前讨论结论记录",$arr[0]);
            if (!empty($arr)) {
                $insertData['date'] = trim($arr[0]);
            }
        }

        // 术前讨论由
        $arr = explode("术前讨论由",$hjnr);
        if (!empty($arr[1])) {
            $arr = explode("医师主持，",$arr[1]);
            if (!empty($arr)) {
                $insertData['SSZC'] = str_replace('_','',$arr[0]);
            }
        }

        $keyList = [
            'JYBQ' => ['简要病情：','术前诊断：'],
            'SQZD' => ['术前诊断：','手术指征：'],
            'SSZZ' => ['手术指征：','拟施手术名称和方式：'],
            'NSSS' => ['拟施手术名称和方式：','拟施麻醉方式：'],
            'NSMZ' => ['拟施麻醉方式：',"术前准备："],
            'desc' => ['术前准备：','术中注意事项：'],
            'SZZY' => ['术中注意事项：','术后处理：'],
            'SHCL' => ['术后处理：','手术者术前查看患者相关情况：'],
        ];

        foreach ($keyList as $key => $value) {
            // 格式化数据
            $str = $this->keyGetValue($value[0],$value[1],$hjnr);
            $array = explode("\n",$str);
            $insertData[$key] = $array;
        }

        $arr = explode("手术者术前查看患者相关情况：",$hjnr);
        $insertData['OTHER'] = '';
        if (!empty($arr[1])) {
            $insertData['OTHER'] = [trim($arr[1])];
        }

        EMR_BL_BL01::query()
            ->where('BLBH','=',$bl01['BLBH'])
            ->update(['bingcheng_content'=>json_encode($insertData,JSON_UNESCAPED_UNICODE)]);

        // 要同步到Es的数据
        $es_params['body'][] = ['update' => ['_index' => 'bl01_2023', '_id' => $bl01['BLBH']]];
        $es_params['body'][] = ['doc' => [
            "bingcheng_content" => json_encode($insertData, JSON_UNESCAPED_UNICODE)
        ], 'doc_as_upsert' => true];
//        app('es')->bulk($es_params);

        return true;
    }

    /**
     * 术后首次病程记录
     * @param $bl01
     * @return true
     */
    protected function shscbcjl($bl01)
    {
        $hjnr = str_replace(':','：',$bl01['HJNR']);
        $insertData = [
            'type' => 3,
            'date' => '',
            'title' => '术后首次病程记录',
            'keshi' => '', // :todo 暂无该字段
            'brxm' => $bl01['BRXM'],
            'ch' => '', // :todo 暂无该字段
            'brbh' => $bl01['BRBH'],
            'desc' => [],
            'ysqz' => '',
        ];

        // 病程日期
        $arr = explode("\n",$hjnr);
        if (!empty($arr)) {
            $arr = explode("术后首次病程记录",$arr[0]);
            if (!empty($arr)) {
                $insertData['date'] = trim($arr[0]);
            }
        }

        $arr = explode('术后首次病程记录',$hjnr);
        if (!empty($arr[1])) {
            $arr = explode('医师签字：',$arr[1]);
            if (!empty($arr)) {
                $array = explode("\n",trim($arr[0]));
                $insertData['desc'] = $array;
            }
        }

        $arr = explode('医师签字：',$hjnr);
        if (!empty($arr[1])) {
            $insertData['ysqz'] = trim($arr[1]);
        }

        EMR_BL_BL01::query()
            ->where('BLBH','=',$bl01['BLBH'])
            ->update(['bingcheng_content'=>json_encode($insertData,JSON_UNESCAPED_UNICODE)]);

        // 要同步到Es的数据
        $es_params['body'][] = ['update' => ['_index' => 'bl01_2023', '_id' => $bl01['BLBH']]];
        $es_params['body'][] = ['doc' => [
            "bingcheng_content" => json_encode($insertData, JSON_UNESCAPED_UNICODE)
        ], 'doc_as_upsert' => true];
//        app('es')->bulk($es_params);

        return true;
    }

    /**
     * 查房记录
     * @param $bl01
     * @return true
     */
    protected function cfjl($bl01)
    {
        $hjnr = str_replace('：',':',$bl01['HJNR']);
        $insertData = [
            'type' => 4,
            'date' => '',
            'title' => '查房记录',
            'keshi' => '', // :todo 暂无该字段
            'brxm' => $bl01['BRXM'],
            'ch' => '', // :todo 暂无该字段
            'brbh' => $bl01['BRBH'],
        ];

        // 病程日期
        $arr = explode("\n",$hjnr);
        if (!empty($arr)) {
            $insertData['date'] = mb_substr($arr[0],0,17);
            $insertData['title'] = trim(mb_substr($arr[0],17));
        }

        // 描述
        $insertData['desc'] = !empty($arr[1]) ? [trim($arr[1])] : [];

        // 上级医师审核日期
        $arr = explode('上级医师审核日期:',$hjnr);
        $insertData['SHRQ'] = !empty($arr[1]) ? trim($arr[1]) : '';

        EMR_BL_BL01::query()
            ->where('BLBH','=',$bl01['BLBH'])
            ->update(['bingcheng_content'=>json_encode($insertData,JSON_UNESCAPED_UNICODE)]);

        // 要同步到Es的数据
        $es_params['body'][] = ['update' => ['_index' => 'bl01_2023', '_id' => $bl01['BLBH']]];
        $es_params['body'][] = ['doc' => [
            "bingcheng_content" => json_encode($insertData, JSON_UNESCAPED_UNICODE)
        ], 'doc_as_upsert' => true];
//        app('es')->bulk($es_params);

        return true;

    }

    /**
     * 匹配出格式化数据
     * @param $key1
     * @param $key2
     * @param $hjnr
     * @return string
     */
    protected function keyGetValue($key1,$key2,$hjnr)
    {
        $arr = explode($key1,$hjnr);
        $str = '';
        if (!empty($arr[1])) {
            $arr = explode($key2,$arr[1]);
            if (!empty($arr[1])) {
                $str = trim($arr[0]);
            }
        }
        return $str;
    }

    /**
     * 匹配出病程类型
     * @param $hjnr
     * @return string
     */
    protected function getBcType($hjnr)
    {
        $surgeryType = '';

        if (stripos($hjnr,'术前小结及术前讨论结论记录')) {
            $surgeryType = '术前小结及术前讨论结论记录';
        } elseif (stripos($hjnr,'术后首次病程记录')) {
            $surgeryType = '术后首次病程记录';
        } elseif (stripos($hjnr,'首次病程记录')) {
            $surgeryType = '首次病程记录';
        } elseif (stripos($hjnr,'查房记录')) {
            $surgeryType = '查房记录';
        } elseif (stripos($hjnr,'转入记录')) {
            $surgeryType = '转入记录';
        } elseif (stripos($hjnr,'转出记录')) {
            $surgeryType = '转出记录';
        }

        return $surgeryType;
    }



}
