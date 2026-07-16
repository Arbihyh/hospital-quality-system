<?php

namespace App\Imports;

use App\Model\OmrDepartment;
use Illuminate\Support\Collection;

use Maatwebsite\Excel\Concerns\ToCollection;

class DepartmentImport implements ToCollection
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
        foreach ($rows as $row){
            $depId = trim($row[0]);
            $depName = trim($row[1]);
            if (!$depId || !$depName) {
                continue;
            }

            $depInfo = OmrDepartment::query()->where('dep_id','=',$depId)->first();
            if (!$depInfo) {
                $insertData = [
                    'dep_id' => $depId,
                    'dep_name' => $depName,
                ];

                OmrDepartment::query()->insert($insertData);
            }
        }

        return true;
    }

}
