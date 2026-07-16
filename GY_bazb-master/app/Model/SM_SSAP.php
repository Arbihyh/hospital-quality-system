<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class SM_SSAP extends Model
{
    protected $table = 'SM_SSAP';

    public static function addData($data = [])
    {
        $insertData = [
            'ZYHM' => $data["BAH"] ?? $data["ZYHM"] ?? "",
            'ZYH' => $data["ZYH"] ?? "",
            'SSRQ' => isset($data["SSRQ"]) && !empty($data["SSRQ"]) ? platformTime($data["SSRQ"]) : '',
            'JSRQ' => isset($data["JSRQ"]) && !empty($data["SSRQ"]) ? platformTime($data["JSRQ"]) : '',
            'ZYCS' => $data["ZYCS"] ?? "",
            'SZDM' => $data["SZDM"] ?? "",
            'SZ' => $data["SZ"] ?? "",
            'SSKS' => $data["SSKS"] ?? "",
            'SSKS_MC' => $data["SSKS_MC"] ?? "",
            'SQKS' => $data["SQKS"] ?? "",
            'SQKS_MC' => $data["SQKS_MC"] ?? "",
            'SQYS' => $data["SQYS"] ?? "",
            'SQYS_MC' => $data["SQYS_MC"] ?? "",
            'SQRQ' => isset($data["SQRQ"]) && !empty($data["SQRQ"]) ? platformTime($data["SQRQ"]) : '',
            'SQDH' => $data["SQDH"] ?? "",
            'flag' => $data["flag"] ?? "",
            'ICD9_SSCZBM' => $data["ICD9_SSCZBM"] ?? "",
            'ICD9_SSCZMC' => $data["ICD9_SSCZMC"] ?? "",
            'MZDM' => $data["MZDM"] ?? "",
            'MZMC' => $data["MZMC"] ?? "",
            'MZYS' => $data["MZYS"] ?? "",
            'MZYS_MC' => $data["MAYS_MC"] ?? "",
            'MZKSSJ' => $data["MZKSSJ"] ?? "",
            'MZJSSJ' => $data["MZJSSJ"] ?? "",
        ];

        $insertData['MZMC'] = $insertData['MZMC'] != 'NULL' ? $insertData['MZMC'] : "";
        $insertData['MZYS_MC'] = $insertData['MZYS_MC'] != 'NULL' ? $insertData['MZYS_MC'] : "";

        if (empty($insertData["SQDH"])) {
            self::query()->updateOrInsert(["ZYHM" => $insertData["ZYHM"], 'SQRQ' => $insertData["SQRQ"]], $insertData);
        } else {
            $data = self::query()->where(["SQDH" => $insertData["SQDH"]])->first();
            if (empty($data)) {
                self::query()->insert($insertData);
            } elseif (!empty($insertData['MZYS_MC'])) {
                self::query()->where(["SQDH" => $insertData["SQDH"]])->update($insertData);
            }
        }
    }
}
