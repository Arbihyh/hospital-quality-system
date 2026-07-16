<?php

namespace App\Console\Commands\Patient;

use Illuminate\Console\Command;

class V_JMGS_TESTRESULT extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'v_jmgs_testresult {start?} {end?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步 V_JMGS_TESTRESULT';

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
            $star = date('Y-m-d 00:00:00', strtotime('-30 day', time()));
        } else {
            $star = date('Y-m-d 00:00:00', strtotime($star));
        }
        if (empty($end)) {
            $end = date('Y-m-d 23:59:59', time());
        } else {
            $end = date('Y-m-d 23:59:59', strtotime($end));
        }
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
        $s = time();
        $total = 0;
        echo "V_JMGS_TESTRESULT数据同步开始:" . $star . PHP_EOL;
        while (true) {
            if ($star > $end) {
                break;
            }
            $star2 = date('Y-m-d 23:59:59', strtotime($star));

            $sql = "SELECT REPORT_PK,PERSON_NAME,IN_PATIENT_ID,OUT_PATIENT_ID,VISIT_TYPE_CODE,SAMPLE_NO,REPORT_NO,SPECIMEN_NAME,INP_NO,
                    LAB_DIAGNOSIS_NAME,LAB_ITEM_ENAME,LAB_ITEM_NAME,LAB_YM_RESULT,RESULT_STATUS_NAME,RANGE,MIN_RESULT_UNIT,
                    SPEC_SENDER_NAME,SPEC_CONFIRMER_NAME,PERFORMED_DOCTOR_NAME,OUTP_NO,VISIT_ID,APPLY_DEPT_NAME,
                    TO_CHAR(SAMPLE_TIME,'yyyy-mm-dd hh24:mi:ss') AS SAMPLE_TIME,
                    TO_CHAR(PRINT_TIME,'yyyy-mm-dd hh24:mi:ss') AS PRINT_TIME,
                    TO_CHAR(REPORT_TIME,'yyyy-mm-dd hh24:mi:ss') AS REPORT_TIME
                    FROM DBO.HDR_LAB_REPORT_BTF
                    WHERE REPORT_TIME BETWEEN TO_DATE('{$star}', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('{$star2}', 'yyyy-MM-dd HH24:mi:ss')";

            $result = oci_parse($con, $sql);
            oci_execute($result, OCI_DEFAULT);
            $data = [];
            while ($row = oci_fetch_assoc($result)) {
                $data[] = $row;
            }
            if (!empty($data)) {
                echo $star . " - 共" . count($data) . "条数据" . " - " . date('Y-m-d H:i:s') . PHP_EOL;
                $total += count($data);
                foreach ($data as $item) {
                    $zyh = 0;
                    if ($item['VISIT_TYPE_CODE'] == '01' || $item['VISIT_TYPE_CODE'] == '03') {
                        $zyh = $item['OUTP_NO'];
                    } elseif ($item['VISIT_TYPE_CODE'] == '02') {
                        $zyh = $item['VISIT_ID'];
                    }
                    $VISIT_TYPE_CODE = '';
                    if (!empty($item['VISIT_TYPE_CODE'])) {
                        $VISIT_TYPE_CODE = $item['VISIT_TYPE_CODE'];
                    } elseif ($item['APPLY_DEPT_NAME'] == '体检中心') {
                        $VISIT_TYPE_CODE = 100;
                    }
                    $testResultData = [
                        'ZYH' => $zyh,                                    //唯一标识
                        'TXM' => $item['SAMPLE_NO'] ?: '',                //样本编号、条码
                        'NO' => $item['REPORT_NO'] ?: '',                //报告号
                        'XM' => $item['PERSON_NAME'] ? desensitize($item['PERSON_NAME'], 1, 1, '*') : '', //姓名
                        'YBLX' => $item['SPECIMEN_NAME'] ?: '',            //样本类型
                        'AAA28' => $item['INP_NO'] ?: '',                   //住院号
                        'LCZD' => $item['LAB_DIAGNOSIS_NAME'] ?: '',       //临床诊断
                        'YW' => $item['LAB_ITEM_ENAME'] ?: '',           //英文（检验项目）
                        'JYXM' => $item['LAB_ITEM_NAME'] ?: '',            //检验项目
                        'JG' => $item['LAB_YM_RESULT'] ?: '',            //结果
                        'TS' => $item['RESULT_STATUS_NAME'] ?: '',       //提示
                        'CKFW' => $item['RANGE'] ?: '',                    //参考范围
                        'DW' => $item['MIN_RESULT_UNIT'] ?: '',          //单位
                        'SJYS' => $item['SPEC_SENDER_NAME'] ?: '',         //送检医生
                        'JYY' => $item['SPEC_CONFIRMER_NAME'] ?: '',      //检验员
                        'SHY' => $item['PERFORMED_DOCTOR_NAME'] ?: '',    //审核员
                        'CJSJ' => $item['SAMPLE_TIME'] ?: '',              //采集时间
                        'JSSJ' => $item['PRINT_TIME'] ?: '',               //接收时间
                        'BGSJ' => $item['REPORT_TIME'] ?: '',              //报告时间
                        'REPORT_PK' => $item['REPORT_PK'],
                        'VISIT_TYPE_CODE' => $VISIT_TYPE_CODE
                    ];

                    \App\Model\V_JMGS_TESTRESULT::query()->updateOrInsert(['REPORT_PK' => $testResultData['REPORT_PK']], $testResultData);
                }
            }
            $star = date('Y-m-d 00:00:00', strtotime($star) + 86400);
        }
        $e = time();
        $timeDifference = $e - $s;
        $totalHours = floor($timeDifference / 3600); // 3600秒 = 1小时
        $totalMinutes = floor(($timeDifference % 3600) / 60); // 剩余分钟
        echo "一共同步" . $total . "条数据；用时" . $totalHours . "小时" . $totalMinutes . "分钟" . "\n";
        echo "V_JMGS_TESTRESULT数据同步结束：" . date('Y-m-d H:i:s') . "\n";
        exit();
    }
}
