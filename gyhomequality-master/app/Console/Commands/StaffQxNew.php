<?php

namespace App\Console\Commands;

use App\Model\StaffIdentity;
use Illuminate\Console\Command;
use App\Model\Staff;
use Illuminate\Support\Facades\Log;

class StaffQxNew extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staff:qx';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';
    protected $con;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->con = oci_connect('VS_BAZK', 'vs_bazk#123', '10.32.82.8:1521/ods',"UTF8");
        if (!$this->con) {
            $e = oci_error();
            Log::info('病案首页质控error', ['msg' => 'getFyData：'.htmlentities($e['message'])]);
        }
        $this->qxks();//清洗科室
    }

    public function qxks()
    {
        $page = 1;
        $pageSize = 100;
        while (true)
        {
            if (empty($v[$vv['filed']])) break;
            $data = Staff::query()->whereNull('ks_code')->select('base_code as code')->paginate($pageSize,$page)->toArray();
            if (empty($data['data'])) break;
            $page++;
            foreach ($data['data'] as $k=>$v)
            {
                $result = $this->getOdsData($v['code']);
                if (!empty($result)){
                    Staff::query()->where('base_code',$v['code'])->update($result);
                }
                echo date('Y-m-d H:i:s')."\n";
            }
        }
    }

    protected function getOdsData($code)
    {
        $sql = "SELECT (NVL(BD_DEP.CODE, '')) AS KS_CODE, (NVL(BD_DEP.NAME, '')) AS KS_NAME FROM HIS.SYS_USER LEFT JOIN HIS.BD_PSNDOC ON HIS.BD_PSNDOC.ID_PSNDOC = HIS.BD_DEP.ID_PSN LEFT JOIN HIS.BD_DEP ON HIS.BD_DEP.ID_DEP = HIS.BD_PSNDOC.ID_DEP" ;
        $sql .= " WHERE HIS.SYS_USER.CODE = '".$code."'";
        $data = oci_parse($this->con, $sql);
        echo $sql."\n";
        exit();
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        oci_free_statement($data);
        return $result;
    }
}
