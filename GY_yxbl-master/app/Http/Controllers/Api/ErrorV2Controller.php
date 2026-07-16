<?php

namespace App\Http\Controllers\Api;

use DateTime;
use App\Model\Yzb;
use App\Model\Staff;
use App\Model\ErrorV2;
use App\Model\GY_SJQX;
use App\Model\ZY_BRRY;
use App\Model\ErrorRule;
use App\Model\QueueList;
use App\Model\TableDict;
use App\Model\Department;
use App\Model\CaseQuality;
use App\Model\DataSyncKey;
use App\Model\FeeDetailed;
use App\Model\HomeQuality;
use App\Model\RuleSetting;
use App\Model\RuleWordMap;
use App\Model\PatientInfoV2;
use Illuminate\Http\Request;
use App\Services\ToolsService;
use App\Services\HomeSzService;
use App\Model\PrimaryKeyControl;
use App\Model\RuleSettingDetail;
use App\Model\HomeRequestContent;
use Illuminate\Support\Facades\DB;
use App\Jobs\BmyHomeRequestContent;
use Illuminate\Support\Facades\Log;
use App\Console\Commands\BmyQuality;
use App\Http\Controllers\Controller;
use App\Model\HomeBmyRequestContent;
use App\Services\BasyQualityService;
use App\Services\HomeQualityService;
use App\Services\ElasticsearchService;
use App\Console\Commands\Format\BasySz;
use App\Services\HomeBmyQualityService;
use App\Services\DataxSync\DataSyncService;

use function Symfony\Component\String\s;

class ErrorV2Controller extends Controller
{
    protected $ageRegexp;
    protected $ageYcRegexp;
    protected $addressRegexp;
    public function __construct()
    {
        $this->addressRegexp = RuleWordMap::query()->whereIn('id', ['4041', '4042', '4011', '4012', '4021', '4022', '4023', '4031', '4032', '4033'])->pluck('keyword', 'id')->toArray();
        $this->ageRegexp = [1 => '/(\d+)岁/', 2 => '/(\d+)月/'];
        $this->ageYcRegexp = [1 => '/(.*?(岁))/u', 2 => '/(.*?(月))/u'];
    }
    /**
     * 病案首页质控（事中 对外）
     * @param Request $request
     * @return array
     */
    public function homeQuality(Request $request)
    {
        $json = file_get_contents('php://input');

        Log::info('病案首页事中质控请求参数：' . $json);
        $sftc = 0;


        $data = json_decode(str_replace("\\", '', $json), true);
        $zyh = $request->get('zyh', "");
        if ($zyh) {
            $data['ZYH'] = $zyh;
        }
        if (empty($data)) {
            // 检测并移除UTF-8 BOM标记 (EF BB BF)
            if (strpos($json, "\xEF\xBB\xBF") === 0) {
                $json = substr($json, 3);
                Log::info('检测到并移除了UTF-8 BOM标记');
            }

            // 尝试解析JSON
            $data = json_decode(str_replace("\\", '', $json), true);

            // 检查解析是否成功
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                // 解析失败，尝试直接解析（不移除反斜杠）
                $data = json_decode($json, true);

                // 如果仍然失败，记录错误并返回错误响应
                if ($data === null) {
                    Log::error('JSON解析失败', ['json' => $json, 'error' => json_last_error_msg()]);
                    return ToolsService::returnData(4001, [], '请求的参数格式有误: ' . json_last_error_msg());
                } else {
                    Log::info('直接解析JSON成功（未移除反斜杠）');
                }
            }
        }

        // 住院号
        $ZYH = (string) ($data['ZYH'] ?? '');
        $data = [];
        $data['ZYH'] = (string) $ZYH;
        Log::info('ZYH', ['ZYH' => $ZYH]);
        if (!$ZYH) {
            return ToolsService::returnData(4001, [], '请求的参数有误，住院号不能为空');
        }

        $mapValues = (string)$ZYH;
        // 郸城需要特殊处理
        if (env('APP_NAME') == 'dancheng') {
            $mapValues = explode('-', $ZYH);
        }

        // 定义字段映射关系 (根据用户要求调整，除 secondary_operation 和 other_diagnosis 外均为单记录)
        // [ request_key => [table_name, db_field, is_plural (0=list, 1=single), data_key_for_list] ]
        //临时新增
        $data['BMY'] = '';
        $data['BMY_BH'] = '';

        //获取主信息
        $pkControl = PrimaryKeyControl::query()->where('tablename', '=', 'main_info')->first();
        $pkField = $pkControl->toArray()['field'];
        $result = DataSyncService::getInstance(1)->setByNameSql('main_info', 0)
            ->setWhere($pkField, $mapValues)
            ->getResult();

        if (empty($result)) {
            //删除patient_info_v2和error_v2的数据
            PatientInfoV2::query()->where('ZYH', '=', $ZYH)->delete();
            ErrorV2::query()->where('ZYH', '=', $ZYH)->delete();
            //return ToolsService::returnData(4001, [], '请求住院号' . $ZYH . '的数据为空');
            return ToolsService::returnData(4001, [
                'ZYH' => $ZYH,
                'req' => 0,
                'home_req' => 0,
                'case_req' => 0,
                'home_req_count' => 0,
                'case_req_count' => 0,
                'sftc' => 0
            ], '请求住院号' . $ZYH . '的数据为空');
        }
        $main_info = $result[0] ?? [];
        if (env('APP_NAME') == 'dancheng') {
            $ZYH = $main_info['ZYH'] ?? '';
        }
        //插入队列
        $currentTime = date('Y-m-d H:i:s');
        $insert = ['data' => $ZYH, 'type' => 'analysis', 'created_at' => $currentTime, 'updated_at' => $currentTime];
        QueueList::query()->insert($insert);

        //先获取field_v2 = patient_info_v2的id
        $id = TableDict::query()->where('field_v2', '=', 'patient_info_v2')->orWhere('field_v2', '=', 'patient_info_cost_v2')->get()->toArray();
        $ids = [];
        foreach ($id as $item) {
            $ids[] = $item['id'];
        }
        //Log::info('ids', ['ids' => $ids]);
        //循环查看里面的key，取表table_dict_sy表查询，如果存在就设置data['feild_zk']=这个key的值
        $tableDict = TableDict::query()->whereIn('parent_field', $ids)->get()->toArray();
        //Log::info('tableDict', ['tableDict' => $tableDict]);
        $tableDict = array_column($tableDict, null, 'field_v2');
        foreach ($main_info as $key => $value) {

            // 使用 isset 和 trim 来检查，避免 empty() 的问题
            if (isset($tableDict[$key]['field_zk']) && trim($tableDict[$key]['field_zk']) !== '') {
                $main_info[$tableDict[$key]['field_zk']] = $value;
            }
        }



        //合并数据
        $data = array_merge($data, $main_info);
        $bazl = $data['BAZL'] ?? '';
        $lyfs = $data['LYFS'] ?? '';
        $sfzzyjh = $data['SFZZYJH'] ?? '';
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
        if (env('APP_NAME') != 'sanyuan') {

            if ($bazl == '甲') {
                $data['BAZL'] = 1;
            } elseif ($bazl == '乙') {
                $data['BAZL'] = 2;
            } elseif ($bazl == '丙') {
                $data['BAZL'] = 3;
            }

            //LYFS离院方式代码1.医嘱离院2.医嘱转院3.医嘱转社区卫生服务机构4.非医嘱离院 5.死亡9.其他3.医嘱转乡镇卫生院

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
            //SFZZYJH 1.否2.是

            if ($sfzzyjh == '无') {
                $data['SFZZYJH'] = 1;
            } elseif ($sfzzyjh == '有') {
                $data['SFZZYJH'] = 2;
            }
        }

