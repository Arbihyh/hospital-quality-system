<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\YK_TYPK;
use Illuminate\Console\Command;

class SyncOmrYT extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:omrYt';

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
        $data = YK_TYPK::query()->get(['id','YPXH','YPMC'])->toArray();

        $index = 'omr_yt_2023';
        if (!empty($data)) {
            $es_params = [];
            foreach ($data as $val) {
                // 要同步到Es的数据
                $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $val['id']]];
                $es_params['body'][] = ['doc' => [
                    "data_id" => $val['id'],
                    "YPXH" => $val['YPXH'],
                    "YPMC" => $val['YPMC'],
                ], 'doc_as_upsert' => true];
            }

            app('es')->bulk($es_params);
        }

        $this->info('索引：'.$index.' 数据处理完成');
    }
}
