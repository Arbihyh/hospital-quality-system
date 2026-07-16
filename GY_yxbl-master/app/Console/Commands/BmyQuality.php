<?php

namespace App\Console\Commands;

use App\Model\Yzb;
use Carbon\Carbon;
use App\Model\GY_SJQX;
use App\Model\ErrorRule;
use App\Model\Department;
use App\Model\PatientAdd;
use App\Model\FeeDetailed;
use App\Model\HomeQuality;
use App\Model\PatientInfo;
use App\Model\RuleWordMap;
use App\Model\MainDiagnosis;
use App\Model\MainOperation;
use App\Model\OtherDiagnosis;
use App\Model\PatientCostInfo;
use App\Model\PatientWorkInfo;
use App\Model\DataxSyncSetting;
use App\Model\PatientOtherInfo;
use App\Services\HomeSzService;
use Illuminate\Console\Command;
use App\Model\PatientDoctorInfo;
use App\Model\PrimaryKeyControl;
use App\Model\PatientAddressInfo;
use App\Model\PatientMedicalInfo;
use App\Model\SecondaryOperation;
use App\Model\PatientContactsInfo;
use App\Model\PatientHospitalInfo;
use Illuminate\Support\Facades\Log;
use App\Services\BasyQualityService;
use App\Services\DataxSync\DataSyncService;
use App\Http\Controllers\Api\CustomZKController;

class BmyQuality extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bmy:quality {startDate?} {endDate?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '编码员批量质控';
    protected $HomeSzService;
    protected $addressRegexp;
    protected $ageRegexp;
    protected $ageYcRegexp;

    public static $mysqlcon;
    /**
     * 建立MySQL数据库连接
     * @return \PDO|null
     */
    public function getMysqlConnect()
    {
        if (self::$mysqlcon) {
            return self::$mysqlcon;
        }
        $servername = '10.32.82.93';
        $port = '9030';
        $username = 'yw_bazk';
        $password = 'Yw#bazk123';
        $dbname = 'ods';

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_EMULATE_PREPARES => true,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ];

