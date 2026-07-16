<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAuditFieldsToCaseQualityShizhongUnlockRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('case_quality_shizhong_unlock_records')) {
            return;
        }

        Schema::table('case_quality_shizhong_unlock_records', function (Blueprint $table) {
            if (!Schema::hasColumn('case_quality_shizhong_unlock_records', 'audit_status')) {
                $table->tinyInteger('audit_status')->default(0)->comment('审核状态：0审核中，1通过，2驳回')->after('expire_time');
            }
            if (!Schema::hasColumn('case_quality_shizhong_unlock_records', 'auditor')) {
                $table->string('auditor', 100)->default('')->comment('审核人')->after('audit_status');
            }
            if (!Schema::hasColumn('case_quality_shizhong_unlock_records', 'audit_time')) {
                $table->dateTime('audit_time')->nullable()->comment('审核时间')->after('auditor');
            }
            if (!Schema::hasColumn('case_quality_shizhong_unlock_records', 'audit_reason')) {
                $table->string('audit_reason', 500)->default('')->comment('通过或驳回原因')->after('audit_time');
            }
        });

        Schema::table('case_quality_shizhong_unlock_records', function (Blueprint $table) {
            $table->index('audit_status', 'idx_audit_status');
            $table->index('audit_time', 'idx_audit_time');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasTable('case_quality_shizhong_unlock_records')) {
            return;
        }

        Schema::table('case_quality_shizhong_unlock_records', function (Blueprint $table) {
            $table->dropIndex('idx_audit_status');
            $table->dropIndex('idx_audit_time');
            $table->dropColumn(['audit_status', 'auditor', 'audit_time', 'audit_reason']);
        });
    }
}
