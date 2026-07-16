<?php

namespace App\Services\MysqlDataSync\sanyuan;

use App\Console\Commands\PatientYS_ZY_HZYJ;
use App\Console\Commands\PatientZY_HCMX;
use App\Model\DataSyncLog;
use App\Model\Icu;
use App\Model\PACS;
use App\Model\Yzb;
use Carbon\Carbon;
use App\Model\SSSQ;
use App\Model\Staff;
use App\Model\ZY_SS;
use App\Model\SM_SSAP;
use App\Model\ZY_BRRY;
use App\Model\OMR_BL01;
use App\Model\PatientAdd;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLSY;
use App\Model\EMR_BL_BLXG;
use App\Model\FeeDetailed;
use App\Model\PatientInfo;
use App\Model\PatientInfoTarget;
use App\Model\RuleWordMap;
use App\Model\MainDiagnosis;
use App\Model\MainOperation;
use App\Model\OtherDiagnosis;
use App\Model\PatientCostInfo;
use App\Model\PatientWorkInfo;
use App\Model\PatientOtherInfo;
use App\Services\EsSaveService;
use Illuminate\Console\Command;
use App\Model\PatientDoctorInfo;
use App\Model\QualitySendMsgLog;
use App\Model\PatientAddressInfo;
use App\Model\PatientMedicalInfo;
use App\Model\SecondaryOperation;
use App\Model\PatientContactsInfo;
use App\Model\PatientHospitalInfo;
use App\Model\MS_BRDA;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Console\Commands\WjzCommand;
use App\Services\BlDataFormatService;
use App\Console\Commands\ShizhongDataSync;
use App\Model\EMR_BL01_DYJL;
use App\Model\OmrBlsy;
use PhpParser\Node\Stmt\Else_;

class HomeData
{

    public static $con;
    public static $con2;
    public static $con3;

    public function getConnect()
    {
        if (self::$con) {
            return self::$con;
        }
        $username = env('ORACLE_USERNAME', 'zdyh');
        $password = env('ORACLE_PASSWORD', 'zdyh');
        $connection = env('ORACLE_HOST', '172.16.9.8');
        $port = env('ORACLE_PORT', '1521');
        $tns = env('ORACLE_TNS', 'his');
        $con = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
        self::$con = $con;
    }

    public function getConnect2()
    {
        if (self::$con2) {
            return self::$con2;
        }
        $username = env('ORACLE_USERNAME', 'btf');
        $password = env('ORACLE_PASSWORD', 'btf');
        $connection = '192.168.52.98';
        $port = env('ORACLE_PORT', '1521');
        $tns = 'storcl';
        self::$con2 = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
    }

    // public function patientInfoV2($zyh = "") {
    //     SELECT
    //         fp.NUM_VISMED AS ZYH,
    //         fp.CD_MEDCASE AS AAA28,
    //         fp.NA_ORG AS ZA03,
    //         fp.CD_ORG AS UNT_ID,
    //         fp.SD_MEDPAY_CD AS AAA26C,
    //         fp.CARD_HEAL AS AAA27,
    //         fp.TS_IP AS AAA29,
    //         fp.NA_PI AS AAA01,
    //         fp.SD_SEX_CD AS AAA02C,
    //         fp.BOD AS AAA03,
    //         fp.AGE AS AAA04,
    //         fp.AGE_D AS AAA40,
    //         fp.SD_CONTRY_CD AS AAA05C,
    //         fp.NA_AREA_BIRTH_PROV AS AAA09,
    //         fp.NA_AREA_BIRTH_CITY AS AAA10,
    //         fp.NA_AREA_BIRTH_DIST AS AAA11,
    //         fp.DES_BIRTH AS CSD,
    //         fp.NA_AREA_ORIGIIN_PROV AS AAA43,
    //         --fp.NA_AREA_ORIGIIN_CITY AS AAA44,
    //         fp.SD_NATION_CD AS AAA06C,
    //         fp.SD_IDENTITY_TYPE AS ZJLB,
    //         fp.NO_CARD AS AAA07,
    //         fp.SD_OCCU_CD AS AAA18C,
    //         fp.SD_MARRY_CD AS AAA08C,
    //         fp.NA_AREA_REGION_PROV AS AAA48,
    //         fp.NA_AREA_REGION_CITY AS AAA49,
    //         fp.NA_AREA_REGION_DIST AS AAA50,
    //         fp.DES_REGION AS AAA15,
    //         fp.PHONE_REGION AS AAA51,
    //         fp.POST_REGION AS AAA17C,
    //         fp.NA_AREA_REGI_PROV AS AAA45,
    //         fp.NA_AREA_REGI_CITY AS AAA46,
    //         fp.NA_AREA_REGI_DIST AS AAA47,
    //         fp.DES_REGI AS AAA12,
    //         fp.POST_REGI AS AAA13C,
    //         (fp.NA_WORKUNIT || fp.ADDR_WORKUNIT) AS AAA19,
    //         fp.PHONE_WORKUNIT AS AAA20,
    //         fp.POST_WORKUNIT AS AAA21C,
    //         fp.NA_PICONT AS AAA22,
    //         fp.SD_PICONTTP AS AAA23C,
    //         fp.ADDR_PICONT AS AAA24,
    //         fp.PHONE_PICONT AS AAA25,
    //         fp.SD_ADMI_ROUTE_CD AS AAB06C,
    //         fp.BABY_IN_WEIGHT AS AAA42,
    //         fp.BABY_BIRTH_WEIGHT AS AEN01,
    //         fp.DT_ADMIT AS AAB01,
    //         fp.NA_DEP_ADMIT_CLINIC AS AAB02C,
    //         fp.ID_DEP_ADMIT_CLINIC AS AAB11C,
    //         fp.NA_DEP_ADMIT_WARD AS AAB11N,
    //         fp.DES_DEPS_TRANS AS AAD01C,
    //         fp.DT_DISCHARGE AS AAC01,
    //         fp.NA_DEP_DISCARGE_CLINIC AS AAC02C,
    //         fp.ID_DEP_DISCARGE_CLINIC AS AAC11C,
    //         fp.NA_DEP_DISCHARGE_WARD AS AAC11N,
    //         fp.DAYS_IP AS AAC04,
    //         fp.CD_DIE_OP AS ABA01C,
    //         fp.NA_DIE_OP AS ABA01N,
    //         fp.RES_HARPOI AS WBYY,
    //         fp.CD_HARPOI AS H23,
    //         fp.CD_DIE_HARPOI AS ABF01C,
    //         fp.NA_DIE_PATDIA AS ABF01N,
    //         fp.CD_PAT AS ABF04,
    //         fp.FG_IRR_MED AS AEB02C,
    //         fp.DES_IRRD_MED AS AEB01,
    //         fp.FG_AUT AS AEI01C,
    //         fp.SD_TYPE_ABO_CD AS AEG01C,
    //         fp.SD_TYPE_RH_CD AS AEG02C,
    //         enc.SPECIAL_CARE AS TJHL,
    //         enc.ONE_CARE AS YJHL,
    //         enc.TWO_CARE AS EJHL,
    //         enc.THREE_CARE AS SJHL,
    //         fp.ID_DEP_HEAD AS AEE01_CODE,
    //         fp.NA_DEP_HEAD AS AEE01,
    //         fp.ID_CHIE_PHY AS AEE02_CODE,
    //         fp.NA_CHIE_PHY AS AEE02,
    //         fp.ID_DOC_ATTE AS AEE03_CODE,
    //         fp.NA_DOC_ATTE AS AEE03,
    //         fp.ID_DOC_AES AS AEE04_CODE,
    //         fp.NA_DOC_AES AS AEE04,
    //         fp.ID_DOC_ADVSTU AS AEE05_CODE,
    //         fp.NA_DOC_ADVSTU AS AEE05,
    //         fp.ID_DOC_INT AS AEE07_CODE,
    //         fp.NA_DOC_INT AS AEE07,
    //         fp.ID_NUR_DUT AS AEE10_CODE,
    //         fp.NA_NUR_DUT AS AEE10,
    //         fp.ID_DOC_QU_REC_MED AS AED02_CODE,
    //         fp.NA_DOC_QU_REC_MED AS AED02,
    //         fp.ID_NUR_QU_REC_MED AS AED03_CODE,
    //         fp.NA_NUR_QU_REC_MED AS AED03,
    //         fp.SD_QU_REC_MED_CD AS AED01C,
    //         fp.DT_QU_REC_MED AS AED04,
    //         enc.DAY_OPERATE AS SFRJSS,
    //         enc.USE_RESPIRATOR AS AEL01,
    //         fp.QUAN_CRIIN_COMA_BEF_DAY AS AEJ01,
    //         fp.QUAN_CRIIN_COMA_BEF_HOUR AS AEJ02,
    //         fp.QUAN_CRIIN_COMA_BEF_MIN AS AEJ03,
    //         fp.QUAN_CRIIN_COMA_AFT_DAY AS AEJ04,
    //         fp.QUAN_CRIIN_COMA_AFT_HOUR AS AEJ05,
    //         fp.QUAN_CRIIN_COMA_AFT_MIN AS AEJ06,
    //         fp.SD_LEAVHOS AS AEM01C,
    //         (fp.NA_ORG_REC || fp.NA_DEPT_REC) AS YZZY_YLJG,
    //         fp.FG_AGA_ADMIT AS AEM03C,
    //         fp.PUR_AGA_ADMIT AS AEM04,
    //         cost.TOTAL_COST AS ADA01,
    //         cost.SELF_COST AS ADA0101,
    //         enc.SD_MADIA_BASIS AS ZZLB,
    //         enc.CLINICAL_PATHWAY AS WCQK,
    //         enc.FG_CLINICAL_MANAGE AS SSLCLJ,
    //         enc.FG_CHANGE AS BYQK,
    //         enc.OPERATE_FRONT AS SQYSH,
    //         enc.CLI_MEC AS LCYBL,
    //         cost.SERVICE_COST AS D11,
    //         cost.TREAT_COST AS D12,
    //         cost.NURSE_COST AS D13,
    //         cost.TOTAL_OTHER_COST AS D14,
    //         cost.PATH_DIE_COST AS D15,
    //         cost.LAB_DIE_COST AS D16,
    //         cost.VIDEO_DIE_COST AS D17,
    //         cost.BED_DIE_COST AS D18,
    //         cost.OTHER_OPER_COST AS D19,
    //         cost.BED_PHY_COST AS D19X01,
    //         cost.OPER_TREAT_COST AS D20,
    //         cost.ANA_COST AS D20X01,
    //         cost.OPER_COST AS D20X02,
    //         cost.FIT_COST AS D21,
    //         cost.CHI_TREAT_COST AS D22,
    //         cost.WEST_MEDICAL_COST AS D23,
    //         cost.ANTI_BIO_COST AS D23X01,
    //         cost.CHI_MEDICAL_COST AS D24,
    //         cost.CHI_HERBAL_COST AS D25,
    //         cost.BLOOD_COST AS D26,
    //         cost.BLOOD_BR_COST AS D27,
    //         cost.BALL_BR_COST AS D28,
    //         cost.BLOOD_FACTOR_COST AS D29,
    //         cost.CELL_FACTOR_COST AS D30,
    //         cost.CHECK_MATER_COST AS D31,
    //         cost.TREAT_MATER_COST AS D32,
    //         cost.OPER_MATER_COST AS D33,
    //         cost.OTHER_COST AS D34
    //     FROM
    //         WHIS_EMR.HI_VIEW_MED_REC_FP fp
    //         LEFT JOIN WHIS_EMR.HI_VIEW_MED_REC_FP_ENC enc
    //             ON fp.NUM_VISMED = enc.NUM_VISMED
    //         LEFT JOIN WHIS_EMR.HI_VIEW_MED_REC_FP_COST cost
    //             ON fp.ID_MEDRECDOC = cost.ID_MEDRECDOC
    //     ORDER BY fp.NUM_VISMED;
    // }


    /**
     * 获取用户主信息
     * @return array
     */
    public function OMRBL01($startTime = "")
    {
        $this->getConnect();
        $this->getConnect2();
        $timeIndex = Carbon::now()->subDays(1)->startOfDay()->timestamp;
        if (!empty($startTime)) {
            $startTime = strtotime($startTime);
        } else {
            $startTime = $timeIndex;
        }

        while (true) {
            if ($startTime >= time()) {
                break;
            }

            $startTimeStr = date("Ymd", $startTime);
            echo '时间：' . $startTimeStr . PHP_EOL;
            $startTime += 86400;
            $sql = "SELECT ID_MEDRECDOC as BLBH,NUM_VISMED AS JZXH,ID_DS as BLLB,NA_MED as BLMC,ID_DS as MBLB,CD_CREATE as SXYS,CD_DEPT as SXKS,FG_ACTIVE as BLZT,HTML_TEXT as BLNR_TXT,ID_ARCHIVES as mzh,NAME as xm,ID_PATIENT as BRID,to_char(CREATE_TIME,'yyyy-mm-dd hh24:mi:ss') AS JLSJ,to_char(UPDATE_TIME,'yyyy-mm-dd hh24:mi:ss') AS WCSJ from WHIS_EMR.HI_VIEW_REC_MZ_HTML WHERE UPDATE_TIME BETWEEN TO_DATE('" . $startTimeStr . "000000', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('" . $startTimeStr . "235959', 'yyyy-MM-dd HH24:mi:ss')";

            $data = oci_parse(self::$con2, $sql);
            oci_execute($data, OCI_DEFAULT);
            $result = [];
            while (@$row = oci_fetch_assoc($data)) {
                $result[] = $row;
            }
            echo '处理数据：' . count($result) . PHP_EOL;

            $BRID = array_column($result, 'BRID');
            $msBrdaData = MS_BRDA::query()->whereIn("BRID", $BRID)->get(['BRID', 'SFZH'])->pluck('SFZH', 'BRID')->toArray();
            foreach ($result as $item) {
                if (!empty($item['BLMC']) && mb_strpos($item['BLMC'], '手术知情同意书') !== false) {
                    continue;
                }

                $msBrdaDataItem = !empty($msBrdaData[$item['BRID']]) ? $msBrdaData[$item['BRID']] : [];
                //身份证号
                $item['SFZH'] = '';
                $SFZH = !empty($msBrdaDataItem['SFZH']) ? $msBrdaDataItem['SFZH'] : '';
                if (mb_strlen($SFZH) > 14) {
                    $SFZH = desensitize($SFZH, 0, 6, '*');
                    $SFZH = desensitize($SFZH, 14, 2, '*');
                    $SFZH = desensitize($SFZH, 17, 1, '*');
                }
                $item['SFZH'] = $SFZH;
                $item['BLNR_TXT'] = !empty($item['BLNR_TXT']) && !empty($item['BLNR_TXT']->size()) ? $item['BLNR_TXT']->load() : '';
                OMR_BL01::query()->updateOrInsert(["BLBH" => $item["BLBH"]], $item);

                $sql = "SELECT  JLXH,BLBH,SYYS,to_char(SYSJ,'yyyy-mm-dd hh24:mi:ss') as SYSJTIME,to_char(JLSJ,'yyyy-mm-dd hh24:mi:ss') as JLSJTIME,FG_ACTIVE FROM WHIS_EMR.EMR_BL_BLSY WHERE BLBH = '" . $item['BLBH'] . "'";
                $resultSet = oci_parse(self::$con2, $sql);
                oci_execute($resultSet, OCI_DEFAULT);
                $data = [];
                while ($row = oci_fetch_assoc($resultSet)) {
                    $data[] = $row;
                }
                if (!empty($data)) {
                    foreach ($data as $v) {
                        $insertData = [
                            'JLXH' => $v['JLXH'],
                            'BLBH' => $v['BLBH'],
                            'SYYS' => $v['SYYS'],
                            'SYSJ' => $v['SYSJTIME'],
                            'JLSJ' => $v['JLSJTIME'],
                        ];
                        OmrBlsy::query()->updateOrInsert(['BLBH' => $insertData['BLBH']], $insertData);
                    }
                }
            }

            $sql = "SELECT BLBH,JZXH,BLLB,BLMC,MBLB,SXYS,SXKS,BLZT,BRID,to_char(JLSJ,'yyyy-mm-dd hh24:mi:ss') AS JLSJ,to_char(WCSJ,'yyyy-mm-dd hh24:mi:ss') AS WCSJ from PORTAL_HIS.OMR_BL01 WHERE CJSJ BETWEEN TO_DATE('" . $startTimeStr . "000000', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('" . $startTimeStr . "235959', 'yyyy-MM-dd HH24:mi:ss')";
            $data = oci_parse(self::$con, $sql);
            oci_execute($data, OCI_DEFAULT);
            $result = [];
            while (@$row = oci_fetch_assoc($data)) {
                $result[] = $row;
            }
            echo 'BL01处理数据：' . count($result) . PHP_EOL;

            $BRID = array_column($result, 'BRID');
            $msBrdaData = MS_BRDA::query()->whereIn("BRID", $BRID)->get(['BRID', 'SFZH'])->pluck('SFZH', 'BRID')->toArray();
            $syncBlbh = [];
            foreach ($result as $item) {
                if (!empty($item['BLMC']) && mb_strpos($item['BLMC'], '手术知情同意书') !== false) {
                    continue;
                }

                $msBrdaDataItem = !empty($msBrdaData[$item['BRID']]) ? $msBrdaData[$item['BRID']] : [];
                //身份证号
                $item['SFZH'] = '';
                $SFZH = !empty($msBrdaDataItem['SFZH']) ? $msBrdaDataItem['SFZH'] : '';
                if (mb_strlen($SFZH) > 14) {
                    $SFZH = desensitize($SFZH, 0, 6, '*');
                    $SFZH = desensitize($SFZH, 14, 2, '*');
                    $SFZH = desensitize($SFZH, 17, 1, '*');
                }
                $item['SFZH'] = $SFZH;
                OMR_BL01::query()->updateOrInsert(["BLBH" => $item["BLBH"]], $item);
                $syncBlbh[] = $item['BLBH'];
            }

            $blbh = $syncBlbh;
            $chunkBlbh = array_chunk($blbh, 100);
            foreach ($chunkBlbh as $blbh) {
                $sql = "SELECT BLBH,BLNR_TXT from PORTAL_HIS.OMR_BL02 WHERE BLBH IN (" . implode(",", $blbh) . ")";
                $data = oci_parse(self::$con, $sql);
                oci_execute($data, OCI_DEFAULT);
                $result = [];
                while (@$row = oci_fetch_assoc($data)) {
                    $result[] = $row;
                }
                foreach ($result as $item) {
                    OMR_BL01::query()->where("BLBH", $item["BLBH"])->update(["BLNR_TXT" => $item['BLNR_TXT']]);
                }
            }
        }
    }

    /**
     * @param string $startTime
     * 同步pacs数据
     */
    public function cleanPacsZYH($startTime = 0, $BAH = '')
    {
        if (!empty($BAH)) {
            // 只按BAH查询
            echo '按BAH处理：' . $BAH . PHP_EOL;
            $result = PACS::query()
                ->where('BAH', $BAH)
                ->where(function ($query) {
                    $query->where('ZYH', '')
                        ->orWhereNull('ZYH');
                })
                ->get(['id', 'ZYH', 'KDSJ', 'BAH', 'StudyUid', 'ZYCS'])
                ->toArray();

            foreach ($result as $item) {
                if ($item['ZYH'] == 1) {
                    $brry = ZY_BRRY::query()->where("AAA28", $item["BAH"])
                        ->where('AAB01', '<=', $item['KDSJ'])
                        ->where('AAC01', '>=', $item['KDSJ'])
                        ->first(["ZYH"]);
                    if (!$brry) {
                        $brry = ZY_BRRY::query()->where("AAA28", $item["BAH"])
                            ->where('AAB01', '<=', $item['KDSJ'])
                            ->where('AAC01', '=', "")
                            ->first(["ZYH"]);
                    }
                    if ($brry) {
                        PACS::query()->where('id', $item['id'])->update(['ZYH' => $brry->ZYH]);
                        continue;
                    }
                }

                if ($item['ZYH'] && $item['ZYH'] != 1) {
                    continue;
                }
                $ZYH = "";
                $brry = "";
                if (!empty($item["ZYCS"])) {
                    $brry = ZY_BRRY::query()->where(["AAA28" => $item["BAH"], "ZYCS" => $item["ZYCS"]])->first(["ZYH"]);
                }
                if (empty($brry) && !empty($item['KDSJ'])) {
                    $brry = ZY_BRRY::query()
                        ->where("AAA28", $item["BAH"])
                        ->where('AAB01', '<', $item['KDSJ'])
                        ->where('AAC01', '>', $item['KDSJ'])
                        ->first(["ZYH"]);
                    if (empty($brry)) {
                        $brry = ZY_BRRY::query()
                            ->where("AAA28", $item["BAH"])
                            ->where('AAB01', '<=', $item['KDSJ'])
                            ->where('AAC01', '=', "")
                            ->first(["ZYH"]);
                    }
                }
                if ($brry) {
                    $ZYH = $brry->ZYH;
                }
                if (empty($ZYH)) {
                    continue;
                }
                PACS::query()->where('id', $item['id'])->update(['ZYH' => $ZYH]);
            }
        } else {
            // 采用原来的逻辑按时间循环处理
            while (true) {
                if ($startTime >= time()) {
                    break;
                }

                $startTimeStr = date("Y-m-d", $startTime);
                echo '时间：' . $startTimeStr . PHP_EOL;
                $startTime += 86400;
                $result = PACS::query()
                    ->whereBetween('BGSJ', [$startTimeStr . " 00:00:00", $startTimeStr . " 23:59:59"])
                    ->where('BAH', '!=', '')
                    ->where(function ($query) {
                        $query->where('ZYH', '')
                            ->orWhereNull('ZYH');
                    })
                    ->get(['id', 'ZYH', 'KDSJ', 'BAH', 'StudyUid', 'ZYCS'])
                    ->toArray();

                foreach ($result as $item) {
                    if ($item['ZYH'] == 1) {
                        $brry = ZY_BRRY::query()->where("AAA28", $item["BAH"])
                            ->where('AAB01', '<=', $item['KDSJ'])
                            ->where('AAC01', '>=', $item['KDSJ'])
                            ->first(["ZYH"]);
                        if (!$brry) {
                            $brry = ZY_BRRY::query()->where("AAA28", $item["BAH"])
                                ->where('AAB01', '<=', $item['KDSJ'])
                                ->where('AAC01', '=', "")
                                ->first(["ZYH"]);
                        }
                        if ($brry) {
                            PACS::query()->where('id', $item['id'])->update(['ZYH' => $brry->ZYH]);
                            continue;
                        }
                    }

                    if ($item['ZYH'] && $item['ZYH'] != 1) {
                        continue;
                    }
                    $ZYH = "";
                    $brry = "";
                    if (!empty($item["ZYCS"])) {
                        $brry = ZY_BRRY::query()->where(["AAA28" => $item["BAH"], "ZYCS" => $item["ZYCS"]])->first(["ZYH"]);
                    }
                    if (empty($brry) && !empty($item['KDSJ'])) {
                        $brry = ZY_BRRY::query()
                            ->where("AAA28", $item["BAH"])
                            ->where('AAB01', '<', $item['KDSJ'])
                            ->where('AAC01', '>', $item['KDSJ'])
                            ->first(["ZYH"]);
                        if (empty($brry)) {
                            $brry = ZY_BRRY::query()
                                ->where("AAA28", $item["BAH"])
                                ->where('AAB01', '<=', $item['KDSJ'])
                                ->where('AAC01', '=', "")
                                ->first(["ZYH"]);
                        }
                    }
                    if ($brry) {
                        $ZYH = $brry->ZYH;
                    }
                    if (empty($ZYH)) {
                        continue;
                    }
                    PACS::query()->where('id', $item['id'])->update(['ZYH' => $ZYH]);
                }
            }
        }
    }

