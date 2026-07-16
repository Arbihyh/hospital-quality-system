<?php

namespace App\Console\Commands;

use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Model\Setting;
use App\Services\BlDataFormatService;
use App\Services\IihinterfaceService;
use App\Services\LanLingIihinterfaceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LanLingSyncBlContent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:lan-ling-sync-bl-content {startTime?} {endTime?}';

    /**
     * The console command description.
     * 通过HIS提供接口获取病历详细内容
     * @var string
     */
    protected $description = '同步病历内容';

    private $secret_key = '';

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
        $startTime = data_get($this->arguments(), 'startTime');
        $endTime = data_get($this->arguments(), 'endTime');

        $sync_setting = Setting::query()->where('name', 'lan-ling-sync-bl-content')->first();
        $sync_last_id = $sync_setting ? $sync_setting->content : 0;

        $this->info("同步病历内容开始时间::{$startTime},结束时间::{$endTime}");
        $carbon = new Carbon();
        $bldf = new BlDataFormatService();

        $query = EMR_BL_BL01::query()
            ->when($startTime && $endTime, function ($query) use ($carbon, $startTime, $endTime) {
                return $query->whereBetween('AAC01', [$carbon::parse($startTime)->format("Y-m-d 00:00:00"), $carbon::parse($endTime)->format("Y-m-d 23:59:59")]);
            })->orderBy('id');

        while ($query->where('id', '>', $sync_last_id)->count() > 0) {
            collect($query->where('id', '>', $sync_last_id)->limit(10000)->get())->map(function ($item) use (&$sync_last_id, $bldf) {
                $this->info("病历内容同步::{$item->id}");
                $is_sync_content = 2;
                $sync_content_msg = '';
                //查询病历内容信息
                $res = LanLingIihinterfaceService::getModel()->setUri('get_mr_details', true)->setXmlParams(['Id_mr' => $item->BLBH])->postCurl();

                if ($res && data_get($res, 'Code') === '0' && data_get($res, 'Data.Result_flag') == 'Y') {
                    $Mr_str = str_replace('[图片]', '', data_get($res, 'Data.Mr_str'));
                    if (empty($Mr_str)) {
                        $sync_content_msg = json_encode($res);
                        Log::error('病历内容Data为空::', array_merge(['Id_mr' => $item->BLBH], compact('res')));
                    } else {
                        EMR_BL_BLXG::query()->updateOrInsert(['BLBH' => $item->BLBH], ['HJNR' => $Mr_str]);
                        $item->HJNR =  $Mr_str;
                        $bldf->insertData([], $item->MBLB, json_decode(json_encode($item, 256), true));
                        $is_sync_content = 1;
                    }
                } else {
                    $sync_content_msg = ($res === false ? '查询或解密失败！！' : json_encode($res));
                    Log::error('Iih病历内容查询失败::', array_merge(['Id_mr' => $item->BLBH], compact('res')));
                }

                EMR_BL_BL01::query()->where('id', $item->id)->update(compact('is_sync_content', 'sync_content_msg'));
                $sync_last_id = $item->id;
            });
            Setting::query()->updateOrInsert(['name' => 'lan-ling-sync-bl-content'], [
                'content' => $sync_last_id,
                'updated_at' => Carbon::now(),
                'remark' => '兰陵IIH病历内容同步完成'
            ]);
        }
        $this->info('IIH病历内容同步完成');
    }
}
