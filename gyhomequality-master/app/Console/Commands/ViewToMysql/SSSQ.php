<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\PatientInfo;
use App\Model\SyncRecord;
use Illuminate\Console\Command;

class SSSQ extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:sssq {start?} {end?}';

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

        if(empty($star)){
            $star = date("Y-m-d H:i:s", time()-9*24*3600);
        }
        if(empty($end)){
            $end = date("Y-m-d H:i:s", time());
        }
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
        echo $star . '-' . $end . PHP_EOL;
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
            exit;
        }

        $dataArr = array_chunk($data, 500);
        foreach ($dataArr as $data) {
            $SSNM = array_column($data, 'SSNM');

            $sql = "SELECT A.SSMC, A.SSDM, A.SSNM  FROM PORTAL_HIS.GY_SSDM A WHERE A.SSNM in (" . implode(',', $SSNM) . ")";
            $datassdm = oci_parse($con, $sql);
            oci_execute($datassdm, OCI_DEFAULT);
            $GY_SSDM = [];
            while ($row = oci_fetch_assoc($datassdm)) {
                $GY_SSDM[] = $row;
            }
            $GY_SSDM = array_column($GY_SSDM, null, 'SSNM');

            $SQDH = array_column($data, 'SQDH');
            $sql = "SELECT A.SQDH, A.SSBH  FROM PORTAL_HIS.SM_SSAP A WHERE A.SQDH in (" . implode(',', $SQDH) . ")";
            $res = oci_parse($con, $sql);
            oci_execute($res, OCI_DEFAULT);
            $SM_SSAP = [];
            while ($row = oci_fetch_assoc($res)) {
                $SM_SSAP[] = $row;
            }
            if($SM_SSAP){
                $sql = "SELECT A.SSBH, A.KSSJ, A.JSSJ  FROM PORTAL_HIS.SM_SSJL A WHERE A.SSBH in (" . implode(',', array_column($SM_SSAP, 'SSBH')) . ")";
                $res = oci_parse($con, $sql);
                oci_execute($res, OCI_DEFAULT);
                $SM_SSJL = [];
                while ($row = oci_fetch_assoc($res)) {
                    $SM_SSJL[] = $row;
                }
                $SM_SSJL = array_column($SM_SSJL, null, 'SSBH');
                foreach ($SM_SSAP as &$v) {
                    $v['KSSJ'] = "";
                    if(!empty($SM_SSJL[$v['SSBH']]) && !empty($SM_SSJL[$v['SSBH']]['KSSJ'])){
                        $v['KSSJ'] = date("Y-m-d H:i:s", strtotime($SM_SSJL[$v['SSBH']]['KSSJ']));
                    }
                    $v['JSSJ'] = "";
                    if(!empty($SM_SSJL[$v['SSBH']]) && !empty($SM_SSJL[$v['SSBH']]['JSSJ'])){
                        $v['JSSJ'] = date("Y-m-d H:i:s", strtotime($SM_SSJL[$v['SSBH']]['JSSJ']));
                    }
                }
                $SM_SSAP = array_column($SM_SSAP, null, 'SQDH');
            }

            foreach ($data as $item) {
                $insertData = [
                    'SSMC' => $GY_SSDM[$item['SSNM']]['SSMC'] ?? '',
                    'SSDM' => $GY_SSDM[$item['SSNM']]['SSDM'] ?? '',
                    'KSSJ' => $SM_SSAP[$item['SQDH']]['KSSJ'] ?? '',
                    'JSSJ' => $SM_SSAP[$item['SQDH']]['JSSJ'] ?? '',
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
                    'updated_at' => now(),
                ];

                \App\Model\SSSQ::query()->updateOrInsert(['SQDH' => $insertData['SQDH']], $insertData);
                PatientInfo::query()->where('MED_REC_ID', $item['ZYH'])->update(['updated_at'=>date("Y-m-d H:i:s")]);
            }
        }


        var_dump("同步完成");
        return 0;
    }
}
