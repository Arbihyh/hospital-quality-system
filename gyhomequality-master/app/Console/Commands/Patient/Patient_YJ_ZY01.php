<?php

namespace App\Console\Commands\Patient;

use Illuminate\Console\Command;

class Patient_YJ_ZY01 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'patient_yj_zy01 {startTime?} {endTime?}';

    public static $con;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步医技信息';

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
        echo "同步医技信息开始：" . date('Y-m-d H:i:s') . "\n";
        $this->getConnect(); //建立数据库连接
        $startTime = $this->argument('startTime');
        $endTime = $this->argument('endTime');
        $s = time();
        $total = 0;
        $page = 1;
        $pageSize = 200;
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
                    echo $v . " - " . $startTime . "共" . count($data) . "条数据 - " . date('Y-m-d H:i:s') . "\n";
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
        echo "同步医技信息结束：" . date('Y-m-d H:i:s') . "\n";
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

    public function getData($zyh)
    {
        $sql = "SELECT YJXH,TJHM,ZYH,ZYHM,BRXM,to_char(KDRQ,'yyyy-mm-dd hh24:mi:ss') as KDRQ,KSDM,YSDM,
                to_char(ZXRQ,'yyyy-mm-dd hh24:mi:ss') as ZXRQ,ZXKS,ZXPB,HJGH,BBBM,ZYSX,ZFPB,HYMX,YJPH,SQDH,BWID,JBID,
                DJZT,SQWH,FYBQ,SQID,JGID
                FROM PORTAL_HIS.BTF_YJ_ZY01
                WHERE ZYH={$zyh}";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }

        return $data;
    }

    public function addData($data)
    {
        $YJXH = [];
        foreach ($data as $item) {
            $YJXH[] = $item['YJXH'];
            $insertData = [
                'YJXH' => $item['YJXH'] ?? '',
                'TJHM' => $item['TJHM'] ?? '',
                'ZYH' => $item['ZYH'] ?? '',
                'ZYHM' => $item['ZYHM'] ?? '',
                'BRXM' => desensitize($item['BRXM'], 1, 1, '*'),
                'KDRQ' => $item['KDRQ'] ?? '',
                'KSDM' => $item['KSDM'] ?? '',
                'YSDM' => $item['YSDM'] ?? '',
                'ZXRQ' => $item['ZXRQ'] ?? '',
                'ZXKS' => $item['ZXKS'] ?? '',
                'ZXPB' => $item['ZXPB'] ?? '',
                'BBBM' => $item['BBBM'] ?? '',
                'ZYSX' => $item['ZYSX'] ?? '',
                'ZFPB' => $item['ZFPB'] ?? '',
                'HYMX' => $item['HYMX'] ?? '',
                'YJPH' => $item['YJPH'] ?? '',
                'SQDH' => $item['SQDH'] ?? '',
                'BWID' => $item['BWID'] ?? '',
                'JBID' => $item['JBID'] ?? '',
                'DJZT' => $item['DJZT'] ?? '',
                'SQWH' => $item['SQWH'] ?? '',
                'FYBQ' => $item['FYBQ'] ?? '',
                'SQID' => $item['SQID'] ?? '',
                //                'YQDH' => $item['YQDH'] ?? '',//---这个字段注释掉 oracle没有这个字段
                'JGID' => $item['JGID'] ?? '',
                //                'SSYS' => $item['SSYS'] ?? '',//---这个字段注释掉 oracle没有这个字段
                //                'SSYZ' => $item['SSYZ'] ?? '',//---这个字段注释掉 oracle没有这个字段
                //                'SSEZ' => $item['SSEZ'] ?? '',//---这个字段注释掉 oracle没有这个字段
                //                'SSSZ' => $item['SSSZ'] ?? '',//---这个字段注释掉 oracle没有这个字段
                //                'JZBZ' => $item['JZBZ'] ?? '',//---这个字段注释掉 oracle没有这个字段
            ];

            \App\Model\YJ_ZY01::query()->updateOrInsert(['YJXH' => $insertData['YJXH']], $insertData);
        }

        $YJXHstr = implode(',', $YJXH);

        $chunkedArray = array_chunk(explode(',', $YJXHstr), 1000);
        foreach ($chunkedArray as $items) {
            $YJXHstrTemp = implode(',', $items);
            $sql = "SELECT SBXH, YJXH, YLXH, XMLX, YJZX, YLDJ, YLSL, FYGB, ZFBL, YZXH, TPLJ, YEPB
                FROM PORTAL_HIS.BTF_YJ_ZY02
                WHERE YJXH in ($YJXHstrTemp)";
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
                if (empty($item)) {
                    continue;
                }
                $insertData = [
                    'SBXH' => $item['SBXH'] ?? '',
                    'YJXH' => $item['YJXH'] ?? '',
                    'YLXH' => $item['YLXH'] ?? '',
                    'XMLX' => $item['XMLX'] ?? '',
                    'YJZX' => $item['YJZX'] ?? '',
                    'YLDJ' => $item['YLDJ'] ?? '',
                    'YLSL' => $item['YLSL'] ?? '',
                    'FYGB' => $item['FYGB'] ?? '',
                    'ZFBL' => $item['ZFBL'] ?? '',
                    'YZXH' => $item['YZXH'] ?? '',
                    'TPLJ' => $item['TPLJ'] ?? '',
                    'YEPB' => $item['YEPB'] ?? '',

                    //                'TMDY_LQ' => $item['TMDY_LQ'] ?? '',//---这个字段注释掉 oracle没有这个字段
                    //                'TMH' => $item['TMH'] ?? '',//---这个字段注释掉 oracle没有这个字段
                    //                'JGID' => $item['JGID'] ?? '',//---这个字段注释掉 oracle没有这个字段
                    //                'ZTMC' => $item['ZTMC'] ?? '',//---这个字段注释掉 oracle没有这个字段
                    //                'JHH' => $item['JHH'] ?? '',//---这个字段注释掉 oracle没有这个字段
                    //                'XDH' => $item['XDH'] ?? '',//---这个字段注释掉 oracle没有这个字段
                    //                'JHSJ' => $item['JHSJ'] ?? '',//---这个字段注释掉 oracle没有这个字段
                ];
                \App\Model\YJ_ZY02::query()->updateOrInsert(['SBXH' => $insertData['SBXH']], $insertData);
            }
        }


    }
}
