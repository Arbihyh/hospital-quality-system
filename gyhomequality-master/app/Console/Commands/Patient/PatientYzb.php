<?php

namespace App\Console\Commands\Patient;

use Illuminate\Console\Command;

class PatientYzb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'patient_yzb {startTime?} {endTime?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '患者医嘱信息同步';

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
        echo "同步患者医嘱信息开始：" . date('Y-m-d H:i:s') . "\n";
        ini_set('default_socket_timeout', 0);
        $this->connect();
        $s = time();
        $total = 0;
        $page = 1;
        $pageSize = 1000;
        $startTime = $this->argument('startTime');
        $endTime = $this->argument('endTime');
        $startTime = empty($startTime) ? date('Y-m-d 00:00:00', time() - 30 * 3600) : date('Y-m-d 00:00:00', strtotime($startTime));
        $endTime = empty($endTime) ? date('Y-m-d 23:59:59', time()) : date('Y-m-d 23:59:59', strtotime($endTime));
        while (true) {
            //由于patient_info表脚本执行速度快，更新时间不会隔天
            $PatientData = \App\Model\PatientInfo::query()
                ->whereBetween('updated_at', [$startTime, $endTime])
                ->paginate($pageSize, ['MED_REC_ID'], 'page', $page)
                ->toArray();
            $PatientData = $PatientData['data'];
            if (empty($PatientData)) {
                break;
            }
            echo "page:" . $page . " - " . date('Y-m-d H:i:s') . PHP_EOL;
            $zyhArray = array_unique(array_column($PatientData, 'MED_REC_ID'));
            foreach ($zyhArray as $v) {
                $data = $this->getData($v);
                if (!empty($data)) {
                    echo $v . " - " . $startTime . "共" . count($data) . "条数据" . " - " . date('Y-m-d H:i:s') . "\n";
                    $total += count($data);
                    $this->addData($data);
                }
            }
            $page++;
        }
        $e = time();
        $timeDifference = $e - $s;
        $totalHours = floor($timeDifference / 3600); // 3600秒 = 1小时
        $totalMinutes = floor(($timeDifference % 3600) / 60); // 剩余分钟
        echo "一共同步" . $total . "条数据；用时" . $totalHours . "小时" . $totalMinutes . "分钟" . "\n";
        echo "同步患者医嘱信息结束：" . date('Y-m-d H:i:s') . "\n";
        exit();
    }

    // 建立数据库连接
    public function connect()
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
    }

    public function getData($ZYH)
    {
        $sql = "SELECT A.*,
                    to_char(TZSJ,'yyyy-mm-dd hh24:mi:ss') as TJ,
                    to_char(XZJDSJ,'yyyy-mm-dd hh24:mi:ss') as XJ,
                    to_char(TZQRSJ,'yyyy-mm-dd hh24:mi:ss') as TZJ,
                    to_char(APSJ,'yyyy-mm-dd hh24:mi:ss') as AJ,
                    to_char(KZSJ,'yyyy-mm-dd hh24:mi:ss') as KJ
                    FROM PORTAL_HIS.BTF_EMR_YZB A
                    WHERE A.ZYH = " . $ZYH;
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        return $result;
    }

    public function addData($result)
    {
        foreach ($result as $val) {
            $insertData = [
                'ZYH' => $val['ZYH'],
                'YZBXH' => $val['YZBXH'] ?? '',
                'RID' => $val['BRID'] ?? '',
                'YEPB' => $val['YEPB'] ?? '',
                'BRKS' => $val['BRKS'] ?? '',
                'BRBQ' => $val['BRBQ'] ?? '',
                'BRCH' => $val['BRCH'] ?? '',
                'YDYZLB' => $val['YDYZLB'] ?? '',
                'XMLB' => $val['XMLB'] ?? '',
                'XMID' => $val['XMID'] ?? '',
                'XMDJ' => $val['XMDJ'] ?? '',
                'YZZH' => $val['YZZH'] ?? '',
                'YZQX' => $val['YZQX'] ?? '',
                'YYSX' => $val['YYSX'] ?? '',
                'KZKS' => $val['KZKS'] ?? '',
                'KZYS' => $val['KZYS'] ?? '',
                'KZSJ' => $val['KJ'] ?? '',
                'YZMC' => $val['YZMC'] ?? '',
                'YPCD' => $val['YPCD'] ?? '',
                'FYSX' => $val['FYSX'] ?? '',
                'SYPC' => $val['SYPC'] ?? '',
                'GYTJ' => $val['GYTJ'] ?? '',
                'YCJL' => $val['YCJL'] ?? '',
                'JLDW' => $val['JLDW'] ?? '',
                'ZL' => $val['ZL'] ?? '',
                'ZLDW' => $val['ZLDW'] ?? '',
                'JJYZ' => $val['JJYZ'] ?? '',
                'BLYZ' => $val['BLYZ'] ?? '',
                'TZSJ' => $val['TJ'] ?? '',
                'TZYS' => $val['TZYS'] ?? '',
                'YZZT' => $val['YZZT'] ?? '',
                'ZXZT' => $val['ZXZT'] ?? '',
                'KZDY' => $val['KZDY'] ?? '',
                'ZTBZ' => $val['ZTBZ'] ?? '',
                'XZJDGH' => $val['XZJDGH'] ?? '',
                'XZJDSJ' => $val['XJ'] ?? '',
                'TZQRGH' => $val['TZQRGH'] ?? '',
                'TZQRSJ' => $val['TZJ'] ?? null,
                'APSJ' => $val['AJ'] ?? null,
                'YYTS' => $val['YYTS'] ?? null,
                'YSZT' => $val['YSZT'] ?? '',
                'SRCS' => $val['SRCS'] ?? null,
                'SRSD' => $val['SRSD'] ?? '',
                'ZXSD' => $val['ZXSD'] ?? '',
                'DS' => $val['DS'] ?? null,
                'DSDW' => $val['DSDW'] ?? '',
                'PSBZ' => $val['PSBZ'] ?? '',
                'PSJG' => $val['PSJG'] ?? null,
                'ZFPB' => $val['ZFPB'] ?? '',
                'YBLX' => $val['YBLX'] ?? '',
                'SPBH' => $val['SPBH'] ?? null,
                'CYJF' => $val['CYJF'] ?? '',
                'PLSX' => $val['PLSX'] ?? '',
                'CZBZ' => $val['CZBZ'] ?? '',
                'BZXX' => $val['BZXX'] ?? '',
                'SQDH' => $val['SQDH'] ?? '',
                'ZXKS' => $val['ZXKS'] ?? '',
                'YFGG' => $val['YFGG'] ?? '',
                'YFDW' => $val['YFDW'] ?? '',
                'YFBZ' => $val['YFBZ'] ?? '',
                'SFSJ' => $val['SFSJ'] ?? '',
                'YFYY' => $val['YFYY'] ?? '',
                'YFYYYY' => $val['YFYYYY'] ?? '',
                'QXKZ' => $val['QXKZ'] ?? '',
                'YYPS' => $val['YYPS'] ?? '',
                'FZLJ' => $val['FZLJ'] ?? '',
                'PASSINDEX' => $val['PASSINDEX'] ?? '',
                'QXMC' => $val['QXMC'] ?? '',
                'YZPLZH' => $val['YZPLZH'] ?? '',
                'LCTS' => $val['LCTS'] ?? '',
                'ZLFY' => $val['ZLFY'] ?? '',
                'YZLX' => $val['YZLX'] ?? '',
                'SSYZ' => $val['SSYZ'] ?? '',
                'CDA_PC' => $val['CDA_PC'] ?? '',
                'NWARN' => $val['NWARN'] ?? '',
                'DSG_LDR_TIME' => $val['DSG_LDR_TIME'] ?? '',
                'DSG_OPERATION' => $val['DSG_OPERATION'] ?? '',
            ];
            \App\Model\Yzb::query()->updateOrInsert(['ZYH' => $insertData['ZYH'], 'YZBXH' => $insertData['YZBXH']], $insertData);
        }
    }
}
