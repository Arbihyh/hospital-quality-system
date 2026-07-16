<?php

namespace App\Console\Commands\Ningxia;

use App\Model\Staff;
use App\Model\ZY_BRRY;
use App\Model\YS_ZY_HZYJ;
use Illuminate\Console\Command;

class CleanHZYJ extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'ningxia:clean_hzyj';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 清洗宁厦的会诊意见数据';

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
        $staff = Staff::query()->get(['code', 'base_code'])->toArray();
        $staff = array_column($staff, 'code', 'base_code');
        $rows = YS_ZY_HZYJ::query()->get(['JLXH', 'SSYS', 'SXYS', 'QMYS'])->toArray();

        foreach ($rows as $ss) {
            echo $ss['JLXH'] . PHP_EOL;
            $updateData = [];
            $updateData['SSYS'] = $staff[$ss['SSYS']] ?? $ss['SSYS'];
            $updateData['SXYS'] = $staff[$ss['SXYS']] ?? $ss['SXYS'];
            $updateData['QMYS'] = $staff[$ss['QMYS']] ?? $ss['QMYS'];
            YS_ZY_HZYJ::query()->where('JLXH', $ss['JLXH'])->update($updateData);
        }
    }

}
