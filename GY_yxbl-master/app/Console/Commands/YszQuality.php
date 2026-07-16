<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Model\GY_SJQX;
use App\Model\ErrorRule;
use App\Model\TableDict;
use App\Model\PatientInfo;
use App\Model\RuleWordMap;
use Illuminate\Console\Command;
use App\Model\PrimaryKeyControl;
use App\Model\HomeRequestContent;
use Illuminate\Support\Facades\Log;
use App\Services\HomeQualityService;
use App\Console\Commands\Format\BasySz;
use App\Services\DataxSync\DataSyncService;
use App\Http\Controllers\Api\CustomZKController;

class YszQuality extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ysz:quality {startDate?} {endDate?} {zyh?} {ruleid?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '运行时批量质控';

    protected $addressRegexp;
    protected $ageRegexp;
    protected $ageYcRegexp;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
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
        $this->info('运行时质控 - 开始');

        // 获取质控时间
        $startDate = $this->argument('startDate') ?: '';
        $endDate = $this->argument('endDate') ?: '';
        $zyh = $this->argument('zyh') ?: '';
        $ruleid = $this->argument('ruleid') ?: '';
        if (!empty($startDate)) {
            $startTime = $startDate . ' 00:00:00';
        } else {
            // 前一天
            $date = Carbon::parse()->addDay(-1)->toDateString();
            $startTime = $date . ' 00:00:00';
        }

        if (!empty($endDate)) {
            $endTime = $endDate . ' 23:59:59';
        } else {
            // 当天
            $date = Carbon::parse()->addDay(0)->toDateString();
            $endTime = $date . ' 23:59:59';
        }

        // 获取质控规则（只获取运行类规则）
        $errorRuleData = ErrorRule::query()
            ->where('status', '=', 0)
            ->where('node', 'like', "%运行%")
            ->orderBy('id')
            ->get()->toArray();
        $errorRuleData = array_column($errorRuleData, null, 'id');

        if (empty($errorRuleData)) {
            $this->warn('未找到运行类质控规则');
            return 0;
        }

        // 开始循环处理
        $homeQualityService = new HomeQualityService();
        $page = 1;
        $pageSize = 1000;
        $successCount = 0;
        $failCount = 0;

        // 获取科室信息
        $depData = cache()->remember('dep_data_binyi', 7200, function () {
            $depName = config('confAdmin.hospital_name');
            if (class_exists(\App\Model\Department::class)) {
                return \App\Model\Department::query()
                    ->where('hospital_name', '=', $depName)
                    ->pluck('dep_id', 'dep_name')->toArray();
            }
            return [];
        });

        while (true) {
            // 从PatientInfo表中获取数据
            $patientData = PatientInfo::query()->whereNotNull('patient_info.AAB01');
            if (!empty($zyh)) {
                $patientData = $patientData->where('MED_REC_ID', '=', $zyh);
            } else {
                $patientData = $patientData->whereBetween('patient_info.AAB01', [$startTime, $endTime]);
            }
            if (!empty($ruleid)) {
                $patientData = $patientData->leftJoin('error_v2 as hq', 'hq.ZYH', '=', 'patient_info.MED_REC_ID')->where('hq.error_rule', '=', $ruleid)->where('hq.status', '=', 0);
                //echo $patientData->toSql() . "\n";
            }
            $patientData = $patientData->orderBy('patient_info.id', 'desc')
                ->paginate($pageSize, ['patient_info.id', 'patient_info.MED_REC_ID', 'patient_info.AAA28', 'patient_info.AAA29', 'patient_info.AAC01'], 'page', $page)
                ->toArray();

            if (empty($patientData['data'])) {
                break;
            }
            $page++;

            foreach ($patientData['data'] as $value) {
                $ZYH = $value['MED_REC_ID'];
                echo $ZYH . " 开始时间:" . date("Y-m-d H:i:s") . "\n";
                echo $ZYH . " 出院时间:" . $value['AAC01'] . "\n";
                try {
                    $content = $this->zkInfo($ZYH); // 获取质控数据

                    if (isset($content) && !empty($content)) {
                        // 记录请求数据
                        $this->recordRequestContent($ZYH, $content);

                        // 执行格式化
                        try {
                            $basySz = new BasySz();
                            // 设置当前Command的output给BasySz
                            $basySz->setOutput($this->output);
                            $result = $basySz->formatByZyh($ZYH);
                            if ($result['success'] ?? false) {
                                echo $ZYH . " 格式化完成 " . date("Y-m-d H:i:s") . "\n";
                            } else {
                                echo $ZYH . " 格式化失败: " . ($result['message'] ?? '未知错误') . " " . date("Y-m-d H:i:s") . "\n";
                            }
                        } catch (\Exception $e) {
                            Log::error('格式化异常', ['ZYH' => $ZYH, 'error' => $e->getMessage()]);
                            echo $ZYH . " 格式化异常: " . $e->getMessage() . " " . date("Y-m-d H:i:s") . "\n";
                        }

                        // 处理质控数据
                        $required = $homeQualityService->qualityContrl($ZYH, $content, $errorRuleData, $depData);

                        // 执行自定义质控
                        if (class_exists(CustomZKController::class)) {
                            $customZK = new CustomZKController();
                            $customZK->customizeRule($ZYH, $content, 2);
                        }

                        $successCount++;
                        echo $ZYH . " 质控完成 分数:" . ($required['score'] ?? 0) . " " . date("Y-m-d H:i:s") . "\n";
                    } else {
                        echo $ZYH . " 没有质控数据 " . date("Y-m-d H:i:s") . "\n";
                        $failCount++;
                        continue;
                    }
                } catch (\Throwable $th) {
                    echo $ZYH . " 质控异常: " . $th->getMessage() . " " . date("Y-m-d H:i:s") . "\n";
                    Log::error('运行时质控异常', ['ZYH' => $ZYH, 'error' => $th->getMessage()]);
                    $failCount++;
                    continue;
                }
            }
        }

