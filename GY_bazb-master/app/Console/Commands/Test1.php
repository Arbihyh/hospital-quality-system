<?php

/**
 * Test1 Command
 *
 * This command is used for testing regex functionality on medical text data.
 *
 * @package App\Console\Commands
 */

namespace App\Console\Commands;

use App\Model\Yzb;
use Carbon\Carbon;
use App\Model\Department;
use App\Model\CaseQuality;
use App\Model\EMR_BL_BL01;
use App\Model\RuleWordMap;
use App\Services\EsSaveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\BlDataFormatService;
use App\Services\LanLingIihinterfaceService;
use App\Console\Commands\DataFormat\OMR_BL01;
use App\Model\ZY_BRRY;

use function Ramsey\Uuid\v1;

/**
 * Test1 Command Class
 *
 * Command for testing regex functionality on medical text data.
 * Extracts temperature, pulse, respiration, and blood pressure from medical text.
 */
class Test1 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     * php artisan command:case
     */
    protected $signature = 'test1 {BLBH?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command 定时生成执行相关诊断信息的统计信息';

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
        $matches = [0=>'11',1=>'张22',2=>'33'];
        var_dump(array_values($matches));
        exit;
        $HJNR = '主持人姓名：张玲主任医师
        参加';
        $HJNR = str_replace(['\t', ' ', '\n', '\r', '\r\n'], '', $HJNR);
        preg_match('/主持人姓名：(.*?)\s/u', $HJNR, $matches);
        var_dump($matches);
        exit;

        $testStrings = [
            // '记录时间：2026-04-23 15时25分43秒',
            // '记录时间：2026-04-23 15时25分',
            // '记录时间：2026-04-23 15:25:43',
            // '记录时间：2026-04-23 15:25',
            // '记录时间：2026年04月23日 15时25分43秒',
            // '记录日期：2026年04月23日 15时25分',
            '记录时间：2026年04月23日 15:25',
            '记录日期：2026年04月23日 15:25:43',
            // '记录时间：2026/04/23 15:25:43',
        ];

        // 方案1：分别处理不同的分隔符（最可靠）
        $pattern1 = '/(日期：|^记录时间：)(\d{4}\/\d{2}\/\d{2}\s+\d{2}:\d{2}(?::\d{2})?|\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(?::\d{2})?|\d{4}-\d{2}-\d{2}\s+\d{2}时\d{2}分(?:\d{2}秒)?|\d{4}年\d{2}月\d{2}日\s+\d{2}时\d{2}分(?:\d{2}秒)?|\d{4}年\d{2}月\d{2}日\s+\d{2}:\d{2}|\d{4}年\d{2}月\d{2}日\s+\d{2}:\d{2}:\d{2})/';

        foreach ($testStrings as $str) {
            $matched = false;
            if (preg_match($pattern1, $str, $matches)) {
                echo "原字符串：$str\n";
                echo "提取结果：{$matches[2]}\n";
                echo "---\n";
                $matched = true;
            }
            if (!$matched) {
                echo "原字符串：$str\n";
                echo "提取结果：未匹配\n";
                echo "---\n";
            }
        }
        exit;
        $yzb_name = '记录时间：2026-04-23 15时25分43秒';
        preg_match("/^记录时间：(\d{4}[-年]\d{1,2}[-月]\d{1,2}[日]? \d{2}[:时]+\d{2}[分]?)/", $yzb_name, $timeMatches5);

        var_dump($timeMatches5);
        exit;

        // 解析日期和时间
        $dateTimePattern = '/(\d{4})年(\d{2})月(\d{2})日(\d{2})时/';
        $timeMatches = [];
        preg_match($dateTimePattern, $timeString, $timeMatches);

        if (!empty($timeMatches)) {
            $year = $timeMatches[1];
            $month = $timeMatches[2];
            $day = $timeMatches[3];
            $hour = $timeMatches[4];

            // 构建标准时间格式
            $formattedTime = sprintf('%s-%s-%s %s:00:00', $year, $month, $day, $hour);
            echo "提取的时间: " . $formattedTime . PHP_EOL;

            // 转换为Carbon对象便于后续操作
            $carbonTime = Carbon::createFromFormat('Y-m-d H:i:s', $formattedTime);
            echo "Carbon时间: " . $carbonTime->toDateTimeString() . PHP_EOL;
        }
        var_dump($matches);

        exit;


        $HJNR = "鉴别诊断代理人姓名：孟繁蔚  年龄：76 与委托人（患者）的关系：父母 电话：15098849129代理人姓名：李红艳  年龄：36 与委托人（患者）的关系：配偶 电话：18363246321      本人郑重委托上述代理人作为我本次住院期间的代理人，代为行使我的医疗知情同意及选择的权利，签署有关知情同意书，选择是否承担相应的医疗风险。委托代理人的签字视同本人签字，签署的知情同意书后所产生的后果，由本人承担。上述代理人均为单独代理。 代理人的权限包括但不限于下列内容： 1.了解本人病情、诊疗方案、医疗风险、医疗费用等，对本人的诊治方案做出选择； 2.要实施手术、麻醉、特殊检查/特殊治疗(如有创诊疗操作、使用医保外付费项目、植入性材料等) 时，在相关知情同意书上写清明确意见并签字； 3.了解所接受的药物、医疗器械是否为临床试验和其他医学研究项目，并选择是否参加； 4.保护本人隐私权等。 委托人（患者）签名：  签名时间：2026-03-15 08:15 代理人签名：  签名时间：2026-03-15 08:15  代理人签名：  诊疗计划签名时间：2026-03-15 10:04";

        $replaceCount = 0;
        $pattern = '/鉴别诊断(.*?)诊疗计划/su';
        $HJNR = preg_replace($pattern, '', $HJNR, 1, $replaceCount);
        var_dump($HJNR);

        exit;

        $bltd = '现病史:患者于3小时前无明显诱因出现右侧肢体活动不灵，伴言语不清，表现为不能行走，无恶心、呕吐，无头晕、头痛，无口角流涎，无饮水呛咳、吞咽困难，无视物模糊、视野缺损及复视，无意识障碍，无肢体抽搐，无大小便失禁。在家未行诊疗，送来我院，于急诊行头颅CT、血常规、生化、凝血检查，NHISS评分4分，头CT未见出血，仔细询问病史，无静脉溶栓禁忌症，电话与患者家属沟通静脉溶栓的适应症、必要性、费用及风险等，告知患者血压持续偏高，经积极降压后血压仍高于180/100mmHg，静脉溶栓出血风险高，家属考虑后表示拒绝溶栓，邻居代替签字，以“脑梗死”收住入院。近期患者无发冷、发热，无腹痛、腹泻史。自此次发病以来，未进饮食、未入眠，未大小便。';
        $xbs = '患者于3小时前无明显诱因出现右侧肢体活动不灵，伴言语不清，表现为不能行走，无恶心、呕吐，无头晕、头痛，无口角流涎，无饮水呛咳、吞咽困难，无视物模糊、视野缺损及复视，无意识障碍，无肢体抽搐，无大小便失禁。在家未行诊疗，送来我院，于急诊行头颅CT、血常规、生化、凝血检查，NHISS评分4分，头CT未见出血，仔细询问病史，无静脉溶栓禁忌症，电话与患者家属沟通静脉溶栓的适应症、必要性、费用及风险等，告知患者血压持续偏高，经积极降压后血压仍高于180/100mmHg，静脉溶栓出血风险高，家属考虑后表示拒绝溶栓，邻居代替签字，以“脑梗死”收住入院。近期患者无发冷、发热，无腹痛、腹泻史。自此次发病以来，未进饮食、未入眠，未大小便。发现患“高血压病”30余年，最高血压240/？mmHg，未服药。患“脑梗死”10余年，无后遗症，未服用。患“蛛网膜下腔出血”10年，无后遗症。';
        // 先获取bltd和xbs的字数（不包含标点符号）
        $bltdWordCount = mb_strlen(preg_replace('/[^\p{L}\p{N}]/u', '', $bltd));
        $xbsWordCount = mb_strlen(preg_replace('/[^\p{L}\p{N}]/u', '', $xbs));
        // 如果bltd字数/xbs字数小于0.85就直接返回
        var_dump($bltdWordCount);
        var_dump($xbsWordCount);
        if ($xbsWordCount > 0 && $bltdWordCount / $xbsWordCount < 0.85) {
            return [];
        }

        $bltdArray = preg_split('//u', $bltd, 0, PREG_SPLIT_NO_EMPTY);
        $xbsArray = preg_split('//u', $xbs, 0, PREG_SPLIT_NO_EMPTY);
        $res = array_intersect($bltdArray, $xbsArray);



        var_dump(count($res));
        var_dump(count($bltdArray));
        var_dump(count($res) / count($bltdArray));

        exit;
    }


    public function getEmrContentFromApi($url, $params = [])
    {
        // 构建完整URL参数
        $queryStr = http_build_query($params);
        $requestUrl = $url . (strpos($url, '?') === false ? '?' : '&') . $queryStr;

        // 请求
        $response = @file_get_contents($requestUrl);
        if ($response === false) {
            return [
                'code' => '-1',
                'message' => '请求失败',
                'data' => []
            ];
        }

        // 解析JSON（接口返回格式为JSON）
        $resArr = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($resArr)) {
            return [
                'code' => '-2',
                'message' => '返回数据不是有效的JSON',
                'data' => []
            ];
        }

        // 正常响应结构判断
        if (!isset($resArr['code'], $resArr['data']['content'])) {
            return [
                'code' => '-3',
                'message' => '接口响应结构异常',
                'data' => []
            ];
        }

        $contentXml = $resArr['data']['content'];

        // 尝试解析XML为数组
        if (is_string($contentXml) && !empty($contentXml)) {
            // $contentXml 是一个xml字符串，需要获取其中 BodyText 的内容
            $bodyTextValue = null;
            try {
                $xmlObjTemp = simplexml_load_string($contentXml, 'SimpleXMLElement', LIBXML_NOCDATA);
                if ($xmlObjTemp !== false) {
                    // 支持BodyText作为根节点或各级子节点
                    if (isset($xmlObjTemp->BodyText)) {
                        $bodyTextValue = (string)$xmlObjTemp->BodyText;
                    } else {
                        // 搜索所有节点中的BodyText
                        $bodyTextNodes = $xmlObjTemp->xpath('//BodyText');
                        if (!empty($bodyTextNodes)) {
                            // 有可能多个BodyText节点，取第一个
                            $bodyTextValue = (string)$bodyTextNodes[0];
                        }
                    }
                }
            } catch (\Throwable $e) {
                // 通过正则获取xml中BodyText标签中的内容
                if (empty($bodyTextValue)) {
                    if (preg_match('/<BodyText(?:\s[^>]*)?>(.*?)<\/BodyText>/is', $contentXml, $matches)) {
                        $bodyTextValue = $matches[1];
                    }
                }
            }
            $resArr['data']['content'] = $bodyTextValue;
        }

        return $resArr;
    }

    public function laizhouYZBXH()
    {

        $page = 1;
        $pageSize = 100;
        while (true) {
            // 读取100条数据
            $items = Yzb::query()
                ->select('id', 'YZBXH', 'YZZT')
                ->offset(($page - 1) * $pageSize)
                ->orderBy('id', 'desc')
                ->limit($pageSize)
                ->get();

            if ($items->isEmpty()) {
                break;
            }
            echo '第' . $page . '页，共' . count($items) . '条数据' . PHP_EOL;

            foreach ($items as $item) {
                $newYzbxh = $item->YZBXH . $item->YZZT;
                // 更新操作，这里假设你想把拼接结果回写到 YZBXH_PJ 字段（你可调整字段名）
                Yzb::query()
                    ->where('id', $item->id)
                    ->update(['YZBXH' => $newYzbxh]);
            }

            $page++;
        }
    }
}
