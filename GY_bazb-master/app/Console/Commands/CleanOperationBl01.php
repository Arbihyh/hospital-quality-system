<?php

namespace App\Console\Commands;

use App\Services\OperationService;
use Illuminate\Console\Command;

class CleanOperationBl01 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:OperationBl01Clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 清洗手术记录中的手术日期';

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
        echo 'start command:OperationBl01Clean ' . date("Y-m-d H:i:s");
        OperationService::checkList();
        echo 'end command:OperationBl01Clean ' . date("Y-m-d H:i:s");
    }
}
