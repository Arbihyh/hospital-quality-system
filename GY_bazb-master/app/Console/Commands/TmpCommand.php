<?php

namespace App\Console\Commands;

use App\Model\QueueList;
use App\Model\ZY_BRRY;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TmpCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'in-list-tmp-command {start_time?} {end_time?}';

    /**
     * The console command description.q
     *
     * @var string
     */
    protected $description = '加入消息队列临时任务';

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
     *
     */
    public function handle()
    {
        $start = $this->argument("start_time");
        if (empty($start)) {
            $start = date("Y-m-d 00:00:00", time() - 30 * 24 * 3600);
        } else {
            $start = $start . " 00:00:00";
        }
        $end = $this->argument("end_time");
        if (empty($end)) {
            $end = date("Y-m-d 23:59:59");
        } else {
            $end = $end . " 23:59:59";
        }

        DB::insert("insert into queue_list (data,type) select ZYH, 'analysis' AS type from ZY_BRRY where (AAB01>'{$start}' and AAB01<'{$end}') or (AAC01 is null or AAC01 = '')");
    }
}
