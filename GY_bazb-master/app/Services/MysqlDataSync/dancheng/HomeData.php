<?php

namespace App\Services\MysqlDataSync\dancheng;

use App\Model\Icu;
use App\Model\Yzb;
use Carbon\Carbon;
use App\Model\PACS;
use App\Model\Bllb1;
use App\Model\Bllb292;
use App\Model\Bllb303;
use App\Model\MS_BRDA;
use App\Model\ZY_BRRY;
use App\Model\OMR_BL01;
use App\Model\Bllb294_45;
use App\Model\PatientAdd;
use App\Model\Bllb294_295;
use App\Model\DataSyncLog;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLSY;
use App\Model\EMR_BL_BLXG;
use App\Model\FeeDetailed;
use App\Model\PatientInfo;
use App\Model\MainDiagnosis;
use App\Model\MainOperation;
use App\Model\OtherDiagnosis;
use App\Model\PatientCostInfo;
use App\Services\EsSaveService;
use App\Model\QualitySendMsgLog;
use App\Model\SecondaryOperation;
use App\Services\BlDataFormatService;
use App\Console\Commands\ShizhongDataSync;
use App\Console\Commands\OmrQualityCommand;
use App\Console\Commands\DataFormat\OMR_BL01 as OMR_BL01_Format;

class HomeData
{

    public static $con;
    public static $con2;

    public function getConnect()
    {
        if (self::$con) {
            return self::$con;
        }
        $username = env('ORACLE_USERNAME', '');
        $password = env('ORACLE_PASSWORD', '');
        $connection = env('ORACLE_HOST', '');
        $port = env('ORACLE_PORT', '1521');
        $tns = env('ORACLE_TNS', '');
        $con = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
        self::$con = $con;
    }

    public function getConnect2()
    {
        if (self::$con2) {
            return self::$con2;
        }
        $username = 'neihanzk_qm';
        $password = 'neihanzk_qm123';
        $connection = '172.16.100.183';
        $port = env('ORACLE_PORT', '1521');
        $tns = 'orcl';
        self::$con2 = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
    }

    /**
     * 获取用户主信息
     * @return array
     */
    public function OMRBL01($startTime = "")
    {
        $this->getConnect();
        $this->getConnect2();
        $timeIndex = Carbon::now()->subDays(1)->startOfDay()->timestamp;
        if (!empty($startTime)) {
            $startTime = strtotime($startTime);
        } else {
            $startTime = $timeIndex;
        }

        $sql = "SELECT * FROM V_CLINIC_PAT_INFO WHERE VISIT_DATE > TO_DATE('" . date("Y-m-d", $startTime) . "000000', 'yyyy-MM-dd HH24:mi:ss')";
        echo $sql . PHP_EOL;
        $stmt = oci_parse(self::$con2, $sql);
        oci_execute($stmt, OCI_DEFAULT);

        $rows = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $rows[] = $row;
        }

