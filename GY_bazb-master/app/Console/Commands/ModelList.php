<?php

namespace App\Console\Commands;

use App\Services\BigModelQualityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ModelList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'model:list {--daemon : 以守护进程模式运行} {--interval=30 : 检查间隔时间(秒)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '监控bigmodel_list表并处理数据';

    /**
     * bigModelQualityService服务实例
     *
     * @var BigModelQualityService
     */
    protected $bigModelQualityService;

    /**
     * 检查间隔时间(秒)
     *
     * @var int
     */
    protected $interval = 30;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->bigModelQualityService = new BigModelQualityService();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('开始监控bigmodel_list表...');

        // 获取检查间隔时间
        $this->interval = (int) $this->option('interval');


        if ($this->option('daemon')) {
            $this->runAsDaemon();
        } else {
            $this->runOnce();
        }

        return 0;
    }

    /**
     * 检查并添加 level 字段
     */
    protected function checkAndAddLevelColumn()
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('bigmodel_list', 'level')) {
                \Illuminate\Support\Facades\Schema::table('bigmodel_list', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->integer('level')->default(0)->after('status')->comment('优先级，越高越先执行');
                });
                $this->info("成功为 bigmodel_list 表添加 level 字段");
            }
        } catch (\Exception $e) {
            $this->error("检查或添加 level 字段失败: " . $e->getMessage());
        }
    }

    /**
     * 守护进程方式运行
     */
    protected function runAsDaemon()
    {
        $this->info("开始以守护进程方式运行模型列表处理...");

        // 启动前检查字段
        $this->checkAndAddLevelColumn();

        $this->info("以守护进程模式运行，检查间隔: {$this->interval}秒");

        while (true) {
            try {
                $this->insertAdmittedPatients();
                $this->processAllData();
                sleep($this->interval);
            } catch (\Exception $e) {
                $this->error('处理数据时发生错误: ' . $e->getMessage());
                Log::error('bigModelList处理错误', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                sleep($this->interval);
            }
        }
    }

    /**
     * 运行一次
     */
    protected function runOnce()
    {
        $this->info("开始运行一次模型列表处理...");

        // 启动前检查字段
        $this->checkAndAddLevelColumn();

        try {
            // 1. 先查询在院患者并插入队列
            $this->insertAdmittedPatients();
            $this->processAllData();
            $this->info('数据处理完成');
        } catch (\Exception $e) {
            $this->error('处理数据时发生错误: ' . $e->getMessage());
            Log::error('bigModelList处理错误', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * 查询在院患者并插入队列
     */
    protected function insertAdmittedPatients()
    {
        $this->info('开始查询在院患者并插入队列...');

        $query = \App\Model\ZY_BRRY::query()
            ->where(function ($q) {
                $q->whereNull('AAC01')->orWhere('AAC01', '');
            });

        $keyword = \App\Model\RuleWordMap::query()->where('id', 8109)->value('keyword');
        if ((string) $keyword !== '1') {
            $this->line('rulewordmap id=8109 keyword != 1，不插入在院患者');
            return;
        }

        $keyword = \App\Model\RuleWordMap::query()->where('id', 8108)->value('keyword');
        if (!empty($keyword)) {
            if (strpos($keyword, ',') !== false) {
                $brksArr = explode(',', $keyword);
                $query->whereIn('BRKS', $brksArr);
            } else {
                $query->where('BRKS', $keyword);
            }
        }

        $zyhs = $query->pluck('ZYH')->toArray();
        if (empty($zyhs)) {
            $this->line('没有需要插入的在院患者');
            return;
        }

        $this->info('共找到 ' . count($zyhs) . ' 个在院患者，开始插入队列...');

        $chunks = array_chunk($zyhs, 500);
        foreach ($chunks as $chunk) {
            $insertData = [];
            foreach ($chunk as $zyh) {
                $insertData[] = [
                    'zyh' => $zyh,
                    'bllb' => '',
                    'blbh' => '',
                ];
            }
            DB::table('bigmodel_list')->insert($insertData);
        }

        $this->info('在院患者插入队列完成');
    }

    /**
     * 消费并且处理所有的bigmodel_list表数据
     */
    protected function processAllData()
    {
        while (true) {
            $data = DB::table('bigmodel_list')
                ->select('id', 'zyh', 'bllb', 'blbh', 'level')
                ->whereNotNull('zyh')
                ->where('zyh', '!=', '')
                ->orderBy('level', 'desc')
                ->orderBy('id', 'asc')
                ->first(); // 每次只取优先级最高的一条进行处理

            if (!$data) {
                $this->line('本轮待处理的数据已全部消费完毕');
                break;
            }

            try {
                $this->syncPatientData($data->zyh, $data->id, $data->bllb, $data->blbh);
            } catch (\Exception $e) {
                $this->error("处理ZYH: {$data->zyh} 时发生错误: " . $e->getMessage());
                Log::error('bigModelList单条数据处理错误', [
                    'ZYH' => $data->zyh,
                    'id' => $data->id,
                    'error' => $e->getMessage()
                ]);
                // 异常也删掉这一条，避免死循环卡住
                DB::table('bigmodel_list')->where('id', $data->id)->delete();
            }
        }
    }

    /**
     * 质控
     *
     * @param string $ZYH 住院号
     * @param int $dataId bigmodel_list表的ID
     */
    protected function syncPatientData($ZYH, $dataId, $bllb, $blbh)
    {
        $this->line("开始处理ZYH: {$ZYH}");

        try {
            $this->bigModelQualityService->singlePatientQualityBybllb($ZYH, $bllb, $blbh);
            // 4. 处理完成后删除bigmodel_list中的记录
            DB::table('bigmodel_list')->where('id', $dataId)->delete();

            $this->info("ZYH: {$ZYH} 处理完成");
        } catch (\Exception $e) {
            $this->error("ZYH: {$ZYH} 处理失败: " . $e->getMessage());
            Log::error('bigModelList数据处理失败', [
                'ZYH' => $ZYH,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
