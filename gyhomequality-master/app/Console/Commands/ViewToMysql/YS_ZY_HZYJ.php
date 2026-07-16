<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use Illuminate\Console\Command;

class YS_ZY_HZYJ extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:ys_zy_hzyj {start?} {end?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步 YS_ZY_HZYJ';

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

        if(empty($star)){
            $star = date("Y-m-d H:i:s", time()-9*24*3600);
        }
        if(empty($end)){
            $end = date("Y-m-d H:i:s", time());
        }

        $username = env('ORACLE_USERNAME', 'zdyh');
        $password = env('ORACLE_PASSWORD', 'zdyh');
        $connection = env('ORACLE_HOST', '172.16.9.8');
        $port = env('ORACLE_PORT', '1521');
        $tns = env('ORACLE_TNS', 'his');
        $con = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        echo $star . '-' . $end . PHP_EOL;
        $syncRecordData = [
            'name' => 'laravel:ys_zy_hzyj',
            'start' => $star,
            'end' => $end,
            'count' => 0,
            'created_at' => now(),
        ];

        $sql = "SELECT JLXH,SQXH,HZYJ,HZYJ2,KSDM,SSYS,SXYS,to_char(SXSJ,'yyyy-mm-dd hh24:mi:ss') as SXSJ,to_char(QMSJ,'yyyy-mm-dd hh24:mi:ss') as QMSJ FROM PORTAL_HIS.YS_ZY_HZYJ WHERE SXSJ BETWEEN TO_DATE('{$star}', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('{$end}', 'yyyy-MM-dd HH24:mi:ss')";
        $result = oci_parse($con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            SyncRecord::query()->insert($syncRecordData);
            var_dump("未同步到数据");
            exit;
        }
        foreach ($data as $item) {
            $insertData = [
                'JLXH' => $item['JLXH'],
                'SQXH' => $item['SQXH'],
                'HZYJ' => $item['HZYJ'],
                'HZYJ2' => $item['HZYJ2'],
                'KSDM' => $item['KSDM'],
                'SSYS' => $item['SSYS'],
                'SXYS' => $item['SXYS'],
                'SXSJ' => $item['SXSJ'],
                'QMSJ' => $item['QMSJ'],
            ];

            \App\Model\YS_ZY_HZYJ::query()->updateOrInsert(['JLXH' => $insertData['JLXH']], $insertData);
        }

        //记录日志
        $syncRecordData['count'] = count($data);
        SyncRecord::query()->insert($syncRecordData);
        var_dump("同步完成");
        return 0;
    }
}
