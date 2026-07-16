<?php

namespace App\Console\Commands;

use App\Model\Staff;
use Illuminate\Console\Command;
use App\Services\CaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pheanstalk\Pheanstalk;

class Department extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:department';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 病例质控信息';

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
        try {
            $sql = 'update BA_SYSB_ZDDZ_x as a inner join department as b on a.HISZ=b.dep_id SET a.KSMC=b.dep_name';
            DB::update($sql);
        } catch (\Throwable $e) {
            $this->error("command quality 错误:code:".$e->getFile().':'.$e->getCode().'；line:'.$e->getLine().'；错误信息：'.$e->getMessage());
        }

        $this->info('部门信息同步成功 - '.date("Y-m-d H:i:s"));
    }
}
