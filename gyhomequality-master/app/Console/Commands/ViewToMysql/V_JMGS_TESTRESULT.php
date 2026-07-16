<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use Illuminate\Console\Command;

class V_JMGS_TESTRESULT extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:v_jmgs_testresult {start} {end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步 V_JMGS_TESTRESULT';

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
        $con = oci_connect('jmba', 'jmba', '172.16.2.29:1521/bslis', "UTF8");
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
                'name' => 'laravel:v_jmgs_testresult',
                'start' => $star,
'end' => $end,
                'count' => 0,
            ];

            $sql = "SELECT ZYH,TXM,NO,XM,XB,NL,CH,YBLX,YBZT,AAA28,BQ,LCZD,YW,JYXM,JG,TS,CKFW,DW,SJYS,JYY,SHY,CJSJ,JSSJ,BGSJ FROM V_JMGS_TESTRESULT WHERE CJSJ BETWEEN TO_DATE('{$star}', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('{$end}', 'yyyy-MM-dd HH24:mi:ss')";
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
                    'ZYH' => $item['ZYH'],
                    'TXM' => $item['TXM'],
                    'NO' => $item['NO'],
                    'XM' => $item['XM'],
                    'XB' => $item['XB'],
                    'NL' => $item['NL'],
                    'CH' => $item['CH'],
                    'YBLX' => $item['YBLX'],
                    'YBZT' => $item['YBZT'],
                    'AAA28' => $item['AAA28'],
                    'BQ' => $item['BQ'],
                    'LCZD' => $item['LCZD'],
                    'YW' => $item['YW'],
                    'JYXM' => $item['JYXM'],
                    'JG' => $item['JG'],
                    'TS' => $item['TS'],
                    'CKFW' => $item['CKFW'],
                    'DW' => $item['DW'],
                    'SJYS' => $item['SJYS'],
                    'JYY' => $item['JYY'],
                    'SHY' => $item['SHY'],
                    'CJSJ' => $item['CJSJ'],
                    'JSSJ' => $item['JSSJ'],
                    'BGSJ' => $item['BGSJ'],
                ];

                \App\Model\V_JMGS_TESTRESULT::query()->updateOrInsert(['ZYH' => $insertData['ZYH']], $insertData);
            }

            //记录日志
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Y-m-d', strtotime($star) + 86400);
        }
        return 0;
    }
}
