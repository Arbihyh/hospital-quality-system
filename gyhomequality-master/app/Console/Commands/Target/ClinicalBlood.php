<?php

namespace App\Console\Commands\Target;

use App\Services\TargetService;
use Illuminate\Console\Command;

class ClinicalBlood extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zb:lcyx';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '临床用血相关记录符合率 - 数据处理';

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
        $this->info('临床用血相关记录符合率 - 数据开始处理');

        $targetService = new TargetService();

        // 临床用血相关记录符合率处理
        $targetService->clinicalBloodDataHandle();

        $this->info('临床用血相关记录符合率 - 数据处理完毕');
    }
}
