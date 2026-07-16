<?php

namespace App\Console\Commands;

use App\Model\OMR_BL01;
use Illuminate\Console\Command;

class Clean_OMR_BL01_BLBH extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clean_omr_bl01_blbh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清洗omr_bl01表的BLBH';

    public static $con;

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
        //1.获取BL01表数据
        $page = 1;
        $pageSize = 10000;
        while (true) {
            //2.分页获取BL01表id和BLBH3
            $result = OMR_BL01::query()
                ->where('BLBH', 0)
                ->orderBy('id','asc')
                ->paginate($pageSize, ['id'], "page", $page)->toArray();
            $data = $result['data'];
            //程序终止条件
            if (empty($data)) {
                break;
            }
            //3.更新BL01表，BLXG表，BLSY表的BLBH
            if (!empty($data)) {
                foreach ($data as $v) {
                    OMR_BL01::query()->where('id', $v['id'])->update(['BLBH'=>$v['id']]);
                }
            }
            $page++;
        }
        echo "OMR_BL01表BLBH清洗完成" . PHP_EOL;
        die();
    }
}
