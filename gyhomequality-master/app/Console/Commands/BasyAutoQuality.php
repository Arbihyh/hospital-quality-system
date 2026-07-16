<?php

namespace App\Console\Commands;

use App\Model\PatientInfo;
use App\Model\Setting;
use Illuminate\Console\Command;
use Pheanstalk\Pheanstalk;
use function GuzzleHttp\json_encode;

class BasyAutoQuality extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'basy:autoQuality';

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
        // 查询是否需要执行
        $content = Setting::query()->where('name','=','home_quality_is_zx')->value('content');
        if (!empty($content)) {
            Setting::query()->updateOrInsert(['name'=>'home_quality_is_zx'],['content'=>0]);

            $setName = 'home_quality_id';
            $lastId = Setting::query()->where('name','=',$setName)->value('content');
            $lastId = !empty($lastId) ? $lastId : 0;

            $updateLastId = '';
            $page = 1;
            $limit = 100;
            while (true) {
                $patientData = PatientInfo::query()
                    ->where('id', '>', $lastId)
                    ->orderBy('id', 'asc')
                    ->paginate($limit,['id','MED_REC_ID'],'page',$page)
                    ->toArray();
                if (empty($patientData['data'])) {
                    break;
                }
                $beanstalkd = Pheanstalk::create(env("BEANSTALKD") ?? 'beanstalkd');
                foreach ($patientData['data'] as $item) {
                    $updateLastId = $item['id'];
                    $beanstalkd->useTube('validate')->put(json_encode(['AAA28' => $item['MED_REC_ID']]));
                }
                $page++;
            }

            if (!empty($updateLastId)) {
                Setting::query()->updateOrInsert(['name'=>$setName],['content'=>$updateLastId]);
            }
        }

        return 0;
    }
}
