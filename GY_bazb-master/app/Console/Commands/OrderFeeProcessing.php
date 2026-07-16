<?php

namespace App\Console\Commands;

use App\Model\FeeDetailed;
use App\Model\Yzb;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderFeeProcessing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:order-fee-processing {type} {startTime?} {endTime?} {flag?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'yzb医嘱表、fee_detailed费用表';

    private $startTime;

    private $endTime;

    private $flag = true;

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
        $carbon = new Carbon();

        $type = data_get($this->arguments(),'type');
        $startTime = data_get($this->arguments(),'startTime','');
        $endTime = data_get($this->arguments(),'endTime','');
        $this->flag = data_get($this->arguments(),'flag',true);

        $this->startTime = $startTime ? $carbon::parse($startTime)->startOfDay()->format("Y-m-d H:i:s") : '';

        $this->endTime = $carbon::parse($endTime)->endOfDay()->format("Y-m-d H:i:s");

        $func = '_' . $type;
        $this->info($this->$func());
    }


    /**
     * @param string $method
     * @param array $parameters
     * @return string
     */
    public function __call($method, $parameters)
    {
        return "{$method}方法不存在！！";
    }

    /**
     *
     */
    private function _yzb(){
        $this->info("医嘱表｛ZYH｝字段为空数据处理::｛开始时间【{$this->startTime}】至【{$this->endTime}｝】");
        $where = [
//            'ZYH_ID' => '1001M3100000000248DH'
        ];
        Yzb::query()->whereNull('ZYH')->orWhere('ZYH','')->update(['SFQX' => 0]);
        //开始处理啊数据
        Yzb::query()->with('patient_info')->where($where)->where(function($query){
            return $query->whereNull('ZYH')->orWhere('ZYH','');
        })->when($this->flag,function($query){
            return $query->where('SFQX',0);
        })->when($this->startTime && $this->endTime,function($query){
            return $query->whereBetween('created_at',[$this->startTime,$this->endTime]);
        })->groupBy('ZYH_ID')->chunk(10000,function($item){

            collect($item)->map(function($item){
                //输出处理数据唯一标识
                $this->info("处理ID::{$item->id}|病案号::{$item->ZYH_ID}");

                //if(!$item->patient_info || !Yzb::where('ZYH_ID',$item->ZYH_ID)->update(['ZYH' => $item->patient_info->MED_REC_ID,'SFQX' => 1,'QXSJ' => date("Y-m-d H:i:s")])){
                //    Log::error("医嘱表ZYH字段为空处理失败::",compact('item'));
                //}
                if(!$item->patient_info){
                    Log::error("医嘱表ZYH字段为空处理失败::",compact('item'));
                }
                Yzb::where('ZYH_ID',$item->ZYH_ID)->update(['ZYH' => $item->patient_info ? $item->patient_info->MED_REC_ID : null,'SFQX' => 1,'QXSJ' => date("Y-m-d H:i:s")]);
            });

        });

        return "医嘱表ZYH字段为空处理完成";
    }


    /**
     *
     */
    private function _feeDetailed(){
        $this->info("费用表｛AAA28｝字段为空数据处理::｛开始时间【{$this->startTime}】至【{$this->endTime}｝】");

        $where = [];

        //开始处理啊数据
        FeeDetailed::query()->with('patient_info')->where($where)->where(function($query){
            return $query->whereNull('AAA28')->orWhere('AAA28','');
        })->when($this->flag,function($query){
            return $query->where('SFQX',0);
        })->when($this->startTime && $this->endTime,function($query){
            return $query->whereBetween('created_at',[$this->startTime,$this->endTime]);
        })->groupBy('ZYH_ID')->chunk(1000,function($item){

                collect($item)->map(function($item){
                    //输出处理数据唯一标识
                    $this->info("处理ID::{$item->id}|病案号::{$item->ZYH_ID}");

                    if(!$item->patient_info || !FeeDetailed::where('ZYH_ID',$item->ZYH_ID)->update(['AAA28' => $item->patient_info->MED_REC_ID,'SFQX' => 1,'QXSJ' => date("Y-m-d H:i:s")])){
                        Log::error("费用表AAA28字段为空处理失败::",compact('item'));
                    }
                });

            });

        return "费用表AAA28字段为空处理完成";
    }


    /**
     * 去重
     */
    private function _feeDetailedDeduplication(){
        $this->info("费用表｛FYXH｝字段重复数据处理::｛开始时间【{$this->startTime}】至【{$this->endTime}｝】");

        $where = [];

        //开始处理数据
        FeeDetailed::query()->select(DB::raw('count(1),FYXH,max(id) as id'))->where($where)
            ->when($this->startTime && $this->endTime,function($query){
                return $query->whereBetween('created_at',[$this->startTime,$this->endTime]);
            })->groupBy('ZYH_ID')->having('count(1)','>',1)->chunk(1000,function($item){

            collect($item)->map(function($item){
                //输出处理数据唯一标识
                $this->info("处理ID::{$item->id}|费用序号::{$item->FYXH}");

                FeeDetailed::query()->where('id','<>',$item->id)->where('FYXH',$item->FYXH)->delete();
            });

        });

        return "费用表FYXH字段重复问题处理完成";
    }
}
