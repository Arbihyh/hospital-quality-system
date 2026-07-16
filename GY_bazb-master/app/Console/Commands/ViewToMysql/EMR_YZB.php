<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use App\Model\YS_ZY_HZYJ;
use App\Model\Yzb;
use Illuminate\Console\Command;

class EMR_YZB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:emr_yzb {start} {end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
                'name' => 'laravel:emr_yzb',
                'start' => $star,
                'end' => $end,
                'count' => 0,
                'created_at' => now(),
            ];

            $sql = "select YZBXH,BRID,ZYH,YEPB,BRKS,BRBQ,BRCH,YDYZLB,XMLB,XMID,XMDJ,YZZH,YZQX,YYSX,KZKS,KZYS,to_char(KZSJ,'yyyy-mm-dd hh24:mi:ss') as KZSJ,YZMC,YPCD,FYSX,SYPC,GYTJ,YCJL,JLDW,ZL,ZLDW,JJYZ,BLYZ,to_char(TZSJ,'yyyy-mm-dd hh24:mi:ss') as TZSJ,TZYS,YZZT,ZXZT,KZDY,ZTBZ,XZJDGH,to_char(XZJDSJ,'yyyy-mm-dd hh24:mi:ss') as XZJDSJ,TZQRGH,to_char(TZQRSJ,'yyyy-mm-dd hh24:mi:ss') as TZQRSJ,to_char(APSJ,'yyyy-mm-dd hh24:mi:ss') as APSJ,YYTS,YSZT,SRCS,SRSD,ZXSD,DS,DSDW,PSBZ,PSJG,ZFPB,YBLX,SPBH,CYJF,PLSX,CZBZ,BZXX,SQDH,ZXKS,YFGG,YFDW,YFBZ,PZPC,ZYBZ,to_char(SFSJ,'yyyy-mm-dd hh24:mi:ss') as SFSJ,YFYY,YFYYYY,YQSY,QXKZ,HOUR24,HOUR36,HOUR241,HOUR48,DAY3,DAY4,DAY5,DAY7,DAY10,REASON24,REASON48,REASON3,REASON4,REASON5,REASON7,REASON10,REASON241,REASONLH,REASONLH3,KJYWLH,YYPS,FZLJ,PASSINDEX,NWARN,QXMC,YZPLZH,LCTS,ZLFY,ZLXZ,YZLX,SQMYZ,SQMHS,to_char(SQMSJ,'yyyy-mm-dd hh24:mi:ss') as SQMSJ,JGID,SSYZ,SMYZXH,KJSSKZ,CDA_PC,YBZFBL,YFYYQK,CYDJ,to_char(ZXSJ,'yyyy-mm-dd hh24:mi:ss') as ZXSJ from PORTAL_HIS.EMR_YZB WHERE KZSJ BETWEEN TO_DATE('{$star}', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('{$end}', 'yyyy-MM-dd HH24:mi:ss')";

            $result = oci_parse($con, $sql);
            oci_execute($result, OCI_DEFAULT);
            $data = [];
            while ($row = oci_fetch_assoc($result)) {
                $data[] = $row;
            }
            if (empty($data)) {
                exit;
            }
            foreach ($data as $item) {
                $insertData = [
                    'YZBXH' => $item['YZBXH'],
                    'ZYH' => $item['ZYH'],
                    'YEPB' => $item['YEPB'],
                    'BRKS' => $item['BRKS'],
                    'BRBQ' => $item['BRBQ'],
                    'BRCH' => $item['BRCH'],
                    'YDYZLB' => $item['YDYZLB'],
                    'XMLB' => $item['XMLB'],
                    'XMID' => $item['XMID'],
                    'XMDJ' => $item['XMDJ'],
                    'YZZH' => $item['YZZH'],
                    'YZQX' => $item['YZQX'],
                    'YYSX' => $item['YYSX'],
                    'KZKS' => $item['KZKS'],
                    'KZYS' => $item['KZYS'],
                    'KZSJ' => $item['KZSJ'],
                    'YZMC' => $item['YZMC'],
                    'YPCD' => $item['YPCD'],
                    'FYSX' => $item['FYSX'],
                    'SYPC' => $item['SYPC'],
                    'GYTJ' => $item['GYTJ'],
                    'YCJL' => $item['YCJL'],
                    'JLDW' => $item['JLDW'],
                    'ZL' => $item['ZL'],
                    'ZLDW' => $item['ZLDW'],
                    'JJYZ' => $item['JJYZ'],
                    'BLYZ' => $item['BLYZ'],
                    'TZSJ' => $item['TZSJ'],
                    'TZYS' => $item['TZYS'],
                    'YZZT' => $item['YZZT'],
                    'ZXZT' => $item['ZXZT'],
                    'KZDY' => $item['KZDY'],
                    'ZTBZ' => $item['ZTBZ'],
                    'XZJDGH' => $item['XZJDGH'],
                    'XZJDSJ' => $item['XZJDSJ'],
                    'TZQRGH' => $item['TZQRGH'],
                    'TZQRSJ' => $item['TZQRSJ'],
                    'APSJ' => $item['APSJ'],
                    'YYTS' => $item['YYTS'],
                    'YSZT' => $item['YSZT'],
                    'SRCS' => $item['SRCS'],
                    'SRSD' => $item['SRSD'],
                    'ZXSD' => $item['ZXSD'],
                    'DS' => $item['DS'],
                    'DSDW' => $item['DSDW'],
                    'PSBZ' => $item['PSBZ'],
                    'PSJG' => $item['PSJG'],
                    'ZFPB' => $item['ZFPB'],
                    'YBLX' => $item['YBLX'],
                    'SPBH' => $item['SPBH'],
                    'CYJF' => $item['CYJF'],
                    'PLSX' => $item['PLSX'],
                    'CZBZ' => $item['CZBZ'],
                    'BZXX' => $item['BZXX'],
                    'SQDH' => $item['SQDH'],
                    'ZXKS' => $item['ZXKS'],
                    'YFGG' => $item['YFGG'],
                    'YFDW' => $item['YFDW'],
                    'YFBZ' => $item['YFBZ'],
                    'SFSJ' => $item['SFSJ'],
                    'YFYY' => $item['YFYY'],
                    'YFYYYY' => $item['YFYYYY'],
                    'QXKZ' => $item['QXKZ'],
                    'YYPS' => $item['YYPS'],
                    'FZLJ' => $item['FZLJ'],
                    'PASSINDEX' => $item['PASSINDEX'],
                    'NWARN' => $item['NWARN'],
                    'QXMC' => $item['QXMC'],
                    'YZPLZH' => $item['YZPLZH'],
                    'LCTS' => $item['LCTS'],
                    'ZLFY' => $item['ZLFY'],
                    'YZLX' => $item['YZLX'],
                    'SSYZ' => $item['SSYZ'],
                    'CDA_PC' => $item['CDA_PC'],
                    'ZXSJ' => $item['ZXSJ']
                ];

                Yzb::query()->updateOrInsert(['YZBXH' => $insertData['YZBXH']], $insertData);
            }

            //记录日志
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Y-m-d', strtotime($star) + 86400);
        }
        return 0;
    }
}