        //获取诊断信息
        $pkControl = PrimaryKeyControl::query()->where('tablename', '=', 'diagnosis')->first();
        $pkField = $pkControl->toArray()['field'];
        $result = DataSyncService::getInstance(1)->setByNameSql('diagnosis', 0)
            ->setWhere($pkField, $mapValues)
            ->getResult();

        $diagnosis = $result;
        $diagnosisnum = 1;
        $zzpb1 = false;


        if (!empty($diagnosis)) {
            //先按照DIA_OEDER正序排序
            usort($diagnosis, function ($a, $b) {
                if (is_numeric($a['DIA_ORDER']) && is_numeric($b['DIA_ORDER'])) {
                    return $a['DIA_ORDER'] - $b['DIA_ORDER'];
                }
                return 0;
            });
            //再判断里面是否有ZZPB=1的
            $zzpb1 = false;
            foreach ($diagnosis as $v) {
                if ($v['ZZPB'] == 1 || $v['ZZPB'] == '1') {
                    $zzpb1 = true;
                    break;
                }
            }
            foreach ($diagnosis as $k => $item) {
                $ryqk = $item['RYQK'];
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
        } else {
            $data['ZYZD'] = '';
            $data['JBDM'] = '';
            $data['RYBQ'] = '';
            $data['CYQK'] = '';
            $data['QTZD1'] = '';
            $data['JBDM1'] = '';
            $data['RYBQ1'] = '';
            $data['CYQK1'] = '';
        }

        //获取手术信息
        $pkControl = PrimaryKeyControl::query()->where('tablename', '=', 'operation')->first();
        $pkField = $pkControl->toArray()['field'];
        $result = DataSyncService::getInstance(1)->setByNameSql('operation', 0)
            ->setWhere($pkField, $mapValues)
            ->getResult();
        $operation = $result;
        $operationnum = 2;

        if (!empty($operation)) {
            //对operation按照OPE_ORDER正序排序，第一个设置为0
            usort($operation, function ($a, $b) {
                return $a['OPE_ORDER'] - $b['OPE_ORDER'];
            });
            $operation[0]['OPE_ORDER'] = 0;
            foreach ($operation as $item) {

                $opedate = $item['OPE_DATE'];
                if (!empty($opedate)) {
                    //只保留日期
                    $opedate = date('Y-m-d', strtotime($opedate));
                    $item['OPE_DATE'] = $opedate;
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
                    $data['SFFJHZRY1'] = $item['SFFJHZRY'] ?? '';
                    $data['SFWRJBF1'] = $item['SFWRJBF'] ?? '';
                    $data['SFFJHZSS1'] = $item['SFFJHZSS'] ?? '';
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
                    $data['SFFJHZRY' . $operationnum] = $item['SFFJHZRY'] ?? '';
                    $data['SFWRJBF' . $operationnum] = $item['SFWRJBF'] ?? '';
                    $data['SFFJHZSS' . $operationnum] = $item['SFFJHZSS'] ?? '';
                    $operationnum++;
                }
            }
        } else {
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
            $data['SFFJHZRY1'] = '';
            $data['SFWRJBF1'] = '';
            $data['SFFJHZSS1'] = '';
        }

        /**
         * CSD =》 CSD 出生地  需要清洗 CSD_SHENG(AAA09)、CSD_SHI(AAA10)、CSD_XIAN(AAA11)
         * GG =》GG   籍贯  需要清洗 GG_SHENG(AAA43)、GG_SHI(AAA44)
         * AAA12 =》HKDZ  户籍 需要清洗 HKDZ_SHENG(AAA45)、HKDZ_SHI(AAA46)、HKDZ_XIAN(AAA47)
         * AAA15 =》XZZ 现住址 需要清洗 XZZ_SHENG(AAA48)、XZZ_SHI(AAA49)、XZZ_XIAN(AAA50)
         * //4011	籍贯拆分规则（省）	/(.*?(省|自治区|北京|天津|上海|重庆))/u	2025-01-25 08:30:21
         * 4012	籍贯拆分规则（市）	/(.*?(市|自治州|地区|区划|盟))/u	2025-01-25 08:30:27
         * 4021	户籍拆分规则(省)	/(.*?(省|自治区|北京|天津|上海|重庆))/u	2025-01-25 08:10:05
         * 4022	户籍拆分规则(市)	/(.*?(市|自治州|地区|区划|盟))/u	2025-01-25 08:11:27
         * 4023	户籍拆分规则(区)	/(.*?(区|县|旗))/u	2025-01-25 08:13:50
         * 4031	现住址拆分规则(省)	/(.*?(省|自治区|北京|天津|上海|重庆))/u	2025-01-25 08:26:00
         * 4032	现住址拆分规则(市)	/(.*?(市|自治州|地区|区划|盟))/u	2025-01-25 08:26:40
         * 4033	现住址拆分规则(区)	/(.*?(区|县|旗))/u	2025-01-25 08:27:15
         * 4038	MBLB&&BLLB清洗规则	{"\u8bca\u65ad\u7eed\u6253":{"key":"MBLB","value":"00001"},"\u5256\u5bab\u4ea7\u624b\u672f\u8bb0\u5f55":{"key":"BLLB","value":"01"},"\u5256\u5bab\u4ea7\u5206\u5a29\u8bb0\u5f55":{"key":"MBLB","value":"9999"},"\u4fee\u6b63\u7eed\u9875":{"key":"MBLB","value":"00001"},"\u8bca\u65ad\u7eed\u9875":{"key":"MBLB","value":"00001"},"\u51fa\u9662\u7eed\u9875":{"key":"MBLB","value":"00001"},"\u8865\u5145\u7eed\u9875":{"key":"MBLB","value":"00001"}}	2025-02-22 15:10:16
         * 4041	出生地拆分规则（省）	/(.*?(省|自治区|北京|天津|上海|重庆))/u	2025-02-08 03:28:03
         * 4042	出生地拆分规则（市）	/(.*?(市|自治州|地区|区划|盟))/u	2025-02-08 03:28:10
         */

        if (env('APP_NAME') == 'HLW' || env('APP_NAME') == 'hlw') {
        } elseif (env('APP_NAME') == 'dancheng') {

            $danchengcity = config('danchengcity');
            $csd = $data['CSD_SHENG'] . $data['CSD_SHI'] . $data['CSD_XIAN'];
            if ($csd && !empty($danchengcity[$csd])) {
                $data['CSD_SHENG'] = $danchengcity[$csd]['province'];
                $data['CSD_SHI'] = $danchengcity[$csd]['city'];
                $data['CSD_XIAN'] = $danchengcity[$csd]['county'];
            }
            $HKDZ = $data['HKDZ_SHENG'] . $data['HKDZ_SHI'] . $data['HKDZ_XIAN'];
            if ($HKDZ && !empty($danchengcity[$HKDZ])) {
                $data['GG_SHENG'] = $danchengcity[$HKDZ]['province'];
                $data['HKDZ_SHENG'] = $danchengcity[$HKDZ]['province'];
                $data['GG_SHI'] = $danchengcity[$HKDZ]['city'];
                $data['HKDZ_SHI'] = $danchengcity[$HKDZ]['city'];
                $data['HKDZ_XIAN'] = $danchengcity[$HKDZ]['county'];
            }
            $XZZ = $data['XZZ_SHENG'] . $data['XZZ_SHI'] . $data['XZZ_XIAN'];
            if ($XZZ && !empty($danchengcity[$XZZ])) {
                $data['XZZ_SHENG'] = $danchengcity[$XZZ]['province'];
                $data['XZZ_SHI'] = $danchengcity[$XZZ]['city'];
                $data['XZZ_XIAN'] = $danchengcity[$XZZ]['county'];
            }
        } elseif (env('APP_NAME') != 'sanyuan') {
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
            // 使用最终剩余的地址作为详细地址，避免 str_replace 替换所有匹配项
            $data['CSD'] = $csdCityResult['address'];

            //清洗籍贯
            $ggAddress = $data['GG'];
            //去除所有空格和换行符
            $ggAddress = preg_replace('/\s+/', '', $ggAddress);
            $ggProvinceResult = $this->parseAndTrimAddress($ggAddress, $this->addressRegexp['4011']);
            $data['GG_SHENG'] = $ggProvinceResult['result'];
            $ggCityResult = $this->parseAndTrimAddress($ggProvinceResult['address'], $this->addressRegexp['4012'], true);
            $data['GG_SHI'] = $ggCityResult['result'];
            // 使用最终剩余的地址作为详细地址，避免 str_replace 替换所有匹配项
            $data['GG'] = $ggCityResult['address'];

            //清洗户籍
            $hjAddress = $data['HKDZ'] ?? '';
            //Log::info('清洗户籍 - 原始地址', ['HKDZ' => $hjAddress]);
            //去除所有空格和换行符
            $hjAddress = preg_replace('/\s+/', '', $hjAddress);
            //Log::info('清洗户籍 - 去除空格后', ['hjAddress' => $hjAddress]);
            $hjProvinceResult = $this->parseAndTrimAddress($hjAddress, $this->addressRegexp['4021']);
            $data['HKDZ_SHENG'] = $hjProvinceResult['result'];
            //Log::info('清洗户籍 - 省匹配结果', ['HKDZ_SHENG' => $data['HKDZ_SHENG'], 'remainingAddress' => $hjProvinceResult['address']]);
            $hjCityResult = $this->parseAndTrimAddress($hjProvinceResult['address'], $this->addressRegexp['4022'], true);
            $data['HKDZ_SHI'] = $hjCityResult['result'];
            //Log::info('清洗户籍 - 市匹配结果', ['HKDZ_SHI' => $data['HKDZ_SHI'], 'remainingAddress' => $hjCityResult['address']]);
            $hjAreaResult = $this->parseAndTrimAddress($hjCityResult['address'], $this->addressRegexp['4023'], false, true);
            $data['HKDZ_XIAN'] = $hjAreaResult['result'];
            //Log::info('清洗户籍 - 县匹配结果', ['HKDZ_XIAN' => $data['HKDZ_XIAN'], 'remainingAddress' => $hjAreaResult['address']]);
            // 使用最终剩余的地址作为详细地址，避免 str_replace 替换所有匹配项
            $data['HKDZ'] = $hjAreaResult['address'];
            Log::info('清洗户籍 - 最终结果', [
                'HKDZ_SHENG' => $data['HKDZ_SHENG'],
                'HKDZ_SHI' => $data['HKDZ_SHI'],
                'HKDZ_XIAN' => $data['HKDZ_XIAN'],
                'HKDZ' => $data['HKDZ']
            ]);

            //清洗现住址
            $xzzAddress = $data['XZZ'] ?? '';
            //Log::info('清洗现住址 - 原始地址', ['XZZ' => $xzzAddress]);
            //去除所有空格和换行符
            $xzzAddress = preg_replace('/\s+/', '', $xzzAddress);
            //Log::info('清洗现住址 - 去除空格后', ['xzzAddress' => $xzzAddress]);
            $xzzProvinceResult = $this->parseAndTrimAddress($xzzAddress, $this->addressRegexp['4031']);
            $data['XZZ_SHENG'] = $xzzProvinceResult['result'];
            //Log::info('清洗现住址 - 省匹配结果', ['XZZ_SHENG' => $data['XZZ_SHENG'], 'remainingAddress' => $xzzProvinceResult['address']]);
            $xzzCityResult = $this->parseAndTrimAddress($xzzProvinceResult['address'], $this->addressRegexp['4032'], true);
            $data['XZZ_SHI'] = $xzzCityResult['result'];
            //Log::info('清洗现住址 - 市匹配结果', ['XZZ_SHI' => $data['XZZ_SHI'], 'remainingAddress' => $xzzCityResult['address']]);
            $xzzAreaResult = $this->parseAndTrimAddress($xzzCityResult['address'], $this->addressRegexp['4033'], false, true);
            $data['XZZ_XIAN'] = $xzzAreaResult['result'];
            //Log::info('清洗现住址 - 县匹配结果', ['XZZ_XIAN' => $data['XZZ_XIAN'], 'remainingAddress' => $xzzAreaResult['address']]);
            // 使用最终剩余的地址作为详细地址，避免 str_replace 替换所有匹配项
            $data['XZZ'] = $xzzAreaResult['address'];
            Log::info('清洗现住址 - 最终结果', [
                'XZZ_SHENG' => $data['XZZ_SHENG'],
                'XZZ_SHI' => $data['XZZ_SHI'],
                'XZZ_XIAN' => $data['XZZ_XIAN'],
                'XZZ' => $data['XZZ']
            ]);

            //格式化AAB01和AAC01,2025-05-29 14:36:4900:00,这种取2025-05-29 14:36:49
            if (!empty($data['RYSJ'])) {
                //先格式化
                $data['RYSJ'] = date('Y-m-d H:i:s', strtotime($data['RYSJ']));
                $data['RYSJ'] = date('Y-m-d H:i:s', strtotime(substr($data['RYSJ'], 0, 19)));
            }
            if (!empty($data['CYSJ'])) {
                $data['CYSJ'] = date('Y-m-d H:i:s', strtotime($data['CYSJ']));
                $data['CYSJ'] = date('Y-m-d H:i:s', strtotime(substr($data['CYSJ'], 0, 19)));
            }

            $NL = $data['NL'];
            if (!empty($NL)) {
                if (strpos($NL, '岁') === false && strpos($NL, '月') === false) {
                    $data['BZYZSNL'] = $NL;
                    $data['NL'] = '';
                }
            }

            $NLM = $data['BZYZSNL'];
            if (!empty($NLM)) {
                $plusPos = strpos($NLM, '+');

                if ($plusPos === 0) {
                    // 如果加号在第一位，取+到/之间的字符
                    $slashPos = strpos($NLM, '/');
                    if ($slashPos !== false && $slashPos > $plusPos) {
                        $extractedText = substr($NLM, $plusPos + 1, $slashPos - $plusPos - 1);
                        $data['BZYZSNL'] = $extractedText;
                    }
                } else {
                    // 如果加号不在第一位，把BZYZSNL设置成空，NL设置成NLM
                    $data['BZYZSNL'] = '';
                    $data['NL'] = $NLM;
                }
            }
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
            // 使用最终剩余的地址作为详细地址，避免 str_replace 替换所有匹配项
            $data['CSD'] = $csdCityResult['address'];
            //清洗籍贯
            $ggAddress = $data['GG'];
            //去除所有空格和换行符
            $ggAddress = preg_replace('/\s+/', '', $ggAddress);
            $ggProvinceResult = $this->parseAndTrimAddress($ggAddress, $this->addressRegexp['4011']);
            $data['GG_SHENG'] = $ggProvinceResult['result'];
            $ggCityResult = $this->parseAndTrimAddress($ggProvinceResult['address'], $this->addressRegexp['4012'], true);
            $data['GG_SHI'] = $ggCityResult['result'];
            // 使用最终剩余的地址作为详细地址，避免 str_replace 替换所有匹配项
            $data['GG'] = $ggCityResult['address'];

            //清洗户籍
            $hjAddress = $data['HKDZ'] ?? '';
            //Log::info('清洗户籍(sanyuan) - 原始地址', ['HKDZ' => $hjAddress]);
            //去除所有空格和换行符
            $hjAddress = preg_replace('/\s+/', '', $hjAddress);
            //Log::info('清洗户籍(sanyuan) - 去除空格后', ['hjAddress' => $hjAddress]);
            $hjProvinceResult = $this->parseAndTrimAddress($hjAddress, $this->addressRegexp['4021']);
            $data['HKDZ_SHENG'] = $hjProvinceResult['result'];
            //Log::info('清洗户籍(sanyuan) - 省匹配结果', ['HKDZ_SHENG' => $data['HKDZ_SHENG'], 'remainingAddress' => $hjProvinceResult['address']]);
            $hjCityResult = $this->parseAndTrimAddress($hjProvinceResult['address'], $this->addressRegexp['4022'], true);
            $data['HKDZ_SHI'] = $hjCityResult['result'];
            //Log::info('清洗户籍(sanyuan) - 市匹配结果', ['HKDZ_SHI' => $data['HKDZ_SHI'], 'remainingAddress' => $hjCityResult['address']]);
            $hjAreaResult = $this->parseAndTrimAddress($hjCityResult['address'], $this->addressRegexp['4023'], false, true);
            $data['HKDZ_XIAN'] = $hjAreaResult['result'];
            //Log::info('清洗户籍(sanyuan) - 县匹配结果', ['HKDZ_XIAN' => $data['HKDZ_XIAN'], 'remainingAddress' => $hjAreaResult['address']]);
            // 使用最终剩余的地址作为详细地址，避免 str_replace 替换所有匹配项
            $data['HKDZ'] = $hjAreaResult['address'];
            Log::info('清洗户籍(sanyuan) - 最终结果', [
                'HKDZ_SHENG' => $data['HKDZ_SHENG'],
                'HKDZ_SHI' => $data['HKDZ_SHI'],
                'HKDZ_XIAN' => $data['HKDZ_XIAN'],
                'HKDZ' => $data['HKDZ']
            ]);

            //清洗现住址
            $xzzAddress = $data['XZZ'] ?? '';
            //Log::info('清洗现住址(sanyuan) - 原始地址', ['XZZ' => $xzzAddress]);
            //去除所有空格和换行符
            $xzzAddress = preg_replace('/\s+/', '', $xzzAddress);
            //Log::info('清洗现住址(sanyuan) - 去除空格后', ['xzzAddress' => $xzzAddress]);
            $xzzProvinceResult = $this->parseAndTrimAddress($xzzAddress, $this->addressRegexp['4031']);
            $data['XZZ_SHENG'] = $xzzProvinceResult['result'];
            //Log::info('清洗现住址(sanyuan) - 省匹配结果', ['XZZ_SHENG' => $data['XZZ_SHENG'], 'remainingAddress' => $xzzProvinceResult['address']]);
            $xzzCityResult = $this->parseAndTrimAddress($xzzProvinceResult['address'], $this->addressRegexp['4032'], true);
            $data['XZZ_SHI'] = $xzzCityResult['result'];
            //Log::info('清洗现住址(sanyuan) - 市匹配结果', ['XZZ_SHI' => $data['XZZ_SHI'], 'remainingAddress' => $xzzCityResult['address']]);
            $xzzAreaResult = $this->parseAndTrimAddress($xzzCityResult['address'], $this->addressRegexp['4033'], false, true);
            $data['XZZ_XIAN'] = $xzzAreaResult['result'];
            //Log::info('清洗现住址(sanyuan) - 县匹配结果', ['XZZ_XIAN' => $data['XZZ_XIAN'], 'remainingAddress' => $xzzAreaResult['address']]);
            // 使用最终剩余的地址作为详细地址，避免 str_replace 替换所有匹配项
            $data['XZZ'] = $xzzAreaResult['address'];
            Log::info('清洗现住址(sanyuan) - 最终结果', [
                'XZZ_SHENG' => $data['XZZ_SHENG'],
                'XZZ_SHI' => $data['XZZ_SHI'],
                'XZZ_XIAN' => $data['XZZ_XIAN'],
                'XZZ' => $data['XZZ']
            ]);
        }


        // --- 获取费用和医嘱 (如果 $data 中没有，则补充) ---
        // 费用明细 fee_detailed (假设总是多记录)
        if (!isset($data['fee_detailed'])) {
            try {
                $pkControl = PrimaryKeyControl::query()->where('tablename', '=', 'fee_detailed')->first();
                if ($pkControl) {
                    $field = $pkControl->toArray()['field'];
                    $feeDetailedData = DataSyncService::getInstance(2)->setByNameSql('fee_detailed', 0) // 显式指定为多记录
                        ->setWhere($field, $mapValues)
                        ->getResult();
                    $data['fee_detailed'] = $feeDetailedData ?? [];
                } else {
                    Log::warning("PrimaryKeyControl not found for table: fee_detailed");
                    $data['fee_detailed'] = [];
                }
            } catch (\Exception $e) {
                Log::error("Error fetching fee_detailed for ZYH: {$ZYH}", ['error' => $e->getMessage()]);
                $data['fee_detailed'] = [];
            }
        }

        // 医嘱 yzb (假设总是多记录)
        if (!isset($data['yz'])) {
            try {
                $pkControl = PrimaryKeyControl::query()->where('tablename', '=', 'yzb')->first();
                if ($pkControl) {
                    $field = $pkControl->toArray()['field'];
                    $yzData = DataSyncService::getInstance(2)->setByNameSql('yzb', 0) // 显式指定为多记录
                        ->setWhere($field, $mapValues)
                        ->getResult();
                    $data['yz'] = $yzData ?? [];
                } else {
                    Log::warning("PrimaryKeyControl not found for table: yzb");
                    $data['yz'] = [];
                }
            } catch (\Exception $e) {
                Log::error("Error fetching yzb for ZYH: {$ZYH}", ['error' => $e->getMessage()]);
                $data['yz'] = [];
            }
        }
        // --- 结束：获取费用和医嘱 ---

        //return $data;

        // 获取科室
        $depData = cache()->remember('dep_data_binyi', 7200, function () {
            $depName = config('confAdmin.hospital_name');
            // 增加对 Department 模型是否存在的检查
            if (class_exists(Department::class)) {
                return Department::query()
                    ->where('hospital_name', '=', $depName)
                    ->pluck('dep_id', 'dep_name')->toArray();
            } else {
                Log::error("Department model not found.");
                return []; // 返回空数组或根据需要处理
            }
        });

        // 记录请求数据 (使用可能已补充完整的 $data)
        $hospitalNameInData = $data['USERNAME'] ?? null;
        // 检查 patient_info 是否在缓存中且不为 null
        $hospitalNameFromCache = isset($fetchedDataCache['patient_info']) && $fetchedDataCache['patient_info'] ? ($fetchedDataCache['patient_info']['hospital_name'] ?? null) : null;


        $insertData = [
            'hospital_name' => $hospitalNameInData ?? $hospitalNameFromCache ?? '', // 优先用 $data 中的，其次用缓存的
            'ZYH' => $ZYH,
            'content' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), // 使用补充后的 $data
            //'fy_content' => !empty($data['fee_detailed']) ? json_encode($data['fee_detailed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '[]', // 默认空JSON数组
            //'yz_content' => !empty($data['yz']) ? json_encode($data['yz'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '[]', // 默认空JSON数组
        ];

