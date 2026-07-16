<?php


namespace App\Services;


use App\Exports\ExportData;
use Maatwebsite\Excel\Facades\Excel;

class ExportWTAndGKService
{
    public static function WTExport($data,$otherDiagnosis,$icu,$mainOption,$otherOption){
        $config = config('wt');
        $title = array_keys($config['title']);
        $exportData = [];
        foreach ($data as $item){
            $temp = [];
            foreach ($config['title'] as $key =>$value){
                if (is_array($value)){
                    if ($value['type'] == 'qd'){
                        $temp[] = $value['key'] == '' ? '' : ($otherDiagnosis[$item['MED_REC_ID']][$value['id']][$value['key']] ?? '');
                    }elseif ($value['type'] == 'ms'){
                        $temp[] = $value['key'] == '' ? '' : ($mainOption[$item['MED_REC_ID']][$value['key']] ?? '');
                    }elseif ($value['type'] == 'qs'){
                        $temp[] = $value['key'] == '' ? '' : ($otherOption[$item['MED_REC_ID']][$value['id']][$value['key']] ?? '');
                    }elseif ($value['type'] == 'icu'){
                        $temp[] = $value['key'] == '' ? '' : ($icu[$item['MED_REC_ID']][$value['id']][$value['key']] ?? '');
                    }
                }elseif($key == 'JGMC'){
                    $temp[] = '滨州医学院烟台附属医院';
                }elseif($key == 'ZZJGDM'){
                    $temp[] = '08718416-3';
                }elseif($key == 'USERNAME'){
                    $temp[] = '滨州医学院烟台附属医院';
                }elseif($key == 'RYQ_T'){
                    $temp[] = "\t".($item[$value] ?: '-' );
                }elseif($key == 'RYQ_XS'){
                    $temp[] = "\t".($item[$value] ?: '-' );
                }elseif($key == 'RYQ_F'){
                    $temp[] = "\t".($item[$value] ?: '-' );
                }elseif($key == 'RYH_T'){
                    $temp[] = "\t".($item[$value] ?: '-' );
                }elseif($key == 'RYH_XS'){
                    $temp[] = "\t".($item[$value] ?: '-' );
                }elseif($key == 'RYH_F'){
                    $temp[] = "\t".($item[$value] ?: '-' );
                }elseif($key == 'JKKH'){
                    $temp[] = "\t".($item[$value] ?: '-' );
                }elseif($key == 'YLZZBM'){
                    $temp[] = "\t".($item[$value] ?: '-' );
                }elseif($key == 'ZRYSBM'){
                    $temp[] = "\t".($item[$value] ?: '-' );
                }elseif($key == 'ZZYSBM'){
                    $temp[] = "\t".($item[$value] ?: '-' );
                }elseif($key == 'ZYYSBM'){
                    $temp[] = "\t".($item[$value] ?: '-' );
                }elseif($key == 'ZRHSBM'){
                    $temp[] = "\t".($item[$value] ?: '-' );
                }elseif($key == 'ZKYSBM'){
                    $temp[] = "\t".($item[$value] ?: '-' );
                }elseif($key == 'ZKHSBM'){
                    $temp[] = "\t".($item[$value] ?: '-' );
                }else{
                    $temp[] =  $value == '' ? '' : ($item[$value] ?? '');
                }
            }
            $exportData[] = $temp;
        }
        ## 公共导出
        return Excel::download(new ExportData($title,$exportData), $config['fileName']);
    }

    public static function GKExport($data,$otherDiagnosis,$icu,$mainOption,$otherOption){
        $config = config('gk');
        $title = array_keys($config['title']);
        $exportData = [];
        foreach ($data as $item){
            $temp = [];
            foreach ($config['title'] as $key =>$value){
                if (is_array($value)){
                    if ($value['type'] == 'qd'){
                        $temp[] = $value['key'] == '' ? '' : ($otherDiagnosis[$item['MED_REC_ID']][$value['id']][$value['key']] ?? '');
                    }elseif ($value['type'] == 'ms'){
                        $temp[] = $value['key'] == '' ? '' : ($mainOption[$item['MED_REC_ID']][$value['key']] ?? '');
                    }elseif ($value['type'] == 'qs'){
                        $temp[] = $value['key'] == '' ? '' : ($otherOption[$item['MED_REC_ID']][$value['id']][$value['key']] ?? '');
                    }elseif ($value['type'] == 'icu'){
                        $temp[] = $value['key'] == '' ? '' : ($icu[$item['MED_REC_ID']][$value['id']][$value['key']] ?? '');
                    }
                }elseif($key == 'A01'){
                    $temp[] = '08718416-3';
                }elseif($key == 'A02'){
                    $temp[] = '滨州医学院烟台附属医院';
                }elseif($key == 'A47'){
                    $temp[] = "\t".(empty($item[$value]) ? '-' :  $item[$value]);
                }elseif($key == 'B21C'){
                    $temp[] = "\t".(empty($item[$value]) ? '-' :  $item[$value]);
                }elseif($key == 'C09C'){
                    $temp[] = "\t".(empty($item[$value]) ? '-' :  $item[$value]);
                }elseif($key == 'C28'){
                    $temp[] = "\t".(empty($item[$value]) ? '0' :  $item[$value]);
                }elseif($key == 'C29'){
                    $temp[] = "\t".(empty($item[$value]) ? '0' :  $item[$value]);
                }elseif($key == 'C30'){
                    $temp[] = "\t".(empty($item[$value]) ? '0' :  $item[$value]);
                }elseif($key == 'C31'){
                    $temp[] = "\t".(empty($item[$value]) ? '0' :  $item[$value]);
                }elseif($key == 'C32'){
                    $temp[] = "\t".(empty($item[$value]) ? '0' :  $item[$value]);
                }elseif($key == 'C33'){
                    $temp[] = "\t".(empty($item[$value]) ? '0' :  $item[$value]);
                }elseif($key == 'C10N'){
                    $temp[] = "\t".(empty($item[$value]) ? '-' :  $item[$value]);
                }elseif($key == 'C11'){
                    $temp[] = "\t".(empty($item[$value]) ? '-' :  $item[$value]);
                }elseif($key == 'C12C'){
                    $temp[] = "\t".(empty($item[$value]) ? '-' :  $item[$value]);
                }elseif($key == 'C13N'){
                    $temp[] = "\t".(empty($item[$value]) ? '-' :  $item[$value]);
                }elseif($key == 'F21'){
                    $temp[] = "\t".(empty($item[$value]) ? '-' :  $item[$value]);
                }elseif($key == 'F22'){
                    $temp[] = "\t".(empty($item[$value]) ? '-' :  $item[$value]);
                }elseif($key == 'F23'){
                    $temp[] = "\t".(empty($item[$value]) ? '-' :  $item[$value]);
                }elseif($key == 'F24'){
                    $temp[] = "\t".(empty($item[$value]) ? '-' :  $item[$value]);
                }elseif($key == 'F25'){
                    $temp[] = "\t".(empty($item[$value]) ? '-' :  $item[$value]);
                }elseif($key == 'F26'){
                    $temp[] = "\t".(empty($item[$value]) ? '-' :  $item[$value]);
                }else{
                    $temp[] = "\t".($item[$value] ?? '');
                }
            }
            $exportData[] = $temp;
        }
        ## 公共导出
        return Excel::download(new ExportData($title,$exportData), $config['fileName']);
    }
}
