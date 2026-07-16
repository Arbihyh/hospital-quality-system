<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSanyuanJjjctysFormatTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('bllb34_jjjctys')) {
            return;
        }

        Schema::create('bllb34_jjjctys', function (Blueprint $table) {
            $table->increments('id');
            $table->string('ZYH', 100)->nullable()->comment('住院号');
            $table->string('BLBH', 255)->nullable()->comment('病历编号');
            $table->unsignedInteger('BLLB')->nullable()->comment('病历类别');
            $table->unsignedInteger('MBLB')->nullable()->comment('模板类别');
            $table->string('BLMC', 255)->nullable()->comment('病历名称');
            $table->string('XM', 100)->nullable()->comment('姓名');
            $table->string('XB', 20)->nullable()->comment('性别');
            $table->string('NL', 50)->nullable()->comment('年龄');
            $table->string('KS', 100)->nullable()->comment('科室');
            $table->string('BAH', 100)->nullable()->comment('病案号');
            $table->text('XMMC')->nullable()->comment('拒绝检查治疗项目名称');
            $table->string('YSQM', 255)->nullable()->comment('医师签名');
            $table->string('YSQMSJ', 32)->nullable()->comment('医师签名时间');
            $table->text('HFYJ')->nullable()->comment('患方意见');
            $table->string('HZQM', 255)->nullable()->comment('患者签名');
            $table->unsignedTinyInteger('SFHZQM')->default(0)->comment('是否患者签名');
            $table->string('HZQMSJ', 32)->nullable()->comment('患者签名时间');
            $table->string('DLRQM', 255)->nullable()->comment('代理人签名');
            $table->unsignedTinyInteger('SFDLRQM')->default(0)->comment('是否代理人签名');
            $table->string('DLRGX', 100)->nullable()->comment('代理人与患者关系');
            $table->string('DLRQMSJ', 32)->nullable()->comment('代理人签名时间');
            $table->dateTime('CJSJ')->nullable()->comment('创建时间');
            $table->dateTime('ZXSJ')->nullable()->comment('记录时间');
            $table->dateTime('WCSJ')->nullable()->comment('完成时间');
            $table->string('SXYS', 50)->nullable()->comment('书写医生');
            $table->string('BRKS', 100)->nullable()->comment('病人科室');
            $table->string('CJKS', 100)->nullable()->comment('创建科室');
            $table->string('BLZT', 20)->nullable()->comment('病历状态');
            $table->text('HJNR')->nullable()->comment('痕迹内容');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent();

            $table->unique('BLBH', 'uk_blbh');
            $table->index('ZYH', 'idx_zyh');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bllb34_jjjctys');
    }
}
