<?php

namespace App\Console\Commands\OracleToMysql;

use Illuminate\Console\Command;

class EMR_BL_BLSY_binyi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oracleToMysql:EMR_BL_BLSY_binyi {startDate?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Oracle数据库中的EMR_BL_BLSY表同步到Mysql';

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
        $this->info('Oracle数据库中的EMR_BL_BLSY表同步到Mysql - 开始同步');

        ini_set('default_socket_timeout', 0);

        // Oracle数据库连接信息
        //ORACLE_USERNAME=jmgs
        //ORACLE_PASSWORD=jmgs@2
        //ORACLE_HOST=172.16.2.238
        //ORACLE_PORT=1521
        //ORACLE_TNS=ODS
        $host = '172.16.2.238';
        $port = '1521';
        $database = 'ODS';
        $username = 'jmgs';
        $password = 'jmgs@2';
        $schema = 'PORTAL55_EMR';

        // 连接Oracle数据库
        $con = oci_connect($username, $password, $host . ':' . $port . '/' . $database, 'UTF8');
        if (!$con) {
            $e = oci_error();
            $this->error('Oracle数据库连接失败: ' . htmlentities($e['message']));
            return 1;
        }

        // 查询条件：JLSJ >= 指定开始时间；未传则默认当年1月1日 00:00:00
        $startDate = $this->argument('startDate');
        if (empty($startDate)) {
            $startDate = date('Y-01-01 00:00:00');
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate .= ' 00:00:00';
        }
        $this->info('查询条件：JLSJ >= ' . $startDate);
        // SQL查询，使用to_char格式化时间字段
        $sql = "SELECT 
                    JLXH,
                    QMLX,
                    BLBH,
                    SYYS,
                    to_char(SYSJ,'yyyy-mm-dd hh24:mi:ss') as SYSJTIME,
                    to_char(JLSJ,'yyyy-mm-dd hh24:mi:ss') as JLSJTIME,
                    QMYS
                FROM {$schema}.EMR_BL_BLSY 
                WHERE JLSJ >= TO_DATE('{$startDate}', 'yyyy-MM-dd HH24:mi:ss')";

        $result = oci_parse($con, $sql);
        oci_set_prefetch($result, 1000);
        oci_execute($result, OCI_DEFAULT);

        $this->info('开始逐条读取并同步数据...');

        $totalCount = 0;
        $successCount = 0;
        $failCount = 0;

        while ($item = oci_fetch_assoc($result)) {
            $totalCount++;
            try {
                $QMMC = 0;
                switch ($item['QMYS'] ?? '') {
                    //3.患者签名：签名,患者签名2,患者签名4,患者签名6,患者签名7,患者签名9,患者签名0,患者签名3,患者签名5,患者签名1,患者签名8,患者签名10,患者签名11
                    case '患者签名':
                    case '患者签名2':
                    case '患者签名4':
                    case '患者签名6':
                    case '患者签名7':
                    case '患者签名9':
                    case '患者签名0':
                    case '患者签名3':
                    case '患者签名5':
                    case '患者签名1':
                    case '患者签名8':
                    case '患者签名10':
                    case '患者签名11':
                        $QMMC = 3;
                        break;
                    //5.医生签名：滨医_主治签名,滨医_住院签名,住院医师签名,滨医_医师签名,主治医师签名,滨医_副主任签名,滨医_主任签名,滨_医师签名
                    case '滨医_主治签名':
                    case '滨医_住院签名':
                    case '住院医师签名':
                    case '滨医_医师签名':
                    case '主治医师签名':
                    case '滨医_副主任签名':
                    case '滨医_主任签名':
                    case '滨_医师签名':
                        $QMMC = 5;
                        break;
                }

                $where = [
                    'JLXH' => $item['JLXH'],
                    'BLBH' => $item['BLBH'],
                ];
                $insertData = [
                    'JLXH' => $item['JLXH'] ?? null,
                    'BLBH' => $item['BLBH'] ?? '',
                    'SYYS' => $item['SYYS'] ?? '',
                    'SYSJ' => $item['SYSJTIME'] ?? null,
                    'JLSJ' => $item['JLSJTIME'] ?? null,
                    'FG_ACTIVE' => 1,
                    'QMLX' => $item['QMLX'] ?? '',
                    'QMMC' => $QMMC,
                    'QMYS' => $item['QMYS'] ?? '',
                ];

                \App\Model\EMR_BL_BLSY::query()->updateOrInsert($where, $insertData);
                $successCount++;

                if ($totalCount % 10000 === 0) {
                    $this->info('已处理: ' . $totalCount . ' 条，成功: ' . $successCount . ' 条，失败: ' . $failCount . ' 条');
                }
            } catch (\Exception $e) {
                $failCount++;
                $this->error('同步失败，JLXH: ' . ($item['JLXH'] ?? '未知') . '，错误: ' . $e->getMessage());
            }
        }

        oci_free_statement($result);

        if ($totalCount === 0) {
            $this->info('没有需要同步的数据');
            oci_close($con);
            return 0;
        }

        oci_close($con);

        $this->info('Oracle数据库中的EMR_BL_BLSY表同步到Mysql - 同步完毕');
        $this->info('总数: ' . $totalCount . ' 条');
        $this->info('成功: ' . $successCount . ' 条，失败: ' . $failCount . ' 条');

        return 0;
    }
}
