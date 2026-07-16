<?php

namespace App\Console\Commands;

use App\Model\PatientAdd;
use App\Model\PatientCostInfo;
use App\Model\PatientInfo;
use Illuminate\Console\Command;

class AddDefectData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:defect';

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
        $offset = 1;
        while (true) {
            $page = ($offset - 1) * 20;
            $list = PatientInfo::query()
                ->offset($page)
                ->limit(20)
                ->orderBy('MED_REC_ID', 'desc')
                ->pluck('MED_REC_ID');
            if ($list) {
                $list = $list->toArray();
            } else {
                break;
            }
            if (empty($list)) {
                break;
            }
            $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS');
            if (!$con) {
                $e = oci_error();
                print_r(htmlentities($e['message'])) . "\n";
                die;
            }
            $list = implode(',',$list);
            echo $list."\n";
            $sql = "select *,to_char(ZKRQ,'yyyy-mm-dd hh24:mi:ss') as ZKRQ1 from PORTAL_HIS.V_JMGS_BASY_FY where `ZYH` in ($list)";
            $data = oci_parse($con, $sql);
            oci_execute($data, OCI_DEFAULT);
            $result = [];
            while ($row = oci_fetch_assoc($data)) {
                $result[] = $row;
            }
            if (empty($result)) {
                $offset++;
                continue;
            }
            $fee = [];
            $add = [];
            foreach ($result as $item) {
                $add[] = [
                    'AAA28' => $item['ZYH'],
                    'TYSHXYDM' => $item['TYSHXYDM'] ?? '',
                    'JKKH' => $item['JKKH'] ?? '',
                    'SFZJLX' => $item['ZJLB'] ?? '',
                    'SJHL' => $item['SJHL'] ?? '',
                    'EJHL' => $item['EJHL'] ?? '',
                    'YJHL' => $item['YJHL'] ?? '',
                    'TJHL' => $item['TJHL'] ?? '',
                    'ZRHS' => $item['ZRHS'] ?? '',
                    'ZRHSBM' => $item['ZRHSBM'] ?? '',
                    'ZKHS' => $item['ZKHS'] ?? '',
                    'ZKHSBM' => $item['ZKHSBM'] ?? '',
                    'ZKRQ' => $item['ZKRQ1'] ?? '',
                    'ZHFZRYS' => $item['ZHFZRYS'] ?? '',
                    'ZZYSBM' => $item['ZZYSBM'] ?? '',
                    'ZYYSBM' => $item['ZYYSBM'] ?? '',
                    'ZZZYSBM' => $item['ZZZYSBM'] ?? '',
                    'KZRXM' => $item['KZRXM'] ?? '',
                    'ZHFZRYSXM' => $item['ZHFZRYSXM'] ?? '',
                    'ZZYSXM' => $item['ZZYSXM'] ?? '',
                    'ZYYSXM' => $item['ZYYSXM'] ?? '',
                    'ZZYISXM' => $item['ZZYISXM'] ?? '',
                    'BMY' => $item['BMY'] ?? '',
                    'RYKB' => $item['RYKB'] ?? '',
                    'BFRY' => $item['BFRY'] ?? '',
                    'ZKKB' => $item['ZKKB'] ?? '',
                    'CYKB' => $item['CYKB'] ?? '',
                    'HB' => $item['HB'] ?? '',
                    'HCV' => $item['HCV'] ?? '',
                    'HIV' => $item['HIV'] ?? '',
                    'LCLJ' => $item['LCLJ'] ?? '', // 临床路径
                    'WCQK' => $item['WCQK'] ?? '',
                    'BYQK' => $item['BYQK'] ?? '',
                ];
            }
            if (!empty($fee)) {
                PatientCostInfo::query()->insert($fee);
            }
            if (!empty($add)) {
                PatientAdd::query()->insert($add);
            }
            $offset++;
        }
        return 0;
    }
}
