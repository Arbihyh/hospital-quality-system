<?php

namespace App\Console\Commands;

use App\Model\EMR_BL_BL01;
use App\Model\Setting;
use Illuminate\Console\Command;
use App\Model\RuleWordMap;

class CleanBLMC_294_3 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bl:cleanblmc_294_3';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清洗bl01表中病程记录(BLLB=294)的BLMC，从BLXG表的HJNR里面获取表头更新到MLMC字段';

    public static $con;

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
        echo "bl:cleanblmc_294 start" . date('Y-m-d H:i:s') . PHP_EOL;
        $last_id = Setting::query()->where('name', 'cleanblmc_294')->value('content');
        $cleanblmckey = RuleWordMap::query()->where('id', '4018')->value('keyword');

        $last_id = $last_id ?? 0;
        $pageSize = 1000;

        $field = ['EMR_BL_BL01.id', 'EMR_BL_BL01.BLBH', 'EMR_BL_BL01.BLMC', 'EMR_BL_BLXG.HJNR'];

        if (strpos($cleanblmckey, ',') !== false) {
            $cleanblmckey = explode(',', $cleanblmckey);
        } else {
            $cleanblmckey = [$cleanblmckey];
        }
        //获取bl01的最后一条记录的id,只获取最大id
        $z_id = EMR_BL_BL01::query()->whereIn('BLLB', [294, 43])->max('id');
        while (true) {
            echo "Processing from ID: {$last_id}, batch size: {$pageSize}" . PHP_EOL;

            $query = EMR_BL_BL01::query()
                ->whereIn('EMR_BL_BL01.BLLB', [294, 43])
                ->where('EMR_BL_BL01.id', '>', $last_id)
                ->where('EMR_BL_BL01.id', '<=', $last_id + $pageSize)
                ->where('EMR_BL_BL01.BLBH', '!=', 0)
                ->orderBy('EMR_BL_BL01.id', 'asc')
                ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BLXG.BLBH', '=', 'EMR_BL_BL01.BLBH');

            $data = $query->take($pageSize)->get($field)->toArray();

            if ($z_id <= $last_id) {
                echo "No more data found, breaking loop" . PHP_EOL;
                break;
            } else {
                if (empty($data)) {
                    $last_id = $last_id + $pageSize;
                    continue;
                }
            }

            $currentBatch = array_column($data, 'id');
            echo "Processing IDs in current batch: " . implode(', ', $currentBatch) . PHP_EOL;

            foreach ($data as $v) {
                echo $v['id'] . PHP_EOL;
                $blmc = $v['BLMC'];
                var_dump('blmc:' . $blmc);
                var_dump('hjnr:' . $v['HJNR']);
                // 处理BLMC，去掉包含cleanblmckey的
                foreach ($cleanblmckey as $key) {
                    $blmc = str_replace($key, '', $blmc);
                }
                if (!empty($v['HJNR'])) {
                    // 从HJNR中提取时间  2024-05-14 09:00:00
                    preg_match("/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/", $v['HJNR'], $timeMatches);
                    //增加格式匹配 '时间：2024-05-1411:33:12 姓名：性别：女
                    preg_match("/^时间：(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", $v['HJNR'], $timeMatches2);
                    //增加格式匹配 '2024-05-14 11:33'不包含秒
                    preg_match("/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", $v['HJNR'], $timeMatches3);
                    //增加格式匹配 '{2024-08-01 19:20}'花括号包围的时间格式
                    preg_match("/\{(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\}/", $v['HJNR'], $timeMatches4);
                    //增加格式匹配 '病案号:{00347580}2024-07-31 10:12'病案号后面的时间格式
                    preg_match("/病案号:[\{\(]?\d+[\}\)]?(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", $v['HJNR'], $timeMatches5);
                    //增加格式匹配 '重症医学二科病案号:003703542025-04-14 14:53危急值记录' 从取出2025-04-14 14:53
                    preg_match("/重症医学二科病案号:[\{\(]?\d+[\}\)]?(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", $v['HJNR'], $timeMatches6);
                    $timePrefix = '';
                    if (!empty($timeMatches[1])) {
                        $timePrefix = $timeMatches[1];
                    } else if (!empty($timeMatches2[1])) {
                        $timePrefix = $timeMatches2[1];
                    } else if (!empty($timeMatches3[1])) {
                        $timePrefix = $timeMatches3[1];
                    } else if (!empty($timeMatches4[1])) {
                        $timePrefix = $timeMatches4[1];
                    } else if (!empty($timeMatches5[1])) {
                        $timePrefix = $timeMatches5[1];
                    } else if (!empty($timeMatches6[1])) {
                        $timePrefix = $timeMatches6[1];
                    }

                    if ($timePrefix != '') {
                        // 检查BLMC中是否已包含重复的时间格式
                        // 先检查是否包含完全相同的时间格式
                        $pattern = "/(\d{4}-\d{2}-\d{2} \d{2}:\d{2})([ ]+)\\1/";
                        if (preg_match($pattern, $blmc)) {
                            // 如果存在重复的时间格式，去除重复部分
                            $blmc = preg_replace($pattern, "$1", $blmc);
                        }

                        //判断blmc是否包含时间格式,如果已经包含时间格式,跟timePrefix对比,如果相同就不改变,如果不同就替换
                        $newblmc = $blmc;


                        //如果原本的blmc中包含01.02 08:09这种格式，先删除
                        $blmc = preg_replace("/(\d{2}\.\d{2} \d{2}:\d{2})/", "", $blmc);

                        //如果blmc中包含2025.01.02 08:09这种格式，先删除
                        $blmc = preg_replace("/(\d{4}\.\d{2}\.\d{2} \d{2}:\d{2})/", "", $blmc);

                        //如果blmc中包含2025-01-02 08:09这种格式，先删除
                        $blmc = preg_replace("/(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", "", $blmc);

                        //判断blmc是否包含时间格式
                        preg_match("/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/", $blmc, $timeMatches1);
                        //最后删除所有空格
                        $blmc = preg_replace("/\s+/", "", $blmc);
                        // 增加对不带秒的时间格式的检查
                        if (empty($timeMatches1[1])) {
                            preg_match("/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", $blmc, $timeMatches1);
                        }

                        if (!empty($timeMatches1[1])) {
                            if ($timeMatches1[1] != $timePrefix) {
                                //替换blmc中的时间格式
                                $newblmc = str_replace($timeMatches1[1], $timePrefix, $blmc);
                            }
                        } else {
                            $newblmc = $timePrefix . ' ' . $blmc;
                        }
                        EMR_BL_BL01::query()->where('BLBH', $v['BLBH'])->update(['BLMC' => $newblmc, 'ZXSJ' => $timePrefix]);
                    } else {
                        EMR_BL_BL01::query()->where('BLBH', $v['BLBH'])->update(['BLMC' => $blmc]);
                    }
                } else {
                    EMR_BL_BL01::query()->where('BLBH', $v['BLBH'])->update(['BLMC' => $blmc]);
                }
            }

            $last_id = end($data)['id'];
            echo "Batch completed, last_id updated to: {$last_id}" . PHP_EOL;

            Setting::query()->where('name', 'cleanblmc_294')->update(['content' => $last_id]);
        }

        echo "bl:cleanblmc_294 end" . date('Y-m-d H:i:s') . PHP_EOL;
        exit();
    }

    public function getMatchResult($hjnr)
    {
        $result = '';
        if (!empty($hjnr)) {
            // 从HJNR中提取时间，修改正则表达式以包含秒
            preg_match("/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/", $hjnr, $timeMatches);
            $timePrefix = !empty($timeMatches[1]) ? $timeMatches[1] : '';

            // 如果获取到了时间前缀，则组合新的结果
            if ($timePrefix) {
                $result = $timePrefix;
            }
        }
        return $result;
    }
}
