<?php

namespace App\Services;

use App\Model\EMR_BL_BASYSJ;
use App\Model\SSSQ;

/**
 * 数据同步
 */
class DataSyncService
{
    /**
     * 临床书写首页的手术和诊断（EMR_BL_BASYSJ）
     * @param $MED_REC_ID
     * @return array|void
     */
    public static function selectEbbInfo($MED_REC_ID)
    {
        $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS', 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }

        $sql = "SELECT JLXH,JZHM,BLBH,XMXH,XMMC,XMQZ,DYYS,DLLJ,GLZD,KSMRZ,SYBTX,XMNM FROM PORTAL_HIS.EMR_BL_BASYSJ WHERE JZHM=".$MED_REC_ID." XMXH IN(498,638)";
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
     * 写入临床书写首页的手术和诊断数据
     * @param $data
     * @return bool
     */
    public static function addEbbData($data)
    {
        return EMR_BL_BASYSJ::query()->insert($data);
    }

    /**
     * 查询手术申请数据（SSSQ）
     * @param $MED_REC_ID
     * @return array|void
     */
    public static function selectSssqInfo($MED_REC_ID)
    {
        $con = oci_connect('ogg', 'ogg#2022', '10.10.11.21:1521/ODS', 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }

        $sql = "SELECT SQDH,JGID,ZYH,SSKS,SQKS,SQYS,to_char(SQRQ,'yyyy-mm-dd hh24:mi:ss') as SQRQ,to_char(SSRQ,'yyyy-mm-dd hh24:mi:ss') as SSRQ,SSNM,SSYS,SSYZ,SSSZ,SSEZ,SSYQ,MZDM,MZYS,TJBZ,APBZ,ZFBZ,TXKS,CZGH,SQTL,SQZD,NSSMC,FYBQ,QKDJ,THYY,ZFYY,ZFGH,LRBZ,YXJS,NLTR,HBQTJB,QTTSQK,TSQKNR,SSJB,CRBZ,CRBG,BXBZ,BXSM,BZXX,SSTW,SPBZ,CFSS,ZLXZ,RJSS FROM PORTAL_HIS.SM_SSSQ WHERE ZYH=".$MED_REC_ID;
        $data = oci_parse($con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        unset($con);
        return $result;
    }

    public static function addSssqData($data)
    {
        return SSSQ::query()->insert($data);
    }

}

