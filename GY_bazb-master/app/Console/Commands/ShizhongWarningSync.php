<?php

namespace App\Console\Commands;

use App\Model\CaseRule;
use App\Model\CaseQuality;
use App\Model\Department;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLSY;
use App\Model\EMR_BL_BLXG;
use App\Model\ErrorV2;
use App\Model\MedicinalInfo;
use App\Model\PACS;
use App\Model\PatientInfo;
use App\Model\PatientInfoV2;
use App\Model\QualitySendMsgLog;
use App\Model\RuleWordMap;
use App\Model\Setting;
use App\Model\ShizhongWarningConfig;
use App\Model\ShizhongSyncZyh;
use App\Model\Staff;
use App\Model\User;
use App\Model\WJZ;
use App\Model\Yzb;
use App\Model\ZY_BRRY;
use App\Model\ZY_HCMX;
use App\Model\ZY_RYZD;
use App\Model\ZY_SS;
use App\Services\CaseService;
use App\Services\ElasticsearchService;
use App\Services\EsSaveService;
use App\Services\SqlServerProxyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ShizhongWarningSync extends Command
{
    private const BRRY_SQL_CONFIG_KEY = 'brry_sql';
    private const ADMISSION_TIME_FIELD_CONFIG_KEY = 'admission_time_field';
    private const DISCHARGE_TIME_FIELD_CONFIG_KEY = 'discharge_time_field';

    private const SCENES = [
        'admission_yesterday' => [
            'name' => '入院前一天0点至今及在院患者',
            'field_config_key' => self::ADMISSION_TIME_FIELD_CONFIG_KEY,
            'type' => 'yesterday_to_now',
            'include_in_hospital' => true,
        ],
        'in_hospital_cleanup' => [
            'name' => '在院患者医院库存在性核查',
            'type' => 'in_hospital_cleanup',
        ],
        'admission_8' => [
            'name' => '入院后6至8小时预警',
            'field_config_key' => self::ADMISSION_TIME_FIELD_CONFIG_KEY,
            'elapsed_start_hours' => 6,
            'elapsed_end_hours' => 8,
            'quality_rules' => ['admission_first_course_8h'],
        ],
        'admission_24' => [
            'name' => '入院后22至24小时预警',
            'field_config_key' => self::ADMISSION_TIME_FIELD_CONFIG_KEY,
            'elapsed_start_hours' => 22,
            'elapsed_end_hours' => 24,
            'quality_rules' => ['admission_record_or_24h'],
        ],
        'admission_48' => [
            'name' => '入院后46至48小时预警',
            'field_config_key' => self::ADMISSION_TIME_FIELD_CONFIG_KEY,
            'elapsed_start_hours' => 46,
            'elapsed_end_hours' => 48,
            'quality_rules' => ['admission_superior_first_round_48h'],
        ],
        'discharge_24' => [
            'name' => '出院后22至24小时预警',
            'field_config_key' => self::DISCHARGE_TIME_FIELD_CONFIG_KEY,
            'elapsed_start_hours' => 22,
            'elapsed_end_hours' => 24,
            'quality_rules' => [
                'discharge_record_24h',
                'discharge_home_page_24h',
                'death_record_24h',
                'discharge_24h_admission_discharge_record',
                'death_24h_admission_death_record',
            ],
        ],
        'discharge_superior_round_day' => [
            'name' => '出院前一日或当日上级医师查房预警',
            'field_config_key' => self::DISCHARGE_TIME_FIELD_CONFIG_KEY,
            'type' => 'today_to_now',
            'quality_rules' => ['discharge_superior_round_day'],
        ],
        'discharge_168' => [
            'name' => '出院后166至168小时预警',
            'field_config_key' => self::DISCHARGE_TIME_FIELD_CONFIG_KEY,
            'elapsed_start_hours' => 166,
            'elapsed_end_hours' => 168,
            'quality_rules' => ['death_home_page_168h'],
        ],
        'critical_value_24' => [
            'name' => '在院危急值接收后22至24小时预警',
            'type' => 'critical_value_24',
            'quality_rules' => ['critical_value_record_24h'],
        ],
        'transfusion_24' => [
            'name' => '在院输血结束后22至24小时预警',
            'type' => 'transfusion_24',
            'quality_rules' => ['transfusion_record_24h'],
        ],
        'superior_round_cycle' => [
            'name' => '在院患者上级医师周期查房预警',
            'type' => 'superior_round_cycle',
            'quality_rules' => [
                'critically_ill_superior_round_daily',
                'seriously_ill_superior_round_2d',
                'stable_round_3d',
            ],
        ],
        'stage_summary_30d' => [
            'name' => '在院患者阶段小结30天周期预警',
            'type' => 'stage_summary_30d',
            'quality_rules' => ['stage_summary_record_30d'],
        ],
        'transfer_in_24' => [
            'name' => '转入后22至24小时转入记录预警',
            'type' => 'transfer_in_24',
            'quality_rules' => ['transfer_in_record_24h'],
        ],
        'ct_progress_72' => [
            'name' => 'CT报告后70至72小时病程记录预警',
            'type' => 'ct_progress_72',
            'quality_rules' => ['ct_progress_record_72h'],
        ],
        'mr_progress_72' => [
            'name' => 'MR报告后70至72小时病程记录预警',
            'type' => 'mr_progress_72',
            'quality_rules' => ['mr_progress_record_72h'],
        ],
        'antibiotic_progress_72' => [
            'name' => '抗菌药物开嘱后70至72小时病程记录预警',
            'type' => 'antibiotic_progress_72',
            'quality_rules' => ['antibiotic_progress_record_72h'],
        ],
        'chemo_progress_72' => [
            'name' => '化疗药物开嘱后70至72小时病程记录预警',
            'type' => 'chemo_progress_72',
            'quality_rules' => ['chemo_progress_record_72h'],
        ],
        'death_discussion_168' => [
            'name' => '死亡后166至168小时死亡病例讨论结论记录预警',
            'type' => 'death_discussion_168',
            'quality_rules' => ['death_discussion_conclusion_168h'],
        ],
        'pending_warnings' => [
            'name' => '未整改预警复查',
            'type' => 'pending_warnings',
        ],
    ];

    /**
     * MySQL连接实例。
     *
     * @var \PDO|null
     */
    private $mysqlConnection;

    /**
     * 当前场景同步统计。
     *
     * @var array
     */
    private $syncStats = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:shizhong-warning-sync
        {scene=admission_yesterday : admission_yesterday/in_hospital_cleanup/admission_8/admission_24/admission_48/discharge_24/discharge_superior_round_day/discharge_168/critical_value_24/transfusion_24/superior_round_cycle/stage_summary_30d/transfer_in_24/ct_progress_72/mr_progress_72/antibiotic_progress_72/chemo_progress_72/death_discussion_168/pending_warnings/all}
        {--no-quality : 仅同步BRRY和PatientInfo，不执行质控规则}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '按入院/出院预警时间段同步事中质控相关病例数据';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $scene = (string)$this->argument('scene');
        $withQuality = !$this->option('no-quality');

        if ($scene === 'all') {
            $scenes = array_keys(self::SCENES);
        } else if (isset(self::SCENES[$scene])) {
            $scenes = [$scene];
        } else {
            $this->error('不支持的同步场景：' . $scene);
            $this->line('可用场景：' . implode(', ', array_merge(array_keys(self::SCENES), ['all'])));
            return 1;
        }

        foreach ($scenes as $currentScene) {
            if (!$this->syncScene($currentScene, $withQuality)) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * 根据住院号从HIS同步本地住院患者信息。
     *
     * 该方法供其他业务代码直接调用，只同步BRRY及关联患者信息，
     * 不执行事中质控规则。
     *
     * @param string $zyh
     * @return bool
     */
    public function updateBrryByZyh($zyh)
    {
        $zyh = trim((string)$zyh);
        if ($zyh === '') {
            Log::warning('shizhong_warning_sync::按住院号同步BRRY失败，住院号为空');
            return false;
        }

        try {
            $rows = $this->getBRRYDataByZyh($zyh);
            if (empty($rows)) {
                Log::warning('shizhong_warning_sync::按住院号同步BRRY未查询到数据', [
                    'zyh' => $zyh,
                ]);
                return false;
            }

            $normalizedRows = array_map([$this, 'normalizeRowKeys'], $rows);
            $matchedRow = null;
            foreach ($normalizedRows as $row) {
                if ((string)($row['ZYH'] ?? '') === $zyh) {
                    $matchedRow = $row;
                    break;
                }
            }

            if ($matchedRow === null) {
                Log::warning('shizhong_warning_sync::HIS返回数据中未匹配到目标住院号', [
                    'zyh' => $zyh,
                    'row_count' => count($normalizedRows),
                ]);
                return false;
            }

            $context = $this->buildSyncContext(false);
            $this->resetSyncStats(count($normalizedRows), 1, 0, max(count($normalizedRows) - 1, 0));
            $this->syncOneRow($matchedRow, $context, false, 'update_brry_by_zyh');

            $updated = (int)($this->syncStats['updated_brry'] ?? 0) > 0;
            $deleted = (int)($this->syncStats['deleted'] ?? 0) > 0;
            $success = $updated || $deleted;

            Log::info('shizhong_warning_sync::按住院号同步BRRY完成', [
                'zyh' => $zyh,
                'updated' => $updated,
                'deleted' => $deleted,
                'success' => $success,
            ]);

            return $success;
        } catch (\Throwable $e) {
            Log::error('shizhong_warning_sync::按住院号同步BRRY失败', [
                'zyh' => $zyh,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return false;
        }
    }

    /**
     * 按指定场景同步数据。
     *
     * @param string $scene
     * @param bool $withQuality
     * @return bool
     */
    private function syncScene($scene, $withQuality)
    {
        $config = self::SCENES[$scene];

        if (($config['type'] ?? '') === 'pending_warnings') {
            if (!$withQuality) {
                $this->line($config['name'] . '：已使用 --no-quality 跳过未整改预警复查');
                return true;
            }

            try {
                $stats = $this->recheckPendingWarnings();
                $this->outputSyncSummary($config['name'], $stats, true);
            } catch (\Throwable $e) {
                Log::error('shizhong_warning_sync::未整改预警复查失败', [
                    'scene' => $scene,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $this->error($config['name'] . ' 失败：' . $e->getMessage());
                return false;
            }

            return true;
        }

        if (($config['type'] ?? '') === 'in_hospital_cleanup') {
            try {
                $stats = $this->cleanupMissingInHospitalPatients();
                $this->outputSyncSummary($config['name'], $stats, true);
            } catch (\Throwable $e) {
                Log::error('shizhong_warning_sync::在院患者医院库存在性核查失败', [
                    'scene' => $scene,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $this->error($config['name'] . ' 失败：' . $e->getMessage());
                return false;
            }

            return true;
        }

        if (($config['type'] ?? '') === 'critical_value_24') {
            if (!$withQuality) {
                $this->line($config['name'] . '：已使用 --no-quality 跳过危急值预警');
                return true;
            }

            try {
                $stats = $this->syncCriticalValue24Warnings();
                $this->outputSyncSummary($config['name'], $stats, true);
            } catch (\Throwable $e) {
                Log::error('shizhong_warning_sync::危急值预警同步失败', [
                    'scene' => $scene,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $this->error($config['name'] . ' 失败：' . $e->getMessage());
                return false;
            }

            return true;
        }

        if (($config['type'] ?? '') === 'transfusion_24') {
            if (!$withQuality) {
                $this->line($config['name'] . '：已使用 --no-quality 跳过输血预警');
                return true;
            }

            try {
                $stats = $this->syncTransfusion24Warnings();
                $this->outputSyncSummary($config['name'], $stats, true);
            } catch (\Throwable $e) {
                Log::error('shizhong_warning_sync::输血预警同步失败', [
                    'scene' => $scene,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $this->error($config['name'] . ' 失败：' . $e->getMessage());
                return false;
            }

            return true;
        }

        if (($config['type'] ?? '') === 'superior_round_cycle') {
            if (!$withQuality) {
                $this->line($config['name'] . '：已使用 --no-quality 跳过周期查房预警');
                return true;
            }

            try {
                $stats = $this->syncSuperiorRoundCycleWarnings();
                $this->outputSyncSummary($config['name'], $stats, true);
            } catch (\Throwable $e) {
                Log::error('shizhong_warning_sync::周期查房预警同步失败', [
                    'scene' => $scene,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $this->error($config['name'] . ' 失败：' . $e->getMessage());
                return false;
            }

            return true;
        }

        if (($config['type'] ?? '') === 'stage_summary_30d') {
            if (!$withQuality) {
                $this->line($config['name'] . '：已使用 --no-quality 跳过阶段小结预警');
                return true;
            }

            try {
                $stats = $this->syncStageSummary30dWarnings();
                $this->outputSyncSummary($config['name'], $stats, true);
            } catch (\Throwable $e) {
                Log::error('shizhong_warning_sync::阶段小结预警同步失败', [
                    'scene' => $scene,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $this->error($config['name'] . ' 失败：' . $e->getMessage());
                return false;
            }

            return true;
        }

        if (($config['type'] ?? '') === 'transfer_in_24') {
            if (!$withQuality) {
                $this->line($config['name'] . '：已使用 --no-quality 跳过转入记录预警');
                return true;
            }

            try {
                $stats = $this->syncTransferIn24Warnings();
                $this->outputSyncSummary($config['name'], $stats, true);
            } catch (\Throwable $e) {
                Log::error('shizhong_warning_sync::转入记录预警同步失败', [
                    'scene' => $scene,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $this->error($config['name'] . ' 失败：' . $e->getMessage());
                return false;
            }

            return true;
        }

        if (($config['type'] ?? '') === 'ct_progress_72') {
            if (!$withQuality) {
                $this->line($config['name'] . '：已使用 --no-quality 跳过CT病程记录预警');
                return true;
            }

            try {
                $stats = $this->syncCtProgress72Warnings();
                $this->outputSyncSummary($config['name'], $stats, true);
            } catch (\Throwable $e) {
                Log::error('shizhong_warning_sync::CT病程记录预警同步失败', [
                    'scene' => $scene,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $this->error($config['name'] . ' 失败：' . $e->getMessage());
                return false;
            }

            return true;
        }

        if (($config['type'] ?? '') === 'mr_progress_72') {
            if (!$withQuality) {
                $this->line($config['name'] . '：已使用 --no-quality 跳过MR病程记录预警');
                return true;
            }

            try {
                $stats = $this->syncMrProgress72Warnings();
                $this->outputSyncSummary($config['name'], $stats, true);
            } catch (\Throwable $e) {
                Log::error('shizhong_warning_sync::MR病程记录预警同步失败', [
                    'scene' => $scene,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $this->error($config['name'] . ' 失败：' . $e->getMessage());
                return false;
            }

            return true;
        }

        if (($config['type'] ?? '') === 'antibiotic_progress_72') {
            if (!$withQuality) {
                $this->line($config['name'] . '：已使用 --no-quality 跳过抗菌药物病程记录预警');
                return true;
            }

            try {
                $stats = $this->syncAntibioticProgress72Warnings();
                $this->outputSyncSummary($config['name'], $stats, true);
            } catch (\Throwable $e) {
                Log::error('shizhong_warning_sync::抗菌药物病程记录预警同步失败', [
                    'scene' => $scene,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $this->error($config['name'] . ' 失败：' . $e->getMessage());
                return false;
            }

            return true;
        }

        if (($config['type'] ?? '') === 'chemo_progress_72') {
            if (!$withQuality) {
                $this->line($config['name'] . '：已使用 --no-quality 跳过化疗药物病程记录预警');
                return true;
            }

            try {
                $stats = $this->syncChemoProgress72Warnings();
                $this->outputSyncSummary($config['name'], $stats, true);
            } catch (\Throwable $e) {
                Log::error('shizhong_warning_sync::化疗药物病程记录预警同步失败', [
                    'scene' => $scene,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $this->error($config['name'] . ' 失败：' . $e->getMessage());
                return false;
            }

            return true;
        }

        if (($config['type'] ?? '') === 'death_discussion_168') {
            if (!$withQuality) {
                $this->line($config['name'] . '：已使用 --no-quality 跳过死亡病例讨论预警');
                return true;
            }

            try {
                $stats = $this->syncDeathDiscussionConclusion168hWarnings();
                $this->outputSyncSummary($config['name'], $stats, true);
            } catch (\Throwable $e) {
                Log::error('shizhong_warning_sync::死亡病例讨论预警同步失败', [
                    'scene' => $scene,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $this->error($config['name'] . ' 失败：' . $e->getMessage());
                return false;
            }

            return true;
        }

        [$startTime, $endTime] = $this->getSceneTimeRange($config);

        $timeField = ShizhongWarningConfig::getValue($config['field_config_key']);

        if (empty($timeField)) {
            Log::error('shizhong_warning_sync::时间字段配置缺失', ['scene' => $scene, 'config' => $config]);
            $this->error($config['name'] . ' 时间字段配置缺失');
            return false;
        }

        try {
            $data = $this->getBRRYDataByTimeRange($timeField, $startTime, $endTime, $config);
        } catch (\Throwable $e) {
            Log::error('shizhong_warning_sync::查询HIS数据失败', [
                'scene' => $scene,
                'message' => $e->getMessage(),
            ]);
            $this->error($config['name'] . ' 查询HIS数据失败：' . $e->getMessage());
            return false;
        }

        Log::info('shizhong_warning_sync::本次同步数据量', [
            'scene' => $scene,
            'name' => $config['name'],
            'time_field' => $timeField,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'count' => count($data),
            'include_in_hospital' => !empty($config['include_in_hospital']),
        ]);
        $includeInHospitalText = !empty($config['include_in_hospital']) ? '，包含出院时间为空的在院患者' : '';
        $this->info($config['name'] . '：' . $startTime . ' 至 ' . $endTime . $includeInHospitalText . '，数据量 ' . count($data));

        if (empty($data)) {
            return true;
        }

        try {
            $stats = $this->syncRows($data, $withQuality, $scene);
            $this->outputSyncSummary($config['name'], $stats, $withQuality);
        } catch (\Throwable $e) {
            Log::error('shizhong_warning_sync::同步数据失败', [
                'scene' => $scene,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $this->error($config['name'] . ' 同步数据失败：' . $e->getMessage());
            return false;
        }

        return true;
    }

    /**
     * 获取场景对应的时间范围。
     *
     * @param array $config
     * @return array
     */
    private function getSceneTimeRange(array $config)
    {
        if (($config['type'] ?? '') === 'today_to_now') {
            return [
                date('Y-m-d 00:00:00'),
                date('Y-m-d H:i:s'),
            ];
        }

        if (($config['type'] ?? '') === 'yesterday_to_now') {
            return [
                date('Y-m-d 00:00:00', strtotime('-1 day')),
                date('Y-m-d H:i:s'),
            ];
        }

        $elapsedStartHours = (int)$config['elapsed_start_hours'];
        $elapsedEndHours = (int)$config['elapsed_end_hours'];

        return [
            date('Y-m-d H:i:s', strtotime('-' . $elapsedEndHours . ' hours')),
            date('Y-m-d H:i:s', strtotime('-' . $elapsedStartHours . ' hours')),
        ];
    }

    /**
     * 根据时间字段和时间范围获取HIS住院数据。
     *
     * @param string $timeField
     * @param string $startTime
     * @param string $endTime
     * @param array $config
     * @return array
     */
    private function getBRRYDataByTimeRange($timeField, $startTime, $endTime, array $config = [])
    {
        $sql = $this->buildBrrySqlByTimeRange($timeField, $startTime, $endTime, $config);
        $connectType = strtoupper((string)env('BRRY_CONNECT_TYPE'));

        if ($connectType === 'SQLSERVER') {
            return $this->getSqlserverBRRYData($sql);
        }

        if ($connectType === 'MYSQL') {
            return $this->getMysqlBRRYData($sql);
        }

        return $this->getOracleBRRYData($sql);
    }

    /**
     * 根据住院号查询HIS住院数据。
     *
     * @param string $zyh
     * @return array
     */
    private function getBRRYDataByZyh($zyh)
    {
        $sql = $this->buildBrrySqlByZyh($zyh);
        $connectType = strtoupper((string)env('BRRY_CONNECT_TYPE'));

        if ($connectType === 'SQLSERVER') {
            return $this->getSqlserverBRRYData($sql);
        }

        if ($connectType === 'MYSQL') {
            return $this->getMysqlBRRYData($sql);
        }

        return $this->getOracleBRRYData($sql);
    }

    /**
     * 拼接BRRY住院号查询SQL。
     *
     * @param string $zyh
     * @return string
     */
    private function buildBrrySqlByZyh($zyh)
    {
        $sql = ShizhongWarningConfig::getValue(self::BRRY_SQL_CONFIG_KEY);
        if (empty($sql)) {
            throw new \RuntimeException('shizhong_warning_configs 未配置BRRY基础SQL');
        }

        $zyhField = ShizhongWarningConfig::getValue('zyh_field', 'ZYH');
        if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $zyhField)) {
            throw new \InvalidArgumentException('非法的HIS住院号字段配置：' . $zyhField);
        }

        $safeZyh = str_replace("'", "''", $zyh);
        return $this->appendWhereCondition($sql, "{$zyhField} = '{$safeZyh}'");
    }

    /**
     * 拼接BRRY时间范围查询SQL。
     *
     * @param string $timeField
     * @param string $startTime
     * @param string $endTime
     * @param array $config
     * @return string
     */
    private function buildBrrySqlByTimeRange($timeField, $startTime, $endTime, array $config = [])
    {
        $sql = ShizhongWarningConfig::getValue(self::BRRY_SQL_CONFIG_KEY);
        if (empty($sql)) {
            throw new \RuntimeException('shizhong_warning_configs 未配置BRRY基础SQL');
        }

        if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $timeField)) {
            throw new \InvalidArgumentException('非法的HIS时间字段配置：' . $timeField);
        }

        $condition = $this->buildTimeRangeCondition($timeField, $startTime, $endTime);

        if (!empty($config['include_in_hospital'])) {
            $dischargeTimeField = ShizhongWarningConfig::getValue(self::DISCHARGE_TIME_FIELD_CONFIG_KEY);
            if (empty($dischargeTimeField)) {
                throw new \RuntimeException('shizhong_warning_configs 未配置出院时间字段，无法查询在院患者');
            }

            if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $dischargeTimeField)) {
                throw new \InvalidArgumentException('非法的HIS出院时间字段配置：' . $dischargeTimeField);
            }

            $condition = '(' . $condition . ' OR ' . $this->buildEmptyTimeCondition($dischargeTimeField) . ')';
        }

        return $this->appendWhereCondition($sql, $condition);
    }

    /**
     * 构建时间范围查询条件。
     *
     * @param string $timeField
     * @param string $startTime
     * @param string $endTime
     * @return string
     */
    private function buildTimeRangeCondition($timeField, $startTime, $endTime)
    {
        $connectType = strtoupper((string)env('BRRY_CONNECT_TYPE'));

        if (in_array($connectType, ['SQLSERVER', 'MYSQL'], true)) {
            return "{$timeField} >= '{$startTime}' AND {$timeField} <= '{$endTime}'";
        }

        return "{$timeField} >= TO_DATE('{$startTime}', 'yyyy-mm-dd HH24:mi:ss') AND {$timeField} <= TO_DATE('{$endTime}', 'yyyy-mm-dd HH24:mi:ss')";
    }

    /**
     * 构建空时间查询条件。
     *
     * @param string $timeField
     * @return string
     */
    private function buildEmptyTimeCondition($timeField)
    {
        return "({$timeField} IS NULL OR {$timeField} = '')";
    }

    /**
     * 给基础SQL追加查询条件。
     *
     * @param string $sql
     * @param string $condition
     * @return string
     */
    private function appendWhereCondition($sql, $condition)
    {
        $connector = preg_match('/\bwhere\b/i', $sql) ? ' AND ' : ' WHERE ';

        if (preg_match('/\border\s+by\b/i', $sql, $matches, PREG_OFFSET_CAPTURE)) {
            $offset = $matches[0][1];
            return rtrim(substr($sql, 0, $offset)) . $connector . $condition . ' ' . substr($sql, $offset);
        }

        return rtrim($sql) . $connector . $condition;
    }

    /**
     * 从Oracle获取BRRY数据。
     *
     * @param string $sql
     * @return array
     */
    private function getOracleBRRYData($sql)
    {
        $username = env('HISDB_USERNAME', '');
        $password = env('HISDB_PASSWORD', '');
        $connection = env('HISDB_HOST', '');
        $port = env('HISDB_PORT', '');
        $tns = env('HISDB_TNS', '');

        $con = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
        if (!$con) {
            $error = oci_error();
            throw new \RuntimeException($error['message'] ?? 'Oracle数据库连接失败');
        }

        $statement = oci_parse($con, $sql);
        if (!$statement) {
            $error = oci_error($con);
            throw new \RuntimeException($error['message'] ?? 'Oracle SQL解析失败');
        }

        if (!oci_execute($statement, OCI_DEFAULT)) {
            $error = oci_error($statement);
            throw new \RuntimeException($error['message'] ?? 'Oracle SQL执行失败');
        }

        $data = [];
        while ($row = oci_fetch_assoc($statement)) {
            $data[] = $row;
        }

        oci_free_statement($statement);
        oci_close($con);

        return $data;
    }

    /**
     * 从SQL Server获取BRRY数据。
     *
     * @param string $sql
     * @return array
     */
    private function getSqlserverBRRYData($sql)
    {
        $sqlsrvConfig = [
            'host' => env('SQLSRV_HOST'),
            'port' => '1433',
            'database' => env('SQLSRV_DATABASE'),
            'username' => env('SQLSRV_USERNAME'),
            'password' => env('SQLSRV_PASSWORD'),
        ];

        $sqlsrvService = SqlServerProxyService::withConfig($sqlsrvConfig);
        if (!$sqlsrvService->testConnection()) {
            throw new \RuntimeException('SQL Server数据库连接失败');
        }

        return $sqlsrvService->query($sql) ?? [];
    }

    /**
     * 从MySQL获取BRRY数据。
     *
     * @param string $sql
     * @return array
     */
    private function getMysqlBRRYData($sql)
    {
        try {
            $statement = $this->getMysqlConnect()->query($sql);
            if (!$statement) {
                throw new \RuntimeException('MySQL SQL执行失败');
            }

            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new \RuntimeException('MySQL SQL执行失败：' . $e->getMessage());
        }
    }

    /**
     * 建立MySQL数据库连接。
     *
     * @return \PDO
     */
    private function getMysqlConnect()
    {
        if ($this->mysqlConnection instanceof \PDO) {
            return $this->mysqlConnection;
        }

        $host = env('MYSQL_HOST', env('HISDB_HOST', '127.0.0.1'));
        $port = env('MYSQL_PORT', env('HISDB_PORT', '3306'));
        $database = env('MYSQL_DATABASE', env('HISDB_DATABASE', ''));
        $username = env('MYSQL_USERNAME', env('HISDB_USERNAME', ''));
        $password = env('MYSQL_PASSWORD', env('HISDB_PASSWORD', ''));

        if (empty($host) || empty($database) || empty($username)) {
            throw new \RuntimeException('MySQL数据库连接配置缺失');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        try {
            $this->mysqlConnection = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (\PDOException $e) {
            throw new \RuntimeException('MySQL数据库连接失败：' . $e->getMessage());
        }

        return $this->mysqlConnection;
    }

    /**
     * 同步HIS返回的数据行。
     *
     * @param array $rows
     * @param bool $withQuality
     * @param string $scene
     * @return array
     */
    private function syncRows(array $rows, $withQuality, $scene)
    {
        $context = $this->buildSyncContext($withQuality);
        $hisCount = count($rows);
        $rows = array_map([$this, 'normalizeRowKeys'], $rows);
        $rowsWithZyh = array_filter($rows, function ($row) {
            return !empty($row['ZYH']);
        });
        $invalidCount = count($rows) - count($rowsWithZyh);
        $rows = array_values(array_column($rowsWithZyh, null, 'ZYH'));
        $duplicateCount = count($rowsWithZyh) - count($rows);

        $this->resetSyncStats($hisCount, count($rows), $invalidCount, $duplicateCount);
        $this->line('  有效住院号 ' . count($rows) . ' 个，过滤空住院号 ' . $invalidCount . ' 条，去重 ' . $duplicateCount . ' 条');

        foreach ($rows as $row) {
            try {
                $this->syncStats['processed']++;
                $this->syncOneRow($row, $context, $withQuality, $scene);
            } catch (\Throwable $e) {
                $this->syncStats['errors']++;
                $this->addSyncDetail('error_detail', '住院号' . ($row['ZYH'] ?? '') . '：' . $e->getMessage());
                Log::error('shizhong_warning_sync::单条数据同步失败', [
                    'zyh' => $row['ZYH'] ?? '',
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $this->error('  - 住院号' . ($row['ZYH'] ?? '') . ' 同步失败：' . $e->getMessage());
            }
        }

        return $this->syncStats;
    }

    /**
     * 清理本地仍标记在院但医院库已不存在的患者。
     *
     * @return array
     */
    private function cleanupMissingInHospitalPatients()
    {
        $limit = (int)ShizhongWarningConfig::getValue('in_hospital_cleanup_limit', '1000');
        $limit = $limit > 0 ? $limit : 1000;
        $patients = ZY_BRRY::query()
            ->where(function ($query) {
                $query->whereNull('AAC01')
                    ->orWhere('AAC01', '=', '')
                    ->orWhere('AAC01', '=', '0000-00-00 00:00:00');
            })
            ->whereNotNull('ZYH')
            ->limit($limit)
            ->get(['ZYH'])
            ->toArray();

        $this->resetSyncStats(count($patients), count($patients), 0, 0);
        $this->syncStats['summary_unit'] = '个本地在院住院号';
        $this->line('  在院患者医院库存在性核查：本次检查本地在院患者 ' . count($patients) . ' 个');

        foreach ($patients as $patient) {
            $zyh = (string)($patient['ZYH'] ?? '');
            if ($zyh === '') {
                $this->syncStats['skipped']++;
                continue;
            }

            try {
                $this->syncStats['processed']++;
                if ($this->existsInHospitalBrry($zyh)) {
                    continue;
                }

                $this->deleteLocalPatientData($zyh);
            } catch (\Throwable $e) {
                $this->syncStats['errors']++;
                $this->addSyncDetail('error_detail', '住院号' . $zyh . '：在院患者存在性核查失败，' . $e->getMessage());
                Log::error('shizhong_warning_sync::在院患者存在性核查单条失败', [
                    'zyh' => $zyh,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        return $this->syncStats;
    }

    /**
     * 判断住院号在医院库是否仍存在。
     *
     * @param string $zyh
     * @return bool
     */
    private function existsInHospitalBrry($zyh)
    {
        $rows = $this->getBRRYDataByZyh($zyh);
        return !empty($rows);
    }

    /**
     * 删除本地患者数据和对应预警信息。
     *
     * @param string $zyh
     * @return void
     */
    private function deleteLocalPatientData($zyh)
    {
        DB::transaction(function () use ($zyh) {
            ZY_BRRY::query()->where('ZYH', '=', $zyh)->delete();
            PatientInfo::query()->where('MED_REC_ID', '=', $zyh)->delete();
            PatientInfoV2::query()->where('ZYH', '=', $zyh)->delete();
            QualitySendMsgLog::query()->where('zyh', '=', $zyh)->delete();
        });

        $this->syncStats['deleted']++;
        $this->addSyncDetail('deleted_detail', '住院号' . $zyh . '：医院库不存在，已删除本地ZY_BRRY、patient_info、patient_info_v2并删除对应预警');
    }

    /**
     * 复查未整改预警，已补写文书的病例需要更新为已整改。
     *
     * @return array
     */
    private function recheckPendingWarnings()
    {
        $context = $this->buildSyncContext(true);
        $supportedRuleIds = [101, 99, 1047, 96, 95, 98, 97, 1000, 1051, 278, 1049, 1050, 132, 1011, 1012, 1013, 1015, 102, 103, 109, 110, 1010];
        $limit = (int)ShizhongWarningConfig::getValue('pending_warning_recheck_limit', '10000');
        $limit = $limit > 0 ? $limit : 1000;

        $pendingWarnings = QualitySendMsgLog::query()
            ->select(['zyh', 'rule_id', 'data_id', 'data_type'])
            ->where('status', '=', 2)
            ->whereIn('rule_id', $supportedRuleIds)
            ->where(function ($query) {
                $query->where('is_delete', '<>', 0)->orWhereNull('is_delete');
            })
            ->whereNotNull('zyh')
            ->distinct()
            ->limit($limit)
            ->get()
            ->toArray();

        $this->resetSyncStats(count($pendingWarnings), count($pendingWarnings), 0, 0);
        $this->syncStats['summary_unit'] = '条未整改预警';
        $this->line('  未整改预警复查：本次复查 ' . count($pendingWarnings) . ' 条，支持规则 ' . implode('、', $supportedRuleIds));

        foreach ($pendingWarnings as $pendingWarning) {
            $zyh = (string)($pendingWarning['zyh'] ?? '');
            $ruleId = (int)($pendingWarning['rule_id'] ?? 0);
            $dataId = (string)($pendingWarning['data_id'] ?? '');
            $dataType = (string)($pendingWarning['data_type'] ?? '');
            if ($zyh === '') {
                $this->syncStats['skipped']++;
                $this->addSyncDetail('skipped_detail', '未整改预警缺少住院号，规则' . $ruleId . '，已跳过');
                continue;
            }

            try {
                $this->syncStats['processed']++;
                $this->recheckOnePendingWarning($zyh, $ruleId, $context, $dataId, $dataType);
            } catch (\Throwable $e) {
                $this->syncStats['errors']++;
                $this->addSyncDetail('error_detail', '住院号' . $zyh . '：规则' . $ruleId . '复查失败，' . $e->getMessage());
                Log::error('shizhong_warning_sync::未整改预警单条复查失败', [
                    'zyh' => $zyh,
                    'rule_id' => $ruleId,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        return $this->syncStats;
    }

    /**
     * 按规则复查单条未整改预警。
     *
     * @param string $zyh
     * @param int $ruleId
     * @param array $context
     * @return void
     */
    private function recheckOnePendingWarning($zyh, $ruleId, array $context, $dataId = '', $dataType = '')
    {
        $patientInfo = PatientInfo::query()
            ->where('MED_REC_ID', '=', $zyh)
            ->first(['MED_REC_ID', 'AAA28', 'AAB01', 'AAC01', 'AAA01']);
        $patientInfo = $patientInfo ? $patientInfo->toArray() : ['MED_REC_ID' => $zyh];

        $this->syncHomeDataForQuality($zyh, $context);

        if ($ruleId === 101) {
            $this->checkAdmissionFirstCourse8h($zyh, $patientInfo, $context, true);
            return;
        }

        if ($ruleId === 99) {
            $this->checkAdmissionRecordOr24h($zyh, $patientInfo, $context, true);
            return;
        }

        if ($ruleId === 1047) {
            $this->checkAdmissionSuperiorFirstRound48h($zyh, $patientInfo, $context, true);
            return;
        }

        if ($ruleId === 96) {
            $this->checkDischargeRecord24h($zyh, $patientInfo, $context, true);
            return;
        }

        if ($ruleId === 95) {
            $this->checkDischargeHomePage24h($zyh, $patientInfo, $context, true);
            return;
        }

        if ($ruleId === 98) {
            $this->checkDeathRecord24h($zyh, $patientInfo, $context, true);
            return;
        }

        if ($ruleId === 97) {
            $this->checkDischarge24hAdmissionDischargeRecord($zyh, $patientInfo, $context, true);
            return;
        }

        if ($ruleId === 1000) {
            $this->checkDeath24hAdmissionDeathRecord($zyh, $patientInfo, $context, true);
            return;
        }

        if ($ruleId === 1051) {
            $this->checkDischargeSuperiorRoundDay($zyh, $patientInfo, $context, true);
            return;
        }

        if ($ruleId === 278) {
            $this->checkDeathHomePage168h($zyh, $patientInfo, $context, true);
            return;
        }

        if ($ruleId === 1049) {
            $this->checkCriticallyIllSuperiorRoundDaily($zyh, $patientInfo, $context, true, $dataId, $dataType);
            return;
        }

        if ($ruleId === 1050) {
            $this->checkSeriouslyIllSuperiorRoundEvery2Days($zyh, $patientInfo, $context, true, $dataId, $dataType);
            return;
        }

        if ($ruleId === 132) {
            $this->checkStableRoundEvery3Days($zyh, $patientInfo, $context, true, $dataId, $dataType);
            return;
        }

        if ($ruleId === 1011) {
            $this->checkCriticalValueRecord24h($zyh, $context, null, true, $dataId, $dataType);
            return;
        }

        if ($ruleId === 1012) {
            $this->checkTransfusionRecord24h($zyh, $context, null, true, $dataId, $dataType);
            return;
        }

        if ($ruleId === 1013) {
            $this->checkStageSummaryRecord30d($zyh, $patientInfo, $context, true, $dataId, $dataType);
            return;
        }

        if ($ruleId === 1015) {
            $this->checkTransferInRecord24h($zyh, $context, null, true, $dataId, $dataType);
            return;
        }

        if ($ruleId === 102) {
            $this->checkCtProgressRecord72h($zyh, $context, null, true, $dataId, $dataType);
            return;
        }

        if ($ruleId === 103) {
            $this->checkMrProgressRecord72h($zyh, $context, null, true, $dataId, $dataType);
            return;
        }

        if ($ruleId === 109) {
            $this->checkAntibioticProgressRecord72h($zyh, $context, null, true, $dataId, $dataType);
            return;
        }

        if ($ruleId === 110) {
            $this->checkChemoProgressRecord72h($zyh, $context, null, true, $dataId, $dataType);
            return;
        }

        if ($ruleId === 1010) {
            $this->checkDeathDiscussionConclusion168h($zyh, $patientInfo, $context, true, $dataId, $dataType);
            return;
        }

        $this->syncStats['skipped']++;
        $this->addSyncDetail('skipped_detail', '住院号' . $zyh . '：规则' . $ruleId . '暂未配置未整改复查逻辑');
    }

    /**
     * 重置当前场景统计数据。
     *
     * @param int $hisCount
     * @param int $validCount
     * @param int $invalidCount
     * @param int $duplicateCount
     * @return void
     */
    private function resetSyncStats($hisCount, $validCount, $invalidCount, $duplicateCount)
    {
        $this->syncStats = [
            'his_rows' => $hisCount,
            'valid_zyh' => $validCount,
            'summary_unit' => '个住院号',
            'invalid_zyh' => $invalidCount,
            'duplicate_zyh' => $duplicateCount,
            'processed' => 0,
            'sync_zyh_marked' => 0,
            'updated_brry' => 0,
            'updated_patient_info' => 0,
            'updated_patient_info_v2' => 0,
            'auto_reviewed' => 0,
            'deleted' => 0,
            'skipped' => 0,
            'filtered_columns' => 0,
            'warnings_sent' => 0,
            'warnings_expired' => 0,
            'warnings_resolved' => 0,
            'pending_unresolved' => 0,
            'errors' => 0,
            'updated_detail' => [],
            'warning_detail' => [],
            'resolved_detail' => [],
            'auto_review_detail' => [],
            'deleted_detail' => [],
            'skipped_detail' => [],
            'filtered_column_detail' => [],
            'error_detail' => [],
        ];
    }

    /**
     * 添加同步明细，避免控制台输出过多。
     *
     * @param string $key
     * @param string $detail
     * @return void
     */
    private function addSyncDetail($key, $detail)
    {
        if (!isset($this->syncStats[$key])) {
            $this->syncStats[$key] = [];
        }

        if (count($this->syncStats[$key]) >= 30) {
            return;
        }

        $this->syncStats[$key][] = $detail;
    }

    /**
     * 构建单条更新明细。
     *
     * @param string $zyh
     * @param array $update
     * @param array $patientInfo
     * @return string
     */
    private function buildUpdatedDetail($zyh, array $update, array $patientInfo)
    {
        $parts = [
            '住院号' . $zyh,
            '患者=' . ($update['BRXM'] ?? ''),
            '科室=' . ($update['ZY_KSMC'] ?: ($update['BRKS'] ?? '')),
            '病区=' . ($update['ZY_BQMC'] ?: ($update['BRBQ'] ?? '')),
            '入院=' . ($update['AAB01'] ?? ''),
            '出院=' . ($update['AAC01'] ?? ''),
            '在院状态=' . (($patientInfo['in_hospital'] ?? 0) == 1 ? '在院' : '出院'),
        ];

        return implode('，', array_filter($parts, function ($part) {
            return substr($part, -1) !== '=';
        }));
    }

    /**
     * 输出当前场景同步汇总。
     *
     * @param string $sceneName
     * @param array $stats
     * @param bool $withQuality
     * @return void
     */
    private function outputSyncSummary($sceneName, array $stats, $withQuality)
    {
        $summaryUnit = $stats['summary_unit'] ?? '个住院号';
        $this->info('  ' . $sceneName . '同步完成：处理 ' . $stats['processed'] . '/' . $stats['valid_zyh'] . ' ' . $summaryUnit);
        $this->line('  更新：ZY_BRRY ' . $stats['updated_brry'] . ' 条，PatientInfo ' . $stats['updated_patient_info'] . ' 条，PatientInfoV2出院时间 ' . $stats['updated_patient_info_v2'] . ' 条');
        $this->line('  其他：标记同步住院号 ' . $stats['sync_zyh_marked'] . ' 条，自动审核 ' . $stats['auto_reviewed'] . ' 条，删除 ' . $stats['deleted'] . ' 条，跳过 ' . $stats['skipped'] . ' 条，字段过滤 ' . $stats['filtered_columns'] . ' 个，失败 ' . $stats['errors'] . ' 条');

        if ($withQuality) {
            $this->line('  预警：发送 ' . $stats['warnings_sent'] . ' 条，已超截止未发送 ' . $stats['warnings_expired'] . ' 条，标记整改 ' . $stats['warnings_resolved'] . ' 条，复查仍未整改 ' . $stats['pending_unresolved'] . ' 条');
        } else {
            $this->line('  预警：已使用 --no-quality 跳过质控规则');
        }

        $this->outputSyncDetailBlock('更新明细', $stats['updated_detail']);
        $this->outputSyncDetailBlock('预警明细', $stats['warning_detail']);
        $this->outputSyncDetailBlock('整改明细', $stats['resolved_detail']);
        $this->outputSyncDetailBlock('自动审核明细', $stats['auto_review_detail']);
        $this->outputSyncDetailBlock('删除明细', $stats['deleted_detail']);
        $this->outputSyncDetailBlock('跳过明细', $stats['skipped_detail']);
        $this->outputSyncDetailBlock('字段过滤明细', $stats['filtered_column_detail']);
        $this->outputSyncDetailBlock('失败明细', $stats['error_detail']);
    }

    /**
     * 输出明细块。
     *
     * @param string $title
     * @param array $details
     * @return void
     */
    private function outputSyncDetailBlock($title, array $details)
    {
        if (empty($details)) {
            return;
        }

        $this->line('  ' . $title . '：');
        foreach ($details as $detail) {
            $this->line('    - ' . $detail);
        }
    }

    /**
     * 构建同步上下文，避免循环内重复查询基础配置。
     *
     * @param bool $withQuality
     * @return array
     */
    private function buildSyncContext($withQuality)
    {
        $department = Department::query()->get()->toArray();
        $staff = Staff::query()->get()->toArray();
        $caseRule = CaseRule::query()->where('status', '=', 1)->get()->toArray();

        $context = [
            'module_name' => env('APP_NAME', ''),
            'department_map' => array_column($department, 'dep_name', 'dep_id'),
            'staff_map' => array_column($staff, 'name', 'code'),
            'case_rule_map' => array_column($caseRule, null, 'id'),
            'auto_review_keyword' => ShizhongWarningConfig::getValue('auto_review_enabled', '0'),
            'auto_review_score_threshold' => ShizhongWarningConfig::getValue('auto_review_score_threshold', '0'),
            'force_level_check' => ShizhongWarningConfig::getValue('force_level_check', '2'),
            'default_quality_user_id' => Setting::query()->where('name', '=', 'default_quality_user_id')->value('content'),
            'users' => User::query()->get(['id', 'dep_id', 'department_review'])->toArray(),
            'hospital_name' => config('confAdmin.hospital_name'),
            'zy_brry_columns' => array_fill_keys(array_map('strtoupper', Schema::getColumnListing((new ZY_BRRY())->getTable())), true),
            'case_service' => null,
            'home_data_service' => null,
        ];

        if ($withQuality) {
            $context['case_service'] = new CaseService();

            if (!$this->shouldSkipHomeDataSync($context)) {
                $className = '\\App\\Services\\MysqlDataSync\\' . $context['module_name'] . '\\HomeData';
                if (!class_exists($className)) {
                    throw new \RuntimeException('事中质控文书同步类不存在：' . $className);
                }

                $context['home_data_service'] = new $className();
            }
        }

        return $context;
    }

    /**
     * 判断是否跳过文书数据同步。
     *
     * @param array $context
     * @return bool
     */
    private function shouldSkipHomeDataSync(array $context)
    {
        return in_array(($context['module_name'] ?? ''), ['hlw', 'ningxia']);
   
    }

    /**
     * 同步质控规则判断所需文书数据。
     *
     * @param string $zyh
     * @param array $context
     * @return void
     */
    private function syncHomeDataForQuality($zyh, array $context)
    {
        if ($this->shouldSkipHomeDataSync($context)) {
            return;
        }

        if (empty($context['home_data_service'])) {
            throw new \RuntimeException('事中质控文书同步服务未初始化');
        }

        $context['home_data_service']->getData($zyh, ['bl01']);
    }

    /**
     * 同步单条住院数据。
     *
     * @param array $row
     * @param array $context
     * @param bool $withQuality
     * @return void
     */
    private function syncOneRow(array $row, array $context, $withQuality, $scene)
    {
        $zyh = (string)$row['ZYH'];
        Log::info('shizhong_warning_sync::同步住院号', ['zyh' => $zyh]);

        if ($context['module_name'] === 'laizhou' && strpos($zyh, '_') !== false) {
            $this->syncStats['skipped']++;
            $this->addSyncDetail('skipped_detail', '住院号' . $zyh . '：莱州带下划线住院号跳过');
            return;
        }

        ShizhongSyncZyh::query()->updateOrInsert(['id' => $zyh], ['id' => $zyh]);
        $this->syncStats['sync_zyh_marked']++;

        if ((string)$this->value($row, ['CYPB']) === '99') {
            ZY_BRRY::query()->where('ZYH', '=', $zyh)->delete();
            PatientInfo::query()->where('MED_REC_ID', '=', $zyh)->delete();
            QualitySendMsgLog::query()->where('zyh', '=', $zyh)->update(['is_delete' => 0]);
            ShizhongSyncZyh::query()->where('id', '=', $zyh)->delete();
            $this->syncStats['deleted']++;
            $this->addSyncDetail('deleted_detail', '住院号' . $zyh . '：CYPB=99，已删除ZY_BRRY和PatientInfo，并恢复消息日志');
            return;
        }

        $update = $this->buildBrryUpdateData($row, $context);
        if (!empty($update['ZYCS']) && (string)$update['ZYCS'] === '-1') {
            $this->syncStats['skipped']++;
            $this->addSyncDetail('skipped_detail', '住院号' . $zyh . '：ZYCS=-1，跳过更新');
            return;
        }

        if ($this->applyAutoReview($zyh, $update, $context)) {
            $this->syncStats['auto_reviewed']++;
            $this->addSyncDetail('auto_review_detail', '住院号' . $zyh . '：符合自动审核条件，审核人ID=' . ($update['review_user'] ?? ''));
        }
        $brryUpdate = $this->filterBrryUpdateColumns($zyh, $update, $context);
        ZY_BRRY::query()->updateOrInsert(['ZYH' => $zyh], $brryUpdate);
        $this->syncStats['updated_brry']++;
        EsSaveService::zy_brry($zyh);

        $patientInfo = $this->buildPatientInfoData($zyh, $row, $update, $context);
        PatientInfo::query()->updateOrInsert(['MED_REC_ID' => $zyh], $patientInfo);
        $this->syncStats['updated_patient_info']++;
        $this->addSyncDetail('updated_detail', $this->buildUpdatedDetail($zyh, $update, $patientInfo));

        if ($this->syncPatientInfoV2DischargeTime($zyh, $update['AAC01'] ?? '')) {
            $this->syncStats['updated_patient_info_v2']++;
        }

        if ($withQuality) {
            $this->runQualityRules($zyh, $patientInfo, $context, $scene);
        }
    }

    /**
     * 同步出院时间到patient_info_v2。
     *
     * @param string $zyh
     * @param mixed $dischargeTime
     * @return bool
     */
    private function syncPatientInfoV2DischargeTime($zyh, $dischargeTime)
    {
        if ($this->isEmptyTime($dischargeTime)) {
            return false;
        }

        $affected = PatientInfoV2::query()
            ->where('ZYH', '=', $zyh)
            ->update(['AAC01' => $dischargeTime]);

        if ($affected > 0) {
            $this->addSyncDetail('updated_detail', '住院号' . $zyh . '：已同步patient_info_v2出院时间=' . $dischargeTime);
        }

        return $affected > 0;
    }

    /**
     * 过滤ZY_BRRY不存在的字段，兼容不同医院本地表结构差异。
     *
     * @param string $zyh
     * @param array $update
     * @param array $context
     * @return array
     */
    private function filterBrryUpdateColumns($zyh, array $update, array $context)
    {
        $columns = $context['zy_brry_columns'] ?? [];
        if (empty($columns)) {
            return $update;
        }

        $filtered = [];
        $missingColumns = [];
        foreach ($update as $column => $value) {
            if (isset($columns[strtoupper($column)])) {
                $filtered[$column] = $value;
                continue;
            }

            $missingColumns[] = $column;
        }

        if (!empty($missingColumns)) {
            $this->syncStats['filtered_columns'] += count($missingColumns);
            $this->addSyncDetail(
                'filtered_column_detail',
                '住院号' . $zyh . '：ZY_BRRY不存在字段【' . implode('、', $missingColumns) . '】，已跳过写入'
            );
        }

        return $filtered;
    }

    /**
     * 组装ZY_BRRY更新数据。
     *
     * @param array $row
     * @param array $context
     * @return array
     */
    private function buildBrryUpdateData(array $row, array $context)
    {
        $dep = $context['department_map'];
        $staff = $context['staff_map'];

        if ($context['module_name'] === 'lanling' && !empty($row['BRKS'])) {
            $brks = array_search($row['BRKS'], $dep);
            $brbq = array_search($this->value($row, ['BRBQ', 'ZY_BQDM']), $dep);
            $row['BRKS'] = $brks === false ? '' : (string)$brks;
            $row['BRBQ'] = $brbq === false ? '' : (string)$brbq;
        }

        $gcyDm = $this->value($row, ['GCYSDM', 'ZYYS']);
        $zlzzDm = $this->value($row, ['ZLZZDM', 'ZLXZ']);
        $zzysDm = $this->value($row, ['ZZYSDM', 'ZZYS']);
        $mzysDm = $this->value($row, ['MZYS']);
        $zrysDm = $this->value($row, ['ZRYS', 'ZSYS']);
        $brbq = $this->value($row, ['BRBQ', 'ZY_BQDM']);

        $update = [
            'AAA28' => $this->value($row, ['AAA28', 'ZYHM', 'BAN']),
            'AAB01' => $this->value($row, ['AAB01', 'RYRQ']),
            'AAC01' => $this->value($row, ['AAC01', 'CYRQ']),
            'ZY_KSDM' => $this->value($row, ['ZY_KSDM']),
            'BRKS' => $this->value($row, ['BRKS']),
            'ZY_KSMC' => $this->value($row, ['ZY_KSMC']),
            'GCYSDM' => $gcyDm,
            'GCYSMC' => $this->value($row, ['GCYSMC', 'GCYS'], $this->mapValue($staff, $gcyDm)),
            'ZLZZDM' => $zlzzDm,
            'ZLZZMC' => $this->value($row, ['ZLZZMC'], $this->mapValue($staff, $zlzzDm)),
            'ZZYSDM' => $zzysDm,
            'ZZYSMC' => $this->value($row, ['ZZYSMC'], $this->mapValue($staff, $zzysDm)),
            'MZYS' => $mzysDm,
            'MZYS_MC' => $this->value($row, ['MZYS_MC'], $this->mapValue($staff, $mzysDm)),
            'ZRYS' => $zrysDm,
            'ZRYS_MC' => $this->value($row, ['ZRYS_MC'], $this->mapValue($staff, $zrysDm)),
            'CYPB' => $this->value($row, ['CYPB']),
            'CYFS' => $this->value($row, ['CYFS']),
            'BRXM' => $this->value($row, ['BRXM']),
            'XB' => $this->value($row, ['XB']),
            'NL' => $this->value($row, ['NL']),
            'BRBQ' => $brbq,
            'CY_KSDM' => $this->value($row, ['CY_KSDM']),
            'CY_KSMC' => $this->value($row, ['CY_KSMC']),
            'ZYCS' => $this->value($row, ['ZYCS', 'RYCS']),
            'ZY_BQDM' => $brbq,
            'ZY_BQMC' => $this->value($row, ['ZY_BQMC'], $this->mapValue($dep, $brbq)),
        ];

        $bedNo = $this->value($row, ['CH', 'BRCH']);
        if ($bedNo !== '') {
            $update['CH'] = $bedNo;
        }

        return $update;
    }

    /**
     * 根据配置自动审核病例。
     *
     * @param string $zyh
     * @param array $update
     * @param array $context
     * @return bool
     */
    private function applyAutoReview($zyh, array &$update, array $context)
    {
        if ((string)$context['auto_review_keyword'] !== '1') {
            return false;
        }

        $scoreThreshold = $context['auto_review_score_threshold'];
        $scoreThreshold = $scoreThreshold === null || $scoreThreshold === '' ? 0 : $scoreThreshold;
        $patientInfo = PatientInfo::query()
            ->where('MED_REC_ID', '=', $zyh)
            ->first(['score', 'home_ysz_score']);

        $score = $patientInfo->score ?? null;
        $homeYszScore = $patientInfo->home_ysz_score ?? null;
        $canReview = !($score < $scoreThreshold || $homeYszScore < $scoreThreshold);

        if ($canReview && (string)$context['force_level_check'] === '1') {
            $canReview = $this->canAutoReviewWithoutForceDefect($zyh);
        }

        if (!$canReview) {
            return false;
        }

        $update['review_status'] = 2;
        $update['review_user'] = $context['default_quality_user_id'];
        $update['review_time'] = date('Y-m-d H:i:s');

        foreach ($context['users'] as $user) {
            $depIds = json_decode($user['dep_id'], true);
            if (!empty($depIds) && !empty($update['BRKS']) && in_array($update['BRKS'], $depIds) && (int)$user['department_review'] === 1) {
                $update['review_user'] = $user['id'];
            }
        }

        return true;
    }

    /**
     * 判断病例是否不存在强制缺陷。
     *
     * @param string $zyh
     * @return bool
     */
    private function canAutoReviewWithoutForceDefect($zyh)
    {
        if (!PatientInfoV2::query()->where('ZYH', '=', $zyh)->exists()) {
            return false;
        }

        $hasCaseRuleForceDefect = CaseQuality::query()
            ->where('JZHM', '=', $zyh)
            ->leftJoin('case_rule', 'case_quality.rule_id', '=', 'case_rule.id')
            ->where('case_rule.level', '=', '1')
            ->exists();
        if ($hasCaseRuleForceDefect) {
            return false;
        }

        $hasErrorV2ForceDefect = ErrorV2::query()
            ->where('ZYH', '=', $zyh)
            ->leftJoin('error_rule', 'error_v2.error_rule', '=', 'error_rule.id')
            ->where('error_rule.level', '=', '0')
            ->exists();
        if ($hasErrorV2ForceDefect) {
            return false;
        }

        return !CaseQuality::query()
            ->where('JZHM', '=', $zyh)
            ->leftJoin('rule_setting', function ($join) {
                $join->on(DB::raw('case_quality.rule_id'), '=', DB::raw('rule_setting.id + 1000000'));
            })
            ->where('rule_setting.error_level', '=', '1')
            ->exists();
    }

    /**
     * 组装patient_info更新数据。
     *
     * @param string $zyh
     * @param array $row
     * @param array $update
     * @param array $context
     * @return array
     */
    private function buildPatientInfoData($zyh, array $row, array $update, array $context)
    {
        $patientInfo = [
            'hospital_name' => $context['hospital_name'],
            'MED_REC_ID' => $zyh,
            'AAA28' => $update['AAA28'] ?? '',
            'AAA29' => $update['ZYCS'] ?? 0,
            'AAB01' => $update['AAB01'] ?? '',
            'AAC01' => $update['AAC01'] ?? '',
            'AAC11N' => $update['ZY_KSMC'] ?? '',
            'in_hospital' => $this->isEmptyTime($update['AAC01'] ?? '') ? 1 : 2,
            'AAA01' => !empty($update['BRXM']) ? desensitize($update['BRXM'], 1, 1, '*') : '',
        ];

        if (!empty($update['CH'])) {
            $patientInfo['CWH'] = $update['CH'];
        }

        if (env('APP_NAME') === 'dancheng') {
            $patientInfo['PATIENT_ID'] = $this->value($row, ['PATIENT_ID']);
        }

        return $patientInfo;
    }

    /**
     * 按预警场景执行对应质控规则。
     *
     * @param string $zyh
     * @param array $patientInfo
     * @param array $context
     * @param string $scene
     * @return void
     */
    private function runQualityRules($zyh, array $patientInfo, array $context, $scene)
    {
        $qualityRules = self::SCENES[$scene]['quality_rules'] ?? [];
        if (empty($qualityRules)) {
            return;
        }

        $this->syncHomeDataForQuality($zyh, $context);

        foreach ($qualityRules as $qualityRule) {
            if ($qualityRule === 'admission_first_course_8h') {
                $this->checkAdmissionFirstCourse8h($zyh, $patientInfo, $context);
            } else if ($qualityRule === 'admission_record_or_24h') {
                $this->checkAdmissionRecordOr24h($zyh, $patientInfo, $context);
            } else if ($qualityRule === 'admission_superior_first_round_48h') {
                $this->checkAdmissionSuperiorFirstRound48h($zyh, $patientInfo, $context);
            } else if ($qualityRule === 'discharge_record_24h') {
                $this->checkDischargeRecord24h($zyh, $patientInfo, $context);
            } else if ($qualityRule === 'discharge_home_page_24h') {
                $this->checkDischargeHomePage24h($zyh, $patientInfo, $context);
            } else if ($qualityRule === 'death_record_24h') {
                $this->checkDeathRecord24h($zyh, $patientInfo, $context);
            } else if ($qualityRule === 'discharge_24h_admission_discharge_record') {
                $this->checkDischarge24hAdmissionDischargeRecord($zyh, $patientInfo, $context);
            } else if ($qualityRule === 'death_24h_admission_death_record') {
                $this->checkDeath24hAdmissionDeathRecord($zyh, $patientInfo, $context);
            } else if ($qualityRule === 'discharge_superior_round_day') {
                $this->checkDischargeSuperiorRoundDay($zyh, $patientInfo, $context);
            } else if ($qualityRule === 'death_home_page_168h') {
                $this->checkDeathHomePage168h($zyh, $patientInfo, $context);
            } else if ($qualityRule === 'critically_ill_superior_round_daily') {
                $this->checkCriticallyIllSuperiorRoundDaily($zyh, $patientInfo, $context);
            } else if ($qualityRule === 'seriously_ill_superior_round_2d') {
                $this->checkSeriouslyIllSuperiorRoundEvery2Days($zyh, $patientInfo, $context);
            } else if ($qualityRule === 'stable_round_3d') {
                $this->checkStableRoundEvery3Days($zyh, $patientInfo, $context);
            }
        }
    }

    /**
     * 同步在院患者上级医师周期查房预警。
     *
     * @return array
     */
    private function syncSuperiorRoundCycleWarnings()
    {
        $context = $this->buildSyncContext(true);
        $limit = (int)ShizhongWarningConfig::getValue('superior_round_cycle_limit', '1000');
        $limit = $limit > 0 ? $limit : 1000;
        $patients = PatientInfo::query()
            ->where('in_hospital', '=', 1)
            ->whereNotNull('MED_REC_ID')
            ->limit($limit)
            ->get(['MED_REC_ID', 'AAA28', 'AAB01', 'AAC01', 'AAA01'])
            ->toArray();

        $this->resetSyncStats(count($patients), count($patients), 0, 0);
        $this->syncStats['summary_unit'] = '个在院住院号';
        $this->line('  周期查房预警：本次检查在院患者 ' . count($patients) . ' 个');

        foreach ($patients as $patientInfo) {
            $zyh = (string)($patientInfo['MED_REC_ID'] ?? '');
            if ($zyh === '') {
                $this->syncStats['skipped']++;
                continue;
            }

            try {
                $this->syncStats['processed']++;
                $this->syncHomeDataForQuality($zyh, $context);
                $this->checkCriticallyIllSuperiorRoundDaily($zyh, $patientInfo, $context);
                $this->checkSeriouslyIllSuperiorRoundEvery2Days($zyh, $patientInfo, $context);
                $this->checkStableRoundEvery3Days($zyh, $patientInfo, $context);
            } catch (\Throwable $e) {
                $this->syncStats['errors']++;
                $this->addSyncDetail('error_detail', '住院号' . $zyh . '：周期查房预警失败，' . $e->getMessage());
                Log::error('shizhong_warning_sync::周期查房预警单条失败', [
                    'zyh' => $zyh,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        return $this->syncStats;
    }

    /**
     * 病危患者每天1次上级医师查房。
     *
     * @param string $zyh
     * @param array $patientInfo
     * @param array $context
     * @param bool $isRecheck
     * @param string $dataId
     * @param string $dataType
     * @return void
     */
    private function checkCriticallyIllSuperiorRoundDaily($zyh, array $patientInfo, array $context, $isRecheck = false, $dataId = '', $dataType = '')
    {
        $this->checkSuperiorRoundCycleRule(
            $zyh,
            $patientInfo,
            $context,
            1049,
            '病危患者上级医师查房',
            1,
            $this->getMedicalAdvicePeriods($zyh, $this->getRuleWordMapList(8015, '病危')),
            true,
            true,
            $isRecheck,
            $dataId,
            $dataType
        );
    }

    /**
     * 病重患者每2天1次上级医师查房。
     *
     * @param string $zyh
     * @param array $patientInfo
     * @param array $context
     * @param bool $isRecheck
     * @param string $dataId
     * @param string $dataType
     * @return void
     */
    private function checkSeriouslyIllSuperiorRoundEvery2Days($zyh, array $patientInfo, array $context, $isRecheck = false, $dataId = '', $dataType = '')
    {
        $this->checkSuperiorRoundCycleRule(
            $zyh,
            $patientInfo,
            $context,
            1050,
            '病重患者上级医师查房',
            2,
            $this->getMedicalAdvicePeriods($zyh, $this->getRuleWordMapList(8014, '病重')),
            false,
            true,
            $isRecheck,
            $dataId,
            $dataType
        );
    }

    /**
     * 病情稳定患者至少3天1次查房。
     *
     * @param string $zyh
     * @param array $patientInfo
     * @param array $context
     * @param bool $isRecheck
     * @param string $dataId
     * @param string $dataType
     * @return void
     */
    private function checkStableRoundEvery3Days($zyh, array $patientInfo, array $context, $isRecheck = false, $dataId = '', $dataType = '')
    {
        $ruleId = 132;
        if (empty($context['case_rule_map'][$ruleId])) {
            return;
        }

        if ($this->hasMedicalAdviceByKeywords($zyh, array_merge($this->getRuleWordMapList(8014, '病重'), $this->getRuleWordMapList(8015, '病危')))) {
            return;
        }

        $admissionTime = $this->getAdmissionTime($zyh, $patientInfo);
        if ($admissionTime === '') {
            return;
        }

        $dischargeTime = $this->getDischargeTime($zyh, $patientInfo);
        $endTime = $dischargeTime !== '' ? $dischargeTime : date('Y-m-d H:i:s');
        $periods = [[
            'start_time' => $admissionTime,
            'end_time' => $endTime,
            'order_name' => '病情稳定',
        ]];

        $this->checkSuperiorRoundCycleRule(
            $zyh,
            $patientInfo,
            $context,
            $ruleId,
            '病情稳定患者查房',
            3,
            $periods,
            false,
            false,
            $isRecheck,
            $dataId,
            $dataType,
            true
        );
    }

    /**
     * 检查周期查房预警。
     *
     * @param string $zyh
     * @param array $patientInfo
     * @param array $context
     * @param int $ruleId
     * @param string $documentName
     * @param int $cycleDays
     * @param array $periods
     * @param bool $skipStartAndEndDate
     * @param bool $requireSuperiorTitle
     * @param bool $isRecheck
     * @param string $dataId
     * @param string $dataType
     * @param bool $skipIncompleteLastCycle
     * @return void
     */
    private function checkSuperiorRoundCycleRule($zyh, array $patientInfo, array $context, $ruleId, $documentName, $cycleDays, array $periods, $skipStartAndEndDate, $requireSuperiorTitle, $isRecheck = false, $dataId = '', $dataType = '', $skipIncompleteLastCycle = false)
    {
        if (empty($context['case_rule_map'][$ruleId])) {
            return;
        }

        if (empty($periods)) {
            return;
        }

        $config = $this->getSuperiorRoundConfig();
        $warningWindowSeconds = (int)ShizhongWarningConfig::getValue('superior_round_warning_window_hours', '2') * 3600;
        $warningWindowSeconds = $warningWindowSeconds > 0 ? $warningWindowSeconds : 2 * 3600;

        foreach ($periods as $period) {
            $cycles = $this->buildSuperiorRoundCycles($period, $cycleDays, $skipStartAndEndDate, $skipIncompleteLastCycle);
            foreach ($cycles as $cycle) {
                $cycleDataId = $this->buildSuperiorRoundDataId($ruleId, $zyh, $period, $cycle);
                $cycleDataType = 'superior_round';
                if ($isRecheck && $dataId !== '' && $cycleDataId !== $dataId) {
                    continue;
                }

                $remainingSeconds = $cycle['deadline'] - time();
                if (!$isRecheck && ($remainingSeconds <= 0 || $remainingSeconds > $warningWindowSeconds)) {
                    continue;
                }

                if ($this->hasDeathAdviceInPeriod($zyh, $cycle['start_time'], $cycle['end_time'])) {
                    continue;
                }

                $status = $this->getSuperiorRoundCycleStatus($zyh, $cycle, $config, $requireSuperiorTitle);
                if ($status['resolved']) {
                    $this->setWarningResolved($zyh, $ruleId, $status['effective'], $cycleDataId, $cycleDataType);
                    return;
                }

                $messageBasis = $this->buildSuperiorRoundMessageBasis($period, $cycle, $status, $documentName);
                if ($isRecheck) {
                    $this->recordPendingWarningUnresolved($zyh, $ruleId, $documentName, $messageBasis);
                    return;
                }

                $this->sendDeadlineWarning($context, $zyh, $ruleId, $cycle['deadline'], $documentName, $messageBasis, $cycleDataId, $cycleDataType);
            }
        }
    }

    /**
     * 获取周期查房配置。
     *
     * @return array
     */
    private function getSuperiorRoundConfig()
    {
        $timeField = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $signTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');

        return [
            'record_template_types' => $this->getRuleWordMapList(8049, '50,296'),
            'time_field' => !empty($timeField) ? $timeField : 'ZXSJ',
            'sign_time_field' => !empty($signTimeField) ? $signTimeField : 'first_blsy_time',
            'superior_title_keywords' => $this->getRuleWordMapList(8012, '主治,主任'),
            'exclude_title_keywords' => $this->getRuleWordMapList(8050, '操作记录'),
        ];
    }

    /**
     * 查询指定关键词医嘱周期。
     *
     * @param string $zyh
     * @param array $keywords
     * @return array
     */
    private function getMedicalAdvicePeriods($zyh, array $keywords)
    {
        if (empty($keywords)) {
            return [];
        }

        $query = Yzb::query()->where('ZYH', '=', $zyh)->where(function ($query) use ($keywords) {
            foreach ($keywords as $keyword) {
                if ($keyword !== '') {
                    $query->orWhere('YZMC', 'like', '%' . $keyword . '%');
                }
            }
        });

        $advices = $query->get(['YZMC', 'KZSJ', 'TZSJ'])->toArray();
        $periods = [];
        foreach ($advices as $advice) {
            $startTime = (string)($advice['KZSJ'] ?? '');
            if ($startTime === '' || strtotime($startTime) === false) {
                continue;
            }

            $endTime = (string)($advice['TZSJ'] ?? '');
            $hasStopTime = !($endTime === '' || $this->isEmptyTime($endTime) || strtotime($endTime) === false);
            if (!$hasStopTime) {
                $endTime = date('Y-m-d H:i:s');
            }

            $periods[] = [
                'start_time' => $startTime,
                'end_time' => $endTime,
                'order_name' => (string)($advice['YZMC'] ?? ''),
                'has_stop_time' => $hasStopTime,
            ];
        }

        return $periods;
    }

    /**
     * 判断是否存在指定关键词医嘱。
     *
     * @param string $zyh
     * @param array $keywords
     * @return bool
     */
    private function hasMedicalAdviceByKeywords($zyh, array $keywords)
    {
        if (empty($keywords)) {
            return false;
        }

        return Yzb::query()->where('ZYH', '=', $zyh)->where(function ($query) use ($keywords) {
            foreach ($keywords as $keyword) {
                if ($keyword !== '') {
                    $query->orWhere('YZMC', 'like', '%' . $keyword . '%');
                }
            }
        })->exists();
    }

    /**
     * 生成周期查房检查窗口。
     *
     * @param array $period
     * @param int $cycleDays
     * @param bool $skipStartAndEndDate
     * @param bool $skipIncompleteLastCycle
     * @return array
     */
    private function buildSuperiorRoundCycles(array $period, $cycleDays, $skipStartAndEndDate, $skipIncompleteLastCycle)
    {
        $periodStart = strtotime($period['start_time'] ?? '');
        $periodEnd = strtotime($period['end_time'] ?? '');
        if ($periodStart === false || $periodEnd === false || $periodEnd < $periodStart) {
            return [];
        }

        $startDate = date('Y-m-d', $periodStart);
        $endDate = date('Y-m-d', $periodEnd);
        if ($skipStartAndEndDate) {
            $startDate = date('Y-m-d', strtotime($startDate . ' +1 day'));
            if (!empty($period['has_stop_time'])) {
                $endDate = date('Y-m-d', strtotime($endDate . ' -1 day'));
            }
        }

        $startDay = strtotime($startDate . ' 00:00:00');
        $endDay = strtotime($endDate . ' 23:59:59');
        if ($startDay === false || $endDay === false || $endDay < $startDay) {
            return [];
        }

        $totalDays = (int)((strtotime($endDate) - strtotime($startDate)) / (24 * 3600)) + 1;
        if ($totalDays < $cycleDays) {
            return [];
        }

        $cycleCount = $skipIncompleteLastCycle ? (int)floor($totalDays / $cycleDays) : (int)ceil($totalDays / $cycleDays);
        $cycles = [];
        for ($i = 0; $i < $cycleCount; $i++) {
            $cycleStart = strtotime($startDate . ' +' . ($i * $cycleDays) . ' days');
            $cycleEndDate = date('Y-m-d', strtotime(date('Y-m-d', $cycleStart) . ' +' . ($cycleDays - 1) . ' days'));
            $cycleEnd = min(strtotime($cycleEndDate . ' 23:59:59'), $endDay);
            if ($cycleStart === false || $cycleEnd === false || $cycleEnd < $cycleStart) {
                continue;
            }

            $cycles[] = [
                'start_time' => date('Y-m-d 00:00:00', $cycleStart),
                'end_time' => date('Y-m-d H:i:s', $cycleEnd),
                'deadline' => $cycleEnd,
            ];
        }

        return $cycles;
    }

    /**
     * 判断周期内是否有死亡医嘱。
     *
     * @param string $zyh
     * @param string $startTime
     * @param string $endTime
     * @return bool
     */
    private function hasDeathAdviceInPeriod($zyh, $startTime, $endTime)
    {
        $keywords = $this->getRuleWordMapList(8003, '死亡');
        if (empty($keywords)) {
            return false;
        }

        return Yzb::query()
            ->where('ZYH', '=', $zyh)
            ->where('KZSJ', '>=', $startTime)
            ->where('KZSJ', '<=', $endTime)
            ->where(function ($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    if ($keyword !== '') {
                        $query->orWhere('YZMC', 'like', '%' . $keyword . '%');
                    }
                }
            })
            ->exists();
    }

    /**
     * 判断周期查房是否完成。
     *
     * @param string $zyh
     * @param array $cycle
     * @param array $config
     * @param bool $requireSuperiorTitle
     * @return array
     */
    private function getSuperiorRoundCycleStatus($zyh, array $cycle, array $config, $requireSuperiorTitle)
    {
        $timeField = $config['time_field'];
        $signTimeField = $config['sign_time_field'];
        $records = EMR_BL_BL01::query()
            ->where('JZHM', '=', $zyh)
            ->whereIn('MBLB', $config['record_template_types'])
            ->where($timeField, '>=', $cycle['start_time'])
            ->where($timeField, '<=', $cycle['end_time'])
            //->where('BLZT', '<>', 9)
            ->orderBy($timeField, 'asc')
            ->get();

        $hasMatchedRecord = false;
        $hasUnsignedRecord = false;
        $lateFinishTime = '';
        $blbh = '';
        foreach ($records as $record) {
            $recordName = (string)($record->BLMC ?? '');
            if ($this->textContainsAnyKeyword($recordName, $config['exclude_title_keywords'])) {
                continue;
            }

            if ($requireSuperiorTitle && !$this->textContainsAnyKeyword($recordName, $config['superior_title_keywords'])) {
                continue;
            }

            $hasMatchedRecord = true;
            if ($blbh === '') {
                $blbh = (string)($record->BLBH ?? '');
            }

            $signTime = (string)($record->{$signTimeField} ?? '');
            $signTimestamp = strtotime($signTime);
            if ($signTime === '' || $signTimestamp === false) {
                $hasUnsignedRecord = true;
                continue;
            }

            if ($signTimestamp <= $cycle['deadline'] && $signTimestamp >= strtotime($cycle['start_time'])) {
                return ['resolved' => true, 'effective' => 1, 'reason' => '', 'blbh' => $blbh];
            }

            if ($signTimestamp > $cycle['deadline'] && $lateFinishTime === '') {
                $lateFinishTime = $signTime;
            }
        }

        if ($lateFinishTime !== '') {
            return ['resolved' => true, 'effective' => 0, 'reason' => 'late', 'late_finish_time' => $lateFinishTime, 'blbh' => $blbh];
        }

        if ($hasUnsignedRecord) {
            return ['resolved' => false, 'reason' => 'unsigned', 'blbh' => $blbh];
        }

        if (!$hasMatchedRecord) {
            return ['resolved' => false, 'reason' => 'missing', 'blbh' => $blbh];
        }

        return ['resolved' => false, 'reason' => 'unfinished', 'blbh' => $blbh];
    }

    /**
     * 生成周期查房预警ID。
     *
     * @param int $ruleId
     * @param string $zyh
     * @param array $period
     * @param array $cycle
     * @return string
     */
    private function buildSuperiorRoundDataId($ruleId, $zyh, array $period, array $cycle)
    {
        $raw = implode(':', [
            $ruleId,
            $zyh,
            strtotime($period['start_time'] ?? ''),
            strtotime($cycle['start_time'] ?? ''),
            strtotime($cycle['end_time'] ?? ''),
        ]);

        return 'round:' . $ruleId . ':' . substr(md5($raw), 0, 24);
    }

    /**
     * 构建周期查房预警依据。
     *
     * @param array $period
     * @param array $cycle
     * @param array $status
     * @param string $documentName
     * @return array
     */
    private function buildSuperiorRoundMessageBasis(array $period, array $cycle, array $status, $documentName)
    {
        $messageBasis = [
            '医嘱【' . (string)($period['order_name'] ?? '') . '】',
            '查房周期【' . $cycle['start_time'] . ' 至 ' . $cycle['end_time'] . '】',
        ];

        if (($status['reason'] ?? '') === 'unsigned') {
            $messageBasis[] = $documentName . '【已创建，未签名】';
        } else if (($status['reason'] ?? '') === 'late') {
            $messageBasis[] = $documentName . '首次签名时间【' . ($status['late_finish_time'] ?? '') . '（超时）】';
        } else {
            $messageBasis[] = $documentName . '【未体现】';
        }

        if (!empty($status['blbh'])) {
            //$messageBasis['BLBH'] = $status['blbh'];
        }

        return $messageBasis;
    }

    /**
     * 同步在院患者阶段小结30天周期预警。
     *
     * @return array
     */
    private function syncStageSummary30dWarnings()
    {
        $context = $this->buildSyncContext(true);
        $limit = (int)ShizhongWarningConfig::getValue('stage_summary_30d_limit', '1000');
        $limit = $limit > 0 ? $limit : 1000;
        $patients = PatientInfo::query()
            ->where('in_hospital', '=', 1)
            ->whereNotNull('MED_REC_ID')
            ->limit($limit)
            ->get(['MED_REC_ID', 'AAA28', 'AAB01', 'AAC01', 'AAA01'])
            ->toArray();

        $this->resetSyncStats(count($patients), count($patients), 0, 0);
        $this->syncStats['summary_unit'] = '个在院住院号';
        $this->line('  阶段小结30天周期预警：本次检查在院患者 ' . count($patients) . ' 个');

        foreach ($patients as $patientInfo) {
            $zyh = (string)($patientInfo['MED_REC_ID'] ?? '');
            if ($zyh === '') {
                $this->syncStats['skipped']++;
                continue;
            }

            try {
                $this->syncStats['processed']++;
                $this->syncHomeDataForQuality($zyh, $context);
                $this->checkStageSummaryRecord30d($zyh, $patientInfo, $context);
            } catch (\Throwable $e) {
                $this->syncStats['errors']++;
                $this->addSyncDetail('error_detail', '住院号' . $zyh . '：阶段小结预警失败，' . $e->getMessage());
                Log::error('shizhong_warning_sync::阶段小结单条预警失败', [
                    'zyh' => $zyh,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        return $this->syncStats;
    }

    /**
     * 检查30天周期阶段小结是否及时完成。
     *
     * @param string $zyh
     * @param array $patientInfo
     * @param array $context
     * @param bool $isRecheck
     * @param string $dataId
     * @param string $dataType
     * @return void
     */
    private function checkStageSummaryRecord30d($zyh, array $patientInfo, array $context, $isRecheck = false, $dataId = '', $dataType = '')
    {
        $ruleId = 1013;
        if (empty($context['case_rule_map'][$ruleId])) {
            return;
        }

        $admissionTime = $this->getAdmissionTime($zyh, $patientInfo);
        if ($admissionTime === '') {
            return;
        }

        $dischargeTime = $this->getDischargeTime($zyh, $patientInfo);
        $endTime = $dischargeTime !== '' ? $dischargeTime : date('Y-m-d H:i:s');
        $cycles = $this->buildStageSummaryCycles($admissionTime, $endTime);
        if (empty($cycles)) {
            return;
        }

        $config = $this->getStageSummaryConfig();
        $warningWindowSeconds = (int)ShizhongWarningConfig::getValue('stage_summary_warning_window_hours', '2') * 3600;
        $warningWindowSeconds = $warningWindowSeconds > 0 ? $warningWindowSeconds : 2 * 3600;

        foreach ($cycles as $cycle) {
            $cycleDataId = $this->buildStageSummaryDataId($zyh, $cycle);
            $cycleDataType = 'stage_summary_cycle';
            if ($isRecheck && $dataId !== '' && $cycleDataId !== $dataId) {
                continue;
            }

            $remainingSeconds = $cycle['deadline'] - time();
            if (!$isRecheck && ($remainingSeconds <= 0 || $remainingSeconds > $warningWindowSeconds)) {
                continue;
            }

            if ($this->hasTransferRecordForStageSummaryCycle($zyh, $cycle, $config)) {
                $this->setWarningResolved($zyh, $ruleId, 1, $cycleDataId, $cycleDataType);
                continue;
            }

            $status = $this->getStageSummaryCycleStatus($zyh, $cycle, $config);
            if ($status['resolved']) {
                $this->setWarningResolved($zyh, $ruleId, $status['effective'], $cycleDataId, $cycleDataType);
                continue;
            }

            $messageBasis = $this->buildStageSummaryMessageBasis($cycle, $status);
            if ($isRecheck) {
                $this->recordPendingWarningUnresolved($zyh, $ruleId, '阶段小结', $messageBasis);
                return;
            }

            $this->sendDeadlineWarning($context, $zyh, $ruleId, $cycle['deadline'], '阶段小结', $messageBasis, $cycleDataId, $cycleDataType);
        }
    }

    /**
     * 构建阶段小结30天周期。
     *
     * @param string $admissionTime
     * @param string $endTime
     * @return array
     */
    private function buildStageSummaryCycles($admissionTime, $endTime)
    {
        $admissionTimestamp = strtotime($admissionTime);
        $endTimestamp = strtotime($endTime);
        if ($admissionTimestamp === false || $endTimestamp === false || $endTimestamp <= $admissionTimestamp) {
            return [];
        }

        $hospitalDays = (int)floor(($endTimestamp - $admissionTimestamp) / (24 * 3600));
        if ($hospitalDays < 30) {
            return [];
        }

        $cycleCount = (int)floor($hospitalDays / 30);
        $cycles = [];
        for ($i = 1; $i <= $cycleCount; $i++) {
            $cycleStart = strtotime('+' . (($i - 1) * 30) . ' days', $admissionTimestamp);
            $cycleEnd = strtotime('+' . ($i * 30) . ' days', $admissionTimestamp);
            if ($cycleStart === false || $cycleEnd === false || $cycleEnd > $endTimestamp) {
                continue;
            }

            $deadline = $cycleEnd + 72 * 3600;
            $cycles[] = [
                'cycle_no' => $i,
                'start_time' => date('Y-m-d H:i:s', $cycleStart),
                'end_time' => date('Y-m-d H:i:s', $cycleEnd),
                'window_start' => date('Y-m-d H:i:s', $cycleEnd - 72 * 3600),
                'window_end' => date('Y-m-d H:i:s', $deadline),
                'deadline' => $deadline,
            ];
        }

        return $cycles;
    }

    /**
     * 获取阶段小结规则配置。
     *
     * @return array
     */
    private function getStageSummaryConfig()
    {
        $timeField = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $signTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');

        return [
            'stage_summary_types' => $this->getRuleWordMapList(8032, '26'),
            'transfer_record_types' => $this->getRuleWordMapList(8033, '30'),
            'transfer_advice_keywords' => $this->getRuleWordMapList(8034, '转科'),
            'time_field' => !empty($timeField) ? $timeField : 'ZXSJ',
            'sign_time_field' => !empty($signTimeField) ? $signTimeField : 'first_blsy_time',
        ];
    }

    /**
     * 判断周期内是否存在可替代阶段小结的转科记录。
     *
     * @param string $zyh
     * @param array $cycle
     * @param array $config
     * @return bool
     */
    private function hasTransferRecordForStageSummaryCycle($zyh, array $cycle, array $config)
    {
        $cycleEndTimestamp = strtotime($cycle['end_time'] ?? '');
        if ($cycleEndTimestamp === false || empty($config['transfer_advice_keywords']) || empty($config['transfer_record_types'])) {
            return false;
        }

        $adviceStartTime = date('Y-m-d H:i:s', $cycleEndTimestamp - 72 * 3600);
        $advices = Yzb::query()
            ->where('ZYH', '=', $zyh)
            ->where('KZSJ', '>=', $adviceStartTime)
            ->where('KZSJ', '<=', $cycle['end_time'])
            ->where(function ($query) use ($config) {
                foreach ($config['transfer_advice_keywords'] as $keyword) {
                    if ($keyword !== '') {
                        $query->orWhere('YZMC', 'like', '%' . $keyword . '%');
                    }
                }
            })
            ->get(['KZSJ'])
            ->toArray();

        foreach ($advices as $advice) {
            $transferTime = (string)($advice['KZSJ'] ?? '');
            $transferTimestamp = strtotime($transferTime);
            if ($transferTimestamp === false) {
                continue;
            }

            $transferRecordStart = date('Y-m-d H:i:s', $transferTimestamp - 72 * 3600);
            if (EMR_BL_BL01::query()
                ->where('JZHM', '=', $zyh)
                ->whereIn('MBLB', $config['transfer_record_types'])
                ->where($config['time_field'], '>=', $transferRecordStart)
                ->where($config['time_field'], '<=', $transferTime)
                //->where('BLZT', '<>', 9)
                ->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判断阶段小结周期是否完成。
     *
     * @param string $zyh
     * @param array $cycle
     * @param array $config
     * @return array
     */
    private function getStageSummaryCycleStatus($zyh, array $cycle, array $config)
    {
        $records = EMR_BL_BL01::query()
            ->where('JZHM', '=', $zyh)
            ->whereIn('MBLB', $config['stage_summary_types'])
            ->where($config['time_field'], '>=', $cycle['window_start'])
            ->where($config['time_field'], '<=', $cycle['window_end'])
            //->where('BLZT', '<>', 9)
            ->orderBy($config['time_field'], 'asc')
            ->get();

        $hasUnsignedRecord = false;
        $earlyFinishTime = '';
        $lateFinishTime = '';
        $blbh = '';
        $windowStart = strtotime($cycle['window_start']);
        $deadline = (int)$cycle['deadline'];

        foreach ($records as $record) {
            if ($blbh === '') {
                $blbh = (string)($record->BLBH ?? '');
            }

            $signTime = (string)($record->{$config['sign_time_field']} ?? '');
            $signTimestamp = strtotime($signTime);
            if ($signTime === '' || $signTimestamp === false) {
                $hasUnsignedRecord = true;
                continue;
            }

            if ($signTimestamp >= $windowStart && $signTimestamp <= $deadline) {
                return ['resolved' => true, 'effective' => 1, 'reason' => '', 'blbh' => $blbh];
            }

            if ($signTimestamp < $windowStart && $earlyFinishTime === '') {
                $earlyFinishTime = $signTime;
            }

            if ($signTimestamp > $deadline && $lateFinishTime === '') {
                $lateFinishTime = $signTime;
            }
        }

        if ($lateFinishTime !== '') {
            return ['resolved' => true, 'effective' => 0, 'reason' => 'late', 'late_finish_time' => $lateFinishTime, 'blbh' => $blbh];
        }

        if ($hasUnsignedRecord) {
            return ['resolved' => false, 'reason' => 'unsigned', 'blbh' => $blbh];
        }

        if ($earlyFinishTime !== '') {
            return ['resolved' => false, 'reason' => 'early', 'early_finish_time' => $earlyFinishTime, 'blbh' => $blbh];
        }

        return ['resolved' => false, 'reason' => 'missing', 'blbh' => $blbh];
    }

    /**
     * 生成阶段小结周期预警ID。
     *
     * @param string $zyh
     * @param array $cycle
     * @return string
     */
    private function buildStageSummaryDataId($zyh, array $cycle)
    {
        return substr('stage:' . $zyh . ':' . (string)($cycle['cycle_no'] ?? '') . ':' . strtotime($cycle['end_time'] ?? ''), 0, 100);
    }

    /**
     * 构建阶段小结预警依据。
     *
     * @param array $cycle
     * @param array $status
     * @return array
     */
    private function buildStageSummaryMessageBasis(array $cycle, array $status)
    {
        $messageBasis = [
            '阶段周期【第' . (string)($cycle['cycle_no'] ?? '') . '个30天周期】',
            '周期结束时间【' . $cycle['end_time'] . '】',
            '允许完成时间【' . $cycle['window_start'] . ' 至 ' . $cycle['window_end'] . '】',
        ];

        if (($status['reason'] ?? '') === 'unsigned') {
            $messageBasis[] = '阶段小结【已创建，未签名】';
        } else if (($status['reason'] ?? '') === 'early') {
            $messageBasis[] = '阶段小结首次签名时间【' . ($status['early_finish_time'] ?? '') . '（提前）】';
        } else if (($status['reason'] ?? '') === 'late') {
            $messageBasis[] = '阶段小结首次签名时间【' . ($status['late_finish_time'] ?? '') . '（超时）】';
        } else {
            $messageBasis[] = '阶段小结【未创建】';
        }

        if (!empty($status['blbh'])) {
            //$messageBasis['BLBH'] = $status['blbh'];
        }

        return $messageBasis;
    }

    /**
     * 同步转入后24小时转入记录预警。
     *
     * @return array
     */
    private function syncTransferIn24Warnings()
    {
        $context = $this->buildSyncContext(true);
        $startTime = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $endTime = date('Y-m-d H:i:s', strtotime('-22 hours'));
        $transferItems = $this->getTransferInWarningItems($startTime, $endTime);

        $this->resetSyncStats(count($transferItems), count($transferItems), 0, 0);
        $this->syncStats['summary_unit'] = '条转入记录';
        $this->line('  转入记录预警：' . $startTime . ' 至 ' . $endTime . '，转入事件 ' . count($transferItems) . ' 条');

        foreach ($transferItems as $transferItem) {
            $zyh = (string)($transferItem['ZYH'] ?? '');
            if ($zyh === '') {
                $this->syncStats['skipped']++;
                $this->addSyncDetail('skipped_detail', '转入事件缺少住院号，已跳过');
                continue;
            }

            try {
                $this->syncStats['processed']++;
                $this->syncHomeDataForQuality($zyh, $context);
                $this->checkTransferInRecord24h($zyh, $context, $transferItem);
            } catch (\Throwable $e) {
                $this->syncStats['errors']++;
                $this->addSyncDetail('error_detail', '住院号' . $zyh . '：转入记录预警失败，' . $e->getMessage());
                Log::error('shizhong_warning_sync::转入记录单条预警失败', [
                    'zyh' => $zyh,
                    'transfer_item' => $transferItem,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        return $this->syncStats;
    }

    /**
     * 查询转入时间进入22至24小时窗口的转入事件。
     *
     * @param string $startTime
     * @param string $endTime
     * @return array
     */
    private function getTransferInWarningItems($startTime, $endTime)
    {
        $limit = (int)ShizhongWarningConfig::getValue('transfer_in_24_limit', '1000');
        $limit = $limit > 0 ? $limit : 1000;

        $rows = ZY_HCMX::query()
            ->where('HCLX', '=', 2)
            ->where('HCRQ', '>=', $startTime)
            ->where('HCRQ', '<=', $endTime)
            ->whereNotNull('ZYH')
            ->whereNotNull('HCRQ')
            ->orderBy('HCRQ', 'asc')
            ->limit($limit)
            ->get(['ZYH', 'HCRQ', 'HQKS', 'HHKS', 'HQBQ', 'HHBQ', 'HQCH', 'HHCH'])
            ->toArray();

        foreach ($rows as &$row) {
            $transferTime = (string)($row['HCRQ'] ?? '');
            $row['data_id'] = $this->buildTransferInDataId((string)($row['ZYH'] ?? ''), $transferTime);
            $row['data_type'] = 'transfer_in';
        }

        return $rows;
    }

    /**
     * 检查转入后24小时内是否完成转入记录。
     *
     * @param string $zyh
     * @param array $context
     * @param array|null $transferItem
     * @param bool $isRecheck
     * @param string $dataId
     * @param string $dataType
     * @return void
     */
    private function checkTransferInRecord24h($zyh, array $context, array $transferItem = null, $isRecheck = false, $dataId = '', $dataType = '')
    {
        $ruleId = 1015;
        if (empty($context['case_rule_map'][$ruleId])) {
            return;
        }

        if ($transferItem === null) {
            $transferItem = $this->getTransferInByDataId($zyh, $dataId);
        }

        if (empty($transferItem)) {
            return;
        }

        $transferTime = (string)($transferItem['HCRQ'] ?? '');
        if ($transferTime === '' || $this->isEmptyTime($transferTime) || strtotime($transferTime) === false) {
            return;
        }

        $dataId = $dataId !== '' ? $dataId : (string)($transferItem['data_id'] ?? $this->buildTransferInDataId($zyh, $transferTime));
        $dataType = $dataType !== '' ? $dataType : (string)($transferItem['data_type'] ?? 'transfer_in');
        $deadline = strtotime($transferTime) + 24 * 3600;
        $config = $this->getTransferInRecordConfig();
        $status = $this->getTransferInRecordStatus($zyh, $transferTime, $deadline, $config);

        if ($status['resolved']) {
            $this->setWarningResolved($zyh, $ruleId, $status['effective'], $dataId, $dataType);
            return;
        }

        $messageBasis = $this->buildTransferInMessageBasis($transferItem, $status);
        if ($isRecheck) {
            $this->recordPendingWarningUnresolved($zyh, $ruleId, '转入记录', $messageBasis);
            return;
        }

        $this->sendDeadlineWarning($context, $zyh, $ruleId, $deadline, '转入记录', $messageBasis, $dataId, $dataType);
    }

    /**
     * 获取转入记录规则配置。
     *
     * @return array
     */
    private function getTransferInRecordConfig()
    {
        $timeField = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $signTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $recordNameKeyword = RuleWordMap::query()->where('id', '=', 8039)->value('keyword');

        return [
            'record_types' => $this->getRuleWordMapList(8033, '30'),
            'time_field' => !empty($timeField) ? $timeField : 'ZXSJ',
            'sign_time_field' => !empty($signTimeField) ? $signTimeField : 'first_blsy_time',
            'record_name_keyword' => !empty($recordNameKeyword) ? $recordNameKeyword : '转入',
        ];
    }

    /**
     * 判断转入记录是否完成。
     *
     * @param string $zyh
     * @param string $transferTime
     * @param int $deadline
     * @param array $config
     * @return array
     */
    private function getTransferInRecordStatus($zyh, $transferTime, $deadline, array $config)
    {
        $records = EMR_BL_BL01::query()
            ->where('JZHM', '=', $zyh)
            ->whereIn('MBLB', $config['record_types'])
            ->where('BLMC', 'like', '%' . $config['record_name_keyword'] . '%')
            ->where($config['time_field'], '>=', $transferTime)
            ->where($config['time_field'], '<=', $deadline)
            //->where('BLZT', '<>', 9)
            ->orderBy($config['time_field'], 'asc')
            ->get();

        $hasUnsignedRecord = false;
        $blbh = '';
        foreach ($records as $record) {
            if ($blbh === '') {
                $blbh = (string)($record->BLBH ?? '');
            }

            $signTime = $this->getMedicalRecordSignTime($record, $config['sign_time_field']);
            $signTimestamp = strtotime($signTime);
            if ($signTime === '' || $signTimestamp === false) {
                $hasUnsignedRecord = true;
                continue;
            }

            return [
                'resolved' => true,
                'effective' => $signTimestamp <= $deadline ? 1 : 0,
                'reason' => $signTimestamp <= $deadline ? '' : 'late',
                'finish_time' => $signTime,
                'blbh' => (string)($record->BLBH ?? ''),
            ];
        }

        if ($hasUnsignedRecord) {
            return ['resolved' => false, 'reason' => 'unsigned', 'blbh' => $blbh];
        }

        return ['resolved' => false, 'reason' => 'missing', 'blbh' => $blbh];
    }

    /**
     * 根据预警日志中的数据ID还原转入事件。
     *
     * @param string $zyh
     * @param string $dataId
     * @return array|null
     */
    private function getTransferInByDataId($zyh, $dataId)
    {
        $prefix = 'transfer_in:' . $zyh . ':';
        if ($dataId === '' || strpos($dataId, $prefix) !== 0) {
            return null;
        }

        $transferTimestamp = (int)substr($dataId, strlen($prefix));
        if ($transferTimestamp <= 0) {
            return null;
        }

        $transferTime = date('Y-m-d H:i:s', $transferTimestamp);
        $item = ZY_HCMX::query()
            ->where('ZYH', '=', $zyh)
            ->where('HCLX', '=', 2)
            ->where('HCRQ', '=', $transferTime)
            ->first(['ZYH', 'HCRQ', 'HQKS', 'HHKS', 'HQBQ', 'HHBQ', 'HQCH', 'HHCH']);

        if (empty($item)) {
            return null;
        }

        $item = $item->toArray();
        $item['data_id'] = $dataId;
        $item['data_type'] = 'transfer_in';

        return $item;
    }

    /**
     * 生成转入记录预警ID。
     *
     * @param string $zyh
     * @param string $transferTime
     * @return string
     */
    private function buildTransferInDataId($zyh, $transferTime)
    {
        return substr('transfer_in:' . $zyh . ':' . strtotime($transferTime), 0, 100);
    }

    /**
     * 生成转入记录预警依据。
     *
     * @param array $transferItem
     * @param array $status
     * @return array
     */
    private function buildTransferInMessageBasis(array $transferItem, array $status)
    {
        $messageBasis = [
            '转入时间【' . (string)($transferItem['HCRQ'] ?? '') . '】',
        ];

        $fromDepartment = (string)($transferItem['HQKS'] ?? '');
        $toDepartment = (string)($transferItem['HHKS'] ?? '');
        if ($fromDepartment !== '' || $toDepartment !== '') {
            $messageBasis[] = '转科信息【' . $fromDepartment . '转入' . $toDepartment . '】';
        }

        if (($status['reason'] ?? '') === 'unsigned') {
            $messageBasis[] = '转入记录【已创建，未签名】';
        } else {
            $messageBasis[] = '转入记录【未创建】';
        }

        if (!empty($status['blbh'])) {
            $messageBasis[] = '病历编号【' . $status['blbh'] . '】';
        }

        return $messageBasis;
    }

    /**
     * 同步CT报告出具后72小时病程记录预警。
     *
     * @return array
     */
    private function syncCtProgress72Warnings()
    {
        $context = $this->buildSyncContext(true);
        $deadlineHours = $this->getCtProgressDeadlineHours();
        $startTime = date('Y-m-d H:i:s', strtotime('-' . $deadlineHours . ' hours'));
        $endTime = date('Y-m-d H:i:s', strtotime('-' . max($deadlineHours - 2, 1) . ' hours'));
        $pacsItems = $this->getCtProgressWarningItems($startTime, $endTime);

        $this->resetSyncStats(count($pacsItems), count($pacsItems), 0, 0);
        $this->syncStats['summary_unit'] = '条CT报告';
        $this->line('  CT病程记录预警：' . $startTime . ' 至 ' . $endTime . '，CT报告 ' . count($pacsItems) . ' 条');

        foreach ($pacsItems as $pacsItem) {
            $zyh = (string)($pacsItem['ZYH'] ?? '');
            if ($zyh === '') {
                $this->syncStats['skipped']++;
                $this->addSyncDetail('skipped_detail', 'CT报告缺少住院号，已跳过');
                continue;
            }

            try {
                $this->syncStats['processed']++;
                $this->syncHomeDataForQuality($zyh, $context);
                $this->checkCtProgressRecord72h($zyh, $context, $pacsItem);
            } catch (\Throwable $e) {
                $this->syncStats['errors']++;
                $this->addSyncDetail('error_detail', '住院号' . $zyh . '：CT病程记录预警失败，' . $e->getMessage());
                Log::error('shizhong_warning_sync::CT病程记录单条预警失败', [
                    'zyh' => $zyh,
                    'pacs_item' => $pacsItem,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        return $this->syncStats;
    }

    /**
     * 查询报告时间进入70至72小时窗口的CT检查报告。
     *
     * @param string $startTime
     * @param string $endTime
     * @return array
     */
    private function getCtProgressWarningItems($startTime, $endTime)
    {
        $limit = (int)ShizhongWarningConfig::getValue('ct_progress_72_limit', '1000');
        $limit = $limit > 0 ? $limit : 1000;
        $ctKeywords = $this->getRuleWordMapList(26, 'CT');
        $octKeywords = $this->getRuleWordMapList(27, 'OCT');

        $rows = PACS::query()
            ->where('BGSJ', '>=', $startTime)
            ->where('BGSJ', '<=', $endTime)
            ->whereNotNull('ZYH')
            ->whereNotNull('BGSJ')
            ->orderBy('BGSJ', 'asc')
            ->limit($limit)
            ->get(['ZYH', 'JCMC', 'BGSJ'])
            ->toArray();

        $items = [];
        foreach ($rows as $row) {
            if (!$this->isCtRelatedText((string)($row['JCMC'] ?? ''), $ctKeywords, $octKeywords)) {
                continue;
            }

            $reportTime = (string)($row['BGSJ'] ?? '');
            if ($reportTime === '' || $this->isEmptyTime($reportTime) || strtotime($reportTime) === false) {
                continue;
            }

            $row['data_id'] = $this->buildCtProgressDataId((string)($row['ZYH'] ?? ''), (string)($row['JCMC'] ?? ''), $reportTime);
            $row['data_type'] = 'ct_progress';
            $items[] = $row;
        }

        return $items;
    }

    /**
     * 检查CT报告出具后72小时内是否完成相关病程记录。
     *
     * @param string $zyh
     * @param array $context
     * @param array|null $pacsItem
     * @param bool $isRecheck
     * @param string $dataId
     * @param string $dataType
     * @return void
     */
    private function checkCtProgressRecord72h($zyh, array $context, array $pacsItem = null, $isRecheck = false, $dataId = '', $dataType = '')
    {
        $ruleId = 102;
        if (empty($context['case_rule_map'][$ruleId])) {
            return;
        }

        if ($pacsItem === null) {
            $pacsItem = $this->getCtProgressByDataId($zyh, $dataId);
        }

        if (empty($pacsItem)) {
            return;
        }

        $reportTime = (string)($pacsItem['BGSJ'] ?? '');
        if ($reportTime === '' || $this->isEmptyTime($reportTime) || strtotime($reportTime) === false) {
            return;
        }

        $config = $this->getCtProgressConfig();
        if (!$this->isCtRelatedText((string)($pacsItem['JCMC'] ?? ''), $config['ct_keywords'], $config['oct_keywords'])) {
            return;
        }

        $orderInfo = $this->getMatchingCtOrderForPacs($zyh, (string)($pacsItem['JCMC'] ?? ''), $reportTime, $config);
        if (empty($orderInfo)) {
            return;
        }

        $dataId = $dataId !== '' ? $dataId : (string)($pacsItem['data_id'] ?? $this->buildCtProgressDataId($zyh, (string)($pacsItem['JCMC'] ?? ''), $reportTime));
        $dataType = $dataType !== '' ? $dataType : (string)($pacsItem['data_type'] ?? 'ct_progress');
        $deadline = strtotime($reportTime) + $config['deadline_hours'] * 3600;
        $status = $this->getCtProgressRecordStatus($zyh, $reportTime, $deadline, $config);

        if ($status['resolved']) {
            $this->setWarningResolved($zyh, $ruleId, $status['effective'], $dataId, $dataType);
            return;
        }

        $messageBasis = $this->buildCtProgressMessageBasis($pacsItem, $orderInfo, $status, $config);
        if ($isRecheck) {
            $this->recordPendingWarningUnresolved($zyh, $ruleId, 'CT相关病程记录', $messageBasis);
            return;
        }

        $this->sendDeadlineWarning($context, $zyh, $ruleId, $deadline, 'CT相关病程记录', $messageBasis, $dataId, $dataType);
    }

    /**
     * 获取CT病程记录规则配置。
     *
     * @return array
     */
    private function getCtProgressConfig()
    {
        $timeField = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $signTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');

        return [
            'ct_keywords' => $this->getRuleWordMapList(26, 'CT'),
            'oct_keywords' => $this->getRuleWordMapList(27, 'OCT'),
            'record_types' => $this->getRuleWordMapList(8068, '294'),
            'time_field' => !empty($timeField) ? $timeField : 'ZXSJ',
            'sign_time_field' => !empty($signTimeField) ? $signTimeField : 'first_blsy_time',
            'deadline_hours' => $this->getCtProgressDeadlineHours(),
        ];
    }

    /**
     * 获取CT报告后病程记录完成时限。
     *
     * @return int
     */
    private function getCtProgressDeadlineHours()
    {
        $hours = (int)RuleWordMap::query()->where('id', '=', 9010)->value('keyword');
        return $hours > 0 ? $hours : 72;
    }

    /**
     * 判断文本是否命中CT且不命中OCT。
     *
     * @param string $text
     * @param array $ctKeywords
     * @param array $octKeywords
     * @return bool
     */
    private function isCtRelatedText($text, array $ctKeywords, array $octKeywords)
    {
        return $this->textContainsAnyKeyword($text, $ctKeywords) && !$this->textContainsAnyKeyword($text, $octKeywords);
    }

    /**
     * 匹配CT报告对应的CT检查医嘱。
     *
     * @param string $zyh
     * @param string $checkName
     * @param string $reportTime
     * @param array $config
     * @return array|null
     */
    private function getMatchingCtOrderForPacs($zyh, $checkName, $reportTime, array $config)
    {
        $reportTimestamp = strtotime($reportTime);
        if ($reportTimestamp === false) {
            return null;
        }

        $orders = Yzb::query()
            ->where('ZYH', '=', $zyh)
            ->where('KZSJ', '>', date('Y-m-d H:i:s', $reportTimestamp - 48 * 3600))
            ->where('KZSJ', '<', $reportTime)
            ->orderBy('KZSJ', 'asc')
            ->get(['YZMC', 'KZSJ'])
            ->toArray();

        $firstCtOrder = null;
        foreach ($orders as $order) {
            $orderName = (string)($order['YZMC'] ?? '');
            if (!$this->isCtRelatedText($orderName, $config['ct_keywords'], $config['oct_keywords'])) {
                continue;
            }

            if ($firstCtOrder === null) {
                $firstCtOrder = $order;
            }

            $normalizedOrderName = $this->normalizeCheckOrderName($orderName);
            if ($normalizedOrderName !== '' && strpos($checkName, $normalizedOrderName) !== false) {
                return $order;
            }
        }

        return $firstCtOrder;
    }

    /**
     * 规范化检查医嘱名称。
     *
     * @param string $orderName
     * @return string
     */
    private function normalizeCheckOrderName($orderName)
    {
        $orderName = preg_replace('/\（.*?\）/', '', $orderName);
        $orderName = preg_replace('/\(.*?\)/', '', $orderName);
        return trim((string)$orderName);
    }

    /**
     * 判断CT相关病程记录是否完成。
     *
     * @param string $zyh
     * @param string $reportTime
     * @param int $deadline
     * @param array $config
     * @return array
     */
    private function getCtProgressRecordStatus($zyh, $reportTime, $deadline, array $config)
    {
        $records = EMR_BL_BL01::query()
            ->join('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
            ->where('EMR_BL_BL01.JZHM', '=', $zyh)
            ->whereIn('EMR_BL_BL01.BLLB', $config['record_types'])
            ->where('EMR_BL_BL01.' . $config['time_field'], '>', $reportTime)
            ->where(function ($query) use ($config) {
                foreach ($config['ct_keywords'] as $keyword) {
                    if ($keyword !== '') {
                        $query->orWhere('EMR_BL_BLXG.HJNR', 'like', '%' . $keyword . '%');
                    }
                }
            })
            ->where(function ($query) use ($config) {
                foreach ($config['oct_keywords'] as $keyword) {
                    if ($keyword !== '') {
                        $query->where('EMR_BL_BLXG.HJNR', 'not like', '%' . $keyword . '%');
                    }
                }
            })
            //->where('EMR_BL_BL01.BLZT', '<>', 9)
            ->orderBy('EMR_BL_BL01.' . $config['time_field'], 'asc')
            ->get([
                'EMR_BL_BL01.BLBH as BLBH',
                'EMR_BL_BL01.CJSJ as CJSJ',
                'EMR_BL_BL01.ZXSJ as ZXSJ',
                'EMR_BL_BL01.BLMC as BLMC',
                'EMR_BL_BL01.BLLB as BLLB',
                'EMR_BL_BL01.' . $config['sign_time_field'] . ' as ' . $config['sign_time_field'],
                'EMR_BL_BLXG.HJNR as HJNR',
            ]);

        $hasUnsignedRecord = false;
        $lateFinishTime = '';
        $blbh = '';
        foreach ($records as $record) {
            $content = (string)($record->HJNR ?? '');
            if (strpos((string)($record->BLMC ?? ''), '会诊') !== false && strpos($content, '会诊意见给予') !== false) {
                $content = substr($content, 0, strpos($content, '会诊意见给予'));
            }

            if (!$this->isCtRelatedText($content, $config['ct_keywords'], $config['oct_keywords'])) {
                continue;
            }

            if ($blbh === '') {
                $blbh = (string)($record->BLBH ?? '');
            }

            $signTime = $this->getMedicalRecordSignTime($record, $config['sign_time_field']);
            $signTimestamp = strtotime($signTime);
            if ($signTime === '' || $signTimestamp === false) {
                $hasUnsignedRecord = true;
                continue;
            }

            if ($signTimestamp >= strtotime($reportTime) && $signTimestamp <= $deadline) {
                return ['resolved' => true, 'effective' => 1, 'reason' => '', 'finish_time' => $signTime, 'blbh' => $blbh];
            }

            if ($lateFinishTime === '') {
                $lateFinishTime = $signTime;
            }
        }

        if ($lateFinishTime !== '') {
            return ['resolved' => true, 'effective' => 0, 'reason' => 'late', 'late_finish_time' => $lateFinishTime, 'blbh' => $blbh];
        }

        if ($hasUnsignedRecord) {
            return ['resolved' => false, 'reason' => 'unsigned', 'blbh' => $blbh];
        }

        return ['resolved' => false, 'reason' => 'missing', 'blbh' => $blbh];
    }

    /**
     * 根据预警日志中的数据ID还原CT报告事件。
     *
     * @param string $zyh
     * @param string $dataId
     * @return array|null
     */
    private function getCtProgressByDataId($zyh, $dataId)
    {
        $prefix = 'ct_progress:' . $zyh . ':';
        if ($dataId === '' || strpos($dataId, $prefix) !== 0) {
            return null;
        }

        $parts = explode(':', substr($dataId, strlen($prefix)));
        $reportTimestamp = (int)($parts[0] ?? 0);
        $checkHash = (string)($parts[1] ?? '');
        if ($reportTimestamp <= 0 || $checkHash === '') {
            return null;
        }

        $reportTime = date('Y-m-d H:i:s', $reportTimestamp);
        $items = PACS::query()
            ->where('ZYH', '=', $zyh)
            ->where('BGSJ', '=', $reportTime)
            ->get(['ZYH', 'JCMC', 'BGSJ'])
            ->toArray();

        foreach ($items as $item) {
            if (substr(md5((string)($item['JCMC'] ?? '')), 0, 8) !== $checkHash) {
                continue;
            }

            $item['data_id'] = $dataId;
            $item['data_type'] = 'ct_progress';
            return $item;
        }

        return null;
    }

    /**
     * 生成CT报告病程记录预警ID。
     *
     * @param string $zyh
     * @param string $checkName
     * @param string $reportTime
     * @return string
     */
    private function buildCtProgressDataId($zyh, $checkName, $reportTime)
    {
        return substr('ct_progress:' . $zyh . ':' . strtotime($reportTime) . ':' . substr(md5($checkName), 0, 8), 0, 100);
    }

    /**
     * 生成CT报告病程记录预警依据。
     *
     * @param array $pacsItem
     * @param array $orderInfo
     * @param array $status
     * @param array $config
     * @return array
     */
    private function buildCtProgressMessageBasis(array $pacsItem, array $orderInfo, array $status, array $config)
    {
        $reportTime = (string)($pacsItem['BGSJ'] ?? '');
        $deadline = strtotime($reportTime) + $config['deadline_hours'] * 3600;
        $messageBasis = [
            '检查名称【' . (string)($pacsItem['JCMC'] ?? '') . '】',
            '报告时间【' . $reportTime . '】',
            '医嘱名称【' . (string)($orderInfo['YZMC'] ?? '') . '】',
            '开嘱时间【' . (string)($orderInfo['KZSJ'] ?? '') . '】',
            '完成时限【' . date('Y-m-d H:i:s', $deadline) . '】',
        ];

        if (($status['reason'] ?? '') === 'unsigned') {
            $messageBasis[] = 'CT相关病程记录【已创建，未签名】';
        } else {
            $messageBasis[] = '报告出具后' . $config['deadline_hours'] . '小时内的病程记录未记录检查结果';
        }

        if (!empty($status['blbh'])) {
            //$messageBasis['BLBH'] = $status['blbh'];
        }

        return $messageBasis;
    }

    /**
     * 同步MR报告出具后72小时病程记录预警。
     *
     * @return array
     */
    private function syncMrProgress72Warnings()
    {
        $context = $this->buildSyncContext(true);
        $deadlineHours = $this->getMrProgressDeadlineHours();
        $startTime = date('Y-m-d H:i:s', strtotime('-' . $deadlineHours . ' hours'));
        $endTime = date('Y-m-d H:i:s', strtotime('-' . max($deadlineHours - 2, 1) . ' hours'));
        $pacsItems = $this->getMrProgressWarningItems($startTime, $endTime);

        $this->resetSyncStats(count($pacsItems), count($pacsItems), 0, 0);
        $this->syncStats['summary_unit'] = '条MR报告';
        $this->line('  MR病程记录预警：' . $startTime . ' 至 ' . $endTime . '，MR报告 ' . count($pacsItems) . ' 条');

        foreach ($pacsItems as $pacsItem) {
            $zyh = (string)($pacsItem['ZYH'] ?? '');
            if ($zyh === '') {
                $this->syncStats['skipped']++;
                $this->addSyncDetail('skipped_detail', 'MR报告缺少住院号，已跳过');
                continue;
            }

            try {
                $this->syncStats['processed']++;
                $this->syncHomeDataForQuality($zyh, $context);
                $this->checkMrProgressRecord72h($zyh, $context, $pacsItem);
            } catch (\Throwable $e) {
                $this->syncStats['errors']++;
                $this->addSyncDetail('error_detail', '住院号' . $zyh . '：MR病程记录预警失败，' . $e->getMessage());
                Log::error('shizhong_warning_sync::MR病程记录单条预警失败', [
                    'zyh' => $zyh,
                    'pacs_item' => $pacsItem,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        return $this->syncStats;
    }

    /**
     * 查询报告时间进入70至72小时窗口的MR检查报告。
     *
     * @param string $startTime
     * @param string $endTime
     * @return array
     */
    private function getMrProgressWarningItems($startTime, $endTime)
    {
        $limit = (int)ShizhongWarningConfig::getValue('mr_progress_72_limit', '1000');
        $limit = $limit > 0 ? $limit : 1000;
        $mrKeywords = $this->getMrProgressKeywords();

        $rows = PACS::query()
            ->where('BGSJ', '>=', $startTime)
            ->where('BGSJ', '<=', $endTime)
            ->whereNotNull('ZYH')
            ->whereNotNull('BGSJ')
            ->orderBy('BGSJ', 'asc')
            ->limit($limit)
            ->get(['ZYH', 'JCMC', 'BGSJ'])
            ->toArray();

        $items = [];
        foreach ($rows as $row) {
            if (!$this->textContainsAnyKeyword((string)($row['JCMC'] ?? ''), $mrKeywords)) {
                continue;
            }

            $reportTime = (string)($row['BGSJ'] ?? '');
            if ($reportTime === '' || $this->isEmptyTime($reportTime) || strtotime($reportTime) === false) {
                continue;
            }

            $row['data_id'] = $this->buildMrProgressDataId((string)($row['ZYH'] ?? ''), (string)($row['JCMC'] ?? ''), $reportTime);
            $row['data_type'] = 'mr_progress';
            $items[] = $row;
        }

        return $items;
    }

    /**
     * 检查MR报告出具后72小时内是否完成相关病程记录。
     *
     * @param string $zyh
     * @param array $context
     * @param array|null $pacsItem
     * @param bool $isRecheck
     * @param string $dataId
     * @param string $dataType
     * @return void
     */
    private function checkMrProgressRecord72h($zyh, array $context, array $pacsItem = null, $isRecheck = false, $dataId = '', $dataType = '')
    {
        $ruleId = 103;
        if (empty($context['case_rule_map'][$ruleId])) {
            return;
        }

        if ($pacsItem === null) {
            $pacsItem = $this->getMrProgressByDataId($zyh, $dataId);
        }

        if (empty($pacsItem)) {
            return;
        }

        $reportTime = (string)($pacsItem['BGSJ'] ?? '');
        if ($reportTime === '' || $this->isEmptyTime($reportTime) || strtotime($reportTime) === false) {
            return;
        }

        $config = $this->getMrProgressConfig();
        if (!$this->textContainsAnyKeyword((string)($pacsItem['JCMC'] ?? ''), $config['mr_keywords'])) {
            return;
        }

        $orderInfo = $this->getMatchingMrOrderForPacs($zyh, (string)($pacsItem['JCMC'] ?? ''), $reportTime, $config);
        if (empty($orderInfo)) {
            return;
        }

        $dataId = $dataId !== '' ? $dataId : (string)($pacsItem['data_id'] ?? $this->buildMrProgressDataId($zyh, (string)($pacsItem['JCMC'] ?? ''), $reportTime));
        $dataType = $dataType !== '' ? $dataType : (string)($pacsItem['data_type'] ?? 'mr_progress');
        $deadline = strtotime($reportTime) + $config['deadline_hours'] * 3600;
        $status = $this->getMrProgressRecordStatus($zyh, $reportTime, $deadline, $config);

        if ($status['resolved']) {
            $this->setWarningResolved($zyh, $ruleId, $status['effective'], $dataId, $dataType);
            return;
        }

        $messageBasis = $this->buildMrProgressMessageBasis($pacsItem, $orderInfo, $status, $config);
        if ($isRecheck) {
            $this->recordPendingWarningUnresolved($zyh, $ruleId, 'MR相关病程记录', $messageBasis);
            return;
        }

        $this->sendDeadlineWarning($context, $zyh, $ruleId, $deadline, 'MR相关病程记录', $messageBasis, $dataId, $dataType);
    }

    /**
     * 获取MR病程记录规则配置。
     *
     * @return array
     */
    private function getMrProgressConfig()
    {
        $timeField = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $signTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');

        return [
            'mr_keywords' => $this->getMrProgressKeywords(),
            'record_types' => $this->getRuleWordMapList(8068, '294'),
            'time_field' => !empty($timeField) ? $timeField : 'ZXSJ',
            'sign_time_field' => !empty($signTimeField) ? $signTimeField : 'first_blsy_time',
            'deadline_hours' => $this->getMrProgressDeadlineHours(),
        ];
    }

    /**
     * 获取MR关键词配置。
     *
     * @return array
     */
    private function getMrProgressKeywords()
    {
        return $this->getRuleWordMapList(25, 'MR,磁共振,DWI,TRICKS,SWI');
    }

    /**
     * 获取MR报告后病程记录完成时限。
     *
     * @return int
     */
    private function getMrProgressDeadlineHours()
    {
        $hours = (int)RuleWordMap::query()->where('id', '=', 9010)->value('keyword');
        return $hours > 0 ? $hours : 72;
    }

    /**
     * 匹配MR报告对应的MR检查医嘱。
     *
     * @param string $zyh
     * @param string $checkName
     * @param string $reportTime
     * @param array $config
     * @return array|null
     */
    private function getMatchingMrOrderForPacs($zyh, $checkName, $reportTime, array $config)
    {
        $reportTimestamp = strtotime($reportTime);
        if ($reportTimestamp === false) {
            return null;
        }

        $orders = Yzb::query()
            ->where('ZYH', '=', $zyh)
            ->where('KZSJ', '>', date('Y-m-d H:i:s', $reportTimestamp - 48 * 3600))
            ->where('KZSJ', '<', $reportTime)
            ->orderBy('KZSJ', 'asc')
            ->get(['YZMC', 'KZSJ'])
            ->toArray();

        $firstMrOrder = null;
        foreach ($orders as $order) {
            $orderName = (string)($order['YZMC'] ?? '');
            if (!$this->textContainsAnyKeyword($orderName, $config['mr_keywords'])) {
                continue;
            }

            if ($firstMrOrder === null) {
                $firstMrOrder = $order;
            }

            $normalizedOrderName = $this->normalizeCheckOrderName($orderName);
            if ($normalizedOrderName !== '' && strpos($checkName, $normalizedOrderName) !== false) {
                return $order;
            }
        }

        return $firstMrOrder;
    }

    /**
     * 判断MR相关病程记录是否完成。
     *
     * @param string $zyh
     * @param string $reportTime
     * @param int $deadline
     * @param array $config
     * @return array
     */
    private function getMrProgressRecordStatus($zyh, $reportTime, $deadline, array $config)
    {
        $records = EMR_BL_BL01::query()
            ->join('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
            ->where('EMR_BL_BL01.JZHM', '=', $zyh)
            ->whereIn('EMR_BL_BL01.BLLB', $config['record_types'])
            ->where('EMR_BL_BL01.' . $config['time_field'], '>', $reportTime)
            ->where(function ($query) use ($config) {
                foreach ($config['mr_keywords'] as $keyword) {
                    if ($keyword !== '') {
                        $query->orWhere('EMR_BL_BLXG.HJNR', 'like', '%' . $keyword . '%');
                    }
                }
            })
            //->where('EMR_BL_BL01.BLZT', '<>', 9)
            ->orderBy('EMR_BL_BL01.' . $config['time_field'], 'asc')
            ->get([
                'EMR_BL_BL01.BLBH as BLBH',
                'EMR_BL_BL01.CJSJ as CJSJ',
                'EMR_BL_BL01.ZXSJ as ZXSJ',
                'EMR_BL_BL01.BLMC as BLMC',
                'EMR_BL_BL01.BLLB as BLLB',
                'EMR_BL_BL01.' . $config['sign_time_field'] . ' as ' . $config['sign_time_field'],
                'EMR_BL_BLXG.HJNR as HJNR',
            ]);

        $hasUnsignedRecord = false;
        $lateFinishTime = '';
        $blbh = '';
        foreach ($records as $record) {
            $content = (string)($record->HJNR ?? '');
            if (strpos((string)($record->BLMC ?? ''), '会诊') !== false && strpos($content, '会诊意见给予') !== false) {
                $content = substr($content, 0, strpos($content, '会诊意见给予'));
            }

            if (!$this->textContainsAnyKeyword($content, $config['mr_keywords'])) {
                continue;
            }

            if ($blbh === '') {
                $blbh = (string)($record->BLBH ?? '');
            }

            $signTime = $this->getMedicalRecordSignTime($record, $config['sign_time_field']);
            $signTimestamp = strtotime($signTime);
            if ($signTime === '' || $signTimestamp === false) {
                $hasUnsignedRecord = true;
                continue;
            }

            if ($signTimestamp >= strtotime($reportTime) && $signTimestamp <= $deadline) {
                return ['resolved' => true, 'effective' => 1, 'reason' => '', 'finish_time' => $signTime, 'blbh' => $blbh];
            }

            if ($lateFinishTime === '') {
                $lateFinishTime = $signTime;
            }
        }

        if ($lateFinishTime !== '') {
            return ['resolved' => true, 'effective' => 0, 'reason' => 'late', 'late_finish_time' => $lateFinishTime, 'blbh' => $blbh];
        }

        if ($hasUnsignedRecord) {
            return ['resolved' => false, 'reason' => 'unsigned', 'blbh' => $blbh];
        }

        return ['resolved' => false, 'reason' => 'missing', 'blbh' => $blbh];
    }

    /**
     * 根据预警日志中的数据ID还原MR报告事件。
     *
     * @param string $zyh
     * @param string $dataId
     * @return array|null
     */
    private function getMrProgressByDataId($zyh, $dataId)
    {
        $prefix = 'mr_progress:' . $zyh . ':';
        if ($dataId === '' || strpos($dataId, $prefix) !== 0) {
            return null;
        }

        $parts = explode(':', substr($dataId, strlen($prefix)));
        $reportTimestamp = (int)($parts[0] ?? 0);
        $checkHash = (string)($parts[1] ?? '');
        if ($reportTimestamp <= 0 || $checkHash === '') {
            return null;
        }

        $reportTime = date('Y-m-d H:i:s', $reportTimestamp);
        $items = PACS::query()
            ->where('ZYH', '=', $zyh)
            ->where('BGSJ', '=', $reportTime)
            ->get(['ZYH', 'JCMC', 'BGSJ'])
            ->toArray();

        foreach ($items as $item) {
            if (substr(md5((string)($item['JCMC'] ?? '')), 0, 8) !== $checkHash) {
                continue;
            }

            $item['data_id'] = $dataId;
            $item['data_type'] = 'mr_progress';
            return $item;
        }

        return null;
    }

    /**
     * 生成MR报告病程记录预警ID。
     *
     * @param string $zyh
     * @param string $checkName
     * @param string $reportTime
     * @return string
     */
    private function buildMrProgressDataId($zyh, $checkName, $reportTime)
    {
        return substr('mr_progress:' . $zyh . ':' . strtotime($reportTime) . ':' . substr(md5($checkName), 0, 8), 0, 100);
    }

    /**
     * 生成MR报告病程记录预警依据。
     *
     * @param array $pacsItem
     * @param array $orderInfo
     * @param array $status
     * @param array $config
     * @return array
     */
    private function buildMrProgressMessageBasis(array $pacsItem, array $orderInfo, array $status, array $config)
    {
        $reportTime = (string)($pacsItem['BGSJ'] ?? '');
        $deadline = strtotime($reportTime) + $config['deadline_hours'] * 3600;
        $messageBasis = [
            '检查名称【' . (string)($pacsItem['JCMC'] ?? '') . '】',
            '报告时间【' . $reportTime . '】',
            '医嘱名称【' . (string)($orderInfo['YZMC'] ?? '') . '】',
            '开嘱时间【' . (string)($orderInfo['KZSJ'] ?? '') . '】',
            '完成时限【' . date('Y-m-d H:i:s', $deadline) . '】',
        ];

        if (($status['reason'] ?? '') === 'unsigned') {
            $messageBasis[] = 'MR相关病程记录【已创建，未签名】';
        } else {
            $messageBasis[] = '报告出具后' . $config['deadline_hours'] . '小时内的病程记录未记录检查结果';
        }

        if (!empty($status['blbh'])) {
            //$messageBasis['BLBH'] = $status['blbh'];
        }

        return $messageBasis;
    }

    /**
     * 同步抗菌药物开嘱后72小时病程记录预警。
     *
     * @return array
     */
    private function syncAntibioticProgress72Warnings()
    {
        $context = $this->buildSyncContext(true);
        $deadlineHours = $this->getAntibioticProgressDeadlineHours();
        $startTime = date('Y-m-d H:i:s', strtotime('-' . $deadlineHours . ' hours'));
        $endTime = date('Y-m-d H:i:s', strtotime('-' . max($deadlineHours - 2, 1) . ' hours'));
        $orders = $this->getAntibioticProgressWarningItems($startTime, $endTime);

        $this->resetSyncStats(count($orders), count($orders), 0, 0);
        $this->syncStats['summary_unit'] = '条抗菌药物医嘱';
        $this->line('  抗菌药物病程记录预警：' . $startTime . ' 至 ' . $endTime . '，抗菌药物医嘱 ' . count($orders) . ' 条');

        foreach ($orders as $order) {
            $zyh = (string)($order['ZYH'] ?? '');
            if ($zyh === '') {
                $this->syncStats['skipped']++;
                $this->addSyncDetail('skipped_detail', '抗菌药物医嘱缺少住院号，已跳过');
                continue;
            }

            try {
                $this->syncStats['processed']++;
                $this->syncHomeDataForQuality($zyh, $context);
                $this->checkAntibioticProgressRecord72h($zyh, $context, $order);
            } catch (\Throwable $e) {
                $this->syncStats['errors']++;
                $this->addSyncDetail('error_detail', '住院号' . $zyh . '：抗菌药物病程记录预警失败，' . $e->getMessage());
                Log::error('shizhong_warning_sync::抗菌药物病程记录单条预警失败', [
                    'zyh' => $zyh,
                    'order' => $order,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        return $this->syncStats;
    }

    /**
     * 查询开嘱时间进入70至72小时窗口的抗菌药物医嘱。
     *
     * @param string $startTime
     * @param string $endTime
     * @return array
     */
    private function getAntibioticProgressWarningItems($startTime, $endTime)
    {
        $limit = (int)ShizhongWarningConfig::getValue('antibiotic_progress_72_limit', '1000');
        $limit = $limit > 0 ? $limit : 1000;

        $rows = Yzb::query()
            ->select(['ZYH', 'YZMC', 'JLDW', 'SYPC', 'YCJL', 'KZSJ', 'kjyw_name', 'YZBXH', 'YZQX'])
            ->where('KZSJ', '>=', $startTime)
            ->where('KZSJ', '<=', $endTime)
            ->where('YZZT', '!=', 3)
            ->where('PSBZ', '!=', 1)
            ->where('is_has_kjyw', '=', 1)
            ->whereNotNull('ZYH')
            ->whereNotNull('KZSJ')
            ->where('YZMC', 'not like', '%皮试%')
            ->orderBy('KZSJ', 'asc')
            ->limit($limit)
            ->get()
            ->toArray();

        $items = [];
        foreach ($rows as $row) {
            $orderTime = (string)($row['KZSJ'] ?? '');
            if ($orderTime === '' || $this->isEmptyTime($orderTime) || strtotime($orderTime) === false) {
                continue;
            }

            $row['data_id'] = $this->buildAntibioticProgressDataId((string)($row['ZYH'] ?? ''), $row);
            $row['data_type'] = 'antibiotic_progress';
            $items[] = $row;
        }

        return $items;
    }

    /**
     * 检查抗菌药物开嘱后72小时内是否完成相关病程记录。
     *
     * @param string $zyh
     * @param array $context
     * @param array|null $order
     * @param bool $isRecheck
     * @param string $dataId
     * @param string $dataType
     * @return void
     */
    private function checkAntibioticProgressRecord72h($zyh, array $context, array $order = null, $isRecheck = false, $dataId = '', $dataType = '')
    {
        $ruleId = 109;
        if (empty($context['case_rule_map'][$ruleId])) {
            return;
        }

        if ($order === null) {
            $order = $this->getAntibioticProgressByDataId($zyh, $dataId);
        }

        if (empty($order)) {
            return;
        }

        $orderTime = (string)($order['KZSJ'] ?? '');
        if ($orderTime === '' || $this->isEmptyTime($orderTime) || strtotime($orderTime) === false) {
            return;
        }

        if ($this->shouldSkipAntibioticProgressByDepartment($zyh)) {
            return;
        }

        $config = $this->getAntibioticProgressConfig($order);
        if (empty($config['content_keywords'])) {
            return;
        }

        $dataId = $dataId !== '' ? $dataId : (string)($order['data_id'] ?? $this->buildAntibioticProgressDataId($zyh, $order));
        $dataType = $dataType !== '' ? $dataType : (string)($order['data_type'] ?? 'antibiotic_progress');
        $deadline = strtotime($orderTime) + $config['deadline_hours'] * 3600;
        $status = $this->getAntibioticProgressRecordStatus($zyh, $orderTime, $deadline, $config);

        if ($status['resolved']) {
            $this->setWarningResolved($zyh, $ruleId, $status['effective'], $dataId, $dataType);
            return;
        }

        $messageBasis = $this->buildAntibioticProgressMessageBasis($order, $status, $config);
        if ($isRecheck) {
            $this->recordPendingWarningUnresolved($zyh, $ruleId, '抗菌药物相关病程记录', $messageBasis);
            return;
        }

        $this->sendDeadlineWarning($context, $zyh, $ruleId, $deadline, '抗菌药物相关病程记录', $messageBasis, $dataId, $dataType);
    }

    /**
     * 是否按科室跳过抗菌药物病程记录预警。
     *
     * @param string $zyh
     * @return bool
     */
    private function shouldSkipAntibioticProgressByDepartment($zyh)
    {
        $departmentCode = (string)ZY_BRRY::query()->where('ZYH', '=', $zyh)->value('BRKS');
        if ($departmentCode === '') {
            return true;
        }

        return in_array($departmentCode, $this->getRuleWordMapList(8025, ''), true);
    }

    /**
     * 获取抗菌药物病程记录规则配置。
     *
     * @param array $order
     * @return array
     */
    private function getAntibioticProgressConfig(array $order)
    {
        $timeField = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $signTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $drugName = (string)($order['kjyw_name'] ?? '');
        $contentKeywords = $this->getAntibioticProgressContentKeywords($drugName);

        return [
            'record_types' => $this->getRuleWordMapList(8068, '294'),
            'time_field' => !empty($timeField) ? $timeField : 'ZXSJ',
            'sign_time_field' => !empty($signTimeField) ? $signTimeField : 'first_blsy_time',
            'deadline_hours' => $this->getAntibioticProgressDeadlineHours(),
            'drug_name' => $drugName,
            'content_keywords' => $contentKeywords,
            'exclude_keywords' => $this->getRuleWordMapList(2055, ''),
        ];
    }

    /**
     * 获取抗菌药物病程记录完成时限。
     *
     * @return int
     */
    private function getAntibioticProgressDeadlineHours()
    {
        return 72;
    }

    /**
     * 获取抗菌药物病程记录内容匹配关键词。
     *
     * @param string $drugName
     * @return array
     */
    private function getAntibioticProgressContentKeywords($drugName)
    {
        $keywords = $this->getRuleWordMapList(2053, '');
        if ($drugName !== '') {
            $keywords[] = $drugName;
            $aliases = MedicinalInfo::query()->where('name', '=', $drugName)->pluck('alias')->toArray();
            foreach ($aliases as $aliasText) {
                foreach (explode(',', str_replace('，', ',', (string)$aliasText)) as $alias) {
                    $alias = trim($alias);
                    if ($alias !== '') {
                        $keywords[] = $alias;
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($keywords, function ($keyword) {
            return trim((string)$keyword) !== '';
        })));
    }

    /**
     * 判断抗菌药物相关病程记录是否完成。
     *
     * @param string $zyh
     * @param string $orderTime
     * @param int $deadline
     * @param array $config
     * @return array
     */
    private function getAntibioticProgressRecordStatus($zyh, $orderTime, $deadline, array $config)
    {
        $windowStart = date('Y-m-d H:i:s', strtotime($orderTime) - 24 * 3600);
        $records = EMR_BL_BL01::query()
            ->join('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
            ->where('EMR_BL_BL01.JZHM', '=', $zyh)
            ->whereIn('EMR_BL_BL01.BLLB', $config['record_types'])
            ->where('EMR_BL_BL01.' . $config['time_field'], '>=', $windowStart)
            ->where('EMR_BL_BL01.' . $config['time_field'], '<=', date('Y-m-d H:i:s', $deadline))
            ->where(function ($query) use ($config) {
                foreach ($config['content_keywords'] as $keyword) {
                    if ($keyword !== '') {
                        $query->orWhere('EMR_BL_BLXG.HJNR', 'like', '%' . $keyword . '%');
                    }
                }
            })
            ->where(function ($query) use ($config) {
                foreach ($config['exclude_keywords'] as $keyword) {
                    if ($keyword !== '') {
                        $query->where('EMR_BL_BLXG.HJNR', 'not like', '%' . $keyword . '%');
                    }
                }
            })
            //->where('EMR_BL_BL01.BLZT', '<>', 9)
            ->orderBy('EMR_BL_BL01.' . $config['time_field'], 'asc')
            ->get([
                'EMR_BL_BL01.BLBH as BLBH',
                'EMR_BL_BL01.CJSJ as CJSJ',
                'EMR_BL_BL01.ZXSJ as ZXSJ',
                'EMR_BL_BL01.BLMC as BLMC',
                'EMR_BL_BL01.BLLB as BLLB',
                'EMR_BL_BL01.' . $config['sign_time_field'] . ' as ' . $config['sign_time_field'],
                'EMR_BL_BLXG.HJNR as HJNR',
            ]);

        $hasUnsignedRecord = false;
        $unsignedRecordName = '';
        $lateFinishTime = '';
        $blbh = '';
        foreach ($records as $record) {
            if ($this->shouldSkipAntibioticConsultationRecord($record, $config['drug_name'] ?? '')) {
                continue;
            }

            if ($blbh === '') {
                $blbh = (string)($record->BLBH ?? '');
            }

            $signTime = $this->getMedicalRecordSignTime($record, $config['sign_time_field']);
            $signTimestamp = strtotime($signTime);
            if ($signTime === '' || $signTimestamp === false) {
                $hasUnsignedRecord = true;
                if ($unsignedRecordName === '') {
                    $unsignedRecordName = trim((string)($record->BLMC ?? ''));
                }
                continue;
            }

            if ($signTimestamp >= strtotime($windowStart) && $signTimestamp <= $deadline) {
                return ['resolved' => true, 'effective' => 1, 'reason' => '', 'finish_time' => $signTime, 'blbh' => $blbh];
            }

            if ($lateFinishTime === '') {
                $lateFinishTime = $signTime;
            }
        }

        if ($lateFinishTime !== '') {
            return ['resolved' => true, 'effective' => 0, 'reason' => 'late', 'late_finish_time' => $lateFinishTime, 'blbh' => $blbh];
        }

        if ($hasUnsignedRecord) {
            return ['resolved' => false, 'reason' => 'unsigned', 'blbh' => $blbh, 'record_name' => $unsignedRecordName];
        }

        return ['resolved' => false, 'reason' => 'missing', 'blbh' => $blbh];
    }

    /**
     * 会诊记录只有明确写到当前抗菌药名称时才算相关病程记录。
     *
     * @param object $record
     * @param string $drugName
     * @return bool
     */
    private function shouldSkipAntibioticConsultationRecord($record, $drugName)
    {
        $recordName = (string)($record->BLMC ?? '');
        if ($drugName === '' || strpos($recordName, '会诊') === false) {
            return false;
        }

        return strpos((string)($record->HJNR ?? ''), $drugName) === false;
    }

    /**
     * 根据预警日志中的数据ID还原抗菌药物医嘱。
     *
     * @param string $zyh
     * @param string $dataId
     * @return array|null
     */
    private function getAntibioticProgressByDataId($zyh, $dataId)
    {
        $prefix = 'antibiotic_progress:' . $zyh . ':';
        if ($dataId === '' || strpos($dataId, $prefix) !== 0) {
            return null;
        }

        $parts = explode(':', substr($dataId, strlen($prefix)));
        $orderTimestamp = (int)($parts[0] ?? 0);
        $orderHash = (string)($parts[1] ?? '');
        if ($orderTimestamp <= 0 || $orderHash === '') {
            return null;
        }

        $orderTime = date('Y-m-d H:i:s', $orderTimestamp);
        $orders = Yzb::query()
            ->select(['ZYH', 'YZMC', 'JLDW', 'SYPC', 'YCJL', 'KZSJ', 'kjyw_name', 'YZBXH', 'YZQX'])
            ->where('ZYH', '=', $zyh)
            ->where('KZSJ', '=', $orderTime)
            ->where('YZZT', '!=', 3)
            ->where('PSBZ', '!=', 1)
            ->where('is_has_kjyw', '=', 1)
            ->where('YZMC', 'not like', '%皮试%')
            ->get()
            ->toArray();

        foreach ($orders as $order) {
            if (substr(md5((string)($order['YZMC'] ?? '') . '|' . (string)($order['YZBXH'] ?? '')), 0, 8) !== $orderHash) {
                continue;
            }

            $order['data_id'] = $dataId;
            $order['data_type'] = 'antibiotic_progress';
            return $order;
        }

        return null;
    }

    /**
     * 生成抗菌药物病程记录预警ID。
     *
     * @param string $zyh
     * @param array $order
     * @return string
     */
    private function buildAntibioticProgressDataId($zyh, array $order)
    {
        $orderTime = (string)($order['KZSJ'] ?? '');
        $hash = substr(md5((string)($order['YZMC'] ?? '') . '|' . (string)($order['YZBXH'] ?? '')), 0, 8);
        return substr('antibiotic_progress:' . $zyh . ':' . strtotime($orderTime) . ':' . $hash, 0, 100);
    }

    /**
     * 生成抗菌药物病程记录预警依据。
     *
     * @param array $order
     * @param array $status
     * @param array $config
     * @return array
     */
    private function buildAntibioticProgressMessageBasis(array $order, array $status, array $config)
    {
        $orderTime = (string)($order['KZSJ'] ?? '');
        $deadline = strtotime($orderTime) + $config['deadline_hours'] * 3600;
        $orderType = (string)($order['YZQX'] ?? '') === '1' ? '长期' : '临时';
        $messageBasis = [
            '医嘱名称【' . (string)($order['YZMC'] ?? '') . '】【' . $orderType . '】',
            '开嘱时间【' . $orderTime . '】',
            '完成时限【' . date('Y-m-d H:i:s', $deadline) . '】',
        ];

        if (($status['reason'] ?? '') === 'unsigned') {
            $recordName = trim((string)($status['record_name'] ?? ''));
            $messageBasis[] = $recordName !== '' ? '【' . $recordName . '】未签名' : '抗菌药物相关病程记录【已创建，未签名】';
        } else {
            $messageBasis[] = '医嘱开具后' . $config['deadline_hours'] . '小时内的病程记录未记录使用原因';
        }

        if (!empty($status['blbh'])) {
            //$messageBasis['BLBH'] = $status['blbh'];
        }

        return $messageBasis;
    }

    /**
     * 同步化疗药物开嘱后72小时病程记录预警。
     *
     * @return array
     */
    private function syncChemoProgress72Warnings()
    {
        $context = $this->buildSyncContext(true);
        $deadlineHours = $this->getChemoProgressDeadlineHours();
        $startTime = date('Y-m-d H:i:s', strtotime('-' . $deadlineHours . ' hours'));
        $endTime = date('Y-m-d H:i:s', strtotime('-' . max($deadlineHours - 2, 1) . ' hours'));
        $orders = $this->getChemoProgressWarningItems($startTime, $endTime);

        $this->resetSyncStats(count($orders), count($orders), 0, 0);
        $this->syncStats['summary_unit'] = '条化疗药物医嘱';
        $this->line('  化疗药物病程记录预警：' . $startTime . ' 至 ' . $endTime . '，化疗药物医嘱 ' . count($orders) . ' 条');

        foreach ($orders as $order) {
            $zyh = (string)($order['ZYH'] ?? '');
            if ($zyh === '') {
                $this->syncStats['skipped']++;
                $this->addSyncDetail('skipped_detail', '化疗药物医嘱缺少住院号，已跳过');
                continue;
            }

            try {
                $this->syncStats['processed']++;
                $this->syncHomeDataForQuality($zyh, $context);
                $this->checkChemoProgressRecord72h($zyh, $context, $order);
            } catch (\Throwable $e) {
                $this->syncStats['errors']++;
                $this->addSyncDetail('error_detail', '住院号' . $zyh . '：化疗药物病程记录预警失败，' . $e->getMessage());
                Log::error('shizhong_warning_sync::化疗药物病程记录单条预警失败', [
                    'zyh' => $zyh,
                    'order' => $order,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        return $this->syncStats;
    }

    /**
     * 查询开嘱时间进入70至72小时窗口的化疗药物医嘱。
     *
     * @param string $startTime
     * @param string $endTime
     * @return array
     */
    private function getChemoProgressWarningItems($startTime, $endTime)
    {
        $limit = (int)ShizhongWarningConfig::getValue('chemo_progress_72_limit', '1000');
        $limit = $limit > 0 ? $limit : 1000;

        $rows = Yzb::query()
            ->select(['ZYH', 'YZMC', 'JLDW', 'SYPC', 'YCJL', 'KZSJ', 'hlyw_name', 'YZBXH', 'YZQX'])
            ->where('KZSJ', '>=', $startTime)
            ->where('KZSJ', '<=', $endTime)
            ->where('YZZT', '!=', 3)
            ->where('is_has_hlyw', '=', 1)
            ->whereNotNull('ZYH')
            ->whereNotNull('KZSJ')
            ->orderBy('KZSJ', 'asc')
            ->limit($limit)
            ->get()
            ->toArray();

        $grouped = [];
        foreach ($rows as $row) {
            $orderTime = (string)($row['KZSJ'] ?? '');
            if ($orderTime === '' || $this->isEmptyTime($orderTime) || strtotime($orderTime) === false) {
                continue;
            }

            $zyh = (string)($row['ZYH'] ?? '');
            $drugName = (string)($row['hlyw_name'] ?? '');
            $groupKey = $zyh . '|' . $drugName;
            if (isset($grouped[$groupKey]) && strtotime($grouped[$groupKey]['KZSJ']) <= strtotime($orderTime)) {
                continue;
            }

            $row['data_id'] = $this->buildChemoProgressDataId($zyh, $row);
            $row['data_type'] = 'chemo_progress';
            $grouped[$groupKey] = $row;
        }

        return array_values($grouped);
    }

    /**
     * 检查化疗药物开嘱后72小时内是否完成相关病程记录。
     *
     * @param string $zyh
     * @param array $context
     * @param array|null $order
     * @param bool $isRecheck
     * @param string $dataId
     * @param string $dataType
     * @return void
     */
    private function checkChemoProgressRecord72h($zyh, array $context, array $order = null, $isRecheck = false, $dataId = '', $dataType = '')
    {
        $ruleId = 110;
        if (empty($context['case_rule_map'][$ruleId])) {
            return;
        }

        if ($order === null) {
            $order = $this->getChemoProgressByDataId($zyh, $dataId);
        }

        if (empty($order)) {
            return;
        }

        $orderTime = (string)($order['KZSJ'] ?? '');
        if ($orderTime === '' || $this->isEmptyTime($orderTime) || strtotime($orderTime) === false) {
            return;
        }

        $config = $this->getChemoProgressConfig($order);
        if (empty($config['content_keywords'])) {
            return;
        }

        $dataId = $dataId !== '' ? $dataId : (string)($order['data_id'] ?? $this->buildChemoProgressDataId($zyh, $order));
        $dataType = $dataType !== '' ? $dataType : (string)($order['data_type'] ?? 'chemo_progress');
        $deadline = strtotime($orderTime) + $config['deadline_hours'] * 3600;
        $status = $this->getChemoProgressRecordStatus($zyh, $orderTime, $deadline, $config);

        if ($status['resolved']) {
            $this->setWarningResolved($zyh, $ruleId, $status['effective'], $dataId, $dataType);
            return;
        }

        $messageBasis = $this->buildChemoProgressMessageBasis($order, $status, $config);
        if ($isRecheck) {
            $this->recordPendingWarningUnresolved($zyh, $ruleId, '化疗药物相关病程记录', $messageBasis);
            return;
        }

        $this->sendDeadlineWarning($context, $zyh, $ruleId, $deadline, '化疗药物相关病程记录', $messageBasis, $dataId, $dataType);
    }

    /**
     * 获取化疗药物病程记录规则配置。
     *
     * @param array $order
     * @return array
     */
    private function getChemoProgressConfig(array $order)
    {
        $timeField = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $signTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $drugName = (string)($order['hlyw_name'] ?? '');
        $contentKeywords = $this->getChemoProgressContentKeywords($drugName);

        return [
            'record_types' => $this->getRuleWordMapList(8068, '294'),
            'time_field' => !empty($timeField) ? $timeField : 'ZXSJ',
            'sign_time_field' => !empty($signTimeField) ? $signTimeField : 'first_blsy_time',
            'deadline_hours' => $this->getChemoProgressDeadlineHours(),
            'drug_name' => $drugName,
            'content_keywords' => $contentKeywords,
        ];
    }

    /**
     * 获取化疗药物病程记录完成时限。
     *
     * @return int
     */
    private function getChemoProgressDeadlineHours()
    {
        return 72;
    }

    /**
     * 获取化疗药物病程记录内容匹配关键词。
     *
     * @param string $drugName
     * @return array
     */
    private function getChemoProgressContentKeywords($drugName)
    {
        $keywords = $this->getRuleWordMapList(9016, '');
        if ($drugName !== '') {
            $keywords[] = $drugName;
        }

        return array_values(array_unique(array_filter($keywords, function ($keyword) {
            return trim((string)$keyword) !== '';
        })));
    }

    /**
     * 判断化疗药物相关病程记录是否完成。
     *
     * @param string $zyh
     * @param string $orderTime
     * @param int $deadline
     * @param array $config
     * @return array
     */
    private function getChemoProgressRecordStatus($zyh, $orderTime, $deadline, array $config)
    {
        $windowStart = date('Y-m-d H:i:s', strtotime($orderTime) - 24 * 3600);
        $records = EMR_BL_BL01::query()
            ->join('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
            ->where('EMR_BL_BL01.JZHM', '=', $zyh)
            ->whereIn('EMR_BL_BL01.BLLB', $config['record_types'])
            ->where('EMR_BL_BL01.' . $config['time_field'], '>=', $windowStart)
            ->where('EMR_BL_BL01.' . $config['time_field'], '<=', date('Y-m-d H:i:s', $deadline))
            ->where(function ($query) use ($config) {
                foreach ($config['content_keywords'] as $keyword) {
                    if ($keyword !== '') {
                        $query->orWhere('EMR_BL_BLXG.HJNR', 'like', '%' . $keyword . '%');
                    }
                }
            })
            //->where('EMR_BL_BL01.BLZT', '<>', 9)
            ->orderBy('EMR_BL_BL01.' . $config['time_field'], 'asc')
            ->get([
                'EMR_BL_BL01.BLBH as BLBH',
                'EMR_BL_BL01.CJSJ as CJSJ',
                'EMR_BL_BL01.ZXSJ as ZXSJ',
                'EMR_BL_BL01.BLMC as BLMC',
                'EMR_BL_BL01.BLLB as BLLB',
                'EMR_BL_BL01.' . $config['sign_time_field'] . ' as ' . $config['sign_time_field'],
                'EMR_BL_BLXG.HJNR as HJNR',
            ]);

        $hasUnsignedRecord = false;
        $unsignedRecordName = '';
        $lateFinishTime = '';
        $blbh = '';
        foreach ($records as $record) {
            if ($this->shouldSkipChemoConsultationRecord($record, $config['drug_name'] ?? '')) {
                continue;
            }

            if ($blbh === '') {
                $blbh = (string)($record->BLBH ?? '');
            }

            $signTime = $this->getMedicalRecordSignTime($record, $config['sign_time_field']);
            $signTimestamp = strtotime($signTime);
            if ($signTime === '' || $signTimestamp === false) {
                $hasUnsignedRecord = true;
                if ($unsignedRecordName === '') {
                    $unsignedRecordName = trim((string)($record->BLMC ?? ''));
                }
                continue;
            }

            if ($signTimestamp >= strtotime($windowStart) && $signTimestamp <= $deadline) {
                return ['resolved' => true, 'effective' => 1, 'reason' => '', 'finish_time' => $signTime, 'blbh' => $blbh];
            }

            if ($lateFinishTime === '') {
                $lateFinishTime = $signTime;
            }
        }

        if ($lateFinishTime !== '') {
            return ['resolved' => true, 'effective' => 0, 'reason' => 'late', 'late_finish_time' => $lateFinishTime, 'blbh' => $blbh];
        }

        if ($hasUnsignedRecord) {
            return ['resolved' => false, 'reason' => 'unsigned', 'blbh' => $blbh, 'record_name' => $unsignedRecordName];
        }

        return ['resolved' => false, 'reason' => 'missing', 'blbh' => $blbh];
    }

    /**
     * 会诊记录只检查“会诊意见给予”之前的内容，且必须明确写到当前化疗药名称。
     *
     * @param object $record
     * @param string $drugName
     * @return bool
     */
    private function shouldSkipChemoConsultationRecord($record, $drugName)
    {
        $recordName = (string)($record->BLMC ?? '');
        if ($drugName === '' || strpos($recordName, '会诊') === false) {
            return false;
        }

        $content = (string)($record->HJNR ?? '');
        if (strpos($content, '会诊意见给予') !== false) {
            $content = substr($content, 0, strpos($content, '会诊意见给予'));
        }

        return strpos($content, $drugName) === false;
    }

    /**
     * 根据预警日志中的数据ID还原化疗药物医嘱。
     *
     * @param string $zyh
     * @param string $dataId
     * @return array|null
     */
    private function getChemoProgressByDataId($zyh, $dataId)
    {
        $prefix = 'chemo_progress:' . $zyh . ':';
        if ($dataId === '' || strpos($dataId, $prefix) !== 0) {
            return null;
        }

        $parts = explode(':', substr($dataId, strlen($prefix)));
        $orderTimestamp = (int)($parts[0] ?? 0);
        $orderHash = (string)($parts[1] ?? '');
        if ($orderTimestamp <= 0 || $orderHash === '') {
            return null;
        }

        $orderTime = date('Y-m-d H:i:s', $orderTimestamp);
        $orders = Yzb::query()
            ->select(['ZYH', 'YZMC', 'JLDW', 'SYPC', 'YCJL', 'KZSJ', 'hlyw_name', 'YZBXH', 'YZQX'])
            ->where('ZYH', '=', $zyh)
            ->where('KZSJ', '=', $orderTime)
            ->where('YZZT', '!=', 3)
            ->where('is_has_hlyw', '=', 1)
            ->get()
            ->toArray();

        foreach ($orders as $order) {
            if (substr(md5((string)($order['YZMC'] ?? '') . '|' . (string)($order['YZBXH'] ?? '') . '|' . (string)($order['hlyw_name'] ?? '')), 0, 8) !== $orderHash) {
                continue;
            }

            $order['data_id'] = $dataId;
            $order['data_type'] = 'chemo_progress';
            return $order;
        }

        return null;
    }

    /**
     * 生成化疗药物病程记录预警ID。
     *
     * @param string $zyh
     * @param array $order
     * @return string
     */
    private function buildChemoProgressDataId($zyh, array $order)
    {
        $orderTime = (string)($order['KZSJ'] ?? '');
        $hash = substr(md5((string)($order['YZMC'] ?? '') . '|' . (string)($order['YZBXH'] ?? '') . '|' . (string)($order['hlyw_name'] ?? '')), 0, 8);
        return substr('chemo_progress:' . $zyh . ':' . strtotime($orderTime) . ':' . $hash, 0, 100);
    }

    /**
     * 生成化疗药物病程记录预警依据。
     *
     * @param array $order
     * @param array $status
     * @param array $config
     * @return array
     */
    private function buildChemoProgressMessageBasis(array $order, array $status, array $config)
    {
        $orderTime = (string)($order['KZSJ'] ?? '');
        $deadline = strtotime($orderTime) + $config['deadline_hours'] * 3600;
        $orderType = (string)($order['YZQX'] ?? '') === '1' ? '长期' : '临时';
        $messageBasis = [
            '医嘱名称【' . (string)($order['YZMC'] ?? '') . '】【' . $orderType . '】',
            '开嘱时间【' . $orderTime . '】',
            '完成时限【' . date('Y-m-d H:i:s', $deadline) . '】',
        ];

        if (($status['reason'] ?? '') === 'unsigned') {
            $recordName = trim((string)($status['record_name'] ?? ''));
            $messageBasis[] = $recordName !== '' ? '【' . $recordName . '】未签名' : '化疗药物相关病程记录【已创建，未签名】';
        } else {
            $messageBasis[] = '医嘱开具后' . $config['deadline_hours'] . '小时内的病程记录未记录使用原因';
        }

        if (!empty($status['blbh'])) {
            //$messageBasis['BLBH'] = $status['blbh'];
        }

        return $messageBasis;
    }

    /**
     * 同步死亡后7天死亡病例讨论结论记录预警。
     *
     * @return array
     */
    private function syncDeathDiscussionConclusion168hWarnings()
    {
        $context = $this->buildSyncContext(true);
        $limit = (int)ShizhongWarningConfig::getValue('death_discussion_168_limit', '1000');
        $limit = $limit > 0 ? $limit : 1000;
        $scanDays = (int)ShizhongWarningConfig::getValue('death_discussion_168_scan_days', '10');
        $scanDays = $scanDays > 0 ? $scanDays : 10;
        $startTime = date('Y-m-d H:i:s', strtotime('-' . $scanDays . ' days'));
        $endTime = date('Y-m-d H:i:s');
        $deathKeywords = $this->getRuleWordMapList(8003, '死亡');

        $rows = Yzb::query()
            ->select(['ZYH'])
            ->whereNotNull('ZYH')
            ->where('KZSJ', '>=', $startTime)
            ->where('KZSJ', '<=', $endTime)
            ->where(function ($query) use ($deathKeywords) {
                foreach ($deathKeywords as $keyword) {
                    if ($keyword !== '') {
                        $query->orWhere('YZMC', 'like', '%' . $keyword . '%');
                    }
                }
            })
            ->distinct()
            ->limit($limit)
            ->get()
            ->toArray();

        $this->resetSyncStats(count($rows), count($rows), 0, 0);
        $this->syncStats['summary_unit'] = '个死亡住院号';
        $this->line('  死亡病例讨论7天预警：本次检查死亡医嘱住院号 ' . count($rows) . ' 个');

        foreach ($rows as $row) {
            $zyh = (string)($row['ZYH'] ?? '');
            if ($zyh === '') {
                $this->syncStats['skipped']++;
                continue;
            }

            try {
                $this->syncStats['processed']++;
                $this->syncHomeDataForQuality($zyh, $context);
                $patientInfo = PatientInfo::query()
                    ->where('MED_REC_ID', '=', $zyh)
                    ->first(['MED_REC_ID', 'AAA28', 'AAB01', 'AAC01', 'AAA01']);
                $patientInfo = $patientInfo ? $patientInfo->toArray() : ['MED_REC_ID' => $zyh];
                $this->checkDeathDiscussionConclusion168h($zyh, $patientInfo, $context);
            } catch (\Throwable $e) {
                $this->syncStats['errors']++;
                $this->addSyncDetail('error_detail', '住院号' . $zyh . '：死亡病例讨论预警失败，' . $e->getMessage());
                Log::error('shizhong_warning_sync::死亡病例讨论单条预警失败', [
                    'zyh' => $zyh,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        return $this->syncStats;
    }

    /**
     * 检查死亡病例讨论结论记录是否在死亡后7天内完成。
     *
     * @param string $zyh
     * @param array $patientInfo
     * @param array $context
     * @param bool $isRecheck
     * @param string $dataId
     * @param string $dataType
     * @return void
     */
    private function checkDeathDiscussionConclusion168h($zyh, array $patientInfo, array $context, $isRecheck = false, $dataId = '', $dataType = '')
    {
        $ruleId = 1010;
        if (empty($context['case_rule_map'][$ruleId])) {
            return;
        }

        if (!$this->hasMedicalAdviceByKeywords($zyh, $this->getRuleWordMapList(8003, '死亡'))) {
            return;
        }

        $deathTime = $this->getDeathRecordTime($zyh);
        if ($deathTime === '') {
            return;
        }

        $deadline = strtotime($deathTime) + 168 * 3600;
        $warningWindowSeconds = (int)ShizhongWarningConfig::getValue('death_discussion_warning_window_hours', '2') * 3600;
        $warningWindowSeconds = $warningWindowSeconds > 0 ? $warningWindowSeconds : 2 * 3600;
        $dataId = $dataId !== '' ? $dataId : $this->buildDeathDiscussionDataId($zyh, $deathTime);
        $dataType = $dataType !== '' ? $dataType : 'death_discussion';

        $remainingSeconds = $deadline - time();
        if (!$isRecheck && ($remainingSeconds <= 0 || $remainingSeconds > $warningWindowSeconds)) {
            return;
        }

        $status = $this->getDeathDiscussionConclusionStatus($zyh, $deathTime, $deadline);
        if ($status['resolved']) {
            $this->setWarningResolved($zyh, $ruleId, $status['effective'], $dataId, $dataType);
            return;
        }

        $messageBasis = $this->buildDeathDiscussionMessageBasis($deathTime, $status);
        if ($isRecheck) {
            $this->recordPendingWarningUnresolved($zyh, $ruleId, '死亡病例讨论结论记录', $messageBasis);
            return;
        }

        $this->sendDeadlineWarning($context, $zyh, $ruleId, $deadline, '死亡病例讨论结论记录', $messageBasis, $dataId, $dataType);
    }

    /**
     * 判断死亡病例讨论结论记录是否完成。
     *
     * @param string $zyh
     * @param string $deathTime
     * @param int $deadline
     * @return array
     */
    private function getDeathDiscussionConclusionStatus($zyh, $deathTime, $deadline)
    {
        $recordTemplateTypes = $this->getRuleWordMapList(8029, '4302');
        $timeField = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $timeField = !empty($timeField) ? $timeField : 'ZXSJ';
        $signTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $signTimeField = !empty($signTimeField) ? $signTimeField : 'first_blsy_time';

        $records = EMR_BL_BL01::query()
            ->where('JZHM', '=', $zyh)
            ->whereIn('MBLB', $recordTemplateTypes)
            ->where($timeField, '>=', $deathTime)
            //->where('BLZT', '<>', 9)
            ->orderBy($timeField, 'asc')
            ->get();

        $hasUnsignedRecord = false;
        $lateFinishTime = '';
        $blbh = '';
        foreach ($records as $record) {
            if ($blbh === '') {
                $blbh = (string)($record->BLBH ?? '');
            }

            $signTime = $this->getMedicalRecordSignTime($record, $signTimeField);
            $signTimestamp = strtotime($signTime);
            if ($signTime === '' || $signTimestamp === false) {
                $hasUnsignedRecord = true;
                continue;
            }

            if ($signTimestamp <= $deadline) {
                return ['resolved' => true, 'effective' => 1, 'reason' => '', 'blbh' => $blbh];
            }

            if ($lateFinishTime === '') {
                $lateFinishTime = $signTime;
            }
        }

        if ($lateFinishTime !== '') {
            return ['resolved' => true, 'effective' => 0, 'reason' => 'late', 'late_finish_time' => $lateFinishTime, 'blbh' => $blbh];
        }

        if ($hasUnsignedRecord) {
            return ['resolved' => false, 'reason' => 'unsigned', 'blbh' => $blbh];
        }

        return ['resolved' => false, 'reason' => 'missing', 'blbh' => $blbh];
    }

    /**
     * 获取病历首次签名时间，优先使用签名表。
     *
     * @param mixed $record
     * @param string $signTimeField
     * @return string
     */
    private function getMedicalRecordSignTime($record, $signTimeField)
    {
        $signTime = (string)($record->{$signTimeField} ?? '');
        return $signTime !== '' && strtotime($signTime) !== false ? $signTime : '';
    }

    /**
     * 生成死亡病例讨论预警ID。
     *
     * @param string $zyh
     * @param string $deathTime
     * @return string
     */
    private function buildDeathDiscussionDataId($zyh, $deathTime)
    {
        return substr('death_discussion:' . $zyh . ':' . strtotime($deathTime), 0, 100);
    }

    /**
     * 构建死亡病例讨论预警依据。
     *
     * @param string $deathTime
     * @param array $status
     * @return array
     */
    private function buildDeathDiscussionMessageBasis($deathTime, array $status)
    {
        $messageBasis = [
            '死亡时间【' . $deathTime . '】',
            '完成时限【死亡后7天内】',
        ];

        if (($status['reason'] ?? '') === 'unsigned') {
            $messageBasis[] = '死亡病例讨论结论记录【已创建，未签名】';
        } else if (($status['reason'] ?? '') === 'late') {
            $messageBasis[] = '死亡病例讨论结论记录首次签名时间【' . ($status['late_finish_time'] ?? '') . '（超时）】';
        } else {
            $messageBasis[] = '死亡病例讨论结论记录【未创建】';
        }

        if (!empty($status['blbh'])) {
            //$messageBasis['BLBH'] = $status['blbh'];
        }

        return $messageBasis;
    }

    /**
     * 入院8小时内完成首次病程记录。
     *
     * @param string $zyh
     * @param array $patientInfo
     * @param array $context
     * @param bool $isRecheck
     * @return void
     */
    private function checkAdmissionFirstCourse8h($zyh, array $patientInfo, array $context, $isRecheck = false)
    {
        $ruleId = 101;
        $caseRule = $context['case_rule_map'];
        if (empty($caseRule[$ruleId])) {
            return;
        }

        $admissionTime = $this->getAdmissionTime($zyh, $patientInfo);
        if ($admissionTime === '') {
            return;
        }

        $dischargeTime = $this->getDischargeTime($zyh, $patientInfo);
        if ($dischargeTime !== '') {
            $admissionTimestamp = strtotime($admissionTime);
            $dischargeTimestamp = strtotime($dischargeTime);
            if ($admissionTimestamp !== false && $dischargeTimestamp !== false && ($dischargeTimestamp - $admissionTimestamp) < 6 * 3600) {
                return;
            }
        }

        if ($this->shouldSkipForObstetricsSpecialLogic($zyh)) {
            return;
        }

        if ($this->hasMedicalRecord($zyh, ['18'])) {
            $this->setWarningResolved($zyh, $ruleId, 1);
            return;
        }

        $deadline = strtotime($admissionTime) + 8 * 3600;
        $timeField = ShizhongWarningConfig::getValue('first_course_finish_time_field', 'first_blsy_time');
        $firstCourse = EMR_BL_BL01::query()
            ->where('JZHM', '=', $zyh)
            ->where('MBLB', '=', 295)
            //->where('BLZT', '<>', 9)
            ->orderBy('BLBH', 'asc')
            ->first();

        $basis = ['入院时间【' . $admissionTime . '】'];
        $messageBasis = ['入院时间【' . $admissionTime . '】'];
        $blbh = '';

        if (empty($firstCourse)) {
            $basis[] = '首次病程记录【未创建】';
            $messageBasis[] = '首次病程记录【未创建】';
        } else {
            $blbh = $firstCourse->BLBH ?? '';
            $finishTime = $firstCourse->{$timeField} ?? '';
            if ($finishTime === '') {
                $basis[] = '首次病程记录【已创建，未签名】';
                //$basis['BLBH'] = $blbh;
                $messageBasis[] = '首次病程记录【已创建，未签名】';
            } else if (strtotime($finishTime) > $deadline) {
                if ($isRecheck) {
                    $this->setWarningResolved($zyh, $ruleId, 0);
                    return;
                }

                $basis[] = '首次病程记录完成时间【' . $finishTime . '（超8小时）】';
                //$basis['BLBH'] = $blbh;
                $messageBasis[] = '首次病程记录完成时间【' . $finishTime . '（超8小时）】';
            } else {
                $this->setWarningResolved($zyh, $ruleId, 1);
                return;
            }
        }

        if ($isRecheck) {
            $this->recordPendingWarningUnresolved($zyh, $ruleId, '首次病程记录', $messageBasis);
            return;
        }

        $this->sendDeadlineWarning($context, $zyh, $ruleId, $deadline, '首次病程记录', $messageBasis);
    }

    /**
     * 入院24小时内完成入院记录或24小时记录。
     *
     * @param string $zyh
     * @param array $patientInfo
     * @param array $context
     * @param bool $isRecheck
     * @return void
     */
    private function checkAdmissionRecordOr24h($zyh, array $patientInfo, array $context, $isRecheck = false)
    {
        $ruleId = 99;
        $caseRule = $context['case_rule_map'];
        if (empty($caseRule[$ruleId])) {
            return;
        }

        $admissionTime = $this->getAdmissionTime($zyh, $patientInfo);
        if ($admissionTime === '') {
            return;
        }

        $dischargeTime = $this->getDischargeTime($zyh, $patientInfo);
        if ($dischargeTime !== '') {
            $admissionTimestamp = strtotime($admissionTime);
            $dischargeTimestamp = strtotime($dischargeTime);
            if ($admissionTimestamp !== false && $dischargeTimestamp !== false && ($dischargeTimestamp - $admissionTimestamp) < 22 * 3600) {
                return;
            }
        }

        $deadline = strtotime($admissionTime) + 24 * 3600;
        $timeField = ShizhongWarningConfig::getValue('admission_record_sign_time_field', 'first_blsy_time');
        $recordTypes = array_values(array_unique(array_merge(
            ShizhongWarningConfig::getList('admission_record_bllb', '292'),
            ShizhongWarningConfig::getList('admission_24h_record_bllb', '18')
        )));

        $records = EMR_BL_BL01::query()
            ->where('JZHM', '=', $zyh)
            ->whereIn('BLLB', $recordTypes)
            //->where('BLZT', '<>', 9)
            ->orderBy('BLBH', 'asc')
            ->get();

        $basis = ['入院时间【' . $admissionTime . '】'];
        $messageBasis = ['入院时间【' . $admissionTime . '】'];
        $blbh = '';
        $hasUnsignedRecord = false;
        $lateFinishTime = '';

        foreach ($records as $record) {
            $finishTime = $record->{$timeField} ?? '';
            if ($blbh === '') {
                $blbh = $record->BLBH ?? '';
            }
            if ($finishTime === '') {
                $hasUnsignedRecord = true;
                continue;
            }
            if (strtotime($finishTime) > $deadline) {
                $lateFinishTime = $finishTime;
                continue;
            }

            $this->setWarningResolved($zyh, $ruleId, 1);
            return;
        }

        if ($isRecheck && $lateFinishTime !== '') {
            $this->setWarningResolved($zyh, $ruleId, 0);
            return;
        }

        if ($records->isEmpty()) {
            $basis[] = '入院记录或24小时记录【未创建】';
            $messageBasis[] = '入院记录或24小时记录【未创建】';
        } else if ($hasUnsignedRecord) {
            $basis[] = '入院记录或24小时记录【已创建，未签名】';
            //$basis['BLBH'] = $blbh;
            $messageBasis[] = '入院记录或24小时记录【已创建，未签名】';
        } else if ($lateFinishTime !== '') {
            $basis[] = '入院记录或24小时记录首次签名时间【' . $lateFinishTime . '（超24小时）】';
            //$basis['BLBH'] = $blbh;
            $messageBasis[] = '入院记录或24小时记录首次签名时间【' . $lateFinishTime . '（超24小时）】';
        } else {
            return;
        }

        if ($isRecheck) {
            $this->recordPendingWarningUnresolved($zyh, $ruleId, '入院记录或24小时记录', $messageBasis);
            return;
        }

        $this->sendDeadlineWarning($context, $zyh, $ruleId, $deadline, '入院记录或24小时记录', $messageBasis);
    }

    /**
     * 入院48小时内完成上级医师首次查房。
     *
     * @param string $zyh
     * @param array $patientInfo
     * @param array $context
     * @param bool $isRecheck
     * @return void
     */
    private function checkAdmissionSuperiorFirstRound48h($zyh, array $patientInfo, array $context, $isRecheck = false)
    {
        $ruleId = 1047;
        $caseRule = $context['case_rule_map'];
        if (empty($caseRule[$ruleId])) {
            return;
        }

        $admissionTime = $this->getAdmissionTime($zyh, $patientInfo);
        if ($admissionTime === '') {
            return;
        }

        $dischargeTime = $this->getDischargeTime($zyh, $patientInfo);
        if ($dischargeTime !== '') {
            $admissionTimestamp = strtotime($admissionTime);
            $dischargeTimestamp = strtotime($dischargeTime);
            if ($admissionTimestamp !== false && $dischargeTimestamp !== false && ($dischargeTimestamp - $admissionTimestamp) < 46 * 3600) {
                return;
            }
        }

        if ($this->shouldSkipForObstetricsSpecialLogic($zyh)) {
            return;
        }

        $deadline = strtotime($admissionTime) + 48 * 3600;
        $recordTypes = $this->getRuleWordMapList(8010, '294');
        $excludeTemplateTypes = $this->getRuleWordMapList(8008, '295');
        $timeField = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $timeField = !empty($timeField) ? $timeField : 'ZXSJ';
        $signTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $signTimeField = !empty($signTimeField) ? $signTimeField : 'first_blsy_time';
        $titleKeywords = $this->getRuleWordMapList(8012, '主治,主任');

        $records = EMR_BL_BL01::query()
            ->where('JZHM', '=', $zyh)
            ->whereIn('BLLB', $recordTypes)
            ->whereNotIn('MBLB', $excludeTemplateTypes)
            //->where($timeField, '>=', $admissionTime)
            ->where($timeField, '<=', date('Y-m-d H:i:s', $deadline))
            //->where('BLZT', '<>', 9)
            ->orderBy($timeField, 'asc')
            ->get();

        $messageBasis = ['入院时间【' . $admissionTime . '】'];
        $blbh = '';
        $hasMatchedRecord = false;
        $hasUnsignedRecord = false;

        foreach ($records as $record) {
            $recordTitle = $record->BLMC ?? '';
            if (!$this->textContainsAnyKeyword($recordTitle, $titleKeywords)) {
                continue;
            }

            $hasMatchedRecord = true;

            if ($blbh === '') {
                $blbh = $record->BLBH ?? '';
            }

            $signTime = $record->{$signTimeField} ?? '';
            if ($signTime === '' || strtotime($signTime) === false) {
                $hasUnsignedRecord = true;
                continue;
            }

            $this->setWarningResolved($zyh, $ruleId, 1);
            return;
        }

        if ($hasUnsignedRecord) {
            $messageBasis[] = '上级医师首次查房【已创建，未签名】';
        } else if (!$hasMatchedRecord) {
            $messageBasis[] = '上级医师首次查房【未创建】';
        } else {
            $messageBasis[] = '入院后48小时内未完成“上级医师首次查房”';
        }

        if ($isRecheck) {
            $this->recordPendingWarningUnresolved($zyh, $ruleId, '上级医师首次查房', $messageBasis);
            return;
        }

        $this->sendDeadlineWarning($context, $zyh, $ruleId, $deadline, '上级医师首次查房', $messageBasis);
    }

    /**
     * 出院后24小时内完成病案首页。
     *
     * @param string $zyh
     * @param array $patientInfo
     * @param array $context
     * @param bool $isRecheck
     * @return void
     */
    private function checkDischargeHomePage24h($zyh, array $patientInfo, array $context, $isRecheck = false)
    {
        $ruleId = 95;
        if (empty($context['case_rule_map'][$ruleId])) {
            return;
        }

        $dischargeTime = $this->getDischargeTime($zyh, $patientInfo);
        if ($dischargeTime === '') {
            return;
        }

        if (!$this->hasDischargeAdvice($zyh)) {
            return;
        }

        $dischargeTimestamp = strtotime($dischargeTime);
        if ($dischargeTimestamp === false) {
            return;
        }

        $deadline = $dischargeTimestamp + 24 * 3600;
        $messageBasis = ['出院时间【' . $dischargeTime . '】'];
        $homePage = EMR_BL_BL01::query()
            ->where('JZHM', '=', $zyh)
            ->whereIn('BLLB', $this->getRuleWordMapList(8001, '2000001'))
            //->where('BLZT', '<>', 9)
            ->orderBy('BLBH', 'asc')
            ->first();

        if (empty($homePage)) {
            $messageBasis[] = '病案首页【未创建】';
            $this->handleUnfinishedDischargeHomePage($context, $zyh, $ruleId, $deadline, $messageBasis, $isRecheck);
            return;
        }

        $blbh = $homePage->BLBH ?? '';
        if ($blbh === '') {
            $messageBasis[] = '病案首页【已创建，病历编号为空】';
            $this->handleUnfinishedDischargeHomePage($context, $zyh, $ruleId, $deadline, $messageBasis, $isRecheck);
            return;
        }

        $signatureTimes = $this->getHomePageSignatureTimes($blbh);
        if (count($signatureTimes) < 6) {
            $messageBasis[] = '病案首页【已创建，签名不足6人】';
            //$messageBasis['BLBH'] = $blbh;
            $this->handleUnfinishedDischargeHomePage($context, $zyh, $ruleId, $deadline, $messageBasis, $isRecheck);
            return;
        }

        $finishTime = date('Y-m-d H:i:s', max($signatureTimes));
        $this->updateHomePageFinishTime($blbh, $finishTime);

        if (strtotime($finishTime) > $deadline) {
            if ($isRecheck) {
                $this->setWarningResolved($zyh, $ruleId, 0);
                return;
            }

            $messageBasis[] = '病案首页首次签名完成时间【' . $finishTime . '（超24小时）】';
            //$messageBasis['BLBH'] = $blbh;
            $this->handleUnfinishedDischargeHomePage($context, $zyh, $ruleId, $deadline, $messageBasis, $isRecheck);
            return;
        }

        $this->setWarningResolved($zyh, $ruleId, 1);
    }

    /**
     * 处理未完成病案首页预警。
     *
     * @param array $context
     * @param string $zyh
     * @param int $ruleId
     * @param int $deadline
     * @param array $messageBasis
     * @param bool $isRecheck
     * @return void
     */
    private function handleUnfinishedDischargeHomePage(array $context, $zyh, $ruleId, $deadline, array $messageBasis, $isRecheck)
    {
        if ($isRecheck) {
            $this->recordPendingWarningUnresolved($zyh, $ruleId, '病案首页', $messageBasis);
            return;
        }

        $this->sendDeadlineWarning($context, $zyh, $ruleId, $deadline, '病案首页', $messageBasis);
    }

    /**
     * 获取病案首页每位签名医师的首次签名时间。
     *
     * @param string $blbh
     * @return array
     */
    private function getHomePageSignatureTimes($blbh)
    {
        $signatures = EMR_BL_BLSY::query()
            ->select(['SYYS', DB::raw('MIN(JLSJ) as first_sign_time')])
            ->where('BLBH', '=', $blbh)
            ->where('FG_ACTIVE', '=', 1)
            ->whereNotNull('SYYS')
            ->where('SYYS', '<>', '')
            ->groupBy('SYYS')
            ->get();

        $signatureTimes = [];
        foreach ($signatures as $signature) {
            $signatureTime = $signature->first_sign_time ?? '';
            $timestamp = strtotime($signatureTime);
            if ($timestamp === false) {
                continue;
            }

            $signatureTimes[] = $timestamp;
        }

        return $signatureTimes;
    }

    /**
     * 回写病案首页签名完成时间。
     *
     * @param string $blbh
     * @param string $finishTime
     * @return void
     */
    private function updateHomePageFinishTime($blbh, $finishTime)
    {
        $finishTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $finishTimeField = !empty($finishTimeField) ? $finishTimeField : 'frist_blsy_time';

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $finishTimeField)) {
            return;
        }

        EMR_BL_BL01::query()
            ->where('BLBH', '=', $blbh)
            ->update([$finishTimeField => $finishTime]);
    }

    /**
     * 死亡后一周内完成病案首页。
     *
     * @param string $zyh
     * @param array $patientInfo
     * @param array $context
     * @param bool $isRecheck
     * @return void
     */
    private function checkDeathHomePage168h($zyh, array $patientInfo, array $context, $isRecheck = false)
    {
        $ruleId = 278;
        if (empty($context['case_rule_map'][$ruleId])) {
            return;
        }

        if (!$this->hasDeathAdvice($zyh)) {
            return;
        }

        $dischargeTime = $this->getDischargeTime($zyh, $patientInfo);
        if ($dischargeTime === '') {
            return;
        }

        $dischargeTimestamp = strtotime($dischargeTime);
        if ($dischargeTimestamp === false) {
            return;
        }

        $deadline = $dischargeTimestamp + 168 * 3600;
        $messageBasis = ['死亡患者出院时间【' . $dischargeTime . '】'];
        $homePage = EMR_BL_BL01::query()
            ->where('JZHM', '=', $zyh)
            ->whereIn('BLLB', $this->getRuleWordMapList(8001, '2000001'))
            //->where('BLZT', '<>', 9)
            ->orderBy('BLBH', 'asc')
            ->first();

        if (empty($homePage)) {
            $messageBasis[] = '病案首页【未创建】';
            $this->handleUnfinishedDischargeHomePage($context, $zyh, $ruleId, $deadline, $messageBasis, $isRecheck);
            return;
        }

        $blbh = $homePage->BLBH ?? '';
        if ($blbh === '') {
            $messageBasis[] = '病案首页【已创建，病历编号为空】';
            $this->handleUnfinishedDischargeHomePage($context, $zyh, $ruleId, $deadline, $messageBasis, $isRecheck);
            return;
        }

        $signatureTimes = $this->getHomePageSignatureTimes($blbh);
        if (count($signatureTimes) < 6) {
            $messageBasis[] = '病案首页【已创建，签名不足6人】';
            //$messageBasis['BLBH'] = $blbh;
            $this->handleUnfinishedDischargeHomePage($context, $zyh, $ruleId, $deadline, $messageBasis, $isRecheck);
            return;
        }

        $finishTime = date('Y-m-d H:i:s', max($signatureTimes));
        $this->updateHomePageFinishTime($blbh, $finishTime);

        if (strtotime($finishTime) > $deadline) {
            if ($isRecheck) {
                $this->setWarningResolved($zyh, $ruleId, 0);
                return;
            }

            $messageBasis[] = '病案首页首次签名完成时间【' . $finishTime . '（超1周）】';
            //$messageBasis['BLBH'] = $blbh;
            $this->handleUnfinishedDischargeHomePage($context, $zyh, $ruleId, $deadline, $messageBasis, $isRecheck);
            return;
        }

        $this->setWarningResolved($zyh, $ruleId, 1);
    }

    /**
     * 出院后24小时内完成出院记录。
     *
     * @param string $zyh
     * @param array $patientInfo
     * @param array $context
     * @param bool $isRecheck
     * @return void
     */
    private function checkDischargeRecord24h($zyh, array $patientInfo, array $context, $isRecheck = false)
    {
        $ruleId = 96;
        if (empty($context['case_rule_map'][$ruleId])) {
            return;
        }

        $dischargeTime = $this->getDischargeTime($zyh, $patientInfo);
        $admissionTime = $this->getAdmissionTime($zyh, $patientInfo);
        if ($dischargeTime === '' || $admissionTime === '') {
            return;
        }

        if ($this->getDateDiffDays($admissionTime, $dischargeTime) <= 1 && !$this->hasMedicalRecord($zyh, ['1'])) {
            return;
        }

        if (!$this->hasDischargeAdvice($zyh)) {
            return;
        }

        $this->checkTimedSignedDocument24h(
            $context,
            $zyh,
            $ruleId,
            $dischargeTime,
            '出院时间',
            '出院记录',
            $this->getRuleWordMapList(8004, '1'),
            $isRecheck,
            null,
            true
        );
    }

    /**
     * 死亡后24小时内完成死亡记录。
     *
     * @param string $zyh
     * @param array $patientInfo
     * @param array $context
     * @param bool $isRecheck
     * @return void
     */
    private function checkDeathRecord24h($zyh, array $patientInfo, array $context, $isRecheck = false)
    {
        $ruleId = 98;
        if (empty($context['case_rule_map'][$ruleId])) {
            return;
        }

        $dischargeTime = $this->getDischargeTime($zyh, $patientInfo);
        if ($dischargeTime === '') {
            return;
        }

        $admissionTime = $this->getAdmissionTime($zyh, $patientInfo);
        if ($admissionTime !== '' && $dischargeTime !== '' && $this->getDateDiffDays($admissionTime, $dischargeTime) <= 1 && !$this->hasMedicalRecord($zyh, ['288'])) {
            return;
        }

        if (!$this->hasDeathAdvice($zyh)) {
            return;
        }

        $this->checkTimedSignedDocument24h(
            $context,
            $zyh,
            $ruleId,
            $dischargeTime,
            '出院时间',
            '死亡记录',
            $this->getRuleWordMapList(8005, '288'),
            $isRecheck,
            null,
            true
        );
    }

    /**
     * 出院后24小时内完成24小时入出院记录。
     *
     * @param string $zyh
     * @param array $patientInfo
     * @param array $context
     * @param bool $isRecheck
     * @return void
     */
    private function checkDischarge24hAdmissionDischargeRecord($zyh, array $patientInfo, array $context, $isRecheck = false)
    {
        $ruleId = 97;
        if (empty($context['case_rule_map'][$ruleId])) {
            return;
        }

        $dischargeTime = $this->getDischargeTime($zyh, $patientInfo);
        $admissionTime = $this->getAdmissionTime($zyh, $patientInfo);
        if ($dischargeTime === '' || $admissionTime === '' || $this->getDateDiffDays($admissionTime, $dischargeTime) > 1) {
            return;
        }

        if ($this->hasMedicalRecord($zyh, ['1']) || $this->hasRequiredDischargeProgressAndAdmissionRecord($zyh) || $this->hasDeathAdvice($zyh) || !$this->hasDischargeAdvice($zyh)) {
            return;
        }

        $this->checkTimedSignedDocument24h(
            $context,
            $zyh,
            $ruleId,
            $dischargeTime,
            '出院时间',
            '24小时入出院记录',
            $this->getRuleWordMapList(8051, '18,1'),
            $isRecheck,
            null,
            true
        );
    }

    /**
     * 死亡后24小时内完成24小时入院死亡记录。
     *
     * @param string $zyh
     * @param array $patientInfo
     * @param array $context
     * @param bool $isRecheck
     * @return void
     */
    private function checkDeath24hAdmissionDeathRecord($zyh, array $patientInfo, array $context, $isRecheck = false)
    {
        $ruleId = 1000;
        if (empty($context['case_rule_map'][$ruleId])) {
            return;
        }

        $dischargeTime = $this->getDischargeTime($zyh, $patientInfo);
        $admissionTime = $this->getAdmissionTime($zyh, $patientInfo);
        if ($dischargeTime === '' || $admissionTime === '' || $this->getDateDiffDays($admissionTime, $dischargeTime) > 1) {
            return;
        }

        if ($this->hasMedicalRecord($zyh, ['288']) || !$this->hasDeathAdvice($zyh)) {
            return;
        }

        $this->checkTimedSignedDocument24h(
            $context,
            $zyh,
            $ruleId,
            $dischargeTime,
            '出院时间',
            '24小时入院死亡记录',
            $this->getRuleWordMapList(8067, '18,288'),
            $isRecheck,
            null,
            true
        );
    }

    /**
     * 出院前一日或当日无上级医师查房记录。
     *
     * @param string $zyh
     * @param array $patientInfo
     * @param array $context
     * @param bool $isRecheck
     * @return void
     */
    private function checkDischargeSuperiorRoundDay($zyh, array $patientInfo, array $context, $isRecheck = false)
    {
        $ruleId = 1051;
        if (empty($context['case_rule_map'][$ruleId])) {
            return;
        }

        $dischargeTime = $this->getDischargeTime($zyh, $patientInfo);
        $admissionTime = $this->getAdmissionTime($zyh, $patientInfo);
        if ($dischargeTime === '' || $admissionTime === '') {
            return;
        }

        if ($this->getDateDiffDays($admissionTime, $dischargeTime) <= 1) {
            return;
        }

        if ($this->hasDeathAdvice($zyh) || !$this->hasDischargeAdvice($zyh)) {
            return;
        }

        $departmentCode = (string)ZY_BRRY::query()->where('ZYH', '=', $zyh)->value('BRKS');
        if ($departmentCode !== '' && in_array($departmentCode, $this->getRuleWordMapList(8057, ''), true)) {
            return;
        }

        $deadline = strtotime(date('Y-m-d 23:59:59', strtotime($dischargeTime)));
        if ($deadline === false) {
            return;
        }

        if (!$isRecheck) {
            $warningWindowSeconds = (int)ShizhongWarningConfig::getValue('discharge_superior_round_warning_window_hours', '2') * 3600;
            $warningWindowSeconds = $warningWindowSeconds > 0 ? $warningWindowSeconds : 2 * 3600;
            $remainingSeconds = $deadline - time();
            if ($remainingSeconds <= 0 || $remainingSeconds > $warningWindowSeconds) {
                return;
            }
        }

        $startTime = date('Y-m-d 00:00:00', strtotime($dischargeTime) - 24 * 3600);
        $endTime = date('Y-m-d 23:59:59', strtotime($dischargeTime));
        $dataId = 'discharge_superior_round_day:' . $zyh . ':' . strtotime(date('Y-m-d', strtotime($dischargeTime)));
        $dataType = 'discharge_superior_round_day';
        $status = $this->getDischargeSuperiorRoundDayStatus($zyh, $startTime, $endTime, $this->getSuperiorRoundConfig());

        if ($status['resolved']) {
            $this->setWarningResolved($zyh, $ruleId, $status['effective'], $dataId, $dataType);
            return;
        }

        $messageBasis = [
            '出院时间【' . $dischargeTime . '】',
        ];

        if (!empty($status['has_unsigned_record'])) {
            $messageBasis[] = '出院前一日或当日上级医师查房记录【已创建，未签名】';
        } else {
            $messageBasis[] = '出院前一日或当日上级医师查房记录【未创建】';
        }

        if (!empty($status['blbh'])) {
            //$messageBasis[] = '病历编号【' . $status['blbh'] . '】';
        }

        if ($isRecheck) {
            $this->recordPendingWarningUnresolved($zyh, $ruleId, '出院前一日或当日上级医师查房记录', $messageBasis);
            return;
        }

        $this->sendDeadlineWarning($context, $zyh, $ruleId, $deadline, '出院前一日或当日上级医师查房记录', $messageBasis, $dataId, $dataType);
    }

    /**
     * 判断出院前一日或当日上级医师查房记录是否完成。
     *
     * @param string $zyh
     * @param string $startTime
     * @param string $endTime
     * @param array $config
     * @return array
     */
    private function getDischargeSuperiorRoundDayStatus($zyh, $startTime, $endTime, array $config)
    {
        $timeField = $config['time_field'];
        $signTimeField = $config['sign_time_field'];
        $records = EMR_BL_BL01::query()
            ->where('JZHM', '=', $zyh)
            ->whereIn('MBLB', $config['record_template_types'])
            ->where($timeField, '>=', $startTime)
            ->where($timeField, '<=', $endTime)
            //->where('BLZT', '<>', 9)
            ->orderBy($timeField, 'asc')
            ->get();

        $hasMatchedRecord = false;
        $hasUnsignedRecord = false;
        $matchedBlbh = '';
        $deadline = strtotime($endTime);

        foreach ($records as $record) {
            $recordTitle = (string)($record->BLMC ?? '');
            if ($this->textContainsAnyKeyword($recordTitle, $config['exclude_title_keywords'])) {
                continue;
            }

            if (!$this->textContainsAnyKeyword($recordTitle, $config['superior_title_keywords'])) {
                continue;
            }

            $hasMatchedRecord = true;
            $matchedBlbh = (string)($record->BLBH ?? $matchedBlbh);
            $signTime = $this->getMedicalRecordSignTime($record, $signTimeField);
            if ($signTime === '' || strtotime($signTime) === false) {
                $hasUnsignedRecord = true;
                continue;
            }

            return [
                'resolved' => true,
                'effective' => strtotime($signTime) <= $deadline ? 1 : 0,
                'has_matched_record' => true,
                'has_unsigned_record' => false,
                'blbh' => (string)($record->BLBH ?? ''),
                'sign_time' => $signTime,
            ];
        }

        return [
            'resolved' => false,
            'effective' => 0,
            'has_matched_record' => $hasMatchedRecord,
            'has_unsigned_record' => $hasUnsignedRecord,
            'blbh' => $matchedBlbh,
            'sign_time' => '',
        ];
    }

    /**
     * 同步在院危急值24小时预警。
     *
     * @return array
     */
    private function syncCriticalValue24Warnings()
    {
        $context = $this->buildSyncContext(true);
        $startTime = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $endTime = date('Y-m-d H:i:s', strtotime('-22 hours'));
        $criticalValues = $this->getCriticalValueWarningItems($startTime, $endTime);

        $this->resetSyncStats(count($criticalValues), count($criticalValues), 0, 0);
        $this->syncStats['summary_unit'] = '条危急值';
        $this->line('  在院危急值预警：' . $startTime . ' 至 ' . $endTime . '，危急值 ' . count($criticalValues) . ' 条');

        foreach ($criticalValues as $criticalValue) {
            $zyh = (string)($criticalValue['ZYH'] ?? '');
            if ($zyh === '') {
                $this->syncStats['skipped']++;
                $this->addSyncDetail('skipped_detail', '危急值缺少住院号，已跳过');
                continue;
            }

            try {
                $this->syncStats['processed']++;
                $this->syncHomeDataForQuality($zyh, $context);
                $this->checkCriticalValueRecord24h($zyh, $context, $criticalValue);
            } catch (\Throwable $e) {
                $this->syncStats['errors']++;
                $this->addSyncDetail('error_detail', '住院号' . $zyh . '：危急值预警失败，' . $e->getMessage());
                Log::error('shizhong_warning_sync::危急值单条预警失败', [
                    'zyh' => $zyh,
                    'critical_value' => $criticalValue,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        return $this->syncStats;
    }

    /**
     * 查询在院且接收时间进入22至24小时窗口的危急值。
     *
     * @param string $startTime
     * @param string $endTime
     * @return array
     */
    private function getCriticalValueWarningItems($startTime, $endTime)
    {
        $wjzTable = (new WJZ())->getTable();
        $brryTable = (new ZY_BRRY())->getTable();

        $rows = WJZ::query()
            ->from($wjzTable . ' as wjz')
            ->select(['wjz.ZYH', 'wjz.WJZSJ', 'wjz.WJZNR'])
            ->join($brryTable . ' as zy_brry', 'wjz.ZYH', '=', 'zy_brry.ZYH')
            ->where(function ($query) {
                $query->whereNull('zy_brry.AAC01')->orWhere('zy_brry.AAC01', '=', '');
            })
            ->where('wjz.WJZSJ', '>=', $startTime)
            ->where('wjz.WJZSJ', '<=', $endTime)
            ->whereNotNull('wjz.WJZSJ')
            ->orderBy('wjz.WJZSJ', 'asc')
            ->get()
            ->toArray();

        foreach ($rows as &$row) {
            $row['data_id'] = $this->buildCriticalValueDataId($row['ZYH'], $row['WJZSJ'], $row['WJZNR'] ?? '');
            $row['data_type'] = 'critical_value';
            $row['WJZNR_TEXT'] = (string)($row['WJZNR'] ?? '');
        }

        return $rows;
    }

    /**
     * 检查单条危急值接收后24小时内是否完成危急值记录。
     *
     * @param string $zyh
     * @param array $context
     * @param array|null $criticalValue
     * @param bool $isRecheck
     * @param string $dataId
     * @param string $dataType
     * @return void
     */
    private function checkCriticalValueRecord24h($zyh, array $context, array $criticalValue = null, $isRecheck = false, $dataId = '', $dataType = '')
    {
        $ruleId = 1011;
        if (empty($context['case_rule_map'][$ruleId])) {
            return;
        }

        if ($criticalValue === null) {
            $criticalValue = $this->getCriticalValueByDataId($zyh, $dataId);
        }

        if (empty($criticalValue)) {
            return;
        }

        $criticalTime = (string)($criticalValue['WJZSJ'] ?? '');
        if ($criticalTime === '' || $this->isEmptyTime($criticalTime) || strtotime($criticalTime) === false) {
            return;
        }

        $dataId = $dataId !== '' ? $dataId : (string)($criticalValue['data_id'] ?? $this->buildCriticalValueDataId($zyh, $criticalTime));
        $dataType = $dataType !== '' ? $dataType : (string)($criticalValue['data_type'] ?? 'critical_value');
        $deadline = strtotime($criticalTime) + 24 * 3600;
        $recordTypes = $this->getRuleWordMapList(8010, '294');
        $timeField = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $timeField = !empty($timeField) ? $timeField : 'ZXSJ';
        $recordKeyword = RuleWordMap::query()->where('id', '=', 8030)->value('keyword');
        $recordKeyword = !empty($recordKeyword) ? $recordKeyword : '危急值记录';
        $signTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $signTimeField = !empty($signTimeField) ? $signTimeField : 'first_blsy_time';

        $records = EMR_BL_BL01::query()
            ->where('JZHM', '=', $zyh)
            ->whereIn('BLLB', $recordTypes)
            ->where('BLMC', 'like', '%' . $recordKeyword . '%')
            ->where($timeField, '>=', date('Y-m-d H:i', strtotime($criticalTime)))
            ->where($timeField, '<=', date('Y-m-d H:i:s', $deadline))
            //->where('BLZT', '<>', 9)
            ->orderBy($timeField, 'asc')
            ->get();

        $hasUnsignedRecord = false;
        foreach ($records as $record) {
            $signTime = $record->{$signTimeField} ?? '';
            if ($signTime === '' || strtotime($signTime) === false) {
                $hasUnsignedRecord = true;
                continue;
            }

            $this->setWarningResolved($zyh, $ruleId, 1, $dataId, $dataType);
            return;
        }

        $contentText = (string)($criticalValue['WJZNR_TEXT'] ?? $this->getCriticalValueContentText($zyh, $criticalTime));
        $messageBasis = [
            '危急值【' . $contentText . '】',
            '接收时间【' . $criticalTime . '】',
        ];

        if ($hasUnsignedRecord) {
            $messageBasis[] = '危急值记录【已创建，未签名】';
        } else {
            $messageBasis[] = '危急值记录【未创建】';
        }

        if ($isRecheck) {
            $this->recordPendingWarningUnresolved($zyh, $ruleId, '危急值记录', $messageBasis);
            return;
        }

        $this->sendDeadlineWarning($context, $zyh, $ruleId, $deadline, '危急值记录', $messageBasis, $dataId, $dataType);
    }

    /**
     * 根据预警日志中的数据ID还原危急值事件。
     *
     * @param string $zyh
     * @param string $dataId
     * @return array|null
     */
    private function getCriticalValueByDataId($zyh, $dataId)
    {
        $prefix = 'wjz:' . $zyh . ':';
        if ($dataId === '' || strpos($dataId, $prefix) !== 0) {
            return null;
        }

        $criticalTimestamp = (int)substr($dataId, strlen($prefix));
        if ($criticalTimestamp <= 0) {
            return null;
        }

        $criticalTime = date('Y-m-d H:i:s', $criticalTimestamp);

        return [
            'ZYH' => $zyh,
            'WJZSJ' => $criticalTime,
            'data_id' => $dataId,
            'data_type' => 'critical_value',
            'WJZNR_TEXT' => $this->getCriticalValueContentText($zyh, $criticalTime),
        ];
    }

    /**
     * 生成危急值事件数据ID。
     *
     * @param string $zyh
     * @param string $criticalTime
     * @return string
     */
    private function buildCriticalValueDataId($zyh, $criticalTime, $content = '')
    {
        $dataId = 'wjz:' . $zyh . ':' . strtotime($criticalTime);
        if ($content !== '') {
            $dataId .= ':' . md5((string)$content);
        }

        return substr($dataId, 0, 100);
    }

    /**
     * 获取同一危急值接收时间下的危急值内容。
     *
     * @param string $zyh
     * @param string $criticalTime
     * @return string
     */
    private function getCriticalValueContentText($zyh, $criticalTime)
    {
        $contents = WJZ::query()
            ->where('ZYH', '=', $zyh)
            ->where('WJZSJ', '=', $criticalTime)
            ->pluck('WJZNR')
            ->toArray();

        if (empty($contents)) {
            return '';
        }

        $text = '';
        foreach ($contents as $content) {
            if ($content !== null && $content !== '') {
                $text .= '(' . $content . ')';
            }
        }

        return $text;
    }

    /**
     * 同步在院输血24小时预警。
     *
     * @return array
     */
    private function syncTransfusion24Warnings()
    {
        $context = $this->buildSyncContext(true);
        $startTime = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $endTime = date('Y-m-d H:i:s', strtotime('-22 hours'));
        $transfusions = $this->getTransfusionWarningItems($startTime, $endTime);

        $this->resetSyncStats(count($transfusions), count($transfusions), 0, 0);
        $this->syncStats['summary_unit'] = '条输血数据';
        $this->line('  在院输血预警：' . $startTime . ' 至 ' . $endTime . '，输血数据 ' . count($transfusions) . ' 条');

        foreach ($transfusions as $transfusion) {
            $zyh = (string)($transfusion['ZYH'] ?? '');
            if ($zyh === '') {
                $this->syncStats['skipped']++;
                $this->addSyncDetail('skipped_detail', '输血数据缺少住院号，已跳过');
                continue;
            }

            try {
                $this->syncStats['processed']++;
                $this->syncHomeDataForQuality($zyh, $context);
                $this->checkTransfusionRecord24h($zyh, $context, $transfusion);
            } catch (\Throwable $e) {
                $this->syncStats['errors']++;
                $this->addSyncDetail('error_detail', '住院号' . $zyh . '：输血预警失败，' . $e->getMessage());
                Log::error('shizhong_warning_sync::输血单条预警失败', [
                    'zyh' => $zyh,
                    'transfusion' => $transfusion,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }

        return $this->syncStats;
    }

    /**
     * 查询在院且输血结束时间进入22至24小时窗口的输血数据。
     *
     * @param string $startTime
     * @param string $endTime
     * @return array
     */
    private function getTransfusionWarningItems($startTime, $endTime)
    {
        $transfusionTable = (new ZY_SS())->getTable();
        $brryTable = (new ZY_BRRY())->getTable();

        $rows = ZY_SS::query()
            ->from($transfusionTable . ' as zy_ss')
            ->select(['zy_ss.id', 'zy_ss.ZYH', 'zy_ss.SXXH', 'zy_ss.XX', 'zy_ss.SZL', 'zy_ss.CFX', 'zy_ss.KSSJ', 'zy_ss.JSSJ', 'zy_ss.SFZF'])
            ->join($brryTable . ' as zy_brry', 'zy_ss.ZYH', '=', 'zy_brry.ZYH')
            ->where(function ($query) {
                $query->whereNull('zy_brry.AAC01')->orWhere('zy_brry.AAC01', '=', '');
            })
            ->where('zy_ss.JSSJ', '>=', $startTime)
            ->where('zy_ss.JSSJ', '<=', $endTime)
            ->whereNotNull('zy_ss.JSSJ')
            ->orderBy('zy_ss.JSSJ', 'asc')
            ->orderBy('zy_ss.id', 'asc')
            ->get()
            ->toArray();

        foreach ($rows as &$row) {
            $row['data_id'] = $this->buildTransfusionDataId($row['ZYH'], $row['id'] ?? '', $row['JSSJ'] ?? '', $row['CFX'] ?? '');
            $row['data_type'] = 'transfusion';
        }

        return $rows;
    }

    /**
     * 检查单条输血结束后24小时内是否完成输血记录。
     *
     * @param string $zyh
     * @param array $context
     * @param array|null $transfusion
     * @param bool $isRecheck
     * @param string $dataId
     * @param string $dataType
     * @return void
     */
    private function checkTransfusionRecord24h($zyh, array $context, array $transfusion = null, $isRecheck = false, $dataId = '', $dataType = '')
    {
        $ruleId = 1012;
        if (empty($context['case_rule_map'][$ruleId])) {
            return;
        }

        if ($transfusion === null) {
            $transfusion = $this->getTransfusionByDataId($zyh, $dataId);
        }

        if (empty($transfusion)) {
            return;
        }

        $endTime = (string)($transfusion['JSSJ'] ?? '');
        if ($endTime === '' || $this->isEmptyTime($endTime) || strtotime($endTime) === false) {
            return;
        }

        if ($this->isExcludedTransfusion($zyh, $transfusion)) {
            return;
        }

        $dataId = $dataId !== '' ? $dataId : (string)($transfusion['data_id'] ?? $this->buildTransfusionDataId($zyh, $transfusion['id'] ?? '', $endTime, $transfusion['CFX'] ?? ''));
        $dataType = $dataType !== '' ? $dataType : (string)($transfusion['data_type'] ?? 'transfusion');
        $deadline = strtotime($endTime) + 24 * 3600;
        $recordTemplateTypes = $this->getRuleWordMapList(8031, '45');
        $timeField = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $timeField = !empty($timeField) ? $timeField : 'ZXSJ';
        $signTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $signTimeField = !empty($signTimeField) ? $signTimeField : 'first_blsy_time';

        $records = EMR_BL_BL01::query()
            ->where('JZHM', '=', $zyh)
            ->whereIn('MBLB', $recordTemplateTypes)
            ->where($timeField, '>=', date('Y-m-d H:i:s', strtotime($endTime)))
            ->where($timeField, '<=', date('Y-m-d H:i:s', $deadline))
            //->where('BLZT', '<>', 9)
            ->orderBy($timeField, 'asc')
            ->get();

        $hasUnsignedRecord = false;
        foreach ($records as $record) {
            $signTime = $record->{$signTimeField} ?? '';
            if ($signTime === '' || strtotime($signTime) === false) {
                $hasUnsignedRecord = true;
                continue;
            }

            $this->setWarningResolved($zyh, $ruleId, 1, $dataId, $dataType);
            return;
        }

        $messageBasis = [
            '输血【' . (string)($transfusion['CFX'] ?? '') . '】',
            '输血量【' . (string)($transfusion['SZL'] ?? '') . '】',
            '结束时间【' . $endTime . '】',
        ];

        if ($hasUnsignedRecord) {
            $messageBasis[] = '输血记录【已创建，未签名】';
        } else {
            $messageBasis[] = '输血记录【未创建】';
        }

        if ($isRecheck) {
            $this->recordPendingWarningUnresolved($zyh, $ruleId, '输血记录', $messageBasis);
            return;
        }

        $this->sendDeadlineWarning($context, $zyh, $ruleId, $deadline, '输血记录', $messageBasis, $dataId, $dataType);
    }

    /**
     * 根据预警日志中的数据ID还原输血数据。
     *
     * @param string $zyh
     * @param string $dataId
     * @return array|null
     */
    private function getTransfusionByDataId($zyh, $dataId)
    {
        $prefix = 'sx:' . $zyh . ':';
        if ($dataId === '' || strpos($dataId, $prefix) !== 0) {
            return null;
        }

        $transfusionId = substr($dataId, strlen($prefix));
        if (strpos($transfusionId, ':') !== false) {
            $transfusionId = substr($transfusionId, 0, strpos($transfusionId, ':'));
        }

        $query = ZY_SS::query()->where('ZYH', '=', $zyh);
        if ($transfusionId !== '') {
            $query->where('id', '=', $transfusionId);
        }

        $transfusion = $query->first(['id', 'ZYH', 'SXXH', 'XX', 'SZL', 'CFX', 'KSSJ', 'JSSJ', 'SFZF']);
        if (empty($transfusion)) {
            return null;
        }

        $transfusion = $transfusion->toArray();
        $transfusion['data_id'] = $dataId;
        $transfusion['data_type'] = 'transfusion';

        return $transfusion;
    }

    /**
     * 判断输血数据是否符合rule1012的排除逻辑。
     *
     * @param string $zyh
     * @param array $transfusion
     * @return bool
     */
    private function isExcludedTransfusion($zyh, array $transfusion)
    {
        $cfx = (string)($transfusion['CFX'] ?? '');
        $startTime = (string)($transfusion['KSSJ'] ?? '');
        $excludeKeywords = $this->getRuleWordMapList(8053, '手术自体血');
        if ($this->textContainsAnyKeyword($cfx, $excludeKeywords)) {
            return true;
        }

        if ($startTime === '' || strtotime($startTime) === false) {
            return false;
        }

        $operationRecords = EMR_BL_BL01::query()
            ->where('EMR_BL_BL01.JZHM', '=', $zyh)
            ->where('EMR_BL_BL01.MBLB', '=', 306)
            ->where('EMR_BL_BL01.ZXSJ', '>=', date('Y-m-d 00:00:00', strtotime($startTime)))
            ->where('EMR_BL_BL01.ZXSJ', '<=', date('Y-m-d 23:59:59', strtotime($startTime)))
            ->leftJoin('EMR_BL_BLXG', 'EMR_BL_BL01.BLBH', '=', 'EMR_BL_BLXG.BLBH')
            ->get(['EMR_BL_BLXG.HJNR'])
            ->toArray();

        $keywordGroup1 = $this->getRuleWordMapList(8054, '输');
        $keywordGroup2 = $this->getRuleWordMapList(8055, '红细胞,血浆,凝血因子,血小板');
        $keywordGroup3 = $this->getRuleWordMapList(8056, '输血');

        foreach ($operationRecords as $record) {
            $content = (string)($record['HJNR'] ?? '');
            if ($this->textContainsAnyKeyword($content, $keywordGroup3)) {
                return true;
            }

            if ($this->textContainsAnyKeyword($content, $keywordGroup1) && $this->textContainsAnyKeyword($content, $keywordGroup2)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 生成输血数据预警ID。
     *
     * @param string $zyh
     * @param string|int $id
     * @param string $endTime
     * @param string $content
     * @return string
     */
    private function buildTransfusionDataId($zyh, $id, $endTime, $content = '')
    {
        $dataId = 'sx:' . $zyh . ':';
        if ($id !== '') {
            $dataId .= $id;
        } else {
            $dataId .= strtotime($endTime) . ':' . md5((string)$content);
        }

        return substr($dataId, 0, 100);
    }

    /**
     * 检查指定24小时窗口内文书是否已签名。
     *
     * @param array $context
     * @param string $zyh
     * @param int $ruleId
     * @param string $baseTime
     * @param string $baseTimeName
     * @param string $documentName
     * @param array $bllbList
     * @param bool $isRecheck
     * @param callable|null $recordFilter
     * @return void
     */
    private function checkTimedSignedDocument24h(array $context, $zyh, $ruleId, $baseTime, $baseTimeName, $documentName, array $bllbList, $isRecheck = false, callable $recordFilter = null, $allowBeforeBaseTime = false)
    {
        $baseTimestamp = strtotime($baseTime);
        if ($baseTimestamp === false) {
            return;
        }

        $deadline = $baseTimestamp + 24 * 3600;
        $timeField = RuleWordMap::query()->where('id', '=', 8011)->value('keyword');
        $timeField = !empty($timeField) ? $timeField : 'ZXSJ';
        $timeField = 'CJSJ';
        $signTimeField = RuleWordMap::query()->where('id', '=', 8002)->value('keyword');
        $signTimeField = !empty($signTimeField) ? $signTimeField : 'frist_blsy_time';

        $recordQuery = EMR_BL_BL01::query()
            ->where('JZHM', '=', $zyh)
            ->whereIn('BLLB', $bllbList);

        /* if (!$allowBeforeBaseTime) {
            $recordQuery->where($timeField, '>=', date('Y-m-d H:i:s', $baseTimestamp));
        } */

        $records = $recordQuery
            //->where($timeField, '<=', date('Y-m-d H:i:s', $deadline))
            //->where('BLZT', '<>', 9)
            ->orderBy($timeField, 'asc')
            ->get();

        $hasMatchedRecord = false;
        $hasUnsignedRecord = false;
        $messageBasis = [$baseTimeName . '【' . $baseTime . '】'];

        foreach ($records as $record) {
            if ($recordFilter !== null && !$recordFilter($record)) {
                continue;
            }

            $hasMatchedRecord = true;
            $signTime = $record->{$signTimeField} ?? '';
            if ($signTime === '' || strtotime($signTime) === false) {
                $hasUnsignedRecord = true;
                continue;
            }

            $this->setWarningResolved($zyh, $ruleId, 1);
            return;
        }

        if ($hasUnsignedRecord) {
            $messageBasis[] = $documentName . '【已创建，未签名】';
        } else if (!$hasMatchedRecord) {
            $messageBasis[] = $documentName . '【未创建】';
        } else {
            $messageBasis[] = $baseTimeName . '后24小时内未完成“' . $documentName . '”';
        }

        if ($isRecheck) {
            $this->recordPendingWarningUnresolved($zyh, $ruleId, $documentName, $messageBasis);
            return;
        }

        $this->sendDeadlineWarning($context, $zyh, $ruleId, $deadline, $documentName, $messageBasis);
    }

    /**
     * 获取出院时间。
     *
     * @param string $zyh
     * @param array $patientInfo
     * @return string
     */
    private function getDischargeTime($zyh, array $patientInfo)
    {
        $dischargeTime = $patientInfo['AAC01'] ?? '';
        if ($dischargeTime === '') {
            $dischargeTime = ZY_BRRY::query()->where('ZYH', '=', $zyh)->value('AAC01') ?: '';
        }

        return $this->isEmptyTime($dischargeTime) ? '' : (string)$dischargeTime;
    }

    /**
     * 计算两个日期相差天数。
     *
     * @param string $startTime
     * @param string $endTime
     * @return int
     */
    private function getDateDiffDays($startTime, $endTime)
    {
        return (int)((strtotime(date('Y-m-d', strtotime($endTime))) - strtotime(date('Y-m-d', strtotime($startTime)))) / (24 * 3600));
    }

    /**
     * 判断是否存在出院或离院医嘱。
     *
     * @param string $zyh
     * @return bool
     */
    private function hasDischargeAdvice($zyh)
    {
        return $this->hasNestedMedicalAdvice($zyh, $this->getRuleWordMapList(8000, '出院,离院'), ['死亡']);
    }

    /**
     * 判断是否存在死亡医嘱。
     *
     * @param string $zyh
     * @return bool
     */
    private function hasDeathAdvice($zyh)
    {
        return $this->hasNestedMedicalAdvice($zyh, $this->getRuleWordMapList(8003, '死亡'));
    }

    /**
     * 判断医嘱中是否命中指定关键词。
     *
     * @param string $zyh
     * @param array $keywords
     * @param array $excludeKeywords
     * @return bool
     */
    private function hasNestedMedicalAdvice($zyh, array $keywords, array $excludeKeywords = [])
    {
        $should = [];
        foreach ($keywords as $keyword) {
            if ($keyword === '') {
                continue;
            }

            $should[] = [
                'nested' => [
                    'path' => 'yzb',
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['match_phrase' => ['yzb.YZMC' => $keyword]],
                            ],
                        ],
                    ],
                ],
            ];
        }

        if (empty($should)) {
            return false;
        }

        $mustNot = [];
        foreach ($excludeKeywords as $keyword) {
            if ($keyword === '') {
                continue;
            }

            $mustNot[] = [
                'nested' => [
                    'path' => 'yzb',
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['match_phrase' => ['yzb.YZMC' => $keyword]],
                            ],
                        ],
                    ],
                ],
            ];
        }

        try {
            $yzbEs = new ElasticsearchService('yz_serach_2023');
            $query = $yzbEs->clearMust()
                ->queryByMust(['term' => ['ZYH' => $zyh]])
                ->queryByShouldBatch($should)
                ->minimumShouldMatch();

            if (!empty($mustNot)) {
                $query->queryByMustNotBatch($mustNot);
            }

            $res = app('es')->search($query->getParams());
            $yzbData = $yzbEs->getDataByEs($res);
        } catch (\Throwable $e) {
            Log::error('shizhong_warning_sync::查询医嘱失败', [
                'zyh' => $zyh,
                'message' => $e->getMessage(),
            ]);
            return false;
        }

        return !empty($yzbData[0]);
    }

    /**
     * 判断是否排除日间入出院记录。
     *
     * @param mixed $record
     * @return bool
     */
    private function isExcludedDayAdmissionDischargeRecord($record)
    {
        $recordName = $record->BLMC ?? '';
        if ($recordName === '') {
            return false;
        }

        if (!$this->textContainsAnyKeyword($recordName, $this->getRuleWordMapList(8023, '日间'))) {
            return false;
        }

        return $this->textContainsAnyKeyword($recordName, $this->getRuleWordMapList(8024, '入出院记录,出入院记录'));
    }

    /**
     * 判断病程中是否已有要求出院且已有入院/24小时记录。
     *
     * @param string $zyh
     * @return bool
     */
    private function hasRequiredDischargeProgressAndAdmissionRecord($zyh)
    {
        $latestProgress = EMR_BL_BL01::query()
            ->where('JZHM', '=', $zyh)
            ->where('BLLB', '=', 294)
            //->where('BLZT', '<>', 9)
            ->orderBy('ZXSJ', 'desc')
            ->first();
        if (empty($latestProgress)) {
            return false;
        }

        $content = EMR_BL_BLXG::query()->where('BLBH', '=', $latestProgress->BLBH)->value('HJNR');
        if (empty($content) || !$this->textContainsAnyKeyword($content, $this->getRuleWordMapList(8078, '要求出院'))) {
            return false;
        }

        return EMR_BL_BL01::query()
            ->where('JZHM', '=', $zyh)
            ->whereIn('BLLB', ['292', '18'])
            //->where('BLZT', '<>', 9)
            ->exists();
    }

    /**
     * 从死亡相关病历中提取死亡时间。
     *
     * @param string $zyh
     * @return string
     */
    private function getDeathRecordTime($zyh)
    {
        $bllbList = $this->getRuleWordMapList(8067, '288,18');
        $blbh = EMR_BL_BL01::query()
            ->where('JZHM', '=', $zyh)
            ->whereIn('BLLB', $bllbList)
            ->value('BLBH');
        if (empty($blbh)) {
            return '';
        }

        $content = EMR_BL_BLXG::query()->where('BLBH', '=', $blbh)->value('HJNR');
        if (empty($content)) {
            return '';
        }

        $deathTime = '';
        if (preg_match('/死亡时间：(?:\{)?(\d{4}-\d{2}-\d{2} \d{2}:\d{2})(?:\})?/', $content, $matches)) {
            $deathTime = $matches[1];
        } else if (preg_match('/死亡时间[:：].*?(\d{4}[-\/]\d{1,2}[-\/]\d{1,2}\s+\d{1,2}:\d{1,2})/', $content, $matches)) {
            $deathTime = $matches[1];
        } else if (preg_match('/于(\d{4}[-\/]\d{1,2}[-\/]\d{1,2}\s+\d{1,2}:\d{1,2})临床死亡/', $content, $matches)) {
            $deathTime = $matches[1];
        } else if (preg_match('/死亡时间：\{(\d{4}[-\/]\d{1,2}[-\/]\d{1,2}\s+\d{1,2}:\d{1,2})/', $content, $matches)) {
            $deathTime = $matches[1];
        } else if (preg_match('/死亡时间：(\d{4}年\d{1,2}月\d{1,2}日 \d{1,2}:\d{1,2}:\d{1,2})/', $content, $matches)) {
            $deathTime = $matches[1];
        } else if (preg_match('/死亡时间：(\d{4}年\d{1,2}月\d{1,2}日 \d{1,2}:\d{1,2})/', $content, $matches)) {
            $deathTime = $matches[1];
        }

        if ($deathTime === '') {
            return '';
        }

        $deathTime = str_replace(['年', '月', '日'], ['-', '-', ''], $deathTime);
        $timestamp = strtotime($deathTime);
        if ($timestamp === false) {
            return '';
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * 获取规则词配置列表。
     *
     * @param int $id
     * @param string $default
     * @return array
     */
    private function shouldSkipForObstetricsSpecialLogic($zyh)
    {
        $obstetricsDepartmentCodes = $this->getRuleWordMapList(8025, '');
        if (empty($obstetricsDepartmentCodes)) {
            return false;
        }

        $departmentCode = ZY_BRRY::query()->where('ZYH', '=', $zyh)->value('BRKS') ?: '';
        if ($departmentCode === '' || !in_array((string)$departmentCode, array_map('strval', $obstetricsDepartmentCodes), true)) {
            return false;
        }

        $obstetricsDiagnosisKeywords = $this->getRuleWordMapList(
            8009,
            '先兆子痫,前置胎盘,先兆早产,先兆流产,妊娠剧吐,稽留流产,妊娠期肝内胆汁淤积综合症,妊娠合并子宫瘢痕,臀先露,肩先露,子宫复旧不全,晚期产后出血,妊娠合并宫颈功能不全,胎儿生长发育迟缓,胎心监护异常,早期难免流产'
        );
        $diagnosisNames = ZY_RYZD::query()->where('ZYH', '=', $zyh)->pluck('JBMC')->toArray();

        foreach ($diagnosisNames as $diagnosisName) {
            if ($this->textContainsAnyKeyword((string)$diagnosisName, $obstetricsDiagnosisKeywords)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 获取规则词配置列表。
     *
     * @param int $id
     * @param string $default
     * @return array
     */
    private function getRuleWordMapList($id, $default)
    {
        $keyword = RuleWordMap::query()->where('id', '=', $id)->value('keyword');
        $keyword = !empty($keyword) ? $keyword : $default;
        $keyword = str_replace('，', ',', $keyword);

        return array_values(array_filter(array_map('trim', explode(',', $keyword)), function ($item) {
            return $item !== '';
        }));
    }

    /**
     * 判断文本是否包含任一关键词。
     *
     * @param string $text
     * @param array $keywords
     * @return bool
     */
    private function textContainsAnyKeyword($text, array $keywords)
    {
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && strpos($text, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取入院时间。
     *
     * @param string $zyh
     * @param array $patientInfo
     * @return string
     */
    private function getAdmissionTime($zyh, array $patientInfo)
    {
        $admissionTime = $patientInfo['AAB01'] ?? '';
        if ($admissionTime === '') {
            $admissionTime = ZY_BRRY::query()->where('ZYH', '=', $zyh)->value('AAB01') ?: '';
        }

        return $this->isEmptyTime($admissionTime) ? '' : $admissionTime;
    }

    /**
     * 判断指定病历类型是否已存在。
     *
     * @param string $zyh
     * @param array $bllbList
     * @return bool
     */
    private function hasMedicalRecord($zyh, array $bllbList)
    {
        return EMR_BL_BL01::query()
            ->where('JZHM', '=', $zyh)
            ->whereIn('BLLB', $bllbList)
            //->where('BLZT', '<>', 9)
            ->exists();
    }

    /**
     * 发送临近截止时间的预警消息。
     *
     * @param array $context
     * @param string $zyh
     * @param int $ruleId
     * @param int $deadline
     * @param string $documentName
     * @param array $messageBasis
     * @return bool
     */
    private function sendDeadlineWarning(array $context, $zyh, $ruleId, $deadline, $documentName, array $messageBasis, $dataId = '', $dataType = '')
    {
        $remainingSeconds = $deadline - time();
        if ($remainingSeconds <= 0) {
            $this->syncStats['warnings_expired']++;
            $this->addSyncDetail(
                'warning_detail',
                '住院号' . $zyh . '：规则' . $ruleId . '，' . $documentName . '已到截止时间，未发送临近提醒；依据：' . implode('；', $messageBasis)
            );
            return false;
        }

        $content = '请在' . $this->formatRemainingTime($remainingSeconds) . '内完成' . $documentName;
        $context['case_service']->sendMsg($zyh, $content, $ruleId, $messageBasis, $dataId, $dataType);
        $this->syncStats['warnings_sent']++;
        $this->addSyncDetail(
            'warning_detail',
            '住院号' . $zyh . '：规则' . $ruleId . '，' . $content . '；依据：' . implode('；', $messageBasis)
        );

        return true;
    }

    /**
     * 记录未整改预警复查后仍未整改的情况。
     *
     * @param string $zyh
     * @param int $ruleId
     * @param string $documentName
     * @param array $messageBasis
     * @return void
     */
    private function recordPendingWarningUnresolved($zyh, $ruleId, $documentName, array $messageBasis)
    {
        $this->syncStats['pending_unresolved']++;
        $this->addSyncDetail(
            'warning_detail',
            '住院号' . $zyh . '：规则' . $ruleId . '，' . $documentName . '复查仍未整改；依据：' . implode('；', $messageBasis)
        );
    }

    /**
     * 标记预警消息已整改。
     *
     * @param string $zyh
     * @param int $ruleId
     * @param int $isEffective
     * @return bool
     */
    private function setWarningResolved($zyh, $ruleId, $isEffective = 0, $dataId = '', $dataType = '')
    {
        $query = QualitySendMsgLog::query()
            ->where('zyh', '=', $zyh)
            ->where('rule_id', '=', $ruleId);

        if ($dataId !== '') {
            $query->where('data_id', '=', $dataId)->where('data_type', '=', $dataType);
        }

        $affected = $query->update([
                'status' => 1,
                'is_youxiao' => $isEffective,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        if ($affected > 0) {
            $this->syncStats['warnings_resolved'] += $affected;
            $this->addSyncDetail('resolved_detail', '住院号' . $zyh . '：规则' . $ruleId . '已标记整改，有效=' . $isEffective . '，影响' . $affected . '条');
        }

        return $affected > 0;
    }

    /**
     * 格式化剩余时间。
     *
     * @param int $seconds
     * @return string
     */
    private function formatRemainingTime($seconds)
    {
        if (function_exists('remainderTime')) {
            return remainderTime($seconds);
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return $hours . '小时' . $minutes . '分钟';
    }

    /**
     * 统一HIS返回字段大小写。
     *
     * @param array $row
     * @return array
     */
    private function normalizeRowKeys(array $row)
    {
        return array_change_key_case($row, CASE_UPPER);
    }

    /**
     * 按多个候选字段取值。
     *
     * @param array $row
     * @param array $keys
     * @param mixed $default
     * @return mixed
     */
    private function value(array $row, array $keys, $default = '')
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return $default;
    }

    /**
     * 从字典中取值。
     *
     * @param array $map
     * @param string $key
     * @return string
     */
    private function mapValue(array $map, $key)
    {
        return $key !== '' && isset($map[$key]) ? $map[$key] : '';
    }

    /**
     * 判断时间是否为空。
     *
     * @param mixed $time
     * @return bool
     */
    private function isEmptyTime($time)
    {
        return empty($time) || $time === '0000-00-00 00:00:00';
    }
}
