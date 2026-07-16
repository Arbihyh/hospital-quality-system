<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\QualityRule;
use App\Http\Controllers\Api\CaseHistory\Terminal\IndexController;
use App\Model\CaseQuality;
use App\Model\CaseRule;
use App\Model\Department;
use App\Model\EMR_BL_BL01;
use App\Model\MainDiagnosis;
use App\Model\MainOperation;
use App\Model\PatientDoctorInfo;
use App\Model\PatientInfo;
use App\Model\PatientInfoDiagnosisV2;
use App\Model\PatientInfoOperationV2;
use App\Model\PatientInfoV2;
use App\Model\RuleSetting;
use App\Model\Staff;
use App\Services\OperationService;
use App\Services\RadioService;
use App\Services\ToolsService;
use App\Services\UserService;
use Illuminate\Console\Command;
use App\Services\CaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use mysql_xdevapi\Exception;
use Pheanstalk\Pheanstalk;
use function GuzzleHttp\json_encode;
use App\Services\BcService;

class Test1 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'test1';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 定时生成执行相关诊断信息的统计信息';

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
     *
     */
    public function handle()
    {
        

    }

}
