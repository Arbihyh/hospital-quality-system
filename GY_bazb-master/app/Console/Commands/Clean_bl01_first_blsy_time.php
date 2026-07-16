<?php

namespace App\Console\Commands;

use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLSY;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Exception;

class Clean_bl01_first_blsy_time extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'clean_bl01_first_blsy_time';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清洗病历里面的首次签名时间';

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
        $this->info($this->description . " 开始: " . date('Y-m-d H:i:s'));
        
        $pageSize = 1000;
        $totalProcessed = 0;
        $totalUpdated = 0;
        
        try {
            // 使用where嵌套正确组合查询条件，bllb包含其中一个2000001,292,1,288,18,294或者mblb包含74，306，30301，3069999
            EMR_BL_BL01::query()
                ->where(function($query) {
                    $query->whereNull('first_blsy_time')
                          ->orWhere('first_blsy_time', '=', '');
                })
                ->where(function($query) {
                    $query->whereIn('BLLB', [2000001,292,1,288,18,294,43])
                          ->orWhereIn('MBLB', [74,306,30301,3069999]);
                })
                ->orderBy('id', 'ASC')
                ->chunk($pageSize, function($records) use (&$totalProcessed, &$totalUpdated) {
                    $updates = [];
                    //本次数据id
                    $this->info("本次数据id: " . $records[0]->id . " - " . $records[count($records) - 1]->id);
                    foreach ($records as $record) {
                        $totalProcessed++;
                        //本次数据id
                        $this->info("本次数据id: " . $record->id);
                        
                        // 只查询需要的字段
                        $blsyRecord = EMR_BL_BLSY::query()
                            ->where('BLBH3', '=', $record->BLBH3)
                            ->orderBy('JLSJ', 'ASC')
                            ->first(['JLSJ']);
                            
                        if (!empty($blsyRecord)) {
                            //记录时间
                            $this->info("记录时间: " . $blsyRecord->JLSJ);
                            $updates[] = [
                                'id' => $record->id,
                                'jlsj' => $blsyRecord->JLSJ
                            ];
                            $totalUpdated++;
                            
                            if ($totalProcessed % 100 === 0) {
                                $this->output->write('.');
                            }
                            
                            if ($totalProcessed % 1000 === 0) {
                                $this->info(" 已处理: {$totalProcessed}, 已更新: {$totalUpdated}");
                            }
                        }
                    }
                    
                    // 使用事务批量更新
                    if (!empty($updates)) {
                        DB::beginTransaction();
                        try {
                            foreach ($updates as $update) {
                                EMR_BL_BL01::query()
                                    ->where('id', '=', $update['id'])
                                    ->update(['first_blsy_time' => $update['jlsj']]);
                            }
                            DB::commit();
                        } catch (Exception $e) {
                            DB::rollBack();
                            throw $e;
                        }
                    }
                });
                
            $this->info("\n处理完成: 总共处理 {$totalProcessed} 条记录, 更新 {$totalUpdated} 条记录");
            $this->info($this->description . " 结束: " . date('Y-m-d H:i:s'));
            
            return 0;
        } catch (Exception $e) {
            $this->error("执行出错: " . $e->getMessage());
            return 1;
        }
    }
}