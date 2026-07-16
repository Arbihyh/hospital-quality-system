<?php

namespace App\Console\Commands;

use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Services\IihinterfaceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncBlContent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:sync-bl-content {startTime?} {endTime?}';

    /**
     * The console command description.
     * 通过HIS提供接口获取病历详细内容
     * @var string
     */
    protected $description = '同步病历内容';

    private $secret_key = '5F384D4AB3CD18C984BCFB1F45D23684';

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
        $startTime = data_get($this->arguments(),'startTime');
        $endTime = data_get($this->arguments(),'endTime');

        $this->info("同步病历内容开始时间::{$startTime},结束时间::{$endTime}");
        $carbon = new Carbon();

        $query = EMR_BL_BL01::query()->select(DB::raw('EMR_BL_BL01.*'))
            ->leftJoin((new EMR_BL_BLXG())->getTable() . ' as blxg','blxg.BLBH','=','EMR_BL_BL01.BLBH')->where('is_sync_content',0)
            ->when($startTime && $endTime,function($query)use($carbon,$startTime,$endTime){
                return $query->whereBetween('EMR_BL_BL01.updated_at',[$carbon::parse($startTime)->format("Y-m-d 00:00:00"),$carbon::parse($endTime)->format("Y-m-d 23:59:59")]);
            })
//            ->whereNull('blxg.BLBH')->orWhere('blxg.BLBH','=','')
            ->orderBy('EMR_BL_BL01.BLBH');
//            ->chunk(1000,function($datas){
                while ($query->count() > 0) {
                    collect($query->limit(1000)->get())->map(function ($item) {
                        $this->info("病历内容同步::{$item->id}");
                        $is_sync_content = 2;
                        $sync_content_msg = '';
                        //查询病历内容信息
                        $res = IihinterfaceService::getModel()->setUri('get_mr_details', true)->setXmlParams(['Id_mr' => $item->BLBH])->postCurl();

                        if ($res && data_get($res, 'Code') === '0') {

                            //获取加密串
                            $encryptStr = data_get($res, 'Data', '');

                            //解密 国密4
                            $blXml = openssl_decrypt($encryptStr, "SM4-ECB", hex2bin($this->secret_key), 0);

                            //将xml转成
                            $blJson = json_encode(simplexml_load_string($blXml, 'SimpleXMLElement', LIBXML_NOCDATA));

                            $blContent = json_decode($blJson, true);

                            if ($encryptStr && $blContent && data_get($blContent, 'Result_flag') == 'Y') {
                                $Mr_str = str_replace('[图片]', '', data_get($blContent, 'Mr_str'));
                                if (!empty($Mr_str)) {
                                    try {
                                        DB::beginTransaction();
                                        EMR_BL_BLXG::query()->updateOrInsert(['BLBH' => $item->BLBH], ['HJNR' => $Mr_str, 'XGSJ' => $item->WCSJ]);
                                        DB::commit();
                                        $is_sync_content = 1;
                                    } catch (\Exception $e) {
                                        DB::rollBack();
                                        $sync_content_msg = '更新或创建记录失败: ' . $e->getMessage();
                                        Log::error('病历内容更新失败::', ['Id_mr' => $item->BLBH, 'error' => $e->getMessage()]);
                                    }
                                } else {
                                    $sync_content_msg = json_encode($blContent);
                                    Log::error('病历内容Data为空::', array_merge(['Id_mr' => $item->BLBH], compact('blContent')));
                                }
                            } else {
                                $sync_content_msg = $blJson;
                                Log::error('Iih病历内容Data为空::', array_merge(['Id_mr' => $item->BLBH], compact('res', 'encryptStr', 'blXml', 'blJson', 'blContent')));
                            }
                        } else {
                            $sync_content_msg = ($res === false ? '查询或解密失败！！' : json_encode($res));
                            Log::error('Iih病历内容查询失败::', array_merge(['Id_mr' => $item->BLBH], compact('res')));
                        }

                        EMR_BL_BL01::query()->where('BLBH', $item->BLBH)->update(compact('is_sync_content', 'sync_content_msg'));
                    });
                }
//            });
        $this->info('IIH病历内容同步完成');
    }
}
