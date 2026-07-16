<?php

namespace App\Services\QualityControl;

use App\Model\Bllb292;
use App\Model\CaseQuality;
use App\Model\CaseRule;
use App\Model\PatientInfo;
use App\Services\CaseService;
use App\Services\ElasticsearchService;
use Carbon\Carbon;

/**
 * 专科质控
 */
class ZkzkService
{
    protected $insertData = [];

    /**
     * 专科质控入口
     * @param $caseRule
     * @param $info
     * @return true
     */
    public function qualityControl($caseRule, $info)
    {
        $ZYH = $info['MED_REC_ID'] ?? '';
        $this->insertData = [];

        // 要调用的方法
        $methodList = [
            156 => 'rule156',    // 入院记录-婚育史-月经周期
//            157 => 'rule157',    // 入院记录-婚育史-经期
            158 => 'rule158',    // 入院记录-婚育史-月经周期或经期
        ];
        $cs = new CaseService();
        foreach ($methodList as $ruleId => $method) {
            // 规则关闭则不进行质控
            if (empty($caseRule[$ruleId]['status']) || in_array($ruleId, $cs->appealRuleIds)) {
                continue;
            }

            // 调用质控规则
            $this->$method($caseRule, $ruleId, $info);
        }

        $insertData = $this->insertData;
        if (!empty($insertData)) {
            // 同步修改病例数据的缺陷状态
            foreach ($insertData as $v) {
                CaseQuality::addData($v);
            }
        }

        return true;
    }

