<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUpdatedByToRuleTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('case_rule')) {
            Schema::table('case_rule', function (Blueprint $table) {
                if (!Schema::hasColumn('case_rule', 'updated_by')) {
                    $table->string('updated_by', 255)->nullable()->comment('更新人')->after('updated_at');
                }
            });
        }

        if (Schema::hasTable('rule_setting')) {
            Schema::table('rule_setting', function (Blueprint $table) {
                if (!Schema::hasColumn('rule_setting', 'updated_by')) {
                    $table->string('updated_by', 255)->nullable()->comment('更新人')->after('updated_at');
                }
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
        if (Schema::hasTable('case_rule')) {
            Schema::table('case_rule', function (Blueprint $table) {
                if (Schema::hasColumn('case_rule', 'updated_by')) {
                    $table->dropColumn('updated_by');
                }
            });
        }

        if (Schema::hasTable('rule_setting')) {
            Schema::table('rule_setting', function (Blueprint $table) {
                if (Schema::hasColumn('rule_setting', 'updated_by')) {
                    $table->dropColumn('updated_by');
                }
            });
        }
    }
}
