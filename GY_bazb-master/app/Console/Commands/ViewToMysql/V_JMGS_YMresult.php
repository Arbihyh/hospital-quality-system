<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use Illuminate\Console\Command;

class V_JMGS_YMresult extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:v_jmgs_ymresult {start} {end}';

    /**
     *  docker exec homeQuality bash -c "cd /data/api && php artisan  laravel:v_jmgs_ymresult 2021-01-01 2023-01-01"
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步 v_jmgs_ymresult';

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
                'name' => 'laravel:v_jmgs_ymresult',
                'start' => $star,
'end' => $end,
                'count' => 0,
            ];

            $sql = "SELECT ZYH,TXM,NO,XM,XB,NL,CH,YBLX,YBZT,AAA28,BQ,LCZD,PYJG,XJMC,XJJL,YMMC,YMJG,YMBW,SJYS,JYY,SHY,CJSJ,JSSJ,BGSJ,EXAMINAIM,STAYHOSPITALMODE FROM V_JMGS_YMresult WHERE CJSJ BETWEEN TO_DATE('{$star}', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('{$end}', 'yyyy-MM-dd HH24:mi:ss')";
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
                    'PYJG' => $item['PYJG'],
                    'XJMC' => $item['XJMC'],
                    'XJJL' => $item['XJJL'],
                    'YMMC' => $item['YMMC'],
                    'YMJG' => $item['YMJG'],
                    'YMBW' => $item['YMBW'],
                    'SJYS' => $item['SJYS'],
                    'JYY' => $item['JYY'],
                    'SHY' => $item['SHY'],
                    'CJSJ' => $item['CJSJ'],
                    'JSSJ' => $item['JSSJ'],
                    'BGSJ' => $item['BGSJ'],
                    'EXAMINAIM' => $item['EXAMINAIM'],
                    'STAYHOSPITALMODE' => $item['STAYHOSPITALMODE'],
                ];

                \App\Model\V_JMGS_YMresult::query()->updateOrInsert(['ZYH' => $insertData['ZYH']], $insertData);
            }

            //记录日志
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Y-m-d', strtotime($star) + 86400);
        }
        return 0;
    }
}
