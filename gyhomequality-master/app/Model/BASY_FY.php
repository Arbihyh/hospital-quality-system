<?php

namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class BASY_FY extends Model
{
    const FYMC_LIST = [
        'D01' => '住院总费用',
        'D09' => '住院总费用其中自付金额',
        'D11' => '一般医疗服务费',
        'D12' => '一般治疗操作费',
        'D13' => '护理费',
        'D14' => '综合医疗服务类其他费用',
        'D15' => '病理诊断费',
        'D16' => '实验室诊断费',
        'D17' => '影像学诊断费',
        'D18' => '临床诊断项目费',
        'D19' => '非手术治疗项目费',
        'D19x01' => '临床物理治疗费',
        'D20' => '手术治疗费',
        'D20x01' => '麻醉费',
        'D20x02' => '手术费',
        'D21' => '康复费',
        'D22' => '中医治疗费',
        'D23' => '西药费',
        'D23x01' => '抗菌药物费',
        'D24' => '中成药费',
        'D25' => '中草药费',
        'D26' => '血费',
        'D27' => '白蛋白类制品费',
        'D28' => '球蛋白类制品费',
        'D29' => '凝血因子类制品费',
        'D30' => '细胞因子类制品费',
        'D31' => '检查用一次性医用材料费',
        'D32' => '治疗用一次性医用材料费',
        'D33' => '手术用一次性医用材料费',
        'D34' => '其他费'
    ];

    protected $table = 'BASY_FY';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }


}
