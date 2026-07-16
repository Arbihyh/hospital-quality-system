<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('menu')) {
            Schema::create("menu", function (Blueprint $table) {
                $table->id();
                $table->string("name")->default("")->comment("模块名");
                $table->integer("sort")->default(1)->comment("排序越小越靠前");
                $table->string("desc", 255)->default("")->comment("描述");
                $table->string("icon", 100)->default("")->comment("icon");
            });
        }
        if (!Schema::hasTable('menu_list')) {
            Schema::create("menu_list", function (Blueprint $table) {
                $table->id();
                $table->string("name", 50)->default("")->comment("名称");
                $table->string("url", 128)->unique()->default("")->comment("url地址");
                $table->integer("menu_id")->comment("模块id");
                $table->tinyInteger("menu")->comment("0不是 1是 菜单");
                $table->tinyInteger("online")->default(1)->comment("1:可用 0:不可用");
                $table->string("desc", 255)->default("")->comment("描述");
                $table->tinyInteger("is_show")->default(1)->comment("是否在菜单展示 1：是 0 否");
            });
        }
        if (!Schema::hasTable('admin_group')) {
            Schema::create("admin_group", function (Blueprint $table) {
                $table->id();
                $table->string("role")->default("[]")->comment("所有权");
                $table->string("name")->default("")->comment("角色名");
                $table->string("desc")->default("")->comment("描述");
                $table->integer("admin_id")->index()->comment("创建人id");
            });
            App\Services\AdminGroupService::add('超级管理员组','超级管理员组','all',1);
        }
        if (!Schema::hasTable('admin')) {
            Schema::create("admin", function (Blueprint $table) {
                $table->id();
                $table->string("name", 100)->index()->nullable(false)->comment("昵称");
                $table->string("account", 50)->index()->nullable(false)->comment("账号");
                $table->string("password")->nullable(false)->comment("密码");
                $table->string("salt", 50)->nullable(false)->comment("盐值");
                $table->string("group_id", 50)->nullable(false)->comment("权限组id");
                $table->string("token")->nullable()->comment("登陆token");
                $table->timestamp("login_at")->default(DB::raw("CURRENT_TIMESTAMP"))->nullable(false)->comment("登陆时间");
                $table->string("login_ip")->nullable()->comment("登陆ip");
                $table->string("desc")->nullable()->comment("描述");
            });
            App\Services\AdminService::addAdmin('超级管理员','562537','jocker',1,'初始化管理员');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
