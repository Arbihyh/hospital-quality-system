<?php

namespace App\Console\Commands\Ningxia;

use App\Model\ZY_BRRY;
use App\Model\V_JMGS_TESTRESULT;
use Illuminate\Console\Command;

class CleanTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'ningxia:clean_test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 清洗宁厦的检查检验数据';

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
        
        $id = 0;
        while (true) {
            $rows = V_JMGS_TESTRESULT::query()
            ->where('id', '>', $id)
            ->orderBy('id', 'asc')
            ->limit(10000)->get(['id', 'AAA28', 'ZYCS', 'ZYH'])->toArray();
            if (empty($rows)) {
                break;
            }
            echo $id . PHP_EOL;

            foreach ($rows as $ss) {
                // 从手术表获取pat_no和inf_starttime
                $pat_no = $ss['AAA28'] ?? '';
                $ZYCS = $ss['ZYCS'] ?? null;
                if (!$pat_no || !$ZYCS) {
                    continue;
                }
                $brrys = ZY_BRRY::query()
                    ->where('AAA28', $pat_no)
                    ->get(['AAA28', 'ZYH', 'ZYCS'])
                    ->toArray();
                if ($brrys) {
                    foreach ($brrys as $brry) {
                        if ($brry['ZYCS'] == $ZYCS && $brry['ZYH'] != $ss['ZYH']) {
                            V_JMGS_TESTRESULT::query()->where('id', $ss['id'])->update(['ZYH' => $brry['ZYH']]);
                        }
                    }
                }
            }
            $id = $rows[count($rows) - 1]['id'];
        }

    }

}
