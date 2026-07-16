<?php

namespace App\Console\Commands;

use App\Model\DataxSyncSetting;
use App\Model\PatientInfo;
use App\Model\QualitySendMsgLog;
use App\Services\EsSaveService;
use App\Services\RadioService;
use Illuminate\Console\Command;
use App\Model\Yzb;

class addYZB extends Command
{
    protected $signature = 'add:yzb {zyh?} {startTime?} {endTime?}';
    protected $description = '补医嘱';
    protected $con;

    public function handle()
    {
        $this->info('补医嘱-' . date('Y-m-d H:i:s'));
        $startTime = $this->argument('startTime') ?? '';
        $endTime = $this->argument('endTime') ?? '';
        $username = "nhzk";
        $password = "nhzk";
        $connection = "10.32.92.86";
        $port = "1521";
        $tns = "IIH";
        $con = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
        $this->con = $con;
        $data = PatientInfo::query();
        if ($this->argument('zyh')) {
            $data = $data->where('MED_REC_ID', $this->argument('zyh'));
        }
        if (empty($startTime)) {
            //前一天00
            $startTime = date('Y-m-d 00:00:00', strtotime('-1 day'));
        }
        if (empty($endTime)) {
            //今天
            $endTime = date('Y-m-d 23:59:59', time());
        }
        $data = $data->where('AAC01', '>=', $startTime)->where('AAC01', '<=', $endTime);
        $data = $data->where('AAC01', '<=', $endTime);
        $data = $data->orderBy('AAC01', 'desc')->get();
        foreach ($data as $item) {
            var_dump($item->MED_REC_ID . '-Start:' . $item->AAC01);
            $yzb = $this->getYzb($item->MED_REC_ID);
            $this->addYzb($yzb);
            // 清洗抗菌药物、化疗药物
            var_dump($item->MED_REC_ID . '-清洗抗菌药物、化疗药物');
            RadioService::filterField($item->MED_REC_ID);
            var_dump($item->MED_REC_ID . '-清洗抗菌药物、化疗药物完毕');
            // 导入ES中
            var_dump($item->MED_REC_ID . '-导入ES');
            EsSaveService::yzb($item->MED_REC_ID);
            var_dump($item->MED_REC_ID . '-导入ES中完毕');
            var_dump($item->MED_REC_ID . '-End:' . $item->AAC01);
        }
        $this->info('补医嘱-' . date('Y-m-d H:i:s') . ' 完毕');
    }

