<?php

namespace App\Model;


use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Facades\Excel as Excels;
use App\Exports\DataExport;

class Excel extends Model
{

    public function export($fileName=null,$rows=null,$list=null)
    {
        $fileName = $fileName ? : 'test';
        //将生成的Excel保存到本地，在服务器端使用时注意要给文件夹权限
        $row[] = $rows ? : ["name" => "姓名", "sex" => "性别",];
        $list = $list ? : [['name' => '张三', 'sex'  => '男']];

//        return Excels::download(new DataExport($row,$list), date('Y:m:d ') . '用户列表.xls');
//        exit;

//        return 123;

        Excels::store(new DataExport($row,$list),$fileName.'.xlsx'  ,"public");
        $path = "/storage/{$fileName}";



        return $path;

    }



}

