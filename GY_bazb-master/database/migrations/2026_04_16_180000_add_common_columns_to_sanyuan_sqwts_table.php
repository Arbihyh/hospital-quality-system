<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCommonColumnsToSanyuanSqwtsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('bllb329_sqwts')) {
            return;
        }

        Schema::table('bllb329_sqwts', function (Blueprint $table) {
            if (!Schema::hasColumn('bllb329_sqwts', 'YSQM')) {
                $table->string('YSQM', 255)->nullable()->comment('医师签名')->after('BAH');
            }

            if (!Schema::hasColumn('bllb329_sqwts', 'YSQMSJ')) {
                $table->string('YSQMSJ', 32)->nullable()->comment('医师签名时间')->after('YSQM');
            }

            if (!Schema::hasColumn('bllb329_sqwts', 'HFYJ')) {
                $table->text('HFYJ')->nullable()->comment('患方意见')->after('YSQMSJ');
            }

            if (!Schema::hasColumn('bllb329_sqwts', 'DLRGX')) {
                $table->string('DLRGX', 100)->nullable()->comment('代理人与患者关系')->after('SFDLRQM');
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
        if (!Schema::hasTable('bllb329_sqwts')) {
            return;
        }

        Schema::table('bllb329_sqwts', function (Blueprint $table) {
            $dropColumns = [];

            foreach (['YSQM', 'YSQMSJ', 'HFYJ', 'DLRGX'] as $column) {
                if (Schema::hasColumn('bllb329_sqwts', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
}
