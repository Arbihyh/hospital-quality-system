<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class CaseQualityDoctor extends Model
{
    protected $table = 'case_quality_doctor';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function addData($blbh = "", $data = [])
    {
        if (empty($blbh)) {
            return false;
        }
        Log::info('111人工质控:',$data);

        // 记录书写医师信息
        $bl01 = EMR_BL_BL01::query()->where("BLBH", $blbh)->first(["SXYS", "JZHM", "BRBH"])->toArray();
        if ($bl01["SXYS"]) {
            CaseQualityDoctor::query()->insert([
                "AAA28" => $data["AAA28"],
                "ZYH" => $bl01["JZHM"],
                "BLBH" => $blbh,
                "code" => $bl01["SXYS"],
                "name" => $staff[$bl01["SXYS"]] ?? "",
                'rule_id' => $data['rule_id']
            ]);
        }
        // 记录签名医师，签名医师存在多个的情况，所以需要循环保存。
        $blsy = EMR_BL_BLSY::query()->where("BLBH", $blbh)->get(["SYYS"])->toArray();
        if ($blsy) {
            foreach ($blsy as $bl) {
                CaseQualityDoctor::query()->insert([
                    "AAA28" => $data["AAA28"],
                    "ZYH" => $bl01["JZHM"],
                    "BLBH" => $blbh,
                    "code" => $bl["SYYS"],
                    "name" => $staff[$bl["SYYS"]] ?? "",
                    'rule_id' => $data['rule_id']
                ]);
            }
        }
    }
}
