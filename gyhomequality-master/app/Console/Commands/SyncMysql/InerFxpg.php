<?php

namespace App\Console\Commands\SyncMysql;

use App\Model\IENR_FXPG;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 风险评估数据同步命令
 * 
 * 从Oracle的BTF_INER_FXPG视图同步数据到MySQL的IENR_FXPG表
 */
class InerFxpg extends Command
{
    /**
     * 命令签名
     *
     * @var string
     */
    protected $signature = 'sync:iner_fxpg {startTime?} {endTime?}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '同步风险评估数据(IENR_FXPG)从Oracle到MySQL';

    /**
     * Oracle连接实例
     *
     * @var resource
     */
    public static $con;

    /**
     * 构造函数
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
        // 建立Oracle连接
        $username = env('ORACLE_USERNAME', 'zdyh');
        $password = env('ORACLE_PASSWORD', 'zdyh');
        $connection = env('ORACLE_HOST', '172.16.9.8');
        $port = env('ORACLE_PORT', '1521');
        $tns = env('ORACLE_TNS', 'his');
        
        $con = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
        
        if (!$con) {
            $e = oci_error();
            $this->error('Oracle连接失败: ' . htmlentities($e['message']));
            Log::error('Oracle连接失败', ['error' => $e]);
            return 1;
        }
        
        self::$con = $con;
        $this->info('Oracle连接成功');

        // 处理时间参数
        $startTime = $this->argument('startTime');
        $endTime = $this->argument('endTime');

        // 如果没有传递开始时间,默认为7天前
        if (empty($startTime)) {
            $startTime = Carbon::now()->subDays(7)->format('Y-m-d 00:00:00');
            $this->info('未指定开始时间,使用默认值: ' . $startTime);
        }

        // 如果没有传递结束时间,默认为当前时间
        if (empty($endTime)) {
            $endTime = Carbon::now()->format('Y-m-d 23:59:59');
            $this->info('未指定结束时间,使用默认值: ' . $endTime);
        }

        $this->info("开始同步数据,时间范围: {$startTime} 至 {$endTime}");

        // 执行数据同步
        $this->syncInerFxpg($startTime, $endTime);

        // 关闭连接
        oci_close($con);
        
        $this->info('数据同步完成');
        return 0;
    }

    /**
     * 同步风险评估数据
     * 使用流式处理，边读边插，避免内存溢出
     *
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @return void
     */
    protected function syncInerFxpg($startTime, $endTime)
    {
        $this->info('开始查询并同步Oracle数据（流式处理）...');
        
        // 查询数据，但不一次性加载到内存
        $sql = "
            SELECT 
                PGXH,
                ZYH,
                PGDH,
                PGDMC,
                to_char(PGSJ, 'yyyy-mm-dd hh24:mi:ss') as PGSJ,
                PGGH,
                PGZF,
                CDMS,
                PGNR,
                YZMS,
                PFFZ,
                FZMS
            FROM PORTAL_HIS.BTF_IENR_FXPG
            WHERE PGSJ >= TO_DATE('{$startTime}', 'yyyy-mm-dd hh24:mi:ss')
                AND PGSJ <= TO_DATE('{$endTime}', 'yyyy-mm-dd hh24:mi:ss')
            ORDER BY PGSJ ASC
        ";

        $result = oci_parse(self::$con, $sql);
        
        if (!$result) {
            $e = oci_error(self::$con);
            $this->error('SQL解析失败: ' . $e['message']);
            Log::error('SQL解析失败', ['error' => $e, 'sql' => $sql]);
            return;
        }

        // 设置预取行数，提高查询效率
        oci_set_prefetch($result, 1000);

        $execute = oci_execute($result, OCI_DEFAULT);
        
        if (!$execute) {
            $e = oci_error($result);
            $this->error('SQL执行失败: ' . $e['message']);
            Log::error('SQL执行失败', ['error' => $e, 'sql' => $sql]);
            return;
        }

        $this->info('开始流式读取并同步数据...');

        $totalCount = 0;
        $batchCount = 0;
        
        // 流式读取，边读边插
        while ($row = oci_fetch_assoc($result)) {
            $insertData = [
                'PGXH' => $row['PGXH'] ?? '',
                'ZYH' => $row['ZYH'] ?? '',
                'PGDH' => $row['PGDH'] ?? '',
                'PGDMC' => $row['PGDMC'] ?? '',
                'PGSJ' => $row['PGSJ'] ?? null,
                'PGGH' => $row['PGGH'] ?? '',
                'PGZF' => $row['PGZF'] ?? 0,
                'CDMS' => $row['CDMS'] ?? '',
                'PGNR' => $row['PGNR'] ?? '',
                'YZMS' => $row['YZMS'] ?? '',
                'PFFZ' => $row['PFFZ'] ?? 0,
                'FZMS' => $row['FZMS'] ?? '',
                'DATA_STATUS' => 1,
            ];

            try {
                IENR_FXPG::query()->updateOrInsert(
                    ['PGXH' => $insertData['PGXH']], 
                    $insertData
                );
                
                $totalCount++;
                
                // 每100条显示一次进度
                if ($totalCount % 100 == 0) {
                    $batchCount++;
                    echo "已同步 {$totalCount} 条记录 - PGXH={$insertData['PGXH']}, PGSJ={$insertData['PGSJ']} - " . date('Y-m-d H:i:s') . PHP_EOL;
                }
                
            } catch (\Exception $e) {
                $this->error("数据插入失败: PGXH={$insertData['PGXH']}, 错误: " . $e->getMessage());
                Log::error('数据插入失败', [
                    'data' => $insertData,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // 显示最终同步的记录（如果不是100的整数倍）
        if ($totalCount % 100 != 0) {
            echo "已同步 {$totalCount} 条记录 - " . date('Y-m-d H:i:s') . PHP_EOL;
        }

        $this->info("数据同步完成！总共同步了 {$totalCount} 条记录");
    }
}
