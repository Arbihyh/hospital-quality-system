<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\PatientInfo;
use App\Model\SyncRecord;
use App\Model\Yzb;
use Illuminate\Console\Command;

class EMR_YZB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:emr_yzb {start?} {end?}';

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

        if (empty($star)) {
            $star = date("Y-m-d H:i:s", time()-9*24*3600);
        }
        if (empty($end)) {
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
        while ($item = oci_fetch_assoc($result)) {
            $insertData = [
                'YZBXH' => $item['YZBXH'] ?: 0,
                'ZYH' => $item['ZYH'] ?: 0,
                'YEPB' => $item['YEPB'] ?: 0,
                'BRKS' => $item['BRKS'] ?: 0,
                'BRBQ' => $item['BRBQ'] ?: 0,
                'BRCH' => $item['BRCH'] ?: 0,
                'YDYZLB' => $item['YDYZLB'] ?: 0,
                'XMLB' => $item['XMLB'] ?: 0,
                'XMID' => $item['XMID'] ?: 0,
                'XMDJ' => $item['XMDJ'] ?: 0,
                'YZZH' => $item['YZZH'] ?: 0,
                'YZQX' => $item['YZQX'] ?: 0,
                'YYSX' => $item['YYSX'] ?: 0,
                'KZKS' => $item['KZKS'] ?: 0,
                'KZYS' => $item['KZYS'] ?: 0,
                'KZSJ' => $item['KZSJ'] ?: 0,
                'YZMC' => $item['YZMC'] ?: 0,
                'YPCD' => $item['YPCD'] ?: 0,
                'FYSX' => $item['FYSX'] ?: 0,
                'SYPC' => $item['SYPC'] ?: 0,
                'GYTJ' => $item['GYTJ'] ?: 0,
                'YCJL' => $item['YCJL'] ?: 0,
                'JLDW' => $item['JLDW'] ?: 0,
                'ZL' => $item['ZL'] ?: 0,
                'ZLDW' => $item['ZLDW'] ?: 0,
                'JJYZ' => $item['JJYZ'] ?: 0,
                'BLYZ' => $item['BLYZ'] ?: 0,
                'TZSJ' => $item['TZSJ'] ?: 0,
                'TZYS' => $item['TZYS'] ?: 0,
                'YZZT' => $item['YZZT'] ?: 0,
                'ZXZT' => $item['ZXZT'] ?: 0,
                'KZDY' => $item['KZDY'] ?: 0,
                'ZTBZ' => $item['ZTBZ'] ?: 0,
                'XZJDGH' => $item['XZJDGH'] ?: 0,
                'XZJDSJ' => $item['XZJDSJ'] ?: 0,
                'TZQRGH' => $item['TZQRGH'] ?: 0,
                'TZQRSJ' => $item['TZQRSJ'] ?: 0,
                'APSJ' => $item['APSJ'] ?: 0,
                'YYTS' => $item['YYTS'] ?: 0,
                'YSZT' => $item['YSZT'] ?: 0,
                'SRCS' => $item['SRCS'] ?: 0,
                'SRSD' => $item['SRSD'] ?: 0,
                'ZXSD' => $item['ZXSD'] ?: 0,
                'DS' => $item['DS'] ?: 0,
                'DSDW' => $item['DSDW'] ?: 0,
                'PSBZ' => $item['PSBZ'] ?: 0,
                'PSJG' => $item['PSJG'] ?: 0,
                'ZFPB' => $item['ZFPB'] ?: 0,
                'YBLX' => $item['YBLX'] ?: 0,
                'SPBH' => $item['SPBH'] ?: 0,
                'CYJF' => $item['CYJF'] ?: 0,
                'PLSX' => $item['PLSX'] ?: 0,
                'CZBZ' => $item['CZBZ'] ?: 0,
                'BZXX' => $item['BZXX'] ?: 0,
                'SQDH' => $item['SQDH'] ?: 0,
                'ZXKS' => $item['ZXKS'] ?: 0,
                'YFGG' => $item['YFGG'] ?: 0,
                'YFDW' => $item['YFDW'] ?: 0,
                'YFBZ' => $item['YFBZ'] ?: 0,
                'SFSJ' => $item['SFSJ'] ?: 0,
                'YFYY' => $item['YFYY'] ?: 0,
                'YFYYYY' => $item['YFYYYY'] ?: 0,
                'QXKZ' => $item['QXKZ'] ?: 0,
                'YYPS' => $item['YYPS'] ?: 0,
                'FZLJ' => $item['FZLJ'] ?: 0,
                'PASSINDEX' => $item['PASSINDEX'] ?: 0,
                'NWARN' => $item['NWARN'] ?: 0,
                'QXMC' => $item['QXMC'] ?: 0,
                'YZPLZH' => $item['YZPLZH'] ?: 0,
                'LCTS' => $item['LCTS'] ?: 0,
                'ZLFY' => $item['ZLFY'] ?: 0,
                'YZLX' => $item['YZLX'] ?: 0,
                'SSYZ' => $item['SSYZ'] ?: 0,
                'CDA_PC' => $item['CDA_PC'] ?: 0,
                'ZXSJ' => $item['ZXSJ'] ?: 0,
            ];

            Yzb::query()->updateOrInsert(['YZBXH' => $insertData['YZBXH']], $insertData);
            PatientInfo::query()->where('MED_REC_ID', $item['ZYH'])->update(['updated_at' => date("Y-m-d H:i:s")]);
        }
        if (empty($data)) {
            var_dump("未同步到数据");
            exit;
        }
        //记录日志
        $syncRecordData['count'] = count($data);
        SyncRecord::query()->insert($syncRecordData);
        var_dump("同步完成");
        return 0;
    }
}
