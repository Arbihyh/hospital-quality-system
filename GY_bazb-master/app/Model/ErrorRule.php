<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class ErrorRule extends Model
{
    const RULE_TYPE = ['患者基本信息','诊疗信息','费用信息'];
    const RULE_LEVEL = ['强制','建议'];

    const RULE_TYPE_CODE = ['患者基本信息'=>0,'诊疗信息'=>1,'费用信息'=>2];
    const RULE_LEVEL_CODE = ['强制'=>0,'建议'=>1];
    const ZKDX = ['通用','临床','编码员'];
    const ZKFL = ['通用','国考','卫统','医保'];
    const CATEGORY = ['A类','B类','C类','D类'];

    // 优良中差分数
    const YLZC = [
        'you'   => [97,100],
        'liang' => [90,96.9],
        'zhong' => [75,89.9],
        'cha'   => [0,75.9],
    ];

    protected $table = 'error_rule';

    /**
     * 获取质控规则
     * @param $ruleId
     * @param $zkdx
     * @return array
     */
    public static function getErrorRule($zkdx=[],$ruleId='')
    {
        if (!empty($ruleId)) {
            $errorRuleData = ErrorRule::query()->where('id','=',$ruleId)->get()->toArray();
        } else {
            $zkdx = !empty($zkdx) ? $zkdx : [0];
            $errorRuleData = ErrorRule::query()
                ->whereIn('ZKDX',$zkdx)
                ->where('status','=',0)
                ->orderBy('id')
                ->get()->toArray();
        }

        return array_column($errorRuleData, null, 'id');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
