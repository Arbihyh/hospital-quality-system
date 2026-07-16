<?php

namespace App\Console\Commands;

use App\Model\PatientInfo;
use App\Model\Setting;
use App\Services\ErrorValidateService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Pheanstalk\Pheanstalk;
use function GuzzleHttp\json_encode;

class PutTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:putTest {startDate?} {endDate?}';

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
     * @return void
     */
    public function handle()
    {
        Cache::forget('2021_*');
        Cache::forget('2022_*');
        Cache::forget('2023_*');

        $startDate = $this->argument('startDate') ?: '';
        $endDate = $this->argument('endDate') ?: '';
        if (!empty($startDate) && !empty($endDate)) {
            $field = 'AAC01';
            $startTime = $startDate.' 00:00:00';
            $endTime = $endDate.' 23:59:59';
        } else {
            $field = 'created_at';
            $date = Carbon::parse()->addDay(-1)->toDateString();
            $startTime = $date.' 00:00:00';
            $endTime = $date.' 23:59:59';
        }

        $page = 1;
        while (true) {
            $data = PatientInfo::query()
                ->where('hospital_name','=',config('confAdmin.hospital_name'))
                ->whereBetween($field, [$startTime,$endTime])
                ->orderBy('id')
                ->paginate(100,['id','MED_REC_ID'],'page',$page)
                ->toArray();
            if (empty($data['data'])) {
                break;
            }
            $keys = $data['data'];
            $page++;

            $beanstalkd = Pheanstalk::create(env("BEANSTALKD") ?? 'beanstalkd');
            foreach ($keys as $item) {
                echo $item['MED_REC_ID']."\n";
                $beanstalkd->useTube('validate')->put(json_encode(['AAA28' => $item['MED_REC_ID']]));
            }

        }

        $this->info("处理完成");
    }
}
