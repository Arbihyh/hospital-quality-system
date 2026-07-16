<?php

namespace App\Console\Commands\Patient;

use App\Model\FeeDetailed;
use App\Model\Icu;
use App\Model\MainDiagnosis;
use App\Model\MainOperation;
use App\Model\OtherDiagnosis;
use App\Model\SecondaryOperation;
use Illuminate\Console\Command;

class PatientOperation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'patient_operation {startTime?} {endTime?}';

    public static $con;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步患者手术信息';

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
        echo "同步患者手术信息开始：" . date('Y-m-d H:i:s') . "\n";
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
        echo "同步患者手术信息结束：" . date('Y-m-d H:i:s') . "\n";
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

    public function getData($ZYH)
    {
        $sql = "SELECT A.*,to_char(SSKSSJ,'yyyy-mm-dd hh24:mi:ss') as SSKSSJ,
                to_char(SSJSSJ,'yyyy-mm-dd hh24:mi:ss') as SSJSSJ,
                to_char(SSCZRI,'yyyy-mm-dd hh24:mi:ss') as SSCZRI
                FROM PORTAL_HIS.V_JMGS_BASY_SS A WHERE A.ZYH=" . $ZYH;
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }

        return $result;
    }


    public function addData($data)
    {
        $main = [];
        $other = [];
        foreach ($data as $val) {
            $addData = [
                'AAA28' => $val['ZYH'],
                'ICD9_ID1' => $val['SSCZBM'] ?? '',//手术或操作ID
                'ICD9_NAME' => $val['SSCZMC'] ?? '',//手术或操作名称
                'OPE_DATE' => $val['SSCZRI'] ?? '',//手术或操作日期  ---oracle没有字段SSCZRQ,更改为字段SSCZRI
                'OPE_ORDER' => $val['SSSX'] ?? '',//手术序号
                'OPE_LEVEL' => $val['SSJB'] ?? '',//手术级别
                'OPE_TYPE' => $val['SSLX'] ?? '',//手术类型
                'OPE_MAN_NAME' => $val['SZXM'] ?? '',//主刀医师姓名
                'OPE_MAN_CODE' => $val['SZBM'] ?? '',//主刀医师编码
                'FRIST_ASSISTANT_CODE' => $val['YZYSBM'] ?? '',//一助医师编码
                'FRIST_ASSISTANT_NAME' => $val['YZXM'] ?? '',//一助医师姓名
                'SECOND_ASSISTANT_CODE' => $val['EZYSBM'] ?? '',//二助医师编码
                'SECOND_ASSISTANT_NAME' => $val['EZXM'] ?? '',//二助医师姓名
                'INCISION_GRADE_ID' => $val['QKDJ'] !== null ? $val['QKDJ'] : 100,//切口等级
                'HEAL_ID' => $val['YHDJ'] !== null ? $val['YHDJ'] : 100,//愈合等级
                'HOCUS_WAY_ID' => $val['MZFS'] ?? '',//麻醉方式
                'HOCUS_MAN_CODE' => $val['MZYSBM'] ?? '',//麻醉医师编码
                'HOCUS_MAN_NAME' => $val['MZYSXM'] ?? '',//麻醉医师名称
                'START_TIME' => $val['SSKSSJ'] ?? '',//手术开始时间
                'END_TIME' => $val['SSJSSJ'] ?? '',//手术结束时间
                'RJSS' => $val['SFWRJSS'] ?? '',//是否日间手术
                //                'CYRQ' => $val['CYRQ'] ?? '', //--- 这个注释掉，oracle没有这个字段
                'SFZYSS' => $val['SFZYSS'] ?? '',
                //                'QKDJ' => $val['QKDJ'] ?? '',
                //                'YHDJ' => $val['YHDJ'] ?? '',
                'QKDJ' => $val['QKDJ'] !== null ? $val['QKDJ'] : 100,
                'YHDJ' => $val['YHDJ'] !== null ? $val['YHDJ'] : 100,
                'BAHM' => $val['BAHM'] ?? '',
                'ZYHM' => $val['ZYHM'] ?? '',
                //                'SSPB' => intval(array_search($val['SSPB'], $config['SSPB']) ?? 5)//手术判别
                'SSPB' => intval($val['SSPB'] !== null ? $val['SSPB'] : 5)//手术判别
            ];

            if ($val['SSSX'] == 1) {
                $main[] = $addData;
            } else {
                $other[] = $addData;
            }
        }

        // 主要手术
        MainOperation::query()->where('AAA28', '=', $data[0]['ZYH'])->delete();
        if (!empty($main)) {
            MainOperation::query()->insert($main);
        }
        // 其他手术
        SecondaryOperation::query()->where('AAA28', '=', $data[0]['ZYH'])->delete();
        if (!empty($other)) {
            SecondaryOperation::query()->insert($other);
        }
    }

}
