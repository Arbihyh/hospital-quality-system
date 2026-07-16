<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RadioServiceV2;
use Illuminate\Support\Facades\Log;
use App\Services\zb_baglService;
use App\Model\IndexCatalog;

class zb_bagl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:zb_bagl {type} {zyh?} {start?} {end?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 指标数据';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $type = $this->argument('type');
        $type = $type ?: 'cacheData';
        $start = $this->argument('start');
        $start = $start ? date('Y-m-d 00:00:00', strtotime($start)) : date('Y-m-d 00:00:00', strtotime("-7 day", time())); //没有传-7天
        $end = $this->argument('end');
        $end = $end ? date('Y-m-d 23:59:59', strtotime($end)) : date('Y-m-d 23:59:59', time());
        $zyh = $this->argument('zyh');
        $zyh = $zyh ?: '';
        echo 'start ' . date("Y-m-d H:i:s");
        echo '开始执行: ' . $type . ' - ' . $start . ' - ' . $end . ' - ' . $zyh;
        $moduleName = env("APP_NAME", "");
        $className = "\\App\\Services\\MysqlDataSync\\{$moduleName}\\ZbBaglService";
        $zbBaglService = new $className();
        // try {
            IndexCatalog::editStatus($type, 1);
            $zbBaglService->$type($zyh, $start, $end);
            IndexCatalog::query()->where("index_name", "=", $type)->update(["status" => 2, 'quality_time' => time()]);
        // } catch (\Throwable $e) {
            // var_dump("command:radio 错误:code:" . $e->getCode() . '；line:' . $e->getLine() . '；错误信息：' . $e->getMessage());
        // }
        echo 'end ' . date("Y-m-d H:i:s");
    }
}
