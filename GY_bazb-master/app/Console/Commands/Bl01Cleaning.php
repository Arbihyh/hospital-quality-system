<?php

namespace App\Console\Commands;

use App\Model\EMR_BL_BL01;
use App\Model\PatientInfo;
use App\Model\RuleWordMap;
use App\Model\Setting;
use App\Services\BlDataFormatService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Bl01Cleaning extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bl01-table-cleaning {type?} {extend_param?} {extend_tow_param?} {extend_three_param?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'BL01表数据清洗';


    /**
     * MBLB && BLLB 对照表
     * @var \string[][]
     */
    private $groupArray = [
        '1001M310000000001BDS' => ['MBLB' => '2000001', 'BLLB' => '2000001'],
        '1001M310000000001BJU' => ['MBLB' => '292', 'BLLB' => '292'],
        '1001M310000000001BJV' => ['MBLB' => '292', 'BLLB' => '292'],
        '1001M310000000001BL4' => ['MBLB' => '1', 'BLLB' => '1'],
        '1001M310000000001BL5' => ['MBLB' => '288', 'BLLB' => '288'],
        '1001M310000000001BJW' => ['MBLB' => '20', 'BLLB' => '18'],
        '1001M310000000001BJX' => ['MBLB' => '21', 'BLLB' => '18'],
        '1001M310000000001BK6' => ['MBLB' => '295', 'BLLB' => '294'],
        '1001M310000000001BK7' => ['MBLB' => '50', 'BLLB' => '294'],
        '1001M310000000001BKE' => ['MBLB' => '50', 'BLLB' => '294'],
        '1001M310000000001BK8' => ['MBLB' => '296', 'BLLB' => '294'],
        '1001M310000000001BKD' => ['MBLB' => '296', 'BLLB' => '294'],
        '1001M310000000001BK5' => ['MBLB' => '296', 'BLLB' => '294'],
        '1001M310000000001BKC' => ['MBLB' => '45', 'BLLB' => '294'],
        '1001M310000000001BKI' => ['MBLB' => '511', 'BLLB' => '294'],
        '1001M310000000001BKG' => ['MBLB' => '511', 'BLLB' => '294'],
        '1001M310000000001BKM' => ['MBLB' => '27', 'BLLB' => '294'],
        '1001M310000000001BKK' => ['MBLB' => '30', 'BLLB' => '294'],
        '1001M310000000001BKJ' => ['MBLB' => '30', 'BLLB' => '294'],
        '1001M310000000001BKL' => ['MBLB' => '26', 'BLLB' => '294'],
        '1001M310000000001BKU' => ['MBLB' => '515', 'BLLB' => '294'],
        '1001M310000000001BK9' => ['MBLB' => '54', 'BLLB' => '294'],
        '1001M310000000001BKO' => ['MBLB' => '54', 'BLLB' => '294'],
        '1001M310000000001BKP' => ['MBLB' => '42', 'BLLB' => '294'],
        '1001M310000000001BKV' => ['MBLB' => '82', 'BLLB' => '294'],
        '1001M310000000001BKS' => ['MBLB' => '32', 'BLLB' => '294'],
        '1001M310000000001BKR' => ['MBLB' => '32', 'BLLB' => '294'],
        '1001M310000000001BL6' => ['MBLB' => '4302', 'BLLB' => '43'],
        '1001M310000000001BKA' => ['MBLB' => '44', 'BLLB' => '43'],
        '1001M310000000001BKY' => ['MBLB' => '306', 'BLLB' => '303'],
        '1001M310000000001BL1' => ['MBLB' => '40', 'BLLB' => '303'],
        '1001M310000000001BL2' => ['MBLB' => '30303', 'BLLB' => '303'],
        '1001M310000000001BL0' => ['MBLB' => '30375', 'BLLB' => '303'],
        '1001M310000000001BKW' => ['MBLB' => '304', 'BLLB' => '303'],
        '1001M310000000001BKN' => ['MBLB' => '30304', 'BLLB' => '303'],
        '1001M310000000001BL8' => ['MBLB' => '8', 'BLLB' => '329'],
        '1001M310000000001BL9' => ['MBLB' => '77', 'BLLB' => '329'],
        '1001M310000000001BLA' => ['MBLB' => '59', 'BLLB' => '329'],
        '1001M310000000001BLD' => ['MBLB' => '60', 'BLLB' => '329'],
        '1001M310000000001BKX' => ['MBLB' => '32977', 'BLLB' => '329'],
        '1001M310000000001BLB' => ['MBLB' => '200018501', 'BLLB' => '2000185'],

        //第二次新增
        '1001M310000000001BLC' => ['MBLB' => '62', 'BLLB' => '329'],
        '1001M310000000001BM7' => ['MBLB' => '7901', 'BLLB' => '79'],
        '1001M310000000006MO8' => ['MBLB' => '7904', 'BLLB' => '79'],
        '1001M310000000001BM8' => ['MBLB' => '3402', 'BLLB' => '34'],
        '1001M310000000001BM9' => ['MBLB' => '10409', 'BLLB' => '104'],
    ];

    private $extend_param;

    private $extend_tow_param;

    private $extend_three_param;

    /**
     * 清洗规则ID
     * @var string
     */
    protected $stripos_id = '4038';


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
        $type = data_get($this->arguments(), 'type');
        $this->extend_param = data_get($this->arguments(), 'extend_param');
        $this->extend_tow_param = data_get($this->arguments(), 'extend_tow_param');
        $this->extend_three_param = data_get($this->arguments(), 'extend_three_param');
        $func = '_' . $type;
        $this->info($this->$func());
    }


    public function __call($method, $parameters)
    {
        return "{$method}方法不存在！！";
    }

    /**
     * $groupArray 第一次清洗规则映射表
     * 清洗MBLB 和 BLLB 类别字段
     */
    private function _cleaning_lb()
    {

        //查询MBLB为空数据
        $query = EMR_BL_BL01::query()->where('MBLB', 0)->whereNull('BLLB')->orderBy('id');
        $this->info("按映射字段清洗MBLB&&BLLB数据");
        while ($query->count() > 0) {

            collect($query->limit(10000)->get())->map(function ($item) {
                $this->info('BLBH:' . $item->BLBH);

                $update_array = data_get($this->groupArray, $item->MBLB3, []);
                if (empty($update_array)) data_set($update_array, 'BLLB', 0);

                EMR_BL_BL01::query()->where('BLBH', $item->BLBH)->update($update_array);
            });
        }

        return $this->info('清洗完成');
    }


    private function _cleaning_lb_extend()
    {

        //获取清洗规则 需查询字符串
        $keyword = RuleWordMap::where('id', $this->stripos_id)->value('keyword');
        $keyword_array = json_decode($keyword, true);

        if (empty($keyword_array)) {
            $this->info("清洗规则不存在或为空");
            return false;
        }

        $stripos_key = array_keys($keyword_array);

        $query = EMR_BL_BL01::query()->where('MBLB', $this->extend_param)
            ->where('is_cleaning_stripos', 0)->orderBy('id');

        $this->info("start时间::" . date("Y-m-d H:i:s"));
        $this->info("开始清洗数据,MBLB:{$this->extend_param},规则ID:$this->stripos_id");

        while ($query->count() > 0) {
            $res = $query->limit(10000)->get()->toArray();
            foreach ($res as $val) {

                $update_array = [];
                foreach ($stripos_key as $item) {
                    if (stripos($val['BLMC'], $item) !== false) {
                        data_set($update_array, 'MBLB', $val[$keyword_array[$item]['key']] . $keyword_array[$item]['value']);
                        if ($this->extend_param == 292) {
                            data_set($update_array, 'BLLB', $val[$keyword_array[$item]['key']] . $keyword_array[$item]['value']);
                        }
                        break;
                    }
                }

                EMR_BL_BL01::query()->where('BLBH', $val['BLBH'])->update(array_merge(['is_cleaning_stripos' => 1], $update_array));
            }
        }

        $this->info("end时间::" . date("Y-m-d H:i:s"));

        return $this->info('清洗完成');
    }


    /**
     * 清洗入院、出院、输血、手术、剖宫产、分娩记录、首次病程记录等
     */
    private function _cleaning_bl_data()
    {
        $this->info("start时间::" . date("Y-m-d H:i:s"));
        $this->info('开始执行病历内容等清洗脚本');

        PatientInfo::query()->where('is_cleaning_bl_data', 0)
            //            ->where('ZYH_ID','1001M3100000000277X8')
            ->chunkById(10000, function ($datas) {
                $object = new BlDataFormatService();
                collect($datas)->map(function ($item) use ($object) {

                    //                $this->info('ZYH_ID::' . $item->MED_REC_ID);
                    $object->formatBlData($item->MED_REC_ID, $this->extend_param);

                    PatientInfo::query()->where('id', $item->id)->update(['is_cleaning_bl_data' => 1]);
                });
            });
        $this->info("end时间::" . date("Y-m-d H:i:s"));
        return $this->info("执行结束");
    }

    /**
     * 清洗入院、出院、输血、手术、剖宫产、分娩记录、首次病程记录等
     */
    private function _cleaning_bl_data_extend()
    {
        $this->info("start时间::" . date("Y-m-d H:i:s"));
        $this->info('开始执行病历内容等清洗脚本');

        PatientInfo::query()->select(DB::raw('patient_info.*'))->leftJoin('bllb294_295', 'bllb294_295.ZYH', '=', 'patient_info.MED_REC_ID')
            ->whereNull('bllb294_295.ZYH')
            //            ->where('is_cleaning_bl_data',0)
            //            ->where('ZYH_ID','1001M3100000000277X8')
            ->chunkById(10000, function ($datas) {
                $object = new BlDataFormatService();
                collect($datas)->map(function ($item) use ($object) {

                    //                $this->info('ZYH_ID::' . $item->MED_REC_ID);
                    $object->formatBlData($item->MED_REC_ID, 295);

                    PatientInfo::query()->where('id', $item->id)->update(['is_cleaning_bl_data' => 1]);
                });
            }, 'patient_info.id');
        $this->info("end时间::" . date("Y-m-d H:i:s"));
        return $this->info("执行结束");
    }

    /**
     * 获取病历对应审签等级的签名时间
     */
    private function _cleaning_first_blsy_time()
    {

        //获取表中已跑数据ID
        $setting_info = Setting::query()->where('name', 'cleaning_first_blsy_time')->firstOrNew();

        $carbon = new Carbon();
        $startTime = $this->extend_param ? $carbon::parse($this->extend_param)->format("Y-m-d 00:00:00") : '';
        $endTime = $this->extend_tow_param ? $carbon::parse($this->extend_tow_param)->format("Y-m-d 23:59:59") : '';

        $this->info('first_blsy_time清洗开始::' . $carbon::now()->format("Y-m-d H:i:s"));

        if (!($odsServer = self::getOdsServer())) {
            $this->info('ODS-Server服务链接失败');
            return false;
        }

        EMR_BL_BL01::query()->when($startTime && $endTime, function ($query) use ($startTime, $endTime) {
            return $query->whereBetween('WCSJ', [$startTime, $endTime]);
        })
            //            ->where('id',1)
            ->where('id', '>', ($setting_info ? $setting_info->content : 0))
            ->chunkById(1000, function ($datas) use ($setting_info, $odsServer) {
                $last_id = $setting_info ? $setting_info->content : 0;

                foreach ($datas->toArray() as $key => $val) {
                    var_dump('blbh->' . $val['BLBH']);
                    //根据BL01表中BLBH查询 ODS 中审签时间
                    $first_blsy_time = $this->getBlInspectionTime($odsServer, $val['BLBH']);
                    var_dump('first_blsy_time->' . $first_blsy_time);
                    //$first_blsy_time = data_get($res, '0.dt_audit_op', '');
                    if (empty($first_blsy_time) || !$first_blsy_time) continue;

                    EMR_BL_BL01::query()->where('id', data_get($val, 'id'))->update(compact('first_blsy_time'));

                    $last_id = data_get($val, 'id');
                }

                if ($last_id > 0) {
                    var_dump('last_id->' . $last_id);
                    $setting_info->name = 'cleaning_first_blsy_time';
                    $setting_info->content = $last_id;
                    //$setting_info->created_at = date("Y-m-d H:i:s");
                    $setting_info->updated_at = date("Y-m-d H:i:s");
                    $setting_info->save();
                }
            });

        //关闭ODS Server服务
        oci_close($odsServer);
        $this->info("first_blsy_time清洗完成::" . $carbon::now()->format("Y-m-d H:i:s"));
    }

    /**
     * 获取First_blsy_time数据
     */

    public function getBlInspectionTime($odsServer, $BLBH)
    {
        // 查询语句
        $first_blsy_time = '';
        $blsysql = "SELECT NVL(IIH.CI_MR_SIGN.ID_MRSIGN, '') AS JLXH, NVL(IIH.CI_MR_SIGN.ID_MR, '') AS BLBH, NVL(IIH.CI_MR_SIGN.ID_EMP_SIGN, '') AS SYYS, NVL(IIH.CI_MR_SIGN.DT_SIGN, '') AS SYSJ, NVL(IIH.CI_MR_SIGN.CREATEDTIME, '') AS JLSJ, NVL(IIH.CI_MR_SIGN.MODIFIEDTIME, '') AS updated_at FROM IIH.CI_MR_SIGN WHERE CI_MR_SIGN.ID_MR = '{$BLBH}' ORDER BY CI_MR_SIGN.CREATEDTIME ASC"; //按照jlsj时间升序
        $blsydata = [];
        $resultblsy = oci_parse($odsServer, $blsysql);
        oci_execute($resultblsy, OCI_DEFAULT);
        while ($row = oci_fetch_assoc($resultblsy)) {
            $blsydata[] = $row;
        }

        $sql = "SELECT DISTINCT CI_MR_SIGN.DT_SIGN AS first_blsy_time FROM IIH.CI_MR_SIGN LEFT JOIN IIH.CI_MR ON IIH.CI_MR.ID_MR = IIH.CI_MR_SIGN.ID_MR LEFT JOIN IIH.BD_MR_LVL ON IIH.BD_MR_LVL.ID_MR_SIGN_TYPE = IIH.CI_MR.ID_MR_SIGNLVL AND IIH.BD_MR_LVL.ID_DEP_LVL = IIH.CI_MR.ID_DEP_PAT LEFT JOIN IIH.BD_MR_LVL_ITM_EMP ON IIH.BD_MR_LVL_ITM_EMP.ID_LVL_EMP = IIH.CI_MR_SIGN.ID_EMP_SIGN LEFT JOIN IIH.BD_MR_LVL_ITM ON IIH.BD_MR_LVL_ITM.ID_MR_LVL_ITM = IIH.BD_MR_LVL_ITM_EMP.ID_MR_LVL_ITM AND IIH.BD_MR_LVL_ITM.NAME = CASE WHEN IIH.CI_MR.SD_MR_SIGNLVL = '01' THEN '一级审签' WHEN IIH.CI_MR.SD_MR_SIGNLVL = '02' THEN '二级审签' WHEN IIH.CI_MR.SD_MR_SIGNLVL = '03' THEN '三级审签' ELSE IIH.CI_MR.SD_MR_SIGNLVL  END WHERE IIH.BD_MR_LVL_ITM.NAME IS NOT NULL AND IIH.CI_MR.ID_MR = '{$BLBH}' ORDER BY IIH.CI_MR_SIGN.DT_SIGN ASC";
        $result1 = oci_parse($odsServer, $sql);
        oci_execute($result1, OCI_DEFAULT);
        $data1 = [];
        while ($row = oci_fetch_assoc($result1)) {
            $data1[] = $row;
        }
        $lvsql = "SELECT CI_MR.SD_MR_SIGNLVL FROM IIH.CI_MR WHERE IIH.CI_MR.ID_MR = '{$BLBH}'";
        $lvdata = oci_parse($odsServer, $lvsql);
        oci_execute($lvdata, OCI_DEFAULT);
        $resultlv = [];
        while ($row = oci_fetch_assoc($lvdata)) {
            $resultlv[] = $row;
        }
        $level = $resultlv[0]['SD_MR_SIGNLVL'];


        if ($level == '一级审签' || $level == '无需审签' || $level == '无审签' || $level == '01' || empty($level)) {
            $result['first_blsy_time']  = $blsydata[0]['SYSJ'] ?? '';
        } else {
            $first_blsy_time = $data1[0]['FIRST_BLSY_TIME'] ?? '';
        }

        //oci_free_statement($data);
        //oci_free_statement($resultblsy);

        return $first_blsy_time;
    }


    /**
     * 获取ODS Server服务
     * @return false|resource
     */
    public static function getOdsServer()
    {
        try {
            $server = oci_connect('nhzk', 'nhzk', '10.32.92.86:1521/IIH', "UTF8");

            if (!$server) {
                $oci_error = oci_error();
                Log::error('BL01清洗First_blsy_time字段脚本,链接ODS报错::', ['msg' => htmlentities($oci_error['message'])]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('BL01清洗First_blsy_time字段脚本,链接ODS报错::' . $e->getMessage());
            return false;
        }

        return $server;
    }
}
