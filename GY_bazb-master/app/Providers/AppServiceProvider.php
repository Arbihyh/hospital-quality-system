<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Elasticsearch\ClientBuilder;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use League\CommonMark\Environment;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
        $this->app->singleton('es', function () {
            $client = ClientBuilder::create()->setHosts(config('database.elasticsearch.hosts'));

            // 不向 Elasticsearch 客户端注入 Laravel Logger，避免输出完整请求体和响应内容。
            return $client->build();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // 预热数据库查询，让首次请求也很快
        // 使用快速预热，只预热最关键的查询，不阻塞应用启动
        $this->warmupDatabase();
    }

    /**
     * 预热数据库查询
     * 在应用启动时预热关键查询，将数据加载到 Buffer Pool
     * 
     * @return void
     */
    private function warmupDatabase()
    {
        // 只在生产环境或非本地环境执行预热
        if (app()->environment() === 'local') {
            return;
        }

        // 快速预热：只执行最关键的查询，使用 limit 1 快速完成
        // 这样不会阻塞应用启动，但能预热 Buffer Pool
        try {
            // 预热 IndexCatalog（getIndex 接口必需）
            DB::table('index_catalog')->limit(1)->get();
            
            // 预热 Indicator（当前月份）
            $year = date('Y');
            $month = date('m');
            DB::table('indicator')
                ->where('AAC01_YEAR', $year)
                ->where('AAC01_MONTH', $month)
                ->limit(1)
                ->get();
        } catch (\Exception $e) {
            // 预热失败不影响应用运行
            Log::debug('数据库预热执行', ['message' => $e->getMessage()]);
        }
    }

}
