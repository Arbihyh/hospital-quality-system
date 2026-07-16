<?php

namespace App\Console\Commands;

use App\Model\Staff;
use Illuminate\Console\Command;
use App\Services\IllnessTypeService;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;

class Illness extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:illness
     */
    protected $signature = 'command:illness';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 定时生成执行相关诊断信息的统计信息';

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
        Log::info('start command:illness '.date("Y-m-d H:i:s"));
        try{
            IllnessTypeService::illness();
        }catch (\Throwable $e){
            Log::error("command laravel:illness 错误:code:".$e->getCode().'；line:'.$e->getLine().'；错误信息：'.$e->getMessage());
        }

        Log::info('end command:illness '.date("Y-m-d H:i:s"));
    }
}
