<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use App\Model\YS_ZY_HZYJ;
use Illuminate\Console\Command;

class OMR_BL01 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:omr_bl01 {start} {end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步 门诊病历';

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
                'name' => 'laravel:omr_bl01',
                'start' => $star,
                'end' => $end,
                'count' => 0,
                'created_at' => now(),
            ];

            $sql = "SELECT BLBH,JZXH,BRID,BLLX,BLLB,BLMC,DLLB,DLJ,MBLB,MBBH,to_char(JLSJ,'yyyy-mm-dd hh24:mi:ss') as JLSJ,to_char(CJSJ,'yyyy-mm-dd hh24:mi:ss') as CJSJ,to_char(WCSJ,'yyyy-mm-dd hh24:mi:ss') as WCSJ,SXYS,SXKS,BRKS,BLZT,SYBZ,BZMBBH,BLBBZ,BLPF,JGID,ZZDY,PTID,BLNR_TXT,SBBZ from PORTAL_HIS.OMR_BL01 WHERE CJSJ BETWEEN TO_DATE('{$star}', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('{$end}', 'yyyy-MM-dd HH24:mi:ss')";
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
                    'BLBH' => $item['BLBH'],
                    'JZXH' => $item['JZXH'],
                    'BRID' => $item['BRID'],
                    'BLLX' => $item['BLLX'],
                    'BLLB' => $item['BLLB'],
                    'BLMC' => $item['BLMC'],
                    'DLLB' => $item['DLLB'],
                    'DLJ' => $item['DLJ'],
                    'MBLB' => $item['MBLB'],
                    'MBBH' => $item['MBBH'],
                    'JLSJ' => $item['JLSJ'],
                    'CJSJ' => $item['CJSJ'],
                    'WCSJ' => $item['WCSJ'],
                    'SXYS' => $item['SXYS'],
                    'SXKS' => $item['SXKS'],
                    'BRKS' => $item['BRKS'],
                    'BLZT' => $item['BLZT'],
                    'SYBZ' => $item['SYBZ'],
                    'BZMBBH' => $item['BZMBBH'],
                    'BLBBZ' => $item['BLBBZ'],
                    'BLPF' => $item['BLPF'],
                    'JGID' => $item['JGID'],
                    'ZZDY' => $item['ZZDY'],
                    'PTID' => $item['PTID'],
                    'BLNR_TXT' => $item['BLNR_TXT'],
                    'SBBZ' => $item['SBBZ'],
                ];

                \App\Model\OMR_BL01::query()->updateOrInsert(['BLBH'=>$insertData['BLBH']],$insertData);
            }

            //记录日志
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Y-m-d',strtotime($star)+86400);
        }
        return 0;
    }
}
