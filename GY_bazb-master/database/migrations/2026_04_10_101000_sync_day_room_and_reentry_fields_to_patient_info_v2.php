<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncDayRoomAndReentryFieldsToPatientInfoV2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('patient_info_v2', 'SSRJSS')) {
            DB::statement("ALTER TABLE `patient_info_v2` ADD COLUMN `SSRJSS` varchar(255) NULL COMMENT '是否为日间病房'");
        } else {
            DB::statement("ALTER TABLE `patient_info_v2` MODIFY COLUMN `SSRJSS` varchar(255) NULL COMMENT '是否为日间病房'");
        }

        if (!Schema::hasColumn('patient_info_v2', 'SFFJHZRY')) {
            DB::statement("ALTER TABLE `patient_info_v2` ADD COLUMN `SFFJHZRY` varchar(255) NULL COMMENT '是否非计划再入院'");
        } else {
            DB::statement("ALTER TABLE `patient_info_v2` MODIFY COLUMN `SFFJHZRY` varchar(255) NULL COMMENT '是否非计划再入院'");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('patient_info_v2', 'SFFJHZRY')) {
            DB::statement("ALTER TABLE `patient_info_v2` DROP COLUMN `SFFJHZRY`");
        }

        if (Schema::hasColumn('patient_info_v2', 'SSRJSS')) {
            DB::statement("ALTER TABLE `patient_info_v2` MODIFY COLUMN `SSRJSS` varchar(255) NULL COMMENT ''");
        }
    }
}
