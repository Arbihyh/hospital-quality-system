<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateShizhongWarningConfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('shizhong_warning_configs')) {
            Schema::create('shizhong_warning_configs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('config_key', 100)->unique()->comment('配置键');
                $table->longText('config_value')->nullable()->comment('配置值');
                $table->string('name', 255)->default('')->comment('配置名称');
                $table->string('remark', 1000)->default('')->comment('备注');
                $table->timestamps();
            });
        }

        $now = date('Y-m-d H:i:s');
        $ruleValue = function ($id, $default) {
            if (!Schema::hasTable('rule_word_map')) {
                return $default;
            }

            $value = DB::table('rule_word_map')->where('id', '=', $id)->value('keyword');

            return $value === null || $value === '' ? $default : $value;
        };

        $configs = [
            'brry_sql' => [
                'name' => 'HIS BRRY基础SQL',
                'value' => $ruleValue(9007, "SELECT CYPB,ZYH,ZYHM,BRKS,BRXM,ZZYS,ZSYS,to_char(RYRQ,'yyyy-mm-dd HH24:mi:ss') as RYRQ,to_char(CYRQ,'yyyy-mm-dd HH24:mi:ss') as CYRQ,BRKS,BRBQ,BRCH,ZYYS,ZLXZ,ZZYS,MZYS,ZSYS,CYPB,CYFS,ZYHM,RYCS FROM PORTAL_HIS.ZY_BRRY"),
                'remark' => '预警同步查询HIS住院病人数据的基础SQL',
            ],
            'brry_zyh_field' => [
                'name' => 'HIS BRRY住院号字段',
                'value' => $ruleValue(9012, 'ZYH'),
                'remark' => '预留配置，表示HIS BRRY视图中的住院号字段',
            ],
            'admission_time_field' => [
                'name' => 'HIS BRRY入院时间字段',
                'value' => $ruleValue(9041, 'RYRQ'),
                'remark' => '入院相关预警使用的HIS时间字段',
            ],
            'discharge_time_field' => [
                'name' => 'HIS BRRY出院时间字段',
                'value' => $ruleValue(9013, 'CYRQ'),
                'remark' => '出院相关预警使用的HIS时间字段',
            ],
            'first_course_finish_time_field' => [
                'name' => '首次病程完成时间字段',
                'value' => $ruleValue(22, 'CJSJ'),
                'remark' => 'EMR_BL_BL01中首次病程记录用于判断完成时间的字段',
            ],
            'admission_record_sign_time_field' => [
                'name' => '入院记录首次签名时间字段',
                'value' => $ruleValue(8002, 'first_blsy_time'),
                'remark' => 'EMR_BL_BL01中入院记录或24小时记录用于判断完成时间的字段',
            ],
            'admission_record_bllb' => [
                'name' => '入院记录BLLB',
                'value' => $ruleValue(8006, '292'),
                'remark' => '可配置多个，逗号分隔',
            ],
            'admission_24h_record_bllb' => [
                'name' => '24小时记录BLLB',
                'value' => $ruleValue(8007, '18'),
                'remark' => '可配置多个，逗号分隔',
            ],
            'auto_review_enabled' => [
                'name' => '自动审核开关',
                'value' => $ruleValue(4003, '0'),
                'remark' => '1表示开启，其他值表示关闭',
            ],
            'auto_review_score_threshold' => [
                'name' => '自动审核分数阈值',
                'value' => $ruleValue(4000, '0'),
                'remark' => '同步BRRY时沿用的自动审核分数阈值',
            ],
            'force_level_check' => [
                'name' => '自动审核强制缺陷检查',
                'value' => $ruleValue(4002, '2'),
                'remark' => '1表示检查强制缺陷，其他值表示不检查',
            ],
        ];

        foreach ($configs as $key => $config) {
            DB::table('shizhong_warning_configs')->updateOrInsert(
                ['config_key' => $key],
                [
                    'config_value' => $config['value'],
                    'name' => $config['name'],
                    'remark' => $config['remark'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shizhong_warning_configs');
    }
}
