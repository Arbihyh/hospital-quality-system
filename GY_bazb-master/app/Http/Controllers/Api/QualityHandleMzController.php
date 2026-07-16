<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\OMR_BL01;
use App\Model\OmrBlsy;
use App\Model\OmrQuality;
use App\Model\YS_MZ_JZLS;
use App\Model\MS_BRDA;
use App\Model\RuleWordMap;
use App\Model\SJKCONN;
use App\Model\RuleSetting;
use App\Model\OmrRule;
use App\Model\Department;
use App\Model\Staff;
use App\Services\ToolsService;
use App\Services\MzBlDataFormatService;
use App\Services\SqlServerProxyService;
use App\Console\Commands\OmrQualityCommand;
use App\Model\MZFK;
use App\Model\OmrDepartment;
use App\Model\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ExportData;

/**
 * 门诊病历质控处理接口
 */
class QualityHandleMzController extends Controller
{
    // 数据库类型常量
    const ORACLE = 'ORACLE';
    const SQL_SERVER = 'SQL_SERVER';
    const MYSQL = 'MYSQL';

    // 门诊数据库连接ID
    const MZ_SJK_ID = 10;

    // 数据库连接信息
    private $dbConfig = null;
    private $dbType = null;
    /**
     * 门诊病历质控处理接口
     * @param Request $request
     * @return array
     */
    public function qualityHandleMz(Request $request)
    {
        try {
            // 获取请求参数（同时支持 GET 和 POST）
            $MZID = $request->input('MZID', '');      // 门（急）诊代码，先用blbh替代
            $BLBH = $request->input('BLBH', '');      // 病历编号
            $BLZT = $request->input('BLZT', '');      // 病历状态：1.完成；2.封存(归档)；3.草稿；9.删除
            $MZHM = $request->input('MZHM', '');      // 门诊号码
            $JZXH = $request->input('JZXH', '');      // 就诊序号
            $BRBH = $request->input('BRBH', '');      // 病人编号
            $BRXM = $request->input('BRXM', '');      // 病人姓名
            $ISMZ = $request->input('ISMZ', '0');     // 是否直接质控（1=是，0=否）

            Log::info('门诊病历质控请求', [
                'MZID' => $MZID,
                'BLBH' => $BLBH,
                'BLZT' => $BLZT,
                'MZHM' => $MZHM,
                'JZXH' => $JZXH,
                'BRBH' => $BRBH,
                'BRXM' => $BRXM,
                'ISZM' => $ISMZ
            ]);

            // 参数验证
            if (empty($BLBH)) {
                return ToolsService::returnData(4001, [], '病历编号不能为空');
            }

            // 如果病历状态是9（删除），则删除相关数据
            if ($BLZT == '9') {
                $this->deleteOmrData($BLBH);
                return ToolsService::returnData(200, [
                    'MZID' => $MZID,
                    'req' => 0,
                    'sftc' => 0
                ], '删除成功');
            }

            // 数据同步（如果 ISZM != 1，则执行数据同步）
            if ($ISMZ != '1') {
                $this->syncOmrData($BLBH, $JZXH, $BRBH, $BRXM, $MZHM);
            }

            // 数据格式化
            /* $formatService = new MzBlDataFormatService();
            $formatService->formatBlData($BLBH); */

            // 执行质控
            $qualityCommand = new OmrQualityCommand();
            $qualityCommand->omrZk(1, '', '', $BLBH);

            // 查询质控结果
            $omrBl01 = OMR_BL01::query()->where('BLBH', $BLBH)->first();
            if (empty($omrBl01)) {
                return ToolsService::returnData(4001, [], '病历不存在');
            }

            // 查询必改问题数量
            // 规则：
            // - rule_id < 1000000: 查询 omr_rule 表，error_level=1 为强制
            // - rule_id >= 1000000: 查询 rule_setting 表（需要 rule_id - 1000000），error_level=1 为强制
            $reqCount = 0;

            // 统计 omr_rule 表中的强制问题（rule_id < 1000000）
            $omrRuleCount = OmrQuality::query()
                ->where('BLBH', $BLBH)
                ->leftJoin('omr_rule', 'omr_quality.rule_id', '=', 'omr_rule.id')
                ->where('omr_rule.status', 1)
                ->where('omr_rule.error_level', 1)
                ->count();

            // 统计 rule_setting 表中的强制问题（rule_id >= 1000000）
            $ruleSettingCount = OmrQuality::query()
                ->where('BLBH', $BLBH)
                ->leftJoin('rule_setting', function ($join) {
                    $join->on(DB::raw('omr_quality.rule_id'), '=', DB::raw('rule_setting.id + 1000000'));
                })
                ->where('rule_setting.status', 1)
                ->where('rule_setting.error_level', 1)
                ->count();

            $reqCount = $omrRuleCount + $ruleSettingCount;

            // 判断是否弹窗：得分不是100分则弹窗
            $sftc = ($omrBl01->score < 100) ? 1 : 0;

            Log::info('门诊病历质控完成', [
                'BLBH' => $BLBH,
                'score' => $omrBl01->score,
                'reqCount' => $reqCount,
                'sftc' => $sftc
            ]);

            return ToolsService::returnData(200, [
                'MZID' => $MZID,
                'req' => $reqCount,
                'sftc' => $sftc
            ], '质控完成');
        } catch (\Exception $e) {
            Log::error('门诊病历质控处理异常：' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return ToolsService::returnData(500, [], '质控处理异常：' . $e->getMessage());
        }
    }

    /**
     * 删除病历相关数据
     * @param string $BLBH
     */
    private function deleteOmrData($BLBH)
    {
        try {
            // 删除数据库中的数据
            OMR_BL01::query()->where('BLBH', $BLBH)->delete();
            OmrQuality::query()->where('BLBH', $BLBH)->delete();

            // 删除ES中的数据
            $year = '2023';
            $esIndexes = [
                "omr_bl01_{$year}",
                "omr_quality_{$year}"
            ];

            foreach ($esIndexes as $index) {
                try {
                    $params = [
                        'index' => $index,
                        'body' => [
                            'query' => [
                                'term' => [
                                    'BLBH' => $BLBH
                                ]
                            ]
                        ]
                    ];

                    // 先检查数据是否存在
                    $result = app('es')->search($params);
                    if (!empty($result['hits']['hits'])) {
                        app('es')->deleteByQuery($params);
                    }
                } catch (\Exception $e) {
                    Log::warning("删除ES索引 {$index} 数据失败：" . $e->getMessage());
                }
            }

            Log::info("删除病历数据成功：BLBH={$BLBH}");
        } catch (\Exception $e) {
            Log::error("删除病历数据失败：" . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 同步门诊病历数据
     * @param string $BLBH 病历编号
     * @param string $JZXH 就诊序号
     * @param string $BRBH 病人编号
     * @param string $BRXM 病人姓名
     * @param string $MZHM 门诊号码
     */
    private function syncOmrData($BLBH, $JZXH, $BRBH, $BRXM, $MZHM)
    {
        try {
            // 1. 同步 omr_bl01 数据（根据 BLBH）
            $JZXH = $this->syncOmrBl01($BLBH);

            // 2. 同步 omr_blsy 数据（根据 BLBH，可能多条签名记录）
            $this->syncOmrBlsy($BLBH);

            // 3. 同步 jzls 数据（根据 JZXH）
            Log::info("同步 jzls 数据：JZXH={$JZXH}");
            if (!empty($JZXH)) {
                $this->syncJzls($JZXH);
            }

            // 4. 同步 brda 数据（根据 BRID）
            if (!empty($BRBH)) {
                $this->syncBrda($BRBH, $BRXM, $MZHM);
            }

            Log::info("数据同步完成：BLBH={$BLBH}");
        } catch (\Exception $e) {
            Log::error("数据同步失败：" . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 同步 omr_bl01 数据（根据 BLBH 唯一标识）
     */
    private function syncOmrBl01($BLBH)
    {
        // 从 rulewordmap 读取 SQL
        $sqlConfig = RuleWordMap::query()->where('name', 'omr_bl01_sql')->value('keyword');
        if (empty($sqlConfig)) {
            Log::warning("未找到 omr_bl01_sql 配置");
            return;
        }

        // 替换SQL中的参数（只需要 BLBH）
        $sql = str_replace(':BLBH', $BLBH, $sqlConfig);

        // 执行SQL并更新数据
        $result = $this->executeQuery($sql);
        if (!empty($result)) {
            $data = $result[0];
            OMR_BL01::query()->updateOrInsert(['BLBH' => $BLBH], $data);
            return $data['JZXH'];
        }
    }

    /**
     * 同步 omr_blsy 数据（根据 BLBH，可能有多条签名记录）
     * 先删除旧记录，再批量插入新记录
     */
    private function syncOmrBlsy($BLBH)
    {
        $sqlConfig = RuleWordMap::query()->where('name', 'omr_blsy_sql')->value('keyword');
        if (empty($sqlConfig)) {
            return;
        }

        $sql = str_replace(':BLBH', $BLBH, $sqlConfig);
        $result = $this->executeQuery($sql);

        if (!empty($result)) {
            // 先删除旧的签名记录
            OmrBlsy::query()->where('BLBH', $BLBH)->delete();

            // 批量插入所有签名记录
            if (!empty($result)) {
                OmrBlsy::query()->insert($result);
            }
        }
    }

    /**
     * 同步 jzls 数据（根据 JZXH 唯一标识）
     */
    private function syncJzls($JZXH)
    {
        $sqlConfig = RuleWordMap::query()->where('name', 'jzls_sql')->value('keyword');
        if (empty($sqlConfig)) {
            return;
        }

        $sql = str_replace(':JZXH', $JZXH, $sqlConfig);
        //Log::info("同步 jzls 数据：sql={$sql}");
        $result = $this->executeQuery($sql);
        //Log::info("同步 jzls 数据：result={$result}");
        if (!empty($result)) {
            $data = $result[0];
            YS_MZ_JZLS::query()->updateOrInsert(['JZXH' => $JZXH], $data);
        }
    }

    /**
     * 同步 brda 数据（根据 BRID 唯一标识）
     * 直接使用传入的参数更新或插入
     * @param string $BRBH 病人编号
     * @param string $BRXM 病人姓名
     * @param string $MZHM 门诊号码
     */
    private function syncBrda($BRBH, $BRXM, $MZHM)
    {
        try {
            // 直接使用传入的参数更新或插入 BRDA 表
            $data = [
                'BRID' => $BRBH,
                'BRXM' => $BRXM,
                'MZHM' => $MZHM
            ];

            MS_BRDA::query()->updateOrInsert(['BRID' => $BRBH], $data);

            Log::info("同步 BRDA 数据成功", $data);
        } catch (\Exception $e) {
            Log::error("同步 BRDA 数据失败：" . $e->getMessage());
            // 不抛出异常，避免影响主流程
        }
    }

    /**
     * 获取数据库连接配置
     * @return bool
     */
    private function initDbConnection()
    {
        if ($this->dbConfig !== null) {
            return true;
        }

        try {
            // 从 SJK_CONN 表读取配置
            $this->dbConfig = SJKCONN::getBySjkId(self::MZ_SJK_ID);

            if (empty($this->dbConfig)) {
                Log::error("未找到 SJK_ID=" . self::MZ_SJK_ID . " 的数据库连接配置");
                return false;
            }

            $this->dbType = strtoupper($this->dbConfig['SJKLX']);

            Log::info("门诊数据库连接配置", [
                'type' => $this->dbType,
                'host' => $this->dbConfig['host'],
                'port' => $this->dbConfig['port'],
                'database' => $this->dbConfig['database']
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("初始化数据库连接配置失败：" . $e->getMessage());
            return false;
        }
    }

    /**
     * 执行数据库查询
     * 支持 Oracle、SQL Server 和 MySQL
     * @param string $sql
     * @return array
     */
    private function executeQuery($sql)
    {
        try {
            if (!$this->initDbConnection()) {
                throw new \Exception('数据库连接配置初始化失败');
            }

            switch ($this->dbType) {
                case self::ORACLE:
                    return $this->executeOracleQuery($sql);

                case self::SQL_SERVER:
                    return $this->executeSqlServerQuery($sql);

                case self::MYSQL:
                    return $this->executeMysqlQuery($sql);

                default:
                    throw new \Exception('不支持的数据库类型：' . $this->dbType);
            }
        } catch (\Exception $e) {
            Log::error("执行SQL失败：" . $e->getMessage(), ['sql' => $sql]);
            return [];
        }
    }

    /**
     * 执行 Oracle 查询
     * @param string $sql
     * @return array
     */
    private function executeOracleQuery($sql)
    {
        $config = $this->dbConfig;
        $con = @oci_connect(
            $config['username'],
            $config['password'],
            $config['host'] . ':' . $config['port'] . '/' . $config['database'],
            'UTF8'
        );

        if (!$con) {
            $e = oci_error();
            throw new \Exception('Oracle连接失败：' . ($e['message'] ?? '未知错误'));
        }

        $stmt = @oci_parse($con, $sql);
        if (!$stmt) {
            $e = oci_error($con);
            oci_close($con);
            throw new \Exception('SQL解析失败：' . ($e['message'] ?? '未知错误'));
        }

        $success = @oci_execute($stmt, OCI_DEFAULT);
        if (!$success) {
            $e = oci_error($stmt);
            oci_free_statement($stmt);
            oci_close($con);
            throw new \Exception('SQL执行失败：' . ($e['message'] ?? '未知错误'));
        }

        $data = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $data[] = $row;
        }

        oci_free_statement($stmt);
        oci_close($con);

        return $data;
    }

    /**
     * 执行 SQL Server 查询
     * @param string $sql
     * @return array
     */
    private function executeSqlServerQuery($sql)
    {
        $config = $this->dbConfig;

        // 使用 SqlServerProxyService 创建连接
        $sqlsrvConfig = [
            'host' => $config['host'],
            'port' => $config['port'] ?? '1433',
            'database' => $config['database'],
            'username' => $config['username'],
            'password' => $config['password']
        ];

        Log::info('SQL Server 连接配置', ['config' => $sqlsrvConfig]);

        $sqlsrvService = SqlServerProxyService::withConfig($sqlsrvConfig);

        // 测试 SQL Server 连接
        if (!$sqlsrvService->testConnection()) {
            throw new \Exception('SQL Server 数据库连接失败');
        }

        // 执行查询
        $result = $sqlsrvService->query($sql);

        if ($result === false) {
            throw new \Exception('SQL Server 查询失败');
        }

        return $result;
    }

    /**
     * 执行 MySQL 查询
     * @param string $sql
     * @return array
     */
    private function executeMysqlQuery($sql)
    {
        $config = $this->dbConfig;
        $port = $config['port'] ?? 3306;
        $dsn = "mysql:host={$config['host']};port={$port};dbname={$config['database']};charset=utf8mb4";

        try {
            $pdo = new \PDO($dsn, $config['username'], $config['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);

            $stmt = $pdo->query($sql);
            $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $data;
        } catch (\PDOException $e) {
            throw new \Exception('MySQL查询失败：' . $e->getMessage());
        }
    }

    /**
     * 门诊反馈接口
     * @param Request $request
     * @return array
     */
    public function mzFeedback(Request $request)
    {
        try {
            // 获取请求参数
            $blbh = $request->post('blbh', '');
            $ruleId = $request->post('rule_id', '');

            // 参数验证
            if (empty($blbh)) {
                return ToolsService::returnData(400, [], '病历编号不能为空');
            }
            if (empty($ruleId)) {
                return ToolsService::returnData(400, [], '缺陷ID不能为空');
            }

            // 查询病历数据
            $omrBl01 = OMR_BL01::query()->where('BLBH', $blbh)->first();
            if (empty($omrBl01)) {
                return ToolsService::returnData(404, [], '病历不存在');
            }

            // 获取当前时间
            $fssj = date('Y-m-d H:i:s');

            // 获取当前登录用户的 realname
            $fsr = '';
            $token = $request->header('token');
            if (!empty($token)) {
                // 优先尝试从 request->user() 获取（通过 CheckLogin 中间件设置）
                $userInfo = $request->user();

                // 如果 request->user() 为空，尝试从 Session 获取
                if (empty($userInfo)) {
                    $userInfo = Session::get($token);
                }

                // 如果 Session 中也没有，尝试从数据库查询（通过 token）
                if (empty($userInfo)) {
                    $userInfo = User::findWhereToken($token);
                }

                if (!empty($userInfo) && is_array($userInfo)) {
                    $fsr = $userInfo['realname'] ?? '';
                }
            }


            // 查询就诊流水获取就诊时间
            $jzsj = $omrBl01->jzsj ?? '';
            if (empty($jzsj)) {
                if (!empty($omrBl01->JZXH)) {
                    $jzls = YS_MZ_JZLS::query()
                        ->where('JZXH', $omrBl01->JZXH)
                        ->orderBy('KSSJ', 'desc')
                        ->first();
                    if (!empty($jzls)) {
                        $jzsj = $jzls->KSSJ ?? '';
                    }
                }
            }

            // 准备基础数据
            $baseData = [
                'status' => '0',  // 默认状态为待整改
                'jzsj' => $jzsj,
                'ks' => $omrBl01->BRKS ?? '',  // 科室code
                'ys' => $omrBl01->SXYS ?? '',  // 医师code
                'mzh' => $omrBl01->mzh ?? '',
                'xm' => $omrBl01->xm ?? '',
                'xb' => $omrBl01->xb ?? '',
                'nl' => $omrBl01->nl ?? '',
                'fssj' => $fssj,
                'fsr' => $fsr,
            ];

            // 检查rule_id是否包含英文逗号，如果包含则分割为多个
            $ruleIds = [];
            if (strpos($ruleId, ',') !== false) {
                // 包含英文逗号，分割为数组
                $ruleIds = explode(',', $ruleId);
                // 去除空格
                $ruleIds = array_map('trim', $ruleIds);
                // 去除空值
                $ruleIds = array_filter($ruleIds);
            } else {
                // 单个rule_id
                $ruleIds = [$ruleId];
            }

            // 批量插入或更新
            $successCount = 0;
            foreach ($ruleIds as $singleRuleId) {
                $data = array_merge($baseData, ['blbh' => $blbh, 'rule_id' => $singleRuleId]);
                
                // 使用 blbh 和 rule_id 作为唯一标识，更新或插入
                $result = MZFK::query()->updateOrInsert(
                    ['blbh' => $blbh, 'rule_id' => $singleRuleId],
                    $data
                );
                
                if ($result) {
                    $successCount++;
                }
            }

            if ($successCount > 0) {
                return ToolsService::returnData(200, [], '反馈提交成功，共提交' . $successCount . '条');
            } else {
                return ToolsService::returnData(500, [], '反馈提交失败');
            }
        } catch (\Exception $e) {
            Log::error('门诊反馈接口异常', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return ToolsService::returnData(500, [], '系统异常：' . $e->getMessage());
        }
    }

    /**
     * 门诊反馈列表接口
     * @param Request $request
     * @return array
     */
    public function mzFeedbackList(Request $request)
    {
        try {
            // 获取请求参数
            $startTime = $request->input('start_time', '');      // 发送时间开始（格式：20260101）
            $endTime = $request->input('end_time', '');          // 发送时间结束（格式：20260101）
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 10);
            $depId = $request->input('dep_id', '');              // 科室ID
            $doctorId = $request->input('doctor_id', '');        // 医师ID
            $jzsjStart = $request->input('jzsj_start', '');      // 就诊时间开始
            $jzsjEnd = $request->input('jzsj_end', '');          // 就诊时间结束
            $mzh = $request->input('mzh', '');                   // 门诊号
            $rectifyStatus = $request->input('rectifyStatus', ''); // 整改状态：0/1，不传=查全部
            $isExport = $request->input('is_export', 0);         // 是否导出：1=导出，0=不导出

            // 如果没有传入时间范围，默认为当前月
            if (empty($startTime) && empty($endTime)) {
                $startTime = date('Ym01');  // 当月第一天
                $endTime = date('Ymd');     // 今天
            }

            // 转换时间格式为 Y-m-d H:i:s
            if (!empty($startTime)) {
                $startTime = date('Y-m-d 00:00:00', strtotime($startTime));
            }
            if (!empty($endTime)) {
                $endTime = date('Y-m-d 23:59:59', strtotime($endTime));
            }

            // 构建查询
            $query = MZFK::query();

            // 发送时间范围
            if (!empty($startTime)) {
                $query->where('fssj', '>=', $startTime);
            }
            if (!empty($endTime)) {
                $query->where('fssj', '<=', $endTime);
            }

            // 科室筛选
            if (!empty($depId)) {
                $query->where('ks', $depId);
            }

            // 医师筛选
            if (!empty($doctorId)) {
                $query->where('ys', $doctorId);
            }

            // 就诊时间范围
            if (!empty($jzsjStart)) {
                $jzsjStart = date('Y-m-d 00:00:00', strtotime($jzsjStart));
                $query->where('jzsj', '>=', $jzsjStart);
            }
            if (!empty($jzsjEnd)) {
                $jzsjEnd = date('Y-m-d 23:59:59', strtotime($jzsjEnd));
                $query->where('jzsj', '<=', $jzsjEnd);
            }

            // 门诊号筛选
            if (!empty($mzh)) {
                $query->where('mzh', 'like', '%' . $mzh . '%');
            }

            // 整改状态筛选：兼容前端传字符串或数字
            $rectifyStatus = $rectifyStatus === null ? '' : (string)$rectifyStatus;
            if ($rectifyStatus === '0' || $rectifyStatus === '1') {
                $query->where('status', $rectifyStatus);
            }

            // 如果是导出，查询全部数据（不分页）
            if ($isExport == 1) {
                $list = $query->orderBy('fssj', 'desc')->get()->toArray();
            } else {
                // 统计总数
                $total = $query->count();

                // 分页查询，按发送时间倒序
                $list = $query->orderBy('fssj', 'desc')
                    ->offset(($page - 1) * $pageSize)
                    ->limit($pageSize)
                    ->get()
                    ->toArray();
            }

            // 查询科室和医师名称
            $depIds = array_unique(array_column($list, 'ks'));
            $doctorIds = array_unique(array_column($list, 'ys'));
            $ruleIds = array_unique(array_column($list, 'rule_id'));

            // 批量查询科室名称
            $depMap = [];
            if (!empty($depIds)) {
                $departments = OmrDepartment::query()
                    ->whereIn('dep_id', $depIds)
                    ->get(['dep_id', 'dep_name'])
                    ->toArray();
                $depMap = array_column($departments, 'dep_name', 'dep_id');
            }

            // 批量查询医师名称
            $doctorMap = [];
            if (!empty($doctorIds)) {
                $doctors = Staff::query()
                    ->whereIn('code', $doctorIds)
                    ->get(['code', 'name'])
                    ->toArray();
                $doctorMap = array_column($doctors, 'name', 'code');
            }

            // 批量查询规则描述
            $ruleMap = [];
            if (!empty($ruleIds)) {
                // 分离普通规则和自定义规则
                $normalRuleIds = [];
                $customRuleIds = [];
                foreach ($ruleIds as $ruleId) {
                    if ($ruleId < 1000000) {
                        $normalRuleIds[] = $ruleId;
                    } else {
                        $customRuleIds[] = $ruleId - 1000000;
                    }
                }

                // 查询普通规则
                if (!empty($normalRuleIds)) {
                    $omrRules = OmrRule::query()
                        ->whereIn('id', $normalRuleIds)
                        ->get(['id', 'notice'])
                        ->toArray();
                    foreach ($omrRules as $rule) {
                        $ruleMap[$rule['id']] = $rule['notice'];
                    }
                }

                // 查询自定义规则
                if (!empty($customRuleIds)) {
                    $ruleSettings = RuleSetting::query()
                        ->whereIn('id', $customRuleIds)
                        ->get(['id', 'description'])
                        ->toArray();
                    foreach ($ruleSettings as $rule) {
                        $ruleMap[$rule['id'] + 1000000] = $rule['description'];
                    }
                }
            }

            // 整理返回数据
            foreach ($list as &$item) {
                // 整改状态转换
                $statusMap = [
                    '0' => '待整改',
                    '1' => '已整改',
                ];
                $item['status_text'] = $statusMap[$item['status']] ?? '未知';

                // 科室名称
                $item['ks_name'] = $depMap[$item['ks']] ?? '';

                // 医师名称
                $item['ys_name'] = $doctorMap[$item['ys']] ?? '';

                // 规则描述
                $item['rule_description'] = $ruleMap[$item['rule_id']] ?? '';
            }

            // 如果是导出，调用导出方法
            if ($isExport == 1) {
                return $this->exportMzFeedbackList($list);
            }

            return ToolsService::returnData(200, [
                'list' => $list,
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
            ], '查询成功');
        } catch (\Exception $e) {
            Log::error('门诊反馈列表接口异常', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return ToolsService::returnData(500, [], '系统异常：' . $e->getMessage());
        }
    }

    /**
     * 导出门诊反馈列表
     * @param array $data
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    private function exportMzFeedbackList($data)
    {
        $title = ['整改状态', '缺陷描述', '就诊时间', '患者姓名', '接诊医师', '门诊号', '性别', '年龄', '发送时间', '发送人'];
        $exportData = [];

        foreach ($data as $item) {
            $exportData[] = [
                $item['status_text'] ?? '',
                $item['rule_description'] ?? '',
                $item['jzsj'] ?? '',
                $item['xm'] ?? '',
                $item['ys_name'] ?? '',
                $item['mzh'] ?? '',
                $item['xb'] ?? '',
                $item['nl'] ?? '',
                $item['fssj'] ?? '',
                $item['fsr'] ?? '',
            ];
        }

        $fileName = '门诊反馈列表_' . date('YmdHis') . '.xlsx';
        return Excel::download(new ExportData($title, $exportData), $fileName);
    }
}
