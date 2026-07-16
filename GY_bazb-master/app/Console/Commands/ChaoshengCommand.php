<?php

namespace App\Console\Commands;

use App\Model\Staff;
use Illuminate\Console\Command;
use App\Services\CaseService;
use Illuminate\Support\Facades\Log;
use Pheanstalk\Pheanstalk;
use App\Services\UltrasonicService;

class ChaoshengCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:chaosheng';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 病例超声质控信息';

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

        $this->info('超声质控规则 - 开始处理 - '.date("Y-m-d H:i:s"));

        try {
            $caseService = new UltrasonicService();
            $caseService->qualityContrl();
        } catch (\Throwable $e) {
            $this->error("command quality 错误:code:".$e->getFile().':'.$e->getCode().'；line:'.$e->getLine().'；错误信息：'.$e->getMessage());
        }

        $this->info('超声质控规则 - 处理结束 - '.date("Y-m-d H:i:s"));
    }
}
