<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RadioService;
use Illuminate\Support\Facades\Log;

class Radio extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:radio';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 清洗医嘱本字段';

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
        echo 'start command:case '.date("Y-m-d H:i:s");
        $radioService = new RadioService();
        try{
            $radioService->cacheData();
        }catch (\Throwable $e){
            var_dump("command:radio 错误:code:".$e->getCode().'；line:'.$e->getLine().'；错误信息：'.$e->getMessage());
            Log::info();
        }
        echo 'end command:case '.date("Y-m-d H:i:s");
    }
}
