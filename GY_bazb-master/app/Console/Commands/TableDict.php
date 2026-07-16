<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CaseService;
use Illuminate\Support\Facades\DB;

class TableDict extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'command:table_dict';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 定时生成执行解析入院记录的信息，并保存到 EMR_BL_BL01 的 analysis_case 字段';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     *
     */
    public function handle()
    {
        echo 'command start::';
        $tableName = env('DB_DATABASE');
        $sql = 'SELECT * FROM information_schema.TABLES where TABLE_SCHEMA = "'.$tableName.'"';
        $res = DB::select($sql);
        $addData = [];
        foreach ($res as $k => $v) {
            $addData[] = ['parent_field'=>'0','field'=>$v->TABLE_NAME, 'field_name'=>$v->TABLE_COMMENT];
            $res1 = DB::select('select * from information_schema.COLUMNS where TABLE_SCHEMA = "'.$tableName.'" AND TABLE_NAME="'.$v->TABLE_NAME.'"');
            foreach ($res1 as $key => $v1) {
                $addData[] = ['parent_field'=>$v1->TABLE_NAME,'field'=>$v1->COLUMN_NAME, 'field_name'=>$v1->COLUMN_COMMENT];
            }

        }
        // 添加新表数据
        foreach ($addData as $item) {
            $where = ['parent_field'=>$item['parent_field'], 'field'=>$item['field']];
            \App\Model\TableDict::query()->updateOrInsert($where, $item);
        }

        // 删除不存在的表数据
        $exitsTable = array_column($addData, 'parent_field');
        $exitsTable = array_unique($exitsTable);
        $tabledict = \App\Model\TableDict::query()->where('parent_field', '=', '0')->get()->toArray();
        foreach ($tabledict as $item) {
            if(!in_array($item['field'], $exitsTable)){
                \App\Model\TableDict::query()->where('parent_field', '=', $item['field'])->update(['status'=>2]);
                \App\Model\TableDict::query()->where('parent_field', '=', $item['field'])->update(['status'=>2]);
            }
        }

        echo 'AnalysisCase end';
    }

}
