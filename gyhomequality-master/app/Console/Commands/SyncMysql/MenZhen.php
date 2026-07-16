<?php

namespace App\Console\Commands\SyncMysql;

use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Model\FeeDetailed;
use App\Model\Icu;
use App\Model\MainDiagnosis;
use App\Model\MainOperation;
use App\Model\MS_BRDA;
use App\Model\OtherDiagnosis;
use App\Model\PatientAdd;
use App\Model\PatientAddressInfo;
use App\Model\PatientContactsInfo;
use App\Model\PatientCostInfo;
use App\Model\PatientDoctorInfo;
use App\Model\PatientHospitalInfo;
use App\Model\PatientInfo;
use App\Model\PatientMedicalInfo;
use App\Model\PatientOtherInfo;
use App\Model\PatientWorkInfo;
use App\Model\SecondaryOperation;
use App\Model\SSSQ;
use App\Model\SyncRecord;
use App\Model\YS_ZY_HZYJ;
use App\Model\Yzb;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Model\OMR_BLSY;

class MenZhen extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:menzhen {startTime?} {endTime?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 门诊数据同步';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public static $con;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
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
        self::$con = $con;

        $index = 0;
        $maxBRID = 0;
        while (true) {
            // 如果数据获取已经是最后的数据则查询oracle数据库中的数据是否有更新，如果没有，则重新开始同步
            if ($index > $maxBRID) {
                $sql = 'SELECT MAX(BRID) as BRID FROM PORTAL_HIS.MS_BRDA';
                $result = oci_parse(self::$con, $sql);
                oci_execute($result, OCI_DEFAULT);
                $row = oci_fetch_assoc($result);
                $maxBRID = $row['BRID'];
            }

            // 如果数据同步到最新时间，则重新从2022年开始同步
            if ($index >= $maxBRID) {
                echo date("Y-m-d H:i:s", time()) . "完成一轮数据同步\n";
                exit;
            }

            $endIndex = $index + 100;
            $sql = "SELECT BRID,MZHM,BRXM,FYZH,SFZH,BRXZ,BRXB FROM PORTAL_HIS.MS_BRDA WHERE BRID >{$index} and BRID < {$endIndex}";
            $result = oci_parse(self::$con, $sql);
            oci_execute($result, OCI_DEFAULT);
            $data = [];
            while ($row = oci_fetch_assoc($result)) {
                $data[] = $row;
            }
            if (empty($data)) {
                continue;
            }

            $BRID = [];
            foreach ($data as $item) {
                $insertData = [
                    'BRID' => $item['BRID'],
                    'MZHM' => $item['MZHM'],
                    'BRXM' => desensitize($item['BRXM'], 1, 1, '*'),
                    'FYZH' => $item['FYZH'],
                    'SFZH' => $item['SFZH'],
                    'BRXZ' => $item['BRXZ'],
                    'BRXB' => $item['BRXB'],
                ];
                $BRID[] = $insertData['BRID'];
                echo $insertData['BRID'] .' - '. date('Y-m-d H:i:s', time()) . PHP_EOL;
                \App\Model\MS_BRDA::query()->updateOrInsert(['BRID' => $insertData['BRID']], $insertData);
            }
            $this->MS_GHMX($BRID);
            $this->OMR_BL01($BRID);
            $this->MS_CF01($BRID);

            $index = $endIndex;

        }
    }

    public function MS_CF01($BRID)
    {
        $BRIDstr = implode(',', $BRID);

        $sql = "select CFSB,CFHM,FPHM,MZXH,CFLX,BRID,BRXM,to_char(KFRQ,'yyyy-mm-dd hh24:mi:ss') as KFRQ,CFTS,KSDM,YSDM,to_char(FYRQ,'yyyy-mm-dd hh24:mi:ss') as FYRQ,FYCK,HJGH,PYGH,FYGH,PYBZ,FYBZ,CFGL,ZFPB,DYBZ,YFSB,TSCF,TSLX,TYBZ,CFBZ,JZXH,YXPB,JZKH,DJYBZ,ZFSJ,HDGH,HDRQ,DJLY,HSKS,HSZXKS,KJLY,KJLYLY,ZDGL,YQDH,DMSB,BZXX,PDBZ,CYJF,CLBZ,JGID,YSZLXZ,FYJCK,DCHECK,CFSM,TYSM,YQSB,BRXZ,SFGH,TAKEWAY,SJRDZ,SJRDH,SJRXM,PRESCRIPTIONINFOID,STDBZBM,STDBZMC,YDBM from PORTAL_HIS.MS_CF01 where BRID in ({$BRIDstr})";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }

        $CFSB = [];
        foreach ($data as $item) {
            $insertData = [
                'CFSB' => $item['CFSB'],
                'CFHM' => $item['CFHM'],
                'FPHM' => $item['FPHM'],
                'MZXH' => $item['MZXH'],
                'CFLX' => $item['CFLX'],
                'BRID' => $item['BRID'],
                'BRXM' => desensitize($item['BRXM'], 1, 1, '*'),
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
            $CFSB[] = $insertData['CFSB'];

            \App\Model\MS_CF01::query()->updateOrInsert(['CFSB' => $insertData['CFSB']], $insertData);
        }

        $CFSBstr = implode(',', $CFSB);
        $sql = "SELECT SBXH,CFSB,YPXH,YPCD,XMLX,CFTS,YPSL,YPDJ,HJJE,YPZS,YCSL,FYGB,ZFBL,GYTJ,YPYF,YPZH,YFGG,YFDW,YFBZ,SJYL,PSPB,YYTS,YCSL2,XSSL,MRCS,CFBZ,YCJL,PSJG,PLXH,SYBZ,SL,CSBZ,JSHID,SQYS,SYLY,YQSY,CLBZ,NWARN,JGID,ZTMC,SFJG,SFJY,SFYJ,YBZFBL,SFGH,TYPH,to_char(BSSJ,'yyyy-mm-dd hh24:mi:ss') as BSSJ,BSSL,BSPB FROM PORTAL_HIS.MS_CF02 WHERE CFSB in ({$CFSBstr})";

        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        foreach ($data as $item) {
            $insertData = [
                'SBXH' => $item['SBXH'],
                'CFSB' => $item['CFSB'],
                'YPXH' => $item['YPXH'],
                'YPCD' => $item['YPCD'],
                'XMLX' => $item['XMLX'],
                'CFTS' => $item['CFTS'],
                'YPSL' => $item['YPSL'],
                'YPDJ' => $item['YPDJ'],
                'HJJE' => $item['HJJE'],
                'YPZS' => $item['YPZS'],
                'YCSL' => $item['YCSL'],
                'FYGB' => $item['FYGB'],
                'ZFBL' => $item['ZFBL'],
                'GYTJ' => $item['GYTJ'],
                'YPYF' => $item['YPYF'],
                'YPZH' => $item['YPZH'],
                'YFGG' => $item['YFGG'],
                'YFDW' => $item['YFDW'],
                'YFBZ' => $item['YFBZ'],
                'SJYL' => $item['SJYL'],
                'PSPB' => $item['PSPB'],
                'YYTS' => $item['YYTS'],
                'YCSL2' => $item['YCSL2'],
                'XSSL' => $item['XSSL'],
                'MRCS' => $item['MRCS'],
                'CFBZ' => $item['CFBZ'],
                'YCJL' => $item['YCJL'],
                'PSJG' => $item['PSJG'],
                'PLXH' => $item['PLXH'],
                'SYBZ' => $item['SYBZ'],
                'SL' => $item['SL'],
                'CSBZ' => $item['CSBZ'],
                'JSHID' => $item['JSHID'],
                'SQYS' => $item['SQYS'],
                'SYLY' => $item['SYLY'],
                'YQSY' => $item['YQSY'],
                'CLBZ' => $item['CLBZ'],
                'NWARN' => $item['NWARN'],
                'JGID' => $item['JGID'],
                'ZTMC' => $item['ZTMC'],
                'SFJG' => $item['SFJG'],
                'SFJY' => $item['SFJY'],
                'SFYJ' => $item['SFYJ'],
                'YBZFBL' => $item['YBZFBL'],
                'SFGH' => $item['SFGH'],
                'TYPH' => $item['TYPH'],
                'BSSJ' => $item['BSSJ'],
                'BSSL' => $item['BSSL'],
                'BSPB' => $item['BSPB'],
            ];

            \App\Model\MS_CF02::query()->updateOrInsert(['SBXH' => $insertData['SBXH']], $insertData);
        }

    }

    public function OMR_BL01($BRID)
    {
        $BRIDstr = implode(',', $BRID);
        $sql = "SELECT BLBH,JZXH,BRID,BLLX,BLLB,BLMC,DLLB,DLJ,MBLB,MBBH,to_char(JLSJ,'yyyy-mm-dd hh24:mi:ss') as JLSJ,to_char(CJSJ,'yyyy-mm-dd hh24:mi:ss') as CJSJ,to_char(WCSJ,'yyyy-mm-dd hh24:mi:ss') as WCSJ,SXYS,SXKS,BRKS,BLZT,SYBZ,BZMBBH,BLBBZ,BLPF,JGID,ZZDY,PTID,BLNR_TXT,SBBZ from PORTAL_HIS.OMR_BL01 WHERE BRID in ({$BRIDstr})";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }
        $BLBH = [];
        $JZXH = [];
        foreach ($data as $item) {
            $str = blobToStr($item['BLNR_TXT']);
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
                'BLNR_TXT' => $str,
                'SBBZ' => $item['SBBZ'],
            ];
            $BLBH[] = $insertData['BLBH'];
            $JZXH[] = $insertData['JZXH'];
            $formatRes = $this->dateFormatBl01($insertData);
            $insertData = array_merge($insertData, $formatRes);

            \App\Model\OMR_BL01::query()->updateOrInsert(['BLBH' => $insertData['BLBH']], $insertData);
        }

        $BLBHstr = implode(',', $BLBH);
        $sql = "SELECT a.*,to_char(a.SYSJ,'yyyy-mm-dd hh24:mi:ss') as SYSJ,to_char(a.JLSJ,'yyyy-mm-dd hh24:mi:ss') as JLSJ FROM PORTAL_HIS.OMR_BLSY a WHERE a.BLBH in ({$BLBHstr})";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }
        foreach ($data as $item) {
            $insertData = [
                'JLXH' => $item['JLXH'],
                'BLBH' => $item['BLBH'],
                'SYQX' => $item['SYQX'],
                'SYYS' => $item['SYYS'],
                'SYSJ' => $item['SYSJ'],
                'JLSJ' => $item['JLSJ'],
                'BZXX' => $item['BZXX'],
                'QXJB' => $item['QXJB'],
                'QMLX' => $item['QMLX'],
                'QMLSH' => $item['QMLSH'],
                'DLLJ' => $item['DLLJ'],
                'QMYS' => $item['QMYS'],
                'YSMRZ' => $item['YSMRZ'],
                'QMYSZ' => $item['QMYSZ'],
                'JGID' => $item['JGID'],
            ];
            OMR_BLSY::query()->updateOrInsert(['JLXH' => $insertData['JLXH']], $insertData);
        }

        // 就诊时间同步
        $JZXHStr = implode(',', $JZXH);
        $sql = "SELECT a.*,to_char(a.VISITDATETIME,'yyyy-mm-dd hh24:mi:ss') as VISITDATETIME FROM PORTAL_HIS.V_CDR_5201 a WHERE VISITID in ({$JZXHStr})";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }
        foreach ($data as $item) {
            $insertData = [
                'jzsj' => $item['VISITDATETIME'],
            ];

            \APP\Model\OMR_BL01::query()->updateOrInsert(['JZXH' => $item['VISITID']], $insertData);
        }

    }

    /**
     * @param $zyh
     * @return bool
     * 门诊挂号明细
     */
    public function MS_GHMX($BRID)
    {
        $BRIDstr = implode(',', $BRID);
        $sql = "SELECT SBXH,BRID,BRXZ,to_char(GHSJ,'yyyy-mm-dd hh24:mi:ss') as GHSJ,GHLB,KSDM,YSDM,JZYS,JZXH,GHCS,ZHLB,JZJS,to_char(JZRQ,'yyyy-mm-dd hh24:mi:ss') AS JZRQ,to_char(HZRQ,'yyyy-mm-dd hh24:mi:ss') AS HZRQ,JZHM,JZZT FROM PORTAL_HIS.MS_GHMX WHERE BRID in ($BRIDstr)";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }

        $SBXH = [];
        foreach ($data as $item) {
            $insertData = [
                'SBXH' => $item['SBXH'],
                'BRID' => $item['BRID'],
                'BRXZ' => $item['BRXZ'],
                'GHSJ' => $item['GHSJ'],
                'GHLB' => $item['GHLB'],
                'KSDM' => $item['KSDM'],
                'YSDM' => $item['YSDM'],
                'JZYS' => $item['JZYS'],
                'JZXH' => $item['JZXH'],
                'GHCS' => $item['GHCS'],
                'ZHLB' => $item['ZHLB'],
                'JZJS' => $item['JZJS'],
                'JZRQ' => $item['JZRQ'],
                'HZRQ' => $item['HZRQ'],
                'JZHM' => $item['JZHM'],
                'JZZT' => $item['JZZT'],
            ];
            $SBXH[] = $insertData['SBXH'];
            \App\Model\MS_GHMX::query()->updateOrInsert(['SBXH' => $insertData['SBXH']], $insertData);
        }

        $SBXHstr = implode(',', $SBXH);
        $sql = "SELECT SBXH,CZGH,to_char(JZRQ,'yyyy-mm-dd hh24:mi:ss') as JZRQ,MZLB,to_char(HZRQ,'yyyy-mm-dd hh24:mi:ss') as HZRQ,to_char(THRQ,'yyyy-mm-dd hh24:mi:ss') as THRQ,JGID,TCKF,TBLF FROM PORTAL_HIS.MS_THMX WHERE SBXH in ({$SBXHstr})";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }

        $SBXH = [];
        foreach ($data as $item) {
            $insertData = [
                'SBXH' => $item['SBXH'],
                'CZGH' => $item['CZGH'],
                'JZRQ' => $item['JZRQ'],
                'MZLB' => $item['MZLB'],
                'HZRQ' => $item['HZRQ'],
                'THRQ' => $item['THRQ'],
                'JGID' => $item['JGID'],
                'TCKF' => $item['TCKF'],
                'TBLF' => $item['TBLF'],
            ];
            $SBXH[] = $insertData['SBXH'];

            \App\Model\MS_THMX::query()->updateOrInsert(['SBXH' => $insertData['SBXH']], $insertData);
        }

    }

    public function dateFormatBl01($value = [])
    {
        if (empty($value)) {
            return [];
        }
        // 就诊时间
        $array = explode("门诊号：", $value['BLNR_TXT']);
        if (!empty($array[1])) {
            $arr = explode('就诊时间：', $array[1]);
            if (!empty($arr[1])) {
                $arr = explode("\n", $arr[1]);
                $jzsj = $arr[0];
                if ($jzsj) {
                    $jzsj = str_replace('年', '-', $jzsj);
                    $jzsj = str_replace('月', '-', $jzsj);
                    $jzsj = str_replace('日', ' ', $jzsj);
                    $jzsj = str_replace('时', ':', $jzsj);
                    $jzsj = str_replace('分', ':00', $jzsj);
                }
                $saveData['jzsj'] = date('Y-m-d H:i:s', strtotime(trim($jzsj)));
            }
        }

        // 西药
        $arr = explode('西药：', str_replace(':', '：', $value['BLNR_TXT']));
        if (!empty($arr[1])) {
            if (stripos($arr[1], "※提醒：") !== false) {
                $arr = explode("※提醒：", $arr[1]);
            } else {
                $arr = explode("滨州医学院烟台附属医院", $arr[1]);
            }

            $saveData['xy'] = trim($arr[0]);
            $arr = explode("\n", trim($arr[0]));
            $xyList = [];
            foreach ($arr as $val) {
                $xy = explode(" ", $val);
                if ($xy[0] == '第' || $xy[0] == '滨州医学院烟台附属医院') {
                    break;
                }
                if (!empty($xy[0]) && $xy[0] != '草药：') {
                    $xyList[] = [
                        'ym' => $xy[0] ?? '',
                        'yl' => $xy[1] ?? '',
                        'yf' => $xy[3] ?? '',
                        'pc' => $xy[2] ?? '',
                    ];
                }
            }
            if ($xyList) {
                $saveData['xy_json'] = json_encode($xyList, JSON_UNESCAPED_UNICODE);
            }
        }

        $BLNR_TXT = str_replace(' ', '', $value['BLNR_TXT']);
        $BLNR_TXT = str_replace(':', '：', $BLNR_TXT);
        $BLNR_TXT = str_replace('辅助检查结果：', '辅助检查：', $BLNR_TXT);
        $BLNR_TXT = str_replace('诊断：', '初步诊断：', $BLNR_TXT);
        $BLNR_TXT = str_replace('处理意见：', '诊疗意见：', $BLNR_TXT);

        // 解析主诉
        $arr = explode('主诉：', $BLNR_TXT);
        if (!empty($arr[1])) {
            $arr = explode("\n", $arr[1]);
            $saveData['zs'] = trim($arr[0]);
        }

        // 现病史
        $arr = explode('现病史：', $BLNR_TXT);
        if (!empty($arr[1])) {
            $arr = explode("\n", $arr[1]);
            $saveData['xbs'] = trim($arr[0]);
        }

        // 既往史
        $arr = explode('既往史：', $BLNR_TXT);
        if (!empty($arr[1])) {
            $arr = explode("\n", $arr[1]);
            $saveData['jws'] = trim($arr[0]);
        }

        // 流行病学史
        $arr = explode('流行病学史：', $BLNR_TXT);
        if (!empty($arr[1])) {
            $arr = explode("\n", $arr[1]);
            $saveData['lxbxs'] = trim($arr[0]);
        }

        // 体格检查
        $arr = explode('体格检查：', $BLNR_TXT);
        if (!empty($arr[1])) {
            $arr = explode("\n", $arr[1]);
            $saveData['tgjc'] = trim($arr[0]);
        }

        // 辅助检查
        $arr = explode('辅助检查：', $BLNR_TXT);
        if (!empty($arr[1])) {
            $arr = explode("\n", $arr[1]);
            $saveData['fzjc'] = trim($arr[0]);
        }

        // 初步诊断
        $arr = explode('初步诊断：', $BLNR_TXT);
        if (!empty($arr[1])) {
            $arr = explode("\n", $arr[1]);
            $saveData['cbzd'] = trim($arr[0]);
        }

        // 诊疗意见
        $arr = explode('诊疗意见：', $BLNR_TXT);
        if (!empty($arr[1])) {
            if (stripos($BLNR_TXT, '西药：') === false) {
                $arr = explode("※提醒：", $arr[1]);
                $saveData['zlyj'] = trim(str_replace('医师签名：', '', $arr[0]));
            } else {
                $arr = explode("西药：", $arr[1]);
                $saveData['zlyj'] = trim(str_replace('医师签名：', '', $arr[0]));
            }
        }

        // 提醒
        $arr = explode('※提醒：', $BLNR_TXT);
        if (!empty($arr[1])) {
            $arr = explode("※", $arr[1]);
            $saveData['tx'] = trim($arr[0]);
        }

        // 门诊号
        $arr = explode('门诊号：', $BLNR_TXT);
        if (!empty($arr[1])) {
            $arr = explode("\n", $arr[1]);
            $saveData['mzh'] = trim($arr[0]);
        }

        // 姓名
        $arr = explode('姓名：', $BLNR_TXT);
        if (!empty($arr[1])) {
            $arr = explode("\n", $arr[1]);
            $saveData['xm'] = !empty(trim($arr[0])) ? desensitize(trim($arr[0]), 1, 1, '*') : '';
        }

        // 性别
        $arr = explode('性别：', $BLNR_TXT);
        if (!empty($arr[1])) {
            $arr = explode("\n", $arr[1]);
            $saveData['xb'] = trim($arr[0]);
        }

        // 科室
        $arr = explode('科室：', $BLNR_TXT);
        if (!empty($arr[1])) {
            $arr = explode("\n", $arr[1]);
            $saveData['ks'] = trim($arr[0]);
        }

        // 年龄
        $arr = explode('年龄：', $BLNR_TXT);
        if (!empty($arr[1])) {
            $saveData['nl1'] = trim($arr[1]);
            if (stripos($arr[1], '岁') !== false) {
                $arr = explode('岁', trim($arr[1]));
                $saveData['nl'] = trim($arr[0]);
            } else {
                $saveData['nl'] = 0;
            }
        }

        // 初诊、复诊、急诊
        $saveData['bl_type'] = '急诊';
        $array = explode('滨州医学院烟台附属医院', $BLNR_TXT);
        if (!empty($array[1])) {
            if (stripos($array[1], '初诊') !== false) {
                $saveData['bl_type'] = '初诊';
            } elseif (stripos($array[1], '复诊') !== false) {
                $saveData['bl_type'] = '复诊';
            }
        }

        // 身份证号获取
        $msBrdaData = MS_BRDA::query()->where("BRID", '=', $value['BRID'])->get()->toArray();
        $SFZH = !empty($msBrdaData[0]) ? $msBrdaData[0]['SFZH'] : '';
        if (mb_strlen($SFZH) > 14) {
            $saveData['SFZH'] = desensitize($SFZH, 6, 8, '*');
        }

        return $saveData;

    }
}
