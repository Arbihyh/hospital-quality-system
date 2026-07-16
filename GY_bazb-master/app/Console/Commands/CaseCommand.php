<?php

namespace App\Console\Commands;

use App\Model\Staff;
use Illuminate\Console\Command;
use App\Services\RuyuanService;
use Illuminate\Support\Facades\Log;
use Pheanstalk\Pheanstalk;

class CaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:quality-ry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 质控入院记录信息';

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
        try{
            $ruyuanService = new RuyuanService();
            $ruyuanService->checkCaseList();
        }catch (\Throwable $e){
            var_dump("command laravel:illness 错误:code:".$e->getCode().'；line:'.$e->getLine().'；错误信息：'.$e->getMessage());
        }

        echo 'end command:case '.date("Y-m-d H:i:s");
    }
}
