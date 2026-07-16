<?php

namespace App\Console\Commands\MysqlDataSyncEs;

use App\Model\User;
use App\Model\UserSearchLog;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Quality2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:quality2';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步quality2主信息数据到Es';

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
        $quality2Service = new ElasticsearchService('quality2');

        $sql = "select DISTINCT a.JZHM,a.JZHM MED_REC_ID,p.AAA28,p.AAC11N,if(p.AAB01='' or p.AAB01=null,'1000-10-10 00:00:00',p.AAB01) AAB01,p.AAC01,p.AAA04,p.AAA40,p.AEM01C,p.AAC04,p.ADA01,p.AAA29,p.AAB06C,p.AAA26C,p.created_at,md.ICD10_NAME,md.ICD10_ID1 from EMR_BL_BL01 as a LEFT JOIN patient_info p on a.JZHM = p.MED_REC_ID left join main_diagnosis as md on p.MED_REC_ID=md.AAA28";
        $data = DB::select($sql);

        dd(count($data));

        return 0;
    }
}
