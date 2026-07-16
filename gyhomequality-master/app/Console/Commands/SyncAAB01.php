<?php

namespace App\Console\Commands;

use App\Model\PatientHospitalInfo;
use App\Model\PatientInfo;
use Illuminate\Console\Command;

class SyncAAB01 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:AAB01 {page?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清洗patient_info表中AAB01字段';

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
        $page = (int)$this->argument('page') ?: 1;
        while (true) {
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->where('AAC01','>','2023-06-28 00:00:00')
                ->paginate(500, ['id','AAA28','MED_REC_ID','AAB01'], 'page', $page)
                ->toArray();

            if ($page == 1) {
                echo '数据总条数：' . $data['total'] . PHP_EOL . '总页数：' . $data['last_page'] . PHP_EOL;
            } elseif ($page > $data['last_page']) {
                break;
            }

            echo $page . PHP_EOL;
            $page++;

            if (!empty($data['data'])) {
                // 分子
                foreach ($data['data'] as $value) {
                    if (empty($value['AAB01'])) {
                        $AAB01 = PatientHospitalInfo::query()
                            ->where('AAA28','=',$value['MED_REC_ID'])
                            ->value('AAB01');

                        PatientInfo::query()->where('id','=',$value['id'])->update(['AAB01'=>$AAB01]);
                    }
                }
            } else {
                break;
            }
        }
    }
}
