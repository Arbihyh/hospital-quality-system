<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BL01_back;
use App\Model\EMR_BL_BLXG;
use App\Model\EMR_BL_BLXG_back;
use Illuminate\Console\Command;

class MT_MEDICAL_RECORD_FIRST_DIS extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:mt_medical_record_first_pat {start} {end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '视图增量同步到mysql';

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
        $end = $this->argument('end');
        while (true){
            if ($star > $end) {
                break;
            }

            $con = oci_connect('zdyh', 'emr#2023', '172.16.9.8:1521/ODS', 'UTF8');
            if (!$con) {
                $e = oci_error();
                print_r(htmlentities($e['message'])) . "\n";
                die;
            }

            $sql = "SELECT a.*,to_char (SUBMISSION_TIME,'yyyy-mm-dd hh24:mi:ss') AS SUBMISSION_TIME,to_char (SAVE_TIME,'yyyy-mm-dd hh24:mi:ss') AS SAVE_TIME,to_char (DISCHARGE_TIME,'yyyy-mm-dd hh24:mi:ss') AS DISCHARGE_TIME,to_char (ADMISSION_TIME,'yyyy-mm-dd hh24:mi:ss') AS ADMISSION_TIME FROM PORTAL_HIS.MT_MEDICAL_RECORD_FIRST_PAT a WHERE (SAVE_TIME BETWEEN TO_DATE ('".$star." 00:00:00','yyyy-MM-dd HH24:mi:ss') AND TO_DATE ('".$star." 23:59:59','yyyy-MM-dd HH24:mi:ss')) OR (SUBMISSION_TIME BETWEEN TO_DATE ('".$star." 00:00:00','yyyy-MM-dd HH24:mi:ss') AND TO_DATE ('".$star." 23:59:59','yyyy-MM-dd HH24:mi:ss'))";
            $result = oci_parse($con, $sql);
            oci_execute($result,OCI_DEFAULT);
            $data = [];
            while ($row = oci_fetch_assoc($result)) {
                $data[] = $row;
            }
            if (empty($data)){
                break;
            }
            $ms_brda = [];
            foreach ($data as $item){
                $ms_brda = [
                    'BRID'=>$item['patient_guid'],
//                    'MZHM'=>$item['patient_guid'],
                    'BRXM'=>$item['patient_name'],
                    'FYZH'=>$item['patient_id_card'],
                    'SFZH'=>$item['patient_id_card'],
                    'BRXZ'=>$item['patient_id_card'],
                    'BRXB'=>$item['patient_gender'],
                ];

                $result = [
                    'BLBH' => $item['patient_guid'] ?? '',
                    'JZHM' => $item['ZYH'] ?? '',
                    'BRBH' => $item['BRBH'] ?? '',
                    'BLLX' => $item['BLLX'] ?? '',
                    'BLLB' => $item['BLLB'] ?? '',
                    'BLMC' => $item['BLMC'] ?? '',
                    'BLZM' => $item['BLZM'] ?? '',
                    'DLLB' => $item['DLLB'] ?? '',
                    'DLJ' => $item['DLJ'] ?? '',
                    'MBLB' => $item['MBLB'] ?? '',
                    'MBBH' => $item['MBBH'] ?? '',
                    'ZXSJ' => $item['ZJ'] ?? '',
                    'CJSJ' => $item['CJ'] ?? '',
                    'WCSJ' => $item['WJ'] ?? '',
                    'SXYS' => $item['SXYS'] ?? '',
                    'BRKS' => $item['BRKS'] ?? '',
                    'CJKS' => $item['CJKS'] ?? '',
                    'BLZT' => $item['BLZT'] ?? '',
                    'BRXM' => $item['BRXM'] ?? '',
                    'BRZD' => $item['BRZD'] ?? '',
                    'SSYS' => $item['SSYS'] ?? '',
                    'SYBZ' => $item['SYBZ'] ?? '',
                    'BZMBBH' => $item['BZMBBH'] ?? '',
                    'BLYM' => $item['BLYM'] ?? '',
                    'YMJL' => $item['YMJL'] ?? '',
                    'RYZDSJ' => $item['RYZDSJ'] ?? '',
                    'PTID' => $item['PTID'] ?? '',
                    'BLZSTJ' => $item['BLZSTJ'] ?? '',
                    'JGID' => $item['JGID'] ?? '',
                    'SQDH' => $item['SQDH'] ?? '',
                    'ZDMC' => $item['ZDMC'] ?? '',
                    'ZDLX' => $item['ZDLX'] ?? '',
                    'CXPX' => $item['CXPX'] ?? '',
                    'SBBZ' => $item['SBBZ'] ?? '',
                    'WZZT' => $item['WZZT'] ?? ''
                ];
                $str = '';
                if(!empty($item['HJNR'])){
                    $text = $item['HJNR']->load();
                    $item['HJNR']->free();
                    $mde = mb_detect_encoding($text, array("ASCII",'UTF-8',"GB2312","GBK",'BIG5'));
                    if ($mde == 'UTF-8'){
                        $str = mb_convert_encoding($text,'utf-8',$mde);
                    }
                }
                $temp = [
                    'JLXH' => $item['JLXH'] ?? '',
                    'BLBH' => $item['BLBH'] ?? '',
                    'XGGH' => $item['XGGH'] ?? '',
                    'XGSJ' => $item['XJ'] ?? '',
                    'HJNR' => $str,
                ];
                EMR_BL_BLXG_back::query()->updateOrInsert(['BLBH'=>$temp['BLBH']],$temp);
                EMR_BL_BL01_back::query()->updateOrInsert(['JZHM'=>$result['JZHM'],'BLBH'=>$result['BLBH']],$result);
            }
            $star = date('Y-m-d',strtotime($star)+86400);
        }
        return 0;
    }
}
