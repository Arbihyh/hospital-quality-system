<?php

namespace App\Console\Commands;

use App\Model\ZY_BLMB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncBlmb extends Command
{
    /**
     * 命令名称
     *
     * @var string
     */
    protected $signature = 'sync:blmb';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '从Oracle数据库同步数据到ZY_BLMB表';

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
            $this->info('开始从Oracle同步数据到ZY_BLMB表...');
            
            // Oracle连接参数
            $host = '192.168.52.98';
            $port = '1521';
            $service = 'storcl';
            $username = 'btf';
            $password = 'btf';
            
            // 方法1：使用EZCONNECT格式
            $connectionString = "//$host:$port/$service";
            
            // 方法2：使用完整TNS格式，使用SERVICE_NAME
            // $connectionString = "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=$host)(PORT=$port))(CONNECT_DATA=(SERVICE_NAME=$service)))";
            
            // 方法3：如果确实需要使用SID（而不是SERVICE_NAME）
            // $connectionString = "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=$host)(PORT=$port))(CONNECT_DATA=(SID=$service)))";
            
            $this->info('正在连接Oracle数据库...');
            $this->info('连接字符串: ' . $connectionString);
            
            // 尝试连接
            $conn = @oci_connect($username, $password, $connectionString, 'AL32UTF8');
            
            if (!$conn) {
                $e = oci_error();
                throw new \Exception('Oracle连接失败: ' . $e['message']);
            }
            
            $this->info('连接成功，开始查询数据...');
            
            // 准备SQL查询
            $sql = "
                SELECT
                    a.ID_TEP,
                    a.TEP_NAME,
                    a.ID_MECA,
                    b.MECA_NA,
                    b.ID_MEDI,
                    b.NA_MEDI
                FROM
                    WHIS_EMR.HI_VIEW_REC_MAKE_BLMB a
                LEFT JOIN
                    WHIS_EMR.HI_VIEW_REC_MAKE_BLLB b ON a.ID_MECA = b.ID_MECA
            ";
            
            // 准备查询
            $stmt = oci_parse($conn, $sql);
            if (!$stmt) {
                $e = oci_error($conn);
                throw new \Exception('SQL语句解析失败: ' . $e['message']);
            }
            
            // 执行查询
            $r = oci_execute($stmt);
            if (!$r) {
                $e = oci_error($stmt);
                throw new \Exception('SQL执行失败: ' . $e['message']);
            }
            
            // 获取数据
            $data = [];
            while ($row = oci_fetch_assoc($stmt)) {
                $data[] = $row;
            }
            
            $this->info('获取到 ' . count($data) . ' 条记录');
            
            // 关闭Oracle连接
            oci_free_statement($stmt);
            oci_close($conn);
            
            // 清空目标表
            $this->info('清空目标表 ZY_BLMB...');
            ZY_BLMB::truncate();
            
            // 批量插入数据
            $this->info('开始插入数据...');
            $batchSize = 100; // 每次处理的批次大小
            $batches = array_chunk($data, $batchSize);
            
            foreach($batches as $key => $batch) {
                $insertData = [];
                foreach($batch as $item) {
                    $insertData[] = [
                        'ID_MEDI' => $item['ID_MEDI'],
                        'NA_MEDI' => $item['NA_MEDI'],
                        'ID_MECA' => $item['ID_MECA'],
                        'MECA_NA' => $item['MECA_NA'],
                        'ID_TEP' => $item['ID_TEP'],
                        'TEP_NAME' => $item['TEP_NAME'],
                        // BLLB和MBLB字段为空，因为未在需求中指定
                        'BLLB' => null,
                        'MBLB' => null,
                    ];
                }
                
                DB::table('ZY_BLMB')->insert($insertData);
                $this->info('已处理：' . (($key + 1) * $batchSize) . '/' . count($data));
            }
            
            $this->info('数据同步完成！');
            return 0;
            
        } catch (\Exception $e) {
            $this->error('同步过程中发生错误: ' . $e->getMessage());
            Log::error('ZY_BLMB同步错误: ' . $e->getMessage());
            return 1;
        }
    }
}