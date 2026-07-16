<?php

namespace App\Console\Commands;

use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLSY;
use Illuminate\Console\Command;

class CleanBLMC_294_3_jining extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bl:cleanblmc_294_3_jining';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清洗bl01表中updated_at大于三天前的数据，把ZXSJ拼到blmc，如果包含时间格式也删除，同时清洗first_blsy_time字段';

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
        echo "bl:cleanblmc_294_3_jining start " . date('Y-m-d H:i:s') . PHP_EOL;
        
        // 计算三天前的时间
        $threeDaysAgo = date('Y-m-d H:i:s', strtotime('-3 days'));
        
        $pageSize = 1000;
        $lastId = 0;
        
        // 获取符合条件的最大id
        $maxId = EMR_BL_BL01::query()
            ->where('updated_at', '>=', $threeDaysAgo)
            ->max('id');
        
        if (!$maxId) {
            echo "No data found with updated_at < {$threeDaysAgo}" . PHP_EOL;
            echo "bl:cleanblmc_294_3_jining end " . date('Y-m-d H:i:s') . PHP_EOL;
            return 0;
        }
        
        echo "Max ID to process: {$maxId}" . PHP_EOL;
        
        while (true) {
            echo "Processing from ID: {$lastId}, batch size: {$pageSize}" . PHP_EOL;
            
            $query = EMR_BL_BL01::query()
                ->where('updated_at', '>=', $threeDaysAgo)
                ->where('id', '>', $lastId)
                ->where('id', '<=', $lastId + $pageSize)
                ->orderBy('id', 'asc');
            
            $data = $query->take($pageSize)->get(['id', 'BLBH', 'BLMC', 'ZXSJ'])->toArray();
            
            if ($maxId <= $lastId) {
                echo "No more data found, breaking loop" . PHP_EOL;
                break;
            } else {
                if (empty($data)) {
                    $lastId = $lastId + $pageSize;
                    continue;
                }
            }
            
            $currentBatch = array_column($data, 'id');
            echo "Processing IDs in current batch: " . implode(', ', $currentBatch) . PHP_EOL;
            
            foreach ($data as $v) {
                echo "Processing ID: {$v['id']}" . PHP_EOL;
                
                $blmc = $v['BLMC'] ?? '';
                $zxsj = $v['ZXSJ'] ?? '';
                $updateData = [];
                
                // 处理BLMC和ZXSJ
                if (!empty($zxsj)) {
                    // 格式化ZXSJ为时间字符串（如果是时间戳则转换）
                    $zxsjStr = '';
                    if (is_numeric($zxsj)) {
                        // 如果是时间戳
                        $zxsjStr = date('Y-m-d H:i:s', $zxsj);
                    } else {
                        // 如果已经是字符串格式
                        $zxsjStr = $zxsj;
                    }
                    
                    // 如果ZXSJ格式正确，处理BLMC
                    if (!empty($zxsjStr) && $zxsjStr != '1970-01-01 00:00:00') {
                        // 删除BLMC中的时间格式
                        // 删除各种时间格式
                        // 格式1: 2024-05-14 09:00:00
                        $blmc = preg_replace("/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/", "", $blmc);
                        // 格式2: 2024-05-14 09:00
                        $blmc = preg_replace("/(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", "", $blmc);
                        // 格式3: 2025.01.02 08:09
                        $blmc = preg_replace("/(\d{4}\.\d{2}\.\d{2} \d{2}:\d{2})/", "", $blmc);
                        // 格式4: 01.02 08:09
                        $blmc = preg_replace("/(\d{2}\.\d{2} \d{2}:\d{2})/", "", $blmc);
                        // 格式5: 时间：2024-05-14 11:33
                        $blmc = preg_replace("/时间：(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", "", $blmc);
                        // 格式6: {2024-08-01 19:20}
                        $blmc = preg_replace("/\{(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\}/", "", $blmc);
                        // 格式7: 记录时间：2024-07-31 10:12
                        $blmc = preg_replace("/记录时间：(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", "", $blmc);
                        // 格式8: 讨论日期：2024-07-31 10:12
                        $blmc = preg_replace("/讨论日期：(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", "", $blmc);
                        
                        // 删除多余的空格
                        $blmc = preg_replace("/\s+/", " ", $blmc);
                        $blmc = trim($blmc);
                        
                        // 将ZXSJ拼接到BLMC前面
                        $newBlmc = $zxsjStr . ' ' . $blmc;
                        $updateData['BLMC'] = $newBlmc;
                        
                        echo "  Updated BLBH: {$v['BLBH']}, new BLMC: {$newBlmc}" . PHP_EOL;
                    } else {
                        echo "  ZXSJ format invalid, skipping BLMC update for ID: {$v['id']}" . PHP_EOL;
                    }
                } else {
                    echo "  ZXSJ is empty, skipping BLMC update for ID: {$v['id']}" . PHP_EOL;
                }
                
                // 处理first_blsy_time：查询EMR_BL_BLSY表获取最早的JLSJ
                $firstBlsyTime = EMR_BL_BLSY::query()
                    ->where('BLBH', $v['BLBH'])
                    ->orderBy('JLSJ', 'asc')
                    ->value('JLSJ');
                
                if (!empty($firstBlsyTime)) {
                    $updateData['first_blsy_time'] = $firstBlsyTime;
                    echo "  Updated first_blsy_time: {$firstBlsyTime} for BLBH: {$v['BLBH']}" . PHP_EOL;
                } else {
                    echo "  No BLSY record found for BLBH: {$v['BLBH']}" . PHP_EOL;
                }
                
                // 批量更新数据库
                if (!empty($updateData)) {
                    EMR_BL_BL01::query()
                        ->where('BLBH', $v['BLBH'])
                        ->update($updateData);
                }
            }
            
            $lastId = end($data)['id'];
            echo "Batch completed, last_id updated to: {$lastId}" . PHP_EOL;
        }
        
        echo "bl:cleanblmc_294_3_jining end " . date('Y-m-d H:i:s') . PHP_EOL;
        return 0;
    }
}

