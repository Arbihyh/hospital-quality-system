<?php

namespace App\Console\Commands;

use App\Model\EMR_BL_BL01;
use App\Model\Setting;
use Illuminate\Console\Command;
use App\Model\RuleWordMap;

class CleanBLMC_303 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bl:cleanblmc_303';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '无限循环清洗bl01表中所有数据的BLMC，当BLMC和NA_MED相同时进行清洗，否则跳过';

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
        echo "bl:cleanblmc_303 start " . date('Y-m-d H:i:s') . PHP_EOL;
        $last_id = Setting::query()->where('name', 'cleanblmc_303')->value('content');

        $last_id = $last_id ?? 0;
        $pageSize = 1000;

        $field = ['EMR_BL_BL01.id', 'EMR_BL_BL01.BLBH', 'EMR_BL_BL01.BLMC', 'EMR_BL_BL01.NA_MED', 'EMR_BL_BL01.CJSJ'];

        while (true) {
            // 获取bl01的最后一条记录的id,只获取最大id
            $z_id = EMR_BL_BL01::query()->max('id');
            
            echo "Processing from ID: {$last_id}, batch size: {$pageSize}, max ID: {$z_id}" . PHP_EOL;

            $query = EMR_BL_BL01::query()
                ->where('EMR_BL_BL01.id', '>', $last_id)
                ->where('EMR_BL_BL01.id', '<=', $last_id + $pageSize)
                ->whereIn('EMR_BL_BL01.BLLB', [303, 43, 294])
                ->where('EMR_BL_BL01.BLBH', '!=', 0)
                ->orderBy('EMR_BL_BL01.id', 'asc');

            $data = $query->take($pageSize)->get($field)->toArray();

            if ($z_id <= $last_id) {
                echo "一轮清洗完成，重置ID，开始新一轮清洗 " . date('Y-m-d H:i:s') . PHP_EOL;
                $last_id = 0;
                //等待1小时
                echo "等待1小时后，开始新一轮清洗 " . date('Y-m-d H:i:s') . PHP_EOL;
                sleep(3600);
                
                Setting::query()->where('name', 'cleanblmc_303')->update(['content' => $last_id]);
                continue;
            } else {
                if (empty($data)) {
                    $last_id = $last_id + $pageSize;
                    continue;
                }
            }

            $currentBatch = array_column($data, 'id');
            echo "Processing IDs in current batch: " . implode(', ', $currentBatch) . PHP_EOL;
            
            $processed = 0;
            $skipped = 0;

            foreach ($data as $v) {
                echo $v['id'] . PHP_EOL;
                
                // 检查BLMC和NA_MED是否相同，如果不同则跳过
                if ($v['BLMC'] !== $v['NA_MED']) {
                    echo "跳过记录ID: " . $v['id'] . ", BLMC和NA_MED不同" . PHP_EOL;
                    $skipped++;
                    continue;
                }
                
                $processed++;
                $blmc = $v['NA_MED'];
                var_dump('blmc:' . $blmc);

                // 获取年份
                $cjsj = $v['CJSJ'];
                $year = date('Y', strtotime($cjsj));
                // 获取cjsj的月份
                $cjsj_month = date('m', strtotime($cjsj));

                // 添加调试信息
                echo "处理记录ID: " . $v['id'] . ", BLMC原始值: [" . $blmc . "]" . PHP_EOL;

                // 更灵活地匹配时间格式
                preg_match("/.*?(\d{2}\.\d{2} \d{2}:\d{2})/", $blmc, $timeMatches);

                echo "匹配结果: " . (!empty($timeMatches) ? json_encode($timeMatches) : "无匹配") . PHP_EOL;

                $timePrefix = !empty($timeMatches[1]) ? $timeMatches[1] : '';

                if (!empty($timePrefix)) {
                    // 正确解析MM.DD HH:MM格式
                    if (preg_match('/(\d{2})\.(\d{2}) (\d{2}):(\d{2})/', $timePrefix, $parts)) {
                        $month = $parts[1];
                        $day = $parts[2];
                        $hour = $parts[3];
                        $minute = $parts[4];

                        // 如果创建时间是12月,month是01,年份加1
                        if ($cjsj_month == 12 && $month == 1) {
                            $year = $year + 1;
                        }

                        // 直接构建标准格式
                        $timePrefix = $month . '-' . $day . ' ' . $hour . ':' . $minute;
                        echo "时间格式转换: 原格式=[" . $parts[0] . "], 转换后=[" . $timePrefix . "]" . PHP_EOL;
                    } else {
                        echo "警告: 无法解析时间格式 [" . $timePrefix . "]" . PHP_EOL;
                        $timePrefix = date('m-d H:i', strtotime($v['CJSJ']));
                    }

                    // 拼上年份
                    $timePrefix = $year . '-' . $timePrefix;

                    // 删除原本的时间格式
                    $blmc = preg_replace("/(\d{2}\.\d{2} \d{2}:\d{2})/", "", $blmc);
                    $blmc = preg_replace("/\s+/", "", $blmc); // 去除空格
                    // 拼接上新的时间
                    $blmc = $timePrefix . ' ' . $blmc;
                    // 更新blmc
                    EMR_BL_BL01::query()->where('BLBH', $v['BLBH'])->update(['BLMC' => $blmc, 'ZXSJ' => $timePrefix]);

                    echo "更新成功，新BLMC: " . $blmc . ", ZXSJ: " . $timePrefix . PHP_EOL;
                } else {
                    echo "未匹配到时间" . PHP_EOL;
                }
            }

            echo "批次统计: 处理 {$processed} 条记录, 跳过 {$skipped} 条记录" . PHP_EOL;

            $last_id = $last_id + $pageSize;
            echo "Batch completed, last_id updated to: {$last_id}" . PHP_EOL;

            Setting::query()->where('name', 'cleanblmc_303')->update(['content' => $last_id]);
        }

        // 注意：由于是无限循环，下面这行代码实际上永远不会执行
        echo "bl:cleanblmc_303 end " . date('Y-m-d H:i:s') . PHP_EOL;
        return 0;
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
