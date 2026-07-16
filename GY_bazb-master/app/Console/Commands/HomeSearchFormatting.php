<?php

namespace App\Console\Commands;

use App\Model\PatientInfo;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class HomeSearchFormatting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:formatting {type} {startTime?} {endTime?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '处理病案首页查询数据格式问题';


    /**
     * @var string
     * 年龄只取数字规则
     */
    private static $preg_replace = '/[^0-9]/';

    private $checkField = 'AAA04_1';

    //key要分割的字符  value要修改的字段
    private static $type = ['岁' => 'AAA04','月' => 'AAA40','天' => 'AAA40','时' => 'AAA40','小时'=> 'AAA40'];

    private $startTime;

    private $endTime;

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
     * @retur bool|string|array
     */
    public function handle()
    {
        $carbon = new Carbon();

        $type = data_get($this->arguments(),'type');
        $startTime = data_get($this->arguments(),'startTime','');
        $endTime = data_get($this->arguments(),'endTime','');

        $this->startTime = $startTime ? $carbon::parse($startTime)->startOfDay()->format("Y-m-d H:i:s") : '';

        $this->endTime = $carbon::parse($endTime)->endOfDay()->format("Y-m-d H:i:s");

        $func = '_' . $type;
        $this->info($this->$func());
    }


    public function __call($method, $parameters)
    {
        return "{$method}方法不存在！！";
    }


    /**
     * @return bool
     * 年龄格式化处理
     */
    private function _formattingAge(){
        $this->info("年龄｛AAA04_1｝格式化处理开始::｛开始时间【{$this->startTime}】至【{$this->endTime}｝】");

        $where = [
            //'MED_REC_ID' => '110662671'
        ];

        //开始处理啊数据
        PatientInfo::query()->where($where)->where(function($query){

            return $query->whereNull(self::$type[array_key_first(self::$type)])
                ->orWhere(self::$type[array_key_first(self::$type)],'')->orWhere(self::$type[array_key_first(self::$type)],0);

        })->when($this->startTime && $this->endTime,function($query){

            return $query->whereBetween('created_at',[$this->startTime,$this->endTime]);

        })->chunkById(1000,function($datas){

            collect($datas)->map(function($item){
                //输出处理数据唯一标识
                $this->info("处理ID::{$item->id}|病案号::{$item->MED_REC_ID}");

                $checkFieldInfo = data_get($item, $this->checkField); //年龄

                //要修改的参数
                $update_array = $this->getAgeField($checkFieldInfo);

                if(empty($update_array)){
                    data_set($update_array,self::$type[array_key_first(self::$type)],0);
                    Log::error(__METHOD__ . '年龄处理失败::',compact('item','checkFieldInfo'));
                }
                if(!PatientInfo::where('id',$item->id)->update($update_array)) Log::error(__METHOD__ . '年龄处理保存失败::',compact('item','checkFieldInfo','update_array'));
            });

        },'id');

        return "年龄格式化处理完成";
    }

    /**
     * @param $value
     * @return array
     * 获取年龄需修改字段
     */
    private function getAgeField($value){
        $update_arr = [];
        foreach (self::$type as $key => $type) {
            if(stripos($value,$key) !== false){
                $arrays = explode($key,$value);
                //只取整数
                $age = (int)preg_replace(self::$preg_replace, '', data_get($arrays,0,0));

                switch ($key){
                    case '月':
                        $age = bcmul($age,30);
                        break;
                    case '时':
                        $age = (int)($age >= 24 ? intdiv ($age,24) : 0);
                        break;
                }

                !isset($update_arr[$type]) && data_set($update_arr,$type,$age);

                //天时小时转换天数 + 上月的天数  不需要注释即可
                if(in_array($key,['天','时','小时'])) $update_arr[$type] += $age;

                array_key_last(self::$type) !== $key && $value = data_get($arrays,1);
            }
        }
        return $update_arr;
    }
}
