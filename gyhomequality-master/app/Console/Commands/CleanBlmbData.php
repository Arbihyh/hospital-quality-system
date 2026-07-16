<?php

namespace App\Console\Commands;

use App\Model\EMR_BL_BL01;
use App\Model\ZY_BLMB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanBlmbData extends Command
{
    /**
     * 命令名称
     *
     * @var string
     */
    protected $signature = 'clean:blmb-data {--batch=1000 : 每批处理的记录数}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '清洗EMR_BL_BL01表中BLMB为空的数据，通过ID_TEP关联ZY_BLMB表更新数据';

    /**
     * 统计数据
     */
    protected $totalProcessed = 0;
    protected $totalUpdated = 0;
    protected $totalSkipped = 0;
    protected $totalZyBlmbUpdated = 0;

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
        try {
            $startTime = microtime(true);
            $batchSize = intval($this->option('batch'));
            
            $this->info('开始清洗EMR_BL_BL01表中BLMB为空的数据...');
            $this->info('每批处理记录数: ' . $batchSize);
            
            // 分批查询BLMB为空或NULL的记录
            EMR_BL_BL01::query()
                ->where(function($query) {
                    $query->whereNull('BLMB')
                          ->orWhere('BLMB', '');
                })
                ->whereNotNull('ID_TEP')
                ->where('ID_TEP', '!=', '')
                ->chunk($batchSize, function($records) {
                    $this->processRecords($records);
                });
            
            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);
            
            $this->info('清洗完成！');
            $this->info('总处理记录数: ' . $this->totalProcessed);
            $this->info('成功更新EMR_BL_BL01记录数: ' . $this->totalUpdated);
            $this->info('成功更新ZY_BLMB记录数: ' . $this->totalZyBlmbUpdated);
            $this->info('跳过记录数: ' . $this->totalSkipped);
            $this->info('执行时间: ' . $executionTime . ' 秒');
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('处理过程中发生错误: ' . $e->getMessage());
            Log::error('清洗EMR_BL_BL01表数据错误: ' . $e->getMessage());
            return 1;
        }
    }
    
    /**
     * 处理一批记录
     *
     * @param \Illuminate\Database\Eloquent\Collection $records
     * @return void
     */
    protected function processRecords($records)
    {
        // 当前批次记录数
        $batchCount = count($records);
        $this->totalProcessed += $batchCount;
        $this->info('正在处理 ' . $batchCount . ' 条记录...');
        
        // 收集ID_TEP值
        $idTepValues = $records->pluck('ID_TEP')->filter()->unique()->toArray();
        
        if (empty($idTepValues)) {
            $this->warn('当前批次没有有效的ID_TEP值，跳过处理');
            $this->totalSkipped += $batchCount;
            return;
        }
        
        // 查询ZY_BLMB表获取对应数据
        $blmbRecords = ZY_BLMB::query()
            ->whereIn('ID_TEP', $idTepValues)
            ->get()
            ->keyBy('ID_TEP')
            ->toArray();
        
        if (empty($blmbRecords)) {
            $this->warn('未找到任何匹配的ZY_BLMB记录，跳过处理');
            $this->totalSkipped += $batchCount;
            return;
        }
        
        // 批处理更新
        $updatedCount = 0;
        $skippedCount = 0;
        $zyBlmbUpdatedCount = 0;
        
        try {
            DB::beginTransaction();
            
            foreach ($records as $record) {
                $idTep = $record->ID_TEP;
                
                // 如果没有对应的ZY_BLMB记录，跳过
                if (empty($idTep) || !isset($blmbRecords[$idTep])) {
                    $skippedCount++;
                    continue;
                }
                
                $blmbRecord = $blmbRecords[$idTep];
                
                // 更新EMR_BL_BL01记录
                EMR_BL_BL01::query()
                    ->where('id', $record->id)
                    ->update([
                        'BLMB' => $blmbRecord['TEP_NAME'],
                        'ID_MEDI' => $blmbRecord['ID_MEDI'],
                        'ID_MECA' => $blmbRecord['ID_MECA']
                    ]);
                
                $updatedCount++;
                
                // 检查ZY_BLMB是否需要反向更新BLLB和MBLB
                if ((is_null($blmbRecord['BLLB']) || $blmbRecord['BLLB'] === '') || 
                    (is_null($blmbRecord['MBLB']) || $blmbRecord['MBLB'] === '')) {
                    
                    // 只有当EMR_BL_BL01有值时才更新
                    $updateData = [];
                    
                    if ((is_null($blmbRecord['BLLB']) || $blmbRecord['BLLB'] === '') && 
                        !empty($record->BLLB)) {
                        $updateData['BLLB'] = $record->BLLB;
                    }
                    
                    if ((is_null($blmbRecord['MBLB']) || $blmbRecord['MBLB'] === '') && 
                        !empty($record->MBLB)) {
                        $updateData['MBLB'] = $record->MBLB;
                    }
                    
                    // 如果有需要更新的数据
                    if (!empty($updateData)) {
                        ZY_BLMB::query()
                            ->where('id', $blmbRecord['id'])
                            ->update($updateData);
                        
                        $zyBlmbUpdatedCount++;
                    }
                }
            }
            
            DB::commit();
            
            $this->totalUpdated += $updatedCount;
            $this->totalSkipped += $skippedCount;
            $this->totalZyBlmbUpdated += $zyBlmbUpdatedCount;
            
            $this->info("当前批次处理完成: 更新EMR_BL_BL01 {$updatedCount} 条记录, 更新ZY_BLMB {$zyBlmbUpdatedCount} 条记录, 跳过 {$skippedCount} 条记录");
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('批处理更新失败: ' . $e->getMessage());
            $this->totalSkipped += $batchCount;
            Log::error('批处理更新记录失败: ' . $e->getMessage());
        }
    }
}