<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use Illuminate\Console\Command;

class EMR_BL_BL01_NEW extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:emr_bl_bl01_new {start} {end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '视图增量同步到mysql BL01_new';

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

        while (true){
            echo $star.PHP_EOL;
            if ($star > $end) {
                break;
            }
            $syncRecordData = [
                'name' => 'laravel:emr_bl_bl01_new',
                'start' => $star,
                'end' => $end,
                'count' => 0,
            ];

            $con = oci_connect('zdyh', 'emr#2023', '172.16.9.8:1521/ODS', 'UTF8');
            if (!$con) {
                $e = oci_error();
                print_r(htmlentities($e['message'])) . "\n";
                die;
            }

            $sql = "SELECT BLBH,JZHM,BLLX,BLLB,BLMC,MBLB,MBBH,to_char(CJSJ,'yyyy-mm-dd hh24:mi:ss') as CJSJ,SQDH,DLLB,BLZT,SXYS,to_char(WCSJ,'yyyy-mm-dd hh24:mi:ss') as WCSJ,BRKS,BRBH,BLZM,DLJ,to_char(ZXSJ,'yyyy-mm-dd hh24:mi:ss') as ZXSJ,CJKS,BRXM,BRZD,SSYS,SYBZ,BZMBBH,BLYM,YMJL,to_char(RYZDSJ,'yyyy-mm-dd hh24:mi:ss') as RYZDSJ,PTID,BLZSTJ,JGID,ZDMC,ZDLX,CXPX,SBBZ,WZZT FROM EMR_BL_BL01 WHERE BLLB=2000001 AND ZXSJ BETWEEN TO_DATE('{$star}', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('{$end}', 'yyyy-MM-dd HH24:mi:ss')";
            $result = oci_parse($con, $sql);
            oci_execute($result,OCI_DEFAULT);
            $data = [];
            while ($row = oci_fetch_assoc($result)) {
                $data[] = $row;
            }
            if (empty($data)){
                SyncRecord::query()->insert($syncRecordData);
                break;
            }
            foreach ($data as $item){
                $result = [
                    'BLBH' => $item['BLBH'] ?? '',
                    'JZHM' => $item['ZYH'] ?? '',
                    'BRBH' => $item['BRBH'] ?? '',
                    'BLLX' => $item['BLLX'] ?? '',
                    'BLLB' => $item['BLLB'] ?? '',
                    'BLMC' => $item['BLMC'] ?? '',
                    'BLZM' => $item['BLZM'] ?? '',
                    'DLLB' => $item['DLLB'] ?? '',
                    'DLJ' => $item['DLJ'] ?? '',
                    'MBLB' => $item['MBLB'] ?? '',
                    'MBBH' => $item['MBBH'] ?? '',
                    'ZXSJ' => $item['ZJ'] ?? '',
                    'CJSJ' => $item['CJ'] ?? '',
                    'WCSJ' => $item['WJ'] ?? '',
                    'SXYS' => $item['SXYS'] ?? '',
                    'BRKS' => $item['BRKS'] ?? '',
                    'CJKS' => $item['CJKS'] ?? '',
                    'BLZT' => $item['BLZT'] ?? '',
                    'BRXM' => $item['BRXM'] ?? '',
                    'BRZD' => $item['BRZD'] ?? '',
                    'SSYS' => $item['SSYS'] ?? '',
                    'SYBZ' => $item['SYBZ'] ?? '',
                    'BZMBBH' => $item['BZMBBH'] ?? '',
                    'BLYM' => $item['BLYM'] ?? '',
                    'YMJL' => $item['YMJL'] ?? '',
                    'RYZDSJ' => $item['RYZDSJ'] ?? '',
                    'PTID' => $item['PTID'] ?? '',
                    'BLZSTJ' => $item['BLZSTJ'] ?? '',
                    'JGID' => $item['JGID'] ?? '',
                    'SQDH' => $item['SQDH'] ?? '',
                    'ZDMC' => $item['ZDMC'] ?? '',
                    'ZDLX' => $item['ZDLX'] ?? '',
                    'CXPX' => $item['CXPX'] ?? '',
                    'SBBZ' => $item['SBBZ'] ?? '',
                    'WZZT' => $item['WZZT'] ?? ''
                ];
                \App\Model\EMR_BL_BL01_NEW::query()->updateOrInsert(['JZHM'=>$result['JZHM'],'BLBH'=>$result['BLBH']],$result);
            }

            //记录日志
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Y-m-d',strtotime($star)+86400);
        }
        return 0;
    }
}
