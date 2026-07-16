<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use Illuminate\Console\Command;

class MZJL extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:mzjl {start?} {end?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步 手麻';

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
        ini_set('default_socket_timeout', 0);

        $star = $this->argument('start');
        $star = empty($star) ? date('Y-m-d', strtotime('-1 day')) : $star;
        $end = $this->argument('end');
        $end = empty($end) ? date('Y-m-d') : $end;
        $con = oci_connect('ane', 'ane', '172.16.2.46:1521/samis', "UTF8");
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        while (true) {
            echo $star . PHP_EOL;
            if ($star > $end) {
                break;
            }
            $syncRecordData = [
                'name' => 'laravel:mzjl',
                'start' => $star,
                'end' => $end,
                'count' => 0,
                'created_at' => now(),
            ];

            $sql = "SELECT DCID,PATIENTID,PATIENTTYPE,VISITID,EFFECTIVEFLAG,AUTHORORGANIZATION,AUTHORORGANIZATIONNAME,IDCARD,CLINICID,HOSPIZATIONID,to_char(VISITDATETIME,'yyyy-mm-dd hh24:mi:ss') as VISITDATETIME,REQUESTNOTEID,NAME,SEX,AGE,MONTHAGE,HEIGHT,WEIGHT,ABOBLOODCODE,RHBLOODCODE,DEPTCODE,DEPTNAME,WARDAREACODE,WARDAREANAME,WARDAREAROOM,SICKBEDID,to_char(REQUESTDATETIME,'yyyy-mm-dd hh24:mi:ss') as REQUESTDATETIME,to_char(REQUESTDATETIME1,'yyyy-mm-dd hh24:mi:ss') as REQUESTDATETIME1,to_char(REQUESTDATETIME2,'yyyy-mm-dd hh24:mi:ss') as REQUESTDATETIME2,OPERATIONDEPTCODE,OPERATIONCODE,PREOPERATIONNAME,OPERATIONNAME,OPTPATIENTTYPE,ISRETURNOPERATION,HAVEPREOPERATIVEDISCUSS,PREOPERATIVENOTES,PREOPERATIVEDIAGNOSECODE,PREOPERATIVEDIAGNOSENAME,POSTOPERATIVEDIAGNOSECODE,POSTOPERATIVEDIAGNOSENAME,DIAGCOINPREOPERATIVEVSPOST,OPERATIONROOMNO,OPERATIONROOMTABLENO,to_char(INOPTROOMTIME,'yyyy-mm-dd hh24:mi:ss') as INOPTROOMTIME,to_char(OUTOPTROOMTIME,'yyyy-mm-dd hh24:mi:ss') as OUTOPTROOMTIME,to_char(OPERATESTARTTIME,'yyyy-mm-dd hh24:mi:ss') as OPERATESTARTTIME,OPERATEENDTIME,OPERATOR,FIRSTASSISTANT,SECONDASSISTANT,THIRDASSISTANT,FIRSTINSTRUMENTNURSE,SECONDINSTRUMENTNURSE,THIRDINSTRUMENTNURSE,FIRSTCIRCULATINGNURSE,SECONDCIRCULATINGNURSE,THIRDCIRCULATINGNURSE,MEDICATEBEFOREANESTHESIA,ASALEVEL,ANESTHESIAWAYCODE,ANESTHESIAWAYNAME,TRACHEATUBETYPE,ANESTHESIABODYPOSITION,ANAESTHETIST,FIRSTANAESTHETISTASSI,SECONDANAESTHETISTASSI,ANESTHESIASTARTTIME,to_char(ANESTHESIAENDTIME,'yyyy-mm-dd hh24:mi:ss') as ANESTHESIAENDTIME,ANAESTHETICNAME,BREATHTYPECODE,ANESTHESIAEFFECT,ANESTHESIADESCRIPTION FROM anesthesiarecord";
            $result = oci_parse($con, $sql);
            oci_execute($result, OCI_DEFAULT);
            $data = [];
            while ($row = oci_fetch_assoc($result)) {
                $data[] = $row;
            }
            if (empty($data)) {
                exit;
            }
            foreach ($data as $item) {
                $insertData = [
                    'DCID' => $item['DCID'],
                    'PATIENTID' => $item['PATIENTID'],
                    'PATIENTTYPE' => $item['PATIENTTYPE'],
                    'VISITID' => $item['VISITID'],
                    'EFFECTIVEFLAG' => $item['EFFECTIVEFLAG'],
                    'AUTHORORGANIZATION' => $item['AUTHORORGANIZATION'],
                    'AUTHORORGANIZATIONNAME' => $item['AUTHORORGANIZATIONNAME'],
                    'IDCARD' => $item['IDCARD'],
                    'CLINICID' => $item['CLINICID'],
                    'HOSPIZATIONID' => $item['HOSPIZATIONID'],
                    'VISITDATETIME' => $item['VISITDATETIME'],
                    'REQUESTNOTEID' => $item['REQUESTNOTEID'],
                    'NAME' => $item['NAME'],
                    'SEX' => $item['SEX'],
                    'AGE' => $item['AGE'],
                    'MONTHAGE' => $item['MONTHAGE'],
                    'HEIGHT' => $item['HEIGHT'],
                    'WEIGHT' => $item['WEIGHT'],
                    'ABOBLOODCODE' => $item['ABOBLOODCODE'],
                    'RHBLOODCODE' => $item['RHBLOODCODE'],
                    'DEPTCODE' => $item['DEPTCODE'],
                    'DEPTNAME' => $item['DEPTNAME'],
                    'WARDAREACODE' => $item['WARDAREACODE'],
                    'WARDAREANAME' => $item['WARDAREANAME'],
                    'WARDAREAROOM' => $item['WARDAREAROOM'],
                    'SICKBEDID' => $item['SICKBEDID'],
                    'REQUESTDATETIME' => $item['REQUESTDATETIME'],
                    'REQUESTDATETIME1' => $item['REQUESTDATETIME1'],
                    'REQUESTDATETIME2' => $item['REQUESTDATETIME2'],
                    'OPERATIONDEPTCODE' => $item['OPERATIONDEPTCODE'],
                    'OPERATIONCODE' => $item['OPERATIONCODE'],
                    'PREOPERATIONNAME' => $item['PREOPERATIONNAME'],
                    'OPERATIONNAME' => $item['OPERATIONNAME'],
                    'OPTPATIENTTYPE' => $item['OPTPATIENTTYPE'],
                    'ISRETURNOPERATION' => $item['ISRETURNOPERATION'],
                    'HAVEPREOPERATIVEDISCUSS' => $item['HAVEPREOPERATIVEDISCUSS'],
                    'PREOPERATIVENOTES' => $item['PREOPERATIVENOTES'],
                    'PREOPERATIVEDIAGNOSECODE' => $item['PREOPERATIVEDIAGNOSECODE'],
                    'PREOPERATIVEDIAGNOSENAME' => $item['PREOPERATIVEDIAGNOSENAME'],
                    'POSTOPERATIVEDIAGNOSECODE' => $item['POSTOPERATIVEDIAGNOSECODE'],
                    'POSTOPERATIVEDIAGNOSENAME' => $item['POSTOPERATIVEDIAGNOSENAME'],
                    'DIAGCOINPREOPERATIVEVSPOST' => $item['DIAGCOINPREOPERATIVEVSPOST'],
                    'OPERATIONROOMNO' => $item['OPERATIONROOMNO'],
                    'OPERATIONROOMTABLENO' => $item['OPERATIONROOMTABLENO'],
                    'INOPTROOMTIME' => $item['INOPTROOMTIME'],
                    'OUTOPTROOMTIME' => $item['OUTOPTROOMTIME'],
                    'OPERATESTARTTIME' => $item['OPERATESTARTTIME'],
                    'OPERATEENDTIME' => $item['OPERATEENDTIME'],
                    'OPERATOR' => $item['OPERATOR'],
                    'FIRSTASSISTANT' => $item['FIRSTASSISTANT'],
                    'SECONDASSISTANT' => $item['SECONDASSISTANT'],
                    'THIRDASSISTANT' => $item['THIRDASSISTANT'],
                    'FIRSTINSTRUMENTNURSE' => $item['FIRSTINSTRUMENTNURSE'],
                    'SECONDINSTRUMENTNURSE' => $item['SECONDINSTRUMENTNURSE'],
                    'THIRDINSTRUMENTNURSE' => $item['THIRDINSTRUMENTNURSE'],
                    'FIRSTCIRCULATINGNURSE' => $item['FIRSTCIRCULATINGNURSE'],
                    'SECONDCIRCULATINGNURSE' => $item['SECONDCIRCULATINGNURSE'],
                    'THIRDCIRCULATINGNURSE' => $item['THIRDCIRCULATINGNURSE'],
                    'MEDICATEBEFOREANESTHESIA' => $item['MEDICATEBEFOREANESTHESIA'],
                    'ASALEVEL' => $item['ASALEVEL'],
                    'ANESTHESIAWAYCODE' => $item['ANESTHESIAWAYCODE'],
                    'ANESTHESIAWAYNAME' => $item['ANESTHESIAWAYNAME'],
                    'TRACHEATUBETYPE' => $item['TRACHEATUBETYPE'],
                    'ANESTHESIABODYPOSITION' => $item['ANESTHESIABODYPOSITION'],
                    'ANAESTHETIST' => $item['ANAESTHETIST'],
                    'FIRSTANAESTHETISTASSI' => $item['FIRSTANAESTHETISTASSI'],
                    'SECONDANAESTHETISTASSI' => $item['SECONDANAESTHETISTASSI'],
                    'ANESTHESIASTARTTIME' => $item['ANESTHESIASTARTTIME'],
                    'ANESTHESIAENDTIME' => $item['ANESTHESIAENDTIME'],
                    'ANAESTHETICNAME' => $item['ANAESTHETICNAME'],
                    'BREATHTYPECODE' => $item['BREATHTYPECODE'],
                    'ANESTHESIAEFFECT' => $item['ANESTHESIAEFFECT'],
                    'ANESTHESIADESCRIPTION' => $item['ANESTHESIADESCRIPTION'],
                ];

                \App\Model\Mzjl::query()->updateOrInsert(['ZYH' => $insertData['ZYH']], $insertData);
            }

            //记录日志
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Y-m-d', strtotime($star) + 86400);
        }
        return 0;
    }
}
