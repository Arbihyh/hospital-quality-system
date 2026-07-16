<?php

namespace App\Console\Commands\ViewToMysql;

use App\Model\SyncRecord;
use Illuminate\Console\Command;

class MS_CF02 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:ms_cf02 {start} {end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步 门诊处方明细';

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
                'name' => 'laravel:ms_cf02',
                'start' => $star,
                'end' => $end,
                'count' => 0,
                'created_at' => now(),
            ];

            $ms_cf02 = \App\Model\MS_CF02::query()->orderByDesc('SBXH')->first();
            $sql = "SELECT SBXH,CFSB,YPXH,YPCD,XMLX,CFTS,YPSL,YPDJ,HJJE,YPZS,YCSL,FYGB,ZFBL,GYTJ,YPYF,YPZH,YFGG,YFDW,YFBZ,SJYL,PSPB,YYTS,YCSL2,XSSL,MRCS,CFBZ,YCJL,PSJG,PLXH,SYBZ,SL,CSBZ,JSHID,SQYS,SYLY,YQSY,CLBZ,NWARN,JGID,ZTMC,SFJG,SFJY,SFYJ,YBZFBL,SFGH,TYPH,to_char(BSSJ,'yyyy-mm-dd hh24:mi:ss') as BSSJ,BSSL,BSPB FROM PORTAL_HIS.MS_CF02 WHERE SBXH >{$ms_cf02->SBXH} ORDER BY SBXH";
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
                    'SBXH' => $item['SBXH'],
                    'CFSB' => $item['CFSB'],
                    'YPXH' => $item['YPXH'],
                    'YPCD' => $item['YPCD'],
                    'XMLX' => $item['XMLX'],
                    'CFTS' => $item['CFTS'],
                    'YPSL' => $item['YPSL'],
                    'YPDJ' => $item['YPDJ'],
                    'HJJE' => $item['HJJE'],
                    'YPZS' => $item['YPZS'],
                    'YCSL' => $item['YCSL'],
                    'FYGB' => $item['FYGB'],
                    'ZFBL' => $item['ZFBL'],
                    'GYTJ' => $item['GYTJ'],
                    'YPYF' => $item['YPYF'],
                    'YPZH' => $item['YPZH'],
                    'YFGG' => $item['YFGG'],
                    'YFDW' => $item['YFDW'],
                    'YFBZ' => $item['YFBZ'],
                    'SJYL' => $item['SJYL'],
                    'PSPB' => $item['PSPB'],
                    'YYTS' => $item['YYTS'],
                    'YCSL2' => $item['YCSL2'],
                    'XSSL' => $item['XSSL'],
                    'MRCS' => $item['MRCS'],
                    'CFBZ' => $item['CFBZ'],
                    'YCJL' => $item['YCJL'],
                    'PSJG' => $item['PSJG'],
                    'PLXH' => $item['PLXH'],
                    'SYBZ' => $item['SYBZ'],
                    'SL' => $item['SL'],
                    'CSBZ' => $item['CSBZ'],
                    'JSHID' => $item['JSHID'],
                    'SQYS' => $item['SQYS'],
                    'SYLY' => $item['SYLY'],
                    'YQSY' => $item['YQSY'],
                    'CLBZ' => $item['CLBZ'],
                    'NWARN' => $item['NWARN'],
                    'JGID' => $item['JGID'],
                    'ZTMC' => $item['ZTMC'],
                    'SFJG' => $item['SFJG'],
                    'SFJY' => $item['SFJY'],
                    'SFYJ' => $item['SFYJ'],
                    'YBZFBL' => $item['YBZFBL'],
                    'SFGH' => $item['SFGH'],
                    'TYPH' => $item['TYPH'],
                    'BSSJ' => $item['BSSJ'],
                    'BSSL' => $item['BSSL'],
                    'BSPB' => $item['BSPB'],
                ];

                \App\Model\MS_CF02::query()->updateOrInsert(['SBXH' => $insertData['SBXH']], $insertData);
            }

            //记录日志
            $syncRecordData['count'] = count($data);
            SyncRecord::query()->insert($syncRecordData);
            $star = date('Y-m-d', strtotime($star) + 86400);
        }
        return 0;
    }
}
