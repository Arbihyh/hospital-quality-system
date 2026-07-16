<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddIndicatorIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 为 indicator 表的 AAC01_YEAR 和 AAC01_MONTH 字段添加索引（用于时间范围查询，优先使用）
        try {
            if (Schema::hasTable('indicator')) {
                // 检查年份索引是否已存在
                $indexes = DB::select("SHOW INDEXES FROM indicator WHERE Key_name = 'idx_indicator_aac01_year'");
                if (empty($indexes)) {
                    DB::statement('ALTER TABLE indicator ADD INDEX idx_indicator_aac01_year (AAC01_YEAR)');
                }
                
                // 检查月份索引是否已存在
                $indexes = DB::select("SHOW INDEXES FROM indicator WHERE Key_name = 'idx_indicator_aac01_month'");
                if (empty($indexes)) {
                    DB::statement('ALTER TABLE indicator ADD INDEX idx_indicator_aac01_month (AAC01_MONTH)');
                }
                
                // 检查联合索引是否已存在（用于年份+月份组合查询）
                $indexes = DB::select("SHOW INDEXES FROM indicator WHERE Key_name = 'idx_indicator_aac01_year_month'");
                if (empty($indexes)) {
                    DB::statement('ALTER TABLE indicator ADD INDEX idx_indicator_aac01_year_month (AAC01_YEAR, AAC01_MONTH)');
                }
                
                // 为 AAC01 字段添加索引（作为后备方案，用于跨年查询）
                $indexes = DB::select("SHOW INDEXES FROM indicator WHERE Key_name = 'idx_indicator_aac01'");
                if (empty($indexes)) {
                    DB::statement('ALTER TABLE indicator ADD INDEX idx_indicator_aac01 (AAC01)');
                }
            }
        } catch (\Exception $e) {
            // 如果添加索引失败，记录日志但不中断
            \Log::warning('添加 indicator 时间字段索引失败', ['error' => $e->getMessage()]);
        }

        // 为 patient_info 表的 AAC01 字段添加索引（如果不存在）
        try {
            if (Schema::hasTable('patient_info')) {
                $indexes = DB::select("SHOW INDEXES FROM patient_info WHERE Key_name = 'idx_patient_info_aac01'");
                if (empty($indexes)) {
                    DB::statement('ALTER TABLE patient_info ADD INDEX idx_patient_info_aac01 (AAC01)');
                }
            }
        } catch (\Exception $e) {
            \Log::warning('添加 patient_info.AAC01 索引失败', ['error' => $e->getMessage()]);
        }

        // 为 case_quality 表的 JZHM 字段添加索引（如果不存在）
        try {
            if (Schema::hasTable('case_quality')) {
                $indexes = DB::select("SHOW INDEXES FROM case_quality WHERE Key_name = 'idx_case_quality_jzhm'");
                if (empty($indexes)) {
                    DB::statement('ALTER TABLE case_quality ADD INDEX idx_case_quality_jzhm (JZHM)');
                }
            }
        } catch (\Exception $e) {
            \Log::warning('添加 case_quality.JZHM 索引失败', ['error' => $e->getMessage()]);
        }

        // 为 home_quality 表的 ZYH 字段添加索引（如果不存在）
        try {
            if (Schema::hasTable('home_quality')) {
                $indexes = DB::select("SHOW INDEXES FROM home_quality WHERE Key_name = 'idx_home_quality_zyh'");
                if (empty($indexes)) {
                    DB::statement('ALTER TABLE home_quality ADD INDEX idx_home_quality_zyh (ZYH)');
                }
            }
        } catch (\Exception $e) {
            \Log::warning('添加 home_quality.ZYH 索引失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // 删除索引
        try {
            if (Schema::hasTable('indicator')) {
                DB::statement('ALTER TABLE indicator DROP INDEX idx_indicator_aac01_year_month');
                DB::statement('ALTER TABLE indicator DROP INDEX idx_indicator_aac01_year');
                DB::statement('ALTER TABLE indicator DROP INDEX idx_indicator_aac01_month');
                DB::statement('ALTER TABLE indicator DROP INDEX idx_indicator_aac01');
            }
        } catch (\Exception $e) {
            // 忽略错误
        }

        try {
            if (Schema::hasTable('patient_info')) {
                DB::statement('ALTER TABLE patient_info DROP INDEX idx_patient_info_aac01');
            }
        } catch (\Exception $e) {
            // 忽略错误
        }

        try {
            if (Schema::hasTable('case_quality')) {
                DB::statement('ALTER TABLE case_quality DROP INDEX idx_case_quality_jzhm');
            }
        } catch (\Exception $e) {
            // 忽略错误
        }

        try {
            if (Schema::hasTable('home_quality')) {
                DB::statement('ALTER TABLE home_quality DROP INDEX idx_home_quality_zyh');
            }
        } catch (\Exception $e) {
            // 忽略错误
        }
    }
}

