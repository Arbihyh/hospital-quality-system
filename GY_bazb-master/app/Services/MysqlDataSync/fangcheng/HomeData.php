<?php

namespace App\Services\MysqlDataSync\fangcheng;

use App\Model\Yzb;
use Carbon\Carbon;
use App\Model\Bllb1;
use App\Model\Bllb303;
use App\Model\DataSyncLog;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLSY;
use App\Model\EMR_BL_BLXG;
use App\Model\FeeDetailed;
use App\Model\PatientInfo;
use App\Services\EsSaveService;
use App\Model\QualitySendMsgLog;
use Illuminate\Support\Facades\Log;
use App\Services\BlDataFormatService;
use App\Services\SqlServerProxyService;
use App\Console\Commands\ShizhongDataSync;

class HomeData
{

    public static $con;

    public static $sqlsrvService;

    public function getConnect()
    {
        if (self::$sqlsrvService) {
            return;
        }

        // 初始化SQL Server连接
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
        self::$sqlsrvService = $sqlsrvService;
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
            $sql = "SELECT MED_REC_ID FROM PORTAL_HIS.INIT_MED_REC_MAIN WHERE AAB01 BETWEEN TO_DATE('" . $startTimeStr . "000000', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('" . $startTimeStr . "235959', 'yyyy-MM-dd HH24:mi:ss')";

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
            echo date("Y-m-d H:i:s", $startTime) . PHP_EOL;
            $zyh = PatientInfo::query()
                ->where("AAC01", ">=", date("Y-m-d", $startTime))
                ->where("AAC01", "<=", date("Y-m-d", $startTime + 86400))
                ->get(['MED_REC_ID'])->toArray();
            foreach ($zyh as $item) {
                echo $item['MED_REC_ID'] . PHP_EOL;
                $this->getData($item['MED_REC_ID'], [], "");
            }
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

        // 医生签名
        if (empty($type) || in_array('bl01', $type)) {
            $this->addBLSY($zyh, $blbh);
        }
        if (empty($type) || in_array('yzb', $type)) {
            // $yzb = $this->getYzb($zyh);
            // $this->addYzb($yzb);
        }
        return true;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function getBlData($zyh = "", $type = [], $blbh = "", $startTime = "")
    {
        // 如果数据库链接字段为初始化则重新链接
        if (!self::$con) {
            $this->getConnect();
        }
        if ($zyh) {
            $szds = new ShizhongDataSync();
            $szds->ZY_BRRY($zyh, 0, 0, 0);
        }

        // 医嘱
        if (empty($type) || in_array('yzb', $type)) {
            Log::info('qualityHandleV2 yzb 开始' . date('Y-m-d H:i:s'), ['zyh' => $zyh]);
            $yzbData = $this->getYzb($zyh);
            if (!empty($yzbData)) {
                $this->addYzb($yzbData);
            }
            Log::info('qualityHandleV2 yzb 结束' . date('Y-m-d H:i:s'), ['zyh' => $zyh]);
        }

        // 费用
        if (empty($type) || in_array('fee_detailed', $type)) {
            Log::info('qualityHandleV2 fee 开始' . date('Y-m-d H:i:s'), ['zyh' => $zyh]);
            $this->getFyData($zyh);

            Log::info('qualityHandleV2 fee 结束' . date('Y-m-d H:i:s'), ['zyh' => $zyh]);
        }

        // 医生签名
        if (empty($type) || in_array('bl01', $type)) {
            $this->addBLSY($zyh, '');
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
        DataSyncLog::addData(['zyh' => $ZYH, 'content' => '费用同步开始']);
        $sql = "SELECT ZYH, ISNULL(YBBM, '') AS YBBM, ISNULL(FYXH, '') AS FYXH, ISNULL(FYMC, '') AS FYMC, CONVERT(VARCHAR(19), JFRQ, 120) AS JFRQ, ISNULL(FYSL, '') AS FYSL, ISNULL(FYDJ, '') AS FYDJ, ISNULL(ZJE, '') AS ZJE, ISNULL(FYKS, '') AS FYKS, ISNULL(YBBM, '') AS YBBM, ISNULL(FYGB, '') AS FYGB, ISNULL(SYFYGB, '') AS SYFYGB, ISNULL(YPLXDM, '') AS YPLX, ISNULL(YSGH, '') AS YSGH, ISNULL(ZXKS, '') AS ZXKS FROM ZYFY WHERE ZYH='{$ZYH}'";
        $data = self::$sqlsrvService->query($sql);
        if (empty($data)) {
            return false;
        }
        $insertData = [];
        foreach ($data as $val) {
            $insertData[] = [
                'AAA28' => $val['ZYH'], //zyh
                'ZYH' => $val['ZYH'],
                'YBBM' => $val['YBBM'],
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
                'JLXH' => $val['JLXH'] ?? '',
                'ZXKS' => $val['ZXKS'] ?? '',
                'YPLX' => $val['YPLX'] ?? ''
            ];
        }
        DataSyncLog::addData(['zyh' => $ZYH, 'content' => '费用数据同步数量：' . count($insertData)]);

        FeeDetailed::query()->where('AAA28', '=', (string)$ZYH)->delete();
        DataSyncLog::addData(['zyh' => $ZYH, 'content' => '费用数据删除结束']);

        if (!empty($insertData)) {
            $chunkList = array_chunk($insertData, 1000);
            DataSyncLog::addData(['zyh' => $ZYH, 'content' => '费用数据插入开始']);
            foreach ($chunkList as $value) {
                FeeDetailed::query()->insert($value);
            }
        }
        DataSyncLog::addData(['zyh' => $ZYH, 'content' => '费用数据插入结束']);
    }

    /**
     * 医嘱
     * @param $ZYH
     * @return array
     */
    public function getYzb($ZYH)
    {
        DataSyncLog::addData(['zyh' => $ZYH, 'content' => '医嘱同步开始']);
        $sql = "SELECT ZYH,ISNULL(YZBXH, '') AS YZBXH, ISNULL(BRKS, '') AS BRKS, ISNULL(BRBQ, '') AS BRBQ, ISNULL(YDYZLB, '') AS YDYZLB, ISNULL(YZQX, '') AS YZQX, ISNULL(YYSX, '') AS YYSX, ISNULL(KZKSDM, '') AS KZKS, ISNULL(KZYSDM, '') AS KZYS, CONVERT(VARCHAR(19), KZSJ, 120) AS KZSJ, ISNULL(YZMC, '') AS YZMC, ISNULL(SYPC, '') AS SYPC, ISNULL(GYTJ, '') AS GYTJ, ISNULL(YCJL, '') AS YCJL, ISNULL(JLDW, '') AS JLDW, ISNULL(JJYZ, '') AS JJYZ, ISNULL(BLYZ, '') AS BLYZ, CONVERT(VARCHAR(19), TZSJ, 120) AS TZSJ, ISNULL(TZYS, '') AS TZYS, ISNULL(YZZTDM, '') AS YZZT, ISNULL(ZTBZDM, '') AS ZTBZ, ISNULL(XZJD, '') AS XZJDGH, CONVERT(VARCHAR(19), XZJDSJ, 120) AS XZJDSJ, ISNULL(TZQR, '') AS TZQRGH, CONVERT(VARCHAR(19), TZQRSJ, 120) AS TZQRSJ, ISNULL(PSBZDM, '') AS PSBZ FROM dbo.YZB WHERE ZYH='{$ZYH}'";
        $data = self::$sqlsrvService->query($sql);
        DataSyncLog::addData(['zyh' => $ZYH, 'content' => '医嘱同步数量：' . count($data)]);
        return $data;
    }

    public function addYzb($data)
    {
        DataSyncLog::addData(['zyh' => $data[0]['ZYH'], 'content' => '医嘱数据插入开始']);
        $insertData = [];
        foreach ($data as $val) {
            $insertData[] = [
                'ZYH' => $val['ZYH'],
                'YZBXH' => $val['YZBXH'] ?? '',
                'BRKS' => $val['BRKS'] ?? '',
                'BRBQ' => $val['BRBQ'] ?? '',
                'YDYZLB' => $val['YDYZLB'] ?? '',
                'YZQX' => $val['YZQX'] ? ($val['YZQX'] == '长期医嘱' ? 1 : 2) : 0,
                'YYSX' => $val['YYSX'] ?? '',
                'KZKS' => $val['KZKS'] ?? '',
                'KZYS' => $val['KZYS'] ?? '',
                'KZSJ' => $val['KZSJ'] ?? '',
                'YZMC' => $val['YZMC'] ?? '',
                'SYPC' => $val['SYPC'] ?? '',
                'GYTJ' => $val['GYTJ'] ?? '',
                'YCJL' => $val['YCJL'] ?? '',
                'JLDW' => $val['JLDW'] ?? '',
                'JJYZ' => $val['JJYZ'] ?? '',
                'BLYZ' => $val['BLYZ'] ?? '',
                'TZSJ' => $val['TZSJ'] ?? '',
                'TZYS' => $val['TZYS'] ?? '',
                'YZZT' => $val['YZZT'] ?? '',
                'ZTBZ' => $val['ZTBZ'] ?? '',
                'XZJDGH' => $val['XZJDGH'] ?? '',
                'XZJDSJ' => $val['XZJDSJ'] ?? '',
                'TZQRGH' => $val['TZQRGH'] ?? '',
                'TZQRSJ' => $val['TZQRSJ'] ?? null,
                'PSBZ' => $val['PSBZ'] ?? '',
            ];
        }
        $yzb = Yzb::query()->where('ZYH', '=', $data[0]['ZYH'])->get()->toArray();
        $oldYZBXH = array_column($yzb, 'YZBXH');
        $YZBXH = array_column($insertData, 'YZBXH');
        $deleteYZBXH = array_diff($oldYZBXH, $YZBXH);
        if ($deleteYZBXH) {
            Yzb::query()->whereIn('YZBXH', $deleteYZBXH)->delete();
            foreach ($deleteYZBXH as $xh) {
                QualitySendMsgLog::setStatus($data[0]['ZYH'], 109, $xh);
                QualitySendMsgLog::setStatus($data[0]['ZYH'], 110, $xh);
            }
        }

        if (!empty($insertData)) {
            foreach ($insertData as $value) {
                DataSyncLog::addData(['zyh' => $data[0]['ZYH'], 'content' => '医嘱数据插入：' . $value['YZBXH']]);
                Yzb::query()->updateOrInsert(['YZBXH' => (string)$value['YZBXH']], $value);
            }
        }
        DataSyncLog::addData(['zyh' => $data[0]['ZYH'], 'content' => '医嘱数据插入结束']);
    }

    /**
     * @param string $zyh
     * @param string $blbh
     * @return bool
     * 同步病程记录相关数据
     */
    public function addBLSY($zyh = "", $blbh3 = "")
    {
        $sj = PatientInfo::query()->where('MED_REC_ID', $zyh)->first(['MED_REC_ID', 'AAB01', 'AAC01', 'AAA28']);
        DataSyncLog::addData(['zyh' => $zyh, 'content' => '4、数据病程记录开始']);
        $bldf = new BlDataFormatService();
        $sql = "SELECT ISNULL(ZYH, '') AS JZHM, ISNULL(BLBH, '') AS BLBH, ISNULL(BLLB, '') AS BLLB, ISNULL(BLLBMC, '') AS BLLBMC, ISNULL(MBLB, '') AS MBLB, ISNULL(MBLBMC, '') AS MBLBMC, ISNULL(BLMC, '') AS BLMC, ISNULL(CONVERT(VARCHAR(19), ZXSJ, 120), '') AS ZXSJ, ISNULL(CONVERT(VARCHAR(19), CJSJ, 120), '') AS CJSJ, ISNULL(CONVERT(VARCHAR(19), WCSJ, 120), '') AS WCSJ, ISNULL(CONVERT(VARCHAR(19), YWSJ, 120), '') AS YWSJ, ISNULL(SXYSDM, '') AS SXYS, ISNULL(HJNR_HTML, '') AS HJNR_HTML, ISNULL(BAH, '') AS BAH, ISNULL(ZYCS, '') AS ZYCS, ISNULL(BLZTDM, '') AS BLZTDM, ISNULL(BLZT, '') AS BLZT FROM dbo.EMR_BL_BL01 WHERE ZYH='{$zyh}'";

        $data = self::$sqlsrvService->query($sql);

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
            '病案首页' => [2000001, 2000001],
            '首页' => [2000001, 2000001],
            '入出院记录' => [18, 20],
            '入院死亡记录' => [18, 21],
            '出院记录' => [1, 1],
            '入院记录' => [292, 292],
            '手术知情同意书' => [329, 30308],
            '会诊记录' => [294, 32],
            '出院小结' => [294, 512],
            '剖宫产手术记录' => [303, 30301],
            '手术记录' => [303, 306],
            '日常病程' => [294, 296],
            '分娩记录' => [303, 3069999],
            '术后首次病程' => [294, 42],
            '首次病程' => [294, 295],
            '麻醉术前访视记录' => [303, 40],
            '麻醉术后访视记录' => [303, 30303],
            '麻醉记录' => [329, 32977],
            '麻醉同意书' => [329, 77],
            '麻醉知情同意书' => [329, 77],
            '病危（病重）通知书' => [2000185, 200018501],
            '疑难病例讨论记录' => [43, 44],
            '术前小结' => [294, 82],
            '术后' => [294, 54],
            '术前讨论' => [303, 82],
            '出生记录' => [294, 518],
            '出院小结' => [294, 512],
            '查房记录' => [294, 50],
            '手术同意书' => [329, 8],
            '手术安全核查表' => [303, 30375],
            '手术核查表' => [303, 30375],
            '手术风险评估表' => [303, 76],
            '死亡记录' => [288, 288],
            '阶段小结' => [294, 26],
            '输血治疗知情同意书' => [329, 59],
            '输血血液制品治疗知情同意' => [329, 59],
            '异体输血治疗知情同意书' => [329, 59],
            '输血,血液制品治疗知情同意书' => [329, 59],
            '知情同意书' => [329, 32901],
            '授权委托书' => [329, 85],
            '高危妊娠协议书' => [329, 85],
            '病情告知书' => [329, 85],
            '安全核查表' => [303, 75],
            '急诊病历' => [303, 75],
            '抢救记录' => [294, 27],
            '死亡病例讨论结论记录' => [43, 4302],
        ];

        foreach ($data as $item) {
            DataSyncLog::addData(['zyh' => $zyh, 'content' => '8、同步病程记录：' . ($item['BLBH'] ?? '')]);
            $str = $item['HJNR_HTML'];
            // 如果2020后面没有"-"，则清空掉
            $str = preg_replace('/2020(?!-)/', '', $str);
            // 如果$str中出现字符n且前后都没有字符（即孤立的n），则使用正则替换成空
            $str = preg_replace('/(?<![^\s])n(?![^\s])/', '', $str);
            // 如果字母n后面没有内容，或者前面没有内容则使用正则表达式替换成空
            $str = preg_replace('/(^n|n$)/', '', $str); // 开头或结尾的n删掉
            $str = str_replace(['n ', 'n ', '&#x0D;', '&#x20;'], '', $str);
            [$item['BLMC'], $timePrefix] = $this->formatBL01BLMC294(['HJNR' => $str, 'BLMC' => $item['BLMC']]);

            $result = [
                'BLBH' => $item['BLBH'] ?? '',
                'JZHM' => $item['JZHM'] ?? '',
                'BLLB' => $item['BLLB'] ?? '',
                'MBLB' => $item['MBLB'] ?? '',
                'BLMC' => $item['BLMC'] ?? '',
                'AAB01' => $sj->AAB01 ?? '',
                'AAC01' => $sj->AAC01 ?? '',
                // 'HTML_PRINT' => $str,
                'ZXSJ' => $timePrefix ? $timePrefix : ($item['ZXSJ'] ?? ''),
                'CJSJ' => $item['CJSJ'] ?? '',
                'WCSJ' => $timePrefix ? $timePrefix : ($item['WCSJ'] ?? ''),
                'SXYS' => $item['SXYS'] ?? '',
                'BLZT' => $item['BLZT'] ?? '',
            ];
            foreach ($bllbMap as $key => $value) {
                if (strpos($item['BLMC'], $key) !== false) {
                    $result['BLLB'] = $value[0];
                    $result['MBLB'] = $value[1];
                    break;
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


            $sql = "SELECT ISNULL(BLBH, '') AS BLBH,ISNULL(SYYS, '') AS SYYS,ISNULL(SYYSDM, '') AS SYYSDM,CONVERT(VARCHAR(19), SYSJ, 120) as SYSJ,CONVERT(VARCHAR(19), JLSJ, 120) as JLSJ,ISNULL(FG_ACTIVE, 1) as FG_ACTIVE FROM  dbo.EMR_BL_BLSY WHERE BLBH='{$blbh}'";
            $data = self::$sqlsrvService->query($sql);
            if (!empty($data)) {
                foreach ($data as $v) {
                    $insertData = [
                        'BLBH' => $v['BLBH'],
                        'SYYS' => $v['SYYSDM'],
                        'SYSJ' => $v['SYSJ'],
                        'JLSJ' => $v['JLSJ'],
                        'FG_ACTIVE' => $v['FG_ACTIVE'] ?? 1
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

    /**
     * @param array $bl01
     * 格式化bl01表中的病例名称
     */
    public function formatBL01BLMC294($bl01 = [])
    {
        $blmc = $bl01['BLMC'];
        $timePrefix = '';
        if (!empty($bl01['HJNR'])) {
            preg_match("/日期时间\(分\)\s*(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", $bl01['HJNR'], $timeMatches5);
            if (!empty($timeMatches5[1])) {
                $timePrefix = $timeMatches5[1];
            }
            if (preg_match('/FC(\d+)\s*(.+)$/u', $bl01['HJNR'], $matches) && strpos($bl01['BLMC'], '首次病程记录') === false) {
                if (mb_strlen($matches[2]) < 20) {
                    $blmc = $matches[2];
                }
            }
            if ($timePrefix != '') {
                $blmc = $timePrefix . ' ' . $blmc;
            }
        }
        return [$blmc, $timePrefix];
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
     * @param $str string 要操作的原始字符串
     * @param $delimiter string 用于分割字符串的分隔符
     * @param $count int 一个整数，如果为正数，则表示从左边开始计数，获取第 $count 个分隔符左边的所有内容；如果为负数，则表示从右边开始计数，获取第 count 个分隔符右边的所有内容
     * @return string
     */
    public static function substringIndex($str, $delimiter, $count)
    {
        $parts = explode($delimiter, $str);
        return implode($delimiter, array_slice($parts, 0, $count));
    }
}
