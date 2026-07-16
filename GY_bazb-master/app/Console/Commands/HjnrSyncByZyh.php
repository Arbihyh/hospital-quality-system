<?php

namespace App\Console\Commands;

use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Model\PatientInfo;
use App\Services\DataxSync\DataSyncService;
use App\Services\IihinterfaceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class HjnrSyncByZyh extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hjnr-sync-by-zyh {zyh?} {startTime?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'HJNR数据同步（根据住院号）';

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
        $zyh = data_get($this->arguments(), 'zyh');
        $startTime = data_get($this->arguments(), 'startTime');
        $this->_bl01($zyh, $startTime);
    }

    /**
     * 同步BL01数据HJNR_HTML内容（根据住院号）
     * @param string $zyh 住院号
     * @param string $startTime 开始时间
     * @return void
     */
    private function _bl01($zyh, $startTime)
    {
        $startTime = empty($startTime) ? Carbon::now()->subDays(3)->startOfDay()->format('Y-m-d H:i:s') : $startTime;
        $patientInfo = PatientInfo::query()->where('AAC01', '>', $startTime)
            ->when(!empty($zyh), function ($query) use ($zyh) {
                return $query->where('MED_REC_ID', $zyh);
            })
            ->get()->toArray();

        foreach ($patientInfo as $item) {
            $zyh = $item['MED_REC_ID'];
            $this->info("HJNR数据同步（根据住院号）::{$zyh}");
            if (!empty($zyh)) {

                $sql = "SELECT NVL(EMR_BL_BLXG.HJNR, '') AS HJNR,NVL(EMR_BL_BLXG.JLXH, '') AS JLXH,NVL(EMR_BL_BLXG.XGGH, '') AS XGGH,NVL(EMR_BL_BLXG.XGSJ, '') AS XGSJ,EMR_BL_BLXG.BLBH FROM EMR_BL_BLXG WHERE BLBH IN (SELECT BLBH FROM EMR_BL_BL01 WHERE zyh = '{$zyh}')";
                $res = DataSyncService::getInstance()->setSql($sql)->getResult();
                $this->info("HJNR数据同步（根据住院号）::{$zyh} 查询结果::" . json_encode($res));
                if (!empty($res)) {
                    foreach ($res as $row) {
                        $this->info("HJNR数据同步（根据住院号）::{$zyh} 更新数据::" . json_encode($row));
                        $hjnr = $this->blobToStr(data_get($row, 'HJNR'));
                        $blbh = data_get($row, 'BLBH');
                        EMR_BL_BLXG::query()->updateOrInsert(['BLBH' => $blbh], ['HJNR' => $hjnr, 'JLXH' => data_get($row, 'JLXH'), 'XGGH' => data_get($row, 'XGGH'), 'XGSJ' => data_get($row, 'XGSJ')]);
                        $this->info("HJNR数据同步（根据住院号）::{$zyh} 更新完成::" . json_encode($row));
                    }
                }
            }

            $this->info($zyh . '--EMR_BL_BLXG同步HJNR数据完成');
        }
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
}
