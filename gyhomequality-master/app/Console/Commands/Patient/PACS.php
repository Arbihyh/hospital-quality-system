<?php

namespace App\Console\Commands\Patient;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PACS extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pacs {time?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步PACS数据';

    public static $con;

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
    public function handle(){
        $servername = "192.168.53.33:3306"; // 数据库服务器地址
        $username = "jnsyyyxt"; // 数据库用户名
        $password = "Jnsy_yyxt5"; // 数据库密码
        $dbname = "clinical"; // 数据库名
        $options = [
            \PDO::ATTR_ERRMODE          => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $dsn = "mysql:host=$servername;dbname=$dbname;charset=utf8mb4";
        // 创建连接
        $conn = new \PDO($dsn, $username, $password, $options);

        $start = $this->argument('time');
        if (empty($start)) {
            $start = date('Y-m-d 00:00:00', time()-86400);
        }
        $end = date('Y-m-d H:i:s');
        $examTypeConf = config("pacsMap.ExamType");
        while (true){
            echo $start . PHP_EOL;
            if ($start > $end){
                echo "pacs数据同步完成".PHP_EOL;
                break;
            }
            $start_end = date('Y-m-d 23:59:59', strtotime($start));
            $sql = "SELECT * FROM hdr_exam_report WHERE APPLY_TIME >= '$start' AND APPLY_TIME <= '$start_end'";
            $statement = $conn->query($sql);
            $data = $statement->fetchAll(\PDO::FETCH_ASSOC);
            if (!empty($data)){
//                DB::table("PACS")->where('ZYH','=',$zyh)->delete();
                foreach ($data as $v) {
                    if($v['VISIT_TYPE_NAME'] != "住院"){
                        continue;
                    }
                    $examType = isset($examTypeConf[$v['EXAM_CLASS_CODE']]) ? $examTypeConf[$v['EXAM_CLASS_CODE']]['EXAM_TYPE_CODE'] : '';
                    $insert = [
                        "ZYH"       => $v['VISIT_ID'],                  //唯一标识
                        "JZLSH"     => $v['APPLY_NO'],                  //就诊流水号：用于与住院就诊记录表或门诊就诊记录表关联的外键(可选关联关系)
                        "BAH"     => $v['INP_NO'],
                        "MZZYBZ"    => $v['VISIT_TYPE_NAME'],           //门诊/住院标志：1门诊，2住院，3、体检 4、绿色通道、41、门诊绿色通道、42、住院绿色通道、9其他
                        "BRXM"      => $v['PERSON_NAME'],               //病人姓名
                        "BRXB"      => $v['SEX_NAME'],                  //病人性别
                        "PatientID" => $v['PACS_URL'],                  //影像号：被检查的病人在医院内部的影像号码，即影像图像DICOM文件中对应Dicom中位置(0010,0020)的值
                        "JCXMDM"    => $v['EXAM_ITEM_CODE'],            //检查项目代码
                        "SQDH"      => $v['APPLY_NO'],                  //申请单号：该检查在HIS或RIS中的申请单编号
                        "JYSJ"      => $v['EXAM_PERFORM_TIME'],         //检查时间
                        "ExamType"  => $examType,                       //检查类型，编码：表明病人检查的类型。01 计算机X线断层摄影 CT，02 核磁共振成像MR，03 数字减影血管造影DSA，04 普通X光摄影X-Ray，05 特殊X光摄影X-Ray，06 超声检查US，07 病理检查Microscopy，08 內窥镜检查ES，09 核医学检查NM，10 其他检查OT，11 介入
                        "SQKS"      => $v['APPLY_DEPT_CODE'],           //申请科室编码
                        "SQKSMC"    => $v['APPLY_DEPT_NAME'],           //申请科室名称
                        "SQRGH"     => $v['APPLY_DOCTOR_CODE'],         //申请人工号
                        "SQRXM"     => $v['APPLY_DOCTOR_NAME'],         //申请人姓名
                        "JCKSMC"    => $v['EXAM_ROOM'],                 //检查科室名称
                        "JCYS"      => $v['PERFORM_DOCTOR'],            //检查医生姓名
                        "BGSJ"      => $v['REPORT_TIME'],               //报告时间
                        "BGRQ"      => $v['REPORT_TIME'],               //报告日期
                        "BGRGH"     => $v['REPORT_DOCTOR_CODE'],        //报告人工号
                        "BGRXM"     => $v['REPORT_DOCTOR_NAME'],        //报告人姓名
                        "SHRGH"     => $v['REPORT_CONFIRMER_CODE'],     //审核人工号
                        "SHRXM"     => $v['REPORT_CONFIRMER_NAME'],     //审核人姓名
                        "JCBW"      => $v['EXAM_PART_NAME'],            //检查部位
                        "BWACR"     => $v['EXAM_PART_CODE'],            //检查部位
                        "JCMC"      => $v['EXAM_ITEM_NAME'],            //检查名称
                        "YXBX"      => $v['EXAM_FEATURE'],              //影像表现或检查所见
                        "YXZD"      => $v['EXAM_DIAG'],                 //检查诊断或提示
                        "SFYYY"     => $v['PACS_URL'],                  //是否有影像:1：有；2：无；3：未定；
                        "XGBZ"      => $v['REPORT_STATUS_NAME'],        //修改标志: 编码。0：正常、1：撤销；
                        "KDSJ"      => $v['APPLY_TIME'],                //开单时间
                        "SOURCE_PK" => $v['SOURCE_PK'],                //来源主键
                    ];
                    //DB::table("PACS")->insert($insert);
                    \App\Model\PACS::query()->updateOrInsert(['SOURCE_PK' => $insert['SOURCE_PK'],'BAH' => $v['INP_NO']], $insert);
                }
            }
            $start = date("Y-m-d 00:00:00",strtotime($start) + 86400);
        }

        echo "pacs数据同步结束".PHP_EOL;
        die();
    }
}