    /**
     * 入院记录-婚育史-月经周期
     * @param $caseRule
     * @param $ruleId
     * @param $info
     * @return true
     */
    public function rule156($caseRule, $ruleId, $info)
    {
        $ZYH = $info['MED_REC_ID'] ?? '';

        $bllb292Service = new ElasticsearchService('bllb292_2023');
        $must = [['term' => ['ZYH' => $ZYH]]];
        $must[] = ['term'=>['XB'=>"女性"]];
       // $mustNot = [['term' => ['XB'=>'男']]];
        $params = $bllb292Service->clearMust()
            ->queryByMustBatch($must)
           // ->queryByMustNotBatch($mustNot)
            ->getParams();
        $restful = app('es')->search($params);
        $data = $bllb292Service->getDataByEs($restful);

        if (empty($data[0])) {
            return true;
        }

        // 月经周期
        $YJJHYS = $data[0][0]['YJJHYS'];
        if (stripos($YJJHYS,'绝经') !== false || stripos($YJJHYS,'月经史不详') !== false) {
            return true;
        }
        $res1 = preg_match('/(月经周期)\d{1,3}(\-|\~|\－)\d{1,3}(天|日|月|年)/', $YJJHYS);
        $res2 = preg_match('/(月经周期)\d{1,3}(天|日|月|年)/', $YJJHYS);
        $res3 = preg_match('/(\/|\／)\d{1,3}(\-|\~|－)\d{1,3}(天|日|月|年)/', $YJJHYS);
        $basis = [];
        if (!$res1 && !$res2 && !$res3) {
            $basis[] = ['月经周期【无】'];
        }

        // 经期
        $res1 = preg_match('/(经期)\d{1,3}(\-|\~)\d{1,3}(天|日|月|年)/', $YJJHYS);
        $res2 = preg_match('/(经期)\d{1,3}(天|日|月|年)/', $YJJHYS);
        $res3 = preg_match('/\d{1,3}(\-|\~|－)\d{1,3}(天|日|月|年)(\/|\／)/', $YJJHYS);
        if (!$res1 && !$res2 && !$res3) {
            $basis[] = ['经期【无】'];
        }

        if ($basis) {
            $this->insertData[] = [
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => $ruleId,
                'code' => 'rule_'.$ruleId,
                'error_field' => $caseRule[$ruleId]['title'] ?? '婚育史',
                'basis' => json_encode($basis, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 入院记录-婚育史-经期
     * @param $caseRule
     * @param $ruleId
     * @param $info
     * @return true
     */
    public function rule157($caseRule, $ruleId, $info)
    {
        $ZYH = $info['MED_REC_ID'] ?? '';

        $bllb292Service = new ElasticsearchService('bllb292_2023');
        $must = [['term' => ['ZYH' => $ZYH]]];
        $mustNot = [['term' => ['XB'=>'男']]];
        $params = $bllb292Service->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNotBatch($mustNot)
            ->getParams();
        $restful = app('es')->search($params);
        $data = $bllb292Service->getDataByEs($restful);
        if (empty($data[0])) {
            return true;
        }

        $YJJHYS = $data[0][0]['YJJHYS'];
        if (stripos($YJJHYS,'绝经') !== false || stripos($YJJHYS,'月经史不详') !== false) {
            return true;
        }

        $res1 = preg_match('/(经期)\d{1,3}(\-|\~)\d{1,3}(天|日|月|年)/', $YJJHYS);
        $res2 = preg_match('/(经期)\d{1,3}(天|日|月|年)/', $YJJHYS);
        $res3 = preg_match('/\d{1,3}(\-|\~|－)\d{1,3}(天|日|月|年)(\/|\／)/', $YJJHYS);

        $basis = [];
        if (!$res1 && !$res2 && !$res3) {
            $basis[] = ['经期【无】'];
        }

        if ($basis) {
            $this->insertData[] = [
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => $ruleId,
                'code' => 'rule_'.$ruleId,
                'error_field' => $caseRule[$ruleId]['title'] ?? '婚育史',
                'basis' => json_encode($basis, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

    /**
     * 入院记录-婚育史-月经周期或经期
     * @param $caseRule
     * @param $ruleId
     * @param $info
     * @return true
     */
    public function rule158($caseRule, $ruleId, $info)
    {
        $ZYH = $info['MED_REC_ID'] ?? '';

        $bllb292Service = new ElasticsearchService('bllb292_2023');
        $must = [['term' => ['ZYH' => $ZYH]]];
        $mustNot = [['term' => ['XB'=>'男']]];
        $params = $bllb292Service->clearMust()
            ->queryByMustBatch($must)
            ->queryByMustNotBatch($mustNot)
            ->getParams();
        $restful = app('es')->search($params);
        $data = $bllb292Service->getDataByEs($restful);
        if (empty($data[0])) {
            return true;
        }

        $CBZD = $data[0][0]['CBZD'];
        $YJJHYS = $data[0][0]['YJJHYS'];
        if (stripos($YJJHYS,'绝经') !== false || stripos($YJJHYS,'月经史不详') !== false) {
            return true;
        }

        $basis = [];
        $res1 = preg_match('/(月经周期)\d{1,3}(\-|\~|\－)\d{1,3}(\天|\日|\月|\年)/', $YJJHYS,$arr1);
        if ($res1) {
            // 月经周期
            $str = str_replace("月经周期","",$arr1[0]);
            $str = str_replace("天","",$str);
            $str = str_replace("日","",$str);
            $str = str_replace("月","",$str);
            $str = str_replace("年","",$str);
            $str = str_replace("－","-",$str);
            $str = str_replace("~","-",$str);
            $arr = explode("-", $str);
            $arr[0] = !empty($arr[0]) ? $arr[0] : 0;
            $arr[1] = !empty($arr[1]) ? $arr[1] : 0;
            if ($arr[0] < 21) {
                $basis[] = ['月经周期【小于21天】'];
            }
            if ($arr[1] > 35) {
                $basis[] = ['月经周期【大于35天】'];
            }
        } else {
            $res2 = preg_match('/(月经周期)\d{1,3}(\天|\日|\月|\年)/', $YJJHYS, $arr2);
            if ($res2) {
                $str = str_replace("月经周期","",$arr2[0]);
                $str = str_replace("天","",$str);
                $str = str_replace("日","",$str);
                $str = str_replace("月","",$str);
                $str = str_replace("年","",$str);
                $str = str_replace("－","-",$str);
                $str = str_replace("~","-",$str);
                if ($str < 21) {
                    $basis[] = ['月经周期【小于21天】'];
                }
                if ($str > 35) {
                    $basis[] = ['月经周期【大于35天】'];
                }
            } else {
                $res3 = preg_match('/(\/|\／)\d{1,3}(\-|\~|\－)\d{1,3}(\天|\日|\月|\年)/', $YJJHYS, $arr3);
                if ($res3) {
                    $str = str_replace("－","-",$arr3[0]);
                    $str = str_replace("~","-",$str);
                    $str = str_replace("／","",$str);
                    $str = str_replace("/","",$str);
                    $str = str_replace("天","",$str);
                    $str = str_replace("日","",$str);
                    $str = str_replace("月","",$str);
                    $str = str_replace("年","",$str);
                    $arr = explode("-", $str);
                    $arr[0] = !empty($arr[0]) ? $arr[0] : 0;
                    $arr[1] = !empty($arr[1]) ? $arr[1] : 0;
                    if ($arr[0] < 21) {
                        $basis[] = ['月经周期【小于21天】'];
                    }
                    if ($arr[1] > 35) {
                        $basis[] = ['月经周期【大于35天】'];
                    }
                }
            }
        }

        // 经期
        $res4 = preg_match('/(经期)\d{1,3}(\-|\~)\d{1,3}(\天|\日|\月|\年)/', $YJJHYS, $arr4);
        if ($res4) {
            $str = str_replace("经期","",$arr4[0]);
            $str = str_replace("天","",$str);
            $str = str_replace("日","",$str);
            $str = str_replace("－","-",$str);
            $str = str_replace("~","-",$str);
            $arr = explode("-", $str);
            $arr[0] = !empty($arr[0]) ? $arr[0] : 0;
            $arr[1] = !empty($arr[1]) ? $arr[1] : 0;
            if ($arr[0] < 3) {
                $basis[] = ['经期【小于3天】'];
            }
            if ($arr[1] > 7) {
                $basis[] = ['经期【大于7天】'];
            }
        } else {
            $res5 = preg_match('/(经期)\d{1,3}(\天|\日|\月|\年)/', $YJJHYS, $arr5);
            if ($res5) {
                $str = str_replace("经期","",$arr5[0]);
                $str = str_replace("天","",$str);
                $str = str_replace("日","",$str);
                $str = str_replace("－","-",$str);
                $str = str_replace("~","-",$str);
                if ($str < 3) {
                    $basis[] = ['经期【小于3天】'];
                }
                if ($str > 7) {
                    $basis[] = ['经期【大于7天】'];
                }
            } else {
                $res6 = preg_match('/\d{1,3}(\-|\~|\－)\d{1,3}(\天|\日|\月|\年)(\/|\／)/', $YJJHYS, $arr6);
                if ($res6) {
                    $str = str_replace("天","",$arr6[0]);
                    $str = str_replace("日","",$str);
                    $str = str_replace("－","-",$str);
                    $str = str_replace("~","-",$str);
                    $str = str_replace("／","",$str);
                    $str = str_replace("/","",$str);
                    $arr = explode("-", $str);
                    $arr[0] = !empty($arr[0]) ? $arr[0] : 0;
                    $arr[1] = !empty($arr[1]) ? $arr[1] : 0;
                    if ($arr[0] < 3) {
                        $basis[] = ['经期【小于3天】'];
                    }
                    if ($arr[1] > 7) {
                        $basis[] = ['经期【大于7天】'];
                    }
                }
            }
        }

        // 初步诊断名称：含“异常子宫出血”
        if (!empty($basis) && stripos($CBZD,'异常子宫出血') === false) {
            $basis[] = ['初步诊断【异常子宫出血（无）】'];
        }

        if ($basis) {
            $this->insertData[] = [
                'JZHM' => $info['MED_REC_ID'],
                'rule_id' => $ruleId,
                'code' => 'rule_'.$ruleId,
                'error_field' => $caseRule[$ruleId]['title'] ?? '婚育史',
                'basis' => json_encode($basis, JSON_UNESCAPED_UNICODE)
            ];
        }

        return true;
    }

}
