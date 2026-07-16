<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use App\Model\YS_ZY_HZYJ;
use Illuminate\Console\Command;

class OMR_BLSY extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:omr_blsy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $con = oci_connect('ogg', 'ogg#2022', '172.16.9.8:1521/ODS',"UTF8");
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        while (true) {
            echo $star.PHP_EOL;
            if ($star > $end) {
                break;
            }
            $syncRecordData = [
                'name' => 'laravel:v_jmgs_basy_qbl',
                'start' => $star,
'end' => $end,
                'count' => 0,
            ];

            $sql = "SELECT JLXH,SQXH,HZYJ,HZYJ2,KSDM,SSYS,SXYS,to_char(SXSJ,'yyyy-mm-dd hh24:mi:ss') as SXSJ,to_char(QMSJ,'yyyy-mm-dd hh24:mi:ss') as QMSJ FROM PORTAL_HIS.YS_ZY_HZYJ WHERE SXSJ BETWEEN TO_DATE('".$star." 00:00:00', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('".$star." 23:59:59', 'yyyy-MM-dd HH24:mi:ss')";
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
                    'JLXH' => $item['JLXH'],
                    'SQXH' => $item['BLBH'],
                    'HZYJ' => $item['HZYJ'],
                    'HZYJ2' => $item['HZYJ2'],
                    'KSDM' => $item['KSDM'],
                    'SSYS' => $item['SSYS'],
                    'SXYS' => $item['SXYS'],
                    'SYSJ' => $item['SXSJ'],
                    'QMSJ' => $item['QMSJ']
                ];

                YS_ZY_HZYJ::query()->updateOrInsert(['SQXH'=>$insertData['SQXH']],$insertData);
            }

            //记录日志
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Y-m-d',strtotime($star)+86400);
        }
        return 0;
    }
}
