<?php

namespace App\Console\Commands\Target;

use App\Services\TargetService;
use Illuminate\Console\Command;

class GermFoster extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zb:germFoster';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '细菌培养检查记录符合率 - 数据处理';

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
        $this->info('细菌培养检查记录符合率指标 - 数据开始处理');

        $targetService = new TargetService();

        // 细菌培养检查记录符合率处理
        $targetService->germFosterDataHandle();

        $this->info('细菌培养检查记录符合率指标 - 数据处理完毕');
    }
}
