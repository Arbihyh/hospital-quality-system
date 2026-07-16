<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateWechatWorkNotificationTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('wechat_work_push_configs')) {
            Schema::create('wechat_work_push_configs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 100)->default('质控预警默认策略')->comment('策略名称');
                $table->tinyInteger('enabled')->default(1)->comment('是否启用企业微信推送:1是0否');
                $table->string('corp_id', 100)->default('')->comment('企业微信企业ID');
                $table->string('secret', 255)->default('')->comment('企业微信应用密钥');
                $table->string('agent_id', 64)->default('')->comment('企业微信应用AgentId');
                $table->string('access_token', 1024)->default('')->comment('手工维护access_token，可为空');
                $table->string('api_base_url', 255)->default('https://qyapi.weixin.qq.com')->comment('企业微信API基础地址');
                $table->string('detail_url_template', 1024)->default('')->comment('消息详情链接模板');
                $table->integer('timeout')->default(5)->comment('企业微信接口请求超时时间秒');
                $table->string('message_type', 20)->default('taskcard')->comment('应用消息类型:text/taskcard');
                $table->string('callback_url', 1024)->default('')->comment('企业微信回调URL');
                $table->string('callback_token', 255)->default('')->comment('企业微信回调Token');
                $table->string('callback_aes_key', 255)->default('')->comment('企业微信回调EncodingAESKey');
                $table->tinyInteger('repeat_enabled')->default(0)->comment('是否开启重复提醒:1是0否');
                $table->string('repeat_type', 20)->default('fixed_times')->comment('重复提醒类型:fixed_times/interval');
                $table->integer('repeat_interval_minutes')->default(0)->comment('固定间隔重复提醒分钟数');
                $table->string('repeat_times', 255)->default('08:00,14:00,16:00,18:00')->comment('定时重复推送时间');
                $table->text('default_userids')->nullable()->comment('默认接收人企业微信UserID,逗号分隔');
                $table->string('default_group_ids', 255)->nullable()->comment('默认接收群配置ID,逗号分隔');
                $table->tinyInteger('unchanged_skip_enabled')->default(1)->comment('未开启重复提醒时同一问题未变化是否跳过');
                $table->tinyInteger('remind_unread_only')->default(0)->comment('重复提醒是否仅提醒未读消息');
                $table->text('remark')->nullable()->comment('备注');
                $table->integer('created_by')->default(0)->comment('创建人ID');
                $table->integer('updated_by')->default(0)->comment('更新人ID');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新时间');

                $table->unique('name');
                $table->index('enabled');
            });

            DB::table('wechat_work_push_configs')->insert([
                'name' => '质控预警默认策略',
                'enabled' => 1,
                'corp_id' => '',
                'secret' => '',
                'agent_id' => '',
                'access_token' => '',
                'api_base_url' => 'https://qyapi.weixin.qq.com',
                'detail_url_template' => '',
                'timeout' => 5,
                'message_type' => 'taskcard',
                'callback_url' => '',
                'callback_token' => '',
                'callback_aes_key' => '',
                'repeat_enabled' => 0,
                'repeat_type' => 'fixed_times',
                'repeat_interval_minutes' => 0,
                'repeat_times' => '08:00,14:00,16:00,18:00',
                'default_userids' => '',
                'default_group_ids' => '',
                'unchanged_skip_enabled' => 1,
                'remind_unread_only' => 0,
                'remark' => '',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if (!Schema::hasTable('wechat_work_groups')) {
            Schema::create('wechat_work_groups', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 100)->comment('企业微信群名称');
                $table->string('send_type', 20)->default('appchat')->comment('发送方式:appchat/webhook');
                $table->string('chat_id', 255)->nullable()->comment('企业微信应用群聊chatid');
                $table->string('agent_id', 64)->nullable()->comment('群聊所属应用AgentId');
                $table->string('webhook_key', 255)->nullable()->comment('群机器人webhook key');
                $table->string('webhook_url', 1024)->nullable()->comment('群机器人完整webhook地址');
                $table->tinyInteger('enabled')->default(1)->comment('是否启用:1是0否');
                $table->text('remark')->nullable()->comment('备注');
                $table->integer('created_by')->default(0)->comment('创建人ID');
                $table->integer('updated_by')->default(0)->comment('更新人ID');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新时间');

                $table->index('enabled');
            });
        }

        if (!Schema::hasTable('wechat_work_push_logs')) {
            Schema::create('wechat_work_push_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('source_log_id')->default(0)->comment('原质控提醒日志ID');
                $table->string('source_type', 50)->default('quality_warning')->comment('来源类型');
                $table->string('warning_key', 64)->default('')->comment('预警去重标识');
                $table->string('patient_zyh', 50)->default('')->comment('住院号');
                $table->string('patient_no', 50)->default('')->comment('病案号');
                $table->integer('rule_id')->default(0)->comment('质控规则ID');
                $table->integer('case_quality_id')->default(0)->comment('质控问题ID');
                $table->string('data_id', 100)->default('')->comment('业务数据ID');
                $table->tinyInteger('quality_type')->default(0)->comment('质控类型');
                $table->string('title', 100)->default('')->comment('消息标题');
                $table->text('content')->nullable()->comment('消息正文');
                $table->text('quality_content')->nullable()->comment('质控问题内容');
                $table->string('message_type', 20)->default('text')->comment('企业微信消息类型:text/taskcard');
                $table->string('task_id', 128)->default('')->comment('企业微信任务卡片task_id');
                $table->string('event_key', 64)->default('')->comment('任务卡片点击按钮key');
                $table->string('receiver_type', 20)->default('user')->comment('接收类型:user/group');
                $table->string('receiver_id', 255)->default('')->comment('接收人UserID或群ID');
                $table->string('receiver_name', 255)->default('')->comment('接收人或群名称');
                $table->tinyInteger('send_status')->default(0)->comment('发送状态:0待发送1成功2失败3跳过');
                $table->timestamp('send_time')->nullable()->comment('发送时间');
                $table->text('fail_reason')->nullable()->comment('失败原因');
                $table->text('request_body')->nullable()->comment('请求内容');
                $table->text('response_body')->nullable()->comment('响应内容');
                $table->tinyInteger('read_status')->default(0)->comment('已读状态:0未查看1已查看');
                $table->timestamp('read_at')->nullable()->comment('查看时间');
                $table->integer('read_user_id')->default(0)->comment('查看用户ID');
                $table->string('repeat_batch', 64)->default('')->comment('重复提醒批次');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新时间');

                $table->index('source_log_id');
                $table->index('warning_key');
                $table->index(['patient_zyh', 'rule_id']);
                $table->index(['send_status', 'send_time']);
                $table->index('read_status');
                $table->index('task_id');
            });
        }

        if (!Schema::hasTable('wechat_work_warning_reads')) {
            Schema::create('wechat_work_warning_reads', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('warning_key', 64)->default('')->comment('预警去重标识');
                $table->bigInteger('source_log_id')->default(0)->comment('原质控提醒日志ID');
                $table->integer('user_id')->default(0)->comment('系统用户ID');
                $table->string('wechat_userid', 100)->default('')->comment('企业微信UserID');
                $table->timestamp('read_at')->nullable()->comment('查看时间');
                $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'))->comment('创建时间');
                $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'))->comment('更新时间');

                $table->index('warning_key');
                $table->index('source_log_id');
                $table->index('user_id');
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
        Schema::dropIfExists('wechat_work_warning_reads');
        Schema::dropIfExists('wechat_work_push_logs');
        Schema::dropIfExists('wechat_work_groups');
        Schema::dropIfExists('wechat_work_push_configs');
    }
}
