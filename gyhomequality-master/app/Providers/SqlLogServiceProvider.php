<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SqlLogServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // 只在非生产环境记录，或者根据需要调整
        if (config('app.debug')) {
            DB::listen(function ($query) {
                // 创建日志记录
                Log::info('uri:'.request()->getPathInfo()."执行的sql语句：".$query->sql, $query->bindings);
            });

        }
    }
}
