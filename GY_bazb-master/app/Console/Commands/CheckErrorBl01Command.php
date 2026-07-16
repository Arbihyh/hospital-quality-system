<?php

namespace App\Console\Commands;

use App\Model\Bllb292;
use App\Model\QueueList;
use Illuminate\Console\Command;


class CheckErrorBl01Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'check-error-bl01';

    /**
     * The console command description.q
     *
     * @var string
     */
    protected $description = '检查BL01中格式化失败的问题数据';

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
     * 将格式化失败的数据加入队列
     */
    public function handle()
    {
        $bllb = Bllb292::query()
            ->where("ZHS", 'like', '%请输入%')
            ->get(["ZYH"])
            ->toArray();
        $bllb = array_column($bllb, 'ZYH');
        $bllb = array_chunk($bllb, 100);
        foreach ($bllb as $key => $value) {
            $addData = [];
            foreach ($value as $k => $v) {
                $addData[] = ['data'=>$v, 'type' => 'analysis'];
            }
            QueueList::query()->insert($addData);
        }
    }

}
