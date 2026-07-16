<?php

namespace App\Console\Commands;

use App\Model\ErrorRule;
use App\Model\DataSyncKey;
use App\Model\DataSyncKeySetting;
use App\Model\DataxSyncSetting;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLSY;
use App\Model\FeeDetailed;
use App\Model\MainDiagnosis;
use App\Model\MainOperation;
use App\Model\OtherDiagnosis;
use App\Model\PatientAdd;
use App\Model\PatientAddressInfo;
use App\Model\PatientContactsInfo;
use App\Model\PatientCostInfo;
use App\Model\PatientDoctorInfo;
use App\Model\PatientHospitalInfo;
use App\Model\PatientInfo;
use App\Model\PatientMedicalInfo;
use App\Model\PatientOtherInfo;
use App\Model\PatientWorkInfo;
use App\Model\RuleWordMap;
use App\Model\SecondaryOperation;
use App\Model\Yzb;
use App\Services\BasyQualityService;
use App\Services\DataxSync\DataSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DetectionSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test-sync {type?} {startTime?} {endTime?} {--continuous : 持续同步模式，不进行数据监控}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步数据检测';

    private $startTime;

    private $endTime;

    private $addressRegexp;

    private $ageRegexp;

    private $ageYcRegexp;


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function __call($method, $parameters)
    {
        return "{$method}方法不存在！！";
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        //$this->HomeSzService = new HomeSzService();
        $this->addressRegexp = RuleWordMap::query()->whereIn('id', ['4041', '4042', '4011', '4012', '4021', '4022', '4023', '4031', '4032', '4033'])->pluck('keyword', 'id')->toArray();
        $this->ageRegexp = [1 => '/(\d+)岁/', 2 => '/(\d+)月/'];
        $this->ageYcRegexp = [1 => '/(.*?(岁))/u', 2 => '/(.*?(月))/u'];
        $type = data_get($this->arguments(), 'type');

        // 检查是否使用持续同步模式
        if ($this->option('continuous')) {
            return $this->_continuousSync();
        }

        while (true) {
            try {
                // 获取时间设置
                $startTime = RuleWordMap::query()->where('id', 9001)->value('keyword');
                $endTime = RuleWordMap::query()->where('id', 9002)->value('keyword');
                $jiange = RuleWordMap::query()->where('id', 9003)->value('keyword');

                if (empty($endTime)) {
                    // 开始时间+间隔(小时)
                    $endTime = Carbon::parse($startTime)->addHours($jiange)->toDateTimeString();
                }

                $carbon = new Carbon();


                // 检查开始时间是否超过当前时间
                if (Carbon::parse($startTime)->gt(Carbon::now())) {
                    $this->info("开始时间{$startTime}超过当前时间，等待1小时后从前一个时间开始...");
                    sleep(3600); // 等待1小时,然后开始时间-一小时
                    $startTime = Carbon::parse($startTime)->subHours(1)->toDateTimeString();
                    $endTime = Carbon::parse($startTime)->addHours($jiange)->toDateTimeString();
                    continue;
                }
                $this->startTime = $startTime;
                $this->endTime = $endTime;

                // 执行原有的处理逻辑
                $func = '_' . $type;
                $this->info($this->$func());

                // 更新数据库中的时间设置
                RuleWordMap::query()->where('id', 9001)->update(['keyword' => $endTime]);
                $newEndTime = Carbon::parse($endTime)->addHours($jiange)->toDateTimeString();
                RuleWordMap::query()->where('id', 9002)->update(['keyword' => $newEndTime]);

                $this->info("时间窗口已更新: {$endTime} -> {$newEndTime}");
            } catch (\Exception $e) {
                $this->error("处理过程出错: " . $e->getMessage());
                Log::error("数据同步循环出错", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                sleep(300); // 出错后等待5分钟后继续
            }
        }

        return true;
    }


    /**
     * 监测HIS数据表更新
     */
    private function _detectionTable()
    {
        //如果没有传 走默认减一天至今,取前一天的00:00:00
        //$this->startTime = $this->startTime ?: Carbon::parse()->subDays(1)->startOfDay()->toDateTimeString();

        $this->info(date("Y-m-d H:i:s") . "::开始,监测时间：{$this->startTime} 至 {$this->endTime}");

        // 记录总处理数量
        $totalCount = 0;
        // 记录basy表的处理数量
        $basyCount = 0;

        foreach ($this->getTableSql() as $value) {
            $this->info("正在处理数据源: " . json_encode($value));

            $res = DataSyncService::getInstance()->setSql($value['sqlcontent'])
                ->setTime("{$value['updatefield']}", "TO_DATE('{$this->startTime}','yyyy-mm-dd hh24:mi:ss')", "TO_DATE('{$this->endTime}','yyyy-mm-dd hh24:mi:ss')")
                ->getResult();

            if (empty($res)) {
                $this->info("数据源返回空结果");
                continue;
            }

            $this->info("获取到 " . count($res) . " 条原始数据");

            // 为当前结果集设置tablename和jm_key
            foreach ($res as &$item) {
                $item['jm_key'] = $value['jm_key'];
                $item['tablename'] = $value['tablename'];
            }

            // 对当前数据源结果进行去重
            $uniqueRes = array_values(array_unique($res, SORT_REGULAR));
            $this->info("去重后剩余 " . count($uniqueRes) . " 条数据");

            if (!empty($uniqueRes)) {
                $successCount = 0;
                $errorCount = 0;

                // 逐条插入数据
                foreach ($uniqueRes as $record) {
                    try {
                        // 在插入前设置时间戳，确保每条记录时间不同
                        $record['created_at'] = Carbon::parse()->toDateTimeString();
                        $record['updated_at'] = Carbon::parse()->toDateTimeString();

                        DataSyncKey::query()->insert($record);
                        $successCount++;
                        $totalCount++;

                        // 如果是basy表的数据，计数加1
                        if ($record['tablename'] === 'basy') {
                            $basyCount++;
                        }
                    } catch (\Exception $e) {
                        $errorCount++;
                        $this->error("插入记录失败: " . $e->getMessage());
                        Log::error("数据同步失败", [
                            'error' => $e->getMessage(),
                            'record' => $record
                        ]);
                        continue;
                    }
                }

                $this->info(date("Y-m-d H:i:s") . "::已处理{$value['tablename']}表数据 {$successCount} 条，失败 {$errorCount} 条");
            }
        }

        // 只对basy表数据进行去重
        if ($basyCount > 0) {
            $this->removeDuplicatesForBasy();
        }

        $this->info(date("Y-m-d H:i:s") . "::完成，共处理 {$totalCount} 条数据");
        //DataSyncService::getInstance()->closeConnect();
        //调用数据同步
        $this->_getDataSyncKey();
        return true;
    }

    /**
     * 只对basy表数据进行去重，保留最新记录
     */
    private function removeDuplicatesForBasy()
    {
        $this->info(date("Y-m-d H:i:s") . "::开始对basy表数据进行去重处理");

        try {
            // 查找basy表中的重复记录
            $duplicates = DB::table('data_sync_key as t1')
                ->select('t1.*')
                ->join('data_sync_key as t2', function ($join) {
                    $join->on('t1.tablename', '=', 't2.tablename')
                        ->on('t1.jm_key', '=', 't2.jm_key')
                        ->on('t1.unique_key', '=', 't2.unique_key')
                        ->on('t1.unique_value', '=', 't2.unique_value')
                        ->on('t1.id', '<', 't2.id'); // 保留最新的记录
                })
                ->where('t1.tablename', '=', 'basy')
                ->get();

            if ($duplicates->isEmpty()) {
                $this->info("basy表没有发现重复数据");
                return;
            }

            // 删除重复记录
            $deleteIds = $duplicates->pluck('id')->toArray();
            $deleteCount = DataSyncKey::query()->whereIn('id', $deleteIds)->delete();

            $this->info("已删除basy表 {$deleteCount} 条重复数据");
        } catch (\Exception $e) {
            $this->error("basy表去重处理失败: " . $e->getMessage());
            Log::error("basy表去重处理失败", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 数据同步脚本
     */
    private function _getDataSyncKey()
    {

        $this->info(date("Y-m-d H:i:s") . "::数据开始同步,同步时间：{$this->startTime} 至 {$this->endTime}");

        // 添加调试信息
        $instance = DataSyncService::getInstance();
        $this->info("DataSyncService实例类型: " . get_class($instance));
        $this->info("可用方法: " . implode(", ", get_class_methods($instance)));
        //当前时间
        $endTime = $this->endTime;
        DataSyncKey::query()->when($this->startTime && $endTime, function ($query) use ($endTime) {
            return $query->whereBetween('created_at', [$this->startTime, $endTime]);
        })->chunkById(1000, function ($items) {

            collect($items)->map(function ($item) {

                if ($item->tablename == 'basy') {
                    //查询是否在编目表中存在
                    //$exist = DataSyncService::getInstance()->setSql( "SELECT ID_ENT FROM CI_MR_FP_PAT_CATA WHERE ID_ENT = {$item->unique_value}")->getResult();
                    //已编目数据
                    $exist = 1;
                    //开始同步
                    //首页基本信息
                    var_dump($item->unique_key, $item->unique_value);
                    //清洗年龄
                    //$field = PrimaryKeyControl::query()->where('tableName', '=', 'patient_info')->first()->toArray()['field'];
                    $patientInfo = DataSyncService::getInstance()->setByNameSql('patient_info', 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getResult();

                    if (is_array($patientInfo) && count($patientInfo) > 0)
                        $patientInfo = $patientInfo[0];
                    $currentTime = date("Y-m-d H:i:s"); //当前时间
                    //年
                    if (!empty($patientInfo)) {
                        if (!empty($patientInfo['AAA04_1'])) {
                            $ageYear = $this->parseAndTrimAge($patientInfo['AAA04_1'], $this->ageRegexp[1], $this->ageYcRegexp[1]);
                            $patientInfo['AAA04'] = $ageYear['num'];
                            //月
                            $ageMonth = $this->parseAndTrimAge($ageYear['age'], $this->ageRegexp[2], $this->ageYcRegexp[2]);
                            $patientInfo['AAA40'] = $ageMonth['num'] > 0 ? $ageMonth['num'] * 30 : '';
                            //清洗AAA06C,把01,02这种类似的转换成1,2这种,使用正则去掉开头的0
                            if (!empty($patientInfo['AAA06C'])) {
                                $patientInfo['AAA06C'] = preg_replace('/^0/', '', $patientInfo['AAA06C']);
                            }
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
                        $patientInfo['updated_at'] = $currentTime;

                        PatientInfo::query()->updateOrInsert(['ZYH_ID' => $item->unique_value], $patientInfo);
                    }

                    $patientMedicalInfo = DataSyncService::getInstance()->setByNameSql('patient_medical_info', 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getResult();
                    if (is_array($patientMedicalInfo) && count($patientMedicalInfo) > 0)
                        $patientMedicalInfo = $patientMedicalInfo[0];
                    if (!empty($patientMedicalInfo)) {
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
                        //unset($patientMedicalInfo['ZYH_ID']);
                        PatientMedicalInfo::query()->updateOrInsert(['ZYH_ID' => $item->unique_value], $patientMedicalInfo);
                    }


                    $patientAddressInfo = DataSyncService::getInstance()->setByNameSql('patient_address_info', 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getResult();
                    if (is_array($patientAddressInfo) && count($patientAddressInfo) > 0)
                        $patientAddressInfo = $patientAddressInfo[0];

                    if (!empty($patientAddressInfo)) {
                        //清洗出生地
                        if (!empty($patientAddressInfo['CSD'])) {
                            //清洗出生地
                            $csdAddress = $patientAddressInfo['CSD'];
                            $csdProvinceResult = $this->parseAndTrimAddress($csdAddress, $this->addressRegexp['4041']);
                            $patientAddressInfo['AAA09'] = $csdProvinceResult['result'];
                            $csdCityResult = $this->parseAndTrimAddress($csdProvinceResult['address'], $this->addressRegexp['4042']);
                            $patientAddressInfo['AAA10'] = $csdCityResult['result'];
                            $patientAddressInfo['AAA11'] = $csdCityResult['address'];
                            $patientAddressInfo['CSD'] = str_replace($patientAddressInfo['AAA09'], '', $patientAddressInfo['CSD']);
                            $patientAddressInfo['CSD'] = str_replace($patientAddressInfo['AAA10'], '', $patientAddressInfo['CSD']);
                            $patientAddressInfo['CSD'] = str_replace($patientAddressInfo['AAA11'], '', $patientAddressInfo['CSD']);
                        }
                        //清洗籍贯
                        if (!empty($patientAddressInfo['GG'])) {
                            //清洗籍贯
                            $ggAddress = $patientAddressInfo['GG'];
                            $ggProvinceResult = $this->parseAndTrimAddress($ggAddress, $this->addressRegexp['4011']);
                            $patientAddressInfo['AAA43'] = $csdProvinceResult['result'];
                            $ggCityResult = $this->parseAndTrimAddress($ggProvinceResult['address'], $this->addressRegexp['4012']);
                            $patientAddressInfo['AAA44'] = $ggCityResult['result'];
                            $patientAddressInfo['GG'] = str_replace($patientAddressInfo['AAA43'], '', $patientAddressInfo['GG']);
                            $patientAddressInfo['GG'] = str_replace($patientAddressInfo['AAA44'], '', $patientAddressInfo['GG']);
                        }

                        //清洗户籍
                        if (!empty($patientAddressInfo['AAA12'])) {
                            // 清洗户籍
                            $hjAddress = $patientAddressInfo['AAA12'];
                            $hjProvinceResult = $this->parseAndTrimAddress($hjAddress, $this->addressRegexp['4021']);
                            $patientAddressInfo['AAA45'] = $hjProvinceResult['result'];
                            $hjCityResult = $this->parseAndTrimAddress($hjProvinceResult['address'], $this->addressRegexp['4022']);
                            $patientAddressInfo['AAA46'] = $hjCityResult['result'];
                            $hjAreaResult = $this->parseAndTrimAddress($hjCityResult['address'], $this->addressRegexp['4023']);
                            $patientAddressInfo['AAA47'] = $hjAreaResult['result'];
                            $patientAddressInfo['AAA12'] = str_replace($patientAddressInfo['AAA45'], '', $patientAddressInfo['AAA12']);
                            $patientAddressInfo['AAA12'] = str_replace($patientAddressInfo['AAA46'], '', $patientAddressInfo['AAA12']);
                            $patientAddressInfo['AAA12'] = str_replace($patientAddressInfo['AAA47'], '', $patientAddressInfo['AAA12']);
                        }

                        //清洗现住址
                        if (!empty($patientAddressInfo['AAA15'])) {
                            //清洗现住址
                            $xzzAddress = $patientAddressInfo['AAA15'];
                            $xzzProvinceResult = $this->parseAndTrimAddress($xzzAddress, $this->addressRegexp['4031']);
                            $patientAddressInfo['AAA48'] = $xzzProvinceResult['result'];
                            $xzzCityResult = $this->parseAndTrimAddress($xzzProvinceResult['address'], $this->addressRegexp['4032']);
                            $patientAddressInfo['AAA49'] = $xzzCityResult['result'];
                            $xzzAreaResult = $this->parseAndTrimAddress($xzzCityResult['address'], $this->addressRegexp['4033']);
                            $patientAddressInfo['AAA50'] = $xzzAreaResult['result'];
                            $patientAddressInfo['AAA15'] = str_replace($patientAddressInfo['AAA48'], '', $patientAddressInfo['AAA15']);
                            $patientAddressInfo['AAA15'] = str_replace($patientAddressInfo['AAA49'], '', $patientAddressInfo['AAA15']);
                            $patientAddressInfo['AAA15'] = str_replace($patientAddressInfo['AAA50'], '', $patientAddressInfo['AAA15']);
                        }
                        $patientAddressInfo['updated_at'] = $currentTime;
                        PatientAddressInfo::query()->updateOrInsert(['ZYH_ID' => $item->unique_value], $patientAddressInfo);
                    }

                    //病案首页（补充信息）
                    $patientAdd = DataSyncService::getInstance()->setByNameSql('patient_add', 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getResult();
                    if (is_array($patientAdd) && count($patientAdd) > 0) $patientAdd = $patientAdd[0];
                    if (!empty($patientAdd)) {
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
                        //unset($patientAdd['ZYH_ID']);
                        $patientAdd['updated_at'] = $currentTime;
                        PatientAdd::query()->updateOrInsert(['ZYH_ID' => $item->unique_value], $patientAdd);
                    }
                    //病案首页（住院信息）
                    $patientHospitalInfo = DataSyncService::getInstance()->setByNameSql('patient_hospital_info', 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getResult();
                    if (is_array($patientHospitalInfo) && count($patientHospitalInfo) > 0)
                        $patientHospitalInfo = $patientHospitalInfo[0];
                    if (!empty($patientHospitalInfo)) {
                        //清洗AEI01C 1.是 2.否 3.-
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
                        //unset($patientHospitalInfo['ZYH_ID']);
                        $patientHospitalInfo['updated_at'] = $currentTime;
                        PatientHospitalInfo::query()->updateOrInsert(['ZYH_ID' => $item->unique_value], $patientHospitalInfo);
                    }

                    //病案首页（联系人信息）
                    $patientContactsInfo = DataSyncService::getInstance()->setByNameSql('patient_contacts_info', empty($exist) ? 0 : 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getResult();
                    if (is_array($patientContactsInfo) && count($patientContactsInfo) > 0)
                        $patientContactsInfo = $patientContactsInfo[0];
                    if (!empty($patientContactsInfo)) {
                        $patientContactsInfo['updated_at'] = $currentTime;
                        PatientContactsInfo::query()->updateOrInsert(['ZYH_ID' => $item->unique_value], $patientContactsInfo);
                    }

                    //病案首页（其他信息）
                    $patientOtherInfo = DataSyncService::getInstance()->setByNameSql('patient_other_info', empty($exist) ? 0 : 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getResult();
                    if (is_array($patientOtherInfo) && count($patientOtherInfo) > 0)
                        $patientOtherInfo = $patientOtherInfo[0];
                    if (!empty($patientOtherInfo)) {
                        $patientOtherInfo['updated_at'] = $currentTime;
                        PatientOtherInfo::query()->updateOrInsert(['ZYH_ID' => $item->unique_value], $patientOtherInfo);
                    }

                    //病案首页工作信息表
                    $patientWorkInfo = DataSyncService::getInstance()->setByNameSql('patient_work_info', empty($exist) ? 0 : 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getResult();
                    if (is_array($patientWorkInfo) && count($patientWorkInfo) > 0)
                        $patientWorkInfo = $patientWorkInfo[0];
                    if (!empty($patientWorkInfo)) {
                        $patientWorkInfo['updated_at'] = $currentTime;
                        PatientWorkInfo::query()->updateOrInsert(['ZYH_ID' => $item->unique_value], $patientWorkInfo);
                    }

                    //病案首页（医生信息）
                    $patientDoctorInfo = DataSyncService::getInstance()->setByNameSql('patient_doctor_info', empty($exist) ? 0 : 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getResult();
                    if (is_array($patientDoctorInfo) && count($patientDoctorInfo) > 0)
                        $patientDoctorInfo = $patientDoctorInfo[0];
                    if (!empty($patientDoctorInfo)) {
                        $patientDoctorInfo['updated_at'] = $currentTime;
                        PatientDoctorInfo::query()->updateOrInsert(['ZYH_ID' => $item->unique_value], $patientDoctorInfo);
                    }

                    //病案首页（费用明细）
                    $patientCostInfo = DataSyncService::getInstance()->setByNameSql('patient_cost_info', empty($exist) ? 0 : 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getResult();
                    if (is_array($patientCostInfo) && count($patientCostInfo) > 0)
                        $patientCostInfo = $patientCostInfo[0];
                    if (!empty($patientCostInfo)) {
                        $patientCostInfo['updated_at'] = $currentTime;
                        PatientCostInfo::query()->updateOrInsert(['ZYH_ID' => $item->unique_value], $patientCostInfo);
                    }
                } else if ($item->tablename == 'zd') {
                    //诊断数据
                    $exist = 1;
                    var_dump($item->unique_key, $item->unique_value);
                    //诊断数据
                    //病案首页（主要诊断）
                    $mainDiagnosis = DataSyncService::getInstance()->setByNameSql('main_diagnosis', empty($exist) ? 0 : 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getResult();
                    if (!empty($mainDiagnosis)) {
                        foreach ($mainDiagnosis as $main) {
                            $main['updated_at'] = date('Y-m-d H:i:s');
                            MainDiagnosis::query()->updateOrInsert(['ZDXH' => $main['ZDXH']], $main);
                        }
                    }

                    //次要诊断
                    $otherDiagnosis = DataSyncService::getInstance()->setByNameSql('other_diagnosis', empty($exist) ? 0 : 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getResult();
                    if (!empty($otherDiagnosis)) {
                        foreach ($otherDiagnosis as $other) {
                            $other['updated_at'] = date('Y-m-d H:i:s');
                            OtherDiagnosis::query()->updateOrInsert(['ZDXH' => $other['ZDXH']], $other);
                        }
                    }
                } else if ($item->tablename == 'ss') {
                    $exist = 1;
                    var_dump($item->unique_key, $item->unique_value);
                    //手术数据
                    //病案首页（主要手术）
                    $mainOperation = DataSyncService::getInstance()->setByNameSql('main_operation', empty($exist) ? 0 : 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getResult();
                    if (!empty($mainOperation)) {
                        foreach ($mainOperation as $main) {
                            $main['updated_at'] = date('Y-m-d H:i:s');
                            MainOperation::query()->updateOrInsert(['SSXH' => $main['SSXH']], $main);
                        }
                    }

                    //次要手术
                    $secondaryOperation = DataSyncService::getInstance()->setByNameSql('secondary_operation', empty($exist) ? 0 : 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getResult();
                    if (!empty($secondaryOperation)) {
                        foreach ($secondaryOperation as $secondary) {
                            $secondary['updated_at'] = date('Y-m-d H:i:s');
                            SecondaryOperation::query()->updateOrInsert(['SSXH' => $secondary['SSXH']], $secondary);
                        }
                    }
                } else if ($item->tablename == 'basy_ys') {
                    //未编目数据
                    $exist = 0;
                    var_dump($item->unique_key, $item->unique_value);
                    //开始同步
                    //首页基本信息
                    DataSyncService::getInstance()->setByNameSql('patient_info_v2', empty($exist) ? 0 : 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getDataSync('patient_info_v2', $item->jm_key);


                    //病案首页（费用明细）
                    DataSyncService::getInstance()->setByNameSql('patient_cost_info_v2', empty($exist) ? 0 : 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getDataSync('patient_cost_info_v2', $item->jm_key);

                    //病案首页（主要诊断）
                    DataSyncService::getInstance()->setByNameSql('patient_info_diagnosis_v2', empty($exist) ? 0 : 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getDataSync('patient_info_diagnosis_v2', $item->jm_key);

                    //病案首页（主要手术）
                    DataSyncService::getInstance()->setByNameSql('patient_info_operation_v2', empty($exist) ? 0 : 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getDataSync('patient_info_operation_v2', $item->jm_key);
                } else if ($item->tablename == 'yzb') {
                    //医嘱数据
                    $exist = 0;
                    var_dump($item->unique_key, $item->unique_value);
                    //yzb
                    $yzb = DataSyncService::getInstance()->setByNameSql('yzb', empty($exist) ? 0 : 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getResult();
                    if (!empty($yzb)) {
                        foreach ($yzb as $y) {
                            $y['updated_at'] = date('Y-m-d H:i:s');
                            Yzb::query()->updateOrInsert(['YZXH' => $y['YZXH']], $y);
                        }
                    }
                } else if ($item->tablename == 'fee') {
                    //费用数据
                    $exist = 0;
                    var_dump($item->unique_key, $item->unique_value);
                    //费用数据
                    $fee = DataSyncService::getInstance()->setByNameSql('fee_detailed', empty($exist) ? 0 : 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getResult();
                    if (!empty($fee)) {
                        foreach ($fee as $f) {
                            $f['updated_at'] = date('Y-m-d H:i:s');
                            FeeDetailed::query()->updateOrInsert(['FYXH' => $f['FYXH']], $f);
                        }
                    }
                } else if ($item->tablename == 'bl01') {
                    //病历
                    $exist = 0;
                    var_dump($item->unique_key, $item->unique_value);

                    $bl01 = DataSyncService::getInstance()->setByNameSql('EMR_BL_BL01', empty($exist) ? 0 : 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getResult();
                    if (!empty($bl01)) {
                        foreach ($bl01 as $bl) {
                            $bl['updated_at'] = date('Y-m-d H:i:s');
                            EMR_BL_BL01::query()->updateOrInsert(['BLBH' => $bl['BLBH']], $bl);
                        }
                    }
                } else if ($item->tablename == 'blsy') {
                    //病历前面
                    $exist = 0;
                    var_dump($item->unique_key, $item->unique_value);

                    $blsy = DataSyncService::getInstance()->setByNameSql('EMR_BL_BLSY', empty($exist) ? 0 : 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getResult();
                    if (!empty($blsy)) {
                        foreach ($blsy as $bl) {
                            $bl['updated_at'] = date('Y-m-d H:i:s');
                            //删除大写bl['UPDATED_AT']
                            unset($bl['UPDATED_AT']);
                            EMR_BL_BLSY::query()->updateOrInsert(['JLXH' => $bl['JLXH']], $bl);
                        }
                    }
                } else if ($item->tablename == 'blxg') {
                    //病历前面
                    $exist = 0;
                    var_dump($item->unique_key, $item->unique_value);

                    DataSyncService::getInstance()->setByNameSql('EMR_BL_BLXG', empty($exist) ? 0 : 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getDataSync('EMR_BL_BLXG', $item->jm_key);
                } else if ($item->tablename == 'hzsq') {
                    //病历前面
                    $exist = 0;
                    var_dump($item->unique_key, $item->unique_value);

                    DataSyncService::getInstance()->setByNameSql('YS_ZY_HZSQ', empty($exist) ? 0 : 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getDataSync('YS_ZY_HZSQ', $item->jm_key);
                } else if ($item->tablename == 'hzyj') {
                    //病历前面
                    $exist = 0;
                    var_dump($item->unique_key, $item->unique_value);

                    DataSyncService::getInstance()->setByNameSql('YS_ZY_HZYJ', empty($exist) ? 0 : 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getDataSync('YS_ZY_HZYJ', $item->jm_key);
                } else if ($item->tablename == 'staff') {
                    //员工
                    $exist = 0;
                    var_dump($item->unique_key, $item->unique_value);

                    DataSyncService::getInstance()->setByNameSql('staff', empty($exist) ? 0 : 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getDataSync('staff', $item->jm_key);
                } else if ($item->tablename == 'dep') {
                    //员工
                    $exist = 0;
                    var_dump($item->unique_key, $item->unique_value);

                    DataSyncService::getInstance()->setByNameSql('department', empty($exist) ? 0 : 1)
                        ->setWhere($item->unique_key, $item->unique_value)
                        ->getDataSync('department', $item->jm_key);
                }

                $item->delete();
            });
        });

        //删除
        /*  DataSyncKey::query()->when($this->startTime && $this->endTime, function ($query) {
             return $query->whereBetween('created_at', [$this->startTime, $this->endTime]);
         })->delete(); */

        $this->info(date("Y-m-d H:i:s") . "::同步完成");
        //DataSyncService::getInstance()->closeConnect();
        return true;
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


    /**
     * 持续数据同步方法，不进行数据监控
     * 直接处理数据同步，跳过监测HIS数据表更新的步骤
     * @author lzh
     * @return bool
     */
    private function _continuousSync()
    {
        $this->info(date("Y-m-d H:i:s") . "::开始持续数据同步模式");


        // 获取同步间隔时间（分钟）
        $syncInterval = intval(RuleWordMap::query()->where('id', 9003)->value('keyword')) * 60;
        if ($syncInterval < 60) {
            $syncInterval = 60; // 默认5分钟
        }

        while (true) {
            try {
                
                $this->info(date("Y-m-d H:i:s") . "::执行数据同步，同步时间：{$this->startTime} 至 {$this->endTime}");

                // 直接执行数据同步，跳过数据监控
                $this->_getDataSyncKey();

                $this->info(date("Y-m-d H:i:s") . "::同步完成，等待 " . ($syncInterval / 60) . " 分钟后继续...");
                sleep($syncInterval);
            } catch (\Exception $e) {
                $this->error("持续同步过程出错: " . $e->getMessage());
                Log::error("持续数据同步出错", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                sleep(60); // 出错后等待5分钟后继续
            }
        }

        return true;
    }

    private function getTableSql(): array
    {
        /* $sql_arr = [
            'ci_mr_fp' => "SELECT 'ZYH_ID' as unique_key ,ID_ENT as unique_value FROM CI_MR_FP",
            'ci_mr_fp_cata' => "SELECT 'ZYH_ID' as unique_key ,ID_ENT as unique_value FROM CI_MR_FP_CATA",
            'ci_mr_fp_pat' => "SELECT 'ZYH_ID' as unique_key ,ID_ENT as unique_value FROM CI_MR_FP_PAT",
            'ci_mr_fp_pat_cata' => "SELECT 'ZYH_ID' as unique_key ,ID_ENT as unique_value FROM CI_MR_FP_PAT_CATA",
            'ci_mr_fp_di' => "SELECT 'ZYH_ID' as unique_key ,ID_ENT as unique_value FROM CI_MR_FP_DI",
            'ci_mr_fp_di_cata' => "SELECT 'ZYH_ID' as unique_key ,ID_ENT as unique_value FROM CI_MR_FP_DI_CATA",
            'ci_mr_fp_xydi' => "SELECT 'ZYH_ID' as unique_key ,ID_ENT as unique_value FROM CI_MR_FP_XYDI",
            'ci_mr_fp_xydi_cata' => "SELECT 'ZYH_ID' as unique_key ,ID_ENT as unique_value FROM CI_MR_FP_XYDI_CATA",
            'ci_mr_fp_sug' => "SELECT 'ZYH_ID' as unique_key ,ID_ENT as unique_value FROM CI_MR_FP_SUG",
            'ci_mr_fp_sug_cata' => "SELECT 'ZYH_ID' as unique_key ,ID_ENT as unique_value FROM CI_MR_FP_SUG_CATA",
            'ci_mr_fp_other' => "SELECT 'ZYH_ID' as unique_key ,ID_ENT as unique_value FROM CI_MR_FP_OTHER",
            'ci_mr_fp_other_cata' => "SELECT 'ZYH_ID' as unique_key ,ID_ENT as unique_value FROM CI_MR_FP_OTHER_CATA",
            'ci_mr_fp_bl' => "SELECT 'ZYH_ID' as unique_key ,ID_ENT as unique_value FROM CI_MR_FP_BL",
            'ci_mr_fp_bl_cata' => "SELECT 'ZYH_ID' as unique_key ,ID_ENT as unique_value FROM CI_MR_FP_BL_CATA"
        ]; */
        $sql_arr = DataSyncKeySetting::query()->get()->toArray();

        return $sql_arr;
    }
}
