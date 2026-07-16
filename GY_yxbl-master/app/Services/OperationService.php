<?php

namespace App\Services;

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
    const ID = 5707311;

    public static function checkList()
    {

        $page = 1;
        $pageSize = 10;
        $column = ['BLBH', 'MBLB', 'JZHM'];
        $staff = Staff::query()->get()->toArray();
        $staffArr = [];
        foreach ($staff as $v) {
            $staffArr[md5($v['name'])] = $v['code'];
        }

        while (1) {
            $res = EMR_BL_BL01::getList($page, $pageSize, $column, ['BLLB' => 303, 'MBLB' => [74, 306]]);
            if (!$res) {
                echo '获取数据完毕';
                break;
            }
            foreach ($res as $no) {
                $checkRes = self::checkCase($no['BLBH']);
                if (empty($checkRes)) {
                    continue;
                }
                $eidtData = [
                    'operation_time' => $checkRes['time'],
                    'operation_end_time' => $checkRes['operation_end_time'],
                    'operation_handler' => $checkRes['operation_handler'],
                ];
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
     *
     * ALTER TABLE `quality`.`EMR_BL_BL01`
     * ADD COLUMN `operation_handler` varchar(255) NULL DEFAULT '' COMMENT '手术者' AFTER `bingcheng_content`,
     * ADD COLUMN `operation_handler_code` varchar(255) NULL DEFAULT '' COMMENT '手术者工号' AFTER `operation_handler`;
     */
    public static function checkCase($no = '')
    {
        if (empty($no)) {
            $no = self::ID;
        }
        $res = EMR_BL_BLXG::getById($no);
        if (!$res) {
            return '';
        }
        $hjnr = $res[0]['HJNR'];

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
                    $endTime = date("Y-m-d", $time) . ' ' . $endTimeArr[1];
                    $endTime = strtotime($endTime);
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

        return ['time' => ($time ?: 0), 'operation_end_time' => $endTime, 'operation_handler'=>$operationHandler];
    }

}

