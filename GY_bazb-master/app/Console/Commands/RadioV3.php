<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RadioService;
use Illuminate\Support\Facades\Log;
use App\Services\RadioServiceV3;

class RadioV3 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:zbv3 {type} {zyh?} {start_time?} {end_time?}';

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
        $zyh = $this->argument('zyh');
        $start_time = $this->argument('start_time');
        $start_time = $start_time ? strtotime($start_time) : 0;
        $end_time = $this->argument('end_time');
        $end_time = $end_time ? strtotime($end_time) : 0;
        $type = $type ?: 'cacheData';

        if (empty($start_time)) {
            $start_time = strtotime(date("Y-m-01", time()));
        }

        if (empty($end_time)) {
            $end_time = strtotime(date("Y-m-d", time()+3600*24));
        }

        $this->info('start_time: '.date("Y-m-d H:i:s", $start_time));
        $this->info('end_time: '.date("Y-m-d H:i:s", $end_time));

        $radioService = new RadioServiceV3();
        $radioService->$type($zyh, $start_time, $end_time);
        $this->info('end '.date("Y-m-d H:i:s"));
    }
}
