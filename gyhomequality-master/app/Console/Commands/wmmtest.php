<?php

namespace App\Console\Commands;

use App\Model\Coder;
use App\Model\Error;
use App\Model\OtherDiagnosis;
use App\Services\ErrorRuleService;
use App\Services\MedicalRecordService;
use App\Services\OperationRelationsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pheanstalk\Pheanstalk;

class wmmtest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:wmmtest';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'wangmeng测试';

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
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        ini_set('default_socket_timeout', 24 * 60 * 60);

        $query = Error::query();
        $page = 1;
        $offset = ($page - 1) * 10;
        $query->where('source', 0);
        $list = Error::query()
            ->groupBy('year')
            ->groupBy('month')
            ->groupBy('error_rule')
            //->orderBy('count','desc')
            ->offset($offset)
            ->limit(100)
            ->get(['error_rule','month','year',DB::raw('count(`id`) as count'), DB::raw('error_name as field'),'desc','level'])->toArray();
        print_r($list);
        //echo $this->lengthOfLongestSubstring('abcabcbb');
        //echo $this->findMaxLenSub('abcabcbb');
        exit;
        $filePath = dirname(public_path(''))."/app/Console/Commands/fee.csv";
        //$filePath = 'fee.csv';
        $data = $this->getCsvData($filePath);
        foreach ($data as $index=>$row){
            if(!is_array($row)){
                break;
            }
            //print_r($row);
            $price = [];
            $price[] = $row[4] ?? '';
            $price[] = $row[5] ?? '';
            $price[] = $row[6] ?? '';
            $price = array_filter($price);
            $price = implode(',', $price);
            echo $index . PHP_EOL;
            //print_r($row);
            OperationRelationsService::addOperationRelations($row[1], $row[2], $row[0], $price, $row[3]);
        }
        exit;
        OperationRelationsService::addOperationRelations('test', 'test', 'test', '10.00', '小时');
        OperationRelationsService::editOperationRelationsById(5, 'test1', 'test', 'test', '10.00', '小时');
        OperationRelationsService::delOperationRelationsById(7);
        $data = OperationRelationsService::relationsList('', '', '', 1, 10);
        print_r($data);
    }

    function lengthOfLongestSubstring($s) {

        $l = strlen($s); //获取字符串总长度
        
        $len = 0;   //记录长度
        
        $find = ''; //保存截取字符串
        
        for($i=0;$i<$l;$i++){
        
            $res = strpos($find,$s[$i]); // 查找$find中是否存在
        
            if($res !== false){
            
            $find.=$s[$i];
            
            $find = substr($find,$res+1);
            
            }else{
            
            $find.=$s[$i];
            
            }
            echo $find . PHP_EOL;
            $len = strlen($find) > $len ? strlen($find) : $len;
        
        }
        return $len;    
    }

    function findMaxLenSub(string $str) {
        //用于模拟滑动窗口
        //temp中存储的是（字符=>位置）的键值对
        $temp = array();
        $len = strlen($str);
        //备选子串开始位置
        $start = 0;
        //备选子串长度
        $len_temp = 0;
        //最大子串开始位置
        $maxStart = 0;
        //最大子串长度
        $maxLen = 0;
        for ($i = 0; $i < $len; $i++) {
            //i处的字符
            $chr = $str[$i];
            var_dump($chr);
            //如果字符在temp数组中，且其在temp数组中记录的位置大于等于备选子串的开始位置，
            //说明有备选子串中有相同字符，需要重新生成备选子串。
            if (array_key_exists($chr, $temp) && $temp[$chr] >= $start) {
                //备选子串的开始位置为重复字符的下一个位置
                $start = $temp[$chr] + 1;
                //备选子串的初始长度为两个相同字符
                $len_temp = $i - $temp[$chr];
            } else {
                $len_temp++;
                if ($len_temp > $maxLen) {
                    //备选子串长度如果大于maxLen,备选子串就转正了。
                    $maxLen = $len_temp;
                    $maxStart = $start;
                }
            }
            $temp[$chr] = $i;
        }
        return $maxLen;
    }    

    function getCsvData($filePath){
        //return [];
        $handle = fopen( $filePath, "rb" );
        $data = [];
        while (!feof($handle)) {
            $data[] = fgetcsv($handle);
        }
        fclose($handle);
        //$data = eval('return ' . iconv('gb2312', 'utf-8', var_export($data, true)) . ';');//字符转码操作
        return $data;
    }
}
