<?php

namespace App\Services;

use App\Model\DiseaseDiagnosisCode;
use App\Model\CaseQuality;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Model\Pacs;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\EventListener\ValidateRequestListener;

/**
 * 病例分析
 */
class PacsService
{
    const ID = 'ZZ18953';

    public static function getPacsDetail($JZLSH='', $ExamType='', $AAB01, $AAC0107)
    {


        $data = PACS::query()->whereRaw("JZLSH='$JZLSH' and ExamType='$ExamType'  and JYSJ>'$AAB01' and JYSJ<'$AAC0107'")->get()->toArray();
        if (!$data) {
            return [];
        }
        return $data;
    }

    public static function getPacsPlatform($PatientID = 0)
    {

        $data = PACS::query()->where('PatientID', '=', $PatientID)->get()->toArray();


        if (!$data) {
            return [];
        } else {
			$type = array();
			foreach($data as $key=>$val) {
				$type[] = array('name'=>$val['JCMC'], 'ExamType'=>$val['ExamType'], 'id'=>$val['StudyUid']);
			}
        }

        return $type;
    }

}

