<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Model\CaseQuality;
use App\Model\PatientInfo;
use App\Model\QueueList;
use App\Model\RuleWordMap;
use App\Model\ShizhongSyncZyh;
use App\Model\ZY_HCMX;
use App\Model\PatientAdd;
use App\Services\CaseService;
use App\Services\QualityControlService;
use App\Services\RuyuanService;
use App\Services\ToolsService;
use Illuminate\Http\Request;
use App\Model\CaseRule;
use App\Model\QualitySendMsgLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CaseController extends Controller
{
    public $rule = [
        [
            '101',  // 入院8小时内未创建首次病程记录
        ],
        [
            '99',   // 患者入院后24小时内完成入院记录或24小时出入院记录
        ]
    ];

    /**
     * @param Request $request
     * @param CaseService $caseService
     * @return array
     * 病例质控对外接口
     */
    public function analysis(Request $request)
    {
        Log::info('病例质控对外接口', $request->post());
        $content = $request->post();
        if (empty($content)){
            $json = file_get_contents('php://input');

            Log::info('病例质控对外接口1：' . $json);
            // 检测并移除UTF-8 BOM标记 (EF BB BF)
            if (strpos($json, "\xEF\xBB\xBF") === 0) {
                $json = substr($json, 3);
                Log::info('检测到并移除了UTF-8 BOM标记');
            }

            // 尝试解析JSON
            $content = json_decode(str_replace("\\", '', $json), true);

            // 检查解析是否成功
            if ($content === null && json_last_error() !== JSON_ERROR_NONE) {
                // 解析失败，尝试直接解析（不移除反斜杠）
                $content = json_decode($request->post(), true);

                 // 如果仍然失败，记录错误并返回错误响应
                if ($content === null) {
                        Log::error('JSON解析失败', ['json' => $json, 'error' => json_last_error_msg()]);
                        return ToolsService::returnData(4001, [], '请求的参数格式有误: ' . json_last_error_msg());
                } else {
                        Log::info('直接解析JSON成功（未移除反斜杠）');
                }
            }
        }
        $zyh = $content['ZYH'] ?? "";
        $BLBH = $content['BLBH'] ?? "";
        if (!$zyh) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }
        $edit_docter_document = $content['document'] ?? "";
        $edit_docter = $content['docter'] ?? "";
        $YGDM = $content['YGDM'] ?? "";
        $KSDM = $content['KSDM'] ?? "";



        //$res1['flag'] = 3;
        $caseQuality = CaseQuality::query()->where('JZHM', '=', $zyh)->get()->toArray();
        //获取预警消息数量
        $warningNum = QualitySendMsgLog::query()->where('zyh', '=', $zyh)->where('status', '=', 2)->where('is_warning_msg', '=', 1)->count();
        //错误数量
        $errNum = $caseQuality ? count($caseQuality) : 0;
        //数量相加大于0，sftc=1
        $sftc = $warningNum + $errNum > 0 ? 1 : 0;
        $res1['sftc'] = $sftc;
        $ruleIds = array_column($caseQuality, 'rule_id');
        $ruleNum = CaseRule::query()->whereIn('id', $ruleIds)->where('level', '=', 1)->count();
        $res1['req'] = $ruleNum;

        // 事中质控，设置当前病例信息是质控中的状态
        PatientInfo::query()->where('MED_REC_ID', $zyh)->update(['is_case' => 1]);
//        充当消息队列使用
        QueueList::query()->updateOrInsert(
            ["data"=>$zyh,"type"=>"analysis", "BLBH"=>$BLBH],
            ["created_at"=>date("Y-m-d H:i:s")]
        );


        // 当前提交数据的时间 - 最后一次数据质控成功的时间小于2分钟，则直接返回100分，【2分钟的限制通过字典获取】
        $pti = PatientInfo::query()->where("MED_REC_ID", "=", $zyh)->get(["quality_time"])->toArray();
        $RuleWordMap = RuleWordMap::query()->where("id", "=", 118)->first()->toArray();
        if (!empty($pti) && time() - $pti[0]['quality_time'] < $RuleWordMap['keyword'] * 60) {
            $res1['reg'] = 0;
            return ToolsService::returnData(200, $res1, "");
        }
        return ToolsService::returnData(200, $res1, "");
        exit;
        // V2
        Log::info('病例质控对外接口', $request->post());
        $content = $request->post();
        $zyh = $content['ZYH'];
        if (!$zyh) {
            return ToolsService::returnData(4001, [], '请求的参数有误');
        }
        $res = file_get_contents('http://192.168.60.191:8081/bazb/quality_handle?is_sz=1&zyh=' . $zyh);
        $res = json_decode($res, true);
        Log::info('病例质控结果', $res);
        if ($res['code'] == 200) {
            $res1['flag'] = 3;
            $caseQuality = CaseQuality::query()->where('JZHM', '=', $zyh)->get()->toArray();
            
            $ruleIds = array_column($caseQuality, 'rule_id');
            $ruleNum = CaseRule::query()->whereIn('id', $ruleIds)->where('level', '=', 1)->count();
            
            $res1['req'] = $ruleNum;

            Log::info('病例质控对外接口-返回结果', $res1);
            return ToolsService::returnData(200, $res1, $msg ?? '');
        } else {
            return ToolsService::returnData(4001, [], $res['msg']);
        }


        // V1
        $content = $request->post();
        if (!$content) {
            return ToolsService::returnData(4001, [], '请求的参数有误 - 0');
        }
        $zyh = $content['ZYH'];
        if (!$zyh) {
            return ToolsService::returnData(4001, [], '请求的参数有误，住院号不能为空');
        }
        $res1 = [];
        try {

            $dataHas = ShizhongSyncZyh::query()->where('id', '=', $zyh)->get()->toArray();
            if (empty($dataHas)) {
                ShizhongSyncZyh::query()->insert(['id' => $zyh]);
            } else {
                ShizhongSyncZyh::query()->where('id', '=', $zyh)->update(['status' => 0]);
            }

            // 将质控数据统一设置为已修改
            CaseQuality::query()->where('JZHM', $zyh)->update(['status' => 2]);

            // 病程类质控
            $caseService = new CaseService();
            $res1 = $caseService->qualityContrl($zyh, $content);

            $qualityControl = new QualityControlService();
            $res2 = $qualityControl->qualityContrl($zyh, $content);
            if (!empty($res2)) {
                $res1[] = $res2;
            }

            // 入院记录质控
            $ruyuanService = new RuyuanService();
            $res3 = $ruyuanService->checkCase($zyh, $content);
            Log::info('病例质控对外接口', $res3);
            $res1 = array_merge($res1, $res3);

            $caseRule = CaseRule::query()->get()->toArray();
            $caseRule = array_column($caseRule, null, 'id');

            $res = CaseQuality::getByJZHM($zyh);
            $score = 0;
            if ($res) {
                foreach ($res as &$v) {
                    $score += $caseRule[$v['rule_id']]['score'];
                }
                PatientInfo::query()->where('MED_REC_ID', '=', $zyh)->update(['is_defect_v2' => 1, 'score' => intval(100 - $score)]);
            }

            $code = 200;
        } catch (\Exception $e) {
            $code = 4001;
            $msg = $e->getMessage() . $e->getFile() . $e->getLine();
            Log::info('病例质控对外接口error', ['msg' => $msg]);
        }

        Log::info('病例质控对外接口', $res1);
        $res1['flag'] = 3;
        return ToolsService::returnData($code, $res1, $msg ?? '');
    }

}
