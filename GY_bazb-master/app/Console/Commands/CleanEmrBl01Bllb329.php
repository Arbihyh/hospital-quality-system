<?php

namespace App\Console\Commands;

use App\Model\EMR_BL_BL01;
use App\Services\MysqlDataSync\sanyuan\HomeData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanEmrBl01Bllb329 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:clean_emr_bl01_bllb329
     */
    protected $signature = 'command:clean_emr_bl01_bllb329 {zyh?} {start_time?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 清洗EMR_BL_BL01表BLLB=329的BLMC和ZXSJ字段';

    /**
     * HomeData服务实例
     *
     * @var HomeData
     */
    protected $homeDataService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->homeDataService = new HomeData();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $zyh = data_get($this->arguments(), 'zyh');
        $startTime = data_get($this->arguments(), 'start_time');

        // 如果没有指定开始时间，默认为今天00:00:00
        if (!$startTime) {
            $startTime = date("Y-m-d 00:00:00");
        }

        $this->info("开始清洗EMR_BL_BL01表BLLB=329的记录，zyh:{$zyh},开始时间:{$startTime}");

        // 构建查询
        $query = EMR_BL_BL01::query()->where('BLLB', 329);
        
        if ($zyh) {
            $query = $query->where('JZHM', $zyh);
            $this->info("指定JZHM: {$zyh}");
        } else {
            $query = $query->where("updated_at", '>', $startTime);
            $this->info("更新时间大于: {$startTime}");
        }

        // 获取需要处理的字段
        $records = $query->get(['BLBH', 'BLMC', 'BLLB', 'CJSJ', 'NA_MED', 'JZHM'])->toArray();

        $totalCount = count($records);
        $this->info("找到 {$totalCount} 条BLLB=329的记录需要清洗");

        if ($totalCount == 0) {
            $this->warn("没有找到需要清洗的记录");
            return;
        }

        $successCount = 0;
        $errorCount = 0;
        $skipCount = 0;

        // 创建进度条
        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        foreach ($records as $index => $item) {
            try {
                // 准备数据，formatBL01BLMC306方法需要NA_MED字段
                $bl01Data = [
                    'JZHM' => $item['JZHM'],
                    'BLBH' => $item['BLBH'],
                    'NA_MED' => $item['NA_MED'] ?: $item['BLMC'], // 优先使用NA_MED，如果为空则使用BLMC
                    'BLMC' => $item['BLMC'],
                    'BLLB' => $item['BLLB'],
                    'CJSJ' => $item['CJSJ'],
                ];

                // 检查是否包含需要清洗的时间格式 (MM.DD HH:MM)
                if (!preg_match("/\d{2}\.\d{2} \d{2}:\d{2}/", $bl01Data['NA_MED'])) {
                    $skipCount++;
                    $bar->advance();
                    continue;
                }

                // 调用HomeData服务的formatBL01BLMC306方法进行清洗
                $this->homeDataService->formatBL01BLMC306($bl01Data);
                
                $successCount++;
            } catch (\Throwable $e) {
                $errorCount++;
                Log::error("清洗EMR_BL_BL01记录失败", [
                    'JZHM' => $item['JZHM'],
                    'msg' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                
                // 记录错误但不中断执行
                $this->line('');
                $this->error("处理失败 JZHM:{$item['JZHM']} - " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->line('');
        $this->line('');

        // 输出统计信息
        $this->info("==================== 清洗完成 ====================");
        $this->info("总记录数: {$totalCount}");
        $this->info("成功: {$successCount} 条");
        $this->info("跳过(无需清洗): {$skipCount} 条");
        $this->info("失败: {$errorCount} 条");
        $this->info("结束时间: " . date("Y-m-d H:i:s"));
        $this->info("================================================");
    }
}
