<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use Illuminate\Console\Command;

class BA_BASE extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:ba_base {start} {end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步 无纸化';

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
        ini_set('default_socket_timeout', 0);

        $star = $this->argument('start');
        $end = $this->argument('end');

        $serverName = "172.16.2.177,1433"; //数据库服务器地址
        $uid = "zzj"; //数据库用户名
        $pwd = "zzj"; //数据库密码
        $connectionInfo = array("UID"=>$uid, "PWD"=>$pwd, "Database"=>"CEMS");
        $con = sqlsrv_connect($serverName, $connectionInfo);

        if (!$con) {
            $e = sqlsrv_errors();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        while (true) {
            echo $star . PHP_EOL;
            if ($star > $end) {
                break;
            }
            $syncRecordData = [
                'name' => 'laravel:ba_base',
                'start' => $star,
'end' => $end,
                'count' => 0,
            ];

            $sql = "SELECT bah,zycs,brxm,brsfzh,cysj,cyksname,mr_archive_time FROM BA_BASE  WHERE cysj BETWEEN TO_DATE('{$star}', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('{$end}', 'yyyy-MM-dd HH24:mi:ss')";
            $result = sqlsrv_query($con, $sql);
            $data = [];
            while ($row = sqlsrv_fetch_array($result,SQLSRV_FETCH_ASSOC)) {
                $data[] = $row;
            }

            if (empty($data)) {
                SyncRecord::query()->insert($syncRecordData);
                exit;
            }
            foreach ($data as $item) {
                $insertData = [
                    'bah' => $item['bah'],
                    'zycs' => $item['zycs'],
                    'brxm' => $item['brxm'],
                    'brsfzh' => $item['brsfzh'],
                    'cysj' => $item['cysj'],
                    'cyksname' => $item['cyksname'],
                    'mr_archive_time' => $item['mr_archive_time'],
                ];

                \App\Model\BA_BASE::query()->updateOrInsert(['bah' => $insertData['bah']], $insertData);
            }

            //记录日志
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Y-m-d', strtotime($star) + 86400);
        }
        return 0;
    }
}
