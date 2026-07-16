<?php

namespace App\Console\Commands\Shizhong;

use App\Model\CaseQualityV2;
use App\Model\CaseRule;
use App\Model\Department;
use App\Model\QualitySendMsgLog;
use App\Model\ShizhongSyncZyh;
use App\Model\PatientHospitalInfo;
use App\Model\Staff;
use App\Services\BanhzkgzService;
use App\Services\CaseService;
use App\Services\HomeData;
use App\Services\ToolsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class Ryjl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shizhong:ryjl';

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
        $sql = "SELECT CYPB,ZYH,ZYHM,BRKS,BRXM,ZZYS,ZSYS,to_char(RYRQ,'yyyy-mm-dd HH24:mi:ss') as RYRQ,to_char(CYRQ,'yyyy-mm-dd HH24:mi:ss') as CYRQ FROM PORTAL_HIS.ZY_BRRY WHERE RYRQ BETWEEN to_date(".$start.",'yyyy-MM-dd HH24:mi:ss') AND to_date(".$end.",'yyyy-MM-dd HH24:mi:ss')";
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
        $caseRule = CaseRule::query()->get()->toArray();
        $caseRule = array_column($caseRule, null, 'id');
        $homeData = new HomeData();
        $caseService = new CaseService();
        $caseService->is_sz = 1;
        $banhzkgzService = new BanhzkgzService();
        $banhzkgzService->is_sz = 1;
        foreach ($data as $item) {

            ShizhongSyncZyh::query()->updateOrInsert(['id' => $item['ZYH']], ['id' => $item['ZYH']]);
            if ($item['CYPB'] == 99) {
                // 如果病例信息进行过质控提醒，数据删除后将数据改为已删除
                QualitySendMsgLog::query()->where('zyh', '=', $item['ZYH'])->update(['is_delete' => 0]);
                // 删除事中的住院号
                ShizhongSyncZyh::query()->where(['id' => $item['ZYH']])->delete();
                continue;
            }
            // 科室信息
            $dep = Department::query()->where('dep_id', '=', $item['BRKS'])->get()->toArray();
            $dep = $dep ? $dep[0] : [];
            $insertData = [
                'MED_REC_ID' => $item['ZYH'],
                'AAA28' => $item['ZYHM'],
                'AAB01' => $item['RYRQ'],
                'AAC01' => $item['CYRQ'],
                'AAA01' => $item['BRXM'] ? desensitize($item['BRXM'], 1, 1, '*') : '',        //患者姓名
            ];

            \App\Model\PatientInfo::query()->updateOrInsert(['MED_REC_ID' => $item['ZYH']], $insertData);
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

            $res = $homeData->getData($item['ZYH'], ['bl01']);
            if ($res === false) {
                continue;
            }

            $res = [];
            $res[] = $caseService->checkRy8($insertData);
            $banhzkgzService->rule99($caseRule, 99, $item['ZYH']);
            $res = array_merge($res, $banhzkgzService->insertData);
            foreach ($res as $v) {
                CaseQualityV2::addData($v);
            }

        }

    }



}
