<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PatientYS_ZY_HZYJ extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'patient_ys_zy_hzyj {startTime?} {endTime?}';

    public static $con;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步患者会诊信息';

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
        echo "同步患者会诊信息开始：" . date('Y-m-d H:i:s') . "\n";
        $this->getConnect(); //建立数据库连接
        $startTime = $this->argument('startTime');
        $endTime = $this->argument('endTime');
        $s = time();
        $total = 0;
        $page = 1;
        $pageSize = 1000;
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
                    echo $v . " - " . $startTime . "共" . count($data) . "条数据" . "\n";
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
        echo "同步患者会诊信息结束：" . date('Y-m-d H:i:s') . "\n";
        exit();
    }

    /**
     * 建立数据库连接
     * @return void
     */
    public function getConnect()
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

    /**
     * 获取护士分床信息
     * @param $zyh
     * @return array
     */
    public function getData($zyh)
    {
        if (!self::$con) {
            $this->getConnect();
        }
        $zyh = (string)$zyh;
        $sql = "SELECT SQXH, JZHM, SQKS, SQYS, TO_CHAR(SQSJ) as SQSJ, HZMD, TO_CHAR(HZSJ) as HZSJ, YQDX, JJBZ,
                TJBZ, TJYS, TJSJ, ZFBZ, JSBZ, TO_CHAR(JSSJ,'yyyy-mm-dd hh24:mi:ss') as JSSJ, TXRY, BQZL, HZLX, BLBH
                FROM PORTAL_HIS.BTF_YS_ZY_HZSQ
                WHERE JZHM={$zyh}";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }

        return $data;
    }

    /**
     * 同步护士分床信息
     * @param $data
     * @return void
     */
    public function addData($data)
    {
        $SQXH = [];
        foreach ($data as $item) {
            $str = $item['BQZL']; //blobToStr($item['BQZL']);
            $inData = [
                'SQXH' => $item['SQXH'] ?? '',
                'JZHM' => $item['JZHM'] ?? '',
                'SQKS' => $item['SQKS'] ?? '',
                'SQYS' => $item['SQYS'] ?? '',
                'SQSJ' => date('Y-m-d H:i:s', strtotime($item['SQSJ'])) ?? '',
                'HZMD' => $item['HZMD'] ?? '',
                //                'HZMD2' => $item['HZMD2'] ?? '',//---这个字段注释掉 oracle没有这个字段
                'HZSJ' => date('Y-m-d H:i:s', strtotime($item['HZSJ'])) ?? '',
                'YQDX' => $item['YQDX'] ?? '',
                'JJBZ' => $item['JJBZ'] ?? '',
                'TJBZ' => $item['TJBZ'] ?? '',
                'TJYS' => $item['TJYS'] ?? '',
                'TJSJ' => $item['TJSJ'] ?? '',
                'ZFBZ' => $item['ZFBZ'] ?? '',
                'JSBZ' => $item['JSBZ'] ?? '',
                'JSSJ' => date('Y-m-d H:i:s', strtotime($item['JSSJ'])) ?? '',
                'TXRY' => $item['TXRY'] ?? '',
                'BQZL' => $str ?? '',
                'HZLX' => $item['HZLX'] ?? '',
                'BLBH' => $item['BLBH'] ?? '',
                //                'SQZD' => $item['SQZD'] ?? '',//---这个字段注释掉 oracle没有这个字段
                //                'JGID' => $item['JGID'] ?? '',//---这个字段注释掉 oracle没有这个字段
                //                'JSYS' => $item['JSYS'] ?? '',//---这个字段注释掉 oracle没有这个字段
                //                'JZBZ' => $item['JZBZ'] ?? '',//---这个字段注释掉 oracle没有这个字段
                //                'HZLB' => $item['HZLB'] ?? '',//---这个字段注释掉 oracle没有这个字段
            ];
            $SQXH[] = $inData['SQXH'];
            \App\Model\YS_ZY_HZSQ::query()->updateOrInsert(['SQXH' => $item['SQXH'],], $inData);
        }
        $SQXHStr = implode(',', $SQXH);
        $sql = "SELECT JLXH,SQXH,HZYJ,KSDM,SSYS,SXYS,to_char(SXSJ,'yyyy-mm-dd hh24:mi:ss') as SXSJ,
                to_char(QMSJ,'yyyy-mm-dd hh24:mi:ss') as QMSJ,
                to_char(JHZDDSJ,'yyyy-mm-dd hh24:mi:ss') as JHZDDSJ
                FROM PORTAL_HIS.BTF_YS_ZY_HZYJ
                WHERE SQXH in ($SQXHStr)";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        foreach ($data as $item) {
            $str = $item['HZYJ']; //blobToStr($item['HZYJ']);
            $insertData = [
                'JLXH' => $item['JLXH'] ?? '',
                'SQXH' => $item['SQXH'] ?? '',
                'HZYJ' => $str ?? '',
                'KSDM' => $item['KSDM'] ?? '',
                'SSYS' => $item['SSYS'] ?? '',
                'SXYS' => $item['SXYS'] ?? '',
                'SXSJ' => $item['SXSJ'] ?? '',
                'QMSJ' => $item['QMSJ'] ?? '',
                'JHZDDSJ' => $item['JHZDDSJ'] ?? '',
            ];

            \App\Model\YS_ZY_HZYJ::query()->updateOrInsert(['SQXH' => $insertData['SQXH']], $insertData);
        }
    }
}
