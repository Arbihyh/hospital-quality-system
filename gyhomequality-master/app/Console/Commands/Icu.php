<?php

namespace App\Console\Commands;

use App\Model\PatientInfo;
use Illuminate\Console\Command;

class Icu extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:icu';

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
        $start = '20210101';
        while (true) {
            $end = date('Ymd', strtotime($start) + 86400);
            if ($end == date("Ymd")) {
                break;
            }
            echo $start . "\n";
            $start_page = 0;
            while (true){
                $end_page = $start_page + 9;
                $info = self::selectInsertData($start,$end,$start_page,$end_page);
                if (empty($info['data'])){
                    break;
                }
                //患者信息入库
                self::insertDataMain($info['data'],$info['inData']);
                $start_page += 10;
            }
            $start = $end;
        }
        return 0;
    }
    //查询患者信息
    public static function selectInsertData($start,$end,$start_page,$end_page){
        $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS', 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $sql = "SELECT * FROM (SELECT ROWNUM r,a.* FROM PORTAL_HIS.INIT_MED_REC_MAIN WHERE AAC01 BETWEEN to_date(".$start."000000,'yyyy-MM-dd HH24:mi:ss') AND to_date(".$start."235959,'yyyy-MM-dd HH24:mi:ss') ORDER BY MED_REC_ID,AAC01 ASC) WHERE r between $start_page and $end_page";
        $result = oci_parse($con, $sql);
        oci_execute($result,OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        $list = array_column($data,'MED_REC_ID');
        if (empty($list)){
            return ['data'=>[],'inData'=>[]];
        }
        $in = implode(',',$list);
        echo $in."\n";
        $in_sql = "SELECT to_char(AAA03,'yyyy-mm-dd hh24:mi:ss') as AAA03,to_char(SYN_DATE,'yyyy-mm-dd hh24:mi:ss') as SYN_DATE,to_char(AAB01,'yyyy-mm-dd hh24:mi:ss') as AAB01,to_char(AAC01,'yyyy-mm-dd hh24:mi:ss') as AAC01,to_char(AAC01,'yyyy-mm-dd hh24:mi:ss') as AAC01,MED_REC_ID FROM PORTAL_HIS.INIT_MED_REC_MAIN WHERE MED_REC_ID IN (".$in.")";
        $inResult = oci_parse($con, $in_sql);
        oci_execute($inResult,OCI_DEFAULT);
        $inData = [];
        while ($row = oci_fetch_assoc($inResult)) {
            $inData[] = $row;
        }
        $inData = array_column($inData,null,'MED_REC_ID');
        unset($con);
        return ['data'=>$data,'inData'=>$inData];
    }
    //患者信息
    public static function insertDataMain($data,$inData)
    {
        foreach ($data as $v) {
            $patient_info['AAA28'] = $v['AAA28'];
            $patient_info['MED_REC_ID'] = $v['MED_REC_ID'];
            $patient_info['AAA01'] = $v['AAA01'] ?: '';//患者姓名
            $patient_info['AAA02C'] = $v['AAA02C'] ?: '';//患者性别
            $patient_info['AAA03'] = $inData[$v['MED_REC_ID']]['AAA03'] ?: '';//出生日期
            $patient_info['AAA04'] = $v['AAA04'] ?: '';//年龄
            $patient_info['AAA05C'] = $v['AAA05C'] ?: '';//国籍
            $patient_info['AAA40'] = $v['AAA40'] ?: '';//不足一周岁年龄
            $patient_info['AAA42'] = $v['AAA42'] ?: '';//新生儿入院体重
            $patient_info['AEN01'] = $v['AEN01'] ?: '';//新生儿出生体重
            $patient_info['AAA06C'] = $v['AAA06C'] ?: '';//民族代码
            $patient_info['AAA07'] = $v['AAA07'] ?: '';//身份证号
            $patient_info['AAA08C'] = $v['AAA08C'] ?: '';//婚姻状况
            $patient_info['AEM01C'] = $v['AEM01C'] ?: '';//离院方式代码
            $patient_info['AAC01'] = $inData[$v['MED_REC_ID']]['AAC01'] ?: '';//出院时间
            $patient_info['AAC11N'] = $v['AAC11N'] ?: '';//出院医院内部科室名称
            $patient_info['AAC04'] = $v['AAC04'] ?: '';//实际住院
            $patient_info['ADA01'] = $v['ADA01'] ?: '';//总费用
            $patient_info['ADA0101'] = $v['ADA0101'] ?: '';//自付费用
            $patient_info['AAA29'] = $v['AAA29'] ?: '';//住院次数
            $patient_info['ABG01C'] = $v['ABG01C'] ?: '';//损伤和中毒外部原因编码 no
            $patient_info['ABG01N'] = $v['ABG01N'] ?: '';//损伤和中毒外部原因名称 no
            $patient_info['AAB06C'] = $v['AAB06C'] ?: '';//入院途径代码
            $patient_info['ABC01N'] = $v['ABC01N'] ?: '';//出院主要诊断名称
            $patient_info['ORG_STATE'] = $v['ORG_STATE'] ?: '';//质控状态
            $patient_info['AAA26C'] = $v['AAA26C'] ?: '';//医疗付费方式代码
            $patient_info['ATTEND_GRP_CODE'] = $v['ATTEND_GRP_CODE'] ?: '';//主诊组编码
            $patient_info['ATTEND_GRP_NAME'] = $v['ATTEND_GRP_NAME'] ?: '';//主诊组名称
            PatientInfo::query()->insert($patient_info);
        }
    }
}
