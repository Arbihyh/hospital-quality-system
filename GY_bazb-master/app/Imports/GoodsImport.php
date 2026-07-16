<?php

namespace App\Imports;

use App\Model\Implants;
use Carbon\Carbon;
use Illuminate\Support\Collection;

use Maatwebsite\Excel\Concerns\ToCollection;

class GoodsImport implements ToCollection
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
        foreach ($rows as $row){;
            $goodsName = trim($row[1]);
            $manufactor = trim($row[3]);
            $goodsInfo = Implants::query()->where('name','=',$goodsName)->first();
            if (!$goodsInfo) {
                $insertData = ['name'=>$goodsName,'manufactor'=>$manufactor,'created_at'=>Carbon::now()->toDateTimeString()];
                Implants::query()->insert($insertData);
            }
        }

        return true;
    }

}
