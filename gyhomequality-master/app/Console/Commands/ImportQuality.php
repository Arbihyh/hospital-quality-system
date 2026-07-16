<?php

namespace App\Console\Commands;


use Exception;
use Illuminate\Console\Command;
use App\Services\QualityService;
use Illuminate\Support\Facades\Log;
use App\Model\EMR_BL_BL01;
use App\Model\FeeDetailed;
use App\Model\Yzb;
use App\Model\V_JMGS_YMresult;
use App\Model\V_JMGS_TESTRESULT;
class ImportQuality extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quality:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'import quality data';

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
        $params = [
            'index' => 'quality',
        ];
        $res = app('es')->indices()->exists($params);
        if ( !$res ){
            // try {
            //     $res = app('es')->indices()->delete($params);
            //     echo date("Y-m-d H:i:s") . " 删除索引成功！ \n";
            // } catch( Exception $e ){
            //     Log::info(date("Y-m-d H:i:s") . " 删除索引失败 原因：" . $e->getMessage());
            // }
        // }
        // try{
            $params = [
                'index' => 'quality',
                'body' => [
                    //settings 设置
                    'settings' => [
                        'number_of_shards' => 4,
                        'number_of_replicas' => 0,
                        "mapping.nested_objects.limit" => 20000,
                    ],
                    //mappings 映射
                    'mappings' => [
                            'properties' => [
                                //病案号
                                'AAA28' => [
                                    "type" => "keyword",
                                    'index' => 'true',
                                ],
                                //病案首页ID
                                'MED_REC_ID' => [
                                    "type" => "keyword",
                                    "index" => 'true',
                                ],
                                //年龄
                                'AAA04' => [
                                    'type' => 'byte',
                                ],
                                //出院科室
                                'AAC11N' => [
                                    'type' => 'keyword',
                                ],
                                //入院时间
                                'AAB01' => [
                                    'type' => 'date',
                                    'format' => 'yyyy-MM-dd HH:mm:ss'
                                ],
                                //出院时间
                                'AAC01' => [
                                    'type' => 'date',
                                    'format' => 'yyyy-MM-dd HH:mm:ss'
                                ],
                                //住院天数
                                'AAC04' => [
                                    'type' => 'integer'
                                ],
                            ]
                        ],
                ]
            ];
            $ret = app('es')->indices()->create($params);
            $params = [
                'index' => 'other_detailed',
                'body' => [
                    //settings 设置
                    'settings' => [
                        'number_of_shards' => 4,
                        'number_of_replicas' => 0,
                        "mapping.nested_objects.limit" => 20000,
                    ],
                    //mappings 映射
                    'mappings' => [
                            'properties' => [
                                "HJNR" =>[
                                    "type" => 'text',
                                    'index' => true
                                ],
                                //入院时间
                                'AAB01' => [
                                    'type' => 'date',
                                    'format' => 'yyyy-MM-dd HH:mm:ss'
                                ],
                                //出院时间
                                'AAC01' => [
                                    'type' => 'date',
                                    'format' => 'yyyy-MM-dd HH:mm:ss'
                                ],
                            ]
                    ]
                ]
            ];
            $ret = app('es')->indices()->create($params);
            if ( $ret['acknowledged'] ){
                Log::info(date("Y-m-d H:i:s") . "创建索引成功");
            } else {
                Log::info(date("Y-m-d H:i:s") . "创建索引失败");
            }
            die();
        // } catch ( Exception $e ){
        //     Log::info(date("Y-m-d H:i:s") . " 创建索引失败 原因：" . $e->getMessage());
        //     throw new Exception("创建索引失败");
        }
        $start_time = time();
        echo "开始时间：" . date("Y-m-d H:i:s", $start_time) . "\n";
        Log::info(date("Y-m-d H:i:s") . " 获取病例列表");
        $data = QualityService::getAllData();
        echo(date("Y-m-d H:i:s") . " 获取病例数：". count($data) . "\n");
        Log::info(date("Y-m-d H:i:s") . " 获取病例数：". count($data));
        $i = 0;
        $bllb = [1,292,294,303,329,43,79,288,18,34,87];
        foreach( $data as $value ){
            if ( empty($value['AAB01']) ){
                $value['AAB01'] = date("Y-m-d H:i:s", 0);
            }
            if ( empty($value['AAC01']) ){
                $value['AAC01'] = date("Y-m-d H:i:s", 0);
            }
            $params = [
                'index' => 'quality',
                'type' => '_doc',
                'id' => $value["MED_REC_ID"],
                'body' => [
                    "MED_REC_ID" => $value["MED_REC_ID"],
                    'AAA28' => $value['AAA28'],
                    'AAA04' => $value['AAA04'],
                    'AAC11N' => $value['AAC11N'],
                    'AAB01' => $value['AAB01'],
                    'AAC01' => $value['AAC01'],
                    'AAC04' => $value['AAC04'],
                ]
            ];
            try {
                $ret = app('es')->index($params);
                if ( $ret['_shards']['successful'] ){
                    $i ++;
                    echo date("Y-m-d H:i:s") . "录入病案号：" . $value['AAA28'] . " 成功个数". $ret['_shards']['successful'] . "，失败个数：" . $ret['_shards']['failed'] . "\n";
                }
            } catch (Exception $e) {
                echo(date("Y-m-d H:i:s") . " 录入病案号：".  $value['AAA28'] ."失败， 原因：" . $e->getMessage() . "\n");
            }
            //其他详细信息
            $params = [
                'index' => 'other_detailed',
                'type' => '_doc',
                // 'id' => $value["MED_REC_ID"],
            ];
            //获取全病历，根据不同的病例类型，放到不同的字段里
            $emr_list = EMR_BL_BL01::query()
            ->join('EMR_BL_BLXG','EMR_BL_BLXG.BLBH','=','EMR_BL_BL01.BLBH')
            ->where('JZHM',$value['MED_REC_ID'])
            ->whereIn('EMR_BL_BL01.BLLB', $bllb)
            ->groupBy('EMR_BL_BLXG.BLBH')
            ->orderBy('EMR_BL_BLXG.XGSJ')
            ->get(['EMR_BL_BLXG.HJNR','EMR_BL_BL01.BLLB','.EMR_BL_BL01.BLBH'])->toArray();
            if ( !empty($emr_list) ){
                foreach( $emr_list as $emr ){
                    $params['body'] = [];
                    $params['id'] = "EMR_BL_BL01_" . $emr['BLBH'];
                    $params['body']['BLLB'] = $emr['BLLB'];
                    $params['body']['HJNR'] = $emr['HJNR'];
                    $params['body']['AAC01'] = $value['AAC01'];
                    $params['body']['AAC11N'] = $value['AAC11N'];
                    $params['body']['AAA28'] = $value['AAA28'];
                    $params['body']['AAA04'] = $value['AAA04'];
                    $params['body']['AAA40'] = $value['AAA40'];
                    $params['body']['AEM01C'] = $value['AEM01C'];
                    $params['body']['AAB01'] = $value['AAB01'];
                    $params['body']['AAC04'] = $value['AAC04'];

                    $params['body']['ADA01'] = $value['ADA01'];
                    $params['body']['AAA29'] = $value['AAA29'];
                    $params['body']['AAB06C'] = $value['AAB06C'];
                    $params['body']['AAA26C'] = $value['AAA26C'];
                    $params['body']['created_at'] = $value['created_at'];

                    // $params['body']['EMR_BL_BL01_'.$emr['BLLB']][] = ['HJNR' => $emr['HJNR']];
                    $params['body']['MED_REC_ID'] = $value['MED_REC_ID'];
                    $params['body']['mark'] = 'EMR_BL_BL01';
                    $ret = app('es')->index($params);
                }
                
            }
            $fields = ['id', 'ZYH', 'TXM', 'NO', 'XM', 'XB', 'NL', 'CH','YBLX', 'YBZT', 'AAA28','BQ','LCZD','PYJG','XJMC','XJJL','YMMC','YMJG','YMBW','SJYS','JYY','SHY','CJSJ','JSSJ','BGSJ','EXAMINAIM'];
            $ym_result = V_JMGS_YMresult::query()->where('ZYH',$value['MED_REC_ID'])->get()->toArray();
            if ( !empty($ym_result) ){
                $params['body']['V_JMGS_YMRESULT'] = $ym_result;
                foreach( $ym_result as $v ){
                    $params['id'] = "ym_result_" . $v['id'];
                    $params['body'] = [];
                    foreach ( $fields as $f ){
                        $params['body'][$f] = $v[$f];
                    }
                    $params['body']['MED_REC_ID'] = $value['MED_REC_ID'];
                    $params['body']['mark'] = 'ym_result';
                    $ret = app('es')->index($params);
                }
            }
            $fields = ['id', 'ZYH', 'TXM', 'NO', 'XM', 'XB', 'NL', 'CH','YBLX', 'YBZT', 'AAA28','BQ','LCZD','YW','JYXM','JG','TS','CKFW','DW','SJYS','JYY','SHY','CJSJ','JSSJ','BGSJ'];
            $test_result = V_JMGS_TESTRESULT::query()->where('ZYH',$value['MED_REC_ID'])->get()->toArray();
            if ( !empty($test_result) ){
                $params['body']['V_JMGS_TESTRESULT'] = $test_result;
                foreach( $test_result as $v ){
                    $params['id'] = "test_result_" . $v['id'];
                    $params['body'] = [];
                    foreach ( $fields as $f ){
                        $params['body'][$f] = $v[$f];
                    }
                    $params['body']['MED_REC_ID'] = $value['MED_REC_ID'];
                    $params['body']['mark'] = 'test_result';
                    $ret = app('es')->index($params);
                }
            }
            
            
            //获取消费明细
            $fields = ['id', 'FYXH', 'FYMC', 'ZFJE', 'JFRQ', 'FYSL', 'FYDJ', 'ZJE','FYKS', 'FYGB', 'SYFYGB'];
            $feeDetailed = FeeDetailed::query()
            ->where('AAA28',$value['MED_REC_ID'])
            ->orderBy('FYXH')
            ->get($fields)->toArray();
            if ( !empty($feeDetailed) ){
                foreach( $feeDetailed as $v ){
                    $params['id'] = "fee_datailed_" . $v['id'];
                    $params['body'] = [];
                    foreach ( $fields as $f ){
                        $params['body'][$f] = $v[$f];
                    }
                    $params['body']['MED_REC_ID'] = $value['MED_REC_ID'];
                    $params['body']['mark'] = 'fee_detailed';
                    $ret = app('es')->index($params);
                }
            } else {
                // $params['body']['FeeDetailed'] = [];
                // echo(date("Y-m-d H:i:s") . " 获取病案号：". $value['AAA28'] . "的消费明细为空\n");
            }
           
            //医嘱本
            $fields = ['id','YZMC','BRKS','KZSJ','YZQX'];
            $yzb_list = Yzb::query()
            ->where('ZYH',$value['MED_REC_ID'])
            ->get()->toArray();
            if ( !empty($yzb_list) ){
                foreach( $yzb_list as $v ){
                    $params['id'] = "yzb_" . $v['id'];
                    $params['body'] = [];
                    foreach ( $fields as $f ){
                        $params['body'][$f] = $v[$f];
                    }
                    $params['body']['MED_REC_ID'] = $value['MED_REC_ID'];
                    $params['body']['mark'] = 'yzb';
                    $ret = app('es')->index($params);
                }
                // $params['body']['YZB'] = $yzb_list;
            } else {
                // $params['body']['YZB'] = [];
            }
        }
        echo date("Y-m-d H:i:s") . "成功录入".$i."个病案号：" . "\n";
        $end_time = time();
        echo "开始时间：" . date("Y-m-d H:i:s", $end_time) . "\n";
        $time = $end_time - $start_time;
        echo "共消耗时间为：" . $time . "秒\n";
        Log::info(date("Y-m-d H:i:s") . "成功录入".$i."个病案号：");
    }
}
