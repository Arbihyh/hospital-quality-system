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
    protected $signature = 'laravel:ms_brda {start?} {end?}';

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

        $msBrda = \App\Model\MS_BRDA::query()->orderByDesc('id')->first();
        $syncRecordData = [
            'name' => 'laravel:ms_brda',
            'start' => "",
            'end' => $msBrda,
            'count' => 0,
            'created_at' => now(),
        ];

        $lastId = $msBrda && $msBrda->BRID ? $msBrda->BRID : 0;
        $sql = "SELECT BRID,MZHM,BRXM,FYZH,SFZH,BRXZ,BRXB FROM PORTAL_HIS.MS_BRDA WHERE BRID >{$lastId} ORDER BY BRID ";
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
                'BRXM' => desensitize($item['BRXM'], 1, 1, '*'),
                'FYZH' => $item['FYZH'],
                'SFZH' => $item['SFZH'],
                'BRXZ' => $item['BRXZ'],
                'BRXB' => $item['BRXB'],
            ];

            \App\Model\MS_BRDA::query()->updateOrInsert(['BRID' => $insertData['BRID']], $insertData);
        }

        //记录日志
        $syncRecordData['count'] = count($data);
        SyncRecord::query()->insert($syncRecordData);
        var_dump("同步完成");
        return 0;
    }
}
