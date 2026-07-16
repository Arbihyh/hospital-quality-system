<?php

namespace App\Console\Commands\Target;

use App\Services\TargetService;
use Illuminate\Console\Command;

class Implants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zb:implants';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '植入物相关记录符合率指标 - 数据处理';

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
        $this->info('植入物相关记录符合率指标 - 数据开始处理');

        // 植入物相关记录符合率指标 数据处理
        $targetService = new TargetService();
        $targetService->ImplantsDataHandle();

        $this->info('植入物相关记录符合率指标 - 数据处理完毕');
    }
}
