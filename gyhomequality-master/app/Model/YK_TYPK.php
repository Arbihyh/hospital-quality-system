<?php


namespace App\Model;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class YK_TYPK extends Model
{
    protected $table = 'YK_TYPK';
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public static function getPymcList()
    {
        $ypmcData = YK_TYPK::query()->pluck('YPMC')->toArray();
        $ypmcList = [];
        foreach ($ypmcData as $ym) {
            $ym = str_replace("*","",$ym);
            $ym = str_replace('［','[',$ym);
            $stratLen = stripos($ym,'[');
            if ($stratLen === 0) {
                $ym = str_replace('］',']',$ym);
                $stratLen = mb_stripos($ym,']');
                $ym = mb_substr($ym,$stratLen+1);
            }
            $ym = str_replace('［','[',$ym);
            $stratLen = stripos($ym,'[');
            if ($stratLen) {
                $ym = substr($ym,0,$stratLen);
            }

            $ym = str_replace('（','(',$ym);
            $stratLen = stripos($ym,'(');
            if ($stratLen === 0) {
                $ym = str_replace('）',')',$ym);
                $stratLen = mb_stripos($ym,')');
                $ym = mb_substr($ym,$stratLen+1);
            }
            $ym = str_replace('（','(',$ym);
            $stratLen = stripos($ym,'(');
            if ($stratLen) {
                $ym = substr($ym,0,$stratLen);
            }

            $stratLen = stripos($ym,'#');
            if ($stratLen) {
                $ym = substr($ym,0,$stratLen);
            }

            $stratLen = stripos($ym,'{');
            if ($stratLen) {
                $ym = substr($ym,0,$stratLen);
            }

            if (!in_array($ym,$ypmcList)) {
                $ypmcList[] = $ym;
            }
        }

        array_multisort(array_map('strlen', $ypmcList), $ypmcList);
        $ypmcList = array_reverse($ypmcList);

        return $ypmcList;
    }
}
