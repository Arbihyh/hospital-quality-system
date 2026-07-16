<?php

namespace App\Console\Commands;

use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Model\Setting;
use App\Services\DataxSync\DataSyncService;
use App\Services\IihinterfaceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class HjnrSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hjnr-sync {type?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'HJNR数据同步';

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
        $type = data_get($this->arguments(),'type');

        $func = '_' . $type;

        $this->$func();
    }


    /**
     * 同步BL01数据HJNR_HTML内容
     */
    private function _bl01(){
        $this->info('EMR_BL_BL01同步HJNR_HTML数据脚本');

	$id = 0;
        $query = EMR_BL_BL01::query()
            ->select(['id','BLBH'])
            ->where('updated_at', '>=', Carbon::now()->subDays(30)->startOfDay());
        while ($query->where('id','>',$id)->count() > 0) {
            collect($query->where('id','>',$id)->orderBy('id')->limit(10000)->get())->map(function ($item)use(&$id) {
                $this->info("病历内容同步::{$item->id}");
                $sql = "SELECT NVL(EMR_BL_BL01.HJNR_HTML, '') AS HTML_PRINT FROM EMR_BL_BL01 WHERE BLBH = '{$item->BLBH}'";
                $res = DataSyncService::getInstance()->setSql($sql)->getResult();
                if(!empty($res)){
                    $html_print = $this->blobToStr(data_get($res,'0.HTML_PRINT'));
                    EMR_BL_BL01::query()->where('id',$item->id)->update(['HTML_PRINT' => $html_print]);
                }
                $id = $item->id;
                $this->info($item->BLBH."--同步EMR_BL_BL01数据完成");
                $this->blxg($item->BLBH);
            });
        }

        $this->info('EMR_BL_BL01同步HJNR_HTML数据完成');

    }


    private function blxg($BLBH = ''){
        $this->info('EMR_BL_BLXG同步HJNR数据脚本');

        if(!empty($BLBH)){

            $sql = "SELECT NVL(EMR_BL_BLXG.HJNR, '') AS HJNR,NVL(EMR_BL_BLXG.JLXH, '') AS JLXH,NVL(EMR_BL_BLXG.XGGH, '') AS XGGH,NVL(EMR_BL_BLXG.XGSJ, '') AS XGSJ FROM EMR_BL_BLXG WHERE BLBH = '{$BLBH}'";
            $res = DataSyncService::getInstance()->setSql($sql)->getResult();
            if(!empty($res)){
                $hjnr = $this->blobToStr(data_get($res,'0.HJNR'));
                EMR_BL_BLXG::query()->updateOrInsert(['BLBH' => $BLBH], ['HJNR' => $hjnr,'JLXH' => data_get($res,'0.JLXH'),'XGGH' => data_get($res,'0.XGGH'),'XGSJ' => data_get($res,'0.XGSJ')]);
            }
        }

        $this->info($BLBH.'--EMR_BL_BLXG同步HJNR数据完成');
    }

    public function blobToStr($blob = null)
    {
        $str = '';
        if (!is_object($blob)) {
            return $blob;
        }
        if (!empty($blob)) {
            $text = $blob->load();
            $blob->free();
            $mde = mb_detect_encoding($text, array("ASCII", 'UTF-8', "GB2312", "GBK", 'BIG5'));
            if ($mde) {
                $str = mb_convert_encoding($text, 'utf-8', $mde);
            }
        }

        return $str;
    }

    /**
     * 同步BL01数据HJNR_HTML内容
     */
    private function _bl01V2(){
        $this->info('EMR_BL_BL01同步HJNR_HTML数据脚本');

        $id = Setting::query()->where('name','hjnr-sync-bl01')->value("keyword") ?: 0;
        $query = EMR_BL_BL01::query()->select(['id','HTML_PRINT']);

        $query->where('id','>',$id)->whereNull("HTML_PRINT")->orderBy('id')->chunkById(10000,function($items){
            $last_id = 0;
            collect($items)->map(function ($item)use(&$last_id) {
                $this->info("BL01病历内容同步::{$item->id}");
                $sql = "SELECT NVL(EMR_BL_BL01.HJNR_HTML, '') AS HTML_PRINT FROM EMR_BL_BL01 WHERE BLBH = '{$item->BLBH}'";
                $res = DataSyncService::getInstance()->setSql($sql)->getResult();
                if(!empty($res)){
                    $html_print = $this->blobToStr(data_get($res,'0.HTML_PRINT'));
                    EMR_BL_BL01::query()->where('id',$item->id)->update(['HTML_PRINT' => $html_print]);
                }
                $last_id = $item->id;
            });
            Setting::query()->where('name','hjnr-sync-bl01')->updateOrInsert(['name'=> 'hjnr-sync-bl01','keyword' => $last_id]);
        },"id");
//        while ($query->where('id','>',$id)->count() > 0) {
//            collect($query->where('id','>',$id)->orderBy('id')->limit(1000)->get())->map(function ($item)use(&$id) {
//                $this->info("病历内容同步::{$item->id}");
//                $sql = "SELECT NVL(EMR_BL_BL01.HJNR_HTML, '') AS HTML_PRINT FROM EMR_BL_BL01 WHERE BLBH = '{$item->BLBH}'";
//                $res = DataSyncService::getInstance()->setSql($sql)->getResult();
//                if(!empty($res)){
//                    $html_print = $this->blobToStr(data_get($res,'0.HTML_PRINT'));
//                    EMR_BL_BL01::query()->where('id',$item->id)->update(['HTML_PRINT' => $html_print]);
//                }
//                $id = $item->id;
//            });
//        }

        $this->info('EMR_BL_BL01同步HJNR_HTML数据完成');
    }


    private function _blxgV2(){
        $this->info('EMR_BL_BLXG同步HJNR数据脚本');

        $id = Setting::query()->where('name','hjnr-sync-blxg')->value("keyword") ?: 0;

        $query = EMR_BL_BLXG::query()->select(['id','BLBH']);

        $query->where('id','>',$id)->whereNull("HJNR")->orderBy('id')->chunkById(10000,function($items){
            $last_id = 0;
            collect($items)->map(function ($item) use(&$last_id){
                $this->info("BLXG病历内容同步::{$item->id}");
                $sql = "SELECT NVL(EMR_BL_BLXG.HJNR, '') AS HJNR FROM EMR_BL_BLXG WHERE BLBH = '{$item->BLBH}'";
                $res = DataSyncService::getInstance()->setSql($sql)->getResult();
                if(!empty($res)){
                    $hjnr = $this->blobToStr(data_get($res,'0.HJNR'));
                    EMR_BL_BLXG::query()->where('id',$item->id)->update(['HJNR' => $hjnr]);
                }
                $last_id = $item->id;
            });
            Setting::query()->where('name','hjnr-sync-blxg')->updateOrInsert(['name'=> 'hjnr-sync-blxg','keyword' => $last_id]);
        },"id");

//        while ($query->where('id','>',$id)->count() > 0) {
//            collect($query->where('id','>',$id)->orderBy('id')->limit(1000)->get())->map(function ($item)use(&$id) {
//                $this->info("病历内容同步::{$item->id}");
//                $sql = "SELECT NVL(EMR_BL_BLXG.HJNR, '') AS HJNR FROM EMR_BL_BLXG WHERE BLBH = '{$item->BLBH}'";
//                $res = DataSyncService::getInstance()->setSql($sql)->getResult();
//                if(!empty($res)){
//                    $hjnr = $this->blobToStr(data_get($res,'0.HJNR'));
//                    EMR_BL_BLXG::query()->where('id',$item->id)->update(['HJNR' => $hjnr]);
//                }
//                $id = $item->id;
//            });
//        }

        $this->info('EMR_BL_BLXG同步HJNR数据完成');
    }
}
