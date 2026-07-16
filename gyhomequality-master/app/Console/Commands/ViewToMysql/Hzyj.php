<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use App\Model\YS_ZY_HZYJ;
use Illuminate\Console\Command;

class Hzyj extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:hzyj';

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

            $sql = "SELECT BRID,MZHM,BRXM,FYZH,SFZH,BRXZ,BRXB FROM MS_BRDA";
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
                    'JLXH' => $item['BRID'],
                    'SQXH' => $item['MZHM'],
                    'HZYJ' => $item['BRXM'],
                    'HZYJ2' => $item['FYZH'],
                    'KSDM' => $item['SFZH'],
                    'SSYS' => $item['BRXZ'],
                    'SXYS' => $item['BRXB']
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
