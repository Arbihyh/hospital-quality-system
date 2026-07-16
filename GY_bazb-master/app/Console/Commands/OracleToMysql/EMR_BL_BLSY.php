<?php

namespace App\Console\Commands\OracleToMysql;

use Illuminate\Console\Command;

class EMR_BL_BLSY extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oracleToMysql:EMR_BL_BLSY {startDate?}';

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
        $host = '192.168.52.98';
        $port = '1521';
        $database = 'storcl';
        $username = 'btf';
        $password = 'btf';
        $schema = 'WHIS_EMR';

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

        // SQL查询，使用to_char格式化时间字段
        $sql = "SELECT 
                    JLXH,
                    QMLX,
                    BLBH,
                    SYYS,
                    to_char(SYSJ,'yyyy-mm-dd hh24:mi:ss') as SYSJTIME,
                    to_char(JLSJ,'yyyy-mm-dd hh24:mi:ss') as JLSJTIME,
                    FG_ACTIVE,
                    QMYS
                FROM {$schema}.EMR_BL_BLSY 
                WHERE JLSJ >= TO_DATE('{$startDate}', 'yyyy-MM-dd HH24:mi:ss')";

        $result = oci_parse($con, $sql);
        oci_execute($result, OCI_DEFAULT);

        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }

        if (empty($data)) {
            $this->info('没有需要同步的数据');
            oci_close($con);
            return 0;
        }

        $this->info('共查询到 ' . count($data) . ' 条数据，开始同步...');

        $successCount = 0;
        $failCount = 0;

        foreach ($data as $item) {
            try {
                $QMMC = 0;
                switch ($item['QMYS'] ?? '') {
                    case '主刀医师签名':
                        $QMMC = 6;
                        break;
                    case '医师签名':
                    case '科室负责人签名':
                    case '主持人签名':
                    case '会诊专家1签名':
                    case '会诊专家2签名':
                    case '会诊专家3签名':
                    case '会诊专家签名':
                    case '医师签名-1':
                    case '医师签名-2':
                    case '医师签名-3':
                    case '审核医师签名':
                    case '麻醉医师签名':
                        $QMMC = 5;
                        break;
                    case '患者签名':
                    case '患者1签名':
                    case '患者2签名':
                    case '患方0签名':
                    case '患方1签名':
                    case '患方2签名':
                    case '患方3签名':
                    case '陈述者签名':
                        $QMMC = 3;
                        break;
                    case '被授权人签名':
                    case '被授权者签名':
                    case '被授权人0签名':
                    case '被授权者1签名':
                    case '被授权人2签名':
                        $QMMC = 4;
                        break;
                    case '患者手写意见1':
                    case '患方手写意见':
                    case '患者手写意见':
                    case '患方手写意见1':
                    case '患者手写意见2':
                    case '患方手写意见2':
                    case '患者手写意见3':
                    case '患方手写意见3':
                        $QMMC = 1;
                        break;
                    case '护士签名':
                        $QMMC = 7;
                        break;
                }

                $where = [
                    'JLXH' => $item['JLXH'],
                    'BLBH3' => $item['BLBH'] ?? '',
                ];
                $insertData = [
                    'JLXH' => $item['JLXH'] ?? null,
                    'BLBH' => $item['BLBH'] ?? '',
                    'BLBH3' => $item['BLBH'] ?? '',
                    'SYYS' => $item['SYYS'] ?? '',
                    'SYSJ' => $item['SYSJTIME'] ?? null,
                    'JLSJ' => $item['JLSJTIME'] ?? null,
                    'FG_ACTIVE' => $item['FG_ACTIVE'] ?? '',
                    'QMLX' => ($item['QMLX'] ?? null) == 1 ? 1 : 2,
                    'QMMC' => $QMMC,
                    'QMYS' => $item['QMYS'] ?? '',
                ];

                \App\Model\EMR_BL_BLSY::query()->updateOrInsert($where, $insertData);
                $successCount++;
            } catch (\Exception $e) {
                $failCount++;
                $this->error('同步失败，JLXH: ' . ($item['JLXH'] ?? '未知') . '，错误: ' . $e->getMessage());
            }
        }

        oci_close($con);

        $this->info('Oracle数据库中的EMR_BL_BLSY表同步到Mysql - 同步完毕');
        $this->info('成功: ' . $successCount . ' 条，失败: ' . $failCount . ' 条');

        return 0;
    }
}
