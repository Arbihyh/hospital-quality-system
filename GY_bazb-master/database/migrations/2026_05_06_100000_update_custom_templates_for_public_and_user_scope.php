<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCustomTemplatesForPublicAndUserScope extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('custom_templates')) {
            Schema::table('custom_templates', function (Blueprint $table) {
                if (!Schema::hasColumn('custom_templates', 'department_id')) {
                    $table->unsignedBigInteger('department_id')->nullable()->comment('科室ID')->after('department');
                    $table->index('department_id', 'idx_department_id');
                }

                if (!Schema::hasColumn('custom_templates', 'disease_id')) {
                    $table->unsignedBigInteger('disease_id')->nullable()->comment('病种ID')->after('department_id');
                    $table->index('disease_id', 'idx_disease_id');
                }

                if (!Schema::hasColumn('custom_templates', 'document_type')) {
                    $table->string('document_type', 255)->nullable()->comment('文书类型')->after('disease_id');
                }

                if (!Schema::hasColumn('custom_templates', 'template_scope')) {
                    $table->tinyInteger('template_scope')->default(1)->comment('模板范围：1-公共模板，2-用户模板')->after('big_model_template_id');
                    $table->index('template_scope', 'idx_template_scope');
                }

                if (!Schema::hasColumn('custom_templates', 'source_template_id')) {
                    $table->unsignedBigInteger('source_template_id')->nullable()->comment('关联公共模板ID')->after('template_scope');
                    $table->index('source_template_id', 'idx_source_template_id');
                }
            });
        }

        if (!Schema::hasTable('custom_template_departments')) {
            Schema::create('custom_template_departments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 255)->comment('科室名称');
                $table->tinyInteger('status')->default(1)->comment('状态：0-禁用，1-启用');
                $table->integer('sort_num')->default(0)->comment('排序');
                $table->timestamps();

                $table->index('status');
                $table->index('sort_num');
            });
        }

        if (!Schema::hasTable('custom_template_diseases')) {
            Schema::create('custom_template_diseases', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 255)->comment('病种名称');
                $table->tinyInteger('status')->default(1)->comment('状态：0-禁用，1-启用');
                $table->integer('sort_num')->default(0)->comment('排序');
                $table->timestamps();

                $table->index('status');
                $table->index('sort_num');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('custom_template_departments')) {
            Schema::dropIfExists('custom_template_departments');
        }

        if (Schema::hasTable('custom_template_diseases')) {
            Schema::dropIfExists('custom_template_diseases');
        }

        if (Schema::hasTable('custom_templates')) {
            Schema::table('custom_templates', function (Blueprint $table) {
                if (Schema::hasColumn('custom_templates', 'source_template_id')) {
                    $table->dropIndex('idx_source_template_id');
                    $table->dropColumn('source_template_id');
                }

                if (Schema::hasColumn('custom_templates', 'template_scope')) {
                    $table->dropIndex('idx_template_scope');
                    $table->dropColumn('template_scope');
                }

                if (Schema::hasColumn('custom_templates', 'disease_id')) {
                    $table->dropIndex('idx_disease_id');
                    $table->dropColumn('disease_id');
                }

                if (Schema::hasColumn('custom_templates', 'document_type')) {
                    $table->dropColumn('document_type');
                }

                if (Schema::hasColumn('custom_templates', 'department_id')) {
                    $table->dropIndex('idx_department_id');
                    $table->dropColumn('department_id');
                }
            });
        }
    }
}
