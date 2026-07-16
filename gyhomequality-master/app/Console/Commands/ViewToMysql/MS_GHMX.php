<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use Illuminate\Console\Command;

class MS_GHMX extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:ms_ghmx {start?} {end?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步 门诊挂号费';

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
            $end = date("Y-m-d H:i:s", time()-9*24*3600);
        }
        if(empty($end)){
            $end = date("Y-m-d H:i:s", time());
        }

        $username = env('ORACLE_USERNAME', 'zdyh');
        $password = env('ORACLE_PASSWORD', 'zdyh');
        $connection = env('ORACLE_HOST', '172.16.9.8');
        $port = env('ORACLE_PORT', '1521');
        $tns = env('ORACLE_TNS', 'his');
        $con = oci_connect($username, $password,  $connection.':'.$port.'/'.$tns, 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $syncRecordData = [
            'name' => 'laravel:ms_ghmx',
            'start' => $star,
            'end' => $end,
            'count' => 0,
        ];

        $sql = "SELECT SBXH,BRID,BRXZ,to_char(GHSJ,'yyyy-mm-dd hh24:mi:ss') as GHSJ,GHLB,KSDM,YSDM,JZYS,JZXH,GHCS,ZHLB,JZJS,to_char(JZRQ,'yyyy-mm-dd hh24:mi:ss') AS JZRQ,to_char(HZRQ,'yyyy-mm-dd hh24:mi:ss') AS HZRQ,JZHM,JZZT FROM PORTAL_HIS.MS_GHMX WHERE JZRQ BETWEEN TO_DATE('{$star}', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('{$end}', 'yyyy-MM-dd HH24:mi:ss')";
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
                'SBXH' => $item['SBXH'],
                'BRID' => $item['BRID'],
                'BRXZ' => $item['BRXZ'],
                'GHSJ' => $item['GHSJ'],
                'GHLB' => $item['GHLB'],
                'KSDM' => $item['KSDM'],
                'YSDM' => $item['YSDM'],
                'JZYS' => $item['JZYS'],
                'JZXH' => $item['JZXH'],
                'GHCS' => $item['GHCS'],
                'ZHLB' => $item['ZHLB'],
                'JZJS' => $item['JZJS'],
                'JZRQ' => $item['JZRQ'],
                'HZRQ' => $item['HZRQ'],
                'JZHM' => $item['JZHM'],
                'JZZT' => $item['JZZT'],
            ];

            \App\Model\MS_GHMX::query()->updateOrInsert(['SBXH' => $insertData['SBXH']], $insertData);
        }

        //记录日志
        $syncRecordData['count'] = count($data);
        SyncRecord::query()->insert($syncRecordData);

        return 0;
    }
}
