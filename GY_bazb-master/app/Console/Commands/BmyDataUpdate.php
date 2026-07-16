<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\MysqlDataSync\sanyuan\HomeData;

class BmyDataUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bmy:data-update {--daemon : 以守护进程模式运行} {--interval=30 : 检查间隔时间(秒)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '监控bmyDataList表并同步数据到相关表';

    /**
     * HomeData服务实例
     *
     * @var HomeData
     */
    protected $homeDataService;

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
        $this->homeDataService = new HomeData();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('开始监控bmyDataList表...');

        // 获取检查间隔时间
        $this->interval = (int) $this->option('interval');

        // 初始化连接
        try {
            $this->homeDataService->getConnect();
            $this->info('数据库连接初始化成功');
        } catch (\Exception $e) {
            $this->error('数据库连接初始化失败: ' . $e->getMessage());
            Log::error('BmyDataUpdate连接初始化失败', ['error' => $e->getMessage()]);
            return 1;
        }

        if ($this->option('daemon')) {
            $this->runAsDaemon();
        } else {
            $this->runOnce();
        }

        return 0;
    }

    /**
     * 以守护进程模式运行
     */
    protected function runAsDaemon()
    {
        $this->info("以守护进程模式运行，检查间隔: {$this->interval}秒");

        while (true) {
            try {
                $this->processData();
                sleep($this->interval);
            } catch (\Exception $e) {
                $this->error('处理数据时发生错误: ' . $e->getMessage());
                Log::error('BmyDataUpdate处理错误', [
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
        $this->info('运行一次数据同步...');
        try {
            $this->processData();
            $this->info('数据同步完成');
        } catch (\Exception $e) {
            $this->error('处理数据时发生错误: ' . $e->getMessage());
            Log::error('BmyDataUpdate处理错误', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * 处理bmyDataList表中的数据
     */
    protected function processData()
    {
        // 查询bmyDataList表中的数据
        $dataList = DB::table('bmyDataList')
            ->select('id', 'ZYH')
            ->whereNotNull('ZYH')
            ->where('ZYH', '!=', '')
            ->limit(100) // 每次处理100条
            ->get();

        if ($dataList->isEmpty()) {
            $this->line('没有待处理的数据');
            return;
        }

        $this->info("找到 {$dataList->count()} 条待处理数据");

        foreach ($dataList as $data) {
            try {
                $this->syncPatientData($data->ZYH, $data->id);
            } catch (\Exception $e) {
                $this->error("处理ZYH: {$data->ZYH} 时发生错误: " . $e->getMessage());
                Log::error('BmyDataUpdate单条数据处理错误', [
                    'ZYH' => $data->ZYH,
                    'id' => $data->id,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
    }

    /**
     * 同步患者数据
     *
     * @param string $ZYH 住院号
     * @param int $dataId bmyDataList表的ID
     */
    protected function syncPatientData($ZYH, $dataId)
    {
        $this->line("开始处理ZYH: {$ZYH}");

        try {
            $config = config('dictionaries');
            // 1. 获取患者基本信息并插入/更新
            $this->syncPatientInfo($ZYH);

            // 2. 获取诊断数据并插入/更新
            $this->syncDiagnosisData($ZYH);

            // 3. 获取手术数据并插入/更新
            $this->syncOperationData($ZYH,$config);

            // 4. 处理完成后删除bmyDataList中的记录
            DB::table('bmyDataList')->where('id', $dataId)->delete();

            $this->info("ZYH: {$ZYH} 处理完成");

        } catch (\Exception $e) {
            $this->error("ZYH: {$ZYH} 处理失败: " . $e->getMessage());
            // Log::error('BmyDataUpdate数据同步失败', [
            //     'ZYH' => $ZYH,
            //     'error' => $e->getMessage(),
            //     'trace' => $e->getTraceAsString()
            // ]);
            throw $e;
        }
    }

    /**
     * 同步患者基本信息
     *
     * @param string $ZYH 住院号
     */
    protected function syncPatientInfo($ZYH)
    {
        try {
            // 获取患者信息
            $patientData = $this->homeDataService->getPatientInfo($ZYH,'');

            if (empty($patientData)) {
                $this->warn("ZYH: {$ZYH} 未找到患者信息");
                return;
            }

            // 插入/更新患者信息
            $this->homeDataService->addPatientInfo($patientData[0]);
            $this->line("ZYH: {$ZYH} 患者信息同步完成");

        } catch (\Exception $e) {
            $this->error("ZYH: {$ZYH} 患者信息同步失败: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 同步诊断数据
     *
     * @param string $ZYH 住院号
     */
    protected function syncDiagnosisData($ZYH)
    {
        try {
            // 获取诊断数据
            $diagnosisData = $this->homeDataService->getDiagnosisData($ZYH);

            if (empty($diagnosisData)) {
                $this->line("ZYH: {$ZYH} 未找到诊断数据");
                return;
            }

            // 插入/更新诊断数据
            $this->homeDataService->addDiagnosis($diagnosisData);
            $this->line("ZYH: {$ZYH} 诊断数据同步完成");

        } catch (\Exception $e) {
            $this->error("ZYH: {$ZYH} 诊断数据同步失败: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 同步手术数据
     *
     * @param string $ZYH 住院号
     */
    protected function syncOperationData($ZYH,$config)
    {
        try {
            // 获取手术数据
            $operationData = $this->homeDataService->getOperationData($ZYH);

            if (empty($operationData)) {
                $this->line("ZYH: {$ZYH} 未找到手术数据");
                return;
            }

            // 插入/更新手术数据
            $this->homeDataService->addOperation($operationData,$config);
            $this->line("ZYH: {$ZYH} 手术数据同步完成");

        } catch (\Exception $e) {
            $this->error("ZYH: {$ZYH} 手术数据同步失败: " . $e->getMessage());
            throw $e;
        }
    }
}