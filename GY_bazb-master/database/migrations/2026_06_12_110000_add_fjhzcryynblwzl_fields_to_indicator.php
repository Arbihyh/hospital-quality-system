<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddFjhzcryynblwzlFieldsToIndicator extends Migration
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

        if (!Schema::hasColumn('indicator', 'fjhzcryynblwzl_fz')) {
            DB::statement('ALTER TABLE indicator ADD COLUMN fjhzcryynblwzl_fz int(11) NULL DEFAULT NULL AFTER fjhzcsshzynblwzl_error');
        }

        if (!Schema::hasColumn('indicator', 'fjhzcryynblwzl_fm')) {
            DB::statement('ALTER TABLE indicator ADD COLUMN fjhzcryynblwzl_fm int(5) NULL DEFAULT NULL AFTER fjhzcryynblwzl_fz');
        }

        if (!Schema::hasColumn('indicator', 'fjhzcryynblwzl_error')) {
            DB::statement('ALTER TABLE indicator ADD COLUMN fjhzcryynblwzl_error text NULL AFTER fjhzcryynblwzl_fm');
        }

        if (Schema::hasTable('index_catalog')) {
            DB::statement("INSERT INTO index_catalog (pid, level, sort_num, name, fenzi, fenmu, url, fenmu_name, fenzi_name, index_name, status, quality_time, is_rg, created_at, updated_at) SELECT 1, 0, 105, '非计划再次入院疑难病例完整率', '[{\"name\":\"1、\",\"info\":\"满足分母条件\"},{\"name\":\"2、\",\"info\":\"存在疑难病例讨论记录\"},{\"name\":\"3、\",\"info\":\"疑难病例讨论记录包含讨论时间、讨论地点、主持人、参加讨论人员、具体讨论意见、讨论结论且主持人已签字\"}]', '[{\"name\":\"1、\",\"info\":\"非计划再次入院患者\"},{\"name\":\"2、\",\"info\":\"当前住院次数大于1\"},{\"name\":\"3、\",\"info\":\"前一次住院31天再住院计划不是有\"},{\"name\":\"4、\",\"info\":\"本次与前一次主要诊断编码前5位一致\"}]', '20', '非计划再次入院患者数', '非计划再次入院患者中疑难病例讨论记录完整的数量', 'fjhzcryynblwzl', 2, UNIX_TIMESTAMP(), '0', NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM index_catalog WHERE index_name = 'fjhzcryynblwzl')");
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
            DB::statement("DELETE FROM index_catalog WHERE index_name = 'fjhzcryynblwzl'");
        }

        if (Schema::hasColumn('indicator', 'fjhzcryynblwzl_error')) {
            DB::statement('ALTER TABLE indicator DROP COLUMN fjhzcryynblwzl_error');
        }

        if (Schema::hasColumn('indicator', 'fjhzcryynblwzl_fm')) {
            DB::statement('ALTER TABLE indicator DROP COLUMN fjhzcryynblwzl_fm');
        }

        if (Schema::hasColumn('indicator', 'fjhzcryynblwzl_fz')) {
            DB::statement('ALTER TABLE indicator DROP COLUMN fjhzcryynblwzl_fz');
        }
    }
}
