<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddFjhzcsshzynblwzlFieldsToIndicator extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('indicator')) {
            return;
        }

        if (!Schema::hasColumn('indicator', 'fjhzcsshzynblwzl_fz')) {
            DB::statement('ALTER TABLE indicator ADD COLUMN fjhzcsshzynblwzl_fz int(11) NULL DEFAULT NULL AFTER shfjhzssbltlsh_error');
        }

        if (!Schema::hasColumn('indicator', 'fjhzcsshzynblwzl_fm')) {
            DB::statement('ALTER TABLE indicator ADD COLUMN fjhzcsshzynblwzl_fm int(5) NULL DEFAULT NULL AFTER fjhzcsshzynblwzl_fz');
        }

        if (!Schema::hasColumn('indicator', 'fjhzcsshzynblwzl_error')) {
            DB::statement('ALTER TABLE indicator ADD COLUMN fjhzcsshzynblwzl_error text NULL AFTER fjhzcsshzynblwzl_fm');
        }

        if (Schema::hasTable('index_catalog')) {
            DB::statement("INSERT INTO index_catalog (pid, level, sort_num, name, fenzi, fenmu, url, fenmu_name, fenzi_name, index_name, status, quality_time, is_rg, created_at, updated_at) SELECT 1, 0, 104, '非计划再次手术患者疑难病例完整率', '[{\"name\":\"1、\",\"info\":\"满足分母条件\"},{\"name\":\"2、\",\"info\":\"术前48小时内存在疑难病例讨论记录\"},{\"name\":\"3、\",\"info\":\"疑难病例讨论记录包含讨论时间、讨论地点、主持人、参加讨论人员、具体讨论意见、讨论结论且主持人已签字\"}]', '[{\"name\":\"1、\",\"info\":\"手术申请是“非计划再手术”\"}]', '19', '非计划再次手术总次数', '非计划再次手术患者中疑难病例讨论记录完整的数量', 'fjhzcsshzynblwzl', 2, UNIX_TIMESTAMP(), '0', NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM index_catalog WHERE index_name = 'fjhzcsshzynblwzl')");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasTable('indicator')) {
            return;
        }

        if (Schema::hasTable('index_catalog')) {
            DB::statement("DELETE FROM index_catalog WHERE index_name = 'fjhzcsshzynblwzl'");
        }

        if (Schema::hasColumn('indicator', 'fjhzcsshzynblwzl_error')) {
            DB::statement('ALTER TABLE indicator DROP COLUMN fjhzcsshzynblwzl_error');
        }

        if (Schema::hasColumn('indicator', 'fjhzcsshzynblwzl_fm')) {
            DB::statement('ALTER TABLE indicator DROP COLUMN fjhzcsshzynblwzl_fm');
        }

        if (Schema::hasColumn('indicator', 'fjhzcsshzynblwzl_fz')) {
            DB::statement('ALTER TABLE indicator DROP COLUMN fjhzcsshzynblwzl_fz');
        }
    }
}