        try {
            $dsn = "mysql:host={$servername};port={$port};dbname={$dbname};charset=utf8mb4";
            $conn = new \PDO($dsn, $username, $password, $options);
            self::$mysqlcon = $conn;
        } catch (\PDOException $e) {
            Log::error('MySQL连接失败: ' . $e->getMessage());
            return null;
        }
    }

    public function queryMysqlSql($sql = '')
    {
        if (!self::$mysqlcon) {
            $this->getMysqlConnect();
        }
        $result = self::$mysqlcon->query($sql);
        $data = [];
        // 获取所有结果
        $results = $result->fetchAll();

        // 或逐行获取
        foreach ($results as $row) {
            $data[] = $row;
        }
        return $data;
    }

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        //$this->HomeSzService = new HomeSzService();
        //$this->addressRegexp = RuleWordMap::query()->whereIn('id', ['4041', '4042', '4011', '4012', '4021', '4022', '4023', '4031', '4032', '4033'])->pluck('keyword', 'id')->toArray();

        $this->addressRegexp = GY_SJQX::query()->whereIn('id', ['4041', '4042', '4011', '4012', '4021', '4022', '4023', '4031', '4032', '4033'])->pluck('keyword', 'id')->toArray();

        $this->ageRegexp = [1 => '/(\d+)岁/', 2 => '/(\d+)月/'];
        $this->ageYcRegexp = [1 => '/(.*?(岁))/u', 2 => '/(.*?(月))/u'];
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('编码员质控 - 开始');

        //获取质控时间
        $startDate = $this->argument('startDate') ?: '';
        $endDate = $this->argument('endDate') ?: '';
        if (!empty($startDate)) {
            $field = 'AAC01';
            $startTime = $startDate . ' 00:00:00';
        } else {
            $field = 'created_at';
            //前一天
            $date = Carbon::parse()->addDay(-15)->toDateString();
            $startTime = $date . ' 00:00:00';
        }
        if (!empty($endDate)) {
            $field = 'AAC01';
            $endTime = $endDate . ' 23:59:59';
        } else {
            $field = 'created_at';
            //当天
            $date = Carbon::parse()->addDay(0)->toDateString();
            $endTime = $date . ' 23:59:59';
        }

        // 获取质控规则
        $errorRuleData = ErrorRule::query()
            ->where('status', '=', 0)
            ->Where(function ($query) {
                $query->where('node', 'like', "%终末%")
                    ->orWhere('node', 'like', "%运行%");
            })
            ->orderBy('id')
            ->get()->toArray();
        $errorRuleData = array_column($errorRuleData, null, 'id');

        //开始循环
        $basyQualityService = new BasyQualityService();
        $pageSize = 100;
        $processedCounts = [];
        $duplicateProcessed = [];
        PatientInfo::query()
            // ->where('MED_REC_ID', '=', '110001771')
            ->whereNotNull('AAB01')
            ->whereBetween('AAC01', [$startTime, $endTime])
            ->chunkById($pageSize, function ($patientData) use ($basyQualityService, $errorRuleData, &$processedCounts, &$duplicateProcessed) {
                foreach ($patientData as $item) {
                    $value = $item->toArray();
                    $processedCounts[$value['MED_REC_ID']] = ($processedCounts[$value['MED_REC_ID']] ?? 0) + 1;
                    if ($processedCounts[$value['MED_REC_ID']] > 1) {
                        $duplicateProcessed[$value['MED_REC_ID']] = $processedCounts[$value['MED_REC_ID']];
                        Log::warning('同一次编码员质控中重复处理住院号', [
                            'MED_REC_ID' => $value['MED_REC_ID'],
                            'count' => $processedCounts[$value['MED_REC_ID']],
                            'id' => $value['id'] ?? null,
                            'AAC01' => $value['AAC01'] ?? null,
                        ]);
                    }
                    echo $value['MED_REC_ID'] . "时间:" . $value['AAC01'] . "\n";
                    try {

                        Log::info('开始数据同步');
                        $content = $this->zkInfo($value); //获取质控数据
                        Log::info('数据同步结束');

                        $moduleName = env("APP_NAME", "");

                        // 排除宁夏中新生儿的质控数据
                        if ($moduleName == 'ningxia') {
                            if (preg_match('/[a-zA-Z]/', $value['AAA28'])) {
                                HomeQuality::where('ZYH', '=', $value['MED_REC_ID'])->delete();
                                continue;
                            }
                        }
                        if (isset($content['data']) && !empty($content['data'])) {
                            $basyQualityService->qualityContrl($content, $errorRuleData); //处理质控数据
                            if (class_exists(CustomZKController::class)) {
                                $customZK = new CustomZKController();
                                $customZK->customizeRule($value['MED_REC_ID'], $content, 1);
                            }
                        } else {
                            echo $value['MED_REC_ID'] . "没有质控数据" . date("Y-m-d H:i:s") . "\n";
                            continue;
                        }
                    } catch (\Throwable $th) {
                        echo $value['MED_REC_ID'] . "质控异常: " . $th->getMessage() . date("Y-m-d H:i:s") . "\n";
                        continue;
                    }
                }
            }, 'id');
        Log::info('编码员质控住院号处理统计', [
            'total_unique' => count($processedCounts),
            'duplicate_unique' => count($duplicateProcessed),
            'duplicate_detail' => $duplicateProcessed,
        ]);

        $this->info('编码员质控 - 完毕');
    }

    /**
     * 重新质控
     * @return void
     */
    public function reCheck($MED_REC_ID)
    {
        Log::info('ZYH---' . $MED_REC_ID);
        // 获取质控规则
        $errorRuleData = ErrorRule::query()
            ->where('status', '=', 0)
            ->Where(function ($query) {
                $query->where('node', 'like', "%终末%")
                    ->orWhere('node', 'like', "%运行%");
            })
            ->orderBy('id')
            ->get()->toArray();
        $errorRuleData = array_column($errorRuleData, null, 'id');

        $patientData = PatientInfo::query()
            ->where('MED_REC_ID', '=', $MED_REC_ID)
            ->get()
            ->toArray();
        if (!empty($patientData)) $patientData = $patientData[0];

        $content = $this->zkInfo($patientData); //获取质控数据
        //$customZKController = new CustomZKController();
        //$customZKController->customizeRule($MED_REC_ID, $content,3);
        $basyQualityService = new BasyQualityService();
        $res = $basyQualityService->qualityContrl($content, $errorRuleData); //处理质控数据

        $customZK = new CustomZKController();
        $zkmsg = $customZK->customizeRule($content['data']['MED_REC_ID'], $content, 1);

        if ($res === true) {
            return true;
        } else {
            return false;
        }
    }


    /**质控信息
     * @return void
     */
    public function zkInfo($data)
    {
        $ZYH_ID = $data['MED_REC_ID']; //ID_ENT唯一标识
        if (empty($ZYH_ID)) return;

        $map9040 = RuleWordMap::query()->where('id', '=', '9040')->first();
        $map9040 = $map9040 ? explode(',', $map9040->keyword) : ['MED_REC_ID'];
        $mapValues = [];
        foreach ($map9040 as $fieldName) {
            $value = isset($data[$fieldName]) ? $data[$fieldName] : null;
            $mapValues[] = $value;
        }

        $currentTime = date("Y-m-d H:i:s"); //当前时间
        //region patient信息===
        //先去ods库中取数据，如果没有再去我们自己的数据库取
        //$patientInfo = $this->HomeSzService->getOdsInfo($this->HomeSzService->getInfoSql(),$ZYH_ID);
        //先通过getInstance连接ods库
        $connect = DataSyncService::getInstance(2);
        // Log::info('数据同步开始 - patientInfo：'.date("Y-m-d H:i:s"));
        $field = PrimaryKeyControl::query()->where('tableName', '=', 'patient_info')->first()->toArray()['field'];
        $patientInfo = $connect->setByNameSql('patient_info', 1)
            ->setWhere($field, $mapValues)
            ->getResult();

        if (is_array($patientInfo) && count($patientInfo) > 0) $patientInfo = $patientInfo[0];

        //如果没有获取到数据则去mysql库取,获取到了则更新mysql到数据
        if (empty($patientInfo)) {

            PatientInfo::query()->where(['MED_REC_ID' => $ZYH_ID])->update(['IS_CATA' => 0]);
            $patientInfo = PatientInfo::query()->where('MED_REC_ID', '=', $ZYH_ID)->first();
            if (empty($patientInfo)) return;
            $patientInfo = $patientInfo->toArray();
            Log::warning('patient_info ODS未取到数据，改用MySQL回退', ['MED_REC_ID' => $ZYH_ID]);
        } else {
            //清洗年龄
            //年
            if (env('APP_NAME') != 'sanyuan' && env('APP_NAME') != 'laizhou') {
                if (isset($patientInfo['AAA04_1']) && !empty($patientInfo['AAA04_1']) && strpos($patientInfo['AAA04_1'], '岁') !== false) {
                    $ageYear = $this->parseAndTrimAge($patientInfo['AAA04_1'], $this->ageRegexp[1], $this->ageYcRegexp[1]);
                    $patientInfo['AAA04'] = $ageYear['num'];
                }

                //月
                if (isset($patientInfo['AAA04_1']) && !empty($patientInfo['AAA04_1']) && strpos($patientInfo['AAA04_1'], '月') !== false) {
                    $ageMonth = $this->parseAndTrimAge($patientInfo['AAA04_1'], $this->ageRegexp[2], $this->ageYcRegexp[2]);
                    $patientInfo['AAA40'] = $ageMonth['num'] > 0 ? $ageMonth['num'] * 30 : '';
                }
                //清洗AAA06C,把01,02这种类似的转换成1,2这种,使用正则去掉开头的0
                if (!empty($patientInfo['AAA06C'])) {
                    $patientInfo['AAA06C'] = preg_replace('/^0/', '', $patientInfo['AAA06C']);
                }
                //清洗AAB06C
                if (!empty($patientInfo['AAB06C'])) {
                    $aab06c = $patientInfo['AAB06C'];
                    if ($aab06c == '门诊') {
                        $patientInfo['AAB06C'] = 1;
                    } elseif ($aab06c == '急诊') {
                        $patientInfo['AAB06C'] = 2;
                    } elseif ($aab06c == '其他医疗机构转入') {
                        $patientInfo['AAB06C'] = 3;
                    } elseif ($aab06c == '其他') {
                        $patientInfo['AAB06C'] = 4;
                    }
                }
                //清洗AEM01C 
                if (!empty($patientInfo['AEM01C'])) {
                    $aem01c = $patientInfo['AEM01C'] ?? '';
                    if ($aem01c == '医嘱离院') {
                        $patientInfo['AEM01C'] = 1;
                    } elseif ($aem01c == '医嘱转院') {
                        $patientInfo['AEM01C'] = 2;
                    } elseif ($aem01c == '医嘱转社区卫生服务机构' || $aem01c == '医嘱转乡镇卫生院') {
                        $patientInfo['AEM01C'] = 3;
                    } elseif ($aem01c == '非医嘱离院') {
                        $patientInfo['AEM01C'] = 4;
                    } elseif ($aem01c == '死亡') {
                        $patientInfo['AEM01C'] = 5;
                    } elseif ($aem01c == '其他') {
                        $patientInfo['AEM01C'] = 9;
                    }
                }
            }
            if (env('APP_NAME') == 'ningxia') {
                $NLM = $patientInfo['AAA40'];
                if (!empty($NLM)) {
                    $plusPos = strpos($NLM, '+');

                    if ($plusPos === 0) {
                        // 如果加号在第一位，取+到/之间的字符
                        $slashPos = strpos($NLM, '/');
                        if ($slashPos !== false && $slashPos > $plusPos) {
                            $extractedText = substr($NLM, $plusPos + 1, $slashPos - $plusPos - 1);
                            $patientInfo['AAA40'] = $extractedText;
                        }
                    } else {
                        // 如果加号不在第一位，把BZYZSNL设置成空，NL设置成NLM
                        $patientInfo['AAA40'] = '';
                        $patientInfo['AAA04'] = $NLM;
                    }
                }
            }
            $patientInfo['updated_at'] = $currentTime;
            $patientInfo['in_hospital'] = 2;
            // $patientInfo['IS_CATA'] = 1;

            PatientInfo::query()->updateOrInsert(['MED_REC_ID' => $ZYH_ID], $patientInfo);
        }
        $aaa28 = $patientInfo['AAA28'] ?? ($data['AAA28'] ?? "");


        //patient_hospital_info表
        $field = PrimaryKeyControl::query()->where('tableName', '=', 'patient_hospital_info')->first()->toArray()['field'];
        $patientHospitalInfo = $connect->setByNameSql('patient_hospital_info', 1)
            ->setWhere($field, $mapValues)
            ->getResult();
        if (is_array($patientHospitalInfo) && count($patientHospitalInfo) > 0) $patientHospitalInfo = $patientHospitalInfo[0];
        // Log::info('patientHospitalInfo',['patientHospitalInfo'=>$patientHospitalInfo]);
        // Log::info('数据同步 - patientHospitalInfo：'.date("Y-m-d H:i:s"));
        if (empty($patientHospitalInfo)) {
            $patientHospitalInfo = PatientHospitalInfo::query()->where('AAA28', '=', $ZYH_ID)->first();
            if (empty($patientHospitalInfo)) {
                $patientHospitalInfo = [];
            } else {
                $patientHospitalInfo = $patientHospitalInfo->toArray();
            }
        } else {
            //清洗AEI01C 1.是 2.否 3.-
            if (env('APP_NAME') != 'sanyuan' && env('APP_NAME') != 'laizhou') {
                $aei01c = $patientHospitalInfo['AEI01C'];
                if ($aei01c == '是') {
                    $patientHospitalInfo['AEI01C'] = 1;
                } elseif ($aei01c == '否') {
                    $patientHospitalInfo['AEI01C'] = 2;
                } elseif ($aei01c == '-') {
                    $patientHospitalInfo['AEI01C'] = 3;
                }
                //清洗AEM03C
                if (!empty($patientHospitalInfo['AEM03C'])) {
                    $aem03c = $patientHospitalInfo['AEM03C'] ?? '';
                    if ($aem03c == '无') {
                        $patientHospitalInfo['AEM03C'] = 1;
                    } elseif ($aem03c == '有') {
                        $patientHospitalInfo['AEM03C'] = 2;
                    }
                }
            }

            if (env('APP_NAME') == 'laizhou') {
                if ($patientInfo['AEM01C'] == 2) {
                    $patientHospitalInfo['AEM02'] = "乡镇卫生院";
                } elseif ($patientInfo['AEM01C'] == 3) {
                    $patientHospitalInfo['AEM02'] = "转其他医疗机构";
                }
            }

            if (env('APP_NAME') == 'ningxia') {
                $AAD01C = [];
                $sql = "SELECT * FROM HIS_V1_EN_DEP_TRANS WHERE ID_ENT='$ZYH_ID' order by DT_APPLY ASC";
                $trans = $this->queryMysqlSql($sql);
                $ZKKBMCIndex = 1;
                if (is_array($trans) && count($trans) > 0) {
                    foreach ($trans as $item) {
                        $dep = $this->queryMysqlSql("SELECT * FROM HIS_V1_BD_DEP WHERE ID_DEP='{$item['ID_DEP_FROM']}'");
                        if (is_array($dep) && count($dep) > 0) {
                            if ($ZKKBMCIndex == 1) {
                                $patientHospitalInfo['ZKKBMC'] = $dep[0]['NAME'];
                            } else {
                                $patientHospitalInfo['ZKKBMC' . $ZKKBMCIndex] = $dep[0]['NAME'];
                            }
                        }
                        $ZKKBMCIndex++;
                    }
                    $lastTrans = end($trans);
                    $dep = $this->queryMysqlSql("SELECT * FROM HIS_V1_BD_DEP WHERE ID_DEP='{$lastTrans['ID_DEP_TO']}'");
                    if (is_array($dep) && count($dep) > 0) {
                        $patientHospitalInfo['ZKKBMC' . $ZKKBMCIndex] = $dep[0]['NAME'];
                    }
                }
            }

            // 出院病房未空，则根据入院病房编码获取
            if (isset($patientHospitalInfo['RYBFBM']) && !empty($patientHospitalInfo['RYBFBM']) && empty($patientHospitalInfo['AAB03'])) {
                $patientHospitalInfo['AAB03'] = Department::query()->where('dep_id', '=', $patientHospitalInfo['RYBFBM'])->first()->dep_name ?? '';
            }
            if (isset($patientHospitalInfo['CYBFBM']) && !empty($patientHospitalInfo['CYBFBM']) && empty($patientHospitalInfo['AAC03'])) {
                $patientHospitalInfo['AAC03'] = Department::query()->where('dep_id', '=', $patientHospitalInfo['CYBFBM'])->first()->dep_name ?? '';
            }
            if (isset($patientHospitalInfo['ZKKB']) && !empty($patientHospitalInfo['ZKKB']) && empty($patientHospitalInfo['ZKKBMC'])) {
                $patientHospitalInfo['ZKKBMC'] = Department::query()->where('dep_id', '=', $patientHospitalInfo['ZKKB'])->first()->dep_name ?? '';
            }
            if (isset($patientHospitalInfo['AAB11C']) && !empty($patientHospitalInfo['AAB11C']) && empty($patientHospitalInfo['AAB11N'])) {
                $patientHospitalInfo['AAB11N'] = Department::query()->where('dep_id', '=', $patientHospitalInfo['AAB11C'])->first()->dep_name ?? '';
            }
            unset($patientHospitalInfo['RYBFBM'], $patientHospitalInfo['CYBFBM'], $patientHospitalInfo['ZKKB']);
            $patientHospitalInfo['updated_at'] = $currentTime;
            PatientHospitalInfo::query()->updateOrInsert(['AAA28' => $ZYH_ID], $patientHospitalInfo);
        }

        //patient_doctor_info表
        $field = PrimaryKeyControl::query()->where('tableName', '=', 'patient_doctor_info')->first()->toArray()['field'];
        $patientDoctorInfo = $connect->setByNameSql('patient_doctor_info', 1)
            ->setWhere($field, $mapValues)
            ->getResult();
        if (is_array($patientDoctorInfo) && count($patientDoctorInfo) > 0) $patientDoctorInfo = $patientDoctorInfo[0];
        // Log::info('patientDoctorInfo',['patientDoctorInfo'=>$patientDoctorInfo]);
        // Log::info('数据同步 - patientDoctorInfo：'.date("Y-m-d H:i:s"));
        if (empty($patientDoctorInfo)) {
            $patientDoctorInfo = PatientDoctorInfo::query()->where('AAA28', '=', $ZYH_ID)->first();
            if (empty($patientDoctorInfo)) {
                $patientDoctorInfo = [];
            } else {
                $patientDoctorInfo = $patientDoctorInfo->toArray();
            }
        } else {
            $patientDoctorInfo['updated_at'] = $currentTime;
            PatientDoctorInfo::query()->updateOrInsert(['AAA28' => $ZYH_ID], $patientDoctorInfo);
        }

        //病案首页（其他信息）
        $field = PrimaryKeyControl::query()->where('tableName', '=', 'patient_other_info')->first()->toArray()['field'];
        $patientOtherInfo = $connect->setByNameSql('patient_other_info', 1)
            ->setWhere($field, $mapValues)
            ->getResult();
        if (is_array($patientOtherInfo) && count($patientOtherInfo) > 0) $patientOtherInfo = $patientOtherInfo[0];
        // Log::info('patientOtherInfo',['patientOtherInfo'=>$patientOtherInfo]);
        // Log::info('数据同步 - patientOtherInfo：'.date("Y-m-d H:i:s"));
        if (empty($patientOtherInfo)) {
            $patientOtherInfo = PatientOtherInfo::query()->where('AAA28', '=', $ZYH_ID)->first();
            if (empty($patientOtherInfo)) {
                $patientOtherInfo = [];
            } else {
                $patientOtherInfo = $patientOtherInfo->toArray();
            }
        } else {
            $patientOtherInfo['updated_at'] = $currentTime;
            PatientOtherInfo::query()->updateOrInsert(['AAA28' => $ZYH_ID], $patientOtherInfo);
        }

        //patient_contacts_info表
        $field = PrimaryKeyControl::query()->where('tableName', '=', 'patient_contacts_info')->first()->toArray()['field'];
        $patientContactsInfo = $connect->setByNameSql('patient_contacts_info', 1)
            ->setWhere($field, $mapValues)
            ->getResult();
        if (is_array($patientContactsInfo) && count($patientContactsInfo) > 0) $patientContactsInfo = $patientContactsInfo[0];
        // Log::info('patientContactsInfo',['patientContactsInfo'=>$patientContactsInfo]);
        // Log::info('数据同步 - patientContactsInfo：'.date("Y-m-d H:i:s"));
        if (empty($patientContactsInfo)) {
            $patientContactsInfo = PatientContactsInfo::query()->where('AAA28', '=', $ZYH_ID)->first();
            if (empty($patientContactsInfo)) {
                $patientContactsInfo = [];
            } else {
                $patientContactsInfo = $patientContactsInfo->toArray();
            }
        } else {
            $patientContactsInfo['updated_at'] = $currentTime;
            if (env('APP_NAME') == 'dancheng') {
                $danchengcity = config('danchengcity');
                $csd = $patientContactsInfo['AAA09'] . $patientContactsInfo['AAA10'] . $patientContactsInfo['AAA11'];
                if ($csd && !empty($danchengcity[$csd])) {
                    $patientContactsInfo['AAA09'] = $danchengcity[$csd]['province'];
                    $patientContactsInfo['AAA10'] = $danchengcity[$csd]['city'];
                    $patientContactsInfo['AAA11'] = $danchengcity[$csd]['county'];
                }
                $HKDZ = $patientContactsInfo['AAA45'] . $patientContactsInfo['AAA46'] . $patientContactsInfo['AAA47'];
                if ($HKDZ && !empty($danchengcity[$HKDZ])) {
                    $patientContactsInfo['AAA43'] = $danchengcity[$HKDZ]['province'];
                    $patientContactsInfo['AAA45'] = $danchengcity[$HKDZ]['province'];
                    $patientContactsInfo['AAA44'] = $danchengcity[$HKDZ]['city'];
                    $patientContactsInfo['AAA46'] = $danchengcity[$HKDZ]['city'];
                    $patientContactsInfo['AAA47'] = $danchengcity[$HKDZ]['county'];
                }
                $XZZ = $patientContactsInfo['AAA48'] . $patientContactsInfo['AAA49'] . $patientContactsInfo['AAA50'];
                if ($XZZ && !empty($danchengcity[$XZZ])) {
                    $patientContactsInfo['AAA48'] = $danchengcity[$XZZ]['province'];
                    $patientContactsInfo['AAA49'] = $danchengcity[$XZZ]['city'];
                    $patientContactsInfo['AAA50'] = $danchengcity[$XZZ]['county'];
                }
            }
            PatientContactsInfo::query()->updateOrInsert(['AAA28' => $ZYH_ID], $patientContactsInfo);
        }
        //patient_address_info表
        $field = PrimaryKeyControl::query()->where('tableName', '=', 'patient_address_info')->first()->toArray()['field'];
        $patientAddressInfo = $connect->setByNameSql('patient_address_info', 1)
            ->setWhere($field, $mapValues)
            ->getResult();
        if (is_array($patientAddressInfo) && count($patientAddressInfo) > 0) $patientAddressInfo = $patientAddressInfo[0];
        // Log::info('patientAddressInfo',['patientAddressInfo'=>$patientAddressInfo]);
        // Log::info('数据同步 - patientAddressInfo：'.date("Y-m-d H:i:s"));
        if (empty($patientAddressInfo)) {
            $patientAddressInfo = PatientAddressInfo::query()->where('AAA28', '=', $ZYH_ID)->first();
            if (empty($patientAddressInfo)) {
                $patientAddressInfo = [];
            } else {
                $patientAddressInfo = $patientAddressInfo->toArray();
            }
        } else {
            //清洗出生地
            if (env('APP_NAME') == 'dancheng') {
                $danchengcity = config('danchengcity');
                $csd = $patientAddressInfo['AAA09'] . $patientAddressInfo['AAA10'] . $patientAddressInfo['AAA11'];
                if ($csd && !empty($danchengcity[$csd])) {
                    $patientAddressInfo['AAA09'] = $danchengcity[$csd]['province'];
                    $patientAddressInfo['AAA10'] = $danchengcity[$csd]['city'];
                    $patientAddressInfo['AAA11'] = $danchengcity[$csd]['county'];
                }
                $HKDZ = $patientAddressInfo['AAA45'] . $patientAddressInfo['AAA46'] . $patientAddressInfo['AAA47'];
                if ($HKDZ && !empty($danchengcity[$HKDZ])) {
                    $patientAddressInfo['AAA43'] = $danchengcity[$HKDZ]['province'];
                    $patientAddressInfo['AAA45'] = $danchengcity[$HKDZ]['province'];
                    $patientAddressInfo['AAA44'] = $danchengcity[$HKDZ]['city'];
                    $patientAddressInfo['AAA46'] = $danchengcity[$HKDZ]['city'];
                    $patientAddressInfo['AAA47'] = $danchengcity[$HKDZ]['county'];
                }
                $XZZ = $patientAddressInfo['AAA48'] . $patientAddressInfo['AAA49'] . $patientAddressInfo['AAA50'];
                if ($XZZ && !empty($danchengcity[$XZZ])) {
                    $patientAddressInfo['AAA48'] = $danchengcity[$XZZ]['province'];
                    $patientAddressInfo['AAA49'] = $danchengcity[$XZZ]['city'];
                    $patientAddressInfo['AAA50'] = $danchengcity[$XZZ]['county'];
                }
                $patientAddressInfo['CSD'] = $csd;
                $patientAddressInfo['GG'] = $patientAddressInfo['AAA09'] . $patientAddressInfo['AAA10'];
            } elseif (env('APP_NAME') != 'sanyuan' && env('APP_NAME') != 'laizhou' && env('APP_NAME') != 'hlw') {
                $csdAddress = $patientAddressInfo['CSD'] ?? '';
                $csdProvinceResult = $this->parseAndTrimAddress($csdAddress, $this->addressRegexp['4041']);
                if (empty($patientAddressInfo['AAA09'])) {
                    $patientAddressInfo['AAA09'] = $csdProvinceResult['result'];
                }
                $csdCityResult = $this->parseAndTrimAddress($csdProvinceResult['address'], $this->addressRegexp['4042']);
                if (empty($patientAddressInfo['AAA10'])) {
                    $patientAddressInfo['AAA10'] = $csdCityResult['result'];
                }
                if (empty($patientAddressInfo['AAA11'])) {
                    $patientAddressInfo['AAA11'] = $csdCityResult['address'];
                }
                $patientAddressInfo['CSD'] = str_replace($patientAddressInfo['AAA09'], '', $patientAddressInfo['CSD']);
                $patientAddressInfo['CSD'] = str_replace($patientAddressInfo['AAA10'], '', $patientAddressInfo['CSD']);
                $patientAddressInfo['CSD'] = str_replace($patientAddressInfo['AAA11'], '', $patientAddressInfo['CSD']);
                //清洗籍贯
                $ggAddress = $patientAddressInfo['GG'] ?? '';
                $ggProvinceResult = $this->parseAndTrimAddress($ggAddress, $this->addressRegexp['4011']);
                if (empty($patientAddressInfo['AAA43'])) {
                    $patientAddressInfo['AAA43'] = $ggProvinceResult['result'];
                }
                $ggCityResult = $this->parseAndTrimAddress($ggProvinceResult['address'], $this->addressRegexp['4012']);
                if (empty($patientAddressInfo['AAA44'])) {
                    $patientAddressInfo['AAA44'] = $ggCityResult['result'];
                }
                $patientAddressInfo['GG'] = str_replace($patientAddressInfo['AAA43'], '', $patientAddressInfo['GG']);
                $patientAddressInfo['GG'] = str_replace($patientAddressInfo['AAA44'], '', $patientAddressInfo['GG']);

                //清洗户籍
                $hjAddress = $patientAddressInfo['AAA12'] ?? '';
                $hjProvinceResult = $this->parseAndTrimAddress($hjAddress, $this->addressRegexp['4021']);
                if (empty($patientAddressInfo['AAA45'])) {
                    $patientAddressInfo['AAA45'] = $hjProvinceResult['result'];
                }
                $hjCityResult = $this->parseAndTrimAddress($hjProvinceResult['address'], $this->addressRegexp['4022']);
                if (empty($patientAddressInfo['AAA46'])) {
                    $patientAddressInfo['AAA46'] = $hjCityResult['result'];
                }
                $hjAreaResult = $this->parseAndTrimAddress($hjCityResult['address'], $this->addressRegexp['4023']);
                if (empty($patientAddressInfo['AAA47'])) {
                    $patientAddressInfo['AAA47'] = $hjAreaResult['result'];
                }
                $patientAddressInfo['AAA12'] = str_replace($patientAddressInfo['AAA45'], '', $patientAddressInfo['AAA12']);
                $patientAddressInfo['AAA12'] = str_replace($patientAddressInfo['AAA46'], '', $patientAddressInfo['AAA12']);
                $patientAddressInfo['AAA12'] = str_replace($patientAddressInfo['AAA47'], '', $patientAddressInfo['AAA12']);

                //清洗现住址
                $xzzAddress = $patientAddressInfo['AAA15'] ?? '';
                $xzzProvinceResult = $this->parseAndTrimAddress($xzzAddress, $this->addressRegexp['4031']);
                if (empty($patientAddressInfo['AAA48'])) {
                    $patientAddressInfo['AAA48'] = $xzzProvinceResult['result'];
                }
                $xzzCityResult = $this->parseAndTrimAddress($xzzProvinceResult['address'], $this->addressRegexp['4032']);
                if (empty($patientAddressInfo['AAA49'])) {
                    $patientAddressInfo['AAA49'] = $xzzCityResult['result'];
                }
                $xzzAreaResult = $this->parseAndTrimAddress($xzzCityResult['address'], $this->addressRegexp['4033']);
                if (empty($patientAddressInfo['AAA50'])) {
                    $patientAddressInfo['AAA50'] = $xzzAreaResult['result'];
                }
                $patientAddressInfo['AAA15'] = str_replace($patientAddressInfo['AAA48'], '', $patientAddressInfo['AAA15']);
                $patientAddressInfo['AAA15'] = str_replace($patientAddressInfo['AAA49'], '', $patientAddressInfo['AAA15']);
                $patientAddressInfo['AAA15'] = str_replace($patientAddressInfo['AAA50'], '', $patientAddressInfo['AAA15']);
            }
            $patientAddressInfo['updated_at'] = $currentTime;
            PatientAddressInfo::query()->updateOrInsert(['AAA28' => $ZYH_ID], $patientAddressInfo);
        }

        //patient_add表
        $field = PrimaryKeyControl::query()->where('tableName', '=', 'patient_add')->first()->toArray()['field'];
        $patientAdd = $connect->setByNameSql('patient_add', 1)
            ->setWhere($field, $mapValues)
            ->getResult();
        if (is_array($patientAdd) && count($patientAdd) > 0) $patientAdd = $patientAdd[0];
        // Log::info('patientAdd',['patientAdd'=>$patientAdd]);
        // Log::info('数据同步 - patientAdd：'.date("Y-m-d H:i:s"));
        if (empty($patientAdd)) {
            $patientAdd = PatientAdd::query()->where('AAA28', '=', $ZYH_ID)->first();
            if (empty($patientAdd)) {
                $patientAdd = [];
            } else {
                $patientAdd = $patientAdd->toArray();
            }
        } else {
            if (env('APP_NAME') != 'sanyuan' && env('APP_NAME') != 'laizhou') {
                //清洗LCLJ 1.是 2.否
                if (!empty($patientAdd['LCLJ'])) {
                    if ($patientAdd['LCLJ'] == '是') {
                        $patientAdd['LCLJ'] = 1;
                    } elseif ($patientAdd['LCLJ'] == '否') {
                        $patientAdd['LCLJ'] = 2;
                    }
                }
                //清洗WCQK 1.完成 2.退出
                if (!empty($patientAdd['WCQK'])) {
                    if ($patientAdd['WCQK'] == '完成') {
                        $patientAdd['WCQK'] = 1;
                    } elseif ($patientAdd['WCQK'] == '退出') {
                        $patientAdd['WCQK'] = 2;
                    }
                }
                //清洗BYQK 1.有 2.无
                if (!empty($patientAdd['BYQK'])) {
                    if ($patientAdd['BYQK'] == '有') {
                        $patientAdd['BYQK'] = 1;
                    } elseif ($patientAdd['BYQK'] == '无') {
                        $patientAdd['BYQK'] = 2;
                    }
                }
            }
            $patientAdd['updated_at'] = $currentTime;
            PatientAdd::query()->updateOrInsert(['AAA28' => $ZYH_ID], $patientAdd);
        }

        //patient_work_info表
        $field = PrimaryKeyControl::query()->where('tableName', '=', 'patient_work_info')->first()->toArray()['field'];
        $patientWorkInfo = $connect->setByNameSql('patient_work_info', 1)
            ->setWhere($field, $mapValues)
            ->getResult();
        if (is_array($patientWorkInfo) && count($patientWorkInfo) > 0) $patientWorkInfo = $patientWorkInfo[0];
        // Log::info('数据同步 - patientWorkInfo：'.date("Y-m-d H:i:s"));
        if (empty($patientWorkInfo)) {
            $patientWorkInfo = PatientWorkInfo::query()->where('AAA28', '=', $ZYH_ID)->first();
            if (empty($patientWorkInfo)) {
                $patientWorkInfo = [];
            } else {
                $patientWorkInfo = $patientWorkInfo->toArray();
            }
        } else {
            $patientWorkInfo['updated_at'] = $currentTime;
            PatientWorkInfo::query()->updateOrInsert(['AAA28' => $ZYH_ID], $patientWorkInfo);
        }

        //patient_medical_info表
        $field = PrimaryKeyControl::query()->where('tableName', '=', 'patient_medical_info')->first()->toArray()['field'];
        $patientMedicalInfo = $connect->setByNameSql('patient_medical_info', 1)
            ->setWhere($field, $mapValues)
            ->getResult();
        if (is_array($patientMedicalInfo) && count($patientMedicalInfo) > 0) $patientMedicalInfo = $patientMedicalInfo[0];
        // Log::info('patientMedicalInfo',['patientMedicalInfo'=>$patientMedicalInfo]);
        // Log::info('数据同步 - patientMedicalInfo：'.date("Y-m-d H:i:s"));
        if (empty($patientMedicalInfo)) {
            $patientMedicalInfo = PatientMedicalInfo::query()->where('AAA28', '=', $ZYH_ID)->first();
            if (empty($patientMedicalInfo)) {
                $patientMedicalInfo = [];
            } else {
                $patientMedicalInfo = $patientMedicalInfo->toArray();
            }
        } else {
            if (env('APP_NAME') != 'sanyuan' && env('APP_NAME') != 'laizhou') {
                //清洗AEB02C 无1有2-3
                if (!empty($patientMedicalInfo['AEB02C'])) {
                    $aeb02c = $patientMedicalInfo['AEB02C'];
                    if ($aeb02c == '无') {
                        $patientMedicalInfo['AEB02C'] = 1;
                    } elseif ($aeb02c == '有') {
                        $patientMedicalInfo['AEB02C'] = 2;
                    } elseif ($aeb02c == '-') {
                        $patientMedicalInfo['AEB02C'] = 3;
                    }
                }
                //清洗AEG01C 1.A 2.B 3.O 4.AB 5.不详 6.未查
                if (!empty($patientMedicalInfo['AEG01C'])) {
                    $aeg01c = $patientMedicalInfo['AEG01C'];
                    if ($aeg01c == 'A') {
                        $patientMedicalInfo['AEG01C'] = 1;
                    } elseif ($aeg01c == 'B') {
                        $patientMedicalInfo['AEG01C'] = 2;
                    } elseif ($aeg01c == 'O') {
                        $patientMedicalInfo['AEG01C'] = 3;
                    } elseif ($aeg01c == 'AB') {
                        $patientMedicalInfo['AEG01C'] = 4;
                    } elseif ($aeg01c == '不详') {
                        $patientMedicalInfo['AEG01C'] = 5;
                    } elseif ($aeg01c == '未查') {
                        $patientMedicalInfo['AEG01C'] = 6;
                    }
                }
                //清洗AEG02C  1.阴 2.阳 3.不详 4.未查
                if (!empty($patientMedicalInfo['AEG02C'])) {
                    $aeg02c = $patientMedicalInfo['AEG02C'];
                    if ($aeg02c == '阴') {
                        $patientMedicalInfo['AEG02C'] = 1;
                    } elseif ($aeg02c == '阳') {
                        $patientMedicalInfo['AEG02C'] = 2;
                    } elseif ($aeg02c == '不详') {
                        $patientMedicalInfo['AEG02C'] = 3;
                    } elseif ($aeg02c == '未查') {
                        $patientMedicalInfo['AEG02C'] = 4;
                    }
                }
                //清洗AED01C  1.甲 2.乙 3.丙
                if (!empty($patientMedicalInfo['AED01C'])) {
                    $aed01c = $patientMedicalInfo['AED01C'];
                    if ($aed01c == '甲') {
                        $patientMedicalInfo['AED01C'] = 1;
                    } elseif ($aed01c == '乙') {
                        $patientMedicalInfo['AED01C'] = 2;
                    } elseif ($aed01c == '丙') {
                        $patientMedicalInfo['AED01C'] = 3;
                    }
                }
            }
            $patientMedicalInfo['updated_at'] = $currentTime;
            PatientMedicalInfo::query()->updateOrInsert(['AAA28' => $ZYH_ID], $patientMedicalInfo);
        }

        //patient_cost_info表
        $field = PrimaryKeyControl::query()->where('tableName', '=', 'patient_cost_info')->first()->toArray()['field'];
        $patientCostInfo = $connect->setByNameSql('patient_cost_info', 1)
            ->setWhere($field, $mapValues)
            ->getResult();
        if (is_array($patientCostInfo) && count($patientCostInfo) > 0) $patientCostInfo = $patientCostInfo[0];
        // Log::info('数据同步 - patientCostInfo：'.date("Y-m-d H:i:s"));
        if (empty($patientCostInfo)) {
            $patientCostInfo = PatientCostInfo::query()->where('AAA28', '=', $ZYH_ID)->first();
            if (empty($patientCostInfo)) {
                $patientCostInfo = [];
            } else {
                $patientCostInfo = $patientCostInfo->toArray();
            }
        } else {
            $patientCostInfo['updated_at'] = $currentTime;
            PatientCostInfo::query()->updateOrInsert(['AAA28' => $ZYH_ID], $patientCostInfo);
        }

        //所属院区
        // $YqCode = [];
        // if(!empty($patientHospitalInfo['AAC02C']))
        // {
        // $YqCode = $this->HomeSzService->getYq($patientHospitalInfo['AAC02C']);
        // if (is_array($YqCode) && count($YqCode) > 0) $YqCode = $YqCode[0] ?? [];
        // }

        $patientInfo = array_merge(
            $patientInfo,
            $patientHospitalInfo,
            $patientDoctorInfo,
            $patientContactsInfo,
            $patientAddressInfo,
            $patientAdd,
            $patientWorkInfo,
            $patientMedicalInfo,
            $patientCostInfo,
            $patientOtherInfo
        );
        $patientInfo['ZA03'] = $patientInfo['HOSPITAL_NAME'] ?? $patientInfo['hospital_name'] ?? '';
        $patientInfo['AAA28'] = $aaa28 ?? '';
        $patientInfo['ABB02C'] = "";
        $patientInfo['AAC11N'] = "";
        $patientInfo['YQ_CODE'] = $YqCode['YQ_CODE'] ?? "1";
        $patientInfo['GX_MC'] = $patientInfo['AAA23C'] ?? "";
        $patientInfo['AAB02C'] = $patientInfo['AAB02C'] ?? "";
        $patientInfo['AEB02C'] = $patientInfo['AEB02C'] ?? "";
        $patientInfo['AEE08'] = $patientInfo['AEE08'] ?? "";
        $patientInfo['ABF01N'] = $patientInfo['ABF01N'] ?? "";
        $patientInfo['AEM03C'] = $patientInfo['AEM03C'] ?? "";
        $patientInfo['AED01C'] = $patientInfo['AED01C'] ?? "";
        $patientInfo['AED04'] = $patientInfo['AED04'] ?? "";
        //endregion

        //region diagnosis信息===
        $field = PrimaryKeyControl::query()->where('tableName', '=', 'main_diagnosis')->first()->toArray()['field'];
        $diagnosisData = [];
        $otherDiagnosis = [];
        $diagnosisData = $connect->setByNameSql('main_diagnosis', 1)
            ->setWhere($field, $mapValues)
            ->getResult();
        // Log::info('diagnosisData',['diagnosisData'=>$diagnosisData]);
        // Log::info('数据同步 - diagnosisData：'.date("Y-m-d H:i:s"));
        $field = PrimaryKeyControl::query()->where('tableName', '=', 'other_diagnosis')->first()->toArray()['field'];
        $otherDiagnosis = $connect->setByNameSql('other_diagnosis', 1)
            ->setWhere($field, $mapValues)
            ->getResult();
        // Log::info('otherDiagnosis',['otherDiagnosis'=>$otherDiagnosis]);
        // Log::info('数据同步 - otherDiagnosis：'.date("Y-m-d H:i:s"));
        if (!empty($diagnosisData) && !empty($otherDiagnosis)) {
            $diagnosisData = array_merge($diagnosisData, $otherDiagnosis);
        }


        if (empty($diagnosisData)) {
            $diagnosisData = MainDiagnosis::query()->where('AAA28', '=', $ZYH_ID)->get()->toArray();
            $otherDiagnosis = OtherDiagnosis::query()->where('AAA28', '=', $ZYH_ID)->get()->toArray();
            $diagnosisData = array_merge($diagnosisData, $otherDiagnosis);
            // Log::info('diagnosisData11',['diagnosisData11'=>$diagnosisData]);
        } else {
            //如果ZZPB为1，则更新主诊断表，否则更新其他诊断表
            MainDiagnosis::query()->where('AAA28', '=', $ZYH_ID)->delete();
            OtherDiagnosis::query()->where('AAA28', '=', $ZYH_ID)->delete();
            foreach ($diagnosisData as $v) {
                $v['updated_at'] = $currentTime;
                if ($v['ZZPB'] == 1 || $v['ZZPB'] == '1') {
                    // 更新patient_info表的ICD10_NAME
                    if (!empty($v['ICD10_NAME'])) {
                        PatientInfo::query()
                            ->where('MED_REC_ID', $ZYH_ID)
                            ->update(['ICD10_NAME' => $v['ICD10_NAME'], 'ABC01N' => $v['ICD10_NAME']]);
                        PatientHospitalInfo::query()
                            ->where('AAA28', $ZYH_ID)
                            ->update(['ABC01C' => $v['ICD10_NAME']]);
                    }
                    MainDiagnosis::query()->insert($v);
                } else {
                    OtherDiagnosis::query()->insert($v);
                }
            }
        }
        foreach ($diagnosisData as $k => $v) {
            $diagnosisData[$k]['ZDBM'] = $v['ICD10_ID1'] ?? ""; //诊断编码
            $diagnosisData[$k]['ZDMC'] = $v['ICD10_NAME'] ?? ""; //诊断名称
        }
        //endregion

        //region 手术信息===
        $field = PrimaryKeyControl::query()->where('tableName', '=', 'main_operation')->first()->toArray()['field'];
        $operationData = [];
        $secondaryOperation = [];
        $operationData = $connect->setByNameSql('main_operation', 1)
            ->setWhere($field, $mapValues)
            ->getResult();
        // Log::info('数据同步 - operationData：'.date("Y-m-d H:i:s"));
        // Log::info('数据数据量 - operationData：'.count($operationData));
        $field = PrimaryKeyControl::query()->where('tableName', '=', 'secondary_operation')->first()->toArray()['field'];
        $secondaryOperation = $connect->setByNameSql('secondary_operation', 1)
            ->setWhere($field, $mapValues)
            ->getResult();
        // Log::info('数据同步 - secondaryOperation：'.date("Y-m-d H:i:s"));
        // Log::info('数据数据量 - secondaryOperation：'.count($secondaryOperation));
        if (!empty($operationData) && !empty($secondaryOperation)) {
            $operationData = array_merge($operationData, $secondaryOperation);
        }
        if (empty($operationData)) {
            $operationData = MainOperation::query()->where('AAA28', '=', $ZYH_ID)->get()->toArray();
            $secondaryOperation = SecondaryOperation::query()->where('AAA28', '=', $ZYH_ID)->get()->toArray();
            $operationData = array_merge($operationData, $secondaryOperation);
        } else {
            // Log::info('数据清除1 - operationData：'.date("Y-m-d H:i:s"));
            // //如果SFZYSS为1，则更新主手术表，否则更新次手术表
            //删除手术信息
            MainOperation::query()->where('AAA28', '=', $ZYH_ID)->delete();
            // Log::info('数据清除2 - operationData：'.date("Y-m-d H:i:s"));
            SecondaryOperation::query()->where('AAA28', '=', $ZYH_ID)->delete();
            $ssbs = RuleWordMap::query()->where('id', '=', 10001)->first()->toArray()['keyword'];
            //使用=号分隔
            $ssbs = explode('=', $ssbs);
            foreach ($operationData as &$vOpe) {
                if (empty($vOpe['OPE_DATE']) && !empty($vOpe['START_TIME'])) {
                    $vOpe['OPE_DATE'] = $vOpe['START_TIME'];
                }
                $vOpe['updated_at'] = $currentTime;
                if ($vOpe[$ssbs[0]] == $ssbs[1]) {
                    // 更新patient_info表的ICD10_NAME
                    if (!empty($vOpe['ICD9_NAME'])) {
                        PatientInfo::query()
                            ->where('MED_REC_ID', $ZYH_ID)
                            ->update(['ICD9_NAME' => $vOpe['ICD9_NAME']]);
                    }
                    MainOperation::query()->insert($vOpe);
                } else {
                    SecondaryOperation::query()->insert($vOpe);
                }
            }
            // Log::info('数据插入 - operationData：'.date("Y-m-d H:i:s"));
        }
        foreach ($operationData as $k => $v) {
            $operationData[$k]['SSCZBM'] = $v['ICD9_ID1'] ?? ""; //手术操作编码
            $operationData[$k]['SSCZMC'] = $v['ICD9_NAME'] ?? ""; //手术操作名称
            $operationData[$k]['SSCZRQ'] = $v['OPE_DATE']; //手术操作日期
            $operationData[$k]['SSSX'] = $v['SFZYSS'];
            $operationData[$k]['SZXM'] = $v['OPE_MAN_NAME']; //手术者对应主刀医生名称
            $operationData[$k]['MZFS'] = $v['HOCUS_WAY_ID']; //麻醉方式
            $operationData[$k]['MZYSXM'] = $v['HOCUS_MAN_NAME']; //麻醉医师
        }
        // Log::info('数据同步结束 - operationData：'.date("Y-m-d H:i:s"));
        //endregion

        //费用详情
        $field = PrimaryKeyControl::query()->where('tableName', '=', 'fee_detailed')->first()->toArray()['field'];
        $feeDetailedData = $connect->setByNameSql('fee_detailed', 0)
            ->setWhere($field, $mapValues)
            ->getResult();
        // Log::info('数据同步 - feeDetailedData：'.date("Y-m-d H:i:s"),['feeDetailedData'=>$feeDetailedData]);
        if (empty($feeDetailedData)) {
            $feeDetailedData = FeeDetailed::query()->where('AAA28', '=', $ZYH_ID)->get()->toArray();
        } /* else {
            foreach ($feeDetailedData as $v) {
                $v['updated_at'] = $currentTime;
                FeeDetailed::query()->updateOrInsert(['FYXH' => $v['FYXH']], $v);
            }
        } */

        //医嘱
        $field = PrimaryKeyControl::query()->where('tableName', '=', 'yzb')->first()->toArray()['field'];
        $yzData = $connect->setByNameSql('yzb', 0)
            ->setWhere($field, $mapValues)
            ->getResult();
        // Log::info('数据同步 - yzData：'.date("Y-m-d H:i:s"),['yzData'=>$yzData]);
        if (empty($yzData)) {
            $yzData = Yzb::query()->where('ZYH', '=', $ZYH_ID)->get()->toArray();
        } /* else {
            foreach ($yzData as $v) {
                $v['updated_at'] = $currentTime;
                Yzb::query()->updateOrInsert(['YZBXH' => $v['YZBXH']], $v);
            }
        } */
        $data = [
            'data' => $patientInfo,
            'diagnosis' => $diagnosisData,
            'operation' => $operationData,
            'fy' => $feeDetailedData,
            'yz' => $yzData,
            'zy_zkjl' => [],
        ];
        Log::info('data', ['data' => $data['data']]);
        return $data;
    }

    /** 清洗地址
     * @params string $address
     * @param string $regexp
     * return array
     */
    protected function parseAndTrimAddress($address, $regexp)
    {
        preg_match("{$regexp}", $address, $matches);
        $result = isset($matches[1]) ? $matches[1] : '';
        if (!empty($result)) {
            $address = preg_replace('/' . preg_quote($result, '/') . '/', '', $address, 1);
        }
        return ['result' => $result, 'address' => $address];
    }

    /**
     * 清洗年龄
     */
    protected function parseAndTrimAge($age, $regexp, $ycRegexp = null)
    {
        // 使用正则表达式匹配岁前面的数字
        preg_match($regexp, $age, $matches);
        $num = isset($matches[1]) ? intval($matches[1]) : 0; //获取数字
        //移除
        preg_match($ycRegexp, $age, $matches);
        $result = isset($matches[1]) ? $matches[1] : '';
        if (!empty($matches)) {
            $age = preg_replace('/' . preg_quote($result, '/') . '/', '', $age, 1);
        }
        return ['num' => $num, 'age' => $age];
    }
}
