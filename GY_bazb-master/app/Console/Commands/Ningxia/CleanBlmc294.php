<?php

namespace App\Console\Commands\Ningxia;

use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Services\MysqlDataSync\ningxia\HomeData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanBlmc294 extends Command
{
    /**
     * 命令名称和签名
     *
     * @var string
     */
    protected $signature = 'command:ningxia_clean_blmc294 {--limit=1000 : 每次处理的记录数}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '清洗EMR_BL_BL01表中BLLB为294、43、303、82的记录，调用formatBL01BLMC294方法';

    /**
     * 创建命令实例
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 执行命令
     *
     * @return int
     */
    public function handle()
    {
        $limit = (int)$this->option('limit');
        $this->info("开始清洗病历名称数据...");
        $this->info("查询条件：CJSJ > 2024-10-01, ZXSJ为空, BLLB IN [294, 43, 303, 82]");
        $this->info("每次处理记录数：{$limit}");

        // 先获取所有符合条件的BLBH列表（避免处理过程中ZXSJ更新导致查询条件变化）
        $blbhList = EMR_BL_BL01::query()
            ->where('CJSJ', '>', '2024-10-01 00:00:00')
            ->where(function ($q) {
                $q->whereNull('ZXSJ')
                  ->orWhere('ZXSJ', '');
            })
            ->whereIn('BLLB', [294, 43, 303, 82])
            ->pluck('BLBH')
            ->toArray();

        $totalCount = count($blbhList);
        $this->info("符合条件的记录总数：{$totalCount}");

        if ($totalCount == 0) {
            $this->info("没有符合条件的记录，退出");
            return 0;
        }

        $processedCount = 0;
        $successCount = 0;
        $errorCount = 0;
        $homeDataService = new HomeData();

        // 分批处理BLBH列表
        $chunks = array_chunk($blbhList, $limit);
        $totalChunks = count($chunks);
        $this->info("将分 {$totalChunks} 批处理，每批 {$limit} 条");

        foreach ($chunks as $chunkIndex => $blbhChunk) {
            $this->info("开始处理第 " . ($chunkIndex + 1) . "/{$totalChunks} 批，共 " . count($blbhChunk) . " 条记录");
            
            // 查询这批BLBH的详细数据
            $items = EMR_BL_BL01::query()
                ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
                ->whereIn('EMR_BL_BL01.BLBH', $blbhChunk)
                ->select([
                    'EMR_BL_BL01.BLBH',
                    'EMR_BL_BL01.BLMC',
                    'EMR_BL_BL01.BLLB',
                    'EMR_BL_BL01.CJSJ',
                    'EMR_BL_BL01.ZXSJ',
                    'EMR_BL_BLXG.HJNR'
                ])
                ->get();

            foreach ($items as $item) {
                try {
                    $processedCount++;
                    $blbh = $item->BLBH;
                    
                    $this->line("处理第 {$processedCount}/{$totalCount} 条记录，BLBH: {$blbh}");

                    // 准备数据数组
                    $bl01Data = [
                        'BLBH' => $item->BLBH,
                        'BLMC' => $item->BLMC ?? '',
                        'HJNR' => $item->HJNR ?? '',
                        'CJSJ' => $item->CJSJ ?? '',
                        'BLLB' => $item->BLLB ?? '',
                    ];

                    // 调用清洗方法
                    $homeDataService->formatBL01BLMC294($bl01Data);
                    
                    $successCount++;
                    
                    if ($processedCount % 100 == 0) {
                        $this->info("已处理 {$processedCount}/{$totalCount} 条，成功 {$successCount} 条，失败 {$errorCount} 条");
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    $errorMsg = "处理BLBH {$blbh} 时出错: " . $e->getMessage();
                    $this->error($errorMsg);
                    Log::error('清洗BLMC294失败', [
                        'BLBH' => $blbh,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
            
            $this->info("第 " . ($chunkIndex + 1) . " 批处理完成，累计处理 {$processedCount}/{$totalCount} 条");
        }

        $this->info("处理完成！");
        $this->info("总记录数：{$totalCount}");
        $this->info("已处理：{$processedCount} 条");
        $this->info("成功：{$successCount} 条");
        $this->info("失败：{$errorCount} 条");

        return 0;
    }
}

