<?php

namespace App\Console\Commands\Target;

use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use Illuminate\Console\Command;

class ZbKeyWordSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zb:keyword {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $conf = config('confAdmin');
        $endTime = date('Y-m-d H:i:s', time());
        $this->info("关键字处理 - 开始");

        $page = (int)$this->argument('page') ?: 1;
        while (true) {
            // 所有符合条件的病例信息
            $data = EMR_BL_BL01::query()
                ->whereIn('BLLB',[18,294])
                ->where('BLZT','!=',9)
                ->whereBetween('ZXSJ',[$conf['zb_start_time'], $endTime])
                ->paginate(500, ['BLBH','zb_keywords'],'page', $page)
                ->toArray();
            if ($page == 1) {
                echo '数据总条数：'.$data['total'].PHP_EOL.'总页数：'.$data['last_page'].PHP_EOL;
            } elseif ($page > $data['last_page']) {
                // 如果当前页码大于最大页码则结束
                break;
            }
            echo $page.PHP_EOL;
            $page++;

            foreach ($data['data'] as $value) {
                $keyWordList = [];
                $hjnrData = EMR_BL_BLXG::query()
                    ->where('BLBH','=',$value['BLBH'])
                    ->pluck('HJNR')->toArray();
                if ($hjnrData) {
                    foreach ($hjnrData as $hjnr) {
                        $hjnr = str_replace('oct','',$hjnr);
                        if (stripos($hjnr,'CT') !== false) {
                            $keyWordList[] = 'CT';
                        }
                        if (stripos($hjnr,'MR') !== false) {
                            $keyWordList[] = 'MR';
                        }
                        if (stripos($hjnr,'磁共振') !== false) {
                            $keyWordList[] = '磁共振';
                        }
                    }

                    $keyWordList = array_unique($keyWordList);
                    if ($keyWordList) {
                        EMR_BL_BL01::query()
                            ->where('BLBH','=',$value['BLBH'])
                            ->update(['zb_keywords'=>implode(',',$keyWordList)]);
                    }
                }
            }
        }

        $this->info("关键字处理 - 完毕");
    }
}