        try {
            // 增加对 HomeRequestContent 模型是否存在的检查
            if (class_exists(HomeRequestContent::class)) {
                HomeRequestContent::query()->insert($insertData);
                // 执行格式化
                $basySz = new BasySz();
                $basySz->formatByZyh($ZYH);
            } else {
                Log::error("HomeRequestContent model not found. Skipping insert.");
            }
        } catch (\Exception $e) {
            Log::error('记录 HomeRequestContent 失败', ['error' => $e->getMessage(), 'data_preview' => substr(json_encode($insertData), 0, 500)]); // 记录部分数据避免日志过大
            // 根据业务决定是否继续执行或返回错误
        }


        // 质控规则获取
        $errorRuleData = null;
        // 增加对 ErrorRule 模型是否存在的检查
        if (class_exists(ErrorRule::class)) {
            $errorRuleData = ErrorRule::query()
                ->where('node', 'like', "%运行%")
                ->where('status', '=', 0)
                ->orderBy('id')
                ->get(); // 获取集合
        } else {
            Log::error("ErrorRule model not found. Cannot fetch rules.");
            // 可能需要返回错误或设置默认值
        }


        $code = 200;
        $required = ['score' => 0, 'required' => 0];
        $msg = ''; // 初始化消息变量

        // 确保 $errorRuleData 是 Eloquent 集合或 null
        if ($errorRuleData && !$errorRuleData->isEmpty()) {
            $errorRuleDataArray = $errorRuleData->keyBy('id')->toArray();

            // 增加对 HomeQualityService 是否存在的检查
            if (class_exists(HomeQualityService::class)) {
                $homeQualityService = new HomeQualityService();
                // 传递补充后的 $data
                $required = $homeQualityService->qualityContrl($ZYH, $data, $errorRuleDataArray, $depData);
            } else {
                Log::error("HomeQualityService not found. Skipping quality control.");
                $code = 500; // 或者其他错误码
                $msg = "质控服务不可用";
            }
            if (class_exists(CustomZKController::class)) {
                try {
                    $customZK = new CustomZKController();
                    $zkmsg = $customZK->customizeRule($ZYH, $data, 2);
                } catch (\Exception $e) {
                    Log::error("Error executing customizeRule for ZYH: {$ZYH}", ['error' => $e->getMessage()]);
                    // 根据业务决定是否继续
                }
            } else {
                Log::warning("CustomZKController not found. Skipping customizeRule.");
            }
        } else {
            Log::warning('病案首页质控（事中 对外）未找到有效的质控规则或 ErrorRule 模型不存在', ['ZYH' => $ZYH]);
        }

