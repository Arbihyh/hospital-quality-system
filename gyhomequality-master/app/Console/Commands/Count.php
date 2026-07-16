<?php

namespace App\Console\Commands;

use App\Model\AttendingGroupData;
use App\Model\CoderData;
use App\Model\DepartmentData;
use App\Model\Error;
use App\Model\ErrorData;
use App\Model\HospitalData;
use App\Model\IndicationsData;
use App\Model\PatientScore;
use App\Services\MedicalRecordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Pheanstalk\Pheanstalk;
use Illuminate\Support\Facades\Cache;
class Count extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:count';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '数据统计';

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
        $beanstalkd = Pheanstalk::create('beanstalkd')->watch('count');
        echo 'count'."\n";
        while (true){
            $job = $beanstalkd->reserve();
            $data = json_decode($job->getData(),true);
            if (!$data){
                $beanstalkd->delete($job);
            }
            $AAA28 = $data['AAA28'];
            $errorList = Error::query()->where('ZYH','=',$AAA28)->get();
            if ($errorList){
                $errorList = $errorList->toArray();
            }else{
                echo $AAA28."不存在缺陷信息\n";
                continue;
            }
            //数据
            $data = MedicalRecordService::getData($AAA28);
            //分数
            $down = self::getScore($errorList);
            echo $AAA28.'-'.array_sum(array_column($errorList,'down'))."\n";
            //缺陷数
            $count = count($errorList);
            $l = 0;$g = 0;$b = 0;
            $year = date("Y", strtotime($data['AAC01']));
            $month = date("m", strtotime($data['AAC01']));
            $A = false;
            $category = array_column($errorList,'category');
            if (in_array(0,$category)){
                $A = true;
            }
            foreach ($errorList as $item){
                if ($item['error_type'] == 0){
                    $l += 1;
                }
                if ($item['error_type'] == 1){
                    $g += 1;
                }
                if ($item['error_type'] == 2){
                    $b += 1;
                }
                if ($item['category'] == 0){
                    $A = true;
                }
            }
            //科室
            self::ks($data,$count,$year,$month,$down);
            //主诊组
            self::zzz($data,$count,$l,$g,$b,$year,$month);
            //主治医师
            self::zzys($data,$count,$l,$g,$b,$year,$month);
            //住院医师
            self::zyys($data,$count,$l,$g,$b,$year,$month);
            //编码员
            self::bmy($data,$count,$year,$month);
            //统计信息
            $level = self::count($count,$year,$month,$down,$A,$data['hospital_name']);
            //缺陷问题
            self::errorData($errorList,$year,$month,$data['hospital_name']);
            //病案分数
            PatientScore::query()->insert([
                'ZYH'=>$AAA28,
                'score'=>$down,
                'is_error'=> $down == 100 ? 0 : 1,
                'level'=>$level
            ]);
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

    //科室
    public static function ks($data,$count,$year,$month,$down){
        if (empty($data['AAB11C'])){
            return;
        }
        $attending_group_query = DepartmentData::query();
        $attending_group_data = $attending_group_query
            ->where('hospital_name','=',$data['hospital_name'])
            ->where('department_id',$data['AAB11C'])
            ->where('year',$year)
            ->where('month',$month)
            ->first();
        if ($attending_group_data){
            $d_data = $attending_group_data->toArray();
            $extra = [];
            if ($count > 0){
                $extra['total_score'] = DB::raw('total_score+'.$down);
                $extra['total_error'] = DB::raw('total_error+'.$count);
                $extra['error_exist'] = DB::raw('error_exist+'.$count);
                $extra['total_error_medical'] = DB::raw('total_error_medical+1');
                $extra['error_medical'] = DB::raw('error_medical+1');
            }
            if ($d_data['max_score'] < $down){
                $extra['max_score'] = $down;
            }
            if ($d_data['min_score'] > $down){
                $extra['min_score'] = $down;
            }
            $attending_group_query
                ->where('hospital_name','=',$data['hospital_name'])
                ->where('department_id',$data['AAB11C'])
                ->where('year',$year)
                ->where('month',$month)
                ->increment('total_medical',1,$extra);
        }else{
            $agd = [
                'hospital_name' => $data['hospital_name'],
                'department_id' => $data['AAB11C'],
                'total_medical' => 1,
                'total_error_medical' => 1,
                'error_medical' => 1,
                'total_error' => $count,
                'error_exist' => $count,
                'total_score' => $down,
                'max_score' => $down,
                'min_score' => $down,
                'year' => $year,
                'month' => $month,
                'created_at' => $year.'-'.$month.'-'.'01 00:00:00'
            ];
            $attending_group_query->insert($agd);
        }
    }

    //主诊组
    public static function zzz($data,$count,$l,$g,$b,$year,$month){
        if (empty($data['ATTEND_GRP_CODE'])){
            return;
        }
        $attending_group_query = AttendingGroupData::query();
        $attending_group_data = $attending_group_query
            ->where('hospital_name','=',$data['hospital_name'])
            ->where('attending_group_id',$data['ATTEND_GRP_CODE'])
            ->where('year',$year)
            ->where('month',$month)
            ->first();
        if ($attending_group_data){
            $extra = [];
            if ($l > 0){
                $extra['logic_error_medical'] = DB::raw('logic_error_medical+'.$l);
            }
            if ($g > 0){
                $extra['standard_error_medical'] = DB::raw('standard_error_medical+'.$g);
            }
            if ($b > 0){
                $extra['code_error_medical'] = DB::raw('code_error_medical+'.$b);
            }
            if ($count > 0){
                $extra['total_error_medical'] = DB::raw('total_error_medical+1');
                $extra['error_medical'] = DB::raw('error_medical+1');
            }
            $attending_group_query
                ->where('hospital_name','=',$data['hospital_name'])
                ->where('attending_group_id',$data['ATTEND_GRP_CODE'])
                ->where('year',$year)
                ->where('month',$month)
                ->increment('total_medical',1,$extra);
        }else{
            $agd = [
                'hospital_name' => $data['hospital_name'],
                'attending_group_id' => $data['ATTEND_GRP_CODE'],
                'year' => $year,
                'month' => $month,
                'total_medical' => 1,
                'total_error_medical' => $count>=0?1:0,
                'error_medical' => $count>=0?1:0,
                'logic_error_medical' => $l,
                'standard_error_medical' => $g,
                'code_error_medical' => $b,
                'created_at' => $year.'-'.$month.'-'.'01 00:00:00'
            ];
            $attending_group_query->insert($agd);
        }
    }

    //主治医师
    public static function zzys($data,$count,$l,$g,$b,$year,$month){
        if (empty($data['AEE03_CODE'])){
            return;
        }
        $attending_group_query = IndicationsData::query();
        $attending_group_data = $attending_group_query
            ->where('hospital_name','=',$data['hospital_name'])
            ->where('indications_id',$data['AEE03_CODE'])
            ->where('year',$year)
            ->where('month',$month)
            ->first();
        if ($attending_group_data){
            $extra = [];
            if ($l > 0){
               $extra['logic_error_medical'] = DB::raw('logic_error_medical+'.$l);
            }
            if ($g > 0){
               $extra['standard_error_medical'] = DB::raw('standard_error_medical+'.$g);
            }
            if ($b > 0){
               $extra['code_error_medical'] = DB::raw('code_error_medical+'.$b);
            }
            if ($count > 0){
                $extra['total_error_medical'] = DB::raw('total_error_medical+1');
                $extra['error_medical'] = DB::raw('error_medical+1');
            }
            $attending_group_query
                ->where('hospital_name','=',$data['hospital_name'])
                ->where('indications_id',$data['AEE03_CODE'])
                ->where('year',$year)
                ->where('month',$month)
                ->increment('total_medical',1,$extra);
        }else{
            $agd = [
                'hospital_name' => $data['hospital_name'],
                'indications_id' => $data['AEE03_CODE'],
                'department_id' => $data['AAB11C'],
                'year' => $year,
                'month' => $month,
                'total_medical' => 1,
                'total_error_medical' => $count>=0?1:0,
                'error_medical' => $count>=0?1:0,
                'logic_error_medical' => $l,
                'standard_error_medical' => $g,
                'code_error_medical' => $b,
                'created_at' => $year.'-'.$month.'-'.'01 00:00:00'
            ];
            $attending_group_query->insert($agd);
        }
    }

    //住院医师
    public static function zyys($data,$count,$l,$g,$b,$year,$month){
        if (empty($data['AEE04_CODE'])){
            return;
        }
        $attending_group_query = HospitalData::query();
        $attending_group_data = $attending_group_query
            ->where('hospital_name','=',$data['hospital_name'])
            ->where('hospital_id',$data['AEE04_CODE'])
            ->where('year',$year)
            ->where('month',$month)
            ->first();
        if ($attending_group_data){
            $extra = [];
            if ($l > 0){
                $extra['logic_error_medical'] = DB::raw('logic_error_medical+'.$l);
            }
            if ($g > 0){
                $extra['standard_error_medical'] = DB::raw('standard_error_medical+'.$g);
            }
            if ($b > 0){
                $extra['code_error_medical'] = DB::raw('code_error_medical+'.$b);
            }
            if ($count > 0){
                $extra['total_error_medical'] = DB::raw('total_error_medical+1');
                $extra['error_medical'] = DB::raw('error_medical+1');
            }
            $attending_group_query
                ->where('hospital_name','=',$data['hospital_name'])
                ->where('hospital_id',$data['AEE04_CODE'])
                ->where('year',$year)
                ->where('month',$month)
                ->increment('total_medical',1,$extra);
        }else{
            $agd = [
                'hospital_name' => $data['hospital_name'],
                'hospital_id' => $data['AEE04_CODE'],
                'department_id' => $data['AAB11C'],
                'year' => $year,
                'month' => $month,
                'total_medical' => 1,
                'total_error_medical' => $count>=0?1:0,
                'error_medical' => $count>=0?1:0,
                'logic_error_medical' => $l,
                'standard_error_medical' => $g,
                'code_error_medical' => $b,
                'created_at' => $year.'-'.$month.'-'.'01 00:00:00'
            ];
            $attending_group_query->insert($agd);
        }
    }

    //编码员
    public static function bmy($data,$count,$year,$month){
        if (empty($data['AEE08'])){
            return;
        }
        $attending_group_query = CoderData::query();
        $attending_group_data = $attending_group_query
            ->where('hospital_name','=',$data['hospital_name'])
            ->where('coder_id',$data['AEE08'])
            ->where('year',$year)
            ->where('month',$month)
            ->first();
        if ($attending_group_data) {
            $extra = [];
            if ($count > 0) {
                $extra = [
                    'total_error_medical' => DB::raw('total_error_medical+1'),
                    'error_medical' => DB::raw('error_medical+1')
                ];
            }
            $attending_group_query
                ->where('hospital_name','=',$data['hospital_name'])
                ->where('coder_id',$data['AEE08'])
                ->where('year',$year)
                ->where('month',$month)
                ->increment('total_medical',1,$extra);
        } else {
            $agd = [
                'hospital_name' => $data['hospital_name'],
                'coder_id' => $data['AEE08'],
                'year' => $year,
                'month' => $month,
                'total_medical' => 1,
                'total_error_medical' => $count>=0?1:0,
                'created_at' => $year.'-'.$month.'-'.'01 00:00:00'
            ];
            $attending_group_query->insert($agd);
        }
    }

    //统计信息
    public static function count($count,$year,$month,$down,$A,$hospitalName) {
        $level = 0;
        $DepartmentQuery = \App\Model\Count::query();
        $Department = $DepartmentQuery
            ->where('hospital_name','=',$hospitalName)
            ->where('year',$year)
            ->where('month',$month)
            ->first();
        if ($Department) {
            $Department = $Department->toArray();
            $d_id = $Department['id'];
            $extra = [];
            if ($count > 0){
                $extra['cumulative_medical'] = DB::raw('cumulative_medical+1');
                $extra['error_medical'] = DB::raw('error_medical+1');
            }
            $extra['total_score'] = DB::raw('total_score+'.$down);
            $extra['total_score_qa'] = DB::raw('total_score_qa+'.$down);
            if ($down >= 97) {
                $extra['excellent'] = DB::raw('excellent+1');
                $extra['excellent_qa'] = DB::raw('excellent_qa+1');
            } else if ($down >= 90 && $down <= 96) {// && $A == false
                $extra['good'] = DB::raw('good+1');
                $extra['good_qa'] = DB::raw('good_qa+1');
                $level = 1;
            }else if ($down >= 75 && $down <= 89) {// && $A == false
                $extra['middle'] = DB::raw('middle+1');
                $extra['middle_qa'] = DB::raw('middle_qa+1');
                $level = 2;
            } else {
                $extra['fail'] = DB::raw('fail+1');
                $extra['fail_qa'] = DB::raw('fail_qa+1');
                $level = 3;
            }
            $DepartmentQuery
                ->where('hospital_name','=',$hospitalName)
                ->where('id',$d_id)
                ->where('year',$year)
                ->where('month',$month)
                ->increment('total_medical',1,$extra);
        } else {
            $agd = [
                'hospital_name' => $hospitalName,
                'total_medical' => 1,
                'cumulative_medical' => $count > 0 ? 1 : 0,
                'error_medical' => $count > 0 ? 1 : 0,
                'total_score' => $down,
                'total_score_qa' => $down,
                'year' => $year,
                'month' => $month,
                'created_at' => $year.'-'.$month.'-'.'01 00:00:00',
            ];

            if ($down >= 97) {
                $agd['excellent'] = 1;
                $agd['excellent_qa'] = 1;
            } else if ($down >= 90 && $down <= 96) {
                $agd['good'] = 1;
                $agd['good_qa'] = 1;
                $level = 1;
            }else if ($down >= 75 && $down <= 89) {
                $agd['middle'] = 1;
                $agd['middle_qa'] = 1;
                $level = 2;
            } else {
                $agd['fail'] = 1;
                $agd['fail_qa'] = 1;
                $level = 3;
            }

            $DepartmentQuery->insert($agd);
        }
        return $level;
    }

    //缺陷问题
    public static function errorData( $errorList, $year, $month, $hospitalName) {
        foreach ($errorList as $item) {
//	        $key = $year.'_'.$month.'_'.$item['error_rule'];
//            $get = Cache::get($key);
//            if ($get === null) {
//                $id = ErrorData::query()->insertGetId([
//                    'error_rule'=>$item['error_rule'],
//                    'type'=>$item['type'],
//                    'year' => $year,
//                    'month' => $month,
//                    'count' => 1,
//                    'created_at' => $year.'-'.$month.'-'.'01 00:00:00',
//                ]);
//                Cache::put($key,$id);
//            } else {
//                $errorDataInfo = ErrorData::query()->where('id',$get)->first();
//                if ($errorDataInfo) {
//                    ErrorData::query()->where('id',$get)->increment('count');
//                } else {
//                    $id = ErrorData::query()->insertGetId([
//                        'error_rule'=>$item['error_rule'],
//                        'type'=>$item['type'],
//                        'year' => $year,
//                        'month' => $month,
//                        'count' => 1,
//                        'created_at' => $year.'-'.$month.'-'.'01 00:00:00',
//                    ]);
//                    Cache::forget($key);
//                    Cache::put($key,$id);
//                }
//            }

            $errorDataId = ErrorData::query()
                ->where('hospital_name','=',$hospitalName)
                ->where('error_rule','=',$item['error_rule'])
                ->where('year','=',$year)
                ->where('month','=',$month)
                ->value('id');
            if ($errorDataId) {
                ErrorData::query()->where('id','=',$errorDataId)->increment('count');
            } else {
                $insertData = [
                    'hospital_name' => $hospitalName,
                    'error_rule' => $item['error_rule'],
                    'type' => $item['type'],
                    'year' => $year,
                    'month' => $month,
                    'count' => 1,
                    'created_at' => $year.'-'.$month.'-'.'01 00:00:00',
                ];
                ErrorData::query()->insert($insertData);
            }
	    }
    }


}
