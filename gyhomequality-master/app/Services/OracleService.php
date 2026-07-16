<?php

namespace App\Services;

use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\CaseService;

/**
 * oracle相关的数据获取操作
 */
class OracleService
{

    public function getBl01Data($blbh = '')
    {
        if (empty($blbh)) {
            return [];
        }
        $username = env('ORACLE_USERNAME', 'zdyh');
        $password = env('ORACLE_PASSWORD', 'zdyh');
        $connection = env('ORACLE_HOST', '172.16.9.8');
        $port = env('ORACLE_PORT', '1521');
        $tns = env('ORACLE_TNS', 'his');
        $con = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $sql = "SELECT a.*,to_char(ZXSJ,'yyyy-mm-dd hh24:mi:ss') as ZJ,to_char(CJSJ,'yyyy-mm-dd hh24:mi:ss') as CJ,to_char(WCSJ,'yyyy-mm-dd hh24:mi:ss') as WJ,to_char(XGSJ,'yyyy-mm-dd hh24:mi:ss') as XJ FROM PORTAL55_EMR.V_JMGS_BASY_QBL a WHERE BLBH=" . $blbh;
        $result = oci_parse($con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if(!$data){
            return [];
        }
        $item = $data[0];
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
        if (!empty($item['HJNR'])) {
            $text = $item['HJNR']->load();
            $item['HJNR']->free();
            $mde = mb_detect_encoding($text, array("ASCII", 'UTF-8', "GB2312", "GBK", 'BIG5'));
            if ($mde) {
                $str = mb_convert_encoding($text, 'utf-8', $mde);
            }
        }
        $temp = [
            'JLXH' => $item['JLXH'] ?? '',
            'BLBH' => $item['BLBH'] ?? '',
            'XGGH' => $item['XGGH'] ?? '',
            'XGSJ' => $item['XJ'] ?? '',
            'HJNR' => $str,
        ];
        EMR_BL_BLXG::query()->insertOrIgnore($temp);
        EMR_BL_BL01::query()->insertOrIgnore($result);
        // 格式化出院记录
        if($result['BLLB'] == 1){
            return CaseService::analysisCaseCy(
                ['analysis_index' => 0, 'BLBH' => $blbh],
                $str
            );
        }else{
            // 格式化入院记录
            return CaseService::analysisCaseRy(
                ['analysis_index' => 0, 'BLBH' => $blbh],
                $str
            );
        }

    }


    /**
     * 获取用户主信息
     * @param $ZYH
     * @return array
     */
    public function getBlInfo($ZYH)
    {
        //$con = oci_connect('zdyh', 'zdyh', '172.16.9.8:1521/his',"UTF8");
        $con = oci_connect('jmgs', 'jmgs@2', '172.16.2.238:1521/ods',"UTF8");
        if (!$con) {
            $e = oci_error();
            Log::info('详情页-blInfo api error', ['msg' => 'INIT_MED_REC_MAIN：'.htmlentities($e['message'])]);
        }

        $sql = "SELECT XMMC FROM PORTAL55_EMR.EMR_BL_BASYSJ WHERE JZHM = $ZYH and (XMXH=461 or XMXH=464) and DSG_OPERATION !='D'";
        $data = oci_parse($con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        unset($con);

        return $result;
    }

}
