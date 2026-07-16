<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExtendedFieldsToCaseRuleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('case_rule')) {
            return;
        }

        Schema::table('case_rule', function (Blueprint $table) {
            if (!Schema::hasColumn('case_rule', 'disease')) {
                $table->string('disease', 255)->nullable()->comment('病种')->after('department');
            }

            if (!Schema::hasColumn('case_rule', 'trigger_condition')) {
                $table->text('trigger_condition')->nullable()->comment('触发条件')->after('disease');
            }

            if (!Schema::hasColumn('case_rule', 'judgment_caliber')) {
                $table->text('judgment_caliber')->nullable()->comment('判断口径')->after('trigger_condition');
            }

            if (!Schema::hasColumn('case_rule', 'quality_basis')) {
                $table->text('quality_basis')->nullable()->comment('质控依据')->after('judgment_caliber');
            }

            if (!Schema::hasColumn('case_rule', 'basis_source')) {
                $table->text('basis_source')->nullable()->comment('依据来源')->after('quality_basis');
            }

            if (!Schema::hasColumn('case_rule', 'data_source')) {
                $table->text('data_source')->nullable()->comment('数据来源')->after('basis_source');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasTable('case_rule')) {
            return;
        }

        Schema::table('case_rule', function (Blueprint $table) {
            if (Schema::hasColumn('case_rule', 'data_source')) {
                $table->dropColumn('data_source');
            }

            if (Schema::hasColumn('case_rule', 'basis_source')) {
                $table->dropColumn('basis_source');
            }

            if (Schema::hasColumn('case_rule', 'quality_basis')) {
                $table->dropColumn('quality_basis');
            }

            if (Schema::hasColumn('case_rule', 'judgment_caliber')) {
                $table->dropColumn('judgment_caliber');
            }

            if (Schema::hasColumn('case_rule', 'trigger_condition')) {
                $table->dropColumn('trigger_condition');
            }

            if (Schema::hasColumn('case_rule', 'disease')) {
                $table->dropColumn('disease');
            }
        });
    }
}
