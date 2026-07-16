<?php

namespace App\Console\Commands\Format;

use App\Model\Department;
use App\Model\EMR_BL_BL01;
use App\Model\HomeRequestContent;
use App\Model\PatientInfo;
use App\Model\PatientInfoCostV2;
use App\Model\PatientInfoDiagnosisV2;
use App\Model\PatientInfoFeeDetailedV2;
use App\Model\PatientInfoIcuV2;
use App\Model\PatientInfoOperationV2;
use App\Model\PatientInfoV2;
use App\Model\Staff;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BasySz extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'format:basySz';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $this->info('病案首页事中请求数据格式化 - 开始');

        // 获取科室
        $hospitalName = config('confAdmin.hospital_name');
        $depData = Department::query()
            ->where('hospital_name', '=', $hospitalName)
            ->pluck('dep_id', 'dep_name')->toArray();

        while (true) {
            $data = HomeRequestContent::query()
                ->where('is_format', '=', 0)
                ->paginate(100, ['*'], 'page', 1)
                ->toArray();
            if (empty($data['data'])) {
                //增加
                $twoDaysAgo = Carbon::now()->subDays(2);
                HomeRequestContent::query()
                    ->where('is_format', '=', 1)
                    ->where('updated_at', '<=', $twoDaysAgo)
                    ->delete();
                echo "等待中···" . PHP_EOL;
                if (date("H") > 18 || date("H") < 7) {
                    sleep(100);
                } else {
                    sleep(30);
                }
                continue;
            }

            foreach ($data['data'] as $value) {
                echo $value['ZYH'] . PHP_EOL;
                try {

                    // 解析数据
                    $content = json_decode($value['content'], true);

                    // 判断是否为空
                    if (!empty($content) && !empty($content['ZYH'])) {
                        $content['RELATION_FIELD'] = $content['ZYH'];

                        // 主表
                        $patientInfoV2Id = $this->addPatientInfoV2($content, $depData);

                        // 费用
                        $this->addPatientInfoCostV2($patientInfoV2Id, $content);

                        // 诊断
                        $this->addPatientInfoDiagnosisV2($patientInfoV2Id, $content);

                        // 手术
                        $this->addPatientInfoOperationV2($patientInfoV2Id, $content);

                        // 重症
                        $this->addPatientInfoIcuV2($patientInfoV2Id, $content);
                    }

                    // 费用明细
                    if (!empty($patientInfoV2Id)) {
                        $this->addFeeDetailed($value['ZYH'], $patientInfoV2Id, $value['fy_content']);
                    }

                    HomeRequestContent::query()->where('id', '=', $value['id'])->update(['is_format' => 1]);
                } catch (\Throwable $e) {
                    Log::error('格式化失败', ['ZYH' => $value['ZYH'], 'error' => $e->getMessage()]);
                    echo "格式化失败" . PHP_EOL;
                }
            }
        }

        $this->info('病案首页事中请求数据格式化 - 结束');
    }

    // ... existing code ...

    /**
     * 根据住院号执行格式化
     * @param string $zyhNumber 住院号
     * @return array 返回处理结果
     */
    public function formatByZyh($zyhNumber)
    {
        try {
            // 使用日志而不是console输出，避免在非console环境中出错
            Log::info("开始格式化住院号: {$zyhNumber}");

            // 如果确实需要console输出，添加检查
            if (app()->runningInConsole() && method_exists($this, 'info')) {
                $this->info("开始格式化住院号: {$zyhNumber}");
            }

            // 获取科室数据
            $hospitalName = config('confAdmin.hospital_name');
            $depData = Department::query()
                ->where('hospital_name', '=', $hospitalName)
                ->pluck('dep_id', 'dep_name')->toArray();

            // 查找指定住院号的数据
            $homeRequestData = HomeRequestContent::query()
                ->where('ZYH', '=', $zyhNumber)
                ->where('is_format', '=', 0)
                ->first();

            if (!$homeRequestData) {
                return [
                    'success' => false,
                    'message' => "未找到住院号 {$zyhNumber} 的待格式化数据",
                    'zyh' => $zyhNumber
                ];
            }

            // 解析数据
            $content = json_decode($homeRequestData->content, true);

            if (empty($content) || empty($content['ZYH'])) {
                return [
                    'success' => false,
                    'message' => "住院号 {$zyhNumber} 的数据内容为空或格式错误",
                    'zyh' => $zyhNumber
                ];
            }

            $content['RELATION_FIELD'] = $content['ZYH'];
            $patientInfoV2Id = null;

            // 执行格式化流程
            // 1. 主表
            $patientInfoV2Id = $this->addPatientInfoV2($content, $depData);

            if ($patientInfoV2Id) {
                // 2. 费用
                $this->addPatientInfoCostV2($patientInfoV2Id, $content);

                // 3. 诊断
                $this->addPatientInfoDiagnosisV2($patientInfoV2Id, $content);

                // 4. 手术
                $this->addPatientInfoOperationV2($patientInfoV2Id, $content);

                // 5. 重症
                $this->addPatientInfoIcuV2($patientInfoV2Id, $content);

                // 6. 费用明细
                $this->addFeeDetailed($homeRequestData->ZYH, $patientInfoV2Id, $homeRequestData->fy_content);
            }

            // 标记为已格式化
            HomeRequestContent::query()
                ->where('id', '=', $homeRequestData->id)
                ->update(['is_format' => 1]);


            Log::info("住院号 {$zyhNumber} 格式化完成");

            return [
                'success' => true,
                'message' => "住院号 {$zyhNumber} 格式化成功",
                'zyh' => $zyhNumber,
                'patient_info_v2_id' => $patientInfoV2Id,
                'home_request_id' => $homeRequestData->id
            ];
        } catch (\Throwable $e) {
            Log::error('住院号格式化失败', [
                'ZYH' => $zyhNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // 安全的错误输出
            if (app()->runningInConsole() && method_exists($this, 'error')) {
                $this->error("住院号 {$zyhNumber} 格式化失败: " . $e->getMessage());
            }

            return [
                'success' => false,
                'message' => "住院号 {$zyhNumber} 格式化失败: " . $e->getMessage(),
                'zyh' => $zyhNumber,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 批量格式化指定的住院号列表
     * @param array $zyhNumbers 住院号数组
     * @return array 返回批量处理结果
     */
    public function formatByZyhBatch(array $zyhNumbers)
    {
        $results = [];
        $successCount = 0;
        $failCount = 0;

        $this->info("开始批量格式化 " . count($zyhNumbers) . " 个住院号");

        foreach ($zyhNumbers as $zyhNumber) {
            $result = $this->formatByZyh($zyhNumber);
            $results[] = $result;

            if ($result['success']) {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        $this->info("批量格式化完成 - 成功: {$successCount}, 失败: {$failCount}");

        return [
            'total' => count($zyhNumbers),
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'results' => $results
        ];
    }


    /**
     * patient_info_v2 主信息
     * @param $data
     * @param $depData
     * @return int
     */
    public function addPatientInfoV2($data, $depData)
    {
        // 入院时间
        $AAB01 = '';
        if (!empty($data['RYSJ'])) {
            $RYSJ = $data['RYSJ'];
            if (strpos($RYSJ, '年') !== false) {
                $RYSJ = str_replace('年', '-', $RYSJ);
            }
            if (strpos($RYSJ, '月') !== false) {
                $RYSJ = str_replace('月', '-', $RYSJ);
            }
            if (strpos($RYSJ, '日') !== false) {
                $RYSJ = str_replace('日', '', $RYSJ);
            }
            if (strpos($RYSJ, '时') !== false) {
                $RYSJ = str_replace('时', ':', $RYSJ) . '00:00';
            }
            if (stripos($RYSJ, '1970') === false) {
                $AAB01 = $RYSJ;
            }
        }
        // 出院时间
        $AAC01 = '';
        if (!empty($data['CYSJ'])) {
            $CYSJ = $data['CYSJ'];
            if (strpos($CYSJ, '年') !== false) {
                $CYSJ = str_replace('年', '-', $CYSJ);
            }
            if (strpos($CYSJ, '月') !== false) {
                $CYSJ = str_replace('月', '-', $CYSJ);
            }
            if (strpos($CYSJ, '日') !== false) {
                $CYSJ = str_replace('日', '', $CYSJ);
            }
            if (strpos($CYSJ, '时') !== false) {
                $CYSJ = str_replace('时', ':', $CYSJ) . '00:00';
            }
            if (stripos($CYSJ, '1970') === false) {
                $AAC01 = $CYSJ;
            }

            if (stripos($CYSJ, '1970') === false) {
                $AAC01 = $CYSJ;
            }
        }
        // 国籍
        $GJ = '';
        if (!empty($data['GJ'])) {
            $GJ = $data['GJ'] == '中国' || $data['GJ'] == 'CN' ? 1 : 2;
        }
        // 民族
        $MZ = '';
        if (!empty($data['MZ'])) {
            $AAA06C = array_flip(config('dictionaries.AAA06C'));
            $MZ = $AAA06C[$data['MZ']] ?? $data['MZ'];
        }
        // 职业
        $ZY = '';
        if (!empty($data['ZY']) && is_numeric($data['ZY'])) {
            $AAA18C = array_flip(config('dictionaries.AAA18C'));
            $ZY = $AAA18C[$data['ZY']] ?? $data['ZY'];
        } else {
            $ZY = $data['ZY'];
        }
        // 婚姻
        $HY = '';
        if (!empty($data['HY'])) {
            $AAA08C = array_flip(config('dictionaries.AAA08C'));
            $HY = $AAA08C[$data['HY']] ?? $data['HY'];
        }
        // 医疗付款方式
        $YLFKFS = '';
        if (!empty($data['YLFKFS'])) {
            $YLFKFS_ARR = ['01' => 1, '02' => 2, '03' => 3, '04' => 4, '05' => 5, '06' => 6, '07' => 7, '08' => 8, '09' => 9, '99' => 9];
            $YLFKFS = $YLFKFS_ARR[$data['YLFKFS']] ?? $data['YLFKFS'];
        }
        // 联系人关系
        $GX = '';
        if (!empty($data['GX'])) {
            $AAA23C = array_flip(config('dictionaries.AAA23C'));
            $GX = $AAA23C[$data['GX']] ?? $data['GX'];
        }
        // 入院途径
        $RYTJ = '';
        if (!empty($data['RYTJ'])) {
            $AAB06C = array_flip(config('dictionaries.AAB06C'));
            $RYTJ = $AAB06C[$data['RYTJ']] ?? $data['RYTJ'];
        }
        // 出生日期
        $CSRQ = !empty($data['CSRQ']) ? $data['CSRQ'] : '';
        if ($CSRQ) {
            $CSRQ = str_replace('年', '-', $CSRQ);
            $CSRQ = str_replace('月', '-', $CSRQ);
            $CSRQ = str_replace('日', '', $CSRQ);
            $CSRQ = date('Y-m-d', strtotime($CSRQ));
        }
        //格式化成YYYY-MM-DD
        

        //$depData = 

        // 入院标准科别代码
        $RYKB = !empty($data['RYKB']) ? $data['RYKB'] : '';
        //$RYKB = !empty($depData[$RYKB]) ? $depData[$RYKB] : $RYKB;
        $RYKB = (isset($depData[$RYKB]) && !empty($depData[$RYKB])) ? $depData[$RYKB] : $data['RYKB'];
        // 入院院内科室编码
        $RYKSBM = !empty($data['RYKSBM']) ? $data['RYKSBM'] : '';
        $RYKSBM = !empty($depData[$RYKSBM]) ? $depData[$RYKSBM] : $RYKSBM;
        // 入院院内病区编码
        $RYBQBM = !empty($data['RYBQBM']) ? $data['RYBQBM'] : '';
        $RYBQBM = !empty($depData[$RYBQBM]) ? $depData[$RYBQBM] : $RYBQBM;
        // 入院院内病房编码
        $RYBFBM = !empty($data['RYBFBM']) ? $data['RYBFBM'] : '';
        $RYBFBM = !empty($depData[$RYBFBM]) ? $depData[$RYBFBM] : $RYBFBM;
        // 出院院内科室编码
        $CYKSBM = !empty($data['CYKSBM']) ? $data['CYKSBM'] : '';
        $CYKSBM = !empty($depData[$CYKSBM]) ? $depData[$CYKSBM] : $CYKSBM;
        // 出院院内病区编码
        $CYBQBM = !empty($data['CYBQBM']) ? $data['CYBQBM'] : '';
        $CYBQBM = !empty($depData[$CYBQBM]) ? $depData[$CYBQBM] : $CYBQBM;
        // 出院院内病房编码
        $CYBFBM = !empty($data['CYBFBM']) ? $data['CYBFBM'] : '';
        $CYBFBM = !empty($depData[$CYBFBM]) ? $depData[$CYBFBM] : $CYBFBM;
        // 转科科别
        $ZKKB = !empty($data['ZKKB']) ? $data['ZKKB'] : '';
        if (!empty($ZKKB) && is_numeric($ZKKB)) {
            $ZKKB = Department::query()->where('dep_id', '=', $ZKKB)->first(['dep_name'])->toArray()['dep_name'] ?? $ZKKB;
        } else {
            $ZKKB = $ZKKB;
        }
        //$ZKKB = !empty($depData[$ZKKB]) ? $depData[$ZKKB] : $ZKKB;

        // 宁夏保留原始年龄格式，其他站点如果包含“岁”则只保留数字
        if (env('APP_NAME') != 'ningxia' && stripos($data['NL'], '岁') !== false) {
            $data['NL'] = preg_replace('/[^0-9]/', '', $data['NL']);
        }

        // 收集所有需要查询的Staff code
        $staffCodes = [];
        $staffFieldMap = [
            'KZR_BH' => 'KZR',
            'ZRYS_BH' => 'ZRYS',
            'ZZYS_BH' => 'ZZYS',
            'ZYYS_BH' => 'ZYYS',
            'JXYS_BH' => 'JXYS',
            'SXYS_BH' => 'SXYS',
            'ZRHS_BH' => 'ZRHS',
            'ZKYS_BH' => 'ZKYS',
            'ZKHS_BH' => 'ZKHS',
        ];

        foreach ($staffFieldMap as $codeField => $nameField) {
            if (!empty($data[$codeField]) && empty($data[$nameField])) {
                $staffCodes[] = $data[$codeField];
            }
        }

        // 批量获取Staff数据
        $staffMap = [];
        if (!empty($staffCodes)) {
            $staffList = Staff::query()
                ->whereIn('code', $staffCodes)
                ->get(['code', 'name'])
                ->toArray();

            foreach ($staffList as $staff) {
                $staffMap[$staff['code']] = $staff['name'] ?? '';
            }
        }

        // 从缓存的数据中获取名称，如果不存在则默认为空
        foreach ($staffFieldMap as $codeField => $nameField) {
            if (!empty($data[$codeField]) && empty($data[$nameField])) {
                $data[$nameField] = $staffMap[$data[$codeField]] ?? '';
            }
        }

        $insertData = [
            'UNT_ID' => isset($data['HOSPITALID']) ? $data['HOSPITALID'] : '',
            'ZA03' => isset($data['USERNAME']) ? $data['USERNAME'] : '',
            'AAA28' => isset($data['BAH']) ? $data['BAH'] : '',
            'ZYH' => isset($data['RELATION_FIELD']) ? $data['RELATION_FIELD'] : '',
            'cid' => isset($data['cid']) ? $data['cid'] : '',
            'AAA01' => isset($data['XM']) ? $data['XM'] : '',
            'AAB01' => $AAB01,
            'AAC01' => $AAC01,
            'AAA02C' => isset($data['XB']) ? $data['XB'] : '',
            'AAA07' => isset($data['SFZH']) ? $data['SFZH'] : '',
            'AAA03' => $CSRQ,
            'AAA04' => isset($data['NL']) ? $data['NL'] : '',
            'AAA40' => isset($data['BZYZSNL']) ? $data['BZYZSNL'] : '',
            'AAA05C' => $GJ,
            'AAA06C' => $MZ,
            'AAA18C' => $ZY,
            'AAA08C' => $HY,
            'AAA29' => isset($data['ZYCS']) ? $data['ZYCS'] : '',
            'AAC04' => isset($data['SJZYTS']) ? $data['SJZYTS'] : '',
            'AAA27' => isset($data['JKKH']) ? $data['JKKH'] : '',
            'AAA26C' => $YLFKFS,
            'AAA43' => isset($data['GG_SHENG']) ? $data['GG_SHENG'] : '',
            'AAA44' => isset($data['GG_SHI']) ? $data['GG_SHI'] : '',
            'GG' => isset($data['GG']) ? $data['GG'] : '',
            'AAA09' => isset($data['CSD_SHENG']) ? $data['CSD_SHENG'] : '',
            'AAA10' => isset($data['CSD_SHI']) ? $data['CSD_SHI'] : '',
            'AAA11' => isset($data['CSD_XIAN']) ? $data['CSD_XIAN'] : '',
            'CSD_JD' => isset($data['CSD_JD']) ? $data['CSD_JD'] : '',
            'CSD' => isset($data['CSD']) ? $data['CSD'] : '',
            'AAA45' => isset($data['HKDZ_SHENG']) ? $data['HKDZ_SHENG'] : '',
            'AAA46' => isset($data['HKDZ_SHI']) ? $data['HKDZ_SHI'] : '',
            'AAA47' => isset($data['HKDZ_XIAN']) ? $data['HKDZ_XIAN'] : '',
            'AAA33C' => isset($data['HKDZ_JD']) ? $data['HKDZ_JD'] : '',
            'AAA12' => isset($data['HKDZ']) ? $data['HKDZ'] : '',
            'AAA13C' => isset($data['YB2']) ? $data['YB2'] : '',
            'AAA48' => isset($data['XZZ_SHENG']) ? $data['XZZ_SHENG'] : '',
            'AAA49' => isset($data['XZZ_SHI']) ? $data['XZZ_SHI'] : '',
            'AAA50' => isset($data['XZZ_XIAN']) ? $data['XZZ_XIAN'] : '',
            'AAA36C' => isset($data['XZZ_JD']) ? $data['XZZ_JD'] : '',
            'AAA15' => isset($data['XZZ']) ? $data['XZZ'] : '',
            'AAA17C' => isset($data['YB1']) ? $data['YB1'] : '',
            'AAA51' => isset($data['DH']) ? $data['DH'] : '',
            'AAA19' => isset($data['GZDWJDZ']) ? $data['GZDWJDZ'] : '',
            'AAA20' => isset($data['DWDH']) ? $data['DWDH'] : '',
            'AAA21C' => isset($data['YB3']) ? $data['YB3'] : '',
            'AAA22' => isset($data['LXRXM']) ? $data['LXRXM'] : '',
            'AAA23C' => $GX,
            'AAA24' => isset($data['DZ']) ? $data['DZ'] : '',
            'AAA25' => isset($data['DH2']) ? $data['DH2'] : '',
            'ZLLB' => isset($data['ZLLB']) ? $data['ZLLB'] : '',
            'YLZZ' => isset($data['YLZZ']) ? $data['YLZZ'] : '',
            'AAB06C' => $RYTJ,
            'AAB02C' => $RYKB,
            'AAB11C' => $RYKSBM,
            'AAB11N' => isset($data['RYBF']) ? $data['RYBF'] : '',
            'RYBQBM' => $RYBQBM,
            'RYBQMC' => isset($data['RYBQMC']) ? $data['RYBQMC'] : '',
            'RYBFBM' => $RYBFBM,
            'RYBFMC' => isset($data['RYBFMC']) ? $data['RYBFMC'] : '',
            'AAD01C' => $ZKKB,
            'AAC02C' => isset($data['CYKB']) ? $data['CYKB'] : '',
            'AAC11C' => $CYKSBM,
            'AAC11N' => isset($data['CYBF']) ? $data['CYBF'] : '',
            'CYBQBM' => $CYBQBM,
            'CYBQMC' => isset($data['CYBQMC']) ? $data['CYBQMC'] : '',
            'CYBFBM' => $CYBFBM,
            'CYBFMC' => isset($data['CYBFMC']) ? $data['CYBFMC'] : '',
            'ABA01C' => isset($data['JBBM']) ? $data['JBBM'] : '',
            'ABA01N' => isset($data['MZZD']) ? $data['MZZD'] : '',
            'BRLY' => isset($data['BRLY']) ? $data['BRLY'] : '',
            'SSLCLJ' => isset($data['SSLCLJ']) ? $data['SSLCLJ'] : '',
            'BYQK' => isset($data['BYQK']) ? $data['BYQK'] : '',
            'WCQK' => isset($data['WCQK']) ? $data['WCQK'] : '',
            'AFA01' => isset($data['QJCS']) ? $data['QJCS'] : '',
            'AFA02' => isset($data['QJCGCS']) ? $data['QJCGCS'] : '',
            'AAB07D' => isset($data['QZRQ']) ? $data['QZRQ'] : '',
            'WBYY' => isset($data['WBYY']) ? $data['WBYY'] : '',
            'H23' => isset($data['H23']) ? $data['H23'] : '',
            'ZQSS' => isset($data['ZQSS']) ? $data['ZQSS'] : '',
            'ABF01C' => isset($data['JBMM']) ? $data['JBMM'] : '',
            'ABF01N' => isset($data['BLZD']) ? $data['BLZD'] : '',
            'ABF04' => isset($data['BLH']) ? $data['BLH'] : '',
            'AEB02C' => isset($data['YWGM']) ? $data['YWGM'] : '',
            'AEB01' => isset($data['GMYW']) ? $data['GMYW'] : '',
            'AEI01C' => isset($data['SWHZSJ']) ? $data['SWHZSJ'] : '',
            'AEG01C' => isset($data['XX']) ? $data['XX'] : '',
            'AEG02C' => isset($data['RH']) ? $data['RH'] : '',
            'AEG04' => isset($data['HXB']) ? $data['HXB'] : '',
            'AEG05' => isset($data['XXB']) ? $data['XXB'] : '',
            'AEG06' => isset($data['XJ']) ? $data['XJ'] : '',
            'AEG07' => isset($data['QX']) ? $data['QX'] : '',
            'ZTXHS' => isset($data['ZTXHS']) ? $data['ZTXHS'] : '',
            'AEE01_CODE' => isset($data['KZR_BH']) ? $data['KZR_BH'] : '',
            'AEE01' => isset($data['KZR']) ? $data['KZR'] : '',
            'AEE02_CODE' => isset($data['ZRYS_BH']) ? $data['ZRYS_BH'] : '',
            'AEE02' => isset($data['ZRYS']) ? $data['ZRYS'] : '',
            'AEE03_CODE' => isset($data['ZZYS_BH']) ? $data['ZZYS_BH'] : '',
            'AEE03' => isset($data['ZZYS']) ? $data['ZZYS'] : '',
            'AEE04_CODE' => isset($data['ZYYS_BH']) ? $data['ZYYS_BH'] : '',
            'AEE04' => isset($data['ZYYS']) ? $data['ZYYS'] : '',
            'AEE05_CODE' => isset($data['JXYS_BH']) ? $data['JXYS_BH'] : '',
            'AEE05' => isset($data['JXYS']) ? $data['JXYS'] : '',
            'AEE07_CODE' => isset($data['SXYS_BH']) ? $data['SXYS_BH'] : '',
            'AEE07' => isset($data['SXYS']) ? $data['SXYS'] : '',
            'AEE08_CODE' => isset($data['BMY_BH']) ? $data['BMY_BH'] : '',
            'AEE08' => isset($data['BMY']) ? $data['BMY'] : '',
            'AEE10_CODE' => isset($data['ZRHS_BH']) ? $data['ZRHS_BH'] : '',
            'AEE10' => isset($data['ZRHS']) ? $data['ZRHS'] : '',
            'AED02_CODE' => isset($data['ZKYS_BH']) ? $data['ZKYS_BH'] : '',
            'AED02' => isset($data['ZKYS']) ? $data['ZKYS'] : '',
            'AED03_CODE' => isset($data['ZKHS_BH']) ? $data['ZKHS_BH'] : '',
            'AED03' => isset($data['ZKHS']) ? $data['ZKHS'] : '',
            'AED01C' => isset($data['BAZL']) ? $data['BAZL'] : '',
            'AED04' => isset($data['ZKRQ']) ? $data['ZKRQ'] : '',
            'AEM01C' => isset($data['LYFS']) ? $data['LYFS'] : '',
            'YZZY_YLJG' => isset($data['YZZY_YLJG']) ? $data['YZZY_YLJG'] : '',
            'WSY_YLJG' => isset($data['WSY_YLJG']) ? $data['WSY_YLJG'] : '',
            'AEM03C' => isset($data['SFZZYJH']) ? $data['SFZZYJH'] : '',
            'AEM04' => isset($data['MD']) ? $data['MD'] : '',
            'AEJ01' => isset($data['RYQ_T']) ? $data['RYQ_T'] : '',
            'AEJ02' => isset($data['RYQ_XS']) ? $data['RYQ_XS'] : '',
            'AEJ03' => isset($data['RYQ_F']) ? $data['RYQ_F'] : '',
            'AEJ04' => isset($data['RYH_T']) ? $data['RYH_T'] : '',
            'AEJ05' => isset($data['RYH_XS']) ? $data['RYH_XS'] : '',
            'AEJ06' => isset($data['RYH_F']) ? $data['RYH_F'] : '',
            'AEL01' => isset($data['YCFXJ']) ? $data['YCFXJ'] : '',
            'WCFXJ' => isset($data['WCFXJ']) ? $data['WCFXJ'] : '',
            'ADA01' => isset($data['ZFY']) ? $data['ZFY'] : '',
            'ADA0101' => isset($data['ZFJE']) ? $data['ZFJE'] : '',
            'JSF' => isset($data['JSF']) ? $data['JSF'] : '',
            'SF_SYKSS' => isset($data['SF_SYKSS']) ? $data['SF_SYKSS'] : '',
            'SF_SX' => isset($data['SF_SX']) ? $data['SF_SX'] : '',
            'SXFY' => isset($data['SXFY']) ? $data['SXFY'] : '',
            'SF_SW' => isset($data['SF_SW']) ? $data['SF_SW'] : '',
            'TJHL' => isset($data['TJHLTS']) ? $data['TJHLTS'] : '',
            'YJHL' => isset($data['YJHLTS']) ? $data['YJHLTS'] : '',
            'EJHL' => isset($data['EJHLTS']) ? $data['EJHLTS'] : '',
            'SJHL' => isset($data['SJHLTS']) ? $data['SJHLTS'] : '',
            'CRBBG' => isset($data['CRBBG']) ? $data['CRBBG'] : '',
            'AFA03' => isset($data['HBsAg']) ? $data['HBsAg'] : '',
            'AFA04' => isset($data['HCV-Ab']) ? $data['HCV-Ab'] : '',
            'AFA05' => isset($data['HIV-Ab']) ? $data['HIV-Ab'] : '',
            'KSS_FA' => isset($data['KSS_FA']) ? $data['KSS_FA'] : '',
            'KSS_MD' => isset($data['KSS_MD']) ? $data['KSS_MD'] : '',
            'KSS_SFSY' => isset($data['KSS_SFSY']) ? $data['KSS_SFSY'] : '',
            'KSS_SFTS' => isset($data['KSS_SFTS']) ? $data['KSS_SFTS'] : '',
            'AAB07' => isset($data['RYSQK']) ? $data['RYSQK'] : '',
            'AAB07C' => isset($data['RY_ZDBM']) ? $data['RY_ZDBM'] : '',
            'AAB07N' => isset($data['RY_ZDMC']) ? $data['RY_ZDMC'] : '',
            'SFRJSS' => isset($data['SFRJSS']) ? $data['SFRJSS'] : '',
            'SFYFJHECSS' => isset($data['SFYFJHECSS']) ? $data['SFYFJHECSS'] : '',
            'ZYQJSFCXWZ' => isset($data['ZYQJSFCXWZ']) ? $data['ZYQJSFCXWZ'] : '',
            'SFZZJHS' => isset($data['SFZZJHS']) ? $data['SFZZJHS'] : '',
            'SQYYZRMC' => isset($data['SQYYZRMC']) ? $data['SQYYZRMC'] : '',
            'YLTZRMC' => isset($data['YLTZRMC']) ? $data['YLTZRMC'] : '',
            'ZCFS' => isset($data['ZCFS']) ? $data['ZCFS'] : '',
            'ZCZSQYYMC' => isset($data['ZCZSQYYMC']) ? $data['ZCZSQYYMC'] : '',
            'ZCZYLTMC' => isset($data['ZCZYLTMC']) ? $data['ZCZYLTMC'] : '',
            'ABD053' => isset($data['ZDFHQK_LCYBL']) ? $data['ZDFHQK_LCYBL'] : '',
            'ABD051' => isset($data['ZDFHQK_RYYCY']) ? $data['ZDFHQK_RYYCY'] : '',
            'ABD052' => isset($data['ZDFHQK_SQYSH']) ? $data['ZDFHQK_SQYSH'] : '',
            'SQYSH' => isset($data['SQYSH']) ? $data['SQYSH'] : '',
            'LCYBL' => isset($data['LCYBL']) ? $data['LCYBL'] : '',
            'ZJLB' => isset($data['ZJLB']) ? $data['ZJLB'] : '',
            'ZRFS' => isset($data['ZRFS']) ? $data['ZRFS'] : '',
            'AAA42' => isset($data['XSERYTZ']) ? $data['XSERYTZ'] : '',
            'AEN01' => isset($data['XSECSTZ']) ? $data['XSECSTZ'] : '',
            'CYQK' => isset($data['CYQK']) ? $data['CYQK'] : '',
            'QZSJ' => isset($data['QZSJ']) ? $data['QZSJ'] : '',
            'SXFY' => isset($data['SXFY']) ? $data['SXFY'] : '',
            'FYLX' => isset($data['FYLX']) ? $data['FYLX'] : '',
            'HXB' => isset($data['HXB']) ? $data['HXB'] : '',
            'XXB' => isset($data['XXB']) ? $data['XXB'] : '',
            'LCD' => isset($data['LCD']) ? $data['LCD'] : '',
            'XJ' => isset($data['XJ']) ? $data['XJ'] : '',
            'QX' => isset($data['QX']) ? $data['QX'] : '',
            'ZTXHS' => isset($data['ZTXHS']) ? $data['ZTXHS'] : '',
            'ZTX_HXB' => isset($data['ZTX_HXB']) ? $data['ZTX_HXB'] : '',
            'CCS' => isset($data['CCS']) ? $data['CCS'] : '',
            'SSS' => isset($data['SSS']) ? $data['SSS'] : '',
            'HSS' => isset($data['HSS']) ? $data['HSS'] : '',
            'HBS' => isset($data['HBS']) ? $data['HBS'] : '',
            'HCV_AB' => isset($data['HCV_AB']) ? $data['HCV_AB'] : '',
            'HIV_AB' => isset($data['HIV_AB']) ? $data['HIV_AB'] : '',
            'TP_AB' => isset($data['TP_AB']) ? $data['TP_AB'] : '',
            'TNM' => isset($data['TNM']) ? $data['TNM'] : '',
            'SSRJSS' => isset($data['SSRJSS']) ? $data['SSRJSS'] : '',
            'SFFJHZRY' => isset($data['SFFJHZRY']) ? $data['SFFJHZRY'] : '',
        ];

        if (env('APP_NAME') == 'laizhou') {
            if ($insertData['AEM01C'] == 2) {
                $insertData['WSY_YLJG'] = "乡镇卫生院";
            } elseif ($insertData['AEM01C'] == 3) {
                $insertData['WSY_YLJG'] = "转其他医疗机构";
            }
        }


        // 同步非空出院时间到旧版 patient_info，避免空值覆盖已有出院时间。
        if (!empty($insertData['AAC01'])) {
            PatientInfo::query()
                ->where('MED_REC_ID', '=', $insertData['ZYH'])
                ->update([
                    'AAC01' => $insertData['AAC01'],
                ]);
        }

        // 写入数据，存在则更新，不存在则插入。
        PatientInfoV2::query()->updateOrInsert(
            ['ZYH' => $insertData['ZYH']],
            $insertData
        );

        return PatientInfoV2::query()
            ->where('ZYH', '=', $insertData['ZYH'])
            ->value('id');
    }

    /**
     * patient_info_cost_v2 费用
     * @param $patientInfoV2Id
     * @param $data
     * @return bool
     */
    public function addPatientInfoCostV2($patientInfoV2Id, $data)
    {
        $insertData = [
            'patient_info_v2_id' => $patientInfoV2Id,
            'ZYH' => $data['RELATION_FIELD'] ?? '',
            'ADA01' => $data['ZFY'] ?? '',
            'ADA0101' => $data['ZFJE'] ?? '',
            'JSF' => $data['JSF'] ?? '',
            'D11' => $data['YLFUF'] ?? '',
            'D12' => $data['ZLCZF'] ?? '',
            'D13' => $data['HLF'] ?? '',
            'D14' => $data['QTFY'] ?? '',
            'D15' => $data['BLZDF'] ?? '',
            'D16' => $data['SYSZDF'] ?? '',
            'D17' => $data['YXXZDF'] ?? '',
            'D18' => $data['LCZDXMF'] ?? '',
            'D19' => $data['FSSZLXMF'] ?? '',
            'D19X01' => $data['WLZLF'] ?? '',
            'D20' => $data['SSZLF'] ?? '',
            'D20X01' => $data['MAF'] ?? '',
            'D20X02' => $data['SSF'] ?? '',
            'D21' => $data['KFF'] ?? '',
            'D22' => $data['ZYZLF'] ?? '',
            'D23' => $data['XYF'] ?? '',
            'D23X01' => $data['KJYWF'] ?? '',
            'D24' => $data['ZCYF'] ?? '',
            'ZYZJF' => $data['ZYZJF'] ?? '',
            'D25' => $data['ZCYF1'] ?? '',
            'D26' => $data['XF'] ?? '',
            'D27' => $data['BDBLZPF'] ?? '',
            'D28' => $data['QDBLZPF'] ?? '',
            'D29' => $data['NXYZLZPF'] ?? '',
            'D30' => $data['XBYZLZPF'] ?? '',
            'D31' => $data['HCYYCLF'] ?? '',
            'D32' => $data['YYCLF'] ?? '',
            'D33' => $data['YCXYYCLF'] ?? '',
            'D34' => $data['QTF'] ?? ''
        ];

        // 老数据标记逻辑删除
        PatientInfoCostV2::where('ZYH', '=', $data['RELATION_FIELD'])->delete();

        // 写入数据
        return PatientInfoCostV2::query()->insert($insertData);
    }

    /**
     * patient_info_diagnosis_v2 诊断
     * @param $patientInfoV2Id
     * @param $data
     * @return void
     */
    public function addPatientInfoDiagnosisV2($patientInfoV2Id, $data)
    {
        $ZYH = $data['RELATION_FIELD'] ?? '';

        $insertData = [];

        // 主要诊断
        if (!empty($data['ZYZD']) || !empty($data['JBDM'])) {
            $insertData[] = [
                'patient_info_v2_id' => $patientInfoV2Id,
                'ZYH' => $ZYH,
                'ICD10_ID1' => $data['JBDM'] ?? '',
                'ICD10_NAME' => $data['ZYZD'] ?? '',
                'RYBQ' => $data['RYBQ'] ?? '',
                'CYQK' => $data['CYQK'] ?? '',
                'DIA_ORDER' => 1,
                'type' => 1,
            ];
        }

        // 其它诊断
        for ($i = 1; $i <= 40; $i++) {
            if (!empty($data['QTZD' . $i]) || !empty($data['JBDM' . $i])) {
                $insertData[] = [
                    'patient_info_v2_id' => $patientInfoV2Id,
                    'ZYH' => $ZYH,
                    'ICD10_ID1' => $data['JBDM' . $i] ?? '',
                    'ICD10_NAME' => $data['QTZD' . $i] ?? '',
                    'RYBQ' => $data['RYBQ' . $i] ?? '',
                    'CYQK' => $data['CYQK' . $i] ?? '',
                    'DIA_ORDER' => $i + 1,
                    'type' => 2,
                ];
            }
        }
        //删除老数据
        PatientInfoDiagnosisV2::where('ZYH', '=', $ZYH)->delete();
        if (!empty($insertData)) {
            PatientInfoDiagnosisV2::query()->insert($insertData);
        }
    }

    /**
     * patient_info_operation_v2 手术
     * @param $patientInfoV2Id
     * @param $data
     * @return void
     */
    public function addPatientInfoOperationV2($patientInfoV2Id, $data)
    {
        $ZYH = $data['RELATION_FIELD'] ?? '';

        $insertData = [];
        $matchedIndexes = [];

        for ($i = 1; $i <= 41; $i++) {
            if (!empty($data['SSJCZBM' . $i]) || !empty($data['SSJCZMC' . $i])) {
                $matchedIndexes[] = $i;
                $type = $i == 1 ? 1 : 2;
                $insertData[] = [
                    'patient_info_v2_id' => $patientInfoV2Id,
                    'ZYH' => $ZYH,
                    'ICD9_ID1' => $data['SSJCZBM' . $i] ?? '',
                    'ICD9_NAME' => $data['SSJCZMC' . $i] ?? '',
                    'OPE_DATE' =>  $data['SSJCZRQ' . $i],
                    'OPE_LEVEL' => $data['SSJB' . $i],
                    'OPE_MAN_NAME' => $data['SZ' . $i] ?? '',
                    'FRIST_ASSISTANT_NAME' => $data['YZ' . $i] ?? '',
                    'SECOND_ASSISTANT_NAME' => $data['EZ' . $i] ?? '',
                    'QKDJ' => $data['QKDJ' . $i] ?? '',
                    'QKYHLB' => $data['QKYHLB' . $i] ?? '',
                    'HOCUS_WAY_ID' => $data['MZFS' . $i] ?? '',
                    'HOCUS_MAN_NAME' => $data['MZYS' . $i] ?? '',
                    'MZKSSJ' => $data['MZKSSJ' . $i] ?? '',
                    'MZFJ' => $data['MZFJ' . $i] ?? '',
                    'SSCXSJ' => $data['SSCXSJ' . $i] ?? '',
                    'OPE_ORDER' => $i - 1,
                    'SSLX' => $data['SSLX' . $i] ?? '',
                    'INCISION_GRADE_ID' => $data['YHDJ' . $i] ?? '',
                    'RJSS' => $data['RJSS' . $i] ?? '否',
                    'SSKSSJ' => $data['SSKSSJ' . $i] ?? '',
                    'SSJSSJ' => $data['SSJSSJ' . $i] ?? '',
                    'SFFJHZRY' => $data['SFFJHZRY' . $i] ?? '',
                    'SFWRJBF' => $data['SFWRJBF' . $i] ?? '',
                    'SFFJHZSS' => $data['SFFJHZSS' . $i] ?? '',
                    'type' => $type,
                ];
            }
        }

        if (empty($insertData)) {
            Log::warning('病案首页手术数据未生成插入记录', [
                'ZYH' => $ZYH,
                'patient_info_v2_id' => $patientInfoV2Id,
                'matched_indexes' => $matchedIndexes,
                'SSJCZBM1' => $data['SSJCZBM1'] ?? '',
                'SSJCZMC1' => $data['SSJCZMC1'] ?? '',
                'SSJCZBM2' => $data['SSJCZBM2'] ?? '',
                'SSJCZMC2' => $data['SSJCZMC2'] ?? '',
            ]);
            return;
        }

        $existingCount = PatientInfoOperationV2::where('ZYH', '=', $ZYH)->count();
        Log::info('病案首页手术数据准备入库', [
            'ZYH' => $ZYH,
            'patient_info_v2_id' => $patientInfoV2Id,
            'existing_count' => $existingCount,
            'insert_count' => count($insertData),
            'matched_indexes' => $matchedIndexes,
            'first_row' => $insertData[0],
        ]);

        //删除老数据
        $deletedCount = PatientInfoOperationV2::where('ZYH', '=', $ZYH)->delete();
        Log::info('病案首页手术旧数据删除完成', [
            'ZYH' => $ZYH,
            'deleted_count' => $deletedCount,
        ]);

        $missingColumns = [];
        foreach (['SSKSSJ', 'SSJSSJ'] as $column) {
            if (!Schema::hasColumn('patient_info_operation_v2', $column)) {
                $missingColumns[] = $column;
            }
        }

        if (!empty($missingColumns)) {
            Log::warning('病案首页手术数据表缺少待写入字段', [
                'ZYH' => $ZYH,
                'missing_columns' => $missingColumns,
            ]);
        }

        try {
            $result = PatientInfoOperationV2::query()->insert($insertData);
            Log::info('病案首页手术数据插入结果', [
                'ZYH' => $ZYH,
                'patient_info_v2_id' => $patientInfoV2Id,
                'insert_count' => count($insertData),
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('病案首页手术数据插入失败', [
                'ZYH' => $ZYH,
                'patient_info_v2_id' => $patientInfoV2Id,
                'insert_count' => count($insertData),
                'matched_indexes' => $matchedIndexes,
                'first_row' => $insertData[0] ?? [],
                'first_row_keys' => isset($insertData[0]) ? array_keys($insertData[0]) : [],
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * patient_info_icu_v2 重症
     * @param $patientInfoV2Id
     * @param $data
     * @return void
     */
    public function addPatientInfoIcuV2($patientInfoV2Id, $data)
    {
        $ZYH = $data['RELATION_FIELD'] ?? '';

        $insertData = [];
        for ($i = 1; $i <= 5; $i++) {
            if (!empty($data['ZZJHSMC' . $i])) {
                $insertData[] = [
                    'patient_info_v2_id' => $patientInfoV2Id,
                    'ZYH' => $ZYH,
                    'IS_MAIN_WAY' => $data['ZZJHSMC' . $i] ?? '',
                    'IN_TIME' => $data['ZZJHSJRSJ' . $i] ?? '',
                    'OUT_TIME' => $data['ZZJHSZCSJ' . $i] ?? '',
                    'sort' => $i,
                ];
            }
        }

        //删除老数据
        PatientInfoIcuV2::where('ZYH', '=', $ZYH)->delete();
        if (!empty($insertData)) {
            PatientInfoIcuV2::query()->insert($insertData);
        }
    }

    /**
     * fee_detailed 费用明细
     * @param $ZYH
     * @param $patientInfoV2Id
     * @param $fyContent
     * @return void
     */
    public function addFeeDetailed($ZYH, $patientInfoV2Id, $fyContent)
    {
        $insertData = [];
        if (!empty($fyContent)) {
            $fyContent = json_decode($fyContent, true);
            if (!empty($fyContent) && is_array($fyContent)) {
                foreach ($fyContent as $value) {
                    $insertData[] = [
                        'patient_info_id' => $patientInfoV2Id,
                        'ZYH' => $ZYH,
                        'FYXH' => $value['FYXH'] ?? '',
                        'FYMC' => $value['FYMC'] ?? '',
                        'ZFJE' => $value['ZFJE'] ?? '',
                        'JFRQ' => $value['JFRQ'] ?? '',
                        'FYSL' => $value['FYSL'] ?? '',
                        'FYDJ' => $value['FYDJ'] ?? '',
                        'FYKS' => $value['FYKS'] ?? '',
                    ];
                }
            } else {
                Log::error('费用明细数据格式化失败', ['ZYH' => $ZYH, 'fyContent' => $fyContent]);
            }
        }

        //删除老数据
        PatientInfoFeeDetailedV2::where('ZYH', '=', $ZYH)->delete();
        if (!empty($insertData)) {
            $chunkList = array_chunk($insertData, 500);
            foreach ($chunkList as $value) {
                PatientInfoFeeDetailedV2::query()->insert($value);
            }
        }
    }
}
