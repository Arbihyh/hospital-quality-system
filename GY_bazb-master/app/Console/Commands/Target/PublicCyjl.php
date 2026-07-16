<?php

namespace App\Console\Commands\Target;

use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use Illuminate\Console\Command;

class PublicCyjl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zb:publicCyjl {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '所有出院患者';

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
        $this->info('所有出院记录患者 - 开始');

        $page = (int)$this->argument('page') ?: 1;

        while (true) {
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->whereBetween('AAC01',[$conf['zb_start_time'], $endTime])
                ->paginate(500, ['AAA28','MED_REC_ID','AAC01'],'page', $page)
                ->toArray();

            if ($page == 1) {
                echo '数据总条数：'.$data['total'].PHP_EOL.'总页数：'.$data['last_page'].PHP_EOL;
            } elseif ($page > $data['last_page']) {
                // 如果当前页码大于最大页码则结束
                break;
            }
            echo $page.PHP_EOL;
            $page++;

            // 分子
            foreach ($data['data'] as $value) {
                if (!$value['AAC01']) {
                    continue;
                }

                // 记录
                $saveData = ['numerator_public_cyjl'=>1];
                PatientInfoTarget::query()->updateOrInsert(['ZYH'=>$value['MED_REC_ID']], $saveData);
            }
        }

        $this->info('所有出院记录患者 - 完毕');
    }
}
