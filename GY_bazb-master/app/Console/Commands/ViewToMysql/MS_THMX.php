<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use Illuminate\Console\Command;

class MS_THMX extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:ms_thmx {start} {end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步门诊挂号退费';

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
                'name' => 'laravel:ms_thmx',
                'start' => $star,
                'end' => $end,
                'count' => 0,
                'created_at' => now(),
            ];

            $sql = "SELECT SBXH,CZGH,to_char(JZRQ,'yyyy-mm-dd hh24:mi:ss') as JZRQ,MZLB,to_char(HZRQ,'yyyy-mm-dd hh24:mi:ss') as HZRQ,to_char(THRQ,'yyyy-mm-dd hh24:mi:ss') as THRQ,JGID,TCKF,TBLF FROM PORTAL_HIS.MS_THMX WHERE JZRQ BETWEEN TO_DATE('{$star}', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('{$end}', 'yyyy-MM-dd HH24:mi:ss')";
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
                    'CZGH' => $item['CZGH'],
                    'JZRQ' => $item['JZRQ'],
                    'MZLB' => $item['MZLB'],
                    'HZRQ' => $item['HZRQ'],
                    'THRQ' => $item['THRQ'],
                    'JGID' => $item['JGID'],
                    'TCKF' => $item['TCKF'],
                    'TBLF' => $item['TBLF'],
                ];

                \App\Model\MS_THMX::query()->updateOrInsert(['SBXH' => $insertData['SBXH']], $insertData);
            }

            //记录日志
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Y-m-d', strtotime($star) + 86400);
        }
        return 0;
    }
}
