<?php

namespace App\Services;

use App\Model\CaseQuality;
use App\Model\CaseRule;
use Carbon\Carbon;
use Illuminate\Filesystem\Cache;

class QualityControlService
{
    // 质控规则方法
    public $method = [
        'role99', // 患者入院后24小时内完成入院记录
    ];

    public function __construct()
    {
        //所有添加的质控规则
        $caseRule = CaseRule::query()->get()->toArray();
        $this->caseRule = array_column($caseRule, null, 'id');
    }

    /**
     * 质控
     * @param $zyh
     * @param $type
     * @param $data
     * @return mixed
     */
    public function qualityContrl($zyh, $data)
    {
        // 住院号
        $this->zyh = $zyh;

        // 要调用的质控规则
//        $rule = 'rule' . $type;

        // 验证质控规则是否存在
//        if (!in_array($rule, $this->method)) {
//            throw new \Error('质控规则不存在');
//        }

        // 质控
        $errorNotice = $this->rule99($zyh,$data);
        if ($errorNotice) {
            $errorNotice['status'] = 1; // 设置有问题的质控数据
            CaseQuality::query()->updateOrInsert(['rule_id'=>$errorNotice['rule_id'], 'JZHM'=>$errorNotice['JZHM']], $errorNotice);
        }

        return $errorNotice;
    }

    /**
     * 入院记录
     * @param $ZYH
     * @param $data
     * @return array
     */
    public function rule99($ZYH,$data)
    {
        $caseRule = $this->caseRule;

        $HCRQ = $data['HCRQ'];
        $HCRQ_END = Carbon::parse($HCRQ)->addDay()->toDateTimeString();
        $ZXSJ = $data['RYJLZXSJ'];
        if(empty($HCRQ) || empty($ZXSJ)){
            return [];
        }

        $insertData = [];
        if ($ZXSJ) {
            if (empty($HCRQ)) {
                $insertData = [
                    'JZHM' => $ZYH,
                    'rule_id' => 99,
                    'code' => 'cyjl',
                    'error_field' => $caseRule[99]['title'],
                    'basis' => json_encode([['入院时间【无】，入院记录执行时间【'.$ZXSJ.'】']],JSON_UNESCAPED_UNICODE)
                ];
            } elseif ($HCRQ > $ZXSJ || $HCRQ_END < $ZXSJ) {
                $insertData = [
                    'JZHM' => $ZYH,
                    'rule_id' => 99,
                    'code' => 'cyjl',
                    'error_field' => $caseRule[99]['title'],
                    'basis' => json_encode([['入院时间【'.$HCRQ.'】，入院记录执行时间【'.$ZXSJ.'（超24小时）】']],JSON_UNESCAPED_UNICODE)
                ];
            }
        } else {
            $HCRQ = !empty($HCRQ) ? $HCRQ : '无';
            $insertData = [
                'JZHM' => $ZYH,
                'rule_id' => 99,
                'code' => 'cyjl',
                'error_field' => $caseRule[99]['title'],
                'basis' => json_encode([['入院时间【'.$HCRQ.'】，入院记录执行时间【无】']],JSON_UNESCAPED_UNICODE)
            ];
        }

        return $insertData;
    }

}
