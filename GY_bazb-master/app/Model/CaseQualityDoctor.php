<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

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
        $dep = Department::query()->get(['dep_id', 'dep_name'])->toArray();
        $dep = array_column($dep, 'dep_name', 'dep_id');

        # 根据依据中的blbh汇总case_quality_doctor数据
        $staff = Staff::query()->get(["code", "name", 'ksdm'])->toArray();
        $staff = array_column($staff, null, "code");
        $staffByBh = array_column($staff, null, "YGBH");

        // 记录书写医师信息
        $bl01 = EMR_BL_BL01::query()->where("BLBH", $blbh)->first(["SXYS", "JZHM", "BRBH"]);
        if(!$bl01){
            return false;
        }
        $bl01 = $bl01->toArray();
        if ($bl01["SXYS"]) {
            CaseQualityDoctor::query()->insert([
                "AAA28" => $bl01["BRBH"],
                "ZYH" => $bl01["JZHM"],
                "BLBH" => $blbh,
                "code" => $bl01["SXYS"],
                "name" => $staffByBh[$bl01["SXYS"]]['name'] ?? $staff[$bl01["SXYS"]]['name'] ?? "",
                "dep_id" => $staffByBh[$bl01["SXYS"]]['ksdm'] ?? $staff[$bl01["SXYS"]]['ksdm'] ?? "",
                "dep_name" => $dep[$staffByBh[$bl01["SXYS"]]['ksdm'] ?? ""] ?? $dep[$staff[$bl01["SXYS"]]['ksdm'] ??""] ?? "",
                'rule_id' => $data['rule_id']
            ]);
        }
        // 记录签名医师，签名医师存在多个的情况，所以需要循环保存。
        $blsy = EMR_BL_BLSY::query()->where("BLBH", $blbh)->get(["SYYS"])->toArray();
        if ($blsy) {
            foreach ($blsy as $bl) {
                CaseQualityDoctor::query()->insert([
                    "AAA28" => $bl01["BRBH"],
                    "ZYH" => $bl01["JZHM"],
                    "BLBH" => $blbh,
                    "code" => $bl["SYYS"],
                    "name" => $staff[$bl["SYYS"]]['name'] ?? "",
                    "dep_id" => $staff[$bl["SYYS"]]['ksdm'] ?? "",
                    "dep_name" => $dep[$staff[$bl["SYYS"]]['ksdm']] ?? "",
                    'rule_id' => $data['rule_id']
                ]);
            }
        }
    }
}