    /**
     * @param string $startTime
     * 清洗patient_info表中的是否编目字段
     */
    public function isCATA($startTime = '')
    {
        $this->getConnect();
        $timeIndex = Carbon::now()->subDays(15)->startOfDay()->timestamp;
        if (!empty($startTime)) {
            $startTime = strtotime($startTime);
        } else {
            $startTime = $timeIndex;
        }

        while (true) {
            // 如果数据同步到最新时间，则重新从本年3月1日 0点0分0秒到现在
            if ($startTime >= time()) {
                $str = date("Y-m-d H:i:s", time()) . "完成一轮数据同步\n";
                $startTime = Carbon::now()->subDays(15)->startOfDay()->timestamp;
                echo $str;
            }

            $startTimeStr = date("Ymd", $startTime);
            echo '时间：' . $startTimeStr . PHP_EOL;
            $startTime += 86400;
            $sql = "SELECT MED_REC_ID FROM PORTAL_HIS.INIT_MED_REC_MAIN WHERE AAC01 BETWEEN TO_DATE('" . $startTimeStr . "000000', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('" . $startTimeStr . "235959', 'yyyy-MM-dd HH24:mi:ss')";

            $data = oci_parse(self::$con, $sql);
            oci_execute($data, OCI_DEFAULT);
            $result = [];
            while (@$row = oci_fetch_assoc($data)) {
                foreach ($row as &$value) {
                    $source_encoding = mb_detect_encoding($value);
                    $value = iconv($source_encoding, 'UTF-8', $value);
                }
                $result[] = $row;
            }
            echo '处理数据：' . count($result) . PHP_EOL;

            $zyh = array_column($result, "MED_REC_ID");
            PatientInfo::query()->whereIn("MED_REC_ID", $zyh)->update(["IS_CATA" => 1]);
        }
    }

