<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCaseQualityShizhongUnlockRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('case_quality_shizhong_unlock_records')) {
            return;
        }

        Schema::create('case_quality_shizhong_unlock_records', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->string('zyh', 64)->comment('住院号');
            $table->unsignedBigInteger('rule_id')->comment('规则ID');
            $table->string('unlock_applicant', 100)->default('')->comment('解锁申请人');
            $table->string('unlock_reason', 500)->default('')->comment('解锁原因');
            $table->dateTime('apply_time')->comment('申请时间');
            $table->dateTime('expire_time')->comment('失效时间');

            $table->index(['zyh', 'rule_id'], 'idx_zyh_rule_id');
            $table->index('apply_time', 'idx_apply_time');
            $table->index('expire_time', 'idx_expire_time');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('case_quality_shizhong_unlock_records');
    }
}
