<?php

namespace App\Console\Commands;

use App\Model\Department;
use App\Model\QualitySendMsgLog;
use App\Model\ShizhongSyncZyh;
use App\Model\PatientHospitalInfo;
use App\Model\Staff;
use App\Services\CaseService;
use App\Services\HomeData;
use Illuminate\Console\Command;

class ShizhongDataSync24 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:shizhong24';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步事中质控相关的病例数据';

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

        ShizhongSyncZyh::query()->chunk(1000, function ($results) use ($con) {
            $zyh = [];
            foreach($results as $re){
                // 对每个用户进行相应的操作
                $zyh[] = $re->id;
            }

            if(!empty($zyh)){
                $ids = implode(',', $zyh);

                $sql = "SELECT CYPB,ZYH,ZYHM,BRKS,BRXM,ZZYS,ZSYS,to_char(RYRQ,'yyyy-mm-dd HH24:mi:ss') as RYRQ,to_char(CYRQ,'yyyy-mm-dd HH24:mi:ss') as CYRQ FROM PORTAL_HIS.ZY_BRRY WHERE ZYH in ($ids)";
                $result = oci_parse($con, $sql);
                oci_execute($result, OCI_DEFAULT);
                $data = [];
                while ($row = oci_fetch_assoc($result)) {
                    $data[] = $row;
                }
                if (empty($data)) {
                    return false;
                }

                $caseService = new CaseService();
                foreach ($data as $item) {

                    if ($item['CYPB'] == 99) {
                        // 如果病例信息进行过质控提醒，数据删除后将数据改为已删除
                        QualitySendMsgLog::query()->where('zyh', '=', $item['ZYH'])->update(['is_delete' => 0]);
                        ShizhongSyncZyh::query()->where(['id' => $item['ZYH']])->delete();
                        continue;
                    }
                    // 出院时间超过7天则删除事中质控的住院号列表
                    $cyrq = strtotime($item['CYRQ']);
                    if ($cyrq && time() - $cyrq > 7 * 24 * 3600) {
                        ShizhongSyncZyh::query()->where(['id' => $item['ZYH']])->delete();
                        continue;
                    }
                    $caseService->syncDataQuality($item['ZYH'], 1);
                }
            }

        });


    }

    /**
     * @return array
     * 获取所有未同步的住院号
     */
    public static function selectList()
    {
        $res = ShizhongSyncZyh::query()->where('status', '=', 0)->get()->toArray();
        return $res ? array_column($res, 'id') : [];
    }
}
