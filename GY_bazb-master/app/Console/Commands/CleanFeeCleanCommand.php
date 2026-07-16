<?php

namespace App\Console\Commands;

use App\Services\RadioService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanFeeCleanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:feeClean';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Command 清洗费用明细中的病理数据';

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
        echo 'start command:feeClean ' . date("Y-m-d H:i:s");
        try {
            $radioService = new RadioService();
            $radioService->feeClean();
        } catch (\Throwable $e) {
            var_dump("command command:feeClean 错误:code:" . $e->getCode() . '；line:' . $e->getLine() . '；错误信息：' . $e->getMessage());
        }

        echo 'end command:feeClean ' . date("Y-m-d H:i:s");
    }
}
