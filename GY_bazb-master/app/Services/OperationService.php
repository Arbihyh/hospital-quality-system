<?php

namespace App\Services;

use App\Model\Setting;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Model\EMR_BL_BL01;
use App\Model\EMR_BL_BLXG;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\EventListener\ValidateRequestListener;
use App\Model\Staff;

/**
 *
 */
class OperationService
{
    public static function checkList($zyh=0)
    {

        $column = ['BLBH', 'MBLB', 'JZHM'];
        $setName = 'qx_OperationBl01Clean_last_id';
        $lastId = Setting::query()->where('name', '=', $setName)->pluck('content')->first();
        $lastId = $lastId ?: 0;
        if ($zyh) {
            $lastId = 0;
        }

        while (1) {
            $query = EMR_BL_BL01::query()
                ->where('BLLB', '=', 303)
                ->whereIn('MBLB', [74, 306])
                ->where('BLBH', '>', $lastId)
                ->limit(1000);
            if ($zyh) {
                $query = $query->whereIn('JZHM', $zyh);
            }

            $res = $query->get($column)->toArray();
            if (!$res) {
                break;
            }
            foreach ($res as $no) {
                $lastId = $no['BLBH'];
                self::checkCase($no['BLBH']);
            }
        }
        if (!$zyh) {

            Setting::query()->where('name', '=', $setName)->update(['content' => $lastId]);

        }


    }

    /**
     * @param string $BLBH
     * @param string $mblb
     * @return array|mixed|string
     *
     * ALTER TABLE `quality`.`EMR_BL_BL01`
     * ADD COLUMN `operation_handler` varchar(255) NULL DEFAULT '' COMMENT '手术者' AFTER `bingcheng_content`,
     * ADD COLUMN `operation_handler_code` varchar(255) NULL DEFAULT '' COMMENT '手术者工号' AFTER `operation_handler`;
     */
    public static function checkCase($BLBH = '')
    {
        $res = EMR_BL_BLXG::getById($BLBH);
        if (!$res) {
            return '';
        }
        $hjnr = $res[0]['HJNR'];

        $eidtData = self::analysisHjnr($hjnr);
        if (empty($eidtData)) {
            return [];
        }
        EMR_BL_BL01::updateById($BLBH, $eidtData);
    }

    public static function analysisHjnr($hjnr = '')
    {
        if (empty($hjnr)) {
            return [];
        }
        $caseContent = str_replace("\r\n", "", $hjnr);
        $caseContent = str_replace("\n", "", $caseContent);
        $caseContent = str_replace('手术日期：', "|&|手术日期||", $caseContent);
        $caseContent = str_replace('手术时间：', "|&|手术时间||", $caseContent);
        $caseContent = str_replace('术前诊断：', "|&|术前诊断||", $caseContent);
        $caseContentArr = explode("|&|", $caseContent);
        if (empty($caseContentArr[1])) {
            return 0;
        }

        $timeRes = explode('||', $caseContentArr[1]);
        $operationDate = trim($timeRes[1]);

        preg_match_all('/\d{4}-\d{1,2}-\d{1,2}/', $operationDate, $res);
        preg_match_all('/\d{4}年\d{1,2}月\d{1,2}日/', $operationDate, $res1);

        $time = 0;
        if (!empty($res[0])) {
            $time = strtotime($operationDate);
        } elseif (!empty($res1[0])) {
            $operationDateRes = date_parse_from_format("Y年m月d日", $operationDate);
            $time = mktime(0, 0, 0, $operationDateRes['month'], $operationDateRes['day'], $operationDateRes['year']);
        }

        $endTime = 0;
        if (!empty($caseContentArr[2])) {
            $endTimeArr = explode('||', $caseContentArr[2]);
            if (!empty($endTimeArr) && !empty($time)) {
                $endTimeArr[1] = str_replace(' ', '', $endTimeArr[1]);
                $endTimeArr = explode('-', $endTimeArr[1]);
                if (!empty($endTimeArr[1])) {
                    $endTimeArr[1] = str_replace(['时', '分', '秒', '：'], ':', $endTimeArr[1]);
                    $endTimeArr[1] = trim($endTimeArr[1], ':');
                    $endTime = date("Y-m-d", $time) . ' ' . $endTimeArr[1];
                    

                    $endTime = strtotime($endTime);
                    // 判断$endTime是否是一个正常的时间戳
                    if ($endTime === false || $endTime < 0 || $endTime > time()) {
                        $endTime = 0;
                    }
                }
            }
        }

        // 手术者
        $hjnr = str_replace('主治医生', '', $hjnr);
        $caseContent = str_replace(['手术者:', '手术者：'], "|&|手术者||", $hjnr);
        $caseContent = str_replace('助手：', "|&|助手||", $caseContent);
        $caseContentArr = explode("|&|", $caseContent);
        $operationHandler = '';
        if (!empty($caseContentArr[1])) {
            $operationHandlerData = explode('||', $caseContentArr[1]);
            $operationHandler = !empty($operationHandlerData[1]) ? $operationHandlerData[1] : '';
        }

        $eidtData = ['operation_time' => ($time ?: 0), 'operation_end_time' => $endTime, 'operation_handler' => $operationHandler];
        return $eidtData;
    }

}

