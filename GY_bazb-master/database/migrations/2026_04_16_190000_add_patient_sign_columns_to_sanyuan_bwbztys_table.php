<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPatientSignColumnsToSanyuanBwbztysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('bllb329_bwbztys')) {
            return;
        }

        Schema::table('bllb329_bwbztys', function (Blueprint $table) {
            if (!Schema::hasColumn('bllb329_bwbztys', 'HZQM')) {
                $table->string('HZQM', 255)->nullable()->comment('患者签名')->after('HFYJ');
            }

            if (!Schema::hasColumn('bllb329_bwbztys', 'SFHZQM')) {
                $table->unsignedTinyInteger('SFHZQM')->default(0)->comment('是否患者签名')->after('HZQM');
            }

            if (!Schema::hasColumn('bllb329_bwbztys', 'HZQMSJ')) {
                $table->string('HZQMSJ', 32)->nullable()->comment('患者签名时间')->after('SFHZQM');
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
        if (!Schema::hasTable('bllb329_bwbztys')) {
            return;
        }

        Schema::table('bllb329_bwbztys', function (Blueprint $table) {
            $dropColumns = [];

            foreach (['HZQM', 'SFHZQM', 'HZQMSJ'] as $column) {
                if (Schema::hasColumn('bllb329_bwbztys', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
}
