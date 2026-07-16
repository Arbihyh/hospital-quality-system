<?php

namespace App\Console\Commands;

use App\Model\DataxSyncSetting;
use Illuminate\Console\Command;
use App\Model\SJKPZ;
use App\Model\SJKCONN;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DataxSyncCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'datax-sync-create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成数据同步JSON配置';

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
        try {
            $this->info('开始生成数据同步配置...');

            // 获取数据库连接配置
            $sjkConn = SJKCONN::query()->get()->keyBy('SJK_ID')->toArray();

            if(empty($sjkConn)) die('请配置ODS数据库信息！！！');

            // 获取凭证数据并按表分组
            $pzList = SJKPZ::query()->whereNotNull('HIS_TABLE')->whereNotNull('JM_TABLE')
                ->whereNotNull('HIS_FIELD')->whereNotNull('JM_FIELD')
                ->where('HIS_TABLE', '!=', '')
                ->where('JM_TABLE', '!=', '')
                ->where('HIS_FIELD', '!=', '')
                ->where('JM_FIELD', '!=', '')
                ->orderBy('HIS_TABLE', 'asc')
                ->orderBy('JM_TABLE', 'asc')
                ->get()->toArray();

            // 按源表和目标表分组
            $groupedPz = [];
            foreach ($pzList as $pz) {
                if (!empty($pz['HIS_TABLE'])) {
                    $key = $pz['HIS_TABLE'] . '_' . $pz['JM_TABLE'];
                    $groupedPz[$key][] = $pz;
                }
            }

             $targetConn = [
                 'IP' => env('DB_HOST', '127.0.0.1'),
                 'PORT' => env('DB_PORT', '3306'),
                 'USERNAME' => env('DB_USERNAME', 'root'),
                 'PASSWORD' => env('DB_PASSWORD', 'Jmjk@886'),
                 'SID' => env('DB_DATABASE', 'quality')
             ];

            $ex = 0;
            // 为每组表创建单独的配置文件
            foreach ($groupedPz as $tableGroup) {
                $firstPz = $tableGroup[0];
                $ex++;

                // 获取源数据库和目标数据库的连接信息
                $sourceConn = $sjkConn[$firstPz['SJK_ID']] ?? null;
                if (!$sourceConn) continue;

                // 检查并添加缺少的字段
                $this->checkAndAddFields($firstPz['JM_TABLE'], $tableGroup);

                // 构建字段映射
                $selectFields = $targetFields = $joinClauses = $joinTables = $whereConditions = [];
                $mainTable = $firstPz['HIS_TABLE'];

                foreach ($tableGroup as $pz) {
                    if (!empty($pz['WHERE_CONDITION'])) {
                        $whereConditions[] = $pz['WHERE_CONDITION'];
                    }

                    if (strpos($pz['HIS_FIELD'], '+') !== false) {
                        // 处理包含加号的字段
                        $fields = explode('+', trim(str_replace(["CDA.", "ODS."], "", $pz['HIS_FIELD'])));
                        $tableName = trim(str_replace(["CDA.", "ODS."], "", $pz['HIS_TABLE']));
                        $concatSql = '';

                        // 使用 || 运算符连接字段
                        foreach ($fields as $index => $field) {
                            if (!empty($field)) {
                                $field = "NVL(" . trim($tableName . "." . $field) . ", '')";
                                if ($index === 0) {
                                    $concatSql = $field;
                                } else {
                                    $concatSql .= " || '" . $pz['HIS_FIELD_ZF'] . "' || " . $field;
                                }
                            }

                        }

                        $selectFields[] = "({$concatSql}) AS {$pz['JM_FIELD']}";
                    } elseif (strpos($pz['HIS_FIELD'], '-') !== false) {
                        // 处理包含减号的字段（表连接）
                        list($tableName, $fieldName) = explode('.', $pz['HIS_FIELD']);
                        $tableName = trim($tableName);
                        $fieldName = trim($fieldName);
                        $fieldName = trim(str_replace("-", "", $fieldName));
                        $fieldName = trim(str_replace(["CDA.", "ODS."], "", $fieldName));

                        // 避免重复添加同一个表的连接
                        if (!isset($joinTables[$tableName])) {
                            // 从 HIS_FIELD_ZF 中分离 JOIN 和 WHERE 条件
                            $joinStr = $pz['HIS_FIELD_ZF'];
                            if (stripos($joinStr, 'WHERE') !== false) {
                                list($joinPart, $wherePart) = explode('WHERE', $joinStr, 2);
                                // 保存 JOIN 部分
                                $joinClauses[] = str_replace('CDA.', '', trim($joinPart));
                                // 保存 WHERE 条件
                                $whereConditions[] = trim($wherePart);
                            } else {
                                // 如果没有 WHERE 条件，直接保存 JOIN 语句
                                $joinClauses[] = str_replace('CDA.', '', $joinStr);
                            }
                            $joinTables[$tableName] = true;
                        }

                        $selectFields[] = "NVL({$tableName}.{$fieldName}, '') AS {$pz['JM_FIELD']}";
                    } else {
                        $fieldName = trim(str_replace(["CDA.", "ODS."], "", $pz['HIS_FIELD']));
                        $tableName = trim(str_replace(["CDA.", "ODS."], "", $pz['HIS_TABLE']));
                        if (strpos($fieldName, "CASE") !== false) {
                            $selectFields[] = "NVL({$fieldName}, '') AS {$pz['JM_FIELD']}";
                        } else {
                            $selectFields[] = "NVL({$tableName}.{$fieldName}, '') AS {$pz['JM_FIELD']}";
                        }

                    }

                    $targetFields[] = "`{$pz['JM_FIELD']}`";
                }

                // 构建完整的SQL查询
                $sqlQuery = "SELECT " . implode(', ', $selectFields) .
                    " FROM {$mainTable} ";

                // 先添加所有 JOIN 语句
                if (!empty($joinClauses)) {
                    $sqlQuery .= implode(' ', array_unique($joinClauses)) . " ";
                }

                // 最后添加所有 WHERE 条件
                if (!empty($whereConditions)) {
                    // 使用 AND 连接所有 WHERE 条件
                    $sqlQuery .= "WHERE " . implode(' AND ', array_unique($whereConditions));
                }

                // 构建 UPDATE 语句
                $updateFields = array_map(function ($field) {
                    // 移除字段两端的反引号
                    $field = trim($field, '`');
                    return "{$field}=VALUES({$field})";
                }, $targetFields);

                // 构建配置
                $config = [
                    'job' => [
                        'setting' => [
                            'speed' => [
                                'channel' => 3,
                                'byte' => 1048576
                            ],
                            'errorLimit' => [
                                'record' => 0,
                                'percentage' => 0.02
                            ]
                        ],
                        'content' => [
                            [
                                'reader' => [
                                    'name' => 'oraclereader',
                                    'parameter' => [
                                        'username' => $sourceConn['username'],
                                        'password' => $sourceConn['password'],
                                        'splitPk' => '',
                                        'connection' => [
                                            [
                                                'querySql' => [
                                                    $sqlQuery
                                                ],
                                                'jdbcUrl' => [
                                                    "jdbc:oracle:thin:@//{$sourceConn['host']}:{$sourceConn['port']}/{$sourceConn['database']}"
                                                ]
                                            ]
                                        ]
                                    ]
                                ],
                                'writer' => [
                                    'name' => 'mysqlwriter',
                                    'writeMode' => 'insert',
                                    'parameter' => [
                                        'username' => $targetConn['USERNAME'],
                                        'password' => $targetConn['PASSWORD'],
                                        'column' => $targetFields,
                                        'connection' => [
                                            [
                                                'table' => [
                                                    $firstPz['JM_TABLE']
                                                ],
                                                'jdbcUrl' => "jdbc:mysql://{$targetConn['IP']}:{$targetConn['PORT']}/{$targetConn['SID']}?useSSL=false"
                                            ]
                                        ],

                                    ]
                                ]
                            ]
                        ]
                    ]
                ];

                $time = date('Y-m-d H:i:s');
                $job_desc = $pz['HIS_TABLE'] . "->" . $pz['JM_TABLE'] . "-" . $ex;
                $dataxData = [
                    'job_group' => 1,
                    'job_cron' => '00 00 01 * * ? *',
//                    'job_desc' => $job_desc,
                    'project_id' => 1,
                    'user_id' => 1,
                    'executor_route_strategy' => 'FIRST',
                    'executor_handler' => 'executorJobHandler',
                    'executor_block_strategy' => 'SERIAL_EXECUTION',
                    'glue_type' => 'BEAN',
                    'job_json' => json_encode($config, 256),
                    'replace_param_type' => 'Timestamp',
                    'add_time' => $time,
                    'update_time' => $time,
                    'alarm_email' => '',
                    'executor_param' => '',
                    'glue_updatetime' => $time,
                    'child_jobid' => '',
                    'last_handle_code' => 0,
                    'datasource_id' => 0
                ];

                // 在代码开始处添加 dataxweb 数据库配置
                Config::set('database.connections.datax_web', [
                    'driver' => 'mysql',
                    'host' =>'10.32.45.110',
                    'port' => '3306',
                    'database' => 'datax_web',
                    'username' => 'root',
                    'password' => 'Jmjk@886',
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => '',
                    'strict' => true,
                    'engine' => null,
                ]);
                $this->info('开始保存数据同步配置...');

                $datax_res = Db::connection('datax_web')->table('job_info')->updateOrInsert(['job_desc' => $job_desc],$dataxData);

                //创建新表保存 查询语句 $sqlQuery

                $setting_res = DataxSyncSetting::query()->updateOrInsert(['job_desc' => $job_desc],['sql_content' => $sqlQuery,'table_name' => $pz['JM_TABLE'],'table_num' => $ex]);

                $this->info("datax保存状态::{$datax_res},datax_sync_setting保存状态::{$setting_res},任务名称::{$job_desc}");

                // 生成并保存配置文件
                $jsonConfig = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                file_put_contents(storage_path("app/{$job_desc}.json"), $jsonConfig);

                $this->info("配置文件{$ex}已保存到: " . storage_path("app/{$job_desc}.json"));

                $this->info("源表: {$firstPz['HIS_TABLE']} -> 目标表: {$firstPz['JM_TABLE']}");

                $this->line("----------------------------------------");
            }

            $this->info("共生成 {$ex} 个配置文件");
        } catch (\Exception $e) {
            $this->error('生成配置失败: ' . $e->getMessage() . $e->getLine());
            die('生成配置失败: ' . $e->getMessage());
        }

        die("任务执行完成");
    }

    private function checkAndAddFields($tableName, $tableGroup)
    {
        // 获取当前表的字段
        $existingColumns = Schema::connection('mysql')->getColumnListing($tableName);

        foreach ($tableGroup as $pz) {
            $fieldName = $pz['JM_FIELD'];

            if (!in_array($fieldName, $existingColumns)) {
                // 如果字段不存在，添加字段
                Schema::connection('mysql')->table($tableName, function ($table) use ($fieldName) {
                    $table->string($fieldName)->nullable();
                });

                $this->info("字段 {$fieldName} 已添加到表 {$tableName}");
            }
        }
    }
}
