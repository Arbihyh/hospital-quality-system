<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomTemplatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('custom_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255)->comment('模版名称');
            $table->string('department', 255)->nullable()->comment('科室');
            $table->string('document_type', 255)->nullable()->comment('文书类型');
            $table->text('content')->nullable()->comment('模版内容');
            $table->unsignedBigInteger('big_model_template_id')->nullable()->comment('关联的大模型模版ID');
            $table->string('staff_code', 56)->nullable()->comment('创建人工号');
            $table->string('staff_name', 255)->nullable()->comment('创建人姓名');
            $table->tinyInteger('status')->default(1)->comment('状态：0-禁用，1-启用');
            $table->timestamps();

            $table->index('big_model_template_id');
            $table->index('staff_code');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('custom_templates');
    }
}
