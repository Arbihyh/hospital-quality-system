<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddShizhongQualityRecordIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('case_quality') && !$this->indexExists('case_quality', 'idx_sz_quality_status')) {
            DB::statement('ALTER TABLE case_quality ADD INDEX idx_sz_quality_status (rule_id, JZHM, is_correction, is_appeal, is_ignore)');
        }

        if (Schema::hasTable('appeal') && !$this->indexExists('appeal', 'idx_sz_appeal_latest')) {
            DB::statement('ALTER TABLE appeal ADD INDEX idx_sz_appeal_latest (quality_type, type, ZYH, error_id, id)');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('appeal') && $this->indexExists('appeal', 'idx_sz_appeal_latest')) {
            DB::statement('ALTER TABLE appeal DROP INDEX idx_sz_appeal_latest');
        }

        if (Schema::hasTable('case_quality') && $this->indexExists('case_quality', 'idx_sz_quality_status')) {
            DB::statement('ALTER TABLE case_quality DROP INDEX idx_sz_quality_status');
        }
    }

    /**
     * 判断索引是否存在。
     *
     * @param string $table
     * @param string $index
     * @return bool
     */
    private function indexExists($table, $index)
    {
        $result = DB::selectOne(
            'SELECT COUNT(*) AS count_num FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $index]
        );

        return !empty($result) && (int)$result->count_num > 0;
    }
}
