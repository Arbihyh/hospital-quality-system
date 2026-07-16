<?php

namespace App\Console\Commands;

use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLSY;
use Illuminate\Console\Command;

class CleanIsCata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'clean_is_cata';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清洗病历首页的数据是否未编目';

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
        echo $this->description . " start: " . date('Y-m-d H:i:s') . "\n";
        $page = 1;
        $pageSize = 1000;
        while (true) {
            $data = EMR_BL_BL01::query()->whereNull('first_blsy_time')->orWhere('first_blsy_time', '=', '')
                ->paginate($pageSize, ['id', 'BLBH'], 'page', $page)->toArray();
            $data = $data['data'];
            if (empty($data)) {
                break;
            }

            foreach ($data as $item) {
                $this->editFirstBlsyTime($item);
            }
            $page++;
        }

        echo $this->description . " end: " . date('Y-m-d H:i:s') . "\n";
        exit();
    }

    public function editFirstBlsyTime($item = [])
    {
        $JLSJ = EMR_BL_BLSY::query()->where('BLBH', '=', $item['BLBH'])->orderBy('JLSJ', 'ASC')->first(['JLSJ']);
        if (!empty($JLSJ)) {
            $JLSJ = $JLSJ->toArray();
            echo $item['BLBH']."\n";
            EMR_BL_BL01::query()->where('BLBH', '=', $item['BLBH'])->update(['first_blsy_time' => $JLSJ['JLSJ']]);
        }
    }
}
