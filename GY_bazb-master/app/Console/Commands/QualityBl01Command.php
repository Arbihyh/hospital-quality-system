<?php

namespace App\Console\Commands;

use App\Model\Staff;
use Illuminate\Console\Command;
use App\Services\QualityBl01Service;
use Illuminate\Support\Facades\Log;
use Pheanstalk\Pheanstalk;

class QualityBl01Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:quality-bl01 {type?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 病程相关质控';

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
        $type = $this->argument('type');
        $type = $type ?? 'chuyuan';
        echo 'start command:quality-bl01 '.date("Y-m-d H:i:s");
        try{
            $ruyuanService = new QualityBl01Service();
            $ruyuanService->$type();
        }catch (\Throwable $e){
            var_dump("command laravel:illness 错误:code:".$e->getCode().'；line:'.$e->getLine().'；错误信息：'.$e->getMessage());
        }

        echo 'end command:quality-bl01 '.date("Y-m-d H:i:s");
    }
}
