<?php

namespace App\Console\Commands;

use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLSY;
use App\Model\EMR_BL_BLXG;
use App\Model\SyncRecord;
use App\Services\BlDataFormatService;
use Elasticsearch\Endpoints\Info;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class Bl01v2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:bl01v2 {start?} {end?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        ini_set('default_socket_timeout', 0);
        $star = $this->argument('start');
        $end = $this->argument('end');
        if (empty($star)) {
            //$star = '20220101';
            //前15天
            $star = date('Ymd', strtotime('-15 days'));
        }
        if (empty($end)) {
            $end = date('Ymd', time());
        }
        //        $between = ['2022-04-05 00:00:00','2022-04-05 23:59:59'];
        //        $del = EMR_BL_BL01::query()->whereBetween('ZXSJ',$between)->pluck('BLBH');
        //        EMR_BL_BL01::query()->whereIn('BLBH',$del)->delete();
        //        EMR_BL_BLXG::query()->whereIn('BLBH',$del)->delete();
        $bldf = new BlDataFormatService();
        $username = env('ORACLE_USERNAME', 'zdyh');
        $password = env('ORACLE_PASSWORD', 'zdyh');
        $connection = env('ORACLE_HOST', '172.16.9.8');
        $port = env('ORACLE_PORT', '1521');
        $tns = env('ORACLE_TNS', 'his');
        $con = oci_connect($username, $password,  $connection . ':' . $port . '/' . $tns, 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }

        while (true) {
            if ($star > date('Ymd', time())) {
                echo date("Y-m-d H:i:s", time()) . "完成一轮数据同步\n";
                //$star = '20220101';
                //前15天
                $star = date('Ymd', strtotime('-15 days'));
            }
            $syncRecordData = [
                'name' => 'laravel:bl01v2',
                'start' => $star,
                'end' => $end,
                'count' => 0,
                'created_at' => now(),
            ];

            //$sql = "SELECT * FROM (SELECT ROWNUM r,a.*,to_char(ZXSJ,'yyyy-mm-dd hh24:mi:ss') as ZJ,to_char(CJSJ,'yyyy-mm-dd hh24:mi:ss') as CJ,to_char(WCSJ,'yyyy-mm-dd hh24:mi:ss') as WJ FROM PORTAL55_EMR.V_JMGS_BASY_QBL a) WHERE ZXSJ BETWEEN TO_DATE('2022-12-01 00:00:00', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('2022-12-01 00:00:00', 'yyyy-MM-dd HH24:mi:ss') AND r BETWEEN $start AND $end";
            $sql = "SELECT a.*,to_char(ZXSJ,'yyyy-mm-dd hh24:mi:ss') as ZJ,to_char(CJSJ,'yyyy-mm-dd hh24:mi:ss') as CJ,to_char(WCSJ,'yyyy-mm-dd hh24:mi:ss') as WJ,to_char(XGSJ,'yyyy-mm-dd hh24:mi:ss') as XJ FROM PORTAL55_EMR.V_JMGS_BASY_QBL a WHERE XGSJ BETWEEN TO_DATE('" . $star . "000000', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('" . $star . "235959', 'yyyy-MM-dd HH24:mi:ss')";
            $result = oci_parse($con, $sql);
            oci_execute($result, OCI_DEFAULT);
            var_dump('开始时间：' . $star);
            $data = [];
            while ($row = oci_fetch_assoc($result)) {
                $data[] = $row;
            }
            /* if (empty($data)){
                SyncRecord::query()->insert($syncRecordData);
                break;
            } */
            var_dump('数据条数：' . count($data));
            foreach ($data as $item) {
                //var_dump($item['BLBH'].' - start');
                $result = [
                    'BLBH' => $item['BLBH'] ?? '',
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
                if (!empty($item['BLNR'])) {
                    $text = $item['BLNR']->load();
                    $item['BLNR']->free();
                    $mde = mb_detect_encoding($text, array("ASCII", 'UTF-8', "GB2312", "GBK", 'BIG5'));
                    if ($mde) {
                        $str = mb_convert_encoding($text, 'utf-8', $mde);
                    }
                }
                if (empty($str)) {
                    Log::info("HJNR_is_empty:BLBH=" . $item['BLBH']);
                    continue;
                }
                $temp = [
                    'JLXH' => $item['JLXH'] ?? '',
                    'BLBH' => $item['BLBH'] ?? '',
                    'XGGH' => $item['XGGH'] ?? '',
                    'XGSJ' => $item['XJ'] ?? '',
                    'HJNR' => $str,
                ];
                EMR_BL_BL01::query()->updateOrInsert(['BLBH' => $item['BLBH']], $result);
                EMR_BL_BLXG::query()->updateOrInsert(['BLBH' => $item['BLBH']], $temp);
                $bldf->insertData([], $result["MBLB"], array_merge($result, $temp));

                $sql = "select JLXH,BLBH,SYYS,to_char(SYSJ,'yyyy-mm-dd hh24:mi:ss') as SYSJ,to_char(JLSJ,'yyyy-mm-dd hh24:mi:ss') as JLSJ,QMLX,QMYS from PORTAL55_EMR.EMR_BL_BLSY WHERE BLBH = '".$item['BLBH']."'";
                $result = oci_parse($con, $sql);
                oci_execute($result, OCI_DEFAULT);
                $data1 = [];
                while ($row = oci_fetch_assoc($result)) {
                    $data1[] = $row;
                }

                foreach ($data1 as $item1) {
                    $QMMC = 0;
                    switch ($item1['QMYS']) {
                        //3.患者签名：签名,患者签名2,患者签名4,患者签名6,患者签名7,患者签名9,患者签名0,患者签名3,患者签名5,患者签名1,患者签名8,患者签名10,患者签名11
                        case '患者签名':
                        case '患者签名2':
                        case '患者签名4':
                        case '患者签名6':
                        case '患者签名7':
                        case '患者签名9':
                        case '患者签名0':
                        case '患者签名3':
                        case '患者签名5':
                        case '患者签名1':
                        case '患者签名8':
                        case '患者签名10':
                        case '患者签名11':
                            $QMMC = 3;
                            break;
                        //5.医生签名：滨医_主治签名,滨医_住院签名,住院医师签名,滨医_医师签名,主治医师签名,滨医_副主任签名,滨医_主任签名,滨_医师签名
                        case '滨医_主治签名':
                        case '滨医_住院签名':
                        case '住院医师签名':
                        case '滨医_医师签名':
                        case '主治医师签名':
                        case '滨医_副主任签名':
                        case '滨医_主任签名':
                        case '滨_医师签名':
                            $QMMC = 5;
                            break;
                    }
                    $insertData = [
                        'JLXH' => $item1['JLXH'] ?? '',
                        'BLBH' => $item1['BLBH'] ?? '',
                        'SYYS' => $item1['SYYS'] ?? '',
                        'QMLX' => $item1['QMLX'] ?? '',
                        'QMYS' => $item1['QMYS'] ?? '',
                        'SYSJ' => $item1['SYSJ'] ?? '',
                        'JLSJ' => $item1['JLSJ'] ?? '',
                        'QMMC' => $QMMC,
                        'FG_ACTIVE' => 1,
                    ];

                    EMR_BL_BLSY::query()->updateOrInsert(['JLXH' => $insertData['JLXH']], $insertData);
                }
                //var_dump($item['BLBH'].' - end');
            }
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Ymd', strtotime($star) + 86400);
        }
        return 0;
    }
}
