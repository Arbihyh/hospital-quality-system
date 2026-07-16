<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateBigModelMedicalRecordTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('big_model_medical_record')) {
            Schema::create('big_model_medical_record', function (Blueprint $table) {
                $table->integer('id', true, true);
                $table->string('zyh', 50)->default('')->index('idx_zyh')->comment('住院号');
                $table->string('title', 255)->default('')->comment('标题');
                $table->longText('content')->comment('内容');
                $table->string('code', 100)->nullable()->comment('编码');
                $table->string('created_at', 100)->nullable()->comment('创建时间');
            });

            DB::statement("ALTER TABLE `big_model_medical_record` COMMENT = '大模型病历保存表'");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('big_model_medical_record');
    }
}
