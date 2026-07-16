<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddApiBaseUrlToWechatWorkPushConfigs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('wechat_work_push_configs') && !Schema::hasColumn('wechat_work_push_configs', 'api_base_url')) {
            Schema::table('wechat_work_push_configs', function (Blueprint $table) {
                $table->string('api_base_url', 255)
                    ->default('https://qyapi.weixin.qq.com')
                    ->comment('企业微信API基础地址')
                    ->after('access_token');
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
        if (Schema::hasTable('wechat_work_push_configs') && Schema::hasColumn('wechat_work_push_configs', 'api_base_url')) {
            Schema::table('wechat_work_push_configs', function (Blueprint $table) {
                $table->dropColumn('api_base_url');
            });
        }
    }
}
