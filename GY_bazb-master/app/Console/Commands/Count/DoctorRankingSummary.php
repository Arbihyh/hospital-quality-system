<?php

namespace App\Console\Commands\Count;

use App\Model\RuleWordMap;
use App\Services\EsSaveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpMyAdmin\Server\Select;

class DoctorRankingSummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'count:doctor_ranking_summary {start_time?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '汇总doctor_ranking_summary数据';

    public static $con;

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

        $startTime = $this->argument("start_time");
        DB::statement('TRUNCATE TABLE doctor_ranking_summary;');
        $sql = "INSERT INTO doctor_ranking_summary ( CODE, zyh, score, record_date ) SELECT
            cqd.CODE,
            cqd.ZYH AS zyh,
            SUM( cqd.score ) AS score,
            DATE( pi.AAC01 ) AS record_date 
            FROM
                case_quality_doctor cqd
                INNER JOIN patient_info pi ON cqd.ZYH = pi.MED_REC_ID
                INNER JOIN patient_doctor_info pdi ON pdi.AAA28 = pi.MED_REC_ID
                INNER JOIN ZY_BRRY zb ON zb.ZYH = pi.MED_REC_ID 
            WHERE
                pi.in_hospital = 2 
                AND pi.AAC01 >= ? 
            GROUP BY
                cqd.CODE,
                cqd.ZYH,
                DATE( pi.AAC01 ) 
                ON DUPLICATE KEY UPDATE score =
            VALUES
                ( score ),
                updated_at = CURRENT_TIMESTAMP;";
        DB::select($sql, [$startTime]);
    }

}
