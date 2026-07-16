<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHomePageFieldsToPatientInfoTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('patient_info_operation_v2', function (Blueprint $table) {
            if (!Schema::hasColumn('patient_info_operation_v2', 'SFFJHZRY')) {
                $table->string('SFFJHZRY', 10)->nullable()->comment('是否为日间手术');
            }

            if (!Schema::hasColumn('patient_info_operation_v2', 'SFWRJBF')) {
                $table->string('SFWRJBF', 10)->nullable()->comment('是否为日间操作');
            }

            if (!Schema::hasColumn('patient_info_operation_v2', 'SFFJHZSS')) {
                $table->string('SFFJHZSS', 10)->nullable()->comment('是否非计划再手术');
            }
        });

        Schema::table('patient_info_v2', function (Blueprint $table) {
            if (!Schema::hasColumn('patient_info_v2', 'SSRJSS')) {
                $table->string('SSRJSS', 255)->nullable()->comment('是否为日间病房');
            }

            if (!Schema::hasColumn('patient_info_v2', 'SFFJHZRY')) {
                $table->string('SFFJHZRY', 255)->nullable()->comment('是否非计划再入院');
            }

            if (!Schema::hasColumn('patient_info_v2', 'TNM')) {
                $table->string('TNM', 255)->nullable()->comment('TNM/肿瘤分期');
            }

            if (!Schema::hasColumn('patient_info_v2', 'FYLX')) {
                $table->string('FYLX', 255)->nullable()->comment('反应类型');
            }

            if (!Schema::hasColumn('patient_info_v2', 'HXB')) {
                $table->string('HXB', 30)->nullable()->comment('红细胞（u）');
            }

            if (!Schema::hasColumn('patient_info_v2', 'XXB')) {
                $table->string('XXB', 30)->nullable()->comment('血小板（u）');
            }

            if (!Schema::hasColumn('patient_info_v2', 'LCD')) {
                $table->string('LCD', 30)->nullable()->comment('冷沉淀（u）');
            }

            if (!Schema::hasColumn('patient_info_v2', 'XJ')) {
                $table->string('XJ', 30)->nullable()->comment('血浆（ml）');
            }

            if (!Schema::hasColumn('patient_info_v2', 'QX')) {
                $table->string('QX', 30)->nullable()->comment('全血（u）');
            }

            if (!Schema::hasColumn('patient_info_v2', 'ZTX_HXB')) {
                $table->string('ZTX_HXB', 30)->nullable()->comment('自体血（红细胞）（ml）');
            }

            if (!Schema::hasColumn('patient_info_v2', 'CCS')) {
                $table->string('CCS', 30)->nullable()->comment('储存式（ml）');
            }

            if (!Schema::hasColumn('patient_info_v2', 'SSS')) {
                $table->string('SSS', 30)->nullable()->comment('稀释式（ml）');
            }

            if (!Schema::hasColumn('patient_info_v2', 'HSS')) {
                $table->string('HSS', 30)->nullable()->comment('回收式（ml）');
            }

            if (!Schema::hasColumn('patient_info_v2', 'HBS')) {
                $table->string('HBS', 50)->nullable()->comment('HBsAg');
            }

            if (!Schema::hasColumn('patient_info_v2', 'HCV_AB')) {
                $table->string('HCV_AB', 50)->nullable()->comment('HCV-Ab');
            }

            if (!Schema::hasColumn('patient_info_v2', 'HIV_AB')) {
                $table->string('HIV_AB', 50)->nullable()->comment('HIV-Ab');
            }

            if (!Schema::hasColumn('patient_info_v2', 'TP_AB')) {
                $table->string('TP_AB', 50)->nullable()->comment('TP-Ab');
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
        Schema::table('patient_info_operation_v2', function (Blueprint $table) {
            $columns = ['SFFJHZRY', 'SFWRJBF', 'SFFJHZSS'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('patient_info_operation_v2', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('patient_info_v2', function (Blueprint $table) {
            $columns = [
                'SSRJSS',
                'SFFJHZRY',
                'TNM',
                'FYLX',
                'HXB',
                'XXB',
                'LCD',
                'XJ',
                'QX',
                'ZTX_HXB',
                'CCS',
                'SSS',
                'HSS',
                'HBS',
                'HCV_AB',
                'HIV_AB',
                'TP_AB',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('patient_info_v2', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
