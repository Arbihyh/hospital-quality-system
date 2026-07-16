<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use Illuminate\Console\Command;

class EMR_BL_BLSY extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:emr_bl_blsy {start} {end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步 住院病历医生签名';

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
        $con = oci_connect('zdyh', 'zdyh', '172.16.9.8:1521/his', "UTF8");
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        while (true) {
            echo $star . PHP_EOL;
            if ($star > $end) {
                break;
            }
            $syncRecordData = [
                'name' => 'laravel:emr_bl_blsy',
                'start' => $star,
                'end' => $end,
                'count' => 0,
                'created_at' => now(),
            ];

            $sql = "select BLBH,SYYS,to_char(SYSJ,'yyyy-mm-dd hh24:mi:ss') as SYSJ,to_char(JLSJ,'yyyy-mm-dd hh24:mi:ss') as JLSJ from PORTAL55_EMR.EMR_BL_BLSY WHERE SYSJ BETWEEN TO_DATE('{$star}', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('{$end}', 'yyyy-MM-dd HH24:mi:ss')";
            $result = oci_parse($con, $sql);
            oci_execute($result, OCI_DEFAULT);
            $data = [];
            while ($row = oci_fetch_assoc($result)) {
                $data[] = $row;
            }
            if (empty($data)) {
                SyncRecord::query()->insert($syncRecordData);
                exit;
            }

            foreach ($data as $item) {
                $insertData = [
                    'BLBH' => $item['BLBH'],
                    'SYYS' => $item['SYYS'],
                    'SYSJ' => $item['SYSJ'],
                    'JLSJ' => $item['JLSJ'],
                ];

                \App\Model\EMR_BL_BLSY::query()->updateOrInsert(['BLBH' => $insertData['BLBH']], $insertData);
            }

            //记录日志
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Y-m-d', strtotime($star) + 86400);
        }
        return 0;
    }
}
