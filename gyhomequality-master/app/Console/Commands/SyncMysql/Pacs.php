<?php

namespace App\Console\Commands\SyncMysql;

use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use App\Model\FeeDetailed;
use App\Model\Icu;
use App\Model\MainDiagnosis;
use App\Model\MainOperation;
use App\Model\OtherDiagnosis;
use App\Model\PatientAdd;
use App\Model\PatientAddressInfo;
use App\Model\PatientContactsInfo;
use App\Model\PatientCostInfo;
use App\Model\PatientDoctorInfo;
use App\Model\PatientHospitalInfo;
use App\Model\PatientInfo;
use App\Model\PatientMedicalInfo;
use App\Model\PatientOtherInfo;
use App\Model\PatientWorkInfo;
use App\Model\SecondaryOperation;
use App\Model\SSSQ;
use App\Model\SyncRecord;
use App\Model\YS_ZY_HZYJ;
use App\Model\Yzb;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class Pacs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:pacs {startTime?} {endTime?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command pacs';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public static $con;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {


        $server = '172.16.2.24';
        $username = 'qyyl';
        $password = '1234';
        $database = 'AnyImage ';
        $conn = sqlsrv_connect($server, array('UID' => $username, 'PWD' => $password, 'Database' => $database));


        if ($conn) {
            echo "连接成功";


            $sql = "SELECT * FROM sys_user";
            $res = sqlsrv_query($conn, $sql);
            if ($res === false) {
                die(print_r(sqlsrv_errors(), true));
            }
            while ($row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)) {
                echo "<p>ID: " . $row['user_id'] . ", user_name: " . $row['user_name'] . ", update_time: " . $row['update_time'] . "</p>";
            }


        } else {
            echo "连接失败";
        }


    }

}
