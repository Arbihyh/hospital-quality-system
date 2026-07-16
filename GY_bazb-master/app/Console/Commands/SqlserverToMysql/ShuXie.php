<?php

namespace App\Console\Commands\SqlserverToMysql;

use App\Model\SM_SSAP;
use App\Model\ZY_BRRY;
use App\Model\ZY_SS;
use App\Services\DbConnectionTestService;
use Illuminate\Console\Command;

class ShuXie extends Command
{
    protected $signature = 'sqlsrv_shuxie {start_time?}';
    protected $description = '同步sqlserver的输血记录数据';

    public function handle()
    {
        $startTime = $this->argument("start_time");

        //使用laravel自带的sqlsrv方法连接
        $connectionOptions = [
            'Database' => env("SS_SQLSRV_DATABASE"),
            'UID' => env("SS_SQLSRV_USERNAME"),
            'PWD' => env("SS_SQLSRV_PASSWORD"),
            'LoginTimeout' => 10,
            'TrustServerCertificate' => 1,
            'Encrypt' => 1
        ];

        $connection = sqlsrv_connect(env("SS_SQLSRV_HOST"), $connectionOptions);
        if ($connection) {
            $this->info("连接成功！");

            // 使用直接查询获取数据
            $startTime = $startTime ? date("Y-m-d 00:00:00", strtotime($startTime)) : date("Y-m-d 00:00:00", time()-7*24*3600);
            $sql = "SELECT * FROM BLOOD_BLZK";
//            $sql = "SELECT * FROM BLOOD_BLZK WHERE SQRQ>'{$startTime}'";

            $stmt = sqlsrv_query($connection, $sql);

            if ($stmt === false) {
                $errors = sqlsrv_errors();
                foreach ($errors as $error) {
                    $this->error("查询失败: SQLSTATE: {$error['SQLSTATE']}, 错误码: {$error['code']}, 错误信息: {$error['message']}");
                }
            } else {
                $this->info("查询成功!");

                // 获取所有数据，并计算每列的最大宽度
                $rows = [];
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $formattedRow = [];
                    foreach ($row as $key => $value) {
                        if ($value instanceof \DateTime) {
                            $formattedValue = $value->format('Y-m-d H:i:s');
                        } else {
                            $formattedValue = $value === null ? 'NULL' : (string)$value;
                        }


                        $formattedRow[$key] = $formattedValue;
                    }
                    $rows[] = $formattedRow;
                }
                // 打印数据行
                foreach ($rows as $row) {

                    ZY_SS::query()->updateOrInsert(["SXXH"=>$row["SXXH"]], $row);
                }

                $this->info("总共获取 " . count($rows) . " 条记录");
            }

            // 释放资源
            sqlsrv_free_stmt($stmt);
            sqlsrv_close($connection);
        } else {
            $errors = sqlsrv_errors();
            if (is_array($errors)) {
                foreach ($errors as $error) {
                    $this->error("连接失败: SQLSTATE: {$error['SQLSTATE']}, 错误码: {$error['code']}, 错误信息: {$error['message']}");
                }
            } else {
                $this->error("连接失败: 未知错误");
            }
        }
    }
}
