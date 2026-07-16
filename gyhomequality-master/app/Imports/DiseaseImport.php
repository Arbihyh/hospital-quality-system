<?php

namespace App\Imports;

use App\Http\Service\PinYin2AbbreviationService;
use App\Model\Disease;
use Illuminate\Support\Collection;

use Maatwebsite\Excel\Concerns\ToCollection;

class DiseaseImport implements ToCollection
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {

    }

    /**
     * @param Collection $rows
     * @return void
     */
    public function collection(Collection $rows)
    {
        //如果需要去除表头
        unset($rows[0]);

        //$rows 是数组格式
        $this->createData($rows);
    }

    public function createData($rows)
    {
        $pinyinService = new PinYin2AbbreviationService();
        foreach ($rows as $key => $row){
            if (empty(trim($row[1])) || empty(trim($row[2]))) {
                continue;
            }

            // 科室
            $KSMC = !empty(trim($row[0])) ? trim($row[0]) : '';

            // 查询数据是否存在
            $where = [];
            $where[] = ['KSMC', '=', $KSMC];
            $where[] = ['JBMC', '=', trim($row[1])];
            $diseaseInfo = Disease::query()->where($where)->first();
            if ($diseaseInfo) {
                continue;
            }

            // 序号（标识）
            $FLAG = '';
//            if (!empty($KSMC)) {
//                $ksSzmArr = $pinyinService->getFirstStr($KSMC);
//                if (!empty($ksSzmArr)) {
//                    $FLAG .= implode("",$ksSzmArr).'_';
//                }
//            }
//
//            // 获取首字母
//            $szmArr = $pinyinService->getFirstStr(trim($row[1]));
//            $FLAG .= implode("", $szmArr);

            $insertData = [
                'FLAG' => $FLAG,
                'KSMC' => $KSMC,
                'JBMC' => $row[1] ?? '',
                'BM' => $row[2] ?? '',
                'JBBM' => $row[3] ?? '',
                'JBZD' => $row[4] ?? '',
                'ZZ' => $row[5] ?? '',
                'TZ' => $row[6] ?? '',
                'YP' => $row[7] ?? '',
                'ZL' => $row[8] ?? '',
                'JC' => $row[9] ?? '',
                'JJ' => $row[10] ?? '',
                'BFZ' => $row[11] ?? '',
                'CKWX' => $row[12] ?? '',
                'SSMC' => $row[13] ?? '',
                'SSBM' => $row[14] ?? '',
            ];
            Disease::query()->insert($insertData);
        }

        return true;
    }

}