    /**
     * 医嘱
     * @param $ZYH
     * @return array
     */
    public function getYzb($ZYH)
    {
        //$sql = "SELECT A.*,to_char(TZSJ,'yyyy-mm-dd hh24:mi:ss') as TJ,to_char(XZJDSJ,'yyyy-mm-dd hh24:mi:ss') as XJ,to_char(TZQRSJ,'yyyy-mm-dd hh24:mi:ss') as TZJ,to_char(APSJ,'yyyy-mm-dd hh24:mi:ss') as AJ,to_char(KZSJ,'yyyy-mm-dd hh24:mi:ss') as KJ FROM PORTAL_HIS.BTF_EMR_YZB A WHERE ZYH=" . $ZYH;
        $sql = DataxSyncSetting::getByNameSql('yzb', 0);
        $sql = $sql . " WHERE CI_ORDER.ID_EN= '" . $ZYH . "'";
        //$sql = $sql . " AND TO_DATE(CI_ORDER.DT_ENTRY,'YYYY-MM-DD HH24:MI:SS') >= TRUNC(SYSDATE) - 3";
        //Log::info('qualityHandleV2 yzb sql', ['sql' => $sql, 'zyh' => $ZYH]);
        $data = oci_parse($this->con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        return $result;
    }

    public function addYzb($data)
    {
        $insertData = [];
        $zyh = 0;
        foreach ($data as $val) {
            $zyh = $val['ZYH'];
            if (!empty($val['DSG_OPERATION']) && $val['DSG_OPERATION'] == 'D') {
                // 删除预警信息
                QualitySendMsgLog::setStatus($val['ZYH'], 109, $val['YZBXH']);
                QualitySendMsgLog::setStatus($val['ZYH'], 110, $val['YZBXH']);
            } else {

                $insertData[] = [
                    'ZYH' => $val['ZYH'],
                    'YZBXH' => $val['YZBXH'] ?? '',
                    'RID' => $val['BRID'] ?? '',
                    'YEPB' => $val['YEPB'] ?? '',
                    'BRKS' => $val['BRKS'] ?? '',
                    'BRBQ' => $val['BRBQ'] ?? '',
                    'BRCH' => $val['BRCH'] ?? '',
                    'YDYZLB' => $val['YDYZLB'] ?? '',
                    'XMLB' => $val['XMLB'] ?? '',
                    'XMID' => $val['XMID'] ?? '',
                    'XMDJ' => $val['XMDJ'] ?? '',
                    'YZZH' => $val['YZZH'] ?? '',
                    'YZQX' => $val['YZQX'] ?? '',
                    'YYSX' => $val['YYSX'] ?? '',
                    'KZKS' => $val['KZKS'] ?? '',
                    'KZYS' => $val['KZYS'] ?? '',
                    'KZSJ' => $val['KZSJ'] ?? '',
                    'YZMC' => $val['YZMC'] ?? '',
                    'YPCD' => $val['YPCD'] ?? '',
                    'FYSX' => $val['FYSX'] ?? '',
                    'SYPC' => $val['SYPC'] ?? '',
                    'GYTJ' => $val['GYTJ'] ?? '',
                    'YCJL' => $val['YCJL'] ?? '',
                    'JLDW' => $val['JLDW'] ?? '',
                    'ZL' => $val['ZL'] ?? '',
                    'ZLDW' => $val['ZLDW'] ?? '',
                    'JJYZ' => $val['JJYZ'] ?? '',
                    'BLYZ' => $val['BLYZ'] ?? '',
                    'TZSJ' => $val['TZSJ'] ?? '',
                    'TZYS' => $val['TZYS'] ?? '',
                    'YZZT' => $val['YZZT'] ?? '',
                    'ZXZT' => $val['ZXZT'] ?? '',
                    'KZDY' => $val['KZDY'] ?? '',
                    'ZTBZ' => $val['ZTBZ'] ?? '',
                    'XZJDGH' => $val['XZJDGH'] ?? '',
                    'XZJDSJ' => $val['XJ'] ?? '',
                    'TZQRGH' => $val['TZQRGH'] ?? '',
                    'TZQRSJ' => $val['TZJ'] ?? null,
                    'APSJ' => $val['AJ'] ?? null,
                    'YYTS' => $val['YYTS'] ?? null,
                    'YSZT' => $val['YSZT'] ?? '',
                    'SRCS' => $val['SRCS'] ?? null,
                    'SRSD' => $val['SRSD'] ?? '',
                    'ZXSD' => $val['ZXSD'] ?? '',
                    'DS' => $val['DS'] ?? null,
                    'DSDW' => $val['DSDW'] ?? '',
                    'PSBZ' => $val['PSBZ'] ?? '',
                    'PSJG' => $val['PSJG'] ?? null,
                    'ZFPB' => $val['ZFPB'] ?? '',
                    'YBLX' => $val['YBLX'] ?? '',
                    'SPBH' => $val['SPBH'] ?? null,
                    'CYJF' => $val['CYJF'] ?? '',
                    'PLSX' => $val['PLSX'] ?? '',
                    'CZBZ' => $val['CZBZ'] ?? '',
                    'BZXX' => $val['BZXX'] ?? '',
                    'SQDH' => $val['SQDH'] ?? '',
                    'ZXKS' => $val['ZXKS'] ?? '',
                    'YFGG' => $val['YFGG'] ?? '',
                    'YFDW' => $val['YFDW'] ?? '',
                    'YFBZ' => $val['YFBZ'] ?? '',
                    'SFSJ' => $val['SFSJ'] ?? '',
                    'YFYY' => $val['YFYY'] ?? '',
                    'YFYYYY' => $val['YFYYYY'] ?? '',
                    'QXKZ' => $val['QXKZ'] ?? '',
                    'YYPS' => $val['YYPS'] ?? '',
                    'FZLJ' => $val['FZLJ'] ?? '',
                    'PASSINDEX' => $val['PASSINDEX'] ?? '',
                    'QXMC' => $val['QXMC'] ?? '',
                    'YZPLZH' => $val['YZPLZH'] ?? '',
                    'LCTS' => $val['LCTS'] ?? '',
                    'ZLFY' => $val['ZLFY'] ?? '',
                    'YZLX' => $val['YZLX'] ?? '',
                    'SSYZ' => $val['SSYZ'] ?? '',
                    'CDA_PC' => $val['CDA_PC'] ?? '',
                    //                    'ZXSJ' => $val['ZJ'] ?? '', //---这个注释掉 oracle没有这个字段
                    'NWARN' => $val['NWARN'] ?? '',
                    'DSG_LDR_TIME' => $val['DSG_LDR_TIME'] ?? '',
                    'DSG_OPERATION' => $val['DSG_OPERATION'] ?? '',
                ];
            }
        }
        $yzb = Yzb::query()->where('ZYH', '=', $zyh)->get()->toArray();
        $idArr = array_column($yzb, 'id');
        Yzb::query()->whereIn('id', $idArr)->delete();
        if ($idArr) {

            $params = [
                'index' => 'yzb_2023',
                'body' => [
                    'query' => [
                        'term' => [
                            'ZYH' => $zyh
                        ]
                    ]
                ]
            ];
            app('es')->deleteByQuery($params);
        }

        $oldYZBXH = array_column($yzb, 'YZBXH');
        $YZBXH = array_column($insertData, 'YZBXH');
        $deleteYZBXH = array_diff($YZBXH, $oldYZBXH);
        if ($deleteYZBXH) {
            foreach ($deleteYZBXH as $xh) {
                QualitySendMsgLog::setStatus($val['ZYH'], 109, $xh);
                QualitySendMsgLog::setStatus($val['ZYH'], 110, $xh);
            }
        }

        //Yzb::query()->where('ZYH', '=', $data[0]['ZYH'])->delete();
        if (!empty($insertData)) {
            //Yzb::query()->insert($insertData);
            $chunkList = array_chunk($insertData, 500);
            foreach ($chunkList as $value) {
                Yzb::query()->insert($value);
            }
        }
    }
}
