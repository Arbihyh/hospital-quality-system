<?php

namespace App\Console\Commands\AutomatedHandle;

use App\Model\Implants;
use App\Model\PatientInfo;
use App\Model\Setting;
use App\Model\XJPYZD;
use App\Services\ZbAutoAllService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PatientInfoTarget extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zb:autoAll {startTime?} {endTime?}';

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
        $startTime = $this->argument('startTime') ?: '';
        $endTime = $this->argument('endTime') ?: '';
        if ($startTime && $endTime) {
            $field = 'AAC01';
            $startTime = $startTime.' 00:00:00';
            $endTime = $endTime.' 23:59:59';
        } else {
            $field = 'created_at';
            $startTime = Carbon::parse()->addDay(-5)->toDateString().' 00:00:00';
            $endTime = Carbon::parse()->addDay(-1)->toDateString().' 23:59:59';
        }

        // 查询细菌培养字典
        $xjpyFyzdList = XJPYZD::query()->where('mc', '!=', '')->distinct()->pluck('MC')->toArray();

        // 获取所有植入物信息
        $implantsList = Implants::query()->pluck('name','manufactor')->toArray();

        $zbAutoAll = new ZbAutoAllService();

        $page = 1;
        while (true) {
            // 所有符合条件的病例信息
            $data = PatientInfo::query()
                ->where('hospital_name','=',config('confAdmin.hospital_name'))
                ->whereBetween($field,[$startTime,$endTime])
                ->orderBy('AAC01')
                ->paginate(500, ['*'], 'page', $page)
                ->toArray();
            if (empty($data['data'])) {
                break;
            }
            $page++;

            foreach ($data['data'] as $value) {
                $zbAutoAll->zbAutoAll($value,$xjpyFyzdList,$implantsList);
            }
        }

        $this->info('指标V1处理完毕');
    }
}
