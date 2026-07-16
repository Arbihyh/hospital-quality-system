<?php

namespace App\Console\Commands\DataFormat;

use App\Model\MS_BRDA;
use App\Model\Setting;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;

class OMR_BL01 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:omr_bl01 {blbh?} {time?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'omr_bl01数据格式化';

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
        // $this->info('omr_bl01数据格式化 - 数据开始处理');

        $blbh = $this->argument('blbh') ?: '';
        $time = $this->argument('time') ?: date('Y-m-d 00:00:00', strtotime('-1 day'));
        $this->omrBl01Format($blbh, $time);

        // $this->info('omr_bl01数据格式化 - 数据处理完毕');
    }

    /**
     * @param $page
     * @return true
     */
    protected function omrBl01Format($blbh='', $time='')
    {
        // 获取数据库中的最小和最大ID
        $minId = 0;
        $maxId = 1;
        if(empty($blbh)){
            $minId = \App\Model\OMR_BL01::query()->where("JLSJ", ">=", $time)->min('id') ?: 0;
            $maxId = \App\Model\OMR_BL01::query()->max('id') ?: 0;
        }

        // 统计变量
        $totalProcessed = 0;
        $totalUpdated = 0;
        $batchSize = 100; // 每批处理的记录数，可以根据需要调整

        // 从上次处理的ID开始，按批次处理所有数据
        for ($currentId = $minId + 1; $currentId <= $maxId; $currentId += $batchSize) {
            $endId = min($currentId + $batchSize - 1, $maxId);

            // $this->info("处理ID范围: {$currentId} - {$endId}");

            // 查询指定ID范围内的所有记录
            $query = \App\Model\OMR_BL01::query();
            if($blbh){
                $query->where('BLBH', $blbh);
            }else{
                $query->whereBetween('id', [$currentId, $endId]);
            }
            $data = $query->orderBy('id')->get()->toArray();
            if (empty($data)) {
                continue; // 继续处理下一批
            }
            $batchLastId = $currentId; // 记录本批次处理的最后一个ID
            foreach ($data as $value) {
                $totalProcessed++;
                $recordId = $value['id'];
//                $this->info("处理记录ID: " . $recordId . " (总第 " . $totalProcessed . " 条)");

                $saveData = [];

                // 处理病历内容
                $BLNR_TXT = $value['BLNR_TXT'];

                // 门诊病历有两套模板，如果第一个没有清洗出来住院号，则换第二种方法
                $saveData1 = $this->cleanDataFilter($BLNR_TXT, 1);
                if(empty($saveData1['mzh'])){
                    $saveData1 = $this->cleanDataFilterV2($BLNR_TXT, 1);
                }
                if ($saveData1['mzh']) {
                    // 判断$saveData1['mzh']是否包含时间格式字符串，如果包含则只保留时间格式之前的数据
                    // 适用格式如: 2020.01.01 08:00 或 01.02 08:00
                    // 针对 mzh 字符串，如 "110766142025-10-1511:06 首次病历记录"
                    // 需将“2025-10-15”前的内容截取出来（包含空格），即"11076614 "
                    if (preg_match('/^(.*?)(\d{4}-\d{2}-\d{2}\d{2}:\d{2})/', $saveData1['mzh'], $mzhMatches)) {
                        $saveData1['mzh'] = trim($mzhMatches[1]);
                        $saveData1['jzsj'] = trim($mzhMatches[2]);
                    }
                } else {
                    unset($saveData['mzh']);
                }

                if (empty($saveData1['xm'])) {
                    unset($saveData['xm']);
                }

                $saveData = array_merge($saveData, $saveData1);
                if (!empty($saveData['xb'])) {
                    $saveData['xb'] = str_replace('性', '', $saveData['xb']);
                } else {
                    unset($saveData['xb']);
                }
                if (!empty($saveData['jzsj'])) {
                    $saveData['jzsj'] = substr($saveData['jzsj'], 0, 10) . ' ' . substr($saveData['jzsj'], 10);
                }

                // 年龄处理 - 使用更宽松的正则表达式
                if (!empty($saveData['nl'])) {
                    $nl = trim($saveData['nl']);
                     $this->info("提取到年龄: " . $nl);
                    $saveData['nl1'] = mb_substr($nl, 0, 20);

                    if (stripos($nl, '岁') !== false) {
                        if (preg_match('/(\d+)岁/', $nl, $matches)) {
                            $saveData['nl'] = $matches[1];
                        } else {
                            $saveData['nl'] = preg_replace('/[^0-9]/', '', $nl);
                        }
                    } else if (stripos($nl, '月') !== false) {
                        $saveData['nl'] = '0';
                        $saveData['nl1'] = mb_substr($nl, 0, 20);
                    } else if (stripos($nl, '天') !== false) {
                        $saveData['nl'] = '0';
                        $saveData['nl_day'] = preg_replace('/[^0-9]/', '', $nl);
                    } else if (stripos($nl, '小时') !== false) {
                        $saveData['nl'] = '0';
                        $saveData['nl_hour'] = preg_replace('/[^0-9]/', '', $nl);
                    } else if (stripos($nl, '分钟') !== false) {
                        $saveData['nl'] = '0';
                        $saveData['nl_minute'] = preg_replace('/[^0-9]/', '', $nl);
                    } else {
                        $saveData['nl'] = '0';
                    }
                } else {
                    $this->error("未能提取到年龄");
                    unset($saveData['jzsj']);
                }

                // 西药处理
                if (preg_match('/&lt;西药&gt;(.*?)}/s', $BLNR_TXT, $matches)) {
                    $xyContent = $matches[1];
                    $saveData['xy'] = trim($xyContent);

                    $xyLines = preg_split('/\s+/', trim($xyContent));
                    $xyList = [];

                    // 每3个元素为一组药品信息
                    for ($i = 0; $i < count($xyLines); $i += 3) {
                        if (isset($xyLines[$i]) && !empty(trim($xyLines[$i]))) {
                            $xyList[] = [
                                'ym' => $xyLines[$i] ?? '',
                                'yl' => $xyLines[$i + 1] ?? '',
                                'yf' => $xyLines[$i + 2] ?? '',
                                'pc' => '',
                            ];
                        }
                    }

                    if ($xyList) {
                        $saveData['xy_json'] = json_encode($xyList, JSON_UNESCAPED_UNICODE);
                    }
                }
                // 提醒
                if (preg_match('/※提醒：(.*?)※/s', $BLNR_TXT, $matches)) {
                    $saveData['tx'] = trim($matches[1]);
                }

                // 初诊、复诊、急诊
                $saveData['bl_type'] = '门诊';
                if (stripos($BLNR_TXT, '门(急)诊病历') !== false) {
                    $saveData['bl_type'] = '门诊';
                } elseif (stripos($BLNR_TXT, '初诊') !== false) {
                    $saveData['bl_type'] = '初诊';
                } elseif (stripos($BLNR_TXT, '复诊') !== false) {
                    $saveData['bl_type'] = '复诊';
                } elseif (stripos($BLNR_TXT, '急诊') !== false) {
                    $saveData['bl_type'] = '急诊';
                }

                // 身份证号获取
                $msBrdaData = MS_BRDA::query()->where('BRID', '=', $value['BRID'])->first();
                if (!empty($msBrdaData)) {
                    $saveData['SFZH'] = desensitize($msBrdaData->SFZH, 6, 8, '*');
                }
                // 打印要更新的数据
                // $this->info("要更新的数据: " . json_encode($saveData, JSON_UNESCAPED_UNICODE));

                if ($saveData) {
                    $result = \App\Model\OMR_BL01::query()->where('id', '=', $recordId)->update($saveData);
                    if ($result) {
                        $this->esSave(array_merge($saveData, $value));
                        $totalUpdated++;
                    }
                }

                $batchLastId = max($batchLastId, $recordId); // 更新本批次的最后ID
            }
            if($blbh){
                break;
            }

        }

        return true;
    }

    function esSave($item=[]) {
        $index = 'omr_bl01_2023';
        $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['BLBH']]];
        $es_params['body'][] = ['doc' => [
            "BLBH" => $item['BLBH'],
            "BLLB" => $item['BLLB'],
            "BLLX" => $item['BLLX'],
            "BLMC" => $item['BLMC'],
            "BLNR_TXT" => $item['BLNR_TXT'],
            "BLZT" => $item['BLZT'],
            "BRID" => $item['BRID'],
            "BRKS" => $item['BRKS'],
            "CJSJ" => $item['CJSJ'],
            "DLJ" => $item['DLJ'],
            "DLLB" => $item['DLLB'],
            "JLSJ" => $item['JLSJ'],
            "JZXH" => $item['JZXH'],
            "SFZH" => $item['SFZH'],
            "SXKS" => $item['SXKS'],
            "SXYS" => $item['SXYS'],
            "WCSJ" => $item['WCSJ'],
            "cbzd" => $item['cbzd'],
            "data_id" => $item['id'],
            "fzjc" => $item['fzjc'],
            "id" => $item['id'],
            "is_defect" => $item['is_defect'],
            "jws" => $item['jws'],
            "jzsj" => platformTime($item['jzsj']),
            "ks" => $item['ks'],
            "lxbxs" => $item['lxbxs'],
            "mzh" => $item['mzh'],
            "nl" => $item['nl'],
            "nl1" => $item['nl1'],
            "tgjc" => $item['tgjc'],
            "tx" => $item['tx'],
            "xb" => $item['xb'],
            "xbs" => $item['xbs'],
            "xm" => $item['xm'],
            "xy" => $item['xy'],
            "xy_json" => $item['xy_json'],
            "zlyj" => $item['zlyj'],
            "zs" => $item['zs'],
        ], 'doc_as_upsert' => true];

        $res = app('es')->bulk($es_params);
        if ($res['errors'] == true) {
            throw new \Exception($res['items'][0]['update']['error']['reason']);
        }
    }

    public function cleanDataFilter($hjnr = "", $filter = 1)
    {
        $hjnr = str_replace('处理意见', '诊疗意见', $hjnr);
        $keyword = ['xm|姓名:','xb|性别:','nl|年龄:','|联系方式','ks|科室:', '|T:', 'mzh|病历号:','jzsj|就诊时间:','ks|就诊科室:', 'zs|主诉:','xbs|现病史:','jws|既往史:','grs|个人史:','jzs|家族史:','tgjc|体格检查:','|专科检查:','cbzd|诊断:','zlyj|诊疗意见:', '|&lt;西药&gt' ,'|医师签名' ,'|医师签名', '|麻醉方式:','|手术日期', '|留抢时间', '|初诊', '|复诊', '|急诊'];
        $data = [];
        foreach ($keyword as $k) {
            $tmp = explode("|", $k);
            if (empty($tmp[0])) {
                continue;
            }
            if (empty($data[$tmp[0]])) {
                $data[$tmp[0]] = $this->getContent($keyword, $hjnr, $tmp[1], $filter);
            }
        }

        return $data;
    }

    function cleanDataFilterV2($hjnr = "", $filter = 1)
    {
        $hjnr = str_replace("诊疗意见", "处理意见", $hjnr);
        if (strpos($hjnr, "初步诊断") === false) {
            $hjnr = str_replace("诊断:", "初步诊断:", $hjnr);
        }
       
        $keyword = ['zs|主诉:','xbs|现病史:','jws|既往史:','tgjc|体格检查:','fzjc|辅助检查:','cbzd|初步诊断:','zlyj|处理意见:','|※提醒:','|医师签名', 'mzh|门诊号:','|济南市第三人民医院', 'xm|姓名:','xb|性别:','jzsj|就诊时间:','|医院名称', 'ks|科室:','ks|就诊科室:','nl|年龄:','|联系方式', '|初诊', '|复诊', '|急诊'];
        $data = [];
        foreach ($keyword as $k) {
            $tmp = explode("|", $k);
            if(empty($tmp[0])){
                continue;
            }
            if (empty($data[$tmp[0]])) {
                $data[$tmp[0]] = $this->getContent($keyword, $hjnr, $tmp[1], $filter);
            }
            
        }

        return $data;
    }

    function getContent($ruleMapArr, $str, $field, $filter = 1)
    {
        // 去除所有空格
        $str = preg_replace("/\s+/", "", $str);
        $field = preg_replace("/\s+/", "", $field);

        // 是否过滤小大括号
        if ($filter == 1) {
            $str = str_replace(["{", "}"], "", $str);
        }

        $str = str_replace("：", ":", $str);
        // 1. 先找到指定字段的位置
        $start = mb_strpos($str, "{$field}");
        if ($start === false) {
            return "";
        }

        // 2. 起始位置（跳过字段名和冒号）
        $start += mb_strlen($field);

        // 3. 查找下一个分隔点
        $end = PHP_INT_MAX;


        foreach ($ruleMapArr as $k) {
            $tmp = explode("|", $k);
            $keyword = $tmp[1];
            $keyword = preg_replace("/\s+/", "", $keyword);

            //如果字符串长度小于等于 $start 直接复制字符串长度 避免报错
            $pos = mb_strlen($str) <= $start ? mb_strlen($str) : mb_strpos($str, $keyword, $start);

            if ($pos !== false && $pos > $start && $pos < $end) {
                $end = $pos;
            } elseif ($pos !== false && $end == PHP_INT_MAX) {
                $end = $pos;
            }
        }

        // 4. 提取内容
        if ($end === PHP_INT_MAX) {
            $content = trim(mb_substr($str, $start));
        } else {
            $content = trim(mb_substr($str, $start, $end - $start));
        }
        return $content;
    }
}
