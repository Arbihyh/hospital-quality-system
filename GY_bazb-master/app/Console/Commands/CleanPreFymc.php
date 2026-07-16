<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RadioService;

class CleanPreFymc extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:cleanPreFymc';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 清洗费用明细中的费用名称前缀';

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
        echo 'start command:cleanPreFymc '.date("Y-m-d H:i:s");
        try{
            $radioService = new RadioService();
            $radioService->cleanPreFymc();
        }catch (\Throwable $e){
            var_dump("command laravel:cleanPreFymc 错误:code:".$e->getFile().'；line:'.$e->getLine().'；错误信息：'.$e->getMessage());
        }

        echo 'end command:cleanPreFymc '.date("Y-m-d H:i:s");
    }
}
