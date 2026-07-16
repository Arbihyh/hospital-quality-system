<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use Illuminate\Console\Command;

class MS_CF01 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:ms_cf01 {start} {end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步 门诊处方';

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
                'name' => 'laravel:ms_cf01',
                'start' => $star,
                'end' => $end,
                'count' => 0,
                'created_at' => now(),
            ];

            $sql = "select CFSB,CFHM,FPHM,MZXH,CFLX,BRID,BRXM,to_char(KFRQ,'yyyy-mm-dd hh24:mi:ss') as KFRQ,CFTS,KSDM,YSDM,to_char(FYRQ,'yyyy-mm-dd hh24:mi:ss') as FYRQ,FYCK,HJGH,PYGH,FYGH,PYBZ,FYBZ,CFGL,ZFPB,DYBZ,YFSB,TSCF,TSLX,TYBZ,CFBZ,JZXH,YXPB,JZKH,DJYBZ,ZFSJ,HDGH,HDRQ,DJLY,HSKS,HSZXKS,KJLY,KJLYLY,ZDGL,YQDH,DMSB,BZXX,PDBZ,CYJF,CLBZ,JGID,YSZLXZ,FYJCK,DCHECK,CFSM,TYSM,YQSB,BRXZ,SFGH,TAKEWAY,SJRDZ,SJRDH,SJRXM,PRESCRIPTIONINFOID,STDBZBM,STDBZMC,YDBM from PORTAL_HIS.MS_CF01 WHERE KFRQ BETWEEN TO_DATE('{$star}', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('{$end}', 'yyyy-MM-dd HH24:mi:ss')";
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
                    'CFSB' => $item['CFSB'],
                    'CFHM' => $item['CFHM'],
                    'FPHM' => $item['FPHM'],
                    'MZXH' => $item['MZXH'],
                    'CFLX' => $item['CFLX'],
                    'BRID' => $item['BRID'],
                    'BRXM' => desensitize($item['BRXM'],1,1,'*'),
                    'KFRQ' => $item['KFRQ'],
                    'CFTS' => $item['CFTS'],
                    'KSDM' => $item['KSDM'],
                    'YSDM' => $item['YSDM'],
                    'FYRQ' => $item['FYRQ'],
                    'FYCK' => $item['FYCK'],
                    'HJGH' => $item['HJGH'],
                    'PYGH' => $item['PYGH'],
                    'FYGH' => $item['FYGH'],
                    'PYBZ' => $item['PYBZ'],
                    'FYBZ' => $item['FYBZ'],
                    'CFGL' => $item['CFGL'],
                    'ZFPB' => $item['ZFPB'],
                    'DYBZ' => $item['DYBZ'],
                    'YFSB' => $item['YFSB'],
                    'TSCF' => $item['TSCF'],
                    'TSLX' => $item['TSLX'],
                    'TYBZ' => $item['TYBZ'],
                    'CFBZ' => $item['CFBZ'],
                    'JZXH' => $item['JZXH'],
                    'YXPB' => $item['YXPB'],
                    'JZKH' => $item['JZKH'],
                    'DJYBZ' => $item['DJYBZ'],
                    'ZFSJ' => $item['ZFSJ'],
                    'HDGH' => $item['HDGH'],
                    'HDRQ' => $item['HDRQ'],
                    'DJLY' => $item['DJLY'],
                    'HSKS' => $item['HSKS'],
                    'HSZXKS' => $item['HSZXKS'],
                    'KJLY' => $item['KJLY'],
                    'KJLYLY' => $item['KJLYLY'],
                    'ZDGL' => $item['ZDGL'],
                    'YQDH' => $item['YQDH'],
                    'DMSB' => $item['DMSB'],
                    'BZXX' => $item['BZXX'],
                    'PDBZ' => $item['PDBZ'],
                    'CYJF' => $item['CYJF'],
                    'CLBZ' => $item['CLBZ'],
                    'JGID' => $item['JGID'],
                    'YSZLXZ' => $item['YSZLXZ'],
                    'FYJCK' => $item['FYJCK'],
                    'DCHECK' => $item['DCHECK'],
                    'CFSM' => $item['CFSM'],
                    'TYSM' => $item['TYSM'],
                    'YQSB' => $item['YQSB'],
                    'BRXZ' => $item['BRXZ'],
                    'SFGH' => $item['SFGH'],
                    'TAKEWAY' => $item['TAKEWAY'],
                    'SJRDZ' => $item['SJRDZ'],
                    'SJRDH' => $item['SJRDH'],
                    'SJRXM' => $item['SJRXM'],
                    'PRESCRIPTIONINFOID' => $item['PRESCRIPTIONINFOID'],
                    'STDBZBM' => $item['STDBZBM'],
                    'STDBZMC' => $item['STDBZMC'],
                    'YDBM' => $item['YDBM'],
                ];

                \App\Model\MS_CF01::query()->updateOrInsert(['CFSB' => $insertData['CFSB']], $insertData);
            }

            //记录日志
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Y-m-d', strtotime($star) + 86400);
        }
        return 0;
    }
}
