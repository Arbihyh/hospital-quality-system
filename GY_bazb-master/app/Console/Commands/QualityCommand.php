<?php

namespace App\Console\Commands;

use App\Model\PatientInfo;
use Illuminate\Console\Command;
use App\Services\CaseService;

class QualityCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:quality {start?} {end?} {zyh?} {is_sz?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 病例质控信息';

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
        $startTime = $this->argument('start');
        $startTime = $startTime ?? '';
        $endTime = $this->argument('end');
        $endTime = $endTime ?? '';
        $this->info('病例内涵质控规则 - 开始处理 - '.date("Y-m-d H:i:s"));
        $isSz = $this->argument('is_sz');
        $isSz = $isSz ?? 0;
        $zyh = $this->argument('zyh');
        $zyh = $zyh ?? '';
        try {
            if (empty($startTime)) {
                $startTime = date("Y-m-d H:i:s", time() - 15 * 24 * 3600);
            } else {
                $startTime .= ' 00:00:00';
            }
            if (empty($endTime)) {
                $endTime = date("Y-m-d H:i:s", time());
            } else {
                $endTime .= ' 23:59:59';
            }
            $this->info('开始处理时间：'.$startTime.'，结束处理时间：'.$endTime);
            $caseService = new CaseService();
            $patientInfo = PatientInfo::query()->whereBetween('AAC01', [$startTime, $endTime])->orderBy('AAC01', 'desc')->get(['MED_REC_ID','AAC01'])->toArray();
            foreach ($patientInfo as $info) {
                $zyh = $info['MED_REC_ID'];
                var_dump('住院号：'.$zyh.'，出院时间：'.$info['AAC01']);
                try {
                    $caseService->qualityContrlV2($startTime, $endTime, [$zyh], 99);
                } catch (\Throwable $e) {
                    $this->info("command quality 错误:code:".$e->getFile().':'.$e->getCode().'；line:'.$e->getLine().'；错误信息：'.$e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            $this->info("command quality 错误:code:".$e->getFile().':'.$e->getCode().'；line:'.$e->getLine().'；错误信息：'.$e->getMessage());
        }

        $this->info('病例内涵质控规则 - 处理结束 - '.date("Y-m-d H:i:s"));
    }
}
