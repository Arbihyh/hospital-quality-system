<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use Illuminate\Console\Command;

class YJ_ZY01 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:yj_zy01 {start} {end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步医技';

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
                'name' => 'laravel:yj_zy01',
                'start' => $star,
                'end' => $end,
                'count' => 0,
                'created_at' => now(),
            ];

            $sql = "SELECT YJXH,TJHM,ZYH,ZYHM,BRXM,to_char(KDRQ,'yyyy-mm-dd hh24:mi:ss') as KDRQ,KSDM,YSDM,to_char(ZXRQ,'yyyy-mm-dd hh24:mi:ss') as ZXRQ,ZXKS,ZXPB,HJGH,BBBM,ZYSX,ZFPB,HYMX,YJPH,SQDH,BWID,JBID,DJZT,SQWH,FYBQ,SQID,YQDH,JGID,SSYS,SSYZ,SSEZ,SSSZ,JZBZ FROM PORTAL_HIS.YJ_ZY01 WHERE KDRQ BETWEEN TO_DATE('{$star}', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('{$end}', 'yyyy-MM-dd HH24:mi:ss')";
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
                    'YJXH' => $item['YJXH'],
                    'TJHM' => $item['TJHM'],
                    'ZYH' => $item['ZYH'],
                    'ZYHM' => $item['ZYHM'],
                    'BRXM' => desensitize($item['BRXM'],1,1,'*'),
                    'KDRQ' => $item['KDRQ'],
                    'KSDM' => $item['KSDM'],
                    'YSDM' => $item['YSDM'],
                    'ZXRQ' => $item['ZXRQ'],
                    'ZXKS' => $item['ZXKS'],
                    'ZXPB' => $item['ZXPB'],
                    'BBBM' => $item['BBBM'],
                    'ZYSX' => $item['ZYSX'],
                    'ZFPB' => $item['ZFPB'],
                    'HYMX' => $item['HYMX'],
                    'YJPH' => $item['YJPH'],
                    'SQDH' => $item['SQDH'],
                    'BWID' => $item['BWID'],
                    'JBID' => $item['JBID'],
                    'DJZT' => $item['DJZT'],
                    'SQWH' => $item['SQWH'],
                    'FYBQ' => $item['FYBQ'],
                    'SQID' => $item['SQID'],
                    'YQDH' => $item['YQDH'],
                    'JGID' => $item['JGID'],
                    'SSYS' => $item['SSYS'],
                    'SSYZ' => $item['SSYZ'],
                    'SSEZ' => $item['SSEZ'],
                    'SSSZ' => $item['SSSZ'],
                    'JZBZ' => $item['JZBZ'],
                ];

                \App\Model\YJ_ZY01::query()->updateOrInsert(['YJXH' => $insertData['YJXH']], $insertData);
            }

            //记录日志
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Y-m-d', strtotime($star) + 86400);
        }
        return 0;
    }
}