        $this->info("运行时质控 - 完毕 成功:{$successCount} 失败:{$failCount}");
        return 0;
    }

    /**
     * 获取质控信息
     * @param string $ZYH
     * @return array
     */
    public function zkInfo($ZYH)
    {
        if (empty($ZYH)) {
            return [];
        }

        $data = [];
        $data['ZYH'] = $ZYH;

        $patientInfo = PatientInfo::query()->where('MED_REC_ID', '=', $ZYH)->first()->toArray();
        $map9040 = RuleWordMap::query()->where('id', '=', '9040')->first();
        $map9040 = $map9040 ? explode(',', $map9040->keyword) : ['MED_REC_ID'];
        $mapValues = [];
        foreach ($map9040 as $fieldName) {
            $value = isset($patientInfo[$fieldName]) ? $patientInfo[$fieldName] : null;
            $mapValues[] = $value;
        }

        // 获取主信息
        $pkControl = PrimaryKeyControl::query()->where('tablename', '=', 'main_info')->first();
        if (!$pkControl) {
            Log::warning("未找到main_info的主键配置", ['ZYH' => $ZYH]);
            return [];
        }

        $pkField = $pkControl->field;
        $result = DataSyncService::getInstance(1)->setByNameSql('main_info', 0)
            ->setWhere($pkField, $mapValues)
            ->getResult();
        if (empty($result)) {
            Log::warning("未找到住院号对应的数据", $patientInfo);
            return [];
        }

        $main_info = $result[0] ?? [];

        // 先获取field_v2 = patient_info_v2的id
        $id = TableDict::query()->where('field_v2', '=', 'patient_info_v2')->get()->toArray();
        $ids = [];
        foreach ($id as $item) {
            $ids[] = $item['id'];
        }

        // 循环查看里面的key，取表table_dict查询，如果存在就设置data['field_zk']=这个key的值
        $tableDict = TableDict::query()->whereIn('parent_field', $ids)->get()->toArray();
        $tableDict = array_column($tableDict, null, 'field_v2');
        
        foreach ($main_info as $key => $value) {
            if (isset($tableDict[$key])) {
                $main_info[$tableDict[$key]['field_zk']] = $value;
            }
        }

        // 合并数据
        $data = array_merge($data, $main_info);
        
        // 设置hospitalId
        $hospitalId = $data['hospitalId'] ?? '';
        if (!empty($hospitalId)) {
            $data['HOSPITALID'] = $data['hospitalId'] ?? '';
        } else if (!empty($data['HOSPITALID'])) {
            $hospitalId = $data['HOSPITALID'] ?? '';
            $data['hospitalId'] = $data['HOSPITALID'] ?? '';
        } else {
            $data['hospitalId'] = '';
            $data['HOSPITALID'] = '';
        }

        // 清洗数据
        $this->cleanData($data);

        // 获取诊断信息（处理成homeQuality需要的格式）
        $this->getDiagnosisData($ZYH, $data, $mapValues);
        
        // 获取手术信息（处理成homeQuality需要的格式）
        $this->getOperationData($ZYH, $data, $mapValues);

        // 获取费用明细
        $data['fee_detailed'] = $this->getFeeDetailedData($ZYH, $mapValues);

        // 获取医嘱
        $data['yz'] = $this->getYzData($ZYH, $mapValues);

        // 返回与homeQuality相同的数据格式
        return $data;
    }

    /**
     * 清洗数据
     * @param array $data
     */
    protected function cleanData(&$data)
    {

        $appName = env('APP_NAME');


        if($appName == 'dancheng'){
            
            $danchengcity = config('danchengcity');
            $csd = $data['CSD_SHENG'].$data['CSD_SHI'].$data['CSD_XIAN'];
            if ($csd && !empty($danchengcity[$csd])) {
                $data['CSD_SHENG'] = $danchengcity[$csd]['province'];
                $data['CSD_SHI'] = $danchengcity[$csd]['city'];
                $data['CSD_XIAN'] = $danchengcity[$csd]['county'];
            }
            $HKDZ = $data['HKDZ_SHENG'].$data['HKDZ_SHI'].$data['HKDZ_XIAN'];
            if ($HKDZ && !empty($danchengcity[$HKDZ])) {
                $data['GG_SHENG'] = $danchengcity[$HKDZ]['province'];
                $data['HKDZ_SHENG'] = $danchengcity[$HKDZ]['province'];
                $data['GG_SHI'] = $danchengcity[$HKDZ]['city'];
                $data['HKDZ_SHI'] = $danchengcity[$HKDZ]['city'];
                $data['HKDZ_XIAN'] = $danchengcity[$HKDZ]['county'];
            }
            $XZZ = $data['XZZ_SHENG'].$data['XZZ_SHI'].$data['XZZ_XIAN'];
            if ($XZZ && !empty($danchengcity[$XZZ])) {
                $data['XZZ_SHENG'] = $danchengcity[$XZZ]['province'];
                $data['XZZ_SHI'] = $danchengcity[$XZZ]['city'];
                $data['XZZ_XIAN'] = $danchengcity[$XZZ]['county'];
            }

        } elseif ($appName != 'sanyuan') {

            // 清洗病案质量
            $bazl = $data['BAZL'] ?? '';
            if ($bazl == '甲') {
                $data['BAZL'] = 1;
            } elseif ($bazl == '乙') {
                $data['BAZL'] = 2;
            } elseif ($bazl == '丙') {
                $data['BAZL'] = 3;
            }

            // 清洗离院方式
            $lyfs = $data['LYFS'] ?? '';
            if ($lyfs == '医嘱离院') {
                $data['LYFS'] = 1;
            } elseif ($lyfs == '医嘱转院') {
                $data['LYFS'] = 2;
            } elseif ($lyfs == '医嘱转社区卫生服务机构' || $lyfs == '医嘱转乡镇卫生院') {
                $data['LYFS'] = 3;
            } elseif ($lyfs == '非医嘱离院') {
                $data['LYFS'] = 4;
            } elseif ($lyfs == '死亡') {
                $data['LYFS'] = 5;
            } elseif ($lyfs == '其他') {
                $data['LYFS'] = 9;
            }

            // 清洗是否转诊急诊
            $sfzzyjh = $data['SFZZYJH'] ?? '';
            if ($sfzzyjh == '无') {
                $data['SFZZYJH'] = 1;
            } elseif ($sfzzyjh == '有') {
                $data['SFZZYJH'] = 2;
            }

            // 清洗地址信息

            $this->cleanAddress($data);


            // 清洗时间格式
            if (!empty($data['RYSJ'])) {
                $data['RYSJ'] = date('Y-m-d H:i:s', strtotime($data['RYSJ']));
                $data['RYSJ'] = date('Y-m-d H:i:s', strtotime(substr($data['RYSJ'], 0, 19)));
            }
            if (!empty($data['CYSJ'])) {
                $data['CYSJ'] = date('Y-m-d H:i:s', strtotime($data['CYSJ']));
                $data['CYSJ'] = date('Y-m-d H:i:s', strtotime(substr($data['CYSJ'], 0, 19)));
            }

            // 清洗年龄
            $NL = $data['NL'] ?? '';
            if (!empty($NL)) {
                if (strpos($NL, '岁') === false && strpos($NL, '月') === false) {
                    $data['BZYZSNL'] = $NL;
                    $data['NL'] = '';
                }
            }

            $NLM = $data['BZYZSNL'] ?? '';
            if (!empty($NLM)) {
                $plusPos = strpos($NLM, '+');
                if ($plusPos === 0) {
                    $slashPos = strpos($NLM, '/');
                    if ($slashPos !== false && $slashPos > $plusPos) {
                        $extractedText = substr($NLM, $plusPos + 1, $slashPos - $plusPos - 1);
                        $data['BZYZSNL'] = $extractedText;
                    }
                } else {
                    $data['BZYZSNL'] = '';
                    $data['NL'] = $NLM;
                }
            }
        } else {

            // sanyuan 情况下也执行地址清洗
            $this->cleanAddress($data);
        }
        echo "cleanData 方法执行完成\n";
    }

    /**
     * 清洗地址信息
     * @param array $data
     */
    protected function cleanAddress(&$data)
    {

        if (env('APP_NAME') != 'sanyuan') {
            $this->addressRegexp = GY_SJQX::query()->whereIn('id', ['4041', '4042', '4011', '4012', '4021', '4022', '4023', '4031', '4032', '4033'])->pluck('keyword', 'id')->toArray();


            //清洗出生地
            $csdAddress = $data['CSD'];
            //去除所有空格和换行符
            $csdAddress = preg_replace('/\s+/', '', $csdAddress);
            $csdProvinceResult = $this->parseAndTrimAddress($csdAddress, $this->addressRegexp['4041']);
            if (empty($data['CSD_SHENG'])){
                $data['CSD_SHENG'] = $csdProvinceResult['result'];
            }
            $csdCityResult = $this->parseAndTrimAddress($csdProvinceResult['address'], $this->addressRegexp['4042'], true);
            if (empty($data['CSD_SHI'])){
                $data['CSD_SHI'] = $csdCityResult['result'];
            }
            if (empty($data['CSD_XIAN'])){
                $data['CSD_XIAN'] = $csdCityResult['address'];
            }
            $data['CSD'] = str_replace($data['CSD_SHENG'], '', $data['CSD']);
            $data['CSD'] = str_replace($data['CSD_SHI'], '', $data['CSD']);
            $data['CSD'] = str_replace($data['CSD_XIAN'], '', $data['CSD']);

            //清洗籍贯
            $ggAddress = $data['GG'];
            //去除所有空格和换行符
            $ggAddress = preg_replace('/\s+/', '', $ggAddress);
            $ggProvinceResult = $this->parseAndTrimAddress($ggAddress, $this->addressRegexp['4011']);
            if (empty($data['GG_SHENG'])){
                $data['GG_SHENG'] = $ggProvinceResult['result'];
            }
            $ggCityResult = $this->parseAndTrimAddress($ggProvinceResult['address'], $this->addressRegexp['4012'], true);
            if (empty($data['GG_SHI'])){
                $data['GG_SHI'] = $ggCityResult['result'];
            }
            $data['GG'] = str_replace($data['GG_SHENG'], '', $data['GG']);
            $data['GG'] = str_replace($data['GG_SHI'], '', $data['GG']);

            //清洗户籍
            $hjAddress = $data['HKDZ'] ?? '';
            Log::info('清洗户籍 - 原始地址', ['HKDZ' => $hjAddress]);
            //去除所有空格和换行符
            $hjAddress = preg_replace('/\s+/', '', $hjAddress);
            Log::info('清洗户籍 - 去除空格后', ['hjAddress' => $hjAddress]);
            $hjProvinceResult = $this->parseAndTrimAddress($hjAddress, $this->addressRegexp['4021']);
            if (empty($data['HKDZ_SHENG'])){
                $data['HKDZ_SHENG'] = $hjProvinceResult['result'];
            }
            Log::info('清洗户籍 - 省匹配结果', ['HKDZ_SHENG' => $data['HKDZ_SHENG'], 'remainingAddress' => $hjProvinceResult['address']]);
            $hjCityResult = $this->parseAndTrimAddress($hjProvinceResult['address'], $this->addressRegexp['4022'], true);
            if (empty($data['HKDZ_SHI'])){
                $data['HKDZ_SHI'] = $hjCityResult['result'];
            }
            Log::info('清洗户籍 - 市匹配结果', ['HKDZ_SHI' => $data['HKDZ_SHI'], 'remainingAddress' => $hjCityResult['address']]);
            $hjAreaResult = $this->parseAndTrimAddress($hjCityResult['address'], $this->addressRegexp['4023'], false, true);
            if (empty($data['HKDZ_XIAN'])){
                $data['HKDZ_XIAN'] = $hjAreaResult['result'];
            }
            Log::info('清洗户籍 - 县匹配结果', ['HKDZ_XIAN' => $data['HKDZ_XIAN'], 'remainingAddress' => $hjAreaResult['address']]);
            $data['HKDZ'] = str_replace($data['HKDZ_SHENG'], '', $data['HKDZ']);
            $data['HKDZ'] = str_replace($data['HKDZ_SHI'], '', $data['HKDZ']);
            $data['HKDZ'] = str_replace($data['HKDZ_XIAN'], '', $data['HKDZ']);
            Log::info('清洗户籍 - 最终结果', [
                'HKDZ_SHENG' => $data['HKDZ_SHENG'],
                'HKDZ_SHI' => $data['HKDZ_SHI'],
                'HKDZ_XIAN' => $data['HKDZ_XIAN'],
                'HKDZ' => $data['HKDZ']
            ]);

            //清洗现住址
            $xzzAddress = $data['XZZ'] ?? '';
            Log::info('清洗现住址 - 原始地址', ['XZZ' => $xzzAddress]);
            //去除所有空格和换行符
            $xzzAddress = preg_replace('/\s+/', '', $xzzAddress);
            Log::info('清洗现住址 - 去除空格后', ['xzzAddress' => $xzzAddress]);
            $xzzProvinceResult = $this->parseAndTrimAddress($xzzAddress, $this->addressRegexp['4031']);
            if (empty($data['XZZ_SHENG'])){
                $data['XZZ_SHENG'] = $xzzProvinceResult['result'];
            }
            Log::info('清洗现住址 - 省匹配结果', ['XZZ_SHENG' => $data['XZZ_SHENG'], 'remainingAddress' => $xzzProvinceResult['address']]);
            $xzzCityResult = $this->parseAndTrimAddress($xzzProvinceResult['address'], $this->addressRegexp['4032'], true);
            if (empty($data['XZZ_SHI'])){
                $data['XZZ_SHI'] = $xzzCityResult['result'];
            }
            Log::info('清洗现住址 - 市匹配结果', ['XZZ_SHI' => $data['XZZ_SHI'], 'remainingAddress' => $xzzCityResult['address']]);
            $xzzAreaResult = $this->parseAndTrimAddress($xzzCityResult['address'], $this->addressRegexp['4033'], false, true);
            if (empty($data['XZZ_XIAN'])){
                $data['XZZ_XIAN'] = $xzzAreaResult['result'];
            }
            Log::info('清洗现住址 - 县匹配结果', ['XZZ_XIAN' => $data['XZZ_XIAN'], 'remainingAddress' => $xzzAreaResult['address']]);
            $data['XZZ'] = str_replace($data['XZZ_SHENG'], '', $data['XZZ']);
            $data['XZZ'] = str_replace($data['XZZ_SHI'], '', $data['XZZ']);
            $data['XZZ'] = str_replace($data['XZZ_XIAN'], '', $data['XZZ']);
            Log::info('清洗现住址 - 最终结果', [
                'XZZ_SHENG' => $data['XZZ_SHENG'],
                'XZZ_SHI' => $data['XZZ_SHI'],
                'XZZ_XIAN' => $data['XZZ_XIAN'],
                'XZZ' => $data['XZZ']
            ]);
        } else {
            $this->addressRegexp = GY_SJQX::query()->whereIn('id', ['4041', '4042', '4011', '4012', '4021', '4022', '4023', '4031', '4032', '4033'])->pluck('keyword', 'id')->toArray();
            //清洗出生地
            $csdAddress = $data['CSD'];
            //去除所有空格和换行符
            $csdAddress = preg_replace('/\s+/', '', $csdAddress);
            $csdProvinceResult = $this->parseAndTrimAddress($csdAddress, $this->addressRegexp['4041']);
            $data['CSD_SHENG'] = $csdProvinceResult['result'];
            $csdCityResult = $this->parseAndTrimAddress($csdProvinceResult['address'], $this->addressRegexp['4042'], true);
            $data['CSD_SHI'] = $csdCityResult['result'];
            $data['CSD_XIAN'] = $csdCityResult['address'];
            $data['CSD'] = str_replace($data['CSD_SHENG'], '', $data['CSD']);
            $data['CSD'] = str_replace($data['CSD_SHI'], '', $data['CSD']);
            $data['CSD'] = str_replace($data['CSD_XIAN'], '', $data['CSD']);
            //清洗籍贯
            $ggAddress = $data['GG'];
            //去除所有空格和换行符
            $ggAddress = preg_replace('/\s+/', '', $ggAddress);
            $ggProvinceResult = $this->parseAndTrimAddress($ggAddress, $this->addressRegexp['4011']);
            $data['GG_SHENG'] = $ggProvinceResult['result'];
            $ggCityResult = $this->parseAndTrimAddress($ggProvinceResult['address'], $this->addressRegexp['4012'], true);
            $data['GG_SHI'] = $ggCityResult['result'];
            $data['GG'] = str_replace($data['GG_SHENG'], '', $data['GG']);
            $data['GG'] = str_replace($data['GG_SHI'], '', $data['GG']);

            //清洗户籍
            $hjAddress = $data['HKDZ'] ?? '';
            Log::info('清洗户籍(sanyuan) - 原始地址', ['HKDZ' => $hjAddress]);
            //去除所有空格和换行符
            $hjAddress = preg_replace('/\s+/', '', $hjAddress);
            Log::info('清洗户籍(sanyuan) - 去除空格后', ['hjAddress' => $hjAddress]);
            $hjProvinceResult = $this->parseAndTrimAddress($hjAddress, $this->addressRegexp['4021']);
            $data['HKDZ_SHENG'] = $hjProvinceResult['result'];
            Log::info('清洗户籍(sanyuan) - 省匹配结果', ['HKDZ_SHENG' => $data['HKDZ_SHENG'], 'remainingAddress' => $hjProvinceResult['address']]);
            $hjCityResult = $this->parseAndTrimAddress($hjProvinceResult['address'], $this->addressRegexp['4022'], true);
            $data['HKDZ_SHI'] = $hjCityResult['result'];
            Log::info('清洗户籍(sanyuan) - 市匹配结果', ['HKDZ_SHI' => $data['HKDZ_SHI'], 'remainingAddress' => $hjCityResult['address']]);
            $hjAreaResult = $this->parseAndTrimAddress($hjCityResult['address'], $this->addressRegexp['4023'], false, true);
            $data['HKDZ_XIAN'] = $hjAreaResult['result'];
            Log::info('清洗户籍(sanyuan) - 县匹配结果', ['HKDZ_XIAN' => $data['HKDZ_XIAN'], 'remainingAddress' => $hjAreaResult['address']]);
            $data['HKDZ'] = str_replace($data['HKDZ_SHENG'], '', $data['HKDZ']);
            $data['HKDZ'] = str_replace($data['HKDZ_SHI'], '', $data['HKDZ']);
            $data['HKDZ'] = str_replace($data['HKDZ_XIAN'], '', $data['HKDZ']);
            Log::info('清洗户籍(sanyuan) - 最终结果', [
                'HKDZ_SHENG' => $data['HKDZ_SHENG'],
                'HKDZ_SHI' => $data['HKDZ_SHI'],
                'HKDZ_XIAN' => $data['HKDZ_XIAN'],
                'HKDZ' => $data['HKDZ']
            ]);

            //清洗现住址
            $xzzAddress = $data['XZZ'] ?? '';
            Log::info('清洗现住址(sanyuan) - 原始地址', ['XZZ' => $xzzAddress]);
            //去除所有空格和换行符
            $xzzAddress = preg_replace('/\s+/', '', $xzzAddress);
            Log::info('清洗现住址(sanyuan) - 去除空格后', ['xzzAddress' => $xzzAddress]);
            $xzzProvinceResult = $this->parseAndTrimAddress($xzzAddress, $this->addressRegexp['4031']);
            $data['XZZ_SHENG'] = $xzzProvinceResult['result'];
            Log::info('清洗现住址(sanyuan) - 省匹配结果', ['XZZ_SHENG' => $data['XZZ_SHENG'], 'remainingAddress' => $xzzProvinceResult['address']]);
            $xzzCityResult = $this->parseAndTrimAddress($xzzProvinceResult['address'], $this->addressRegexp['4032'], true);
            $data['XZZ_SHI'] = $xzzCityResult['result'];
            Log::info('清洗现住址(sanyuan) - 市匹配结果', ['XZZ_SHI' => $data['XZZ_SHI'], 'remainingAddress' => $xzzCityResult['address']]);
            $xzzAreaResult = $this->parseAndTrimAddress($xzzCityResult['address'], $this->addressRegexp['4033'], false, true);
            $data['XZZ_XIAN'] = $xzzAreaResult['result'];
            Log::info('清洗现住址(sanyuan) - 县匹配结果', ['XZZ_XIAN' => $data['XZZ_XIAN'], 'remainingAddress' => $xzzAreaResult['address']]);
            $data['XZZ'] = str_replace($data['XZZ_SHENG'], '', $data['XZZ']);
            $data['XZZ'] = str_replace($data['XZZ_SHI'], '', $data['XZZ']);
            $data['XZZ'] = str_replace($data['XZZ_XIAN'], '', $data['XZZ']);
            Log::info('清洗现住址(sanyuan) - 最终结果', [
                'XZZ_SHENG' => $data['XZZ_SHENG'],
                'XZZ_SHI' => $data['XZZ_SHI'],
                'XZZ_XIAN' => $data['XZZ_XIAN'],
                'XZZ' => $data['XZZ']
            ]);
        }
    }

    /**
     * 获取诊断数据并合并到data中
     * @param string $ZYH
     * @param array $data
     * @return void
     */
    protected function getDiagnosisData($ZYH, &$data, $mapValues)
    {
        $pkControl = PrimaryKeyControl::query()->where('tablename', '=', 'diagnosis')->first();
        if (!$pkControl) {
            $data['ZYZD'] = '';
            $data['JBDM'] = '';
            $data['RYBQ'] = '';
            $data['CYQK'] = '';
            return;
        }

        $pkField = $pkControl->field;
        $diagnosis = DataSyncService::getInstance(1)->setByNameSql('diagnosis', 0)
            ->setWhere($pkField, $mapValues)
            ->getResult();

        if (empty($diagnosis)) {
            $data['ZYZD'] = '';
            $data['JBDM'] = '';
            $data['RYBQ'] = '';
            $data['CYQK'] = '';
            return;
        }

        // 按照DIA_ORDER正序排序
        if(is_numeric($diagnosis[0]['DIA_ORDER'])){
            usort($diagnosis, function ($a, $b) {
                return $a['DIA_ORDER'] - $b['DIA_ORDER'];
            });
        }

        $diagnosisnum = 1;
        $zzpb1 = false;

        // 判断里面是否有ZZPB=1的
        foreach ($diagnosis as $v) {
            if ($v['ZZPB'] == 1 || $v['ZZPB'] == '1') {
                $zzpb1 = true;
                break;
            }
        }

        foreach ($diagnosis as $k => $item) {
            $ryqk = $item['RYQK'] ?? '';

            if (env('APP_NAME') != 'sanyuan') {
                if ($ryqk == '有') {
                    $ryqk = 1;
                } elseif ($ryqk == '临床未确定') {
                    $ryqk = 2;
                } elseif ($ryqk == '情况不明') {
                    $ryqk = 3;
                } elseif ($ryqk == '无') {
                    $ryqk = 4;
                } elseif (empty($ryqk)) {
                    $ryqk = null;
                }
            }

            if ($zzpb1) {
                if ($item['ZZPB'] == 1) {
                    $data['ZYZD'] = $item['ICD10_NAME'];
                    $data['JBDM'] = $item['ICD10_ID1'];
                    $data['RYBQ'] = $ryqk;
                    $data['CYQK'] = $item['CYQK'];
                } else {
                    $data['QTZD' . $diagnosisnum] = $item['ICD10_NAME'];
                    $data['JBDM' . $diagnosisnum] = $item['ICD10_ID1'];
                    $data['RYBQ' . $diagnosisnum] = $ryqk;
                    $data['CYQK' . $diagnosisnum] = $item['CYQK'];
                    $diagnosisnum++;
                }
            } else {
                if ($k == 0) {
                    $data['ZYZD'] = $item['ICD10_NAME'];
                    $data['JBDM'] = $item['ICD10_ID1'];
                    $data['RYBQ'] = $ryqk;
                    $data['CYQK'] = $item['CYQK'];
                } else {
                    $data['QTZD' . $diagnosisnum] = $item['ICD10_NAME'];
                    $data['JBDM' . $diagnosisnum] = $item['ICD10_ID1'];
                    $data['RYBQ' . $diagnosisnum] = $ryqk;
                    $data['CYQK' . $diagnosisnum] = $item['CYQK'];
                    $diagnosisnum++;
                }
            }
        }
    }

    /**
     * 获取手术数据并合并到data中
     * @param string $ZYH
     * @param array $data
     * @return void
     */
    protected function getOperationData($ZYH, &$data, $mapValues)
    {
            $pkControl = PrimaryKeyControl::query()->where('tablename', '=', 'operation')->first();
            if (!$pkControl) {
                $data['SSJCZBM1'] = '';
                $data['SSJCZMC1'] = '';
                $data['SSJCZRQ1'] = '';
                $data['SSJB1'] = '';
                $data['SZ1'] = '';
                $data['YZ1'] = '';
                $data['EZ1'] = '';
                $data['SSLX1'] = '';
                $data['QKDJ1'] = '';
                $data['YHDJ1'] = '';
                $data['MZFS1'] = '';
                $data['MZYS1'] = '';
                $data['MZKSSJ1'] = '';
                $data['MZFJ1'] = '';
                $data['SSKSSJ1'] = '';
                $data['SSJSSJ1'] = '';
                return;
            }

            $pkField = $pkControl->field;
            $operation = DataSyncService::getInstance(1)->setByNameSql('operation', 0)
                ->setWhere($pkField, $mapValues)
                ->getResult();

            if (empty($operation)) {
                $data['SSJCZBM1'] = '';
                $data['SSJCZMC1'] = '';
                $data['SSJCZRQ1'] = '';
                $data['SSJB1'] = '';
                $data['SZ1'] = '';
                $data['YZ1'] = '';
                $data['EZ1'] = '';
                $data['SSLX1'] = '';
                $data['QKDJ1'] = '';
                $data['YHDJ1'] = '';
                $data['MZFS1'] = '';
                $data['MZYS1'] = '';
                $data['MZKSSJ1'] = '';
                $data['MZFJ1'] = '';
                $data['SSKSSJ1'] = '';
                $data['SSJSSJ1'] = '';
                return;
            }

            // 按照OPE_ORDER正序排序
            if(is_numeric($operation[0]['OPE_ORDER'])){
                usort($operation, function ($a, $b) {
                    return $a['OPE_ORDER'] - $b['OPE_ORDER'];
                });
            }

            // 将第一个设置为0
            $operation[0]['OPE_ORDER'] = 0;

            $operationnum = 2;

            foreach ($operation as $item) {
                $opedate = $item['OPE_DATE'] ?? '';
                if (!empty($opedate)) {
                    $item['OPE_DATE'] = date('Y-m-d', strtotime($opedate));
                }

                if ($item['OPE_ORDER'] == 0) {
                    $data['SSJCZBM1'] = $item['ICD9_ID1'] ?? '';
                    $data['SSJCZMC1'] = $item['ICD9_NAME'] ?? '';
                    $data['SSJCZRQ1'] = $item['OPE_DATE'] ?? '';
                    $data['SSJB1'] = $item['OPE_LEVEL'] ?? '';
                    $data['SZ1'] = $item['OPE_MAN_NAME'] ?? '';
                    $data['YZ1'] = $item['FRIST_ASSISTANT_NAME'] ?? '';
                    $data['EZ1'] = $item['SECOND_ASSISTANT_NAME'] ?? '';
                    $data['SSLX1'] = $item['SSLX'] ?? '';
                    $data['QKDJ1'] = $item['QKDJ'] ?? '';
                    $data['YHDJ1'] = $item['YHDJ'] ?? '';
                    $data['MZFS1'] = $item['HOCUS_WAY_ID'] ?? '';
                    $data['MZYS1'] = $item['HOCUS_MAN_NAME'] ?? '';
                    $data['MZKSSJ1'] = $item['MZKSSJ'] ?? '';
                    $data['MZFJ1'] = $item['MZFJ'] ?? '';
                    $data['SSKSSJ1'] = $item['SSKSSJ'] ?? '';
                    $data['SSJSSJ1'] = $item['SSJSSJ'] ?? '';
                } else {
                    $data['SSJCZBM' . $operationnum] = $item['ICD9_ID1'] ?? '';
                    $data['SSJCZMC' . $operationnum] = $item['ICD9_NAME'] ?? '';
                    $data['SSJCZRQ' . $operationnum] = $item['OPE_DATE'] ?? '';
                    $data['SSJB' . $operationnum] = $item['OPE_LEVEL'] ?? '';
                    $data['SZ' . $operationnum] = $item['OPE_MAN_NAME'] ?? '';
                    $data['YZ' . $operationnum] = $item['FRIST_ASSISTANT_NAME'] ?? '';
                    $data['EZ' . $operationnum] = $item['SECOND_ASSISTANT_NAME'] ?? '';
                    $data['SSLX' . $operationnum] = $item['SSLX'] ?? '';
                    $data['QKDJ' . $operationnum] = $item['QKDJ'] ?? '';
                    $data['YHDJ' . $operationnum] = $item['YHDJ'] ?? '';
                    $data['MZFS' . $operationnum] = $item['HOCUS_WAY_ID'] ?? '';
                    $data['MZYS' . $operationnum] = $item['HOCUS_MAN_NAME'] ?? '';
                    $data['MZKSSJ' . $operationnum] = $item['MZKSSJ'] ?? '';
                    $data['MZFJ' . $operationnum] = $item['MZFJ'] ?? '';
                    $data['SSKSSJ' . $operationnum] = $item['SSKSSJ'] ?? '';
                    $data['SSJSSJ' . $operationnum] = $item['SSJSSJ'] ?? '';
                    $operationnum++;
                }
            }
    }

    /**
     * 获取费用明细数据
     * @param string $ZYH
     * @return array
     */
    protected function getFeeDetailedData($ZYH, $mapValues)
    {
        $pkControl = PrimaryKeyControl::query()->where('tablename', '=', 'fee_detailed')->first();
        if (!$pkControl) {
            return [];
        }

        $field = $pkControl->field;
        $feeDetailedData = DataSyncService::getInstance(2)->setByNameSql('fee_detailed', 0)
            ->setWhere($field, $mapValues)
            ->getResult();

        return $feeDetailedData ?? [];
    }

    /**
     * 获取医嘱数据
     * @param string $ZYH
     * @return array
     */
    protected function getYzData($ZYH, $mapValues)
    {
        $pkControl = PrimaryKeyControl::query()->where('tablename', '=', 'yzb')->first();
        if (!$pkControl) {
            return [];
        }

        $field = $pkControl->field;
        $yzData = DataSyncService::getInstance(2)->setByNameSql('yzb', 0)
            ->setWhere($field, $mapValues)
            ->getResult();

        return $yzData ?? [];
    }

    /**
     * 清洗地址
     * @param string $address
     * @param string $regexp
     * @return array
     */
    protected function parseAndTrimAddress($address, $regexp, $isCityLevel = false, $isAreaLevel = false)
    {
        Log::info('parseAndTrimAddress 开始', [
            'address' => $address,
            'regexp' => $regexp,
            'isCityLevel' => $isCityLevel,
            'isAreaLevel' => $isAreaLevel
        ]);
        
        // 如果是市级匹配，需要特殊处理县级市的情况
        // 县级市通常格式为：地级市 + 县级市（如：青岛市胶州市）
        // 需要确保只匹配地级市，不匹配县级市
        if ($isCityLevel) {
            // 对于市级匹配，需要确保只匹配到第一个"市"
            // 检查地址中是否有多个"市"，如果有，需要特殊处理
            $cityCount = mb_substr_count($address, '市');
            Log::info('市级匹配 - 检查市的数量', ['cityCount' => $cityCount, 'address' => $address]);
            if ($cityCount > 1) {
                // 如果有多个"市"，找到第一个"市"的位置
                $firstCityPos = mb_strpos($address, '市');
                if ($firstCityPos !== false) {
                    // 截取到第一个"市"之后的位置（包含"市"）
                    $tempAddress = mb_substr($address, 0, $firstCityPos + 1);
                    Log::info('市级匹配 - 截取临时地址', ['tempAddress' => $tempAddress, 'firstCityPos' => $firstCityPos]);
                    // 在截取的地址中匹配市
                    preg_match("{$regexp}", $tempAddress, $matches);
                    Log::info('市级匹配 - 正则匹配结果', ['matches' => $matches]);
                    
                    // 如果匹配成功，使用匹配结果
                    if (!empty($matches[1])) {
                        $result = $matches[1];
                        $address = preg_replace('/' . preg_quote($result, '/') . '/', '', $address, 1);
                        Log::info('市级匹配 - 匹配成功', ['result' => $result, 'remainingAddress' => $address]);
                        return ['result' => $result, 'address' => $address];
                    }
                }
            }
        }
        
        // 默认匹配逻辑
        preg_match("{$regexp}", $address, $matches);
        $result = isset($matches[1]) ? $matches[1] : '';
        Log::info('默认匹配逻辑', ['matches' => $matches, 'result' => $result]);
        
        // 如果是区/县级别匹配，需要特殊处理
        if ($isAreaLevel) {
            // 如果匹配结果中包含"市"，需要判断是否是"市中区"、"市辖区"等特殊情况
            // 如果"市"在开头位置，说明这是正常的区名（如"市中区"），不应该走县级市的逻辑
            // 只有当"市"不在开头时，才需要重新处理（如"新泰市华恒社区"）
            if (!empty($result) && mb_strpos($result, '市') !== false) {
                $cityPosInResult = mb_strpos($result, '市');
                // 如果"市"在开头位置（位置0），说明是"市中区"等特殊情况，不需要重新处理
                if ($cityPosInResult === 0) {
                    Log::info('区/县级别匹配 - 匹配结果"市"在开头，是正常区名（如"市中区"），不需要重新处理', ['result' => $result, 'address' => $address]);
                } else {
                    // "市"不在开头，说明可能是县级市的情况，需要重新处理
                    // 例如："新泰市华恒社区" 被错误匹配为整个字符串，应该只匹配"新泰市"
                    Log::info('区/县级别匹配 - 匹配结果包含"市"且不在开头，需要重新处理', ['result' => $result, 'address' => $address]);
                    // 找到地址中第一个"市"的位置，只匹配到该位置
                    $firstCityPos = mb_strpos($address, '市');
                    if ($firstCityPos !== false) {
                        // 截取到第一个"市"之后的位置（包含"市"），这就是县级市名称
                        $result = mb_substr($address, 0, $firstCityPos + 1);
                        // 从原地址中移除匹配到的县级市
                        $address = mb_substr($address, $firstCityPos + 1);
                        Log::info('区/县级别匹配 - 重新匹配县级市成功', ['result' => $result, 'remainingAddress' => $address, 'firstCityPos' => $firstCityPos]);
                        return ['result' => $result, 'address' => $address];
                    }
                }
            }
            
            // 检查匹配结果是否过长或包含详细地址特征（如"社区"、"街道"等）
            // 如果匹配结果明显过长（超过10个字符），或者包含详细地址特征，说明可能匹配过度
            if (!empty($result)) {
                $resultLength = mb_strlen($result);
                $hasDetailAddress = false;
                $detailKeywords = ['社区', '街道', '路', '街', '村', '组', '号', '栋', '单元', '室'];
                foreach ($detailKeywords as $keyword) {
                    if (mb_strpos($result, $keyword) !== false) {
                        $hasDetailAddress = true;
                        break;
                    }
                }
                
                // 如果匹配结果过长或包含详细地址特征，需要重新处理
                if ($resultLength > 10 || $hasDetailAddress) {
                    Log::info('区/县级别匹配 - 匹配结果可能过长或包含详细地址，需要重新处理', [
                        'result' => $result,
                        'resultLength' => $resultLength,
                        'hasDetailAddress' => $hasDetailAddress,
                        'address' => $address
                    ]);
                    
                    // 找到第一个区/县/旗的位置，只匹配到该位置
                    $firstAreaPos = false;
                    $areaKeywords = ['区', '县', '旗'];
                    foreach ($areaKeywords as $keyword) {
                        $pos = mb_strpos($address, $keyword);
                        if ($pos !== false) {
                            if ($firstAreaPos === false || $pos < $firstAreaPos) {
                                $firstAreaPos = $pos;
                            }
                        }
                    }
                    
                    if ($firstAreaPos !== false) {
                        // 截取到第一个区/县/旗之后的位置（包含区/县/旗），这就是区/县名称
                        $result = mb_substr($address, 0, $firstAreaPos + 1);
                        // 从原地址中移除匹配到的区/县
                        $address = mb_substr($address, $firstAreaPos + 1);
                        Log::info('区/县级别匹配 - 重新匹配区/县成功', ['result' => $result, 'remainingAddress' => $address, 'firstAreaPos' => $firstAreaPos]);
                        return ['result' => $result, 'address' => $address];
                    }
                }
            }
            
            // 如果原正则没有匹配到结果，但剩余地址中包含"市"，尝试匹配县级市
            if (empty($result) && mb_strpos($address, '市') !== false) {
                Log::info('区/县级别匹配 - 尝试匹配县级市', ['address' => $address]);
                // 对于县级市，需要确保只匹配到第一个"市"，避免匹配到详细地址
                // 例如："新泰市华恒社区" 应该只匹配"新泰市"，不匹配"新泰市华恒社区"
                // 直接找到第一个"市"的位置，然后截取到该位置（包含"市"）
                $firstCityPos = mb_strpos($address, '市');
                if ($firstCityPos !== false) {
                    // 截取到第一个"市"之后的位置（包含"市"），这就是县级市名称
                    $result = mb_substr($address, 0, $firstCityPos + 1);
                    // 从原地址中移除匹配到的县级市
                    $address = mb_substr($address, $firstCityPos + 1);
                    Log::info('区/县级别匹配 - 县级市匹配成功', ['result' => $result, 'remainingAddress' => $address, 'firstCityPos' => $firstCityPos]);
                    return ['result' => $result, 'address' => $address];
                }
            }
        }
        
        if (!empty($result)) {
            $address = preg_replace('/' . preg_quote($result, '/') . '/', '', $address, 1);
        }
        Log::info('parseAndTrimAddress 结束', ['result' => $result, 'address' => $address]);
        return ['result' => $result, 'address' => $address];
    }

    /**
     * 清洗年龄
     * @param string $age
     * @param string $regexp
     * @param string|null $ycRegexp
     * @return array
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
     * 记录请求内容
     * @param string $ZYH
     * @param array $data
     * @return void
     */
    public function recordRequestContent($ZYH, $data)
    {
        try {
            $hospitalNameInData = $data['USERNAME'] ?? '';

            $insertData = [
                'hospital_name' => $hospitalNameInData,
                'ZYH' => $ZYH,
                'content' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'fy_content' => !empty($data['fee_detailed']) ? json_encode($data['fee_detailed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '[]',
                'yz_content' => !empty($data['yz']) ? json_encode($data['yz'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '[]',
            ];

            if (class_exists(HomeRequestContent::class)) {
                HomeRequestContent::query()->insert($insertData);
            } else {
                Log::error("HomeRequestContent model not found. Skipping insert.");
            }
        } catch (\Exception $e) {
            Log::error('记录 HomeRequestContent 失败', ['error' => $e->getMessage(), 'ZYH' => $ZYH]);
        }
    }
}