        //查询errorv2有数据就sftc=1,没有就是0
        $errorv2 = ErrorV2::query()->where('ZYH', '=', $ZYH)->where('status', '=', 0)->count();
        $sftc = $errorv2 > 0 ? 1 : 0;

        $requiredCounts = $this->getCombinedRequiredCounts($ZYH);

        return ToolsService::returnData($code, [
            'ZYH' => $ZYH,
            'req' => $requiredCounts['req'],
            'home_req' => $requiredCounts['home_req'],
            'case_req' => $requiredCounts['case_req'],
            'home_req_count' => $requiredCounts['home_req'],
            'case_req_count' => $requiredCounts['case_req'],
            'sftc' => $sftc
        ], $code == 200 ? '' : $msg);
    }

    /**
     * 编码员质控
     * @param Request $request
     * @return array
     */
    public function homeBmyQuality(Request $request)
    {
        $start = time();
        //获取所有的参数,包含get和post
        $data = $request->all();

        Log::info('编码员质控请求参数：', $data);
        $ZYH = $data['ZYH'] ?? '';

        if (empty($ZYH)) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }

        $moduleName = env("APP_NAME", "");
        // 排除宁夏中新生儿的质控数据
        if ($moduleName == 'ningxia') {
            $brry = ZY_BRRY::query()->where('ZYH', '=', $ZYH)->first();
            if (preg_match('/[a-zA-Z]/', $brry->AAA28)) {
                HomeQuality::where('ZYH', '=', $ZYH)->delete();
                return ToolsService::returnData(200, ['ZYH' => $ZYH, 'req' => 0, 'sftc' => 0], '');
            }
        }

        Log::info('ZYH', ['ZYH' => $ZYH]);
        $info = null;
        try {
            //先删除home_quality表中ZYH为$ZYH的记录
            $pkControl = PrimaryKeyControl::query()->where('tablename', '=', 'patient_info')->first();
            //Log::info('pkc->', ['pkc' => $pkControl ? $pkControl->toArray() : null]);
            if ($pkControl) {
                $field = $pkControl->field;
                $infoResult = DataSyncService::getInstance(2)->setByNameSql('patient_info', 1)
                    ->setWhere($field, $ZYH)
                    ->getResult();
                if (!empty($infoResult)) {
                    //确定有数据，更新到数据库
                    //tablename = basy 且 unique_value = $ZYH更新，不是就插入
                    /* try {
                        DataSyncKey::query()
                            ->updateOrCreate(
                                [
                                    'tablename' => 'basy',
                                    'unique_value' => $ZYH
                                ],
                                [
                                    'unique_key' => $field,
                                    'jm_key' => 'ZYH_ID',
                                ]
                            );
                    } catch (\Exception $e) {
                        Log::error('DataSyncKey 更新失败', [
                            'error' => $e->getMessage(),
                            'ZYH' => $ZYH,
                            'field' => $field
                        ]);
                    } */

                    $info = $infoResult[0];
                    if (isset($info['MED_REC_ID']) && class_exists(HomeQuality::class)) { // 确保 MED_REC_ID 存在且模型存在
                        HomeQuality::query()->where('ZYH', '=', $info['MED_REC_ID'])->delete();
                    } else {
                        if (!isset($info['MED_REC_ID'])) {
                            Log::warning(" patient_info record found for ZYH: {$ZYH}, but MED_REC_ID is missing.", ['info' => $info]);
                        }
                        if (!class_exists(HomeQuality::class)) {
                            Log::error("HomeQuality model not found. Cannot delete records.");
                        }
                    }
                } else {
                    Log::warning("No patient_info record found for ZYH: {$ZYH} during homeBmyQuality prep.");
                    return ToolsService::returnData(4004, [], '未找到患者基本信息');
                }
            } else {
                Log::error("PrimaryKeyControl not found for table: patient_info during homeBmyQuality prep.");
                return ToolsService::returnData(5000, [], '服务器内部错误：缺少主键配置');
            }
        } catch (\Exception $e) {
            Log::error("Error preparing for homeBmyQuality (fetching patient info or deleting HomeQuality)", ['error' => $e->getMessage(), 'ZYH' => $ZYH]);
            return ToolsService::returnData(5000, [], '服务器内部错误');
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

        $basyQualityService = null;
        if (class_exists(BasyQualityService::class)) {
            $basyQualityService = new BasyQualityService();
        } else {
            Log::error("BasyQualityService not found.");
        }

        $content = [];
        $sftc = 0;
        $isQz = ['score' => 0, 'isQz' => 0]; // 初始化 isQz

        try {
            $content = null;
            // 检查 BmyQuality 类和 zkInfo 方法是否存在
            $basyQuality = new BmyQuality();
            $content = $basyQuality->zkInfo(["MED_REC_ID" => $ZYH]);

            //Log::info('获取内容数据', ['contentData_preview' => substr(json_encode($content), 0, 500)]); // 记录部分数据

            // 确保 $content['data'] 和 $content['zy_zkjl'] 存在且是数组或可编码对象
            $hospitalName = $content['data']['HOSPITAL_NAME'] ?? ($info['hospital_name'] ?? ''); // 从 content 或 info 获取
            $contentJson = is_array($content) || is_object($content) ? json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}';
            $zyZkjlJson = isset($content['zy_zkjl']) && (is_array($content['zy_zkjl']) || is_object($content['zy_zkjl'])) ? json_encode($content['zy_zkjl'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}';

            $insertData = [
                'hospital_name' => $hospitalName,
                'ZYH' => $ZYH,
                'content' => $contentJson,
                'ZY_ZKJL' => $zyZkjlJson,
            ];

            // 检查 HomeBmyRequestContent 模型是否存在
            if (class_exists(HomeBmyRequestContent::class)) {
                try {
                    HomeBmyRequestContent::query()->insert($insertData);
                } catch (\Exception $e) {
                    Log::error('记录 HomeBmyRequestContent 失败', ['error' => $e->getMessage(), 'data_preview' => substr(json_encode($insertData), 0, 500)]);
                }
            } else {
                Log::error("HomeBmyRequestContent model not found. Skipping insert.");
            }


            // 只有在 $content 有效且 $basyQualityService 存在时才执行质控
            if (!empty($content) && $basyQualityService) {
                $zkmsg = 0; // 初始化

                $isQz = $basyQualityService->qualityContrl($content, $errorRuleData);
                // 检查 CustomZKController 是否存在
                if (class_exists(CustomZKController::class)) {
                    try {
                        $customZK = new CustomZKController();
                        $zkmsg = $customZK->customizeRule($ZYH, $content, 1);
                    } catch (\Exception $e) {
                        Log::error("Error executing customizeRule for ZYH: {$ZYH}", ['error' => $e->getMessage()]);
                        // 根据业务决定是否继续
                    }
                } else {
                    Log::warning("CustomZKController not found. Skipping customizeRule.");
                }
            } else {
                if (empty($content)) {
                    Log::warning("Skipping BasyQualityService->qualityContrl due to empty content for ZYH: {$ZYH}");
                }
                if (!$basyQualityService) {
                    Log::warning("Skipping BasyQualityService->qualityContrl because service is unavailable for ZYH: {$ZYH}");
                }
            }


            //zkmsg + isQz['score'] > 0 就返回1
            /* if (($zkmsg + ($isQz['score'] ?? 0)) > 0) {
                $sftc = 1;
            } */
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $insertErrorData = ['ZYH' => $ZYH, 'error_info' => $msg];
            // 检查 HomeBmyRequestContent 模型是否存在
            if (class_exists(HomeBmyRequestContent::class)) {
                try {
                    HomeBmyRequestContent::query()->insert($insertErrorData);
                } catch (\Exception $insertEx) {
                    Log::error('记录 HomeBmyRequestContent 错误信息也失败', ['error' => $insertEx->getMessage(), 'original_error' => $msg, 'ZYH' => $ZYH]);
                }
            } else {
                Log::error("HomeBmyRequestContent model not found. Skipping error info insert.");
            }

            Log::error('编码员质控主逻辑 error', ['msg' => $msg, 'ZYH' => $ZYH, 'exception' => $e]);
        }

        $reqValue = 0;
        $homequality = HomeQuality::query()->where('ZYH', '=', $ZYH)->where('is_del', '=', 0)->get()->toArray();
        if (!empty($homequality)) {
            //homequality数量>=1,sftc=1,没有就是0
            $sftc = count($homequality) > 0 ? 1 : 0;
            foreach ($homequality as $v) {
                if ($v['error_rule'] > 1000000) {
                    $rule = RuleSetting::query()->where('id', '=', $v['error_rule'] - 1000000)->first()->toArray();
                    if ($rule['error_level'] == '1') {
                        $reqValue++;
                    }
                } else {
                    $rule = ErrorRule::query()->where('id', '=', $v['error_rule'])->first()->toArray();
                    if ($rule['bmy_level'] == 0) {
                        $reqValue++;
                    }
                }
            }
        }
        return ToolsService::returnData(200, ['ZYH' => $ZYH, 'req' => $reqValue, 'sftc' => $sftc], '');
    }

    // 编码员质控（测试用） - 增加健壮性
    public function bmyQualityTest(Request $request)
    {
        $data = $request->post();

        $ZYH = $data['data']['MED_REC_ID'] ?? null;
        if (empty($ZYH)) {
            return ToolsService::returnData(4001, [], '请求的参数有误，缺少 MED_REC_ID');
        }

        // 获取科室
        $depData = [];
        if (class_exists(Department::class)) {
            $hospital_name = config('confAdmin.hospital_name');
            $depData = Department::query()
                ->where('hospital_name', '=', $hospital_name)
                ->pluck('dep_id', 'dep_name')->toArray();
        } else {
            Log::error("Department model not found in bmyQualityTest.");
        }

        Log::info('病案首页事中质控请求参数：费用：' . time()); //打印时间


        // 质控规则获取
        $errorRuleDataArray = [];
        if (class_exists(ErrorRule::class)) {
            $errorRuleDataArray = ErrorRule::query()
                ->where('node', 'like', "%终末%")
                ->where('status', '=', 0)
                ->orderBy('id')
                ->get()->keyBy('id')->toArray();
        } else {
            Log::error("ErrorRule model not found in bmyQualityTest.");
        }


        $content = [
            'data' => $data['data'] ?? [],
            'diagnosis' => $data['diagnosis'] ?? [],
            'operation' => $data['operation'] ?? [],
            'icu' => $data['icu'] ?? [],
            'fy' => $data['fy'] ?? '', // 保持原样，可能是混合类型
            'other' => $data['other'] ?? [],
        ];

        $fyJson = isset($data['fy']) && (is_array($data['fy']) || is_object($data['fy']) || is_string($data['fy'])) ? json_encode($data['fy'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}';

        $insertData = [
            'hospital_name' => $data['data']['ZA03'] ?? '',
            'ZYH' => $ZYH,
            'content' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'fy_content' => $fyJson
        ];

        if (class_exists(HomeBmyRequestContent::class)) {
            try {
                HomeBmyRequestContent::query()->insert($insertData);
            } catch (\Exception $e) {
                Log::error('记录 HomeBmyRequestContent (测试) 失败', ['error' => $e->getMessage(), 'data_preview' => substr(json_encode($insertData), 0, 500)]);
            }
        } else {
            Log::error("HomeBmyRequestContent model not found in bmyQualityTest. Skipping insert.");
        }


        if (class_exists(HomeBmyQualityService::class)) {
            try {
                $homeBmyQualityService = new HomeBmyQualityService();
                $homeBmyQualityService->qualityContrl($ZYH, $content, $errorRuleDataArray, $depData);
            } catch (\Exception $e) {
                $msg = $e->getMessage();
                Log::error('bmyQualityTest 执行 qualityContrl 失败', ['msg' => $msg, 'ZYH' => $ZYH, 'exception' => $e]);
                return ToolsService::returnData(5001, [], '测试质控执行失败: ' . $msg);
            }
        } else {
            Log::error("HomeBmyQualityService not found in bmyQualityTest. Skipping quality control.");
            return ToolsService::returnData(5001, [], '测试质控服务不可用');
        }


        return ToolsService::returnData(200, ['ZYH' => $ZYH], '');
    }

    /**
     * 编码员质控（新） todo 测试用 - 增加健壮性
     * @param Request $request
     * @return array
     */
    public function homeBmyQualityV2(Request $request)
    {
        $data = $request->post();

        Log::info('编码员质控请求参数（V2）：', $data);

        $ZYH = $data['ZYH'] ?? '';
        if (empty($ZYH)) {
            return ToolsService::returnData(4001, [], '请求的参数有误，缺少 ZYH');
        }

        // 获取质控规则
        $errorRuleData = [];
        if (class_exists(ErrorRule::class) && method_exists(ErrorRule::class, 'getErrorRule')) {
            $errorRuleData = ErrorRule::getErrorRule([0, 2]);
        } else {
            Log::error("ErrorRule model or getErrorRule method not found in homeBmyQualityV2.");
        }


        $homeSzService = null;
        if (class_exists(HomeSzService::class)) {
            $homeSzService = new HomeSzService();
        } else {
            Log::error("HomeSzService not found in homeBmyQualityV2.");
        }

        $basyQualityService = null;
        if (class_exists(BasyQualityService::class)) {
            $basyQualityService = new BasyQualityService();
        } else {
            Log::error("BasyQualityService not found in homeBmyQualityV2.");
        }

        $isQz = 0; // 初始化

        // 只有当必要的服务存在时才继续
        if ($homeSzService && $basyQualityService) {
            try {
                // 获取用户信息
                $patientInfoResult = $homeSzService->getOdsInfo($homeSzService->getInfoSql(), $ZYH);
                if (!empty($patientInfoResult)) {
                    $patientInfo = $patientInfoResult[0];

                    // 获取其他信息
                    $feeDetailedData = $homeSzService->getOdsInfo($homeSzService->getFeeDetailedSql(), $ZYH);
                    $IcuData = $homeSzService->getOdsInfo($homeSzService->getHospitalSql(), $ZYH);
                    $diagnosisData = $homeSzService->getOdsInfo($homeSzService->getDiagnosisSql(), $ZYH);
                    $operationData = $homeSzService->getOdsInfo($homeSzService->getOperationSql(), $ZYH);
                    $otherResult = $homeSzService->getOdsInfo($homeSzService->getOtherSql(), $ZYH);
                    $other = $otherResult[0] ?? [];
                    $yzData = $homeSzService->getOdsInfo($homeSzService->getYzSql(), $ZYH);
                    $zyZkjlData = $homeSzService->getOdsInfo($homeSzService->getZyZkjlSql(), $ZYH);

                    $contentData = [
                        'data' => $patientInfo,
                        'diagnosis' => $diagnosisData ?? [],
                        'operation' => $operationData ?? [],
                        'icu' => $IcuData ?? [],
                        'other' => $other,
                    ];

                    $contentJson = json_encode($contentData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $fyJson = json_encode($feeDetailedData ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $yzJson = json_encode($yzData ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $zkjlJson = json_encode($zyZkjlData ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);


                    $insertData = [
                        'hospital_name' => $patientInfo['ZA03'] ?? '',
                        'ZYH' => $ZYH,
                        'content' => $contentJson,
                        'fy_content' => $yzJson,    // 保持原逻辑 (fy_content <- yzData)
                        'yz_content' => $fyJson,    // 保持原逻辑 (yz_content <- feeDetailedData)
                        'ZY_ZKJL' => $zkjlJson,
                    ];

                    if (class_exists(HomeBmyRequestContent::class)) {
                        try {
                            HomeBmyRequestContent::query()->insert($insertData);
                        } catch (\Exception $e) {
                            Log::error('记录 HomeBmyRequestContent (V2) 失败', ['error' => $e->getMessage(), 'data_preview' => substr(json_encode($insertData), 0, 500)]);
                        }
                    } else {
                        Log::error("HomeBmyRequestContent model not found in homeBmyQualityV2. Skipping insert.");
                    }


                    $contentForQuality = [
                        'data' => $patientInfo,
                        'diagnosis' => $diagnosisData ?? [],
                        'operation' => $operationData ?? [],
                        'icu' => $IcuData ?? [],
                        'fy' => $feeDetailedData ?? [], // 使用正确的费用数据
                        'other' => $other,
                        'yz' => $yzData ?? [],       // 使用正确的医嘱数据
                        'zy_zkjl' => $zyZkjlData ?? []
                    ];

                    // 执行质控
                    $isQzResult = $basyQualityService->qualityContrl($contentForQuality, $errorRuleData);
                    $isQz = $isQzResult['isQz'] ?? 0;
                } else {
                    Log::warning("homeBmyQualityV2 未找到 ZYH: {$ZYH} 的患者信息");
                }
            } catch (\Exception $e) {
                $isQz = 0;
                $msg = $e->getMessage();

                $insertErrorData = ['ZYH' => $ZYH, 'error_info' => $msg];
                if (class_exists(HomeBmyRequestContent::class)) {
                    try {
                        HomeBmyRequestContent::query()->insert($insertErrorData);
                    } catch (\Exception $insertEx) {
                        Log::error('记录 HomeBmyRequestContent (V2) 错误信息也失败', ['error' => $insertEx->getMessage(), 'original_error' => $msg, 'ZYH' => $ZYH]);
                    }
                } else {
                    Log::error("HomeBmyRequestContent model not found in homeBmyQualityV2. Skipping error insert.");
                }


                Log::error('编码员质控(V2) error', ['msg' => $msg, 'ZYH' => $ZYH, 'exception' => $e]);
            }
        } else {
            Log::error("Cannot execute homeBmyQualityV2 due to missing services (HomeSzService or BasyQualityService).");
            return ToolsService::returnData(500, ['ZYH' => $ZYH, 'is_qz' => 0], '服务器内部错误：依赖服务缺失');
        }


        return ToolsService::returnData(200, ['ZYH' => $ZYH, 'is_qz' => $isQz], '');
    }

    private function getCombinedRequiredCounts(string $zyh): array
    {
        $homeReq = $this->getHomeRequiredCount($zyh);
        $caseReq = $this->getCaseRequiredCount($zyh);

        //获取rulewormap id=20067的keyword
        $keyword20067 = RuleWordMap::query()->where('id', 20067)->value('keyword') ?? '0';
        //如果=1或者='1',则req=$homeReq + $caseReq,否则req=$caseReq
        $req = $keyword20067 == 1 || $keyword20067 == '1' ? $homeReq + $caseReq : $homeReq;
        return [
            'req' => $req,
            'home_req' => $homeReq,
            'case_req' => $caseReq,
            'home_req_count' => $homeReq,
            'case_req_count' => $caseReq,
        ];
    }

    private function getHomeRequiredCount(string $zyh): int
    {
        $ruleCount = ErrorV2::query()
            ->where('ZYH', '=', $zyh)
            ->where('error_v2.status', '=', 0)
            ->join('error_rule', 'error_v2.error_rule', '=', 'error_rule.id')
            ->where('error_rule.level', '=', 0)
            ->count();

        $customRuleCount = ErrorV2::query()
            ->where('ZYH', '=', $zyh)
            ->where('error_v2.status', '=', 0)
            ->join('rule_setting', function ($join) {
                $join->on(
                    DB::raw('error_v2.error_rule'),
                    '=',
                    DB::raw('rule_setting.id + 1000000')
                );
            })
            ->where('rule_setting.error_level', '=', 1)
            ->count();

        return $ruleCount + $customRuleCount;
    }

    private function getCaseRequiredCount(string $zyh): int
    {
        $ruleCount = CaseQuality::query()
            ->where('JZHM', '=', $zyh)
            ->join('case_rule', 'case_quality.rule_id', '=', 'case_rule.id')
            ->where('case_rule.status', '=', 1)
            ->where('case_rule.level', '=', 1)
            ->distinct()
            ->count('case_quality.rule_id');

        $customRuleCount = CaseQuality::query()
            ->where('JZHM', '=', $zyh)
            ->join('rule_setting', function ($join) {
                $join->on(
                    DB::raw('case_quality.rule_id'),
                    '=',
                    DB::raw('rule_setting.id + 1000000')
                );
            })
            ->where('rule_setting.status', '=', 1)
            ->where('rule_setting.error_level', '=', 1)
            ->distinct()
            ->count('case_quality.rule_id');

        return $ruleCount + $customRuleCount;
    }

    /** 清洗地址
     * @params string $address
     * @param string $regexp
     * @param bool $isCityLevel 是否为市级匹配，用于处理县级市的情况
     * @param bool $isAreaLevel 是否为区/县级别匹配，用于处理县级市的情况
     * return array
     */
    protected function parseAndTrimAddress($address, $regexp, $isCityLevel = false, $isAreaLevel = false)
    {
        Log::info('parseAndTrimAddress 开始', [
            'address' => $address,
            'regexp' => $regexp,
            'isCityLevel' => $isCityLevel,
            'isAreaLevel' => $isAreaLevel
        ]);

        // 特殊处理：新疆维吾尔自治区直辖县级行政单位
        // 例如："新疆维吾尔自治区自治区直辖县级行政单位石河子市"
        // 省：新疆维吾尔自治区，市：自治区直辖县级行政单位，县：石河子市
        if (mb_strpos($address, '自治区直辖县级行政单位') !== false) {
            Log::info('检测到新疆自治区直辖县级行政单位特殊格式', ['address' => $address]);

            // 如果是市级匹配
            if ($isCityLevel) {
                $result = '自治区直辖县级行政单位';
                $address = str_replace($result, '', $address);
                Log::info('市级匹配 - 新疆自治区直辖县级行政单位', ['result' => $result, 'remainingAddress' => $address]);
                return ['result' => $result, 'address' => $address];
            }

            // 如果是县级匹配
            if ($isAreaLevel) {
                // 提取"自治区直辖县级行政单位"后面的市名（如"石河子市"）
                $pattern = '/自治区直辖县级行政单位(.*?市)/u';
                preg_match($pattern, $address, $matches);
                if (!empty($matches[1])) {
                    $result = $matches[1];
                    $address = str_replace($result, '', $address);
                    Log::info('县级匹配 - 新疆自治区直辖县级行政单位下的市', ['result' => $result, 'remainingAddress' => $address]);
                    return ['result' => $result, 'address' => $address];
                }
            }
        }

        // 特殊处理：北京市城区
        // 例如："北京市北京市城区西城区"
        // 省：北京（省级正则匹配"北京"不带"市"），市：北京市城区，县：西城区
        // 注意：省级正则匹配"北京"后，剩余可能是"市北京市城区西城区"
        if (mb_strpos($address, '北京市城区') !== false) {
            Log::info('检测到北京市城区特殊格式', ['address' => $address]);

            // 如果是市级匹配
            if ($isCityLevel) {
                $result = '北京市城区';
                $address = str_replace($result, '', $address);
                Log::info('市级匹配 - 北京市城区', ['result' => $result, 'remainingAddress' => $address]);
                return ['result' => $result, 'address' => $address];
            }
        }

        // 特殊处理：重庆市重庆XX区/县 或 重庆市重庆
        // 例如："重庆市重庆XX区"、"重庆市重庆XX县"、"重庆市重庆彭水苗族土家族自治县" 或 "重庆市重庆"
        // 省：重庆市，市：重庆，县：XX区/县（如果有）
        // 注意：省级正则匹配"重庆市"后，剩余地址是"重庆XX区/县"或"重庆"
        if ($isCityLevel && mb_strpos($address, '重庆') === 0) {
            // 检查是否是"重庆市重庆XX区/县"或"重庆市重庆"格式（剩余地址以"重庆"开头）
            // 如果剩余地址就是"重庆"或以"重庆"开头后面还有内容，都需要特殊处理
            // 这样可以处理"重庆XX区"、"重庆XX县"、"重庆XX自治县"等各种情况
            if ($address === '重庆' || mb_strlen($address) > 2) {
                Log::info('检测到重庆市重庆XX区/县或重庆市重庆特殊格式', ['address' => $address]);
                // 市级匹配时，只匹配"重庆"（不带"市"）
                $result = '重庆';
                $address = str_replace($result, '', $address);
                Log::info('市级匹配 - 重庆市重庆XX区/县或重庆市重庆格式，匹配"重庆"', ['result' => $result, 'remainingAddress' => $address]);
                return ['result' => $result, 'address' => $address];
            }
        }

        // 县级匹配：处理市级匹配"北京市城区"后的残留（如"市西城区"）
        // 注意：只有当地址以"市"开头，且后面不是"中区"、"辖区"等正常区名时，才认为是残留
        if ($isAreaLevel) {
            // 如果地址以"市"开头且后面跟着区名
            if (mb_strpos($address, '市') === 0) {
                // 检查是否是"市中区"、"市辖区"等正常区名
                $normalDistrictNames = ['市中区', '市辖区', '市北区', '市南区'];
                $isNormalDistrict = false;
                foreach ($normalDistrictNames as $name) {
                    if (mb_strpos($address, $name) === 0) {
                        $isNormalDistrict = true;
                        break;
                    }
                }

                // 如果不是正常区名，说明是残留的"市"字（如"市西城区"），需要移除
                if (!$isNormalDistrict && preg_match('/(.*?区)/u', $address, $matches)) {
                    Log::info('县级匹配 - 检测到残留的"市"字', ['address' => $address]);
                    // 移除开头的"市"字
                    $address = mb_substr($address, 1);
                    // 重新匹配区名
                    preg_match('/(.*?区)/u', $address, $matches);
                    if (!empty($matches[1])) {
                        $result = $matches[1];
                        $address = str_replace($result, '', $address);
                        Log::info('县级匹配 - 处理残留的"市"字后匹配区名', ['result' => $result, 'remainingAddress' => $address]);
                        return ['result' => $result, 'address' => $address];
                    }
                }
            }
        }

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

        // 如果是省级匹配（既不是市级也不是县级），对直辖市做特殊处理
        if (!$isCityLevel && !$isAreaLevel) {
            // 直辖市：北京、天津、上海、重庆
            // 如果匹配到的是直辖市名称（不带"市"），且后面紧跟"市"字，则将"市"也包含进来
            $municipalities = ['北京', '天津', '上海', '重庆'];
            if (!empty($result) && in_array($result, $municipalities)) {
                // 检查原地址中匹配结果后面是否紧跟"市"字
                $pattern = '/' . preg_quote($result, '/') . '市/u';
                if (preg_match($pattern, $address)) {
                    $result .= '市';
                    Log::info('省级匹配 - 直辖市特殊处理，添加"市"字', ['result' => $result, 'address' => $address]);
                }
            }
        }

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
