<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use Illuminate\Console\Command;

class SSSQ extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:sssq {start} {end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步手术申请';

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
                'name' => 'laravel:sssq',
                'start' => $star,
                'end' => $end,
                'count' => 0,
                'created_at' => now(),
            ];

            $sql = "SELECT SQDH,JGID,ZYH,SSKS,SQKS,SQYS,to_char(SQRQ,'yyyy-mm-dd hh24:mi:ss') as SQRQ,to_char(SSRQ,'yyyy-mm-dd hh24:mi:ss') as SSRQ,SSNM,SSYS,SSYZ,SSSZ,SSEZ,SSYQ,MZDM,MZYS,TJBZ,APBZ,ZFBZ,TXKS,CZGH,SQTL,SQZD,NSSMC,FYBQ,QKDJ,THYY,ZFYY,ZFGH,LRBZ,YXJS,NLTR,HBQTJB,QTTSQK,TSQKNR,SSJB,CRBZ,CRBG,BXBZ,BXSM,BZXX,SSTW,SPBZ,CFSS,ZLXZ,RJSS FROM PORTAL_HIS.SM_SSSQ WHERE SQRQ BETWEEN TO_DATE('{$star}', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('{$end}', 'yyyy-MM-dd HH24:mi:ss')";
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
                    'SQDH' => $item['SQDH'],
                    'JGID' => $item['JGID'],
                    'ZYH' => $item['ZYH'],
                    'SSKS' => $item['SSKS'],
                    'SQKS' => $item['SQKS'],
                    'SQYS' => $item['SQYS'],
                    'SQRQ' => $item['SQRQ'],
                    'SSRQ' => $item['SSRQ'],
                    'SSNM' => $item['SSNM'],
                    'SSYS' => $item['SSYS'],
                    'SSYZ' => $item['SSYZ'],
                    'SSSZ' => $item['SSSZ'],
                    'SSEZ' => $item['SSEZ'],
                    'SSYQ' => $item['SSYQ'],
                    'MZDM' => $item['MZDM'],
                    'MZYS' => $item['MZYS'],
                    'TJBZ' => $item['TJBZ'],
                    'APBZ' => $item['APBZ'],
                    'ZFBZ' => $item['ZFBZ'],
                    'TXKS' => $item['TXKS'],
                    'CZGH' => $item['CZGH'],
                    'SQTL' => $item['SQTL'],
                    'SQZD' => $item['SQZD'],
                    'NSSMC' => $item['NSSMC'],
                    'FYBQ' => $item['FYBQ'],
                    'QKDJ' => $item['QKDJ'],
                    'THYY' => $item['THYY'],
                    'ZFYY' => $item['ZFYY'],
                    'ZFGH' => $item['ZFGH'],
                    'LRBZ' => $item['LRBZ'],
                    'YXJS' => $item['YXJS'],
                    'NLTR' => $item['NLTR'],
                    'HBQTJB' => $item['HBQTJB'],
                    'QTTSQK' => $item['QTTSQK'],
                    'TSQKNR' => $item['TSQKNR'],
                    'SSJB' => $item['SSJB'],
                    'CRBZ' => $item['CRBZ'],
                    'CRBG' => $item['CRBG'],
                    'BXBZ' => $item['BXBZ'],
                    'BXSM' => $item['BXSM'],
                    'BZXX' => $item['BZXX'],
                    'SSTW' => $item['SSTW'],
                    'SPBZ' => $item['SPBZ'],
                    'CFSS' => $item['CFSS'],
                    'ZLXZ' => $item['ZLXZ'],
                    'RJSS' => $item['RJSS'],
                    'AAC01' => now(),
                ];

                \App\Model\SSSQ::query()->updateOrInsert(['SQDH' => $insertData['SQDH']], $insertData);
            }

            //记录日志
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Y-m-d', strtotime($star) + 86400);
        }
        return 0;
    }
}
