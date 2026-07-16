<?php

namespace App\Console\Commands;

use App\Model\Department;
use App\Model\RuleWordMap;
use App\Model\Staff;
use Illuminate\Console\Command;

class ShizhongDataSyncTmp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:shizhong-tmp {start_time?} {end_time?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步事中质控相关的病例数据';

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
        $endTime = $this->argument("end_time");
        $this->ZY_BRRY($startTime, $endTime);
    }

    /**
     * @param string $zyh 根据指定住院号同步brry数据
     * @param int $sync_data 同步brry表数据，不质控
     * @param int $last_id 质控从什么时候开始同步brry的数据，这个是一个6位住院号
     * @return bool
     * 三院
     * SELECT CYPB,ZYH,ZYHM,BRKS,BRXM,ZZYS,ZSYS,to_char(RYRQ,'yyyy-mm-dd HH24:mi:ss') as RYRQ,to_char(CYRQ,'yyyy-mm-dd HH24:mi:ss') as CYRQ,BRKS,BRBQ,BRCH,ZYYS,ZLXZ,ZZYS,MZYS,ZSYS,CYPB,CYFS,ZYHM FROM PORTAL_HIS.ZY_BRRY
     * 兰陵
     * SELECT ZYH,AABO1 AS RYRQ,GCYS,ZZYS AS ZZYSMC,ZRYS_MC,CCSJ AS CYRQ,CYPB,ZY_KSDM,ZY_KSMC,ZY_BQDM,ZY_BQMC,CH AS BRCH,GCYSDM,ZRYS,ZYCS AS RYCS,BRXM,BAN AS ZYHM FROM iih.VZK_ZY_BRRY
     * 预警消息，brry数据同步
     */
    public function ZY_BRRY($startTime = "", $endTime = "")
    {
        $data = $this->getBRRYData($startTime, $endTime);

        $department = Department::query()->get()->toArray();
        $dep = array_column($department, 'dep_name', 'dep_id');

        $staff = Staff::query()->get()->toArray();
        $staff = array_column($staff, 'name', 'code');

        $data = array_column($data, null, 'ZYH');
        $data = array_values($data);
        foreach ($data as $item) {
            var_dump($item['ZYH'].' - '.$item['RYRQ'].' - '.$item['CYRQ'] . '-' .date('Y-m-d H:i:s'));
            try {
                $update = [
                    'AAA28' => $item['ZYHM'] ?? "",
                    'AAB01' => $item['RYRQ'] ?? "",
                    'AAC01' => $item['CYRQ'] ?? "",
                    'ZY_KSDM' => $item['ZY_KSDM'] ?? "",
                    'BRKS' => $item['BRKS'] ?? "",
                    'ZY_KSMC' => $item['ZY_KSMC'] ?? "",
                    'GCYSDM' => $item['GCYSDM'] ?? $item['ZYYS'] ?? "",
                    'GCYSMC' => $item['GCYS'] ?? $staff[$item['ZYYS']] ?? "",
                    'ZLZZDM' => $item['ZLXZ'] ?? "",
                    'ZLZZMC' => !empty($item['ZLXZ']) ? $staff[$item['ZLXZ']] ?? "" : "",
                    'ZZYSDM' => $item['ZZYS'] ?? "",
                    'ZZYSMC' => $item['ZZYSMC'] ?? $staff[$item['ZZYS']??""] ?? "",
                    'MZYS' => $item['MZYS'] ?? "",
                    'MZYS_MC' => !empty($item['MZYS']) ? $staff[$item['MZYS']] ?? "" : "",
                    'ZRYS' => $item['ZSYS'] ?? "",
                    'ZRYS_MC' => $item['ZRYS_MC'] ?? $staff[$item['ZSYS']] ?? "",
                    'CYPB' => $item['CYPB'] ?? "",
                    'CYFS' => $item['CYFS'] ?? "",
                    'BRXM' => $item['BRXM'] ?? "",
                    'BRBQ' => $item['ZY_BQDM'] ?? $item['BRBQ'] ?? "",
                    'ZYCS' => $item['RYCS'] ?? "",
                    'ZY_BQDM' => $item['ZY_BQDM'] ?? $item['BRBQ'] ?? "",
                    'ZY_BQMC' => $item['ZY_BQMC'] ?? $dep[$item['BRBQ']] ?? "",
                ];
                if ($item['BRCH']) {
                    $update["CH"] = $item['BRCH'];
                }
                \App\Model\ZY_BRRY::query()->updateOrInsert(['ZYH' => $item['ZYH']], $update);

            } catch (\Throwable $e) {
                var_dump($e->getMessage());
                var_dump($e->getFile() . $e->getLine());
                continue;
            }
        }
    }

    /**
     * @return array
     * 获取HIS中BRRY的数据
     */
    public function getBRRYData($startTime = "", $endTime = "")
    {
        $username = env('HISDB_USERNAME', '');
        $password = env('HISDB_PASSWORD', '');
        $connection = env('HISDB_HOST', '');
        $port = env('HISDB_PORT', '');
        $tns = env('HISDB_TNS', '');
        $con = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        // 同步所有数据
        if (!$startTime) {
            $startTime = date("Ymd");
        }

        $sql = RuleWordMap::query()->where("id", 9007)->value("keyword");
        $map9013 = RuleWordMap::query()->where("id", 9013)->value("keyword");
        // 指定住院号同步
        $sql .= " WHERE {$map9013} > TO_DATE('" . $startTime . "000000', 'yyyy-MM-dd HH24:mi:ss') and {$map9013} < TO_DATE('" . $endTime . "235959', 'yyyy-MM-dd HH24:mi:ss')";
        $result = oci_parse($con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }

        return $data;
    }
}
