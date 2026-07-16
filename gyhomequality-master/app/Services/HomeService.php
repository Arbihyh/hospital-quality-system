<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class HomeService
{
    /**
     * 获取费用信息
     * @param $ZYH
     * @return array
     */
    public function getFyData($ZYH)
    {
        $con = oci_connect('zdyh', 'zdyh', '172.16.9.8:1521/his',"UTF8");
        if (!$con) {
            $e = oci_error();
            Log::info('病案首页质控error', ['msg' => 'getFyData：'.htmlentities($e['message'])]);
        }

        // 查询费用数据
        // ,FYGB,SYFYGB,ZJE
        $sql = "SELECT ZYH as AAA28,FYXH,FYMC,ZFJE,to_char(JFRQ,'yyyy-mm-dd hh24:mi:ss') as JFRQ,FYSL,FYDJ,FYKS FROM PORTAL_HIS.V_ZY_FYMX WHERE ZYH=".$ZYH;
        $data = oci_parse($con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        unset($con);

        return $result;
    }

    /**
     * 获取用户主信息
     * @param $ZYH
     * @return array
     */
    public function getPatientInfo($ZYH)
    {
        $con = oci_connect('zdyh', 'zdyh', '172.16.9.8:1521/his',"UTF8");
        if (!$con) {
            $e = oci_error();
            Log::info('病案首页质控error', ['msg' => 'INIT_MED_REC_MAIN：'.htmlentities($e['message'])]);
        }

        $sql = "SELECT A.*,to_char(AAA03,'yyyy-mm-dd hh24:mi:ss') as AAA03,to_char(SYN_DATE,'yyyy-mm-dd hh24:mi:ss') as SYN_DATE,to_char(AAB01,'yyyy-mm-dd hh24:mi:ss') as AAB01,to_char(AAC01,'yyyy-mm-dd hh24:mi:ss') as AAC01,to_char(AED04,'yyyy-mm-dd hh24:mi:ss') as AED04,to_char(MT_JZRQ,'yyyy-mm-dd') as MT_JZRQ FROM PORTAL_HIS.INIT_MED_REC_MAIN A WHERE A.MED_REC_ID=".$ZYH;
        $data = oci_parse($con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        unset($con);

        return $result;
    }

    /**
     * 获取医嘱信息
     * @param $ZYH
     * @return array
     */
    public function getYzbInfo($ZYH)
    {
        $con = oci_connect('zdyh', 'zdyh', '172.16.9.8:1521/his',"UTF8");
        if (!$con) {
            $e = oci_error();
            Log::info('病案首页质控error', ['msg' => 'INIT_MED_REC_MAIN：'.htmlentities($e['message'])]);
        }

        $sql = "SELECT A.*,to_char(TZSJ,'yyyy-mm-dd hh24:mi:ss') as TZSJ,to_char(XZJDSJ,'yyyy-mm-dd hh24:mi:ss') as XZJDSJ,to_char(TZQRSJ,'yyyy-mm-dd hh24:mi:ss') as TZQRSJ,to_char(APSJ,'yyyy-mm-dd hh24:mi:ss') as APSJ,to_char(KZSJ,'yyyy-mm-dd hh24:mi:ss') as KZSJ,to_char(ZXSJ,'yyyy-mm-dd hh24:mi:ss') as ZXSJ FROM PORTAL_HIS.EMR_YZB A WHERE A.ZYH=".$ZYH;
        $data = oci_parse($con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        unset($con);

        return $result;
    }

    /**
     * 获取重症监护（ICU）信息
     * @param $ZYH
     * @return array
     */
    public function getIcuInfo($ZYH)
    {
        $con = oci_connect('zdyh', 'zdyh', '172.16.9.8:1521/his',"UTF8");
        if (!$con) {
            $e = oci_error();
            Log::info('病案首页质控error', ['msg' => 'INIT_MED_REC_MAIN：'.htmlentities($e['message'])]);
        }

        $sql = "SELECT A.MED_REC_ID,A.AREA_ID,A.BATCH_ID,A.IS_MAIN_WAY,to_char(IN_TIME,'yyyy-mm-dd hh24:mi:ss') as IN_TIME,to_char(OUT_TIME,'yyyy-mm-dd hh24:mi:ss') as OUT_TIME FROM PORTAL_HIS.INIT_MED_REC_TUTORSSIP A WHERE A.MED_REC_ID=".$ZYH;
        $data = oci_parse($con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        unset($con);

        return $result;
    }

    /**
     * 获取诊断信息
     * @param $ZYH
     * @return array
     */
    public function getDiagnosisData($ZYH)
    {
        $con = oci_connect('zdyh', 'zdyh', '172.16.9.8:1521/his',"UTF8");
        if (!$con) {
            $e = oci_error();
            Log::info('病案首页质控error', ['msg' => 'INIT_MED_REC_MAIN：'.htmlentities($e['message'])]);
        }

        $sql = "SELECT A.*,to_char(CYRQ,'yyyy-mm-dd hh24:mi:ss') as CYRQ FROM PORTAL_HIS.V_JMGS_BASY_ZD A WHERE ZYH=".$ZYH;
        $data = oci_parse($con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        unset($con);

        return $result;
    }

    /**
     * 获取手术信息
     * @param $ZYH
     * @return array
     */
    public function getOperationData($ZYH)
    {
        $con = oci_connect('zdyh', 'zdyh', '172.16.9.8:1521/his',"UTF8");
        if (!$con) {
            $e = oci_error();
            Log::info('病案首页质控error', ['msg' => 'INIT_MED_REC_MAIN：'.htmlentities($e['message'])]);
        }

        $sql = "SELECT A.*,to_char(CYRQ,'yyyy-mm-dd hh24:mi:ss') as CYRQ,to_char(SSCZRQ,'yyyy-mm-dd hh24:mi:ss') as SSCZRQ,to_char(SSKSSJ,'yyyy-mm-dd hh24:mi:ss') as SSKSSJ,to_char(SSJSSJ,'yyyy-mm-dd hh24:mi:ss') as SSJSSJ,to_char(SSCZRQ,'yyyy-mm-dd hh24:mi:ss') as SSCZRQ FROM PORTAL_HIS.V_JMGS_BASY_SS A WHERE A.ZYH=".$ZYH;
        $data = oci_parse($con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        unset($con);

        return $result;
    }

    /**
     * 补充信息
     * @param $ZYH
     * @return array
     */
    public function getBuChong($ZYH)
    {
        $con = oci_connect('zdyh', 'zdyh', '172.16.9.8:1521/his',"UTF8");
        $sql = "SELECT A.*,to_char(ZKRQ,'yyyy-mm-dd hh24:mi:ss') as ZKRQ1 FROM PORTAL_HIS.V_JMGS_BASY_FY A WHERE A.ZYH=".$ZYH;
        $data = oci_parse($con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        unset($con);

        return $result;
    }

    /**
     * ZY_ZKJL
     * @param $ZYH
     * @return array
     */
    public function ZY_ZKJL($ZYH)
    {
        $con = oci_connect('zdyh', 'zdyh', '172.16.9.8:1521/his',"UTF8");
        $sql = "SELECT A.*,to_char(YSSQRQ,'yyyy-mm-dd hh24:mi:ss') as YSSQRQ,to_char(BQSQRQ,'yyyy-mm-dd hh24:mi:ss') as BQSQRQ,to_char(BQZXRQ,'yyyy-mm-dd hh24:mi:ss') as BQZXRQ,to_char(YSZXRQ,'yyyy-mm-dd hh24:mi:ss') as YSZXRQ FROM PORTAL_HIS.ZY_ZKJL A WHERE A.ZYH=".$ZYH;
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
