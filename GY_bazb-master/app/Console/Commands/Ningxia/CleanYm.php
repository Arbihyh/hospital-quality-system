<?php

namespace App\Console\Commands\Ningxia;

use App\Model\ZY_BRRY;
use App\Model\EMR_BL_BL01;
use App\Model\V_JMGS_YMresult;
use Illuminate\Console\Command;

class CleanYm extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'ningxia:clean_ym';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 清洗宁厦的药敏数据';

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
            $rows = V_JMGS_YMresult::query()
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
                if (!$pat_no || !$ZYCS || !empty($ss['ZYH'])) {
                    continue;
                }
                $brrys = ZY_BRRY::query()
                    ->where('AAA28', $pat_no)
                    ->get(['AAA28', 'ZYH', 'ZYCS'])
                    ->toArray();
                if ($brrys) {
                    foreach ($brrys as $brry) {
                        if ($brry['ZYCS'] == $ZYCS && $brry['ZYH'] != $ss['ZYH']) {
                            V_JMGS_YMresult::query()->where('id', $ss['id'])->update(['ZYH' => $brry['ZYH']]);
                        }
                    }
                }
            }
            $id = $rows[count($rows) - 1]['id'];
        }


        
        // $batchSize = 500;
        // $lastId = 34914712;
        // while (true) {
        //     // 获取id>34914712的前500条
        //     $rows = V_JMGS_YMresult::query()
        //         ->where('id', '>', $lastId)
        //         ->orderBy('id', 'asc')
        //         ->limit($batchSize)
        //         ->pluck('id')
        //         ->toArray();

        //     if (empty($rows)) {
        //         break;
        //     }

        //     // 执行删除
        //     V_JMGS_YMresult::query()
        //         ->whereIn('id', $rows)
        //         ->delete();

        //     // 更新lastId为当前批次的最后一个id
        //     $lastId = end($rows);

        //     // 可选：输出进度
        //     $this->info("Deleted up to id: {$lastId}");
        // }
    }

}
