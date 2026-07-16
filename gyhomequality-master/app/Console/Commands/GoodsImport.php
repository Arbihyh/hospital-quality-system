<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GoodsImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:goods';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '导入商品信息';

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
        $file = 'goods.xls';
        if(!Storage::disk('local')->exists($file)){
            $this->error($file.'文件不存在');
            return ;
        }
        \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\GoodsImport() , $file , 'local');

        $this->info('商品导入完毕');
    }
}
