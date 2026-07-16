<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ErrorRule extends Model
{
    protected $table = 'error_rule';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

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
}
