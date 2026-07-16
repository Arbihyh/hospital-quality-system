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
 *
 */
class OperationService
{
    const ID = 5707311;

    public static function checkList()
    {

        $page = 1;
        $pageSize = 10;
        $column = ['BLBH', 'MBLB'];
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
                $eidtData = ['operation_time' => $checkRes];
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
    public static function checkCase($no = '')
    {
        if (empty($no)) {
            $no = self::ID;
        }
        $res = EMR_BL_BLXG::getById($no);
        if (!$res) {
            return '';
        }
        $caseContent = $res[0]['HJNR'];

        $caseContent = str_replace("\r\n", "", $caseContent);
        $caseContent = str_replace("\n", "", $caseContent);
        $caseContent = str_replace('手术日期：', "|&|手术日期||", $caseContent);
        $caseContent = str_replace('手术时间：', "|&|手术时间||", $caseContent);

        $caseContentArr = explode("|&|", $caseContent);
        $time = explode('||', $caseContentArr[1]);
        $operationDate = trim($time[1]);
        $operationDateRes = date_parse_from_format("Y年m月d日", $operationDate);
        $time = mktime(0,0,0, $operationDateRes['month'], $operationDateRes['day'], $operationDateRes['year']);

        return $time ?: 0;
    }

}

