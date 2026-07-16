<?php

namespace App\Console\Commands\ZhiBiao;

use Illuminate\Console\Command;
use App\Http\Controllers\Api\IndicatorController;
use Illuminate\Http\Request;

class SptUploadDataCommand extends Command
{
    protected $signature = 'spt:upload-data {period?}';
    protected $description = '执行数据上传到省平台';

    public function handle()
    {
        $period = trim((string)$this->argument('period'));
        if ($period === '') {
            $period = date('Y-m', strtotime('first day of last month'));
        }

        $controller = app()->make(IndicatorController::class);
        $request = new Request([
            'period' => $period,
        ]);
        $result = $controller->sptUploadData($request);

        $this->info('上传周期：' . $period);
        $this->info('上传结果：' . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return 0;
    }
}