        // 处理每页数据 $rows
        foreach ($rows as $info) {
            
            $sql = "SELECT ZYH AS BRID, ZYH AS mzh, BLBH, BLLB, MBLB, to_char(JLSJ,'yyyy-mm-dd hh24:mi:ss') AS JLSJ, to_char(CJSJ,'yyyy-mm-dd hh24:mi:ss') AS CJSJ,  to_char(WCSJ,'yyyy-mm-dd hh24:mi:ss') AS WCSJ, SXYS, SXKS, SXKSDM AS SXKS, BRKSDM AS BRKS, BRKS as ks, BLZTDM AS BLZT, BLNRTXT AS BLNR_TXT FROM EMR_NEIHANZK_QM.OMR_BL01 where PATIENT_ID = '{$info['PATIENT_ID']}' and VISIT_ID = '{$info['VISIT_ID']}'";
            $data = oci_parse(self::$con, $sql);
            oci_execute($data, OCI_DEFAULT);
            $result = [];
            while (@$row = oci_fetch_assoc($data)) {
                $result[] = $row;
            }
            echo '处理数据：' . count($result) . PHP_EOL;

            $BRID = array_column($result, 'BRID');
            $msBrdaData = MS_BRDA::query()->whereIn("BRID", $BRID)->get(['BRID', 'SFZH'])->pluck('SFZH', 'BRID')->toArray();
            foreach ($result as $item) {

                // 判断创建时间是否大于2025年01月01日
                if (!empty($item['CJSJ'])) {
                    $createTime = strtotime($item['CJSJ']);
                    $limitTime = strtotime('2025-01-01 00:00:00');
                    if ($createTime < $limitTime) {
                        continue;
                    }
                } else {
                    continue;
                }
                
                $msBrdaDataItem = !empty($msBrdaData[$item['BRID']]) ? $msBrdaData[$item['BRID']] : [];
                //身份证号
                $item['SFZH'] = '';
                $SFZH = !empty($msBrdaDataItem['SFZH']) ? $msBrdaDataItem['SFZH'] : '';
                if (mb_strlen($SFZH) > 14) {
                    $SFZH = desensitize($SFZH, 0, 6, '*');
                    $SFZH = desensitize($SFZH, 14, 2, '*');
                    $SFZH = desensitize($SFZH, 17, 1, '*');
                }
                $item['SFZH'] = $SFZH;
                $item['xm'] = $info['XM'];        
                $item['nl'] = $info['NL'];        
                $item['nl1'] = $info['NL'].'岁';        
                $item['xb'] = $info['XB'];        
                $item['ks'] = $item['KS'];        
                $item['BLNR_TXT'] = !empty($item['BLNR_TXT']) && !empty($item['BLNR_TXT']->size()) ? $item['BLNR_TXT']->load() : '';
                OMR_BL01::query()->updateOrInsert(["BLBH" => $item["BLBH"]], $item);
                $this->cleanBl01Data($item);
                
                // 执行质控
                $qualityCommand = new OmrQualityCommand();
                $qualityCommand->omrZk(1, '', '', $item['BLBH']);

            }
        }

    }


    /**
     * 清洗病历数据
     * @param array $result
     * @return void
     */
    public function cleanBl01Data($result = [])
    {
        $omrBl01Format = new OMR_BL01_Format();
        $blbh = $result['BLBH'];
        $saveData = [];

        // 处理病历内容
        $BLNR_TXT = $result['BLNR_TXT'];

        // 门诊病历有两套模板，如果第一个没有清洗出来住院号，则换第二种方法
        $saveData1 = $omrBl01Format->cleanDataFilter($BLNR_TXT, 1);
        if (empty($saveData1['mzh'])) {
            $saveData1 = $omrBl01Format->cleanDataFilterV2($BLNR_TXT, 1);
        }
        if ($saveData1['mzh']) {
            if (preg_match('/^(.*?)(\d{4}-\d{2}-\d{2}\d{2}:\d{2})/', $saveData1['mzh'], $mzhMatches)) {
                $saveData1['mzh'] = trim($mzhMatches[1]);
                $saveData1['jzsj'] = trim($mzhMatches[2]);
            }
        }
        $saveData = array_merge($saveData, $saveData1);
        if (!empty($saveData['jzsj'])) {
            $saveData['jzsj'] = substr($saveData['jzsj'], 0, 10) . ' ' . substr($saveData['jzsj'], 10);
        }

        // 西药处理
        if (preg_match('/&lt;西药&gt;(.*?)}/s', $BLNR_TXT, $matches)) {
            $xyContent = $matches[1];
            $saveData['xy'] = trim($xyContent);

            $xyLines = preg_split('/\s+/', trim($xyContent));
            $xyList = [];

            // 每3个元素为一组药品信息
            for ($i = 0; $i < count($xyLines); $i += 3) {
                if (isset($xyLines[$i]) && !empty(trim($xyLines[$i]))) {
                    $xyList[] = [
                        'ym' => $xyLines[$i] ?? '',
                        'yl' => $xyLines[$i + 1] ?? '',
                        'yf' => $xyLines[$i + 2] ?? '',
                        'pc' => '',
                    ];
                }
            }

            if ($xyList) {
                $saveData['xy_json'] = json_encode($xyList, JSON_UNESCAPED_UNICODE);
            }
        }
        // 提醒
        if (preg_match('/※提醒：(.*?)※/s', $BLNR_TXT, $matches)) {
            $saveData['tx'] = trim($matches[1]);
        }

        // 初诊、复诊、急诊
        $saveData['bl_type'] = '门诊';
        if (stripos($BLNR_TXT, '门(急)诊病历') !== false) {
            $saveData['bl_type'] = '门诊';
        } elseif (stripos($BLNR_TXT, '初诊') !== false) {
            $saveData['bl_type'] = '初诊';
        } elseif (stripos($BLNR_TXT, '复诊') !== false) {
            $saveData['bl_type'] = '复诊';
        } elseif (stripos($BLNR_TXT, '急诊') !== false) {
            $saveData['bl_type'] = '急诊';
        }
        
        unset($saveData['xm']);
        unset($saveData['nl']);
        unset($saveData['xb']);
        unset($saveData['mzh']);
        unset($saveData['ks']);

        if ($saveData) {
            $result = OMR_BL01::query()->where('BLBH', '=', $blbh)->update($saveData);
            if ($result) {
                EsSaveService::esSaveOmrBl01($blbh);
            }
        }
    }

    /**
     * @param string $startTime
     * 同步pacs数据
     */
    public function cleanPacsZYH($startTime = 0)
    {
        while (true) {
            if ($startTime >= time()) {
                break;
            }

            $startTimeStr = date("Y-m-d", $startTime);
            echo '时间：' . $startTimeStr . PHP_EOL;
            $startTime += 86400;
            $result = PACS::query()
                ->whereBetween('BGSJ', [$startTimeStr . " 00:00:00", $startTimeStr . " 23:59:59"])
                ->get(['id', 'ZYH', 'KDSJ', 'BAH', 'StudyUid', 'ZYCS'])
                ->toArray();

            foreach ($result as $item) {
                // CT\MR中如何ZYH=1可能是在院患者
                if ($item['ZYH'] == 1) {
                    $brry = ZY_BRRY::query()->where("AAA28", $item["BAH"])
                        ->where('AAB01', '<=', $item['KDSJ'])
                        ->where('AAC01', '=', "")
                        ->first(["ZYH"]);
                    if ($brry) {
                        PACS::query()->where('id', $item['id'])->update(['ZYH' => $brry->ZYH]);
                        continue;
                    }
                }

                if ($item['ZYH'] && $item['ZYH'] != 1) {
                    continue;
                }
                $ZYH = "";
                $brry = "";
                if (!empty($item["ZYCS"])) {
                    $brry = ZY_BRRY::query()->where(["AAA28" => $item["BAH"], "ZYCS" => $item["ZYCS"]])->first(["ZYH"]);
                }
                if (empty($brry) && !empty($item['KDSJ'])) {
                    $brry = ZY_BRRY::query()
                        ->where("AAA28", $item["BAH"])
                        ->where('AAB01', '<', $item['KDSJ'])
                        ->where('AAC01', '>', $item['KDSJ'])
                        ->first(["ZYH"]);
                }
                if ($brry) {
                    $ZYH = $brry->ZYH;
                }
                if (empty($ZYH)) {
                    continue;
                }
                PACS::query()->where('id', $item['id'])->update(['ZYH' => $ZYH]);
            }
        }
    }

    /**
     * @param string $startTime
     * 清洗patient_info表中的是否编目字段
     */
    public function isCATA($startTime = '')
    {
        $this->getConnect();
        $timeIndex = Carbon::now()->subDays(15)->startOfDay()->timestamp;
        if (!empty($startTime)) {
            $startTime = strtotime($startTime);
        } else {
            $startTime = $timeIndex;
        }

        while (true) {
            // 如果数据同步到最新时间，则重新从本年3月1日 0点0分0秒到现在
            if ($startTime >= time()) {
                $str = date("Y-m-d H:i:s", time()) . "完成一轮数据同步\n";
                $startTime = Carbon::now()->subDays(15)->startOfDay()->timestamp;
                echo $str;
            }

            $startTimeStr = date("Ymd", $startTime);
            echo '时间：' . $startTimeStr . PHP_EOL;
            $startTime += 86400;
            $sql = "SELECT MED_REC_ID FROM PORTAL_HIS.INIT_MED_REC_MAIN WHERE AAC01 BETWEEN TO_DATE('" . $startTimeStr . "000000', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('" . $startTimeStr . "235959', 'yyyy-MM-dd HH24:mi:ss')";

            $data = oci_parse(self::$con, $sql);
            oci_execute($data, OCI_DEFAULT);
            $result = [];
            while (@$row = oci_fetch_assoc($data)) {
                foreach ($row as &$value) {
                    $source_encoding = mb_detect_encoding($value);
                    $value = iconv($source_encoding, 'UTF-8', $value);
                }
                $result[] = $row;
            }
            echo '处理数据：' . count($result) . PHP_EOL;

            $zyh = array_column($result, "MED_REC_ID");
            PatientInfo::query()->whereIn("MED_REC_ID", $zyh)->update(["IS_CATA" => 1]);
        }
    }

    /**
     * @param string $startTime
     * @param string $endTime
     * @param string $zyh
     * 同步指定时间范围内的数据
     */
    public function index($startTime = '', $endTime = '', $zyh = "")
    {
        $this->getConnect();

        $timeIndex = Carbon::now()->subDays(15)->startOfDay()->timestamp;
        if (!empty($startTime) && !empty($endTime)) {
            $startTime = strtotime($startTime);
        } else {
            $startTime = $timeIndex;
        }

        while (true) {
            // 如果数据同步到最新时间，则重新从本年3月1日 0点0分0秒到现在
            if ($startTime >= time()) {
                $str = date("Y-m-d H:i:s", time()) . "完成一轮数据同步\n";
                $startTime = Carbon::now()->subDays(15)->startOfDay()->timestamp;
                echo $str;
            }
            echo date("Y-m-d H:i:s", $startTime) . PHP_EOL;
            // 获取用户信息
            $this->getData($zyh, ["bl01"], "", $startTime);
            $startTime += 86400;
        }
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function getData($zyh = "", $type = [], $blbh = "", $startTime = "")
    {
        // 如果数据库链接字段为初始化则重新链接
        if (!self::$con) {
            $this->getConnect();
        }
        if (!self::$con2) {
            $this->getConnect2();
        }

        try {
            // 医嘱
            if (empty($type) || in_array('yzb', $type)) {
                $yzbData = $this->getYzb($zyh);
                if (!empty($yzbData)) {
                    $this->addYzb($yzbData);
                }
            }

            // 费用明细
            if (empty($type) || in_array('fee_detailed', $type)) {
                $feeDetailedData = $this->getFeeDetailed($zyh);
                if (!empty($feeDetailedData)) {
                    $this->addFeeDetailed($feeDetailedData);
                }
            }

            // 医生签名
            if (empty($type) || in_array('bl01', $type)) {
                $this->addBLSY($zyh, $blbh);
            }
        } catch (\Throwable $e) {
            echo $e->getMessage() . '，所在行：' . $e->getLine() . PHP_EOL;
        }

        return true;
    }

    public function getBldata($zyh = "", $type = [], $blbh = "", $startTime = "")
    {
        // 如果数据库链接字段为初始化则重新链接
        if (!self::$con) {
            $this->getConnect();
        }
        if (!self::$con2) {
            $this->getConnect2();
        }
        if ($zyh) {
            $szds = new ShizhongDataSync();
            $szds->ZY_BRRY($zyh, 0, 0, 0);
        }

        if ($startTime) {
            echo $zyh . PHP_EOL;
        }
        // 医嘱
        if (empty($type) || in_array('yzb', $type)) {
            $yzbData = $this->getYzb($zyh);
            if (!empty($yzbData)) {
                $this->addYzb($yzbData);
            }
        }

        // 费用明细
        if (empty($type) || in_array('fee_detailed', $type)) {
            $feeDetailedData = $this->getFeeDetailed($zyh);
            if (!empty($feeDetailedData)) {
                $this->addFeeDetailed($feeDetailedData);
            }
        }

        // 医生签名-住院病历
        if (empty($type) || in_array('bl01', $type)) {
            $this->addBLSY($zyh, $blbh);
        }

        return true;
    }


    /**
     * 获取费用信息
     * @param $ZYH
     * @return array
     */
    public function getFyData($ZYH)
    {
        // 查询费用数据
        // ,FYGB,SYFYGB,ZJE
        //$sql = "SELECT ZYH as AAA28,FYXH,FYMC,ZFJE,to_char(JFRQ,'yyyy-mm-dd hh24:mi:ss') as JFRQ,FYSL,FYDJ,FYKS FROM PORTAL_HIS.V_ZY_FYMX WHERE ZYH=" . $ZYH;
        $sql = "SELECT A.*,to_char(JFRQ,'yyyy-mm-dd hh24:mi:ss') as JFRQ FROM PORTAL_HIS.V_JMGS_BASY_FYMX A WHERE ZYH=" . $ZYH;
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }

        if (empty($result)) {
            return false;
        }
        $insertData = [];
        foreach ($result as $val) {
            $insertData[] = [
                'AAA28' => $val['ZYH'], //zyh
                'FYXH' => $val['FYXH'] ?? '', //费用序号
                'FYMC' => $val['FYMC'] ?? '', //费用名称
                'ZFJE' => $val['ZFJE'] ?? '', //自付金额
                'JFRQ' => $val['JFRQ'] ?? '', //计费日期
                'FYSL' => $val['FYSL'] ?? '', //费用数量
                'FYDJ' => $val['FYDJ'] ?? '', //费用单价
                'ZJE' => $val['ZJE'] ?? '', //总金额
                'FYKS' => $val['FYKS'] ?? '', //费用科室
                'FYGB' => $val['FYGB'] ?? '',
                'SYFYGB' => $val['SYFYGB'] ?? '',
                'JLXH' => $val['JLXH'] ?? ''
            ];
        }
        FeeDetailed::query()->where('AAA28', '=', $ZYH)->delete();

        if (!empty($insertData)) {
            $chunkList = array_chunk($insertData, 1000);
            foreach ($chunkList as $value) {
                FeeDetailed::query()->insert($value);
            }
        }
    }



    /**
     * 医嘱
     * @param $ZYH
     * @return array
     */
    public function getYzb($ZYH)
    {

        $sj = PatientInfo::query()->where('MED_REC_ID', $ZYH)->first(['MED_REC_ID', 'AAB01', 'AAC01', 'PATIENT_ID', 'AAA29']);
        $PATIENT_ID = $sj->PATIENT_ID;
        $visitId = $sj->AAA29;

        // $sql = "SELECT A.*,to_char(TZSJ,'yyyy-mm-dd hh24:mi:ss') as TJ,to_char(XZJDSJ,'yyyy-mm-dd hh24:mi:ss') as XJ,to_char(TZQRSJ,'yyyy-mm-dd hh24:mi:ss') as TZJ,to_char(APSJ,'yyyy-mm-dd hh24:mi:ss') as AJ,to_char(KZSJ,'yyyy-mm-dd hh24:mi:ss') as KJ FROM PORTAL_HIS.BTF_EMR_YZB A WHERE ZYH=" . $ZYH;
        $sql = "SELECT ZYH AS ZYH, YZBXH AS YZBXH, BRKSDM AS BRKS, BRBQ AS BRBQ, YDYZLB AS YDYZLB, YZQXDM AS YZQX, YYSX AS YYSX, KZKSDM AS KZKS, KZYS AS KZYS, to_char(KZSJ,'yyyy-mm-dd hh24:mi:ss') AS KZSJ, YZMC AS YZMC, SYPC AS SYPC, GYTJ AS GYTJ, YCJL AS YCJL, JLDW AS JLDW, JJYZ AS JJYZ, BLYZ AS BLYZ, to_char(TZSJ,'yyyy-mm-dd hh24:mi:ss') as TJ, TZYS AS TZYS, YZZT AS YZZT, ZTBZ AS ZTBZ, XZJD AS XZJDGH, to_char(XZJDSJ,'yyyy-mm-dd hh24:mi:ss') as XJ, TZQR AS TZQRGH, to_char(TZQRSJ,'yyyy-mm-dd hh24:mi:ss') as TZJ, PSBZ AS PSBZ FROM NEIHANZK_QM.YZB WHERE PATIENT_ID='" . $PATIENT_ID . "' AND VISIT_ID=" . $visitId;
        $data = oci_parse(self::$con2, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        return $result;
    }

    public function addYzb($data)
    {
        $insertData = [];
        $zyh = 0;
        foreach ($data as $val) {
            $zyh = $val['ZYH'];
            if (!empty($val['DSG_OPERATION']) && $val['DSG_OPERATION'] == 'D') {
                // 删除预警信息
                QualitySendMsgLog::setStatus($val['ZYH'], 109, $val['YZBXH']);
                QualitySendMsgLog::setStatus($val['ZYH'], 110, $val['YZBXH']);
            } else {
                $yzzt = $val['YZZT'] ?? '';
                // 3作废,4停止,5已执行
                $yzztMap = [
                    '3' => '作废',
                    '4' => '停止',
                    '5' => '已执行',
                ];
                $yzzt = array_search($yzzt, $yzztMap) ?? '';
                $insertData[] = [
                    'ZYH' => $val['ZYH'],
                    'YZBXH' => $val['ZYH'] . '_' . $val['YZBXH'] ?? '',
                    'RID' => $val['BRID'] ?? '',
                    'YEPB' => $val['YEPB'] ?? '',
                    'BRKS' => $val['BRKS'] ?? '',
                    'BRBQ' => $val['BRBQ'] ?? '',
                    'BRCH' => $val['BRCH'] ?? '',
                    'YDYZLB' => $val['YDYZLB'] ?? '',
                    'XMLB' => $val['XMLB'] ?? '',
                    'XMID' => $val['XMID'] ?? '',
                    'XMDJ' => $val['XMDJ'] ?? '',
                    'YZZH' => $val['YZZH'] ?? '',
                    'YZQX' => $val['YZQX'] ?? '',
                    'YYSX' => $val['YYSX'] ?? '',
                    'KZKS' => $val['KZKS'] ?? '',
                    'KZYS' => $val['KZYS'] ?? '',
                    'KZSJ' => $val['KZSJ'] ?? '',
                    'YZMC' => $val['YZMC'] ?? '',
                    'YPCD' => $val['YPCD'] ?? '',
                    'FYSX' => $val['FYSX'] ?? '',
                    'SYPC' => $val['SYPC'] ?? '',
                    'GYTJ' => $val['GYTJ'] ?? '',
                    'YCJL' => $val['YCJL'] ?? '',
                    'JLDW' => $val['JLDW'] ?? '',
                    'ZL' => $val['ZL'] ?? '',
                    'ZLDW' => $val['ZLDW'] ?? '',
                    'JJYZ' => $val['JJYZ'] ?? '',
                    'BLYZ' => $val['BLYZ'] ?? '',
                    'TZSJ' => $val['TJ'] ?? '',
                    'TZYS' => $val['TZYS'] ?? '',
                    'YZZT' => $yzzt,
                    'ZXZT' => $val['ZXZT'] ?? '',
                    'KZDY' => $val['KZDY'] ?? '',
                    'ZTBZ' => $val['ZTBZ'] ?? '',
                    'XZJDGH' => $val['XZJDGH'] ?? '',
                    'XZJDSJ' => $val['XJ'] ?? '',
                    'TZQRGH' => $val['TZQRGH'] ?? '',
                    'TZQRSJ' => $val['TZJ'] ?? null,
                    'APSJ' => $val['AJ'] ?? null,
                    'YYTS' => $val['YYTS'] ?? null,
                    'YSZT' => $val['YSZT'] ?? '',
                    'SRCS' => $val['SRCS'] ?? null,
                    'SRSD' => $val['SRSD'] ?? '',
                    'ZXSD' => $val['ZXSD'] ?? '',
                    'DS' => $val['DS'] ?? null,
                    'DSDW' => $val['DSDW'] ?? '',
                    'PSBZ' => $val['PSBZ'] ?? '',
                    'PSJG' => $val['PSJG'] ?? null,
                    'ZFPB' => $val['ZFPB'] ?? '',
                    'YBLX' => $val['YBLX'] ?? '',
                    'SPBH' => $val['SPBH'] ?? null,
                    'CYJF' => $val['CYJF'] ?? '',
                    'PLSX' => $val['PLSX'] ?? '',
                    'CZBZ' => $val['CZBZ'] ?? '',
                    'BZXX' => $val['BZXX'] ?? '',
                    'SQDH' => $val['SQDH'] ?? '',
                    'ZXKS' => $val['ZXKS'] ?? '',
                    'YFGG' => $val['YFGG'] ?? '',
                    'YFDW' => $val['YFDW'] ?? '',
                    'YFBZ' => $val['YFBZ'] ?? '',
                    'SFSJ' => $val['SFSJ'] ?? '',
                    'YFYY' => $val['YFYY'] ?? '',
                    'YFYYYY' => $val['YFYYYY'] ?? '',
                    'QXKZ' => $val['QXKZ'] ?? '',
                    'YYPS' => $val['YYPS'] ?? '',
                    'FZLJ' => $val['FZLJ'] ?? '',
                    'PASSINDEX' => $val['PASSINDEX'] ?? '',
                    'QXMC' => $val['QXMC'] ?? '',
                    'YZPLZH' => $val['YZPLZH'] ?? '',
                    'LCTS' => $val['LCTS'] ?? '',
                    'ZLFY' => $val['ZLFY'] ?? '',
                    'YZLX' => $val['YZLX'] ?? '',
                    'SSYZ' => $val['SSYZ'] ?? '',
                    'CDA_PC' => $val['CDA_PC'] ?? '',
                    //                    'ZXSJ' => $val['ZJ'] ?? '', //---这个注释掉 oracle没有这个字段
                    'NWARN' => $val['NWARN'] ?? '',
                    'DSG_LDR_TIME' => $val['DSG_LDR_TIME'] ?? '',
                    'DSG_OPERATION' => $val['DSG_OPERATION'] ?? '',
                ];
            }
        }
        $yzb = Yzb::query()->where('ZYH', '=', $zyh)->get()->toArray();
        $oldYZBXH = array_column($yzb, 'YZBXH');
        $YZBXH = array_column($insertData, 'YZBXH');
        $deleteYZBXH = array_diff($oldYZBXH, $YZBXH);
        if ($deleteYZBXH) {
            Yzb::query()->whereIn('YZBXH', $deleteYZBXH)->delete();
            foreach ($deleteYZBXH as $xh) {
                QualitySendMsgLog::setStatus($val['ZYH'], 109, $xh);
                QualitySendMsgLog::setStatus($val['ZYH'], 110, $xh);
            }
        }

        if (!empty($insertData)) {
            foreach ($insertData as $value) {
                Yzb::query()->updateOrInsert(['YZBXH' => $value['YZBXH']], $value);
            }
        }
    }

    public function formatBL01BLMC306($bl01 = [])
    {

        $blmc = $bl01['BLMC'];
        //获取年份
        $cjsj = $bl01['CJSJ'];
        $year = date('Y', strtotime($cjsj));
        //获取cjsj的月份
        $cjsj_month = date('m', strtotime($cjsj));

        // 更灵活地匹配时间格式
        preg_match("/.*?(\d{2}\.\d{2} \d{2}:\d{2})/", $blmc, $timeMatches);

        $timePrefix = !empty($timeMatches[1]) ? $timeMatches[1] : '';

        if (!empty($timePrefix)) {
            // 正确解析MM.DD HH:MM格式
            if (preg_match('/(\d{2})\.(\d{2}) (\d{2}):(\d{2})/', $timePrefix, $parts)) {
                $month = $parts[1];
                $day = $parts[2];
                $hour = $parts[3];
                $minute = $parts[4];

                //如果创建时间是12月,month是01,年份加1
                if ($cjsj_month == 12 && $month == 1) {
                    $year = $year + 1;
                }

                // 直接构建标准格式
                $timePrefix = $month . '-' . $day . ' ' . $hour . ':' . $minute;
            } else {
                $timePrefix = null;
            }

            //拼上年份
            if (!empty($timePrefix)) {
                $timePrefix = $year . '-' . $timePrefix;
            } else {
                $timePrefix = null;
            }

            //删除原本的时间格式
            $blmc = preg_replace("/(\d{2}\.\d{2} \d{2}:\d{2})/", "", $blmc);
            $blmc = preg_replace("/\s+/", "", $blmc); //去除空格
            //拼接上新的时间
            $blmc = $timePrefix . ' ' . $blmc;
            //更新blmc
            if (!empty($timePrefix)) {
                EMR_BL_BL01::query()->where('BLBH', $bl01['BLBH'])->update(['BLMC' => $blmc, 'ZXSJ' => $timePrefix]);
            }
        }
    }

    public function formatBL01BLMC82($bl01 = [])
    {

        // 首先尝试匹配"记录时间：{YYYY-MM-DD HH:MM}"格式
        preg_match("/术前小结及术前讨论结论记录\s*\{\s*(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\s*\}/", $bl01['HJNR'], $timeMatches);
        $timePrefix = !empty($timeMatches[1]) ? $timeMatches[1] : '';

        if (!empty($timePrefix)) {
            // 如果无法提取时间，使用CJSJ
            $formattedTime = date('Y-m-d H:i:s', strtotime($bl01['CJSJ']));
            EMR_BL_BL01::query()->where('BLBH', $bl01['BLBH'])->update(['operation_time' => strtotime($formattedTime)]);
        }
    }

    /**
     * @param array $bl01
     * 格式化bl01表中的病例名称
     */
    public function formatBL01BLMC294($bl01 = [])
    {

        //$HJNR = $bl01['HJNR'];
        $blmc = $bl01['BLMC'];
        if (!empty($bl01['HJNR'])) {
            // 从HJNR中提取时间  2024-05-14 09:00:00
            preg_match("/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/", $bl01['HJNR'], $timeMatches);
            //增加格式匹配 '时间：2024-05-1411:33:12 姓名：性别：女
            preg_match("/^时间：(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", $bl01['HJNR'], $timeMatches2);
            //增加格式匹配 '2024-05-14 11:33'不包含秒
            preg_match("/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", $bl01['HJNR'], $timeMatches3);
            //增加格式匹配 '{2024-08-01 19:20}'花括号包围的时间格式
            preg_match("/\{(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\}/", $bl01['HJNR'], $timeMatches4);
            //增加格式匹配 '记录时间：2024-07-31 10:12'
            preg_match("/^记录时间：(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", $bl01['HJNR'], $timeMatches5);
            //增加格式匹配 '讨论日期：2024-07-31 10:12'不一定在开头
            preg_match("/讨论日期：(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", $bl01['HJNR'], $timeMatches6);
            //新增匹配格式 2024.07.01 10:55:20
            preg_match("/(\d{4}\.\d{2}\.\d{2} \d{2}:\d{2}:\d{2})/", $bl01['HJNR'], $timeMatches7);
            //新增匹配格式 2024.07.01 10:55
            preg_match("/(\d{4}\.\d{2}\.\d{2} \d{2}:\d{2})/", $bl01['HJNR'], $timeMatches8);
            // 从HJNR中提取时间  2024-05-14 09:00:00
            preg_match("/(\d{4}-\d{2}-\d{2}，\d{2}:\d{2})/", $bl01['HJNR'], $timeMatches9);
            $timePrefix = '';
            if (!empty($timeMatches[1])) {
                $timePrefix = $timeMatches[1];
            } else if (!empty($timeMatches2[1])) {
                $timePrefix = $timeMatches2[1];
            } else if (!empty($timeMatches3[1])) {
                $timePrefix = $timeMatches3[1];
            } else if (!empty($timeMatches4[1])) {
                $timePrefix = $timeMatches4[1];
            } else if (!empty($timeMatches5[1])) {
                $timePrefix = $timeMatches5[1];
            } else if (!empty($timeMatches6[1])) {
                $timePrefix = $timeMatches6[1];
            } else if (!empty($timeMatches7[1])) {
                $timePrefix = $timeMatches7[1];
            } else if (!empty($timeMatches8[1])) {
                $timePrefix = $timeMatches8[1];
            } else if (!empty($timeMatches9[1])) {
                $timePrefix = $timeMatches9[1];
                $timePrefix = str_replace('，', ' ', $timePrefix);
            }

            if ($timePrefix != '') {
                // 检查BLMC中是否已包含重复的时间格式
                // 先检查是否包含完全相同的时间格式
                $pattern = "/(\d{4}-\d{2}-\d{2} \d{2}:\d{2})([ ]+)\\1/";
                if (preg_match($pattern, $blmc)) {
                    // 如果存在重复的时间格式，去除重复部分
                    $blmc = preg_replace($pattern, "$1", $blmc);
                }

                //判断blmc是否包含时间格式,如果已经包含时间格式,跟timePrefix对比,如果相同就不改变,如果不同就替换
                $newblmc = $blmc;


                //如果blmc中包含2025.01.02 08:09这种格式，先删除
                $blmc = preg_replace("/(\d{4}\.\d{2}\.\d{2} \d{2}:\d{2})/", "", $blmc);
                //如果原本的blmc中包含01.02 08:09这种格式，先删除
                $blmc = preg_replace("/(\d{2}\.\d{2} \d{2}:\d{2})/", "", $blmc);

                //如果blmc中包含2025-01-02 08:09这种格式，先删除
                $blmc = preg_replace("/(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", "", $blmc);

                //判断blmc是否包含时间格式
                preg_match("/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/", $blmc, $timeMatches1);
                //最后删除所有空格
                $blmc = preg_replace("/\s+/", "", $blmc);
                // 增加对不带秒的时间格式的检查
                if (empty($timeMatches1[1])) {
                    preg_match("/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", $blmc, $timeMatches1);
                }

                if (!empty($timeMatches1[1])) {
                    if ($timeMatches1[1] != $timePrefix) {
                        //替换blmc中的时间格式
                        $newblmc = str_replace($timeMatches1[1], $timePrefix, $blmc);
                    }
                } else {
                    $newblmc = $timePrefix . ' ' . $blmc;
                }
                EMR_BL_BL01::query()->where('BLBH', $bl01['BLBH'])->update(['BLMC' => $newblmc, 'ZXSJ' => $timePrefix, 'YWSJ' => $timePrefix]);
            } else {
                EMR_BL_BL01::query()->where('BLBH', $bl01['BLBH'])->update(['BLMC' => $blmc]);
            }
        }
    }

    /**
     * @param string $zyh
     * @param string $blbh
     * @return bool
     * 同步病程记录相关数据
     */
    public function addBLSY($zyh = "", $blbh3 = "")
    {
        DataSyncLog::addData(['zyh' => $zyh, 'content' => '4、数据病程记录开始']);
        $sj = PatientInfo::query()->where('MED_REC_ID', $zyh)->first(['MED_REC_ID', 'AAB01', 'AAC01', 'PATIENT_ID', 'AAA29']);
        $AAA28 = $sj->PATIENT_ID;
        $visitId = $sj->AAA29;

        $bldf = new BlDataFormatService();
        $sql = "SELECT BLBH, ZYH AS JZHM, BLLB AS BLLB, BLMC AS BLMC, MBLB AS MBLB, TO_CHAR(WCSJ,'yyyy-mm-dd hh24:mi:ss') as ZXSJ, TO_CHAR(CJSJ,'yyyy-mm-dd hh24:mi:ss') as CJSJ, SXYSMC as SXYS, TO_CHAR(WCSJ,'yyyy-mm-dd hh24:mi:ss') as WCSJ,BLZTDM as BLZT,HJNR as HTML_PRINT,VISIT_ID as  AAA29,PATIENT_ID as BAH  FROM EMR_NEIHANZK_QM.V_JMGS_BASY_QBL WHERE PATIENT_ID = '{$AAA28}' AND VISIT_ID={$visitId}";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }

        DataSyncLog::addData(['zyh' => $zyh, 'content' => '5、本次同步数据量', 'data_nums' => count($data)]);
        if (empty($data) && !empty($zyh)) {
            DataSyncLog::addData(['zyh' => $zyh, 'content' => '6、未获取到病程记录，清空mysql中的病程记录']);
            // 未同步到数据则清空bl01
            EsSaveService::deleteBl01ByZyh($zyh);
            return false;
        }

        if (empty($blbh3)) {
            $diffBLBH = array_column($data, "BLBH");
            // 检查数据表中有没有不在新同步的数据中的blbh
            $diffRes = EMR_BL_BL01::query()->where('JZHM', $zyh)->whereNotIn("BLBH", $diffBLBH)->get(["BLBH"])->toArray();
            if ($diffRes) {
                foreach ($diffRes as $blbh) {
                    DataSyncLog::addData(['zyh' => $zyh, 'content' => '7、删除mysql中的病程记录：' . $blbh['BLBH']]);
                    EsSaveService::deleteBl01ByBLBH($blbh['BLBH']);
                }
            }
        } else {
            EsSaveService::deleteBl01ByBLBH($blbh3);
        }

        $bllbMap = [
            '34763_1' => [292, 292],
            '34763_13' => [292, 292],
            '34763_1_xg' => [292, 292],
            '34763_1_dx' => [292, 292],
            '34763_9' => [292, 292],
            '34130_1' => [294, 295],
            '34130_3' => [294, 296],
            '34130_4' => [294, 50],
            '34130_5' => [294, 32],
            '34130_19' => [1, 1],
            '34130_39' => [1, 1],
            '34130_sc' => [1, 1],
            '34130_pgc' => [1, 1],
            '34130_tnb' => [1, 1],
            '34130_10' => [294, 42],
            '34130_13' => [288, 288],
            '34763_3' => [18, 20],
            '34763_11' => [18, 21],
            '34130_9' => [294, 45],
            '34130_16' => [294, 45],
            '34130_6' => [329, 32901],
            '34130_8' => [294, 27],
            '34130_7' => [294, 26],
            '34110-90' => [294, 515],
            'pfxg_sn10' => [294, 516],
            '34130_10' => [294, 42],
            '34130_12' => [294, 82],
            '34110-120' => [101, 101],
            '34130_27' => [43, 46],
            '34130_21' => [303, 306],
            '34110-90' => [303, 3069999],
            'pfxg_zl1' => [303, 76],
            '34130_11' => [303, 304],
            'pfxg_zl1' => [303, 76],
            '34130_56' => [303, 75],
            '34110-12' => [329, 8],
            '34110-66' => [329, 30308],
            '34110-13' => [329, 30308],
            '34110-46' => [329, 77],
            '34110-69' => [329, 59],
            '34110-74' => [329, 60],
            '34110-171' => [329, 88],
            '34110-8' => [2000185, 200018501],
            '34110-7' => [34, 3405],
            '34110-115' => [104, 10409],
            '34110-13' => [329, 30308],
            '34110-138' => [329, 30308],
            '34110-143' => [329, 30308],
            '34110-144' => [329, 30308],
            '34110-152' => [329, 30308],
            '34110-163' => [329, 30308],
            '34110-20' => [329, 30308],
            '34110-21' => [329, 30308],
            '34110-28' => [329, 30308],
            '34110-31' => [329, 30308],
            '34110-32' => [329, 30308],
            '34110-4' => [329, 30308],
            '34110-41' => [329, 30308],
            '34110-139' => [329, 30308],
            '34110-58' => [329, 30308],
            '34110-56' => [329, 30308],
            '34110-60' => [329, 30308],
            '34110-61' => [329, 30308],
            '34110-65' => [329, 30308],
            '34110-68' => [329, 30308],
            '34110-84' => [329, 30308],
            '34110-122' => [329, 30308],
            '34110-13' => [329, 30308],
            '34110-132' => [329, 30308],
            '34110-133' => [329, 30308],
            '34110-138' => [329, 30308],
            '34110-14' => [329, 30308],
            '34110-146' => [329, 30308],
            '34110-152' => [329, 30308],
            '34110-163' => [329, 30308],
            '34110-144' => [329, 30308],
            '34110-143' => [329, 30308],
            '34110-67' => [329, 30308],
            '34110-17' => [329, 30308],
            '34110-2' => [329, 30308],
            '34110-20' => [329, 30308],
            '34110-21' => [329, 30308],
            '34110-22' => [329, 30308],
            '34110-28' => [329, 30308],
            '34110-30' => [329, 30308],
            '34110-31' => [329, 30308],
            '34110-32' => [329, 30308],
            '34110-35' => [329, 30308],
            '34110-36' => [329, 30308],
            '34110-41' => [329, 30308],
            '34110-49' => [329, 30308],
            '34110-51' => [329, 30308],
            '34110-52' => [329, 30308],
            '34110-53' => [329, 30308],
            '34110-54' => [329, 30308],
            '34110-56' => [329, 30308],
            '34110-60' => [329, 30308],
            '34110-61' => [329, 30308],
            '34110-63' => [329, 30308],
            '34110-64' => [329, 30308],
            '34110-65' => [329, 30308],
            '34110-78' => [329, 30308],
            '34110-82' => [329, 30308],
            '34110-83' => [329, 30308],
            '34110-84' => [329, 30308],
            '34110-87' => [329, 30308],
            '34110-93' => [329, 30308],
            '34110-98' => [329, 30308],
            '34130_11_jzx' => [303, 304],
            '34130_19_dx' => [1, 1],
            '34130_19_exzl' => [1, 1],
            '34130_19_gja' => [1, 1],
            '34130_19_nxgjr' => [1, 1],
            '34130_21_fa' => [303, 306],
            '34130_21_jzx' => [303, 306],
            '34130_21_xn' => [303, 306],
            '34130_30' => [303, 306],
            '34130_39' => [1, 1],
            '34130_sc' => [1, 1],
        ];


        foreach ($data as $item) {
            DataSyncLog::addData(['zyh' => $zyh, 'content' => '8、同步病程记录：' . ($item['BLBH'] ?? '')]);
            //将三院的BLOB类型的数据转化为字符串
            $str = $this->blobToStr($item['HTML_PRINT']);
            if (empty($str)) {
                $HTML_PRINT = !empty($item['HTML_PRINT']) && !empty($item['HTML_PRINT']->size()) ? $item['HTML_PRINT']->load() : '';
                $str = removeHtmlAndHiddenElements($HTML_PRINT);
            }

            $result = [
                'BLBH' => $item['BLBH'] ?? '',
                'JZHM' => $item['JZHM'] ?? '',
                'BLLB' => $item['BLLB'] ?? '',
                'MBLB' => $item['MBLB'] ?? '',
                'BLMC' => $item['BLMC'] ?? '',
                'AAB01' => $sj->AAB01 ?? '',
                'AAC01' => $sj->AAC01 ?? '',
                'HTML_PRINT' => $str,
                'ZXSJ' => $item['ZXSJ'] ?? '',
                'CJSJ' => $item['CJSJ'] ?? '',
                'WCSJ' => $item['WCSJ'] ?? '',
                'SXYS' => $item['SXYS'] ?? '',
                'BLZT' => $item['BLZT'] ?? '',
            ];
            foreach ($bllbMap as $key => $value) {
                if ($item['MBLB'] == $key) {
                    $result['BLLB'] = $value[0];
                    $result['MBLB'] = $value[1];
                }
            }
            $blbh = $item['BLBH'];

            $temp = [
                'HJNR' => $str,
                'BLBH' => $item['BLBH'] ?? ''
            ];

            $bl01Data = $result;
            EMR_BL_BL01::query()->updateOrInsert(['BLBH' => $item['BLBH']], $result); //插入或更新病历数据
            EMR_BL_BLXG::query()->updateOrInsert(['BLBH' => $item['BLBH']], $temp); //插入或更新BLXG数据
            //2025.12.08 zhangjie
            // $sql = "SELECT  JLXH,BLBH,SYYS,to_char(SYSJ,'yyyy-mm-dd hh24:mi:ss') as SYSJTIME,to_char(JLSJ,'yyyy-mm-dd hh24:mi:ss') as JLSJTIME,FG_ACTIVE FROM EMR_BL_BLSY WHERE BLBH = '" . $item['ID_MEDRECDOC'] . "'";
            $sql = "SELECT BLBH,SYYS,to_char(SYSJ,'yyyy-mm-dd hh24:mi:ss') as SYSJTIME,to_char(JLSJ,'yyyy-mm-dd hh24:mi:ss') as JLSJTIME,FG_ACTIVE FROM EMR_BL_BLSY WHERE BLBH = '" . $item['BLBH'] . "'";

            $resultSet = oci_parse(self::$con2, $sql);
            oci_execute($resultSet, OCI_DEFAULT);
            $data = [];
            while ($row = oci_fetch_assoc($resultSet)) {
                $data[] = $row;
            }
            if (!empty($data)) {
                foreach ($data as $v) {
                    $insertData = [
                        'BLBH' => $v['BLBH'],
                        'SYYS' => $v['SYYS'],
                        'SYSJ' => $v['SYSJTIME'],
                        'JLSJ' => $v['JLSJTIME'],
                        'FG_ACTIVE' => $v['FG_ACTIVE']
                    ];
                    EMR_BL_BLSY::query()->updateOrInsert(['BLBH' => $insertData['BLBH'], 'SYYS' => $insertData['SYYS']], $insertData);
                }
            }

            $BLSY = EMR_BL_BLSY::query()->where('BLBH', '=', $blbh)->orderBy('JLSJ', 'asc')->first(['JLSJ']);
            if (!empty($BLSY)) {
                $BLSY = $BLSY->toArray();
                EMR_BL_BL01::query()->where('BLBH', '=', $blbh)->update(['first_blsy_time' => $BLSY['JLSJ']]);
            }

            // 格式化病例名称
            $bl01Data["HJNR"] = $str;
            if ($bl01Data["BLLB"] == 294 || $bl01Data["BLLB"] == 43) {
                $this->formatBL01BLMC294($bl01Data);
            }
            if ($bl01Data["BLLB"] == 303) {
                $this->formatBL01BLMC306($bl01Data);
            }
            if ($bl01Data["BLLB"] == 82) {
                $this->formatBL01BLMC82($bl01Data);
            }
            $bldf->insertData([], $bl01Data["MBLB"], $bl01Data);
        }
        EsSaveService::bl01($zyh);
    }


    public function blobToStr($blob = null)
    {
        $str = '';
        if (!is_object($blob)) {
            return $blob;
        }
        if (!empty($blob)) {
            $text = $blob->load();
            $blob->free();
            $mde = mb_detect_encoding($text, array("ASCII", 'UTF-8', "GB2312", "GBK", 'BIG5'));
            if ($mde) {
                $str = mb_convert_encoding($text, 'utf-8', $mde);
            }
        }

        return $str;
    }


    /**
     * 获取费用信息
     * @param $zyh
     * @return array
     */
    public function getFeeDetailed($zyh)
    {
        // 查询费用数据
        // $sql = "SELECT A.*,to_char(JFRQ,'yyyy-mm-dd hh24:mi:ss') as JFRQ,to_char(DSG_LDR_TIME,'yyyy-mm-dd hh24:mi:ss') as DSG_LDR_TIME
        // FROM PORTAL_HIS.V_JMGS_BASY_FYMX A WHERE A.ZYH = {$zyh}";

        $sj = PatientInfo::query()->where('MED_REC_ID', $zyh)->first(['MED_REC_ID', 'AAB01', 'AAC01', 'PATIENT_ID', 'AAA29']);
        $PATIENT_ID = $sj->PATIENT_ID;
        $visitId = $sj->AAA29;

        $sql = "SELECT ZYH, FYXH, FYMC, to_char(JFRQ,'yyyy-mm-dd hh24:mi:ss') as JFRQ, FYSL, FYDJ, ZJE, FYKS, YBBM, FYGB, FYGB as SYFYGB, YPLX, YSGH, ZXKS, ZYH || '_' || FYXH as JLXH FROM ZYFY WHERE PATIENT_ID = '{$PATIENT_ID}' AND VISIT_ID = '{$visitId}'";
        $data = oci_parse(self::$con2, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * 添加费用信息
     * @param $result
     * @return void
     */
    public function addFeeDetailed($result)
    {
        foreach ($result as $val) {
            $item = [
                'AAA28' => $val['ZYH'], //zyh
                'FYXH' => $val['FYXH'] ?? '', //费用序号
                'FYMC' => $val['FYMC'] ?? '', //费用名称
                'ZFJE' => $val['ZFJE'] ?? '', //自付金额
                'JFRQ' => $val['JFRQ'] ?? '', //计费日期
                'FYSL' => $val['FYSL'] ?? '', //费用数量
                'FYDJ' => $val['FYDJ'] ?? '', //费用单价
                'ZJE' => $val['ZJE'] ?? '', //总金额
                'FYKS' => $val['FYKS'] ?? '', //费用科室
                'FYGB' => $val['FYGB'] ?? '',
                'SYFYGB' => $val['SYFYGB'] ?? '',
                'DSG_LDR_TIME' => $val['DSG_LDR_TIME'] ?? '',
                'DSG_OPERATION' => $val['DSG_OPERATION'] ?? '',
                'JLXH' => $val['JLXH'] ?? '',
                'YZXH' => $val['YZXH'] ?? '', //医嘱序号
            ];
            FeeDetailed::query()->updateOrInsert(['AAA28' => $item['AAA28'], 'JLXH' => $item['JLXH']], $item);
        }
    }
}
