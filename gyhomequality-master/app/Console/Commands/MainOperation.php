<?php

namespace App\Console\Commands;

use App\Model\PatientAdd;
use App\Model\PatientCostInfo;
use App\Model\PatientInfo;
use App\Model\SecondaryOperation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MainOperation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:mo';

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
        $offset = 1;
        $limit = 10;
        $config = config('dictionaries.SSPB');
        while (true) {
            $page = ($offset - 1) * $limit;
            $list = PatientInfo::query()
                ->offset($page)
                ->limit($limit)
                ->orderBy('MED_REC_ID', 'desc')
                ->pluck('MED_REC_ID');
            if ($list) {
                $list = $list->toArray();
            } else {
                break;
            }
            if (empty($list)) {
                break;
            }
            $filter = [];
            $array = [];
            foreach ($list as $item){
                if (!in_array($item,$filter)){
                    $array[] = $item;
                }
            }
            $list = implode(',',$array);
            $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS',"UTF8");
            if (!$con) {
                $e = oci_error();
                print_r(htmlentities($e['message'])) . "\n";
                die;
            }
            $sql = "SELECT ZYH,SSCZBM,to_char(SSCZRQ,'yyyy-mm-dd hh24:mi:ss') as SSCZRI,SFZYSS,SSSX,SSCZMC,SSJB,SSLX,SZXM,SZBM,YZYSBM,YZXM,EZXM,EZYSBM,QKDJ,YHDJ,MZFS,MZYSXM,MZYSBM,to_char(SSKSSJ,'yyyy-mm-dd hh24:mi:ss') as SSKSSJ,to_char(SSJSSJ,'yyyy-mm-dd hh24:mi:ss') as SSJSSJ,SFWRJSS,SSPB FROM PORTAL_HIS.V_JMGS_BASY_SS WHERE ZYH IN (" . $list . ")";
            $data = oci_parse($con, $sql);
            oci_execute($data, OCI_DEFAULT);
            $result = [];
            while ($row = oci_fetch_assoc($data)) {
                $result[] = $row;
            }
            if (empty($result)) {

                $offset++;
                continue;
            }
            $main = [];
            $other = [];
            foreach ($result as $item) {
                if ($item['SFZYSS'] == 1){
                    $main[] = [
                        'AAA28' => $item['ZYH'],
                        'ICD9_ID1' => $item['SSCZBM'] ?? '',//手术或操作ID
                        'ICD9_NAME' => $item['SSCZMC'] ?? '',//手术或操作名称
                        'OPE_DATE' => $item['SSCZRI'] ?? '',//手术或操作日期
                        'OPE_ORDER' =>  $item['SSSX'] ?? '',//手术序号
                        'OPE_LEVEL' => $item['SSJB'] ?? '',//手术级别
                        'OPE_TYPE' => $item['SSLX'] ?? '',//手术类型
                        'OPE_MAN_NAME' => $item['SZXM'] ?? '',//主刀医师姓名
                        'OPE_MAN_CODE' => $item['SZBM'] ?? '',//主刀医师编码
                        'FRIST_ASSISTANT_CODE' => $item['YZYSBM'] ?? '',//一助医师编码
                        'FRIST_ASSISTANT_NAME' => $item['YZXM'] ?? '',//一助医师姓名
                        'SECOND_ASSISTANT_CODE' => $item['EZYSBM'] ?? '',//二助医师编码
                        'SECOND_ASSISTANT_NAME' => $item['EZXM'] ?? '',//二助医师姓名
                        'INCISION_GRADE_ID' => $item['QKDJ'] ?? 100,//切口等级
                        'HEAL_ID' => $item['YHDJ'] ?? 100,//愈合等级
                        'HOCUS_WAY_ID' => $item['MZFS'] ?? '',//麻醉方式
                        'HOCUS_MAN_CODE' => $item['MZYSBM'] ?? '',//麻醉医师编码
                        'HOCUS_MAN_NAME' => $item['MZYSXM'] ?? '',//麻醉医师名称
                        'START_TIME' => $item['SSKSSJ'] ?? '',//手术开始时间
                        'END_TIME' => $item['SSJSSJ'] ?? '',//手术结束时间
                        'RJSS' => $item['SFWRJSS'] ?? '',//是否日间手术
                        'SSPB' => array_search($item['SSPB'],$config) ?? 5//手术判别
                    ];
                }else{
                    $other[] = [
                        'AAA28' => $item['ZYH'],
                        'ICD9_ID1' => $item['SSCZBM'] ?? '',//手术或操作ID
                        'ICD9_NAME' => $item['SSCZMC'] ?? '',//手术或操作名称
                        'OPE_DATE' => $item['SSCZRI'] ?? '',//手术或操作日期
                        'OPE_ORDER' =>  $item['SSSX'] ?? '',//手术序号
                        'OPE_LEVEL' => $item['SSJB'] ?? '',//手术级别
                        'OPE_TYPE' => $item['SSLX'] ?? '',//手术类型
                        'OPE_MAN_NAME' => $item['SZXM'] ?? '',//主刀医师姓名
                        'OPE_MAN_CODE' => $item['SZBM'] ?? '',//主刀医师编码
                        'FRIST_ASSISTANT_CODE' => $item['YZYSBM'] ?? '',//一助医师编码
                        'FRIST_ASSISTANT_NAME' => $item['YZXM'] ?? '',//一助医师姓名
                        'SECOND_ASSISTANT_CODE' => $item['EZYSBM'] ?? '',//二助医师编码
                        'SECOND_ASSISTANT_NAME' => $item['EZXM'] ?? '',//二助医师姓名
                        'INCISION_GRADE_ID' => $item['QKDJ'] ?? 100,//切口等级
                        'HEAL_ID' => $item['YHDJ'] ?? 100,//愈合等级
                        'HOCUS_WAY_ID' => $item['MZFS'] ?? '',//麻醉方式
                        'HOCUS_MAN_CODE' => $item['MZYSBM'] ?? '',//麻醉医师编码
                        'HOCUS_MAN_NAME' => $item['MZYSXM'] ?? '',//麻醉医师名称
                        'START_TIME' => $item['SSKSSJ'] ?? '',//手术开始时间
                        'END_TIME' => $item['SSJSSJ'] ?? '',//手术结束时间
                        'RJSS' => $item['SFWRJSS'] ?? '',//是否日间手术
                        'SSPB' => array_search($item['SSPB'],$config) ?? 5//手术判别
                    ];
                }
            }
            DB::beginTransaction();
            if (!empty($main)) {
                \App\Model\MainOperation::query()->insert($main);
            }
            if (!empty($other)){
                SecondaryOperation::query()->insert($other);
            }
            DB::commit();
            sleep(1);
            $offset++;
        }
        return 0;
    }
}
