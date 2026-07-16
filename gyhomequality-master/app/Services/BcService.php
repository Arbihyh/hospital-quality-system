<?php

namespace App\Services;

use App\Model\DiseaseDiagnosisCode;
use App\Model\CaseQuality;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\EventListener\ValidateRequestListener;
use App\Model\CaseRule;

/**
 * 病例分析
 */
class BcService
{
    const ID = 4257116;

    public static function checkCaseList()
    {

        $page = 1;
        $pageSize = 10;
        $column = ['BLBH', 'MBLB'];
        while (1) {
            $res = EMR_BL_BL01::getList($page, $pageSize, $column, ['BLLB' => 294]);
            if (!$res) {
                echo '获取数据完毕';
                break;
            }
            foreach ($res as $no) {
                $checkRes = self::checkCase($no['BLBH'], $no['MBLB']);
                if (empty($checkRes)) {
                    continue;
                }
                if ($no['MBLB'] == 295) {
                    $eidtData = ['bcts' => $checkRes];
                } else {
                    $eidtData = ['bc_content' => $checkRes];
                }
                $res = EMR_BL_BL01::updateById($no['BLBH'], $eidtData);
                var_dump($res);
            }
            $page++;
        }

        var_dump("病程信息处理完毕");
    }

    /**
     * @param string $no
     * @param string $mblb
     * @return array|mixed|string
     */
    public static function checkCase($no = '', $mblb = '')
    {
        $errorNotice = [];
        $title = ['病例特点', '诊断依据', '鉴别诊断', '诊疗计划'];

        if (empty($no)) {
            $no = self::ID;
        }
        $res = EMR_BL_BLXG::getById($no);
        if (!$res) {
            return '';
        }
        $caseContent = $res[0]['HJNR'];

        // 整理数据，将数据整理成数组结构
        $caseContent = str_replace("\r\n", "", $caseContent);
        $caseContent = str_replace("\n", "", $caseContent);
//        $caseContent = str_replace("：", ":", $caseContent);
//        $caseContent = str_replace(['.', '(', ')', '（', '）', "“", "”", "，", "。", "；", "、", ' '], "", $caseContent);

        // 非首次病程则直接返回数据
        if ($mblb != 295) {
            return $caseContent;
        }
        foreach ($title as $t) {
            $caseContent = str_replace($t, "|&|" . $t . '||', $caseContent);
        }
        $caseContentArr = explode("|&|", $caseContent);

        $newData = [];
        foreach ($caseContentArr as $item) {
            $itemArr = explode("||", $item);
            if (!in_array($itemArr[0], $title) || !isset($itemArr[1])) {
                continue;
            }
            $info['title'] = $itemArr[0];
            $info['value'] = $itemArr[1] ?? '';
            $newData[] = $info;
        }
        if (empty($newData)) {
            return '';
        }
        $bcStr = $newData[0]['value'];
//        $myArray = preg_split('//u', $bcStr, null, PREG_SPLIT_NO_EMPTY);

        return $bcStr;
    }

}

