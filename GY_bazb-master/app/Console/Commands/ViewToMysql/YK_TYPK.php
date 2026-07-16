<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use Illuminate\Console\Command;

class YK_TYPK extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:yk_typk {start?} {end?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步药品库';

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

        $con = oci_connect('zdyh', 'zdyh', '172.16.9.8:1521/his', "UTF8");
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
                'name' => 'laravel:yk_typk',
                'start' => $star,
'end' => $end,
                'count' => 0,
            ];

            $sql = "SELECT ZXDW,ZXCD,ZXBZ,ZSSF,ZLYP,ZLJFZYW,ZLFY,ZJPB,ZGZFBL,ZGYBBM,ZFPB,ZFBL,ZDYP,ZDJL,ZDLC,ZBLB,YYBZ,YWMC,YPZC,YPXZ,YPXQ,YPXH,YPSX,YPQC,YPMC,YPJL,YPGG,YPDW,YPDM,YPDC,YPBH,YLXZ,YKZF,YJTXFS,YFZF,YFGG,YFGC,YFDW,YFDC,YFBZ,YDYSY,YCYL,YCJL,YBFL,YBBZXX,YBBZ,YBBXBL,YBBM,to_char(XZSJ,'yyyy-mm-dd hh24:mi:ss') as XZSJ,XTSB,XKYBBM,TYPE,TYMC,TY5,TY4,TY3,TY2,TY1,TSYY,TSJD,TPN,TPLYP,TAYBBM,SPMC,SFXZ,SDLCG4,SDLCG3,SDLCG2,SDLCG1,SDLCG,QZCL,QTDM,QBZF,PYDM,PSPB,PCBM,MRXL,KWBM,KSSFL,KSDJ,KSBZ,KJYFYY,KJSPBZ,KJJB,JZYY,JMYBBM,JMQZCL,JMPYLB,JLDW,JGID,JBYWBZ,HZXZ,GYFF,GWYP,GGQC,GCSL,FZYYLB,FZYY,FYFS,DZBZ,DYFS,DWQC,DLCGYP4,DLCGYP3,DLCGYP2,DLCGYP1,DLCGYP,DJYBZ,DJJB,DDJL,DDDZ,DCSL,CYYW,CYYPBZ,CWBM,CFYP,CFLX,BZXX,BXLC,BFZF,BFGG,BFGC,BFDW,BFDC,BFBZ,ATCM,ABC FROM YK_TYPK WHERE XZSJ BETWEEN TO_DATE('{$star}', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('{$end}', 'yyyy-MM-dd HH24:mi:ss')";
            $result = oci_parse($con, $sql);
            oci_execute($result, OCI_DEFAULT);
            $data = [];
            while ($row = oci_fetch_assoc($result)) {
                $data[] = $row;
            }
            if (empty($data)) {
                SyncRecord::query()->insert($syncRecordData);
                exit;
            }
            foreach ($data as $item) {
                $insertData = [
                    'ZXDW' => $item['ZXDW'],
                    'ZXCD' => $item['ZXCD'],
                    'ZXBZ' => $item['ZXBZ'],
                    'ZSSF' => $item['ZSSF'],
                    'ZLYP' => $item['ZLYP'],
                    'ZLJFZYW' => $item['ZLJFZYW'],
                    'ZLFY' => $item['ZLFY'],
                    'ZJPB' => $item['ZJPB'],
                    'ZGZFBL' => $item['ZGZFBL'],
                    'ZGYBBM' => $item['ZGYBBM'],
                    'ZFPB' => $item['ZFPB'],
                    'ZFBL' => $item['ZFBL'],
                    'ZDYP' => $item['ZDYP'],
                    'ZDJL' => $item['ZDJL'],
                    'ZDLC' => $item['ZDLC'],
                    'ZBLB' => $item['ZBLB'],
                    'YYBZ' => $item['YYBZ'],
                    'YWMC' => $item['YWMC'],
                    'YPZC' => $item['YPZC'],
                    'YPXZ' => $item['YPXZ'],
                    'YPXQ' => $item['YPXQ'],
                    'YPXH' => $item['YPXH'],
                    'YPSX' => $item['YPSX'],
                    'YPQC' => $item['YPQC'],
                    'YPMC' => $item['YPMC'],
                    'YPJL' => $item['YPJL'],
                    'YPGG' => $item['YPGG'],
                    'YPDW' => $item['YPDW'],
                    'YPDM' => $item['YPDM'],
                    'YPDC' => $item['YPDC'],
                    'YPBH' => $item['YPBH'],
                    'YLXZ' => $item['YLXZ'],
                    'YKZF' => $item['YKZF'],
                    'YJTXFS' => $item['YJTXFS'],
                    'YFZF' => $item['YFZF'],
                    'YFGG' => $item['YFGG'],
                    'YFGC' => $item['YFGC'],
                    'YFDW' => $item['YFDW'],
                    'YFDC' => $item['YFDC'],
                    'YFBZ' => $item['YFBZ'],
                    'YDYSY' => $item['YDYSY'],
                    'YCYL' => $item['YCYL'],
                    'YCJL' => $item['YCJL'],
                    'YBFL' => $item['YBFL'],
                    'YBBZXX' => $item['YBBZXX'],
                    'YBBZ' => $item['YBBZ'],
                    'YBBXBL' => $item['YBBXBL'],
                    'YBBM' => $item['YBBM'],
                    'XZSJ' => $item['XZSJ'],
                    'XTSB' => $item['XTSB'],
                    'XKYBBM' => $item['XKYBBM'],
                    'WBDM' => $item['WBDM'],
                    'TYPE' => $item['TYPE'],
                    'TYMC' => $item['TYMC'],
                    'TY5' => $item['TY5'],
                    'TY4' => $item['TY4'],
                    'TY3' => $item['TY3'],
                    'TY2' => $item['TY2'],
                    'TY1' => $item['TY1'],
                    'TSYY' => $item['TSYY'],
                    'TSJD' => $item['TSJD'],
                    'TPN' => $item['TPN'],
                    'TPLYP' => $item['TPLYP'],
                    'TAYBBM' => $item['TAYBBM'],
                    'SPMC' => $item['SPMC'],
                    'SFXZ' => $item['SFXZ'],
                    'SDLCG4' => $item['SDLCG4'],
                    'SDLCG3' => $item['SDLCG3'],
                    'SDLCG2' => $item['SDLCG2'],
                    'SDLCG1' => $item['SDLCG1'],
                    'SDLCG' => $item['SDLCG'],
                    'QZCL' => $item['QZCL'],
                    'QTDM' => $item['QTDM'],
                    'QBZF' => $item['QBZF'],
                    'PYDM' => $item['PYDM'],
                    'PSPB' => $item['PSPB'],
                    'PCBM' => $item['PCBM'],
                    'MRXL' => $item['MRXL'],
                    'MESS' => $item['MESS'],
                    'KWBM' => $item['KWBM'],
                    'KSSFL' => $item['KSSFL'],
                    'KSDJ' => $item['KSDJ'],
                    'KSBZ' => $item['KSBZ'],
                    'KJYFYY' => $item['KJYFYY'],
                    'KJSPBZ' => $item['KJSPBZ'],
                    'KJJB' => $item['KJJB'],
                    'JZYY' => $item['JZYY'],
                    'JXDM' => $item['JXDM'],
                    'JMYBBM' => $item['JMYBBM'],
                    'JMQZCL' => $item['JMQZCL'],
                    'JMPYLB' => $item['JMPYLB'],
                    'JLDW' => $item['JLDW'],
                    'JGID' => $item['JGID'],
                    'JBYWBZ' => $item['JBYWBZ'],
                    'HZXZ' => $item['HZXZ'],
                    'GYFF' => $item['GYFF'],
                    'GWYP' => $item['GWYP'],
                    'GGQC' => $item['GGQC'],
                    'GCSL' => $item['GCSL'],
                    'FZYYLB' => $item['FZYYLB'],
                    'FZYY' => $item['FZYY'],
                    'FYFS' => $item['FYFS'],
                    'DZBZ' => $item['DZBZ'],
                    'DYFS' => $item['DYFS'],
                    'DWQC' => $item['DWQC'],
                    'DLCGYP4' => $item['DLCGYP4'],
                    'DLCGYP3' => $item['DLCGYP3'],
                    'DLCGYP2' => $item['DLCGYP2'],
                    'DLCGYP1' => $item['DLCGYP1'],
                    'DLCGYP' => $item['DLCGYP'],
                    'DJYBZ' => $item['DJYBZ'],
                    'DJJB' => $item['DJJB'],
                    'DDJL' => $item['DDJL'],
                    'DDDZ' => $item['DDDZ'],
                    'DCSL' => $item['DCSL'],
                    'CYYW' => $item['CYYW'],
                    'CYYPBZ' => $item['CYYPBZ'],
                    'CWBM' => $item['CWBM'],
                    'CFYP' => $item['CFYP'],
                    'CFLX' => $item['CFLX'],
                    'BZXX' => $item['BZXX'],
                    'BXLC' => $item['BXLC'],
                    'BFZF' => $item['BFZF'],
                    'BFGG' => $item['BFGG'],
                    'BFGC' => $item['BFGC'],
                    'BFDW' => $item['BFDW'],
                    'BFDC' => $item['BFDC'],
                    'BFBZ' => $item['BFBZ'],
                    'ATCM' => $item['ATCM'],
                    'ABC' => $item['ABC'],
                ];

                \App\Model\YK_TYPK::query()->updateOrInsert(['SQXH' => $insertData['SQXH']], $insertData);
            }

            //记录日志
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Y-m-d', strtotime($star) + 86400);
        }
        return 0;
    }
}
