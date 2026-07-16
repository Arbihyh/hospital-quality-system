<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use App\Model\YS_ZY_HZYJ;
use Illuminate\Console\Command;

class MS_BRDA extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:ms_brda {start} {end}';

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
        $con = oci_connect('zdyh', 'zdyh', '172.16.9.8:1521/his', "UTF8");
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
                'name' => 'laravel:ms_brda',
                'start' => $star,
                'end' => $end,
                'count' => 0,
                'created_at' => now(),
            ];

            $msBrda = \App\Model\MS_BRDA::query()->orderByDesc('id')->first();

            $sql = "SELECT BRID,MZHM,BRXM,FYZH,SFZH,BRXZ,BRXB FROM PORTAL_HIS.BTF_MS_BRDA WHERE BRID >{$msBrda->BRID} ORDER BY BRID ";
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
                    'BRID' => $item['BRID'],
                    'MZHM' => $item['MZHM'],
                    'BRXM' => desensitize($item['BRXM'],1,1,'*'),
                    'FYZH' => $item['FYZH'],
                    'SFZH' => $item['SFZH'],
                    'BRXZ' => $item['BRXZ'],
                    'BRXB' => $item['BRXB'],
                ];

                \App\Model\MS_BRDA::query()->updateOrInsert(['BRID'=>$insertData['BRID']],$insertData);
            }

            //记录日志
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Y-m-d',strtotime($star)+86400);
        }
        return 0;
    }
}
