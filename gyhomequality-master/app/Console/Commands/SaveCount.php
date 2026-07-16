<?php

namespace App\Console\Commands;

use App\Model\Error;
use App\Model\ErrorData;
use App\Model\PatientInfo;
use App\Model\Count;
use App\Services\MedicalRecordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Pheanstalk\Pheanstalk;

class SaveCount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:saveCount';

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
        ini_set('default_socket_timeout',24 * 60 * 60);
        $beanstalkd = Pheanstalk::create(env('BEANSTALKD')??'localhost')->watch('saveCount');
        while (true){
            $job = $beanstalkd->reserve();
            $data = json_decode($job->getData(),true);
            if (!$data){
                $beanstalkd->delete($job);
            }
            $AAA28 = $data['AAA28'];
            $errorList = Error::query()
                ->where('AAA28',$AAA28)
                ->where('status',0)
                ->get();
            if ($errorList) {
                $errorList = $errorList->toArray();
            }else{
                $errorList = [];
            }
            //数据
            $data = MedicalRecordService::getData($AAA28);
            $Ydown = $data['score'];
            //分数
            $down = self::getScore($errorList);
            //缺陷数
            $count = count($errorList);
            $year = date("Y", strtotime($data['AAC01']));
            $month = date("m", strtotime($data['AAC01']));
            $source = $data['source'];
            $A = false;
            $category = array_column($errorList,'category');
            if (in_array(0,$category)){
                $A = true;
            }
            DB::beginTransaction();
            //统计信息
            $level = self::count($count,$year,$month,$down,$source,$Ydown,$A);
            PatientInfo::query()
                ->where('MED_REC_ID',$AAA28)
                ->update(['score'=>$down,'is_error'=> $down == 100 ? 0 : 1,'status'=>1,'level'=>$level]);
            DB::commit();
            $beanstalkd->delete($job);
        }
    }
    //分数计算
    public static function getScore($errorList)
    {
        if (empty($errorList)) {
            return 100;
        }
        $bClass = ['其他诊断名称'];
        $cClass = ['其他诊断编码'];
        $dClass = ['其他手术或操作名称'];
        $eClass = ['其他手术或操作编码'];
        $fClass = ['损伤(中毒)外部原因及疾病编码','病理诊断及编码和病历号','药物过敏史','尸检记录','血型及Rh标识','手术级别','术者','第一助手'];
        $gClass = ['综合医疗服务类','诊断类','治疗类','康复类中医类','西药类','中药类','血液和血制品类耗材类','其他类'];
        $aClass = ['健康卡号','患者姓名','出生地','籍贯','民族','身份证号','职业','婚姻状况','现住址','电话号码','邮编','户口地址及邮编','工作单位及地址','单位电话及邮编','联系人姓名','关系','地址','电话号码'];
        $score = 0;
        $aClassScore = 0;
        $bClassScore = 0;
        $cClassScore = 0;
        $dClassScore = 0;
        $eClassScore = 0;
        $fClassScore = 0;
        $gClassScore = 0;
        foreach ($errorList as $val) {
            if (in_array($val['error_name'],$aClass)) {
                $aClassScore += 1;
                if ($aClassScore > 8) {
                    continue;
                }
            } elseif (in_array($val['error_name'],$bClass)) {
                $bClassScore += 1;
                if ($bClassScore > 4){
                    continue;
                }
            } elseif (in_array($val['error_name'],$cClass)) {
                $cClassScore += 1;
                if ($cClassScore > 4){
                    continue;
                }
            } elseif (in_array($val['error_name'],$dClass)) {
                $dClassScore += 1;
                if ($dClassScore > 4){
                    continue;
                }
            } elseif (in_array($val['error_name'],$eClass)) {
                $eClassScore += 1;
                if ($eClassScore > 4){
                    continue;
                }
            } elseif (in_array($val['error_name'],$fClass)) {
                $fClassScore += 1;
                if ($fClassScore > 6){
                    continue;
                }
            } elseif (in_array($val['error_name'],$gClass)) {
                $gClassScore += 1;
                if ($gClassScore > 4){
                    continue;
                }
            }
            $score += $val['down'];
        }
        return sprintf( "%.1f",100 - $score);
    }
    //统计信息
    public static function count($count,$year,$month,$down,$source,$Ydown,$A) {
        $level = 0;
        $DepartmentQuery = Count::query();
        $Department = $DepartmentQuery
            ->where('year',$year)
            ->where('month',$month)
            ->first();
        if ($Department) {
            $Department = $Department->toArray();
            $d_id = $Department['id'];
            $extra = [];
            if ($count == 0){
                $extra['error_medical'] = DB::raw('error_medical-1');
            }
            if ($down >= 97) {
                $extra['excellent'] = DB::raw('excellent+1');
                $extra['excellent_qa'] = DB::raw('excellent_qa+1');
            } else if ($down >= 90 && $down <= 96 && $A == false) {
                $extra['good'] = DB::raw('good+1');
                $extra['good_qa'] = DB::raw('good_qa+1');
                $level = 1;
            }else if ($down >= 75 && $down <= 89 && $A == false) {
                $extra['middle'] = DB::raw('middle+1');
                $extra['middle_qa'] = DB::raw('middle_qa+1');
                $level = 2;
            } else {
                $extra['fail'] = DB::raw('fail+1');
                $extra['fail_qa'] = DB::raw('fail_qa+1');
                $level = 3;
            }
            if ($Ydown >= 97) {
                $extra['excellent'] = DB::raw('excellent+1');
                $extra['excellent_qa'] = DB::raw('excellent_qa+1');
            } else if ($down >= 90 && $down <= 96 && $A == false) {
                $extra['good'] = DB::raw('good+1');
                $extra['good_qa'] = DB::raw('good_qa+1');
            }else if ($down >= 75 && $down <= 89 && $A == false) {
                $extra['middle'] = DB::raw('middle+1');
                $extra['middle_qa'] = DB::raw('middle_qa+1');
            } else {
                $extra['fail'] = DB::raw('fail+1');
                $extra['fail_qa'] = DB::raw('fail_qa+1');
            }
            $ttm = $Department['total_medical'] - 1;
            $average_score = sprintf("%.2f",(($ttm * $Department['average_score']) + $down) / $Department['total_medical']);
            if ($average_score != $Department['average_score_qa']) {
                $extra['average_score_qa'] = $average_score;
            }
            $DepartmentQuery
                ->where('id',$d_id)
                ->where('year',$year)
                ->where('month',$month)
                ->update($extra);
        }
        return $level;
    }
}
