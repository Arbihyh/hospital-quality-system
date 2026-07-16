<?php

namespace App\Console\Commands\SqlserverToMysql;

use App\Model\SM_SSAP;
use App\Model\Staff;
use App\Model\ZY_BRRY;
use App\Services\DbConnectionTestService;
use Illuminate\Console\Command;

class ShoumaSrv extends Command
{
    protected $signature = 'sqlsrv_shouma {start_time?}';
    protected $description = '同步sqlserver的手麻数据';

    public function handle()
    {
        $startTime = $this->argument("start_time");

        //使用laravel自带的sqlsrv方法连接
        $connectionOptions = [
            'Database' => env("SQLSRV_DATABASE"),
            'UID' => env("SQLSRV_USERNAME"),
            'PWD' => env("SQLSRV_PASSWORD"),
            'LoginTimeout' => 10,
            'TrustServerCertificate' => 1,
            'Encrypt' => 1
        ];

        $connection = sqlsrv_connect(env("SQLSRV_HOST"), $connectionOptions);
        if ($connection) {
            $this->info("连接成功！");

            // 使用直接查询获取数据
            $startTime = $startTime ? date("Y-m-d 00:00:00", strtotime($startTime)) : date("Y-m-d 00:00:00", time() - 24 * 7 * 3600);
            $sql = "SELECT * FROM btf_ssap WHERE GXSJ>'{$startTime}'";

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

                $staff = Staff::query()->get()->toArray();
                $staff = array_column($staff, "code", "YGBH");
                // 打印数据行
                foreach ($rows as $row) {
                    $brry = ZY_BRRY::query()->where(["AAA28" => $row["BAH"], "ZYCS" => $row["ZYCS"]])->first(["ZYH"]);
                    $row["ZYH"] = "";
                    if ($brry) {
                        $row["ZYH"] = $brry->ZYH;
                    } else {

                        $brry = ZY_BRRY::query()
                            ->where('AAA28', $row["BAH"])
                            ->where('AAB01', '<=', $row['SSRQ'])
                            ->where(function ($query) use ($row) {
                                $query->where('AAC01', '>=', $row['SSRQ'])->orWhereNull('AAC01')->orWhere('AAC01', '');
                            })
                            ->first();

                        if ($brry) {
                            $row["ZYH"] = $brry->ZYH;
                        }
                    }
                    $row["flag"] = "米健";
                    $row["SZDM"] = $staff[$row["SZDM"]] ?? $row["SZDM"];
                    $row["SQYS"] = $staff[$row["SQYS"]] ?? $row["SQYS"];
                    SM_SSAP::addData($row);
                    $this->line($row["SQDH"]);
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
