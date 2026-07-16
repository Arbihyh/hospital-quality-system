<?php

namespace App\Console\Commands\Patient;

use Illuminate\Console\Command;

class V_JMGS_YMresult extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'v_jmgs_ymresult {start?} {end?}';

    public static $con;
    /**
     *  docker exec homeQuality bash -c "cd /data/api && php artisan v_jmgs_ymresult 2021-01-01 2023-01-01"
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步药敏数据 v_jmgs_ymresult';

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
        $this->getConnect();
        $start = $this->argument('start');
        $end = $this->argument('end');
        $startTime = empty($start) ? date('Y-m-d 00:00:00', time() - 30 * 3600) : date('Y-m-d 00:00:00', strtotime($start));
        $endTime = empty($end) ? date('Y-m-d 23:59:59', time()) : date('Y-m-d 23:59:59', strtotime($end));
        echo "药敏数据同步开始:" . $startTime . PHP_EOL;
        $s = time();
        $total = 0;
        $page = 1;
        $pageSize = 1000;
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
        echo "药敏数据同步结束：" . date('Y-m-d H:i:s') . "\n";
        exit();
    }

    public function getConnect()
    {
        //进行数据库连接
        $username = "BTF";
        $password = "BTF";
        $connection = "192.168.10.20";
        $port = 1521;
        $tns = "xhlis";
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
        $sql = "SELECT OUTP_NO,VISIT_ID,VISIT_TYPE_CODE,SAMPLE_NO,PERSON_NAME,LAB_ITEM_ENAME,OUT_PATIENT_ID,
                    IN_PATIENT_ID,RESULT_STATUS_NAME,MICRO_ITEM_NAME,LAB_YM_NAME,LAB_YM_RESULT,SOURCE_PK,
                    TO_CHAR(REPORT_TIME,'yyyy-mm-dd hh24:mi:ss') AS BGSJ
                    FROM DBO.HDR_LAB_REPORT_DETAIL_MICRO
                   WHERE VISIT_ID = '" . $ZYH . "'OR OUTP_NO = '" . $ZYH . "'";

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
        foreach ($data as $item) {
            $zyh = 0;
            $AAA28 = '';
            if ($item['VISIT_TYPE_CODE'] == '01') {
                $zyh = $item['OUTP_NO'];
                $AAA28 = $item['OUT_PATIENT_ID'];
            } elseif ($item['VISIT_TYPE_CODE'] == '02') {
                $zyh = $item['VISIT_ID'];
                $AAA28 = $item['IN_PATIENT_ID'];
            }
            $ymResultData = [
                'ZYH' => $zyh,                                    //唯一标识
                'TXM' => $item['SAMPLE_NO'] ?: '',                //样本编号、条码
                'NO' => $item['SAMPLE_NO'] ?: '',                //报告号
                'XM' => $item['PERSON_NAME'] ? desensitize($item['PERSON_NAME'], 1, 1, '*') : '', //姓名
                'YBLX' => $item['LAB_ITEM_ENAME'] ?: '',            //样本类型
                'AAA28' => $AAA28,                                  //住院号
                'PYJG' => $item['RESULT_STATUS_NAME'],             //细菌培养结果
                'XJMC' => $item['MICRO_ITEM_NAME'] ?: '',          //细菌名称
                'XJJL' => $item['LAB_YM_RESULT'] ?: '',            //细菌数量
                'YMMC' => $item['LAB_YM_NAME'] ?: '',              //药敏名称
                'YMJG' => $item['LAB_YM_RESULT'] ?: '',            //药敏结果
                'YMBW' => $item['LAB_ITEM_ENAME'] ?: '',           //药敏部位
                'BGSJ' => $item['BGSJ'] ?: '',              //报告时间
                'SOURCE_PK' => $item['SOURCE_PK']
            ];

            \App\Model\V_JMGS_YMresult::query()->updateOrInsert(['SOURCE_PK' => $ymResultData['SOURCE_PK'], 'ZYH' => $ymResultData['ZYH']], $ymResultData);
        }
    }

}
