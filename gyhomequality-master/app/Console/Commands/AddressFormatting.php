<?php

namespace App\Console\Commands;

use App\Model\Error;
use App\Model\PatientAddressInfo;
use App\Model\RuleWordMap;
use App\Model\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AddressFormatting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'addressFormatting {type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        echo  "地址格式化开始\n";
        $type = $this->argument('type');

        //出生地清洗
        if ($type === 'csd') $this->csdAddress();

        //籍贯清洗
        if ($type === 'gg')$this->ggAddress();

        //户籍清洗
        if ($type === 'hj') $this->hjAddress();

        //现住址
        if ($type === 'xzz') $this->xzzAddress();

        echo "处理完成";
    }

    /**
     * 户籍清洗
     */
    public function hjAddress()
    {
        $setName = 'formatting_patient_address_info_hj';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        //region 获取正则
        //获取省正则
        $provinceRegexp = RuleWordMap::query()->where('id','=',4021)->get(['keyword'])->first()['keyword'];
        //获取市正则
        $cityRegexp = RuleWordMap::query()->where('id','=',4022)->get(['keyword'])->first()['keyword'];
        //获取区正则
        $areaRegexp = RuleWordMap::query()->where('id','=',4023)->get(['keyword'])->first()['keyword'];
        //endregion

        $currentTime = date("Y-m-d H:i:s");//获取当前时间
        //region 循环处理--
        while (true){
            $data = PatientAddressInfo::query()
                ->whereNull('AAA45')
                ->whereNull('AAA46')
                ->whereNull('AAA47')
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->limit(1000)
                ->get(['id', 'AAA12']);
            if ($data->isEmpty()) break;
            $data = $data->toArray();

            //循环处理data
            foreach ($data as $v)
            {
                $lastId = $v['id'];
                $address = $v['AAA12'];//户籍地址
                $update = [];
                //处理省级
                $provinceResult = $this->parseAndTrimAddress($address,$provinceRegexp);
                $update['AAA45'] = $provinceResult['result'];
                //处理市级
                $cityResult = $this->parseAndTrimAddress($provinceResult['address'],$cityRegexp);
                $update['AAA46'] = $cityResult['result'];
                //处理区级
                $areaResult = $this->parseAndTrimAddress($cityResult['address'],$areaRegexp);
                $update['AAA47'] = $areaResult['result'];
                $update['xzz_qx_status'] = 1;
                $update['xzz_qx_time'] = $currentTime;
                $result = PatientAddressInfo::query()->where('id','=',$v['id'])->update($update);
                if ($result === 0) {
                    Log::error("户籍更新失败::id->'{$v['id']}'");
                }else{
                    echo "处理成功id:{$v['id']}\n";
                }
            }
            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
        //endregion
        Setting::query()->where('name','=',$setName)->update(['content'=>0]);
        echo "户籍清洗完成\n";
    }

    /**
     * 现住址清洗
     */
    public function xzzAddress()
    {
        $setName = 'formatting_patient_address_info_xzz';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        //region 获取正则
        //获取省正则
        $provinceRegexp = RuleWordMap::query()->where('id','=',4031)->get(['keyword'])->first()['keyword'];
        //获取市正则
        $cityRegexp = RuleWordMap::query()->where('id','=',4032)->get(['keyword'])->first()['keyword'];
        //获取区正则
        $areaRegexp = RuleWordMap::query()->where('id','=',4033)->get(['keyword'])->first()['keyword'];
        //endregion

        $currentTime = date("Y-m-d H:i:s");//获取当前时间
        //region 循环处理--
        while (true){
            $data = PatientAddressInfo::query()
                ->whereNull('AAA48')
                ->whereNull('AAA49')
                ->whereNull('AAA50')
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->limit(1000)
                ->get(['id', 'AAA15']);
            if ($data->isEmpty()) break;
            $data = $data->toArray();

            //循环处理data
            foreach ($data as $v)
            {
                $lastId = $v['id'];
                $address = $v['AAA15'];//现住址地址
                $update = [];
                //处理省级
                $provinceResult = $this->parseAndTrimAddress($address,$provinceRegexp);
                $update['AAA48'] = $provinceResult['result'];
                //处理市级
                $cityResult = $this->parseAndTrimAddress($provinceResult['address'],$cityRegexp);
                $update['AAA49'] = $cityResult['result'];
                //处理区级
                $areaResult = $this->parseAndTrimAddress($cityResult['address'],$areaRegexp);
                $update['AAA50'] = $areaResult['result'];
                $update['hj_qx_status'] = 1;
                $update['hj_qx_time'] = $currentTime;
                $result = PatientAddressInfo::query()->where('id','=',$v['id'])->update($update);
                if ($result === 0) {
                    Log::error("现住址清洗失败::id->'{$v['id']}'");
                }else{
                    echo "处理成功id:{$v['id']}\n";
                }
            }
            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
        //endregion
        Setting::query()->where('name','=',$setName)->update(['content'=>0]);
        echo "现住址清洗完成\n";
    }

    /**清洗出生地
     * @return void
     */
    public function csdAddress()
    {
        $setName = 'formatting_patient_address_info_csd';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        //region 获取正则
        //获取省正则
        $provinceRegexp = RuleWordMap::query()->where('id','=',4041)->get(['keyword'])->first()['keyword'];
        //获取市正则
        $cityRegexp = RuleWordMap::query()->where('id','=',4042)->get(['keyword'])->first()['keyword'];
        //endregion

        $currentTime = date("Y-m-d H:i:s");//获取当前时间
        //region 循环处理--
        while (true){
            $data = PatientAddressInfo::query()
                ->whereNull('AAA09')
                ->whereNull('AAA10')
                ->whereNull('AAA11')
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->limit(1000)
                ->get(['id', 'CSD']);

           if ($data->isEmpty()) break;
            $data = $data->toArray();
            //循环处理data
            foreach ($data as $v)
            {
                $lastId = $v['id'];
                $address = $v['CSD'];//户籍地址
                $update = [];
                //处理省级
                $provinceResult = $this->parseAndTrimAddress($address,$provinceRegexp);
                $update['AAA09'] = $provinceResult['result'];
                //处理市级
                $cityResult = $this->parseAndTrimAddress($provinceResult['address'],$cityRegexp);
                $update['AAA10'] = $cityResult['result'];
                $update['AAA11'] = $cityResult['address'];
                $update['csd_qx_status'] = 1;
                $update['csd_qx_time'] = $currentTime;
                $result = PatientAddressInfo::query()->where('id','=',$v['id'])->update($update);
                if ($result === 0) {
                    Log::error("出生地清洗失败::id->'{$v['id']}'");
                }else{
                    echo "处理成功id:{$v['id']}\n";
                }
            }
            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
        //endregion
        Setting::query()->where('name','=',$setName)->update(['content'=>0]);
        echo "出生地清洗完成\n";
    }

    /**
     * 清洗籍贯
     */
    public function ggAddress()
    {

        $setName = 'formatting_patient_address_info_gg';
        $lastId = Setting::query()->where('name', '=', $setName)->value('content');
        $lastId = $lastId ?: 0;

        //region 获取正则
        //获取省正则
        $provinceRegexp = RuleWordMap::query()->where('id','=',4011)->get(['keyword'])->first()['keyword'];
        //获取市正则
        $cityRegexp = RuleWordMap::query()->where('id','=',4012)->get(['keyword'])->first()['keyword'];
        //endregion

        $currentTime = date("Y-m-d H:i:s");//获取当前时间

        //开始循环
        while (true){
            $data = PatientAddressInfo::query()
                ->whereNull('AAA43')
                ->whereNull('AAA44')
                ->where('id','>',$lastId)
                ->orderBy('id')
                ->limit(1000)
                ->get(['id', 'GG']);
            if ($data->isEmpty()) break;
            $data = $data->toArray();

            //循环处理data
            foreach ($data as $v)
            {
                $lastId = $v['id'];
                $address = $v['GG'];//户籍地址
                $update = [];
                //处理省级
                $provinceResult = $this->parseAndTrimAddress($address,$provinceRegexp);
                $update['AAA43'] = $provinceResult['result'];
                //处理市级
                $cityResult = $this->parseAndTrimAddress($provinceResult['address'],$cityRegexp);
                $update['AAA44'] = $cityResult['result'];
                $update['gg_qx_status'] = 1;
                $update['gg_qx_time'] = $currentTime;
                $result = PatientAddressInfo::query()->where('id','=',$v['id'])->update($update);
                if ($result === 0) {
                    Log::error("籍贯清洗失败::id->'{$v['id']}'");
                }else{
                    echo "处理成功id:{$v['id']}\n";
                }
            }
            Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$lastId]);
        }
        Setting::query()->where('name','=',$setName)->update(['content'=>0]);
        echo "籍贯清洗完成\n";
    }


    /** 清洗地址
     * @params string $address
     * @param string $regexp
     * return array
     */
    protected function parseAndTrimAddress($address,$regexp)
    {
        preg_match("{$regexp}",$address,$matches);
        $result = isset($matches[1]) ? $matches[1] : '';
        if (!empty($result))
        {
            $address = preg_replace('/' . preg_quote($result, '/') . '/', '', $address, 1);
        }
        return ['result'=>$result,'address'=>$address];
    }
}
