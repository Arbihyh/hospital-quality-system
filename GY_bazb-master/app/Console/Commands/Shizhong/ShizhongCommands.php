<?php

namespace App\Console\Commands\Shizhong;

use App\Jobs\AsynHomeData;
use App\Model\CaseQualityV2;

use App\Model\Department;
use App\Model\QualitySendMsgLog;
use App\Model\ShizhongSyncZyh;
use App\Model\PatientHospitalInfo;
use App\Model\Staff;
use App\Services\HomeData;
use App\Model\RuleWordMap;
use App\Services\ShizhongService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ShizhongCommands extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shizhong:new';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '事中质控-入院记录';

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
    public function handle()
    {
        $this->ZY_BRRY();
    }

    /**
     * @return bool
     * 获取近24小时内的病例信息，用于循环检查病例的时效性质控
     */
    public function ZY_BRRY($zyh = '')
    {
        $username = 'zdyh';
        $password = 'zdyh';
        $connection = '172.16.2.1';
        $port = '1521';
        $tns = 'his';
        $con = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $start = date('YmdHis', time() - 24 * 3600);
        $end = date('YmdHis', time());
        //$sql = "SELECT CYPB,ZYH,ZYHM,BRKS,BRXM,ZZYS,ZSYS,to_char(RYRQ,'yyyy-mm-dd HH24:mi:ss') as RYRQ,to_char(CYRQ,'yyyy-mm-dd HH24:mi:ss') as CYRQ FROM PORTAL_HIS.ZY_BRRY WHERE RYRQ BETWEEN to_date(" . $start . ",'yyyy-MM-dd HH24:mi:ss') AND to_date(" . $end . ",'yyyy-MM-dd HH24:mi:ss')";
        $sql = "SELECT CYPB,ZYH,ZYHM,BRKS,BRXM,ZZYS,ZSYS,to_char(RYRQ,'yyyy-mm-dd HH24:mi:ss') as RYRQ,to_char(CYRQ,'yyyy-mm-dd HH24:mi:ss') as CYRQ FROM PORTAL_HIS.ZY_BRRY WHERE CYRQ IS NULL";
        $result = oci_parse($con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }

        //所有添加的质控规则
        $wanchengshijian = RuleWordMap::query()->where('name', '完成时间')->first();
        $homeData = new HomeData();
        $shizhongService = new ShizhongService();
        foreach ($data as $item) {

            if ($item['CYPB'] == 99) {
                // 如果病例信息进行过质控提醒，数据删除后将数据改为已删除
                QualitySendMsgLog::query()->where('zyh', '=', $item['ZYH'])->update(['is_delete' => 0]);
                // 删除事中的住院号
                ShizhongSyncZyh::query()->where(['id' => $item['ZYH']])->update(['status' => 1]);
                continue;
            }

            $hisData = $homeData->getOnlyData($item['ZYH'], ['yzb']);
            if (empty($hisData['yzb'])) {
                continue;
            }

            $yzbData = [];
            foreach ($hisData['yzb'] as $yzbItem) {

                $kjTime = strtotime($yzbItem['KJ']);
                if ($kjTime <= time() - 3600 * 48 || $kjTime > time() - 3600 * 20 || (!empty($yzbItem['DSG_OPERATION']) && $yzbItem['DSG_OPERATION'] == 'D')) {
                   continue;
                }

                $yzbData[] = $yzbItem;
            }

            $kjyYz = $shizhongService->isKjy($yzbData);

            if(empty($kjyYz)){
                continue;
            }

            ShizhongSyncZyh::query()->updateOrInsert(['id' => $item['ZYH']], ['id' => $item['ZYH'], 'AAB01' => $item['RYRQ']]);

            $hisData = $homeData->getOnlyData($item['ZYH'], ['bl01']);
            $hisData['yzb'] = $kjyYz;


            $brryData = [
                'MED_REC_ID' => $item['ZYH'],
                'AAA28' => $item['ZYHM'],
                'AAB01' => $item['RYRQ'],
                'AAC01' => $item['CYRQ'],
                'AAA01' => $item['BRXM'] ? desensitize($item['BRXM'], 1, 1, '*') : '',        //患者姓名
            ];

            // 科室信息
            $dep = Department::query()->where('dep_id', '=', $item['BRKS'])->get()->toArray();
            $dep = $dep ? $dep[0] : [];
            \App\Model\PatientInfo::query()->updateOrInsert(['MED_REC_ID' => $item['ZYH']], $brryData);
            PatientHospitalInfo::query()->updateOrInsert(['AAA28' => $item['ZYH']], ['AAA28' => $item['ZYH'], 'AAB11N' => $dep ? $dep['dep_name'] : '']);

            $code[] = $item['ZSYS'] ?: '';
            $code[] = $item['ZZYS'] ?: '';
            $code = array_filter($code);
            $staff = Staff::query()->whereIn('code', $code)->get()->toArray();
            $staff = array_column($staff, null, 'code');

            \App\Model\PatientDoctorInfo::query()->updateOrInsert(
                ['AAA28' => $item['ZYH']],
                [
                    'AEE03' => !empty($staff[$item['ZZYS']]) ? $staff[$item['ZZYS']]['name'] : '',
                    'AEE03_CODE' => $item['ZZYS'],
                    'AEE01' => !empty($staff[$item['ZSYS']]) ? $staff[$item['ZSYS']]['name'] : '',
                    'AEE01_CODE' => $item['ZSYS']
                ]
            );

            //异步更新入库
            AsynHomeData::dispatch(['yzb' => $hisData['yzb'], 'bl01' => $hisData['bl01'][$item['ZYH']] ?? '', 'zyh'=>$item['ZYH']])->onQueue('asynHomeData');

            $checkKjy = $shizhongService->kjy2($hisData + ['ZYH' => $item['ZYH']] + ['wanchengshijian' => $wanchengshijian->keyword]);

            $errors = [];
            if(!empty($checkKjy)){
                $errors[] = $checkKjy;
            }

            foreach ($errors as $v) {
                CaseQualityV2::addData($v);
            }

        }

    }


}
