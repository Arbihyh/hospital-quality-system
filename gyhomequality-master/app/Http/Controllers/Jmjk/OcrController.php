<?php

namespace App\Http\Controllers\Jmjk;

use App\Http\Controllers\Controller;
use App\Model\Ocr;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OcrController extends Controller
{
    static $mustExtractEntity = [
        [
            'entity' => '白细胞',
            'company' => ['10^9/L','10^9/l'],
            'symptom' => [
                ['抑郁'],
                ['抑郁'],
                ['焦虑'],
                [],
                ['焦虑'],
                ['抑郁'],
                ['抑郁','应激反应']
            ]
        ],
        [
            'entity' => '中性粒细胞',
            'company' => ['10^9/L','10^9/l'],
            'symptom' => [
                ['严重抑郁'],
                ['抑郁'],
                ['焦虑'],
                [],
                ['紧张','焦虑','恐惧'],
                ['愤怒','易怒','恐惧'],
                ['愤怒','易怒']
            ]
        ],
        [
            'entity' => '中性粒细胞百分比',
            'company' => ['10^9/L','10^9/l'],
            'symptom' => [
                [],
                ['抑郁'],
                ['焦虑'],
                [],
                ['紧张','焦虑','恐惧'],
                ['愤怒','易怒','恐惧'],
                ['愤怒','易怒']
            ]
        ],
        [
            'entity' => '淋巴细胞',
            'company' => ['10^9/L','10^9/l'],
            'symptom' => [
                ['抑郁'],
                ['委屈'],
                ['愤怒'],
                [],
                ['焦虑'],
                ['抑郁','焦虑'],
                ['抑郁']
            ]
        ],
        [
            'entity' => '单核细胞',
            'company' => ['10^9/L','10^9/l'],
            'symptom' => [
                ['抑郁'],
                ['抑郁'],
                ['抑郁'],
                [],
                ['焦虑'],
                ['焦虑'],
                ['焦虑']
            ]
        ],
        [
            'entity' => '嗜酸粒细胞',
            'company' => ['10^9/L','10^9/l'],
            'symptom' => [
                ['抑郁'],
                ['抑郁'],
                ['焦虑'],
                [],
                ['焦虑'],
                ['易怒'],
                ['愤怒','易怒']
            ]
        ],
        [
            'entity' => '嗜碱性粒细胞',
            'company' => ['10^9/L','10^9/l'],
            'symptom' => [
                ['焦虑','抑郁'],
                ['焦虑','抑郁'],
                ['焦虑'],
                [],
                ['恐惧','紧张','焦虑'],
                ['愤怒','恐惧','易怒'],
                ['愤怒','易怒']
            ]
        ],
        [
            'entity' => '红细胞',
            'company' => ['10^12/L','10^12/l'],
            'symptom' => [
                ['抑郁'],
                ['抑郁'],
                ['抑郁'],
                [],
                [],
                ['补铁','饮水少'],
                ['补铁','发烧']
            ]
        ],
        [
            'entity' => '血红蛋白',
            'company' => ['10^12/L','10^12/l'],
            'symptom' => [
                ['抑郁'],
                ['抑郁'],
                ['抑郁'],
                [],
                [],
                ['补铁','饮水少'],
                ['代谢低','发烧','腹泻']
            ]
        ],
        [
            'entity' => '红细胞比容/红细胞压积',
            'company' => ['L/L'],
            'symptom' => [
                ['抑郁'],
                ['抑郁'],
                ['抑郁'],
                [],
                [],
                [],
                []
            ]
        ],
        [
            'entity' => '红细胞平均体积',
            'company' => ['fL','fl'],
            'symptom' => [
                ['抑郁'],
                ['抑郁'],
                ['抑郁'],
                [],
                ['焦虑','紧张'],
                ['抑郁','易怒'],
                ['抑郁','易怒']
            ]
        ],
        [
            'entity' => '平均血红蛋白含量',
            'company' => ['g/L','g/l'],
            'symptom' => [
                ['抑郁'],
                ['抑郁'],
                ['抑郁'],
                [],
                ['焦虑'],
                ['抑郁'],
                ['抑郁']
            ]
        ],
        [
            'entity' => '平均血红蛋白浓度',
            'company' => ['g/L','g/l'],
            'symptom' => [
                ['抑郁'],
                ['抑郁'],
                ['抑郁'],
                [],
                ['焦虑'],
                ['抑郁'],
                ['抑郁']
            ]
        ],
        [
            'entity' => '红细胞分布宽度变异系数',
            'company' => ['%'],
            'symptom' => [
                ['抑郁'],
                ['抑郁'],
                ['抑郁'],
                [],
                ['焦虑'],
                ['焦虑'],
                ['焦虑']
            ]
        ],
        [
            'entity' => '红细胞分布宽度标准差',
            'company' => ['fL','fl'],
            'symptom' => [
                ['抑郁'],
                ['抑郁'],
                ['抑郁'],
                [],
                ['焦虑'],
                ['焦虑'],
                ['焦虑']
            ]
        ],
        [
            'entity' => '血小板',
            'company' => ['10^9/L','10^9/l'],
            'symptom' => [
                [],
                [],
                [],
                [],
                [],
                ['焦抗凝血药','维生素k缺乏虑'],
                ['毛细血管出血','妇科炎症','抑郁']
            ]
        ],
        [
            'entity' => '平均血小板体积',
            'company' => ['fL','fl'],
            'symptom' => [
                [],
                [],
                [],
                [],
                [],
                [],
                []
            ]
        ],
        [
            'entity' => '血小板体积分布宽度',
            'company' => ['fL','fl'],
            'symptom' => [
                [],
                [],
                [],
                [],
                [],
                [],
                []
            ]
        ],
        [
            'entity' => '血小板压积',
            'company' => ['%'],
            'symptom' => [
                [],
                [],
                [],
                [],
                [],
                [],
                []
            ]
        ],
        [
            'entity' => '丙氨酸氨基转氨酶',
            'company' => ['U/L'],
            'symptom' => [
                ['抑郁'],
                ['焦虑','抑郁'],
                ['焦虑'],
                [],
                ['焦虑'],
                ['愤怒','易怒','熬夜','酗酒'],
                ['愤怒','易怒']
            ]
        ],
        [
            'entity' => '天门冬氨酸氨基转移酶',
            'company' => ['U/L'],
            'symptom' => [
                ['抑郁'],
                ['焦虑','抑郁'],
                ['焦虑'],
                [],
                ['紧张','焦虑'],
                ['愤怒','易怒','熬夜','酗酒'],
                ['愤怒','易怒']
            ]
        ],
        [
            'entity' => '碱性磷酸酶',
            'company' => ['U/L'],
            'symptom' => [
                ['抑郁'],
                ['焦虑','抑郁'],
                ['焦虑'],
                [],
                ['紧张','烦躁','焦虑','恐惧'],
                ['愤怒','恐惧'],
                ['愤怒','紧张','烦躁','焦虑','恐惧']
            ]
        ],
        [
            'entity' => 'γ-谷氨酰基转移酶',
            'company' => ['U/L'],
            'symptom' => [
                ['抑郁'],
                ['焦虑','抑郁'],
                ['焦虑'],
                [],
                ['易怒'],
                ['易怒'],
                ['愤怒']
            ]
        ],
        [
            'entity' => '胆碱酯酶',
            'company' => ['U/L'],
            'symptom' => [
                ['抑郁'],
                ['焦虑','抑郁'],
                ['焦虑'],
                [],
                ['紧张'],
                ['恐惧'],
                ['愤怒']
            ]
        ],
        [
            'entity' => '总蛋白',
            'company' => ['g/L','g/l'],
            'symptom' => [
                ['安全感不足','易怒','抑郁','恐惧'],
                ['焦虑','恐惧','节食'],
                ['紧张'],
                [],
                ['焦虑'],
                ['焦虑'],
                ['愤怒','生酮饮食']
            ]
        ],
        [
            'entity' => '白蛋白',
            'company' => ['g/L','g/l'],
            'symptom' => [
                ['抑郁','安全感不足'],
                ['焦虑','恐惧'],
                ['紧张'],
                [],
                ['焦虑'],
                ['愤怒'],
                ['易怒']
            ]
        ],
        [
            'entity' => '球蛋白',
            'company' => ['g/L','g/l'],
            'symptom' => [
                ['抑郁'],
                ['抑郁'],
                ['抑郁'],
                [],
                ['紧张'],
                ['恐惧焦虑'],
                ['安全感不足','易怒','抑郁']
            ]
        ],
        [
            'entity' => '白/球蛋白比值',
            'company' => ['%'],
            'symptom' => [
                ['抑郁','安全感不足','易怒'],
                ['焦虑','恐惧'],
                ['紧张'],
                [],
                ['焦虑'],
                ['愤怒','易怒'],
                ['愤怒','易怒']
            ]
        ],
        [
            'entity' => '总胆红素',
            'company' => ['umol/L','umol/l'],
            'symptom' => [
                ['节食'],
                ['自律'],
                ['自律'],
                [],
                ['焦虑'],
                ['愤怒','抑郁'],
                ['愤怒','抑郁']
            ]
        ],
        [
            'entity' => '直接胆红素',
            'company' => ['umol/L','umol/l'],
            'symptom' => [
                ['节食'],
                ['自律'],
                ['自律'],
                [],
                ['焦虑'],
                ['愤怒','抑郁'],
                ['愤怒','抑郁']
            ]
        ],
        [
            'entity' => '间接胆红素',
            'company' => ['umol/L','umol/l'],
            'symptom' => [
                [],
                [],
                [],
                [],
                ['抑郁'],
                ['抑郁'],
                ['抑郁']
            ]
        ],
        [
            'entity' => '岩藻糖苷酶',
            'company' => ['umol/L','umol/l'],
            'symptom' => [
                [],
                ['抑郁'],
                ['焦虑'],
                [],
                ['委屈'],
                ['委屈'],
                []
            ]
        ],
        [
            'entity' => '尿素',
            'company' => ['umol/L','umol/l'],
            'symptom' => [
                [],
                [],
                [],
                [],
                [],
                [],
                []
            ]
        ],
        [
            'entity' => '尿酸',
            'company' => ['umol/L','umol/l'],
            'symptom' => [
                [],
                [],
                [],
                [],
                ['紧张'],
                ['焦虑','恐惧'],
                ['抑郁']
            ]
        ],
        [
            'entity' => '肌酐',
            'company' => ['umol/L','umol/l'],
            'symptom' => [
                ['抑郁'],
                ['抑郁'],
                ['抑郁'],
                [],
                ['紧张'],
                ['愤怒','焦虑','恐惧'],
                ['愤怒']
            ]
        ],
        [
            'entity' => '葡萄糖',
            'company' => ['umol/L','umol/l'],
            'symptom' => [
                ['抑郁'],
                ['抑郁'],
                ['抑郁'],
                [],
                ['委屈','紧张'],
                ['恐惧'],
                ['愤怒','抑郁']
            ]
        ],
        [
            'entity' => '总胆固醇',
            'company' => ['umol/L','umol/l'],
            'symptom' => [
                ['抑郁'],
                ['抑郁'],
                ['抑郁'],
                [],
                ['委屈','紧张'],
                ['恐惧'],
                ['愤怒','抑郁']
            ]
        ],
        [
            'entity' => '甘油三酯',
            'company' => ['umol/L','umol/l'],
            'symptom' => [
                ['抑郁'],
                ['抑郁'],
                ['抑郁'],
                [],
                ['委屈','紧张'],
                ['恐惧'],
                ['愤怒','抑郁']
            ]
        ],
        [
            'entity' => '高密度脂蛋白',
            'company' => ['umol/L','umol/l'],
            'symptom' => [
                ['抑郁','易怒'],
                ['抑郁','易怒'],
                ['焦虑'],
                [],
                ['兴奋'],
                ['兴奋'],
                ['额外摄入y鱼油类的']
            ]
        ],
        [
            'entity' => '低密度脂蛋白',
            'company' => ['umol/L','umol/l'],
            'symptom' => [
                ['额外摄入y鱼油类的'],
                ['兴奋'],
                ['兴奋'],
                [],
                ['焦虑'],
                ['熬夜','吸烟','嗜酒','抑郁','易怒'],
                ['熬夜','吸烟','嗜酒','焦虑','抑郁','易怒']
            ]
        ],
        [
            'entity' => '小而密低密度脂蛋白',
            'company' => ['umol/L','umol/l'],
            'symptom' => [
                ['额外摄入y鱼油类的'],
                ['兴奋'],
                ['兴奋'],
                [],
                ['焦虑'],
                ['熬夜','吸烟','嗜酒','抑郁','易怒'],
                ['熬夜','吸烟','嗜酒','焦虑','抑郁','易怒']
            ]
        ],
        [
            'entity' => '载脂蛋白A1',
            'company' => ['g/L','g/l'],
            'symptom' => [
                ['抑郁'],
                ['抑郁'],
                ['抑郁'],
                [],
                ['高蛋白饮食','兴奋','突发事件'],
                ['高蛋白饮食','兴奋','突发事件'],
                []
            ]
        ],
        [
            'entity' => '载脂蛋白B',
            'company' => ['g/L','g/l'],
            'symptom' => [
                ['额外摄入y鱼油类的'],
                ['兴奋'],
                ['兴奋'],
                [],
                ['焦虑'],
                ['熬夜','吸烟','嗜酒','抑郁','易怒'],
                ['熬夜','吸烟','嗜酒','焦虑','抑郁','易怒']
            ]
        ],
        [
            'entity' => '肌酸激酶',
            'company' => ['U/L'],
            'symptom' => [
                ['抑郁'],
                ['抑郁'],
                ['焦虑'],
                [],
                ['恐惧','紧张'],
                ['愤怒','恐惧'],
                ['愤怒']
            ]
        ],
        [
            'entity' => '肌酸激酶同工酶',
            'company' => ['U/L'],
            'symptom' => [
                [],
                [],
                [],
                [],
                ['紧张'],
                ['焦虑'],
                ['抑郁']
            ]
        ],
        [
            'entity' => 'α羟基丁酸脱氢酶',
            'company' => ['U/L'],
            'symptom' => [
                [],
                [],
                [],
                [],
                ['委屈'],
                ['紧张'],
                ['愤怒']
            ]
        ],
        [
            'entity' => '乳酸脱氢酶',
            'company' => ['U/L'],
            'symptom' => [
                ['运动量不足','抑郁'],
                ['运动量不足','抑郁'],
                ['运动量不足'],
                [],
                ['紧张'],
                ['突发事件导致的肌肉紧张','突然增加运动量'],
                ['突发事件导致的肌肉紧张','突然增加运动量']
            ]
        ],
        [
            'entity' => '同型半胱氨酸',
            'company' => ['μmol/L','μmol/l'],
            'symptom' => [
                [],
                [],
                [],
                [],
                ['抑郁','易怒','记忆力下降'],
                ['抑郁','易怒','记忆力下降'],
                ['抑郁','易怒','记忆力下降']
            ]
        ],
        [
            'entity' => '钙',
            'company' => ['mmol/L','mmol/l'],
            'symptom' => [
                ['焦虑','抑郁','过度紧张','易怒','愤怒','恐惧'],
                ['焦虑','过度紧张','恐惧'],
                ['过度紧张','恐惧'],
                [],
                ['过量补钙未被身体吸收且代谢不足','血钙高会导致便秘','神经紧张','代谢低'],
                ['过量补钙未被身体吸收且代谢不足','血钙高会导致便秘','神经紧张'],
                ['过量补钙未被身体吸收且代谢不足','血钙高会导致便秘','神经紧张']
            ]
        ],
        [
            'entity' => '钾',
            'company' => ['mmol/L','mmol/l'],
            'symptom' => [
                ['食蔬菜少','节食','烦躁','易怒','抑郁','焦虑'],
                ['食蔬菜少','节食','烦躁','抑郁','焦虑'],
                ['食蔬菜少','节食','烦躁','抑郁','焦虑'],
                [],
                ['食蔬菜多','饮茶多'],
                ['食蔬菜多','饮茶多'],
                ['食蔬菜多','饮茶多']
            ]
        ],
        [
            'entity' => '钠',
            'company' => ['mmol/L','mmol/l'],
            'symptom' => [
                ['抑郁'],
                ['抑郁'],
                ['抑郁'],
                [],
                ['烦躁'],
                ['愤怒'],
                ['愤怒']
            ]
        ],
        [
            'entity' => '氯',
            'company' => ['mmol/L','mmol/l'],
            'symptom' => [
                [],
                [],
                [],
                [],
                [],
                [],
                []
            ]
        ],
        [
            'entity' => '镁',
            'company' => ['mmol/L','mmol/l'],
            'symptom' => [
                ['抑郁'],
                ['抑郁'],
                ['抑郁'],
                [],
                [],
                ['健身多'],
                ['健身多']
            ]
        ],
        [
            'entity' => '总胆汁酸',
            'company' => ['μmol/L','μmol/l','umol/l','umol/L'],
            'symptom' => [
                [],
                [],
                [],
                [],
                [],
                [],
                ['脂肪摄入多']
            ]
        ],
        [
            'entity' => '超敏C反应蛋白',
            'company' => ['mg/L'],
            'symptom' => [
                [],
                [],
                [],
                [],
                ['愤怒','焦虑','抑郁'],
                ['愤怒','焦虑','抑郁'],
                ['愤怒','焦虑','抑郁']
            ]
        ],
    ];
    public function submit(Request $request)
    {
        $file =  $request->file('file');
        if ($file->getSize() > (4*1024*1024)){
            return ToolsService::returnData(0,'','文件大小超过限制');
        }
        $text = self::callOcr($file);
        if (empty($text)){
            return ToolsService::returnData(0,'','文件大小超过限制');
        }
        $data = self::formatTestSheet($text);
        return json_encode($data);
    }
    public static function callOcr($file){
        $appId = '645ee768';
        $apiKey = 'e72fce63bf3994f0b6398cccc7b49cc5';
        $apiSecret = 'OThmNjcyYTU5NTAwNmE4N2E0ZTkxNDM3';
        $url = 'https://api.xf-yun.com/v1/private/sf8e6aca1';
        $date = gmstrftime("%a, %d %b %Y %T %Z",time());
        $host = 'api.xf-yun.com';
        $signature = "host: api.xf-yun.com\ndate: $date\nPOST /v1/private/sf8e6aca1 HTTP/1.1";
        $signature = hash_hmac('sha256',$signature,$apiSecret,true);
        $signature = base64_encode($signature);
        $authorization = 'api_key="'.$apiKey.'",algorithm="hmac-sha256",headers="host date request-line",signature="'.$signature.'"';
        $authorization = base64_encode($authorization);
        $url = $url .'?authorization='.$authorization.'&host='.$host.'&date='.urlencode($date);
        $image = base64_encode(file_get_contents($file->getRealPath()));
        $data = [
            "header" => [
                "app_id"=> $appId,
                "status"=> 3
            ],
            'parameter' => [
                'sf8e6aca1' => [
                    'category' => 'ch_en_public_cloud',
                    'result' => [
                        'encoding' => 'utf8',
                        'compress' => 'raw',
                        'format' => 'json'
                    ]
                ]
            ],
            'payload' => [
                'sf8e6aca1_data_1' => [
                    'encoding' => $file->getClientOriginalExtension(),
                    'status' => 3,
                    'image' => $image
                ]
            ]
        ];
        $strlen = strlen($image);
        Log::info(print_r($strlen,true));
        if (strlen(base64_encode(file_get_contents($file->getRealPath()))) > 10485760){
            return '';
        }
        $data = json_encode($data);
        $result = self::sendPost($url,$data);
        if ($result['header']['code'] == 0){
            return $result['payload']['result']['text'];
        }else{
            return '';
        }
    }
    public static function sendPost($url,$data){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','host: api.xf-yun.com', 'app_id: 645ee768'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $output = curl_exec($ch);
        curl_close($ch);
        $output = json_decode($output,true);
        return $output;
    }
    public static function formatTestSheet($text){
        $text = str_replace(
            [" ","\n","★","*"],
            ['','','',''],
            base64_decode($text));
        $text = json_decode($text,true);
        $wordArray = array_column($text['pages'][0]['lines'],'words');
        foreach ($wordArray as $item){
            $word[] = $item[0];
        }
        $word = array_values(array_filter(array_column($word,'content')));
        $ocr['handled'] = json_encode($word);
        $data = [];
        $count = count($word);
        $template = [];
        for ($i = 0 ; $i < $count ; $i++){
            $value = $word[$i];
            //跳过没用的数据
            if (strpos($value,'.') !== false){
                continue;
            }
            if (strpos($value,'/') !== false){
                continue;
            }
            if (in_array($value,['序号','项目名称','英文缩写','检查结果','单位','采集时间'])){
                continue;
            }
            if (strpos($value,'-') !== false){
                continue;
            }
            $value = preg_replace("/\\d+/",'', $value);
            $value = preg_replace("/\s*（\w+#?）/i",'', $value);
            foreach (self::$mustExtractEntity as $item){
                //匹配实体
                if (empty($value)){
                    continue;
                }
                $valueCount = mb_strpos($value,$item['entity']);
                if ($valueCount !== false){
                    $temp = array_slice($word,$i,6);
                    if (count($temp) < 6){
                        $temp = array_pad($temp,6,'');
                    }
                    $template[] = $temp;
                    $tempValue = '';
                    $tempRange = '';
                    if (preg_match('/\d+$/',$temp[1]) && preg_match("/-|~/",$temp[1]) == 0){
                        $tempValue = $temp[1];
                    }elseif (preg_match('/\d+$/',$temp[2]) && preg_match("/-|~/",$temp[2]) == 0){
                        $tempValue = $temp[2];
                    }elseif (preg_match('/\d+$/',$temp[3]) && preg_match("/-|~/",$temp[3]) == 0){
                        $tempValue = $temp[3];
                    }elseif (preg_match('/\d+$/',$temp[4]) && preg_match("/-|~/",$temp[4]) == 0){
                        $tempValue = $temp[4];
                    }elseif (preg_match('/\d+$/',$temp[5]) && preg_match("/-|~/",$temp[5]) == 0){
                        $tempValue = $temp[5];
                    }
                    $templateArray[] = $tempValue;
                    if (preg_match('/\d+-|~+\d+/',$temp[2])){
                        $tempRange = $temp[2];
                    }elseif (preg_match('/\d+-|~+\d+/',$temp[3])){
                        $tempRange = $temp[3];
                    }elseif (preg_match('/\d+-|~+\d+/',$temp[4])){
                        $tempRange = $temp[4];
                    }elseif (preg_match('/\d+-|~+\d+/',$temp[5])){
                        $tempRange = $temp[5];
                    }
                    $tempRangeArray[] = $tempRange;
                    if (empty($tempValue) || empty($tempRange)){
                        continue;
                    }else{
                        $tempRange = preg_replace("/[a-z]*\/[a-z]*/i",'', $tempRange);
                        $strCount1 = substr_count($tempRange,'-');
                        $strCount2 = substr_count($tempRange,'~');
                        if (empty($strCount1)){
                            $delimter = $strCount2 == 2 ? '~~' : '~';
                        }else{
                            $delimter = $strCount1 == 2 ? '--' : '-';
                        }
                        $range = explode($delimter,$tempRange);
                        if(!is_numeric($tempValue)){
                            continue;
                        }
                        $sub1 = sprintf('%.2f',$tempValue - $range[0]);
                        $sub2 = sprintf('%.2f',$range[1] - $range[0]);
                        if ($sub2 == 0){
                            continue;
                        }
                        $Calculation = intval(bcdiv($sub1,$sub2,2) * 100);
                        if ($Calculation < 0){
                            $index = 0;
                        }elseif ($Calculation < 20){
                            $index = 1;
                        }elseif ($Calculation < 40){
                            $index = 2;
                        }elseif ($Calculation < 60){
                            $index = 3;
                        }elseif ($Calculation < 80){
                            $index = 4;
                        }elseif ($Calculation < 100){
                            $index = 5;
                        }else{
                            $index = 6;
                        }
                        $data[] = [
                            'entity' => $value,
                            'value' => $tempValue,
                            'range' => $tempRange,
                            'calculation' => $Calculation,
                            'symptom' => $item['symptom'][$index]
                        ];
                    }
                }else{
                    continue;
                }
            }
        }
        $ocr['calculation'] = json_encode($data);
        $array = array_column($data,'symptom');
        $tem = [];
        foreach ($array as $item){
            $tem = array_merge($item,$tem);
        }
        $tem = array_count_values($tem);
        $symptom = '';
        foreach ($tem as $key=>$value){
            $symptom .= $key." + ".$value.'; ';
        }
        Ocr::query()->insert($ocr);
        return ['list'=>$data,'symptom'=>$symptom];
    }

    public static function ocr_test(Request $request){
        $file =  $request->file('file');
//        $data = file_get_contents($file->getPath());
        $obj = fopen($file->getRealPath(),'rb');
        $data = fread($obj,$file->getSize());
        fclose($obj);
        $imageData = json_encode(['imgdata'=>base64_encode($data)]);
        $url = 'http://192.210.196.127:9001';
        $result = self::sendPost($url,$imageData);
        dd($result);
    }
    public static function sendPaddle($url,$data){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type:multipart/form-data;"));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $output = curl_exec($ch);
        curl_close($ch);
        $output = json_decode($output,true);
        return $output;
    }
    public static function paddle(Request $request){
        $file =  $request->file('file');
        $path = $file->getRealPath();
//        $data['f'] = '@'.$path;
        $url = 'http://182.44.15.118:5000/upload';
        $data = new \CURLFile($path,'image/jpeg','upload.png');
        $result = self::sendPaddle($url,['file'=>$data]);
        if ($result['message'] != '识别成功') {
            return ToolsService::returnData(0,'','识别失败');
        }
        $ocr = array_column(array_column($result['ocr_result'],1),0);
        return json_encode(self::formatPaddle($ocr));
    }
    public static function formatPaddle($word){
        $data = [];
        $count = count($word);
        for ($i = 0 ; $i < $count ; $i++){
            $value = $word[$i];
            //跳过没用的数据
            if (strpos($value,'.') !== false){
                continue;
            }
            if (strpos($value,'/') !== false){
                continue;
            }
            if (in_array($value,['序号','项目名称','英文缩写','检查结果','单位','采集时间'])){
                continue;
            }
            if (strpos($value,'-') !== false){
                continue;
            }
            $value = preg_replace("/\\d+/",'', $value);
            $value = preg_replace("/\s*（\w+#?）/i",'', $value);
            foreach (self::$mustExtractEntity as $item){
                //匹配实体
                if (empty($value)){
                    continue;
                }
                $valueCount = mb_strpos($value,$item['entity']);
                if ($valueCount !== false){
                    $temp = array_slice($word,$i,6);
                    if (count($temp) < 6){
                        $temp = array_pad($temp,6,'');
                    }
                    $tempValue = '';
                    $tempRange = '';
                    if (preg_match('/\d+$/',$temp[1]) && preg_match("/-|~/",$temp[1]) == 0){
                        $tempValue = $temp[1];
                    }elseif (preg_match('/\d+$/',$temp[2]) && preg_match("/-|~/",$temp[2]) == 0){
                        $tempValue = $temp[2];
                    }elseif (preg_match('/\d+$/',$temp[3]) && preg_match("/-|~/",$temp[3]) == 0){
                        $tempValue = $temp[3];
                    }elseif (preg_match('/\d+$/',$temp[4]) && preg_match("/-|~/",$temp[4]) == 0){
                        $tempValue = $temp[4];
                    }elseif (preg_match('/\d+$/',$temp[5]) && preg_match("/-|~/",$temp[5]) == 0){
                        $tempValue = $temp[5];
                    }
                    if (preg_match('/\d+-|~+\d+/',$temp[2])){
                        $tempRange = $temp[2];
                    }elseif (preg_match('/\d+-|~+\d+/',$temp[3])){
                        $tempRange = $temp[3];
                    }elseif (preg_match('/\d+-|~+\d+/',$temp[4])){
                        $tempRange = $temp[4];
                    }elseif (preg_match('/\d+-|~+\d+/',$temp[5])){
                        $tempRange = $temp[5];
                    }
                    $tempRangeArray[] = $tempRange;
                    if (empty($tempValue) || empty($tempRange)){
                        continue;
                    }else{
                        $tempRange = preg_replace("/[a-z]*\/[a-z]*/i",'', $tempRange);
                        $strCount1 = substr_count($tempRange,'-');
                        $strCount2 = substr_count($tempRange,'~');
                        if (empty($strCount1)){
                            $delimter = $strCount2 == 2 ? '~~' : '~';
                        }else{
                            $delimter = $strCount1 == 2 ? '--' : '-';
                        }
                        $range = explode($delimter,$tempRange);
                        if(!is_numeric($tempValue)){
                            continue;
                        }
                        $sub1 = sprintf('%.2f',$tempValue - $range[0]);
                        $sub2 = sprintf('%.2f',$range[1] - $range[0]);
                        if ($sub2 == 0){
                            continue;
                        }
                        $Calculation = intval(bcdiv($sub1,$sub2,2) * 100);
                        if ($Calculation < 0){
                            $index = 0;
                        }elseif ($Calculation < 20){
                            $index = 1;
                        }elseif ($Calculation < 40){
                            $index = 2;
                        }elseif ($Calculation < 60){
                            $index = 3;
                        }elseif ($Calculation < 80){
                            $index = 4;
                        }elseif ($Calculation < 100){
                            $index = 5;
                        }else{
                            $index = 6;
                        }
                        $data[] = [
                            'entity' => $value,
                            'value' => $tempValue,
                            'range' => $tempRange,
                            'calculation' => $Calculation,
                            'symptom' => $item['symptom'][$index]
                        ];
                    }
                }
            }
        }
        $array = array_column($data,'symptom');
        $tem = [];
        foreach ($array as $item){
            $tem = array_merge($item,$tem);
        }
        $tem = array_count_values($tem);
        $symptom = '';
        foreach ($tem as $key=>$value){
            $symptom .= $key." + ".$value.'; ';
        }
        return ['list'=>$data,'symptom'=>$symptom];
    }
}
