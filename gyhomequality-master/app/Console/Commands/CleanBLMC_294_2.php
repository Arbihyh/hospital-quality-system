<?php

namespace App\Console\Commands;

use App\Model\EMR_BL_BL01;
use App\Model\Setting;
use Illuminate\Console\Command;

class CleanBLMC_294_2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bl:cleanblmc_294_2';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清洗bl01表中病程记录(BLLB=294)的BLMC，将表头里面的时间清洗到JLSJ字段';

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
     * Summary of handle
     * @return void
     */
    public function handle()
    {
        echo "bl:cleanblmc_294_2 start" . date('Y-m-d H:i:s') . PHP_EOL;

        $lastUpdataTime = Setting::query()->where('id', 128)->value('content');
        $lastUpdataTime = $lastUpdataTime ?? '2024-06-01 00:00:00';
        $lastUpdataId = 0;
        $page = 1;
        $pageSize = 10000;
        $field = ['id', 'BLMC', 'CJSJ', 'updated_at'];
        while (true) {
            echo $page . " - " . date('Y-m-d H:i:s') . PHP_EOL;
            $data = EMR_BL_BL01::query()
                ->where('BLLB', '=', 294)
                ->where('BLBH', '!=', 0)
                ->where('updated_at', '>=', $lastUpdataTime)
                ->paginate($pageSize, $field, 'page', $page)
                ->toArray();
            $data = $data['data'];

            if (!empty($data)) {
                foreach ($data as $item) {
                    $year = "";
                    $blmc = $item['BLMC'];
                    $lastUpdataId = $item['id'];
                    /**
                     * 提取日期
                     */
                    // 定义正则表达式模式来匹配日期和时间
                    $pattern = '/(\d{4})-(\d{2})-(\d{2})\s(\d{2}):(\d{2})/'; // YYYY-MM-DD HH:MM
                    // 对于只有月和日的格式，我们可以添加另一个模式，但这里假设年份缺失时使用当前年份
                    $patternWithoutYear = '/(\d{2})\.(\d{2})\s(\d{2}):(\d{2})/'; // MM.DD HH:MM
                    if (preg_match($pattern, $blmc, $matches)) {
                        // 匹配到 YYYY-MM-DD HH:MM 格式
                        $year = $matches[1];
                        $month = $matches[2];
                        $day = $matches[3];
                        $hour = $matches[4];
                        $minute = $matches[5];
                    } elseif (preg_match($patternWithoutYear, $blmc, $matches)) {
                        // 匹配到 MM.DD HH:MM 格式，使用当前年份
                        $month = str_pad($matches[1], 2, '0', STR_PAD_LEFT); // 确保月份是两位数
                        $day = str_pad($matches[2], 2, '0', STR_PAD_LEFT); // 确保日期是两位数
                        $hour = $matches[3];
                        $minute = $matches[4];
                        $CJSJYear = date('Y', strtotime($item['CJSJ']));
                        $CJSJMonth = date('M', strtotime($item['CJSJ']));
                        $year = $CJSJYear;

                        /**
                         * 处理当记录时间与CJSJ时间不一致时的逻辑
                         */
                        if ((intval($month) == 12) && (intval($CJSJMonth) == 1)) {
                            $year = $CJSJYear - 1;
                        }
                    } else {
                        // 没有匹配到任何日期时间格式
                        continue;
                    }

                    // 构造日期时间字符串并创建 DateTime 对象
                    $dateTimeString = "$year-$month-$day $hour:$minute:00"; // 添加秒数部分，设置为00秒
                    $jlsj = date('Y-m-d H:i:00', strtotime($dateTimeString));

                    if (!empty($jlsj)) {
                        echo $jlsj . PHP_EOL;
                        EMR_BL_BL01::query()->where('id', '=', $item['id'])->update(['JLSJ' => $jlsj]);
                    }
                }
            } else {
                break;
            }
            $page++;
        }
        $lastTime = EMR_BL_BL01::query()->where('id', $lastUpdataId)->value('updated_at');
        Setting::query()->where('id', 128)->update(['content' => $lastTime]);
        echo "bl:cleanblmc_294_2 end" . date('Y-m-d H:i:s') . PHP_EOL;
        exit();
    }
}
