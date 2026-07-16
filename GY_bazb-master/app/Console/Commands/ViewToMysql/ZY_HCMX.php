<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use Illuminate\Console\Command;

class ZY_HCMX extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:zy_hcmx {start} {end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步护士分床时间';

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
                'name' => 'laravel:zy_hcmx',
                'start' => $star,
                'end' => $end,
                'count' => 0,
                'created_at' => now(),
            ];

            $sql = "SELECT ZYH,to_char(HCRQ,'yyyy-mm-dd hh24:mi:ss') as HCRQ,to_char(ZZRQ,'yyyy-mm-dd hh24:mi:ss') as ZZRQ,HCLX,HQCH,HHCH,HQKS,HHKS,HQBQ,HHBQ,JSCS,CZGH,JGID FROM PORTAL_HIS.ZY_HCMX WHERE HCRQ BETWEEN TO_DATE('{$star}', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('{$end}', 'yyyy-MM-dd HH24:mi:ss')";
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
                    'HCRQ' => $item['HCRQ'],
                    'ZZRQ' => $item['ZZRQ'],
                    'HCLX' => $item['HCLX'],
                    'HQCH' => $item['HQCH'],
                    'HHCH' => $item['HHCH'],
                    'HQKS' => $item['HQKS'],
                    'HHKS' => $item['HHKS'],
                    'HQBQ' => $item['HQBQ'],
                    'HHBQ' => $item['HHBQ'],
                    'JSCS' => $item['JSCS'],
                    'CZGH' => $item['CZGH'],
                    'JGID' => $item['JGID']
                ];

                \App\Model\ZY_HCMX::query()->updateOrInsert(['ZYH' => $insertData['ZYH'],'HCRQ' => $insertData['HCRQ']], $insertData);
            }

            //记录日志
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Y-m-d', strtotime($star) + 86400);



            /*
docker exec homeQuality bash -c "cd /data/api && php artisan  laravel:ms_ghmx '2023-07-01 00:00:00' '2023-08-14 00:00:00'" >> /data/sh/logs/test/ms_ghmx.log

docker exec homeQuality bash -c "cd /data/api && php artisan  laravel:bl01v2 '2023-07-01 00:00:00' '2023-08-14 00:00:00'" >> /data/mt_medical_record_first_pat.log

docker exec homeQuality bash -c "cd /data/api && php artisan  laravel:ms_thmx '2023-07-01 00:00:00' '2023-08-14 00:00:00'" >> /data/sh/logs/test/ms_thmx.log

docker exec homeQuality bash -c "cd /data/api && php artisan  laravel:ms_cf01 '2023-07-01 00:00:00' '2023-08-14 00:00:00'" >> /data/sh/logs/test/ms_cf01.log

docker exec homeQuality bash -c "cd /data/api && php artisan  laravel:ms_cf02 '2023-07-01 00:00:00' '2023-08-14 00:00:00'" >> /data/sh/logs/test/ms_cf02.log

docker exec homeQuality bash -c "cd /data/api && php artisan  laravel:zy_hcmx '2023-07-01 00:00:00' '2023-08-14 00:00:00'" >> /data/sh/logs/test/zy_hcmx.log

docker exec homeQuality bash -c "cd /data/api && php artisan  laravel:zy_sssq '2023-07-01 00:00:00' '2023-08-14 00:00:00'" >> /data/sh/logs/test/sssq.log

docker exec homeQuality bash -c "cd /data/api && php artisan  laravel:ys_zy_hzyj '2023-07-01 00:00:00' '2023-08-14 00:00:00'" >> /data/sh/logs/test/ys_zy_hzyj.log

docker exec homeQuality bash -c "cd /data/api && php artisan  laravel:yj_zy01 '2023-07-01 00:00:00' '2023-08-14 00:00:00'" >> /data/sh/logs/test/yj_zy01.log

docker exec homeQuality bash -c "cd /data/api && php artisan  laravel:emr_bl_basysj '2023-07-01 00:00:00' '2023-08-14 00:00:00'" >> /data/sh/logs/test/emr_bl_basysj.log

docker exec homeQuality bash -c "cd /data/api && php artisan  laravel:omr_bl01 '2023-07-01 00:00:00' '2023-08-14 00:00:00'" >> /data/sh/logs/test/omr_bl01.log

docker exec homeQuality bash -c "cd /data/api && php artisan  laravel:emr_bl_blsy '2023-07-01 00:00:00' '2023-08-14 00:00:00'" >> /data/sh/logs/test/emr_bl_blsy.log

docker exec homeQuality bash -c "cd /data/api && php artisan  laravel:emr_yzb '2023-07-01 00:00:00' '2023-08-14 00:00:00'" >> /data/sh/logs/test/emr_yzb.log

docker exec homeQuality bash -c "cd /data/api && php artisan  laravel:bl01v2 '2023-07-01 00:00:00' '2023-08-14 00:00:00'" >> /data/sh/logs/test/bl01v2.log
             */
        }
        return 0;
    }
}
