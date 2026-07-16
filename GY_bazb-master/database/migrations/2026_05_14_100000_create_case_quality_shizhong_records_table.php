<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCaseQualityShizhongRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('case_quality_shizhong_records')) {
            return;
        }

        Schema::create('case_quality_shizhong_records', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('列表序号');
            $table->string('jzhm', 255)->default('')->comment('住院号，用于关联患者本次住院记录');
            $table->integer('rule_id')->default(0)->comment('触发的质控规则ID');
            $table->string('department', 255)->default('')->comment('所属科室：患者当前所属科室，取brry.brks');
            $table->integer('lock_count')->default(0)->comment('当前规则触发强控并锁定的次数');
            $table->string('resident_doctor', 255)->default('')->comment('住院医师：患者管床医师，格式为汉字（工号），取brry.GCYSMC(GCYSDM)');
            $table->string('medical_record_no', 255)->default('')->comment('病案号：首次质控取brry.aaa28');
            $table->string('patient_name', 255)->default('')->comment('患者姓名：首次质控取brry.xm');
            $table->string('bed_no', 100)->default('')->comment('床号：首次质控取brry.ch');
            $table->dateTime('last_quality_time')->nullable()->comment('末次质控时间：该问题最近一次质控时间');
            $table->timestamps();

            $table->index('jzhm');
            $table->index('rule_id');
            $table->index('medical_record_no');
            $table->index('last_quality_time');
            $table->unique(['jzhm', 'rule_id'], 'uk_jzhm_rule_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('case_quality_shizhong_records');
    }
}
