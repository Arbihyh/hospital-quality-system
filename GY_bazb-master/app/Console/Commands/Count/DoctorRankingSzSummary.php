<?php

namespace App\Console\Commands\Count;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 事中医生排名统计（基于 case_quality 实时计算）
 */
class DoctorRankingSzSummary extends Command
{
    /**
     * 命令签名
     *
     * 使用示例：
     * php artisan count:doctor_ranking_sz_summary 2025-01-01
     *
     * 第一个参数为起始入院日期（AAB01），可选，默认为当年 1 月 1 日。
     *
     * @var string
     */
    protected $signature = 'count:doctor_ranking_sz_summary {start_time?}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '根据 case_quality 按入院时间(AAB01)实时计算事中医生扣分并写入 doctor_ranking_sz_summary';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 执行命令
     *
     * @return int
     */
    public function handle(): int
    {
        // 起始入院日期，默认当年 1 月 1 日
        $startTime = $this->argument('start_time') ?: date('Y-01-01');

        // 将起始日期时间设为当天 00:00:00
        $startTime = date('Y-m-d 00:00:00', strtotime($startTime));
        

        // 如需全量重算，可打开下面一行（谨慎使用）
        // DB::statement('TRUNCATE TABLE doctor_ranking_sz_summary');

        /**
         * 统计逻辑说明：
         * 1. 从 case_quality 中按 ZYH 聚合计算总扣分 diff_score：
         *    - 普通规则：使用 case_rule.score
         *    - 设置规则：使用 rule_setting.score（rule_id > 1000000）
         *    - 仅统计未整改 / 未申诉 / 未忽略的缺陷
         * 2. 关联 patient_info，使用 AAB01 作为时间维度（事中不限制出院状态）；
         * 3. 关联 patient_info_v2，将 AEE01_CODE / AEE02_CODE / AEE03_CODE / AEE04_CODE 展开为医生编码；
         * 4. 将「医生编码 + 住院号 + 入院日期」的扣分写入 doctor_ranking_sz_summary；
         */
        $sql = "
            INSERT INTO doctor_ranking_sz_summary (
                code,
                zyh,
                score,
                record_date
            )
            SELECT
                doc_codes.code            AS code,
                cq_sum.zyh                AS zyh,
                cq_sum.diff_score         AS score,
                DATE(pi.AAB01)            AS record_date
            FROM
            (
                -- 1) 按 ZYH 聚合缺陷扣分（基于 case_quality 实时计算）
                SELECT
                    cq.JZHM AS zyh,
                    SUM(
                        CASE
                            WHEN cq.rule_id > 1000000 THEN rs.score
                            ELSE cr.score
                        END
                    ) AS diff_score
                FROM
                    case_quality cq
                    LEFT JOIN case_rule cr
                        ON cr.id = cq.rule_id
                        AND cq.rule_id < 1000000
                        AND cr.status = 1
                    LEFT JOIN rule_setting rs
                        ON rs.id + 1000000 = cq.rule_id
                        AND rs.status = 1
                    INNER JOIN patient_info pi
                        ON pi.MED_REC_ID = cq.JZHM
                WHERE
                    cq.is_correction = 0
                    AND cq.is_appeal = 0
                    AND cq.is_ignore = 0
                    -- 事中按入院时间维度统计
                    AND pi.AAB01 >= ?
                GROUP BY
                    cq.JZHM
            ) AS cq_sum
            INNER JOIN patient_info pi
                ON pi.MED_REC_ID = cq_sum.zyh
            INNER JOIN patient_info_v2 piv
                ON piv.ZYH = cq_sum.zyh
            -- 2) 将 AEE01_CODE/02/03/04 四类医生编码展开成多行
            INNER JOIN (
                SELECT ZYH, AEE01_CODE AS code FROM patient_info_v2 WHERE AEE01_CODE IS NOT NULL AND AEE01_CODE <> ''
                UNION ALL
                SELECT ZYH, AEE02_CODE AS code FROM patient_info_v2 WHERE AEE02_CODE IS NOT NULL AND AEE02_CODE <> ''
                UNION ALL
                SELECT ZYH, AEE03_CODE AS code FROM patient_info_v2 WHERE AEE03_CODE IS NOT NULL AND AEE03_CODE <> ''
                UNION ALL
                SELECT ZYH, AEE04_CODE AS code FROM patient_info_v2 WHERE AEE04_CODE IS NOT NULL AND AEE04_CODE <> ''
            ) AS doc_codes
                ON doc_codes.ZYH = cq_sum.zyh
            GROUP BY
                doc_codes.code,
                cq_sum.zyh,
                DATE(pi.AAB01)
            ON DUPLICATE KEY UPDATE
                score      = VALUES(score),
                updated_at = CURRENT_TIMESTAMP
        ";

        DB::statement($sql, [$startTime]);

        $this->info('doctor_ranking_sz_summary 统计完成，起始入院日期：' . $startTime);

        return 0;
    }
}

