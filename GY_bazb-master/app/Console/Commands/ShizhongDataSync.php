<?php

namespace App\Console\Commands;

use App\Model\User;
use App\Model\Staff;
use App\Model\ErrorV2;
use App\Model\Setting;
use App\Model\CaseRule;
use App\Model\Department;
use App\Model\CaseQuality;
use App\Model\DataSyncLog;
use App\Model\PatientInfo;
use App\Model\RuleWordMap;
use App\Services\HomeData;
use App\Model\PatientInfoV2;
use App\Services\CaseService;
use App\Model\ShizhongSyncZyh;
use App\Services\EsSaveService;
use Illuminate\Console\Command;
use App\Model\QualitySendMsgLog;
use App\Services\BanhzkgzService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\SqlServerProxyService;

class ShizhongDataSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:shizhong {zyh?} {sync_data?} {start_time?} ';

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
        //var_dump(date('Y-m-d H:i:s'));//时间
        $zyh = $this->argument("zyh");
        $sync_data = $this->argument("sync_data");
        $startTime = $this->argument("start_time");
        $this->ZY_BRRY($zyh ?? "", $sync_data, $startTime, 1);
    }

    /**
     * @param string $zyh 根据指定住院号同步brry数据
     * @param int $sync_data 同步brry表数据，不质控
     * @param int $last_id 质控从什么时候开始同步brry的数据，这个是一个6位住院号
     * @return bool
     * 三院 SELECT CYPB,ZYH,ZYHM as AAA28,BRKS,BRXM,ZZYS as ZZYSDM,ZSYS,to_char(RYRQ,'yyyy-mm-dd HH24:mi:ss') as AAB01,to_char(CYRQ,'yyyy-mm-dd HH24:mi:ss') as AAC01,BRBQ,BRCH as CH,ZYYS as GCYSDM,ZSYS as ZRYS,ZLXZ as ZLZZDM,MZYS,CYFS,RYCS as ZYCS FROM PORTAL_HIS.ZY_BRRY
     * 预警消息，brry数据同步
     */
    public function ZY_BRRY($zyh = '', $sync_data = 0, $startTime = "", $isCase = 1)
    {


        DataSyncLog::addData(['zyh' => $zyh, 'content' => 'ZY_BRRY开始同步']);
        $moduleName = env("APP_NAME", "");
        $className = "\\App\\Services\\MysqlDataSync\\{$moduleName}\\HomeData";
        $homeDataService = new $className();
        // 使用sqlserver连接
        try {
            if (env('BRRY_CONNECT_TYPE') == 'SQLSERVER') {
                $data = $this->getSqlserverBRRYData($zyh, $sync_data, $startTime);
            } else {
                $data = $this->getBRRYData($zyh, $sync_data, $startTime);
            }
        } catch (\Throwable $e) {
            Log::error('brryzyh::同步数据失败' . $e->getMessage());
            return false;
        }
        DataSyncLog::addData(['zyh' => $zyh, 'content' => 'ZY_BRRY获取数据']);

        Log::info('brryzyh::本次同步数据量' . count($data));
        if (empty($data)) {
            return false;
        }

        $department = Department::query()->get()->toArray();
        $dep = array_column($department, 'dep_name', 'dep_id');

        $staff = Staff::query()->get()->toArray();
        $staff = array_column($staff, 'name', 'code');

        $caseRule = CaseRule::query()->where('status', 1)->get()->toArray();
        $caseRule = array_column($caseRule, null, 'id');

        $caseService = new CaseService();
        $banhzkgzService = new BanhzkgzService();


        // 是否开启自动审核
        $keyword = RuleWordMap::query()
            ->where('id', '=', 4003)
            ->value("keyword");

        $keyword4000 = RuleWordMap::query()
            ->where('id', '=', 4000)
            ->value("keyword");

        $defaultQualityUserId = Setting::query()->where("name", "default_quality_user_id")->value("content");

        // 获取所有的登录用户信息
        $user = User::query()->get(["id", "dep_id", "department_review"])->toArray();

        $hospital_name = config('confAdmin.hospital_name');

        $data = array_column($data, null, 'ZYH');
        $data = array_values($data);
        foreach ($data as $item) {
            Log::info('brryzyh::' . $item['ZYH']);
            // 莱州的数据特殊处理，住院号有_的不同步
            if ($moduleName == 'laizhou' && strpos($item['ZYH'], '_') !== false) {
                continue;
            }
            if ($item['CYPB'] == 99) {
                \App\Model\ZY_BRRY::query()->where('ZYH', $item['ZYH'])->delete();
                \App\Model\PatientInfo::query()->where('MED_REC_ID', $item['ZYH'])->delete();
                // 如果病例信息进行过质控提醒，数据删除后将数据改为已删除
                QualitySendMsgLog::query()->where('zyh', '=', $item['ZYH'])->update(['is_delete' => 0]);
                continue;
            }
            if (env('APP_NAME') == 'lanling' && !empty($item['BRKS'])) {
                $item['BRKS'] = (string) array_search($item['BRKS'], $dep) ?? "";
                $item['BRBQ'] = (string) array_search($item['BRBQ'], $dep) ?? "";
            }

            $update = [
                'AAA28' => !empty($item['AAA28']) ? $item['AAA28'] : "",
                'AAB01' => !empty($item['AAB01']) ? $item['AAB01'] : "",
                'AAC01' => !empty($item['AAC01']) ? $item['AAC01'] : "",
                'ZY_KSDM' => !empty($item['ZY_KSDM']) ? $item['ZY_KSDM'] : "",
                'BRKS' => !empty($item['BRKS']) ? $item['BRKS'] : "",
                'ZY_KSMC' => !empty($item['ZY_KSMC']) ? $item['ZY_KSMC'] : "",
                'GCYSDM' => !empty($item['GCYSDM']) ? $item['GCYSDM'] : "",
                'GCYSMC' => !empty($item['GCYSMC']) ? $item['GCYSMC'] : (!empty($item['GCYSDM']) && !empty($staff[$item['GCYSDM']]) ? $staff[$item['GCYSDM']] : ""),
                'ZLZZDM' => !empty($item['ZLZZDM']) ? $item['ZLZZDM'] : "",
                'ZLZZMC' => !empty($item['ZLZZMC']) ? $item['ZLZZMC'] : (!empty($item['ZLZZDM']) && !empty($staff[$item['ZLZZDM']]) ? $staff[$item['ZLZZDM']] : ""),
                'ZZYSDM' => !empty($item['ZZYSDM']) ? $item['ZZYSDM'] : "",
                'ZZYSMC' => !empty($item['ZZYSMC']) ? $item['ZZYSMC'] : (!empty($item['ZZYSDM']) && !empty($staff[$item['ZZYSDM']]) ? $staff[$item['ZZYSDM']] : ""),
                'MZYS' => !empty($item['MZYS']) ? $item['MZYS'] : "",
                'MZYS_MC' => !empty($item['MZYS_MC']) ? $item['MZYS_MC'] : (!empty($item['MZYS']) && !empty($staff[$item['MZYS']]) ? $staff[$item['MZYS']] : ""),
                'ZRYS' => !empty($item['ZRYS']) ? $item['ZRYS'] : "",
                'ZRYS_MC' => !empty($item['ZRYS_MC']) ? $item['ZRYS_MC'] : (!empty($item['ZRYS']) && !empty($staff[$item['ZRYS']]) ? $staff[$item['ZRYS']] : ""),
                'CYPB' => !empty($item['CYPB']) ? $item['CYPB'] : "",
                'CYFS' => !empty($item['CYFS']) ? $item['CYFS'] : "",
                'BRXM' => !empty($item['BRXM']) ? $item['BRXM'] : "",
                'XB' => !empty($item['XB']) ? $item['XB'] : "",
                'NL' => !empty($item['NL']) ? $item['NL'] : "",
                'BRBQ' => !empty($item['BRBQ']) ? $item['BRBQ'] : "",
                'CY_KSDM' => !empty($item['CY_KSDM']) ? $item['CY_KSDM'] : "",
                'CY_KSMC' => !empty($item['CY_KSMC']) ? $item['CY_KSMC'] : "",
                'ZYCS' => !empty($item['ZYCS']) ? $item['ZYCS'] : "",
                'ZY_BQDM' => !empty($item['BRBQ']) ? $item['BRBQ'] : "",
                'ZY_BQMC' => !empty($item['ZY_BQMC']) ? $item['ZY_BQMC'] : (!empty($item['BRBQ']) && !empty($dep[$item['BRBQ']]) ? $dep[$item['BRBQ']] : ""),
            ];
            if (env('APP_NAME') == 'sanyuan') {
                $update["SFZH"] = $item['SFZH'];
            }
            // 住院次数是-1，则不同步
            if (!empty($item['ZYCS']) && $update["ZYCS"] == -1) {
                continue;
            }
            if ($item['CH']) {
                $update["CH"] = $item['CH'];
            }
            // 如果科室质控开启自动审核，则获取病例中病人科室对应的病例审核人
            //查询patient_info的score和home_ysz_score的分数，是否大于等于keyword4000
            $score = PatientInfo::query()->where('MED_REC_ID', $item['ZYH'])->value('score');
            $home_ysz_score = PatientInfo::query()->where('MED_REC_ID', $item['ZYH'])->value('home_ysz_score');
            $isscore = true;
            if ($score < $keyword4000 || $home_ysz_score < $keyword4000) {
                $isscore = false;
            }
            //查询rulewordmap 4002
            $levelstatus = RuleWordMap::query()->where('id', '=', 4002)->value('keyword') ?? '2';
            DataSyncLog::addData(['zyh' => $zyh, 'content' => 'ZY_BRRY查询rulewordmap 4002，status：' . $levelstatus]);

            //查询case_quality中的缺陷等级是否有强制的
            if ($isscore && $levelstatus == '1') {
                //查询是否有运行首页
                $patientInfoV2 = PatientInfoV2::query()->where('ZYH', $item['ZYH'])->get()->toArray();
                if (empty($patientInfoV2)) {
                    $isscore = false;
                }
                DataSyncLog::addData(['zyh' => $zyh, 'content' => 'ZY_BRRY查询运行首页完成']);
                $caseQuality = CaseQuality::query()->where('JZHM', $item['ZYH'])->leftJoin('case_rule', 'case_quality.rule_id', '=', 'case_rule.id')->where('case_rule.level', '=', '1')->get()->toArray();
                $caseQuality = array_column($caseQuality, 'JZHM');
                if (!empty($caseQuality)) {
                    $isscore = false;
                }
                DataSyncLog::addData(['zyh' => $zyh, 'content' => 'ZY_BRRY查询case_quality完成']);

                //查询errorv2中的缺陷是否有强制的
                $errorV2 = ErrorV2::query()->where('ZYH', $item['ZYH'])->leftJoin('error_rule', 'error_v2.error_rule', '=', 'error_rule.id')->where('error_rule.level', '=', '0')->get()->toArray();
                $errorV2 = array_column($errorV2, 'ZYH');
                if (!empty($errorV2)) {
                    $isscore = false;
                }
                DataSyncLog::addData(['zyh' => $zyh, 'content' => 'ZY_BRRY查询errorv2完成']);

                //自定义规则
                $rulesetting = CaseQuality::query()
                    ->where('JZHM', $item['ZYH'])
                    ->leftJoin('rule_setting', function ($join) {
                        $join->on(DB::raw('case_quality.rule_id'), '=', DB::raw('rule_setting.id + 1000000'));
                    })
                    ->where('rule_setting.error_level', '=', '1')
                    ->get()
                    ->toArray();
                //var_dump($rulesetting);
                $rulesetting = array_column($rulesetting, 'JZHM');
                if (!empty($rulesetting)) {
                    $isscore = false;
                }
                DataSyncLog::addData(['zyh' => $zyh, 'content' => 'ZY_BRRY查询自定义规则完成']);
            }

            if ($keyword == 1 && $isscore) {
                $update["review_status"] = 2;
                $update["review_user"] = $defaultQualityUserId;
                $update["review_time"] = date('Y-m-d H:i:s');
                foreach ($user as $uv) {
                    $depId = json_decode($uv["dep_id"], true);
                    if (!empty($depId) && !empty($update['BRKS']) && in_array($update['BRKS'], $depId) && $uv["department_review"] == 1) {
                        $update["review_user"] = $uv["id"];
                    }
                }
            }
            \App\Model\ZY_BRRY::query()->updateOrInsert(['ZYH' => $item['ZYH']], $update);
            DataSyncLog::addData(['zyh' => $zyh, 'content' => 'ZY_BRRY更新数据完成']);


            EsSaveService::zy_brry($item['ZYH']);
            $inhospital = 2;
            if (empty($update['AAC01']) || $update['AAC01'] == '') {
                $inhospital = 1;
            } else {
                $inhospital = 2;
            }

            $insertData = [
                'hospital_name' => $hospital_name,
                'MED_REC_ID' => $item['ZYH'] ?? "",
                'AAA28' => $update['AAA28'] ?? "",
                'AAA29' => $update['ZYCS'] ?? 0,
                'AAB01' => $update['AAB01'] ?? "",
                'AAC01' => $update['AAC01'] ?? "",
                'in_hospital' => $inhospital,
                'AAA01' => $update['BRXM'] ? desensitize($update['BRXM'], 1, 1, '*') : '',        // 患者姓名
            ];
            if (!empty($update['CH'])) {
                $insertData['CWH'] = $update['CH'];
            }
            if (!empty($update['BRKS'])) {
                $insertData['AAC11N'] = $dep[$update['BRKS']] ?? "";
            }
            // 郸城增加特殊字段
            if (env('APP_NAME') == 'dancheng') {
                $insertData['PATIENT_ID'] = !empty($item['PATIENT_ID']) ? $item['PATIENT_ID'] : "";
            }
            \App\Model\PatientInfo::query()->updateOrInsert(['MED_REC_ID' => $item['ZYH']], $insertData);

            // 只对24小时内入院的质控
            if ($isCase == 1) {
                // 2025年4月27日17:14:21 已沟通，暂时只质控首次病程和入院记录
                $homeDataService->getData($item['ZYH'], ['bl01']);
                if (!empty($caseRule[101])) {
                    $caseService->checkRy8($insertData); //首程
                }
                if (!empty($caseRule[99])) {
                    $banhzkgzService->rule99($caseRule, 99, $item['ZYH'], 1); //入院记录
                }
                if (!empty($caseRule[95])) {
                    $banhzkgzService->rule95($caseRule, 95, $item['ZYH'], 1); //病案首页
                }
                if (!empty($caseRule[114])) {
                    $banhzkgzService->rule114($caseRule, 114, $item['ZYH'], 1); //护士分床后48小时内，病程记录中，需要有一个 副高或正高签名（首次病程除外）
                }
                if (!empty($caseRule[1047])) {
                    $banhzkgzService->rule1047($caseRule, 1047, $item['ZYH'], 1); //上级医师首次查房记录
                }
            }
        }
    }

    /**
     * @return array
     * 获取HIS中BRRY的数据
     * @param string $zyh 根据指定住院号同步brry数据
     * @param int $sync_data 同步brry表数据，不质控
     * @param string $startTime 开始时间
     * @return array
     */
    public function getBRRYData($zyh = '', $sync_data = 0, $startTime = "")
    {
        $sql = $this->getBrrySql($zyh, $sync_data, $startTime);
        $username = env('HISDB_USERNAME', '');
        $password = env('HISDB_PASSWORD', '');
        $connection = env('HISDB_HOST', '');
        $port = env('HISDB_PORT', '');
        $tns = env('HISDB_TNS', '');
        $con = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $result = oci_parse($con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }

        return $data;
    }

    /**
     * @return array
     * 获取sqlserver中BRRY的数据
     * @param string $zyh 根据指定住院号同步brry数据
     * @param int $sync_data 同步brry表数据，不质控
     * @param string $startTime 开始时间
     * @return array
     */
    public function getSqlserverBRRYData($zyh = '', $sync_data = 0, $startTime = "")
    {
        $sql = $this->getBrrySql($zyh, $sync_data, $startTime);
        $sqlsrvConfig = [
            'host' => env('SQLSRV_HOST'),
            'port' => '1433',
            'database' => env('SQLSRV_DATABASE'),
            'username' => env('SQLSRV_USERNAME'),
            'password' => env('SQLSRV_PASSWORD'),
        ];

        // 使用SqlServerProxyService创建连接
        $sqlsrvService = SqlServerProxyService::withConfig($sqlsrvConfig);

        // 测试SQL Server连接
        if (!$sqlsrvService->testConnection()) {
            Log::error('SQL Server数据库连接失败');
            throw new \Exception('SQL Server数据库连接失败');
        }

        // 将SQL Server服务实例保存到静态属性中，供后续使用
        $data = $sqlsrvService->query($sql);
        return $data ?? [];
    }

    /**
     * @return string
     * 获取brry的sql语句
     * @param string $zyh 根据指定住院号同步brry数据
     * @param int $sync_data 同步brry表数据，不质控
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @return string
     */
    private function getBrrySql($zyh = '', $sync_data = 0, $startTime = "", $endTime = "")
    {

        $sql = RuleWordMap::query()->where("id", 9007)->value("keyword");
        $map9012 = RuleWordMap::query()->where("id", 9012)->value("keyword");
        $map9013 = RuleWordMap::query()->where("id", 9013)->value("keyword");
        // 指定住院号同步
        if ($zyh) {
            $sql .= " WHERE {$map9012}='" . $zyh . "'";
        }
        // 仅同步brry同步字段为date类型
        elseif ($sync_data == 1) {
            $sql .= " WHERE {$map9013} > TO_DATE('" . $startTime . "000000', 'yyyy-MM-dd HH24:mi:ss')";
        }
        // 仅同步brry同步字段为字符串类型
        elseif ($sync_data == 2) {
            $startTime = date('Y-m-d H:i:s', strtotime($startTime));
            $sql .= " WHERE {$map9013} > '$startTime'";
        } elseif ($sync_data == 3) {
            //获取当前时间，取RYRQ在当前时间-8小时到当前时间-6时之间
            $startTime = date('Y-m-d H:i:s', strtotime('-8 hours'));
            $endTime = date('Y-m-d H:i:s', strtotime('-6 hours'));
            $sql .= " WHERE {$map9013} >= TO_DATE('$startTime', 'yyyy-mm-dd HH24:mi:ss') AND {$map9013} <= TO_DATE('$endTime', 'yyyy-mm-dd HH24:mi:ss')";
        } elseif ($sync_data == 4) {
            //获取当前时间，取RYRQ在当前时间-22小时到当前时间-24时之间
            $startTime = date('Y-m-d H:i:s', strtotime('-24 hours'));
            $endTime = date('Y-m-d H:i:s', strtotime('-22 hours'));
            $sql .= " WHERE {$map9013} >= TO_DATE('$startTime', 'yyyy-mm-dd HH24:mi:ss') AND {$map9013} <= TO_DATE('$endTime', 'yyyy-mm-dd HH24:mi:ss')";
        } elseif ($sync_data == 5) {
            //获取当前时间，取RYRQ在当前时间-46小时到当前时间-48时之间
            $startTime = date('Y-m-d H:i:s', strtotime('-48 hours'));
            $endTime = date('Y-m-d H:i:s', strtotime('-46 hours'));
            $sql .= " WHERE {$map9013} >= TO_DATE('$startTime', 'yyyy-mm-dd HH24:mi:ss') AND {$map9013} <= TO_DATE('$endTime', 'yyyy-mm-dd HH24:mi:ss')";
        }
        // 预警消息
        else {
            if (stripos(strtolower($sql), 'where') !== false) {
                $sql .= " AND ({$map9013} IS NULL OR {$map9013} = '')";
            } else {
                $sql .= " WHERE ({$map9013} IS NULL OR {$map9013} = '')";
            }
        }

        return $sql;
    }
}
