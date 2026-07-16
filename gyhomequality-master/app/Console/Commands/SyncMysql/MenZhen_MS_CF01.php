<?php

namespace App\Console\Commands\SyncMysql;

use App\Model\MS_BRDA;
use Illuminate\Console\Command;

class MenZhen_MS_CF01 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'menzhen:ms_cf01 {startTime?} {endTime?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步门诊病历数据 -- 门诊处方：ms_cf01，处方明细：ms_cf02';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public static $hisCon;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->getHisConnect();
        echo "同步门诊处方：ms_cf01，处方明细：ms_cf02--start:" . date('Y-m-d H:i:s') . "\n";
        $startTime = $this->argument('startTime');
        $endTime = $this->argument('endTime');

        $startTime = empty($startTime) ? date('Y-m-d 00:00:00', strtotime("-1 day", time())) : date('Y-m-d 00:00:00', strtotime($startTime));
        $endTime = empty($endTime) ? date('Y-m-d 23:59:59', strtotime("-1 day", time())) : date('Y-m-d 23:59:59', strtotime($endTime));

        while (true) {
            $startTimeEnd = date('Y-m-d 23:59:59', strtotime($startTime));
            if ($startTime > $endTime) {
                break;
            }
            $this->MS_CF01($startTime, $startTimeEnd); //同步门诊病历数据 -- 门诊处方：ms_cf01，处方明细：ms_cf02
            $startTime = date('Y-m-d 00:00:00', strtotime($startTime) + 86400);
        }
        echo "同步门诊处方：ms_cf01，处方明细：ms_cf02 --end:" . date('Y-m-d H:i:s') . "\n";
        exit();
    }

    public function getHisConnect()
    {
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
        self::$hisCon = $con;
    }


    public function MS_CF01($startTime, $endTime)
    {
        //YDBM,STDBZMC,STDBZBM,PRESCRIPTIONINFOID,SJRXM,SJRDZ,SJRDH,SFGH,BRXZ,CFSM,FYJCK,HSKS,HSZXKS,ZDGL,YQDH,BZXX,PDBZ,CLBZ,YSZLXZ
        $sql = "select CFSB,CFHM,FPHM,MZXH,CFLX,BRID,BRXM,CFTS,KSDM,YSDM,
                to_char(KFRQ,'yyyy-mm-dd hh24:mi:ss') as KFRQ,
                to_char(FYRQ,'yyyy-mm-dd hh24:mi:ss') as FYRQ,
                FYCK,HJGH,PYGH,FYGH,PYBZ,FYBZ,CFGL,ZFPB,DYBZ,YFSB,TSCF,TSLX,TYBZ,CFBZ,JZXH,YXPB,JZKH,DJYBZ,ZFSJ,HDGH,
                HDRQ,DJLY,KJLY,KJLYLY,DMSB,CYJF,JGID,DCHECK,TYSM,YQSB,TAKEWAY
                from PORTAL_HIS.BTF_MS_CF01
                where KFRQ BETWEEN TO_DATE('{$startTime}','yyyy-mm-dd hh24:mi:ss') AND TO_DATE('{$endTime}','yyyy-mm-dd hh24:mi:ss')";
        $result = oci_parse(self::$hisCon, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (!empty($data)) {
            echo $startTime . " - " . count($data) . " - " . date('Y-m-d H:i:s') . "\n";
            foreach ($data as $item) {
                $insertData = [
                    'CFSB'    => $item['CFSB'],
                    'CFHM'    => $item['CFHM'],
                    'FPHM'    => $item['FPHM'],
                    'MZXH'    => $item['MZXH'],
                    'CFLX'    => $item['CFLX'],
                    'BRID'    => $item['BRID'],
                    'BRXM'    => desensitize($item['BRXM'], 1, 1, '*'),
                    'KFRQ'    => $item['KFRQ'],
                    'CFTS'    => $item['CFTS'],
                    'KSDM'    => $item['KSDM'],
                    'YSDM'    => $item['YSDM'],
                    'FYRQ'    => $item['FYRQ'],
                    'FYCK'    => $item['FYCK'],
                    'HJGH'    => $item['HJGH'],
                    'PYGH'    => $item['PYGH'],
                    'FYGH'    => $item['FYGH'],
                    'PYBZ'    => $item['PYBZ'],
                    'FYBZ'    => $item['FYBZ'],
                    'CFGL'    => $item['CFGL'],
                    'ZFPB'    => $item['ZFPB'],
                    'DYBZ'    => $item['DYBZ'],
                    'YFSB'    => $item['YFSB'],
                    'TSCF'    => $item['TSCF'],
                    'TSLX'    => $item['TSLX'],
                    'TYBZ'    => $item['TYBZ'],
                    'CFBZ'    => $item['CFBZ'],
                    'JZXH'    => $item['JZXH'],
                    'YXPB'    => $item['YXPB'],
                    'JZKH'    => $item['JZKH'],
                    'DJYBZ'   => $item['DJYBZ'],
                    'ZFSJ'    => $item['ZFSJ'],
                    'HDGH'    => $item['HDGH'],
                    'HDRQ'    => $item['HDRQ'],
                    'DJLY'    => $item['DJLY'],
                    //                    'HSKS'               => $item['HSKS'],
                    //                    'HSZXKS'             => $item['HSZXKS'],
                    'KJLY'    => $item['KJLY'],
                    'KJLYLY'  => $item['KJLYLY'],
                    //                    'ZDGL'               => $item['ZDGL'],
                    //                    'YQDH'               => $item['YQDH'],
                    'DMSB'    => $item['DMSB'],
                    //                    'BZXX'               => $item['BZXX'],
                    //                    'PDBZ'               => $item['PDBZ'],
                    'CYJF'    => $item['CYJF'],
                    //                    'CLBZ'               => $item['CLBZ'],
                    'JGID'    => $item['JGID'],
                    //                    'YSZLXZ'             => $item['YSZLXZ'],
                    //                    'FYJCK'              => $item['FYJCK'],
                    'DCHECK'  => $item['DCHECK'],
                    //                    'CFSM'               => $item['CFSM'],
                    'TYSM'    => $item['TYSM'],
                    'YQSB'    => $item['YQSB'],
                    //                    'BRXZ'               => $item['BRXZ'],
                    //                    'SFGH'               => $item['SFGH'],
                    'TAKEWAY' => $item['TAKEWAY'],
                    //                    'SJRDZ'              => $item['SJRDZ'],
                    //                    'SJRDH'              => $item['SJRDH'],
                    //                    'SJRXM'              => $item['SJRXM'],
                    //                    'PRESCRIPTIONINFOID' => $item['PRESCRIPTIONINFOID'],
                    //                    'STDBZBM'            => $item['STDBZBM'],
                    //                    'STDBZMC'            => $item['STDBZMC'],
                    //                    'YDBM'               => $item['YDBM'],
                ];
                \App\Model\MS_CF01::query()->updateOrInsert(['CFSB' => $insertData['CFSB']], $insertData);

                $sql = "SELECT SBXH,CFSB,YPXH,YPCD,XMLX,CFTS,YPSL,YPDJ,HJJE,YPZS,YCSL,FYGB,ZFBL,GYTJ,YPYF,YPZH,YFGG,YFDW,YFBZ,
                SJYL,PSPB,YYTS,YCSL2,XSSL,MRCS,CFBZ,YCJL,PSJG,PLXH,SYBZ,SL,CSBZ,JSHID,SQYS,SYLY,YQSY,CLBZ,NWARN,JGID,
                ZTMC,SFJG,SFJY,SFYJ,YBZFBL,SFGH,TYPH,BSSL,BSPB,
                to_char(BSSJ,'yyyy-mm-dd hh24:mi:ss') as BSSJ
                FROM PORTAL_HIS.BTF_MS_CF02 WHERE CFSB = '" . $insertData['CFSB'] . "'";
                $result = oci_parse(self::$hisCon, $sql);
                oci_execute($result, OCI_DEFAULT);
                $data2 = [];
                while ($row = oci_fetch_assoc($result)) {
                    $data2[] = $row;
                }
                if (!empty($data2)) {
                    foreach ($data2 as $item2) {
                        $insertData2 = [
                            'SBXH'   => $item2['SBXH'],
                            'CFSB'   => $item2['CFSB'],
                            'YPXH'   => $item2['YPXH'],
                            'YPCD'   => $item2['YPCD'],
                            'XMLX'   => $item2['XMLX'],
                            'CFTS'   => $item2['CFTS'],
                            'YPSL'   => $item2['YPSL'],
                            'YPDJ'   => $item2['YPDJ'],
                            'HJJE'   => $item2['HJJE'],
                            'YPZS'   => $item2['YPZS'],
                            'YCSL'   => $item2['YCSL'],
                            'FYGB'   => $item2['FYGB'],
                            'ZFBL'   => $item2['ZFBL'],
                            'GYTJ'   => $item2['GYTJ'],
                            'YPYF'   => $item2['YPYF'],
                            'YPZH'   => $item2['YPZH'],
                            'YFGG'   => $item2['YFGG'],
                            'YFDW'   => $item2['YFDW'],
                            'YFBZ'   => $item2['YFBZ'],
                            'SJYL'   => $item2['SJYL'],
                            'PSPB'   => $item2['PSPB'],
                            'YYTS'   => $item2['YYTS'],
                            'YCSL2'  => $item2['YCSL2'],
                            'XSSL'   => $item2['XSSL'],
                            'MRCS'   => $item2['MRCS'],
                            'CFBZ'   => $item2['CFBZ'],
                            'YCJL'   => $item2['YCJL'],
                            'PSJG'   => $item2['PSJG'],
                            'PLXH'   => $item2['PLXH'],
                            'SYBZ'   => $item2['SYBZ'],
                            'SL'     => $item2['SL'],
                            'CSBZ'   => $item2['CSBZ'],
                            'JSHID'  => $item2['JSHID'],
                            'SQYS'   => $item2['SQYS'],
                            'SYLY'   => $item2['SYLY'],
                            'YQSY'   => $item2['YQSY'],
                            'CLBZ'   => $item2['CLBZ'],
                            'NWARN'  => $item2['NWARN'],
                            'JGID'   => $item2['JGID'],
                            'ZTMC'   => $item2['ZTMC'],
                            'SFJG'   => $item2['SFJG'],
                            'SFJY'   => $item2['SFJY'],
                            'SFYJ'   => $item2['SFYJ'],
                            'YBZFBL' => $item2['YBZFBL'],
                            'SFGH'   => $item2['SFGH'],
                            'TYPH'   => $item2['TYPH'],
                            'BSSJ'   => $item2['BSSJ'],
                            'BSSL'   => $item2['BSSL'],
                            'BSPB'   => $item2['BSPB'],
                        ];

                        \App\Model\MS_CF02::query()->updateOrInsert(['SBXH' => $insertData2['SBXH']], $insertData2);
                    }
                }


            }
        }


    }
}
