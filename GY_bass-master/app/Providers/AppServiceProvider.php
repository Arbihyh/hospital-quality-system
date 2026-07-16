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
            if ( app()->environment() === "local" ){
                $client->setLogger(app('log')->driver());
            }
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
        /*DB::listen(function ($query) {
                $tmp = str_replace('?', '"' . '%s' . '"', $query->sql);
                $qBindings = [];
                foreach ($query->bindings as $key => $value) {
                    if (is_numeric($key)) {
                        $qBindings[] = $value;
                    } else {
                        $tmp = str_replace(':' . $key, '"' . $value . '"', $tmp);
                    }
                }
                
                try {
                    $tmp = vsprintf($tmp, $qBindings);
                    $tmp = str_replace("\\", "", $tmp);
                    Log::info(' execution time: ' . $query->time . 'ms; ' . $tmp . "\n\t");
                } catch (Exception $e) {

                }
            });*/
    }
}
