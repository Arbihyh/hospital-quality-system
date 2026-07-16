<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use App\Model\Indicator;
use App\Model\IndicatorUploadHistory;
use App\Model\IndexCatalogRg;
use App\Utils\SM3Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IndicatorController extends Controller
{

    private const SIGN_FIELD = 'sign';
    private const SIGN_STR_SIGN_KEY = '&sign_key=';
    private const NULL = 'null';

    /**
     * 生成签名
     * @param string $json 请求参数的 JSON 字符串
     * @param string $signKey 签名密钥
     * @return string
     */
    private function sign($json, $signKey)
    {
        // 解析 JSON 为数组
        $params = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON');
        }

        // 获取排序后的参数
        $sortedParams = $this->getParamSimple($params);

        // 构建签名字符串
        $signStr = '';
        foreach ($sortedParams as $key => $value) {
            if ($key === self::SIGN_FIELD) {
                continue;
            }

            if (empty($value) || $value === self::NULL) {
                continue;
            }

            $signStr .= $key . '=' . $value;
        }

        // 添加签名密钥
        $signStr .= self::SIGN_STR_SIGN_KEY . $signKey;


        $sm3 = new SM3Util();
        return $sm3->sign($signStr);
    }

    /**
     * ASCII 码排序处理参数
     * @param array $params 参数数组
     * @return array
     */
    private function getParamSimple($params)
    {
        $sortedParams = [];

        // 按 ASCII 码升序排序
        ksort($params);

        foreach ($params as $key => $value) {
            if (is_array($value) || $key === 'data') {
                // 数组和 data 字段转为 JSON
                $sortedParams[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
            } else {
                $sortedParams[$key] = (string) $value;
            }
        }

        return $sortedParams;
    }


    public function sptUploadData(Request $request)
    {
        // 这是外网测试地址
        /*         $url = "https://wjsyb.msunsoft.com/medicalManagement";
        $passCode = "bzyxyytfsyy";
        $signKey = "Qlk570%#"; */


        // 这是正式地址 -- 卫生专网地址
        /* $url = "http://11.1.3.75:8085/medicalManagement";
        $passCode = "bzyxyytfsyy";
        $signKey = "Urd133%%";
        $requestId = Str::uuid()->toString();
        $period = "2025-08"; */
        $url = "http://11.1.3.75:8085/medicalManagement"; 
        $passCode = "jnsdsrmyy";
        $signKey = "Hus765$$";
        $requestId = Str::uuid()->toString();
        $period = trim((string)$request->input('period', ''));

        if ($period === '') {
            return [
                'code' => 400,
                'message' => 'period不能为空'
            ];
        }

        $data = $this->getIndicatorData($period);
        $newData = [];
        
        // 需要保留浮点数的比率字段
        $floatFields = ['ssjssbfz_fm', 'ssjssbfz_fz', 'ssjsshzsw_fm', 'ssjsshzsw_fz'];
        
        foreach ($data as $k => $v) {
            // 比率字段保留浮点数（保留4位小数），其他字段转换为整数
            if (in_array($k, $floatFields)) {
                $newData[$k] = floatval($v);
            } else {
                $newData[$k] = intval($v);
            }
        }
        // 构建请求参数
        $params = [
            'request_id' => $requestId,
            'pass_code' => $passCode,
            'timestamp' => date('YmdHis'),
            'period' => $period,
            'data' => $newData
        ];

        ksort($params);
        // 生成签名
        $params['sign'] = $this->sign(json_encode($params), $signKey);
        // 发送请求前作废一次
        $this->invalidateIndicator($params['period']);
        // 发送请求
        $url .= "/hxzdJkdy/hxzdUpload";
        $client = new Client(['verify' => false]);
        try {
            $response = $client->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $params
            ]);
            IndicatorUploadHistory::query()->create([
                'period' => $period,
                'upload_time' => date('Y-m-d H:i:s'),
                'data' => json_encode($newData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            return ['code' => 200, 'data' => json_decode($response->getBody()->getContents(), true), 'message' => ['data' => $newData, 'period' => $period]];
        } catch (\Exception $e) {
            return [
                'code' => 500,
                'message' => '请求失败：' . $e->getMessage()
            ];
        }
    }

    public function sptUploadHistoryList(Request $request)
    {
        $period = trim((string)$request->input('period', ''));
        $page = max(1, (int)$request->input('page', 1));
        $pageSize = max(1, (int)$request->input('page_size', $request->input('pageSize', 10)));

        $query = IndicatorUploadHistory::query()
            ->when($period !== '', function ($builder) use ($period) {
                return $builder->where('period', $period);
            })
            ->orderBy('id', 'desc');

        $count = $query->count();
        $list = $query->forPage($page, $pageSize)->get()->toArray();

        foreach ($list as &$item) {
            $item['data'] = !empty($item['data']) ? json_decode($item['data'], true) : [];
        }
        unset($item);

        return [
            'code' => 200,
            'data' => [
                'count' => $count,
                'list' => $list,
            ],
        ];
    }




    /**
     * 获取指标数据
     */
    private function getIndicatorData($period)
    {

        $year = date('Y', strtotime($period));
        $month = date('m', strtotime($period));
        // 特殊处理 sjsjsssjkzl 指标

        $indicatorData = Indicator::query()
            ->selectRaw("sum(hzry48xsnzk_fz) as hzryzk48_fz,sum(hzry48xsnzk_fm) as hzryzk48_fm,
                        sum(hzry8xsncf_fz) as hzrycf8_fz,sum(hzry8xsncf_fm) as hzrycf8_fm,
                        sum(sjyscf_fz) as sjyscf_fz,sum(sjyscf_fm) as sjyscf_fm,
                        sum(ejyscf_fz) as ejyscf_fz,sum(ejyscf_fm) as ejyscf_fm,
                        sum(szsqcf_fz) as szsqcf24_fz,sum(szsqcf_fm) as szsqcf24_fm,
                        sum(szshcf24_fz) as szshcf24_fz,sum(szshcf24_fm) as szshcf24_fm,
                        sum(sstysqs_fz) as sstysqs_fz,sum(sstysqs_fm) as sstysqs_fm,
                        sum(sstysqssj_fz) as sstysqssj_fz,sum(sstysqssj_fm) as sstysqssj_fm,
                        sum(sstysqssx_fz) as sstysqssx_fz,sum(sstysqssx_fm) as sstysqssx_fm,
                        sum(zlfajcqyssh_fz) as zlfajcqyssh_fz,sum(zlfajcqyssh_fm) as zlfajcqyssh_fm,
                        sum(jhzjsdwl_fz) as jhzjsdwl_fz,sum(jhzjsdwl_fm) as jhzjsdwl_fm,
                        sum(pthzjswcl_fz) as pthzjswcl_fz,sum(pthzjswcl_fm) as pthzjswcl_fm,
                        sum(ejhlsjhlcy_fz) as esjhlcy_fz,sum(ejhlsjhlcy_fm) as esjhlcy_fm,
                        sum(zyhzzlnljspg_fz) as zyhzzlnljspg_fz,sum(zyhzzlnljspg_fm) as zyhzzlnljspg_fm,
                        sum(zbqjzlcz_fz) as zbqjzlcz_fz,sum(zbqjzlcz_fm) as zbqjzlcz_fm,
                        sum(sqfjhzssbltl_fz) as sqfjhzssbltl_fz,sum(sqfjhzssbltl_fm) as sqfjhzssbltl_fm,
                        sum(shfjhzssbltl_fz) as shfjhzssbltl_fz,sum(shfjhzssbltl_fm) as shfjhzssbltl_fm,
                        sum(shshbfzbltl_fz) as shshbfzbltl_fz,sum(shshbfzbltl_fm) as shshbfzbltl_fm,
                        sum(ynbltlgf_fz) as ynbltlgf_fz,sum(ynbltlgf_fm) as ynbltlgf_fm,
                        sum(qjjljsjl_fz) as qjjljsjl_fz,sum(qjjljsjl_fm) as qjjljsjl_fm,
                        sum(qjjlsh_fz) as qjjlsh_fz,sum(qjjlsh_fm) as qjjlsh_fm,
                        sum(qjcg_fz) as qjcgl_fz,sum(qjcg_fm) as qjcgl_fm,
                        sum(sqtlwcl_fz) as sqtlwcl_fz,sum(sqtlwcl_fm) as sqtlwcl_fm,
                        sum(sqtlrygfcyl_fz) as sqtlrygfcyl_fz,sum(sqtlrygfcyl_fm) as sqtlrygfcyl_fm,
                        sum(sjsssqdxktl_fz) as sjsssqdxktl_fz,sum(sjsssqdxktl_fm) as sjsssqdxktl_fm,
                        sum(ssyzgfkjl_fz) as ssyzgfkjl_fz,sum(ssyzgfkjl_fm) as ssyzgfkjl_fm,
                        sum(swtljswcl_fz) as swtljswcl_fz,sum(swtljswcl_fm) as swtljswcl_fm,
                        sum(kzrzcswtll_fz) as kzrzcswtll_fz,sum(kzrzcswtll_fm) as kzrzcswtll_fm,
                        sum(cqyzdrzz_fz) as cqyzdrzz_fz,sum(cqyzdrzz_fm) as cqyzdrzz_fm,
                        sum(szfhsqmlyzl_fz) as szfhsqmlyzl_fz,sum(szfhsqmlyzl_fm) as szfhsqmlyzl_fm,
                        sum(ssyssssjch_fz) as ssyssssjch_fz,sum(ssyssssjch_fm) as ssyssssjch_fm,
                        sum(sjsjsssjkzl_fz) as ssjsssjkz_fz,sum(sjsjsssjkzl_fm) as ssjsssjkz_fm,
                        sum(xjshxxmssr_fz) as xjshxxmssr_fz,sum(xjshxxmssr_fm) as xjshxxmssr_fm,
                        sum(wjzjlfhl_fz) as wjzjlfhl_fz,sum(wjzjlfhl_fm) as wjzjlfhl_fm,
                        sum(ryjlxswcl24_fz) as ryjlxswcl24_fz,sum(ryjlxswcl24_fm) as ryjlxswcl24_fm,
                        sum(scbcjlwc8_fz) as scbcjlwc8_fz,sum(scbcjlwc8_fm) as scbcjlwc8_fm,
                        sum(ssjlwc24_fz) as ssjlwc24_fz,sum(ssjlwc24_fm) as ssjlwc24_fm,
                        sum(rjssblsqtl_fz) as rjssblsqtl_fz,sum(rjssblsqtl_fm) as rjssblsqtl_fm,
                        sum(rjssblsqpg_fz) as rjssblsqpg_fz,sum(rjssblsqpg_fm) as rjssblsqpg_fm,
                        sum(kjywcfqlshg_fz) as kjywcfqlshg_fz,sum(kjywcfqlshg_fm) as kjywcfqlshg_fm,
                        sum(tsjkjywhz_fz) as tsjkjywhz_fz,sum(tsjkjywhz_fm) as tsjkjywhz_fm,
                        sum(lcyxhpg_fz) as lcyxhpg_fz,sum(lcyxhpg_fm) as lcyxhpg_fm,
                        sum(sjssbfz_fm) as sjssbfz_fm,sum(sjssbfz_fz) as sjssbfz_fz,
                        sum(sijssbfz_fm) as sijssbfz_fm,sum(sijssbfz_fz) as sijssbfz_fz,
                        sum(sjsssw_fm) as sjsssw_fm,sum(sjsssw_fz) as sjsssw_fz,
                        sum(sijsssw_fm) as sijsssw_fm,sum(sijsssw_fz) as sijsssw_fz,
                        sum(zyhzfjhssl_fm) as zyhzfjhssl_fm,sum(zyhzfjhssl_fz) as zyhzfjhssl_fz,
                        sum(jhzyxl_fm) as jhzyxl_fm,sum(jhzyxl_fz) as jhzyxl_fz,
                        sum(pthzyxl_fm) as pthzyxl_fm,sum(pthzyxl_fz) as pthzyxl_fz,
                        sum(sshztjhlyjhlcyl_fm) as sshztjhlyjhlcyl_fm,sum(sshztjhlyjhlcyl_fz) as sshztjhlyjhlcyl_fz,
                        sum(sjsshzssdrbcjjbzb_fm) as sjsshzssdrbcjjbzb_fm,sum(sjsshzssdrbcjjbzb_fz) as sjsshzssdrbcjjbzb_fz,
                        sum(geycfyhzjxynbltldzb_fm) as geycfyhzjxynbltldzb_fm,sum(geycfyhzjxynbltldzb_fz) as geycfyhzjxynbltldzb_fz,
                        sum(sqtljhssyzl_fm) as sqtljhssyzl_fm,sum(sqtljhssyzl_fz) as sqtljhssyzl_fz,
                        sum(swblyfsjfdswblbz_fm) as swblyfsjfdswblbz_fm,sum(swblyfsjfdswblbz_fz) as swblyfsjfdswblbz_fz,
                        sum(szztxhsl_fm) as szztxhsl_fm,sum(szztxhsl_fz) as szztxhsl_fz")
            ->where('AAC01_YEAR', '=', $year)
            ->where('AAC01_MONTH', '=', $month)
            ->groupBy("AAC01_MONTH")
            ->first();
        
        // 如果查询结果为空，返回默认值
        if (!$indicatorData) {
            $indicatorData = [];
        } else {
            $indicatorData = $indicatorData->toArray();
        }

        // 分母：查询 surgical_catalog 的 CYSJ 包含该月份
        $monthStr = sprintf('%04d-%02d', $year, $month); // 格式：2025-01
        $fenmu = DB::table('surgical_catalog')
            ->where('CYSJ', 'like', '%' . $monthStr . '%')
            ->count();

        // 分子：查询主手术和其他手术中关联 info 表的 AAC01 范围是当月的
        // 首先获取当月的开始和结束时间
        $monthStart = date('Y-m-01 00:00:00', strtotime("$year-$month-01"));
        $monthEnd = date('Y-m-t 23:59:59', strtotime("$year-$month-01"));

        // 构建主手术查询
        $mainOperations = DB::table('main_operation as mo')
            ->join('patient_info as pi', 'mo.AAA28', '=', 'pi.MED_REC_ID')
            ->whereBetween('pi.AAC01', [$monthStart, $monthEnd])
            ->whereIn('mo.OPE_LEVEL', [3, 4]) // 只查询3、4级手术
            ->select('mo.ICD9_NAME')
            ->get();

        // 构建其他手术查询
        $secondaryOperations = DB::table('secondary_operation as so')
            ->join('patient_info as pi', 'so.AAA28', '=', 'pi.MED_REC_ID')
            ->whereBetween('pi.AAC01', [$monthStart, $monthEnd])
            ->whereIn('so.OPE_LEVEL', [3, 4]) // 只查询3、4级手术
            ->select('so.ICD9_NAME')
            ->get();

        // 合并并按照 ICD9_NAME 分组
        $allOperations = $mainOperations->merge($secondaryOperations);
        $groupedOperations = $allOperations->groupBy('ICD9_NAME');

        // 获取 surgical_catalog 中存在的 ICD9_NAME 列表
        $surgicalCatalogNames = DB::table('surgical_catalog')
            ->where('CYSJ', 'like', '%' . $monthStr . '%')
            ->pluck('ICD9_NAME')
            ->toArray();

        // 计算分子：在 surgical_catalog 的 ICD9_NAME 存在的算作分子
        $fenzi = 0;
        foreach ($groupedOperations as $icd9Name => $operations) {
            if (in_array($icd9Name, $surgicalCatalogNames)) {
                $fenzi += $operations->count();
            }
        }

        $ssjsssjkz_fz = round(floatval($fenzi), 4);
        $ssjsssjkz_fm = round(floatval($fenmu), 4);

        $indicatorData['ssjsssjkz_fz'] = $ssjsssjkz_fz;
        $indicatorData['ssjsssjkz_fm'] = $ssjsssjkz_fm;

        // 特殊处理 sjssysjssbfzfs 指标 - 计算三级手术和四级手术的并发症发生率比
        // 分母：三级手术并发症发生率 = sjssbfz_fz / sjssbfz_fm
        // 分子：四级手术并发症发生率 = sijssbfz_fz / sijssbfz_fm
        $sjssbfz_fm = floatval($indicatorData['sjssbfz_fm'] ?? 0);
        $sjssbfz_fz = floatval($indicatorData['sjssbfz_fz'] ?? 0);
        $sijssbfz_fm = floatval($indicatorData['sijssbfz_fm'] ?? 0);
        $sijssbfz_fz = floatval($indicatorData['sijssbfz_fz'] ?? 0);
        
        $indicatorData['ssjssbfz_fm'] = $sjssbfz_fm > 0 ? round($sjssbfz_fz / $sjssbfz_fm, 4) : 0;
        $indicatorData['ssjssbfz_fz'] = $sijssbfz_fm > 0 ? round($sijssbfz_fz / $sijssbfz_fm, 4) : 0;

        // 特殊处理 sjssysjssswlb 指标 - 计算三级手术和四级手术患者死亡率比
        // 分母：三级手术死亡率 = sjsssw_fz / sjsssw_fm
        // 分子：四级手术死亡率 = sijsssw_fz / sijsssw_fm
        $sjsssw_fm = floatval($indicatorData['sjsssw_fm'] ?? 0);
        $sjsssw_fz = floatval($indicatorData['sjsssw_fz'] ?? 0);
        $sijsssw_fm = floatval($indicatorData['sijsssw_fm'] ?? 0);
        $sijsssw_fz = floatval($indicatorData['sijsssw_fz'] ?? 0);
        
        $indicatorData['ssjsshzsw_fm'] = $sjsssw_fm > 0 ? round($sjsssw_fz / $sjsssw_fm, 4) : 0;
        $indicatorData['ssjsshzsw_fz'] = $sijsssw_fm > 0 ? round($sijsssw_fz / $sijsssw_fm, 4) : 0;

        $indicatorData = $this->fillManualIndicatorData($indicatorData, (int)$year, (int)$month);

        return $indicatorData;
        /* return [
                'sjyscf_fz' => 7921,
                'sjyscf_fm' => 11316,
                'ejyscf_fz' => 11712,
                'ejyscf_fm' => 16974,
                'szsqcf_fz' => 902,
                'szsqcf_fm' => 1289,
                'szshcf24_fz' => 928,
                'szshcf24_fm' => 1289,
                'sstysqs_fz' => 954,
                'sstysqs_fm' => 1289,
                'sstysqssj_fz' => 941,
                'sstysqssj_fm' => 1289,
                'sstysqssx_fz' => 928,
                'sstysqssx_fm' => 1289,
                'zlfajcqyssh_fz' => 3904,
                'zlfajcqyssh_fm' => 5658,
                'jhzjsdwl_fz' => 111,
                'jhzjsdwl_fm' => 116,
                'pthzjswcl_fz' => 5667,
                'pthzjswcl_fm' => 5682,
                'zbqjzlcz_fz' => 40738,
                'zbqjzlcz_fm' => 56580,
                'sqfjhzssbltl_fz' => 3,
                'sqfjhzssbltl_fm' => 3,
                'shfjhzssbltl_fz' => 3,
                'shfjhzssbltl_fm' => 3,
                'shshbfzbltl_fz' => 0,
                'shshbfzbltl_fm' => 0,
                'ynbltlgf_fz' => 65,
                'ynbltlgf_fm' => 110,
                'qjjljsjl_fz' => 14,
                'qjjljsjl_fm' => 22,
                'qjjlsh_fz' => 22,
                'qjjlsh_fm' => 22,
                'sqtlwcl_fz' => 954,
                'sqtlwcl_fm' => 1289,
                'sqtlrygfcyl_fz' => 993,
                'sqtlrygfcyl_fm' => 1289,
                'sjsssqdxktl_fz' => 239,
                'sjsssqdxktl_fm' => 299,
                'ssyzgfkjl_fz' => 1005,
                'ssyzgfkjl_fm' => 1289,
                'swtljswcl_fz' => 16,
                'swtljswcl_fm' => 16,
                'kzrzcswtll_fz' => 16,
                'kzrzcswtll_fm' => 16,
                'szfhsqmlyzl_fz' => 1289,
                'szfhsqmlyzl_fm' => 1289,
                'xjshxxmssr_fz' => 65,
                'xjshxxmssr_fm' => 88,
                'wjzjlfhl_fz' => 230,
                'wjzjlfhl_fm' => 235,
                'ryjlxswcl24_fz' => 5640,
                'ryjlxswcl24_fm' => 5658,
                'scbcjlwc8_fz' => 5647,
                'scbcjlwc8_fm' => 5658,
                'ssjlwc24_fz' => 1014,
                'ssjlwc24_fm' => 1289,
                'shscbcjkwc_fz' => 1026,
                'shscbcjkwc_fm' => 1289,
                'rjssblsqtl_fz' => 37,
                'rjssblsqtl_fm' => 39,
                'rjssblsqpg_fz' => 38,
                'rjssblsqpg_fm' => 39,
                'rjssblrcyjl24_fz' => 39,
                'rjssblrcyjl24_fm' => 39,
                'kjywcfqlshg_fz' => 2979,
                'kjywcfqlshg_fm' => 3547,
                'tsjkjywhz_fz' => 47,
                'tsjkjywhz_fm' => 57,
                'cyqxdkjywy_fz' => 0,
                'cyqxdkjywy_fm' => 0,
                'kjywsyjl_fz' => 2653,
                'kjywsyjl_fm' => 3401,
                'lcyxqpgjl_fz' => 349,
                'lcyxqpgjl_fm' => 349,
                'lcyxhpg_fz' => 349,
                'lcyxhpg_fm' => 349
            ]; */
    }

    /**
     * 填充人工指标数据
     * @param array $indicatorData 指标数据
     * @param int $year 年份
     * @param int $month 月份
     * @return array
     */
    private function fillManualIndicatorData(array $indicatorData, int $year, int $month)
    {
        $manualIndicators = ['swhzbascl', 'xjsxxmlc'];
        $catalogs = IndexCatalogRg::query()
            ->whereIn('index_name', $manualIndicators)
            ->get(['index_name', 'custom_data'])
            ->keyBy('index_name');

        foreach ($manualIndicators as $indexName) {
            $monthData = ['fenzi' => 0, 'fenmu' => 0];
            if ($catalogs->has($indexName)) {
                $monthData = $this->getManualIndicatorMonthData($catalogs->get($indexName)->custom_data, $year, $month);
            }

            $indicatorData[$indexName . '_fz'] = $monthData['fenzi'];
            $indicatorData[$indexName . '_fm'] = $monthData['fenmu'];
        }

        return $indicatorData;
    }

    /**
     * 获取人工指标指定年月的分子分母
     * @param mixed $customData 自定义数据
     * @param int $year 年份
     * @param int $month 月份
     * @return array
     */
    private function getManualIndicatorMonthData($customData, int $year, int $month)
    {
        $items = $this->normalizeManualIndicatorCustomData($customData);
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemYear = (int)($item['year'] ?? 0);
            $itemMonth = (int)($item['month'] ?? 0);
            if ($itemYear === $year && $itemMonth === $month) {
                return [
                    'fenzi' => $this->normalizeManualIndicatorNumber($item['fenzi'] ?? 0),
                    'fenmu' => $this->normalizeManualIndicatorNumber($item['fenmu'] ?? 0),
                ];
            }
        }

        return ['fenzi' => 0, 'fenmu' => 0];
    }

    /**
     * 解析人工指标自定义数据
     * @param mixed $customData 自定义数据
     * @return array
     */
    private function normalizeManualIndicatorCustomData($customData)
    {
        if (empty($customData)) {
            return [];
        }

        if (is_string($customData)) {
            $customData = json_decode($customData, true);
        }

        if (!is_array($customData)) {
            return [];
        }

        if (isset($customData['year']) && isset($customData['month'])) {
            return [$customData];
        }

        return $customData;
    }

    /**
     * 规范化人工指标数值
     * @param mixed $value 指标数值
     * @return int|float
     */
    private function normalizeManualIndicatorNumber($value)
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return 0;
        }

        return $value + 0;
    }

    /**
     * 获取自定义指标数据
     */
    public function getCustomIndicatorData($period)
    {
        // 返回写死的数据 直接返回数组
        return [
            'sjyscf_fz' => 8324,
            'sjyscf_fm' => 11892,
            'ejyscf_fz' => 12665,
            'ejyscf_fm' => 17838,
            'szshcf24_fz' => 1462,
            'szshcf24_fm' => 1976,
            'sstysqs_fz' => 1442,
            'sstysqs_fm' => 1976,
            'sstysqssj_fz' => 1482,
            'sstysqssj_fm' => 1976,
            'sstysqssx_fz' => 1462,
            'sstysqssx_fm' => 1976,
            'zlfajcqyssh_fz' => 4162,
            'zlfajcqyssh_fm' => 5946,
            'jhzjsdwl_fz' => 1328,
            'jhzjsdwl_fm' => 1328,
            'pthzjswcl_fz' => 5314,
            'pthzjswcl_fm' => 5315,
            'sqfjhzssbltl_fz' => 1,
            'sqfjhzssbltl_fm' => 1,
            'shshbfzbltl_fz' => 3,
            'shshbfzbltl_fm' => 3,
            'ynbltlgf_fz' => 72,
            'ynbltlgf_m' => 110,
            'qjjljsjl_fz' => 20,
            'qjjljsjl_fm' => 20,
            'qjjlsh_fz' => 19,
            'qjjlsh_fm' => 20,
            'sqtlwcl_fz' => 1501,
            'sqtlwcl_fm' => 1976,
            'sqtlrygfcyl_fz' => 1541,
            'sqtlrygfcyl_fm' => 1976,
            'sjsssqdxktl_fz' => 200,
            'sjsssqdxktl_fm' => 244,
            'ssyzgfkjl_fz' => 1541,
            'ssyzgfkjl_fm' => 1976,
            'swtljswcl_fz' => 26,
            'swtljswcl_fm' => 29,
            'kzrzcswtll_fz' => 26,
            'kzrzcswtll_fm' => 29,
            'szfhsqmlyzl_fz' => 1976,
            'szfhsqmlyzl_fm' => 1976,
            'wjzjlfhl_fz' => 517,
            'wjzjlfhl_fm' => 525,
            'ryjlxswcl24_fz' => 5173,
            'ryjlxswcl24_fm' => 5946,
            'scbcjlwc8_fz' => 5411,
            'scbcjlwc8_fm' => 5946,
            'ssjlwc24_fz' => 1699,
            'ssjlwc24_fm' => 1976,
            'rjssblsqtl_fz' => 34,
            'rjssblsqtl_fm' => 37,
            'rjssblsqpg_fz' => 34,
            'rjssblsqpg_fm' => 37,
            'lcyxhpg_fz' => 349,
            'lcyxhpg_fm' => 413
        ];
   
    }

    /**
     * 指标作废接口
     */
    public function invalidateIndicator($period)
    {
        try {
            // 这是外网测试地址
            /* $url = "http://11.1.3.75:8085/medicalManagement";
            $passCode = "bzyxyytfsyy";
            $signKey = "Urd133%%"; */

            // 这是正式地址 -- 卫生专网地址
            $url = "http://11.1.3.75:8085/medicalManagement";
            $passCode = "jnsdsrmyy";
            $signKey = "Hus765$$";
            $requestId = Str::uuid()->toString();

            // 构建请求参数
            $params = [
                'request_id' => $requestId,
                'pass_code' => $passCode,
                'timestamp' => date('YmdHis'),
                'period' => $period,
            ];
            ksort($params);
            // 生成签名
            $jsonStr = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $params['sign'] = $this->sign($jsonStr, $signKey);
            // 发送请求
            $url .= "/hxzdJkdy/hxzdCancel";
            $client = new Client(['verify' => false]);
            $response = $client->request('POST', $url, [
                'headers' => ['Content-Type' => 'application/json',],
                'json' => $params
            ]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            return [
                'code' => '500',
                'msg' => $e->getMessage(),
                'request_id' => $requestId ?? '',
                'timestamp' => date('YmdHis')
            ];
        }
    }
}
