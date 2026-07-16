<?php

namespace App\Services;

use Exception;
use App\Model\Bllb1;
use App\Model\Bllb288;
use App\Model\Bllb292;
use App\Model\Bllb303;
use App\Model\OMR_BL01;
use App\Model\Indicator;
use App\Model\Bllb294_45;
use App\Model\OmrQuality;
use App\Model\Bllb294_295;
use App\Model\Bllb303_303;
use App\Model\Bllb329Bdqp;
use App\Model\Bllb329Ctzq;
use App\Model\Bllb329Tszl;
use App\Model\Bllb34Sjgzs;
use App\Model\DataSyncLog;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLSY;
use App\Model\EMR_BL_BLXG;
use App\Model\FeeDetailed;
use App\Model\PatientInfo;
use App\Model\Bllb329Hltys;
use App\Model\Bllb329Sqwts;
use App\Model\Bllb329Zrltys;
use App\Model\Bllb34Jjjctys;
use App\Model\Bllb34Zdcygzs;
use App\Model\Bllb329Bwbztys;
use App\Model\Bllb329Sszqtys;
use App\Model\Bllb329Sxzqtys;
use App\Model\Bllb34Ybwffgzs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * es保存数据
 */
class EsSaveService
{
    public static function mzjl($zyh = '')
    {
        $mzjl = DB::select('select DCID AS id,HOSPIZATIONID,OPERATESTARTTIME,OPERATEENDTIME,PREOPERATIONNAME,OPERATOR from mzjl where HOSPIZATIONID = "' . $zyh . '"');
        if (empty($mzjl)) {
            return false;
        }
        $mzjl = json_decode(json_encode($mzjl), true);

        // 病例索引
        $es_params = [];
        $index = 'mzjl_2023';
        foreach ($mzjl as $item) {
            $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['id']]];
            $es_params['body'][] = ['doc' => [
                "uniid" => $item['id'],
                "HOSPIZATIONID" => $item['HOSPIZATIONID'],
                "OPERATESTARTTIME" => $item['OPERATESTARTTIME'],
                "OPERATEENDTIME" => $item['OPERATEENDTIME'],
                "PREOPERATIONNAME" => $item['PREOPERATIONNAME'],
                "OPERATOR" => $item['OPERATOR'],
            ], 'doc_as_upsert' => true];
        }
        if (empty($es_params)) {
            return false;
        }
        $res = app('es')->bulk($es_params);
        if ($res['errors'] == true) {
            foreach ($res['items'] as $item) {
                if (!empty($item['update']['error'])) {
                    DataSyncLog::addData(['zyh' => $zyh, 'content' => '消息队列质控失败：' . $item['update']['error']['reason']]);
                }
            }
        }

        return true;
    }

    public static function sssq($zyh = '')
    {
        $sssq = DB::select('select * from SSSQ where ZYH = "' . $zyh . '"');
        if (empty($sssq)) {
            return false;
        }
        $sssq = json_decode(json_encode($sssq), true);

        // 病例索引
        $es_params = [];
        $index = 'sssq_2023';
        foreach ($sssq as $item) {
            $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['SQDH']]];
            $es_params['body'][] = ['doc' => [
                "SQDH" => $item["SQDH"] ?: '',
                "JGID" => $item["JGID"] ?: '',
                "ZYH" => $item["ZYH"] ?: '',
                "SSKS" => $item["SSKS"] ?: '',
                "SQKS" => $item["SQKS"] ?: '',
                "SQYS" => $item["SQYS"] ?: '',
                "SQRQ" => $item["SQRQ"] ?: '',
                "SSRQ" => $item["SSRQ"] ?: '',
                "SSNM" => $item["SSNM"] ?: '',
                "SSYS" => $item["SSYS"] ?: '',
                "SSYZ" => $item["SSYZ"] ?: '',
                "SSSZ" => $item["SSSZ"] ?: '',
                "SSEZ" => $item["SSEZ"] ?: '',
                "SSYQ" => $item["SSYQ"] ?: '',
                "MZDM" => $item["MZDM"] ?: '',
                "MZYS" => $item["MZYS"] ?: '',
                "TJBZ" => $item["TJBZ"] ?: '',
                "APBZ" => $item["APBZ"] ?: '',
                "ZFBZ" => $item["ZFBZ"] ?: '',
                "TXKS" => $item["TXKS"] ?: '',
                "CZGH" => $item["CZGH"] ?: '',
                "SQTL" => $item["SQTL"] ?: '',
                "SQZD" => $item["SQZD"] ?: '',
                "NSSMC" => $item["NSSMC"] ?: '',
                "FYBQ" => $item["FYBQ"] ?: '',
                "QKDJ" => $item["QKDJ"] ?: '',
                "THYY" => $item["THYY"] ?: '',
                "ZFYY" => $item["ZFYY"] ?: '',
                "ZFGH" => $item["ZFGH"] ?: '',
                "LRBZ" => $item["LRBZ"] ?: '',
                "YXJS" => $item["YXJS"] ?: '',
                "NLTR" => $item["NLTR"] ?: '',
                "HBQTJB" => $item["HBQTJB"] ?: '',
                "QTTSQK" => $item["QTTSQK"] ?: '',
                "TSQKNR" => $item["TSQKNR"] ?: '',
                "SSJB" => $item["SSJB"] ?: '',
                "CRBZ" => $item["CRBZ"] ?: '',
                "CRBG" => $item["CRBG"] ?: '',
                "BXBZ" => $item["BXBZ"] ?: '',
                "BXSM" => $item["BXSM"] ?: '',
                "BZXX" => $item["BZXX"] ?: '',
                "SSTW" => $item["SSTW"] ?: '',
                "SPBZ" => $item["SPBZ"] ?: '',
                "CFSS" => $item["CFSS"] ?: '',
                "ZLXZ" => $item["ZLXZ"] ?: '',
                "RJSS" => $item["RJSS"] ?: '',
            ], 'doc_as_upsert' => true];
        }
        if (empty($es_params)) {
            return false;
        }
        $res = app('es')->bulk($es_params);
        if ($res['errors'] == true) {

            foreach ($res['items'] as $item) {
                if (!empty($item['update']['error'])) {
                    DataSyncLog::addData(['zyh' => $zyh, 'content' => '消息队列质控失败：' . $item['update']['error']['reason']]);
                }
            }
        }
        return true;
    }

    public static function bl01($zyh = '')
    {

        $bl01 = DB::select('SELECT a.first_blsy_time,a.NA_MED,a.BLBH as id,a.BLBH,a.MBLB,CJKS,a.WCSJ,p.AAB01,p.AAC01,BRKS,a.JZHM,SXYS,BRBH,p.is_defect,BLLB,BLMC,b.HJNR,CJSJ,ZXSJ,a.operation_time,a.JLSJ,a.operation_handler,a.operation_handler_code,a.operation_end_time,p.AAC11N,a.AAC01 from EMR_BL_BL01 as a inner join EMR_BL_BLXG as b on a.BLBH=b.BLBH left join patient_info as p on a.JZHM=p.MED_REC_ID where a.BLZT<>9 AND a.JZHM="' . $zyh . '"');
        if (empty($bl01)) {
            return false;
        }
        $bl01 = json_decode(json_encode($bl01), true);

        // 病例索引
        $es_params = [];
        $index = 'bl01_202303';
        foreach ($bl01 as $item) {
            $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['BLBH']]];
            $es_params['body'][] = ['doc' => [
                "AAB01" => platformTime($item["AAB01"]),
                "AAC01" => platformTime($item["AAC01"]),
                "AAC11N" => $item["AAC11N"],
                "BLBH" => $item["BLBH"],
                "BLLB" => $item["BLLB"],
                "BLMC" => $item["BLMC"],
                "BRBH" => $item["BRBH"],
                "BRKS" => $item["BRKS"],
                "CJKS" => $item["CJKS"],
                "CJSJ" => platformTime($item["CJSJ"]),
                "HJNR" => $item["HJNR"],
                "JLSJ" => platformTime($item["JLSJ"]),
                "JZHM" => $item["JZHM"],
                "MBLB" => $item["MBLB"],
                "NA_MED" => $item["NA_MED"],
                "SXYS" => $item["SXYS"],
                "WCSJ" => platformTime($item["WCSJ"]),
                "ZXSJ" => platformTime($item["ZXSJ"]),
                "first_blsy_time" => platformTime($item["first_blsy_time"]),
                "is_defect" => $item["is_defect"],
                "operation_end_time" => $item["operation_end_time"],
                "operation_handler" => $item["operation_handler"],
                "operation_handler_code" => $item["operation_handler_code"],
                "operation_time" => $item["operation_time"],

            ], 'doc_as_upsert' => true];
        }
        if (empty($es_params)) {
            return false;
        }
        $res = app('es')->bulk($es_params);
        if ($res['errors'] == true) {

            foreach ($res['items'] as $item) {
                if (!empty($item['update']['error'])) {
                    DataSyncLog::addData(['zyh' => $zyh, 'content' => '消息队列质控失败：' . $item['update']['error']['reason']]);
                }
            }
        }

        return true;
    }

    public static function yzb($zyh = '')
    {
        $yzb = DB::select("select a.YZBXH AS id,a.ZYH,a.BRKS,a.KZKS,a.KZSJ,a.KZYS,a.TZSJ,a.XZJDGH,a.YZMC,a.YZQX,a.XZJDSJ,a.YDYZLB,b.AAA28,b.AAB01,b.AAC01,a.is_has_kjyw,a.is_has_hlyw,a.kjyw_name,a.hlyw_name,a.YCJL,a.JLDW,a.SYPC,a.is_operation,FROM_UNIXTIME(unix_timestamp(a.TZQRSJ), '%Y-%m-%d %H:%i:%s') as TZQRSJ,a.ypmc from yzb as a left join patient_info  as b on a.ZYH =b.MED_REC_ID WHERE a.ZYH='" . $zyh . "'");
        if (empty($yzb)) {
            return false;
        }
        $yzb = json_decode(json_encode($yzb), true);

        // 病例索引
        $es_params = [];
        $index = 'yzb_2023';
        foreach ($yzb as $item) {
            $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['id']]];
            $es_params['body'][] = ['doc' => [
                "id" => $item['id'],
                "ZYH" => $item['ZYH'],
                "BRKS" => $item['BRKS'],
                "KZKS" => $item['KZKS'],
                "KZSJ" => platformTime($item['KZSJ']),
                "KZYS" => $item['KZYS'],
                "TZSJ" => platformTime($item['TZSJ']),
                "XZJDGH" => $item['XZJDGH'],
                "YZMC" => $item['YZMC'],
                "YZQX" => $item['YZQX'],
                "XZJDSJ" => platformTime($item['XZJDSJ']),
                "YDYZLB" => $item['YDYZLB'],
                "AAA28" => $item['AAA28'],
                "AAB01" => platformTime($item['AAB01']),
                "AAC01" => platformTime($item['AAC01']),
                "is_has_kjyw" => $item['is_has_kjyw'],
                "is_has_hlyw" => $item['is_has_hlyw'],
                "kjyw_name" => $item['kjyw_name'],
                "hlyw_name" => $item['hlyw_name'],
                "YCJL" => $item['YCJL'],
                "JLDW" => $item['JLDW'],
                "SYPC" => $item['SYPC'],
                "is_operation" => $item['is_operation'],
                "TZQRSJ" => platformTime($item['TZQRSJ']),
                "ypmc" => $item['ypmc'],
            ], 'doc_as_upsert' => true];
        }
        if (empty($es_params)) {
            return false;
        }
        $res = app('es')->bulk($es_params);
        if ($res['errors'] == true) {

            foreach ($res['items'] as $item) {
                if (!empty($item['update']['error'])) {
                    DataSyncLog::addData(['zyh' => $zyh, 'content' => '消息队列质控失败：' . $item['update']['error']['reason']]);
                }
            }
        }

        return true;
    }

    public static function vjmgsymresult($zyh = '')
    {
        $res = DB::select("SELECT id, ZYH, TXM, NO, XM, XB, NL, CH, YBLX, YBZT, AAA28, BQ, LCZD, PYJG, XJMC, XJJL, YMMC, YMJG, YMBW, SJYS, JYY, SHY, FROM_UNIXTIME(unix_timestamp(CJSJ), '%Y-%m-%d %H:%i:%s') as CJSJ, FROM_UNIXTIME(unix_timestamp(JSSJ), '%Y-%m-%d %H:%i:%s') as JSSJ, FROM_UNIXTIME(unix_timestamp(BGSJ), '%Y-%m-%d %H:%i:%s') as BGSJ,EXAMINAIM,STAYHOSPITALMODE from V_JMGS_YMresult where ZYH = '{$zyh}'");
        if (empty($res)) {
            return false;
        }
        $res = json_decode(json_encode($res), true);

        // 病例索引
        $es_params = [];
        $index = 'v_jmgs_ymresult_2023';
        foreach ($res as $item) {
            $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['id']]];
            $es_params['body'][] = ['doc' => [
                "ZYH" => $item['ZYH'],
                "TXM" => $item['TXM'],
                "NO" => $item['NO'],
                "XM" => $item['XM'],
                "XB" => $item['XB'],
                "NL" => $item['NL'],
                "CH" => $item['CH'],
                "YBLX" => $item['YBLX'],
                "YBZT" => $item['YBZT'],
                "AAA28" => $item['AAA28'],
                "BQ" => $item['BQ'],
                "LCZD" => $item['LCZD'],
                "PYJG" => $item['PYJG'],
                "XJMC" => $item['XJMC'],
                "XJJL" => $item['XJJL'],
                "YMMC" => $item['YMMC'],
                "YMJG" => $item['YMJG'],
                "YMBW" => $item['YMBW'],
                "SJYS" => $item['SJYS'],
                "JYY" => $item['JYY'],
                "SHY" => $item['SHY'],
                "CJSJ" => $item['CJSJ'],
                "JSSJ" => $item['JSSJ'],
                "BGSJ" => $item['BGSJ'],
                "EXAMINAIM" => $item['EXAMINAIM'],
                "STAYHOSPITALMODE" => $item['STAYHOSPITALMODE'],
            ], 'doc_as_upsert' => true];
        }
        if (empty($es_params)) {
            return false;
        }
        $res = app('es')->bulk($es_params);
        if ($res['errors'] == true) {

            foreach ($res['items'] as $item) {
                if (!empty($item['update']['error'])) {
                    DataSyncLog::addData(['zyh' => $zyh, 'content' => '消息队列质控失败：' . $item['update']['error']['reason']]);
                }
            }
        }
        return true;
    }

    public static function pacs($zyh = '')
    {

        $pi = PatientInfo::query()->where('MED_REC_ID', '=', $zyh)->get(['AAA28'])->toArray();
        if (empty($pi)) {
            return false;
        }
        $AAA28 = $pi ? $pi[0]['AAA28'] : '';
        $res = DB::select("SELECT StudyUid,JZLSH,JCMC, FROM_UNIXTIME(unix_timestamp(BGSJ), '%Y-%m-%d %H:%i:%s') as BGSJ, FROM_UNIXTIME(unix_timestamp(KDSJ), '%Y-%m-%d %H:%i:%s') as KDSJ from PACS where JZLSH = '{$AAA28}'");
        if (empty($res)) {
            return false;
        }
        $res = json_decode(json_encode($res), true);

        // 病例索引
        $es_params = [];
        $index = 'pacs';
        foreach ($res as $item) {
            if (empty($item['StudyUid'])) {
                continue;
            }
            $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['StudyUid']]];
            $es_params['body'][] = ['doc' => [
                "StudyUid" => $item['StudyUid'],
                "JZLSH" => $item['JZLSH'],
                "JCMC" => $item['JCMC'],
                "BGSJ" => $item['BGSJ'],
                "KDSJ" => $item['KDSJ'],
            ], 'doc_as_upsert' => true];
        }
        if (empty($es_params)) {
            return false;
        }
        $res = app('es')->bulk($es_params);
        if ($res['errors'] == true) {
            foreach ($res['items'] as $item) {
                if (!empty($item['update']['error'])) {
                    DataSyncLog::addData(['zyh' => $zyh, 'content' => '消息队列质控失败：' . $item['update']['error']['reason']]);
                }
            }
        }
        return true;
    }

    public static function feeDetailed($zyh = '')
    {

        $res = FeeDetailed::query()->where('AAA28', '=', $zyh)->get()->toArray();
        if (empty($res)) {
            return false;
        }

        // 病例索引
        $es_params = [];
        $index = 'fee_detailed';
        foreach ($res as $item) {
            if (empty($item['FYXH'])) {
                continue;
            }

            $_id = $item['FYXH'];
            if (env('APP_NAME') == 'dancheng') {
                $_id = $item['AAA28'] . '-' . $item['FYXH'];
            }
            $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $_id]];
            $es_params['body'][] = ['doc' => [
                "MED_REC_ID" => $item['AAA28'],
                "FYXH" => $item['FYXH'],
                "FYMC" => $item['FYMC'],
                "pre_FYMC" => $item['pre_FYMC'],
                "ZFJE" => $item['ZFJE'],
                "JFRQ" => platformTime($item['JFRQ']),
                "FYSL" => $item['FYSL'],
                "FYDJ" => $item['FYDJ'],
                "ZJE" => $item['ZJE'],
                "FYKS" => $item['FYKS'],
                "YBBM" => $item['YBBM'] ?? '',
                "FYGB" => $item['FYGB'],
                "SYFYGB" => $item['SYFYGB'],
            ], 'doc_as_upsert' => true];
        }
        if (empty($es_params)) {
            return false;
        }
        $res = app('es')->bulk($es_params);
        if ($res['errors'] == true) {

            foreach ($res['items'] as $item) {
                if (!empty($item['update']['error'])) {
                    DataSyncLog::addData(['zyh' => $zyh, 'content' => '消息队列质控失败：' . $item['update']['error']['reason']]);
                }
            }
        }
        return true;
    }

    /**
     * @param string $zyh
     * @return bool
     * 入院记录
     */
    public static function bllb292($zyh = '')
    {
        $res = DB::select("SELECT * from bllb292 where ZYH = '{$zyh}'");
        if (empty($res)) {
            return false;
        }
        $res = json_decode(json_encode($res), true);

        // 病例索引
        $es_params = [];
        $index = 'bllb292_2023';
        foreach ($res as $item) {
            $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['ZYH']]];
            $es_params['body'][] = ['doc' => [
                "AAA28" => $item["AAA28"],
                "AAB01" => platformTime($item["AAB01"]),
                "AAC01" => platformTime($item["AAC01"]),
                "BLBH" => $item["BLBH"],
                "BLMC" => $item["BLMC"],
                "BLZT" => $item["BLZT"],
                "BRKS" => $item["BRKS"],
                "BSCSZ" => $item["BSCSZ"],
                "CBZB_FIRST" => $item["CBZB_FIRST"],
                "CBZD" => $item["CBZD"],
                "CHH" => $item["CHH"],
                "CJKS" => $item["CJKS"],
                "CJSJ" => platformTime($item["CJSJ"]),
                "CSD" => $item["CSD"],
                "DH" => $item["DH"],
                "FZJC" => $item["FZJC"],
                "GRS" => $item["GRS"],
                "GZDW" => $item["GZDW"],
                "HJNR" => $item["HJNR"],
                "HY" => $item["HY"],
                "HYS" => $item["HYS"],
                "JCJG" => $item["JCJG"],
                "JCJGUO" => $item["JCJGUO"],
                "JCSJ" => $item["JCSJ"],
                "JLSJ" => $item["JLSJ"],
                "JWS" => $item["JWS"],
                "JZS" => $item["JZS"],
                "KKCD" => $item["KKCD"],
                "LXBXS" => $item["LXBXS"],
                "LXDZ" => $item["LXDZ"],
                "LXRDH" => $item["LXRDH"],
                "MZ" => $item["MZ"],
                "NL" => $item["NL"],
                "RYSJ" => platformTime($item["RYSJ"]),
                "SXYS" => $item["SXYS"],
                "TGJC" => $item["TGJC"],
                "WCSJ" => platformTime($item["WCSJ"]),
                "XB" => $item["XB"],
                "XBS" => $item["XBS"],
                "XM" => $item["XM"],
                "YJJHYS" => $item["YJJHYS"],
                "YSQM" => $item["YSQM"],
                "ZHS" => $item["ZHS"],
                "ZHUANKE" => $item["ZHUANKE"],
                "ZHY" => $item["ZHY"],
                "ZYH" => $item["ZYH"],
                "ZZ" => $item["ZZ"],
                "ZKJC" => $item["ZKJC"],
            ], 'doc_as_upsert' => true];
        }
        if (empty($es_params)) {
            return false;
        }
        $res = app('es')->bulk($es_params);
        if ($res['errors'] == true) {

            foreach ($res['items'] as $item) {
                if (!empty($item['update']['error'])) {
                    DataSyncLog::addData(['zyh' => $zyh, 'content' => '消息队列质控失败：' . $item['update']['error']['reason']]);
                }
            }
        }
        return true;
    }

    /**
     * @param string $zyh
     * @return bool
     * 出院记录
     */
    public static function bllb1($zyh = '')
    {
        $res = DB::select("SELECT * from bllb1 where ZYH = '{$zyh}'");
        if (empty($res)) {
            return false;
        }
        $res = json_decode(json_encode($res), true);

        // 病例索引
        $es_params = [];
        $index = 'bllb1_2023';
        foreach ($res as $item) {
            $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['ZYH']]];
            $es_params['body'][] = ['doc' => [
                "AAA28" => $item["AAA28"],
                "AAB01" => platformTime($item["AAB01"]),
                "AAC01" => platformTime($item["AAC01"]),
                "BLBH" => $item["BLBH"],
                "BLMC" => $item["BLMC"],
                "BLZT" => $item["BLZT"],
                "BRKS" => $item["BRKS"],
                "CBZD" => $item["CBZD"],
                "CBZD_FIRST" => $item["CBZD_FIRST"],
                "CH" => $item["CH"],
                "CJKS" => $item["CJKS"],
                "CJSJ" => platformTime($item["CJSJ"]),
                "CYQK" => $item["CYQK"],
                "CYRQ" => platformTime($item["CYRQ"]),
                "CYYZ" => $item["CYYZ"],
                "CYZD" => $item["CYZD"],
                "CYZD_FIRST" => $item["CYZD_FIRST"],
                "HJNR" => $item["HJNR"],
                "HZQZ" => $item["HZQZ"],
                "KS" => $item["KS"],
                "MBLB" => $item["MBLB"],
                "NL" => $item["NL"],
                "RYQK" => $item["RYQK"],
                "RYRQ" => platformTime($item["RYRQ"]),
                "SXYS" => $item["SXYS"],
                "TGJC" => $item["TGJC"],
                "WB_ZYH" => $item["WB_ZYH"],
                "WCSJ" => platformTime($item["WCSJ"]),
                "XB" => $item["XB"],
                "XM" => $item["XM"],
                "YSQM" => $item["YSQM"],
                "ZLJG" => $item["ZLJG"],
                "ZXSJ" => platformTime($item["ZXSJ"]),
                "ZYH" => $item["ZYH"],
                "ZYTS" => intval($item["ZYTS"]),
                "RYZD" => $item["RYZD"],
                "data_id" => $item["id"],
            ], 'doc_as_upsert' => true];
        }
        if (empty($es_params)) {
            return false;
        }
        $res = app('es')->bulk($es_params);
        if ($res['errors'] == true) {

            foreach ($res['items'] as $item) {
                if (!empty($item['update']['error'])) {
                    DataSyncLog::addData(['zyh' => $zyh, 'content' => '消息队列质控失败：' . $item['update']['error']['reason']]);
                }
            }
        }
        return true;
    }

    /**
     * @param string $zyh
     * @return bool
     * 首次病程
     */
    public static function bllb294_295_2023($zyh = '')
    {
        $res = DB::select("SELECT * from bllb294_295 where ZYH = '{$zyh}'");
        if (empty($res)) {
            return false;
        }
        $res = json_decode(json_encode($res), true);

        // 病例索引
        $es_params = [];
        $index = 'bllb294_295_2023';
        foreach ($res as $item) {
            $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['ZYH']]];
            $es_params['body'][] = ['doc' => [
                "BLBH" => $item["BLBH"],
                "BLMC" => $item["BLMC"],
                "BLTD" => $item["BLTD"],
                "BLTD_2" => $item["BLTD_2"],
                "BLZT" => $item["BLZT"],
                "BRBH" => $item["BRBH"],
                "BRKS" => $item["BRKS"],
                "CBZD" => $item["CBZD"],
                "CBZD_ONE" => $item["CBZD_ONE"],
                "CBZD_OTHER" => $item["CBZD_OTHER"],
                "CJKS" => $item["CJKS"],
                "CJSJ" => platformTime($item["CJSJ"]),
                "CWH" => $item["CWH"],
                "HJNR" => $item["HJNR"],
                "JBZD" => $item["JBZD"],
                "JBZDMC" => $item["JBZDMC"],
                "MZ" => $item["MZ"],
                "NL" => $item["NL"],
                "SHRQ" => $item["SHRQ"],
                "SXYS" => $item["SXYS"],
                "WCSJ" => platformTime($item["WCSJ"]),
                "XB" => $item["XB"],
                "XM" => $item["XM"],
                "ZDYJ" => $item["ZDYJ"],
                "ZLJH" => $item["ZLJH"],
                "ZXSJ" => platformTime($item["ZXSJ"]),
                "ZYH" => $item["ZYH"],
                "data_id" => $item["id"],
                "HJNR" => $item["HJNR"],
            ], 'doc_as_upsert' => true];
        }
        if (empty($es_params)) {
            return false;
        }
        $res = app('es')->bulk($es_params);
        if ($res['errors'] == true) {

            foreach ($res['items'] as $item) {
                if (!empty($item['update']['error'])) {
                    DataSyncLog::addData(['zyh' => $zyh, 'content' => '消息队列质控失败：' . $item['update']['error']['reason']]);
                }
            }
        }
        return true;
    }

    /**
     * @param string $zyh
     * @return bool
     * 输血记录
     */
    public static function bllb294_45($zyh = '')
    {
        $res = DB::select("SELECT * from bllb294_45 where ZYH = '{$zyh}'");
        if (empty($res)) {
            return false;
        }
        $res = json_decode(json_encode($res), true);

        // 病例索引
        $es_params = [];
        $index = 'bllb294_45_2023';
        foreach ($res as $item) {
            $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['BLBH']]];
            $es_params['body'][] = ['doc' => [
                "BLBH" => $item["BLBH"],
                "BLMC" => $item["BLMC"],
                "BLZT" => $item["BLZT"],
                "BRKS" => $item["BRKS"],
                "CJKS" => $item["CJKS"],
                "CJSJ" => platformTime($item["CJSJ"]),
                "CWH" => $item["CWH"],
                "CZF" => $item["CZFX"],
                "HDZ" => $item["HDZ"],
                "HJNR" => $item["HJNR"],
                "JCJG" => $item["JCJG"],
                "JCZB" => $item["JCZB"],
                "SXHLXCBPJ" => $item["SXHLXCBPJ"],
                "SXJL" => $item["SXJL"],
                "SXJSSJ" => platformTime($item["SXJSSJ"]),
                "SXKSSJ" => platformTime($item["SXKSSJ"]),
                "SXQYHGTQK" => $item["SXQYHGTQK"],
                "SXYS" => $item["SXYS"],
                "SZZ" => $item["SZZ"],
                "TIWEN" => $item["TIWEN"],
                "WCSJ" => platformTime($item["WCSJ"]),
                "XM" => $item["XM"],
                "YDYA" => $item["YDYA"],
                "ZYH" => $item["ZYH"],
                "data_id" => $item["id"],
            ], 'doc_as_upsert' => true];
        }
        if (empty($es_params)) {
            return false;
        }
        $res = app('es')->bulk($es_params);
        if ($res['errors'] == true) {

            foreach ($res['items'] as $item) {
                if (!empty($item['update']['error'])) {
                    DataSyncLog::addData(['zyh' => $zyh, 'content' => '消息队列质控失败：' . $item['update']['error']['reason']]);
                }
            }
        }
        return true;
    }

    /**
     * @param string $zyh
     * @return bool
     * 手术记录
     */
    public static function bllb303($zyh = '')
    {
        $res = DB::select("SELECT * from bllb303 where ZYH = '{$zyh}'");
        if (empty($res)) {
            return false;
        }
        $res = json_decode(json_encode($res), true);

        // 病例索引
        $es_params = [];
        $index = 'bllb303_2023';
        foreach ($res as $item) {
            $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['BLBH']]];
            $es_params['body'][] = ['doc' => [
                "AAA28" => $item["AAA28"],
                "AAB01" => platformTime($item["AAB01"]),
                "AAC01" => platformTime($item["AAC01"]),
                "BLBH" => $item["BLBH"],
                "BLJC" => $item["BLJC"],
                "BLMC" => $item["BLMC"],
                "BLZT" => $item["BLZT"],
                "BRKS" => $item["BRKS"],
                "CH" => $item["CH"],
                "CJKS" => $item["CJKS"],
                "CJSJ" => platformTime($item["CJSJ"]),
                "HJNR" => $item["HJNR"],
                "HS" => $item["HS"],
                "JLSJ" => platformTime($item["JLSJ"]),
                "MBLB" => $item["MBLB"],
                "MZFS" => $item["MZFS"],
                "MZZ" => $item["MZZ"],
                "NL" => $item["NL"],
                "NSSS" => $item["NSSS"],
                "SHZD" => $item["SHZD"],
                "SQZD" => $item["SQZD"],
                "SSJGJBBSJ" => $item["SSJGJBBSJ"],
                "SSJSSJ" => platformTime($item["SSJSSJ"]),
                "SSKSSJ" => platformTime($item["SSKSSJ"]),
                "SSMC" => $item["SSMC"],
                "SSMC_ONE" => $item["SSMC_ONE"],
                "SSQM" => $item["SSQM"],
                "SSRQ" => platformTime($item["SSRQ"], "Y-m-d"),
                "SSZ" => $item["SSZ"],
                "SSZCX" => $item["SSZCX"],
                "SSZD" => $item["SSZD"],
                "SX" => $item["SX"],
                "SXYS" => $item["SXYS"],
                "SZZD" => $item["SZZD"],
                "SZZD_ONE" => $item["SZZD_ONE"],
                "WCSJ" => platformTime($item["WCSJ"]),
                "XB" => $item["XB"],
                "XGSJ" => platformTime($item["XGSJ"]),
                "YSSS" => $item["YSSS"],
                "ZS" => $item["ZS"],
                "ZYH" => $item["ZYH"],
                "data_id" => $item["BLBH"],
            ], 'doc_as_upsert' => true];
        }
        if (empty($es_params)) {
            return false;
        }
        $res = app('es')->bulk($es_params);
        if ($res['errors'] == true) {

            foreach ($res['items'] as $item) {
                if (!empty($item['update']['error'])) {
                    DataSyncLog::addData(['zyh' => $zyh, 'content' => '消息队列质控失败：' . $item['update']['error']['reason']]);
                }
            }
        }
        return true;
    }

    /**
     * @param string $zyh
     * @return bool
     * 手术记录
     */
    public static function zy_brry($zyh = '')
    {
        $res = DB::select("SELECT * from ZY_BRRY where ZYH = '{$zyh}'");
        if (empty($res)) {
            return false;
        }
        $res = json_decode(json_encode($res), true);

        // 病例索引
        $es_params = [];
        $index = 'zy_brry';
        foreach ($res as $item) {
            $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $item['ZYH']]];
            $es_params['body'][] = ['doc' => [
                "id" => $item["ZYH"],
                "ZYH" => $item["ZYH"],
                "ZY_YQ" => $item["ZY_YQ"],
                "HCSJ" => $item["HCSJ"],
                "AAB01" => platformTime($item["AAB01"]),
                "ZY_KSDM" => $item["ZY_KSDM"],
                "ZY_KSMC" => $item["ZY_KSMC"],
                "ZY_BQDM" => $item["ZY_BQDM"],
                "ZY_BQMC" => $item["ZY_BQMC"],
                "CH" => $item["CH"],
                "ZKKS" => $item["ZKKS"],
                "GCYSDM" => $item["GCYSDM"],
                "GCYSMC" => $item["GCYSMC"],
                "ZLZZDM" => $item["ZLZZDM"],
                "ZLZZMC" => $item["ZLZZMC"],
                "ZZYSDM" => $item["ZZYSDM"],
                "ZZYSMC" => $item["ZZYSMC"],
                "AAC01" => platformTime($item["AAC01"]),
                "CY_YQ" => $item["CY_YQ"],
                "CY_KSDM" => $item["CY_KSDM"],
                "CY_KSMC" => $item["CY_KSMC"],
                "CY_BQDM" => $item["CY_BQDM"],
                "CY_BQMC" => $item["CY_BQMC"],
                "MZHM" => $item["MZHM"],
                "MZYS" => $item["MZYS"],
                "MZYS_MC" => $item["MZYS_MC"],
                "ZRYS" => $item["ZRYS"],
                "ZRYS_MC" => $item["ZRYS_MC"],
                "CYPB" => $item["CYPB"],
                "CYFS" => $item["CYFS"],
                "AAA28" => $item["AAA28"],
                "ZYCS" => $item["ZYCS"],
                "BASYPB" => $item["BASYPB"],
                "home_ysz_score" => $item["home_ysz_score"],
                "home_ysz_score_lv" => $item["home_ysz_score_lv"],
                "score_lv" => $item["score_lv"],
                "review_status" => $item["review_status"],
                "review_content" => $item["review_content"],
                "review_user" => $item["review_user"],
                "home_bmy_score" => $item["home_bmy_score"],
                "home_bmy_score_lv" => $item["home_bmy_score_lv"],
                "BRXM" => $item["BRXM"],
                "BRKS" => $item["BRKS"],
                "BRBQ" => $item["BRBQ"]
            ], 'doc_as_upsert' => true];
        }
        if (empty($es_params)) {
            return false;
        }
        $res = app('es')->bulk($es_params);
        if ($res['errors'] == true) {

            foreach ($res['items'] as $item) {
                if (!empty($item['update']['error'])) {
                    DataSyncLog::addData(['zyh' => $zyh, 'content' => '消息队列质控失败：' . $item['update']['error']['reason']]);
                }
            }
        }
        return true;
    }



    /**
     * @param $BLBH
     * 根据BLBH清空bl01数据
     */
    public static function deleteBl01ByBLBH($blbh)
    {
        $blbh = !is_array($blbh) ? [$blbh] : $blbh;
        if (empty($blbh)) {
            return false;
        }
        EMR_BL_BL01::query()->whereIn('BLBH', $blbh)->delete();
        EMR_BL_BLXG::query()->whereIn('BLBH', $blbh)->delete();
        EMR_BL_BLSY::query()->whereIn('BLBH', $blbh)->delete();


        Bllb292::query()->whereIn('BLBH', $blbh)->delete();
        Bllb1::query()->whereIn('BLBH', $blbh)->delete();
        Bllb294_295::query()->whereIn('BLBH', $blbh)->delete();
        Bllb294_45::query()->whereIn('BLBH', $blbh)->delete();
        Bllb303::query()->whereIn('BLBH', $blbh)->delete();
        Bllb303_303::query()->whereIn('BLBH', $blbh)->delete();
        Bllb288::query()->whereIn('BLBH', $blbh)->delete();

        $esIndex = ["bllb1_2023", "bllb292_2023", "bllb294_295_2023", "bllb294_45_2023", "bllb303_2023", 'bl01_202303', 'blsy_2023'];
        foreach ($esIndex as $index) {
            $params = [
                'index' => $index,
                'body' => [
                    'query' => [
                        'terms' => [
                            'BLBH' => $blbh
                        ]
                    ]
                ]
            ];
            // 先检查数据是否存在，如果存在则删除
            $result = app('es')->search($params);
            if (!empty($result['hits']['hits'])) {
                app('es')->deleteByQuery($params);
            }
        }
    }

    /**
     * @param $zyh
     * @return void
     * 根据住院号清空bl01数据
     */
    public static function deleteBl01ByZyh($zyh = "")
    {
        $emrBl01 = EMR_BL_BL01::query()->where("JZHM", $zyh)->get(["BLBH"])->toArray();
        $blbh = array_column($emrBl01, "BLBH");
        EMR_BL_BL01::query()->where('JZHM', $zyh)->delete();
        EMR_BL_BLXG::query()->whereIn('BLBH', $blbh)->delete();
        EMR_BL_BLSY::query()->whereIn('BLBH', $blbh)->delete();
        Bllb294_45::query()->where('ZYH', $zyh)->delete();
        Bllb303::query()->where('ZYH', $zyh)->delete();
        Bllb292::query()->where('ZYH', $zyh)->delete();
        Bllb1::query()->where('ZYH', $zyh)->delete();
        Bllb294_295::query()->where('ZYH', $zyh)->delete();
        $esIndex = ["bllb1_2023", "bllb292_2023", "bllb294_295_2023", "bllb294_45_2023", "bllb303_2023", 'blsy_2023'];
        foreach ($esIndex as $index) {
            // 删除ES中的数据
            $params = [
                'index' => $index,
                'body' => [
                    'query' => [
                        'term' => [
                            'ZYH' => $zyh
                        ]
                    ]
                ]
            ];
            // 先检查数据是否存在，如果存在则删除
            $result = app('es')->search($params);
            if (!empty($result['hits']['hits'])) {
                app('es')->deleteByQuery($params);
            }
        }
        // 删除ES中的数据
        $params = [
            'index' => 'bl01_202303',
            'body' => [
                'query' => [
                    'term' => [
                        'JZHM' => $zyh
                    ]
                ]
            ]
        ];
        // 先检查数据是否存在，如果存在则删除
        $result = app('es')->search($params);
        if (!empty($result['hits']['hits'])) {
            app('es')->deleteByQuery($params);
        }
    }

    public static function esSaveOmrBl01($blbh = '')
    {
        $omrBl01 = OMR_BL01::query()->where('BLBH', $blbh)->first();
        if (empty($omrBl01)) {
            return false;
        }

        $omrBl01 = $omrBl01->toArray();

        $index = 'omr_bl01_2023';
        $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $blbh]];
        $es_params['body'][] = ['doc' => [
            "BLBH" => $omrBl01['BLBH'] ?? '',
            "BLLB" => $omrBl01['BLLB'] ?? '',
            "BLLX" => $omrBl01['BLLX'] ?? '',
            "BLMC" => $omrBl01['BLMC'] ?? '',
            "BLNR_TXT" => $omrBl01['BLNR_TXT'] ?? '',
            "BLZT" => $omrBl01['BLZT'] ?? '',
            "BRID" => $omrBl01['BRID'] ?? '',
            "BRKS" => $omrBl01['BRKS'] ?? '',
            "CJSJ" => platformTime($omrBl01['CJSJ']),
            "DLJ" => $omrBl01['DLJ'] ?? '',
            "DLLB" => $omrBl01['DLLB'] ?? '',
            "JLSJ" => platformTime($omrBl01['JLSJ']),
            "JZXH" => $omrBl01['JZXH'] ?? '',
            "SFZH" => $omrBl01['SFZH'] ?? '',
            "SXKS" => $omrBl01['SXKS'] ?? '',
            "SXYS" => $omrBl01['SXYS'] ?? '',
            "WCSJ" => platformTime($omrBl01['WCSJ']),
            "cbzd" => $omrBl01['cbzd'] ?? '',
            "data_id" => $omrBl01['id'] ?? '',
            "fzjc" => $omrBl01['fzjc'] ?? '',
            "id" => $omrBl01['id'] ?? '',
            "is_defect" => $omrBl01['is_defect'],
            "jws" => $omrBl01['jws'] ?? '',
            "jzsj" => platformTime($omrBl01['jzsj']),
            "ks" => $omrBl01['ks'] ?? '',
            "lxbxs" => $omrBl01['lxbxs'] ?? '',
            "mzh" => $omrBl01['mzh'] ?? '',
            "nl" => $omrBl01['nl'] ?? '',
            "nl1" => $omrBl01['nl1'] ?? '',
            "tgjc" => $omrBl01['tgjc'] ?? '',
            "tx" => $omrBl01['tx'] ?? '',
            "xb" => $omrBl01['xb'] ?? '',
            "xbs" => $omrBl01['xbs'] ?? '',
            "xm" => $omrBl01['xm'] ?? '',
            "xy" => $omrBl01['xy'] ?? '',
            "xy_json" => $omrBl01['xy_json'] ?? '',
            "zlyj" => $omrBl01['zlyj'] ?? '',
            "zs" => $omrBl01['zs'] ?? '',
            "score" => $omrBl01['score'] ?? '',
            "score_lv" => $omrBl01['score_lv'] ?? '',
        ], 'doc_as_upsert' => true];

        $res = app('es')->bulk($es_params);
        if ($res['errors'] == true) {
            var_dump($res['items']);
        }
    }


    public static function esSaveOmrQuality($blbh = '')
    {
        $omrQuality = OmrQuality::query()->where('BLBH', $blbh)->get()->toArray();
        if (empty($omrQuality)) {
            return false;
        }

        $index = 'omr_quality_2023';
        foreach ($omrQuality as $item) {
            $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $blbh . '_' . $item['rule_id']]];
            $es_params['body'][] = ['doc' => [
                "BLBH" => $item['BLBH'],
                "MED_REC_ID" => $item['JZXH'],
                "BRID" => $item['BRID'],
                "rule_id" => $item['rule_id'],
                "code" => $item['code'],
                "error_field" => $item['error_field'],
                "basis" => $item['basis'],
                "mzh" => $item['mzh'],
                "xm" => $item['xm'],
                "xb" => $item['xb'],
                "nl" => $item['nl'],
                "cbzd" => $item['cbzd'],
                "BRKS" => $item['BRKS'],
                "SFZH" => $item['SFZH'],
                "SXYS" => $item['SXYS'],
                "jzsj" => $item['jzsj'],
                "ks" => $item['ks'],
                "bl_type" => $item['bl_type'],
            ], 'doc_as_upsert' => true];

            $res = app('es')->bulk($es_params);
            if ($res['errors'] == true) {
                foreach ($res['items'] as $item) {
                    if (!empty($item['update']['error'])) {
                        DataSyncLog::addData(['zyh' => $zyh, 'content' => '消息队列质控失败：' . $item['update']['error']['reason']]);
                    }
                }
            }
        }
    }

    public static function esSaveIndicator($zyh = '', $data=[])
    {
        
        $index = 'indicator';
        $es_params['body'][] = ['update' => ['_index' => $index, '_id' => $zyh]];
        $es_params['body'][] = ['doc' => $data, 'doc_as_upsert' => true];
        $res = app('es')->bulk($es_params);
        if ($res['errors'] == true) {
            var_dump($res['items']);
        }
    }
}
