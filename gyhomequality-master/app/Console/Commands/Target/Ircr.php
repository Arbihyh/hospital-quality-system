<?php

namespace App\Console\Commands\Target;

use App\Services\TargetService;
use Illuminate\Console\Command;

class Ircr extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zb:ircr';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'CT/MRI检查记录符合率 - 数据处理';

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
        $this->info('CT/MRI检查记录符合率 - 数据开始处理');

        // CT/MRI检查记录符合率 数据处理
        $targetService = new TargetService();
        $targetService->IrcrDataHandle();

        $this->info('CT/MRI检查记录符合率 - 数据处理完毕');
    }


}