    /**
     * @param string $startTime
     * @param string $endTime
     * @param string $zyh
     * 同步指定时间范围内的数据
     */
    public function index($startTime = '', $endTime = '', $zyh = "")
    {
        $this->getConnect();

        $timeIndex = Carbon::now()->subDays(15)->startOfDay()->timestamp;
        if (!empty($startTime) && !empty($endTime)) {
            $startTime = strtotime($startTime);
        } else {
            $startTime = $timeIndex;
        }

        while (true) {
            // 如果数据同步到最新时间，则重新从本年3月1日 0点0分0秒到现在
            if ($startTime >= time()) {
                $str = date("Y-m-d H:i:s", time()) . "完成一轮数据同步\n";
                $startTime = Carbon::now()->subDays(15)->startOfDay()->timestamp;
                echo $str;
            }
            echo date("Y-m-d H:i:s", $startTime) . PHP_EOL;
            // 获取用户信息
            $this->getData($zyh, ["bl01"], "", $startTime);
            $startTime += 86400;
        }
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function getData($zyh = "", $type = [], $blbh = "", $startTime = "", $isRecordLog = 0)
    {
        // 如果数据库链接字段为初始化则重新链接
        if (!self::$con) {
            $this->getConnect();
        }
        if (!self::$con2) {
            $this->getConnect2();
        }
        if ($zyh) {
            $szds = new ShizhongDataSync();
            $szds->ZY_BRRY($zyh, 0, 0, 0);
        }

        // 获取用户信息
        $patientData = $this->getPatientInfo($zyh, $startTime);
        if (!empty($patientData)) {
            $this->addPatientInfo($patientData[0]);
        }

        try {
            // 医嘱
            if (empty($type) || in_array('yzb', $type)) {
                $yzbData = $this->getYzb($zyh);
                if (!empty($yzbData)) {
                    $this->addYzb($yzbData);
                }
            }

            // 费用明细
            if (empty($type) || in_array('fee_detailed', $type)) {
                $feeDetailedData = $this->getFeeDetailed($zyh);
                if (!empty($feeDetailedData)) {
                    $this->addFeeDetailed($feeDetailedData);
                }
            }

            // 医生签名
            if (empty($type) || in_array('bl01', $type)) {
                $this->addBLSY($zyh, $blbh, $isRecordLog);
            }

            if (empty($type) || in_array('bl01New', $type)) {
                //            $this->bl01New($zyh);
            }

            // 护士分床时间
            if (empty($type) || in_array('zy_hcmx', $type)) {
                //            $this->ZY_HCMX($zyh);
            }

            // 会诊信息
            if (empty($type) || in_array('ys_zy_hzyj', $type)) {
                $this->YS_ZY_HZYJ($zyh);
            }

            // 检查
            if (empty($type) || in_array('v_jmgs_testresult', $type)) {
                $this->V_JMGS_TESTRESULT($zyh);
            }

            // 检验
            if (empty($type) || in_array('v_jmgs_ymresult', $type)) {
                $this->V_JMGS_YMresult($zyh);
            }

            // 麻醉记录
            if (empty($type) || in_array('mzjl', $type)) {
                $this->mzjl($zyh);
            }

            if (empty($type) || in_array('pacs', $type)) {
                $this->pacs($zyh);
            }

            // 首麻
            if (empty($type) || in_array('sm', $type)) {
                $this->SM_SSAP($zyh);
            }

            // 输血
            if (empty($type) || in_array('shuxie', $type)) {
                $this->BLOOD_BLZK($zyh);
            }
        } catch (\Throwable $e) {
            echo $e->getMessage() . '，所在行：' . $e->getLine() . PHP_EOL;
        }

        return true;
    }

    public function getBldata($zyh = "", $type = [], $blbh = "", $startTime = "", $cfjd = '', $qmys = '', $qmrq = '')
    {
        // 如果数据库链接字段为初始化则重新链接
        if (!self::$con) {
            $this->getConnect();
        }
        if (!self::$con2) {
            $this->getConnect2();
        }
        if ($zyh) {
            $szds = new ShizhongDataSync();
            $szds->ZY_BRRY($zyh, 0, 0, 0);
        }

        if ($startTime) {
            echo $zyh . PHP_EOL;
        }
        // 医嘱
        if (empty($type) || in_array('yzb', $type)) {
            $yzbData = $this->getYzb($zyh);
            if (!empty($yzbData)) {
                $this->addYzb($yzbData);
            }
        }

        // 费用明细
        if (empty($type) || in_array('fee_detailed', $type)) {
            $feeDetailedData = $this->getFeeDetailed($zyh);
            if (!empty($feeDetailedData)) {
                $this->addFeeDetailed($feeDetailedData);
            }
        }

        // 医生签名
        if (empty($type) || in_array('bl01', $type)) {
            $this->addBLSY($zyh, $blbh, 1, $cfjd, $qmys, $qmrq);
        }

        if (empty($type) || in_array('bl01New', $type)) {
            //            $this->bl01New($zyh);
        }

        // 护士分床时间
        if (empty($type) || in_array('zy_hcmx', $type)) {
            //            $this->ZY_HCMX($zyh);
        }

        // 会诊信息
        if (empty($type) || in_array('ys_zy_hzyj', $type)) {
            $this->YS_ZY_HZYJ($zyh);
        }

        // 检查
        if (empty($type) || in_array('v_jmgs_testresult', $type)) {
            $this->V_JMGS_TESTRESULT($zyh);
        }

        // 检验
        if (empty($type) || in_array('v_jmgs_ymresult', $type)) {
            $this->V_JMGS_YMresult($zyh);
        }

        $this->SM_SSAP($zyh);

        // 输血
        if (empty($type) || in_array('shuxie', $type)) {
            $this->BLOOD_BLZK($zyh);
        }

        // 危急值
        if (empty($type) || in_array('wjz', $type)) {
            $wjzCommand = new WjzCommand();
            $wjzData = $wjzCommand->getWjzData("", "", $zyh);
            $wjzCommand->addWjzData($wjzData, "", "", $zyh);
        }

        return true;
    }

    public function addWjz($zyh = "")
    {
        if(!$zyh){
            return false;
        }
        $wjzCommand = new WjzCommand();
        $wjzData = $wjzCommand->getWjzData("", "", $zyh);
        if(!empty($wjzData)){
            $wjzCommand->addWjzData($wjzData, "", "", $zyh);
        }
    }

    // 输血
    public function BLOOD_BLZK($zyh = "")
    {

        //使用laravel自带的sqlsrv方法连接
        $connectionOptions = [
            'Database' => env("SS_SQLSRV_DATABASE"),
            'UID' => env("SS_SQLSRV_USERNAME"),
            'PWD' => env("SS_SQLSRV_PASSWORD"),
            'LoginTimeout' => 10,
            'TrustServerCertificate' => 1,
            'Encrypt' => 1
        ];

        $connection = sqlsrv_connect(env("SS_SQLSRV_HOST"), $connectionOptions);
        if ($connection) {

            // 使用直接查询获取数据
            $sql = "SELECT * FROM BLOOD_BLZK WHERE ZYH='{$zyh}'";
            $stmt = sqlsrv_query($connection, $sql);
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                foreach ($errors as $error) {
                    return false;
                }
            } else {

                // 获取所有数据，并计算每列的最大宽度
                $rows = [];
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $formattedRow = [];
                    foreach ($row as $key => $value) {
                        if ($value instanceof \DateTime) {
                            $formattedValue = $value->format('Y-m-d H:i:s');
                        } else {
                            $formattedValue = $value === null ? 'NULL' : (string)$value;
                        }


                        $formattedRow[$key] = $formattedValue;
                    }
                    $rows[] = $formattedRow;
                }
                // 打印数据行
                foreach ($rows as $row) {
                    ZY_SS::query()->updateOrInsert(["SXXH" => $row["SXXH"]], $row);
                }
            }

            // 释放资源
            sqlsrv_free_stmt($stmt);
            sqlsrv_close($connection);
        } else {
            $errors = sqlsrv_errors();
            if (is_array($errors)) {
                foreach ($errors as $error) {
                    return false;
                }
            } else {
                return false;
            }
        }
    }

    public function addShuxue($zyh = "")
    {

        //使用laravel自带的sqlsrv方法连接
        $connectionOptions = [
            'Database' => env("SS_SQLSRV_DATABASE"),
            'UID' => env("SS_SQLSRV_USERNAME"),
            'PWD' => env("SS_SQLSRV_PASSWORD"),
            'LoginTimeout' => 10,
            'TrustServerCertificate' => 1,
            'Encrypt' => 1
        ];

        $connection = sqlsrv_connect(env("SS_SQLSRV_HOST"), $connectionOptions);
        if ($connection) {

            // 使用直接查询获取数据
            $sql = "SELECT * FROM BLOOD_BLZK WHERE ZYH='{$zyh}'";
            $stmt = sqlsrv_query($connection, $sql);
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                foreach ($errors as $error) {
                    return false;
                }
            } else {

                // 获取所有数据，并计算每列的最大宽度
                $rows = [];
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $formattedRow = [];
                    foreach ($row as $key => $value) {
                        if ($value instanceof \DateTime) {
                            $formattedValue = $value->format('Y-m-d H:i:s');
                        } else {
                            $formattedValue = $value === null ? null : (string)$value;
                        }


                        $formattedRow[$key] = $formattedValue;
                    }
                    $rows[] = $formattedRow;
                }
                // 打印数据行
                foreach ($rows as $row) {
                    ZY_SS::query()->updateOrInsert(["SXXH" => $row["SXXH"]], $row);
                }
            }

            // 释放资源
            sqlsrv_free_stmt($stmt);
            sqlsrv_close($connection);
        } else {
            $errors = sqlsrv_errors();
            if (is_array($errors)) {
                foreach ($errors as $error) {
                    return false;
                }
            } else {
                return false;
            }
        }
    }

    public function SM_SSAP($zyh = "")
    {

        $staff = Staff::query()->get()->toArray();
        $staff = array_column($staff, "code", "YGBH");
        $brry = ZY_BRRY::query()->where("ZYH", $zyh)->first();
        if (empty($brry)) {
            return false;
        }
        $brry = $brry->toArray();

        //使用laravel自带的sqlsrv方法连接
        $connectionOptions = [
            'Database' => env("SQLSRV_DATABASE"),
            'UID' => env("SQLSRV_USERNAME"),
            'PWD' => env("SQLSRV_PASSWORD"),
            'LoginTimeout' => 10,
            'TrustServerCertificate' => 1,
            'Encrypt' => 1
        ];

        $connection = sqlsrv_connect(env("SQLSRV_HOST"), $connectionOptions);
        if ($connection) {

            // 使用直接查询获取数据
            $sql = "SELECT * FROM btf_ssap WHERE ZYH='{$brry["AAA28"]}'";

            $stmt = sqlsrv_query($connection, $sql);

            if ($stmt === false) {
                $errors = sqlsrv_errors();
                foreach ($errors as $error) {
                    return false;
                }
            } else {

                // 获取所有数据，并计算每列的最大宽度
                $rows = [];
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $formattedRow = [];
                    foreach ($row as $key => $value) {
                        if ($value instanceof \DateTime) {
                            $formattedValue = $value->format('Y-m-d H:i:s');
                        } else {
                            $formattedValue = $value === null ? 'NULL' : (string)$value;
                        }


                        $formattedRow[$key] = $formattedValue;
                    }
                    $rows[] = $formattedRow;
                }
                // 打印数据行
                foreach ($rows as $row) {
                    if (empty($row["SQDH"]) || $row["SQDH"] == 'NULL') {
                        continue;
                    }
                    $brry = ZY_BRRY::query()->where(["AAA28" => $row["BAH"], "ZYCS" => $row["ZYCS"]])->first(["ZYH"]);
                    $row["ZYH"] = "";
                    if ($brry) {
                        $row["ZYH"] = $brry->ZYH;
                    } else {
                        $brry = ZY_BRRY::query()
                            ->where('AAA28', $row["BAH"])
                            ->where('AAB01', '<=', $row['SSRQ'])
                            ->where(function ($query) use ($row) {
                                $query->where('AAC01', '>=', $row['SSRQ'])->orWhereNull('AAC01')->orWhere('AAC01', '');
                            })
                            ->first();

                        if ($brry) {
                            $row["ZYH"] = $brry->ZYH;
                        }
                    }
                    $row["flag"] = "米健";
                    $row["SZDM"] = $staff[$row["SZDM"]] ?? $row["SZDM"];
                    $row["SQYS"] = $staff[$row["SQYS"]] ?? $row["SQYS"];
                    SM_SSAP::addData($row);
                }
            }

            // 释放资源
            sqlsrv_free_stmt($stmt);
            sqlsrv_close($connection);
        } else {
            $errors = sqlsrv_errors();
            if (is_array($errors)) {
                return false;
            }
        }

        $con = oci_connect('btfssjl', 'btfssjl', '192.168.10.157:1521/docare', 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }

        // 使用直接查询获取数据
        $sql = "SELECT * FROM MEDCOMM.ZY_SSAP WHERE ZYH='{$zyh}'";
        $result = oci_parse($con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }

        foreach ($data as $item) {
            if (empty($item["SQDH"]) || $item["SQDH"] == 'NULL') {
                continue;
            }
            $brry = ZY_BRRY::query()->where(["AAA28" => $item["BAH"], "ZYCS" => $item["ZYCS"]])->first(["ZYH"]);
            $item["ZYH"] = "";
            if ($brry) {
                $item["ZYH"] = $brry->ZYH;
            } else {
                $brry = ZY_BRRY::query()
                    ->where('AAA28', $item["BAH"])
                    ->where('AAB01', '<=', $item['SSRQ'])
                    ->where(function ($query) use ($item) {
                        $query->where('AAC01', '>=', $item['SSRQ'])->orWhereNull('AAC01')->orWhere('AAC01', '');
                    })
                    ->first();

                if ($brry) {
                    $item["ZYH"] = $brry->ZYH;
                }
            }
            $mc = $item["ICD9_SSCZMC"] ?? "";
            $item["ICD9_SSCZMC"] = $item["ICD9_SSCZBM"] ?? "";
            $item["ICD9_SSCZBM"] = $mc;
            $item["flag"] = "麦迪斯顿";
            $item["SZDM"] = $staff[$item["SZDM"]] ?? $item["SZDM"];
            $item["SQYS"] = $staff[$item["SQYS"]] ?? $item["SQYS"];
            SM_SSAP::addData($item);
        }
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function getOnlyData($zyh, $type = [])
    {
        $this->getConnect();
        // PatientInfo::query()->updateOrInsert(['MED_REC_ID' => $zyh], ['MED_REC_ID' => $zyh]);


        // 医嘱
        $yzbData = [];
        if (empty($type) || in_array('yzb', $type)) {
            $yzbData = $this->getYzb($zyh);
        }


        // 医生签名
        $bl01 = [];
        if (empty($type) || in_array('bl01', $type)) {
            $bl01 = $this->getBLSY($zyh);
        }

        if (empty($type) || in_array('bl01New', $type)) {
            $this->getBl01New($zyh);
        }

        // 护士分床时间
        if (empty($type) || in_array('zy_hcmx', $type)) {
            $this->ZY_HCMX($zyh);
        }

        // 会诊信息
        if (empty($type) || in_array('ys_zy_hzyj', $type)) {
            $this->YS_ZY_HZYJ($zyh);
        }

        // 检查
        if (empty($type) || in_array('v_jmgs_testresult', $type)) {
            $this->V_JMGS_TESTRESULT($zyh);
        }

        // 检验
        if (empty($type) || in_array('v_jmgs_ymresult', $type)) {
            $this->V_JMGS_YMresult($zyh);
        }

        // 麻醉记录
        if (empty($type) || in_array('mzjl', $type)) {
            //            $this->mzjl($zyh);
        }

        if (empty($type) || in_array('pacs', $type)) {
            $this->pacs($zyh);
        }

        return ['yzb' => $yzbData, 'bl01' => $bl01];
    }

    /**
     * 获取用户主信息
     * @return array
     */
    public function getPatientInfo($ZYH = "", $startTime = "")
    {
        $sql = "SELECT A.ZA03,A.AAA28,A.AAA01,A.AAA01,A.AAA02C,A.AAA03,A.AAA04,A.AAA05C,A.AAA40,A.AAA42,A.AEN01,A.AAA06C, A.AAA07,A.AAA07,A.AAA08C,A.AEM01C,A.AAB01,A.AAC01,A.AAC11N,A.AAC04,A.ADA01,A.ADA0101,A.AAA29,A.ABG01C, A.ABG01N,A.AAB06C,A.ABC01N,A.ORG_STATE,A.AAA26C,A.ATTEND_GRP_CODE,A.ATTEND_GRP_NAME,A.AAB07C,A.AAB07N, A.AAB07,A.AAB07D,A.ABD04,A.ABD051,A.ABD052,A.ABD053,A.ABD054,A.ZB09,A.ZB08,A.ZB07,A.ZB06,A.ZB05,A.ZB04, A.ZB03,A.ZB02,A.ZB01C,A.ZA04,A.UNT_ID,A.ZA03,A.AFA01,A.AFA02,A.AFA03,A.AFA04,A.AFA05,A.AFA06,A.AFA07, A.AFA08,A.AFA09,A.AFA10,A.AFA11,A.AFA12,A.ZB10,A.ZB11,A.IS_VALID,A.SYN_DATE,A.QU_STATE,A.DATA_STATE, A.BALANCEID,A.AKC021,A.ABA01C,A.ABA01N,A.ABC03C,A.ABF01C,A.ABF01N,A.ABF04,A.ABF02C,A.ABF03C,A.ABH01C, A.ABH0201C,A.ABH0202C,A.ABH0203C,A.ABH03C,A.AEB02C,A.AEB01,A.AED01C,A.AEG01C,A.AEG02C,A.AEG04,A.AEG05, A.AEG06,A.AEG07,A.AEG08,A.AEJ01,A.AEJ02,A.AEJ03,A.AEJ04,A.AEJ05,A.AEJ06,A.AEL01,A.AEN02C,A.AEN02N, A.AEI09,A.AEI10,A.AEI08,A.AAA30,A.ABC01C,A.AAA27,A.AAC001,A.AAB01,A.AAB02C,A.AAB03,A.AAB11C,A.AAB11N, A.AAC02C,A.AAC03,A.AAC11C,A.AAD01C,A.AEM02,A.AEM03C,A.AEM04,A.AEI01C,A.AED02,A.AED03,A.AEE01,A.AEE02, A.AEE03,A.AEE11,A.AEE09,A.AEE04,A.AEE05,A.AEE07,A.AEE08,A.AEE10,A.AEE01_CODE,A.AEE02_CODE,A.AEE03_CODE, A.AEE04_CODE,A.AAA09,A.AAA10,A.AAA11,A.AAA43,A.AAA44,A.AAA45,A.AAA46,A.AAA47,A.AAA12,A.AAA13C,A.AAA33C, A.AAA14C,A.AAA15,A.AAA48,A.AAA49,A.AAA50,A.AAA16C,A.AAA36C,A.AAA51,A.AAA51,A.AAA17C,A.AAA18C,A.AAA19, A.AAA20,A.AAA20,A.AAA21C,A.AAA22,A.AAA22,A.AAA23C,A.AAA24,A.AAA25,A.AAA25,A.MED_REC_ID, to_char(A.AAA03,'yyyy-mm-dd hh24:mi:ss') as AAA03, to_char(A.SYN_DATE,'yyyy-mm-dd hh24:mi:ss') as SYN_DATE, to_char(A.AAB01,'yyyy-mm-dd hh24:mi:ss') as AAB01, to_char(A.AAC01,'yyyy-mm-dd hh24:mi:ss') as AAC01, to_char(A.AED04,'yyyy-mm-dd hh24:mi:ss') as AED04, to_char(A.MT_JZRQ,'yyyy-mm-dd') as MT_JZRQ FROM PORTAL_HIS.INIT_MED_REC_MAIN A WHERE 1=1";
        if ($ZYH) {
            $sql .= " and A.MED_REC_ID=" . $ZYH;
        }
        if ($startTime) {
            $startTimeStr = date("Ymd", $startTime);
            $sql .= " AND AAC01 BETWEEN TO_DATE('" . $startTimeStr . "000000', 'yyyy-MM-dd HH24:mi:ss') AND TO_DATE('" . $startTimeStr . "235959', 'yyyy-MM-dd HH24:mi:ss')";
        }

        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while (@$row = oci_fetch_assoc($data)) {
            foreach ($row as &$value) {
                $source_encoding = mb_detect_encoding($value);
                $value = iconv($source_encoding, 'UTF-8', $value);
            }
            $result[] = $row;
        }

        return $result;
    }

    public function addPatientInfo($data)
    {
        // 主信息
        $hospital_name = config('confAdmin.hospital_name');
        //身份证号信息脱敏
        $AAA07 = $data['AAA07'] ? desensitize($data['AAA07'], 0, 6, '*') : '';
        $AAA07 = $AAA07 ? desensitize($AAA07, 14, 2, '*') : '';
        $AAA07 = $AAA07 ? desensitize($AAA07, 17, 1, '*') : '';
        $patient_info = [
            'hospital_name' => $data['ZA03'] ?: $hospital_name, //机构名称
            'MED_REC_ID' => $data['MED_REC_ID'],
            'AAA28' => $data['AAA28'],
            'AAA01' => $data['AAA01'] ? desensitize($data['AAA01'], 1, 1, '*') : '',        //患者姓名
            'AAA02C' => $data['AAA02C'] ?: '',      //患者性别
            'AAA03' => $data['AAA03'] ?: '',        //出生日期
            'AAA04' => $data['AAA04'] ?: '',        //年龄
            'AAA05C' => $data['AAA05C'] ?: '',      //国籍
            'AAA40' => $data['AAA40'] ?: '',        //不足一周岁年龄
            'AAA42' => $data['AAA42'] ?: '',        //新生儿入院体重
            'AEN01' => $data['AEN01'] ?: '',        //新生儿出生体重
            'AAA06C' => $data['AAA06C'] ?: '',      //民族代码
            'AAA07' => $AAA07,                      //身份证号
            'AAA08C' => $data['AAA08C'] ?: '',      //婚姻状况
            'AEM01C' => $data['AEM01C'] ?: '',      //离院方式代码
            'AAB01' => $data['AAB01'] ?: '',        //入院时间
            'AAC01' => $data['AAC01'] ?: '',        //出院时间
            'AAC11N' => $data['AAC11N'] ?: '',      //出院医院内部科室名称
            'AAC04' => $data['AAC04'] ?: '',        //实际住院
            'ADA01' => $data['ADA01'] ?: '',        //总费用
            'ADA0101' => $data['ADA0101'] ?: '',    //自付费用
            'AAA29' => $data['AAA29'] ?: '',        //住院次数
            'ABG01C' => $data['ABG01C'] ?: '',      //损伤和中毒外部原因编码 no
            'ABG01N' => $data['ABG01N'] ?: '',      //损伤和中毒外部原因名称 no
            'AAB06C' => $data['AAB06C'] ?: '',      //入院途径代码
            'ABC01N' => $data['ABC01N'] ?: '',      //出院主要诊断名称
            'ORG_STATE' => $data['ORG_STATE'] ?: '', //质控状态
            'AAA26C' => $data['AAA26C'] ?: '',      //医疗付费方式代码
            'ATTEND_GRP_CODE' => $data['ATTEND_GRP_CODE'] ?: '',    //主诊组编码
            'ATTEND_GRP_NAME' => $data['ATTEND_GRP_NAME'] ?: '',    //主诊组名称
        ];
        PatientInfo::query()->updateOrInsert(['MED_REC_ID' => $data['MED_REC_ID']], $patient_info);

        // 其他信息
        $patient_other_info = [
            'AAB07C' => $data['AAB07C'] ?: '',      //入院诊断id
            'AAB07N' => $data['AAB07N'] ?: '',      //入院诊断名称
            'AAB07' => $data['AAB07'] ?: '',        //入院时情况
            'AAB07D' => $data['AAB07D'] ?: '',      //入院后确诊日期
            'ABD04' => $data['ABD04'] ?: '',        //医院感染名称
            'ABD051' => $data['ABD051'] ?: '',      //门诊与出院诊断符合情况
            'ABD052' => $data['ABD052'] ?: '',      //术前与术后诊断符合情况
            'ABD053' => $data['ABD053'] ?: '',      //临床与病理诊断符合情况
            'ABD054' => $data['ABD054'] ?: '',      //放射与病理诊断符合情况
            'ZB09' => $data['ZB09'] ?: '',          //手机
            'ZB08' => $data['ZB08'] ?: '',
            'ZB07' => $data['ZB07'] ?: '',
            'ZB06' => $data['ZB06'] ?: '',
            'ZB05' => $data['ZB05'] ?: '',
            'ZB04' => $data['ZB04'] ?: '',
            'ZB03' => $data['ZB03'] ?: '',
            'ZB02' => $data['ZB02'] ?: '',
            'ZB01C' => $data['ZB01C'] ?: '',
            'ZA04' => $data['ZA04'] ?: '',
            'MED_REC_ID' => $data['MED_REC_ID'] ?: '',  //病案⾸⻚ID
            'UNT_ID' => $data['UNT_ID'] ?: '',      //组织机构代码ID
            'ZA03' => $data['ZA03'] ?: '',          //机构名称
            'AFA01' => $data['AFA01'] ?: '',        //抢救次数
            'AFA02' => $data['AFA02'] ?: '',        //成本次数
            'AFA03' => $data['AFA03'] ?: '',
            'AFA04' => $data['AFA04'] ?: '',
            'AFA05' => $data['AFA05'] ?: '',
            'AFA06' => $data['AFA06'] ?: '',
            'AFA07' => $data['AFA07'] ?: '',
            'AFA08' => $data['AFA08'] ?: '',
            'AFA09' => $data['AFA09'] ?: '',
            'AFA10' => $data['AFA10'] ?: '',
            'AFA11' => $data['AFA11'] ?: '',
            'AFA12' => $data['AFA12'] ?: '',
            'ZB10' => $data['ZB10'] ?: '',          //填报版本
            'ZB11' => $data['ZB11'] ?: '',          //填报说明
            'IS_VALID' => $data['IS_VALID'] ?: '',  //有效标识
            'SYN_DATE' => $data['SYN_DATE'] ?: '',  //获取时间
            'QU_STATE' => $data['QU_STATE'] ?: '',  //是否采集
            'DATA_STATE' => $data['DATA_STATE'] ?: '',  //病案采集状态
            'BALANCEID' => $data['BALANCEID'] ?: '',    //病案流水号
            'AKC021' => $data['AKC021'] ?: '',      //⼈群类型
        ];
        PatientOtherInfo::query()->updateOrInsert(['AAA28' => $data['MED_REC_ID']], $patient_other_info);

        $patient_medical_info = [
            'ABA01C' => $data['ABA01C'] ?: '',      //门(急)诊诊断编码
            'ABA01N' => $data['ABA01N'] ?: '',      //⻔（急）诊诊断名称
            'ABC03C' => $data['ABC03C'] ?: '',      //入院病情代码
            'ABF01C' => $data['ABF01C'] ?: '',      //病理诊断编码
            'ABF01N' => $data['ABF01N'] ?: '',      //病理诊断名称
            'ABF04' => $data['ABF04'] ?: '',        //病理号
            'ABF02C' => $data['ABF02C'] ?: '',      //最高诊断依据代码ID
            'ABF03C' => $data['ABF03C'] ?: '',      //分化程度编码ID
            'ABH01C' => $data['ABH01C'] ?: '',      //肿瘤分期是否不详
            'ABH0201C' => $data['ABH0201C'] ?: '',  //肿瘤分期 TID
            'ABH0202C' => $data['ABH0202C'] ?: '',  //肿瘤分期 NID
            'ABH0203C' => $data['ABH0203C'] ?: '',  //肿瘤分期 MID
            'ABH03C' => $data['ABH03C'] ?: '',      //0～Ⅳ肿瘤分期ID
            'AEB02C' => $data['AEB02C'] ?: null,      //有无药物过敏
            'AEB01' => $data['AEB01'] ?: '',        //过敏药物
            'AED01C' => $data['AED01C'] ?: '',      //病案质量代码ID
            'AEG01C' => $data['AEG01C'] ?: '',      //血型代码ID
            'AEG02C' => $data['AEG02C'] ?: '',      //Rh 代码ID
            'AEG04' => $data['AEG04'] ?: '',        //红细胞(单位)
            'AEG05' => $data['AEG05'] ?: '',        //血小板(袋)
            'AEG06' => $data['AEG06'] ?: '',        //血浆(ml)
            'AEG07' => $data['AEG07'] ?: '',        //全血(ml)
            'AEG08' => $data['AEG08'] ?: '',        //其它(ml)
            'AEJ01' => $data['AEJ01'] ?: '',        //颅脑损伤患者入院前昏迷时间（天）
            'AEJ02' => $data['AEJ02'] ?: '',        //颅脑损伤患者入院前昏迷时间（天）
            'AEJ03' => $data['AEJ03'] ?: '',        //颅脑损伤患者入院前昏迷时间（天）
            'AEJ04' => $data['AEJ04'] ?: '',        //颅脑损伤患者入院后昏迷时间（天）
            'AEJ05' => $data['AEJ05'] ?: '',        //颅脑损伤患者入院后昏迷时间（天）
            'AEJ06' => $data['AEJ06'] ?: '',        //颅脑损伤患者入院后昏迷时间（天）
            'AEL01' => $data['AEL01'] ?: '',        //呼吸机使用时间（天）
            'AEN02C' => $data['AEN02C'] ?: '',      //新生儿出生缺陷诊断
            'AEN02N' => $data['AEN02N'] ?: '',      //新生儿出生缺陷诊断名称
            'AEI09' => $data['AEI09'] ?: '',        //日常生活能力评定量得分
            'AEI10' => $data['AEI10'] ?: '',        //日常生活能力评定量得分
            'AEI08' => $data['AEI08'] ?: '',        //备注
        ];
        PatientMedicalInfo::query()->updateOrInsert(['AAA28' => $data['MED_REC_ID']], $patient_medical_info);

        //患者住院信息
        $patient_hospital_info = [
            'AAA30' => $data['AAA30'] ?: '',    //住院号
            'ABC01C' => $data['ABC01C'] ?: '',  //出院时主要诊断编码
            'AAA27' => $data['AAA27'] ?: '-',   //医疗保险手册(卡)号
            'AAC001' => $data['AAC001'] ?: '',  //医保个人编号
            'AAB01' => $data['AAB01'] ?: '',    //入院时间（时）
            'AAB02C' => $data['AAB02C'] ?: '',  //入院科别代码
            'AAB03' => $data['AAB03'] ?: '',    //入院病房
            'AAB11C' => $data['AAB11C'] ?: '',  //入院医院内部科室代码ID
            'AAB11N' => $data['AAB11N'] ?: '',  //入院医院内部科室名称
            'AAC02C' => $data['AAC02C'] ?: '',  //出院科别代码ID
            'AAC03' => $data['AAC03'] ?: '',    //出院病房
            'AAC11C' => $data['AAC11C'] ?: '',  //出院医院内部科室代码ID
            'AAD01C' => $data['AAD01C'] ?: '',  //转经科别代码ID
            'AEM02' => $data['AEM02'] ?: '',   //医嘱转院、转社区、卫生院机编码ID
            'AEM03C' => $data['AEM03C'] ?: '',  //是否有出院31日内再住院计划
            'AEM04' => $data['AEM04'] ?: '',    //31日内再住院目的
            'AEI01C' => $data['AEI01C'] ?: '',  //是否尸检代码ID
        ];
        PatientHospitalInfo::query()->updateOrInsert(['AAA28' => $data['MED_REC_ID']], $patient_hospital_info);

        //患者医生信息
        $patient_doctor_info = [
            'AED02' => $data['AED02'] ?: '',    //质控医师姓名
            'AED03' => $data['AED03'] ?: '',    //质控护士姓名
            'AED04' => $data['AED04'] ?: '',    //病案质量检查日期
            'AEE01' => $data['AEE01'] ?: '',    //科主任姓名
            'AEE02' => $data['AEE02'] ?: '',    //主(副主)任医师姓名
            'AEE03' => $data['AEE03'] ?: '',    //主治医师姓
            'AEE11' => $data['AEE11'] ?: '',    //主诊医师执业证书编码
            'AEE09' => $data['AEE09'] ?: '',    //主诊医师姓名
            'AEE04' => $data['AEE04'] ?: '',    //住院医师姓名
            'AEE05' => $data['AEE05'] ?: '',    //进修医师姓名
            'AEE07' => $data['AEE07'] ?: '',    //实习医师姓名
            'AEE08' => $data['AEE08'] ?: '',    //编码员姓名
            'AEE10' => $data['AEE10'] ?: '',    //责任护士姓名
            'AEE01_CODE' => $data['AEE01_CODE'] ?: '',  //科主任编码
            'AEE02_CODE' => $data['AEE02_CODE'] ?: '',  //主（副主）任医师工号
            'AEE03_CODE' => $data['AEE03_CODE'] ?: '',  //主治医师工号
            'AEE04_CODE' => $data['AEE04_CODE'] ?: '',  //住院医师工号
        ];
        PatientDoctorInfo::query()->updateOrInsert(['AAA28' => $data['MED_REC_ID']], $patient_doctor_info);

        //患者地址相关信息
        $patient_address_info = [
            'AAA09' => $data['AAA09'] ?: '',    //出生地省
            'AAA10' => $data['AAA10'] ?: '',    //出生地市
            'AAA11' => $data['AAA11'] ?: '',    //出生地县
            'AAA43' => $data['AAA43'] ?: '',    //籍贯省
            'AAA44' => $data['AAA44'] ?: '',    //籍贯市
            'AAA45' => $data['AAA45'] ?: '',    //户籍省
            'AAA46' => $data['AAA46'] ?: '',    //户籍市
            'AAA47' => $data['AAA47'] ?: '',    //户籍县
            'AAA12' => $data['AAA12'] ?: '',    //户籍详细地址
            'AAA13C' => $data['AAA13C'] ?: '',  //户籍地址区县编码
            'AAA33C' => $data['AAA33C'] ?: '',  //户籍街道乡镇代码ID
            'AAA14C' => $data['AAA14C'] ?: '',  //户籍地址邮政编码
            'AAA15' => $data['AAA15'] ?: '',    //现住址详细地址
            'AAA48' => $data['AAA48'] ?: '',    //现住址省
            'AAA49' => $data['AAA49'] ?: '',    //现住址市
            'AAA50' => $data['AAA50'] ?: '',    //现住址县
            'AAA16C' => $data['AAA16C'] ?: '',  //现住址区县编码
            'AAA36C' => $data['AAA36C'] ?: '',  //现住址街道乡镇代码
            'AAA51' => $data['AAA51'] ? desensitize($data['AAA51'], 3, 4, '*') : '',    //现住址电话
            'AAA17C' => $data['AAA17C'] ?: '',  //现住址邮政编码
        ];
        PatientAddressInfo::query()->updateOrInsert(['AAA28' => $data['MED_REC_ID']], $patient_address_info);

        //患者工作信息
        $patient_work_info = [
            'AAA18C' => $data['AAA18C'] ?: '',      //职业代码ID
            'AAA19' => $data['AAA19'] ?: '',        //工作单位及地址
            'AAA20' => $data['AAA20'] ? desensitize($data['AAA20'], 3, 4, '*') : '',        //工作单位电话
            'AAA21C' => $data['AAA21C'] ?: '',      //工作单位邮政编码
        ];
        PatientWorkInfo::query()->updateOrInsert(['AAA28' => $data['MED_REC_ID']], $patient_work_info);

        //患者联系人信息
        $patient_contacts_info = [
            'AAA22' => $data['AAA22'] ? desensitize($data['AAA22'], 1, 1, '*') : '',    //联系人姓名
            'AAA23C' => $data['AAA23C'] ?: '',  //联系人关系代码ID
            'AAA24' => $data['AAA24'] ?: '',    //联系人地址
            'AAA25' => $data['AAA25'] ? desensitize($data['AAA25'], 3, 4, '*') : '',    //联系人电话
        ];
        PatientContactsInfo::query()->updateOrInsert(['AAA28' => $data['MED_REC_ID']], $patient_contacts_info);
    }

    /**
     * 获取手术申请
     * @param $ZYH
     * @return array
     */
    public function getSssqData($ZYH)
    {
        // 查询 手术申请
        $sql = "SELECT A.*,to_char(SQRQ,'yyyy-mm-dd hh24:mi:ss') as SQRQ,to_char(SSRQ,'yyyy-mm-dd hh24:mi:ss') as SSRQ FROM PORTAL_HIS.SM_SSSQ A WHERE ZYH=" . $ZYH;
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }

        return $result;
    }

    public function addSmSssq($data)
    {
        $insertData = [];
        foreach ($data as $val) {
            $insertData[] = [
                'SQDH' => $val['SQDH'] ?? '',
                'ZYH' => $val['ZYH'] ?? '',
                'SSKS' => $val['SSKS'] ?? '',
                'SQKS' => $val['SQKS'] ?? '',
                'SQYS' => $val['SQYS'] ?? '',
                'SQRQ' => $val['SQRQ'] ?? '',
                'SSRQ' => $val['SSRQ'] ?? '',
                'SSNM' => $val['SSNM'] ?? '',
                'SSYS' => $val['SSYS'] ?? '',
                'SSYZ' => $val['SSYZ'] ?? '',
                'SSEZ' => $val['SSEZ'] ?? '',
                'SSSZ' => $val['SSSZ'] ?? '',
                'MZDM' => $val['MZDM'] ?? '',
                'MZYS' => $val['MZYS'] ?? '',
                'TJBZ' => $val['TJBZ'] ?? '',
                'APBZ' => $val['APBZ'] ?? '',
                'ZFBZ' => $val['ZFBZ'] ?? '',
                'TXKS' => $val['TXKS'] ?? '',
                'CZGH' => $val['CZGH'] ?? '',
                'SQTL' => $val['SQTL'] ?? '',
                'SQZD' => $val['SQZD'] ?? '',
                'NSSMC' => $val['NSSMC'] ?? '',
                'FYBQ' => $val['FYBQ'] ?? '',
                'ZFGH' => $val['ZFGH'] ?? '',
                'ZLXZ' => $val['ZLXZ'] ?? '',
                'QKDJ' => $val['QKDJ'] ?? '',
                'CFSS' => $val['CFSS'] ?? '',
                'SSYQ' => $val['SSYQ'] ?? '',
                'LRBZ' => $val['LRBZ'] ?? '',
                'THYY' => $val['THYY'] ?? '',
                'ZFYY' => $val['ZFYY'] ?? '',
                'YXJS' => $val['YXJS'] ?? '',
                'NLTR' => $val['NLTR'] ?? '',
                'HBQTJB' => $val['HBQTJB'] ?? '',
                'QTTSQK' => $val['QTTSQK'] ?? '',
                'TSQKNR' => $val['TSQKNR'] ?? '',
                'SSJB' => $val['SSJB'] ?? '',
                'CRBZ' => $val['CRBZ'] ?? '',
                'CRBG' => $val['CRBG'] ?? '',
                'BXBZ' => $val['BXBZ'] ?? '',
                'BXSM' => $val['BXSM'] ?? '',
                'BZXX' => $val['BZXX'] ?? '',
                'SSTW' => $val['SSTW'] ?? '',
                'SPBZ' => $val['SPBZ'] ?? '',
                'JGID' => $val['JGID'] ?? '',
                'RJSS' => $val['RJSS'] ?? null,
                'JJBZ' => $val['JZBZ'] ?? '',
                'BRLY' => $val['BRLY'] ?? '',
                'BRID' => $val['BRID'] ?? null,
                'SSLX' => $val['SSLX'] ?? null,
                'JJYZ' => $val['JJYZ'] ?? null,
                'TZBH' => $val['TZBH'] ?? null,
            ];
        }
        SSSQ::query()->where('ZYH', '=', $data[0]['ZYH'])->delete();
        if (!empty($insertData)) {
            SSSQ::query()->insert($insertData);
        }
    }

    /**
     * 同步手术申请信息。
     *
     * @param string $zyh 住院号
     * @return bool
     */
    public function addSssq($zyh = '')
    {
        if (empty($zyh)) {
            return false;
        }

        if (!self::$con) {
            $this->getConnect();
        }

        $sql = "SELECT A.*,to_char(SQRQ,'yyyy-mm-dd hh24:mi:ss') as SQRQ,to_char(SSRQ,'yyyy-mm-dd hh24:mi:ss') as SSRQ
                FROM PORTAL_HIS.BTF_SM_SSSQ A
                WHERE ZYH=" . $zyh;
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }

        if (empty($result)) {
            return false;
        }

        foreach ($result as $val) {
            $insertData = [
                'SQDH' => $val['SQDH'] ?? '',
                'ZYH' => $val['ZYH'] ?? '',
                'SSKS' => $val['SSKS'] ?? '',
                'SQKS' => $val['SQKS'] ?? '',
                'SQYS' => $val['SQYS'] ?? '',
                'SQRQ' => $val['SQRQ'] ?? '',
                'SSRQ' => $val['SSRQ'] ?? '',
                'SSNM' => $val['SSNM'] ?? '',
                'SSYS' => $val['SSYS'] ?? '',
                'SSYZ' => $val['SSYZ'] ?? '',
                'SSEZ' => $val['SSEZ'] ?? '',
                'SSSZ' => $val['SSSZ'] ?? '',
                'MZDM' => $val['MZDM'] ?? '',
                'MZYS' => $val['MZYS'] ?? '',
                'TJBZ' => $val['TJBZ'] ?? '',
                'APBZ' => $val['APBZ'] ?? '',
                'ZFBZ' => $val['ZFBZ'] ?? '',
                'TXKS' => $val['TXKS'] ?? '',
                'CZGH' => $val['CZGH'] ?? '',
                'SQTL' => $val['SQTL'] ?? '',
                'SQZD' => $val['SQZD'] ?? '',
                'NSSMC' => $val['NSSMC'] ?? '',
                'FYBQ' => $val['FYBQ'] ?? '',
                'ZFGH' => $val['ZFGH'] ?? '',
                'ZLXZ' => $val['ZLXZ'] ?? '',
                'QKDJ' => $val['QKDJ'] ?? '',
                'CFSS' => $val['CFSS'] ?? '',
                'SSYQ' => $val['SSYQ'] ?? '',
                'LRBZ' => $val['LRBZ'] ?? '',
                'THYY' => $val['THYY'] ?? '',
                'ZFYY' => $val['ZFYY'] ?? '',
                'YXJS' => $val['YXJS'] ?? '',
                'NLTR' => $val['NLTR'] ?? '',
                'HBQTJB' => $val['HBQTJB'] ?? '',
                'QTTSQK' => $val['QTTSQK'] ?? '',
                'TSQKNR' => $val['TSQKNR'] ?? '',
                'SSJB' => $val['SSJB'] ?? '',
                'CRBZ' => $val['CRBZ'] ?? '',
                'CRBG' => $val['CRBG'] ?? '',
                'BXBZ' => $val['BXBZ'] ?? '',
                'BXSM' => $val['BXSM'] ?? '',
                'BZXX' => $val['BZXX'] ?? '',
                'SSTW' => $val['SSTW'] ?? '',
                'SPBZ' => $val['SPBZ'] ?? '',
                'JGID' => $val['JGID'] ?? '',
                'RJSS' => $val['RJSS'] ?? null,
                'JJBZ' => $val['JZBZ'] ?? '',
                'BRLY' => $val['BRLY'] ?? '',
                'BRID' => $val['BRID'] ?? null,
                'SSLX' => $val['SSLX'] ?? null,
                'JJYZ' => $val['JJYZ'] ?? null,
                'TZBH' => $val['TZBH'] ?? null,
                'DSG_OPERATION' => $val['DSG_OPERATION'] ?? '',
                'DSG_LDR_TIME' => $val['DSG_LDR_TIME'] ?? '',
                'FJHZCSS' => $val['FJHZCSS'] ?? '',
            ];

            SSSQ::query()->updateOrInsert(['SQDH' => $insertData['SQDH'], 'ZYH' => $insertData['ZYH']], $insertData);
        }

        return true;
    }

    /**
     * 清洗手术类别。
     *
     * @param string $zyh 住院号
     * @return bool
     */
    public function cleanSSLB($zyh = '')
    {
        if (empty($zyh)) {
            return false;
        }

        $data = SM_SSAP::query()
            ->where('ZYH', $zyh)
            ->get()
            ->toArray();

        if (empty($data)) {
            return false;
        }

        foreach ($data as $item) {
            $ssczbm = $item['ICD9_SSCZBM'] ?? '';
            if ($ssczbm === '') {
                continue;
            }

            $sslb = \App\Model\ICD9::query()
                ->where('SSCZBM', 'like', '%' . $ssczbm . '%')
                ->first(['SSLB', 'SSCZBM', 'SSCZMC']);

            if (empty($sslb)) {
                continue;
            }

            $sslb = $sslb->toArray();
            echo $item['id'] . ' - ' . date('Y-m-d H:i:s') . PHP_EOL;
            SM_SSAP::query()->where('id', '=', $item['id'])->update([
                'ICD9_SSLB' => $sslb['SSLB'],
                'ICD9_SSCZBM' => $sslb['SSCZBM'],
                'ICD9_SSCZMC' => $sslb['SSCZMC'],
            ]);
        }

        return true;
    }

    public function addSsap($zyh = "")
    {

        $staff = Staff::query()->get()->toArray();
        $staff = array_column($staff, "code", "YGBH");
        $brry = ZY_BRRY::query()->where("ZYH", $zyh)->first();
        if (empty($brry)) {
            return false;
        }
        $brry = $brry->toArray();

        //使用laravel自带的sqlsrv方法连接
        $connectionOptions = [
            'Database' => env("SQLSRV_DATABASE"),
            'UID' => env("SQLSRV_USERNAME"),
            'PWD' => env("SQLSRV_PASSWORD"),
            'LoginTimeout' => 10,
            'TrustServerCertificate' => 1,
            'Encrypt' => 1
        ];

        $connection = sqlsrv_connect(env("SQLSRV_HOST"), $connectionOptions);
        if ($connection) {

            // 使用直接查询获取数据
            $sql = "SELECT * FROM btf_ssap WHERE ZYH='{$brry["AAA28"]}'";

            $stmt = sqlsrv_query($connection, $sql);

            if ($stmt === false) {
                $errors = sqlsrv_errors();
                foreach ($errors as $error) {
                    return false;
                }
            } else {

                // 获取所有数据，并计算每列的最大宽度
                $rows = [];
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $formattedRow = [];
                    foreach ($row as $key => $value) {
                        if ($value instanceof \DateTime) {
                            $formattedValue = $value->format('Y-m-d H:i:s');
                        } else {
                            $formattedValue = $value === null ? 'NULL' : (string)$value;
                        }


                        $formattedRow[$key] = $formattedValue;
                    }
                    $rows[] = $formattedRow;
                }
                // 打印数据行
                foreach ($rows as $row) {
                    if (empty($row["SQDH"]) || $row["SQDH"] == 'NULL') {
                        continue;
                    }
                    $brry = ZY_BRRY::query()->where(["AAA28" => $row["BAH"], "ZYCS" => $row["ZYCS"]])->first(["ZYH"]);
                    $row["ZYH"] = "";
                    if ($brry) {
                        $row["ZYH"] = $brry->ZYH;
                    } else {
                        $brry = ZY_BRRY::query()
                            ->where('AAA28', $row["BAH"])
                            ->where('AAB01', '<=', $row['SSRQ'])
                            ->where(function ($query) use ($row) {
                                $query->where('AAC01', '>=', $row['SSRQ'])->orWhereNull('AAC01')->orWhere('AAC01', '');
                            })
                            ->first();

                        if ($brry) {
                            $row["ZYH"] = $brry->ZYH;
                        }
                    }
                    $row["flag"] = "米健";
                    $row["SZDM"] = $staff[$row["SZDM"]] ?? $row["SZDM"];
                    $row["SQYS"] = $staff[$row["SQYS"]] ?? $row["SQYS"];
                    SM_SSAP::addData($row);
                }
            }

            // 释放资源
            sqlsrv_free_stmt($stmt);
            sqlsrv_close($connection);
        } else {
            $errors = sqlsrv_errors();
            if (is_array($errors)) {
                return false;
            }
        }

        $con = oci_connect('btfssjl', 'btfssjl', '192.168.10.157:1521/docare', 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }

        // 使用直接查询获取数据
        $sql = "SELECT * FROM MEDCOMM.ZY_SSAP WHERE ZYH='{$zyh}'";
        $result = oci_parse($con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }

        foreach ($data as $item) {
            if (empty($item["SQDH"]) || $item["SQDH"] == 'NULL') {
                continue;
            }
            //$brry = ZY_BRRY::query()->where(["AAA28" => $item["BAH"], "ZYCS" => $item["ZYCS"]])->first(["ZYH"]);
            $item["ZYH"] = $item["ZYCS"] ?? "";
            $item["ZYCS"] = "";
            /* if ($brry) {
                $item["ZYH"] = $brry->ZYH;
            } else {
                $brry = ZY_BRRY::query()
                    ->where('AAA28', $item["BAH"])
                    ->where('AAB01', '<=', $item['SSRQ'])
                    ->where(function ($query) use ($item) {
                        $query->where('AAC01', '>=', $item['SSRQ'])->orWhereNull('AAC01')->orWhere('AAC01', '');
                    })
                    ->first();

                if ($brry) {
                    $item["ZYH"] = $brry->ZYH;
                }
            } */
            $mc = $item["ICD9_SSCZMC"] ?? "";
            $item["ICD9_SSCZMC"] = $item["ICD9_SSCZBM"] ?? "";
            $item["ICD9_SSCZBM"] = $mc;
            $item["flag"] = "麦迪斯顿";
            $item["SZDM"] = $staff[$item["SZDM"]] ?? $item["SZDM"];
            $item["SQYS"] = $staff[$item["SQYS"]] ?? $item["SQYS"];
            SM_SSAP::addData($item);
        }
        $this->cleanSSLB($zyh);
    }

    /**
     * 获取费用信息
     * @param $ZYH
     * @return array
     */
    public function getFyData($ZYH)
    {
        // 查询费用数据
        // ,FYGB,SYFYGB,ZJE
        //$sql = "SELECT ZYH as AAA28,FYXH,FYMC,ZFJE,to_char(JFRQ,'yyyy-mm-dd hh24:mi:ss') as JFRQ,FYSL,FYDJ,FYKS FROM PORTAL_HIS.V_ZY_FYMX WHERE ZYH=" . $ZYH;
        $sql = "SELECT A.*,to_char(JFRQ,'yyyy-mm-dd hh24:mi:ss') as JFRQ FROM PORTAL_HIS.V_JMGS_BASY_FYMX A WHERE ZYH=" . $ZYH;
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }

        if (empty($result)) {
            return false;
        }
        $insertData = [];
        foreach ($result as $val) {
            $insertData[] = [
                'AAA28' => $val['ZYH'], //zyh
                'FYXH' => $val['FYXH'] ?? '', //费用序号
                'FYMC' => $val['FYMC'] ?? '', //费用名称
                'ZFJE' => $val['ZFJE'] ?? '', //自付金额
                'JFRQ' => $val['JFRQ'] ?? '', //计费日期
                'FYSL' => $val['FYSL'] ?? '', //费用数量
                'FYDJ' => $val['FYDJ'] ?? '', //费用单价
                'ZJE' => $val['ZJE'] ?? '', //总金额
                'FYKS' => $val['FYKS'] ?? '', //费用科室
                'FYGB' => $val['FYGB'] ?? '',
                'SYFYGB' => $val['SYFYGB'] ?? '',
                'JLXH' => $val['JLXH'] ?? ''
            ];
        }
        FeeDetailed::query()->where('AAA28', '=', $ZYH)->delete();

        if (!empty($insertData)) {
            $chunkList = array_chunk($insertData, 1000);
            foreach ($chunkList as $value) {
                FeeDetailed::query()->insert($value);
            }
        }
    }

    /**
     * 获取重症监护（ICU）信息
     * @param $ZYH
     * @return array
     */
    public function getIcuInfo($ZYH)
    {
        $sql = "SELECT A.MED_REC_ID,A.AREA_ID,A.BATCH_ID,A.IS_MAIN_WAY,to_char(IN_TIME,'yyyy-mm-dd hh24:mi:ss') as IN_TIME,to_char(OUT_TIME,'yyyy-mm-dd hh24:mi:ss') as OUT_TIME FROM PORTAL_HIS.INIT_MED_REC_TUTORSSIP A WHERE A.MED_REC_ID=" . $ZYH;
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }

        return $result;
    }

    public function addIcuData($data)
    {
        $insertData = [];
        foreach ($data as $val) {
            $insertData[] = [
                'AAA28' => $val['MED_REC_ID'],
                'IS_MAIN_WAY' => $val['IS_MAIN_WAY'] ?? '',
                'IN_TIME' => $val['IN_TIME'] ?? '',
                'OUT_TIME' => $val['OUT_TIME'] ?? '',
                'AREA_ID' => $val['AREA_ID'] ?? 0,
                'BATCH_ID' => $val['BATCH_ID'] ?? '',
            ];
        }

        Icu::query()->where('AAA28', '=', $data[0]['MED_REC_ID'])->delete();
        if (!empty($insertData)) {
            Icu::query()->insert($insertData);
        }
    }

    /**
     * 获取诊断信息
     * @param $ZYH
     * @return array
     */
    public function getDiagnosisData($ZYH)
    {
        $sql = "SELECT A.*,to_char(CYRQ,'yyyy-mm-dd hh24:mi:ss') as CYRQ FROM PORTAL_HIS.V_JMGS_BASY_ZD A WHERE ZYH=" . $ZYH;
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }

        return $result;
    }

    public function addDiagnosis($data)
    {
        $main = [];
        $diagnosis = [];
        foreach ($data as $val) {
            $addData = [
                'AAA28' => $val['ZYH'],
                'ICD10_ID1' => $val['ZDBM'],
                'ICD10_NAME' => $val['ZDMC'],
                'DIA_ORDER' => $val['ZDXH'],
                'LBMC' => $val['LBMC'],
                'RYQK' => $val['RYQK']
            ];

            if ($val['ZZPB'] == 1) {
                $main[] = $addData;
            } else {
                $diagnosis[] = $addData;
            }
        }

        // 主要诊断
        MainDiagnosis::query()->where('AAA28', '=', $data[0]['ZYH'])->delete();
        if (!empty($main)) {
            MainDiagnosis::query()->insert($main);
        }
        // 其他诊断
        OtherDiagnosis::query()->where('AAA28', '=', $data[0]['ZYH'])->delete();
        if (!empty($diagnosis)) {
            OtherDiagnosis::query()->insert($diagnosis);
        }

        return array_column($main, 'RYQK', 'AAA28');
    }

    /**
     * 获取手术信息
     * @param $ZYH
     * @return array
     */
    public function getOperationData($ZYH)
    {
        $sql = "SELECT A.*,to_char(CYSJ,'yyyy-mm-dd hh24:mi:ss') as CYRQ,to_char(SSCZRI,'yyyy-mm-dd hh24:mi:ss') as SSCZRQ,to_char(SSKSSJ,'yyyy-mm-dd hh24:mi:ss') as SSKSSJ,to_char(SSJSSJ,'yyyy-mm-dd hh24:mi:ss') as SSJSSJ FROM PORTAL_HIS.V_JMGS_BASY_SS A WHERE A.ZYH=" . $ZYH;
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }

        return $result;
    }

    public function addOperation($data, $config)
    {
        $main = [];
        $other = [];
        foreach ($data as $val) {
            $addData = [
                'AAA28' => $val['ZYH'],
                'ICD9_ID1' => $val['SSCZBM'] ?? '', //手术或操作ID
                'ICD9_NAME' => $val['SSCZMC'] ?? '', //手术或操作名称
                'OPE_DATE' => $val['SSCZRQ'] ?? '', //手术或操作日期
                'OPE_ORDER' => $val['SSSX'] ?? '', //手术序号
                'OPE_LEVEL' => $val['SSJB'] ?? '', //手术级别
                'OPE_TYPE' => $val['SSLX'] ?? '', //手术类型
                'OPE_MAN_NAME' => $val['SZXM'] ?? '', //主刀医师姓名
                'OPE_MAN_CODE' => $val['SZBM'] ?? '', //主刀医师编码
                'FRIST_ASSISTANT_CODE' => $val['YZYSBM'] ?? '', //一助医师编码
                'FRIST_ASSISTANT_NAME' => $val['YZXM'] ?? '', //一助医师姓名
                'SECOND_ASSISTANT_CODE' => $val['EZYSBM'] ?? '', //二助医师编码
                'SECOND_ASSISTANT_NAME' => $val['EZXM'] ?? '', //二助医师姓名
                'INCISION_GRADE_ID' => $val['QKDJ'] ?? 100, //切口等级
                'HEAL_ID' => $val['YHDJ'] ?? 100, //愈合等级
                'HOCUS_WAY_ID' => $val['MZFS'] ?? '', //麻醉方式
                'HOCUS_MAN_CODE' => $val['MZYSBM'] ?? '', //麻醉医师编码
                'HOCUS_MAN_NAME' => $val['MZYSXM'] ?? '', //麻醉医师名称
                'START_TIME' => $val['SSKSSJ'] ?? '', //手术开始时间
                'END_TIME' => $val['SSJSSJ'] ?? '', //手术结束时间
                'RJSS' => $val['SFWRJSS'] ?? '', //是否日间手术
                'CYRQ' => $val['CYRQ'] ?? '',
                'SFZYSS' => $val['SFZYSS'] ?? '',
                'QKDJ' => $val['QKDJ'] ?? '',
                'YHDJ' => $val['YHDJ'] ?? '',
                'BAHM' => $val['BAHM'] ?? '',
                'ZYHM' => $val['ZYHM'] ?? '',
                'SSPB' => intval(array_search($val['SSPB'], $config['SSPB']) ?? 5) //手术判别
            ];

            if ($val['SFZYSS'] == 1) {
                $main[] = $addData;
            } else {
                $other[] = $addData;
            }
        }

        // 主要手术
        MainOperation::query()->where('AAA28', '=', $data[0]['ZYH'])->delete();
        if (!empty($main)) {
            MainOperation::query()->insert($main);
        }
        // 其他手术
        SecondaryOperation::query()->where('AAA28', '=', $data[0]['ZYH'])->delete();
        if (!empty($other)) {
            SecondaryOperation::query()->insert($other);
        }
    }

    /**
     * 补充信息
     * @param $ZYH
     * @return array
     */
    public function getBuChong($ZYH)
    {
        //        $sql = "SELECT A.*,to_char(ZKRQ,'yyyy-mm-dd hh24:mi:ss') as ZKRQ1 FROM PORTAL_HIS.V_JMGS_BASY_FY A WHERE A.ZYH=".$ZYH;
        $sql = "SELECT A.YBYLFWF,A.YBZLCZF,A.HLF,A.ZHYLFWLQTFY,A.BLZDF,A.SYSZDF,A.YXXZDF,A.LCZDXMF,A.FSSZLXMF,A.LCWLZLF,A.SSZLF,A.MZF,A.SSF,A.KFF,A.ZYZLF,A.XYF,A.KJYWF,A.ZCHENGYF,A.ZCAOYF,A.XF,A.BDBLZPF,A.QDBLZPF,A.NXYZLZPF,A.XBYZLZPF,A.JCYYCXYYCLF,A.ZLYYCXYYCLF,A.SSYYCXYYCLF,A.QTF,A.TYSHXYDM,A.JKKH,A.ZJLB,A.SJHL,A.EJHL,A.YJHL,A.TJHL,A.ZRHS,A.ZRHSBM,A.ZKHS,A.ZKHSBM,A.ZHFZRYS,A.ZZYSBM,A.ZYYSBM,A.ZZZYSBM,A.KZRXM,A.ZHFZRYSXM,A.ZZYSXM,A.ZYYSXM,A.ZZYISXM,A.BMY,A.RYKB,A.BFRY,A.ZKKB,A.CYKB,A.HB,A.HCV,A.HIV,A.LCLJ,A.WCQK,A.BYQK,to_char(A.ZKRQ,'yyyy-mm-dd hh24:mi:ss') as ZKRQ1 FROM PORTAL_HIS.V_JMGS_BASY_FY A WHERE A.ZYH=" . $ZYH;
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }

        return $result;
    }

    public function addBuChong($ZYH, $data)
    {
        // 费用信息
        $feeData = [
            'ADA0101' => $data['ZFFY'] ?? 0,
            'D11' => $data['YBYLFWF'] ?? '',
            'D12' => $data['YBZLCZF'] ?? '',
            'D13' => $data['HLF'] ?? '',
            'D14' => $data['ZHYLFWLQTFY'] ?? '',
            'D15' => $data['BLZDF'] ?? '',
            'D16' => $data['SYSZDF'] ?? '',
            'D17' => $data['YXXZDF'] ?? '',
            'D18' => $data['LCZDXMF'] ?? '',
            'D19' => $data['FSSZLXMF'] ?? '',
            'D19X01' => $data['LCWLZLF'] ?? '',
            'D20' => $data['SSZLF'] ?? '',
            'D20X01' => $data['MZF'] ?? '',
            'D20X02' => $data['SSF'] ?? '',
            'D21' => $data['KFF'] ?? '',
            'D22' => $data['ZYZLF'] ?? '',
            'D23' => $data['XYF'] ?? '',
            'D23X01' => $data['KJYWF'] ?? '',
            'D24' => $data['ZCHENGYF'] ?? '',
            'D25' => $data['ZCAOYF'] ?? '',
            'D26' => $data['XF'] ?? '',
            'D27' => $data['BDBLZPF'] ?? '',
            'D28' => $data['QDBLZPF'] ?? '',
            'D29' => $data['NXYZLZPF'] ?? '',
            'D30' => $data['XBYZLZPF'] ?? '',
            'D31' => $data['JCYYCXYYCLF'] ?? '',
            'D32' => $data['ZLYYCXYYCLF'] ?? '',
            'D33' => $data['SSYYCXYYCLF'] ?? '',
            'D34' => $data['QTF'] ?? '',
        ];
        PatientCostInfo::query()->updateOrInsert(['AAA28' => $ZYH], $feeData);

        // 补充信息
        $addData = [
            'TYSHXYDM' => $data['TYSHXYDM'] ?? '',
            'JKKH' => $data['JKKH'] ?? '',
            'SFZJLX' => $data['ZJLB'] ?? '',
            'SJHL' => $data['SJHL'] ?? '',
            'EJHL' => $data['EJHL'] ?? '',
            'YJHL' => $data['YJHL'] ?? '',
            'TJHL' => $data['TJHL'] ?? '',
            'ZRHS' => $data['ZRHS'] ?? '',
            'ZRHSBM' => $data['ZRHSBM'] ?? '',
            'ZKHS' => $data['ZKHS'] ?? '',
            'ZKHSBM' => $data['ZKHSBM'] ?? '',
            'ZKRQ' => $data['ZKRQ1'] ?? '',
            'ZHFZRYS' => $data['ZHFZRYS'] ?? '',
            'ZZYSBM' => $data['ZZYSBM'] ?? '',
            'ZYYSBM' => $data['ZYYSBM'] ?? '',
            'ZZZYSBM' => $data['ZZZYSBM'] ?? '',
            'KZRXM' => $data['KZRXM'] ?? '',
            'ZHFZRYSXM' => $data['ZHFZRYSXM'] ?? '',
            'ZZYSXM' => $data['ZZYSXM'] ?? '',
            'ZYYSXM' => $data['ZYYSXM'] ?? '',
            'ZZYISXM' => $data['ZZYISXM'] ?? '',
            'BMY' => $data['BMY'] ?? '',
            'RYKB' => $data['RYKB'] ?? '',
            'BFRY' => $data['BFRY'] ?? '',
            'ZKKB' => $data['ZKKB'] ?? '',
            'CYKB' => $data['CYKB'] ?? '',
            'HB' => $data['HB'] ?? '',
            'HCV' => $data['HCV'] ?? '',
            'HIV' => $data['HIV'] ?? '',
            'LCLJ' => $data['LCLJ'] ?? '', // 临床路径
            'WCQK' => $data['WCQK'] ?? '',
            'BYQK' => $data['BYQK'] ?? '',
        ];
        PatientAdd::query()->updateOrInsert(['AAA28' => $ZYH], $addData);

        return true;
    }

    /**
     * 医嘱
     * @param $ZYH
     * @return array
     */
    public function getYzb($ZYH)
    {
        $sql = "SELECT A.*,to_char(TZSJ,'yyyy-mm-dd hh24:mi:ss') as TJ,to_char(XZJDSJ,'yyyy-mm-dd hh24:mi:ss') as XJ,to_char(TZQRSJ,'yyyy-mm-dd hh24:mi:ss') as TZJ,to_char(APSJ,'yyyy-mm-dd hh24:mi:ss') as AJ,to_char(KZSJ,'yyyy-mm-dd hh24:mi:ss') as KJ FROM PORTAL_HIS.BTF_EMR_YZB A WHERE YZZT != 3 AND ZYH=" . $ZYH;
        $data = oci_parse(self::$con, $sql);
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
                    'KZSJ' => $val['KJ'] ?? '',
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
                    'TZSJ' => $val['TJ'] ?? '',
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

    public function formatBL01BLMC306($bl01 = [])
    {

        $blmc = $bl01['NA_MED'];
        //获取年份
        $cjsj = $bl01['CJSJ'];
        $year = date('Y', strtotime($cjsj));
        //获取cjsj的月份
        $cjsj_month = date('m', strtotime($cjsj));

        // 更灵活地匹配时间格式
        preg_match("/.*?(\d{2}\.\d{2} \d{2}:\d{2})/", $blmc, $timeMatches);

        $timePrefix = !empty($timeMatches[1]) ? $timeMatches[1] : '';

        if ($bl01['BLLB'] == 329 && !strpos($blmc, "手术同意书") && !strpos($blmc, "手术知情同意书")) {
            $HTML_PRINT = !empty($bl01['HTML_PRINT']) ? $bl01['HTML_PRINT'] : '';
            if (strpos($HTML_PRINT, "手术知情同意书") !== false) {
                $blmc = $blmc . "手术知情同意书";
            }
        }

        if (!empty($timePrefix)) {
            // 正确解析MM.DD HH:MM格式
            if (preg_match('/(\d{2})\.(\d{2}) (\d{2}):(\d{2})/', $timePrefix, $parts)) {
                $month = $parts[1];
                $day = $parts[2];
                $hour = $parts[3];
                $minute = $parts[4];

                //如果创建时间是12月,month是01,年份加1
                if ($cjsj_month == 12 && $month == 1) {
                    $year = $year + 1;
                }

                // 直接构建标准格式
                $timePrefix = $month . '-' . $day . ' ' . $hour . ':' . $minute;
            } else {
                $timePrefix = null;
            }

            //拼上年份
            if (!empty($timePrefix)) {
                $timePrefix = $year . '-' . $timePrefix;
            } else {
                $timePrefix = null;
            }

            //删除原本的时间格式
            $blmc = preg_replace("/(\d{2}\.\d{2} \d{2}:\d{2})/", "", $blmc);
            $blmc = preg_replace("/\s+/", "", $blmc); //去除空格
            //拼接上新的时间
            $blmc = $timePrefix . ' ' . $blmc;
            //更新blmc
            if (!empty($timePrefix)) {
                EMR_BL_BL01::query()->where('BLBH', $bl01['BLBH'])->update(['BLMC' => $blmc, 'ZXSJ' => $timePrefix]);
            }
        }
    }

    public function formatBL01BLMC82($bl01 = [])
    {

        $blmc = $bl01['NA_MED'];
        $blmc = preg_replace("/(\d{2}\.\d{2} \d{2}:\d{2})/", "", $blmc);
        $blmc = preg_replace("/\s+/", "", $blmc); //去除空格
        // 首先尝试匹配"记录时间：{YYYY-MM-DD HH:MM}"格式
        $bl01['HJNR'] = preg_replace('/[\p{Z}\s\x{00A0}\x{1680}\x{180E}\x{2000}-\x{200D}\x{2028}\x{2029}\x{202F}\x{205F}\x{2060}\x{3000}\x{FEFF}]+/u', '', $bl01['HJNR']);
        preg_match("/术前小结及术前讨论结论记录\s*(\d{4}-\d{2}-\d{2}\d{2}:\d{2})/", $bl01['HJNR'], $timeMatches);
        $timePrefix = !empty($timeMatches[1]) ? $timeMatches[1] : '';
        $timePrefix = substr($timePrefix, 0, 10) . ' ' . substr($timePrefix, 10);
        $blmc = $timePrefix . ' ' . $blmc;

        if (!empty($timePrefix)) {
            EMR_BL_BL01::query()->where('BLBH', $bl01['BLBH'])->update(['BLMC' => $blmc, 'ZXSJ' => $timePrefix]);
        }
    }

    /**
     * @param array $bl01
     * 格式化bl01表中的病例名称
     */
    public function formatBL01BLMC294($blbh = "", $str = "")
    {

        $bl01 = EMR_BL_BL01::query()->where('BLBH', $blbh)->first()->toArray();
        $cleanblmckey = RuleWordMap::query()->where('id', '4018')->value('keyword');
        if (strpos($cleanblmckey, ',') !== false) {
            $cleanblmckey = explode(',', $cleanblmckey);
        } else {
            $cleanblmckey = [$cleanblmckey];
        }
        $bl01["HJNR"] = $str;

        $blmc = $bl01['BLMC'];
        // 处理BLMC，去掉包含cleanblmckey的
        foreach ($cleanblmckey as $key) {
            $blmc = str_replace($key, '', $blmc);
        }
        if (!empty($bl01['HJNR'])) {
            // 从HJNR中提取时间  2024-05-14 09:00:00
            preg_match("/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/", $bl01['HJNR'], $timeMatches);
            //增加格式匹配 '时间：2024-05-1411:33:12 姓名：性别：女
            preg_match("/^时间：(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", $bl01['HJNR'], $timeMatches2);
            //增加格式匹配 '2024-05-14 11:33'不包含秒
            preg_match("/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", $bl01['HJNR'], $timeMatches3);
            //增加格式匹配 '{2024-08-01 19:20}'花括号包围的时间格式
            preg_match("/\{(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\}/", $bl01['HJNR'], $timeMatches4);
            //增加格式匹配 '病案号:{00347580}2024-07-31 10:12'病案号后面的时间格式
            preg_match("/病案号:[\{\(]?\d+[\}\)]?(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", $bl01['HJNR'], $timeMatches5);
            //增加格式匹配 '重症医学二科病案号:003703542025-04-14 14:53危急值记录' 从取出2025-04-14 14:53
            preg_match("/重症医学二科病案号:[\{\(]?\d+[\}\)]?(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", $bl01['HJNR'], $timeMatches6);

            $timePrefix = '';
            if (!empty($timeMatches[1])) {
                $timePrefix = $timeMatches[1];
            } else if (!empty($timeMatches2[1])) {
                $timePrefix = $timeMatches2[1];
            } else if (!empty($timeMatches3[1])) {
                $timePrefix = $timeMatches3[1];
            } else if (!empty($timeMatches4[1])) {
                $timePrefix = $timeMatches4[1];
            } else if (!empty($timeMatches5[1])) {
                $timePrefix = $timeMatches5[1];
            }

            if ($timePrefix != '') {
                // 检查BLMC中是否已包含重复的时间格式
                // 先检查是否包含完全相同的时间格式
                $pattern = "/(\d{4}-\d{2}-\d{2} \d{2}:\d{2})([ ]+)\\1/";
                if (preg_match($pattern, $blmc)) {
                    // 如果存在重复的时间格式，去除重复部分
                    $blmc = preg_replace($pattern, "$1", $blmc);
                }

                //判断blmc是否包含时间格式,如果已经包含时间格式,跟timePrefix对比,如果相同就不改变,如果不同就替换
                $newblmc = $blmc;

                //如果原本的blmc中包含01.02 08:09这种格式，先删除
                $blmc = preg_replace("/(\d{2}\.\d{2} \d{2}:\d{2})/", "", $blmc);

                //如果blmc中包含2025.01.02 08:09这种格式，先删除
                $blmc = preg_replace("/(\d{4}\.\d{2}\.\d{2} \d{2}:\d{2})/", "", $blmc);

                //如果blmc中包含2025-01-02 08:09这种格式，先删除
                $blmc = preg_replace("/(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", "", $blmc);

                //判断blmc是否包含时间格式
                preg_match("/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/", $blmc, $timeMatches1);

                //最后删除所有空格
                $blmc = preg_replace("/\s+/", "", $blmc);

                // 增加对不带秒的时间格式的检查
                if (empty($timeMatches1[1])) {
                    preg_match("/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/", $blmc, $timeMatches1);
                }

                if (!empty($timeMatches1[1])) {
                    if ($timeMatches1[1] != $timePrefix) {
                        //替换blmc中的时间格式
                        $newblmc = str_replace($timeMatches1[1], $timePrefix, $blmc);
                    }
                } else {
                    $newblmc = $timePrefix . ' ' . $blmc;
                }
                EMR_BL_BL01::query()->where('BLBH', $bl01['BLBH'])->update(['BLMC' => $newblmc, 'ZXSJ' => $timePrefix]);
            } else {
                EMR_BL_BL01::query()->where('BLBH', $bl01['BLBH'])->update(['BLMC' => $blmc]);
            }
        } else {
            EMR_BL_BL01::query()->where('BLBH', $bl01['BLBH'])->update(['BLMC' => $blmc]);
        }
    }

    /**
     * @param string $zyh
     * @param string $blbh
     * @return bool
     * 同步病程记录相关数据
     */
    public function addBLSY($zyh = "", $blbh3 = "", $isRecordLog = 1, $cfjd = '', $qmys = '', $qmrq = '')
    {
        $blbh1 = ""; //三院同步所有病历

        if ($isRecordLog == 1) {
            DataSyncLog::addData(['zyh' => $zyh, 'content' => '4、数据病程记录开始']);
        }
        $bldf = new BlDataFormatService();
        $sql = "SELECT ID_TEP,ID_HOSPITAL, ID_MEDRECDOC, ID_MEDI, ID_DS, NA_MED, CD_CREATE, CD_DEPT, FG_ACTIVE, NA_MECA,to_char(FINISH_TIME,'yyyy-mm-dd hh24:mi:ss') as WCSJ,to_char(CREATE_TIME,'yyyy-mm-dd hh24:mi:ss') as CJSJ,to_char(UPDATE_TIME,'yyyy-mm-dd hh24:mi:ss') as ZXSJ,to_char(TITLE_TIME,'yyyy-mm-dd hh24:mi:ss') as JLSJ,UPDATE_USER,HTML_TEXT,HTML_PRINT,FG_PRINT FROM WHIS_EMR.HI_VIEW_REC_ZY_HTML a WHERE ID_HOSPITAL = '{$zyh}'";
        if (!empty($blbh1)) {
            $sql .= " and ID_MEDRECDOC='{$blbh1}'";
        }
        $result = oci_parse(self::$con2, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if ($isRecordLog == 1) {
            DataSyncLog::addData(['zyh' => $zyh, 'content' => '5、本次同步数据量', 'data_nums' => count($data)]);
        }
        if (empty($data) && !empty($zyh)) {
            if ($isRecordLog == 1) {
                DataSyncLog::addData(['zyh' => $zyh, 'content' => '6、未获取到病程记录，清空mysql中的病程记录']);
            }
            // 未同步到数据则清空bl01
            EsSaveService::deleteBl01ByZyh($zyh);
            return false;
        }

        if (empty($blbh1)) {
            $diffBLBH = array_column($data, "ID_MEDRECDOC");
            // 检查数据表中有没有不在新同步的数据中的blbh
            $diffRes = EMR_BL_BL01::query()->where('JZHM', $zyh)->whereNotIn("BLBH", $diffBLBH)->get(["BLBH"])->toArray();
            if ($diffRes) {
                foreach ($diffRes as $blbh) {
                    if ($isRecordLog == 1) {
                        DataSyncLog::addData(['zyh' => $zyh, 'content' => '7、删除mysql中的病程记录：' . $blbh['BLBH']]);
                    }
                    EsSaveService::deleteBl01ByBLBH($blbh['BLBH']);
                }
            }
        } else {
            ////DataSyncLog::addData(['zyh' => $zyh, 'content' => '7.1、病历编号：' . $blbh3]);
            EsSaveService::deleteBl01ByBLBH($blbh1);
        }

        ////DataSyncLog::addData(['zyh' => $zyh, 'content' => '7.7、同步病程记录开始']);


        $sj = PatientInfo::query()->where('MED_REC_ID', $zyh)->first(['MED_REC_ID', 'AAB01', 'AAC01']);
        foreach ($data as $item) {

            if ($isRecordLog == 1) {
                DataSyncLog::addData(['zyh' => $zyh, 'content' => '8、同步病程记录：' . $item['ID_MEDRECDOC']]);
            }
            //将三院的BLOB类型的数据转化为字符串
            $HTML_PRINT = !empty($item['HTML_PRINT']) && !empty($item['HTML_PRINT']->size()) ? $item['HTML_PRINT']->load() : '';
            $str = removeHtmlAndHiddenElements($HTML_PRINT);

            $result = [
                'JZHM' => $item['ID_HOSPITAL'] ?? '',
                'BLBH' => $item['ID_MEDRECDOC'] ?? '',
                'BLBH3' => $item['ID_MEDRECDOC'] ?? '',
                'NA_MECA' => $item['NA_MECA'] ?? '',
                'BLLB' => in_array($item['ID_TEP'], array_keys(config("bl.shoushu"))) ? config("bl.shoushu")[$item['ID_TEP']]['BLLB'] : (in_array($item['NA_MECA'], array_keys(config("bl"))) ? config("bl")[$item['NA_MECA']]['BLLB'] : ''),
                'MBLB' => in_array($item['ID_TEP'], array_keys(config("bl.shoushu"))) ? config("bl.shoushu")[$item['ID_TEP']]['MBLB'] : (in_array($item['NA_MECA'], array_keys(config("bl"))) ? config("bl")[$item['NA_MECA']]['MBLB'] : ''),
                'BLMC' => $item['NA_MED'] ?? '',
                'SXYS' => $item['CD_CREATE'] ?? '',
                'BLZT' => $item['FG_ACTIVE'] ?? '',
                'BRKS' => $item['CD_DEPT'] ?? '',
                'AAB01' => $sj->AAB01 ?? '',
                'AAC01' => $sj->AAC01 ?? '',
                'ID_DS' => $item['ID_DS'] ?? '',
                'HTML_PRINT' => $HTML_PRINT,
                'ID_TEP' => $item['ID_TEP'] ?? '',
                'CJSJ' => $item['CJSJ'] ?? '',
                'WCSJ' => $item['WCSJ'] ?? '',
                'ZXSJ' => $item['ZXSJ'] ?? '',
                'JLSJ' => $item['JLSJ'] ?? '',
                'YWSJ' => $item['JLSJ'] ?? '',
                'NA_MED' => $item['NA_MED'] ?? '',
            ];
            $blbh = $item['ID_MEDRECDOC'];
            if ($result['BLLB'] == 303) {
                $MBLB = $this->text_name($HTML_PRINT);
                $result['MBLB'] = $MBLB;

                if (strpos($result['BLMC'], '粘贴') !== false || strpos($result['BLMC'], '化验') !== false) {
                    $result['MBLB'] = 30306;
                }
                if (strpos($result['BLMC'], '核查') !== false) {
                    $result['MBLB'] = 30375;
                }
            }
            //如果医院大类是日常病程记录，并且表头包含会诊记录文字的，将MBLB修改为会诊记录 32
            if ($item['NA_MECA'] == "日常病程记录" && stripos($item['NA_MED'], "会诊记录") !== false) {
                $result['MBLB'] = 32;
            }

            $temp = [
                'JZHM' => $item['ID_HOSPITAL'] ?? '',
                'JLXH' => $item['ID_MEDRECDOC'] ?? '',
                'XGGH' => $item['UPDATE_USER'] ?? '',
                'XGSJ' => $item['ZXSJ'] ?? '',
                'HJNR' => $str,
                'BLBH3' => $item['ID_MEDRECDOC'] ?? '',
                'BLBH' => $item['ID_MEDRECDOC'] ?? ''
            ];

            if ($result['MBLB'] == 295) {
                $result['bl_type'] = 1;
            }

            //处理死亡记录
            if (strpos($result['BLMC'], "死亡记录") !== false) {
                $result['BLLB'] = 288;
                $result['MBLB'] = 288;
            }
            $bl01Data = $result;
            EMR_BL_BL01::query()->updateOrInsert(['BLBH3' => $item['ID_MEDRECDOC']], $result); //插入或更新病历数据
            EMR_BL_BLXG::query()->updateOrInsert(['BLBH3' => $item['ID_MEDRECDOC']], $temp); //插入或更新BLXG数据

            //dyjl
            $dyjl = [
                'BLBH' => $item['ID_MEDRECDOC'] ?? '',
                'SFDY' => $item['FG_PRINT'] ?? '',
            ];
            //根据BLBH更新或插入
            EMR_BL01_DYJL::query()->updateOrInsert(['BLBH' => $item['ID_MEDRECDOC']], $dyjl);
            

            $sql = "SELECT  JLXH,QMLX,BLBH,SYYS,to_char(SYSJ,'yyyy-mm-dd hh24:mi:ss') as SYSJTIME,to_char(JLSJ,'yyyy-mm-dd hh24:mi:ss') as JLSJTIME,FG_ACTIVE,QMYS FROM EMR_BL_BLSY WHERE BLBH = '" . $item['ID_MEDRECDOC'] . "'";
            $resultSet = oci_parse(self::$con2, $sql);
            oci_execute($resultSet, OCI_DEFAULT);
            $data = [];
            while ($row = oci_fetch_assoc($resultSet)) {
                $data[] = $row;
            }
            if (!empty($data)) {
                //DataSyncLog::addData(['zyh' => $zyh, 'content' => '7.2、同步病程记录签名数据开始，数据量：' . count($data)]);
                foreach ($data as $v) {
                    //DataSyncLog::addData(['zyh' => $zyh, 'content' => '7.2.1、同步病程记录签名数据：' . $v['JLXH']]);
                    $QMMC = 0;
                    switch ($v['QMYS']) {
                        case '主刀医师签名':
                            $QMMC = 6;
                            break;
                        case '医师签名':
                        case '科室负责人签名':
                        case '主持人签名':
                        case '会诊专家1签名':
                        case '会诊专家2签名':
                        case '会诊专家3签名':
                        case '会诊专家签名':
                        case '医师签名-1':
                        case '医师签名-2':
                        case '医师签名-3':
                        case '审核医师签名':
                        case '麻醉医师签名':
                            $QMMC = 5;
                            break;
                        case '患者签名':
                        case '患者1签名':
                        case '患者2签名':
                        case '患方0签名':
                        case '患方1签名':
                        case '患方2签名':
                        case '患方3签名':
                        case '患者2签名':
                        case '陈述者签名':
                            $QMMC = 3;
                            break;
                        case '被授权人签名':
                        case '被授权者签名':
                        case '被授权人0签名':
                        case '被授权者1签名':
                        case '被授权人2签名':
                            $QMMC = 4;
                            break;
                        case '患者手写意见1':
                        case '患方手写意见':
                        case '患者手写意见':
                        case '患方手写意见1':
                        case '患者手写意见2':
                        case '患方手写意见2':
                        case '患者手写意见3':
                        case '患方手写意见3':
                            $QMMC = 1;
                            break;
                        case '护士签名':
                            $QMMC = 7;
                            break;
                    }
                    $insertData = [
                        'JLXH' => $v['JLXH'],
                        'BLBH3' => $v['BLBH'],
                        'BLBH' => $v['BLBH'],
                        'SYYS' => $v['SYYS'],
                        'SYSJ' => $v['SYSJTIME'],
                        'JLSJ' => $v['JLSJTIME'],
                        'FG_ACTIVE' => $v['FG_ACTIVE'],
                        'QMLX' => $v['QMLX'] == 1 ? 1 : 2,
                        'QMMC' => $QMMC,
                        'QMYS' => $v['QMYS'],
                    ];
                    EMR_BL_BLSY::query()->updateOrInsert(['JLXH' => $insertData['JLXH'], 'BLBH3' => $insertData['BLBH3']], $insertData);
                }
                //DataSyncLog::addData(['zyh' => $zyh, 'content' => '7.3、同步病程记录签名数据结束']);
            } else {
                //如果cfjd=2；把传入的签名数据更新上去
                if ($cfjd == 2 && $blbh == $blbh3) {
                    //获取最大id的JLXH，然后拼接一个‘1’
                    $maxIdJlzh = EMR_BL_BLSY::query()->max('id');
                    $maxJlXh = $maxIdJlzh + 1;
                    $insertData = [
                        'JLXH' => $maxJlXh,
                        'BLBH3' => $blbh3,
                        'BLBH' => $blbh3,
                        'SYYS' => $qmys,
                        'SYSJ' => $qmrq,
                        'JLSJ' => $qmrq,
                        'QMLX' => 1,
                        'FG_ACTIVE' => 1,
                        'is_cfjd' => '1'
                    ];
                    EMR_BL_BLSY::query()->updateOrInsert(['JLXH' => $insertData['JLXH'], 'BLBH3' => $insertData['BLBH3']], $insertData);
                }
            }

            //处理首次签名时间
            $ruleMapMBLB = RuleWordMap::query()->where('id', '=', 73)->value('keyword');
            $ruleMapMBLB = explode(',', $ruleMapMBLB);
            $first_blsy_time = EMR_BL_BL01::query()->where('BLBH', '=', $blbh)
                ->whereIn('MBLB', $ruleMapMBLB)->value('first_blsy_time');
            if (empty($first_blsy_time)) {
                $BLSY = EMR_BL_BLSY::query()->where('BLBH', '=', $blbh)->orderBy('JLSJ', 'asc')->first(['JLSJ']);
                if (!empty($BLSY)) {
                    $BLSY = $BLSY->toArray();
                    EMR_BL_BL01::query()->where('BLBH', '=', $blbh)->update(['first_blsy_time' => $BLSY['JLSJ']]);
                }
            }

            if ($bl01Data["BLLB"] == 2000001) {
                // 查询签名记录并按SYYS分组
                $blsyGroups = EMR_BL_BLSY::query()
                    ->where('BLBH', '=', $blbh)
                    ->get()
                    ->groupBy('SYYS')
                    ->toArray();
                foreach ($blsyGroups as $syys => $records) {
                    // 获取每组签名记录中最早的签名时间
                    $earliestTime = null;
                    foreach ($records as $record) {
                        $jlsj = strtotime($record['JLSJ']);
                        if (is_null($earliestTime) || $jlsj < $earliestTime) {
                            $earliestTime = $jlsj;
                        }
                    }

                    if (!is_null($earliestTime)) {
                        $earliestSignTimes[] = $earliestTime;
                    }
                }

                // 获取所有最早签名中的最晚时间点
                $latestOfEarliest = !empty($earliestSignTimes) ? max($earliestSignTimes) : null;
                if (!is_null($latestOfEarliest)) {
                    EMR_BL_BL01::query()->where('BLBH', '=', $blbh)->update(['first_blsy_time' => date('Y-m-d H:i:s', $latestOfEarliest)]);
                }
            }

            // 格式化病例名称
            $bl01Data["HJNR"] = $str;
            /* if ($bl01Data['MBLB'] == 82) {
                $this->formatBL01BLMC82($bl01Data);
            } else */
            if ($bl01Data["BLLB"] == 294 || $bl01Data["BLLB"] == 43 || $bl01Data["BLLB"] == 329) {
                // $this->formatBL01BLMC294($blbh, $str);
                $this->formatBL01BLMC306($bl01Data);
            } elseif ($bl01Data["BLLB"] == 303) {
                $this->formatBL01BLMC306($bl01Data);
            }
            if ($bl01Data['MBLB'] == 59) {
                $this->formatMBLB59($bl01Data);
            }
            //$bldf->insertData([], $bl01Data["MBLB"], $bl01Data);
        }
        EsSaveService::bl01($zyh);
    }

    public function formatMBLB59($bl01Data)
    {
        $dom = removeHtmlAndHiddenElements($bl01Data["HJNR"], true);
        $text = $dom->textContent;
        $text = str_replace(' ', '', $text);
        $addData = [];
        // 匹配患者姓名
        if (preg_match('/患者姓名[：:]\s*(\S+)性别/u', $text, $matches)) {
            $addData['name'] = $matches[1];
        }

        // 匹配性别
        if (preg_match('/性别[：:]\s*(男|女)年龄/u', $text, $matches)) {
            $addData['sex'] = $matches[1];
        }

        // 匹配年龄（支持"岁"或纯数字）
        if (preg_match('/年龄[：:]\s*(\d+)\s*(?:岁)?科室/u', $text, $matches)) {
            $addData['age'] = $matches[1];
        }

        // 匹配科室
        if (preg_match('/科室[：:]\s*(\d+)病案号/u', $text, $matches)) {
            $addData['department'] = $matches[1];
        }

        // 匹配病案号（支持多种格式）
        if (preg_match('/病案号[：:]\s*([0-9\-]+)/u', $text, $matches)) {
            $addData['blbh'] = '';
        }

        return '';
    }

    /**
     * 根据病历名称获取病历名称
     *
     * @param string $hjnr 病历内容
     * @param int $mblb 病历类型
     * @return string
     */
    public function getMatchResultByMblb($hjnr, $mblb)
    {
        $result = '';
        if (!empty($hjnr)) {
            $hjnr = str_replace("：", ":", $hjnr);

            switch ($mblb) {
                case 26: //阶段小结
                    $patten = "/\{(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\}(阶段小结)/u";
                    break;
                case 27: //抢救记录
                    $patten = "/\{(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\}(抢救记录)/u";
                    break;
                case 30: //转接科记录
                    $patten = "/\{(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\}(转入记录|转出记录)/u";
                    break;
                case 32: //会诊记录
                    $patten = "/\{(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\}(会诊记录)/u";
                    break;
                case 42: //术后首次病程记录
                    $patten = "/\{(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\}(术后首次病程记录)/u";
                    break;
                case 45: //输血记录
                    $patten = "/\{(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\}(输血记录)/u";
                    break;
                case 50: //查房记录
                    $patten = "/(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\s+(.*?查房记录)/u";
                    break;
                case 82: //术前小结
                    $patten = "/\{(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\}(术前小结及术前讨论结论记录)/u";
                    break;
                case 295: //首次病程记录
                    $patten = "/\{(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\}(首次病程记录)/u";
                    break;
                case 296: //日常病程记录
                    $patten = "/\{(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\}.*(病程记录|操作记录|产后记录)/u";
                    break;
                default:
                    $patten = "";
            }
            if (!empty($patten)) {
                preg_match_all($patten, $hjnr, $match);
                if (isset($match[0]) && !empty($match[0][0])) {
                    $result = str_replace(["{", "}"], "", $match[0][0]);
                } else {
                    $result = '';
                }
            }
        }
        return $result;
    }


    public function text_name($text)
    {

        $text = str_replace([' ', "\t", "\n", "\r\n", ' '], '', $text);
        $MBLB = 306;
        $bl = [
            '306' => '手术记录',
            '30301' => '剖宫产手术记录',
            '30375' => '手术安全核查表',
            '75' => '手术安全核查表',
            '3030002' => '手术记录附页',
            '3030001' => '条形码粘贴（信息记录）单',
        ];
        foreach ($bl as $k => $v) {
            if (strstr($text, $v)) {
                $MBLB = $k;
            }
        }
        return $MBLB;
    }


    /**
     * @param $zyh
     * 医生签名
     */
    public function addBLSY2($data)
    {
        //        $sql = "SELECT a.*,to_char(ZXSJ,'yyyy-mm-dd hh24:mi:ss') as ZJ,to_char(CJSJ,'yyyy-mm-dd hh24:mi:ss') as CJ,to_char(WCSJ,'yyyy-mm-dd hh24:mi:ss') as WJ,to_char(XGSJ,'yyyy-mm-dd hh24:mi:ss') as XJ FROM PORTAL55_EMR.V_JMGS_BASY_QBL a WHERE a.ZYH={$zyh}";
        //        $result = oci_parse(self::$con, $sql);
        //        oci_execute($result, OCI_DEFAULT);
        //        $data = [];
        //        while ($row = oci_fetch_assoc($result)) {
        //            $data[] = $row;
        //        }
        if (empty($data)) {
            return false;
        }
        $blbh = [];
        foreach ($data as $item) {
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
                'WZZT' => $item['WZZT'] ?? '',
                'bl_type' => 0
            ];
            $str = $item['HJNR']; //$this->blobToStr($item['BLNR']);
            if (empty($str)) {
                continue;
            }
            if (
                in_array($item['MBLB'], [295, 129]) ||
                ($item['BLLB'] == 294 && (strpos($str, '病例特点') !== false || strpos($str, '鉴别诊断') !== false))
            ) {
                $result['bl_type'] = 1;
            }
            $temp = [
                'JLXH' => $item['JLXH'] ?? '',
                'BLBH' => $item['BLBH'] ?? '',
                'XGGH' => $item['XGGH'] ?? '',
                'XGSJ' => $item['XJ'] ?? '',
                'HJNR' => $str,
            ];
            EMR_BL_BL01::query()->updateOrInsert(['BLBH' => $item['BLBH']], $result);
            EMR_BL_BLXG::query()->updateOrInsert(['BLBH' => $item['BLBH']], $temp);
            $blbh[] = $item['BLBH'];
        }
        $blbhStr = implode(',', $blbh);
        $sql = "select JLXH,BLBH,SYYS,to_char(SYSJ,'yyyy-mm-dd hh24:mi:ss') as SYSJ,to_char(JLSJ,'yyyy-mm-dd hh24:mi:ss') as JLSJ from PORTAL55_EMR.EMR_BL_BLSY WHERE BLBH in ($blbhStr)";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }

        foreach ($data as $item) {
            $insertData = [
                'JLXH' => $item['JLXH'],
                'BLBH' => $item['BLBH'],
                'SYYS' => $item['SYYS'],
                'SYSJ' => $item['SYSJ'],
                'JLSJ' => $item['JLSJ'],
            ];

            \App\Model\EMR_BL_BLSY::query()->updateOrInsert(['JLXH' => $insertData['JLXH']], $insertData);
        }
    }


    /**
     * @param $zyh
     * 医生签名
     */
    public function getBLSY($zyh)
    {
        $sql = "SELECT a.*,to_char(ZXSJ,'yyyy-mm-dd hh24:mi:ss') as ZJ,to_char(CJSJ,'yyyy-mm-dd hh24:mi:ss') as CJ,to_char(WCSJ,'yyyy-mm-dd hh24:mi:ss') as WJ,to_char(XGSJ,'yyyy-mm-dd hh24:mi:ss') as XJ FROM PORTAL55_EMR.V_JMGS_BASY_QBL a WHERE a.ZYH={$zyh}";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }
        $bl = [];
        foreach ($data as $item) {
            $bl[$item['ZYH']][] = [
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
                'WZZT' => $item['WZZT'] ?? '',
                'JLXH' => $item['JLXH'] ?? '',
                'bl_type' => 0,
                'HJNR' => $this->blobToStr($item['BLNR'])
            ];
        }

        return $bl;
    }


    public function bl01New($zyh)
    {

        $sql = "SELECT BLBH,JZHM,BLLX,BLLB,BLMC,MBLB,MBBH,to_char(CJSJ,'yyyy-mm-dd hh24:mi:ss') as CJSJ,SQDH,DLLB,BLZT,SXYS,to_char(WCSJ,'yyyy-mm-dd hh24:mi:ss') as WCSJ,BRKS,BRBH,BLZM,DLJ,to_char(ZXSJ,'yyyy-mm-dd hh24:mi:ss') as ZXSJ,CJKS,BRXM,BRZD,SSYS,SYBZ,BZMBBH,BLYM,YMJL,to_char(RYZDSJ,'yyyy-mm-dd hh24:mi:ss') as RYZDSJ,PTID,BLZSTJ,JGID,ZDMC,ZDLX,CXPX,SBBZ,WZZT FROM PORTAL55_EMR.EMR_BL_BL01 WHERE BLLB=2000001 AND JZHM='{$zyh}'";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }
        foreach ($data as $item) {
            $result = [
                'BLBH' => $item['BLBH'] ?? '',
                'JZHM' => $item['JZHM'] ?? '',
                'BRBH' => $item['BRBH'] ?? '',
                'BLLX' => $item['BLLX'] ?? '',
                'BLLB' => $item['BLLB'] ?? '',
                'BLMC' => $item['BLMC'] ?? '',
                'BLZM' => $item['BLZM'] ?? '',
                'DLLB' => $item['DLLB'] ?? '',
                'DLJ' => $item['DLJ'] ?? '',
                'MBLB' => $item['MBLB'] ?? '',
                'MBBH' => $item['MBBH'] ?? '',
                'ZXSJ' => $item['ZXSJ'] ?? '',
                'CJSJ' => $item['CJSJ'] ?? '',
                'WCSJ' => $item['WCSJ'] ?? '',
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
            \App\Model\EMR_BL_BL01_NEW::query()->updateOrInsert(['BLBH' => $result['BLBH']], $result);
        }
    }


    public function getBl01New($zyh)
    {

        $sql = "SELECT BLBH,JZHM,BLLX,BLLB,BLMC,MBLB,MBBH,to_char(CJSJ,'yyyy-mm-dd hh24:mi:ss') as CJSJ,SQDH,DLLB,BLZT,SXYS,to_char(WCSJ,'yyyy-mm-dd hh24:mi:ss') as WCSJ,BRKS,BRBH,BLZM,DLJ,to_char(ZXSJ,'yyyy-mm-dd hh24:mi:ss') as ZXSJ,CJKS,BRXM,BRZD,SSYS,SYBZ,BZMBBH,BLYM,YMJL,to_char(RYZDSJ,'yyyy-mm-dd hh24:mi:ss') as RYZDSJ,PTID,BLZSTJ,JGID,ZDMC,ZDLX,CXPX,SBBZ,WZZT FROM PORTAL55_EMR.EMR_BL_BL01 WHERE BLLB=2000001 AND JZHM='{$zyh}'";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }

        $re = [];
        foreach ($data as $item) {
            $re[] = [
                'BLBH' => $item['BLBH'] ?? '',
                'JZHM' => $item['JZHM'] ?? '',
                'BRBH' => $item['BRBH'] ?? '',
                'BLLX' => $item['BLLX'] ?? '',
                'BLLB' => $item['BLLB'] ?? '',
                'BLMC' => $item['BLMC'] ?? '',
                'BLZM' => $item['BLZM'] ?? '',
                'DLLB' => $item['DLLB'] ?? '',
                'DLJ' => $item['DLJ'] ?? '',
                'MBLB' => $item['MBLB'] ?? '',
                'MBBH' => $item['MBBH'] ?? '',
                'ZXSJ' => $item['ZXSJ'] ?? '',
                'CJSJ' => $item['CJSJ'] ?? '',
                'WCSJ' => $item['WCSJ'] ?? '',
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
        }

        return $re;
    }

    /**
     * @param $zyh
     * 护士分床时间
     */
    public function ZY_HCMX($zyh)
    {
        $sql = "SELECT ZYH,to_char(HCRQ,'yyyy-mm-dd hh24:mi:ss') as HCRQ,to_char(ZZRQ,'yyyy-mm-dd hh24:mi:ss') as ZZRQ,HCLX,HQCH,HHCH,HQKS,HHKS,HQBQ,HHBQ,JSCS,CZGH,JGID FROM PORTAL_HIS.BTF_ZY_HCMX WHERE ZYH={$zyh}";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }

        foreach ($data as $item) {
            $insertData = [
                'ZYH' => $item['ZYH'],
                'HCRQ' => $item['HCRQ'],
                'ZZRQ' => $item['ZZRQ'],
                'HCLX' => $item['HCLX'],
                'HQCH' => $item['HQCH'],
                'HHCH' => $item['HHCH'],
                'HQKS' => $item['HQKS'],
                'HHKS' => $item['HHKS'],
                'HQBQ' => $item['HQBQ'],
                'HHBQ' => $item['HHBQ'],
                'JSCS' => $item['JSCS'],
                'CZGH' => $item['CZGH'],
                'JGID' => $item['JGID']
            ];

            \App\Model\ZY_HCMX::query()->updateOrInsert(['ZYH' => $insertData['ZYH'], 'HCRQ' => $insertData['HCRQ']], $insertData);
        }
    }

    /**
     * @param $zyh
     *住院病历（会诊意见）\住院病历（会诊申请）
     */
    public function YS_ZY_HZYJ($zyh)
    {

        //        $sql = "SELECT SQXH, JZHM, SQKS, SQYS, to_char(SQSJ,'yyyy-mm-dd hh24:mi:ss') as SQSJ, HZMD, HZMD2, HZSJ, YQDX, JJBZ, TJBZ, TJYS, TJSJ, ZFBZ, JSBZ, JSSJ, TXRY, BQZL, HZLX, BLBH, SQZD, JGID, JSYS, JZBZ, HZLB FROM PORTAL_HIS.BTF_YS_ZY_HZSQ  WHERE JZHM={$zyh}";
        //修改后sql
        $sql = "SELECT SQXH, JZHM, SQKS, SQYS, TO_CHAR(SQSJ) as SQSJ, HZMD, TO_CHAR(HZSJ) as HZSJ, YQDX, JJBZ, TJBZ, TJYS, TJSJ, ZFBZ, JSBZ, TO_CHAR(JSSJ,'yyyy-mm-dd hh24:mi:ss') as JSSJ, TXRY, BQZL, HZLX, BLBH  FROM PORTAL_HIS.BTF_YS_ZY_HZSQ  WHERE JZHM={$zyh}";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }

        $SQXH = [];
        foreach ($data as $item) {

            $str = $item['BQZL']; //blobToStr($item['BQZL']);
            $inData = [
                'SQXH' => $item['SQXH'] ?? '',
                'JZHM' => $item['JZHM'] ?? '',
                'SQKS' => $item['SQKS'] ?? '',
                'SQYS' => $item['SQYS'] ?? '',
                'SQSJ' => date('Y-m-d H:i:s', strtotime($item['SQSJ'])) ?? '',
                'HZMD' => $item['HZMD'] ?? '',
                //                'HZMD2' => $item['HZMD2'] ?? '',//---这个字段注释掉 oracle没有这个字段
                'HZSJ' => date('Y-m-d H:i:s', strtotime($item['HZSJ'])) ?? '',
                'YQDX' => $item['YQDX'] ?? '',
                'JJBZ' => $item['JJBZ'] ?? '',
                'TJBZ' => $item['TJBZ'] ?? '',
                'TJYS' => $item['TJYS'] ?? '',
                'TJSJ' => $item['TJSJ'] ?? '',
                'ZFBZ' => $item['ZFBZ'] ?? '',
                'JSBZ' => $item['JSBZ'] ?? '',
                'JSSJ' => date('Y-m-d H:i:s', strtotime($item['JSSJ'])) ?? '',
                'TXRY' => $item['TXRY'] ?? '',
                'BQZL' => $str ?? '',
                'HZLX' => $item['HZLX'] ?? '',
                'BLBH' => $item['BLBH'] ?? '',
                //                'SQZD' => $item['SQZD'] ?? '',//---这个字段注释掉 oracle没有这个字段
                //                'JGID' => $item['JGID'] ?? '',//---这个字段注释掉 oracle没有这个字段
                //                'JSYS' => $item['JSYS'] ?? '',//---这个字段注释掉 oracle没有这个字段
                //                'JZBZ' => $item['JZBZ'] ?? '',//---这个字段注释掉 oracle没有这个字段
                //                'HZLB' => $item['HZLB'] ?? '',//---这个字段注释掉 oracle没有这个字段
            ];
            $SQXH[] = $inData['SQXH'];
            \App\Model\YS_ZY_HZSQ::query()->updateOrInsert(['SQXH' => $item['SQXH'],], $inData);
        }

        $SQXHStr = implode(',', $SQXH);
        //        $sql = "SELECT JLXH,SQXH,HZYJ,HZYJ2,KSDM,SSYS,SXYS,to_char(SXSJ,'yyyy-mm-dd hh24:mi:ss') as SXSJ,to_char(QMSJ,'yyyy-mm-dd hh24:mi:ss') as QMSJ FROM PORTAL_HIS.BTF_YS_ZY_HZYJ WHERE SQXH in ($SQXHStr)";
        //修改后sql
        $sql = "SELECT JLXH,SQXH,HZYJ,KSDM,SSYS,SXYS,to_char(SXSJ,'yyyy-mm-dd hh24:mi:ss') as SXSJ,to_char(QMSJ,'yyyy-mm-dd hh24:mi:ss') as QMSJ FROM PORTAL_HIS.BTF_YS_ZY_HZYJ WHERE SQXH in ($SQXHStr)";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        foreach ($data as $item) {
            $str = $item['HZYJ']; //blobToStr($item['HZYJ']);
            $insertData = [
                'JLXH' => $item['JLXH'] ?? '',
                'SQXH' => $item['SQXH'] ?? '',
                'HZYJ' => $str ?? '',
                //                'HZYJ2' => $item['HZYJ2'] ?? '',//---这个字段注释掉 oracle没有这个字段
                'KSDM' => $item['KSDM'] ?? '',
                'SSYS' => $item['SSYS'] ?? '',
                'SXYS' => $item['SXYS'] ?? '',
                'SXSJ' => $item['SXSJ'] ?? '',
                'QMSJ' => $item['QMSJ'] ?? '',
            ];

            \App\Model\YS_ZY_HZYJ::query()->updateOrInsert(['JLXH' => $insertData['JLXH']], $insertData);
        }
    }

    public function addHzxx($zyh = "")
    {
        if(!$zyh){
            return false;
        }
        $ys_zy_hzyjCommand = new PatientYS_ZY_HZYJ();
        $hzxxData = $ys_zy_hzyjCommand->getData($zyh);
        if(!empty($hzxxData)){
            $ys_zy_hzyjCommand->addData($hzxxData);
        }
    }

    public function addHcmx($zyh = "")
    {
        if(!$zyh){
            return false;
        }
        $zy_hcmxCommand = new PatientZY_HCMX();
        $hcmxData = $zy_hcmxCommand->getData($zyh);
        if(!empty($hcmxData)){
            $zy_hcmxCommand->addData($hcmxData);
        }
    }

    public function mzjl($zyh)
    {

        $sql = "SELECT DCID,PATIENTID,PATIENTTYPE,VISITID,EFFECTIVEFLAG,AUTHORORGANIZATION,AUTHORORGANIZATIONNAME,IDCARD,CLINICID,HOSPIZATIONID,to_char(VISITDATETIME,'yyyy-mm-dd hh24:mi:ss') as VISITDATETIME,REQUESTNOTEID,NAME,SEX,AGE,MONTHAGE,HEIGHT,WEIGHT,ABOBLOODCODE,RHBLOODCODE,DEPTCODE,DEPTNAME,WARDAREANAME,WARDAREAROOM,SICKBEDID,to_char(REQUESTDATETIME,'yyyy-mm-dd hh24:mi:ss') as REQUESTDATETIME,OPERATIONDEPTCODE,OPERATIONCODE,PREOPERATIONNAME,OPERATIONNAME,OPTPATIENTTYPE,ISRETURNOPERATION,HAVEPREOPERATIVEDISCUSS,PREOPERATIVENOTES,PREOPERATIVEDIAGNOSECODE,PREOPERATIVEDIAGNOSENAME,POSTOPERATIVEDIAGNOSECODE,POSTOPERATIVEDIAGNOSENAME,DIAGCOINPREOPERATIVEVSPOST,OPERATIONROOMNO,OPERATIONROOMTABLENO,to_char(INOPTROOMTIME,'yyyy-mm-dd hh24:mi:ss') as INOPTROOMTIME,to_char(OUTOPTROOMTIME,'yyyy-mm-dd hh24:mi:ss') as OUTOPTROOMTIME,to_char(OPERATESTARTTIME,'yyyy-mm-dd hh24:mi:ss') as OPERATESTARTTIME,OPERATEENDTIME,OPERATOR,FIRSTASSISTANT,SECONDASSISTANT,THIRDASSISTANT,FIRSTINSTRUMENTNURSE,SECONDINSTRUMENTNURSE,THIRDINSTRUMENTNURSE,FIRSTCIRCULATINGNURSE,SECONDCIRCULATINGNURSE,THIRDCIRCULATINGNURSE,MEDICATEBEFOREANESTHESIA,ASALEVEL,ANESTHESIAWAYCODE,ANESTHESIAWAYNAME,TRACHEATUBETYPE,ANESTHESIABODYPOSITION,ANAESTHETIST,FIRSTANAESTHETISTASSI,SECONDANAESTHETISTASSI,ANESTHESIASTARTTIME,to_char(ANESTHESIAENDTIME,'yyyy-mm-dd hh24:mi:ss') as ANESTHESIAENDTIME,ANAESTHETICNAME,BREATHTYPECODE,ANESTHESIAEFFECT,ANESTHESIADESCRIPTION FROM SAMIS.V_CDR_5504 where HOSPIZATIONID='" . $zyh . "'";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }
        foreach ($data as $item) {
            $insertData = [
                'DCID' => $item['DCID'] ?? '',
                'PATIENTID' => $item['PATIENTID'] ?? '',
                'PATIENTTYPE' => $item['PATIENTTYPE'] ?? '',
                'VISITID' => $item['VISITID'] ?? '',
                'EFFECTIVEFLAG' => $item['EFFECTIVEFLAG'] ?? '',
                'AUTHORORGANIZATION' => $item['AUTHORORGANIZATION'] ?? '',
                'AUTHORORGANIZATIONNAME' => $item['AUTHORORGANIZATIONNAME'] ?? '',
                'IDCARD' => $item['IDCARD'] ?? '',
                'CLINICID' => $item['CLINICID'] ?? '',
                'HOSPIZATIONID' => $item['HOSPIZATIONID'] ?? '',
                'VISITDATETIME' => $item['VISITDATETIME'] ?? '',
                'REQUESTNOTEID' => $item['REQUESTNOTEID'] ?? '',
                'NAME' => $item['NAME'] ?? '',
                'SEX' => $item['SEX'] ?? '',
                'AGE' => $item['AGE'] ?? '',
                'MONTHAGE' => $item['MONTHAGE'] ?? '',
                'HEIGHT' => $item['HEIGHT'] ?? '',
                'WEIGHT' => $item['WEIGHT'] ?? '',
                'ABOBLOODCODE' => $item['ABOBLOODCODE'] ?? '',
                'RHBLOODCODE' => $item['RHBLOODCODE'] ?? '',
                'DEPTCODE' => $item['DEPTCODE'] ?? '',
                'DEPTNAME' => $item['DEPTNAME'] ?? '',
                'WARDAREANAME' => $item['WARDAREANAME'] ?? '',
                'WARDAREAROOM' => $item['WARDAREAROOM'] ?? '',
                'SICKBEDID' => $item['SICKBEDID'] ?? '',
                'REQUESTDATETIME' => $item['REQUESTDATETIME'] ?? '',
                'OPERATIONDEPTCODE' => $item['OPERATIONDEPTCODE'] ?? '',
                'OPERATIONCODE' => $item['OPERATIONCODE'] ?? '',
                'PREOPERATIONNAME' => $item['PREOPERATIONNAME'] ?? '',
                'OPERATIONNAME' => $item['OPERATIONNAME'] ?? '',
                'OPTPATIENTTYPE' => $item['OPTPATIENTTYPE'] ?? '',
                'ISRETURNOPERATION' => $item['ISRETURNOPERATION'] ?? '',
                'HAVEPREOPERATIVEDISCUSS' => $item['HAVEPREOPERATIVEDISCUSS'] ?? '',
                'PREOPERATIVENOTES' => $item['PREOPERATIVENOTES'] ?? '',
                'PREOPERATIVEDIAGNOSECODE' => $item['PREOPERATIVEDIAGNOSECODE'] ?? '',
                'PREOPERATIVEDIAGNOSENAME' => $item['PREOPERATIVEDIAGNOSENAME'] ?? '',
                'POSTOPERATIVEDIAGNOSECODE' => $item['POSTOPERATIVEDIAGNOSECODE'] ?? '',
                'POSTOPERATIVEDIAGNOSENAME' => $item['POSTOPERATIVEDIAGNOSENAME'] ?? '',
                'DIAGCOINPREOPERATIVEVSPOST' => $item['DIAGCOINPREOPERATIVEVSPOST'] ?? '',
                'OPERATIONROOMNO' => $item['OPERATIONROOMNO'] ?? '',
                'OPERATIONROOMTABLENO' => $item['OPERATIONROOMTABLENO'] ?? '',
                'INOPTROOMTIME' => $item['INOPTROOMTIME'] ?? '',
                'OUTOPTROOMTIME' => $item['OUTOPTROOMTIME'] ?? '',
                'OPERATESTARTTIME' => $item['OPERATESTARTTIME'] ?? '',
                'OPERATEENDTIME' => $item['OPERATEENDTIME'] ?? '',
                'OPERATOR' => $item['OPERATOR'] ?? '',
                'FIRSTASSISTANT' => $item['FIRSTASSISTANT'] ?? '',
                'SECONDASSISTANT' => $item['SECONDASSISTANT'] ?? '',
                'THIRDASSISTANT' => $item['THIRDASSISTANT'] ?? '',
                'FIRSTINSTRUMENTNURSE' => $item['FIRSTINSTRUMENTNURSE'] ?? '',
                'SECONDINSTRUMENTNURSE' => $item['SECONDINSTRUMENTNURSE'] ?? '',
                'THIRDINSTRUMENTNURSE' => $item['THIRDINSTRUMENTNURSE'] ?? '',
                'FIRSTCIRCULATINGNURSE' => $item['FIRSTCIRCULATINGNURSE'] ?? '',
                'SECONDCIRCULATINGNURSE' => $item['SECONDCIRCULATINGNURSE'] ?? '',
                'THIRDCIRCULATINGNURSE' => $item['THIRDCIRCULATINGNURSE'] ?? '',
                'MEDICATEBEFOREANESTHESIA' => $item['MEDICATEBEFOREANESTHESIA'] ?? '',
                'ASALEVEL' => $item['ASALEVEL'] ?? '',
                'ANESTHESIAWAYCODE' => $item['ANESTHESIAWAYCODE'] ?? '',
                'ANESTHESIAWAYNAME' => $item['ANESTHESIAWAYNAME'] ?? '',
                'TRACHEATUBETYPE' => $item['TRACHEATUBETYPE'] ?? '',
                'ANESTHESIABODYPOSITION' => $item['ANESTHESIABODYPOSITION'] ?? '',
                'ANAESTHETIST' => $item['ANAESTHETIST'] ?? '',
                'FIRSTANAESTHETISTASSI' => $item['FIRSTANAESTHETISTASSI'] ?? '',
                'SECONDANAESTHETISTASSI' => $item['SECONDANAESTHETISTASSI'] ?? '',
                'ANESTHESIASTARTTIME' => $item['ANESTHESIASTARTTIME'] ?? '',
                'ANESTHESIAENDTIME' => $item['ANESTHESIAENDTIME'] ?? '',
                'ANAESTHETICNAME' => $item['ANAESTHETICNAME'] ?? '',
                'BREATHTYPECODE' => $item['BREATHTYPECODE'] ?? '',
                'ANESTHESIAEFFECT' => $item['ANESTHESIAEFFECT'] ?? '',
                'ANESTHESIADESCRIPTION' => $item['ANESTHESIADESCRIPTION'] ?? '',
            ];

            \App\Model\Mzjl::query()->updateOrInsert(['DCID' => $insertData['DCID']], $insertData);
        }
    }

    /**
     * @param $zyh
     * 入院途径
     */
    public function BaBrsy($zyh)
    {
        $sql = "SELECT ZYH AS AAA28,CYBQ,ZZYLJG FROM PORTAL_HIS.ba_brsy WHERE ZYH={$zyh}";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }
        foreach ($data as $item) {
            $insertData = [
                'AAA28' => $item['AAA28'] ?? '',
                'CYBQ' => $item['CYBQ'] ?? '',
                'ZZYLJG' => $item['ZZYLJG'] ?? '',
            ];
            \App\Model\BaBrsy::query()->updateOrInsert(['AAA28' => $insertData['AAA28']], $insertData);
        }
    }

    public function YJ_ZY01($zyh = null)
    {

        $sql = "SELECT YJXH,TJHM,ZYH,ZYHM,BRXM,to_char(KDRQ,'yyyy-mm-dd hh24:mi:ss') as KDRQ,KSDM,YSDM,to_char(ZXRQ,'yyyy-mm-dd hh24:mi:ss') as ZXRQ,ZXKS,ZXPB,HJGH,BBBM,ZYSX,ZFPB,HYMX,YJPH,SQDH,BWID,JBID,DJZT,SQWH,FYBQ,SQID,YQDH,JGID,SSYS,SSYZ,SSEZ,SSSZ,JZBZ FROM PORTAL_HIS.YJ_ZY01 WHERE ZYH={$zyh}";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }

        $YJXH = [];
        foreach ($data as $item) {
            $YJXH[] = $item['YJXH'];
            $insertData = [
                'YJXH' => $item['YJXH'] ?? '',
                'TJHM' => $item['TJHM'] ?? '',
                'ZYH' => $item['ZYH'] ?? '',
                'ZYHM' => $item['ZYHM'] ?? '',
                'BRXM' => desensitize($item['BRXM'], 1, 1, '*'),
                'KDRQ' => $item['KDRQ'] ?? '',
                'KSDM' => $item['KSDM'] ?? '',
                'YSDM' => $item['YSDM'] ?? '',
                'ZXRQ' => $item['ZXRQ'] ?? '',
                'ZXKS' => $item['ZXKS'] ?? '',
                'ZXPB' => $item['ZXPB'] ?? '',
                'BBBM' => $item['BBBM'] ?? '',
                'ZYSX' => $item['ZYSX'] ?? '',
                'ZFPB' => $item['ZFPB'] ?? '',
                'HYMX' => $item['HYMX'] ?? '',
                'YJPH' => $item['YJPH'] ?? '',
                'SQDH' => $item['SQDH'] ?? '',
                'BWID' => $item['BWID'] ?? '',
                'JBID' => $item['JBID'] ?? '',
                'DJZT' => $item['DJZT'] ?? '',
                'SQWH' => $item['SQWH'] ?? '',
                'FYBQ' => $item['FYBQ'] ?? '',
                'SQID' => $item['SQID'] ?? '',
                'YQDH' => $item['YQDH'] ?? '',
                'JGID' => $item['JGID'] ?? '',
                'SSYS' => $item['SSYS'] ?? '',
                'SSYZ' => $item['SSYZ'] ?? '',
                'SSEZ' => $item['SSEZ'] ?? '',
                'SSSZ' => $item['SSSZ'] ?? '',
                'JZBZ' => $item['JZBZ'] ?? '',
            ];

            \App\Model\YJ_ZY01::query()->updateOrInsert(['YJXH' => $insertData['YJXH']], $insertData);
        }

        $YJXHstr = implode(',', $YJXH);
        $sql = "SELECT SBXH, YJXH, YLXH, XMLX, YJZX, YLDJ, YLSL, FYGB, ZFBL, YZXH, TPLJ, YEPB, TMDY_LQ, TMH, JGID, ZTMC, JHH, XDH, JHSJ, JFID FROM PORTAL_HIS.YJ_ZY02 WHERE YJXH in ($YJXHstr)";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }

        foreach ($data as $item) {
            if (empty($item)) {
                continue;
            }
            $insertData = [
                'SBXH' => $item['SBXH'],
                'YJXH' => $item['YJXH'],
                'YLXH' => $item['YLXH'],
                'XMLX' => $item['XMLX'],
                'YJZX' => $item['YJZX'],
                'YLDJ' => $item['YLDJ'],
                'YLSL' => $item['YLSL'],
                'FYGB' => $item['FYGB'],
                'ZFBL' => $item['ZFBL'],
                'YZXH' => $item['YZXH'],
                'TPLJ' => $item['TPLJ'],
                'YEPB' => $item['YEPB'],
                'TMDY_LQ' => $item['TMDY_LQ'],
                'TMH' => $item['TMH'],
                'JGID' => $item['JGID'],
                'ZTMC' => $item['ZTMC'],
                'JHH' => $item['JHH'],
                'XDH' => $item['XDH'],
                'JHSJ' => $item['JHSJ'],
            ];
            \App\Model\YJ_ZY02::query()->updateOrInsert(['SBXH' => $insertData['SBXH']], $insertData);
        }
    }

    public function blobToStr($blob = null)
    {
        $str = '';
        if (!is_object($blob)) {
            return $blob;
        }
        if (!empty($blob)) {
            $text = $blob->load();
            $blob->free();
            $mde = mb_detect_encoding($text, array("ASCII", 'UTF-8', "GB2312", "GBK", 'BIG5'));
            if ($mde) {
                $str = mb_convert_encoding($text, 'utf-8', $mde);
            }
        }

        return $str;
    }

    public function EMR_BL_BASYSJ($zyh)
    {
        $sql = "SELECT JLXH,JZHM,BLBH,XMXH,XMMC,XMQZ,DYYS,DLLJ,GLZD,KSMRZ,SYBTX,XMNM FROM PORTAL55_EMR.EMR_BL_BASYSJ WHERE JZHM='{$zyh}'";
        $result = oci_parse(self::$con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }
        foreach ($data as $item) {
            $insertData = [
                'JLXH' => $item['JLXH'],
                'JZHM' => $item['JZHM'],
                'BLBH' => $item['BLBH'],
                'XMXH' => $item['XMXH'],
                'XMMC' => $item['XMMC'],
                'XMQZ' => $item['XMQZ'],
                'DYYS' => $item['DYYS'],
                'DLLJ' => $item['DLLJ'],
                'GLZD' => $item['GLZD'],
                'KSMRZ' => $item['KSMRZ'],
                'SYBTX' => $item['SYBTX'],
                'XMNM' => $item['XMNM'],
            ];

            \App\Model\EMR_BL_BASYSJ::query()->updateOrInsert(['JLXH' => $insertData['JLXH']], $insertData);
        }
    }

    public function V_JMGS_TESTRESULT($zyh)
    {
        //进行数据库连接
        $username = "BTF";
        $password = "BTF";
        $connection = "192.168.10.20";
        $port = 1521;
        $tns = "xhlis";
        $con = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }

        $sql = "SELECT REPORT_PK,PERSON_NAME,IN_PATIENT_ID,OUT_PATIENT_ID,VISIT_TYPE_CODE,SAMPLE_NO,REPORT_NO,SPECIMEN_NAME,INP_NO,
                    LAB_DIAGNOSIS_NAME,LAB_ITEM_ENAME,LAB_ITEM_NAME,LAB_YM_RESULT,RESULT_STATUS_NAME,RANGE,MIN_RESULT_UNIT,
                    SPEC_SENDER_NAME,SPEC_CONFIRMER_NAME,PERFORMED_DOCTOR_NAME,OUTP_NO,VISIT_ID,APPLY_DEPT_NAME,
                    TO_CHAR(SAMPLE_TIME,'yyyy-mm-dd hh24:mi:ss') AS SAMPLE_TIME,
                    TO_CHAR(PRINT_TIME,'yyyy-mm-dd hh24:mi:ss') AS PRINT_TIME,
                    TO_CHAR(REPORT_TIME,'yyyy-mm-dd hh24:mi:ss') AS REPORT_TIME
                    FROM DBO.HDR_LAB_REPORT_BTF
                    WHERE VISIT_ID = '" . $zyh . "'OR OUTP_NO = '" . $zyh . "'";


        $result = oci_parse($con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }

        //        \App\Model\V_JMGS_TESTRESULT::query()->where('ZYH','=',$zyh)->delete();
        foreach ($data as $item) {
            $zyh = 0;
            if ($item['VISIT_TYPE_CODE'] == '01' || $item['VISIT_TYPE_CODE'] == '03') {
                $zyh = $item['OUTP_NO'];
            } elseif ($item['VISIT_TYPE_CODE'] == '02') {
                $zyh = $item['VISIT_ID'];
            }
            $VISIT_TYPE_CODE = '';
            if (!empty($item['VISIT_TYPE_CODE'])) {
                $VISIT_TYPE_CODE = $item['VISIT_TYPE_CODE'];
            } elseif ($item['APPLY_DEPT_NAME'] == '体检中心') {
                $VISIT_TYPE_CODE = 100;
            }
            $testResultData = [
                'ZYH' => $zyh,                                    //唯一标识
                'TXM' => $item['SAMPLE_NO'] ?: '',                //样本编号、条码
                'NO' => $item['REPORT_NO'] ?: '',                //报告号
                'XM' => $item['PERSON_NAME'] ? desensitize($item['PERSON_NAME'], 1, 1, '*') : '', //姓名
                'YBLX' => $item['SPECIMEN_NAME'] ?: '',            //样本类型
                //                        'YBZT' => $item['RESULT_STATUS_NAME'] ?: '',    //样本状态
                'AAA28' => $item['INP_NO'] ?: '',                   //住院号
                'LCZD' => $item['LAB_DIAGNOSIS_NAME'] ?: '',       //临床诊断
                'YW' => $item['LAB_ITEM_ENAME'] ?: '',           //英文（检验项目）
                'JYXM' => $item['LAB_ITEM_NAME'] ?: '',            //检验项目
                'JG' => $item['LAB_YM_RESULT'] ?: '',            //结果
                'TS' => $item['RESULT_STATUS_NAME'] ?: '',       //提示
                'CKFW' => $item['RANGE'] ?: '',                    //参考范围
                'DW' => $item['MIN_RESULT_UNIT'] ?: '',          //单位
                'SJYS' => $item['SPEC_SENDER_NAME'] ?: '',         //送检医生
                'JYY' => $item['SPEC_CONFIRMER_NAME'] ?: '',      //检验员
                'SHY' => $item['PERFORMED_DOCTOR_NAME'] ?: '',    //审核员
                'CJSJ' => $item['SAMPLE_TIME'] ?: '',              //采集时间
                'JSSJ' => $item['PRINT_TIME'] ?: '',               //接收时间
                'BGSJ' => $item['REPORT_TIME'] ?: '',              //报告时间
                'REPORT_PK' => $item['REPORT_PK'],
                'VISIT_TYPE_CODE' => $VISIT_TYPE_CODE
            ];

            \App\Model\V_JMGS_TESTRESULT::query()->updateOrInsert(['REPORT_PK' => $testResultData['REPORT_PK']], $testResultData);
        }
    }

    public function V_JMGS_YMresult($zyh)
    {
        //进行数据库连接
        $username = "BTF";
        $password = "BTF";
        $connection = "192.168.10.20";
        $port = 1521;
        $tns = "xhlis";
        $con = oci_connect($username, $password, $connection . ':' . $port . '/' . $tns, 'UTF8');
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }
        $sql = "SELECT OUTP_NO,VISIT_ID,VISIT_TYPE_CODE,SAMPLE_NO,PERSON_NAME,LAB_ITEM_ENAME,OUT_PATIENT_ID,
                    IN_PATIENT_ID,RESULT_STATUS_NAME,MICRO_ITEM_NAME,LAB_YM_NAME,LAB_YM_RESULT,SOURCE_PK,
                    TO_CHAR(REPORT_TIME,'yyyy-mm-dd hh24:mi:ss') AS BGSJ
                    FROM DBO.HDR_LAB_REPORT_DETAIL_MICRO
                    WHERE VISIT_ID = '" . $zyh . "'OR OUTP_NO = '" . $zyh . "'";

        $result = oci_parse($con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            return false;
        }
        \App\Model\V_JMGS_YMresult::query()->where('ZYH', '=', $zyh)->delete();
        $params = [
            'index' => 'v_jmgs_ymresult_2023',
            'body' => [
                'query' => [
                    'term' => [
                        'ZYH' => $zyh
                    ]
                ]
            ]
        ];
        app('es')->deleteByQuery($params);
        foreach ($data as $item) {
            $zyh = 0;
            $AAA28 = '';
            if ($item['VISIT_TYPE_CODE'] == '01') {
                $zyh = $item['OUTP_NO'];
                $AAA28 = $item['OUT_PATIENT_ID'];
            } elseif ($item['VISIT_TYPE_CODE'] == '02') {
                $zyh = $item['VISIT_ID'];
                $AAA28 = $item['IN_PATIENT_ID'];
            }
            $ymResultData = [
                'ZYH' => $zyh,                                    //唯一标识
                'TXM' => $item['SAMPLE_NO'] ?: '',                //样本编号、条码
                'NO' => $item['SAMPLE_NO'] ?: '',                //报告号
                'XM' => $item['PERSON_NAME'] ? desensitize($item['PERSON_NAME'], 1, 1, '*') : '', //姓名
                'YBLX' => $item['LAB_ITEM_ENAME'] ?: '',            //样本类型
                'AAA28' => $AAA28,                                  //住院号
                'PYJG' => $item['RESULT_STATUS_NAME'],             //细菌培养结果
                'XJMC' => $item['MICRO_ITEM_NAME'] ?: '',          //细菌名称
                'XJJL' => $item['LAB_YM_RESULT'] ?: '',            //细菌数量
                'YMMC' => $item['LAB_YM_NAME'] ?: '',              //药敏名称
                'YMJG' => $item['LAB_YM_RESULT'] ?: '',            //药敏结果
                'YMBW' => $item['LAB_ITEM_ENAME'] ?: '',           //药敏部位
                'BGSJ' => $item['BGSJ'] ?: '',                     //报告时间
                'SOURCE_PK' => $item['SOURCE_PK']
            ];

            \App\Model\V_JMGS_YMresult::query()->updateOrInsert(['SOURCE_PK' => $ymResultData['SOURCE_PK']], $ymResultData);
        }
    }


    public function BA_MR_CLASS_NUMBER($aaa28)
    {
        $con = oci_connect('zzj', 'zzj', '172.16.2.177:1433/CEMS', "UTF8");
        if (!$con) {
            $e = oci_error();
            print_r(htmlentities($e['message'])) . "\n";
            die;
        }

        $sql = "SELECT patient_id,visit_id,MrClass,Quantity,serial_no FROM BA_MR_CLASS_NUMBER where patient_id='{$aaa28}'";
        $result = oci_parse($con, $sql);
        oci_execute($result, OCI_DEFAULT);
        $data = [];
        while ($row = oci_fetch_assoc($result)) {
            $data[] = $row;
        }
        if (empty($data)) {
            exit;
        }
        \App\Model\BA_MR_CLASS_NUMBER::query()->where('patient_id', '=', $aaa28)->delete();
        $insertData = [];
        foreach ($data as $item) {
            $insertData[] = [
                'patient_id' => $item['patient_id'],
                'visit_id' => $item['visit_id'],
                'MrClass' => $item['MrClass'],
                'Quantity' => $item['Quantity'],
                'serial_no' => $item['serial_no'],
            ];
        }
        \App\Model\BA_MR_CLASS_NUMBER::query()->insert($insertData);
    }


    /**
     * 获取费用信息
     * @param $zyh
     * @return array
     */
    public function getFeeDetailed($zyh)
    {
        DataSyncLog::addData(['zyh' => $zyh, 'content' => '费用明细同步开始']);
        // 查询费用数据
        $sql = "SELECT A.*,to_char(JFRQ,'yyyy-mm-dd hh24:mi:ss') as JFRQ,to_char(DSG_LDR_TIME,'yyyy-mm-dd hh24:mi:ss') as DSG_LDR_TIME
                FROM PORTAL_HIS.V_JMGS_BASY_FYMX A WHERE A.ZYH = {$zyh}";
        $data = oci_parse(self::$con, $sql);
        oci_execute($data, OCI_DEFAULT);
        $result = [];
        while ($row = oci_fetch_assoc($data)) {
            $result[] = $row;
        }
        DataSyncLog::addData(['zyh' => $zyh, 'content' => '本次同步费用明细数量', 'data_nums' => count($result)]);

        return $result;
    }

    /**
     * 添加费用信息
     * @param $result
     * @return void
     */
    public function addFeeDetailed($result)
    {
        foreach ($result as $val) {
            $item = [
                'AAA28' => $val['ZYH'], //zyh
                'FYXH' => $val['FYXH'] ?? '', //费用序号
                'FYMC' => $val['FYMC'] ?? '', //费用名称
                'ZFJE' => $val['ZFJE'] ?? '', //自付金额
                'JFRQ' => $val['JFRQ'] ?? '', //计费日期
                'FYSL' => $val['FYSL'] ?? '', //费用数量
                'FYDJ' => $val['FYDJ'] ?? '', //费用单价
                'ZJE' => $val['ZJE'] ?? '', //总金额
                'FYKS' => $val['FYKS'] ?? '', //费用科室
                'FYGB' => $val['FYGB'] ?? '',
                'SYFYGB' => $val['SYFYGB'] ?? '',
                'DSG_LDR_TIME' => $val['DSG_LDR_TIME'] ?? '',
                'DSG_OPERATION' => $val['DSG_OPERATION'] ?? '',
                'JLXH' => $val['JLXH'] ?? '',
                'YZXH' => $val['YZXH'] ?? '', //医嘱序号
            ];
            FeeDetailed::query()->updateOrInsert(['AAA28' => $item['AAA28'], 'JLXH' => $item['JLXH']], $item);
        }
        DataSyncLog::addData(['zyh' => $val['ZYH'], 'content' => '费用明细同步结束']);
    }

    /**
     * 同步并格式化 PACS 报告数据。
     *
     * @param string $zyh 住院号
     * @return bool
     */
    public function addPacs($zyh = '')
    {
        if (empty($zyh)) {
            return false;
        }

        $patientInfo = $this->getPacsPatientInfo($zyh);
        if (empty($patientInfo)) {
            return false;
        }

        $this->syncPacsReport($zyh, $patientInfo);
        $this->syncBingLiPacs($zyh, $patientInfo);
        $this->formatPacsContent($zyh, $patientInfo);
        $this->cleanPacsZYH(0, $patientInfo['AAA28']);

        return true;
    }

    /**
     * 兼容旧的 pacs 调用入口。
     *
     * @param string $zyh 住院号
     * @return bool
     */
    public function pacs($zyh = '')
    {
        return $this->addPacs($zyh);
    }

    /**
     * 获取 PACS 同步需要的患者基础信息。
     *
     * @param string $zyh 住院号
     * @return array
     */
    private function getPacsPatientInfo($zyh)
    {


        $brry = ZY_BRRY::query()->where('ZYH', $zyh)->first();
        if (!$brry) {
            return [];
        }

        $patientInfo = $brry->toArray();

        $patientInfo['MED_REC_ID'] = $patientInfo['ZYH'] ?? $zyh;

        return $patientInfo;
    }

    /**
     * 同步 PACS 报告数据。
     *
     * @param string $zyh 住院号
     * @param array $patientInfo 患者基础信息
     * @return int
     */
    private function syncPacsReport($zyh, array $patientInfo)
    {
        $conn = $this->getPacsMysqlConnect();
        if (!$conn) {
            return 0;
        }

        $aaa28 = $patientInfo['AAA28'] ?? '';
        $startTime = $patientInfo['AAB01'] ?? '';
        $endTime = $patientInfo['AAC01'] ?? '';
        $sql = "SELECT * FROM hdr_exam_report WHERE VISIT_TYPE_NAME = '住院' AND INP_NO = :aaa28";
        $params = [
            ':aaa28' => (string)$aaa28,
        ];

        if (!empty($startTime)) {
            $sql .= " AND APPLY_TIME >= :start_time";
            $params[':start_time'] = $startTime;
        }

        if (!empty($endTime)) {
            $sql .= " AND APPLY_TIME <= :end_time";
            $params[':end_time'] = $endTime;
        } else {
            //取当前时间
            $endTime = date('Y-m-d H:i:s', time());
            $params[':end_time'] = $endTime;
            $sql .= " AND APPLY_TIME <= :end_time";
        }

        $statement = $conn->prepare($sql);
        $statement->execute($params);
        $data = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $count = 0;
        foreach ($data as $v) {
            $examType = $this->getPacsExamType($v['EXAM_CLASS_CODE'] ?? '', $v['EXAM_CLASS_NAME'] ?? '', $v['EXAM_ITEM_NAME'] ?? '');
            $studyUid = $v['APPLY_NO'] ?? '';
            if (empty($studyUid)) {
                $studyUid = $v['PACS_URL'] ?? '';
            }

            $insert = [
                'ZYH' => $v['VISIT_ID'] ?? $zyh,
                'JZLSH' => $v['APPLY_NO'] ?? '',
                'BAH' => $v['INP_NO'] ?? $aaa28,
                'MZZYBZ' => $v['VISIT_TYPE_NAME'] ?? '',
                'BRXM' => $v['PERSON_NAME'] ?? ($patientInfo['AAA01'] ?? ''),
                'BRXB' => $v['SEX_NAME'] ?? ($patientInfo['AAA02C'] ?? ''),
                'PatientID' => $v['PACS_URL'] ?? '',
                'JCXMDM' => $v['EXAM_ITEM_CODE'] ?? '',
                'SQDH' => $v['APPLY_NO'] ?? '',
                'JYSJ' => $v['EXAM_PERFORM_TIME'] ?? '',
                'ExamType' => $examType,
                'SQKS' => $v['APPLY_DEPT_CODE'] ?? '',
                'SQKSMC' => $v['APPLY_DEPT_NAME'] ?? '',
                'SQRGH' => $v['APPLY_DOCTOR_CODE'] ?? '',
                'SQRXM' => $v['APPLY_DOCTOR_NAME'] ?? '',
                'JCKSMC' => $v['EXAM_ROOM'] ?? '',
                'JCYS' => $v['PERFORM_DOCTOR'] ?? '',
                'BGSJ' => $v['REPORT_TIME'] ?? '',
                'BGRQ' => $v['REPORT_TIME'] ?? '',
                'BGRGH' => $v['REPORT_DOCTOR_CODE'] ?? '',
                'BGRXM' => $v['REPORT_DOCTOR_NAME'] ?? '',
                'SHRGH' => $v['REPORT_CONFIRMER_CODE'] ?? '',
                'SHRXM' => $v['REPORT_CONFIRMER_NAME'] ?? '',
                'JCBW' => $v['EXAM_PART_NAME'] ?? '',
                'BWACR' => $v['EXAM_PART_CODE'] ?? '',
                'JCMC' => $v['EXAM_ITEM_NAME'] ?? '',
                'YXBX' => $v['EXAM_FEATURE'] ?? '',
                'YXZD' => $v['EXAM_DIAG'] ?? '',
                'SFYYY' => $v['PACS_URL'] ?? '',
                'XGBZ' => $v['REPORT_STATUS_NAME'] ?? '',
                'KDSJ' => $v['APPLY_TIME'] ?? '',
                'SOURCE_PK' => $v['SOURCE_PK'] ?? '',
                'StudyUid' => $studyUid ?? '',
            ];

            if (!empty($insert['SOURCE_PK'])) {
                PACS::query()->updateOrInsert(['SOURCE_PK' => $insert['SOURCE_PK'], 'BAH' => $aaa28], $insert);
            } else {
                PACS::query()->insert($insert);
            }
            $count++;
        }

        return $count;
    }

    /**
     * 同步病理报告数据。
     *
     * @param string $zyh 住院号
     * @param array $patientInfo 患者基础信息
     * @return int
     */
    private function syncBingLiPacs($zyh, array $patientInfo)
    {
        if (!function_exists('sqlsrv_connect')) {
            return 0;
        }

        $serverName = env('PACS_BL_SQLSRV_HOST', '192.168.10.110,1433');
        $connectionOptions = [
            'Database' => env('PACS_BL_SQLSRV_DATABASE', 'PQCSDB'),
            'UID' => env('PACS_BL_SQLSRV_USERNAME', 'ReportsUser'),
            'PWD' => env('PACS_BL_SQLSRV_PASSWORD', 'asd@admin!666#'),
            'CharacterSet' => 'UTF-8',
            'LoginTimeout' => 10,
            'TrustServerCertificate' => 1,
        ];

        $connection = sqlsrv_connect($serverName, $connectionOptions);
        if (!$connection) {
            return 0;
        }

        $aaa28 = $patientInfo['AAA28'] ?? '';
        $aab01 = $patientInfo['AAB01'] ?? '';
        $aac01 = $patientInfo['AAC01'] ?? '';
        $params = [];
        $params[] = (string)$aaa28;
        $sql = "SELECT PatientID AS StudyUid, PatientID, SQDH,
                    CONVERT(varchar(19), KDSJ, 120) AS KDSJ,
                    CONVERT(varchar(19), JYSJ, 120) AS JYSJ,
                    '07' AS ExamType,
                    CONVERT(varchar(19), BGSJ, 120) AS BGSJ,
                    CONVERT(varchar(19), BGSJ, 120) AS BGRQ,
                    BGRGH, BGRXM, SHRGH, SHRXM, JCBW, JCMC, YXBX, YXZD, XGBZ, BAH, ZYCS,JYSJ AS SOURCE_PK
                FROM dbo.Inspectionreport_PACS
                WHERE MZZYBZ = 2 AND BAH = ?";
        if (!empty($aab01)) {
            $sql .= " AND JYSJ >= ?";
            $params[] = $aab01;
        }

        if (!empty($aac01)) {
            $sql .= " AND JYSJ <= ?";
            $params[] = $aac01;
        } else {
            //取当前时间
            $aac01 = date('Y-m-d H:i:s', time());
            $params[] = $aac01;
            $sql .= " AND JYSJ <= ?";
        }
        $stmt = sqlsrv_query($connection, $sql, $params);
        if ($stmt === false) {
            sqlsrv_close($connection);

            return 0;
        }

        $count = 0;
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $row = $this->formatSqlsrvRow($row);
            $studyUid = $row['StudyUid'] ?? '';
            if (empty($studyUid)) {
                continue;
            }

            $insert = [
                'StudyUid' => $studyUid,
                'ZYH' => $zyh,
                'JZLSH' => $aaa28,
                'PatientID' => $row['PatientID'] ?? '',
                'SQDH' => $row['SQDH'] ?? '',
                'KDSJ' => $row['KDSJ'] ?? '',
                'JYSJ' => $row['JYSJ'] ?? '',
                'ExamType' => '07',
                'BGSJ' => $row['BGSJ'] ?? '',
                'BGRQ' => $row['BGRQ'] ?? '',
                'BGRGH' => $row['BGRGH'] ?? '',
                'BGRXM' => $row['BGRXM'] ?? '',
                'SHRGH' => $row['SHRGH'] ?? '',
                'SHRXM' => $row['SHRXM'] ?? '',
                'JCBW' => $row['JCBW'] ?? '',
                'JCMC' => $row['JCMC'] ?? '',
                'YXBX' => $row['YXBX'] ?? '',
                'YXZD' => $row['YXZD'] ?? '',
                'XGBZ' => $row['XGBZ'] ?? '',
                'BAH' => $row['BAH'] ?? $aaa28,
                'ZYCS' => $row['ZYCS'] ?? $zycs,
                'BRXM' => $patientInfo['AAA01'] ?? '',
                'BRXB' => $patientInfo['AAA02C'] ?? '',
                'SQKSMC' => $patientInfo['AAB02C'] ?? '',
                'SOURCE_PK' => $row['SOURCE_PK'] ?? '',
            ];

            PACS::query()->updateOrInsert(['SOURCE_PK' => $insert['SOURCE_PK'], 'BAH' => $insert['BAH']], $insert);
            $count++;
        }

        sqlsrv_free_stmt($stmt);
        sqlsrv_close($connection);

        return $count;
    }

    /**
     * 格式化 PACS 内容并写入 patient_info_target。
     *
     * @param string $zyh 住院号
     * @param array $patientInfo 患者基础信息
     * @return void
     */
    private function formatPacsContent($zyh, array $patientInfo)
    {
        $insertData = [];
        foreach ([1, 2, 3, 4, 5, 6] as $type) {
            $pacsInfo = $this->getPacsFormatData($type, $zyh, $patientInfo);
            if ($pacsInfo) {
                $insertData[$type] = $pacsInfo;
            }
        }

        if (empty($insertData)) {
            return;
        }

        PatientInfoTarget::query()->updateOrInsert(
            ['ZYH' => $zyh],
            ['pacs_content' => json_encode($insertData, JSON_UNESCAPED_UNICODE)]
        );
    }

    /**
     * 获取指定类型的 PACS 格式化数据。
     *
     * @param int $type 报告类型
     * @param string $zyh 住院号
     * @param array $patientInfo 患者基础信息
     * @return array
     */
    private function getPacsFormatData($type, $zyh, array $patientInfo)
    {
        if ($type == 5) {
            return $this->getInspectPacsFormatData($zyh, $patientInfo);
        }

        $typeData = [
            1 => ['07', '7'],
            2 => ['06', '6'],
            3 => ['01', '02', '03', '04', '05', '09', '11', '1', '2', '3', '4', '5', '9'],
            4 => ['10'],
            6 => ['08', '8'],
        ];

        if (empty($typeData[$type])) {
            return [];
        }

        $query = PACS::query()
            ->where(function ($query) use ($zyh, $patientInfo) {
                $query->where('ZYH', $zyh);
                if (!empty($patientInfo['AAA28'])) {
                    $query->orWhere('JZLSH', $patientInfo['AAA28'])
                        ->orWhere('BAH', $patientInfo['AAA28']);
                }
            })
            ->whereIn('ExamType', $typeData[$type])
            ->orderBy('KDSJ', 'asc');

        if (!empty($patientInfo['AAB01']) && !empty($patientInfo['AAC01'])) {
            $query->whereBetween('KDSJ', [$patientInfo['AAB01'], $patientInfo['AAC01']]);
        }

        $data = $query->get()->toArray();

        if (empty($data)) {
            return [];
        }

        return $this->getPacsTypeFormatData($data, $type, $patientInfo);
    }

    /**
     * 按质控格式组装 PACS 报告数据。
     *
     * @param array $data PACS 原始数据
     * @param int $type 报告类型
     * @param array $patientInfo 患者基础信息
     * @return array
     */
    private function getPacsTypeFormatData(array $data, $type, array $patientInfo)
    {
        $returnData = [];
        $age = $patientInfo['AAA04'] ?? '';

        if ($type == 1) {
            foreach ($data as $key => $val) {
                $returnData[$key] = [
                    'type' => $type,
                    'JYSJ' => $val['JYSJ'] ?? '',
                    'BGSJ' => $val['BGSJ'] ?? '',
                    'BRXM' => $val['BRXM'] ?? ($patientInfo['AAA01'] ?? ''),
                    'BRXB' => $val['BRXB'] ?? ($patientInfo['AAA02C'] ?? ''),
                    'BRNL' => $age,
                    'SQKSMC' => $val['SQKSMC'] ?? ($patientInfo['AAB02C'] ?? ''),
                    'JZLSH' => $val['JZLSH'] ?? ($patientInfo['AAA28'] ?? ''),
                    'JCBGJGMC' => $val['JCBGJGMC'] ?? '',
                    'JCBW' => $this->splitPacsText($val['JCBW'] ?? ''),
                    'StudyUid' => $val['StudyUid'] ?? '',
                    'YXBX' => $this->splitPacsText($val['YXBX'] ?? ''),
                    'YXZD' => $this->splitPacsText($val['YXZD'] ?? ''),
                    'BGRXM' => $val['BGRXM'] ?? '',
                    'SHRXM' => $val['SHRXM'] ?? '',
                    'KDSJ' => $val['KDSJ'] ?? '',
                    'JCKSMC' => $val['JCKSMC'] ?? '',
                    'ExamType' => $val['ExamType'] ?? '',
                ];
            }
        } elseif ($type == 2) {
            foreach ($data as $key => $val) {
                $returnData[$key] = [
                    'type' => $type,
                    'JYSJ' => $val['JYSJ'] ?? '',
                    'BGSJ' => $val['BGSJ'] ?? '',
                    'StudyUid' => $val['StudyUid'] ?? '',
                    'JZLSH' => $val['JZLSH'] ?? ($patientInfo['AAA28'] ?? ''),
                    'BRXM' => $val['BRXM'] ?? ($patientInfo['AAA01'] ?? ''),
                    'BRXB' => $val['BRXB'] ?? ($patientInfo['AAA02C'] ?? ''),
                    'BRNL' => $age,
                    'SQKSMC' => $val['SQKSMC'] ?? ($patientInfo['AAB02C'] ?? ''),
                    'YXBX' => $this->splitPacsText($val['YXBX'] ?? ''),
                    'YXZD' => $this->splitPacsText($val['YXZD'] ?? ''),
                    'JCYS' => $val['JCYS'] ?? '',
                    'SHRXM' => $val['SHRXM'] ?? '',
                    'LRY' => '',
                    'HZYS' => '',
                    'KDSJ' => $val['KDSJ'] ?? '',
                    'JCKSMC' => $val['JCKSMC'] ?? '',
                    'ExamType' => $val['ExamType'] ?? '',
                ];
            }
        } elseif ($type == 3) {
            foreach ($data as $key => $val) {
                $returnData[$key] = [
                    'type' => $type,
                    'JYSJ' => $val['JYSJ'] ?? '',
                    'BGSJ' => $val['BGSJ'] ?? '',
                    'BRXM' => $val['BRXM'] ?? ($patientInfo['AAA01'] ?? ''),
                    'BRXB' => $val['BRXB'] ?? ($patientInfo['AAA02C'] ?? ''),
                    'BRNL' => $age,
                    'PatientID' => $val['PatientID'] ?? '',
                    'SQKSMC' => $val['SQKSMC'] ?? ($patientInfo['AAB02C'] ?? ''),
                    'JZLSH' => $val['JZLSH'] ?? ($patientInfo['AAA28'] ?? ''),
                    'CH' => '',
                    'StudyUid' => $val['StudyUid'] ?? '',
                    'JCMC' => $val['JCMC'] ?? '',
                    'YXBX' => $this->splitPacsText($val['YXBX'] ?? ''),
                    'YXZD' => $this->splitPacsText($val['YXZD'] ?? ''),
                    'BGRXM' => $val['BGRXM'] ?? '',
                    'SHRXM' => $val['SHRXM'] ?? '',
                    'KDSJ' => $val['KDSJ'] ?? '',
                    'JCKSMC' => $val['JCKSMC'] ?? '',
                    'ExamType' => $val['ExamType'] ?? '',
                ];
            }
        } elseif ($type == 4) {
            foreach ($data as $key => $val) {
                $returnData[$key] = [
                    'type' => $type,
                    'JYSJ' => $val['JYSJ'] ?? '',
                    'BGSJ' => $val['BGSJ'] ?? '',
                    'BRXM' => $val['BRXM'] ?? ($patientInfo['AAA01'] ?? ''),
                    'BRXB' => $val['BRXB'] ?? ($patientInfo['AAA02C'] ?? ''),
                    'BRNL' => $age,
                    'SQKSMC' => $val['SQKSMC'] ?? ($patientInfo['AAB02C'] ?? ''),
                    'JZLSH' => $val['JZLSH'] ?? ($patientInfo['AAA28'] ?? ''),
                    'StudyUid' => $val['StudyUid'] ?? '',
                    'JCMC' => $val['JCMC'] ?? '',
                    'YXZD' => $this->splitPacsText($val['YXZD'] ?? ''),
                    'BGRXM' => $val['BGRXM'] ?? '',
                    'SHRXM' => $val['SHRXM'] ?? '',
                    'KDSJ' => $val['KDSJ'] ?? '',
                    'JCKSMC' => $val['JCKSMC'] ?? '',
                    'ExamType' => $val['ExamType'] ?? '',
                ];
            }
        } elseif ($type == 6) {
            foreach ($data as $key => $val) {
                $returnData[$key] = [
                    'type' => $type,
                    'JYSJ' => $val['JYSJ'] ?? '',
                    'BGSJ' => $val['BGSJ'] ?? '',
                    'BRXM' => $val['BRXM'] ?? ($patientInfo['AAA01'] ?? ''),
                    'BRXB' => $val['BRXB'] ?? ($patientInfo['AAA02C'] ?? ''),
                    'BRNL' => $age,
                    'SQKSMC' => $val['SQKSMC'] ?? ($patientInfo['AAB02C'] ?? ''),
                    'JZLSH' => $val['JZLSH'] ?? ($patientInfo['AAA28'] ?? ''),
                    'StudyUid' => $val['StudyUid'] ?? '',
                    'JCMC' => $val['JCMC'] ?? '',
                    'JCBW' => $this->splitPacsText($val['JCBW'] ?? ''),
                    'YXBX' => $this->splitPacsText($val['YXBX'] ?? ''),
                    'YXZD' => $this->splitPacsText($val['YXZD'] ?? ''),
                    'BGRXM' => $val['BGRXM'] ?? '',
                    'SHRXM' => $val['SHRXM'] ?? '',
                    'KDSJ' => $val['KDSJ'] ?? '',
                    'JCKSMC' => $val['JCKSMC'] ?? '',
                    'ExamType' => $val['ExamType'] ?? '',
                ];
            }
        }

        foreach ($returnData as &$v) {
            if (!empty($v['BRXM'])) {
                $v['BRXM'] = desensitize($v['BRXM'], 1, 0, '*');
            }
        }

        return $returnData;
    }

    /**
     * 按原格式组装检验报告单数据。
     *
     * @param string $zyh 住院号
     * @param array $patientInfo 患者基础信息
     * @return array
     */
    private function getInspectPacsFormatData($zyh, array $patientInfo)
    {
        $data = [];

        $ymRows = \App\Model\V_JMGS_YMresult::query()
            ->where('ZYH', $zyh)
            ->get()
            ->toArray();
        $txmList = [];
        foreach ($ymRows as $value) {
            if (!empty($value['TXM']) && !in_array($value['TXM'], $txmList)) {
                $txmList[] = $value['TXM'];
            }
        }

        foreach ($txmList as $txm) {
            $txmData = \App\Model\V_JMGS_YMresult::query()
                ->where('TXM', $txm)
                ->get()
                ->toArray();
            if (empty($txmData)) {
                continue;
            }

            $data1 = $this->getJcBgdPublicData($zyh, 1, $txmData[0], $patientInfo);
            $data1Jcxm = [];
            foreach ($txmData as $txmInfo) {
                $data1Jcxm[] = [
                    'PYJG' => $txmInfo['PYJG'] ?? '',
                    'XJMC' => $txmInfo['XJMC'] ?? '',
                    'XJSL' => '',
                    'YMMC' => $txmInfo['YMMC'] ?? '',
                    'YMJG' => $txmInfo['YMJG'] ?? '',
                    'YMBW' => $txmInfo['YMBW'] ?? '',
                ];
            }
            $data1['JCXM'] = $data1Jcxm;
            $data[] = $data1;
        }

        $testRows = \App\Model\V_JMGS_TESTRESULT::query()
            ->where('ZYH', $zyh)
            ->get()
            ->toArray();
        $txmList = [];
        foreach ($testRows as $value) {
            if (!empty($value['TXM']) && !in_array($value['TXM'], $txmList)) {
                $txmList[] = $value['TXM'];
            }
        }

        foreach ($txmList as $txm) {
            $txmData = \App\Model\V_JMGS_TESTRESULT::query()
                ->where('TXM', $txm)
                ->get()
                ->toArray();
            if (empty($txmData)) {
                continue;
            }

            $data2 = $this->getJcBgdPublicData($zyh, 2, $txmData[0], $patientInfo);
            $data2Jcxm = [];
            foreach ($txmData as $txmInfo) {
                $data2Jcxm[] = [
                    'JYXM' => $txmInfo['JYXM'] ?? '',
                    'YW' => $txmInfo['YW'] ?? '',
                    'JG' => $txmInfo['JG'] ?? '',
                    'TS' => $txmInfo['TS'] ?? '',
                    'CKFW' => $txmInfo['CKFW'] ?? '',
                    'DW' => $txmInfo['DW'] ?? '',
                ];
            }
            $data2['JCXM'] = $data2Jcxm;
            $data[] = $data2;
        }

        return $data;
    }

    /**
     * 获取检验报告公共字段。
     *
     * @param string $zyh 住院号
     * @param int $templateType 模板类型
     * @param array $txmInfo 条码数据
     * @param array $patientInfo 患者基础信息
     * @return array
     */
    private function getJcBgdPublicData($zyh, $templateType, array $txmInfo, array $patientInfo)
    {
        $jyy = !empty($txmInfo['JYY']) ? Staff::query()->where('code', '=', $txmInfo['JYY'])->value('name') : '';
        $shy = !empty($txmInfo['SHY']) ? Staff::query()->where('code', '=', $txmInfo['SHY'])->value('name') : '';
        $name = $txmInfo['XM'] ?? ($patientInfo['AAA01'] ?? '');
        if (!empty($name)) {
            $name = desensitize($name, 1, 0, '*');
        }

        return [
            'type' => 5,
            'template_type' => $templateType,
            'TXM' => $txmInfo['TXM'] ?? '',
            'NO' => $txmInfo['NO'] ?? '',
            'XM' => $name,
            'XB' => $txmInfo['XB'] ?? ($patientInfo['AAA02C'] ?? ''),
            'NL' => $txmInfo['NL'] ?? ($patientInfo['AAA04'] ?? ''),
            'CH' => $txmInfo['CH'] ?? '',
            'YBLX' => $txmInfo['YBLX'] ?? '',
            'YBZT' => $txmInfo['YBZT'] ?? '',
            'ZYH' => $txmInfo['AAA28'] ?? ($patientInfo['AAA28'] ?? ''),
            'BQ' => $txmInfo['BQ'] ?? '',
            'LCZD' => $txmInfo['LCZD'] ?? '',
            'SJYS' => $txmInfo['SJYS'] ?? '',
            'JYY' => $jyy,
            'SHY' => $shy,
            'CJSJ' => $txmInfo['CJSJ'] ?? '',
            'JSSJ' => $txmInfo['JSSJ'] ?? '',
            'BGSJ' => $txmInfo['BGSJ'] ?? '',
            'AAA28' => $txmInfo['AAA28'] ?? ($patientInfo['AAA28'] ?? ''),
        ];
    }

    /**
     * 获取 PACS MySQL 源库连接。
     *
     * @return \PDO|null
     */
    private function getPacsMysqlConnect()
    {
        try {
            $host = env('PACS_MYSQL_HOST', '192.168.53.33');
            $port = env('PACS_MYSQL_PORT', '3306');
            $database = env('PACS_MYSQL_DATABASE', 'clinical');
            $username = env('PACS_MYSQL_USERNAME', 'jnsyyyxt');
            $password = env('PACS_MYSQL_PASSWORD', 'Jnsy_yyxt5');
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

            return new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 获取检查类型编码。
     *
     * @param string $examClassCode 检查分类编码
     * @param string $examClassName 检查分类名称
     * @param string $examItemName 检查项目名称
     * @return string
     */
    private function getPacsExamType($examClassCode, $examClassName = '', $examItemName = '')
    {
        $examTypeConf = config('pacsMap.ExamType', []);
        if (!empty($examTypeConf[$examClassCode]['EXAM_TYPE_CODE'])) {
            return $examTypeConf[$examClassCode]['EXAM_TYPE_CODE'];
        }

        return '';
    }

    /**
     * 格式化 SQL Server 查询结果。
     *
     * @param array $row 原始行
     * @return array
     */
    private function formatSqlsrvRow(array $row)
    {
        foreach ($row as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $row[$key] = $value->format('Y-m-d H:i:s');
            } elseif ($value === null) {
                $row[$key] = '';
            }
        }

        return $row;
    }

    /**
     * 将报告文本按行拆分。
     *
     * @param string $text 报告文本
     * @return array
     */
    private function splitPacsText($text)
    {
        $text = trim((string)$text);
        if ($text === '') {
            return [];
        }

        return preg_split('/\r\n|\r|\n/', $text);
    }
}
